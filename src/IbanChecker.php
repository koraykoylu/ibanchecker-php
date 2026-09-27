<?php

declare(strict_types=1);

namespace IbanChecker;

use IbanChecker\Exception\ApiException;
use IbanChecker\Exception\AuthenticationException;
use IbanChecker\Exception\BadRequestException;
use IbanChecker\Exception\IbanCheckerException;
use IbanChecker\Exception\NotFoundException;
use IbanChecker\Exception\RateLimitException;
use IbanChecker\Model\BankRecord;
use IbanChecker\Model\BatchResult;
use IbanChecker\Model\FormatSpec;
use IbanChecker\Model\ValidationResult;

/**
 * Client for the ibanchecker.cash IBAN validation API.
 *
 * Validate IBANs across 92 countries, validate up to 100 IBANs per request,
 * extract IBANs from free text, look up country format specifications, and
 * resolve SWIFT/BIC codes.
 *
 * Every method except getFormat() needs an API key; without one the API
 * answers 401 and an AuthenticationException is thrown. A free key covers
 * validate(), 100 requests a month: https://ibanchecker.cash/api-docs.
 * validateBulk() and lookupBic() need the Basic plan or above, and extract()
 * the Growth plan or above. A key whose email address has a verified account
 * at https://ibanchecker.cash/dashboard can try the methods its plan lacks,
 * at a smaller size. A call outside the key's plan gets 403 with the error
 * code PLAN_REQUIRED, thrown as an ApiException. getFormat() works without a
 * key, limited to 100 requests an hour per IP.
 *
 *     $client = new IbanChecker('YOUR_API_KEY');
 *     $result = $client->validate('DE89 3704 0044 0532 0130 00');
 *     if ($result->valid) {
 *         echo $result->bankName, ' ', $result->bic;
 *     }
 */
final class IbanChecker
{
    public const VERSION = '0.1.2';
    public const DEFAULT_BASE_URL = 'https://ibanchecker.cash/api/v1';

    private ?string $apiKey;
    private string $baseUrl;
    private Transport $transport;

    public function __construct(
        ?string $apiKey = null,
        string $baseUrl = self::DEFAULT_BASE_URL,
        float $timeout = 10.0,
        ?Transport $transport = null
    ) {
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->transport = $transport ?? new CurlTransport($timeout);
    }

    /**
     * Validate a single IBAN. Any key can call this, a free key included,
     * and each call counts one request.
     *
     * A malformed IBAN is not an exception: the result comes back with
     * valid = false and an error plus errorCode explaining why.
     */
    public function validate(string $iban): ValidationResult
    {
        return ValidationResult::fromArray(
            $this->request('POST', '/validate', ['iban' => $iban])
        );
    }

    /**
     * Validate up to 100 IBANs in one request. Results come back in the same
     * order as the input.
     *
     * Needs a key on the Basic plan or above. A key with a verified account
     * can try it with up to 10 IBANs per call; more gets a
     * BadRequestException (TOO_MANY_IBANS). Each IBAN counts one request.
     *
     * @param iterable<string> $ibans
     */
    public function validateBulk(iterable $ibans): BatchResult
    {
        $list = [];
        foreach ($ibans as $iban) {
            $list[] = (string) $iban;
        }

        return BatchResult::fromArray(
            $this->request('POST', '/validate/bulk', ['ibans' => $list])
        );
    }

    /**
     * Scan free text (emails, invoices) for IBAN-shaped strings and validate
     * each candidate. Up to 50,000 characters per request.
     *
     * Needs a key on the Growth plan or above. A key with a verified account
     * can try it with up to 5,000 characters per call; more gets a
     * BadRequestException (TEXT_TOO_LONG). Each IBAN found counts one
     * request, with at least one per call.
     */
    public function extract(string $text): BatchResult
    {
        return BatchResult::fromArray(
            $this->request('POST', '/extract', ['text' => $text])
        );
    }

    /**
     * The IBAN format specification for an ISO 3166-1 alpha-2 country code,
     * for example "DE".
     *
     * Works without a key, limited to 100 requests an hour per IP.
     */
    public function getFormat(string $country): FormatSpec
    {
        return FormatSpec::fromArray(
            $this->request('GET', '/formats/' . rawurlencode(strtolower($country)))
        );
    }

    /**
     * Resolve an 8 or 11 character ISO 9362 BIC to a bank record.
     *
     * Needs a key on the Basic plan or above, or a key with a verified
     * account on a trial; it no longer works without a key. Without one the
     * API answers 401 (AuthenticationException); a key whose plan lacks it
     * gets 403 PLAN_REQUIRED (ApiException). Each call counts one request.
     */
    public function lookupBic(string $bic): BankRecord
    {
        return BankRecord::fromArray(
            $this->request('GET', '/swift/' . rawurlencode(strtoupper($bic)))
        );
    }

    /**
     * @param  array<string, mixed>|null $payload
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $payload = null): array
    {
        $headers = [
            'Accept' => 'application/json',
            'User-Agent' => 'ibanchecker-php/' . self::VERSION,
        ];
        if ($this->apiKey !== null && $this->apiKey !== '') {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        }

        $body = null;
        if ($payload !== null) {
            $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($encoded === false) {
                throw new IbanCheckerException('Could not encode the request body: ' . json_last_error_msg());
            }
            $body = $encoded;
            $headers['Content-Type'] = 'application/json';
        }

        $response = $this->transport->send($method, $this->baseUrl . $path, $headers, $body);
        $status = $response['status'];

        $decoded = json_decode($response['body'], true);
        $data = is_array($decoded) ? $decoded : [];

        if ($status >= 400) {
            $message = is_string($data['error'] ?? null) ? $data['error'] : 'HTTP ' . $status;
            $errorCode = is_string($data['error_code'] ?? null) ? $data['error_code'] : null;

            throw match ($status) {
                400 => new BadRequestException($message, $status, $errorCode, $data),
                401 => new AuthenticationException($message, $status, $errorCode, $data),
                404 => new NotFoundException($message, $status, $errorCode, $data),
                429 => new RateLimitException($message, $status, $errorCode, $data),
                default => new ApiException($message, $status, $errorCode, $data),
            };
        }

        if ($decoded === null && trim($response['body']) !== '') {
            throw new ApiException('The API returned a body that is not JSON', $status);
        }

        return $data;
    }
}

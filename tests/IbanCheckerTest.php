<?php

declare(strict_types=1);

namespace IbanChecker\Tests;

use IbanChecker\Exception\ApiException;
use IbanChecker\Exception\AuthenticationException;
use IbanChecker\Exception\BadRequestException;
use IbanChecker\Exception\IbanCheckerException;
use IbanChecker\Exception\NotFoundException;
use IbanChecker\Exception\RateLimitException;
use IbanChecker\IbanChecker;
use IbanChecker\Model\ValidationResult;
use IbanChecker\Transport;
use PHPUnit\Framework\TestCase;

/** Records what it was asked for and replays a canned response. */
final class FakeTransport implements Transport
{
    /** @var list<array{method: string, url: string, headers: array<string, string>, body: string|null}> */
    public array $calls = [];

    private int $status;
    private string $payload;

    /** @param array<string, mixed> $body */
    public function __construct(int $status, array $body)
    {
        $this->status = $status;
        $this->payload = (string) json_encode($body);
    }

    public function send(string $method, string $url, array $headers, ?string $body): array
    {
        $this->calls[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];

        return ['status' => $this->status, 'body' => $this->payload];
    }
}

final class IbanCheckerTest extends TestCase
{
    public function testValidateReturnsAPopulatedResult(): void
    {
        $transport = new FakeTransport(200, [
            'valid' => true,
            'iban' => 'DE89370400440532013000',
            'formatted' => 'DE89 3704 0044 0532 0130 00',
            'country' => 'DE',
            'country_name' => 'Germany',
            'bank_name' => 'Commerzbank AG Cologne',
            'bic' => 'COBADEFFXXX',
            'sepa' => true,
            'national_check_valid' => true,
        ]);

        $result = (new IbanChecker(null, IbanChecker::DEFAULT_BASE_URL, 10.0, $transport))
            ->validate('DE89 3704 0044 0532 0130 00');

        $this->assertInstanceOf(ValidationResult::class, $result);
        $this->assertTrue($result->valid);
        $this->assertSame('Germany', $result->countryName);
        $this->assertSame('COBADEFFXXX', $result->bic);
        $this->assertTrue($result->sepa);
        $this->assertTrue($result->nationalCheckValid);
        $this->assertSame('DE89370400440532013000', $result->raw['iban']);

        $call = $transport->calls[0];
        $this->assertSame('POST', $call['method']);
        $this->assertSame(IbanChecker::DEFAULT_BASE_URL . '/validate', $call['url']);
        $this->assertSame('{"iban":"DE89 3704 0044 0532 0130 00"}', $call['body']);
    }

    public function testAMalformedIbanIsAResultNotAnException(): void
    {
        $transport = new FakeTransport(200, [
            'valid' => false,
            'iban' => 'XX00',
            'error' => 'IBAN is too short',
            'error_code' => 'INVALID_LENGTH',
        ]);

        $result = (new IbanChecker(null, IbanChecker::DEFAULT_BASE_URL, 10.0, $transport))->validate('XX00');

        $this->assertFalse($result->valid);
        $this->assertSame('INVALID_LENGTH', $result->errorCode);
        $this->assertNull($result->nationalCheckValid);
    }

    public function testApiKeyBecomesABearerHeaderAndIsOmittedWithoutOne(): void
    {
        $withKey = new FakeTransport(200, ['valid' => true, 'iban' => 'DE89370400440532013000']);
        (new IbanChecker('iban_test_key', IbanChecker::DEFAULT_BASE_URL, 10.0, $withKey))->validate('DE89');
        $this->assertSame('Bearer iban_test_key', $withKey->calls[0]['headers']['Authorization']);

        $withoutKey = new FakeTransport(200, ['valid' => true, 'iban' => 'DE89370400440532013000']);
        (new IbanChecker(null, IbanChecker::DEFAULT_BASE_URL, 10.0, $withoutKey))->validate('DE89');
        $this->assertArrayNotHasKey('Authorization', $withoutKey->calls[0]['headers']);
    }

    public function testValidateBulkKeepsInputOrderAndCounts(): void
    {
        $transport = new FakeTransport(200, [
            'count' => 2,
            'valid_count' => 1,
            'invalid_count' => 1,
            'results' => [
                ['valid' => true, 'iban' => 'DE89370400440532013000'],
                ['valid' => false, 'iban' => 'XX00'],
            ],
        ]);

        $batch = (new IbanChecker(null, IbanChecker::DEFAULT_BASE_URL, 10.0, $transport))
            ->validateBulk(['DE89370400440532013000', 'XX00']);

        $this->assertSame(2, $batch->count);
        $this->assertSame(1, $batch->validCount);
        $this->assertSame(1, $batch->invalidCount);
        $this->assertCount(2, $batch);

        $ibans = [];
        foreach ($batch as $result) {
            $ibans[] = $result->iban;
        }
        $this->assertSame(['DE89370400440532013000', 'XX00'], $ibans);
    }

    public function testExtractReadsIbansOutOfText(): void
    {
        $transport = new FakeTransport(200, [
            'count' => 1,
            'valid_count' => 1,
            'invalid_count' => 0,
            'results' => [['valid' => true, 'iban' => 'DE89370400440532013000', 'bank_name' => 'Commerzbank AG Cologne']],
        ]);

        $batch = (new IbanChecker(null, IbanChecker::DEFAULT_BASE_URL, 10.0, $transport))
            ->extract('Please wire to DE89 3704 0044 0532 0130 00 by Friday.');

        $this->assertCount(1, $batch);
        $this->assertSame('Commerzbank AG Cologne', $batch->results[0]->bankName);
    }

    public function testGetFormatParsesBbanFields(): void
    {
        $transport = new FakeTransport(200, [
            'country_code' => 'DE',
            'country_name' => 'Germany',
            'length' => 22,
            'sepa' => true,
            'example' => 'DE89370400440532013000',
            'bban_fields' => [
                ['label' => 'BLZ', 'length' => 8, 'type' => 'numeric', 'description' => '8-digit Bankleitzahl'],
                ['label' => 'Account number', 'length' => 10, 'type' => 'numeric'],
            ],
        ]);

        $format = (new IbanChecker(null, IbanChecker::DEFAULT_BASE_URL, 10.0, $transport))->getFormat('DE');

        $this->assertSame(22, $format->length);
        $this->assertTrue($format->sepa);
        $this->assertCount(2, $format->bbanFields);
        $this->assertSame('BLZ', $format->bbanFields[0]->label);
        $this->assertSame(8, $format->bbanFields[0]->length);
        $this->assertStringEndsWith('/formats/de', $transport->calls[0]['url']);
    }

    public function testLookupBicUppercasesThePath(): void
    {
        $transport = new FakeTransport(200, [
            'bic' => 'DEUTDEFFXXX',
            'bic8' => 'DEUTDEFF',
            'bank_name' => 'Deutsche Bank AG',
            'city' => 'Frankfurt am Main',
            'sepa' => true,
        ]);

        $bank = (new IbanChecker(null, IbanChecker::DEFAULT_BASE_URL, 10.0, $transport))->lookupBic('deutdeff');

        $this->assertSame('Deutsche Bank AG', $bank->bankName);
        $this->assertSame('DEUTDEFF', $bank->bic8);
        $this->assertStringEndsWith('/swift/DEUTDEFF', $transport->calls[0]['url']);
    }

    public function testErrorStatusesMapToTypedExceptions(): void
    {
        $cases = [
            400 => BadRequestException::class,
            401 => AuthenticationException::class,
            404 => NotFoundException::class,
            429 => RateLimitException::class,
            500 => ApiException::class,
        ];

        foreach ($cases as $status => $expected) {
            $transport = new FakeTransport($status, ['error' => 'nope', 'error_code' => 'SOME_CODE']);
            $client = new IbanChecker('iban_test_key', IbanChecker::DEFAULT_BASE_URL, 10.0, $transport);

            try {
                $client->lookupBic('ZZZZZZZZ');
                $this->fail('Expected ' . $expected . ' for HTTP ' . $status);
            } catch (IbanCheckerException $e) {
                $this->assertInstanceOf($expected, $e);
                $this->assertSame($status, $e->getStatus());
                $this->assertSame('SOME_CODE', $e->getErrorCode());
                $this->assertSame('nope', $e->getMessage());
                $this->assertIsArray($e->getResponse());
            }
        }
    }

    public function testANonJsonBodyOnASuccessfulStatusIsAnApiException(): void
    {
        $transport = new class implements Transport {
            public function send(string $method, string $url, array $headers, ?string $body): array
            {
                return ['status' => 200, 'body' => '<html>maintenance</html>'];
            }
        };

        $this->expectException(ApiException::class);
        (new IbanChecker(null, IbanChecker::DEFAULT_BASE_URL, 10.0, $transport))->validate('DE89');
    }

    public function testBaseUrlOverrideDropsATrailingSlash(): void
    {
        $transport = new FakeTransport(200, ['valid' => true, 'iban' => 'DE89']);
        (new IbanChecker(null, 'https://example.test/v1/', 10.0, $transport))->validate('DE89');

        $this->assertSame('https://example.test/v1/validate', $transport->calls[0]['url']);
    }
}

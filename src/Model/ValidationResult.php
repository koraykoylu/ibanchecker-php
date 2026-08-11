<?php

declare(strict_types=1);

namespace IbanChecker\Model;

/**
 * The result of validating one IBAN.
 *
 * $valid is the primary flag. When it is false only iban, formatted, country,
 * countryName, error and errorCode are populated.
 */
final class ValidationResult
{
    public bool $valid = false;
    public string $iban = '';
    public ?string $formatted = null;
    public ?string $checkDigits = null;
    public ?string $bban = null;
    public ?string $country = null;
    public ?string $countryName = null;
    public ?string $bankName = null;
    public ?string $bankType = null;
    public ?string $bic = null;
    public ?string $bankCity = null;
    public ?string $bankCode = null;
    public ?string $branchCode = null;
    public ?string $accountNumber = null;
    public ?string $currency = null;
    public ?string $currencyName = null;
    public ?string $transferType = null;
    public ?bool $sepa = null;
    public ?string $flag = null;
    public ?string $error = null;
    public ?string $errorCode = null;

    /**
     * True when the API also ran a national account check digit and it did not
     * match. The IBAN is still valid under ISO 13616; this is advisory and
     * usually means a transcription error. Null when no such check exists for
     * the country.
     */
    public ?bool $nationalCheckValid = null;

    /** @var array<string, mixed> The untouched response body. */
    public array $raw = [];

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $self = new self();
        $self->raw = $data;
        $self->valid = (bool) ($data['valid'] ?? false);
        $self->iban = (string) ($data['iban'] ?? '');
        $self->sepa = isset($data['sepa']) ? (bool) $data['sepa'] : null;
        $self->nationalCheckValid = isset($data['national_check_valid'])
            ? (bool) $data['national_check_valid']
            : null;

        $strings = [
            'formatted' => 'formatted',
            'check_digits' => 'checkDigits',
            'bban' => 'bban',
            'country' => 'country',
            'country_name' => 'countryName',
            'bank_name' => 'bankName',
            'bank_type' => 'bankType',
            'bic' => 'bic',
            'bank_city' => 'bankCity',
            'bank_code' => 'bankCode',
            'branch_code' => 'branchCode',
            'account_number' => 'accountNumber',
            'currency' => 'currency',
            'currency_name' => 'currencyName',
            'transfer_type' => 'transferType',
            'flag' => 'flag',
            'error' => 'error',
            'error_code' => 'errorCode',
        ];
        foreach ($strings as $key => $property) {
            if (isset($data[$key]) && is_scalar($data[$key])) {
                $self->{$property} = (string) $data[$key];
            }
        }

        return $self;
    }
}

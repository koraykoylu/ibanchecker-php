<?php

declare(strict_types=1);

namespace IbanChecker\Model;

/** The institution behind a SWIFT/BIC code. */
final class BankRecord
{
    public ?string $bic = null;
    public ?string $bic8 = null;
    public ?string $bankCode = null;
    public ?string $countryCode = null;
    public ?string $locationCode = null;
    public ?string $branchCode = null;
    public ?string $bankName = null;
    public ?string $city = null;
    public ?string $countryName = null;
    public ?bool $sepa = null;
    public ?string $type = null;
    public ?string $status = null;

    /** @var array<string, mixed> */
    public array $raw = [];

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $self = new self();
        $self->raw = $data;
        $self->sepa = isset($data['sepa']) ? (bool) $data['sepa'] : null;

        $strings = [
            'bic' => 'bic',
            'bic8' => 'bic8',
            'bank_code' => 'bankCode',
            'country_code' => 'countryCode',
            'location_code' => 'locationCode',
            'branch_code' => 'branchCode',
            'bank_name' => 'bankName',
            'city' => 'city',
            'country_name' => 'countryName',
            'type' => 'type',
            'status' => 'status',
        ];
        foreach ($strings as $key => $property) {
            if (isset($data[$key]) && is_scalar($data[$key])) {
                $self->{$property} = (string) $data[$key];
            }
        }

        return $self;
    }
}

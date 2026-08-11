<?php

declare(strict_types=1);

namespace IbanChecker\Model;

/** The IBAN format specification for one country. */
final class FormatSpec
{
    public ?string $countryCode = null;
    public ?string $countryName = null;
    public ?int $length = null;
    public ?string $currency = null;
    public ?string $currencyName = null;
    public ?bool $sepa = null;
    public ?bool $swift = null;
    public ?string $formatString = null;
    public ?string $example = null;

    /** @var list<BbanField> */
    public array $bbanFields = [];

    /** @var array<string, mixed> */
    public array $raw = [];

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $self = new self();
        $self->raw = $data;
        $self->length = isset($data['length']) ? (int) $data['length'] : null;
        $self->sepa = isset($data['sepa']) ? (bool) $data['sepa'] : null;
        $self->swift = isset($data['swift']) ? (bool) $data['swift'] : null;

        $strings = [
            'country_code' => 'countryCode',
            'country_name' => 'countryName',
            'currency' => 'currency',
            'currency_name' => 'currencyName',
            'format_string' => 'formatString',
            'example' => 'example',
        ];
        foreach ($strings as $key => $property) {
            if (isset($data[$key]) && is_scalar($data[$key])) {
                $self->{$property} = (string) $data[$key];
            }
        }

        $fields = $data['bban_fields'] ?? [];
        if (is_array($fields)) {
            foreach ($fields as $field) {
                if (is_array($field)) {
                    $self->bbanFields[] = BbanField::fromArray($field);
                }
            }
        }

        return $self;
    }
}

<?php

declare(strict_types=1);

namespace IbanChecker\Model;

/** One segment of a country's BBAN, in the order it appears in the IBAN. */
final class BbanField
{
    public ?string $label = null;
    public ?int $length = null;
    public ?string $type = null;
    public ?string $description = null;

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

        foreach (['label', 'type', 'description'] as $key) {
            if (isset($data[$key]) && is_scalar($data[$key])) {
                $self->{$key} = (string) $data[$key];
            }
        }

        return $self;
    }
}

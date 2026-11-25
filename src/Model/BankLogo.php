<?php

declare(strict_types=1);

namespace IbanChecker\Model;

/**
 * The bank's logo as the ibanchecker.cash result card shows it. The logos are
 * the banks' trademarks, provided to identify the bank.
 */
final class BankLogo
{
    /** The file to draw on a light background. */
    public string $onLight = '';

    /** The file to draw on a dark background; the same URL as $onLight when one file reads on both. */
    public string $onDark = '';

    /** True when $onDark has no dark variant: on a dark surface, draw it on a light chip. */
    public bool $darkNeedsLightBacking = false;

    /** "svg", "png" or "webp". */
    public string $format = '';

    /** @var array<string, mixed> */
    public array $raw = [];

    public static function fromValue(mixed $data): ?self
    {
        if (!is_array($data) || $data === []) {
            return null;
        }
        $self = new self();
        $self->raw = $data;
        $self->onLight = is_scalar($data['on_light'] ?? null) ? (string) $data['on_light'] : '';
        $self->onDark = is_scalar($data['on_dark'] ?? null) ? (string) $data['on_dark'] : '';
        $self->darkNeedsLightBacking = (bool) ($data['dark_needs_light_backing'] ?? false);
        $self->format = is_scalar($data['format'] ?? null) ? (string) $data['format'] : '';

        return $self;
    }
}

<?php

declare(strict_types=1);

namespace IbanChecker\Model;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * The result of a bulk validation or a text extraction.
 *
 * Iterating the object walks the per-IBAN results in input order; count()
 * returns how many came back.
 *
 * @implements IteratorAggregate<int, ValidationResult>
 */
final class BatchResult implements IteratorAggregate, Countable
{
    public int $count = 0;
    public int $validCount = 0;
    public int $invalidCount = 0;

    /** @var list<ValidationResult> */
    public array $results = [];

    /** @var array<string, mixed> The untouched response body. */
    public array $raw = [];

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $self = new self();
        $self->raw = $data;
        $self->count = (int) ($data['count'] ?? 0);
        $self->validCount = (int) ($data['valid_count'] ?? 0);
        $self->invalidCount = (int) ($data['invalid_count'] ?? 0);

        $rows = $data['results'] ?? [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (is_array($row)) {
                    $self->results[] = ValidationResult::fromArray($row);
                }
            }
        }

        return $self;
    }

    /** @return Traversable<int, ValidationResult> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->results);
    }

    public function count(): int
    {
        return count($this->results);
    }
}

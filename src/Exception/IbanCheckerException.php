<?php

declare(strict_types=1);

namespace IbanChecker\Exception;

use RuntimeException;

/**
 * Base class for every error this client raises.
 *
 * A malformed IBAN is not an error: validate() returns a result with
 * valid = false. These are raised for transport, authentication, quota and
 * server-side problems only.
 */
class IbanCheckerException extends RuntimeException
{
    private ?int $status;
    private ?string $errorCode;

    /** @var array<string, mixed>|null */
    private ?array $response;

    /**
     * @param array<string, mixed>|null $response
     */
    public function __construct(
        string $message,
        ?int $status = null,
        ?string $errorCode = null,
        ?array $response = null
    ) {
        parent::__construct($message);
        $this->status = $status;
        $this->errorCode = $errorCode;
        $this->response = $response;
    }

    /** HTTP status that produced this error, when there was one. */
    public function getStatus(): ?int
    {
        return $this->status;
    }

    /** Machine-readable code from the API body, e.g. "INVALID_LENGTH". */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /** @return array<string, mixed>|null The decoded response body. */
    public function getResponse(): ?array
    {
        return $this->response;
    }
}

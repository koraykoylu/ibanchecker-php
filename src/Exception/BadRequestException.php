<?php

declare(strict_types=1);

namespace IbanChecker\Exception;

/**
 * The request was malformed (HTTP 400). A trial call over the trial size also
 * gets this, with the error code TOO_MANY_IBANS (bulk validation) or
 * TEXT_TOO_LONG (extraction).
 */
final class BadRequestException extends IbanCheckerException
{
}

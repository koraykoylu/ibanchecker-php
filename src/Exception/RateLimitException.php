<?php

declare(strict_types=1);

namespace IbanChecker\Exception;

/** The hourly rate limit or monthly quota was exceeded (HTTP 429). */
final class RateLimitException extends IbanCheckerException
{
}

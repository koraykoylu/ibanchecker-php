<?php

declare(strict_types=1);

namespace IbanChecker\Exception;

/**
 * The monthly quota of an API key (error code QUOTA_EXCEEDED) or the hourly
 * per-IP limit on keyless format and BIC lookups (RATE_LIMIT_EXCEEDED) was
 * exceeded (HTTP 429).
 */
final class RateLimitException extends IbanCheckerException
{
}

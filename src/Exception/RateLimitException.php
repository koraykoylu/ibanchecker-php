<?php

declare(strict_types=1);

namespace IbanChecker\Exception;

/**
 * The monthly quota of an API key (error code QUOTA_EXCEEDED) or the hourly
 * per-IP limit on keyless format lookups (RATE_LIMIT_EXCEEDED) was exceeded
 * (HTTP 429). A call that costs more than the requests left this month also
 * gets QUOTA_EXCEEDED; bulk validation counts one request per IBAN and
 * extraction one per IBAN found.
 */
final class RateLimitException extends IbanCheckerException
{
}

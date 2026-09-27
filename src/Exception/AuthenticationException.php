<?php

declare(strict_types=1);

namespace IbanChecker\Exception;

/**
 * The API key is missing, invalid, or inactive (HTTP 401).
 *
 * Every endpoint except country formats needs a key, so a lookupBic() call
 * without one also gets this, from the API's 401.
 */
final class AuthenticationException extends IbanCheckerException
{
}

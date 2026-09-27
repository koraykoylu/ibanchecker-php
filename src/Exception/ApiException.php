<?php

declare(strict_types=1);

namespace IbanChecker\Exception;

/**
 * An HTTP status this client has no dedicated class for: a server-side error
 * (HTTP 5xx), or HTTP 403 with the error code PLAN_REQUIRED when the call is
 * outside the key's plan. For PLAN_REQUIRED, getResponse() carries
 * required_plan ("basic" or "growth") and upgrade_url.
 */
final class ApiException extends IbanCheckerException
{
}

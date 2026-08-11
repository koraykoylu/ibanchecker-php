<?php

declare(strict_types=1);

namespace IbanChecker\Exception;

/** An unexpected server-side error (HTTP 5xx or other). */
final class ApiException extends IbanCheckerException
{
}

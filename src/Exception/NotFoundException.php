<?php

declare(strict_types=1);

namespace IbanChecker\Exception;

/** The requested country code or BIC was not found (HTTP 404). */
final class NotFoundException extends IbanCheckerException
{
}

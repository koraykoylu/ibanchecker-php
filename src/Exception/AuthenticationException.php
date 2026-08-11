<?php

declare(strict_types=1);

namespace IbanChecker\Exception;

/** The API key is missing, invalid, or inactive (HTTP 401). */
final class AuthenticationException extends IbanCheckerException
{
}

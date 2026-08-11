<?php

declare(strict_types=1);

namespace IbanChecker;

/**
 * Minimal HTTP seam.
 *
 * The client ships CurlTransport and needs nothing else installed. Anything
 * that speaks this interface can be injected instead, which is how the tests
 * run without a network and how a host application can route calls through
 * its own HTTP stack.
 */
interface Transport
{
    /**
     * @param  string                $method  HTTP verb, uppercase.
     * @param  string                $url     Absolute URL.
     * @param  array<string, string> $headers Header name => value.
     * @param  string|null           $body    Raw request body, or null.
     * @return array{status: int, body: string} Status 0 means the request never completed.
     */
    public function send(string $method, string $url, array $headers, ?string $body): array;
}

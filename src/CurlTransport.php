<?php

declare(strict_types=1);

namespace IbanChecker;

use IbanChecker\Exception\IbanCheckerException;

/**
 * Default transport, built on ext-curl.
 */
final class CurlTransport implements Transport
{
    private float $timeout;

    public function __construct(float $timeout = 10.0)
    {
        $this->timeout = $timeout;
    }

    public function send(string $method, string $url, array $headers, ?string $body): array
    {
        $handle = curl_init();
        if ($handle === false) {
            throw new IbanCheckerException('Could not initialise a cURL handle');
        }

        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        curl_setopt_array($handle, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_TIMEOUT_MS => (int) round($this->timeout * 1000),
            CURLOPT_CONNECTTIMEOUT_MS => (int) round($this->timeout * 1000),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
        ]);

        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($handle);
        $error = curl_error($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        // No curl_close(): since PHP 8.0 the handle is an object freed by the
        // garbage collector, and calling it emits a deprecation from 8.5 on.

        if ($response === false) {
            throw new IbanCheckerException(sprintf('Request to %s failed: %s', $url, $error));
        }

        return ['status' => $status, 'body' => (string) $response];
    }
}

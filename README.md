# ibanchecker/client

Official PHP client for the [ibanchecker.cash](https://ibanchecker.cash) IBAN validation API.

Validate IBANs across 92 countries, validate up to 100 IBANs per request, extract IBANs from free text, look up country format specifications, and resolve SWIFT/BIC codes. No IBAN data is stored or logged; all validation runs in memory at the edge.

## Install

```bash
composer require ibanchecker/client
```

Requires PHP 8.0 or newer with `ext-curl` and `ext-json`. There are no Composer dependencies.

## Quick start

```php
use IbanChecker\IbanChecker;

// Validation needs an API key. A free key covers 100 requests a month:
// https://ibanchecker.cash/api-docs
$client = new IbanChecker('YOUR_API_KEY');

$result = $client->validate('DE89 3704 0044 0532 0130 00');
if ($result->valid) {
    echo $result->countryName;  // "Germany"
    echo $result->bankName;     // "Commerzbank AG Cologne"
    echo $result->bic;          // "COBADEFFXXX"
} else {
    echo $result->error;        // human-readable reason
    echo $result->errorCode;    // e.g. "INVALID_COUNTRY"
}
```

## Authentication

`validate()`, `validateBulk()` and `extract()` need an API key. Without one the API answers HTTP 401 and the client throws an `AuthenticationException`. A free key covers 100 requests a month and arrives by email in seconds: request one at [ibanchecker.cash/api-docs](https://ibanchecker.cash/api-docs). Paid plans with a larger quota are at [ibanchecker.cash/pricing](https://ibanchecker.cash/pricing).

```php
$client = new IbanChecker(getenv('IBANCHECKER_API_KEY') ?: null);
```

`getFormat()` and `lookupBic()` work without a key, limited to 100 requests an hour per IP.

Past the monthly quota the API answers HTTP 429 with the error code `QUOTA_EXCEEDED`, and the client throws a `RateLimitException`; the response body carries an `upgrade_url`. The quota resets on the 1st of each month (UTC).

## Methods

| Method | Description | API key |
| --- | --- | --- |
| `validate(string $iban)` | Validate a single IBAN. Returns a `ValidationResult`. | Required |
| `validateBulk(iterable $ibans)` | Validate up to 100 IBANs. Returns a `BatchResult`. | Required |
| `extract(string $text)` | Find and validate IBANs in free text (up to 50,000 chars). Returns a `BatchResult`. | Required |
| `getFormat(string $country)` | IBAN format spec for an ISO country code. Returns a `FormatSpec`. | Not needed |
| `lookupBic(string $bic)` | Resolve an 8 or 11 character BIC. Returns a `BankRecord`. | Not needed |

### Bulk validation

```php
$batch = $client->validateBulk([
    'DE89370400440532013000',
    'GB29NWBK60161331926819',
    'XX00',
]);

echo $batch->validCount, ' of ', $batch->count, ' valid';

foreach ($batch as $result) {      // results come back in input order
    echo $result->iban, ' ', $result->valid ? 'ok' : 'bad', PHP_EOL;
}
```

### Extract from text

```php
$batch = $client->extract('Please wire to DE89 3704 0044 0532 0130 00 by Friday.');

foreach ($batch as $result) {
    echo $result->iban, ' ', $result->bankName, PHP_EOL;
}
```

### Country format and BIC lookup

```php
$format = $client->getFormat('DE');
echo $format->length, ' ', $format->example;   // 22 DE89370400440532013000

foreach ($format->bbanFields as $field) {
    echo $field->label, ' (', $field->length, ')', PHP_EOL;
}

$bank = $client->lookupBic('DEUTDEFF');
echo $bank->bankName, ' ', $bank->city;        // Deutsche Bank AG Frankfurt  FRANKFURT AM MAIN
```

### The national check digit

For a number of countries the API also runs the national account check digit on top of the ISO 13616 check, and reports it on `nationalCheckValid`. It is advisory: an IBAN with `valid = true` is a valid IBAN whatever this says. A `false` usually means a transcription error in the account number. It is `null` where the country has no such scheme.

```php
$result = $client->validate('DE89370400440532013000');
if ($result->valid && $result->nationalCheckValid === false) {
    echo 'Valid IBAN, but the account number looks mistyped.';
}
```

## Error handling

A malformed IBAN is **not** an exception: `validate()` returns a `ValidationResult` with `valid = false`. Exceptions are raised only for transport, authentication, quota and server-side problems.

```php
use IbanChecker\Exception\AuthenticationException;
use IbanChecker\Exception\NotFoundException;
use IbanChecker\Exception\RateLimitException;

try {
    $bank = $client->lookupBic('ZZZZZZZZ');
} catch (NotFoundException $e) {
    echo 'No bank for that BIC';
} catch (RateLimitException $e) {
    echo 'Limit reached: ', $e->getMessage();   // getErrorCode(): QUOTA_EXCEEDED or RATE_LIMIT_EXCEEDED
} catch (AuthenticationException $e) {
    echo 'Missing or invalid API key';
}
```

Every exception extends `IbanChecker\Exception\IbanCheckerException` and carries `getStatus()`, `getErrorCode()` and `getResponse()`.

## Using your own HTTP stack

The client ships a cURL transport and needs nothing installed. If your application already has an HTTP layer, implement `IbanChecker\Transport` and pass it in. This is also how the test suite runs without a network.

```php
use IbanChecker\IbanChecker;
use IbanChecker\Transport;

final class MyTransport implements Transport
{
    public function send(string $method, string $url, array $headers, ?string $body): array
    {
        // ... return ['status' => int, 'body' => string]
    }
}

$client = new IbanChecker('iban_your_api_key', IbanChecker::DEFAULT_BASE_URL, 10.0, new MyTransport());
```

## Raw responses

Every model keeps the untouched response body on `->raw`, so a field added to the API later is reachable without waiting for a client release.

```php
$result = $client->validate('DE89370400440532013000');
$result->raw['transfer_type'];  // "SEPA+SWIFT"
```

## Tests

```bash
composer install
composer test
```

## Links

- Website: https://ibanchecker.cash
- API documentation: https://ibanchecker.cash/api-docs
- OpenAPI spec: https://ibanchecker.cash/openapi.json
- Free online tools: https://ibanchecker.cash/tools

## License

MIT

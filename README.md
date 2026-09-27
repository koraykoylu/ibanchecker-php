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

// Every method except getFormat() needs an API key. A free key covers
// single IBAN validation, 100 requests a month:
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

Every method except `getFormat()` needs an API key, and that includes `lookupBic()`, which used to work without one. Without a key the API answers HTTP 401 and the client throws an `AuthenticationException`. A free key arrives by email in seconds: request one at [ibanchecker.cash/api-docs](https://ibanchecker.cash/api-docs). Paid plans with a larger quota are at [ibanchecker.cash/pricing](https://ibanchecker.cash/pricing).

```php
$client = new IbanChecker(getenv('IBANCHECKER_API_KEY') ?: null);
```

What a key can call follows its plan:

- A free key covers single IBAN validation (`validate()`), 100 requests a month.
- `validateBulk()` and `lookupBic()` need the Basic plan or above (Basic, Starter, Growth, Enterprise).
- `extract()` needs the Growth plan or above (Growth, Enterprise).

If the key's email address has a verified account at [ibanchecker.cash/dashboard](https://ibanchecker.cash/dashboard), the key can try the methods its plan lacks: `validateBulk()` with up to 10 IBANs per call, `lookupBic()`, and `extract()` with up to 5,000 characters per call. This applies to any plan without the feature; a Basic key with a verified account can try `extract()`, for example. A trial call over the trial size gets HTTP 400 with the error code `TOO_MANY_IBANS` (bulk) or `TEXT_TOO_LONG` (extraction), and the client throws a `BadRequestException`. The full limits are 100 IBANs per bulk call and 50,000 characters per extraction.

A call outside the key's plan gets HTTP 403 with the error code `PLAN_REQUIRED`. The client has no dedicated class for 403 and throws an `ApiException`: `getStatus()` returns 403, `getErrorCode()` returns `PLAN_REQUIRED`, and `getResponse()` holds the decoded body, including `required_plan` (`basic` or `growth`) and `upgrade_url` (https://ibanchecker.cash/pricing).

`getFormat()` works without a key, limited to 100 requests an hour per IP; beyond that the API answers HTTP 429 with the error code `RATE_LIMIT_EXCEEDED` and the client throws a `RateLimitException`. This hourly limit applies only to format lookups.

Past the monthly quota the API answers HTTP 429 with the error code `QUOTA_EXCEEDED`, and the client throws a `RateLimitException`; the response body carries an `upgrade_url`. The quota resets on the 1st of each month (UTC).

`validate()` and `lookupBic()` count one request each. `validateBulk()` counts one request per IBAN sent, and `extract()` one per IBAN found, with at least one per call. A call that costs more than the requests left this month also gets HTTP 429 with `QUOTA_EXCEEDED` (a `RateLimitException`).

## Methods

| Method | Description | API key |
| --- | --- | --- |
| `validate(string $iban)` | Validate a single IBAN. Returns a `ValidationResult`. | Required, any plan (including free) |
| `validateBulk(iterable $ibans)` | Validate up to 100 IBANs (10 on a trial). Returns a `BatchResult`. Counts one request per IBAN. | Required, Basic plan or above |
| `extract(string $text)` | Find and validate IBANs in free text (up to 50,000 chars; 5,000 on a trial). Returns a `BatchResult`. Counts one request per IBAN found, at least one per call. | Required, Growth plan or above |
| `getFormat(string $country)` | IBAN format spec for an ISO country code. Returns a `FormatSpec`. | Not needed |
| `lookupBic(string $bic)` | Resolve an 8 or 11 character BIC. Returns a `BankRecord`. | Required, Basic plan or above |

A key with a verified account can try the methods its plan lacks, as described under [Authentication](#authentication).

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

`getFormat()` works without a key. `lookupBic()` needs one on the Basic plan or above, or a trial through a verified account.

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

A malformed IBAN is **not** an exception: `validate()` returns a `ValidationResult` with `valid = false`. Exceptions are raised only for transport, authentication, plan, quota and server-side problems.

```php
use IbanChecker\Exception\ApiException;
use IbanChecker\Exception\AuthenticationException;
use IbanChecker\Exception\NotFoundException;
use IbanChecker\Exception\RateLimitException;

try {
    $bank = $client->lookupBic('ZZZZZZZZ');
} catch (NotFoundException $e) {
    echo 'No bank for that BIC';
} catch (RateLimitException $e) {
    echo 'Limit reached: ', $e->getMessage();   // getErrorCode(): QUOTA_EXCEEDED (RATE_LIMIT_EXCEEDED only on keyless getFormat())
} catch (AuthenticationException $e) {
    echo 'Missing or invalid API key';
} catch (ApiException $e) {
    if ($e->getStatus() === 403 && $e->getErrorCode() === 'PLAN_REQUIRED') {
        $plan = $e->getResponse()['required_plan'] ?? null;   // "basic" or "growth"
        echo 'This call needs the ', $plan, ' plan or above';
    } else {
        throw $e;
    }
}
```

Every exception extends `IbanChecker\Exception\IbanCheckerException` and carries `getStatus()`, `getErrorCode()` and `getResponse()`. A 403 `PLAN_REQUIRED` has no class of its own and arrives as an `ApiException`, the class also used for server-side errors, so check `getErrorCode()` to tell them apart.

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

# Changelog

## 0.1.1

Documentation only; the client's behaviour is unchanged.

- The API now requires a key for `validate`, `validateBulk` and `extract`;
  without one it answers 401 and the client throws `AuthenticationException`.
  The README, docblocks and quick start now construct the client with a key.
- A free key covers 100 requests a month. Past the quota the API answers 429
  with `QUOTA_EXCEEDED` (a `RateLimitException`).
- `getFormat` and `lookupBic` still work without a key, limited to 100
  requests an hour per IP.
- `IbanChecker::VERSION` (sent in the User-Agent) is now `0.1.1`.

## 0.1.0

First release.

- `validate`, `validateBulk`, `extract`, `getFormat` and `lookupBic`
- Typed models for every response, with the raw body kept on `->raw`
- Typed exceptions for 400, 401, 404, 429 and other failures; a malformed
  IBAN is a result with `valid = false`, never an exception
- No required Composer dependencies; ships a cURL transport and accepts any
  `Transport` implementation instead

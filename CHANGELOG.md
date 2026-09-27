# Changelog

## 0.1.2

Documentation only, for changes in the API.

- Every endpoint except `getFormat` now requires an API key. `lookupBic`
  used to work without one; without a key it now gets 401 and the client
  throws `AuthenticationException`.
- What a key can call follows its plan: a free key covers `validate` (100
  requests a month), `validateBulk` and `lookupBic` need Basic or above, and
  `extract` needs Growth or above. A key whose email address has a verified
  account at https://ibanchecker.cash/dashboard can try the methods its plan
  lacks: bulk validation up to 10 IBANs per call, BIC lookup, and extraction
  up to 5,000 characters per call.
- A call outside the key's plan gets 403 with `PLAN_REQUIRED`; the client
  throws it as an `ApiException`, and `getResponse()` carries
  `required_plan` and `upgrade_url`. A trial call over the trial size gets
  400 with `TOO_MANY_IBANS` or `TEXT_TOO_LONG` (a `BadRequestException`).
- Bulk validation counts one request per IBAN and extraction one per IBAN
  found (at least one per call). A call that costs more than the requests
  left this month gets 429 with `QUOTA_EXCEEDED`.
- The keyless limit of 100 requests an hour per IP now covers `getFormat`
  only.
- `IbanChecker::VERSION` (sent in the User-Agent) is now `0.1.2`.

The client's behaviour does not change.

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

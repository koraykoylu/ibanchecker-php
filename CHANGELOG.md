# Changelog

## 0.1.0

First release.

- `validate`, `validateBulk`, `extract`, `getFormat` and `lookupBic`
- Typed models for every response, with the raw body kept on `->raw`
- Typed exceptions for 400, 401, 404, 429 and other failures; a malformed
  IBAN is a result with `valid = false`, never an exception
- No required Composer dependencies; ships a cURL transport and accepts any
  `Transport` implementation instead

# Contributing notes for AI agents and humans

This file describes how to work in this repository. It is public; keep it that
way. Anything internal belongs in `CLAUDE.local.md`, which is gitignored.

## What this package is

A small, dependency-free PHP client for the VRIO commerce API. It has one job:
turn a method call into a well-formed HTTP request, and turn the response into
a predictable envelope. It holds no state between calls and no credentials of
its own.

## Language and tooling

- PHP **8.4 minimum**, 8.5 recommended. CI runs the gate on both.
- Every file starts with `declare(strict_types=1);`.
- Raising the minimum PHP version is a breaking change — it goes in the
  changelog and in `composer.json`, never quietly.
- No runtime dependencies beyond `ext-curl` and `ext-json`. Adding one needs a
  strong argument — consumers install this alongside their own framework.

## Style

- PSR-12, enforced by `phpcs`. Lines wrap at 120 columns.
- Full docblocks on every class, method and property, including `@param`,
  `@return` and `@throws`. PHPStan runs at max level and relies on them.
- Class, method and variable names in `camelCase`/`StudlyCaps`; API payload
  keys stay in whatever case the provider uses (`order_id`, `cart_token`).
- Comments explain *why*, not *what*. Do not narrate the code.

## Architecture rules

- `API` is the only entry point a consumer touches. It resolves a method name
  to a resource class through `METHOD_MAP` and delegates.
- Resource classes (`Order`, `Cart`, …) extend `Vrio`, set `$this->section`
  and `$this->fields`, then call one of the `sendGet`/`sendPost`/`sendPatch`/
  `sendPut`/`sendDelete` helpers. They contain no HTTP logic.
- `Vrio` owns URL assembly, header construction, the response envelope and
  per-request state reset. **Always reset state after a request** — instances
  are reused across calls.
- All network access goes through `HttpClientInterface`. Never call `curl_*`
  outside `Http\CurlClient`.
- Credentials travel in headers only. Never merge the API key into a body, a
  query string, or anything returned by `getPayloadInfo()`.
- Never accept a full URL from a consumer. `ClientConfig` takes a bare host and
  rejects anything containing a scheme, path, query or space.
- Never disable TLS verification, and never add an option to.
- Logging must not be able to change behaviour. `DebugLogger` reads from
  `Request`/`Response` value objects and returns a string; it mutates nothing
  and swallows its own failures.
- Adding a resource method means adding it to `API::METHOD_MAP`, to the
  `@method` docblock on `API`, and to the README.

## Testing rules

- Every public method has a test.
- **No test may touch the network.** Use `Tests\Support\MockHttpClient`.
- Resource tests assert the URL, the HTTP method, the headers and the request
  body — `ClientTestCase::assertRequest()` covers all four.
- Credentials in tests are obviously fake placeholders. Never a real key, not
  even an expired one.
- New redaction rules need a test proving the real request and response are
  unchanged, alongside the test proving the log entry is masked.

## Running the gate

```bash
composer install
composer gate
```

which runs, in order and stopping at the first failure:

```bash
vendor/bin/phpcs                        # PSR-12
vendor/bin/phpstan analyse              # max level
vendor/bin/phpunit                      # full suite
```

`vendor/bin/phpcbf` fixes most style findings automatically. All three must
pass on every supported PHP version before anything is tagged.

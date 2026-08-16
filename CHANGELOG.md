# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.0.1] - 2026-08-16

First public release.

### Added

- `API` client covering 18 VRIO endpoints across campaigns, customers, offers,
  discounts, carts, orders and routes, reached through a chainable call
  followed by `get()`, `getInArray()` or `getInObject()`.
- A normalised response envelope — `success`, `message`, `data` and
  `validation_code` — with provider errors and transport failures reported as
  data rather than as exceptions.
- `getPayloadInfo()`, exposing the exact URL and parameters of the last call
  with credentials deliberately excluded, so it is safe to surface in a
  consumer's own diagnostics.
- Credentials supplied entirely by the caller: the API key is a required
  constructor argument, sent only as the `X-Api-Key` header. The package ships
  no defaults and reads no environment variables.
- `host` and `basePath` options taking a bare hostname; full URLs are rejected
  so the scheme and path assembly stay inside the client. Any host VRIO issues
  you, production or sandbox, is passed the same way.
- `timeout` and `connectTimeout` options, defaulting to 30 and 10 seconds.
- TLS peer and host verification, always on, with no option to disable it.
- Opt-in debug logging that is redacted by default: credentials, tokens, card,
  bank and government identifiers are masked in both headers and bodies.
  Redaction is proven by test not to alter the real request or response.
- Card numbers additionally caught by shape — any 13–19 digit string passing a
  Luhn check is masked wherever it appears.
- `debugRedact => false` escape hatch for verbatim local debugging, documented
  as never-in-production.
- A built-in file sink writing one file per day from a caller-supplied base
  path, with a retention setting (default 7 days, `0` keeps everything).
  Pruning matches only the package's own dated filenames, reads the date from
  the filename rather than the mtime, and runs once per process.
- A `debugSink` option accepting a closure that replaces the file sink
  entirely, as the vendor-neutral extension point for any external log
  destination.
- `HttpClientInterface` transport seam with a `CurlClient` default, so requests
  can be routed through a consumer's own HTTP stack and the test suite can run
  without touching the network.
- `withProxy()` for per-call HTTP proxy routing.
- `VrioException` as the single exception type, extending `\Exception`.
- `API::supportedMethods()` listing every dispatchable resource method.
- Documentation: README, integration guide, architecture notes, security
  policy, and contributor conventions.
- PSR-12 style, PHPStan at max level, and a PHPUnit suite covering every public
  method and asserting URL, HTTP method, headers and request body, running on
  PHP 8.4 and 8.5 in CI.

[Unreleased]: https://github.com/astermd/vrio-client/compare/v0.0.1...HEAD
[0.0.1]: https://github.com/astermd/vrio-client/releases/tag/v0.0.1

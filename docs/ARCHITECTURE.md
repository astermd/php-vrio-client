# Architecture

How a call travels through `astermd/vrio-client`, why the pieces are split the
way they are, and where to extend it.

---

## Layers

```
        ┌──────────────────────────────────────────────────┐
        │  API                    facade + method dispatch  │
        └───────────────────────┬──────────────────────────┘
                                │ resolves a method name to a resource
        ┌───────────────────────▼──────────────────────────┐
        │  Campaign  Customer  Discount  Offer             │
        │  Order     Route     Cart          resources     │
        └───────────────────────┬──────────────────────────┘
                                │ set $section and $fields, call a send* helper
        ┌───────────────────────▼──────────────────────────┐
        │  Vrio            URL assembly, headers, envelope  │
        └──────┬────────────────────────────────┬──────────┘
               │ Request                        │ Request + Response
    ┌──────────▼──────────┐          ┌──────────▼──────────┐
    │ HttpClientInterface │          │    DebugLogger      │
    │      CurlClient     │          │  Redactor, FileSink │
    └─────────────────────┘          └─────────────────────┘
```

Two rules hold the design together:

1. **Everything that reaches the network goes through `HttpClientInterface`.**
   No `curl_*` call exists outside `Http\CurlClient`.
2. **Logging is a read-only observer.** It receives immutable `Request` and
   `Response` objects and returns a string. It cannot alter what is sent or
   what the caller receives.

---

## The lifecycle of one call

Take `$api->captureOrder('ord_1', ['amount' => 1000])->getInArray()`.

**1. Dispatch.** `captureOrder` is not defined on `API`, so `__call` runs. It
looks the name up in `API::METHOD_MAP`, a compile-time constant mapping all 18
resource methods to their class. An unknown name throws `VrioException` here,
before anything else happens.

*Why a map rather than reflection:* the original implementation called
`get_class_methods()` on every resource and diffed against the parent on every
single call. The map is a constant lookup, it is greppable, and a static
analyser can see it.

**2. Resource resolution.** `API` instantiates `Order` once and caches it for
the lifetime of the client, handing it the shared `ClientConfig`,
`HttpClientInterface` and `DebugLogger`. A proxy queued by `withProxy()` is
applied to the resource now, then cleared.

**3. The resource describes the request.** `Order::captureOrder()` validates
its arguments, sets `$this->section = 'orders/ord_1/capture'` and
`$this->fields = ['amount' => 1000]`, then calls `sendPost()`. Resource classes
contain no HTTP knowledge whatsoever — that is the whole reason they are thin.

**4. `Vrio` assembles.** `setApiUrl()` joins `ClientConfig::getBaseUrl()` to the
section. `buildHeaders()` produces the three headers. `encodeFields()` JSON-
encodes the body, using `{}` rather than `[]` for an empty payload.

**5. A `Request` value object is built** and handed to the transport. It is
immutable, which is what makes the logging guarantee in rule 2 structural
rather than a matter of discipline.

**6. `CurlClient` sends it.** TLS peer and host verification are on, with no
option to disable them. A transport failure is *not* thrown — it is returned as
`Response::getTransportError()`, so the logger can still record the attempt.

**7. `DebugLogger::log()` observes.** No-op unless `debug` is on. Otherwise it
renders the entry — redacted unless you opted out — and passes the string to
the sink. Any failure inside logging is swallowed.

**8. `Vrio` normalises.** A transport error sets the error flag and stores the
message. Otherwise `buildEnvelope()` wraps the provider's body in the
`success` / `message` / `data` (/ `validation_code`) envelope.

**9. State resets.** `reset()` clears the URL, section, fields, query and proxy.
This is what makes a cached resource instance safe to reuse — without it, the
fields from one call leak into the next.

**10. The caller reads.** `getInArray()` decodes the envelope. `getPayloadInfo()`
returns the URL and parameters from the snapshot taken before the reset.

---

## Class responsibilities

| Class | Owns | Never does |
|---|---|---|
| `API` | Method dispatch, resource caching, the three accessors | HTTP, URL building |
| `ClientConfig` | Credentials, host validation, base URL, timeouts | Anything mutable |
| `Vrio` | URL assembly, headers, body encoding, envelope, state reset | Direct cURL calls |
| `Campaign`…`Cart` | Route shape and argument validation | HTTP, encoding |
| `Http\Request` / `Http\Response` | Immutable descriptions of one exchange | Behaviour |
| `Http\CurlClient` | The only cURL in the package | Throwing on transport failure |
| `Logging\DebugLogger` | Entry formatting, sink dispatch | Mutating anything |
| `Logging\Redactor` | Masking copies of headers and bodies | Touching the URL |
| `Logging\FileSink` | Dated files, retention, pruning | Throwing |
| `Config` / `Repository` | Message strings from `config/` | Runtime configuration |
| `Exception\VrioException` | Every error the package raises | — |

---

## Design decisions worth knowing

### Credentials live in headers, nowhere else

`ClientConfig` holds the key; `Vrio::buildHeaders()` is the only place it is
read. It is never merged into `$this->fields`, never appended to a query
string, and never included in `getPayloadInfo()`.

An earlier version copied the key into the request payload, from where it
reached callers through `get(true)`. Keeping the credential on exactly one path
is what makes that class of leak structurally impossible rather than a bug
waiting to reappear.

### A bare host, not a URL

`ClientConfig` rejects any host containing a scheme, path, query or space. The
client owns the scheme (always HTTPS) and the path assembly. A consumer that
could pass a full URL could downgrade to plaintext or redirect the client
somewhere unintended; a consumer that passes a hostname cannot.

`basePath` exists for APIs served under a version prefix, and is the only
supported way to add a path.

### The transport is an interface

Three things fall out of this: the entire test suite runs without a network,
consumers can route calls through their own HTTP stack, and cURL specifics stay
in one 90-line class. `Response` carries transport errors as data rather than
throwing, so a failure is observable by the logger before anyone decides what
to do about it.

### Immutable request and response objects

The logging guarantee — redaction cannot change behaviour — is enforced by the
type system rather than by review. `Redactor` receives arrays and strings by
value and returns new ones; `Request` and `Response` expose only getters. A
test asserts the wire body and the returned body are untouched while the log
entry is masked.

### Field masking, not body dropping

Bodies are masked field by field rather than dropped whole on sensitive routes.
This keeps debug logs useful for support while removing credentials, tokens,
card, bank and identity data. Two backstops widen the net: an unparseable body
is replaced entirely rather than logged, and any 13–19 digit string passing a
Luhn check is masked wherever it appears, even under an unexpected key.

The URL is deliberately exempt — a log entry that does not show the real URL is
not reproducible. The README lists which calls therefore record identifiers.

### Retention reads the filename, not the mtime

Appending to a log file updates its mtime, so mtime-based pruning keeps a
month-old file alive indefinitely as long as something writes to it. The date
in the filename is the file's real age. Pruning also matches only this
package's own dated pattern for the configured base path, so it can never
delete a neighbouring file, and it runs once per process rather than once per
request.

### No runtime dependencies

The package previously pulled in a full microframework to store six message
strings. `Repository` is now a static array with dotted-key lookup. A client
library is installed alongside the consumer's own stack; every dependency it
adds is a version constraint someone else has to satisfy.

---

## Extension points

| You want to | Do this |
|---|---|
| Route calls through your own HTTP stack | Implement `HttpClientInterface`, pass it as the third constructor argument |
| Send logs to a PSR-3 logger, queue or object store | Supply a `debugSink` closure; it replaces the file sink entirely |
| Change log retention | `debugRetentionDays` (`0` keeps everything), or take it over with your own sink |
| Support a new endpoint | Add the method to its resource class, register it in `API::METHOD_MAP` and the `@method` block, document it in the README, test URL + method + headers + body |
| Add a redaction rule | Extend the key lists in `Redactor`, and add both tests: the log is masked, the real request and response are not |
| Target a different host | `host` and `basePath` options — never a full URL |

Anything requiring a change to `Vrio` or `CurlClient` is worth a second look:
those two classes hold the invariants everything else depends on.

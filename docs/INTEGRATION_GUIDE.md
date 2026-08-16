# Integration guide

Getting `astermd/vrio-client` into a production application, and the decisions
worth making deliberately along the way.

This guide assumes you already have VRIO API credentials. For what each
endpoint accepts and returns, use the provider's own reference:
<https://docs.vrio.com/reference/vrio-api-overview>.

---

## 1. Install

```bash
composer require astermd/vrio-client
```

Requires **PHP 8.4 or newer** (8.5 recommended), `ext-curl` and `ext-json`.
There are no other runtime dependencies, so the package will not drag a
framework or an HTTP library into your application.

---

## 2. Supply credentials

The API key is a **required constructor argument that you pass in**. The
package has no credential discovery of any kind: it ships no defaults, reads no
environment variables, opens no config file, and consults no global state.
Whatever you hand the constructor is what it uses.

```php
use AsterMD\VrioClient\API;

$api = new API($apiKey);
```

That is deliberate. A key can never arrive from somewhere you did not intend,
and nothing changes behaviour between machines because of an environment
difference.

Where *you* get `$apiKey` from is entirely your decision — a secrets manager,
your application's configuration, or a per-account record in your own database.
The package neither knows nor cares.

### Multiple VRIO accounts

Because the key is a constructor argument rather than ambient configuration,
integrating several accounts is just several instances. Nothing is shared or
cached between them:

```php
final class VrioFactory
{
    /** @param object $account A record from your own store */
    public function forAccount(object $account): API
    {
        return new API($account->apiKey, [
            'host' => $account->host ?: 'api.vrio.app',
        ]);
    }
}
```

Build the client at the point of use, from the account you are acting for.
Avoid registering a single process-wide instance unless you genuinely only ever
talk to one account.

### Wiring it into a container

If you do have exactly one account, a container binding is fine — just note
that the key still arrives as an argument you supply:

```php
$container->singleton(API::class, static fn (): API => new API(
    $keyFromWhereverYouKeepIt,
    [
        'host'  => 'api.vrio.app',
        'debug' => false,
        // when debug is on, one of debugFile / debugSink is required
    ]
));
```

### Handling the key itself

If the key has ever been committed, pasted into a ticket, or logged verbatim,
rotate it — removing it from a file does not un-expose it.

---

## 3. Choose the host

The client takes a **bare hostname**. It builds the scheme and the path itself,
so a full URL is rejected:

```php
new API($key, ['host' => 'api.vrio.app']);                     // fine
new API($key, ['host' => 'https://api.vrio.app']);             // VrioException
new API($key, ['host' => 'api.vrio.app/v1']);                  // VrioException
new API($key, ['host' => 'api.vrio.app', 'basePath' => 'v1']); // fine
```

Omitting `host` gives you `api.vrio.app`.

### Sandbox and test accounts

If VRIO issued you a separate sandbox or test host, pass it exactly the same
way — the client treats every host identically and applies no environment
branching of its own:

```php
$sandbox = new API($sandboxKey, ['host' => $sandboxHostFromYourVrioAccount]);
```

Keep the sandbox host and its key in configuration rather than in code, so the
same build can be pointed at either environment.

> The sandbox hostname is issued per account, so this document does not name
> one. Take it from your VRIO account or from VRIO support, and record it in
> your own environment configuration.

---

## 4. Make a call and read the result

Resource calls are chainable and return the client; an accessor produces the
result.

```php
$result = $api->searchOrder(['with' => 'items'])->getInArray();

if ($result['response']['success']) {
    $orders = $result['response']['data'];
}
```

Three accessors, same envelope:

| Accessor | `response` contains |
|---|---|
| `get()` | the envelope as a JSON string |
| `getInArray()` | the envelope as an associative array |
| `getInObject()` | the envelope as an object graph |

Pass `true` to add a `payload` key with the exact URL called and the parameters
sent. It never includes your API key, so it is safe to attach to your own error
reports.

---

## 5. Handle failures

There are three distinct failure modes, and they surface differently. Handling
all three is the difference between a robust integration and a fragile one.

### a. Your mistake — a `VrioException`

Thrown before or during the call: an empty API key, a host that is not bare, a
missing required argument, an unknown method, an undecodable envelope.

```php
use AsterMD\VrioClient\Exception\VrioException;

try {
    $result = $api->getOrder($orderId)->getInArray();
} catch (VrioException $e) {
    // programming or configuration error — log it and fail loudly
}
```

### b. The provider rejected the request

Not an exception. The envelope reports it:

```php
$response = $api->getOrder($orderId)->getInArray()['response'];

if (!$response['success']) {
    $message = $response['message'];            // e.g. "Order not found"
    $code    = $response['validation_code'];    // e.g. "not_found"
}
```

### c. The request never arrived

A DNS failure, a timeout, a TLS problem. Also not an exception:

```php
$response = $api->searchOrder()->getInArray()['response'];

if (isset($response['curlError'])) {
    // transport failure — safe to retry
}
```

A complete handler:

```php
function fetchOrder(API $api, string $orderId): ?array
{
    try {
        $response = $api->getOrder($orderId)->getInArray()['response'];
    } catch (VrioException $e) {
        report($e);
        return null;
    }

    if (isset($response['curlError'])) {
        throw new TransientVrioFailure($response['curlError']);   // retry this
    }

    if (!$response['success']) {
        return null;                                              // do not retry
    }

    return $response['data'];
}
```

### Timeouts and retries

Defaults are 30 s transfer, 10 s connect. Tighten them for user-facing paths
and widen them for background jobs:

```php
$interactive = new API($key, ['timeout' => 5,  'connectTimeout' => 2]);
$batch       = new API($key, ['timeout' => 120, 'connectTimeout' => 10]);
```

The package performs no retries — retry policy belongs to your job runner,
where it can be observed and bounded. Retry on `curlError`; do not blindly
retry `authorizeOrder` or `captureOrder`, which are not idempotent.

---

## 6. Logging in production

Logging is off unless you enable it. Enabling it needs a destination — either
`debugFile` or `debugSink` — or construction throws.

### Recommended production shape

```php
$api = new API($key, [
    'debug'              => true,
    'debugFile'          => '/var/log/vrio/client.log',
    'debugRetentionDays' => 7,
    'debugTimezone'      => 'UTC',
]);
```

- Redaction is on by default. Leave it on.
- One file per day: `client-2026-08-16.log`, `client-2026-08-17.log`, …
- Files older than the retention window are pruned once per process. Pruning
  only matches this package's own dated pattern and reads the date from the
  filename, never the mtime.
- Point `debugFile` at a directory your web user can write to and your
  application cannot serve. Never under a public document root.

### Shipping elsewhere

A sink closure replaces the file sink entirely — the package then writes no
files, and retention becomes yours to manage:

```php
'debugSink' => static function (string $entry): void {
    $psrLogger->debug($entry);
},
```

Anything throwing inside your sink is caught and discarded; a logging failure
will not break an API call.

### Before you ship logs anywhere

- The **URL is written verbatim**. Order IDs, customer IDs, cart tokens and
  payment token IDs appear in the path, and every query parameter you pass to a
  `GET` is recorded unmasked. See the README table for the exact calls.
- `debugRedact => false` writes live credentials and complete bodies to disk.
  Local debugging only.
- Redacted logs remain sensitive. They record who touched what and when.
  Encrypt at rest, restrict read access, and retain them no longer than the
  order data they describe.

---

## 7. Testing your own code

Inject a fake transport instead of stubbing the client:

```php
use AsterMD\VrioClient\API;
use AsterMD\VrioClient\Http\HttpClientInterface;
use AsterMD\VrioClient\Http\Request;
use AsterMD\VrioClient\Http\Response;

final class FakeVrio implements HttpClientInterface
{
    public $lastRequest;

    public function send(Request $request): Response
    {
        $this->lastRequest = $request;

        return new Response(200, '{"id":"ord_1"}', ['http_code' => 200]);
    }
}

$transport = new FakeVrio();
$api       = new API('test-key-placeholder', [], $transport);

$api->getOrder('ord_1');

self::assertSame('https://api.vrio.app/orders/ord_1', $transport->lastRequest->getUrl());
```

Use obviously fake keys. Never a real credential, not even an expired one.

---

## 8. Reusing the client

A single `API` instance is safe to reuse across many calls. Per-request state
is cleared after every call, so fields from one call cannot leak into the next.

Two caveats:

- `withProxy()` applies to the **next call only** and then clears itself.
- The instance is not designed for concurrent use inside one process. Give each
  worker or coroutine its own instance.

---

## 9. Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| `An API key is required` | Empty or whitespace key | Check the value you passed to the constructor — nothing is defaulted |
| `The host must be a bare hostname…` | A full URL in `host` | Strip `https://` and move any path to `basePath` |
| `Debug logging needs either a "debugFile"…` | `debug => true` with no destination | Add `debugFile` or `debugSink` |
| `No such method found` | Typo, or a method this client does not implement | `API::supportedMethods()` lists all 18 |
| `Missing required fields` | An empty ID, or a missing `order_id` / `cart_token` | Validate before calling |
| `curlError` on every call | Network, DNS, TLS or firewall | Verify the host, and that egress to it is allowed |
| No log file appears | Directory not writable, or `debug` still off | Check permissions on the `debugFile` directory |
| Log file has yesterday's date | Files are per calendar day in `debugTimezone` | Expected — set `debugTimezone` to match your ops timezone |

---

## 10. Migrating from a pre-1.0 internal build

If you are moving off an internal copy of this client:

| Before | Now |
|---|---|
| The old internal namespace | `use AsterMD\VrioClient\API;` |
| `new API($endpoint, $apiKey)` | `new API($apiKey, ['host' => $host])` |
| `$endpoint = 'https://api.vrio.app'` | `$host = 'api.vrio.app'` — no scheme |
| `$endpoint = 'https://api.vrio.app/v1'` | `['host' => 'api.vrio.app', 'basePath' => 'v1']` |
| `get(true)['payload']['apiKey']` | Absent — that was a credential leak |
| `withProxy($key)` | `withProxy($proxyUrl, $credentials)` — the old form never worked |
| `catch (\Exception $e)` | Still works; `VrioException` extends `\Exception` |

Behaviour that changed and may need attention:

- TLS verification is now enforced. If requests start failing against an
  internal proxy with a self-signed certificate, install the CA properly rather
  than looking for a switch — there is not one.
- Requests now time out. A call that previously hung forever will now fail
  after 30 seconds by default.
- An empty request body is now sent as `{}` rather than `[]`.

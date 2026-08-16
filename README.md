# astermd/vrio-client

A small, dependency-free PHP client for the VRIO commerce API — campaigns,
customers, offers, discounts, carts, orders and routes — with opt-in request
logging that is redacted by default.

> **Unofficial.** This is an independent client library. It is not the official
> VRIO PHP SDK and is not affiliated with, endorsed by or supported by VRIO.
> "VRIO" and related marks belong to their owner, <https://www.vrio.com/>. The
> name is used here only to identify the API this library talks to. See
> [LICENSE](LICENSE) for the full notice.

API reference: **<https://docs.vrio.com/reference/vrio-api-overview>**

## Requirements

- PHP **8.4 minimum**, **8.5 recommended**
- `ext-curl`, `ext-json`

No runtime dependencies. CI runs the full gate on 8.4 and 8.5.

## Installation

```bash
composer require astermd/vrio-client
```

## Quick start

```php
use AsterMD\VrioClient\API;

$api = new API($apiKey);                       // host defaults to api.vrio.app

$orders = $api->searchOrder(['with' => 'items'])->getInArray();

if ($orders['response']['success']) {
    foreach ($orders['response']['data'] as $order) {
        // ...
    }
}
```

The API key is a **required argument**. This package ships no default
credentials and reads none from the environment.

## Configuration

```php
$api = new API($apiKey, [
    'host'               => 'api.vrio.app',
    'basePath'           => '',
    'timeout'            => 30,
    'connectTimeout'     => 10,
    'debug'              => false,
    'debugRedact'        => true,
    'debugFile'          => null,
    'debugRetentionDays' => 7,
    'debugTimezone'      => 'UTC',
    'debugSink'          => null,
]);
```

| Option | Type | Default | Purpose |
|---|---|---|---|
| `host` | string | `api.vrio.app` | **Bare hostname only.** A scheme, path, query or space is rejected. |
| `basePath` | string | `''` | Optional path prefix below the host, e.g. `v1`. |
| `timeout` | int | `30` | Transfer timeout in seconds. |
| `connectTimeout` | int | `10` | Connection timeout in seconds. |
| `debug` | bool | `false` | Master switch for request/response logging. |
| `debugRedact` | bool | `true` | Mask credentials and sensitive fields in log entries. |
| `debugFile` | string | — | Base path for the built-in dated file sink. Required when `debug` is on and no `debugSink` is given. |
| `debugRetentionDays` | int | `7` | Days of log history to keep. `0` keeps everything. |
| `debugTimezone` | string | `UTC` | IANA timezone for log timestamps and dated filenames. |
| `debugSink` | callable | — | Replaces the file sink entirely. |

### Choosing an environment

Pass whichever host VRIO issued you. Production defaults to `api.vrio.app`; if
your account has a separate sandbox or test host, supply it the same way:

```php
$sandbox = new API($sandboxKey, ['host' => 'sandbox-host-from-your-vrio-account']);
```

Full URLs are rejected on purpose — the scheme is always HTTPS and path
assembly stays inside the client:

```php
new API($apiKey, ['host' => 'https://api.vrio.app']);   // throws VrioException
new API($apiKey, ['host' => 'api.vrio.app/v1']);        // throws VrioException
new API($apiKey, ['host' => 'api.vrio.app', 'basePath' => 'v1']);  // correct
```

## Reading a response

Every resource call is chainable and returns the client. Three accessors read
the result:

```php
$api->searchOrder()->get();          // envelope as a JSON string
$api->searchOrder()->getInArray();   // envelope decoded to an array
$api->searchOrder()->getInObject();  // envelope decoded to an object
```

Pass `true` to also receive the request URL and payload:

```php
$api->searchOrder(['with' => 'items'])->getInArray(true);
```

```php
[
    'response' => [
        'success' => true,
        'message' => '',
        'data'    => [ /* the provider's body, verbatim */ ],
    ],
    'payload' => [
        'endPoint' => 'https://api.vrio.app/orders?with=items',
        'with'     => 'items',
    ],
]
```

`payload` never contains your API key — it is safe to surface in your own
diagnostics.

When the provider returns an error the envelope carries it:

```php
[
    'success'         => false,
    'message'         => 'Order not found',
    'validation_code' => 'not_found',
    'data'            => [ /* the provider's error body */ ],
]
```

If the request never reached the provider, the array accessor returns
`['curlError' => '...']` instead.

## Available methods

Consult the [VRIO API reference](https://docs.vrio.com/reference/vrio-api-overview)
for the fields each endpoint accepts. `$params` is sent as query parameters on
`GET` calls and as the JSON body on the rest.

### Campaigns

| Method | Request |
|---|---|
| `getCampaignItems(string $campaignId, array $params = [])` | `GET /campaigns/{campaignId}/items` |

### Customers

| Method | Request |
|---|---|
| `getCustomer(string $customerId, array $params = [])` | `GET /customers/{customerId}` |

### Offers

| Method | Request |
|---|---|
| `searchOffer(array $params = [])` | `GET /offers` |

### Routes

| Method | Request |
|---|---|
| `getRoute(string $routeId, array $params = [])` | `GET /routes/{routeId}` |

### Discounts

| Method | Request |
|---|---|
| `validateDiscount(array $params = [])` | `POST /discounts/validate` |
| `calculateDiscount(array $params = [])` | `POST /discounts/calculate` — the array is sent as the body's `offers` member |

### Orders

| Method | Request |
|---|---|
| `searchOrder(array $params = [])` | `GET /orders` |
| `getOrder(string $orderId, array $params = [])` | `GET /orders/{orderId}` |
| `addOrder(array $params = [])` | `POST /orders` |
| `editOrder(array $params = [])` | `PATCH /orders/{order_id}` — requires `order_id` in `$params` |
| `processOrder(string $orderId, array $params = [])` | `POST /orders/{orderId}/process` |
| `completeOrder(string $orderId, array $params = [])` | `POST /orders/{orderId}/complete` |
| `authorizeOrder(string $orderId, array $params = [])` | `POST /orders/{orderId}/authorize` |
| `captureOrder(string $orderId, array $params = [])` | `POST /orders/{orderId}/capture` |
| `addOrderNote(string $orderId, array $params = [])` | `POST /orders/{orderId}/notes` |

### Carts

| Method | Request |
|---|---|
| `createCart(array $params = [])` | `POST /carts` |
| `createPaypalToken(array $params = [])` | `POST /carts/{cart_token}/payment_tokens` — requires `cart_token` |
| `getPaypalData(array $params = [])` | `GET /carts/{cart_token}/payment_tokens/{payment_token_id}` |

Examples:

```php
$api->getCampaignItems('camp_1', ['with' => 'offers'])->getInArray();

$api->addOrder([
    'connection_id' => 'con_1',
    'campaign_id'   => 'camp_1',
    'email'         => 'buyer@example.test',
])->getInObject();

$api->captureOrder('ord_1', ['amount' => 1000])->getInArray();
```

## Errors

Everything the package raises is an `AsterMD\VrioClient\Exception\VrioException`,
which extends `\Exception`:

```php
use AsterMD\VrioClient\Exception\VrioException;

try {
    $result = $api->getOrder($orderId)->getInArray();
} catch (VrioException $e) {
    // empty API key, non-bare host, missing required argument,
    // unknown method, or an undecodable response
}
```

Provider-side errors and transport failures are **not** exceptions — they come
back in the envelope, as shown above.

## Debug logging

Logging is off unless you turn it on. When on, entries are redacted by default
and written as copy-pasteable cURL commands with the response beneath.

```php
$api = new API($apiKey, [
    'debug'              => true,
    'debugFile'          => '/var/log/vrio/client.log',
    'debugRetentionDays' => 7,
    'debugTimezone'      => 'UTC',
]);
```

Which produces `/var/log/vrio/client-2026-08-16.log` containing:

```
[2026-08-16 09:14:02.481930 UTC]
curl --location --request POST 'https://api.vrio.app/orders' \
  --header 'Content-Type: application/json' \
  --header 'hostname: api.vrio.app' \
  --header 'X-Api-Key: [REDACTED]' \
  --data '{"email":"buyer@example.test","card":{"number":"[REDACTED]","cvv":"[REDACTED]"}}'

# Response: HTTP 201
{"id":"ord_1","access_token":"[REDACTED]"}
```

### What is redacted

Headers and bodies. In headers: `X-Api-Key`, `Authorization` (the scheme is
kept, so `Bearer [REDACTED]`), `Proxy-Authorization`, `Cookie`. In bodies, by
field name: API keys and secrets, passwords, every `*_token` including
`access_token` and `refresh_token`, card numbers, CVV/CVC, expiry fields, bank
account and routing numbers, IBAN, and government identifiers such as SSN, tax
ID and date of birth. Card numbers are additionally caught by shape — any
13–19 digit string that passes a Luhn check is masked wherever it appears.

A body that is not decodable JSON cannot be field-masked, so it is replaced
whole rather than logged on the chance it is harmless.

**Redaction never changes what is sent or what you receive.** The logger reads
from immutable request and response objects and produces a string; the wire
request and the value returned to your code are untouched. The test suite
asserts this directly.

### The URL is logged verbatim

By design — you need the real URL for a log entry to be reproducible. That
means anything the API takes in a path segment or query string is written to
the log even with redaction on. In this client that is:

| Call | What lands in the log |
|---|---|
| `getPaypalData()` | the cart token and the payment token, both in the path |
| `createPaypalToken()` | the cart token, in the path |
| `getCustomer()` | the customer ID, in the path |
| `getOrder()`, `processOrder()`, `completeOrder()`, `authorizeOrder()`, `captureOrder()`, `addOrderNote()`, `editOrder()` | the order ID, in the path |
| `getCampaignItems()`, `getRoute()` | the campaign or route ID, in the path |
| any `GET` with `$params` | every query parameter you passed, encoded but unmasked |

Do not pass sensitive values as query parameters to `GET` calls if your log
retention cannot accommodate them.

### Turning redaction off

```php
$api = new API($apiKey, [
    'debug'       => true,
    'debugRedact' => false,     // logs the real API key and full bodies
    'debugFile'   => '/tmp/vrio-debug.log',
]);
```

This writes live credentials and complete payloads to disk. It exists for local
debugging. **Never enable it in production.**

### File rotation and retention

The built-in sink writes one file per calendar day, deriving the name from your
base path: `/var/log/vrio/client.log` becomes `client-2026-08-16.log`,
`client-2026-08-17.log`, and so on.

Pruning removes files older than `debugRetentionDays` (default 7; `0` keeps
everything). It only ever matches this package's own dated filename pattern for
your base path — other files in the directory are never touched — and it reads
the age from the filename rather than the modification time, so an appended-to
or restored file keeps its true age. It runs once per process, not once per
request.

### Sending logs somewhere else

Supply a closure and the file sink is replaced entirely. The package then
writes no files, and retention becomes your responsibility:

```php
$api = new API($apiKey, [
    'debug'     => true,
    'debugSink' => static function (string $entry): void {
        $myLogger->debug($entry);
    },
]);
```

The closure receives the finished entry, already redacted unless you opted out.
This is the extension point for any external destination — a PSR-3 logger, a
queue, a log shipper, an object store.

A failure inside your sink is caught and discarded: logging must never break an
API call.

### Logs stay sensitive after redaction

A redacted entry still records which account touched which order, cart,
customer and route, and when. Store logs on encrypted volumes, restrict read
access, ship them only to systems cleared for that data, and apply a retention
period at least as strict as the rest of your order data.

## Proxy support

```php
$api->withProxy('proxy.example.test:8080', 'user:password')
    ->searchOrder()
    ->getInArray();
```

The setting applies to the next call only.

## Custom transport

Pass anything implementing `HttpClientInterface` as the third constructor
argument to route requests through your own stack, or to test without a network:

```php
use AsterMD\VrioClient\Http\HttpClientInterface;
use AsterMD\VrioClient\Http\Request;
use AsterMD\VrioClient\Http\Response;

final class MyTransport implements HttpClientInterface
{
    public function send(Request $request): Response
    {
        // ... hand $request to Guzzle, a PSR-18 client, a fixture ...
        return new Response(200, $body, ['http_code' => 200]);
    }
}

$api = new API($apiKey, [], new MyTransport());
```

TLS peer and host verification are always on in the bundled cURL transport, and
there is no option to disable them.

## Further documentation

- [Integration guide](docs/INTEGRATION_GUIDE.md) — setup, environments,
  production logging, error handling, troubleshooting.
- [Architecture](docs/ARCHITECTURE.md) — how a call flows through the package
  and where to extend it.
- [Security policy](SECURITY.md) — private disclosure and credential handling.
- [Changelog](CHANGELOG.md)

## Development

```bash
composer install
composer gate     # phpcs → phpstan (max) → phpunit
```

See [CLAUDE.md](CLAUDE.md) for the conventions the gate enforces. No test in
this suite makes a network call.

## Support

Email **info@astermd.com**, or open an issue at
<https://github.com/astermd/vrio-client/issues>.

Report security issues privately — see [SECURITY.md](SECURITY.md). Do not open
a public issue for a vulnerability.

## Compliance

Using this package does not by itself make your application PCI DSS, HIPAA or
GDPR compliant. It is one component in your system. Scoping, encryption at
rest, access control, audit logging, breach procedures and your agreements with
VRIO and your payment processors remain your responsibility.

## License

MIT — see [LICENSE](LICENSE), including the trademark and affiliation notice.

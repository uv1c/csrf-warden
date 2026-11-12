# Usage

## Requirements

- PHP 8.0 or later
- No runtime dependencies

## Install

```sh
composer require uv1c/csrf-warden
```

## Configure the session first

The library never starts or configures the session. Do that in your application
before creating `SessionStorage`:

```php
session_set_cookie_params([
    'httponly' => true,
    'secure' => true,       // use false only for local HTTP development
    'samesite' => 'Lax',
]);

session_start();
```

## Create the service

```php
use CsrfWarden\CSRF;
use CsrfWarden\Storage\SessionStorage;

$csrf = new CSRF(new SessionStorage());
```

The default token lifetime is two hours. Pass a `TokenLifetime` to change it:

```php
use CsrfWarden\Token\TokenLifetime;

$csrf = new CSRF(new SessionStorage(), TokenLifetime::seconds(3600));

// Or disable expiry entirely:
$csrf = new CSRF(new SessionStorage(), TokenLifetime::neverExpires());
```

## Forms

Render hidden fields with a scoped token (plus a binding field when a
double-submit policy is configured):

```php
echo $csrf->field('login');
```

Check the request when the form is submitted:

```php
use CsrfWarden\Http\GlobalsRequest;

if (!$csrf->checkRequest(new GlobalsRequest(), 'login')) {
    http_response_code(403);
    exit('Invalid CSRF token');
}
```

`checkRequest()` reads `_csrf_token` from `$_POST` (or the `X-CSRF-TOKEN`
header) and `_csrf_binding` from `$_POST` (or `X-CSRF-BINDING`).

## AJAX

Render meta tags in the page head:

```php
echo $csrf->metaTag('delete-account');
```

Send the token and binding in headers, reading the meta tags inside the event
handler so the values never live in a global variable:

```js
document.querySelector('#delete').addEventListener('click', async () => {
    const token = document.querySelector('meta[name="csrf-token"]').content;
    const binding = document.querySelector('meta[name="csrf-binding"]').content;

    await fetch('/account', {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': token,
            'X-CSRF-BINDING': binding,
        },
    });
});
```

Check the header on the server:

```php
use CsrfWarden\Http\GlobalsRequest;

if (!$csrf->checkRequest(new GlobalsRequest(), 'delete-account')) {
    http_response_code(403);
    exit('Invalid CSRF token');
}
```

## Security policy

Rate limiting, double-submit cookies, and origin checks are opt-in. Compose
them in a `SecurityPolicy` and pass it as the fourth constructor argument:

```php
use CsrfWarden\Cookie\CookieOptions;
use CsrfWarden\Cookie\NativeCookieJar;
use CsrfWarden\Http\OriginVerifier;
use CsrfWarden\RateLimit\StorageRateLimiter;
use CsrfWarden\Security\DoubleSubmitCookie;
use CsrfWarden\Security\SecurityPolicy;
use CsrfWarden\Storage\SessionStorage;
use CsrfWarden\SystemClock;

$clock = new SystemClock();

$csrf = new CSRF(
    new SessionStorage(),
    TokenLifetime::default(),
    $clock,
    new SecurityPolicy(
        new DoubleSubmitCookie(
            new NativeCookieJar(),
            new CookieOptions(secure: true, sameSite: 'Lax')
        ),
        new StorageRateLimiter(new SessionStorage('_csrf_warden_rate'), $clock, 120, 60),
        new OriginVerifier(['https://example.com'])
    )
);
```

Each argument is optional; pass `null` to leave a layer out.

- **Double-submit** - one 256-bit binding per session in an `HttpOnly` cookie,
  echoed in every form/header. CSRF tokens stay unique per form.
- **Rate limit** - fixed windows keyed by scope and client IP, or by the session
  fingerprint when no request context is supplied. Use a shared backend (Redis,
  database) under load; `InMemoryRateLimiter` is process-local.
- **Origin** - prefers `Sec-Fetch-Site`, then `Origin`, then `Referer`, and
  rejects requests where none establish a trusted origin.

When an `OriginVerifier` is configured, `check()` requires a `RequestContext`;
use `checkRequest()` or pass one explicitly. This prevents accidentally
bypassing the origin check.

## Cookie-less browsers

Browsers such as Tor at its safest level block cookies. With cookies blocked,
the binding cookie is absent and the library falls back to signed-token
validation. `SessionStorage` cannot keep a secret without a session cookie, so
use a shared backend plus origin checks:

```php
use CsrfWarden\Storage\FileStorage;

$storage = new FileStorage(__DIR__ . '/../var/csrf');

$csrf = new CSRF(
    $storage,
    TokenLifetime::default(),
    new SystemClock(),
    new SecurityPolicy(
        null,
        new StorageRateLimiter($storage, new SystemClock(), 60, 60),
        new OriginVerifier(['https://example.com'])
    )
);
```

Keep the `FileStorage` directory outside the web root. This mode is weaker than
session binding: the secret is shared by all visitors and cannot identify a
user. It still stops blind cross-site forgery, but it leans on origin checks and
rate limits. Prefer session binding whenever cookies work.

## Scopes

Use a different scope for each action or form family. A token issued for
`login` does not validate for `delete-account`:

```php
$loginToken = $csrf->token('login');

$csrf->check($loginToken, 'login');          // true
$csrf->check($loginToken, 'delete-account'); // false
```

An empty scope (`''`) is its own scope, not a wildcard.

## Rotate after authentication changes

Call `rotate()` after login and logout so tokens issued before the session
changed stop working:

```php
$csrf->rotate();
```

## Fingerprint

`fingerprint()` returns a stable, non-secret 32-character identifier derived
from the session secret:

```php
error_log('csrf session: ' . $csrf->fingerprint());
```

It is safe to log, can be used as a cache key, and changes after `rotate()`. It
is not a CSRF token and does not identify a user.

## Storages

| Storage | Use case |
| --- | --- |
| `CsrfWarden\Storage\SessionStorage` | Recommended for normal PHP sessions. |
| `CsrfWarden\Storage\FileStorage` | Cookie-less deployments; shared secret and rate limits. |
| `CsrfWarden\Storage\ArrayStorage` | In-memory; tests or framework-managed state. |
| Custom `Storage` | Redis, database, or framework session abstraction. |
| Custom `AtomicStorage` | Same as above, plus race-safe first-use secret creation. |

```php
use CsrfWarden\Storage\Storage;

final class RedisStorage implements Storage
{
    // get(), set(), remove()
}
```

If your backend can create a value only when it does not exist yet, implement
`CsrfWarden\Storage\AtomicStorage` as well.

## Testing with a fixed clock

Inject a `CsrfWarden\Clock` to make expiry deterministic:

```php
use CsrfWarden\Clock;

final class FixedClock implements Clock
{
    public function __construct(private int $now)
    {
    }

    public function now(): int
    {
        return $this->now;
    }
}

$csrf = new CSRF(
    new ArrayStorage(),
    TokenLifetime::seconds(100),
    new FixedClock(1_700_000_000)
);
```

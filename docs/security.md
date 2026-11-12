# Security

## The attack this prevents

CSRF abuses the fact that browsers attach cookies to requests automatically.
The classic session-cookie attack flow:

1. **The user logs in.** The server creates a session and sends a session cookie.
2. **The session is established.** The browser stores the cookie and sends it
   with every request to your domain.
3. **The user visits a malicious site** in another tab or window, still logged
   in to your application.
4. **The malicious site submits a hidden form or runs JavaScript** that targets
   your application.
5. **The browser automatically includes the session cookie**, because the
   request goes to your domain.
6. **Without CSRF protection**, your server sees a valid session and performs
   the action - money transferred, account deleted, email changed.
7. **With CSRF protection**, your server looks for a token, does not find one
   (or finds an invalid one), and rejects the request.

The malicious origin can *send* the request but cannot *read* your page, so it
cannot learn the token. The token is the proof that the request came from a page
your application rendered.

## Defense layers

The signed token is always on. The other layers are opt-in through
`CsrfWarden\Security\SecurityPolicy`:

| Layer | Class | Stops |
| --- | --- | --- |
| Signed scoped token | built in | Forged or replayed cross-scope submissions |
| Double-submit binding | `Security\DoubleSubmitCookie` | Cross-origin requests that replay a leaked token |
| Rate limiting | `RateLimit\InMemoryRateLimiter`, `RateLimit\StorageRateLimiter` | Token guessing and check-flooding |
| Origin verification | `Http\OriginVerifier` | Requests whose origin cannot be trusted |

Layers are additive. A request must pass every configured layer and then the
signed-token check. Disabling a layer only removes that layer; it does not
weaken the others.

## Token design

Each token is:

```
base64url( pad[32] | (HMAC-SHA256(secret, scope | timestamp) XOR pad)[32] | timestamp[8] )
```

- **Secret** - 32 random bytes created once per session and stored server-side.
  It is never sent to the browser.
- **Scope** - length-prefixed before hashing, so `("ab", "c")` and `("a", "bc")`
  cannot collide. A token for one form does not work for another.
- **Timestamp** - included in the MAC, so it cannot be changed without
  invalidating the token.
- **Masking** - every token is XOR-masked with fresh random bytes. Issuing the
  same scope twice produces different strings, which mitigates BREACH-style
  compression attacks.
- **Randomness** - `random_bytes(32)` supplies 256 bits of cryptographically
  secure randomness; `bin2hex()` turns it into a readable value where a hex
  string is required.
- **Comparison** - tokens are checked with `hash_equals()` to avoid timing
  leaks. An attacker cannot learn a token by measuring response times.

## Expiry and clock skew

The default lifetime is 7200 seconds. `TokenLifetime::neverExpires()` disables
expiry. Tokens dated up to 60 seconds in the future are accepted to tolerate
small clock differences; anything further ahead is rejected.

`rotate()` removes the secret and the double-submit cookie, invalidating every
token and binding issued so far. Call it after login and logout.

## Fingerprint

`fingerprint()` is an HMAC of a fixed context string keyed by the session
secret. It is:

- stable for the lifetime of the secret,
- safe to log or use as a cache key,
- changed by `rotate()`,
- not usable as a CSRF token,
- not a client fingerprint and not a user identifier.

The legacy single-file implementation used `md5(IP | User-Agent)`. That is
weak, leaks personal data into logs, and breaks for users behind proxies or
changing networks. This library deliberately does not bind tokens to IP
addresses or User-Agent strings.

## Session cookies

CSRF protection is only as strong as the session cookie. Set at least:

```php
session_set_cookie_params([
    'httponly' => true,
    'secure' => true,
    'samesite' => 'Lax',
]);
```

Use `SameSite=Strict` when your application can tolerate it. `SameSite` is an
additional layer, not a replacement for tokens.

## Double-submit cookies

`DoubleSubmitCookie` creates one 256-bit random binding per session with
`bin2hex(random_bytes(32))`. It is stored in an `HttpOnly` cookie and echoed in
every form (`_csrf_binding`) and AJAX request (`X-CSRF-BINDING`).

On submission the cookie and the submitted value are compared with
`hash_equals()`. A cross-origin attacker can cause the cookie to be sent but
cannot read it, so it cannot produce the matching value.

The binding is shared per session; the CSRF tokens themselves remain unique per
form and per issue. A missing binding cookie falls back to signed-token
validation, which is what allows cookie-less browsers to keep working.

## Origin checks

`OriginVerifier` looks at the request in this order:

1. `Sec-Fetch-Site` - rejects `cross-site` outright.
2. `Origin` - must match one of the configured origins.
3. `Referer` - used only as a fallback, never as the sole signal.
4. If none of the above establish a trusted origin, the request is rejected.

It fails closed. `Referer` is frequently stripped by privacy tools and proxies,
so it is never trusted on its own.

## Rate limiting

Configure a limiter in the `SecurityPolicy` and every `check()` /
`checkRequest()` consumes one attempt for the scope and client IP. When no
request context is supplied, the key falls back to the session fingerprint so
callers do not share one global bucket:

```php
new StorageRateLimiter($storage, $clock, 120, 60); // 120 attempts per minute
```

`InMemoryRateLimiter` is process-local and useful for tests or single-process
workers. `StorageRateLimiter` keeps state in any `Storage` backend so it can be
shared across requests.

Both use fixed windows. The storage implementation performs a plain
read-modify-write; under heavy concurrency use a backend with atomic increments
(Redis `INCR`, a database counter) so the limit cannot be exceeded.

Rate limiting raises the cost of guessing, but it is not a substitute for the
token. A correct token is still required.

## Cookie-less and privacy-focused browsers

Some browsers (Tor Browser at its safest level, hardened Firefox profiles, and
extensions) block cookies by default. The library must not silently fail open.

- The signed token is validated on its own when the binding cookie is absent.
- With session cookies blocked, `SessionStorage` cannot persist the secret, so
  session-bound tokens fail closed.
- For those deployments use `FileStorage` (or a shared Redis/database store)
  with `OriginVerifier` and `StorageRateLimiter`:

```php
$storage = new FileStorage(__DIR__ . '/../var/csrf');
$csrf = new CSRF(
    $storage,
    TokenLifetime::default(),
    new SystemClock(),
    new SecurityPolicy(
        null,                                  // no binding cookie
        new StorageRateLimiter($storage, new SystemClock(), 60, 60),
        new OriginVerifier(['https://example.com'])
    )
);
```

This mode is **weaker** than session binding: the secret is shared by every
visitor, so it cannot distinguish users. It still prevents blind cross-site
forgery because the attacker cannot read the token or the origin data, but it
relies on origin checks and rate limits for the properties that session binding
would otherwise provide. Prefer session binding whenever cookies work.

## Checklist

- **No predictable tokens.** Tokens are HMAC-SHA256 over a per-session secret
  with a fresh 256-bit random pad. No timestamps, counters, or user data are
  used as the token.
- **No tokens in global JavaScript variables.** The examples read meta tags
  inside the event handler and keep the value in a local variable.
- **No unprotected AJAX.** `checkRequest()` reads the same field or header for
  every request; the AJAX example sends `X-CSRF-TOKEN` and `X-CSRF-BINDING`.
- **No reliance on `Referer` alone.** `OriginVerifier` prefers Fetch Metadata
  and `Origin`, uses `Referer` only as a fallback, and fails closed.
- **No token reuse across forms.** Every `token()` call produces a distinct
  masked value, and scopes prevent cross-form use.
- **`random_bytes(32)`** generates 32 cryptographically secure bytes (256 bits).
- **`bin2hex()`** produces the readable hex binding value.
- **`hash_equals()`** compares tokens and bindings in constant time.

## What this library does not do

- It does not start or configure sessions.
- It does not store tokens in the session; only the secret is stored.
- It does not bind tokens to IP addresses or User-Agent strings.
- It does not replace authentication or authorization.
- It does not protect against XSS. An attacker who can run JavaScript in your
  page can read the token and the binding.
- It does not protect GET requests. State-changing endpoints must use
  `POST`/`PUT`/`PATCH`/`DELETE` and be checked.

## Storage trust

The secret is only as safe as the storage backend. Use a server-side session
store, not a client-side cookie. If you implement a custom `Storage`, make sure
values cannot be read or written by the client. `FileStorage` writes files with
mode `0600`; keep its directory outside the web root.

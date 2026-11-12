# API reference

## `CsrfWarden\CSRF`

```php
new CSRF(
    Storage $storage,
    ?TokenLifetime $lifetime = null,
    ?Clock $clock = null,
    ?SecurityPolicy $security = null
);
```

| Method | Description |
| --- | --- |
| `token(string $scope = ''): string` | Issue a token for a scope. Also creates the binding when a double-submit policy is configured. |
| `check(mixed $token, string $scope = '', mixed $binding = null, ?RequestContext $request = null): bool` | Validate a token and every configured security layer. Accepts untrusted input and returns `false` for non-strings. Throws `MissingRequestContext` when an origin verifier is configured but no request is supplied. |
| `checkRequest(RequestContext $request, string $scope = ''): bool` | Read the token and binding from the request (field or header) and validate them. |
| `fingerprint(): string` | Stable, non-secret session identifier (32 hex characters). |
| `rotate(): void` | Remove the secret and binding cookie and invalidate all issued tokens. |
| `field(string $scope = ''): string` | Hidden inputs using `FIELD_NAME` and, when configured, `BINDING_FIELD_NAME`. |
| `metaTag(string $scope = ''): string` | Meta tags for AJAX clients. |

Constants:

- `CSRF::FIELD_NAME` - `_csrf_token`
- `CSRF::HEADER_NAME` - `X-CSRF-TOKEN`
- `CSRF::BINDING_FIELD_NAME` - `_csrf_binding`
- `CSRF::BINDING_HEADER_NAME` - `X-CSRF-BINDING`

## `CsrfWarden\Token\TokenLifetime`

| Method | Description |
| --- | --- |
| `TokenLifetime::seconds(int $seconds): self` | Lifetime in seconds. Negative values throw `InvalidLifetime`. |
| `TokenLifetime::default(): self` | 7200 seconds. |
| `TokenLifetime::neverExpires(): self` | Lifetime `0`. |
| `allows(int $ageInSeconds): bool` | Whether a token of that age is acceptable. |
| `durationInSeconds(): int` | Configured lifetime. |

## Storage

`CsrfWarden\Storage\Storage`:

```php
public function get(string $key): ?string;
public function set(string $key, string $value): void;
public function remove(string $key): void;
```

`CsrfWarden\Storage\AtomicStorage` adds:

```php
public function add(string $key, string $value): bool;
```

`add()` must store the value only when the key does not already exist and
return `true` when it stored it.

Bundled implementations:

- `CsrfWarden\Storage\ArrayStorage`
- `CsrfWarden\Storage\SessionStorage` (namespace configurable, default `_csrf_warden`)
- `CsrfWarden\Storage\FileStorage` (directory-backed, atomic `add()`, mode `0600`)

`FileStorage` keeps a stable secret and shared rate-limit state for cookie-less
deployments. Keep its directory outside the web root.

## Security

`CsrfWarden\Security\SecurityPolicy`:

```php
new SecurityPolicy(
    ?DoubleSubmitCookie $doubleSubmit = null,
    ?RateLimiter $rateLimiter = null,
    ?OriginVerifier $originVerifier = null
);
```

`CsrfWarden\Security\DoubleSubmitCookie`:

| Method | Description |
| --- | --- |
| `binding(): string` | Return the session binding, creating a 64-hex value with `random_bytes(32)` when needed. |
| `hasCookie(): bool` | Whether the binding cookie is present. |
| `matches(mixed $submitted): bool` | Constant-time comparison with `hash_equals()`. |
| `rotate(): void` | Delete the binding cookie. |
| `cookieName(): string` | Configured cookie name. |

`CsrfWarden\Http\OriginVerifier`:

| Method | Description |
| --- | --- |
| `new OriginVerifier(array $allowedOrigins)` | Accepts absolute origins such as `https://example.com`. Invalid values throw `InvalidArgumentException`. |
| `isAllowed(RequestContext $request): bool` | Prefers `Sec-Fetch-Site`, then `Origin`, then `Referer`; fails closed. Safe methods are always allowed. |

## HTTP request context

`CsrfWarden\Http\RequestContext`:

```php
public function method(): string;
public function header(string $name): ?string;
public function post(string $name): mixed;
public function clientIp(): ?string;
```

`CsrfWarden\Http\GlobalsRequest` reads from `$_SERVER` and `$_POST`. Implement
the interface to adapt a framework request.

## Cookies

`CsrfWarden\Cookie\CookieJar`:

```php
public function get(string $name): ?string;
public function set(string $name, string $value, CookieOptions $options): void;
public function delete(string $name, CookieOptions $options): void;
```

`CsrfWarden\Cookie\NativeCookieJar` uses `setcookie()`. `CookieOptions` defaults
to `Secure=true`, `HttpOnly=true`, `SameSite=Lax`, path `/`, two-hour lifetime.
`SameSite=None` requires `Secure`.

## Rate limiting

`CsrfWarden\RateLimit\RateLimiter`:

```php
public function allow(string $key): bool;
```

- `InMemoryRateLimiter(Clock $clock, int $limit = 120, int $windowSeconds = 60)` - process-local.
- `StorageRateLimiter(Storage $storage, Clock $clock, int $limit = 120, int $windowSeconds = 60)` - shared through any `Storage`.

Both use fixed windows. The storage limiter is a plain read-modify-write; use an
atomic backend for strict limits under heavy concurrency.

## Clocks

`CsrfWarden\Clock`:

```php
public function now(): int;
```

`CsrfWarden\SystemClock` uses `time()`. Inject your own clock in tests.

## Exceptions

- `CsrfWarden\Exception\InvalidLifetime` - negative TTL.
- `CsrfWarden\Exception\SessionNotStarted` - `SessionStorage` used before
  `session_start()`.
- `CsrfWarden\Exception\MissingRequestContext` - an `OriginVerifier` is
  configured but `check()` was called without a `RequestContext`.

## Internal classes

These are implementation details, but they are final and independently
testable:

- `CsrfWarden\Secret` - secret creation, atomic race handling, fingerprint.
- `CsrfWarden\Token\TokenCodec` - wire format encoding/decoding.
- `CsrfWarden\Token\DecodedToken` - decoded token value object.
- `CsrfWarden\Token\TokenService` - issue and validate tokens.

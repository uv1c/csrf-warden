<p align="center">
  <img src="assets/logo.svg" width="96" height="96" alt="csrf-warden shield">
</p>

<h1 align="center">csrf-warden</h1>

<p align="center">
  Clean, dependency-free Cross-Site Request Forgery protection for PHP 8.0+.
</p>

## Features

- Scoped tokens: a `login` token cannot be used for `delete-account`.
- Expiring tokens (two hours by default, or never).
- HMAC-SHA256 signed with a per-session secret that never reaches the client.
- Masked with fresh random bytes on every issue, mitigating BREACH-style attacks.
- Optional double-submit cookie binding, rate limiting, and origin checks.
- Cookie-less fallback for browsers that block cookies (Tor, hardened profiles).
- No global state, no singleton, no static mutable properties.
- Storage abstraction with atomic first-use secret creation.
- Small, final classes with explicit dependencies.
- No runtime dependencies.

## Installation

```sh
composer require uv1c/csrf-warden
```

## Quick start

Configure the session in your application first:

```php
session_set_cookie_params([
    'httponly' => true,
    'secure' => true,
    'samesite' => 'Lax',
]);

session_start();
```

Create the service. For the full set of protections, wrap it in a
`SecurityPolicy`:

```php
use CsrfWarden\Cookie\CookieOptions;
use CsrfWarden\Cookie\NativeCookieJar;
use CsrfWarden\CSRF;
use CsrfWarden\Http\OriginVerifier;
use CsrfWarden\RateLimit\StorageRateLimiter;
use CsrfWarden\Security\DoubleSubmitCookie;
use CsrfWarden\Security\SecurityPolicy;
use CsrfWarden\Storage\SessionStorage;
use CsrfWarden\SystemClock;

$clock = new SystemClock();

$csrf = new CSRF(
    new SessionStorage(),
    null,
    $clock,
    new SecurityPolicy(
        new DoubleSubmitCookie(new NativeCookieJar(), new CookieOptions(secure: true)),
        new StorageRateLimiter(new SessionStorage('_csrf_warden_rate'), $clock, 120, 60),
        new OriginVerifier(['https://example.com'])
    )
);
```

The policy is optional. `new CSRF(new SessionStorage())` keeps the plain
signed-token behavior.

Render the hidden fields in a form:

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

For AJAX, render `$csrf->metaTag('login')` in the page head and send
`X-CSRF-TOKEN` plus `X-CSRF-BINDING`.

Rotate the secret after login and logout:

```php
$csrf->rotate();
```

## Simple API

| Method | Purpose |
| --- | --- |
| `token($scope)` | Issue a token. |
| `check($token, $scope, $binding, $request)` | Validate a token and any configured security layers. |
| `checkRequest($request, $scope)` | Read the token/binding from a request and validate. |
| `fingerprint()` | Stable, non-secret session identifier. |
| `rotate()` | Invalidate all issued tokens and bindings. |
| `field($scope)` | Hidden inputs for forms. |
| `metaTag($scope)` | Meta tags for AJAX. |

```php
$token = $csrf->token('login');

$csrf->check($token, 'login');          // true
$csrf->check($token, 'delete-account'); // false
```

## Documentation

- [Usage](docs/usage.md) - forms, AJAX, scopes, storages, testing.
- [Security](docs/security.md) - token design, expiry, fingerprints, caveats.
- [API reference](docs/api.md) - classes, methods, interfaces.
- [Migration](docs/migration.md) - moving from the legacy single-file class.

## Project layout

```
.github/      CI workflow (PHP 8.0-8.4 matrix)
assets/       logo and static assets
bin/          csrf-warden CLI (serve examples, lint)
docs/         usage, security, API, migration
example/      runnable examples
src/          library source (PSR-4: CsrfWarden\)
tests/        PHPUnit tests and dependency-free smoke test
composer.json
```

## Examples

```sh
php bin/csrf-warden serve
```

Then open <http://127.0.0.1:8000/form.php> or
<http://127.0.0.1:8000/ajax.php>. See [example/README.md](example/README.md).

## Development

```sh
composer install
composer test
```

`composer test` runs PHPUnit and the dependency-free smoke test. The CLI also
offers a syntax check:

```sh
php bin/csrf-warden lint
```

## License

MIT. See [LICENSE](LICENSE).

# Migrating from the legacy single-file class

The original `CSRF.php` was a static class that stored a raw token in
`$_SESSION` and mixed an MD5 IP/User-Agent fingerprint into it. The rewrite
keeps the familiar operations but makes them instance methods with explicit
dependencies.

## Method mapping

| Legacy | New |
| --- | --- |
| `CSRF::generate($form)` | `$csrf->token($form)` |
| `CSRF::check($token, $form)` | `$csrf->check($token, $form)` |
| `CSRF::fingerprint()` (protected) | `$csrf->fingerprint()` (public) |
| `CSRF::token()` (protected) | `$csrf->token()` (public) |
| `CSRF::$session` | `new SessionStorage($namespace)` |

## Before

```php
require 'CSRF.php';

$token = CSRF::generate('login');

if (!CSRF::check($_POST['token'] ?? null, 'login')) {
    exit('Invalid CSRF token');
}
```

## After

```php
require 'vendor/autoload.php';

use CsrfWarden\CSRF;
use CsrfWarden\Storage\SessionStorage;

session_start();

$csrf = new CSRF(new SessionStorage());
$token = $csrf->token('login');

if (!$csrf->check($_POST['_csrf_token'] ?? null, 'login')) {
    exit('Invalid CSRF token');
}
```

## Important differences

- Tokens are no longer stored in `$_SESSION`; only the 32-byte secret is.
- Tokens are HMAC-signed, scoped, timestamped, and masked.
- Tokens expire (two hours by default).
- `fingerprint()` is a session identifier derived from the secret, not
  `md5(IP | User-Agent)`.
- There is no static mutable state and no global `$session` property.
- The class is namespaced: `CsrfWarden\CSRF`.
- Rate limiting, double-submit cookies, and origin checks are available through
  an optional `SecurityPolicy`. They are not enabled by default.

## Enabling the extra layers

The quickest migration keeps `check()` working and adds a policy:

```php
use CsrfWarden\Cookie\CookieOptions;
use CsrfWarden\Cookie\NativeCookieJar;
use CsrfWarden\Http\GlobalsRequest;
use CsrfWarden\Http\OriginVerifier;
use CsrfWarden\Security\DoubleSubmitCookie;
use CsrfWarden\Security\SecurityPolicy;

$csrf = new CSRF(
    new SessionStorage(),
    null,
    null,
    new SecurityPolicy(
        new DoubleSubmitCookie(new NativeCookieJar(), new CookieOptions(secure: true)),
        null,
        new OriginVerifier(['https://example.com'])
    )
);
```

Then render `$csrf->field('login')` and validate with
`$csrf->checkRequest(new GlobalsRequest(), 'login')`. The legacy
`$csrf->check($token, 'login')` signature still works when no origin verifier is
configured.

## Field name

The legacy examples often used a custom POST field. The new default is
`_csrf_token`, available as `CSRF::FIELD_NAME`. Use `$csrf->field('login')` to
render it, or read your own field name and pass the value to `check()`.

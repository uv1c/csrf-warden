# Examples

Run the examples from the repository root with PHP's built-in server:

```sh
php -S 127.0.0.1:8000 -t example
```

or use the bundled command:

```sh
php bin/csrf-warden serve
```

Then open <http://127.0.0.1:8000/> for the index, or directly:

- <http://127.0.0.1:8000/form.php> - a classic HTML form using `field()` and
  `checkRequest()`
- <http://127.0.0.1:8000/ajax.php> - `fetch()` sending `X-CSRF-TOKEN` and
  `X-CSRF-BINDING`

`bootstrap.php` falls back to a small PSR-4 loader for `src/` when
`vendor/autoload.php` does not exist, so `composer install` is optional for the
examples.

## Security policy

`Example::csrf()` wires up the full policy:

- `DoubleSubmitCookie` with a `NativeCookieJar` binding cookie,
- session-backed `StorageRateLimiter` (120 attempts per minute),
- `OriginVerifier` for the current scheme and host.

The binding cookie is `HttpOnly` and `SameSite=Lax`. The `Secure` flag is set
automatically when `HTTPS` is on; the local server runs over HTTP, so it is off
there.

The form and AJAX pages call `checkRequest()`, which reads both the token and the
binding from the POST body or the headers. Requests without an `Origin`,
`Sec-Fetch-Site`, or `Referer` header are rejected by the origin verifier.

## Cookie-less note

The examples use session cookies. If you need to support browsers that block
cookies, replace `SessionStorage` with `FileStorage` and read
[the cookie-less section](../docs/usage.md#cookie-less-browsers) first - that
mode is weaker than session binding.

These examples are intentionally small: add your application's logging, error
handling, and framework integration before using them as production code.

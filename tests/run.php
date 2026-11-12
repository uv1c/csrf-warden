<?php

// Dependency-free smoke test: php tests/run.php (or: composer test)
declare(strict_types=1);

use CsrfWarden\Cookie\CookieOptions;
use CsrfWarden\CSRF;
use CsrfWarden\Exception\InvalidLifetime;
use CsrfWarden\Exception\MissingRequestContext;
use CsrfWarden\Exception\SessionNotStarted;
use CsrfWarden\Http\OriginVerifier;
use CsrfWarden\RateLimit\InMemoryRateLimiter;
use CsrfWarden\RateLimit\StorageRateLimiter;
use CsrfWarden\Secret;
use CsrfWarden\Security\DoubleSubmitCookie;
use CsrfWarden\Security\SecurityPolicy;
use CsrfWarden\Storage\ArrayStorage;
use CsrfWarden\Storage\AtomicStorage;
use CsrfWarden\Storage\FileStorage;
use CsrfWarden\Storage\SessionStorage;
use CsrfWarden\Tests\ArrayCookieJar;
use CsrfWarden\Tests\ArrayRequest;
use CsrfWarden\Token\TokenLifetime;

ob_start(); // let session_start() work after earlier output in CLI

$files = [
    'Exception/InvalidLifetime',
    'Exception/MissingRequestContext',
    'Exception/SessionNotStarted',
    'Clock',
    'SystemClock',
    'Http/RequestContext',
    'Http/GlobalsRequest',
    'Http/OriginVerifier',
    'Cookie/CookieJar',
    'Cookie/CookieOptions',
    'Cookie/NativeCookieJar',
    'RateLimit/RateLimiter',
    'RateLimit/InMemoryRateLimiter',
    'RateLimit/StorageRateLimiter',
    'Storage/Storage',
    'Storage/AtomicStorage',
    'Storage/ArrayStorage',
    'Storage/FileStorage',
    'Storage/SessionStorage',
    'Secret',
    'Security/DoubleSubmitCookie',
    'Security/SecurityPolicy',
    'Token/TokenLifetime',
    'Token/DecodedToken',
    'Token/TokenCodec',
    'Token/TokenService',
    'CSRF',
];

foreach ($files as $file) {
    require __DIR__ . '/../src/' . $file . '.php';
}

require __DIR__ . '/Unit/ArrayCookieJar.php';
require __DIR__ . '/Unit/ArrayRequest.php';

final class FixedClock implements \CsrfWarden\Clock
{
    public function __construct(private int $now)
    {
    }

    public function now(): int
    {
        return $this->now;
    }

    public function advance(int $seconds): void
    {
        $this->now += $seconds;
    }

    public function rewind(int $seconds): void
    {
        $this->now -= $seconds;
    }
}

final class RacingStorage implements AtomicStorage
{
    /** @var array<string,string> */
    private array $values = [];

    public bool $addWasCalled = false;

    public function get(string $key): ?string
    {
        return $this->values[$key] ?? null;
    }

    public function set(string $key, string $value): void
    {
        $this->values[$key] = $value;
    }

    public function add(string $key, string $value): bool
    {
        $this->addWasCalled = true;
        $this->values[$key] = str_repeat('r', 32);

        return false;
    }

    public function remove(string $key): void
    {
        unset($this->values[$key]);
    }
}

$checks = 0;
$failures = 0;

function check(string $name, bool $ok): void
{
    global $checks, $failures;
    $checks++;
    $failures += $ok ? 0 : 1;
    echo ($ok ? '  ok    ' : '  FAIL  ') . $name . "\n";
}

$clock = new FixedClock(1_700_000_000);

echo "Tokens\n";
$csrf = new CSRF(new ArrayStorage(), TokenLifetime::seconds(7200), $clock);
$token = $csrf->token('login');

check('round trip validates', $csrf->check($token, 'login'));
check('token is url-safe base64, 96 chars', preg_match('/^[A-Za-z0-9_-]{96}$/', $token) === 1);
check('checking does not consume the token', $csrf->check($token, 'login') && $csrf->check($token, 'login'));
check('two tokens for the same scope differ', $csrf->token('login') !== $csrf->token('login'));
check('wrong scope rejected', !$csrf->check($token, 'signup'));
check('empty scope is its own scope', !$csrf->check($token, '') && $csrf->check($csrf->token(), ''));
check('null / int / array / empty rejected', !$csrf->check(null) && !$csrf->check(123) && !$csrf->check(['x']) && !$csrf->check(''));

$tamperRejected = true;
for ($index = 0; $index < strlen($token) - 1; $index++) {
    $replacement = $token[$index] === 'A' ? 'B' : 'A';
    $tampered = substr($token, 0, $index) . $replacement . substr($token, $index + 1);
    if ($csrf->check($tampered, 'login')) {
        $tamperRejected = false;
        break;
    }
}
check('every single-character tamper rejected', $tamperRejected);

$other = new CSRF(new ArrayStorage(), TokenLifetime::default(), $clock);
check('token from another session rejected', !$other->check($token, 'login'));

echo "\nExpiry\n";
$expiryClock = new FixedClock(1_700_000_000);
$expiring = new CSRF(new ArrayStorage(), TokenLifetime::seconds(100), $expiryClock);
$expiringToken = $expiring->token('f');

$expiryClock->advance(100);
check('valid at exactly ttl', $expiring->check($expiringToken, 'f'));
$expiryClock->advance(1);
check('expired after ttl', !$expiring->check($expiringToken, 'f'));

$expiryClock->rewind(101);
$neverExpires = new CSRF(new ArrayStorage(), TokenLifetime::neverExpires(), $expiryClock);
$neverToken = $neverExpires->token('f');
$expiryClock->advance(10 * 365 * 86400);
check('ttl=0 never expires', $neverExpires->check($neverToken, 'f'));

$threw = false;
try {
    new CSRF(new ArrayStorage(), TokenLifetime::seconds(-1), $clock);
} catch (InvalidLifetime $exception) {
    $threw = true;
}
check('negative ttl rejected', $threw);

$skewClock = new FixedClock(1_700_000_000);
$skew = new CSRF(new ArrayStorage(), TokenLifetime::default(), $skewClock);
$skewToken = $skew->token('f');
$skewClock->rewind(60);
check('small clock skew into the past tolerated', $skew->check($skewToken, 'f'));
$skewClock->rewind(1);
check('token from the far future rejected', !$skew->check($skewToken, 'f'));

echo "\nFingerprint and rotation\n";
$storage = new ArrayStorage();
$csrf = new CSRF($storage, TokenLifetime::default(), $clock);
$fingerprint = $csrf->fingerprint();
check('fingerprint is 32 hex chars', preg_match('/^[a-f0-9]{32}$/', $fingerprint) === 1);
check('fingerprint is stable', $fingerprint === $csrf->fingerprint());
$oldToken = $csrf->token('f');
$csrf->rotate();
check('rotate() invalidates old tokens', !$csrf->check($oldToken, 'f'));
check('fingerprint changes after rotate()', $csrf->fingerprint() !== $fingerprint);
check('new tokens work after rotate()', $csrf->check($csrf->token('f'), 'f'));

$storage = new ArrayStorage();
$storage->set('secret', 'too-short');
$csrf = new CSRF($storage, TokenLifetime::default(), $clock);
$token = $csrf->token('f');
check('corrupt stored secret is replaced', $csrf->check($token, 'f') && strlen((string) $storage->get('secret')) === 32);

echo "\nAtomic storage\n";
$arrayStorage = new ArrayStorage();
check('ArrayStorage::add is atomic', $arrayStorage->add('k', 'v1') && !$arrayStorage->add('k', 'v2') && $arrayStorage->get('k') === 'v1');

$racingStorage = new RacingStorage();
$secret = new Secret($racingStorage);
$secretValue = $secret->value();
check('atomic add race uses the stored secret', $racingStorage->addWasCalled && $secretValue === str_repeat('r', 32));

echo "\nHTML helpers\n";
$csrf = new CSRF(new ArrayStorage(), TokenLifetime::default(), $clock);
$field = $csrf->field('form');
check('field() renders a valid token', preg_match('/value="([A-Za-z0-9_-]+)"/', $field, $matches) === 1 && $csrf->check($matches[1], 'form'));
$metaTag = $csrf->metaTag('x');
check('metaTag() renders a valid token', preg_match('/content="([A-Za-z0-9_-]+)"/', $metaTag, $matches) === 1 && $csrf->check($matches[1], 'x'));

echo "\nDouble-submit cookies\n";
$cookieOptions = new CookieOptions('_csrf_binding', '/', null, false, true, 'Lax', 7200);
$cookies = new ArrayCookieJar();
$doubleSubmit = new DoubleSubmitCookie($cookies, $cookieOptions);
$binding = $doubleSubmit->binding();
check('binding is 256-bit hex', preg_match('/^[a-f0-9]{64}$/', $binding) === 1);
check('binding is stable', $binding === $doubleSubmit->binding());
check('binding matches itself', $doubleSubmit->matches($binding));
check('binding rejects a wrong value', !$doubleSubmit->matches(str_repeat('a', 64)));
$doubleSubmit->rotate();
check('rotate removes the binding cookie', !$doubleSubmit->hasCookie());

$cookies = new ArrayCookieJar();
$security = new SecurityPolicy(new DoubleSubmitCookie($cookies, $cookieOptions));
$csrf = new CSRF(new ArrayStorage(), TokenLifetime::default(), $clock, $security);
$field = $csrf->field('form');
preg_match('/name="_csrf_token" value="([A-Za-z0-9_-]+)"/', $field, $tokenMatch);
preg_match('/name="_csrf_binding" value="([a-f0-9]{64})"/', $field, $bindingMatch);
$postedToken = $tokenMatch[1] ?? '';
$postedBinding = $bindingMatch[1] ?? '';
$goodRequest = new ArrayRequest(
    'POST',
    [],
    [CSRF::FIELD_NAME => $postedToken, CSRF::BINDING_FIELD_NAME => $postedBinding],
    '127.0.0.1'
);
check('matching double-submit accepted', $csrf->checkRequest($goodRequest, 'form'));
$badRequest = new ArrayRequest(
    'POST',
    [],
    [CSRF::FIELD_NAME => $postedToken, CSRF::BINDING_FIELD_NAME => str_repeat('b', 64)],
    '127.0.0.1'
);
check('wrong double-submit rejected', !$csrf->checkRequest($badRequest, 'form'));
$cookies->clear();
$cookieLessRequest = new ArrayRequest(
    'POST',
    [],
    [CSRF::FIELD_NAME => $postedToken],
    '127.0.0.1'
);
check('cookie-less fallback uses the signed token', $csrf->checkRequest($cookieLessRequest, 'form'));

echo "\nRate limiting\n";
$rateClock = new FixedClock(1_700_000_000);
$limiter = new InMemoryRateLimiter($rateClock, 2, 60);
check('first two attempts allowed', $limiter->allow('key') && $limiter->allow('key'));
check('third attempt blocked', !$limiter->allow('key'));
$rateClock->advance(60);
check('next window allowed', $limiter->allow('key'));

$storageLimiter = new StorageRateLimiter(new ArrayStorage(), new FixedClock(1_700_000_000), 2, 60);
check('storage limiter first two allowed', $storageLimiter->allow('key') && $storageLimiter->allow('key'));
check('storage limiter third blocked', !$storageLimiter->allow('key'));

echo "\nOrigin checks\n";
$verifier = new OriginVerifier(['https://example.test']);
check('same-origin fetch metadata allowed', $verifier->isAllowed(new ArrayRequest('POST', ['Sec-Fetch-Site' => 'same-origin'])));
check('cross-site fetch metadata rejected', !$verifier->isAllowed(new ArrayRequest('POST', ['Sec-Fetch-Site' => 'cross-site'])));
check('matching Origin allowed', $verifier->isAllowed(new ArrayRequest('POST', ['Origin' => 'https://example.test'])));
check('matching Referer fallback allowed', $verifier->isAllowed(new ArrayRequest('POST', ['Referer' => 'https://example.test/form'])));
check('missing origin data rejected', !$verifier->isAllowed(new ArrayRequest('POST')));
check('safe method does not need origin data', $verifier->isAllowed(new ArrayRequest('GET')));

$cookies = new ArrayCookieJar();
$security = new SecurityPolicy(
    new DoubleSubmitCookie($cookies, $cookieOptions),
    null,
    new OriginVerifier(['https://example.test'])
);
$csrf = new CSRF(new ArrayStorage(), TokenLifetime::default(), $clock, $security);
$field = $csrf->field('form');
preg_match('/name="_csrf_token" value="([A-Za-z0-9_-]+)"/', $field, $tokenMatch);
preg_match('/name="_csrf_binding" value="([a-f0-9]{64})"/', $field, $bindingMatch);
$postedToken = $tokenMatch[1] ?? '';
$postedBinding = $bindingMatch[1] ?? '';
$sameOrigin = new ArrayRequest(
    'POST',
    ['Origin' => 'https://example.test'],
    [CSRF::FIELD_NAME => $postedToken, CSRF::BINDING_FIELD_NAME => $postedBinding],
    '127.0.0.1'
);
check('same-origin request with binding accepted', $csrf->checkRequest($sameOrigin, 'form'));
$crossSite = new ArrayRequest(
    'POST',
    ['Sec-Fetch-Site' => 'cross-site'],
    [CSRF::FIELD_NAME => $postedToken, CSRF::BINDING_FIELD_NAME => $postedBinding],
    '127.0.0.1'
);
check('cross-site request rejected even with valid token', !$csrf->checkRequest($crossSite, 'form'));
$threw = false;
try {
    $csrf->check($postedToken, 'form', $postedBinding);
} catch (MissingRequestContext $exception) {
    $threw = true;
}
check('origin verifier requires a request context', $threw);

echo "\nFileStorage\n";
$directory = sys_get_temp_dir() . '/csrf-warden-' . bin2hex(random_bytes(4));
$fileStorage = new FileStorage($directory);
check(
    'FileStorage add is atomic',
    $fileStorage->add('key', 'first')
        && !$fileStorage->add('key', 'second')
        && $fileStorage->get('key') === 'first'
);
$fileStorage->set('key', 'third');
check('FileStorage set overwrites', $fileStorage->get('key') === 'third');
$fileStorage->remove('key');
check('FileStorage remove', $fileStorage->get('key') === null);
foreach (glob($directory . '/*') ?: [] as $file) {
    @unlink($file);
}
@rmdir($directory);

echo "\nSessionStorage\n";
ini_set('session.use_cookies', '0');
ini_set('session.use_only_cookies', '0');
ini_set('session.cache_limiter', '');
session_save_path(sys_get_temp_dir());

$threw = false;
try {
    (new SessionStorage())->get('k');
} catch (SessionNotStarted $exception) {
    $threw = true;
}
check('refuses to run without an active session', $threw);

session_id('csrfwardentest' . getmypid());
session_start();
$sessionStorage = new SessionStorage('ns');
check('SessionStorage::add is atomic', $sessionStorage->add('k', 'v1') && !$sessionStorage->add('k', 'v2') && $sessionStorage->get('k') === 'v1');

$csrf = new CSRF($sessionStorage, TokenLifetime::default(), $clock);
$token = $csrf->token('f');
check('works on top of $_SESSION', $csrf->check($token, 'f') && isset($_SESSION['ns']['secret']));
check('secret is not exposed in the token', strpos($token, (string) $_SESSION['ns']['secret']) === false);

$secondInstance = new CSRF(new SessionStorage('ns'), TokenLifetime::default(), $clock);
check('a second instance on the same session accepts the token', $secondInstance->check($token, 'f'));
session_destroy();

ob_end_flush();

echo "\n" . ($checks - $failures) . "/$checks checks passed\n";
exit($failures === 0 ? 0 : 1);

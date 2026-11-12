<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use CsrfWarden\Http\GlobalsRequest;

$csrf = Example::csrf();
$request = new GlobalsRequest();
$message = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!$csrf->checkRequest($request, 'newsletter')) {
        http_response_code(403);
        $message = 'Rejected: invalid or missing CSRF token.';
    } else {
        $message = 'Subscribed ' . (string) ($_POST['email'] ?? '') . '.';
        $csrf->rotate();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>csrf-warden: form example</title>
</head>
<body>
    <h1>Newsletter</h1>
    <p><?= Example::escape($message) ?></p>

    <form method="post" action="">
        <?= $csrf->field('newsletter') ?>
        <label>Email <input type="email" name="email" required></label>
        <button type="submit">Subscribe</button>
    </form>

    <p>Session fingerprint: <code><?= Example::escape($csrf->fingerprint()) ?></code></p>
</body>
</html>

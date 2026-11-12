<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use CsrfWarden\Http\GlobalsRequest;

$csrf = Example::csrf();
$request = new GlobalsRequest();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    header('Content-Type: application/json');

    if (!$csrf->checkRequest($request, 'ajax')) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'invalid or missing CSRF token']);
        exit;
    }

    echo json_encode(['ok' => true, 'message' => 'AJAX request accepted']);
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>csrf-warden: AJAX example</title>
    <?= $csrf->metaTag('ajax') ?>
</head>
<body>
    <h1>AJAX</h1>
    <p>
        The button sends the token and binding in the
        <code>X-CSRF-TOKEN</code> and <code>X-CSRF-BINDING</code> headers.
    </p>
    <button id="send" type="button">Send AJAX POST</button>
    <pre id="out"></pre>

    <script>
        document.getElementById('send').addEventListener('click', async () => {
            const token = document.querySelector('meta[name="csrf-token"]').content;
            const binding = document.querySelector('meta[name="csrf-binding"]').content;

            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-CSRF-BINDING': binding,
                },
                body: JSON.stringify({ ping: true }),
            });

            document.getElementById('out').textContent =
                response.status + ' ' + await response.text();
        });
    </script>
</body>
</html>

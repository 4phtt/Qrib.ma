<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method Not Allowed');
}

try {
    verifyCsrf();
    $_SESSION = [];
    session_destroy();

    $cookie = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $cookie['path'],
        $cookie['domain'],
        $cookie['secure'],
        $cookie['httponly']
    );

    redirectTo('index.php');
} catch (CsrfValidationException $exception) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    exit($exception->getMessage());
}

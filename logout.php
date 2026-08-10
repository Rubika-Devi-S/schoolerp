<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$_SESSION = [];

if (ini_get('session.use_cookies')) {
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
}

session_destroy();

header('Location: ' . BASE_URL . 'login.php');
exit;
<?php

declare(strict_types=1);

if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(__DIR__));
}

if (!defined('BASE_URL')) {
    define('BASE_URL', '/git/schoolerp/');
}

/*
|--------------------------------------------------------------------------
| Start session
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_name('SCHOOL_ERP_SESSION');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => false, // Change to true on HTTPS hosting
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

/*
|--------------------------------------------------------------------------
| Load first available file
|--------------------------------------------------------------------------
*/

if (!function_exists('require_first_existing')) {
    function require_first_existing(
        array $paths,
        bool $required = true
    ): ?string {
        foreach ($paths as $path) {
            if (is_file($path)) {
                require_once $path;

                return $path;
            }
        }

        if ($required) {
            http_response_code(500);

            exit(
                '<h3>Required project file not found</h3>'
                . '<pre>'
                . htmlspecialchars(
                    implode(PHP_EOL, $paths),
                    ENT_QUOTES,
                    'UTF-8'
                )
                . '</pre>'
            );
        }

        return null;
    }
}

/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
*/

require_first_existing([
    __DIR__ . '/db.php',
    __DIR__ . '/db(21).php',
    PROJECT_ROOT . '/config/database.php',
    PROJECT_ROOT . '/config/db.php',
]);

/*
|--------------------------------------------------------------------------
| Common files
|--------------------------------------------------------------------------
*/

require_first_existing([
    __DIR__ . '/auth.php',
    __DIR__ . '/auth(13).php',
], false);

require_first_existing([
    __DIR__ . '/permissions.php',
], false);

require_once __DIR__ . '/layout_helpers.php';

/*
|--------------------------------------------------------------------------
| Common functions
|--------------------------------------------------------------------------
*/

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

if (!function_exists('is_logged_in')) {
    function is_logged_in(): bool
    {
        return !empty($_SESSION['user_id']);
    }
}

if (!function_exists('require_login')) {
    function require_login(): void
    {
        if (!is_logged_in()) {
            header('Location: ' . BASE_URL . 'login.php');
            exit;
        }
    }
}

if (!function_exists('csrfToken')) {
    function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(
                random_bytes(32)
            );
        }

        return $_SESSION['csrf_token'];
    }
}

/*
|--------------------------------------------------------------------------
| Page access guard
|--------------------------------------------------------------------------
*/

$currentPage = basename(
    parse_url(
        $_SERVER['SCRIPT_NAME'] ?? '',
        PHP_URL_PATH
    ) ?: ''
);

$publicPages = [
    'login.php',
    'forgot-password.php',
    'reset-password.php',
    'logout.php',
];

$isPublicPage = in_array(
    $currentPage,
    $publicPages,
    true
);

/*
| Never apply require_login() to login.php.
*/
if (!$isPublicPage) {
    require_login();
}
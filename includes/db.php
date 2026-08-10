<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';

$pdo = null;

if (APP_DEMO_MODE) {
    return;
}

$configFile = dirname(__DIR__) . '/config/database.local.php';
$config = [];

if (is_file($configFile)) {
    $loaded = require $configFile;
    if (is_array($loaded)) {
        $config = $loaded;
    }
}

$host = (string)($config['host'] ?? getenv('DB_HOST') ?: '127.0.0.1');
$port = (string)($config['port'] ?? getenv('DB_PORT') ?: '3306');
$name = (string)($config['name'] ?? getenv('DB_NAME') ?: 'school_erp');
$user = (string)($config['user'] ?? getenv('DB_USER') ?: 'root');
$pass = (string)($config['pass'] ?? getenv('DB_PASS') ?: '');

if (!extension_loaded('pdo_mysql')) {
    http_response_code(500);
    exit(
        'PDO MySQL is disabled. Enable php_pdo_mysql in WAMP and restart all services.'
    );
}

try {
    $serverPdo = new PDO(
        "mysql:host={$host};port={$port};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $check = $serverPdo->prepare(
        'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = :name'
    );
    $check->execute(['name' => $name]);

    if (!$check->fetchColumn()) {
        http_response_code(500);
        exit(
            'Database "' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') .
            '" is not installed. Import database/install_school_erp.sql in phpMyAdmin.'
        );
    }

    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]
    );

    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $GLOBALS['pdo'] = $pdo;
} catch (PDOException $e) {
    $pdo = null;
    error_log('Database connection failed: ' . $e->getMessage());

    $driverCode = (int)($e->errorInfo[1] ?? 0);
    $reason = match ($driverCode) {
        1045 => 'MySQL username or password is incorrect.',
        1049 => 'The configured database does not exist.',
        2002 => 'MySQL is not running or the host/port is incorrect.',
        default => 'Check the WAMP MySQL service and database configuration.',
    };

    http_response_code(500);
    exit('Database connection failed. ' . $reason);
}

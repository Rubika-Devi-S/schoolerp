<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function current_user(): array
{
    return [
        'id' => (int)($_SESSION['user_id'] ?? 0),
        'name' => (string)($_SESSION['name'] ?? 'John Admin'),
        'role_id' => (int)($_SESSION['role_id'] ?? 1),
        'role_name' => (string)($_SESSION['role_name'] ?? 'Super Administrator'),
        'tenant_id' => (int)($_SESSION['tenant_id'] ?? 1),
        'branch_id' => (int)($_SESSION['branch_id'] ?? 1),
    ];
}
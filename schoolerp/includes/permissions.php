<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

function has_permission(string $pageKey, string $action = 'view'): bool
{
    global $pdo;
    $user = current_user();

    if (APP_DEMO_MODE || !$pdo) {
        return true;
    }

    $columnMap = [
        'view'=>'can_view','open'=>'can_open','create'=>'can_create','edit'=>'can_edit',
        'delete'=>'can_delete','restore'=>'can_restore','approve'=>'can_approve','reject'=>'can_reject',
        'print'=>'can_print','export'=>'can_export','import'=>'can_import','assign'=>'can_assign',
        'publish'=>'can_publish','lock'=>'can_lock'
    ];
    $column = $columnMap[$action] ?? null;
    if (!$column) return false;

    $sql = "SELECT COALESCE(MAX(rp.$column),0)
            FROM role_page_permissions rp
            JOIN app_pages p ON p.id = rp.page_id AND p.is_active=1
            WHERE rp.role_id = :role_id AND p.page_key = :page_key";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['role_id'=>$user['role_id'],'page_key'=>$pageKey]);
    return (int)$stmt->fetchColumn() === 1;
}

function require_page_permission(string $pageKey): void
{
    require_login();
    if (!has_permission($pageKey, 'view')) {
        http_response_code(403);
        exit('Access denied.');
    }
}

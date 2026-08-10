<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

/*
 * This is a JSON API. Load the shared bootstrap without rendering a page.
 * The Super Admin check below is the authorization boundary for this module.
 */
$originalScriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
$_SERVER['SCRIPT_NAME'] = '/login.php';
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/school-sidebar-service.php';
require_once dirname(__DIR__) . '/includes/sidebar-icon-helper.php';
$_SERVER['SCRIPT_NAME'] = $originalScriptName;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function ssm_json(bool $success, string $message = '', array $data = [], int $status = 200): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code($status);
    echo json_encode(
        ['success' => $success, 'message' => $message, 'data' => $data],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function ssm_input(): array
{
    $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    if (str_contains($contentType, 'application/json')) {
        $decoded = json_decode((string)file_get_contents('php://input'), true);
        return is_array($decoded) ? $decoded : [];
    }

    return $_POST;
}

function ssm_table(PDO $pdo, string $table): bool
{
    if (function_exists('school_table_exists')) {
        return school_table_exists($pdo, $table);
    }

    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables '
        . 'WHERE table_schema = DATABASE() AND table_name = :table_name'
    );
    $statement->execute(['table_name' => $table]);
    return (int)$statement->fetchColumn() > 0;
}

function ssm_column(PDO $pdo, string $table, string $column): bool
{
    if (function_exists('school_column_exists')) {
        return school_column_exists($pdo, $table, $column);
    }

    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.columns '
        . 'WHERE table_schema = DATABASE() '
        . 'AND table_name = :table_name AND column_name = :column_name'
    );
    $statement->execute([
        'table_name' => $table,
        'column_name' => $column,
    ]);
    return (int)$statement->fetchColumn() > 0;
}

function ssm_super_admin(PDO $pdo): void
{
    /*
     * Do not rely only on $_SESSION['role_id']. Some project login flows keep
     * the canonical role on current_user()/users.role_id while the page itself
     * is still correctly recognized as Super Administrator. The old API used
     * only the session keys, which could make the page load but leave its AJAX
     * request at "Loading the complete sidebar catalogue...".
     */
    $current = function_exists('current_user') ? current_user() : [];
    if (!is_array($current)) {
        $current = [];
    }

    $userId = (int)(
        $current['id']
        ?? $current['user_id']
        ?? $_SESSION['user_id']
        ?? 0
    );
    $roleId = (int)(
        $current['role_id']
        ?? $_SESSION['role_id']
        ?? 0
    );
    $roleKey = strtolower(trim((string)(
        $current['role_key']
        ?? $_SESSION['role_key']
        ?? ''
    )));

    if ($userId <= 0) {
        ssm_json(false, 'An active Super Admin login is required.', [], 401);
    }

    if (function_exists('is_super_admin') && is_super_admin()) {
        return;
    }

    /* Recover the role from the authenticated user when the session role is
       not populated by the current login flow. */
    if ($roleId <= 0 && ssm_table($pdo, 'users')) {
        try {
            $userRole = $pdo->prepare(
                'SELECT role_id FROM users WHERE id = :user_id LIMIT 1'
            );
            $userRole->execute(['user_id' => $userId]);
            $roleId = (int)($userRole->fetchColumn() ?: 0);
        } catch (Throwable $exception) {
            error_log('school sidebar super admin user role: ' . $exception->getMessage());
        }
    }

    if ($roleKey === '' && $roleId > 0 && ssm_table($pdo, 'roles')) {
        $statement = $pdo->prepare(
            "SELECT role_key
             FROM roles
             WHERE id = :role_id
               AND status = 'active'
             LIMIT 1"
        );
        $statement->execute(['role_id' => $roleId]);
        $roleKey = strtolower(trim((string)$statement->fetchColumn()));
    }

    if (!in_array(
        $roleKey,
        ['super_admin', 'super-administrator', 'super_administrator'],
        true
    )) {
        ssm_json(false, 'Only a Super Administrator can manage school sidebars.', [], 403);
    }
}

function ssm_csrf(array $input): void
{
    $token = trim((string)($input['csrf_token'] ?? ''));
    $valid = function_exists('csrf_is_valid')
        ? csrf_is_valid($token)
        : isset($_SESSION['csrf_token'])
            && hash_equals((string)$_SESSION['csrf_token'], $token);

    if (!$valid) {
        ssm_json(false, 'Invalid or expired CSRF token.', [], 419);
    }
}

function ssm_school(PDO $pdo, int $schoolId): array
{
    $statement = $pdo->prepare(
        "SELECT id, tenant_code, school_name
         FROM tenants
         WHERE id = :school_id
           AND status IN ('trial', 'active')
         LIMIT 1"
    );
    $statement->execute(['school_id' => $schoolId]);
    $school = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$school) {
        ssm_json(false, 'Selected school is unavailable.', [], 422);
    }

    return $school;
}

function ssm_role(PDO $pdo, int $schoolId, int $roleId): array
{
    $scopeFilter = ssm_column($pdo, 'roles', 'role_scope')
        ? " AND role_scope = 'school'"
        : '';
    $deletedFilter = ssm_column($pdo, 'roles', 'deleted_at')
        ? ' AND deleted_at IS NULL'
        : '';

    $statement = $pdo->prepare(
        "SELECT id, role_key, role_name
         FROM roles
         WHERE id = :role_id
           AND tenant_id = :school_id
           AND status = 'active'
           {$scopeFilter}
           {$deletedFilter}
         LIMIT 1"
    );
    $statement->execute([
        'role_id' => $roleId,
        'school_id' => $schoolId,
    ]);
    $role = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$role) {
        ssm_json(false, 'Selected role does not belong to the selected school.', [], 422);
    }

    return $role;
}

function ssm_key(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';
    return substr(trim($value, '_'), 0, 100);
}

function ssm_action_key(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';
    return substr(trim($value, '_'), 0, 50);
}

function ssm_valid_icon(string $icon, string $fallback = 'circle'): string
{
    return school_sidebar_icon_normalize($icon, $fallback);
}

function ssm_ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS permission_actions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            action_key VARCHAR(50) NOT NULL,
            action_name VARCHAR(80) NOT NULL,
            display_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_permission_action (action_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $standardActions = [
        ['view', 'View', 10],
        ['create', 'Add', 20],
        ['edit', 'Edit', 30],
        ['delete', 'Delete', 40],
        ['approve', 'Approve', 50],
        ['reject', 'Reject', 60],
        ['print', 'Print', 70],
        ['pdf', 'PDF', 80],
        ['export', 'Export', 90],
        ['import', 'Import', 100],
        ['restore', 'Restore', 110],
        ['manage_settings', 'Manage Settings', 120],
        ['manage_visibility', 'Manage Visibility', 130],
        ['full_access', 'Full Access', 999],
    ];

    $actionUpsert = $pdo->prepare(
        "INSERT INTO permission_actions
            (action_key, action_name, display_order, is_active)
         VALUES
            (:action_key, :action_name, :display_order, 1)
         ON DUPLICATE KEY UPDATE
            action_name = CASE
                WHEN action_name = '' THEN VALUES(action_name)
                ELSE action_name
            END,
            is_active = 1"
    );
    foreach ($standardActions as [$key, $name, $order]) {
        $actionUpsert->execute([
            'action_key' => $key,
            'action_name' => $name,
            'display_order' => $order,
        ]);
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS school_sidebar_permission_grants (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id BIGINT UNSIGNED NOT NULL,
            role_id BIGINT UNSIGNED NOT NULL,
            sidebar_item_id BIGINT UNSIGNED NOT NULL,
            action_key VARCHAR(50) NOT NULL,
            is_allowed TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_school_sidebar_grant
                (tenant_id, role_id, sidebar_item_id, action_key),
            KEY idx_school_sidebar_grant_role (tenant_id, role_id),
            KEY idx_school_sidebar_grant_item (sidebar_item_id),
            KEY idx_school_sidebar_grant_action (action_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS sidebar_default_settings (
            sidebar_item_id BIGINT UNSIGNED NOT NULL,
            is_enabled TINYINT(1) NOT NULL DEFAULT 1,
            display_order INT NULL DEFAULT NULL,
            updated_by BIGINT UNSIGNED NULL DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (sidebar_item_id),
            KEY idx_sidebar_default_enabled (is_enabled, display_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    if (ssm_table($pdo, 'tenant_sidebar_items')
        && !ssm_column($pdo, 'tenant_sidebar_items', 'inherit_default')) {
        $pdo->exec(
            "ALTER TABLE tenant_sidebar_items
             ADD COLUMN inherit_default TINYINT(1) NOT NULL DEFAULT 0
             AFTER is_visible"
        );

        $oldTriggerCount = (int)$pdo->query(
            "SELECT COUNT(*) FROM information_schema.triggers
             WHERE trigger_schema = DATABASE()
               AND trigger_name IN (
                    'trg_sidebar_item_auto_assign_insert',
                    'trg_sidebar_item_auto_assign_update',
                    'trg_tenant_sidebar_catalog_insert',
                    'trg_tenant_sidebar_catalog_update'
               )"
        )->fetchColumn();

        if ($oldTriggerCount > 0) {
            $permissionJoin = ssm_table($pdo, 'school_sidebar_action_permissions')
                ? "LEFT JOIN school_sidebar_action_permissions sap
                     ON sap.tenant_id=tsi.tenant_id
                    AND sap.sidebar_item_id=tsi.sidebar_item_id"
                : '';
            $grantJoin = ssm_table($pdo, 'school_sidebar_permission_grants')
                ? "LEFT JOIN school_sidebar_permission_grants spg
                     ON spg.tenant_id=tsi.tenant_id
                    AND spg.sidebar_item_id=tsi.sidebar_item_id"
                : '';
            $permissionEmpty = ssm_table($pdo, 'school_sidebar_action_permissions')
                ? 'AND sap.id IS NULL' : '';
            $grantEmpty = ssm_table($pdo, 'school_sidebar_permission_grants')
                ? 'AND spg.id IS NULL' : '';

            $pdo->exec(
                "UPDATE tenant_sidebar_items tsi
                 INNER JOIN sidebar_items si ON si.id=tsi.sidebar_item_id
                 {$permissionJoin}
                 {$grantJoin}
                 SET tsi.inherit_default=1
                 WHERE tsi.is_visible=0
                   AND NULLIF(TRIM(COALESCE(tsi.custom_title,'')),'') IS NULL
                   AND NULLIF(TRIM(COALESCE(tsi.custom_icon,'')),'') IS NULL
                   AND NULLIF(TRIM(COALESCE(tsi.custom_route,'')),'') IS NULL
                   AND tsi.custom_parent_id IS NULL
                   AND (tsi.display_order IS NULL OR tsi.display_order=si.display_order)
                   {$permissionEmpty}
                   {$grantEmpty}"
            );
        }

        $pdo->exec(
            "ALTER TABLE tenant_sidebar_items
             MODIFY inherit_default TINYINT(1) NOT NULL DEFAULT 1"
        );
    }

    if (!ssm_table($pdo, 'school_sidebar_action_permissions')) {
        $pdo->exec(
            "CREATE TABLE school_sidebar_action_permissions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id BIGINT UNSIGNED NOT NULL,
                role_id BIGINT UNSIGNED NOT NULL,
                sidebar_item_id BIGINT UNSIGNED NOT NULL,
                can_view TINYINT(1) NOT NULL DEFAULT 0,
                can_add TINYINT(1) NOT NULL DEFAULT 0,
                can_edit TINYINT(1) NOT NULL DEFAULT 0,
                can_delete TINYINT(1) NOT NULL DEFAULT 0,
                can_print TINYINT(1) NOT NULL DEFAULT 0,
                can_pdf TINYINT(1) NOT NULL DEFAULT 0,
                can_export TINYINT(1) NOT NULL DEFAULT 0,
                can_import TINYINT(1) NOT NULL DEFAULT 0,
                can_approve TINYINT(1) NOT NULL DEFAULT 0,
                can_reject TINYINT(1) NOT NULL DEFAULT 0,
                can_restore TINYINT(1) NOT NULL DEFAULT 0,
                can_manage TINYINT(1) NOT NULL DEFAULT 0,
                can_manage_visibility TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_school_sidebar_action
                    (tenant_id,role_id,sidebar_item_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (ssm_table($pdo, 'school_sidebar_action_permissions')) {
        $extendedColumns = [
            'can_print',
            'can_export',
            'can_import',
            'can_approve',
            'can_reject',
            'can_restore',
            'can_manage',
            'can_manage_visibility',
        ];

        foreach ($extendedColumns as $column) {
            if (!ssm_column($pdo, 'school_sidebar_action_permissions', $column)) {
                $pdo->exec(
                    "ALTER TABLE school_sidebar_action_permissions
                     ADD COLUMN {$column} TINYINT(1) NOT NULL DEFAULT 0
                     AFTER can_delete"
                );
            }
        }

        if (!ssm_column($pdo, 'school_sidebar_action_permissions', 'can_pdf')) {
            $pdo->exec(
                "ALTER TABLE school_sidebar_action_permissions
                 ADD COLUMN can_pdf TINYINT(1) NOT NULL DEFAULT 0
                 AFTER can_print"
            );

            if (ssm_column($pdo, 'school_sidebar_action_permissions', 'can_export')) {
                $pdo->exec(
                    "UPDATE school_sidebar_action_permissions
                     SET can_pdf = can_export"
                );
            }
        }

        $migrations = [
            'view' => 'can_view',
            'create' => 'can_add',
            'edit' => 'can_edit',
            'delete' => 'can_delete',
            'print' => 'can_print',
            'pdf' => 'can_pdf',
            'export' => 'can_export',
            'import' => 'can_import',
            'approve' => 'can_approve',
            'reject' => 'can_reject',
            'restore' => 'can_restore',
            'manage_settings' => 'can_manage',
            'manage_visibility' => 'can_manage_visibility',
        ];
        foreach ($migrations as $actionKey => $column) {
            if (!ssm_column($pdo, 'school_sidebar_action_permissions', $column)) {
                continue;
            }
            $pdo->exec(
                "INSERT IGNORE INTO school_sidebar_permission_grants
                    (tenant_id,role_id,sidebar_item_id,action_key,is_allowed)
                 SELECT tenant_id,role_id,sidebar_item_id,"
                 . $pdo->quote($actionKey) . ",{$column}
                 FROM school_sidebar_action_permissions"
            );
        }
    }
}

function ssm_actions(PDO $pdo): array
{
    $statement = $pdo->query(
        "SELECT action_key, action_name, display_order
         FROM permission_actions
         WHERE is_active = 1
         ORDER BY display_order, id"
    );

    $actions = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $key = ssm_action_key((string)($row['action_key'] ?? ''));
        if ($key === '') {
            continue;
        }

        $name = trim((string)($row['action_name'] ?? ''));
        if ($key === 'create' && strtolower($name) === 'create') {
            $name = 'Add';
        }

        $actions[] = [
            'action_key' => $key,
            'action_name' => $name !== '' ? $name : ucwords(str_replace('_', ' ', $key)),
            'display_order' => (int)($row['display_order'] ?? 0),
        ];
    }

    return $actions;
}

function ssm_sync_school_catalog(PDO $pdo, int $schoolId): void
{
    /* Missing school rows intentionally inherit the Default Sidebar. */
}

function ssm_assign_item_to_schools(
    PDO $pdo,
    int $itemId,
    ?int $ownerTenantId,
    int $displayOrder,
    int $visible = 1
): void {
    if ($ownerTenantId === null || $ownerTenantId <= 0) {
        return;
    }

    $hasInheritance = ssm_column($pdo, 'tenant_sidebar_items', 'inherit_default');
    $statement = $pdo->prepare(
        "INSERT INTO tenant_sidebar_items
            (tenant_id,sidebar_item_id,display_order,is_visible"
            . ($hasInheritance ? ',inherit_default' : '') . ")
         VALUES
            (:tenant_id,:sidebar_item_id,:display_order,:is_visible"
            . ($hasInheritance ? ',0' : '') . ")
         ON DUPLICATE KEY UPDATE
            display_order=VALUES(display_order),
            is_visible=VALUES(is_visible),"
            . ($hasInheritance ? ' inherit_default=0,' : '') . "
            updated_at=CURRENT_TIMESTAMP"
    );
    $statement->execute([
        'tenant_id' => $ownerTenantId,
        'sidebar_item_id' => $itemId,
        'display_order' => $displayOrder,
        'is_visible' => $visible,
    ]);
}

function ssm_item_query(PDO $pdo, int $schoolId = 0, int $roleId = 0): array
{
    $selected=$schoolId>0;
    $hasDefault=ssm_table($pdo,'sidebar_default_settings');
    $defaultJoin=$hasDefault
        ? 'LEFT JOIN sidebar_default_settings sds ON sds.sidebar_item_id=si.id'
        : '';

    if (!$selected) {
        $sql="SELECT si.id AS sidebar_item_id,si.parent_id,
                    si.parent_id AS default_parent_id,si.menu_key,si.menu_title,
                    si.route,si.route AS default_route,si.icon,
                    si.display_order AS master_order,
                    ".($hasDefault?'COALESCE(sds.display_order,si.display_order)':'si.display_order')." AS default_order,
                    ".($hasDefault?'COALESCE(sds.is_enabled,1)':'1')." AS default_enabled,
                    NULL AS custom_title,NULL AS custom_icon,
                    ".($hasDefault?'COALESCE(sds.display_order,si.display_order)':'si.display_order')." AS display_order,
                    ".($hasDefault?'COALESCE(sds.is_enabled,1)':'1')." AS is_visible,
                    0 AS is_inherited,NULL AS owner_tenant_id
             FROM sidebar_items si {$defaultJoin}
             WHERE si.is_active=1 AND si.show_in_sidebar=1
               AND si.portal_scope IN ('school','all')
               AND si.owner_tenant_id IS NULL
             ORDER BY COALESCE(si.parent_id,0),display_order,si.id";
        $items=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $inherit=ssm_column($pdo,'tenant_sidebar_items','inherit_default')
            ? 'AND COALESCE(tsi.inherit_default,0)=0' : '';
        $sql="SELECT si.id AS sidebar_item_id,
                    COALESCE(tsi.custom_parent_id,si.parent_id) AS parent_id,
                    si.parent_id AS default_parent_id,si.menu_key,si.menu_title,
                    COALESCE(NULLIF(tsi.custom_route,''),si.route) AS route,
                    si.route AS default_route,si.icon,
                    si.display_order AS master_order,
                    ".($hasDefault?'COALESCE(sds.display_order,si.display_order)':'si.display_order')." AS default_order,
                    ".($hasDefault?'COALESCE(sds.is_enabled,1)':'1')." AS default_enabled,
                    tsi.custom_title,tsi.custom_icon,
                    COALESCE(tsi.display_order,si.display_order) AS display_order,
                    1 AS is_visible,0 AS is_inherited,si.owner_tenant_id
             FROM tenant_sidebar_items tsi
             INNER JOIN sidebar_items si ON si.id=tsi.sidebar_item_id
             {$defaultJoin}
             WHERE tsi.tenant_id=:tenant_id AND tsi.is_visible=1 {$inherit}
               AND si.is_active=1 AND si.show_in_sidebar=1
               AND si.portal_scope IN ('school','all')
               AND (si.owner_tenant_id IS NULL OR si.owner_tenant_id=:owner_id)
             ORDER BY COALESCE(tsi.custom_parent_id,si.parent_id,0),
                      COALESCE(tsi.display_order,si.display_order),si.id";
        $stmt=$pdo->prepare($sql);
        $stmt->execute(['tenant_id'=>$schoolId,'owner_id'=>$schoolId]);
        $items=$stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    foreach ($items as &$item) {
        $item['permissions']=[];
        $item['is_inherited']=0;
        $item['default_enabled']=(int)($item['default_enabled']??1);
    }
    unset($item);
    if (!$selected || $roleId<=0 || $items===[]) return $items;

    $ids=array_values(array_unique(array_map(
        static fn(array $row):int=>(int)$row['sidebar_item_id'],$items
    )));
    $marks=implode(',',array_fill(0,count($ids),'?'));
    $stmt=$pdo->prepare(
        "SELECT sidebar_item_id,action_key,MAX(is_allowed) is_allowed
         FROM school_sidebar_permission_grants
         WHERE tenant_id=? AND role_id=? AND sidebar_item_id IN ({$marks})
         GROUP BY sidebar_item_id,action_key"
    );
    $stmt->execute([$schoolId,$roleId,...$ids]);
    $grants=[];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $g) {
        $grants[(int)$g['sidebar_item_id']][ssm_action_key((string)$g['action_key'])]
            =(int)$g['is_allowed'];
    }
    foreach ($items as &$item) $item['permissions']=$grants[(int)$item['sidebar_item_id']]??[];
    unset($item);
    return $items;
}

function ssm_normalize_permission_map(array $raw, array $actionKeys): array
{
    $permissions = array_fill_keys($actionKeys, 0);

    foreach ($raw as $key => $value) {
        if (is_int($key) && is_array($value)) {
            $actionKey = ssm_action_key((string)($value['action_key'] ?? ''));
            $allowed = !empty($value['is_allowed']) ? 1 : 0;
        } else {
            $actionKey = ssm_action_key((string)$key);
            $allowed = !empty($value) ? 1 : 0;
        }

        if ($actionKey !== '' && array_key_exists($actionKey, $permissions)) {
            $permissions[$actionKey] = $allowed;
        }
    }

    if (($permissions['full_access'] ?? 0) === 1) {
        foreach ($permissions as $key => $value) {
            $permissions[$key] = 1;
        }
        return $permissions;
    }

    $hasOperationalPermission = false;
    foreach ($permissions as $key => $allowed) {
        if (!in_array($key, ['view', 'full_access'], true) && $allowed === 1) {
            $hasOperationalPermission = true;
            break;
        }
    }

    if ($hasOperationalPermission && array_key_exists('view', $permissions)) {
        $permissions['view'] = 1;
    }

    if (($permissions['view'] ?? 0) !== 1) {
        foreach ($permissions as $key => $value) {
            $permissions[$key] = 0;
        }
    }

    return $permissions;
}

function ssm_save_grants(
    PDO $pdo,
    int $schoolId,
    int $roleId,
    int $itemId,
    array $permissions,
    array $actionKeys
): void {
    $delete = $pdo->prepare(
        "DELETE FROM school_sidebar_permission_grants
         WHERE tenant_id = :tenant_id
           AND role_id = :role_id
           AND sidebar_item_id = :sidebar_item_id"
    );
    $delete->execute([
        'tenant_id' => $schoolId,
        'role_id' => $roleId,
        'sidebar_item_id' => $itemId,
    ]);

    $insert = $pdo->prepare(
        "INSERT INTO school_sidebar_permission_grants
            (tenant_id, role_id, sidebar_item_id, action_key, is_allowed)
         VALUES
            (:tenant_id, :role_id, :sidebar_item_id, :action_key, :is_allowed)"
    );

    foreach ($actionKeys as $actionKey) {
        $insert->execute([
            'tenant_id' => $schoolId,
            'role_id' => $roleId,
            'sidebar_item_id' => $itemId,
            'action_key' => $actionKey,
            'is_allowed' => (int)($permissions[$actionKey] ?? 0),
        ]);
    }

    if (!ssm_table($pdo, 'school_sidebar_action_permissions')) {
        return;
    }

    $legacy = $pdo->prepare(
        "INSERT INTO school_sidebar_action_permissions
            (
                tenant_id, role_id, sidebar_item_id,
                can_view, can_add, can_edit, can_delete,
                can_print, can_pdf, can_export, can_import,
                can_approve, can_reject, can_restore, can_manage,
                can_manage_visibility
            )
         VALUES
            (
                :tenant_id, :role_id, :sidebar_item_id,
                :can_view, :can_add, :can_edit, :can_delete,
                :can_print, :can_pdf, :can_export, :can_import,
                :can_approve, :can_reject, :can_restore, :can_manage,
                :can_manage_visibility
            )
         ON DUPLICATE KEY UPDATE
            can_view = VALUES(can_view),
            can_add = VALUES(can_add),
            can_edit = VALUES(can_edit),
            can_delete = VALUES(can_delete),
            can_print = VALUES(can_print),
            can_pdf = VALUES(can_pdf),
            can_export = VALUES(can_export),
            can_import = VALUES(can_import),
            can_approve = VALUES(can_approve),
            can_reject = VALUES(can_reject),
            can_restore = VALUES(can_restore),
            can_manage = VALUES(can_manage),
            can_manage_visibility = VALUES(can_manage_visibility),
            updated_at = CURRENT_TIMESTAMP"
    );

    $legacy->execute([
        'tenant_id' => $schoolId,
        'role_id' => $roleId,
        'sidebar_item_id' => $itemId,
        'can_view' => (int)($permissions['view'] ?? 0),
        'can_add' => (int)($permissions['create'] ?? $permissions['add'] ?? 0),
        'can_edit' => (int)($permissions['edit'] ?? 0),
        'can_delete' => (int)($permissions['delete'] ?? 0),
        'can_print' => (int)($permissions['print'] ?? 0),
        'can_pdf' => (int)($permissions['pdf'] ?? 0),
        'can_export' => (int)($permissions['export'] ?? 0),
        'can_import' => (int)($permissions['import'] ?? 0),
        'can_approve' => (int)($permissions['approve'] ?? 0),
        'can_reject' => (int)($permissions['reject'] ?? 0),
        'can_restore' => (int)($permissions['restore'] ?? 0),
        'can_manage' => (int)($permissions['manage_settings'] ?? 0),
        'can_manage_visibility' =>
            (int)($permissions['manage_visibility'] ?? 0),
    ]);
}

function ssm_log(
    PDO $pdo,
    int $schoolId,
    int $roleId,
    string $action,
    int $recordId,
    string $description,
    array $values = []
): void {
    if ($schoolId <= 0 || !ssm_table($pdo, 'activity_logs')) {
        return;
    }

    try {
        $columns = [
            'tenant_id', 'branch_id', 'user_id', 'role_id', 'module_name',
            'action_key', 'table_name', 'record_id', 'description',
            'ip_address', 'user_agent',
        ];
        $params = [
            'tenant_id' => $schoolId,
            'branch_id' => null,
            'user_id' => (int)($_SESSION['user_id'] ?? 0) ?: null,
            'role_id' => $roleId ?: null,
            'module_name' => 'School Sidebar Management',
            'action_key' => $action,
            'table_name' => 'sidebar_items',
            'record_id' => $recordId ?: null,
            'description' => substr($description, 0, 500),
            'ip_address' => substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
            'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1000) ?: null,
        ];

        if (ssm_column($pdo, 'activity_logs', 'new_values')) {
            $columns[] = 'new_values';
            $params['new_values'] = json_encode(
                $values,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        }

        $placeholders = array_map(static fn(string $column): string => ':' . $column, $columns);
        $statement = $pdo->prepare(
            'INSERT INTO activity_logs (`' . implode('`,`', $columns) . '`) '
            . 'VALUES (' . implode(',', $placeholders) . ')'
        );
        $statement->execute($params);
    } catch (Throwable $exception) {
        error_log('school sidebar activity log: ' . $exception->getMessage());
    }
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    ssm_json(false, 'Database connection unavailable.', [], 500);
}

ssm_super_admin($pdo);
$input = ssm_input();
$action = strtolower(trim((string)($input['action'] ?? $_GET['action'] ?? '')));

try {
    foreach (['sidebar_items', 'tenant_sidebar_items', 'tenants', 'roles'] as $requiredTable) {
        if (!ssm_table($pdo, $requiredTable)) {
            ssm_json(false, 'Missing database table: ' . $requiredTable . '.', [], 500);
        }
    }

    ssm_ensure_schema($pdo);
    school_sidebar_service_ensure($pdo);
    $actions = ssm_actions($pdo);
    $actionKeys = array_values(array_unique(array_column($actions, 'action_key')));

    if ($action === 'meta') {
        /* Master mode never requires school_id or role_id. */
        $schools = $pdo->query(
            "SELECT id, tenant_code, school_name
             FROM tenants
             WHERE status IN ('trial', 'active')
             ORDER BY id, school_name"
        )->fetchAll(PDO::FETCH_ASSOC);

        $masterItems = ssm_item_query($pdo, 0, 0);

        ssm_json(true, 'Sidebar catalogue loaded.', [
            'csrf_token' => function_exists('csrfToken') ? csrfToken() : '',
            'schools' => $schools,
            'actions' => $actions,
            'items' => $masterItems,
            'mode' => 'master',
            'summary' => [
                'school_count' => count($schools),
                'menu_count' => count($masterItems),
            ],
        ]);
    }

    $schoolId = (int)($input['school_id'] ?? $_GET['school_id'] ?? 0);

    if ($action === 'roles') {
        if ($schoolId <= 0) {
            ssm_json(false, 'Select a school.', [], 422);
        }
        ssm_school($pdo, $schoolId);
        ssm_sync_school_catalog($pdo, $schoolId);

        $scopeFilter = ssm_column($pdo, 'roles', 'role_scope')
            ? " AND role_scope = 'school'"
            : '';
        $deletedFilter = ssm_column($pdo, 'roles', 'deleted_at')
            ? ' AND deleted_at IS NULL'
            : '';

        $statement = $pdo->prepare(
            "SELECT id, role_key, role_name
             FROM roles
             WHERE tenant_id = :school_id
               AND status = 'active'
               {$scopeFilter}
               {$deletedFilter}
             ORDER BY
                CASE WHEN role_key IN ('school_admin','school_administrator','school-administrator')
                     THEN 0 ELSE 1 END,
                role_name"
        );
        $statement->execute(['school_id' => $schoolId]);

        ssm_json(true, 'School roles loaded.', [
            'roles' => $statement->fetchAll(PDO::FETCH_ASSOC),
            'actions' => $actions,
        ]);
    }

    if ($action === 'items') {
        $roleId = (int)($input['role_id'] ?? $_GET['role_id'] ?? 0);
        if ($schoolId <= 0 || $roleId <= 0) {
            ssm_json(false, 'Select a school and role.', [], 422);
        }

        $school = ssm_school($pdo, $schoolId);
        $role = ssm_role($pdo, $schoolId, $roleId);
        ssm_sync_school_catalog($pdo, $schoolId);

        $schoolItems = ssm_item_query($pdo, $schoolId, $roleId);
        $schoolAdminRole = in_array(
            strtolower(trim((string)($role['role_key'] ?? ''))),
            ['school_admin','school-administrator','school_administrator'],
            true
        );

        /*
         * Backward-compatible default: a School Administrator with no stored
         * action rows for a menu starts with full access. As soon as Super Admin
         * saves that menu, the explicit grant rows become authoritative and can
         * contain both allows and denies. The UI is never locked.
         */
        if ($schoolAdminRole) {
            foreach ($schoolItems as &$schoolItem) {
                if (($schoolItem['permissions'] ?? []) === []) {
                    $schoolItem['permissions'] = array_fill_keys($actionKeys, 1);
                }
            }
            unset($schoolItem);
        }

        ssm_json(true, 'School sidebar permissions loaded.', [
            'school' => $school,
            'role' => $role,
            'actions' => $actions,
            'items' => $schoolItems,
            'mode' => 'school',
            'protected_role' => false,
            'school_admin_default_full_access' => $schoolAdminRole,
        ]);
    }

    ssm_csrf($input);

    if ($action === 'save_default') {
        $submittedItems = $input['items'] ?? [];
        if (!is_array($submittedItems)) {
            ssm_json(false, 'Invalid Default Sidebar data.', [], 422);
        }

        $masterItems = ssm_item_query($pdo, 0, 0);
        $masterMap = [];
        foreach ($masterItems as $masterItem) {
            $masterMap[(int)$masterItem['sidebar_item_id']] = $masterItem;
        }

        $normalized = [];
        foreach ($submittedItems as $item) {
            if (!is_array($item)) continue;
            $itemId = (int)($item['sidebar_item_id'] ?? 0);
            if ($itemId <= 0 || !isset($masterMap[$itemId])) continue;

            $normalized[$itemId] = [
                'sidebar_item_id' => $itemId,
                'parent_id' => (int)($masterMap[$itemId]['parent_id'] ?? 0),
                'is_enabled' => !empty($item['is_visible']) ? 1 : 0,
                'display_order' => max(
                    0,
                    min(9999, (int)($item['display_order']
                        ?? $masterMap[$itemId]['default_order']
                        ?? 0))
                ),
            ];
        }

        /*
         * A default-visible child always requires its default parent chain.
         * This prevents an impossible configuration where a child is enabled
         * but can never render because its parent is disabled.
         */
        foreach ($normalized as $itemId => $item) {
            if ($item['is_enabled'] !== 1) continue;
            $parentId = (int)$item['parent_id'];
            $visited = [];
            while ($parentId > 0 && !isset($visited[$parentId])) {
                $visited[$parentId] = true;
                if (!isset($normalized[$parentId])) break;
                $normalized[$parentId]['is_enabled'] = 1;
                $parentId = (int)$normalized[$parentId]['parent_id'];
            }
        }

        $upsert = $pdo->prepare(
            "INSERT INTO sidebar_default_settings
                (sidebar_item_id,is_enabled,display_order,updated_by)
             VALUES
                (:sidebar_item_id,:is_enabled,:display_order,:updated_by)
             ON DUPLICATE KEY UPDATE
                is_enabled=VALUES(is_enabled),
                display_order=VALUES(display_order),
                updated_by=VALUES(updated_by),
                updated_at=CURRENT_TIMESTAMP"
        );

        $pdo->beginTransaction();
        foreach ($normalized as $item) {
            $upsert->execute([
                'sidebar_item_id' => $item['sidebar_item_id'],
                'is_enabled' => $item['is_enabled'],
                'display_order' => $item['display_order'],
                'updated_by' => (int)($_SESSION['user_id'] ?? 0) ?: null,
            ]);
        }
        school_sidebar_service_bump_version($pdo, null);
        $pdo->commit();

        ssm_json(true, 'Default Sidebar configuration saved successfully.');
    }

    if ($action === 'reset_school_item') {
        if ($schoolId<=0) ssm_json(false,'Select a school.',[],422);
        $itemId=(int)($input['sidebar_item_id']??0);
        if ($itemId<=0) ssm_json(false,'Invalid sidebar item.',[],422);
        ssm_school($pdo,$schoolId);
        $pdo->beginTransaction();
        $ids=school_sidebar_service_school_descendants($pdo,$schoolId,$itemId);
        if ($ids===[]) $ids=[$itemId];
        school_sidebar_service_remove_school_items($pdo,$schoolId,$ids);
        school_sidebar_service_bump_version($pdo,$schoolId);
        $pdo->commit();
        ssm_json(true,'Sidebar item and assigned submenus removed from this school.');
    }

    if ($action === 'save') {
        $roleId=(int)($input['role_id']??0);
        if ($schoolId<=0 || $roleId<=0) ssm_json(false,'Select a school and role.',[],422);
        ssm_school($pdo,$schoolId); ssm_role($pdo,$schoolId,$roleId);
        $submitted=$input['items']??[];
        if (!is_array($submitted)) ssm_json(false,'Invalid sidebar item data.',[],422);

        $remove=[];
        foreach ($submitted as $item) {
            if (!is_array($item) || !empty($item['is_visible'])) continue;
            $id=(int)($item['sidebar_item_id']??0); if ($id<=0) continue;
            $ids=school_sidebar_service_school_descendants($pdo,$schoolId,$id);
            if ($ids===[]) $ids=[$id];
            foreach ($ids as $x) $remove[$x]=$x;
        }

        $pdo->beginTransaction();
        if ($remove!==[]) school_sidebar_service_remove_school_items($pdo,$schoolId,array_values($remove));
        foreach ($submitted as $item) {
            if (!is_array($item)) continue;
            $id=(int)($item['sidebar_item_id']??0);
            if ($id<=0 || isset($remove[$id]) || empty($item['is_visible'])) continue;
            school_sidebar_service_assign_parent_chain($pdo,$schoolId,$id);
            school_sidebar_service_assign_item($pdo,$schoolId,$id);
            $permissions=ssm_normalize_permission_map(
                is_array($item['permissions']??null)?$item['permissions']:[],
                $actionKeys
            );
            ssm_save_grants($pdo,$schoolId,$roleId,$id,$permissions,$actionKeys);
        }
        school_sidebar_service_bump_version($pdo,$schoolId);
        $pdo->commit();
        ssm_json(true,'School sidebar and role permissions saved successfully.');
    }

    if ($action === 'add') {
        $roleId=(int)($input['role_id']??0);
        if ($schoolId>0) {
            ssm_school($pdo,$schoolId);
            if ($roleId>0) ssm_role($pdo,$schoolId,$roleId);
        }
        $pdo->beginTransaction();
        $created=school_sidebar_service_create_menu(
            $pdo,$schoolId,$input,(int)($_SESSION['user_id']??0)?:null
        );
        $itemId=(int)$created['sidebar_item_id'];
        if ($schoolId>0 && $roleId>0) {
            $permissions=array_fill_keys($actionKeys,0);
            $permissions['view']=1;
            $role=ssm_role($pdo,$schoolId,$roleId);
            if (in_array(strtolower((string)$role['role_key']),['school_admin','school_administrator','school-administrator'],true)) {
                foreach ($permissions as $key=>$_) $permissions[$key]=1;
            }
            ssm_save_grants($pdo,$schoolId,$roleId,$itemId,$permissions,$actionKeys);
        }
        school_sidebar_service_bump_version($pdo,$schoolId>0?$schoolId:null);
        $pdo->commit();
        ssm_json(true,!empty($created['reused'])
            ? 'Existing Default Sidebar menu assigned without duplicate records.'
            : ($schoolId>0
                ? 'School sidebar created and added to Default Sidebar successfully.'
                : 'Default sidebar item created successfully.'),
            ['sidebar_item_id'=>$itemId,'reused'=>(bool)$created['reused']],201);
    }

    if ($action === 'edit') {
        $itemId=(int)($input['sidebar_item_id']??0);
        if ($itemId<=0) ssm_json(false,'Invalid sidebar item.',[],422);
        if ($schoolId>0) ssm_school($pdo,$schoolId);
        $pdo->beginTransaction();
        school_sidebar_service_edit_menu(
            $pdo,$schoolId,$itemId,$input,(int)($_SESSION['user_id']??0)?:null
        );
        school_sidebar_service_bump_version($pdo,$schoolId>0?$schoolId:null);
        $pdo->commit();
        ssm_json(true,$schoolId>0
            ? 'School sidebar item updated successfully.'
            : 'Default sidebar item updated successfully.');
    }

    if ($action === 'delete') {
        $itemId=(int)($input['sidebar_item_id']??0);
        if ($itemId<=0) ssm_json(false,'Invalid sidebar item.',[],422);
        if ($schoolId>0) ssm_school($pdo,$schoolId);
        $pdo->beginTransaction();
        $affected=school_sidebar_service_delete_menu($pdo,$schoolId,$itemId);
        school_sidebar_service_bump_version($pdo,$schoolId>0?$schoolId:null);
        $pdo->commit();
        ssm_json(true,$schoolId>0
            ? 'Sidebar item and assigned submenus removed from this school.'
            : 'Default sidebar item, submenus and related records deleted successfully.',
            ['affected_items'=>count($affected)]);
    }

    ssm_json(false, 'Invalid API action.', [], 400);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('school-sidebar-management: ' . $exception->getMessage());
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $isLocal = strpos($host, 'localhost') !== false
        || strpos($host, '127.0.0.1') !== false;

    ssm_json(
        false,
        $isLocal
            ? 'Sidebar request failed: ' . $exception->getMessage()
            : 'Unable to complete the sidebar request.',
        [
            'action' => $action,
            'error_type' => $isLocal ? get_class($exception) : null,
        ],
        500
    );
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function sa_json(bool $success, string $message, array $data = [], int $status = 200): never
{
    http_response_code($status);
    echo json_encode(
        ['success' => $success, 'message' => $message, 'data' => $data],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function sa_input(): array
{
    $decoded = json_decode((string)file_get_contents('php://input'), true);
    return is_array($decoded) ? $decoded : $_POST;
}

function sa_csrf_valid(mixed $token): bool
{
    if (function_exists('csrf_is_valid')) {
        return csrf_is_valid($token);
    }
    $stored = (string)($_SESSION['csrf_token'] ?? $_SESSION['_csrf'] ?? '');
    $given = is_string($token) ? $token : '';
    return $stored !== '' && $given !== '' && hash_equals($stored, $given);
}

function sa_role_key(PDO $pdo, int $roleId): string
{
    $stmt = $pdo->prepare(
        "SELECT role_key FROM roles WHERE id = :id AND status = 'active' LIMIT 1"
    );
    $stmt->execute(['id' => $roleId]);
    return strtolower(trim((string)$stmt->fetchColumn()));
}

function sa_is_super_role(PDO $pdo, int $roleId): bool
{
    return $roleId === 1 || in_array(
        sa_role_key($pdo, $roleId),
        ['super_admin', 'super-administrator', 'super_administrator'],
        true
    );
}

function sa_scope_sql(PDO $pdo, string $alias = 'si'): string
{
    return school_column_exists($pdo, 'sidebar_items', 'portal_scope')
        ? "{$alias}.portal_scope IN ('super_admin','all')"
        : "{$alias}.menu_key LIKE 'sa\\_%'";
}

function sa_menu_key(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';
    return substr(trim($value, '_'), 0, 100);
}

function sa_column(PDO $pdo, string $column): bool
{
    return school_column_exists($pdo, 'sidebar_items', $column);
}

/**
 * @return array<int,int>
 */
function sa_descendant_ids(
    PDO $pdo,
    int $rootId,
    string $scope
): array {
    $rows = $pdo->query(
        "SELECT si.id, si.parent_id
         FROM sidebar_items AS si
         WHERE {$scope}"
    )->fetchAll(PDO::FETCH_ASSOC);

    $children = [];

    foreach ($rows as $row) {
        $parentId = (int)($row['parent_id'] ?? 0);
        $children[$parentId][] = (int)$row['id'];
    }

    $ids = [];
    $stack = [$rootId];

    while ($stack) {
        $current = array_pop($stack);

        if (in_array($current, $ids, true)) {
            continue;
        }

        $ids[] = $current;

        foreach ($children[$current] ?? [] as $childId) {
            $stack[] = $childId;
        }
    }

    return $ids;
}

/**
 * @param array<int,array<string,mixed>> $items
 * @return array<int,array<string,mixed>>
 */
function sa_normalize_submitted_items(array $items): array
{
    $map = [];

    foreach ($items as $item) {
        if (!is_array($item)) continue;

        $id = (int)($item['sidebar_item_id'] ?? 0);
        if ($id <= 0) continue;

        $item['sidebar_item_id'] = $id;
        $item['parent_id'] = isset($item['parent_id'])
            && $item['parent_id'] !== null
            && $item['parent_id'] !== ''
                ? (int)$item['parent_id']
                : null;

        $map[$id] = $item;
    }

    foreach ($map as $id => &$item) {
        $parentId = $item['parent_id'];

        if ($parentId === $id || ($parentId !== null && !isset($map[$parentId]))) {
            $item['parent_id'] = null;
            continue;
        }

        $seen = [$id => true];
        $cursor = $parentId;

        while ($cursor !== null && isset($map[$cursor])) {
            if (isset($seen[$cursor])) {
                $item['parent_id'] = null;
                break;
            }

            $seen[$cursor] = true;
            $cursor = $map[$cursor]['parent_id'] ?? null;
        }
    }
    unset($item);

    foreach ($map as $item) {
        if (
            empty($item['can_show'])
            && empty($item['is_visible'])
            && empty($item['is_active'])
        ) {
            continue;
        }

        $parentId = $item['parent_id'];
        $guard = 0;

        while ($parentId !== null && isset($map[$parentId]) && $guard++ < 100) {
            $map[$parentId]['can_show'] = 1;
            $map[$parentId]['is_visible'] = 1;
            $map[$parentId]['is_active'] = 1;
            $parentId = $map[$parentId]['parent_id'] ?? null;
        }
    }

    $siblings = [];

    foreach ($map as $id => $item) {
        $siblings[(int)($item['parent_id'] ?? 0)][$id] = $item;
    }

    foreach ($siblings as $group) {
        uasort($group, static function (array $a, array $b): int {
            return ((int)($a['display_order'] ?? 0))
                <=> ((int)($b['display_order'] ?? 0))
                ?: strcmp(
                    (string)($a['menu_title'] ?? ''),
                    (string)($b['menu_title'] ?? '')
                )
                ?: ((int)$a['sidebar_item_id'] <=> (int)$b['sidebar_item_id']);
        });

        $order = 10;
        foreach ($group as $id => $unused) {
            $map[$id]['display_order'] = $order;
            $order += 10;
        }
    }

    return array_values($map);
}

function sa_repair_database_hierarchy(
    PDO $pdo,
    string $scope,
    int $tenantId
): int {
    $rows = $pdo->query(
        "SELECT
            si.id AS sidebar_item_id,
            si.parent_id,
            si.menu_title,
            si.icon,
            si.route,
            si.display_order,
            si.is_active,
            si.show_in_sidebar AS is_visible,
            1 AS can_show
         FROM sidebar_items AS si
         WHERE {$scope}
         ORDER BY si.display_order, si.id"
    )->fetchAll(PDO::FETCH_ASSOC);

    $items = sa_normalize_submitted_items($rows);

    $update = $pdo->prepare(
        "UPDATE sidebar_items
         SET parent_id = :parent_id,
             display_order = :display_order,
             is_active = :is_active,
             show_in_sidebar = :is_visible
         WHERE id = :item_id"
    );

    foreach ($items as $item) {
        $update->execute([
            'parent_id' => $item['parent_id'],
            'display_order' => $item['display_order'],
            'is_active' => !empty($item['is_active']) ? 1 : 0,
            'is_visible' => !empty($item['is_visible']) ? 1 : 0,
            'item_id' => $item['sidebar_item_id'],
        ]);
    }

    if (school_table_exists($pdo, 'tenant_sidebar_items')) {
        $clear = $pdo->prepare(
            "UPDATE tenant_sidebar_items AS tsi
             INNER JOIN sidebar_items AS si
                ON si.id = tsi.sidebar_item_id
             SET tsi.custom_title = NULL,
                 tsi.custom_icon = NULL,
                 tsi.display_order = si.display_order,
                 tsi.is_visible = si.show_in_sidebar,
                 tsi.updated_at = CURRENT_TIMESTAMP
             WHERE tsi.tenant_id = :tenant_id
               AND {$scope}"
        );

        $clear->execute(['tenant_id' => $tenantId]);
    }

    return count($items);
}

function sa_activity(
    PDO $pdo,
    int $tenantId,
    int $roleId,
    string $action,
    int $recordId,
    string $description,
    array $newValues = []
): void {
    if (!school_table_exists($pdo, 'activity_logs')) {
        return;
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO activity_logs
                (tenant_id,branch_id,user_id,role_id,module_name,action_key,
                 table_name,record_id,new_values,description,ip_address,user_agent)
             VALUES
                (:tenant_id,NULL,:user_id,:role_id,'Super Admin Sidebar',
                 :action_key,'sidebar_items',:record_id,:new_values,
                 :description,:ip_address,:user_agent)"
        );

        $stmt->execute([
            'tenant_id' => $tenantId,
            'user_id' => (int)($_SESSION['user_id'] ?? 0) ?: null,
            'role_id' => $roleId ?: null,
            'action_key' => $action,
            'record_id' => $recordId,
            'new_values' => json_encode(
                $newValues,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
            'description' => substr($description, 0, 255),
            'ip_address' => substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
            'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1000) ?: null,
        ]);
    } catch (Throwable $e) {
        error_log('sidebar activity log: ' . $e->getMessage());
    }
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    sa_json(false, 'Database connection is unavailable', [], 500);
}
foreach (['roles', 'sidebar_items', 'role_sidebar_permissions'] as $table) {
    if (!school_table_exists($pdo, $table)) {
        sa_json(false, 'Required table is missing: ' . $table, [], 500);
    }
}

$user = function_exists('current_user') ? current_user() : [];
$currentRoleId = (int)($user['role_id'] ?? $_SESSION['role_id'] ?? 0);
$tenantId = max(1, (int)($user['tenant_id'] ?? $_SESSION['tenant_id'] ?? 1));
if (!sa_is_super_role($pdo, $currentRoleId)) {
    sa_json(false, 'Access denied', [], 403);
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$scope = sa_scope_sql($pdo);
$hasOverrides = school_table_exists($pdo, 'tenant_sidebar_items');

if ($method === 'GET') {
    $targetRoleId = (int)($_GET['role_id'] ?? $currentRoleId);
    if (!sa_is_super_role($pdo, $targetRoleId)) {
        sa_json(false, 'Invalid Super Admin role', [], 422);
    }

    $roles = $pdo->query(
        "SELECT id, role_key, role_name FROM roles
         WHERE status = 'active'
           AND role_key IN ('super_admin','super-administrator','super_administrator')
         ORDER BY is_system DESC, role_name"
    )->fetchAll(PDO::FETCH_ASSOC);

    $overrideSelect = $hasOverrides
        ? "COALESCE(tsi.is_visible,1) AS is_visible,
           tsi.custom_title, tsi.custom_icon,
           tsi.display_order AS custom_order,
           COALESCE(NULLIF(tsi.custom_title,''),si.menu_title) AS effective_title,
           COALESCE(NULLIF(tsi.custom_icon,''),si.icon) AS effective_icon"
        : "1 AS is_visible, NULL AS custom_title,
           NULL AS custom_icon, NULL AS custom_order,
           si.menu_title AS effective_title,
           si.icon AS effective_icon";
    $overrideJoin = $hasOverrides
        ? "LEFT JOIN tenant_sidebar_items tsi
             ON tsi.sidebar_item_id = si.id AND tsi.tenant_id = :tenant_id"
        : '';
    $orderSql = $hasOverrides
        ? 'COALESCE(tsi.display_order, si.display_order)'
        : 'si.display_order';

    $sql = "SELECT si.id AS sidebar_item_id, si.parent_id, si.menu_key,
                   si.menu_title, si.route, si.icon, si.is_active,
                   si.show_in_sidebar,
                   si.display_order AS default_order,
                   COALESCE(rsp.can_show,0) AS can_show,
                   {$overrideSelect}
            FROM sidebar_items si
            LEFT JOIN role_sidebar_permissions rsp
              ON rsp.sidebar_item_id = si.id AND rsp.role_id = :role_id
            {$overrideJoin}
            WHERE {$scope}
            ORDER BY {$orderSql}, si.id";
    $stmt = $pdo->prepare($sql);
    $params = ['role_id' => $targetRoleId];
    if ($hasOverrides) $params['tenant_id'] = $tenantId;
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($items as &$item) {
        $item['sidebar_item_id'] = (int)$item['sidebar_item_id'];
        $item['parent_id'] = !empty($item['parent_id']) ? (int)$item['parent_id'] : null;
        $item['can_show'] = (int)$item['can_show'];
        $item['is_visible'] = (int)$item['is_visible'];
        $item['is_active'] = (int)$item['is_active'];
        $item['show_in_sidebar'] = (int)$item['show_in_sidebar'];
        $item['default_order'] = (int)$item['default_order'];
        $item['display_order'] = $item['custom_order'] !== null
            ? (int)$item['custom_order'] : $item['default_order'];
    }
    unset($item);

    sa_json(true, 'Super Admin sidebar options loaded', [
        'roles' => $roles,
        'role_id' => $targetRoleId,
        'items' => $items,
        'csrf_token' => function_exists('csrfToken') ? csrfToken() : null,
    ]);
}

if ($method !== 'POST') {
    sa_json(false, 'Method not allowed', [], 405);
}
$input = sa_input();
if (!sa_csrf_valid($input['csrf_token'] ?? null)) {
    sa_json(false, 'Invalid CSRF token', [], 419);
}
$targetRoleId = (int)($input['role_id'] ?? $currentRoleId);
if (!sa_is_super_role($pdo, $targetRoleId)) {
    sa_json(false, 'Invalid Super Admin role', [], 422);
}
$action = strtolower(trim((string)($input['action'] ?? 'save')));

try {
    $idRows = $pdo->query("SELECT si.id FROM sidebar_items si WHERE {$scope}")
        ->fetchAll(PDO::FETCH_COLUMN);
    $allowedIds = array_map('intval', $idRows);

    if ($action === 'create_item') {
        $title = trim((string)($input['menu_title'] ?? ''));
        $menuKey = sa_menu_key((string)($input['menu_key'] ?? $title));
        $route = trim((string)($input['route'] ?? '#'));
        $icon = trim((string)($input['icon'] ?? 'circle'));
        $parentId = isset($input['parent_id']) && $input['parent_id'] !== null
            ? (int)$input['parent_id']
            : null;
        $displayOrder = max(0, min(9999, (int)($input['display_order'] ?? 100)));
        $canShow = !empty($input['can_show']) ? 1 : 0;
        $isVisible = !empty($input['is_visible']) ? 1 : 0;
        $isActive = !empty($input['is_active']) ? 1 : 0;

        if ($title === '' || mb_strlen($title) > 120) {
            throw new InvalidArgumentException('Menu Title is required and cannot exceed 120 characters');
        }

        if ($menuKey === '' || preg_match('/^[a-z][a-z0-9_]{1,99}$/', $menuKey) !== 1) {
            throw new InvalidArgumentException('Menu Key must start with a letter and contain lowercase letters, numbers and underscores');
        }

        if ($route === '') {
            $route = '#';
        }

        if (mb_strlen($route) > 255) {
            throw new InvalidArgumentException('Route cannot exceed 255 characters');
        }

        if ($icon === '') {
            $icon = 'circle';
        }

        if (preg_match('/^[a-zA-Z0-9_-]{1,80}$/', $icon) !== 1) {
            throw new InvalidArgumentException('Invalid Lucide icon name');
        }

        if ($parentId !== null && !in_array($parentId, $allowedIds, true)) {
            throw new InvalidArgumentException('Invalid parent sidebar option');
        }

        $duplicate = $pdo->prepare(
            "SELECT id FROM sidebar_items WHERE menu_key = :menu_key LIMIT 1"
        );
        $duplicate->execute(['menu_key' => $menuKey]);

        if ($duplicate->fetchColumn()) {
            throw new InvalidArgumentException('This Menu Key already exists');
        }

        $columns = [
            'parent_id',
            'menu_key',
            'menu_title',
            'route',
            'icon',
            'display_order',
            'show_in_sidebar',
            'is_active',
        ];

        $values = [
            ':parent_id',
            ':menu_key',
            ':menu_title',
            ':route',
            ':icon',
            ':display_order',
            ':show_in_sidebar',
            ':is_active',
        ];

        $params = [
            'parent_id' => $parentId,
            'menu_key' => $menuKey,
            'menu_title' => $title,
            'route' => $route,
            'icon' => $icon,
            'display_order' => $displayOrder,
            'show_in_sidebar' => 1,
            'is_active' => $isActive,
        ];

        if (sa_column($pdo, 'portal_scope')) {
            $columns[] = 'portal_scope';
            $values[] = ':portal_scope';
            $params['portal_scope'] = 'super_admin';
        }

        if (sa_column($pdo, 'badge_text')) {
            $columns[] = 'badge_text';
            $values[] = ':badge_text';
            $params['badge_text'] = null;
        }

        $pdo->beginTransaction();

        $insert = $pdo->prepare(
            "INSERT INTO sidebar_items (" . implode(',', $columns) . ")
             VALUES (" . implode(',', $values) . ")"
        );
        $insert->execute($params);

        $itemId = (int)$pdo->lastInsertId();

        $permission = $pdo->prepare(
            "INSERT INTO role_sidebar_permissions
                (role_id,sidebar_item_id,can_show)
             VALUES
                (:role_id,:item_id,:can_show)
             ON DUPLICATE KEY UPDATE
                can_show=VALUES(can_show),
                updated_at=CURRENT_TIMESTAMP"
        );
        $permission->execute([
            'role_id' => $targetRoleId,
            'item_id' => $itemId,
            'can_show' => $canShow,
        ]);

        if ($hasOverrides) {
            $override = $pdo->prepare(
                "INSERT INTO tenant_sidebar_items
                    (tenant_id,sidebar_item_id,custom_title,custom_icon,
                     display_order,is_visible)
                 VALUES
                    (:tenant_id,:item_id,NULL,NULL,:display_order,:is_visible)
                 ON DUPLICATE KEY UPDATE
                    display_order=VALUES(display_order),
                    is_visible=VALUES(is_visible),
                    updated_at=CURRENT_TIMESTAMP"
            );

            $override->execute([
                'tenant_id' => $tenantId,
                'item_id' => $itemId,
                'display_order' => $displayOrder,
                'is_visible' => $isVisible,
            ]);
        }

        sa_activity(
            $pdo,
            $tenantId,
            $currentRoleId,
            'sidebar_item_created',
            $itemId,
            'Created Super Admin sidebar option ' . $title,
            [
                'menu_key' => $menuKey,
                'menu_title' => $title,
                'route' => $route,
                'icon' => $icon,
                'parent_id' => $parentId,
                'display_order' => $displayOrder,
                'can_show' => $canShow,
                'is_visible' => $isVisible,
                'is_active' => $isActive,
            ]
        );

        $pdo->commit();

        sa_json(true, 'Sidebar option created successfully', [
            'sidebar_item_id' => $itemId,
        ], 201);
    }

    if ($action === 'update_item') {
        $itemId = (int)($input['sidebar_item_id'] ?? 0);

        if (
            $itemId <= 0
            || !in_array($itemId, $allowedIds, true)
        ) {
            throw new InvalidArgumentException(
                'Invalid sidebar item'
            );
        }

        $title = trim(
            (string)($input['menu_title'] ?? '')
        );

        $route = trim(
            (string)($input['route'] ?? '#')
        );

        $icon = strtolower(trim(
            (string)($input['icon'] ?? 'circle')
        ));

        $parentId =
            isset($input['parent_id'])
            && $input['parent_id'] !== null
            && $input['parent_id'] !== ''
                ? (int)$input['parent_id']
                : null;

        $displayOrder = max(
            0,
            min(
                9999,
                (int)($input['display_order'] ?? 0)
            )
        );

        $canShow =
            !empty($input['can_show']) ? 1 : 0;

        $isVisible =
            !empty($input['is_visible']) ? 1 : 0;

        $isActive =
            !empty($input['is_active']) ? 1 : 0;

        if (
            $title === ''
            || mb_strlen($title) > 120
        ) {
            throw new InvalidArgumentException(
                'Menu Title is required and cannot exceed 120 characters'
            );
        }

        if ($route === '') {
            $route = '#';
        }

        if (mb_strlen($route) > 255) {
            throw new InvalidArgumentException(
                'Route cannot exceed 255 characters'
            );
        }

        if ($icon === '') {
            $icon = 'circle';
        }

        if (
            preg_match(
                '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $icon
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Invalid Lucide icon name'
            );
        }

        if ($parentId !== null) {
            if (!in_array($parentId, $allowedIds, true)) {
                throw new InvalidArgumentException(
                    'Invalid parent sidebar option'
                );
            }

            $descendantIds = sa_descendant_ids(
                $pdo,
                $itemId,
                $scope
            );

            if (in_array($parentId, $descendantIds, true)) {
                throw new InvalidArgumentException(
                    'A sidebar item cannot be moved under itself or one of its child items'
                );
            }
        }

        $pdo->beginTransaction();

        $update = $pdo->prepare(
            "UPDATE sidebar_items
             SET parent_id = :parent_id,
                 menu_title = :menu_title,
                 route = :route,
                 icon = :icon,
                 display_order = :display_order,
                 show_in_sidebar = :show_in_sidebar,
                 is_active = :is_active
             WHERE id = :item_id"
        );

        $update->execute([
            'parent_id' => $parentId,
            'menu_title' => $title,
            'route' => $route,
            'icon' => $icon,
            'display_order' => $displayOrder,
            'show_in_sidebar' => $isVisible,
            'is_active' => $isActive,
            'item_id' => $itemId,
        ]);

        $permission = $pdo->prepare(
            "INSERT INTO role_sidebar_permissions
                (role_id,sidebar_item_id,can_show)
             VALUES
                (:role_id,:item_id,:can_show)
             ON DUPLICATE KEY UPDATE
                can_show = VALUES(can_show),
                updated_at = CURRENT_TIMESTAMP"
        );

        $permission->execute([
            'role_id' => $targetRoleId,
            'item_id' => $itemId,
            'can_show' => $canShow,
        ]);

        if ($hasOverrides) {
            $override = $pdo->prepare(
                "INSERT INTO tenant_sidebar_items
                    (tenant_id,sidebar_item_id,custom_title,custom_icon,
                     display_order,is_visible)
                 VALUES
                    (:tenant_id,:item_id,NULL,NULL,:display_order,:is_visible)
                 ON DUPLICATE KEY UPDATE
                    custom_title = NULL,
                    custom_icon = NULL,
                    display_order = VALUES(display_order),
                    is_visible = VALUES(is_visible),
                    updated_at = CURRENT_TIMESTAMP"
            );

            $override->execute([
                'tenant_id' => $tenantId,
                'item_id' => $itemId,
                'display_order' => $displayOrder,
                'is_visible' => $isVisible,
            ]);
        }

        sa_activity(
            $pdo,
            $tenantId,
            $currentRoleId,
            'sidebar_item_updated',
            $itemId,
            'Updated Super Admin sidebar option ' . $title,
            [
                'menu_title' => $title,
                'route' => $route,
                'icon' => $icon,
                'parent_id' => $parentId,
                'display_order' => $displayOrder,
                'can_show' => $canShow,
                'is_visible' => $isVisible,
                'is_active' => $isActive,
            ]
        );

        $pdo->commit();

        sa_json(
            true,
            'Sidebar option updated successfully',
            [
                'sidebar_item_id' => $itemId,
                'csrf_token' =>
                    function_exists('csrfToken')
                        ? csrfToken()
                        : null,
            ]
        );
    }

    if ($action === 'delete_item') {
        $itemId = (int)($input['sidebar_item_id'] ?? 0);

        if (!in_array($itemId, $allowedIds, true)) {
            throw new InvalidArgumentException(
                'Invalid sidebar item'
            );
        }

        $itemStatement = $pdo->prepare(
            "SELECT menu_title, menu_key
             FROM sidebar_items
             WHERE id = :item_id
             LIMIT 1"
        );
        $itemStatement->execute([
            'item_id' => $itemId,
        ]);

        $itemRecord = $itemStatement->fetch(PDO::FETCH_ASSOC);

        if (!$itemRecord) {
            throw new InvalidArgumentException(
                'Sidebar item was not found'
            );
        }

        $deleteIds = sa_descendant_ids(
            $pdo,
            $itemId,
            $scope
        );

        $marks = implode(
            ',',
            array_fill(0, count($deleteIds), '?')
        );

        $pdo->beginTransaction();

        if ($hasOverrides) {
            $deleteOverrides = $pdo->prepare(
                "DELETE FROM tenant_sidebar_items
                 WHERE sidebar_item_id IN ({$marks})"
            );
            $deleteOverrides->execute($deleteIds);
        }

        $deleteRolePermissions = $pdo->prepare(
            "DELETE FROM role_sidebar_permissions
             WHERE sidebar_item_id IN ({$marks})"
        );
        $deleteRolePermissions->execute($deleteIds);

        $deleteItems = $pdo->prepare(
            "DELETE FROM sidebar_items
             WHERE id IN ({$marks})"
        );
        $deleteItems->execute($deleteIds);

        sa_activity(
            $pdo,
            $tenantId,
            $currentRoleId,
            'sidebar_item_deleted',
            $itemId,
            'Deleted Super Admin sidebar option '
                . (string)$itemRecord['menu_title'],
            [
                'menu_key' =>
                    (string)$itemRecord['menu_key'],
                'deleted_item_ids' => $deleteIds,
            ]
        );

        $pdo->commit();

        sa_json(
            true,
            count($deleteIds) > 1
                ? 'Sidebar option and child items deleted successfully'
                : 'Sidebar option deleted successfully'
        );
    }

    $pdo->beginTransaction();
    $permissionStmt = $pdo->prepare(
        "INSERT INTO role_sidebar_permissions(role_id,sidebar_item_id,can_show)
         VALUES(:role_id,:item_id,:can_show)
         ON DUPLICATE KEY UPDATE can_show=VALUES(can_show),updated_at=CURRENT_TIMESTAMP"
    );

    if ($action === 'repair_consistency') {
        $itemCount = sa_repair_database_hierarchy(
            $pdo,
            $scope,
            $tenantId
        );

        foreach ($allowedIds as $itemId) {
            $permissionStmt->execute([
                'role_id' => $targetRoleId,
                'item_id' => $itemId,
                'can_show' => 1,
            ]);
        }

        sa_activity(
            $pdo,
            $tenantId,
            $currentRoleId,
            'sidebar_consistency_repaired',
            $targetRoleId,
            'Repaired Super Admin sidebar hierarchy, labels, icons and order',
            [
                'role_id' => $targetRoleId,
                'item_count' => $itemCount,
            ]
        );

        $pdo->commit();

        sa_json(
            true,
            'Super Admin sidebar consistency repaired successfully'
        );
    }

    if ($action === 'reset_role') {
        foreach ($allowedIds as $itemId) {
            $permissionStmt->execute([
                'role_id' => $targetRoleId,
                'item_id' => $itemId,
                'can_show' => 1,
            ]);
        }
        if ($hasOverrides && $allowedIds) {
            $marks = implode(',', array_fill(0, count($allowedIds), '?'));
            $delete = $pdo->prepare(
                "DELETE FROM tenant_sidebar_items
                 WHERE tenant_id = ? AND sidebar_item_id IN ({$marks})"
            );
            $delete->execute([$tenantId, ...$allowedIds]);
        }
        $pdo->commit();
        sa_json(true, 'Super Admin sidebar options reset successfully');
    }

    if ($action !== 'save' || !is_array($input['items'] ?? null)) {
        throw new InvalidArgumentException('Invalid action or menu data');
    }

    $itemUpdateStmt = $pdo->prepare(
        "UPDATE sidebar_items
         SET parent_id = :parent_id,
             menu_title = :menu_title,
             route = :route,
             icon = :icon,
             display_order = :display_order,
             show_in_sidebar = :show_in_sidebar,
             is_active = :is_active
         WHERE id = :item_id"
    );

    $overrideStmt = $hasOverrides ? $pdo->prepare(
        "INSERT INTO tenant_sidebar_items
            (tenant_id,sidebar_item_id,custom_title,custom_icon,display_order,is_visible)
         VALUES
            (:tenant_id,:item_id,:title,:icon,:display_order,:is_visible)
         ON DUPLICATE KEY UPDATE
            custom_title=VALUES(custom_title),custom_icon=VALUES(custom_icon),
            display_order=VALUES(display_order),is_visible=VALUES(is_visible),
            updated_at=CURRENT_TIMESTAMP"
    ) : null;

    $normalizedItems = sa_normalize_submitted_items($input['items']);

    foreach ($normalizedItems as $item) {
        if (!is_array($item)) continue;
        $itemId = (int)($item['sidebar_item_id'] ?? 0);
        if (!in_array($itemId, $allowedIds, true)) continue;

        $parentId = isset($item['parent_id'])
            && $item['parent_id'] !== null
            && $item['parent_id'] !== ''
                ? (int)$item['parent_id']
                : null;

        if ($parentId === $itemId) {
            throw new InvalidArgumentException(
                'A sidebar item cannot be its own parent'
            );
        }

        if (
            $parentId !== null
            && !in_array($parentId, $allowedIds, true)
        ) {
            throw new InvalidArgumentException(
                'Invalid parent sidebar item'
            );
        }

        $menuTitle = trim((string)(
            $item['menu_title']
            ?? $item['custom_title']
            ?? ''
        ));

        if (
            $menuTitle === ''
            || mb_strlen($menuTitle) > 120
        ) {
            throw new InvalidArgumentException(
                'Every sidebar item must have a valid title'
            );
        }

        $route = trim((string)(
            $item['route']
            ?? '#'
        ));

        if ($route === '') {
            $route = '#';
        }

        if (mb_strlen($route) > 255) {
            throw new InvalidArgumentException(
                'Sidebar route cannot exceed 255 characters'
            );
        }

        $icon = trim((string)(
            $item['icon']
            ?? $item['effective_icon']
            ?? $item['custom_icon']
            ?? 'circle'
        ));

        if ($icon === '') {
            $icon = 'circle';
        }

        $icon = strtolower($icon);

        if (
            preg_match(
                '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $icon
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Invalid Lucide icon name'
            );
        }

        $displayOrder = max(
            0,
            min(
                9999,
                (int)($item['display_order'] ?? 0)
            )
        );

        $itemUpdateStmt->execute([
            'parent_id' => $parentId,
            'menu_title' => $menuTitle,
            'route' => $route,
            'icon' => $icon,
            'display_order' => $displayOrder,
            'show_in_sidebar' =>
                !empty($item['is_visible']) ? 1 : 0,
            'is_active' =>
                !empty($item['is_active']) ? 1 : 0,
            'item_id' => $itemId,
        ]);

        $permissionStmt->execute([
            'role_id' => $targetRoleId,
            'item_id' => $itemId,
            'can_show' => !empty($item['can_show']) ? 1 : 0,
        ]);

        if ($overrideStmt instanceof PDOStatement) {
            $overrideStmt->execute([
                'tenant_id' => $tenantId,
                'item_id' => $itemId,
                'title' => null,
                'icon' => null,
                'display_order' => $displayOrder,
                'is_visible' => !empty($item['is_visible']) ? 1 : 0,
            ]);
        }
    }
    sa_activity(
        $pdo,
        $tenantId,
        $currentRoleId,
        'sidebar_options_saved',
        $targetRoleId,
        'Updated Super Admin sidebar options',
        [
            'role_id' => $targetRoleId,
            'item_count' => count($normalizedItems),
        ]
    );

    $pdo->commit();
    sa_json(true, 'Super Admin sidebar options saved successfully');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('super-admin-sidebar-settings: ' . $e->getMessage());

    if ($e instanceof InvalidArgumentException) {
        sa_json(false, $e->getMessage(), [], 422);
    }

    if (
        $e instanceof PDOException
        && (
            (string)$e->getCode() === '23000'
            || (int)($e->errorInfo[1] ?? 0) === 1062
        )
    ) {
        sa_json(false, 'The Menu Key already exists', [], 409);
    }

    sa_json(false, 'Unable to save Super Admin sidebar options', [], 500);
}

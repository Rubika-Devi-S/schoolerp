<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

const RP_API_BUILD = '2026-08-06-strict-action-permissions-v6';

function rpJson(bool $success, string $message = '', array $data = [], int $status = 200): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }

    echo json_encode(
        ['success' => $success, 'message' => $message, 'data' => $data],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

function rpInput(): array
{
    $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    if (str_contains($contentType, 'application/json')) {
        $decoded = json_decode((string)file_get_contents('php://input'), true);
        return is_array($decoded) ? $decoded : [];
    }

    return $_POST;
}

function rpTable(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = :table_name'
    );
    $stmt->execute(['table_name' => $table]);

    return (int)$stmt->fetchColumn() > 0;
}

function rpColumn(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = :table_name
           AND column_name = :column_name'
    );
    $stmt->execute([
        'table_name' => $table,
        'column_name' => $column,
    ]);

    return (int)$stmt->fetchColumn() > 0;
}

function rpScope(PDO $pdo): array
{
    $user = function_exists('current_user') ? current_user() : [];
    $user = is_array($user) ? $user : [];

    $scope = [
        'tenant_id' => (int)(
            $user['tenant_id']
            ?? $user['school_id']
            ?? $_SESSION['tenant_id']
            ?? $_SESSION['school_id']
            ?? 0
        ),
        'user_id' => (int)(
            $user['id']
            ?? $user['user_id']
            ?? $_SESSION['user_id']
            ?? 0
        ),
        'role_id' => (int)(
            $user['role_id']
            ?? $_SESSION['role_id']
            ?? 0
        ),
        'role_key' => strtolower(trim((string)(
            $user['role_key']
            ?? $_SESSION['role_key']
            ?? ''
        ))),
        'role_name' => strtolower(trim((string)(
            $user['role_name']
            ?? $_SESSION['role_name']
            ?? ''
        ))),
    ];

    if ($scope['role_key'] === '' && $scope['role_id'] > 0 && rpTable($pdo, 'roles')) {
        $stmt = $pdo->prepare(
            'SELECT role_key, role_name
             FROM roles
             WHERE id = :role_id
             LIMIT 1'
        );
        $stmt->execute(['role_id' => $scope['role_id']]);
        $role = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($role) {
            $scope['role_key'] = strtolower(trim((string)$role['role_key']));
            $scope['role_name'] = strtolower(trim((string)$role['role_name']));
        }
    }

    return $scope;
}

function rpIsAdministrator(array $scope): bool
{
    return in_array(
        $scope['role_key'],
        [
            'super_admin',
            'super-administrator',
            'super_administrator',
            'school_admin',
            'school-administrator',
            'school_administrator',
            'admin',
        ],
        true
    ) || in_array(
        $scope['role_name'],
        [
            'super administrator',
            'super admin',
            'school administrator',
            'school admin',
            'administrator',
            'admin',
        ],
        true
    );
}

function rpCan(array $scope, string $action): bool
{
    if (rpIsAdministrator($scope)) {
        return true;
    }

    if (function_exists('has_permission')) {
        foreach (
            [
                'roles_permissions',
                'school_role_permission',
                'role_permission',
                'user_management',
            ] as $permissionKey
        ) {
            try {
                if ((bool)has_permission($permissionKey, $action)) {
                    return true;
                }
            } catch (Throwable $exception) {
                error_log(
                    'Roles permission check failed for '
                    . $permissionKey
                    . '/'
                    . $action
                    . ': '
                    . $exception->getMessage()
                );
            }
        }
    }

    return false;
}

function rpRequire(array $scope, string $action): void
{
    if (!rpCan($scope, $action)) {
        rpJson(
            false,
            'You do not have permission to ' . $action . ' roles and permissions.',
            [],
            403
        );
    }
}

function rpCsrf(array $input): void
{
    $saved = (string)($_SESSION['roles_permissions_csrf_token'] ?? '');
    $received = (string)($input['csrf_token'] ?? '');

    if ($saved === '' || $received === '' || !hash_equals($saved, $received)) {
        rpJson(false, 'Invalid or expired CSRF token. Refresh the page.', [], 419);
    }
}

function rpSlug(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';

    return trim($value, '_');
}

function rpRequiredTables(PDO $pdo): void
{
    $required = [
        'roles',
        'users',
        'sidebar_items',
        'tenant_sidebar_items',
    ];

    $missing = [];
    foreach ($required as $table) {
        if (!rpTable($pdo, $table)) {
            $missing[] = $table;
        }
    }

    if (
        !rpTable($pdo, 'school_sidebar_action_permissions')
        && !rpTable($pdo, 'role_sidebar_permissions')
    ) {
        $missing[] = 'school_sidebar_action_permissions';
    }

    if (rpTable($pdo, 'school_sidebar_action_permissions')) {
        foreach (['can_view', 'can_add', 'can_edit', 'can_delete'] as $column) {
            if (!rpColumn($pdo, 'school_sidebar_action_permissions', $column)) {
                $missing[] = 'school_sidebar_action_permissions.' . $column;
            }
        }
    }

    if ($missing !== []) {
        throw new RuntimeException(
            'Missing required database table(s): ' . implode(', ', $missing) . '.'
        );
    }
}

/**
 * Return only sidebar rows explicitly assigned to this school by Super Admin.
 * A missing tenant_sidebar_items record is never treated as visible.
 *
 * @return array<int,array<string,mixed>> keyed by sidebar item id
 */
function rpAssignedSidebar(PDO $pdo, array $scope): array
{
    $tenantId = (int)$scope['tenant_id'];
    if ($tenantId <= 0) {
        return [];
    }

    $hasPortalScope = rpColumn($pdo, 'sidebar_items', 'portal_scope');
    $hasOwnerTenant = rpColumn($pdo, 'sidebar_items', 'owner_tenant_id');
    $hasCustomTitle = rpColumn($pdo, 'tenant_sidebar_items', 'custom_title');
    $hasCustomIcon = rpColumn($pdo, 'tenant_sidebar_items', 'custom_icon');
    $hasCustomRoute = rpColumn($pdo, 'tenant_sidebar_items', 'custom_route');
    $hasCustomParent = rpColumn($pdo, 'tenant_sidebar_items', 'custom_parent_id');
    $hasDisplayOrder = rpColumn($pdo, 'tenant_sidebar_items', 'display_order');
    $hasModuleId = rpColumn($pdo, 'sidebar_items', 'module_id');
    $hasShowInSidebar = rpColumn($pdo, 'sidebar_items', 'show_in_sidebar');
    $hasIsActive = rpColumn($pdo, 'sidebar_items', 'is_active');
    $hasTenantModules = $hasModuleId
        && rpTable($pdo, 'tenant_modules')
        && rpColumn($pdo, 'tenant_modules', 'tenant_id')
        && rpColumn($pdo, 'tenant_modules', 'module_id')
        && rpColumn($pdo, 'tenant_modules', 'is_enabled');

    $parentExpression = $hasCustomParent
        ? 'COALESCE(tsi.custom_parent_id, si.parent_id)'
        : 'si.parent_id';

    $titleExpression = $hasCustomTitle
        ? "COALESCE(NULLIF(TRIM(tsi.custom_title), ''), si.menu_title)"
        : 'si.menu_title';

    $iconExpression = $hasCustomIcon
        ? "COALESCE(NULLIF(TRIM(tsi.custom_icon), ''), si.icon)"
        : 'si.icon';

    $routeExpression = $hasCustomRoute
        ? "COALESCE(NULLIF(TRIM(tsi.custom_route), ''), si.route)"
        : 'si.route';

    $orderExpression = $hasDisplayOrder
        ? 'COALESCE(tsi.display_order, si.display_order)'
        : 'si.display_order';

    $where = ['tsi.is_visible = 1'];
    $parameters = [
        'override_tenant_id' => $tenantId,
    ];

    if ($hasPortalScope) {
        $where[] = "si.portal_scope IN ('school', 'all')";
    } else {
        $where[] = "si.menu_key NOT LIKE 'sa\\_%'";
    }

    if ($hasOwnerTenant) {
        $where[] = '(si.owner_tenant_id IS NULL OR si.owner_tenant_id = :owner_tenant_id)';
        $parameters['owner_tenant_id'] = $tenantId;
    }

    if ($hasShowInSidebar) {
        $where[] = 'si.show_in_sidebar = 1';
    }

    if ($hasIsActive) {
        $where[] = 'si.is_active = 1';
    }

    $moduleSelect = $hasModuleId ? 'si.module_id' : 'NULL AS module_id';
    $tenantModuleJoin = '';

    if ($hasTenantModules) {
        $tenantModuleJoin = 'LEFT JOIN tenant_modules AS tm
                ON tm.module_id = si.module_id
               AND tm.tenant_id = :module_tenant_id';
        $where[] = '(si.module_id IS NULL OR COALESCE(tm.is_enabled, 1) = 1)';
        $parameters['module_tenant_id'] = $tenantId;
    }

    /*
     * Use the same source, hierarchy fields and sibling ordering as the
     * School Admin sidebar loader. The final result is flattened in recursive
     * sidebar render order: parent, then each child subtree.
     */
    $sql = "SELECT
                si.id,
                {$parentExpression} AS parent_id,
                {$moduleSelect},
                si.menu_key,
                si.menu_title,
                {$titleExpression} AS display_title,
                {$iconExpression} AS display_icon,
                {$routeExpression} AS effective_route,
                {$orderExpression} AS display_order
            FROM sidebar_items AS si
            INNER JOIN tenant_sidebar_items AS tsi
                ON tsi.sidebar_item_id = si.id
               AND tsi.tenant_id = :override_tenant_id
               AND tsi.is_visible = 1
            {$tenantModuleJoin}
            WHERE " . implode(' AND ', $where) . "
            ORDER BY
                COALESCE({$parentExpression}, 0),
                {$orderExpression},
                si.id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($parameters);

    $raw = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $id = (int)($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }

        $row['id'] = $id;
        $row['parent_id'] = (int)($row['parent_id'] ?? 0);
        $row['module_id'] = (int)($row['module_id'] ?? 0);
        $row['display_order'] = (int)($row['display_order'] ?? 0);
        $raw[$id] = $row;
    }

    /* A child is valid only when every parent is also assigned. */
    $state = [];
    $valid = [];

    $isValid = static function (int $id) use (&$isValid, &$state, &$valid, $raw): bool {
        if (($state[$id] ?? 0) === 2) {
            return true;
        }

        if (($state[$id] ?? 0) === 1 || ($state[$id] ?? 0) === -1) {
            return false;
        }

        if (!isset($raw[$id])) {
            return false;
        }

        $state[$id] = 1;
        $parentId = (int)($raw[$id]['parent_id'] ?? 0);
        $ok = $parentId <= 0 || (isset($raw[$parentId]) && $isValid($parentId));
        $state[$id] = $ok ? 2 : -1;

        if ($ok) {
            $valid[$id] = $raw[$id];
        }

        return $ok;
    };

    foreach (array_keys($raw) as $id) {
        $isValid((int)$id);
    }

    if ($valid === []) {
        return [];
    }

    $sortSiblings = static function (array &$ids) use ($valid): void {
        usort(
            $ids,
            static function (int $leftId, int $rightId) use ($valid): int {
                $left = $valid[$leftId];
                $right = $valid[$rightId];

                return ((int)$left['display_order'] <=> (int)$right['display_order'])
                    ?: ($leftId <=> $rightId);
            }
        );
    };

    $children = [];
    $roots = [];

    foreach ($valid as $id => $item) {
        $parentId = (int)($item['parent_id'] ?? 0);

        if ($parentId > 0 && isset($valid[$parentId])) {
            $children[$parentId][] = (int)$id;
        } else {
            $roots[] = (int)$id;
        }
    }

    $sortSiblings($roots);
    foreach ($children as &$childIds) {
        $sortSiblings($childIds);
    }
    unset($childIds);

    $ordered = [];
    $sequence = 0;
    $visited = [];

    $appendTree = static function (int $id, int $depth) use (
        &$appendTree,
        &$ordered,
        &$sequence,
        &$visited,
        $valid,
        $children
    ): void {
        if (isset($visited[$id]) || !isset($valid[$id])) {
            return;
        }

        $visited[$id] = true;
        $item = $valid[$id];
        $item['depth'] = $depth;
        $item['sidebar_sequence'] = ++$sequence;
        $ordered[$id] = $item;

        foreach ($children[$id] ?? [] as $childId) {
            $appendTree((int)$childId, $depth + 1);
        }
    };

    foreach ($roots as $rootId) {
        $appendTree((int)$rootId, 0);
    }

    return $ordered;
}

function rpRolePermissionPageAssigned(array $assigned): bool
{
    foreach ($assigned as $item) {
        $key = strtolower(trim((string)($item['menu_key'] ?? '')));
        $route = strtolower(basename((string)($item['effective_route'] ?? '')));

        if (
            in_array(
                $key,
                [
                    'roles_permissions',
                    'roles_permission',
                    'school_role_permission',
                    'role_permission',
                ],
                true
            )
            || $route === 'roles-permissions.php'
        ) {
            return true;
        }
    }

    return false;
}

function rpRoleWhere(PDO $pdo, string $alias = 'r'): string
{
    $parts = ["{$alias}.tenant_id = :tenant_id"];

    if (rpColumn($pdo, 'roles', 'role_scope')) {
        $parts[] = "{$alias}.role_scope = 'school'";
    }

    if (rpColumn($pdo, 'roles', 'deleted_at')) {
        $parts[] = "{$alias}.deleted_at IS NULL";
    }

    return implode(' AND ', $parts);
}

function rpUserCount(PDO $pdo, int $tenantId, int $roleId): int
{
    if (!rpTable($pdo, 'users')) {
        return 0;
    }

    $where = [
        'tenant_id = :tenant_id',
        'role_id = :role_id',
    ];

    if (rpColumn($pdo, 'users', 'deleted_at')) {
        $where[] = 'deleted_at IS NULL';
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM users WHERE ' . implode(' AND ', $where)
    );
    $stmt->execute([
        'tenant_id' => $tenantId,
        'role_id' => $roleId,
    ]);

    return (int)$stmt->fetchColumn();
}

function rpVisiblePermissionCount(PDO $pdo, int $tenantId, int $roleId): int
{
    if (rpTable($pdo, 'school_sidebar_action_permissions')) {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM school_sidebar_action_permissions AS sap
             INNER JOIN tenant_sidebar_items AS tsi
                ON tsi.tenant_id = sap.tenant_id
               AND tsi.sidebar_item_id = sap.sidebar_item_id
               AND tsi.is_visible = 1
             WHERE sap.tenant_id = :tenant_id
               AND sap.role_id = :role_id
               AND sap.can_view = 1'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'role_id' => $roleId,
        ]);

        return (int)$stmt->fetchColumn();
    }

    if (rpTable($pdo, 'role_sidebar_permissions')) {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM role_sidebar_permissions AS rsp
             INNER JOIN tenant_sidebar_items AS tsi
                ON tsi.sidebar_item_id = rsp.sidebar_item_id
               AND tsi.tenant_id = :tenant_id
               AND tsi.is_visible = 1
             WHERE rsp.role_id = :role_id
               AND rsp.can_show = 1'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'role_id' => $roleId,
        ]);

        return (int)$stmt->fetchColumn();
    }

    return 0;
}

function rpRole(PDO $pdo, array $scope, int $roleId): array
{
    if ($roleId <= 0) {
        throw new InvalidArgumentException('Invalid role.');
    }

    $where = rpRoleWhere($pdo, 'r') . ' AND r.id = :role_id';
    $stmt = $pdo->prepare("SELECT r.* FROM roles AS r WHERE {$where} LIMIT 1");
    $stmt->execute([
        'tenant_id' => $scope['tenant_id'],
        'role_id' => $roleId,
    ]);

    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$role) {
        throw new InvalidArgumentException('Role not found.');
    }

    $role['user_count'] = rpUserCount(
        $pdo,
        (int)$scope['tenant_id'],
        (int)$role['id']
    );
    $role['permission_count'] = rpVisiblePermissionCount(
        $pdo,
        (int)$scope['tenant_id'],
        (int)$role['id']
    );

    return $role;
}

function rpProtectedRole(array $role): bool
{
    $key = strtolower(trim((string)($role['role_key'] ?? '')));

    /* Only the School Administrator is locked to full assigned-school access. */
    return in_array(
        $key,
        ['school_admin', 'school-administrator', 'school_administrator'],
        true
    );
}

function rpListRoles(PDO $pdo, array $scope, array $filter): array
{
    $search = trim((string)($filter['search'] ?? ''));
    $status = strtolower(trim((string)($filter['status'] ?? 'all')));
    $page = max(1, (int)($filter['page'] ?? 1));
    $perPage = min(100, max(5, (int)($filter['per_page'] ?? 10)));

    $where = [rpRoleWhere($pdo, 'r')];
    $params = ['tenant_id' => $scope['tenant_id']];

    if ($search !== '') {
        $where[] = '(
            r.role_name LIKE :search_name
            OR r.role_key LIKE :search_key
            OR COALESCE(r.description, \'\') LIKE :search_description
        )';
        $searchLike = '%' . $search . '%';
        $params['search_name'] = $searchLike;
        $params['search_key'] = $searchLike;
        $params['search_description'] = $searchLike;
    }

    if (in_array($status, ['active', 'inactive'], true)) {
        $where[] = 'r.status = :status';
        $params['status'] = $status;
    }

    $whereSql = implode(' AND ', $where);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM roles AS r WHERE {$whereSql}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $lastPage = max(1, (int)ceil($total / $perPage));
    $page = min($page, $lastPage);
    $offset = ($page - 1) * $perPage;

    $stmt = $pdo->prepare(
        "SELECT r.*
         FROM roles AS r
         WHERE {$whereSql}
         ORDER BY r.is_system DESC, r.role_name, r.id
         LIMIT :offset, :per_page"
    );

    foreach ($params as $name => $value) {
        $stmt->bindValue(':' . $name, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $perPage, PDO::PARAM_INT);
    $stmt->execute();

    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($records as &$record) {
        $record['user_count'] = rpUserCount(
            $pdo,
            (int)$scope['tenant_id'],
            (int)$record['id']
        );
        $record['permission_count'] = rpVisiblePermissionCount(
            $pdo,
            (int)$scope['tenant_id'],
            (int)$record['id']
        );
        $record['is_protected'] = rpProtectedRole($record);
        $record['can_edit'] = rpCan($scope, 'edit');
        $record['can_delete'] = rpCan($scope, 'delete')
            && !$record['is_protected']
            && (int)$record['user_count'] === 0;
        $record['can_manage_permissions'] = rpCan($scope, 'edit')
            || rpCan($scope, 'manage_settings');
    }
    unset($record);

    $statsStmt = $pdo->prepare(
        'SELECT
            COUNT(*) AS total_roles,
            SUM(status = \'active\') AS active_roles,
            SUM(is_system = 1) AS protected_roles
         FROM roles AS r
         WHERE ' . rpRoleWhere($pdo, 'r')
    );
    $statsStmt->execute(['tenant_id' => $scope['tenant_id']]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $usersWhere = ['tenant_id = :tenant_id'];
    if (rpColumn($pdo, 'users', 'deleted_at')) {
        $usersWhere[] = 'deleted_at IS NULL';
    }
    $usersStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM users WHERE ' . implode(' AND ', $usersWhere)
    );
    $usersStmt->execute(['tenant_id' => $scope['tenant_id']]);
    $stats['assigned_users'] = (int)$usersStmt->fetchColumn();

    return [
        'records' => $records,
        'stats' => $stats,
        'pagination' => [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => $lastPage,
        ],
        'permissions' => [
            'view' => rpCan($scope, 'view'),
            'create' => rpCan($scope, 'create'),
            'edit' => rpCan($scope, 'edit'),
            'delete' => rpCan($scope, 'delete'),
            'manage' => rpCan($scope, 'edit') || rpCan($scope, 'manage_settings'),
        ],
    ];
}

/**
 * @return array<int,array<string,int>> keyed by sidebar item id
 */
function rpPermissionRows(PDO $pdo, int $tenantId, int $roleId): array
{
    $rows = [];

    if (rpTable($pdo, 'school_sidebar_action_permissions')) {
        $stmt = $pdo->prepare(
            'SELECT sidebar_item_id, can_view, can_add, can_edit, can_delete
             FROM school_sidebar_action_permissions
             WHERE tenant_id = :tenant_id
               AND role_id = :role_id'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'role_id' => $roleId,
        ]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $id = (int)$row['sidebar_item_id'];
            $rows[$id] = [
                'can_view' => (int)$row['can_view'],
                'can_add' => (int)$row['can_add'],
                'can_edit' => (int)$row['can_edit'],
                'can_delete' => (int)$row['can_delete'],
            ];
        }

        return $rows;
    }

    if (rpTable($pdo, 'role_sidebar_permissions')) {
        $stmt = $pdo->prepare(
            'SELECT sidebar_item_id, can_show
             FROM role_sidebar_permissions
             WHERE role_id = :role_id'
        );
        $stmt->execute(['role_id' => $roleId]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $id = (int)$row['sidebar_item_id'];
            $rows[$id] = [
                'can_view' => (int)$row['can_show'],
                'can_add' => 0,
                'can_edit' => 0,
                'can_delete' => 0,
            ];
        }
    }

    return $rows;
}

function rpMatrix(PDO $pdo, array $scope, int $roleId, array $assigned): array
{
    $role = rpRole($pdo, $scope, $roleId);
    $locked = rpProtectedRole($role);
    $saved = rpPermissionRows(
        $pdo,
        (int)$scope['tenant_id'],
        (int)$role['id']
    );

    $items = [];
    foreach ($assigned as $id => $item) {
        $permission = $saved[(int)$id] ?? [
            'can_view' => 0,
            'can_add' => 0,
            'can_edit' => 0,
            'can_delete' => 0,
        ];

        if ($locked) {
            $permission = [
                'can_view' => 1,
                'can_add' => 1,
                'can_edit' => 1,
                'can_delete' => 1,
            ];
        }

        $items[] = [
            'sidebar_item_id' => (int)$id,
            'parent_id' => (int)($item['parent_id'] ?? 0),
            'module_id' => (int)($item['module_id'] ?? 0),
            'menu_key' => (string)($item['menu_key'] ?? ''),
            'menu_title' => (string)($item['display_title'] ?? $item['menu_title'] ?? 'Menu'),
            'route' => (string)($item['effective_route'] ?? '#'),
            'icon' => (string)($item['display_icon'] ?? 'circle'),
            'display_order' => (int)($item['display_order'] ?? 0),
            'sidebar_sequence' => (int)($item['sidebar_sequence'] ?? (count($items) + 1)),
            'depth' => (int)($item['depth'] ?? 0),
            'is_container' => trim((string)($item['effective_route'] ?? '#')) === '#' ? 1 : 0,
            'can_view' => (int)$permission['can_view'],
            'can_add' => (int)$permission['can_add'],
            'can_edit' => (int)$permission['can_edit'],
            'can_delete' => (int)$permission['can_delete'],
        ];
    }

    return [
        'role' => $role,
        'items' => $items,
        'is_locked' => $locked,
        'assigned_count' => count($items),
    ];
}

/**
 * Normalize the permission rows and enforce the parent-child visibility rule.
 *
 * @param array<int,array<string,mixed>> $submitted
 * @return array<int,array<string,int>> keyed by assigned sidebar item id
 */
function rpNormalizeRows(array $submitted, array $assigned, bool $locked): array
{
    $normalized = [];

    foreach ($assigned as $id => $item) {
        $normalized[(int)$id] = [
            'can_view' => $locked ? 1 : 0,
            'can_add' => $locked ? 1 : 0,
            'can_edit' => $locked ? 1 : 0,
            'can_delete' => $locked ? 1 : 0,
        ];
    }

    if (!$locked) {
        foreach ($submitted as $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = (int)($row['sidebar_item_id'] ?? 0);
            if (!isset($assigned[$id])) {
                continue;
            }

            $canAdd = !empty($row['can_add']) ? 1 : 0;
            $canEdit = !empty($row['can_edit']) ? 1 : 0;
            $canDelete = !empty($row['can_delete']) ? 1 : 0;
            $canView = !empty($row['can_view']) || $canAdd || $canEdit || $canDelete
                ? 1
                : 0;

            $normalized[$id] = [
                'can_view' => $canView,
                'can_add' => $canAdd,
                'can_edit' => $canEdit,
                'can_delete' => $canDelete,
            ];
        }

        /*
         * A hidden parent wins and clears every descendant permission. The
         * client sends the complete matrix, so this also blocks manually
         * crafted requests that try to keep a child visible under a hidden
         * module.
         */
        $children = [];
        foreach ($assigned as $id => $item) {
            $parentId = (int)($item['parent_id'] ?? 0);
            $children[$parentId][] = (int)$id;
        }

        $clearChildren = static function (int $parentId) use (&$clearChildren, &$normalized, $children): void {
            foreach ($children[$parentId] ?? [] as $childId) {
                $normalized[$childId] = [
                    'can_view' => 0,
                    'can_add' => 0,
                    'can_edit' => 0,
                    'can_delete' => 0,
                ];
                $clearChildren($childId);
            }
        };

        foreach ($normalized as $id => $permission) {
            if ((int)$permission['can_view'] === 0) {
                $clearChildren((int)$id);
            }
        }

        /* Enabling a remaining child automatically enables every parent View. */
        foreach ($normalized as $id => $permission) {
            if ((int)$permission['can_view'] !== 1) {
                continue;
            }

            $parentId = (int)($assigned[$id]['parent_id'] ?? 0);
            $visited = [];

            while ($parentId > 0 && isset($assigned[$parentId]) && !isset($visited[$parentId])) {
                $visited[$parentId] = true;
                $normalized[$parentId]['can_view'] = 1;
                $parentId = (int)($assigned[$parentId]['parent_id'] ?? 0);
            }
        }
    }

    return $normalized;
}

function rpSaveMatrix(
    PDO $pdo,
    array $scope,
    int $roleId,
    array $rows,
    array $assigned,
    bool $locked
): void {
    $normalized = rpNormalizeRows($rows, $assigned, $locked);
    $assignedIds = array_map('intval', array_keys($assigned));

    if ($assignedIds === []) {
        return;
    }

    if (rpTable($pdo, 'school_sidebar_action_permissions')) {
        $upsert = $pdo->prepare(
            'INSERT INTO school_sidebar_action_permissions
                (tenant_id, role_id, sidebar_item_id,
                 can_view, can_add, can_edit, can_delete, can_manage_visibility)
             VALUES
                (:tenant_id, :role_id, :sidebar_item_id,
                 :can_view, :can_add, :can_edit, :can_delete, 0)
             ON DUPLICATE KEY UPDATE
                can_view = VALUES(can_view),
                can_add = VALUES(can_add),
                can_edit = VALUES(can_edit),
                can_delete = VALUES(can_delete),
                updated_at = CURRENT_TIMESTAMP'
        );

        foreach ($normalized as $sidebarId => $permission) {
            $upsert->execute([
                'tenant_id' => $scope['tenant_id'],
                'role_id' => $roleId,
                'sidebar_item_id' => $sidebarId,
                'can_view' => $permission['can_view'],
                'can_add' => $permission['can_add'],
                'can_edit' => $permission['can_edit'],
                'can_delete' => $permission['can_delete'],
            ]);
        }

        /* Remove role access for sidebar items no longer assigned to the school. */
        $placeholders = implode(',', array_fill(0, count($assignedIds), '?'));
        $delete = $pdo->prepare(
            "DELETE FROM school_sidebar_action_permissions
             WHERE tenant_id = ?
               AND role_id = ?
               AND sidebar_item_id NOT IN ({$placeholders})"
        );
        $delete->execute([
            (int)$scope['tenant_id'],
            $roleId,
            ...$assignedIds,
        ]);
    }

    if (rpTable($pdo, 'role_sidebar_permissions')) {
        $legacy = $pdo->prepare(
            'INSERT INTO role_sidebar_permissions
                (role_id, sidebar_item_id, can_show)
             VALUES
                (:role_id, :sidebar_item_id, :can_show)
             ON DUPLICATE KEY UPDATE
                can_show = VALUES(can_show)'
        );

        foreach ($normalized as $sidebarId => $permission) {
            $legacy->execute([
                'role_id' => $roleId,
                'sidebar_item_id' => $sidebarId,
                'can_show' => $permission['can_view'],
            ]);
        }
    }
}

function rpAudit(
    PDO $pdo,
    array $scope,
    string $action,
    int $entityId,
    array $oldData = [],
    array $newData = []
): void {
    if (!rpTable($pdo, 'activity_logs')) {
        return;
    }

    try {
        $columns = [
            'tenant_id' => $scope['tenant_id'],
            'user_id' => $scope['user_id'] ?: null,
            'module_name' => 'roles_permissions',
            'action_name' => $action,
            'reference_type' => 'role',
            'reference_id' => $entityId,
            'old_data' => $oldData !== [] ? json_encode($oldData, JSON_UNESCAPED_UNICODE) : null,
            'new_data' => $newData !== [] ? json_encode($newData, JSON_UNESCAPED_UNICODE) : null,
            'ip_address' => substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 50),
            'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1000),
            'remarks' => 'Roles and permissions update',
        ];

        $available = [];
        foreach ($columns as $column => $value) {
            if (rpColumn($pdo, 'activity_logs', $column)) {
                $available[$column] = $value;
            }
        }

        if ($available === []) {
            return;
        }

        $columnSql = implode(',', array_map(
            static fn(string $column): string => '`' . $column . '`',
            array_keys($available)
        ));
        $valueSql = implode(',', array_map(
            static fn(string $column): string => ':' . $column,
            array_keys($available)
        ));

        $stmt = $pdo->prepare(
            "INSERT INTO activity_logs ({$columnSql}) VALUES ({$valueSql})"
        );
        $stmt->execute($available);
    } catch (Throwable $exception) {
        error_log('Roles permission audit failed: ' . $exception->getMessage());
    }
}

if (!isset($pdo) || !$pdo instanceof PDO) {
    rpJson(false, 'Database connection is missing.', [], 500);
}

$scope = rpScope($pdo);
if ($scope['tenant_id'] <= 0 || $scope['user_id'] <= 0) {
    rpJson(false, 'Tenant or user session is missing.', [], 401);
}

if (
    empty($_SESSION['roles_permissions_csrf_token'])
    || !is_string($_SESSION['roles_permissions_csrf_token'])
) {
    $_SESSION['roles_permissions_csrf_token'] = bin2hex(random_bytes(32));
}

$input = rpInput();
$action = strtolower(trim((string)(
    $input['action']
    ?? $_GET['action']
    ?? 'list'
)));

try {
    rpRequiredTables($pdo);
    $assigned = rpAssignedSidebar($pdo, $scope);

    $isPlatformSuperAdmin = in_array(
        $scope['role_key'],
        ['super_admin', 'super-administrator', 'super_administrator'],
        true
    );

    if (!$isPlatformSuperAdmin && !rpRolePermissionPageAssigned($assigned)) {
        rpJson(
            false,
            'Roles & Permissions is not assigned to this school by Super Admin.',
            [],
            403
        );
    }

    if ($action === 'list' || $action === 'meta') {
        rpRequire($scope, 'view');
        $data = rpListRoles($pdo, $scope, array_merge($_GET, $input));
        $data['csrf_token'] = $_SESSION['roles_permissions_csrf_token'];
        $data['assigned_sidebar_count'] = count($assigned);
        $data['api_build'] = RP_API_BUILD;
        rpJson(true, 'Roles loaded.', $data);
    }

    if ($action === 'detail') {
        rpRequire($scope, 'view');
        $roleId = (int)($_GET['id'] ?? $input['id'] ?? 0);
        rpJson(true, 'Role details loaded.', [
            'record' => rpRole($pdo, $scope, $roleId),
        ]);
    }

    if ($action === 'save_role') {
        rpCsrf($input);
        $roleId = (int)($input['id'] ?? 0);
        rpRequire($scope, $roleId > 0 ? 'edit' : 'create');

        $roleName = trim((string)($input['role_name'] ?? ''));
        $roleKey = rpSlug((string)($input['role_key'] ?? $roleName));
        $description = trim((string)($input['description'] ?? ''));
        $status = strtolower(trim((string)($input['status'] ?? 'active')));

        if ($roleName === '' || mb_strlen($roleName) > 120) {
            throw new InvalidArgumentException('Enter a valid Role Name.');
        }
        if ($roleKey === '' || strlen($roleKey) > 80) {
            throw new InvalidArgumentException('Enter a valid Role Key.');
        }
        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new InvalidArgumentException('Invalid role status.');
        }

        $duplicateSql = 'SELECT id FROM roles
                         WHERE tenant_id = :tenant_id
                           AND role_key = :role_key';
        if (rpColumn($pdo, 'roles', 'deleted_at')) {
            $duplicateSql .= ' AND deleted_at IS NULL';
        }
        if (rpColumn($pdo, 'roles', 'role_scope')) {
            $duplicateSql .= " AND role_scope = 'school'";
        }
        $duplicateSql .= ' AND (:current_id = 0 OR id <> :exclude_id) LIMIT 1';

        $duplicate = $pdo->prepare($duplicateSql);
        $duplicate->execute([
            'tenant_id' => $scope['tenant_id'],
            'role_key' => $roleKey,
            'current_id' => $roleId,
            'exclude_id' => $roleId,
        ]);

        if ($duplicate->fetchColumn()) {
            throw new InvalidArgumentException('Another role already uses this Role Key.');
        }

        $pdo->beginTransaction();
        try {
            if ($roleId > 0) {
                $existing = rpRole($pdo, $scope, $roleId);
                $protected = rpProtectedRole($existing);

                $stmt = $pdo->prepare(
                    'UPDATE roles
                     SET role_name = :role_name,
                         role_key = :role_key,
                         description = :description,
                         status = :status,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE id = :role_id
                       AND tenant_id = :tenant_id'
                );
                $stmt->execute([
                    'role_name' => $roleName,
                    'role_key' => $protected ? $existing['role_key'] : $roleKey,
                    'description' => $description !== '' ? $description : null,
                    'status' => $protected ? 'active' : $status,
                    'role_id' => $roleId,
                    'tenant_id' => $scope['tenant_id'],
                ]);
                $savedRoleId = $roleId;
                $message = 'Role updated successfully.';
                rpAudit($pdo, $scope, 'role_updated', $savedRoleId, $existing, [
                    'role_name' => $roleName,
                    'role_key' => $roleKey,
                    'status' => $status,
                ]);
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO roles
                        (tenant_id, role_key, role_name, description,
                         role_scope, is_system, status, created_by)
                     VALUES
                        (:tenant_id, :role_key, :role_name, :description,
                         'school', 0, :status, :created_by)"
                );
                $stmt->execute([
                    'tenant_id' => $scope['tenant_id'],
                    'role_key' => $roleKey,
                    'role_name' => $roleName,
                    'description' => $description !== '' ? $description : null,
                    'status' => $status,
                    'created_by' => $scope['user_id'] ?: null,
                ]);
                $savedRoleId = (int)$pdo->lastInsertId();
                $message = 'Role created successfully.';
                rpAudit($pdo, $scope, 'role_created', $savedRoleId, [], [
                    'role_name' => $roleName,
                    'role_key' => $roleKey,
                    'status' => $status,
                ]);
            }

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        rpJson(true, $message, ['role_id' => $savedRoleId]);
    }

    if ($action === 'toggle_status') {
        rpCsrf($input);
        rpRequire($scope, 'edit');
        $role = rpRole($pdo, $scope, (int)($input['id'] ?? 0));

        if (rpProtectedRole($role)) {
            throw new InvalidArgumentException('A protected system role cannot be deactivated.');
        }

        $next = strtolower((string)$role['status']) === 'active'
            ? 'inactive'
            : 'active';

        $stmt = $pdo->prepare(
            'UPDATE roles
             SET status = :status,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :role_id
               AND tenant_id = :tenant_id'
        );
        $stmt->execute([
            'status' => $next,
            'role_id' => $role['id'],
            'tenant_id' => $scope['tenant_id'],
        ]);

        rpAudit($pdo, $scope, 'role_status_changed', (int)$role['id'], [
            'status' => $role['status'],
        ], [
            'status' => $next,
        ]);

        rpJson(
            true,
            'Role ' . ($next === 'active' ? 'activated' : 'deactivated') . ' successfully.'
        );
    }

    if ($action === 'delete') {
        rpCsrf($input);
        rpRequire($scope, 'delete');
        $role = rpRole($pdo, $scope, (int)($input['id'] ?? 0));

        if (rpProtectedRole($role)) {
            throw new InvalidArgumentException('A protected system role cannot be deleted.');
        }
        if ((int)$role['user_count'] > 0) {
            throw new InvalidArgumentException(
                'This role is assigned to users. Reassign those users before deleting the role.'
            );
        }

        $pdo->beginTransaction();
        try {
            if (rpTable($pdo, 'school_sidebar_action_permissions')) {
                $stmt = $pdo->prepare(
                    'DELETE FROM school_sidebar_action_permissions
                     WHERE tenant_id = :tenant_id
                       AND role_id = :role_id'
                );
                $stmt->execute([
                    'tenant_id' => $scope['tenant_id'],
                    'role_id' => $role['id'],
                ]);
            }

            if (rpTable($pdo, 'role_sidebar_permissions')) {
                $stmt = $pdo->prepare(
                    'DELETE FROM role_sidebar_permissions WHERE role_id = :role_id'
                );
                $stmt->execute(['role_id' => $role['id']]);
            }

            if (rpColumn($pdo, 'roles', 'deleted_at')) {
                $stmt = $pdo->prepare(
                    "UPDATE roles
                     SET status = 'inactive',
                         deleted_at = NOW(),
                         updated_at = CURRENT_TIMESTAMP
                     WHERE id = :role_id
                       AND tenant_id = :tenant_id"
                );
            } else {
                $stmt = $pdo->prepare(
                    'DELETE FROM roles
                     WHERE id = :role_id
                       AND tenant_id = :tenant_id'
                );
            }

            $stmt->execute([
                'role_id' => $role['id'],
                'tenant_id' => $scope['tenant_id'],
            ]);

            rpAudit($pdo, $scope, 'role_deleted', (int)$role['id'], $role, []);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        rpJson(true, 'Role deleted successfully.');
    }

    if ($action === 'copy_role') {
        rpCsrf($input);
        rpRequire($scope, 'create');
        $source = rpRole($pdo, $scope, (int)($input['source_role_id'] ?? 0));

        $newName = trim((string)($input['role_name'] ?? ($source['role_name'] . ' Copy')));
        $newKey = rpSlug((string)($input['role_key'] ?? $newName));

        if ($newName === '' || $newKey === '') {
            throw new InvalidArgumentException('Enter a valid copied Role Name and Role Key.');
        }

        $check = $pdo->prepare(
            'SELECT COUNT(*)
             FROM roles
             WHERE tenant_id = :tenant_id
               AND role_key = :role_key
               AND deleted_at IS NULL'
        );
        $check->execute([
            'tenant_id' => $scope['tenant_id'],
            'role_key' => $newKey,
        ]);

        if ((int)$check->fetchColumn() > 0) {
            throw new InvalidArgumentException('Another role already uses the copied Role Key.');
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO roles
                    (tenant_id, role_key, role_name, description,
                     role_scope, is_system, status, created_by)
                 VALUES
                    (:tenant_id, :role_key, :role_name, :description,
                     'school', 0, 'active', :created_by)"
            );
            $stmt->execute([
                'tenant_id' => $scope['tenant_id'],
                'role_key' => $newKey,
                'role_name' => $newName,
                'description' => $source['description'] ?? null,
                'created_by' => $scope['user_id'] ?: null,
            ]);
            $newRoleId = (int)$pdo->lastInsertId();

            $sourceRows = rpPermissionRows(
                $pdo,
                (int)$scope['tenant_id'],
                (int)$source['id']
            );
            $submitted = [];
            foreach ($sourceRows as $sidebarId => $permission) {
                if (!isset($assigned[$sidebarId])) {
                    continue;
                }
                $submitted[] = [
                    'sidebar_item_id' => $sidebarId,
                    ...$permission,
                ];
            }
            rpSaveMatrix(
                $pdo,
                $scope,
                $newRoleId,
                $submitted,
                $assigned,
                false
            );

            rpAudit($pdo, $scope, 'role_copied', $newRoleId, [], [
                'source_role_id' => $source['id'],
                'role_name' => $newName,
            ]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        rpJson(true, 'Role copied successfully.', ['role_id' => $newRoleId]);
    }

    if ($action === 'matrix') {
        rpRequire($scope, 'view');
        $roleId = (int)($_GET['role_id'] ?? $input['role_id'] ?? 0);
        rpJson(true, 'Permission matrix loaded.', rpMatrix(
            $pdo,
            $scope,
            $roleId,
            $assigned
        ));
    }

    if ($action === 'save_permissions') {
        rpCsrf($input);
        rpRequire(
            $scope,
            rpCan($scope, 'manage_settings') ? 'manage_settings' : 'edit'
        );

        $role = rpRole($pdo, $scope, (int)($input['role_id'] ?? 0));
        $rows = is_array($input['items'] ?? null) ? $input['items'] : [];
        $locked = rpProtectedRole($role);

        $pdo->beginTransaction();
        try {
            rpSaveMatrix(
                $pdo,
                $scope,
                (int)$role['id'],
                $rows,
                $assigned,
                $locked
            );
            rpAudit($pdo, $scope, 'permissions_updated', (int)$role['id'], [], [
                'assigned_sidebar_ids' => array_keys($assigned),
                'submitted_items' => $rows,
            ]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        rpJson(
            true,
            $locked
                ? 'Protected School Administrator access synchronized successfully.'
                : 'Role permissions saved successfully.'
        );
    }

    rpJson(false, 'Invalid Roles & Permissions action.', [], 400);
} catch (InvalidArgumentException $exception) {
    rpJson(false, $exception->getMessage(), [], 422);
} catch (PDOException $exception) {
    $reference = 'RP-' . date('Ymd-His') . '-' . substr(
        hash('sha256', $exception->getMessage()),
        0,
        8
    );

    error_log(
        'roles-permissions.php build=' . RP_API_BUILD
        . ' reference=' . $reference
        . ' PDO error: ' . $exception->getMessage()
    );

    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $isLocal = str_contains($host, 'localhost') || str_contains($host, '127.0.0.1');

    rpJson(
        false,
        $isLocal
            ? 'Roles & Permissions database error: ' . $exception->getMessage()
            : 'Database operation failed. Error reference: ' . $reference,
        [
            'error_reference' => $reference,
            'api_build' => RP_API_BUILD,
        ],
        500
    );
} catch (Throwable $exception) {
    error_log(
        'roles-permissions.php build=' . RP_API_BUILD
        . ' action=' . $action
        . ' line=' . $exception->getLine()
        . ' error=' . $exception->getMessage()
    );

    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $isLocal = str_contains($host, 'localhost') || str_contains($host, '127.0.0.1');

    rpJson(
        false,
        $isLocal
            ? 'Roles & Permissions request failed: ' . $exception->getMessage()
            : 'Unable to complete the Roles & Permissions request.',
        ['api_build' => RP_API_BUILD],
        500
    );
}

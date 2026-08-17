<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/sidebar-manager.php';
require_once dirname(__DIR__) . '/includes/permission-chain.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

const RP_API_BUILD = '2026-08-14-super-admin-school-branch-ceiling-v24';

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
        'branch_id' => (int)(
            $user['branch_id']
            ?? $_SESSION['branch_id']
            ?? $_SESSION['default_branch_id']
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
        'school_name' => '',
        'branch_name' => '',
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

    if ($scope['tenant_id'] > 0 && rpTable($pdo, 'tenants')) {
        try {
            $stmt = $pdo->prepare(
                "SELECT school_name
                 FROM tenants
                 WHERE id = :tenant_id
                 LIMIT 1"
            );
            $stmt->execute(['tenant_id' => $scope['tenant_id']]);
            $scope['school_name'] = trim((string)($stmt->fetchColumn() ?: ''));
        } catch (Throwable $exception) {
            error_log('Roles permission school context: ' . $exception->getMessage());
        }
    }

    if ($scope['branch_id'] > 0 && rpTable($pdo, 'branches')) {
        try {
            $stmt = $pdo->prepare(
                "SELECT branch_name
                 FROM branches
                 WHERE id = :branch_id
                   AND tenant_id = :tenant_id
                   AND status = 'active'
                 LIMIT 1"
            );
            $stmt->execute([
                'branch_id' => $scope['branch_id'],
                'tenant_id' => $scope['tenant_id'],
            ]);
            $branchName = $stmt->fetchColumn();

            if ($branchName === false) {
                $scope['branch_id'] = 0;
            } else {
                $scope['branch_name'] = trim((string)$branchName);
            }
        } catch (Throwable $exception) {
            error_log('Roles permission branch context: ' . $exception->getMessage());
            $scope['branch_id'] = 0;
        }
    }

    return $scope;
}

function rpIsAdministrator(array $scope): bool
{
    /*
     * Only the SaaS / Platform Super Admin bypasses this page's permission
     * checks. School Admin must obey the Super Admin school / branch ceiling.
     */
    return in_array(
        $scope['role_key'],
        ['super_admin', 'super-administrator', 'super_administrator'],
        true
    ) || in_array(
        $scope['role_name'],
        ['super administrator', 'super admin'],
        true
    );
}

function rpCan(array $scope, string $action): bool
{
    if (rpIsAdministrator($scope)) {
        return true;
    }

    $action = pc_normalize_action($action);

    /*
     * Use the same runtime permission chain as the School panel. This makes
     * the Roles & Permissions buttons obey the exact Super Admin ceiling for
     * the logged school and branch instead of treating School Admin as an
     * unrestricted user.
     */
    if (function_exists('school_effective_permission')) {
        try {
            return (bool)school_effective_permission(
                'roles_permissions',
                $action,
                (int)$scope['tenant_id'],
                (int)($scope['branch_id'] ?? 0),
                (int)$scope['user_id']
            );
        } catch (Throwable $exception) {
            error_log(
                'Roles permission chain check failed: '
                . $exception->getMessage()
            );
        }
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
    $adminRoleId = pc_role_id($pdo, $tenantId, 'school_admin');

    if ($tenantId <= 0 || $adminRoleId <= 0) {
        return [];
    }

    $runtime = school_sidebar_get_items($pdo, $adminRoleId, $tenantId);
    $out = [];
    $sequence = 0;

    foreach ($runtime as $row) {
        $id = (int)($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }

        /*
         * The School Admin role-permission page must expose only the menu
         * ceiling currently allowed by Super Admin for this school / branch.
         */
        $caps = rpCaps($pdo, $scope, $id);
        if ((int)($caps['view'] ?? 0) !== 1) {
            continue;
        }

        $sequence++;

        $out[$id] = [
            'id' => $id,
            'parent_id' => (int)($row['parent_id'] ?? 0),
            'module_id' => (int)($row['module_id'] ?? 0),
            'menu_key' => (string)($row['menu_key'] ?? ''),
            'menu_title' => (string)(
                $row['display_title']
                ?? $row['menu_title']
                ?? 'Menu'
            ),
            'display_title' => (string)(
                $row['display_title']
                ?? $row['menu_title']
                ?? 'Menu'
            ),
            'display_icon' => (string)(
                $row['display_icon']
                ?? $row['icon']
                ?? 'circle'
            ),
            'effective_route' => (string)($row['route'] ?? '#'),
            'display_order' => (int)($row['display_order'] ?? 0),
            'sidebar_sequence' => $sequence,
            'depth' => (int)($row['depth'] ?? 0),
            'super_admin_caps' => $caps,
        ];
    }

    return $out;
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

function rpVisiblePermissionCount(
    PDO $pdo,
    array $scope,
    int $roleId,
    array $assigned = []
): int {
    if ($roleId <= 0) {
        return 0;
    }

    if ($assigned === []) {
        $assigned = rpAssignedSidebar($pdo, $scope);
    }

    if ($assigned === []) {
        return 0;
    }

    $roleStmt = $pdo->prepare(
        "SELECT role_key
         FROM roles
         WHERE id = :role_id
           AND tenant_id = :tenant_id
         LIMIT 1"
    );
    $roleStmt->execute([
        'role_id' => $roleId,
        'tenant_id' => (int)$scope['tenant_id'],
    ]);
    $roleKey = strtolower(trim((string)($roleStmt->fetchColumn() ?: '')));
    $locked = in_array(
        $roleKey,
        ['school_admin', 'school-administrator', 'school_administrator'],
        true
    );

    $saved = $locked
        ? []
        : rpPermissionRows(
            $pdo,
            (int)$scope['tenant_id'],
            $roleId
        );

    $count = 0;

    foreach ($assigned as $itemId => $item) {
        $caps = is_array($item['super_admin_caps'] ?? null)
            ? $item['super_admin_caps']
            : rpCaps($pdo, $scope, (int)$itemId);

        if ((int)($caps['view'] ?? 0) !== 1) {
            continue;
        }

        if ($locked || (int)($saved[(int)$itemId]['view'] ?? 0) === 1) {
            $count++;
        }
    }

    return $count;
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
        $scope,
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

    $where = [rpRoleWhere($pdo, 'r'), "r.role_key <> 'parent'"];
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
            $scope,
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

    /*
     * Statistics use the same role population shown in this page. Parent is
     * managed separately by Parent Sidebar Permission and is not counted here.
     */
    $statsStmt = $pdo->prepare(
        'SELECT
            COUNT(*) AS total_roles,
            COALESCE(SUM(r.status = \'active\'),0) AS active_roles,
            COALESCE(SUM(r.is_system = 1),0) AS protected_roles,
            COALESCE(SUM(r.is_system = 0),0) AS custom_roles
         FROM roles AS r
         WHERE ' . rpRoleWhere($pdo, 'r') . "
           AND r.role_key <> 'parent'"
    );
    $statsStmt->execute(['tenant_id' => $scope['tenant_id']]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $usersWhere = [
        'u.tenant_id = :tenant_id',
        "r.role_key <> 'parent'",
    ];
    if (rpColumn($pdo, 'users', 'deleted_at')) {
        $usersWhere[] = 'u.deleted_at IS NULL';
    }
    if (rpColumn($pdo, 'roles', 'deleted_at')) {
        $usersWhere[] = 'r.deleted_at IS NULL';
    }

    $usersStmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM users AS u
         INNER JOIN roles AS r
            ON r.id = u.role_id
           AND r.tenant_id = u.tenant_id
         WHERE ' . implode(' AND ', $usersWhere)
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
        'scope' => [
            'school_id' => (int)$scope['tenant_id'],
            'school_name' => (string)($scope['school_name'] ?? ''),
            'branch_id' => (int)($scope['branch_id'] ?? 0),
            'branch_name' => (string)($scope['branch_name'] ?? ''),
        ],
    ];
}

/**
 * @return array<int,array<string,int>> keyed by sidebar item id
 */
function rpActionCatalog(PDO $pdo): array
{
    return pc_action_catalog($pdo);
}

function rpActionKeys(PDO $pdo): array
{
    return array_values(array_unique(array_map(
        static fn(array $row): string => (string)$row['action_key'],
        rpActionCatalog($pdo)
    )));
}

function rpSuperAdminActionAllowed(
    PDO $pdo,
    array $scope,
    int $itemId,
    string $action
): bool {
    $tenantId = (int)$scope['tenant_id'];
    $branchId = (int)($scope['branch_id'] ?? 0);
    $adminRoleId = pc_role_id($pdo, $tenantId, 'school_admin');

    if ($tenantId <= 0 || $adminRoleId <= 0 || $itemId <= 0) {
        return false;
    }

    $action = pc_normalize_action($action);

    /*
     * New branch-aware permission-chain build: read the School ceiling first,
     * then apply the current Branch override. A branch may only reduce the
     * school permission.
     */
    if (function_exists('pc_school_role_action')) {
        $allowed = pc_school_role_action(
            $pdo,
            $tenantId,
            $adminRoleId,
            $itemId,
            $action,
            true
        );

        if (
            $allowed
            && $action === 'view'
            && function_exists('pc_effective_school_visibility')
        ) {
            $allowed = pc_effective_school_visibility(
                $pdo,
                $tenantId,
                $itemId
            );
        }

        if (!$allowed) {
            return false;
        }

        if ($branchId > 0) {
            if (function_exists('pc_branch_role_action_override')) {
                $branchDecision = pc_branch_role_action_override(
                    $pdo,
                    $tenantId,
                    $branchId,
                    $adminRoleId,
                    $itemId,
                    $action
                );

                if ($branchDecision !== null) {
                    return $branchDecision;
                }
            } elseif (rpTable($pdo, 'branch_sidebar_permission_grants')) {
                $stmt = $pdo->prepare(
                    "SELECT is_allowed
                     FROM branch_sidebar_permission_grants
                     WHERE tenant_id = :tenant_id
                       AND branch_id = :branch_id
                       AND role_id = :role_id
                       AND sidebar_item_id = :item_id
                       AND action_key = :action_key
                     LIMIT 1"
                );
                $base = [
                    'tenant_id' => $tenantId,
                    'branch_id' => $branchId,
                    'role_id' => $adminRoleId,
                    'item_id' => $itemId,
                ];

                $stmt->execute(
                    $base + ['action_key' => $action]
                );
                $branchValue = $stmt->fetchColumn();

                if ($branchValue !== false) {
                    return (int)$branchValue === 1;
                }

                if ($action !== 'full_access') {
                    $stmt->execute(
                        $base + ['action_key' => 'full_access']
                    );
                    $full = $stmt->fetchColumn();

                    if ($full !== false) {
                        return (int)$full === 1;
                    }
                }
            }
        }

        return true;
    }

    /*
     * Compatibility with older permission-chain builds. pc_super_cap() is the
     * existing Super Admin ceiling and, when the branch-aware build is
     * installed, already uses the logged branch.
     */
    return pc_super_cap(
        $pdo,
        $tenantId,
        $itemId,
        $action,
        'school_admin'
    );
}

function rpCaps(PDO $pdo, array $scope, int $itemId): array
{
    $caps = [];

    foreach (rpActionKeys($pdo) as $action) {
        $caps[$action] = rpSuperAdminActionAllowed(
            $pdo,
            $scope,
            $itemId,
            $action
        ) ? 1 : 0;
    }

    return $caps;
}

function rpPermissionRows(PDO $pdo, int $tenantId, int $roleId): array
{
    $rows=[];
    if(rpTable($pdo,'school_sidebar_permission_grants')){
        $q=$pdo->prepare("SELECT sidebar_item_id,action_key,is_allowed
            FROM school_sidebar_permission_grants
            WHERE tenant_id=:tenant_id AND role_id=:role_id
              AND action_key NOT LIKE 'parent_delegate\\_%'");
        $q->execute(['tenant_id'=>$tenantId,'role_id'=>$roleId]);
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r){
            $id=(int)$r['sidebar_item_id'];
            $key=pc_normalize_action((string)$r['action_key']);
            if($id>0&&$key!=='')$rows[$id][$key]=(int)$r['is_allowed'];
        }
    }
    if(rpTable($pdo,'school_sidebar_action_permissions')){
        $map=['view'=>'can_view','create'=>'can_add','edit'=>'can_edit','delete'=>'can_delete',
            'print'=>'can_print','pdf'=>'can_pdf','export'=>'can_export','import'=>'can_import',
            'approve'=>'can_approve','reject'=>'can_reject','restore'=>'can_restore',
            'manage_settings'=>'can_manage','manage_visibility'=>'can_manage_visibility'];
        $avail=[];
        foreach($map as $k=>$c) if(rpColumn($pdo,'school_sidebar_action_permissions',$c))$avail[$k]=$c;
        if($avail){
            $q=$pdo->prepare("SELECT ".implode(',',array_unique(['sidebar_item_id',...array_values($avail)]))."
              FROM school_sidebar_action_permissions
              WHERE tenant_id=:tenant_id AND role_id=:role_id");
            $q->execute(['tenant_id'=>$tenantId,'role_id'=>$roleId]);
            foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r){
                $id=(int)$r['sidebar_item_id'];
                foreach($avail as $k=>$c)
                    if(!array_key_exists($k,$rows[$id]??[]))$rows[$id][$k]=(int)($r[$c]??0);
            }
        }
    }
    return $rows;
}

function rpMatrix(PDO $pdo, array $scope, int $roleId, array $assigned): array
{
    $role = rpRole($pdo, $scope, $roleId);

    if (pc_role_key((string)($role['role_key'] ?? '')) === 'parent') {
        throw new RuntimeException(
            'Use Parent Sidebar Permission to manage Parent access.',
            422
        );
    }

    $locked = rpProtectedRole($role);
    $saved = rpPermissionRows(
        $pdo,
        (int)$scope['tenant_id'],
        (int)$role['id']
    );
    $actions = rpActionCatalog($pdo);
    $items = [];

    foreach ($assigned as $id => $item) {
        $caps = is_array($item['super_admin_caps'] ?? null)
            ? $item['super_admin_caps']
            : rpCaps($pdo, $scope, (int)$id);

        /*
         * View=0 means Super Admin did not assign this menu to the current
         * school/branch. Do not expose it as an option at all.
         */
        if ((int)($caps['view'] ?? 0) !== 1) {
            continue;
        }

        $permissions = [];

        foreach ($actions as $actionRow) {
            $key = (string)$actionRow['action_key'];
            $cap = (int)($caps[$key] ?? 0);

            $permissions[$key] = $locked
                ? $cap
                : (
                    $cap === 1
                    && (int)($saved[(int)$id][$key] ?? 0) === 1
                        ? 1
                        : 0
                );
        }

        $items[] = [
            'sidebar_item_id' => (int)$id,
            'parent_id' => (int)($item['parent_id'] ?? 0),
            'module_id' => (int)($item['module_id'] ?? 0),
            'menu_key' => (string)($item['menu_key'] ?? ''),
            'menu_title' => (string)($item['display_title'] ?? 'Menu'),
            'route' => (string)($item['effective_route'] ?? '#'),
            'icon' => (string)($item['display_icon'] ?? 'circle'),
            'display_order' => (int)($item['display_order'] ?? 0),
            'sidebar_sequence' => (int)(
                $item['sidebar_sequence']
                ?? count($items) + 1
            ),
            'depth' => (int)($item['depth'] ?? 0),
            'is_container' => trim(
                (string)($item['effective_route'] ?? '#')
            ) === '#' ? 1 : 0,
            'permissions' => $permissions,
            'caps' => $caps,
            'can_view' => (int)($permissions['view'] ?? 0),
            'can_add' => (int)($permissions['create'] ?? 0),
            'can_edit' => (int)($permissions['edit'] ?? 0),
            'can_delete' => (int)($permissions['delete'] ?? 0),
        ];
    }

    return [
        'role' => $role,
        'items' => $items,
        'actions' => $actions,
        'is_locked' => $locked,
        'assigned_count' => count($items),
        'scope' => [
            'school_id' => (int)$scope['tenant_id'],
            'school_name' => (string)($scope['school_name'] ?? ''),
            'branch_id' => (int)($scope['branch_id'] ?? 0),
            'branch_name' => (string)($scope['branch_name'] ?? ''),
        ],
        'permission_chain' => (int)($scope['branch_id'] ?? 0) > 0
            ? 'Super Admin School Permission → Branch Permission → School Role Permission'
            : 'Super Admin School Permission → School Role Permission',
    ];
}

function rpNormalizeRows(array $submitted, array $assigned, bool $locked, PDO $pdo, array $scope): array
{
    $actions=rpActionKeys($pdo); $out=[];
    foreach($assigned as $id=>$item){
        $caps=rpCaps($pdo,$scope,(int)$id); $out[(int)$id]=[];
        foreach($actions as $a)$out[(int)$id][$a]=$locked?(int)($caps[$a]??0):0;
    }
    if($locked)return $out;

    foreach($submitted as $row){
        if(!is_array($row))continue;
        $id=(int)($row['sidebar_item_id']??0); if(!isset($assigned[$id]))continue;
        $caps=rpCaps($pdo,$scope,$id);
        $p=is_array($row['permissions']??null)?$row['permissions']:[];
        foreach(['view'=>'can_view','create'=>'can_add','edit'=>'can_edit','delete'=>'can_delete'] as $k=>$legacy)
            if(array_key_exists($legacy,$row)&&!array_key_exists($k,$p))$p[$k]=$row[$legacy];
        foreach($actions as $a)$out[$id][$a]=!empty($p[$a])&&!empty($caps[$a])?1:0;
        if(!empty($out[$id]['full_access']))
            foreach($actions as $a)if($a!=='full_access')$out[$id][$a]=!empty($caps[$a])?1:0;
        $any=false;
        foreach($out[$id] as $a=>$v)if(!in_array($a,['view','full_access'],true)&&$v===1){$any=true;break;}
        if($any&&!empty($caps['view']))$out[$id]['view']=1;
        if(empty($out[$id]['view']))foreach($actions as $a)if($a!=='view')$out[$id][$a]=0;
    }

    $children=[];
    foreach($assigned as $id=>$item)$children[(int)($item['parent_id']??0)][]=(int)$id;
    $clear=function(int $pid)use(&$clear,&$out,$children){
        foreach($children[$pid]??[] as $cid){foreach($out[$cid] as $a=>$_)$out[$cid][$a]=0;$clear($cid);}
    };
    foreach($out as $id=>$p)if(empty($p['view']))$clear((int)$id);

    foreach(array_keys($out) as $id){
        if(empty($out[$id]['view']))continue;
        $pid=(int)($assigned[$id]['parent_id']??0);$seen=[];$ok=true;
        while($pid>0&&isset($assigned[$pid])&&!isset($seen[$pid])){
            $seen[$pid]=1;
            if(!pc_super_cap($pdo,(int)$scope['tenant_id'],$pid,'view','school_admin')){$ok=false;break;}
            $out[$pid]['view']=1;$pid=(int)($assigned[$pid]['parent_id']??0);
        }
        if(!$ok)foreach($out[$id] as $a=>$_)$out[$id][$a]=0;
    }
    return $out;
}

function rpSaveMatrix(PDO $pdo,array $scope,int $roleId,array $rows,array $assigned,bool $locked): void
{
    if($locked)return;
    $normalized=rpNormalizeRows($rows,$assigned,false,$pdo,$scope);

    $grant=rpTable($pdo,'school_sidebar_permission_grants')?$pdo->prepare(
        "INSERT INTO school_sidebar_permission_grants
         (tenant_id,role_id,sidebar_item_id,action_key,is_allowed)
         VALUES(:tenant_id,:role_id,:item_id,:action_key,:allowed)
         ON DUPLICATE KEY UPDATE is_allowed=VALUES(is_allowed),updated_at=CURRENT_TIMESTAMP"
    ):null;

    $map=['view'=>'can_view','create'=>'can_add','edit'=>'can_edit','delete'=>'can_delete',
        'print'=>'can_print','pdf'=>'can_pdf','export'=>'can_export','import'=>'can_import',
        'approve'=>'can_approve','reject'=>'can_reject','restore'=>'can_restore',
        'manage_settings'=>'can_manage','manage_visibility'=>'can_manage_visibility'];
    $avail=[];
    if(rpTable($pdo,'school_sidebar_action_permissions'))
        foreach($map as $k=>$c)if(rpColumn($pdo,'school_sidebar_action_permissions',$c))$avail[$k]=$c;

    $matrix=null;
    if($avail){
        $cols=['tenant_id','role_id','sidebar_item_id',...array_values($avail)];
        $vals=[':tenant_id',':role_id',':item_id',...array_map(fn($k)=>':'.$k,array_keys($avail))];
        $ups=array_map(fn($c)=>$c.'=VALUES('.$c.')',array_values($avail));
        $matrix=$pdo->prepare("INSERT INTO school_sidebar_action_permissions(".implode(',',$cols).")
         VALUES(".implode(',',$vals).") ON DUPLICATE KEY UPDATE ".implode(',',$ups).",updated_at=CURRENT_TIMESTAMP");
    }
    $legacy=rpTable($pdo,'role_sidebar_permissions')?$pdo->prepare(
        "INSERT INTO role_sidebar_permissions(role_id,sidebar_item_id,can_show)
         VALUES(:role_id,:item_id,:can_show)
         ON DUPLICATE KEY UPDATE can_show=VALUES(can_show),updated_at=CURRENT_TIMESTAMP"
    ):null;

    foreach($normalized as $itemId=>$p){
        if($grant)foreach($p as $action=>$allowed)$grant->execute([
            'tenant_id'=>$scope['tenant_id'],'role_id'=>$roleId,'item_id'=>$itemId,
            'action_key'=>$action,'allowed'=>$allowed
        ]);
        if($matrix){
            $params=['tenant_id'=>$scope['tenant_id'],'role_id'=>$roleId,'item_id'=>$itemId];
            foreach($avail as $k=>$c)$params[$k]=(int)($p[$k]??0);
            $matrix->execute($params);
        }
        if($legacy)$legacy->execute(['role_id'=>$roleId,'item_id'=>$itemId,'can_show'=>(int)($p['view']??0)]);
    }
    school_sidebar_service_bump_version($pdo,(int)$scope['tenant_id']);
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

            school_sidebar_service_ensure_role_master(
                $pdo,
                $protected ?? false
                    ? (string)($existing['role_key'] ?? $roleKey)
                    : $roleKey,
                (int)$scope['user_id'] ?: null
            );

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

            school_sidebar_service_ensure_role_master(
                $pdo,
                $newKey,
                (int)$scope['user_id'] ?: null
            );

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
                    'permissions' => $permission,
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
                ? 'School Administrator permissions are controlled by Super Admin and were not changed.'
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

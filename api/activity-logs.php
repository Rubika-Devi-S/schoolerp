<?php
declare(strict_types=1);

/**
 * School Activity Logs API
 *
 * Strict hierarchy:
 * School (tenant) -> Branch -> Role -> User -> Activity
 *
 * `tenant_id` is the canonical School ID in this ERP.  The API also returns
 * the same value as `school_id` so the School/Branch ownership is explicit.
 */

define('SCHOOL_API_PAGE_KEY', 'activity_logs');

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

const SCHOOL_ACTIVITY_LOGS_BUILD = '2026-08-15-school-branch-role-user-v4';
const SCHOOL_ACTIVITY_LOGS_MAX_EXPORT = 50000;

/**
 * @param array<string,mixed> $data
 */
function alJson(
    bool $success,
    string $message = '',
    array $data = [],
    int $status = 200
): never {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
    }

    echo json_encode(
        [
            'success' => $success,
            'message' => $message,
            'data' => $data,
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function alTableExists(PDO $pdo, string $table): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = ?'
    );
    $statement->execute([$table]);

    return (int)$statement->fetchColumn() > 0;
}

/**
 * @return array<string,mixed>
 */
function alScope(PDO $pdo): array
{
    $user = function_exists('current_user') ? current_user() : [];
    $user = is_array($user) ? $user : [];

    $tenantId = (int)(
        $user['tenant_id']
        ?? $user['school_id']
        ?? $_SESSION['tenant_id']
        ?? $_SESSION['school_id']
        ?? 0
    );

    $branchId = (int)(
        $user['branch_id']
        ?? $user['default_branch_id']
        ?? $_SESSION['branch_id']
        ?? $_SESSION['default_branch_id']
        ?? 0
    );

    $userId = (int)(
        $user['id']
        ?? $user['user_id']
        ?? $_SESSION['user_id']
        ?? 0
    );

    $roleId = (int)(
        $user['role_id']
        ?? $_SESSION['role_id']
        ?? 0
    );

    $roleKey = strtolower(trim((string)(
        $user['role_key']
        ?? $_SESSION['role_key']
        ?? ''
    )));

    if ($roleId > 0 && $tenantId > 0 && alTableExists($pdo, 'roles')) {
        try {
            $statement = $pdo->prepare(
                "SELECT role_key
                 FROM roles
                 WHERE id = :role_id
                   AND tenant_id = :tenant_id
                   AND role_scope = 'school'
                   AND deleted_at IS NULL
                 LIMIT 1"
            );
            $statement->execute([
                'role_id' => $roleId,
                'tenant_id' => $tenantId,
            ]);
            $dbRoleKey = $statement->fetchColumn();
            if ($dbRoleKey !== false) {
                $roleKey = strtolower(trim((string)$dbRoleKey));
            }
        } catch (Throwable $exception) {
            error_log('Activity Logs scope role lookup: ' . $exception->getMessage());
        }
    }

    return [
        'tenant_id' => $tenantId,
        'school_id' => $tenantId,
        'branch_id' => $branchId,
        'user_id' => $userId,
        'role_id' => $roleId,
        'role_key' => str_replace('-', '_', $roleKey),
    ];
}

function alString(array $input, string $key, int $maxLength = 255): string
{
    $value = trim((string)($input[$key] ?? ''));
    if ($maxLength > 0 && strlen($value) > $maxLength) {
        $value = substr($value, 0, $maxLength);
    }
    return $value;
}

function alPositiveInt(mixed $value, int $default = 0): int
{
    $number = filter_var($value, FILTER_VALIDATE_INT);
    return $number !== false && $number > 0 ? (int)$number : $default;
}

function alValidDate(string $value): string
{
    if ($value === '') {
        return '';
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $value : '';
}

function alActionLabel(string $actionKey): string
{
    $key = strtolower(trim($actionKey));
    if ($key === '') {
        return '-';
    }

    $labels = [
        'create' => 'Create',
        'add' => 'Add',
        'insert' => 'Create',
        'update' => 'Update',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'remove' => 'Remove',
        'view' => 'View',
        'list' => 'View List',
        'detail' => 'View Details',
        'save' => 'Save',
        'approve' => 'Approve',
        'reject' => 'Reject',
        'restore' => 'Restore',
        'login' => 'Login',
        'logout' => 'Logout',
        'print' => 'Print',
        'pdf' => 'PDF',
        'export' => 'Export',
        'import' => 'Import',
        'download' => 'Download',
        'upload' => 'Upload',
        'create_academic_year' => 'Create Academic Year',
        'update_academic_year' => 'Update Academic Year',
        'delete_academic_year' => 'Delete Academic Year',
        'school_sidebar_saved' => 'Save School Sidebar',
        'school_sidebar_edited' => 'Edit School Sidebar',
        'school_sidebar_added' => 'Add School Sidebar',
        'sidebar_options_saved' => 'Save Sidebar Options',
        'permissions_updated' => 'Update Permissions',
        'role_created' => 'Create Role',
        'role_updated' => 'Update Role',
        'role_deleted' => 'Delete Role',
        'role_status_changed' => 'Change Role Status',
        'parent_sidebar_saved' => 'Save Parent Sidebar',
        'student_promoted' => 'Promote Student',
    ];

    return $labels[$key] ?? ucwords(str_replace(['_', '-'], ' ', $key));
}

function alActionGroup(string $actionKey): string
{
    $key = strtolower(trim($actionKey));

    if (preg_match('/create|add|insert|register|upload/', $key)) {
        return 'create';
    }
    if (preg_match('/delete|remove|trash|cancel/', $key)) {
        return 'delete';
    }
    if (preg_match('/login|sign_in/', $key)) {
        return 'login';
    }
    if (preg_match('/view|list|detail|print|export|download|pdf/', $key)) {
        return 'view';
    }

    return 'update';
}

function alActorJoins(): string
{
    return "
        LEFT JOIN users u
               ON u.id = al.user_id
              AND u.tenant_id = al.tenant_id
        LEFT JOIN roles logged_role
               ON logged_role.id = al.role_id
              AND logged_role.tenant_id = al.tenant_id
        LEFT JOIN roles user_role
               ON user_role.id = u.role_id
              AND user_role.tenant_id = al.tenant_id
        LEFT JOIN roles effective_role
               ON effective_role.id = COALESCE(user_role.id, logged_role.id)
              AND effective_role.tenant_id = al.tenant_id
        LEFT JOIN branches b
               ON b.id = COALESCE(al.branch_id, u.default_branch_id)
              AND b.tenant_id = al.tenant_id
    ";
}

/**
 * @param array<string,mixed> $scope
 * @return array<int,array<string,mixed>>
 */
function alAccessibleBranches(PDO $pdo, array $scope): array
{
    $tenantId = (int)$scope['tenant_id'];
    $userId = (int)$scope['user_id'];
    $currentBranchId = (int)$scope['branch_id'];

    if ($tenantId <= 0 || $userId <= 0 || !alTableExists($pdo, 'branches')) {
        return [];
    }

    $branches = [];

    if (alTableExists($pdo, 'user_branch_access')) {
        $statement = $pdo->prepare(
            "SELECT DISTINCT b.id, b.branch_name, b.branch_code, b.is_main
             FROM user_branch_access uba
             INNER JOIN branches b
                     ON b.id = uba.branch_id
                    AND b.tenant_id = :tenant_id
                    AND b.status = 'active'
             WHERE uba.user_id = :user_id
               AND uba.can_access = 1
             ORDER BY b.is_main DESC, b.branch_name, b.id"
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
        ]);
        $branches = $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    if (!$branches && $currentBranchId > 0) {
        $statement = $pdo->prepare(
            "SELECT id, branch_name, branch_code, is_main
             FROM branches
             WHERE tenant_id = :tenant_id
               AND id = :branch_id
               AND status = 'active'
             LIMIT 1"
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'branch_id' => $currentBranchId,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $branches[] = $row;
        }
    }

    if (!$branches) {
        $statement = $pdo->prepare(
            "SELECT id, branch_name, branch_code, is_main
             FROM branches
             WHERE tenant_id = :tenant_id
               AND status = 'active'
             ORDER BY is_main DESC, branch_name, id
             LIMIT 1"
        );
        $statement->execute(['tenant_id' => $tenantId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $branches[] = $row;
        }
    }

    return $branches;
}

/**
 * @param array<string,mixed> $input
 * @param array<string,mixed> $scope
 */
function alResolveBranch(PDO $pdo, array $input, array $scope): int
{
    $branches = alAccessibleBranches($pdo, $scope);
    if (!$branches) {
        throw new RuntimeException('No active branch is available for this school user.', 403);
    }

    $allowed = [];
    foreach ($branches as $branch) {
        $allowed[(int)$branch['id']] = true;
    }

    $requested = alPositiveInt($input['branch_id'] ?? 0);
    if ($requested > 0) {
        if (!isset($allowed[$requested])) {
            throw new RuntimeException('The selected branch is not available for this school user.', 403);
        }
        return $requested;
    }

    $scopeBranchId = (int)$scope['branch_id'];
    if ($scopeBranchId > 0 && isset($allowed[$scopeBranchId])) {
        return $scopeBranchId;
    }

    return (int)$branches[0]['id'];
}

/**
 * @param array<string,mixed> $input
 * @param array<string,mixed> $scope
 * @return array{where:string,params:array<string,mixed>,branch_id:int}
 */
function alBuildWhere(PDO $pdo, array $input, array $scope): array
{
    $branchId = alResolveBranch($pdo, $input, $scope);

    $clauses = [
        'al.tenant_id = :tenant_id',
        'COALESCE(al.branch_id, u.default_branch_id) = :branch_id',
        "effective_role.role_scope = 'school'",
        "effective_role.status = 'active'",
        'effective_role.deleted_at IS NULL',
    ];
    $params = [
        'tenant_id' => (int)$scope['tenant_id'],
        'branch_id' => $branchId,
    ];

    $roleId = alPositiveInt($input['role_id'] ?? 0);
    if ($roleId > 0) {
        $clauses[] = 'effective_role.id = :role_id';
        $params['role_id'] = $roleId;
    }

    $userId = alPositiveInt($input['user_id'] ?? 0);
    if ($userId > 0) {
        $clauses[] = 'al.user_id = :user_id';
        $params['user_id'] = $userId;
    }

    $module = alString($input, 'module', 120);
    if ($module !== '') {
        $clauses[] = 'al.module_name = :module_name';
        $params['module_name'] = $module;
    }

    $actionKey = alString($input, 'action_key', 80);
    if ($actionKey !== '') {
        $clauses[] = 'al.action_key = :action_key';
        $params['action_key'] = $actionKey;
    }

    $dateFrom = alValidDate(alString($input, 'date_from', 10));
    if ($dateFrom !== '') {
        $clauses[] = 'al.created_at >= :date_from';
        $params['date_from'] = $dateFrom . ' 00:00:00';
    }

    $dateTo = alValidDate(alString($input, 'date_to', 10));
    if ($dateTo !== '') {
        $clauses[] = 'al.created_at < DATE_ADD(:date_to, INTERVAL 1 DAY)';
        $params['date_to'] = $dateTo . ' 00:00:00';
    }

    $search = alString($input, 'search', 180);
    if ($search !== '') {
        $like = '%' . $search . '%';
        $searchClauses = [
            'al.module_name LIKE :q1',
            'al.action_key LIKE :q2',
            'COALESCE(al.description, \'\') LIKE :q3',
            'COALESCE(al.table_name, \'\') LIKE :q4',
            'COALESCE(al.ip_address, \'\') LIKE :q5',
            'COALESCE(u.name, \'\') LIKE :q6',
            'COALESCE(u.username, \'\') LIKE :q7',
            'COALESCE(u.email, \'\') LIKE :q8',
            'COALESCE(effective_role.role_name, \'\') LIKE :q9',
            'COALESCE(b.branch_name, \'\') LIKE :q10',
            'CAST(COALESCE(al.record_id, 0) AS CHAR) LIKE :q11',
        ];
        $clauses[] = '(' . implode(' OR ', $searchClauses) . ')';
        for ($index = 1; $index <= 11; $index++) {
            $params['q' . $index] = $like;
        }
    }

    return [
        'where' => implode(' AND ', $clauses),
        'params' => $params,
        'branch_id' => $branchId,
    ];
}

/**
 * @param array<string,mixed> $params
 */
function alBind(PDOStatement $statement, array $params): void
{
    foreach ($params as $key => $value) {
        $statement->bindValue(
            ':' . $key,
            $value,
            is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
        );
    }
}

/**
 * @param array<string,mixed> $input
 * @param array<string,mixed> $scope
 * @return array<string,mixed>
 */
function alList(PDO $pdo, array $input, array $scope): array
{
    $page = max(1, alPositiveInt($input['page'] ?? 1, 1));
    $allowedPerPage = [10, 20, 50, 100];
    $perPage = alPositiveInt($input['per_page'] ?? 20, 20);
    if (!in_array($perPage, $allowedPerPage, true)) {
        $perPage = 20;
    }

    $filter = alBuildWhere($pdo, $input, $scope);
    $joins = alActorJoins();

    $countStatement = $pdo->prepare(
        "SELECT COUNT(*)
         FROM activity_logs al
         {$joins}
         WHERE {$filter['where']}"
    );
    alBind($countStatement, $filter['params']);
    $countStatement->execute();
    $total = (int)$countStatement->fetchColumn();

    $totalPages = max(1, (int)ceil($total / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;

    $summaryStatement = $pdo->prepare(
        "SELECT
            COUNT(*) AS filtered_total,
            SUM(CASE WHEN DATE(al.created_at) = CURRENT_DATE THEN 1 ELSE 0 END) AS today_total,
            COUNT(DISTINCT al.user_id) AS user_total,
            COUNT(DISTINCT al.module_name) AS module_total
         FROM activity_logs al
         {$joins}
         WHERE {$filter['where']}"
    );
    alBind($summaryStatement, $filter['params']);
    $summaryStatement->execute();
    $summary = $summaryStatement->fetch(PDO::FETCH_ASSOC) ?: [];

    $statement = $pdo->prepare(
        "SELECT
            al.id,
            al.tenant_id AS school_id,
            t.school_name,
            COALESCE(al.branch_id, u.default_branch_id) AS branch_id,
            al.user_id,
            effective_role.id AS role_id,
            al.module_name,
            al.action_key,
            al.table_name,
            al.record_id,
            al.description,
            al.ip_address,
            al.created_at,
            COALESCE(NULLIF(u.name, ''), NULLIF(u.username, ''), 'System') AS user_name,
            COALESCE(NULLIF(u.username, ''), '') AS username,
            COALESCE(NULLIF(effective_role.role_name, ''), 'School Role') AS role_name,
            COALESCE(NULLIF(b.branch_name, ''), 'Unknown Branch') AS branch_name,
            COALESCE(NULLIF(b.branch_code, ''), '') AS branch_code,
            CASE
                WHEN COALESCE(al.old_values, '') <> '' OR COALESCE(al.new_values, '') <> '' THEN 1
                ELSE 0
            END AS has_changes
         FROM activity_logs al
         {$joins}
         INNER JOIN tenants t
                 ON t.id = al.tenant_id
         WHERE {$filter['where']}
         ORDER BY al.created_at DESC, al.id DESC
         LIMIT :limit OFFSET :offset"
    );
    alBind($statement, $filter['params']);
    $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
    $statement->execute();

    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['action_label'] = alActionLabel((string)($row['action_key'] ?? ''));
        $row['action_group'] = alActionGroup((string)($row['action_key'] ?? ''));
    }
    unset($row);

    return [
        'school_id' => (int)$scope['tenant_id'],
        'branch_id' => (int)$filter['branch_id'],
        'rows' => $rows,
        'latest_id' => $rows ? (int)($rows[0]['id'] ?? 0) : 0,
        'server_time' => date('Y-m-d H:i:s'),
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
            'from' => $total > 0 ? $offset + 1 : 0,
            'to' => min($offset + $perPage, $total),
        ],
        'summary' => [
            'filtered_total' => (int)($summary['filtered_total'] ?? 0),
            'today_total' => (int)($summary['today_total'] ?? 0),
            'user_total' => (int)($summary['user_total'] ?? 0),
            'module_total' => (int)($summary['module_total'] ?? 0),
        ],
    ];
}

/**
 * @param array<string,mixed> $input
 * @param array<string,mixed> $scope
 * @return array<string,mixed>
 */
function alMeta(PDO $pdo, array $input, array $scope): array
{
    $tenantId = (int)$scope['tenant_id'];
    $selectedBranchId = alResolveBranch($pdo, $input, $scope);
    $selectedRoleId = alPositiveInt($input['role_id'] ?? 0);
    $selectedUserId = alPositiveInt($input['user_id'] ?? 0);

    $schoolStatement = $pdo->prepare(
        "SELECT id, tenant_code, school_name
         FROM tenants
         WHERE id = :tenant_id
         LIMIT 1"
    );
    $schoolStatement->execute(['tenant_id' => $tenantId]);
    $school = $schoolStatement->fetch(PDO::FETCH_ASSOC) ?: [
        'id' => $tenantId,
        'tenant_code' => '',
        'school_name' => 'Current School',
    ];

    $branches = alAccessibleBranches($pdo, $scope);

    $branchUserCondition = "(
        u.default_branch_id = :selected_branch_id
        OR EXISTS (
            SELECT 1
            FROM user_branch_access uba_user
            WHERE uba_user.user_id = u.id
              AND uba_user.branch_id = :selected_branch_id_access
              AND uba_user.can_access = 1
        )
    )";

    if (!alTableExists($pdo, 'user_branch_access')) {
        $branchUserCondition = 'u.default_branch_id = :selected_branch_id';
    }

    $roleSql = "SELECT DISTINCT
                    r.id AS value,
                    r.role_name AS label,
                    r.role_key
                FROM users u
                INNER JOIN roles r
                        ON r.id = u.role_id
                       AND r.tenant_id = u.tenant_id
                WHERE u.tenant_id = :tenant_id
                  AND u.status = 'active'
                  AND u.deleted_at IS NULL
                  AND r.role_scope = 'school'
                  AND r.status = 'active'
                  AND r.deleted_at IS NULL
                  AND {$branchUserCondition}
                ORDER BY r.role_name, r.id";
    $roleStatement = $pdo->prepare($roleSql);
    $roleParams = [
        'tenant_id' => $tenantId,
        'selected_branch_id' => $selectedBranchId,
    ];
    if (str_contains($branchUserCondition, ':selected_branch_id_access')) {
        $roleParams['selected_branch_id_access'] = $selectedBranchId;
    }
    $roleStatement->execute($roleParams);
    $roles = $roleStatement->fetchAll(PDO::FETCH_ASSOC);

    $allowedRoleIds = array_map(static fn(array $row): int => (int)$row['value'], $roles);
    if ($selectedRoleId > 0 && !in_array($selectedRoleId, $allowedRoleIds, true)) {
        $selectedRoleId = 0;
    }

    $userSql = "SELECT DISTINCT
                    u.id AS value,
                    COALESCE(NULLIF(u.name, ''), NULLIF(u.username, ''), CONCAT('User #', u.id)) AS label,
                    u.username,
                    u.role_id
                FROM users u
                INNER JOIN roles r
                        ON r.id = u.role_id
                       AND r.tenant_id = u.tenant_id
                WHERE u.tenant_id = :tenant_id
                  AND u.status = 'active'
                  AND u.deleted_at IS NULL
                  AND r.role_scope = 'school'
                  AND r.status = 'active'
                  AND r.deleted_at IS NULL
                  AND {$branchUserCondition}";
    $userParams = [
        'tenant_id' => $tenantId,
        'selected_branch_id' => $selectedBranchId,
    ];
    if (str_contains($branchUserCondition, ':selected_branch_id_access')) {
        $userParams['selected_branch_id_access'] = $selectedBranchId;
    }
    if ($selectedRoleId > 0) {
        $userSql .= ' AND u.role_id = :selected_role_id';
        $userParams['selected_role_id'] = $selectedRoleId;
    }
    $userSql .= ' ORDER BY label, u.id';

    $userStatement = $pdo->prepare($userSql);
    $userStatement->execute($userParams);
    $users = $userStatement->fetchAll(PDO::FETCH_ASSOC);

    $allowedUserIds = array_map(static fn(array $row): int => (int)$row['value'], $users);
    if ($selectedUserId > 0 && !in_array($selectedUserId, $allowedUserIds, true)) {
        $selectedUserId = 0;
    }

    $metaInput = [
        'branch_id' => $selectedBranchId,
        'role_id' => $selectedRoleId,
        'user_id' => $selectedUserId,
    ];
    $filter = alBuildWhere($pdo, $metaInput, $scope);
    $joins = alActorJoins();

    $moduleStatement = $pdo->prepare(
        "SELECT DISTINCT al.module_name AS value, al.module_name AS label
         FROM activity_logs al
         {$joins}
         WHERE {$filter['where']}
           AND al.module_name <> ''
         ORDER BY al.module_name"
    );
    alBind($moduleStatement, $filter['params']);
    $moduleStatement->execute();
    $modules = $moduleStatement->fetchAll(PDO::FETCH_ASSOC);

    $actionStatement = $pdo->prepare(
        "SELECT DISTINCT al.action_key AS value, al.action_key AS label
         FROM activity_logs al
         {$joins}
         WHERE {$filter['where']}
           AND al.action_key <> ''
         ORDER BY al.action_key"
    );
    alBind($actionStatement, $filter['params']);
    $actionStatement->execute();
    $actions = $actionStatement->fetchAll(PDO::FETCH_ASSOC);
    foreach ($actions as &$action) {
        $action['label'] = alActionLabel((string)$action['value']);
    }
    unset($action);

    $rangeStatement = $pdo->prepare(
        "SELECT
            MIN(DATE(al.created_at)) AS min_date,
            MAX(DATE(al.created_at)) AS max_date,
            COUNT(*) AS total_logs
         FROM activity_logs al
         {$joins}
         WHERE {$filter['where']}"
    );
    alBind($rangeStatement, $filter['params']);
    $rangeStatement->execute();
    $range = $rangeStatement->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'school' => [
            'id' => (int)$school['id'],
            'school_id' => (int)$school['id'],
            'tenant_code' => (string)($school['tenant_code'] ?? ''),
            'school_name' => (string)($school['school_name'] ?? 'Current School'),
        ],
        'branches' => $branches,
        'selected_branch_id' => $selectedBranchId,
        'roles' => $roles,
        'selected_role_id' => $selectedRoleId,
        'users' => $users,
        'selected_user_id' => $selectedUserId,
        'modules' => $modules,
        'actions' => $actions,
        'date_range' => [
            'min' => (string)($range['min_date'] ?? ''),
            'max' => (string)($range['max_date'] ?? ''),
        ],
        'total_logs' => (int)($range['total_logs'] ?? 0),
        'can_change_branch' => count($branches) > 1,
        'current_branch_id' => (int)$scope['branch_id'],
        'hierarchy' => 'School -> Branch -> Role -> User -> Activity',
        'live_refresh_seconds' => 5,
        'build' => SCHOOL_ACTIVITY_LOGS_BUILD,
    ];
}

/**
 * @param array<string,mixed> $input
 * @param array<string,mixed> $scope
 * @return array<string,mixed>
 */
function alDetail(PDO $pdo, int $id, array $input, array $scope): array
{
    if ($id <= 0) {
        throw new InvalidArgumentException('Activity log ID is invalid.');
    }

    $selectedBranchId = alResolveBranch($pdo, $input, $scope);
    $joins = alActorJoins();

    $statement = $pdo->prepare(
        "SELECT
            al.*,
            al.tenant_id AS school_id,
            t.school_name,
            COALESCE(al.branch_id, u.default_branch_id) AS effective_branch_id,
            effective_role.id AS effective_role_id,
            COALESCE(NULLIF(u.name, ''), NULLIF(u.username, ''), 'System') AS user_name,
            COALESCE(NULLIF(u.username, ''), '') AS username,
            COALESCE(NULLIF(u.email, ''), '') AS user_email,
            COALESCE(NULLIF(effective_role.role_name, ''), 'School Role') AS role_name,
            COALESCE(NULLIF(b.branch_name, ''), 'Unknown Branch') AS branch_name,
            COALESCE(NULLIF(b.branch_code, ''), '') AS branch_code
         FROM activity_logs al
         {$joins}
         INNER JOIN tenants t
                 ON t.id = al.tenant_id
         WHERE al.id = :id
           AND al.tenant_id = :tenant_id
           AND COALESCE(al.branch_id, u.default_branch_id) = :branch_id
           AND effective_role.role_scope = 'school'
           AND effective_role.status = 'active'
           AND effective_role.deleted_at IS NULL
         LIMIT 1"
    );
    $statement->execute([
        'id' => $id,
        'tenant_id' => (int)$scope['tenant_id'],
        'branch_id' => $selectedBranchId,
    ]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new RuntimeException('Activity log was not found in the selected school/branch.', 404);
    }

    $row['branch_id'] = (int)($row['effective_branch_id'] ?? 0);
    $row['action_label'] = alActionLabel((string)($row['action_key'] ?? ''));
    $row['action_group'] = alActionGroup((string)($row['action_key'] ?? ''));

    return $row;
}

function alCsvSafe(mixed $value): string
{
    $text = (string)($value ?? '');
    $text = str_replace(["\r\n", "\r"], "\n", $text);

    if ($text !== '' && in_array($text[0], ['=', '+', '-', '@'], true)) {
        $text = "'" . $text;
    }

    return $text;
}

/**
 * @param array<string,mixed> $input
 * @param array<string,mixed> $scope
 */
function alExport(PDO $pdo, array $input, array $scope): never
{
    $filter = alBuildWhere($pdo, $input, $scope);
    $joins = alActorJoins();

    $statement = $pdo->prepare(
        "SELECT
            al.id,
            al.tenant_id AS school_id,
            t.school_name,
            COALESCE(al.branch_id, u.default_branch_id) AS branch_id,
            COALESCE(NULLIF(b.branch_name, ''), 'Unknown Branch') AS branch_name,
            COALESCE(NULLIF(b.branch_code, ''), '') AS branch_code,
            al.created_at,
            COALESCE(NULLIF(effective_role.role_name, ''), 'School Role') AS role_name,
            COALESCE(NULLIF(u.name, ''), NULLIF(u.username, ''), 'System') AS user_name,
            COALESCE(NULLIF(u.username, ''), '') AS username,
            al.module_name,
            al.action_key,
            al.table_name,
            al.record_id,
            al.description,
            al.ip_address,
            al.old_values,
            al.new_values,
            al.user_agent
         FROM activity_logs al
         {$joins}
         INNER JOIN tenants t
                 ON t.id = al.tenant_id
         WHERE {$filter['where']}
         ORDER BY al.created_at DESC, al.id DESC
         LIMIT " . SCHOOL_ACTIVITY_LOGS_MAX_EXPORT
    );
    alBind($statement, $filter['params']);
    $statement->execute();

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $fileName = 'school-branch-activity-logs-' . date('Y-m-d-His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    $output = fopen('php://output', 'wb');
    if ($output === false) {
        throw new RuntimeException('Unable to create the export file.');
    }

    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, [
        'Log ID',
        'School ID',
        'School',
        'Branch ID',
        'Branch',
        'Branch Code',
        'Role',
        'User',
        'Username',
        'Date & Time',
        'Module',
        'Action',
        'Table',
        'Record ID',
        'Description',
        'IP Address',
        'Old Values',
        'New Values',
        'User Agent',
    ]);

    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, array_map('alCsvSafe', [
            $row['id'],
            $row['school_id'],
            $row['school_name'],
            $row['branch_id'],
            $row['branch_name'],
            $row['branch_code'],
            $row['role_name'],
            $row['user_name'],
            $row['username'],
            $row['created_at'],
            $row['module_name'],
            alActionLabel((string)$row['action_key']),
            $row['table_name'],
            $row['record_id'],
            $row['description'],
            $row['ip_address'],
            $row['old_values'],
            $row['new_values'],
            $row['user_agent'],
        ]));
    }

    fclose($output);
    exit;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    alJson(false, 'Database connection is unavailable.', ['build' => SCHOOL_ACTIVITY_LOGS_BUILD], 500);
}

foreach (['activity_logs', 'tenants', 'branches', 'users', 'roles'] as $requiredTable) {
    if (!alTableExists($pdo, $requiredTable)) {
        alJson(false, "Required table {$requiredTable} is missing.", ['build' => SCHOOL_ACTIVITY_LOGS_BUILD], 500);
    }
}

$scope = alScope($pdo);
if ((int)$scope['tenant_id'] <= 0 || (int)$scope['user_id'] <= 0) {
    alJson(false, 'School or user session is missing.', ['build' => SCHOOL_ACTIVITY_LOGS_BUILD], 401);
}

$input = array_merge($_GET, $_POST);
$action = strtolower(trim((string)($input['action'] ?? 'list')));

try {
    if ($action === 'meta') {
        alJson(true, 'School/branch activity hierarchy loaded.', alMeta($pdo, $input, $scope));
    }

    if ($action === 'list') {
        alJson(true, 'Branch activity logs loaded.', alList($pdo, $input, $scope));
    }

    if ($action === 'detail' || $action === 'view') {
        $id = alPositiveInt($input['id'] ?? 0);
        alJson(true, 'Activity log loaded.', [
            'log' => alDetail($pdo, $id, $input, $scope),
            'build' => SCHOOL_ACTIVITY_LOGS_BUILD,
        ]);
    }

    if ($action === 'export') {
        alExport($pdo, $input, $scope);
    }

    alJson(false, 'Activity Logs action is invalid.', ['build' => SCHOOL_ACTIVITY_LOGS_BUILD], 400);
} catch (InvalidArgumentException $exception) {
    alJson(false, $exception->getMessage(), ['build' => SCHOOL_ACTIVITY_LOGS_BUILD], 422);
} catch (RuntimeException $exception) {
    $status = $exception->getCode();
    if ($status < 400 || $status > 599) {
        $status = 500;
    }
    alJson(false, $exception->getMessage(), ['build' => SCHOOL_ACTIVITY_LOGS_BUILD], $status);
} catch (PDOException $exception) {
    error_log('School Activity Logs [' . SCHOOL_ACTIVITY_LOGS_BUILD . ']: ' . $exception->getMessage());

    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $local = str_contains($host, 'localhost') || str_contains($host, '127.0.0.1');

    alJson(
        false,
        $local
            ? 'Activity Logs database error: ' . $exception->getMessage()
            : 'Unable to load School Activity Logs.',
        ['build' => SCHOOL_ACTIVITY_LOGS_BUILD],
        500
    );
} catch (Throwable $exception) {
    error_log('School Activity Logs [' . SCHOOL_ACTIVITY_LOGS_BUILD . ']: ' . $exception->getMessage());
    alJson(false, 'Activity Logs request failed.', ['build' => SCHOOL_ACTIVITY_LOGS_BUILD], 500);
}

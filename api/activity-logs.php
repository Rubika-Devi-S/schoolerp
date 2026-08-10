<?php
declare(strict_types=1);

/**
 * School Activity Logs API
 *
 * Read-only audit viewer for the current school/tenant.
 * The shared Bootstrap permission guard maps this API to the
 * `activity_logs` sidebar permission through SCHOOL_API_PAGE_KEY.
 */

define('SCHOOL_API_PAGE_KEY', 'activity_logs');

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

const SCHOOL_ACTIVITY_LOGS_BUILD = '2026-08-06-school-activity-logs-school-roles-v2';
const SCHOOL_ACTIVITY_LOGS_MAX_EXPORT = 50000;

/**
 * @param array<string,mixed> $data
 */
function alJson(
    bool $success,
    string $message = '',
    array $data = [],
    int $status = 200
): void {
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
    $roleScope = strtolower(trim((string)($user['role_scope'] ?? '')));
    $isSystemRole = (int)($user['is_system'] ?? 0) === 1;

    if ($roleId > 0 && alTableExists($pdo, 'roles')) {
        try {
            $statement = $pdo->prepare(
                "SELECT role_key, role_scope, is_system
                 FROM roles
                 WHERE id = :role_id
                   AND status = 'active'
                   AND deleted_at IS NULL
                 LIMIT 1"
            );
            $statement->execute(['role_id' => $roleId]);
            $role = $statement->fetch(PDO::FETCH_ASSOC);

            if ($role) {
                $roleKey = strtolower(trim((string)$role['role_key']));
                $roleScope = strtolower(trim((string)$role['role_scope']));
                $isSystemRole = (int)$role['is_system'] === 1;
            }
        } catch (Throwable $exception) {
            error_log('Activity Logs role lookup: ' . $exception->getMessage());
        }
    }

    $schoolAdminKeys = [
        'school_admin',
        'school_administrator',
        'school-administrator',
        'schooladmin',
    ];

    $canSeeAllBranches = $roleScope === 'school'
        && $isSystemRole
        && in_array($roleKey, $schoolAdminKeys, true);

    return [
        'tenant_id' => $tenantId,
        'branch_id' => $branchId,
        'user_id' => $userId,
        'role_id' => $roleId,
        'role_key' => $roleKey,
        'can_see_all_branches' => $canSeeAllBranches,
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

/**
 * @param array<string,mixed> $input
 * @param array<string,mixed> $scope
 * @return array{where:string,params:array<string,mixed>}
 */
function alBuildWhere(array $input, array $scope): array
{
    /*
     * Only School Portal actors are visible here.
     *
     * The users.role_id value is treated as the authoritative actor role when
     * the log has a user_id. This is important because some older logs contain
     * a school role in activity_logs.role_id even though the actor user is a
     * platform Super Administrator. Those records must never appear in the
     * School Activity Logs screen.
     */
    $clauses = [
        'al.tenant_id = :tenant_id',
        "effective_role.role_scope = 'school'",
        "effective_role.status = 'active'",
        'effective_role.deleted_at IS NULL',
        "LOWER(COALESCE(effective_role.role_key, '')) NOT IN ('super_admin', 'super-administrator', 'super_administrator', 'platform_admin', 'platform-administrator', 'platform_administrator', 'root')",
    ];
    $params = ['tenant_id' => (int)$scope['tenant_id']];

    if (empty($scope['can_see_all_branches']) && (int)$scope['branch_id'] > 0) {
        $clauses[] = '(al.branch_id = :scope_branch_id OR al.branch_id IS NULL)';
        $params['scope_branch_id'] = (int)$scope['branch_id'];
    }

    $branchId = alPositiveInt($input['branch_id'] ?? 0);
    if ($branchId > 0) {
        if (empty($scope['can_see_all_branches']) && $branchId !== (int)$scope['branch_id']) {
            $branchId = (int)$scope['branch_id'];
        }
        $clauses[] = 'al.branch_id = :branch_id';
        $params['branch_id'] = $branchId;
    }

    $userId = alPositiveInt($input['user_id'] ?? 0);
    if ($userId > 0) {
        $clauses[] = 'al.user_id = :user_id';
        $params['user_id'] = $userId;
    }

    $roleId = alPositiveInt($input['role_id'] ?? 0);
    if ($roleId > 0) {
        $clauses[] = 'effective_role.id = :role_id';
        $params['role_id'] = $roleId;
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

    $filter = alBuildWhere($input, $scope);
    $joins = "
        LEFT JOIN users u
               ON u.id = al.user_id
              AND u.tenant_id = al.tenant_id
        LEFT JOIN roles effective_role
               ON effective_role.id = CASE
                    WHEN u.id IS NOT NULL THEN u.role_id
                    ELSE al.role_id
                  END
        LEFT JOIN branches b
               ON b.id = al.branch_id
              AND b.tenant_id = al.tenant_id
    ";

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
            al.branch_id,
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
            CASE
                WHEN al.branch_id IS NULL THEN 'School-wide'
                ELSE COALESCE(NULLIF(b.branch_name, ''), 'Unknown Branch')
            END AS branch_name,
            CASE
                WHEN COALESCE(al.old_values, '') <> '' OR COALESCE(al.new_values, '') <> '' THEN 1
                ELSE 0
            END AS has_changes
         FROM activity_logs al
         {$joins}
         WHERE {$filter['where']}
         ORDER BY al.created_at DESC, al.id DESC
         LIMIT :limit OFFSET :offset"
    );
    alBind($statement, $filter['params']);
    $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
    $statement->execute();

    return [
        'rows' => $statement->fetchAll(PDO::FETCH_ASSOC),
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
 * @param array<string,mixed> $scope
 * @return array<string,mixed>
 */
function alMeta(PDO $pdo, array $scope): array
{
    $actorJoins = "
        LEFT JOIN users u
               ON u.id = al.user_id
              AND u.tenant_id = al.tenant_id
        LEFT JOIN roles effective_role
               ON effective_role.id = CASE
                    WHEN u.id IS NOT NULL THEN u.role_id
                    ELSE al.role_id
                  END
    ";

    $scopeWhere = "al.tenant_id = :tenant_id
        AND effective_role.role_scope = 'school'
        AND effective_role.status = 'active'
        AND effective_role.deleted_at IS NULL
        AND LOWER(COALESCE(effective_role.role_key, '')) NOT IN (
            'super_admin',
            'super-administrator',
            'super_administrator',
            'platform_admin',
            'platform-administrator',
            'platform_administrator',
            'root'
        )";
    $params = ['tenant_id' => (int)$scope['tenant_id']];

    if (empty($scope['can_see_all_branches']) && (int)$scope['branch_id'] > 0) {
        $scopeWhere .= ' AND (al.branch_id = :scope_branch_id OR al.branch_id IS NULL)';
        $params['scope_branch_id'] = (int)$scope['branch_id'];
    }

    $branches = [];
    if (alTableExists($pdo, 'branches')) {
        if (!empty($scope['can_see_all_branches'])) {
            $statement = $pdo->prepare(
                "SELECT id, branch_name, branch_code
                 FROM branches
                 WHERE tenant_id = :tenant_id
                   AND status = 'active'
                 ORDER BY is_main DESC, branch_name, id"
            );
            $statement->execute(['tenant_id' => (int)$scope['tenant_id']]);
        } else {
            $statement = $pdo->prepare(
                "SELECT id, branch_name, branch_code
                 FROM branches
                 WHERE tenant_id = :tenant_id
                   AND id = :branch_id
                   AND status = 'active'
                 LIMIT 1"
            );
            $statement->execute([
                'tenant_id' => (int)$scope['tenant_id'],
                'branch_id' => (int)$scope['branch_id'],
            ]);
        }
        $branches = $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    $optionQueries = [
        'modules' => "SELECT DISTINCT al.module_name AS value, al.module_name AS label
                      FROM activity_logs al
                      {$actorJoins}
                      WHERE {$scopeWhere}
                        AND al.module_name <> ''
                      ORDER BY al.module_name",
        'actions' => "SELECT DISTINCT al.action_key AS value, al.action_key AS label
                      FROM activity_logs al
                      {$actorJoins}
                      WHERE {$scopeWhere}
                        AND al.action_key <> ''
                      ORDER BY al.action_key",
        'users' => "SELECT DISTINCT u.id AS value,
                            COALESCE(NULLIF(u.name, ''), NULLIF(u.username, ''), CONCAT('User #', u.id)) AS label
                    FROM activity_logs al
                    {$actorJoins}
                    WHERE {$scopeWhere}
                      AND u.id IS NOT NULL
                    ORDER BY label",
        'roles' => "SELECT DISTINCT effective_role.id AS value,
                            COALESCE(NULLIF(effective_role.role_name, ''), CONCAT('Role #', effective_role.id)) AS label
                    FROM activity_logs al
                    {$actorJoins}
                    WHERE {$scopeWhere}
                    ORDER BY label",
    ];

    $options = [];
    foreach ($optionQueries as $key => $sql) {
        $statement = $pdo->prepare($sql);
        alBind($statement, $params);
        $statement->execute();
        $options[$key] = $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    $rangeStatement = $pdo->prepare(
        "SELECT
            MIN(DATE(al.created_at)) AS min_date,
            MAX(DATE(al.created_at)) AS max_date,
            COUNT(*) AS total_logs
         FROM activity_logs al
         {$actorJoins}
         WHERE {$scopeWhere}"
    );
    alBind($rangeStatement, $params);
    $rangeStatement->execute();
    $range = $rangeStatement->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'branches' => $branches,
        'modules' => $options['modules'] ?? [],
        'actions' => $options['actions'] ?? [],
        'users' => $options['users'] ?? [],
        'roles' => $options['roles'] ?? [],
        'date_range' => [
            'min' => (string)($range['min_date'] ?? ''),
            'max' => (string)($range['max_date'] ?? ''),
        ],
        'total_logs' => (int)($range['total_logs'] ?? 0),
        'can_filter_all_branches' => !empty($scope['can_see_all_branches']),
        'current_branch_id' => (int)$scope['branch_id'],
        'school_roles_only' => true,
        'build' => SCHOOL_ACTIVITY_LOGS_BUILD,
    ];
}

/**
 * @param array<string,mixed> $scope
 * @return array<string,mixed>
 */
function alDetail(PDO $pdo, int $id, array $scope): array
{
    if ($id <= 0) {
        throw new InvalidArgumentException('Activity log ID is invalid.');
    }

    $clauses = [
        'al.id = :id',
        'al.tenant_id = :tenant_id',
        "effective_role.role_scope = 'school'",
        "effective_role.status = 'active'",
        'effective_role.deleted_at IS NULL',
        "LOWER(COALESCE(effective_role.role_key, '')) NOT IN ('super_admin', 'super-administrator', 'super_administrator', 'platform_admin', 'platform-administrator', 'platform_administrator', 'root')",
    ];
    $params = [
        'id' => $id,
        'tenant_id' => (int)$scope['tenant_id'],
    ];

    if (empty($scope['can_see_all_branches']) && (int)$scope['branch_id'] > 0) {
        $clauses[] = '(al.branch_id = :scope_branch_id OR al.branch_id IS NULL)';
        $params['scope_branch_id'] = (int)$scope['branch_id'];
    }

    $statement = $pdo->prepare(
        "SELECT
            al.*,
            effective_role.id AS effective_role_id,
            COALESCE(NULLIF(u.name, ''), NULLIF(u.username, ''), 'System') AS user_name,
            COALESCE(NULLIF(u.username, ''), '') AS username,
            COALESCE(NULLIF(u.email, ''), '') AS user_email,
            COALESCE(NULLIF(effective_role.role_name, ''), 'School Role') AS role_name,
            CASE
                WHEN al.branch_id IS NULL THEN 'School-wide'
                ELSE COALESCE(NULLIF(b.branch_name, ''), 'Unknown Branch')
            END AS branch_name
         FROM activity_logs al
         LEFT JOIN users u
                ON u.id = al.user_id
               AND u.tenant_id = al.tenant_id
         LEFT JOIN roles effective_role
                ON effective_role.id = CASE
                     WHEN u.id IS NOT NULL THEN u.role_id
                     ELSE al.role_id
                   END
         LEFT JOIN branches b
                ON b.id = al.branch_id
               AND b.tenant_id = al.tenant_id
         WHERE " . implode(' AND ', $clauses) . "
         LIMIT 1"
    );
    alBind($statement, $params);
    $statement->execute();
    $row = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new RuntimeException('Activity log was not found.', 404);
    }

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
function alExport(PDO $pdo, array $input, array $scope): void
{
    $filter = alBuildWhere($input, $scope);
    $statement = $pdo->prepare(
        "SELECT
            al.id,
            al.created_at,
            COALESCE(NULLIF(u.name, ''), NULLIF(u.username, ''), 'System') AS user_name,
            COALESCE(NULLIF(u.username, ''), '') AS username,
            COALESCE(NULLIF(effective_role.role_name, ''), 'School Role') AS role_name,
            CASE
                WHEN al.branch_id IS NULL THEN 'School-wide'
                ELSE COALESCE(NULLIF(b.branch_name, ''), 'Unknown Branch')
            END AS branch_name,
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
         LEFT JOIN users u
                ON u.id = al.user_id
               AND u.tenant_id = al.tenant_id
         LEFT JOIN roles effective_role
                ON effective_role.id = CASE
                     WHEN u.id IS NOT NULL THEN u.role_id
                     ELSE al.role_id
                   END
         LEFT JOIN branches b
                ON b.id = al.branch_id
               AND b.tenant_id = al.tenant_id
         WHERE {$filter['where']}
         ORDER BY al.created_at DESC, al.id DESC
         LIMIT " . SCHOOL_ACTIVITY_LOGS_MAX_EXPORT
    );
    alBind($statement, $filter['params']);
    $statement->execute();

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $fileName = 'school-activity-logs-' . date('Y-m-d-His') . '.csv';
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
        'Date & Time',
        'User',
        'Username',
        'Role',
        'Branch',
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
            $row['created_at'],
            $row['user_name'],
            $row['username'],
            $row['role_name'],
            $row['branch_name'],
            $row['module_name'],
            $row['action_key'],
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

if (!alTableExists($pdo, 'activity_logs')) {
    alJson(false, 'The activity_logs table is missing from the School ERP database.', ['build' => SCHOOL_ACTIVITY_LOGS_BUILD], 500);
}

$scope = alScope($pdo);
if ((int)$scope['tenant_id'] <= 0 || (int)$scope['user_id'] <= 0) {
    alJson(false, 'School or user session is missing.', ['build' => SCHOOL_ACTIVITY_LOGS_BUILD], 401);
}

$input = array_merge($_GET, $_POST);
$action = strtolower(trim((string)($input['action'] ?? 'list')));

try {
    if ($action === 'meta') {
        alJson(true, 'Activity log filters loaded.', alMeta($pdo, $scope));
    }

    if ($action === 'list') {
        alJson(true, 'Activity logs loaded.', alList($pdo, $input, $scope));
    }

    if ($action === 'detail' || $action === 'view') {
        $id = alPositiveInt($input['id'] ?? 0);
        alJson(true, 'Activity log loaded.', [
            'log' => alDetail($pdo, $id, $scope),
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

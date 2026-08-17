<?php
declare(strict_types=1);

/*
 * Super Admin Audit Logs API
 * Build: 2026-08-15-super-admin-audit-logs-v1
 *
 * Reads the existing activity_logs table across every school.
 */
ob_start();
ini_set('display_errors', '0');

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/audit-log.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

const SA_AUDIT_BUILD = '2026-08-15-super-admin-audit-logs-v1';
const SA_AUDIT_EXPORT_LIMIT = 50000;

function saAuditJson(
    bool $success,
    string $message,
    array $data = [],
    int $status = 200
): never {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        [
            'success' => $success,
            'message' => $message,
            'data' => $data,
            'build' => SA_AUDIT_BUILD,
        ],
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

function saAuditSuperAdmin(PDO $pdo): bool
{
    if (function_exists('current_user_has_platform_role')) {
        try {
            if (current_user_has_platform_role()) {
                return true;
            }
        } catch (Throwable $ignored) {
        }
    }

    $user = function_exists('current_user')
        ? current_user()
        : [];
    $user = is_array($user) ? $user : [];

    $roleId = (int)(
        $user['role_id']
        ?? $_SESSION['role_id']
        ?? 0
    );

    if ($roleId === 1) {
        return true;
    }

    $roleKey = strtolower(trim((string)(
        $user['role_key']
        ?? $_SESSION['role_key']
        ?? ''
    )));

    if (in_array(
        $roleKey,
        ['super_admin', 'super-administrator', 'super_administrator'],
        true
    )) {
        return true;
    }

    if ($roleId <= 0) {
        return false;
    }

    $statement = $pdo->prepare(
        "SELECT role_key, role_scope
         FROM roles
         WHERE id = :role_id
           AND status = 'active'
         LIMIT 1"
    );
    $statement->execute(['role_id' => $roleId]);
    $role = $statement->fetch(PDO::FETCH_ASSOC) ?: [];

    return strtolower((string)($role['role_scope'] ?? '')) === 'platform'
        && in_array(
            strtolower((string)($role['role_key'] ?? '')),
            ['super_admin', 'super-administrator', 'super_administrator'],
            true
        );
}

function saAuditInt(mixed $value): int
{
    return max(0, (int)$value);
}

function saAuditDate(?string $value): ?string
{
    $value = trim((string)$value);

    if ($value === '') {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value
        ? $value
        : null;
}

function saAuditBind(PDOStatement $statement, array $params): void
{
    foreach ($params as $key => $value) {
        $statement->bindValue(
            ':' . $key,
            $value,
            is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
        );
    }
}

function saAuditYearIdExpression(): string
{
    $newYear = "
        CAST(
            NULLIF(
                JSON_UNQUOTE(
                    JSON_EXTRACT(
                        CASE
                            WHEN JSON_VALID(al.new_values)
                            THEN al.new_values
                            ELSE '{}'
                        END,
                        '$.academic_year_id'
                    )
                ),
                ''
            ) AS UNSIGNED
        )
    ";

    $oldYear = "
        CAST(
            NULLIF(
                JSON_UNQUOTE(
                    JSON_EXTRACT(
                        CASE
                            WHEN JSON_VALID(al.old_values)
                            THEN al.old_values
                            ELSE '{}'
                        END,
                        '$.academic_year_id'
                    )
                ),
                ''
            ) AS UNSIGNED
        )
    ";

    $dateYear = "
        (
            SELECT ay_date.id
            FROM academic_years ay_date
            WHERE ay_date.tenant_id = al.tenant_id
              AND DATE(al.created_at)
                  BETWEEN ay_date.start_date AND ay_date.end_date
            ORDER BY
                ay_date.is_current DESC,
                ay_date.start_date DESC,
                ay_date.id DESC
            LIMIT 1
        )
    ";

    return "COALESCE(NULLIF(($newYear), 0), NULLIF(($oldYear), 0), $dateYear)";
}

function saAuditActionGroupExpression(): string
{
    return "
        CASE
            WHEN LOWER(al.action_key) LIKE '%import%'
                THEN 'import'
            WHEN LOWER(al.action_key) LIKE '%export%'
                OR LOWER(al.action_key) LIKE '%download%'
                THEN 'export'
            WHEN LOWER(al.action_key) LIKE '%print%'
                OR LOWER(al.action_key) LIKE '%reprint%'
                THEN 'print'
            WHEN LOWER(al.action_key) LIKE '%delete%'
                OR LOWER(al.action_key) LIKE '%remove%'
                OR LOWER(al.action_key) LIKE '%cancel%'
                THEN 'delete'
            WHEN LOWER(al.action_key) LIKE '%edit%'
                OR LOWER(al.action_key) LIKE '%update%'
                OR LOWER(al.action_key) LIKE '%save%'
                OR LOWER(al.action_key) LIKE '%status%'
                OR LOWER(al.action_key) LIKE '%approve%'
                OR LOWER(al.action_key) LIKE '%reject%'
                OR LOWER(al.action_key) LIKE '%restore%'
                THEN 'edit'
            WHEN LOWER(al.action_key) LIKE '%create%'
                OR LOWER(al.action_key) LIKE '%created%'
                OR LOWER(al.action_key) LIKE '%add%'
                OR LOWER(al.action_key) LIKE '%insert%'
                THEN 'create'
            WHEN LOWER(al.action_key) LIKE '%view%'
                OR LOWER(al.action_key) LIKE '%open%'
                OR LOWER(al.action_key) LIKE '%read%'
                OR LOWER(al.action_key) LIKE '%detail%'
                OR LOWER(al.action_key) = 'list'
                THEN 'view'
            WHEN LOWER(al.action_key) LIKE '%login%'
                OR LOWER(al.action_key) LIKE '%logout%'
                OR LOWER(al.action_key) LIKE '%sign_in%'
                OR LOWER(al.action_key) LIKE '%sign_out%'
                THEN 'login'
            ELSE 'other'
        END
    ";
}

function saAuditFilter(array $input): array
{
    $where = ['1 = 1'];
    $params = [];

    $yearExpr = saAuditYearIdExpression();
    $groupExpr = saAuditActionGroupExpression();

    $schoolId = saAuditInt($input['school_id'] ?? 0);
    if ($schoolId > 0) {
        $where[] = 'al.tenant_id = :school_id';
        $params['school_id'] = $schoolId;
    }

    $branchId = saAuditInt($input['branch_id'] ?? 0);
    if ($branchId > 0) {
        $where[] = 'COALESCE(al.branch_id, u.default_branch_id) = :branch_id';
        $params['branch_id'] = $branchId;
    }

    $academicYearId = saAuditInt($input['academic_year_id'] ?? 0);
    if ($academicYearId > 0) {
        $where[] = "($yearExpr) = :academic_year_id";
        $params['academic_year_id'] = $academicYearId;
    }

    $userId = saAuditInt($input['user_id'] ?? 0);
    if ($userId > 0) {
        $where[] = 'al.user_id = :user_id';
        $params['user_id'] = $userId;
    }

    $roleId = saAuditInt($input['role_id'] ?? 0);
    if ($roleId > 0) {
        $where[] = 'COALESCE(al.role_id, u.role_id) = :role_id';
        $params['role_id'] = $roleId;
    }

    $module = trim((string)($input['module'] ?? ''));
    if ($module !== '') {
        $where[] = 'al.module_name = :module_name';
        $params['module_name'] = $module;
    }

    $action = trim((string)($input['action_key'] ?? ''));
    if ($action !== '') {
        $where[] = 'al.action_key = :action_key';
        $params['action_key'] = $action;
    }

    $actionGroup = strtolower(trim((string)($input['action_group'] ?? '')));
    if (in_array(
        $actionGroup,
        ['view', 'create', 'edit', 'delete', 'import', 'export', 'print', 'login', 'other'],
        true
    )) {
        $where[] = "($groupExpr) = :action_group";
        $params['action_group'] = $actionGroup;
    }

    $tableName = trim((string)($input['table_name'] ?? ''));
    if ($tableName !== '') {
        $where[] = 'al.table_name = :table_name';
        $params['table_name'] = $tableName;
    }

    $fromDate = saAuditDate($input['from_date'] ?? null);
    if ($fromDate !== null) {
        $where[] = 'al.created_at >= :from_date';
        $params['from_date'] = $fromDate . ' 00:00:00';
    }

    $toDate = saAuditDate($input['to_date'] ?? null);
    if ($toDate !== null) {
        $where[] = 'al.created_at <= :to_date';
        $params['to_date'] = $toDate . ' 23:59:59';
    }

    $search = trim((string)($input['q'] ?? $input['search'] ?? ''));
    if ($search !== '') {
        $where[] = "(
            t.school_name LIKE :search
            OR t.tenant_code LIKE :search
            OR b.branch_name LIKE :search
            OR b.branch_code LIKE :search
            OR u.name LIKE :search
            OR u.username LIKE :search
            OR u.email LIKE :search
            OR r.role_name LIKE :search
            OR r.role_key LIKE :search
            OR al.module_name LIKE :search
            OR al.action_key LIKE :search
            OR al.table_name LIKE :search
            OR CAST(al.record_id AS CHAR) LIKE :search
            OR al.description LIKE :search
            OR al.ip_address LIKE :search
        )";
        $params['search'] = '%' . $search . '%';
    }

    return [
        'where' => implode(' AND ', $where),
        'params' => $params,
    ];
}

function saAuditFrom(): string
{
    return "
        FROM activity_logs al
        LEFT JOIN tenants t
            ON t.id = al.tenant_id
        LEFT JOIN users u
            ON u.id = al.user_id
           AND u.tenant_id = al.tenant_id
        LEFT JOIN branches b
            ON b.id = COALESCE(al.branch_id, u.default_branch_id)
           AND b.tenant_id = al.tenant_id
        LEFT JOIN roles r
            ON r.id = COALESCE(al.role_id, u.role_id)
    ";
}

function saAuditSelect(): string
{
    $yearExpr = saAuditYearIdExpression();
    $groupExpr = saAuditActionGroupExpression();

    return "
        SELECT
            al.id,
            al.tenant_id,
            COALESCE(al.branch_id, u.default_branch_id) AS branch_id,
            al.user_id,
            COALESCE(al.role_id, u.role_id) AS role_id,
            al.module_name,
            al.action_key,
            ($groupExpr) AS action_group,
            al.table_name,
            al.record_id,
            al.description,
            al.ip_address,
            al.created_at,
            t.tenant_code,
            t.school_name,
            b.branch_code,
            b.branch_name,
            u.name AS user_name,
            u.username,
            u.email AS user_email,
            r.role_key,
            r.role_name,
            ($yearExpr) AS academic_year_id,
            (
                SELECT ay_name.year_name
                FROM academic_years ay_name
                WHERE ay_name.id = ($yearExpr)
                  AND ay_name.tenant_id = al.tenant_id
                LIMIT 1
            ) AS academic_year_name
    ";
}

function saAuditMeta(PDO $pdo): array
{
    $schools = $pdo->query(
        "SELECT id, tenant_code, school_name, status
         FROM tenants
         ORDER BY school_name ASC, id ASC"
    )->fetchAll(PDO::FETCH_ASSOC);

    $branches = $pdo->query(
        "SELECT id, tenant_id, branch_code, branch_name, status
         FROM branches
         ORDER BY tenant_id ASC, is_main DESC, branch_name ASC"
    )->fetchAll(PDO::FETCH_ASSOC);

    $years = $pdo->query(
        "SELECT id, tenant_id, year_name, academic_year_code,
                start_date, end_date, is_current, status
         FROM academic_years
         ORDER BY tenant_id ASC, start_date DESC, id DESC"
    )->fetchAll(PDO::FETCH_ASSOC);

    $users = $pdo->query(
        "SELECT id, tenant_id, default_branch_id, role_id,
                name, username, email, status
         FROM users
         ORDER BY tenant_id ASC, name ASC, id ASC"
    )->fetchAll(PDO::FETCH_ASSOC);

    $roles = $pdo->query(
        "SELECT id, tenant_id, role_key, role_name, role_scope, status
         FROM roles
         ORDER BY role_scope ASC, role_name ASC, id ASC"
    )->fetchAll(PDO::FETCH_ASSOC);

    $modules = $pdo->query(
        "SELECT DISTINCT module_name
         FROM activity_logs
         WHERE module_name <> ''
         ORDER BY module_name ASC"
    )->fetchAll(PDO::FETCH_COLUMN);

    $actions = $pdo->query(
        "SELECT DISTINCT action_key
         FROM activity_logs
         WHERE action_key <> ''
         ORDER BY action_key ASC"
    )->fetchAll(PDO::FETCH_COLUMN);

    $tables = $pdo->query(
        "SELECT DISTINCT table_name
         FROM activity_logs
         WHERE table_name IS NOT NULL
           AND table_name <> ''
         ORDER BY table_name ASC"
    )->fetchAll(PDO::FETCH_COLUMN);

    return compact(
        'schools',
        'branches',
        'years',
        'users',
        'roles',
        'modules',
        'actions',
        'tables'
    );
}

function saAuditSummary(
    PDO $pdo,
    array $filter
): array {
    $groupExpr = saAuditActionGroupExpression();

    $sql = "
        SELECT
            COUNT(*) AS total,
            SUM(DATE(al.created_at) = CURRENT_DATE()) AS today,
            COUNT(DISTINCT al.tenant_id) AS schools,
            COUNT(DISTINCT COALESCE(al.branch_id, u.default_branch_id)) AS branches,
            COUNT(DISTINCT al.user_id) AS users,
            SUM(($groupExpr) = 'view') AS view_count,
            SUM(($groupExpr) = 'create') AS create_count,
            SUM(($groupExpr) = 'edit') AS edit_count,
            SUM(($groupExpr) = 'delete') AS delete_count,
            SUM(($groupExpr) = 'import') AS import_count,
            SUM(($groupExpr) = 'export') AS export_count,
            SUM(($groupExpr) = 'print') AS print_count
        " . saAuditFrom() . "
        WHERE {$filter['where']}
    ";

    $statement = $pdo->prepare($sql);
    saAuditBind($statement, $filter['params']);
    $statement->execute();
    $row = $statement->fetch(PDO::FETCH_ASSOC) ?: [];

    foreach ($row as $key => $value) {
        $row[$key] = (int)($value ?? 0);
    }

    return $row;
}

function saAuditList(
    PDO $pdo,
    array $input
): array {
    $page = max(1, (int)($input['page'] ?? 1));
    $perPage = (int)($input['per_page'] ?? 25);
    $perPage = in_array($perPage, [10, 25, 50, 100], true)
        ? $perPage
        : 25;

    $filter = saAuditFilter($input);

    $countStatement = $pdo->prepare(
        "SELECT COUNT(*) "
        . saAuditFrom()
        . " WHERE {$filter['where']}"
    );
    saAuditBind($countStatement, $filter['params']);
    $countStatement->execute();

    $total = (int)$countStatement->fetchColumn();
    $pages = max(1, (int)ceil($total / $perPage));

    if ($page > $pages) {
        $page = $pages;
    }

    $offset = ($page - 1) * $perPage;

    $sql = saAuditSelect()
        . saAuditFrom()
        . " WHERE {$filter['where']}
            ORDER BY al.created_at DESC, al.id DESC
            LIMIT :limit OFFSET :offset";

    $statement = $pdo->prepare($sql);
    saAuditBind($statement, $filter['params']);
    $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
    $statement->execute();

    return [
        'rows' => $statement->fetchAll(PDO::FETCH_ASSOC),
        'summary' => saAuditSummary($pdo, $filter),
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'pages' => $pages,
            'from' => $total > 0 ? $offset + 1 : 0,
            'to' => min($offset + $perPage, $total),
        ],
    ];
}

function saAuditDetail(PDO $pdo, int $id): array
{
    if ($id <= 0) {
        throw new InvalidArgumentException('Audit log ID is required.');
    }

    $yearExpr = saAuditYearIdExpression();
    $groupExpr = saAuditActionGroupExpression();

    $statement = $pdo->prepare(
        "SELECT
            al.*,
            COALESCE(al.branch_id, u.default_branch_id) AS effective_branch_id,
            t.tenant_code,
            t.school_name,
            b.branch_code,
            b.branch_name,
            u.name AS user_name,
            u.username,
            u.email AS user_email,
            r.role_key,
            r.role_name,
            ($groupExpr) AS action_group,
            ($yearExpr) AS academic_year_id,
            (
                SELECT ay_name.year_name
                FROM academic_years ay_name
                WHERE ay_name.id = ($yearExpr)
                  AND ay_name.tenant_id = al.tenant_id
                LIMIT 1
            ) AS academic_year_name
         " . saAuditFrom() . "
         WHERE al.id = :id
         LIMIT 1"
    );
    $statement->execute(['id' => $id]);

    $row = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new RuntimeException('Audit log record was not found.', 404);
    }

    return $row;
}

function saAuditCsvSafe(mixed $value): string
{
    $value = (string)($value ?? '');

    if ($value !== '' && preg_match('/^[=+\-@]/', $value) === 1) {
        return "'" . $value;
    }

    return $value;
}

function saAuditExport(PDO $pdo, array $input): never
{
    $filter = saAuditFilter($input);

    $statement = $pdo->prepare(
        saAuditSelect()
        . ",
            al.old_values,
            al.new_values,
            al.user_agent
         "
        . saAuditFrom()
        . " WHERE {$filter['where']}
            ORDER BY al.created_at DESC, al.id DESC
            LIMIT " . SA_AUDIT_EXPORT_LIMIT
    );
    saAuditBind($statement, $filter['params']);
    $statement->execute();

    /*
     * Record the export itself. It will appear on the next Audit Logs load.
     */
    schoolerp_audit_log(
        $pdo,
        'Audit Logs',
        'export',
        'activity_logs',
        null,
        null,
        [
            'format' => 'csv',
            'filters' => array_intersect_key(
                $input,
                array_flip([
                    'school_id',
                    'branch_id',
                    'academic_year_id',
                    'user_id',
                    'role_id',
                    'module',
                    'action_group',
                    'action_key',
                    'table_name',
                    'from_date',
                    'to_date',
                    'q',
                ])
            ),
        ],
        'Exported Super Admin Audit Logs'
    );

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $fileName = 'super-admin-audit-logs-' . date('Y-m-d-His') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header(
        'Content-Disposition: attachment; filename="' . $fileName . '"'
    );
    header('Cache-Control: no-store, no-cache, must-revalidate');

    $output = fopen('php://output', 'wb');

    if ($output === false) {
        exit;
    }

    fwrite($output, "\xEF\xBB\xBF");

    fputcsv($output, [
        'Log ID',
        'Date & Time',
        'School Code',
        'School',
        'Branch Code',
        'Branch',
        'Academic Year',
        'User',
        'Username',
        'Role',
        'Module',
        'Action Group',
        'Action Key',
        'Table',
        'Record ID',
        'Description',
        'IP Address',
        'Old Values',
        'New Values',
        'User Agent',
    ]);

    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        fputcsv(
            $output,
            array_map('saAuditCsvSafe', [
                $row['id'] ?? '',
                $row['created_at'] ?? '',
                $row['tenant_code'] ?? '',
                $row['school_name'] ?? '',
                $row['branch_code'] ?? '',
                $row['branch_name'] ?? '',
                $row['academic_year_name'] ?? '',
                $row['user_name'] ?? '',
                $row['username'] ?? '',
                $row['role_name'] ?? '',
                $row['module_name'] ?? '',
                $row['action_group'] ?? '',
                $row['action_key'] ?? '',
                $row['table_name'] ?? '',
                $row['record_id'] ?? '',
                $row['description'] ?? '',
                $row['ip_address'] ?? '',
                $row['old_values'] ?? '',
                $row['new_values'] ?? '',
                $row['user_agent'] ?? '',
            ])
        );
    }

    fclose($output);
    exit;
}

try {
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        saAuditJson(false, 'Database connection is unavailable.', [], 500);
    }

    if (!saAuditSuperAdmin($pdo)) {
        saAuditJson(
            false,
            'Only a Super Administrator can access Audit Logs.',
            [],
            403
        );
    }

    if (function_exists('school_table_exists')
        && !school_table_exists($pdo, 'activity_logs')) {
        saAuditJson(
            false,
            'The activity_logs table is missing.',
            [],
            500
        );
    }

    $input = array_merge($_GET, $_POST);
    $action = strtolower(trim((string)($input['action'] ?? 'list')));

    if ($action === 'meta') {
        saAuditJson(
            true,
            'Audit log filters loaded.',
            saAuditMeta($pdo)
        );
    }

    if ($action === 'list') {
        saAuditJson(
            true,
            'Audit logs loaded.',
            saAuditList($pdo, $input)
        );
    }

    if ($action === 'detail' || $action === 'view') {
        saAuditJson(
            true,
            'Audit log loaded.',
            [
                'log' => saAuditDetail(
                    $pdo,
                    saAuditInt($input['id'] ?? 0)
                ),
            ]
        );
    }

    if ($action === 'export') {
        saAuditExport($pdo, $input);
    }

    saAuditJson(
        false,
        'Invalid Audit Logs action.',
        [],
        400
    );
} catch (InvalidArgumentException $exception) {
    saAuditJson(
        false,
        $exception->getMessage(),
        [],
        422
    );
} catch (RuntimeException $exception) {
    $status = (int)$exception->getCode();

    if ($status < 400 || $status > 599) {
        $status = 500;
    }

    saAuditJson(
        false,
        $exception->getMessage(),
        [],
        $status
    );
} catch (Throwable $exception) {
    error_log(
        'Super Admin Audit Logs [' . SA_AUDIT_BUILD . ']: '
        . $exception->getMessage()
    );

    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $local = str_contains($host, 'localhost')
        || str_contains($host, '127.0.0.1');

    saAuditJson(
        false,
        $local
            ? 'Audit Logs error: ' . $exception->getMessage()
            : 'Unable to load Super Admin Audit Logs.',
        [],
        500
    );
}

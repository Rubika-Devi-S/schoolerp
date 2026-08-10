<?php
declare(strict_types=1);

/*
 * Link this API to the exact School sidebar permission item before Bootstrap
 * performs its automatic API action check.
 */
if (!defined('SCHOOL_API_PAGE_KEY')) {
    define('SCHOOL_API_PAGE_KEY', 'leave_management');
}

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

function slOut(
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
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
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

function slInput(): array
{
    $payload = function_exists('school_request_payload')
        ? school_request_payload()
        : $_POST;

    return array_merge(
        is_array($_GET) ? $_GET : [],
        is_array($payload) ? $payload : []
    );
}

function slScope(): array
{
    $user = function_exists('current_user') ? current_user() : [];
    $user = is_array($user) ? $user : [];

    return [
        'tenant_id' => (int)(
            $user['tenant_id']
            ?? $user['school_id']
            ?? $_SESSION['tenant_id']
            ?? $_SESSION['school_id']
            ?? 0
        ),
        'branch_id' => (int)(
            $user['branch_id']
            ?? $user['default_branch_id']
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
    ];
}

function slCan(string $action): bool
{
    $action = strtolower(trim($action));
    $action = $action === 'add' ? 'create' : $action;

    if (function_exists('school_effective_permission')) {
        return school_effective_permission(
            'leave_management',
            $action
        );
    }

    return function_exists('has_permission')
        && has_permission('leave_management', $action);
}

function slRequire(string $action): void
{
    if (slCan($action)) {
        return;
    }

    $labels = [
        'view' => 'view',
        'create' => 'add',
        'add' => 'add',
        'edit' => 'edit',
        'delete' => 'delete',
    ];

    $label = $labels[strtolower($action)] ?? strtolower($action);

    slOut(
        false,
        'You do not have permission to ' . $label . ' Staff Leave.',
        [],
        403
    );
}

function slCapabilities(): array
{
    return [
        'view' => slCan('view'),
        'add' => slCan('create'),
        'edit' => slCan('edit'),
        'delete' => slCan('delete'),
    ];
}

function slCsrf(array $input): void
{
    $stored = (string)($_SESSION['staff_leave_csrf'] ?? '');
    $received = (string)($input['csrf_token'] ?? '');

    if (
        $stored === ''
        || $received === ''
        || !hash_equals($stored, $received)
    ) {
        slOut(
            false,
            'Invalid or expired CSRF token. Refresh the page and try again.',
            [],
            419
        );
    }
}

function slTable(PDO $pdo, string $table): bool
{
    if (function_exists('school_table_exists')) {
        return school_table_exists($pdo, $table);
    }

    $statement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema=DATABASE()
           AND table_name=:table'
    );
    $statement->execute(['table' => $table]);

    return (int)$statement->fetchColumn() > 0;
}

function slColumn(PDO $pdo, string $table, string $column): bool
{
    if (function_exists('school_column_exists')) {
        return school_column_exists($pdo, $table, $column);
    }

    $statement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.columns
         WHERE table_schema=DATABASE()
           AND table_name=:table
           AND column_name=:column'
    );
    $statement->execute([
        'table' => $table,
        'column' => $column,
    ]);

    return (int)$statement->fetchColumn() > 0;
}

function slIndex(PDO $pdo, string $table, string $index): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.statistics
         WHERE table_schema=DATABASE()
           AND table_name=:table
           AND index_name=:index'
    );
    $statement->execute([
        'table' => $table,
        'index' => $index,
    ]);

    return (int)$statement->fetchColumn() > 0;
}

function slEnsurePermissionRegistry(PDO $pdo): void
{
    /*
     * Keep the page/API registry aligned with the existing sidebar item. This
     * does not grant any role permission and never adds a tenant assignment.
     */
    if (!slTable($pdo, 'sidebar_items') || !slTable($pdo, 'app_pages')) {
        return;
    }

    try {
        $sidebar = $pdo->query(
            "SELECT id,module_id,menu_title,route,icon,parent_id,display_order
             FROM sidebar_items
             WHERE menu_key='leave_management'
               AND is_active=1
             ORDER BY id
             LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC);

        if (!is_array($sidebar)) {
            return;
        }

        $moduleId = (int)($sidebar['module_id'] ?? 0);
        if ($moduleId <= 0 && slTable($pdo, 'app_modules')) {
            $module = $pdo->query(
                "SELECT id
                 FROM app_modules
                 WHERE module_key IN('staff','hr','attendance')
                   AND is_active=1
                 ORDER BY FIELD(module_key,'staff','hr','attendance'),id
                 LIMIT 1"
            );
            $moduleId = (int)($module->fetchColumn() ?: 0);
        }

        if ($moduleId <= 0) {
            return;
        }

        $hasPortal = slColumn($pdo, 'app_pages', 'portal_scope');
        $portalColumn = $hasPortal ? ',portal_scope' : '';
        $portalValue = $hasPortal ? ",'school'" : '';

        $statement = $pdo->prepare(
            "INSERT INTO app_pages(
                module_id,page_key,page_name,route,icon,
                display_order,show_in_sidebar,is_active{$portalColumn}
             )
             VALUES(
                :module_id,'leave_management','Leave Management',
                'leave-management.php','calendar-off',
                :display_order,1,1{$portalValue}
             )
             ON DUPLICATE KEY UPDATE
                module_id=VALUES(module_id),
                page_name=VALUES(page_name),
                route=VALUES(route),
                icon=VALUES(icon),
                display_order=VALUES(display_order),
                show_in_sidebar=1,
                is_active=1"
        );
        $statement->execute([
            'module_id' => $moduleId,
            'display_order' => (int)($sidebar['display_order'] ?? 4),
        ]);

        if (slColumn($pdo, 'app_pages', 'parent_page_id')) {
            $pdo->exec(
                "UPDATE app_pages AS child
                 INNER JOIN sidebar_items AS child_item
                    ON child_item.menu_key=child.page_key
                 LEFT JOIN sidebar_items AS parent_item
                    ON parent_item.id=child_item.parent_id
                 LEFT JOIN app_pages AS parent_page
                    ON parent_page.page_key=parent_item.menu_key
                 SET child.parent_page_id=parent_page.id
                 WHERE child.page_key='leave_management'"
            );
        }

        /*
         * Disable the obsolete app-page alias created by older Staff Leave
         * code. The sidebar key leave_management remains the sole authority.
         */
        $pdo->exec(
            "UPDATE app_pages
             SET show_in_sidebar=0,is_active=0
             WHERE page_key='staff_leave'"
        );

        if (slTable($pdo, 'app_page_api_routes')) {
            $pageId = (int)($pdo->query(
                "SELECT id
                 FROM app_pages
                 WHERE page_key='leave_management'
                   AND is_active=1
                 LIMIT 1"
            )->fetchColumn() ?: 0);

            if ($pageId > 0) {
                $map = $pdo->prepare(
                    "INSERT INTO app_page_api_routes(page_id,api_route,is_active)
                     VALUES(:page_id,'api/staff-leave.php',1)
                     ON DUPLICATE KEY UPDATE
                        is_active=1,
                        updated_at=CURRENT_TIMESTAMP"
                );
                $map->execute(['page_id' => $pageId]);
            }
        }
    } catch (Throwable $exception) {
        /*
         * The runtime constant still links the API even when an older schema
         * cannot be synchronized. Log the issue without changing permissions.
         */
        error_log(
            'Staff Leave permission registry: '
            . $exception->getMessage()
        );
    }
}

function slUpgradeLeaveTable(PDO $pdo): void
{
    $columns = [
        'tenant_id' => 'BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER id',
        'branch_id' => 'BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER tenant_id',
        'staff_id' => 'BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER branch_id',
        'leave_type' => "ENUM('casual','sick','earned','loss_of_pay') NOT NULL DEFAULT 'casual' AFTER staff_id",
        'from_date' => 'DATE NULL AFTER leave_type',
        'to_date' => 'DATE NULL AFTER from_date',
        'total_days' => 'DECIMAL(6,2) NOT NULL DEFAULT 1 AFTER to_date',
        'reason' => "VARCHAR(500) NOT NULL DEFAULT '' AFTER total_days",
        'status' => "ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending' AFTER reason",
        'created_by' => 'BIGINT UNSIGNED DEFAULT NULL AFTER status',
        'updated_by' => 'BIGINT UNSIGNED DEFAULT NULL AFTER created_by',
        'decided_by' => 'BIGINT UNSIGNED DEFAULT NULL AFTER updated_by',
        'decided_at' => 'DATETIME DEFAULT NULL AFTER decided_by',
        'deleted_at' => 'DATETIME DEFAULT NULL AFTER decided_at',
        'deleted_by' => 'BIGINT UNSIGNED DEFAULT NULL AFTER deleted_at',
        'created_at' => 'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER deleted_by',
        'updated_at' => 'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
    ];

    foreach ($columns as $column => $definition) {
        if (!slColumn($pdo, 'staff_leave_requests', $column)) {
            $pdo->exec(
                "ALTER TABLE staff_leave_requests
                 ADD COLUMN `{$column}` {$definition}"
            );
        }
    }

    $indexes = [
        'idx_staff_leave_list' => '(tenant_id,branch_id,status,from_date)',
        'idx_staff_leave_staff' => '(staff_id,from_date,to_date)',
        'idx_staff_leave_deleted' => '(tenant_id,deleted_at)',
    ];

    foreach ($indexes as $name => $columnsSql) {
        if (!slIndex($pdo, 'staff_leave_requests', $name)) {
            $pdo->exec(
                "ALTER TABLE staff_leave_requests
                 ADD INDEX `{$name}` {$columnsSql}"
            );
        }
    }
}

function slEnsure(PDO $pdo): void
{
    foreach (['staff_members', 'users', 'roles'] as $table) {
        if (!slTable($pdo, $table)) {
            throw new RuntimeException(
                'Missing required table: '
                . $table
                . '. Install Staff Management first.'
            );
        }
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS staff_leave_requests(
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id BIGINT UNSIGNED NOT NULL,
            branch_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            staff_id BIGINT UNSIGNED NOT NULL,
            leave_type ENUM('casual','sick','earned','loss_of_pay')
                NOT NULL DEFAULT 'casual',
            from_date DATE NOT NULL,
            to_date DATE NOT NULL,
            total_days DECIMAL(6,2) NOT NULL DEFAULT 1,
            reason VARCHAR(500) NOT NULL,
            status ENUM('pending','approved','rejected')
                NOT NULL DEFAULT 'pending',
            created_by BIGINT UNSIGNED DEFAULT NULL,
            updated_by BIGINT UNSIGNED DEFAULT NULL,
            decided_by BIGINT UNSIGNED DEFAULT NULL,
            decided_at DATETIME DEFAULT NULL,
            deleted_at DATETIME DEFAULT NULL,
            deleted_by BIGINT UNSIGNED DEFAULT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY(id),
            KEY idx_staff_leave_list(tenant_id,branch_id,status,from_date),
            KEY idx_staff_leave_staff(staff_id,from_date,to_date),
            KEY idx_staff_leave_deleted(tenant_id,deleted_at)
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci"
    );

    slUpgradeLeaveTable($pdo);
    slEnsurePermissionRegistry($pdo);
}

function slDate(string $value, string $label): string
{
    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();

    if (
        !$date
        || (
            is_array($errors)
            && (
                (int)($errors['warning_count'] ?? 0) > 0
                || (int)($errors['error_count'] ?? 0) > 0
            )
        )
        || $date->format('Y-m-d') !== $value
    ) {
        throw new InvalidArgumentException($label . ' is invalid.');
    }

    return $value;
}

function slStaff(PDO $pdo, array $scope): array
{
    $where = [
        'tenant_id=:tenant_id',
    ];
    $params = [
        'tenant_id' => (int)$scope['tenant_id'],
    ];

    if (slColumn($pdo, 'staff_members', 'deleted_at')) {
        $where[] = 'deleted_at IS NULL';
    }

    if (slColumn($pdo, 'staff_members', 'status')) {
        $where[] = "status='active'";
    }

    if ((int)$scope['branch_id'] > 0
        && slColumn($pdo, 'staff_members', 'branch_id')) {
        $where[] = 'branch_id=:branch_id';
        $params['branch_id'] = (int)$scope['branch_id'];
    }

    $statement = $pdo->prepare(
        "SELECT id,staff_code,
                TRIM(CONCAT(first_name,' ',COALESCE(last_name,''))) AS staff_name,
                mobile
         FROM staff_members
         WHERE " . implode(' AND ', $where) . "
         ORDER BY first_name,last_name"
    );
    $statement->execute($params);

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function slList(PDO $pdo, array $scope, array $filters): array
{
    $where = [
        'l.tenant_id=:tenant_id',
        'l.deleted_at IS NULL',
    ];
    $params = [
        'tenant_id' => (int)$scope['tenant_id'],
    ];

    if ((int)$scope['branch_id'] > 0) {
        $where[] = 'l.branch_id=:branch_id';
        $params['branch_id'] = (int)$scope['branch_id'];
    }

    $search = trim((string)($filters['search'] ?? ''));
    if ($search !== '') {
        $where[] = "(
            st.staff_code LIKE :search
            OR st.first_name LIKE :search
            OR st.last_name LIKE :search
            OR CONCAT_WS(' ',st.first_name,st.last_name) LIKE :search
            OR st.mobile LIKE :search
        )";
        $params['search'] = '%' . $search . '%';
    }

    $status = strtolower(trim((string)($filters['status'] ?? 'all')));
    if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
        $where[] = 'l.status=:status';
        $params['status'] = $status;
    }

    $baseSql = "
        FROM staff_leave_requests AS l
        INNER JOIN staff_members AS st
            ON st.id=l.staff_id
           AND st.tenant_id=l.tenant_id
        WHERE " . implode(' AND ', $where);

    $count = $pdo->prepare('SELECT COUNT(*)' . $baseSql);
    $count->execute($params);
    $total = (int)$count->fetchColumn();

    $page = max(1, (int)($filters['page'] ?? 1));
    $perPage = min(100, max(5, (int)($filters['per_page'] ?? 10)));
    $lastPage = max(1, (int)ceil($total / $perPage));
    $page = min($page, $lastPage);
    $offset = ($page - 1) * $perPage;

    $statement = $pdo->prepare(
        "SELECT l.*,
                TRIM(CONCAT(st.first_name,' ',COALESCE(st.last_name,'')))
                    AS staff_name,
                st.staff_code,
                DATE_FORMAT(l.from_date,'%d-%m-%Y') AS from_date_display,
                DATE_FORMAT(l.to_date,'%d-%m-%Y') AS to_date_display,
                CASE
                    WHEN CHAR_LENGTH(l.reason)>60
                    THEN CONCAT(LEFT(l.reason,60),'...')
                    ELSE l.reason
                END AS reason_short
         {$baseSql}
         ORDER BY l.created_at DESC,l.id DESC
         LIMIT {$perPage} OFFSET {$offset}"
    );
    $statement->execute($params);
    $records = $statement->fetchAll(PDO::FETCH_ASSOC);

    $statsWhere = [
        'tenant_id=:tenant_id',
        'deleted_at IS NULL',
    ];
    $statsParams = [
        'tenant_id' => (int)$scope['tenant_id'],
    ];

    if ((int)$scope['branch_id'] > 0) {
        $statsWhere[] = 'branch_id=:branch_id';
        $statsParams['branch_id'] = (int)$scope['branch_id'];
    }

    $stats = [
        'total' => 0,
        'pending' => 0,
        'approved' => 0,
        'rejected' => 0,
    ];

    $statsStatement = $pdo->prepare(
        "SELECT status,COUNT(*) AS count
         FROM staff_leave_requests
         WHERE " . implode(' AND ', $statsWhere) . "
         GROUP BY status"
    );
    $statsStatement->execute($statsParams);

    foreach ($statsStatement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rowStatus = (string)$row['status'];
        $countValue = (int)$row['count'];

        if (array_key_exists($rowStatus, $stats)) {
            $stats[$rowStatus] = $countValue;
        }

        $stats['total'] += $countValue;
    }

    return [
        'records' => $records,
        'pagination' => [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => $lastPage,
        ],
        'stats' => $stats,
    ];
}

function slRecord(PDO $pdo, array $scope, int $id): array
{
    if ($id <= 0) {
        throw new InvalidArgumentException('Invalid leave request.');
    }

    $where = [
        'l.id=:id',
        'l.tenant_id=:tenant_id',
        'l.deleted_at IS NULL',
    ];
    $params = [
        'id' => $id,
        'tenant_id' => (int)$scope['tenant_id'],
    ];

    if ((int)$scope['branch_id'] > 0) {
        $where[] = 'l.branch_id=:branch_id';
        $params['branch_id'] = (int)$scope['branch_id'];
    }

    $statement = $pdo->prepare(
        "SELECT l.*,
                TRIM(CONCAT(st.first_name,' ',COALESCE(st.last_name,'')))
                    AS staff_name,
                st.staff_code,
                DATE_FORMAT(l.from_date,'%d-%m-%Y') AS from_date_display,
                DATE_FORMAT(l.to_date,'%d-%m-%Y') AS to_date_display,
                creator.name AS created_by_name,
                decider.name AS decided_by_name
         FROM staff_leave_requests AS l
         INNER JOIN staff_members AS st
            ON st.id=l.staff_id
           AND st.tenant_id=l.tenant_id
         LEFT JOIN users AS creator
            ON creator.id=l.created_by
           AND creator.tenant_id=l.tenant_id
         LEFT JOIN users AS decider
            ON decider.id=l.decided_by
           AND decider.tenant_id=l.tenant_id
         WHERE " . implode(' AND ', $where) . "
         LIMIT 1"
    );
    $statement->execute($params);

    $record = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($record)) {
        throw new InvalidArgumentException('Leave request not found.');
    }

    return $record;
}

function slSave(PDO $pdo, array $scope, array $input): array
{
    $id = (int)($input['id'] ?? 0);

    if ($id > 0) {
        slRequire('edit');
    } else {
        slRequire('create');
    }

    $staffId = (int)($input['staff_id'] ?? 0);
    $leaveType = strtolower(trim((string)($input['leave_type'] ?? '')));
    $status = strtolower(trim((string)($input['status'] ?? 'pending')));

    /*
     * Add permission alone creates a pending request. Approving/rejecting is
     * an Edit action and therefore requires Edit permission.
     */
    if ($id <= 0 && !slCan('edit')) {
        $status = 'pending';
    }

    $fromDate = slDate((string)($input['from_date'] ?? ''), 'From Date');
    $toDate = slDate((string)($input['to_date'] ?? ''), 'To Date');

    if ($toDate < $fromDate) {
        throw new InvalidArgumentException(
            'To Date cannot be before From Date.'
        );
    }

    if (
        $staffId <= 0
        || !in_array(
            $leaveType,
            ['casual', 'sick', 'earned', 'loss_of_pay'],
            true
        )
        || !in_array(
            $status,
            ['pending', 'approved', 'rejected'],
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Staff, Leave Type and Status are required.'
        );
    }

    $reason = trim((string)($input['reason'] ?? ''));
    if ($reason === '' || mb_strlen($reason) > 500) {
        throw new InvalidArgumentException(
            'Reason is required and must not exceed 500 characters.'
        );
    }

    $days = (new DateTimeImmutable($fromDate))
        ->diff(new DateTimeImmutable($toDate))
        ->days + 1;

    $staffWhere = [
        'id=:staff_id',
        'tenant_id=:tenant_id',
    ];
    $staffParams = [
        'staff_id' => $staffId,
        'tenant_id' => (int)$scope['tenant_id'],
    ];

    if (slColumn($pdo, 'staff_members', 'deleted_at')) {
        $staffWhere[] = 'deleted_at IS NULL';
    }

    if (slColumn($pdo, 'staff_members', 'status')) {
        $staffWhere[] = "status='active'";
    }

    if ((int)$scope['branch_id'] > 0
        && slColumn($pdo, 'staff_members', 'branch_id')) {
        $staffWhere[] = 'branch_id=:branch_id';
        $staffParams['branch_id'] = (int)$scope['branch_id'];
    }

    $staffStatement = $pdo->prepare(
        "SELECT branch_id
         FROM staff_members
         WHERE " . implode(' AND ', $staffWhere) . "
         LIMIT 1"
    );
    $staffStatement->execute($staffParams);
    $branchId = (int)($staffStatement->fetchColumn() ?: 0);

    if ($branchId <= 0) {
        throw new InvalidArgumentException(
            'Selected staff is invalid or inactive.'
        );
    }

    $overlapSql = "
        SELECT COUNT(*)
        FROM staff_leave_requests
        WHERE tenant_id=:tenant_id
          AND staff_id=:staff_id
          AND deleted_at IS NULL
          AND status IN('pending','approved')
          AND NOT(
              to_date<:from_date
              OR from_date>:to_date
          )";

    $overlapParams = [
        'tenant_id' => (int)$scope['tenant_id'],
        'staff_id' => $staffId,
        'from_date' => $fromDate,
        'to_date' => $toDate,
    ];

    if ($id > 0) {
        $overlapSql .= ' AND id<>:id';
        $overlapParams['id'] = $id;
    }

    $overlap = $pdo->prepare($overlapSql);
    $overlap->execute($overlapParams);

    if ((int)$overlap->fetchColumn() > 0) {
        throw new InvalidArgumentException(
            'This staff member already has an overlapping '
            . 'pending or approved leave request.'
        );
    }

    $pdo->beginTransaction();

    try {
        if ($id > 0) {
            slRecord($pdo, $scope, $id);

            $statement = $pdo->prepare(
                "UPDATE staff_leave_requests
                 SET staff_id=:staff_id,
                     branch_id=:branch_id,
                     leave_type=:leave_type,
                     from_date=:from_date,
                     to_date=:to_date,
                     total_days=:total_days,
                     reason=:reason,
                     status=:status,
                     updated_by=:updated_by,
                     decided_by=CASE
                        WHEN :decision_status IN('approved','rejected')
                        THEN :decision_user
                        ELSE NULL
                     END,
                     decided_at=CASE
                        WHEN :decision_time_status IN('approved','rejected')
                        THEN NOW()
                        ELSE NULL
                     END
                 WHERE id=:id
                   AND tenant_id=:tenant_id
                   AND deleted_at IS NULL"
            );
            $statement->execute([
                'staff_id' => $staffId,
                'branch_id' => $branchId,
                'leave_type' => $leaveType,
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'total_days' => $days,
                'reason' => $reason,
                'status' => $status,
                'updated_by' => (int)$scope['user_id'],
                'decision_status' => $status,
                'decision_user' => (int)$scope['user_id'],
                'decision_time_status' => $status,
                'id' => $id,
                'tenant_id' => (int)$scope['tenant_id'],
            ]);
        } else {
            $statement = $pdo->prepare(
                "INSERT INTO staff_leave_requests(
                    tenant_id,branch_id,staff_id,leave_type,
                    from_date,to_date,total_days,reason,status,
                    created_by,updated_by,decided_by,decided_at
                 )
                 VALUES(
                    :tenant_id,:branch_id,:staff_id,:leave_type,
                    :from_date,:to_date,:total_days,:reason,:status,
                    :created_by,:updated_by,
                    CASE
                        WHEN :decision_status IN('approved','rejected')
                        THEN :decision_user
                        ELSE NULL
                    END,
                    CASE
                        WHEN :decision_time_status IN('approved','rejected')
                        THEN NOW()
                        ELSE NULL
                    END
                 )"
            );
            $statement->execute([
                'tenant_id' => (int)$scope['tenant_id'],
                'branch_id' => $branchId,
                'staff_id' => $staffId,
                'leave_type' => $leaveType,
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'total_days' => $days,
                'reason' => $reason,
                'status' => $status,
                'created_by' => (int)$scope['user_id'],
                'updated_by' => (int)$scope['user_id'],
                'decision_status' => $status,
                'decision_user' => (int)$scope['user_id'],
                'decision_time_status' => $status,
            ]);

            $id = (int)$pdo->lastInsertId();
        }

        $pdo->commit();

        return ['id' => $id];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    slOut(false, 'Database connection unavailable.', [], 500);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (
    empty($_SESSION['staff_leave_csrf'])
    || !is_string($_SESSION['staff_leave_csrf'])
) {
    $_SESSION['staff_leave_csrf'] = bin2hex(random_bytes(32));
}

$scope = slScope();

if ($scope['tenant_id'] <= 0 || $scope['user_id'] <= 0) {
    slOut(false, 'Active School login is required.', [], 401);
}

try {
    slEnsure($pdo);
} catch (Throwable $exception) {
    error_log('Staff Leave initialization: ' . $exception->getMessage());
    slOut(
        false,
        'Unable to initialize Staff Leave: ' . $exception->getMessage(),
        [],
        500
    );
}

$input = slInput();
$action = strtolower(trim((string)($input['action'] ?? '')));

try {
    if ($action === 'meta') {
        slRequire('view');

        slOut(
            true,
            'Staff Leave loaded.',
            [
                'staff' => slStaff($pdo, $scope),
                'permissions' => slCapabilities(),
                'csrf_token' => (string)$_SESSION['staff_leave_csrf'],
            ]
        );
    }

    if ($action === 'list') {
        slRequire('view');

        $data = slList($pdo, $scope, $input);
        $data['csrf_token'] = (string)$_SESSION['staff_leave_csrf'];

        slOut(true, 'Leave requests loaded.', $data);
    }

    if ($action === 'detail') {
        slRequire('view');

        slOut(
            true,
            'Leave request loaded.',
            [
                'record' => slRecord(
                    $pdo,
                    $scope,
                    (int)($input['id'] ?? 0)
                ),
                'csrf_token' => (string)$_SESSION['staff_leave_csrf'],
            ]
        );
    }

    if ($action === 'save') {
        slCsrf($input);

        $isUpdate = (int)($input['id'] ?? 0) > 0;
        $saved = slSave($pdo, $scope, $input);
        $saved['csrf_token'] = (string)$_SESSION['staff_leave_csrf'];

        slOut(
            true,
            $isUpdate
                ? 'Leave request updated successfully.'
                : 'Leave request created successfully.',
            $saved
        );
    }

    if ($action === 'status') {
        slRequire('edit');
        slCsrf($input);

        $id = (int)($input['id'] ?? 0);
        $status = strtolower(trim((string)($input['status'] ?? '')));

        if (!in_array($status, ['approved', 'rejected'], true)) {
            throw new InvalidArgumentException('Invalid leave status.');
        }

        $record = slRecord($pdo, $scope, $id);

        $statement = $pdo->prepare(
            "UPDATE staff_leave_requests
             SET status=:status,
                 decided_by=:user_id,
                 decided_at=NOW(),
                 updated_by=:updated_by
             WHERE id=:id
               AND tenant_id=:tenant_id
               AND branch_id=:branch_id
               AND deleted_at IS NULL"
        );
        $statement->execute([
            'status' => $status,
            'user_id' => (int)$scope['user_id'],
            'updated_by' => (int)$scope['user_id'],
            'id' => $id,
            'tenant_id' => (int)$scope['tenant_id'],
            'branch_id' => (int)$record['branch_id'],
        ]);

        slOut(
            true,
            $status === 'approved'
                ? 'Leave request approved successfully.'
                : 'Leave request rejected successfully.',
            ['csrf_token' => (string)$_SESSION['staff_leave_csrf']]
        );
    }

    if ($action === 'delete') {
        slRequire('delete');
        slCsrf($input);

        $id = (int)($input['id'] ?? 0);
        $record = slRecord($pdo, $scope, $id);

        $statement = $pdo->prepare(
            "UPDATE staff_leave_requests
             SET deleted_at=NOW(),
                 deleted_by=:user_id,
                 updated_by=:updated_by
             WHERE id=:id
               AND tenant_id=:tenant_id
               AND branch_id=:branch_id
               AND deleted_at IS NULL"
        );
        $statement->execute([
            'user_id' => (int)$scope['user_id'],
            'updated_by' => (int)$scope['user_id'],
            'id' => $id,
            'tenant_id' => (int)$scope['tenant_id'],
            'branch_id' => (int)$record['branch_id'],
        ]);

        slOut(
            true,
            'Leave request deleted successfully.',
            ['csrf_token' => (string)$_SESSION['staff_leave_csrf']]
        );
    }

    slOut(
        false,
        'Invalid Staff Leave action.',
        [
            'action' => $action,
            'allowed_actions' => [
                'meta',
                'list',
                'detail',
                'save',
                'status',
                'delete',
            ],
        ],
        400
    );
} catch (InvalidArgumentException $exception) {
    slOut(false, $exception->getMessage(), [], 422);
} catch (RuntimeException $exception) {
    $code = (int)$exception->getCode();
    slOut(
        false,
        $exception->getMessage(),
        [],
        $code >= 400 && $code < 600 ? $code : 500
    );
} catch (PDOException $exception) {
    error_log('Staff Leave DB: ' . $exception->getMessage());
    slOut(
        false,
        'Database operation failed while processing Staff Leave.',
        [],
        500
    );
} catch (Throwable $exception) {
    error_log(
        'Staff Leave: '
        . $exception->getMessage()
        . ' in '
        . $exception->getFile()
        . ':'
        . $exception->getLine()
    );

    slOut(
        false,
        'Unable to process Staff Leave request.',
        [],
        500
    );
}

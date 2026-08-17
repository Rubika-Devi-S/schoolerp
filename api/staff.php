<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

if (!defined('SCHOOL_API_PAGE_KEY')) {
    define('SCHOOL_API_PAGE_KEY', 'teachers');
}

require_once dirname(__DIR__) . '/includes/bootstrap.php';
$staffCommonFile = dirname(__DIR__) . '/includes/common.php';
if (is_file($staffCommonFile)) {
    require_once $staffCommonFile;
}

function smOut(bool $success, string $message = '', array $data = [], int $status = 200): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function smInput(): array
{
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if (str_contains($contentType, 'application/json')) {
        $data = json_decode((string) file_get_contents('php://input'), true);
        return is_array($data) ? $data : [];
    }
    return $_POST;
}

function smScope(): array
{
    $user = function_exists('current_user') ? current_user() : [];
    $user = is_array($user) ? $user : [];

    return [
        'tenant_id' => (int) ($user['tenant_id'] ?? $user['school_id'] ?? $_SESSION['tenant_id'] ?? $_SESSION['school_id'] ?? 0),
        'branch_id' => (int) ($user['branch_id'] ?? $user['default_branch_id'] ?? $_SESSION['branch_id'] ?? 0),
        'user_id' => (int) ($user['id'] ?? $user['user_id'] ?? $_SESSION['user_id'] ?? 0),
        'role_id' => (int) ($user['role_id'] ?? $_SESSION['role_id'] ?? 0),
    ];
}

function smCapabilities(): array
{
    return [
        'page_key' => 'teachers',
        'view' => function_exists('school_effective_permission')
            && school_effective_permission('teachers', 'view'),
        'add' => function_exists('school_effective_permission')
            && school_effective_permission('teachers', 'create'),
        'create' => function_exists('school_effective_permission')
            && school_effective_permission('teachers', 'create'),
        'edit' => function_exists('school_effective_permission')
            && school_effective_permission('teachers', 'edit'),
        'delete' => function_exists('school_effective_permission')
            && school_effective_permission('teachers', 'delete'),
    ];
}

function smRequirePermission(string $action): void
{
    $action = strtolower(trim($action));
    $permissionAction = $action === 'add' ? 'create' : $action;
    $allowed = function_exists('school_effective_permission')
        && school_effective_permission('teachers', $permissionAction);

    if (!$allowed) {
        $label = $action === 'add' ? 'add' : $action;
        smOut(false, 'You do not have permission to ' . $label . ' Staff Management.', [], 403);
    }
}

function smCsrf(array $input): void
{
    $sessionToken = (string) ($_SESSION['staff_csrf_token'] ?? '');
    $requestToken = (string) ($input['csrf_token'] ?? '');
    if ($sessionToken === '' || $requestToken === '' || !hash_equals($sessionToken, $requestToken)) {
        smOut(false, 'Invalid or expired CSRF token. Refresh the page.', [], 419);
    }
}

function smTable(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table_name'
    );
    $stmt->execute(['table_name' => $table]);
    return (int) $stmt->fetchColumn() > 0;
}

function smEnsure(PDO $pdo, int $tenantId): void
{
    foreach (['branches', 'users', 'roles'] as $table) {
        if (!smTable($pdo, $table)) {
            throw new RuntimeException('Missing required database table: ' . $table . '.', 500);
        }
    }

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS staff_departments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    department_code VARCHAR(40) NOT NULL,
    department_name VARCHAR(120) NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    display_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_staff_department (tenant_id, department_code),
    KEY idx_staff_department_status (tenant_id, status, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS staff_designations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    department_id BIGINT UNSIGNED DEFAULT NULL,
    designation_code VARCHAR(40) NOT NULL,
    designation_name VARCHAR(120) NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    display_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_staff_designation (tenant_id, designation_code),
    KEY idx_staff_designation_department (tenant_id, department_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS staff_members (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED DEFAULT NULL,
    staff_code VARCHAR(40) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) DEFAULT NULL,
    gender ENUM('male','female','other') NOT NULL DEFAULT 'male',
    date_of_birth DATE DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    mobile VARCHAR(20) NOT NULL,
    alternate_mobile VARCHAR(20) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    department_id BIGINT UNSIGNED NOT NULL,
    designation_id BIGINT UNSIGNED NOT NULL,
    employment_type ENUM('permanent','contract','part_time','temporary') NOT NULL DEFAULT 'permanent',
    joining_date DATE NOT NULL,
    qualification VARCHAR(180) DEFAULT NULL,
    experience_years DECIMAL(5,2) NOT NULL DEFAULT 0,
    basic_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
    emergency_contact_name VARCHAR(150) DEFAULT NULL,
    emergency_contact_mobile VARCHAR(20) DEFAULT NULL,
    notes VARCHAR(500) DEFAULT NULL,
    status ENUM('active','inactive','left') NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED DEFAULT NULL,
    updated_by BIGINT UNSIGNED DEFAULT NULL,
    deleted_at DATETIME DEFAULT NULL,
    deleted_by BIGINT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_staff_code (tenant_id, staff_code),
    KEY idx_staff_email (tenant_id, email),
    KEY idx_staff_list (tenant_id, branch_id, status, department_id),
    KEY idx_staff_designation (tenant_id, designation_id),
    KEY idx_staff_mobile (tenant_id, mobile),
    KEY idx_staff_user (tenant_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $departments = [
        ['ADMIN', 'Administration', 10],
        ['TEACHING', 'Teaching', 20],
        ['ACCOUNTS', 'Accounts', 30],
        ['LIBRARY', 'Library', 40],
        ['TRANSPORT', 'Transport', 50],
        ['MAINTENANCE', 'Maintenance', 60],
        ['SECURITY', 'Security', 70],
    ];

    $departmentStmt = $pdo->prepare(
        "INSERT IGNORE INTO staff_departments
            (tenant_id, department_code, department_name, status, display_order)
         VALUES
            (:tenant_id, :code, :name, 'active', :display_order)"
    );
    foreach ($departments as [$code, $name, $order]) {
        $departmentStmt->execute([
            'tenant_id' => $tenantId,
            'code' => $code,
            'name' => $name,
            'display_order' => $order,
        ]);
    }

    $departmentRows = $pdo->prepare(
        'SELECT id, department_code FROM staff_departments WHERE tenant_id = :tenant_id'
    );
    $departmentRows->execute(['tenant_id' => $tenantId]);
    $departmentMap = [];
    foreach ($departmentRows->fetchAll(PDO::FETCH_ASSOC) as $department) {
        $departmentMap[(string) $department['department_code']] = (int) $department['id'];
    }

    $designations = [
        ['PRINCIPAL', 'Principal', 'ADMIN', 10],
        ['VICE_PRINCIPAL', 'Vice Principal', 'ADMIN', 20],
        ['OFFICE_ASSISTANT', 'Office Assistant', 'ADMIN', 30],
        ['TEACHER', 'Teacher', 'TEACHING', 10],
        ['HEAD_TEACHER', 'Head Teacher', 'TEACHING', 20],
        ['ACCOUNTANT', 'Accountant', 'ACCOUNTS', 10],
        ['LIBRARIAN', 'Librarian', 'LIBRARY', 10],
        ['DRIVER', 'Driver', 'TRANSPORT', 10],
        ['ATTENDER', 'Attender', 'MAINTENANCE', 10],
        ['HOUSEKEEPING', 'Housekeeping', 'MAINTENANCE', 20],
        ['SECURITY_GUARD', 'Security Guard', 'SECURITY', 10],
    ];

    $designationStmt = $pdo->prepare(
        "INSERT IGNORE INTO staff_designations
            (tenant_id, department_id, designation_code, designation_name, status, display_order)
         VALUES
            (:tenant_id, :department_id, :code, :name, 'active', :display_order)"
    );
    foreach ($designations as [$code, $name, $departmentCode, $order]) {
        $designationStmt->execute([
            'tenant_id' => $tenantId,
            'department_id' => $departmentMap[$departmentCode] ?? null,
            'code' => $code,
            'name' => $name,
            'display_order' => $order,
        ]);
    }
}

function smValidDate(?string $value, string $label, bool $required = false): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        if ($required) {
            throw new InvalidArgumentException($label . ' is required.');
        }
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    $invalid = is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0);
    if (!$date || $invalid || $date->format('Y-m-d') !== $value) {
        throw new InvalidArgumentException($label . ' is invalid.');
    }
    return $value;
}

function smPhone(string $value, string $label, bool $required = false): ?string
{
    $value = trim($value);
    if ($value === '') {
        if ($required) {
            throw new InvalidArgumentException($label . ' is required.');
        }
        return null;
    }
    if (!preg_match('/^[0-9+()\-\s]{7,20}$/', $value)) {
        throw new InvalidArgumentException($label . ' is invalid.');
    }
    return $value;
}

function smEmail(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Email address is invalid.');
    }
    return strtolower($value);
}

function smBranch(PDO $pdo, array $scope, int $branchId): int
{
    if ($scope['branch_id'] > 0) {
        $branchId = $scope['branch_id'];
    }
    if ($branchId <= 0) {
        throw new InvalidArgumentException('Branch is required.');
    }

    $stmt = $pdo->prepare(
        "SELECT id FROM branches
         WHERE id = :id AND tenant_id = :tenant_id AND status = 'active'
         LIMIT 1"
    );
    $stmt->execute([
        'id' => $branchId,
        'tenant_id' => $scope['tenant_id'],
    ]);
    if (!$stmt->fetchColumn()) {
        throw new InvalidArgumentException('Selected Branch is invalid or inactive.');
    }
    return $branchId;
}

function smDepartmentDesignation(PDO $pdo, int $tenantId, int $departmentId, int $designationId): void
{
    if ($departmentId <= 0 || $designationId <= 0) {
        throw new InvalidArgumentException('Department and Designation are required.');
    }

    $departmentStmt = $pdo->prepare(
        "SELECT id FROM staff_departments
         WHERE id = :id AND tenant_id = :tenant_id AND status = 'active'
         LIMIT 1"
    );
    $departmentStmt->execute(['id' => $departmentId, 'tenant_id' => $tenantId]);
    if (!$departmentStmt->fetchColumn()) {
        throw new InvalidArgumentException('Selected Department is invalid or inactive.');
    }

    $designationStmt = $pdo->prepare(
        "SELECT id FROM staff_designations
         WHERE id = :id AND tenant_id = :tenant_id AND status = 'active'
           AND (department_id IS NULL OR department_id = :department_id)
         LIMIT 1"
    );
    $designationStmt->execute([
        'id' => $designationId,
        'tenant_id' => $tenantId,
        'department_id' => $departmentId,
    ]);
    if (!$designationStmt->fetchColumn()) {
        throw new InvalidArgumentException('Selected Designation does not belong to the selected Department.');
    }
}

function smNextCode(PDO $pdo, int $tenantId): string
{
    $stmt = $pdo->prepare(
        "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(staff_code, '-', -1) AS UNSIGNED)), 0)
         FROM staff_members
         WHERE tenant_id = :tenant_id AND staff_code REGEXP '^STF-[0-9]+$'"
    );
    $stmt->execute(['tenant_id' => $tenantId]);
    $number = (int) $stmt->fetchColumn() + 1;

    for ($attempt = 0; $attempt < 100; $attempt++, $number++) {
        $code = 'STF-' . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
        $check = $pdo->prepare(
            'SELECT COUNT(*) FROM staff_members WHERE tenant_id = :tenant_id AND staff_code = :staff_code'
        );
        $check->execute(['tenant_id' => $tenantId, 'staff_code' => $code]);
        if ((int) $check->fetchColumn() === 0) {
            return $code;
        }
    }

    throw new RuntimeException('Unable to generate Staff Code.');
}

function smLog(PDO $pdo, array $scope, string $action, int $recordId, ?array $old, ?array $new, string $description): void
{
    if (!smTable($pdo, 'activity_logs')) {
        return;
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO activity_logs
                (tenant_id, branch_id, user_id, role_id, module_name, action_key, table_name, record_id,
                 old_values, new_values, description, ip_address, user_agent)
             VALUES
                (:tenant_id, :branch_id, :user_id, :role_id, 'staff', :action_key, 'staff_members', :record_id,
                 :old_values, :new_values, :description, :ip_address, :user_agent)"
        );
        $stmt->execute([
            'tenant_id' => $scope['tenant_id'],
            'branch_id' => $scope['branch_id'] > 0 ? $scope['branch_id'] : null,
            'user_id' => $scope['user_id'],
            'role_id' => $scope['role_id'] > 0 ? $scope['role_id'] : null,
            'action_key' => $action,
            'record_id' => $recordId,
            'old_values' => $old ? json_encode($old, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'new_values' => $new ? json_encode($new, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'description' => $description,
            'ip_address' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1000) ?: null,
        ]);
    } catch (Throwable) {
        // Activity logging must never block the main staff transaction.
    }
}

function smMeta(PDO $pdo, array $scope): array
{
    if ($scope['branch_id'] > 0) {
        $branches = $pdo->prepare(
            "SELECT id, branch_code, branch_name
             FROM branches
             WHERE tenant_id = :tenant_id AND id = :branch_id AND status = 'active'
             ORDER BY is_main DESC, branch_name"
        );
        $branches->execute([
            'tenant_id' => $scope['tenant_id'],
            'branch_id' => $scope['branch_id'],
        ]);
    } else {
        $branches = $pdo->prepare(
            "SELECT id, branch_code, branch_name
             FROM branches
             WHERE tenant_id = :tenant_id AND status = 'active'
             ORDER BY is_main DESC, branch_name"
        );
        $branches->execute(['tenant_id' => $scope['tenant_id']]);
    }

    $departments = $pdo->prepare(
        "SELECT id, department_code, department_name, status
         FROM staff_departments
         WHERE tenant_id = :tenant_id
         ORDER BY display_order, department_name"
    );
    $departments->execute(['tenant_id' => $scope['tenant_id']]);

    $designations = $pdo->prepare(
        "SELECT id, department_id, designation_code, designation_name, status
         FROM staff_designations
         WHERE tenant_id = :tenant_id
         ORDER BY display_order, designation_name"
    );
    $designations->execute(['tenant_id' => $scope['tenant_id']]);

    return [
        'branches' => $branches->fetchAll(PDO::FETCH_ASSOC),
        'departments' => $departments->fetchAll(PDO::FETCH_ASSOC),
        'designations' => $designations->fetchAll(PDO::FETCH_ASSOC),
        'capabilities' => smCapabilities(),
        'csrf_token' => $_SESSION['staff_csrf_token'],
    ];
}

function smBaseWhere(array $scope, array $filters, array &$params): array
{
    $where = [
        's.tenant_id = :tenant_id',
        's.deleted_at IS NULL',
    ];
    $params = ['tenant_id' => $scope['tenant_id']];

    if ($scope['branch_id'] > 0) {
        $where[] = 's.branch_id = :scope_branch_id';
        $params['scope_branch_id'] = $scope['branch_id'];
    } else {
        $branchId = (int) ($filters['branch_id'] ?? 0);
        if ($branchId > 0) {
            $where[] = 's.branch_id = :branch_id';
            $params['branch_id'] = $branchId;
        }
    }

    $departmentId = (int) ($filters['department_id'] ?? 0);
    if ($departmentId > 0) {
        $where[] = 's.department_id = :department_id';
        $params['department_id'] = $departmentId;
    }

    $designationId = (int) ($filters['designation_id'] ?? 0);
    if ($designationId > 0) {
        $where[] = 's.designation_id = :designation_id';
        $params['designation_id'] = $designationId;
    }

    $status = strtolower(trim((string) ($filters['status'] ?? 'all')));
    if (in_array($status, ['active', 'inactive', 'left'], true)) {
        $where[] = 's.status = :status';
        $params['status'] = $status;
    }

    $employmentType = strtolower(trim((string) ($filters['employment_type'] ?? 'all')));
    if (in_array($employmentType, ['permanent', 'contract', 'part_time', 'temporary'], true)) {
        $where[] = 's.employment_type = :employment_type';
        $params['employment_type'] = $employmentType;
    }

    $search = trim((string) ($filters['search'] ?? ''));
    if ($search !== '') {
        $where[] = "(
            s.staff_code LIKE :search_code OR
            s.first_name LIKE :search_first OR
            s.last_name LIKE :search_last OR
            CONCAT_WS(' ', s.first_name, s.last_name) LIKE :search_full OR
            s.mobile LIKE :search_mobile OR
            s.email LIKE :search_email
        )";
        $value = '%' . $search . '%';
        $params['search_code'] = $value;
        $params['search_first'] = $value;
        $params['search_last'] = $value;
        $params['search_full'] = $value;
        $params['search_mobile'] = $value;
        $params['search_email'] = $value;
    }

    return $where;
}

function smStats(PDO $pdo, array $scope): array
{
    $where = ['s.tenant_id = :tenant_id', 's.deleted_at IS NULL'];
    $params = ['tenant_id' => $scope['tenant_id']];
    if ($scope['branch_id'] > 0) {
        $where[] = 's.branch_id = :branch_id';
        $params['branch_id'] = $scope['branch_id'];
    }

    $stmt = $pdo->prepare(
        "SELECT
            COUNT(*) AS total_staff,
            SUM(CASE WHEN s.status = 'active' THEN 1 ELSE 0 END) AS active_staff,
            SUM(CASE WHEN d.department_code = 'TEACHING' AND s.status = 'active' THEN 1 ELSE 0 END) AS teaching_staff,
            COUNT(DISTINCT CASE WHEN s.status <> 'left' THEN s.department_id END) AS used_departments
         FROM staff_members s
         LEFT JOIN staff_departments d
           ON d.id = s.department_id AND d.tenant_id = s.tenant_id
         WHERE " . implode(' AND ', $where)
    );
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'total_staff' => (int) ($row['total_staff'] ?? 0),
        'active_staff' => (int) ($row['active_staff'] ?? 0),
        'teaching_staff' => (int) ($row['teaching_staff'] ?? 0),
        'used_departments' => (int) ($row['used_departments'] ?? 0),
    ];
}

function smList(PDO $pdo, array $scope, array $filters): array
{
    $params = [];
    $where = smBaseWhere($scope, $filters, $params);

    $page = max(1, (int) ($filters['page'] ?? 1));
    $perPage = min(5000, max(5, (int) ($filters['per_page'] ?? 10)));

    $countStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM staff_members s WHERE ' . implode(' AND ', $where)
    );
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();
    $lastPage = max(1, (int) ceil($total / $perPage));
    $page = min($page, $lastPage);
    $offset = ($page - 1) * $perPage;

    $stmt = $pdo->prepare(
        "SELECT
            s.*,
            TRIM(CONCAT(s.first_name, CASE WHEN COALESCE(s.last_name, '') = '' THEN '' ELSE CONCAT(' ', s.last_name) END)) AS staff_name,
            b.branch_name,
            d.department_name,
            d.department_code,
            g.designation_name,
            DATE_FORMAT(s.joining_date, '%d-%m-%Y') AS joining_date_display,
            DATE_FORMAT(s.date_of_birth, '%d-%m-%Y') AS date_of_birth_display
         FROM staff_members s
         INNER JOIN branches b
           ON b.id = s.branch_id AND b.tenant_id = s.tenant_id
         INNER JOIN staff_departments d
           ON d.id = s.department_id AND d.tenant_id = s.tenant_id
         INNER JOIN staff_designations g
           ON g.id = s.designation_id AND g.tenant_id = s.tenant_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY
            CASE s.status WHEN 'active' THEN 1 WHEN 'inactive' THEN 2 ELSE 3 END,
            d.display_order,
            g.display_order,
            s.first_name,
            s.last_name
         LIMIT {$perPage} OFFSET {$offset}"
    );
    $stmt->execute($params);

    return [
        'records' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'stats' => smStats($pdo, $scope),
        'pagination' => [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => $lastPage,
        ],
    ];
}

function smRecord(PDO $pdo, array $scope, int $id, bool $lock = false): array
{
    if ($id <= 0) {
        throw new InvalidArgumentException('Staff record is invalid.');
    }

    $where = [
        's.id = :id',
        's.tenant_id = :tenant_id',
        's.deleted_at IS NULL',
    ];
    $params = ['id' => $id, 'tenant_id' => $scope['tenant_id']];
    if ($scope['branch_id'] > 0) {
        $where[] = 's.branch_id = :branch_id';
        $params['branch_id'] = $scope['branch_id'];
    }

    $stmt = $pdo->prepare(
        "SELECT
            s.*,
            TRIM(CONCAT(s.first_name, CASE WHEN COALESCE(s.last_name, '') = '' THEN '' ELSE CONCAT(' ', s.last_name) END)) AS staff_name,
            b.branch_name,
            d.department_name,
            g.designation_name,
            DATE_FORMAT(s.joining_date, '%d-%m-%Y') AS joining_date_display,
            DATE_FORMAT(s.date_of_birth, '%d-%m-%Y') AS date_of_birth_display
         FROM staff_members s
         INNER JOIN branches b ON b.id = s.branch_id AND b.tenant_id = s.tenant_id
         INNER JOIN staff_departments d ON d.id = s.department_id AND d.tenant_id = s.tenant_id
         INNER JOIN staff_designations g ON g.id = s.designation_id AND g.tenant_id = s.tenant_id
         WHERE " . implode(' AND ', $where) .
         ' LIMIT 1' . ($lock ? ' FOR UPDATE' : '')
    );
    $stmt->execute($params);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$record) {
        throw new InvalidArgumentException('Staff record was not found.');
    }
    return $record;
}

function smSave(PDO $pdo, array $scope, array $input): array
{
    smCsrf($input);

    $id = (int) ($input['id'] ?? 0);
    smRequirePermission($id > 0 ? 'edit' : 'add');
    $branchId = smBranch($pdo, $scope, (int) ($input['branch_id'] ?? 0));

    $firstName = trim((string) ($input['first_name'] ?? ''));
    $lastName = trim((string) ($input['last_name'] ?? ''));
    if ($firstName === '') {
        throw new InvalidArgumentException('First Name is required.');
    }
    if (mb_strlen($firstName) > 100 || mb_strlen($lastName) > 100) {
        throw new InvalidArgumentException('Staff name is too long.');
    }

    $staffCode = strtoupper(trim((string) ($input['staff_code'] ?? '')));
    if ($staffCode !== '' && !preg_match('/^[A-Z0-9_\-\/]{2,40}$/', $staffCode)) {
        throw new InvalidArgumentException('Staff Code may contain letters, numbers, hyphen, slash and underscore only.');
    }

    $gender = strtolower(trim((string) ($input['gender'] ?? 'male')));
    if (!in_array($gender, ['male', 'female', 'other'], true)) {
        throw new InvalidArgumentException('Gender is invalid.');
    }

    $dateOfBirth = smValidDate((string) ($input['date_of_birth'] ?? ''), 'Date of Birth');
    if ($dateOfBirth !== null && $dateOfBirth >= date('Y-m-d')) {
        throw new InvalidArgumentException('Date of Birth must be before today.');
    }

    $joiningDate = smValidDate((string) ($input['joining_date'] ?? ''), 'Joining Date', true);
    $email = smEmail((string) ($input['email'] ?? ''));
    $mobile = smPhone((string) ($input['mobile'] ?? ''), 'Mobile Number', true);
    $alternateMobile = smPhone((string) ($input['alternate_mobile'] ?? ''), 'Alternate Mobile');
    $emergencyMobile = smPhone((string) ($input['emergency_contact_mobile'] ?? ''), 'Emergency Contact Mobile');

    $departmentId = (int) ($input['department_id'] ?? 0);
    $designationId = (int) ($input['designation_id'] ?? 0);
    smDepartmentDesignation($pdo, $scope['tenant_id'], $departmentId, $designationId);

    $employmentType = strtolower(trim((string) ($input['employment_type'] ?? 'permanent')));
    if (!in_array($employmentType, ['permanent', 'contract', 'part_time', 'temporary'], true)) {
        throw new InvalidArgumentException('Employment Type is invalid.');
    }

    $status = strtolower(trim((string) ($input['status'] ?? 'active')));
    if (!in_array($status, ['active', 'inactive', 'left'], true)) {
        throw new InvalidArgumentException('Staff Status is invalid.');
    }

    $experienceYears = round((float) ($input['experience_years'] ?? 0), 2);
    $basicSalary = round((float) ($input['basic_salary'] ?? 0), 2);
    if ($experienceYears < 0 || $experienceYears > 80) {
        throw new InvalidArgumentException('Experience Years must be between 0 and 80.');
    }
    if ($basicSalary < 0 || $basicSalary > 9999999999) {
        throw new InvalidArgumentException('Basic Salary is invalid.');
    }

    $qualification = trim((string) ($input['qualification'] ?? ''));
    $address = trim((string) ($input['address'] ?? ''));
    $emergencyName = trim((string) ($input['emergency_contact_name'] ?? ''));
    $notes = trim((string) ($input['notes'] ?? ''));

    if (mb_strlen($qualification) > 180 || mb_strlen($emergencyName) > 150 || mb_strlen($notes) > 500) {
        throw new InvalidArgumentException('One or more text fields exceed the allowed length.');
    }

    $pdo->beginTransaction();
    try {
        $old = null;
        if ($id > 0) {
            $old = smRecord($pdo, $scope, $id, true);
        }

        if ($staffCode === '') {
            $staffCode = $id > 0 ? (string) $old['staff_code'] : smNextCode($pdo, $scope['tenant_id']);
        }

        $duplicateCode = $pdo->prepare(
            "SELECT id FROM staff_members
             WHERE tenant_id = :tenant_id AND staff_code = :staff_code
               AND deleted_at IS NULL AND id <> :id
             LIMIT 1"
        );
        $duplicateCode->execute([
            'tenant_id' => $scope['tenant_id'],
            'staff_code' => $staffCode,
            'id' => $id,
        ]);
        if ($duplicateCode->fetchColumn()) {
            throw new InvalidArgumentException('Staff Code already exists.');
        }

        if ($email !== null) {
            $duplicateEmail = $pdo->prepare(
                "SELECT id FROM staff_members
                 WHERE tenant_id = :tenant_id AND email = :email
                   AND deleted_at IS NULL AND id <> :id
                 LIMIT 1"
            );
            $duplicateEmail->execute([
                'tenant_id' => $scope['tenant_id'],
                'email' => $email,
                'id' => $id,
            ]);
            if ($duplicateEmail->fetchColumn()) {
                throw new InvalidArgumentException('Email address is already used by another staff member.');
            }
        }

        $data = [
            'tenant_id' => $scope['tenant_id'],
            'branch_id' => $branchId,
            'staff_code' => $staffCode,
            'first_name' => $firstName,
            'last_name' => $lastName !== '' ? $lastName : null,
            'gender' => $gender,
            'date_of_birth' => $dateOfBirth,
            'email' => $email,
            'mobile' => $mobile,
            'alternate_mobile' => $alternateMobile,
            'address' => $address !== '' ? $address : null,
            'department_id' => $departmentId,
            'designation_id' => $designationId,
            'employment_type' => $employmentType,
            'joining_date' => $joiningDate,
            'qualification' => $qualification !== '' ? $qualification : null,
            'experience_years' => $experienceYears,
            'basic_salary' => $basicSalary,
            'emergency_contact_name' => $emergencyName !== '' ? $emergencyName : null,
            'emergency_contact_mobile' => $emergencyMobile,
            'notes' => $notes !== '' ? $notes : null,
            'status' => $status,
            'created_by' => $scope['user_id'],
            'updated_by' => $scope['user_id'],
        ];

        if ($id > 0) {
            $stmt = $pdo->prepare(
                "UPDATE staff_members SET
                    branch_id = :branch_id,
                    staff_code = :staff_code,
                    first_name = :first_name,
                    last_name = :last_name,
                    gender = :gender,
                    date_of_birth = :date_of_birth,
                    email = :email,
                    mobile = :mobile,
                    alternate_mobile = :alternate_mobile,
                    address = :address,
                    department_id = :department_id,
                    designation_id = :designation_id,
                    employment_type = :employment_type,
                    joining_date = :joining_date,
                    qualification = :qualification,
                    experience_years = :experience_years,
                    basic_salary = :basic_salary,
                    emergency_contact_name = :emergency_contact_name,
                    emergency_contact_mobile = :emergency_contact_mobile,
                    notes = :notes,
                    status = :status,
                    updated_by = :updated_by
                 WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL"
            );
            $updateData = $data;
            unset($updateData['created_by']);
            $stmt->execute($updateData + ['id' => $id]);
            $message = 'Staff details updated successfully.';
            $action = 'update';
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO staff_members
                    (tenant_id, branch_id, staff_code, first_name, last_name, gender, date_of_birth,
                     email, mobile, alternate_mobile, address, department_id, designation_id,
                     employment_type, joining_date, qualification, experience_years, basic_salary,
                     emergency_contact_name, emergency_contact_mobile, notes, status, created_by, updated_by)
                 VALUES
                    (:tenant_id, :branch_id, :staff_code, :first_name, :last_name, :gender, :date_of_birth,
                     :email, :mobile, :alternate_mobile, :address, :department_id, :designation_id,
                     :employment_type, :joining_date, :qualification, :experience_years, :basic_salary,
                     :emergency_contact_name, :emergency_contact_mobile, :notes, :status, :created_by, :updated_by)"
            );
            $stmt->execute($data);
            $id = (int) $pdo->lastInsertId();
            $message = 'Staff created successfully. Staff Code: ' . $staffCode;
            $action = 'create';
        }

        $new = smRecord($pdo, $scope, $id, false);
        smLog(
            $pdo,
            $scope,
            $action,
            $id,
            $old,
            $new,
            $action === 'create' ? 'Created staff ' . $staffCode : 'Updated staff ' . $staffCode
        );

        $pdo->commit();
        return [
            'id' => $id,
            'staff_code' => $staffCode,
            'csrf_token' => $_SESSION['staff_csrf_token'],
            'message' => $message,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function smUpdateStatus(PDO $pdo, array $scope, array $input): string
{
    smCsrf($input);
    smRequirePermission('edit');
    $id = (int) ($input['id'] ?? 0);
    $status = strtolower(trim((string) ($input['status'] ?? '')));
    if (!in_array($status, ['active', 'inactive', 'left'], true)) {
        throw new InvalidArgumentException('Status is invalid.');
    }

    $pdo->beginTransaction();
    try {
        $old = smRecord($pdo, $scope, $id, true);
        $stmt = $pdo->prepare(
            "UPDATE staff_members
             SET status = :status, updated_by = :updated_by
             WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL"
        );
        $stmt->execute([
            'status' => $status,
            'updated_by' => $scope['user_id'],
            'id' => $id,
            'tenant_id' => $scope['tenant_id'],
        ]);
        $new = smRecord($pdo, $scope, $id);
        smLog($pdo, $scope, 'status', $id, $old, $new, 'Changed staff status to ' . $status);
        $pdo->commit();
        return 'Staff status updated successfully.';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function smDelete(PDO $pdo, array $scope, array $input): string
{
    smCsrf($input);
    smRequirePermission('delete');
    $id = (int) ($input['id'] ?? 0);

    $pdo->beginTransaction();
    try {
        $old = smRecord($pdo, $scope, $id, true);
        $stmt = $pdo->prepare(
            "UPDATE staff_members
             SET status = 'left', deleted_at = NOW(), deleted_by = :deleted_by, updated_by = :updated_by
             WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL"
        );
        $stmt->execute([
            'deleted_by' => $scope['user_id'],
            'updated_by' => $scope['user_id'],
            'id' => $id,
            'tenant_id' => $scope['tenant_id'],
        ]);
        smLog($pdo, $scope, 'delete', $id, $old, null, 'Deleted staff ' . $old['staff_code']);
        $pdo->commit();
        return 'Staff record deleted successfully.';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

if (!isset($pdo) || !$pdo instanceof PDO) {
    smOut(false, 'Database connection unavailable.', [], 500);
}
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['staff_csrf_token']) || !is_string($_SESSION['staff_csrf_token'])) {
    $_SESSION['staff_csrf_token'] = bin2hex(random_bytes(32));
}

$scope = smScope();
if ($scope['tenant_id'] <= 0 || $scope['user_id'] <= 0) {
    smOut(false, 'Tenant or user session is missing.', [], 401);
}

$input = smInput();
$action = strtolower(trim((string) ($input['action'] ?? $_GET['action'] ?? '')));

$permissionAction = match ($action) {
    'meta', 'list', 'detail', 'export' => 'view',
    'save' => (int)($input['id'] ?? 0) > 0 ? 'edit' : 'add',
    'status' => 'edit',
    'delete' => 'delete',
    default => '',
};

if ($permissionAction !== '') {
    smRequirePermission($permissionAction);
}

try {
    smEnsure($pdo, $scope['tenant_id']);
} catch (Throwable $e) {
    smOut(false, 'Unable to initialize Staff Management: ' . $e->getMessage(), [], 500);
}

try {
    if ($action === 'meta') {
        smOut(true, 'Staff metadata loaded.', smMeta($pdo, $scope));
    }

    if ($action === 'list') {
        smOut(true, 'Staff loaded.', smList($pdo, $scope, array_merge($_GET, $input)));
    }

    if ($action === 'detail') {
        $record = smRecord($pdo, $scope, (int) ($_GET['id'] ?? 0));
        smOut(true, 'Staff details loaded.', ['record' => $record]);
    }

    if ($action === 'save') {
        $result = smSave($pdo, $scope, $input);
        smOut(true, $result['message'], $result);
    }

    if ($action === 'status') {
        smOut(true, smUpdateStatus($pdo, $scope, $input), ['csrf_token' => $_SESSION['staff_csrf_token']]);
    }

    if ($action === 'delete') {
        smOut(true, smDelete($pdo, $scope, $input), ['csrf_token' => $_SESSION['staff_csrf_token']]);
    }

    if ($action === 'export') {
        $result = smList($pdo, $scope, array_merge($_GET, ['page' => 1, 'per_page' => 5000]));
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="staff-report-' . date('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, [
            'Staff Code', 'Staff Name', 'Gender', 'Mobile', 'Email', 'Branch',
            'Department', 'Designation', 'Employment Type', 'Joining Date',
            'Qualification', 'Experience Years', 'Basic Salary', 'Status',
        ]);
        foreach ($result['records'] as $row) {
            fputcsv($out, [
                $row['staff_code'],
                $row['staff_name'],
                ucfirst($row['gender']),
                $row['mobile'],
                $row['email'],
                $row['branch_name'],
                $row['department_name'],
                $row['designation_name'],
                ucwords(str_replace('_', ' ', $row['employment_type'])),
                $row['joining_date'],
                $row['qualification'],
                $row['experience_years'],
                $row['basic_salary'],
                ucfirst($row['status']),
            ]);
        }
        fclose($out);
        exit;
    }

    smOut(false, 'Invalid Staff Management action.', [], 400);
} catch (InvalidArgumentException $e) {
    smOut(false, $e->getMessage(), [], 422);
} catch (PDOException $e) {
    $message = str_contains(strtolower($e->getMessage()), 'duplicate')
        ? 'A staff record with the same unique details already exists.'
        : 'Database operation failed while processing Staff Management.';
    smOut(false, $message, [], 500);
} catch (Throwable $e) {
    smOut(false, $e->getMessage() !== '' ? $e->getMessage() : 'Unexpected Staff Management error.', [], 500);
}

<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

const USER_MANAGEMENT_BUILD = '2026-08-05-staff-login-management-v2';
const USER_PHOTO_MAX_BYTES = 3145728; // 3 MB

function umOut(bool $success, string $message = '', array $data = [], int $status = 200): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }

    echo json_encode(
        ['success' => $success, 'message' => $message, 'data' => $data],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

function umInput(): array
{
    $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    if (str_contains($contentType, 'application/json')) {
        $decoded = json_decode((string)file_get_contents('php://input'), true);
        return is_array($decoded) ? $decoded : [];
    }
    return $_POST;
}

function umTable(PDO $pdo, string $table): bool
{
    $statement = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = :table_name"
    );
    $statement->execute(['table_name' => $table]);
    return (int)$statement->fetchColumn() > 0;
}

function umColumn(PDO $pdo, string $table, string $column): bool
{
    $statement = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = :table_name
           AND column_name = :column_name"
    );
    $statement->execute(['table_name' => $table, 'column_name' => $column]);
    return (int)$statement->fetchColumn() > 0;
}

function umIndex(PDO $pdo, string $table, string $index): bool
{
    $statement = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.statistics
         WHERE table_schema = DATABASE()
           AND table_name = :table_name
           AND index_name = :index_name"
    );
    $statement->execute(['table_name' => $table, 'index_name' => $index]);
    return (int)$statement->fetchColumn() > 0;
}

function umScope(PDO $pdo): array
{
    $user = function_exists('current_user') ? current_user() : [];
    $user = is_array($user) ? $user : [];

    $scope = [
        'tenant_id' => (int)($user['tenant_id'] ?? $user['school_id'] ?? $_SESSION['tenant_id'] ?? $_SESSION['school_id'] ?? 0),
        'branch_id' => (int)($user['branch_id'] ?? $user['default_branch_id'] ?? $_SESSION['branch_id'] ?? 0),
        'user_id' => (int)($user['id'] ?? $user['user_id'] ?? $_SESSION['user_id'] ?? 0),
        'role_id' => (int)($user['role_id'] ?? $_SESSION['role_id'] ?? 0),
        'role_key' => strtolower(trim((string)($user['role_key'] ?? $_SESSION['role_key'] ?? ''))),
        'role_name' => strtolower(trim((string)($user['role_name'] ?? $_SESSION['role_name'] ?? ''))),
    ];

    if ($scope['role_key'] === '' && $scope['role_id'] > 0 && umTable($pdo, 'roles')) {
        $statement = $pdo->prepare(
            "SELECT role_key, role_name FROM roles WHERE id = :role_id LIMIT 1"
        );
        $statement->execute(['role_id' => $scope['role_id']]);
        $role = $statement->fetch(PDO::FETCH_ASSOC);
        if ($role) {
            $scope['role_key'] = strtolower(trim((string)$role['role_key']));
            $scope['role_name'] = strtolower(trim((string)$role['role_name']));
        }
    }

    return $scope;
}

function umIsSuperAdmin(array $scope): bool
{
    return (int)$scope['role_id'] === 1
        || in_array((string)$scope['role_key'], ['super_admin', 'super-administrator', 'super_administrator'], true)
        || in_array((string)$scope['role_name'], ['super administrator', 'super admin'], true);
}

function umIsSchoolAdmin(array $scope): bool
{
    return (int)$scope['role_id'] === 4
        || in_array((string)$scope['role_key'], ['school_admin', 'school-administrator', 'school_administrator', 'admin'], true)
        || in_array((string)$scope['role_name'], ['school administrator', 'school admin', 'administrator', 'admin'], true);
}

function umCan(array $scope, string $action): bool
{
    if (umIsSuperAdmin($scope) || umIsSchoolAdmin($scope)) {
        return true;
    }

    if (defined('APP_DEMO_MODE') && APP_DEMO_MODE) {
        return true;
    }

    if (function_exists('has_permission')) {
        foreach (['users', 'user_management', 'school_user_management'] as $permissionKey) {
            try {
                if ((bool)has_permission($permissionKey, $action)) {
                    return true;
                }
            } catch (Throwable $e) {
                error_log('User permission check ' . $permissionKey . '/' . $action . ': ' . $e->getMessage());
            }
        }
    }

    return false;
}

function umRequire(array $scope, string $action): void
{
    if (!umCan($scope, $action)) {
        umOut(false, 'You do not have permission to ' . $action . ' staff login accounts.', [], 403);
    }
}

function umCsrf(array $input): void
{
    $stored = (string)($_SESSION['user_management_csrf_token'] ?? '');
    $given = (string)($input['csrf_token'] ?? '');
    if ($stored === '' || $given === '' || !hash_equals($stored, $given)) {
        umOut(false, 'Invalid or expired CSRF token. Refresh the page.', [], 419);
    }
}

function umEnsure(PDO $pdo): void
{
    foreach (['users', 'roles', 'branches', 'staff_members', 'staff_departments', 'staff_designations'] as $table) {
        if (!umTable($pdo, $table)) {
            throw new RuntimeException('Missing required database table: ' . $table . '.');
        }
    }

    $requiredUserColumns = [
        'id', 'tenant_id', 'default_branch_id', 'role_id', 'employee_id',
        'name', 'email', 'mobile', 'username', 'password_hash', 'profile_photo',
        'status', 'last_login_at', 'created_at', 'updated_at', 'deleted_at',
    ];
    foreach ($requiredUserColumns as $column) {
        if (!umColumn($pdo, 'users', $column)) {
            throw new RuntimeException('The users table is missing the required column: ' . $column . '.');
        }
    }

    if (!umColumn($pdo, 'staff_members', 'user_id')) {
        $pdo->exec("ALTER TABLE staff_members ADD COLUMN user_id BIGINT UNSIGNED NULL AFTER branch_id");
    }
    if (!umIndex($pdo, 'staff_members', 'idx_staff_user')) {
        $pdo->exec("ALTER TABLE staff_members ADD KEY idx_staff_user(tenant_id, user_id)");
    }
}

function umAssetUrl(?string $relativePath): ?string
{
    $relativePath = trim((string)$relativePath);
    if ($relativePath === '') {
        return null;
    }
    if (preg_match('#^https?://#i', $relativePath) === 1) {
        return $relativePath;
    }
    if (function_exists('app_url')) {
        return app_url($relativePath);
    }
    if (defined('BASE_URL')) {
        return rtrim((string)BASE_URL, '/') . '/' . ltrim($relativePath, '/');
    }
    return '../' . ltrim($relativePath, '/');
}

function umTenantCode(PDO $pdo, int $tenantId): string
{
    if (umTable($pdo, 'tenants') && umColumn($pdo, 'tenants', 'tenant_code')) {
        $statement = $pdo->prepare('SELECT tenant_code FROM tenants WHERE id = :tenant_id LIMIT 1');
        $statement->execute(['tenant_id' => $tenantId]);
        return strtoupper(preg_replace('/[^A-Z0-9]+/', '', (string)$statement->fetchColumn()) ?: 'SCH');
    }
    return 'SCH';
}

function umUserCode(PDO $pdo, int $tenantId, int $userId): string
{
    return sprintf('USR-%s-%06d', umTenantCode($pdo, $tenantId), $userId);
}

function umRoles(PDO $pdo, array $scope): array
{
    $conditions = [
        "status = 'active'",
        '(tenant_id = :tenant_id OR tenant_id IS NULL)',
    ];
    if (umColumn($pdo, 'roles', 'deleted_at')) {
        $conditions[] = 'deleted_at IS NULL';
    }
    if (!umIsSuperAdmin($scope)) {
        $conditions[] = "role_scope = 'school'";
        $conditions[] = "role_key <> 'super_admin'";
    }

    $statement = $pdo->prepare(
        "SELECT id, role_key, role_name, role_scope, is_system
         FROM roles
         WHERE " . implode(' AND ', $conditions) . "
         ORDER BY is_system DESC, role_name, id"
    );
    $statement->execute(['tenant_id' => $scope['tenant_id']]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function umRole(PDO $pdo, array $scope, int $roleId): array
{
    $whereDeleted = umColumn($pdo, 'roles', 'deleted_at') ? 'AND deleted_at IS NULL' : '';
    $statement = $pdo->prepare(
        "SELECT id, tenant_id, role_key, role_name, role_scope, is_system, status
         FROM roles
         WHERE id = :role_id
           AND status = 'active'
           AND (tenant_id = :tenant_id OR tenant_id IS NULL)
           $whereDeleted
         LIMIT 1"
    );
    $statement->execute(['role_id' => $roleId, 'tenant_id' => $scope['tenant_id']]);
    $role = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$role) {
        throw new InvalidArgumentException('Select a valid active Role.');
    }

    $roleKey = strtolower(trim((string)$role['role_key']));
    if (!umIsSuperAdmin($scope) && ($roleKey === 'super_admin' || (string)$role['role_scope'] === 'platform')) {
        throw new RuntimeException('Only a Super Administrator can assign this role.');
    }
    return $role;
}

function umValidatePassword(string $password, string $confirmPassword): void
{
    if (mb_strlen($password) < 6) {
        throw new InvalidArgumentException('Password must contain at least 6 characters. A 6-digit numeric password is allowed.');
    }
    if (!hash_equals($password, $confirmPassword)) {
        throw new InvalidArgumentException('Password confirmation does not match.');
    }
}

function umStorePhoto(array $scope): array
{
    if (!isset($_FILES['profile_photo']) || !is_array($_FILES['profile_photo'])) {
        return ['relative' => null, 'absolute' => null];
    }

    $file = $_FILES['profile_photo'];
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return ['relative' => null, 'absolute' => null];
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('Profile Photo upload failed. Error code: ' . $error . '.');
    }

    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > USER_PHOTO_MAX_BYTES) {
        throw new InvalidArgumentException('Profile Photo must be an image smaller than 3 MB.');
    }

    $temporary = (string)($file['tmp_name'] ?? '');
    if ($temporary === '' || !is_uploaded_file($temporary)) {
        throw new InvalidArgumentException('The uploaded Profile Photo is invalid.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporary) ?: '';
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($extensions[$mime])) {
        throw new InvalidArgumentException('Profile Photo must be JPG, PNG or WEBP.');
    }

    $baseDirectory = dirname(__DIR__) . '/uploads/user-profiles/' . $scope['tenant_id'];
    if (!is_dir($baseDirectory) && !mkdir($baseDirectory, 0775, true) && !is_dir($baseDirectory)) {
        throw new RuntimeException('Unable to create the Profile Photo upload directory.');
    }

    $filename = 'user-' . bin2hex(random_bytes(10)) . '.' . $extensions[$mime];
    $absolute = $baseDirectory . '/' . $filename;
    if (!move_uploaded_file($temporary, $absolute)) {
        throw new RuntimeException('Unable to save the Profile Photo.');
    }

    return [
        'relative' => 'uploads/user-profiles/' . $scope['tenant_id'] . '/' . $filename,
        'absolute' => $absolute,
    ];
}

function umDeletePhoto(?string $relativePath): void
{
    $relativePath = trim((string)$relativePath);
    if ($relativePath === '' || preg_match('#^https?://#i', $relativePath)) {
        return;
    }
    $root = realpath(dirname(__DIR__));
    if ($root === false) {
        return;
    }
    $candidate = $root . '/' . ltrim($relativePath, '/');
    if (is_file($candidate)) {
        @unlink($candidate);
    }
}

function umSyncUserRole(PDO $pdo, array $scope, int $userId, int $roleId): void
{
    if (!umTable($pdo, 'user_roles')) {
        return;
    }
    $pdo->prepare('DELETE FROM user_roles WHERE user_id = :user_id')->execute(['user_id' => $userId]);
    $statement = $pdo->prepare(
        "INSERT INTO user_roles (user_id, role_id, is_primary, assigned_by)
         VALUES (:user_id, :role_id, 1, :assigned_by)"
    );
    $statement->execute([
        'user_id' => $userId,
        'role_id' => $roleId,
        'assigned_by' => $scope['user_id'] ?: null,
    ]);
}

function umSyncBranchAccess(PDO $pdo, array $scope, int $userId, int $branchId): void
{
    if (!umTable($pdo, 'user_branch_access') || $branchId <= 0) {
        return;
    }

    $pdo->prepare('UPDATE user_branch_access SET can_access = 0 WHERE user_id = :user_id')
        ->execute(['user_id' => $userId]);

    $statement = $pdo->prepare(
        "INSERT INTO user_branch_access (user_id, branch_id, can_access)
         VALUES (:user_id, :branch_id, 1)
         ON DUPLICATE KEY UPDATE can_access = 1"
    );
    $statement->execute(['user_id' => $userId, 'branch_id' => $branchId]);
}

function umAudit(PDO $pdo, array $scope, string $action, int $recordId, ?array $oldValues, ?array $newValues, string $description): void
{
    if (!umTable($pdo, 'activity_logs')) {
        return;
    }

    try {
        $statement = $pdo->prepare(
            "INSERT INTO activity_logs
             (tenant_id, branch_id, user_id, role_id, module_name, action_key,
              table_name, record_id, old_values, new_values, description,
              ip_address, user_agent)
             VALUES
             (:tenant_id, :branch_id, :user_id, :role_id, 'User Management', :action_key,
              'users', :record_id, :old_values, :new_values, :description,
              :ip_address, :user_agent)"
        );
        $statement->execute([
            'tenant_id' => $scope['tenant_id'],
            'branch_id' => $scope['branch_id'] ?: null,
            'user_id' => $scope['user_id'] ?: null,
            'role_id' => $scope['role_id'] ?: null,
            'action_key' => $action,
            'record_id' => $recordId,
            'old_values' => $oldValues === null ? null : json_encode($oldValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'new_values' => $newValues === null ? null : json_encode($newValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'description' => mb_substr($description, 0, 255),
            'ip_address' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
            'user_agent' => (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
        ]);
    } catch (Throwable $e) {
        error_log('User Management audit failed: ' . $e->getMessage());
    }
}

function umActiveAdminCount(PDO $pdo, int $tenantId): int
{
    $statement = $pdo->prepare(
        "SELECT COUNT(*)
         FROM users u
         INNER JOIN roles r ON r.id = u.role_id
         WHERE u.tenant_id = :tenant_id
           AND u.deleted_at IS NULL
           AND u.status = 'active'
           AND r.role_key IN ('super_admin', 'school_admin')"
    );
    $statement->execute(['tenant_id' => $tenantId]);
    return (int)$statement->fetchColumn();
}

function umEnsureAdminAvailability(PDO $pdo, array $record): void
{
    $roleKey = strtolower((string)($record['role_key'] ?? ''));
    if (in_array($roleKey, ['super_admin', 'school_admin'], true)
        && (string)($record['status'] ?? '') === 'active'
        && umActiveAdminCount($pdo, (int)$record['tenant_id']) <= 1) {
        throw new RuntimeException('At least one active School Administrator or Super Administrator must remain.');
    }
}

function umBranchScopeSql(array $scope, string $alias = 's'): string
{
    return $scope['branch_id'] > 0 ? " AND $alias.branch_id = :scope_branch_id" : '';
}

function umBackfillStaffLinks(PDO $pdo, array $scope): void
{
    $params = ['tenant_id' => $scope['tenant_id']];
    $branchSql = '';
    if ($scope['branch_id'] > 0) {
        $branchSql = ' AND s.branch_id = :scope_branch_id';
        $params['scope_branch_id'] = $scope['branch_id'];
    }

    // Safest automatic link: users.employee_id already equals staff code.
    $statement = $pdo->prepare(
        "UPDATE staff_members s
         INNER JOIN users u
            ON u.tenant_id = s.tenant_id
           AND u.employee_id = s.staff_code
           AND u.deleted_at IS NULL
         SET s.user_id = u.id
         WHERE s.tenant_id = :tenant_id
           AND s.deleted_at IS NULL
           AND s.user_id IS NULL
           $branchSql"
    );
    $statement->execute($params);

    // Repair broken links where the linked user was deleted.
    $statement = $pdo->prepare(
        "UPDATE staff_members s
         LEFT JOIN users u
           ON u.id = s.user_id
          AND u.tenant_id = s.tenant_id
          AND u.deleted_at IS NULL
         SET s.user_id = NULL
         WHERE s.tenant_id = :tenant_id
           AND s.deleted_at IS NULL
           AND s.user_id IS NOT NULL
           AND u.id IS NULL
           $branchSql"
    );
    $statement->execute($params);

    // Keep access disabled when staff is inactive or has left.
    $statement = $pdo->prepare(
        "UPDATE users u
         INNER JOIN staff_members s
            ON s.user_id = u.id
           AND s.tenant_id = u.tenant_id
         SET u.status = 'inactive',
             u.default_branch_id = s.branch_id,
             u.employee_id = s.staff_code
         WHERE s.tenant_id = :tenant_id
           AND s.deleted_at IS NULL
           AND s.status <> 'active'
           AND u.deleted_at IS NULL
           $branchSql"
    );
    $statement->execute($params);
}

function umStaffCandidates(PDO $pdo, array $scope): array
{
    $params = ['tenant_id' => $scope['tenant_id']];
    $where = [
        's.tenant_id = :tenant_id',
        's.deleted_at IS NULL',
        "s.status = 'active'",
    ];
    if ($scope['branch_id'] > 0) {
        $where[] = 's.branch_id = :scope_branch_id';
        $params['scope_branch_id'] = $scope['branch_id'];
    }

    $statement = $pdo->prepare(
        "SELECT s.id staff_id, s.staff_code, s.user_id,
                TRIM(CONCAT(COALESCE(s.first_name,''), CASE WHEN COALESCE(s.last_name,'')='' THEN '' ELSE CONCAT(' ',s.last_name) END)) staff_name,
                s.mobile, s.email, s.branch_id, b.branch_name,
                d.department_name, g.designation_name
         FROM staff_members s
         INNER JOIN branches b ON b.id = s.branch_id AND b.tenant_id = s.tenant_id
         INNER JOIN staff_departments d ON d.id = s.department_id AND d.tenant_id = s.tenant_id
         INNER JOIN staff_designations g ON g.id = s.designation_id AND g.tenant_id = s.tenant_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY s.first_name, s.last_name, s.staff_code"
    );
    $statement->execute($params);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function umStaffRecord(PDO $pdo, array $scope, int $staffId, bool $lock = false): array
{
    if ($staffId <= 0) {
        throw new InvalidArgumentException('Select a valid Staff Member.');
    }

    $where = [
        's.id = :staff_id',
        's.tenant_id = :tenant_id',
        's.deleted_at IS NULL',
    ];
    $params = ['staff_id' => $staffId, 'tenant_id' => $scope['tenant_id']];
    if ($scope['branch_id'] > 0) {
        $where[] = 's.branch_id = :scope_branch_id';
        $params['scope_branch_id'] = $scope['branch_id'];
    }

    $statement = $pdo->prepare(
        "SELECT s.*,
                TRIM(CONCAT(COALESCE(s.first_name,''), CASE WHEN COALESCE(s.last_name,'')='' THEN '' ELSE CONCAT(' ',s.last_name) END)) staff_name,
                b.branch_name, d.department_name, g.designation_name
         FROM staff_members s
         INNER JOIN branches b ON b.id = s.branch_id AND b.tenant_id = s.tenant_id
         INNER JOIN staff_departments d ON d.id = s.department_id AND d.tenant_id = s.tenant_id
         INNER JOIN staff_designations g ON g.id = s.designation_id AND g.tenant_id = s.tenant_id
         WHERE " . implode(' AND ', $where) . "
         LIMIT 1" . ($lock ? ' FOR UPDATE' : '')
    );
    $statement->execute($params);
    $record = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$record) {
        throw new InvalidArgumentException('Staff record not found.');
    }
    return $record;
}

function umLoginJoin(PDO $pdo): array
{
    if (!umTable($pdo, 'login_logs')) {
        return ['', 'u.last_login_at AS effective_last_login'];
    }
    return [
        "LEFT JOIN (
            SELECT tenant_id, user_id, MAX(login_at) AS last_login_at
            FROM login_logs
            WHERE login_status = 'success'
            GROUP BY tenant_id, user_id
        ) ll ON ll.tenant_id = u.tenant_id AND ll.user_id = u.id",
        'COALESCE(u.last_login_at, ll.last_login_at) AS effective_last_login',
    ];
}

function umCombinedRecord(PDO $pdo, array $scope, int $staffId = 0, int $userId = 0): array
{
    if ($staffId <= 0 && $userId <= 0) {
        throw new InvalidArgumentException('Select a valid Staff Login record.');
    }

    [$loginJoin, $loginSelect] = umLoginJoin($pdo);
    $where = ['s.tenant_id = :tenant_id', 's.deleted_at IS NULL'];
    $params = ['tenant_id' => $scope['tenant_id']];
    if ($staffId > 0) {
        $where[] = 's.id = :staff_id';
        $params['staff_id'] = $staffId;
    } else {
        $where[] = 'u.id = :user_id';
        $params['user_id'] = $userId;
    }
    if ($scope['branch_id'] > 0) {
        $where[] = 's.branch_id = :scope_branch_id';
        $params['scope_branch_id'] = $scope['branch_id'];
    }

    $statement = $pdo->prepare(
        "SELECT s.id staff_id, s.staff_code, s.user_id linked_user_id, s.first_name, s.last_name,
                TRIM(CONCAT(COALESCE(s.first_name,''), CASE WHEN COALESCE(s.last_name,'')='' THEN '' ELSE CONCAT(' ',s.last_name) END)) staff_name,
                s.mobile staff_mobile, s.email staff_email, s.status staff_status,
                s.branch_id, b.branch_name, d.department_name, g.designation_name,
                u.id user_id, u.role_id, u.employee_id, u.username, u.profile_photo,
                u.status user_status, u.last_login_at, u.created_at user_created_at, u.updated_at user_updated_at,
                r.role_key, r.role_name, r.role_scope,
                $loginSelect
         FROM staff_members s
         INNER JOIN branches b ON b.id = s.branch_id AND b.tenant_id = s.tenant_id
         INNER JOIN staff_departments d ON d.id = s.department_id AND d.tenant_id = s.tenant_id
         INNER JOIN staff_designations g ON g.id = s.designation_id AND g.tenant_id = s.tenant_id
         LEFT JOIN users u ON u.id = s.user_id AND u.tenant_id = s.tenant_id AND u.deleted_at IS NULL
         LEFT JOIN roles r ON r.id = u.role_id
         $loginJoin
         WHERE " . implode(' AND ', $where) . "
         LIMIT 1"
    );
    $statement->execute($params);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new InvalidArgumentException('Staff Login record not found.');
    }

    if (!umIsSuperAdmin($scope) && strtolower((string)($row['role_key'] ?? '')) === 'super_admin') {
        throw new RuntimeException('You cannot manage a Super Administrator account.');
    }

    $row['account_exists'] = (int)($row['user_id'] ?? 0) > 0;
    $row['user_code'] = $row['account_exists']
        ? umUserCode($pdo, $scope['tenant_id'], (int)$row['user_id'])
        : 'Not Created';
    $row['name'] = (string)$row['staff_name'];
    $row['mobile'] = (string)$row['staff_mobile'];
    $row['email'] = (string)($row['staff_email'] ?? '');
    $row['status'] = $row['account_exists'] ? (string)$row['user_status'] : 'not_created';
    $row['created_at'] = $row['user_created_at'];
    $row['updated_at'] = $row['user_updated_at'];
    $row['profile_photo_url'] = umAssetUrl($row['profile_photo'] ?? null);
    $row['can_edit'] = umCan($scope, 'edit') && $row['account_exists'];
    $row['can_create'] = umCan($scope, 'create') && !$row['account_exists'] && $row['staff_status'] === 'active';
    $row['can_delete'] = umCan($scope, 'delete')
        && $row['account_exists']
        && (int)$row['user_id'] !== $scope['user_id'];
    return $row;
}

function umAccountEmail(PDO $pdo, int $tenantId, int $userId, ?string $staffEmail): ?string
{
    $staffEmail = strtolower(trim((string)$staffEmail));
    if ($staffEmail === '' || !filter_var($staffEmail, FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    $statement = $pdo->prepare(
        "SELECT COUNT(*) FROM users
         WHERE tenant_id = :tenant_id
           AND LOWER(COALESCE(email,'')) = LOWER(:email)
           AND deleted_at IS NULL
           AND id <> :user_id"
    );
    $statement->execute([
        'tenant_id' => $tenantId,
        'email' => $staffEmail,
        'user_id' => $userId,
    ]);
    return (int)$statement->fetchColumn() > 0 ? null : $staffEmail;
}

function umList(PDO $pdo, array $scope, array $filters): array
{
    $page = max(1, (int)($filters['page'] ?? 1));
    $perPage = min(100, max(5, (int)($filters['per_page'] ?? 10)));
    $search = trim((string)($filters['search'] ?? ''));
    $roleId = (int)($filters['role_id'] ?? 0);
    $status = strtolower(trim((string)($filters['status'] ?? 'all')));

    $where = ['s.tenant_id = :tenant_id', 's.deleted_at IS NULL'];
    $params = ['tenant_id' => $scope['tenant_id']];
    if ($scope['branch_id'] > 0) {
        $where[] = 's.branch_id = :scope_branch_id';
        $params['scope_branch_id'] = $scope['branch_id'];
    }

    if ($search !== '') {
        $value = '%' . $search . '%';
        $where[] = "(
            s.staff_code LIKE :search_code OR
            s.first_name LIKE :search_first OR
            COALESCE(s.last_name,'') LIKE :search_last OR
            CONCAT_WS(' ',s.first_name,s.last_name) LIKE :search_name OR
            s.mobile LIKE :search_mobile OR
            COALESCE(s.email,'') LIKE :search_email OR
            COALESCE(u.username,'') LIKE :search_username OR
            COALESCE(r.role_name,'') LIKE :search_role OR
            COALESCE(d.department_name,'') LIKE :search_department OR
            COALESCE(g.designation_name,'') LIKE :search_designation
        )";
        $params += [
            'search_code' => $value,
            'search_first' => $value,
            'search_last' => $value,
            'search_name' => $value,
            'search_mobile' => $value,
            'search_email' => $value,
            'search_username' => $value,
            'search_role' => $value,
            'search_department' => $value,
            'search_designation' => $value,
        ];
    }

    if ($roleId > 0) {
        $where[] = 'u.role_id = :role_id';
        $params['role_id'] = $roleId;
    }

    if ($status === 'not_created') {
        $where[] = 'u.id IS NULL';
    } elseif ($status === 'active') {
        $where[] = "u.status = 'active'";
    } elseif ($status === 'inactive') {
        $where[] = "u.id IS NOT NULL AND u.status <> 'active'";
    }

    if (!umIsSuperAdmin($scope)) {
        $where[] = "(u.id IS NULL OR COALESCE(r.role_key,'') <> 'super_admin')";
    }

    $whereSql = implode(' AND ', $where);
    $count = $pdo->prepare(
        "SELECT COUNT(*)
         FROM staff_members s
         LEFT JOIN users u ON u.id = s.user_id AND u.tenant_id = s.tenant_id AND u.deleted_at IS NULL
         LEFT JOIN roles r ON r.id = u.role_id
         LEFT JOIN staff_departments d ON d.id = s.department_id AND d.tenant_id = s.tenant_id
         LEFT JOIN staff_designations g ON g.id = s.designation_id AND g.tenant_id = s.tenant_id
         WHERE $whereSql"
    );
    $count->execute($params);
    $total = (int)$count->fetchColumn();
    $lastPage = max(1, (int)ceil($total / $perPage));
    $page = min($page, $lastPage);
    $offset = ($page - 1) * $perPage;

    [$loginJoin, $loginSelect] = umLoginJoin($pdo);
    $statement = $pdo->prepare(
        "SELECT s.id staff_id, s.staff_code, s.user_id linked_user_id,
                TRIM(CONCAT(COALESCE(s.first_name,''), CASE WHEN COALESCE(s.last_name,'')='' THEN '' ELSE CONCAT(' ',s.last_name) END)) staff_name,
                s.mobile staff_mobile, s.email staff_email, s.status staff_status,
                s.branch_id, b.branch_name, d.department_name, g.designation_name,
                u.id user_id, u.role_id, u.username, u.profile_photo, u.status user_status,
                u.created_at user_created_at, u.updated_at user_updated_at, u.last_login_at,
                r.role_key, r.role_name,
                $loginSelect
         FROM staff_members s
         INNER JOIN branches b ON b.id = s.branch_id AND b.tenant_id = s.tenant_id
         INNER JOIN staff_departments d ON d.id = s.department_id AND d.tenant_id = s.tenant_id
         INNER JOIN staff_designations g ON g.id = s.designation_id AND g.tenant_id = s.tenant_id
         LEFT JOIN users u ON u.id = s.user_id AND u.tenant_id = s.tenant_id AND u.deleted_at IS NULL
         LEFT JOIN roles r ON r.id = u.role_id
         $loginJoin
         WHERE $whereSql
         ORDER BY
            CASE WHEN u.id IS NULL THEN 1 WHEN u.status='active' THEN 2 ELSE 3 END,
            s.first_name, s.last_name, s.staff_code
         LIMIT :offset, :per_page"
    );
    foreach ($params as $key => $value) {
        $statement->bindValue(':' . $key, $value);
    }
    $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
    $statement->bindValue(':per_page', $perPage, PDO::PARAM_INT);
    $statement->execute();

    $records = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $row['account_exists'] = (int)($row['user_id'] ?? 0) > 0;
        $row['user_code'] = $row['account_exists']
            ? umUserCode($pdo, $scope['tenant_id'], (int)$row['user_id'])
            : 'Not Created';
        $row['name'] = (string)$row['staff_name'];
        $row['mobile'] = (string)$row['staff_mobile'];
        $row['email'] = (string)($row['staff_email'] ?? '');
        $row['status'] = $row['account_exists'] ? (string)$row['user_status'] : 'not_created';
        $row['created_at'] = $row['user_created_at'];
        $row['profile_photo_url'] = umAssetUrl($row['profile_photo'] ?? null);
        $row['can_create'] = umCan($scope, 'create') && !$row['account_exists'] && $row['staff_status'] === 'active';
        $row['can_edit'] = umCan($scope, 'edit') && $row['account_exists'];
        $row['can_delete'] = umCan($scope, 'delete')
            && $row['account_exists']
            && (int)$row['user_id'] !== $scope['user_id'];
        $records[] = $row;
    }

    $statsWhere = ['s.tenant_id = :stats_tenant', 's.deleted_at IS NULL'];
    $statsParams = ['stats_tenant' => $scope['tenant_id']];
    if ($scope['branch_id'] > 0) {
        $statsWhere[] = 's.branch_id = :stats_branch';
        $statsParams['stats_branch'] = $scope['branch_id'];
    }
    $stats = $pdo->prepare(
        "SELECT COUNT(*) total_staff,
                COALESCE(SUM(u.id IS NOT NULL),0) login_accounts,
                COALESCE(SUM(u.id IS NOT NULL AND u.status='active'),0) active_accounts,
                COALESCE(SUM(u.id IS NULL),0) without_login
         FROM staff_members s
         LEFT JOIN users u ON u.id = s.user_id AND u.tenant_id = s.tenant_id AND u.deleted_at IS NULL
         WHERE " . implode(' AND ', $statsWhere)
    );
    $stats->execute($statsParams);
    $statsRow = $stats->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'records' => $records,
        'stats' => [
            'total_staff' => (int)($statsRow['total_staff'] ?? 0),
            'login_accounts' => (int)($statsRow['login_accounts'] ?? 0),
            'active_accounts' => (int)($statsRow['active_accounts'] ?? 0),
            'without_login' => (int)($statsRow['without_login'] ?? 0),
        ],
        'pagination' => [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => $lastPage,
        ],
        'meta' => [
            'roles' => umRoles($pdo, $scope),
            'staff_candidates' => umStaffCandidates($pdo, $scope),
            'csrf_token' => $_SESSION['user_management_csrf_token'],
            'api_build' => USER_MANAGEMENT_BUILD,
        ],
        'permissions' => [
            'view' => umCan($scope, 'view'),
            'create' => umCan($scope, 'create'),
            'edit' => umCan($scope, 'edit'),
            'delete' => umCan($scope, 'delete'),
        ],
    ];
}

if (!isset($pdo) || !$pdo instanceof PDO) {
    umOut(false, 'Database connection unavailable.', [], 500);
}

$scope = umScope($pdo);
if ($scope['tenant_id'] <= 0 || $scope['user_id'] <= 0) {
    umOut(false, 'Tenant or user session is missing.', [], 401);
}

if (empty($_SESSION['user_management_csrf_token']) || !is_string($_SESSION['user_management_csrf_token'])) {
    $_SESSION['user_management_csrf_token'] = bin2hex(random_bytes(32));
}

$input = umInput();
$action = strtolower(trim((string)($input['action'] ?? $_GET['action'] ?? '')));

try {
    umEnsure($pdo);
    umBackfillStaffLinks($pdo, $scope);

    if ($action === 'meta') {
        umRequire($scope, 'view');
        umOut(true, 'Staff login metadata loaded.', [
            'roles' => umRoles($pdo, $scope),
            'staff_candidates' => umStaffCandidates($pdo, $scope),
            'statuses' => [
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'inactive', 'label' => 'Inactive'],
            ],
            'csrf_token' => $_SESSION['user_management_csrf_token'],
            'permissions' => [
                'view' => umCan($scope, 'view'),
                'create' => umCan($scope, 'create'),
                'edit' => umCan($scope, 'edit'),
                'delete' => umCan($scope, 'delete'),
            ],
            'api_build' => USER_MANAGEMENT_BUILD,
        ]);
    }

    if ($action === 'list') {
        umRequire($scope, 'view');
        umOut(true, 'Staff login accounts loaded.', umList($pdo, $scope, array_merge($_GET, $input)));
    }

    if ($action === 'detail' || $action === 'view') {
        umRequire($scope, 'view');
        $staffId = (int)($_GET['staff_id'] ?? $input['staff_id'] ?? 0);
        $userId = (int)($_GET['user_id'] ?? $_GET['id'] ?? $input['user_id'] ?? $input['id'] ?? 0);
        umOut(true, 'Staff login details loaded.', [
            'record' => umCombinedRecord($pdo, $scope, $staffId, $userId),
        ]);
    }

    if ($action === 'save') {
        umCsrf($input);

        $staffId = (int)($input['staff_id'] ?? 0);
        $userId = (int)($input['user_id'] ?? $input['id'] ?? 0);
        umRequire($scope, $userId > 0 ? 'edit' : 'create');

        $username = trim((string)($input['username'] ?? ''));
        $roleId = (int)($input['role_id'] ?? 0);
        $status = strtolower(trim((string)($input['status'] ?? 'active')));
        $password = (string)($input['password'] ?? '');
        $confirmPassword = (string)($input['confirm_password'] ?? '');
        $removePhoto = (string)($input['remove_photo'] ?? '0') === '1';

        if (mb_strlen($username) < 3 || mb_strlen($username) > 100
            || preg_match('/^[\p{L}\p{N}][\p{L}\p{N}._@-]{2,99}$/u', $username) !== 1) {
            throw new InvalidArgumentException('Username must contain 3 to 100 letters or numbers. Dots, underscores, @ and hyphens are allowed.');
        }
        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new InvalidArgumentException('Select a valid Status.');
        }

        $selectedRole = umRole($pdo, $scope, $roleId);
        if ($userId <= 0 || $password !== '' || $confirmPassword !== '') {
            umValidatePassword($password, $confirmPassword);
        }

        $duplicateUsername = $pdo->prepare(
            "SELECT id FROM users
             WHERE tenant_id = :tenant_id
               AND LOWER(username) = LOWER(:username)
               AND deleted_at IS NULL
               AND id <> :user_id
             LIMIT 1"
        );
        $duplicateUsername->execute([
            'tenant_id' => $scope['tenant_id'],
            'username' => $username,
            'user_id' => $userId,
        ]);
        if ((int)$duplicateUsername->fetchColumn() > 0) {
            throw new RuntimeException('This Username already exists.', 409);
        }

        $photo = umStorePhoto($scope);
        $newPhotoAbsolute = $photo['absolute'];
        $oldPhotoToDelete = null;

        try {
            $pdo->beginTransaction();
            $staff = umStaffRecord($pdo, $scope, $staffId, true);

            if ($status === 'active' && (string)$staff['status'] !== 'active') {
                throw new RuntimeException('Only an active staff member can receive active login access.');
            }

            $oldRecord = null;
            if ($userId > 0) {
                if ((int)($staff['user_id'] ?? 0) !== $userId) {
                    throw new RuntimeException('This user account is not linked to the selected staff member.');
                }
                $oldRecord = umCombinedRecord($pdo, $scope, $staffId, $userId);
            } elseif ((int)($staff['user_id'] ?? 0) > 0) {
                throw new RuntimeException('Login credentials already exist for this staff member. Use Edit or Reset Password.');
            }

            if ($userId === $scope['user_id'] && $status !== 'active') {
                throw new RuntimeException('You cannot deactivate your own account.');
            }

            if ($oldRecord && $oldRecord['status'] === 'active') {
                $oldRoleKey = strtolower((string)($oldRecord['role_key'] ?? ''));
                $newRoleKey = strtolower((string)($selectedRole['role_key'] ?? ''));
                $removingAdminAccess = in_array($oldRoleKey, ['super_admin', 'school_admin'], true)
                    && !in_array($newRoleKey, ['super_admin', 'school_admin'], true);
                if ($status !== 'active' || $removingAdminAccess) {
                    umEnsureAdminAvailability($pdo, [
                        'tenant_id' => $scope['tenant_id'],
                        'role_key' => $oldRoleKey,
                        'status' => $oldRecord['status'],
                    ]);
                }
            }

            $passwordHash = null;
            if ($userId <= 0 || $password !== '') {
                $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                if ($passwordHash === false) {
                    throw new RuntimeException('Unable to secure the password.');
                }
            }

            $staffName = preg_replace('/\s+/u', ' ', trim((string)$staff['staff_name'])) ?: (string)$staff['staff_code'];
            $staffMobile = trim((string)$staff['mobile']);
            $staffEmail = umAccountEmail($pdo, $scope['tenant_id'], $userId, $staff['email'] ?? null);

            if ($userId > 0) {
                $profilePhoto = $photo['relative']
                    ?? ($removePhoto ? null : ($oldRecord['profile_photo'] ?? null));

                $sql = "UPDATE users SET
                            default_branch_id = :default_branch_id,
                            role_id = :role_id,
                            employee_id = :employee_id,
                            name = :name,
                            email = :email,
                            mobile = :mobile,
                            username = :username,
                            profile_photo = :profile_photo,
                            status = :status";
                $params = [
                    'default_branch_id' => (int)$staff['branch_id'],
                    'role_id' => $roleId,
                    'employee_id' => (string)$staff['staff_code'],
                    'name' => $staffName,
                    'email' => $staffEmail,
                    'mobile' => $staffMobile,
                    'username' => $username,
                    'profile_photo' => $profilePhoto,
                    'status' => $status,
                    'user_id' => $userId,
                    'tenant_id' => $scope['tenant_id'],
                ];
                if ($passwordHash !== null) {
                    $sql .= ', password_hash = :password_hash';
                    $params['password_hash'] = $passwordHash;
                }
                $sql .= ' WHERE id = :user_id AND tenant_id = :tenant_id AND deleted_at IS NULL';
                $statement = $pdo->prepare($sql);
                $statement->execute($params);

                if (($photo['relative'] !== null || $removePhoto) && !empty($oldRecord['profile_photo'])) {
                    $oldPhotoToDelete = (string)$oldRecord['profile_photo'];
                }
                $savedUserId = $userId;
                $message = 'Staff login credentials updated successfully.';
            } else {
                $statement = $pdo->prepare(
                    "INSERT INTO users
                     (tenant_id, default_branch_id, role_id, employee_id, name,
                      email, mobile, username, password_hash, profile_photo, status)
                     VALUES
                     (:tenant_id, :default_branch_id, :role_id, :employee_id, :name,
                      :email, :mobile, :username, :password_hash, :profile_photo, :status)"
                );
                $statement->execute([
                    'tenant_id' => $scope['tenant_id'],
                    'default_branch_id' => (int)$staff['branch_id'],
                    'role_id' => $roleId,
                    'employee_id' => (string)$staff['staff_code'],
                    'name' => $staffName,
                    'email' => $staffEmail,
                    'mobile' => $staffMobile,
                    'username' => $username,
                    'password_hash' => $passwordHash,
                    'profile_photo' => $photo['relative'],
                    'status' => $status,
                ]);
                $savedUserId = (int)$pdo->lastInsertId();

                $link = $pdo->prepare(
                    "UPDATE staff_members SET user_id = :user_id, updated_by = :updated_by
                     WHERE id = :staff_id AND tenant_id = :tenant_id AND deleted_at IS NULL"
                );
                $link->execute([
                    'user_id' => $savedUserId,
                    'updated_by' => $scope['user_id'] ?: null,
                    'staff_id' => $staffId,
                    'tenant_id' => $scope['tenant_id'],
                ]);
                $message = 'Login credentials created successfully for ' . $staffName . '.';
            }

            umSyncUserRole($pdo, $scope, $savedUserId, $roleId);
            umSyncBranchAccess($pdo, $scope, $savedUserId, (int)$staff['branch_id']);

            umAudit(
                $pdo,
                $scope,
                $userId > 0 ? 'update_staff_login' : 'create_staff_login',
                $savedUserId,
                $oldRecord,
                [
                    'staff_id' => $staffId,
                    'staff_code' => $staff['staff_code'],
                    'username' => $username,
                    'role_id' => $roleId,
                    'role_name' => $selectedRole['role_name'],
                    'status' => $status,
                ],
                ($userId > 0 ? 'Updated' : 'Created') . ' login credentials for ' . $staffName
            );

            $pdo->commit();

            if ($oldPhotoToDelete !== null) {
                umDeletePhoto($oldPhotoToDelete);
            }

            umOut(true, $message, [
                'record' => umCombinedRecord($pdo, $scope, $staffId, $savedUserId),
                'csrf_token' => $_SESSION['user_management_csrf_token'],
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($newPhotoAbsolute !== null && is_file($newPhotoAbsolute)) {
                @unlink($newPhotoAbsolute);
            }
            throw $e;
        }
    }

    if ($action === 'reset_password') {
        umRequire($scope, 'edit');
        umCsrf($input);

        $userId = (int)($input['user_id'] ?? $input['id'] ?? 0);
        $password = (string)($input['password'] ?? '');
        $confirmPassword = (string)($input['confirm_password'] ?? '');
        umValidatePassword($password, $confirmPassword);

        $record = umCombinedRecord($pdo, $scope, 0, $userId);
        if (!$record['account_exists']) {
            throw new InvalidArgumentException('Login account not found.');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        if ($hash === false) {
            throw new RuntimeException('Unable to secure the password.');
        }

        $statement = $pdo->prepare(
            "UPDATE users SET password_hash = :password_hash
             WHERE id = :user_id AND tenant_id = :tenant_id AND deleted_at IS NULL"
        );
        $statement->execute([
            'password_hash' => $hash,
            'user_id' => $userId,
            'tenant_id' => $scope['tenant_id'],
        ]);

        umAudit($pdo, $scope, 'reset_password', $userId, null, null, 'Reset password for ' . $record['staff_name']);
        umOut(true, 'Password reset successfully.');
    }

    if ($action === 'toggle_status') {
        umRequire($scope, 'edit');
        umCsrf($input);

        $userId = (int)($input['user_id'] ?? $input['id'] ?? 0);
        $status = strtolower(trim((string)($input['status'] ?? '')));
        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new InvalidArgumentException('Select a valid Status.');
        }
        if ($userId === $scope['user_id'] && $status !== 'active') {
            throw new RuntimeException('You cannot deactivate your own account.');
        }

        $record = umCombinedRecord($pdo, $scope, 0, $userId);
        if ($status === 'active' && $record['staff_status'] !== 'active') {
            throw new RuntimeException('This staff member is not active. Update Staff Management first.');
        }
        if ($record['status'] === 'active' && $status !== 'active') {
            umEnsureAdminAvailability($pdo, [
                'tenant_id' => $scope['tenant_id'],
                'role_key' => $record['role_key'],
                'status' => $record['status'],
            ]);
        }

        $statement = $pdo->prepare(
            "UPDATE users SET status = :status
             WHERE id = :user_id AND tenant_id = :tenant_id AND deleted_at IS NULL"
        );
        $statement->execute([
            'status' => $status,
            'user_id' => $userId,
            'tenant_id' => $scope['tenant_id'],
        ]);

        umAudit(
            $pdo,
            $scope,
            'status_' . $status,
            $userId,
            ['status' => $record['status']],
            ['status' => $status],
            ucfirst($status) . ' login access for ' . $record['staff_name']
        );
        umOut(true, $status === 'active' ? 'Login access activated successfully.' : 'Login access deactivated successfully.');
    }

    if ($action === 'delete') {
        umRequire($scope, 'delete');
        umCsrf($input);

        $userId = (int)($input['user_id'] ?? $input['id'] ?? 0);
        if ($userId <= 0) {
            throw new InvalidArgumentException('Select a valid Login Account.');
        }
        if ($userId === $scope['user_id']) {
            throw new RuntimeException('You cannot remove your own login account.');
        }

        $record = umCombinedRecord($pdo, $scope, 0, $userId);
        umEnsureAdminAvailability($pdo, [
            'tenant_id' => $scope['tenant_id'],
            'role_key' => $record['role_key'],
            'status' => $record['status'],
        ]);

        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare(
                "UPDATE users SET
                    status = 'inactive',
                    deleted_at = NOW(),
                    employee_id = NULL,
                    email = NULL,
                    username = CONCAT(LEFT(username, 70), '__deleted__', id)
                 WHERE id = :user_id AND tenant_id = :tenant_id AND deleted_at IS NULL"
            );
            $statement->execute([
                'user_id' => $userId,
                'tenant_id' => $scope['tenant_id'],
            ]);
            if ($statement->rowCount() === 0) {
                throw new InvalidArgumentException('Login account not found.');
            }

            $unlink = $pdo->prepare(
                "UPDATE staff_members SET user_id = NULL, updated_by = :updated_by
                 WHERE id = :staff_id AND tenant_id = :tenant_id AND user_id = :user_id"
            );
            $unlink->execute([
                'updated_by' => $scope['user_id'] ?: null,
                'staff_id' => $record['staff_id'],
                'tenant_id' => $scope['tenant_id'],
                'user_id' => $userId,
            ]);
            if (umTable($pdo, 'user_branch_access')) {
                $pdo->prepare('UPDATE user_branch_access SET can_access = 0 WHERE user_id = :user_id')
                    ->execute(['user_id' => $userId]);
            }
            if (umTable($pdo, 'user_roles')) {
                $pdo->prepare('DELETE FROM user_roles WHERE user_id = :user_id')
                    ->execute(['user_id' => $userId]);
            }

            umAudit($pdo, $scope, 'remove_staff_login', $userId, $record, null, 'Removed login credentials for ' . $record['staff_name']);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        if (!empty($record['profile_photo'])) {
            umDeletePhoto((string)$record['profile_photo']);
        }
        umOut(true, 'Login credentials removed. The Staff Management record is unchanged.');
    }

    umOut(false, 'Invalid User Management action.', [], 400);
} catch (InvalidArgumentException $e) {
    umOut(false, $e->getMessage(), [], 422);
} catch (Throwable $e) {
    error_log(
        'user-management.php build=' . USER_MANAGEMENT_BUILD
        . ' action=' . $action
        . ' line=' . $e->getLine()
        . ' error=' . $e->getMessage()
    );

    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $status = $e->getCode() === 409 ? 409 : 500;
    umOut(
        false,
        (str_contains($host, 'localhost') || str_contains($host, '127.0.0.1'))
            ? 'User Management request failed [' . USER_MANAGEMENT_BUILD . ']: ' . $e->getMessage()
            : 'Unable to complete the User Management request.',
        [],
        $status
    );
}

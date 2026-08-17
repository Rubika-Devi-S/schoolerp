<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Application configuration
|--------------------------------------------------------------------------
*/

const APP_NAME = 'Brighton Public School ERP';
const APP_TIMEZONE = 'Asia/Kolkata';
const APP_DEMO_MODE = false; // Set true only when you need database-free preview mode.

const SCHOOL_PERMISSION_BOOTSTRAP_BUILD = '2026-08-17-branch-module-permissions-v57';

if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(__DIR__));
}

if (!defined('BASE_URL')) {
    define('BASE_URL', '/git/schoolerp/');
}

date_default_timezone_set(APP_TIMEZONE);

/*
|--------------------------------------------------------------------------
| Session
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = !empty($_SERVER['HTTPS'])
        && strtolower((string)$_SERVER['HTTPS']) !== 'off';

    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');

    session_name('SCHOOL_ERP_SESSION');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
*/

$pdo = null;

if (!APP_DEMO_MODE) {
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('DB_NAME') ?: 'school_erp';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';

    try {
        $pdo = new PDO(
            "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        $pdo->exec("SET time_zone = '+05:30'");
        $pdo->exec(
            "SET SESSION sql_mode =
             'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'"
        );
    } catch (Throwable $e) {
        error_log('Database connection failed: ' . $e->getMessage());
        http_response_code(500);
        exit('Database connection failed.');
    }
}

/*
|--------------------------------------------------------------------------
| Branch module permission layer
|--------------------------------------------------------------------------
*/

$branchModuleAccessFile = PROJECT_ROOT . '/includes/branch-module-access.php';

if (is_file($branchModuleAccessFile)) {
    require_once $branchModuleAccessFile;
}

/*
|--------------------------------------------------------------------------
| Common helpers
|--------------------------------------------------------------------------
*/

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('app_url')) {
    function app_url(string $path = ''): string
    {
        return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('is_logged_in')) {
    function is_logged_in(): bool
    {
        return !empty($_SESSION['user_id']);
    }
}

if (!function_exists('school_is_api_request')) {
    function school_is_api_request(): bool
    {
        $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
        $requestedWith = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));

        return str_contains($script, '/api/')
            || str_contains($accept, 'application/json')
            || $requestedWith === 'xmlhttprequest';
    }
}

if (is_file(__DIR__ . '/common-toast.php')) {
    require_once __DIR__ . '/common-toast.php';
}

if (!function_exists('require_login')) {
    function require_login(): void
    {
        if (is_logged_in()) {
            return;
        }

        if (school_is_api_request()) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');

            echo json_encode(
                [
                    'success' => false,
                    'message' => 'Your login session has expired. Please sign in again.',
                    'data' => [],
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
            exit;
        }

        header('Location: ' . app_url('login.php'));
        exit;
    }
}

if (!function_exists('csrfToken')) {
    function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return (string)$_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_is_valid')) {
    function csrf_is_valid(mixed $token): bool
    {
        $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
        $givenToken = is_string($token) ? $token : '';

        return $sessionToken !== ''
            && $givenToken !== ''
            && hash_equals($sessionToken, $givenToken);
    }
}

if (!function_exists('validateCsrfToken')) {
    function validateCsrfToken(mixed $token): bool
    {
        return csrf_is_valid($token);
    }
}

if (!function_exists('json_response')) {
    function json_response(
        bool $success,
        string $message = '',
        array $data = [],
        int $statusCode = 200
    ): never {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(
            array_merge(['success' => $success, 'message' => $message], $data),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Runtime-safe schema helpers
|--------------------------------------------------------------------------
*/

if (!function_exists('school_table_exists')) {
    function school_table_exists(PDO $pdo, string $table): bool
    {
        static $cache = [];

        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name = :table"
            );
            $stmt->execute(['table' => $table]);

            return $cache[$table] = ((int)$stmt->fetchColumn() > 0);
        } catch (Throwable $e) {
            error_log('school_table_exists: ' . $e->getMessage());
            return $cache[$table] = false;
        }
    }
}

if (!function_exists('school_column_exists')) {
    function school_column_exists(PDO $pdo, string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name = :table
                   AND column_name = :column"
            );
            $stmt->execute([
                'table' => $table,
                'column' => $column,
            ]);

            return $cache[$key] = ((int)$stmt->fetchColumn() > 0);
        } catch (Throwable $e) {
            error_log('school_column_exists: ' . $e->getMessage());
            return $cache[$key] = false;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Permission-chain initialization
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/permission-chain.php';

if ($pdo instanceof PDO) {
    try {
        $needsPermissionChainSetup =
            !school_table_exists($pdo, 'sidebar_role_master_items');

        if (!$needsPermissionChainSetup
            && school_table_exists($pdo, 'sidebar_items')) {
            $stmt = $pdo->query(
                "SELECT COUNT(*) FROM sidebar_items
                 WHERE menu_key='parent_sidebar_permissions'"
            );
            $needsPermissionChainSetup =
                (int)$stmt->fetchColumn() === 0;
        }

        if (!$needsPermissionChainSetup
            && school_table_exists($pdo, 'app_pages')) {
            $stmt = $pdo->query(
                "SELECT COUNT(*) FROM app_pages
                 WHERE page_key='parent_sidebar_permissions'
                   AND is_active=1"
            );
            $needsPermissionChainSetup =
                (int)$stmt->fetchColumn() === 0;
        }

        if ($needsPermissionChainSetup) {
            pc_ensure_parent_permission_page($pdo);
        }
    } catch (Throwable $exception) {
        error_log('Permission-chain initialization: ' . $exception->getMessage());
    }
}

/*
|--------------------------------------------------------------------------
| User, tenant and academic context
|--------------------------------------------------------------------------
*/

if (!function_exists('current_user')) {
    function current_user(): array
    {
        global $pdo;

        $defaults = [
            'id' => (int)($_SESSION['user_id'] ?? 0),
            'name' => (string)($_SESSION['name'] ?? $_SESSION['username'] ?? 'John Admin'),
            'username' => (string)($_SESSION['username'] ?? ''),
            'role_id' => (int)($_SESSION['role_id'] ?? 0),
            'role_key' => (string)($_SESSION['role_key'] ?? ''),
            'role_name' => (string)($_SESSION['role_name'] ?? ''),
            'tenant_id' => (int)($_SESSION['school_id'] ?? $_SESSION['tenant_id'] ?? 0),
            'branch_id' => (int)($_SESSION['branch_id'] ?? $_SESSION['default_branch_id'] ?? 0),
            'photo_path' => (string)($_SESSION['photo_path'] ?? $_SESSION['profile_photo'] ?? ''),
            'notification_count' => 0,
            'message_count' => 0,
        ];

        if (!($pdo instanceof PDO) || $defaults['id'] <= 0 || !school_table_exists($pdo, 'users')) {
            return $defaults;
        }

        try {
            $photoColumn = school_column_exists($pdo, 'users', 'profile_photo')
                ? 'u.profile_photo AS photo_path'
                : (school_column_exists($pdo, 'users', 'photo_path')
                    ? 'u.photo_path'
                    : "'' AS photo_path");

            $roleJoin = school_table_exists($pdo, 'roles')
                && school_column_exists($pdo, 'users', 'role_id');

            $roleFields = $roleJoin
                ? 'r.role_key, r.role_name'
                : "'super_admin' AS role_key, 'Super Administrator' AS role_name";

            $sql = "SELECT u.id, u.name, u.username, u.tenant_id,
                           u.default_branch_id AS branch_id, u.role_id,
                           {$photoColumn}, {$roleFields}
                    FROM users u"
                . ($roleJoin ? ' LEFT JOIN roles r ON r.id = u.role_id' : '')
                . " WHERE u.id = :user_id";

            $params = ['user_id' => $defaults['id']];

            if (school_column_exists($pdo, 'users', 'tenant_id')) {
                $sql .= ' AND u.tenant_id = :tenant_id';
                $params['tenant_id'] = $defaults['tenant_id'];
            }

            $sql .= ' LIMIT 1';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!is_array($row)) {
                return $defaults;
            }

            $user = array_merge($defaults, $row);

            if (school_table_exists($pdo, 'notifications')) {
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*)
                     FROM notifications
                     WHERE user_id = :user_id
                       AND tenant_id = :tenant_id
                       AND read_at IS NULL"
                );
                $stmt->execute([
                    'user_id' => $user['id'],
                    'tenant_id' => $user['tenant_id'],
                ]);
                $user['notification_count'] = (int)$stmt->fetchColumn();
            }

            if (school_table_exists($pdo, 'user_messages')) {
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*)
                     FROM user_messages
                     WHERE receiver_user_id = :user_id
                       AND tenant_id = :tenant_id
                       AND read_at IS NULL"
                );
                $stmt->execute([
                    'user_id' => $user['id'],
                    'tenant_id' => $user['tenant_id'],
                ]);
                $user['message_count'] = (int)$stmt->fetchColumn();
            }

            return $user;
        } catch (Throwable $e) {
            error_log('current_user: ' . $e->getMessage());
            return $defaults;
        }
    }
}

if (!function_exists('clear_current_role_ids_cache')) {
    function clear_current_role_ids_cache(): void
    {
        unset($GLOBALS['school_erp_current_role_ids']);
    }
}

if (!function_exists('current_role_ids')) {
    /** @return array<int,int> */
    function current_role_ids(): array
    {
        global $pdo;

        if (isset($GLOBALS['school_erp_current_role_ids'])) {
            return $GLOBALS['school_erp_current_role_ids'];
        }

        $userId = (int)($_SESSION['user_id'] ?? 0);
        $primaryRoleId = (int)($_SESSION['role_id'] ?? 0);
        $roleIds = [];

        if ($userId > 0
            && $pdo instanceof PDO
            && school_table_exists($pdo, 'user_roles')
            && school_table_exists($pdo, 'roles')) {
            try {
                $deletedFilter = school_column_exists($pdo, 'roles', 'deleted_at')
                    ? 'AND r.deleted_at IS NULL'
                    : '';
                $stmt = $pdo->prepare(
                    "SELECT ur.role_id
                     FROM user_roles ur
                     INNER JOIN roles r
                        ON r.id=ur.role_id
                       AND r.status='active'
                       {$deletedFilter}
                     WHERE ur.user_id=:user_id
                     ORDER BY ur.is_primary DESC,ur.role_id"
                );
                $stmt->execute(['user_id' => $userId]);
                $roleIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
            } catch (Throwable $e) {
                error_log('current_role_ids: ' . $e->getMessage());
            }
        }

        if ($primaryRoleId > 0) {
            array_unshift($roleIds, $primaryRoleId);
        }

        $roleIds = array_values(array_unique(array_filter(
            $roleIds,
            static fn(int $id): bool => $id > 0
        )));

        $GLOBALS['school_erp_current_role_ids'] = $roleIds;
        return $roleIds;
    }
}

if (!function_exists('current_user_has_platform_role')) {
    function current_user_has_platform_role(): bool
    {
        global $pdo;

        $roleIds = current_role_ids();
        if ($roleIds === []) {
            return false;
        }

        if (!($pdo instanceof PDO)
            || !school_table_exists($pdo, 'roles')
            || !school_column_exists($pdo, 'roles', 'role_scope')) {
            return strtolower((string)($_SESSION['role_key'] ?? '')) === 'super_admin';
        }

        try {
            $marks = implode(',', array_fill(0, count($roleIds), '?'));
            $deletedFilter = school_column_exists($pdo, 'roles', 'deleted_at')
                ? 'AND deleted_at IS NULL'
                : '';
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM roles
                 WHERE id IN ({$marks})
                   AND role_scope='platform'
                   AND status='active'
                   {$deletedFilter}"
            );
            $stmt->execute($roleIds);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            error_log('current_user_has_platform_role: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('is_super_admin')) {
    function is_super_admin(): bool
    {
        global $pdo;

        $roleKey = strtolower((string)($_SESSION['role_key'] ?? ''));
        $roleName = strtolower((string)($_SESSION['role_name'] ?? ''));
        if ($roleKey === 'super_admin'
            || $roleName === 'super administrator'
            || $roleName === 'super admin') {
            return true;
        }

        $roleIds = current_role_ids();
        if ($roleIds === [] || !($pdo instanceof PDO) || !school_table_exists($pdo, 'roles')) {
            return false;
        }

        try {
            $marks = implode(',', array_fill(0, count($roleIds), '?'));
            $scope = school_column_exists($pdo, 'roles', 'role_scope')
                ? "AND role_scope='platform'"
                : '';
            $deleted = school_column_exists($pdo, 'roles', 'deleted_at')
                ? 'AND deleted_at IS NULL'
                : '';
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM roles
                 WHERE id IN ({$marks})
                   AND role_key='super_admin'
                   AND status='active'
                   {$scope} {$deleted}"
            );
            $stmt->execute($roleIds);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            error_log('is_super_admin: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('current_tenant_branding')) {
    /**
     * Current School Profile is the canonical branding source for ALL pages.
     * Legacy tenant/tenant_branding values are kept only as fallbacks.
     */
    function current_tenant_branding(): array
    {
        global $pdo;

        $branding = [
            'school_name' => 'Brighton Public School',
            'tagline' => 'Nurturing Future Leaders',
            'logo_path' => '',
            'logo_fit' => 'contain',
            'logo_zoom' => 100,
            'logo_position_x' => 50,
            'logo_position_y' => 50,
            'logo_rotation' => 0,
            'logo_shape' => 'rounded',
            'search_placeholder' => 'Search students, classes, teachers...',
            'sidebar_footer_title' => 'Excellence in Education',
            'sidebar_footer_text' => 'Building a strong foundation for a brighter tomorrow.',
            'sidebar_footer_icon' => 'graduation-cap',
        ];

        if (!($pdo instanceof PDO)) {
            return $branding;
        }

        $tenantId = max(1, (int)(
            $_SESSION['tenant_id']
            ?? $_SESSION['school_id']
            ?? 1
        ));

        try {
            if (school_table_exists($pdo, 'tenants')) {
                $statement = $pdo->prepare(
                    'SELECT * FROM tenants WHERE id = :tenant_id LIMIT 1'
                );
                $statement->execute(['tenant_id' => $tenantId]);
                $row = $statement->fetch(PDO::FETCH_ASSOC);

                if (is_array($row)) {
                    if (array_key_exists('school_name', $row) && $row['school_name'] !== null) {
                        $branding['school_name'] = (string)$row['school_name'];
                    } elseif (array_key_exists('name', $row) && $row['name'] !== null) {
                        $branding['school_name'] = (string)$row['name'];
                    }
                    if (array_key_exists('logo_path', $row)) {
                        $branding['logo_path'] = (string)($row['logo_path'] ?? '');
                    }
                }
            }

            if (school_table_exists($pdo, 'tenant_branding')) {
                $where = 'tenant_id = :tenant_id';
                if (school_column_exists($pdo, 'tenant_branding', 'is_active')) {
                    $where .= ' AND is_active = 1';
                }
                $order = school_column_exists($pdo, 'tenant_branding', 'id')
                    ? ' ORDER BY id DESC'
                    : '';

                $statement = $pdo->prepare(
                    "SELECT * FROM tenant_branding WHERE {$where}{$order} LIMIT 1"
                );
                $statement->execute(['tenant_id' => $tenantId]);
                $row = $statement->fetch(PDO::FETCH_ASSOC);

                if (is_array($row)) {
                    foreach ($row as $key => $value) {
                        if ($value !== null) {
                            $branding[(string)$key] = $value;
                        }
                    }
                }
            }

            if (school_table_exists($pdo, 'school_profile')) {
                $orderParts = [];
                if (school_column_exists($pdo, 'school_profile', 'branch_id')) {
                    $orderParts[] = 'CASE WHEN branch_id IS NULL THEN 0 ELSE 1 END';
                }
                if (school_column_exists($pdo, 'school_profile', 'updated_at')) {
                    $orderParts[] = 'updated_at DESC';
                }
                if (school_column_exists($pdo, 'school_profile', 'id')) {
                    $orderParts[] = 'id DESC';
                }
                $orderSql = $orderParts
                    ? ' ORDER BY ' . implode(', ', $orderParts)
                    : '';

                $statement = $pdo->prepare(
                    "SELECT * FROM school_profile
                     WHERE tenant_id = :tenant_id{$orderSql}
                     LIMIT 1"
                );
                $statement->execute(['tenant_id' => $tenantId]);
                $profile = $statement->fetch(PDO::FETCH_ASSOC);

                if (is_array($profile)) {
                    foreach ($profile as $key => $value) {
                        if ($value !== null) {
                            $branding[(string)$key] = $value;
                        }
                    }

                    if (array_key_exists('logo_path', $profile)) {
                        $branding['logo_path'] = (string)($profile['logo_path'] ?? '');
                    }
                    if (array_key_exists('school_motto', $profile)) {
                        $branding['tagline'] = (string)($profile['school_motto'] ?? '');
                    }
                }
            }
        } catch (Throwable $exception) {
            error_log('current_tenant_branding: ' . $exception->getMessage());
        }

        $fit = strtolower((string)($branding['logo_fit'] ?? 'contain'));
        $branding['logo_fit'] = in_array($fit, ['contain', 'cover'], true) ? $fit : 'contain';
        $branding['logo_zoom'] = max(50, min(200, (int)($branding['logo_zoom'] ?? 100)));
        $branding['logo_position_x'] = max(0, min(100, (int)($branding['logo_position_x'] ?? 50)));
        $branding['logo_position_y'] = max(0, min(100, (int)($branding['logo_position_y'] ?? 50)));
        $branding['logo_rotation'] = max(-180, min(180, (int)($branding['logo_rotation'] ?? 0)));
        $shape = strtolower((string)($branding['logo_shape'] ?? 'rounded'));
        $branding['logo_shape'] = in_array($shape, ['rounded', 'circle', 'square'], true)
            ? $shape
            : 'rounded';

        return $branding;
    }
}

if (!function_exists('user_initials')) {
    function user_initials(string $name): string
    {
        $parts = array_values(array_filter(preg_split('/\s+/', trim($name)) ?: []));

        if (!$parts) {
            return 'U';
        }

        if (count($parts) === 1) {
            return strtoupper(substr($parts[0], 0, 2));
        }

        return strtoupper(
            substr($parts[0], 0, 1)
            . substr($parts[count($parts) - 1], 0, 1)
        );
    }
}

if (!function_exists('get_accessible_academic_years')) {
    function get_accessible_academic_years(): array
    {
        global $pdo;

        $fallback = [[
            'id' => 1,
            'year_name' => '2024 - 2025',
            'is_current' => 1,
        ]];

        if (!($pdo instanceof PDO) || !school_table_exists($pdo, 'academic_years')) {
            return $fallback;
        }

        try {
            $tenantId = max(1, (int)($_SESSION['tenant_id'] ?? 1));
            $userId = (int)($_SESSION['user_id'] ?? 0);
            $params = ['tenant_id' => $tenantId];

            $sql = "SELECT ay.id, ay.year_name, ay.is_current
                    FROM academic_years ay";

            if ($userId > 0 && school_table_exists($pdo, 'user_academic_year_access')) {
                $sql .= " LEFT JOIN user_academic_year_access uaya
                             ON uaya.academic_year_id = ay.id
                            AND uaya.user_id = :user_id";
                $params['user_id'] = $userId;
            }

            $sql .= " WHERE ay.tenant_id = :tenant_id
                        AND ay.status = 'active'";

            if (array_key_exists('user_id', $params)) {
                $sql .= ' AND COALESCE(uaya.can_access, 1) = 1';
            }

            $sql .= ' ORDER BY ay.is_current DESC, ay.id DESC';

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return is_array($rows) && $rows ? $rows : $fallback;
        } catch (Throwable $e) {
            error_log('get_accessible_academic_years: ' . $e->getMessage());
            return $fallback;
        }
    }
}

if (!function_exists('current_academic_year')) {
    function current_academic_year(): array
    {
        $years = get_accessible_academic_years();
        $selectedId = (int)($_SESSION['academic_year_id'] ?? 0);

        foreach ($years as $year) {
            if ($selectedId > 0 && (int)($year['id'] ?? 0) === $selectedId) {
                return $year;
            }
        }

        foreach ($years as $year) {
            if ((int)($year['is_current'] ?? 0) === 1) {
                $_SESSION['academic_year_id'] = (int)($year['id'] ?? 0);
                return $year;
            }
        }

        $first = is_array($years[0] ?? null)
            ? $years[0]
            : ['id' => 1, 'year_name' => '2024 - 2025', 'is_current' => 1];

        $_SESSION['academic_year_id'] = (int)($first['id'] ?? 1);
        return $first;
    }
}

/*
|--------------------------------------------------------------------------
| Permissions
|--------------------------------------------------------------------------
*/


if (!function_exists('school_permission_key_for_page')) {
    function school_permission_key_for_page(string $pageKey): ?string
    {
        $key = strtolower(trim($pageKey));

        if ($key === '' || $key === 'dashboard' || $key === 'logout') {
            return null;
        }

        if ($key === 'theme_settings' || str_contains($key, 'theme_settings')) {
            return 'theme_settings_access';
        }

        $map = [
            'branches' => 'branch_management',
            'branch_management' => 'branch_management',
            'users' => 'user_management',
            'user_list' => 'user_management',
            'roles_permissions' => 'user_management',
            'student_management' => 'student_management',
            'students' => 'student_management',
            'admissions' => 'student_management',
            'student_promotion' => 'student_management',
            'transfer_certificate' => 'student_management',
            'teacher_management' => 'staff_management',
            'teachers' => 'staff_management',
            'staff' => 'staff_management',
            'leave_management' => 'staff_management',
            'fees' => 'fee_management',
            'fee_structure' => 'fee_management',
            'fee_collection' => 'fee_management',
            'fee_reports' => 'fee_management',
            'due_fees' => 'fee_management',
            'examinations' => 'examination_management',
            'exam_setup' => 'examination_management',
            'exam_schedule' => 'examination_management',
            'marks_entry' => 'examination_management',
            'grade_management' => 'examination_management',
            'report_cards' => 'examination_management',
            'attendance' => 'attendance_management',
            'staff_attendance' => 'attendance_management',
            'attendance_reports' => 'attendance_management',
            'reports' => 'reports_access',
            'student_reports' => 'reports_access',
            'examination_reports' => 'reports_access',
            'reports_fee_reports' => 'reports_access',
            'settings' => 'settings_access',
            'school_profile' => 'settings_access',
            'general_settings' => 'settings_access',
            'backup_restore' => 'settings_access',
        ];

        if (isset($map[$key])) {
            return $map[$key];
        }

        $prefixes = [
            'branch' => 'branch_management',
            'user' => 'user_management',
            'role' => 'user_management',
            'student' => 'student_management',
            'admission' => 'student_management',
            'teacher' => 'staff_management',
            'staff' => 'staff_management',
            'leave' => 'staff_management',
            'fee' => 'fee_management',
            'exam' => 'examination_management',
            'mark' => 'examination_management',
            'grade' => 'examination_management',
            'attendance' => 'attendance_management',
            'report' => 'reports_access',
            'setting' => 'settings_access',
        ];

        foreach ($prefixes as $prefix => $permission) {
            if (str_starts_with($key, $prefix)) {
                return $permission;
            }
        }

        return null;
    }
}

if (!function_exists('user_has_module_permission')) {
    function user_has_module_permission(string $permissionKey): bool
    {
        global $pdo;

        if (is_super_admin()) {
            return true;
        }

        if (
            !($pdo instanceof PDO)
            || !school_table_exists($pdo, 'user_module_permissions')
        ) {
            return true;
        }

        $userId = (int)($_SESSION['user_id'] ?? 0);
        $schoolId = (int)(
            $_SESSION['school_id']
            ?? $_SESSION['tenant_id']
            ?? 0
        );

        if ($userId <= 0 || $schoolId <= 0) {
            return false;
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT permission_key,is_allowed
                 FROM user_module_permissions
                 WHERE tenant_id=:school_id
                   AND user_id=:user_id
                   AND permission_key IN
                       ('full_school_access', :permission_key)"
            );
            $stmt->execute([
                'school_id' => $schoolId,
                'user_id' => $userId,
                'permission_key' => $permissionKey,
            ]);

            $specificFound = false;
            $specificAllowed = false;

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $key = (string)$row['permission_key'];
                $allowed = (int)$row['is_allowed'] === 1;

                if ($key === 'full_school_access' && $allowed) {
                    return true;
                }
                if ($key === $permissionKey) {
                    $specificFound = true;
                    $specificAllowed = $allowed;
                }
            }

            return $specificFound ? $specificAllowed : true;
        } catch (Throwable $e) {
            error_log('user_has_module_permission: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('has_platform_permission')) {
    function has_platform_permission(
        string $pageKey,
        string $action = 'view',
        ?int $userId = null
    ): bool {
        global $pdo;

        if (APP_DEMO_MODE) {
            return true;
        }

        if (!($pdo instanceof PDO)) {
            return false;
        }

        $userId = $userId ?? (int)($_SESSION['user_id'] ?? 0);

        if ($userId <= 0) {
            return false;
        }

        foreach (
            [
                'permissions',
                'role_permissions',
                'permission_actions',
                'user_roles',
            ]
            as $table
        ) {
            if (!school_table_exists($pdo, $table)) {
                return is_super_admin();
            }
        }

        if (
            !school_column_exists($pdo, 'roles', 'role_scope')
            || !school_column_exists(
                $pdo,
                'app_pages',
                'portal_scope'
            )
        ) {
            return is_super_admin();
        }

        $pageKey = strtolower(trim($pageKey));
        $action = strtolower(trim($action));

        $actionAliases = [
            'manage' => 'manage_settings',
            'assign' => 'manage_settings',
            'lock' => 'manage_settings',
            'override' => 'manage_settings',
            'update' => 'edit',
            'open' => 'view',
            'publish' => 'approve',
        ];

        $action = $actionAliases[$action] ?? $action;

        try {
            /*
             * The protected core Super Administrator can never be
             * restricted.
             */
            $core = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM user_roles AS ur
                 INNER JOIN roles AS r
                    ON r.id = ur.role_id
                   AND r.role_scope = 'platform'
                   AND r.role_key = 'super_admin'
                   AND r.is_system = 1
                   AND r.status = 'active'
                   AND r.deleted_at IS NULL
                 WHERE ur.user_id = :user_id"
            );
            $core->execute(['user_id' => $userId]);

            if ((int)$core->fetchColumn() > 0) {
                return true;
            }

            $permission = $pdo->prepare(
                "SELECT
                    p.id,
                    a.action_key
                 FROM permissions AS p
                 INNER JOIN permission_actions AS a
                    ON a.id = p.action_id
                   AND a.action_key IN (
                        :action_key,
                        'full_access'
                   )
                 INNER JOIN app_pages AS ap
                    ON ap.id = p.page_id
                   AND ap.page_key = :page_key
                   AND ap.portal_scope = 'super_admin'
                   AND ap.is_active = 1
                 WHERE p.portal_scope = 'super_admin'
                   AND p.is_active = 1"
            );
            $permission->execute([
                'action_key' => $action,
                'page_key' => $pageKey,
            ]);

            $permissionRows = $permission->fetchAll(PDO::FETCH_ASSOC);

            if ($permissionRows === []) {
                return false;
            }

            $permissionIds = array_map(
                static fn(array $row): int => (int)$row['id'],
                $permissionRows
            );

            $specificPermissionIds = array_map(
                static fn(array $row): int => (int)$row['id'],
                array_filter(
                    $permissionRows,
                    static fn(array $row): bool =>
                        $row['action_key'] === $action
                )
            );

            if (
                school_table_exists($pdo, 'user_permissions')
                && $specificPermissionIds !== []
            ) {
                $marks = implode(
                    ',',
                    array_fill(
                        0,
                        count($specificPermissionIds),
                        '?'
                    )
                );

                $override = $pdo->prepare(
                    "SELECT effect
                     FROM user_permissions
                     WHERE user_id = ?
                       AND permission_id IN ({$marks})
                     ORDER BY
                        CASE effect
                            WHEN 'deny' THEN 0
                            ELSE 1
                        END
                     LIMIT 1"
                );
                $override->execute([
                    $userId,
                    ...$specificPermissionIds,
                ]);
                $effect = strtolower((string)(
                    $override->fetchColumn() ?: ''
                ));

                if ($effect === 'deny') {
                    return false;
                }

                if ($effect === 'allow') {
                    return true;
                }
            }

            $marks = implode(
                ',',
                array_fill(0, count($permissionIds), '?')
            );

            $roleGrant = $pdo->prepare(
                "SELECT COALESCE(MAX(rp.is_allowed), 0)
                 FROM user_roles AS ur
                 INNER JOIN roles AS r
                    ON r.id = ur.role_id
                   AND r.role_scope = 'platform'
                   AND r.status = 'active'
                   AND r.deleted_at IS NULL
                 INNER JOIN role_permissions AS rp
                    ON rp.role_id = r.id
                   AND rp.permission_id IN ({$marks})
                 WHERE ur.user_id = ?"
            );
            $roleGrant->execute([
                ...$permissionIds,
                $userId,
            ]);

            return (int)$roleGrant->fetchColumn() === 1;
        } catch (Throwable $exception) {
            error_log(
                'has_platform_permission: '
                . $exception->getMessage()
            );

            return false;
        }
    }
}

if (!function_exists('require_platform_permission')) {
    function require_platform_permission(string $pageKey, string $action = 'view'): void
    {
        require_login();
        if (!has_platform_permission($pageKey, $action)) {
            http_response_code(403);
            exit('Access denied.');
        }
    }
}

if (!function_exists('platform_audit_log')) {
    function platform_audit_log(
        string $action,
        string $description,
        ?string $tableName = null,
        ?int $recordId = null,
        array $oldValues = [],
        array $newValues = []
    ): void {
        global $pdo;
        if (!($pdo instanceof PDO) || !school_table_exists($pdo, 'activity_logs')) return;
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO activity_logs
                (tenant_id,branch_id,user_id,role_id,module_name,action_key,table_name,record_id,old_values,new_values,description,ip_address,user_agent)
                VALUES
                (:tenant_id,NULL,:user_id,:role_id,'SaaS Platform',:action_key,:table_name,:record_id,:old_values,:new_values,:description,:ip_address,:user_agent)"
            );
            $stmt->execute([
                'tenant_id' => max(1, (int)($_SESSION['tenant_id'] ?? $_SESSION['school_id'] ?? 1)),
                'user_id' => (int)($_SESSION['user_id'] ?? 0) ?: null,
                'role_id' => (int)($_SESSION['role_id'] ?? 0) ?: null,
                'action_key' => $action,
                'table_name' => $tableName,
                'record_id' => $recordId,
                'old_values' => $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null,
                'new_values' => $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null,
                'description' => substr($description, 0, 255),
                'ip_address' => substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
                'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1000) ?: null,
            ]);
        } catch (Throwable $e) {
            error_log('platform_audit_log: ' . $e->getMessage());
        }
    }
}

if (!function_exists('platform_support_access_school_id')) {
    function platform_support_access_school_id(): int
    {
        global $pdo;
        if (!current_user_has_platform_role()
            || !($pdo instanceof PDO)
            || !school_table_exists($pdo, 'support_access_sessions')) return 0;
        $sessionId = (int)($_SESSION['support_access_session_id'] ?? 0);
        if ($sessionId <= 0) return 0;
        try {
            $stmt = $pdo->prepare(
                "SELECT tenant_id FROM support_access_sessions
                 WHERE id=:session_id AND user_id=:user_id
                   AND status='active' AND expires_at>NOW() LIMIT 1"
            );
            $stmt->execute(['session_id' => $sessionId, 'user_id' => (int)($_SESSION['user_id'] ?? 0)]);
            return (int)($stmt->fetchColumn() ?: 0);
        } catch (Throwable $e) {
            error_log('platform_support_access_school_id: ' . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('begin_platform_support_access')) {
    function begin_platform_support_access(int $schoolId, string $reason, int $durationMinutes = 30): bool
    {
        global $pdo;
        $reason = trim($reason);
        if (!has_platform_permission('platform_support_access', 'manage')
            || !($pdo instanceof PDO) || $schoolId <= 0 || $reason === ''
            || !school_table_exists($pdo, 'support_access_sessions')) return false;
        try {
            $schoolStmt = $pdo->prepare(
                "SELECT id,school_name FROM tenants
                 WHERE id=:school_id AND status IN ('trial','active') LIMIT 1"
            );
            $schoolStmt->execute(['school_id' => $schoolId]);
            $school = $schoolStmt->fetch(PDO::FETCH_ASSOC);
            if (!$school) return false;
            $minutes = min(120, max(5, $durationMinutes));
            $expiresAt = date('Y-m-d H:i:s', time() + ($minutes * 60));
            $pdo->beginTransaction();
            $close = $pdo->prepare(
                "UPDATE support_access_sessions SET status='ended',ended_at=NOW()
                 WHERE user_id=:user_id AND status='active'"
            );
            $close->execute(['user_id' => (int)($_SESSION['user_id'] ?? 0)]);
            $stmt = $pdo->prepare(
                "INSERT INTO support_access_sessions
                (tenant_id,user_id,reason,started_at,expires_at,ip_address,user_agent,status)
                VALUES (:school_id,:user_id,:reason,NOW(),:expires_at,:ip_address,:user_agent,'active')"
            );
            $stmt->execute([
                'school_id' => $schoolId,
                'user_id' => (int)($_SESSION['user_id'] ?? 0),
                'reason' => $reason,
                'expires_at' => $expiresAt,
                'ip_address' => substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
                'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1000) ?: null,
            ]);
            $id = (int)$pdo->lastInsertId();
            platform_audit_log(
                'support_access_started',
                'Started support access for ' . $school['school_name'] . '. Reason: ' . $reason,
                'support_access_sessions',
                $id,
                [],
                ['school_id' => $schoolId, 'duration_minutes' => $minutes, 'reason' => $reason]
            );
            $pdo->commit();
            $_SESSION['support_access_session_id'] = $id;
            $_SESSION['active_school_id'] = $schoolId;
            return true;
        } catch (Throwable $e) {
            if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
            error_log('begin_platform_support_access: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('end_platform_support_access')) {
    function end_platform_support_access(): void
    {
        global $pdo;
        $id = (int)($_SESSION['support_access_session_id'] ?? 0);
        if ($id > 0 && $pdo instanceof PDO && school_table_exists($pdo, 'support_access_sessions')) {
            try {
                $stmt = $pdo->prepare(
                    "UPDATE support_access_sessions SET status='ended',ended_at=NOW()
                     WHERE id=:session_id AND user_id=:user_id AND status='active'"
                );
                $stmt->execute(['session_id' => $id, 'user_id' => (int)($_SESSION['user_id'] ?? 0)]);
                platform_audit_log('support_access_ended', 'Ended support access session.', 'support_access_sessions', $id);
            } catch (Throwable $e) {
                error_log('end_platform_support_access: ' . $e->getMessage());
            }
        }
        unset($_SESSION['support_access_session_id'], $_SESSION['active_school_id']);
    }
}

if (!function_exists('require_platform_support_access')) {
    function require_platform_support_access(int $schoolId): void
    {
        if (!current_user_has_platform_role()
            || !has_platform_permission('platform_support_access', 'manage')
            || platform_support_access_school_id() !== $schoolId) {
            http_response_code(403);
            exit('Support Access permission and an active logged session are required.');
        }
        platform_audit_log(
            'support_access_request',
            'Accessed school-scoped data through Support Access.',
            'tenants',
            $schoolId,
            [],
            ['request_uri' => (string)($_SERVER['REQUEST_URI'] ?? '')]
        );
    }
}

if (!function_exists('enforce_platform_api_access')) {
    function enforce_platform_api_access(string $relativePath): void
    {
        global $pdo;

        if (!current_user_has_platform_role()) {
            return;
        }

        $apiFile = strtolower(basename($relativePath));
        $sharedPlatformApis = [
            'auth.php',
            'super-admin-sidebar-settings.php',
            'school-admin-sidebar-settings.php',
        ];

        if (in_array($apiFile, $sharedPlatformApis, true)) {
            return;
        }

        if ($pdo instanceof PDO
            && school_table_exists($pdo, 'app_pages')
            && school_column_exists($pdo, 'app_pages', 'portal_scope')) {
            try {
                $statement = $pdo->query(
                    "SELECT page_key,route
                     FROM app_pages
                     WHERE portal_scope='super_admin'
                       AND is_active=1"
                );

                foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $page) {
                    if (strtolower(basename((string)$page['route'])) === $apiFile) {
                        if (!has_platform_permission((string)$page['page_key'], 'view')) {
                            http_response_code(403);
                            exit('Access denied.');
                        }
                        return;
                    }
                }
            } catch (Throwable $exception) {
                error_log(
                    'enforce_platform_api_access: '
                    . $exception->getMessage()
                );
            }
        }

        $schoolId = platform_support_access_school_id();

        if ($schoolId <= 0) {
            http_response_code(403);
            exit('An active logged Support Access session is required for school APIs.');
        }

        require_platform_support_access($schoolId);
    }
}

if (!function_exists('enforce_current_platform_page_permission')) {
    function enforce_current_platform_page_permission(string $relativePath): void
    {
        global $pdo;
        if (!current_user_has_platform_role()) return;
        if (!($pdo instanceof PDO) || !school_table_exists($pdo, 'app_pages')
            || !school_column_exists($pdo, 'app_pages', 'portal_scope')) {
            if (!is_super_admin()) {
                http_response_code(403);
                exit('Access denied.');
            }
            return;
        }
        $route = ltrim($relativePath, '/');
        try {
            $stmt = $pdo->prepare(
                "SELECT page_key FROM app_pages
                 WHERE portal_scope='super_admin' AND is_active=1
                   AND (route=:route OR route=:root_route)
                 ORDER BY show_in_sidebar DESC,id LIMIT 1"
            );
            $stmt->execute(['route' => $route, 'root_route' => basename($route)]);
            $pageKey = (string)($stmt->fetchColumn() ?: '');
            if ($pageKey === '') {
                if (!is_super_admin()) {
                    http_response_code(403);
                    exit('This platform page is not registered in the permission catalogue.');
                }
                return;
            }
            if (!has_platform_permission($pageKey, 'view')) {
                http_response_code(403);
                exit('Access denied.');
            }
        } catch (Throwable $e) {
            error_log('enforce_current_platform_page_permission: ' . $e->getMessage());
            if (!is_super_admin()) {
                http_response_code(403);
                exit('Access denied.');
            }
        }
    }
}


if (!function_exists('school_effective_role_ids')) {
    /**
     * Return every active School role assigned to the user for this tenant.
     *
     * The previous implementation returned immediately when user_roles had
     * any row. That could silently drop users.role_id / session role_id, which
     * especially affected the protected School Administrator account.
     *
     * @return array<int,int>
     */
    function school_effective_role_ids(
        int $tenantId,
        ?int $userId = null
    ): array {
        global $pdo;

        if (!($pdo instanceof PDO) || $tenantId <= 0) {
            return [];
        }

        $userId = $userId ?? (int)($_SESSION['user_id'] ?? 0);

        if ($userId <= 0) {
            return [];
        }

        $candidateRoleIds = [];

        $addRoleId = static function (mixed $roleId) use (&$candidateRoleIds): void {
            $roleId = (int)$roleId;
            if ($roleId > 0) {
                $candidateRoleIds[$roleId] = $roleId;
            }
        };

        /*
         * Preserve the role that authenticated the current session. Do not
         * allow a stale/incomplete user_roles table to hide the Admin role.
         */
        if ($userId === (int)($_SESSION['user_id'] ?? 0)) {
            $addRoleId($_SESSION['role_id'] ?? 0);

            $sessionUser = function_exists('current_user')
                ? current_user()
                : [];
            if (is_array($sessionUser)) {
                $addRoleId($sessionUser['role_id'] ?? 0);
            }
        }

        try {
            if (school_table_exists($pdo, 'user_roles')) {
                $orderBy = school_column_exists($pdo, 'user_roles', 'is_primary')
                    ? 'ORDER BY ur.is_primary DESC, ur.role_id'
                    : 'ORDER BY ur.role_id';

                $scopeFilter = school_column_exists($pdo, 'roles', 'role_scope')
                    ? "AND r.role_scope = 'school'"
                    : '';
                $deletedFilter = school_column_exists($pdo, 'roles', 'deleted_at')
                    ? 'AND r.deleted_at IS NULL'
                    : '';

                $statement = $pdo->prepare(
                    "SELECT DISTINCT ur.role_id
                     FROM user_roles AS ur
                     INNER JOIN roles AS r
                        ON r.id = ur.role_id
                       AND r.tenant_id = :tenant_id
                       {$scopeFilter}
                       AND r.status = 'active'
                       {$deletedFilter}
                     WHERE ur.user_id = :user_id
                     {$orderBy}"
                );
                $statement->execute([
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                ]);

                foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $roleId) {
                    $addRoleId($roleId);
                }
            }

            if (school_table_exists($pdo, 'users')
                && school_column_exists($pdo, 'users', 'role_id')) {
                $statement = $pdo->prepare(
                    "SELECT role_id
                     FROM users
                     WHERE id = :user_id
                       AND tenant_id = :tenant_id
                     LIMIT 1"
                );
                $statement->execute([
                    'user_id' => $userId,
                    'tenant_id' => $tenantId,
                ]);
                $addRoleId($statement->fetchColumn());
            }

            if ($candidateRoleIds === []) {
                return [];
            }

            /*
             * Validate session/user candidates against the current tenant and
             * active School role catalogue before using them.
             */
            if (!school_table_exists($pdo, 'roles')) {
                return array_values($candidateRoleIds);
            }

            $marks = implode(',', array_fill(0, count($candidateRoleIds), '?'));
            $scopeFilter = school_column_exists($pdo, 'roles', 'role_scope')
                ? "AND role_scope = 'school'"
                : '';
            $deletedFilter = school_column_exists($pdo, 'roles', 'deleted_at')
                ? 'AND deleted_at IS NULL'
                : '';

            $statement = $pdo->prepare(
                "SELECT id
                 FROM roles
                 WHERE tenant_id = ?
                   AND id IN ({$marks})
                   AND status = 'active'
                   {$scopeFilter}
                   {$deletedFilter}
                 ORDER BY id"
            );
            $statement->execute([
                $tenantId,
                ...array_values($candidateRoleIds),
            ]);

            return array_values(array_unique(array_map(
                'intval',
                $statement->fetchAll(PDO::FETCH_COLUMN)
            )));
        } catch (Throwable $exception) {
            error_log(
                'school_effective_role_ids: '
                . $exception->getMessage()
            );

            return [];
        }
    }
}



/*
|--------------------------------------------------------------------------
| Strict School sidebar action permissions
|--------------------------------------------------------------------------
|
| The School Role Permission matrix stores View/Add/Edit/Delete in
| school_sidebar_action_permissions. These helpers make that table the
| authoritative source for every sidebar-linked School page and API.
|
*/

if (!function_exists('school_request_payload')) {
    function school_request_payload(): array
    {
        static $payload = null;

        if (is_array($payload)) {
            return $payload;
        }

        $payload = is_array($_POST) ? $_POST : [];
        $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));

        if (str_contains($contentType, 'application/json')) {
            $raw = (string)file_get_contents('php://input');
            $decoded = $raw !== '' ? json_decode($raw, true) : null;

            if (is_array($decoded)) {
                $payload = array_merge($payload, $decoded);
            }
        }

        return $payload;
    }
}

if (!function_exists('school_sidebar_normalize_action')) {
    function school_sidebar_normalize_action(string $action): string
    {
        $action = strtolower(trim($action));
        $action = preg_replace('/[^a-z0-9]+/', '_', $action) ?? '';
        $action = trim($action, '_');

        if ($action === '') {
            return 'view';
        }

        /*
         * Keep atomic actions separate. permission_actions and
         * school_sidebar_permission_grants are intentionally extensible, so a
         * future action key can be added in the database without editing this
         * resolver. Common API verbs are only normalized to their canonical
         * permission section.
         */
        $direct = [
            'view' => 'view',
            'read' => 'view',
            'list' => 'view',
            'detail' => 'view',
            'details' => 'view',
            'meta' => 'view',
            'search' => 'view',
            'load' => 'view',
            'fetch' => 'view',
            'show' => 'view',
            'preview' => 'view',
            'open' => 'view',

            'add' => 'create',
            'create' => 'create',
            'store' => 'create',
            'insert' => 'create',
            'new' => 'create',
            'copy' => 'create',
            'clone' => 'create',
            'duplicate' => 'create',
            'register' => 'create',

            'edit' => 'edit',
            'update' => 'edit',
            'modify' => 'edit',
            'save' => 'edit',
            'toggle' => 'edit',
            'status' => 'edit',
            'activate' => 'edit',
            'deactivate' => 'edit',
            'assign' => 'edit',
            'lock' => 'edit',
            'publish' => 'edit',
            'pay' => 'edit',
            'collect' => 'edit',
            'receive' => 'edit',
            'process' => 'edit',
            'complete' => 'edit',
            'cancel' => 'edit',
            'archive' => 'edit',

            'delete' => 'delete',
            'remove' => 'delete',
            'destroy' => 'delete',
            'trash' => 'delete',

            'print' => 'print',
            'pdf' => 'pdf',
            'export' => 'export',
            'download' => 'export',
            'import' => 'import',
            'upload' => 'import',
            'approve' => 'approve',
            'reject' => 'reject',
            'restore' => 'restore',
            'manage' => 'manage_settings',
            'settings' => 'manage_settings',
            'manage_settings' => 'manage_settings',
            'visibility' => 'manage_visibility',
            'manage_visibility' => 'manage_visibility',
            'full' => 'full_access',
            'full_access' => 'full_access',
        ];

        if (isset($direct[$action])) {
            return $direct[$action];
        }

        $contains = [
            'delete' => ['delete', 'remove', 'destroy', 'trash'],
            'restore' => ['restore', 'recover'],
            'approve' => ['approve'],
            'reject' => ['reject'],
            'print' => ['print'],
            'pdf' => ['pdf'],
            'export' => ['export', 'download'],
            'import' => ['import', 'upload'],
            'create' => ['copy', 'clone', 'duplicate', 'create', 'insert', 'register', 'add', 'new'],
            'manage_visibility' => ['manage_visibility', 'visibility'],
            'manage_settings' => ['manage_settings', 'setting', 'manage'],
            'edit' => [
                'edit', 'update', 'modify', 'toggle', 'status', 'activate',
                'deactivate', 'assign', 'lock', 'publish', 'pay', 'collect',
                'receive', 'process', 'complete', 'cancel', 'archive', 'save',
            ],
            'view' => ['view', 'detail', 'show', 'preview', 'open', 'list', 'search', 'load', 'fetch'],
        ];

        foreach ($contains as $canonical => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($action, $needle)) {
                    return $canonical;
                }
            }
        }

        /* Future database-defined action section. */
        return preg_match('/^[a-z][a-z0-9_]{0,49}$/', $action) ? $action : 'view';
    }
}

if (!function_exists('school_role_ids_include_school_admin')) {
    /**
     * Determine whether one of the user's active tenant roles is the protected
     * School Administrator role.
     *
     * Existing databases use several compatible names, including
     * school_admin, school_administrator and admin. Role names are checked as
     * well so older records named "Admin" continue to work.
     *
     * @param array<int,int> $roleIds
     */
    function school_role_ids_include_school_admin(
        PDO $pdo,
        int $tenantId,
        array $roleIds
    ): bool {
        $roleIds = array_values(array_unique(array_filter(
            array_map('intval', $roleIds),
            static fn(int $id): bool => $id > 0
        )));

        if ($tenantId <= 0 || $roleIds === [] || !school_table_exists($pdo, 'roles')) {
            return false;
        }

        try {
            $marks = implode(',', array_fill(0, count($roleIds), '?'));
            $deleted = school_column_exists($pdo, 'roles', 'deleted_at')
                ? 'AND deleted_at IS NULL'
                : '';
            $scope = school_column_exists($pdo, 'roles', 'role_scope')
                ? "AND role_scope = 'school'"
                : '';
            $systemSelect = school_column_exists($pdo, 'roles', 'is_system')
                ? 'is_system'
                : '0 AS is_system';

            $stmt = $pdo->prepare(
                "SELECT id, role_key, role_name, {$systemSelect}
                 FROM roles
                 WHERE tenant_id = ?
                   AND id IN ({$marks})
                   AND status = 'active'
                   {$scope}
                   {$deleted}"
            );
            $stmt->execute([$tenantId, ...$roleIds]);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $role) {
                $rawKey = strtolower(trim((string)($role['role_key'] ?? '')));
                $rawName = strtolower(trim((string)($role['role_name'] ?? '')));

                $normalized = function_exists('school_normalize_role_key')
                    ? school_normalize_role_key($rawKey, $rawName)
                    : trim((string)preg_replace(
                        '/[^a-z0-9]+/',
                        '_',
                        $rawKey !== '' ? $rawKey : $rawName
                    ), '_');

                if ($normalized === 'school_admin') {
                    return true;
                }

                if (in_array(
                    $rawName,
                    [
                        'school administrator',
                        'school admin',
                        'administrator',
                        'admin',
                    ],
                    true
                )) {
                    return true;
                }

                /*
                 * Preserve protected system Admin records whose old role key
                 * used a non-standard separator or suffix.
                 */
                if ((int)($role['is_system'] ?? 0) === 1
                    && (str_contains($rawKey, 'admin')
                        || str_contains($rawName, 'admin'))) {
                    return true;
                }
            }

            return false;
        } catch (Throwable $exception) {
            error_log('school_role_ids_include_school_admin: ' . $exception->getMessage());
            return false;
        }
    }
}

if (!function_exists('school_sidebar_assigned_items')) {
    /**
     * Return the EFFECTIVE sidebar for one school.
     *
     * Default inheritance:
     * - shared Default item + no tenant override => use default is_enabled
     * - explicit tenant row                      => use tenant is_visible
     * - school-owned custom item                 => requires its tenant row
     *
     * This means a newly enabled Default Sidebar item is automatically visible
     * to every school without copying rows into tenant_sidebar_items.
     *
     * @return array<int,array<string,mixed>>
     */
    function school_sidebar_assigned_items(int $tenantId): array
    {
        global $pdo;
        static $cache=[];

        if (isset($cache[$tenantId])) {
            return $cache[$tenantId];
        }

        if (!($pdo instanceof PDO) || $tenantId<=0
            || !school_table_exists($pdo,'sidebar_items')) {
            return $cache[$tenantId]=[];
        }

        try {
            $hasTenantTable=school_table_exists($pdo,'tenant_sidebar_items');
            $hasDefaults=school_table_exists($pdo,'sidebar_default_settings');
            $hasCustomRoute=$hasTenantTable
                && school_column_exists($pdo,'tenant_sidebar_items','custom_route');
            $hasCustomParent=$hasTenantTable
                && school_column_exists($pdo,'tenant_sidebar_items','custom_parent_id');
            $hasInherit=$hasTenantTable
                && school_column_exists($pdo,'tenant_sidebar_items','inherit_default');
            $hasPortal=school_column_exists($pdo,'sidebar_items','portal_scope');
            $hasOwner=school_column_exists($pdo,'sidebar_items','owner_tenant_id');
            $hasModuleId=school_column_exists($pdo,'sidebar_items','module_id');
            /*
             * Sidebar visibility is controlled by Default Sidebar + the
             * selected school's explicit override. Do not apply tenant_modules
             * as a second hidden visibility gate here, otherwise Super Admin
             * can show an item as Enabled while the School Admin sidebar still
             * hides it.
             */
            $hasTenantModules=false;

            $explicit = !$hasTenantTable
                ? '0=1'
                : ($hasInherit
                    ? '(tsi.id IS NOT NULL AND COALESCE(tsi.inherit_default,0)=0)'
                    : '(tsi.id IS NOT NULL)');

            $defaultEnabled=$hasDefaults
                ? 'COALESCE(sds.is_enabled,1)'
                : '1';

            $route = $hasCustomRoute
                ? "CASE WHEN {$explicit}
                         THEN COALESCE(NULLIF(TRIM(tsi.custom_route),''),si.route)
                         ELSE si.route END"
                : 'si.route';

            $parent = $hasCustomParent
                ? "CASE WHEN {$explicit}
                         THEN COALESCE(tsi.custom_parent_id,si.parent_id)
                         ELSE si.parent_id END"
                : 'si.parent_id';

            $tenantJoin=$hasTenantTable
                ? 'LEFT JOIN tenant_sidebar_items tsi
                     ON tsi.sidebar_item_id=si.id
                    AND tsi.tenant_id=:sidebar_tenant_id'
                : '';

            $defaultJoin=$hasDefaults
                ? 'LEFT JOIN sidebar_default_settings sds
                     ON sds.sidebar_item_id=si.id'
                : '';

            $effectiveEnabled = $hasOwner
                ? "CASE
                     WHEN {$explicit} THEN COALESCE(tsi.is_visible,0)
                     WHEN si.owner_tenant_id=:owner_visibility_id THEN 0
                     ELSE {$defaultEnabled}
                   END"
                : "CASE
                     WHEN {$explicit} THEN COALESCE(tsi.is_visible,0)
                     ELSE {$defaultEnabled}
                   END";

            $where=["({$effectiveEnabled})=1"];
            $params=[];

            if ($hasTenantTable) {
                $params['sidebar_tenant_id']=$tenantId;
            }
            if ($hasOwner) {
                $params['owner_visibility_id']=$tenantId;
            }

            if (school_column_exists($pdo,'sidebar_items','is_active')) {
                $where[]='si.is_active=1';
            }
            if (school_column_exists($pdo,'sidebar_items','show_in_sidebar')) {
                $where[]='si.show_in_sidebar=1';
            }
            if ($hasPortal) {
                $where[]="si.portal_scope IN ('school','all')";
            } else {
                $where[]="si.menu_key NOT LIKE 'sa\\_%'";
            }

            if ($hasOwner) {
                /*
                 * Shared master rows are inherited. A school-owned custom menu
                 * is visible only to its owner and only when an explicit tenant
                 * row exists.
                 */
                $where[]='(
                    si.owner_tenant_id IS NULL
                    OR (
                        si.owner_tenant_id=:owner_tenant_id
                        AND ' . ($hasTenantTable ? 'tsi.id IS NOT NULL' : '0=1') . '
                    )
                )';
                $params['owner_tenant_id']=$tenantId;
            }

            $moduleJoin='';
            if ($hasTenantModules) {
                $moduleJoin='LEFT JOIN tenant_modules tm
                    ON tm.module_id=si.module_id
                   AND tm.tenant_id=:module_tenant_id';
                $where[]='(si.module_id IS NULL OR COALESCE(tm.is_enabled,1)=1)';
                $params['module_tenant_id']=$tenantId;
            }

            $stmt=$pdo->prepare(
                "SELECT
                    si.id,
                    {$parent} AS parent_id,
                    si.menu_key,
                    {$route} AS effective_route
                 FROM sidebar_items si
                 {$defaultJoin}
                 {$tenantJoin}
                 {$moduleJoin}
                 WHERE ".implode(' AND ',$where)
            );
            $stmt->execute($params);

            $items=[];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $id=(int)($row['id']??0);
                if ($id<=0) {
                    continue;
                }

                $row['id']=$id;
                $row['parent_id']=(int)($row['parent_id']??0);
                $row['menu_key']=school_permission_canonical_page_key(
                    (string)($row['menu_key']??''),
                    (string)($row['effective_route']??'')
                );
                $row['effective_route']=school_normalize_sidebar_route(
                    (string)($row['effective_route']??'')
                );
                $items[$id]=$row;
            }

            return $cache[$tenantId]=$items;
        } catch (Throwable $exception) {
            error_log(
                'school_sidebar_assigned_items(default inheritance): '
                . $exception->getMessage()
            );
            return $cache[$tenantId]=[];
        }
    }
}

if (!function_exists('school_app_page_route_for_key')) {
    function school_app_page_route_for_key(string $pageKey): string
    {
        global $pdo;

        if (!($pdo instanceof PDO)
            || $pageKey === ''
            || !school_table_exists($pdo, 'app_pages')) {
            return '';
        }

        try {
            $portal = school_column_exists($pdo, 'app_pages', 'portal_scope')
                ? "AND portal_scope = 'school'"
                : '';
            $stmt = $pdo->prepare(
                "SELECT route
                 FROM app_pages
                 WHERE LOWER(page_key) = :page_key
                   AND is_active = 1
                   {$portal}
                 ORDER BY show_in_sidebar DESC, id
                 LIMIT 1"
            );
            $stmt->execute(['page_key' => strtolower(trim($pageKey))]);
            return school_normalize_sidebar_route((string)($stmt->fetchColumn() ?: ''));
        } catch (Throwable $exception) {
            error_log('school_app_page_route_for_key: ' . $exception->getMessage());
            return '';
        }
    }
}

if (!function_exists('school_app_page_chain_for_key')) {
    /**
     * Return the registered School page followed by its registered parents.
     * This lets detail/form/API pages inherit the permission of the nearest
     * sidebar page instead of falling back to an unrelated broad permission.
     *
     * @return array<int,array<string,mixed>>
     */
    function school_app_page_chain_for_key(string $pageKey): array
    {
        global $pdo;

        $pageKey = school_permission_canonical_page_key($pageKey);

        if (!($pdo instanceof PDO)
            || $pageKey === ''
            || !school_table_exists($pdo, 'app_pages')) {
            return [];
        }

        try {
            $portal = school_column_exists($pdo, 'app_pages', 'portal_scope')
                ? "AND portal_scope = 'school'"
                : '';
            $hasParent = school_column_exists($pdo, 'app_pages', 'parent_page_id');
            $parentSelect = $hasParent ? 'parent_page_id' : 'NULL AS parent_page_id';

            $stmt = $pdo->prepare(
                "SELECT id, page_key, route, {$parentSelect}
                 FROM app_pages
                 WHERE LOWER(page_key) = :page_key
                   AND is_active = 1
                   {$portal}
                 ORDER BY show_in_sidebar DESC, id
                 LIMIT 1"
            );
            $stmt->execute(['page_key' => $pageKey]);
            $page = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!is_array($page)) {
                return [];
            }

            $chain = [];
            $visited = [];

            while (is_array($page)) {
                $id = (int)($page['id'] ?? 0);
                if ($id <= 0 || isset($visited[$id])) {
                    break;
                }

                $visited[$id] = true;
                $page['page_key'] = strtolower(trim((string)($page['page_key'] ?? '')));
                $page['route'] = school_normalize_sidebar_route((string)($page['route'] ?? ''));
                $chain[] = $page;

                $parentId = (int)($page['parent_page_id'] ?? 0);
                if (!$hasParent || $parentId <= 0) {
                    break;
                }

                $parentStmt = $pdo->prepare(
                    "SELECT id, page_key, route, parent_page_id
                     FROM app_pages
                     WHERE id = :page_id
                       AND is_active = 1
                       {$portal}
                     LIMIT 1"
                );
                $parentStmt->execute(['page_id' => $parentId]);
                $page = $parentStmt->fetch(PDO::FETCH_ASSOC);
            }

            return $chain;
        } catch (Throwable $exception) {
            error_log('school_app_page_chain_for_key: ' . $exception->getMessage());
            return [];
        }
    }
}

if (!function_exists('school_sidebar_catalog_match_exists')) {
    function school_sidebar_catalog_match_exists(
        int $tenantId,
        string $pageKey = '',
        string $relativePath = ''
    ): bool {
        global $pdo;

        if (!($pdo instanceof PDO)
            || !school_table_exists($pdo, 'sidebar_items')) {
            return false;
        }

        $pageKey = school_permission_canonical_page_key(
            $pageKey,
            $relativePath
        );
        $route = school_normalize_sidebar_route($relativePath);
        if ($route === '' && $pageKey !== '') {
            $route = school_app_page_route_for_key($pageKey);
        }

        try {
            $hasTenant = $tenantId > 0 && school_table_exists($pdo, 'tenant_sidebar_items');
            $hasCustomRoute = $hasTenant
                && school_column_exists($pdo, 'tenant_sidebar_items', 'custom_route');
            $portal = school_column_exists($pdo, 'sidebar_items', 'portal_scope')
                ? "AND si.portal_scope IN ('school','all')"
                : "AND si.menu_key NOT LIKE 'sa\\_%'";
            $owner = school_column_exists($pdo, 'sidebar_items', 'owner_tenant_id')
                ? 'AND (si.owner_tenant_id IS NULL OR si.owner_tenant_id = :owner_tenant_id)'
                : '';
            $join = $hasTenant
                ? 'LEFT JOIN tenant_sidebar_items AS tsi
                     ON tsi.sidebar_item_id = si.id
                    AND tsi.tenant_id = :tenant_id'
                : '';
            $effectiveRoute = $hasCustomRoute
                ? "COALESCE(NULLIF(TRIM(tsi.custom_route), ''), si.route)"
                : 'si.route';

            $conditions = [];
            $params = [];
            if ($pageKey !== '') {
                $conditions[] = 'LOWER(si.menu_key) = :menu_key';
                $params['menu_key'] = $pageKey;
            }
            if ($route !== '') {
                $conditions[] = "LOWER(SUBSTRING_INDEX({$effectiveRoute}, '/', -1)) = :route_file";
                $params['route_file'] = strtolower(basename($route));
            }
            if ($conditions === []) {
                return false;
            }
            if ($hasTenant) {
                $params['tenant_id'] = $tenantId;
            }
            if ($owner !== '') {
                $params['owner_tenant_id'] = $tenantId;
            }

            $stmt = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM sidebar_items AS si
                 {$join}
                 WHERE (" . implode(' OR ', $conditions) . ")
                   {$portal}
                   {$owner}"
            );
            $stmt->execute($params);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $exception) {
            error_log('school_sidebar_catalog_match_exists: ' . $exception->getMessage());
            return false;
        }
    }
}

if (!function_exists('school_sidebar_find_assigned_item')) {
    /** @return array<string,mixed>|null */
    function school_sidebar_find_assigned_item(
        int $tenantId,
        string $pageKey = '',
        string $relativePath = ''
    ): ?array {
        $items = school_sidebar_assigned_items($tenantId);
        $pageKey = school_permission_canonical_page_key(
            $pageKey,
            $relativePath
        );
        $route = school_normalize_sidebar_route($relativePath);

        $find = static function (string $candidateKey, string $candidateRoute) use ($items): ?array {
            $candidateKey = strtolower(trim($candidateKey));
            $candidateRoute = school_normalize_sidebar_route($candidateRoute);

            if ($candidateKey !== '') {
                foreach ($items as $item) {
                    if ((string)($item['menu_key'] ?? '') === $candidateKey) {
                        return $item;
                    }
                }
            }

            if ($candidateRoute !== '') {
                $routeFile = strtolower(basename($candidateRoute));
                foreach ($items as $item) {
                    $databaseRoute = (string)($item['effective_route'] ?? '');
                    if ($databaseRoute !== ''
                        && ($databaseRoute === $candidateRoute
                            || strtolower(basename($databaseRoute)) === $routeFile)) {
                        return $item;
                    }
                }
            }

            return null;
        };

        $direct = $find($pageKey, $route);
        if ($direct !== null) {
            return $direct;
        }

        if ($route === '' && $pageKey !== '') {
            $route = school_app_page_route_for_key($pageKey);
            $direct = $find($pageKey, $route);
            if ($direct !== null) {
                return $direct;
            }
        }

        /*
         * Detail, form and utility pages can be children of a sidebar page in
         * app_pages. Walk that page hierarchy and use the nearest assigned
         * sidebar ancestor as the authorization boundary.
         */
        if ($pageKey !== '') {
            foreach (school_app_page_chain_for_key($pageKey) as $page) {
                $matched = $find(
                    (string)($page['page_key'] ?? ''),
                    (string)($page['route'] ?? '')
                );
                if ($matched !== null) {
                    return $matched;
                }
            }
        }

        return null;
    }
}


if (!function_exists('school_sidebar_role_master_item_allowed')) {
    function school_sidebar_role_master_item_allowed(
        PDO $pdo,
        array $roleIds,
        int $sidebarItemId,
        int $tenantId
    ): bool {
        if ($sidebarItemId <= 0 || $tenantId <= 0 || $roleIds === []) {
            return false;
        }

        /*
         * Backward compatibility before Super Admin opens the upgraded Sidebar
         * Permission page and creates the role-master catalogue.
         */
        if (!school_table_exists($pdo, 'sidebar_role_master_items')
            || !school_table_exists($pdo, 'roles')) {
            return true;
        }

        $roleIds = array_values(array_unique(array_filter(
            array_map('intval', $roleIds),
            static fn(int $id): bool => $id > 0
        )));
        if ($roleIds === []) {
            return false;
        }

        try {
            $marks = implode(',', array_fill(0, count($roleIds), '?'));
            $stmt = $pdo->prepare(
                "SELECT role_key
                 FROM roles
                 WHERE id IN ({$marks})
                   AND tenant_id=?
                   AND status='active'
                   AND deleted_at IS NULL"
            );
            $stmt->execute([...$roleIds, $tenantId]);

            $keys = [];
            $hasParent = false;
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $rawKey) {
                $key = school_normalize_role_key((string)$rawKey);
                if ($key === 'super_admin') {
                    return true;
                }
                if ($key === 'parent') {
                    $hasParent = true;
                }
                if ($key !== '') {
                    $keys[$key] = $key;
                }
            }

            if ($keys === []) {
                return false;
            }

            /*
             * Preserve existing school-owned custom menus for non-Parent roles.
             * Parent must always come exclusively from its global Parent master.
             */
            if (!$hasParent
                && school_column_exists($pdo, 'sidebar_items', 'owner_tenant_id')) {
                $owner = $pdo->prepare(
                    "SELECT COALESCE(owner_tenant_id,0)
                     FROM sidebar_items
                     WHERE id=?
                     LIMIT 1"
                );
                $owner->execute([$sidebarItemId]);
                if ((int)$owner->fetchColumn() === $tenantId) {
                    return true;
                }
            }

            $keyMarks = implode(',', array_fill(0, count($keys), '?'));
            $stmt = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM sidebar_role_master_items
                 WHERE role_key IN ({$keyMarks})
                   AND sidebar_item_id=?
                   AND is_enabled=1"
            );
            $stmt->execute([...array_values($keys), $sidebarItemId]);

            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $exception) {
            error_log(
                'school_sidebar_role_master_item_allowed: '
                . $exception->getMessage()
            );
            return false;
        }
    }
}

if (!function_exists('school_sidebar_action_allowed_for_item')) {
    function school_sidebar_action_allowed_for_item(
        int $sidebarItemId,
        string $action,
        int $tenantId,
        ?int $userId = null
    ): bool {
        global $pdo;

        if (!($pdo instanceof PDO)
            || $sidebarItemId <= 0
            || $tenantId <= 0) {
            return false;
        }

        $items = school_sidebar_assigned_items($tenantId);
        if (!isset($items[$sidebarItemId])) {
            return false;
        }

        $userId = $userId ?? (int)($_SESSION['user_id'] ?? 0);
        $roleIds = school_effective_role_ids($tenantId, $userId);
        if ($roleIds === []) {
            return false;
        }

        $chain = [];
        $currentId = $sidebarItemId;
        $visited = [];
        while ($currentId > 0) {
            if (isset($visited[$currentId])
                || !isset($items[$currentId])) {
                return false;
            }
            $visited[$currentId] = true;
            $chain[] = $currentId;
            $currentId = (int)($items[$currentId]['parent_id'] ?? 0);
        }

        $action = school_sidebar_normalize_action($action);

        foreach ($roleIds as $roleId) {
            $roleId = (int)$roleId;
            if ($roleId <= 0) continue;

            $masterAllowed = true;
            foreach ($chain as $chainItemId) {
                if (!school_sidebar_role_master_item_allowed(
                    $pdo,
                    [$roleId],
                    (int)$chainItemId,
                    $tenantId
                )) {
                    $masterAllowed = false;
                    break;
                }
            }
            if (!$masterAllowed) continue;

            if (pc_effective_role_chain_action(
                $pdo,
                $tenantId,
                $roleId,
                $chain,
                $action
            )) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('school_sidebar_permission_decision')) {
    function school_sidebar_permission_decision(
        string $pageKey,
        string $action = 'view',
        int $tenantId = 0,
        ?int $userId = null,
        string $relativePath = ''
    ): ?bool {
        $tenantId = $tenantId > 0
            ? $tenantId
            : (int)($_SESSION['school_id'] ?? $_SESSION['tenant_id'] ?? 0);

        if ($tenantId <= 0) {
            return false;
        }

        $item = school_sidebar_find_assigned_item($tenantId, $pageKey, $relativePath);
        if ($item !== null) {
            return school_sidebar_action_allowed_for_item(
                (int)$item['id'],
                $action,
                $tenantId,
                $userId
            );
        }

        /*
         * When the page or one of its registered parents belongs to the School
         * sidebar catalogue but is not assigned to this school, deny it. Never
         * fall through to a broader legacy grant.
         */
        if (school_sidebar_catalog_match_exists($tenantId, $pageKey, $relativePath)) {
            return false;
        }

        foreach (school_app_page_chain_for_key($pageKey) as $page) {
            if (school_sidebar_catalog_match_exists(
                $tenantId,
                (string)($page['page_key'] ?? ''),
                (string)($page['route'] ?? '')
            )) {
                return false;
            }
        }

        return null;
    }
}

if (!function_exists('school_current_page_capabilities')) {
    /**
     * Return fixed compatibility keys and every active database-defined
     * permission action. New permission_actions rows therefore become
     * available to pages without another Bootstrap code change.
     *
     * @return array<string,mixed>
     */
    function school_current_page_capabilities(string $pageKey = ''): array
    {
        global $pdo;

        $pageKey = school_permission_canonical_page_key($pageKey);
        if ($pageKey === '') {
            $pageKey = school_permission_canonical_page_key(
                (string)($GLOBALS['pageKey'] ?? '')
            );
        }

        $relative = ltrim(
            (string)(parse_url($_SERVER['SCRIPT_NAME'] ?? '', PHP_URL_PATH) ?: ''),
            '/'
        );
        $registeredPage = school_registered_page_for_file($relative);
        $registeredKey = strtolower(trim((string)($registeredPage['page_key'] ?? '')));
        if ($registeredKey !== '') {
            $pageKey = $registeredKey;
        }

        $actionKeys = [
            'view', 'create', 'edit', 'delete', 'approve', 'reject',
            'print', 'pdf', 'export', 'import', 'restore', 'manage_settings',
            'manage_visibility', 'full_access',
        ];

        if ($pdo instanceof PDO && school_table_exists($pdo, 'permission_actions')) {
            try {
                $statement = $pdo->query(
                    "SELECT action_key
                     FROM permission_actions
                     WHERE is_active = 1
                     ORDER BY display_order, id"
                );
                foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $key) {
                    $key = strtolower(trim((string)$key));
                    $key = preg_replace('/[^a-z0-9]+/', '_', $key) ?? '';
                    $key = trim($key, '_');
                    if ($key !== '') {
                        $actionKeys[] = $key;
                    }
                }
            } catch (Throwable $exception) {
                error_log('school_current_page_capabilities actions: ' . $exception->getMessage());
            }
        }
        $actionKeys = array_values(array_unique($actionKeys));

        $actions = array_fill_keys($actionKeys, false);
        if ($pageKey === '') {
            $actions['view'] = true;
        } else {
            foreach ($actionKeys as $actionKey) {
                $actions[$actionKey] = school_effective_permission($pageKey, $actionKey);
            }
        }

        $actions['create'] = (bool)($actions['create'] ?? false);
        $result = [
            'page_key' => $pageKey,
            'view' => (bool)($actions['view'] ?? false),
            'add' => $actions['create'],
            'create' => $actions['create'],
            'edit' => (bool)($actions['edit'] ?? false),
            'delete' => (bool)($actions['delete'] ?? false),
            'actions' => $actions,
        ];

        foreach ($actions as $key => $allowed) {
            $result[$key] = (bool)$allowed;
        }
        $result['add'] = $result['create'];

        /*
         * Branch module permission is an additional ceiling on top of Role
         * Permissions. OFF keeps View/Print/PDF/Export available for existing
         * current-branch records, but mutation actions become read-only.
         */
        if (
            $pdo instanceof PDO
            && function_exists('branch_module_canonical_key')
            && function_exists('branch_module_current_scope')
            && function_exists('branch_module_is_enabled')
            && function_exists('branch_module_filter_capabilities')
        ) {
            try {
                $branchModuleKey = branch_module_canonical_key($pageKey);

                if ($branchModuleKey !== '') {
                    $branchScope = branch_module_current_scope();

                    if (
                        (int)($branchScope['tenant_id'] ?? 0) > 0
                        && (int)($branchScope['branch_id'] ?? 0) > 0
                    ) {
                        $branchModuleEnabled = branch_module_is_enabled(
                            $pdo,
                            (int)$branchScope['tenant_id'],
                            (int)$branchScope['branch_id'],
                            $branchModuleKey,
                            true
                        );

                        $result = branch_module_filter_capabilities(
                            $result,
                            $branchModuleEnabled
                        );
                    }
                }
            } catch (Throwable $exception) {
                error_log(
                    'school_current_page_capabilities branch module: '
                    . $exception->getMessage()
                );
            }
        }

        return $result;
    }
}

if (!function_exists('school_effective_permission')) {
    function school_effective_permission(
        string $pageKey,
        string $action = 'view',
        int $tenantId = 0,
        int $branchId = 0,
        ?int $userId = null
    ): bool {
        global $pdo;

        /*
         * The protected SaaS Super Admin is unrestricted. Accessing
         * operational school records is separately guarded by Support Access.
         */
        if (is_super_admin()) {
            return true;
        }

        if (!($pdo instanceof PDO)) {
            return false;
        }

        $userId = $userId ?? (int)($_SESSION['user_id'] ?? 0);
        $tenantId = $tenantId > 0
            ? $tenantId
            : (int)(
                $_SESSION['school_id']
                ?? $_SESSION['tenant_id']
                ?? 0
            );
        $branchId = $branchId > 0
            ? $branchId
            : (int)(
                $_SESSION['branch_id']
                ?? $_SESSION['default_branch_id']
                ?? 0
            );

        if ($userId <= 0 || $tenantId <= 0) {
            return false;
        }

        $pageKey = school_permission_canonical_page_key($pageKey);

        /* Resolve common API verbs while preserving every atomic dynamic
         * permission section such as Print, Export, Approve or a future
         * database-defined action key. */
        $action = school_sidebar_normalize_action($action);

        $roleIds = school_effective_role_ids(
            $tenantId,
            $userId
        );

        if ($roleIds === []) {
            return false;
        }

        /*
         * Sidebar-linked School pages use the Role Permission matrix as the
         * authoritative action source. A false decision must not fall back to
         * a broader legacy or normalized grant.
         */
        $sidebarDecision = school_sidebar_permission_decision(
            $pageKey,
            $action,
            $tenantId,
            $userId
        );

        if ($sidebarDecision !== null) {
            /*
             * The School Role Permission matrix is authoritative for every
             * sidebar-linked page and API. Once the exact assigned item and
             * parent chain have been evaluated, older module/branch/legacy
             * permission tables must not reverse that decision.
             */
            return $sidebarDecision;
        }

        /*
         * Compatibility fallback while the normalized migration has not
         * yet been imported.
         */
        if (
            !school_table_exists($pdo, 'permissions')
            || !school_table_exists($pdo, 'role_permissions')
            || !school_table_exists(
                $pdo,
                'permission_actions'
            )
            || !school_column_exists(
                $pdo,
                'app_pages',
                'portal_scope'
            )
        ) {
            $legacyMap = [
                'view' => 'can_view',
                'create' => 'can_create',
                'edit' => 'can_edit',
                'delete' => 'can_delete',
                'restore' => 'can_restore',
                'approve' => 'can_approve',
                'reject' => 'can_reject',
                'print' => 'can_print',
                'pdf' => school_column_exists(
                    $pdo,
                    'role_page_permissions',
                    'can_pdf'
                ) ? 'can_pdf' : null,
                'export' => 'can_export',
                'import' => 'can_import',
                'manage_settings' => school_column_exists(
                    $pdo,
                    'role_page_permissions',
                    'can_manage'
                ) ? 'can_manage' : 'can_assign',
                'full_access' => school_column_exists(
                    $pdo,
                    'role_page_permissions',
                    'can_manage'
                ) ? 'can_manage' : 'can_assign',
            ];

            $column = $legacyMap[$action] ?? null;

            if (
                $column === null
                || !school_table_exists(
                    $pdo,
                    'role_page_permissions'
                )
                || !school_column_exists(
                    $pdo,
                    'role_page_permissions',
                    $column
                )
            ) {
                return false;
            }

            try {
                $marks = implode(
                    ',',
                    array_fill(0, count($roleIds), '?')
                );
                $statement = $pdo->prepare(
                    "SELECT COALESCE(MAX(rpp.{$column}), 0)
                     FROM role_page_permissions AS rpp
                     INNER JOIN app_pages AS p
                        ON p.id = rpp.page_id
                       AND p.page_key = ?
                       AND p.is_active = 1
                     WHERE rpp.role_id IN ({$marks})"
                );
                $statement->execute([
                    $pageKey,
                    ...$roleIds,
                ]);

                return (int)$statement->fetchColumn() === 1;
            } catch (Throwable $exception) {
                error_log(
                    'school_effective_permission legacy: '
                    . $exception->getMessage()
                );

                return false;
            }
        }

        try {
            $permissionStatement = $pdo->prepare(
                "SELECT
                    p.id,
                    a.action_key
                 FROM permissions AS p
                 INNER JOIN permission_actions AS a
                    ON a.id = p.action_id
                   AND a.action_key IN (
                        :action_key,
                        'full_access'
                   )
                 INNER JOIN app_pages AS ap
                    ON ap.id = p.page_id
                   AND ap.page_key = :page_key
                   AND ap.portal_scope = 'school'
                   AND ap.is_active = 1
                 WHERE p.portal_scope = 'school'
                   AND p.is_active = 1"
            );
            $permissionStatement->execute([
                'action_key' => $action,
                'page_key' => $pageKey,
            ]);

            $permissionRows = $permissionStatement->fetchAll(
                PDO::FETCH_ASSOC
            );

            if ($permissionRows === []) {
                return false;
            }

            $permissionIds = array_map(
                static fn(array $row): int => (int)$row['id'],
                $permissionRows
            );

            $specificIds = array_map(
                static fn(array $row): int => (int)$row['id'],
                array_filter(
                    $permissionRows,
                    static fn(array $row): bool =>
                        $row['action_key'] === $action
                )
            );

            if (
                school_table_exists($pdo, 'user_permissions')
                && $specificIds !== []
            ) {
                $marks = implode(
                    ',',
                    array_fill(0, count($specificIds), '?')
                );
                $override = $pdo->prepare(
                    "SELECT effect
                     FROM user_permissions
                     WHERE user_id = ?
                       AND permission_id IN ({$marks})
                     ORDER BY
                        CASE effect
                            WHEN 'deny' THEN 0
                            ELSE 1
                        END
                     LIMIT 1"
                );
                $override->execute([
                    $userId,
                    ...$specificIds,
                ]);

                $effect = strtolower((string)(
                    $override->fetchColumn() ?: ''
                ));

                if ($effect === 'deny') {
                    return false;
                }

                if ($effect === 'allow') {
                    return true;
                }
            }

            $permissionMarks = implode(
                ',',
                array_fill(0, count($permissionIds), '?')
            );
            $roleMarks = implode(
                ',',
                array_fill(0, count($roleIds), '?')
            );

            $hasBranchTables =
                $branchId > 0
                && school_table_exists(
                    $pdo,
                    'branch_role_settings'
                )
                && school_table_exists(
                    $pdo,
                    'branch_role_permissions'
                );

            if ($hasBranchTables) {
                $statement = $pdo->prepare(
                    "SELECT COALESCE(MAX(
                        CASE
                            WHEN COALESCE(
                                brs.permission_mode,
                                'inherit'
                            ) = 'custom'
                            THEN COALESCE(brp.is_allowed, 0)
                            ELSE COALESCE(rp.is_allowed, 0)
                        END
                     ), 0)
                     FROM roles AS r
                     LEFT JOIN branch_role_settings AS brs
                        ON brs.role_id = r.id
                       AND brs.tenant_id = ?
                       AND brs.branch_id = ?
                       AND brs.status = 'active'
                     LEFT JOIN role_permissions AS rp
                        ON rp.role_id = r.id
                       AND rp.permission_id IN ({$permissionMarks})
                     LEFT JOIN branch_role_permissions AS brp
                        ON brp.role_id = r.id
                       AND brp.tenant_id = ?
                       AND brp.branch_id = ?
                       AND brp.permission_id IN ({$permissionMarks})
                     WHERE r.id IN ({$roleMarks})
                       AND r.tenant_id = ?
                       AND r.role_scope = 'school'
                       AND r.status = 'active'
                       AND r.deleted_at IS NULL"
                );

                $statement->execute([
                    $tenantId,
                    $branchId,
                    ...$permissionIds,
                    $tenantId,
                    $branchId,
                    ...$permissionIds,
                    ...$roleIds,
                    $tenantId,
                ]);
            } else {
                $statement = $pdo->prepare(
                    "SELECT COALESCE(MAX(rp.is_allowed), 0)
                     FROM role_permissions AS rp
                     INNER JOIN roles AS r
                        ON r.id = rp.role_id
                       AND r.tenant_id = ?
                       AND r.role_scope = 'school'
                       AND r.status = 'active'
                       AND r.deleted_at IS NULL
                     WHERE rp.role_id IN ({$roleMarks})
                       AND rp.permission_id IN ({$permissionMarks})"
                );
                $statement->execute([
                    $tenantId,
                    ...$roleIds,
                    ...$permissionIds,
                ]);
            }

            if ((int)$statement->fetchColumn() !== 1) {
                return false;
            }

            $modulePermission =
                school_permission_key_for_page($pageKey);

            return $modulePermission === null
                || user_has_module_permission($modulePermission);
        } catch (Throwable $exception) {
            error_log(
                'school_effective_permission: '
                . $exception->getMessage()
            );

            return false;
        }
    }
}

if (!function_exists('school_effective_sidebar_visible')) {
    function school_effective_sidebar_visible(
        int $sidebarItemId,
        int $tenantId = 0,
        int $branchId = 0,
        ?int $userId = null
    ): bool {
        if (is_super_admin()) {
            return true;
        }

        $tenantId = $tenantId > 0
            ? $tenantId
            : (int)($_SESSION['school_id'] ?? $_SESSION['tenant_id'] ?? 0);

        return school_sidebar_action_allowed_for_item(
            $sidebarItemId,
            'view',
            $tenantId,
            $userId
        );
    }
}

if (!function_exists('school_normalize_sidebar_route')) {
    function school_normalize_sidebar_route(string $route): string
    {
        $path = (string)(
            parse_url(trim($route), PHP_URL_PATH)
            ?: trim($route)
        );

        $path = strtolower(
            trim(
                str_replace('\\', '/', $path),
                '/'
            )
        );

        $basePath = strtolower(
            trim(
                (string)(
                    parse_url(
                        defined('BASE_URL') ? BASE_URL : '/',
                        PHP_URL_PATH
                    ) ?: ''
                ),
                '/'
            )
        );

        if (
            $basePath !== ''
            && str_starts_with($path, $basePath . '/')
        ) {
            $path = substr($path, strlen($basePath) + 1);
        }

        if (str_starts_with($path, 'school/')) {
            $path = substr($path, 7);
        }

        return trim($path, '/');
    }
}

if (!function_exists('school_sidebar_route_access_allowed')) {
    function school_sidebar_route_access_allowed(
        string $relativePath,
        int $tenantId = 0,
        int $roleId = 0
    ): bool {
        global $pdo;

        if (is_super_admin()) {
            return true;
        }

        $tenantId = $tenantId > 0
            ? $tenantId
            : (int)($_SESSION['school_id'] ?? $_SESSION['tenant_id'] ?? 0);

        if ($tenantId <= 0) {
            return false;
        }

        if (!($pdo instanceof PDO)) {
            return false;
        }

        if (!school_table_exists($pdo, 'sidebar_items')
            || !school_table_exists($pdo, 'tenant_sidebar_items')) {
            return true;
        }

        $page = school_registered_page_for_file($relativePath);
        $pageKey = strtolower(trim((string)($page['page_key'] ?? '')));
        $decision = school_sidebar_permission_decision(
            $pageKey,
            'view',
            $tenantId,
            null,
            $relativePath
        );

        return $decision ?? true;
    }
}

if (!function_exists('require_school_sidebar_route_access')) {
    function require_school_sidebar_route_access(
        string $relativePath
    ): void {
        if (!school_sidebar_route_access_allowed($relativePath)) {
            http_response_code(403);

            if (
                str_contains(
                    strtolower(
                        (string)(
                            $_SERVER['HTTP_ACCEPT'] ?? ''
                        )
                    ),
                    'application/json'
                )
            ) {
                header(
                    'Content-Type: application/json; charset=utf-8'
                );

                echo json_encode([
                    'success' => false,
                    'message' =>
                        'This module is disabled for your school or role.',
                    'data' => [],
                ]);

                exit;
            }

            exit(
                'Access denied. This module is disabled '
                . 'for your school or role.'
            );
        }
    }
}

if (!function_exists('require_school_permission')) {
    function require_school_permission(
        string $pageKey,
        string $action = 'view'
    ): void {
        require_login();

        if (!school_effective_permission($pageKey, $action)) {
            http_response_code(403);
            exit('Access denied.');
        }
    }
}

if (!function_exists('has_permission')) {
    function has_permission(
        string $pageKey,
        string $action = 'view'
    ): bool {
        if (APP_DEMO_MODE) {
            return true;
        }

        $pageKey = strtolower(trim($pageKey));
        $action = strtolower(trim($action));

        if (current_user_has_platform_role()) {
            return has_platform_permission($pageKey, $action);
        }

        return school_effective_permission(
            $pageKey,
            $action
        );
    }
}

if (!function_exists('require_page_permission')) {
    function require_page_permission(string $pageKey): void
    {
        require_login();

        if (!has_permission($pageKey, 'view')) {
            http_response_code(403);
            exit('Access denied.');
        }
    }
}

/*
|--------------------------------------------------------------------------
| Theme
|--------------------------------------------------------------------------
*/

if (!function_exists('current_theme_settings')) {
    function current_theme_settings(): array
    {
        global $pdo;

        $theme = [
            'sidebar_bg' => '#ffffff',
            'sidebar_text' => '#334155',
            'sidebar_active_bg_1' => '#6d4df2',
            'sidebar_active_bg_2' => '#3559dc',
            'sidebar_active_text' => '#ffffff',
            'sidebar_hover_bg' => '#eef2ff',
            'sidebar_hover_text' => '#27305f',
            'topbar_bg_1' => '#653dd8',
            'topbar_bg_2' => '#1959c8',
            'topbar_text' => '#ffffff',
            'body_bg' => '#f6f8fc',
            'card_bg' => '#ffffff',
            'text_main' => '#101b46',
            'text_muted' => '#6c7895',
            'border_soft' => '#e5e9f2',
            'brand_1' => '#6747e8',
            'brand_2' => '#2f62d7',
            'success_color' => '#21ae71',
            'warning_color' => '#ff9f1a',
            'danger_color' => '#f54267',
            'info_color' => '#3478f6',
            'layout_density' => 'comfortable',
        ];

        if (APP_DEMO_MODE
            || !($pdo instanceof PDO)
            || !school_table_exists($pdo, 'website_color_settings')) {
            return $theme;
        }

        try {
            $themeTenantId = 0;
            foreach ([
                $_SESSION['school_id'] ?? null,
                $_SESSION['tenant_id'] ?? null,
            ] as $candidateTenantId) {
                $candidateTenantId = (int)$candidateTenantId;
                if ($candidateTenantId > 0) {
                    $themeTenantId = $candidateTenantId;
                    break;
                }
            }

            /* No school context means no school-specific theme. */
            if ($themeTenantId <= 0) {
                return $theme;
            }

            $stmt = $pdo->prepare(
                "SELECT setting_key, setting_value
                 FROM website_color_settings
                 WHERE tenant_id = :tenant_id
                   AND is_active = 1"
            );
            $stmt->execute([
                'tenant_id' => $themeTenantId,
            ]);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $key = (string)($row['setting_key'] ?? '');
                if (array_key_exists($key, $theme)) {
                    $theme[$key] = (string)($row['setting_value'] ?? $theme[$key]);
                }
            }
        } catch (Throwable $e) {
            error_log('current_theme_settings: ' . $e->getMessage());
        }

        return $theme;
    }
}


/*
|--------------------------------------------------------------------------
| Role, school context and dashboard access
|--------------------------------------------------------------------------
*/

if (!function_exists('school_normalize_role_key')) {
    function school_normalize_role_key(
        string $roleKey,
        string $roleName = ''
    ): string {
        $value = trim($roleKey) !== ''
            ? $roleKey
            : $roleName;

        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?: '';
        $value = trim($value, '_');

        $aliases = [
            'super_administrator' => 'super_admin',
            'superadmin' => 'super_admin',

            /*
             * School Administrator aliases used by different project
             * migrations and older login records.
             */
            'school_admin' => 'school_admin',
            'school_administrator' => 'school_admin',
            'schooladmin' => 'school_admin',
            'school_admin_user' => 'school_admin',
            'administrator' => 'school_admin',
            'admin' => 'school_admin',
            'branch_administrator' => 'school_admin',
            'branch_admin' => 'school_admin',

            'accounts' => 'accountant',
            'accountant_user' => 'accountant',
        ];

        return $aliases[$value] ?? $value;
    }
}

if (!function_exists('school_session_is_super_admin')) {
    function school_session_is_super_admin(): bool
    {
        return current_user_has_platform_role();
    }
}

if (!function_exists('school_role_dashboard_path')) {
    function school_role_dashboard_path(string $roleKey, string $roleName = ''): string
    {
        if (!empty($_SESSION['user_id']) && current_user_has_platform_role()) {
            return 'super-admin/dashboard.php';
        }
        $normalizedRole = school_normalize_role_key(
            $roleKey,
            $roleName
        );

        if ($normalizedRole === 'super_admin') {
            return 'super-admin/dashboard.php';
        }

        if ($normalizedRole === 'parent') {
            return 'parent/s_dashboard.php';
        }

        return 'school/dashboard.php';
    }
}

if (!function_exists('school_role_dashboard_url')) {
    function school_role_dashboard_url(
        string $roleKey,
        string $roleName = ''
    ): string {
        return app_url(
            school_role_dashboard_path(
                $roleKey,
                $roleName
            )
        );
    }
}

if (!function_exists('school_current_role_dashboard_url')) {
    function school_current_role_dashboard_url(): string
    {
        return school_role_dashboard_url(
            (string)($_SESSION['role_key'] ?? ''),
            (string)($_SESSION['role_name'] ?? '')
        );
    }
}

if (!function_exists('current_school_id')) {
    /**
     * Returns the mandatory school scope for school users.
     *
     * Super Admin is cross-school. A Super Admin must explicitly choose
     * active_school_id before opening school-scoped data.
     */
    function current_school_id(): int
    {
        if (school_session_is_super_admin()) {
            return platform_support_access_school_id();
        }

        return (int)(
            $_SESSION['school_id']
            ?? $_SESSION['tenant_id']
            ?? 0
        );
    }
}

if (!function_exists('require_current_school_id')) {
    function require_current_school_id(): int
    {
        $schoolId = current_school_id();

        if ($schoolId <= 0) {
            http_response_code(403);
            exit('A valid school context is required.');
        }

        return $schoolId;
    }
}

if (!function_exists('current_branch_id')) {
    function current_branch_id(): int
    {
        return (int)(
            $_SESSION['branch_id']
            ?? $_SESSION['default_branch_id']
            ?? 0
        );
    }
}

if (!function_exists('set_super_admin_school_context')) {
    function set_super_admin_school_context(
        int $schoolId,
        string $reason = 'Support access initiated from the platform school selector.'
    ): bool {
        return begin_platform_support_access($schoolId, $reason);
    }
}

if (!function_exists('clear_super_admin_school_context')) {
    function clear_super_admin_school_context(): void
    {
        end_platform_support_access();
    }
}

if (!function_exists('school_scope_condition')) {
    /**
     * Example:
     * $sql = "SELECT * FROM students s WHERE "
     *      . school_scope_condition('s');
     */
    function school_scope_condition(
        string $tableAlias = ''
    ): string {
        $prefix = trim($tableAlias);

        if ($prefix !== '' && !str_ends_with($prefix, '.')) {
            $prefix .= '.';
        }

        return $prefix . 'tenant_id = :school_id';
    }
}

if (!function_exists('school_scope_params')) {
    function school_scope_params(
        array $parameters = []
    ): array {
        $parameters['school_id'] =
            require_current_school_id();

        return $parameters;
    }
}

if (!function_exists('school_execute_scoped')) {
    /**
     * Executes a school-owned query safely.
     *
     * School-owned SELECT/UPDATE/DELETE statements must contain :school_id.
     * INSERT statements should explicitly insert tenant_id using :school_id.
     */
    function school_execute_scoped(
        PDO $pdo,
        string $sql,
        array $parameters = []
    ): PDOStatement {
        if (!str_contains($sql, ':school_id')) {
            throw new LogicException(
                'School-owned query must contain :school_id.'
            );
        }

        $statement = $pdo->prepare($sql);
        $statement->execute(
            school_scope_params($parameters)
        );

        return $statement;
    }
}

if (!function_exists('school_requested_id_is_allowed')) {
    function school_requested_id_is_allowed(
        int $requestedSchoolId
    ): bool {
        if (school_session_is_super_admin()) {
            return $requestedSchoolId > 0;
        }

        return $requestedSchoolId > 0
            && $requestedSchoolId === current_school_id();
    }
}

if (!function_exists('school_user_can_access_branch')) {
    function school_user_can_access_branch(
        int $branchId
    ): bool {
        global $pdo;

        if (
            $branchId <= 0
            || !($pdo instanceof PDO)
        ) {
            return false;
        }

        $schoolId = require_current_school_id();

        try {
            $branchStatement = $pdo->prepare(
                "SELECT id
                 FROM branches
                 WHERE id = :branch_id
                   AND tenant_id = :school_id
                   AND status = 'active'
                 LIMIT 1"
            );

            $branchStatement->execute([
                'branch_id' => $branchId,
                'school_id' => $schoolId,
            ]);

            if ((int)$branchStatement->fetchColumn() !== $branchId) {
                return false;
            }

            if (school_session_is_super_admin()) {
                return true;
            }

            if (!school_table_exists(
                $pdo,
                'user_branch_access'
            )) {
                return $branchId === current_branch_id();
            }

            $accessStatement = $pdo->prepare(
                "SELECT can_access
                 FROM user_branch_access
                 WHERE user_id = :user_id
                   AND branch_id = :branch_id
                 LIMIT 1"
            );

            $accessStatement->execute([
                'user_id' => (int)(
                    $_SESSION['user_id']
                    ?? 0
                ),
                'branch_id' => $branchId,
            ]);

            $access = $accessStatement->fetchColumn();

            if ($access === false) {
                return $branchId === current_branch_id();
            }

            return (int)$access === 1;
        } catch (Throwable $exception) {
            error_log(
                'school_user_can_access_branch: '
                . $exception->getMessage()
            );

            return false;
        }
    }
}

if (!function_exists('school_destroy_login_session')) {
    function school_destroy_login_session(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $parameters = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                [
                    'expires' => time() - 42000,
                    'path' => $parameters['path'],
                    'domain' => $parameters['domain'],
                    'secure' => (bool)$parameters['secure'],
                    'httponly' => (bool)$parameters['httponly'],
                    'samesite' => $parameters['samesite']
                        ?? 'Lax',
                ]
            );
        }

        session_destroy();
    }
}

if (!function_exists('school_validate_authenticated_session')) {
    /**
     * Revalidates account, role, school and branch state on protected pages.
     */
    function school_validate_authenticated_session(): void
    {
        global $pdo;

        if (
            !is_logged_in()
            || !($pdo instanceof PDO)
        ) {
            return;
        }

        try {
            $statement = $pdo->prepare(
                "SELECT
                    u.id,
                    u.tenant_id,
                    u.default_branch_id,
                    u.role_id,
                    u.name,
                    u.email,
                    u.mobile,
                    u.username,
                    u.profile_photo,
                    u.status AS user_status,
                    r.role_key,
                    r.role_name,
                    r.status AS role_status,
                    t.status AS school_status,
                    b.branch_code,
                    b.branch_name,
                    b.status AS branch_status
                 FROM users AS u
                 INNER JOIN roles AS r
                    ON r.id = u.role_id
                 INNER JOIN tenants AS t
                    ON t.id = u.tenant_id
                 LEFT JOIN branches AS b
                    ON b.id = u.default_branch_id
                 WHERE u.id = :user_id
                 LIMIT 1"
            );

            $statement->execute([
                'user_id' => (int)$_SESSION['user_id'],
            ]);

            $user = $statement->fetch(PDO::FETCH_ASSOC);

            if (
                !$user
                || strtolower((string)$user['user_status'])
                    !== 'active'
                || strtolower((string)$user['role_status'])
                    !== 'active'
                || !in_array(
                    strtolower((string)$user['school_status']),
                    ['trial', 'active'],
                    true
                )
            ) {
                school_destroy_login_session();
                header('Location: ' . app_url('login.php'));
                exit;
            }

            $roleKey = school_normalize_role_key(
                (string)$user['role_key'],
                (string)$user['role_name']
            );

            $isSuperAdmin = $roleKey === 'super_admin';

            if (
                !$isSuperAdmin
                && (int)($_SESSION['school_id'] ?? 0)
                    !== (int)$user['tenant_id']
            ) {
                school_destroy_login_session();
                header('Location: ' . app_url('login.php'));
                exit;
            }

            if (
                !$isSuperAdmin
                && $user['default_branch_id'] !== null
                && strtolower((string)$user['branch_status'])
                    !== 'active'
            ) {
                school_destroy_login_session();
                header('Location: ' . app_url('login.php'));
                exit;
            }

            $_SESSION['tenant_id'] = (int)$user['tenant_id'];
            $_SESSION['school_id'] = (int)$user['tenant_id'];
            $_SESSION['branch_id'] =
                $user['default_branch_id'] !== null
                    ? (int)$user['default_branch_id']
                    : 0;
            $_SESSION['default_branch_id'] =
                $_SESSION['branch_id'];
            $_SESSION['role_id'] = (int)$user['role_id'];
            $_SESSION['role_key'] = $roleKey;
            $_SESSION['role_name'] =
                (string)$user['role_name'];
            $_SESSION['name'] = (string)$user['name'];
            $_SESSION['username'] =
                (string)$user['username'];
            $_SESSION['email'] =
                (string)($user['email'] ?? '');
            $_SESSION['mobile'] =
                (string)($user['mobile'] ?? '');
            $_SESSION['profile_photo'] =
                (string)($user['profile_photo'] ?? '');
            $_SESSION['branch_code'] =
                (string)($user['branch_code'] ?? '');
            $_SESSION['branch_name'] =
                (string)($user['branch_name'] ?? '');
        } catch (Throwable $exception) {
            error_log(
                'school_validate_authenticated_session: '
                . $exception->getMessage()
            );

            school_destroy_login_session();
            header('Location: ' . app_url('login.php'));
            exit;
        }
    }
}


if (!function_exists('school_forbid_permission_request')) {
    function school_forbid_permission_request(
        string $message,
        bool $json = false
    ): never {
        http_response_code(403);

        if ($json) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(
                [
                    'success' => false,
                    'message' => $message,
                    'data' => [],
                ],
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            );
        } else {
            echo $message;
        }

        exit;
    }
}


if (!function_exists('school_permission_canonical_page_key')) {
    /**
     * Convert legacy page/API keys to the exact School sidebar menu key.
     *
     * The sidebar menu key is the authorization boundary. Old pages may still
     * use a historical module key or a different API filename; resolving those
     * aliases here keeps page access, API access and button capabilities on the
     * same View/Add/Edit/Delete record.
     */
    function school_permission_canonical_page_key(
        string $pageKey,
        string $relativePath = ''
    ): string {
        $key = strtolower(trim($pageKey));
        $key = trim((string)preg_replace('/[^a-z0-9]+/', '_', $key), '_');

        $fileKey = strtolower(pathinfo(basename($relativePath), PATHINFO_FILENAME));
        $fileKey = trim((string)preg_replace('/[^a-z0-9]+/', '_', $fileKey), '_');

        $aliases = [
            /* Staff Leave: live sidebar key is leave_management. */
            'staff_leave' => 'leave_management',
            'staff_leave_management' => 'leave_management',
            'leave_requests' => 'leave_management',

            /* Vehicle module compatibility used by the working Transport page. */
            'transport_management' => 'transport_vehicles',
            'vehicle_management' => 'transport_vehicles',
            'vehicle_master' => 'transport_vehicles',
            'vehicles' => 'transport_vehicles',
        ];

        if ($key !== '' && isset($aliases[$key])) {
            return $aliases[$key];
        }

        if ($key !== '') {
            return $key;
        }

        return $aliases[$fileKey] ?? $fileKey;
    }
}

if (!function_exists('school_registered_page_for_file')) {
    /**
     * Resolve the current script/API to one exact School permission page.
     *
     * Resolution order:
     * 1. Declared page key / SCHOOL_API_PAGE_KEY.
     * 2. Sidebar item assigned to the active tenant.
     * 3. app_page_api_routes mapping.
     * 4. Exact registered page route.
     *
     * An assigned sidebar item is accepted even when app_pages has not yet been
     * synchronized. This prevents a valid Super Admin assignment from being
     * rejected because of a stale/duplicate app_pages row.
     *
     * @return array<string,mixed>
     */
    function school_registered_page_for_file(
        string $relativePath
    ): array {
        global $pdo, $pageKey;

        if (!($pdo instanceof PDO)) {
            return [];
        }

        $normalizedPath = trim(str_replace('\\', '/', strtolower($relativePath)), '/');
        $fileName = strtolower(basename($normalizedPath));
        if ($fileName === '') {
            return [];
        }

        $isApi = str_contains('/' . $normalizedPath . '/', '/api/');

        $explicitKey = '';
        if (defined('SCHOOL_API_PAGE_KEY')) {
            $explicitKey = (string)constant('SCHOOL_API_PAGE_KEY');
        } elseif (isset($GLOBALS['SCHOOL_API_PAGE_KEY'])) {
            $explicitKey = (string)$GLOBALS['SCHOOL_API_PAGE_KEY'];
        } elseif (!$isApi) {
            $explicitKey = (string)($pageKey ?? '');
        }

        $explicitKey = school_permission_canonical_page_key(
            $explicitKey,
            $relativePath
        );

        $tenantId = (int)(
            $_SESSION['school_id']
            ?? $_SESSION['tenant_id']
            ?? 0
        );

        try {
            /*
             * Prefer the tenant-assigned sidebar catalogue. It is the same
             * source used by the visible sidebar and Role Permissions screen.
             */
            if ($tenantId > 0
                && school_table_exists($pdo, 'sidebar_items')
                && school_table_exists($pdo, 'tenant_sidebar_items')) {
                $assigned = school_sidebar_find_assigned_item(
                    $tenantId,
                    $explicitKey,
                    $relativePath
                );

                if ($assigned !== null) {
                    $assignedKey = school_permission_canonical_page_key(
                        (string)($assigned['menu_key'] ?? ''),
                        (string)($assigned['effective_route'] ?? '')
                    );

                    if ($assignedKey !== ''
                        && school_table_exists($pdo, 'app_pages')) {
                        $portalFilter = school_column_exists(
                            $pdo,
                            'app_pages',
                            'portal_scope'
                        ) ? "AND ap.portal_scope = 'school'" : '';

                        $statement = $pdo->prepare(
                            "SELECT ap.id,ap.page_key,ap.page_name,ap.route
                             FROM app_pages AS ap
                             WHERE LOWER(ap.page_key)=:page_key
                               AND ap.is_active=1
                               {$portalFilter}
                             ORDER BY ap.show_in_sidebar DESC,ap.id
                             LIMIT 1"
                        );
                        $statement->execute(['page_key' => $assignedKey]);
                        $registered = $statement->fetch(PDO::FETCH_ASSOC);

                        if (is_array($registered)) {
                            $registered['page_key'] = $assignedKey;
                            return $registered;
                        }
                    }

                    return [
                        'id' => 0,
                        'page_key' => $assignedKey,
                        'page_name' => (string)(
                            $assigned['menu_title']
                            ?? $assigned['menu_key']
                            ?? $assignedKey
                        ),
                        'route' => (string)(
                            $assigned['effective_route']
                            ?? $relativePath
                        ),
                        'sidebar_item_id' => (int)($assigned['id'] ?? 0),
                    ];
                }
            }

            if (!school_table_exists($pdo, 'app_pages')) {
                return [];
            }

            $portalFilter = school_column_exists($pdo, 'app_pages', 'portal_scope')
                ? "AND ap.portal_scope = 'school'"
                : '';

            if ($explicitKey !== '') {
                $statement = $pdo->prepare(
                    "SELECT ap.id,ap.page_key,ap.page_name,ap.route
                     FROM app_pages AS ap
                     WHERE LOWER(ap.page_key)=:page_key
                       AND ap.is_active=1
                       {$portalFilter}
                     ORDER BY ap.show_in_sidebar DESC,ap.id
                     LIMIT 1"
                );
                $statement->execute(['page_key' => $explicitKey]);
                $page = $statement->fetch(PDO::FETCH_ASSOC);

                if (is_array($page)) {
                    $page['page_key'] = $explicitKey;
                    return $page;
                }
            }

            if ($isApi
                && school_table_exists($pdo, 'app_page_api_routes')
                && school_column_exists($pdo, 'app_page_api_routes', 'page_id')
                && school_column_exists($pdo, 'app_page_api_routes', 'api_route')) {
                $activeFilter = school_column_exists(
                    $pdo,
                    'app_page_api_routes',
                    'is_active'
                ) ? 'AND ar.is_active=1' : '';

                $statement = $pdo->prepare(
                    "SELECT ap.id,ap.page_key,ap.page_name,ap.route
                     FROM app_page_api_routes AS ar
                     INNER JOIN app_pages AS ap
                        ON ap.id=ar.page_id
                       AND ap.is_active=1
                     WHERE LOWER(SUBSTRING_INDEX(ar.api_route,'/',-1))=:api_file
                       {$activeFilter}
                       {$portalFilter}
                     ORDER BY ap.show_in_sidebar DESC,ap.id
                     LIMIT 1"
                );
                $statement->execute(['api_file' => $fileName]);
                $page = $statement->fetch(PDO::FETCH_ASSOC);

                if (is_array($page)) {
                    $page['page_key'] = school_permission_canonical_page_key(
                        (string)($page['page_key'] ?? ''),
                        (string)($page['route'] ?? '')
                    );
                    return $page;
                }
            }

            $statement = $pdo->prepare(
                "SELECT ap.id,ap.page_key,ap.page_name,ap.route
                 FROM app_pages AS ap
                 WHERE ap.is_active=1
                   {$portalFilter}
                   AND LOWER(SUBSTRING_INDEX(ap.route,'/',-1))=:file_name
                 ORDER BY ap.show_in_sidebar DESC,ap.id
                 LIMIT 1"
            );
            $statement->execute(['file_name' => $fileName]);
            $page = $statement->fetch(PDO::FETCH_ASSOC);

            if (is_array($page)) {
                $page['page_key'] = school_permission_canonical_page_key(
                    (string)($page['page_key'] ?? ''),
                    (string)($page['route'] ?? '')
                );
                return $page;
            }

            return [];
        } catch (Throwable $exception) {
            error_log(
                'school_registered_page_for_file: '
                . $exception->getMessage()
            );
            return [];
        }
    }
}

if (!function_exists('school_action_from_request')) {
    function school_action_from_request(): string
    {
        $payload = school_request_payload();
        $requested = strtolower(trim((string)(
            $payload['action']
            ?? $payload['operation']
            ?? $payload['mode']
            ?? $payload['task']
            ?? $payload['command']
            ?? $payload['request_action']
            ?? $_GET['action']
            ?? $_GET['operation']
            ?? $_GET['mode']
            ?? $_GET['task']
            ?? $_POST['action']
            ?? $_POST['operation']
            ?? $_POST['mode']
            ?? $_POST['task']
            ?? ''
        )));

        $recordId = 0;
        foreach (['id', 'record_id', 'role_id', 'student_id', 'staff_id', 'route_id',
                  'vehicle_id', 'expense_id', 'salary_id', 'payment_id'] as $idKey) {
            $recordId = max($recordId, (int)($payload[$idKey] ?? $_GET[$idKey] ?? 0));
        }

        if ($requested === 'save' || str_starts_with($requested, 'save_')) {
            return $recordId > 0 || str_contains($requested, 'permission')
                ? 'edit'
                : 'create';
        }

        $normalized = school_sidebar_normalize_action($requested);
        if ($requested !== '' && $normalized !== 'view') {
            return $normalized === 'add' ? 'create' : $normalized;
        }

        $queryParts = [];
        foreach ($_GET as $queryKey => $queryValue) {
            $queryParts[] = (string)$queryKey;
            if (is_scalar($queryValue)) {
                $queryParts[] = (string)$queryValue;
            }
        }
        $querySignature = strtolower(implode(' ', $queryParts));

        if (preg_match('/(?:^|[_\s-])pdf(?:$|[_\s-])/', $querySignature)) {
            return 'pdf';
        }
        if (preg_match('/(?:^|[_\s-])print(?:$|[_\s-])/', $querySignature)) {
            return 'print';
        }
        if (preg_match('/(?:^|[_\s-])(import|upload)(?:$|[_\s-])/', $querySignature)
            || $_FILES !== []) {
            return 'import';
        }
        if (preg_match('/(?:^|[_\s-])(export|download|excel|csv)(?:$|[_\s-])/', $querySignature)) {
            return 'export';
        }

        $payloadKeys = strtolower(implode(' ', array_map('strval', array_keys($payload))));
        if (preg_match('/(?:^|[_\s-])(delete|remove|destroy|trash)(?:$|[_\s-])/', $payloadKeys)) {
            return 'delete';
        }
        if (preg_match('/(?:^|[_\s-])(edit|update|modify|status|approve|reject|assign|pay|collect)(?:$|[_\s-])/', $payloadKeys)) {
            return 'edit';
        }
        if (preg_match('/(?:^|[_\s-])(import|upload)(?:$|[_\s-])/', $payloadKeys)) {
            return 'import';
        }
        if (preg_match('/(?:^|[_\s-])(add|create|insert|copy)(?:$|[_\s-])/', $payloadKeys)) {
            return 'create';
        }

        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method === 'DELETE') {
            return 'delete';
        }
        if (in_array($method, ['PUT', 'PATCH'], true)) {
            return 'edit';
        }
        if ($method === 'POST') {
            /*
             * A non-empty but unknown POST action is still a mutation. Never
             * allow it to fall back to View permission.
             */
            return $recordId > 0 ? 'edit' : 'create';
        }

        return 'view';
    }
}


if (!function_exists('school_enforce_branch_module_action')) {
    function school_enforce_branch_module_action(
        string $pageKey,
        string $action,
        bool $isApi = false
    ): void {
        global $pdo;

        if (
            !($pdo instanceof PDO)
            || !function_exists('branch_module_canonical_key')
            || !function_exists('branch_module_current_scope')
            || !function_exists('branch_module_is_enabled')
            || !function_exists('branch_module_is_write_action')
        ) {
            return;
        }

        $moduleKey = branch_module_canonical_key($pageKey);

        if ($moduleKey === '') {
            return;
        }

        $scope = branch_module_current_scope();

        $tenantId = (int)($scope['tenant_id'] ?? 0);
        $branchId = (int)($scope['branch_id'] ?? 0);

        /*
         * A branch-controlled School module must always have a concrete branch
         * context. This prevents a missing branch from silently turning a
         * tenant-wide query into an "all branches" query.
         */
        if ($tenantId > 0 && $branchId <= 0) {
            school_forbid_permission_request(
                'Select an active Branch before opening this module.',
                $isApi
            );
        }

        if (
            $tenantId <= 0
            || $branchId <= 0
            || !branch_module_is_write_action($action)
        ) {
            return;
        }

        if (
            branch_module_is_enabled(
                $pdo,
                $tenantId,
                $branchId,
                $moduleKey,
                true
            )
        ) {
            return;
        }

        $catalog = function_exists('branch_module_catalog')
            ? branch_module_catalog()
            : [];

        $label = (string)(
            $catalog[$moduleKey]['label']
            ?? $moduleKey
        );

        school_forbid_permission_request(
            $label
            . ' is OFF for the active Branch. '
            . 'Existing current-branch data is read-only.',
            $isApi
        );
    }
}

if (!function_exists('enforce_current_school_page_permission')) {
    function enforce_current_school_page_permission(
        string $relativePath
    ): void {
        $page = school_registered_page_for_file($relativePath);

        /*
         * Existing unregistered read-only project pages are preserved. Once a
         * page is registered, both page View and same-page write submissions
         * are protected by the exact Role Permission matrix action.
         */
        if ($page === []) {
            return;
        }

        $pageKey = (string)$page['page_key'];

        if (!school_effective_permission($pageKey, 'view')) {
            school_forbid_permission_request(
                'You do not have permission to view this page.'
            );
        }

        if (function_exists('school_enforce_branch_module_action')) {
            school_enforce_branch_module_action(
                $pageKey,
                'view',
                false
            );
        }

        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $action = school_action_from_request();

        $requiresActionPermission = !in_array(
            $method,
            ['GET', 'HEAD', 'OPTIONS'],
            true
        ) || in_array(
            $action,
            ['print', 'pdf', 'export', 'import'],
            true
        );

        if ($requiresActionPermission
            && $action !== 'view'
            && !school_effective_permission($pageKey, $action)) {
            school_forbid_permission_request(
                'You do not have permission to perform this action.',
                school_is_api_request()
            );
        }

        if (function_exists('school_enforce_branch_module_action')) {
            school_enforce_branch_module_action(
                $pageKey,
                $action,
                school_is_api_request()
            );
        }
    }
}

if (!function_exists('enforce_current_school_api_permission')) {
    function enforce_current_school_api_permission(
        string $relativePath
    ): void {
        $apiFile = strtolower(basename($relativePath));

        if (in_array(
            $apiFile,
            [
                'auth.php',
                'login.php',
                'forgot-password.php',
                'reset-password.php',
            ],
            true
        )) {
            return;
        }

        $action = school_action_from_request();
        $page = school_registered_page_for_file($relativePath);

        if ($page === []) {
            /*
             * Unregistered read-only endpoints remain compatible. Any write
             * endpoint must be linked through app_page_api_routes or declare
             * SCHOOL_API_PAGE_KEY before bootstrap.php is loaded.
             */
            if ($action !== 'view') {
                school_forbid_permission_request(
                    'This API is not linked to a permitted School sidebar page.',
                    true
                );
            }
            return;
        }

        if (!school_effective_permission(
            (string)$page['page_key'],
            $action
        )) {
            school_forbid_permission_request(
                'You do not have permission to perform this action.',
                true
            );
        }

        if (function_exists('school_enforce_branch_module_action')) {
            school_enforce_branch_module_action(
                (string)$page['page_key'],
                $action,
                true
            );
        }
    }
}

if (!function_exists('school_enforce_role_panel_access')) {
    function school_enforce_role_panel_access(): void
    {
        global $pdo;

        if (!is_logged_in()) {
            return;
        }

        $scriptPath = str_replace(
            '\\',
            '/',
            (string)(
                parse_url(
                    $_SERVER['SCRIPT_NAME'] ?? '',
                    PHP_URL_PATH
                ) ?: ''
            )
        );

        $basePath = str_replace(
            '\\',
            '/',
            (string)(
                parse_url(
                    defined('BASE_URL') ? BASE_URL : '/',
                    PHP_URL_PATH
                ) ?: '/'
            )
        );

        $basePath = '/' . trim($basePath, '/') . '/';
        $normalizedScript = '/' . ltrim($scriptPath, '/');

        if (
            $basePath !== '//'
            && str_starts_with(
                $normalizedScript,
                $basePath
            )
        ) {
            $relativePath = substr(
                $normalizedScript,
                strlen($basePath)
            );
        } else {
            $relativePath = ltrim($scriptPath, '/');
        }

        $relativePath = ltrim((string)$relativePath, '/');
        $segments = array_values(array_filter(
            explode('/', $relativePath),
            static fn(string $segment): bool =>
                $segment !== ''
        ));

        $currentPanel = strtolower(
            (string)($segments[0] ?? '')
        );

        $isSuperAdmin = school_session_is_super_admin();

        if ($currentPanel === 'super-admin' && $isSuperAdmin) {
            enforce_current_platform_page_permission($relativePath);
        }

        if ($currentPanel === 'api' && $isSuperAdmin) {
            enforce_platform_api_access($relativePath);
        }

        if ($currentPanel === 'super-admin' && !$isSuperAdmin) {
            http_response_code(403);
            header('Location: ' . app_url('school/dashboard.php'));
            exit;
        }

        if ($currentPanel === 'parent' && !$isSuperAdmin) {
            $current = function_exists('current_user')
                ? current_user()
                : [];
            $loggedRole = school_normalize_role_key(
                (string)($current['role_key'] ?? $_SESSION['role_key'] ?? ''),
                (string)($current['role_name'] ?? $_SESSION['role_name'] ?? '')
            );

            if ($loggedRole !== 'parent') {
                http_response_code(403);
                header(
                    'Location: '
                    . school_current_role_dashboard_url()
                );
                exit;
            }

            $parentTenantId = (int)(
                $current['tenant_id']
                ?? $_SESSION['school_id']
                ?? $_SESSION['tenant_id']
                ?? 0
            );
            $parentRoleId = (int)(
                $current['role_id']
                ?? $_SESSION['role_id']
                ?? 0
            );

            /*
             * Parent pages are a single portal with several sidebar anchors.
             * Permit the portal when this school has at least one enabled Parent
             * master item with View access. Individual sidebar visibility is
             * still filtered item-by-item by school_sidebar_get_items().
             */
            $parentPortalAllowed = false;
            if ($parentTenantId > 0
                && $parentRoleId > 0
                && $pdo instanceof PDO
                && school_table_exists($pdo, 'sidebar_role_master_items')) {
                try {
                    $stmt = $pdo->query(
                        "SELECT sidebar_item_id
                         FROM sidebar_role_master_items
                         WHERE role_key='parent'
                           AND is_enabled=1
                         ORDER BY COALESCE(display_order,9999),id"
                    );
                    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $parentItemId) {
                        if (pc_effective_role_action(
                            $pdo,
                            $parentTenantId,
                            $parentRoleId,
                            (int)$parentItemId,
                            'view'
                        )) {
                            $parentPortalAllowed = true;
                            break;
                        }
                    }
                } catch (Throwable $exception) {
                    error_log(
                        'Parent portal permission-chain access: '
                        . $exception->getMessage()
                    );
                }
            }

            if (!$parentPortalAllowed) {
                http_response_code(403);
                exit(
                    'Access denied. Parent Portal is disabled '
                    . 'for this school.'
                );
            }
        }

        if ($currentPanel === 'school' && !$isSuperAdmin) {
            /*
             * Sidebar assignment is a backend authorization boundary.
             * Hiding a menu therefore also blocks its direct URL.
             */
            require_school_sidebar_route_access($relativePath);
            enforce_current_school_page_permission($relativePath);
        }

        if ($currentPanel === 'api' && !$isSuperAdmin) {
            enforce_current_school_api_permission($relativePath);
        }

        if ($currentPanel === 'school' && $isSuperAdmin) {
            $supportSchoolId = platform_support_access_school_id();

            if ($supportSchoolId <= 0) {
                header('Location: ' . app_url('super-admin/dashboard.php'));
                exit;
            }

            require_platform_support_access($supportSchoolId);
            return;
        }

        /*
         * Legacy role-specific dashboards are redirected to the shared
         * School dashboard. Module access is controlled by permissions.
         */
        $legacyRolePanels = [
            'teacher',
            'student',
            'accountant',
            'principal',
            'staff',
            'hr',
            'librarian',
            'transport',
            'reception',
            'admissions',
            'exam',
        ];

        if (in_array(
            $currentPanel,
            $legacyRolePanels,
            true
        )) {
            header(
                'Location: '
                . school_current_role_dashboard_url()
            );
            exit;
        }

        if (
            $relativePath === 'dashboard.php'
            || $relativePath === ''
        ) {
            header(
                'Location: '
                . school_current_role_dashboard_url()
            );
            exit;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Shared page-action UI guard
|--------------------------------------------------------------------------
|
| Server-side API checks remain the security boundary. This shared HTML guard
| keeps the interface consistent by hiding View/Add/Edit/Delete controls that
| the logged-in role is not allowed to use. Existing pages do not need to be
| rewritten; explicit data-permission-action attributes are also supported.
|
*/

if (!function_exists('school_can_view')) {
    function school_can_view(string $pageKey = ''): bool
    {
        return (bool)school_current_page_capabilities($pageKey)['view'];
    }
}

if (!function_exists('school_can_add')) {
    function school_can_add(string $pageKey = ''): bool
    {
        return (bool)school_current_page_capabilities($pageKey)['add'];
    }
}

if (!function_exists('school_can_edit')) {
    function school_can_edit(string $pageKey = ''): bool
    {
        return (bool)school_current_page_capabilities($pageKey)['edit'];
    }
}

if (!function_exists('school_can_delete')) {
    function school_can_delete(string $pageKey = ''): bool
    {
        return (bool)school_current_page_capabilities($pageKey)['delete'];
    }
}

if (!function_exists('school_can_print')) {
    function school_can_print(string $pageKey = ''): bool
    {
        return (bool)(school_current_page_capabilities($pageKey)['print'] ?? false);
    }
}

if (!function_exists('school_can_pdf')) {
    function school_can_pdf(string $pageKey = ''): bool
    {
        return (bool)(school_current_page_capabilities($pageKey)['pdf'] ?? false);
    }
}

if (!function_exists('school_can_export')) {
    function school_can_export(string $pageKey = ''): bool
    {
        return (bool)(school_current_page_capabilities($pageKey)['export'] ?? false);
    }
}

if (!function_exists('school_can_import')) {
    function school_can_import(string $pageKey = ''): bool
    {
        return (bool)(school_current_page_capabilities($pageKey)['import'] ?? false);
    }
}

if (!function_exists('school_permission_ui_markup')) {
    function school_permission_ui_markup(array $capabilities): string
    {
        $actions = is_array($capabilities['actions'] ?? null)
            ? $capabilities['actions']
            : [];
        foreach ($capabilities as $key => $value) {
            if (is_bool($value) && $key !== 'page_key') {
                $actions[(string)$key] = $value;
            }
        }
        $actions['create'] = (bool)($actions['create'] ?? $actions['add'] ?? false);
        $actions['add'] = $actions['create'];

        $payload = [
            'page_key' => (string)($capabilities['page_key'] ?? ''),
            'view' => (bool)($actions['view'] ?? false),
            'add' => $actions['create'],
            'create' => $actions['create'],
            'edit' => (bool)($actions['edit'] ?? false),
            'delete' => (bool)($actions['delete'] ?? false),
            'actions' => $actions,
        ];
        foreach ($actions as $key => $value) {
            $payload[$key] = (bool)$value;
        }

        $json = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
        );

        if (!is_string($json)) {
            $json = '{"page_key":"","view":false,"add":false,"create":false,"edit":false,"delete":false,"actions":{}}';
        }

        $template = <<<'HTML'
<style id="schoolPermissionUiStyle">
[data-school-permission-hidden="1"]{display:none!important}
</style>
<script>
(function(){
    'use strict';

    const permissions = Object.freeze(__SCHOOL_PERMISSION_JSON__);
    const actionPermissions = Object.freeze(permissions.actions || {});
    window.SCHOOL_PAGE_PERMISSIONS = permissions;

    const slug = value => String(value || '')
        .trim().toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '');

    const normalizeAction = value => {
        const action = slug(value);
        if (!action) return '';
        const direct = {
            read:'view',list:'view',detail:'view',details:'view',meta:'view',search:'view',load:'view',fetch:'view',show:'view',preview:'view',open:'view',
            add:'create',store:'create',insert:'create',new:'create',copy:'create',clone:'create',duplicate:'create',register:'create',
            update:'edit',modify:'edit',save:'edit',toggle:'edit',status:'edit',activate:'edit',deactivate:'edit',assign:'edit',lock:'edit',publish:'edit',pay:'edit',collect:'edit',receive:'edit',process:'edit',complete:'edit',cancel:'edit',archive:'edit',
            remove:'delete',destroy:'delete',trash:'delete',pdf:'pdf',download:'export',upload:'import',manage:'manage_settings',settings:'manage_settings',visibility:'manage_visibility',full:'full_access'
        };
        if (direct[action]) return direct[action];
        if (Object.prototype.hasOwnProperty.call(actionPermissions, action)
            || Object.prototype.hasOwnProperty.call(permissions, action)) return action;
        const tests = [
            ['delete',/(delete|remove|destroy|trash)/],
            ['restore',/(restore|recover)/],
            ['approve',/approve/],
            ['reject',/reject/],
            ['print',/print/],
            ['pdf',/pdf/],
            ['export',/(export|download)/],
            ['import',/(import|upload)/],
            ['create',/(add|create|insert|new|copy|clone|duplicate|register)/],
            ['manage_visibility',/(manage_visibility|visibility)/],
            ['manage_settings',/(manage_settings|setting|manage)/],
            ['edit',/(edit|update|modify|toggle|status|activate|deactivate|assign|lock|publish|pay|collect|receive|process|complete|cancel|archive|save)/],
            ['view',/(view|detail|show|preview|open|list|search|load|fetch)/]
        ];
        return tests.find(([,re]) => re.test(action))?.[0] || action;
    };

    const allowed = action => {
        action = normalizeAction(action);
        if (!action) return true;
        if (action === 'add') action = 'create';
        if (Object.prototype.hasOwnProperty.call(actionPermissions, action)) {
            return actionPermissions[action] === true;
        }
        return permissions[action] === true;
    };

    window.schoolHasPagePermission = allowed;
    window.schoolCanView = () => allowed('view');
    window.schoolCanAdd = () => allowed('create');
    window.schoolCanEdit = () => allowed('edit');
    window.schoolCanDelete = () => allowed('delete');
    window.schoolCanPrint = () => allowed('print');
    window.schoolCanPdf = () => allowed('pdf');
    window.schoolCanExport = () => allowed('export');
    window.schoolCanImport = () => allowed('import');
    window.schoolCanApprove = () => allowed('approve');
    window.schoolCanReject = () => allowed('reject');
    window.schoolCanRestore = () => allowed('restore');
    window.schoolCanManage = () => allowed('manage_settings');

    const clickableSelector = [
        'button','a[href]','a[role="button"]','[role="button"]',
        'input[type="button"]','input[type="submit"]'
    ].join(',');

    const splitWords = value => String(value || '')
        .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
        .replace(/[_\-:/?#=&.]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim().toLowerCase();

    const formAction = form => {
        if (!form) return '';

        const mode = splitWords([
            form.id,
            form.className,
            form.getAttribute('name'),
            form.getAttribute('action')
        ].join(' '));

        if (/\b(import|upload)\b/.test(mode)) return 'import';

        const explicit = form.dataset.permissionAction
            || form.dataset.requiredPermission
            || form.dataset.action || '';
        const explicitAction = normalizeAction(explicit);
        if (explicitAction) return explicitAction;

        if (/\b(delete|remove|destroy|trash)\b/.test(mode)) return 'delete';
        if (/\b(edit|update|modify)\b/.test(mode)) return 'edit';
        if (/\b(add|create|new|register|insert)\b/.test(mode)) return 'create';

        for (const input of form.querySelectorAll('input[type="hidden"][name],input[type="hidden"][id]')) {
            const key = splitWords(`${input.name || ''} ${input.id || ''}`);
            if (/\b(id|record id|student id|staff id|role id|route id|vehicle id|expense id|salary id|payment id)\b/.test(key)
                && Number(input.value || 0) > 0) return 'edit';
        }
        return 'create';
    };

    const elementAction = element => {
        if (!(element instanceof Element)) return '';
        if (element.closest('#sidebar,.sidebar-nav,.pagination,.rp-page-buttons,.dataTables_paginate,[data-bs-dismiss],.btn-close,[aria-label="Close"],[aria-label="close"]')) return '';

        const icon = element.querySelector('[data-lucide]')?.getAttribute('data-lucide') || '';
        const signature = splitWords([
            element.id,element.className,element.getAttribute('name'),element.getAttribute('title'),
            element.getAttribute('aria-label'),element.getAttribute('data-action'),
            element.getAttribute('href'),element.value,element.textContent,icon
        ].join(' '));

        if (!signature || /\b(cancel|close|back|reset|refresh|search|filter|clear|next|previous)\b/.test(signature)) return '';

        /*
         * Always recognize these toolbar actions before stale explicit View
         * attributes that exist in older School pages.
         */
        if (/\b(pdf|file pdf)\b/.test(signature)) return 'pdf';
        if (/\b(print|printer)\b/.test(signature)) return 'print';
        if (/\b(import|upload|file up)\b/.test(signature)) return 'import';
        if (/\b(export|download|file down|spreadsheet|excel|csv)\b/.test(signature)) return 'export';

        const explicit = element.dataset.permissionAction
            || element.dataset.requiredPermission
            || element.dataset.permission || '';
        const explicitAction = normalizeAction(explicit);
        if (explicitAction) return explicitAction;

        if (/\b(delete|remove|destroy|trash|trash 2)\b/.test(signature)) return 'delete';
        if (/\b(restore|recover)\b/.test(signature)) return 'restore';
        if (/\b(approve|approval)\b/.test(signature)) return 'approve';
        if (/\b(reject|rejection)\b/.test(signature)) return 'reject';
        if (/\b(manage visibility|visibility)\b/.test(signature)) return 'manage_visibility';
        if (/\b(manage settings|settings)\b/.test(signature)) return 'manage_settings';
        if (/\b(edit|update|modify|change status|activate|deactivate|assign|pay|collect|receive|process|pencil|square pen)\b/.test(signature)) return 'edit';
        if (/\b(add|create|new|register|insert|copy|clone|duplicate|plus|circle plus|user plus)\b/.test(signature)) return 'create';
        if (/\b(view|details|detail|show|preview|open|eye)\b/.test(signature)) return 'view';

        const type = String(element.getAttribute('type') || '').toLowerCase();
        if ((type === 'submit' || /\b(save|submit)\b/.test(signature)) && element.closest('form')) {
            return formAction(element.closest('form'));
        }
        return '';
    };

    const setVisible = (element, visible) => {
        if (!(element instanceof HTMLElement)) return;
        if (!visible) {
            if (!Object.prototype.hasOwnProperty.call(element.dataset, 'schoolOriginalDisplay')) {
                element.dataset.schoolOriginalDisplay = element.style.display || '';
            }
            element.dataset.schoolPermissionHidden = '1';
            element.setAttribute('aria-hidden', 'true');
            element.setAttribute('tabindex', '-1');
            element.style.setProperty('display', 'none', 'important');
            return;
        }
        if (element.dataset.schoolPermissionHidden === '1') {
            const original = element.dataset.schoolOriginalDisplay || '';
            element.style.removeProperty('display');
            if (original) element.style.display = original;
            delete element.dataset.schoolPermissionHidden;
            delete element.dataset.schoolOriginalDisplay;
            element.removeAttribute('aria-hidden');
            if (element.getAttribute('tabindex') === '-1') element.removeAttribute('tabindex');
        }
    };

    const applyElement = element => {
        const action = elementAction(element);
        if (!action) return;
        element.dataset.resolvedPermissionAction = action;
        setVisible(element, allowed(action));
    };
    const scan = root => {
        if (!root) return;
        if (root.matches?.(clickableSelector)) applyElement(root);
        root.querySelectorAll?.(clickableSelector).forEach(applyElement);
    };
    const denyEvent = event => {
        const target = event.target instanceof Element ? event.target.closest(clickableSelector) : null;
        if (!target) return;
        const action = elementAction(target);
        if (action && !allowed(action)) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    };

    document.addEventListener('click', denyEvent, true);
    document.addEventListener('submit', event => {
        const form = event.target instanceof HTMLFormElement ? event.target : null;
        if (!form) return;
        const action = formAction(form);
        if (action && !allowed(action)) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }, true);

    const start = () => {
        scan(document);
        const observer = new MutationObserver(records => {
            for (const record of records) {
                record.addedNodes.forEach(node => { if (node instanceof Element) scan(node); });
                if (record.type === 'attributes' && record.target instanceof Element) scan(record.target);
            }
        });
        observer.observe(document.documentElement, {
            childList:true,subtree:true,attributes:true,
            attributeFilter:['class','id','title','aria-label','data-action','data-permission-action','data-required-permission','href','value']
        });
        document.addEventListener('shown.bs.modal', () => scan(document), true);
        document.addEventListener('click', () => setTimeout(() => scan(document), 0), false);
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, {once:true});
    else start();
})();
</script>
HTML;

        return str_replace('__SCHOOL_PERMISSION_JSON__', $json, $template);
    }
}

if (!function_exists('school_enable_permission_ui_guard')) {
    function school_enable_permission_ui_guard(): void
    {
        if (!is_logged_in() || school_is_api_request()) {
            return;
        }

        $scriptPath = strtolower(str_replace(
            '\\',
            '/',
            (string)(parse_url($_SERVER['SCRIPT_NAME'] ?? '', PHP_URL_PATH) ?: '')
        ));

        /* Platform pages have a separate permission catalogue and UI. */
        if (str_contains('/' . trim($scriptPath, '/') . '/', '/super-admin/')) {
            return;
        }

        $capabilities = school_current_page_capabilities();
        $GLOBALS['school_page_permissions'] = $capabilities;
        $GLOBALS['canView'] = (bool)$capabilities['view'];
        $GLOBALS['canAdd'] = (bool)$capabilities['add'];
        $GLOBALS['canCreate'] = (bool)$capabilities['add'];
        $GLOBALS['canEdit'] = (bool)$capabilities['edit'];
        $GLOBALS['canDelete'] = (bool)$capabilities['delete'];
        $GLOBALS['canPrint'] = (bool)($capabilities['print'] ?? false);
        $GLOBALS['canPdf'] = (bool)($capabilities['pdf'] ?? false);
        $GLOBALS['canExport'] = (bool)($capabilities['export'] ?? false);
        $GLOBALS['canImport'] = (bool)($capabilities['import'] ?? false);
        $GLOBALS['canApprove'] = (bool)($capabilities['approve'] ?? false);
        $GLOBALS['canReject'] = (bool)($capabilities['reject'] ?? false);
        $GLOBALS['canRestore'] = (bool)($capabilities['restore'] ?? false);
        $GLOBALS['canManage'] = (bool)($capabilities['manage_settings'] ?? false);

        $markup = school_permission_ui_markup($capabilities);

        ob_start(static function (string $html) use ($markup): string {
            $trimmed = ltrim($html);
            if ($trimmed === ''
                || (!str_contains(strtolower($html), '<html')
                    && !str_contains(strtolower($html), '<body'))) {
                return $html;
            }

            $position = strripos($html, '</body>');
            if ($position !== false) {
                return substr($html, 0, $position)
                    . $markup
                    . substr($html, $position);
            }

            return $html . $markup;
        });
    }
}

if (!function_exists('school_enable_common_toast_ui')) {
    function school_enable_common_toast_ui(): void
    {
        if (!is_logged_in() || school_is_api_request()) {
            return;
        }

        if (!function_exists('school_toast_markup')) {
            return;
        }

        $toastMarkup = school_toast_markup();

        ob_start(static function (string $html) use ($toastMarkup): string {
            if ($toastMarkup === '') {
                return $html;
            }

            $trimmed = ltrim($html);
            if ($trimmed === ''
                || (!str_contains(strtolower($html), '<html')
                    && !str_contains(strtolower($html), '<body'))) {
                return $html;
            }

            $position = strripos($html, '</body>');
            if ($position !== false) {
                return substr($html, 0, $position)
                    . $toastMarkup
                    . substr($html, $position);
            }

            return $html . $toastMarkup;
        });
    }
}

/*
|--------------------------------------------------------------------------
| Automatic page guard
|--------------------------------------------------------------------------
*/

$currentPage = basename(
    parse_url($_SERVER['SCRIPT_NAME'] ?? '', PHP_URL_PATH) ?: ''
);

$publicPages = [
    'login.php',
    'forgot-password.php',
    'reset-password.php',
    'logout.php',
];

if (!in_array($currentPage, $publicPages, true)) {
    require_login();
    school_validate_authenticated_session();
    school_enforce_role_panel_access();
    school_enable_permission_ui_guard();
    school_enable_common_toast_ui();
}

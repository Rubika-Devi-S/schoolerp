<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Unified authentication + Super Admin user API (complete HY093 fix v4)
|--------------------------------------------------------------------------
| Existing UI files can call this one API. No helper/API file is required.
|
| Supported actions:
| - login
| - logout
| - check_session
| - super_admin_user_meta
| - create_super_admin_user
|--------------------------------------------------------------------------
*/

ob_start();
ini_set('display_errors', '0');

require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (!function_exists('authApiResponse')) {
    function authApiResponse(
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
            ],
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_INVALID_UTF8_SUBSTITUTE
        );

        exit;
    }
}

if (!function_exists('authApiInput')) {
    function authApiInput(): array
    {
        $contentType = strtolower((string)(
            $_SERVER['CONTENT_TYPE']
            ?? ''
        ));

        if (str_contains($contentType, 'application/json')) {
            $rawBody = (string)file_get_contents('php://input');

            if ($rawBody === '') {
                return [];
            }

            $decoded = json_decode($rawBody, true);

            if (!is_array($decoded)) {
                authApiResponse(
                    false,
                    'Invalid JSON request.',
                    [],
                    400
                );
            }

            return $decoded;
        }

        return $_POST;
    }
}

if (!function_exists('authApiTableExists')) {
    function authApiTableExists(
        PDO $pdo,
        string $table
    ): bool {
        if (function_exists('school_table_exists')) {
            return school_table_exists($pdo, $table);
        }

        $statement = $pdo->prepare(
            "SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = :table_name"
        );

        $statement->execute([
            'table_name' => $table,
        ]);

        return (int)$statement->fetchColumn() > 0;
    }
}

if (!function_exists('authApiColumnExists')) {
    function authApiColumnExists(
        PDO $pdo,
        string $table,
        string $column
    ): bool {
        if (function_exists('school_column_exists')) {
            return school_column_exists(
                $pdo,
                $table,
                $column
            );
        }

        $statement = $pdo->prepare(
            "SELECT COUNT(*)
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = :table_name
               AND column_name = :column_name"
        );

        $statement->execute([
            'table_name' => $table,
            'column_name' => $column,
        ]);

        return (int)$statement->fetchColumn() > 0;
    }
}


if (!function_exists('authApiBranchMainExpression')) {
    function authApiBranchMainExpression(PDO $pdo): string
    {
        if (authApiColumnExists($pdo, 'branches', 'is_main')) {
            return 'COALESCE(is_main, 0)';
        }

        if (authApiColumnExists($pdo, 'branches', 'is_head')) {
            return 'COALESCE(is_head, 0)';
        }

        if (authApiColumnExists($pdo, 'branches', 'is_default')) {
            return 'COALESCE(is_default, 0)';
        }

        if (authApiColumnExists($pdo, 'branches', 'branch_type')) {
            return "CASE
                WHEN LOWER(COALESCE(branch_type, '')) IN (
                    'main',
                    'head',
                    'head office',
                    'head_office'
                ) THEN 1
                ELSE 0
            END";
        }

        return '0';
    }
}

if (!function_exists('authApiLocalDebugEnabled')) {
    function authApiLocalDebugEnabled(): bool
    {
        $host = strtolower((string)(
            $_SERVER['HTTP_HOST']
            ?? $_SERVER['SERVER_NAME']
            ?? ''
        ));

        $remoteAddress = (string)(
            $_SERVER['REMOTE_ADDR']
            ?? ''
        );

        return str_contains($host, 'localhost')
            || str_contains($host, '127.0.0.1')
            || in_array(
                $remoteAddress,
                ['127.0.0.1', '::1'],
                true
            );
    }
}

if (!function_exists('authApiCsrfValid')) {
    function authApiCsrfValid(string $token): bool
    {
        if (function_exists('csrf_is_valid')) {
            return csrf_is_valid($token);
        }

        if (function_exists('verify_csrf_token')) {
            return verify_csrf_token($token);
        }

        if (function_exists('verifyCsrf')) {
            return verifyCsrf($token);
        }

        $storedToken = (string)(
            $_SESSION['csrf_token']
            ?? $_SESSION['_csrf']
            ?? $_SESSION['csrf']
            ?? ''
        );

        return $token !== ''
            && $storedToken !== ''
            && hash_equals($storedToken, $token);
    }
}

if (!function_exists('authApiCsrfToken')) {
    function authApiCsrfToken(): string
    {
        if (function_exists('csrfToken')) {
            return (string)csrfToken();
        }

        if (function_exists('csrf_token')) {
            return (string)csrf_token();
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(
                random_bytes(32)
            );
        }

        return (string)$_SESSION['csrf_token'];
    }
}

if (!function_exists('authApiCurrentUser')) {
    function authApiCurrentUser(): array
    {
        return function_exists('current_user')
            ? current_user()
            : [];
    }
}

if (!function_exists('authApiIsSuperAdminRole')) {
    function authApiIsSuperAdminRole(
        int $roleId,
        string $roleKey
    ): bool {
        return $roleId === 1
            || in_array(
                strtolower(trim($roleKey)),
                [
                    'super_admin',
                    'super-administrator',
                    'super_administrator',
                ],
                true
            );
    }
}

if (!function_exists('authApiIsSchoolAdminRole')) {
    function authApiIsSchoolAdminRole(
        string $roleKey,
        string $roleName = ''
    ): bool {
        $normalizedKey = strtolower(trim($roleKey));
        $normalizedName = strtolower(trim($roleName));

        $normalizedName = preg_replace(
            '/[\s_-]+/',
            ' ',
            $normalizedName
        ) ?: $normalizedName;

        return in_array(
            $normalizedKey,
            [
                'school_admin',
                'school-administrator',
                'school_administrator',
                'branch_admin',
                'branch-administrator',
                'branch_administrator',
                'admin',
            ],
            true
        ) || in_array(
            $normalizedName,
            [
                'school admin',
                'school administrator',
                'branch admin',
                'branch administrator',
                'admin',
            ],
            true
        );
    }
}

if (!function_exists('authApiRequireSuperAdmin')) {
    function authApiRequireSuperAdmin(PDO $pdo): array
    {
        if (empty($_SESSION['user_id'])) {
            authApiResponse(
                false,
                'Your login session has expired.',
                [],
                401
            );
        }

        $user = authApiCurrentUser();

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

        if ($roleKey === '' && $roleId > 0) {
            $statement = $pdo->prepare(
                "SELECT role_key
                 FROM roles
                 WHERE id = :role_id
                   AND status = 'active'
                 LIMIT 1"
            );

            $statement->execute([
                'role_id' => $roleId,
            ]);

            $roleKey = strtolower(trim(
                (string)$statement->fetchColumn()
            ));
        }

        if (!authApiIsSuperAdminRole($roleId, $roleKey)) {
            authApiResponse(
                false,
                'Only a Super Administrator can perform this action.',
                [],
                403
            );
        }

        return $user;
    }
}

if (!function_exists('authApiUsersListUrl')) {
    function authApiUsersListUrl(): string
    {
        $baseUrl = defined('BASE_URL')
            ? rtrim((string)BASE_URL, '/') . '/'
            : '../';

        $projectRoot = defined('PROJECT_ROOT')
            ? rtrim((string)PROJECT_ROOT, '/\\')
            : dirname(__DIR__);

        foreach (
            [
                'super-admin/users/index.php',
                'super-admin/users.php',
                'super-admin/super-admin-users-list.php',
            ] as $candidate
        ) {
            if (is_file($projectRoot . '/' . $candidate)) {
                return $baseUrl . $candidate;
            }
        }

        return $baseUrl . 'super-admin/users.php';
    }
}

if (!function_exists('authApiSchoolAdminsListUrl')) {
    function authApiSchoolAdminsListUrl(): string
    {
        $baseUrl = defined('BASE_URL')
            ? rtrim((string)BASE_URL, '/') . '/'
            : '../';

        $projectRoot = defined('PROJECT_ROOT')
            ? rtrim((string)PROJECT_ROOT, '/\\')
            : dirname(__DIR__);

        $candidates = [
            'super-admin/school-admins.php',
            'super-admin/users.php?type=school_admin',
            'super-admin/users.php',
        ];

        foreach ($candidates as $candidate) {
            $path = (string)(
                parse_url($candidate, PHP_URL_PATH)
                ?: ''
            );

            if (
                $path !== ''
                && is_file($projectRoot . '/' . $path)
            ) {
                return $baseUrl . $candidate;
            }
        }

        return $baseUrl
            . 'super-admin/users.php?type=school_admin';
    }
}

if (!function_exists('authApiDeleteFile')) {
    function authApiDeleteFile(?string $path): void
    {
        if (
            $path !== null
            && $path !== ''
            && is_file($path)
        ) {
            @unlink($path);
        }
    }
}


if (!function_exists('authApiSchoolAdminPermissionDefinitions')) {
    function authApiSchoolAdminPermissionDefinitions(): array
    {
        return [
            'full_school_access' => ['Full School Access', 'Access every permitted module and active branch in the assigned school.', 1],
            'branch_management' => ['Branch Management', 'Manage branches and branch access inside the assigned school.', 1],
            'user_management' => ['User Management', 'Manage users, roles and access inside the assigned school.', 1],
            'student_management' => ['Student Management', 'Manage admissions, students, promotion and transfer records.', 1],
            'staff_management' => ['Staff Management', 'Manage teachers, staff and leave records.', 1],
            'fee_management' => ['Fee Management', 'Manage fee structures, collections, dues and fee reports.', 1],
            'examination_management' => ['Examination Management', 'Manage exams, schedules, marks, grades and report cards.', 1],
            'attendance_management' => ['Attendance Management', 'Manage student and staff attendance.', 1],
            'reports_access' => ['Reports Access', 'View and export permitted school reports.', 1],
            'settings_access' => ['Settings Access', 'Manage school profile, general settings and backups.', 1],
            'theme_settings_access' => ['Theme Settings Access', 'Manage the assigned school theme and appearance.', 1],
        ];
    }
}

if (!function_exists('authApiSchoolAdminPermissionRows')) {
    function authApiSchoolAdminPermissionRows(): array
    {
        $rows = [];
        foreach (authApiSchoolAdminPermissionDefinitions() as $key => $value) {
            $rows[] = [
                'key' => $key,
                'label' => $value[0],
                'description' => $value[1],
                'default_allowed' => (int)$value[2],
            ];
        }
        return $rows;
    }
}

if (!function_exists('authApiParseSchoolAdminPermissions')) {
    function authApiParseSchoolAdminPermissions(array $input): array
    {
        $submitted = $input['permissions'] ?? [];
        if (is_string($submitted)) {
            $decoded = json_decode($submitted, true);
            $submitted = is_array($decoded) ? $decoded : [];
        }
        $submitted = is_array($submitted) ? $submitted : [];
        $permissions = [];
        foreach (authApiSchoolAdminPermissionDefinitions() as $key => $value) {
            $permissions[$key] = filter_var(
                $submitted[$key] ?? false,
                FILTER_VALIDATE_BOOLEAN
            ) ? 1 : 0;
        }
        if (($permissions['full_school_access'] ?? 0) === 1) {
            foreach ($permissions as $key => $value) {
                $permissions[$key] = 1;
            }
        }
        return $permissions;
    }
}

if (!function_exists('authApiEnsureSchoolAdminSchema')) {
    function authApiEnsureSchoolAdminSchema(PDO $pdo): void
    {
        foreach (['employee_id', 'gender', 'date_of_birth', 'address', 'deleted_at'] as $column) {
            if (!authApiColumnExists($pdo, 'users', $column)) {
                authApiResponse(
                    false,
                    'Run the supplied School Admin database migration before using this module.',
                    ['missing_column' => $column],
                    500
                );
            }
        }
        if (!authApiTableExists($pdo, 'user_module_permissions')) {
            authApiResponse(
                false,
                'Run the supplied School Admin database migration before using this module.',
                ['missing_table' => 'user_module_permissions'],
                500
            );
        }
    }
}

if (!function_exists('authApiEnsureSchoolAdminRole')) {
    function authApiEnsureSchoolAdminRole(PDO $pdo, int $schoolId): int
    {
        $stmt = $pdo->prepare(
            "SELECT id FROM roles
             WHERE tenant_id = :school_id
               AND role_key = 'school_admin'
             LIMIT 1"
        );
        $stmt->execute(['school_id' => $schoolId]);
        $roleId = (int)($stmt->fetchColumn() ?: 0);
        if ($roleId > 0) {
            return $roleId;
        }

        $templateId = (int)($pdo->query(
            "SELECT id FROM roles
             WHERE role_key = 'school_admin'
             ORDER BY id LIMIT 1"
        )->fetchColumn() ?: 0);

        $stmt = $pdo->prepare(
            "INSERT INTO roles
             (tenant_id, role_key, role_name, is_system, status)
             VALUES
             (:school_id, 'school_admin', 'School Administrator', 0, 'active')"
        );
        $stmt->execute(['school_id' => $schoolId]);
        $roleId = (int)$pdo->lastInsertId();

        if ($templateId > 0 && authApiTableExists($pdo, 'role_page_permissions')) {
            $copy = $pdo->prepare(
                "INSERT INTO role_page_permissions
                 (role_id, page_id, can_view, can_open, can_create, can_edit,
                  can_delete, can_restore, can_approve, can_reject, can_print,
                  can_export, can_import, can_assign, can_publish, can_lock)
                 SELECT :new_role, page_id, can_view, can_open, can_create,
                        can_edit, can_delete, can_restore, can_approve,
                        can_reject, can_print, can_export, can_import,
                        can_assign, can_publish, can_lock
                 FROM role_page_permissions
                 WHERE role_id = :template_role
                 ON DUPLICATE KEY UPDATE
                    can_view=VALUES(can_view), can_open=VALUES(can_open),
                    can_create=VALUES(can_create), can_edit=VALUES(can_edit),
                    can_delete=VALUES(can_delete), can_restore=VALUES(can_restore),
                    can_approve=VALUES(can_approve), can_reject=VALUES(can_reject),
                    can_print=VALUES(can_print), can_export=VALUES(can_export),
                    can_import=VALUES(can_import), can_assign=VALUES(can_assign),
                    can_publish=VALUES(can_publish), can_lock=VALUES(can_lock)"
            );
            $copy->execute([
                'new_role' => $roleId,
                'template_role' => $templateId,
            ]);
        }

        if ($templateId > 0 && authApiTableExists($pdo, 'role_sidebar_permissions')) {
            $copy = $pdo->prepare(
                "INSERT INTO role_sidebar_permissions
                 (role_id, sidebar_item_id, can_show)
                 SELECT :new_role, sidebar_item_id, can_show
                 FROM role_sidebar_permissions
                 WHERE role_id = :template_role
                 ON DUPLICATE KEY UPDATE can_show=VALUES(can_show)"
            );
            $copy->execute([
                'new_role' => $roleId,
                'template_role' => $templateId,
            ]);
        }

        return $roleId;
    }
}

if (!function_exists('authApiSchoolAdminEmployeePreview')) {
    function authApiSchoolAdminEmployeePreview(PDO $pdo, int $schoolId): string
    {
        $stmt = $pdo->prepare(
            "SELECT tenant_code FROM tenants
             WHERE id = :school_id
               AND status IN ('trial','active')
             LIMIT 1"
        );
        $stmt->execute(['school_id' => $schoolId]);
        $code = strtoupper(trim((string)$stmt->fetchColumn()));
        if ($code === '') {
            authApiResponse(false, 'The selected school is unavailable.', [], 422);
        }
        $next = (int)($pdo->query(
            "SELECT AUTO_INCREMENT
             FROM information_schema.tables
             WHERE table_schema=DATABASE()
               AND table_name='users'"
        )->fetchColumn() ?: 1);
        $code = preg_replace('/[^A-Z0-9]+/', '', $code) ?: 'SCH';
        return sprintf('SA-%s-%06d', $code, max(1, $next));
    }
}

if (!function_exists('authApiSaveSchoolAdminPermissions')) {
    function authApiSaveSchoolAdminPermissions(
        PDO $pdo,
        int $schoolId,
        int $userId,
        array $permissions
    ): void {
        $stmt = $pdo->prepare(
            "INSERT INTO user_module_permissions
             (tenant_id, user_id, permission_key, is_allowed)
             VALUES
             (:school_id, :user_id, :permission_key, :is_allowed)
             ON DUPLICATE KEY UPDATE
                tenant_id=VALUES(tenant_id),
                is_allowed=VALUES(is_allowed),
                updated_at=CURRENT_TIMESTAMP"
        );
        foreach ($permissions as $key => $allowed) {
            $stmt->execute([
                'school_id' => $schoolId,
                'user_id' => $userId,
                'permission_key' => $key,
                'is_allowed' => $allowed,
            ]);
        }
    }
}

if (!function_exists('authApiLoadSchoolAdminPermissions')) {
    function authApiLoadSchoolAdminPermissions(
        PDO $pdo,
        int $schoolId,
        int $userId
    ): array {
        $permissions = [];
        foreach (authApiSchoolAdminPermissionDefinitions() as $key => $value) {
            $permissions[$key] = (int)$value[2];
        }
        $stmt = $pdo->prepare(
            "SELECT permission_key, is_allowed
             FROM user_module_permissions
             WHERE tenant_id=:school_id AND user_id=:user_id"
        );
        $stmt->execute(['school_id' => $schoolId, 'user_id' => $userId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = (string)$row['permission_key'];
            if (array_key_exists($key, $permissions)) {
                $permissions[$key] = (int)$row['is_allowed'];
            }
        }
        return $permissions;
    }
}

if (!function_exists('authApiAssignSchoolAdminBranches')) {
    function authApiAssignSchoolAdminBranches(
        PDO $pdo,
        int $schoolId,
        int $userId,
        ?int $defaultBranchId,
        array $permissions
    ): void {
        if (!authApiTableExists($pdo, 'user_branch_access')) {
            return;
        }
        $delete = $pdo->prepare(
            "DELETE FROM user_branch_access
             WHERE user_id=:user_id"
        );
        $delete->execute(['user_id' => $userId]);

        $all = (int)($permissions['full_school_access'] ?? 0) === 1
            || (int)($permissions['branch_management'] ?? 0) === 1;

        if ($all) {
            $stmt = $pdo->prepare(
                "INSERT INTO user_branch_access
                 (user_id, branch_id, can_access)
                 SELECT :user_id, id, 1 FROM branches
                 WHERE tenant_id=:school_id AND status='active'
                 ON DUPLICATE KEY UPDATE can_access=1"
            );
            $stmt->execute(['user_id' => $userId, 'school_id' => $schoolId]);
            return;
        }

        if ($defaultBranchId === null || $defaultBranchId <= 0) {
            return;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO user_branch_access
             (user_id, branch_id, can_access)
             VALUES (:user_id, :branch_id, 1)
             ON DUPLICATE KEY UPDATE can_access=1"
        );
        $stmt->execute([
            'user_id' => $userId,
            'branch_id' => $defaultBranchId,
        ]);
    }
}

if (!function_exists('authApiStoreSchoolAdminPhoto')) {
    function authApiStoreSchoolAdminPhoto(int $schoolId, int $branchId): array
    {
        if (
            !isset($_FILES['profile_photo'])
            || !is_array($_FILES['profile_photo'])
            || (int)($_FILES['profile_photo']['error'] ?? UPLOAD_ERR_NO_FILE)
                === UPLOAD_ERR_NO_FILE
        ) {
            return ['relative' => null, 'absolute' => null];
        }

        $file = $_FILES['profile_photo'];
        if ((int)$file['error'] !== UPLOAD_ERR_OK) {
            authApiResponse(false, 'Profile photo upload failed.', [], 422);
        }
        if ((int)$file['size'] <= 0 || (int)$file['size'] > 2 * 1024 * 1024) {
            authApiResponse(false, 'Profile photo must be smaller than 2 MB.', [], 422);
        }
        if (!is_uploaded_file((string)$file['tmp_name'])) {
            authApiResponse(false, 'Invalid profile photo upload.', [], 422);
        }

        $mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file(
            (string)$file['tmp_name']
        );
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($allowed[$mime])) {
            authApiResponse(false, 'Profile photo must be JPG, PNG or WebP.', [], 422);
        }

        $root = defined('PROJECT_ROOT')
            ? rtrim((string)PROJECT_ROOT, '/\\')
            : dirname(__DIR__);
        $directory = "uploads/users/school-{$schoolId}/branch-{$branchId}";
        $absoluteDirectory = $root . '/' . $directory;
        if (
            !is_dir($absoluteDirectory)
            && !mkdir($absoluteDirectory, 0755, true)
            && !is_dir($absoluteDirectory)
        ) {
            authApiResponse(false, 'Unable to create the profile photo directory.', [], 500);
        }
        $name = 'school_admin_' . bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
        $relative = $directory . '/' . $name;
        $absolute = $root . '/' . $relative;
        if (!move_uploaded_file((string)$file['tmp_name'], $absolute)) {
            authApiResponse(false, 'Unable to save the profile photo.', [], 500);
        }
        return ['relative' => $relative, 'absolute' => $absolute];
    }
}

if (!function_exists('authApiSchoolAdminRecord')) {
    function authApiSchoolAdminRecord(PDO $pdo, int $userId): array
    {
        $stmt = $pdo->prepare(
            "SELECT u.id,u.tenant_id,u.default_branch_id,u.role_id,
                    u.employee_id,u.name,u.email,u.mobile,u.username,
                    u.profile_photo,u.gender,u.date_of_birth,u.address,
                    u.status,u.last_login_at,u.created_at,
                    t.tenant_code,t.school_name,
                    b.branch_code,b.branch_name,
                    r.role_key,r.role_name
             FROM users u
             INNER JOIN roles r
                ON r.id=u.role_id AND r.role_key='school_admin'
             INNER JOIN tenants t ON t.id=u.tenant_id
             LEFT JOIN branches b
                ON b.id=u.default_branch_id
               AND b.tenant_id=u.tenant_id
             WHERE u.id=:user_id
               AND u.deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute(['user_id' => $userId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($item)) {
            authApiResponse(false, 'School Admin user was not found.', [], 404);
        }
        $item['permissions'] = authApiLoadSchoolAdminPermissions(
            $pdo,
            (int)$item['tenant_id'],
            (int)$item['id']
        );
        return $item;
    }
}

if (!function_exists('authApiLogSchoolAdminAction')) {
    function authApiLogSchoolAdminAction(
        PDO $pdo,
        int $schoolId,
        ?int $branchId,
        int $recordId,
        string $action,
        string $description,
        array $values = []
    ): void {
        if (!authApiTableExists($pdo, 'activity_logs')) {
            return;
        }
        $user = authApiCurrentUser();
        $stmt = $pdo->prepare(
            "INSERT INTO activity_logs
             (tenant_id,branch_id,user_id,role_id,module_name,action_key,
              table_name,record_id,new_values,description,ip_address,user_agent)
             VALUES
             (:school_id,:branch_id,:user_id,:role_id,'School Admin Management',
              :action_key,'users',:record_id,:new_values,:description,
              :ip_address,:user_agent)"
        );
        $stmt->execute([
            'school_id' => $schoolId,
            'branch_id' => $branchId,
            'user_id' => (int)($user['id'] ?? $user['user_id'] ?? $_SESSION['user_id'] ?? 0) ?: null,
            'role_id' => (int)($user['role_id'] ?? $_SESSION['role_id'] ?? 0) ?: null,
            'action_key' => $action,
            'record_id' => $recordId,
            'new_values' => json_encode(
                $values,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
            'description' => $description,
            'ip_address' => substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
            'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1000) ?: null,
        ]);
    }
}

if (!function_exists('authApiSyncBranchOnlySidebar')) {
    function authApiSyncBranchOnlySidebar(PDO $pdo): void
    {
        if (!authApiTableExists($pdo, 'sidebar_items')) {
            return;
        }

        try {
            $hasPortalScope = authApiColumnExists(
                $pdo,
                'sidebar_items',
                'portal_scope'
            );

            $scopeCondition = $hasPortalScope
                ? " AND portal_scope = 'super_admin'"
                : '';

            /*
             * Keep the existing parent and UI structure, but change only its
             * functional meaning from School Management to Branch Management.
             */
            $pdo->exec(
                "UPDATE sidebar_items
                 SET menu_title = 'Branch Management',
                     icon = 'git-branch',
                     route = '#',
                     show_in_sidebar = 1,
                     is_active = 1
                 WHERE menu_key = 'sa_school_management'"
                 . $scopeCondition
            );

            $parentStatement = $pdo->query(
                "SELECT id
                 FROM sidebar_items
                 WHERE menu_key = 'sa_school_management'
                 LIMIT 1"
            );

            $branchParentId = (int)(
                $parentStatement->fetchColumn()
                ?: 0
            );

            if ($branchParentId > 0) {
                $branchStatement = $pdo->prepare(
                    "UPDATE sidebar_items
                     SET parent_id = :parent_id,
                         menu_title = 'Branch List',
                         route = 'super-admin/branches.php',
                         icon = 'git-branch',
                         display_order = 1,
                         show_in_sidebar = 1,
                         is_active = 1
                     WHERE menu_key = 'sa_branches'"
                );

                $branchStatement->execute([
                    'parent_id' => $branchParentId,
                ]);
            }

            /*
             * Disable only the old Super Admin School/Department management
             * entries. School Admin portal records are not changed.
             */
            $disabledKeys = [
                'sa_schools',
                'sa_add_school',
                'sa_school_requests',
                'sa_school_status',
                'sa_departments',
                'sa_department_management',
            ];

            $placeholders = implode(
                ', ',
                array_fill(0, count($disabledKeys), '?')
            );

            $disableStatement = $pdo->prepare(
                "UPDATE sidebar_items
                 SET show_in_sidebar = 0,
                     is_active = 0
                 WHERE menu_key IN ({$placeholders})"
                 . $scopeCondition
            );

            $disableStatement->execute($disabledKeys);
        } catch (Throwable $exception) {
            /*
             * Sidebar cleanup must not stop login or user creation.
             */
            error_log(
                'Branch-only sidebar sync failed: '
                . $exception->getMessage()
            );
        }
    }
}

if (!function_exists('authApiDevelopmentBypassEnabled')) {
    function authApiDevelopmentBypassEnabled(): bool
    {
        $remoteAddress = (string)(
            $_SERVER['REMOTE_ADDR']
            ?? ''
        );

        $serverName = strtolower((string)(
            $_SERVER['SERVER_NAME']
            ?? ''
        ));

        $isLocal = in_array(
            $remoteAddress,
            ['127.0.0.1', '::1'],
            true
        ) || in_array(
            $serverName,
            ['localhost', '127.0.0.1', '::1'],
            true
        );

        $environmentFlag = filter_var(
            getenv('SUPER_ADMIN_DEV_PASSWORD_BYPASS')
                ?: '0',
            FILTER_VALIDATE_BOOLEAN
        );

        return $isLocal || $environmentFlag;
    }
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    authApiResponse(
        false,
        'Database connection is unavailable.',
        [],
        500
    );
}

$input = authApiInput();

$action = strtolower(trim((string)(
    $input['action']
    ?? $_GET['action']
    ?? ''
)));

try {
    $schoolAdminOnly = false;

    switch ($action) {
        case 'login':
            $login = trim((string)(
                $input['login']
                ?? $input['username']
                ?? $input['identity']
                ?? $input['email']
                ?? ''
            ));

            $password = (string)(
                $input['password']
                ?? ''
            );

            if ($login === '') {
                authApiResponse(
                    false,
                    'Enter your username or email.',
                    [],
                    422
                );
            }

            if ($password === '') {
                authApiResponse(
                    false,
                    'Enter your password.',
                    [],
                    422
                );
            }

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
                    u.password_hash,
                    u.profile_photo,
                    u.status AS user_status,
                    r.role_key,
                    r.role_name,
                    r.status AS role_status,
                    b.branch_code,
                    b.branch_name,
                    b.status AS branch_status
                 FROM users AS u
                 INNER JOIN roles AS r
                    ON r.id = u.role_id
                 LEFT JOIN branches AS b
                    ON b.id = u.default_branch_id
                 WHERE
                    LOWER(u.username) = LOWER(:login_username)
                    OR LOWER(COALESCE(u.email, ''))
                       = LOWER(:login_email)
                 LIMIT 1"
            );

            $statement->execute([
                'login_username' => $login,
                'login_email' => $login,
            ]);

            $user = $statement->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                authApiResponse(
                    false,
                    'Invalid username/email or password.',
                    [],
                    422
                );
            }

            if (
                strtolower((string)$user['user_status'])
                    !== 'active'
                || strtolower((string)$user['role_status'])
                    !== 'active'
            ) {
                authApiResponse(
                    false,
                    'Your account is inactive.',
                    [],
                    403
                );
            }

            $isSuperAdmin = authApiIsSuperAdminRole(
                (int)$user['role_id'],
                (string)$user['role_key']
            );

            if (
                !$isSuperAdmin
                && $user['default_branch_id'] !== null
                && strtolower((string)$user['branch_status'])
                    !== 'active'
            ) {
                authApiResponse(
                    false,
                    'Your branch is inactive.',
                    [],
                    403
                );
            }

            /*
             * Temporary development bypass:
             * - username/email must still match an active Super Admin account
             * - any non-empty password is accepted only on localhost, or when
             *   SUPER_ADMIN_DEV_PASSWORD_BYPASS=1 is explicitly configured
             * - every other role continues to use password_verify()
             */
            $developmentBypass = $isSuperAdmin
                && authApiDevelopmentBypassEnabled();

            $passwordValid = $developmentBypass
                || password_verify(
                    $password,
                    (string)$user['password_hash']
                );

            if (!$passwordValid) {
                authApiResponse(
                    false,
                    'Invalid username/email or password.',
                    [],
                    422
                );
            }

            session_regenerate_id(true);

            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['tenant_id'] = (int)$user['tenant_id'];
            $_SESSION['branch_id'] = $user['default_branch_id'] !== null
                ? (int)$user['default_branch_id']
                : 0;
            $_SESSION['role_id'] = (int)$user['role_id'];
            $_SESSION['name'] = (string)$user['name'];
            $_SESSION['email'] = (string)($user['email'] ?? '');
            $_SESSION['mobile'] = (string)($user['mobile'] ?? '');
            $_SESSION['username'] = (string)$user['username'];
            $_SESSION['role_key'] = (string)$user['role_key'];
            $_SESSION['role_name'] = (string)$user['role_name'];
            $_SESSION['profile_photo'] = (string)(
                $user['profile_photo']
                ?? ''
            );
            $_SESSION['branch_code'] = (string)(
                $user['branch_code']
                ?? ''
            );
            $_SESSION['branch_name'] = (string)(
                $user['branch_name']
                ?? ''
            );
            $_SESSION['logged_in_at'] = date('Y-m-d H:i:s');
            $_SESSION['super_admin_dev_password_bypass'] =
                $developmentBypass;

            $updateStatement = $pdo->prepare(
                "UPDATE users
                 SET last_login_at = NOW()
                 WHERE id = :id"
            );

            $updateStatement->execute([
                'id' => (int)$user['id'],
            ]);

            authApiSyncBranchOnlySidebar($pdo);

            $baseUrl = defined('BASE_URL')
                ? rtrim((string)BASE_URL, '/') . '/'
                : '../';

            authApiResponse(
                true,
                'Login successful.',
                [
                    'redirect' => $isSuperAdmin
                        ? $baseUrl
                            . 'super-admin/dashboard.php'
                        : $baseUrl . 'dashboard.php',
                    'development_password_bypass' =>
                        $developmentBypass,
                ]
            );

        case 'logout':
            $_SESSION = [];

            if (
                ini_get('session.use_cookies')
                && session_status() === PHP_SESSION_ACTIVE
            ) {
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

            authApiResponse(
                true,
                'Logged out successfully.'
            );

        case 'check_session':
            authApiResponse(
                true,
                !empty($_SESSION['user_id'])
                    ? 'Session is active.'
                    : 'No active session.',
                [
                    'authenticated' =>
                        !empty($_SESSION['user_id']),
                    'user_id' => (int)(
                        $_SESSION['user_id']
                        ?? 0
                    ),
                    'role_key' => (string)(
                        $_SESSION['role_key']
                        ?? ''
                    ),
                    'branch_id' => (int)(
                        $_SESSION['branch_id']
                        ?? 0
                    ),
                ]
            );

        case 'school_admin_user_meta':
            authApiRequireSuperAdmin($pdo);
            authApiEnsureSchoolAdminSchema($pdo);

            $schools = $pdo->query(
                "SELECT id,tenant_code,school_name,status
                 FROM tenants
                 WHERE status IN ('trial','active')
                 ORDER BY school_name,id"
            )->fetchAll(PDO::FETCH_ASSOC);

            $mainBranchExpression =
                authApiBranchMainExpression($pdo);

            $branches = $pdo->query(
                "SELECT
                    id,
                    tenant_id,
                    branch_code,
                    branch_name,
                    {$mainBranchExpression} AS is_main,
                    status
                 FROM branches
                 WHERE status='active'
                 ORDER BY
                    tenant_id,
                    is_main DESC,
                    branch_name,
                    id"
            )->fetchAll(PDO::FETCH_ASSOC);

            authApiResponse(
                true,
                'School Admin management details loaded.',
                [
                    'csrf_token' => authApiCsrfToken(),
                    'schools' => $schools,
                    'branches' => $branches,
                    'permissions' => authApiSchoolAdminPermissionRows(),
                ]
            );

        case 'super_admin_user_meta':
            authApiRequireSuperAdmin($pdo);
            authApiSyncBranchOnlySidebar($pdo);

            $branchStatement = $pdo->query(
                "SELECT
                    id,
                    tenant_id,
                    branch_code,
                    branch_name
                 FROM branches
                 WHERE status = 'active'
                 ORDER BY
                    branch_name,
                    branch_code,
                    id"
            );

            $roleStatement = $pdo->query(
                "SELECT
                    id,
                    tenant_id,
                    role_key,
                    role_name,
                    is_system
                 FROM roles
                 WHERE status = 'active'
                 ORDER BY
                    is_system DESC,
                    role_name,
                    id"
            );

            $availableRoles = $roleStatement->fetchAll(
                PDO::FETCH_ASSOC
            );

            if ($schoolAdminOnly) {
                $availableRoles = array_values(
                    array_filter(
                        $availableRoles,
                        static function (array $role): bool {
                            return authApiIsSchoolAdminRole(
                                (string)($role['role_key'] ?? ''),
                                (string)($role['role_name'] ?? '')
                            );
                        }
                    )
                );
            }

            authApiResponse(
                true,
                $schoolAdminOnly
                    ? 'Branch and School Admin role details loaded.'
                    : 'Branch and role details loaded.',
                [
                    'csrf_token' => authApiCsrfToken(),
                    'branches' => $branchStatement->fetchAll(
                        PDO::FETCH_ASSOC
                    ),
                    'roles' => $availableRoles,
                    'users_list_url' => $schoolAdminOnly
                        ? authApiSchoolAdminsListUrl()
                        : authApiUsersListUrl(),
                ]
            );

        case 'school_admin_employee_id':
            authApiRequireSuperAdmin($pdo);
            authApiEnsureSchoolAdminSchema($pdo);

            $schoolId = filter_var(
                $_GET['school_id'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );
            if ($schoolId === false) {
                authApiResponse(false, 'Select a valid school.', [], 422);
            }

            $pdo->beginTransaction();
            try {
                $adminRoleId = authApiEnsureSchoolAdminRole(
                    $pdo,
                    (int)$schoolId
                );
                $preview = authApiSchoolAdminEmployeePreview(
                    $pdo,
                    (int)$schoolId
                );
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }

            authApiResponse(
                true,
                'Employee ID generated.',
                [
                    'role_id' => $adminRoleId,
                    'employee_id_preview' => $preview,
                ]
            );

        case 'school_admin_list':
            authApiRequireSuperAdmin($pdo);
            authApiEnsureSchoolAdminSchema($pdo);

            $search = trim((string)($_GET['search'] ?? ''));
            $schoolId = filter_var(
                $_GET['school_id'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );
            $status = strtolower(trim((string)($_GET['status'] ?? '')));

            $where = ["r.role_key='school_admin'", 'u.deleted_at IS NULL'];
            $params = [];

            if ($schoolId !== false) {
                $where[] = 'u.tenant_id=:school_id';
                $params['school_id'] = $schoolId;
            }
            if (in_array($status, ['active', 'inactive'], true)) {
                $where[] = 'u.status=:status';
                $params['status'] = $status;
            }
            if ($search !== '') {
                $where[] = "(
                    u.name LIKE :search_name
                    OR u.username LIKE :search_username
                    OR COALESCE(u.email,'') LIKE :search_email
                    OR COALESCE(u.mobile,'') LIKE :search_mobile
                    OR COALESCE(u.employee_id,'') LIKE :search_employee
                    OR t.school_name LIKE :search_school
                    OR COALESCE(b.branch_name,'') LIKE :search_branch
                )";

                $searchValue = '%' . $search . '%';

                $params['search_name'] = $searchValue;
                $params['search_username'] = $searchValue;
                $params['search_email'] = $searchValue;
                $params['search_mobile'] = $searchValue;
                $params['search_employee'] = $searchValue;
                $params['search_school'] = $searchValue;
                $params['search_branch'] = $searchValue;
            }

            $stmt = $pdo->prepare(
                "SELECT u.id,u.tenant_id,u.default_branch_id,u.employee_id,
                        u.name,u.username,u.email,u.mobile,u.profile_photo,
                        u.status,u.last_login_at,u.created_at,
                        t.tenant_code,t.school_name,b.branch_code,b.branch_name
                 FROM users u
                 INNER JOIN roles r ON r.id=u.role_id
                 INNER JOIN tenants t ON t.id=u.tenant_id
                 LEFT JOIN branches b
                    ON b.id=u.default_branch_id
                   AND b.tenant_id=u.tenant_id
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY t.school_name,u.name,u.id DESC
                 LIMIT 500"
            );
            $stmt->execute($params);

            authApiResponse(
                true,
                'School Admin users loaded.',
                ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC)]
            );

        case 'school_admin_view':
            authApiRequireSuperAdmin($pdo);
            authApiEnsureSchoolAdminSchema($pdo);

            $userId = filter_var(
                $_GET['user_id'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );
            if ($userId === false) {
                authApiResponse(false, 'Select a valid School Admin.', [], 422);
            }
            authApiResponse(
                true,
                'School Admin details loaded.',
                ['item' => authApiSchoolAdminRecord($pdo, (int)$userId)]
            );

        case 'create_school_admin_user':
        case 'update_school_admin_user':
            authApiRequireSuperAdmin($pdo);
            authApiEnsureSchoolAdminSchema($pdo);

            if (!authApiCsrfValid(trim((string)($input['csrf_token'] ?? '')))) {
                authApiResponse(false, 'Invalid or expired CSRF token.', [], 419);
            }

            $isUpdate = $action === 'update_school_admin_user';
            $userId = filter_var(
                $input['user_id'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );
            if ($isUpdate && $userId === false) {
                authApiResponse(false, 'Select a valid School Admin.', [], 422);
            }

            $name = trim((string)($input['name'] ?? ''));
            $username = preg_replace(
                '/\s+/u',
                ' ',
                trim((string)($input['username'] ?? ''))
            ) ?? '';
            $email = strtolower(trim((string)($input['email'] ?? '')));
            $mobile = trim((string)($input['mobile'] ?? ''));
            $password = (string)($input['password'] ?? '');
            $confirmPassword = (string)($input['confirm_password'] ?? '');
            $schoolId = filter_var(
                $input['school_id'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );
            $submittedBranchId = trim((string)(
                $input['branch_id'] ?? ''
            ));

            $branchId = $submittedBranchId === ''
                ? null
                : filter_var(
                    $submittedBranchId,
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1]]
                );
            $gender = strtolower(trim((string)($input['gender'] ?? '')));
            $dob = trim((string)($input['date_of_birth'] ?? ''));
            $address = trim((string)($input['address'] ?? ''));
            $status = strtolower(trim((string)($input['status'] ?? 'active')));
            $permissions = authApiParseSchoolAdminPermissions($input);

            if (mb_strlen($name) < 2 || mb_strlen($name) > 150) {
                authApiResponse(false, 'School Admin Name must contain 2 to 150 characters.', [], 422);
            }
            if (
                mb_strlen($username) < 3
                || mb_strlen($username) > 100
                || preg_match(
                    '/^[\p{L}\p{N}][\p{L}\p{N} ._@-]{1,98}[\p{L}\p{N}]$/u',
                    $username
                ) !== 1
            ) {
                authApiResponse(false, 'Username must contain 3 to 100 letters or numbers. Spaces, dots, underscores, @ and hyphens are allowed.', [], 422);
            }
            if (
                filter_var($email, FILTER_VALIDATE_EMAIL) === false
                || mb_strlen($email) > 150
            ) {
                authApiResponse(false, 'Enter a valid email address.', [], 422);
            }
            if (
                $mobile !== ''
                && preg_match('/^\+?[0-9][0-9\s-]{6,19}$/', $mobile) !== 1
            ) {
                authApiResponse(false, 'Enter a valid mobile number.', [], 422);
            }

            if (!$isUpdate || $password !== '' || $confirmPassword !== '') {
                if (
                    mb_strlen($password) < 6
                    || mb_strlen($password) > 128
                ) {
                    authApiResponse(
                        false,
                        'Password must contain 6 to 128 characters. Letters, numbers, symbols and spaces are supported.',
                        [],
                        422
                    );
                }
                if (!hash_equals($password, $confirmPassword)) {
                    authApiResponse(false, 'Password confirmation does not match.', [], 422);
                }
            }

            if ($schoolId === false) {
                authApiResponse(false, 'Select a valid school.', [], 422);
            }
            if ($branchId === false) {
                authApiResponse(false, 'Select a valid branch or leave it automatic.', [], 422);
            }
            if ($gender !== '' && !in_array($gender, ['male', 'female', 'other'], true)) {
                authApiResponse(false, 'Select a valid gender.', [], 422);
            }
            if (
                $dob !== ''
                && (
                    preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob) !== 1
                    || strtotime($dob) === false
                    || $dob > date('Y-m-d')
                )
            ) {
                authApiResponse(false, 'Enter a valid Date of Birth.', [], 422);
            }
            if (mb_strlen($address) > 1000) {
                authApiResponse(false, 'Address cannot exceed 1000 characters.', [], 422);
            }
            if (!in_array($status, ['active', 'inactive'], true)) {
                authApiResponse(false, 'Select a valid account status.', [], 422);
            }

            $stmt = $pdo->prepare(
                "SELECT id,tenant_code,school_name
                 FROM tenants
                 WHERE id=:school_id
                   AND status IN ('trial','active')
                 LIMIT 1"
            );
            $stmt->execute(['school_id' => $schoolId]);
            $selectedSchool = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$selectedSchool) {
                authApiResponse(false, 'The selected school is unavailable.', [], 422);
            }

            if ($branchId !== null) {
                $mainBranchExpression =
                    authApiBranchMainExpression($pdo);

                $stmt = $pdo->prepare(
                    "SELECT
                        id,
                        tenant_id,
                        branch_code,
                        branch_name,
                        {$mainBranchExpression} AS is_main
                     FROM branches
                     WHERE id=:branch_id
                       AND tenant_id=:school_id
                       AND status='active'
                     LIMIT 1"
                );
                $stmt->execute([
                    'branch_id' => $branchId,
                    'school_id' => $schoolId,
                ]);

                $selectedBranch = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$selectedBranch) {
                    authApiResponse(
                        false,
                        'The selected branch does not belong to the selected school.',
                        [],
                        422
                    );
                }
            } else {
                $mainBranchExpression =
                    authApiBranchMainExpression($pdo);

                $stmt = $pdo->prepare(
                    "SELECT
                        id,
                        tenant_id,
                        branch_code,
                        branch_name,
                        {$mainBranchExpression} AS is_main
                     FROM branches
                     WHERE tenant_id=:school_id
                       AND status='active'
                     ORDER BY is_main DESC, id ASC
                     LIMIT 1"
                );
                $stmt->execute([
                    'school_id' => $schoolId,
                ]);

                $selectedBranch = $stmt->fetch(PDO::FETCH_ASSOC);

                $branchId = $selectedBranch
                    ? (int)$selectedBranch['id']
                    : null;
            }

            $oldItem = $isUpdate
                ? authApiSchoolAdminRecord($pdo, (int)$userId)
                : null;

            $stmt = $pdo->prepare(
                "SELECT id,username,email
                 FROM users
                 WHERE tenant_id = :duplicate_school_id
                   AND deleted_at IS NULL
                   AND (
                        LOWER(username) =
                            LOWER(:duplicate_username)
                        OR LOWER(COALESCE(email, '')) =
                            LOWER(:duplicate_email)
                   )
                   AND id <> :exclude_user_id
                 LIMIT 1"
            );

            $stmt->execute([
                'duplicate_school_id' => (int)$schoolId,
                'duplicate_username' => $username,
                'duplicate_email' => $email,
                'exclude_user_id' =>
                    $isUpdate ? (int)$userId : 0,
            ]);
            $duplicate = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($duplicate) {
                if (strcasecmp((string)$duplicate['username'], $username) === 0) {
                    authApiResponse(false, 'This username already exists in the selected school.', [], 409);
                }
                authApiResponse(false, 'This email address already exists in the selected school.', [], 409);
            }

            $photo = authApiStoreSchoolAdminPhoto(
                (int)$schoolId,
                (int)($branchId ?? 0)
            );
            $newPhoto = $photo['relative'];
            $newPhotoAbsolute = $photo['absolute'];
            $oldPhotoAbsolute = null;

            try {
                $pdo->beginTransaction();
                $schoolAdminRoleId = authApiEnsureSchoolAdminRole(
                    $pdo,
                    (int)$schoolId
                );

                $passwordHash = null;
                if (!$isUpdate || $password !== '') {
                    $passwordHash = password_hash(
                        $password,
                        PASSWORD_BCRYPT,
                        ['cost' => 12]
                    );
                    if ($passwordHash === false) {
                        throw new RuntimeException('Password hashing failed.');
                    }
                }

                if ($isUpdate) {
                    $profilePhoto = $newPhoto
                        ?? $oldItem['profile_photo']
                        ?? null;
                    $sql = "UPDATE users SET
                                tenant_id=:school_id,
                                default_branch_id=:branch_id,
                                role_id=:role_id,
                                name=:name,email=:email,mobile=:mobile,
                                username=:username,profile_photo=:profile_photo,
                                gender=:gender,date_of_birth=:dob,address=:address,
                                status=:status
                            WHERE id=:user_id AND deleted_at IS NULL";
                    $params = [
                        'school_id' => $schoolId,
                        'branch_id' => $branchId,
                        'role_id' => $schoolAdminRoleId,
                        'name' => $name,
                        'email' => $email,
                        'mobile' => $mobile !== '' ? $mobile : null,
                        'username' => $username,
                        'profile_photo' => $profilePhoto,
                        'gender' => $gender !== '' ? $gender : null,
                        'dob' => $dob !== '' ? $dob : null,
                        'address' => $address !== '' ? $address : null,
                        'status' => $status,
                        'user_id' => $userId,
                    ];
                    if ($passwordHash !== null) {
                        $sql = str_replace(
                            'profile_photo=:profile_photo,',
                            'password_hash=:password_hash,profile_photo=:profile_photo,',
                            $sql
                        );
                        $params['password_hash'] = $passwordHash;
                    }
                    $pdo->prepare($sql)->execute($params);
                    $savedUserId = (int)$userId;

                    if ($newPhoto !== null && !empty($oldItem['profile_photo'])) {
                        $root = defined('PROJECT_ROOT')
                            ? rtrim((string)PROJECT_ROOT, '/\\')
                            : dirname(__DIR__);
                        $oldPhotoAbsolute = $root . '/' . ltrim(
                            (string)$oldItem['profile_photo'],
                            '/'
                        );
                    }
                } else {
                    $stmt = $pdo->prepare(
                        "INSERT INTO users
                         (tenant_id,default_branch_id,role_id,employee_id,
                          name,email,mobile,username,password_hash,profile_photo,
                          gender,date_of_birth,address,status)
                         VALUES
                         (:school_id,:branch_id,:role_id,NULL,
                          :name,:email,:mobile,:username,:password_hash,
                          :profile_photo,:gender,:dob,:address,:status)"
                    );
                    $stmt->execute([
                        'school_id' => $schoolId,
                        'branch_id' => $branchId,
                        'role_id' => $schoolAdminRoleId,
                        'name' => $name,
                        'email' => $email,
                        'mobile' => $mobile !== '' ? $mobile : null,
                        'username' => $username,
                        'password_hash' => $passwordHash,
                        'profile_photo' => $newPhoto,
                        'gender' => $gender !== '' ? $gender : null,
                        'dob' => $dob !== '' ? $dob : null,
                        'address' => $address !== '' ? $address : null,
                        'status' => $status,
                    ]);
                    $savedUserId = (int)$pdo->lastInsertId();
                    $code = strtoupper(
                        preg_replace(
                            '/[^A-Z0-9]+/',
                            '',
                            (string)$selectedSchool['tenant_code']
                        ) ?: 'SCH'
                    );
                    $employee = sprintf('SA-%s-%06d', $code, $savedUserId);
                    $stmt = $pdo->prepare(
                        "UPDATE users SET employee_id=:employee_id
                         WHERE id=:user_id AND tenant_id=:school_id"
                    );
                    $stmt->execute([
                        'employee_id' => $employee,
                        'user_id' => $savedUserId,
                        'school_id' => $schoolId,
                    ]);
                }

                authApiSaveSchoolAdminPermissions(
                    $pdo,
                    (int)$schoolId,
                    $savedUserId,
                    $permissions
                );
                authApiAssignSchoolAdminBranches(
                    $pdo,
                    (int)$schoolId,
                    $savedUserId,
                    $branchId !== null
                        ? (int)$branchId
                        : null,
                    $permissions
                );
                authApiLogSchoolAdminAction(
                    $pdo,
                    (int)$schoolId,
                    $branchId !== null
                        ? (int)$branchId
                        : null,
                    $savedUserId,
                    $isUpdate ? 'update' : 'create',
                    ($isUpdate ? 'Updated School Admin ' : 'Created School Admin ')
                        . $username,
                    [
                        'school_id' => $schoolId,
                        'branch_id' => $branchId,
                        'username' => $username,
                        'email' => $email,
                        'status' => $status,
                        'permissions' => $permissions,
                    ]
                );
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                authApiDeleteFile($newPhotoAbsolute);
                error_log('School Admin save failed: ' . $e->getMessage());
                if (
                    $e instanceof PDOException
                    && (
                        (string)$e->getCode() === '23000'
                        || (int)($e->errorInfo[1] ?? 0) === 1062
                    )
                ) {
                    authApiResponse(false, 'The username, email or Employee ID already exists in the selected school.', [], 409);
                }
                authApiResponse(
                    false,
                    authApiLocalDebugEnabled()
                        ? 'Unable to save the School Admin: '
                            . $e->getMessage()
                        : 'Unable to save the School Admin. Check the PHP error log.',
                    [
                        'build' =>
                            '2026-08-06-school-admin-hy093-complete-fixed-v4',
                    ],
                    500
                );
            }

            if ($oldPhotoAbsolute !== null) {
                authApiDeleteFile($oldPhotoAbsolute);
            }

            authApiResponse(
                true,
                $isUpdate
                    ? 'School Admin updated successfully.'
                    : 'School Admin created successfully.',
                ['item' => authApiSchoolAdminRecord($pdo, $savedUserId)],
                $isUpdate ? 200 : 201
            );

        case 'reset_school_admin_password':
            authApiRequireSuperAdmin($pdo);
            authApiEnsureSchoolAdminSchema($pdo);

            if (!authApiCsrfValid(trim((string)($input['csrf_token'] ?? '')))) {
                authApiResponse(false, 'Invalid or expired CSRF token.', [], 419);
            }
            $userId = filter_var(
                $input['user_id'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );
            $password = (string)($input['password'] ?? '');
            $confirm = (string)($input['confirm_password'] ?? '');

            if ($userId === false) {
                authApiResponse(false, 'Select a valid School Admin.', [], 422);
            }
            if (
                mb_strlen($password) < 6
                || mb_strlen($password) > 128
            ) {
                authApiResponse(
                    false,
                    'Password must contain 6 to 128 characters. Letters, numbers, symbols and spaces are supported.',
                    [],
                    422
                );
            }
            if (!hash_equals($password, $confirm)) {
                authApiResponse(false, 'Password confirmation does not match.', [], 422);
            }

            $item = authApiSchoolAdminRecord($pdo, (int)$userId);
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            if ($hash === false) {
                authApiResponse(false, 'Unable to secure the password.', [], 500);
            }
            $stmt = $pdo->prepare(
                "UPDATE users SET password_hash=:password_hash
                 WHERE id=:user_id AND tenant_id=:school_id
                   AND deleted_at IS NULL"
            );
            $stmt->execute([
                'password_hash' => $hash,
                'user_id' => $userId,
                'school_id' => $item['tenant_id'],
            ]);
            authApiLogSchoolAdminAction(
                $pdo,
                (int)$item['tenant_id'],
                $item['default_branch_id'] !== null
                    ? (int)$item['default_branch_id']
                    : null,
                (int)$userId,
                'reset_password',
                'Reset password for School Admin ' . $item['username']
            );
            authApiResponse(true, 'School Admin password reset successfully.');

        case 'toggle_school_admin_status':
            authApiRequireSuperAdmin($pdo);
            authApiEnsureSchoolAdminSchema($pdo);

            if (!authApiCsrfValid(trim((string)($input['csrf_token'] ?? '')))) {
                authApiResponse(false, 'Invalid or expired CSRF token.', [], 419);
            }
            $userId = filter_var(
                $input['user_id'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );
            $newStatus = strtolower(trim((string)($input['status'] ?? '')));
            if (
                $userId === false
                || !in_array($newStatus, ['active', 'inactive'], true)
            ) {
                authApiResponse(false, 'Invalid School Admin status request.', [], 422);
            }

            $item = authApiSchoolAdminRecord($pdo, (int)$userId);
            $stmt = $pdo->prepare(
                "UPDATE users SET status=:status
                 WHERE id=:user_id AND tenant_id=:school_id
                   AND deleted_at IS NULL"
            );
            $stmt->execute([
                'status' => $newStatus,
                'user_id' => $userId,
                'school_id' => $item['tenant_id'],
            ]);
            authApiLogSchoolAdminAction(
                $pdo,
                (int)$item['tenant_id'],
                $item['default_branch_id'] !== null
                    ? (int)$item['default_branch_id']
                    : null,
                (int)$userId,
                'status',
                ucfirst($newStatus) . ' School Admin ' . $item['username'],
                ['status' => $newStatus]
            );
            authApiResponse(
                true,
                $newStatus === 'active'
                    ? 'School Admin activated successfully.'
                    : 'School Admin deactivated successfully.'
            );

        case 'delete_school_admin':
            authApiRequireSuperAdmin($pdo);
            authApiEnsureSchoolAdminSchema($pdo);

            if (!authApiCsrfValid(trim((string)($input['csrf_token'] ?? '')))) {
                authApiResponse(false, 'Invalid or expired CSRF token.', [], 419);
            }
            $userId = filter_var(
                $input['user_id'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );
            if ($userId === false) {
                authApiResponse(false, 'Select a valid School Admin.', [], 422);
            }

            $item = authApiSchoolAdminRecord($pdo, (int)$userId);
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare(
                    "UPDATE users SET status='inactive',deleted_at=NOW()
                     WHERE id=:user_id AND tenant_id=:school_id
                       AND deleted_at IS NULL"
                );
                $stmt->execute([
                    'user_id' => $userId,
                    'school_id' => $item['tenant_id'],
                ]);
                if (authApiTableExists($pdo, 'user_branch_access')) {
                    $pdo->prepare(
                        "UPDATE user_branch_access
                         SET can_access=0 WHERE user_id=:user_id"
                    )->execute(['user_id' => $userId]);
                }
                $pdo->prepare(
                    "UPDATE user_module_permissions SET is_allowed=0
                     WHERE tenant_id=:school_id AND user_id=:user_id"
                )->execute([
                    'school_id' => $item['tenant_id'],
                    'user_id' => $userId,
                ]);
                authApiLogSchoolAdminAction(
                    $pdo,
                    (int)$item['tenant_id'],
                    $item['default_branch_id'] !== null
                        ? (int)$item['default_branch_id']
                        : null,
                    (int)$userId,
                    'delete',
                    'Deleted School Admin ' . $item['username']
                );
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
            authApiResponse(true, 'School Admin deleted successfully.');

        case 'create_super_admin_user':
            $currentUser = authApiRequireSuperAdmin($pdo);

            if (!authApiCsrfValid(
                trim((string)(
                    $input['csrf_token']
                    ?? ''
                ))
            )) {
                authApiResponse(
                    false,
                    'Invalid or expired CSRF token.',
                    [],
                    419
                );
            }

            $name = trim((string)(
                $input['name']
                ?? ''
            ));

            $username = preg_replace(
                '/\s+/u',
                ' ',
                trim((string)(
                    $input['username']
                    ?? ''
                ))
            ) ?? '';

            $email = strtolower(trim((string)(
                $input['email']
                ?? ''
            )));

            $mobile = trim((string)(
                $input['mobile']
                ?? ''
            ));

            $password = (string)(
                $input['password']
                ?? ''
            );

            $confirmPassword = (string)(
                $input['confirm_password']
                ?? ''
            );

            $branchId = filter_var(
                $input['branch_id']
                ?? null,
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 1,
                    ],
                ]
            );

            $selectedRoleId = filter_var(
                $input['role_id']
                ?? null,
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 1,
                    ],
                ]
            );

            $status = strtolower(trim((string)(
                $input['status']
                ?? 'active'
            )));

            if (
                mb_strlen($name) < 2
                || mb_strlen($name) > 150
            ) {
                authApiResponse(
                    false,
                    'Full Name must contain 2 to 150 characters.',
                    [],
                    422
                );
            }

            if (
                mb_strlen($username) < 3
                || mb_strlen($username) > 100
                || preg_match(
                    '/^[\p{L}\p{N}][\p{L}\p{N} ._@-]{1,98}[\p{L}\p{N}]$/u',
                    $username
                ) !== 1
            ) {
                authApiResponse(
                    false,
                    'Username must contain 3 to 100 letters or numbers. Spaces, dots, underscores, @ and hyphens are allowed.',
                    [],
                    422
                );
            }

            if (
                filter_var(
                    $email,
                    FILTER_VALIDATE_EMAIL
                ) === false
                || mb_strlen($email) > 150
            ) {
                authApiResponse(
                    false,
                    'Enter a valid email address.',
                    [],
                    422
                );
            }

            if (
                $mobile !== ''
                && preg_match(
                    '/^\+?[0-9][0-9\s-]{6,19}$/',
                    $mobile
                ) !== 1
            ) {
                authApiResponse(
                    false,
                    'Enter a valid mobile number.',
                    [],
                    422
                );
            }

            if (
                mb_strlen($password) < 8
                || preg_match('/[A-Z]/', $password) !== 1
                || preg_match('/[a-z]/', $password) !== 1
                || preg_match('/[0-9]/', $password) !== 1
            ) {
                authApiResponse(
                    false,
                    'Password must contain at least 8 characters, uppercase, lowercase and a number.',
                    [],
                    422
                );
            }

            if (!hash_equals(
                $password,
                $confirmPassword
            )) {
                authApiResponse(
                    false,
                    'Password confirmation does not match.',
                    [],
                    422
                );
            }

            if ($branchId === false) {
                authApiResponse(
                    false,
                    'Select a valid branch.',
                    [],
                    422
                );
            }

            if ($selectedRoleId === false) {
                authApiResponse(
                    false,
                    'Select a valid role.',
                    [],
                    422
                );
            }

            if (!in_array(
                $status,
                ['active', 'inactive'],
                true
            )) {
                authApiResponse(
                    false,
                    'Select a valid status.',
                    [],
                    422
                );
            }

            $branchStatement = $pdo->prepare(
                "SELECT
                    id,
                    tenant_id,
                    branch_code,
                    branch_name
                 FROM branches
                 WHERE id = :branch_id
                   AND status = 'active'
                 LIMIT 1"
            );

            $branchStatement->execute([
                'branch_id' => $branchId,
            ]);

            $branch = $branchStatement->fetch(
                PDO::FETCH_ASSOC
            );

            if (!$branch) {
                authApiResponse(
                    false,
                    'The selected branch is unavailable.',
                    [],
                    422
                );
            }

            $tenantId = (int)$branch['tenant_id'];

            $roleStatement = $pdo->prepare(
                "SELECT
                    id,
                    tenant_id,
                    role_key,
                    role_name
                 FROM roles
                 WHERE id = :role_id
                   AND status = 'active'
                 LIMIT 1"
            );

            $roleStatement->execute([
                'role_id' => $selectedRoleId,
            ]);

            $selectedRole = $roleStatement->fetch(
                PDO::FETCH_ASSOC
            );

            if (!$selectedRole) {
                authApiResponse(
                    false,
                    'The selected role is unavailable.',
                    [],
                    422
                );
            }

            if (
                $schoolAdminOnly
                && !authApiIsSchoolAdminRole(
                    (string)($selectedRole['role_key'] ?? ''),
                    (string)($selectedRole['role_name'] ?? '')
                )
            ) {
                authApiResponse(
                    false,
                    'Select a valid School Admin role.',
                    [],
                    422
                );
            }

            if (
                $selectedRole['tenant_id'] !== null
                && (int)$selectedRole['tenant_id']
                    !== $tenantId
            ) {
                authApiResponse(
                    false,
                    'The selected role is not available for this branch.',
                    [],
                    422
                );
            }

            $duplicateStatement = $pdo->prepare(
                "SELECT username, email
                 FROM users
                 WHERE tenant_id = :tenant_id
                   AND (
                       LOWER(username) = LOWER(:username)
                       OR LOWER(COALESCE(email, ''))
                          = LOWER(:email)
                   )
                 LIMIT 1"
            );

            $duplicateStatement->execute([
                'tenant_id' => $tenantId,
                'username' => $username,
                'email' => $email,
            ]);

            $duplicateUser = $duplicateStatement->fetch(
                PDO::FETCH_ASSOC
            );

            if ($duplicateUser) {
                if (
                    strcasecmp(
                        (string)$duplicateUser['username'],
                        $username
                    ) === 0
                ) {
                    authApiResponse(
                        false,
                        'This username is already used.',
                        [],
                        409
                    );
                }

                authApiResponse(
                    false,
                    'This email address is already used.',
                    [],
                    409
                );
            }

            $profilePhotoRelativePath = null;
            $profilePhotoAbsolutePath = null;

            if (
                isset($_FILES['profile_photo'])
                && is_array($_FILES['profile_photo'])
                && (int)(
                    $_FILES['profile_photo']['error']
                    ?? UPLOAD_ERR_NO_FILE
                ) !== UPLOAD_ERR_NO_FILE
            ) {
                $upload = $_FILES['profile_photo'];
                $uploadError = (int)(
                    $upload['error']
                    ?? UPLOAD_ERR_NO_FILE
                );

                if ($uploadError !== UPLOAD_ERR_OK) {
                    authApiResponse(
                        false,
                        'Profile photo upload failed.',
                        [],
                        422
                    );
                }

                if (
                    (int)($upload['size'] ?? 0) <= 0
                    || (int)$upload['size']
                        > 2 * 1024 * 1024
                ) {
                    authApiResponse(
                        false,
                        'Profile photo must be smaller than 2 MB.',
                        [],
                        422
                    );
                }

                if (
                    !isset($upload['tmp_name'])
                    || !is_uploaded_file(
                        (string)$upload['tmp_name']
                    )
                ) {
                    authApiResponse(
                        false,
                        'Invalid profile photo upload.',
                        [],
                        422
                    );
                }

                $finfo = new finfo(FILEINFO_MIME_TYPE);

                $mimeType = (string)$finfo->file(
                    (string)$upload['tmp_name']
                );

                $allowedImages = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                ];

                if (!isset($allowedImages[$mimeType])) {
                    authApiResponse(
                        false,
                        'Profile photo must be JPG, PNG or WebP.',
                        [],
                        422
                    );
                }

                $projectRoot = defined('PROJECT_ROOT')
                    ? rtrim((string)PROJECT_ROOT, '/\\')
                    : dirname(__DIR__);

                $relativeDirectory = (
                    'uploads/users/branch-'
                    . (int)$branchId
                );

                $absoluteDirectory = (
                    $projectRoot
                    . '/'
                    . $relativeDirectory
                );

                if (
                    !is_dir($absoluteDirectory)
                    && !mkdir(
                        $absoluteDirectory,
                        0755,
                        true
                    )
                    && !is_dir($absoluteDirectory)
                ) {
                    authApiResponse(
                        false,
                        'Unable to create the profile photo directory.',
                        [],
                        500
                    );
                }

                $fileName = (
                    'user_'
                    . bin2hex(random_bytes(16))
                    . '.'
                    . $allowedImages[$mimeType]
                );

                $profilePhotoRelativePath = (
                    $relativeDirectory
                    . '/'
                    . $fileName
                );

                $profilePhotoAbsolutePath = (
                    $projectRoot
                    . '/'
                    . $profilePhotoRelativePath
                );

                if (!move_uploaded_file(
                    (string)$upload['tmp_name'],
                    $profilePhotoAbsolutePath
                )) {
                    authApiResponse(
                        false,
                        'Unable to save the profile photo.',
                        [],
                        500
                    );
                }
            }

            $passwordHash = password_hash(
                $password,
                PASSWORD_BCRYPT,
                [
                    'cost' => 12,
                ]
            );

            if ($passwordHash === false) {
                authApiDeleteFile(
                    $profilePhotoAbsolutePath
                );

                authApiResponse(
                    false,
                    'Unable to secure the password.',
                    [],
                    500
                );
            }

            try {
                $pdo->beginTransaction();

                $insertStatement = $pdo->prepare(
                    "INSERT INTO users (
                        tenant_id,
                        default_branch_id,
                        role_id,
                        name,
                        email,
                        mobile,
                        username,
                        password_hash,
                        profile_photo,
                        status
                    ) VALUES (
                        :tenant_id,
                        :default_branch_id,
                        :role_id,
                        :name,
                        :email,
                        :mobile,
                        :username,
                        :password_hash,
                        :profile_photo,
                        :status
                    )"
                );

                $insertStatement->execute([
                    'tenant_id' => $tenantId,
                    'default_branch_id' => $branchId,
                    'role_id' => $selectedRoleId,
                    'name' => $name,
                    'email' => $email,
                    'mobile' => $mobile !== ''
                        ? $mobile
                        : null,
                    'username' => $username,
                    'password_hash' => $passwordHash,
                    'profile_photo' =>
                        $profilePhotoRelativePath,
                    'status' => $status,
                ]);

                $newUserId = (int)$pdo->lastInsertId();

                if (authApiTableExists(
                    $pdo,
                    'user_branch_access'
                )) {
                    $accessStatement = $pdo->prepare(
                        "INSERT INTO user_branch_access (
                            user_id,
                            branch_id,
                            can_access
                        ) VALUES (
                            :user_id,
                            :branch_id,
                            1
                        )
                        ON DUPLICATE KEY UPDATE
                            can_access = 1"
                    );

                    $accessStatement->execute([
                        'user_id' => $newUserId,
                        'branch_id' => $branchId,
                    ]);
                }

                if (authApiTableExists(
                    $pdo,
                    'activity_logs'
                )) {
                    $creatorUserId = (int)(
                        $currentUser['id']
                        ?? $currentUser['user_id']
                        ?? $_SESSION['user_id']
                        ?? 0
                    );

                    $creatorRoleId = (int)(
                        $currentUser['role_id']
                        ?? $_SESSION['role_id']
                        ?? 0
                    );

                    $logStatement = $pdo->prepare(
                        "INSERT INTO activity_logs (
                            tenant_id,
                            branch_id,
                            user_id,
                            role_id,
                            module_name,
                            action_key,
                            table_name,
                            record_id,
                            new_values,
                            description,
                            ip_address,
                            user_agent
                        ) VALUES (
                            :tenant_id,
                            :branch_id,
                            :user_id,
                            :role_id,
                            'User Management',
                            'create',
                            'users',
                            :record_id,
                            :new_values,
                            :description,
                            :ip_address,
                            :user_agent
                        )"
                    );

                    $logStatement->execute([
                        'tenant_id' => $tenantId,
                        'branch_id' => $branchId,
                        'user_id' => $creatorUserId > 0
                            ? $creatorUserId
                            : null,
                        'role_id' => $creatorRoleId > 0
                            ? $creatorRoleId
                            : null,
                        'record_id' => $newUserId,
                        'new_values' => json_encode(
                            [
                                'name' => $name,
                                'username' => $username,
                                'email' => $email,
                                'role_id' => $selectedRoleId,
                                'branch_id' => $branchId,
                                'status' => $status,
                            ],
                            JSON_UNESCAPED_UNICODE
                            | JSON_UNESCAPED_SLASHES
                        ),
                        'description' => (
                            $schoolAdminOnly
                                ? 'Created School Admin '
                                    . $username
                                : 'Created branch user '
                                    . $username
                        ),
                        'ip_address' => substr(
                            (string)(
                                $_SERVER['REMOTE_ADDR']
                                ?? ''
                            ),
                            0,
                            45
                        ) ?: null,
                        'user_agent' => substr(
                            (string)(
                                $_SERVER['HTTP_USER_AGENT']
                                ?? ''
                            ),
                            0,
                            1000
                        ) ?: null,
                    ]);
                }

                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                authApiDeleteFile(
                    $profilePhotoAbsolutePath
                );

                error_log(
                    'Create branch user failed: '
                    . $exception->getMessage()
                );

                if (
                    $exception instanceof PDOException
                    && (
                        (string)$exception->getCode() === '23000'
                        || (int)(
                            $exception->errorInfo[1]
                            ?? 0
                        ) === 1062
                    )
                ) {
                    authApiResponse(
                        false,
                        'The username or email address already exists.',
                        [],
                        409
                    );
                }

                authApiResponse(
                    false,
                    'Unable to create the user. Check the PHP error log.',
                    [],
                    500
                );
            }

            authApiSyncBranchOnlySidebar($pdo);

            authApiResponse(
                true,
                $schoolAdminOnly
                    ? 'School Admin created successfully.'
                    : 'User created successfully.',
                [
                    'user_id' => $newUserId,
                    'redirect' => $schoolAdminOnly
                        ? authApiSchoolAdminsListUrl()
                        : authApiUsersListUrl(),
                ],
                201
            );

        default:
            authApiResponse(
                false,
                'Invalid API action.',
                [],
                400
            );
    }
} catch (Throwable $exception) {
    error_log(
        'Authentication API error: '
        . $exception->getMessage()
    );

    authApiResponse(
        false,
        authApiLocalDebugEnabled()
            ? 'The request could not be completed'
                . (
                    isset($action) && $action !== ''
                        ? ' [' . $action . ']'
                        : ''
                )
                . ': '
                . $exception->getMessage()
            : 'The request could not be completed.',
        [
            'build' =>
                '2026-08-06-school-admin-hy093-complete-fixed-v4',
            'exception' => authApiLocalDebugEnabled()
                ? get_class($exception)
                : null,
        ],
        500
    );
}

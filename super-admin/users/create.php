<?php
declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);

require_once $projectRoot . '/includes/bootstrap.php';

if (function_exists('require_login')) {
    require_login();
}

$pageTitle = 'Create Super Admin User';

/*
 * Use the existing Super Admin Users menu key so the current sidebar keeps
 * User Management highlighted while this create form is open.
 */
$pageKey = 'super_admin_users';
$sidebarFile = dirname(__DIR__) . '/sidebar.php';

$currentUser = function_exists('current_user')
    ? current_user()
    : [];

$currentRoleId = (int)(
    $currentUser['role_id']
    ?? $_SESSION['role_id']
    ?? 0
);

$currentRoleKey = strtolower(trim((string)(
    $currentUser['role_key']
    ?? $_SESSION['role_key']
    ?? ''
)));

if (
    $currentRoleKey === ''
    && isset($pdo)
    && $pdo instanceof PDO
    && $currentRoleId > 0
) {
    try {
        $roleStatement = $pdo->prepare(
            "SELECT role_key
             FROM roles
             WHERE id = :role_id
               AND status = 'active'
             LIMIT 1"
        );

        $roleStatement->execute([
            'role_id' => $currentRoleId,
        ]);

        $currentRoleKey = strtolower(trim(
            (string)$roleStatement->fetchColumn()
        ));
    } catch (Throwable $exception) {
        error_log(
            'Create user role lookup failed: '
            . $exception->getMessage()
        );
    }
}

$isSuperAdmin = $currentRoleId === 1
    || in_array(
        $currentRoleKey,
        [
            'super_admin',
            'super-administrator',
            'super_administrator',
        ],
        true
    );

if (!$isSuperAdmin) {
    http_response_code(403);

    require $projectRoot . '/includes/layout-start.php';
    ?>
    <div class="ui-card">
        <div class="ui-card-body">
            <h1 class="page-title">Access denied</h1>
            <p class="page-subtitle">
                Only a Super Administrator can create users.
            </p>
        </div>
    </div>
    <?php
    require $projectRoot . '/includes/layout-end.php';
    exit;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    throw new RuntimeException('Database connection is unavailable.');
}

$baseUrl = defined('BASE_URL')
    ? rtrim((string)BASE_URL, '/') . '/'
    : '../../../';

if (!function_exists('saCreateUserTableExists')) {
    function saCreateUserTableExists(
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

if (!function_exists('saCreateUserColumnExists')) {
    function saCreateUserColumnExists(
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

if (!function_exists('saCreateUserCsrfToken')) {
    function saCreateUserCsrfToken(): string
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

if (!function_exists('saCreateUserCsrfValid')) {
    function saCreateUserCsrfValid(string $token): bool
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

        if (function_exists('csrf_validate')) {
            return csrf_validate($token);
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

if (!function_exists('saCreateUserResolveListUrl')) {
    function saCreateUserResolveListUrl(
        string $projectRoot,
        string $baseUrl
    ): string {
        $candidates = [
            'super-admin/users/index.php',
            'super-admin/users.php',
        ];

        foreach ($candidates as $candidate) {
            if (is_file(
                $projectRoot . '/' . $candidate
            )) {
                return $baseUrl . $candidate;
            }
        }

        /*
         * Existing project sidebar already uses this users-list route.
         */
        return $baseUrl . 'super-admin/users.php';
    }
}

if (!function_exists('saCreateUserDeleteFile')) {
    function saCreateUserDeleteFile(
        ?string $absolutePath
    ): void {
        if (
            $absolutePath !== null
            && $absolutePath !== ''
            && is_file($absolutePath)
        ) {
            @unlink($absolutePath);
        }
    }
}

$usersListUrl = saCreateUserResolveListUrl(
    $projectRoot,
    $baseUrl
);

$csrfToken = saCreateUserCsrfToken();
$hasDepartmentColumn = saCreateUserColumnExists(
    $pdo,
    'users',
    'department'
);

$errors = [];
$old = [
    'name' => '',
    'username' => '',
    'email' => '',
    'mobile' => '',
    'role_id' => '',
    'tenant_id' => (string)(
        $currentUser['tenant_id']
        ?? $_SESSION['tenant_id']
        ?? 1
    ),
    'department' => '',
    'status' => 'active',
];

$tenants = [];
$roles = [];

try {
    if (!saCreateUserTableExists($pdo, 'users')) {
        throw new RuntimeException(
            'The users table is unavailable.'
        );
    }

    $tenantStatement = $pdo->query(
        "SELECT
            id,
            tenant_code,
            school_name,
            status
         FROM tenants
         WHERE status IN ('trial', 'active')
         ORDER BY school_name, id"
    );

    $tenants = $tenantStatement->fetchAll(
        PDO::FETCH_ASSOC
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

    $roles = $roleStatement->fetchAll(
        PDO::FETCH_ASSOC
    );
} catch (Throwable $exception) {
    error_log(
        'Create user form data failed: '
        . $exception->getMessage()
    );

    $errors[] = 'Unable to load schools and roles.';
}

if (
    ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
) {
    $old = [
        'name' => trim((string)($_POST['name'] ?? '')),
        'username' => trim((string)(
            $_POST['username']
            ?? ''
        )),
        'email' => strtolower(trim((string)(
            $_POST['email']
            ?? ''
        ))),
        'mobile' => trim((string)(
            $_POST['mobile']
            ?? ''
        )),
        'role_id' => trim((string)(
            $_POST['role_id']
            ?? ''
        )),
        'tenant_id' => trim((string)(
            $_POST['tenant_id']
            ?? ''
        )),
        'department' => trim((string)(
            $_POST['department']
            ?? ''
        )),
        'status' => strtolower(trim((string)(
            $_POST['status']
            ?? 'active'
        ))),
    ];

    $password = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)(
        $_POST['confirm_password']
        ?? ''
    );

    if (!saCreateUserCsrfValid(
        trim((string)($_POST['csrf_token'] ?? ''))
    )) {
        $errors[] = (
            'Your session token is invalid or expired. '
            . 'Refresh the page and try again.'
        );
    }

    if (
        mb_strlen($old['name']) < 2
        || mb_strlen($old['name']) > 150
    ) {
        $errors[] = (
            'Full Name must contain between '
            . '2 and 150 characters.'
        );
    }

    if (
        preg_match(
            '/^[A-Za-z0-9._-]{3,100}$/',
            $old['username']
        ) !== 1
    ) {
        $errors[] = (
            'Username must contain 3 to 100 letters, '
            . 'numbers, dots, underscores or hyphens.'
        );
    }

    if (
        $old['email'] === ''
        || filter_var(
            $old['email'],
            FILTER_VALIDATE_EMAIL
        ) === false
        || mb_strlen($old['email']) > 150
    ) {
        $errors[] = 'Enter a valid email address.';
    }

    if (
        $old['mobile'] !== ''
        && preg_match(
            '/^\+?[0-9][0-9\s-]{6,19}$/',
            $old['mobile']
        ) !== 1
    ) {
        $errors[] = 'Enter a valid mobile number.';
    }

    if (mb_strlen($password) < 8) {
        $errors[] = (
            'Password must contain at least '
            . '8 characters.'
        );
    }

    if (
        $password !== ''
        && (
            preg_match('/[A-Z]/', $password) !== 1
            || preg_match('/[a-z]/', $password) !== 1
            || preg_match('/[0-9]/', $password) !== 1
        )
    ) {
        $errors[] = (
            'Password must contain an uppercase letter, '
            . 'a lowercase letter and a number.'
        );
    }

    if (!hash_equals($password, $confirmPassword)) {
        $errors[] = 'Password confirmation does not match.';
    }

    $selectedTenantId = filter_var(
        $old['tenant_id'],
        FILTER_VALIDATE_INT,
        [
            'options' => [
                'min_range' => 1,
            ],
        ]
    );

    $selectedRoleId = filter_var(
        $old['role_id'],
        FILTER_VALIDATE_INT,
        [
            'options' => [
                'min_range' => 1,
            ],
        ]
    );

    if ($selectedTenantId === false) {
        $errors[] = 'Select a valid school.';
    }

    if ($selectedRoleId === false) {
        $errors[] = 'Select a valid role.';
    }

    if (!in_array(
        $old['status'],
        ['active', 'inactive'],
        true
    )) {
        $errors[] = 'Select a valid user status.';
    }

    if (mb_strlen($old['department']) > 120) {
        $errors[] = (
            'Department cannot exceed 120 characters.'
        );
    }

    if (
        $old['department'] !== ''
        && !$hasDepartmentColumn
    ) {
        $errors[] = (
            'The users.department column is missing. '
            . 'Run the supplied database migration first.'
        );
    }

    $selectedRole = null;
    $selectedTenant = null;

    if (
        !$errors
        && $selectedTenantId !== false
        && $selectedRoleId !== false
    ) {
        try {
            $tenantValidation = $pdo->prepare(
                "SELECT id, school_name
                 FROM tenants
                 WHERE id = :tenant_id
                   AND status IN ('trial', 'active')
                 LIMIT 1"
            );

            $tenantValidation->execute([
                'tenant_id' => $selectedTenantId,
            ]);

            $selectedTenant = $tenantValidation->fetch(
                PDO::FETCH_ASSOC
            );

            if (!$selectedTenant) {
                $errors[] = (
                    'The selected school is unavailable.'
                );
            }

            $roleValidation = $pdo->prepare(
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

            $roleValidation->execute([
                'role_id' => $selectedRoleId,
            ]);

            $selectedRole = $roleValidation->fetch(
                PDO::FETCH_ASSOC
            );

            if (!$selectedRole) {
                $errors[] = (
                    'The selected role is unavailable.'
                );
            } elseif (
                $selectedRole['tenant_id'] !== null
                && (int)$selectedRole['tenant_id']
                    !== (int)$selectedTenantId
            ) {
                $errors[] = (
                    'The selected role does not belong '
                    . 'to the selected school.'
                );
            }
        } catch (Throwable $exception) {
            error_log(
                'Create user tenant/role validation failed: '
                . $exception->getMessage()
            );

            $errors[] = (
                'Unable to validate the selected '
                . 'school and role.'
            );
        }
    }

    if (
        !$errors
        && $selectedTenantId !== false
    ) {
        try {
            $duplicateStatement = $pdo->prepare(
                "SELECT username, email
                 FROM users
                 WHERE tenant_id = :tenant_id
                   AND (
                       username = :username
                       OR email = :email
                   )
                 LIMIT 1"
            );

            $duplicateStatement->execute([
                'tenant_id' => $selectedTenantId,
                'username' => $old['username'],
                'email' => $old['email'],
            ]);

            $duplicateUser = $duplicateStatement->fetch(
                PDO::FETCH_ASSOC
            );

            if ($duplicateUser) {
                if (
                    strcasecmp(
                        (string)$duplicateUser['username'],
                        $old['username']
                    ) === 0
                ) {
                    $errors[] = (
                        'This username is already used '
                        . 'in the selected school.'
                    );
                }

                if (
                    strcasecmp(
                        (string)$duplicateUser['email'],
                        $old['email']
                    ) === 0
                ) {
                    $errors[] = (
                        'This email address is already used '
                        . 'in the selected school.'
                    );
                }
            }
        } catch (Throwable $exception) {
            error_log(
                'Create user duplicate check failed: '
                . $exception->getMessage()
            );

            $errors[] = (
                'Unable to verify username and email '
                . 'availability.'
            );
        }
    }

    $profilePhotoRelativePath = null;
    $profilePhotoAbsolutePath = null;

    if (
        !$errors
        && isset($_FILES['profile_photo'])
        && is_array($_FILES['profile_photo'])
        && (int)($_FILES['profile_photo']['error'] ?? UPLOAD_ERR_NO_FILE)
            !== UPLOAD_ERR_NO_FILE
    ) {
        $upload = $_FILES['profile_photo'];
        $uploadError = (int)(
            $upload['error']
            ?? UPLOAD_ERR_NO_FILE
        );

        if ($uploadError !== UPLOAD_ERR_OK) {
            $errors[] = 'Profile photo upload failed.';
        } elseif (
            (int)($upload['size'] ?? 0) <= 0
            || (int)$upload['size'] > 2 * 1024 * 1024
        ) {
            $errors[] = (
                'Profile photo must be smaller than 2 MB.'
            );
        } elseif (
            !isset($upload['tmp_name'])
            || !is_uploaded_file(
                (string)$upload['tmp_name']
            )
        ) {
            $errors[] = 'Invalid profile photo upload.';
        } else {
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
                $errors[] = (
                    'Profile photo must be JPG, PNG or WebP.'
                );
            } else {
                $uploadRelativeDirectory = (
                    'uploads/users/'
                    . (int)$selectedTenantId
                );

                $uploadAbsoluteDirectory = (
                    $projectRoot
                    . '/'
                    . $uploadRelativeDirectory
                );

                if (
                    !is_dir($uploadAbsoluteDirectory)
                    && !mkdir(
                        $uploadAbsoluteDirectory,
                        0755,
                        true
                    )
                    && !is_dir($uploadAbsoluteDirectory)
                ) {
                    $errors[] = (
                        'Unable to create the profile '
                        . 'photo directory.'
                    );
                } else {
                    $fileName = (
                        'user_'
                        . bin2hex(random_bytes(16))
                        . '.'
                        . $allowedImages[$mimeType]
                    );

                    $profilePhotoRelativePath = (
                        $uploadRelativeDirectory
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
                        $profilePhotoRelativePath = null;
                        $profilePhotoAbsolutePath = null;

                        $errors[] = (
                            'Unable to save the profile photo.'
                        );
                    }
                }
            }
        }
    }

    if (
        !$errors
        && $selectedTenantId !== false
        && $selectedRoleId !== false
    ) {
        try {
            $defaultBranchId = null;

            if (saCreateUserTableExists($pdo, 'branches')) {
                $branchStatement = $pdo->prepare(
                    "SELECT id
                     FROM branches
                     WHERE tenant_id = :tenant_id
                       AND status = 'active'
                     ORDER BY is_main DESC, id
                     LIMIT 1"
                );

                $branchStatement->execute([
                    'tenant_id' => $selectedTenantId,
                ]);

                $branchValue = $branchStatement->fetchColumn();

                if ($branchValue !== false) {
                    $defaultBranchId = (int)$branchValue;
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
                throw new RuntimeException(
                    'Password hashing failed.'
                );
            }

            $columns = [
                'tenant_id',
                'default_branch_id',
                'role_id',
                'name',
                'email',
                'mobile',
                'username',
                'password_hash',
                'profile_photo',
                'status',
            ];

            $placeholders = [
                ':tenant_id',
                ':default_branch_id',
                ':role_id',
                ':name',
                ':email',
                ':mobile',
                ':username',
                ':password_hash',
                ':profile_photo',
                ':status',
            ];

            $parameters = [
                'tenant_id' => $selectedTenantId,
                'default_branch_id' => $defaultBranchId,
                'role_id' => $selectedRoleId,
                'name' => $old['name'],
                'email' => $old['email'],
                'mobile' => $old['mobile'] !== ''
                    ? $old['mobile']
                    : null,
                'username' => $old['username'],
                'password_hash' => $passwordHash,
                'profile_photo' => $profilePhotoRelativePath,
                'status' => $old['status'],
            ];

            if ($hasDepartmentColumn) {
                $columns[] = 'department';
                $placeholders[] = ':department';
                $parameters['department'] = (
                    $old['department'] !== ''
                        ? $old['department']
                        : null
                );
            }

            $pdo->beginTransaction();

            $insertStatement = $pdo->prepare(
                "INSERT INTO users ("
                . implode(', ', $columns)
                . ") VALUES ("
                . implode(', ', $placeholders)
                . ")"
            );

            $insertStatement->execute($parameters);

            $newUserId = (int)$pdo->lastInsertId();

            if (
                $defaultBranchId !== null
                && saCreateUserTableExists(
                    $pdo,
                    'user_branch_access'
                )
            ) {
                $branchAccessStatement = $pdo->prepare(
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

                $branchAccessStatement->execute([
                    'user_id' => $newUserId,
                    'branch_id' => $defaultBranchId,
                ]);
            }

            if (saCreateUserTableExists(
                $pdo,
                'activity_logs'
            )) {
                $creatorUserId = (int)(
                    $currentUser['id']
                    ?? $currentUser['user_id']
                    ?? $_SESSION['user_id']
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
                    'tenant_id' => $selectedTenantId,
                    'branch_id' => $defaultBranchId,
                    'user_id' => $creatorUserId > 0
                        ? $creatorUserId
                        : null,
                    'role_id' => $currentRoleId > 0
                        ? $currentRoleId
                        : null,
                    'record_id' => $newUserId,
                    'new_values' => json_encode(
                        [
                            'name' => $old['name'],
                            'username' => $old['username'],
                            'email' => $old['email'],
                            'role_id' => $selectedRoleId,
                            'tenant_id' => $selectedTenantId,
                            'status' => $old['status'],
                        ],
                        JSON_UNESCAPED_UNICODE
                        | JSON_UNESCAPED_SLASHES
                    ),
                    'description' => (
                        'Created user '
                        . $old['username']
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

            $_SESSION['flash_success'] = (
                'User created successfully.'
            );

            $_SESSION['success_message'] = (
                'User created successfully.'
            );

            $separator = str_contains(
                $usersListUrl,
                '?'
            ) ? '&' : '?';

            header(
                'Location: '
                . $usersListUrl
                . $separator
                . 'created=1'
            );

            exit;
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            saCreateUserDeleteFile(
                $profilePhotoAbsolutePath
            );

            error_log(
                'Create user database error: '
                . $exception->getMessage()
            );

            if (
                (string)$exception->getCode() === '23000'
                || (int)($exception->errorInfo[1] ?? 0)
                    === 1062
            ) {
                $errors[] = (
                    'The username or email address '
                    . 'already exists.'
                );
            } else {
                $errors[] = (
                    'Unable to create the user. '
                    . 'Check the PHP error log.'
                );
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            saCreateUserDeleteFile(
                $profilePhotoAbsolutePath
            );

            error_log(
                'Create user failed: '
                . $exception->getMessage()
            );

            $errors[] = (
                'Unable to create the user. '
                . 'Check the PHP error log.'
            );
        }
    } elseif ($errors) {
        saCreateUserDeleteFile(
            $profilePhotoAbsolutePath
        );
    }
}

require $projectRoot . '/includes/layout-start.php';
?>

<style>
.user-create-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 310px;
    gap: 16px;
}

.user-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

.user-form-field.full {
    grid-column: 1 / -1;
}

.user-form-field label {
    display: block;
    margin-bottom: 7px;
    font-size: 11px;
    font-weight: 750;
}

.user-required {
    color: var(--danger-color, #dc3545);
}

.user-form-help {
    display: block;
    margin-top: 6px;
    color: var(--text-muted, #64748b);
    font-size: 9px;
    line-height: 1.5;
}

.user-photo-box {
    display: grid;
    gap: 14px;
    justify-items: center;
    padding: 18px;
    border: 1px dashed var(--border-soft, #e7ebf3);
    border-radius: 13px;
    background: var(--body-bg, #f6f8fc);
    text-align: center;
}

.user-photo-preview {
    width: 112px;
    height: 112px;
    display: grid;
    place-items: center;
    overflow: hidden;
    border-radius: 50%;
    color: var(--brand-1, #6747e8);
    background: var(--card-bg, #ffffff);
    border: 1px solid var(--border-soft, #e7ebf3);
}

.user-photo-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.user-photo-preview svg {
    width: 38px;
    height: 38px;
}

.user-security-list {
    display: grid;
    gap: 10px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.user-security-list li {
    display: flex;
    align-items: flex-start;
    gap: 9px;
    color: var(--text-muted, #64748b);
    font-size: 10px;
    line-height: 1.5;
}

.user-security-list svg {
    width: 15px;
    min-width: 15px;
    margin-top: 1px;
    color: var(--success-color, #21ae71);
}

.user-submit-row {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding-top: 4px;
}

.user-error-list {
    margin: 0;
    padding-left: 18px;
}

.user-error-list li + li {
    margin-top: 4px;
}

@media (max-width: 991.98px) {
    .user-create-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 767.98px) {
    .user-form-grid {
        grid-template-columns: 1fr;
    }

    .user-form-field.full {
        grid-column: auto;
    }
}

@media (max-width: 575.98px) {
    .user-submit-row {
        flex-direction: column-reverse;
    }

    .user-submit-row .btn-ui {
        width: 100%;
        justify-content: center;
    }
}
</style>

<div class="page-heading">
    <div>
        <h1 class="page-title">Create User</h1>
        <p class="page-subtitle">
            Add a user and assign the appropriate school and role.
        </p>
    </div>

    <div class="page-actions">
        <a
            class="btn-ui"
            href="<?= e($usersListUrl) ?>"
        >
            <i data-lucide="arrow-left"></i>
            Users List
        </a>
    </div>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger" role="alert">
        <strong>Unable to create the user.</strong>

        <ul class="user-error-list mt-2">
            <?php foreach (array_unique($errors) as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (!$hasDepartmentColumn): ?>
    <div class="alert alert-warning" role="alert">
        Run the supplied user-module database migration to enable
        Department storage and database-level email uniqueness.
    </div>
<?php endif; ?>

<form
    id="createUserForm"
    method="post"
    enctype="multipart/form-data"
    novalidate
>
    <input
        type="hidden"
        name="csrf_token"
        value="<?= e($csrfToken) ?>"
    >

    <div class="user-create-grid">
        <article class="ui-card">
            <div class="ui-card-header">
                <h2 class="ui-card-title">User Information</h2>
                <i data-lucide="user-plus"></i>
            </div>

            <div class="ui-card-body">
                <div class="user-form-grid">
                    <div class="user-form-field">
                        <label for="name">
                            Full Name
                            <span class="user-required">*</span>
                        </label>

                        <input
                            id="name"
                            name="name"
                            class="form-control"
                            type="text"
                            minlength="2"
                            maxlength="150"
                            value="<?= e($old['name']) ?>"
                            autocomplete="name"
                            required
                        >

                        <div class="invalid-feedback">
                            Enter the user's full name.
                        </div>
                    </div>

                    <div class="user-form-field">
                        <label for="username">
                            Username
                            <span class="user-required">*</span>
                        </label>

                        <input
                            id="username"
                            name="username"
                            class="form-control"
                            type="text"
                            minlength="3"
                            maxlength="100"
                            pattern="[A-Za-z0-9._-]{3,100}"
                            value="<?= e($old['username']) ?>"
                            autocomplete="username"
                            required
                        >

                        <small class="user-form-help">
                            Letters, numbers, dots, underscores and hyphens.
                        </small>

                        <div class="invalid-feedback">
                            Enter a valid username.
                        </div>
                    </div>

                    <div class="user-form-field">
                        <label for="email">
                            Email Address
                            <span class="user-required">*</span>
                        </label>

                        <input
                            id="email"
                            name="email"
                            class="form-control"
                            type="email"
                            maxlength="150"
                            value="<?= e($old['email']) ?>"
                            autocomplete="email"
                            required
                        >

                        <div class="invalid-feedback">
                            Enter a valid email address.
                        </div>
                    </div>

                    <div class="user-form-field">
                        <label for="mobile">
                            Mobile Number
                        </label>

                        <input
                            id="mobile"
                            name="mobile"
                            class="form-control"
                            type="tel"
                            maxlength="20"
                            value="<?= e($old['mobile']) ?>"
                            autocomplete="tel"
                            placeholder="+91 98765 43210"
                        >

                        <div class="invalid-feedback">
                            Enter a valid mobile number.
                        </div>
                    </div>

                    <div class="user-form-field">
                        <label for="password">
                            Password
                            <span class="user-required">*</span>
                        </label>

                        <input
                            id="password"
                            name="password"
                            class="form-control"
                            type="password"
                            minlength="8"
                            maxlength="128"
                            autocomplete="new-password"
                            required
                        >

                        <small class="user-form-help">
                            Minimum 8 characters with uppercase,
                            lowercase and a number.
                        </small>

                        <div class="invalid-feedback">
                            Enter a strong password.
                        </div>
                    </div>

                    <div class="user-form-field">
                        <label for="confirm_password">
                            Confirm Password
                            <span class="user-required">*</span>
                        </label>

                        <input
                            id="confirm_password"
                            name="confirm_password"
                            class="form-control"
                            type="password"
                            minlength="8"
                            maxlength="128"
                            autocomplete="new-password"
                            required
                        >

                        <div class="invalid-feedback">
                            Password confirmation must match.
                        </div>
                    </div>

                    <div class="user-form-field">
                        <label for="tenant_id">
                            School
                            <span class="user-required">*</span>
                        </label>

                        <select
                            id="tenant_id"
                            name="tenant_id"
                            class="form-select"
                            required
                        >
                            <option value="">
                                Select school
                            </option>

                            <?php foreach ($tenants as $tenant): ?>
                                <option
                                    value="<?= (int)$tenant['id'] ?>"
                                    <?= (int)$old['tenant_id']
                                        === (int)$tenant['id']
                                            ? 'selected'
                                            : '' ?>
                                >
                                    <?= e($tenant['school_name']) ?>
                                    (<?= e($tenant['tenant_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <div class="invalid-feedback">
                            Select a school.
                        </div>
                    </div>

                    <div class="user-form-field">
                        <label for="role_id">
                            Role
                            <span class="user-required">*</span>
                        </label>

                        <select
                            id="role_id"
                            name="role_id"
                            class="form-select"
                            required
                        >
                            <option value="">
                                Select role
                            </option>

                            <?php foreach ($roles as $role): ?>
                                <option
                                    value="<?= (int)$role['id'] ?>"
                                    data-tenant-id="<?= $role['tenant_id'] !== null
                                        ? (int)$role['tenant_id']
                                        : '' ?>"
                                    <?= (int)$old['role_id']
                                        === (int)$role['id']
                                            ? 'selected'
                                            : '' ?>
                                >
                                    <?= e($role['role_name']) ?>
                                    (<?= e($role['role_key']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <div class="invalid-feedback">
                            Select a role available for the school.
                        </div>
                    </div>

                    <div class="user-form-field">
                        <label for="department">
                            Department
                        </label>

                        <input
                            id="department"
                            name="department"
                            class="form-control"
                            type="text"
                            maxlength="120"
                            value="<?= e($old['department']) ?>"
                            placeholder="Administration, Accounts, Science..."
                        >

                        <small class="user-form-help">
                            Optional.
                        </small>
                    </div>

                    <div class="user-form-field">
                        <label for="status">
                            Status
                            <span class="user-required">*</span>
                        </label>

                        <select
                            id="status"
                            name="status"
                            class="form-select"
                            required
                        >
                            <option
                                value="active"
                                <?= $old['status'] === 'active'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Active
                            </option>

                            <option
                                value="inactive"
                                <?= $old['status'] === 'inactive'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Inactive
                            </option>
                        </select>
                    </div>

                    <div class="user-form-field full">
                        <div class="user-submit-row">
                            <a
                                href="<?= e($usersListUrl) ?>"
                                class="btn-ui"
                            >
                                Cancel
                            </a>

                            <button
                                id="submitButton"
                                class="btn-ui btn-primary-ui"
                                type="submit"
                            >
                                <i data-lucide="user-plus"></i>
                                Create User
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </article>

        <div>
            <article class="ui-card">
                <div class="ui-card-header">
                    <h2 class="ui-card-title">Profile Photo</h2>
                    <i data-lucide="image-up"></i>
                </div>

                <div class="ui-card-body">
                    <div class="user-photo-box">
                        <div
                            id="photoPreview"
                            class="user-photo-preview"
                        >
                            <i data-lucide="user-round"></i>
                        </div>

                        <div>
                            <label
                                for="profile_photo"
                                class="btn-ui"
                            >
                                <i data-lucide="upload"></i>
                                Choose Photo
                            </label>

                            <input
                                id="profile_photo"
                                name="profile_photo"
                                class="d-none"
                                type="file"
                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                            >
                        </div>

                        <small class="user-form-help">
                            JPG, PNG or WebP. Maximum file size: 2 MB.
                        </small>
                    </div>
                </div>
            </article>

            <article class="ui-card mt-3">
                <div class="ui-card-header">
                    <h2 class="ui-card-title">Security</h2>
                    <i data-lucide="shield-check"></i>
                </div>

                <div class="ui-card-body">
                    <ul class="user-security-list">
                        <li>
                            <i data-lucide="check-circle-2"></i>
                            Username and email are checked before saving.
                        </li>
                        <li>
                            <i data-lucide="check-circle-2"></i>
                            Passwords are stored using BCRYPT hashing.
                        </li>
                        <li>
                            <i data-lucide="check-circle-2"></i>
                            The selected role is validated against the school.
                        </li>
                        <li>
                            <i data-lucide="check-circle-2"></i>
                            Profile photos are type and size validated.
                        </li>
                    </ul>
                </div>
            </article>
        </div>
    </div>
</form>

<script>
(function () {
    'use strict';

    const form = document.getElementById('createUserForm');
    const schoolSelect = document.getElementById('tenant_id');
    const roleSelect = document.getElementById('role_id');
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById(
        'confirm_password'
    );
    const mobile = document.getElementById('mobile');
    const photoInput = document.getElementById('profile_photo');
    const photoPreview = document.getElementById('photoPreview');
    const submitButton = document.getElementById('submitButton');

    function filterRoles() {
        const tenantId = schoolSelect.value;
        let selectedIsAvailable = false;

        Array.from(roleSelect.options).forEach(option => {
            if (option.value === '') {
                option.hidden = false;
                option.disabled = false;
                return;
            }

            const roleTenantId = option.dataset.tenantId || '';
            const available = roleTenantId === ''
                || roleTenantId === tenantId;

            option.hidden = !available;
            option.disabled = !available;

            if (option.selected && available) {
                selectedIsAvailable = true;
            }
        });

        if (!selectedIsAvailable) {
            roleSelect.value = '';
        }
    }

    function validatePasswordConfirmation() {
        if (
            confirmPassword.value !== ''
            && password.value !== confirmPassword.value
        ) {
            confirmPassword.setCustomValidity(
                'Password confirmation does not match.'
            );
        } else {
            confirmPassword.setCustomValidity('');
        }
    }

    function validateStrongPassword() {
        const value = password.value;
        const isStrong = value === ''
            || (
                value.length >= 8
                && /[A-Z]/.test(value)
                && /[a-z]/.test(value)
                && /[0-9]/.test(value)
            );

        password.setCustomValidity(
            isStrong
                ? ''
                : 'Use uppercase, lowercase and a number.'
        );

        validatePasswordConfirmation();
    }

    function validateMobile() {
        const value = mobile.value.trim();

        mobile.setCustomValidity(
            value === ''
            || /^\+?[0-9][0-9\s-]{6,19}$/.test(value)
                ? ''
                : 'Enter a valid mobile number.'
        );
    }

    schoolSelect.addEventListener('change', filterRoles);
    password.addEventListener('input', validateStrongPassword);
    confirmPassword.addEventListener(
        'input',
        validatePasswordConfirmation
    );
    mobile.addEventListener('input', validateMobile);

    photoInput.addEventListener('change', () => {
        const file = photoInput.files
            ? photoInput.files[0]
            : null;

        if (!file) {
            photoPreview.innerHTML =
                '<i data-lucide="user-round"></i>';

            if (window.lucide) {
                window.lucide.createIcons();
            }

            return;
        }

        const allowedTypes = [
            'image/jpeg',
            'image/png',
            'image/webp'
        ];

        if (
            !allowedTypes.includes(file.type)
            || file.size > 2 * 1024 * 1024
        ) {
            photoInput.value = '';
            photoPreview.innerHTML =
                '<i data-lucide="user-round"></i>';
            photoInput.setCustomValidity(
                'Choose a JPG, PNG or WebP file below 2 MB.'
            );

            if (window.lucide) {
                window.lucide.createIcons();
            }

            return;
        }

        photoInput.setCustomValidity('');

        const reader = new FileReader();

        reader.addEventListener('load', event => {
            const image = document.createElement('img');
            image.src = String(event.target.result || '');
            image.alt = 'Profile preview';
            photoPreview.replaceChildren(image);
        });

        reader.readAsDataURL(file);
    });

    form.addEventListener('submit', event => {
        validateStrongPassword();
        validatePasswordConfirmation();
        validateMobile();

        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        } else {
            submitButton.disabled = true;
        }

        form.classList.add('was-validated');
    });

    filterRoles();
})();
</script>

<?php require $projectRoot . '/includes/layout-end.php'; ?>

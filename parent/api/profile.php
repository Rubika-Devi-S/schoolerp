<?php
declare(strict_types=1);

/*
 * Parent Profile API
 * Location: parent/api/profile.php
 * Build: 2026-08-13-parent-profile-v40
 *
 * Actions:
 * - GET  action=profile
 * - POST action=save_profile
 * - POST action=change_password
 *
 * Updates existing users / guardians rows only.
 * Does not create Parent, Guardian or Student records.
 */

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/sidebar-manager.php';
require_once dirname(__DIR__, 2) . '/includes/permission-chain.php';

if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('Asia/Kolkata');
}

function pdJson(bool $success, string $message = '', array $data = [], int $status = 200): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }

    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}

function pdUser(): array
{
    $user = function_exists('current_user') ? current_user() : [];
    return is_array($user) ? $user : [];
}

function pdScope(): array
{
    $user = pdUser();

    return [
        'tenant_id' => (int)(
            $user['tenant_id']
            ?? $user['school_id']
            ?? $_SESSION['tenant_id']
            ?? $_SESSION['school_id']
            ?? 0
        ),
        'branch_id' => (int)(
            $user['default_branch_id']
            ?? $user['branch_id']
            ?? $_SESSION['branch_id']
            ?? 0
        ),
        'user_id' => (int)(
            $user['id']
            ?? $user['user_id']
            ?? $_SESSION['user_id']
            ?? 0
        ),
        'email' => trim((string)(
            $user['email']
            ?? $_SESSION['email']
            ?? $_SESSION['user_email']
            ?? ''
        )),
        'mobile' => trim((string)(
            $user['mobile']
            ?? $_SESSION['mobile']
            ?? $_SESSION['user_mobile']
            ?? ''
        )),
    ];
}

function pdTableExists(PDO $pdo, string $table): bool
{
    static $cache = [];

    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = :table_name'
    );
    $stmt->execute(['table_name' => $table]);

    return $cache[$table] = ((int)$stmt->fetchColumn() > 0);
}

function pdColumns(PDO $pdo, string $table): array
{
    static $cache = [];

    if (isset($cache[$table])) {
        return $cache[$table];
    }

    if (!pdTableExists($pdo, $table)) {
        return $cache[$table] = [];
    }

    $quoted = str_replace('`', '``', $table);
    $rows = $pdo->query("SHOW COLUMNS FROM `{$quoted}`")->fetchAll(PDO::FETCH_ASSOC);

    $columns = [];
    foreach ($rows as $row) {
        $columns[] = (string)$row['Field'];
    }

    return $cache[$table] = $columns;
}

function pdMobile(string $value): string
{
    return preg_replace('/\D+/', '', $value) ?? '';
}

function pdGuardian(PDO $pdo, array $scope): array
{
    $tenantId=(int)$scope['tenant_id'];$userId=(int)$scope['user_id'];
    if($tenantId<=0)throw new RuntimeException('School session was not found.',401);

    if($userId>0&&pdTableExists($pdo,'student_parent_logins')){
        $q=$pdo->prepare(
            "SELECT DISTINCT g.id,g.tenant_id,g.guardian_name,g.relationship,g.mobile,g.email,g.occupation,g.address
             FROM student_parent_logins spl
             INNER JOIN guardians g ON g.id=spl.guardian_id AND g.tenant_id=spl.tenant_id
             WHERE spl.tenant_id=:tenant_id AND spl.user_id=:user_id AND spl.status='active'
             ORDER BY spl.id LIMIT 1"
        );
        $q->execute(['tenant_id'=>$tenantId,'user_id'=>$userId]);
        if($g=$q->fetch(PDO::FETCH_ASSOC)){$_SESSION['guardian_id']=(int)$g['id'];return $g;}
    }

    if($userId>0&&in_array('user_id',pdColumns($pdo,'guardians'),true)){
        $q=$pdo->prepare(
            "SELECT id,tenant_id,guardian_name,relationship,mobile,email,occupation,address
             FROM guardians WHERE tenant_id=:tenant_id AND user_id=:user_id ORDER BY id LIMIT 1"
        );
        $q->execute(['tenant_id'=>$tenantId,'user_id'=>$userId]);
        if($g=$q->fetch(PDO::FETCH_ASSOC)){$_SESSION['guardian_id']=(int)$g['id'];return $g;}
    }

    $guardianId=(int)($_SESSION['guardian_id']??$_SESSION['parent_guardian_id']??$_SESSION['parent_id']??0);
    if($guardianId>0){
        $q=$pdo->prepare(
            "SELECT id,tenant_id,guardian_name,relationship,mobile,email,occupation,address
             FROM guardians WHERE id=:id AND tenant_id=:tenant_id LIMIT 1"
        );
        $q->execute(['id'=>$guardianId,'tenant_id'=>$tenantId]);
        if($g=$q->fetch(PDO::FETCH_ASSOC))return $g;
    }

    $email=strtolower(trim((string)$scope['email']));$mobile=pdMobile((string)$scope['mobile']);
    if($email===''&&$mobile==='')throw new RuntimeException('This login is not linked to a Parent/Guardian record.',403);

    $q=$pdo->prepare(
        "SELECT id,tenant_id,guardian_name,relationship,mobile,email,occupation,address
         FROM guardians WHERE tenant_id=:tenant_id ORDER BY id"
    );
    $q->execute(['tenant_id'=>$tenantId]);
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $g){
        $ge=strtolower(trim((string)($g['email']??'')));$gm=pdMobile((string)($g['mobile']??''));
        if(($email!==''&&$ge!==''&&$ge===$email)||($mobile!==''&&$gm!==''&&$gm===$mobile)){
            $_SESSION['guardian_id']=(int)$g['id'];return $g;
        }
    }
    throw new RuntimeException('No Parent/Guardian record matches this login.',403);
}

function pdMappedStudentIds(PDO $pdo,int $tenantId,int $userId): array
{
    if($tenantId<=0||$userId<=0||!pdTableExists($pdo,'student_parent_logins'))return [];
    $q=$pdo->prepare("SELECT DISTINCT student_id FROM student_parent_logins
        WHERE tenant_id=:tenant_id AND user_id=:user_id AND status='active'");
    $q->execute(['tenant_id'=>$tenantId,'user_id'=>$userId]);
    return array_values(array_filter(array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN)),fn(int $id)=>$id>0));
}

function pdParentMenuKeys(PDO $pdo,array $scope): array
{
    $u=pdUser();$roleId=(int)($u['role_id']??$_SESSION['role_id']??0);
    if($roleId<=0)return [];
    $items=school_sidebar_get_items($pdo,$roleId,(int)$scope['tenant_id']);
    return array_values(array_unique(array_filter(array_map(
        fn(array $i)=>(string)($i['menu_key']??''),$items
    ))));
}

function pdChildren(PDO $pdo, int $tenantId, int $guardianId, int $userId = 0): array
{
    $stmt = $pdo->prepare(
        "SELECT
            s.id,
            s.tenant_id,
            s.branch_id,
            s.admission_no,
            s.emis_no,
            TRIM(CONCAT_WS(' ', s.first_name, s.last_name)) AS student_name,
            s.first_name,
            s.last_name,
            s.gender,
            s.date_of_birth,
            s.blood_group,
            s.mobile,
            s.email,
            s.address,
            s.photo_path,
            s.admission_date,
            s.status,
            sg.is_primary,
            se.id AS enrollment_id,
            se.academic_year_id,
            ay.year_name AS academic_year_name,
            ay.start_date AS academic_year_start,
            ay.end_date AS academic_year_end,
            se.class_id,
            c.class_name,
            se.section_id,
            sec.section_name,
            se.roll_no,
            se.enrollment_status,
            se.enrolled_on,
            spe.notes AS profile_notes
         FROM student_guardians sg
         INNER JOIN students s
            ON s.id = sg.student_id
           AND s.tenant_id = :tenant_id
         LEFT JOIN student_enrollments se
            ON se.id = (
                SELECT se2.id
                FROM student_enrollments se2
                INNER JOIN academic_years ay2
                   ON ay2.id = se2.academic_year_id
                  AND ay2.tenant_id = se2.tenant_id
                WHERE se2.tenant_id = s.tenant_id
                  AND se2.student_id = s.id
                ORDER BY
                    ay2.start_date DESC,
                    FIELD(se2.enrollment_status, 'active', 'promoted', 'transferred', 'completed'),
                    se2.id DESC
                LIMIT 1
            )
         LEFT JOIN academic_years ay
            ON ay.id = se.academic_year_id
           AND ay.tenant_id = s.tenant_id
         LEFT JOIN classes c
            ON c.id = se.class_id
           AND c.tenant_id = s.tenant_id
         LEFT JOIN sections sec
            ON sec.id = se.section_id
           AND sec.tenant_id = s.tenant_id
         LEFT JOIN student_profile_extras spe
            ON spe.tenant_id = s.tenant_id
           AND spe.student_id = s.id
         WHERE sg.guardian_id = :guardian_id
           AND s.deleted_at IS NULL
         ORDER BY sg.is_primary DESC, s.first_name, s.last_name, s.id"
    );
    $stmt->execute([
        'tenant_id' => $tenantId,
        'guardian_id' => $guardianId,
    ]);

    $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    $mapped=pdMappedStudentIds($pdo,$tenantId,$userId);
    if($mapped!==[]){
        $allowed=array_fill_keys($mapped,true);
        $rows=array_values(array_filter($rows,fn(array $row)=>isset($allowed[(int)($row['id']??0)])));
    }
    return $rows;
}

function pdSelectedChild(array $children, int $requestedStudentId): array
{
    if (!$children) {
        throw new RuntimeException(
            'No students are linked to this Parent/Guardian account.',
            404
        );
    }

    if ($requestedStudentId > 0) {
        foreach ($children as $child) {
            if ((int)$child['id'] === $requestedStudentId) {
                $_SESSION['parent_selected_student_id'] = $requestedStudentId;
                return $child;
            }
        }

        throw new RuntimeException(
            'The selected student is not linked to this Parent/Guardian account.',
            403
        );
    }

    $sessionId = (int)($_SESSION['parent_selected_student_id'] ?? 0);
    if ($sessionId > 0) {
        foreach ($children as $child) {
            if ((int)$child['id'] === $sessionId) {
                return $child;
            }
        }
    }

    $_SESSION['parent_selected_student_id'] = (int)$children[0]['id'];
    return $children[0];
}

function ppInput(): array
{
    $json = json_decode(
        (string)file_get_contents('php://input'),
        true
    );

    return is_array($json)
        ? $json
        : $_POST;
}

function ppCsrfToken(): string
{
    if (
        empty($_SESSION['parent_profile_csrf'])
        || !is_string($_SESSION['parent_profile_csrf'])
    ) {
        $_SESSION['parent_profile_csrf'] =
            bin2hex(random_bytes(32));
    }

    return (string)$_SESSION['parent_profile_csrf'];
}

function ppRequireCsrf(array $input): void
{
    $sent = (string)(
        $input['csrf_token']
        ?? $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? ''
    );

    if (
        $sent === ''
        || !hash_equals(
            ppCsrfToken(),
            $sent
        )
    ) {
        throw new RuntimeException(
            'Your session token is invalid. Refresh the page and try again.',
            419
        );
    }
}

function ppUserRow(PDO $pdo, array $scope): array
{
    $userId = (int)$scope['user_id'];
    $tenantId = (int)$scope['tenant_id'];

    if ($userId <= 0 || $tenantId <= 0) {
        throw new RuntimeException(
            'Parent login session was not found.',
            401
        );
    }

    $columns = pdColumns($pdo, 'users');

    $where = [
        'id = :user_id',
        'tenant_id = :tenant_id',
    ];

    if (in_array('deleted_at', $columns, true)) {
        $where[] = 'deleted_at IS NULL';
    }

    $stmt = $pdo->prepare(
        "SELECT *
         FROM users
         WHERE " . implode(' AND ', $where) . "
         LIMIT 1"
    );

    $stmt->execute([
        'user_id' => $userId,
        'tenant_id' => $tenantId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new RuntimeException(
            'Parent login account was not found.',
            404
        );
    }

    return $row;
}

function ppCleanName(string $value): string
{
    return preg_replace(
        '/\s+/u',
        ' ',
        trim($value)
    ) ?? '';
}

function ppCleanMobile(string $value): string
{
    return preg_replace(
        '/\D+/',
        '',
        $value
    ) ?? '';
}

function ppValidateProfile(
    PDO $pdo,
    array $scope,
    array $input,
    array $user
): array {
    $name = ppCleanName(
        (string)($input['parent_name'] ?? '')
    );

    $username = trim(
        (string)($input['username'] ?? '')
    );

    $email = strtolower(
        trim((string)($input['email'] ?? ''))
    );

    $mobile = ppCleanMobile(
        (string)($input['mobile'] ?? '')
    );

    $occupation = ppCleanName(
        (string)($input['occupation'] ?? '')
    );

    $address = trim(
        (string)($input['address'] ?? '')
    );

    if ($name === '' || mb_strlen($name) < 2) {
        throw new InvalidArgumentException(
            'Enter a valid Parent Name.'
        );
    }

    if (mb_strlen($name) > 150) {
        throw new InvalidArgumentException(
            'Parent Name cannot exceed 150 characters.'
        );
    }

    if (
        $username === ''
        || !preg_match(
            '/^[A-Za-z0-9._-]{3,100}$/',
            $username
        )
    ) {
        throw new InvalidArgumentException(
            'Username must be 3 to 100 characters using letters, numbers, dot, underscore or hyphen.'
        );
    }

    if (
        $email !== ''
        && !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {
        throw new InvalidArgumentException(
            'Enter a valid Email Address.'
        );
    }

    if (
        $mobile !== ''
        && (
            strlen($mobile) < 10
            || strlen($mobile) > 15
        )
    ) {
        throw new InvalidArgumentException(
            'Enter a valid Mobile Number.'
        );
    }

    if (mb_strlen($occupation) > 150) {
        throw new InvalidArgumentException(
            'Occupation cannot exceed 150 characters.'
        );
    }

    if (mb_strlen($address) > 1000) {
        throw new InvalidArgumentException(
            'Address cannot exceed 1000 characters.'
        );
    }

    /*
     * Existing users module uses tenant-level username/email uniqueness.
     * Exclude this current Parent account.
     */
    $check = $pdo->prepare(
        "SELECT id, username, email
         FROM users
         WHERE tenant_id = :tenant_id
           AND id <> :user_id
           AND (
                LOWER(username) = LOWER(:username)
                OR (
                    :email_check <> ''
                    AND LOWER(COALESCE(email,'')) = LOWER(:email_value)
                )
           )
         LIMIT 1"
    );

    $check->execute([
        'tenant_id' => (int)$scope['tenant_id'],
        'user_id' => (int)$scope['user_id'],
        'username' => $username,
        'email_check' => $email,
        'email_value' => $email,
    ]);

    $duplicate = $check->fetch(PDO::FETCH_ASSOC);

    if ($duplicate) {
        if (
            strcasecmp(
                (string)($duplicate['username'] ?? ''),
                $username
            ) === 0
        ) {
            throw new InvalidArgumentException(
                'This username is already used in this school.'
            );
        }

        if (
            $email !== ''
            && strcasecmp(
                (string)($duplicate['email'] ?? ''),
                $email
            ) === 0
        ) {
            throw new InvalidArgumentException(
                'This email address is already used in this school.'
            );
        }
    }

    return [
        'parent_name' => $name,
        'username' => $username,
        'email' => $email,
        'mobile' => $mobile,
        'occupation' => $occupation,
        'address' => $address,
    ];
}

function ppProfilePayload(
    PDO $pdo,
    array $scope,
    array $guardian,
    array $children,
    array $user
): array {
    return [
        'profile' => [
            'guardian_id' =>
                (int)($guardian['id'] ?? 0),
            'user_id' =>
                (int)($user['id'] ?? 0),
            'parent_name' =>
                (string)(
                    $guardian['guardian_name']
                    ?? $user['name']
                    ?? ''
                ),
            'username' =>
                (string)($user['username'] ?? ''),
            'email' =>
                (string)(
                    $guardian['email']
                    ?? $user['email']
                    ?? ''
                ),
            'mobile' =>
                (string)(
                    $guardian['mobile']
                    ?? $user['mobile']
                    ?? ''
                ),
            'relationship' =>
                (string)($guardian['relationship'] ?? ''),
            'occupation' =>
                (string)($guardian['occupation'] ?? ''),
            'address' =>
                (string)($guardian['address'] ?? ''),
            'account_status' =>
                (string)($user['status'] ?? ''),
        ],
        'children' => array_values($children),
        'summary' => [
            'total_children' => count($children),
        ],
        'csrf_token' => ppCsrfToken(),
    ];
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    pdJson(
        false,
        'Database connection unavailable.',
        [],
        500
    );
}

try {
    if (
        session_status()
        !== PHP_SESSION_ACTIVE
    ) {
        session_start();
    }

    $scope = pdScope();
    $userSession = pdUser();

    $roleId = (int)(
        $userSession['role_id']
        ?? $_SESSION['role_id']
        ?? 0
    );

    $role = pc_role(
        $pdo,
        (int)$scope['tenant_id'],
        $roleId
    );

    if (
        pc_role_key(
            (string)($role['role_key'] ?? '')
        ) !== 'parent'
    ) {
        throw new RuntimeException(
            'Profile is available only to Parent accounts.',
            403
        );
    }

    $allowedMenus = pdParentMenuKeys(
        $pdo,
        $scope
    );

    if (
        !in_array(
            'parent_profile',
            $allowedMenus,
            true
        )
    ) {
        throw new RuntimeException(
            'Profile is disabled for this Parent account.',
            403
        );
    }

    $guardian = pdGuardian(
        $pdo,
        $scope
    );

    $children = pdChildren(
        $pdo,
        (int)$scope['tenant_id'],
        (int)$guardian['id'],
        (int)$scope['user_id']
    );

    $uniqueChildren = [];

    foreach ($children as $child) {
        $studentId = (int)($child['id'] ?? 0);

        if ($studentId > 0) {
            $uniqueChildren[$studentId] = $child;
        }
    }

    $children = array_values(
        $uniqueChildren
    );

    $account = ppUserRow(
        $pdo,
        $scope
    );

    $input = ppInput();

    $action = strtolower(
        trim(
            (string)(
                $input['action']
                ?? $_GET['action']
                ?? 'profile'
            )
        )
    );

    if ($action === 'profile') {
        pdJson(
            true,
            'Parent Profile loaded.',
            ppProfilePayload(
                $pdo,
                $scope,
                $guardian,
                $children,
                $account
            )
        );
    }

    if ($action === 'save_profile') {
        ppRequireCsrf($input);

        $values = ppValidateProfile(
            $pdo,
            $scope,
            $input,
            $account
        );

        $guardianColumns = pdColumns(
            $pdo,
            'guardians'
        );

        $userColumns = pdColumns(
            $pdo,
            'users'
        );

        $pdo->beginTransaction();

        try {
            $guardianSets = [];
            $guardianParams = [
                'guardian_id' =>
                    (int)$guardian['id'],
                'tenant_id' =>
                    (int)$scope['tenant_id'],
            ];

            $guardianMap = [
                'guardian_name' =>
                    $values['parent_name'],
                'email' =>
                    $values['email'] !== ''
                        ? $values['email']
                        : null,
                'mobile' =>
                    $values['mobile'] !== ''
                        ? $values['mobile']
                        : null,
                'occupation' =>
                    $values['occupation'] !== ''
                        ? $values['occupation']
                        : null,
                'address' =>
                    $values['address'] !== ''
                        ? $values['address']
                        : null,
            ];

            foreach (
                $guardianMap
                as $column => $value
            ) {
                if (
                    in_array(
                        $column,
                        $guardianColumns,
                        true
                    )
                ) {
                    $guardianSets[] =
                        "`{$column}` = :g_{$column}";

                    $guardianParams[
                        'g_' . $column
                    ] = $value;
                }
            }

            if ($guardianSets) {
                $stmt = $pdo->prepare(
                    "UPDATE guardians
                     SET " . implode(', ', $guardianSets) . "
                     WHERE id = :guardian_id
                       AND tenant_id = :tenant_id"
                );

                $stmt->execute(
                    $guardianParams
                );
            }

            /*
             * Update the same existing login user.
             * Email is synchronized only when this account already uses
             * users.email, preserving student-specific Parent accounts that
             * intentionally keep user email NULL.
             */
            $userSets = [];
            $userParams = [
                'user_id' =>
                    (int)$scope['user_id'],
                'tenant_id' =>
                    (int)$scope['tenant_id'],
            ];

            $userMap = [
                'name' =>
                    $values['parent_name'],
                'username' =>
                    $values['username'],
                'mobile' =>
                    $values['mobile'] !== ''
                        ? $values['mobile']
                        : null,
            ];

            if (
                in_array(
                    'email',
                    $userColumns,
                    true
                )
                && (
                    ($account['email'] ?? null)
                    !== null
                    && trim(
                        (string)($account['email'] ?? '')
                    ) !== ''
                )
            ) {
                $userMap['email'] =
                    $values['email'] !== ''
                        ? $values['email']
                        : null;
            }

            foreach (
                $userMap
                as $column => $value
            ) {
                if (
                    in_array(
                        $column,
                        $userColumns,
                        true
                    )
                ) {
                    $userSets[] =
                        "`{$column}` = :u_{$column}";

                    $userParams[
                        'u_' . $column
                    ] = $value;
                }
            }

            if (
                in_array(
                    'updated_at',
                    $userColumns,
                    true
                )
            ) {
                $userSets[] = 'updated_at = NOW()';
            }

            if ($userSets) {
                $userWhere =
                    'id = :user_id AND tenant_id = :tenant_id';

                if (
                    in_array(
                        'deleted_at',
                        $userColumns,
                        true
                    )
                ) {
                    $userWhere .=
                        ' AND deleted_at IS NULL';
                }

                $stmt = $pdo->prepare(
                    "UPDATE users
                     SET " . implode(', ', $userSets) . "
                     WHERE {$userWhere}"
                );

                $stmt->execute(
                    $userParams
                );
            }

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }

        /*
         * Keep current authenticated session in sync.
         */
        $_SESSION['name'] =
            $values['parent_name'];

        $_SESSION['username'] =
            $values['username'];

        $_SESSION['mobile'] =
            $values['mobile'];

        if (
            ($account['email'] ?? null)
            !== null
            && trim(
                (string)($account['email'] ?? '')
            ) !== ''
        ) {
            $_SESSION['email'] =
                $values['email'];
        }

        $guardian = pdGuardian(
            $pdo,
            $scope
        );

        $account = ppUserRow(
            $pdo,
            $scope
        );

        pdJson(
            true,
            'Profile updated successfully.',
            ppProfilePayload(
                $pdo,
                $scope,
                $guardian,
                $children,
                $account
            )
        );
    }

    if ($action === 'change_password') {
        ppRequireCsrf($input);

        $currentPassword = (string)(
            $input['current_password']
            ?? ''
        );

        $newPassword = (string)(
            $input['new_password']
            ?? ''
        );

        $confirmPassword = (string)(
            $input['confirm_password']
            ?? ''
        );

        if ($currentPassword === '') {
            throw new InvalidArgumentException(
                'Enter your current password.'
            );
        }

        if (
            strlen($newPassword) < 8
            || strlen($newPassword) > 72
            || !preg_match('/[A-Za-z]/', $newPassword)
            || !preg_match('/\d/', $newPassword)
        ) {
            throw new InvalidArgumentException(
                'New password must be 8 to 72 characters and contain at least one letter and one number.'
            );
        }

        if ($newPassword !== $confirmPassword) {
            throw new InvalidArgumentException(
                'New Password and Confirm Password do not match.'
            );
        }

        if (
            hash_equals(
                $currentPassword,
                $newPassword
            )
        ) {
            throw new InvalidArgumentException(
                'New password must be different from the current password.'
            );
        }

        $storedHash = trim(
            (string)(
                $account['password_hash']
                ?? ''
            )
        );

        if (
            $storedHash === ''
            || !password_verify(
                $currentPassword,
                $storedHash
            )
        ) {
            throw new InvalidArgumentException(
                'Current password is incorrect.'
            );
        }

        $newHash = password_hash(
            $newPassword,
            PASSWORD_BCRYPT,
            ['cost' => 12]
        );

        if ($newHash === false) {
            throw new RuntimeException(
                'Unable to secure the new password.'
            );
        }

        $userColumns = pdColumns(
            $pdo,
            'users'
        );

        $sets = [
            'password_hash = :password_hash',
        ];

        if (
            in_array(
                'updated_at',
                $userColumns,
                true
            )
        ) {
            $sets[] = 'updated_at = NOW()';
        }

        $where =
            'id = :user_id AND tenant_id = :tenant_id';

        if (
            in_array(
                'deleted_at',
                $userColumns,
                true
            )
        ) {
            $where .= ' AND deleted_at IS NULL';
        }

        $stmt = $pdo->prepare(
            "UPDATE users
             SET " . implode(', ', $sets) . "
             WHERE {$where}"
        );

        $stmt->execute([
            'password_hash' => $newHash,
            'user_id' =>
                (int)$scope['user_id'],
            'tenant_id' =>
                (int)$scope['tenant_id'],
        ]);

        if ($stmt->rowCount() < 1) {
            throw new RuntimeException(
                'Unable to update the password.'
            );
        }

        /*
         * Regenerate the session ID after credential change.
         * The Parent remains signed in to the same existing account.
         */
        session_regenerate_id(true);

        pdJson(
            true,
            'Password changed successfully.',
            [
                'csrf_token' =>
                    ppCsrfToken(),
            ]
        );
    }

    throw new InvalidArgumentException(
        'Invalid Profile action.'
    );
} catch (InvalidArgumentException $exception) {
    pdJson(
        false,
        $exception->getMessage(),
        [],
        422
    );
} catch (Throwable $exception) {
    $status = (int)$exception->getCode();

    if ($status < 400 || $status > 599) {
        $status = 500;
    }

    error_log(
        'Parent Profile API: '
        . $exception->getMessage()
    );

    pdJson(
        false,
        $status >= 500
            ? 'Unable to process Parent Profile.'
            : $exception->getMessage(),
        [],
        $status
    );
}

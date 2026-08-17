<?php
declare(strict_types=1);

/* Build: 2026-08-15-classes-branch-visibility-v56 */

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

if (!defined('SCHOOL_API_PAGE_KEY')) {
    define('SCHOOL_API_PAGE_KEY', 'classes');
}

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/general-settings-runtime.php';

require_once dirname(__DIR__)
    . '/includes/controllers/ClassManagementController.php';

function classJson(
    bool $success,
    string $message = '',
    array $data = [],
    int $statusCode = 200
): never {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
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

function classInput(): array
{
    $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));

    if (str_contains($contentType, 'application/json')) {
        $decoded = json_decode(
            (string)file_get_contents('php://input'),
            true
        );

        return is_array($decoded) ? $decoded : [];
    }

    return $_POST;
}

function classCsrf(array $input): void
{
    $token = trim((string)($input['csrf_token'] ?? ''));

    $valid = function_exists('csrf_is_valid')
        ? csrf_is_valid($token)
        : (
            isset($_SESSION['csrf_token'])
            && is_string($_SESSION['csrf_token'])
            && hash_equals((string)$_SESSION['csrf_token'], $token)
        );

    if (!$valid) {
        classJson(false, 'Invalid or expired CSRF token.', [], 419);
    }
}


function classTableExists(PDO $pdo, string $table): bool
{
    static $cache = [];

    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    $statement = $pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = :table_name"
    );
    $statement->execute(['table_name' => $table]);

    return $cache[$table] = (int)$statement->fetchColumn() > 0;
}

function classColumnExists(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
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

    return $cache[$key] = (int)$statement->fetchColumn() > 0;
}


function classBranchClassesFileEnabled(
    PDO $pdo,
    array $scope
): bool {
    $tenantId = (int)($scope['tenant_id'] ?? 0);
    $branchId = (int)($scope['branch_id'] ?? 0);

    if ($tenantId <= 0 || $branchId <= 0) {
        return false;
    }

    /*
     * Existing schools default to ON until School Admin explicitly switches
     * the Classes File OFF for a branch in Branch Settings.
     */
    if (!classTableExists($pdo, 'branch_module_visibility')) {
        return true;
    }

    $statement = $pdo->prepare(
        "SELECT is_enabled
         FROM branch_module_visibility
         WHERE tenant_id = :tenant_id
           AND branch_id = :branch_id
           AND module_key = 'classes'
         LIMIT 1"
    );

    $statement->execute([
        'tenant_id' => $tenantId,
        'branch_id' => $branchId,
    ]);

    $value = $statement->fetchColumn();

    return $value === false
        ? true
        : (int)$value === 1;
}

function classRequireBranchClassesFileEnabled(
    PDO $pdo,
    array $scope
): void {
    if (
        !classBranchClassesFileEnabled(
            $pdo,
            $scope
        )
    ) {
        throw new RuntimeException(
            'Classes File is OFF for the active Branch in Branch Settings.',
            403
        );
    }
}

function classScope(array $user, ClassManagementController $controller): array
{
    $tenantId = max(0, $controller->tenantId());
    $branchId = 0;

    /*
     * Use the ERP's existing active Branch context first.
     * Branch Settings already stores the selected branch in the session.
     */
    if (function_exists('current_branch_id')) {
        $branchId = max(0, (int)current_branch_id());
    }

    if (
        $branchId <= 0
        && function_exists('branch_current_id')
    ) {
        $branchId = max(0, (int)branch_current_id());
    }

    if ($branchId <= 0) {
        $branchId = max(
            0,
            (int)(
                $_SESSION['branch_id']
                ?? $_SESSION['default_branch_id']
                ?? $user['branch_id']
                ?? $user['default_branch_id']
                ?? 0
            )
        );
    }

    if (
        $branchId <= 0
        && method_exists($controller, 'branchId')
    ) {
        $branchId = max(0, (int)$controller->branchId());
    }

    return [
        'tenant_id' => $tenantId,
        'branch_id' => $branchId,
        'user_id' => max(0, $controller->userId()),
    ];
}


function classResolveActiveBranch(
    PDO $pdo,
    array $user,
    ClassManagementController $controller
): array {
    $scope = classScope($user, $controller);

    $tenantId = (int)($scope['tenant_id'] ?? 0);
    $userId = (int)($scope['user_id'] ?? 0);

    if ($tenantId <= 0) {
        throw new RuntimeException(
            'School session is unavailable.',
            403
        );
    }

    $branchAllowed = static function (
        int $branchId
    ) use (
        $pdo,
        $tenantId
    ): bool {
        if ($branchId <= 0) {
            return false;
        }

        $statement = $pdo->prepare(
            "SELECT id
             FROM branches
             WHERE id = :branch_id
               AND tenant_id = :tenant_id
               AND status = 'active'
             LIMIT 1"
        );

        $statement->execute([
            'branch_id' => $branchId,
            'tenant_id' => $tenantId,
        ]);

        if ((int)$statement->fetchColumn() !== $branchId) {
            return false;
        }

        if (
            function_exists('school_user_can_access_branch')
            && !school_user_can_access_branch($branchId)
        ) {
            return false;
        }

        return true;
    };

    /*
     * 1. Branch selected through the existing Branch Settings page.
     */
    $branchId = (int)($scope['branch_id'] ?? 0);

    if (!$branchAllowed($branchId)) {
        $branchId = 0;
    }

    /*
     * 2. Persisted default branch from the logged-in User.
     */
    if (
        $branchId <= 0
        && $userId > 0
        && classTableExists($pdo, 'users')
    ) {
        $statement = $pdo->prepare(
            "SELECT default_branch_id
             FROM users
             WHERE id = :user_id
               AND tenant_id = :tenant_id
               AND status = 'active'
               AND deleted_at IS NULL
             LIMIT 1"
        );

        $statement->execute([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
        ]);

        $candidate = (int)$statement->fetchColumn();

        if ($branchAllowed($candidate)) {
            $branchId = $candidate;
        }
    }

    /*
     * 3. Explicit user_branch_access fallback.
     */
    if (
        $branchId <= 0
        && $userId > 0
        && classTableExists($pdo, 'user_branch_access')
    ) {
        $statement = $pdo->prepare(
            "SELECT b.id
             FROM user_branch_access uba
             INNER JOIN branches b
                ON b.id = uba.branch_id
               AND b.tenant_id = :tenant_id
               AND b.status = 'active'
             WHERE uba.user_id = :user_id
               AND uba.can_access = 1
             ORDER BY b.is_main DESC, b.id ASC
             LIMIT 1"
        );

        $statement->execute([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
        ]);

        $candidate = (int)$statement->fetchColumn();

        if ($branchAllowed($candidate)) {
            $branchId = $candidate;
        }
    }

    /*
     * 4. Compatibility for existing School Admin accounts created before
     * default_branch_id was populated. Start from this school's Main Branch.
     *
     * This does NOT replace or modify the existing Branch Switcher.
     * Once a branch is selected through Branch Settings, that selected branch
     * remains the first choice above.
     */
    if ($branchId <= 0) {
        $statement = $pdo->prepare(
            "SELECT id
             FROM branches
             WHERE tenant_id = :tenant_id
               AND status = 'active'
             ORDER BY is_main DESC, id ASC
             LIMIT 1"
        );

        $statement->execute([
            'tenant_id' => $tenantId,
        ]);

        $candidate = (int)$statement->fetchColumn();

        if ($branchAllowed($candidate)) {
            $branchId = $candidate;
        }
    }

    if ($branchId <= 0) {
        throw new RuntimeException(
            'No active Branch is available for this School.',
            403
        );
    }

    /*
     * Keep this request internally consistent.
     */
    $_SESSION['branch_id'] = $branchId;

    if ((int)($_SESSION['default_branch_id'] ?? 0) <= 0) {
        $_SESSION['default_branch_id'] = $branchId;
    }

    $scope['branch_id'] = $branchId;

    return $scope;
}


/**
 * Strict active Branch scope for Class Management.
 *
 * Branch switching is NOT changed here. This reads the branch already selected
 * by the existing School ERP session and validates it against the current School.
 */
function classStrictScope(
    PDO $pdo,
    array $user,
    ClassManagementController $controller
): array {
    $scope = classResolveActiveBranch(
        $pdo,
        $user,
        $controller
    );

    $tenantId = (int)($scope['tenant_id'] ?? 0);
    $branchId = (int)($scope['branch_id'] ?? 0);

    $branchStatement = $pdo->prepare(
        "SELECT id
         FROM branches
         WHERE id = :branch_id
           AND tenant_id = :tenant_id
           AND status = 'active'
         LIMIT 1"
    );

    $branchStatement->execute([
        'branch_id' => $branchId,
        'tenant_id' => $tenantId,
    ]);

    if ((int)$branchStatement->fetchColumn() !== $branchId) {
        throw new RuntimeException(
            'The selected Branch does not belong to this School.',
            403
        );
    }

    if (
        function_exists('school_user_can_access_branch')
        && !school_user_can_access_branch($branchId)
    ) {
        throw new RuntimeException(
            'You do not have access to the selected Branch.',
            403
        );
    }

    /*
     * The current database contains the branch-protection triggers.
     * Set the connection context so legacy controller writes also inherit the
     * authenticated active Branch instead of trusting browser POST values.
     */
    $pdo->exec(
        'SET @schoolerp_tenant_id = ' . $tenantId
    );
    $pdo->exec(
        'SET @schoolerp_branch_id = ' . $branchId
    );

    return $scope;
}

function classRequireManagementBranch(
    PDO $pdo,
    array $scope,
    int $managementId
): array {
    if ($managementId <= 0) {
        throw new InvalidArgumentException(
            'Invalid class selected.'
        );
    }

    $statement = $pdo->prepare(
        "SELECT *
         FROM class_management_classes
         WHERE id = :id
           AND tenant_id = :tenant_id
           AND branch_id = :branch_id
         LIMIT 1"
    );

    $statement->execute([
        'id' => $managementId,
        'tenant_id' => (int)$scope['tenant_id'],
        'branch_id' => (int)$scope['branch_id'],
    ]);

    $row = $statement->fetch(PDO::FETCH_ASSOC);

    if (!is_array($row)) {
        throw new RuntimeException(
            'Class was not found in the selected Branch.',
            404
        );
    }

    return $row;
}

function classRelatedTable(string $type): string
{
    return match (strtolower(trim($type))) {
        'subjects' => 'class_subject_allocations',
        'teachers' => 'class_teacher_allocations',
        'timetable' => 'class_timetable_entries',
        'transfers' => 'class_student_transfers',
        'promotions' => 'class_student_promotions',
        default => throw new InvalidArgumentException(
            'Invalid class assignment type.'
        ),
    };
}

function classRequireRelatedBranch(
    PDO $pdo,
    array $scope,
    string $type,
    int $id
): void {
    if ($id <= 0) {
        throw new InvalidArgumentException(
            'Invalid assignment selected.'
        );
    }

    $table = classRelatedTable($type);

    $statement = $pdo->prepare(
        "SELECT id
         FROM `{$table}`
         WHERE id = :id
           AND tenant_id = :tenant_id
           AND branch_id = :branch_id
         LIMIT 1"
    );

    $statement->execute([
        'id' => $id,
        'tenant_id' => (int)$scope['tenant_id'],
        'branch_id' => (int)$scope['branch_id'],
    ]);

    if (!(int)$statement->fetchColumn()) {
        throw new RuntimeException(
            'Assignment was not found in the selected Branch.',
            404
        );
    }
}


function classRequireStudentBranch(
    PDO $pdo,
    array $scope,
    int $studentId
): void {
    if ($studentId <= 0) {
        return;
    }

    if (!classTableExists($pdo, 'students')) {
        throw new RuntimeException(
            'Students table is unavailable.',
            500
        );
    }

    $statement = $pdo->prepare(
        "SELECT id
         FROM students
         WHERE id = :id
           AND tenant_id = :tenant_id
           AND branch_id = :branch_id
           AND deleted_at IS NULL
         LIMIT 1"
    );

    $statement->execute([
        'id' => $studentId,
        'tenant_id' => (int)$scope['tenant_id'],
        'branch_id' => (int)$scope['branch_id'],
    ]);

    if (!(int)$statement->fetchColumn()) {
        throw new RuntimeException(
            'Student does not belong to the selected Branch.',
            404
        );
    }
}

function classRequireTeacherBranch(
    PDO $pdo,
    array $scope,
    int $userId
): void {
    if ($userId <= 0) {
        return;
    }

    $tenantId = (int)$scope['tenant_id'];
    $branchId = (int)$scope['branch_id'];

    /*
     * Prefer the Staff master because teachers are operational branch data.
     */
    if (classTableExists($pdo, 'staff_members')) {
        $statement = $pdo->prepare(
            "SELECT id
             FROM staff_members
             WHERE tenant_id = :tenant_id
               AND branch_id = :branch_id
               AND user_id = :user_id
               AND deleted_at IS NULL
               AND status = 'active'
             LIMIT 1"
        );

        $statement->execute([
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'user_id' => $userId,
        ]);

        if ((int)$statement->fetchColumn() > 0) {
            return;
        }
    }

    /*
     * Compatibility for teacher accounts that are assigned through
     * user_branch_access but do not yet have a Staff row.
     */
    if (
        classTableExists($pdo, 'users')
        && classTableExists($pdo, 'user_branch_access')
    ) {
        $statement = $pdo->prepare(
            "SELECT u.id
             FROM users u
             INNER JOIN user_branch_access uba
                ON uba.user_id = u.id
               AND uba.branch_id = :branch_id
               AND uba.can_access = 1
             WHERE u.id = :user_id
               AND u.tenant_id = :tenant_id
               AND u.status = 'active'
               AND u.deleted_at IS NULL
             LIMIT 1"
        );

        $statement->execute([
            'branch_id' => $branchId,
            'user_id' => $userId,
            'tenant_id' => $tenantId,
        ]);

        if ((int)$statement->fetchColumn() > 0) {
            return;
        }
    }

    throw new RuntimeException(
        'Selected teacher does not belong to the active Branch.',
        404
    );
}

function classBranchSaveRelated(
    PDO $pdo,
    array $scope,
    ClassManagementController $controller,
    string $type,
    array $input
): int {
    $type = strtolower(trim($type));

    $managementId = (int)(
        $input['class_id']
        ?? 0
    );

    classRequireManagementBranch(
        $pdo,
        $scope,
        $managementId
    );

    $existingId = max(
        0,
        (int)($input['id'] ?? 0)
    );

    $permission = $existingId > 0
        ? 'edit'
        : 'create';

    if (!$controller->can($permission)) {
        throw new RuntimeException(
            'You do not have permission to save this Class assignment.',
            403
        );
    }

    if ($existingId > 0) {
        classRequireRelatedBranch(
            $pdo,
            $scope,
            $type,
            $existingId
        );
    }

    $tenantId = (int)$scope['tenant_id'];
    $branchId = (int)$scope['branch_id'];
    $createdBy = (int)($scope['user_id'] ?? 0);
    $status = strtolower(
        trim((string)($input['status'] ?? 'active'))
    );

    if (!in_array($status, ['active','inactive','cancelled'], true)) {
        $status = 'active';
    }

    try {
        if ($type === 'subjects') {
            $subjectName = trim((string)($input['subject_name'] ?? ''));
            if ($subjectName === '') {
                throw new InvalidArgumentException(
                    'Subject Name is required.'
                );
            }

            $teacherUserId = max(
                0,
                (int)($input['teacher_user_id'] ?? 0)
            );
            classRequireTeacherBranch(
                $pdo,
                $scope,
                $teacherUserId
            );

            $values = [
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'class_id' => $managementId,
                'subject_name' => mb_substr($subjectName, 0, 150),
                'subject_code' => (
                    trim((string)($input['subject_code'] ?? '')) ?: null
                ),
                'teacher_user_id' => $teacherUserId ?: null,
                'teacher_name' => (
                    trim((string)($input['teacher_name'] ?? '')) ?: null
                ),
                'periods_per_week' => max(
                    0,
                    (int)($input['periods_per_week'] ?? 0)
                ),
                'status' => $status === 'cancelled' ? 'inactive' : $status,
                'created_by' => $createdBy ?: null,
            ];

            if ($existingId > 0) {
                $statement = $pdo->prepare(
                    "UPDATE class_subject_allocations
                     SET subject_name = :subject_name,
                         subject_code = :subject_code,
                         teacher_user_id = :teacher_user_id,
                         teacher_name = :teacher_name,
                         periods_per_week = :periods_per_week,
                         status = :status
                     WHERE id = :id
                       AND tenant_id = :tenant_id
                       AND branch_id = :branch_id
                       AND class_id = :class_id"
                );

                $statement->execute([
                    'subject_name' => $values['subject_name'],
                    'subject_code' => $values['subject_code'],
                    'teacher_user_id' => $values['teacher_user_id'],
                    'teacher_name' => $values['teacher_name'],
                    'periods_per_week' => $values['periods_per_week'],
                    'status' => $values['status'],
                    'id' => $existingId,
                    'tenant_id' => $tenantId,
                    'branch_id' => $branchId,
                    'class_id' => $managementId,
                ]);

                return $existingId;
            }

            $statement = $pdo->prepare(
                "INSERT INTO class_subject_allocations
                    (
                        tenant_id,
                        branch_id,
                        class_id,
                        subject_name,
                        subject_code,
                        teacher_user_id,
                        teacher_name,
                        periods_per_week,
                        status,
                        created_by
                    )
                 VALUES
                    (
                        :tenant_id,
                        :branch_id,
                        :class_id,
                        :subject_name,
                        :subject_code,
                        :teacher_user_id,
                        :teacher_name,
                        :periods_per_week,
                        :status,
                        :created_by
                    )"
            );

            $statement->execute($values);
            return (int)$pdo->lastInsertId();
        }

        if ($type === 'teachers') {
            $teacherName = trim((string)($input['teacher_name'] ?? ''));
            if ($teacherName === '') {
                throw new InvalidArgumentException(
                    'Teacher Name is required.'
                );
            }

            $teacherUserId = max(
                0,
                (int)($input['teacher_user_id'] ?? 0)
            );
            classRequireTeacherBranch(
                $pdo,
                $scope,
                $teacherUserId
            );

            $assignmentType = strtolower(
                trim((string)(
                    $input['assignment_type']
                    ?? 'class_teacher'
                ))
            );

            if (
                !in_array(
                    $assignmentType,
                    [
                        'class_teacher',
                        'subject_teacher',
                        'assistant',
                    ],
                    true
                )
            ) {
                throw new InvalidArgumentException(
                    'Invalid Teacher Assignment Type.'
                );
            }

            $values = [
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'class_id' => $managementId,
                'teacher_user_id' => $teacherUserId ?: null,
                'teacher_name' => mb_substr($teacherName, 0, 150),
                'assignment_type' => $assignmentType,
                'subject_name' => (
                    trim((string)($input['subject_name'] ?? '')) ?: null
                ),
                'status' => $status === 'cancelled' ? 'inactive' : $status,
                'created_by' => $createdBy ?: null,
            ];

            if ($existingId > 0) {
                $statement = $pdo->prepare(
                    "UPDATE class_teacher_allocations
                     SET teacher_user_id = :teacher_user_id,
                         teacher_name = :teacher_name,
                         assignment_type = :assignment_type,
                         subject_name = :subject_name,
                         status = :status
                     WHERE id = :id
                       AND tenant_id = :tenant_id
                       AND branch_id = :branch_id
                       AND class_id = :class_id"
                );

                $statement->execute([
                    'teacher_user_id' => $values['teacher_user_id'],
                    'teacher_name' => $values['teacher_name'],
                    'assignment_type' => $values['assignment_type'],
                    'subject_name' => $values['subject_name'],
                    'status' => $values['status'],
                    'id' => $existingId,
                    'tenant_id' => $tenantId,
                    'branch_id' => $branchId,
                    'class_id' => $managementId,
                ]);

                return $existingId;
            }

            $statement = $pdo->prepare(
                "INSERT INTO class_teacher_allocations
                    (
                        tenant_id,
                        branch_id,
                        class_id,
                        teacher_user_id,
                        teacher_name,
                        assignment_type,
                        subject_name,
                        status,
                        created_by
                    )
                 VALUES
                    (
                        :tenant_id,
                        :branch_id,
                        :class_id,
                        :teacher_user_id,
                        :teacher_name,
                        :assignment_type,
                        :subject_name,
                        :status,
                        :created_by
                    )"
            );

            $statement->execute($values);
            return (int)$pdo->lastInsertId();
        }

        if ($type === 'timetable') {
            $dayName = trim((string)($input['day_name'] ?? ''));
            $periodNo = max(0, (int)($input['period_no'] ?? 0));
            $subjectName = trim((string)($input['subject_name'] ?? ''));

            if ($dayName === '' || $periodNo <= 0 || $subjectName === '') {
                throw new InvalidArgumentException(
                    'Day, Period No and Subject are required.'
                );
            }

            $values = [
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'class_id' => $managementId,
                'day_name' => mb_substr($dayName, 0, 20),
                'period_no' => $periodNo,
                'start_time' => (
                    trim((string)($input['start_time'] ?? '')) ?: null
                ),
                'end_time' => (
                    trim((string)($input['end_time'] ?? '')) ?: null
                ),
                'subject_name' => mb_substr($subjectName, 0, 150),
                'teacher_name' => (
                    trim((string)($input['teacher_name'] ?? '')) ?: null
                ),
                'classroom_name' => (
                    trim((string)($input['classroom_name'] ?? '')) ?: null
                ),
                'status' => $status === 'cancelled' ? 'inactive' : $status,
                'created_by' => $createdBy ?: null,
            ];

            if ($existingId > 0) {
                $statement = $pdo->prepare(
                    "UPDATE class_timetable_entries
                     SET day_name = :day_name,
                         period_no = :period_no,
                         start_time = :start_time,
                         end_time = :end_time,
                         subject_name = :subject_name,
                         teacher_name = :teacher_name,
                         classroom_name = :classroom_name,
                         status = :status
                     WHERE id = :id
                       AND tenant_id = :tenant_id
                       AND branch_id = :branch_id
                       AND class_id = :class_id"
                );

                $statement->execute([
                    'day_name' => $values['day_name'],
                    'period_no' => $values['period_no'],
                    'start_time' => $values['start_time'],
                    'end_time' => $values['end_time'],
                    'subject_name' => $values['subject_name'],
                    'teacher_name' => $values['teacher_name'],
                    'classroom_name' => $values['classroom_name'],
                    'status' => $values['status'],
                    'id' => $existingId,
                    'tenant_id' => $tenantId,
                    'branch_id' => $branchId,
                    'class_id' => $managementId,
                ]);

                return $existingId;
            }

            $statement = $pdo->prepare(
                "INSERT INTO class_timetable_entries
                    (
                        tenant_id,
                        branch_id,
                        class_id,
                        day_name,
                        period_no,
                        start_time,
                        end_time,
                        subject_name,
                        teacher_name,
                        classroom_name,
                        status,
                        created_by
                    )
                 VALUES
                    (
                        :tenant_id,
                        :branch_id,
                        :class_id,
                        :day_name,
                        :period_no,
                        :start_time,
                        :end_time,
                        :subject_name,
                        :teacher_name,
                        :classroom_name,
                        :status,
                        :created_by
                    )"
            );

            $statement->execute($values);
            return (int)$pdo->lastInsertId();
        }

        if ($type === 'transfers') {
            $studentId = max(
                0,
                (int)($input['student_id'] ?? 0)
            );
            classRequireStudentBranch(
                $pdo,
                $scope,
                $studentId
            );

            $studentName = trim((string)($input['student_name'] ?? ''));
            $fromClass = trim((string)($input['from_class_name'] ?? ''));
            $toClass = trim((string)($input['to_class_name'] ?? ''));
            $transferDate = trim((string)($input['transfer_date'] ?? ''));

            if (
                $studentName === ''
                || $fromClass === ''
                || $toClass === ''
                || $transferDate === ''
            ) {
                throw new InvalidArgumentException(
                    'Student, From Class, To Class and Transfer Date are required.'
                );
            }

            $values = [
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'class_id' => $managementId,
                'student_id' => $studentId ?: null,
                'student_name' => mb_substr($studentName, 0, 150),
                'from_class_name' => mb_substr($fromClass, 0, 150),
                'to_class_name' => mb_substr($toClass, 0, 150),
                'transfer_date' => $transferDate,
                'reason' => (
                    trim((string)($input['reason'] ?? '')) ?: null
                ),
                'status' => in_array($status, ['active','cancelled'], true)
                    ? $status
                    : 'active',
                'created_by' => $createdBy ?: null,
            ];

            if ($existingId > 0) {
                $statement = $pdo->prepare(
                    "UPDATE class_student_transfers
                     SET student_id = :student_id,
                         student_name = :student_name,
                         from_class_name = :from_class_name,
                         to_class_name = :to_class_name,
                         transfer_date = :transfer_date,
                         reason = :reason,
                         status = :status
                     WHERE id = :id
                       AND tenant_id = :tenant_id
                       AND branch_id = :branch_id
                       AND class_id = :class_id"
                );

                $statement->execute([
                    'student_id' => $values['student_id'],
                    'student_name' => $values['student_name'],
                    'from_class_name' => $values['from_class_name'],
                    'to_class_name' => $values['to_class_name'],
                    'transfer_date' => $values['transfer_date'],
                    'reason' => $values['reason'],
                    'status' => $values['status'],
                    'id' => $existingId,
                    'tenant_id' => $tenantId,
                    'branch_id' => $branchId,
                    'class_id' => $managementId,
                ]);

                return $existingId;
            }

            $statement = $pdo->prepare(
                "INSERT INTO class_student_transfers
                    (
                        tenant_id,
                        branch_id,
                        class_id,
                        student_id,
                        student_name,
                        from_class_name,
                        to_class_name,
                        transfer_date,
                        reason,
                        status,
                        created_by
                    )
                 VALUES
                    (
                        :tenant_id,
                        :branch_id,
                        :class_id,
                        :student_id,
                        :student_name,
                        :from_class_name,
                        :to_class_name,
                        :transfer_date,
                        :reason,
                        :status,
                        :created_by
                    )"
            );

            $statement->execute($values);
            return (int)$pdo->lastInsertId();
        }

        if ($type === 'promotions') {
            $studentId = max(
                0,
                (int)($input['student_id'] ?? 0)
            );
            classRequireStudentBranch(
                $pdo,
                $scope,
                $studentId
            );

            $studentName = trim((string)($input['student_name'] ?? ''));
            $fromClass = trim((string)($input['from_class_name'] ?? ''));
            $toClass = trim((string)($input['to_class_name'] ?? ''));
            $promotionDate = trim((string)($input['promotion_date'] ?? ''));

            $resultStatus = strtolower(
                trim((string)(
                    $input['result_status']
                    ?? 'pending'
                ))
            );

            if (
                !in_array(
                    $resultStatus,
                    ['promoted','retained','pending'],
                    true
                )
            ) {
                throw new InvalidArgumentException(
                    'Invalid Promotion Result.'
                );
            }

            if (
                $studentName === ''
                || $fromClass === ''
                || $toClass === ''
                || $promotionDate === ''
            ) {
                throw new InvalidArgumentException(
                    'Student, From Class, To Class and Promotion Date are required.'
                );
            }

            $values = [
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'class_id' => $managementId,
                'student_id' => $studentId ?: null,
                'student_name' => mb_substr($studentName, 0, 150),
                'from_class_name' => mb_substr($fromClass, 0, 150),
                'to_class_name' => mb_substr($toClass, 0, 150),
                'promotion_date' => $promotionDate,
                'result_status' => $resultStatus,
                'remarks' => (
                    trim((string)($input['remarks'] ?? '')) ?: null
                ),
                'status' => in_array($status, ['active','cancelled'], true)
                    ? $status
                    : 'active',
                'created_by' => $createdBy ?: null,
            ];

            if ($existingId > 0) {
                $statement = $pdo->prepare(
                    "UPDATE class_student_promotions
                     SET student_id = :student_id,
                         student_name = :student_name,
                         from_class_name = :from_class_name,
                         to_class_name = :to_class_name,
                         promotion_date = :promotion_date,
                         result_status = :result_status,
                         remarks = :remarks,
                         status = :status
                     WHERE id = :id
                       AND tenant_id = :tenant_id
                       AND branch_id = :branch_id
                       AND class_id = :class_id"
                );

                $statement->execute([
                    'student_id' => $values['student_id'],
                    'student_name' => $values['student_name'],
                    'from_class_name' => $values['from_class_name'],
                    'to_class_name' => $values['to_class_name'],
                    'promotion_date' => $values['promotion_date'],
                    'result_status' => $values['result_status'],
                    'remarks' => $values['remarks'],
                    'status' => $values['status'],
                    'id' => $existingId,
                    'tenant_id' => $tenantId,
                    'branch_id' => $branchId,
                    'class_id' => $managementId,
                ]);

                return $existingId;
            }

            $statement = $pdo->prepare(
                "INSERT INTO class_student_promotions
                    (
                        tenant_id,
                        branch_id,
                        class_id,
                        student_id,
                        student_name,
                        from_class_name,
                        to_class_name,
                        promotion_date,
                        result_status,
                        remarks,
                        status,
                        created_by
                    )
                 VALUES
                    (
                        :tenant_id,
                        :branch_id,
                        :class_id,
                        :student_id,
                        :student_name,
                        :from_class_name,
                        :to_class_name,
                        :promotion_date,
                        :result_status,
                        :remarks,
                        :status,
                        :created_by
                    )"
            );

            $statement->execute($values);
            return (int)$pdo->lastInsertId();
        }
    } catch (PDOException $exception) {
        if ((string)$exception->getCode() === '23000') {
            throw new InvalidArgumentException(
                'The same Class assignment already exists.'
            );
        }

        throw $exception;
    }

    throw new InvalidArgumentException(
        'Invalid Class assignment type.'
    );
}

function classBranchDeleteRelated(
    PDO $pdo,
    array $scope,
    ClassManagementController $controller,
    string $type,
    int $id
): void {
    if (!$controller->can('delete')) {
        throw new RuntimeException(
            'You do not have permission to delete this Class assignment.',
            403
        );
    }

    classRequireRelatedBranch(
        $pdo,
        $scope,
        $type,
        $id
    );

    $table = classRelatedTable($type);

    $statement = $pdo->prepare(
        "DELETE FROM `{$table}`
         WHERE id = :id
           AND tenant_id = :tenant_id
           AND branch_id = :branch_id"
    );

    $statement->execute([
        'id' => $id,
        'tenant_id' => (int)$scope['tenant_id'],
        'branch_id' => (int)$scope['branch_id'],
    ]);

    if ($statement->rowCount() !== 1) {
        throw new RuntimeException(
            'Unable to delete the selected Class assignment.',
            409
        );
    }
}


function classBranchList(
    PDO $pdo,
    array $scope,
    array $filters
): array {
    $where = [
        'cmc.tenant_id = :tenant_id',
        'cmc.branch_id = :branch_id',
    ];

    $params = [
        'tenant_id' => (int)$scope['tenant_id'],
        'branch_id' => (int)$scope['branch_id'],
    ];

    $search = trim((string)($filters['search'] ?? ''));
    if ($search !== '') {
        $where[] = "(
            cmc.class_name LIKE :search
            OR cmc.class_code LIKE :search
            OR cmc.section_name LIKE :search
            OR COALESCE(cmc.class_teacher_name,'') LIKE :search
            OR COALESCE(cmc.classroom_name,'') LIKE :search
        )";
        $params['search'] = '%' . $search . '%';
    }

    $academicYearId = (int)($filters['academic_year_id'] ?? 0);
    if ($academicYearId > 0) {
        $where[] = 'cmc.academic_year_id = :academic_year_id';
        $params['academic_year_id'] = $academicYearId;
    }

    $medium = trim((string)($filters['medium'] ?? ''));
    if ($medium !== '' && strtolower($medium) !== 'all') {
        $where[] = 'cmc.medium = :medium';
        $params['medium'] = $medium;
    }

    $shift = trim((string)($filters['shift_name'] ?? ''));
    if ($shift !== '' && strtolower($shift) !== 'all') {
        $where[] = 'cmc.shift_name = :shift_name';
        $params['shift_name'] = $shift;
    }

    $status = strtolower(trim((string)($filters['status'] ?? '')));
    if (
        $status !== ''
        && $status !== 'all'
        && in_array(
            $status,
            ['active', 'inactive', 'archived'],
            true
        )
    ) {
        $where[] = 'cmc.status = :status';
        $params['status'] = $status;
    }

    $statement = $pdo->prepare(
        "SELECT
            cmc.*,
            ay.year_name AS academic_year_name
         FROM class_management_classes cmc
         INNER JOIN academic_years ay
            ON ay.id = cmc.academic_year_id
           AND ay.tenant_id = cmc.tenant_id
           AND ay.branch_id = cmc.branch_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY
            ay.is_current DESC,
            ay.start_date DESC,
            cmc.display_order,
            cmc.class_name,
            cmc.section_name,
            cmc.id"
    );

    $statement->execute($params);

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function classBranchRelated(
    PDO $pdo,
    array $scope,
    string $type,
    int $managementId
): array {
    classRequireManagementBranch(
        $pdo,
        $scope,
        $managementId
    );

    $table = classRelatedTable($type);

    $statement = $pdo->prepare(
        "SELECT *
         FROM `{$table}`
         WHERE tenant_id = :tenant_id
           AND branch_id = :branch_id
           AND class_id = :class_id
         ORDER BY id DESC"
    );

    $statement->execute([
        'tenant_id' => (int)$scope['tenant_id'],
        'branch_id' => (int)$scope['branch_id'],
        'class_id' => $managementId,
    ]);

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function classNormal(string $value): string
{
    $value = trim(mb_strtolower($value));
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    return $value;
}

function classStrengthKey(
    int $academicYearId,
    string $className,
    string $sectionName = '*'
): string {
    return $academicYearId
        . '|'
        . classNormal($className)
        . '|'
        . classNormal($sectionName);
}

function classCurrentAcademicYearId(
    PDO $pdo,
    int $tenantId,
    int $branchId
): int {
    if (
        !classTableExists($pdo, 'academic_years')
        || $tenantId <= 0
        || $branchId <= 0
    ) {
        return 0;
    }

    $statement = $pdo->prepare(
        "SELECT id
         FROM academic_years
         WHERE tenant_id = :tenant_id
           AND branch_id = :branch_id
         ORDER BY is_current DESC,
                  status = 'active' DESC,
                  start_date DESC,
                  id DESC
         LIMIT 1"
    );
    $statement->execute([
        'tenant_id' => $tenantId,
        'branch_id' => $branchId,
    ]);

    return (int)$statement->fetchColumn();
}


/**
 * Load academic years directly from the canonical academic_years table.
 *
 * The Classes page must use the same IDs that are stored in classes and
 * student_enrollments.  Do not build the year list from class-management
 * helper tables because those tables can be incomplete for a newly created
 * academic year.
 */
function classAcademicYears(
    PDO $pdo,
    int $tenantId,
    int $branchId
): array {
    if (
        $tenantId <= 0
        || $branchId <= 0
        || !classTableExists($pdo, 'academic_years')
    ) {
        return [];
    }

    $statement = $pdo->prepare(
        "SELECT
            id,
            branch_id,
            academic_year_code,
            year_name,
            start_date,
            end_date,
            is_current,
            status
         FROM academic_years
         WHERE tenant_id = :tenant_id
           AND branch_id = :branch_id
         ORDER BY is_current DESC,
                  start_date DESC,
                  id DESC"
    );
    $statement->execute([
        'tenant_id' => $tenantId,
        'branch_id' => $branchId,
    ]);

    return array_map(
        static function (array $row): array {
            $row['id'] = (int)$row['id'];
            $row['branch_id'] = (int)$row['branch_id'];
            $row['is_current'] = (int)$row['is_current'];
            return $row;
        },
        $statement->fetchAll(PDO::FETCH_ASSOC)
    );
}

function classResolveCanonicalClass(
    PDO $pdo,
    int $tenantId,
    int $classId
): ?array {
    if ($classId <= 0) {
        return null;
    }

    if (classTableExists($pdo, 'classes')) {
        $statement = $pdo->prepare(
            "SELECT id, academic_year_id, class_name, display_order, status
             FROM classes
             WHERE id = :id
               AND tenant_id = :tenant_id
               AND branch_id = @schoolerp_branch_id
             LIMIT 1"
        );
        $statement->execute([
            'id' => $classId,
            'tenant_id' => $tenantId,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (is_array($row)) {
            return [
                'id' => (int)$row['id'],
                'academic_year_id' => (int)$row['academic_year_id'],
                'class_name' => (string)$row['class_name'],
                'display_order' => (int)($row['display_order'] ?? 0),
                'status' => (string)($row['status'] ?? 'active'),
                'source' => 'classes',
            ];
        }
    }

    /*
     * Compatibility for old enrollment rows that were saved with the
     * class_management_classes ID before the canonical Classes mapping
     * was corrected.
     */
    if (classTableExists($pdo, 'class_management_classes')) {
        $statement = $pdo->prepare(
            "SELECT id, academic_year_id, class_name, display_order, status
             FROM class_management_classes
             WHERE id = :id
               AND tenant_id = :tenant_id
               AND branch_id = @schoolerp_branch_id
             LIMIT 1"
        );
        $statement->execute([
            'id' => $classId,
            'tenant_id' => $tenantId,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (is_array($row)) {
            return [
                'id' => (int)$row['id'],
                'academic_year_id' => (int)$row['academic_year_id'],
                'class_name' => (string)$row['class_name'],
                'display_order' => (int)($row['display_order'] ?? 0),
                'status' => (string)($row['status'] ?? 'active'),
                'source' => 'class_management_classes',
            ];
        }
    }

    return null;
}

function classResolveSection(
    PDO $pdo,
    int $tenantId,
    int $sectionId,
    int $academicYearId,
    string $className
): array {
    $default = [
        'id' => 0,
        'section_name' => 'General',
        'section_code' => '',
        'medium' => 'English',
        'shift_name' => 'General',
        'room_number' => '',
        'capacity' => 40,
        'class_teacher_user_id' => 0,
        'class_teacher_name' => '',
    ];

    if ($sectionId > 0 && classTableExists($pdo, 'sections')) {
        $statement = $pdo->prepare(
            "SELECT id, section_name, capacity
             FROM sections
             WHERE id = :id
               AND tenant_id = :tenant_id
               AND branch_id = @schoolerp_branch_id
             LIMIT 1"
        );
        $statement->execute([
            'id' => $sectionId,
            'tenant_id' => $tenantId,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (is_array($row)) {
            $default['id'] = (int)$row['id'];
            $default['section_name'] =
                trim((string)$row['section_name']) ?: 'General';
            $default['capacity'] = max(
                1,
                (int)($row['capacity'] ?? 40)
            );
        }
    }

    if (
        classTableExists($pdo, 'school_sections')
        && classColumnExists($pdo, 'school_sections', 'section_name')
    ) {
        $where = [
            'tenant_id = :tenant_id',
            'branch_id = @schoolerp_branch_id',
            'academic_year_id = :academic_year_id',
            'LOWER(TRIM(class_name_snapshot)) = LOWER(TRIM(:class_name))',
        ];
        $params = [
            'tenant_id' => $tenantId,
            'academic_year_id' => $academicYearId,
            'class_name' => $className,
        ];

        if ($default['section_name'] !== 'General') {
            $where[] =
                'LOWER(TRIM(section_name)) = LOWER(TRIM(:section_name))';
            $params['section_name'] = $default['section_name'];
        }

        $statement = $pdo->prepare(
            "SELECT *
             FROM school_sections
             WHERE " . implode(' AND ', $where) . "
             ORDER BY status = 'active' DESC, display_order, id
             LIMIT 1"
        );
        $statement->execute($params);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (is_array($row)) {
            $default['section_name'] =
                trim((string)($row['section_name'] ?? ''))
                ?: $default['section_name'];
            $default['section_code'] =
                trim((string)($row['section_code'] ?? ''));
            $default['medium'] =
                trim((string)($row['medium'] ?? ''))
                ?: 'English';
            $default['shift_name'] =
                trim((string)($row['shift_name'] ?? ''))
                ?: 'General';
            $default['room_number'] =
                trim((string)($row['room_number'] ?? ''));
            $default['capacity'] = max(
                1,
                (int)(
                    $row['maximum_student_capacity']
                    ?? $default['capacity']
                )
            );
            $default['class_teacher_user_id'] = max(
                0,
                (int)($row['class_teacher_user_id'] ?? 0)
            );
            $default['class_teacher_name'] =
                trim((string)($row['class_teacher_name'] ?? ''));
        }
    }

    return $default;
}

function classUniqueCode(
    PDO $pdo,
    int $tenantId,
    int $academicYearId,
    string $preferred,
    int $classId,
    string $sectionName
): string {
    $code = strtoupper(trim($preferred));
    $code = preg_replace('/[^A-Z0-9_-]+/', '-', $code) ?? $code;
    $code = trim($code, '-_');

    if ($code === '') {
        $sectionPart = strtoupper(
            preg_replace('/[^A-Z0-9]+/', '', $sectionName) ?? ''
        );
        $code = 'CLS-' . $classId
            . ($sectionPart !== '' ? '-' . $sectionPart : '');
    }

    $code = substr($code, 0, 30);
    $base = $code;
    $suffix = 1;

    $statement = $pdo->prepare(
        "SELECT COUNT(*)
         FROM class_management_classes
         WHERE tenant_id = :tenant_id
           AND branch_id = @schoolerp_branch_id
           AND academic_year_id = :academic_year_id
           AND class_code = :class_code"
    );

    while (true) {
        $statement->execute([
            'tenant_id' => $tenantId,
            'academic_year_id' => $academicYearId,
            'class_code' => $code,
        ]);

        if ((int)$statement->fetchColumn() === 0) {
            return $code;
        }

        $suffix++;
        $tail = '-' . $suffix;
        $code = substr($base, 0, 30 - strlen($tail)) . $tail;
    }
}

/**
 * Synchronize the Class Management display table from the canonical
 * classes + sections tables.
 *
 * DATABASE SOURCE OF TRUTH
 * ------------------------
 * academic_years.id  -> classes.academic_year_id
 * classes.id         -> sections.class_id
 *
 * class_management_classes is only the richer UI/configuration record.  It
 * must never decide which classes exist in an academic year.  Previously the
 * page created rows only when an active student_enrollment existed, so an
 * academic year could show only a few classes even though the classes table
 * already contained the complete class list.
 *
 * This function creates only missing management rows. Existing rows keep the
 * teacher, room, medium, shift and other user-maintained values.
 */
function classSyncFromCanonicalClasses(
    PDO $pdo,
    array $scope,
    int $academicYearId = 0
): void {
    if (
        $scope['tenant_id'] <= 0
        || !classTableExists($pdo, 'classes')
        || !classTableExists($pdo, 'class_management_classes')
    ) {
        return;
    }

    $where = [
        'c.tenant_id = :tenant_id',
        'c.branch_id = @schoolerp_branch_id',
    ];
    $params = ['tenant_id' => $scope['tenant_id']];

    if ($academicYearId > 0) {
        $where[] = 'c.academic_year_id = :academic_year_id';
        $params['academic_year_id'] = $academicYearId;
    }

    $hasSections = classTableExists($pdo, 'sections');

    $sql = "SELECT
                c.id AS canonical_class_id,
                c.academic_year_id,
                c.class_name,
                c.display_order,
                c.status AS class_status";

    if ($hasSections) {
        $sql .= ",
                s.id AS section_id,
                s.section_name,
                s.capacity,
                s.status AS section_status";
    } else {
        $sql .= ",
                0 AS section_id,
                'General' AS section_name,
                NULL AS capacity,
                'active' AS section_status";
    }

    $sql .= "
         FROM classes c";

    if ($hasSections) {
        $sql .= "
         LEFT JOIN sections s
           ON s.class_id = c.id
          AND s.tenant_id = c.tenant_id
          AND s.branch_id = c.branch_id";
    }

    $sql .= "
         WHERE " . implode(' AND ', $where) . "
         ORDER BY c.academic_year_id DESC,
                  c.display_order,
                  c.id,
                  section_name";

    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    $canonicalRows = $statement->fetchAll(PDO::FETCH_ASSOC);

    $find = $pdo->prepare(
        "SELECT id
         FROM class_management_classes
         WHERE tenant_id = :tenant_id
           AND branch_id = @schoolerp_branch_id
           AND academic_year_id = :academic_year_id
           AND LOWER(TRIM(class_name)) = LOWER(TRIM(:class_name))
           AND LOWER(TRIM(section_name)) = LOWER(TRIM(:section_name))
         LIMIT 1"
    );

    $insert = $pdo->prepare(
        "INSERT INTO class_management_classes (
            tenant_id,
            branch_id,
            academic_year_id,
            class_name,
            class_code,
            section_name,
            medium,
            shift_name,
            class_teacher_user_id,
            class_teacher_name,
            classroom_name,
            maximum_strength,
            current_strength,
            status,
            description,
            display_order,
            created_by,
            updated_by
         ) VALUES (
            :tenant_id,
            @schoolerp_branch_id,
            :academic_year_id,
            :class_name,
            :class_code,
            :section_name,
            :medium,
            :shift_name,
            :class_teacher_user_id,
            :class_teacher_name,
            :classroom_name,
            :maximum_strength,
            0,
            :status,
            :description,
            :display_order,
            :created_by,
            :updated_by
         )"
    );

    foreach ($canonicalRows as $row) {
        $yearId = (int)$row['academic_year_id'];
        $className = trim((string)$row['class_name']);
        if ($yearId <= 0 || $className === '') {
            continue;
        }

        $sectionId = (int)($row['section_id'] ?? 0);
        $section = classResolveSection(
            $pdo,
            $scope['tenant_id'],
            $sectionId,
            $yearId,
            $className
        );

        // When a real canonical section exists, its name is authoritative.
        $canonicalSectionName = trim((string)($row['section_name'] ?? ''));
        if ($canonicalSectionName !== '') {
            $section['section_name'] = $canonicalSectionName;
        }

        if (($row['capacity'] ?? null) !== null && (int)$row['capacity'] > 0) {
            $section['capacity'] = (int)$row['capacity'];
        }

        $sectionName = trim((string)$section['section_name']) ?: 'General';

        $find->execute([
            'tenant_id' => $scope['tenant_id'],
            'academic_year_id' => $yearId,
            'class_name' => $className,
            'section_name' => $sectionName,
        ]);

        if ((int)$find->fetchColumn() > 0) {
            continue;
        }

        $classCode = classUniqueCode(
            $pdo,
            $scope['tenant_id'],
            $yearId,
            (string)($section['section_code'] ?? ''),
            (int)$row['canonical_class_id'],
            $sectionName
        );

        $status = (
            strtolower((string)($row['class_status'] ?? 'active')) === 'active'
            && strtolower((string)($row['section_status'] ?? 'active')) === 'active'
        ) ? 'active' : 'inactive';

        $insert->execute([
            'tenant_id' => $scope['tenant_id'],
            'academic_year_id' => $yearId,
            'class_name' => $className,
            'class_code' => $classCode,
            'section_name' => $sectionName,
            'medium' => trim((string)($section['medium'] ?? '')) ?: 'English',
            'shift_name' => trim((string)($section['shift_name'] ?? '')) ?: 'General',
            'class_teacher_user_id' => (int)($section['class_teacher_user_id'] ?? 0) ?: null,
            'class_teacher_name' => trim((string)($section['class_teacher_name'] ?? '')) ?: null,
            'classroom_name' => trim((string)($section['room_number'] ?? '')) ?: null,
            'maximum_strength' => max(1, (int)($section['capacity'] ?? 40)),
            'status' => $status,
            'description' => $className . ' Section ' . $sectionName,
            'display_order' => (int)($row['display_order'] ?? 0),
            'created_by' => $scope['user_id'] ?: null,
            'updated_by' => $scope['user_id'] ?: null,
        ]);
    }
}

/**
 * The Student List stores academic placement in student_enrollments, not in
 * students. This synchronizes missing Class Management rows from the active
 * enrollment records so a current-year class is visible before its strength
 * is rendered.
 */
function classSyncFromStudentEnrollments(
    PDO $pdo,
    array $scope
): void {
    if (
        $scope['tenant_id'] <= 0
        || !classTableExists($pdo, 'students')
        || !classTableExists($pdo, 'student_enrollments')
        || !classTableExists($pdo, 'class_management_classes')
    ) {
        return;
    }

    $studentConditions = [
        'e.tenant_id = :tenant_id',
        'e.branch_id = @schoolerp_branch_id',
    ];
    $params = [
        'tenant_id' => $scope['tenant_id'],
    ];

    if (classColumnExists($pdo, 'student_enrollments', 'enrollment_status')) {
        $studentConditions[] = "e.enrollment_status = 'active'";
    }

    if (classColumnExists($pdo, 'students', 'status')) {
        $studentConditions[] = "s.status = 'active'";
    }

    if (classColumnExists($pdo, 'students', 'deleted_at')) {
        $studentConditions[] = 's.deleted_at IS NULL';
    }

    if (
        $scope['branch_id'] > 0
        && classColumnExists($pdo, 'students', 'branch_id')
    ) {
        $studentConditions[] = 's.branch_id = :branch_id';
        $params['branch_id'] = $scope['branch_id'];
    }

    $statement = $pdo->prepare(
        "SELECT DISTINCT
            e.academic_year_id,
            e.class_id,
            e.section_id
         FROM student_enrollments e
         INNER JOIN students s
            ON s.id = e.student_id
           AND s.tenant_id = e.tenant_id
           AND s.branch_id = e.branch_id
         WHERE " . implode(' AND ', $studentConditions)
    );
    $statement->execute($params);
    $placements = $statement->fetchAll(PDO::FETCH_ASSOC);

    $find = $pdo->prepare(
        "SELECT id
         FROM class_management_classes
         WHERE tenant_id = :tenant_id
           AND branch_id = @schoolerp_branch_id
           AND academic_year_id = :academic_year_id
           AND LOWER(TRIM(class_name)) = LOWER(TRIM(:class_name))
           AND LOWER(TRIM(section_name)) = LOWER(TRIM(:section_name))
         LIMIT 1"
    );

    $insert = $pdo->prepare(
        "INSERT INTO class_management_classes (
            tenant_id,
            branch_id,
            academic_year_id,
            class_name,
            class_code,
            section_name,
            medium,
            shift_name,
            class_teacher_user_id,
            class_teacher_name,
            classroom_name,
            maximum_strength,
            current_strength,
            status,
            description,
            display_order,
            created_by,
            updated_by
        ) VALUES (
            :tenant_id,
            @schoolerp_branch_id,
            :academic_year_id,
            :class_name,
            :class_code,
            :section_name,
            :medium,
            :shift_name,
            :class_teacher_user_id,
            :class_teacher_name,
            :classroom_name,
            :maximum_strength,
            0,
            'active',
            :description,
            :display_order,
            :created_by,
            :updated_by
        )"
    );

    foreach ($placements as $placement) {
        $academicYearId = (int)$placement['academic_year_id'];
        $class = classResolveCanonicalClass(
            $pdo,
            $scope['tenant_id'],
            (int)$placement['class_id']
        );

        if (!$class) {
            continue;
        }

        /*
         * The enrollment's academic year is the source of truth. This also
         * repairs older class IDs that pointed to a template from another year.
         */
        $class['academic_year_id'] = $academicYearId;

        $section = classResolveSection(
            $pdo,
            $scope['tenant_id'],
            (int)$placement['section_id'],
            $academicYearId,
            (string)$class['class_name']
        );

        $find->execute([
            'tenant_id' => $scope['tenant_id'],
            'academic_year_id' => $academicYearId,
            'class_name' => $class['class_name'],
            'section_name' => $section['section_name'],
        ]);

        if ((int)$find->fetchColumn() > 0) {
            continue;
        }

        $classCode = classUniqueCode(
            $pdo,
            $scope['tenant_id'],
            $academicYearId,
            $section['section_code'],
            (int)$class['id'],
            (string)$section['section_name']
        );

        $insert->execute([
            'tenant_id' => $scope['tenant_id'],
            'academic_year_id' => $academicYearId,
            'class_name' => $class['class_name'],
            'class_code' => $classCode,
            'section_name' => $section['section_name'],
            'medium' => $section['medium'],
            'shift_name' => $section['shift_name'],
            'class_teacher_user_id' =>
                $section['class_teacher_user_id'] ?: null,
            'class_teacher_name' =>
                $section['class_teacher_name'] ?: null,
            'classroom_name' =>
                $section['room_number'] ?: null,
            'maximum_strength' => $section['capacity'],
            'description' =>
                $class['class_name']
                . ' Section '
                . $section['section_name'],
            'display_order' => (int)$class['display_order'],
            'created_by' => $scope['user_id'] ?: null,
            'updated_by' => $scope['user_id'] ?: null,
        ]);
    }
}

/**
 * @return array{
 *   exact:array<string,int>,
 *   class:array<string,int>,
 *   selected_total:int,
 *   current_year_id:int,
 *   unassigned:int
 * }
 */
function classBuildLiveStrengthMap(
    PDO $pdo,
    array $scope,
    int $selectedAcademicYearId = 0
): array {
    $result = [
        'exact' => [],
        'class' => [],
        'selected_total' => 0,
        'current_year_id' => classCurrentAcademicYearId(
            $pdo,
            (int)$scope['tenant_id'],
            (int)$scope['branch_id']
        ),
        'unassigned' => 0,
    ];

    if (
        $scope['tenant_id'] <= 0
        || !classTableExists($pdo, 'students')
        || !classTableExists($pdo, 'student_enrollments')
    ) {
        return $result;
    }

    $conditions = [
        'e.tenant_id = :tenant_id',
        'e.branch_id = @schoolerp_branch_id',
    ];
    $params = ['tenant_id' => $scope['tenant_id']];

    if (classColumnExists($pdo, 'student_enrollments', 'enrollment_status')) {
        $conditions[] = "e.enrollment_status = 'active'";
    }

    if (classColumnExists($pdo, 'students', 'status')) {
        $conditions[] = "s.status = 'active'";
    }

    if (classColumnExists($pdo, 'students', 'deleted_at')) {
        $conditions[] = 's.deleted_at IS NULL';
    }

    if (
        $scope['branch_id'] > 0
        && classColumnExists($pdo, 'students', 'branch_id')
    ) {
        $conditions[] = 's.branch_id = :branch_id';
        $params['branch_id'] = $scope['branch_id'];
    }

    $statement = $pdo->prepare(
        "SELECT DISTINCT
            e.student_id,
            e.academic_year_id,
            e.class_id,
            e.section_id
         FROM student_enrollments e
         INNER JOIN students s
            ON s.id = e.student_id
           AND s.tenant_id = e.tenant_id
           AND s.branch_id = e.branch_id
         WHERE " . implode(' AND ', $conditions)
    );
    $statement->execute($params);
    $enrollments = $statement->fetchAll(PDO::FETCH_ASSOC);

    $exactStudents = [];
    $classStudents = [];
    $yearStudents = [];

    foreach ($enrollments as $enrollment) {
        $academicYearId = (int)$enrollment['academic_year_id'];
        $studentId = (int)$enrollment['student_id'];
        $class = classResolveCanonicalClass(
            $pdo,
            $scope['tenant_id'],
            (int)$enrollment['class_id']
        );

        if (!$class) {
            continue;
        }

        $section = classResolveSection(
            $pdo,
            $scope['tenant_id'],
            (int)$enrollment['section_id'],
            $academicYearId,
            (string)$class['class_name']
        );

        $exactKey = classStrengthKey(
            $academicYearId,
            (string)$class['class_name'],
            (string)$section['section_name']
        );
        $classKey = classStrengthKey(
            $academicYearId,
            (string)$class['class_name']
        );

        $exactStudents[$exactKey][$studentId] = true;
        $classStudents[$classKey][$studentId] = true;
        $yearStudents[$academicYearId][$studentId] = true;
    }

    foreach ($exactStudents as $key => $students) {
        $result['exact'][$key] = count($students);
    }

    foreach ($classStudents as $key => $students) {
        $result['class'][$key] = count($students);
    }

    $statsYearId = $selectedAcademicYearId > 0
        ? $selectedAcademicYearId
        : $result['current_year_id'];

    $result['selected_total'] = isset($yearStudents[$statsYearId])
        ? count($yearStudents[$statsYearId])
        : 0;

    /*
     * Active students without any active enrollment cannot be assigned to a
     * class because the students table has no academic_year_id/class_id/
     * section_id columns. Return the count for diagnostics.
     */
    $unassignedConditions = [
        's.tenant_id = :tenant_id',
        's.branch_id = @schoolerp_branch_id',
    ];
    $unassignedParams = [
        'tenant_id' => $scope['tenant_id'],
        'academic_year_id' => $statsYearId,
    ];

    if (classColumnExists($pdo, 'students', 'status')) {
        $unassignedConditions[] = "s.status = 'active'";
    }

    if (classColumnExists($pdo, 'students', 'deleted_at')) {
        $unassignedConditions[] = 's.deleted_at IS NULL';
    }

    if (
        $scope['branch_id'] > 0
        && classColumnExists($pdo, 'students', 'branch_id')
    ) {
        $unassignedConditions[] = 's.branch_id = :branch_id';
        $unassignedParams['branch_id'] = $scope['branch_id'];
    }

    if ($statsYearId > 0) {
        $unassignedStatement = $pdo->prepare(
            "SELECT COUNT(*)
             FROM students s
             WHERE " . implode(' AND ', $unassignedConditions) . "
               AND NOT EXISTS (
                    SELECT 1
                    FROM student_enrollments e
                    WHERE e.student_id = s.id
                      AND e.tenant_id = s.tenant_id
                      AND e.branch_id = s.branch_id
                      AND e.academic_year_id = :academic_year_id
                      AND e.enrollment_status = 'active'
               )"
        );
        $unassignedStatement->execute($unassignedParams);
        $result['unassigned'] =
            (int)$unassignedStatement->fetchColumn();
    }

    return $result;
}

function classApplyLiveStrengths(
    PDO $pdo,
    array $scope,
    array $classes,
    array $strengthMap
): array {
    if (!classTableExists($pdo, 'class_management_classes')) {
        return $classes;
    }

    $update = $pdo->prepare(
        "UPDATE class_management_classes
         SET current_strength = :current_strength
         WHERE id = :id
           AND tenant_id = :tenant_id
           AND branch_id = @schoolerp_branch_id"
    );

    foreach ($classes as &$classRow) {
        if (!is_array($classRow)) {
            continue;
        }

        $yearId = (int)($classRow['academic_year_id'] ?? 0);
        $className = (string)($classRow['class_name'] ?? '');
        $sectionName = (string)($classRow['section_name'] ?? 'General');

        $exactKey = classStrengthKey(
            $yearId,
            $className,
            $sectionName
        );
        $classKey = classStrengthKey(
            $yearId,
            $className
        );

        $strength = $strengthMap['exact'][$exactKey]
            ?? (
                in_array(
                    classNormal($sectionName),
                    ['', 'general', 'all', 'all sections'],
                    true
                )
                    ? ($strengthMap['class'][$classKey] ?? 0)
                    : 0
            );

        $classRow['stored_current_strength'] =
            (int)($classRow['current_strength'] ?? 0);
        $classRow['current_strength'] = (int)$strength;
        $classRow['strength_source'] = 'student_enrollments';

        if ((int)($classRow['id'] ?? 0) > 0) {
            $update->execute([
                'current_strength' => (int)$strength,
                'id' => (int)$classRow['id'],
                'tenant_id' => $scope['tenant_id'],
            ]);
        }
    }
    unset($classRow);

    return $classes;
}


/**
 * Resolve the exact canonical class + section represented by one
 * class_management_classes row.  Student checks must be section-wise; using
 * only class_name/class_id can incorrectly block deletion of an empty
 * "General" row when another section of the same class contains students.
 */
function classDeleteState(PDO $pdo, array $scope, array $row): array
{
    $state = [
        'can_delete' => true,
        'linked_students' => 0,
        'canonical_class_id' => 0,
        'canonical_section_id' => 0,
        'has_other_sections' => false,
    ];

    $tenantId = (int)($scope['tenant_id'] ?? 0);
    $yearId = (int)($row['academic_year_id'] ?? 0);
    $className = trim((string)($row['class_name'] ?? ''));
    $sectionName = trim((string)($row['section_name'] ?? '')) ?: 'General';

    if ($tenantId <= 0 || $yearId <= 0 || $className === '') {
        return $state;
    }

    if (!classTableExists($pdo, 'classes')) {
        return $state;
    }

    $classStmt = $pdo->prepare(
        "SELECT id
         FROM classes
         WHERE tenant_id = :tenant_id
           AND branch_id = @schoolerp_branch_id
           AND academic_year_id = :academic_year_id
           AND LOWER(TRIM(class_name)) = LOWER(TRIM(:class_name))
         LIMIT 1"
    );
    $classStmt->execute([
        'tenant_id' => $tenantId,
        'academic_year_id' => $yearId,
        'class_name' => $className,
    ]);
    $classId = (int)($classStmt->fetchColumn() ?: 0);
    $state['canonical_class_id'] = $classId;

    if ($classId <= 0) {
        return $state;
    }

    $sectionId = 0;
    $sectionCount = 0;
    if (classTableExists($pdo, 'sections')) {
        $sectionStmt = $pdo->prepare(
            "SELECT id
             FROM sections
             WHERE tenant_id = :tenant_id
               AND branch_id = @schoolerp_branch_id
               AND class_id = :class_id
               AND LOWER(TRIM(section_name)) = LOWER(TRIM(:section_name))
             LIMIT 1"
        );
        $sectionStmt->execute([
            'tenant_id' => $tenantId,
            'class_id' => $classId,
            'section_name' => $sectionName,
        ]);
        $sectionId = (int)($sectionStmt->fetchColumn() ?: 0);

        $countStmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM sections
             WHERE tenant_id = :tenant_id
               AND branch_id = @schoolerp_branch_id
               AND class_id = :class_id"
        );
        $countStmt->execute([
            'tenant_id' => $tenantId,
            'class_id' => $classId,
        ]);
        $sectionCount = (int)$countStmt->fetchColumn();
    }

    $state['canonical_section_id'] = $sectionId;
    $state['has_other_sections'] = $sectionCount > ($sectionId > 0 ? 1 : 0);

    if (!classTableExists($pdo, 'student_enrollments')) {
        return $state;
    }

    $where = [
        'tenant_id = :tenant_id',
        'branch_id = @schoolerp_branch_id',
        'academic_year_id = :academic_year_id',
        'class_id = :class_id',
    ];
    $params = [
        'tenant_id' => $tenantId,
        'academic_year_id' => $yearId,
        'class_id' => $classId,
    ];

    if ($sectionId > 0) {
        $where[] = 'section_id = :section_id';
        $params['section_id'] = $sectionId;
    } elseif ($sectionCount > 0) {
        /*
         * This management row has no matching canonical section, while the
         * class does have real sections.  It is an orphan/helper row (commonly
         * "General"), therefore students in A/B/etc. must not block deleting it.
         */
        $state['linked_students'] = 0;
        $state['can_delete'] = true;
        return $state;
    }

    $studentStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM student_enrollments WHERE " . implode(' AND ', $where)
    );
    $studentStmt->execute($params);
    $linked = (int)$studentStmt->fetchColumn();

    $state['linked_students'] = $linked;
    $state['can_delete'] = $linked === 0;
    return $state;
}

function classDecorateDeleteState(PDO $pdo, array $scope, array $classes): array
{
    foreach ($classes as &$row) {
        if (!is_array($row)) {
            continue;
        }
        $state = classDeleteState($pdo, $scope, $row);
        $row['can_delete'] = (bool)$state['can_delete'];
        $row['linked_students'] = (int)$state['linked_students'];
    }
    unset($row);
    return $classes;
}

/**
 * Permanently delete an empty Class Management row and its exact canonical
 * section.  If the row represents the last/no section of the canonical class,
 * delete the canonical class too when nothing else references it.
 */
function classDeleteEmptyClass(
    PDO $pdo,
    array $scope,
    ClassManagementController $controller,
    int $managementId
): void {
    if ($managementId <= 0) {
        throw new InvalidArgumentException('Invalid class selected.');
    }

    if (!$controller->can('delete')) {
        throw new RuntimeException('You do not have permission to delete classes.', 403);
    }

    $statement = $pdo->prepare(
        "SELECT id, tenant_id, academic_year_id, class_name, section_name
         FROM class_management_classes
         WHERE id = :id
           AND tenant_id = :tenant_id
           AND branch_id = @schoolerp_branch_id
         LIMIT 1"
    );
    $statement->execute([
        'id' => $managementId,
        'tenant_id' => $scope['tenant_id'],
    ]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);

    if (!is_array($row)) {
        throw new InvalidArgumentException('Class Management record was not found.');
    }

    /*
     * class_management_classes is a management/configuration table.
     * Student placement is stored in student_enrollments -> classes/sections.
     * Therefore deleting a dummy Class Management row must NOT delete or modify:
     *   - classes
     *   - sections
     *   - school_sections
     *   - student_enrollments
     *
     * Foreign keys owned by Class Management helper tables use ON DELETE CASCADE,
     * so any helper rows that belong only to this management record are cleaned
     * by MariaDB automatically.
     */
    $delete = $pdo->prepare(
        "DELETE FROM class_management_classes
         WHERE id = :id
           AND tenant_id = :tenant_id
           AND branch_id = @schoolerp_branch_id"
    );
    $delete->execute([
        'id' => $managementId,
        'tenant_id' => $scope['tenant_id'],
    ]);

    if ($delete->rowCount() !== 1) {
        throw new RuntimeException('Unable to delete the Class Management record.');
    }
}


/**
 * Save Class Management through the REAL School ERP class tables.
 *
 * Source of truth:
 *   academic_years.id -> classes.academic_year_id
 *   classes.id        -> sections.class_id
 *
 * class_management_classes only stores the additional Class Management UI
 * fields (code, medium, shift, teacher, room, description, etc.).
 *
 * A normal list/refresh never creates canonical or management rows.
 * Only the explicit Add/Edit action calls this function.
 */
function classSaveDatabaseClass(
    PDO $pdo,
    array $scope,
    ClassManagementController $controller,
    array $input
): array {
    $tenantId = (int)($scope['tenant_id'] ?? 0);
    $userId = (int)($scope['user_id'] ?? 0);
    $managementId = max(0, (int)($input['id'] ?? 0));
    $isEdit = $managementId > 0;

    if (!$controller->can($isEdit ? 'edit' : 'create')) {
        throw new RuntimeException(
            $isEdit
                ? 'You do not have permission to edit classes.'
                : 'You do not have permission to create classes.',
            403
        );
    }

    if (
        $tenantId <= 0
        || !classTableExists($pdo, 'academic_years')
        || !classTableExists($pdo, 'classes')
        || !classTableExists($pdo, 'sections')
        || !classTableExists($pdo, 'class_management_classes')
    ) {
        throw new RuntimeException(
            'Required Class Management database tables are unavailable.',
            500
        );
    }

    $academicYearId = max(0, (int)($input['academic_year_id'] ?? 0));
    $className = trim((string)($input['class_name'] ?? ''));
    $classCode = strtoupper(trim((string)($input['class_code'] ?? '')));
    $sectionName = trim((string)($input['section_name'] ?? ''));
    $medium = trim((string)($input['medium'] ?? '')) ?: 'English';
    $shiftName = trim((string)($input['shift_name'] ?? ''));
    $teacherUserId = max(0, (int)($input['class_teacher_user_id'] ?? 0));
    $teacherName = trim((string)($input['class_teacher_name'] ?? ''));
    $classroomName = trim((string)($input['classroom_name'] ?? ''));
    $maximumStrength = max(1, min(500, (int)($input['maximum_strength'] ?? 40)));
    $status = strtolower(trim((string)($input['status'] ?? 'active')));
    $description = trim((string)($input['description'] ?? ''));
    $displayOrder = max(0, (int)($input['display_order'] ?? 0));

    if ($academicYearId <= 0) {
        throw new InvalidArgumentException('Select an Academic Year.');
    }
    if ($className === '') {
        throw new InvalidArgumentException('Class Name is required.');
    }
    if ($classCode === '') {
        throw new InvalidArgumentException('Class Code is required.');
    }
    if ($sectionName === '') {
        throw new InvalidArgumentException('Section is required.');
    }

    classRequireTeacherBranch(
        $pdo,
        $scope,
        $teacherUserId
    );
    if (!in_array($status, ['active', 'inactive', 'archived'], true)) {
        throw new InvalidArgumentException('Invalid Class status.');
    }

    $className = mb_substr($className, 0, 80);
    $classCode = mb_substr($classCode, 0, 30);
    $sectionName = mb_substr($sectionName, 0, 30);
    $medium = mb_substr($medium, 0, 50);
    $shiftName = mb_substr($shiftName, 0, 50);
    $teacherName = mb_substr($teacherName, 0, 150);
    $classroomName = mb_substr($classroomName, 0, 100);
    $description = mb_substr($description, 0, 5000);

    $yearStatement = $pdo->prepare(
        "SELECT id
         FROM academic_years
         WHERE id = :id
           AND tenant_id = :tenant_id
           AND branch_id = :branch_id
         LIMIT 1"
    );
    $yearStatement->execute([
        'id' => $academicYearId,
        'tenant_id' => $tenantId,
        'branch_id' => (int)$scope['branch_id'],
    ]);
    if (!(int)$yearStatement->fetchColumn()) {
        throw new InvalidArgumentException(
            'Selected Academic Year does not belong to the active Branch.'
        );
    }

    $oldRow = null;
    if ($isEdit) {
        $oldStatement = $pdo->prepare(
            "SELECT *
             FROM class_management_classes
             WHERE id = :id
               AND tenant_id = :tenant_id
               AND branch_id = @schoolerp_branch_id
             LIMIT 1"
        );
        $oldStatement->execute([
            'id' => $managementId,
            'tenant_id' => $tenantId,
        ]);
        $oldRow = $oldStatement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($oldRow)) {
            throw new InvalidArgumentException(
                'Class Management record was not found.'
            );
        }
    }

    /*
     * Prevent duplicate management rows and duplicate class codes before
     * touching canonical tables.
     */
    $duplicateStatement = $pdo->prepare(
        "SELECT id
         FROM class_management_classes
         WHERE tenant_id = :tenant_id
           AND branch_id = @schoolerp_branch_id
           AND academic_year_id = :academic_year_id
           AND LOWER(TRIM(class_name)) = LOWER(TRIM(:class_name))
           AND LOWER(TRIM(section_name)) = LOWER(TRIM(:section_name))
           AND id <> :id
         LIMIT 1"
    );
    $duplicateStatement->execute([
        'tenant_id' => $tenantId,
        'academic_year_id' => $academicYearId,
        'class_name' => $className,
        'section_name' => $sectionName,
        'id' => $managementId,
    ]);
    if ((int)$duplicateStatement->fetchColumn() > 0) {
        throw new InvalidArgumentException(
            'This Class and Section already exists for the selected Academic Year.'
        );
    }

    $codeStatement = $pdo->prepare(
        "SELECT id
         FROM class_management_classes
         WHERE tenant_id = :tenant_id
           AND branch_id = @schoolerp_branch_id
           AND academic_year_id = :academic_year_id
           AND class_code = :class_code
           AND id <> :id
         LIMIT 1"
    );
    $codeStatement->execute([
        'tenant_id' => $tenantId,
        'academic_year_id' => $academicYearId,
        'class_code' => $classCode,
        'id' => $managementId,
    ]);
    if ((int)$codeStatement->fetchColumn() > 0) {
        throw new InvalidArgumentException(
            'Class Code already exists in the selected Academic Year.'
        );
    }

    /*
     * If Edit changes Academic Year / Class Name / Section, make sure the old
     * exact class+section has no student history.  We never silently move
     * student_enrollments while editing a Class master.
     */
    $oldClassId = 0;
    $oldSectionId = 0;
    $identityChanged = false;

    if (is_array($oldRow)) {
        $identityChanged =
            (int)$oldRow['academic_year_id'] !== $academicYearId
            || classNormal((string)$oldRow['class_name']) !== classNormal($className)
            || classNormal((string)$oldRow['section_name']) !== classNormal($sectionName);

        $oldClassStatement = $pdo->prepare(
            "SELECT id
             FROM classes
             WHERE tenant_id = :tenant_id
               AND branch_id = @schoolerp_branch_id
               AND academic_year_id = :academic_year_id
               AND LOWER(TRIM(class_name)) = LOWER(TRIM(:class_name))
             LIMIT 1"
        );
        $oldClassStatement->execute([
            'tenant_id' => $tenantId,
            'academic_year_id' => (int)$oldRow['academic_year_id'],
            'class_name' => (string)$oldRow['class_name'],
        ]);
        $oldClassId = (int)($oldClassStatement->fetchColumn() ?: 0);

        if ($oldClassId > 0) {
            $oldSectionStatement = $pdo->prepare(
                "SELECT id
                 FROM sections
                 WHERE tenant_id = :tenant_id
                   AND branch_id = @schoolerp_branch_id
                   AND class_id = :class_id
                   AND LOWER(TRIM(section_name)) = LOWER(TRIM(:section_name))
                 LIMIT 1"
            );
            $oldSectionStatement->execute([
                'tenant_id' => $tenantId,
                'class_id' => $oldClassId,
                'section_name' => (string)$oldRow['section_name'],
            ]);
            $oldSectionId = (int)($oldSectionStatement->fetchColumn() ?: 0);
        }

        if (
            $identityChanged
            && $oldClassId > 0
            && classTableExists($pdo, 'student_enrollments')
        ) {
            $where = [
                'tenant_id = :tenant_id',
                'branch_id = @schoolerp_branch_id',
                'academic_year_id = :academic_year_id',
                'class_id = :class_id',
            ];
            $params = [
                'tenant_id' => $tenantId,
                'academic_year_id' => (int)$oldRow['academic_year_id'],
                'class_id' => $oldClassId,
            ];

            if ($oldSectionId > 0) {
                $where[] = 'section_id = :section_id';
                $params['section_id'] = $oldSectionId;
            }

            $studentStatement = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM student_enrollments
                 WHERE " . implode(' AND ', $where)
            );
            $studentStatement->execute($params);

            if ((int)$studentStatement->fetchColumn() > 0) {
                throw new InvalidArgumentException(
                    'Students are linked to this Class / Section. '
                    . 'You may edit teacher, room, shift, capacity and other details, '
                    . 'but Academic Year, Class Name or Section cannot be changed.'
                );
            }
        }
    }

    $canonicalStatus = $status === 'active' ? 'active' : 'inactive';
    $sectionStatus = $canonicalStatus;

    $pdo->beginTransaction();

    try {
        /*
         * 1. Find or create the REAL classes row.
         */
        $classStatement = $pdo->prepare(
            "SELECT id
             FROM classes
             WHERE tenant_id = :tenant_id
               AND branch_id = @schoolerp_branch_id
               AND academic_year_id = :academic_year_id
               AND LOWER(TRIM(class_name)) = LOWER(TRIM(:class_name))
             LIMIT 1
             FOR UPDATE"
        );
        $classStatement->execute([
            'tenant_id' => $tenantId,
            'academic_year_id' => $academicYearId,
            'class_name' => $className,
        ]);
        $classId = (int)($classStatement->fetchColumn() ?: 0);

        if ($classId <= 0) {
            $insertClass = $pdo->prepare(
                "INSERT INTO classes(
                    tenant_id,
                    branch_id,
                    academic_year_id,
                    class_name,
                    display_order,
                    status
                 ) VALUES(
                    :tenant_id,
                    @schoolerp_branch_id,
                    :academic_year_id,
                    :class_name,
                    :display_order,
                    :status
                 )"
            );
            $insertClass->execute([
                'tenant_id' => $tenantId,
                'academic_year_id' => $academicYearId,
                'class_name' => $className,
                'display_order' => $displayOrder,
                'status' => $canonicalStatus,
            ]);
            $classId = (int)$pdo->lastInsertId();
        } else {
            /*
             * display_order/status belong to the canonical Class itself and are
             * intentionally shared by all sections of this class.
             */
            $updateClass = $pdo->prepare(
                "UPDATE classes
                 SET display_order = :display_order,
                     status = :status
                 WHERE id = :id
                   AND tenant_id = :tenant_id
                   AND branch_id = @schoolerp_branch_id"
            );
            $updateClass->execute([
                'display_order' => $displayOrder,
                'status' => $canonicalStatus,
                'id' => $classId,
                'tenant_id' => $tenantId,
            ]);
        }

        /*
         * 2. Find or create the REAL sections row.
         */
        $sectionStatement = $pdo->prepare(
            "SELECT id
             FROM sections
             WHERE tenant_id = :tenant_id
               AND branch_id = @schoolerp_branch_id
               AND class_id = :class_id
               AND LOWER(TRIM(section_name)) = LOWER(TRIM(:section_name))
             LIMIT 1
             FOR UPDATE"
        );
        $sectionStatement->execute([
            'tenant_id' => $tenantId,
            'class_id' => $classId,
            'section_name' => $sectionName,
        ]);
        $sectionId = (int)($sectionStatement->fetchColumn() ?: 0);

        if ($sectionId <= 0) {
            $insertSection = $pdo->prepare(
                "INSERT INTO sections(
                    tenant_id,
                    branch_id,
                    class_id,
                    section_name,
                    capacity,
                    status
                 ) VALUES(
                    :tenant_id,
                    @schoolerp_branch_id,
                    :class_id,
                    :section_name,
                    :capacity,
                    :status
                 )"
            );
            $insertSection->execute([
                'tenant_id' => $tenantId,
                'class_id' => $classId,
                'section_name' => $sectionName,
                'capacity' => $maximumStrength,
                'status' => $sectionStatus,
            ]);
            $sectionId = (int)$pdo->lastInsertId();
        } else {
            $updateSection = $pdo->prepare(
                "UPDATE sections
                 SET capacity = :capacity,
                     status = :status
                 WHERE id = :id
                   AND tenant_id = :tenant_id
                   AND branch_id = @schoolerp_branch_id"
            );
            $updateSection->execute([
                'capacity' => $maximumStrength,
                'status' => $sectionStatus,
                'id' => $sectionId,
                'tenant_id' => $tenantId,
            ]);
        }

        /*
         * 3. Save the richer Class Management record.
         */
        if ($isEdit) {
            $managementStatement = $pdo->prepare(
                "UPDATE class_management_classes SET
                    academic_year_id = :academic_year_id,
                    class_name = :class_name,
                    class_code = :class_code,
                    section_name = :section_name,
                    medium = :medium,
                    shift_name = :shift_name,
                    class_teacher_user_id = :class_teacher_user_id,
                    class_teacher_name = :class_teacher_name,
                    classroom_name = :classroom_name,
                    maximum_strength = :maximum_strength,
                    status = :status,
                    description = :description,
                    display_order = :display_order,
                    updated_by = :updated_by
                 WHERE id = :id
                   AND tenant_id = :tenant_id
                   AND branch_id = @schoolerp_branch_id"
            );
            $managementStatement->execute([
                'academic_year_id' => $academicYearId,
                'class_name' => $className,
                'class_code' => $classCode,
                'section_name' => $sectionName,
                'medium' => $medium,
                'shift_name' => $shiftName,
                'class_teacher_user_id' => $teacherUserId ?: null,
                'class_teacher_name' => $teacherName !== '' ? $teacherName : null,
                'classroom_name' => $classroomName !== '' ? $classroomName : null,
                'maximum_strength' => $maximumStrength,
                'status' => $status,
                'description' => $description !== '' ? $description : null,
                'display_order' => $displayOrder,
                'updated_by' => $userId ?: null,
                'id' => $managementId,
                'tenant_id' => $tenantId,
            ]);
        } else {
            $managementStatement = $pdo->prepare(
                "INSERT INTO class_management_classes(
                    tenant_id,
                    branch_id,
                    academic_year_id,
                    class_name,
                    class_code,
                    section_name,
                    medium,
                    shift_name,
                    class_teacher_user_id,
                    class_teacher_name,
                    classroom_name,
                    maximum_strength,
                    current_strength,
                    status,
                    description,
                    display_order,
                    created_by,
                    updated_by
                 ) VALUES(
                    :tenant_id,
                    @schoolerp_branch_id,
                    :academic_year_id,
                    :class_name,
                    :class_code,
                    :section_name,
                    :medium,
                    :shift_name,
                    :class_teacher_user_id,
                    :class_teacher_name,
                    :classroom_name,
                    :maximum_strength,
                    0,
                    :status,
                    :description,
                    :display_order,
                    :created_by,
                    :updated_by
                 )"
            );
            $managementStatement->execute([
                'tenant_id' => $tenantId,
                'academic_year_id' => $academicYearId,
                'class_name' => $className,
                'class_code' => $classCode,
                'section_name' => $sectionName,
                'medium' => $medium,
                'shift_name' => $shiftName,
                'class_teacher_user_id' => $teacherUserId ?: null,
                'class_teacher_name' => $teacherName !== '' ? $teacherName : null,
                'classroom_name' => $classroomName !== '' ? $classroomName : null,
                'maximum_strength' => $maximumStrength,
                'status' => $status,
                'description' => $description !== '' ? $description : null,
                'display_order' => $displayOrder,
                'created_by' => $userId ?: null,
                'updated_by' => $userId ?: null,
            ]);
            $managementId = (int)$pdo->lastInsertId();
        }

        /*
         * 4. If an Edit moved an EMPTY record to another class/section,
         * remove the now-unused old canonical section/class.  Never remove
         * history or fee structures.
         */
        if (
            $identityChanged
            && is_array($oldRow)
            && $oldClassId > 0
        ) {
            if ($oldSectionId > 0) {
                $otherManagement = $pdo->prepare(
                    "SELECT COUNT(*)
                     FROM class_management_classes
                     WHERE tenant_id = :tenant_id
                       AND branch_id = @schoolerp_branch_id
                       AND academic_year_id = :academic_year_id
                       AND LOWER(TRIM(class_name)) = LOWER(TRIM(:class_name))
                       AND LOWER(TRIM(section_name)) = LOWER(TRIM(:section_name))
                       AND id <> :id"
                );
                $otherManagement->execute([
                    'tenant_id' => $tenantId,
                    'academic_year_id' => (int)$oldRow['academic_year_id'],
                    'class_name' => (string)$oldRow['class_name'],
                    'section_name' => (string)$oldRow['section_name'],
                    'id' => $managementId,
                ]);

                $studentCount = 0;
                if (classTableExists($pdo, 'student_enrollments')) {
                    $oldStudent = $pdo->prepare(
                        "SELECT COUNT(*)
                         FROM student_enrollments
                         WHERE tenant_id = :tenant_id
                           AND branch_id = @schoolerp_branch_id
                           AND class_id = :class_id
                           AND section_id = :section_id"
                    );
                    $oldStudent->execute([
                        'tenant_id' => $tenantId,
                        'class_id' => $oldClassId,
                        'section_id' => $oldSectionId,
                    ]);
                    $studentCount = (int)$oldStudent->fetchColumn();
                }

                if (
                    (int)$otherManagement->fetchColumn() === 0
                    && $studentCount === 0
                ) {
                    $deleteOldSection = $pdo->prepare(
                        "DELETE FROM sections
                         WHERE id = :id
                           AND tenant_id = :tenant_id
                           AND branch_id = @schoolerp_branch_id"
                    );
                    $deleteOldSection->execute([
                        'id' => $oldSectionId,
                        'tenant_id' => $tenantId,
                    ]);
                }
            }

            $remainingSections = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM sections
                 WHERE tenant_id = :tenant_id
                   AND branch_id = @schoolerp_branch_id
                   AND class_id = :class_id"
            );
            $remainingSections->execute([
                'tenant_id' => $tenantId,
                'class_id' => $oldClassId,
            ]);

            if ((int)$remainingSections->fetchColumn() === 0) {
                $classStudentCount = 0;
                if (classTableExists($pdo, 'student_enrollments')) {
                    $oldClassStudents = $pdo->prepare(
                        "SELECT COUNT(*)
                         FROM student_enrollments
                         WHERE tenant_id = :tenant_id
                           AND branch_id = @schoolerp_branch_id
                           AND class_id = :class_id"
                    );
                    $oldClassStudents->execute([
                        'tenant_id' => $tenantId,
                        'class_id' => $oldClassId,
                    ]);
                    $classStudentCount = (int)$oldClassStudents->fetchColumn();
                }

                $feeStructureCount = 0;
                if (classTableExists($pdo, 'fee_structures')) {
                    $feeStatement = $pdo->prepare(
                        "SELECT COUNT(*)
                         FROM fee_structures
                         WHERE tenant_id = :tenant_id
                           AND branch_id = @schoolerp_branch_id
                           AND class_id = :class_id"
                    );
                    $feeStatement->execute([
                        'tenant_id' => $tenantId,
                        'class_id' => $oldClassId,
                    ]);
                    $feeStructureCount = (int)$feeStatement->fetchColumn();
                }

                if ($classStudentCount === 0 && $feeStructureCount === 0) {
                    $deleteOldClass = $pdo->prepare(
                        "DELETE FROM classes
                         WHERE id = :id
                           AND tenant_id = :tenant_id
                           AND branch_id = @schoolerp_branch_id"
                    );
                    $deleteOldClass->execute([
                        'id' => $oldClassId,
                        'tenant_id' => $tenantId,
                    ]);
                }
            }
        }

        $pdo->commit();

        return [
            'id' => $managementId,
            'class_id' => $classId,
            'section_id' => $sectionId,
            'academic_year_id' => $academicYearId,
        ];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if (
            $exception instanceof PDOException
            && (string)$exception->getCode() === '23000'
        ) {
            throw new InvalidArgumentException(
                'This Class / Section or Class Code already exists.'
            );
        }

        throw $exception;
    }
}

/**
 * Delete the exact Class Management row from all class master tables.
 *
 * If students are assigned (active or historical) the delete is blocked.
 * For an empty row:
 *   1. class_management_classes is deleted.
 *   2. the matching sections row is deleted.
 *   3. the classes row is deleted only when no sections remain and no
 *      fee/student dependency references it.
 *
 * Old orphan/dummy management rows that have no matching canonical
 * class/section are simply removed from class_management_classes.
 */
function classDeleteDatabaseClass(
    PDO $pdo,
    array $scope,
    ClassManagementController $controller,
    int $managementId
): array {
    if ($managementId <= 0) {
        throw new InvalidArgumentException('Invalid class selected.');
    }

    if (!$controller->can('delete')) {
        throw new RuntimeException(
            'You do not have permission to delete classes.',
            403
        );
    }

    $tenantId = (int)($scope['tenant_id'] ?? 0);

    $rowStatement = $pdo->prepare(
        "SELECT id, academic_year_id, class_name, section_name
         FROM class_management_classes
         WHERE id = :id
           AND tenant_id = :tenant_id
           AND branch_id = @schoolerp_branch_id
         LIMIT 1"
    );
    $rowStatement->execute([
        'id' => $managementId,
        'tenant_id' => $tenantId,
    ]);
    $row = $rowStatement->fetch(PDO::FETCH_ASSOC);

    if (!is_array($row)) {
        throw new InvalidArgumentException(
            'Class Management record was not found.'
        );
    }

    $academicYearId = (int)$row['academic_year_id'];
    $classId = 0;
    $sectionId = 0;

    if (classTableExists($pdo, 'classes')) {
        $classStatement = $pdo->prepare(
            "SELECT id
             FROM classes
             WHERE tenant_id = :tenant_id
               AND branch_id = @schoolerp_branch_id
               AND academic_year_id = :academic_year_id
               AND LOWER(TRIM(class_name)) = LOWER(TRIM(:class_name))
             LIMIT 1"
        );
        $classStatement->execute([
            'tenant_id' => $tenantId,
            'academic_year_id' => $academicYearId,
            'class_name' => (string)$row['class_name'],
        ]);
        $classId = (int)($classStatement->fetchColumn() ?: 0);
    }

    if ($classId > 0 && classTableExists($pdo, 'sections')) {
        $sectionStatement = $pdo->prepare(
            "SELECT id
             FROM sections
             WHERE tenant_id = :tenant_id
               AND branch_id = @schoolerp_branch_id
               AND class_id = :class_id
               AND LOWER(TRIM(section_name)) = LOWER(TRIM(:section_name))
             LIMIT 1"
        );
        $sectionStatement->execute([
            'tenant_id' => $tenantId,
            'class_id' => $classId,
            'section_name' => (string)$row['section_name'],
        ]);
        $sectionId = (int)($sectionStatement->fetchColumn() ?: 0);
    }

    /*
     * IMPORTANT DELETE BEHAVIOUR
     * --------------------------
     * The Class Management row is allowed to be deleted even when students
     * are already linked to the canonical class/section.
     *
     * In that case student_enrollments, classes and sections are preserved so
     * no student academic history is lost and no foreign-key constraint is
     * broken. The class disappears from Class Management because the page list
     * reads class_management_classes and does not auto-create deleted rows.
     */
    $linkedStudentCount = 0;

    if ($classId > 0 && classTableExists($pdo, 'student_enrollments')) {
        $where = [
            'tenant_id = :tenant_id',
            'branch_id = @schoolerp_branch_id',
            'academic_year_id = :academic_year_id',
            'class_id = :class_id',
        ];
        $params = [
            'tenant_id' => $tenantId,
            'academic_year_id' => $academicYearId,
            'class_id' => $classId,
        ];

        if ($sectionId > 0) {
            $where[] = 'section_id = :section_id';
            $params['section_id'] = $sectionId;
        } elseif (classTableExists($pdo, 'sections')) {
            /*
             * No exact canonical section means this is only a management row.
             * Students in other real sections must not be treated as belonging
             * to this exact management section.
             */
            $realSectionStatement = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM sections
                 WHERE tenant_id = :tenant_id
                   AND branch_id = @schoolerp_branch_id
                   AND class_id = :class_id"
            );
            $realSectionStatement->execute([
                'tenant_id' => $tenantId,
                'class_id' => $classId,
            ]);

            if ((int)$realSectionStatement->fetchColumn() > 0) {
                $where = [];
            }
        }

        if ($where) {
            $studentStatement = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM student_enrollments
                 WHERE " . implode(' AND ', $where)
            );
            $studentStatement->execute($params);
            $linkedStudentCount = (int)$studentStatement->fetchColumn();
        }
    }

    /*
     * Step 1: Always delete the selected Class Management record.
     * This is the record displayed on school/classes.php.
     */
    $pdo->beginTransaction();
    try {
        $deleteManagement = $pdo->prepare(
            "DELETE FROM class_management_classes
             WHERE id = :id
               AND tenant_id = :tenant_id
               AND branch_id = @schoolerp_branch_id"
        );
        $deleteManagement->execute([
            'id' => $managementId,
            'tenant_id' => $tenantId,
        ]);

        if ($deleteManagement->rowCount() !== 1) {
            throw new RuntimeException(
                'Unable to delete the Class Management record.'
            );
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if (
            $exception instanceof PDOException
            && (string)$exception->getCode() === '23000'
        ) {
            throw new InvalidArgumentException(
                'This Class Management record is used by another module and cannot be deleted.'
            );
        }

        throw $exception;
    }

    /*
     * If students are linked, stop here. Their enrollment history and the
     * canonical academic structure must remain intact.
     */
    if ($linkedStudentCount > 0) {
        return [
            'management_id' => $managementId,
            'class_id' => $classId,
            'section_id' => $sectionId,
            'linked_students' => $linkedStudentCount,
            'management_deleted' => true,
            'canonical_section_deleted' => false,
            'canonical_class_deleted' => false,
            'student_records_preserved' => true,
        ];
    }

    /*
     * Step 2: No students are linked to this exact class/section. Clean the
     * canonical section/class only when doing so is safe. Failure of this
     * optional cleanup must NOT bring back the already deleted management row.
     */
    $canonicalSectionDeleted = false;
    $canonicalClassDeleted = false;

    if ($classId > 0 && $sectionId > 0 && classTableExists($pdo, 'sections')) {
        $sectionCountStatement = $pdo->prepare(
            "SELECT COUNT(*)
             FROM sections
             WHERE tenant_id = :tenant_id
               AND branch_id = @schoolerp_branch_id
               AND class_id = :class_id"
        );
        $sectionCountStatement->execute([
            'tenant_id' => $tenantId,
            'class_id' => $classId,
        ]);
        $sectionCount = (int)$sectionCountStatement->fetchColumn();
        $isLastSection = $sectionCount <= 1;

        $canRemoveLastCanonicalClass = true;

        if ($isLastSection && classTableExists($pdo, 'student_enrollments')) {
            $allStudentStatement = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM student_enrollments
                 WHERE tenant_id = :tenant_id
                   AND branch_id = @schoolerp_branch_id
                   AND academic_year_id = :academic_year_id
                   AND class_id = :class_id"
            );
            $allStudentStatement->execute([
                'tenant_id' => $tenantId,
                'academic_year_id' => $academicYearId,
                'class_id' => $classId,
            ]);
            $canRemoveLastCanonicalClass =
                (int)$allStudentStatement->fetchColumn() === 0;
        }

        if ($isLastSection && $canRemoveLastCanonicalClass && classTableExists($pdo, 'fee_structures')) {
            $feeStatement = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM fee_structures
                 WHERE tenant_id = :tenant_id
                   AND branch_id = @schoolerp_branch_id
                   AND class_id = :class_id"
            );
            $feeStatement->execute([
                'tenant_id' => $tenantId,
                'class_id' => $classId,
            ]);
            if ((int)$feeStatement->fetchColumn() > 0) {
                $canRemoveLastCanonicalClass = false;
            }
        }

        if ($isLastSection && $canRemoveLastCanonicalClass && classTableExists($pdo, 'admission_applications')) {
            $admissionStatement = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM admission_applications
                 WHERE tenant_id = :tenant_id
                   AND branch_id = @schoolerp_branch_id
                   AND applied_class_id = :class_id"
            );
            $admissionStatement->execute([
                'tenant_id' => $tenantId,
                'class_id' => $classId,
            ]);
            if ((int)$admissionStatement->fetchColumn() > 0) {
                $canRemoveLastCanonicalClass = false;
            }
        }

        /*
         * If this is the last section but the class itself still has business
         * dependencies, keep the canonical class+section together. The
         * management record has already been deleted successfully.
         */
        $canCleanupCanonical = !$isLastSection || $canRemoveLastCanonicalClass;

        if ($canCleanupCanonical) {
            try {
                $pdo->beginTransaction();

                $deleteSection = $pdo->prepare(
                    "DELETE FROM sections
                     WHERE id = :id
                       AND tenant_id = :tenant_id
                       AND branch_id = @schoolerp_branch_id"
                );
                $deleteSection->execute([
                    'id' => $sectionId,
                    'tenant_id' => $tenantId,
                ]);
                $canonicalSectionDeleted = $deleteSection->rowCount() === 1;

                if ($isLastSection && $canonicalSectionDeleted) {
                    $deleteClass = $pdo->prepare(
                        "DELETE FROM classes
                         WHERE id = :id
                           AND tenant_id = :tenant_id
                           AND branch_id = @schoolerp_branch_id"
                    );
                    $deleteClass->execute([
                        'id' => $classId,
                        'tenant_id' => $tenantId,
                    ]);
                    $canonicalClassDeleted = $deleteClass->rowCount() === 1;
                }

                $pdo->commit();
            } catch (Throwable $cleanupException) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $canonicalSectionDeleted = false;
                $canonicalClassDeleted = false;
                error_log(
                    'classes-api canonical cleanup skipped after management delete: '
                    . $cleanupException->getMessage()
                );
            }
        }
    }

    return [
        'management_id' => $managementId,
        'class_id' => $classId,
        'section_id' => $sectionId,
        'linked_students' => 0,
        'management_deleted' => true,
        'canonical_section_deleted' => $canonicalSectionDeleted,
        'canonical_class_deleted' => $canonicalClassDeleted,
        'student_records_preserved' => true,
    ];
}

function classConfiguredShifts(PDO $pdo, int $tenantId): array
{
    if ($tenantId <= 0) return [];
    return school_settings_enabled_shifts($pdo, $tenantId);
}

function classRequireConfiguredShift(PDO $pdo, int $tenantId, string $shiftName): array
{
    $matched = school_settings_shift_allowed($pdo, $tenantId, $shiftName);
    if (!$matched) {
        throw new InvalidArgumentException(
            'Selected Shift Time is not enabled in General Settings. Configure and enable the shift first.'
        );
    }
    return $matched;
}

if (!isset($pdo) || !$pdo instanceof PDO) {
    classJson(false, 'Database unavailable.', [], 500);
}

$user = function_exists('current_user') ? current_user() : [];
$user = is_array($user) ? $user : [];

$controller = new ClassManagementController($pdo, $user);
$input = classInput();
$action = strtolower(
    trim((string)($input['action'] ?? $_GET['action'] ?? ''))
);

try {
    if ($action === 'meta') {
        $scope = classStrictScope(
            $pdo,
            $user,
            $controller
        );

        $branchDataEnabled =
            classBranchClassesFileEnabled(
                $pdo,
                $scope
            );

        $controllerMeta = $controller->meta();
        $controllerMeta = is_array($controllerMeta)
            ? $controllerMeta
            : [];

        /*
         * The current Classes UI only needs mediums, academic years and shifts.
         * Do not return tenant-wide class records from the legacy controller meta.
         */
        $classMeta = [
            'mediums' => is_array($controllerMeta['mediums'] ?? null)
                ? array_values($controllerMeta['mediums'])
                : ['English'],
        ];

        /*
         * Academic Years belong to the active Branch only.
         * When Branch Settings switches Classes File OFF, do not expose any
         * Academic Year in the Classes page.
         */
        $classMeta['academic_years'] = $branchDataEnabled
            ? classAcademicYears(
                $pdo,
                (int)$scope['tenant_id'],
                (int)$scope['branch_id']
            )
            : [];

        $classMeta['current_academic_year_id'] = $branchDataEnabled
            ? classCurrentAcademicYearId(
                $pdo,
                (int)$scope['tenant_id'],
                (int)$scope['branch_id']
            )
            : 0;

        $classMeta['active_branch_id'] =
            (int)$scope['branch_id'];
        $classMeta['branch_data_enabled'] =
            $branchDataEnabled;

        /*
         * Never use the old static Morning / General / Evening list here.
         * Only enabled Shift Time records from General Settings are returned.
         */
        $classMeta['shifts'] = classConfiguredShifts(
            $pdo,
            $controller->tenantId()
        );

        classJson(
            true,
            'Metadata loaded.',
            [
                'csrf_token' => function_exists('csrfToken')
                    ? csrfToken()
                    : '',
                'meta' => $classMeta,
                'branch_data_enabled' => $branchDataEnabled,
                'access_level' => $controller->access(),
                'permissions' => (static function () use ($controller): array {
                    $controllerPermissions = $controller->permissions();
                    $controllerPermissions = is_array($controllerPermissions)
                        ? $controllerPermissions
                        : [];

                    $sidebarPermissions = function_exists(
                        'school_current_page_capabilities'
                    )
                        ? school_current_page_capabilities('classes')
                        : [];

                    $controllerCanCreate = !empty(
                        $controllerPermissions['create']
                        ?? $controllerPermissions['add']
                        ?? false
                    );

                    $sidebarCanCreate = !empty(
                        $sidebarPermissions['create']
                        ?? $sidebarPermissions['add']
                        ?? false
                    );

                    return [
                        'view' => !empty($controllerPermissions['view'])
                            && !empty($sidebarPermissions['view']),
                        'add' => $controllerCanCreate
                            && $sidebarCanCreate,
                        'create' => $controllerCanCreate
                            && $sidebarCanCreate,
                        'edit' => !empty($controllerPermissions['edit'])
                            && !empty($sidebarPermissions['edit']),
                        'delete' => !empty($controllerPermissions['delete'])
                            && !empty($sidebarPermissions['delete']),
                        'print' => !empty($sidebarPermissions['print']),
                        'pdf' => !empty($sidebarPermissions['pdf']),
                        'export' => !empty($sidebarPermissions['export']),
                        'import' => !empty($sidebarPermissions['import']),
                    ];
                })(),
                'scope' => [
                    'tenant_id' => (int)$scope['tenant_id'],
                    'branch_id' => (int)$scope['branch_id'],
                ],
            ]
        );
    }

    if ($action === 'list') {
        if (!$controller->can('view')) {
            throw new RuntimeException(
                'You do not have permission to view classes.',
                403
            );
        }

        $scope = classStrictScope(
            $pdo,
            $user,
            $controller
        );

        if (
            !classBranchClassesFileEnabled(
                $pdo,
                $scope
            )
        ) {
            classJson(
                true,
                'Classes File is OFF for the active Branch.',
                [
                    'classes' => [],
                    'stats' => [
                        'current_strength' => 0,
                        'current_academic_year_id' => 0,
                    ],
                    'branch_data_enabled' => false,
                    'scope' => [
                        'tenant_id' => (int)$scope['tenant_id'],
                        'branch_id' => (int)$scope['branch_id'],
                    ],
                ]
            );
        }

        $filters = array_merge($_GET, $input);
        $filters['branch_id'] = (int)$scope['branch_id'];

        $selectedAcademicYearId = (
            isset($filters['academic_year_id'])
            && is_numeric($filters['academic_year_id'])
        )
            ? (int)$filters['academic_year_id']
            : 0;

        /*
         * IMPORTANT: Class Management list is READ ONLY here.
         *
         * Do NOT auto-create rows in class_management_classes from classes,
         * sections or student_enrollments.  A simple page refresh/list request
         * must never write dummy/mirror class rows into the database.
         *
         * class_management_classes is changed only by an explicit Add/Edit/Delete
         * action from Class Management.
         */

        $classes = classBranchList(
            $pdo,
            $scope,
            $filters
        );

        $strengthMap = classBuildLiveStrengthMap(
            $pdo,
            $scope,
            $selectedAcademicYearId
        );

        $classes = classApplyLiveStrengths(
            $pdo,
            $scope,
            is_array($classes) ? $classes : [],
            $strengthMap
        );

        /* Exact class+section delete state. */
        $classes = classDecorateDeleteState($pdo, $scope, $classes);

        classJson(
            true,
            'Classes loaded with live Student List strength.',
            [
                'classes' => $classes,
                'branch_data_enabled' => true,
                'stats' => [
                    /*
                     * Match the Classes page behaviour: Current Strength is
                     * the total live strength of the class rows returned by
                     * the current filters. Do not treat students from another
                     * academic year as an error.
                     */
                    'current_strength' => array_sum(
                        array_map(
                            static fn(array $classRow): int =>
                                max(
                                    0,
                                    (int)($classRow['current_strength'] ?? 0)
                                ),
                            $classes
                        )
                    ),
                    'current_academic_year_id' =>
                        $strengthMap['current_year_id'],
                ],
            ]
        );
    }

    if ($action === 'related') {
        if (!$controller->can('view')) {
            throw new RuntimeException(
                'You do not have permission to view Class assignments.',
                403
            );
        }

        $scope = classStrictScope(
            $pdo,
            $user,
            $controller
        );

        if (
            !classBranchClassesFileEnabled(
                $pdo,
                $scope
            )
        ) {
            classJson(
                true,
                'Classes File is OFF for the active Branch.',
                [
                    'records' => [],
                    'branch_data_enabled' => false,
                ]
            );
        }

        $type = trim(
            (string)(
                $input['type']
                ?? $_GET['type']
                ?? ''
            )
        );

        $managementId = (int)(
            $input['class_id']
            ?? $_GET['class_id']
            ?? 0
        );

        classJson(
            true,
            'Records loaded.',
            [
                'records' => classBranchRelated(
                    $pdo,
                    $scope,
                    $type,
                    $managementId
                ),
            ]
        );
    }

    $writeActions = [
        'save',
        'delete',
        'save_related',
        'delete_related',
    ];

    if (!in_array($action, $writeActions, true)) {
        classJson(false, 'Invalid API action.', [], 400);
    }

    classCsrf($input);

    if ($action === 'save') {
        $isEdit = (int)($input['id'] ?? 0) > 0;
        $requiredPermission = $isEdit ? 'edit' : 'create';

        $scope = classStrictScope(
            $pdo,
            $user,
            $controller
        );

        classRequireBranchClassesFileEnabled(
            $pdo,
            $scope
        );

        /*
         * Current Strength is calculated from Student List enrollments.
         * Ignore any manual value posted by the browser.
         */
        $input['current_strength'] = 0;

        $configuredShift = classRequireConfiguredShift(
            $pdo,
            $controller->tenantId(),
            trim((string)($input['shift_name'] ?? ''))
        );
        $input['shift_name'] = (string)$configuredShift['shift_name'];

        // Fast, clear API-level check. The controller validates again using
        // the specific class record, so this does not replace security.
        if (!$controller->can($requiredPermission)) {
            classJson(
                false,
                $isEdit
                    ? 'You do not have permission to edit classes.'
                    : 'You do not have permission to create classes.',
                [],
                403
            );
        }

        /*
         * Browser-supplied scope is ignored. The authenticated session wins.
         */
        $input['tenant_id'] = (int)$scope['tenant_id'];
        $input['branch_id'] = (int)$scope['branch_id'];

        if ($isEdit) {
            classRequireManagementBranch(
                $pdo,
                $scope,
                (int)($input['id'] ?? 0)
            );
        }

        $result = classSaveDatabaseClass(
            $pdo,
            $scope,
            $controller,
            $input
        );

        classJson(
            true,
            $isEdit
                ? 'Class updated successfully.'
                : 'Class created successfully.',
            $result
        );
    }

    if ($action === 'delete') {
        $scope = classStrictScope(
            $pdo,
            $user,
            $controller
        );

        classRequireBranchClassesFileEnabled(
            $pdo,
            $scope
        );

        classRequireManagementBranch(
            $pdo,
            $scope,
            (int)($input['id'] ?? 0)
        );

        $deleteResult = classDeleteDatabaseClass(
            $pdo,
            $scope,
            $controller,
            (int)($input['id'] ?? 0)
        );
        $deleteMessage = !empty($deleteResult['linked_students'])
            ? 'Class deleted from Class Management. Existing student enrollment records were preserved.'
            : (
                !empty($deleteResult['canonical_class_deleted'])
                    ? 'Class deleted permanently from Class Management and the class database.'
                    : 'Class deleted from Class Management successfully.'
            );

        classJson(
            true,
            $deleteMessage,
            $deleteResult
        );
    }

    if ($action === 'save_related') {
        $scope = classStrictScope(
            $pdo,
            $user,
            $controller
        );

        classRequireBranchClassesFileEnabled(
            $pdo,
            $scope
        );

        $type = trim(
            (string)($input['type'] ?? '')
        );

        $managementId = (int)(
            $input['class_id']
            ?? 0
        );

        classRequireManagementBranch(
            $pdo,
            $scope,
            $managementId
        );

        /*
         * Ignore browser supplied School / Branch values.
         */
        $input['tenant_id'] =
            (int)$scope['tenant_id'];

        $input['branch_id'] =
            (int)$scope['branch_id'];

        $id = classBranchSaveRelated(
            $pdo,
            $scope,
            $controller,
            $type,
            $input
        );

        classJson(
            true,
            'Assignment saved successfully.',
            ['id' => $id]
        );
    }

    if ($action === 'delete_related') {
        $scope = classStrictScope(
            $pdo,
            $user,
            $controller
        );

        classRequireBranchClassesFileEnabled(
            $pdo,
            $scope
        );

        $type = trim(
            (string)($input['type'] ?? '')
        );

        $relatedId = (int)(
            $input['id']
            ?? 0
        );

        classBranchDeleteRelated(
            $pdo,
            $scope,
            $controller,
            $type,
            $relatedId
        );

        classJson(
            true,
            'Assignment deleted successfully.'
        );
    }

    classJson(false, 'Invalid API action.', [], 400);
} catch (InvalidArgumentException $exception) {
    classJson(false, $exception->getMessage(), [], 422);
} catch (RuntimeException $exception) {
    $code = (int)$exception->getCode();

    classJson(
        false,
        $exception->getMessage(),
        [],
        $code >= 400 && $code <= 599 ? $code : 403
    );
} catch (Throwable $exception) {
    error_log('classes-api: ' . $exception->getMessage());

    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $message = (
        str_contains($host, 'localhost')
        || str_contains($host, '127.0.0.1')
    )
        ? 'Class request failed: ' . $exception->getMessage()
        : 'Unable to complete class request.';

    classJson(false, $message, [], 500);
}

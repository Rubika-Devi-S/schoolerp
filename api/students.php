<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

if (!defined('SCHOOL_API_PAGE_KEY')) {
    define('SCHOOL_API_PAGE_KEY', 'students');
}

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/general-settings-runtime.php';

/* Build: 2026-08-15-student-strict-school-branch-v15 */

function studentsJson(bool $success, string $message = '', array $data = [], int $status = 200): never
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
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function studentsInput(): array
{
    $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    if (str_contains($contentType, 'application/json')) {
        $decoded = json_decode((string)file_get_contents('php://input'), true);
        return is_array($decoded) ? $decoded : [];
    }
    return $_POST;
}

function studentsUser(): array
{
    $user = function_exists('current_user') ? current_user() : [];
    return is_array($user) ? $user : [];
}

function studentsScope(PDO $pdo): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $user = studentsUser();

    $tenantId = (int)(
        $user['tenant_id']
        ?? $user['school_id']
        ?? $_SESSION['tenant_id']
        ?? $_SESSION['school_id']
        ?? ($_SESSION['tenant']['id'] ?? 0)
    );

    $userId = (int)(
        $user['id']
        ?? $user['user_id']
        ?? $_SESSION['user_id']
        ?? 0
    );

    $branchId = (int)(
        $user['branch_id']
        ?? $user['default_branch_id']
        ?? $_SESSION['branch_id']
        ?? $_SESSION['default_branch_id']
        ?? 0
    );

    $roleId = (int)(
        $user['role_id']
        ?? $_SESSION['role_id']
        ?? 0
    );

    if ($tenantId <= 0 || $userId <= 0) {
        throw new RuntimeException('Active School Admin login required.', 401);
    }

    /* Same branch resolution method used by Academic Year Management. */
    if ($branchId <= 0 && studentsTableExists($pdo, 'users')) {
        try {
            $statement = $pdo->prepare(
                "SELECT default_branch_id
                 FROM users
                 WHERE id=:user_id
                   AND tenant_id=:tenant_id
                   AND deleted_at IS NULL
                 LIMIT 1"
            );
            $statement->execute([
                'user_id' => $userId,
                'tenant_id' => $tenantId,
            ]);
            $branchId = (int)$statement->fetchColumn();
        } catch (Throwable) {
            $statement = $pdo->prepare(
                "SELECT default_branch_id
                 FROM users
                 WHERE id=:user_id
                   AND tenant_id=:tenant_id
                 LIMIT 1"
            );
            $statement->execute([
                'user_id' => $userId,
                'tenant_id' => $tenantId,
            ]);
            $branchId = (int)$statement->fetchColumn();
        }
    }

    if ($branchId <= 0 && studentsTableExists($pdo, 'branches')) {
        $statement = $pdo->prepare(
            "SELECT id
             FROM branches
             WHERE tenant_id=:tenant_id
               AND status='active'
             ORDER BY is_main DESC,id ASC
             LIMIT 1"
        );
        $statement->execute(['tenant_id' => $tenantId]);
        $branchId = (int)$statement->fetchColumn();
    }

    if ($branchId <= 0) {
        throw new RuntimeException(
            'Active School and Branch context is required. Assign an active branch to this School Admin.',
            422
        );
    }

    $branchName = 'Branch #' . $branchId;
    if (studentsTableExists($pdo, 'branches')) {
        $statement = $pdo->prepare(
            "SELECT branch_name
             FROM branches
             WHERE id=:branch_id
               AND tenant_id=:tenant_id
               AND status='active'
             LIMIT 1"
        );
        $statement->execute([
            'branch_id' => $branchId,
            'tenant_id' => $tenantId,
        ]);
        $branchName = (string)($statement->fetchColumn() ?: '');

        if ($branchName === '') {
            throw new RuntimeException(
                'The active Branch does not belong to this School.',
                403
            );
        }
    }

    /* Required by the database branch-isolation triggers. */
    $statement = $pdo->prepare(
        "SET @schoolerp_tenant_id=:tenant_id,
             @schoolerp_branch_id=:branch_id"
    );
    $statement->execute([
        'tenant_id' => $tenantId,
        'branch_id' => $branchId,
    ]);

    return [
        'tenant_id' => $tenantId,
        'branch_id' => $branchId,
        'branch_name' => $branchName,
        'user_id' => $userId,
        'role_id' => $roleId,
    ];
}

function studentsCan(string $action): bool
{
    if (function_exists('is_super_admin') && is_super_admin()) {
        return true;
    }
    if (!function_exists('has_permission')) {
        return true;
    }

    $aliases = [$action];
    if ($action === 'add') {
        $aliases[] = 'create';
    }
    if ($action === 'create') {
        $aliases[] = 'add';
    }

    foreach (['student_management', 'students'] as $module) {
        foreach ($aliases as $permissionAction) {
            if (has_permission($module, $permissionAction)) {
                return true;
            }
        }
    }

    return false;
}

function studentsCsrf(array $input): void
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
        studentsJson(false, 'Invalid or expired CSRF token.', [], 419);
    }
}

function studentsTableExists(PDO $pdo, string $table): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = :table_name'
    );
    $statement->execute(['table_name' => $table]);
    return (int)$statement->fetchColumn() > 0;
}

function studentsColumnExists(PDO $pdo, string $table, string $column): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = :table_name
           AND column_name = :column_name'
    );
    $statement->execute([
        'table_name' => $table,
        'column_name' => $column,
    ]);
    return (int)$statement->fetchColumn() > 0;
}

function studentsForeignKeysForColumn(
    PDO $pdo,
    string $table,
    string $column
): array {
    $statement = $pdo->prepare(
        'SELECT
            CONSTRAINT_NAME AS constraint_name,
            REFERENCED_TABLE_NAME AS referenced_table,
            REFERENCED_COLUMN_NAME AS referenced_column
         FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND COLUMN_NAME = :column_name
           AND REFERENCED_TABLE_NAME IS NOT NULL'
    );

    $statement->execute([
        'table_name' => $table,
        'column_name' => $column,
    ]);

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function studentsSafeSchemaExec(
    PDO $pdo,
    string $sql,
    string $label
): bool {
    try {
        $pdo->exec($sql);
        return true;
    } catch (Throwable $exception) {
        error_log(
            'Students transport schema repair [' .
            $label .
            ']: ' .
            $exception->getMessage()
        );

        return false;
    }
}

function studentsIdentifier(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function studentsColumnAcceptsSchoolRoute(
    PDO $pdo,
    string $table,
    string $column
): bool {
    $foreignKeys = studentsForeignKeysForColumn(
        $pdo,
        $table,
        $column
    );

    if (!$foreignKeys) {
        return true;
    }

    foreach ($foreignKeys as $foreignKey) {
        if (
            strtolower(
                (string)($foreignKey['referenced_table'] ?? '')
            ) !== 'school_routes'
        ) {
            return false;
        }
    }

    return true;
}

function studentsRepairSchoolRouteForeignKey(
    PDO $pdo,
    string $table,
    string $column,
    string $constraintName
): void {
    if (
        !studentsTableExists($pdo, $table)
        || !studentsColumnExists($pdo, $table, $column)
        || !studentsTableExists($pdo, 'school_routes')
    ) {
        return;
    }

    $foreignKeys = studentsForeignKeysForColumn(
        $pdo,
        $table,
        $column
    );

    foreach ($foreignKeys as $foreignKey) {
        $referencedTable = strtolower(
            (string)($foreignKey['referenced_table'] ?? '')
        );

        if ($referencedTable === 'school_routes') {
            continue;
        }

        $oldConstraint = (string)(
            $foreignKey['constraint_name'] ?? ''
        );

        if ($oldConstraint === '') {
            continue;
        }

        studentsSafeSchemaExec(
            $pdo,
            'ALTER TABLE ' .
            studentsIdentifier($table) .
            ' DROP FOREIGN KEY ' .
            studentsIdentifier($oldConstraint),
            'drop ' . $table . '.' . $oldConstraint
        );
    }

    /*
     * Route Stops identify the correct School Route, so use them to repair
     * transport fee rows created by the stop-based workflow.
     */
    if (
        $table === 'student_fee_items'
        && $column === 'transport_route_id'
        && studentsTableExists($pdo, 'school_route_stops')
        && studentsColumnExists(
            $pdo,
            'student_fee_items',
            'transport_stop_id'
        )
    ) {
        studentsSafeSchemaExec(
            $pdo,
            "UPDATE student_fee_items sfi
             INNER JOIN school_route_stops srs
                ON srs.id = sfi.transport_stop_id
               AND srs.tenant_id = sfi.tenant_id
             SET sfi.transport_route_id = srs.route_id
             WHERE sfi.transport_stop_id IS NOT NULL",
            'map fee item route from boarding stop'
        );
    }

    /*
     * Remove values which cannot be referenced by School Route Master.
     * This is required before adding the corrected foreign key.
     */
    studentsSafeSchemaExec(
        $pdo,
        'UPDATE ' .
        studentsIdentifier($table) .
        ' target_row
         LEFT JOIN school_routes school_route
           ON school_route.id = target_row.' .
           studentsIdentifier($column) .
          ' AND school_route.tenant_id = target_row.tenant_id
         SET target_row.' .
           studentsIdentifier($column) .
          ' = NULL
         WHERE target_row.' .
           studentsIdentifier($column) .
          ' IS NOT NULL
           AND school_route.id IS NULL',
        'clear incompatible ' . $table . '.' . $column
    );

    if (
        studentsColumnAcceptsSchoolRoute(
            $pdo,
            $table,
            $column
        )
        && studentsForeignKeysForColumn(
            $pdo,
            $table,
            $column
        )
    ) {
        return;
    }

    studentsSafeSchemaExec(
        $pdo,
        'ALTER TABLE ' .
        studentsIdentifier($table) .
        ' ADD CONSTRAINT ' .
        studentsIdentifier($constraintName) .
        ' FOREIGN KEY (' .
        studentsIdentifier($column) .
        ') REFERENCES school_routes(id)
           ON DELETE SET NULL
           ON UPDATE CASCADE',
        'add ' . $constraintName
    );
}

function studentsRepairTransportRouteForeignKeys(PDO $pdo): void
{
    studentsRepairSchoolRouteForeignKey(
        $pdo,
        'student_fee_items',
        'transport_route_id',
        'fk_sfi_school_route'
    );

    studentsRepairSchoolRouteForeignKey(
        $pdo,
        'student_fee_assignments',
        'transport_route_id',
        'fk_sfa_school_route'
    );

    studentsRepairSchoolRouteForeignKey(
        $pdo,
        'student_transport_assignments',
        'route_id',
        'fk_sta_school_route'
    );
}

function studentsRequireTables(PDO $pdo): void
{
    foreach (
        [
            'students',
            'student_enrollments',
            'academic_years',
            'classes',
            'sections',
            'guardians',
            'student_guardians',
        ] as $table
    ) {
        if (!studentsTableExists($pdo, $table)) {
            throw new RuntimeException('Missing required database table: ' . $table . '.', 500);
        }
    }
}

function studentsEnsureSupportSchema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS student_activity_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id BIGINT UNSIGNED NOT NULL,
            branch_id BIGINT UNSIGNED NULL,
            student_id BIGINT UNSIGNED NULL,
            user_id BIGINT UNSIGNED NULL,
            action_name VARCHAR(80) NOT NULL,
            description VARCHAR(500) NULL,
            old_values JSON NULL,
            new_values JSON NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_student_logs (tenant_id, branch_id, student_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS student_profile_extras (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id BIGINT UNSIGNED NOT NULL,
            student_id BIGINT UNSIGNED NOT NULL,
            notes TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_student_profile_extra (tenant_id, student_id),
            KEY idx_student_profile_extra_student (student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    /*
     * Parent Portal login link.
     * One guardian receives one user account, even when the same guardian
     * is linked to multiple students.
     */
    if (
        studentsTableExists($pdo, 'users')
        && studentsTableExists($pdo, 'guardians')
    ) {
        if (!studentsColumnExists($pdo, 'guardians', 'user_id')) {
            $pdo->exec(
                "ALTER TABLE guardians
                 ADD COLUMN user_id BIGINT UNSIGNED NULL AFTER tenant_id"
            );
        }

        $indexStatement = $pdo->prepare(
            "SELECT COUNT(*)
             FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name = 'guardians'
               AND index_name = 'idx_guardian_user'"
        );
        $indexStatement->execute();

        if ((int)$indexStatement->fetchColumn() === 0) {
            $pdo->exec(
                "ALTER TABLE guardians
                 ADD KEY idx_guardian_user (tenant_id, user_id)"
            );
        }

        /*
         * Do not retain broken links when a user account has been removed.
         */
        $pdo->exec(
            "UPDATE guardians g
             LEFT JOIN users u
               ON u.id = g.user_id
              AND u.tenant_id = g.tenant_id
              AND u.deleted_at IS NULL
             SET g.user_id = NULL
             WHERE g.user_id IS NOT NULL
               AND u.id IS NULL"
        );

        /*
         * Student-specific Parent Portal accounts.
         *
         * This table is the durable link used by the Students module.
         * One student can have one Parent Login account. The same guardian
         * may therefore have separate Parent Login accounts for different
         * students when the school explicitly generates them.
         *
         * Passwords are NEVER stored here. Only users.password_hash stores
         * the secure hash. Temporary passwords are returned only once at
         * generation time for printing/sharing.
         */
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS student_parent_logins (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id BIGINT UNSIGNED NOT NULL,
                student_id BIGINT UNSIGNED NOT NULL,
                guardian_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                status ENUM('active','inactive') NOT NULL DEFAULT 'active',
                created_by BIGINT UNSIGNED NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_student_parent_login_student (tenant_id, student_id),
                UNIQUE KEY uq_student_parent_login_user (tenant_id, user_id),
                KEY idx_student_parent_login_guardian (tenant_id, guardian_id),
                KEY idx_student_parent_login_student (student_id),
                KEY idx_student_parent_login_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        /*
         * Remove mappings only when their linked user was deleted.
         * This does not reset or overwrite any valid existing account.
         */
        $pdo->exec(
            "DELETE spl
             FROM student_parent_logins spl
             LEFT JOIN users u
               ON u.id = spl.user_id
              AND u.tenant_id = spl.tenant_id
              AND u.deleted_at IS NULL
             WHERE u.id IS NULL"
        );
    }
}

function studentsCurrentAcademicYearId(PDO $pdo, int $tenantId, int $branchId): int
{
    $statement = $pdo->prepare(
        "SELECT id
         FROM academic_years
         WHERE tenant_id = :tenant_id
           AND branch_id = :branch_id
         ORDER BY is_current DESC, status = 'active' DESC, start_date DESC, id DESC
         LIMIT 1"
    );
    $statement->execute([
        'tenant_id' => $tenantId,
        'branch_id' => $branchId,
    ]);
    return (int)$statement->fetchColumn();
}

function studentsNormalizeGender(string $gender): string
{
    $gender = strtolower(trim($gender));
    return in_array($gender, ['male', 'female', 'other'], true) ? $gender : '';
}

function studentsNormalizeStatus(string $status): string
{
    $status = strtolower(trim($status));
    return match ($status) {
        'active' => 'active',
        'inactive', 'withdrawn' => 'inactive',
        'tc', 'tc_issued', 'transferred' => 'tc',
        'alumni' => 'alumni',
        default => 'active',
    };
}

function studentsStatusLabel(string $status): string
{
    return match ($status) {
        'tc' => 'tc',
        'alumni' => 'alumni',
        'inactive' => 'inactive',
        default => 'active',
    };
}

function studentsValidateDate(string $value, string $fieldLabel): string
{
    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    $hasErrors = is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0);

    if (!$date || $hasErrors || $date->format('Y-m-d') !== $value) {
        throw new InvalidArgumentException($fieldLabel . ' is invalid.');
    }

    return $value;
}

function studentsSplitName(string $fullName): array
{
    $fullName = preg_replace('/\s+/u', ' ', trim($fullName)) ?? '';
    if ($fullName === '') {
        return ['', null];
    }

    if (mb_strlen($fullName) <= 100) {
        return [$fullName, null];
    }

    $first = mb_substr($fullName, 0, 100);
    $last = trim(mb_substr($fullName, 100, 100));
    return [$first, $last !== '' ? $last : null];
}

function studentsLog(
    PDO $pdo,
    array $scope,
    string $action,
    ?int $studentId,
    string $description,
    ?array $old = null,
    ?array $new = null
): void {
    try {
        $statement = $pdo->prepare(
            'INSERT INTO student_activity_logs
                (tenant_id, branch_id, student_id, user_id, action_name, description, old_values, new_values)
             VALUES
                (:tenant_id, :branch_id, :student_id, :user_id, :action_name, :description, :old_values, :new_values)'
        );
        $statement->execute([
            'tenant_id' => $scope['tenant_id'],
            'branch_id' => $scope['branch_id'] > 0 ? $scope['branch_id'] : null,
            'student_id' => $studentId,
            'user_id' => $scope['user_id'] > 0 ? $scope['user_id'] : null,
            'action_name' => $action,
            'description' => $description,
            'old_values' => $old !== null ? json_encode($old, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'new_values' => $new !== null ? json_encode($new, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ]);
    } catch (Throwable $exception) {
        error_log('student activity log failed: ' . $exception->getMessage());
    }
}

function studentsFindAcademicYear(PDO $pdo, int $tenantId, int $branchId, int $academicYearId): array
{
    $statement = $pdo->prepare(
        'SELECT id, branch_id, year_name, start_date, end_date, is_current, status
         FROM academic_years
         WHERE id = :id
           AND tenant_id = :tenant_id
           AND branch_id = :branch_id
         LIMIT 1'
    );
    $statement->execute([
        'id' => $academicYearId,
        'tenant_id' => $tenantId,
        'branch_id' => $branchId,
    ]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new InvalidArgumentException(
            'Please select a valid academic year configured for the current branch.'
        );
    }

    return $row;
}

function studentsFindBranch(PDO $pdo, int $tenantId, int $branchId): array
{
    if (!studentsTableExists($pdo, 'branches')) {
        return ['id' => $branchId, 'branch_name' => ''];
    }

    $statement = $pdo->prepare(
        'SELECT id, branch_name, status
         FROM branches
         WHERE id = :id AND tenant_id = :tenant_id
         LIMIT 1'
    );
    $statement->execute([
        'id' => $branchId,
        'tenant_id' => $tenantId,
    ]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new InvalidArgumentException('Please select a valid branch.');
    }

    return $row;
}

function studentsResolveClass(
    PDO $pdo,
    int $tenantId,
    int $branchId,
    int $academicYearId,
    int $inputClassId
): array {
    if ($inputClassId <= 0) {
        throw new InvalidArgumentException('Please select a class.');
    }

    $statement = $pdo->prepare(
        "SELECT id, branch_id, academic_year_id, class_name, display_order, status
         FROM classes
         WHERE id = :id
           AND tenant_id = :tenant_id
           AND branch_id = :branch_id
           AND academic_year_id = :academic_year_id
         LIMIT 1"
    );
    $statement->execute([
        'id' => $inputClassId,
        'tenant_id' => $tenantId,
        'branch_id' => $branchId,
        'academic_year_id' => $academicYearId,
    ]);
    $source = $statement->fetch(PDO::FETCH_ASSOC);

    /* Compatibility for old Class Management row IDs. */
    if (!$source && studentsTableExists($pdo, 'class_management_classes')) {
        $managedStatement = $pdo->prepare(
            "SELECT academic_year_id, class_name
             FROM class_management_classes
             WHERE id = :id
               AND tenant_id = :tenant_id
               AND branch_id = :branch_id
               AND academic_year_id = :academic_year_id
               AND status <> 'archived'
             LIMIT 1"
        );
        $managedStatement->execute([
            'id' => $inputClassId,
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'academic_year_id' => $academicYearId,
        ]);
        $managed = $managedStatement->fetch(PDO::FETCH_ASSOC);

        if ($managed) {
            $statement = $pdo->prepare(
                "SELECT id, branch_id, academic_year_id, class_name, display_order, status
                 FROM classes
                 WHERE tenant_id = :tenant_id
                   AND branch_id = :branch_id
                   AND academic_year_id = :academic_year_id
                   AND LOWER(TRIM(class_name)) = LOWER(TRIM(:class_name))
                 LIMIT 1"
            );
            $statement->execute([
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'academic_year_id' => $academicYearId,
                'class_name' => (string)$managed['class_name'],
            ]);
            $source = $statement->fetch(PDO::FETCH_ASSOC);
        }
    }

    if (!$source) {
        throw new InvalidArgumentException(
            'Please select a valid class created in Class Management for the current branch and academic year.'
        );
    }

    return [
        'source_class_id' => (int)$source['id'],
        'canonical_class_id' => (int)$source['id'],
        'class_name' => trim((string)$source['class_name']),
        'display_order' => (int)($source['display_order'] ?? 0),
        'status' => (string)($source['status'] ?? 'active'),
    ];
}

function studentsResolveSection(
    PDO $pdo,
    int $tenantId,
    int $branchId,
    int $academicYearId,
    array $class,
    int $inputSectionId
): array {
    $canonicalClassId = (int)($class['canonical_class_id'] ?? 0);

    if ($canonicalClassId <= 0) {
        throw new InvalidArgumentException(
            'Please select a valid class before selecting a section.'
        );
    }

    $source = null;

    if ($inputSectionId > 0) {
        $statement = $pdo->prepare(
            "SELECT
                s.id,
                s.section_name,
                s.capacity,
                s.status,
                c.academic_year_id,
                c.class_name
             FROM sections s
             INNER JOIN classes c
                ON c.id = s.class_id
               AND c.tenant_id = s.tenant_id
               AND c.branch_id = s.branch_id
             WHERE s.id = :id
               AND s.tenant_id = :tenant_id
               AND s.branch_id = :branch_id
               AND s.class_id = :class_id
               AND c.branch_id = :branch_id_class
               AND c.academic_year_id = :academic_year_id
             LIMIT 1"
        );
        $statement->execute([
            'id' => $inputSectionId,
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'branch_id_class' => $branchId,
            'class_id' => $canonicalClassId,
            'academic_year_id' => $academicYearId,
        ]);
        $source = $statement->fetch(PDO::FETCH_ASSOC);
    }

    if (
        !$source
        && $inputSectionId > 0
        && studentsTableExists($pdo, 'school_sections')
    ) {
        $legacyStatement = $pdo->prepare(
            "SELECT section_name
             FROM school_sections
             WHERE id = :id
               AND tenant_id = :tenant_id
               AND branch_id = :branch_id
               AND academic_year_id = :academic_year_id
               AND LOWER(TRIM(class_name_snapshot)) = LOWER(TRIM(:class_name))
               AND status <> 'archived'
             LIMIT 1"
        );
        $legacyStatement->execute([
            'id' => $inputSectionId,
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'academic_year_id' => $academicYearId,
            'class_name' => (string)$class['class_name'],
        ]);
        $legacy = $legacyStatement->fetch(PDO::FETCH_ASSOC);

        if ($legacy) {
            $statement = $pdo->prepare(
                "SELECT id, section_name, capacity, status
                 FROM sections
                 WHERE tenant_id = :tenant_id
                   AND branch_id = :branch_id
                   AND class_id = :class_id
                   AND LOWER(TRIM(section_name)) = LOWER(TRIM(:section_name))
                 LIMIT 1"
            );
            $statement->execute([
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'class_id' => $canonicalClassId,
                'section_name' => (string)$legacy['section_name'],
            ]);
            $source = $statement->fetch(PDO::FETCH_ASSOC);
        }
    }

    if (!$source && $inputSectionId <= 0) {
        $statement = $pdo->prepare(
            "SELECT id, section_name, capacity, status
             FROM sections
             WHERE tenant_id = :tenant_id
               AND branch_id = :branch_id
               AND class_id = :class_id
             ORDER BY status = 'active' DESC, id
             LIMIT 2"
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'class_id' => $canonicalClassId,
        ]);
        $available = $statement->fetchAll(PDO::FETCH_ASSOC);

        if (count($available) === 1) {
            $source = $available[0];
        }
    }

    if (!$source) {
        throw new InvalidArgumentException(
            'Please select a valid section configured for the current branch, class and academic year.'
        );
    }

    return [
        'source_section_id' => (int)$source['id'],
        'canonical_section_id' => (int)$source['id'],
        'section_name' => trim((string)$source['section_name']),
        'capacity' => $source['capacity'] !== null ? (int)$source['capacity'] : null,
        'status' => (string)($source['status'] ?? 'active'),
    ];
}

function studentsPrimaryGuardian(PDO $pdo, int $tenantId, int $studentId): ?array
{
    $statement = $pdo->prepare(
        'SELECT g.*
         FROM student_guardians sg
         INNER JOIN guardians g ON g.id = sg.guardian_id
         WHERE sg.student_id = :student_id
           AND g.tenant_id = :tenant_id
         ORDER BY sg.is_primary DESC, g.id ASC
         LIMIT 1'
    );
    $statement->execute([
        'student_id' => $studentId,
        'tenant_id' => $tenantId,
    ]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function studentsUpsertGuardian(PDO $pdo, int $tenantId, int $studentId, array $data): int
{
    $existing = studentsPrimaryGuardian($pdo, $tenantId, $studentId);

    if ($existing) {
        $guardianId = (int)$existing['id'];
        $statement = $pdo->prepare(
            'UPDATE guardians SET
                guardian_name = :guardian_name,
                relationship = :relationship,
                mobile = :mobile,
                email = :email,
                address = :address
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $statement->execute([
            'guardian_name' => $data['parent_name'],
            'relationship' => $data['relationship'] !== '' ? $data['relationship'] : null,
            'mobile' => $data['mobile'] !== '' ? $data['mobile'] : null,
            'email' => $data['email'] !== '' ? $data['email'] : null,
            'address' => $data['address'] !== '' ? $data['address'] : null,
            'id' => $guardianId,
            'tenant_id' => $tenantId,
        ]);
    } else {
        $statement = $pdo->prepare(
            'INSERT INTO guardians
                (tenant_id, guardian_name, relationship, mobile, email, address)
             VALUES
                (:tenant_id, :guardian_name, :relationship, :mobile, :email, :address)'
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'guardian_name' => $data['parent_name'],
            'relationship' => $data['relationship'] !== '' ? $data['relationship'] : null,
            'mobile' => $data['mobile'] !== '' ? $data['mobile'] : null,
            'email' => $data['email'] !== '' ? $data['email'] : null,
            'address' => $data['address'] !== '' ? $data['address'] : null,
        ]);
        $guardianId = (int)$pdo->lastInsertId();

        $statement = $pdo->prepare(
            'INSERT INTO student_guardians (student_id, guardian_id, is_primary)
             VALUES (:student_id, :guardian_id, 1)'
        );
        $statement->execute([
            'student_id' => $studentId,
            'guardian_id' => $guardianId,
        ]);
    }

    $pdo->prepare('UPDATE student_guardians SET is_primary = 0 WHERE student_id = :student_id AND guardian_id <> :guardian_id')
        ->execute([
            'student_id' => $studentId,
            'guardian_id' => $guardianId,
        ]);
    $pdo->prepare('UPDATE student_guardians SET is_primary = 1 WHERE student_id = :student_id AND guardian_id = :guardian_id')
        ->execute([
            'student_id' => $studentId,
            'guardian_id' => $guardianId,
        ]);

    return $guardianId;
}

function studentsUpsertNotes(PDO $pdo, int $tenantId, int $studentId, string $notes): void
{
    $statement = $pdo->prepare(
        'INSERT INTO student_profile_extras (tenant_id, student_id, notes)
         VALUES (:tenant_id, :student_id, :notes)
         ON DUPLICATE KEY UPDATE notes = VALUES(notes), updated_at = CURRENT_TIMESTAMP'
    );
    $statement->execute([
        'tenant_id' => $tenantId,
        'student_id' => $studentId,
        'notes' => $notes !== '' ? $notes : null,
    ]);
}



function studentsEnsureFeeSchema(PDO $pdo): void
{
    foreach (
        [
            'fee_structures',
            'fee_structure_items',
            'fee_heads',
            'student_fee_assignments',
        ] as $table
    ) {
        if (!studentsTableExists($pdo, $table)) {
            throw new RuntimeException(
                'Missing required Fee Management table: ' . $table . '.'
            );
        }
    }

    /*
     * Retain the legacy table for backward compatibility.
     * New Student Admission transport selections use:
     * school_routes, school_route_stops and school_vehicles.
     */
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS transport_routes (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id BIGINT UNSIGNED NOT NULL,
            branch_id BIGINT UNSIGNED NULL,
            route_code VARCHAR(40) NOT NULL,
            route_name VARCHAR(150) NOT NULL,
            bus_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY(id),
            UNIQUE KEY uk_transport_route_code(tenant_id,route_code),
            KEY idx_transport_route_lookup(tenant_id,branch_id,status)
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS student_transport_assignments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id BIGINT UNSIGNED NOT NULL,
            student_id BIGINT UNSIGNED NOT NULL,
            academic_year_id BIGINT UNSIGNED NOT NULL,
            route_id BIGINT UNSIGNED NULL,
            stop_id BIGINT UNSIGNED NULL,
            vehicle_id BIGINT UNSIGNED NULL,
            route_name VARCHAR(150) NULL,
            boarding_stop_name VARCHAR(150) NULL,
            vehicle_name VARCHAR(150) NULL,
            driver_name VARCHAR(120) NULL,
            transport_required TINYINT(1) NOT NULL DEFAULT 0,
            transport_fee_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            bus_fee_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            assigned_on DATE NOT NULL,
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY(id),
            UNIQUE KEY uk_student_transport_year(
                student_id,
                academic_year_id
            ),
            KEY idx_student_transport_route(
                tenant_id,
                route_id,
                status
            ),
            KEY idx_student_transport_stop(
                tenant_id,
                stop_id,
                status
            )
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci"
    );

    $transportColumns = [
        'stop_id' =>
            "BIGINT UNSIGNED NULL AFTER route_id",
        'vehicle_id' =>
            "BIGINT UNSIGNED NULL AFTER stop_id",
        'route_name' =>
            "VARCHAR(150) NULL AFTER vehicle_id",
        'boarding_stop_name' =>
            "VARCHAR(150) NULL AFTER route_name",
        'vehicle_name' =>
            "VARCHAR(150) NULL AFTER boarding_stop_name",
        'driver_name' =>
            "VARCHAR(120) NULL AFTER vehicle_name",
        'transport_fee_amount' =>
            "DECIMAL(12,2) NOT NULL DEFAULT 0
             AFTER transport_required",
        'bus_fee_amount' =>
            "DECIMAL(12,2) NOT NULL DEFAULT 0
             AFTER transport_fee_amount",
    ];

    foreach ($transportColumns as $column => $definition) {
        if (
            !studentsColumnExists(
                $pdo,
                'student_transport_assignments',
                $column
            )
        ) {
            $pdo->exec(
                "ALTER TABLE student_transport_assignments
                 ADD COLUMN {$column} {$definition}"
            );
        }
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS student_fee_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id BIGINT UNSIGNED NOT NULL,
            assignment_id BIGINT UNSIGNED NOT NULL,
            student_id BIGINT UNSIGNED NOT NULL,
            academic_year_id BIGINT UNSIGNED NOT NULL,
            fee_structure_item_id BIGINT UNSIGNED NULL,
            transport_route_id BIGINT UNSIGNED NULL,
            transport_stop_id BIGINT UNSIGNED NULL,
            source_receipt_id BIGINT UNSIGNED NULL,
            item_type ENUM(
                'admission',
                'tuition',
                'term',
                'transport',
                'additional',
                'previous_due'
            ) NOT NULL,
            item_name VARCHAR(180) NOT NULL,
            period_key VARCHAR(80) NOT NULL,
            period_label VARCHAR(120) NOT NULL,
            due_date DATE NOT NULL,
            original_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            balance_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            item_status ENUM(
                'unpaid',
                'partial',
                'paid',
                'waived',
                'cancelled'
            ) NOT NULL DEFAULT 'unpaid',
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY(id),
            UNIQUE KEY uk_student_fee_period(
                assignment_id,
                item_type,
                period_key
            ),
            KEY idx_student_fee_due(
                tenant_id,
                student_id,
                due_date,
                item_status
            ),
            KEY idx_student_fee_assignment(
                assignment_id,
                item_status
            ),
            KEY idx_student_fee_transport_stop(
                tenant_id,
                transport_stop_id,
                item_status
            )
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci"
    );

    if (
        !studentsColumnExists(
            $pdo,
            'student_fee_items',
            'transport_stop_id'
        )
    ) {
        $pdo->exec(
            "ALTER TABLE student_fee_items
             ADD COLUMN transport_stop_id BIGINT UNSIGNED NULL
             AFTER transport_route_id"
        );
    }

    $columns = [
        'assignment_date' =>
            "DATE NULL AFTER fee_structure_id",
        'assignment_status' =>
            "ENUM('active','inactive') NOT NULL DEFAULT 'active'
             AFTER assignment_date",
        'is_new_admission' =>
            "TINYINT(1) NOT NULL DEFAULT 0
             AFTER assignment_status",
        'base_fee_amount' =>
            "DECIMAL(12,2) NOT NULL DEFAULT 0
             AFTER is_new_admission",
        'transport_fee_amount' =>
            "DECIMAL(12,2) NOT NULL DEFAULT 0
             AFTER base_fee_amount",
        'transport_route_id' =>
            "BIGINT UNSIGNED NULL AFTER transport_fee_amount",
        'transport_stop_id' =>
            "BIGINT UNSIGNED NULL AFTER transport_route_id",
        'transport_vehicle_id' =>
            "BIGINT UNSIGNED NULL AFTER transport_stop_id",
        'transport_driver_name' =>
            "VARCHAR(120) NULL AFTER transport_vehicle_id",
        'schedule_generated_at' =>
            "DATETIME NULL AFTER transport_driver_name",
    ];

    foreach ($columns as $column => $definition) {
        if (
            !studentsColumnExists(
                $pdo,
                'student_fee_assignments',
                $column
            )
        ) {
            $pdo->exec(
                "ALTER TABLE student_fee_assignments
                 ADD COLUMN {$column} {$definition}"
            );
        }
    }

    /*
     * Route Stops V5 stores the selected-stop fee here.
     */
    if (
        studentsTableExists($pdo, 'school_route_stops')
        && !studentsColumnExists(
            $pdo,
            'school_route_stops',
            'transport_fee'
        )
    ) {
        $pdo->exec(
            "ALTER TABLE school_route_stops
             ADD COLUMN transport_fee
             DECIMAL(12,2) NOT NULL DEFAULT 0.00
             AFTER stop_order"
        );
    }
}

function studentsFeeSetting(
    PDO $pdo,
    int $tenantId,
    string $key,
    string $default
): string {
    if (!studentsTableExists($pdo, 'fee_management_settings')) {
        return $default;
    }

    $statement = $pdo->prepare(
        "SELECT setting_value
         FROM fee_management_settings
         WHERE tenant_id = :tenant_id
           AND setting_key = :setting_key
         LIMIT 1"
    );
    $statement->execute([
        'tenant_id' => $tenantId,
        'setting_key' => $key,
    ]);
    $value = $statement->fetchColumn();

    return $value === false ? $default : (string)$value;
}

function studentsMonthPeriods(
    string $startDate,
    string $endDate,
    int $dueDay
): array {
    $start = new DateTimeImmutable($startDate);
    $end = new DateTimeImmutable($endDate);
    $cursor = $start->modify('first day of this month');
    $last = $end->modify('first day of this month');
    $periods = [];

    while ($cursor <= $last) {
        $lastDay = (int)$cursor->format('t');
        $day = min(max(1, $dueDay), $lastDay);
        $due = $cursor->setDate(
            (int)$cursor->format('Y'),
            (int)$cursor->format('m'),
            $day
        );

        if ($due < $start) {
            $due = $start;
        }
        if ($due > $end) {
            $due = $end;
        }

        $periods[] = [
            'key' => $cursor->format('Y-m'),
            'label' => $cursor->format('F Y'),
            'due_date' => $due->format('Y-m-d'),
        ];
        $cursor = $cursor->modify('+1 month');
    }

    return $periods;
}

function studentsTermPeriods(
    string $startDate,
    string $endDate,
    int $termCount
): array {
    $start = new DateTimeImmutable($startDate);
    $end = new DateTimeImmutable($endDate);
    $days = max(1, (int)$start->diff($end)->format('%a'));
    $termCount = max(1, $termCount);
    $periods = [];

    for ($term = 1; $term <= $termCount; $term++) {
        $offset = (int)floor(($term - 1) * $days / $termCount);
        $due = $start->modify('+' . $offset . ' days');
        $periods[] = [
            'key' => 'TERM-' . $term,
            'label' => 'Term ' . $term,
            'due_date' => $due->format('Y-m-d'),
        ];
    }

    return $periods;
}


function studentsFrequencyPeriods(
    string $frequency,
    string $startDate,
    string $endDate,
    int $occurrenceCount,
    int $monthlyDueDay,
    string $feeCode,
    string $feeName
): array {
    $frequency = strtolower(trim($frequency));
    $occurrenceCount = max(1, min(36, $occurrenceCount));
    $prefix = strtoupper(preg_replace('/[^A-Z0-9]+/i', '-', $feeCode) ?? 'FEE');
    $prefix = trim($prefix, '-') ?: 'FEE';

    if ($frequency === 'monthly') {
        $rows = studentsMonthPeriods($startDate, $endDate, $monthlyDueDay);
        $rows = array_slice($rows, 0, $occurrenceCount);
        foreach ($rows as &$row) {
            $row['key'] = $prefix . '-M-' . $row['key'];
        }
        unset($row);
        return $rows;
    }

    $count = match ($frequency) {
        'quarterly' => 4,
        'half_yearly' => 2,
        'yearly', 'annual', 'one_time' => 1,
        'term' => $occurrenceCount,
        'custom' => $occurrenceCount,
        default => 1,
    };

    $rows = studentsTermPeriods($startDate, $endDate, $count);
    $labels = [
        'quarterly' => 'Quarter',
        'half_yearly' => 'Half-Year',
        'yearly' => 'Yearly',
        'annual' => 'Annual',
        'one_time' => $feeName,
        'term' => 'Term',
        'custom' => 'Installment',
    ];
    $labelPrefix = $labels[$frequency] ?? 'Installment';

    foreach ($rows as $index => &$row) {
        $number = $index + 1;
        $row['key'] = $prefix . '-' . strtoupper(str_replace('_', '-', $frequency)) . '-' . $number;
        $row['label'] = $count === 1 && in_array($frequency, ['one_time', 'yearly', 'annual'], true)
            ? ($frequency === 'one_time' ? $feeName : $labelPrefix)
            : $labelPrefix . ' ' . $number;
    }
    unset($row);

    return $rows;
}

function studentsFeeStructures(
    PDO $pdo,
    array $scope
): array {
    $statement = $pdo->prepare(
        "SELECT
            fs.id,
            fs.academic_year_id,
            fs.class_id,
            c.class_name,
            fs.structure_name,
            fs.status,
            ay.start_date,
            ay.end_date
         FROM fee_structures fs
         INNER JOIN classes c
            ON c.id = fs.class_id
           AND c.tenant_id = fs.tenant_id
           AND c.branch_id = fs.branch_id
         INNER JOIN academic_years ay
            ON ay.id = fs.academic_year_id
           AND ay.tenant_id = fs.tenant_id
           AND ay.branch_id = fs.branch_id
         WHERE fs.tenant_id = :tenant_id
           AND fs.branch_id = :branch_id
           AND fs.status = 'active'
         ORDER BY
            fs.academic_year_id DESC,
            c.display_order,
            c.class_name,
            fs.id DESC"
    );
    $statement->execute([
        'tenant_id' => $scope['tenant_id'],
        'branch_id' => $scope['branch_id'],
    ]);
    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

    $hasFeeTypes = studentsTableExists($pdo, 'fee_types')
        && studentsColumnExists($pdo, 'fee_structure_items', 'fee_type_id');

    if ($hasFeeTypes) {
        $itemStatement = $pdo->prepare(
            "SELECT
                fsi.id,
                fsi.fee_type_id,
                ft.fee_type_name,
                ft.fee_type_code,
                ft.description,
                COALESCE(NULLIF(fsi.frequency, ''), ft.default_frequency, 'one_time') AS frequency,
                GREATEST(
                    1,
                    COALESCE(
                        NULLIF(fsi.occurrence_count, 0),
                        NULLIF(ft.default_occurrence_count, 0),
                        1
                    )
                ) AS occurrence_count,
                fsi.amount,
                COALESCE(fsi.status, 'active') AS item_status,
                ft.is_mandatory,
                ft.is_enabled,
                COALESCE(fsi.display_order, ft.display_order, 0) AS display_order
             FROM fee_structure_items fsi
             INNER JOIN fee_types ft
                ON ft.id = fsi.fee_type_id
               AND ft.tenant_id = :tenant_id
               AND ft.deleted_at IS NULL
             WHERE fsi.fee_structure_id = :structure_id
             ORDER BY
                COALESCE(fsi.display_order, ft.display_order, 0),
                ft.fee_type_name,
                fsi.id"
        );
    } else {
        $itemStatement = $pdo->prepare(
            "SELECT
                fsi.id,
                NULL AS fee_type_id,
                COALESCE(fh.head_name, CONCAT('Fee Item ', fsi.id)) AS fee_type_name,
                UPPER(COALESCE(fsi.fee_type_key, 'FEE')) AS fee_type_code,
                NULL AS description,
                COALESCE(NULLIF(fsi.frequency, ''),
                    CASE fsi.fee_type_key
                        WHEN 'tuition' THEN 'monthly'
                        WHEN 'term' THEN 'term'
                        ELSE 'one_time'
                    END
                ) AS frequency,
                GREATEST(
                    1,
                    COALESCE(
                        NULLIF(fsi.occurrence_count, 0),
                        CASE fsi.fee_type_key
                            WHEN 'tuition' THEN 12
                            WHEN 'term' THEN 3
                            ELSE 1
                        END
                    )
                ) AS occurrence_count,
                fsi.amount,
                COALESCE(fsi.status, 'active') AS item_status,
                0 AS is_mandatory,
                1 AS is_enabled,
                COALESCE(fsi.display_order, 0) AS display_order
             FROM fee_structure_items fsi
             LEFT JOIN fee_heads fh
                ON fh.id = fsi.fee_head_id
             WHERE fsi.fee_structure_id = :structure_id
             ORDER BY COALESCE(fsi.display_order, 0), fsi.id"
        );
    }

    foreach ($rows as &$row) {
        $parameters = ['structure_id' => $row['id']];
        if ($hasFeeTypes) {
            $parameters['tenant_id'] = $scope['tenant_id'];
        }

        $itemStatement->execute($parameters);
        $items = $itemStatement->fetchAll(PDO::FETCH_ASSOC);
        $annual = 0.0;

        foreach ($items as &$item) {
            $amount = round((float)($item['amount'] ?? 0), 2);
            $occurrences = max(1, (int)($item['occurrence_count'] ?? 1));
            $isActive = strtolower((string)($item['item_status'] ?? 'active')) === 'active';

            $item['amount'] = $amount;
            $item['occurrence_count'] = $occurrences;
            $item['annual_amount'] = $isActive
                ? round($amount * $occurrences, 2)
                : 0.0;
            $item['frequency_label'] = match (
                strtolower((string)($item['frequency'] ?? 'one_time'))
            ) {
                'monthly' => 'Monthly',
                'term' => 'Term-wise',
                'quarterly' => 'Quarterly',
                'half_yearly' => 'Half-Yearly',
                'yearly' => 'Yearly',
                'annual' => 'Annual',
                'custom' => 'Custom',
                default => 'One Time',
            };

            if ($isActive) {
                $annual += (float)$item['annual_amount'];
            }
        }
        unset($item);

        $row['items'] = $items;
        $row['fee_items'] = $items;
        $row['item_count'] = count($items);
        $row['annual_total'] = round($annual, 2);
        $row['total_amount'] = $row['annual_total'];
    }
    unset($row);

    return $rows;
}

function studentsTransportRoutes(
    PDO $pdo,
    array $scope
): array {
    if (!studentsTableExists($pdo, 'school_routes')) {
        return [];
    }

    $where = [
        'r.tenant_id = :tenant_id',
        "r.status = 'active'",
    ];

    $params = [
        'tenant_id' => $scope['tenant_id'],
    ];

    if (
        (int)$scope['branch_id'] > 0
        && studentsColumnExists(
            $pdo,
            'school_routes',
            'branch_id'
        )
    ) {
        $where[] =
            'r.branch_id = :branch_id';
        $params['branch_id'] = (int)$scope['branch_id'];
    }

    $hasVehicles =
        studentsTableExists($pdo, 'school_vehicles');

    $routeHasVehicleId =
        studentsColumnExists(
            $pdo,
            'school_routes',
            'vehicle_id'
        );

    $vehicleHasRouteId =
        $hasVehicles
        && studentsColumnExists(
            $pdo,
            'school_vehicles',
            'route_id'
        );

    $routeHasDriver =
        studentsColumnExists(
            $pdo,
            'school_routes',
            'driver_name'
        );

    $vehicleHasDriver =
        $hasVehicles
        && studentsColumnExists(
            $pdo,
            'school_vehicles',
            'driver_name'
        );

    $routeVehicleExpression = $routeHasVehicleId
        ? 'NULLIF(r.vehicle_id, 0)'
        : 'NULL';

    if ($hasVehicles && $vehicleHasRouteId) {
        $fallbackVehicleExpression =
            "(SELECT v2.id
              FROM school_vehicles v2
              WHERE v2.tenant_id = r.tenant_id
                AND v2.route_id = r.id
              ORDER BY
                CASE
                    WHEN LOWER(COALESCE(v2.status, 'active')) = 'active'
                    THEN 0
                    ELSE 1
                END,
                v2.id
              LIMIT 1)";
    } else {
        $fallbackVehicleExpression = 'NULL';
    }

    $resolvedVehicleExpression =
        "COALESCE(
            {$routeVehicleExpression},
            {$fallbackVehicleExpression}
        )";

    $vehicleJoin = $hasVehicles
        ? "LEFT JOIN school_vehicles v
              ON v.id = {$resolvedVehicleExpression}
             AND v.tenant_id = r.tenant_id"
        : '';

    $vehicleIdField = $hasVehicles
        ? "COALESCE(v.id, {$routeVehicleExpression}) AS vehicle_id"
        : "{$routeVehicleExpression} AS vehicle_id";

    $vehicleNameField = $hasVehicles
        && studentsColumnExists(
            $pdo,
            'school_vehicles',
            'vehicle_name'
        )
        ? "COALESCE(v.vehicle_name, '') AS vehicle_name"
        : "'' AS vehicle_name";

    $vehicleNumberField = $hasVehicles
        && studentsColumnExists(
            $pdo,
            'school_vehicles',
            'vehicle_number'
        )
        ? "COALESCE(v.vehicle_number, '') AS vehicle_number"
        : "'' AS vehicle_number";

    $driverParts = [];

    if ($routeHasDriver) {
        $driverParts[] = "NULLIF(r.driver_name, '')";
    }

    if ($vehicleHasDriver) {
        $driverParts[] = "NULLIF(v.driver_name, '')";
    }

    $driverParts[] = "''";

    $driverField =
        'COALESCE(' .
        implode(', ', $driverParts) .
        ') AS assigned_driver';

    $branchField = studentsColumnExists(
        $pdo,
        'school_routes',
        'branch_id'
    )
        ? 'r.branch_id'
        : 'NULL AS branch_id';

    $statement = $pdo->prepare(
        "SELECT
            r.id,
            {$branchField},
            r.route_code,
            r.route_name,
            {$vehicleIdField},
            {$vehicleNameField},
            {$vehicleNumberField},
            {$driverField},
            r.status
         FROM school_routes r
         {$vehicleJoin}
         WHERE " . implode(' AND ', $where) . "
         ORDER BY r.route_name, r.route_code"
    );

    $statement->execute($params);

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function studentsTransportStops(
    PDO $pdo,
    array $scope
): array {
    if (!studentsTableExists($pdo, 'school_route_stops')) {
        return [];
    }

    $where = [
        'rs.tenant_id = :tenant_id',
        "rs.status = 'active'",
    ];
    $params = [
        'tenant_id' => $scope['tenant_id'],
    ];

    if (
        (int)$scope['branch_id'] > 0
        && studentsColumnExists(
            $pdo,
            'school_route_stops',
            'branch_id'
        )
    ) {
        $where[] =
            'rs.branch_id = :branch_id';
        $params['branch_id'] = (int)$scope['branch_id'];
    }

    $statement = $pdo->prepare(
        "SELECT
            rs.id,
            rs.route_id,
            rs.stop_name,
            rs.stop_order,
            COALESCE(rs.transport_fee, 0) AS transport_fee,
            rs.status
         FROM school_route_stops rs
         WHERE " . implode(' AND ', $where) . "
         ORDER BY rs.route_id, rs.stop_order, rs.stop_name"
    );

    $statement->execute($params);

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function studentsResolveFeeStructure(
    PDO $pdo,
    int $tenantId,
    int $branchId,
    int $academicYearId,
    int $canonicalClassId,
    int $requestedStructureId
): array {
    $params = [
        'tenant_id' => $tenantId,
        'branch_id' => $branchId,
        'academic_year_id' => $academicYearId,
        'class_id' => $canonicalClassId,
    ];
    $where = [
        'fs.tenant_id = :tenant_id',
        'fs.branch_id = :branch_id',
        'fs.academic_year_id = :academic_year_id',
        'fs.class_id = :class_id',
        "fs.status = 'active'",
    ];

    if ($requestedStructureId > 0) {
        $where[] = 'fs.id = :structure_id';
        $params['structure_id'] = $requestedStructureId;
    }

    $statement = $pdo->prepare(
        "SELECT
            fs.id,
            fs.structure_name,
            fs.academic_year_id,
            fs.class_id,
            ay.start_date,
            ay.end_date
         FROM fee_structures fs
         INNER JOIN academic_years ay
            ON ay.id = fs.academic_year_id
           AND ay.tenant_id = fs.tenant_id
           AND ay.branch_id = fs.branch_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY fs.id DESC"
    );
    $statement->execute($params);
    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

    if ($requestedStructureId > 0 && !$rows) {
        throw new InvalidArgumentException(
            'The selected Fee Structure does not match the current Branch, Academic Year and Class.'
        );
    }
    if (count($rows) > 1) {
        throw new InvalidArgumentException(
            'More than one Active Fee Structure exists for this Branch, Academic Year and Class. Keep only one structure active.'
        );
    }
    if (!$rows) {
        throw new InvalidArgumentException(
            'No Active Fee Structure exists for the current Branch, Academic Year and Class.'
        );
    }

    return $rows[0];
}

function studentsResolveTransportSelection(
    PDO $pdo,
    int $tenantId,
    int $branchId,
    bool $transportRequired,
    int $routeId,
    int $stopId
): ?array {
    if (!$transportRequired) {
        return null;
    }

    if ($routeId <= 0) {
        throw new InvalidArgumentException(
            'Select a Route when Transport Required is Yes.'
        );
    }

    if ($stopId <= 0) {
        throw new InvalidArgumentException(
            'Select a Boarding Stop when Transport Required is Yes.'
        );
    }

    if (!studentsTableExists($pdo, 'school_routes')) {
        throw new RuntimeException(
            'Route Master table is missing.'
        );
    }

    if (!studentsTableExists($pdo, 'school_route_stops')) {
        throw new RuntimeException(
            'Route Stops table is missing.'
        );
    }

    $hasVehicles =
        studentsTableExists($pdo, 'school_vehicles');

    $routeHasVehicleId =
        studentsColumnExists(
            $pdo,
            'school_routes',
            'vehicle_id'
        );

    $vehicleHasRouteId =
        $hasVehicles
        && studentsColumnExists(
            $pdo,
            'school_vehicles',
            'route_id'
        );

    $routeHasDriver =
        studentsColumnExists(
            $pdo,
            'school_routes',
            'driver_name'
        );

    $vehicleHasDriver =
        $hasVehicles
        && studentsColumnExists(
            $pdo,
            'school_vehicles',
            'driver_name'
        );

    $routeVehicleExpression = $routeHasVehicleId
        ? 'NULLIF(r.vehicle_id, 0)'
        : 'NULL';

    if ($hasVehicles && $vehicleHasRouteId) {
        $fallbackVehicleExpression =
            "(SELECT v2.id
              FROM school_vehicles v2
              WHERE v2.tenant_id = r.tenant_id
                AND v2.route_id = r.id
              ORDER BY
                CASE
                    WHEN LOWER(COALESCE(v2.status, 'active')) = 'active'
                    THEN 0
                    ELSE 1
                END,
                v2.id
              LIMIT 1)";
    } else {
        $fallbackVehicleExpression = 'NULL';
    }

    $resolvedVehicleExpression =
        "COALESCE(
            {$routeVehicleExpression},
            {$fallbackVehicleExpression}
        )";

    $vehicleJoin = $hasVehicles
        ? "LEFT JOIN school_vehicles v
              ON v.id = {$resolvedVehicleExpression}
             AND v.tenant_id = r.tenant_id"
        : '';

    $vehicleIdField = $hasVehicles
        ? "COALESCE(v.id, {$routeVehicleExpression}) AS vehicle_id"
        : "{$routeVehicleExpression} AS vehicle_id";

    $vehicleNameField = $hasVehicles
        && studentsColumnExists(
            $pdo,
            'school_vehicles',
            'vehicle_name'
        )
        ? "COALESCE(v.vehicle_name, '') AS vehicle_name"
        : "'' AS vehicle_name";

    $vehicleNumberField = $hasVehicles
        && studentsColumnExists(
            $pdo,
            'school_vehicles',
            'vehicle_number'
        )
        ? "COALESCE(v.vehicle_number, '') AS vehicle_number"
        : "'' AS vehicle_number";

    $driverParts = [];

    if ($routeHasDriver) {
        $driverParts[] = "NULLIF(r.driver_name, '')";
    }

    if ($vehicleHasDriver) {
        $driverParts[] = "NULLIF(v.driver_name, '')";
    }

    $driverParts[] = "''";

    $driverField =
        'COALESCE(' .
        implode(', ', $driverParts) .
        ') AS driver_name';

    $branchCondition = studentsColumnExists(
        $pdo,
        'school_routes',
        'branch_id'
    )
        ? "AND r.branch_id = :branch_id"
        : '';

    $branchField = studentsColumnExists(
        $pdo,
        'school_routes',
        'branch_id'
    )
        ? 'r.branch_id'
        : 'NULL AS branch_id';

    $statement = $pdo->prepare(
        "SELECT
            r.id,
            {$branchField},
            r.route_name,
            r.route_code,
            {$vehicleIdField},
            {$vehicleNameField},
            {$vehicleNumberField},
            {$driverField},
            r.status
         FROM school_routes r
         {$vehicleJoin}
         WHERE r.id = :route_id
           AND r.tenant_id = :tenant_id
           AND r.status = 'active'
           {$branchCondition}
         LIMIT 1"
    );

    $params = [
        'route_id' => $routeId,
        'tenant_id' => $tenantId,
    ];

    if ($branchCondition !== '') {
        $params['branch_id'] = $branchId;
    }

    $statement->execute($params);
    $route = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$route) {
        throw new InvalidArgumentException(
            'Selected Route is invalid, inactive, or belongs to another Branch.'
        );
    }

    /*
     * Older records sometimes store the assignment only in
     * school_vehicles.route_id. When found, synchronize the Route Master
     * vehicle_id so all modules use the same assignment going forward.
     */
    if (
        $routeHasVehicleId
        && (int)($route['vehicle_id'] ?? 0) > 0
    ) {
        try {
            $sync = $pdo->prepare(
                "UPDATE school_routes
                 SET vehicle_id = ?
                 WHERE id = ?
                   AND tenant_id = ?
                   AND (
                        vehicle_id IS NULL
                        OR vehicle_id = 0
                   )"
            );

            $sync->execute([
                (int)$route['vehicle_id'],
                $routeId,
                $tenantId,
            ]);
        } catch (Throwable $exception) {
            error_log(
                'Student transport vehicle sync failed: ' .
                $exception->getMessage()
            );
        }
    }

    $statement = $pdo->prepare(
        "SELECT
            id,
            route_id,
            stop_name,
            stop_order,
            COALESCE(transport_fee, 0) AS transport_fee,
            status
         FROM school_route_stops
         WHERE id = :stop_id
           AND route_id = :route_id
           AND tenant_id = :tenant_id
           AND status = 'active'
         LIMIT 1"
    );

    $statement->execute([
        'stop_id' => $stopId,
        'route_id' => $routeId,
        'tenant_id' => $tenantId,
    ]);

    $stop = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$stop) {
        throw new InvalidArgumentException(
            'Selected Boarding Stop does not belong to the selected Route.'
        );
    }

    $fee = round(
        (float)($stop['transport_fee'] ?? 0),
        2
    );

    if ($fee < 0) {
        throw new InvalidArgumentException(
            'The selected Boarding Stop has an invalid Transport Fee.'
        );
    }

    /*
     * A vehicle is useful but is no longer allowed to block Admission.
     * When no vehicle has been assigned yet, the route and stop are saved,
     * vehicle_id remains NULL, and the page displays "Not Assigned".
     */
    $resolvedVehicleId =
        (int)($route['vehicle_id'] ?? 0);

    return array_merge(
        $route,
        [
            'vehicle_id' =>
                $resolvedVehicleId > 0
                    ? $resolvedVehicleId
                    : null,
            'vehicle_name' =>
                trim((string)($route['vehicle_name'] ?? '')),
            'vehicle_number' =>
                trim((string)($route['vehicle_number'] ?? '')),
            'driver_name' =>
                trim((string)($route['driver_name'] ?? '')),
            'stop_id' => (int)$stop['id'],
            'stop_name' => (string)$stop['stop_name'],
            'stop_order' => (int)$stop['stop_order'],
            'transport_fee' => $fee,
            'bus_fee' => $fee,
        ]
    );
}

function studentsAggregateAssignment(
    PDO $pdo,
    int $tenantId,
    int $assignmentId,
    ?array $transport
): void {
    $statement = $pdo->prepare(
        "SELECT
            COALESCE(SUM(original_amount),0) AS gross,
            COALESCE(SUM(discount_amount),0) AS discount,
            COALESCE(SUM(paid_amount),0) AS paid,
            COALESCE(SUM(balance_amount),0) AS balance,
            COALESCE(
                SUM(
                    CASE
                        WHEN item_type <> 'transport'
                        THEN original_amount
                        ELSE 0
                    END
                ),
                0
            ) AS base_fee,
            COALESCE(
                SUM(
                    CASE
                        WHEN item_type = 'transport'
                        THEN original_amount
                        ELSE 0
                    END
                ),
                0
            ) AS transport_fee
         FROM student_fee_items
         WHERE tenant_id = :tenant_id
           AND assignment_id = :assignment_id
           AND item_status <> 'cancelled'"
    );

    $statement->execute([
        'tenant_id' => $tenantId,
        'assignment_id' => $assignmentId,
    ]);

    $totals = $statement->fetch(PDO::FETCH_ASSOC) ?: [];
    $gross = (float)($totals['gross'] ?? 0);
    $discount = (float)($totals['discount'] ?? 0);
    $paid = (float)($totals['paid'] ?? 0);
    $balance = (float)($totals['balance'] ?? 0);

    $status = $balance <= 0.009
        ? 'paid'
        : ($paid > 0.009 ? 'partial' : 'unpaid');

    $assignmentRouteId = (
        $transport
        && studentsColumnAcceptsSchoolRoute(
            $pdo,
            'student_fee_assignments',
            'transport_route_id'
        )
    )
        ? (int)$transport['id']
        : null;

    $update = $pdo->prepare(
        "UPDATE student_fee_assignments
         SET
            base_fee_amount = :base_fee,
            transport_fee_amount = :transport_fee,
            transport_route_id = :route_id,
            transport_stop_id = :stop_id,
            transport_vehicle_id = :vehicle_id,
            transport_driver_name = :driver_name,
            gross_amount = :gross,
            net_amount = :net,
            paid_amount = :paid,
            balance_amount = :balance,
            payment_status = :payment_status,
            schedule_generated_at = CURRENT_TIMESTAMP
         WHERE id = :assignment_id
           AND tenant_id = :tenant_id"
    );

    $update->execute([
        'base_fee' => $totals['base_fee'] ?? 0,
        'transport_fee' => $totals['transport_fee'] ?? 0,
        'route_id' => $assignmentRouteId,
        'stop_id' => $transport['stop_id'] ?? null,
        'vehicle_id' => $transport['vehicle_id'] ?? null,
        'driver_name' => $transport['driver_name'] ?? null,
        'gross' => $gross,
        'net' => max(0, $gross - $discount),
        'paid' => $paid,
        'balance' => $balance,
        'payment_status' => $status,
        'assignment_id' => $assignmentId,
        'tenant_id' => $tenantId,
    ]);
}

function studentsUpsertTransport(
    PDO $pdo,
    int $tenantId,
    int $studentId,
    int $academicYearId,
    bool $transportRequired,
    ?array $transport,
    string $assignedOn
): void {
    $statement = $pdo->prepare(
        "INSERT INTO student_transport_assignments(
            tenant_id,
            student_id,
            academic_year_id,
            route_id,
            stop_id,
            vehicle_id,
            route_name,
            boarding_stop_name,
            vehicle_name,
            driver_name,
            transport_required,
            transport_fee_amount,
            bus_fee_amount,
            assigned_on,
            status
         ) VALUES(
            :tenant_id,
            :student_id,
            :academic_year_id,
            :route_id,
            :stop_id,
            :vehicle_id,
            :route_name,
            :boarding_stop_name,
            :vehicle_name,
            :driver_name,
            :transport_required,
            :transport_fee_amount,
            :bus_fee_amount,
            :assigned_on,
            'active'
         )
         ON DUPLICATE KEY UPDATE
            route_id = VALUES(route_id),
            stop_id = VALUES(stop_id),
            vehicle_id = VALUES(vehicle_id),
            route_name = VALUES(route_name),
            boarding_stop_name = VALUES(boarding_stop_name),
            vehicle_name = VALUES(vehicle_name),
            driver_name = VALUES(driver_name),
            transport_required = VALUES(transport_required),
            transport_fee_amount = VALUES(transport_fee_amount),
            bus_fee_amount = VALUES(bus_fee_amount),
            assigned_on = VALUES(assigned_on),
            status = 'active'"
    );

    $fee = $transport
        ? round((float)$transport['transport_fee'], 2)
        : 0;

    $transportAssignmentRouteId = (
        $transport
        && studentsColumnAcceptsSchoolRoute(
            $pdo,
            'student_transport_assignments',
            'route_id'
        )
    )
        ? (int)$transport['id']
        : null;

    $statement->execute([
        'tenant_id' => $tenantId,
        'student_id' => $studentId,
        'academic_year_id' => $academicYearId,
        'route_id' => $transportAssignmentRouteId,
        'stop_id' => $transport['stop_id'] ?? null,
        'vehicle_id' => $transport['vehicle_id'] ?? null,
        'route_name' => $transport['route_name'] ?? null,
        'boarding_stop_name' => $transport['stop_name'] ?? null,
        'vehicle_name' => $transport['vehicle_name'] ?? null,
        'driver_name' => $transport['driver_name'] ?? null,
        'transport_required' => $transportRequired ? 1 : 0,
        'transport_fee_amount' => $fee,
        'bus_fee_amount' => $fee,
        'assigned_on' => $assignedOn,
    ]);
}

function studentsGenerateFeeSchedule(
    PDO $pdo,
    array $scope,
    int $assignmentId,
    int $studentId,
    array $structure,
    bool $applyAdmissionFee,
    ?array $route
): void {
    $tenantId = $scope['tenant_id'];
    $scheduleStart = new DateTimeImmutable($structure['start_date']);
    $assignmentDate = new DateTimeImmutable(
        (string)($structure['assignment_date'] ?? $structure['start_date'])
    );

    /*
     * For a new admission, fees start from the actual enrollment/admission
     * date. For promoted/existing students, the academic-year start is used.
     */
    if ($applyAdmissionFee && $assignmentDate > $scheduleStart) {
        $scheduleStart = $assignmentDate;
    }

    /*
     * Never destroy fee rows which already have a collection/waiver/discount.
     * Only untouched generated rows are rebuilt. This allows Route, Boarding
     * Stop and Bus Fee changes even when Tuition/Admission collection has
     * already started, while preserving the financial history exactly.
     */
    $protectedStatement = $pdo->prepare(
        "SELECT item_type, period_key, original_amount
         FROM student_fee_items
         WHERE tenant_id = :tenant_id
           AND assignment_id = :assignment_id
           AND item_type IN('admission','tuition','term','additional','transport')
           AND item_status <> 'cancelled'
           AND (
                COALESCE(paid_amount,0) > 0.009
                OR COALESCE(discount_amount,0) > 0.009
                OR item_status IN('paid','partial','waived')
           )"
    );
    $protectedStatement->execute([
        'tenant_id' => $tenantId,
        'assignment_id' => $assignmentId,
    ]);

    $protectedKeys = [];
    $protectedTransportGross = 0.0;
    foreach ($protectedStatement->fetchAll(PDO::FETCH_ASSOC) as $protectedRow) {
        $protectedType = strtolower((string)$protectedRow['item_type']);
        $protectedKeys[
            $protectedType .
            '|' . (string)$protectedRow['period_key']
        ] = true;
        if ($protectedType === 'transport') {
            $protectedTransportGross += (float)($protectedRow['original_amount'] ?? 0);
        }
    }

    $pdo->prepare(
        "DELETE FROM student_fee_items
         WHERE tenant_id = :tenant_id
           AND assignment_id = :assignment_id
           AND item_type IN('admission','tuition','term','additional','transport')
           AND COALESCE(paid_amount,0) <= 0.009
           AND COALESCE(discount_amount,0) <= 0.009
           AND item_status NOT IN('paid','partial','waived')"
    )->execute([
        'tenant_id' => $tenantId,
        'assignment_id' => $assignmentId,
    ]);

    $hasDynamicFeeTypes = studentsTableExists($pdo, 'fee_types')
        && studentsColumnExists($pdo, 'fee_structure_items', 'fee_type_id');

    if ($hasDynamicFeeTypes) {
        $itemStatement = $pdo->prepare(
            "SELECT
                fsi.id,
                fsi.fee_type_id,
                LOWER(ft.fee_type_code) AS fee_type_key,
                ft.fee_type_name AS head_name,
                fsi.amount,
                COALESCE(NULLIF(fsi.frequency, ''), ft.default_frequency, 'one_time') AS frequency,
                GREATEST(1, COALESCE(NULLIF(fsi.occurrence_count, 0), ft.default_occurrence_count, 1)) AS occurrence_count,
                COALESCE(fsi.status, 'active') AS status
             FROM fee_structure_items fsi
             INNER JOIN fee_types ft
                ON ft.id = fsi.fee_type_id
               AND ft.tenant_id = :tenant_id
               AND ft.deleted_at IS NULL
             WHERE fsi.fee_structure_id = :structure_id
               AND COALESCE(fsi.status, 'active') = 'active'
             ORDER BY COALESCE(fsi.display_order, ft.display_order, 0), fsi.id"
        );
    } else {
        $itemStatement = $pdo->prepare(
            "SELECT
                fsi.id,
                NULL AS fee_type_id,
                fsi.fee_type_key,
                fsi.amount,
                fsi.frequency,
                COALESCE(NULLIF(fsi.occurrence_count, 0), 1) AS occurrence_count,
                fsi.status,
                fh.head_name
             FROM fee_structure_items fsi
             INNER JOIN fee_heads fh
                ON fh.id = fsi.fee_head_id
             WHERE fsi.fee_structure_id = :structure_id
               AND fsi.status = 'active'
             ORDER BY fsi.display_order, fsi.id"
        );
    }

    $itemParameters = ['structure_id' => $structure['id']];
    if ($hasDynamicFeeTypes) {
        $itemParameters['tenant_id'] = $tenantId;
    }
    $itemStatement->execute($itemParameters);
    $structureItems = $itemStatement->fetchAll(PDO::FETCH_ASSOC);

    $monthlyDueDay = max(
        1,
        (int)studentsFeeSetting($pdo, $tenantId, 'monthly_due_day', '10')
    );
    $transportDueDay = max(
        1,
        (int)studentsFeeSetting($pdo, $tenantId, 'transport_due_day', '10')
    );

    $insert = $pdo->prepare(
        "INSERT INTO student_fee_items(
            tenant_id, assignment_id, student_id, academic_year_id,
            fee_structure_item_id,
            transport_route_id,
            transport_stop_id,
            item_type,
            item_name,
            period_key,
            period_label,
            due_date,
            original_amount, discount_amount, paid_amount,
            balance_amount, item_status
         ) VALUES(
            :tenant_id, :assignment_id, :student_id, :academic_year_id,
            :structure_item_id,
            :route_id,
            :stop_id,
            :item_type,
            :item_name,
            :period_key,
            :period_label,
            :due_date,
            :amount, 0, 0, :balance, 'unpaid'
         )"
    );

    foreach ($structureItems as $item) {
        $type = strtolower((string)($item['fee_type_key'] ?? ''));
        $amount = round((float)$item['amount'], 2);
        if ($amount <= 0) {
            continue;
        }

        $frequency = strtolower((string)($item['frequency'] ?? 'one_time'));
        $occurrenceCount = max(1, (int)($item['occurrence_count'] ?? 1));
        $isAdmissionType = str_contains($type, 'admission');

        if ($isAdmissionType && !$applyAdmissionFee) {
            continue;
        }

        $itemType = match (true) {
            str_contains($type, 'admission') => 'admission',
            str_contains($type, 'tuition') => 'tuition',
            str_contains($type, 'exam'), str_contains($type, 'term') => 'term',
            default => 'additional',
        };

        $periods = studentsFrequencyPeriods(
            $frequency,
            $frequency === 'one_time'
                ? $assignmentDate->format('Y-m-d')
                : $scheduleStart->format('Y-m-d'),
            $structure['end_date'],
            $occurrenceCount,
            $monthlyDueDay,
            (string)($item['fee_type_key'] ?: 'FEE'),
            (string)$item['head_name']
        );

        $periods = array_values(
            array_filter(
                $periods,
                static fn(array $period): bool =>
                    $period['due_date'] >= $scheduleStart->format('Y-m-d')
            )
        );

        foreach ($periods as $period) {
            $protectedKey = $itemType . '|' . (string)$period['key'];
            if (isset($protectedKeys[$protectedKey])) {
                continue;
            }

            $insert->execute([
                'tenant_id' => $tenantId,
                'assignment_id' => $assignmentId,
                'student_id' => $studentId,
                'academic_year_id' => $structure['academic_year_id'],
                'structure_item_id' => $item['id'],
                'route_id' => null,
                'stop_id' => null,
                'item_type' => $itemType,
                'item_name' => $item['head_name'],
                'period_key' => $period['key'],
                'period_label' => $period['label'],
                'due_date' => $period['due_date'],
                'amount' => $amount,
                'balance' => $amount,
            ]);
        }
    }

    if ($route && (float)$route['transport_fee'] > 0) {
        /*
         * Transport is a fixed one-time student charge. The boarding-stop
         * amount is stored exactly once and is never expanded into monthly
         * BUS-* rows.
         */
        $amount = round(
            max(0.0, (float)$route['transport_fee'] - $protectedTransportGross),
            2
        );
        $feeItemRouteId = null;
        $periodKey = 'BUS-FIXED';

        if ($amount > 0.009 && !isset($protectedKeys['transport|' . $periodKey])) {
            $insert->execute([
                'tenant_id' => $tenantId,
                'assignment_id' => $assignmentId,
                'student_id' => $studentId,
                'academic_year_id' => $structure['academic_year_id'],
                'structure_item_id' => null,
                'route_id' => $feeItemRouteId,
                'stop_id' => $route['stop_id'],
                'item_type' => 'transport',
                'item_name' =>
                    'Bus Fee - ' .
                    $route['route_name'] .
                    ' / ' .
                    $route['stop_name'],
                'period_key' => $periodKey,
                'period_label' => 'Fixed',
                'due_date' => $scheduleStart->format('Y-m-d'),
                'amount' => $amount,
                'balance' => $amount,
            ]);
        }
    }

    studentsAggregateAssignment(
        $pdo,
        $tenantId,
        $assignmentId,
        $route
    );
}
function studentsAssignFeeStructure(
    PDO $pdo,
    array $scope,
    int $studentId,
    int $academicYearId,
    int $canonicalClassId,
    int $branchId,
    int $requestedStructureId,
    bool $transportRequired,
    int $routeId,
    int $stopId,
    bool $newStudent,
    string $assignmentDate
): int {
    $structure = studentsResolveFeeStructure(
        $pdo,
        $scope['tenant_id'],
        $branchId,
        $academicYearId,
        $canonicalClassId,
        $requestedStructureId
    );

    $transport = studentsResolveTransportSelection(
        $pdo,
        $scope['tenant_id'],
        $branchId,
        $transportRequired,
        $routeId,
        $stopId
    );

    $feeAssignmentRouteId = (
        $transport
        && studentsColumnAcceptsSchoolRoute(
            $pdo,
            'student_fee_assignments',
            'transport_route_id'
        )
    )
        ? (int)$transport['id']
        : null;

    $existing = $pdo->prepare(
        "SELECT *
         FROM student_fee_assignments
         WHERE tenant_id = :tenant_id
           AND student_id = :student_id
           AND academic_year_id = :academic_year_id
           AND assignment_status = 'active'
         ORDER BY id DESC
         LIMIT 1
         FOR UPDATE"
    );

    $existing->execute([
        'tenant_id' => $scope['tenant_id'],
        'student_id' => $studentId,
        'academic_year_id' => $academicYearId,
    ]);

    $assignment = $existing->fetch(PDO::FETCH_ASSOC);

    if ($assignment) {
        $paidStatement = $pdo->prepare(
            "SELECT COALESCE(SUM(paid_amount),0)
             FROM student_fee_items
             WHERE tenant_id = :tenant_id
               AND assignment_id = :assignment_id
               AND item_status <> 'cancelled'"
        );
        $paidStatement->execute([
            'tenant_id' => $scope['tenant_id'],
            'assignment_id' => (int)$assignment['id'],
        ]);
        $paid = max(
            (float)($assignment['paid_amount'] ?? 0),
            (float)$paidStatement->fetchColumn()
        );

        $feeStructureChanged =
            (int)$assignment['fee_structure_id']
                !== (int)$structure['id'];

        /*
         * A collected Fee Structure remains immutable, but transport is not.
         * Route/Stop/Bus Fee changes are handled by the schedule synchronizer,
         * which preserves collected rows and rebuilds only untouched rows.
         */
        if ($paid > 0.009 && $feeStructureChanged) {
            throw new InvalidArgumentException(
                'Fee Structure cannot be changed after Fee Collection has started. Bus Route, Boarding Stop and Bus Fee can still be updated.'
            );
        }

        if ($feeStructureChanged) {
            $pdo->prepare(
                "UPDATE student_fee_assignments
                 SET assignment_status = 'inactive'
                 WHERE id = :id
                   AND tenant_id = :tenant_id"
            )->execute([
                'id' => $assignment['id'],
                'tenant_id' => $scope['tenant_id'],
            ]);

            $assignment = null;
        }
    }

    if (!$assignment) {
        $insert = $pdo->prepare(
            "INSERT INTO student_fee_assignments(
                tenant_id,
                student_id,
                academic_year_id,
                fee_structure_id,
                assignment_date,
                assignment_status,
                is_new_admission,
                base_fee_amount,
                transport_fee_amount,
                transport_route_id,
                transport_stop_id,
                transport_vehicle_id,
                transport_driver_name,
                gross_amount,
                concession_amount,
                scholarship_amount,
                net_amount,
                paid_amount,
                balance_amount,
                payment_status
             ) VALUES(
                :tenant_id,
                :student_id,
                :academic_year_id,
                :structure_id,
                :assignment_date,
                'active',
                :is_new_admission,
                0,
                0,
                :route_id,
                :stop_id,
                :vehicle_id,
                :driver_name,
                0,
                0,
                0,
                0,
                0,
                0,
                'unpaid'
             )"
        );

        $insert->execute([
            'tenant_id' => $scope['tenant_id'],
            'student_id' => $studentId,
            'academic_year_id' => $academicYearId,
            'structure_id' => $structure['id'],
            'assignment_date' => $assignmentDate,
            'is_new_admission' => $newStudent ? 1 : 0,
            'route_id' => $feeAssignmentRouteId,
            'stop_id' => $transport['stop_id'] ?? null,
            'vehicle_id' => $transport['vehicle_id'] ?? null,
            'driver_name' => $transport['driver_name'] ?? null,
        ]);

        $assignmentId = (int)$pdo->lastInsertId();
        $applyAdmissionFee = $newStudent;
    } else {
        $assignmentId = (int)$assignment['id'];
        $applyAdmissionFee =
            (int)($assignment['is_new_admission'] ?? 0) === 1;

        $pdo->prepare(
            "UPDATE student_fee_assignments
             SET
                fee_structure_id = :structure_id,
                transport_route_id = :route_id,
                transport_stop_id = :stop_id,
                transport_vehicle_id = :vehicle_id,
                transport_driver_name = :driver_name,
                assignment_date = :assignment_date,
                assignment_status = 'active'
             WHERE id = :id
               AND tenant_id = :tenant_id"
        )->execute([
            'structure_id' => $structure['id'],
            'route_id' => $feeAssignmentRouteId,
            'stop_id' => $transport['stop_id'] ?? null,
            'vehicle_id' => $transport['vehicle_id'] ?? null,
            'driver_name' => $transport['driver_name'] ?? null,
            'assignment_date' => $assignmentDate,
            'id' => $assignmentId,
            'tenant_id' => $scope['tenant_id'],
        ]);
    }

    $structure['assignment_date'] = $assignmentDate;

    studentsGenerateFeeSchedule(
        $pdo,
        $scope,
        $assignmentId,
        $studentId,
        $structure,
        $applyAdmissionFee,
        $transport
    );

    studentsUpsertTransport(
        $pdo,
        $scope['tenant_id'],
        $studentId,
        $academicYearId,
        $transportRequired,
        $transport,
        $assignmentDate
    );

    return (int)$structure['id'];
}


function studentsParentLoginRequireSchema(PDO $pdo): void
{
    foreach (
        [
            'users',
            'roles',
            'guardians',
            'student_guardians',
            'students',
            'student_parent_logins',
        ] as $table
    ) {
        if (!studentsTableExists($pdo, $table)) {
            throw new RuntimeException(
                'Parent Login requires the ' . $table . ' table.',
                500
            );
        }
    }
}

function studentsParentRole(PDO $pdo, array $scope): array
{
    studentsParentLoginRequireSchema($pdo);

    $statement = $pdo->prepare(
        "SELECT
            id,
            tenant_id,
            role_key,
            role_name,
            role_scope,
            is_system,
            status,
            deleted_at
         FROM roles
         WHERE tenant_id = :tenant_id
           AND role_key = 'parent'
         LIMIT 1"
    );
    $statement->execute([
        'tenant_id' => $scope['tenant_id'],
    ]);
    $role = $statement->fetch(PDO::FETCH_ASSOC);

    if ($role) {
        if (
            (string)$role['status'] !== 'active'
            || !empty($role['deleted_at'])
        ) {
            $update = $pdo->prepare(
                "UPDATE roles
                 SET role_name = 'Parent',
                     description = 'Parent Portal Login',
                     role_scope = 'school',
                     status = 'active',
                     deleted_at = NULL
                 WHERE id = :role_id
                   AND tenant_id = :tenant_id"
            );
            $update->execute([
                'role_id' => (int)$role['id'],
                'tenant_id' => $scope['tenant_id'],
            ]);
        }
    } else {
        $insert = $pdo->prepare(
            "INSERT INTO roles
             (
                tenant_id,
                role_key,
                role_name,
                description,
                role_scope,
                is_system,
                status,
                created_by
             )
             VALUES
             (
                :tenant_id,
                'parent',
                'Parent',
                'Parent Portal Login',
                'school',
                0,
                'active',
                :created_by
             )"
        );
        $insert->execute([
            'tenant_id' => $scope['tenant_id'],
            'created_by' => $scope['user_id'] > 0
                ? $scope['user_id']
                : null,
        ]);
    }

    $statement = $pdo->prepare(
        "SELECT
            id,
            tenant_id,
            role_key,
            role_name,
            role_scope,
            is_system,
            status
         FROM roles
         WHERE tenant_id = :tenant_id
           AND role_key = 'parent'
           AND status = 'active'
           AND deleted_at IS NULL
         LIMIT 1"
    );
    $statement->execute([
        'tenant_id' => $scope['tenant_id'],
    ]);
    $role = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$role) {
        throw new RuntimeException(
            'Unable to prepare the Parent role.',
            500
        );
    }

    return $role;
}


function studentsParentLoginNormalizeFilters(
    PDO $pdo,
    array $scope,
    array $filters = [],
    bool $requireComplete = false
): array {
    $yearId = max(0, (int)($filters['academic_year_id'] ?? 0));
    $classId = max(0, (int)($filters['class_id'] ?? 0));
    $sectionId = max(0, (int)($filters['section_id'] ?? 0));

    if ($requireComplete && ($yearId <= 0 || $classId <= 0 || $sectionId <= 0)) {
        throw new InvalidArgumentException(
            'Select Academic Year, Class and Section before loading students.'
        );
    }

    if ($yearId > 0) {
        $yearStatement = $pdo->prepare(
            "SELECT id, year_name
             FROM academic_years
             WHERE id = :year_id
               AND tenant_id = :tenant_id
               AND branch_id = :branch_id
             LIMIT 1"
        );
        $yearStatement->execute([
            'year_id' => $yearId,
            'tenant_id' => $scope['tenant_id'],
            'branch_id' => $scope['branch_id'],
        ]);
        if (!$yearStatement->fetch(PDO::FETCH_ASSOC)) {
            throw new InvalidArgumentException('Selected Academic Year is invalid.');
        }
    }

    if ($classId > 0) {
        if ($yearId <= 0) {
            throw new InvalidArgumentException('Select Academic Year before selecting Class.');
        }

        $classStatement = $pdo->prepare(
            "SELECT id, class_name
             FROM classes
             WHERE id = :class_id
               AND tenant_id = :tenant_id
               AND branch_id = :branch_id
               AND academic_year_id = :academic_year_id
             LIMIT 1"
        );
        $classStatement->execute([
            'class_id' => $classId,
            'tenant_id' => $scope['tenant_id'],
            'branch_id' => $scope['branch_id'],
            'academic_year_id' => $yearId,
        ]);
        if (!$classStatement->fetch(PDO::FETCH_ASSOC)) {
            throw new InvalidArgumentException(
                'Selected Class does not belong to the selected Academic Year.'
            );
        }
    }

    if ($sectionId > 0) {
        if ($classId <= 0) {
            throw new InvalidArgumentException('Select Class before selecting Section.');
        }

        $sectionStatement = $pdo->prepare(
            "SELECT s.id
             FROM sections s
             INNER JOIN classes c
                ON c.id = s.class_id
               AND c.tenant_id = s.tenant_id
               AND c.branch_id = s.branch_id
             WHERE s.id = :section_id
               AND s.tenant_id = :tenant_id
               AND s.branch_id = :branch_id
               AND s.class_id = :class_id
               AND c.academic_year_id = :academic_year_id
             LIMIT 1"
        );
        $sectionStatement->execute([
            'section_id' => $sectionId,
            'tenant_id' => $scope['tenant_id'],
            'branch_id' => $scope['branch_id'],
            'class_id' => $classId,
            'academic_year_id' => $yearId,
        ]);
        if (!$sectionStatement->fetchColumn()) {
            throw new InvalidArgumentException(
                'Selected Section does not belong to the selected Class and Academic Year.'
            );
        }
    }

    return [
        'academic_year_id' => $yearId,
        'class_id' => $classId,
        'section_id' => $sectionId,
    ];
}

function studentsParentLoginRows(
    PDO $pdo,
    array $scope,
    string $studentScope = 'active',
    array $studentIds = [],
    array $filters = []
): array {
    studentsParentLoginRequireSchema($pdo);

    $studentScope = strtolower(trim($studentScope));
    if (!in_array($studentScope, ['active', 'all'], true)) {
        $studentScope = 'active';
    }

    $filter = studentsParentLoginNormalizeFilters(
        $pdo,
        $scope,
        $filters,
        false
    );

    $where = [
        's.tenant_id = :tenant_id',
        's.deleted_at IS NULL',
    ];
    $params = [
        'tenant_id' => $scope['tenant_id'],
    ];

    $where[] = 's.branch_id = :scope_branch_id';
    $params['scope_branch_id'] = $scope['branch_id'];

    if ($studentScope === 'active') {
        $where[] = "s.status = 'active'";
    }

    $normalizedIds = array_values(array_unique(array_filter(
        array_map('intval', $studentIds),
        static fn(int $id): bool => $id > 0
    )));

    if ($normalizedIds) {
        $placeholders = [];
        foreach ($normalizedIds as $index => $studentId) {
            $key = 'student_id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $studentId;
        }
        $where[] = 's.id IN (' . implode(',', $placeholders) . ')';
    }

    if ($filter['academic_year_id'] > 0) {
        $enrollmentJoin = "
         INNER JOIN student_enrollments se
           ON se.student_id = s.id
          AND se.tenant_id = s.tenant_id
          AND se.branch_id = s.branch_id
          AND se.academic_year_id = :parent_filter_year_id";
        $params['parent_filter_year_id'] = $filter['academic_year_id'];

        if ($filter['class_id'] > 0) {
            $where[] = 'se.class_id = :parent_filter_class_id';
            $params['parent_filter_class_id'] = $filter['class_id'];
        }

        if ($filter['section_id'] > 0) {
            $where[] = 'se.section_id = :parent_filter_section_id';
            $params['parent_filter_section_id'] = $filter['section_id'];
        }
    } else {
        $enrollmentJoin = "
         LEFT JOIN student_enrollments se
           ON se.id = (
                SELECT se2.id
                FROM student_enrollments se2
                LEFT JOIN academic_years ay2
                  ON ay2.id = se2.academic_year_id
                 AND ay2.tenant_id = se2.tenant_id
                 AND ay2.branch_id = se2.branch_id
                WHERE se2.student_id = s.id
                  AND se2.tenant_id = s.tenant_id
                  AND se2.branch_id = s.branch_id
                ORDER BY
                    CASE se2.enrollment_status
                        WHEN 'active' THEN 0
                        WHEN 'promoted' THEN 1
                        WHEN 'transferred' THEN 2
                        WHEN 'completed' THEN 3
                        ELSE 4
                    END,
                    COALESCE(ay2.start_date, '1000-01-01') DESC,
                    se2.id DESC
                LIMIT 1
           )";
    }

    $statement = $pdo->prepare(
        "SELECT
            s.id AS student_id,
            s.branch_id,
            s.admission_no AS admission_number,
            TRIM(CONCAT_WS(' ', s.first_name, s.last_name)) AS student_name,
            s.status AS student_status,
            b.branch_name,
            se.academic_year_id,
            ay.year_name AS academic_year_name,
            se.class_id,
            c.class_name,
            se.section_id,
            sec.section_name,
            g.id AS guardian_id,
            g.guardian_name,
            g.relationship,
            g.mobile AS guardian_mobile,
            g.email AS guardian_email,
            g.user_id AS guardian_general_user_id,
            spl.id AS student_parent_login_id,
            spl.user_id AS parent_user_id,
            spl.status AS parent_login_status,
            u.username AS parent_username,
            u.status AS parent_user_status
         FROM students s
         LEFT JOIN branches b
           ON b.id = s.branch_id
          AND b.tenant_id = s.tenant_id"
        . $enrollmentJoin .
        "
         LEFT JOIN academic_years ay
           ON ay.id = se.academic_year_id
          AND ay.tenant_id = s.tenant_id
          AND ay.branch_id = s.branch_id
         LEFT JOIN classes c
           ON c.id = se.class_id
          AND c.tenant_id = s.tenant_id
          AND c.branch_id = s.branch_id
         LEFT JOIN sections sec
           ON sec.id = se.section_id
          AND sec.tenant_id = s.tenant_id
          AND sec.branch_id = s.branch_id
         LEFT JOIN student_guardians sg
           ON sg.student_id = s.id
          AND sg.guardian_id = (
                SELECT sg2.guardian_id
                FROM student_guardians sg2
                WHERE sg2.student_id = s.id
                ORDER BY
                    sg2.is_primary DESC,
                    sg2.guardian_id ASC
                LIMIT 1
           )
         LEFT JOIN guardians g
           ON g.id = sg.guardian_id
          AND g.tenant_id = s.tenant_id
         LEFT JOIN student_parent_logins spl
           ON spl.tenant_id = s.tenant_id
          AND spl.student_id = s.id
         LEFT JOIN users u
           ON u.id = spl.user_id
          AND u.tenant_id = spl.tenant_id
          AND u.deleted_at IS NULL
         WHERE " . implode(' AND ', $where) . "
         ORDER BY
            COALESCE(c.display_order, 9999),
            c.class_name,
            sec.section_name,
            s.first_name,
            s.last_name,
            s.id"
    );
    $statement->execute($params);

    $rows = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $hasAccount =
            (int)($row['student_parent_login_id'] ?? 0) > 0
            && (int)($row['parent_user_id'] ?? 0) > 0
            && trim((string)($row['parent_username'] ?? '')) !== '';

        $hasGuardian = (int)($row['guardian_id'] ?? 0) > 0;

        $row['has_parent_login'] = $hasAccount;
        $row['can_generate_parent_login'] = $hasGuardian && !$hasAccount;
        $row['parent_password_display'] = $hasAccount
            ? 'Previously Generated'
            : ($hasGuardian ? 'Not Generated' : 'No Guardian');

        $rows[] = $row;
    }

    return $rows;
}


function studentsParentLoginOneRow(
    PDO $pdo,
    array $scope,
    int $studentId,
    array $filters = []
): array {
    $rows = studentsParentLoginRows(
        $pdo,
        $scope,
        'all',
        [$studentId],
        $filters
    );

    if (!$rows) {
        throw new RuntimeException(
            'Student not found in the selected Academic Year / Class / Section or outside your branch access.',
            404
        );
    }

    return $rows[0];
}

function studentsParentUsernameBase(array $row): string
{
    $admission = strtolower(
        preg_replace(
            '/[^a-zA-Z0-9]+/',
            '',
            (string)($row['admission_number'] ?? '')
        ) ?? ''
    );

    if ($admission !== '') {
        return 'parent.' . $admission;
    }

    return 'parent.student' . (int)($row['student_id'] ?? 0);
}

function studentsParentUniqueUsername(
    PDO $pdo,
    int $tenantId,
    string $base
): string {
    $base = strtolower(trim($base));
    $base = preg_replace(
        '/[^a-z0-9._@-]+/',
        '',
        $base
    ) ?? '';

    if ($base === '' || mb_strlen($base) < 3) {
        $base = 'parent.account';
    }

    $base = mb_substr($base, 0, 84);

    $statement = $pdo->prepare(
        "SELECT COUNT(*)
         FROM users
         WHERE tenant_id = :tenant_id
           AND LOWER(username) = LOWER(:username)"
    );

    for ($attempt = 0; $attempt < 10000; $attempt++) {
        $suffix = $attempt === 0
            ? ''
            : '.' . ($attempt + 1);

        $candidate = mb_substr(
            $base,
            0,
            100 - mb_strlen($suffix)
        ) . $suffix;

        $statement->execute([
            'tenant_id' => $tenantId,
            'username' => $candidate,
        ]);

        if ((int)$statement->fetchColumn() === 0) {
            return $candidate;
        }
    }

    throw new RuntimeException(
        'Unable to generate a unique Parent username.',
        500
    );
}


function studentsParentTemporaryPassword(
    int $studentId,
    int $randomLength = 6
): string {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $max = strlen($alphabet) - 1;
    $random = '';

    $randomLength = max(6, min(12, $randomLength));

    for ($index = 0; $index < $randomLength; $index++) {
        $random .= $alphabet[random_int(0, $max)];
    }

    $studentPart = strtoupper(
        base_convert((string)max(1, $studentId), 10, 36)
    );

    return 'P' . $studentPart . '-' . $random;
}

function studentsParentSyncUserRole(
    PDO $pdo,
    array $scope,
    int $userId,
    int $roleId
): void {
    if (!studentsTableExists($pdo, 'user_roles')) {
        return;
    }

    $pdo->prepare(
        'DELETE FROM user_roles WHERE user_id = :user_id'
    )->execute([
        'user_id' => $userId,
    ]);

    $statement = $pdo->prepare(
        "INSERT INTO user_roles
         (user_id, role_id, is_primary, assigned_by)
         VALUES
         (:user_id, :role_id, 1, :assigned_by)"
    );
    $statement->execute([
        'user_id' => $userId,
        'role_id' => $roleId,
        'assigned_by' => $scope['user_id'] > 0
            ? $scope['user_id']
            : null,
    ]);
}

function studentsParentSyncAccess(
    PDO $pdo,
    int $tenantId,
    int $userId,
    int $studentId,
    int $branchId,
    int $academicYearId
): void {
    if (
        studentsTableExists($pdo, 'user_branch_access')
        && $branchId > 0
    ) {
        $insert = $pdo->prepare(
            "INSERT INTO user_branch_access
             (user_id, branch_id, can_access)
             VALUES
             (:user_id, :branch_id, 1)
             ON DUPLICATE KEY UPDATE can_access = 1"
        );
        $insert->execute([
            'user_id' => $userId,
            'branch_id' => $branchId,
        ]);
    }

    if (
        studentsTableExists($pdo, 'user_academic_year_access')
        && $academicYearId > 0
    ) {
        $insert = $pdo->prepare(
            "INSERT INTO user_academic_year_access
             (user_id, academic_year_id, can_access)
             VALUES
             (:user_id, :academic_year_id, 1)
             ON DUPLICATE KEY UPDATE can_access = 1"
        );
        $insert->execute([
            'user_id' => $userId,
            'academic_year_id' => $academicYearId,
        ]);
    }
}



function studentsParentCredentialSessionBucket(array $scope): string
{
    $adminUserId = (int)($scope['user_id'] ?? 0);
    $tenantId = (int)($scope['tenant_id'] ?? 0);

    return $tenantId . ':' . $adminUserId;
}

function studentsRememberParentTemporaryCredential(
    array $scope,
    int $studentId,
    int $parentUserId,
    string $username,
    string $temporaryPassword
): void {
    if ($studentId <= 0 || $parentUserId <= 0 || $temporaryPassword === '') {
        return;
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }

    if (!isset($_SESSION['student_parent_login_credentials'])
        || !is_array($_SESSION['student_parent_login_credentials'])) {
        $_SESSION['student_parent_login_credentials'] = [];
    }

    $bucket = studentsParentCredentialSessionBucket($scope);

    if (!isset($_SESSION['student_parent_login_credentials'][$bucket])
        || !is_array($_SESSION['student_parent_login_credentials'][$bucket])) {
        $_SESSION['student_parent_login_credentials'][$bucket] = [];
    }

    $_SESSION['student_parent_login_credentials'][$bucket][(string)$studentId] = [
        'parent_user_id' => $parentUserId,
        'username' => $username,
        'temporary_password' => $temporaryPassword,
        'generated_at' => date('Y-m-d H:i:s'),
    ];
}

function studentsParentTemporaryCredentialFromSession(
    array $scope,
    int $studentId,
    int $parentUserId,
    string $username
): ?array {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }

    $bucket = studentsParentCredentialSessionBucket($scope);

    $row = $_SESSION['student_parent_login_credentials'][$bucket][(string)$studentId]
        ?? null;

    if (!is_array($row)) {
        return null;
    }

    if ((int)($row['parent_user_id'] ?? 0) !== $parentUserId) {
        return null;
    }

    if (strcasecmp(
        trim((string)($row['username'] ?? '')),
        trim($username)
    ) !== 0) {
        return null;
    }

    $password = (string)($row['temporary_password'] ?? '');
    if ($password === '') {
        return null;
    }

    return $row;
}

function studentsParentLoginCredentialDetails(
    PDO $pdo,
    array $scope,
    int $studentId
): array {
    if ($studentId <= 0) {
        throw new InvalidArgumentException('Select a valid student.');
    }

    $row = studentsParentLoginOneRow(
        $pdo,
        $scope,
        $studentId
    );

    $hasLogin = (bool)($row['has_parent_login'] ?? false);
    $parentUserId = (int)($row['parent_user_id'] ?? 0);
    $username = trim((string)($row['parent_username'] ?? ''));

    if (!$hasLogin || $parentUserId <= 0 || $username === '') {
        return [
            'student_id' => $studentId,
            'has_parent_login' => false,
            'username' => '',
            'temporary_password' => '',
            'password_available' => false,
            'generated_at' => '',
            'status' => 'not_generated',
            'message' => 'Parent Login has not been generated for this student.',
        ];
    }

    $sessionCredential = studentsParentTemporaryCredentialFromSession(
        $scope,
        $studentId,
        $parentUserId,
        $username
    );

    /*
     * A parent may change the password after it was generated. Never display
     * a cached temporary password unless it still verifies against the current
     * users.password_hash value.
     */
    if ($sessionCredential !== null) {
        $passwordCheck = $pdo->prepare(
            "SELECT password_hash
             FROM users
             WHERE id = :user_id
               AND tenant_id = :tenant_id
               AND deleted_at IS NULL
             LIMIT 1"
        );
        $passwordCheck->execute([
            'user_id' => $parentUserId,
            'tenant_id' => $scope['tenant_id'],
        ]);
        $currentHash = (string)($passwordCheck->fetchColumn() ?: '');

        if (
            $currentHash === ''
            || !password_verify(
                (string)$sessionCredential['temporary_password'],
                $currentHash
            )
        ) {
            $sessionCredential = null;
        }
    }

    return [
        'student_id' => $studentId,
        'has_parent_login' => true,
        'username' => $username,
        'temporary_password' => (string)(
            $sessionCredential['temporary_password'] ?? ''
        ),
        'password_available' => $sessionCredential !== null,
        'generated_at' => (string)(
            $sessionCredential['generated_at'] ?? ''
        ),
        'status' => (string)($row['parent_login_status'] ?? 'active'),
        'message' => $sessionCredential !== null
            ? 'Parent Login credentials loaded.'
            : 'The existing password is securely hashed and cannot be displayed. Generate a new temporary password to view it.',
    ];
}

function studentsResetParentLoginPassword(
    PDO $pdo,
    array $scope,
    int $studentId
): array {
    if ($studentId <= 0) {
        throw new InvalidArgumentException('Select a valid student.');
    }

    $row = studentsParentLoginOneRow(
        $pdo,
        $scope,
        $studentId
    );

    if (!(bool)($row['has_parent_login'] ?? false)) {
        throw new RuntimeException(
            'Parent Login has not been generated for this student.',
            404
        );
    }

    $parentUserId = (int)($row['parent_user_id'] ?? 0);
    $username = trim((string)($row['parent_username'] ?? ''));

    if ($parentUserId <= 0 || $username === '') {
        throw new RuntimeException(
            'Parent Login account is incomplete. Generate the Parent Login again.',
            409
        );
    }

    $temporaryPassword = studentsParentTemporaryPassword(
        $studentId,
        7
    );

    $passwordHash = password_hash(
        $temporaryPassword,
        PASSWORD_BCRYPT,
        ['cost' => 12]
    );

    if ($passwordHash === false) {
        throw new RuntimeException(
            'Unable to secure the new Parent Login password.'
        );
    }

    $pdo->beginTransaction();

    try {
        $update = $pdo->prepare(
            "UPDATE users
             SET password_hash = :password_hash
             WHERE id = :user_id
               AND tenant_id = :tenant_id
               AND deleted_at IS NULL"
        );
        $update->execute([
            'password_hash' => $passwordHash,
            'user_id' => $parentUserId,
            'tenant_id' => $scope['tenant_id'],
        ]);

        if ($update->rowCount() < 1) {
            $check = $pdo->prepare(
                "SELECT id
                 FROM users
                 WHERE id = :user_id
                   AND tenant_id = :tenant_id
                   AND deleted_at IS NULL
                 LIMIT 1"
            );
            $check->execute([
                'user_id' => $parentUserId,
                'tenant_id' => $scope['tenant_id'],
            ]);

            if (!(int)$check->fetchColumn()) {
                throw new RuntimeException(
                    'Parent Login user account was not found.',
                    404
                );
            }
        }

        studentsLog(
            $pdo,
            $scope,
            'parent_login_password_reset',
            $studentId,
            'A new temporary Parent Login password was generated for '
                . $username . '.',
            null,
            [
                'parent_user_id' => $parentUserId,
                'username' => $username,
            ]
        );

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    studentsRememberParentTemporaryCredential(
        $scope,
        $studentId,
        $parentUserId,
        $username,
        $temporaryPassword
    );

    return [
        'student_id' => $studentId,
        'has_parent_login' => true,
        'username' => $username,
        'temporary_password' => $temporaryPassword,
        'password_available' => true,
        'generated_at' => date('Y-m-d H:i:s'),
        'status' => 'active',
        'message' => 'New temporary Parent Login password generated successfully.',
    ];
}


function studentsGenerateParentLoginForStudent(
    PDO $pdo,
    array $scope,
    int $studentId,
    array $filters = []
): array {
    if ($studentId <= 0) {
        throw new InvalidArgumentException('Select a valid student.');
    }

    $parentRole = studentsParentRole($pdo, $scope);

    $pdo->beginTransaction();

    try {
        $lockWhere = [
            'id = :student_id',
            'tenant_id = :tenant_id',
            'deleted_at IS NULL',
        ];
        $lockParams = [
            'student_id' => $studentId,
            'tenant_id' => $scope['tenant_id'],
        ];

        if ($scope['branch_id'] > 0) {
            $lockWhere[] = 'branch_id = :scope_branch_id';
            $lockParams['scope_branch_id'] = $scope['branch_id'];
        }

        $lock = $pdo->prepare(
            "SELECT id
             FROM students
             WHERE " . implode(' AND ', $lockWhere) . "
             LIMIT 1
             FOR UPDATE"
        );
        $lock->execute($lockParams);

        if (!(int)$lock->fetchColumn()) {
            throw new RuntimeException(
                'Student not found or outside your branch access.',
                404
            );
        }

        $row = studentsParentLoginOneRow(
            $pdo,
            $scope,
            $studentId,
            $filters
        );

        $existing = $pdo->prepare(
            "SELECT
                spl.id,
                spl.user_id,
                u.username,
                u.status
             FROM student_parent_logins spl
             LEFT JOIN users u
               ON u.id = spl.user_id
              AND u.tenant_id = spl.tenant_id
              AND u.deleted_at IS NULL
             WHERE spl.tenant_id = :tenant_id
               AND spl.student_id = :student_id
             LIMIT 1"
        );
        $existing->execute([
            'tenant_id' => $scope['tenant_id'],
            'student_id' => $studentId,
        ]);
        $existingRow = $existing->fetch(PDO::FETCH_ASSOC);

        if (
            $existingRow
            && (int)($existingRow['user_id'] ?? 0) > 0
            && trim((string)($existingRow['username'] ?? '')) !== ''
        ) {
            $pdo->commit();

            return [
                'status' => 'existing',
                'student_id' => $studentId,
                'username' => (string)$existingRow['username'],
                'message' => 'Parent Login already exists. Existing credentials were not changed.',
            ];
        }

        if ($existingRow) {
            $pdo->prepare(
                "DELETE FROM student_parent_logins
                 WHERE id = :id
                   AND tenant_id = :tenant_id"
            )->execute([
                'id' => (int)$existingRow['id'],
                'tenant_id' => $scope['tenant_id'],
            ]);
        }

        $guardianId = (int)($row['guardian_id'] ?? 0);
        if ($guardianId <= 0) {
            throw new RuntimeException(
                'No Parent / Guardian is linked to this student.'
            );
        }

        $username = studentsParentUniqueUsername(
            $pdo,
            $scope['tenant_id'],
            studentsParentUsernameBase($row)
        );

        $temporaryPassword = studentsParentTemporaryPassword(
            $studentId,
            7
        );

        $passwordHash = password_hash(
            $temporaryPassword,
            PASSWORD_BCRYPT,
            ['cost' => 12]
        );

        if ($passwordHash === false) {
            throw new RuntimeException(
                'Unable to secure the temporary password.'
            );
        }

        $guardianName = preg_replace(
            '/\s+/u',
            ' ',
            trim((string)($row['guardian_name'] ?? ''))
        ) ?? '';

        if ($guardianName === '') {
            $guardianName = 'Parent';
        }

        $mobile = trim(
            (string)($row['guardian_mobile'] ?? '')
        );

        $insert = $pdo->prepare(
            "INSERT INTO users
             (
                tenant_id,
                default_branch_id,
                role_id,
                employee_id,
                name,
                email,
                mobile,
                username,
                password_hash,
                profile_photo,
                status
             )
             VALUES
             (
                :tenant_id,
                :default_branch_id,
                :role_id,
                NULL,
                :name,
                NULL,
                :mobile,
                :username,
                :password_hash,
                NULL,
                'active'
             )"
        );
        $insert->execute([
            'tenant_id' => $scope['tenant_id'],
            'default_branch_id' =>
                (int)($row['branch_id'] ?? 0) ?: null,
            'role_id' => (int)$parentRole['id'],
            'name' => $guardianName,
            'mobile' => $mobile !== '' ? $mobile : null,
            'username' => $username,
            'password_hash' => $passwordHash,
        ]);
        $userId = (int)$pdo->lastInsertId();

        $mapping = $pdo->prepare(
            "INSERT INTO student_parent_logins
             (
                tenant_id,
                student_id,
                guardian_id,
                user_id,
                status,
                created_by
             )
             VALUES
             (
                :tenant_id,
                :student_id,
                :guardian_id,
                :user_id,
                'active',
                :created_by
             )"
        );
        $mapping->execute([
            'tenant_id' => $scope['tenant_id'],
            'student_id' => $studentId,
            'guardian_id' => $guardianId,
            'user_id' => $userId,
            'created_by' => $scope['user_id'] > 0
                ? $scope['user_id']
                : null,
        ]);

        if (studentsColumnExists(
            $pdo,
            'guardians',
            'user_id'
        )) {
            $pdo->prepare(
                "UPDATE guardians
                 SET user_id = :user_id
                 WHERE id = :guardian_id
                   AND tenant_id = :tenant_id
                   AND user_id IS NULL"
            )->execute([
                'user_id' => $userId,
                'guardian_id' => $guardianId,
                'tenant_id' => $scope['tenant_id'],
            ]);
        }

        studentsParentSyncUserRole(
            $pdo,
            $scope,
            $userId,
            (int)$parentRole['id']
        );

        studentsParentSyncAccess(
            $pdo,
            $scope['tenant_id'],
            $userId,
            $studentId,
            (int)($row['branch_id'] ?? 0),
            (int)($row['academic_year_id'] ?? 0)
        );

        studentsLog(
            $pdo,
            $scope,
            'parent_login_create',
            $studentId,
            'Parent Login generated for '
                . $guardianName
                . ' (' . $username . ').',
            null,
            [
                'guardian_id' => $guardianId,
                'parent_user_id' => $userId,
                'username' => $username,
            ]
        );

        $pdo->commit();

        studentsRememberParentTemporaryCredential(
            $scope,
            $studentId,
            $userId,
            $username,
            $temporaryPassword
        );

        return [
            'status' => 'created',
            'credential' => [
                'student_id' => $studentId,
                'student_name' => (string)($row['student_name'] ?? ''),
                'admission_number' => (string)($row['admission_number'] ?? ''),
                'academic_year' => (string)($row['academic_year_name'] ?? ''),
                'class_name' => (string)($row['class_name'] ?? ''),
                'section_name' => (string)($row['section_name'] ?? ''),
                'guardian_name' => $guardianName,
                'relationship' => (string)($row['relationship'] ?? ''),
                'mobile' => (string)($row['guardian_mobile'] ?? ''),
                'email' => (string)($row['guardian_email'] ?? ''),
                'username' => $username,
                'temporary_password' => $temporaryPassword,
            ],
        ];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}


function studentsGenerateParentLoginsSelected(
    PDO $pdo,
    array $scope,
    array $studentIds,
    array $filters = []
): array {
    $studentIds = array_values(array_unique(array_filter(
        array_map('intval', $studentIds),
        static fn(int $id): bool => $id > 0
    )));

    if (!$studentIds) {
        throw new InvalidArgumentException(
            'Select at least one student.'
        );
    }

    if (count($studentIds) > 1000) {
        throw new InvalidArgumentException(
            'Generate Parent Login for a maximum of 1000 students at a time.'
        );
    }

    $filter = studentsParentLoginNormalizeFilters(
        $pdo,
        $scope,
        $filters,
        true
    );

    $eligibleRows = studentsParentLoginRows(
        $pdo,
        $scope,
        'active',
        $studentIds,
        $filter
    );
    $eligibleIds = array_map(
        static fn(array $row): int => (int)$row['student_id'],
        $eligibleRows
    );

    $missingIds = array_values(array_diff($studentIds, $eligibleIds));
    if ($missingIds) {
        throw new InvalidArgumentException(
            'One or more selected students no longer match the selected Academic Year, Class and Section. Reload the student list and try again.'
        );
    }

    $credentials = [];
    $skipped = [];
    $failed = [];

    foreach ($studentIds as $studentId) {
        try {
            $result = studentsGenerateParentLoginForStudent(
                $pdo,
                $scope,
                $studentId,
                $filter
            );

            if (($result['status'] ?? '') === 'created') {
                $credentials[] = $result['credential'];
            } else {
                $row = studentsParentLoginOneRow(
                    $pdo,
                    $scope,
                    $studentId,
                    $filter
                );

                $skipped[] = [
                    'student_id' => $studentId,
                    'student_name' => (string)($row['student_name'] ?? ''),
                    'admission_number' => (string)($row['admission_number'] ?? ''),
                    'guardian_name' => (string)($row['guardian_name'] ?? ''),
                    'username' => (string)(
                        $result['username']
                        ?? $row['parent_username']
                        ?? ''
                    ),
                    'reason' => (string)(
                        $result['message']
                        ?? 'Parent Login already exists.'
                    ),
                ];
            }
        } catch (Throwable $exception) {
            try {
                $row = studentsParentLoginOneRow(
                    $pdo,
                    $scope,
                    $studentId,
                    $filter
                );
            } catch (Throwable $ignored) {
                $row = [
                    'student_name' => 'Student #' . $studentId,
                    'admission_number' => '',
                    'guardian_name' => '',
                ];
            }

            $failed[] = [
                'student_id' => $studentId,
                'student_name' => (string)($row['student_name'] ?? ''),
                'admission_number' => (string)($row['admission_number'] ?? ''),
                'guardian_name' => (string)($row['guardian_name'] ?? ''),
                'reason' => $exception->getMessage(),
            ];
        }
    }

    return [
        'created' => count($credentials),
        'skipped' => count($skipped),
        'failed' => count($failed),
        'credentials' => $credentials,
        'skipped_rows' => $skipped,
        'failed_rows' => $failed,
        'filters' => $filter,
    ];
}

function studentsMeta(PDO $pdo, array $scope): array
{
    $years = [];
    $classes = [];
    $sections = [];
    $branches = [];

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
         ORDER BY is_current DESC, start_date DESC, id DESC"
    );
    $statement->execute([
        'tenant_id' => $scope['tenant_id'],
        'branch_id' => $scope['branch_id'],
    ]);
    $years = $statement->fetchAll(PDO::FETCH_ASSOC);

    if (studentsTableExists($pdo, 'class_management_classes')) {
        $managedWhere = [
            'cmc.tenant_id = :tenant_id',
            'cmc.branch_id = :branch_id',
            "cmc.status <> 'archived'",
        ];
        $managedParams = [
            'tenant_id' => $scope['tenant_id'],
            'branch_id' => $scope['branch_id'],
        ];

        $statement = $pdo->prepare(
            "SELECT
                c.id,
                cmc.academic_year_id,
                c.class_name,
                MIN(cmc.display_order) AS display_order,
                CASE WHEN SUM(cmc.status = 'active') > 0 THEN 'active' ELSE 'inactive' END AS status,
                MIN(cmc.id) AS class_management_id
             FROM class_management_classes cmc
             INNER JOIN classes c
                ON c.tenant_id = cmc.tenant_id
               AND c.branch_id = cmc.branch_id
               AND c.academic_year_id = cmc.academic_year_id
               AND LOWER(TRIM(c.class_name)) = LOWER(TRIM(cmc.class_name))
             INNER JOIN academic_years ay
                ON ay.id = cmc.academic_year_id
               AND ay.tenant_id = cmc.tenant_id
               AND ay.branch_id = cmc.branch_id
             WHERE " . implode(' AND ', $managedWhere) . "
             GROUP BY c.id, cmc.academic_year_id, c.class_name
             ORDER BY cmc.academic_year_id DESC, MIN(cmc.display_order), c.class_name, c.id"
        );
        $statement->execute($managedParams);
        $classes = $statement->fetchAll(PDO::FETCH_ASSOC);

        $statement = $pdo->prepare(
            "SELECT
                s.id,
                cmc.academic_year_id,
                c.id AS class_id,
                c.class_name AS class_name_snapshot,
                cmc.section_name,
                cmc.maximum_strength AS maximum_student_capacity,
                cmc.status,
                cmc.class_code AS section_code,
                cmc.medium,
                cmc.shift_name,
                COALESCE(cmc.classroom_name, '') AS room_number,
                cmc.display_order,
                COALESCE(cmc.class_teacher_user_id, 0) AS class_teacher_user_id,
                COALESCE(cmc.class_teacher_name, '') AS class_teacher_name,
                cmc.id AS class_management_id
             FROM class_management_classes cmc
             INNER JOIN classes c
                ON c.tenant_id = cmc.tenant_id
               AND c.branch_id = cmc.branch_id
               AND c.academic_year_id = cmc.academic_year_id
               AND LOWER(TRIM(c.class_name)) = LOWER(TRIM(cmc.class_name))
             INNER JOIN sections s
                ON s.tenant_id = cmc.tenant_id
               AND s.branch_id = cmc.branch_id
               AND s.class_id = c.id
               AND LOWER(TRIM(s.section_name)) = LOWER(TRIM(cmc.section_name))
             INNER JOIN academic_years ay
                ON ay.id = cmc.academic_year_id
               AND ay.tenant_id = cmc.tenant_id
               AND ay.branch_id = cmc.branch_id
             WHERE " . implode(' AND ', $managedWhere) . "
             ORDER BY cmc.academic_year_id DESC, cmc.display_order, c.class_name, cmc.section_name, cmc.id"
        );
        $statement->execute($managedParams);
        $sections = $statement->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $statement = $pdo->prepare(
            "SELECT c.id,c.academic_year_id,c.class_name,c.display_order,c.status
             FROM classes c
             INNER JOIN academic_years ay
                ON ay.id=c.academic_year_id
               AND ay.tenant_id=c.tenant_id
               AND ay.branch_id=c.branch_id
             WHERE c.tenant_id=:tenant_id
               AND c.branch_id=:branch_id
             ORDER BY c.academic_year_id DESC,c.display_order,c.class_name,c.id"
        );
        $statement->execute([
            'tenant_id' => $scope['tenant_id'],
            'branch_id' => $scope['branch_id'],
        ]);
        $classes = $statement->fetchAll(PDO::FETCH_ASSOC);

        $sectionSql = "
            SELECT
                s.id,
                c.academic_year_id,
                c.id AS class_id,
                c.class_name AS class_name_snapshot,
                s.section_name,
                s.capacity AS maximum_student_capacity,
                s.status";

        if (studentsTableExists($pdo, 'school_sections')) {
            $sectionSql .= ",
                COALESCE(ss.section_code, '') AS section_code,
                COALESCE(ss.medium, 'English') AS medium,
                COALESCE(ss.shift_name, 'General') AS shift_name,
                COALESCE(ss.room_number, '') AS room_number,
                COALESCE(ss.display_order, c.display_order, 0) AS display_order,
                COALESCE(ss.class_teacher_user_id, 0) AS class_teacher_user_id,
                COALESCE(ss.class_teacher_name, '') AS class_teacher_name";
        } else {
            $sectionSql .= ",
                '' AS section_code,'English' AS medium,'General' AS shift_name,'' AS room_number,
                COALESCE(c.display_order, 0) AS display_order,0 AS class_teacher_user_id,'' AS class_teacher_name";
        }

        $sectionSql .= "
             FROM sections s
             INNER JOIN classes c
                ON c.id=s.class_id
               AND c.tenant_id=s.tenant_id
               AND c.branch_id=s.branch_id
             INNER JOIN academic_years ay
                ON ay.id=c.academic_year_id
               AND ay.tenant_id=c.tenant_id
               AND ay.branch_id=c.branch_id";

        if (studentsTableExists($pdo, 'school_sections')) {
            $sectionSql .= "
             LEFT JOIN school_sections ss
                ON ss.tenant_id=s.tenant_id
               AND ss.branch_id=s.branch_id
               AND ss.academic_year_id=c.academic_year_id
               AND ss.class_id=c.id
               AND LOWER(TRIM(ss.section_name))=LOWER(TRIM(s.section_name))
               AND ss.status<>'archived'";
        }

        $sectionSql .= "
             WHERE s.tenant_id=:tenant_id
               AND s.branch_id=:branch_id
             ORDER BY c.academic_year_id DESC,c.display_order,c.class_name,display_order,s.section_name,s.id";

        $statement = $pdo->prepare($sectionSql);
        $statement->execute([
            'tenant_id' => $scope['tenant_id'],
            'branch_id' => $scope['branch_id'],
        ]);
        $sections = $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    if (studentsTableExists($pdo, 'branches')) {
        $statement = $pdo->prepare(
            "SELECT id, branch_name, is_main, status
             FROM branches
             WHERE tenant_id=:tenant_id
               AND id=:branch_id
               AND status='active'
             LIMIT 1"
        );
        $statement->execute([
            'tenant_id' => $scope['tenant_id'],
            'branch_id' => $scope['branch_id'],
        ]);
        $branch = $statement->fetch(PDO::FETCH_ASSOC);
        if ($branch) $branches[] = $branch;
    }

    return [
        'academic_years' => $years,
        'classes' => $classes,
        'sections' => $sections,
        'branches' => $branches,
        'fee_structures' => studentsFeeStructures($pdo, $scope),
        'transport_routes' => studentsTransportRoutes($pdo, $scope),
        'transport_stops' => studentsTransportStops($pdo, $scope),
        'statuses' => ['active', 'inactive', 'tc', 'alumni'],
        'current_academic_year_id' => studentsCurrentAcademicYearId(
            $pdo,
            $scope['tenant_id'],
            $scope['branch_id']
        ),
        'current_branch_id' => $scope['branch_id'],
        'current_branch_name' => (string)($scope['branch_name'] ?? ($branches[0]['branch_name'] ?? '')),
        'general_settings' => school_settings_get($pdo, $scope['tenant_id']),
        'next_admission_number' => studentsPeekUniqueAdmissionNumber($pdo, $scope['tenant_id']),
    ];
}

function studentsResolveClassFilter(
    PDO $pdo,
    int $tenantId,
    int $branchId,
    int $inputClassId
): ?array {
    if ($inputClassId <= 0) return null;

    $statement = $pdo->prepare(
        "SELECT id AS class_id, academic_year_id, class_name
         FROM classes
         WHERE id=:id AND tenant_id=:tenant_id AND branch_id=:branch_id
         LIMIT 1"
    );
    $statement->execute([
        'id'=>$inputClassId,'tenant_id'=>$tenantId,'branch_id'=>$branchId,
    ]);
    $row=$statement->fetch(PDO::FETCH_ASSOC);
    if ($row) return $row;

    if (studentsTableExists($pdo,'class_management_classes')) {
        $statement=$pdo->prepare(
            "SELECT academic_year_id,class_name
             FROM class_management_classes
             WHERE id=:id AND tenant_id=:tenant_id AND branch_id=:branch_id
             LIMIT 1"
        );
        $statement->execute([
            'id'=>$inputClassId,'tenant_id'=>$tenantId,'branch_id'=>$branchId,
        ]);
        $managed=$statement->fetch(PDO::FETCH_ASSOC);
        if ($managed) {
            $statement=$pdo->prepare(
                "SELECT id AS class_id,academic_year_id,class_name
                 FROM classes
                 WHERE tenant_id=:tenant_id AND branch_id=:branch_id
                   AND academic_year_id=:academic_year_id
                   AND LOWER(TRIM(class_name))=LOWER(TRIM(:class_name))
                 LIMIT 1"
            );
            $statement->execute([
                'tenant_id'=>$tenantId,'branch_id'=>$branchId,
                'academic_year_id'=>(int)$managed['academic_year_id'],
                'class_name'=>(string)$managed['class_name'],
            ]);
            $row=$statement->fetch(PDO::FETCH_ASSOC);
            if ($row) return $row;
        }
    }
    return null;
}

function studentsResolveSectionFilter(
    PDO $pdo,
    int $tenantId,
    int $branchId,
    int $inputSectionId
): ?array {
    if ($inputSectionId <= 0) return null;

    $statement=$pdo->prepare(
        "SELECT s.id AS section_id,c.academic_year_id,c.id AS class_id,c.class_name,s.section_name
         FROM sections s
         INNER JOIN classes c ON c.id=s.class_id AND c.tenant_id=s.tenant_id AND c.branch_id=s.branch_id
         WHERE s.id=:id AND s.tenant_id=:tenant_id AND s.branch_id=:branch_id
         LIMIT 1"
    );
    $statement->execute([
        'id'=>$inputSectionId,'tenant_id'=>$tenantId,'branch_id'=>$branchId,
    ]);
    $row=$statement->fetch(PDO::FETCH_ASSOC);
    if ($row) return $row;

    if (studentsTableExists($pdo,'school_sections')) {
        $statement=$pdo->prepare(
            "SELECT academic_year_id,class_name_snapshot,section_name
             FROM school_sections
             WHERE id=:id AND tenant_id=:tenant_id AND branch_id=:branch_id
             LIMIT 1"
        );
        $statement->execute([
            'id'=>$inputSectionId,'tenant_id'=>$tenantId,'branch_id'=>$branchId,
        ]);
        $legacy=$statement->fetch(PDO::FETCH_ASSOC);
        if ($legacy) {
            $statement=$pdo->prepare(
                "SELECT s.id AS section_id,c.academic_year_id,c.id AS class_id,c.class_name,s.section_name
                 FROM sections s
                 INNER JOIN classes c ON c.id=s.class_id AND c.tenant_id=s.tenant_id AND c.branch_id=s.branch_id
                 WHERE s.tenant_id=:tenant_id AND s.branch_id=:branch_id
                   AND c.academic_year_id=:academic_year_id
                   AND LOWER(TRIM(c.class_name))=LOWER(TRIM(:class_name))
                   AND LOWER(TRIM(s.section_name))=LOWER(TRIM(:section_name))
                 LIMIT 1"
            );
            $statement->execute([
                'tenant_id'=>$tenantId,'branch_id'=>$branchId,
                'academic_year_id'=>(int)$legacy['academic_year_id'],
                'class_name'=>(string)$legacy['class_name_snapshot'],
                'section_name'=>(string)$legacy['section_name'],
            ]);
            $row=$statement->fetch(PDO::FETCH_ASSOC);
            if ($row) return $row;
        }
    }
    return null;
}

function studentsList(PDO $pdo, array $scope, array $filters): array
{
    $where = [
        's.tenant_id = :tenant_id',
        's.branch_id = :scope_branch_id',
    ];
    $params = [
        'tenant_id' => $scope['tenant_id'],
        'scope_branch_id' => $scope['branch_id'],
    ];

    $academicYearId = (int)($filters['academic_year_id'] ?? 0);

    if (studentsColumnExists($pdo, 'students', 'deleted_at')) {
        $where[] = 's.deleted_at IS NULL';
    }

    $search = trim((string)($filters['search'] ?? ''));
    if ($search !== '') {
        $where[] = "(
            s.admission_no LIKE :search
            OR s.first_name LIKE :search
            OR s.last_name LIKE :search
            OR CONCAT_WS(' ', s.first_name, s.last_name) LIKE :search
            OR g.guardian_name LIKE :search
            OR g.mobile LIKE :search
        )";
        $params['search'] = '%' . $search . '%';
    }

    $gender = strtolower(trim((string)($filters['gender'] ?? '')));
    if ($gender !== '' && $gender !== 'all') {
        $where[] = 's.gender = :gender';
        $params['gender'] = $gender;
    }

    $status = studentsNormalizeStatus((string)($filters['status'] ?? ''));
    if (trim((string)($filters['status'] ?? '')) !== '' && (string)$filters['status'] !== 'all') {
        $where[] = 's.status = :status';
        $params['status'] = $status;
    }

    $classFilter = studentsResolveClassFilter(
        $pdo,
        $scope['tenant_id'],
        $scope['branch_id'],
        (int)($filters['class_id'] ?? 0)
    );
    if ($classFilter) {
        $where[] = 'se.class_id = :filter_class_id';
        $params['filter_class_id'] = (int)$classFilter['class_id'];
    }

    $sectionFilter = studentsResolveSectionFilter(
        $pdo,
        $scope['tenant_id'],
        $scope['branch_id'],
        (int)($filters['section_id'] ?? 0)
    );
    if ($sectionFilter) {
        $where[] = 'se.section_id = :filter_section_id';
        $params['filter_section_id'] = (int)$sectionFilter['section_id'];
    }

    if ($academicYearId > 0) {
        $params['filter_academic_year_id'] = $academicYearId;
        $where[] = 'se.id IS NOT NULL';
        $enrollmentJoin = "
        LEFT JOIN student_enrollments se
          ON se.student_id = s.id
         AND se.tenant_id = s.tenant_id
         AND se.branch_id = s.branch_id
         AND se.academic_year_id = :filter_academic_year_id";
    } else {
        $enrollmentJoin = "
        LEFT JOIN student_enrollments se
          ON se.id = (
                SELECT se2.id
                FROM student_enrollments se2
                LEFT JOIN academic_years ay2
                  ON ay2.id = se2.academic_year_id
                 AND ay2.tenant_id = se2.tenant_id
                 AND ay2.branch_id = se2.branch_id
                WHERE se2.student_id = s.id
                  AND se2.tenant_id = s.tenant_id
                  AND se2.branch_id = s.branch_id
                ORDER BY
                    CASE se2.enrollment_status
                        WHEN 'active' THEN 0
                        WHEN 'promoted' THEN 1
                        WHEN 'transferred' THEN 2
                        WHEN 'completed' THEN 3
                        ELSE 4
                    END,
                    COALESCE(ay2.start_date, '1000-01-01') DESC,
                    se2.academic_year_id DESC,
                    se2.id DESC
                LIMIT 1
          )";
    }

    $sql = "
        SELECT
            s.id,
            s.tenant_id,
            s.branch_id,
            s.admission_no AS admission_number,
            s.emis_no,
            TRIM(CONCAT_WS(' ', s.first_name, s.last_name)) AS student_name,
            s.date_of_birth,
            CONCAT(UCASE(LEFT(s.gender, 1)), SUBSTRING(s.gender, 2)) AS gender,
            s.blood_group,
            s.admission_date,
            s.status,
            se.academic_year_id,
            ay.year_name AS academic_year_name,
            se.class_id AS class_id,
            c.class_name,
            se.section_id AS section_id,
            sec.section_name,
            se.roll_no AS roll_number,
            se.enrollment_status,
            g.guardian_name AS parent_name,
            g.relationship,
            COALESCE(g.mobile, s.mobile, '') AS mobile,
            COALESCE(g.email, s.email, '') AS email,
            COALESCE(g.address, s.address, '') AS address,
            COALESCE(ex.notes, '') AS notes,
            b.branch_name,
            COALESCE(
                (
                    SELECT sfa0.id
                    FROM student_fee_assignments sfa0
                    WHERE sfa0.tenant_id = s.tenant_id
                      AND sfa0.branch_id = s.branch_id
                      AND sfa0.student_id = s.id
                      AND sfa0.academic_year_id = se.academic_year_id
                      AND sfa0.assignment_status = 'active'
                    ORDER BY sfa0.id DESC
                    LIMIT 1
                ),
                0
            ) AS fee_assignment_id,
            (
                SELECT sfa.fee_structure_id
                FROM student_fee_assignments sfa
                WHERE sfa.tenant_id = s.tenant_id
                      AND sfa.branch_id = s.branch_id
                  AND sfa.student_id = s.id
                  AND sfa.academic_year_id = se.academic_year_id
                  AND sfa.assignment_status = 'active'
                ORDER BY sfa.id DESC
                LIMIT 1
            ) AS fee_structure_id,
            (
                SELECT fs2.structure_name
                FROM student_fee_assignments sfa2
                INNER JOIN fee_structures fs2
                    ON fs2.id = sfa2.fee_structure_id
                   AND fs2.tenant_id = sfa2.tenant_id
                   AND fs2.branch_id = sfa2.branch_id
                WHERE sfa2.tenant_id = s.tenant_id
                      AND sfa2.branch_id = s.branch_id
                  AND sfa2.student_id = s.id
                  AND sfa2.academic_year_id = se.academic_year_id
                  AND sfa2.assignment_status = 'active'
                ORDER BY sfa2.id DESC
                LIMIT 1
            ) AS fee_structure_name,
            COALESCE(
                (
                    SELECT sfa3.is_new_admission
                    FROM student_fee_assignments sfa3
                    WHERE sfa3.tenant_id = s.tenant_id
                      AND sfa3.branch_id = s.branch_id
                      AND sfa3.student_id = s.id
                      AND sfa3.academic_year_id = se.academic_year_id
                      AND sfa3.assignment_status = 'active'
                    ORDER BY sfa3.id DESC
                    LIMIT 1
                ),
                0
            ) AS fee_is_new_admission,
            (
                SELECT sfa4.assignment_date
                FROM student_fee_assignments sfa4
                WHERE sfa4.tenant_id = s.tenant_id
                      AND sfa4.branch_id = s.branch_id
                  AND sfa4.student_id = s.id
                  AND sfa4.academic_year_id = se.academic_year_id
                  AND sfa4.assignment_status = 'active'
                ORDER BY sfa4.id DESC
                LIMIT 1
            ) AS fee_assignment_date,
            COALESCE(
                (
                    SELECT sfa5.gross_amount
                    FROM student_fee_assignments sfa5
                    WHERE sfa5.tenant_id = s.tenant_id
                      AND sfa5.branch_id = s.branch_id
                      AND sfa5.student_id = s.id
                      AND sfa5.academic_year_id = se.academic_year_id
                      AND sfa5.assignment_status = 'active'
                    ORDER BY sfa5.id DESC
                    LIMIT 1
                ),
                0
            ) AS total_fee_amount,
            COALESCE(
                (
                    SELECT sta.transport_required
                    FROM student_transport_assignments sta
                    WHERE sta.tenant_id = s.tenant_id
                      AND sta.branch_id = s.branch_id
                      AND sta.student_id = s.id
                      AND sta.academic_year_id = se.academic_year_id
                      AND sta.status = 'active'
                    ORDER BY sta.id DESC
                    LIMIT 1
                ),
                0
            ) AS transport_required,
            (
                SELECT sta2.route_id
                FROM student_transport_assignments sta2
                WHERE sta2.tenant_id = s.tenant_id
                      AND sta2.branch_id = s.branch_id
                  AND sta2.student_id = s.id
                  AND sta2.academic_year_id = se.academic_year_id
                  AND sta2.status = 'active'
                ORDER BY sta2.id DESC
                LIMIT 1
            ) AS transport_route_id,
            (
                SELECT sta3.stop_id
                FROM student_transport_assignments sta3
                WHERE sta3.tenant_id = s.tenant_id
                      AND sta3.branch_id = s.branch_id
                  AND sta3.student_id = s.id
                  AND sta3.academic_year_id = se.academic_year_id
                  AND sta3.status = 'active'
                ORDER BY sta3.id DESC
                LIMIT 1
            ) AS transport_stop_id,
            (
                SELECT sta4.vehicle_id
                FROM student_transport_assignments sta4
                WHERE sta4.tenant_id = s.tenant_id
                      AND sta4.branch_id = s.branch_id
                  AND sta4.student_id = s.id
                  AND sta4.academic_year_id = se.academic_year_id
                  AND sta4.status = 'active'
                ORDER BY sta4.id DESC
                LIMIT 1
            ) AS transport_vehicle_id,
            (
                SELECT sta5.route_name
                FROM student_transport_assignments sta5
                WHERE sta5.tenant_id = s.tenant_id
                      AND sta5.branch_id = s.branch_id
                  AND sta5.student_id = s.id
                  AND sta5.academic_year_id = se.academic_year_id
                  AND sta5.status = 'active'
                ORDER BY sta5.id DESC
                LIMIT 1
            ) AS transport_route_name,
            (
                SELECT sta6.boarding_stop_name
                FROM student_transport_assignments sta6
                WHERE sta6.tenant_id = s.tenant_id
                      AND sta6.branch_id = s.branch_id
                  AND sta6.student_id = s.id
                  AND sta6.academic_year_id = se.academic_year_id
                  AND sta6.status = 'active'
                ORDER BY sta6.id DESC
                LIMIT 1
            ) AS transport_stop_name,
            (
                SELECT sta7.vehicle_name
                FROM student_transport_assignments sta7
                WHERE sta7.tenant_id = s.tenant_id
                      AND sta7.branch_id = s.branch_id
                  AND sta7.student_id = s.id
                  AND sta7.academic_year_id = se.academic_year_id
                  AND sta7.status = 'active'
                ORDER BY sta7.id DESC
                LIMIT 1
            ) AS transport_vehicle_name,
            (
                SELECT sta8.driver_name
                FROM student_transport_assignments sta8
                WHERE sta8.tenant_id = s.tenant_id
                      AND sta8.branch_id = s.branch_id
                  AND sta8.student_id = s.id
                  AND sta8.academic_year_id = se.academic_year_id
                  AND sta8.status = 'active'
                ORDER BY sta8.id DESC
                LIMIT 1
            ) AS transport_driver_name,
            COALESCE(
                (
                    SELECT COALESCE(
                        sta9.transport_fee_amount,
                        sta9.bus_fee_amount,
                        0
                    )
                    FROM student_transport_assignments sta9
                    WHERE sta9.tenant_id = s.tenant_id
                      AND sta9.branch_id = s.branch_id
                      AND sta9.student_id = s.id
                      AND sta9.academic_year_id = se.academic_year_id
                      AND sta9.status = 'active'
                    ORDER BY sta9.id DESC
                    LIMIT 1
                ),
                0
            ) AS transport_fee_amount
        FROM students s
        {$enrollmentJoin}
        LEFT JOIN academic_years ay
          ON ay.id = se.academic_year_id
         AND ay.tenant_id = s.tenant_id
         AND ay.branch_id = s.branch_id
        LEFT JOIN classes c
          ON c.id = se.class_id
         AND c.tenant_id = s.tenant_id
         AND c.branch_id = s.branch_id
        LEFT JOIN sections sec
          ON sec.id = se.section_id
         AND sec.tenant_id = s.tenant_id
         AND sec.branch_id = s.branch_id
        LEFT JOIN student_guardians sg
          ON sg.student_id = s.id
         AND sg.guardian_id = (
                SELECT sg2.guardian_id
                FROM student_guardians sg2
                WHERE sg2.student_id = s.id
                ORDER BY sg2.is_primary DESC, sg2.guardian_id ASC
                LIMIT 1
          )
        LEFT JOIN guardians g
          ON g.id = sg.guardian_id
         AND g.tenant_id = s.tenant_id
        LEFT JOIN student_profile_extras ex
          ON ex.student_id = s.id
         AND ex.tenant_id = s.tenant_id
        LEFT JOIN branches b
          ON b.id = s.branch_id
         AND b.tenant_id = s.tenant_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY s.id DESC";

    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    $records = $statement->fetchAll(PDO::FETCH_ASSOC);

    $feeBreakdownStatement = null;
    if (studentsTableExists($pdo, 'student_fee_items')) {
        $feeBreakdownStatement = $pdo->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN item_type='tuition' THEN original_amount ELSE 0 END),0) tuition_amount,
                COALESCE(SUM(CASE WHEN item_type='admission' THEN original_amount ELSE 0 END),0) admission_amount,
                COALESCE(SUM(CASE WHEN item_type='transport' THEN paid_amount ELSE 0 END),0) transport_paid,
                COALESCE(SUM(CASE WHEN item_type='transport' THEN discount_amount ELSE 0 END),0) transport_discount,
                COALESCE(SUM(CASE
                    WHEN (item_type='previous_due' OR source_academic_year_id IS NOT NULL)
                    THEN balance_amount ELSE 0 END),0) previous_pending,
                COALESCE(SUM(CASE
                    WHEN (item_type='previous_due' OR source_academic_year_id IS NOT NULL)
                    THEN original_amount ELSE 0 END),0) previous_assigned,
                COALESCE(SUM(CASE
                    WHEN item_type NOT IN('tuition','admission','transport','previous_due')
                         AND source_academic_year_id IS NULL
                    THEN original_amount ELSE 0 END),0) other_amount,
                COALESCE(SUM(CASE WHEN item_type<>'transport' THEN original_amount ELSE 0 END),0) nontransport_gross,
                COALESCE(SUM(CASE WHEN item_type<>'transport' THEN balance_amount ELSE 0 END),0) nontransport_balance
             FROM student_fee_items
             WHERE tenant_id=:tenant_id
               AND branch_id=:branch_id
               AND assignment_id=:assignment_id
               AND item_status<>'cancelled'"
        );
    }

    foreach ($records as &$record) {
        $record['status'] = studentsStatusLabel((string)$record['status']);
        $record['fee_tuition_amount'] = 0.0;
        $record['fee_admission_amount'] = 0.0;
        $record['fee_other_amount'] = 0.0;
        $record['previous_year_pending_amount'] = 0.0;
        $record['previous_year_assigned_amount'] = 0.0;
        $record['fee_display_total_amount'] = (float)($record['total_fee_amount'] ?? 0);
        $record['fee_display_balance_amount'] = 0.0;

        $assignmentId = (int)($record['fee_assignment_id'] ?? 0);
        if ($feeBreakdownStatement && $assignmentId > 0) {
            $feeBreakdownStatement->execute([
                'tenant_id' => $scope['tenant_id'],
                'branch_id' => $scope['branch_id'],
                'assignment_id' => $assignmentId,
            ]);
            $fee = $feeBreakdownStatement->fetch(PDO::FETCH_ASSOC) ?: [];

            $fixedTransport = max(0.0, round((float)($record['transport_fee_amount'] ?? 0), 2));
            $transportPaid = max(0.0, (float)($fee['transport_paid'] ?? 0));
            $transportDiscount = max(0.0, (float)($fee['transport_discount'] ?? 0));
            $transportBalance = max(0.0, $fixedTransport - $transportPaid - $transportDiscount);

            $record['fee_tuition_amount'] = round((float)($fee['tuition_amount'] ?? 0), 2);
            $record['fee_admission_amount'] = round((float)($fee['admission_amount'] ?? 0), 2);
            $record['fee_other_amount'] = round((float)($fee['other_amount'] ?? 0), 2);
            $record['previous_year_pending_amount'] = round((float)($fee['previous_pending'] ?? 0), 2);
            $record['previous_year_assigned_amount'] = round((float)($fee['previous_assigned'] ?? 0), 2);
            $record['fee_display_total_amount'] = round((float)($fee['nontransport_gross'] ?? 0) + $fixedTransport, 2);
            $record['fee_display_balance_amount'] = round((float)($fee['nontransport_balance'] ?? 0) + $transportBalance, 2);
        }
    }
    unset($record);

    return $records;
}

function studentsStats(PDO $pdo, array $scope, array $filters = []): array
{
    $academicYearId = (int)($filters['academic_year_id'] ?? 0);

    $where = [
        's.tenant_id = :tenant_id',
        's.branch_id = :branch_id',
    ];
    $params = [
        'tenant_id' => $scope['tenant_id'],
        'branch_id' => $scope['branch_id'],
    ];

    if (studentsColumnExists($pdo, 'students', 'deleted_at')) {
        $where[] = 's.deleted_at IS NULL';
    }

    $join = '';
    $newAdmissionExpression = 'YEAR(s.admission_date) = YEAR(CURDATE())';

    if ($academicYearId > 0) {
        $join = "
         INNER JOIN student_enrollments se
            ON se.student_id = s.id
           AND se.tenant_id = s.tenant_id
           AND se.branch_id = s.branch_id
           AND se.academic_year_id = :stats_academic_year_id
         LEFT JOIN academic_years ay
            ON ay.id = se.academic_year_id
           AND ay.tenant_id = se.tenant_id
           AND ay.branch_id = se.branch_id";
        $params['stats_academic_year_id'] = $academicYearId;
        $newAdmissionExpression = "(ay.id IS NOT NULL AND s.admission_date BETWEEN ay.start_date AND ay.end_date)";
    }

    $statement = $pdo->prepare(
        "SELECT
            COUNT(DISTINCT s.id) AS total,
            COUNT(DISTINCT CASE WHEN s.status='active' THEN s.id END) AS active,
            COUNT(DISTINCT CASE WHEN s.status<>'active' THEN s.id END) AS inactive,
            COUNT(DISTINCT CASE WHEN {$newAdmissionExpression} THEN s.id END) AS new_admissions
         FROM students s
         {$join}
         WHERE " . implode(' AND ', $where)
    );
    $statement->execute($params);
    return $statement->fetch(PDO::FETCH_ASSOC) ?: [];
}

function studentsGetRecord(PDO $pdo, array $scope, int $studentId): ?array
{
    $records = studentsList($pdo, $scope, ['search' => '']);
    foreach ($records as $record) {
        if ((int)$record['id'] === $studentId) {
            return $record;
        }
    }
    return null;
}


function studentsImportHeaders(): array
{
    return [
        'academic_year_id','class_id','class_name','section_id','section_name',
        'fee_structure_id','transport_required','transport_route_id','transport_stop_id',
        'admission_number','roll_number','student_name','date_of_birth','gender',
        'blood_group','admission_date','status','parent_name','relationship','mobile','email','address','notes'
    ];
}

function studentsTemplateRows(): array
{
    return [[
        '3','23','LKG','','A','','0','','','','1','Sample Student',
        '2021-05-15','Male','O+','2026-08-04','active','Sample Parent','Father',
        '9876543210','parent@example.com','Sample Address','Sample import row'
    ]];
}

function studentsDownloadImportTemplate(string $format): never
{
    $headers=studentsImportHeaders();
    $rows=studentsTemplateRows();
    while(ob_get_level()>0){ob_end_clean();}

    if($format==='csv'){
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="students-import-template.csv"');
        $out=fopen('php://output','wb');
        fwrite($out,"\xEF\xBB\xBF");
        fputcsv($out,$headers);
        foreach($rows as $row)fputcsv($out,$row);
        fclose($out);
        exit;
    }

    if(!class_exists('ZipArchive')){
        throw new RuntimeException('PHP ZipArchive extension is required to generate the Excel template.',500);
    }

    $escape=static fn(string $value):string=>htmlspecialchars($value,ENT_XML1|ENT_QUOTES,'UTF-8');
    $sheetRows=[];
    foreach(array_merge([$headers],$rows) as $rowIndex=>$row){
        $cells=[];
        foreach($row as $columnIndex=>$value){
            $n=$columnIndex+1;$letters='';
            while($n>0){$n--; $letters=chr(65+($n%26)).$letters; $n=intdiv($n,26);}
            $ref=$letters.($rowIndex+1);
            $cells[]='<c r="'.$ref.'" t="inlineStr"><is><t>'.$escape((string)$value).'</t></is></c>';
        }
        $sheetRows[]='<row r="'.($rowIndex+1).'">'.implode('',$cells).'</row>';
    }
    $sheet='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.implode('',$sheetRows).'</sheetData></worksheet>';
    $workbook='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Students" sheetId="1" r:id="rId1"/></sheets></workbook>';
    $types='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>';
    $rels='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    $wbRels='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>';

    $tmp=tempnam(sys_get_temp_dir(),'students-xlsx-');
    $zip=new ZipArchive();
    if($zip->open($tmp,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('Unable to create Excel template.',500);
    $zip->addFromString('[Content_Types].xml',$types);
    $zip->addFromString('_rels/.rels',$rels);
    $zip->addFromString('xl/workbook.xml',$workbook);
    $zip->addFromString('xl/_rels/workbook.xml.rels',$wbRels);
    $zip->addFromString('xl/worksheets/sheet1.xml',$sheet);
    $zip->close();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="students-import-template.xlsx"');
    header('Content-Length: '.filesize($tmp));
    readfile($tmp);unlink($tmp);exit;
}

function studentsExcelColumnIndex(string $reference): int
{
    $letters=preg_replace('/[^A-Z]/','',strtoupper($reference))??'';
    $index=0;
    for($i=0,$l=strlen($letters);$i<$l;$i++)$index=$index*26+(ord($letters[$i])-64);
    return max(0,$index-1);
}

function studentsDecodeXmlText(string $value): string
{
    return html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function studentsReadWorksheetXmlFallback(string $sheetXml, array $sharedStrings): array
{
    /*
     * Primary parser: DOM + namespace-independent XPath.
     * This safely handles prefixed XML such as x:worksheet, x:row and x:c.
     */
    if(class_exists('DOMDocument') && class_exists('DOMXPath')){
        $document=new DOMDocument();
        $previous=libxml_use_internal_errors(true);

        try{
            if($document->loadXML($sheetXml,LIBXML_NONET|LIBXML_COMPACT)){
                $xpath=new DOMXPath($document);
                $rows=[];

                foreach($xpath->query('//*[local-name()="row"]')?:[] as $rowNode){
                    $values=[];

                    foreach($xpath->query('./*[local-name()="c"]',$rowNode)?:[] as $cellNode){
                        if(!$cellNode instanceof DOMElement){
                            continue;
                        }

                        $reference=(string)$cellNode->getAttribute('r');
                        $type=strtolower((string)$cellNode->getAttribute('t'));
                        $index=$reference!==''?studentsExcelColumnIndex($reference):count($values);
                        $value='';

                        if($type==='inlinestr'){
                            foreach($xpath->query('.//*[local-name()="t"]',$cellNode)?:[] as $textNode){
                                $value.=(string)$textNode->textContent;
                            }
                        }else{
                            $valueNode=$xpath->query('./*[local-name()="v"]',$cellNode)?->item(0);
                            $raw=$valueNode?(string)$valueNode->textContent:'';

                            if($type==='s'){
                                $value=(string)($sharedStrings[(int)$raw]??'');
                            }elseif($type==='b'){
                                $value=$raw==='1'?'1':'0';
                            }else{
                                /*
                                 * Covers numeric, formula cache and t="str" cells.
                                 */
                                $value=$raw;
                            }
                        }

                        $values[$index]=trim(
                            html_entity_decode($value,ENT_QUOTES|ENT_XML1,'UTF-8')
                        );
                    }

                    if($values===[]){
                        continue;
                    }

                    $maximum=max(array_keys($values));
                    $line=[];

                    for($column=0;$column<=$maximum;$column++){
                        $line[]=(string)($values[$column]??'');
                    }

                    if(array_filter(
                        $line,
                        static fn(string $value):bool=>trim($value)!==''
                    )){
                        $rows[]=$line;
                    }
                }

                if($rows!==[]){
                    return $rows;
                }
            }
        }finally{
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /*
     * Regex fallback for servers where DOM is unavailable.
     */
    $rows=[];

    if(!preg_match_all(
        '/<(?:[A-Za-z0-9_]+:)?row[^>]*>(.*?)<\/(?:[A-Za-z0-9_]+:)?row>/si',
        $sheetXml,
        $rowMatches
    )){
        return [];
    }

    foreach($rowMatches[1] as $rowXml){
        $values=[];

        if(!preg_match_all(
            '/<(?:[A-Za-z0-9_]+:)?c([^>]*)(?:\/>|>(.*?)<\/(?:[A-Za-z0-9_]+:)?c>)/si',
            $rowXml,
            $cellMatches,
            PREG_SET_ORDER
        )){
            continue;
        }

        foreach($cellMatches as $cellMatch){
            $attributes=(string)($cellMatch[1]??'');
            $cellBody=(string)($cellMatch[2]??'');

            preg_match("/r=['\"]([^'\"]+)['\"]/i",$attributes,$referenceMatch);
            preg_match("/t=['\"]([^'\"]+)['\"]/i",$attributes,$typeMatch);

            $reference=(string)($referenceMatch[1]??'');
            $type=strtolower((string)($typeMatch[1]??''));
            $index=$reference!==''?studentsExcelColumnIndex($reference):count($values);
            $value='';

            if($type==='inlinestr'){
                if(preg_match_all(
                    '/<(?:[A-Za-z0-9_]+:)?t[^>]*>(.*?)<\/(?:[A-Za-z0-9_]+:)?t>/si',
                    $cellBody,
                    $textMatches
                )){
                    foreach($textMatches[1] as $textValue){
                        $value.=studentsDecodeXmlText((string)$textValue);
                    }
                }
            }else{
                $raw='';

                if(preg_match(
                    '/<(?:[A-Za-z0-9_]+:)?v[^>]*>(.*?)<\/(?:[A-Za-z0-9_]+:)?v>/si',
                    $cellBody,
                    $valueMatch
                )){
                    $raw=studentsDecodeXmlText((string)$valueMatch[1]);
                }

                if($type==='s'){
                    $value=(string)($sharedStrings[(int)$raw]??'');
                }elseif($type==='b'){
                    $value=$raw==='1'?'1':'0';
                }else{
                    $value=$raw;
                }
            }

            $values[$index]=trim($value);
        }

        if($values===[]){
            continue;
        }

        $maximum=max(array_keys($values));
        $line=[];

        for($column=0;$column<=$maximum;$column++){
            $line[]=(string)($values[$column]??'');
        }

        if(array_filter($line,static fn(string $value):bool=>trim($value)!=='')){
            $rows[]=$line;
        }
    }

    return $rows;
}

function studentsReadXlsx(string $path): array
{
    if(!class_exists('ZipArchive'))throw new RuntimeException('PHP ZipArchive extension is required for Excel import.',500);
    $zip=new ZipArchive();
    if($zip->open($path)!==true)throw new InvalidArgumentException('Unable to open the Excel file.');
    try{
        $sharedStrings=[];$sharedXml=$zip->getFromName('xl/sharedStrings.xml');
        if($sharedXml!==false&&preg_match_all('/<(?:[A-Za-z0-9_]+:)?si\\b[^>]*>(.*?)<\\/(?:[A-Za-z0-9_]+:)?si>/si',$sharedXml,$stringMatches)){
            foreach($stringMatches[1] as $stringXml){$text='';if(preg_match_all('/<(?:[A-Za-z0-9_]+:)?t\\b[^>]*>(.*?)<\\/(?:[A-Za-z0-9_]+:)?t>/si',(string)$stringXml,$textMatches)){foreach($textMatches[1] as $textValue)$text.=studentsDecodeXmlText((string)$textValue);} $sharedStrings[]=$text;}
        }
        $worksheetNames=[];
        for($index=0;$index<$zip->numFiles;$index++){$name=(string)$zip->getNameIndex($index);if(preg_match('#^xl/worksheets/[^/]+\\.xml$#i',$name))$worksheetNames[]=$name;}
        natsort($worksheetNames);
        $required=['academic_year_id','student_name','date_of_birth','gender','admission_date','parent_name','mobile'];
        $best=[];
        foreach($worksheetNames as $worksheetName){
            $sheetXml=$zip->getFromName($worksheetName);if($sheetXml===false)continue;
            $rows=studentsReadWorksheetXmlFallback((string)$sheetXml,$sharedStrings);if(count($rows)<2)continue;
            $headers=array_map(static fn($v)=>strtolower(trim((string)$v)),$rows[0]);
            if(array_diff($required,$headers)===[])return $rows;
            if(count($rows)>count($best))$best=$rows;
        }
        if(count($best)>=2)return $best;
        throw new InvalidArgumentException('The Excel workbook was opened, but no Student Import worksheet with data was found. Download the latest template and keep the header row unchanged.');
    }finally{$zip->close();}
}

function studentsReadCsvFile(string $path): array
{
    $handle=fopen($path,'rb');if(!$handle)throw new InvalidArgumentException('Unable to read the CSV file.');
    $rows=[];while(($row=fgetcsv($handle))!==false){if($rows===[]&&isset($row[0]))$row[0]=preg_replace('/^\xEF\xBB\xBF/','',(string)$row[0]);$rows[]=$row;}fclose($handle);return $rows;
}

function studentsNormalizeImportDate(mixed $value): string
{
    $value=trim((string)$value);
    if($value==='')return '';
    if(is_numeric($value)&&((float)$value)>20000){$base=new DateTimeImmutable('1899-12-30');return $base->modify('+'.((int)$value).' days')->format('Y-m-d');}
    foreach(['Y-m-d','d-m-Y','d/m/Y','m/d/Y'] as $format){$date=DateTimeImmutable::createFromFormat($format,$value);if($date&&$date->format($format)===$value)return $date->format('Y-m-d');}
    return $value;
}


function studentsResolveImportClassId(
    PDO $pdo,
    int $tenantId,
    int $branchId,
    int $academicYearId,
    int $inputClassId,
    string $className
): int {
    $className=trim($className);
    if ($inputClassId>0) {
        $statement=$pdo->prepare(
            "SELECT id FROM classes
             WHERE id=:id AND tenant_id=:tenant_id AND branch_id=:branch_id
               AND academic_year_id=:academic_year_id LIMIT 1"
        );
        $statement->execute([
            'id'=>$inputClassId,'tenant_id'=>$tenantId,'branch_id'=>$branchId,
            'academic_year_id'=>$academicYearId,
        ]);
        $id=(int)($statement->fetchColumn()?:0);
        if($id>0)return $id;

        if(studentsTableExists($pdo,'class_management_classes')){
            $statement=$pdo->prepare(
                "SELECT class_name FROM class_management_classes
                 WHERE id=:id AND tenant_id=:tenant_id AND branch_id=:branch_id
                   AND academic_year_id=:academic_year_id LIMIT 1"
            );
            $statement->execute([
                'id'=>$inputClassId,'tenant_id'=>$tenantId,'branch_id'=>$branchId,
                'academic_year_id'=>$academicYearId,
            ]);
            $legacy=trim((string)($statement->fetchColumn()?:''));
            if($legacy!=='')$className=$legacy;
        }
    }
    if($className!==''){
        $statement=$pdo->prepare(
            "SELECT id FROM classes
             WHERE tenant_id=:tenant_id AND branch_id=:branch_id
               AND academic_year_id=:academic_year_id
               AND LOWER(TRIM(class_name))=LOWER(TRIM(:class_name))
             ORDER BY display_order,id LIMIT 1"
        );
        $statement->execute([
            'tenant_id'=>$tenantId,'branch_id'=>$branchId,
            'academic_year_id'=>$academicYearId,'class_name'=>$className,
        ]);
        $id=(int)($statement->fetchColumn()?:0);
        if($id>0)return $id;
    }
    throw new InvalidArgumentException(
        'Class was not found in Class Management for the current Branch and Academic Year ID '.$academicYearId.'.'
    );
}

function studentsResolveImportSectionId(
    PDO $pdo,
    int $tenantId,
    int $branchId,
    int $academicYearId,
    int $classId,
    int $inputSectionId,
    string $sectionName
): int {
    if($inputSectionId>0){
        $statement=$pdo->prepare(
            "SELECT s.id FROM sections s
             INNER JOIN classes c ON c.id=s.class_id AND c.tenant_id=s.tenant_id AND c.branch_id=s.branch_id
             WHERE s.id=:id AND s.tenant_id=:tenant_id AND s.branch_id=:branch_id
               AND s.class_id=:class_id AND c.academic_year_id=:academic_year_id LIMIT 1"
        );
        $statement->execute([
            'id'=>$inputSectionId,'tenant_id'=>$tenantId,'branch_id'=>$branchId,
            'class_id'=>$classId,'academic_year_id'=>$academicYearId,
        ]);
        $id=(int)($statement->fetchColumn()?:0);
        if($id>0)return $id;
    }
    $sectionName=trim($sectionName);
    if($sectionName!==''){
        $statement=$pdo->prepare(
            "SELECT id FROM sections
             WHERE tenant_id=:tenant_id AND branch_id=:branch_id AND class_id=:class_id
               AND LOWER(TRIM(section_name))=LOWER(TRIM(:section_name))
             ORDER BY status='active' DESC,id LIMIT 1"
        );
        $statement->execute([
            'tenant_id'=>$tenantId,'branch_id'=>$branchId,'class_id'=>$classId,'section_name'=>$sectionName,
        ]);
        $id=(int)($statement->fetchColumn()?:0);
        if($id>0)return $id;
    }
    $statement=$pdo->prepare(
        "SELECT id FROM sections
         WHERE tenant_id=:tenant_id AND branch_id=:branch_id AND class_id=:class_id
         ORDER BY status='active' DESC,id LIMIT 2"
    );
    $statement->execute([
        'tenant_id'=>$tenantId,'branch_id'=>$branchId,'class_id'=>$classId,
    ]);
    $available=array_map('intval',$statement->fetchAll(PDO::FETCH_COLUMN));
    if(count($available)===1)return $available[0];
    throw new InvalidArgumentException(
        'Section was not found for the selected class in the current Branch.'
    );
}

function studentsImportExistingStudentId(
    PDO $pdo,
    int $tenantId,
    int $branchId,
    string $admissionNumber
): int {
    $statement=$pdo->prepare(
        "SELECT id FROM students
         WHERE tenant_id=:tenant_id AND branch_id=:branch_id AND admission_no=:admission_no
         LIMIT 1"
    );
    $statement->execute([
        'tenant_id'=>$tenantId,'branch_id'=>$branchId,'admission_no'=>$admissionNumber,
    ]);
    return (int)($statement->fetchColumn()?:0);
}

function studentsNormalizeImportHeader(mixed $value): string
{
    $header = strtolower(trim((string)$value));
    $header = preg_replace('/[^a-z0-9]+/', '_', $header) ?? $header;
    $header = trim($header, '_');

    $aliases = [
        'admission_no' => 'admission_number',
        'roll_no' => 'roll_number',
        'name' => 'student_name',
        'student' => 'student_name',
        'dob' => 'date_of_birth',
        'class' => 'class_name',
        'standard' => 'class_name',
        'section' => 'section_name',
        'guardian_name' => 'parent_name',
        'father_name' => 'parent_name',
        'phone' => 'mobile',
        'mobile_number' => 'mobile',
        'route_id' => 'transport_route_id',
        'stop_id' => 'transport_stop_id',
    ];

    return $aliases[$header] ?? $header;
}

function studentsImportFallbackValues(array $input): array
{
    $academicYearId=(int)($input['fallback_academic_year_id']??0);
    $admissionDate=studentsNormalizeImportDate($input['fallback_admission_date']??'');
    return [
        'academic_year_id'=>$academicYearId>0?$academicYearId:'',
        'admission_date'=>trim($admissionDate),
    ];
}

function studentsImportMissingColumns(array $rows,array $fallbackValues=[]): array
{
    if(!$rows)return [];
    $headers=array_map('studentsNormalizeImportHeader',(array)($rows[0]??[]));
    $required=[
        'academic_year_id','student_name','date_of_birth','gender',
        'admission_date','parent_name','mobile',
    ];
    $missing=[];
    foreach($required as $column){
        if(in_array($column,$headers,true))continue;
        if((string)($fallbackValues[$column]??'')!=='')continue;
        $missing[]=$column;
    }
    if(!in_array('class_id',$headers,true)&&!in_array('class_name',$headers,true)){
        $missing[]='class_id or class_name';
    }
    return array_values(array_unique($missing));
}

function studentsImportRows(PDO $pdo,array $scope,array $rows,string $duplicateMode='skip',array $fallbackValues=[]): array
{
    if(count($rows)<2)throw new InvalidArgumentException('The import file must contain a header row and at least one student row.');
    if(count($rows)>2001)throw new InvalidArgumentException('A maximum of 2,000 student rows can be imported at one time.');
    $headers = array_map(
        'studentsNormalizeImportHeader',
        array_shift($rows)
    );

    $missing = studentsImportMissingColumns(
        [$headers],
        $fallbackValues
    );

    if ($missing) {
        throw new InvalidArgumentException(
            'Missing required columns: ' . implode(', ', $missing) . '.'
        );
    }
    $duplicateMode=strtolower(trim($duplicateMode));
    if(!in_array($duplicateMode,['skip','update'],true))$duplicateMode='skip';
    $created=0;
    $updated=0;
    $skipped=0;
    $errors=[];
    $createdRows=[];
    $updatedRows=[];
    $skippedRows=[];
    $failedRows=[];
    foreach($rows as $index=>$values){
        $rowNumber=$index+2;

        if(!array_filter($values,static fn($v)=>trim((string)$v)!==''))continue;
        $values=array_pad($values,count($headers),'');
        $row=array_combine($headers,array_slice($values,0,count($headers)));
        if(!is_array($row)){
            $reason='Invalid column structure.';
            $errors[]='Row '.$rowNumber.': '.$reason;
            $failedRows[]=[
                'row'=>$rowNumber,
                'admission_number'=>'',
                'student_name'=>'',
                'reason'=>$reason,
            ];
            continue;
        }
        $row=array_map(static fn($v)=>is_string($v)?trim($v):$v,$row);

        /* Import always belongs to the logged-in active branch. */
        $row['branch_id'] = (int)$scope['branch_id'];

        foreach ($fallbackValues as $fallbackColumn => $fallbackValue) {
            if (
                !array_key_exists($fallbackColumn, $row)
                || trim((string)($row[$fallbackColumn] ?? '')) === ''
            ) {
                $row[$fallbackColumn] = $fallbackValue;
            }
        }

        $row['id']=0;
        $row['date_of_birth']=studentsNormalizeImportDate($row['date_of_birth']??'');
        $row['admission_date']=studentsNormalizeImportDate($row['admission_date']??'');
        $row['transport_required']=in_array(
            strtolower((string)($row['transport_required']??'0')),
            ['1','yes','true'],
            true
        )?1:0;

        try{
            $academicYearId=(int)($row['academic_year_id']??0);
            $row['class_id']=studentsResolveImportClassId(
                $pdo,
                $scope['tenant_id'],
                $scope['branch_id'],
                $academicYearId,
                (int)($row['class_id']??0),
                (string)($row['class_name']??'')
            );
            $row['section_id']=studentsResolveImportSectionId(
                $pdo,
                $scope['tenant_id'],
                $scope['branch_id'],
                $academicYearId,
                (int)$row['class_id'],
                (int)($row['section_id']??0),
                (string)($row['section_name']??'')
            );

            $admissionNumber=trim((string)($row['admission_number']??''));
            $existingId=$admissionNumber!==''
                ?studentsImportExistingStudentId(
                    $pdo,
                    $scope['tenant_id'],
                    $scope['branch_id'],
                    $admissionNumber
                )
                :0;

            if($existingId>0&&$duplicateMode==='skip'){
                $skipped++;
                $skippedRows[]=[
                    'row'=>$rowNumber,
                    'student_id'=>$existingId,
                    'admission_number'=>$admissionNumber,
                    'student_name'=>(string)($row['student_name']??''),
                    'reason'=>'Admission Number already exists.',
                ];
                continue;
            }

            $row['id']=$existingId>0?$existingId:0;
            $savedStudentId=studentsSave($pdo,$scope,$row);

            if($existingId>0){
                $updated++;
                $updatedRows[]=[
                    'row'=>$rowNumber,
                    'student_id'=>$existingId,
                    'admission_number'=>$admissionNumber,
                    'student_name'=>(string)($row['student_name']??''),
                    'reason'=>'Existing student updated.',
                ];
            }else{
                $created++;
                $savedRecord=studentsGetRecord($pdo,$scope,$savedStudentId);
                $generatedAdmissionNumber=(string)($savedRecord['admission_number']??$admissionNumber);
                $createdRows[]=[
                    'row'=>$rowNumber,
                    'student_id'=>$savedStudentId,
                    'admission_number'=>$generatedAdmissionNumber,
                    'student_name'=>(string)($row['student_name']??''),
                    'reason'=>'New student created.',
                ];
            }
        }catch(Throwable $e){
            $reason=$e->getMessage();
            $errors[]='Row '.$rowNumber.': '.$reason;
            $failedRows[]=[
                'row'=>$rowNumber,
                'student_id'=>0,
                'admission_number'=>(string)($row['admission_number']??''),
                'student_name'=>(string)($row['student_name']??''),
                'reason'=>$reason,
            ];
        }
    }

    return [
        'created'=>$created,
        'updated'=>$updated,
        'skipped'=>$skipped,
        'failed'=>count($errors),
        'errors'=>$errors,
        'created_rows'=>$createdRows,
        'updated_rows'=>$updatedRows,
        'skipped_rows'=>$skippedRows,
        'failed_rows'=>$failedRows,
    ];
}

function studentsAdmissionNumberExists(
    PDO $pdo,
    int $tenantId,
    string $admissionNumber,
    int $excludeStudentId = 0
): bool {
    $admissionNumber = strtoupper(trim($admissionNumber));
    if ($tenantId <= 0 || $admissionNumber === '') return false;

    $statement = $pdo->prepare(
        'SELECT id
         FROM students
         WHERE tenant_id = :tenant_id
           AND admission_no = :admission_no
           AND id <> :exclude_id
         LIMIT 1'
    );
    $statement->execute([
        'tenant_id' => $tenantId,
        'admission_no' => $admissionNumber,
        'exclude_id' => $excludeStudentId,
    ]);
    return (int)$statement->fetchColumn() > 0;
}

function studentsGenerateUniqueAdmissionNumber(
    PDO $pdo,
    int $tenantId,
    int $excludeStudentId = 0,
    int $maxAttempts = 250
): string {
    if ($tenantId <= 0) {
        throw new InvalidArgumentException(
            'School tenant is required for Admission Number generation.'
        );
    }

    for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
        $candidate = strtoupper(trim((string)school_settings_next_number(
            $pdo,
            $tenantId,
            'admission'
        )));

        if ($candidate === '') continue;

        if (!studentsAdmissionNumberExists(
            $pdo,
            $tenantId,
            $candidate,
            $excludeStudentId
        )) {
            return $candidate;
        }
    }

    throw new RuntimeException(
        'Unable to generate a unique Admission Number. Check the Admission Number Prefix/sequence in General Settings.'
    );
}

function studentsPeekUniqueAdmissionNumber(PDO $pdo, int $tenantId): string
{
    $candidate = strtoupper(trim((string)school_settings_peek_number(
        $pdo,
        $tenantId,
        'admission'
    )));

    if ($candidate !== '' && !studentsAdmissionNumberExists($pdo, $tenantId, $candidate)) {
        return $candidate;
    }

    $settings = school_settings_get($pdo, $tenantId);
    $prefix = strtoupper(trim((string)school_settings_prefix($settings, 'admission')));
    if ($prefix === '') $prefix = 'ADM';

    $width = 4;
    $number = 1;
    if ($candidate !== '' && preg_match('/^(.*?)(\\d+)$/', $candidate, $matches)) {
        $prefix = strtoupper(trim((string)$matches[1])) ?: $prefix;
        $width = max(1, strlen((string)$matches[2]));
        $number = max(1, (int)$matches[2]);
    }

    for ($attempt = 0; $attempt < 500; $attempt++, $number++) {
        $preview = $prefix . str_pad((string)$number, $width, '0', STR_PAD_LEFT);
        if (!studentsAdmissionNumberExists($pdo, $tenantId, $preview)) {
            return $preview;
        }
    }

    return $candidate !== '' ? $candidate : $prefix . '0001';
}

function studentsSave(PDO $pdo, array $scope, array $input): int
{
    $studentId = (int)($input['id'] ?? 0);
    $isNewStudent = $studentId <= 0;
    $academicYearId = (int)($input['academic_year_id'] ?? 0);
    $postedBranchId = (int)($input['branch_id'] ?? 0);
    $branchId = (int)$scope['branch_id'];
    if ($postedBranchId > 0 && $postedBranchId !== $branchId) {
        throw new InvalidArgumentException('Student cannot be saved to another branch. Reload the page and try again.');
    }
    $inputClassId = (int)($input['class_id'] ?? 0);
    $inputSectionId = (int)($input['section_id'] ?? 0);
    $feeStructureId = (int)($input['fee_structure_id'] ?? 0);
    $transportRequired = (int)($input['transport_required'] ?? 0) === 1;
    $transportRouteId = (int)($input['transport_route_id'] ?? 0);
    $transportStopId = (int)($input['transport_stop_id'] ?? 0);
    $admissionNo = strtoupper(trim((string)($input['admission_number'] ?? $input['admission_no'] ?? '')));
    $admissionNumberMode = strtolower(trim((string)($input['admission_number_mode'] ?? '')));
    $admissionNumberAuto = (int)($input['admission_number_auto'] ?? 0) === 1;

    /* Auto flag is authoritative for a new student. */
    if ($studentId <= 0 && $admissionNumberAuto) {
        $admissionNumberMode = 'auto';
    }

    if (!in_array($admissionNumberMode, ['auto','manual'], true)) {
        $admissionNumberMode = $studentId <= 0 ? 'auto' : 'manual';
    }

    /*
     * Auto mode deliberately ignores the browser preview value.
     * The real number is generated inside the save transaction so two users
     * cannot reserve the same Admission Number.
     */
    if ($studentId <= 0 && $admissionNumberMode === 'auto') {
        $admissionNo = '';
    }

    $studentName = trim((string)($input['student_name'] ?? ''));
    $dateOfBirth = trim((string)($input['date_of_birth'] ?? ''));
    $gender = studentsNormalizeGender((string)($input['gender'] ?? ''));
    $admissionDate = trim((string)($input['admission_date'] ?? ''));
    $parentName = trim((string)($input['parent_name'] ?? ''));
    $mobile = trim((string)($input['mobile'] ?? ''));

    $missingRequired=[];

    if($academicYearId<=0)$missingRequired[]='academic_year_id';
    if($inputClassId<=0)$missingRequired[]='class_id';
    if($studentName==='')$missingRequired[]='student_name';
    if($dateOfBirth==='')$missingRequired[]='date_of_birth';
    if($gender==='')$missingRequired[]='gender';
    if($admissionDate==='')$missingRequired[]='admission_date';
    if($parentName==='')$missingRequired[]='parent_name';
    if($mobile==='')$missingRequired[]='mobile';

    if($missingRequired!==[]){
        throw new InvalidArgumentException(
            'Missing required value(s): '.implode(', ',$missingRequired).'.'
        );
    }

    if (
        $studentId <= 0
        && $admissionNumberMode === 'manual'
        && $admissionNo === ''
    ) {
        throw new InvalidArgumentException(
            'Manual Admission Number is required. Enter a number or use Auto generation.'
        );
    }

    if (mb_strlen($admissionNo) > 50) {
        throw new InvalidArgumentException('Admission number cannot exceed 50 characters.');
    }

    studentsValidateDate($dateOfBirth, 'Date of birth');
    studentsValidateDate($admissionDate, 'Admission date');

    $academicYear = studentsFindAcademicYear($pdo, $scope['tenant_id'], $branchId, $academicYearId);
    studentsFindBranch($pdo, $scope['tenant_id'], $branchId);
    $class = studentsResolveClass($pdo, $scope['tenant_id'], $branchId, $academicYearId, $inputClassId);
    $section = studentsResolveSection(
        $pdo,
        $scope['tenant_id'],
        $branchId,
        $academicYearId,
        $class,
        $inputSectionId
    );

    if (
        $admissionNo !== ''
        && studentsAdmissionNumberExists(
            $pdo,
            $scope['tenant_id'],
            $admissionNo,
            $studentId
        )
    ) {
        throw new InvalidArgumentException(
            'Admission number already exists. Use Auto generation or enter another manual number.'
        );
    }

    [$firstName, $lastName] = studentsSplitName($studentName);
    $status = studentsNormalizeStatus((string)($input['status'] ?? 'active'));
    $bloodGroup = trim((string)($input['blood_group'] ?? ''));
    $email = trim((string)($input['email'] ?? ''));
    $address = trim((string)($input['address'] ?? ''));
    $relationship = trim((string)($input['relationship'] ?? ''));
    $rollNo = trim((string)($input['roll_number'] ?? $input['roll_no'] ?? ''));
    $notes = trim((string)($input['notes'] ?? ''));
    $emisNo = trim((string)($input['emis_no'] ?? ''));

    $old = $studentId > 0 ? studentsGetRecord($pdo, $scope, $studentId) : null;

    if ($studentId > 0 && $admissionNo === '' && $old) {
        $admissionNo = trim((string)($old['admission_number'] ?? $old['admission_no'] ?? ''));
    }

    $pdo->beginTransaction();
    try {
        if (
            $studentId <= 0
            && $admissionNumberMode === 'auto'
        ) {
            $admissionNo = studentsGenerateUniqueAdmissionNumber(
                $pdo,
                $scope['tenant_id'],
                0
            );
        }

        if ($admissionNo === '') {
            throw new InvalidArgumentException('Admission number could not be generated.');
        }

        if (studentsAdmissionNumberExists(
            $pdo,
            $scope['tenant_id'],
            $admissionNo,
            $studentId
        )) {
            if ($studentId <= 0 && $admissionNumberMode === 'auto') {
                $admissionNo = studentsGenerateUniqueAdmissionNumber(
                    $pdo,
                    $scope['tenant_id'],
                    0
                );
            } else {
                throw new InvalidArgumentException(
                    'Admission number already exists. Use another manual number.'
                );
            }
        }
        if ($studentId > 0) {
            $statement = $pdo->prepare(
                'UPDATE students SET
                    branch_id = :branch_id,
                    admission_no = :admission_no,
                    emis_no = :emis_no,
                    first_name = :first_name,
                    last_name = :last_name,
                    gender = :gender,
                    date_of_birth = :date_of_birth,
                    blood_group = :blood_group,
                    mobile = :mobile,
                    email = :email,
                    address = :address,
                    admission_date = :admission_date,
                    status = :status,
                    deleted_at = NULL
                 WHERE id = :id AND tenant_id = :tenant_id AND branch_id = :current_branch_id'
            );
            $statement->execute([
                'branch_id' => $branchId,
                'admission_no' => $admissionNo,
                'emis_no' => $emisNo !== '' ? $emisNo : null,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'gender' => $gender,
                'date_of_birth' => $dateOfBirth,
                'blood_group' => $bloodGroup !== '' ? $bloodGroup : null,
                'mobile' => $mobile,
                'email' => $email !== '' ? $email : null,
                'address' => $address !== '' ? $address : null,
                'admission_date' => $admissionDate,
                'status' => $status,
                'current_branch_id' => $branchId,
                'id' => $studentId,
                'tenant_id' => $scope['tenant_id'],
            ]);

            if ($statement->rowCount() === 0) {
                $exists = $pdo->prepare('SELECT COUNT(*) FROM students WHERE id=:id AND tenant_id=:tenant_id AND branch_id=:branch_id');
                $exists->execute(['id'=>$studentId,'tenant_id'=>$scope['tenant_id'],'branch_id'=>$branchId]);
                if ((int)$exists->fetchColumn() === 0) {
                    throw new RuntimeException('Student not found.', 404);
                }
            }
        } else {
            $statement = $pdo->prepare(
                'INSERT INTO students
                    (tenant_id, branch_id, admission_no, emis_no, first_name, last_name, gender,
                     date_of_birth, blood_group, mobile, email, address, admission_date, status)
                 VALUES
                    (:tenant_id, :branch_id, :admission_no, :emis_no, :first_name, :last_name, :gender,
                     :date_of_birth, :blood_group, :mobile, :email, :address, :admission_date, :status)'
            );

            $insertAttempts = $admissionNumberMode === 'auto' ? 10 : 1;

            for ($insertAttempt = 0; $insertAttempt < $insertAttempts; $insertAttempt++) {
                try {
                    $statement->execute([
                        'tenant_id' => $scope['tenant_id'],
                        'branch_id' => $branchId,
                        'admission_no' => $admissionNo,
                        'emis_no' => $emisNo !== '' ? $emisNo : null,
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'gender' => $gender,
                        'date_of_birth' => $dateOfBirth,
                        'blood_group' => $bloodGroup !== '' ? $bloodGroup : null,
                        'mobile' => $mobile,
                        'email' => $email !== '' ? $email : null,
                        'address' => $address !== '' ? $address : null,
                        'admission_date' => $admissionDate,
                        'status' => $status,
                    ]);
                    $studentId = (int)$pdo->lastInsertId();
                    break;
                } catch (PDOException $exception) {
                    $sqlState = (string)$exception->getCode();
                    $driverCode = (int)($exception->errorInfo[1] ?? 0);
                    $duplicateKey = $sqlState === '23000' || $driverCode === 1062;

                    if (
                        !$duplicateKey
                        || $admissionNumberMode !== 'auto'
                        || $insertAttempt + 1 >= $insertAttempts
                    ) {
                        throw $exception;
                    }

                    $admissionNo = studentsGenerateUniqueAdmissionNumber(
                        $pdo,
                        $scope['tenant_id'],
                        0
                    );
                }
            }

            if ($studentId <= 0) {
                throw new RuntimeException(
                    'Unable to save the student with a unique Admission Number.'
                );
            }
        }

        $enrollmentStatus = match ($status) {
            'tc' => 'transferred',
            'alumni' => 'completed',
            default => 'active',
        };

        /*
         * Do not overwrite a promotion enrollment date with the student's
         * original admission date. Existing promoted/current-year enrollment
         * keeps its own enrolled_on value. If this year has no enrollment yet,
         * start it at the later of the academic-year start or admission date.
         */
        $feeAssignmentDate = $admissionDate;
        if (!$isNewStudent) {
            $existingEnrollmentDate = $pdo->prepare(
                "SELECT enrolled_on
                 FROM student_enrollments
                 WHERE tenant_id = :tenant_id
                   AND branch_id = :branch_id
                   AND student_id = :student_id
                   AND academic_year_id = :academic_year_id
                 ORDER BY id DESC
                 LIMIT 1"
            );
            $existingEnrollmentDate->execute([
                'tenant_id' => $scope['tenant_id'],
                'branch_id' => $branchId,
                'student_id' => $studentId,
                'academic_year_id' => $academicYearId,
            ]);
            $savedEnrollmentDate = trim((string)$existingEnrollmentDate->fetchColumn());

            if ($savedEnrollmentDate !== '') {
                $feeAssignmentDate = $savedEnrollmentDate;
            }

            /*
             * Repair an older promoted enrollment which may already have been
             * overwritten with the student's original (previous-year)
             * admission date.
             */
            $academicStart = (string)($academicYear['start_date'] ?? '');
            if ($academicStart !== '' && $feeAssignmentDate < $academicStart) {
                $feeAssignmentDate = $academicStart;
            }
        }

        $statement = $pdo->prepare(
            'INSERT INTO student_enrollments
                (tenant_id, branch_id, student_id, academic_year_id, class_id, section_id, roll_no, enrollment_status, enrolled_on)
             VALUES
                (:tenant_id, :branch_id, :student_id, :academic_year_id, :class_id, :section_id, :roll_no, :enrollment_status, :enrolled_on)
             ON DUPLICATE KEY UPDATE
                branch_id = VALUES(branch_id),
                class_id = VALUES(class_id),
                section_id = VALUES(section_id),
                roll_no = VALUES(roll_no),
                enrollment_status = VALUES(enrollment_status),
                enrolled_on = VALUES(enrolled_on)'
        );
        $statement->execute([
            'tenant_id' => $scope['tenant_id'],
            'branch_id' => $branchId,
            'student_id' => $studentId,
            'academic_year_id' => $academicYearId,
            'class_id' => $class['canonical_class_id'],
            'section_id' => $section['canonical_section_id'],
            'roll_no' => $rollNo !== '' ? $rollNo : null,
            'enrollment_status' => $enrollmentStatus,
            'enrolled_on' => $feeAssignmentDate,
        ]);

        $assignedFeeStructureId = studentsAssignFeeStructure(
            $pdo,
            $scope,
            $studentId,
            $academicYearId,
            (int)$class['canonical_class_id'],
            $branchId,
            $feeStructureId,
            $transportRequired,
            $transportRouteId,
            $transportStopId,
            $isNewStudent,
            $feeAssignmentDate
        );

        studentsUpsertGuardian($pdo, $scope['tenant_id'], $studentId, [
            'parent_name' => $parentName,
            'relationship' => $relationship,
            'mobile' => $mobile,
            'email' => $email,
            'address' => $address,
        ]);
        studentsUpsertNotes($pdo, $scope['tenant_id'], $studentId, $notes);

        $new = [
            'student_id' => $studentId,
            'academic_year_id' => (int)$academicYear['id'],
            'academic_year_name' => (string)$academicYear['year_name'],
            'branch_id' => $branchId,
            'class_id' => $class['source_class_id'],
            'class_name' => $class['class_name'],
            'section_id' => $section['source_section_id'],
            'section_name' => $section['section_name'],
            'fee_structure_id' => $assignedFeeStructureId,
            'transport_required' => $transportRequired ? 1 : 0,
            'transport_route_id' =>
                $transportRequired ? $transportRouteId : null,
            'transport_stop_id' =>
                $transportRequired ? $transportStopId : null,
            'admission_number' => $admissionNo,
            'student_name' => $studentName,
            'status' => $status,
        ];

        studentsLog(
            $pdo,
            $scope,
            $old ? 'update' : 'create',
            $studentId,
            $old ? 'Student updated.' : 'Student created.',
            $old,
            $new
        );

        $pdo->commit();
        return $studentId;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    studentsJson(false, 'Database connection unavailable.', [], 500);
}

try {
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    $scope = studentsScope($pdo);
    studentsRequireTables($pdo);
    studentsEnsureSupportSchema($pdo);
    studentsEnsureFeeSchema($pdo);
    school_settings_ensure_schema($pdo);
} catch (Throwable $exception) {
    $status = (int)$exception->getCode();
    studentsJson(
        false,
        'Unable to initialize Students Management: ' . $exception->getMessage(),
        [],
        $status >= 400 && $status <= 599 ? $status : 500
    );
}

$input = studentsInput();
$action = strtolower(trim((string)($input['action'] ?? $_GET['action'] ?? '')));

try {
    if ($action === 'meta') {
        if (
            !studentsCan('view')
            && !studentsCan('add')
            && !studentsCan('create')
        ) {
            throw new RuntimeException('Permission denied.', 403);
        }

        studentsJson(true, 'Metadata loaded.', [
            'csrf_token' => function_exists('csrfToken') ? csrfToken() : '',
            'meta' => studentsMeta($pdo, $scope),
            'permissions' => [
                'view' => studentsCan('view'),
                'add' => studentsCan('add') || studentsCan('create'),
                'edit' => studentsCan('edit'),
                'delete' => studentsCan('delete'),
                'import' => studentsCan('import'),
                'export' => studentsCan('export'),
            ],
        ]);
    }

    if ($action === 'import_template') {
        if (!studentsCan('import') && !studentsCan('add') && !studentsCan('create')) {
            throw new RuntimeException('Permission denied.', 403);
        }
        $format=strtolower(trim((string)($_GET['format']??'xlsx')));
        studentsDownloadImportTemplate($format==='csv'?'csv':'xlsx');
    }

    if ($action === 'list') {
        if (!studentsCan('view')) {
            throw new RuntimeException('Permission denied.', 403);
        }

        studentsJson(true, 'Students loaded.', [
            'records' => studentsList($pdo, $scope, array_merge($_GET, $input)),
            'stats' => studentsStats($pdo, $scope, array_merge($_GET, $input)),
        ]);
    }

    if ($action === 'export') {
        if (!studentsCan('export')) {
            throw new RuntimeException('Permission denied.', 403);
        }

        $rows = studentsList($pdo, $scope, $_GET);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="students-' . date('Ymd-His') . '.csv"');
        $output = fopen('php://output', 'wb');
        fputcsv($output, [
            'Admission No',
            'Student',
            'Academic Year',
            'Class',
            'Section',
            'Roll No',
            'Parent',
            'Mobile',
            'Status',
        ]);
        foreach ($rows as $row) {
            fputcsv($output, [
                $row['admission_number'],
                $row['student_name'],
                $row['academic_year_name'],
                $row['class_name'],
                $row['section_name'],
                $row['roll_number'],
                $row['parent_name'],
                $row['mobile'],
                $row['status'],
            ]);
        }
        fclose($output);
        exit;
    }


    if ($action === 'parent_login_list') {
        if (!studentsCan('view')) {
            throw new RuntimeException(
                'Permission denied.',
                403
            );
        }

        $studentScope = strtolower(trim(
            (string)($_GET['student_scope'] ?? 'active')
        ));

        $filter = studentsParentLoginNormalizeFilters(
            $pdo,
            $scope,
            [
                'academic_year_id' => (int)($_GET['academic_year_id'] ?? 0),
                'class_id' => (int)($_GET['class_id'] ?? 0),
                'section_id' => (int)($_GET['section_id'] ?? 0),
            ],
            true
        );

        $rows = studentsParentLoginRows(
            $pdo,
            $scope,
            $studentScope,
            [],
            $filter
        );

        $eligible = 0;
        $existing = 0;
        $withoutGuardian = 0;

        foreach ($rows as $row) {
            if ((bool)$row['has_parent_login']) {
                $existing++;
            } elseif (!(int)($row['guardian_id'] ?? 0)) {
                $withoutGuardian++;
            } else {
                $eligible++;
            }
        }

        studentsJson(
            true,
            'Parent Login student list loaded.',
            [
                'rows' => $rows,
                'summary' => [
                    'total_students' => count($rows),
                    'eligible' => $eligible,
                    'existing' => $existing,
                    'without_guardian' => $withoutGuardian,
                ],
                'filters' => $filter,
                'csrf_token' => function_exists('csrfToken')
                    ? csrfToken()
                    : '',
            ]
        );
    }


    if ($action === 'parent_login_student_credentials') {
        if (!studentsCan('view')) {
            throw new RuntimeException(
                'Permission denied.',
                403
            );
        }

        $studentId = (int)($_GET['student_id'] ?? 0);

        $details = studentsParentLoginCredentialDetails(
            $pdo,
            $scope,
            $studentId
        );

        studentsJson(
            true,
            $details['message'],
            [
                'credential' => $details,
                'csrf_token' => function_exists('csrfToken')
                    ? csrfToken()
                    : '',
            ]
        );
    }


    studentsCsrf($input);


    if ($action === 'parent_login_reset_password') {
        if (
            !studentsCan('edit')
            && !studentsCan('add')
            && !studentsCan('create')
        ) {
            throw new RuntimeException(
                'Permission denied.',
                403
            );
        }

        $studentId = (int)($input['student_id'] ?? 0);

        $details = studentsResetParentLoginPassword(
            $pdo,
            $scope,
            $studentId
        );

        studentsJson(
            true,
            $details['message'],
            [
                'credential' => $details,
                'csrf_token' => function_exists('csrfToken')
                    ? csrfToken()
                    : '',
            ]
        );
    }


    if ($action === 'parent_login_create_selected') {
        if (
            !studentsCan('add')
            && !studentsCan('create')
        ) {
            throw new RuntimeException(
                'Permission denied.',
                403
            );
        }

        $studentIds = is_array(
            $input['student_ids'] ?? null
        )
            ? $input['student_ids']
            : [];

        $result = studentsGenerateParentLoginsSelected(
            $pdo,
            $scope,
            $studentIds,
            [
                'academic_year_id' => (int)($input['academic_year_id'] ?? 0),
                'class_id' => (int)($input['class_id'] ?? 0),
                'section_id' => (int)($input['section_id'] ?? 0),
            ]
        );

        studentsJson(
            true,
            $result['created']
                . ' Parent Login'
                . ($result['created'] === 1 ? '' : 's')
                . ' generated successfully.',
            array_merge(
                $result,
                [
                    'csrf_token' => function_exists('csrfToken')
                        ? csrfToken()
                        : '',
                ]
            )
        );
    }

    if ($action === 'save') {
        $studentId = (int)($input['id'] ?? 0);
        $permission = $studentId > 0 ? 'edit' : 'add';
        if (!studentsCan($permission) && !($studentId === 0 && studentsCan('create'))) {
            throw new RuntimeException('Permission denied.', 403);
        }

        $savedId = studentsSave($pdo, $scope, $input);

        $parentLoginCredential = null;
        $parentLoginWarning = '';
        $parentLoginRequired = $studentId === 0
            && in_array(
                strtolower(trim((string)(
                    $input['parent_login_required'] ?? 'no'
                ))),
                ['yes', '1', 'true'],
                true
            );

        if ($parentLoginRequired) {
            try {
                $parentLoginResult =
                    studentsGenerateParentLoginForStudent(
                        $pdo,
                        $scope,
                        $savedId
                    );

                if (
                    ($parentLoginResult['status'] ?? '') === 'created'
                ) {
                    $parentLoginCredential =
                        $parentLoginResult['credential'];
                } else {
                    $parentLoginWarning =
                        (string)(
                            $parentLoginResult['message']
                            ?? 'Parent Login was not generated.'
                        );
                }
            } catch (Throwable $parentLoginException) {
                /*
                 * The student is already safely saved at this point.
                 * Do not undo Student Admission because credential generation
                 * failed. Return a clear warning so the admin can generate it
                 * later from Generate Parent Login.
                 */
                $parentLoginWarning =
                    $parentLoginException->getMessage();

                error_log(
                    'Auto Parent Login generation failed for student '
                    . $savedId
                    . ': '
                    . $parentLoginException->getMessage()
                );
            }
        }

        $message = $studentId > 0
            ? 'Student updated successfully.'
            : 'Student created successfully.';

        if ($parentLoginCredential) {
            $message =
                'Student created and Parent Login generated successfully.';
        } elseif ($parentLoginRequired && $parentLoginWarning !== '') {
            $message .=
                ' Parent Login could not be generated automatically: '
                . $parentLoginWarning;
        }

        studentsJson(
            true,
            $message,
            [
                'id' => $savedId,
                'admission_number' => (string)(studentsGetRecord($pdo, $scope, $savedId)['admission_number'] ?? ''),
                'admission_number_mode' => strtolower((string)($input['admission_number_mode'] ?? 'auto')),
                'admission_number_prefix' => school_settings_prefix(
                    school_settings_get($pdo, $scope['tenant_id']),
                    'admission'
                ),
                'next_admission_number' => studentsPeekUniqueAdmissionNumber($pdo, $scope['tenant_id']),
                'parent_login_required' => $parentLoginRequired,
                'parent_login_credential' => $parentLoginCredential,
                'parent_login_warning' => $parentLoginWarning,
            ]
        );
    }

    if ($action === 'delete') {
        if (!studentsCan('delete')) {
            throw new RuntimeException('Permission denied.', 403);
        }

        $studentId = (int)($input['id'] ?? 0);
        if ($studentId <= 0) {
            throw new InvalidArgumentException('Invalid student ID.');
        }

        $old = studentsGetRecord($pdo, $scope, $studentId);
        if (!$old) {
            throw new RuntimeException('Student not found.', 404);
        }

        $pdo->beginTransaction();
        try {
            if (studentsColumnExists($pdo, 'students', 'deleted_at')) {
                $statement = $pdo->prepare(
                    "UPDATE students
                     SET status = 'inactive', deleted_at = CURRENT_TIMESTAMP
                     WHERE id = :id AND tenant_id = :tenant_id AND branch_id = :branch_id"
                );
            } else {
                $statement = $pdo->prepare(
                    "UPDATE students
                     SET status = 'inactive'
                     WHERE id = :id AND tenant_id = :tenant_id AND branch_id = :branch_id"
                );
            }
            $statement->execute([
                'id' => $studentId,
                'tenant_id' => $scope['tenant_id'],
                'branch_id' => $scope['branch_id'],
            ]);

            $pdo->prepare(
                "UPDATE student_enrollments
                 SET enrollment_status = 'completed'
                 WHERE student_id = :student_id
                   AND tenant_id = :tenant_id
                   AND branch_id = :branch_id"
            )->execute([
                'student_id' => $studentId,
                'tenant_id' => $scope['tenant_id'],
                'branch_id' => $scope['branch_id'],
            ]);

            studentsLog($pdo, $scope, 'delete', $studentId, 'Student archived.', $old, null);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        studentsJson(true, 'Student archived successfully.');
    }

    if ($action === 'import_file') {
        if (!studentsCan('import') && !studentsCan('add') && !studentsCan('create')) {
            throw new RuntimeException('Permission denied.', 403);
        }
        studentsCsrf($input);
        $file=$_FILES['import_file']??null;
        if(!is_array($file))throw new InvalidArgumentException('The uploaded import file was not received by PHP.');
        $uploadError=(int)($file['error']??UPLOAD_ERR_NO_FILE);
        if($uploadError!==UPLOAD_ERR_OK){
            $messages=[UPLOAD_ERR_INI_SIZE=>'The file exceeds upload_max_filesize.',UPLOAD_ERR_FORM_SIZE=>'The file exceeds the form upload limit.',UPLOAD_ERR_PARTIAL=>'The file was only partially uploaded.',UPLOAD_ERR_NO_FILE=>'Choose an Excel or CSV file.',UPLOAD_ERR_NO_TMP_DIR=>'The server temporary upload folder is missing.',UPLOAD_ERR_CANT_WRITE=>'The server could not write the uploaded file.',UPLOAD_ERR_EXTENSION=>'A PHP extension stopped the upload.'];
            throw new InvalidArgumentException($messages[$uploadError]??'The Excel/CSV upload failed.');
        }
        if((int)($file['size']??0)<=0)throw new InvalidArgumentException('The selected import file is empty.');
        if((int)($file['size']??0)>10*1024*1024)throw new InvalidArgumentException('Import file cannot exceed 10 MB.');
        $extension=strtolower(pathinfo((string)$file['name'],PATHINFO_EXTENSION));
        if(!in_array($extension,['xlsx','csv'],true))throw new InvalidArgumentException('Only .xlsx and .csv files are supported.');
        $rows=$extension==='xlsx'
            ? studentsReadXlsx((string)$file['tmp_name'])
            : studentsReadCsvFile((string)$file['tmp_name']);

        $fallbackValues = studentsImportFallbackValues($_POST);
        $missingColumns = studentsImportMissingColumns(
            $rows,
            $fallbackValues
        );

        if ($missingColumns) {
            studentsJson(
                false,
                'Missing required columns: ' . implode(', ', $missingColumns) . '.',
                [
                    'type' => 'missing_required_columns',
                    'missing_columns' => $missingColumns,
                    'supported_fallback_columns' => [
                        'academic_year_id',
                        'admission_date',
                    ],
                    'csrf_token' => function_exists('csrfToken')
                        ? csrfToken()
                        : '',
                ],
                422
            );
        }

        $result=studentsImportRows(
            $pdo,
            $scope,
            $rows,
            (string)($_POST['duplicate_mode']??'skip'),
            $fallbackValues
        );
        studentsJson(
            true,
            $result['created'].' created, '.$result['updated'].' updated, '.
            $result['skipped'].' skipped and '.$result['failed'].' failed.',
            [
            'created'=>$result['created'],
            'updated'=>$result['updated'],
            'skipped'=>$result['skipped'],
            'failed'=>$result['failed'],
            'errors'=>$result['errors'],
            'created_rows'=>$result['created_rows'],
            'updated_rows'=>$result['updated_rows'],
            'skipped_rows'=>$result['skipped_rows'],
            'failed_rows'=>$result['failed_rows'],
            'csrf_token'=>function_exists('csrfToken')?csrfToken():''
        ]);
    }

    if ($action === 'import_csv') {
        if (!studentsCan('import') && !studentsCan('add') && !studentsCan('create')) {
            throw new RuntimeException('Permission denied.', 403);
        }

        $csv = trim((string)($input['csv_text'] ?? ''));
        if ($csv === '') {
            throw new InvalidArgumentException('Paste CSV content first.');
        }

        $lines = preg_split('/\r\n|\r|\n/', $csv) ?: [];
        if (!$lines) {
            throw new InvalidArgumentException('CSV content is empty.');
        }

        $headerLine = array_shift($lines);
        $headers = array_map(
            static fn(string $value): string => strtolower(trim($value)),
            str_getcsv((string)$headerLine)
        );

        $created = 0;
        $errors = [];
        foreach ($lines as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            $values = str_getcsv($line);
            $values = array_pad($values, count($headers), '');
            $row = array_combine($headers, array_slice($values, 0, count($headers)));
            if (!is_array($row)) {
                $errors[] = 'Line ' . ($index + 2) . ': Invalid CSV structure.';
                continue;
            }

            try {
                $row['id'] = 0;
                $row['branch_id'] = $scope['branch_id'];
                studentsSave($pdo, $scope, $row);
                $created++;
            } catch (Throwable $exception) {
                $errors[] = 'Line ' . ($index + 2) . ': ' . $exception->getMessage();
            }
        }

        studentsJson(true, $created . ' students imported.', [
            'created' => $created,
            'errors' => $errors,
        ]);
    }

    studentsJson(false, 'Invalid students action.', [], 400);
} catch (InvalidArgumentException $exception) {
    studentsJson(false, $exception->getMessage(), [], 422);
} catch (RuntimeException $exception) {
    $status = (int)$exception->getCode();
    studentsJson(
        false,
        $exception->getMessage(),
        [],
        $status >= 400 && $status <= 599 ? $status : 403
    );
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log(
        'students.php: ' . $exception->getMessage()
        . ' in ' . $exception->getFile()
        . ':' . $exception->getLine()
    );

    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $message = str_contains($host, 'localhost') || str_contains($host, '127.0.0.1')
        ? 'Student request failed: ' . $exception->getMessage()
        : 'Unable to complete the student request.';

    studentsJson(false, $message, [], 500);
}

<?php
declare(strict_types=1);

/* Build: 2026-08-17-promotion-strict-branch-v9 */

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

if (!defined('SCHOOL_API_PAGE_KEY')) {
    define('SCHOOL_API_PAGE_KEY', 'student_promotion');
}

require_once dirname(__DIR__) . '/includes/bootstrap.php';

function promotionJson(
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

function promotionInput(): array
{
    $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));

    if (str_contains($contentType, 'application/json')) {
        $decoded = json_decode((string)file_get_contents('php://input'), true);
        return is_array($decoded) ? $decoded : [];
    }

    return $_POST;
}

function promotionScope(PDO $pdo): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $user = function_exists('current_user') ? current_user() : [];
    $user = is_array($user) ? $user : [];

    $tenantId = (int)(
        $user['tenant_id']
        ?? $user['school_id']
        ?? $_SESSION['tenant_id']
        ?? $_SESSION['school_id']
        ?? 0
    );

    $userId = (int)(
        $user['id']
        ?? $user['user_id']
        ?? $_SESSION['user_id']
        ?? 0
    );

    /*
     * Branch Settings stores the currently selected Branch in the runtime
     * context/session. That selected branch must be authoritative here.
     */
    $branchId = 0;

    if (function_exists('current_branch_id')) {
        try {
            $branchId = (int)current_branch_id();
        } catch (Throwable) {
            $branchId = 0;
        }
    }

    if ($branchId <= 0 && function_exists('branch_current_id')) {
        try {
            $branchId = (int)branch_current_id();
        } catch (Throwable) {
            $branchId = 0;
        }
    }

    if ($branchId <= 0) {
        $branchId = (int)(
            $_SESSION['branch_id']
            ?? $_SESSION['default_branch_id']
            ?? $user['branch_id']
            ?? $user['default_branch_id']
            ?? 0
        );
    }

    if (
        $branchId <= 0
        && $userId > 0
        && promotionTableExists($pdo, 'users')
        && promotionColumnExists($pdo, 'users', 'default_branch_id')
    ) {
        $statement = $pdo->prepare(
            "SELECT default_branch_id
             FROM users
             WHERE id = :user_id
               AND tenant_id = :tenant_id
             LIMIT 1"
        );
        $statement->execute([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
        ]);
        $branchId = (int)$statement->fetchColumn();
    }

    if (
        $branchId <= 0
        && $tenantId > 0
        && promotionTableExists($pdo, 'branches')
    ) {
        $statement = $pdo->prepare(
            "SELECT id
             FROM branches
             WHERE tenant_id = :tenant_id
               AND status = 'active'
             ORDER BY is_main DESC,id ASC
             LIMIT 1"
        );
        $statement->execute([
            'tenant_id' => $tenantId,
        ]);
        $branchId = (int)$statement->fetchColumn();
    }

    if ($tenantId <= 0) {
        throw new RuntimeException(
            'School tenant session was not found.',
            401
        );
    }

    if ($branchId <= 0) {
        throw new RuntimeException(
            'Active Branch is required for Student Promotion.',
            422
        );
    }

    if (promotionTableExists($pdo, 'branches')) {
        $statement = $pdo->prepare(
            "SELECT branch_name
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

        $branchName = (string)($statement->fetchColumn() ?: '');

        if ($branchName === '') {
            throw new RuntimeException(
                'The active Branch does not belong to this School.',
                403
            );
        }

        $_SESSION['branch_id'] = $branchId;
        $_SESSION['default_branch_id'] = $branchId;
        $_SESSION['branch_name'] = $branchName;
    }

    /*
     * Keep DB trigger context synchronized with the selected Branch.
     */
    try {
        $statement = $pdo->prepare(
            "SET @schoolerp_tenant_id = :tenant_id,
                 @schoolerp_branch_id = :branch_id"
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
        ]);
    } catch (Throwable) {
        /* Compatibility with installations without branch triggers. */
    }

    return [
        'tenant_id' => $tenantId,
        'branch_id' => $branchId,
        'user_id' => $userId,
    ];
}

function promotionCan(string $action): bool
{
    if (function_exists('is_super_admin') && is_super_admin()) {
        return true;
    }

    $aliases = [$action];
    if ($action === 'add') {
        $aliases[] = 'create';
    }
    if ($action === 'create') {
        $aliases[] = 'add';
    }

    if (function_exists('school_current_page_capabilities')) {
        $capabilities = school_current_page_capabilities('student_promotion');
        if (is_array($capabilities)) {
            foreach ($aliases as $alias) {
                if (!empty($capabilities[$alias])) {
                    return true;
                }
            }
        }
    }

    if (function_exists('has_permission')) {
        foreach (['student_promotion', 'student_management', 'students'] as $module) {
            foreach ($aliases as $alias) {
                if (has_permission($module, $alias)) {
                    return true;
                }
            }
        }
        return false;
    }

    return true;
}

function promotionCsrf(array $input): void
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
        promotionJson(false, 'Invalid or expired CSRF token. Refresh the page and try again.', [], 419);
    }
}

function promotionTableExists(PDO $pdo, string $table): bool
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

function promotionColumnExists(PDO $pdo, string $table, string $column): bool
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


/**
 * Return the table referenced by a foreign key on one column.
 *
 * The project has both the legacy Transport tables (transport_routes, etc.)
 * and the current School Transport tables (school_routes, school_route_stops,
 * school_vehicles).  A School Route ID must never be inserted into a column
 * whose FK still points at the legacy table, otherwise MySQL raises 1452.
 */
function promotionForeignKeyTarget(
    PDO $pdo,
    string $table,
    string $column
): ?string {
    static $cache = [];
    $key = strtolower($table . '.' . $column);

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $statement = $pdo->prepare(
        "SELECT REFERENCED_TABLE_NAME
         FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND COLUMN_NAME = :column_name
           AND REFERENCED_TABLE_NAME IS NOT NULL
         ORDER BY CONSTRAINT_NAME
         LIMIT 1"
    );
    $statement->execute([
        'table_name' => $table,
        'column_name' => $column,
    ]);

    $target = trim((string)($statement->fetchColumn() ?: ''));
    $cache[$key] = $target !== '' ? $target : null;

    return $cache[$key];
}

/**
 * Keep a current School Transport ID only when the destination column can
 * safely reference the expected current table. If the column still has a
 * legacy FK, return NULL rather than breaking Student Promotion.
 *
 * The human-readable route/stop names and the student's fixed transport fee
 * are still retained, so Fee Collection remains correct even when the legacy
 * compatibility ID column must be NULL.
 */
function promotionSafeTransportReference(
    PDO $pdo,
    string $table,
    string $column,
    int $value,
    string $expectedTable,
    int $tenantId
): ?int {
    if ($value <= 0 || !promotionColumnExists($pdo, $table, $column)) {
        return null;
    }

    $foreignTarget = promotionForeignKeyTarget($pdo, $table, $column);

    if (
        $foreignTarget !== null
        && strcasecmp($foreignTarget, $expectedTable) !== 0
    ) {
        return null;
    }

    if (!promotionTableExists($pdo, $expectedTable)) {
        return null;
    }

    $where = ['id = :id'];
    $params = ['id' => $value];

    if (promotionColumnExists($pdo, $expectedTable, 'tenant_id')) {
        $where[] = 'tenant_id = :tenant_id';
        $params['tenant_id'] = $tenantId;
    }

    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM `' .
        str_replace('`', '``', $expectedTable) .
        '` WHERE ' . implode(' AND ', $where)
    );
    $statement->execute($params);

    return (int)$statement->fetchColumn() > 0 ? $value : null;
}

function promotionRequireTables(PDO $pdo): void
{
    foreach (
        [
            'academic_years',
            'class_management_classes',
            'classes',
            'sections',
            'students',
            'student_enrollments',
        ] as $table
    ) {
        if (!promotionTableExists($pdo, $table)) {
            throw new RuntimeException('Missing required database table: ' . $table . '.', 500);
        }
    }
}

function promotionEnsureHistorySchema(PDO $pdo): void
{
    if (!promotionTableExists($pdo, 'class_student_promotions')) {
        $pdo->exec(
            "CREATE TABLE class_student_promotions(
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id BIGINT UNSIGNED NOT NULL,
                branch_id BIGINT UNSIGNED NULL,
                class_id BIGINT UNSIGNED NOT NULL,
                from_section_id BIGINT UNSIGNED NULL,
                student_id BIGINT UNSIGNED NULL,
                student_name VARCHAR(150) NOT NULL,
                from_academic_year_id BIGINT UNSIGNED NULL,
                to_academic_year_id BIGINT UNSIGNED NULL,
                to_class_id BIGINT UNSIGNED NULL,
                to_section_id BIGINT UNSIGNED NULL,
                from_class_name VARCHAR(150) NOT NULL,
                from_section_name VARCHAR(100) NULL,
                to_class_name VARCHAR(150) NOT NULL,
                to_section_name VARCHAR(100) NULL,
                promotion_date DATE NOT NULL,
                result_status ENUM('promoted','retained','pending') NOT NULL DEFAULT 'pending',
                remarks TEXT NULL,
                status ENUM('active','cancelled') NOT NULL DEFAULT 'active',
                created_by BIGINT UNSIGNED NULL,
                cancelled_by BIGINT UNSIGNED NULL,
                cancelled_at DATETIME NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY(id),
                KEY idx_promotion_student(tenant_id,student_id),
                KEY idx_promotion_years(tenant_id,from_academic_year_id,to_academic_year_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } else {
        $columns = [
            'branch_id' => "BIGINT UNSIGNED NULL AFTER tenant_id",
            'from_section_id' => "BIGINT UNSIGNED NULL AFTER class_id",
            'from_academic_year_id' => "BIGINT UNSIGNED NULL AFTER student_name",
            'to_academic_year_id' => "BIGINT UNSIGNED NULL AFTER from_academic_year_id",
            'to_class_id' => "BIGINT UNSIGNED NULL AFTER to_academic_year_id",
            'to_section_id' => "BIGINT UNSIGNED NULL AFTER to_class_id",
            'from_section_name' => "VARCHAR(100) NULL AFTER from_class_name",
            'to_section_name' => "VARCHAR(100) NULL AFTER to_class_name",
            'cancelled_by' => "BIGINT UNSIGNED NULL AFTER created_by",
            'cancelled_at' => "DATETIME NULL AFTER cancelled_by",
        ];

        foreach ($columns as $column => $definition) {
            if (!promotionColumnExists($pdo, 'class_student_promotions', $column)) {
                $pdo->exec(
                    "ALTER TABLE class_student_promotions
                     ADD COLUMN `{$column}` {$definition}"
                );
            }
        }
    }

    if (!promotionTableExists($pdo, 'student_promotion_logs')) {
        $pdo->exec(
            "CREATE TABLE student_promotion_logs(
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id BIGINT UNSIGNED NOT NULL,
                branch_id BIGINT UNSIGNED NULL,
                promotion_id BIGINT UNSIGNED NULL,
                student_id BIGINT UNSIGNED NULL,
                user_id BIGINT UNSIGNED NULL,
                action_name VARCHAR(80) NOT NULL,
                details JSON NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY(id),
                KEY idx_promotion_log(tenant_id,branch_id,student_id,created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}

function promotionLog(
    PDO $pdo,
    array $scope,
    string $action,
    ?int $promotionId,
    ?int $studentId,
    array $details = []
): void {
    if (!promotionTableExists($pdo, 'student_promotion_logs')) {
        return;
    }

    $statement = $pdo->prepare(
        "INSERT INTO student_promotion_logs(
            tenant_id,branch_id,promotion_id,student_id,user_id,action_name,details
         ) VALUES(
            :tenant_id,:branch_id,:promotion_id,:student_id,:user_id,:action_name,:details
         )"
    );
    $statement->execute([
        'tenant_id' => $scope['tenant_id'],
        'branch_id' => $scope['branch_id'] ?: null,
        'promotion_id' => $promotionId,
        'student_id' => $studentId,
        'user_id' => $scope['user_id'] ?: null,
        'action_name' => $action,
        'details' => json_encode(
            $details,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ),
    ]);
}

function promotionCurrentAcademicYearId(
    PDO $pdo,
    int $tenantId,
    int $branchId
): int {
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

function promotionFindYear(
    PDO $pdo,
    int $tenantId,
    int $branchId,
    int $yearId
): array {
    $statement = $pdo->prepare(
        "SELECT id,branch_id,year_name,start_date,end_date,is_current,status
         FROM academic_years
         WHERE id = :id
           AND tenant_id = :tenant_id
           AND branch_id = :branch_id
         LIMIT 1"
    );
    $statement->execute([
        'id' => $yearId,
        'tenant_id' => $tenantId,
        'branch_id' => $branchId,
    ]);

    $row = $statement->fetch(PDO::FETCH_ASSOC);

    if (!is_array($row)) {
        throw new InvalidArgumentException('Please select a valid Academic Year.');
    }

    return $row;
}

function promotionFindManagedClass(
    PDO $pdo,
    int $tenantId,
    int $branchId,
    int $managementId,
    ?int $academicYearId = null
): array {
    $where = [
        'id = :id',
        'tenant_id = :tenant_id',
        'branch_id = :branch_id',
    ];
    $params = [
        'id' => $managementId,
        'tenant_id' => $tenantId,
        'branch_id' => $branchId,
    ];

    if ($academicYearId !== null && $academicYearId > 0) {
        $where[] = 'academic_year_id = :academic_year_id';
        $params['academic_year_id'] = $academicYearId;
    }

    $statement = $pdo->prepare(
        "SELECT *
         FROM class_management_classes
         WHERE " . implode(' AND ', $where) . "
         LIMIT 1"
    );
    $statement->execute($params);

    $row = $statement->fetch(PDO::FETCH_ASSOC);

    if (!is_array($row)) {
        throw new InvalidArgumentException(
            'The selected Class / Section is not available in Class Management.'
        );
    }

    return $row;
}

function promotionSectionNameSql(bool $hasSchoolSections): string
{
    if ($hasSchoolSections) {
        return "COALESCE(
            NULLIF(CASE WHEN sec.class_id = e.class_id THEN sec.section_name END, ''),
            NULLIF(ss.section_name, ''),
            NULLIF(cmc.section_name, ''),
            NULLIF(sec.section_name, ''),
            'General'
        )";
    }

    return "COALESCE(
        NULLIF(CASE WHEN sec.class_id = e.class_id THEN sec.section_name END, ''),
        NULLIF(cmc.section_name, ''),
        NULLIF(sec.section_name, ''),
        'General'
    )";
}

function promotionStudentMatchSql(bool $hasSchoolSections): array
{
    $schoolSectionJoin = $hasSchoolSections
        ? "LEFT JOIN school_sections ss
              ON ss.id = e.section_id
             AND ss.tenant_id = e.tenant_id"
        : '';

    $sectionNameSql = promotionSectionNameSql($hasSchoolSections);

    $sql = "
        FROM student_enrollments e
        INNER JOIN students s
           ON s.id = e.student_id
          AND s.tenant_id = e.tenant_id
        LEFT JOIN classes c
           ON c.id = e.class_id
          AND c.tenant_id = e.tenant_id
        LEFT JOIN class_management_classes cmc
           ON cmc.id = e.class_id
          AND cmc.tenant_id = e.tenant_id
        LEFT JOIN sections sec
           ON sec.id = e.section_id
          AND sec.tenant_id = e.tenant_id
        {$schoolSectionJoin}
        WHERE e.tenant_id = :tenant_id
          AND e.academic_year_id = :academic_year_id
          AND (
                e.class_id = :management_id
                OR LOWER(TRIM(c.class_name)) = LOWER(TRIM(:class_name))
                OR LOWER(TRIM(cmc.class_name)) = LOWER(TRIM(:class_name_legacy))
              )
          AND LOWER(TRIM({$sectionNameSql}))
              = LOWER(TRIM(:section_name))
          AND e.enrollment_status = 'active'
          AND s.status = 'active'
          AND s.deleted_at IS NULL
          AND (
                :branch_scope = 0
                OR s.branch_id = :branch_value
                OR s.branch_id IS NULL
              )";

    return [$sql, $sectionNameSql];
}


function promotionResolveCanonicalManaged(
    PDO $pdo,
    int $tenantId,
    array $managed
): array {
    $branchId = (int)($managed['branch_id'] ?? 0);
    $yearId = (int)($managed['academic_year_id'] ?? 0);
    $className = trim((string)($managed['class_name'] ?? ''));
    $sectionName = trim((string)($managed['section_name'] ?? '')) ?: 'General';

    if (
        $tenantId <= 0
        || $branchId <= 0
        || $yearId <= 0
        || $className === ''
    ) {
        throw new InvalidArgumentException(
            'The selected Class / Section is invalid.'
        );
    }

    $classStatement = $pdo->prepare(
        "SELECT id
         FROM classes
         WHERE tenant_id = :tenant_id
           AND branch_id = :branch_id
           AND academic_year_id = :academic_year_id
           AND LOWER(TRIM(class_name)) = LOWER(TRIM(:class_name))
         LIMIT 1"
    );
    $classStatement->execute([
        'tenant_id' => $tenantId,
        'branch_id' => $branchId,
        'academic_year_id' => $yearId,
        'class_name' => $className,
    ]);

    $classId = (int)($classStatement->fetchColumn() ?: 0);

    if ($classId <= 0) {
        throw new InvalidArgumentException(
            'The selected class is not configured in the selected Academic Year.'
        );
    }

    $sectionStatement = $pdo->prepare(
        "SELECT id
         FROM sections
         WHERE tenant_id = :tenant_id
           AND branch_id = :branch_id
           AND class_id = :class_id
           AND LOWER(TRIM(section_name)) = LOWER(TRIM(:section_name))
         LIMIT 1"
    );
    $sectionStatement->execute([
        'tenant_id' => $tenantId,
        'branch_id' => $branchId,
        'class_id' => $classId,
        'section_name' => $sectionName,
    ]);

    $sectionId = (int)($sectionStatement->fetchColumn() ?: 0);

    if ($sectionId <= 0) {
        throw new InvalidArgumentException(
            'The selected section is not configured for this class and Academic Year.'
        );
    }

    return [
        'class_id' => $classId,
        'section_id' => $sectionId,
        'class_name' => $className,
        'section_name' => $sectionName,
        'academic_year_id' => $yearId,
    ];
}

function promotionFeeFrequencyLabel(string $frequency): string
{
    $frequency = strtolower(trim($frequency));

    switch ($frequency) {
        case 'monthly':
            return 'Monthly';
        case 'quarterly':
            return 'Quarterly';
        case 'half_yearly':
            return 'Half-Yearly';
        case 'yearly':
        case 'annual':
            return 'Annual';
        case 'term':
            return 'Term-wise';
        case 'custom':
            return 'Custom';
        default:
            return 'One Time';
    }
}

function promotionFeeStructure(
    PDO $pdo,
    int $tenantId,
    int $academicYearId,
    int $canonicalClassId,
    bool $required = true
): ?array {
    if (
        !promotionTableExists($pdo, 'fee_structures')
        || !promotionTableExists($pdo, 'fee_structure_items')
        || !promotionTableExists($pdo, 'academic_years')
    ) {
        if ($required) {
            throw new RuntimeException(
                'Fee Structure tables are unavailable. Configure Fee Structure before promotion.',
                500
            );
        }
        return null;
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
         WHERE fs.tenant_id = :tenant_id
           AND fs.academic_year_id = :academic_year_id
           AND fs.class_id = :class_id
           AND fs.status = 'active'
         ORDER BY fs.id DESC"
    );
    $statement->execute([
        'tenant_id' => $tenantId,
        'academic_year_id' => $academicYearId,
        'class_id' => $canonicalClassId,
    ]);
    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) > 1) {
        throw new InvalidArgumentException(
            'More than one Active Fee Structure exists for the selected Academic Year and Class. Keep only one Fee Structure active.'
        );
    }

    if (!$rows) {
        if ($required) {
            throw new InvalidArgumentException(
                'No Active Fee Structure exists for the selected To Academic Year and Class.'
            );
        }
        return null;
    }

    $structure = $rows[0];
    $structure['id'] = (int)$structure['id'];
    $structure['academic_year_id'] = (int)$structure['academic_year_id'];
    $structure['class_id'] = (int)$structure['class_id'];
    return $structure;
}

function promotionNormalizedOccurrences(
    string $frequency,
    int $occurrences
): int {
    $frequency = strtolower(trim($frequency));
    $occurrences = max(1, $occurrences);

    switch ($frequency) {
        case 'one_time':
            return 1;

        case 'monthly':
            return 12;

        case 'quarterly':
            return 4;

        case 'half_yearly':
            return 2;

        case 'yearly':
        case 'annual':
            return 1;

        default:
            /*
             * Term-wise and Custom use the configured occurrence count.
             */
            return $occurrences;
    }
}

function promotionFeeItems(
    PDO $pdo,
    int $tenantId,
    int $structureId
): array {
    $hasDynamicFeeTypes =
        promotionTableExists($pdo, 'fee_types')
        && promotionColumnExists(
            $pdo,
            'fee_structure_items',
            'fee_type_id'
        );

    if ($hasDynamicFeeTypes) {
        /*
         * IMPORTANT:
         * This query intentionally matches the corrected Fee Structure API.
         *
         * Only current Fee Structure rows are used:
         * - active fee_structure_items
         * - enabled Fee Types
         * - non-deleted Fee Types
         * - usage_scope structure/both
         *
         * Old duplicate rows can exist from previous versions. One current
         * row per Fee Type is kept, and the latest active row wins.
         */
        $statement = $pdo->prepare(
            "SELECT
                fsi.id,
                fsi.fee_type_id,
                LOWER(
                    COALESCE(ft.fee_type_code, '')
                ) AS fee_type_key,
                ft.fee_type_name AS fee_name,
                fsi.amount,
                COALESCE(
                    NULLIF(fsi.frequency, ''),
                    ft.default_frequency,
                    'one_time'
                ) AS frequency,
                GREATEST(
                    1,
                    COALESCE(
                        NULLIF(fsi.occurrence_count, 0),
                        NULLIF(ft.default_occurrence_count, 0),
                        1
                    )
                ) AS occurrence_count,
                fsi.status,
                COALESCE(
                    fsi.display_order,
                    ft.display_order,
                    0
                ) AS display_order
             FROM fee_structure_items fsi
             INNER JOIN fee_types ft
                ON ft.id = fsi.fee_type_id
               AND ft.tenant_id = :tenant_id
             WHERE fsi.fee_structure_id = :structure_id
               AND fsi.status = 'active'
               AND ft.deleted_at IS NULL
               AND ft.is_enabled = 1
               AND COALESCE(
                    ft.usage_scope,
                    'structure'
               ) IN ('structure','both')
             ORDER BY
                COALESCE(
                    fsi.display_order,
                    ft.display_order,
                    0
                ),
                fsi.fee_type_id,
                fsi.id"
        );

        $statement->execute([
            'tenant_id' => $tenantId,
            'structure_id' => $structureId,
        ]);

        $raw = $statement->fetchAll(PDO::FETCH_ASSOC);

        /*
         * Same de-duplication rule as Fee Structure:
         * one active row per fee_type_id, latest ID wins.
         */
        $deduped = [];

        foreach ($raw as $row) {
            $feeTypeId = (int)($row['fee_type_id'] ?? 0);

            if ($feeTypeId <= 0) {
                continue;
            }

            if (
                !isset($deduped[$feeTypeId])
                || (int)$row['id']
                    > (int)$deduped[$feeTypeId]['id']
            ) {
                $deduped[$feeTypeId] = $row;
            }
        }

        $rows = array_values($deduped);

        usort(
            $rows,
            static function (
                array $a,
                array $b
            ): int {
                $order =
                    (int)($a['display_order'] ?? 0)
                    <=>
                    (int)($b['display_order'] ?? 0);

                if ($order !== 0) {
                    return $order;
                }

                return
                    (int)$a['id']
                    <=>
                    (int)$b['id'];
            }
        );
    } else {
        /*
         * Legacy fallback for installations without fee_types.
         */
        $statement = $pdo->prepare(
            "SELECT
                fsi.id,
                NULL AS fee_type_id,
                LOWER(
                    COALESCE(
                        fsi.fee_type_key,
                        'fee'
                    )
                ) AS fee_type_key,
                COALESCE(
                    fh.head_name,
                    CONCAT(
                        'Fee Item ',
                        fsi.id
                    )
                ) AS fee_name,
                fsi.amount,
                COALESCE(
                    NULLIF(
                        fsi.frequency,
                        ''
                    ),
                    'one_time'
                ) AS frequency,
                GREATEST(
                    1,
                    COALESCE(
                        NULLIF(
                            fsi.occurrence_count,
                            0
                        ),
                        1
                    )
                ) AS occurrence_count,
                COALESCE(
                    fsi.status,
                    'active'
                ) AS status,
                COALESCE(
                    fsi.display_order,
                    0
                ) AS display_order
             FROM fee_structure_items fsi
             LEFT JOIN fee_heads fh
                ON fh.id = fsi.fee_head_id
             WHERE fsi.fee_structure_id = :structure_id
               AND COALESCE(
                    fsi.status,
                    'active'
               ) = 'active'
             ORDER BY
                COALESCE(
                    fsi.display_order,
                    0
                ),
                fsi.id"
        );

        $statement->execute([
            'structure_id' => $structureId,
        ]);

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    foreach ($rows as &$row) {
        $row['id'] =
            (int)($row['id'] ?? 0);

        $row['fee_type_id'] =
            isset($row['fee_type_id'])
                ? (int)$row['fee_type_id']
                : null;

        $row['amount'] = round(
            (float)($row['amount'] ?? 0),
            2
        );

        $row['frequency'] = strtolower(
            trim(
                (string)(
                    $row['frequency']
                    ?? 'one_time'
                )
            )
        );

        $row['occurrence_count'] =
            promotionNormalizedOccurrences(
                $row['frequency'],
                (int)(
                    $row['occurrence_count']
                    ?? 1
                )
            );

        $row['annual_amount'] = round(
            $row['amount']
            * $row['occurrence_count'],
            2
        );
    }
    unset($row);

    return $rows;
}

function promotionIsAdmissionFee(array $item): bool
{
    $key = strtolower(trim((string)($item['fee_type_key'] ?? '')));
    $name = strtolower(trim((string)($item['fee_name'] ?? '')));

    return str_contains($key, 'admission')
        || str_contains($name, 'admission');
}

function promotionFeePreview(
    PDO $pdo,
    int $tenantId,
    int $academicYearId,
    int $canonicalClassId
): ?array {
    $structure = promotionFeeStructure(
        $pdo,
        $tenantId,
        $academicYearId,
        $canonicalClassId,
        false
    );

    if (!$structure) {
        return null;
    }

    $items = promotionFeeItems(
        $pdo,
        $tenantId,
        (int)$structure['id']
    );

    $total = 0.0;
    $previewItems = [];

    foreach ($items as $item) {
        $amount = round(
            (float)($item['amount'] ?? 0),
            2
        );

        $occurrences = promotionNormalizedOccurrences(
            (string)(
                $item['frequency']
                ?? 'one_time'
            ),
            (int)(
                $item['occurrence_count']
                ?? 1
            )
        );

        if ($amount <= 0) {
            continue;
        }

        $annualAmount = round(
            (float)(
                $item['annual_amount']
                ?? ($amount * $occurrences)
            ),
            2
        );

        $total += $annualAmount;

        $previewItems[] = [
            'id' =>
                (int)$item['id'],
            'fee_type_id' =>
                (int)($item['fee_type_id'] ?? 0),
            'fee_type_key' =>
                (string)($item['fee_type_key'] ?? ''),
            'fee_name' =>
                (string)($item['fee_name'] ?? 'Fee'),
            'amount' =>
                $amount,
            'frequency' =>
                (string)($item['frequency'] ?? 'one_time'),
            'occurrence_count' =>
                $occurrences,
            'annual_amount' =>
                $annualAmount,
        ];
    }

    return [
        'id' => (int)$structure['id'],
        'structure_name' => (string)$structure['structure_name'],
        'annual_amount' => round($total, 2),
        'item_count' => count($previewItems),
        'items' => $previewItems,
    ];
}
function promotionFeeDueDates(
    string $startDate,
    string $endDate,
    string $frequency,
    int $occurrences
): array {
    $start = new DateTimeImmutable($startDate);
    $end = new DateTimeImmutable($endDate);
    $frequency = strtolower(trim($frequency));
    $occurrences = max(1, $occurrences);

    $dates = [];

    if ($frequency === 'monthly') {
        $cursor = $start;
        for ($i = 0; $i < $occurrences && $cursor <= $end; $i++) {
            $dates[] = $cursor->format('Y-m-d');
            $cursor = $cursor->modify('+1 month');
        }
    } elseif ($frequency === 'quarterly') {
        $cursor = $start;
        for ($i = 0; $i < $occurrences && $cursor <= $end; $i++) {
            $dates[] = $cursor->format('Y-m-d');
            $cursor = $cursor->modify('+3 months');
        }
    } elseif ($frequency === 'half_yearly') {
        $cursor = $start;
        for ($i = 0; $i < $occurrences && $cursor <= $end; $i++) {
            $dates[] = $cursor->format('Y-m-d');
            $cursor = $cursor->modify('+6 months');
        }
    } elseif ($frequency === 'term' || $frequency === 'custom') {
        if ($occurrences === 1) {
            $dates[] = $start->format('Y-m-d');
        } else {
            $days = max(1, (int)$start->diff($end)->format('%a'));
            for ($i = 0; $i < $occurrences; $i++) {
                $offset = (int)round(($days * $i) / $occurrences);
                $dates[] = $start->modify('+' . $offset . ' days')->format('Y-m-d');
            }
        }
    } else {
        $dates[] = $start->format('Y-m-d');
    }

    if (!$dates) {
        $dates[] = $start->format('Y-m-d');
    }

    while (count($dates) < $occurrences) {
        $dates[] = end($dates);
    }

    return array_slice($dates, 0, $occurrences);
}

function promotionFeeItemType(array $item): string
{
    $key = strtolower(
        trim(
            (string)(
                ($item['fee_type_key'] ?? '')
                . ' '
                . ($item['fee_name'] ?? '')
            )
        )
    );

    if (str_contains($key, 'admission')) {
        return 'admission';
    }
    if (str_contains($key, 'tuition')) {
        return 'tuition';
    }
    if (
        str_contains($key, 'exam')
        || str_contains($key, 'term')
    ) {
        return 'term';
    }
    return 'additional';
}


function promotionStudentTransport(
    PDO $pdo,
    int $tenantId,
    int $studentId,
    int $fromAcademicYearId
): ?array {
    /*
     * Primary source is the student's year-specific transport assignment.
     */
    if (promotionTableExists($pdo, 'student_transport_assignments')) {
        $statement = $pdo->prepare(
            "SELECT *
             FROM student_transport_assignments
             WHERE tenant_id = :tenant_id
               AND student_id = :student_id
               AND academic_year_id = :academic_year_id
               AND status = 'active'
               AND transport_required = 1
             ORDER BY id DESC
             LIMIT 1"
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'student_id' => $studentId,
            'academic_year_id' => $fromAcademicYearId,
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (is_array($row)) {
            $fee = round(
                (float)(
                    $row['transport_fee_amount']
                    ?? $row['bus_fee_amount']
                    ?? 0
                ),
                2
            );

            /*
             * When the same stop still exists, use the current configured
             * stop fee. This carries the student's route/stop forward while
             * respecting a newly updated Route Master fee.
             */
            $stopId = (int)($row['stop_id'] ?? 0);
            $routeId = (int)($row['route_id'] ?? 0);

            if (
                $stopId > 0
                && promotionTableExists($pdo, 'school_route_stops')
            ) {
                $stopStatement = $pdo->prepare(
                    "SELECT
                        s.id,
                        s.route_id,
                        s.stop_name,
                        COALESCE(s.transport_fee, 0) AS transport_fee
                     FROM school_route_stops s
                     WHERE s.id = :stop_id
                       AND s.tenant_id = :tenant_id
                       AND s.status = 'active'
                     LIMIT 1"
                );
                $stopStatement->execute([
                    'stop_id' => $stopId,
                    'tenant_id' => $tenantId,
                ]);
                $stop = $stopStatement->fetch(PDO::FETCH_ASSOC);

                if (is_array($stop)) {
                    $fee = round(
                        (float)($stop['transport_fee'] ?? $fee),
                        2
                    );

                    $row['stop_id'] = (int)$stop['id'];
                    $row['route_id'] = (int)$stop['route_id'];

                    if (trim((string)($stop['stop_name'] ?? '')) !== '') {
                        $row['boarding_stop_name'] =
                            (string)$stop['stop_name'];
                    }
                }
            }

            $row['transport_required'] = 1;
            $row['transport_fee_amount'] = max(0, $fee);
            $row['bus_fee_amount'] = max(0, $fee);

            return $row;
        }
    }

    /*
     * Compatibility fallback for older students which have transport only
     * on the fee assignment.
     */
    if (
        promotionTableExists($pdo, 'student_fee_assignments')
        && promotionColumnExists(
            $pdo,
            'student_fee_assignments',
            'transport_fee_amount'
        )
    ) {
        $statement = $pdo->prepare(
            "SELECT *
             FROM student_fee_assignments
             WHERE tenant_id = :tenant_id
               AND student_id = :student_id
               AND academic_year_id = :academic_year_id
               AND assignment_status = 'active'
               AND COALESCE(transport_fee_amount,0) > 0
             ORDER BY id DESC
             LIMIT 1"
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'student_id' => $studentId,
            'academic_year_id' => $fromAcademicYearId,
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (is_array($row)) {
            $routeId = (int)($row['transport_route_id'] ?? 0);
            $stopId = (int)($row['transport_stop_id'] ?? 0);
            $fee = round((float)$row['transport_fee_amount'], 2);
            $stopName = '';

            /*
             * Older fee assignments may contain the old yearly/monthly total
             * in transport_fee_amount.  When the Boarding Stop still exists,
             * always recover the CURRENT FIXED stop fee instead of carrying
             * that old aggregate amount into the promoted year.
             */
            if (
                $stopId > 0
                && promotionTableExists($pdo, 'school_route_stops')
            ) {
                $stopStatement = $pdo->prepare(
                    "SELECT id,route_id,stop_name,
                            COALESCE(transport_fee,0) AS transport_fee
                     FROM school_route_stops
                     WHERE id = :stop_id
                       AND tenant_id = :tenant_id
                       AND status = 'active'
                     LIMIT 1"
                );
                $stopStatement->execute([
                    'stop_id' => $stopId,
                    'tenant_id' => $tenantId,
                ]);
                $stop = $stopStatement->fetch(PDO::FETCH_ASSOC);

                if (is_array($stop)) {
                    $routeId = (int)($stop['route_id'] ?? $routeId);
                    $stopName = (string)($stop['stop_name'] ?? '');
                    $fee = round((float)($stop['transport_fee'] ?? $fee), 2);
                }
            }

            return [
                'route_id' => $routeId,
                'stop_id' => $stopId,
                'vehicle_id' => (int)($row['transport_vehicle_id'] ?? 0),
                'route_name' => '',
                'boarding_stop_name' => $stopName,
                'vehicle_name' => '',
                'driver_name' =>
                    (string)($row['transport_driver_name'] ?? ''),
                'transport_required' => 1,
                'transport_fee_amount' => max(0, $fee),
                'bus_fee_amount' => max(0, $fee),
            ];
        }
    }

    return null;
}

function promotionUpsertTargetTransport(
    PDO $pdo,
    int $tenantId,
    int $studentId,
    int $toAcademicYearId,
    ?array $transport,
    string $assignedOn
): void {
    if (!promotionTableExists($pdo, 'student_transport_assignments')) {
        return;
    }

    /*
     * A promoted student without transport still receives an explicit
     * no-transport row when the table supports it. This prevents an old route
     * from accidentally appearing as active in the target academic year.
     */
    $fee = $transport
        ? round((float)($transport['transport_fee_amount'] ?? 0), 2)
        : 0.0;

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

    $statement->execute([
        'tenant_id' => $tenantId,
        'student_id' => $studentId,
        'academic_year_id' => $toAcademicYearId,
        'route_id' => promotionSafeTransportReference(
            $pdo,
            'student_transport_assignments',
            'route_id',
            (int)($transport['route_id'] ?? 0),
            'school_routes',
            $tenantId
        ),
        'stop_id' => promotionSafeTransportReference(
            $pdo,
            'student_transport_assignments',
            'stop_id',
            (int)($transport['stop_id'] ?? 0),
            'school_route_stops',
            $tenantId
        ),
        'vehicle_id' => promotionSafeTransportReference(
            $pdo,
            'student_transport_assignments',
            'vehicle_id',
            (int)($transport['vehicle_id'] ?? 0),
            'school_vehicles',
            $tenantId
        ),
        'route_name' =>
            $transport
                ? (string)($transport['route_name'] ?? '')
                : null,
        'boarding_stop_name' =>
            $transport
                ? (string)($transport['boarding_stop_name'] ?? '')
                : null,
        'vehicle_name' =>
            $transport
                ? (string)($transport['vehicle_name'] ?? '')
                : null,
        'driver_name' =>
            $transport
                ? (string)($transport['driver_name'] ?? '')
                : null,
        'transport_required' => $transport ? 1 : 0,
        'transport_fee_amount' => $fee,
        'bus_fee_amount' => $fee,
        'assigned_on' => $assignedOn,
    ]);
}

function promotionTransportPeriods(
    string $startDate,
    string $endDate
): array {
    /*
     * Transport is a FIXED student fee, not a monthly fee.
     *
     * Keep this helper because the promotion schedule code calls it, but
     * deliberately return exactly one period.  The configured boarding-stop
     * transport fee is therefore inserted once and is never multiplied by
     * the number of months in the Academic Year.
     */
    $start = new DateTimeImmutable($startDate);

    return [[
        'key' => 'FIXED',
        'label' => 'Fixed',
        'due_date' => $start->format('Y-m-d'),
    ]];
}

function promotionAggregateFeeAssignment(
    PDO $pdo,
    int $tenantId,
    int $assignmentId,
    ?array $transport = null
): void {
    $statement = $pdo->prepare(
        "SELECT
            COALESCE(SUM(original_amount),0) AS gross_amount,
            COALESCE(SUM(discount_amount),0) AS discount_amount,
            COALESCE(SUM(paid_amount),0) AS paid_amount,
            COALESCE(SUM(balance_amount),0) AS balance_amount,
            COALESCE(
                SUM(
                    CASE
                        WHEN item_type NOT IN ('transport','previous_due')
                        THEN original_amount
                        ELSE 0
                    END
                ),
                0
            ) AS base_fee_amount,
            COALESCE(
                SUM(
                    CASE
                        WHEN item_type = 'transport'
                        THEN original_amount
                        ELSE 0
                    END
                ),
                0
            ) AS transport_fee_amount
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

    $gross = round((float)($totals['gross_amount'] ?? 0), 2);
    $discount = round((float)($totals['discount_amount'] ?? 0), 2);
    $paid = round((float)($totals['paid_amount'] ?? 0), 2);
    $balance = round((float)($totals['balance_amount'] ?? 0), 2);
    $baseFee = round((float)($totals['base_fee_amount'] ?? 0), 2);
    $transportFee = round(
        (float)($totals['transport_fee_amount'] ?? 0),
        2
    );

    $net = max(0, round($gross - $discount, 2));

    $status = 'unpaid';
    if ($balance <= 0.009 && $net > 0) {
        $status = 'paid';
    } elseif ($paid > 0.009) {
        $status = 'partial';
    }

    $update = $pdo->prepare(
        "UPDATE student_fee_assignments
         SET base_fee_amount = :base_fee,
             transport_fee_amount = :transport_fee,
             transport_route_id = :route_id,
             transport_stop_id = :stop_id,
             transport_vehicle_id = :vehicle_id,
             transport_driver_name = :driver_name,
             gross_amount = :gross_amount,
             concession_amount = :discount_amount,
             net_amount = :net_amount,
             paid_amount = :paid_amount,
             balance_amount = :balance_amount,
             payment_status = :payment_status,
             schedule_generated_at = NOW()
         WHERE id = :id
           AND tenant_id = :tenant_id"
    );

    $update->execute([
        'base_fee' => $baseFee,
        'transport_fee' => $transportFee,
        'route_id' => promotionSafeTransportReference(
            $pdo,
            'student_fee_assignments',
            'transport_route_id',
            (int)($transport['route_id'] ?? 0),
            'school_routes',
            $tenantId
        ),
        'stop_id' => promotionSafeTransportReference(
            $pdo,
            'student_fee_assignments',
            'transport_stop_id',
            (int)($transport['stop_id'] ?? 0),
            'school_route_stops',
            $tenantId
        ),
        'vehicle_id' => promotionSafeTransportReference(
            $pdo,
            'student_fee_assignments',
            'transport_vehicle_id',
            (int)($transport['vehicle_id'] ?? 0),
            'school_vehicles',
            $tenantId
        ),
        'driver_name' =>
            $transport
                ? (string)($transport['driver_name'] ?? '')
                : null,
        'gross_amount' => $gross,
        'discount_amount' => $discount,
        'net_amount' => $net,
        'paid_amount' => $paid,
        'balance_amount' => $balance,
        'payment_status' => $status,
        'id' => $assignmentId,
        'tenant_id' => $tenantId,
    ]);
}
function promotionEnsureCarryForwardSchema(PDO $pdo): void
{
    if (!promotionTableExists($pdo, 'student_fee_items')) {
        return;
    }

    $freshColumnExists=static function(
        PDO $pdo,
        string $column
    ): bool {
        $statement=$pdo->prepare(
            "SELECT COUNT(*)
             FROM information_schema.columns
             WHERE table_schema=DATABASE()
               AND table_name='student_fee_items'
               AND column_name=:column_name"
        );
        $statement->execute(['column_name'=>$column]);
        return (int)$statement->fetchColumn()>0;
    };

    foreach ([
        'source_academic_year_id' => 'BIGINT UNSIGNED NULL AFTER academic_year_id',
        'source_student_fee_item_id' => 'BIGINT UNSIGNED NULL AFTER source_academic_year_id',
        'carried_forward_at' => 'DATETIME NULL AFTER source_student_fee_item_id',
    ] as $column => $definition) {
        if (!$freshColumnExists($pdo,$column)) {
            $pdo->exec(
                "ALTER TABLE student_fee_items ADD COLUMN `{$column}` {$definition}"
            );
        }
    }

    $s=$pdo->query(
        "SELECT COLUMN_TYPE FROM information_schema.columns
         WHERE table_schema=DATABASE()
           AND table_name='student_fee_items'
           AND column_name='item_type'
         LIMIT 1"
    );
    $itemType=strtolower((string)$s->fetchColumn());
    if ($itemType!=='' && !str_contains($itemType, "'previous_due'")) {
        $pdo->exec(
            "ALTER TABLE student_fee_items
             MODIFY COLUMN item_type
             ENUM('admission','tuition','term','transport','additional','previous_due')
             NOT NULL"
        );
    }

    $s=$pdo->query(
        "SELECT COLUMN_TYPE FROM information_schema.columns
         WHERE table_schema=DATABASE()
           AND table_name='student_fee_items'
           AND column_name='item_status'
         LIMIT 1"
    );
    $itemStatus=strtolower((string)$s->fetchColumn());
    if ($itemStatus!=='' && !str_contains($itemStatus, "'carried_forward'")) {
        $pdo->exec(
            "ALTER TABLE student_fee_items
             MODIFY COLUMN item_status
             ENUM('unpaid','partial','paid','waived','cancelled','carried_forward')
             NOT NULL DEFAULT 'unpaid'"
        );
    }
}
function promotionPreviousYearDueRows(
    PDO $pdo,
    int $tenantId,
    int $studentId,
    int $fromAcademicYearId
): array {
    if (
        !promotionTableExists($pdo, 'student_fee_assignments')
        || !promotionTableExists($pdo, 'student_fee_items')
    ) {
        return [];
    }

    promotionEnsureCarryForwardSchema($pdo);

    $assignment=$pdo->prepare(
        "SELECT
            a.id,
            a.balance_amount,
            a.gross_amount,
            a.paid_amount,
            COALESCE(NULLIF(fs.structure_name,''),'Fee Structure') structure_name,
            ay.year_name source_academic_year_name
         FROM student_fee_assignments a
         LEFT JOIN fee_structures fs
           ON fs.id=a.fee_structure_id
          AND fs.tenant_id=a.tenant_id
         LEFT JOIN academic_years ay
           ON ay.id=a.academic_year_id
          AND ay.tenant_id=a.tenant_id
         WHERE a.tenant_id=:tenant_id
           AND a.student_id=:student_id
           AND a.academic_year_id=:academic_year_id
           AND a.assignment_status='active'
         ORDER BY a.id DESC
         LIMIT 1"
    );
    $assignment->execute([
        'tenant_id'=>$tenantId,
        'student_id'=>$studentId,
        'academic_year_id'=>$fromAcademicYearId,
    ]);
    $assignmentRow=$assignment->fetch(PDO::FETCH_ASSOC);

    if (!is_array($assignmentRow)) {
        return [];
    }

    $assignmentId=(int)$assignmentRow['id'];

    $statement=$pdo->prepare(
        "SELECT
            sfi.id,
            sfi.assignment_id,
            sfi.item_type,
            sfi.item_name,
            sfi.period_key,
            sfi.period_label,
            sfi.due_date,
            sfi.original_amount,
            sfi.discount_amount,
            sfi.paid_amount,
            sfi.balance_amount,
            sfi.item_status,
            ay.year_name AS source_academic_year_name
         FROM student_fee_items sfi
         INNER JOIN academic_years ay
            ON ay.id=sfi.academic_year_id
           AND ay.tenant_id=sfi.tenant_id
         WHERE sfi.tenant_id=:tenant_id
           AND sfi.student_id=:student_id
           AND sfi.assignment_id=:assignment_id
           AND sfi.academic_year_id=:academic_year_id
           AND sfi.balance_amount>0.009
           AND sfi.item_status IN('unpaid','partial')
         ORDER BY sfi.due_date,sfi.id"
    );
    $statement->execute([
        'tenant_id'=>$tenantId,
        'student_id'=>$studentId,
        'assignment_id'=>$assignmentId,
        'academic_year_id'=>$fromAcademicYearId,
    ]);
    $rows=$statement->fetchAll(PDO::FETCH_ASSOC);

    if ($rows) {
        return $rows;
    }

    /*
     * Legacy fallback:
     * Some old fee assignments contain only assignment.balance_amount and no
     * student_fee_items schedule. Still carry the true old outstanding amount.
     */
    $legacyBalance=round(
        (float)($assignmentRow['balance_amount']??0),
        2
    );

    if ($legacyBalance<=0.009) {
        return [];
    }

    return [[
        'id'=>0,
        'assignment_id'=>$assignmentId,
        'item_type'=>'additional',
        'item_name'=>(string)($assignmentRow['structure_name']??'Outstanding Fee'),
        'period_key'=>'ASSIGNMENT-'.$assignmentId,
        'period_label'=>'Outstanding Balance',
        'due_date'=>date('Y-m-d'),
        'original_amount'=>$legacyBalance,
        'discount_amount'=>0,
        'paid_amount'=>0,
        'balance_amount'=>$legacyBalance,
        'item_status'=>'unpaid',
        'source_academic_year_name'=>(string)(
            $assignmentRow['source_academic_year_name']??''
        ),
    ]];
}
function promotionPreviousYearPending(
    PDO $pdo,
    int $tenantId,
    int $studentId,
    int $fromAcademicYearId
): float {
    $total=0.0;
    foreach (promotionPreviousYearDueRows(
        $pdo,$tenantId,$studentId,$fromAcademicYearId
    ) as $row) {
        $total+=(float)($row['balance_amount']??0);
    }
    return round($total,2);
}

function promotionCarryForwardPreviousDues(
    PDO $pdo,
    array $scope,
    int $studentId,
    int $fromAcademicYearId,
    int $targetAcademicYearId,
    int $targetAssignmentId,
    string $assignmentDate
): array {
    promotionEnsureCarryForwardSchema($pdo);

    $rows=promotionPreviousYearDueRows(
        $pdo,
        (int)$scope['tenant_id'],
        $studentId,
        $fromAcademicYearId
    );

    if (!$rows) {
        return ['amount'=>0.0,'items'=>0];
    }

    $insert=$pdo->prepare(
        "INSERT INTO student_fee_items(
            tenant_id,assignment_id,student_id,academic_year_id,
            source_academic_year_id,source_student_fee_item_id,
            fee_structure_item_id,transport_route_id,transport_stop_id,
            item_type,item_name,period_key,period_label,due_date,
            original_amount,discount_amount,paid_amount,balance_amount,item_status
         ) VALUES(
            :tenant_id,:assignment_id,:student_id,:academic_year_id,
            :source_academic_year_id,:source_student_fee_item_id,
            NULL,NULL,NULL,
            'previous_due',:item_name,:period_key,:period_label,:due_date,
            :amount,0,0,:balance,'unpaid'
         )
         ON DUPLICATE KEY UPDATE
            item_name=VALUES(item_name),
            period_label=VALUES(period_label),
            due_date=VALUES(due_date),
            source_academic_year_id=VALUES(source_academic_year_id),
            source_student_fee_item_id=VALUES(source_student_fee_item_id),
            original_amount=CASE
                WHEN paid_amount<=0.009 THEN VALUES(original_amount)
                ELSE original_amount
            END,
            balance_amount=CASE
                WHEN paid_amount<=0.009 THEN VALUES(balance_amount)
                ELSE balance_amount
            END"
    );

    $markSource=$pdo->prepare(
        "UPDATE student_fee_items
         SET balance_amount=0,
             item_status='carried_forward',
             carried_forward_at=NOW()
         WHERE id=:id
           AND tenant_id=:tenant_id
           AND student_id=:student_id
           AND academic_year_id=:academic_year_id
           AND balance_amount>0.009
           AND item_status IN('unpaid','partial')"
    );

    $total=0.0;
    $count=0;

    foreach ($rows as $row) {
        $amount=round((float)($row['balance_amount']??0),2);
        if ($amount<=0.009) {
            continue;
        }

        $yearName=trim((string)($row['source_academic_year_name']??''));
        $sourceName=trim((string)($row['item_name']??'Fee'));
        $sourcePeriod=trim((string)($row['period_label']??''));

        $insert->execute([
            'tenant_id'=>$scope['tenant_id'],
            'assignment_id'=>$targetAssignmentId,
            'student_id'=>$studentId,
            'academic_year_id'=>$targetAcademicYearId,
            'source_academic_year_id'=>$fromAcademicYearId,
            'source_student_fee_item_id'=>(int)$row['id']>0?(int)$row['id']:null,
            'item_name'=>'Previous Year Pending - '.($sourceName!==''?$sourceName:'Fee'),
            'period_key'=>(int)$row['id']>0
                ? 'CF-'.$fromAcademicYearId.'-'.(int)$row['id']
                : 'CF-ASSIGNMENT-'.$fromAcademicYearId.'-'.(int)$row['assignment_id'],
            'period_label'=>($yearName!==''?$yearName:'Previous Academic Year')
                .($sourcePeriod!==''?' • '.$sourcePeriod:''),
            'due_date'=>$assignmentDate,
            'amount'=>$amount,
            'balance'=>$amount,
        ]);

        if((int)$row['id']>0){
            $markSource->execute([
                'id'=>(int)$row['id'],
                'tenant_id'=>$scope['tenant_id'],
                'student_id'=>$studentId,
                'academic_year_id'=>$fromAcademicYearId,
            ]);
        }

        $total+=$amount;
        $count++;
    }

    if($count>0){
        $sourceAssignmentId=(int)($rows[0]['assignment_id']??0);
        if($sourceAssignmentId>0){
            $remainingStmt=$pdo->prepare(
                "SELECT COALESCE(SUM(balance_amount),0)
                 FROM student_fee_items
                 WHERE tenant_id=:tenant_id
                   AND assignment_id=:assignment_id
                   AND item_status NOT IN('cancelled','carried_forward')"
            );
            $remainingStmt->execute([
                'tenant_id'=>$scope['tenant_id'],
                'assignment_id'=>$sourceAssignmentId,
            ]);
            $remaining=round((float)$remainingStmt->fetchColumn(),2);

            $sourcePaidStmt=$pdo->prepare(
                "SELECT COALESCE(paid_amount,0)
                 FROM student_fee_assignments
                 WHERE id=:id AND tenant_id=:tenant_id LIMIT 1"
            );
            $sourcePaidStmt->execute([
                'id'=>$sourceAssignmentId,
                'tenant_id'=>$scope['tenant_id'],
            ]);
            $sourcePaid=(float)$sourcePaidStmt->fetchColumn();

            $sourceStatus=$remaining<=0.009
                ? 'paid'
                : ($sourcePaid>0.009?'partial':'unpaid');

            $pdo->prepare(
                "UPDATE student_fee_assignments
                 SET balance_amount=:balance,
                     payment_status=:payment_status
                 WHERE id=:id AND tenant_id=:tenant_id"
            )->execute([
                'balance'=>$remaining,
                'payment_status'=>$sourceStatus,
                'id'=>$sourceAssignmentId,
                'tenant_id'=>$scope['tenant_id'],
            ]);
        }
    }

    return ['amount'=>round($total,2),'items'=>$count];
}

function promotionAssignTargetFees(
    PDO $pdo,
    array $scope,
    int $studentId,
    int $fromAcademicYearId,
    int $academicYearId,
    int $canonicalClassId,
    string $assignmentDate
): array {
    if (
        !promotionTableExists($pdo, 'student_fee_assignments')
        || !promotionTableExists($pdo, 'student_fee_items')
    ) {
        throw new RuntimeException(
            'Student Fee assignment tables are unavailable. Configure the Fee module before promotion.',
            500
        );
    }


    promotionEnsureCarryForwardSchema($pdo);

    $structure = promotionFeeStructure(
        $pdo,
        (int)$scope['tenant_id'],
        $academicYearId,
        $canonicalClassId,
        true
    );

    $items = promotionFeeItems(
        $pdo,
        (int)$scope['tenant_id'],
        (int)$structure['id']
    );

    $transport = promotionStudentTransport(
        $pdo,
        (int)$scope['tenant_id'],
        $studentId,
        $fromAcademicYearId
    );

    promotionUpsertTargetTransport(
        $pdo,
        (int)$scope['tenant_id'],
        $studentId,
        $academicYearId,
        $transport,
        $assignmentDate
    );

    $existing = $pdo->prepare(
        "SELECT *
         FROM student_fee_assignments
         WHERE tenant_id = :tenant_id
           AND student_id = :student_id
           AND academic_year_id = :academic_year_id
         ORDER BY assignment_status = 'active' DESC, id DESC
         LIMIT 1
         FOR UPDATE"
    );
    $existing->execute([
        'tenant_id' => $scope['tenant_id'],
        'student_id' => $studentId,
        'academic_year_id' => $academicYearId,
    ]);
    $assignment = $existing->fetch(PDO::FETCH_ASSOC);

    if (
        is_array($assignment)
        && (float)($assignment['paid_amount'] ?? 0) > 0.009
        && (int)($assignment['fee_structure_id'] ?? 0) !== (int)$structure['id']
    ) {
        throw new InvalidArgumentException(
            'The target Academic Year already has fee collection. Its Fee Structure cannot be changed.'
        );
    }

    if (is_array($assignment)) {
        $assignmentId = (int)$assignment['id'];

        $update = $pdo->prepare(
            "UPDATE student_fee_assignments
             SET fee_structure_id = :fee_structure_id,
                 assignment_date = :assignment_date,
                 assignment_status = 'active',
                 is_new_admission = 0
             WHERE id = :id
               AND tenant_id = :tenant_id
               AND branch_id = :branch_id"
        );
        $update->execute([
            'fee_structure_id' => $structure['id'],
            'assignment_date' => $assignmentDate,
            'id' => $assignmentId,
            'tenant_id' => $scope['tenant_id'],
        ]);

        if ((float)($assignment['paid_amount'] ?? 0) <= 0.009) {
            $deleteItems = $pdo->prepare(
                "DELETE FROM student_fee_items
                 WHERE tenant_id = :tenant_id
                   AND assignment_id = :assignment_id
                   AND paid_amount <= 0.009
                   AND item_type <> 'previous_due'"
            );
            $deleteItems->execute([
                'tenant_id' => $scope['tenant_id'],
                'assignment_id' => $assignmentId,
            ]);
        } else {
            $carryForward = promotionCarryForwardPreviousDues(
                $pdo,
                $scope,
                $studentId,
                $fromAcademicYearId,
                $academicYearId,
                $assignmentId,
                $assignmentDate
            );

            promotionAggregateFeeAssignment(
                $pdo,
                (int)$scope['tenant_id'],
                $assignmentId,
                $transport
            );

            $freshAssignment = $pdo->prepare(
                "SELECT * FROM student_fee_assignments
                 WHERE id=:id AND tenant_id=:tenant_id LIMIT 1"
            );
            $freshAssignment->execute([
                'id'=>$assignmentId,
                'tenant_id'=>$scope['tenant_id'],
            ]);
            $fresh=$freshAssignment->fetch(PDO::FETCH_ASSOC) ?: $assignment;

            return [
                'assignment_id'=>$assignmentId,
                'fee_structure_id'=>(int)$structure['id'],
                'fee_structure_name'=>(string)$structure['structure_name'],
                'base_fee_amount'=>round((float)($fresh['base_fee_amount']??0),2),
                'transport_fee_amount'=>round((float)($fresh['transport_fee_amount']??0),2),
                'previous_year_pending'=>round((float)($carryForward['amount']??0),2),
                'amount'=>round((float)($fresh['net_amount']??0),2),
                'balance_amount'=>round((float)($fresh['balance_amount']??0),2),
                'has_transport'=>$transport!==null,
                'schedule_preserved'=>true,
            ];
        }
    } else {
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
                :fee_structure_id,
                :assignment_date,
                'active',
                0,
                0,
                0,
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
            'fee_structure_id' => $structure['id'],
            'assignment_date' => $assignmentDate,
        ]);
        $assignmentId = (int)$pdo->lastInsertId();
    }

    $insertItem = $pdo->prepare(
        "INSERT INTO student_fee_items(
            tenant_id,
            assignment_id,
            student_id,
            academic_year_id,
            fee_structure_item_id,
            transport_route_id,
            transport_stop_id,
            item_type,
            item_name,
            period_key,
            period_label,
            due_date,
            original_amount,
            discount_amount,
            paid_amount,
            balance_amount,
            item_status
         ) VALUES(
            :tenant_id,
            :assignment_id,
            :student_id,
            :academic_year_id,
            :fee_structure_item_id,
            NULL,
            NULL,
            :item_type,
            :item_name,
            :period_key,
            :period_label,
            :due_date,
            :original_amount,
            0,
            0,
            :balance_amount,
            'unpaid'
         )
         ON DUPLICATE KEY UPDATE
            item_name = VALUES(item_name),
            due_date = VALUES(due_date),
            original_amount = VALUES(original_amount),
            balance_amount = CASE
                WHEN paid_amount <= 0.009
                THEN VALUES(balance_amount)
                ELSE balance_amount
            END"
    );

    $assignedAmount = 0.0;

    foreach ($items as $item) {
        /*
         * All active Fee Structure items are assigned during promotion:
         * Tuition, Admission, Exam, Books, Library and any fee added later.
         */
        $amount = round((float)$item['amount'], 2);
        if ($amount <= 0) {
            continue;
        }

        $occurrences = promotionNormalizedOccurrences(
            (string)(
                $item['frequency']
                ?? 'one_time'
            ),
            (int)(
                $item['occurrence_count']
                ?? 1
            )
        );
        $dates = promotionFeeDueDates(
            (string)$structure['start_date'],
            (string)$structure['end_date'],
            (string)$item['frequency'],
            $occurrences
        );

        for ($index = 0; $index < $occurrences; $index++) {
            $number = $index + 1;
            $frequencyLabel = promotionFeeFrequencyLabel(
                (string)$item['frequency']
            );

            $periodLabel = $occurrences === 1
                ? $frequencyLabel
                : $frequencyLabel . ' ' . $number;

            $periodKey =
                'FSI-' . (int)$item['id']
                . '-' . strtoupper(
                    preg_replace(
                        '/[^A-Z0-9]+/i',
                        '-',
                        (string)$item['frequency']
                    ) ?: 'ONE-TIME'
                )
                . '-' . $number;

            $insertItem->execute([
                'tenant_id' => $scope['tenant_id'],
                'assignment_id' => $assignmentId,
                'student_id' => $studentId,
                'academic_year_id' => $academicYearId,
                'fee_structure_item_id' => (int)$item['id'],
                'item_type' => promotionFeeItemType($item),
                'item_name' => (string)$item['fee_name'],
                'period_key' => $periodKey,
                'period_label' => $periodLabel,
                'due_date' => $dates[$index] ?? (string)$structure['start_date'],
                'original_amount' => $amount,
                'balance_amount' => $amount,
            ]);

            $assignedAmount += $amount;
        }
    }

    $transportAssignedAmount = 0.0;

    if (
        $transport
        && (float)($transport['transport_fee_amount'] ?? 0) > 0
    ) {
        $transportAmount = round(
            (float)$transport['transport_fee_amount'],
            2
        );

        $transportItem = $pdo->prepare(
            "INSERT INTO student_fee_items(
                tenant_id,
                assignment_id,
                student_id,
                academic_year_id,
                fee_structure_item_id,
                transport_route_id,
                transport_stop_id,
                item_type,
                item_name,
                period_key,
                period_label,
                due_date,
                original_amount,
                discount_amount,
                paid_amount,
                balance_amount,
                item_status
             ) VALUES(
                :tenant_id,
                :assignment_id,
                :student_id,
                :academic_year_id,
                NULL,
                :transport_route_id,
                :transport_stop_id,
                'transport',
                :item_name,
                :period_key,
                :period_label,
                :due_date,
                :amount,
                0,
                0,
                :balance,
                'unpaid'
             )
             ON DUPLICATE KEY UPDATE
                item_name = VALUES(item_name),
                transport_route_id = VALUES(transport_route_id),
                transport_stop_id = VALUES(transport_stop_id),
                due_date = VALUES(due_date),
                original_amount = VALUES(original_amount),
                balance_amount = CASE
                    WHEN paid_amount <= 0.009
                    THEN VALUES(balance_amount)
                    ELSE balance_amount
                END"
        );

        $routeName = trim((string)($transport['route_name'] ?? ''));
        $stopName = trim(
            (string)($transport['boarding_stop_name'] ?? '')
        );

        $transportName = 'Transport Fee';
        if ($routeName !== '') {
            $transportName .= ' - ' . $routeName;
        }
        if ($stopName !== '') {
            $transportName .= ' / ' . $stopName;
        }

        /* Exactly one BUS-FIXED row is generated for transport. */
        foreach (
            promotionTransportPeriods(
                (string)$structure['start_date'],
                (string)$structure['end_date']
            ) as $period
        ) {
            $transportItem->execute([
                'tenant_id' => $scope['tenant_id'],
                'assignment_id' => $assignmentId,
                'student_id' => $studentId,
                'academic_year_id' => $academicYearId,
                'transport_route_id' => promotionSafeTransportReference(
                    $pdo,
                    'student_fee_items',
                    'transport_route_id',
                    (int)($transport['route_id'] ?? 0),
                    'school_routes',
                    (int)$scope['tenant_id']
                ),
                'transport_stop_id' => promotionSafeTransportReference(
                    $pdo,
                    'student_fee_items',
                    'transport_stop_id',
                    (int)($transport['stop_id'] ?? 0),
                    'school_route_stops',
                    (int)$scope['tenant_id']
                ),
                'item_name' => $transportName,
                'period_key' => 'BUS-' . $period['key'],
                'period_label' => $period['label'],
                'due_date' => $period['due_date'],
                'amount' => $transportAmount,
                'balance' => $transportAmount,
            ]);

            $transportAssignedAmount += $transportAmount;
        }
    }

    $carryForward=promotionCarryForwardPreviousDues(
        $pdo,
        $scope,
        $studentId,
        $fromAcademicYearId,
        $academicYearId,
        $assignmentId,
        $assignmentDate
    );

    promotionAggregateFeeAssignment(
        $pdo,
        (int)$scope['tenant_id'],
        $assignmentId,
        $transport
    );

    $freshAssignment=$pdo->prepare(
        "SELECT * FROM student_fee_assignments
         WHERE id=:id AND tenant_id=:tenant_id LIMIT 1"
    );
    $freshAssignment->execute([
        'id'=>$assignmentId,
        'tenant_id'=>$scope['tenant_id'],
    ]);
    $fresh=$freshAssignment->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'assignment_id'=>$assignmentId,
        'fee_structure_id'=>(int)$structure['id'],
        'fee_structure_name'=>(string)$structure['structure_name'],
        'base_fee_amount'=>round($assignedAmount,2),
        'transport_fee_amount'=>round($transportAssignedAmount,2),
        'previous_year_pending'=>round((float)($carryForward['amount']??0),2),
        'amount'=>round((float)($fresh['net_amount']??(
            $assignedAmount+$transportAssignedAmount+(float)($carryForward['amount']??0)
        )),2),
        'balance_amount'=>round((float)($fresh['balance_amount']??0),2),
        'has_transport'=>$transport!==null,
        'schedule_preserved'=>false,
    ];
}

function promotionManagedStudentCount(
    PDO $pdo,
    array $scope,
    array $managed
): int {
    /*
     * Student Promotion source is CLASS-WISE.
     *
     * Use the same logical count that the Student List presents:
     * one active student per selected Academic Year + Class,
     * regardless of which section the student currently belongs to.
     *
     * COUNT(DISTINCT student_id) also prevents duplicate active enrollment
     * rows from increasing the displayed class strength.
     */
    $canonical = promotionResolveCanonicalManaged(
        $pdo,
        (int)$scope['tenant_id'],
        $managed
    );

    $where = [
        'e.tenant_id = :tenant_id',
        'e.academic_year_id = :academic_year_id',
        'e.class_id = :class_id',
        "e.enrollment_status = 'active'",
        "s.status = 'active'",
        's.deleted_at IS NULL',
    ];

    $params = [
        'tenant_id' => $scope['tenant_id'],
        'academic_year_id' => $canonical['academic_year_id'],
        'class_id' => $canonical['class_id'],
    ];

    $where[] = 'e.branch_id = :branch_id';
    $where[] = 's.branch_id = :branch_id';
    $params['branch_id'] = $scope['branch_id'];

    $statement = $pdo->prepare(
        "SELECT COUNT(DISTINCT e.student_id)
         FROM student_enrollments e
         INNER JOIN students s
            ON s.id = e.student_id
           AND s.tenant_id = e.tenant_id
         WHERE " . implode(' AND ', $where)
    );

    $statement->execute($params);

    return (int)$statement->fetchColumn();
}
function promotionMeta(PDO $pdo, array $scope): array
{
    $yearsStatement = $pdo->prepare(
        "SELECT id,branch_id,year_name,start_date,end_date,is_current,status
         FROM academic_years
         WHERE tenant_id = :tenant_id
           AND branch_id = :branch_id
         ORDER BY start_date,id"
    );
    $yearsStatement->execute([
        'tenant_id' => $scope['tenant_id'],
        'branch_id' => $scope['branch_id'],
    ]);
    $years = $yearsStatement->fetchAll(PDO::FETCH_ASSOC);

    $classesStatement = $pdo->prepare(
        "SELECT
            id,
            branch_id,
            academic_year_id,
            class_name,
            class_code,
            section_name,
            medium,
            shift_name,
            maximum_strength,
            current_strength,
            status,
            display_order
         FROM class_management_classes
         WHERE tenant_id = :tenant_id
           AND branch_id = :branch_id
           AND status = 'active'
         ORDER BY academic_year_id,display_order,class_name,section_name,id"
    );
    $classesStatement->execute([
        'tenant_id' => $scope['tenant_id'],
        'branch_id' => $scope['branch_id'],
    ]);
    $classes = $classesStatement->fetchAll(PDO::FETCH_ASSOC);

    foreach ($classes as &$class) {
        $class['id'] = (int)$class['id'];
        $class['academic_year_id'] = (int)$class['academic_year_id'];
        $class['display_order'] = (int)$class['display_order'];
        $class['maximum_strength'] = (int)$class['maximum_strength'];

        try {
            $canonical = promotionResolveCanonicalManaged(
                $pdo,
                (int)$scope['tenant_id'],
                $class
            );

            $class['canonical_class_id'] = (int)$canonical['class_id'];
            $class['canonical_section_id'] = (int)$canonical['section_id'];
            $class['student_count'] = promotionManagedStudentCount(
                $pdo,
                $scope,
                $class
            );

            $feePreview = promotionFeePreview(
                $pdo,
                (int)$scope['tenant_id'],
                (int)$class['academic_year_id'],
                (int)$canonical['class_id']
            );

            $class['fee_structure_id'] = (int)($feePreview['id'] ?? 0);
            $class['fee_structure_name'] =
                (string)($feePreview['structure_name'] ?? '');
            $class['fee_amount'] =
                round((float)($feePreview['annual_amount'] ?? 0), 2);
            $class['fee_item_count'] =
                (int)($feePreview['item_count'] ?? 0);
            $class['fee_items'] =
                is_array($feePreview['items'] ?? null)
                    ? $feePreview['items']
                    : [];
        } catch (Throwable $classException) {
            /*
             * Keep an invalid/orphan management row out of Promotion instead of
             * mixing students from a different canonical class.
             */
            $class['canonical_class_id'] = 0;
            $class['canonical_section_id'] = 0;
            $class['student_count'] = 0;
            $class['fee_structure_id'] = 0;
            $class['fee_structure_name'] = '';
            $class['fee_amount'] = 0.0;
            $class['fee_item_count'] = 0;
            $class['fee_items'] = [];
        }
    }
    unset($class);

    $classes = array_values(
        array_filter(
            $classes,
            static fn(array $class): bool =>
                (int)($class['canonical_class_id'] ?? 0) > 0
                && (int)($class['canonical_section_id'] ?? 0) > 0
        )
    );

    return [
        'academic_years' => $years,
        'classes' => $classes,
        'current_academic_year_id' => promotionCurrentAcademicYearId(
            $pdo,
            (int)$scope['tenant_id'],
            (int)$scope['branch_id']
        ),
        'active_branch_id' => (int)$scope['branch_id'],
    ];
}

function promotionStudents(
    PDO $pdo,
    array $scope,
    int $fromYearId,
    int $toYearId,
    array $managed
): array {
    promotionFindYear(
        $pdo,
        (int)$scope['tenant_id'],
        (int)$scope['branch_id'],
        $fromYearId
    );

    promotionFindYear(
        $pdo,
        (int)$scope['tenant_id'],
        (int)$scope['branch_id'],
        $toYearId
    );

    if ((int)$managed['academic_year_id'] !== $fromYearId) {
        throw new InvalidArgumentException(
            'The selected Current Class does not belong to the selected From Academic Year.'
        );
    }

    $canonical = promotionResolveCanonicalManaged(
        $pdo,
        (int)$scope['tenant_id'],
        $managed
    );

    $where = [
        'e.tenant_id = :tenant_id',
        'e.academic_year_id = :from_year_id',
        'e.class_id = :class_id',
        "e.enrollment_status = 'active'",
        "s.status = 'active'",
        's.deleted_at IS NULL',

        /*
         * One current active enrollment row per student.
         * Old duplicate enrollment rows must not duplicate the student in
         * Load Students or make the loaded total differ from the class count.
         */
        "e.id = (
            SELECT e_latest.id
            FROM student_enrollments e_latest
            WHERE e_latest.tenant_id = e.tenant_id
              AND e_latest.student_id = e.student_id
              AND e_latest.academic_year_id = e.academic_year_id
              AND e_latest.class_id = e.class_id
              AND e_latest.enrollment_status = 'active'
            ORDER BY e_latest.id DESC
            LIMIT 1
        )",
    ];

    $params = [
        'tenant_id' => $scope['tenant_id'],
        'from_year_id' => $fromYearId,
        'class_id' => $canonical['class_id'],
        'to_year_id' => $toYearId,
        'display_class_name' => (string)$managed['class_name'],
    ];

    $where[] = 'e.branch_id = :branch_id';
    $where[] = 's.branch_id = :branch_id';
    $params['branch_id'] = $scope['branch_id'];

    $statement = $pdo->prepare(
        "SELECT
            s.id,
            s.admission_no AS admission_number,
            TRIM(
                CONCAT(
                    COALESCE(s.first_name,''),
                    CASE
                        WHEN COALESCE(s.last_name,'') = ''
                            THEN ''
                        ELSE CONCAT(' ',s.last_name)
                    END
                )
            ) AS student_name,
            e.id AS enrollment_id,
            e.roll_no,
            e.class_id,
            e.section_id,
            :display_class_name AS class_name,
            COALESCE(
                NULLIF(sec.section_name,''),
                'General'
            ) AS section_name,
            CASE
                WHEN EXISTS(
                    SELECT 1
                    FROM student_enrollments target
                    WHERE target.student_id = e.student_id
                      AND target.tenant_id = e.tenant_id
                      AND target.academic_year_id = :to_year_id
                      AND target.enrollment_status = 'active'
                )
                THEN 1
                ELSE 0
            END AS already_promoted
         FROM student_enrollments e
         INNER JOIN students s
            ON s.id = e.student_id
           AND s.tenant_id = e.tenant_id
           AND s.branch_id = e.branch_id
         LEFT JOIN sections sec
            ON sec.id = e.section_id
           AND sec.tenant_id = e.tenant_id
           AND sec.branch_id = e.branch_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY
            CASE
                WHEN e.roll_no REGEXP '^[0-9]+$'
                    THEN CAST(e.roll_no AS UNSIGNED)
                ELSE 999999999
            END,
            e.roll_no,
            s.first_name,
            s.last_name,
            s.admission_no"
    );

    $statement->execute($params);

    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        $transport = promotionStudentTransport(
            $pdo,
            (int)$scope['tenant_id'],
            (int)$row['id'],
            $fromYearId
        );

        $row['transport_required'] =
            $transport ? 1 : 0;

        $row['transport_fee_amount'] =
            $transport
                ? round(
                    (float)(
                        $transport['transport_fee_amount']
                        ?? 0
                    ),
                    2
                )
                : 0.0;

        $row['transport_route_name'] =
            $transport
                ? (string)(
                    $transport['route_name']
                    ?? ''
                )
                : '';

        $row['transport_stop_name'] =
            $transport
                ? (string)(
                    $transport['boarding_stop_name']
                    ?? ''
                )
                : '';
    }
    unset($row);

    return $rows;
}
function promotionFindSourceEnrollment(
    PDO $pdo,
    array $scope,
    int $studentId,
    int $fromYearId,
    array $managed
): array {
    /*
     * Validate the student against the selected Academic Year + Class.
     * The student's actual source section is read from the enrollment row.
     */
    $canonical = promotionResolveCanonicalManaged(
        $pdo,
        (int)$scope['tenant_id'],
        $managed
    );

    $where = [
        'e.tenant_id = :tenant_id',
        'e.student_id = :student_id',
        'e.academic_year_id = :academic_year_id',
        'e.class_id = :class_id',
        "e.enrollment_status = 'active'",
        "s.status = 'active'",
        's.deleted_at IS NULL',
    ];

    $params = [
        'tenant_id' => $scope['tenant_id'],
        'student_id' => $studentId,
        'academic_year_id' => $fromYearId,
        'class_id' => $canonical['class_id'],
    ];

    $where[] = 'e.branch_id = :branch_id';
    $where[] = 's.branch_id = :branch_id';
    $params['branch_id'] = $scope['branch_id'];

    $statement = $pdo->prepare(
        "SELECT
            e.*,
            COALESCE(
                NULLIF(sec.section_name,''),
                'General'
            ) AS resolved_section_name
         FROM student_enrollments e
         INNER JOIN students s
            ON s.id = e.student_id
           AND s.tenant_id = e.tenant_id
           AND s.branch_id = e.branch_id
         LEFT JOIN sections sec
            ON sec.id = e.section_id
           AND sec.tenant_id = e.tenant_id
           AND sec.branch_id = e.branch_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY e.id DESC
         LIMIT 1
         FOR UPDATE"
    );

    $statement->execute($params);
    $row = $statement->fetch(PDO::FETCH_ASSOC);

    if (!is_array($row)) {
        throw new RuntimeException(
            'The student is no longer active in the selected Academic Year and Class.'
        );
    }

    return $row;
}
function promotionEnsureCanonicalTarget(
    PDO $pdo,
    array $scope,
    array $managed
): array {
    $tenantId = (int)$scope['tenant_id'];
    $branchId = (int)$scope['branch_id'];
    $yearId = (int)$managed['academic_year_id'];

    if ((int)($managed['branch_id'] ?? 0) !== $branchId) {
        throw new InvalidArgumentException(
            'The selected target Class / Section does not belong to the active Branch.'
        );
    }
    $className = trim((string)$managed['class_name']);
    $sectionName = trim((string)$managed['section_name']) ?: 'General';
    $displayOrder = (int)($managed['display_order'] ?? 0);
    $capacity = max(1, (int)($managed['maximum_strength'] ?? 40));

    $classStatement = $pdo->prepare(
        "SELECT id
         FROM classes
         WHERE tenant_id = :tenant_id
           AND branch_id = :branch_id
           AND academic_year_id = :academic_year_id
           AND LOWER(TRIM(class_name)) = LOWER(TRIM(:class_name))
         LIMIT 1
         FOR UPDATE"
    );
    $classStatement->execute([
        'tenant_id' => $tenantId,
        'branch_id' => $branchId,
        'academic_year_id' => $yearId,
        'class_name' => $className,
    ]);

    $classId = (int)($classStatement->fetchColumn() ?: 0);

    if ($classId <= 0) {
        $insertClass = $pdo->prepare(
            "INSERT INTO classes(
                tenant_id,branch_id,academic_year_id,class_name,display_order,status
             ) VALUES(
                :tenant_id,:branch_id,:academic_year_id,:class_name,:display_order,'active'
             )"
        );
        $insertClass->execute([
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'academic_year_id' => $yearId,
            'class_name' => $className,
            'display_order' => $displayOrder,
        ]);
        $classId = (int)$pdo->lastInsertId();
    }

    $sectionStatement = $pdo->prepare(
        "SELECT id
         FROM sections
         WHERE tenant_id = :tenant_id
           AND branch_id = :branch_id
           AND class_id = :class_id
           AND LOWER(TRIM(section_name)) = LOWER(TRIM(:section_name))
         LIMIT 1
         FOR UPDATE"
    );
    $sectionStatement->execute([
        'tenant_id' => $tenantId,
        'branch_id' => $branchId,
        'class_id' => $classId,
        'section_name' => $sectionName,
    ]);

    $sectionId = (int)($sectionStatement->fetchColumn() ?: 0);

    if ($sectionId <= 0) {
        $insertSection = $pdo->prepare(
            "INSERT INTO sections(
                tenant_id,branch_id,class_id,section_name,capacity,status
             ) VALUES(
                :tenant_id,:branch_id,:class_id,:section_name,:capacity,'active'
             )"
        );
        $insertSection->execute([
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'class_id' => $classId,
            'section_name' => $sectionName,
            'capacity' => $capacity,
        ]);
        $sectionId = (int)$pdo->lastInsertId();
    }

    return [
        'class_id' => $classId,
        'section_id' => $sectionId,
        'class_name' => $className,
        'section_name' => $sectionName,
    ];
}

function promotionInsertHistory(
    PDO $pdo,
    array $scope,
    array $sourceManaged,
    array $targetManaged,
    array $sourceEnrollment,
    int $studentId,
    string $studentName,
    string $promotionDate,
    string $remarks,
    int $targetSectionId
): int {
    /*
     * An older failed/incomplete promotion attempt can leave an active
     * class_student_promotions history row even though the student still has
     * an active source-year enrollment and no active target-year enrollment.
     *
     * The promote action already checks for an ACTIVE target-year enrollment
     * before calling this function. Therefore an existing history row here is
     * safe to reuse/update instead of blocking the student with:
     * "already has an active promotion record".
     */
    $duplicate = $pdo->prepare(
        "SELECT id
         FROM class_student_promotions
         WHERE tenant_id = :tenant_id
           AND branch_id = :branch_id
           AND student_id = :student_id
           AND from_academic_year_id = :from_year_id
           AND to_academic_year_id = :to_year_id
           AND status = 'active'
         ORDER BY id DESC
         LIMIT 1
         FOR UPDATE"
    );
    $duplicate->execute([
        'tenant_id' => $scope['tenant_id'],
        'branch_id' => $scope['branch_id'],
        'student_id' => $studentId,
        'from_year_id' => (int)$sourceManaged['academic_year_id'],
        'to_year_id' => (int)$targetManaged['academic_year_id'],
    ]);

    $existingPromotionId = (int)($duplicate->fetchColumn() ?: 0);

    if ($existingPromotionId > 0) {
        $update = $pdo->prepare(
            "UPDATE class_student_promotions
             SET branch_id = :branch_id,
                 class_id = :class_id,
                 from_section_id = :from_section_id,
                 student_name = :student_name,
                 to_class_id = :to_class_id,
                 to_section_id = :to_section_id,
                 from_class_name = :from_class_name,
                 from_section_name = :from_section_name,
                 to_class_name = :to_class_name,
                 to_section_name = :to_section_name,
                 promotion_date = :promotion_date,
                 result_status = 'promoted',
                 remarks = :remarks,
                 status = 'active',
                 created_by = :created_by,
                 cancelled_by = NULL,
                 cancelled_at = NULL
             WHERE id = :id
               AND tenant_id = :tenant_id"
        );

        $update->execute([
            'branch_id' => $scope['branch_id'] ?: null,
            'class_id' => (int)$sourceManaged['id'],
            'from_section_id' => (int)($sourceEnrollment['section_id'] ?? 0) ?: null,
            'student_name' => $studentName,
            'to_class_id' => (int)$targetManaged['id'],
            'to_section_id' => $targetSectionId > 0 ? $targetSectionId : null,
            'from_class_name' => (string)$sourceManaged['class_name'],
            'from_section_name' =>
                trim((string)($sourceEnrollment['resolved_section_name'] ?? ''))
                ?: (trim((string)$sourceManaged['section_name']) ?: 'General'),
            'to_class_name' => (string)$targetManaged['class_name'],
            'to_section_name' =>
                trim((string)$targetManaged['section_name']) ?: 'General',
            'promotion_date' => $promotionDate,
            'remarks' => $remarks !== '' ? $remarks : null,
            'created_by' => $scope['user_id'] ?: null,
            'id' => $existingPromotionId,
            'tenant_id' => $scope['tenant_id'],
        ]);

        return $existingPromotionId;
    }

    $statement = $pdo->prepare(
        "INSERT INTO class_student_promotions(
            tenant_id,
            branch_id,
            class_id,
            from_section_id,
            student_id,
            student_name,
            from_academic_year_id,
            to_academic_year_id,
            to_class_id,
            to_section_id,
            from_class_name,
            from_section_name,
            to_class_name,
            to_section_name,
            promotion_date,
            result_status,
            remarks,
            status,
            created_by
         ) VALUES(
            :tenant_id,
            :branch_id,
            :class_id,
            :from_section_id,
            :student_id,
            :student_name,
            :from_academic_year_id,
            :to_academic_year_id,
            :to_class_id,
            :to_section_id,
            :from_class_name,
            :from_section_name,
            :to_class_name,
            :to_section_name,
            :promotion_date,
            'promoted',
            :remarks,
            'active',
            :created_by
         )"
    );

    $statement->execute([
        'tenant_id' => $scope['tenant_id'],
        'branch_id' => $scope['branch_id'] ?: null,
        /*
         * class_id / to_class_id intentionally store Class Management IDs.
         * Student enrollment itself stores canonical classes/sections IDs.
         */
        'class_id' => (int)$sourceManaged['id'],
        'from_section_id' => (int)$sourceEnrollment['section_id'] ?: null,
        'student_id' => $studentId,
        'student_name' => $studentName,
        'from_academic_year_id' => (int)$sourceManaged['academic_year_id'],
        'to_academic_year_id' => (int)$targetManaged['academic_year_id'],
        'to_class_id' => (int)$targetManaged['id'],
        'to_section_id' => $targetSectionId,
        'from_class_name' => (string)$sourceManaged['class_name'],
        'from_section_name' => trim((string)$sourceManaged['section_name']) ?: 'General',
        'to_class_name' => (string)$targetManaged['class_name'],
        'to_section_name' => trim((string)$targetManaged['section_name']) ?: 'General',
        'promotion_date' => $promotionDate,
        'remarks' => $remarks !== '' ? $remarks : null,
        'created_by' => $scope['user_id'] ?: null,
    ]);

    return (int)$pdo->lastInsertId();
}

if (!isset($pdo) || !$pdo instanceof PDO) {
    promotionJson(false, 'Database connection unavailable.', [], 500);
}

try {
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    promotionRequireTables($pdo);
} catch (Throwable $exception) {
    promotionJson(
        false,
        'Unable to initialize Student Promotion module: ' . $exception->getMessage(),
        [],
        500
    );
}

$scope = promotionScope($pdo);

if ($scope['tenant_id'] <= 0 || $scope['branch_id'] <= 0) {
    promotionJson(
        false,
        'Active School and Branch context is required.',
        [],
        422
    );
}

$input = promotionInput();
$action = strtolower(
    trim((string)($input['action'] ?? $_GET['action'] ?? ''))
);

try {
    if ($action === 'meta') {
        if (!promotionCan('view')) {
            throw new RuntimeException('You do not have permission to view Student Promotion.', 403);
        }

        promotionJson(
            true,
            'Promotion metadata loaded from Class Management.',
            [
                'csrf_token' => function_exists('csrfToken') ? csrfToken() : '',
                'meta' => promotionMeta($pdo, $scope),
                'permissions' => [
                    'view' => promotionCan('view'),
                    'promote' => promotionCan('edit')
                        || promotionCan('add')
                        || promotionCan('create'),
                ],
            ]
        );
    }

    if ($action === 'students') {
        if (!promotionCan('view')) {
            throw new RuntimeException('You do not have permission to view Student Promotion.', 403);
        }

        $filters = array_merge($_GET, $input);
        $fromYearId = (int)($filters['from_academic_year_id'] ?? 0);
        $toYearId = (int)($filters['to_academic_year_id'] ?? 0);
        $currentManagementId = (int)($filters['current_management_id'] ?? 0);
        $targetManagementId = (int)($filters['target_management_id'] ?? 0);

        if (
            $fromYearId <= 0
            || $toYearId <= 0
            || $currentManagementId <= 0
            || $targetManagementId <= 0
        ) {
            throw new InvalidArgumentException(
                'Select From Academic Year, To Academic Year, Current Class / Section and Promote To Class / Section.'
            );
        }

        if ($fromYearId === $toYearId) {
            throw new InvalidArgumentException(
                'From Academic Year and To Academic Year must be different.'
            );
        }

        $sourceManaged = promotionFindManagedClass(
            $pdo,
            (int)$scope['tenant_id'],
            (int)$scope['branch_id'],
            $currentManagementId,
            $fromYearId
        );

        $targetManaged = promotionFindManagedClass(
            $pdo,
            (int)$scope['tenant_id'],
            (int)$scope['branch_id'],
            $targetManagementId,
            $toYearId
        );

        $targetCanonical = promotionResolveCanonicalManaged(
            $pdo,
            (int)$scope['tenant_id'],
            $targetManaged
        );

        $targetFee = promotionFeePreview(
            $pdo,
            (int)$scope['tenant_id'],
            $toYearId,
            (int)$targetCanonical['class_id']
        );

        $students = promotionStudents(
            $pdo,
            $scope,
            $fromYearId,
            $toYearId,
            $sourceManaged
        );

        $targetStructure=promotionFeeStructure(
            $pdo,
            (int)$scope['tenant_id'],
            $toYearId,
            (int)$targetCanonical['class_id'],
            false
        );

        foreach($students as &$studentRow){
            $previousPending=promotionPreviousYearPending(
                $pdo,
                (int)$scope['tenant_id'],
                (int)$studentRow['id'],
                $fromYearId
            );

            /* Fixed transport fee: use the configured amount exactly once. */
            $transportFixed=round(
                (float)($studentRow['transport_fee_amount']??0),
                2
            );
            $classFee=round((float)($targetFee['annual_amount']??0),2);

            $studentRow['previous_year_pending']=$previousPending;
            $studentRow['target_class_fee_amount']=$classFee;
            $studentRow['transport_fixed_amount']=$transportFixed;
            /* Backward-compatible field; now equal to the fixed fee. */
            $studentRow['transport_annual_amount']=$transportFixed;
            $studentRow['combined_fee_total']=round(
                $previousPending+$classFee+$transportFixed,
                2
            );
        }
        unset($studentRow);

        $total = count($students);

        promotionJson(
            true,
            $total > 0
                ? $total . ' student' . ($total === 1 ? '' : 's') . ' loaded.'
                : 'No active students were found in the selected Class.',
            [
                'students' => $students,
                'total' => $total,
                'already_promoted' => count(
                    array_filter(
                        $students,
                        static fn(array $row): bool =>
                            (int)($row['already_promoted'] ?? 0) === 1
                    )
                ),
                'target_fee_structure' => $targetFee,
            ]
        );
    }

    if ($action === 'promote') {
        promotionCsrf($input);

        if (
            !promotionCan('edit')
            && !promotionCan('add')
            && !promotionCan('create')
        ) {
            throw new RuntimeException('You do not have permission to promote students.', 403);
        }

        $fromYearId = (int)($input['from_academic_year_id'] ?? 0);
        $toYearId = (int)($input['to_academic_year_id'] ?? 0);
        $currentManagementId = (int)($input['current_management_id'] ?? 0);
        $targetManagementId = (int)($input['target_management_id'] ?? 0);
        $promotionDate = trim((string)($input['promotion_date'] ?? ''));
        $remarks = mb_substr(trim((string)($input['remarks'] ?? '')), 0, 500);
        $studentIds = $input['student_ids'] ?? [];

        if (
            $fromYearId <= 0
            || $toYearId <= 0
            || $currentManagementId <= 0
            || $targetManagementId <= 0
        ) {
            throw new InvalidArgumentException(
                'Select From Academic Year, To Academic Year, Current Class / Section and Promote To Class / Section.'
            );
        }

        if ($fromYearId === $toYearId) {
            throw new InvalidArgumentException(
                'From Academic Year and To Academic Year must be different.'
            );
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $promotionDate);
        if (
            !$date
            || $date->format('Y-m-d') !== $promotionDate
        ) {
            throw new InvalidArgumentException('Select a valid Promotion Date.');
        }

        if (!is_array($studentIds)) {
            throw new InvalidArgumentException('Student selection is invalid.');
        }

        $studentIds = array_values(
            array_unique(
                array_filter(
                    array_map('intval', $studentIds),
                    static fn(int $id): bool => $id > 0
                )
            )
        );

        if (!$studentIds) {
            throw new InvalidArgumentException('Select at least one student.');
        }

        $fromYear = promotionFindYear(
            $pdo,
            (int)$scope['tenant_id'],
            (int)$scope['branch_id'],
            $fromYearId
        );
        $toYear = promotionFindYear(
            $pdo,
            (int)$scope['tenant_id'],
            (int)$scope['branch_id'],
            $toYearId
        );

        $sourceManaged = promotionFindManagedClass(
            $pdo,
            (int)$scope['tenant_id'],
            (int)$scope['branch_id'],
            $currentManagementId,
            $fromYearId
        );
        $targetManaged = promotionFindManagedClass(
            $pdo,
            (int)$scope['tenant_id'],
            (int)$scope['branch_id'],
            $targetManagementId,
            $toYearId
        );

        promotionEnsureHistorySchema($pdo);

        $pdo->beginTransaction();

        try {
            $targetCanonical = promotionEnsureCanonicalTarget(
                $pdo,
                $scope,
                $targetManaged
            );

            $targetFind = $pdo->prepare(
                "SELECT id,enrollment_status
                 FROM student_enrollments
                 WHERE tenant_id = :tenant_id
                   AND branch_id = :branch_id
                   AND student_id = :student_id
                   AND academic_year_id = :academic_year_id
                 LIMIT 1
                 FOR UPDATE"
            );

            $targetInsert = $pdo->prepare(
                "INSERT INTO student_enrollments(
                    tenant_id,
                    branch_id,
                    student_id,
                    academic_year_id,
                    class_id,
                    section_id,
                    roll_no,
                    enrollment_status,
                    enrolled_on
                 ) VALUES(
                    :tenant_id,
                    :branch_id,
                    :student_id,
                    :academic_year_id,
                    :class_id,
                    :section_id,
                    :roll_no,
                    'active',
                    :enrolled_on
                 )"
            );

            $targetUpdate = $pdo->prepare(
                "UPDATE student_enrollments
                 SET class_id = :class_id,
                     section_id = :section_id,
                     roll_no = :roll_no,
                     enrollment_status = 'active',
                     enrolled_on = :enrolled_on
                 WHERE id = :id
                   AND tenant_id = :tenant_id
                   AND branch_id = :branch_id"
            );

            $sourcePromote = $pdo->prepare(
                "UPDATE student_enrollments
                 SET enrollment_status = 'promoted'
                 WHERE id = :id
                   AND tenant_id = :tenant_id
                   AND branch_id = :branch_id"
            );

            $studentNameStatement = $pdo->prepare(
                "SELECT TRIM(CONCAT(
                    COALESCE(first_name,''),
                    CASE
                        WHEN COALESCE(last_name,'') = '' THEN ''
                        ELSE CONCAT(' ',last_name)
                    END
                 ))
                 FROM students
                 WHERE id = :id
                   AND tenant_id = :tenant_id
                   AND branch_id = :branch_id
                 LIMIT 1"
            );

            $promoted = 0;
            $skipped = 0;
            $errors = [];
            $feeAssignedTotal = 0.0;

            foreach ($studentIds as $studentId) {
                $savepoint = 'promotion_student_' . $studentId;
                $pdo->exec('SAVEPOINT ' . $savepoint);

                try {
                    $sourceEnrollment = promotionFindSourceEnrollment(
                        $pdo,
                        $scope,
                        $studentId,
                        $fromYearId,
                        $sourceManaged
                    );

                    $targetFind->execute([
                        'tenant_id' => $scope['tenant_id'],
                        'branch_id' => $scope['branch_id'],
                        'student_id' => $studentId,
                        'academic_year_id' => $toYearId,
                    ]);
                    $existingTarget = $targetFind->fetch(PDO::FETCH_ASSOC);

                    if (
                        is_array($existingTarget)
                        && (string)$existingTarget['enrollment_status'] === 'active'
                    ) {
                        $skipped++;
                        $errors[] = 'Student #' . $studentId . ' already has an active enrollment in the target Academic Year.';
                        $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                        $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
                        continue;
                    }

                    $targetData = [
                        'tenant_id' => $scope['tenant_id'],
                        'student_id' => $studentId,
                        'academic_year_id' => $toYearId,
                        'class_id' => (int)$targetCanonical['class_id'],
                        'section_id' => (int)$targetCanonical['section_id'],
                        'roll_no' => $sourceEnrollment['roll_no'] ?: null,
                        'enrolled_on' => $promotionDate,
                    ];

                    if (is_array($existingTarget)) {
                        $targetUpdate->execute([
                            'class_id' => $targetData['class_id'],
                            'section_id' => $targetData['section_id'],
                            'roll_no' => $targetData['roll_no'],
                            'enrolled_on' => $targetData['enrolled_on'],
                            'id' => (int)$existingTarget['id'],
                            'tenant_id' => $scope['tenant_id'],
                        ]);
                    } else {
                        $targetInsert->execute($targetData);
                    }

                    $sourcePromote->execute([
                        'id' => (int)$sourceEnrollment['id'],
                        'tenant_id' => $scope['tenant_id'],
                    ]);

                    $studentNameStatement->execute([
                        'id' => $studentId,
                        'tenant_id' => $scope['tenant_id'],
                    ]);
                    $studentName = trim(
                        (string)($studentNameStatement->fetchColumn() ?: ('Student #' . $studentId))
                    );

                    $promotionId = promotionInsertHistory(
                        $pdo,
                        $scope,
                        $sourceManaged,
                        $targetManaged,
                        $sourceEnrollment,
                        $studentId,
                        $studentName,
                        $promotionDate,
                        $remarks,
                        (int)$targetCanonical['section_id']
                    );

                    /*
                     * Automatically assign the Fee Structure that belongs to
                     * the selected To Academic Year + target canonical Class.
                     */
                    $feeAssignment = promotionAssignTargetFees(
                        $pdo,
                        $scope,
                        $studentId,
                        $fromYearId,
                        $toYearId,
                        (int)$targetCanonical['class_id'],
                        $promotionDate
                    );

                    promotionLog(
                        $pdo,
                        $scope,
                        'student_promoted',
                        $promotionId,
                        $studentId,
                        [
                            'from_academic_year_id' => $fromYearId,
                            'to_academic_year_id' => $toYearId,
                            'from_management_id' => $currentManagementId,
                            'to_management_id' => $targetManagementId,
                            'from_class' => $sourceManaged['class_name'],
                            'from_section' => $sourceManaged['section_name'],
                            'to_class' => $targetManaged['class_name'],
                            'to_section' => $targetManaged['section_name'],
                            'target_canonical_class_id' => $targetCanonical['class_id'],
                            'target_canonical_section_id' => $targetCanonical['section_id'],
                            'fee_structure_id' => $feeAssignment['fee_structure_id'],
                            'fee_structure_name' => $feeAssignment['fee_structure_name'],
                            'base_fee_amount' => $feeAssignment['base_fee_amount'] ?? 0,
                            'transport_fee_amount' => $feeAssignment['transport_fee_amount'] ?? 0,
                            'previous_year_pending' => $feeAssignment['previous_year_pending'] ?? 0,
                            'fee_amount' => $feeAssignment['amount'],
                            'has_transport' => $feeAssignment['has_transport'] ?? false,
                        ]
                    );

                    $feeAssignedTotal += (float)($feeAssignment['amount'] ?? 0);

                    $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
                    $promoted++;
                } catch (Throwable $studentException) {
                    $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                    $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
                    $skipped++;
                    $errors[] = 'Student #' . $studentId . ': ' . $studentException->getMessage();
                }
            }

            if ($promoted <= 0) {
                $pdo->rollBack();

                throw new RuntimeException(
                    'No students were promoted. '
                    . implode(' ', array_slice($errors, 0, 3)),
                    422
                );
            }

            $pdo->commit();

            promotionJson(
                true,
                $promoted . ' student' . ($promoted === 1 ? '' : 's')
                    . ' promoted successfully from '
                    . (string)$sourceManaged['class_name']
                    . ' (' . (string)$fromYear['year_name'] . ') to '
                    . (string)$targetManaged['class_name']
                    . ' / ' . (trim((string)$targetManaged['section_name']) ?: 'General')
                    . ' (' . (string)$toYear['year_name'] . ').',
                [
                    'promoted' => $promoted,
                    'skipped' => $skipped,
                    'errors' => $errors,
                    'target_canonical_class_id' => (int)$targetCanonical['class_id'],
                    'target_canonical_section_id' => (int)$targetCanonical['section_id'],
                    'fee_assigned_total' => round($feeAssignedTotal, 2),
                ]
            );
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    promotionJson(false, 'Invalid Student Promotion action.', [], 400);
} catch (InvalidArgumentException $exception) {
    promotionJson(false, $exception->getMessage(), [], 422);
} catch (RuntimeException $exception) {
    $status = (int)$exception->getCode();

    promotionJson(
        false,
        $exception->getMessage(),
        [],
        $status >= 400 && $status <= 599 ? $status : 409
    );
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('student-promotion.php: ' . $exception->getMessage());

    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $message = (
        str_contains($host, 'localhost')
        || str_contains($host, '127.0.0.1')
    )
        ? 'Student Promotion request failed: ' . $exception->getMessage()
        : 'Unable to complete the Student Promotion request.';

    promotionJson(false, $message, [], 500);
}

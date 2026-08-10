<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

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

function promotionUser(): array
{
    $user = function_exists('current_user') ? current_user() : [];
    return is_array($user) ? $user : [];
}

function promotionScope(): array
{
    $user = promotionUser();

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
            ?? $_SESSION['branch_id']
            ?? 0
        ),
        'user_id' => (int)(
            $user['id']
            ?? $user['user_id']
            ?? $_SESSION['user_id']
            ?? 0
        ),
    ];
}

function promotionCan(string $action): bool
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

    foreach (['student_promotion', 'student_management', 'students'] as $module) {
        foreach ($aliases as $permissionAction) {
            if (has_permission($module, $permissionAction)) {
                return true;
            }
        }
    }

    return false;
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
        promotionJson(false, 'Invalid or expired CSRF token.', [], 419);
    }
}

function promotionTableExists(PDO $pdo, string $table): bool
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

function promotionRequireTables(PDO $pdo): void
{
    foreach (
        [
            'academic_years',
            'classes',
            'sections',
            'students',
            'student_enrollments',
        ] as $table
    ) {
        if (!promotionTableExists($pdo, $table)) {
            throw new RuntimeException(
                'Missing required database table: ' . $table . '.',
                500
            );
        }
    }
}

function promotionCurrentAcademicYearId(PDO $pdo, int $tenantId): int
{
    $statement = $pdo->prepare(
        "SELECT id
         FROM academic_years
         WHERE tenant_id = :tenant_id
         ORDER BY is_current DESC, status = 'active' DESC, start_date DESC, id DESC
         LIMIT 1"
    );
    $statement->execute(['tenant_id' => $tenantId]);
    return (int)$statement->fetchColumn();
}

function promotionClassRank(string $className): int
{
    $normalized = strtolower(trim($className));
    $map = [
        'lkg' => 1,
        'lower kindergarten' => 1,
        'ukg' => 2,
        'upper kindergarten' => 2,
        'class 1' => 3,
        '1st standard' => 3,
        'class 2' => 4,
        '2nd standard' => 4,
        'class 3' => 5,
        '3rd standard' => 5,
        'class 4' => 6,
        '4th standard' => 6,
        'class 5' => 7,
        '5th standard' => 7,
    ];

    return $map[$normalized] ?? 999;
}

function promotionMeta(PDO $pdo, array $scope): array
{
    $yearsStatement = $pdo->prepare(
        'SELECT id, year_name, start_date, end_date, is_current, status
         FROM academic_years
         WHERE tenant_id = :tenant_id
         ORDER BY start_date DESC, id DESC'
    );
    $yearsStatement->execute(['tenant_id' => $scope['tenant_id']]);
    $years = $yearsStatement->fetchAll(PDO::FETCH_ASSOC);

    $classesStatement = $pdo->prepare(
        "SELECT id, academic_year_id, class_name, display_order
         FROM classes
         WHERE tenant_id = :tenant_id
           AND status = 'active'
         ORDER BY academic_year_id DESC, display_order, class_name"
    );
    $classesStatement->execute(['tenant_id' => $scope['tenant_id']]);
    $classes = $classesStatement->fetchAll(PDO::FETCH_ASSOC);

    $legacyClassTable = promotionTableExists($pdo, 'class_management_classes');
    $legacyJoin = $legacyClassTable
        ? "LEFT JOIN class_management_classes cmc
              ON cmc.id = e.class_id
             AND cmc.tenant_id = e.tenant_id"
        : '';

    $legacyClassCondition = $legacyClassTable
        ? "OR LOWER(TRIM(cmc.class_name)) = LOWER(TRIM(:class_name_legacy))"
        : '';

    $countSql = "
        SELECT COUNT(DISTINCT e.student_id)
        FROM student_enrollments e
        INNER JOIN students s
           ON s.id = e.student_id
          AND s.tenant_id = e.tenant_id
        LEFT JOIN classes c
           ON c.id = e.class_id
          AND c.tenant_id = e.tenant_id
        {$legacyJoin}
        WHERE e.tenant_id = :tenant_id
          AND e.academic_year_id = :academic_year_id
          AND (
                e.class_id = :class_id
                OR LOWER(TRIM(c.class_name)) = LOWER(TRIM(:class_name))
                {$legacyClassCondition}
              )
          AND LOWER(e.enrollment_status) = 'active'
          AND LOWER(s.status) = 'active'
          AND s.deleted_at IS NULL
          AND (
                :branch_scope = 0
                OR s.branch_id = :branch_value
                OR s.branch_id IS NULL
              )";

    $countStatement = $pdo->prepare($countSql);
    foreach ($classes as &$class) {
        $params = [
            'tenant_id' => $scope['tenant_id'],
            'academic_year_id' => (int)$class['academic_year_id'],
            'class_id' => (int)$class['id'],
            'class_name' => (string)$class['class_name'],
            'branch_scope' => $scope['branch_id'],
            'branch_value' => $scope['branch_id'],
        ];
        if ($legacyClassTable) {
            $params['class_name_legacy'] = (string)$class['class_name'];
        }
        $countStatement->execute($params);
        $class['student_count'] = (int)$countStatement->fetchColumn();
    }
    unset($class);

    $templates = [];
    foreach ($classes as $class) {
        $name = trim((string)$class['class_name']);
        if ($name === '') {
            continue;
        }

        $key = mb_strtolower($name);
        if (!isset($templates[$key])) {
            $templates[$key] = [
                'class_name' => $name,
                'display_order' => (int)($class['display_order'] ?? 0),
                'rank' => promotionClassRank($name),
            ];
        }
    }

    $templates = array_values($templates);
    usort(
        $templates,
        static function (array $a, array $b): int {
            $rankCompare = ((int)$a['rank']) <=> ((int)$b['rank']);
            if ($rankCompare !== 0) {
                return $rankCompare;
            }

            $displayCompare = ((int)$a['display_order']) <=> ((int)$b['display_order']);
            if ($displayCompare !== 0) {
                return $displayCompare;
            }

            return strcasecmp((string)$a['class_name'], (string)$b['class_name']);
        }
    );

    return [
        'academic_years' => $years,
        'classes' => $classes,
        'class_templates' => $templates,
        'current_academic_year_id' => promotionCurrentAcademicYearId(
            $pdo,
            $scope['tenant_id']
        ),
    ];
}

function promotionFindYear(PDO $pdo, int $tenantId, int $yearId): array
{
    $statement = $pdo->prepare(
        'SELECT id, year_name, start_date, end_date, status
         FROM academic_years
         WHERE id = :id
           AND tenant_id = :tenant_id
         LIMIT 1'
    );
    $statement->execute([
        'id' => $yearId,
        'tenant_id' => $tenantId,
    ]);

    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new InvalidArgumentException('Please select a valid academic year.');
    }

    return $row;
}

function promotionFindSourceClass(
    PDO $pdo,
    int $tenantId,
    int $academicYearId,
    int $classId
): array {
    $statement = $pdo->prepare(
        "SELECT id, academic_year_id, class_name, display_order
         FROM classes
         WHERE id = :id
           AND tenant_id = :tenant_id
           AND academic_year_id = :academic_year_id
           AND status = 'active'
         LIMIT 1"
    );
    $statement->execute([
        'id' => $classId,
        'tenant_id' => $tenantId,
        'academic_year_id' => $academicYearId,
    ]);

    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $row['source_table'] = 'classes';
        return $row;
    }

    if (promotionTableExists($pdo, 'class_management_classes')) {
        $statement = $pdo->prepare(
            "SELECT id, academic_year_id, class_name, display_order
             FROM class_management_classes
             WHERE id = :id
               AND tenant_id = :tenant_id
               AND academic_year_id = :academic_year_id
               AND status <> 'archived'
             LIMIT 1"
        );
        $statement->execute([
            'id' => $classId,
            'tenant_id' => $tenantId,
            'academic_year_id' => $academicYearId,
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $row['source_table'] = 'class_management_classes';
            return $row;
        }
    }

    throw new InvalidArgumentException(
        'Please select a valid current class for the selected academic year.'
    );
}

function promotionResolveTargetClass(
    PDO $pdo,
    int $tenantId,
    int $academicYearId,
    string $className,
    int $displayOrder
): array {
    $className = trim($className);
    if ($className === '') {
        throw new InvalidArgumentException('Please select a class to promote students into.');
    }

    $statement = $pdo->prepare(
        'SELECT id, class_name, display_order
         FROM classes
         WHERE tenant_id = :tenant_id
           AND academic_year_id = :academic_year_id
           AND class_name = :class_name
         LIMIT 1'
    );
    $statement->execute([
        'tenant_id' => $tenantId,
        'academic_year_id' => $academicYearId,
        'class_name' => $className,
    ]);

    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return $row;
    }

    $statement = $pdo->prepare(
        "INSERT INTO classes
            (tenant_id, academic_year_id, class_name, display_order, status)
         VALUES
            (:tenant_id, :academic_year_id, :class_name, :display_order, 'active')"
    );
    $statement->execute([
        'tenant_id' => $tenantId,
        'academic_year_id' => $academicYearId,
        'class_name' => $className,
        'display_order' => $displayOrder,
    ]);

    return [
        'id' => (int)$pdo->lastInsertId(),
        'class_name' => $className,
        'display_order' => $displayOrder,
    ];
}

function promotionResolveTargetSection(
    PDO $pdo,
    int $tenantId,
    int $targetClassId,
    string $sectionName,
    ?int $capacity
): int {
    $sectionName = trim($sectionName) !== '' ? trim($sectionName) : 'General';

    $statement = $pdo->prepare(
        'SELECT id
         FROM sections
         WHERE tenant_id = :tenant_id
           AND class_id = :class_id
           AND section_name = :section_name
         LIMIT 1'
    );
    $statement->execute([
        'tenant_id' => $tenantId,
        'class_id' => $targetClassId,
        'section_name' => $sectionName,
    ]);

    $sectionId = (int)$statement->fetchColumn();
    if ($sectionId > 0) {
        return $sectionId;
    }

    $statement = $pdo->prepare(
        "INSERT INTO sections
            (tenant_id, class_id, section_name, capacity, status)
         VALUES
            (:tenant_id, :class_id, :section_name, :capacity, 'active')"
    );
    $statement->execute([
        'tenant_id' => $tenantId,
        'class_id' => $targetClassId,
        'section_name' => $sectionName,
        'capacity' => $capacity,
    ]);

    return (int)$pdo->lastInsertId();
}

function promotionStudents(
    PDO $pdo,
    array $scope,
    int $fromYearId,
    int $toYearId,
    int $classId
): array {
    promotionFindYear($pdo, $scope['tenant_id'], $fromYearId);
    promotionFindYear($pdo, $scope['tenant_id'], $toYearId);

    $sourceClass = promotionFindSourceClass(
        $pdo,
        $scope['tenant_id'],
        $fromYearId,
        $classId
    );

    $legacyClassTable = promotionTableExists($pdo, 'class_management_classes');
    $legacySectionTable = promotionTableExists($pdo, 'school_sections');

    $legacyClassJoin = $legacyClassTable
        ? "LEFT JOIN class_management_classes cmc
              ON cmc.id = e.class_id
             AND cmc.tenant_id = e.tenant_id"
        : '';

    $legacySectionJoin = $legacySectionTable
        ? "LEFT JOIN school_sections ss
              ON ss.id = e.section_id
             AND ss.tenant_id = e.tenant_id"
        : '';

    $legacyClassMatch = $legacyClassTable
        ? "OR LOWER(TRIM(cmc.class_name)) = LOWER(TRIM(:source_class_name_legacy))"
        : '';

    $resolvedSectionName = $legacySectionTable
        ? "COALESCE(
                NULLIF(CASE WHEN sec.class_id = e.class_id THEN sec.section_name END, ''),
                NULLIF(ss.section_name, ''),
                NULLIF(sec.section_name, ''),
                'General'
            )"
        : "COALESCE(NULLIF(sec.section_name, ''), 'General')";

    $statement = $pdo->prepare(
        "SELECT
            s.id,
            s.admission_no AS admission_number,
            TRIM(CONCAT(
                COALESCE(s.first_name, ''),
                CASE
                    WHEN COALESCE(s.last_name, '') = '' THEN ''
                    ELSE CONCAT(' ', s.last_name)
                END
            )) AS student_name,
            e.roll_no,
            e.class_id,
            :display_class_name AS class_name,
            e.section_id,
            {$resolvedSectionName} AS section_name,
            CASE WHEN target.id IS NULL THEN 0 ELSE 1 END AS already_promoted,
            target.class_id AS target_class_id,
            target.section_id AS target_section_id
         FROM student_enrollments e
         INNER JOIN students s
            ON s.id = e.student_id
           AND s.tenant_id = e.tenant_id
         LEFT JOIN classes c
            ON c.id = e.class_id
           AND c.tenant_id = e.tenant_id
         {$legacyClassJoin}
         LEFT JOIN sections sec
            ON sec.id = e.section_id
           AND sec.tenant_id = e.tenant_id
         {$legacySectionJoin}
         LEFT JOIN student_enrollments target
            ON target.student_id = e.student_id
           AND target.tenant_id = e.tenant_id
           AND target.academic_year_id = :to_year_id
         WHERE e.tenant_id = :tenant_id
           AND e.academic_year_id = :from_year_id
           AND (
                e.class_id = :source_class_id
                OR LOWER(TRIM(c.class_name)) = LOWER(TRIM(:source_class_name))
                {$legacyClassMatch}
               )
           AND LOWER(e.enrollment_status) = 'active'
           AND LOWER(s.status) = 'active'
           AND s.deleted_at IS NULL
           AND (
                :branch_scope = 0
                OR s.branch_id = :branch_value
                OR s.branch_id IS NULL
               )
         ORDER BY
            CASE
                WHEN e.roll_no REGEXP '^[0-9]+$' THEN CAST(e.roll_no AS UNSIGNED)
                ELSE 999999999
            END,
            e.roll_no,
            s.first_name,
            s.last_name,
            s.admission_no"
    );

    $params = [
        'display_class_name' => (string)$sourceClass['class_name'],
        'to_year_id' => $toYearId,
        'tenant_id' => $scope['tenant_id'],
        'from_year_id' => $fromYearId,
        'source_class_id' => (int)$sourceClass['id'],
        'source_class_name' => (string)$sourceClass['class_name'],
        'branch_scope' => $scope['branch_id'],
        'branch_value' => $scope['branch_id'],
    ];
    if ($legacyClassTable) {
        $params['source_class_name_legacy'] = (string)$sourceClass['class_name'];
    }
    $statement->execute($params);

    return $statement->fetchAll(PDO::FETCH_ASSOC);
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

$scope = promotionScope();
if ($scope['tenant_id'] <= 0) {
    promotionJson(false, 'School tenant session was not found.', [], 401);
}

$input = promotionInput();
$action = strtolower(trim((string)($input['action'] ?? $_GET['action'] ?? '')));

try {
    if ($action === 'meta') {
        if (!promotionCan('view')) {
            throw new RuntimeException('Permission denied.', 403);
        }

        promotionJson(
            true,
            'Promotion metadata loaded.',
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
            throw new RuntimeException('Permission denied.', 403);
        }

        $filters = array_merge($_GET, $input);
        $fromYearId = (int)($filters['from_academic_year_id'] ?? 0);
        $toYearId = (int)($filters['to_academic_year_id'] ?? 0);
        $classId = (int)($filters['current_class_id'] ?? 0);

        if ($fromYearId <= 0 || $toYearId <= 0 || $classId <= 0) {
            throw new InvalidArgumentException(
                'Select From Academic Year, To Academic Year and Current Class.'
            );
        }

        if ($fromYearId === $toYearId) {
            throw new InvalidArgumentException(
                'From Academic Year and To Academic Year must be different.'
            );
        }

        $sourceClass = promotionFindSourceClass(
            $pdo,
            $scope['tenant_id'],
            $fromYearId,
            $classId
        );

        $students = promotionStudents(
            $pdo,
            $scope,
            $fromYearId,
            $toYearId,
            $classId
        );

        $total = count($students);
        $message = $total > 0
            ? $total . ' student' . ($total === 1 ? '' : 's') . ' loaded.'
            : 'No active students were found in '
                . (string)$sourceClass['class_name']
                . ' for the selected academic year.';

        promotionJson(
            true,
            $message,
            [
                'students' => $students,
                'total' => $total,
                'selected_class_name' => (string)$sourceClass['class_name'],
                'already_promoted' => count(
                    array_filter(
                        $students,
                        static fn(array $row): bool => (int)$row['already_promoted'] === 1
                    )
                ),
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
            throw new RuntimeException('Permission denied.', 403);
        }

        $fromYearId = (int)($input['from_academic_year_id'] ?? 0);
        $toYearId = (int)($input['to_academic_year_id'] ?? 0);
        $currentClassId = (int)($input['current_class_id'] ?? 0);
        $targetClassName = trim((string)($input['target_class_name'] ?? ''));
        $studentIds = $input['student_ids'] ?? [];

        if ($fromYearId <= 0 || $toYearId <= 0 || $currentClassId <= 0) {
            throw new InvalidArgumentException(
                'Select From Academic Year, To Academic Year and Current Class.'
            );
        }

        if ($fromYearId === $toYearId) {
            throw new InvalidArgumentException(
                'From Academic Year and To Academic Year must be different.'
            );
        }

        if ($targetClassName === '') {
            throw new InvalidArgumentException('Select Promote To Class.');
        }

        if (!is_array($studentIds) || $studentIds === []) {
            throw new InvalidArgumentException('Select at least one student.');
        }

        $studentIds = array_values(
            array_unique(
                array_filter(
                    array_map('intval', $studentIds),
                    static fn(int $value): bool => $value > 0
                )
            )
        );

        if ($studentIds === []) {
            throw new InvalidArgumentException('Select at least one valid student.');
        }

        $fromYear = promotionFindYear($pdo, $scope['tenant_id'], $fromYearId);
        $toYear = promotionFindYear($pdo, $scope['tenant_id'], $toYearId);
        $sourceClass = promotionFindSourceClass(
            $pdo,
            $scope['tenant_id'],
            $fromYearId,
            $currentClassId
        );

        if (strcasecmp((string)$sourceClass['class_name'], $targetClassName) === 0) {
            throw new InvalidArgumentException(
                'Promote To Class must be different from the Current Class.'
            );
        }

        $pdo->beginTransaction();

        $targetClass = promotionResolveTargetClass(
            $pdo,
            $scope['tenant_id'],
            $toYearId,
            $targetClassName,
            max(0, (int)$sourceClass['display_order'] + 10)
        );

        $promoted = 0;
        $skipped = 0;
        $errors = [];

        $legacyClassTable = promotionTableExists($pdo, 'class_management_classes');
        $legacySectionTable = promotionTableExists($pdo, 'school_sections');

        $legacyClassJoin = $legacyClassTable
            ? "LEFT JOIN class_management_classes cmc
                  ON cmc.id = e.class_id
                 AND cmc.tenant_id = e.tenant_id"
            : '';

        $legacySectionJoin = $legacySectionTable
            ? "LEFT JOIN school_sections ss
                  ON ss.id = e.section_id
                 AND ss.tenant_id = e.tenant_id"
            : '';

        $legacyClassMatch = $legacyClassTable
            ? "OR LOWER(TRIM(cmc.class_name)) = LOWER(TRIM(:source_class_name_legacy))"
            : '';

        $sectionNameSql = $legacySectionTable
            ? "COALESCE(
                    NULLIF(CASE WHEN sec.class_id = e.class_id THEN sec.section_name END, ''),
                    NULLIF(ss.section_name, ''),
                    NULLIF(sec.section_name, ''),
                    'General'
                )"
            : "COALESCE(NULLIF(sec.section_name, ''), 'General')";

        $sectionCapacitySql = $legacySectionTable
            ? "COALESCE(
                    CASE WHEN sec.class_id = e.class_id THEN sec.capacity END,
                    ss.maximum_student_capacity,
                    sec.capacity
                )"
            : "sec.capacity";

        $sourceStatement = $pdo->prepare(
            "SELECT
                e.*,
                {$sectionNameSql} AS section_name,
                {$sectionCapacitySql} AS capacity
             FROM student_enrollments e
             INNER JOIN students s
                ON s.id = e.student_id
               AND s.tenant_id = e.tenant_id
             LEFT JOIN classes c
                ON c.id = e.class_id
               AND c.tenant_id = e.tenant_id
             {$legacyClassJoin}
             LEFT JOIN sections sec
                ON sec.id = e.section_id
               AND sec.tenant_id = e.tenant_id
             {$legacySectionJoin}
             WHERE e.student_id = :student_id
               AND e.tenant_id = :tenant_id
               AND e.academic_year_id = :academic_year_id
               AND (
                    e.class_id = :source_class_id
                    OR LOWER(TRIM(c.class_name)) = LOWER(TRIM(:source_class_name))
                    {$legacyClassMatch}
                   )
               AND LOWER(e.enrollment_status) = 'active'
               AND LOWER(s.status) = 'active'
               AND s.deleted_at IS NULL
               AND (
                    :branch_scope = 0
                    OR s.branch_id = :branch_value
                    OR s.branch_id IS NULL
                   )
             LIMIT 1"
        );

        $targetFindStatement = $pdo->prepare(
            'SELECT id
             FROM student_enrollments
             WHERE student_id = :student_id
               AND tenant_id = :tenant_id
               AND academic_year_id = :academic_year_id
             LIMIT 1'
        );

        $targetUpdateStatement = $pdo->prepare(
            "UPDATE student_enrollments SET
                class_id = :class_id,
                section_id = :section_id,
                roll_no = :roll_no,
                enrollment_status = 'active',
                enrolled_on = :enrolled_on
             WHERE id = :id
               AND tenant_id = :tenant_id"
        );

        $targetInsertStatement = $pdo->prepare(
            "INSERT INTO student_enrollments
                (tenant_id, student_id, academic_year_id, class_id, section_id,
                 roll_no, enrollment_status, enrolled_on)
             VALUES
                (:tenant_id, :student_id, :academic_year_id, :class_id, :section_id,
                 :roll_no, 'active', :enrolled_on)"
        );

        $sourcePromoteStatement = $pdo->prepare(
            "UPDATE student_enrollments
             SET enrollment_status = 'promoted'
             WHERE id = :id
               AND tenant_id = :tenant_id"
        );

        foreach ($studentIds as $studentId) {
            $savepoint = 'student_' . $studentId;
            $pdo->exec('SAVEPOINT ' . $savepoint);

            try {
                $sourceParams = [
                    'student_id' => $studentId,
                    'tenant_id' => $scope['tenant_id'],
                    'academic_year_id' => $fromYearId,
                    'source_class_id' => (int)$sourceClass['id'],
                    'source_class_name' => (string)$sourceClass['class_name'],
                    'branch_scope' => $scope['branch_id'],
                    'branch_value' => $scope['branch_id'],
                ];
                if ($legacyClassTable) {
                    $sourceParams['source_class_name_legacy'] = (string)$sourceClass['class_name'];
                }
                $sourceStatement->execute($sourceParams);

                $sourceEnrollment = $sourceStatement->fetch(PDO::FETCH_ASSOC);
                if (!$sourceEnrollment) {
                    $skipped++;
                    $errors[] = 'Student #' . $studentId . ': active enrollment not found.';
                    continue;
                }

                $targetSectionId = promotionResolveTargetSection(
                    $pdo,
                    $scope['tenant_id'],
                    (int)$targetClass['id'],
                    (string)($sourceEnrollment['section_name'] ?? 'General'),
                    $sourceEnrollment['capacity'] !== null
                        ? (int)$sourceEnrollment['capacity']
                        : null
                );

                $targetFindStatement->execute([
                    'student_id' => $studentId,
                    'tenant_id' => $scope['tenant_id'],
                    'academic_year_id' => $toYearId,
                ]);
                $targetEnrollmentId = (int)$targetFindStatement->fetchColumn();

                $targetData = [
                    'tenant_id' => $scope['tenant_id'],
                    'student_id' => $studentId,
                    'academic_year_id' => $toYearId,
                    'class_id' => (int)$targetClass['id'],
                    'section_id' => $targetSectionId,
                    'roll_no' => $sourceEnrollment['roll_no'] ?: null,
                    'enrolled_on' => date('Y-m-d'),
                ];

                if ($targetEnrollmentId > 0) {
                    $targetUpdateStatement->execute([
                        'class_id' => $targetData['class_id'],
                        'section_id' => $targetData['section_id'],
                        'roll_no' => $targetData['roll_no'],
                        'enrolled_on' => $targetData['enrolled_on'],
                        'id' => $targetEnrollmentId,
                        'tenant_id' => $scope['tenant_id'],
                    ]);
                } else {
                    $targetInsertStatement->execute($targetData);
                }

                $sourcePromoteStatement->execute([
                    'id' => (int)$sourceEnrollment['id'],
                    'tenant_id' => $scope['tenant_id'],
                ]);

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
                'No students were promoted. ' . implode(' ', array_slice($errors, 0, 3)),
                422
            );
        }

        $pdo->commit();

        promotionJson(
            true,
            $promoted . ' student' . ($promoted === 1 ? '' : 's')
                . ' promoted successfully from '
                . $fromYear['year_name'] . ' to ' . $toYear['year_name'] . '.',
            [
                'promoted' => $promoted,
                'skipped' => $skipped,
                'errors' => $errors,
                'target_class_id' => (int)$targetClass['id'],
                'target_class_name' => (string)$targetClass['class_name'],
            ]
        );
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
        $status >= 400 && $status <= 599 ? $status : 403
    );
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('student-promotion.php: ' . $exception->getMessage());

    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $message = str_contains($host, 'localhost')
        || str_contains($host, '127.0.0.1')
        ? 'Student promotion failed: ' . $exception->getMessage()
        : 'Unable to complete the student promotion request.';

    promotionJson(false, $message, [], 500);
}

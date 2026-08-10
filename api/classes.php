<?php
declare(strict_types=1);

/* Build: 2026-08-07-classes-permission-visibility-v15 */

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

function classScope(array $user, ClassManagementController $controller): array
{
    return [
        'tenant_id' => max(0, $controller->tenantId()),
        'branch_id' => max(
            0,
            (int)(
                $user['branch_id']
                ?? $_SESSION['branch_id']
                ?? 0
            )
        ),
        'user_id' => max(0, $controller->userId()),
    ];
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

function classCurrentAcademicYearId(PDO $pdo, int $tenantId): int
{
    if (
        !classTableExists($pdo, 'academic_years')
        || $tenantId <= 0
    ) {
        return 0;
    }

    $statement = $pdo->prepare(
        "SELECT id
         FROM academic_years
         WHERE tenant_id = :tenant_id
         ORDER BY is_current DESC,
                  status = 'active' DESC,
                  start_date DESC,
                  id DESC
         LIMIT 1"
    );
    $statement->execute(['tenant_id' => $tenantId]);

    return (int)$statement->fetchColumn();
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
         WHERE " . implode(' AND ', $studentConditions)
    );
    $statement->execute($params);
    $placements = $statement->fetchAll(PDO::FETCH_ASSOC);

    $find = $pdo->prepare(
        "SELECT id
         FROM class_management_classes
         WHERE tenant_id = :tenant_id
           AND academic_year_id = :academic_year_id
           AND LOWER(TRIM(class_name)) = LOWER(TRIM(:class_name))
           AND LOWER(TRIM(section_name)) = LOWER(TRIM(:section_name))
         LIMIT 1"
    );

    $insert = $pdo->prepare(
        "INSERT INTO class_management_classes (
            tenant_id,
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
            $scope['tenant_id']
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

    $conditions = ['e.tenant_id = :tenant_id'];
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
           AND tenant_id = :tenant_id"
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
        $classMeta = $controller->meta();
        $classMeta = is_array($classMeta) ? $classMeta : [];

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
                    'tenant_id' => $controller->tenantId(),
                    'branch_id' => $controller->branchId(),
                ],
            ]
        );
    }

    if ($action === 'list') {
        $scope = classScope($user, $controller);

        classSyncFromStudentEnrollments(
            $pdo,
            $scope
        );

        $filters = array_merge($_GET, $input);
        $classes = $controller->list($filters);

        $selectedAcademicYearId = (
            isset($filters['academic_year_id'])
            && is_numeric($filters['academic_year_id'])
        )
            ? (int)$filters['academic_year_id']
            : 0;

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

        classJson(
            true,
            'Classes loaded with live Student List strength.',
            [
                'classes' => $classes,
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
        classJson(
            true,
            'Records loaded.',
            [
                'records' => $controller->related(
                    trim(
                        (string)(
                            $input['type']
                            ?? $_GET['type']
                            ?? ''
                        )
                    ),
                    (int)(
                        $input['class_id']
                        ?? $_GET['class_id']
                        ?? 0
                    )
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

        $result = $controller->save($input);

        classJson(
            true,
            $isEdit
                ? 'Class updated successfully.'
                : 'Class created successfully.',
            $result
        );
    }

    if ($action === 'delete') {
        $controller->delete((int)($input['id'] ?? 0));
        classJson(true, 'Class deleted successfully.');
    }

    if ($action === 'save_related') {
        $id = $controller->saveRelated(
            trim((string)($input['type'] ?? '')),
            $input
        );

        classJson(
            true,
            'Assignment saved successfully.',
            ['id' => $id]
        );
    }

    if ($action === 'delete_related') {
        $controller->deleteRelated(
            trim((string)($input['type'] ?? '')),
            (int)($input['id'] ?? 0)
        );

        classJson(true, 'Assignment deleted successfully.');
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

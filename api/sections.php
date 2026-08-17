<?php
declare(strict_types=1);

/* Build: 2026-08-12-sections-canonical-class-based-v1 */

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

if (!defined('SCHOOL_API_PAGE_KEY')) {
    define('SCHOOL_API_PAGE_KEY', 'sections');
}

require_once dirname(__DIR__) . '/includes/bootstrap.php';

function sectionsJson(
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

function sectionsInput(): array
{
    $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));

    if (str_contains($contentType, 'application/json')) {
        $decoded = json_decode((string)file_get_contents('php://input'), true);
        return is_array($decoded) ? $decoded : [];
    }

    return $_POST;
}

function sectionsCsrf(array $input): void
{
    $token = trim((string)($input['csrf_token'] ?? ''));

    $valid = function_exists('csrf_is_valid')
        ? csrf_is_valid($token)
        : (
            isset($_SESSION['csrf_token'])
            && is_string($_SESSION['csrf_token'])
            && $token !== ''
            && hash_equals((string)$_SESSION['csrf_token'], $token)
        );

    if (!$valid) {
        sectionsJson(false, 'Invalid or expired CSRF token.', [], 419);
    }
}

function sectionsTable(PDO $pdo, string $table): bool
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

function sectionsColumn(PDO $pdo, string $table, string $column): bool
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

function sectionsUser(): array
{
    $user = function_exists('current_user') ? current_user() : [];
    return is_array($user) ? $user : [];
}

function sectionsScope(array $user): array
{
    return [
        'tenant_id' => max(0, (int)(
            $user['tenant_id']
            ?? $user['school_id']
            ?? $_SESSION['tenant_id']
            ?? $_SESSION['school_id']
            ?? 0
        )),
        'branch_id' => max(0, (int)(
            $user['branch_id']
            ?? $_SESSION['branch_id']
            ?? 0
        )),
        'user_id' => max(0, (int)(
            $user['id']
            ?? $user['user_id']
            ?? $_SESSION['user_id']
            ?? 0
        )),
    ];
}

function sectionsPlatformFullAccess(array $user): bool
{
    if (function_exists('is_super_admin')) {
        try {
            if ((bool)is_super_admin()) {
                return true;
            }
        } catch (Throwable) {
            // Continue with role matching.
        }
    }

    $roleText = strtolower(trim((string)(
        $user['role_key']
        ?? $user['role_name']
        ?? $user['role']
        ?? $user['user_type']
        ?? $_SESSION['role_key']
        ?? $_SESSION['role_name']
        ?? $_SESSION['role']
        ?? ''
    )));

    $roleKey = preg_replace('/[^a-z0-9]+/', '_', $roleText) ?? '';

    return in_array(
        trim($roleKey, '_'),
        [
            'platform_owner',
            'platformowner',
            'platform_admin',
            'platformadministrator',
            'super_admin',
            'superadministrator',
        ],
        true
    );
}

function sectionsCan(array $user, string $action): bool
{
    if (sectionsPlatformFullAccess($user)) {
        return true;
    }

    $action = strtolower(trim($action));
    if ($action === 'add') {
        $action = 'create';
    }

    if (function_exists('school_current_page_capabilities')) {
        $capabilities = school_current_page_capabilities('sections');
        if (is_array($capabilities)) {
            if ($action === 'create') {
                return !empty($capabilities['create']) || !empty($capabilities['add']);
            }
            return !empty($capabilities[$action]);
        }
    }

    if (function_exists('has_permission')) {
        $aliases = $action === 'create' ? ['create', 'add'] : [$action];
        foreach (['sections', 'academics.sections', 'student_management'] as $module) {
            foreach ($aliases as $permissionAction) {
                try {
                    if (has_permission($module, $permissionAction)) {
                        return true;
                    }
                } catch (Throwable) {
                    // Try next alias/module.
                }
            }
        }
    }

    return false;
}

function sectionsAcademicYears(PDO $pdo, int $tenantId): array
{
    $statement = $pdo->prepare(
        "SELECT id, academic_year_code, year_name, start_date, end_date, is_current, status
         FROM academic_years
         WHERE tenant_id = :tenant_id
         ORDER BY is_current DESC, start_date DESC, id DESC"
    );
    $statement->execute(['tenant_id' => $tenantId]);

    return array_map(
        static function (array $row): array {
            $row['id'] = (int)$row['id'];
            $row['is_current'] = (int)($row['is_current'] ?? 0);
            return $row;
        },
        $statement->fetchAll(PDO::FETCH_ASSOC)
    );
}

function sectionsClasses(PDO $pdo, int $tenantId): array
{
    $statement = $pdo->prepare(
        "SELECT id, academic_year_id, class_name, display_order, status
         FROM classes
         WHERE tenant_id = :tenant_id
         ORDER BY academic_year_id DESC, display_order, class_name, id"
    );
    $statement->execute(['tenant_id' => $tenantId]);

    return array_map(
        static function (array $row): array {
            $row['id'] = (int)$row['id'];
            $row['academic_year_id'] = (int)$row['academic_year_id'];
            $row['display_order'] = (int)($row['display_order'] ?? 0);
            return $row;
        },
        $statement->fetchAll(PDO::FETCH_ASSOC)
    );
}

function sectionsTeachers(PDO $pdo, int $tenantId): array
{
    if (!sectionsTable($pdo, 'users')) {
        return [];
    }

    $hasRoles = sectionsTable($pdo, 'roles');

    if ($hasRoles) {
        $statement = $pdo->prepare(
            "SELECT DISTINCT
                u.id,
                COALESCE(NULLIF(TRIM(u.name), ''), NULLIF(TRIM(u.username), ''), CONCAT('User #', u.id)) AS teacher_name
             FROM users u
             LEFT JOIN roles r
               ON r.id = u.role_id
              AND (r.tenant_id = u.tenant_id OR r.tenant_id IS NULL)
             WHERE u.tenant_id = :tenant_id
               AND LOWER(COALESCE(u.status, 'active')) = 'active'
               AND (
                    LOWER(COALESCE(r.role_key, '')) LIKE '%teacher%'
                    OR LOWER(COALESCE(r.role_name, '')) LIKE '%teacher%'
                    OR EXISTS (
                        SELECT 1
                        FROM staff_members sm
                        WHERE sm.tenant_id = u.tenant_id
                          AND sm.user_id = u.id
                          AND LOWER(COALESCE(sm.status, 'active')) = 'active'
                    )
               )
             ORDER BY teacher_name"
        );
    } else {
        $statement = $pdo->prepare(
            "SELECT
                id,
                COALESCE(NULLIF(TRIM(name), ''), NULLIF(TRIM(username), ''), CONCAT('User #', id)) AS teacher_name
             FROM users
             WHERE tenant_id = :tenant_id
               AND LOWER(COALESCE(status, 'active')) = 'active'
             ORDER BY teacher_name"
        );
    }

    try {
        $statement->execute(['tenant_id' => $tenantId]);
        return array_map(
            static function (array $row): array {
                $row['id'] = (int)$row['id'];
                return $row;
            },
            $statement->fetchAll(PDO::FETCH_ASSOC)
        );
    } catch (Throwable) {
        // staff_members may not exist in older installations. Use all active users.
        $fallback = $pdo->prepare(
            "SELECT
                id,
                COALESCE(NULLIF(TRIM(name), ''), NULLIF(TRIM(username), ''), CONCAT('User #', id)) AS teacher_name
             FROM users
             WHERE tenant_id = :tenant_id
               AND LOWER(COALESCE(status, 'active')) = 'active'
             ORDER BY teacher_name"
        );
        $fallback->execute(['tenant_id' => $tenantId]);
        return array_map(
            static function (array $row): array {
                $row['id'] = (int)$row['id'];
                return $row;
            },
            $fallback->fetchAll(PDO::FETCH_ASSOC)
        );
    }
}

function sectionsMediums(PDO $pdo, int $tenantId): array
{
    $values = [];

    if (sectionsTable($pdo, 'school_sections')) {
        $statement = $pdo->prepare(
            "SELECT DISTINCT TRIM(medium) AS medium
             FROM school_sections
             WHERE tenant_id = :tenant_id
               AND TRIM(COALESCE(medium, '')) <> ''"
        );
        $statement->execute(['tenant_id' => $tenantId]);
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $value) {
            $value = trim((string)$value);
            if ($value !== '') {
                $values[strtolower($value)] = $value;
            }
        }
    }

    if (sectionsTable($pdo, 'class_management_classes')) {
        $statement = $pdo->prepare(
            "SELECT DISTINCT TRIM(medium) AS medium
             FROM class_management_classes
             WHERE tenant_id = :tenant_id
               AND TRIM(COALESCE(medium, '')) <> ''"
        );
        $statement->execute(['tenant_id' => $tenantId]);
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $value) {
            $value = trim((string)$value);
            if ($value !== '') {
                $values[strtolower($value)] = $value;
            }
        }
    }

    if ($values === []) {
        $values['english'] = 'English';
    }

    natcasesort($values);
    return array_values($values);
}

function sectionsMeta(PDO $pdo, array $scope): array
{
    return [
        'academic_years' => sectionsAcademicYears($pdo, $scope['tenant_id']),
        'classes' => sectionsClasses($pdo, $scope['tenant_id']),
        'teachers' => sectionsTeachers($pdo, $scope['tenant_id']),
        'mediums' => sectionsMediums($pdo, $scope['tenant_id']),
    ];
}

function sectionsList(PDO $pdo, array $scope, array $filters): array
{
    $where = ['ss.tenant_id = :tenant_id'];
    $params = ['tenant_id' => $scope['tenant_id']];

    $year = trim((string)($filters['academic_year_id'] ?? ''));
    if ($year !== '' && strtolower($year) !== 'all' && ctype_digit($year) && (int)$year > 0) {
        $where[] = 'ss.academic_year_id = :academic_year_id';
        $params['academic_year_id'] = (int)$year;
    }

    $class = trim((string)($filters['class_id'] ?? ''));
    if ($class !== '' && strtolower($class) !== 'all' && ctype_digit($class) && (int)$class > 0) {
        $where[] = 'ss.class_id = :class_id';
        $params['class_id'] = (int)$class;
    }

    $medium = trim((string)($filters['medium'] ?? ''));
    if ($medium !== '' && strtolower($medium) !== 'all') {
        $where[] = 'LOWER(TRIM(ss.medium)) = LOWER(TRIM(:medium))';
        $params['medium'] = $medium;
    }

    $shift = trim((string)($filters['shift_name'] ?? ''));
    if ($shift !== '' && strtolower($shift) !== 'all') {
        $where[] = 'LOWER(TRIM(COALESCE(ss.shift_name, \'\'))) = LOWER(TRIM(:shift_name))';
        $params['shift_name'] = $shift;
    }

    $status = strtolower(trim((string)($filters['status'] ?? '')));
    if ($status !== '' && $status !== 'all' && in_array($status, ['active', 'inactive', 'archived'], true)) {
        $where[] = 'ss.status = :status';
        $params['status'] = $status;
    }

    $search = trim((string)($filters['search'] ?? ''));
    if ($search !== '') {
        $where[] = "(
            ss.section_name LIKE :search
            OR ss.section_code LIKE :search
            OR ss.class_name_snapshot LIKE :search
            OR COALESCE(ss.room_number, '') LIKE :search
            OR COALESCE(ss.class_teacher_name, '') LIKE :search
            OR COALESCE(ss.medium, '') LIKE :search
            OR COALESCE(ss.shift_name, '') LIKE :search
        )";
        $params['search'] = '%' . $search . '%';
    }

    $statement = $pdo->prepare(
        "SELECT
            ss.id,
            ss.tenant_id,
            ss.academic_year_id,
            ss.class_id,
            COALESCE(NULLIF(TRIM(c.class_name), ''), ss.class_name_snapshot) AS class_name_snapshot,
            ss.section_name,
            ss.section_code,
            ss.medium,
            ss.shift_name,
            ss.room_number,
            ss.maximum_student_capacity,
            ss.class_teacher_user_id,
            ss.class_teacher_name,
            ss.status,
            ss.description,
            ss.display_order,
            ss.created_by,
            ss.updated_by,
            ss.created_at,
            ss.updated_at,
            ay.year_name AS academic_year_name,
            COALESCE(st.live_strength, 0) AS current_strength
         FROM school_sections ss
         LEFT JOIN academic_years ay
           ON ay.id = ss.academic_year_id
          AND ay.tenant_id = ss.tenant_id
         LEFT JOIN classes c
           ON c.id = ss.class_id
          AND c.tenant_id = ss.tenant_id
         LEFT JOIN (
            SELECT
                e.tenant_id,
                e.academic_year_id,
                e.class_id,
                e.section_id,
                COUNT(DISTINCT e.student_id) AS live_strength
            FROM student_enrollments e
            INNER JOIN students s
              ON s.id = e.student_id
             AND s.tenant_id = e.tenant_id
            WHERE LOWER(e.enrollment_status) = 'active'
              AND LOWER(s.status) = 'active'
              AND s.deleted_at IS NULL
            GROUP BY e.tenant_id, e.academic_year_id, e.class_id, e.section_id
         ) st
           ON st.tenant_id = ss.tenant_id
          AND st.academic_year_id = ss.academic_year_id
          AND st.class_id = ss.class_id
          AND st.section_id = (
                SELECT sec.id
                FROM sections sec
                WHERE sec.tenant_id = ss.tenant_id
                  AND sec.class_id = ss.class_id
                  AND LOWER(TRIM(sec.section_name)) = LOWER(TRIM(ss.section_name))
                LIMIT 1
          )
         WHERE " . implode(' AND ', $where) . "
         ORDER BY ay.is_current DESC,
                  ay.start_date DESC,
                  c.display_order,
                  ss.display_order,
                  ss.section_name,
                  ss.id"
    );
    $statement->execute($params);

    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        foreach (['id', 'tenant_id', 'academic_year_id', 'class_id', 'maximum_student_capacity', 'class_teacher_user_id', 'display_order', 'current_strength'] as $key) {
            $row[$key] = (int)($row[$key] ?? 0);
        }
    }
    unset($row);

    return $rows;
}

function sectionsFindClass(PDO $pdo, int $tenantId, int $academicYearId, int $classId): array
{
    $statement = $pdo->prepare(
        "SELECT id, academic_year_id, class_name, display_order, status
         FROM classes
         WHERE id = :id
           AND tenant_id = :tenant_id
           AND academic_year_id = :academic_year_id
         LIMIT 1"
    );
    $statement->execute([
        'id' => $classId,
        'tenant_id' => $tenantId,
        'academic_year_id' => $academicYearId,
    ]);

    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        throw new InvalidArgumentException(
            'Selected Class does not belong to the selected Academic Year.'
        );
    }

    return $row;
}

function sectionsTeacherName(PDO $pdo, int $tenantId, int $teacherId): ?string
{
    if ($teacherId <= 0) {
        return null;
    }

    $statement = $pdo->prepare(
        "SELECT COALESCE(NULLIF(TRIM(name), ''), NULLIF(TRIM(username), ''), CONCAT('User #', id))
         FROM users
         WHERE id = :id
           AND tenant_id = :tenant_id
         LIMIT 1"
    );
    $statement->execute([
        'id' => $teacherId,
        'tenant_id' => $tenantId,
    ]);

    $name = $statement->fetchColumn();
    if ($name === false) {
        throw new InvalidArgumentException('Selected Class Teacher is invalid.');
    }

    return trim((string)$name) ?: null;
}

function sectionsValidateShift(PDO $pdo, int $tenantId, string $shiftName): void
{
    $shiftName = trim($shiftName);
    if ($shiftName === '' || !sectionsTable($pdo, 'school_shift_settings')) {
        return;
    }

    $statement = $pdo->prepare(
        "SELECT COUNT(*)
         FROM school_shift_settings
         WHERE tenant_id = :tenant_id
           AND LOWER(TRIM(shift_name)) = LOWER(TRIM(:shift_name))
           AND is_enabled = 1"
    );
    $statement->execute([
        'tenant_id' => $tenantId,
        'shift_name' => $shiftName,
    ]);

    if ((int)$statement->fetchColumn() === 0) {
        throw new InvalidArgumentException(
            'Selected Shift Time is not enabled in General Settings.'
        );
    }
}

function sectionsCanonicalSectionId(
    PDO $pdo,
    int $tenantId,
    int $classId,
    string $sectionName
): int {
    $statement = $pdo->prepare(
        "SELECT id
         FROM sections
         WHERE tenant_id = :tenant_id
           AND class_id = :class_id
           AND LOWER(TRIM(section_name)) = LOWER(TRIM(:section_name))
         LIMIT 1"
    );
    $statement->execute([
        'tenant_id' => $tenantId,
        'class_id' => $classId,
        'section_name' => $sectionName,
    ]);

    return (int)($statement->fetchColumn() ?: 0);
}

function sectionsStudentLinks(
    PDO $pdo,
    int $tenantId,
    int $academicYearId,
    int $classId,
    int $sectionId
): int {
    if ($sectionId <= 0 || !sectionsTable($pdo, 'student_enrollments')) {
        return 0;
    }

    $statement = $pdo->prepare(
        "SELECT COUNT(*)
         FROM student_enrollments
         WHERE tenant_id = :tenant_id
           AND academic_year_id = :academic_year_id
           AND class_id = :class_id
           AND section_id = :section_id"
    );
    $statement->execute([
        'tenant_id' => $tenantId,
        'academic_year_id' => $academicYearId,
        'class_id' => $classId,
        'section_id' => $sectionId,
    ]);

    return (int)$statement->fetchColumn();
}

function sectionsUniqueManagementCode(
    PDO $pdo,
    int $tenantId,
    int $academicYearId,
    int $classId,
    string $sectionName,
    string $preferred = ''
): string {
    $preferred = strtoupper(trim($preferred));
    $preferred = preg_replace('/[^A-Z0-9_-]+/', '-', $preferred) ?? '';
    $preferred = trim($preferred, '-_');

    $sectionPart = strtoupper(preg_replace('/[^A-Z0-9]+/', '', $sectionName) ?? '');
    $base = $preferred !== ''
        ? substr($preferred, 0, 30)
        : substr('CLS-' . $classId . ($sectionPart !== '' ? '-' . $sectionPart : ''), 0, 30);

    if ($base === '') {
        $base = 'CLS-' . $classId;
    }

    $code = $base;
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
        $code = substr($base, 0, max(1, 30 - strlen($tail))) . $tail;
    }
}

function sectionsSyncManagementRow(
    PDO $pdo,
    array $scope,
    array $class,
    array $sectionData
): void {
    if (!sectionsTable($pdo, 'class_management_classes')) {
        return;
    }

    $find = $pdo->prepare(
        "SELECT id, class_code
         FROM class_management_classes
         WHERE tenant_id = :tenant_id
           AND academic_year_id = :academic_year_id
           AND LOWER(TRIM(class_name)) = LOWER(TRIM(:class_name))
           AND LOWER(TRIM(section_name)) = LOWER(TRIM(:section_name))
         LIMIT 1"
    );
    $find->execute([
        'tenant_id' => $scope['tenant_id'],
        'academic_year_id' => (int)$class['academic_year_id'],
        'class_name' => (string)$class['class_name'],
        'section_name' => (string)$sectionData['section_name'],
    ]);
    $existing = $find->fetch(PDO::FETCH_ASSOC);

    $managementStatus = $sectionData['status'] === 'active' ? 'active' : 'inactive';
    if ($sectionData['status'] === 'archived') {
        $managementStatus = 'archived';
    }

    if (is_array($existing)) {
        $statement = $pdo->prepare(
            "UPDATE class_management_classes SET
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
               AND tenant_id = :tenant_id"
        );
        $statement->execute([
            'medium' => $sectionData['medium'],
            'shift_name' => $sectionData['shift_name'] !== '' ? $sectionData['shift_name'] : 'General',
            'class_teacher_user_id' => $sectionData['class_teacher_user_id'] ?: null,
            'class_teacher_name' => $sectionData['class_teacher_name'],
            'classroom_name' => $sectionData['room_number'],
            'maximum_strength' => $sectionData['maximum_student_capacity'],
            'status' => $managementStatus,
            'description' => $sectionData['description'],
            'display_order' => $sectionData['display_order'],
            'updated_by' => $scope['user_id'] ?: null,
            'id' => (int)$existing['id'],
            'tenant_id' => $scope['tenant_id'],
        ]);
        return;
    }

    $classCode = sectionsUniqueManagementCode(
        $pdo,
        $scope['tenant_id'],
        (int)$class['academic_year_id'],
        (int)$class['id'],
        (string)$sectionData['section_name'],
        ''
    );

    $statement = $pdo->prepare(
        "INSERT INTO class_management_classes(
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
         ) VALUES(
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
            :status,
            :description,
            :display_order,
            :created_by,
            :updated_by
         )"
    );
    $statement->execute([
        'tenant_id' => $scope['tenant_id'],
        'academic_year_id' => (int)$class['academic_year_id'],
        'class_name' => (string)$class['class_name'],
        'class_code' => $classCode,
        'section_name' => $sectionData['section_name'],
        'medium' => $sectionData['medium'],
        'shift_name' => $sectionData['shift_name'] !== '' ? $sectionData['shift_name'] : 'General',
        'class_teacher_user_id' => $sectionData['class_teacher_user_id'] ?: null,
        'class_teacher_name' => $sectionData['class_teacher_name'],
        'classroom_name' => $sectionData['room_number'],
        'maximum_strength' => $sectionData['maximum_student_capacity'],
        'status' => $managementStatus,
        'description' => $sectionData['description'],
        'display_order' => $sectionData['display_order'],
        'created_by' => $scope['user_id'] ?: null,
        'updated_by' => $scope['user_id'] ?: null,
    ]);
}

function sectionsDeleteOldManagementRow(
    PDO $pdo,
    array $scope,
    array $oldRow
): void {
    if (!sectionsTable($pdo, 'class_management_classes')) {
        return;
    }

    $statement = $pdo->prepare(
        "DELETE FROM class_management_classes
         WHERE tenant_id = :tenant_id
           AND academic_year_id = :academic_year_id
           AND LOWER(TRIM(class_name)) = LOWER(TRIM(:class_name))
           AND LOWER(TRIM(section_name)) = LOWER(TRIM(:section_name))"
    );
    $statement->execute([
        'tenant_id' => $scope['tenant_id'],
        'academic_year_id' => (int)$oldRow['academic_year_id'],
        'class_name' => (string)$oldRow['class_name_snapshot'],
        'section_name' => (string)$oldRow['section_name'],
    ]);
}

function sectionsSave(PDO $pdo, array $scope, array $input): array
{
    $tenantId = $scope['tenant_id'];
    $userId = $scope['user_id'];
    $id = max(0, (int)($input['id'] ?? 0));

    $academicYearId = max(0, (int)($input['academic_year_id'] ?? 0));
    $classId = max(0, (int)($input['class_id'] ?? 0));
    $sectionName = trim((string)($input['section_name'] ?? ''));
    $sectionCode = strtoupper(trim((string)($input['section_code'] ?? '')));
    $medium = trim((string)($input['medium'] ?? '')) ?: 'English';
    $shiftName = trim((string)($input['shift_name'] ?? ''));
    $roomNumber = trim((string)($input['room_number'] ?? ''));
    $capacity = max(1, min(500, (int)($input['maximum_student_capacity'] ?? 40)));
    $teacherId = max(0, (int)($input['class_teacher_user_id'] ?? 0));
    $status = strtolower(trim((string)($input['status'] ?? 'active')));
    $description = trim((string)($input['description'] ?? ''));
    $displayOrder = max(0, (int)($input['display_order'] ?? 0));

    if ($academicYearId <= 0) {
        throw new InvalidArgumentException('Select an Academic Year.');
    }
    if ($classId <= 0) {
        throw new InvalidArgumentException('Select a Class.');
    }
    if ($sectionName === '') {
        throw new InvalidArgumentException('Section Name is required.');
    }
    if ($sectionCode === '') {
        throw new InvalidArgumentException('Section Code is required.');
    }
    if (!in_array($status, ['active', 'inactive', 'archived'], true)) {
        throw new InvalidArgumentException('Invalid Section status.');
    }

    $sectionName = mb_substr($sectionName, 0, 100);
    $sectionCode = mb_substr($sectionCode, 0, 30);
    $medium = mb_substr($medium, 0, 50);
    $shiftName = mb_substr($shiftName, 0, 50);
    $roomNumber = mb_substr($roomNumber, 0, 50);
    $description = mb_substr($description, 0, 5000);

    sectionsValidateShift($pdo, $tenantId, $shiftName);
    $class = sectionsFindClass($pdo, $tenantId, $academicYearId, $classId);
    $teacherName = sectionsTeacherName($pdo, $tenantId, $teacherId);

    $oldRow = null;
    $oldCanonicalSectionId = 0;
    if ($id > 0) {
        $statement = $pdo->prepare(
            "SELECT *
             FROM school_sections
             WHERE id = :id
               AND tenant_id = :tenant_id
             LIMIT 1"
        );
        $statement->execute([
            'id' => $id,
            'tenant_id' => $tenantId,
        ]);
        $oldRow = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($oldRow)) {
            throw new InvalidArgumentException('Section record was not found.');
        }

        $oldCanonicalSectionId = sectionsCanonicalSectionId(
            $pdo,
            $tenantId,
            (int)$oldRow['class_id'],
            (string)$oldRow['section_name']
        );

        $identityChanged =
            (int)$oldRow['academic_year_id'] !== $academicYearId
            || (int)$oldRow['class_id'] !== $classId
            || mb_strtolower(trim((string)$oldRow['section_name'])) !== mb_strtolower($sectionName);

        if (
            $identityChanged
            && sectionsStudentLinks(
                $pdo,
                $tenantId,
                (int)$oldRow['academic_year_id'],
                (int)$oldRow['class_id'],
                $oldCanonicalSectionId
            ) > 0
        ) {
            throw new InvalidArgumentException(
                'Students are assigned to this Section. Academic Year, Class or Section Name cannot be changed.'
            );
        }
    }

    $duplicate = $pdo->prepare(
        "SELECT id
         FROM school_sections
         WHERE tenant_id = :tenant_id
           AND academic_year_id = :academic_year_id
           AND class_id = :class_id
           AND LOWER(TRIM(section_name)) = LOWER(TRIM(:section_name))
           AND id <> :id
         LIMIT 1"
    );
    $duplicate->execute([
        'tenant_id' => $tenantId,
        'academic_year_id' => $academicYearId,
        'class_id' => $classId,
        'section_name' => $sectionName,
        'id' => $id,
    ]);
    if ((int)$duplicate->fetchColumn() > 0) {
        throw new InvalidArgumentException(
            'This Section already exists for the selected Class and Academic Year.'
        );
    }

    $codeDuplicate = $pdo->prepare(
        "SELECT id
         FROM school_sections
         WHERE tenant_id = :tenant_id
           AND academic_year_id = :academic_year_id
           AND UPPER(TRIM(section_code)) = UPPER(TRIM(:section_code))
           AND id <> :id
         LIMIT 1"
    );
    $codeDuplicate->execute([
        'tenant_id' => $tenantId,
        'academic_year_id' => $academicYearId,
        'section_code' => $sectionCode,
        'id' => $id,
    ]);
    if ((int)$codeDuplicate->fetchColumn() > 0) {
        throw new InvalidArgumentException(
            'Section Code already exists in the selected Academic Year.'
        );
    }

    $pdo->beginTransaction();

    try {
        $canonicalSectionId = sectionsCanonicalSectionId(
            $pdo,
            $tenantId,
            $classId,
            $sectionName
        );

        $canonicalStatus = $status === 'active' ? 'active' : 'inactive';

        if ($canonicalSectionId > 0) {
            $statement = $pdo->prepare(
                "UPDATE sections
                 SET capacity = :capacity,
                     status = :status
                 WHERE id = :id
                   AND tenant_id = :tenant_id"
            );
            $statement->execute([
                'capacity' => $capacity,
                'status' => $canonicalStatus,
                'id' => $canonicalSectionId,
                'tenant_id' => $tenantId,
            ]);
        } else {
            $statement = $pdo->prepare(
                "INSERT INTO sections(
                    tenant_id,
                    class_id,
                    section_name,
                    capacity,
                    status
                 ) VALUES(
                    :tenant_id,
                    :class_id,
                    :section_name,
                    :capacity,
                    :status
                 )"
            );
            $statement->execute([
                'tenant_id' => $tenantId,
                'class_id' => $classId,
                'section_name' => $sectionName,
                'capacity' => $capacity,
                'status' => $canonicalStatus,
            ]);
            $canonicalSectionId = (int)$pdo->lastInsertId();
        }

        $saveData = [
            'academic_year_id' => $academicYearId,
            'class_id' => $classId,
            'class_name_snapshot' => (string)$class['class_name'],
            'section_name' => $sectionName,
            'section_code' => $sectionCode,
            'medium' => $medium,
            'shift_name' => $shiftName,
            'room_number' => $roomNumber !== '' ? $roomNumber : null,
            'maximum_student_capacity' => $capacity,
            'class_teacher_user_id' => $teacherId ?: null,
            'class_teacher_name' => $teacherName,
            'status' => $status,
            'description' => $description !== '' ? $description : null,
            'display_order' => $displayOrder,
        ];

        if ($id > 0) {
            $statement = $pdo->prepare(
                "UPDATE school_sections SET
                    academic_year_id = :academic_year_id,
                    class_id = :class_id,
                    class_name_snapshot = :class_name_snapshot,
                    section_name = :section_name,
                    section_code = :section_code,
                    medium = :medium,
                    shift_name = :shift_name,
                    room_number = :room_number,
                    maximum_student_capacity = :maximum_student_capacity,
                    class_teacher_user_id = :class_teacher_user_id,
                    class_teacher_name = :class_teacher_name,
                    status = :status,
                    description = :description,
                    display_order = :display_order,
                    updated_by = :updated_by
                 WHERE id = :id
                   AND tenant_id = :tenant_id"
            );
            $statement->execute($saveData + [
                'updated_by' => $userId ?: null,
                'id' => $id,
                'tenant_id' => $tenantId,
            ]);
        } else {
            $statement = $pdo->prepare(
                "INSERT INTO school_sections(
                    tenant_id,
                    academic_year_id,
                    class_id,
                    class_name_snapshot,
                    section_name,
                    section_code,
                    medium,
                    shift_name,
                    room_number,
                    maximum_student_capacity,
                    class_teacher_user_id,
                    class_teacher_name,
                    status,
                    description,
                    display_order,
                    created_by,
                    updated_by
                 ) VALUES(
                    :tenant_id,
                    :academic_year_id,
                    :class_id,
                    :class_name_snapshot,
                    :section_name,
                    :section_code,
                    :medium,
                    :shift_name,
                    :room_number,
                    :maximum_student_capacity,
                    :class_teacher_user_id,
                    :class_teacher_name,
                    :status,
                    :description,
                    :display_order,
                    :created_by,
                    :updated_by
                 )"
            );
            $statement->execute($saveData + [
                'tenant_id' => $tenantId,
                'created_by' => $userId ?: null,
                'updated_by' => $userId ?: null,
            ]);
            $id = (int)$pdo->lastInsertId();
        }

        sectionsSyncManagementRow(
            $pdo,
            $scope,
            $class,
            $saveData
        );

        if (is_array($oldRow)) {
            $identityChanged =
                (int)$oldRow['academic_year_id'] !== $academicYearId
                || (int)$oldRow['class_id'] !== $classId
                || mb_strtolower(trim((string)$oldRow['section_name'])) !== mb_strtolower($sectionName);

            if ($identityChanged) {
                if ($oldCanonicalSectionId > 0) {
                    $links = sectionsStudentLinks(
                        $pdo,
                        $tenantId,
                        (int)$oldRow['academic_year_id'],
                        (int)$oldRow['class_id'],
                        $oldCanonicalSectionId
                    );

                    if ($links === 0) {
                        $pdo->prepare(
                            "DELETE FROM sections
                             WHERE id = :id
                               AND tenant_id = :tenant_id"
                        )->execute([
                            'id' => $oldCanonicalSectionId,
                            'tenant_id' => $tenantId,
                        ]);
                    }
                }

                sectionsDeleteOldManagementRow($pdo, $scope, $oldRow);
            }
        }

        $pdo->commit();

        return [
            'id' => $id,
            'canonical_section_id' => $canonicalSectionId,
            'class_id' => $classId,
            'academic_year_id' => $academicYearId,
        ];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function sectionsDelete(PDO $pdo, array $scope, int $id): void
{
    if ($id <= 0) {
        throw new InvalidArgumentException('Invalid Section selected.');
    }

    $statement = $pdo->prepare(
        "SELECT *
         FROM school_sections
         WHERE id = :id
           AND tenant_id = :tenant_id
         LIMIT 1"
    );
    $statement->execute([
        'id' => $id,
        'tenant_id' => $scope['tenant_id'],
    ]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);

    if (!is_array($row)) {
        throw new InvalidArgumentException('Section record was not found.');
    }

    $canonicalSectionId = sectionsCanonicalSectionId(
        $pdo,
        $scope['tenant_id'],
        (int)$row['class_id'],
        (string)$row['section_name']
    );

    $linked = sectionsStudentLinks(
        $pdo,
        $scope['tenant_id'],
        (int)$row['academic_year_id'],
        (int)$row['class_id'],
        $canonicalSectionId
    );

    if ($linked > 0) {
        throw new InvalidArgumentException(
            'This Section has students assigned to it and cannot be deleted.'
        );
    }

    $pdo->beginTransaction();

    try {
        $pdo->prepare(
            "DELETE FROM school_sections
             WHERE id = :id
               AND tenant_id = :tenant_id"
        )->execute([
            'id' => $id,
            'tenant_id' => $scope['tenant_id'],
        ]);

        if ($canonicalSectionId > 0) {
            $pdo->prepare(
                "DELETE FROM sections
                 WHERE id = :id
                   AND tenant_id = :tenant_id"
            )->execute([
                'id' => $canonicalSectionId,
                'tenant_id' => $scope['tenant_id'],
            ]);
        }

        sectionsDeleteOldManagementRow($pdo, $scope, $row);

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

if (!isset($pdo) || !$pdo instanceof PDO) {
    sectionsJson(false, 'Database connection unavailable.', [], 500);
}

$user = sectionsUser();
$scope = sectionsScope($user);

if ($scope['tenant_id'] <= 0) {
    sectionsJson(false, 'School tenant session was not found.', [], 401);
}

foreach (['academic_years', 'classes', 'sections', 'school_sections'] as $requiredTable) {
    if (!sectionsTable($pdo, $requiredTable)) {
        sectionsJson(
            false,
            'Required database table is missing: ' . $requiredTable . '.',
            [],
            500
        );
    }
}

$input = sectionsInput();
$action = strtolower(trim((string)($input['action'] ?? $_GET['action'] ?? '')));

try {
    if ($action === 'meta') {
        if (!sectionsCan($user, 'view')) {
            throw new RuntimeException('You do not have permission to view sections.', 403);
        }

        $canCreate = sectionsCan($user, 'create');

        sectionsJson(
            true,
            'Section metadata loaded.',
            [
                'csrf_token' => function_exists('csrfToken') ? csrfToken() : '',
                'meta' => sectionsMeta($pdo, $scope),
                'permissions' => [
                    'view' => sectionsCan($user, 'view'),
                    'add' => $canCreate,
                    'create' => $canCreate,
                    'edit' => sectionsCan($user, 'edit'),
                    'delete' => sectionsCan($user, 'delete'),
                    'print' => sectionsCan($user, 'print'),
                    'pdf' => sectionsCan($user, 'pdf'),
                    'export' => sectionsCan($user, 'export'),
                    'platform_full_access' => sectionsPlatformFullAccess($user),
                ],
            ]
        );
    }

    if ($action === 'list') {
        if (!sectionsCan($user, 'view')) {
            throw new RuntimeException('You do not have permission to view sections.', 403);
        }

        sectionsJson(
            true,
            'Sections loaded.',
            [
                'sections' => sectionsList($pdo, $scope, array_merge($_GET, $input)),
            ]
        );
    }

    if ($action === 'save') {
        sectionsCsrf($input);

        $isEdit = (int)($input['id'] ?? 0) > 0;
        if (!sectionsCan($user, $isEdit ? 'edit' : 'create')) {
            throw new RuntimeException(
                $isEdit
                    ? 'You do not have permission to edit sections.'
                    : 'You do not have permission to add sections.',
                403
            );
        }

        $result = sectionsSave($pdo, $scope, $input);

        sectionsJson(
            true,
            $isEdit ? 'Section updated successfully.' : 'Section created successfully.',
            $result
        );
    }

    if ($action === 'delete') {
        sectionsCsrf($input);

        if (!sectionsCan($user, 'delete')) {
            throw new RuntimeException('You do not have permission to delete sections.', 403);
        }

        sectionsDelete($pdo, $scope, (int)($input['id'] ?? 0));
        sectionsJson(true, 'Section deleted permanently from the database.');
    }

    sectionsJson(false, 'Invalid Sections API action.', [], 400);
} catch (InvalidArgumentException $exception) {
    sectionsJson(false, $exception->getMessage(), [], 422);
} catch (RuntimeException $exception) {
    $code = (int)$exception->getCode();
    sectionsJson(
        false,
        $exception->getMessage(),
        [],
        $code >= 400 && $code <= 599 ? $code : 403
    );
} catch (PDOException $exception) {
    error_log('sections-api PDO: ' . $exception->getMessage());

    $message = $exception->getCode() === '23000'
        ? 'This Section or Section Code already exists for the selected Academic Year.'
        : 'Unable to save Section data.';

    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    if (str_contains($host, 'localhost') || str_contains($host, '127.0.0.1')) {
        $message .= ' ' . $exception->getMessage();
    }

    sectionsJson(false, $message, [], 422);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('sections-api: ' . $exception->getMessage());

    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $message = (
        str_contains($host, 'localhost')
        || str_contains($host, '127.0.0.1')
    )
        ? 'Section request failed: ' . $exception->getMessage()
        : 'Unable to complete the section request.';

    sectionsJson(false, $message, [], 500);
}

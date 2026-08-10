<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

define('SCHOOL_API_PAGE_KEY', 'student_reports');
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

const STUDENT_REPORTS_BUILD = '2026-08-06-student-reports-easy-handle-v1';

function srOut(bool $success, string $message = '', array $data = [], int $status = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
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

function srTable(PDO $pdo, string $table): bool
{
    static $cache = [];
    $key = strtolower($table);
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = ?'
    );
    $stmt->execute([$table]);
    $cache[$key] = (int)$stmt->fetchColumn() > 0;
    return $cache[$key];
}

function srColumn(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = strtolower($table . '.' . $column);
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = ?
           AND column_name = ?'
    );
    $stmt->execute([$table, $column]);
    $cache[$key] = (int)$stmt->fetchColumn() > 0;
    return $cache[$key];
}

function srScope(PDO $pdo): array
{
    $user = function_exists('current_user') ? current_user() : [];
    $user = is_array($user) ? $user : [];

    $tenantId = (int)(
        $user['tenant_id']
        ?? $user['school_id']
        ?? $_SESSION['tenant_id']
        ?? $_SESSION['school_id']
        ?? 0
    );
    $branchId = (int)(
        $user['branch_id']
        ?? $user['default_branch_id']
        ?? $_SESSION['branch_id']
        ?? 0
    );
    $userId = (int)(
        $user['id']
        ?? $user['user_id']
        ?? $_SESSION['user_id']
        ?? 0
    );
    $roleId = (int)(
        $user['role_id']
        ?? $_SESSION['role_id']
        ?? 0
    );

    if ($tenantId <= 0 || $userId <= 0) {
        srOut(false, 'School or user session is missing.', ['build' => STUDENT_REPORTS_BUILD], 401);
    }

    if (srTable($pdo, 'users')) {
        $stmt = $pdo->prepare(
            "SELECT tenant_id, default_branch_id, role_id
             FROM users
             WHERE id = ?
               AND status = 'active'
               AND deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute([$userId]);
        $dbUser = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($dbUser)) {
            $tenantId = (int)($dbUser['tenant_id'] ?? $tenantId);
            $branchId = (int)($dbUser['default_branch_id'] ?? $branchId);
            $roleId = (int)($dbUser['role_id'] ?? $roleId);
        }
    }

    $role = [
        'role_key' => strtolower(trim((string)($user['role_key'] ?? $_SESSION['role_key'] ?? ''))),
        'role_name' => trim((string)($user['role_name'] ?? $_SESSION['role_name'] ?? '')),
        'role_scope' => strtolower(trim((string)($user['role_scope'] ?? 'school'))),
    ];

    if ($roleId > 0 && srTable($pdo, 'roles')) {
        $stmt = $pdo->prepare(
            "SELECT role_key, role_name, role_scope
             FROM roles
             WHERE id = ?
               AND status = 'active'
               AND (tenant_id = ? OR tenant_id IS NULL)
             LIMIT 1"
        );
        $stmt->execute([$roleId, $tenantId]);
        $dbRole = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($dbRole)) {
            $role = [
                'role_key' => strtolower(trim((string)$dbRole['role_key'])),
                'role_name' => trim((string)$dbRole['role_name']),
                'role_scope' => strtolower(trim((string)$dbRole['role_scope'])),
            ];
        }
    }

    if ($role['role_scope'] !== 'school') {
        srOut(false, 'Only school roles can access Student Reports.', ['build' => STUDENT_REPORTS_BUILD], 403);
    }

    $adminKeys = [
        'school_admin',
        'school_administrator',
        'school-administrator',
        'schooladmin',
        'admin',
        'administrator',
        'branch_admin',
        'branch_administrator',
    ];
    $adminNames = [
        'school administrator',
        'school admin',
        'administrator',
        'admin',
        'branch administrator',
        'branch admin',
    ];

    $isSchoolAdmin = in_array($role['role_key'], $adminKeys, true)
        || in_array(strtolower($role['role_name']), $adminNames, true);

    if (!$isSchoolAdmin && $branchId <= 0 && srTable($pdo, 'user_branch_access')) {
        $stmt = $pdo->prepare(
            "SELECT uba.branch_id
             FROM user_branch_access uba
             INNER JOIN branches b
                ON b.id = uba.branch_id
               AND b.tenant_id = ?
               AND b.status = 'active'
             WHERE uba.user_id = ?
               AND uba.can_access = 1
             ORDER BY b.is_main DESC, b.id
             LIMIT 1"
        );
        $stmt->execute([$tenantId, $userId]);
        $branchId = (int)($stmt->fetchColumn() ?: 0);
    }

    if (!$isSchoolAdmin && $branchId <= 0) {
        srOut(false, 'A branch must be assigned to this school role.', ['build' => STUDENT_REPORTS_BUILD], 403);
    }

    return [
        'tenant_id' => $tenantId,
        'branch_id' => $branchId,
        'user_id' => $userId,
        'role_id' => $roleId,
        'role_key' => $role['role_key'],
        'role_name' => $role['role_name'],
        'is_school_admin' => $isSchoolAdmin,
    ];
}

function srRequiredTables(PDO $pdo): void
{
    $required = ['students', 'student_enrollments', 'academic_years', 'classes', 'sections', 'branches'];
    $missing = [];
    foreach ($required as $table) {
        if (!srTable($pdo, $table)) {
            $missing[] = $table;
        }
    }

    if ($missing !== []) {
        throw new RuntimeException('Missing required table(s): ' . implode(', ', $missing));
    }
}

function srInt(array $input, string $key, int $default = 0): int
{
    return max(0, (int)($input[$key] ?? $default));
}

function srText(array $input, string $key, int $max = 180): string
{
    $value = trim((string)($input[$key] ?? ''));
    if ($max > 0 && mb_strlen($value) > $max) {
        $value = mb_substr($value, 0, $max);
    }
    return $value;
}

function srDate(array $input, string $key): string
{
    $value = trim((string)($input[$key] ?? ''));
    if ($value === '') {
        return '';
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $value : '';
}

function srReportType(array $input): string
{
    $type = strtolower(trim((string)($input['report_type'] ?? 'directory')));
    $allowed = [
        'directory',
        'admissions',
        'class_strength',
        'guardian_contacts',
        'attendance_summary',
        'fee_summary',
        'transport',
    ];
    return in_array($type, $allowed, true) ? $type : 'directory';
}

function srRequestedBranch(array $scope, array $input): int
{
    if (!$scope['is_school_admin']) {
        return (int)$scope['branch_id'];
    }
    return srInt($input, 'branch_id');
}

function srCurrentAcademicYear(PDO $pdo, int $tenantId): int
{
    $stmt = $pdo->prepare(
        "SELECT id
         FROM academic_years
         WHERE tenant_id = ?
         ORDER BY is_current DESC, start_date DESC, id DESC
         LIMIT 1"
    );
    $stmt->execute([$tenantId]);
    return (int)($stmt->fetchColumn() ?: 0);
}

function srMeta(PDO $pdo, array $scope): array
{
    $branchSql = "SELECT id, branch_code, branch_name, is_main
                  FROM branches
                  WHERE tenant_id = ? AND status = 'active'";
    $branchParams = [$scope['tenant_id']];
    if (!$scope['is_school_admin']) {
        $branchSql .= ' AND id = ?';
        $branchParams[] = $scope['branch_id'];
    }
    $branchSql .= ' ORDER BY is_main DESC, branch_name, id';
    $stmt = $pdo->prepare($branchSql);
    $stmt->execute($branchParams);
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare(
        "SELECT id, academic_year_code, year_name, start_date, end_date, is_current, status
         FROM academic_years
         WHERE tenant_id = ?
         ORDER BY is_current DESC, start_date DESC, id DESC"
    );
    $stmt->execute([$scope['tenant_id']]);
    $years = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare(
        "SELECT id, academic_year_id, class_name, display_order, status
         FROM classes
         WHERE tenant_id = ?
         ORDER BY academic_year_id DESC, display_order, class_name, id"
    );
    $stmt->execute([$scope['tenant_id']]);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare(
        "SELECT sec.id, sec.class_id, sec.section_name, sec.capacity, sec.status
         FROM sections sec
         INNER JOIN classes c ON c.id = sec.class_id AND c.tenant_id = sec.tenant_id
         WHERE sec.tenant_id = ?
         ORDER BY c.display_order, c.class_name, sec.section_name, sec.id"
    );
    $stmt->execute([$scope['tenant_id']]);
    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $school = [
        'school_name' => 'School',
        'school_code' => '',
        'logo_url' => '',
    ];
    if (srTable($pdo, 'school_profile')) {
        $stmt = $pdo->prepare(
            "SELECT school_name, school_code, logo_path
             FROM school_profile
             WHERE tenant_id = ?
             ORDER BY CASE WHEN branch_id IS NULL THEN 0 ELSE 1 END, updated_at DESC, id DESC
             LIMIT 1"
        );
        $stmt->execute([$scope['tenant_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            $school = [
                'school_name' => (string)($row['school_name'] ?? 'School'),
                'school_code' => (string)($row['school_code'] ?? ''),
                'logo_url' => !empty($row['logo_path'])
                    ? '../' . ltrim((string)$row['logo_path'], '/')
                    : '',
            ];
        }
    }

    return [
        'branches' => $branches,
        'academic_years' => $years,
        'classes' => $classes,
        'sections' => $sections,
        'current_academic_year_id' => srCurrentAcademicYear($pdo, $scope['tenant_id']),
        'can_all_branches' => (bool)$scope['is_school_admin'],
        'current_branch_id' => (int)$scope['branch_id'],
        'role_name' => (string)$scope['role_name'],
        'school' => $school,
        'available_reports' => [
            ['key' => 'directory', 'label' => 'Student Directory'],
            ['key' => 'admissions', 'label' => 'Admission Register'],
            ['key' => 'class_strength', 'label' => 'Class Strength'],
            ['key' => 'guardian_contacts', 'label' => 'Guardian Contacts'],
            ['key' => 'attendance_summary', 'label' => 'Attendance Summary'],
            ['key' => 'fee_summary', 'label' => 'Fee Summary'],
            ['key' => 'transport', 'label' => 'Transport Students'],
        ],
        'features' => [
            'guardians' => srTable($pdo, 'guardians') && srTable($pdo, 'student_guardians'),
            'attendance' => srTable($pdo, 'student_attendance'),
            'fees' => srTable($pdo, 'student_fee_assignments'),
            'transport' => srTable($pdo, 'student_transport_assignments'),
        ],
    ];
}

function srFilters(array $input, array $scope, PDO $pdo): array
{
    $page = max(1, srInt($input, 'page', 1));
    $perPage = srInt($input, 'per_page', 20);
    if (!in_array($perPage, [10, 20, 50, 100], true)) {
        $perPage = 20;
    }

    $dateFrom = srDate($input, 'date_from');
    $dateTo = srDate($input, 'date_to');
    if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
        [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
    }

    return [
        'report_type' => srReportType($input),
        'branch_id' => srRequestedBranch($scope, $input),
        'academic_year_id' => srInt($input, 'academic_year_id', srCurrentAcademicYear($pdo, $scope['tenant_id'])),
        'class_id' => srInt($input, 'class_id'),
        'section_id' => srInt($input, 'section_id'),
        'status' => strtolower(srText($input, 'status', 30)),
        'gender' => strtolower(srText($input, 'gender', 20)),
        'search' => srText($input, 'search', 120),
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'page' => $page,
        'per_page' => $perPage,
    ];
}

function srStudentWhere(array $filters, array $scope, array &$params, string $studentAlias = 's', string $enrollmentAlias = 'e'): string
{
    $where = [
        $studentAlias . '.tenant_id = ?',
        $studentAlias . '.deleted_at IS NULL',
    ];
    $params[] = $scope['tenant_id'];

    if ($filters['branch_id'] > 0) {
        $where[] = $studentAlias . '.branch_id = ?';
        $params[] = $filters['branch_id'];
    }
    if ($filters['academic_year_id'] > 0) {
        $where[] = $enrollmentAlias . '.academic_year_id = ?';
        $params[] = $filters['academic_year_id'];
    }
    if ($filters['class_id'] > 0) {
        $where[] = $enrollmentAlias . '.class_id = ?';
        $params[] = $filters['class_id'];
    }
    if ($filters['section_id'] > 0) {
        $where[] = $enrollmentAlias . '.section_id = ?';
        $params[] = $filters['section_id'];
    }
    if (in_array($filters['status'], ['active', 'inactive', 'tc', 'alumni'], true)) {
        $where[] = $studentAlias . '.status = ?';
        $params[] = $filters['status'];
    }
    if (in_array($filters['gender'], ['male', 'female', 'other'], true)) {
        $where[] = $studentAlias . '.gender = ?';
        $params[] = $filters['gender'];
    }
    if ($filters['search'] !== '') {
        $like = '%' . $filters['search'] . '%';
        $where[] = '(' . implode(' OR ', [
            $studentAlias . '.admission_no LIKE ?',
            $studentAlias . '.emis_no LIKE ?',
            $studentAlias . '.first_name LIKE ?',
            $studentAlias . '.last_name LIKE ?',
            $studentAlias . '.mobile LIKE ?',
            $studentAlias . '.email LIKE ?',
            'CONCAT_WS(\' \', ' . $studentAlias . '.first_name, ' . $studentAlias . '.last_name) LIKE ?',
        ]) . ')';
        array_push($params, $like, $like, $like, $like, $like, $like, $like);
    }

    return implode(' AND ', $where);
}

function srBaseJoins(PDO $pdo): string
{
    $joins = "
        INNER JOIN student_enrollments e
            ON e.student_id = s.id
           AND e.tenant_id = s.tenant_id
        INNER JOIN academic_years ay
            ON ay.id = e.academic_year_id
           AND ay.tenant_id = s.tenant_id
        INNER JOIN classes c
            ON c.id = e.class_id
           AND c.tenant_id = s.tenant_id
        INNER JOIN sections sec
            ON sec.id = e.section_id
           AND sec.tenant_id = s.tenant_id
        INNER JOIN branches b
            ON b.id = s.branch_id
           AND b.tenant_id = s.tenant_id";

    if (srTable($pdo, 'student_guardians') && srTable($pdo, 'guardians')) {
        $joins .= "
        LEFT JOIN student_guardians sg
            ON sg.student_id = s.id
           AND sg.is_primary = 1
        LEFT JOIN guardians g
            ON g.id = sg.guardian_id
           AND g.tenant_id = s.tenant_id";
    }

    return $joins;
}

function srDirectoryQuery(PDO $pdo, array $filters, array $scope): array
{
    $params = [];
    $where = srStudentWhere($filters, $scope, $params);
    $guardianFields = srTable($pdo, 'student_guardians') && srTable($pdo, 'guardians')
        ? "COALESCE(g.guardian_name, '') AS guardian_name,
           COALESCE(g.relationship, '') AS guardian_relationship,
           COALESCE(g.mobile, '') AS guardian_mobile"
        : "'' AS guardian_name, '' AS guardian_relationship, '' AS guardian_mobile";

    $sql = "SELECT
                s.id AS student_id,
                s.admission_no,
                COALESCE(s.emis_no, '') AS emis_no,
                TRIM(CONCAT_WS(' ', s.first_name, s.last_name)) AS student_name,
                s.gender,
                s.date_of_birth,
                COALESCE(s.blood_group, '') AS blood_group,
                COALESCE(s.mobile, '') AS mobile,
                COALESCE(s.email, '') AS email,
                COALESCE(s.address, '') AS address,
                s.admission_date,
                s.status,
                COALESCE(s.photo_path, '') AS photo_path,
                b.id AS branch_id,
                b.branch_name,
                ay.id AS academic_year_id,
                ay.year_name,
                c.id AS class_id,
                c.class_name,
                sec.id AS section_id,
                sec.section_name,
                COALESCE(e.roll_no, '') AS roll_no,
                e.enrollment_status,
                {$guardianFields}
            FROM students s
            " . srBaseJoins($pdo) . "
            WHERE {$where}
            ORDER BY b.branch_name, ay.start_date DESC, c.display_order, sec.section_name,
                     CASE WHEN e.roll_no REGEXP '^[0-9]+$' THEN CAST(e.roll_no AS UNSIGNED) ELSE 999999 END,
                     student_name, s.id";

    return [$sql, $params];
}

function srAdmissionsQuery(PDO $pdo, array $filters, array $scope): array
{
    [$sql, $params] = srDirectoryQuery($pdo, $filters, $scope);
    $sql = preg_replace('/ORDER BY[\s\S]*$/', '', $sql) ?? $sql;

    if ($filters['date_from'] !== '') {
        $sql .= ' AND s.admission_date >= ?';
        $params[] = $filters['date_from'];
    }
    if ($filters['date_to'] !== '') {
        $sql .= ' AND s.admission_date <= ?';
        $params[] = $filters['date_to'];
    }

    $sql .= ' ORDER BY s.admission_date DESC, b.branch_name, c.display_order, sec.section_name, student_name, s.id';
    return [$sql, $params];
}

function srClassStrengthQuery(PDO $pdo, array $filters, array $scope): array
{
    $params = [];
    $where = srStudentWhere($filters, $scope, $params);

    $sql = "SELECT
                b.id AS branch_id,
                b.branch_name,
                ay.id AS academic_year_id,
                ay.year_name,
                c.id AS class_id,
                c.class_name,
                sec.id AS section_id,
                sec.section_name,
                sec.capacity,
                COUNT(DISTINCT s.id) AS total_students,
                COUNT(DISTINCT CASE WHEN s.gender = 'male' THEN s.id END) AS male_students,
                COUNT(DISTINCT CASE WHEN s.gender = 'female' THEN s.id END) AS female_students,
                COUNT(DISTINCT CASE WHEN s.gender = 'other' THEN s.id END) AS other_students,
                COUNT(DISTINCT CASE WHEN s.status = 'active' THEN s.id END) AS active_students,
                COUNT(DISTINCT CASE WHEN s.status <> 'active' THEN s.id END) AS inactive_students
            FROM students s
            " . srBaseJoins($pdo) . "
            WHERE {$where}
            GROUP BY b.id, b.branch_name, ay.id, ay.year_name, ay.start_date,
                     c.id, c.class_name, c.display_order,
                     sec.id, sec.section_name, sec.capacity
            ORDER BY b.branch_name, ay.start_date DESC, c.display_order, c.class_name, sec.section_name";

    return [$sql, $params];
}

function srGuardianQuery(PDO $pdo, array $filters, array $scope): array
{
    if (!srTable($pdo, 'student_guardians') || !srTable($pdo, 'guardians')) {
        return ['SELECT NULL WHERE 1 = 0', []];
    }

    $params = [];
    $where = srStudentWhere($filters, $scope, $params);

    $sql = "SELECT
                s.id AS student_id,
                s.admission_no,
                TRIM(CONCAT_WS(' ', s.first_name, s.last_name)) AS student_name,
                b.branch_name,
                ay.year_name,
                c.class_name,
                sec.section_name,
                COALESCE(e.roll_no, '') AS roll_no,
                g.guardian_name,
                COALESCE(g.relationship, '') AS relationship,
                COALESCE(g.mobile, '') AS guardian_mobile,
                COALESCE(g.email, '') AS guardian_email,
                COALESCE(g.occupation, '') AS occupation,
                COALESCE(g.address, '') AS guardian_address,
                sg.is_primary,
                s.status
            FROM students s
            " . srBaseJoins($pdo) . "
            WHERE {$where}
            ORDER BY b.branch_name, c.display_order, sec.section_name, student_name, sg.is_primary DESC, g.guardian_name";

    return [$sql, $params];
}

function srAttendanceQuery(PDO $pdo, array $filters, array $scope): array
{
    if (!srTable($pdo, 'student_attendance')) {
        return ['SELECT NULL WHERE 1 = 0', []];
    }

    $whereParams = [];
    $where = srStudentWhere($filters, $scope, $whereParams);
    $attendanceParams = [];
    $attendanceConditions = [
        'sa.student_id = s.id',
        'sa.tenant_id = s.tenant_id',
        'sa.academic_year_id = e.academic_year_id',
    ];
    if ($filters['branch_id'] > 0) {
        $attendanceConditions[] = 'sa.branch_id = ?';
        $attendanceParams[] = $filters['branch_id'];
    }
    if ($filters['date_from'] !== '') {
        $attendanceConditions[] = 'sa.attendance_date >= ?';
        $attendanceParams[] = $filters['date_from'];
    }
    if ($filters['date_to'] !== '') {
        $attendanceConditions[] = 'sa.attendance_date <= ?';
        $attendanceParams[] = $filters['date_to'];
    }
    $params = array_merge($attendanceParams, $whereParams);

    $sql = "SELECT
                s.id AS student_id,
                s.admission_no,
                TRIM(CONCAT_WS(' ', s.first_name, s.last_name)) AS student_name,
                b.branch_name,
                ay.year_name,
                c.class_name,
                sec.section_name,
                COALESCE(e.roll_no, '') AS roll_no,
                COUNT(sa.id) AS marked_days,
                SUM(CASE WHEN sa.status IN ('present', 'late', 'on_duty') THEN 1 ELSE 0 END) AS present_days,
                SUM(CASE WHEN sa.status = 'absent' THEN 1 ELSE 0 END) AS absent_days,
                SUM(CASE WHEN sa.status = 'late' THEN 1 ELSE 0 END) AS late_days,
                SUM(CASE WHEN sa.status = 'half_day' THEN 1 ELSE 0 END) AS half_days,
                SUM(CASE WHEN sa.status = 'leave' THEN 1 ELSE 0 END) AS leave_days,
                ROUND(
                    CASE WHEN COUNT(sa.id) > 0
                         THEN (SUM(CASE WHEN sa.status IN ('present', 'late', 'on_duty') THEN 1 ELSE 0 END) / COUNT(sa.id)) * 100
                         ELSE 0 END,
                    2
                ) AS attendance_percentage,
                s.status
            FROM students s
            " . srBaseJoins($pdo) . "
            LEFT JOIN student_attendance sa
                ON " . implode(' AND ', $attendanceConditions) . "
            WHERE {$where}
            GROUP BY s.id, s.admission_no, s.first_name, s.last_name,
                     b.branch_name, ay.year_name, ay.start_date,
                     c.class_name, c.display_order, sec.section_name, e.roll_no, s.status
            ORDER BY b.branch_name, c.display_order, sec.section_name, student_name, s.id";

    return [$sql, $params];
}

function srFeeQuery(PDO $pdo, array $filters, array $scope): array
{
    if (!srTable($pdo, 'student_fee_assignments')) {
        return ['SELECT NULL WHERE 1 = 0', []];
    }

    $params = [];
    $where = srStudentWhere($filters, $scope, $params);

    $sql = "SELECT
                s.id AS student_id,
                s.admission_no,
                TRIM(CONCAT_WS(' ', s.first_name, s.last_name)) AS student_name,
                b.branch_name,
                ay.year_name,
                c.class_name,
                sec.section_name,
                COALESCE(e.roll_no, '') AS roll_no,
                COALESCE(SUM(fa.gross_amount), 0) AS gross_amount,
                COALESCE(SUM(fa.concession_amount), 0) AS concession_amount,
                COALESCE(SUM(fa.scholarship_amount), 0) AS scholarship_amount,
                COALESCE(SUM(fa.net_amount), 0) AS net_amount,
                COALESCE(SUM(fa.paid_amount), 0) AS paid_amount,
                COALESCE(SUM(fa.balance_amount), 0) AS balance_amount,
                CASE
                    WHEN COUNT(fa.id) = 0 THEN 'not_assigned'
                    WHEN SUM(fa.balance_amount) <= 0 THEN 'paid'
                    WHEN SUM(fa.paid_amount) > 0 THEN 'partial'
                    ELSE 'unpaid'
                END AS payment_status,
                s.status
            FROM students s
            " . srBaseJoins($pdo) . "
            LEFT JOIN student_fee_assignments fa
                ON fa.student_id = s.id
               AND fa.tenant_id = s.tenant_id
               AND fa.academic_year_id = e.academic_year_id
               AND fa.assignment_status = 'active'
            WHERE {$where}
            GROUP BY s.id, s.admission_no, s.first_name, s.last_name,
                     b.branch_name, ay.year_name, ay.start_date,
                     c.class_name, c.display_order, sec.section_name, e.roll_no, s.status
            ORDER BY b.branch_name, c.display_order, sec.section_name, student_name, s.id";

    return [$sql, $params];
}

function srTransportQuery(PDO $pdo, array $filters, array $scope): array
{
    if (!srTable($pdo, 'student_transport_assignments')) {
        return ['SELECT NULL WHERE 1 = 0', []];
    }

    $params = [];
    $where = srStudentWhere($filters, $scope, $params);
    $where .= ' AND ta.transport_required = 1 AND ta.status = \'active\'';

    $sql = "SELECT
                s.id AS student_id,
                s.admission_no,
                TRIM(CONCAT_WS(' ', s.first_name, s.last_name)) AS student_name,
                b.branch_name,
                ay.year_name,
                c.class_name,
                sec.section_name,
                COALESCE(e.roll_no, '') AS roll_no,
                COALESCE(ta.route_name, tr.route_name, '') AS route_name,
                COALESCE(ta.boarding_stop_name, '') AS boarding_stop_name,
                COALESCE(ta.vehicle_name, '') AS vehicle_name,
                COALESCE(ta.driver_name, '') AS driver_name,
                COALESCE(NULLIF(ta.transport_fee_amount, 0), ta.bus_fee_amount, 0) AS transport_fee_amount,
                ta.assigned_on,
                ta.status AS transport_status,
                s.status
            FROM students s
            " . srBaseJoins($pdo) . "
            INNER JOIN student_transport_assignments ta
                ON ta.student_id = s.id
               AND ta.tenant_id = s.tenant_id
               AND ta.academic_year_id = e.academic_year_id
            " . (srTable($pdo, 'transport_routes')
                ? "LEFT JOIN transport_routes tr ON tr.id = ta.route_id AND tr.tenant_id = s.tenant_id"
                : "LEFT JOIN (SELECT NULL AS id, NULL AS tenant_id, NULL AS route_name) tr ON 1 = 0") . "
            WHERE {$where}
            ORDER BY b.branch_name, route_name, c.display_order, sec.section_name, student_name, s.id";

    return [$sql, $params];
}

function srBuildQuery(PDO $pdo, array $filters, array $scope): array
{
    return match ($filters['report_type']) {
        'admissions' => srAdmissionsQuery($pdo, $filters, $scope),
        'class_strength' => srClassStrengthQuery($pdo, $filters, $scope),
        'guardian_contacts' => srGuardianQuery($pdo, $filters, $scope),
        'attendance_summary' => srAttendanceQuery($pdo, $filters, $scope),
        'fee_summary' => srFeeQuery($pdo, $filters, $scope),
        'transport' => srTransportQuery($pdo, $filters, $scope),
        default => srDirectoryQuery($pdo, $filters, $scope),
    };
}

function srSummary(array $rows, string $reportType, int $totalRows): array
{
    $summary = [
        'total_students' => 0,
        'active_students' => 0,
        'report_rows' => $totalRows,
        'extra_label' => 'Branches',
        'extra_value' => 0,
        'extra_format' => 'number',
    ];

    if ($reportType === 'class_strength') {
        $summary['total_students'] = array_sum(array_map(static fn($row) => (int)($row['total_students'] ?? 0), $rows));
        $summary['active_students'] = array_sum(array_map(static fn($row) => (int)($row['active_students'] ?? 0), $rows));
        $summary['extra_label'] = 'Sections';
        $summary['extra_value'] = $totalRows;
        return $summary;
    }

    $studentIds = [];
    $activeIds = [];
    $branches = [];
    foreach ($rows as $row) {
        $studentId = (int)($row['student_id'] ?? 0);
        if ($studentId > 0) {
            $studentIds[$studentId] = true;
            if (($row['status'] ?? '') === 'active') {
                $activeIds[$studentId] = true;
            }
        }
        if (!empty($row['branch_name'])) {
            $branches[(string)$row['branch_name']] = true;
        }
    }
    $summary['total_students'] = count($studentIds) > 0 ? count($studentIds) : $totalRows;
    $summary['active_students'] = count($activeIds);
    $summary['extra_value'] = count($branches);

    if ($reportType === 'attendance_summary') {
        $percentages = array_map(static fn($row) => (float)($row['attendance_percentage'] ?? 0), $rows);
        $summary['extra_label'] = 'Avg. Attendance';
        $summary['extra_value'] = $percentages === [] ? 0 : round(array_sum($percentages) / count($percentages), 2);
        $summary['extra_format'] = 'percent';
    } elseif ($reportType === 'fee_summary') {
        $summary['extra_label'] = 'Total Balance';
        $summary['extra_value'] = round(array_sum(array_map(static fn($row) => (float)($row['balance_amount'] ?? 0), $rows)), 2);
        $summary['extra_format'] = 'currency';
    } elseif ($reportType === 'transport') {
        $summary['extra_label'] = 'Transport Students';
        $summary['extra_value'] = count($studentIds);
    } elseif ($reportType === 'admissions') {
        $summary['extra_label'] = 'Admissions';
        $summary['extra_value'] = $totalRows;
    } elseif ($reportType === 'guardian_contacts') {
        $summary['extra_label'] = 'Contacts';
        $summary['extra_value'] = $totalRows;
    }

    return $summary;
}

function srExecuteReport(PDO $pdo, array $filters, array $scope, bool $allRows = false): array
{
    [$sql, $params] = srBuildQuery($pdo, $filters, $scope);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM ({$sql}) sr_count");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $summaryRows = [];
    if ($allRows) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $summaryRows = $rows;
    } else {
        $summaryStmt = $pdo->prepare($sql);
        $summaryStmt->execute($params);
        $summaryRows = $summaryStmt->fetchAll(PDO::FETCH_ASSOC);

        $offset = ($filters['page'] - 1) * $filters['per_page'];
        $pagedSql = $sql . ' LIMIT ' . (int)$filters['per_page'] . ' OFFSET ' . (int)$offset;
        $stmt = $pdo->prepare($pagedSql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    return [
        'rows' => $rows,
        'total' => $total,
        'summary' => srSummary($summaryRows, $filters['report_type'], $total),
    ];
}

function srDetail(PDO $pdo, array $scope, array $input): array
{
    $studentId = srInt($input, 'student_id');
    if ($studentId <= 0) {
        throw new InvalidArgumentException('Student is required.');
    }

    $branchId = srRequestedBranch($scope, $input);
    $academicYearId = srInt($input, 'academic_year_id', srCurrentAcademicYear($pdo, $scope['tenant_id']));

    $params = [$scope['tenant_id'], $studentId];
    $where = 's.tenant_id = ? AND s.id = ? AND s.deleted_at IS NULL';
    if ($branchId > 0) {
        $where .= ' AND s.branch_id = ?';
        $params[] = $branchId;
    }
    if ($academicYearId > 0) {
        $where .= ' AND e.academic_year_id = ?';
        $params[] = $academicYearId;
    }

    $guardianFields = srTable($pdo, 'student_guardians') && srTable($pdo, 'guardians')
        ? "COALESCE(g.guardian_name, '') AS guardian_name,
           COALESCE(g.relationship, '') AS guardian_relationship,
           COALESCE(g.mobile, '') AS guardian_mobile,
           COALESCE(g.email, '') AS guardian_email,
           COALESCE(g.occupation, '') AS guardian_occupation"
        : "'' AS guardian_name, '' AS guardian_relationship, '' AS guardian_mobile,
           '' AS guardian_email, '' AS guardian_occupation";

    $stmt = $pdo->prepare(
        "SELECT
            s.*,
            TRIM(CONCAT_WS(' ', s.first_name, s.last_name)) AS student_name,
            b.branch_name,
            ay.year_name,
            c.class_name,
            sec.section_name,
            COALESCE(e.roll_no, '') AS roll_no,
            e.enrollment_status,
            {$guardianFields}
         FROM students s
         " . srBaseJoins($pdo) . "
         WHERE {$where}
         ORDER BY ay.is_current DESC, ay.start_date DESC, e.id DESC
         LIMIT 1"
    );
    $stmt->execute($params);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($student)) {
        throw new RuntimeException('Student record was not found.');
    }

    $attendance = [
        'marked_days' => 0,
        'present_days' => 0,
        'absent_days' => 0,
        'late_days' => 0,
        'attendance_percentage' => 0,
    ];
    if (srTable($pdo, 'student_attendance')) {
        $stmt = $pdo->prepare(
            "SELECT
                COUNT(*) AS marked_days,
                SUM(CASE WHEN status IN ('present','late','on_duty') THEN 1 ELSE 0 END) AS present_days,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) AS absent_days,
                SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) AS late_days,
                ROUND(CASE WHEN COUNT(*) > 0
                     THEN SUM(CASE WHEN status IN ('present','late','on_duty') THEN 1 ELSE 0 END) / COUNT(*) * 100
                     ELSE 0 END, 2) AS attendance_percentage
             FROM student_attendance
             WHERE tenant_id = ?
               AND student_id = ?
               AND academic_year_id = ?"
        );
        $stmt->execute([$scope['tenant_id'], $studentId, (int)$student['academic_year_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            $attendance = array_merge($attendance, $row);
        }
    }

    $fees = [
        'gross_amount' => 0,
        'paid_amount' => 0,
        'balance_amount' => 0,
        'payment_status' => 'not_assigned',
    ];
    if (srTable($pdo, 'student_fee_assignments')) {
        $stmt = $pdo->prepare(
            "SELECT
                COALESCE(SUM(gross_amount), 0) AS gross_amount,
                COALESCE(SUM(paid_amount), 0) AS paid_amount,
                COALESCE(SUM(balance_amount), 0) AS balance_amount,
                CASE
                    WHEN COUNT(*) = 0 THEN 'not_assigned'
                    WHEN SUM(balance_amount) <= 0 THEN 'paid'
                    WHEN SUM(paid_amount) > 0 THEN 'partial'
                    ELSE 'unpaid'
                END AS payment_status
             FROM student_fee_assignments
             WHERE tenant_id = ?
               AND student_id = ?
               AND academic_year_id = ?
               AND assignment_status = 'active'"
        );
        $stmt->execute([$scope['tenant_id'], $studentId, (int)$student['academic_year_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            $fees = array_merge($fees, $row);
        }
    }

    $transport = [];
    if (srTable($pdo, 'student_transport_assignments')) {
        $stmt = $pdo->prepare(
            "SELECT route_name, boarding_stop_name, vehicle_name, driver_name,
                    transport_required, transport_fee_amount, bus_fee_amount,
                    assigned_on, status
             FROM student_transport_assignments
             WHERE tenant_id = ?
               AND student_id = ?
               AND academic_year_id = ?
             ORDER BY status = 'active' DESC, id DESC
             LIMIT 1"
        );
        $stmt->execute([$scope['tenant_id'], $studentId, (int)$student['academic_year_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $transport = is_array($row) ? $row : [];
    }

    return [
        'student' => $student,
        'attendance' => $attendance,
        'fees' => $fees,
        'transport' => $transport,
    ];
}

function srCsvValue(mixed $value): string
{
    $text = is_scalar($value) || $value === null ? (string)$value : json_encode($value);
    $text = str_replace(["\r", "\n"], ' ', $text);
    if ($text !== '' && in_array($text[0], ['=', '+', '-', '@'], true)) {
        $text = "'" . $text;
    }
    return $text;
}

function srCsvColumns(string $type): array
{
    return match ($type) {
        'admissions' => [
            'admission_date' => 'Admission Date', 'admission_no' => 'Admission No',
            'student_name' => 'Student', 'gender' => 'Gender', 'date_of_birth' => 'Date of Birth',
            'branch_name' => 'Branch', 'year_name' => 'Academic Year', 'class_name' => 'Class',
            'section_name' => 'Section', 'roll_no' => 'Roll No', 'guardian_name' => 'Guardian',
            'guardian_mobile' => 'Guardian Mobile', 'status' => 'Status',
        ],
        'class_strength' => [
            'branch_name' => 'Branch', 'year_name' => 'Academic Year', 'class_name' => 'Class',
            'section_name' => 'Section', 'capacity' => 'Capacity', 'total_students' => 'Total',
            'male_students' => 'Male', 'female_students' => 'Female', 'other_students' => 'Other',
            'active_students' => 'Active', 'inactive_students' => 'Inactive',
        ],
        'guardian_contacts' => [
            'admission_no' => 'Admission No', 'student_name' => 'Student', 'branch_name' => 'Branch',
            'year_name' => 'Academic Year', 'class_name' => 'Class', 'section_name' => 'Section',
            'roll_no' => 'Roll No', 'guardian_name' => 'Guardian', 'relationship' => 'Relationship',
            'guardian_mobile' => 'Mobile', 'guardian_email' => 'Email', 'occupation' => 'Occupation',
            'guardian_address' => 'Address', 'status' => 'Student Status',
        ],
        'attendance_summary' => [
            'admission_no' => 'Admission No', 'student_name' => 'Student', 'branch_name' => 'Branch',
            'year_name' => 'Academic Year', 'class_name' => 'Class', 'section_name' => 'Section',
            'roll_no' => 'Roll No', 'marked_days' => 'Marked Days', 'present_days' => 'Present',
            'absent_days' => 'Absent', 'late_days' => 'Late', 'half_days' => 'Half Day',
            'leave_days' => 'Leave', 'attendance_percentage' => 'Attendance %', 'status' => 'Status',
        ],
        'fee_summary' => [
            'admission_no' => 'Admission No', 'student_name' => 'Student', 'branch_name' => 'Branch',
            'year_name' => 'Academic Year', 'class_name' => 'Class', 'section_name' => 'Section',
            'roll_no' => 'Roll No', 'gross_amount' => 'Gross Amount', 'concession_amount' => 'Concession',
            'scholarship_amount' => 'Scholarship', 'net_amount' => 'Net Amount',
            'paid_amount' => 'Paid Amount', 'balance_amount' => 'Balance Amount',
            'payment_status' => 'Payment Status', 'status' => 'Student Status',
        ],
        'transport' => [
            'admission_no' => 'Admission No', 'student_name' => 'Student', 'branch_name' => 'Branch',
            'year_name' => 'Academic Year', 'class_name' => 'Class', 'section_name' => 'Section',
            'roll_no' => 'Roll No', 'route_name' => 'Route', 'boarding_stop_name' => 'Boarding Stop',
            'vehicle_name' => 'Vehicle', 'driver_name' => 'Driver',
            'transport_fee_amount' => 'Transport Fee', 'assigned_on' => 'Assigned On',
            'transport_status' => 'Transport Status', 'status' => 'Student Status',
        ],
        default => [
            'admission_no' => 'Admission No', 'emis_no' => 'EMIS No', 'student_name' => 'Student',
            'gender' => 'Gender', 'date_of_birth' => 'Date of Birth', 'blood_group' => 'Blood Group',
            'mobile' => 'Mobile', 'email' => 'Email', 'branch_name' => 'Branch',
            'year_name' => 'Academic Year', 'class_name' => 'Class', 'section_name' => 'Section',
            'roll_no' => 'Roll No', 'guardian_name' => 'Guardian', 'guardian_mobile' => 'Guardian Mobile',
            'admission_date' => 'Admission Date', 'status' => 'Status',
        ],
    };
}

function srLog(PDO $pdo, array $scope, string $action, string $description, array $details = []): void
{
    if (!srTable($pdo, 'activity_logs')) {
        return;
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO activity_logs
                (tenant_id, branch_id, user_id, role_id, module_name, action_key,
                 table_name, record_id, old_values, new_values, description,
                 ip_address, user_agent, created_at)
             VALUES (?, ?, ?, ?, 'Student Reports', ?, 'students', NULL, NULL, ?, ?, ?, ?, CURRENT_TIMESTAMP)"
        );
        $stmt->execute([
            $scope['tenant_id'],
            $scope['branch_id'] > 0 ? $scope['branch_id'] : null,
            $scope['user_id'],
            $scope['role_id'] > 0 ? $scope['role_id'] : null,
            $action,
            json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $description,
            substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
            substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1000),
        ]);
    } catch (Throwable $ignored) {
        error_log('Student Reports activity log failed: ' . $ignored->getMessage());
    }
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    srOut(false, 'Database connection is missing.', ['build' => STUDENT_REPORTS_BUILD], 500);
}

$scope = srScope($pdo);
$input = array_merge($_GET, $_POST);
$action = strtolower(trim((string)($input['action'] ?? 'meta')));

try {
    srRequiredTables($pdo);

    if ($action === 'meta') {
        srOut(true, 'Student Reports options loaded.', [
            'meta' => srMeta($pdo, $scope),
            'build' => STUDENT_REPORTS_BUILD,
        ]);
    }

    if ($action === 'detail') {
        srOut(true, 'Student details loaded.', [
            'detail' => srDetail($pdo, $scope, $input),
            'build' => STUDENT_REPORTS_BUILD,
        ]);
    }

    if ($action === 'list') {
        $filters = srFilters($input, $scope, $pdo);
        $result = srExecuteReport($pdo, $filters, $scope, false);
        $pages = max(1, (int)ceil($result['total'] / $filters['per_page']));

        srOut(true, 'Student Report loaded.', [
            'report_type' => $filters['report_type'],
            'rows' => $result['rows'],
            'summary' => $result['summary'],
            'pagination' => [
                'page' => $filters['page'],
                'per_page' => $filters['per_page'],
                'total' => $result['total'],
                'pages' => $pages,
            ],
            'filters' => $filters,
            'build' => STUDENT_REPORTS_BUILD,
        ]);
    }

    if ($action === 'export') {
        $filters = srFilters($input, $scope, $pdo);
        $result = srExecuteReport($pdo, $filters, $scope, true);
        $columns = srCsvColumns($filters['report_type']);

        srLog(
            $pdo,
            $scope,
            'export',
            'Exported Student Report: ' . $filters['report_type'],
            $filters
        );

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $fileName = 'student-' . str_replace('_', '-', $filters['report_type']) . '-report-' . date('Ymd-His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array_values($columns));
        foreach ($result['rows'] as $row) {
            $line = [];
            foreach (array_keys($columns) as $key) {
                $line[] = srCsvValue($row[$key] ?? '');
            }
            fputcsv($out, $line);
        }
        fclose($out);
        exit;
    }

    srOut(false, 'Student Reports action is invalid.', ['build' => STUDENT_REPORTS_BUILD], 400);
} catch (InvalidArgumentException $exception) {
    srOut(false, $exception->getMessage(), ['build' => STUDENT_REPORTS_BUILD], 422);
} catch (PDOException $exception) {
    error_log('Student Reports PDO [' . STUDENT_REPORTS_BUILD . ']: ' . $exception->getMessage());
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $local = str_contains($host, 'localhost') || str_contains($host, '127.0.0.1');
    srOut(
        false,
        $local
            ? 'Student Reports database error: ' . $exception->getMessage()
            : 'Unable to load Student Reports.',
        ['build' => STUDENT_REPORTS_BUILD],
        500
    );
} catch (Throwable $exception) {
    error_log('Student Reports [' . STUDENT_REPORTS_BUILD . ']: ' . $exception->getMessage());
    srOut(false, 'Student Reports request failed: ' . $exception->getMessage(), ['build' => STUDENT_REPORTS_BUILD], 500);
}

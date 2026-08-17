<?php
declare(strict_types=1);

/*
 * Parent Attendance API
 * Location: parent/api/attendance.php
 * Build: 2026-08-13-parent-attendance-readonly-v1
 *
 * Read-only API. A Parent can view attendance only for students linked to
 * the logged-in Parent account. No attendance create/update/delete action
 * is exposed here.
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

function paJson(bool $success, string $message = '', array $data = [], int $status = 200): never
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

function paUser(): array
{
    $user = function_exists('current_user') ? current_user() : [];
    return is_array($user) ? $user : [];
}

function paScope(): array
{
    $user = paUser();

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
        'role_id' => (int)(
            $user['role_id']
            ?? $_SESSION['role_id']
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

function paTableExists(PDO $pdo, string $table): bool
{
    static $cache = [];

    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    $statement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = :table_name'
    );
    $statement->execute(['table_name' => $table]);

    return $cache[$table] = ((int)$statement->fetchColumn() > 0);
}

function paColumns(PDO $pdo, string $table): array
{
    static $cache = [];

    if (isset($cache[$table])) {
        return $cache[$table];
    }

    if (!paTableExists($pdo, $table)) {
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

function paMobile(string $value): string
{
    return preg_replace('/\D+/', '', $value) ?? '';
}

function paRequireParent(PDO $pdo, array $scope): void
{
    if ((int)$scope['tenant_id'] <= 0 || (int)$scope['user_id'] <= 0) {
        throw new RuntimeException('Parent login session was not found.', 401);
    }

    $role = pc_role(
        $pdo,
        (int)$scope['tenant_id'],
        (int)$scope['role_id']
    );

    if (pc_role_key((string)($role['role_key'] ?? '')) !== 'parent') {
        throw new RuntimeException('Attendance is available only to Parent accounts.', 403);
    }

    $items = school_sidebar_get_items(
        $pdo,
        (int)$scope['role_id'],
        (int)$scope['tenant_id']
    );

    $allowed = false;
    foreach ($items as $item) {
        if ((string)($item['menu_key'] ?? '') === 'parent_attendance') {
            $allowed = true;
            break;
        }
    }

    if (!$allowed) {
        throw new RuntimeException('Parent Attendance is disabled for this school.', 403);
    }
}

function paGuardian(PDO $pdo, array $scope): array
{
    $tenantId = (int)$scope['tenant_id'];
    $userId = (int)$scope['user_id'];

    if ($userId > 0 && paTableExists($pdo, 'student_parent_logins')) {
        $statement = $pdo->prepare(
            "SELECT DISTINCT
                g.id,
                g.tenant_id,
                g.guardian_name,
                g.relationship,
                g.mobile,
                g.email,
                g.occupation,
                g.address
             FROM student_parent_logins spl
             INNER JOIN guardians g
                ON g.id = spl.guardian_id
               AND g.tenant_id = spl.tenant_id
             WHERE spl.tenant_id = :tenant_id
               AND spl.user_id = :user_id
               AND spl.status = 'active'
             ORDER BY spl.id
             LIMIT 1"
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
        ]);

        if ($guardian = $statement->fetch(PDO::FETCH_ASSOC)) {
            $_SESSION['guardian_id'] = (int)$guardian['id'];
            return $guardian;
        }
    }

    if ($userId > 0 && in_array('user_id', paColumns($pdo, 'guardians'), true)) {
        $statement = $pdo->prepare(
            'SELECT id, tenant_id, guardian_name, relationship, mobile, email, occupation, address
             FROM guardians
             WHERE tenant_id = :tenant_id
               AND user_id = :user_id
             ORDER BY id
             LIMIT 1'
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
        ]);

        if ($guardian = $statement->fetch(PDO::FETCH_ASSOC)) {
            $_SESSION['guardian_id'] = (int)$guardian['id'];
            return $guardian;
        }
    }

    $guardianId = (int)(
        $_SESSION['guardian_id']
        ?? $_SESSION['parent_guardian_id']
        ?? $_SESSION['parent_id']
        ?? 0
    );

    if ($guardianId > 0) {
        $statement = $pdo->prepare(
            'SELECT id, tenant_id, guardian_name, relationship, mobile, email, occupation, address
             FROM guardians
             WHERE id = :guardian_id
               AND tenant_id = :tenant_id
             LIMIT 1'
        );
        $statement->execute([
            'guardian_id' => $guardianId,
            'tenant_id' => $tenantId,
        ]);

        if ($guardian = $statement->fetch(PDO::FETCH_ASSOC)) {
            return $guardian;
        }
    }

    $email = strtolower(trim((string)$scope['email']));
    $mobile = paMobile((string)$scope['mobile']);

    if ($email === '' && $mobile === '') {
        throw new RuntimeException('This login is not linked to a Parent/Guardian record.', 403);
    }

    $statement = $pdo->prepare(
        'SELECT id, tenant_id, guardian_name, relationship, mobile, email, occupation, address
         FROM guardians
         WHERE tenant_id = :tenant_id
         ORDER BY id'
    );
    $statement->execute(['tenant_id' => $tenantId]);

    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $guardian) {
        $guardianEmail = strtolower(trim((string)($guardian['email'] ?? '')));
        $guardianMobile = paMobile((string)($guardian['mobile'] ?? ''));

        if (
            ($email !== '' && $guardianEmail !== '' && $guardianEmail === $email)
            || ($mobile !== '' && $guardianMobile !== '' && $guardianMobile === $mobile)
        ) {
            $_SESSION['guardian_id'] = (int)$guardian['id'];
            return $guardian;
        }
    }

    throw new RuntimeException('No Parent/Guardian record matches this login.', 403);
}

function paMappedStudentIds(PDO $pdo, int $tenantId, int $userId): array
{
    if (
        $tenantId <= 0
        || $userId <= 0
        || !paTableExists($pdo, 'student_parent_logins')
    ) {
        return [];
    }

    $statement = $pdo->prepare(
        "SELECT DISTINCT student_id
         FROM student_parent_logins
         WHERE tenant_id = :tenant_id
           AND user_id = :user_id
           AND status = 'active'"
    );
    $statement->execute([
        'tenant_id' => $tenantId,
        'user_id' => $userId,
    ]);

    return array_values(array_filter(
        array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN)),
        static fn(int $id): bool => $id > 0
    ));
}

function paChildren(PDO $pdo, int $tenantId, int $guardianId, int $userId): array
{
    $statement = $pdo->prepare(
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
            s.photo_path,
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
            se.enrollment_status
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
                    FIELD(se2.enrollment_status, 'active', 'promoted', 'transferred', 'completed'),
                    ay2.start_date DESC,
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
         WHERE sg.guardian_id = :guardian_id
           AND s.deleted_at IS NULL
         ORDER BY sg.is_primary DESC, s.first_name, s.last_name, s.id"
    );
    $statement->execute([
        'tenant_id' => $tenantId,
        'guardian_id' => $guardianId,
    ]);

    $children = $statement->fetchAll(PDO::FETCH_ASSOC);
    $mappedIds = paMappedStudentIds($pdo, $tenantId, $userId);

    if ($mappedIds !== []) {
        $allowed = array_fill_keys($mappedIds, true);
        $children = array_values(array_filter(
            $children,
            static fn(array $child): bool => isset($allowed[(int)($child['id'] ?? 0)])
        ));
    }

    return $children;
}

function paSelectedChild(array $children, int $requestedStudentId): array
{
    if ($children === []) {
        throw new RuntimeException('No students are linked to this Parent account.', 404);
    }

    if ($requestedStudentId > 0) {
        foreach ($children as $child) {
            if ((int)$child['id'] === $requestedStudentId) {
                $_SESSION['parent_selected_student_id'] = $requestedStudentId;
                return $child;
            }
        }

        throw new RuntimeException('The selected student is not linked to this Parent account.', 403);
    }

    $sessionStudentId = (int)($_SESSION['parent_selected_student_id'] ?? 0);
    if ($sessionStudentId > 0) {
        foreach ($children as $child) {
            if ((int)$child['id'] === $sessionStudentId) {
                return $child;
            }
        }
    }

    $_SESSION['parent_selected_student_id'] = (int)$children[0]['id'];
    return $children[0];
}

function paAttendanceYears(PDO $pdo, int $tenantId, int $studentId, array $child): array
{
    $years = [];

    if (paTableExists($pdo, 'student_attendance')) {
        $statement = $pdo->prepare(
            'SELECT DISTINCT YEAR(attendance_date) AS attendance_year
             FROM student_attendance
             WHERE tenant_id = :tenant_id
               AND student_id = :student_id
               AND attendance_date IS NOT NULL
             ORDER BY attendance_year DESC'
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'student_id' => $studentId,
        ]);

        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $year) {
            $year = (int)$year;
            if ($year >= 2000 && $year <= 2100) {
                $years[$year] = $year;
            }
        }
    }

    foreach (['academic_year_start', 'academic_year_end'] as $key) {
        $value = trim((string)($child[$key] ?? ''));
        if ($value !== '') {
            $year = (int)substr($value, 0, 4);
            if ($year >= 2000 && $year <= 2100) {
                $years[$year] = $year;
            }
        }
    }

    $currentYear = (int)date('Y');
    $years[$currentYear] = $currentYear;

    rsort($years, SORT_NUMERIC);
    return array_values($years);
}

function paAttendance(PDO $pdo, int $tenantId, int $studentId, int $year, int $month): array
{
    if (!paTableExists($pdo, 'student_attendance')) {
        return [
            'summary' => [
                'total_days' => 0,
                'present_days' => 0,
                'absent_days' => 0,
                'leave_days' => 0,
                'late_days' => 0,
                'half_day_days' => 0,
                'on_duty_days' => 0,
                'holiday_days' => 0,
                'attendance_percentage' => 0,
            ],
            'rows' => [],
        ];
    }

    $where = [
        'sa.tenant_id = :tenant_id',
        'sa.student_id = :student_id',
        'YEAR(sa.attendance_date) = :attendance_year',
    ];
    $params = [
        'tenant_id' => $tenantId,
        'student_id' => $studentId,
        'attendance_year' => $year,
    ];

    if ($month > 0) {
        $where[] = 'MONTH(sa.attendance_date) = :attendance_month';
        $params['attendance_month'] = $month;
    }

    $whereSql = implode(' AND ', $where);

    $summaryStatement = $pdo->prepare(
        "SELECT
            COALESCE(SUM(sa.status <> 'holiday'), 0) AS total_days,
            COALESCE(SUM(sa.status = 'present'), 0) AS present_days,
            COALESCE(SUM(sa.status = 'absent'), 0) AS absent_days,
            COALESCE(SUM(sa.status = 'leave'), 0) AS leave_days,
            COALESCE(SUM(sa.status = 'late'), 0) AS late_days,
            COALESCE(SUM(sa.status = 'half_day'), 0) AS half_day_days,
            COALESCE(SUM(sa.status = 'on_duty'), 0) AS on_duty_days,
            COALESCE(SUM(sa.status = 'holiday'), 0) AS holiday_days
         FROM student_attendance sa
         WHERE {$whereSql}"
    );
    $summaryStatement->execute($params);
    $summary = $summaryStatement->fetch(PDO::FETCH_ASSOC) ?: [];

    $totalDays = (float)($summary['total_days'] ?? 0);
    $attendanceCredit =
        (float)($summary['present_days'] ?? 0)
        + (float)($summary['late_days'] ?? 0)
        + (float)($summary['on_duty_days'] ?? 0)
        + ((float)($summary['half_day_days'] ?? 0) * 0.5);

    $summary['attendance_percentage'] = $totalDays > 0
        ? round(($attendanceCredit / $totalDays) * 100, 1)
        : 0.0;

    foreach ([
        'total_days',
        'present_days',
        'absent_days',
        'leave_days',
        'late_days',
        'half_day_days',
        'on_duty_days',
        'holiday_days',
    ] as $key) {
        $summary[$key] = (int)($summary[$key] ?? 0);
    }

    $rowsStatement = $pdo->prepare(
        "SELECT
            sa.id,
            sa.academic_year_id,
            ay.year_name AS academic_year_name,
            sa.attendance_date,
            sa.status,
            sa.check_in,
            sa.check_out,
            sa.late_minutes,
            sa.remarks
         FROM student_attendance sa
         LEFT JOIN academic_years ay
            ON ay.id = sa.academic_year_id
           AND ay.tenant_id = sa.tenant_id
         WHERE {$whereSql}
         ORDER BY sa.attendance_date DESC, sa.id DESC"
    );
    $rowsStatement->execute($params);

    return [
        'summary' => $summary,
        'rows' => $rowsStatement->fetchAll(PDO::FETCH_ASSOC),
    ];
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new RuntimeException('Parent Attendance is read-only.', 405);
    }

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new RuntimeException('Database connection is unavailable.', 500);
    }

    foreach (['students', 'guardians', 'student_guardians'] as $requiredTable) {
        if (!paTableExists($pdo, $requiredTable)) {
            throw new RuntimeException('Required Parent data table is unavailable.', 500);
        }
    }

    $scope = paScope();
    paRequireParent($pdo, $scope);

    $guardian = paGuardian($pdo, $scope);
    $children = paChildren(
        $pdo,
        (int)$scope['tenant_id'],
        (int)$guardian['id'],
        (int)$scope['user_id']
    );

    $requestedStudentId = (int)($_GET['student_id'] ?? 0);
    $child = paSelectedChild($children, $requestedStudentId);

    $availableYears = paAttendanceYears(
        $pdo,
        (int)$scope['tenant_id'],
        (int)$child['id'],
        $child
    );

    $year = (int)($_GET['year'] ?? 0);
    if ($year < 2000 || $year > 2100) {
        $year = in_array((int)date('Y'), $availableYears, true)
            ? (int)date('Y')
            : (int)($availableYears[0] ?? date('Y'));
    }

    $month = (int)($_GET['month'] ?? date('n'));
    if ($month < 0 || $month > 12) {
        $month = (int)date('n');
    }

    $attendance = paAttendance(
        $pdo,
        (int)$scope['tenant_id'],
        (int)$child['id'],
        $year,
        $month
    );

    paJson(true, 'Parent attendance loaded.', [
        'parent' => [
            'guardian_id' => (int)$guardian['id'],
            'guardian_name' => (string)($guardian['guardian_name'] ?? 'Parent'),
            'relationship' => (string)($guardian['relationship'] ?? ''),
        ],
        'children' => $children,
        'selected_child' => $child,
        'filters' => [
            'year' => $year,
            'month' => $month,
            'available_years' => $availableYears,
        ],
        'summary' => $attendance['summary'],
        'rows' => $attendance['rows'],
        'read_only' => true,
        'generated_at' => date('c'),
    ]);
} catch (Throwable $exception) {
    $status = (int)$exception->getCode();
    if ($status < 400 || $status > 599) {
        $status = 500;
    }

    error_log('Parent Attendance API: ' . $exception->getMessage());

    paJson(
        false,
        $status >= 500
            ? 'Unable to load Parent Attendance.'
            : $exception->getMessage(),
        [],
        $status
    );
}

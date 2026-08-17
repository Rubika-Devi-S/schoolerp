<?php
declare(strict_types=1);

$pageTitle = 'Dashboard';
$pageKey   = 'dashboard';
$sidebarFile = __DIR__ . '/sidebar.php';

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (function_exists('require_login')) {
    require_login();
}

if (!function_exists('dashboard_table_exists')) {
    function dashboard_table_exists(PDO $pdo, string $table): bool
    {
        static $cache = [];

        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name = :table_name"
            );
            $stmt->execute(['table_name' => $table]);
            return $cache[$table] = ((int)$stmt->fetchColumn() > 0);
        } catch (Throwable $e) {
            error_log('Dashboard table check failed: ' . $e->getMessage());
            return $cache[$table] = false;
        }
    }
}

if (!function_exists('dashboard_safe')) {
    function dashboard_safe(
        callable $callback,
        mixed $default,
        string $label,
        array &$errors
    ): mixed {
        try {
            return $callback();
        } catch (Throwable $e) {
            error_log('Dashboard [' . $label . '] ' . $e->getMessage());
            $errors[] = $label;
            return $default;
        }
    }
}

if (!function_exists('dashboard_money')) {
    function dashboard_money(float $amount): string
    {
        return '₹' . number_format($amount, 2);
    }
}

if (!function_exists('dashboard_short_money')) {
    function dashboard_short_money(float $amount): string
    {
        if ($amount >= 10000000) {
            return '₹' . number_format($amount / 10000000, 2) . 'Cr';
        }

        if ($amount >= 100000) {
            return '₹' . number_format($amount / 100000, 2) . 'L';
        }

        if ($amount >= 1000) {
            return '₹' . number_format($amount / 1000, 1) . 'K';
        }

        return '₹' . number_format($amount, 0);
    }
}

if (!function_exists('dashboard_relative_time')) {
    function dashboard_relative_time(?string $value): string
    {
        if (!$value) {
            return '';
        }

        try {
            $date = new DateTimeImmutable($value);
            $now = new DateTimeImmutable();
            $seconds = max(0, $now->getTimestamp() - $date->getTimestamp());

            if ($seconds < 60) {
                return 'Just now';
            }

            $minutes = intdiv($seconds, 60);
            if ($minutes < 60) {
                return $minutes . ' min ago';
            }

            $hours = intdiv($minutes, 60);
            if ($hours < 24) {
                return $hours . ' hr' . ($hours === 1 ? '' : 's') . ' ago';
            }

            $days = intdiv($hours, 24);
            if ($days < 7) {
                return $days . ' day' . ($days === 1 ? '' : 's') . ' ago';
            }

            return $date->format('d M Y');
        } catch (Throwable) {
            return '';
        }
    }
}

$currentUser = function_exists('current_user') ? current_user() : [];
$currentUser = is_array($currentUser) ? $currentUser : [];

$tenantId = (int)(
    $currentUser['tenant_id']
    ?? $currentUser['school_id']
    ?? $_SESSION['tenant_id']
    ?? $_SESSION['school_id']
    ?? 0
);

$branchId = (int)(
    $currentUser['branch_id']
    ?? $currentUser['default_branch_id']
    ?? $_SESSION['branch_id']
    ?? $_SESSION['default_branch_id']
    ?? 0
);

if ($tenantId <= 0) {
    http_response_code(403);
    exit('School session is unavailable.');
}

$dashboardErrors = [];
$today = date('Y-m-d');

/*
 * Academic year:
 * first use the shared topbar/session selected year;
 * otherwise use academic_years.is_current = 1.
 */
$academicYearId = (int)(
    $_SESSION['academic_year_id']
    ?? $_SESSION['selected_academic_year_id']
    ?? $_SESSION['current_academic_year_id']
    ?? 0
);

$academicYear = [];

if (dashboard_table_exists($pdo, 'academic_years')) {
    $academicYear = dashboard_safe(
        static function () use ($pdo, $tenantId, $academicYearId): array {
            if ($academicYearId > 0) {
                $stmt = $pdo->prepare(
                    "SELECT id, year_name, start_date, end_date, is_current, status
                     FROM academic_years
                     WHERE tenant_id = :tenant_id
                       AND id = :academic_year_id
                     LIMIT 1"
                );
                $stmt->execute([
                    'tenant_id' => $tenantId,
                    'academic_year_id' => $academicYearId,
                ]);

                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (is_array($row)) {
                    return $row;
                }
            }

            $stmt = $pdo->prepare(
                "SELECT id, year_name, start_date, end_date, is_current, status
                 FROM academic_years
                 WHERE tenant_id = :tenant_id
                 ORDER BY is_current DESC, start_date DESC, id DESC
                 LIMIT 1"
            );
            $stmt->execute(['tenant_id' => $tenantId]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : [];
        },
        [],
        'academic year',
        $dashboardErrors
    );
}

if ($academicYear) {
    $academicYearId = (int)$academicYear['id'];
}

$academicYearName = (string)($academicYear['year_name'] ?? '');
$academicYearStart = (string)($academicYear['start_date'] ?? '');
$academicYearEnd = (string)($academicYear['end_date'] ?? '');

/*
 * Total Students
 * Current UI label is Total Students.
 */
$totalStudents = 0;

if (dashboard_table_exists($pdo, 'students')) {
    $totalStudents = dashboard_safe(
        static function () use ($pdo, $tenantId, $branchId): int {
            $sql = "SELECT COUNT(*)
                    FROM students
                    WHERE tenant_id = :tenant_id
                      AND status = 'active'
                      AND deleted_at IS NULL";

            $params = ['tenant_id' => $tenantId];

            if ($branchId > 0) {
                $sql .= " AND branch_id = :branch_id";
                $params['branch_id'] = $branchId;
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            return (int)$stmt->fetchColumn();
        },
        0,
        'students',
        $dashboardErrors
    );
}

/*
 * Total Teachers / Staff.
 * Your database stores teacher/staff records in staff_members.
 */
$totalTeachers = 0;

if (dashboard_table_exists($pdo, 'staff_members')) {
    $totalTeachers = dashboard_safe(
        static function () use ($pdo, $tenantId, $branchId): int {
            $sql = "SELECT COUNT(*)
                    FROM staff_members
                    WHERE tenant_id = :tenant_id
                      AND status = 'active'
                      AND deleted_at IS NULL";

            $params = ['tenant_id' => $tenantId];

            if ($branchId > 0) {
                $sql .= " AND branch_id = :branch_id";
                $params['branch_id'] = $branchId;
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            return (int)$stmt->fetchColumn();
        },
        0,
        'staff',
        $dashboardErrors
    );
}

/*
 * Today's Attendance.
 * Status values in your DB:
 * present, absent, half_day, late, leave, on_duty, holiday.
 */
$attendance = [
    'present' => 0,
    'absent' => 0,
    'leave' => 0,
    'half_day' => 0,
    'late' => 0,
    'on_duty' => 0,
    'holiday' => 0,
    'marked' => 0,
];

if (dashboard_table_exists($pdo, 'student_attendance')) {
    $attendanceData = dashboard_safe(
        static function () use (
            $pdo,
            $tenantId,
            $branchId,
            $academicYearId,
            $today
        ): array {
            $sql = "SELECT
                        COALESCE(SUM(status = 'present'), 0) AS present_count,
                        COALESCE(SUM(status = 'absent'), 0) AS absent_count,
                        COALESCE(SUM(status = 'leave'), 0) AS leave_count,
                        COALESCE(SUM(status = 'half_day'), 0) AS half_day_count,
                        COALESCE(SUM(status = 'late'), 0) AS late_count,
                        COALESCE(SUM(status = 'on_duty'), 0) AS on_duty_count,
                        COALESCE(SUM(status = 'holiday'), 0) AS holiday_count,
                        COUNT(*) AS marked_count
                    FROM student_attendance
                    WHERE tenant_id = :tenant_id
                      AND attendance_date = :attendance_date";

            $params = [
                'tenant_id' => $tenantId,
                'attendance_date' => $today,
            ];

            if ($branchId > 0) {
                $sql .= " AND branch_id = :branch_id";
                $params['branch_id'] = $branchId;
            }

            if ($academicYearId > 0) {
                $sql .= " AND academic_year_id = :academic_year_id";
                $params['academic_year_id'] = $academicYearId;
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : [];
        },
        [],
        'attendance',
        $dashboardErrors
    );

    $attendance['present'] = (int)($attendanceData['present_count'] ?? 0);
    $attendance['absent'] = (int)($attendanceData['absent_count'] ?? 0);
    $attendance['leave'] = (int)($attendanceData['leave_count'] ?? 0);
    $attendance['half_day'] = (int)($attendanceData['half_day_count'] ?? 0);
    $attendance['late'] = (int)($attendanceData['late_count'] ?? 0);
    $attendance['on_duty'] = (int)($attendanceData['on_duty_count'] ?? 0);
    $attendance['holiday'] = (int)($attendanceData['holiday_count'] ?? 0);
    $attendance['marked'] = (int)($attendanceData['marked_count'] ?? 0);
}

$attendancePresentEquivalent =
    $attendance['present']
    + $attendance['late']
    + $attendance['on_duty']
    + ($attendance['half_day'] * 0.5);

$attendancePercentage = $attendance['marked'] > 0
    ? round(($attendancePresentEquivalent / $attendance['marked']) * 100, 1)
    : 0.0;

$attendancePresentDisplay =
    $attendance['present']
    + $attendance['late']
    + $attendance['on_duty'];

/*
 * Monthly Fee Collection.
 * fee_receipts is the correct posted collection table.
 */
$monthlyCollection = 0.0;

if (dashboard_table_exists($pdo, 'fee_receipts')) {
    $monthlyCollection = dashboard_safe(
        static function () use (
            $pdo,
            $tenantId,
            $branchId,
            $academicYearId
        ): float {
            $sql = "SELECT COALESCE(SUM(paid_amount), 0)
                    FROM fee_receipts
                    WHERE tenant_id = :tenant_id
                      AND payment_status <> 'reversed'
                      AND receipt_date >= :month_start
                      AND receipt_date < :next_month";

            $params = [
                'tenant_id' => $tenantId,
                'month_start' => date('Y-m-01 00:00:00'),
                'next_month' => date(
                    'Y-m-01 00:00:00',
                    strtotime('first day of next month')
                ),
            ];

            if ($branchId > 0) {
                $sql .= " AND branch_id = :branch_id";
                $params['branch_id'] = $branchId;
            }

            if ($academicYearId > 0) {
                $sql .= " AND academic_year_id = :academic_year_id";
                $params['academic_year_id'] = $academicYearId;
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            return (float)$stmt->fetchColumn();
        },
        0.0,
        'monthly fee collection',
        $dashboardErrors
    );
}

/*
 * Pending fees.
 * Kept as useful supporting information without adding another top card,
 * so your current four-card dashboard UI remains unchanged.
 */
$pendingFees = 0.0;
$pendingFeeStudents = 0;

if (
    dashboard_table_exists($pdo, 'student_fee_assignments')
    && dashboard_table_exists($pdo, 'students')
) {
    $pendingData = dashboard_safe(
        static function () use (
            $pdo,
            $tenantId,
            $branchId,
            $academicYearId
        ): array {
            $sql = "SELECT
                        COALESCE(SUM(sfa.balance_amount), 0) AS pending_amount,
                        COUNT(DISTINCT sfa.student_id) AS pending_students
                    FROM student_fee_assignments sfa
                    INNER JOIN students s
                        ON s.id = sfa.student_id
                       AND s.tenant_id = sfa.tenant_id
                    WHERE sfa.tenant_id = :tenant_id
                      AND sfa.assignment_status = 'active'
                      AND sfa.balance_amount > 0
                      AND s.deleted_at IS NULL";

            $params = ['tenant_id' => $tenantId];

            if ($academicYearId > 0) {
                $sql .= " AND sfa.academic_year_id = :academic_year_id";
                $params['academic_year_id'] = $academicYearId;
            }

            if ($branchId > 0) {
                $sql .= " AND s.branch_id = :branch_id";
                $params['branch_id'] = $branchId;
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : [];
        },
        [],
        'pending fees',
        $dashboardErrors
    );

    $pendingFees = (float)($pendingData['pending_amount'] ?? 0);
    $pendingFeeStudents = (int)($pendingData['pending_students'] ?? 0);
}

/*
 * Fee chart: uses the selected/current academic year's actual receipt data.
 */
$feeMonthLabels = [];
$feeMonthData = [];
$feeMonthKeys = [];

$chartStart = $academicYearStart !== ''
    ? $academicYearStart
    : date('Y-04-01');

$chartEnd = $academicYearEnd !== ''
    ? $academicYearEnd
    : date('Y-03-31', strtotime('+1 year', strtotime($chartStart)));

try {
    $cursor = new DateTimeImmutable(date('Y-m-01', strtotime($chartStart)));
    $limit = new DateTimeImmutable(date('Y-m-01', strtotime($chartEnd)));
    $guard = 0;

    while ($cursor <= $limit && $guard < 12) {
        $key = $cursor->format('Y-m');
        $feeMonthKeys[] = $key;
        $feeMonthLabels[] = $cursor->format('M');
        $feeMonthData[$key] = 0.0;
        $cursor = $cursor->modify('+1 month');
        $guard++;
    }
} catch (Throwable) {
    $feeMonthKeys = [];
    $feeMonthLabels = [];
    $feeMonthData = [];
}

if (
    $feeMonthKeys
    && dashboard_table_exists($pdo, 'fee_receipts')
) {
    $feeRows = dashboard_safe(
        static function () use (
            $pdo,
            $tenantId,
            $branchId,
            $academicYearId
        ): array {
            $sql = "SELECT
                        DATE_FORMAT(receipt_date, '%Y-%m') AS month_key,
                        COALESCE(SUM(paid_amount), 0) AS amount
                    FROM fee_receipts
                    WHERE tenant_id = :tenant_id
                      AND payment_status <> 'reversed'";

            $params = ['tenant_id' => $tenantId];

            if ($branchId > 0) {
                $sql .= " AND branch_id = :branch_id";
                $params['branch_id'] = $branchId;
            }

            if ($academicYearId > 0) {
                $sql .= " AND academic_year_id = :academic_year_id";
                $params['academic_year_id'] = $academicYearId;
            }

            $sql .= " GROUP BY DATE_FORMAT(receipt_date, '%Y-%m')
                      ORDER BY month_key";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        },
        [],
        'fee chart',
        $dashboardErrors
    );

    foreach ($feeRows as $row) {
        $key = (string)($row['month_key'] ?? '');
        if (array_key_exists($key, $feeMonthData)) {
            $feeMonthData[$key] = (float)($row['amount'] ?? 0);
        }
    }
}

$feeChartData = [];
foreach ($feeMonthKeys as $key) {
    $feeChartData[] = round((float)($feeMonthData[$key] ?? 0) / 100000, 2);
}

/*
 * Upcoming Exams and Academic Calendar events.
 */
$upcomingItems = [];

if (dashboard_table_exists($pdo, 'exams')) {
    $examRows = dashboard_safe(
        static function () use (
            $pdo,
            $tenantId,
            $branchId,
            $academicYearId,
            $today
        ): array {
            $sql = "SELECT
                        e.id,
                        e.exam_name,
                        e.start_date,
                        e.end_date,
                        e.exam_status,
                        c.class_name
                    FROM exams e
                    LEFT JOIN classes c
                        ON c.id = e.class_id
                       AND c.tenant_id = e.tenant_id
                    WHERE e.tenant_id = :tenant_id
                      AND e.end_date >= :today
                      AND e.exam_status IN ('draft','scheduled','ongoing')";

            $params = [
                'tenant_id' => $tenantId,
                'today' => $today,
            ];

            if ($academicYearId > 0) {
                $sql .= " AND e.academic_year_id = :academic_year_id";
                $params['academic_year_id'] = $academicYearId;
            }

            if ($branchId > 0) {
                $sql .= " AND (e.branch_id IS NULL OR e.branch_id = :branch_id)";
                $params['branch_id'] = $branchId;
            }

            $sql .= " ORDER BY e.start_date ASC, e.id ASC
                      LIMIT 8";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        },
        [],
        'upcoming exams',
        $dashboardErrors
    );

    foreach ($examRows as $row) {
        $upcomingItems[] = [
            'title' => (string)$row['exam_name'],
            'date' => (string)$row['start_date'],
            'location' => trim((string)($row['class_name'] ?? '')),
            'icon' => 'book-open-check',
            'class' => 'event-blue',
            'type' => 'Exam',
        ];
    }
}

if (dashboard_table_exists($pdo, 'academic_year_module_records')) {
    $calendarRows = dashboard_safe(
        static function () use ($pdo, $tenantId): array {
            $stmt = $pdo->prepare(
                "SELECT id, record_data, created_at
                 FROM academic_year_module_records
                 WHERE tenant_id = :tenant_id
                   AND module_key = 'academic_calendar'
                   AND status = 'active'
                 ORDER BY id DESC
                 LIMIT 100"
            );
            $stmt->execute(['tenant_id' => $tenantId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        },
        [],
        'calendar events',
        $dashboardErrors
    );

    foreach ($calendarRows as $row) {
        $data = json_decode((string)$row['record_data'], true);
        if (!is_array($data)) {
            continue;
        }

        $eventDate = trim((string)($data['event_date'] ?? ''));
        $eventYearId = (int)($data['academic_year_id'] ?? 0);

        if ($eventDate === '' || $eventDate < $today) {
            continue;
        }

        if (
            $academicYearId > 0
            && $eventYearId > 0
            && $eventYearId !== $academicYearId
        ) {
            continue;
        }

        $type = strtolower((string)($data['event_type'] ?? 'event'));

        $upcomingItems[] = [
            'title' => trim((string)($data['event_name'] ?? 'Event')),
            'date' => $eventDate,
            'location' => trim((string)($data['description'] ?? '')),
            'icon' => $type === 'holiday'
                ? 'calendar-off'
                : 'calendar-check',
            'class' => $type === 'holiday'
                ? 'event-orange'
                : 'event-purple',
            'type' => ucfirst($type),
        ];
    }
}

usort(
    $upcomingItems,
    static fn(array $a, array $b): int =>
        strcmp((string)$a['date'], (string)$b['date'])
);

$upcomingItems = array_slice($upcomingItems, 0, 4);

/*
 * Recent activities.
 */
$recentActivities = [];

if (dashboard_table_exists($pdo, 'activity_logs')) {
    $recentActivities = dashboard_safe(
        static function () use ($pdo, $tenantId, $branchId): array {
            $sql = "SELECT
                        id,
                        module_name,
                        action_key,
                        description,
                        created_at
                    FROM activity_logs
                    WHERE tenant_id = :tenant_id";

            $params = ['tenant_id' => $tenantId];

            if ($branchId > 0) {
                $sql .= " AND (branch_id IS NULL OR branch_id = :branch_id)";
                $params['branch_id'] = $branchId;
            }

            $sql .= " ORDER BY created_at DESC, id DESC
                      LIMIT 6";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        },
        [],
        'recent activities',
        $dashboardErrors
    );
}

require dirname(__DIR__) . '/includes/layout-start.php';

/*
 * Use the existing common toaster only.
 */
$commonToasterCandidates = [
    dirname(__DIR__) . '/includes/common/toaster.php',
    dirname(__DIR__) . '/includes/common-toast.php',
    dirname(__DIR__) . '/includes/common_toaster.php',
    dirname(__DIR__) . '/includes/toast.php',
];

foreach ($commonToasterCandidates as $commonToasterFile) {
    if (is_file($commonToasterFile)) {
        require_once $commonToasterFile;
        break;
    }
}

unset($commonToasterCandidates, $commonToasterFile);
?>

<style>
/* =========================================================
   DASHBOARD STATISTIC CARDS - REFERENCE STYLE
   Only the top statistic cards are modified.
   ========================================================= */
.dashboard-page .metric-grid{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:14px;
}

.dashboard-page .metric-card{
    position:relative;
    min-width:0;
    min-height:124px;
    padding:20px 22px;
    display:flex;
    align-items:center;
    gap:16px;
    overflow:hidden;

    color:#fff;
    border:0;
    border-radius:15px;

    box-shadow:
        0 10px 24px rgba(15,23,42,.08);

    isolation:isolate;
}

.dashboard-page .metric-card::before{
    content:"";
    position:absolute;
    z-index:-1;
    width:132px;
    height:132px;
    right:-42px;
    top:-55px;
    border-radius:50%;
    background:rgba(255,255,255,.085);
}

.dashboard-page .metric-card::after{
    content:"";
    position:absolute;
    z-index:-1;
    width:52px;
    height:52px;
    right:14px;
    bottom:-31px;
    border-radius:50%;
    background:rgba(255,255,255,.045);
}

.dashboard-page .metric-purple{
    background:
        linear-gradient(135deg,#7548ee 0%,#5033d5 100%);
}

.dashboard-page .metric-blue{
    background:
        linear-gradient(135deg,#4b96ed 0%,#2e73dc 100%);
}

.dashboard-page .metric-green{
    background:
        linear-gradient(135deg,#43c987 0%,#20aa6f 100%);
}

.dashboard-page .metric-orange{
    background:
        linear-gradient(135deg,#ffb22a 0%,#ff8b19 100%);
}

.dashboard-page .metric-icon{
    position:relative;
    z-index:1;
    width:54px;
    height:54px;
    flex:0 0 54px;

    display:grid;
    place-items:center;

    color:#fff;
    background:rgba(255,255,255,.17);
    border:1px solid rgba(255,255,255,.09);
    border-radius:50%;
}

.dashboard-page .metric-icon svg{
    width:27px;
    height:27px;
    stroke-width:1.9;
}

.dashboard-page .metric-card>div:last-child{
    position:relative;
    z-index:1;
    min-width:0;
}

.dashboard-page .metric-card small{
    display:block;
    margin:0 0 5px;

    color:rgba(255,255,255,.94);
    font-size:11px;
    font-weight:700;
    line-height:1.2;
}

.dashboard-page .metric-value{
    overflow:hidden;

    color:#fff;
    font-size:clamp(27px,2.05vw,34px);
    font-weight:800;
    line-height:1;
    letter-spacing:-.035em;

    text-overflow:ellipsis;
    white-space:nowrap;
}

.dashboard-page .metric-trend{
    margin-top:8px;

    overflow:hidden;

    color:rgba(255,255,255,.92);
    font-size:9.5px;
    font-weight:650;
    line-height:1.3;

    text-overflow:ellipsis;
    white-space:nowrap;
}

@media(max-width:1199.98px){
    .dashboard-page .metric-grid{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }
}

@media(max-width:575.98px){
    .dashboard-page .metric-grid{
        grid-template-columns:1fr;
        gap:12px;
    }

    .dashboard-page .metric-card{
        min-height:108px;
        padding:17px 18px;
    }

    .dashboard-page .metric-icon{
        width:48px;
        height:48px;
        flex-basis:48px;
    }

    .dashboard-page .metric-icon svg{
        width:24px;
        height:24px;
    }

    .dashboard-page .metric-value{
        font-size:28px;
    }
}

/* Existing dashboard styles below - unchanged */
.dashboard-page{display:grid;gap:16px}
.dashboard-main-grid{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(310px,.85fr) minmax(300px,.8fr);gap:16px;align-items:stretch}
.dashboard-lower-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:16px;align-items:stretch}
.dashboard-card{overflow:hidden;background:var(--card-bg,#fff);border:1px solid var(--border-soft,#e7ebf3);border-radius:13px;box-shadow:0 5px 18px rgba(15,23,42,.04)}
.dashboard-card-header{min-height:54px;padding:13px 16px;display:flex;align-items:center;justify-content:space-between;gap:12px;border-bottom:1px solid var(--border-soft,#e7ebf3)}
.dashboard-card-header h2{margin:0;color:var(--text-main,#101a3b);font-size:14px;font-weight:700;letter-spacing:-.15px}
.dashboard-card-header a{font-size:11px;font-weight:700;text-decoration:none}
.dashboard-card-subtitle{color:var(--text-muted,#64748b);font-size:10px}
.dashboard-chart-summary{padding:16px 16px 0}
.dashboard-chart-summary strong,.dashboard-chart-summary small{display:block}
.dashboard-chart-summary strong{font-size:22px;font-weight:800;letter-spacing:-.5px}
.dashboard-chart-summary small{margin-top:3px;color:var(--text-muted,#64748b);font-size:10px;font-weight:700}
.dashboard-chart-box{height:245px;padding:5px 13px 15px}
.attendance-widget{padding:18px;display:grid;grid-template-columns:minmax(170px,1fr) minmax(120px,.7fr);align-items:center;gap:14px}
.attendance-chart-wrap{position:relative;height:210px}
.attendance-center{position:absolute;inset:50% auto auto 50%;z-index:2;text-align:center;transform:translate(-50%,-52%);pointer-events:none}
.attendance-center strong,.attendance-center small{display:block}
.attendance-center strong{font-size:22px;font-weight:800}
.attendance-center small{margin-top:2px;color:var(--text-muted,#64748b);font-size:9px}
.attendance-legend{display:grid;gap:14px}
.attendance-legend-row{display:grid;grid-template-columns:9px 1fr;gap:8px;align-items:start;font-size:11px}
.attendance-legend-row span:first-child{width:9px;height:9px;margin-top:4px;border-radius:50%}
.attendance-legend-row strong,.attendance-legend-row small{display:block}
.attendance-legend-row strong{font-size:11px}
.attendance-legend-row small{margin-top:3px;color:var(--text-muted,#64748b);font-size:10px}
.event-list{padding:3px 15px 8px}
.event-item{padding:12px 0;display:grid;grid-template-columns:40px minmax(0,1fr);gap:11px;align-items:center;border-bottom:1px solid var(--border-soft,#e7ebf3)}
.event-item:last-child{border-bottom:0}
.event-icon{width:40px;height:40px;display:grid;place-items:center;border-radius:50%}
.event-icon svg{width:18px;height:18px}
.event-purple{color:#6747dd;background:#eeeafd}
.event-blue{color:#3168d8;background:#eaf1ff}
.event-green{color:#189354;background:#e9f8ef}
.event-orange{color:#df7d0b;background:#fff2df}
.event-copy strong,.event-copy small{display:block}
.event-copy strong{font-size:11px}
.event-copy small{margin-top:2px;color:var(--text-muted,#64748b);font-size:9px;line-height:1.4}
.activity-timeline{padding:10px 16px 14px}
.activity-timeline-item{position:relative;padding:8px 0 10px 23px}
.activity-timeline-item::before{position:absolute;top:14px;left:4px;width:7px;height:7px;content:"";border-radius:50%;background:linear-gradient(135deg,var(--brand-1,#6547e8),var(--brand-2,#315ed8))}
.activity-timeline-item::after{position:absolute;top:21px;bottom:-3px;left:7px;width:1px;content:"";background:#dfe4ef}
.activity-timeline-item:last-child::after{display:none}
.activity-timeline-item strong,.activity-timeline-item small{display:block}
.activity-timeline-item strong{font-size:11px;font-weight:650}
.activity-timeline-item small{margin-top:3px;color:var(--text-muted,#64748b);font-size:9px}
.pending-summary{padding:15px 16px;display:grid;grid-template-columns:1fr 1fr;gap:12px}
.pending-box{padding:14px;border:1px solid var(--border-soft,#e7ebf3);border-radius:11px;background:#f8fafc}
.pending-box small,.pending-box strong{display:block}
.pending-box small{font-size:10px;color:var(--text-muted,#64748b)}
.pending-box strong{margin-top:5px;font-size:18px}
.dashboard-empty{padding:26px 14px;text-align:center;color:var(--text-muted,#64748b);font-size:10px}
@media(max-width:1399.98px){.dashboard-main-grid{grid-template-columns:1fr 1fr}.dashboard-main-grid>article:first-child{grid-column:1/-1}}
@media(max-width:991.98px){.dashboard-main-grid,.dashboard-lower-grid{grid-template-columns:1fr}.dashboard-main-grid>article:first-child{grid-column:auto}}
@media(max-width:575.98px){.attendance-widget{grid-template-columns:1fr}.dashboard-chart-box{height:225px}.pending-summary{grid-template-columns:1fr}}
</style>

<div class="dashboard-page">
    <div class="page-heading">
        <div>
            <h1 class="page-title">School Dashboard</h1>
            <p class="page-subtitle">
                Welcome back. Here is today’s school overview.
            </p>
        </div>

        <div class="page-actions">
            <a href="student-admission.php" class="btn-ui">
                <i data-lucide="user-plus"></i>
                New Admission
            </a>

            <a href="attendance.php" class="btn-ui btn-primary-ui">
                <i data-lucide="calendar-check"></i>
                Mark Attendance
            </a>
        </div>
    </div>

    <section class="metric-grid">
        <article class="metric-card metric-purple">
            <div class="metric-icon">
                <i data-lucide="users"></i>
            </div>
            <div>
                <small>Total Students</small>
                <div class="metric-value"><?= number_format($totalStudents) ?></div>
                <div class="metric-trend">Active students</div>
            </div>
        </article>

        <article class="metric-card metric-blue">
            <div class="metric-icon">
                <i data-lucide="presentation"></i>
            </div>
            <div>
                <small>Total Teachers</small>
                <div class="metric-value"><?= number_format($totalTeachers) ?></div>
                <div class="metric-trend">Active teachers / staff</div>
            </div>
        </article>

        <article class="metric-card metric-green">
            <div class="metric-icon">
                <i data-lucide="user-check"></i>
            </div>
            <div>
                <small>Today’s Attendance</small>
                <div class="metric-value"><?= number_format($attendancePresentDisplay) ?></div>
                <div class="metric-trend"><?= number_format($attendancePercentage,1) ?>% present</div>
            </div>
        </article>

        <article class="metric-card metric-orange">
            <div class="metric-icon">
                <i data-lucide="indian-rupee"></i>
            </div>
            <div>
                <small>Monthly Collection</small>
                <div class="metric-value"><?= e(dashboard_money($monthlyCollection)) ?></div>
                <div class="metric-trend"><?= e(date('F Y')) ?></div>
            </div>
        </article>
    </section>

    <section class="dashboard-main-grid">
        <article class="dashboard-card">
            <div class="dashboard-card-header">
                <h2>Monthly Fee Collection</h2>
                <span class="status blue">
                    <?= e($academicYearName !== '' ? $academicYearName : 'Current Year') ?>
                </span>
            </div>

            <div class="dashboard-chart-summary">
                <strong><?= e(dashboard_money($monthlyCollection)) ?></strong>
                <small>
                    Pending: <?= e(dashboard_money($pendingFees)) ?>
                    · <?= number_format($pendingFeeStudents) ?> students
                </small>
            </div>

            <div class="dashboard-chart-box">
                <canvas id="dashboardFeeChart"></canvas>
            </div>
        </article>

        <article class="dashboard-card">
            <div class="dashboard-card-header">
                <h2>Student Attendance Today</h2>
                <span class="status green"><?= number_format($attendancePercentage,1) ?>%</span>
            </div>

            <div class="attendance-widget">
                <div class="attendance-chart-wrap">
                    <div class="attendance-center">
                        <strong><?= number_format($attendancePercentage,1) ?>%</strong>
                        <small>Attendance</small>
                    </div>
                    <canvas id="dashboardAttendanceChart"></canvas>
                </div>

                <div class="attendance-legend">
                    <div class="attendance-legend-row">
                        <span style="background:#2caf6c"></span>
                        <div>
                            <strong>Present</strong>
                            <small><?= number_format($attendancePresentDisplay) ?></small>
                        </div>
                    </div>

                    <div class="attendance-legend-row">
                        <span style="background:#ffad1f"></span>
                        <div>
                            <strong>Leave</strong>
                            <small><?= number_format($attendance['leave']) ?></small>
                        </div>
                    </div>

                    <div class="attendance-legend-row">
                        <span style="background:#f04468"></span>
                        <div>
                            <strong>Absent</strong>
                            <small><?= number_format($attendance['absent']) ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </article>

        <article class="dashboard-card">
            <div class="dashboard-card-header">
                <h2>Upcoming Exams / Events</h2>
                <a href="years.php">View Calendar</a>
            </div>

            <?php if ($upcomingItems): ?>
                <div class="event-list">
                    <?php foreach ($upcomingItems as $event): ?>
                        <div class="event-item">
                            <div class="event-icon <?= e($event['class']) ?>">
                                <i data-lucide="<?= e($event['icon']) ?>"></i>
                            </div>

                            <div class="event-copy">
                                <strong><?= e($event['title']) ?></strong>
                                <small>
                                    <?= e(date('d M Y', strtotime($event['date']))) ?>
                                    · <?= e($event['type']) ?>
                                </small>
                                <?php if ($event['location'] !== ''): ?>
                                    <small><?= e($event['location']) ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="dashboard-empty">No upcoming exams or events.</div>
            <?php endif; ?>
        </article>
    </section>

    <section class="dashboard-lower-grid">
        <article class="dashboard-card">
            <div class="dashboard-card-header">
                <h2>Fee Status</h2>
                <a href="fee-collection.php">Open Fees</a>
            </div>

            <div class="pending-summary">
                <div class="pending-box">
                    <small>Pending Fees</small>
                    <strong><?= e(dashboard_money($pendingFees)) ?></strong>
                </div>

                <div class="pending-box">
                    <small>Students With Pending Fees</small>
                    <strong><?= number_format($pendingFeeStudents) ?></strong>
                </div>
            </div>
        </article>

        <article class="dashboard-card">
            <div class="dashboard-card-header">
                <h2>Recent Activities</h2>
                <a href="activity-logs.php">View All</a>
            </div>

            <?php if ($recentActivities): ?>
                <div class="activity-timeline">
                    <?php foreach ($recentActivities as $activity): ?>
                        <?php
                        $description = trim((string)($activity['description'] ?? ''));

                        if ($description === '') {
                            $description =
                                trim((string)($activity['module_name'] ?? 'Activity'))
                                . ' - '
                                . ucfirst((string)($activity['action_key'] ?? 'updated'));
                        }
                        ?>
                        <div class="activity-timeline-item">
                            <strong><?= e($description) ?></strong>
                            <small>
                                <?= e((string)($activity['module_name'] ?? '')) ?>
                                <?php if (!empty($activity['created_at'])): ?>
                                    · <?= e(dashboard_relative_time((string)$activity['created_at'])) ?>
                                <?php endif; ?>
                            </small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="dashboard-empty">No recent activities.</div>
            <?php endif; ?>
        </article>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof window.Chart === 'undefined') {
        return;
    }

    const feeCanvas = document.getElementById('dashboardFeeChart');

    if (feeCanvas) {
        new Chart(feeCanvas, {
            type: 'bar',
            data: {
                labels: <?= json_encode($feeMonthLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
                datasets: [{
                    label: 'Collection in Lakhs',
                    data: <?= json_encode($feeChartData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
                    backgroundColor: '#6550df',
                    borderRadius: 6,
                    borderSkipped: false,
                    maxBarThickness: 28
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 9 } }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#edf0f6' },
                        ticks: {
                            font: { size: 9 },
                            callback: function (value) {
                                return value + 'L';
                            }
                        }
                    }
                }
            }
        });
    }

    const attendanceCanvas =
        document.getElementById('dashboardAttendanceChart');

    if (attendanceCanvas) {
        new Chart(attendanceCanvas, {
            type: 'doughnut',
            data: {
                labels: [
                    'Present',
                    'Leave',
                    'Absent'
                ],
                datasets: [{
                    data: [
                        <?= (int)$attendancePresentDisplay ?>,
                        <?= (int)$attendance['leave'] ?>,
                        <?= (int)$attendance['absent'] ?>
                    ],
                    backgroundColor: [
                        '#2caf6c',
                        '#ffad1f',
                        '#f04468'
                    ],
                    borderWidth: 0,
                    hoverOffset: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }
});
</script>

<?php if ($dashboardErrors): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const message =
        'Some dashboard information could not be loaded. Please verify the related module data.';

    if (typeof window.showToast === 'function') {
        window.showToast('warning', message, {title: 'Dashboard'});
        return;
    }

    if (
        window.SchoolToast
        && typeof window.SchoolToast.warning === 'function'
    ) {
        window.SchoolToast.warning(message, {title: 'Dashboard'});
    }
});
</script>
<?php endif; ?>

<?php require dirname(__DIR__) . '/includes/layout-end.php'; ?>

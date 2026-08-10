<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_login();

$pageTitle = 'Super Admin Dashboard';
$pageKey   = 'super_admin_dashboard';

/*
|--------------------------------------------------------------------------
| Use the Super Admin sidebar without changing the shared layout template.
|--------------------------------------------------------------------------
*/
$sidebarFile = __DIR__ . '/sidebar.php';

$currentUser = function_exists('current_user') ? current_user() : [];
$roleId      = (int)($currentUser['role_id'] ?? $_SESSION['role_id'] ?? 0);
$roleKey     = strtolower(trim((string)($_SESSION['role_key'] ?? '')));

if ($roleKey === '' && isset($pdo) && $pdo instanceof PDO
    && function_exists('school_table_exists')
    && school_table_exists($pdo, 'roles')
    && $roleId > 0) {
    try {
        $stmt = $pdo->prepare(
            "SELECT role_key
             FROM roles
             WHERE id = :role_id
               AND status = 'active'
             LIMIT 1"
        );
        $stmt->execute(['role_id' => $roleId]);
        $roleKey = strtolower(trim((string)$stmt->fetchColumn()));
    } catch (Throwable $e) {
        error_log('super-admin/dashboard.php role lookup: ' . $e->getMessage());
    }
}

$isSuperAdmin = $roleId === 1
    || in_array($roleKey, ['super_admin', 'super-administrator', 'super_administrator'], true);

if (!$isSuperAdmin) {
    $baseUrl = defined('BASE_URL') ? rtrim((string)BASE_URL, '/') . '/' : '../';
    header('Location: ' . $baseUrl . 'school/dashboard.php');
    exit;
}

$canViewDashboard = !function_exists('has_permission')
    || has_permission('super_admin_dashboard', 'view')
    || has_permission('dashboard', 'view');

if (!$canViewDashboard) {
    http_response_code(403);
    exit('Access denied.');
}

$baseUrl = defined('BASE_URL')
    ? rtrim((string)BASE_URL, '/') . '/'
    : '../';

$superAdminBaseUrl = $baseUrl . 'super-admin/';

if (!function_exists('superAdminCurrency')) {
    function superAdminCurrency(float|int|string $amount): string
    {
        return '₹' . number_format((float)$amount, 0);
    }
}

if (!function_exists('superAdminTimeAgo')) {
    function superAdminTimeAgo(?string $dateTime): string
    {
        if (!$dateTime) {
            return 'Recently';
        }

        try {
            $time = new DateTimeImmutable($dateTime);
            $now  = new DateTimeImmutable('now');
            $seconds = max(0, $now->getTimestamp() - $time->getTimestamp());

            if ($seconds < 60) {
                return 'Just now';
            }

            $minutes = intdiv($seconds, 60);
            if ($minutes < 60) {
                return $minutes . ' minute' . ($minutes === 1 ? '' : 's') . ' ago';
            }

            $hours = intdiv($minutes, 60);
            if ($hours < 24) {
                return $hours . ' hour' . ($hours === 1 ? '' : 's') . ' ago';
            }

            $days = intdiv($hours, 24);
            if ($days < 30) {
                return $days . ' day' . ($days === 1 ? '' : 's') . ' ago';
            }

            return $time->format('d M Y');
        } catch (Throwable) {
            return 'Recently';
        }
    }
}

if (!function_exists('superAdminSchoolBadge')) {
    function superAdminSchoolBadge(string $status): string
    {
        return match (strtolower($status)) {
            'active'    => 'dashboard-badge-success',
            'trial'     => 'dashboard-badge-info',
            'suspended',
            'cancelled' => 'dashboard-badge-warning',
            default     => 'dashboard-badge-info',
        };
    }
}

if (!function_exists('superAdminCan')) {
    function superAdminCan(string $pageKey, string $action = 'view'): bool
    {
        if (!function_exists('has_permission')) {
            return true;
        }

        return has_permission($pageKey, $action)
            || has_permission('super_admin_dashboard', 'edit')
            || has_permission('dashboard', 'view');
    }
}

/*
|--------------------------------------------------------------------------
| Dashboard fallback data
|--------------------------------------------------------------------------
| Database values replace these automatically whenever the relevant tables
| are available. This keeps the UI usable in demo and partial-install modes.
*/
$dashboardStats = [
    'schools'              => 12,
    'active_subscriptions' => 10,
    'users'                => 326,
    'monthly_revenue'      => 185000,
    'branches'             => 24,
    'plans'                => 3,
    'modules'              => 18,
    'renewals_due'         => 4,
];

$schoolStatus = [
    'active'    => 8,
    'trial'     => 3,
    'suspended' => 1,
    'cancelled' => 0,
];

$recentSchools = [
    [
        'tenant_code' => 'SCH-0012',
        'school_name'  => 'Green Valley Public School',
        'plan_name'    => 'Enterprise',
        'user_count'   => 46,
        'registered'   => '26 Jul 2026',
        'status'       => 'Active',
    ],
    [
        'tenant_code' => 'SCH-0011',
        'school_name'  => 'Starlight Matric School',
        'plan_name'    => 'Professional',
        'user_count'   => 31,
        'registered'   => '24 Jul 2026',
        'status'       => 'Trial',
    ],
    [
        'tenant_code' => 'SCH-0010',
        'school_name'  => 'Bright Future Academy',
        'plan_name'    => 'Enterprise',
        'user_count'   => 52,
        'registered'   => '21 Jul 2026',
        'status'       => 'Active',
    ],
    [
        'tenant_code' => 'SCH-0009',
        'school_name'  => 'Sunrise International School',
        'plan_name'    => 'Starter',
        'user_count'   => 18,
        'registered'   => '18 Jul 2026',
        'status'       => 'Active',
    ],
    [
        'tenant_code' => 'SCH-0008',
        'school_name'  => 'National Model School',
        'plan_name'    => 'Professional',
        'user_count'   => 29,
        'registered'   => '14 Jul 2026',
        'status'       => 'Suspended',
    ],
];

$recentActivities = [
    ['title' => 'New school account created', 'time' => '4 minutes ago'],
    ['title' => 'Enterprise subscription activated', 'time' => '18 minutes ago'],
    ['title' => 'School Admin role permissions updated', 'time' => '1 hour ago'],
    ['title' => 'Sidebar module configuration published', 'time' => '2 hours ago'],
    ['title' => 'Platform database backup completed', 'time' => '3 hours ago'],
];

$upcomingRenewals = [
    [
        'title' => 'Green Valley Public School',
        'class' => 'Enterprise plan renewal',
        'days'  => '3 Days',
        'badge' => 'blue',
        'icon'  => 'calendar-clock',
    ],
    [
        'title' => 'Starlight Matric School',
        'class' => 'Trial period ending',
        'days'  => '7 Days',
        'badge' => 'orange',
        'icon'  => 'hourglass',
    ],
    [
        'title' => 'Bright Future Academy',
        'class' => 'Annual subscription renewal',
        'days'  => '14 Days',
        'badge' => 'green',
        'icon'  => 'refresh-cw',
    ],
];

$planSummary = [
    ['plan_name' => 'Enterprise',   'schools' => 5, 'price' => 9999, 'badge' => 'blue'],
    ['plan_name' => 'Professional', 'schools' => 4, 'price' => 5999, 'badge' => 'green'],
    ['plan_name' => 'Starter',      'schools' => 3, 'price' => 2999, 'badge' => 'orange'],
];

$revenueLabels = [];
$revenueValues = [];

for ($monthOffset = 11; $monthOffset >= 0; $monthOffset--) {
    $month = new DateTimeImmutable('first day of -' . $monthOffset . ' month');
    $revenueLabels[] = $month->format('M');
    $revenueValues[] = 72 + ((11 - $monthOffset) * 8);
}

/*
|--------------------------------------------------------------------------
| Load live platform data
|--------------------------------------------------------------------------
*/
if (isset($pdo) && $pdo instanceof PDO && !APP_DEMO_MODE) {
    try {
        if (school_table_exists($pdo, 'tenants')) {
            $dashboardStats['schools'] = (int)$pdo->query(
                'SELECT COUNT(*) FROM tenants'
            )->fetchColumn();

            $statusRows = $pdo->query(
                'SELECT status, COUNT(*) AS total
                 FROM tenants
                 GROUP BY status'
            )->fetchAll(PDO::FETCH_ASSOC);

            $schoolStatus = [
                'active'    => 0,
                'trial'     => 0,
                'suspended' => 0,
                'cancelled' => 0,
            ];

            foreach ($statusRows as $row) {
                $status = strtolower((string)($row['status'] ?? ''));
                if (array_key_exists($status, $schoolStatus)) {
                    $schoolStatus[$status] = (int)($row['total'] ?? 0);
                }
            }

            $recentSchoolSql = "
                SELECT
                    t.tenant_code,
                    t.school_name,
                    t.status,
                    t.created_at,
                    COALESCE(sp.plan_name, 'No Plan') AS plan_name,
                    (
                        SELECT COUNT(*)
                        FROM users u
                        WHERE u.tenant_id = t.id
                    ) AS user_count
                FROM tenants t
                LEFT JOIN (
                    SELECT ts1.*
                    FROM tenant_subscriptions ts1
                    INNER JOIN (
                        SELECT tenant_id, MAX(id) AS latest_id
                        FROM tenant_subscriptions
                        GROUP BY tenant_id
                    ) latest
                        ON latest.latest_id = ts1.id
                ) ts ON ts.tenant_id = t.id
                LEFT JOIN subscription_plans sp ON sp.id = ts.plan_id
                ORDER BY t.created_at DESC, t.id DESC
                LIMIT 5";

            if (school_table_exists($pdo, 'users')
                && school_table_exists($pdo, 'tenant_subscriptions')
                && school_table_exists($pdo, 'subscription_plans')) {
                $rows = $pdo->query($recentSchoolSql)->fetchAll(PDO::FETCH_ASSOC);

                if ($rows) {
                    $recentSchools = array_map(
                        static fn(array $row): array => [
                            'tenant_code' => (string)($row['tenant_code'] ?? ''),
                            'school_name'  => (string)($row['school_name'] ?? ''),
                            'plan_name'    => (string)($row['plan_name'] ?? 'No Plan'),
                            'user_count'   => (int)($row['user_count'] ?? 0),
                            'registered'   => !empty($row['created_at'])
                                ? date('d M Y', strtotime((string)$row['created_at']))
                                : '',
                            'status'       => ucfirst((string)($row['status'] ?? 'trial')),
                        ],
                        $rows
                    );
                }
            }
        }

        if (school_table_exists($pdo, 'users')) {
            $dashboardStats['users'] = (int)$pdo->query(
                "SELECT COUNT(*) FROM users WHERE status = 'active'"
            )->fetchColumn();
        }

        if (school_table_exists($pdo, 'branches')) {
            $dashboardStats['branches'] = (int)$pdo->query(
                "SELECT COUNT(*) FROM branches WHERE status = 'active'"
            )->fetchColumn();
        }

        if (school_table_exists($pdo, 'subscription_plans')) {
            $dashboardStats['plans'] = (int)$pdo->query(
                'SELECT COUNT(*) FROM subscription_plans WHERE is_active = 1'
            )->fetchColumn();
        }

        if (school_table_exists($pdo, 'app_modules')) {
            $dashboardStats['modules'] = (int)$pdo->query(
                'SELECT COUNT(*) FROM app_modules WHERE is_active = 1'
            )->fetchColumn();
        }

        if (school_table_exists($pdo, 'tenant_subscriptions')
            && school_table_exists($pdo, 'subscription_plans')) {
            $subscriptionSummary = $pdo->query(
                "SELECT
                    COUNT(*) AS active_count,
                    COALESCE(SUM(sp.monthly_price), 0) AS monthly_revenue
                 FROM tenant_subscriptions ts
                 INNER JOIN subscription_plans sp ON sp.id = ts.plan_id
                 WHERE ts.status = 'active'
                   AND CURDATE() BETWEEN ts.starts_on AND ts.ends_on"
            )->fetch(PDO::FETCH_ASSOC);

            if (is_array($subscriptionSummary)) {
                $dashboardStats['active_subscriptions'] =
                    (int)($subscriptionSummary['active_count'] ?? 0);
                $dashboardStats['monthly_revenue'] =
                    (float)($subscriptionSummary['monthly_revenue'] ?? 0);
            }

            $renewalStmt = $pdo->query(
                "SELECT
                    t.school_name,
                    sp.plan_name,
                    ts.ends_on,
                    DATEDIFF(ts.ends_on, CURDATE()) AS days_remaining
                 FROM tenant_subscriptions ts
                 INNER JOIN tenants t ON t.id = ts.tenant_id
                 INNER JOIN subscription_plans sp ON sp.id = ts.plan_id
                 WHERE ts.status IN ('active', 'trial')
                   AND ts.ends_on >= CURDATE()
                 ORDER BY ts.ends_on ASC
                 LIMIT 3"
            );

            $renewalRows = $renewalStmt->fetchAll(PDO::FETCH_ASSOC);
            if ($renewalRows) {
                $badgeCycle = ['blue', 'orange', 'green'];
                $upcomingRenewals = [];

                foreach ($renewalRows as $index => $row) {
                    $days = max(0, (int)($row['days_remaining'] ?? 0));
                    $upcomingRenewals[] = [
                        'title' => (string)($row['school_name'] ?? 'School'),
                        'class' => (string)($row['plan_name'] ?? 'Plan') . ' renewal',
                        'days'  => $days === 0 ? 'Today' : $days . ' Days',
                        'badge' => $badgeCycle[$index % count($badgeCycle)],
                        'icon'  => 'calendar-clock',
                    ];
                }
            }

            $dashboardStats['renewals_due'] = (int)$pdo->query(
                "SELECT COUNT(*)
                 FROM tenant_subscriptions
                 WHERE status IN ('active', 'trial')
                   AND ends_on BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"
            )->fetchColumn();

            $planRows = $pdo->query(
                "SELECT
                    sp.plan_name,
                    sp.monthly_price,
                    COUNT(ts.id) AS school_count
                 FROM subscription_plans sp
                 LEFT JOIN tenant_subscriptions ts
                    ON ts.plan_id = sp.id
                   AND ts.status IN ('active', 'trial')
                 WHERE sp.is_active = 1
                 GROUP BY sp.id, sp.plan_name, sp.monthly_price
                 ORDER BY school_count DESC, sp.monthly_price DESC
                 LIMIT 5"
            )->fetchAll(PDO::FETCH_ASSOC);

            if ($planRows) {
                $badges = ['blue', 'green', 'orange', 'blue', 'green'];
                $planSummary = [];

                foreach ($planRows as $index => $row) {
                    $planSummary[] = [
                        'plan_name' => (string)($row['plan_name'] ?? 'Plan'),
                        'schools'   => (int)($row['school_count'] ?? 0),
                        'price'     => (float)($row['monthly_price'] ?? 0),
                        'badge'     => $badges[$index % count($badges)],
                    ];
                }
            }

            $revenueLabels = [];
            $revenueValues = [];

            $monthlyRevenueStmt = $pdo->prepare(
                "SELECT COALESCE(SUM(sp.monthly_price), 0)
                 FROM tenant_subscriptions ts
                 INNER JOIN subscription_plans sp ON sp.id = ts.plan_id
                 WHERE ts.status IN ('active', 'trial')
                   AND ts.starts_on <= :month_end
                   AND ts.ends_on >= :month_start"
            );

            for ($monthOffset = 11; $monthOffset >= 0; $monthOffset--) {
                $month = new DateTimeImmutable('first day of -' . $monthOffset . ' month');
                $monthStart = $month->format('Y-m-01');
                $monthEnd   = $month->format('Y-m-t');

                $monthlyRevenueStmt->execute([
                    'month_start' => $monthStart,
                    'month_end'   => $monthEnd,
                ]);

                $revenueLabels[] = $month->format('M');
                $revenueValues[] = round(
                    ((float)$monthlyRevenueStmt->fetchColumn()) / 100000,
                    2
                );
            }
        }

        if (school_table_exists($pdo, 'activity_logs')) {
            $activitySql = "
                SELECT
                    COALESCE(
                        NULLIF(al.description, ''),
                        CONCAT(
                            al.module_name,
                            ' - ',
                            REPLACE(al.action_key, '_', ' ')
                        )
                    ) AS activity_title,
                    al.created_at";

            if (school_table_exists($pdo, 'users')) {
                $activitySql .= ", u.name AS user_name
                    FROM activity_logs al
                    LEFT JOIN users u ON u.id = al.user_id";
            } else {
                $activitySql .= ", NULL AS user_name
                    FROM activity_logs al";
            }

            $activitySql .= " ORDER BY al.created_at DESC, al.id DESC LIMIT 5";

            $activityRows = $pdo->query($activitySql)->fetchAll(PDO::FETCH_ASSOC);
            if ($activityRows) {
                $recentActivities = array_map(
                    static function (array $row): array {
                        $title = (string)($row['activity_title'] ?? 'Platform activity');
                        $userName = trim((string)($row['user_name'] ?? ''));

                        return [
                            'title' => $userName !== ''
                                ? $title . ' by ' . $userName
                                : $title,
                            'time' => superAdminTimeAgo(
                                isset($row['created_at'])
                                    ? (string)$row['created_at']
                                    : null
                            ),
                        ];
                    },
                    $activityRows
                );
            }
        }
    } catch (Throwable $e) {
        error_log('super-admin/dashboard.php data: ' . $e->getMessage());
    }
}

require dirname(__DIR__) . '/includes/layout-start.php';

$canManageSchools = superAdminCan('schools', 'view');
$canCreateSchool  = superAdminCan('schools', 'create');
$canManagePlans   = superAdminCan('subscription_plans', 'view');
$canViewReports   = superAdminCan('super_admin_reports', 'view')
    || superAdminCan('reports', 'view');

$totalStatusSchools = max(1, array_sum($schoolStatus));
$activePercentage = round(
    (($schoolStatus['active'] ?? 0) / $totalStatusSchools) * 100,
    1
);
?>

<style>
.dashboard-page {
    display: grid;
    gap: 16px;
}

.dashboard-main-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.4fr) minmax(310px, .85fr) minmax(300px, .8fr);
    gap: 16px;
    align-items: stretch;
}

.dashboard-lower-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.7fr) minmax(300px, .65fr);
    gap: 16px;
    align-items: stretch;
}

.dashboard-card {
    overflow: hidden;
    background: var(--card-bg, #ffffff);
    border: 1px solid var(--border-soft, #e7ebf3);
    border-radius: 13px;
    box-shadow: 0 5px 18px rgba(15, 23, 42, .04);
}

.dashboard-card-header {
    min-height: 54px;
    padding: 13px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    border-bottom: 1px solid var(--border-soft, #e7ebf3);
}

.dashboard-card-header h2 {
    margin: 0;
    color: var(--text-main, #101a3b);
    font-size: 14px;
    font-weight: 700;
    letter-spacing: -.15px;
}

.dashboard-card-header a {
    font-size: 11px;
    font-weight: 700;
    text-decoration: none;
}

.dashboard-chart-summary {
    padding: 16px 16px 0;
}

.dashboard-chart-summary strong,
.dashboard-chart-summary small {
    display: block;
}

.dashboard-chart-summary strong {
    font-size: 22px;
    font-weight: 800;
    letter-spacing: -.5px;
}

.dashboard-chart-summary small {
    margin-top: 3px;
    color: #169454;
    font-size: 10px;
    font-weight: 700;
}

.dashboard-chart-box {
    height: 245px;
    padding: 5px 13px 15px;
}

.attendance-widget {
    padding: 18px;
    display: grid;
    grid-template-columns: minmax(170px, 1fr) minmax(120px, .7fr);
    align-items: center;
    gap: 14px;
}

.attendance-chart-wrap {
    position: relative;
    height: 210px;
}

.attendance-center {
    position: absolute;
    inset: 50% auto auto 50%;
    z-index: 2;
    text-align: center;
    transform: translate(-50%, -52%);
    pointer-events: none;
}

.attendance-center strong,
.attendance-center small {
    display: block;
}

.attendance-center strong {
    font-size: 22px;
    font-weight: 800;
}

.attendance-center small {
    margin-top: 2px;
    color: var(--text-muted, #64748b);
    font-size: 9px;
}

.attendance-legend {
    display: grid;
    gap: 14px;
}

.attendance-legend-row {
    display: grid;
    grid-template-columns: 9px 1fr;
    gap: 8px;
    align-items: start;
    font-size: 11px;
}

.attendance-legend-row span:first-child {
    width: 9px;
    height: 9px;
    margin-top: 4px;
    border-radius: 50%;
}

.attendance-legend-row strong,
.attendance-legend-row small {
    display: block;
}

.attendance-legend-row strong {
    font-size: 11px;
}

.attendance-legend-row small {
    margin-top: 3px;
    color: var(--text-muted, #64748b);
    font-size: 10px;
}

.event-list {
    padding: 3px 15px 8px;
}

.event-item {
    padding: 12px 0;
    display: grid;
    grid-template-columns: 40px minmax(0, 1fr);
    gap: 11px;
    align-items: center;
    border-bottom: 1px solid var(--border-soft, #e7ebf3);
}

.event-item:last-child {
    border-bottom: 0;
}

.event-icon {
    width: 40px;
    height: 40px;
    display: grid;
    place-items: center;
    border-radius: 50%;
}

.event-icon svg {
    width: 18px;
    height: 18px;
}

.event-purple {
    color: #6747dd;
    background: #eeeafd;
}

.event-blue {
    color: #3168d8;
    background: #eaf1ff;
}

.event-green {
    color: #189354;
    background: #e9f8ef;
}

.event-orange {
    color: #df7d0b;
    background: #fff2df;
}

.event-copy strong,
.event-copy small {
    display: block;
}

.event-copy strong {
    font-size: 11px;
}

.event-copy small {
    margin-top: 2px;
    color: var(--text-muted, #64748b);
    font-size: 9px;
    line-height: 1.4;
}

.dashboard-table-wrap {
    overflow-x: auto;
}

.dashboard-table {
    width: 100%;
    min-width: 850px;
    border-collapse: collapse;
}

.dashboard-table th,
.dashboard-table td {
    padding: 10px 12px;
    border-bottom: 1px solid var(--border-soft, #e7ebf3);
    font-size: 11px;
    text-align: left;
    vertical-align: middle;
}

.dashboard-table th {
    color: #475569;
    background: #f8fafc;
    font-weight: 700;
}

.dashboard-student {
    display: flex;
    align-items: center;
    gap: 9px;
    font-weight: 700;
}

.dashboard-avatar {
    width: 28px;
    height: 28px;
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    border-radius: 50%;
    color: #ffffff;
    background: linear-gradient(135deg,
            var(--brand-1, #6547e8),
            var(--brand-2, #315ed8));
    font-size: 10px;
    font-weight: 800;
}

.dashboard-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 8px;
    border-radius: 30px;
    font-size: 9px;
    font-weight: 800;
    white-space: nowrap;
}

.dashboard-badge-success {
    color: #168448;
    background: #e8f8ef;
}

.dashboard-badge-info {
    color: #3164ce;
    background: #e8f1ff;
}

.dashboard-badge-warning {
    color: #d97706;
    background: #fff2dc;
}

.activity-timeline {
    padding: 10px 16px 14px;
}

.activity-timeline-item {
    position: relative;
    padding: 8px 0 10px 23px;
}

.activity-timeline-item::before {
    position: absolute;
    top: 14px;
    left: 4px;
    width: 7px;
    height: 7px;
    content: "";
    border-radius: 50%;
    background: linear-gradient(135deg,
            var(--brand-1, #6547e8),
            var(--brand-2, #315ed8));
}

.activity-timeline-item::after {
    position: absolute;
    top: 21px;
    bottom: -3px;
    left: 7px;
    width: 1px;
    content: "";
    background: #dfe4ef;
}

.activity-timeline-item:last-child::after {
    display: none;
}

.activity-timeline-item strong,
.activity-timeline-item small {
    display: block;
}

.activity-timeline-item strong {
    font-size: 11px;
    font-weight: 650;
}

.activity-timeline-item small {
    margin-top: 3px;
    color: var(--text-muted, #64748b);
    font-size: 9px;
}

.dashboard-extra-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.quick-summary-list {
    padding: 6px 16px 12px;
}

.quick-summary-row {
    padding: 11px 0;
    display: flex;
    align-items: center;
    gap: 10px;
    border-bottom: 1px solid var(--border-soft, #e7ebf3);
}

.quick-summary-row:last-child {
    border-bottom: 0;
}

.quick-summary-row svg {
    width: 18px;
    height: 18px;
    color: var(--brand-1, #6547e8);
}

.quick-summary-row div {
    min-width: 0;
}

.quick-summary-row strong,
.quick-summary-row small {
    display: block;
}

.quick-summary-row strong {
    font-size: 11px;
}

.quick-summary-row small {
    margin-top: 2px;
    color: var(--text-muted, #64748b);
    font-size: 9px;
}

.quick-summary-row>b {
    margin-left: auto;
    font-size: 12px;
}

.exam-list {
    padding: 4px 16px 10px;
}

.exam-list .list-clean li {
    padding: 14px 0;
}

.exam-list .list-clean svg {
    width: 19px;
    height: 19px;
}

@media (max-width: 1399.98px) {
    .dashboard-main-grid {
        grid-template-columns: 1fr 1fr;
    }

    .dashboard-main-grid>article:first-child {
        grid-column: 1 / -1;
    }

    .dashboard-lower-grid {
        grid-template-columns: 1.35fr .65fr;
    }
}

@media (max-width: 991.98px) {

    .dashboard-main-grid,
    .dashboard-lower-grid,
    .dashboard-extra-grid {
        grid-template-columns: 1fr;
    }

    .dashboard-main-grid>article:first-child {
        grid-column: auto;
    }
}

@media (max-width: 575.98px) {
    .attendance-widget {
        grid-template-columns: 1fr;
    }

    .dashboard-chart-box {
        height: 225px;
    }
}
</style>


<div class="dashboard-page">
    <div class="page-heading">
        <div>
            <h1 class="page-title">Super Admin Dashboard</h1>
            <p class="page-subtitle">
                Welcome back. Here is today’s complete platform overview.
            </p>
        </div>

        <div class="page-actions">
            <?php if ($canManageSchools): ?>
                <a href="<?= e($superAdminBaseUrl . 'schools.php') ?>" class="btn-ui">
                    <i data-lucide="school"></i>
                    Manage Schools
                </a>
            <?php endif; ?>

            <?php if ($canCreateSchool): ?>
                <a href="<?= e($superAdminBaseUrl . 'schools.php?action=create') ?>"
                   class="btn-ui btn-primary-ui">
                    <i data-lucide="building-2"></i>
                    Add School
                </a>
            <?php endif; ?>
        </div>
    </div>

    <section class="metric-grid">
        <article class="metric-card metric-purple">
            <div class="metric-icon">
                <i data-lucide="school"></i>
            </div>
            <div>
                <small>Total Schools</small>
                <div class="metric-value">
                    <?= number_format($dashboardStats['schools']) ?>
                </div>
                <div class="metric-trend">
                    <?= number_format($schoolStatus['active'] ?? 0) ?> active schools
                </div>
            </div>
        </article>

        <article class="metric-card metric-blue">
            <div class="metric-icon">
                <i data-lucide="badge-check"></i>
            </div>
            <div>
                <small>Active Subscriptions</small>
                <div class="metric-value">
                    <?= number_format($dashboardStats['active_subscriptions']) ?>
                </div>
                <div class="metric-trend">
                    <?= number_format($schoolStatus['trial'] ?? 0) ?> schools on trial
                </div>
            </div>
        </article>

        <article class="metric-card metric-green">
            <div class="metric-icon">
                <i data-lucide="users-round"></i>
            </div>
            <div>
                <small>Platform Users</small>
                <div class="metric-value">
                    <?= number_format($dashboardStats['users']) ?>
                </div>
                <div class="metric-trend">
                    Across all registered schools
                </div>
            </div>
        </article>

        <article class="metric-card metric-orange">
            <div class="metric-icon">
                <i data-lucide="indian-rupee"></i>
            </div>
            <div>
                <small>Monthly SaaS Revenue</small>
                <div class="metric-value">
                    <?= e(superAdminCurrency($dashboardStats['monthly_revenue'])) ?>
                </div>
                <div class="metric-trend">
                    Current recurring revenue
                </div>
            </div>
        </article>
    </section>

    <section class="dashboard-main-grid">
        <article class="dashboard-card">
            <div class="dashboard-card-header">
                <h2>Monthly Subscription Revenue</h2>
                <span class="status blue">Last 12 Months</span>
            </div>

            <div class="dashboard-chart-summary">
                <strong>
                    <?= e(superAdminCurrency($dashboardStats['monthly_revenue'])) ?>
                </strong>
                <small>Current monthly recurring revenue</small>
            </div>

            <div class="dashboard-chart-box">
                <canvas id="superAdminRevenueChart"></canvas>
            </div>
        </article>

        <article class="dashboard-card">
            <div class="dashboard-card-header">
                <h2>School Account Status</h2>
                <span class="status green">
                    <?= e((string)$activePercentage) ?>%
                </span>
            </div>

            <div class="attendance-widget">
                <div class="attendance-chart-wrap">
                    <div class="attendance-center">
                        <strong><?= e((string)$activePercentage) ?>%</strong>
                        <small>Active Schools</small>
                    </div>

                    <canvas id="superAdminSchoolStatusChart"></canvas>
                </div>

                <div class="attendance-legend">
                    <div class="attendance-legend-row">
                        <span style="background:#2caf6c"></span>
                        <div>
                            <strong>Active</strong>
                            <small><?= number_format($schoolStatus['active'] ?? 0) ?> schools</small>
                        </div>
                    </div>

                    <div class="attendance-legend-row">
                        <span style="background:#ffad1f"></span>
                        <div>
                            <strong>Trial</strong>
                            <small><?= number_format($schoolStatus['trial'] ?? 0) ?> schools</small>
                        </div>
                    </div>

                    <div class="attendance-legend-row">
                        <span style="background:#f04468"></span>
                        <div>
                            <strong>Restricted</strong>
                            <small>
                                <?= number_format(
                                    ($schoolStatus['suspended'] ?? 0)
                                    + ($schoolStatus['cancelled'] ?? 0)
                                ) ?> schools
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </article>

        <article class="dashboard-card">
            <div class="dashboard-card-header">
                <h2>Upcoming Renewals</h2>

                <?php if ($canManagePlans): ?>
                    <a href="<?= e($superAdminBaseUrl . 'subscriptions.php') ?>">
                        View Subscriptions
                    </a>
                <?php endif; ?>
            </div>

            <div class="event-list">
                <?php foreach ($upcomingRenewals as $renewal): ?>
                    <div class="event-item">
                        <div class="event-icon event-purple">
                            <i data-lucide="<?= e($renewal['icon']) ?>"></i>
                        </div>

                        <div class="event-copy">
                            <strong><?= e($renewal['title']) ?></strong>
                            <small><?= e($renewal['class']) ?></small>
                            <small><?= e($renewal['days']) ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </article>
    </section>

    <section class="dashboard-lower-grid">
        <article class="dashboard-card">
            <div class="dashboard-card-header">
                <h2>Recently Registered Schools</h2>

                <?php if ($canManageSchools): ?>
                    <a href="<?= e($superAdminBaseUrl . 'schools.php') ?>">
                        View All
                    </a>
                <?php endif; ?>
            </div>

            <div class="dashboard-table-wrap">
                <table class="dashboard-table">
                    <thead>
                        <tr>
                            <th>School Code</th>
                            <th>School Name</th>
                            <th>Subscription Plan</th>
                            <th>Users</th>
                            <th>Registered</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($recentSchools as $school): ?>
                            <tr>
                                <td><?= e($school['tenant_code']) ?></td>

                                <td>
                                    <div class="dashboard-student">
                                        <span class="dashboard-avatar">
                                            <?= e(strtoupper(substr(
                                                (string)$school['school_name'],
                                                0,
                                                1
                                            ))) ?>
                                        </span>

                                        <?= e($school['school_name']) ?>
                                    </div>
                                </td>

                                <td><?= e($school['plan_name']) ?></td>
                                <td><?= number_format((int)$school['user_count']) ?></td>
                                <td><?= e($school['registered']) ?></td>

                                <td>
                                    <span class="dashboard-badge <?= e(
                                        superAdminSchoolBadge(
                                            (string)$school['status']
                                        )
                                    ) ?>">
                                        <?= e($school['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </article>

        <article class="dashboard-card">
            <div class="dashboard-card-header">
                <h2>Recent Platform Activity</h2>

                <a href="<?= e($superAdminBaseUrl . 'activity-logs.php') ?>">
                    View All
                </a>
            </div>

            <div class="activity-timeline">
                <?php foreach ($recentActivities as $activity): ?>
                    <div class="activity-timeline-item">
                        <strong><?= e($activity['title']) ?></strong>
                        <small><?= e($activity['time']) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        </article>
    </section>

    <section class="dashboard-extra-grid">
        <article class="dashboard-card">
            <div class="dashboard-card-header">
                <h2>Subscription Plans</h2>

                <?php if ($canManagePlans): ?>
                    <a href="<?= e($superAdminBaseUrl . 'plans.php') ?>">
                        Manage Plans
                    </a>
                <?php endif; ?>
            </div>

            <div class="exam-list">
                <ul class="list-clean">
                    <?php foreach ($planSummary as $plan): ?>
                        <li>
                            <i data-lucide="badge-indian-rupee"></i>

                            <div>
                                <strong><?= e($plan['plan_name']) ?></strong>
                                <div class="small text-muted">
                                    <?= e(superAdminCurrency($plan['price'])) ?> per month
                                </div>
                            </div>

                            <span class="status <?= e($plan['badge']) ?> ms-auto">
                                <?= number_format((int)$plan['schools']) ?> Schools
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </article>

        <article class="dashboard-card">
            <div class="dashboard-card-header">
                <h2>Quick Platform Summary</h2>

                <?php if ($canViewReports): ?>
                    <a href="<?= e($superAdminBaseUrl . 'reports.php') ?>">
                        Open Reports
                    </a>
                <?php endif; ?>
            </div>

            <div class="quick-summary-list">
                <div class="quick-summary-row">
                    <i data-lucide="git-branch"></i>
                    <div>
                        <strong>School Branches</strong>
                        <small>Branches registered across all schools</small>
                    </div>
                    <b><?= number_format($dashboardStats['branches']) ?></b>
                </div>

                <div class="quick-summary-row">
                    <i data-lucide="boxes"></i>
                    <div>
                        <strong>Available Modules</strong>
                        <small>Active modules in the ERP platform</small>
                    </div>
                    <b><?= number_format($dashboardStats['modules']) ?></b>
                </div>

                <div class="quick-summary-row">
                    <i data-lucide="credit-card"></i>
                    <div>
                        <strong>Subscription Plans</strong>
                        <small>Active SaaS plans available to schools</small>
                    </div>
                    <b><?= number_format($dashboardStats['plans']) ?></b>
                </div>

                <div class="quick-summary-row">
                    <i data-lucide="calendar-clock"></i>
                    <div>
                        <strong>Renewals Due</strong>
                        <small>Subscriptions ending within 30 days</small>
                    </div>
                    <b><?= number_format($dashboardStats['renewals_due']) ?></b>
                </div>
            </div>
        </article>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof window.Chart === 'undefined') {
        return;
    }

    const revenueCanvas =
        document.getElementById('superAdminRevenueChart');

    if (revenueCanvas) {
        new Chart(revenueCanvas, {
            type: 'bar',
            data: {
                labels: <?= json_encode(
                    array_values($revenueLabels),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ) ?>,
                datasets: [{
                    label: 'Revenue in Lakhs',
                    data: <?= json_encode(
                        array_values($revenueValues),
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    ) ?>,
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
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: 9
                            }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#edf0f6'
                        },
                        ticks: {
                            font: {
                                size: 9
                            },
                            callback: function (value) {
                                return value + 'L';
                            }
                        }
                    }
                }
            }
        });
    }

    const statusCanvas =
        document.getElementById('superAdminSchoolStatusChart');

    if (statusCanvas) {
        new Chart(statusCanvas, {
            type: 'doughnut',
            data: {
                labels: ['Active', 'Trial', 'Restricted'],
                datasets: [{
                    data: [
                        <?= (int)($schoolStatus['active'] ?? 0) ?>,
                        <?= (int)($schoolStatus['trial'] ?? 0) ?>,
                        <?= (int)(
                            ($schoolStatus['suspended'] ?? 0)
                            + ($schoolStatus['cancelled'] ?? 0)
                        ) ?>
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
                    legend: {
                        display: false
                    }
                }
            }
        });
    }
});
</script>

<?php require dirname(__DIR__) . '/includes/layout-end.php'; ?>

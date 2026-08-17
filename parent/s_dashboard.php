<?php
declare(strict_types=1);

/*
 * Parent Dashboard
 * Location: parent/s_dashboard.php
 * API: parent/api/dashboard.php
 * Build: 2026-08-13-parent-dashboard-overview-removed-v24
 */

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/sidebar-manager.php';
require_once dirname(__DIR__) . '/includes/permission-chain.php';

$user = function_exists('current_user') ? current_user() : [];
$user = is_array($user) ? $user : [];

if (
    empty($user)
    && empty($_SESSION['user_id'])
    && empty($_SESSION['guardian_id'])
    && empty($_SESSION['parent_guardian_id'])
) {
    foreach ([
        dirname(__DIR__) . '/login.php' => '../login.php',
        dirname(__DIR__) . '/auth/login.php' => '../auth/login.php',
    ] as $path => $url) {
        if (is_file($path)) {
            header('Location: ' . $url);
            exit;
        }
    }
}


$tenantId=(int)($user['tenant_id']??$_SESSION['tenant_id']??$_SESSION['school_id']??0);
$roleId=(int)($user['role_id']??$_SESSION['role_id']??0);
$role=isset($pdo)&&$pdo instanceof PDO?pc_role($pdo,$tenantId,$roleId):[];
if(pc_role_key((string)($role['role_key']??''))!=='parent'){
    http_response_code(403);
    exit('Parent Dashboard is available only to Parent accounts.');
}

$parentSidebarItems=isset($pdo)&&$pdo instanceof PDO
    ?school_sidebar_get_items($pdo,$roleId,$tenantId):[];
$parentAllowedMenuKeys=[];
foreach($parentSidebarItems as $menu){
    $key=(string)($menu['menu_key']??'');
    if($key!=='')$parentAllowedMenuKeys[$key]=true;
}
if($parentSidebarItems===[]){
    http_response_code(403);
    exit('Parent Portal is disabled for this school.');
}
$parentCan=static fn(string $key):bool=>isset($parentAllowedMenuKeys[$key]);

/*
|--------------------------------------------------------------------------
| Parent sidebar route resolver
|--------------------------------------------------------------------------
|
| IMPORTANT:
| The database/Super Admin Sidebar Master is the source of truth for routes.
| Previous UI code incorrectly discarded every non-hash route and forced:
|
|     s_dashboard.php
|
| That made routes such as parent/my_children.php reopen Dashboard.
|
| Rules:
| 1. Real Parent page routes are opened exactly as configured.
| 2. Dashboard section routes stay as same-page anchors for smooth navigation.
| 3. The old #announcements anchor is mapped to this dashboard's #updates ID.
| 4. Empty routes get a safe menu-key fallback.
| 5. Unsafe javascript/data/vbscript routes are rejected.
*/
$parentSidebarHref = static function (array $menu): string {
    $menuKey = trim((string)($menu['menu_key'] ?? ''));
    $route = trim((string)($menu['route'] ?? ''));

    $dashboardFallbacks = [
        'parent_dashboard'   => 's_dashboard.php',
        'parent_my_children' => '#profile',
        'parent_attendance'  => 'attendance.php',
        'parent_fees'        => '#fees',
        'parent_fee_payment' => '#fees',
        'parent_results'     => '#results',
        'parent_homework'    => '#homework',
        'parent_notices'     => '#updates',
        'parent_transport'   => '#transport',
        'parent_profile'     => '#profile',
    ];

    if ($route === '' || $route === '#') {
        return $dashboardFallbacks[$menuKey]
            ?? 's_dashboard.php';
    }

    /*
     * Sidebar routes are admin-controlled, but still never emit executable
     * browser URI schemes.
     */
    if (preg_match(
        '/^(?:javascript|data|vbscript)\s*:/i',
        $route
    )) {
        return '#';
    }

    $routePath = (string)(
        parse_url($route, PHP_URL_PATH) ?? ''
    );
    $fragment = (string)(
        parse_url($route, PHP_URL_FRAGMENT) ?? ''
    );

    $normalizedPath = ltrim(
        str_replace('\\', '/', $routePath),
        '/'
    );

    /*
     * When app_url()/BASE_URL produced an absolute application path such as
     * /git/schoolerp/parent/s_dashboard.php, strip that application prefix
     * only for the "is this current dashboard?" comparison.
     */
    if (defined('BASE_URL')) {
        $basePath = trim(
            (string)(
                parse_url((string)BASE_URL, PHP_URL_PATH)
                ?? ''
            ),
            '/'
        );

        if (
            $basePath !== ''
            && str_starts_with(
                $normalizedPath,
                $basePath . '/'
            )
        ) {
            $normalizedPath = substr(
                $normalizedPath,
                strlen($basePath) + 1
            );
        }
    }

    $normalizedPath = ltrim(
        preg_replace(
            '#^\./+#',
            '',
            $normalizedPath
        ) ?? $normalizedPath,
        '/'
    );

    /*
     * The following routes point to THIS dashboard. Keep them as anchors
     * rather than causing a full page reload.
     */
    $isCurrentDashboard = in_array(
        strtolower($normalizedPath),
        [
            '',
            's_dashboard.php',
            'parent/s_dashboard.php',
        ],
        true
    );

    if ($isCurrentDashboard) {
        if ($fragment !== '') {
            $fragmentAliases = [
                'announcements' => 'updates',
                'notices' => 'updates',
                'my-children' => 'profile',
                'my_children' => 'profile',
                'exams' => 'results',
                'exam-results' => 'results',
                'fee-payment' => 'fees',
                'fee_payment' => 'fees',
            ];

            $safeFragment = strtolower(trim($fragment));
            $safeFragment =
                $fragmentAliases[$safeFragment]
                ?? $safeFragment;

            return '#'
                . preg_replace(
                    '/[^a-zA-Z0-9_-]/',
                    '',
                    $safeFragment
                );
        }

        return 's_dashboard.php';
    }

    /*
     * External absolute HTTP(S) links, when intentionally configured by
     * Super Admin, remain absolute.
     */
    if (preg_match('#^https?://#i', $route)) {
        return $route;
    }

    /*
     * An application-root absolute route is already complete.
     */
    if (str_starts_with($route, '/')) {
        return $route;
    }

    /*
     * Explicit ../ relative navigation is intentionally preserved.
     */
    if (str_starts_with($route, '../')) {
        return $route;
    }

    /*
     * A bare filename entered in Parent Sidebar Master belongs to /parent/.
     * Example: my_children.php -> /git/schoolerp/parent/my_children.php
     */
    $routeForApp = ltrim($route, './');
    $pathForApp = (string)(
        parse_url($routeForApp, PHP_URL_PATH)
        ?? ''
    );

    if (
        $pathForApp !== ''
        && !str_contains(
            str_replace('\\', '/', $pathForApp),
            '/'
        )
    ) {
        $routeForApp = 'parent/' . $routeForApp;
    }

    if (function_exists('app_url')) {
        return app_url($routeForApp);
    }

    /*
     * Bootstrap normally provides app_url(). This fallback keeps the file
     * usable if this page is isolated during development.
     */
    return '../' . ltrim($routeForApp, '/');
};

$parentDashboardItemId=0;
foreach($parentSidebarItems as $menu){
    if(($menu['menu_key']??'')==='parent_dashboard'){$parentDashboardItemId=(int)$menu['id'];break;}
}
$canPrintDashboard=$parentDashboardItemId>0&&isset($pdo)&&$pdo instanceof PDO
    &&pc_effective_role_action($pdo,$tenantId,$roleId,$parentDashboardItemId,'print');

$pageTitle = 'Parent Dashboard';
$pageKey = 'parent_dashboard';

/*
 * Use the SAME sidebar renderer as the School Admin layout.
 * school/sidebar.php is already role-aware and automatically loads the
 * Parent role's permitted sidebar items when the logged-in role is Parent.
 */
$sidebarFile = dirname(__DIR__) . '/school/sidebar.php';

require dirname(__DIR__) . '/includes/layout-start.php';
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

/* =========================================================
   PARENT DASHBOARD CONTENT
   The shell/navbar/sidebar are provided by layout-start.php.
   These additions follow the same Dashboard card sizing,
   typography, spacing, borders and CSS variables used above.
   ========================================================= */

.parent-dashboard .parent-child-picker{
    min-width:220px;
    height:38px;
    display:flex;
    align-items:center;
    gap:8px;
    padding:0 10px;
    border:1px solid var(--border-soft,#e7ebf3);
    border-radius:9px;
    background:var(--card-bg,#fff);
}

.parent-dashboard .parent-child-picker svg{
    width:16px;
    height:16px;
    color:var(--text-muted,#64748b);
}

.parent-dashboard .parent-child-picker select{
    width:100%;
    min-width:0;
    border:0;
    outline:0;
    background:transparent;
    color:var(--text-main,#101a3b);
    font-size:10px;
    font-weight:700;
}

.parent-dashboard .student-overview-card{
    padding:16px 18px;
}

.parent-dashboard .student-overview{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:16px;
}

.parent-dashboard .student-main{
    min-width:0;
    display:flex;
    align-items:center;
    gap:13px;
}

.parent-dashboard .student-avatar{
    width:54px;
    height:54px;
    flex:0 0 54px;
    display:grid;
    place-items:center;
    overflow:hidden;
    color:#fff;
    background:linear-gradient(135deg,#7548ee,#5033d5);
    border-radius:50%;
    font-size:17px;
    font-weight:800;
}

.parent-dashboard .student-avatar img{
    width:100%;
    height:100%;
    object-fit:cover;
}

.parent-dashboard .student-copy{
    min-width:0;
}

.parent-dashboard .student-copy h2{
    margin:0;
    color:var(--text-main,#101a3b);
    font-size:15px;
    font-weight:700;
}

.parent-dashboard .student-copy p{
    margin:4px 0 0;
    color:var(--text-muted,#64748b);
    font-size:10px;
}

.parent-dashboard .student-pills{
    display:flex;
    align-items:center;
    flex-wrap:wrap;
    gap:6px;
    margin-top:8px;
}

.parent-dashboard .pill{
    display:inline-flex;
    align-items:center;
    min-height:22px;
    padding:3px 7px;
    color:#4f46e5;
    background:#eef2ff;
    border-radius:999px;
    font-size:8px;
    font-weight:700;
}

.parent-dashboard .student-shortcuts{
    display:flex;
    align-items:center;
    justify-content:flex-end;
    flex-wrap:wrap;
    gap:7px;
}

.parent-dashboard .student-shortcuts a{
    padding:6px 9px;
    color:var(--text-main,#101a3b);
    background:#fff;
    border:1px solid var(--border-soft,#e7ebf3);
    border-radius:8px;
    font-size:9px;
    font-weight:700;
    text-decoration:none;
}

.parent-dashboard .student-shortcuts a:hover{
    color:#4f46e5;
    border-color:#cfd4ff;
    background:#f8f9ff;
}

.parent-dashboard .parent-two-grid{
    display:grid;
    grid-template-columns:minmax(0,1fr) minmax(0,1fr);
    gap:16px;
    align-items:start;
}

.parent-dashboard .parent-full-card{
    width:100%;
}

.parent-dashboard .parent-card-body{
    padding:16px;
}

.parent-dashboard .parent-profile-grid,
.parent-dashboard .transport-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:10px;
}

.parent-dashboard .info{
    min-width:0;
    padding:11px 12px;
    background:#f8fafc;
    border:1px solid var(--border-soft,#e7ebf3);
    border-radius:10px;
}

.parent-dashboard .info span,
.parent-dashboard .info strong{
    display:block;
}

.parent-dashboard .info span{
    margin-bottom:4px;
    color:var(--text-muted,#64748b);
    font-size:8px;
    font-weight:600;
    text-transform:uppercase;
    letter-spacing:.03em;
}

.parent-dashboard .info strong{
    overflow:hidden;
    color:var(--text-main,#101a3b);
    font-size:10px;
    font-weight:700;
    text-overflow:ellipsis;
    white-space:nowrap;
}

.parent-dashboard .parent-mini-grid{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:10px;
    padding:16px;
}

.parent-dashboard .parent-mini{
    padding:13px 10px;
    text-align:center;
    background:#f8fafc;
    border:1px solid var(--border-soft,#e7ebf3);
    border-radius:10px;
}

.parent-dashboard .parent-mini strong,
.parent-dashboard .parent-mini span{
    display:block;
}

.parent-dashboard .parent-mini strong{
    color:var(--text-main,#101a3b);
    font-size:18px;
    font-weight:800;
}

.parent-dashboard .parent-mini span{
    margin-top:3px;
    color:var(--text-muted,#64748b);
    font-size:9px;
    font-weight:600;
}

.parent-dashboard .parent-table-wrap{
    overflow:auto;
}

.parent-dashboard .parent-table{
    width:100%;
    min-width:620px;
    border-collapse:collapse;
}

.parent-dashboard .parent-table th,
.parent-dashboard .parent-table td{
    padding:10px 13px;
    text-align:left;
    vertical-align:middle;
    border-bottom:1px solid var(--border-soft,#e7ebf3);
}

.parent-dashboard .parent-table th{
    color:var(--text-muted,#64748b);
    background:#f8fafc;
    font-size:8px;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:.035em;
}

.parent-dashboard .parent-table td{
    color:var(--text-main,#101a3b);
    font-size:9.5px;
    font-weight:500;
}

.parent-dashboard .parent-table tbody tr:last-child td{
    border-bottom:0;
}

.parent-dashboard .badge{
    display:inline-flex;
    align-items:center;
    padding:4px 7px;
    border-radius:999px;
    font-size:8px;
    font-weight:700;
    text-transform:capitalize;
}

.parent-dashboard .badge.good{
    color:#158458;
    background:#e9f8f1;
}

.parent-dashboard .badge.warn{
    color:#b4770b;
    background:#fff6dd;
}

.parent-dashboard .badge.bad{
    color:#c83a55;
    background:#fff0f3;
}

.parent-dashboard .badge.neutral{
    color:#65728b;
    background:#f0f3f8;
}

.parent-dashboard .parent-list{
    display:grid;
    padding:5px 16px 10px;
}

.parent-dashboard .item{
    padding:11px 0;
    border-bottom:1px solid var(--border-soft,#e7ebf3);
}

.parent-dashboard .item:last-child{
    border-bottom:0;
}

.parent-dashboard .item-top{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:10px;
}

.parent-dashboard .item strong{
    color:var(--text-main,#101a3b);
    font-size:10px;
    font-weight:700;
}

.parent-dashboard .item small{
    display:block;
    margin-top:3px;
    color:var(--text-muted,#64748b);
    font-size:8.5px;
}

.parent-dashboard .item p{
    margin:6px 0 0;
    color:var(--text-muted,#64748b);
    font-size:9px;
    line-height:1.5;
}

.parent-dashboard .amount{
    font-weight:800;
    white-space:nowrap;
}

.parent-dashboard .fee-lines{
    display:grid;
    gap:10px;
    padding:16px;
}

.parent-dashboard .fee-line{
    display:grid;
    grid-template-columns:110px minmax(0,1fr) auto;
    align-items:center;
    gap:10px;
}

.parent-dashboard .fee-line-name{
    font-size:9px;
    font-weight:700;
}

.parent-dashboard .track{
    height:7px;
    overflow:hidden;
    background:#edf1f7;
    border-radius:999px;
}

.parent-dashboard .track span{
    display:block;
    height:100%;
    background:linear-gradient(90deg,#7548ee,#4f46e5);
    border-radius:999px;
}

.parent-dashboard .empty{
    padding:24px 12px;
    color:var(--text-muted,#64748b);
    font-size:9px;
    text-align:center;
}

.parent-dashboard .module-note{
    margin:14px 16px;
    padding:11px 12px;
    color:#8a6412;
    background:#fff8e8;
    border:1px solid #f3dfaa;
    border-radius:9px;
    font-size:9px;
    line-height:1.5;
}

.parent-dashboard .parent-error{
    display:none;
    padding:11px 13px;
    color:#a4394d;
    background:#fff0f3;
    border:1px solid #ffd7df;
    border-radius:10px;
    font-size:9px;
}

.parent-dashboard .parent-error.show{
    display:block;
}

.parent-dashboard-loading{
    position:fixed;
    inset:0;
    z-index:5000;
    display:grid;
    place-items:center;
    background:rgba(245,247,251,.84);
    backdrop-filter:blur(2px);
}

.parent-dashboard-loading>div{
    padding:13px 17px;
    color:var(--text-main,#101a3b);
    background:#fff;
    border:1px solid var(--border-soft,#e7ebf3);
    border-radius:10px;
    box-shadow:0 10px 28px rgba(15,23,42,.08);
    font-size:10px;
    font-weight:700;
}

@media(max-width:991.98px){
    .parent-dashboard .student-overview{
        align-items:flex-start;
        flex-direction:column;
    }

    .parent-dashboard .student-shortcuts{
        justify-content:flex-start;
    }

    .parent-dashboard .parent-two-grid{
        grid-template-columns:1fr;
    }
}

@media(max-width:767.98px){
    .parent-dashboard .page-actions{
        width:100%;
    }

    .parent-dashboard .parent-child-picker{
        width:100%;
        min-width:0;
    }

    .parent-dashboard .parent-mini-grid{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }

    .parent-dashboard .parent-profile-grid,
    .parent-dashboard .transport-grid{
        grid-template-columns:1fr;
    }
}

@media(max-width:575.98px){
    .parent-dashboard .student-main{
        align-items:flex-start;
    }

    .parent-dashboard .student-avatar{
        width:48px;
        height:48px;
        flex-basis:48px;
    }

    .parent-dashboard .fee-line{
        grid-template-columns:85px minmax(0,1fr);
    }

    .parent-dashboard .fee-line .amount{
        grid-column:2;
    }
}

</style>

<div id="loading" class="parent-dashboard-loading">
    <div>Loading Parent Dashboard...</div>
</div>

<div class="dashboard-page parent-dashboard">
    <div class="page-heading">
        <div>
            <h1 class="page-title">Parent Dashboard</h1>
            <p class="page-subtitle">
                Welcome back. Here is your child’s school overview.
            </p>
        </div>

        <div class="page-actions">
            <label class="parent-child-picker" for="childSelect">
                <i data-lucide="users-round"></i>
                <select id="childSelect">
                    <option>Loading child...</option>
                </select>
            </label>

            <?php if ($canPrintDashboard): ?>
                <button
                    class="btn-ui btn-primary-ui"
                    type="button"
                    onclick="window.print()"
                >
                    <i data-lucide="printer"></i>
                    Print Dashboard
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div id="errorBox" class="parent-error"></div>

    <section class="metric-grid">
        <article
            class="metric-card metric-purple"
            style="<?= $parentCan('parent_attendance') ? '' : 'display:none' ?>"
        >
            <div class="metric-icon">
                <i data-lucide="calendar-check"></i>
            </div>
            <div>
                <small>Attendance</small>
                <div id="attendanceKpi" class="metric-value">0.0%</div>
                <div id="attendanceNote" class="metric-trend">0 working days</div>
            </div>
        </article>

        <article
            class="metric-card metric-blue"
            style="<?= ($parentCan('parent_fees') || $parentCan('parent_fee_payment')) ? '' : 'display:none' ?>"
        >
            <div class="metric-icon">
                <i data-lucide="wallet-cards"></i>
            </div>
            <div>
                <small>Total Fee</small>
                <div id="totalFeeKpi" class="metric-value">₹0</div>
                <div id="feeStatusNote" class="metric-trend">Not assigned</div>
            </div>
        </article>

        <article
            class="metric-card metric-green"
            style="<?= ($parentCan('parent_fees') || $parentCan('parent_fee_payment')) ? '' : 'display:none' ?>"
        >
            <div class="metric-icon">
                <i data-lucide="indian-rupee"></i>
            </div>
            <div>
                <small>Fee Balance</small>
                <div id="feeDueKpi" class="metric-value">₹0</div>
                <div id="feePaidNote" class="metric-trend">Paid ₹0</div>
            </div>
        </article>

        <article
            class="metric-card metric-orange"
            style="<?= $parentCan('parent_results') ? '' : 'display:none' ?>"
        >
            <div class="metric-icon">
                <i data-lucide="book-open-check"></i>
            </div>
            <div>
                <small>Upcoming Exams</small>
                <div id="examKpi" class="metric-value">0</div>
                <div class="metric-trend">Scheduled papers</div>
            </div>
        </article>
    </section>

    <!-- Existing Parent JS support values -->
    <span id="homeworkKpi" hidden>0</span>
    <span id="leaveKpi" hidden>0</span>

    <section class="dashboard-main-grid">
        <article
            id="attendance"
            class="dashboard-card"
            style="<?= $parentCan('parent_attendance') ? '' : 'display:none' ?>"
        >
            <div class="dashboard-card-header">
                <h2>Student Attendance</h2>
                <span id="attendanceYear" class="status green">Academic Year</span>
            </div>

            <div class="parent-mini-grid">
                <div class="parent-mini">
                    <strong id="presentDays">0</strong>
                    <span>Present</span>
                </div>
                <div class="parent-mini">
                    <strong id="absentDays">0</strong>
                    <span>Absent</span>
                </div>
                <div class="parent-mini">
                    <strong id="leaveDays">0</strong>
                    <span>Leave</span>
                </div>
                <div class="parent-mini">
                    <strong id="lateDays">0</strong>
                    <span>Late</span>
                </div>
            </div>

            <div class="parent-table-wrap">
                <table class="parent-table">
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Check In</th>
                        <th>Check Out</th>
                        <th>Remarks</th>
                    </tr>
                    </thead>
                    <tbody id="attendanceRows"></tbody>
                </table>
            </div>
        </article>

        <article
            id="profile"
            class="dashboard-card"
            style="<?= ($parentCan('parent_my_children') || $parentCan('parent_profile')) ? '' : 'display:none' ?>"
        >
            <div class="dashboard-card-header">
                <h2>Student Profile</h2>
                <span id="profileYear" class="status blue">Current Year</span>
            </div>

            <div
                id="profileGrid"
                class="parent-card-body parent-profile-grid"
            ></div>
        </article>

        <article
            class="dashboard-card"
            style="<?= $parentCan('parent_results') ? '' : 'display:none' ?>"
        >
            <div class="dashboard-card-header">
                <h2>Upcoming Exams</h2>
                <span class="dashboard-card-subtitle">Exam Schedule</span>
            </div>

            <div id="examList" class="parent-list"></div>
        </article>
    </section>

    <section class="dashboard-lower-grid">
        <article
            id="fees"
            class="dashboard-card"
            style="<?= ($parentCan('parent_fees') || $parentCan('parent_fee_payment')) ? '' : 'display:none' ?>"
        >
            <div class="dashboard-card-header">
                <h2>Fees & Payment Status</h2>
                <span id="feeStructureName" class="dashboard-card-subtitle">Fee Structure</span>
            </div>

            <div id="feeBreakdown" class="fee-lines"></div>

            <div class="parent-table-wrap">
                <table class="parent-table">
                    <thead>
                    <tr>
                        <th>Fee</th>
                        <th>Period</th>
                        <th>Due Date</th>
                        <th>Balance</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody id="feeRows"></tbody>
                </table>
            </div>
        </article>

        <article
            id="updates"
            class="dashboard-card"
            style="<?= $parentCan('parent_notices') ? '' : 'display:none' ?>"
        >
            <div class="dashboard-card-header">
                <h2>Notices & Updates</h2>
                <span class="dashboard-card-subtitle">School Communication</span>
            </div>

            <div id="updatesList" class="parent-list"></div>
        </article>
    </section>

    <section class="dashboard-lower-grid">
        <article
            id="results"
            class="dashboard-card"
            style="<?= $parentCan('parent_results') ? '' : 'display:none' ?>"
        >
            <div class="dashboard-card-header">
                <h2>Exam Results</h2>
                <span class="dashboard-card-subtitle">Published Results</span>
            </div>

            <div class="parent-table-wrap">
                <table class="parent-table">
                    <thead>
                    <tr>
                        <th>Exam</th>
                        <th>Marks</th>
                        <th>Percentage</th>
                        <th>Grade</th>
                        <th>Rank</th>
                        <th>Result</th>
                    </tr>
                    </thead>
                    <tbody id="resultRows"></tbody>
                </table>
            </div>
        </article>

        <article
            id="homework"
            class="dashboard-card"
            style="<?= $parentCan('parent_homework') ? '' : 'display:none' ?>"
        >
            <div class="dashboard-card-header">
                <h2>Homework</h2>
                <span class="dashboard-card-subtitle">Assignments</span>
            </div>

            <div id="homeworkList" class="parent-list"></div>
        </article>
    </section>

    <article
        class="dashboard-card parent-full-card"
        style="<?= $parentCan('parent_results') ? '' : 'display:none' ?>"
    >
        <div class="dashboard-card-header">
            <h2>Subject Marks</h2>
            <span class="dashboard-card-subtitle">Recent Examinations</span>
        </div>

        <div class="parent-table-wrap">
            <table class="parent-table">
                <thead>
                <tr>
                    <th>Exam</th>
                    <th>Subject</th>
                    <th>Date</th>
                    <th>Marks</th>
                    <th>Grade</th>
                    <th>Remarks</th>
                </tr>
                </thead>
                <tbody id="subjectMarkRows"></tbody>
            </table>
        </div>
    </article>

    <section class="dashboard-lower-grid">
        <article
            class="dashboard-card"
            style="<?= $parentCan('parent_notices') ? '' : 'display:none' ?>"
        >
            <div class="dashboard-card-header">
                <h2>Academic Calendar</h2>
                <span class="dashboard-card-subtitle">Events & Holidays</span>
            </div>

            <div id="calendarList" class="parent-list"></div>
        </article>

        <article
            class="dashboard-card"
            style="<?= ($parentCan('parent_fees') || $parentCan('parent_fee_payment')) ? '' : 'display:none' ?>"
        >
            <div class="dashboard-card-header">
                <h2>Recent Fee Receipts</h2>
                <span class="dashboard-card-subtitle">Payment History</span>
            </div>

            <div id="receiptList" class="parent-list"></div>
        </article>
    </section>

    <article
        id="transport"
        class="dashboard-card parent-full-card"
        style="<?= $parentCan('parent_transport') ? '' : 'display:none' ?>"
    >
        <div class="dashboard-card-header">
            <h2>Transport Details</h2>
            <span class="dashboard-card-subtitle">Route / Vehicle Information</span>
        </div>

        <div id="transportBox" class="parent-card-body"></div>
    </article>

    <!-- Retained because the existing Parent API/JS can populate these. -->
    <article id="leave" class="dashboard-card" hidden>
        <div id="leaveList"></div>
    </article>

    <article id="timetable" class="dashboard-card" hidden>
        <span id="timetableDay"></span>
        <div id="timetableList"></div>
    </article>

    <!-- Optional legacy/header IDs are retained safely but hidden. -->
    <span id="parentName" hidden></span>
    <span id="parentContact" hidden></span>
    <span id="parentHeaderName" hidden></span>
    <span id="parentHeaderRole" hidden></span>
    <span id="parentHeaderInitials" hidden></span>
    <span id="headerAcademicYear" hidden></span>
</div>


<script>
(() => {
    const $ = id => document.getElementById(id);
    const apiUrl = 'api/dashboard.php';

    const esc = value => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const money = value => new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 0
    }).format(Number(value || 0));

    const dateText = value => {
        if (!value) return '—';
        const raw = String(value).slice(0, 10);
        const date = new Date(`${raw}T00:00:00`);
        if (Number.isNaN(date.getTime())) return raw;
        return date.toLocaleDateString('en-IN', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        });
    };

    const timeText = value => {
        if (!value) return '—';
        const bits = String(value).split(':');
        const d = new Date();
        d.setHours(Number(bits[0] || 0), Number(bits[1] || 0), 0, 0);
        return d.toLocaleTimeString('en-IN', {
            hour: '2-digit',
            minute: '2-digit'
        });
    };

    const badgeClass = status => {
        const s = String(status || '').toLowerCase();
        if (['present','paid','published','pass','active','approved','completed','on_duty'].includes(s)) return 'good';
        if (['partial','late','leave','scheduled','ongoing','pending','assigned'].includes(s)) return 'warn';
        if (['absent','unpaid','overdue','fail','failed','rejected','cancelled'].includes(s)) return 'bad';
        return 'neutral';
    };

    const feeName = type => ({
        admission: 'Admission Fee',
        tuition: 'Tuition Fee',
        term: 'Term / Exam Fee',
        additional: 'Other Fees',
        transport: 'Bus Fee',
        previous_due: 'Previous Due'
    })[String(type || '').toLowerCase()] || String(type || 'Fee');

    async function getDashboard(studentId = 0) {
        const suffix = studentId > 0
            ? `?student_id=${encodeURIComponent(studentId)}`
            : '';

        const response = await fetch(apiUrl + suffix, {
            credentials: 'same-origin',
            headers: {'Accept': 'application/json'}
        });

        const text = await response.text();
        let payload;

        try {
            payload = JSON.parse(text);
        } catch (e) {
            throw new Error('Parent Dashboard API returned an invalid response.');
        }

        if (!response.ok || !payload.success) {
            throw new Error(payload.message || 'Unable to load Parent Dashboard.');
        }

        return payload.data || {};
    }

    function renderParent(data) {
        const parent = data.parent || {};

        /*
         * v25 UI no longer contains the old parentName / parentContact
         * elements from the previous layout. Always check an element before
         * writing to it so permission-based UI changes cannot throw:
         * "Cannot set properties of null (setting 'textContent')".
         */
        const legacyParentName = $('parentName');
        const legacyParentContact = $('parentContact');

        if (legacyParentName) {
            legacyParentName.textContent =
                parent.guardian_name || 'Parent';
        }

        if (legacyParentContact) {
            legacyParentContact.textContent =
                parent.mobile
                || parent.email
                || parent.relationship
                || 'Guardian Account';
        }

        const headerName = $('parentHeaderName');
        const headerRole = $('parentHeaderRole');
        const headerInitials = $('parentHeaderInitials');

        if (headerName) {
            headerName.textContent =
                parent.guardian_name || 'Parent';
        }

        if (headerRole) {
            headerRole.textContent =
                parent.relationship || 'Parent';
        }

        if (headerInitials) {
            headerInitials.textContent =
                String(parent.guardian_name || 'P')
                    .split(/\s+/)
                    .filter(Boolean)
                    .slice(0, 2)
                    .map(value =>
                        value.charAt(0).toUpperCase()
                    )
                    .join('') || 'P';
        }
    }

    function renderChildren(data) {
        const rows = data.children || [];
        const selected = data.selected_child || {};

        $('childSelect').innerHTML = rows.map(row => `
            <option value="${esc(row.id)}"${Number(row.id) === Number(selected.id) ? ' selected' : ''}>
                ${esc(row.student_name)} · ${esc(row.class_name || 'No Class')}
            </option>
        `).join('');

        $('childSelect').disabled = rows.length <= 1;
    }

    function renderStudent(data) {
        const child = data.selected_child || {};

        $('profileYear').textContent = child.academic_year_name || 'Current enrollment';
        $('attendanceYear').textContent = child.academic_year_name || 'Academic year';

        const headerYear = $('headerAcademicYear');
        if (headerYear) {
            headerYear.textContent = child.academic_year_name || 'Academic Year';
        }

        const fields = [
            ['Admission No', child.admission_no],
            ['EMIS No', child.emis_no],
            ['Academic Year', child.academic_year_name],
            ['Class', child.class_name],
            ['Section', child.section_name],
            ['Roll No', child.roll_no],
            ['Gender', child.gender],
            ['Date of Birth', dateText(child.date_of_birth)],
            ['Blood Group', child.blood_group],
            ['Student Mobile', child.mobile],
            ['Student Email', child.email],
            ['Admission Date', dateText(child.admission_date)]
        ];

        $('profileGrid').innerHTML = fields.map(([label, value]) => `
            <div class="info">
                <span>${esc(label)}</span>
                <strong title="${esc(value || '—')}">${esc(value || '—')}</strong>
            </div>
        `).join('');
    }

    function renderSummary(data) {
        const s = data.summary || {};
        $('attendanceKpi').textContent = `${Number(s.attendance_percentage || 0).toFixed(1)}%`;
        $('attendanceNote').textContent = `${Number(s.working_days || 0)} working days`;
        $('totalFeeKpi').textContent = money(s.fee_total || 0);
        $('feeStatusNote').textContent = `Status: ${String(s.fee_status || 'not assigned').replaceAll('_', ' ')}`;
        $('feeDueKpi').textContent = money(s.fee_due || 0);
        $('feePaidNote').textContent = `Paid ${money(s.fee_paid || 0)}`;
        $('examKpi').textContent = Number(s.upcoming_exams || 0);
        $('homeworkKpi').textContent = Number(s.homework_count || 0);
        $('leaveKpi').textContent = Number(s.pending_leave_requests || 0);
    }

    function renderAttendance(data) {
        const a = data.attendance || {};
        $('presentDays').textContent = Number(a.present_days || 0);
        $('absentDays').textContent = Number(a.absent_days || 0);
        $('leaveDays').textContent = Number(a.leave_days || 0);
        $('lateDays').textContent = Number(a.late_days || 0);

        const rows = a.recent || [];
        $('attendanceRows').innerHTML = rows.length ? rows.map(row => `
            <tr>
                <td>${esc(dateText(row.attendance_date))}</td>
                <td><span class="badge ${badgeClass(row.status)}">${esc(row.status)}</span></td>
                <td>${esc(timeText(row.check_in))}</td>
                <td>${esc(timeText(row.check_out))}</td>
                <td>${esc(row.remarks || '—')}</td>
            </tr>
        `).join('') : '<tr><td colspan="5" class="empty">No attendance records found.</td></tr>';
    }

    function renderFees(data) {
        const fees = data.fees || {};
        const assignment = fees.assignment || {};
        const breakdown = fees.breakdown || [];

        $('feeStructureName').textContent =
            assignment.structure_name || 'No active fee structure';

        $('feeBreakdown').innerHTML = breakdown.length ? breakdown.map(row => {
            const gross = Number(row.gross_amount || 0);
            const paid = Number(row.paid_amount || 0);
            const pct = gross > 0
                ? Math.min(100, Math.max(0, (paid / gross) * 100))
                : 0;

            return `
                <div class="fee-line">
                    <div class="fee-line-name">${esc(feeName(row.item_type))}</div>
                    <div class="track"><span style="width:${pct.toFixed(1)}%"></span></div>
                    <div class="amount">${esc(money(gross))}</div>
                </div>`;
        }).join('') : '<div class="empty">No fee structure is assigned for this academic year.</div>';

        const due = fees.upcoming || [];
        $('feeRows').innerHTML = due.length ? due.map(row => `
            <tr>
                <td><strong>${esc(row.item_name || feeName(row.item_type))}</strong></td>
                <td>${esc(row.period_label || '—')}</td>
                <td>${esc(dateText(row.due_date))}</td>
                <td class="amount">${esc(money(row.balance_amount))}</td>
                <td><span class="badge ${badgeClass(row.item_status)}">${esc(row.item_status)}</span></td>
            </tr>
        `).join('') : '<tr><td colspan="5" class="empty">No pending fee dues.</td></tr>';

        const receipts = fees.receipts || [];
        $('receiptList').innerHTML = receipts.length ? receipts.map(row => `
            <div class="item">
                <div class="item-top">
                    <div>
                        <strong>${esc(row.receipt_no)}</strong>
                        <small>${esc(dateText(row.receipt_date))}</small>
                    </div>
                    <span class="amount">${esc(money(row.paid_amount))}</span>
                </div>
                <small><span class="badge ${badgeClass(row.payment_status)}">${esc(row.payment_status)}</span></small>
            </div>
        `).join('') : '<div class="empty">No fee receipts found.</div>';
    }

    function renderExams(data) {
        const exams = data.exams || {};

        const results = exams.results || [];
        $('resultRows').innerHTML = results.length ? results.map(row => `
            <tr>
                <td><strong>${esc(row.exam_name)}</strong></td>
                <td>${esc(Number(row.obtained_marks || 0))} / ${esc(Number(row.total_marks || 0))}</td>
                <td>${esc(Number(row.percentage || 0).toFixed(1))}%</td>
                <td>${esc(row.grade || '—')}</td>
                <td>${esc(row.rank || '—')}</td>
                <td><span class="badge ${Number(row.is_passed) === 1 ? 'good' : 'bad'}">${Number(row.is_passed) === 1 ? 'Pass' : 'Fail'}</span></td>
            </tr>
        `).join('') : '<tr><td colspan="6" class="empty">No published exam results.</td></tr>';

        const marks = exams.subject_marks || [];
        $('subjectMarkRows').innerHTML = marks.length ? marks.map(row => `
            <tr>
                <td>${esc(row.exam_name || '—')}</td>
                <td><strong>${esc(row.subject_name || 'Subject')}</strong></td>
                <td>${esc(dateText(row.exam_date))}</td>
                <td>${Number(row.is_absent) === 1 ? '<span class="badge bad">Absent</span>' : `${esc(Number(row.marks_obtained || 0))} / ${esc(Number(row.max_marks || 0))}`}</td>
                <td>${esc(row.grade || '—')}</td>
                <td>${esc(row.remarks || '—')}</td>
            </tr>
        `).join('') : '<tr><td colspan="6" class="empty">No subject marks are available.</td></tr>';

        const upcoming = exams.upcoming || [];
        $('examList').innerHTML = upcoming.length ? upcoming.map(row => `
            <div class="item">
                <div class="item-top">
                    <div>
                        <strong>${esc(row.subject_name)}</strong>
                        <small>${esc(row.exam_name)}</small>
                    </div>
                    <span class="badge warn">${esc(dateText(row.exam_date))}</span>
                </div>
                <p>${esc(timeText(row.start_time))} – ${esc(timeText(row.end_time))}${row.room_no ? ` · Room ${esc(row.room_no)}` : ''}</p>
            </div>
        `).join('') : '<div class="empty">No upcoming exams.</div>';
    }

    function renderTimetable(data) {
        const t = data.timetable || {};
        $('timetableDay').textContent = t.day_name || 'Today';

        const rows = t.entries || [];
        $('timetableList').innerHTML = rows.length ? rows.map(row => `
            <div class="item">
                <div class="item-top">
                    <div>
                        <strong>${esc(row.subject_name || 'Period')}</strong>
                        <small>${esc(row.teacher_name || 'Teacher not assigned')}</small>
                    </div>
                    <span class="badge neutral">${esc(row.period_name || 'Period')}</span>
                </div>
                <p>${esc(timeText(row.start_time))} – ${esc(timeText(row.end_time))}${row.room_name ? ` · ${esc(row.room_name)}` : ''}</p>
            </div>
        `).join('') : '<div class="empty">No timetable entries for today.</div>';
    }

    function renderHomework(data) {
        const hw = data.homework || {};

        if (!hw.configured) {
            $('homeworkList').innerHTML = `
                <div class="module-note">
                    Homework module exists in the ERP menu, but the uploaded database does not currently contain a dedicated Homework data table. This dashboard will start showing homework automatically when a supported homework table is available.
                </div>`;
            return;
        }

        const rows = hw.rows || [];
        $('homeworkList').innerHTML = rows.length ? rows.map(row => `
            <div class="item">
                <div class="item-top">
                    <div>
                        <strong>${esc(row.title || 'Homework')}</strong>
                        <small>${esc(row.subject || 'General')}</small>
                    </div>
                    <span class="badge ${badgeClass(row.status)}">${esc(row.status || 'assigned')}</span>
                </div>
                ${row.description ? `<p>${esc(row.description)}</p>` : ''}
                <small>
                    Assigned: ${esc(dateText(row.assigned_date))}
                    ${row.due_date ? ` · Due: ${esc(dateText(row.due_date))}` : ''}
                </small>
            </div>
        `).join('') : '<div class="empty">No homework assigned.</div>';
    }

    function renderLeave(data) {
        const leave = data.leave_requests || {};

        if (!leave.configured) {
            $('leaveList').innerHTML = '<div class="module-note">Student leave request storage is not configured.</div>';
            return;
        }

        const rows = leave.rows || [];
        $('leaveList').innerHTML = rows.length ? rows.map(row => `
            <div class="item">
                <div class="item-top">
                    <div>
                        <strong>Student Leave Request</strong>
                        <small>${esc(dateText(row.attendance_date))}</small>
                    </div>
                    <span class="badge ${badgeClass(row.status)}">${esc(row.status)}</span>
                </div>
                <p>${esc(row.reason || 'No reason entered.')}</p>
            </div>
        `).join('') : '<div class="empty">No leave requests found.</div>';
    }

    function renderUpdates(data) {
        const notifications = data.notifications || [];
        const messages = data.messages || [];

        const rows = [
            ...notifications.map(row => ({
                title: row.title || 'Notification',
                body: row.body || '',
                created_at: row.created_at,
                unread: !row.read_at,
                source: 'Notification'
            })),
            ...messages.map(row => ({
                title: row.subject || 'School Message',
                body: row.message || '',
                created_at: row.created_at,
                unread: !row.read_at,
                source: row.sender_name || 'School'
            }))
        ].sort((a, b) => String(b.created_at).localeCompare(String(a.created_at))).slice(0, 10);

        $('updatesList').innerHTML = rows.length ? rows.map(row => `
            <div class="item">
                <div class="item-top">
                    <div>
                        <strong>${esc(row.title)}</strong>
                        <small>${esc(row.source)} · ${esc(dateText(row.created_at))}</small>
                    </div>
                    ${row.unread ? '<span class="badge good">New</span>' : ''}
                </div>
                ${row.body ? `<p>${esc(row.body)}</p>` : ''}
            </div>
        `).join('') : '<div class="empty">No announcements or important updates.</div>';

        const calendar = data.calendar_updates || [];
        $('calendarList').innerHTML = calendar.length ? calendar.map(row => `
            <div class="item">
                <div class="item-top">
                    <div>
                        <strong>${esc(row.title)}</strong>
                        <small>${esc(row.category || row.type || 'Event')}</small>
                    </div>
                    <span class="badge neutral">${esc(dateText(row.event_date))}</span>
                </div>
                ${row.body ? `<p>${esc(row.body)}</p>` : ''}
            </div>
        `).join('') : '<div class="empty">No academic calendar updates.</div>';
    }

    function renderTransport(data) {
        const row = data.transport;

        if (!row || Number(row.transport_required || 0) !== 1) {
            $('transportBox').innerHTML = '<div class="empty">Transport is not assigned for this student.</div>';
            return;
        }

        const fields = [
            ['Route', row.route_name],
            ['Boarding Stop', row.boarding_stop_name],
            ['Vehicle', row.vehicle_name],
            ['Vehicle No', row.vehicle_number],
            ['Driver', row.driver_name],
            ['Driver Mobile', row.driver_mobile],
            ['Bus Fee', money(row.bus_fee_amount || row.transport_fee_amount || 0)],
            ['Route Code', row.route_code]
        ];

        $('transportBox').innerHTML = `
            <div class="transport-grid">
                ${fields.map(([label, value]) => `
                    <div class="info">
                        <span>${esc(label)}</span>
                        <strong title="${esc(value || '—')}">${esc(value || '—')}</strong>
                    </div>
                `).join('')}
            </div>`;
    }

    function render(data) {
        renderParent(data);
        renderChildren(data);
        renderStudent(data);
        renderSummary(data);
        renderAttendance(data);
        renderFees(data);
        renderExams(data);
        renderTimetable(data);
        renderHomework(data);
        renderLeave(data);
        renderUpdates(data);
        renderTransport(data);
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }
    }

    async function load(studentId = 0) {
        $('errorBox').classList.remove('show');
        $('loading').style.display = 'grid';

        try {
            const data = await getDashboard(studentId);
            render(data);
        } catch (error) {
            $('errorBox').textContent =
                error.message || 'Unable to load Parent Dashboard.';
            $('errorBox').classList.add('show');
        } finally {
            $('loading').style.display = 'none';
        }
    }

    $('childSelect').addEventListener('change', () => {
        load(Number($('childSelect').value || 0));
    });

    load();
})();
</script>


<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
        window.lucide.createIcons();
    }
});
</script>

<?php require dirname(__DIR__) . '/includes/layout-end.php'; ?>

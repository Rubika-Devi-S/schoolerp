<?php
declare(strict_types=1);

/*
 * Parent - My Children
 * Location: parent/my_children.php
 * Data source: parent/api/my_children.php
 * Build: 2026-08-13-parent-my-children-v30
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

$tenantId = (int)(
    $user['tenant_id']
    ?? $_SESSION['tenant_id']
    ?? $_SESSION['school_id']
    ?? 0
);

$roleId = (int)(
    $user['role_id']
    ?? $_SESSION['role_id']
    ?? 0
);

$role = isset($pdo) && $pdo instanceof PDO
    ? pc_role($pdo, $tenantId, $roleId)
    : [];

if (
    pc_role_key((string)($role['role_key'] ?? ''))
    !== 'parent'
) {
    http_response_code(403);
    exit('My Children is available only to Parent accounts.');
}

$parentSidebarItems = isset($pdo) && $pdo instanceof PDO
    ? school_sidebar_get_items(
        $pdo,
        $roleId,
        $tenantId
    )
    : [];

$parentAllowedMenuKeys = [];

foreach ($parentSidebarItems as $menu) {
    $key = (string)($menu['menu_key'] ?? '');
    if ($key !== '') {
        $parentAllowedMenuKeys[$key] = true;
    }
}

if ($parentSidebarItems === []) {
    http_response_code(403);
    exit('Parent Portal is disabled for this school.');
}

$parentCan = static fn(string $key): bool =>
    isset($parentAllowedMenuKeys[$key]);

if (!$parentCan('parent_my_children')) {
    http_response_code(403);
    exit('My Children is disabled for this Parent account.');
}

$pageTitle = 'My Children';
$pageKey = 'parent_my_children';

/*
 * Use the exact same shared ERP header/sidebar shell as Parent Dashboard.
 * school/sidebar.php is role-aware, so only enabled Parent menus are shown.
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

/* =========================================================
   MY CHILDREN
   Only page-content styles are added.
   Shared header/sidebar/navbar remain untouched.
   ========================================================= */
.my-children-page{
    display:grid;
    gap:16px;
}

.my-children-page .children-summary{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
}

.my-children-page .children-summary-copy strong,
.my-children-page .children-summary-copy small{
    display:block;
}

.my-children-page .children-summary-copy strong{
    color:var(--text-main,#101a3b);
    font-size:14px;
    font-weight:700;
}

.my-children-page .children-summary-copy small{
    margin-top:3px;
    color:var(--text-muted,#64748b);
    font-size:10px;
}

.my-children-page .children-count{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:32px;
    height:28px;
    padding:0 9px;
    color:#3157d7;
    background:#edf3ff;
    border-radius:999px;
    font-size:10px;
    font-weight:800;
}

.my-children-table-wrap{
    overflow:auto;
}

.my-children-table{
    width:100%;
    min-width:900px;
    border-collapse:collapse;
}

.my-children-table th,
.my-children-table td{
    padding:12px 14px;
    border-bottom:1px solid var(--border-soft,#e7ebf3);
    text-align:left;
    vertical-align:middle;
}

.my-children-table th{
    color:var(--text-muted,#64748b);
    background:#f8fafc;
    font-size:8px;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:.035em;
}

.my-children-table td{
    color:var(--text-main,#101a3b);
    font-size:10px;
    font-weight:500;
}

.my-children-table tbody tr:last-child td{
    border-bottom:0;
}

.child-name-cell{
    min-width:180px;
    display:flex;
    align-items:center;
    gap:10px;
}

.child-avatar{
    width:38px;
    height:38px;
    flex:0 0 38px;
    display:grid;
    place-items:center;
    overflow:hidden;
    color:#fff;
    background:linear-gradient(135deg,#7548ee,#5033d5);
    border-radius:50%;
    font-size:11px;
    font-weight:800;
}

.child-avatar img{
    width:100%;
    height:100%;
    object-fit:cover;
}

.child-name-copy{
    min-width:0;
}

.child-name-copy strong,
.child-name-copy small{
    display:block;
}

.child-name-copy strong{
    overflow:hidden;
    color:var(--text-main,#101a3b);
    font-size:10.5px;
    font-weight:700;
    text-overflow:ellipsis;
    white-space:nowrap;
}

.child-name-copy small{
    margin-top:3px;
    color:var(--text-muted,#64748b);
    font-size:8px;
}

.child-mobile-grid{
    display:none;
    gap:12px;
}

.child-mobile-card{
    padding:15px;
}

.child-mobile-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    margin-bottom:13px;
}

.child-mobile-info{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:9px;
}

.child-field{
    min-width:0;
    padding:10px;
    background:#f8fafc;
    border:1px solid var(--border-soft,#e7ebf3);
    border-radius:9px;
}

.child-field span,
.child-field strong{
    display:block;
}

.child-field span{
    margin-bottom:4px;
    color:var(--text-muted,#64748b);
    font-size:7.5px;
    font-weight:600;
    text-transform:uppercase;
}

.child-field strong{
    overflow:hidden;
    color:var(--text-main,#101a3b);
    font-size:9.5px;
    font-weight:700;
    text-overflow:ellipsis;
    white-space:nowrap;
}

.child-mobile-action{
    margin-top:12px;
    display:flex;
    justify-content:flex-end;
}

.my-children-empty{
    padding:38px 16px;
    text-align:center;
    color:var(--text-muted,#64748b);
    font-size:10px;
}

.my-children-loading{
    padding:34px 16px;
    text-align:center;
    color:var(--text-muted,#64748b);
    font-size:10px;
}

/* View Details modal uses the same card/field language as Parent Dashboard. */
.child-detail-profile{
    display:flex;
    align-items:center;
    gap:12px;
    padding-bottom:14px;
    margin-bottom:14px;
    border-bottom:1px solid var(--border-soft,#e7ebf3);
}

.child-detail-profile .child-avatar{
    width:48px;
    height:48px;
    flex-basis:48px;
    font-size:14px;
}

.child-detail-profile strong,
.child-detail-profile small{
    display:block;
}

.child-detail-profile strong{
    font-size:13px;
    font-weight:700;
}

.child-detail-profile small{
    margin-top:3px;
    color:var(--text-muted,#64748b);
    font-size:9px;
}

.child-detail-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:10px;
}

@media(max-width:767.98px){
    .my-children-table-wrap{
        display:none;
    }

    .child-mobile-grid{
        display:grid;
    }

    .my-children-page .children-summary{
        align-items:flex-start;
    }
}

@media(max-width:575.98px){
    .child-mobile-info,
    .child-detail-grid{
        grid-template-columns:1fr;
    }

    .child-mobile-action .btn-ui{
        width:100%;
        justify-content:center;
    }
}
</style>


<div class="dashboard-page parent-dashboard my-children-page">
    <div class="page-heading">
        <div>
            <h1 class="page-title">My Children</h1>
            <p class="page-subtitle">
                View all students linked to your Parent account.
            </p>
        </div>
    </div>

    <article class="dashboard-card">
        <div class="dashboard-card-header">
            <div class="children-summary-copy">
                <strong>Linked Children</strong>
                <small>
                    Student details are loaded from the existing Parent / Student relationship.
                </small>
            </div>

            <span id="childrenCount" class="children-count">0</span>
        </div>

        <div id="childrenLoading" class="my-children-loading">
            Loading children...
        </div>

        <div id="childrenEmpty" class="my-children-empty" style="display:none">
            No students are linked to this Parent account.
        </div>

        <div id="childrenDesktop" class="my-children-table-wrap" style="display:none">
            <table class="my-children-table">
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Admission Number</th>
                        <th>Class</th>
                        <th>Section</th>
                        <th>Roll Number</th>
                        <th>Academic Year</th>
                        <th style="width:115px">Action</th>
                    </tr>
                </thead>
                <tbody id="childrenTableBody"></tbody>
            </table>
        </div>

        <div id="childrenMobile" class="child-mobile-grid" style="display:none"></div>
    </article>
</div>

<div
    class="modal fade"
    id="childDetailModal"
    tabindex="-1"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Child Details</h5>
                    <small class="text-muted">
                        Student information linked to this Parent account.
                    </small>
                </div>
                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Close"
                ></button>
            </div>

            <div class="modal-body">
                <div id="childDetailContent"></div>
            </div>

            <div class="modal-footer">
                <button
                    type="button"
                    class="btn-ui"
                    data-bs-dismiss="modal"
                >
                    Close
                </button>
            </div>
        </div>
    </div>
</div>


<script>
(function(){
    'use strict';

    const apiUrl = new URL(
        'api/my_children.php',
        window.location.href
    ).href;

    const $ = id => document.getElementById(id);

    const esc = value => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    let children = [];

    function initials(name) {
        return String(name || 'S')
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map(part => part.charAt(0).toUpperCase())
            .join('') || 'S';
    }

    function avatarHtml(child, className = 'child-avatar') {
        if (child.photo_path) {
            const src = '../'
                + String(child.photo_path)
                    .replace(/^\/+/, '');

            return `
                <div class="${className}">
                    <img
                        src="${esc(src)}"
                        alt="${esc(child.student_name || 'Student')}"
                    >
                </div>`;
        }

        return `
            <div class="${className}">
                ${esc(initials(child.student_name))}
            </div>`;
    }

    function display(value) {
        const text = String(value ?? '').trim();
        return text !== '' ? text : '—';
    }

    function childRow(child) {
        return `
            <tr>
                <td>
                    <div class="child-name-cell">
                        ${avatarHtml(child)}
                        <div class="child-name-copy">
                            <strong title="${esc(display(child.student_name))}">
                                ${esc(display(child.student_name))}
                            </strong>
                            <small>
                                ${esc(display(child.enrollment_status || 'Student'))}
                            </small>
                        </div>
                    </div>
                </td>
                <td>${esc(display(child.admission_no))}</td>
                <td>${esc(display(child.class_name))}</td>
                <td>${esc(display(child.section_name))}</td>
                <td>${esc(display(child.roll_no))}</td>
                <td>${esc(display(child.academic_year_name))}</td>
                <td>
                    <button
                        class="btn-ui js-view-child"
                        type="button"
                        data-child-id="${Number(child.id || 0)}"
                    >
                        <i data-lucide="eye"></i>
                        View Details
                    </button>
                </td>
            </tr>`;
    }

    function childMobileCard(child) {
        return `
            <article class="dashboard-card child-mobile-card">
                <div class="child-mobile-head">
                    <div class="child-name-cell">
                        ${avatarHtml(child)}
                        <div class="child-name-copy">
                            <strong>${esc(display(child.student_name))}</strong>
                            <small>${esc(display(child.admission_no))}</small>
                        </div>
                    </div>
                </div>

                <div class="child-mobile-info">
                    <div class="child-field">
                        <span>Class</span>
                        <strong>${esc(display(child.class_name))}</strong>
                    </div>

                    <div class="child-field">
                        <span>Section</span>
                        <strong>${esc(display(child.section_name))}</strong>
                    </div>

                    <div class="child-field">
                        <span>Roll Number</span>
                        <strong>${esc(display(child.roll_no))}</strong>
                    </div>

                    <div class="child-field">
                        <span>Academic Year</span>
                        <strong>${esc(display(child.academic_year_name))}</strong>
                    </div>
                </div>

                <div class="child-mobile-action">
                    <button
                        class="btn-ui btn-primary-ui js-view-child"
                        type="button"
                        data-child-id="${Number(child.id || 0)}"
                    >
                        <i data-lucide="eye"></i>
                        View Details
                    </button>
                </div>
            </article>`;
    }

    function bindViewButtons() {
        document.querySelectorAll('.js-view-child')
            .forEach(button => {
                button.addEventListener('click', () => {
                    const childId = Number(
                        button.dataset.childId || 0
                    );

                    const child = children.find(
                        row => Number(row.id || 0) === childId
                    );

                    if (!child) {
                        return;
                    }

                    openDetails(child);
                });
            });
    }

    function openDetails(child) {
        const fields = [
            ['Admission Number', child.admission_no],
            ['Class', child.class_name],
            ['Section', child.section_name],
            ['Roll Number', child.roll_no],
            ['Academic Year', child.academic_year_name]
        ];

        $('childDetailContent').innerHTML = `
            <div class="child-detail-profile">
                ${avatarHtml(child)}
                <div>
                    <strong>${esc(display(child.student_name))}</strong>
                    <small>
                        ${esc(display(child.class_name))}
                        ${child.section_name
                            ? ' · ' + esc(child.section_name)
                            : ''}
                    </small>
                </div>
            </div>

            <div class="child-detail-grid">
                ${fields.map(([label, value]) => `
                    <div class="child-field">
                        <span>${esc(label)}</span>
                        <strong title="${esc(display(value))}">
                            ${esc(display(value))}
                        </strong>
                    </div>
                `).join('')}
            </div>`;

        window.lucide?.createIcons();

        if (window.bootstrap?.Modal) {
            window.bootstrap.Modal
                .getOrCreateInstance(
                    $('childDetailModal')
                )
                .show();
        }
    }

    function render() {
        $('childrenCount').textContent =
            String(children.length);

        $('childrenLoading').style.display =
            'none';

        if (!children.length) {
            $('childrenEmpty').style.display = '';
            $('childrenDesktop').style.display = 'none';
            $('childrenMobile').style.display = 'none';
            return;
        }

        $('childrenEmpty').style.display = 'none';

        $('childrenTableBody').innerHTML =
            children.map(childRow).join('');

        $('childrenMobile').innerHTML =
            children.map(childMobileCard).join('');

        $('childrenDesktop').style.display = '';
        $('childrenMobile').style.display = '';

        bindViewButtons();
        window.lucide?.createIcons();
    }

    async function load() {
        try {
            const response = await fetch(
                apiUrl,
                {
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json'
                    }
                }
            );

            const text = await response.text();
            let payload;

            try {
                payload = JSON.parse(text);
            } catch (error) {
                throw new Error(
                    'Parent Dashboard API returned an invalid response.'
                );
            }

            if (!response.ok || !payload.success) {
                throw new Error(
                    payload.message
                    || 'Unable to load linked children.'
                );
            }

            /*
             * Reuse the exact existing Parent Dashboard relationship result.
             * No student/guardian data is created or copied here.
             */
            children = Array.isArray(payload.data?.children)
                ? payload.data.children
                : [];

            render();
        } catch (error) {
            $('childrenLoading').innerHTML =
                `<span class="text-danger">${
                    esc(
                        error.message
                        || 'Unable to load linked children.'
                    )
                }</span>`;
        }
    }

    load();
})();
</script>


<?php require dirname(__DIR__) . '/includes/layout-end.php'; ?>

<?php
declare(strict_types=1);

/*
 * Parent Fees
 * Location: parent/fees.php
 * API: parent/api/fees.php
 * Build: 2026-08-13-parent-fees-current-ui-v33
 *
 * Read-only page.
 * Uses the existing Parent Dashboard API fee data.
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
    exit('Fees is available only to Parent accounts.');
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

if (!$parentCan('parent_fees')) {
    http_response_code(403);
    exit('Fees is disabled for this Parent account.');
}

$pageTitle = 'Fees';
$pageKey = 'parent_fees';

/*
 * Same shared ERP sidebar/header shell used by the supplied Parent Dashboard.
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

/*
 * Fees page content only.
 * No shared layout/header/sidebar design is changed.
 */
.parent-fees-page .fee-overview-grid{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:10px;
    padding:16px;
}

.parent-fees-page .fee-overview-item{
    min-width:0;
    padding:11px 12px;
    background:#f8fafc;
    border:1px solid var(--border-soft,#e7ebf3);
    border-radius:10px;
}

.parent-fees-page .fee-overview-item span,
.parent-fees-page .fee-overview-item strong{
    display:block;
}

.parent-fees-page .fee-overview-item span{
    margin-bottom:4px;
    color:var(--text-muted,#64748b);
    font-size:8px;
    font-weight:600;
    text-transform:uppercase;
    letter-spacing:.03em;
}

.parent-fees-page .fee-overview-item strong{
    overflow:hidden;
    color:var(--text-main,#101a3b);
    font-size:10px;
    font-weight:700;
    text-overflow:ellipsis;
    white-space:nowrap;
}

.parent-fees-page .fee-type-cell{
    display:flex;
    align-items:center;
    gap:8px;
    min-width:150px;
}

.parent-fees-page .fee-type-icon{
    width:30px;
    height:30px;
    flex:0 0 30px;
    display:grid;
    place-items:center;
    color:#4f46e5;
    background:#eef2ff;
    border-radius:8px;
}

.parent-fees-page .fee-type-icon svg{
    width:15px;
    height:15px;
}

.parent-fees-page .fee-outstanding{
    font-weight:800;
}

.parent-fees-page .fee-outstanding.has-due{
    color:#c83a55;
}

.parent-fees-page .fee-outstanding.clear{
    color:#158458;
}

.parent-fees-page .receipt-actions{
    display:flex;
    align-items:center;
    gap:6px;
}

@media(max-width:991.98px){
    .parent-fees-page .fee-overview-grid{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }
}

@media(max-width:575.98px){
    .parent-fees-page .fee-overview-grid{
        grid-template-columns:1fr;
    }
}
</style>

<div id="loading" class="parent-dashboard-loading">
    <div>Loading Fees...</div>
</div>

<div class="dashboard-page parent-dashboard parent-fees-page">
    <div class="page-heading">
        <div>
            <h1 class="page-title">Fees</h1>
            <p class="page-subtitle">
                View your child’s complete fee details and outstanding balance.
            </p>
        </div>

        <div class="page-actions">
            <label class="parent-child-picker" for="childSelect">
                <i data-lucide="users-round"></i>
                <select id="childSelect">
                    <option>Loading child...</option>
                </select>
            </label>
        </div>
    </div>

    <div id="errorBox" class="parent-error"></div>

    <section class="metric-grid">
        <article class="metric-card metric-purple">
            <div class="metric-icon">
                <i data-lucide="wallet-cards"></i>
            </div>
            <div>
                <small>Total Amount</small>
                <div id="totalFeeKpi" class="metric-value">₹0</div>
                <div id="feeStructureName" class="metric-trend">
                    No active fee structure
                </div>
            </div>
        </article>

        <article class="metric-card metric-blue">
            <div class="metric-icon">
                <i data-lucide="badge-indian-rupee"></i>
            </div>
            <div>
                <small>Paid Amount</small>
                <div id="paidFeeKpi" class="metric-value">₹0</div>
                <div class="metric-trend">Payments received</div>
            </div>
        </article>

        <article class="metric-card metric-green">
            <div class="metric-icon">
                <i data-lucide="circle-check-big"></i>
            </div>
            <div>
                <small>Current Balance</small>
                <div id="balanceFeeKpi" class="metric-value">₹0</div>
                <div id="balanceFeeNote" class="metric-trend">
                    Outstanding balance
                </div>
            </div>
        </article>

        <article class="metric-card metric-orange">
            <div class="metric-icon">
                <i data-lucide="calendar-days"></i>
            </div>
            <div>
                <small>Academic Year</small>
                <div id="academicYearKpi" class="metric-value">—</div>
                <div id="classKpi" class="metric-trend">
                    Class —
                </div>
            </div>
        </article>
    </section>

    <article class="dashboard-card">
        <div class="dashboard-card-header">
            <h2>Fee Summary</h2>
            <span id="feeStatusText" class="dashboard-card-subtitle">
                Current academic year
            </span>
        </div>

        <div id="feeOverviewGrid" class="fee-overview-grid"></div>
    </article>

    <article class="dashboard-card">
        <div class="dashboard-card-header">
            <h2>Applicable Fees</h2>
            <span class="dashboard-card-subtitle">
                Tuition, transport and other assigned fees
            </span>
        </div>

        <div class="parent-table-wrap">
            <table class="parent-table">
                <thead>
                    <tr>
                        <th>Academic Year</th>
                        <th>Class</th>
                        <th>Fee Type</th>
                        <th>Total Amount</th>
                        <th>Paid Amount</th>
                        <th>Balance Amount</th>
                    </tr>
                </thead>
                <tbody id="feeBreakdownRows"></tbody>
            </table>
        </div>
    </article>

    <section class="dashboard-lower-grid">
        <article class="dashboard-card">
            <div class="dashboard-card-header">
                <h2>Pending Fee Dues</h2>
                <span class="dashboard-card-subtitle">
                    Outstanding fee installments
                </span>
            </div>

            <div class="parent-table-wrap">
                <table class="parent-table">
                    <thead>
                        <tr>
                            <th>Fee</th>
                            <th>Period</th>
                            <th>Due Date</th>
                            <th>Total</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="pendingFeeRows"></tbody>
                </table>
            </div>
        </article>

        <article class="dashboard-card">
            <div class="dashboard-card-header">
                <h2>Recent Fee Receipts</h2>
                <span class="dashboard-card-subtitle">
                    Read-only payment history
                </span>
            </div>

            <div id="receiptList" class="parent-list"></div>
        </article>
    </section>
</div>

<script>
(() => {
    const $ = id => document.getElementById(id);
    const apiUrl = 'api/fees.php';

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

        if (Number.isNaN(date.getTime())) {
            return raw;
        }

        return date.toLocaleDateString('en-IN', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        });
    };

    const badgeClass = status => {
        const value = String(status || '').toLowerCase();

        if ([
            'paid',
            'active',
            'completed'
        ].includes(value)) {
            return 'good';
        }

        if ([
            'partial',
            'pending',
            'assigned'
        ].includes(value)) {
            return 'warn';
        }

        if ([
            'unpaid',
            'overdue',
            'cancelled'
        ].includes(value)) {
            return 'bad';
        }

        return 'neutral';
    };

    const feeName = type => ({
        admission: 'Admission Fee',
        tuition: 'Tuition Fee',
        term: 'Term / Exam Fee',
        additional: 'Other Fees',
        transport: 'Bus Fee',
        previous_due: 'Previous Due'
    })[String(type || '').toLowerCase()]
        || String(type || 'Fee')
            .replaceAll('_', ' ')
            .replace(/\b\w/g, value => value.toUpperCase());

    const feeIcon = type => ({
        admission: 'school',
        tuition: 'book-open',
        term: 'notebook-tabs',
        additional: 'receipt-text',
        transport: 'bus-front',
        previous_due: 'history'
    })[String(type || '').toLowerCase()]
        || 'receipt-text';

    async function getFees(studentId = 0) {
        const suffix = studentId > 0
            ? `?student_id=${encodeURIComponent(studentId)}`
            : '';

        const response = await fetch(apiUrl + suffix, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json'
            }
        });

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
                payload.message || 'Unable to load fee details.'
            );
        }

        return payload.data || {};
    }

    function renderChildren(data) {
        const children = Array.isArray(data.children)
            ? data.children
            : [];

        const selected = data.selected_child || {};

        $('childSelect').innerHTML = children.length
            ? children.map(row => `
                <option
                    value="${esc(row.id)}"
                    ${
                        Number(row.id) === Number(selected.id)
                            ? ' selected'
                            : ''
                    }
                >
                    ${esc(row.student_name)}
                    ·
                    ${esc(row.class_name || 'No Class')}
                </option>
            `).join('')
            : '<option value="">No linked child</option>';

        $('childSelect').disabled =
            children.length <= 1;
    }

    function renderSummary(data) {
        const child = data.selected_child || {};
        const fees = data.fees || {};
        const assignment = fees.assignment || null;
        const summary = data.summary || {};

        const total = Number(
            assignment?.net_amount
            ?? assignment?.gross_amount
            ?? summary.fee_total
            ?? 0
        );

        const paid = Number(
            assignment?.paid_amount
            ?? summary.fee_paid
            ?? 0
        );

        const balance = Number(
            assignment?.balance_amount
            ?? summary.fee_due
            ?? 0
        );

        $('totalFeeKpi').textContent = money(total);
        $('paidFeeKpi').textContent = money(paid);
        $('balanceFeeKpi').textContent = money(balance);

        $('balanceFeeNote').textContent =
            balance > 0
                ? `Outstanding ${money(balance)}`
                : 'No outstanding balance';

        $('feeStructureName').textContent =
            assignment?.structure_name
            || 'No active fee structure';

        $('academicYearKpi').textContent =
            child.academic_year_name || '—';

        $('classKpi').textContent =
            child.class_name
                ? `Class ${child.class_name}`
                : 'Class —';

        const status = String(
            assignment?.payment_status
            ?? summary.fee_status
            ?? 'not assigned'
        ).replaceAll('_', ' ');

        $('feeStatusText').textContent =
            `Status: ${status}`;

        const overview = [
            ['Academic Year', child.academic_year_name],
            ['Class', child.class_name],
            ['Section', child.section_name],
            ['Fee Structure', assignment?.structure_name],
            ['Total Amount', money(total)],
            ['Paid Amount', money(paid)],
            ['Balance Amount', money(balance)]
        ];

        $('feeOverviewGrid').innerHTML =
            overview.map(([label, value]) => `
                <div class="fee-overview-item">
                    <span>${esc(label)}</span>
                    <strong title="${esc(value || '—')}">
                        ${esc(value || '—')}
                    </strong>
                </div>
            `).join('');
    }

    function renderBreakdown(data) {
        const child = data.selected_child || {};
        const fees = data.fees || {};
        const rows = Array.isArray(fees.breakdown)
            ? fees.breakdown
            : [];

        $('feeBreakdownRows').innerHTML =
            rows.length
                ? rows.map(row => {
                    const total = Number(row.gross_amount || 0);
                    const paid = Number(row.paid_amount || 0);
                    const balance = Number(row.balance_amount || 0);

                    return `
                        <tr>
                            <td>
                                ${esc(child.academic_year_name || '—')}
                            </td>
                            <td>
                                ${esc(child.class_name || '—')}
                            </td>
                            <td>
                                <div class="fee-type-cell">
                                    <span class="fee-type-icon">
                                        <i data-lucide="${esc(
                                            feeIcon(row.item_type)
                                        )}"></i>
                                    </span>
                                    <strong>
                                        ${esc(feeName(row.item_type))}
                                    </strong>
                                </div>
                            </td>
                            <td class="amount">
                                ${esc(money(total))}
                            </td>
                            <td class="amount">
                                ${esc(money(paid))}
                            </td>
                            <td class="fee-outstanding ${
                                balance > 0 ? 'has-due' : 'clear'
                            }">
                                ${esc(money(balance))}
                            </td>
                        </tr>
                    `;
                }).join('')
                : `
                    <tr>
                        <td colspan="6" class="empty">
                            No fee structure is assigned for this academic year.
                        </td>
                    </tr>
                `;
    }

    function renderPending(data) {
        const fees = data.fees || {};
        const rows = Array.isArray(fees.upcoming)
            ? fees.upcoming
            : [];

        $('pendingFeeRows').innerHTML =
            rows.length
                ? rows.map(row => {
                    const total = Number(
                        row.original_amount
                        ?? row.gross_amount
                        ?? 0
                    );

                    const paid = Number(
                        row.paid_amount || 0
                    );

                    const balance = Number(
                        row.balance_amount || 0
                    );

                    return `
                        <tr>
                            <td>
                                <strong>
                                    ${esc(
                                        row.item_name
                                        || feeName(row.item_type)
                                    )}
                                </strong>
                            </td>
                            <td>
                                ${esc(row.period_label || '—')}
                            </td>
                            <td>
                                ${esc(dateText(row.due_date))}
                            </td>
                            <td class="amount">
                                ${esc(money(total))}
                            </td>
                            <td class="amount">
                                ${esc(money(paid))}
                            </td>
                            <td class="fee-outstanding ${
                                balance > 0 ? 'has-due' : 'clear'
                            }">
                                ${esc(money(balance))}
                            </td>
                            <td>
                                <span class="badge ${
                                    badgeClass(row.item_status)
                                }">
                                    ${esc(
                                        row.item_status || 'pending'
                                    )}
                                </span>
                            </td>
                        </tr>
                    `;
                }).join('')
                : `
                    <tr>
                        <td colspan="7" class="empty">
                            No pending fee dues.
                        </td>
                    </tr>
                `;
    }

    function renderReceipts(data) {
        const fees = data.fees || {};
        const receipts = Array.isArray(fees.receipts)
            ? fees.receipts
            : [];

        $('receiptList').innerHTML =
            receipts.length
                ? receipts.map(row => `
                    <div class="item">
                        <div class="item-top">
                            <div>
                                <strong>
                                    ${esc(row.receipt_no || 'Receipt')}
                                </strong>
                                <small>
                                    ${esc(dateText(row.receipt_date))}
                                </small>
                            </div>

                            <span class="amount">
                                ${esc(money(row.paid_amount))}
                            </span>
                        </div>

                        <small>
                            <span class="badge ${
                                badgeClass(row.payment_status)
                            }">
                                ${esc(
                                    row.payment_status
                                    || 'paid'
                                )}
                            </span>
                        </small>
                    </div>
                `).join('')
                : `
                    <div class="empty">
                        No fee receipts found.
                    </div>
                `;
    }

    function render(data) {
        renderChildren(data);
        renderSummary(data);
        renderBreakdown(data);
        renderPending(data);
        renderReceipts(data);

        if (
            window.lucide
            && typeof window.lucide.createIcons === 'function'
        ) {
            window.lucide.createIcons();
        }
    }

    async function load(studentId = 0) {
        $('errorBox').classList.remove('show');
        $('loading').style.display = 'grid';

        try {
            const data = await getFees(studentId);
            render(data);
        } catch (error) {
            $('errorBox').textContent =
                error.message || 'Unable to load fee details.';

            $('errorBox').classList.add('show');
        } finally {
            $('loading').style.display = 'none';
        }
    }

    $('childSelect').addEventListener(
        'change',
        () => {
            load(
                Number(
                    $('childSelect').value || 0
                )
            );
        }
    );

    load();
})();
</script>

<?php
require dirname(__DIR__) . '/includes/layout-end.php';
?>

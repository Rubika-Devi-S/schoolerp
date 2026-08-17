<?php
declare(strict_types=1);

/*
 * Parent - Profile
 * Location: parent/profile.php
 * API: parent/api/profile.php
 * Build: 2026-08-13-parent-profile-v40
 */

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/sidebar-manager.php';
require_once dirname(__DIR__) . '/includes/permission-chain.php';

$user = function_exists('current_user')
    ? current_user()
    : [];

$user = is_array($user)
    ? $user
    : [];

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
    ? pc_role(
        $pdo,
        $tenantId,
        $roleId
    )
    : [];

if (
    pc_role_key(
        (string)($role['role_key'] ?? '')
    ) !== 'parent'
) {
    http_response_code(403);

    exit(
        'Profile is available only to Parent accounts.'
    );
}

$parentSidebarItems =
    isset($pdo) && $pdo instanceof PDO
        ? school_sidebar_get_items(
            $pdo,
            $roleId,
            $tenantId
        )
        : [];

$parentAllowedMenuKeys = [];

foreach ($parentSidebarItems as $menu) {
    $key = (string)(
        $menu['menu_key']
        ?? ''
    );

    if ($key !== '') {
        $parentAllowedMenuKeys[$key] =
            true;
    }
}

$parentCan = static fn(string $key): bool =>
    isset(
        $parentAllowedMenuKeys[$key]
    );

if (!$parentCan('parent_profile')) {
    http_response_code(403);

    exit(
        'Profile is disabled for this Parent account.'
    );
}

$pageTitle = 'Profile';
$pageKey = 'parent_profile';

$sidebarFile =
    dirname(__DIR__)
    . '/school/sidebar.php';

require dirname(__DIR__)
    . '/includes/layout-start.php';
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
 * Results page content only.
 * Existing header/sidebar/card/button/font design is unchanged.
 */

.parent-results-page .result-filter-group{
    display:flex;
    align-items:center;
    gap:8px;
    flex-wrap:wrap;
}

.parent-results-page .result-summary-grid{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:10px;
    padding:16px;
}

.parent-results-page .result-summary-item{
    min-width:0;
    padding:11px 12px;
    background:#f8fafc;
    border:1px solid var(--border-soft,#e7ebf3);
    border-radius:10px;
}

.parent-results-page .result-summary-item span,
.parent-results-page .result-summary-item strong{
    display:block;
}

.parent-results-page .result-summary-item span{
    margin-bottom:4px;
    color:var(--text-muted,#64748b);
    font-size:8px;
    font-weight:600;
    text-transform:uppercase;
    letter-spacing:.03em;
}

.parent-results-page .result-summary-item strong{
    overflow:hidden;
    color:var(--text-main,#101a3b);
    font-size:10px;
    font-weight:700;
    text-overflow:ellipsis;
    white-space:nowrap;
}

.parent-results-page .result-status{
    display:inline-flex;
    align-items:center;
    min-height:23px;
    padding:4px 8px;
    border-radius:999px;
    font-size:8px;
    font-weight:700;
}

.parent-results-page .result-status.pass{
    color:#158458;
    background:#e9f8f1;
}

.parent-results-page .result-status.fail,
.parent-results-page .result-status.absent{
    color:#c83a55;
    background:#fff0f3;
}

.parent-results-page .result-status.pending{
    color:#b4770b;
    background:#fff6dd;
}

@media(max-width:991.98px){
    .parent-results-page .result-summary-grid{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }
}

@media(max-width:767.98px){
    .parent-results-page .page-actions{
        width:100%;
    }

    .parent-results-page .result-filter-group{
        width:100%;
    }

    .parent-results-page .parent-child-picker{
        width:100%;
        min-width:0;
    }
}

@media(max-width:575.98px){
    .parent-results-page .result-summary-grid{
        grid-template-columns:1fr;
    }
}

/*
 * Homework page content only.
 * Shared UI, font, header, sidebar, buttons and cards are unchanged.
 */

.parent-homework-page .homework-count{
    color:var(--text-muted,#64748b);
    font-size:10px;
    font-weight:600;
}

.parent-homework-page .homework-description{
    max-width:420px;
}

.parent-homework-page .homework-description strong,
.parent-homework-page .homework-description small{
    display:block;
}

.parent-homework-page .homework-description strong{
    color:var(--text-main,#101a3b);
    font-size:9.5px;
    font-weight:700;
}

.parent-homework-page .homework-description small{
    margin-top:4px;
    color:var(--text-muted,#64748b);
    font-size:8.5px;
    line-height:1.45;
    white-space:normal;
}

.parent-homework-page .attachment-list{
    display:flex;
    align-items:center;
    gap:5px;
    flex-wrap:wrap;
}

.parent-homework-page .attachment-list .btn-ui{
    min-height:30px;
    padding:5px 8px;
    font-size:8px;
}

@media(max-width:767.98px){
    .parent-homework-page .page-actions{
        width:100%;
    }

    .parent-homework-page .parent-child-picker{
        width:100%;
        min-width:0;
    }
}

/*
 * Notices page content only.
 * No shared font, color, spacing, header, sidebar, card or button changes.
 */

.parent-notices-page .notice-title-cell{
    min-width:190px;
}

.parent-notices-page .notice-title-cell strong,
.parent-notices-page .notice-title-cell small{
    display:block;
}

.parent-notices-page .notice-title-cell strong{
    color:var(--text-main,#101a3b);
    font-size:9.5px;
    font-weight:700;
}

.parent-notices-page .notice-title-cell small{
    margin-top:3px;
    color:var(--text-muted,#64748b);
    font-size:8px;
}

.parent-notices-page .notice-description{
    max-width:430px;
    overflow:hidden;
    display:-webkit-box;
    -webkit-box-orient:vertical;
    -webkit-line-clamp:2;
    color:var(--text-muted,#64748b);
    line-height:1.45;
}

.parent-notices-page .notice-actions,
.parent-notices-page .notice-attachments{
    display:flex;
    align-items:center;
    gap:5px;
    flex-wrap:wrap;
}

.parent-notices-page .notice-actions .btn-ui,
.parent-notices-page .notice-attachments .btn-ui{
    min-height:30px;
    padding:5px 8px;
    font-size:8px;
}

.parent-notices-page .notice-modal-body{
    display:grid;
    gap:14px;
}

.parent-notices-page .notice-modal-meta{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:10px;
}

.parent-notices-page .notice-modal-content{
    padding:13px;
    color:var(--text-main,#101a3b);
    background:#f8fafc;
    border:1px solid var(--border-soft,#e7ebf3);
    border-radius:10px;
    font-size:10px;
    line-height:1.65;
    white-space:pre-wrap;
    overflow-wrap:anywhere;
}

@media(max-width:767.98px){
    .parent-notices-page .page-actions{
        width:100%;
    }

    .parent-notices-page .parent-child-picker{
        width:100%;
        min-width:0;
    }

    .parent-notices-page .notice-modal-meta{
        grid-template-columns:1fr;
    }
}

/* Transport page content only. Shared UI remains unchanged. */
.parent-transport-page .transport-status-box{
    padding:18px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:14px;
}

.parent-transport-page .transport-status-main{
    min-width:0;
    display:flex;
    align-items:center;
    gap:12px;
}

.parent-transport-page .transport-status-icon{
    width:46px;
    height:46px;
    flex:0 0 46px;
    display:grid;
    place-items:center;
    color:#4f46e5;
    background:#eef2ff;
    border-radius:50%;
}

.parent-transport-page .transport-status-icon svg{
    width:21px;
    height:21px;
}

.parent-transport-page .transport-status-copy strong,
.parent-transport-page .transport-status-copy small{
    display:block;
}

.parent-transport-page .transport-status-copy strong{
    color:var(--text-main,#101a3b);
    font-size:12px;
    font-weight:700;
}

.parent-transport-page .transport-status-copy small{
    margin-top:4px;
    color:var(--text-muted,#64748b);
    font-size:9px;
}

.parent-transport-page .transport-detail-grid{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:10px;
    padding:16px;
}

.parent-transport-page .transport-not-assigned{
    padding:38px 18px;
    text-align:center;
}

.parent-transport-page .transport-not-assigned svg{
    width:32px;
    height:32px;
    margin-bottom:10px;
    color:var(--text-muted,#64748b);
}

.parent-transport-page .transport-not-assigned strong,
.parent-transport-page .transport-not-assigned small{
    display:block;
}

.parent-transport-page .transport-not-assigned strong{
    color:var(--text-main,#101a3b);
    font-size:12px;
    font-weight:700;
}

.parent-transport-page .transport-not-assigned small{
    margin-top:5px;
    color:var(--text-muted,#64748b);
    font-size:9px;
}

@media(max-width:991.98px){
    .parent-transport-page .transport-detail-grid{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }
}

@media(max-width:767.98px){
    .parent-transport-page .page-actions{
        width:100%;
    }

    .parent-transport-page .parent-child-picker{
        width:100%;
        min-width:0;
    }

    .parent-transport-page .transport-status-box{
        align-items:flex-start;
        flex-direction:column;
    }
}

@media(max-width:575.98px){
    .parent-transport-page .transport-detail-grid{
        grid-template-columns:1fr;
    }
}

/*
 * My Children page content only.
 * Shared Parent Dashboard UI is unchanged.
 */

.parent-my-children-page .children-count{
    color:var(--text-muted,#64748b);
    font-size:10px;
    font-weight:600;
}

.parent-my-children-page .child-name-cell{
    min-width:180px;
    display:flex;
    align-items:center;
    gap:10px;
}

.parent-my-children-page .child-avatar{
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

.parent-my-children-page .child-avatar img{
    width:100%;
    height:100%;
    object-fit:cover;
}

.parent-my-children-page .child-name-copy{
    min-width:0;
}

.parent-my-children-page .child-name-copy strong,
.parent-my-children-page .child-name-copy small{
    display:block;
}

.parent-my-children-page .child-name-copy strong{
    overflow:hidden;
    color:var(--text-main,#101a3b);
    font-size:10px;
    font-weight:700;
    text-overflow:ellipsis;
    white-space:nowrap;
}

.parent-my-children-page .child-name-copy small{
    margin-top:3px;
    color:var(--text-muted,#64748b);
    font-size:8px;
}

.parent-my-children-page .child-actions{
    display:flex;
    align-items:center;
    gap:6px;
}

.parent-my-children-page .child-actions .btn-ui{
    min-height:30px;
    padding:5px 8px;
    font-size:8px;
}

.parent-my-children-page .child-detail-header{
    display:flex;
    align-items:center;
    gap:12px;
    padding-bottom:14px;
    margin-bottom:14px;
    border-bottom:1px solid var(--border-soft,#e7ebf3);
}

.parent-my-children-page .child-detail-header .child-avatar{
    width:48px;
    height:48px;
    flex-basis:48px;
    font-size:14px;
}

.parent-my-children-page .child-detail-copy strong,
.parent-my-children-page .child-detail-copy small{
    display:block;
}

.parent-my-children-page .child-detail-copy strong{
    color:var(--text-main,#101a3b);
    font-size:13px;
    font-weight:700;
}

.parent-my-children-page .child-detail-copy small{
    margin-top:4px;
    color:var(--text-muted,#64748b);
    font-size:9px;
}

.parent-my-children-page .child-detail-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:10px;
}

@media(max-width:575.98px){
    .parent-my-children-page .child-detail-grid{
        grid-template-columns:1fr;
    }
}

/*
 * Profile page content only.
 * Existing Parent Dashboard UI remains unchanged.
 */

.parent-profile-page .profile-main-grid{
    display:grid;
    grid-template-columns:minmax(0,1.15fr) minmax(340px,.85fr);
    gap:16px;
    align-items:start;
}

.parent-profile-page .profile-detail-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:10px;
    padding:16px;
}

.parent-profile-page .profile-address{
    grid-column:1/-1;
}

.parent-profile-page .profile-actions{
    display:flex;
    justify-content:flex-end;
    gap:7px;
    padding:0 16px 16px;
}

.parent-profile-page .profile-form-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:12px;
}

.parent-profile-page .profile-form-grid .full{
    grid-column:1/-1;
}

.parent-profile-page .profile-modal-note{
    margin-top:5px;
    color:var(--text-muted,#64748b);
    font-size:8px;
    line-height:1.45;
}

.parent-profile-page .profile-child-list{
    display:grid;
    padding:5px 16px 10px;
}

.parent-profile-page .profile-child{
    padding:11px 0;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    border-bottom:1px solid var(--border-soft,#e7ebf3);
}

.parent-profile-page .profile-child:last-child{
    border-bottom:0;
}

.parent-profile-page .profile-child-copy strong,
.parent-profile-page .profile-child-copy small{
    display:block;
}

.parent-profile-page .profile-child-copy strong{
    color:var(--text-main,#101a3b);
    font-size:10px;
    font-weight:700;
}

.parent-profile-page .profile-child-copy small{
    margin-top:3px;
    color:var(--text-muted,#64748b);
    font-size:8px;
}

.parent-profile-page .profile-message{
    display:none;
    padding:11px 13px;
    border-radius:10px;
    font-size:9px;
}

.parent-profile-page .profile-message.show{
    display:block;
}

.parent-profile-page .profile-message.success{
    color:#166534;
    background:#ecfdf3;
    border:1px solid #bbf7d0;
}

.parent-profile-page .profile-message.error{
    color:#a4394d;
    background:#fff0f3;
    border:1px solid #ffd7df;
}

@media(max-width:991.98px){
    .parent-profile-page .profile-main-grid{
        grid-template-columns:1fr;
    }
}

@media(max-width:575.98px){
    .parent-profile-page .profile-detail-grid,
    .parent-profile-page .profile-form-grid{
        grid-template-columns:1fr;
    }

    .parent-profile-page .profile-address,
    .parent-profile-page .profile-form-grid .full{
        grid-column:auto;
    }

    .parent-profile-page .profile-actions{
        flex-direction:column;
    }

    .parent-profile-page .profile-actions .btn-ui{
        width:100%;
        justify-content:center;
    }
}
</style>

<div id="loading" class="parent-dashboard-loading">
    <div>Loading Profile...</div>
</div>

<div class="dashboard-page parent-dashboard parent-profile-page">
    <div class="page-heading">
        <div>
            <h1 class="page-title">
                Profile
            </h1>

            <p class="page-subtitle">
                View and manage your Parent account information.
            </p>
        </div>
    </div>

    <div
        id="profileMessage"
        class="profile-message"
    ></div>

    <section class="profile-main-grid">
        <article class="dashboard-card">
            <div class="dashboard-card-header">
                <h2>Parent Details</h2>

                <span
                    id="accountStatus"
                    class="dashboard-card-subtitle"
                >
                    Parent Account
                </span>
            </div>

            <div
                id="profileDetailGrid"
                class="profile-detail-grid"
            ></div>

            <div class="profile-actions">
                <button
                    id="editProfileButton"
                    type="button"
                    class="btn-ui"
                >
                    <i data-lucide="pencil"></i>
                    Edit Profile
                </button>

                <button
                    id="changePasswordButton"
                    type="button"
                    class="btn-ui btn-primary-ui"
                >
                    <i data-lucide="key-round"></i>
                    Change Password
                </button>
            </div>
        </article>

        <article class="dashboard-card">
            <div class="dashboard-card-header">
                <h2>Linked Children</h2>

                <span
                    id="childrenCount"
                    class="dashboard-card-subtitle"
                >
                    0 Children
                </span>
            </div>

            <div
                id="childrenList"
                class="profile-child-list"
            ></div>
        </article>
    </section>

    <div
        class="modal fade"
        id="editProfileModal"
        tabindex="-1"
        aria-hidden="true"
    >
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <form id="editProfileForm">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title">
                                Edit Profile
                            </h5>

                            <small class="text-muted">
                                Update your existing Parent account details.
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
                        <div class="profile-form-grid">
                            <div>
                                <label class="form-label">
                                    Parent Name
                                </label>

                                <input
                                    id="editParentName"
                                    class="form-control"
                                    maxlength="150"
                                    required
                                >
                            </div>

                            <div>
                                <label class="form-label">
                                    Username
                                </label>

                                <input
                                    id="editUsername"
                                    class="form-control"
                                    maxlength="100"
                                    required
                                >
                            </div>

                            <div>
                                <label class="form-label">
                                    Email
                                </label>

                                <input
                                    id="editEmail"
                                    class="form-control"
                                    type="email"
                                    maxlength="150"
                                >
                            </div>

                            <div>
                                <label class="form-label">
                                    Mobile Number
                                </label>

                                <input
                                    id="editMobile"
                                    class="form-control"
                                    inputmode="numeric"
                                    maxlength="15"
                                >
                            </div>

                            <div>
                                <label class="form-label">
                                    Relationship
                                </label>

                                <input
                                    id="editRelationship"
                                    class="form-control"
                                    readonly
                                >

                                <div class="profile-modal-note">
                                    Relationship is linked to the student record and is view-only here.
                                </div>
                            </div>

                            <div>
                                <label class="form-label">
                                    Occupation
                                </label>

                                <input
                                    id="editOccupation"
                                    class="form-control"
                                    maxlength="150"
                                >
                            </div>

                            <div class="full">
                                <label class="form-label">
                                    Address
                                </label>

                                <textarea
                                    id="editAddress"
                                    class="form-control"
                                    rows="3"
                                    maxlength="1000"
                                ></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button
                            type="button"
                            class="btn-ui"
                            data-bs-dismiss="modal"
                        >
                            Cancel
                        </button>

                        <button
                            id="saveProfileButton"
                            type="submit"
                            class="btn-ui btn-primary-ui"
                        >
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div
        class="modal fade"
        id="changePasswordModal"
        tabindex="-1"
        aria-hidden="true"
    >
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="changePasswordForm">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title">
                                Change Password
                            </h5>

                            <small class="text-muted">
                                Current password verification is required.
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
                        <div class="profile-form-grid">
                            <div class="full">
                                <label class="form-label">
                                    Current Password
                                </label>

                                <input
                                    id="currentPassword"
                                    type="password"
                                    class="form-control"
                                    autocomplete="current-password"
                                    required
                                >
                            </div>

                            <div class="full">
                                <label class="form-label">
                                    New Password
                                </label>

                                <input
                                    id="newPassword"
                                    type="password"
                                    class="form-control"
                                    minlength="8"
                                    maxlength="72"
                                    autocomplete="new-password"
                                    required
                                >
                            </div>

                            <div class="full">
                                <label class="form-label">
                                    Confirm Password
                                </label>

                                <input
                                    id="confirmPassword"
                                    type="password"
                                    class="form-control"
                                    minlength="8"
                                    maxlength="72"
                                    autocomplete="new-password"
                                    required
                                >

                                <div class="profile-modal-note">
                                    Password must be 8–72 characters and contain at least one letter and one number.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button
                            type="button"
                            class="btn-ui"
                            data-bs-dismiss="modal"
                        >
                            Cancel
                        </button>

                        <button
                            id="savePasswordButton"
                            type="submit"
                            class="btn-ui btn-primary-ui"
                        >
                            Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    'use strict';

    const $ = id =>
        document.getElementById(id);

    const apiUrl =
        'api/profile.php';

    const esc = value =>
        String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');

    const display = value => {
        const text =
            String(value ?? '').trim();

        return text !== ''
            ? text
            : '—';
    };

    let profile = {};
    let children = [];
    let csrfToken = '';

    function setLoading(show) {
        $('loading').style.display =
            show ? 'grid' : 'none';
    }

    function showMessage(
        message = '',
        type = 'success'
    ) {
        const box =
            $('profileMessage');

        box.textContent = message;

        box.className =
            `profile-message ${type} ${
                message ? 'show' : ''
            }`;
    }

    async function parseResponse(
        response
    ) {
        const text =
            await response.text();

        let payload;

        try {
            payload =
                JSON.parse(text);
        } catch (error) {
            throw new Error(
                'Parent Profile API returned an invalid response.'
            );
        }

        if (
            !response.ok
            || !payload.success
        ) {
            throw new Error(
                payload.message
                || 'Unable to process Profile.'
            );
        }

        return payload;
    }

    async function getProfile() {
        const url = new URL(
            apiUrl,
            window.location.href
        );

        url.searchParams.set(
            'action',
            'profile'
        );

        return parseResponse(
            await fetch(
                url,
                {
                    credentials:
                        'same-origin',
                    headers: {
                        'Accept':
                            'application/json'
                    }
                }
            )
        );
    }

    async function postAction(
        action,
        data
    ) {
        return parseResponse(
            await fetch(
                apiUrl,
                {
                    method: 'POST',
                    credentials:
                        'same-origin',
                    headers: {
                        'Content-Type':
                            'application/json',
                        'Accept':
                            'application/json'
                    },
                    body: JSON.stringify({
                        action,
                        csrf_token:
                            csrfToken,
                        ...data
                    })
                }
            )
        );
    }

    function renderProfile() {
        const fields = [
            [
                'Parent Name',
                profile.parent_name
            ],
            [
                'Username',
                profile.username
            ],
            [
                'Email',
                profile.email
            ],
            [
                'Mobile Number',
                profile.mobile
            ],
            [
                'Relationship',
                profile.relationship
            ],
            [
                'Occupation',
                profile.occupation
            ],
            [
                'Address',
                profile.address,
                true
            ]
        ];

        $('profileDetailGrid').innerHTML =
            fields.map(
                ([label, value, full]) => `
                    <div
                        class="info ${
                            full
                                ? 'profile-address'
                                : ''
                        }"
                    >
                        <span>
                            ${esc(label)}
                        </span>

                        <strong
                            title="${esc(
                                display(value)
                            )}"
                        >
                            ${esc(
                                display(value)
                            )}
                        </strong>
                    </div>
                `
            ).join('');

        $('accountStatus').textContent =
            profile.account_status
                ? `Account: ${
                    profile.account_status
                }`
                : 'Parent Account';
    }

    function renderChildren() {
        $('childrenCount').textContent =
            `${children.length} ${
                children.length === 1
                    ? 'Child'
                    : 'Children'
            }`;

        $('childrenList').innerHTML =
            children.length
                ? children.map(
                    child => `
                        <div class="profile-child">
                            <div class="profile-child-copy">
                                <strong>
                                    ${esc(
                                        display(
                                            child.student_name
                                        )
                                    )}
                                </strong>

                                <small>
                                    ${esc(
                                        display(
                                            child.admission_no
                                        )
                                    )}
                                    ·
                                    ${esc(
                                        display(
                                            child.class_name
                                        )
                                    )}
                                    ${
                                        child.section_name
                                            ? `· Section ${esc(
                                                child.section_name
                                            )}`
                                            : ''
                                    }
                                </small>
                            </div>

                            <span class="badge neutral">
                                ${esc(
                                    display(
                                        child.academic_year_name
                                    )
                                )}
                            </span>
                        </div>
                    `
                ).join('')
                : `
                    <div class="empty">
                        No students are linked to this Parent account.
                    </div>
                `;
    }

    function fillEditForm() {
        $('editParentName').value =
            profile.parent_name || '';

        $('editUsername').value =
            profile.username || '';

        $('editEmail').value =
            profile.email || '';

        $('editMobile').value =
            profile.mobile || '';

        $('editRelationship').value =
            profile.relationship || '';

        $('editOccupation').value =
            profile.occupation || '';

        $('editAddress').value =
            profile.address || '';
    }

    async function load() {
        setLoading(true);
        showMessage('');

        try {
            const response =
                await getProfile();

            const data =
                response.data || {};

            profile =
                data.profile || {};

            children =
                Array.isArray(data.children)
                    ? data.children
                    : [];

            csrfToken =
                data.csrf_token || '';

            renderProfile();
            renderChildren();

            window.lucide?.createIcons();
        } catch (error) {
            showMessage(
                error.message
                || 'Unable to load Profile.',
                'error'
            );
        } finally {
            setLoading(false);
        }
    }

    $('editProfileButton')
        .addEventListener(
            'click',
            () => {
                fillEditForm();

                window.bootstrap?.Modal
                    .getOrCreateInstance(
                        $('editProfileModal')
                    )
                    .show();
            }
        );

    $('changePasswordButton')
        .addEventListener(
            'click',
            () => {
                $('changePasswordForm')
                    .reset();

                window.bootstrap?.Modal
                    .getOrCreateInstance(
                        $('changePasswordModal')
                    )
                    .show();
            }
        );

    $('editProfileForm')
        .addEventListener(
            'submit',
            async event => {
                event.preventDefault();

                const name =
                    $('editParentName')
                        .value.trim();

                const username =
                    $('editUsername')
                        .value.trim();

                const email =
                    $('editEmail')
                        .value.trim();

                const mobile =
                    $('editMobile')
                        .value
                        .replace(/\D+/g, '');

                if (name.length < 2) {
                    showMessage(
                        'Enter a valid Parent Name.',
                        'error'
                    );
                    return;
                }

                if (
                    !/^[A-Za-z0-9._-]{3,100}$/
                        .test(username)
                ) {
                    showMessage(
                        'Enter a valid Username.',
                        'error'
                    );
                    return;
                }

                if (
                    email
                    && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/
                        .test(email)
                ) {
                    showMessage(
                        'Enter a valid Email Address.',
                        'error'
                    );
                    return;
                }

                if (
                    mobile
                    && (
                        mobile.length < 10
                        || mobile.length > 15
                    )
                ) {
                    showMessage(
                        'Enter a valid Mobile Number.',
                        'error'
                    );
                    return;
                }

                const button =
                    $('saveProfileButton');

                button.disabled = true;

                try {
                    const response =
                        await postAction(
                            'save_profile',
                            {
                                parent_name:
                                    name,
                                username,
                                email,
                                mobile,
                                occupation:
                                    $('editOccupation')
                                        .value
                                        .trim(),
                                address:
                                    $('editAddress')
                                        .value
                                        .trim()
                            }
                        );

                    const data =
                        response.data || {};

                    profile =
                        data.profile
                        || profile;

                    children =
                        Array.isArray(
                            data.children
                        )
                            ? data.children
                            : children;

                    csrfToken =
                        data.csrf_token
                        || csrfToken;

                    renderProfile();
                    renderChildren();

                    window.bootstrap?.Modal
                        .getInstance(
                            $('editProfileModal')
                        )
                        ?.hide();

                    showMessage(
                        response.message
                        || 'Profile updated successfully.',
                        'success'
                    );
                } catch (error) {
                    showMessage(
                        error.message,
                        'error'
                    );
                } finally {
                    button.disabled = false;
                    window.lucide?.createIcons();
                }
            }
        );

    $('changePasswordForm')
        .addEventListener(
            'submit',
            async event => {
                event.preventDefault();

                const currentPassword =
                    $('currentPassword')
                        .value;

                const newPassword =
                    $('newPassword')
                        .value;

                const confirmPassword =
                    $('confirmPassword')
                        .value;

                if (
                    newPassword.length < 8
                    || newPassword.length > 72
                    || !/[A-Za-z]/.test(
                        newPassword
                    )
                    || !/\d/.test(
                        newPassword
                    )
                ) {
                    showMessage(
                        'New password must be 8–72 characters and contain at least one letter and one number.',
                        'error'
                    );
                    return;
                }

                if (
                    newPassword
                    !== confirmPassword
                ) {
                    showMessage(
                        'New Password and Confirm Password do not match.',
                        'error'
                    );
                    return;
                }

                const button =
                    $('savePasswordButton');

                button.disabled = true;

                try {
                    const response =
                        await postAction(
                            'change_password',
                            {
                                current_password:
                                    currentPassword,
                                new_password:
                                    newPassword,
                                confirm_password:
                                    confirmPassword
                            }
                        );

                    csrfToken =
                        response.data
                            ?.csrf_token
                        || csrfToken;

                    $('changePasswordForm')
                        .reset();

                    window.bootstrap?.Modal
                        .getInstance(
                            $('changePasswordModal')
                        )
                        ?.hide();

                    showMessage(
                        response.message
                        || 'Password changed successfully.',
                        'success'
                    );
                } catch (error) {
                    showMessage(
                        error.message,
                        'error'
                    );
                } finally {
                    button.disabled = false;
                }
            }
        );

    load();
})();
</script>

<?php
require dirname(__DIR__)
    . '/includes/layout-end.php';
?>

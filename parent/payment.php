<?php
declare(strict_types=1);

/*
 * Parent - Fee Payment
 * Location: parent/fee_payment.php
 * Build: 2026-08-13-parent-fee-payment-v32
 *
 * Uses:
 * - parent/api/dashboard.php for linked children
 * - parent/api/fee_payment.php as the secure façade
 * - existing api/fee-collection.php for fee detail/history/receipts
 * - existing api/online-payment.php for Razorpay initiation/verification
 */

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/sidebar-manager.php';
require_once dirname(__DIR__) . '/includes/permission-chain.php';

$user=function_exists('current_user')
    ?current_user()
    :[];
$user=is_array($user)?$user:[];

$tenantId=(int)(
    $user['tenant_id']
    ??$_SESSION['tenant_id']
    ??$_SESSION['school_id']
    ??0
);

$roleId=(int)(
    $user['role_id']
    ??$_SESSION['role_id']
    ??0
);

$role=isset($pdo)&&$pdo instanceof PDO
    ?pc_role($pdo,$tenantId,$roleId)
    :[];

if(
    pc_role_key(
        (string)($role['role_key']??'')
    )!=='parent'
){
    http_response_code(403);
    exit(
        'Fee Payment is available only to Parent accounts.'
    );
}

$parentSidebarItems=
    isset($pdo)&&$pdo instanceof PDO
        ?school_sidebar_get_items(
            $pdo,
            $roleId,
            $tenantId
        )
        :[];

$parentAllowedMenuKeys=[];
foreach($parentSidebarItems as $menu){
    $key=(string)($menu['menu_key']??'');
    if($key!==''){
        $parentAllowedMenuKeys[$key]=true;
    }
}

$parentCan=static fn(string $key):bool=>
    isset($parentAllowedMenuKeys[$key]);

if(!$parentCan('parent_fee_payment')){
    http_response_code(403);
    exit(
        'Fee Payment is disabled for this Parent account.'
    );
}

$pageTitle='Fee Payment';
$pageKey='parent_fee_payment';
$sidebarFile=dirname(__DIR__).'/school/sidebar.php';

require dirname(__DIR__)
    .'/includes/layout-start.php';
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
   PARENT FEES PAGE
   Only page content styles.
   Header/sidebar/navbar are the existing shared ERP layout.
   ========================================================= */

.parent-fees-page{
    display:grid;
    gap:16px;
}

.parent-fees-page .parent-child-picker{
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

.parent-fees-page .parent-child-picker svg{
    width:16px;
    height:16px;
    color:var(--text-muted,#64748b);
}

.parent-fees-page .parent-child-picker select{
    width:100%;
    min-width:0;
    border:0;
    outline:0;
    background:transparent;
    color:var(--text-main,#101a3b);
    font-size:10px;
    font-weight:700;
}

.parent-fees-page .student-context{
    padding:15px 17px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:14px;
}

.parent-fees-page .student-context-main{
    min-width:0;
    display:flex;
    align-items:center;
    gap:12px;
}

.parent-fees-page .student-avatar{
    width:46px;
    height:46px;
    flex:0 0 46px;
    display:grid;
    place-items:center;
    overflow:hidden;
    color:#fff;
    background:linear-gradient(135deg,#7548ee,#5033d5);
    border-radius:50%;
    font-size:13px;
    font-weight:800;
}

.parent-fees-page .student-avatar img{
    width:100%;
    height:100%;
    object-fit:cover;
}

.parent-fees-page .student-context-copy{
    min-width:0;
}

.parent-fees-page .student-context-copy strong,
.parent-fees-page .student-context-copy small{
    display:block;
}

.parent-fees-page .student-context-copy strong{
    overflow:hidden;
    color:var(--text-main,#101a3b);
    font-size:12px;
    font-weight:700;
    text-overflow:ellipsis;
    white-space:nowrap;
}

.parent-fees-page .student-context-copy small{
    margin-top:4px;
    color:var(--text-muted,#64748b);
    font-size:9px;
}

.parent-fees-page .fee-status-pill{
    display:inline-flex;
    align-items:center;
    min-height:25px;
    padding:4px 9px;
    border-radius:999px;
    font-size:8px;
    font-weight:800;
    text-transform:capitalize;
}

.parent-fees-page .fee-status-pill.paid{
    color:#158458;
    background:#e9f8f1;
}

.parent-fees-page .fee-status-pill.partial{
    color:#b4770b;
    background:#fff6dd;
}

.parent-fees-page .fee-status-pill.unpaid,
.parent-fees-page .fee-status-pill.overdue{
    color:#c83a55;
    background:#fff0f3;
}

.parent-fees-page .fee-status-pill.not_assigned{
    color:#65728b;
    background:#f0f3f8;
}

.parent-fees-table-wrap{
    overflow:auto;
}

.parent-fees-table{
    width:100%;
    min-width:930px;
    border-collapse:collapse;
}

.parent-fees-table th,
.parent-fees-table td{
    padding:11px 13px;
    border-bottom:1px solid var(--border-soft,#e7ebf3);
    text-align:left;
    vertical-align:middle;
}

.parent-fees-table th{
    color:var(--text-muted,#64748b);
    background:#f8fafc;
    font-size:8px;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:.035em;
}

.parent-fees-table td{
    color:var(--text-main,#101a3b);
    font-size:9.5px;
    font-weight:500;
}

.parent-fees-table tbody tr:last-child td{
    border-bottom:0;
}

.parent-fees-table .amount{
    font-weight:800;
    white-space:nowrap;
}

.parent-fees-page .fee-type{
    display:inline-flex;
    align-items:center;
    gap:7px;
    font-weight:700;
}

.parent-fees-page .fee-type-icon{
    width:28px;
    height:28px;
    display:grid;
    place-items:center;
    color:#4f46e5;
    background:#eef2ff;
    border-radius:8px;
}

.parent-fees-page .fee-type-icon svg{
    width:14px;
    height:14px;
}

.parent-fees-page .fee-detail-grid{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:10px;
    padding:16px;
}

.parent-fees-page .fee-detail{
    min-width:0;
    padding:11px 12px;
    background:#f8fafc;
    border:1px solid var(--border-soft,#e7ebf3);
    border-radius:10px;
}

.parent-fees-page .fee-detail span,
.parent-fees-page .fee-detail strong{
    display:block;
}

.parent-fees-page .fee-detail span{
    margin-bottom:4px;
    color:var(--text-muted,#64748b);
    font-size:8px;
    font-weight:600;
    text-transform:uppercase;
}

.parent-fees-page .fee-detail strong{
    overflow:hidden;
    color:var(--text-main,#101a3b);
    font-size:10px;
    font-weight:700;
    text-overflow:ellipsis;
    white-space:nowrap;
}

.parent-fees-page .receipt-list{
    display:grid;
    padding:4px 16px 10px;
}

.parent-fees-page .receipt-item{
    padding:11px 0;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    border-bottom:1px solid var(--border-soft,#e7ebf3);
}

.parent-fees-page .receipt-item:last-child{
    border-bottom:0;
}

.parent-fees-page .receipt-item strong,
.parent-fees-page .receipt-item small{
    display:block;
}

.parent-fees-page .receipt-item strong{
    font-size:9.5px;
    font-weight:700;
}

.parent-fees-page .receipt-item small{
    margin-top:3px;
    color:var(--text-muted,#64748b);
    font-size:8px;
}

.parent-fees-page .empty{
    padding:28px 14px;
    color:var(--text-muted,#64748b);
    font-size:9px;
    text-align:center;
}

.parent-fees-page .page-error{
    display:none;
    padding:11px 13px;
    color:#a4394d;
    background:#fff0f3;
    border:1px solid #ffd7df;
    border-radius:10px;
    font-size:9px;
}

.parent-fees-page .page-error.show{
    display:block;
}

.parent-fees-loading{
    position:fixed;
    inset:0;
    z-index:5000;
    display:grid;
    place-items:center;
    background:rgba(245,247,251,.84);
    backdrop-filter:blur(2px);
}

.parent-fees-loading>div{
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
    .parent-fees-page .fee-detail-grid{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }
}

@media(max-width:767.98px){
    .parent-fees-page .page-actions{
        width:100%;
    }

    .parent-fees-page .parent-child-picker{
        width:100%;
        min-width:0;
    }

    .parent-fees-page .student-context{
        align-items:flex-start;
        flex-direction:column;
    }

    .parent-fees-page .fee-detail-grid{
        grid-template-columns:1fr;
    }
}

/* Fee Payment content only; shared ERP UI is unchanged. */
.parent-payment-page{
    display:grid;
    gap:16px;
}

.parent-payment-grid{
    display:grid;
    grid-template-columns:minmax(0,1.45fr) minmax(300px,.8fr);
    gap:16px;
    align-items:start;
}

.payment-form-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:12px;
    padding:16px;
}

.payment-form-grid .full{
    grid-column:1/-1;
}

.payment-help{
    margin-top:5px;
    color:var(--text-muted,#64748b);
    font-size:8px;
    line-height:1.45;
}

.payment-current-box{
    padding:16px;
    display:grid;
    gap:10px;
}

.payment-balance-row{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    padding:11px 12px;
    background:#f8fafc;
    border:1px solid var(--border-soft,#e7ebf3);
    border-radius:10px;
}

.payment-balance-row span{
    color:var(--text-muted,#64748b);
    font-size:9px;
    font-weight:600;
}

.payment-balance-row strong{
    color:var(--text-main,#101a3b);
    font-size:11px;
    font-weight:800;
}

.payment-gateway-note{
    padding:11px 12px;
    border-radius:10px;
    font-size:9px;
    line-height:1.5;
}

.payment-gateway-note.ready{
    color:#166534;
    background:#ecfdf3;
    border:1px solid #bbf7d0;
}

.payment-gateway-note.missing{
    color:#92400e;
    background:#fffbeb;
    border:1px solid #fde68a;
}

.payment-submit-row{
    display:flex;
    justify-content:flex-end;
    gap:8px;
    padding:0 16px 16px;
}

.payment-history-actions{
    display:flex;
    gap:6px;
    flex-wrap:wrap;
}

.payment-history-actions .btn-ui{
    min-height:30px;
    padding:5px 8px;
    font-size:8px;
}

.payment-success{
    display:none;
    padding:12px 14px;
    color:#166534;
    background:#ecfdf3;
    border:1px solid #bbf7d0;
    border-radius:10px;
    font-size:9px;
}

.payment-success.show{
    display:block;
}

@media(max-width:991.98px){
    .parent-payment-grid{
        grid-template-columns:1fr;
    }
}

@media(max-width:767.98px){
    .payment-form-grid{
        grid-template-columns:1fr;
    }

    .payment-form-grid .full{
        grid-column:auto;
    }

    .payment-submit-row .btn-ui{
        width:100%;
        justify-content:center;
    }
}
</style>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>

<div id="paymentLoading" class="parent-fees-loading">
    <div>Loading Fee Payment...</div>
</div>

<div class="dashboard-page parent-dashboard parent-fees-page parent-payment-page">
    <div class="page-heading">
        <div>
            <h1 class="page-title">Fee Payment</h1>
            <p class="page-subtitle">
                Pay your child’s outstanding school fees securely.
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

    <div id="paymentError" class="page-error"></div>
    <div id="paymentSuccess" class="payment-success"></div>

    <section class="metric-grid">
        <article class="metric-card metric-purple">
            <div class="metric-icon">
                <i data-lucide="wallet-cards"></i>
            </div>
            <div>
                <small>Total Fee</small>
                <div id="totalFeeKpi" class="metric-value">₹0</div>
                <div id="feeStructureKpi" class="metric-trend">Fee Structure</div>
            </div>
        </article>

        <article class="metric-card metric-blue">
            <div class="metric-icon">
                <i data-lucide="badge-indian-rupee"></i>
            </div>
            <div>
                <small>Paid Amount</small>
                <div id="paidAmountKpi" class="metric-value">₹0</div>
                <div class="metric-trend">Already paid</div>
            </div>
        </article>

        <article class="metric-card metric-orange">
            <div class="metric-icon">
                <i data-lucide="history"></i>
            </div>
            <div>
                <small>Previous Balance</small>
                <div id="previousBalanceKpi" class="metric-value">₹0</div>
                <div class="metric-trend">Past due items</div>
            </div>
        </article>

        <article class="metric-card metric-green">
            <div class="metric-icon">
                <i data-lucide="circle-alert"></i>
            </div>
            <div>
                <small>Current Balance</small>
                <div id="currentBalanceKpi" class="metric-value">₹0</div>
                <div id="currentBalanceNote" class="metric-trend">Outstanding</div>
            </div>
        </article>
    </section>

    <section class="parent-payment-grid">
        <article class="dashboard-card">
            <div class="dashboard-card-header">
                <h2>Outstanding Fees</h2>
                <span class="dashboard-card-subtitle">
                    Existing fee collection schedule
                </span>
            </div>

            <div class="parent-fees-table-wrap">
                <table class="parent-fees-table">
                    <thead>
                        <tr>
                            <th>Fee Type</th>
                            <th>Total Fee</th>
                            <th>Paid Amount</th>
                            <th>Previous Balance</th>
                            <th>Current Balance</th>
                        </tr>
                    </thead>
                    <tbody id="outstandingRows"></tbody>
                </table>
            </div>
        </article>

        <article class="dashboard-card">
            <div class="dashboard-card-header">
                <h2>Make Payment</h2>
                <span class="dashboard-card-subtitle">
                    Secure Online Payment
                </span>
            </div>

            <form id="paymentForm">
                <div class="payment-form-grid">
                    <div>
                        <label class="form-label">Payment Type</label>
                        <select id="paymentType" class="form-select">
                            <option value="partial">Partial Payment</option>
                            <option value="full">Full Outstanding</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label">Payment Method</label>
                        <select id="paymentMethod" class="form-select">
                            <option value="upi">UPI</option>
                            <option value="card">Card</option>
                            <option value="netbanking">Net Banking</option>
                            <option value="wallet">Wallet</option>
                        </select>
                    </div>

                    <div class="full">
                        <label class="form-label">Payment Amount</label>
                        <input
                            id="paymentAmount"
                            class="form-control"
                            type="number"
                            min="1"
                            step="0.01"
                            placeholder="Enter amount"
                        >
                        <div id="paymentAmountHelp" class="payment-help">
                            Enter an amount up to the current outstanding balance.
                        </div>
                    </div>

                    <div class="full">
                        <label class="form-label">Payer Notes</label>
                        <textarea
                            id="payerNotes"
                            class="form-control"
                            rows="2"
                            maxlength="500"
                            placeholder="Optional"
                        ></textarea>
                    </div>

                    <div class="full">
                        <div id="gatewayNote" class="payment-gateway-note missing">
                            Checking online payment gateway...
                        </div>
                    </div>
                </div>

                <div class="payment-submit-row">
                    <button
                        id="payButton"
                        type="submit"
                        class="btn-ui btn-primary-ui"
                    >
                        <i data-lucide="lock-keyhole"></i>
                        Proceed Securely
                    </button>
                </div>
            </form>
        </article>
    </section>

    <article class="dashboard-card">
        <div class="dashboard-card-header">
            <h2>Previous Payment History</h2>
            <span class="dashboard-card-subtitle">
                Fee receipts for the selected child
            </span>
        </div>

        <div class="parent-fees-table-wrap">
            <table class="parent-fees-table">
                <thead>
                    <tr>
                        <th>Receipt No</th>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Payment Mode</th>
                        <th>Status</th>
                        <th>Receipt</th>
                    </tr>
                </thead>
                <tbody id="paymentHistoryRows"></tbody>
            </table>
        </div>
    </article>
</div>

<script>
(function(){
'use strict';

const dashboardApi=new URL(
    'api/dashboard.php',
    window.location.href
).href;

const paymentApi=new URL(
    'api/fee_payment.php',
    window.location.href
).href;

const $=id=>document.getElementById(id);

const esc=value=>String(value??'')
    .replace(/&/g,'&amp;')
    .replace(/</g,'&lt;')
    .replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;')
    .replace(/'/g,'&#039;');

const money=value=>'₹'+new Intl.NumberFormat(
    'en-IN',
    {maximumFractionDigits:0}
).format(Number(value||0));

const display=value=>{
    const text=String(value??'').trim();
    return text!==''?text:'—';
};

const initials=name=>String(name||'S')
    .split(/\s+/)
    .filter(Boolean)
    .slice(0,2)
    .map(x=>x.charAt(0).toUpperCase())
    .join('')||'S';

let csrfToken='';
let gateway={name:'razorpay',configured:false};
let children=[];
let selectedChild={};
let detail=null;
let history=null;
let assignmentId=0;

function loading(show){
    $('paymentLoading').style.display=show?'grid':'none';
}

function error(message=''){
    $('paymentError').textContent=message;
    $('paymentError').classList.toggle(
        'show',
        Boolean(message)
    );
}

function success(message=''){
    $('paymentSuccess').textContent=message;
    $('paymentSuccess').classList.toggle(
        'show',
        Boolean(message)
    );
}

async function jsonResponse(response){
    const text=await response.text();
    let data;
    try{
        data=JSON.parse(text);
    }catch{
        throw new Error(
            text.replace(/\s+/g,' ').trim().slice(0,220)
            ||'Invalid server response.'
        );
    }
    if(!response.ok||!data.success){
        throw new Error(
            data.message||`HTTP ${response.status}`
        );
    }
    return data;
}

async function getPayment(action,params={}){
    const url=new URL(paymentApi);
    url.searchParams.set('action',action);
    Object.entries(params).forEach(([key,value])=>{
        if(value!==''&&value!==null&&value!==undefined){
            url.searchParams.set(key,String(value));
        }
    });
    return jsonResponse(
        await fetch(
            url,
            {
                credentials:'same-origin',
                headers:{Accept:'application/json'}
            }
        )
    );
}

async function postPayment(action,data={}){
    return jsonResponse(
        await fetch(
            paymentApi,
            {
                method:'POST',
                credentials:'same-origin',
                headers:{
                    'Content-Type':'application/json',
                    Accept:'application/json'
                },
                body:JSON.stringify({
                    action,
                    csrf_token:csrfToken,
                    ...data
                })
            }
        )
    );
}

async function loadMeta(){
    const result=await getPayment('meta');
    csrfToken=result.data.csrf_token||'';
    gateway=result.data.gateway||gateway;

    $('gatewayNote').className=
        'payment-gateway-note '
        +(gateway.configured?'ready':'missing');

    $('gatewayNote').textContent=
        gateway.configured
            ?'Secure Razorpay payment is configured for this school.'
            :'Online payment is unavailable because Razorpay credentials are not configured in Fee Settings.';

    $('payButton').disabled=!gateway.configured;
}

async function loadChildren(studentId=0){
    const url=new URL(dashboardApi);
    if(Number(studentId)>0){
        url.searchParams.set(
            'student_id',
            String(Number(studentId))
        );
    }

    const result=await jsonResponse(
        await fetch(
            url,
            {
                credentials:'same-origin',
                headers:{Accept:'application/json'}
            }
        )
    );

    children=Array.isArray(result.data?.children)
        ?result.data.children
        :[];

    selectedChild=result.data?.selected_child||{};

    $('childSelect').innerHTML=children.length
        ?children.map(child=>`
            <option
                value="${Number(child.id||0)}"
                ${
                    Number(child.id||0)
                    ===Number(selectedChild.id||0)
                        ?'selected'
                        :''
                }
            >
                ${esc(display(child.student_name))}
                ·
                ${esc(display(child.class_name))}
            </option>
        `).join('')
        :'<option value="">No linked child</option>';

    $('childSelect').disabled=children.length<=1;
}

function feeName(item){
    return item.item_name
        ||String(item.item_type||'Fee')
            .replace(/_/g,' ')
            .replace(/\b\w/g,x=>x.toUpperCase());
}

function renderDetail(){
    if(!detail){
        assignmentId=0;
        return;
    }

    const student=detail.student||{};
    assignmentId=Number(student.id||student.assignment_id||0);

    const total=Number(detail.assigned_total||0);
    const paid=Number(detail.previously_paid||0);
    const previous=Number(detail.previous_due||0);
    const balance=Number(detail.outstanding_balance||0);

    $('totalFeeKpi').textContent=money(total);
    $('paidAmountKpi').textContent=money(paid);
    $('previousBalanceKpi').textContent=money(previous);
    $('currentBalanceKpi').textContent=money(balance);
    $('currentBalanceNote').textContent=
        balance>0?'Outstanding balance':'No outstanding balance';

    $('feeStructureKpi').textContent=
        student.structure_name||'Fee Structure';

    const items=Array.isArray(detail.schedule)
        ?detail.schedule
        :[];

    $('outstandingRows').innerHTML=items.length
        ?items.map(item=>{
            const original=Number(item.original_amount||0);
            const itemPaid=Number(item.paid_amount||0);
            const itemBalance=Number(item.balance_amount||0);
            const previousBalance=
                String(item.bucket||'')==='previous'
                    ?itemBalance
                    :0;

            return`
                <tr>
                    <td><strong>${esc(feeName(item))}</strong></td>
                    <td class="amount">${esc(money(original))}</td>
                    <td class="amount">${esc(money(itemPaid))}</td>
                    <td class="amount">${esc(money(previousBalance))}</td>
                    <td class="amount">${esc(money(itemBalance))}</td>
                </tr>
            `;
        }).join('')
        :`<tr><td colspan="5" class="empty">No fee schedule found.</td></tr>`;

    $('paymentAmount').max=String(balance);
    $('paymentAmountHelp').textContent=
        balance>0
            ?`Maximum payable now: ${money(balance)}`
            :'No outstanding fee is available for payment.';

    $('payButton').disabled=
        !gateway.configured||balance<=0.009;

    if($('paymentType').value==='full'){
        $('paymentAmount').value=
            balance>0?balance.toFixed(2):'';
    }
}

function renderHistory(){
    const receipts=Array.isArray(history?.receipts)
        ?history.receipts
        :[];

    $('paymentHistoryRows').innerHTML=receipts.length
        ?receipts.map(row=>`
            <tr>
                <td><strong>${esc(display(row.receipt_no))}</strong></td>
                <td>${esc(display(row.receipt_date_display||row.receipt_date))}</td>
                <td class="amount">${esc(money(row.paid_amount))}</td>
                <td>${esc(display(row.payment_methods))}</td>
                <td>
                    <span class="fee-status-pill ${esc(String(row.payment_status||'').toLowerCase())}">
                        ${esc(display(row.payment_status))}
                    </span>
                </td>
                <td>
                    <div class="payment-history-actions">
                        <button
                            class="btn-ui js-print-receipt"
                            type="button"
                            data-id="${Number(row.id||0)}"
                        >
                            <i data-lucide="printer"></i>
                            Print
                        </button>
                        <button
                            class="btn-ui js-pdf-receipt"
                            type="button"
                            data-id="${Number(row.id||0)}"
                        >
                            <i data-lucide="file-down"></i>
                            PDF
                        </button>
                    </div>
                </td>
            </tr>
        `).join('')
        :`<tr><td colspan="6" class="empty">No previous payment history found.</td></tr>`;

    document.querySelectorAll('.js-print-receipt')
        .forEach(button=>{
            button.onclick=()=>{
                const id=Number(button.dataset.id||0);
                window.open(
                    `${paymentApi}?action=receipt_print&receipt_id=${encodeURIComponent(id)}`,
                    '_blank'
                );
            };
        });

    document.querySelectorAll('.js-pdf-receipt')
        .forEach(button=>{
            button.onclick=()=>{
                const id=Number(button.dataset.id||0);
                window.open(
                    `${paymentApi}?action=receipt_pdf&receipt_id=${encodeURIComponent(id)}`,
                    '_blank'
                );
            };
        });

    window.lucide?.createIcons();
}

async function loadFeeData(){
    const studentId=Number(selectedChild.id||0);
    const yearId=Number(
        selectedChild.academic_year_id||0
    );

    if(studentId<=0||yearId<=0){
        detail=null;
        history=null;
        renderDetail();
        renderHistory();
        return;
    }

    try{
        const detailResult=await getPayment(
            'detail',
            {
                student_id:studentId,
                academic_year_id:yearId,
                payment_date:new Date()
                    .toISOString()
                    .slice(0,10)
            }
        );
        detail=detailResult.data||null;
    }catch(e){
        detail=null;
        throw e;
    }

    const historyResult=await getPayment(
        'student_history',
        {
            student_id:studentId,
            academic_year_id:yearId
        }
    );
    history=historyResult.data||null;

    renderDetail();
    renderHistory();
}

async function loadAll(studentId=0){
    loading(true);
    error('');
    success('');

    try{
        await loadMeta();
        await loadChildren(studentId);
        await loadFeeData();
    }catch(e){
        error(e.message||'Unable to load Fee Payment.');
    }finally{
        loading(false);
        window.lucide?.createIcons();
    }
}

async function verifyPayment(payload){
    const result=await postPayment(
        'verify',
        payload
    );

    success(
        'Payment successful. Receipt generated.'
    );

    const receiptId=Number(
        result.data?.receipt_id||0
    );

    await loadAll(
        Number(selectedChild.id||0)
    );

    if(receiptId>0){
        window.open(
            `${paymentApi}?action=receipt_print&receipt_id=${encodeURIComponent(receiptId)}`,
            '_blank'
        );
    }
}

$('paymentType').addEventListener(
    'change',
    function(){
        if(!detail)return;
        const balance=Number(
            detail.outstanding_balance||0
        );

        if(this.value==='full'){
            $('paymentAmount').value=
                balance>0
                    ?balance.toFixed(2)
                    :'';
            $('paymentAmount').readOnly=true;
        }else{
            $('paymentAmount').readOnly=false;
            $('paymentAmount').value='';
        }
    }
);

$('childSelect').addEventListener(
    'change',
    function(){
        loadAll(
            Number(this.value||0)
        );
    }
);

$('paymentForm').addEventListener(
    'submit',
    async function(event){
        event.preventDefault();
        error('');
        success('');

        try{
            if(!gateway.configured){
                throw new Error(
                    'Online payment gateway is not configured.'
                );
            }

            if(!detail||assignmentId<=0){
                throw new Error(
                    'No outstanding fee assignment is available.'
                );
            }

            const balance=Number(
                detail.outstanding_balance||0
            );

            let amount=Number(
                $('paymentAmount').value||0
            );

            const type=$('paymentType').value;

            if(type==='full'){
                amount=balance;
            }

            if(amount<=0){
                throw new Error(
                    'Enter a payment amount greater than zero.'
                );
            }

            if(amount>balance+0.01){
                throw new Error(
                    'Payment amount cannot exceed the current outstanding balance.'
                );
            }

            const result=await postPayment(
                'initiate',
                {
                    assignment_id:assignmentId,
                    payment_method:
                        $('paymentMethod').value,
                    amount,
                    payment_type:type,
                    payer_notes:
                        $('payerNotes').value.trim()
                }
            );

            const data=result.data||{};

            if(data.gateway!=='razorpay'){
                throw new Error(
                    'Razorpay is not configured for this school.'
                );
            }

            if(typeof Razorpay==='undefined'){
                throw new Error(
                    'Razorpay checkout could not be loaded.'
                );
            }

            const options={
                key:data.key_id,
                amount:data.amount_subunits,
                currency:data.currency,
                name:data.school_name||'School Fee',
                description:data.description||'School Fee Payment',
                order_id:data.gateway_order_id,
                prefill:{
                    name:data.student_name||'',
                    email:data.email||'',
                    contact:data.mobile||''
                },
                notes:{
                    transaction_no:
                        data.transaction_no||''
                },
                handler:response=>verifyPayment({
                    transaction_id:
                        data.transaction_id,
                    razorpay_order_id:
                        response.razorpay_order_id,
                    razorpay_payment_id:
                        response.razorpay_payment_id,
                    razorpay_signature:
                        response.razorpay_signature
                }),
                modal:{
                    ondismiss:()=>{
                        postPayment(
                            'cancel',
                            {
                                transaction_id:
                                    data.transaction_id
                            }
                        ).catch(()=>{});
                    }
                },
                theme:{
                    color:'#4f46e5'
                }
            };

            new Razorpay(options).open();
        }catch(e){
            error(
                e.message||'Unable to start payment.'
            );
        }
    }
);

loadAll();
})();
</script>

<?php
require dirname(__DIR__)
    .'/includes/layout-end.php';
?>

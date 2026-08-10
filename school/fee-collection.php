<?php
declare(strict_types=1);
$pageTitle='Fee Collection';
$pageKey='fee_management';
require dirname(__DIR__).'/includes/layout-start.php';
if(session_status()!==PHP_SESSION_ACTIVE){session_start();}
if(empty($_SESSION['fee_csrf_token'])||!is_string($_SESSION['fee_csrf_token'])){$_SESSION['fee_csrf_token']=bin2hex(random_bytes(32));}
$feeCsrf=$_SESSION['fee_csrf_token'];
?>
<style>
*{box-sizing:border-box}

.fc-page{
    display:grid;
    gap:16px;
    width:100%;
    min-width:0;
}

.fc-page .page-heading{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:16px;
    min-width:0;
}

.fc-page .page-heading>div:first-child{min-width:0}

.fc-page .page-title{
    margin:0;
    font-size:clamp(24px,2vw,30px);
    line-height:1.15;
}

.fc-page .page-subtitle{
    margin-top:5px;
    max-width:760px;
}

.fc-page .page-actions{
    display:flex;
    align-items:center;
    justify-content:flex-end;
    flex-wrap:wrap;
    gap:9px;
    min-width:0;
}

.fc-page .page-actions .btn-ui{
    min-height:40px;
    white-space:nowrap;
}

.fc-message{display:none}
.fc-message.show{display:block}

.fc-stats{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:14px;
}

.fc-stat{
    border:0;
    border-radius:14px;
    min-height:116px;
    padding:18px 20px;
    display:flex;
    align-items:center;
    gap:14px;
    color:#fff;
    position:relative;
    overflow:hidden;
    min-width:0;
    box-shadow:0 12px 28px rgba(15,23,42,.08);
}

.fc-stat::before{
    content:"";
    position:absolute;
    inset:0;
    background:linear-gradient(180deg,rgba(255,255,255,.03),rgba(15,23,42,.05));
    pointer-events:none;
}

.fc-stat::after{
    content:"";
    position:absolute;
    width:118px;
    height:118px;
    border-radius:50%;
    right:-40px;
    top:-42px;
    background:rgba(255,255,255,.09);
}

.fc-stat.green{background:linear-gradient(135deg,#45c783,#1ea568)}
.fc-stat.pink{background:linear-gradient(135deg,#ff527c,#ed2f63)}
.fc-stat.orange{background:linear-gradient(135deg,#ffb327,#ff8a17)}
.fc-stat.blue{background:linear-gradient(135deg,#4696ef,#2469d5)}

.fc-stat-icon{
    width:50px;
    height:50px;
    border-radius:50%;
    background:rgba(255,255,255,.16);
    display:grid;
    place-items:center;
    flex:0 0 auto;
    position:relative;
    z-index:1;
}

.fc-stat-icon svg{width:25px;height:25px}

.fc-stat>div{
    position:relative;
    z-index:1;
    min-width:0;
}

.fc-stat strong{
    display:block;
    font-size:clamp(21px,1.8vw,27px);
    line-height:1.05;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}

.fc-stat small{
    display:block;
    font-size:11px;
    font-weight:700;
    opacity:.96;
    margin-bottom:6px;
}

.fc-stat .trend{
    font-size:9px;
    font-weight:700;
    opacity:.94;
    margin-top:8px;
    white-space:normal;
}

.fee-nav,
.fc-tabs{
    display:flex;
    gap:8px;
    overflow-x:auto;
    overflow-y:hidden;
    padding:10px;
    scrollbar-width:thin;
}

.fee-nav a,
.fc-tab{
    flex:0 0 auto;
    display:inline-flex;
    align-items:center;
    gap:7px;
    padding:10px 14px;
    border:1px solid var(--border-soft,#e7ebf3);
    border-radius:9px;
    background:var(--card-bg,#fff);
    color:var(--text-main,#101a3b);
    font-size:11px;
    font-weight:800;
    text-decoration:none;
    white-space:nowrap;
}

.fee-nav a.active,
.fc-tab.active{
    color:#fff;
    border-color:transparent;
    background:linear-gradient(135deg,#6547e8,#315ed8);
}

.fee-nav svg{width:15px;height:15px}

.fc-panel{display:none;min-width:0}
.fc-panel.active{display:block}

.fc-card{
    border-radius:14px;
    overflow:hidden;
    min-width:0;
}

.fc-card-head{
    padding:14px 16px;
    border-bottom:1px solid var(--border-soft,#e7ebf3);
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    min-width:0;
}

.fc-card-head strong{font-size:14px}

.fc-card-actions,
.fc-inline-actions,
.fc-action-group,
.fc-receipt-actions{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    align-items:center;
}

.fc-grid{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:12px;
    padding:16px;
}

.fc-grid>div{min-width:0}
.fc-grid .full{grid-column:1/-1}

.fc-summary,
.fc-history-summary{
    display:grid;
    grid-template-columns:repeat(5,minmax(0,1fr));
    gap:10px;
    padding:12px;
    border:1px solid var(--border-soft,#e7ebf3);
    border-radius:12px;
    background:rgba(99,102,241,.04);
}

.fc-summary div,
.fc-history-summary>div{
    padding:10px;
    border-radius:10px;
    background:var(--card-bg,#fff);
    min-width:0;
}

.fc-summary small,
.fc-history-summary small{
    display:block;
    color:var(--text-muted,#64748b);
    font-size:9px;
    font-weight:700;
}

.fc-summary strong,
.fc-history-summary strong{
    display:block;
    margin-top:4px;
    font-size:15px;
    overflow-wrap:anywhere;
}

.fc-table-wrap{
    width:100%;
    max-width:100%;
    overflow-x:auto;
    overflow-y:visible;
    -webkit-overflow-scrolling:touch;
    scrollbar-width:thin;
}

.fc-table{
    width:100%;
    min-width:980px;
    border-collapse:separate;
    border-spacing:0;
}

.fc-due-table{min-width:860px}

.fc-table th,
.fc-due-table th{
    font-size:10px;
    white-space:nowrap;
    position:sticky;
    top:0;
    z-index:2;
    background:var(--card-bg,#fff);
}

.fc-table td,
.fc-due-table td{
    font-size:11px;
    vertical-align:middle;
}

.fc-table th,
.fc-table td,
.fc-due-table th,
.fc-due-table td{
    padding:11px 10px;
}

.fc-table tbody tr:hover,
.fc-due-table tbody tr:hover{
    background:rgba(79,70,229,.025);
}

.fc-empty{
    padding:40px 18px!important;
    text-align:center!important;
    color:var(--text-muted,#64748b);
}

.fc-badge{
    display:inline-flex;
    align-items:center;
    padding:5px 9px;
    border-radius:999px;
    font-size:9px;
    font-weight:800;
    text-transform:capitalize;
    white-space:nowrap;
}

.fc-badge.paid,
.fc-badge.success,
.fc-badge.active{color:#16834f;background:#e8f8ef}

.fc-badge.partial,
.fc-badge.pending{color:#9a6700;background:#fff7d6}

.fc-badge.unpaid,
.fc-badge.overdue,
.fc-badge.failed,
.fc-badge.reversed,
.fc-badge.no_fee{color:#dc2626;background:#fff0f1}

.fc-action{
    width:30px;
    height:30px;
    display:grid;
    place-items:center;
    flex:0 0 auto;
    border:1px solid #d7def1;
    border-radius:7px;
    background:var(--card-bg,#fff);
    color:#4f46e5;
}

.fc-action.danger{color:#dc2626}
.fc-action svg{width:13px;height:13px}

.fc-student-card{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:10px;
    padding:12px;
    border:1px solid var(--border-soft,#e7ebf3);
    border-radius:12px;
}

.fc-student-card div{
    padding:10px;
    background:rgba(99,102,241,.035);
    border-radius:10px;
    min-width:0;
}

.fc-student-card small{
    display:block;
    font-size:9px;
    color:var(--text-muted,#64748b);
    font-weight:700;
}

.fc-student-card strong{
    display:block;
    margin-top:4px;
    font-size:12px;
    overflow-wrap:anywhere;
}

.fc-filter{
    padding:14px 16px;
    display:grid;
    grid-template-columns:minmax(220px,1.4fr) repeat(4,minmax(145px,.72fr)) auto;
    gap:10px;
    align-items:center;
    border-bottom:1px solid var(--border-soft,#e7ebf3);
}

.fc-filter .form-control,
.fc-filter .form-select,
.fc-filter .btn-ui{
    width:100%;
    min-width:0;
    min-height:40px;
}

.fc-pagination{
    padding:14px 16px;
    border-top:1px solid var(--border-soft,#e7ebf3);
    display:flex;
    justify-content:space-between;
    gap:10px;
    align-items:center;
    flex-wrap:wrap;
}

.fc-page-buttons{
    display:flex;
    gap:6px;
    flex-wrap:wrap;
}

.fc-page-button{
    min-width:34px;
    height:34px;
    border:1px solid var(--border-soft,#e7ebf3);
    background:#fff;
    border-radius:8px;
    font-size:11px;
    font-weight:800;
}

.fc-page-button.active{
    color:#fff;
    border-color:transparent;
    background:linear-gradient(135deg,#6547e8,#315ed8);
}

.fc-page-button:disabled{opacity:.45}

.fc-student-name{
    display:flex;
    align-items:center;
    gap:9px;
    min-width:0;
}

.fc-student-name>div{min-width:0}

.fc-student-avatar{
    width:30px;
    height:30px;
    border-radius:50%;
    display:grid;
    place-items:center;
    color:#fff;
    font-size:11px;
    font-weight:800;
    flex:0 0 auto;
    background:linear-gradient(135deg,#6d4ce7,#345fe0);
}

.fc-student-row.pending-fee{box-shadow:inset 3px 0 0 #ff8a17}
.fc-student-row.pending-fee td{background:rgba(255,179,39,.045)}
.fc-collect-action{white-space:nowrap}
.fc-balance-cell strong{display:block}
.fc-balance-cell .fc-badge{margin-top:4px}

.fee-form-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:12px;
}

.fee-form-grid>div{min-width:0}
.fee-form-grid .full{grid-column:1/-1}

.fc-charge-row{
    display:grid;
    grid-template-columns:1fr 1.15fr .65fr auto;
    gap:8px;
    align-items:end;
    margin-bottom:8px;
}

.fc-charge-row>div{min-width:0}

.fc-calculation{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:10px;
}

.fc-calculation div{
    padding:11px;
    border:1px solid var(--border-soft,#e7ebf3);
    border-radius:10px;
    background:rgba(99,102,241,.035);
    min-width:0;
}

.fc-calculation small{
    display:block;
    font-size:9px;
    font-weight:700;
    color:var(--text-muted,#64748b);
}

.fc-calculation strong{
    display:block;
    margin-top:4px;
    font-size:14px;
    overflow-wrap:anywhere;
}

.fc-calculation .grand{background:rgba(99,102,241,.09)}

.fc-section-title{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
    margin:16px 0 9px;
    flex-wrap:wrap;
}

.fc-section-title strong{font-size:13px}

.fc-type{
    display:inline-flex;
    padding:4px 8px;
    border-radius:999px;
    background:#eef2ff;
    color:#4338ca;
    font-size:9px;
    font-weight:800;
    text-transform:capitalize;
    white-space:nowrap;
}

.fc-history-section{margin-top:18px}

.fc-history-section-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    margin-bottom:9px;
    flex-wrap:wrap;
}

.fc-history-section-head strong{font-size:13px}

#collectFeeModal .modal-dialog,
#receiptManageModal .modal-dialog,
#studentHistoryModal .modal-dialog{
    max-height:calc(100dvh - 32px);
    margin:16px auto;
}

#collectFeeModal .modal-dialog,
#studentHistoryModal .modal-dialog{
    width:min(1180px,calc(100vw - 32px));
    max-width:1180px;
}

#receiptManageModal .modal-dialog{
    width:min(760px,calc(100vw - 32px));
    max-width:760px;
}

#collectFeeModal .modal-content,
#receiptManageModal .modal-content,
#studentHistoryModal .modal-content{
    max-height:calc(100dvh - 32px);
    overflow:hidden;
}

#collectFeeModal form,
#receiptManageModal form{
    display:flex;
    flex-direction:column;
    max-height:calc(100dvh - 32px);
}

#collectFeeModal .modal-body,
#receiptManageModal .modal-body,
#studentHistoryModal .modal-body{
    overflow-y:auto;
    min-height:0;
}

#collectFeeModal .modal-footer,
#receiptManageModal .modal-footer,
#studentHistoryModal .modal-footer{
    flex-wrap:wrap;
}

/* Large desktop monitors */
@media(min-width:1600px){
    .fc-page{gap:18px}
    .fc-stats{gap:16px}
    .fc-stat{min-height:122px;padding:20px 22px}
    .fc-filter{
        grid-template-columns:minmax(280px,1.5fr) repeat(4,minmax(155px,.72fr)) auto;
    }
    .fc-table{min-width:100%}
    .fc-table th,
    .fc-table td{padding:12px}
}

/* Standard desktop and 1366px laptop */
@media(max-width:1399px){
    .fc-page .page-actions{max-width:520px}
    .fc-grid{grid-template-columns:repeat(3,minmax(0,1fr))}
    .fc-filter{
        grid-template-columns:minmax(220px,1.35fr) repeat(3,minmax(145px,.76fr)) auto;
    }
}

/* Small desktop and laptop */
@media(max-width:1199px){
    .fc-page .page-heading{
        flex-direction:column;
        align-items:stretch;
    }

    .fc-page .page-actions{
        justify-content:flex-start;
        max-width:none;
    }

    .fc-stats{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }

    .fc-grid{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }

    .fc-filter{
        grid-template-columns:repeat(3,minmax(0,1fr));
    }

    .fc-filter input:first-child{
        grid-column:span 2;
    }

    .fc-summary,
    .fc-history-summary{
        grid-template-columns:repeat(3,minmax(0,1fr));
    }

    .fc-student-card{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }
}

/* Tablet */
@media(max-width:767px){
    .fc-page{gap:12px}

    .fc-page .page-actions{
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        width:100%;
    }

    .fc-page .page-actions .btn-ui{
        width:100%;
        justify-content:center;
    }

    .fc-stats{
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:10px;
    }

    .fc-stat{
        min-height:108px;
        padding:15px;
    }

    .fc-filter{
        grid-template-columns:repeat(2,minmax(0,1fr));
        padding:12px;
    }

    .fc-filter input:first-child{
        grid-column:1/-1;
    }

    .fc-card-head,
    .fc-pagination{
        align-items:flex-start;
        flex-direction:column;
    }

    .fc-grid,
    .fee-form-grid,
    .fc-charge-row{
        grid-template-columns:1fr;
    }

    .fc-grid .full,
    .fee-form-grid .full{
        grid-column:auto;
    }

    .fc-summary,
    .fc-history-summary,
    .fc-student-card,
    .fc-calculation{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }

    .fc-table{min-width:900px}
    .fc-due-table{min-width:760px}
}

/* Mobile */
@media(max-width:575px){
    .fc-page .page-title{font-size:24px}

    .fc-page .page-actions{
        grid-template-columns:1fr 1fr;
    }

    .fc-page .page-actions .btn-ui:first-child{
        grid-column:1/-1;
    }

    .fc-stats,
    .fc-filter,
    .fc-summary,
    .fc-history-summary,
    .fc-student-card,
    .fc-calculation{
        grid-template-columns:1fr;
    }

    .fc-stat{min-height:102px}

    .fc-filter input:first-child{grid-column:auto}

    .fee-nav,
    .fc-tabs{padding:8px}

    .fee-nav a,
    .fc-tab{padding:9px 11px}

    .fc-pagination{padding:12px}

    #collectFeeModal .modal-dialog,
    #receiptManageModal .modal-dialog,
    #studentHistoryModal .modal-dialog{
        width:calc(100vw - 16px);
        margin:8px auto;
        max-height:calc(100dvh - 16px);
    }

    #collectFeeModal .modal-content,
    #receiptManageModal .modal-content,
    #studentHistoryModal .modal-content,
    #collectFeeModal form,
    #receiptManageModal form{
        max-height:calc(100dvh - 16px);
    }

    #collectFeeModal .modal-footer,
    #receiptManageModal .modal-footer,
    #studentHistoryModal .modal-footer{
        display:grid;
        grid-template-columns:1fr;
    }

    #collectFeeModal .modal-footer .btn-ui,
    #receiptManageModal .modal-footer .btn-ui,
    #studentHistoryModal .modal-footer .btn-ui{
        width:100%;
        justify-content:center;
    }
}
</style>
<div class="fc-page" data-page="collection">
<div class="page-heading">
 <div><h1 class="page-title">Fee Collection</h1><p class="page-subtitle">Collect scheduled fees, route-wise bus fees and additional charges.</p></div>
 <div class="page-actions"><button id="topCollectBtn" class="btn-ui btn-primary-ui" type="button"><i data-lucide="indian-rupee"></i> Collect Fee</button><a id="exportReceipts" class="btn-ui"><i data-lucide="download"></i> Export Report</a><button id="refreshBtn" class="btn-ui" type="button"><i data-lucide="refresh-cw"></i> Refresh</button></div>
</div>
<div id="feeMessage" class="alert fc-message"></div>
<section class="fc-stats">
 <article class="fc-stat green"><span class="fc-stat-icon"><i data-lucide="wallet-cards"></i></span><div><small>Total Assigned</small><strong id="statAssigned">₹0</strong><div class="trend">Selected Academic Year</div></div></article>
 <article class="fc-stat pink"><span class="fc-stat-icon"><i data-lucide="badge-indian-rupee"></i></span><div><small>Total Collected</small><strong id="statCollected">₹0</strong><div class="trend">Paid by filtered students</div></div></article>
 <article class="fc-stat orange"><span class="fc-stat-icon"><i data-lucide="clock-3"></i></span><div><small>Outstanding Balance</small><strong id="statOutstanding">₹0</strong><div class="trend">Pending student fees</div></div></article>
 <article class="fc-stat blue"><span class="fc-stat-icon"><i data-lucide="receipt-text"></i></span><div><small>Recent Receipts</small><strong id="statReceipts">0</strong><div class="trend">Payment history</div></div></article>
</section>
<section class="ui-card fc-tabs"><button class="fc-tab active" data-tab="collection" type="button">Fee Collection</button><button class="fc-tab" data-tab="summary" type="button">Fee Breakdown</button><button class="fc-tab" data-tab="receipts" type="button">Payment History</button></section>
<section class="fc-panel active" data-panel="collection">
 <section class="ui-card fc-card" id="studentFeeCard">
  <div class="fc-card-head"><strong>Student Fee Collection</strong><small class="text-muted">Select filters and click Collect Fee.</small></div>
  <div class="fc-filter">
   <input id="studentSearch" class="form-control" placeholder="Search student name, admission no. or mobile...">
   <select id="yearId" class="form-select"><option value="">Select Academic Year</option></select>
   <select id="classId" class="form-select"><option value="">All Classes</option></select>
   <select id="sectionId" class="form-select" disabled><option value="">All Sections</option></select>
   <select id="feeStatusFilter" class="form-select"><option value="all">All Fee Status</option><option value="pending">Pending Fees</option><option value="paid">Fully Paid</option><option value="no_fee">No Fee Assigned</option></select>
   <button id="studentFilterReset" class="btn-ui" type="button"><i data-lucide="rotate-ccw"></i> Reset</button>
  </div>
  <div class="fc-table-wrap"><table class="data-table fc-table"><thead><tr><th>Student Name</th><th>Admission No.</th><th>Total Fee</th><th>Paid Amount</th><th>Balance Amount</th><th>Action</th></tr></thead><tbody id="studentFeeBody"><tr><td colspan="6" class="fc-empty">Loading students...</td></tr></tbody></table></div>
  <div class="fc-pagination"><small id="studentRecordCount" class="text-muted">Loading...</small><small class="text-muted">Pending students are highlighted.</small></div>
 </section>
</section>
<section class="fc-panel" data-panel="summary">
 <section class="ui-card fc-card"><div class="fc-card-head"><strong>Assigned Fee Schedule</strong><small id="breakdownHint" class="text-muted">Open a student to view the schedule.</small></div><div class="fc-table-wrap"><table class="data-table fc-table"><thead><tr><th>Fee Type</th><th>Fee Component</th><th>Period</th><th>Due Date</th><th>Amount</th><th>Discount</th><th>Paid</th><th>Balance</th><th>Status</th></tr></thead><tbody id="breakdownBody"><tr><td colspan="9" class="fc-empty">Select a student.</td></tr></tbody></table></div></section>
</section>
<section class="fc-panel" data-panel="receipts">
 <section class="ui-card fc-card">
  <div class="fc-card-head"><strong>Payment History</strong><small id="receiptCount" class="text-muted">0 receipts</small></div>
  <div class="fc-filter"><input id="receiptSearch" class="form-control" placeholder="Receipt no. or student..."><input id="fromDate" class="form-control" type="date"><input id="toDate" class="form-control" type="date"><select id="receiptStatus" class="form-select"><option value="all">All Statuses</option><option value="paid">Paid</option><option value="partial">Partial</option><option value="due">Due</option><option value="reversed">Reversed</option></select><select id="receiptMethod" class="form-select"><option value="all">All Payment Modes</option></select><button id="receiptReset" class="btn-ui" type="button">Reset</button></div>
  <div class="fc-table-wrap"><table class="data-table fc-table"><thead><tr><th>Receipt No</th><th>Date</th><th>Student</th><th>Gross</th><th>Discount</th><th>Paid</th><th>Balance</th><th>Method</th><th>Status</th><th>Actions</th></tr></thead><tbody id="receiptBody"><tr><td colspan="10" class="fc-empty">Loading...</td></tr></tbody></table></div>
  <div class="fc-pagination"><small id="receiptPageInfo" class="text-muted"></small><div id="receiptPagination" class="fc-page-buttons"></div></div>
 </section>
</section>
</div>

<div class="modal fade" id="collectFeeModal" tabindex="-1">
<div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content"><form id="collectFeeForm" novalidate>
 <div class="modal-header"><div><h5 class="modal-title">Collect Fee</h5><small class="text-muted">Scheduled fees and additional charges.</small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
 <div class="modal-body">
  <input id="modalStudentId" type="hidden"><div id="studentDetails" class="fc-student-card mb-3"></div>
  <div class="fc-section-title"><strong>Due Fee Components</strong><small class="text-muted">Previous due and current period fees load automatically.</small></div>
  <div class="fc-table-wrap"><table class="data-table fc-due-table"><thead><tr><th>Fee Type</th><th>Component</th><th>Period</th><th>Due Date</th><th>Balance</th></tr></thead><tbody id="dueItemBody"><tr><td colspan="5" class="fc-empty">Loading...</td></tr></tbody></table></div>
  <div class="fc-section-title"><strong>Additional Charges</strong><button id="addChargeBtn" class="btn-ui btn-sm" type="button"><i data-lucide="plus"></i> Add Charge</button></div>
  <div id="chargeRows"></div>
  <div class="fee-form-grid mt-3">
   <div><label class="form-label">Discount</label><input id="discount" class="form-control" type="number" min="0" step="0.01" value="0"></div>
   <div><label class="form-label">Paid Amount *</label><input id="amount" class="form-control" type="number" min="0.01" step="0.01" required></div>
   <div><label class="form-label">Payment Date *</label><input id="paymentDate" class="form-control" type="date" required></div>
   <div><label class="form-label">Payment Mode *</label><select id="methodId" class="form-select" required></select></div>
   <div><label class="form-label">Reference Number</label><input id="reference" class="form-control" maxlength="100"></div>
   <div class="full"><label class="form-label">Remarks</label><textarea id="notes" class="form-control" rows="3" maxlength="255"></textarea></div>
  </div>
  <div class="fc-calculation mt-3">
   <div><small>Total Assigned Fee</small><strong id="calcTotalFee">₹0</strong></div><div><small>Previously Paid</small><strong id="calcPrevious">₹0</strong></div><div><small>Outstanding Before Payment</small><strong id="calcOutstanding">₹0</strong></div><div><small>Additional Charges</small><strong id="calcAdditional">₹0</strong></div><div><small>New Discount</small><strong id="calcDiscount">₹0</strong></div><div class="grand"><small>Total Payable Now</small><strong id="calcGrand">₹0</strong></div><div><small>Paid Amount</small><strong id="calcPaid">₹0</strong></div><div><small>Balance / Due</small><strong id="calcBalance">₹0</strong></div>
  </div>
 </div>
 <div class="modal-footer"><button type="button" class="btn-ui" data-bs-dismiss="modal">Cancel</button><button id="saveFeeBtn" type="button" class="btn-ui"><i data-lucide="save"></i> Save</button><button id="savePrintFeeBtn" type="button" class="btn-ui btn-primary-ui"><i data-lucide="printer"></i> Save &amp; Print Receipt</button></div>
</form></div></div>
</div>


<div class="modal fade" id="studentHistoryModal" tabindex="-1">
<div class="modal-dialog modal-xl modal-dialog-centered">
<div class="modal-content">
 <div class="modal-header">
  <div>
   <h5 class="modal-title">Student Fee History</h5>
   <small id="historySubtitle" class="text-muted">Schedule follows the latest Fee Structure frequency; paid records remain preserved.</small>
  </div>
  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
 </div>
 <div class="modal-body">
  <div id="historyStudentDetails" class="fc-student-card"></div>

  <div id="historySummary" class="fc-history-summary mt-3"></div>

  <section class="fc-history-section">
   <div class="fc-history-section-head">
    <strong>Complete Fee Schedule</strong>
    <small id="historyScheduleCount" class="text-muted">0 items</small>
   </div>
   <div class="fc-table-wrap">
    <table class="data-table fc-table">
     <thead>
      <tr>
       <th>Fee Type</th>
       <th>Component</th>
       <th>Period</th>
       <th>Due Date</th>
       <th>Original</th>
       <th>Discount</th>
       <th>Paid</th>
       <th>Balance</th>
       <th>Status</th>
      </tr>
     </thead>
     <tbody id="historyScheduleBody">
      <tr><td colspan="9" class="fc-empty">Loading fee schedule...</td></tr>
     </tbody>
    </table>
   </div>
  </section>

  <section class="fc-history-section">
   <div class="fc-history-section-head">
    <strong>Payment &amp; Receipt History</strong>
    <small id="historyReceiptCount" class="text-muted">0 receipts</small>
   </div>
   <div class="fc-table-wrap">
    <table class="data-table fc-table">
     <thead>
      <tr>
       <th>Receipt No.</th>
       <th>Date</th>
       <th>Gross</th>
       <th>Discount</th>
       <th>Paid</th>
       <th>Balance</th>
       <th>Payment Mode</th>
       <th>Status</th>
       <th>Action</th>
      </tr>
     </thead>
     <tbody id="historyReceiptBody">
      <tr><td colspan="9" class="fc-empty">Loading payment history...</td></tr>
     </tbody>
    </table>
   </div>
  </section>
 </div>
 <div class="modal-footer">
  <button class="btn-ui" type="button" data-bs-dismiss="modal">Close</button>
 </div>
</div>
</div>
</div>

<div class="modal fade" id="receiptManageModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><form id="receiptManageForm"><div class="modal-header"><div><h5 class="modal-title">Manage Receipt</h5><small class="text-muted">Update payment mode, reference and remarks.</small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input id="manageReceiptId" type="hidden"><div class="fee-form-grid"><div><label class="form-label">Receipt No</label><input id="manageReceiptNo" class="form-control" readonly></div><div><label class="form-label">Payment Method</label><select id="manageMethodId" class="form-select"></select></div><div class="full"><label class="form-label">Reference No.</label><input id="manageReference" class="form-control" maxlength="100"></div><div class="full"><label class="form-label">Remarks</label><textarea id="manageNotes" class="form-control" rows="3" maxlength="255"></textarea></div></div></div><div class="modal-footer"><button type="button" class="btn-ui" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn-ui btn-primary-ui"><i data-lucide="save"></i> Update Receipt</button></div></form></div></div></div>

<script>
(function(){
'use strict';
const apiUrl=new URL('../api/fee-collection.php',window.location.href).href;
let csrfToken=<?=json_encode($feeCsrf)?>,meta={years:[],classes:[],sections:[],methods:[],charge_types:[]},studentRows=[],selectedStudent=null,detail=null,receiptPage=1,searchTimer=null,receiptTimer=null,chargeCounter=0,historyStudent=null,paidAmountManual=false;
const $=id=>document.getElementById(id);const esc=value=>String(value??'').replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));const money=value=>new Intl.NumberFormat('en-IN',{style:'currency',currency:'INR',maximumFractionDigits:2}).format(Number(value||0));const badge=value=>`<span class="fc-badge ${esc(String(value||'').toLowerCase())}">${esc(value||'-')}</span>`;
function localToday(){const now=new Date();return new Date(now.getTime()-now.getTimezoneOffset()*60000).toISOString().slice(0,10)}
async function request(action,data={},method='GET'){let response;if(method==='GET'){const url=new URL(apiUrl);url.searchParams.set('action',action);Object.entries(data).forEach(([k,v])=>{if(v!==''&&v!==null&&v!==undefined)url.searchParams.set(k,String(v))});response=await fetch(url,{headers:{Accept:'application/json'},credentials:'same-origin'});}else response=await fetch(apiUrl,{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json'},credentials:'same-origin',body:JSON.stringify({action,csrf_token:csrfToken,...data})});const text=await response.text();let result;try{result=JSON.parse(text)}catch{throw new Error(`Fee Collection API returned HTTP ${response.status}. ${text.replace(/\s+/g,' ').trim().slice(0,220)||'Invalid server response.'}`)}if(!response.ok||!result.success)throw new Error(result.message||'Request failed.');if(result.data?.csrf_token)csrfToken=result.data.csrf_token;return result;}
function message(text,success=false){const box=$('feeMessage');box.className='alert fc-message show '+(success?'alert-success':'alert-danger');box.textContent=text;clearTimeout(box._timer);box._timer=setTimeout(()=>box.className='alert fc-message',7000)}
function fill(id,rows,key,label,first='',firstValue=''){const el=$(id);el.innerHTML=(first?`<option value="${esc(firstValue)}">${esc(first)}</option>`:'')+rows.map(row=>`<option value="${esc(row[key])}">${esc(typeof label==='function'?label(row):row[label])}</option>`).join('')}
function n(id){const el=$(id);if(!el)return 0;const v=Number(el.value||0);return Number.isFinite(v)&&v>=0?v:0}
function refreshClasses(){const year=Number($('yearId').value||0),old=$('classId').value;const rows=(meta.classes||[]).filter(row=>!year||Number(row.academic_year_id)===year);fill('classId',rows,'id','class_name','All Classes','');if(old&&[...$('classId').options].some(o=>o.value===old))$('classId').value=old;refreshSections()}
function refreshSections(){const classId=Number($('classId').value||0),old=$('sectionId').value;const rows=(meta.sections||[]).filter(row=>classId>0&&Number(row.class_id)===classId);fill('sectionId',rows,'id','section_name','All Sections','');$('sectionId').disabled=classId<=0;if(old&&[...$('sectionId').options].some(o=>o.value===old))$('sectionId').value=old}
function filters(){return{academic_year_id:$('yearId').value,class_id:$('classId').value,section_id:$('sectionId').value,search:$('studentSearch').value.trim(),fee_status:$('feeStatusFilter').value}}
function renderStats(stats={}){$('statAssigned').textContent=money(stats.total_fee||0);$('statCollected').textContent=money(stats.paid_amount||0);$('statOutstanding').textContent=money(stats.balance_amount||0)}
function renderStudents(){
 $('studentFeeBody').innerHTML=studentRows.map(row=>{
  const balance=Number(row.balance_amount||0);
  const total=Number(row.total_fee||0);
  const pending=balance>0.009;
  const status=total<=0
   ?'no_fee'
   :(pending?(Number(row.paid_amount)>0?'partial':'pending'):'paid');

  const collectAction=pending
   ?`<button class="btn-ui btn-primary-ui btn-sm js-collect" data-id="${Number(row.id)}" type="button"><i data-lucide="indian-rupee"></i> Collect Fee</button>`
   :(status==='paid'
      ?'<button class="btn-ui btn-sm" disabled>Paid</button>'
      :'<button class="btn-ui btn-sm" disabled>No Fee Assigned</button>');

  return `<tr class="fc-student-row ${pending?'pending-fee':''}">
   <td>
    <div class="fc-student-name">
     <span class="fc-student-avatar">${esc((row.student_name||'?').charAt(0).toUpperCase())}</span>
     <div>
      <strong>${esc(row.student_name)}</strong>
      <div class="text-muted">${esc(row.class_name||'-')}${row.section_name?' / '+esc(row.section_name):''}</div>
     </div>
    </div>
   </td>
   <td><strong>${esc(row.admission_no)}</strong></td>
   <td>${money(total)}</td>
   <td>${money(row.paid_amount)}</td>
   <td class="fc-balance-cell"><strong>${money(balance)}</strong>${badge(status)}</td>
   <td>
    <div class="fc-action-group">
     <button class="fc-action js-view-history" data-id="${Number(row.id)}" type="button" title="View Full History"><i data-lucide="eye"></i></button>
     ${collectAction}
    </div>
   </td>
  </tr>`;
 }).join('')||'<tr><td colspan="6" class="fc-empty">No students found.</td></tr>';

 $('studentRecordCount').textContent=`Showing ${studentRows.length} student${studentRows.length===1?'':'s'}`;

 document.querySelectorAll('.js-collect').forEach(
  button=>button.onclick=()=>openCollect(Number(button.dataset.id))
 );
 document.querySelectorAll('.js-view-history').forEach(
  button=>button.onclick=()=>openStudentHistory(Number(button.dataset.id))
 );

 window.lucide?.createIcons();
}

function renderHistoryStudent(student={}){
 const route=student.transport_route_name||'Not Required';
 $('historyStudentDetails').innerHTML=`
  <div><small>Student</small><strong>${esc(student.student_name||'-')}</strong></div>
  <div><small>Admission No.</small><strong>${esc(student.admission_no||'-')}</strong></div>
  <div><small>Academic Year</small><strong>${esc(student.academic_year_name||'-')}</strong></div>
  <div><small>Class / Section</small><strong>${esc(student.class_name||'-')}${student.section_name?' / '+esc(student.section_name):''}</strong></div>
  <div><small>Fee Structure</small><strong>${esc(student.structure_name||'-')}</strong></div>
  <div><small>Transport Route</small><strong>${esc(route)}</strong></div>
  <div><small>Mobile</small><strong>${esc(student.mobile||'-')}</strong></div>
  <div><small>Fee Status</small><strong>${badge(student.payment_status||'unpaid')}</strong></div>
 `;
}
function renderHistorySummary(data={}){
 const summary=data.summary||{};
 $('historySummary').innerHTML=`
  <div><small>Total Assigned</small><strong>${money(summary.total_assigned||0)}</strong></div>
  <div><small>Total Discount</small><strong>${money(summary.total_discount||0)}</strong></div>
  <div><small>Total Paid</small><strong>${money(summary.total_paid||0)}</strong></div>
  <div><small>Outstanding Balance</small><strong>${money(summary.balance_amount||0)}</strong></div>
  <div><small>Total Receipts</small><strong>${Number(summary.receipt_count||0).toLocaleString('en-IN')}</strong></div>
 `;
}
function renderHistorySchedule(rows=[]){
 $('historyScheduleCount').textContent=`${rows.length} fee item${rows.length===1?'':'s'}`;
 $('historyScheduleBody').innerHTML=rows.map(row=>`<tr>
  <td><span class="fc-type">${esc(row.item_type||'-')}</span></td>
  <td>${esc(row.item_name||'-')}</td>
  <td>${esc(row.period_label||'-')}</td>
  <td>${esc(row.due_date_display||row.due_date||'-')}</td>
  <td>${money(row.original_amount)}</td>
  <td>${money(row.discount_amount)}</td>
  <td>${money(row.paid_amount)}</td>
  <td><strong>${money(row.balance_amount)}</strong></td>
  <td>${badge(row.item_status||'-')}</td>
 </tr>`).join('')||'<tr><td colspan="9" class="fc-empty">No fee schedule found.</td></tr>';
}
function renderHistoryReceipts(rows=[]){
 $('historyReceiptCount').textContent=`${rows.length} receipt${rows.length===1?'':'s'}`;
 $('historyReceiptBody').innerHTML=rows.map(row=>`<tr>
  <td><strong>${esc(row.receipt_no||'-')}</strong></td>
  <td>${esc(row.receipt_date_display||row.receipt_date||'-')}</td>
  <td>${money(row.gross_amount)}</td>
  <td>${money(row.discount_amount)}</td>
  <td>${money(row.paid_amount)}</td>
  <td>${money(row.due_amount)}</td>
  <td>${esc(row.payment_methods||'-')}</td>
  <td>${badge(row.payment_status||'-')}</td>
  <td>
   <a class="fc-action" target="_blank" href="${apiUrl}?action=print_receipt&id=${Number(row.id)}" title="Print Receipt">
    <i data-lucide="printer"></i>
   </a>
  </td>
 </tr>`).join('')||'<tr><td colspan="9" class="fc-empty">No payment receipts found.</td></tr>';
}
async function openStudentHistory(id){
 historyStudent=studentRows.find(row=>Number(row.id)===id)||null;
 if(!historyStudent)return;

 $('historySubtitle').textContent=`${historyStudent.student_name} · Complete fee history`;
 $('historyScheduleBody').innerHTML='<tr><td colspan="9" class="fc-empty">Loading fee schedule...</td></tr>';
 $('historyReceiptBody').innerHTML='<tr><td colspan="9" class="fc-empty">Loading payment history...</td></tr>';

 try{
  const result=await request('student_history',{
   student_id:historyStudent.id,
   academic_year_id:historyStudent.academic_year_id
  });

  renderHistoryStudent(result.data.student||historyStudent);
  renderHistorySummary(result.data);
  renderHistorySchedule(result.data.schedule||[]);
  renderHistoryReceipts(result.data.receipts||[]);
  bootstrap.Modal.getOrCreateInstance($('studentHistoryModal')).show();
  window.lucide?.createIcons();
 }catch(error){
  message(error.message);
 }
}

async function loadStudents(){if(Number($('yearId').value||0)<=0){studentRows=[];renderStats({});renderStudents();return}try{const result=await request('students',filters());studentRows=result.data.records||[];renderStats(result.data.stats||{});renderStudents()}catch(error){message(error.message)}}
function renderStudentDetails(){const route=detail?.student?.transport_route_name||'Not Required';$('studentDetails').innerHTML=`<div><small>Student</small><strong>${esc(selectedStudent?.student_name||'-')}</strong></div><div><small>Admission No.</small><strong>${esc(selectedStudent?.admission_no||'-')}</strong></div><div><small>Class / Section</small><strong>${esc(selectedStudent?.class_name||'-')}${selectedStudent?.section_name?' / '+esc(selectedStudent.section_name):''}</strong></div><div><small>Transport Route</small><strong>${esc(route)}${Number(detail?.student?.route_bus_fee||0)>0?' · '+money(detail.student.route_bus_fee):''}</strong></div>`}
function renderDueItems(){const rows=detail?.items||[];$('dueItemBody').innerHTML=rows.map(row=>`<tr><td><span class="fc-type">${esc(row.item_type)}</span></td><td>${esc(row.item_name)}</td><td>${esc(row.period_label)}</td><td>${esc(row.due_date_display||row.due_date)}</td><td><strong>${money(row.balance_amount)}</strong></td></tr>`).join('')||'<tr><td colspan="5" class="fc-empty">No outstanding fee components are available.</td></tr>';$('breakdownHint').textContent=selectedStudent?`${selectedStudent.student_name} · Complete assigned schedule`:'Select a student.';$('breakdownBody').innerHTML=(detail?.schedule||[]).map(row=>`<tr><td>${esc(row.item_type)}</td><td>${esc(row.item_name)}</td><td>${esc(row.period_label)}</td><td>${esc(row.due_date_display||row.due_date)}</td><td>${money(row.original_amount)}</td><td>${money(row.discount_amount)}</td><td>${money(row.paid_amount)}</td><td>${money(row.balance_amount)}</td><td>${badge(row.item_status)}</td></tr>`).join('')||'<tr><td colspan="9" class="fc-empty">No schedule found.</td></tr>'}
function chargeRow(data={}){chargeCounter++;const id=chargeCounter;const options=(meta.charge_types||[]).map(row=>`<option value="${Number(row.id)}" data-code="${esc(row.charge_code)}">${esc(row.charge_name)}</option>`).join('');return `<div class="fc-charge-row" data-charge-row="${id}"><div><label class="form-label">Charge Type</label><select class="form-select js-charge-type">${options}</select></div><div><label class="form-label">Description</label><input class="form-control js-charge-description" maxlength="120" value="${esc(data.description||'')}"></div><div><label class="form-label">Amount</label><input class="form-control js-charge-amount" type="number" min="0" step="0.01" value="${Number(data.amount||0)}"></div><button class="btn-ui js-remove-charge" type="button"><i data-lucide="trash-2"></i></button></div>`}
function bindCharges(){document.querySelectorAll('.js-charge-amount').forEach(el=>el.oninput=()=>calculate(true));document.querySelectorAll('.js-remove-charge').forEach(el=>el.onclick=()=>{el.closest('[data-charge-row]')?.remove();calculate(true)});window.lucide?.createIcons()}
function addCharge(){if(!(meta.charge_types||[]).length){message('No active Additional Charge types are configured.');return}$('chargeRows').insertAdjacentHTML('beforeend',chargeRow());bindCharges();calculate(true)}
function additionalCharges(){return [...document.querySelectorAll('[data-charge-row]')].map(row=>({charge_type_id:Number(row.querySelector('.js-charge-type').value||0),description:row.querySelector('.js-charge-description').value.trim(),amount:Number(row.querySelector('.js-charge-amount').value||0)})).filter(row=>row.charge_type_id>0&&row.amount>0)}
function calculation(){
 const total=Number(
  detail?.assigned_total
  ?? detail?.student?.gross_amount
  ?? selectedStudent?.total_fee
  ?? 0
 );
 const previous=Number(
  detail?.previously_paid
  ?? detail?.student?.paid_amount
  ?? selectedStudent?.paid_amount
  ?? 0
 );
 const outstanding=Number(
  detail?.outstanding_balance
  ?? detail?.student?.balance_amount
  ?? selectedStudent?.balance_amount
  ?? 0
 );
 const additional=additionalCharges().reduce((sum,row)=>sum+row.amount,0);
 const discount=n('discount');
 const grand=Math.max(0,outstanding+additional-discount);
 const paid=n('amount');
 const balance=Math.max(0,grand-paid);
 return{total,previous,outstanding,additional,discount,grand,paid,balance};
}
function calculate(syncAutoPaid=false){
 let c=calculation();

 /*
  * Default behaviour is "Pay Full Current Balance".
  * Once the user types a Paid Amount manually, preserve that partial amount.
  * Before manual editing, Discount / Additional Charge changes keep the
  * Paid Amount synchronized with the current Total Payable.
  */
 if(syncAutoPaid&&!paidAmountManual){
  $('amount').value=c.grand>0?c.grand.toFixed(2):'';
  c=calculation();
 }

 $('amount').max=c.grand>0?c.grand.toFixed(2):'0';
 $('calcTotalFee').textContent=money(c.total);
 $('calcPrevious').textContent=money(c.previous);
 $('calcOutstanding').textContent=money(c.outstanding);
 $('calcAdditional').textContent=money(c.additional);
 $('calcDiscount').textContent=money(c.discount);
 $('calcGrand').textContent=money(c.grand);
 $('calcPaid').textContent=money(c.paid);
 $('calcBalance').textContent=money(c.balance);

 return c;
}
async function loadDetail(resetPaid=true){
 if(!selectedStudent)return;

 const existingPaid=String($('amount').value||'');

 const result=await request('detail',{
  student_id:selectedStudent.id,
  academic_year_id:selectedStudent.academic_year_id,
  payment_date:$('paymentDate').value
 });

 detail=result.data;
 renderStudentDetails();
 renderDueItems();

 if(resetPaid){
  paidAmountManual=false;
 }

 if(!resetPaid&&paidAmountManual&&existingPaid!==''){
  $('amount').value=existingPaid;
  const current=calculation();

  if(current.paid>current.grand){
   $('amount').value=current.grand>0?current.grand.toFixed(2):'';
   paidAmountManual=false;
  }
 }

 calculate(true);
}
async function openCollect(id){
 selectedStudent=studentRows.find(row=>Number(row.id)===id)||null;
 if(!selectedStudent)return;

 $('modalStudentId').value=String(id);
 $('paymentDate').value=localToday();
 $('discount').value='0';
 $('amount').value='';
 $('reference').value='';
 $('notes').value='';
 $('chargeRows').innerHTML='';
 paidAmountManual=false;

 try{
  await loadDetail(true);
  bootstrap.Modal.getOrCreateInstance($('collectFeeModal')).show();
  window.lucide?.createIcons();
 }catch(error){
  message(error.message);
 }
}
function setButtons(value){$('saveFeeBtn').disabled=value;$('savePrintFeeBtn').disabled=value}
async function save(printReceipt){
 const c=calculate(false);
 const grand=Math.round(c.grand*100)/100;
 const paid=Math.round(c.paid*100)/100;
 const discount=Math.round(c.discount*100)/100;

 if(!selectedStudent){message('Select a student.');return}
 if(grand<=0){message('There is no payable amount.');return}
 if(paid<=0){message('Enter a valid Paid Amount.');$('amount').focus();return}
 if(paid>grand+0.009){message('Paid Amount cannot exceed Total Payable Now.');$('amount').focus();return}
 if(Number($('methodId').value||0)<=0){message('Select a Payment Mode.');return}

 let printWindow=printReceipt?window.open('about:blank','_blank'):null;
 setButtons(true);

 try{
  const result=await request('collect',{
   student_id:selectedStudent.id,
   academic_year_id:selectedStudent.academic_year_id,
   payment_date:$('paymentDate').value,
   payment_method_id:Number($('methodId').value),
   reference_no:$('reference').value.trim(),
   remarks:$('notes').value.trim(),
   discount_amount:discount,
   paid_amount:paid,
   additional_charges:additionalCharges()
  },'POST');

  bootstrap.Modal.getInstance($('collectFeeModal'))?.hide();
  message(result.message,true);
  await loadStudents();
  await loadReceipts();

  if(printReceipt&&result.data?.receipt_id){
   const url=`${apiUrl}?action=print_receipt&id=${encodeURIComponent(result.data.receipt_id)}`;
   if(printWindow)printWindow.location.href=url;
   else window.open(url,'_blank');
  }else if(printWindow){
   printWindow.close();
  }
 }catch(error){
  if(printWindow)printWindow.close();
  message(error.message);
 }finally{
  setButtons(false);
 }
}
function renderReceiptPagination(p={}){const total=Number(p.total||0),page=Number(p.page||1),per=Number(p.per_page||10),last=Math.max(1,Number(p.last_page||1)),start=total?((page-1)*per)+1:0,end=Math.min(page*per,total);$('receiptCount').textContent=`${total} receipt${total===1?'':'s'}`;$('receiptPageInfo').textContent=`Showing ${start}-${end} of ${total}`;let html=`<button class="fc-page-button" data-page="${page-1}" ${page<=1?'disabled':''}>‹</button>`;for(let i=Math.max(1,page-2);i<=Math.min(last,page+2);i++)html+=`<button class="fc-page-button ${i===page?'active':''}" data-page="${i}">${i}</button>`;html+=`<button class="fc-page-button" data-page="${page+1}" ${page>=last?'disabled':''}>›</button>`;$('receiptPagination').innerHTML=html;document.querySelectorAll('.fc-page-button').forEach(b=>b.onclick=()=>{if(!b.disabled){receiptPage=Number(b.dataset.page);loadReceipts()}})}
async function loadReceipts(){try{const result=await request('receipts',{academic_year_id:$('yearId').value,search:$('receiptSearch').value.trim(),from_date:$('fromDate').value,to_date:$('toDate').value,status:$('receiptStatus').value,payment_method_id:$('receiptMethod').value,page:receiptPage,per_page:10});const rows=result.data.records||[];$('statReceipts').textContent=Number(result.data.pagination?.total||0).toLocaleString('en-IN');renderReceiptPagination(result.data.pagination||{});$('receiptBody').innerHTML=rows.map(row=>`<tr><td><strong>${esc(row.receipt_no)}</strong></td><td>${esc(row.receipt_date_display||row.receipt_date)}</td><td>${esc(row.student_name)}</td><td>${money(row.gross_amount)}</td><td>${money(row.discount_amount)}</td><td>${money(row.paid_amount)}</td><td>${money(row.due_amount)}</td><td>${esc(row.payment_methods||'-')}</td><td>${badge(row.payment_status)}</td><td><div class="fc-receipt-actions"><a class="fc-action" target="_blank" href="${apiUrl}?action=print_receipt&id=${Number(row.id)}" title="Reprint"><i data-lucide="printer"></i></a><button class="fc-action js-manage" data-id="${Number(row.id)}" type="button" title="Manage"><i data-lucide="pencil"></i></button>${row.payment_status!=='reversed'?`<button class="fc-action js-reverse" data-id="${Number(row.id)}" type="button" title="Reverse"><i data-lucide="rotate-ccw"></i></button>`:''}${row.payment_status==='reversed'?`<button class="fc-action danger js-delete" data-id="${Number(row.id)}" type="button" title="Delete"><i data-lucide="trash-2"></i></button>`:''}</div></td></tr>`).join('')||'<tr><td colspan="10" class="fc-empty">No payment history found.</td></tr>';document.querySelectorAll('.js-manage').forEach(b=>b.onclick=()=>openReceipt(Number(b.dataset.id)));document.querySelectorAll('.js-reverse').forEach(b=>b.onclick=()=>receiptAction('reverse',Number(b.dataset.id)));document.querySelectorAll('.js-delete').forEach(b=>b.onclick=()=>receiptAction('delete',Number(b.dataset.id)));updateExport();window.lucide?.createIcons()}catch(error){message(error.message)}}
function updateExport(){const q=new URLSearchParams({action:'export_receipts',academic_year_id:$('yearId').value||'',search:$('receiptSearch').value.trim(),from_date:$('fromDate').value,to_date:$('toDate').value,status:$('receiptStatus').value,payment_method_id:$('receiptMethod').value});$('exportReceipts').href=apiUrl+'?'+q}
async function openReceipt(id){try{const result=await request('receipt_detail',{id});const receipt=result.data.receipt,payment=result.data.payments?.[0];$('manageReceiptId').value=String(id);$('manageReceiptNo').value=receipt.receipt_no;fill('manageMethodId',meta.methods||[],'id','method_name');$('manageMethodId').value=String(payment?.payment_method_id||'');$('manageReference').value=payment?.reference_no||'';$('manageNotes').value=receipt.clean_notes||'';bootstrap.Modal.getOrCreateInstance($('receiptManageModal')).show()}catch(error){message(error.message)}}
async function receiptAction(action,id){const text=action==='reverse'?'Reverse this receipt and restore all allocated balances?':'Delete this reversed receipt?';if(!confirm(text))return;try{const result=await request(action,{id},'POST');message(result.message,true);await loadStudents();await loadReceipts()}catch(error){message(error.message)}}
async function init(){try{const result=await request('meta');meta=result.data;fill('yearId',meta.years||[],'id','year_name','Select Academic Year','');const current=(meta.years||[]).find(row=>Number(row.is_current)===1)||(meta.years||[])[0];if(current)$('yearId').value=String(current.id);fill('methodId',meta.methods||[],'id','method_name');fill('manageMethodId',meta.methods||[],'id','method_name');fill('receiptMethod',meta.methods||[],'id','method_name','All Payment Modes','all');const cash=(meta.methods||[]).find(row=>row.method_key==='cash');if(cash)$('methodId').value=String(cash.id);refreshClasses();$('paymentDate').value=localToday();await loadStudents();await loadReceipts()}catch(error){message(error.message)}}
$('receiptManageForm').onsubmit=async e=>{e.preventDefault();try{const result=await request('update_receipt',{id:Number($('manageReceiptId').value),payment_method_id:Number($('manageMethodId').value),reference_no:$('manageReference').value.trim(),remarks:$('manageNotes').value.trim()},'POST');bootstrap.Modal.getInstance($('receiptManageModal'))?.hide();message(result.message,true);await loadReceipts()}catch(error){message(error.message)}};
document.querySelectorAll('.fc-tab').forEach(button=>button.onclick=()=>{document.querySelectorAll('.fc-tab').forEach(x=>x.classList.remove('active'));document.querySelectorAll('.fc-panel').forEach(x=>x.classList.remove('active'));button.classList.add('active');document.querySelector(`[data-panel="${button.dataset.tab}"]`)?.classList.add('active')});
$('yearId').onchange=async()=>{refreshClasses();receiptPage=1;await loadStudents();await loadReceipts()};$('classId').onchange=async()=>{refreshSections();await loadStudents()};$('sectionId').onchange=loadStudents;$('feeStatusFilter').onchange=loadStudents;$('studentSearch').oninput=()=>{clearTimeout(searchTimer);searchTimer=setTimeout(loadStudents,250)};$('studentFilterReset').onclick=async()=>{$('studentSearch').value='';$('classId').value='';refreshSections();$('feeStatusFilter').value='all';const current=(meta.years||[]).find(row=>Number(row.is_current)===1)||(meta.years||[])[0];$('yearId').value=current?String(current.id):'';refreshClasses();await loadStudents()};$('addChargeBtn').onclick=addCharge;$('discount').oninput=()=>calculate(true);$('amount').oninput=()=>{paidAmountManual=true;calculate(false)};$('paymentDate').onchange=()=>loadDetail(true);$('saveFeeBtn').onclick=()=>save(false);$('savePrintFeeBtn').onclick=()=>save(true);$('topCollectBtn').onclick=()=>{$('studentFeeCard').scrollIntoView({behavior:'smooth',block:'start'});setTimeout(()=>$('studentSearch').focus(),300)};$('refreshBtn').onclick=async()=>{await loadStudents();await loadReceipts();message('Fee Collection refreshed.',true)};$('receiptSearch').oninput=()=>{clearTimeout(receiptTimer);receiptTimer=setTimeout(()=>{receiptPage=1;loadReceipts()},300)};['fromDate','toDate','receiptStatus','receiptMethod'].forEach(id=>$(id).onchange=()=>{receiptPage=1;loadReceipts()});$('receiptReset').onclick=()=>{$('receiptSearch').value='';$('fromDate').value='';$('toDate').value='';$('receiptStatus').value='all';$('receiptMethod').value='all';receiptPage=1;loadReceipts()};
init();window.lucide?.createIcons();
})();
</script>
<?php require dirname(__DIR__).'/includes/layout-end.php'; ?>

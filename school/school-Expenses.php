<?php
declare(strict_types=1);

$pageTitle='Expense Management';
$pageKey='school_expenses';

if (!defined('SCHOOL_PAGE_KEY')) {
    define('SCHOOL_PAGE_KEY', 'school_expenses');
}

require dirname(__DIR__).'/includes/layout-start.php';

if(session_status()!==PHP_SESSION_ACTIVE)session_start();
if(empty($_SESSION['expense_csrf_token'])||!is_string($_SESSION['expense_csrf_token'])){
    $_SESSION['expense_csrf_token']=bin2hex(random_bytes(32));
}
$expenseCsrf=$_SESSION['expense_csrf_token'];
?>
<style>
.exp-page{display:grid;gap:16px}
.exp-page .page-title{font-size:28px;line-height:1.1}
.exp-page .page-subtitle{margin-top:4px}
.exp-message{display:none}.exp-message.show{display:block}
.exp-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.exp-stat{border-radius:14px;min-height:110px;padding:18px 20px;display:flex;align-items:center;gap:14px;color:#fff;position:relative;overflow:hidden;box-shadow:0 12px 28px rgba(15,23,42,.08)}
.exp-stat::after{content:"";position:absolute;width:110px;height:110px;border-radius:50%;right:-38px;top:-40px;background:rgba(255,255,255,.08)}
.exp-stat.purple{background:linear-gradient(135deg,#7448e8,#4b36cf)}
.exp-stat.green{background:linear-gradient(135deg,#45c783,#1ea568)}
.exp-stat.orange{background:linear-gradient(135deg,#ffb327,#ff8a17)}
.exp-stat.blue{background:linear-gradient(135deg,#4696ef,#2469d5)}
.exp-stat-icon{width:50px;height:50px;border-radius:50%;background:rgba(255,255,255,.16);display:grid;place-items:center}
.exp-stat-icon svg{width:25px;height:25px}
.exp-stat strong{display:block;font-size:25px;line-height:1}
.exp-stat small{display:block;font-size:11px;font-weight:700;opacity:.95;margin-bottom:6px}
.exp-stat .trend{font-size:9px;font-weight:700;opacity:.94;margin-top:8px}
.exp-tabs{display:flex;gap:8px;overflow:auto;padding:10px}
.exp-tab{border:1px solid var(--border-soft,#e7ebf3);background:var(--card-bg,#fff);color:var(--text-main,#101b46);border-radius:9px;padding:9px 12px;font-size:11px;font-weight:800;white-space:nowrap}
.exp-tab.active{color:#fff;border-color:transparent;background:linear-gradient(135deg,#6747e8,#2f62d7)}
.exp-panel{display:none}.exp-panel.active{display:block}
.exp-card{border-radius:14px;overflow:hidden}
.exp-card-head{padding:14px 16px;border-bottom:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;align-items:center;gap:10px}
.exp-filter{padding:14px 16px;display:grid;grid-template-columns:minmax(220px,1.4fr) repeat(5,minmax(130px,.7fr)) auto;gap:10px;border-bottom:1px solid var(--border-soft,#e7ebf3)}
.exp-table-wrap{overflow:auto}.exp-table{min-width:1360px}
.exp-table th{font-size:10px}.exp-table td{font-size:11px;vertical-align:middle}
.exp-empty{padding:40px 18px!important;text-align:center!important;color:var(--text-muted,#64748b)}
.exp-badge{display:inline-flex;align-items:center;padding:5px 9px;border-radius:999px;font-size:9px;font-weight:800;text-transform:capitalize}
.exp-badge.paid,.exp-badge.expense{color:#16834f;background:#e8f8ef}
.exp-badge.pending,.exp-badge.adjustment{color:#9a6700;background:#fff7d6}
.exp-badge.cancelled,.exp-badge.refund{color:#dc2626;background:#fff0f1}
.exp-actions{display:flex;gap:5px;flex-wrap:wrap}
.exp-action{width:30px;height:30px;display:grid;place-items:center;border:1px solid #d7def1;border-radius:7px;background:#fff;color:#4f46e5}
.exp-action.danger{color:#dc2626}
.exp-pagination{padding:14px 16px;border-top:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;align-items:center;gap:10px}
.exp-page-buttons{display:flex;gap:6px}
.exp-page-button{min-width:34px;height:34px;border:1px solid var(--border-soft,#e7ebf3);background:#fff;border-radius:8px;font-size:11px;font-weight:800}
.exp-page-button.active{color:#fff;border-color:transparent;background:linear-gradient(135deg,#6547e8,#315ed8)}
.exp-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.exp-form-grid .full{grid-column:1/-1}
.exp-dynamic{grid-column:1/-1;padding:14px;border:1px solid var(--border-soft,#e7ebf3);border-radius:12px;background:rgba(99,102,241,.035)}
.exp-dynamic-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.exp-help{font-size:10px;color:var(--text-muted,#64748b);margin-top:5px}
.exp-purpose-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:12px}
.exp-purpose-head strong{font-size:13px}
.exp-purpose-tag{display:inline-flex;align-items:center;padding:4px 8px;border-radius:999px;background:rgba(99,102,241,.1);color:#4f46e5;font-size:9px;font-weight:800;white-space:nowrap}
.exp-dynamic-grid .exp-field-full{grid-column:1/-1}
.exp-dynamic-grid .form-label .required-mark{color:#dc2626}
.exp-vehicle-note{margin-top:5px;font-size:9px;color:var(--text-muted,#64748b)}
.exp-no-vehicles{color:#dc2626;font-weight:700}
@media(max-width:767px){.exp-purpose-head{flex-direction:column}.exp-dynamic-grid .exp-field-full{grid-column:auto}}
.refund-box{border-color:#fecaca;background:#fff7f7}
.net-negative{color:#dc2626}.net-positive{color:#16834f}
#expenseModal .modal-dialog,#viewModal .modal-dialog{max-height:calc(100dvh - 32px);margin:16px auto}
#expenseModal .modal-content,#viewModal .modal-content{max-height:calc(100dvh - 32px);overflow:hidden}
#expenseModal form{display:flex;flex-direction:column;max-height:calc(100dvh - 32px)}
#expenseModal .modal-body,#viewModal .modal-body{overflow-y:auto;min-height:0}

/* =========================================================
   RESPONSIVE LAYOUT UPDATE
   Supports 1024px laptops, 1366px desktops, Full HD,
   ultrawide screens, tablets and mobile devices.
   ========================================================= */

.exp-page{
    width:100%;
    max-width:100%;
    min-width:0;
    overflow:visible;
}

.exp-page .page-heading{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:16px;
    min-width:0;
}

.exp-page .page-heading>div:first-child{
    min-width:0;
    flex:1 1 auto;
}

.exp-page .page-actions{
    display:flex;
    align-items:center;
    justify-content:flex-end;
    flex-wrap:wrap;
    gap:8px;
    flex:0 1 auto;
}

.exp-page .page-actions .btn-ui{
    min-height:40px;
    white-space:nowrap;
}

.exp-stats{
    width:100%;
    min-width:0;
}

.exp-stat{
    min-width:0;
}

.exp-stat-icon{
    flex:0 0 auto;
}

.exp-stat>div{
    min-width:0;
    position:relative;
    z-index:1;
}

.exp-stat strong{
    max-width:100%;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
}

.exp-tabs{
    width:100%;
    max-width:100%;
    overflow-x:auto;
    overflow-y:hidden;
    -webkit-overflow-scrolling:touch;
    scrollbar-width:thin;
}

.exp-tab{
    flex:0 0 auto;
}

.exp-panel,
.exp-card{
    width:100%;
    min-width:0;
}

.exp-card-head{
    min-width:0;
}

.exp-card-head>*{
    min-width:0;
}

.exp-filter{
    width:100%;
    min-width:0;
    align-items:center;
}

.exp-filter .form-control,
.exp-filter .form-select,
.exp-filter .btn-ui{
    width:100%;
    min-width:0;
    min-height:40px;
}

.exp-table-wrap{
    width:100%;
    max-width:100%;
    min-width:0;
    overflow-x:auto;
    overflow-y:visible;
    -webkit-overflow-scrolling:touch;
    scrollbar-width:thin;
}

.exp-table{
    width:100%;
    border-collapse:separate;
    border-spacing:0;
}

.exp-table thead th{
    position:sticky;
    top:0;
    z-index:2;
    background:var(--card-bg,#fff);
    white-space:nowrap;
}

.exp-table td{
    max-width:260px;
    overflow-wrap:anywhere;
}

.exp-table td:nth-child(2),
.exp-table td:nth-child(3),
.exp-table td:nth-child(4),
.exp-table td:nth-child(8),
.exp-table td:nth-child(9),
.exp-table td:nth-child(10),
.exp-table td:nth-child(11),
.exp-table td:nth-child(12),
.exp-table td:nth-child(13),
.exp-table td:nth-child(14){
    white-space:nowrap;
}

.exp-actions{
    flex-wrap:nowrap;
}

.exp-action{
    flex:0 0 auto;
}

.exp-pagination{
    min-width:0;
}

.exp-page-buttons{
    max-width:100%;
    overflow-x:auto;
    padding-bottom:2px;
}

.exp-page-button{
    flex:0 0 auto;
}

.exp-form-grid,
.exp-dynamic-grid{
    width:100%;
    min-width:0;
}

.exp-form-grid>div,
.exp-dynamic-grid>div{
    min-width:0;
}

.exp-form-grid .form-control,
.exp-form-grid .form-select,
.exp-dynamic-grid .form-control,
.exp-dynamic-grid .form-select{
    width:100%;
    min-width:0;
}

#expenseModal .modal-dialog{
    width:min(1040px,calc(100vw - 32px));
    max-width:1040px;
}

#viewModal .modal-dialog{
    width:min(1040px,calc(100vw - 32px));
    max-width:1040px;
}

#expenseModal .modal-header,
#viewModal .modal-header{
    align-items:flex-start;
    gap:12px;
}

#expenseModal .modal-header>div,
#viewModal .modal-header>div{
    min-width:0;
}

#expenseModal .modal-title,
#viewModal .modal-title{
    overflow-wrap:anywhere;
}

#expenseModal .modal-footer,
#viewModal .modal-footer{
    flex-wrap:wrap;
}

#expenseModal .modal-footer .btn-ui,
#viewModal .modal-footer .btn-ui{
    min-height:40px;
}

@media(min-width:1800px){
    .exp-page{
        gap:18px;
    }

    .exp-stats{
        gap:16px;
    }

    .exp-stat{
        min-height:118px;
        padding:20px 22px;
    }

    .exp-filter{
        grid-template-columns:
            minmax(280px,1.6fr)
            repeat(3,minmax(150px,.75fr))
            repeat(2,minmax(145px,.7fr))
            auto;
    }

    .exp-table{
        min-width:100%;
    }

    .exp-table th,
    .exp-table td{
        padding:12px 11px;
    }
}

@media(min-width:1440px) and (max-width:1799px){
    .exp-filter{
        grid-template-columns:
            minmax(250px,1.5fr)
            repeat(3,minmax(135px,.75fr))
            repeat(2,minmax(132px,.7fr))
            auto;
    }

    .exp-table{
        min-width:1360px;
    }
}

@media(min-width:1201px) and (max-width:1439px){
    .exp-page .page-actions{
        max-width:560px;
    }

    .exp-filter{
        grid-template-columns:
            minmax(220px,1.35fr)
            repeat(3,minmax(125px,.75fr))
            repeat(2,minmax(125px,.7fr))
            auto;
    }

    .exp-table{
        min-width:1320px;
    }

    .exp-stat{
        padding:16px;
        gap:12px;
    }

    .exp-stat-icon{
        width:46px;
        height:46px;
    }

    .exp-stat strong{
        font-size:22px;
    }
}

@media(min-width:992px) and (max-width:1200px){
    .exp-page .page-heading{
        align-items:stretch;
    }

    .exp-page .page-actions{
        max-width:460px;
    }

    .exp-stats{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }

    .exp-filter{
        grid-template-columns:repeat(3,minmax(0,1fr));
    }

    .exp-filter #search{
        grid-column:1/-1;
    }

    .exp-filter #resetBtn{
        width:auto;
        min-width:120px;
        justify-self:start;
    }

    .exp-table{
        min-width:1280px;
    }

    #expenseModal .modal-dialog,
    #viewModal .modal-dialog{
        width:calc(100vw - 24px);
        max-width:none;
        margin:12px auto;
    }
}

@media(min-width:768px) and (max-width:991px){
    .exp-page{
        gap:14px;
    }

    .exp-page .page-heading{
        flex-direction:column;
        align-items:stretch;
    }

    .exp-page .page-actions{
        justify-content:flex-start;
        max-width:none;
    }

    .exp-page .page-actions .btn-ui{
        flex:0 1 auto;
    }

    .exp-stats{
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:12px;
    }

    .exp-filter{
        grid-template-columns:repeat(2,minmax(0,1fr));
        padding:12px;
    }

    .exp-filter #search{
        grid-column:1/-1;
    }

    .exp-table{
        min-width:1220px;
    }

    .exp-form-grid,
    .exp-dynamic-grid{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }

    #expenseModal .modal-dialog,
    #viewModal .modal-dialog{
        width:calc(100vw - 20px);
        max-width:none;
        margin:10px auto;
        max-height:calc(100dvh - 20px);
    }

    #expenseModal .modal-content,
    #viewModal .modal-content,
    #expenseModal form{
        max-height:calc(100dvh - 20px);
    }
}

@media(max-width:767px){
    .exp-page{
        gap:12px;
    }

    .exp-page .page-heading{
        flex-direction:column;
        align-items:stretch;
        gap:12px;
    }

    .exp-page .page-title{
        font-size:24px;
    }

    .exp-page .page-actions{
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        width:100%;
    }

    .exp-page .page-actions .btn-ui{
        width:100%;
        min-width:0;
        justify-content:center;
    }

    .exp-stats{
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:10px;
    }

    .exp-stat{
        min-height:102px;
        padding:14px;
        gap:10px;
    }

    .exp-stat-icon{
        width:44px;
        height:44px;
    }

    .exp-stat-icon svg{
        width:21px;
        height:21px;
    }

    .exp-stat strong{
        font-size:20px;
    }

    .exp-tabs{
        padding:8px;
    }

    .exp-tab{
        min-height:38px;
        padding:8px 11px;
    }

    .exp-card-head{
        align-items:flex-start;
        flex-direction:column;
        padding:13px;
    }

    .exp-filter{
        grid-template-columns:1fr;
        padding:12px;
    }

    .exp-filter #search{
        grid-column:auto;
    }

    .exp-filter #resetBtn{
        width:100%;
        justify-content:center;
    }

    .exp-table{
        min-width:1120px;
    }

    .exp-table th,
    .exp-table td{
        padding:10px 9px;
    }

    .exp-pagination{
        align-items:stretch;
        flex-direction:column;
        padding:12px;
    }

    .exp-page-buttons{
        width:100%;
    }

    .exp-form-grid,
    .exp-dynamic-grid{
        grid-template-columns:1fr;
    }

    .exp-form-grid .full,
    .exp-dynamic{
        grid-column:auto;
    }

    #expenseModal .modal-dialog,
    #viewModal .modal-dialog{
        width:calc(100vw - 16px);
        max-width:none;
        margin:8px auto;
        max-height:calc(100dvh - 16px);
    }

    #expenseModal .modal-content,
    #viewModal .modal-content,
    #expenseModal form{
        max-height:calc(100dvh - 16px);
    }

    #expenseModal .modal-body,
    #viewModal .modal-body{
        padding:13px;
    }

    #expenseModal .modal-footer,
    #viewModal .modal-footer{
        display:grid;
        grid-template-columns:1fr 1fr;
        width:100%;
    }

    #expenseModal .modal-footer .btn-ui,
    #viewModal .modal-footer .btn-ui{
        width:100%;
        justify-content:center;
    }
}

@media(max-width:480px){
    .exp-page .page-actions{
        grid-template-columns:1fr;
    }

    .exp-stats{
        grid-template-columns:1fr;
    }

    .exp-stat{
        min-height:96px;
    }

    .exp-table{
        min-width:1060px;
    }

    #expenseModal .modal-footer,
    #viewModal .modal-footer{
        grid-template-columns:1fr;
    }
}

@media(max-height:760px) and (min-width:768px){
    #expenseModal .modal-dialog,
    #viewModal .modal-dialog{
        margin:8px auto;
        max-height:calc(100dvh - 16px);
    }

    #expenseModal .modal-content,
    #viewModal .modal-content,
    #expenseModal form{
        max-height:calc(100dvh - 16px);
    }

    #expenseModal .modal-header,
    #viewModal .modal-header{
        padding-top:11px;
        padding-bottom:11px;
    }

    #expenseModal .modal-footer,
    #viewModal .modal-footer{
        padding-top:10px;
        padding-bottom:10px;
    }
}

@media print{
    .exp-page .page-actions,
    .exp-tabs,
    .exp-filter,
    .exp-pagination,
    .exp-actions{
        display:none!important;
    }

    .exp-stats{
        grid-template-columns:repeat(4,1fr);
    }

    .exp-table-wrap{
        overflow:visible!important;
    }

    .exp-table{
        min-width:0!important;
        width:100%!important;
    }

    .exp-table th,
    .exp-table td{
        font-size:8px!important;
        padding:5px!important;
    }
}

</style>

<div class="exp-page">
<div class="page-heading">
 <div><h1 class="page-title">Expense Management</h1><p class="page-subtitle">Simple manual expenses, purpose-based details and refund tracking.</p></div>
 <div class="page-actions">
  <button id="addExpenseBtn" class="btn-ui btn-primary-ui" type="button" aria-label="Add Expense"><i data-lucide="plus"></i> Add Expense</button>
  <button id="addRefundBtn" class="btn-ui" type="button" aria-label="Add Refund"><i data-lucide="rotate-ccw"></i> Add Refund</button>
  <button id="exportBtn" class="btn-ui" type="button" aria-label="Export Expenses"><i data-lucide="download"></i> Export</button>
  <button id="printBtn" class="btn-ui" type="button" aria-label="Print Expenses"><i data-lucide="printer"></i> Print</button>
  <button id="refreshBtn" class="btn-ui" type="button" aria-label="Refresh Expenses"><i data-lucide="refresh-cw"></i> Refresh</button>
 </div>
</div>

<div id="expenseMessage" class="alert exp-message"></div>

<section class="exp-stats">
 <article class="exp-stat purple"><span class="exp-stat-icon"><i data-lucide="indian-rupee"></i></span><div><small>Net Expense</small><strong id="statTotal">₹0</strong><div class="trend">Expenses minus refunds</div></div></article>
 <article class="exp-stat green"><span class="exp-stat-icon"><i data-lucide="calendar-days"></i></span><div><small>Today's Net Expense</small><strong id="statToday">₹0</strong><div class="trend">Today's transactions</div></div></article>
 <article class="exp-stat orange"><span class="exp-stat-icon"><i data-lucide="clock-3"></i></span><div><small>Pending Expense</small><strong id="statPending">₹0</strong><div class="trend">Expected / unpaid</div></div></article>
 <article class="exp-stat blue"><span class="exp-stat-icon"><i data-lucide="undo-2"></i></span><div><small>Refunded / Adjusted</small><strong id="statReturned">₹0</strong><div class="trend">Amounts returned</div></div></article>
</section>

<section class="ui-card exp-tabs">
 <button class="exp-tab active" data-tab="list" type="button">Expense Dashboard</button>
 <button class="exp-tab" data-tab="summary" type="button">Purpose Summary</button>
 <button class="exp-tab" data-tab="history" type="button">Transaction History</button>
</section>

<section class="exp-panel active" data-panel="list">
<section class="ui-card exp-card">
 <div class="exp-card-head"><strong>Expense & Refund List</strong><small id="recordInfo" class="text-muted">Loading...</small></div>
 <div class="exp-filter">
  <input id="search" class="form-control" placeholder="Search expense, party, bus, issue...">
  <select id="purposeFilter" class="form-select"><option value="all">All Purposes</option></select>
  <select id="typeFilter" class="form-select"><option value="all">All Transactions</option><option value="expense">Expenses</option><option value="refund">Refunds / Returns</option><option value="adjustment">Adjustments</option></select>
  <select id="statusFilter" class="form-select"><option value="all">All Statuses</option><option value="paid">Paid / Received</option><option value="pending">Pending</option><option value="cancelled">Cancelled</option></select>
  <select id="modeFilter" class="form-select"><option value="all">All Payment Methods</option></select>
  <input id="fromDate" class="form-control" type="date">
  <input id="toDate" class="form-control" type="date">
  <button id="resetBtn" class="btn-ui" type="button"><i data-lucide="rotate-ccw"></i> Reset</button>
 </div>
 <div class="exp-table-wrap"><table class="data-table exp-table"><thead><tr><th>#</th><th>Expense No.</th><th>Date</th><th>Type</th><th>Purpose</th><th>Details</th><th>Paid To / Received From</th><th>Amount</th><th>Tax</th><th>Net Impact</th><th>Method</th><th>Reference</th><th>Status</th><th>Actions</th></tr></thead><tbody id="expenseBody"><tr><td colspan="14" class="exp-empty">Loading...</td></tr></tbody></table></div>
 <div class="exp-pagination"><small id="pageInfo" class="text-muted"></small><div id="pagination" class="exp-page-buttons"></div></div>
</section>
</section>

<section class="exp-panel" data-panel="summary"><section class="ui-card exp-card"><div class="exp-card-head"><strong>Purpose Summary</strong></div><div class="exp-table-wrap"><table class="data-table exp-table"><thead><tr><th>Purpose</th><th>Entries</th><th>Paid Expense</th><th>Pending</th><th>Cancelled</th><th>Refunded / Adjusted</th><th>Net Expense</th></tr></thead><tbody id="summaryBody"></tbody></table></div></section></section>

<section class="exp-panel" data-panel="history"><section class="ui-card exp-card"><div class="exp-card-head"><strong>Expense Transaction History</strong></div><div class="exp-table-wrap"><table class="data-table exp-table"><thead><tr><th>Date</th><th>Action</th><th>Expense No.</th><th>User</th><th>Details</th></tr></thead><tbody id="historyBody"></tbody></table></div></section></section>
</div>

<div class="modal fade" id="expenseModal" tabindex="-1">
<div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content"><form id="expenseForm">
 <div class="modal-header"><div><h5 id="expenseModalTitle" class="modal-title">Add Expense</h5><small id="expenseModalHint" class="text-muted">Enter the expense manually.</small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
 <div class="modal-body exp-form-grid">
  <input id="expenseId" type="hidden">
  <div><label class="form-label">Transaction Type *</label><select id="transactionType" class="form-select"><option value="expense">Expense Payment</option><option value="refund">Refund / Return Received</option><option value="adjustment">Amount Adjustment</option></select></div>
  <div><label class="form-label">Payment Date *</label><input id="expenseDate" class="form-control" type="date" required></div>

  <div id="parentExpenseWrap" class="full exp-dynamic refund-box" hidden>
   <label class="form-label">Original Expense *</label>
   <select id="parentExpenseId" class="form-select"><option value="">Select original expense</option></select>
   <div id="parentExpenseHelp" class="exp-help">Select the original payment being returned or adjusted.</div>
  </div>

  <div class="full">
   <label class="form-label">Purpose * <span class="text-muted">(search and select)</span></label>
   <input id="expensePurposeSearch" class="form-control" list="purposeList" autocomplete="off" placeholder="Type to search: Bus, Salary, Maintenance..." required>
   <datalist id="purposeList"></datalist>
   <input id="expensePurposeCode" type="hidden">
  </div>

  <div id="purposeOptionWrap">
   <label class="form-label">Expense Type / Issue</label>
   <select id="purposeOption" class="form-select"><option value="">Select option</option></select>
  </div>
  <div><label class="form-label" id="partyLabel">Paid To *</label><input id="paidTo" class="form-control" maxlength="150" required></div>

  <div id="purposeDynamicDetails" class="exp-dynamic" hidden>
   <div class="exp-purpose-head">
    <div>
     <strong id="purposeDynamicTitle">Purpose Details</strong>
     <div id="purposeDynamicHelp" class="exp-help">Only fields relevant to the selected Expense Purpose are shown.</div>
    </div>
    <span id="purposeDynamicTag" class="exp-purpose-tag">Dynamic fields</span>
   </div>
   <div id="purposeDynamicGrid" class="exp-dynamic-grid"></div>
  </div>

  <div><label class="form-label" id="amountLabel">Amount Paid *</label><input id="expenseAmount" class="form-control" type="number" min="0.01" step="0.01" required></div>
  <div><label class="form-label">Tax</label><input id="expenseTax" class="form-control" type="number" min="0" step="0.01" value="0"></div>
  <div><label class="form-label">Payment Method *</label><select id="paymentMethod" class="form-select" required></select></div>
  <div><label class="form-label">Reference No.</label><input id="referenceNo" class="form-control" maxlength="100"></div>
  <div><label class="form-label">Status *</label><select id="expenseStatus" class="form-select"><option value="paid">Paid / Received</option><option value="pending">Pending</option><option value="cancelled">Cancelled</option></select></div>
  <div class="full"><label class="form-label">Description / Remarks</label><textarea id="expenseDescription" class="form-control" rows="3" maxlength="500"></textarea></div>
 </div>
 <div class="modal-footer"><button type="button" class="btn-ui" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn-ui btn-primary-ui">Save Transaction</button></div>
</form></div></div>
</div>

<div class="modal fade" id="viewModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content"><div class="modal-header"><div><h5 class="modal-title">Expense Details</h5><small id="viewSubtitle" class="text-muted"></small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div id="viewContent" class="exp-dynamic-grid"></div><div class="mt-3"><strong>Description</strong></div><div id="viewDescription" class="alert alert-light mt-2 mb-0"></div></div><div class="modal-footer"><button type="button" class="btn-ui" data-bs-dismiss="modal">Close</button></div></div></div></div>

<script>
(function(){
'use strict';
const apiUrl=new URL('../api/expense-management.php',window.location.href).href;
let csrfToken=<?=json_encode($expenseCsrf)?>;
let meta={purposes:[],options:[],vehicles:[],parent_expenses:[],payment_methods:[]};
let rows=[],page=1,permissions={edit:true,delete:true},loadTimer=null;
const $=id=>document.getElementById(id);
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
const money=v=>new Intl.NumberFormat('en-IN',{style:'currency',currency:'INR',maximumFractionDigits:2}).format(Number(v||0));
const badge=v=>`<span class="exp-badge ${esc(String(v||'').toLowerCase())}">${esc(v||'-')}</span>`;
const today=()=>{const d=new Date();return new Date(d.getTime()-d.getTimezoneOffset()*60000).toISOString().slice(0,10)};

async function request(action,data={},method='GET'){
 let response;
 if(method==='GET'){
  const url=new URL(apiUrl);url.searchParams.set('action',action);
  Object.entries(data).forEach(([k,v])=>{if(v!==''&&v!==null&&v!==undefined)url.searchParams.set(k,String(v))});
  response=await fetch(url,{headers:{Accept:'application/json'},credentials:'same-origin'});
 }else{
  response=await fetch(apiUrl,{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json'},credentials:'same-origin',body:JSON.stringify({action,csrf_token:csrfToken,...data})});
 }
 const text=await response.text();let result;
 try{result=JSON.parse(text)}catch{throw new Error(`Expense API returned HTTP ${response.status}. ${text.replace(/\s+/g,' ').trim().slice(0,220)||'Invalid server response.'}`)}
 if(!response.ok||!result.success)throw new Error(result.message||'Request failed.');
 if(result.data?.csrf_token)csrfToken=result.data.csrf_token;
 return result;
}
function message(text,ok=false){const b=$('expenseMessage');b.className='alert exp-message show '+(ok?'alert-success':'alert-danger');b.textContent=text;clearTimeout(b._timer);b._timer=setTimeout(()=>b.className='alert exp-message',7000)}
function fill(id,data,value,label,first,firstValue=''){const el=$(id);el.innerHTML=`<option value="${esc(firstValue)}">${esc(first)}</option>`+data.map(r=>`<option value="${esc(r[value])}">${esc(typeof label==='function'?label(r):r[label])}</option>`).join('')}

function setupMeta(){
 $('purposeList').innerHTML=meta.purposes.map(p=>`<option value="${esc(p.purpose_name)}"></option>`).join('');
 fill('purposeFilter',meta.purposes,'purpose_code','purpose_name','All Purposes','all');
 fill('paymentMethod',(meta.payment_methods||[]).map(x=>({id:x,name:x})),'name','name','Select payment method','');
 fill('modeFilter',(meta.payment_methods||[]).map(x=>({id:x,name:x})),'name','name','All Payment Methods','all');
 fill('parentExpenseId',meta.parent_expenses||[],'id',r=>`${r.expense_no} • ${r.expense_date} • ${r.purpose} • ${money(r.available_amount)} available`,'Select original expense','');
}

function selectedPurpose(){
 const text=$('expensePurposeSearch').value.trim().toLowerCase();
 return meta.purposes.find(p=>p.purpose_name.toLowerCase()===text||p.purpose_code.toLowerCase()===text)||null;
}

function parsePurposeDetails(value){
 if(!value)return {};
 if(typeof value==='object'&&!Array.isArray(value))return {...value};
 try{
  const parsed=JSON.parse(String(value));
  return parsed&&typeof parsed==='object'&&!Array.isArray(parsed)?parsed:{};
 }catch{return {}}
}
function currentPurposeDetails(){
 const values={};
 document.querySelectorAll('#purposeDynamicGrid [data-purpose-field]').forEach(el=>{
  const key=el.dataset.purposeField;
  if(!key)return;
  values[key]=el.type==='checkbox'?(el.checked?'1':'0'):el.value;
 });
 return values;
}
function purposeContext(purpose,optionText=''){
 return [
  purpose?.purpose_code||'',
  purpose?.purpose_name||'',
  purpose?.purpose_group||'',
  optionText||''
 ].join(' ').toLowerCase();
}
function purposeProfile(purpose,optionText=''){
 const text=purposeContext(purpose,optionText);
 const f=(key,label,type='text',required=false,placeholder='',extra={})=>({key,label,type,required,placeholder,...extra});

 if(/fuel|diesel|petrol/.test(text)){
  return {
   title:'Bus / Vehicle Fuel Details',
   tag:'Vehicle Fuel',
   help:'Select the bus from Vehicle Master. Fuel quantity is optional; expense amount, paid to, payment date and remarks use the common fields below.',
   fields:[
    f('vehicle_id','Select Bus','vehicle',true),
    f('fuel_type','Fuel Type','select',false,'',{options:['Diesel','Petrol','CNG','Electric Charging','Other']}),
    f('fuel_quantity','Fuel Amount / Quantity','number',false,'e.g. 35',{step:'0.01',min:'0'}),
    f('fuel_unit','Quantity Unit','select',false,'',{options:['Litres','Kg','Units','Other']}),
    f('odometer_reading','Odometer Reading','text',false,'e.g. 45210 km'),
    f('vendor_name','Fuel Station / Vendor','text',false,'Vendor name'),
    f('bill_no','Bill / Invoice No.','text',false,'Invoice number')
   ]
  };
 }
 if(/bus|transport|vehicle/.test(text)){
  return {
   title:'Bus / Transport Expense Details',
   tag:'Transport',
   help:'Select the related bus. Additional transport fields change with the selected expense type.',
   fields:[
    f('vehicle_id','Select Bus / Vehicle','vehicle',true),
    f('odometer_reading','Odometer Reading','text',false,'Current reading'),
    f('vendor_name','Vendor / Workshop','text',false,'Vendor or workshop'),
    f('bill_no','Bill / Invoice No.','text',false,'Invoice number')
   ]
  };
 }
 if(/scholarship/.test(text)){
  return {title:'Scholarship Details',tag:'Student',help:'Record the beneficiary and scholarship reference.',
   fields:[f('beneficiary_name','Student / Beneficiary Name','text',true,'Student name'),f('beneficiary_reference','Admission / Reference No.','text',false,'Admission number'),f('scheme_name','Scholarship / Scheme Name','text',false,'Scheme name')]};
 }
 if(/salary|wages|bonus|staff advance|staff_advance|advance/.test(text)){
  return {title:'Staff Payment Details',tag:'Staff',help:'Enter staff-specific payment information.',
   fields:[f('staff_name','Employee / Staff Name','text',true,'Employee name'),f('employee_code','Employee ID','text',false,'Employee code'),f('salary_period','Salary / Payment Period','month',false),f('payment_component','Component','text',false,'Salary, bonus, advance, overtime...')]};
 }
 if(/building|maintenance|repair|plumbing|painting|civil work|electrical work/.test(text)){
  return {title:'Maintenance Details',tag:'Maintenance',help:'Enter work location and maintenance details.',
   fields:[f('work_type','Work / Maintenance Type','text',true,'Electrical, plumbing, civil...'),f('work_location','Building / Location','text',false,'Block, floor or room'),f('vendor_name','Vendor / Contractor','text',false,'Contractor name'),f('bill_no','Bill / Invoice No.','text',false,'Invoice number')]};
 }
 if(/electricity/.test(text)){
  return {title:'Electricity Bill Details',tag:'Utility',help:'Enter the electricity service and billing details.',
   fields:[f('consumer_number','Consumer / Service No.','text',true,'EB service number'),f('billing_period','Billing Period','month',false),f('meter_number','Meter No.','text',false,'Meter number'),f('bill_no','Bill No.','text',false,'Bill number')]};
 }
 if(/\bwater\b/.test(text)){
  return {title:'Water Expense Details',tag:'Utility',help:'Enter water connection or supplier details.',
   fields:[f('connection_number','Connection / Consumer No.','text',false,'Connection number'),f('billing_period','Billing Period','month',false),f('supplier_name','Water Supplier','text',false,'Supplier name'),f('bill_no','Bill No.','text',false,'Bill number')]};
 }
 if(/internet|telephone|mobile recharge|broadband/.test(text)){
  return {title:'Internet / Telephone Details',tag:'Utility',help:'Enter account and billing details.',
   fields:[f('service_provider','Service Provider','text',true,'Provider name'),f('account_number','Account / Mobile No.','text',false,'Account or phone number'),f('billing_period','Billing Period','month',false),f('bill_no','Bill No.','text',false,'Bill number')]};
 }
 if(/advertisement|advertising/.test(text)){
  return {title:'Advertisement Details',tag:'Advertisement',help:'Enter campaign/media details.',
   fields:[f('campaign_name','Campaign / Advertisement','text',true,'Campaign name'),f('media_type','Media / Platform','text',false,'Print, banner, online...'),f('campaign_period','Campaign Period','text',false,'Period'),f('bill_no','Bill / Invoice No.','text',false,'Invoice number')]};
 }
 if(/event|function|competition|tour|trip/.test(text)){
  return {title:'Event / Function Details',tag:'Event',help:'Enter event-specific expense information.',
   fields:[f('event_name','Event / Function Name','text',true,'Event name'),f('event_date','Event Date','date',false),f('event_location','Venue / Location','text',false,'Venue'),f('bill_no','Bill / Invoice No.','text',false,'Invoice number')]};
 }
 if(/books|study material|academic|stationery|printing|software purchase|software/.test(text)){
  return {title:'Purchase / Materials Details',tag:'Purchase',help:'Enter item and purchase information.',
   fields:[f('item_details','Item / Material Details','text',true,'Items purchased'),f('quantity','Quantity','number',false,'Quantity',{step:'0.01',min:'0'}),f('supplier_name','Supplier','text',false,'Supplier name'),f('bill_no','Bill / Invoice No.','text',false,'Invoice number')]};
 }
 if(/medical|first aid|pharmacy/.test(text)){
  return {title:'Medical Expense Details',tag:'Medical',help:'Enter beneficiary and medical purchase details.',
   fields:[f('beneficiary_name','Beneficiary / Purpose','text',false,'Student/staff/purpose'),f('medical_details','Medicine / Treatment Details','text',false,'Details'),f('vendor_name','Hospital / Pharmacy','text',false,'Vendor'),f('bill_no','Bill No.','text',false,'Bill number')]};
 }
 if(/rent|lease/.test(text)){
  return {title:'Rent / Lease Details',tag:'Rent',help:'Enter property and rental period.',
   fields:[f('property_name','Property / Location','text',true,'Property or location'),f('rental_period','Rental Period','month',false),f('agreement_reference','Agreement Reference','text',false,'Agreement/reference')]};
 }
 if(/tax|government|permit|fitness/.test(text)){
  return {title:'Tax / Government Fee Details',tag:'Government',help:'Enter authority and fee details.',
   fields:[f('authority_name','Authority / Department','text',true,'Department name'),f('fee_type','Tax / Fee Type','text',false,'Tax or fee type'),f('assessment_period','Assessment Period','text',false,'Period'),f('challan_number','Challan / Reference No.','text',false,'Challan number')]};
 }
 return {title:'Additional Expense Details',tag:'General',help:'Add any purpose-specific reference required for this expense.',
  fields:[f('category_detail','Category / Detail','text',false,'Optional detail'),f('bill_no','Bill / Invoice No.','text',false,'Invoice number')]};
}
function dynamicFieldHtml(field,value=''){
 const required=field.required?' required data-purpose-required="1"':'';
 const req=field.required?' <span class="required-mark">*</span>':'';
 const val=esc(value??'');
 if(field.type==='vehicle'){
  const vehicles=meta.vehicles||[];
  const options=vehicles.map(v=>`<option value="${Number(v.id)}" ${String(v.id)===String(value)?'selected':''}>${esc(v.vehicle_label)}</option>`).join('');
  const first=vehicles.length?'Select bus / vehicle':'No active buses available';
  return `<div><label class="form-label">${esc(field.label)}${req}</label><select class="form-select" data-purpose-field="${esc(field.key)}"${required}><option value="">${esc(first)}</option>${options}</select><div class="exp-vehicle-note ${vehicles.length?'':'exp-no-vehicles'}">${vehicles.length?`${vehicles.length} active vehicle(s) loaded from Vehicle Master.`:'No active bus/vehicle was found in the school database.'}</div></div>`;
 }
 if(field.type==='select'){
  const options=(field.options||[]).map(v=>`<option value="${esc(v)}" ${String(v)===String(value)?'selected':''}>${esc(v)}</option>`).join('');
  return `<div><label class="form-label">${esc(field.label)}${req}</label><select class="form-select" data-purpose-field="${esc(field.key)}"${required}><option value="">Select</option>${options}</select></div>`;
 }
 const attrs=[
  `type="${esc(field.type||'text')}"`,
  `value="${val}"`,
  field.placeholder?`placeholder="${esc(field.placeholder)}"`:'',
  field.step?`step="${esc(field.step)}"`:'',
  field.min!==undefined?`min="${esc(field.min)}"`:'',
  required
 ].filter(Boolean).join(' ');
 return `<div><label class="form-label">${esc(field.label)}${req}</label><input class="form-control" data-purpose-field="${esc(field.key)}" ${attrs}></div>`;
}
function renderPurposeDetails(purpose,values={}){
 const wrap=$('purposeDynamicDetails'),grid=$('purposeDynamicGrid');
 if(!purpose){wrap.hidden=true;grid.innerHTML='';return}
 const profile=purposeProfile(purpose,$('purposeOption').value);
 $('purposeDynamicTitle').textContent=profile.title;
 $('purposeDynamicHelp').textContent=profile.help;
 $('purposeDynamicTag').textContent=profile.tag;
 grid.innerHTML=profile.fields.map(field=>dynamicFieldHtml(field,values[field.key]??'')).join('');
 wrap.hidden=profile.fields.length===0;
}
function refreshPurpose(values=null){
 const preserved=values&&typeof values==='object'?values:currentPurposeDetails();
 const purpose=selectedPurpose();
 $('expensePurposeCode').value=purpose?.purpose_code||'';
 const previousOption=$('purposeOption').value;
 const options=(meta.options||[]).filter(o=>o.purpose_code===purpose?.purpose_code);
 fill('purposeOption',options,'option_name','option_name',options.length?'Select expense type / issue':'No additional option','');
 $('purposeOptionWrap').hidden=!options.length;
 if(previousOption&&options.some(o=>o.option_name===previousOption))$('purposeOption').value=previousOption;
 renderPurposeDetails(purpose,preserved);
}
function validatePurposeDetails(){
 let ok=true,first=null;
 document.querySelectorAll('#purposeDynamicGrid [data-purpose-required="1"]').forEach(el=>{
  const valid=String(el.value||'').trim()!=='';
  el.classList.toggle('is-invalid',!valid);
  if(!valid&&!first){first=el;ok=false}
 });
 if(!ok){
  first?.focus();
  message('Complete the required fields for the selected Expense Purpose.');
 }
 return ok;
}
function refreshType(){
 const type=$('transactionType').value;
 const isReturn=type!=='expense';
 $('parentExpenseWrap').hidden=!isReturn;
 $('amountLabel').textContent=isReturn?'Amount Returned / Adjusted *':'Amount Paid *';
 $('partyLabel').textContent=isReturn?'Received From *':'Paid To *';
 $('expenseModalTitle').textContent=type==='expense'?'Add Expense':type==='refund'?'Add Refund / Return':'Add Adjustment';
 $('expenseModalHint').textContent=isReturn?'Link this transaction to the original expense.':'Enter the expense manually.';
}
function parentChanged(){
 const parent=meta.parent_expenses.find(x=>Number(x.id)===Number($('parentExpenseId').value));
 if(!parent)return;
 $('expensePurposeSearch').value=parent.purpose||'';
 refreshPurpose();
 $('expenseAmount').max=Number(parent.available_amount||0);
 $('parentExpenseHelp').textContent=`Available amount: ${money(parent.available_amount)} • Original: ${parent.expense_no}`;
}

function updateStats(s={}){
 $('statTotal').textContent=money(s.total_expense||0);
 $('statToday').textContent=money(s.today_expense||0);
 $('statPending').textContent=money(s.pending_expense||0);
 $('statReturned').textContent=money(s.returned_amount||0);
}
function details(r){
 const d=parsePurposeDetails(r.purpose_details);
 const fuel=d.fuel_quantity?`${d.fuel_quantity}${d.fuel_unit?' '+d.fuel_unit:''}`:'';
 return [r.purpose_option,r.vehicle_label||d.vehicle_label,r.issue_type,fuel,r.parent_expense_no?`Against ${r.parent_expense_no}`:''].filter(Boolean).join(' • ')||'-';
}
function render(data){
 rows=data;
 $('expenseBody').innerHTML=data.map((r,index)=>`<tr>
  <td>${index+1}</td><td><strong>${esc(r.expense_no)}</strong></td><td>${esc(r.expense_date)}</td>
  <td>${badge(r.transaction_type)}</td><td>${esc(r.purpose)}</td><td>${esc(details(r))}</td>
  <td>${esc(r.paid_to)}</td><td>${money(r.amount)}</td><td>${money(r.tax||0)}</td>
  <td><strong class="${Number(r.net_amount)<0?'net-negative':'net-positive'}">${money(r.net_amount)}</strong></td>
  <td>${esc(r.payment_method||'-')}</td><td>${esc(r.reference_no||'-')}</td><td>${badge(r.status)}</td>
  <td><div class="exp-actions"><button class="exp-action js-view" data-id="${r.id}" title="View"><i data-lucide="eye"></i></button>${permissions.edit?`<button class="exp-action js-edit" data-id="${r.id}" title="Edit"><i data-lucide="pencil"></i></button>`:''}${permissions.delete?`<button class="exp-action danger js-delete" data-id="${r.id}" title="Delete"><i data-lucide="trash-2"></i></button>`:''}</div></td>
 </tr>`).join('')||'<tr><td colspan="14" class="exp-empty">No expense records found.</td></tr>';
 document.querySelectorAll('.js-view').forEach(b=>b.onclick=()=>viewExpense(Number(b.dataset.id)));
 document.querySelectorAll('.js-edit').forEach(b=>b.onclick=()=>openExpense(Number(b.dataset.id)));
 document.querySelectorAll('.js-delete').forEach(b=>b.onclick=()=>deleteExpense(Number(b.dataset.id)));
 window.lucide?.createIcons();
}
function renderSummary(data){
 $('summaryBody').innerHTML=data.map(r=>`<tr><td>${esc(r.purpose)}</td><td>${Number(r.entries||0).toLocaleString('en-IN')}</td><td>${money(r.paid_amount)}</td><td>${money(r.pending_amount)}</td><td>${money(r.cancelled_amount)}</td><td>${money(r.returned_amount)}</td><td><strong>${money(r.net_amount)}</strong></td></tr>`).join('')||'<tr><td colspan="7" class="exp-empty">No summary available.</td></tr>';
}
function renderHistory(data){
 $('historyBody').innerHTML=data.map(r=>`<tr><td>${esc(r.created_at)}</td><td>${esc(r.action_name)}</td><td>${esc(r.expense_no||'-')}</td><td>${esc(r.user_name||'-')}</td><td>${esc(r.details_text||'-')}</td></tr>`).join('')||'<tr><td colspan="5" class="exp-empty">No transaction history.</td></tr>';
}
function renderPagination(p={}){
 const total=Number(p.total||0),current=Number(p.page||1),per=Number(p.per_page||10),last=Math.max(1,Number(p.last_page||1));
 const start=total?((current-1)*per)+1:0,end=Math.min(current*per,total);
 $('recordInfo').textContent=`${total} transaction${total===1?'':'s'}`;
 $('pageInfo').textContent=`Showing ${start}-${end} of ${total}`;
 let html=`<button class="exp-page-button" data-page="${current-1}" ${current<=1?'disabled':''}>‹</button>`;
 for(let x=Math.max(1,current-2);x<=Math.min(last,current+2);x++)html+=`<button class="exp-page-button ${x===current?'active':''}" data-page="${x}">${x}</button>`;
 html+=`<button class="exp-page-button" data-page="${current+1}" ${current>=last?'disabled':''}>›</button>`;
 $('pagination').innerHTML=html;
 document.querySelectorAll('.exp-page-button').forEach(b=>b.onclick=()=>{if(!b.disabled){page=Number(b.dataset.page);load()}});
}
async function load(){
 try{
  const r=await request('list',{search:$('search').value.trim(),purpose:$('purposeFilter').value,transaction_type:$('typeFilter').value,status:$('statusFilter').value,payment_method:$('modeFilter').value,from_date:$('fromDate').value,to_date:$('toDate').value,page,per_page:10});
  meta=r.data.meta||meta;permissions=r.data.permissions||permissions;setupMeta();
  render(r.data.records||[]);renderSummary(r.data.summary||[]);renderHistory(r.data.history||[]);renderPagination(r.data.pagination||{});updateStats(r.data.stats||{});
 }catch(e){message(e.message)}
}

function clearForm(type='expense'){
 $('expenseForm').reset();$('expenseId').value='';$('transactionType').value=type;$('expenseDate').value=today();$('expenseTax').value=0;$('expenseStatus').value='paid';$('expensePurposeCode').value='';$('expenseAmount').removeAttribute('max');$('purposeDynamicGrid').innerHTML='';$('purposeDynamicDetails').hidden=true;refreshType();refreshPurpose({});
}
function openExpense(id=0,type='expense'){
 const r=rows.find(x=>Number(x.id)===id);
 clearForm(r?.transaction_type||type);
 if(r){
  $('expenseId').value=r.id;$('transactionType').value=r.transaction_type||'expense';$('expenseDate').value=r.expense_date||today();
  $('expensePurposeSearch').value=r.purpose||'';$('expensePurposeCode').value=r.purpose_code||'';refreshPurpose();
  $('purposeOption').value=r.purpose_option||'';$('parentExpenseId').value=r.parent_expense_id||'';
  const savedDetails=parsePurposeDetails(r.purpose_details);
  if(r.vehicle_id&&!savedDetails.vehicle_id)savedDetails.vehicle_id=String(r.vehicle_id);
  if(r.odometer_reading&&!savedDetails.odometer_reading)savedDetails.odometer_reading=r.odometer_reading;
  if(r.vendor_name&&!savedDetails.vendor_name)savedDetails.vendor_name=r.vendor_name;
  if(r.bill_no&&!savedDetails.bill_no)savedDetails.bill_no=r.bill_no;
  if(r.fuel_quantity!==null&&r.fuel_quantity!==undefined&&!savedDetails.fuel_quantity)savedDetails.fuel_quantity=String(r.fuel_quantity);
  if(r.fuel_unit&&!savedDetails.fuel_unit)savedDetails.fuel_unit=r.fuel_unit;
  renderPurposeDetails(selectedPurpose(),savedDetails);
  $('paidTo').value=r.paid_to||'';
  $('expenseAmount').value=r.amount||'';$('expenseTax').value=r.tax||0;$('paymentMethod').value=r.payment_method||'';
  $('referenceNo').value=r.reference_no||'';$('expenseStatus').value=r.status||'paid';$('expenseDescription').value=r.description||'';
  refreshType();parentChanged();
 }
 bootstrap.Modal.getOrCreateInstance($('expenseModal')).show();
}
async function viewExpense(id){
 try{
  const r=await request('detail',{id}),x=r.data.record;
  $('viewSubtitle').textContent=`${x.expense_no} • ${x.transaction_type}`;
  const detailValues=parsePurposeDetails(x.purpose_details);
  const purposeMeta=meta.purposes.find(p=>p.purpose_code===x.purpose_code)||{purpose_code:x.purpose_code,purpose_name:x.purpose,purpose_group:''};
  const profile=purposeProfile(purposeMeta,x.purpose_option||'');
  const detailLabels=Object.fromEntries(profile.fields.map(f=>[f.key,f.label]));
  const fields=[
   ['Payment Date',x.expense_date],['Transaction',x.transaction_type],['Purpose',x.purpose],['Sub-option',x.purpose_option||'-'],
   ['Original Expense',x.parent_expense_no||'-'],
   ...Object.entries(detailValues).filter(([,v])=>String(v??'').trim()!=='').map(([k,v])=>{
    if(k==='vehicle_id'){
     const vehicle=(meta.vehicles||[]).find(z=>Number(z.id)===Number(v));
     return [detailLabels[k]||'Bus / Vehicle',vehicle?.vehicle_label||x.vehicle_label||v];
    }
    return [detailLabels[k]||k.replaceAll('_',' ').replace(/\b\w/g,m=>m.toUpperCase()),v];
   }),
   [x.transaction_type==='expense'?'Paid To':'Received From',x.paid_to],['Amount',money(x.amount)],['Tax',money(x.tax||0)],
   ['Payment Method',x.payment_method],['Reference',x.reference_no||'-'],['Status',x.status],['Created By',x.created_by_name||'-']
  ];
  $('viewContent').innerHTML=fields.map(([l,v])=>`<div class="exp-dynamic"><small>${esc(l)}</small><strong class="d-block mt-1">${esc(v)}</strong></div>`).join('');
  $('viewDescription').textContent=x.description||'No description.';
  bootstrap.Modal.getOrCreateInstance($('viewModal')).show();
 }catch(e){message(e.message)}
}
async function deleteExpense(id){
 if(!confirm('Delete this expense transaction?'))return;
 try{const r=await request('delete',{id},'POST');message(r.message,true);await load()}catch(e){message(e.message)}
}

$('expenseForm').onsubmit=async e=>{
 e.preventDefault();
 const purpose=selectedPurpose();
 if(!purpose){message('Select a valid Purpose from the searchable list.');return}
 if(!validatePurposeDetails())return;
 const purposeDetails=currentPurposeDetails();
 const vehicleId=Number(purposeDetails.vehicle_id||0);
 const vehicleOption=(meta.vehicles||[]).find(v=>Number(v.id)===vehicleId);
 purposeDetails.vehicle_label=vehicleOption?.vehicle_label||'';
 try{
  const r=await request('save',{
   id:Number($('expenseId').value||0),transaction_type:$('transactionType').value,parent_expense_id:Number($('parentExpenseId').value||0),
   purpose_code:purpose.purpose_code,purpose:purpose.purpose_name,purpose_option:$('purposeOption').value,
   expense_date:$('expenseDate').value,vehicle_id:vehicleId,
   vehicle_label:vehicleOption?.vehicle_label||'',
   issue_type:$('purposeOption').value,
   vendor_name:String(purposeDetails.vendor_name||purposeDetails.supplier_name||'').trim(),
   bill_no:String(purposeDetails.bill_no||'').trim(),
   odometer_reading:String(purposeDetails.odometer_reading||'').trim(),
   fuel_quantity:Number(purposeDetails.fuel_quantity||0),
   fuel_unit:String(purposeDetails.fuel_unit||'').trim(),
   purpose_details:purposeDetails,
   paid_to:$('paidTo').value.trim(),
   amount:Number($('expenseAmount').value||0),tax:Number($('expenseTax').value||0),
   payment_method:$('paymentMethod').value,reference_no:$('referenceNo').value.trim(),
   status:$('expenseStatus').value,description:$('expenseDescription').value.trim()
  },'POST');
  bootstrap.Modal.getInstance($('expenseModal'))?.hide();message(r.message,true);await load();
 }catch(err){message(err.message)}
};

$('addExpenseBtn').onclick=()=>openExpense(0,'expense');
$('addRefundBtn').onclick=()=>openExpense(0,'refund');
$('refreshBtn').onclick=load;
$('transactionType').onchange=refreshType;
$('parentExpenseId').onchange=parentChanged;
$('expensePurposeSearch').addEventListener('input',refreshPurpose);
$('purposeOption').onchange=()=>renderPurposeDetails(selectedPurpose(),currentPurposeDetails());
$('exportBtn').onclick=()=>{const p=new URLSearchParams({action:'export',search:$('search').value.trim(),purpose:$('purposeFilter').value,transaction_type:$('typeFilter').value,status:$('statusFilter').value,payment_method:$('modeFilter').value,from_date:$('fromDate').value,to_date:$('toDate').value});window.location.href=apiUrl+'?'+p};
$('printBtn').onclick=()=>{const p=new URLSearchParams({action:'print',search:$('search').value.trim(),purpose:$('purposeFilter').value,transaction_type:$('typeFilter').value,status:$('statusFilter').value,payment_method:$('modeFilter').value,from_date:$('fromDate').value,to_date:$('toDate').value});window.open(apiUrl+'?'+p,'_blank')};
$('resetBtn').onclick=()=>{$('search').value='';['purposeFilter','typeFilter','statusFilter','modeFilter'].forEach(id=>$(id).value='all');$('fromDate').value='';$('toDate').value='';page=1;load()};
['purposeFilter','typeFilter','statusFilter','modeFilter','fromDate','toDate'].forEach(id=>$(id).onchange=()=>{page=1;load()});
$('search').oninput=()=>{clearTimeout(loadTimer);loadTimer=setTimeout(()=>{page=1;load()},300)};
document.querySelectorAll('.exp-tab').forEach(b=>b.onclick=()=>{document.querySelectorAll('.exp-tab').forEach(x=>x.classList.toggle('active',x===b));document.querySelectorAll('.exp-panel').forEach(x=>x.classList.toggle('active',x.dataset.panel===b.dataset.tab))});

load();
window.lucide?.createIcons();
})();
</script>
<?php require dirname(__DIR__).'/includes/layout-end.php'; ?>

<?php
declare(strict_types=1);
$pageTitle='Salary Payroll';$pageKey='payroll_management';
require dirname(__DIR__).'/includes/layout-start.php';
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
if(empty($_SESSION['salary_csrf_token']))$_SESSION['salary_csrf_token']=bin2hex(random_bytes(32));
$csrf=$_SESSION['salary_csrf_token'];
?>
<style>
*{box-sizing:border-box}

.s-page{
    display:grid;
    gap:16px;
    width:100%;
    min-width:0;
}

.s-page .page-heading{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:16px;
    min-width:0;
}

.s-page .page-heading>div:first-child{min-width:0}

.s-page .page-title{
    margin:0;
    font-size:clamp(24px,2vw,30px);
    line-height:1.15;
}

.s-page .page-subtitle{
    margin-top:5px;
    max-width:760px;
}

.s-page .page-actions{
    display:flex;
    align-items:center;
    justify-content:flex-end;
    flex-wrap:wrap;
    gap:9px;
    min-width:0;
}

.s-page .page-actions .btn-ui{
    min-height:40px;
    white-space:nowrap;
}

.s-msg{display:none}
.s-msg.show{display:block}

.s-stats{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:14px;
}

.s-stat{
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

.s-stat::before{
    content:"";
    position:absolute;
    inset:0;
    background:linear-gradient(180deg,rgba(255,255,255,.03),rgba(15,23,42,.05));
    pointer-events:none;
}

.s-stat::after{
    content:"";
    position:absolute;
    width:118px;
    height:118px;
    border-radius:50%;
    right:-40px;
    top:-42px;
    background:rgba(255,255,255,.09);
}

.s-stat.purple{background:linear-gradient(135deg,#7448e8,#4b36cf)}
.s-stat.green{background:linear-gradient(135deg,#45c783,#1ea568)}
.s-stat.orange{background:linear-gradient(135deg,#ffb327,#ff8a17)}
.s-stat.blue{background:linear-gradient(135deg,#4696ef,#2469d5)}

.s-stat-icon{
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

.s-stat-icon svg{width:25px;height:25px}

.s-stat>div{
    position:relative;
    z-index:1;
    min-width:0;
}

.s-stat small{
    display:block;
    font-size:11px;
    font-weight:700;
    opacity:.96;
    margin-bottom:6px;
}

.s-stat strong{
    display:block;
    font-size:clamp(21px,1.8vw,27px);
    line-height:1.05;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}

.s-stat .trend{
    font-size:9px;
    font-weight:700;
    opacity:.94;
    margin-top:8px;
    white-space:normal;
}

.s-card{
    border-radius:14px;
    overflow:hidden;
    min-width:0;
}

.s-head{
    padding:14px 16px;
    border-bottom:1px solid var(--border-soft,#e7ebf3);
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
    min-width:0;
}

.s-head strong{font-size:14px}

.s-head small{
    color:var(--text-muted,#64748b);
    font-size:10px;
}

.s-filter{
    padding:14px 16px;
    display:grid;
    grid-template-columns:minmax(230px,1.4fr) minmax(180px,.7fr) minmax(170px,.65fr) auto;
    gap:10px;
    align-items:center;
    border-bottom:1px solid var(--border-soft,#e7ebf3);
}

.s-filter .form-control,
.s-filter .form-select,
.s-filter .btn-ui{
    width:100%;
    min-width:0;
    min-height:40px;
}

.s-wrap{
    width:100%;
    max-width:100%;
    overflow-x:auto;
    overflow-y:visible;
    -webkit-overflow-scrolling:touch;
    scrollbar-width:thin;
}

.s-table{
    width:100%;
    min-width:1320px;
    border-collapse:separate;
    border-spacing:0;
}

.s-table th{
    font-size:10px;
    white-space:nowrap;
    position:sticky;
    top:0;
    z-index:2;
    background:var(--card-bg,#fff);
}

.s-table td{
    font-size:11px;
    vertical-align:middle;
}

.s-table th,
.s-table td{
    padding:11px 10px;
}

.s-table tbody tr:hover{
    background:rgba(79,70,229,.025);
}

.s-badge{
    display:inline-flex;
    align-items:center;
    padding:5px 9px;
    border-radius:999px;
    font-size:9px;
    font-weight:800;
    text-transform:capitalize;
    white-space:nowrap;
}

.s-badge.paid{background:#e8f8ef;color:#16834f}
.s-badge.pending{background:#fff7d6;color:#9a6700}

.s-actions{
    display:flex;
    gap:5px;
    flex-wrap:nowrap;
}

.s-actions .btn-ui{
    min-height:32px;
    padding:6px 10px;
    white-space:nowrap;
}

.s-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:12px;
}

.s-grid>div{min-width:0}
.s-grid .full{grid-column:1/-1}

.s-info{
    padding:12px;
    border:1px solid var(--border-soft,#e3e8f1);
    border-radius:10px;
    background:rgba(99,102,241,.035);
    min-width:0;
}

.s-info small{
    display:block;
    font-size:9px;
    color:var(--text-muted,#64748b);
    font-weight:700;
}

.s-info strong{
    display:block;
    margin-top:5px;
    overflow-wrap:anywhere;
}

.s-calc{
    grid-column:1/-1;
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:10px;
}

.s-calc div{
    padding:12px;
    border:1px solid var(--border-soft,#dce3ef);
    border-radius:10px;
    background:rgba(99,102,241,.035);
    min-width:0;
}

.s-calc small{
    display:block;
    font-size:9px;
    font-weight:700;
    color:var(--text-muted,#64748b);
}

.s-calc strong{
    display:block;
    margin-top:5px;
    font-size:14px;
    overflow-wrap:anywhere;
}

.s-calc .net{
    background:#ecfdf5;
    border-color:#bbf7d0;
}

.s-calc .net strong{color:#16834f}

#payModal .modal-dialog{
    width:min(1050px,calc(100vw - 32px));
    max-width:1050px;
    max-height:calc(100dvh - 32px);
    margin:16px auto;
}

#payModal .modal-content{
    max-height:calc(100dvh - 32px);
    overflow:hidden;
}

#payModal form{
    display:flex;
    flex-direction:column;
    max-height:calc(100dvh - 32px);
}

#payModal .modal-body{
    overflow-y:auto;
    min-height:0;
}

#payModal .modal-footer{
    flex-wrap:wrap;
}

/* Large desktop */
@media(min-width:1600px){
    .s-page{gap:18px}
    .s-stats{gap:16px}
    .s-stat{min-height:122px;padding:20px 22px}
    .s-filter{
        grid-template-columns:minmax(320px,1.5fr) 210px 190px auto;
    }
    .s-table{min-width:100%}
    .s-table th,
    .s-table td{padding:12px}
}

/* Standard desktop and 1366px laptop */
@media(max-width:1399px){
    .s-page .page-actions{max-width:520px}
    .s-filter{
        grid-template-columns:minmax(230px,1.4fr) 180px 170px auto;
    }
}

/* Small laptop */
@media(max-width:1199px){
    .s-page .page-heading{
        flex-direction:column;
        align-items:stretch;
    }

    .s-page .page-actions{
        justify-content:flex-start;
        max-width:none;
    }

    .s-stats{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }

    .s-filter{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }

    .s-filter #search{
        grid-column:1/-1;
    }

    .s-filter #reset{width:100%}

    .s-calc{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }
}

/* Tablet */
@media(max-width:767px){
    .s-page{gap:12px}

    .s-page .page-actions{
        display:grid;
        grid-template-columns:1fr;
        width:100%;
    }

    .s-page .page-actions .btn-ui{
        width:100%;
        justify-content:center;
    }

    .s-stats{
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:10px;
    }

    .s-stat{
        min-height:108px;
        padding:15px;
    }

    .s-head{
        align-items:flex-start;
        flex-direction:column;
    }

    .s-filter{
        grid-template-columns:1fr;
        padding:12px;
    }

    .s-filter #search{grid-column:auto}

    .s-grid{
        grid-template-columns:1fr;
    }

    .s-grid .full,
    .s-calc{
        grid-column:auto;
    }

    .s-calc{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }

    .s-table{min-width:1180px}
}

/* Mobile */
@media(max-width:575px){
    .s-page .page-title{font-size:24px}

    .s-stats{
        grid-template-columns:1fr;
    }

    .s-stat{min-height:102px}

    .s-calc{
        grid-template-columns:1fr;
    }

    #payModal .modal-dialog{
        width:calc(100vw - 16px);
        margin:8px auto;
        max-height:calc(100dvh - 16px);
    }

    #payModal .modal-content,
    #payModal form{
        max-height:calc(100dvh - 16px);
    }

    #payModal .modal-footer{
        display:grid;
        grid-template-columns:1fr;
    }

    #payModal .modal-footer .btn-ui{
        width:100%;
        justify-content:center;
    }
}

.s-view-grid{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:10px;
}

.s-view-item{
    padding:12px;
    border:1px solid var(--border-soft,#e3e8f1);
    border-radius:10px;
    background:rgba(99,102,241,.035);
    min-width:0;
}

.s-view-item small{
    display:block;
    font-size:9px;
    font-weight:700;
    color:var(--text-muted,#64748b);
}

.s-view-item strong{
    display:block;
    margin-top:5px;
    overflow-wrap:anywhere;
}

.s-history-title{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    margin:18px 0 9px;
    flex-wrap:wrap;
}

.s-history-title strong{font-size:13px}

.s-history-table{
    min-width:1900px;
}

.s-icon-btn{
    width:32px;
    height:32px;
    padding:0!important;
    display:grid;
    place-items:center;
    flex:0 0 auto;
}

.s-icon-btn svg{
    width:14px;
    height:14px;
}

#historyModal .modal-dialog{
    width:min(1160px,calc(100vw - 32px));
    max-width:1160px;
    max-height:calc(100dvh - 32px);
    margin:16px auto;
}

#historyModal .modal-content{
    max-height:calc(100dvh - 32px);
    overflow:hidden;
}

#historyModal .modal-body{
    overflow-y:auto;
    min-height:0;
}

@media(max-width:1199px){
    .s-view-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
}

@media(max-width:575px){
    .s-view-grid{grid-template-columns:1fr}

    #historyModal .modal-dialog{
        width:calc(100vw - 16px);
        margin:8px auto;
        max-height:calc(100dvh - 16px);
    }

    #historyModal .modal-content{
        max-height:calc(100dvh - 16px);
    }
}


.s-calc.s-calc-wide{grid-template-columns:repeat(4,minmax(0,1fr))}
.s-calc .paid-total{background:#eff6ff;border-color:#bfdbfe}
.s-calc .paid-total strong{color:#2563eb}
.s-calc .balance{background:#fff7ed;border-color:#fed7aa}
.s-calc .balance strong{color:#c2410c}
.s-badge.partial{background:#fff7d6;color:#9a6700}
.s-badge.pending{background:#fff0f1;color:#dc2626}
@media(max-width:1199px){.s-calc.s-calc-wide{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:575px){.s-calc.s-calc-wide{grid-template-columns:1fr}}

.s-calc .is-invalid-calc{
    border-color:#ef4444!important;
    background:#fff1f2!important;
}
.s-calc .is-invalid-calc strong{color:#dc2626!important}

.s-history-summary{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:10px;
    margin-bottom:16px;
}

.s-history-summary-card{
    padding:13px;
    border:1px solid var(--border-soft,#e3e8f1);
    border-radius:10px;
    background:var(--card-bg,#fff);
    min-width:0;
}

.s-history-summary-card small{
    display:block;
    font-size:9px;
    font-weight:700;
    color:var(--text-muted,#64748b);
}

.s-history-summary-card strong{
    display:block;
    margin-top:5px;
    font-size:16px;
    overflow-wrap:anywhere;
}

.s-history-summary-card.total{background:#eef2ff}
.s-history-summary-card.paid{background:#ecfdf5}
.s-history-summary-card.balance{background:#fff7ed}
.s-history-summary-card.count{background:#eff6ff}

.s-history-list{
    display:grid;
    gap:10px;
}

.s-history-payment{
    display:grid;
    grid-template-columns:minmax(160px,1fr) repeat(6,minmax(120px,.7fr)) auto;
    gap:10px;
    align-items:center;
    padding:13px;
    border:1px solid var(--border-soft,#e3e8f1);
    border-radius:10px;
    background:var(--card-bg,#fff);
}

.s-history-payment-main strong{
    display:block;
    font-size:13px;
}

.s-history-payment-main small{
    display:block;
    margin-top:4px;
    font-size:10px;
    color:var(--text-muted,#64748b);
}

.s-history-payment-item small{
    display:block;
    font-size:9px;
    font-weight:700;
    color:var(--text-muted,#64748b);
}

.s-history-payment-item strong{
    display:block;
    margin-top:4px;
    font-size:12px;
}

@media(max-width:991px){
    .s-history-summary{grid-template-columns:repeat(2,minmax(0,1fr))}
    .s-history-payment{grid-template-columns:repeat(2,minmax(0,1fr))}
}

@media(max-width:575px){
    .s-history-summary{grid-template-columns:1fr}
    .s-history-payment{grid-template-columns:1fr}
}


.s-ledger-list{
    display:grid;
    gap:9px;
}

.s-ledger-entry{
    display:grid;
    grid-template-columns:minmax(180px,1.2fr) minmax(120px,.7fr) minmax(130px,.75fr) minmax(130px,.75fr) minmax(140px,.8fr) minmax(150px,1fr);
    gap:12px;
    align-items:center;
    padding:13px 14px;
    border:1px solid var(--border-soft,#e3e8f1);
    border-radius:11px;
    background:var(--card-bg,#fff);
}

.s-ledger-entry.allowance{border-left:4px solid #16a34a}
.s-ledger-entry.deduction{border-left:4px solid #dc2626}
.s-ledger-entry.payment{border-left:4px solid #4f46e5}
.s-ledger-entry.opening_salary{border-left:4px solid #0284c7}

.s-ledger-main strong{
    display:block;
    font-size:13px;
}

.s-ledger-main small,
.s-ledger-cell small{
    display:block;
    margin-top:3px;
    font-size:9px;
    color:var(--text-muted,#64748b);
}

.s-ledger-cell strong{
    display:block;
    margin-top:4px;
    font-size:12px;
    overflow-wrap:anywhere;
}

.s-ledger-amount.credit{color:#16834f}
.s-ledger-amount.debit{color:#dc2626}
.s-ledger-amount.payment{color:#4f46e5}

@media(max-width:991px){
    .s-ledger-entry{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }
}

@media(max-width:575px){
    .s-ledger-entry{
        grid-template-columns:1fr;
    }
}

</style>
<div class="s-page">
<div class="page-heading"><div><h1 class="page-title">Salary Payroll</h1><p class="page-subtitle">Monthly salary payment using the fixed salary from Salary Setup.</p></div><div class="page-actions"><button id="refresh" class="btn-ui"><i data-lucide="refresh-cw"></i> Refresh</button></div></div>
<div id="msg" class="alert s-msg"></div>
<section class="s-stats" aria-label="Salary statistics">
    <article class="s-stat purple">
        <span class="s-stat-icon" aria-hidden="true"><i data-lucide="wallet-cards"></i></span>
        <div><small>Monthly Payroll</small><strong id="total">₹0</strong><div class="trend">Selected salary month</div></div>
    </article>
    <article class="s-stat green">
        <span class="s-stat-icon" aria-hidden="true"><i data-lucide="badge-indian-rupee"></i></span>
        <div><small>Paid Payroll</small><strong id="paid">₹0</strong><div class="trend">Successfully processed</div></div>
    </article>
    <article class="s-stat orange">
        <span class="s-stat-icon" aria-hidden="true"><i data-lucide="clock-3"></i></span>
        <div><small>Pending Salaries</small><strong id="pending">0</strong><div class="trend">Awaiting payment</div></div>
    </article>
    <article class="s-stat blue">
        <span class="s-stat-icon" aria-hidden="true"><i data-lucide="users-round"></i></span>
        <div><small>Salary Staff</small><strong id="staff">0</strong><div class="trend">Staff with fixed salary</div></div>
    </article>
</section>
<section class="ui-card s-card"><div class="s-head"><strong>Monthly Salary List</strong><small id="monthText"></small></div><div class="s-filter"><input id="search" class="form-control" placeholder="Search staff, code or department"><input id="month" class="form-control" type="month"><select id="status" class="form-select"><option value="all">All Statuses</option><option value="paid">Paid</option><option value="pending">Pending</option></select><button id="reset" class="btn-ui">Reset</button></div><div class="s-wrap"><table class="data-table s-table"><thead><tr><th>#</th><th>Staff Code</th><th>Staff</th><th>Department</th><th>Designation</th><th>Fixed Salary</th><th>Allowances</th><th>Deductions</th><th>Total Salary</th><th>Total Paid</th><th>Balance</th><th>Last Payment</th><th>Status</th><th>Action</th></tr></thead><tbody id="body"><tr><td colspan="14" class="text-center p-4">Loading...</td></tr></tbody></table></div></section>
</div>
<div class="modal fade" id="payModal" tabindex="-1">
 <div class="modal-dialog modal-xl modal-dialog-centered">
  <div class="modal-content">
   <form id="form">
    <div class="modal-header">
     <div><h5 class="modal-title">Process Salary Payment</h5><small id="sub"></small></div>
     <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body s-grid">
     <input id="staffId" type="hidden">
     <div class="s-info"><small>Staff</small><strong id="staffName">-</strong></div>
     <div class="s-info"><small>Staff Code</small><strong id="staffCode">-</strong></div>
     <div><label class="form-label">Fixed Salary</label><input id="fixed" class="form-control" readonly></div>
     <div>
      <label class="form-label">Allowance Amount</label>
      <input id="allow" class="form-control" type="number" min="0" step="0.01" value="0">
     </div>
     <div id="allowDescriptionWrap" hidden>
      <label class="form-label">Allowance Description *</label>
      <select id="allowDescription" class="form-select">
       <option value="">Select allowance description</option>
       <option value="Travel Allowance">Travel Allowance</option>
       <option value="Bonus">Bonus</option>
       <option value="Overtime">Overtime</option>
       <option value="Incentive">Incentive</option>
       <option value="Food Allowance">Food Allowance</option>
       <option value="Medical Allowance">Medical Allowance</option>
       <option value="House Rent Allowance">House Rent Allowance</option>
       <option value="Special Allowance">Special Allowance</option>
       <option value="Other Allowance">Other Allowance</option>
      </select>
     </div>

     <div>
      <label class="form-label">Deduction Amount</label>
      <input id="deduct" class="form-control" type="number" min="0" step="0.01" value="0">
     </div>
     <div id="deductDescriptionWrap" hidden>
      <label class="form-label">Deduction Description *</label>
      <select id="deductDescription" class="form-select">
       <option value="">Select deduction description</option>
       <option value="Late Penalty">Late Penalty</option>
       <option value="Loan Deduction">Loan Deduction</option>
       <option value="Leave Deduction">Leave Deduction</option>
       <option value="Advance Recovery">Advance Recovery</option>
       <option value="Absent Deduction">Absent Deduction</option>
       <option value="PF / ESI">PF / ESI</option>
       <option value="Tax Deduction">Tax Deduction</option>
       <option value="Damage Recovery">Damage Recovery</option>
       <option value="Other Deduction">Other Deduction</option>
      </select>
     </div>

     <div>
      <label class="form-label">Payment Amount <small class="text-muted">(Optional)</small></label>
      <input id="paymentAmount" class="form-control" type="number" min="0" step="0.01" placeholder="Leave blank to save adjustment only">
     </div>
     <div class="s-calc s-calc-wide">
      <div><small>Fixed Salary</small><strong id="cFixed">₹0</strong></div>
      <div><small>Allowances</small><strong id="cAdd">₹0</strong></div>
      <div><small>Deductions</small><strong id="cReduce">₹0</strong></div>
      <div class="net"><small>Total Salary</small><strong id="cTotal">₹0</strong></div>
      <div class="paid-total"><small>Previously Paid</small><strong id="cPrevious">₹0</strong></div>
      <div><small>Current Payment</small><strong id="cCurrent">₹0</strong></div>
      <div class="balance"><small>Remaining Balance</small><strong id="cBalance">₹0</strong></div>
     </div>
     <div><label class="form-label">Payment Date</label><input id="date" class="form-control" type="date"></div>
     <div><label class="form-label">Payment Mode *</label><select id="method" class="form-select"><option value="cash">Cash</option><option value="bank">Bank</option><option value="upi">UPI</option></select></div>
     <div id="bankBox"><label class="form-label">Company Bank Account</label><select id="bank" class="form-select"></select></div>
     <div><label class="form-label">Transaction / Reference</label><input id="ref" class="form-control"></div>
     <div class="full"><label class="form-label">Remarks</label><textarea id="remarks" class="form-control" rows="3"></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn-ui" data-bs-dismiss="modal">Cancel</button><button class="btn-ui btn-primary-ui">Save Payment</button></div>
   </form>
  </div>
 </div>
</div>


<div class="modal fade" id="historyModal" tabindex="-1">
 <div class="modal-dialog modal-xl modal-dialog-centered">
  <div class="modal-content">
   <div class="modal-header">
    <div>
     <h5 class="modal-title">Salary Ledger History</h5>
     <small id="historySubtitle" class="text-muted">Staff salary ledger entries</small>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
   </div>

   <div class="modal-body">
    <div id="historyStaffInfo" class="s-view-grid mb-3"></div>

    <div class="s-history-summary">
     <div class="s-history-summary-card total">
      <small>Total Payable Salary</small>
      <strong id="historyTotalSalary">₹0</strong>
     </div>
     <div class="s-history-summary-card paid">
      <small>Total Paid</small>
      <strong id="historyTotalPaid">₹0</strong>
     </div>
     <div class="s-history-summary-card balance">
      <small>Remaining Balance</small>
      <strong id="historyBalance">₹0</strong>
     </div>
     <div class="s-history-summary-card count">
      <small>Ledger Entries</small>
      <strong id="historyCount">0</strong>
     </div>
    </div>

    <div class="s-history-title">
     <strong>Ledger Entries</strong>
     <small class="text-muted">Latest entry shown first</small>
    </div>

    <div id="historyBody" class="s-ledger-list">
     <div class="text-center p-4 text-muted">Select a staff member.</div>
    </div>
   </div>

   <div class="modal-footer">
    <button type="button" class="btn-ui" data-bs-dismiss="modal">Close</button>
   </div>
  </div>
 </div>
</div>

<script>
(()=>{'use strict';
const api=new URL('../api/salary-management.php',location.href).href;
let csrf=<?=json_encode($csrf)?>,rows=[],banks=[],timer,currentDetail=null;
const $=id=>document.getElementById(id);
const money=v=>new Intl.NumberFormat('en-IN',{style:'currency',currency:'INR',maximumFractionDigits:2}).format(Number(v||0));
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
const today=()=>{let d=new Date();return new Date(d-d.getTimezoneOffset()*60000).toISOString().slice(0,10)};
async function req(action,data={},post=false){let r;if(post)r=await fetch(api,{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action,csrf_token:csrf,...data})});else{let u=new URL(api);u.searchParams.set('action',action);Object.entries(data).forEach(([k,v])=>u.searchParams.set(k,v));r=await fetch(u,{credentials:'same-origin'})}let t=await r.text(),j;try{j=JSON.parse(t)}catch{throw Error(`Salary API HTTP ${r.status}: ${t.slice(0,220)||'Invalid response'}`)}if(!r.ok||!j.success)throw Error(j.message||'Request failed');if(j.data?.csrf_token)csrf=j.data.csrf_token;return j}
function msg(t,ok=false){$('msg').className='alert s-msg show '+(ok?'alert-success':'alert-danger');$('msg').textContent=t}
function toggleAdjustmentDescriptions(){
 const allowanceAmount=Math.max(0,+$('allow').value||0);
 const deductionAmount=Math.max(0,+$('deduct').value||0);

 $('allowDescriptionWrap').hidden=allowanceAmount<=0;
 $('allowDescription').required=allowanceAmount>0;
 if(allowanceAmount<=0)$('allowDescription').value='';

 $('deductDescriptionWrap').hidden=deductionAmount<=0;
 $('deductDescription').required=deductionAmount>0;
 if(deductionAmount<=0)$('deductDescription').value='';
}
function calc(){
 let fixed=Math.max(0,+$('fixed').value||0);
 let newAllowance=Math.max(0,+$('allow').value||0);
 let newDeduction=Math.max(0,+$('deduct').value||0);
 let existingAllowance=Math.max(0,Number(currentDetail?.allowances||0));
 let existingDeduction=Math.max(0,Number(currentDetail?.deductions||0));
 let totalAllowance=existingAllowance+newAllowance;
 let totalDeduction=existingDeduction+newDeduction;
 let payment=Math.max(0,+$('paymentAmount').value||0);
 let previous=Math.max(0,Number(currentDetail?.total_paid||0));

 let total=Math.max(0,fixed+totalAllowance-totalDeduction);
 let balanceBefore=Math.max(0,total-previous);
 let remaining=Math.max(0,balanceBefore-payment);

 $('cFixed').textContent=money(fixed);
 $('cAdd').textContent=money(totalAllowance);
 $('cReduce').textContent=money(totalDeduction);
 $('cTotal').textContent=money(total);
 $('cPrevious').textContent=money(previous);
 $('cCurrent').textContent=money(payment);
 $('cBalance').textContent=money(remaining);

 toggleAdjustmentDescriptions();
 toggle();

 return{
  newAllowance,
  newDeduction,
  total,
  previous,
  balanceBefore,
  current:payment,
  balance:remaining,
  overpayment:payment>balanceBefore+0.009,
  totalBelowPaid:total+0.009<previous
 };
}
function toggle(){let payment=+$('paymentAmount').value||0,hasPayment=payment>0,bank=hasPayment&&$('method').value==='bank';$('date').disabled=!hasPayment;$('date').required=hasPayment;$('method').disabled=!hasPayment;$('bankBox').hidden=!bank;$('bank').disabled=!bank;$('bank').required=bank;$('ref').disabled=!hasPayment;$('ref').required=hasPayment&&['bank','upi'].includes($('method').value);if(!hasPayment)$('ref').value=''}
function render(){ $('body').innerHTML=rows.map((r,i)=>`<tr><td>${i+1}</td><td><strong>${esc(r.staff_code)}</strong></td><td>${esc(r.staff_name)}</td><td>${esc(r.department_name||'-')}</td><td>${esc(r.designation_name||'-')}</td><td>${money(r.fixed_salary)}</td><td>${money(r.allowances)}</td><td>${money(r.deductions)}</td><td><strong>${money(r.total_salary)}</strong></td><td>${money(r.total_paid)}</td><td><strong>${money(r.balance_amount)}</strong></td><td>${esc(r.last_payment_date||'-')}</td><td><span class="s-badge ${r.computed_status}">${esc(r.computed_status)}</span></td><td><div class="s-actions"><button class="btn-ui s-icon-btn view-history" data-id="${r.staff_id}" title="View Transaction History" type="button"><i data-lucide="eye"></i></button><button class="btn-ui btn-primary-ui pay" data-id="${r.staff_id}" type="button" ${Number(r.balance_amount)<=0?'disabled':''}>${Number(r.balance_amount)<=0?'Paid':'Pay'}</button>${r.salary_record_id?`<button class="btn-ui slip" data-id="${r.salary_record_id}" type="button">Payslip</button>`:''}</div></td></tr>`).join('')||'<tr><td colspan="14" class="text-center p-4">No fixed salaries found.</td></tr>';document.querySelectorAll('.view-history').forEach(b=>b.onclick=()=>openHistory(+b.dataset.id));document.querySelectorAll('.pay').forEach(b=>b.onclick=()=>openPay(+b.dataset.id));document.querySelectorAll('.slip').forEach(b=>b.onclick=()=>open(`${api}?action=payslip&id=${b.dataset.id}`,'_blank'));window.lucide?.createIcons()}
async function load(){try{let j=await req('list',{month:$('month').value,search:$('search').value,status:$('status').value});rows=j.data.records||[];banks=j.data.banks||[];$('monthText').textContent=j.data.month_label;$('total').textContent=money(j.data.stats.total_payroll);$('paid').textContent=money(j.data.stats.paid_payroll);$('pending').textContent=j.data.stats.pending_count;$('staff').textContent=j.data.stats.staff_count;render()}catch(e){msg(e.message)}}
async function openHistory(staffId){
 try{
  $('historyBody').innerHTML='<div class="text-center p-4 text-muted">Loading salary ledger...</div>';

  let j=await req('history',{
   staff_id:staffId,
   month:$('month').value
  });

  let staffInfo=j.data.staff||{};
  let summary=j.data.summary||{};
  let entries=j.data.records||[];

  $('historySubtitle').textContent=
   `${staffInfo.staff_name||'-'} • ${staffInfo.staff_code||'-'}`;

  $('historyStaffInfo').innerHTML=[
   ['Staff Name',staffInfo.staff_name||'-'],
   ['Staff Code',staffInfo.staff_code||'-'],
   ['Department',staffInfo.department_name||'-'],
   ['Designation',staffInfo.designation_name||'-']
  ].map(([label,value])=>
   `<div class="s-view-item">
     <small>${esc(label)}</small>
     <strong>${esc(value)}</strong>
    </div>`
  ).join('');

  $('historyTotalSalary').textContent=money(summary.total_salary||0);
  $('historyTotalPaid').textContent=money(summary.total_paid||0);
  $('historyBalance').textContent=money(summary.remaining_balance||0);
  $('historyCount').textContent=Number(summary.entry_count||0);

  const typeClass=type=>{
   if(type==='payment')return'payment';
   if(type.includes('allowance'))return'allowance';
   if(type.includes('deduction'))return'deduction';
   return'opening_salary';
  };

  const amountClass=type=>{
   if(type==='payment')return'payment';
   if(type==='allowance'||type==='deduction_reversal')return'credit';
   return'debit';
  };

  $('historyBody').innerHTML=entries.map(entry=>`
   <article class="s-ledger-entry ${typeClass(entry.entry_type)}">
    <div class="s-ledger-main">
     <strong>${esc(entry.entry_label||'-')}</strong>
     <small>${esc(entry.transaction_display||'-')}</small>
    </div>

    <div class="s-ledger-cell">
     <small>Amount</small>
     <strong class="s-ledger-amount ${amountClass(entry.entry_type)}">
      ${money(entry.amount)}
     </strong>
    </div>

    <div class="s-ledger-cell">
     <small>Balance Before</small>
     <strong>${money(entry.balance_before)}</strong>
    </div>

    <div class="s-ledger-cell">
     <small>Remaining Balance</small>
     <strong>${money(entry.balance_after)}</strong>
    </div>

    <div class="s-ledger-cell">
     <small>Payment Method</small>
     <strong>${esc(entry.payment_method||'-')}</strong>
    </div>

    <div class="s-ledger-cell">
     <small>Description / Remarks</small>
     <strong>${esc(entry.description||entry.remarks||'-')}</strong>
    </div>
   </article>
  `).join('')||
  '<div class="text-center p-4 text-muted">No salary ledger entries found.</div>';

  bootstrap.Modal.getOrCreateInstance($('historyModal')).show();
  window.lucide?.createIcons();
 }catch(e){
  msg(e.message);
 }
}
async function openPay(id){try{let j=await req('detail',{staff_id:id,month:$('month').value}),r=j.data.record;currentDetail=r;$('staffId').value=r.staff_id;$('staffName').textContent=r.staff_name;$('staffCode').textContent=r.staff_code;$('sub').textContent=$('monthText').textContent;$('fixed').value=Number(r.fixed_salary||0);$('allow').value='0';$('deduct').value='0';$('allowDescription').value='';$('deductDescription').value='';$('allowDescriptionWrap').hidden=true;$('deductDescriptionWrap').hidden=true;$('paymentAmount').value=Number(r.balance_amount||0)>0?Number(r.balance_amount).toFixed(2):'';$('date').value=today();$('method').value='cash';$('bank').innerHTML='<option value="">Select account</option>'+banks.map(x=>`<option value="${x.id}">${esc([x.account_name,x.bank_name,x.account_number].filter(Boolean).join(' • '))}</option>`).join('');$('bank').value='';$('ref').value='';$('remarks').value='';calc();toggle();bootstrap.Modal.getOrCreateInstance($('payModal')).show()}catch(e){msg(e.message)}}
$('form').onsubmit=async e=>{
 e.preventDefault();
 let c=calc();

 if(c.newAllowance>0&&!$('allowDescription').value.trim()){
  msg('Enter the Allowance Description.');
  return;
 }

 if(c.newDeduction>0&&!$('deductDescription').value.trim()){
  msg('Enter the Deduction Description.');
  return;
 }

 if(c.totalBelowPaid){
  msg(`Total Payable Salary cannot be less than Previously Paid (${money(c.previous)}).`);
  return;
 }

 if(c.overpayment){
  msg(`Payment Amount cannot exceed the current balance of ${money(c.balanceBefore)}.`);
  return;
 }

 if(c.current>0&&!$('date').value){
  msg('Select the payment date.');
  return;
 }

 try{
  let j=await req('save',{
   staff_id:+$('staffId').value,
   month:$('month').value,
   allowance_amount:c.newAllowance,
   allowance_description:$('allowDescription').value.trim(),
   deduction_amount:c.newDeduction,
   deduction_description:$('deductDescription').value.trim(),
   payment_amount:c.current,
   payment_date:$('date').value,
   payment_method:$('method').value||'cash',
   bank_account_id:+$('bank').value||0,
   reference_no:$('ref').value.trim(),
   remarks:$('remarks').value.trim()
  },true);

  bootstrap.Modal.getInstance($('payModal'))?.hide();
  msg(j.message,true);
  await load();
 }catch(x){
  msg(x.message);
 }
};

$('month').value=today().slice(0,7);
$('refresh').onclick=load;
$('reset').onclick=()=>{
 $('search').value='';
 $('status').value='all';
 $('month').value=today().slice(0,7);
 load();
};
$('month').onchange=load;
$('status').onchange=load;
$('search').oninput=()=>{
 clearTimeout(timer);
 timer=setTimeout(load,300);
};
['allow','deduct','paymentAmount'].forEach(id=>$(id).oninput=calc);$('allowDescription').onchange=calc;$('deductDescription').onchange=calc;
$('method').onchange=toggle;
load();
})();
</script>
<?php require dirname(__DIR__).'/includes/layout-end.php'; ?>

<?php
declare(strict_types=1);

$pageTitle='Income Management';
$pageKey='accounts_management';

require dirname(__DIR__).'/includes/layout-start.php';

if(session_status()!==PHP_SESSION_ACTIVE){
    session_start();
}
if(empty($_SESSION['income_csrf_token']) || !is_string($_SESSION['income_csrf_token'])){
    $_SESSION['income_csrf_token']=bin2hex(random_bytes(32));
}
$incomeCsrf=$_SESSION['income_csrf_token'];
?>
<style>
*{box-sizing:border-box}
.income-page{
    display:grid;
    gap:16px;
    width:100%;
    min-width:0;
}
.income-page .page-heading{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:16px;
    min-width:0;
}
.income-page .page-heading>div:first-child{min-width:0}
.income-page .page-title{
    font-size:clamp(24px,2vw,30px);
    line-height:1.15;
    margin:0;
}
.income-page .page-subtitle{
    margin-top:5px;
    max-width:760px;
}
.income-page .page-actions{
    display:flex;
    align-items:center;
    justify-content:flex-end;
    flex-wrap:wrap;
    gap:9px;
    min-width:0;
}
.income-page .page-actions .btn-ui{
    min-height:40px;
    white-space:nowrap;
}
.income-message{display:none}
.income-message.show{display:block}

.income-stats{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:14px;
}
.income-stat{
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
.income-stat::before{
    content:"";
    position:absolute;
    inset:0;
    background:linear-gradient(180deg,rgba(255,255,255,.03),rgba(15,23,42,.05));
    pointer-events:none;
}
.income-stat::after{
    content:"";
    position:absolute;
    width:118px;
    height:118px;
    border-radius:50%;
    right:-40px;
    top:-42px;
    background:rgba(255,255,255,.09);
}
.income-stat.purple{background:linear-gradient(135deg,#7448e8,#4b36cf)}
.income-stat.green{background:linear-gradient(135deg,#45c783,#1ea568)}
.income-stat.orange{background:linear-gradient(135deg,#ffb327,#ff8a17)}
.income-stat.blue{background:linear-gradient(135deg,#4696ef,#2469d5)}
.income-stat-icon{
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
.income-stat-icon svg{width:25px;height:25px}
.income-stat>div{position:relative;z-index:1;min-width:0}
.income-stat strong{
    display:block;
    font-size:clamp(21px,1.8vw,27px);
    line-height:1.05;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}
.income-stat small{
    display:block;
    font-size:11px;
    font-weight:700;
    opacity:.96;
    margin-bottom:6px;
}
.income-stat .trend{
    font-size:9px;
    font-weight:700;
    opacity:.94;
    margin-top:8px;
    white-space:normal;
}

.income-tabs{
    display:flex;
    gap:8px;
    overflow-x:auto;
    overflow-y:hidden;
    padding:10px;
    scrollbar-width:thin;
}
.income-tab{
    border:1px solid var(--border-soft,#e7ebf3);
    background:var(--card-bg,#fff);
    color:var(--text-main,#101b46);
    border-radius:9px;
    padding:10px 14px;
    font-size:11px;
    font-weight:800;
    white-space:nowrap;
    flex:0 0 auto;
}
.income-tab.active{
    color:#fff;
    border-color:transparent;
    background:linear-gradient(135deg,#6747e8,#2f62d7);
}
.income-panel{display:none;min-width:0}
.income-panel.active{display:block}
.income-card{
    border-radius:14px;
    overflow:hidden;
    min-width:0;
}
.income-card-head{
    padding:14px 16px;
    border-bottom:1px solid var(--border-soft,#e7ebf3);
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
    min-width:0;
}
.income-card-head strong{font-size:14px}
.income-card-actions{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
}

.income-filter{
    padding:14px 16px;
    display:grid;
    grid-template-columns:minmax(220px,1.35fr) repeat(3,minmax(145px,.75fr)) repeat(2,minmax(145px,.72fr)) auto;
    gap:10px;
    align-items:center;
    border-bottom:1px solid var(--border-soft,#e7ebf3);
}
.income-filter .form-control,
.income-filter .form-select,
.income-filter .btn-ui{
    width:100%;
    min-width:0;
    min-height:40px;
}

.income-table-wrap{
    width:100%;
    max-width:100%;
    overflow-x:auto;
    overflow-y:visible;
    -webkit-overflow-scrolling:touch;
    scrollbar-width:thin;
}
.income-table{
    width:100%;
    min-width:1320px;
    border-collapse:separate;
    border-spacing:0;
}
.income-table th{
    font-size:10px;
    white-space:nowrap;
    position:sticky;
    top:0;
    z-index:2;
    background:var(--card-bg,#fff);
}
.income-table td{
    font-size:11px;
    vertical-align:middle;
}
.income-table th,
.income-table td{
    padding:11px 10px;
}
.income-table tbody tr:hover{background:rgba(79,70,229,.025)}
.income-empty{
    padding:40px 18px!important;
    text-align:center!important;
    color:var(--text-muted,#64748b);
}
.income-badge{
    display:inline-flex;
    align-items:center;
    padding:5px 9px;
    border-radius:999px;
    font-size:9px;
    font-weight:800;
    text-transform:capitalize;
    white-space:nowrap;
}
.income-badge.received,
.income-badge.active,
.income-badge.success{color:#16834f;background:#e8f8ef}
.income-badge.pending,
.income-badge.draft{color:#9a6700;background:#fff7d6}
.income-badge.cancelled,
.income-badge.inactive{color:#dc2626;background:#fff0f1}
.income-actions{
    display:flex;
    gap:5px;
    flex-wrap:nowrap;
}
.income-action{
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
.income-action.danger{color:#dc2626}
.income-action.success{color:#16834f}
.income-action svg{width:13px;height:13px}

.income-pagination{
    padding:14px 16px;
    border-top:1px solid var(--border-soft,#e7ebf3);
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
}
.income-page-buttons{
    display:flex;
    gap:6px;
    flex-wrap:wrap;
}
.income-page-button{
    min-width:34px;
    height:34px;
    border:1px solid var(--border-soft,#e7ebf3);
    background:#fff;
    border-radius:8px;
    font-size:11px;
    font-weight:800;
}
.income-page-button.active{
    color:#fff;
    border-color:transparent;
    background:linear-gradient(135deg,#6547e8,#315ed8);
}
.income-page-button:disabled{opacity:.45}

.income-form-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:12px;
}
.income-form-grid>div{min-width:0}
.income-form-grid .full{grid-column:1/-1}
.income-summary{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:10px;
}
.income-summary>div{
    border:1px solid var(--border-soft,#e7ebf3);
    border-radius:10px;
    padding:12px;
    min-width:0;
}
.income-summary small{
    display:block;
    font-size:9px;
    color:var(--text-muted,#64748b);
    font-weight:700;
}
.income-summary strong{
    display:block;
    margin-top:4px;
    font-size:14px;
    overflow-wrap:anywhere;
}

#importModal .modal-dialog,
#incomeModal .modal-dialog,
#viewModal .modal-dialog{
    max-height:calc(100dvh - 32px);
    margin:16px auto;
}
#importModal .modal-content,
#incomeModal .modal-content,
#viewModal .modal-content{
    max-height:calc(100dvh - 32px);
    overflow:hidden;
}
#importModal form,
#incomeModal form{
    display:flex;
    flex-direction:column;
    max-height:calc(100dvh - 32px);
}
#importModal .modal-body,
#incomeModal .modal-body,
#viewModal .modal-body{
    overflow-y:auto;
    min-height:0;
}
#incomeModal .modal-dialog,
#viewModal .modal-dialog{
    width:min(1100px,calc(100vw - 32px));
    max-width:1100px;
}
#importModal .modal-dialog{
    width:min(760px,calc(100vw - 32px));
    max-width:760px;
}

/* Large desktop */
@media(min-width:1600px){
    .income-page{gap:18px}
    .income-stats{gap:16px}
    .income-stat{min-height:122px;padding:20px 22px}
    .income-filter{
        grid-template-columns:minmax(280px,1.5fr) repeat(5,minmax(150px,.72fr)) auto;
    }
    .income-table{min-width:100%}
    .income-table th,.income-table td{padding:12px 12px}
}

/* Standard desktop and 1366px laptop */
@media(max-width:1399px){
    .income-page .page-heading{align-items:flex-start}
    .income-page .page-actions{max-width:600px}
    .income-filter{
        grid-template-columns:minmax(220px,1.4fr) repeat(2,minmax(150px,.8fr)) repeat(2,minmax(145px,.75fr));
    }
    .income-filter #toDate{grid-column:auto}
    .income-filter #resetBtn{min-width:110px}
}

/* Small desktop / laptop */
@media(max-width:1199px){
    .income-page .page-heading{
        flex-direction:column;
        align-items:stretch;
    }
    .income-page .page-actions{
        justify-content:flex-start;
        max-width:none;
    }
    .income-stats{grid-template-columns:repeat(2,minmax(0,1fr))}
    .income-filter{
        grid-template-columns:repeat(3,minmax(0,1fr));
    }
    .income-filter #search{grid-column:span 2}
    .income-filter #resetBtn{width:100%}
}

/* Tablet */
@media(max-width:767px){
    .income-page{gap:12px}
    .income-page .page-actions{
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        width:100%;
    }
    .income-page .page-actions .btn-ui{
        width:100%;
        justify-content:center;
    }
    .income-stats{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
    .income-stat{min-height:108px;padding:15px}
    .income-filter{grid-template-columns:repeat(2,minmax(0,1fr));padding:12px}
    .income-filter #search{grid-column:1/-1}
    .income-card-head{align-items:flex-start;flex-direction:column}
    .income-pagination{align-items:flex-start;flex-direction:column}
    .income-form-grid{grid-template-columns:1fr}
    .income-form-grid .full{grid-column:auto}
    .income-summary{grid-template-columns:repeat(2,minmax(0,1fr))}
    .income-table{min-width:1180px}
}

/* Mobile */
@media(max-width:575px){
    .income-page .page-title{font-size:24px}
    .income-page .page-actions{grid-template-columns:1fr 1fr}
    .income-page .page-actions .btn-ui:first-child{grid-column:1/-1}
    .income-stats{grid-template-columns:1fr}
    .income-stat{min-height:102px}
    .income-filter{grid-template-columns:1fr}
    .income-filter #search{grid-column:auto}
    .income-tabs{padding:8px}
    .income-tab{padding:9px 11px}
    .income-summary{grid-template-columns:1fr}
    .income-pagination{padding:12px}
    #incomeModal .modal-dialog,
    #viewModal .modal-dialog,
    #importModal .modal-dialog{
        width:calc(100vw - 16px);
        margin:8px auto;
        max-height:calc(100dvh - 16px);
    }
    #incomeModal .modal-content,
    #viewModal .modal-content,
    #importModal .modal-content,
    #incomeModal form,
    #importModal form{
        max-height:calc(100dvh - 16px);
    }
}
</style>

<div class="income-page" data-page="income">
<div class="page-heading">
 <div><h1 class="page-title">Income Management</h1><p class="page-subtitle">Record, monitor and report all school income transactions.</p></div>
 <div class="page-actions">
  <button id="addIncomeBtn" class="btn-ui btn-primary-ui" type="button"><i data-lucide="plus"></i> Add Income</button>
  <button id="importBtn" class="btn-ui" type="button"><i data-lucide="upload"></i> Import</button>
  <button id="exportBtn" class="btn-ui" type="button"><i data-lucide="download"></i> Export</button>
  <button id="printBtn" class="btn-ui" type="button"><i data-lucide="printer"></i> Print</button>
  <button id="refreshBtn" class="btn-ui" type="button"><i data-lucide="refresh-cw"></i> Refresh</button>
 </div>
</div>

<div id="incomeMessage" class="alert income-message"></div>

<section class="income-stats">
 <article class="income-stat purple"><span class="income-stat-icon"><i data-lucide="indian-rupee"></i></span><div><small>Total Income</small><strong id="statTotal">₹0</strong><div class="trend">Selected filter period</div></div></article>
 <article class="income-stat green"><span class="income-stat-icon"><i data-lucide="calendar-days"></i></span><div><small>Today's Income</small><strong id="statToday">₹0</strong><div class="trend">Received today</div></div></article>
 <article class="income-stat orange"><span class="income-stat-icon"><i data-lucide="clock-3"></i></span><div><small>Pending Income</small><strong id="statPending">₹0</strong><div class="trend">Expected but not received</div></div></article>
 <article class="income-stat blue"><span class="income-stat-icon"><i data-lucide="receipt-text"></i></span><div><small>Total Entries</small><strong id="statEntries">0</strong><div class="trend">Income transactions</div></div></article>
</section>

<section class="ui-card income-tabs">
 <button class="income-tab active" data-tab="list" type="button">Income Dashboard</button>
 <button class="income-tab" data-tab="summary" type="button">Purpose Summary</button>
 <button class="income-tab" data-tab="history" type="button">Audit History</button>
</section>

<section class="income-panel active" data-panel="list">
<section class="ui-card income-card">
<div class="income-card-head"><strong>Income List</strong><small id="recordInfo" class="text-muted">Loading...</small></div>
<div class="income-filter">
 <input id="search" class="form-control" placeholder="Income no., payer, reference or purpose...">
 <select id="purposeFilter" class="form-select"><option value="all">All Purposes</option></select>
 <select id="statusFilter" class="form-select"><option value="all">All Statuses</option><option value="received">Received</option><option value="pending">Pending</option><option value="cancelled">Cancelled</option></select>
 <select id="modeFilter" class="form-select"><option value="all">All Payment Modes</option></select>
 <input id="fromDate" class="form-control" type="date">
 <input id="toDate" class="form-control" type="date">
 <button id="resetBtn" class="btn-ui" type="button"><i data-lucide="rotate-ccw"></i> Reset</button>
</div>
<div class="income-table-wrap"><table class="data-table income-table"><thead><tr><th>#</th><th>Income No.</th><th>Date</th><th>Source</th><th>Purpose</th><th>Payer / Source</th><th>Student</th><th>Amount</th><th>Payment Mode</th><th>Reference</th><th>Status</th><th>Created By</th><th>Actions</th></tr></thead><tbody id="incomeBody"><tr><td colspan="13" class="income-empty">Loading...</td></tr></tbody></table></div>
<div class="income-pagination"><small id="pageInfo" class="text-muted"></small><div id="pagination" class="income-page-buttons"></div></div>
</section>
</section>

<section class="income-panel" data-panel="summary">
<section class="ui-card income-card">
<div class="income-card-head"><strong>Income Purpose Summary</strong></div>
<div class="income-table-wrap"><table class="data-table income-table"><thead><tr><th>Purpose</th><th>Entries</th><th>Received</th><th>Pending</th><th>Cancelled</th><th>Total Amount</th></tr></thead><tbody id="summaryBody"></tbody></table></div>
</section>
</section>

<section class="income-panel" data-panel="history">
<section class="ui-card income-card">
<div class="income-card-head"><strong>Income Audit History</strong></div>
<div class="income-table-wrap"><table class="data-table income-table"><thead><tr><th>Date</th><th>Action</th><th>Income No.</th><th>Source</th><th>User</th><th>Details</th></tr></thead><tbody id="historyBody"><tr><td colspan="6" class="income-empty">Loading audit history...</td></tr></tbody></table></div>
</section>
</section>
</div>

<div class="modal fade" id="incomeModal" tabindex="-1">
<div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content"><form id="incomeForm">
<div class="modal-header"><h5 id="incomeModalTitle" class="modal-title">Add Income</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body income-form-grid">
<input id="incomeId" type="hidden">
<div><label class="form-label">Income Purpose *</label><select id="incomePurpose" class="form-select" required></select></div>
<div><label class="form-label">Income Date *</label><input id="incomeDate" class="form-control" type="date" required></div>
<div><label class="form-label">Amount *</label><input id="incomeAmount" class="form-control" type="number" min="0.01" step="0.01" required></div>
<div><label class="form-label">Payment Mode *</label><select id="paymentModeId" class="form-select" required></select></div>
<div><label class="form-label">Payer / Source *</label><input id="payerName" class="form-control" maxlength="150" required></div>
<div><label class="form-label">Transaction Reference</label><input id="referenceNo" class="form-control" maxlength="120"></div>
<div><label class="form-label">Status *</label><select id="incomeStatus" class="form-select"><option value="received">Received</option><option value="pending">Pending</option><option value="cancelled">Cancelled</option></select></div>
<div><label class="form-label">Student (Optional)</label><select id="studentId" class="form-select"></select></div>
<div><label class="form-label">Academic Year</label><select id="academicYearId" class="form-select"></select></div>
<div><label class="form-label">Linked Receipt (Optional)</label><select id="receiptId" class="form-select"></select></div>
<div class="full"><label class="form-label">Description / Remarks</label><textarea id="incomeDescription" class="form-control" rows="3" maxlength="500"></textarea></div>
</div>
<div class="modal-footer"><button type="button" class="btn-ui" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn-ui btn-primary-ui">Save Income</button></div>
</form></div></div>
</div>

<div class="modal fade" id="importModal" tabindex="-1">
<div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><form id="importForm">
<div class="modal-header"><h5 class="modal-title">Import Income CSV</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
 <div class="alert alert-info">Required columns: purpose_code, income_date, amount, payment_mode, payer_name, reference_no, status, description.</div>
 <input id="importFile" class="form-control" type="file" accept=".csv,text/csv" required>
</div>
<div class="modal-footer"><button type="button" class="btn-ui" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn-ui btn-primary-ui">Import CSV</button></div>
</form></div></div>
</div>

<div class="modal fade" id="viewModal" tabindex="-1">
<div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content">
<div class="modal-header"><div><h5 class="modal-title">Income Details</h5><small id="viewSubtitle" class="text-muted"></small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body"><div id="viewContent" class="income-summary"></div><div class="mt-3"><strong>Description</strong></div><div id="viewDescription" class="alert alert-light mt-2 mb-0"></div></div>
<div class="modal-footer"><button type="button" class="btn-ui" data-bs-dismiss="modal">Close</button></div>
</div></div>
</div>

<script>
(function(){
'use strict';
const apiUrl=new URL('../api/income-management.php',window.location.href).href;
let csrfToken=<?=json_encode($incomeCsrf)?>;
let meta={purposes:[],payment_modes:[],students:[],years:[],receipts:[]};
let rows=[],page=1,permissions={edit:false,delete:false};
const $=id=>document.getElementById(id);
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
const money=v=>new Intl.NumberFormat('en-IN',{style:'currency',currency:'INR',maximumFractionDigits:2}).format(Number(v||0));
const badge=v=>`<span class="income-badge ${esc(String(v||'').toLowerCase())}">${esc(v||'-')}</span>`;

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
 try{result=JSON.parse(text)}catch{throw new Error(`Income API returned HTTP ${response.status}. ${text.replace(/\s+/g,' ').trim().slice(0,220)||'Invalid server response.'}`)}
 if(!response.ok||!result.success)throw new Error(result.message||'Request failed.');
 if(result.data?.csrf_token)csrfToken=result.data.csrf_token;
 return result;
}
function message(text,ok=false){const b=$('incomeMessage');b.className='alert income-message show '+(ok?'alert-success':'alert-danger');b.textContent=text}
function fill(id,items,key,label,first=''){const e=$(id);e.innerHTML=(first?`<option value="">${esc(first)}</option>`:'')+items.map(x=>`<option value="${esc(x[key])}">${esc(x[label])}</option>`).join('')}
function updateStats(s={}){$('statTotal').textContent=money(s.total_income||0);$('statToday').textContent=money(s.today_income||0);$('statPending').textContent=money(s.pending_income||0);$('statEntries').textContent=Number(s.total_entries||0).toLocaleString('en-IN')}
function render(data){
 rows=data;
 $('incomeBody').innerHTML=data.map(r=>`<tr>
  <td>${r.row_number}</td><td><strong>${esc(r.income_no)}</strong></td><td>${esc(r.income_date)}</td><td>${r.source_module==='fee_collection'?'<span class="income-badge success">Fee Collection</span>':'<span class="income-badge active">Manual Income</span>'}</td><td>${esc(r.purpose_name)}</td><td>${esc(r.payer_name)}</td><td>${esc(r.student_name||'-')}</td><td><strong>${money(r.amount)}</strong></td><td>${esc(r.payment_mode_name||'-')}</td><td>${esc(r.reference_no||'-')}</td><td>${badge(r.income_status)}</td><td>${esc(r.created_by_name||'-')}</td>
  <td><div class="income-actions"><button class="income-action js-view" data-id="${r.id}" title="View"><i data-lucide="eye"></i></button>${permissions.edit&&r.source_module!=='fee_collection'?`<button class="income-action js-edit" data-id="${r.id}" title="Edit"><i data-lucide="pencil"></i></button>`:''}${permissions.delete&&r.source_module!=='fee_collection'?`<button class="income-action danger js-delete" data-id="${r.id}" title="Delete"><i data-lucide="trash-2"></i></button>`:''}</div></td>
 </tr>`).join('')||'<tr><td colspan="13" class="income-empty">No income records found.</td></tr>';
 document.querySelectorAll('.js-view').forEach(b=>b.onclick=()=>viewIncome(Number(b.dataset.id)));
 document.querySelectorAll('.js-edit').forEach(b=>b.onclick=()=>openIncome(Number(b.dataset.id)));
 document.querySelectorAll('.js-delete').forEach(b=>b.onclick=()=>deleteIncome(Number(b.dataset.id)));
 window.lucide?.createIcons();
}
function renderSummary(data){$('summaryBody').innerHTML=data.map(r=>`<tr><td>${esc(r.purpose_name)}</td><td>${Number(r.entries||0).toLocaleString('en-IN')}</td><td>${money(r.received_amount)}</td><td>${money(r.pending_amount)}</td><td>${money(r.cancelled_amount)}</td><td><strong>${money(r.total_amount)}</strong></td></tr>`).join('')||'<tr><td colspan="6" class="income-empty">No summary available.</td></tr>'}
function auditAction(value){
 const labels={
  fee_income_synchronized:'Fee Income Synchronized',
  income_recorded:'Income Recorded',
  create_income:'Income Created',
  update_income:'Income Updated',
  delete_income:'Income Deleted',
  import_income:'Income Imported'
 };
 return labels[value]||String(value||'-').replaceAll('_',' ').replace(/\b\w/g,c=>c.toUpperCase());
}
function renderHistory(data){
 $('historyBody').innerHTML=data.map(r=>`<tr>
  <td>${esc(r.created_at||'-')}</td>
  <td><strong>${esc(auditAction(r.action_name))}</strong></td>
  <td>${esc(r.income_no||'-')}</td>
  <td>${r.source_module==='fee_collection'
      ?'<span class="income-badge success">Fee Collection</span>'
      :'<span class="income-badge active">Manual Income</span>'}</td>
  <td>${esc(r.user_name||'System')}</td>
  <td>${esc(r.details_text||'-')}</td>
 </tr>`).join('')||'<tr><td colspan="6" class="income-empty">No audit history found.</td></tr>';
}
function renderPagination(p={}){
 const total=Number(p.total||0),current=Number(p.page||1),per=Number(p.per_page||10),last=Math.max(1,Number(p.last_page||1)),start=total?((current-1)*per)+1:0,end=Math.min(current*per,total);
 $('recordInfo').textContent=`${total} income entr${total===1?'y':'ies'}`;$('pageInfo').textContent=`Showing ${start}-${end} of ${total}`;
 let html=`<button class="income-page-button" data-page="${current-1}" ${current<=1?'disabled':''}>‹</button>`;
 for(let x=Math.max(1,current-2);x<=Math.min(last,current+2);x++)html+=`<button class="income-page-button ${x===current?'active':''}" data-page="${x}">${x}</button>`;
 html+=`<button class="income-page-button" data-page="${current+1}" ${current>=last?'disabled':''}>›</button>`;
 $('pagination').innerHTML=html;
 document.querySelectorAll('.income-page-button').forEach(b=>b.onclick=()=>{if(!b.disabled){page=Number(b.dataset.page);load()}});
}
async function load(){
 try{
  const r=await request('list',{search:$('search').value.trim(),purpose_id:$('purposeFilter').value,status:$('statusFilter').value,payment_mode_id:$('modeFilter').value,from_date:$('fromDate').value,to_date:$('toDate').value,page,per_page:10});
  meta=r.data.meta;permissions=r.data.permissions||permissions;render(r.data.records||[]);renderSummary(r.data.summary||[]);renderHistory(r.data.history||[]);renderPagination(r.data.pagination||{});updateStats(r.data.stats||{});
  if($('purposeFilter').options.length<=1)fill('purposeFilter',meta.purposes,'id','purpose_name','All Purposes');
  if($('modeFilter').options.length<=1)fill('modeFilter',meta.payment_modes,'id','method_name','All Payment Modes');
 }catch(e){message(e.message)}
}
function openIncome(id=0){
 const r=rows.find(x=>Number(x.id)===id);
 $('incomeId').value=r?.id||'';
 fill('incomePurpose',meta.purposes,'id','purpose_name','Select purpose');
 fill('paymentModeId',meta.payment_modes,'id','method_name','Select payment mode');
 fill('studentId',meta.students,'id','student_name','No student');
 fill('academicYearId',meta.years,'id','year_name','Select academic year');
 fill('receiptId',meta.receipts,'id','receipt_label','No linked receipt');
 $('incomePurpose').value=r?.purpose_id||'';
 $('incomeDate').value=r?.income_date||new Date().toISOString().slice(0,10);
 $('incomeAmount').value=r?.amount||'';
 $('paymentModeId').value=r?.payment_mode_id||'';
 $('payerName').value=r?.payer_name||'';
 $('referenceNo').value=r?.reference_no||'';
 $('incomeStatus').value=r?.income_status||'received';
 $('studentId').value=r?.student_id||'';
 $('academicYearId').value=r?.academic_year_id||'';
 $('receiptId').value=r?.receipt_id||'';
 $('incomeDescription').value=r?.description||'';
 $('incomeModalTitle').textContent=r?'Edit Income':'Add Income';
 bootstrap.Modal.getOrCreateInstance($('incomeModal')).show();
}
async function viewIncome(id){
 try{
  const r=await request('detail',{id});const x=r.data.record;
  $('viewSubtitle').textContent=`${x.income_no} • ${x.purpose_name}`;
  $('viewContent').innerHTML=[['Income No.',x.income_no],['Date',x.income_date],['Purpose',x.purpose_name],['Payer / Source',x.payer_name],['Student',x.student_name||'-'],['Academic Year',x.year_name||'-'],['Amount',money(x.amount)],['Payment Mode',x.payment_mode_name||'-'],['Reference',x.reference_no||'-'],['Status',x.income_status],['Linked Receipt',x.receipt_no||'-'],['Created By',x.created_by_name||'-']].map(([l,v])=>`<div><small>${esc(l)}</small><strong>${esc(v)}</strong></div>`).join('');
  $('viewDescription').textContent=x.description||'No description.';
  bootstrap.Modal.getOrCreateInstance($('viewModal')).show();
 }catch(e){message(e.message)}
}
async function deleteIncome(id){if(!confirm('Delete this income record?'))return;try{const r=await request('delete',{id},'POST');message(r.message,true);await load()}catch(e){message(e.message)}}
$('incomeForm').onsubmit=async e=>{e.preventDefault();try{const r=await request('save',{id:Number($('incomeId').value||0),purpose_id:Number($('incomePurpose').value),income_date:$('incomeDate').value,amount:Number($('incomeAmount').value||0),payment_mode_id:Number($('paymentModeId').value),payer_name:$('payerName').value.trim(),reference_no:$('referenceNo').value.trim(),income_status:$('incomeStatus').value,student_id:Number($('studentId').value||0),academic_year_id:Number($('academicYearId').value||0),receipt_id:Number($('receiptId').value||0),description:$('incomeDescription').value.trim()},'POST');bootstrap.Modal.getInstance($('incomeModal'))?.hide();message(r.message,true);await load()}catch(err){message(err.message)}};
$('importForm').onsubmit=async e=>{e.preventDefault();const file=$('importFile').files[0];if(!file){message('Select a CSV file.');return}const text=await file.text();try{const r=await request('import',{csv_text:text},'POST');bootstrap.Modal.getInstance($('importModal'))?.hide();message(r.message,true);$('importFile').value='';await load()}catch(err){message(err.message)}};
$('addIncomeBtn').onclick=()=>openIncome();$('importBtn').onclick=()=>bootstrap.Modal.getOrCreateInstance($('importModal')).show();$('refreshBtn').onclick=load;
$('exportBtn').onclick=()=>{const p=new URLSearchParams({action:'export',search:$('search').value.trim(),purpose_id:$('purposeFilter').value,status:$('statusFilter').value,payment_mode_id:$('modeFilter').value,from_date:$('fromDate').value,to_date:$('toDate').value});window.location.href=apiUrl+'?'+p};
$('printBtn').onclick=()=>{const p=new URLSearchParams({action:'print',search:$('search').value.trim(),purpose_id:$('purposeFilter').value,status:$('statusFilter').value,payment_mode_id:$('modeFilter').value,from_date:$('fromDate').value,to_date:$('toDate').value});window.open(apiUrl+'?'+p,'_blank')};
$('resetBtn').onclick=()=>{$('search').value='';['purposeFilter','statusFilter','modeFilter'].forEach(id=>$(id).value='all');$('fromDate').value='';$('toDate').value='';page=1;load()};
['purposeFilter','statusFilter','modeFilter','fromDate','toDate'].forEach(id=>$(id).onchange=()=>{page=1;load()});
let timer;$('search').oninput=()=>{clearTimeout(timer);timer=setTimeout(()=>{page=1;load()},300)};
async function loadHistory(){
 try{
  $('historyBody').innerHTML='<tr><td colspan="6" class="income-empty">Loading audit history...</td></tr>';
  const result=await request('history');
  renderHistory(result.data.history||[]);
 }catch(error){
  $('historyBody').innerHTML=`<tr><td colspan="6" class="income-empty">${esc(error.message)}</td></tr>`;
  message(error.message);
 }
}
document.querySelectorAll('.income-tab').forEach(button=>{
 button.addEventListener('click',async event=>{
  event.preventDefault();
  const tab=button.dataset.tab;
  document.querySelectorAll('.income-tab').forEach(item=>item.classList.toggle('active',item===button));
  document.querySelectorAll('.income-panel').forEach(panel=>panel.classList.toggle('active',panel.dataset.panel===tab));
  if(tab==='history')await loadHistory();
 });
});
load();window.lucide?.createIcons();
})();
</script>

<?php require dirname(__DIR__).'/includes/layout-end.php'; ?>

<?php
declare(strict_types=1);

$pageTitle='Due Management';
$pageKey='fee_management';

require dirname(__DIR__).'/includes/layout-start.php';

if(session_status()!==PHP_SESSION_ACTIVE){
    session_start();
}
if(empty($_SESSION['fee_csrf_token'])||!is_string($_SESSION['fee_csrf_token'])){
    $_SESSION['fee_csrf_token']=bin2hex(random_bytes(32));
}
$feeCsrf=$_SESSION['fee_csrf_token'];
?>
<style>
*{box-sizing:border-box}

.du-page{
    display:grid;
    gap:16px;
    width:100%;
    min-width:0;
}

.du-page .page-heading{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:16px;
    min-width:0;
}

.du-page .page-heading>div:first-child{min-width:0}

.du-page .page-title{
    margin:0;
    font-size:clamp(24px,2vw,30px);
    line-height:1.15;
}

.du-page .page-subtitle{
    margin-top:5px;
    max-width:760px;
}

.du-page .page-actions{
    display:flex;
    align-items:center;
    justify-content:flex-end;
    flex-wrap:wrap;
    gap:9px;
    min-width:0;
}

.du-page .page-actions .btn-ui{
    min-height:40px;
    white-space:nowrap;
}

.du-message{display:none}
.du-message.show{display:block}

.du-stats{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:14px;
}

.du-stat{
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

.du-stat::before{
    content:"";
    position:absolute;
    inset:0;
    background:linear-gradient(180deg,rgba(255,255,255,.03),rgba(15,23,42,.05));
    pointer-events:none;
}

.du-stat::after{
    content:"";
    position:absolute;
    width:118px;
    height:118px;
    border-radius:50%;
    right:-40px;
    top:-42px;
    background:rgba(255,255,255,.09);
}

.du-stat.purple{background:linear-gradient(135deg,#7448e8,#4b36cf)}
.du-stat.green{background:linear-gradient(135deg,#45c783,#1ea568)}
.du-stat.orange{background:linear-gradient(135deg,#ffb327,#ff8a17)}
.du-stat.blue{background:linear-gradient(135deg,#4696ef,#2469d5)}

.du-stat-icon{
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

.du-stat-icon svg{width:25px;height:25px}

.du-stat>div{
    position:relative;
    z-index:1;
    min-width:0;
}

.du-stat strong{
    display:block;
    font-size:clamp(21px,1.8vw,27px);
    line-height:1.05;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}

.du-stat small{
    display:block;
    font-size:11px;
    font-weight:700;
    opacity:.96;
    margin-bottom:6px;
}

.du-stat .trend{
    font-size:9px;
    font-weight:700;
    opacity:.94;
    margin-top:8px;
    white-space:normal;
}

.fee-nav,
.du-tabs{
    display:flex;
    gap:8px;
    overflow-x:auto;
    overflow-y:hidden;
    padding:10px;
    scrollbar-width:thin;
}

.fee-nav a,
.du-tab{
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
.du-tab.active{
    color:#fff;
    border-color:transparent;
    background:linear-gradient(135deg,#6547e8,#315ed8);
}

.fee-nav svg{width:15px;height:15px}

.du-panel{display:none;min-width:0}
.du-panel.active{display:block}

.du-card{
    border-radius:14px;
    overflow:hidden;
    min-width:0;
}

.du-card-head{
    padding:14px 16px;
    border-bottom:1px solid var(--border-soft,#e7ebf3);
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
    min-width:0;
}

.du-card-head strong{font-size:14px}

.du-card-actions{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    align-items:center;
}

.du-filter{
    padding:14px 16px;
    display:grid;
    grid-template-columns:minmax(220px,1.4fr) repeat(6,minmax(135px,.72fr)) auto;
    gap:10px;
    align-items:center;
    border-bottom:1px solid var(--border-soft,#e7ebf3);
}

.du-filter .form-control,
.du-filter .form-select,
.du-filter .btn-ui{
    width:100%;
    min-width:0;
    min-height:40px;
}

.du-table-wrap{
    width:100%;
    max-width:100%;
    overflow-x:auto;
    overflow-y:visible;
    -webkit-overflow-scrolling:touch;
    scrollbar-width:thin;
}

.du-table{
    width:100%;
    min-width:1380px;
    border-collapse:separate;
    border-spacing:0;
}

.du-table th{
    font-size:10px;
    white-space:nowrap;
    position:sticky;
    top:0;
    z-index:2;
    background:var(--card-bg,#fff);
}

.du-table td{
    font-size:11px;
    vertical-align:middle;
}

.du-table th,
.du-table td{
    padding:11px 10px;
}

.du-table tbody tr:hover{
    background:rgba(79,70,229,.025);
}

.du-empty{
    padding:40px 18px!important;
    text-align:center!important;
    color:var(--text-muted,#64748b);
}

.du-badge{
    display:inline-flex;
    align-items:center;
    padding:5px 9px;
    border-radius:999px;
    font-size:9px;
    font-weight:800;
    text-transform:capitalize;
    white-space:nowrap;
}

.du-badge.paid,
.du-badge.cleared,
.du-badge.success{color:#16834f;background:#e8f8ef}

.du-badge.partial,
.du-badge.due,
.du-badge.pending{color:#9a6700;background:#fff7d6}

.du-badge.overdue,
.du-badge.failed{color:#dc2626;background:#fff0f1}

.du-actions{
    display:flex;
    gap:5px;
    flex-wrap:nowrap;
}

.du-action{
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

.du-action.success{color:#16834f}
.du-action.danger{color:#dc2626}
.du-action svg{width:13px;height:13px}

.du-pagination{
    padding:14px 16px;
    border-top:1px solid var(--border-soft,#e7ebf3);
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
}

.du-page-buttons{
    display:flex;
    gap:6px;
    flex-wrap:wrap;
}

.du-page-button{
    min-width:34px;
    height:34px;
    border:1px solid var(--border-soft,#e7ebf3);
    background:#fff;
    border-radius:8px;
    font-size:11px;
    font-weight:800;
}

.du-page-button.active{
    color:#fff;
    border-color:transparent;
    background:linear-gradient(135deg,#6547e8,#315ed8);
}

.du-page-button:disabled{opacity:.45}

.du-form-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:12px;
}

.du-form-grid>div{min-width:0}
.du-form-grid .full{grid-column:1/-1}

#followupModal .modal-dialog,
#reminderModal .modal-dialog,
#detailModal .modal-dialog{
    max-height:calc(100dvh - 32px);
    margin:16px auto;
}

#followupModal .modal-dialog,
#reminderModal .modal-dialog{
    width:min(760px,calc(100vw - 32px));
    max-width:760px;
}

#detailModal .modal-dialog{
    width:min(1120px,calc(100vw - 32px));
    max-width:1120px;
}

#followupModal .modal-content,
#reminderModal .modal-content,
#detailModal .modal-content{
    max-height:calc(100dvh - 32px);
    overflow:hidden;
}

#followupModal form,
#reminderModal form{
    display:flex;
    flex-direction:column;
    max-height:calc(100dvh - 32px);
}

#followupModal .modal-body,
#reminderModal .modal-body,
#detailModal .modal-body{
    overflow-y:auto;
    min-height:0;
}

#followupModal .modal-footer,
#reminderModal .modal-footer,
#detailModal .modal-footer{
    flex-wrap:wrap;
}

/* Large desktop monitors */
@media(min-width:1600px){
    .du-page{gap:18px}
    .du-stats{gap:16px}
    .du-stat{min-height:122px;padding:20px 22px}
    .du-filter{
        grid-template-columns:minmax(280px,1.5fr) repeat(6,minmax(150px,.72fr)) auto;
    }
    .du-table{min-width:100%}
    .du-table th,
    .du-table td{padding:12px}
}

/* Standard desktop and 1366px laptop */
@media(max-width:1399px){
    .du-page .page-actions{max-width:520px}

    .du-filter{
        grid-template-columns:minmax(220px,1.35fr) repeat(3,minmax(145px,.76fr)) repeat(2,minmax(145px,.72fr));
    }

    .du-filter #resetBtn{min-width:110px}
}

/* Small desktop and laptop */
@media(max-width:1199px){
    .du-page .page-heading{
        flex-direction:column;
        align-items:stretch;
    }

    .du-page .page-actions{
        justify-content:flex-start;
        max-width:none;
    }

    .du-stats{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }

    .du-filter{
        grid-template-columns:repeat(3,minmax(0,1fr));
    }

    .du-filter #search{
        grid-column:span 2;
    }

    .du-filter #resetBtn{
        width:100%;
    }
}

/* Tablet */
@media(max-width:767px){
    .du-page{gap:12px}

    .du-page .page-actions{
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        width:100%;
    }

    .du-page .page-actions .btn-ui{
        width:100%;
        justify-content:center;
    }

    .du-stats{
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:10px;
    }

    .du-stat{
        min-height:108px;
        padding:15px;
    }

    .du-filter{
        grid-template-columns:repeat(2,minmax(0,1fr));
        padding:12px;
    }

    .du-filter #search{
        grid-column:1/-1;
    }

    .du-card-head,
    .du-pagination{
        align-items:flex-start;
        flex-direction:column;
    }

    .du-form-grid{
        grid-template-columns:1fr;
    }

    .du-form-grid .full{
        grid-column:auto;
    }

    .du-table{
        min-width:1240px;
    }
}

/* Mobile */
@media(max-width:575px){
    .du-page .page-title{font-size:24px}

    .du-page .page-actions{
        grid-template-columns:1fr 1fr;
    }

    .du-page .page-actions .btn-ui:first-child{
        grid-column:1/-1;
    }

    .du-stats{
        grid-template-columns:1fr;
    }

    .du-stat{
        min-height:102px;
    }

    .du-filter{
        grid-template-columns:1fr;
    }

    .du-filter #search{
        grid-column:auto;
    }

    .fee-nav,
    .du-tabs{
        padding:8px;
    }

    .fee-nav a,
    .du-tab{
        padding:9px 11px;
    }

    .du-pagination{
        padding:12px;
    }

    #followupModal .modal-dialog,
    #reminderModal .modal-dialog,
    #detailModal .modal-dialog{
        width:calc(100vw - 16px);
        margin:8px auto;
        max-height:calc(100dvh - 16px);
    }

    #followupModal .modal-content,
    #reminderModal .modal-content,
    #detailModal .modal-content,
    #followupModal form,
    #reminderModal form{
        max-height:calc(100dvh - 16px);
    }

    #followupModal .modal-footer,
    #reminderModal .modal-footer,
    #detailModal .modal-footer{
        display:grid;
        grid-template-columns:1fr;
    }

    #followupModal .modal-footer .btn-ui,
    #reminderModal .modal-footer .btn-ui,
    #detailModal .modal-footer .btn-ui{
        width:100%;
        justify-content:center;
    }
}
</style>

<div class="du-page" data-page="dues">
<div class="page-heading">
 <div><h1 class="page-title">Due Management</h1><p class="page-subtitle">Track pending fees, overdue installments and payment follow-ups.</p></div>
 <div class="page-actions">
  <button id="sendBulkReminderBtn" class="btn-ui btn-primary-ui" type="button"><i data-lucide="send"></i> Send Reminders</button>
  <button id="exportBtn" class="btn-ui" type="button"><i data-lucide="download"></i> Export</button>
  <button id="refreshBtn" class="btn-ui" type="button"><i data-lucide="refresh-cw"></i> Refresh</button>
 </div>
</div>

<div id="duMessage" class="alert du-message"></div>

<section class="du-stats">
 <article class="du-stat purple"><span class="du-stat-icon"><i data-lucide="wallet-cards"></i></span><div><small>Total Pending Amount</small><strong id="statPending">₹0</strong><div class="trend">Outstanding assignment balance</div></div></article>
 <article class="du-stat green"><span class="du-stat-icon"><i data-lucide="users"></i></span><div><small>Students with Due</small><strong id="statStudents">0</strong><div class="trend">Distinct students</div></div></article>
 <article class="du-stat orange"><span class="du-stat-icon"><i data-lucide="clock-alert"></i></span><div><small>Overdue Students</small><strong id="statOverdue">0</strong><div class="trend">Past due date</div></div></article>
 <article class="du-stat blue"><span class="du-stat-icon"><i data-lucide="messages-square"></i></span><div><small>Reminders Sent</small><strong id="statReminders">0</strong><div class="trend">Current filtered period</div></div></article>
</section>

<section class="ui-card fee-nav">
<a href="fee-dashboard.php" data-key="dashboard"><i data-lucide="layout-dashboard"></i> Dashboard</a>
<a href="fee-setup.php" data-key="setup"><i data-lucide="settings-2"></i> Fee Setup</a>
<a href="fee-categories.php" data-key="categories"><i data-lucide="tags"></i> Fee Categories</a>
<a href="fee-structures.php" data-key="structures"><i data-lucide="list-tree"></i> Fee Structures</a>
<a href="fee-assignment.php" data-key="assignment"><i data-lucide="user-round-check"></i> Fee Assignment</a>
<a href="fee-collection.php" data-key="collection"><i data-lucide="indian-rupee"></i> Fee Collection</a>
<a href="receipt-management.php" data-key="receipts"><i data-lucide="receipt-text"></i> Receipts</a>
<a href="fine-management.php" data-key="fines"><i data-lucide="landmark"></i> Fines</a>
<a href="due-management.php" data-key="dues"><i data-lucide="clock-alert"></i> Dues</a>
</section>

<section class="ui-card du-tabs">
 <button class="du-tab active" data-tab="dashboard" type="button">Due Dashboard</button>
 <button class="du-tab" data-tab="followups" type="button">Follow-up History</button>
 <button class="du-tab" data-tab="reminders" type="button">Reminder History</button>
</section>

<section class="du-panel active" data-panel="dashboard">
<section class="ui-card du-card">
<div class="du-card-head"><strong>Student Due List</strong><div class="du-card-actions"><small id="recordInfo" class="text-muted">Loading...</small></div></div>
<div class="du-filter">
 <input id="search" class="form-control" placeholder="Student, admission no. or fee structure...">
 <select id="yearFilter" class="form-select"><option value="all">All Academic Years</option></select>
 <select id="classFilter" class="form-select"><option value="all">All Classes</option></select>
 <select id="sectionFilter" class="form-select"><option value="all">All Sections</option></select>
 <select id="statusFilter" class="form-select"><option value="all">All Due Statuses</option><option value="due">Due</option><option value="partial">Partial</option><option value="overdue">Overdue</option></select>
 <select id="installmentFilter" class="form-select"><option value="all">All Installments</option><option value="with_installment">With Installments</option><option value="without_installment">Without Installments</option></select>
 <input id="dueBefore" class="form-control" type="date">
 <button id="resetBtn" class="btn-ui" type="button"><i data-lucide="rotate-ccw"></i> Reset</button>
</div>
<div class="du-table-wrap"><table class="data-table du-table"><thead><tr><th>#</th><th>Student</th><th>Admission No.</th><th>Academic Year</th><th>Class</th><th>Section</th><th>Fee Structure</th><th>Installment</th><th>Due Date</th><th>Total Fee</th><th>Paid</th><th>Pending</th><th>Status</th><th>Actions</th></tr></thead><tbody id="dueBody"><tr><td colspan="14" class="du-empty">Loading...</td></tr></tbody></table></div>
<div class="du-pagination"><small id="pageInfo" class="text-muted"></small><div id="pagination" class="du-page-buttons"></div></div>
</section>
</section>

<section class="du-panel" data-panel="followups">
<section class="ui-card du-card">
<div class="du-card-head"><strong>Payment Follow-up History</strong></div>
<div class="du-table-wrap"><table class="data-table du-table"><thead><tr><th>Date</th><th>Student</th><th>Admission No.</th><th>Fee Structure</th><th>Follow-up Mode</th><th>Outcome</th><th>Next Follow-up</th><th>User</th><th>Notes</th></tr></thead><tbody id="followupBody"></tbody></table></div>
</section>
</section>

<section class="du-panel" data-panel="reminders">
<section class="ui-card du-card">
<div class="du-card-head"><strong>Reminder History</strong></div>
<div class="du-table-wrap"><table class="data-table du-table"><thead><tr><th>Date</th><th>Student</th><th>Channel</th><th>Recipient</th><th>Amount</th><th>Status</th><th>Reference</th><th>Message</th></tr></thead><tbody id="reminderBody"></tbody></table></div>
</section>
</section>
</div>

<div class="modal fade" id="followupModal" tabindex="-1">
<div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><form id="followupForm">
<div class="modal-header"><h5 class="modal-title">Add Payment Follow-up</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body du-form-grid">
<input id="followupAssignmentId" type="hidden">
<div><label class="form-label">Follow-up Date *</label><input id="followupDate" class="form-control" type="date" required></div>
<div><label class="form-label">Follow-up Mode *</label><select id="followupMode" class="form-select"><option value="phone">Phone</option><option value="sms">SMS</option><option value="email">Email</option><option value="whatsapp">WhatsApp</option><option value="meeting">Meeting</option></select></div>
<div><label class="form-label">Outcome *</label><select id="followupOutcome" class="form-select"><option value="promised">Payment Promised</option><option value="unreachable">Unreachable</option><option value="disputed">Disputed</option><option value="partial_commitment">Partial Commitment</option><option value="no_response">No Response</option></select></div>
<div><label class="form-label">Next Follow-up</label><input id="nextFollowupDate" class="form-control" type="date"></div>
<div class="full"><label class="form-label">Notes *</label><textarea id="followupNotes" class="form-control" rows="3" maxlength="500" required></textarea></div>
</div>
<div class="modal-footer"><button type="button" class="btn-ui" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn-ui btn-primary-ui">Save Follow-up</button></div>
</form></div></div>
</div>

<div class="modal fade" id="reminderModal" tabindex="-1">
<div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><form id="reminderForm">
<div class="modal-header"><h5 class="modal-title">Send Due Reminder</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body du-form-grid">
<input id="reminderAssignmentId" type="hidden">
<div><label class="form-label">Channel *</label><select id="reminderChannel" class="form-select"><option value="sms">SMS</option><option value="email">Email</option><option value="whatsapp">WhatsApp</option></select></div>
<div><label class="form-label">Recipient</label><input id="reminderRecipient" class="form-control" maxlength="190"></div>
<div class="full"><label class="form-label">Message *</label><textarea id="reminderMessage" class="form-control" rows="4" maxlength="1000" required></textarea></div>
</div>
<div class="modal-footer"><button type="button" class="btn-ui" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn-ui btn-primary-ui">Send Reminder</button></div>
</form></div></div>
</div>

<div class="modal fade" id="detailModal" tabindex="-1">
<div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content">
<div class="modal-header"><div><h5 class="modal-title">Due Details</h5><small id="detailSubtitle" class="text-muted"></small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body"><div id="detailContent" class="du-form-grid"></div><div class="mt-3"><strong>Installment and Payment Timeline</strong></div><div class="du-table-wrap mt-2"><table class="data-table du-table"><thead><tr><th>Type</th><th>Date</th><th>Description</th><th>Amount</th><th>Status</th></tr></thead><tbody id="timelineBody"></tbody></table></div></div>
<div class="modal-footer"><button type="button" class="btn-ui" data-bs-dismiss="modal">Close</button></div>
</div></div>
</div>

<script>
(function(){
'use strict';

const apiUrl=new URL('../api/due-management.php',window.location.href).href;
let csrfToken=<?=json_encode($feeCsrf)?>;
let meta={years:[],classes:[],sections:[]};
let rows=[],page=1,permissions={remind:false,followup:false};
const $=id=>document.getElementById(id);
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
const money=v=>new Intl.NumberFormat('en-IN',{style:'currency',currency:'INR',maximumFractionDigits:2}).format(Number(v||0));
const badge=v=>`<span class="du-badge ${esc(String(v||'').toLowerCase())}">${esc(v||'-')}</span>`;

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
 try{result=JSON.parse(text)}catch{throw new Error(`Due API returned HTTP ${response.status}. ${text.replace(/\s+/g,' ').trim().slice(0,220)||'Invalid server response.'}`)}
 if(!response.ok||!result.success)throw new Error(result.message||'Request failed.');
 if(result.data?.csrf_token)csrfToken=result.data.csrf_token;
 return result;
}
function message(text,ok=false){const b=$('duMessage');b.className='alert du-message show '+(ok?'alert-success':'alert-danger');b.textContent=text}
function fill(id,items,key,label,first=''){const e=$(id);e.innerHTML=(first?`<option value="all">${esc(first)}</option>`:'')+items.map(x=>`<option value="${esc(x[key])}">${esc(x[label])}</option>`).join('')}
function updateStats(s={}){$('statPending').textContent=money(s.pending_amount||0);$('statStudents').textContent=Number(s.students_with_due||0).toLocaleString('en-IN');$('statOverdue').textContent=Number(s.overdue_students||0).toLocaleString('en-IN');$('statReminders').textContent=Number(s.reminders_sent||0).toLocaleString('en-IN')}
function render(data){
 rows=data;
 $('dueBody').innerHTML=data.map(r=>`<tr>
 <td>${r.row_number}</td><td><strong>${esc(r.student_name)}</strong></td><td>${esc(r.admission_no)}</td><td>${esc(r.year_name)}</td><td>${esc(r.class_name||'-')}</td><td>${esc(r.section_name||'-')}</td><td>${esc(r.structure_name)}</td><td>${esc(r.installment_label||'Single')}</td><td>${esc(r.due_date||'-')}</td><td>${money(r.net_amount)}</td><td>${money(r.paid_amount)}</td><td><strong>${money(r.balance_amount)}</strong></td><td>${badge(r.due_status)}</td>
 <td><div class="du-actions"><button class="du-action js-view" data-id="${r.id}" title="View"><i data-lucide="eye"></i></button>${permissions.followup?`<button class="du-action js-followup" data-id="${r.id}" title="Follow-up"><i data-lucide="phone-call"></i></button>`:''}${permissions.remind?`<button class="du-action success js-reminder" data-id="${r.id}" title="Reminder"><i data-lucide="send"></i></button>`:''}<a class="du-action" href="fee-collection.php?student_id=${r.student_id}&assignment_id=${r.id}" title="Collect"><i data-lucide="indian-rupee"></i></a></div></td>
 </tr>`).join('')||'<tr><td colspan="14" class="du-empty">No due records found.</td></tr>';
 document.querySelectorAll('.js-view').forEach(b=>b.onclick=()=>viewDue(Number(b.dataset.id)));
 document.querySelectorAll('.js-followup').forEach(b=>b.onclick=()=>openFollowup(Number(b.dataset.id)));
 document.querySelectorAll('.js-reminder').forEach(b=>b.onclick=()=>openReminder(Number(b.dataset.id)));
 window.lucide?.createIcons();
}
function renderFollowups(data){$('followupBody').innerHTML=data.map(r=>`<tr><td>${esc(r.followup_date)}</td><td>${esc(r.student_name)}</td><td>${esc(r.admission_no)}</td><td>${esc(r.structure_name)}</td><td>${esc(r.followup_mode)}</td><td>${esc(r.outcome)}</td><td>${esc(r.next_followup_date||'-')}</td><td>${esc(r.user_name||'-')}</td><td>${esc(r.notes)}</td></tr>`).join('')||'<tr><td colspan="9" class="du-empty">No follow-up history.</td></tr>'}
function renderReminders(data){$('reminderBody').innerHTML=data.map(r=>`<tr><td>${esc(r.sent_at)}</td><td>${esc(r.student_name)}</td><td>${esc(r.channel)}</td><td>${esc(r.recipient||'-')}</td><td>${money(r.due_amount)}</td><td>${badge(r.send_status)}</td><td>${esc(r.provider_reference||'-')}</td><td>${esc(r.message_text)}</td></tr>`).join('')||'<tr><td colspan="8" class="du-empty">No reminder history.</td></tr>'}
function renderPagination(p={}){
 const total=Number(p.total||0),current=Number(p.page||1),per=Number(p.per_page||10),last=Math.max(1,Number(p.last_page||1)),start=total?((current-1)*per)+1:0,end=Math.min(current*per,total);
 $('recordInfo').textContent=`${total} due record${total===1?'':'s'}`;$('pageInfo').textContent=`Showing ${start}-${end} of ${total}`;
 let html=`<button class="du-page-button" data-page="${current-1}" ${current<=1?'disabled':''}>‹</button>`;
 for(let x=Math.max(1,current-2);x<=Math.min(last,current+2);x++)html+=`<button class="du-page-button ${x===current?'active':''}" data-page="${x}">${x}</button>`;
 html+=`<button class="du-page-button" data-page="${current+1}" ${current>=last?'disabled':''}>›</button>`;
 $('pagination').innerHTML=html;
 document.querySelectorAll('.du-page-button').forEach(b=>b.onclick=()=>{if(!b.disabled){page=Number(b.dataset.page);load()}});
}
async function load(){
 try{
  const r=await request('list',{search:$('search').value.trim(),academic_year_id:$('yearFilter').value,class_id:$('classFilter').value,section_id:$('sectionFilter').value,due_status:$('statusFilter').value,installment_filter:$('installmentFilter').value,due_before:$('dueBefore').value,page,per_page:10});
  meta=r.data.meta;permissions=r.data.permissions||permissions;render(r.data.records||[]);renderFollowups(r.data.followups||[]);renderReminders(r.data.reminders||[]);renderPagination(r.data.pagination||{});updateStats(r.data.stats||{});
  if($('yearFilter').options.length<=1)fill('yearFilter',meta.years,'id','year_name','All Academic Years');
  if($('classFilter').options.length<=1)fill('classFilter',meta.classes,'id','class_name','All Classes');
  if($('sectionFilter').options.length<=1)fill('sectionFilter',meta.sections,'id','section_name','All Sections');
 }catch(e){message(e.message)}
}
async function viewDue(id){
 try{
  const r=await request('detail',{id});const x=r.data.record,t=r.data.timeline||[];
  $('detailSubtitle').textContent=`${x.student_name} • ${x.structure_name}`;
  $('detailContent').innerHTML=[['Student',x.student_name],['Admission No.',x.admission_no],['Academic Year',x.year_name],['Class / Section',(x.class_name||'-')+' / '+(x.section_name||'-')],['Fee Structure',x.structure_name],['Due Date',x.due_date||'-'],['Total Fee',money(x.net_amount)],['Paid Amount',money(x.paid_amount)],['Pending Amount',money(x.balance_amount)],['Due Status',x.due_status]].map(([l,v])=>`<div class="p-3 border rounded"><small class="text-muted d-block">${esc(l)}</small><strong>${esc(v)}</strong></div>`).join('');
  $('timelineBody').innerHTML=t.map(i=>`<tr><td>${esc(i.timeline_type)}</td><td>${esc(i.timeline_date)}</td><td>${esc(i.description)}</td><td>${money(i.amount)}</td><td>${badge(i.status)}</td></tr>`).join('')||'<tr><td colspan="5" class="du-empty">No timeline data.</td></tr>';
  bootstrap.Modal.getOrCreateInstance($('detailModal')).show();
 }catch(e){message(e.message)}
}
function openFollowup(id){$('followupAssignmentId').value=id;$('followupDate').value=new Date().toISOString().slice(0,10);$('followupMode').value='phone';$('followupOutcome').value='promised';$('nextFollowupDate').value='';$('followupNotes').value='';bootstrap.Modal.getOrCreateInstance($('followupModal')).show()}
function openReminder(id){const r=rows.find(x=>Number(x.id)===id);$('reminderAssignmentId').value=id;$('reminderChannel').value='sms';$('reminderRecipient').value=r?.mobile||'';$('reminderMessage').value=`Dear ${r?.student_name||'Parent'}, fee balance ${money(r?.balance_amount||0)} is pending${r?.due_date?' with due date '+r.due_date:''}. Please make payment at the earliest.`;bootstrap.Modal.getOrCreateInstance($('reminderModal')).show()}
$('followupForm').onsubmit=async e=>{e.preventDefault();try{const r=await request('save_followup',{assignment_id:Number($('followupAssignmentId').value),followup_date:$('followupDate').value,followup_mode:$('followupMode').value,outcome:$('followupOutcome').value,next_followup_date:$('nextFollowupDate').value,notes:$('followupNotes').value.trim()},'POST');bootstrap.Modal.getInstance($('followupModal'))?.hide();message(r.message,true);await load()}catch(err){message(err.message)}};
$('reminderForm').onsubmit=async e=>{e.preventDefault();try{const r=await request('send_reminder',{assignment_id:Number($('reminderAssignmentId').value),channel:$('reminderChannel').value,recipient:$('reminderRecipient').value.trim(),message_text:$('reminderMessage').value.trim()},'POST');bootstrap.Modal.getInstance($('reminderModal'))?.hide();message(r.message,true);await load()}catch(err){message(err.message)}};
$('sendBulkReminderBtn').onclick=async()=>{if(!confirm('Send reminders for all currently filtered overdue records?'))return;try{const r=await request('bulk_reminder',{academic_year_id:$('yearFilter').value,class_id:$('classFilter').value,section_id:$('sectionFilter').value,due_before:$('dueBefore').value},'POST');message(r.message,true);await load()}catch(e){message(e.message)}};
$('exportBtn').onclick=()=>{const p=new URLSearchParams({action:'export',search:$('search').value.trim(),academic_year_id:$('yearFilter').value,class_id:$('classFilter').value,section_id:$('sectionFilter').value,due_status:$('statusFilter').value,installment_filter:$('installmentFilter').value,due_before:$('dueBefore').value});window.location.href=apiUrl+'?'+p};
$('refreshBtn').onclick=load;
$('resetBtn').onclick=()=>{$('search').value='';['yearFilter','classFilter','sectionFilter','statusFilter','installmentFilter'].forEach(id=>$(id).value='all');$('dueBefore').value='';page=1;load()};
['yearFilter','classFilter','sectionFilter','statusFilter','installmentFilter','dueBefore'].forEach(id=>$(id).onchange=()=>{page=1;load()});
let timer;$('search').oninput=()=>{clearTimeout(timer);timer=setTimeout(()=>{page=1;load()},300)};
document.querySelectorAll('.du-tab').forEach(b=>b.onclick=()=>{document.querySelectorAll('.du-tab').forEach(x=>x.classList.remove('active'));document.querySelectorAll('.du-panel').forEach(x=>x.classList.remove('active'));b.classList.add('active');document.querySelector(`[data-panel="${b.dataset.tab}"]`)?.classList.add('active')});
document.querySelector('.fee-nav a[data-key="dues"]')?.classList.add('active');load();window.lucide?.createIcons();
})();
</script>

<?php require dirname(__DIR__).'/includes/layout-end.php'; ?>

<?php
declare(strict_types=1);

$pageTitle='Fee Refund Management';
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
.rf-page{display:grid;gap:16px}
.rf-page .page-title{font-size:28px;line-height:1.1}
.rf-page .page-subtitle{margin-top:4px}
.rf-message{display:none}.rf-message.show{display:block}
.rf-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.rf-stat{border-radius:14px;min-height:110px;padding:18px 20px;display:flex;align-items:center;gap:14px;color:#fff;position:relative;overflow:hidden;box-shadow:0 12px 28px rgba(15,23,42,.08)}
.rf-stat::after{content:"";position:absolute;width:110px;height:110px;border-radius:50%;right:-38px;top:-40px;background:rgba(255,255,255,.08)}
.rf-stat.purple{background:linear-gradient(135deg,#7448e8,#4b36cf)}
.rf-stat.green{background:linear-gradient(135deg,#45c783,#1ea568)}
.rf-stat.orange{background:linear-gradient(135deg,#ffb327,#ff8a17)}
.rf-stat.blue{background:linear-gradient(135deg,#4696ef,#2469d5)}
.rf-stat-icon{width:50px;height:50px;border-radius:50%;background:rgba(255,255,255,.16);display:grid;place-items:center;flex:0 0 auto}
.rf-stat-icon svg{width:25px;height:25px}
.rf-stat strong{display:block;font-size:25px;line-height:1}
.rf-stat small{display:block;font-size:11px;font-weight:700;opacity:.95;margin-bottom:6px}
.rf-stat .trend{font-size:9px;font-weight:700;opacity:.94;margin-top:8px}
.fee-nav{display:flex;gap:8px;overflow:auto;padding:10px}
.fee-nav a{flex:0 0 auto;display:inline-flex;align-items:center;gap:7px;padding:9px 12px;border:1px solid var(--border-soft,#e7ebf3);border-radius:9px;background:var(--card-bg,#fff);color:var(--text-main,#101a3b);font-size:11px;font-weight:800;text-decoration:none}
.fee-nav a.active{color:#fff;border-color:transparent;background:linear-gradient(135deg,#6547e8,#315ed8)}
.fee-nav svg{width:15px;height:15px}
.rf-tabs{display:flex;gap:8px;overflow:auto;padding:10px}
.rf-tab{border:1px solid var(--border-soft,#e7ebf3);background:var(--card-bg,#fff);color:var(--text-main,#101b46);border-radius:9px;padding:9px 12px;font-size:11px;font-weight:800;white-space:nowrap}
.rf-tab.active{color:#fff;border-color:transparent;background:linear-gradient(135deg,#6747e8,#2f62d7)}
.rf-panel{display:none}.rf-panel.active{display:block}
.rf-card{border-radius:14px;overflow:hidden}
.rf-card-head{padding:14px 16px;border-bottom:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;align-items:center;gap:10px}
.rf-card-actions{display:flex;gap:8px;flex-wrap:wrap}
.rf-filter{padding:14px 16px;display:grid;grid-template-columns:minmax(220px,1.4fr) repeat(5,minmax(135px,.7fr)) auto;gap:10px;border-bottom:1px solid var(--border-soft,#e7ebf3)}
.rf-table-wrap{overflow:auto}
.rf-table{min-width:1320px}
.rf-table th{font-size:10px}
.rf-table td{font-size:11px;vertical-align:middle}
.rf-empty{padding:40px 18px!important;text-align:center!important;color:var(--text-muted,#64748b)}
.rf-badge{display:inline-flex;align-items:center;padding:5px 9px;border-radius:999px;font-size:9px;font-weight:800;text-transform:capitalize}
.rf-badge.approved,.rf-badge.completed,.rf-badge.active{color:#16834f;background:#e8f8ef}
.rf-badge.pending{color:#9a6700;background:#fff7d6}
.rf-badge.rejected,.rf-badge.cancelled{color:#dc2626;background:#fff0f1}
.rf-actions{display:flex;gap:5px;flex-wrap:wrap}
.rf-action{width:30px;height:30px;display:grid;place-items:center;border:1px solid #d7def1;border-radius:7px;background:var(--card-bg,#fff);color:#4f46e5}
.rf-action.danger{color:#dc2626}.rf-action.success{color:#16834f}
.rf-action svg{width:13px;height:13px}
.rf-pagination{padding:14px 16px;border-top:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;align-items:center;gap:10px}
.rf-page-buttons{display:flex;gap:6px;flex-wrap:wrap}
.rf-page-button{min-width:34px;height:34px;border:1px solid var(--border-soft,#e7ebf3);background:#fff;border-radius:8px;font-size:11px;font-weight:800}
.rf-page-button.active{color:#fff;border-color:transparent;background:linear-gradient(135deg,#6547e8,#315ed8)}
.rf-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.rf-form-grid .full{grid-column:1/-1}
.rf-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
.rf-summary>div{border:1px solid var(--border-soft,#e7ebf3);border-radius:10px;padding:12px}
.rf-summary small{display:block;font-size:9px;color:var(--text-muted,#64748b);font-weight:700}
.rf-summary strong{display:block;margin-top:4px;font-size:14px}
#refundModal .modal-dialog,#detailModal .modal-dialog,#completeModal .modal-dialog{max-height:calc(100dvh - 32px);margin:16px auto}
#refundModal .modal-content,#detailModal .modal-content,#completeModal .modal-content{max-height:calc(100dvh - 32px);overflow:hidden}
#refundModal form,#completeModal form{display:flex;flex-direction:column;max-height:calc(100dvh - 32px)}
#refundModal .modal-body,#detailModal .modal-body,#completeModal .modal-body{overflow-y:auto;min-height:0}
@media(max-width:1200px){.rf-filter{grid-template-columns:repeat(3,1fr)}}
@media(max-width:900px){.rf-stats,.rf-summary{grid-template-columns:repeat(2,1fr)}}
@media(max-width:575px){.rf-stats,.rf-filter,.rf-form-grid,.rf-summary{grid-template-columns:1fr}.rf-form-grid .full{grid-column:auto}.rf-card-head,.rf-pagination{align-items:flex-start;flex-direction:column}}
</style>

<div class="rf-page" data-page="refunds">
<div class="page-heading">
 <div><h1 class="page-title">Fee Refund Management</h1><p class="page-subtitle">Create, approve and complete student fee refund requests.</p></div>
 <div class="page-actions">
  <button id="addRefundBtn" class="btn-ui btn-primary-ui" type="button"><i data-lucide="undo-2"></i> Refund Request</button>
  <button id="exportBtn" class="btn-ui" type="button"><i data-lucide="download"></i> Export</button>
  <button id="refreshBtn" class="btn-ui" type="button"><i data-lucide="refresh-cw"></i> Refresh</button>
 </div>
</div>

<div id="rfMessage" class="alert rf-message"></div>

<section class="rf-stats">
 <article class="rf-stat purple"><span class="rf-stat-icon"><i data-lucide="undo-2"></i></span><div><small>Total Refund Requests</small><strong id="statTotal">0</strong><div class="trend">All refund records</div></div></article>
 <article class="rf-stat green"><span class="rf-stat-icon"><i data-lucide="circle-check-big"></i></span><div><small>Completed Refunds</small><strong id="statCompleted">₹0</strong><div class="trend">Successfully refunded value</div></div></article>
 <article class="rf-stat orange"><span class="rf-stat-icon"><i data-lucide="clock-3"></i></span><div><small>Pending Approval</small><strong id="statPending">0</strong><div class="trend">Awaiting review</div></div></article>
 <article class="rf-stat blue"><span class="rf-stat-icon"><i data-lucide="badge-indian-rupee"></i></span><div><small>Approved Amount</small><strong id="statApproved">₹0</strong><div class="trend">Approved for payment</div></div></article>
</section>

<section class="ui-card fee-nav">
<a href="fee-dashboard.php" data-key="dashboard"><i data-lucide="layout-dashboard"></i> Dashboard</a>
<a href="fee-setup.php" data-key="setup"><i data-lucide="settings-2"></i> Fee Setup</a>
<a href="fee-categories.php" data-key="categories"><i data-lucide="tags"></i> Fee Categories</a>
<a href="fee-structures.php" data-key="structures"><i data-lucide="list-tree"></i> Fee Structures</a>
<a href="fee-assignment.php" data-key="assignment"><i data-lucide="user-round-check"></i> Fee Assignment</a>
<a href="fee-collection.php" data-key="collection"><i data-lucide="indian-rupee"></i> Fee Collection</a>
<a href="receipt-management.php" data-key="receipts"><i data-lucide="receipt-text"></i> Receipts</a>
<a href="discount-management.php" data-key="discounts"><i data-lucide="badge-percent"></i> Discounts</a>
<a href="scholarship-management.php" data-key="scholarships"><i data-lucide="graduation-cap"></i> Scholarships</a>
<a href="fine-management.php" data-key="fines"><i data-lucide="landmark"></i> Fines</a>
<a href="fee-refund-management.php" data-key="refunds"><i data-lucide="undo-2"></i> Refunds</a>
</section>

<section class="ui-card rf-tabs">
 <button class="rf-tab active" data-tab="dashboard" type="button">Refund Dashboard</button>
 <button class="rf-tab" data-tab="history" type="button">Refund History</button>
</section>

<section class="rf-panel active" data-panel="dashboard">
<section class="ui-card rf-card">
<div class="rf-card-head"><strong>Refund Request List</strong><div class="rf-card-actions"><small id="recordInfo" class="text-muted">Loading...</small></div></div>
<div class="rf-filter">
 <input id="search" class="form-control" placeholder="Refund no., student or admission no...">
 <select id="yearFilter" class="form-select"><option value="all">All Academic Years</option></select>
 <select id="typeFilter" class="form-select"><option value="all">All Refund Types</option><option value="full">Full Refund</option><option value="partial">Partial Refund</option><option value="excess">Excess Payment</option><option value="cancellation">Fee Cancellation</option></select>
 <select id="statusFilter" class="form-select"><option value="all">All Statuses</option><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option><option value="completed">Completed</option></select>
 <select id="methodFilter" class="form-select"><option value="all">All Payment Modes</option></select>
 <input id="fromDate" class="form-control" type="date">
 <button id="resetBtn" class="btn-ui" type="button"><i data-lucide="rotate-ccw"></i> Reset</button>
</div>
<div class="rf-table-wrap"><table class="data-table rf-table"><thead><tr><th>#</th><th>Refund No.</th><th>Request Date</th><th>Student</th><th>Admission No.</th><th>Academic Year</th><th>Receipt</th><th>Refund Type</th><th>Paid Amount</th><th>Refund Amount</th><th>Status</th><th>Payment Mode</th><th>Actions</th></tr></thead><tbody id="refundBody"><tr><td colspan="13" class="rf-empty">Loading...</td></tr></tbody></table></div>
<div class="rf-pagination"><small id="pageInfo" class="text-muted"></small><div id="pagination" class="rf-page-buttons"></div></div>
</section>
</section>

<section class="rf-panel" data-panel="history">
<section class="ui-card rf-card">
<div class="rf-card-head"><strong>Refund History</strong></div>
<div class="rf-table-wrap"><table class="data-table rf-table"><thead><tr><th>Date</th><th>Action</th><th>Refund No.</th><th>Student</th><th>Amount</th><th>Status</th><th>User</th><th>Remarks</th></tr></thead><tbody id="historyBody"></tbody></table></div>
</section>
</section>
</div>

<div class="modal fade" id="refundModal" tabindex="-1">
<div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content"><form id="refundForm">
<div class="modal-header"><h5 id="refundModalTitle" class="modal-title">Create Refund Request</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body rf-form-grid">
<input id="refundId" type="hidden">
<div><label class="form-label">Academic Year *</label><select id="academicYearId" class="form-select" required></select></div>
<div><label class="form-label">Student *</label><select id="studentId" class="form-select" required></select></div>
<div><label class="form-label">Paid Receipt *</label><select id="receiptId" class="form-select" required></select></div>
<div><label class="form-label">Refund Type *</label><select id="refundType" class="form-select"><option value="partial">Partial Refund</option><option value="full">Full Refund</option><option value="excess">Excess Payment</option><option value="cancellation">Fee Cancellation</option></select></div>
<div><label class="form-label">Refund Amount *</label><input id="refundAmount" class="form-control" type="number" min="0.01" step="0.01" required></div>
<div><label class="form-label">Request Date *</label><input id="requestDate" class="form-control" type="date" required></div>
<div class="full"><div id="receiptSummary" class="rf-summary"><div><small>Receipt Amount</small><strong>₹0</strong></div><div><small>Previously Refunded</small><strong>₹0</strong></div><div><small>Available to Refund</small><strong>₹0</strong></div><div><small>Payment Mode</small><strong>-</strong></div></div></div>
<div class="full"><label class="form-label">Refund Reason *</label><textarea id="refundReason" class="form-control" rows="3" maxlength="500" required></textarea></div>
</div>
<div class="modal-footer"><button type="button" class="btn-ui" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn-ui btn-primary-ui">Save Refund Request</button></div>
</form></div></div>
</div>

<div class="modal fade" id="detailModal" tabindex="-1">
<div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content">
<div class="modal-header"><div><h5 class="modal-title">Refund Details</h5><small id="detailSubtitle" class="text-muted"></small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body"><div id="detailContent" class="rf-summary"></div><div class="mt-3"><strong>Transaction and Approval Details</strong></div><div id="detailExtra" class="alert alert-light mt-2 mb-0"></div></div>
<div class="modal-footer"><button type="button" class="btn-ui" data-bs-dismiss="modal">Close</button></div>
</div></div>
</div>

<div class="modal fade" id="completeModal" tabindex="-1">
<div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><form id="completeForm">
<div class="modal-header"><h5 class="modal-title">Complete Refund Payment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body rf-form-grid">
<input id="completeRefundId" type="hidden">
<div><label class="form-label">Refund Payment Mode *</label><select id="paymentMethodId" class="form-select" required></select></div>
<div><label class="form-label">Refund Date *</label><input id="refundDate" class="form-control" type="date" required></div>
<div class="full"><label class="form-label">Transaction Reference</label><input id="transactionReference" class="form-control" maxlength="120"></div>
<div class="full"><label class="form-label">Completion Remarks</label><textarea id="completionRemarks" class="form-control" rows="3" maxlength="500"></textarea></div>
</div>
<div class="modal-footer"><button type="button" class="btn-ui" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn-ui btn-primary-ui">Complete Refund</button></div>
</form></div></div>
</div>

<script>
(function(){
'use strict';
const apiUrl=new URL('../api/fee-refund-management.php',window.location.href).href;
let csrfToken=<?=json_encode($feeCsrf)?>;
let meta={years:[],students:[],receipts:[],methods:[]};
let rows=[],page=1,permissions={approve:false,complete:false,delete:false};
const $=id=>document.getElementById(id);
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
const money=v=>new Intl.NumberFormat('en-IN',{style:'currency',currency:'INR',maximumFractionDigits:2}).format(Number(v||0));
const badge=v=>`<span class="rf-badge ${esc(String(v||'').toLowerCase())}">${esc(v||'-')}</span>`;

async function request(action,data={},method='GET'){
 let response;
 if(method==='GET'){const url=new URL(apiUrl);url.searchParams.set('action',action);Object.entries(data).forEach(([k,v])=>{if(v!==''&&v!==null&&v!==undefined)url.searchParams.set(k,String(v))});response=await fetch(url,{headers:{Accept:'application/json'},credentials:'same-origin'});}
 else response=await fetch(apiUrl,{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json'},credentials:'same-origin',body:JSON.stringify({action,csrf_token:csrfToken,...data})});
 const text=await response.text();let result;try{result=JSON.parse(text)}catch{throw new Error(`Refund API returned HTTP ${response.status}. ${text.replace(/\s+/g,' ').trim().slice(0,220)||'Invalid server response.'}`)}
 if(!response.ok||!result.success)throw new Error(result.message||'Request failed.');if(result.data?.csrf_token)csrfToken=result.data.csrf_token;return result;
}
function message(text,ok=false){const b=$('rfMessage');b.className='alert rf-message show '+(ok?'alert-success':'alert-danger');b.textContent=text}
function fill(id,items,key,label,first=''){const e=$(id);e.innerHTML=(first?`<option value="">${esc(first)}</option>`:'')+items.map(x=>`<option value="${esc(x[key])}">${esc(x[label])}</option>`).join('')}
function updateStats(s={}){$('statTotal').textContent=Number(s.total||0).toLocaleString('en-IN');$('statCompleted').textContent=money(s.completed||0);$('statPending').textContent=Number(s.pending||0).toLocaleString('en-IN');$('statApproved').textContent=money(s.approved||0)}
function render(data){
 rows=data;$('refundBody').innerHTML=data.map(r=>`<tr><td>${r.row_number}</td><td><strong>${esc(r.refund_no)}</strong></td><td>${esc(r.request_date)}</td><td>${esc(r.student_name)}</td><td>${esc(r.admission_no)}</td><td>${esc(r.year_name)}</td><td>${esc(r.receipt_no)}</td><td>${esc(r.refund_type)}</td><td>${money(r.receipt_paid_amount)}</td><td><strong>${money(r.refund_amount)}</strong></td><td>${badge(r.refund_status)}</td><td>${esc(r.refund_method_name||'-')}</td><td><div class="rf-actions"><button class="rf-action js-view" data-id="${r.id}" title="View"><i data-lucide="eye"></i></button>${r.refund_status==='pending'?`<button class="rf-action js-edit" data-id="${r.id}" title="Edit"><i data-lucide="pencil"></i></button>`:''}${permissions.approve&&r.refund_status==='pending'?`<button class="rf-action success js-approve" data-id="${r.id}" title="Approve"><i data-lucide="check"></i></button><button class="rf-action danger js-reject" data-id="${r.id}" title="Reject"><i data-lucide="x"></i></button>`:''}${permissions.complete&&r.refund_status==='approved'?`<button class="rf-action success js-complete" data-id="${r.id}" title="Complete"><i data-lucide="badge-check"></i></button>`:''}${permissions.delete&&['pending','rejected'].includes(r.refund_status)?`<button class="rf-action danger js-delete" data-id="${r.id}" title="Delete"><i data-lucide="trash-2"></i></button>`:''}</div></td></tr>`).join('')||'<tr><td colspan="13" class="rf-empty">No refund requests found.</td></tr>';
 document.querySelectorAll('.js-view').forEach(b=>b.onclick=()=>viewRefund(Number(b.dataset.id)));document.querySelectorAll('.js-edit').forEach(b=>b.onclick=()=>openRefund(Number(b.dataset.id)));document.querySelectorAll('.js-approve').forEach(b=>b.onclick=()=>approveRefund(Number(b.dataset.id),'approved'));document.querySelectorAll('.js-reject').forEach(b=>b.onclick=()=>approveRefund(Number(b.dataset.id),'rejected'));document.querySelectorAll('.js-complete').forEach(b=>b.onclick=()=>openComplete(Number(b.dataset.id)));document.querySelectorAll('.js-delete').forEach(b=>b.onclick=()=>deleteRefund(Number(b.dataset.id)));window.lucide?.createIcons();
}
function renderHistory(data){$('historyBody').innerHTML=data.map(r=>`<tr><td>${esc(r.created_at)}</td><td>${esc(r.action_name)}</td><td>${esc(r.refund_no||'-')}</td><td>${esc(r.student_name||'-')}</td><td>${money(r.amount||0)}</td><td>${badge(r.refund_status||'-')}</td><td>${esc(r.user_name||'-')}</td><td>${esc(r.remarks||'-')}</td></tr>`).join('')||'<tr><td colspan="8" class="rf-empty">No refund history.</td></tr>'}
function renderPagination(p={}){
 const total=Number(p.total||0),current=Number(p.page||1),per=Number(p.per_page||10),last=Math.max(1,Number(p.last_page||1)),start=total?((current-1)*per)+1:0,end=Math.min(current*per,total);
 $('recordInfo').textContent=`${total} refund${total===1?'':'s'}`;$('pageInfo').textContent=`Showing ${start}-${end} of ${total}`;
 let html=`<button class="rf-page-button" data-page="${current-1}" ${current<=1?'disabled':''}>‹</button>`;for(let x=Math.max(1,current-2);x<=Math.min(last,current+2);x++)html+=`<button class="rf-page-button ${x===current?'active':''}" data-page="${x}">${x}</button>`;html+=`<button class="rf-page-button" data-page="${current+1}" ${current>=last?'disabled':''}>›</button>`;$('pagination').innerHTML=html;document.querySelectorAll('.rf-page-button').forEach(b=>b.onclick=()=>{if(!b.disabled){page=Number(b.dataset.page);load()}});
}
async function load(){
 try{const r=await request('list',{search:$('search').value.trim(),academic_year_id:$('yearFilter').value,refund_type:$('typeFilter').value,refund_status:$('statusFilter').value,payment_method_id:$('methodFilter').value,from_date:$('fromDate').value,page,per_page:10});meta=r.data.meta;permissions=r.data.permissions||permissions;render(r.data.records||[]);renderHistory(r.data.history||[]);renderPagination(r.data.pagination||{});updateStats(r.data.stats||{});
 if($('yearFilter').options.length<=1)fill('yearFilter',meta.years,'id','year_name','All Academic Years');if($('methodFilter').options.length<=1)fill('methodFilter',meta.methods,'id','method_name','All Payment Modes');
 }catch(e){message(e.message)}
}
function refreshStudents(){
 const year=Number($('academicYearId').value||0);fill('studentId',meta.students.filter(x=>!year||Number(x.academic_year_id)===year),'id','student_name','Select student');refreshReceipts();
}
function refreshReceipts(){
 const student=Number($('studentId').value||0),year=Number($('academicYearId').value||0);fill('receiptId',meta.receipts.filter(x=>(!student||Number(x.student_id)===student)&&(!year||Number(x.academic_year_id)===year)&&Number(x.available_refund)>0),'id','receipt_label','Select paid receipt');updateReceiptSummary();
}
function selectedReceipt(){return meta.receipts.find(x=>Number(x.id)===Number($('receiptId').value))}
function updateReceiptSummary(){
 const r=selectedReceipt();$('receiptSummary').innerHTML=`<div><small>Receipt Amount</small><strong>${money(r?.paid_amount||0)}</strong></div><div><small>Previously Refunded</small><strong>${money(r?.refunded_amount||0)}</strong></div><div><small>Available to Refund</small><strong>${money(r?.available_refund||0)}</strong></div><div><small>Payment Mode</small><strong>${esc(r?.payment_methods||'-')}</strong></div>`;
 if($('refundType').value==='full'&&r)$('refundAmount').value=Number(r.available_refund).toFixed(2);
}
function openRefund(id=0){
 const r=rows.find(x=>Number(x.id)===id);$('refundId').value=r?.id||'';fill('academicYearId',meta.years,'id','year_name');$('academicYearId').value=r?.academic_year_id||meta.years[0]?.id||'';refreshStudents();$('studentId').value=r?.student_id||'';refreshReceipts();$('receiptId').value=r?.receipt_id||'';$('refundType').value=r?.refund_type||'partial';$('refundAmount').value=r?.refund_amount||'';$('requestDate').value=r?.request_date||new Date().toISOString().slice(0,10);$('refundReason').value=r?.refund_reason||'';updateReceiptSummary();$('refundModalTitle').textContent=r?'Edit Refund Request':'Create Refund Request';bootstrap.Modal.getOrCreateInstance($('refundModal')).show();
}
async function viewRefund(id){
 try{const r=await request('detail',{id});const x=r.data.refund;$('detailSubtitle').textContent=`${x.refund_no} • ${x.student_name}`;$('detailContent').innerHTML=[['Refund No.',x.refund_no],['Student',x.student_name],['Admission No.',x.admission_no],['Academic Year',x.year_name],['Receipt No.',x.receipt_no],['Receipt Paid',money(x.receipt_paid_amount)],['Refund Type',x.refund_type],['Refund Amount',money(x.refund_amount)],['Request Date',x.request_date],['Status',x.refund_status],['Payment Mode',x.refund_method_name||'-'],['Transaction Reference',x.transaction_reference||'-']].map(([l,v])=>`<div><small>${esc(l)}</small><strong>${esc(v)}</strong></div>`).join('');$('detailExtra').innerHTML=`<strong>Reason:</strong> ${esc(x.refund_reason)}<br><strong>Approval:</strong> ${esc(x.approval_remarks||'-')}<br><strong>Completion:</strong> ${esc(x.completion_remarks||'-')}`;bootstrap.Modal.getOrCreateInstance($('detailModal')).show()}catch(e){message(e.message)}
}
async function approveRefund(id,status){const remarks=prompt(status==='approved'?'Approval remarks:':'Rejection reason:')??'';try{const r=await request('approve',{id,refund_status:status,remarks},'POST');message(r.message,true);await load()}catch(e){message(e.message)}}
function openComplete(id){$('completeRefundId').value=id;fill('paymentMethodId',meta.methods,'id','method_name');$('refundDate').value=new Date().toISOString().slice(0,10);$('transactionReference').value='';$('completionRemarks').value='';bootstrap.Modal.getOrCreateInstance($('completeModal')).show()}
async function deleteRefund(id){if(!confirm('Delete this refund request?'))return;try{const r=await request('delete',{id},'POST');message(r.message,true);await load()}catch(e){message(e.message)}}
$('refundForm').onsubmit=async e=>{e.preventDefault();try{const r=await request('save',{id:Number($('refundId').value||0),academic_year_id:Number($('academicYearId').value),student_id:Number($('studentId').value),receipt_id:Number($('receiptId').value),refund_type:$('refundType').value,refund_amount:Number($('refundAmount').value||0),request_date:$('requestDate').value,refund_reason:$('refundReason').value.trim()},'POST');bootstrap.Modal.getInstance($('refundModal'))?.hide();message(r.message,true);await load()}catch(err){message(err.message)}};
$('completeForm').onsubmit=async e=>{e.preventDefault();try{const r=await request('complete',{id:Number($('completeRefundId').value),payment_method_id:Number($('paymentMethodId').value),refund_date:$('refundDate').value,transaction_reference:$('transactionReference').value.trim(),completion_remarks:$('completionRemarks').value.trim()},'POST');bootstrap.Modal.getInstance($('completeModal'))?.hide();message(r.message,true);await load()}catch(err){message(err.message)}};
document.querySelectorAll('.rf-tab').forEach(b=>b.onclick=()=>{document.querySelectorAll('.rf-tab').forEach(x=>x.classList.remove('active'));document.querySelectorAll('.rf-panel').forEach(x=>x.classList.remove('active'));b.classList.add('active');document.querySelector(`[data-panel="${b.dataset.tab}"]`)?.classList.add('active')});
$('addRefundBtn').onclick=()=>openRefund();$('refreshBtn').onclick=load;$('academicYearId').onchange=refreshStudents;$('studentId').onchange=refreshReceipts;$('receiptId').onchange=updateReceiptSummary;$('refundType').onchange=updateReceiptSummary;
$('exportBtn').onclick=()=>{const p=new URLSearchParams({action:'export',search:$('search').value.trim(),academic_year_id:$('yearFilter').value,refund_type:$('typeFilter').value,refund_status:$('statusFilter').value,payment_method_id:$('methodFilter').value,from_date:$('fromDate').value});window.location.href=apiUrl+'?'+p};
$('resetBtn').onclick=()=>{$('search').value='';['yearFilter','typeFilter','statusFilter','methodFilter'].forEach(id=>$(id).value='all');$('fromDate').value='';page=1;load()};['yearFilter','typeFilter','statusFilter','methodFilter','fromDate'].forEach(id=>$(id).onchange=()=>{page=1;load()});let timer;$('search').oninput=()=>{clearTimeout(timer);timer=setTimeout(()=>{page=1;load()},300)};
document.querySelector('.fee-nav a[data-key="refunds"]')?.classList.add('active');load();window.lucide?.createIcons();
})();
</script>

<?php require dirname(__DIR__).'/includes/layout-end.php'; ?>

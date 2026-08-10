<?php
declare(strict_types=1);

$pageTitle='Online Payment';
$pageKey='fee_management';

require dirname(__DIR__).'/includes/layout-start.php';

if(session_status()!==PHP_SESSION_ACTIVE){
    session_start();
}
if(empty($_SESSION['fee_csrf_token']) || !is_string($_SESSION['fee_csrf_token'])){
    $_SESSION['fee_csrf_token']=bin2hex(random_bytes(32));
}
$feeCsrf=$_SESSION['fee_csrf_token'];
?>
<style>
.op-page{display:grid;gap:16px}
.op-page .page-title{font-size:28px;line-height:1.1}
.op-page .page-subtitle{margin-top:4px}
.op-message{display:none}.op-message.show{display:block}
.op-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.op-stat{border-radius:14px;min-height:110px;padding:18px 20px;display:flex;align-items:center;gap:14px;color:#fff;position:relative;overflow:hidden;box-shadow:0 12px 28px rgba(15,23,42,.08)}
.op-stat::after{content:"";position:absolute;width:110px;height:110px;border-radius:50%;right:-38px;top:-40px;background:rgba(255,255,255,.08)}
.op-stat.purple{background:linear-gradient(135deg,#7448e8,#4b36cf)}
.op-stat.green{background:linear-gradient(135deg,#45c783,#1ea568)}
.op-stat.orange{background:linear-gradient(135deg,#ffb327,#ff8a17)}
.op-stat.blue{background:linear-gradient(135deg,#4696ef,#2469d5)}
.op-stat-icon{width:50px;height:50px;border-radius:50%;background:rgba(255,255,255,.16);display:grid;place-items:center;flex:0 0 auto}
.op-stat-icon svg{width:25px;height:25px}
.op-stat strong{display:block;font-size:25px;line-height:1}
.op-stat small{display:block;font-size:11px;font-weight:700;opacity:.95;margin-bottom:6px}
.op-stat .trend{font-size:9px;font-weight:700;opacity:.94;margin-top:8px}
.fee-nav{display:flex;gap:8px;overflow:auto;padding:10px}
.fee-nav a{flex:0 0 auto;display:inline-flex;align-items:center;gap:7px;padding:9px 12px;border:1px solid var(--border-soft,#e7ebf3);border-radius:9px;background:var(--card-bg,#fff);color:var(--text-main,#101a3b);font-size:11px;font-weight:800;text-decoration:none}
.fee-nav a.active{color:#fff;border-color:transparent;background:linear-gradient(135deg,#6547e8,#315ed8)}
.fee-nav svg{width:15px;height:15px}
.op-tabs{display:flex;gap:8px;overflow:auto;padding:10px}
.op-tab{border:1px solid var(--border-soft,#e7ebf3);background:var(--card-bg,#fff);color:var(--text-main,#101b46);border-radius:9px;padding:9px 12px;font-size:11px;font-weight:800;white-space:nowrap}
.op-tab.active{color:#fff;border-color:transparent;background:linear-gradient(135deg,#6747e8,#2f62d7)}
.op-panel{display:none}.op-panel.active{display:block}
.op-card{border-radius:14px;overflow:hidden}
.op-card-head{padding:14px 16px;border-bottom:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;align-items:center;gap:10px}
.op-filter{padding:14px 16px;display:grid;grid-template-columns:minmax(220px,1.4fr) repeat(5,minmax(135px,.7fr)) auto;gap:10px;border-bottom:1px solid var(--border-soft,#e7ebf3)}
.op-table-wrap{overflow:auto}
.op-table{min-width:1380px}
.op-table th{font-size:10px}
.op-table td{font-size:11px;vertical-align:middle}
.op-empty{padding:40px 18px!important;text-align:center!important;color:var(--text-muted,#64748b)}
.op-badge{display:inline-flex;align-items:center;padding:5px 9px;border-radius:999px;font-size:9px;font-weight:800;text-transform:capitalize}
.op-badge.successful,.op-badge.refunded{color:#16834f;background:#e8f8ef}
.op-badge.pending,.op-badge.processing{color:#9a6700;background:#fff7d6}
.op-badge.failed,.op-badge.cancelled{color:#dc2626;background:#fff0f1}
.op-actions{display:flex;gap:5px;flex-wrap:wrap}
.op-action{width:30px;height:30px;display:grid;place-items:center;border:1px solid #d7def1;border-radius:7px;background:var(--card-bg,#fff);color:#4f46e5}
.op-action.danger{color:#dc2626}.op-action.success{color:#16834f}
.op-action svg{width:13px;height:13px}
.op-pagination{padding:14px 16px;border-top:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;align-items:center;gap:10px}
.op-page-buttons{display:flex;gap:6px;flex-wrap:wrap}
.op-page-button{min-width:34px;height:34px;border:1px solid var(--border-soft,#e7ebf3);background:#fff;border-radius:8px;font-size:11px;font-weight:800}
.op-page-button.active{color:#fff;border-color:transparent;background:linear-gradient(135deg,#6547e8,#315ed8)}
.op-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.op-form-grid .full{grid-column:1/-1}
.op-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
.op-summary>div{border:1px solid var(--border-soft,#e7ebf3);border-radius:10px;padding:12px}
.op-summary small{display:block;font-size:9px;color:var(--text-muted,#64748b);font-weight:700}
.op-summary strong{display:block;margin-top:4px;font-size:14px}
#paymentModal .modal-dialog,#detailModal .modal-dialog{max-height:calc(100dvh - 32px);margin:16px auto}
#paymentModal .modal-content,#detailModal .modal-content{max-height:calc(100dvh - 32px);overflow:hidden}
#paymentModal form{display:flex;flex-direction:column;max-height:calc(100dvh - 32px)}
#paymentModal .modal-body,#detailModal .modal-body{overflow-y:auto;min-height:0}
@media(max-width:1200px){.op-filter{grid-template-columns:repeat(3,1fr)}}
@media(max-width:900px){.op-stats,.op-summary{grid-template-columns:repeat(2,1fr)}}
@media(max-width:575px){.op-stats,.op-filter,.op-form-grid,.op-summary{grid-template-columns:1fr}.op-form-grid .full{grid-column:auto}.op-card-head,.op-pagination{align-items:flex-start;flex-direction:column}}
</style>

<div class="op-page" data-page="online_payment">
<div class="page-heading">
 <div><h1 class="page-title">Online Payment</h1><p class="page-subtitle">Collect student fees securely through UPI, cards, net banking and wallets.</p></div>
 <div class="page-actions">
  <button id="newPaymentBtn" class="btn-ui btn-primary-ui" type="button"><i data-lucide="credit-card"></i> New Payment</button>
  <button id="exportBtn" class="btn-ui" type="button"><i data-lucide="download"></i> Export</button>
  <button id="refreshBtn" class="btn-ui" type="button"><i data-lucide="refresh-cw"></i> Refresh</button>
 </div>
</div>

<div id="opMessage" class="alert op-message"></div>

<section class="op-stats">
 <article class="op-stat purple"><span class="op-stat-icon"><i data-lucide="badge-indian-rupee"></i></span><div><small>Total Online Collection</small><strong id="statTotal">₹0</strong><div class="trend">Successful online payments</div></div></article>
 <article class="op-stat green"><span class="op-stat-icon"><i data-lucide="circle-check-big"></i></span><div><small>Successful Payments</small><strong id="statSuccess">0</strong><div class="trend">Completed gateway transactions</div></div></article>
 <article class="op-stat orange"><span class="op-stat-icon"><i data-lucide="loader-circle"></i></span><div><small>Pending / Processing</small><strong id="statPending">0</strong><div class="trend">Awaiting gateway confirmation</div></div></article>
 <article class="op-stat blue"><span class="op-stat-icon"><i data-lucide="undo-2"></i></span><div><small>Refunded Amount</small><strong id="statRefunded">₹0</strong><div class="trend">Completed online refunds</div></div></article>
</section>

<section class="ui-card fee-nav">
<a href="fee-dashboard.php" data-key="dashboard"><i data-lucide="layout-dashboard"></i> Dashboard</a>
<a href="fee-setup.php" data-key="setup"><i data-lucide="settings-2"></i> Fee Setup</a>
<a href="fee-categories.php" data-key="categories"><i data-lucide="tags"></i> Fee Categories</a>
<a href="fee-structures.php" data-key="structures"><i data-lucide="list-tree"></i> Fee Structures</a>
<a href="fee-assignment.php" data-key="assignment"><i data-lucide="user-round-check"></i> Fee Assignment</a>
<a href="fee-collection.php" data-key="collection"><i data-lucide="indian-rupee"></i> Fee Collection</a>
<a href="receipt-management.php" data-key="receipts"><i data-lucide="receipt-text"></i> Receipts</a>
<a href="online-payment.php" data-key="online_payment"><i data-lucide="credit-card"></i> Online Payment</a>
<a href="due-management.php" data-key="dues"><i data-lucide="clock-alert"></i> Dues</a>
<a href="fee-reports.php" data-key="reports"><i data-lucide="chart-column"></i> Reports</a>
</section>

<section class="ui-card op-tabs">
 <button class="op-tab active" data-tab="dashboard" type="button">Payment Dashboard</button>
 <button class="op-tab" data-tab="history" type="button">Payment History</button>
</section>

<section class="op-panel active" data-panel="dashboard">
<section class="ui-card op-card">
<div class="op-card-head"><strong>Online Payment Transactions</strong><small id="recordInfo" class="text-muted">Loading...</small></div>
<div class="op-filter">
 <input id="search" class="form-control" placeholder="Transaction, receipt, student or admission no...">
 <select id="yearFilter" class="form-select"><option value="all">All Academic Years</option></select>
 <select id="methodFilter" class="form-select"><option value="all">All Methods</option><option value="upi">UPI</option><option value="card">Card</option><option value="netbanking">Net Banking</option><option value="wallet">Wallet</option></select>
 <select id="statusFilter" class="form-select"><option value="all">All Statuses</option><option value="pending">Pending</option><option value="processing">Processing</option><option value="successful">Successful</option><option value="failed">Failed</option><option value="cancelled">Cancelled</option><option value="refunded">Refunded</option></select>
 <input id="fromDate" class="form-control" type="date">
 <input id="toDate" class="form-control" type="date">
 <button id="resetBtn" class="btn-ui" type="button"><i data-lucide="rotate-ccw"></i> Reset</button>
</div>
<div class="op-table-wrap"><table class="data-table op-table"><thead><tr><th>#</th><th>Transaction ID</th><th>Date</th><th>Student</th><th>Admission No.</th><th>Academic Year</th><th>Fee Structure</th><th>Method</th><th>Amount</th><th>Gateway Reference</th><th>Receipt</th><th>Status</th><th>Actions</th></tr></thead><tbody id="paymentBody"><tr><td colspan="13" class="op-empty">Loading...</td></tr></tbody></table></div>
<div class="op-pagination"><small id="pageInfo" class="text-muted"></small><div id="pagination" class="op-page-buttons"></div></div>
</section>
</section>

<section class="op-panel" data-panel="history">
<section class="ui-card op-card">
<div class="op-card-head"><strong>Gateway Event History</strong></div>
<div class="op-table-wrap"><table class="data-table op-table"><thead><tr><th>Date</th><th>Transaction ID</th><th>Event</th><th>Old Status</th><th>New Status</th><th>Gateway Reference</th><th>Message</th></tr></thead><tbody id="historyBody"></tbody></table></div>
</section>
</section>
</div>

<div class="modal fade" id="paymentModal" tabindex="-1">
<div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content"><form id="paymentForm">
<div class="modal-header"><h5 class="modal-title">New Online Payment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body op-form-grid">
<div><label class="form-label">Academic Year *</label><select id="academicYearId" class="form-select" required></select></div>
<div><label class="form-label">Student *</label><select id="studentId" class="form-select" required></select></div>
<div><label class="form-label">Fee Assignment *</label><select id="assignmentId" class="form-select" required></select></div>
<div><label class="form-label">Payment Method *</label><select id="paymentMethod" class="form-select"><option value="upi">UPI</option><option value="card">Card</option><option value="netbanking">Net Banking</option><option value="wallet">Wallet</option></select></div>
<div><label class="form-label">Payment Amount *</label><input id="paymentAmount" class="form-control" type="number" min="1" step="0.01" required></div>
<div><label class="form-label">Payment Type</label><select id="paymentType" class="form-select"><option value="partial">Partial Payment</option><option value="full">Full Payment</option></select></div>
<div class="full"><div id="feeSummary" class="op-summary"><div><small>Total Fee</small><strong>₹0</strong></div><div><small>Paid</small><strong>₹0</strong></div><div><small>Outstanding</small><strong>₹0</strong></div><div><small>Fine Included</small><strong>₹0</strong></div></div></div>
<div class="full"><label class="form-label">Payer Notes</label><textarea id="payerNotes" class="form-control" rows="2" maxlength="500"></textarea></div>
</div>
<div class="modal-footer"><button type="button" class="btn-ui" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn-ui btn-primary-ui"><i data-lucide="lock-keyhole"></i> Proceed Securely</button></div>
</form></div></div>
</div>

<div class="modal fade" id="detailModal" tabindex="-1">
<div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content">
<div class="modal-header"><div><h5 class="modal-title">Online Payment Details</h5><small id="detailSubtitle" class="text-muted"></small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body"><div id="detailContent" class="op-summary"></div><div class="mt-3"><strong>Gateway Information</strong></div><div id="gatewayInfo" class="alert alert-light mt-2 mb-0"></div></div>
<div class="modal-footer"><button type="button" class="btn-ui" data-bs-dismiss="modal">Close</button></div>
</div></div>
</div>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
(function(){
'use strict';
const apiUrl=new URL('../api/online-payment.php',window.location.href).href;
let csrfToken=<?=json_encode($feeCsrf)?>;
let meta={years:[],students:[],assignments:[],gateway:{}};
let rows=[],page=1,permissions={refund:false};
const $=id=>document.getElementById(id);
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
const money=v=>new Intl.NumberFormat('en-IN',{style:'currency',currency:'INR',maximumFractionDigits:2}).format(Number(v||0));
const badge=v=>`<span class="op-badge ${esc(String(v||'').toLowerCase())}">${esc(v||'-')}</span>`;

async function request(action,data={},method='GET'){
 let response;
 if(method==='GET'){const url=new URL(apiUrl);url.searchParams.set('action',action);Object.entries(data).forEach(([k,v])=>{if(v!==''&&v!==null&&v!==undefined)url.searchParams.set(k,String(v))});response=await fetch(url,{headers:{Accept:'application/json'},credentials:'same-origin'});}
 else response=await fetch(apiUrl,{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json'},credentials:'same-origin',body:JSON.stringify({action,csrf_token:csrfToken,...data})});
 const text=await response.text();let result;try{result=JSON.parse(text)}catch{throw new Error(`Online Payment API returned HTTP ${response.status}. ${text.replace(/\s+/g,' ').trim().slice(0,220)||'Invalid server response.'}`)}
 if(!response.ok||!result.success)throw new Error(result.message||'Request failed.');if(result.data?.csrf_token)csrfToken=result.data.csrf_token;return result;
}
function message(text,ok=false){const b=$('opMessage');b.className='alert op-message show '+(ok?'alert-success':'alert-danger');b.textContent=text}
function fill(id,items,key,label,first=''){const e=$(id);e.innerHTML=(first?`<option value="">${esc(first)}</option>`:'')+items.map(x=>`<option value="${esc(x[key])}">${esc(x[label])}</option>`).join('')}
function updateStats(s={}){$('statTotal').textContent=money(s.total_collection||0);$('statSuccess').textContent=Number(s.successful||0).toLocaleString('en-IN');$('statPending').textContent=Number(s.pending||0).toLocaleString('en-IN');$('statRefunded').textContent=money(s.refunded_amount||0)}
function render(data){
 rows=data;$('paymentBody').innerHTML=data.map(r=>`<tr><td>${r.row_number}</td><td><strong>${esc(r.transaction_no)}</strong></td><td>${esc(r.created_at)}</td><td>${esc(r.student_name)}</td><td>${esc(r.admission_no)}</td><td>${esc(r.year_name)}</td><td>${esc(r.structure_name)}</td><td>${esc(r.payment_method)}</td><td><strong>${money(r.amount)}</strong></td><td>${esc(r.gateway_payment_id||r.gateway_order_id||'-')}</td><td>${esc(r.receipt_no||'-')}</td><td>${badge(r.payment_status)}</td><td><div class="op-actions"><button class="op-action js-view" data-id="${r.id}" title="View"><i data-lucide="eye"></i></button>${r.receipt_id?`<a class="op-action" href="${apiUrl}?action=receipt_print&id=${r.id}" target="_blank" title="Print"><i data-lucide="printer"></i></a><a class="op-action" href="${apiUrl}?action=receipt_pdf&id=${r.id}" title="PDF"><i data-lucide="file-down"></i></a>`:''}${permissions.refund&&r.payment_status==='successful'?`<button class="op-action danger js-refund" data-id="${r.id}" title="Refund"><i data-lucide="undo-2"></i></button>`:''}</div></td></tr>`).join('')||'<tr><td colspan="13" class="op-empty">No online payments found.</td></tr>';
 document.querySelectorAll('.js-view').forEach(b=>b.onclick=()=>viewPayment(Number(b.dataset.id)));document.querySelectorAll('.js-refund').forEach(b=>b.onclick=()=>refundPayment(Number(b.dataset.id)));window.lucide?.createIcons();
}
function renderHistory(data){$('historyBody').innerHTML=data.map(r=>`<tr><td>${esc(r.created_at)}</td><td>${esc(r.transaction_no)}</td><td>${esc(r.event_name)}</td><td>${esc(r.old_status||'-')}</td><td>${esc(r.new_status||'-')}</td><td>${esc(r.gateway_reference||'-')}</td><td>${esc(r.message_text||'-')}</td></tr>`).join('')||'<tr><td colspan="7" class="op-empty">No gateway history.</td></tr>'}
function renderPagination(p={}){
 const total=Number(p.total||0),current=Number(p.page||1),per=Number(p.per_page||10),last=Math.max(1,Number(p.last_page||1)),start=total?((current-1)*per)+1:0,end=Math.min(current*per,total);
 $('recordInfo').textContent=`${total} transaction${total===1?'':'s'}`;$('pageInfo').textContent=`Showing ${start}-${end} of ${total}`;
 let html=`<button class="op-page-button" data-page="${current-1}" ${current<=1?'disabled':''}>‹</button>`;for(let x=Math.max(1,current-2);x<=Math.min(last,current+2);x++)html+=`<button class="op-page-button ${x===current?'active':''}" data-page="${x}">${x}</button>`;html+=`<button class="op-page-button" data-page="${current+1}" ${current>=last?'disabled':''}>›</button>`;$('pagination').innerHTML=html;document.querySelectorAll('.op-page-button').forEach(b=>b.onclick=()=>{if(!b.disabled){page=Number(b.dataset.page);load()}});
}
async function load(){
 try{const r=await request('list',{search:$('search').value.trim(),academic_year_id:$('yearFilter').value,payment_method:$('methodFilter').value,payment_status:$('statusFilter').value,from_date:$('fromDate').value,to_date:$('toDate').value,page,per_page:10});meta=r.data.meta;permissions=r.data.permissions||permissions;render(r.data.records||[]);renderHistory(r.data.history||[]);renderPagination(r.data.pagination||{});updateStats(r.data.stats||{});
 if($('yearFilter').options.length<=1)fill('yearFilter',meta.years,'id','year_name','All Academic Years');
 }catch(e){message(e.message)}
}
function refreshStudents(){const year=Number($('academicYearId').value||0);fill('studentId',meta.students.filter(x=>!year||Number(x.academic_year_id)===year),'id','student_name','Select student');refreshAssignments()}
function refreshAssignments(){const year=Number($('academicYearId').value||0),student=Number($('studentId').value||0);fill('assignmentId',meta.assignments.filter(x=>(!year||Number(x.academic_year_id)===year)&&(!student||Number(x.student_id)===student)&&Number(x.balance_amount)>0),'id','assignment_label','Select fee assignment');updateSummary()}
function selectedAssignment(){return meta.assignments.find(x=>Number(x.id)===Number($('assignmentId').value))}
function updateSummary(){const a=selectedAssignment();$('feeSummary').innerHTML=`<div><small>Total Fee</small><strong>${money(a?.net_amount||0)}</strong></div><div><small>Paid</small><strong>${money(a?.paid_amount||0)}</strong></div><div><small>Outstanding</small><strong>${money(a?.balance_amount||0)}</strong></div><div><small>Fine Included</small><strong>${money(a?.fine_amount||0)}</strong></div>`;if($('paymentType').value==='full'&&a)$('paymentAmount').value=Number(a.balance_amount).toFixed(2)}
function openPayment(){fill('academicYearId',meta.years,'id','year_name');$('academicYearId').value=meta.years[0]?.id||'';refreshStudents();$('paymentMethod').value='upi';$('paymentType').value='partial';$('paymentAmount').value='';$('payerNotes').value='';bootstrap.Modal.getOrCreateInstance($('paymentModal')).show()}
async function viewPayment(id){try{const r=await request('detail',{id});const x=r.data.transaction;$('detailSubtitle').textContent=`${x.transaction_no} • ${x.student_name}`;$('detailContent').innerHTML=[['Transaction ID',x.transaction_no],['Student',x.student_name],['Admission No.',x.admission_no],['Fee Structure',x.structure_name],['Amount',money(x.amount)],['Method',x.payment_method],['Status',x.payment_status],['Receipt',x.receipt_no||'-']].map(([l,v])=>`<div><small>${esc(l)}</small><strong>${esc(v)}</strong></div>`).join('');$('gatewayInfo').innerHTML=`<strong>Gateway Order:</strong> ${esc(x.gateway_order_id||'-')}<br><strong>Gateway Payment:</strong> ${esc(x.gateway_payment_id||'-')}<br><strong>Reference:</strong> ${esc(x.gateway_reference||'-')}<br><strong>Failure:</strong> ${esc(x.failure_reason||'-')}`;bootstrap.Modal.getOrCreateInstance($('detailModal')).show()}catch(e){message(e.message)}}
async function verifyPayment(payload){try{const r=await request('verify',payload,'POST');message(r.message,true);bootstrap.Modal.getInstance($('paymentModal'))?.hide();await load()}catch(e){message(e.message)}}
async function refundPayment(id){const amount=prompt('Enter refund amount:');if(amount===null)return;const reason=prompt('Enter refund reason:')??'';try{const r=await request('refund',{id,amount:Number(amount),reason},'POST');message(r.message,true);await load()}catch(e){message(e.message)}}
$('paymentForm').onsubmit=async e=>{e.preventDefault();try{const a=selectedAssignment();if(!a)throw new Error('Select a fee assignment.');const r=await request('initiate',{assignment_id:Number($('assignmentId').value),payment_method:$('paymentMethod').value,amount:Number($('paymentAmount').value||0),payment_type:$('paymentType').value,payer_notes:$('payerNotes').value.trim()},'POST');const d=r.data;if(d.gateway!=='razorpay')throw new Error('Online payment gateway is not configured. Configure Razorpay credentials in fee settings.');if(typeof Razorpay==='undefined')throw new Error('Razorpay checkout could not be loaded.');const options={key:d.key_id,amount:d.amount_subunits,currency:d.currency,name:d.school_name||'School Fee',description:d.description,order_id:d.gateway_order_id,prefill:{name:d.student_name,email:d.email||'',contact:d.mobile||''},notes:{transaction_no:d.transaction_no},handler:resp=>verifyPayment({transaction_id:d.transaction_id,razorpay_order_id:resp.razorpay_order_id,razorpay_payment_id:resp.razorpay_payment_id,razorpay_signature:resp.razorpay_signature}),modal:{ondismiss:()=>request('cancel',{transaction_id:d.transaction_id},'POST').then(load).catch(()=>{})},theme:{color:'#4f46e5'}};new Razorpay(options).open()}catch(err){message(err.message)}};
document.querySelectorAll('.op-tab').forEach(b=>b.onclick=()=>{document.querySelectorAll('.op-tab').forEach(x=>x.classList.remove('active'));document.querySelectorAll('.op-panel').forEach(x=>x.classList.remove('active'));b.classList.add('active');document.querySelector(`[data-panel="${b.dataset.tab}"]`)?.classList.add('active')});
$('newPaymentBtn').onclick=openPayment;$('refreshBtn').onclick=load;$('academicYearId').onchange=refreshStudents;$('studentId').onchange=refreshAssignments;$('assignmentId').onchange=updateSummary;$('paymentType').onchange=updateSummary;
$('exportBtn').onclick=()=>{const p=new URLSearchParams({action:'export',search:$('search').value.trim(),academic_year_id:$('yearFilter').value,payment_method:$('methodFilter').value,payment_status:$('statusFilter').value,from_date:$('fromDate').value,to_date:$('toDate').value});window.location.href=apiUrl+'?'+p};
$('resetBtn').onclick=()=>{$('search').value='';['yearFilter','methodFilter','statusFilter'].forEach(id=>$(id).value='all');$('fromDate').value='';$('toDate').value='';page=1;load()};['yearFilter','methodFilter','statusFilter','fromDate','toDate'].forEach(id=>$(id).onchange=()=>{page=1;load()});let timer;$('search').oninput=()=>{clearTimeout(timer);timer=setTimeout(()=>{page=1;load()},300)};
document.querySelector('.fee-nav a[data-key="online_payment"]')?.classList.add('active');load();window.lucide?.createIcons();
})();
</script>

<?php require dirname(__DIR__).'/includes/layout-end.php'; ?>

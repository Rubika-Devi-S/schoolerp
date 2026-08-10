<?php
declare(strict_types=1);
$pageTitle='Student Fees';
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
.fee-page{display:grid;gap:16px}
.fee-page .page-title{font-size:28px;line-height:1.1}
.fee-page .page-subtitle{margin-top:4px}
.fee-nav{display:flex;gap:8px;overflow:auto;padding:10px}
.fee-nav a{flex:0 0 auto;display:inline-flex;align-items:center;gap:7px;padding:9px 12px;border:1px solid var(--border-soft,#e7ebf3);border-radius:9px;background:var(--card-bg,#fff);color:var(--text-main,#101a3b);font-size:11px;font-weight:800;text-decoration:none}
.fee-nav a.active{color:#fff;border-color:transparent;background:linear-gradient(135deg,#6547e8,#315ed8)}
.fee-nav svg{width:15px;height:15px}
.fee-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.fee-stat{border-radius:14px;min-height:110px;padding:18px 20px;display:flex;align-items:center;gap:14px;color:#fff;position:relative;overflow:hidden;box-shadow:0 12px 28px rgba(15,23,42,.08)}
.fee-stat::after{content:"";position:absolute;width:110px;height:110px;border-radius:50%;right:-38px;top:-40px;background:rgba(255,255,255,.08)}
.fee-stat.purple{background:linear-gradient(135deg,#7448e8,#4b36cf)}
.fee-stat.blue{background:linear-gradient(135deg,#4696ef,#2469d5)}
.fee-stat.orange{background:linear-gradient(135deg,#ffb327,#ff8a17)}
.fee-stat.green{background:linear-gradient(135deg,#45c783,#1ea568)}
.fee-stat.pink{background:linear-gradient(135deg,#ff527c,#ed2f63)}
.fee-stat-icon{width:50px;height:50px;border-radius:50%;background:rgba(255,255,255,.16);display:grid;place-items:center;flex:0 0 auto}
.fee-stat-icon svg{width:25px;height:25px}
.fee-stat strong{display:block;font-size:25px;line-height:1}
.fee-stat small{display:block;font-size:11px;font-weight:700;opacity:.95;margin-bottom:6px}
.fee-stat .trend{font-size:9px;font-weight:700;opacity:.94;margin-top:8px}
.fee-card{border-radius:14px;overflow:hidden}
.fee-card-head{padding:14px 16px;border-bottom:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;align-items:center;gap:10px}
.fee-card-head strong{font-size:14px}
.fee-card-actions{display:flex;gap:8px;flex-wrap:wrap}
.fee-filter{padding:14px 16px;display:grid;grid-template-columns:minmax(220px,1.4fr) repeat(5,minmax(130px,.7fr)) auto;gap:10px;border-bottom:1px solid var(--border-soft,#e7ebf3)}
.fee-table-wrap{overflow:auto}
.fee-table{min-width:1250px}
.fee-table th{font-size:10px}
.fee-table td{font-size:11px;vertical-align:middle}
.fee-empty{padding:40px 18px!important;text-align:center!important;color:var(--text-muted,#64748b)}
.fee-badge{display:inline-flex;align-items:center;padding:5px 9px;border-radius:999px;font-size:9px;font-weight:800;text-transform:capitalize}
.fee-badge.active,.fee-badge.paid,.fee-badge.success{color:#16834f;background:#e8f8ef}
.fee-badge.partial,.fee-badge.pending,.fee-badge.draft{color:#b96b00;background:#fff4df}
.fee-badge.unpaid,.fee-badge.overdue,.fee-badge.failed,.fee-badge.reversed,.fee-badge.inactive{color:#dc2626;background:#fff0f1}
.fee-action{width:30px;height:30px;border:1px solid #d7def1;border-radius:7px;background:var(--card-bg,#fff);display:grid;place-items:center;color:#4f46e5}
.fee-action svg{width:13px;height:13px}
.fee-actions{display:flex;gap:5px;flex-wrap:wrap}
.fee-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.fee-form-grid .full{grid-column:1/-1}
.fee-detail-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
.fee-detail-item{padding:12px;border:1px solid var(--border-soft,#e7ebf3);border-radius:10px}
.fee-detail-item small{display:block;color:var(--text-muted,#64748b);font-size:9px;font-weight:700}
.fee-detail-item strong{display:block;margin-top:4px;font-size:14px}
.fee-tabs{display:flex;gap:8px;overflow:auto;padding:10px 0 14px}
.fee-tab{border:1px solid var(--border-soft,#e7ebf3);background:var(--card-bg,#fff);border-radius:8px;padding:8px 11px;font-size:10px;font-weight:800}
.fee-tab.active{color:#fff;border-color:transparent;background:linear-gradient(135deg,#6547e8,#315ed8)}
.fee-panel{display:none}.fee-panel.active{display:block}
.fee-message{display:none}.fee-message.show{display:block}
#feeModal .modal-dialog,#detailModal .modal-dialog{max-height:calc(100dvh - 32px);margin:16px auto}
#feeModal .modal-content,#detailModal .modal-content{max-height:calc(100dvh - 32px);overflow:hidden}
#feeModal form,#detailModal .modal-content{display:flex;flex-direction:column}
#feeModal .modal-body,#detailModal .modal-body{overflow-y:auto;min-height:0}
@media(max-width:1200px){.fee-filter{grid-template-columns:repeat(3,1fr)}}
@media(max-width:900px){.fee-stats,.fee-detail-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:575px){.fee-stats,.fee-filter,.fee-form-grid,.fee-detail-grid{grid-template-columns:1fr}.fee-form-grid .full{grid-column:auto}.fee-card-head{align-items:flex-start;flex-direction:column}}
</style>

<div class="fee-page" data-page="student_fees">
<div class="page-heading">
    <div>
        <h1 class="page-title">Student Fees</h1>
        <p class="page-subtitle">Assign fee structures and manage student fee details, ledger, dues, installments and payment history.</p>
    </div>
    <div class="page-actions">
        <button id="assignBtn" class="btn-ui btn-primary-ui" type="button"><i data-lucide="plus"></i> Assign Fee</button>
        <button id="refresh" class="btn-ui" type="button"><i data-lucide="refresh-cw"></i> Refresh</button>
    </div>
</div>

<section class="ui-card fee-nav">
<a href="fee-dashboard.php" data-key="dashboard"><i data-lucide="layout-dashboard"></i> Dashboard</a>
<a href="fee-setup.php" data-key="setup"><i data-lucide="settings-2"></i> Fee Setup</a>
<a href="fee-collection.php" data-key="collection"><i data-lucide="indian-rupee"></i> Fee Collection</a>
<a href="student-fees.php" data-key="student_fees"><i data-lucide="users"></i> Student Fees</a>
<a href="due-fees.php" data-key="due_fees"><i data-lucide="circle-alert"></i> Due Fees</a>
<a href="fee-transactions.php" data-key="transactions"><i data-lucide="receipt-text"></i> Transactions</a>
<a href="fee-reports.php" data-key="reports"><i data-lucide="chart-column"></i> Reports</a>
<a href="fee-settings.php" data-key="settings"><i data-lucide="sliders-horizontal"></i> Settings</a>
</section>

<div id="feeMessage" class="alert fee-message"></div>

<section class="fee-stats">
<article class="fee-stat purple"><span class="fee-stat-icon"><i data-lucide="users"></i></span><div><small>Assigned Students</small><strong id="statStudents">0</strong><div class="trend">Filtered student count</div></div></article>
<article class="fee-stat green"><span class="fee-stat-icon"><i data-lucide="wallet-cards"></i></span><div><small>Total Fee</small><strong id="statTotal">₹0</strong><div class="trend">Net assigned amount</div></div></article>
<article class="fee-stat blue"><span class="fee-stat-icon"><i data-lucide="badge-indian-rupee"></i></span><div><small>Total Paid</small><strong id="statPaid">₹0</strong><div class="trend">Collected amount</div></div></article>
<article class="fee-stat orange"><span class="fee-stat-icon"><i data-lucide="clock-3"></i></span><div><small>Total Balance</small><strong id="statBalance">₹0</strong><div class="trend">Pending amount</div></div></article>
</section>

<section class="ui-card fee-card">
<div class="fee-card-head"><strong>Student Fee Assignments</strong><div class="fee-card-actions"><small id="recordInfo" class="text-muted">Loading...</small></div></div>
<div class="fee-filter">
<input id="search" class="form-control" placeholder="Admission no., student ID or name...">
<select id="yearFilter" class="form-select"><option value="all">All Academic Years</option></select>
<select id="classFilter" class="form-select"><option value="all">All Classes</option></select>
<select id="sectionFilter" class="form-select"><option value="all">All Sections</option></select>
<select id="statusFilter" class="form-select"><option value="all">All Statuses</option><option value="unpaid">Unpaid</option><option value="partial">Partial</option><option value="paid">Paid</option><option value="overdue">Overdue</option></select>
<select id="dueFilter" class="form-select"><option value="all">All Due Types</option><option value="pending">Pending Fees</option><option value="overdue">Overdue Fees</option></select>
<button id="resetBtn" class="btn-ui" type="button"><i data-lucide="rotate-ccw"></i> Reset</button>
</div>
<div class="fee-table-wrap">
<table class="data-table fee-table">
<thead><tr><th>Student ID</th><th>Student</th><th>Admission No</th><th>Class</th><th>Section</th><th>Academic Year</th><th>Structure</th><th>Total</th><th>Paid</th><th>Balance</th><th>Due</th><th>Status</th><th>Actions</th></tr></thead>
<tbody id="body"><tr><td colspan="13" class="fee-empty">Loading...</td></tr></tbody>
</table>
</div>
</section>

<div class="modal fade" id="feeModal" tabindex="-1">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
<form id="assignForm">
<div class="modal-header"><h5 class="modal-title">Assign Student Fee</h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div>
<div class="modal-body fee-form-grid">
<div><label class="form-label">Academic Year *</label><select id="yearId" class="form-select" required></select></div>
<div><label class="form-label">Student *</label><select id="studentId" class="form-select" required></select></div>
<div><label class="form-label">Fee Structure *</label><select id="structureId" class="form-select" required></select></div>
<div><label class="form-label">Concession</label><input id="concession" class="form-control" type="number" min="0" step="0.01" value="0"></div>
<div><label class="form-label">Scholarship</label><input id="scholarship" class="form-control" type="number" min="0" step="0.01" value="0"></div>
<div class="full"><div id="assignmentPreview" class="alert alert-light mb-0">Select a fee structure to view the amount.</div></div>
</div>
<div class="modal-footer"><button class="btn-ui" type="button" data-bs-dismiss="modal">Cancel</button><button id="saveAssignBtn" class="btn-ui btn-primary-ui" type="submit">Assign</button></div>
</form>
</div>
</div>
</div>

<div class="modal fade" id="detailModal" tabindex="-1">
<div class="modal-dialog modal-xl modal-dialog-centered">
<div class="modal-content">
<div class="modal-header"><div><h5 class="modal-title">Student Fee Details</h5><small id="detailSubtitle" class="text-muted"></small></div><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
<div id="detailSummary" class="fee-detail-grid"></div>
<div class="fee-tabs">
<button class="fee-tab active" data-tab="details" type="button">Fee Details</button>
<button class="fee-tab" data-tab="installments" type="button">Installments</button>
<button class="fee-tab" data-tab="ledger" type="button">Fee Ledger</button>
<button class="fee-tab" data-tab="payments" type="button">Payment History</button>
<button class="fee-tab" data-tab="adjustments" type="button">Discounts / Scholarship / Fine</button>
</div>
<div class="fee-panel active" data-panel="details"><div class="fee-table-wrap"><table class="data-table fee-table"><thead><tr><th>Fee Head</th><th>Amount</th><th>Due Date</th><th>Fine Type</th><th>Fine Value</th></tr></thead><tbody id="detailItems"></tbody></table></div></div>
<div class="fee-panel" data-panel="installments"><div class="fee-table-wrap"><table class="data-table fee-table"><thead><tr><th>Installment / Due Date</th><th>Heads</th><th>Amount</th><th>Fine</th><th>Status</th></tr></thead><tbody id="installmentBody"></tbody></table></div></div>
<div class="fee-panel" data-panel="ledger"><div class="fee-table-wrap"><table class="data-table fee-table"><thead><tr><th>Date</th><th>Type</th><th>Reference</th><th>Debit</th><th>Credit</th><th>Balance</th><th>Status</th></tr></thead><tbody id="ledgerBody"></tbody></table></div></div>
<div class="fee-panel" data-panel="payments"><div class="fee-table-wrap"><table class="data-table fee-table"><thead><tr><th>Receipt No</th><th>Date</th><th>Amount</th><th>Discount</th><th>Fine</th><th>Method</th><th>Status</th><th>Print</th></tr></thead><tbody id="paymentBody"></tbody></table></div></div>
<div class="fee-panel" data-panel="adjustments"><div id="adjustmentSummary" class="fee-detail-grid"></div></div>
</div>
<div class="modal-footer"><a id="collectLink" class="btn-ui btn-primary-ui"><i data-lucide="indian-rupee"></i> Collect Fee</a><button class="btn-ui" type="button" data-bs-dismiss="modal">Close</button></div>
</div>
</div>
</div>

<script>
(function(){
'use strict';
const apiUrl=new URL('../api/student-fees.php',window.location.href).href;
let csrfToken=<?=json_encode($feeCsrf)?>;
let meta={years:[],students:[],structures:[],classes:[],sections:[]};
let records=[];
const $=id=>document.getElementById(id);
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
const money=v=>new Intl.NumberFormat('en-IN',{style:'currency',currency:'INR',maximumFractionDigits:2}).format(Number(v||0));
const badge=v=>`<span class="fee-badge ${esc(String(v||'').toLowerCase())}">${esc(v||'-')}</span>`;
const assignModal=()=>bootstrap.Modal.getOrCreateInstance($('feeModal'));
const detailModal=()=>bootstrap.Modal.getOrCreateInstance($('detailModal'));

async function request(action,data={},method='GET'){
 let response;
 if(method==='GET'){
  const url=new URL(apiUrl);
  url.searchParams.set('action',action);
  Object.entries(data).forEach(([k,v])=>{if(v!==''&&v!==null&&v!==undefined)url.searchParams.set(k,String(v))});
  response=await fetch(url,{headers:{Accept:'application/json'},credentials:'same-origin'});
 }else{
  response=await fetch(apiUrl,{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json'},credentials:'same-origin',body:JSON.stringify({action,csrf_token:csrfToken,...data})});
 }
 const text=await response.text();
 let result;
 try{result=JSON.parse(text)}catch{throw new Error(`Student Fees API returned HTTP ${response.status}. Invalid server response.`)}
 if(!response.ok||!result.success)throw new Error(result.message||'Request failed.');
 if(result.data?.csrf_token)csrfToken=result.data.csrf_token;
 return result;
}

function message(text,ok=false){
 const box=$('feeMessage');
 box.className='alert fee-message show '+(ok?'alert-success':'alert-danger');
 box.textContent=text;
}

function fill(id,rows,key,label,first=''){
 const element=$(id);
 element.innerHTML=(first?`<option value="all">${esc(first)}</option>`:'')+rows.map(row=>`<option value="${esc(row[key])}">${esc(row[label])}</option>`).join('');
}

function filters(){
 return{
  search:$('search').value.trim(),
  academic_year_id:$('yearFilter').value,
  class_id:$('classFilter').value,
  section_id:$('sectionFilter').value,
  status:$('statusFilter').value,
  due_type:$('dueFilter').value
 };
}

function updateStats(stats={}){
 $('statStudents').textContent=Number(stats.students||0).toLocaleString('en-IN');
 $('statTotal').textContent=money(stats.total||0);
 $('statPaid').textContent=money(stats.paid||0);
 $('statBalance').textContent=money(stats.balance||0);
}

function render(rows){
 records=rows;
 $('body').innerHTML=rows.map(row=>`<tr>
 <td>#${esc(row.student_id)}</td>
 <td><strong>${esc(row.student_name||'-')}</strong><small class="d-block text-muted">${esc(row.mobile||'')}</small></td>
 <td>${esc(row.admission_no||'-')}</td>
 <td>${esc(row.class_name||'-')}</td>
 <td>${esc(row.section_name||'-')}</td>
 <td>${esc(row.year_name||'-')}</td>
 <td>${esc(row.structure_name||'-')}</td>
 <td>${money(row.net_amount)}</td>
 <td>${money(row.paid_amount)}</td>
 <td><strong>${money(row.balance_amount)}</strong></td>
 <td>${esc(row.due_date||'-')}</td>
 <td>${badge(row.display_status||row.payment_status)}</td>
 <td><div class="fee-actions">
  <button class="fee-action js-view" data-id="${row.assignment_id}" type="button" title="View Details"><i data-lucide="eye"></i></button>
  <a class="fee-action" href="fee-collection.php?student_id=${encodeURIComponent(row.student_id)}&assignment_id=${encodeURIComponent(row.assignment_id)}" title="Collect Fee"><i data-lucide="indian-rupee"></i></a>
 </div></td>
 </tr>`).join('')||'<tr><td colspan="13" class="fee-empty">No student fee records found.</td></tr>';
 $('recordInfo').textContent=`Showing ${rows.length} record${rows.length===1?'':'s'}`;
 document.querySelectorAll('.js-view').forEach(button=>button.onclick=()=>openDetails(Number(button.dataset.id)));
 window.lucide?.createIcons();
}

async function loadMeta(){
 const response=await request('meta');
 meta=response.data;
 fill('yearFilter',meta.years||[],'id','year_name','All Academic Years');
 fill('classFilter',meta.classes||[],'id','class_name','All Classes');
 fill('sectionFilter',meta.sections||[],'id','section_name','All Sections');
 fill('yearId',meta.years||[],'id','year_name');
 fill('studentId',meta.students||[],'id','student_name');
 fill('structureId',meta.structures||[],'id','structure_name');
}

async function load(){
 try{
  const response=await request('list',filters());
  render(response.data.records||[]);
  updateStats(response.data.stats||{});
 }catch(error){message(error.message)}
}

function updateAssignmentPreview(){
 const structure=meta.structures.find(item=>Number(item.id)===Number($('structureId').value));
 const concession=Math.max(0,Number($('concession').value||0));
 const scholarship=Math.max(0,Number($('scholarship').value||0));
 const gross=Number(structure?.gross_amount||0);
 const net=Math.max(0,gross-concession-scholarship);
 $('assignmentPreview').innerHTML=`Gross: <strong>${money(gross)}</strong> &nbsp; Net payable: <strong>${money(net)}</strong>`;
}

async function openDetails(id){
 try{
  const response=await request('detail',{assignment_id:id});
  const data=response.data;
  const a=data.assignment;
  $('detailSubtitle').textContent=`${a.student_name} • ${a.admission_no} • ${a.class_name||'-'} ${a.section_name||''}`;
  $('detailSummary').innerHTML=[
   ['Total',money(a.net_amount)],['Paid',money(a.paid_amount)],['Balance',money(a.balance_amount)],['Due Amount',money(data.due_amount)],
   ['Concession',money(a.concession_amount)],['Scholarship',money(a.scholarship_amount)],['Fine',money(data.fine_total)],['Status',a.display_status||a.payment_status]
  ].map(([label,value])=>`<div class="fee-detail-item"><small>${esc(label)}</small><strong>${esc(value)}</strong></div>`).join('');

  $('detailItems').innerHTML=(data.items||[]).map(item=>`<tr><td>${esc(item.head_name)}</td><td>${money(item.amount)}</td><td>${esc(item.due_date||'-')}</td><td>${esc(item.fine_type)}</td><td>${money(item.fine_value)}</td></tr>`).join('')||'<tr><td colspan="5" class="fee-empty">No fee items.</td></tr>';

  $('installmentBody').innerHTML=(data.installments||[]).map(item=>`<tr><td>${esc(item.due_date||'No Due Date')}</td><td>${esc(item.heads||'-')}</td><td>${money(item.amount)}</td><td>${money(item.fine_amount)}</td><td>${badge(item.status)}</td></tr>`).join('')||'<tr><td colspan="5" class="fee-empty">No installment schedule.</td></tr>';

  $('ledgerBody').innerHTML=(data.ledger||[]).map(item=>`<tr><td>${esc(item.entry_date)}</td><td>${esc(item.entry_type)}</td><td>${esc(item.reference_no||'-')}</td><td>${money(item.debit)}</td><td>${money(item.credit)}</td><td>${money(item.balance)}</td><td>${badge(item.status)}</td></tr>`).join('')||'<tr><td colspan="7" class="fee-empty">No ledger entries.</td></tr>';

  $('paymentBody').innerHTML=(data.payments||[]).map(item=>`<tr><td>${esc(item.receipt_no)}</td><td>${esc(item.receipt_date)}</td><td>${money(item.paid_amount)}</td><td>${money(item.discount_amount)}</td><td>${money(item.fine_amount)}</td><td>${esc(item.payment_methods||'-')}</td><td>${badge(item.payment_status)}</td><td><a class="fee-action" target="_blank" href="${apiUrl}?action=print_receipt&id=${encodeURIComponent(item.id)}"><i data-lucide="printer"></i></a></td></tr>`).join('')||'<tr><td colspan="8" class="fee-empty">No payment history.</td></tr>';

  $('adjustmentSummary').innerHTML=[
   ['Gross Fee',money(a.gross_amount)],['Discount / Concession',money(a.concession_amount)],['Scholarship',money(a.scholarship_amount)],['Fine Accrued',money(data.fine_total)]
  ].map(([label,value])=>`<div class="fee-detail-item"><small>${esc(label)}</small><strong>${esc(value)}</strong></div>`).join('');

  $('collectLink').href=`fee-collection.php?student_id=${encodeURIComponent(a.student_id)}&assignment_id=${encodeURIComponent(a.assignment_id)}`;
  detailModal().show();
  window.lucide?.createIcons();
 }catch(error){message(error.message)}
}

document.querySelectorAll('.fee-tab').forEach(button=>{
 button.onclick=()=>{
  document.querySelectorAll('.fee-tab').forEach(tab=>tab.classList.remove('active'));
  document.querySelectorAll('.fee-panel').forEach(panel=>panel.classList.remove('active'));
  button.classList.add('active');
  document.querySelector(`[data-panel="${button.dataset.tab}"]`)?.classList.add('active');
 };
});

$('assignBtn').onclick=()=>{updateAssignmentPreview();assignModal().show()};
$('assignForm').onsubmit=async event=>{
 event.preventDefault();
 try{
  const response=await request('assign',{
   academic_year_id:Number($('yearId').value),
   student_id:Number($('studentId').value),
   fee_structure_id:Number($('structureId').value),
   concession_amount:Number($('concession').value||0),
   scholarship_amount:Number($('scholarship').value||0)
  },'POST');
  assignModal().hide();
  message(response.message,true);
  await load();
 }catch(error){message(error.message)}
};

['structureId','concession','scholarship'].forEach(id=>$(id).addEventListener('input',updateAssignmentPreview));
['yearFilter','classFilter','sectionFilter','statusFilter','dueFilter'].forEach(id=>$(id).addEventListener('change',load));
let searchTimer;
$('search').addEventListener('input',()=>{clearTimeout(searchTimer);searchTimer=setTimeout(load,300)});
$('refresh').onclick=load;
$('resetBtn').onclick=()=>{
 $('search').value='';
 ['yearFilter','classFilter','sectionFilter','statusFilter','dueFilter'].forEach(id=>$(id).value='all');
 load();
};

document.querySelector('.fee-nav a[data-key="student_fees"]')?.classList.add('active');

(async()=>{
 try{
  await loadMeta();
  await load();
  updateAssignmentPreview();
  window.lucide?.createIcons();
 }catch(error){message(error.message)}
})();
})();
</script>

<?php require dirname(__DIR__).'/includes/layout-end.php'; ?>

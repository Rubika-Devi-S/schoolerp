<?php
declare(strict_types=1);

$pageTitle='Scholarship Management';
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
.sm-page{display:grid;gap:16px}
.sm-page .page-title{font-size:28px;line-height:1.1}
.sm-page .page-subtitle{margin-top:4px}
.sm-message{display:none}.sm-message.show{display:block}
.sm-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.sm-stat{border-radius:14px;min-height:110px;padding:18px 20px;display:flex;align-items:center;gap:14px;color:#fff;position:relative;overflow:hidden;box-shadow:0 12px 28px rgba(15,23,42,.08)}
.sm-stat::after{content:"";position:absolute;width:110px;height:110px;border-radius:50%;right:-38px;top:-40px;background:rgba(255,255,255,.08)}
.sm-stat.purple{background:linear-gradient(135deg,#7448e8,#4b36cf)}
.sm-stat.green{background:linear-gradient(135deg,#45c783,#1ea568)}
.sm-stat.orange{background:linear-gradient(135deg,#ffb327,#ff8a17)}
.sm-stat.blue{background:linear-gradient(135deg,#4696ef,#2469d5)}
.sm-stat-icon{width:50px;height:50px;border-radius:50%;background:rgba(255,255,255,.16);display:grid;place-items:center;flex:0 0 auto}
.sm-stat-icon svg{width:25px;height:25px}
.sm-stat strong{display:block;font-size:25px;line-height:1}
.sm-stat small{display:block;font-size:11px;font-weight:700;opacity:.95;margin-bottom:6px}
.sm-stat .trend{font-size:9px;font-weight:700;opacity:.94;margin-top:8px}
.fee-nav{display:flex;gap:8px;overflow:auto;padding:10px}
.fee-nav a{flex:0 0 auto;display:inline-flex;align-items:center;gap:7px;padding:9px 12px;border:1px solid var(--border-soft,#e7ebf3);border-radius:9px;background:var(--card-bg,#fff);color:var(--text-main,#101a3b);font-size:11px;font-weight:800;text-decoration:none}
.fee-nav a.active{color:#fff;border-color:transparent;background:linear-gradient(135deg,#6547e8,#315ed8)}
.fee-nav svg{width:15px;height:15px}
.sm-tabs{display:flex;gap:8px;overflow:auto;padding:10px}
.sm-tab{border:1px solid var(--border-soft,#e7ebf3);background:var(--card-bg,#fff);color:var(--text-main,#101b46);border-radius:9px;padding:9px 12px;font-size:11px;font-weight:800;white-space:nowrap}
.sm-tab.active{color:#fff;border-color:transparent;background:linear-gradient(135deg,#6747e8,#2f62d7)}
.sm-panel{display:none}.sm-panel.active{display:block}
.sm-card{border-radius:14px;overflow:hidden}
.sm-card-head{padding:14px 16px;border-bottom:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;align-items:center;gap:10px}
.sm-card-actions{display:flex;gap:8px;flex-wrap:wrap}
.sm-filter{padding:14px 16px;display:grid;grid-template-columns:minmax(220px,1.4fr) repeat(5,minmax(135px,.7fr)) auto;gap:10px;border-bottom:1px solid var(--border-soft,#e7ebf3)}
.sm-table-wrap{overflow:auto}
.sm-table{min-width:1200px}
.sm-table th{font-size:10px}
.sm-table td{font-size:11px;vertical-align:middle}
.sm-empty{padding:40px 18px!important;text-align:center!important;color:var(--text-muted,#64748b)}
.sm-badge{display:inline-flex;align-items:center;padding:5px 9px;border-radius:999px;font-size:9px;font-weight:800;text-transform:capitalize}
.sm-badge.active,.sm-badge.approved{color:#16834f;background:#e8f8ef}
.sm-badge.inactive,.sm-badge.rejected{color:#dc2626;background:#fff0f1}
.sm-badge.pending{color:#9a6700;background:#fff7d6}
.sm-actions{display:flex;gap:5px;flex-wrap:wrap}
.sm-action{width:30px;height:30px;display:grid;place-items:center;border:1px solid #d7def1;border-radius:7px;background:var(--card-bg,#fff);color:#4f46e5}
.sm-action.danger{color:#dc2626}.sm-action.success{color:#16834f}
.sm-action svg{width:13px;height:13px}
.sm-pagination{padding:14px 16px;border-top:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;align-items:center;gap:10px}
.sm-page-buttons{display:flex;gap:6px;flex-wrap:wrap}
.sm-page-button{min-width:34px;height:34px;border:1px solid var(--border-soft,#e7ebf3);background:#fff;border-radius:8px;font-size:11px;font-weight:800}
.sm-page-button.active{color:#fff;border-color:transparent;background:linear-gradient(135deg,#6547e8,#315ed8)}
.sm-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.sm-form-grid .full{grid-column:1/-1}
#scholarshipModal .modal-dialog,#typeModal .modal-dialog{max-height:calc(100dvh - 32px);margin:16px auto}
#scholarshipModal .modal-content,#typeModal .modal-content{max-height:calc(100dvh - 32px);overflow:hidden}
#scholarshipModal form,#typeModal form{display:flex;flex-direction:column;max-height:calc(100dvh - 32px)}
#scholarshipModal .modal-body,#typeModal .modal-body{overflow-y:auto;min-height:0}
@media(max-width:1200px){.sm-filter{grid-template-columns:repeat(3,1fr)}}
@media(max-width:900px){.sm-stats{grid-template-columns:repeat(2,1fr)}}
@media(max-width:575px){.sm-stats,.sm-filter,.sm-form-grid{grid-template-columns:1fr}.sm-form-grid .full{grid-column:auto}.sm-card-head,.sm-pagination{align-items:flex-start;flex-direction:column}}
</style>

<div class="sm-page" data-page="scholarships">
<div class="page-heading">
 <div><h1 class="page-title">Scholarship Management</h1><p class="page-subtitle">Configure, approve and monitor student scholarship benefits.</p></div>
 <div class="page-actions">
  <button id="addScholarshipBtn" class="btn-ui btn-primary-ui" type="button"><i data-lucide="graduation-cap"></i> Add Scholarship</button>
  <button id="addTypeBtn" class="btn-ui" type="button"><i data-lucide="plus"></i> Scholarship Type</button>
  <button id="refreshBtn" class="btn-ui" type="button"><i data-lucide="refresh-cw"></i> Refresh</button>
 </div>
</div>

<div id="smMessage" class="alert sm-message"></div>

<section class="sm-stats">
 <article class="sm-stat purple"><span class="sm-stat-icon"><i data-lucide="graduation-cap"></i></span><div><small>Total Scholarships</small><strong id="statTotal">0</strong><div class="trend">All scholarship records</div></div></article>
 <article class="sm-stat green"><span class="sm-stat-icon"><i data-lucide="circle-check-big"></i></span><div><small>Approved Scholarships</small><strong id="statApproved">0</strong><div class="trend">Applied to fee assignments</div></div></article>
 <article class="sm-stat orange"><span class="sm-stat-icon"><i data-lucide="clock-3"></i></span><div><small>Pending Approval</small><strong id="statPending">0</strong><div class="trend">Awaiting review</div></div></article>
 <article class="sm-stat blue"><span class="sm-stat-icon"><i data-lucide="indian-rupee"></i></span><div><small>Total Scholarship Benefit</small><strong id="statBenefit">₹0</strong><div class="trend">Approved scholarship value</div></div></article>
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
<a href="fee-reports.php" data-key="reports"><i data-lucide="chart-column"></i> Reports</a>
</section>

<section class="ui-card sm-tabs">
 <button class="sm-tab active" data-tab="scholarships" type="button">Scholarship Dashboard</button>
 <button class="sm-tab" data-tab="types" type="button">Scholarship Types</button>
 <button class="sm-tab" data-tab="history" type="button">Scholarship History</button>
</section>

<section class="sm-panel active" data-panel="scholarships">
<section class="ui-card sm-card">
<div class="sm-card-head"><strong>Scholarship List</strong><div class="sm-card-actions"><small id="recordInfo" class="text-muted">Loading...</small></div></div>
<div class="sm-filter">
 <input id="search" class="form-control" placeholder="Student, admission no. or scholarship...">
 <select id="yearFilter" class="form-select"><option value="all">All Academic Years</option></select>
 <select id="scopeFilter" class="form-select"><option value="all">All Scopes</option><option value="student">Student-wise</option><option value="class">Class-wise</option></select>
 <select id="typeFilter" class="form-select"><option value="all">All Scholarship Types</option></select>
 <select id="approvalFilter" class="form-select"><option value="all">All Approval Status</option><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option></select>
 <select id="statusFilter" class="form-select"><option value="all">All Statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select>
 <button id="resetBtn" class="btn-ui" type="button"><i data-lucide="rotate-ccw"></i> Reset</button>
</div>
<div class="sm-table-wrap"><table class="data-table sm-table"><thead><tr><th>#</th><th>Scholarship</th><th>Scope</th><th>Student / Class</th><th>Academic Year</th><th>Fee Structure</th><th>Calculation</th><th>Benefit</th><th>Approval</th><th>Status</th><th>Actions</th></tr></thead><tbody id="scholarshipBody"><tr><td colspan="11" class="sm-empty">Loading...</td></tr></tbody></table></div>
<div class="sm-pagination"><small id="pageInfo" class="text-muted"></small><div id="pagination" class="sm-page-buttons"></div></div>
</section>
</section>

<section class="sm-panel" data-panel="types">
<section class="ui-card sm-card">
<div class="sm-card-head"><strong>Scholarship Types</strong><div class="sm-card-actions"><button id="typeAddInside" class="btn-ui btn-primary-ui" type="button"><i data-lucide="plus"></i> Add Type</button></div></div>
<div class="sm-table-wrap"><table class="data-table sm-table"><thead><tr><th>Code</th><th>Name</th><th>Default Scope</th><th>Calculation</th><th>Default Value</th><th>Approval Required</th><th>Status</th><th>Actions</th></tr></thead><tbody id="typeBody"></tbody></table></div>
</section>
</section>

<section class="sm-panel" data-panel="history">
<section class="ui-card sm-card">
<div class="sm-card-head"><strong>Scholarship History</strong></div>
<div class="sm-table-wrap"><table class="data-table sm-table"><thead><tr><th>Date</th><th>Action</th><th>Scholarship</th><th>Student / Class</th><th>Amount</th><th>User</th><th>Remarks</th></tr></thead><tbody id="historyBody"></tbody></table></div>
</section>
</section>
</div>

<div class="modal fade" id="scholarshipModal" tabindex="-1">
<div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content"><form id="scholarshipForm">
<div class="modal-header"><h5 id="scholarshipModalTitle" class="modal-title">Add Scholarship</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body sm-form-grid">
<input id="scholarshipId" type="hidden">
<div><label class="form-label">Scholarship Type *</label><select id="scholarshipTypeId" class="form-select" required></select></div>
<div><label class="form-label">Assignment Scope *</label><select id="scopeType" class="form-select"><option value="student">Student-wise</option><option value="class">Class-wise</option></select></div>
<div><label class="form-label">Academic Year *</label><select id="academicYearId" class="form-select" required></select></div>
<div><label class="form-label">Class</label><select id="classId" class="form-select"></select></div>
<div><label class="form-label">Student</label><select id="studentId" class="form-select"></select></div>
<div><label class="form-label">Fee Structure</label><select id="feeStructureId" class="form-select"></select></div>
<div><label class="form-label">Scholarship Amount Type *</label><select id="calculationType" class="form-select"><option value="percentage">Percentage</option><option value="fixed">Fixed Amount</option></select></div>
<div><label class="form-label">Scholarship Value *</label><input id="scholarshipValue" class="form-control" type="number" min="0.01" step="0.01" required></div>
<div><label class="form-label">Valid From *</label><input id="validFrom" class="form-control" type="date" required></div>
<div><label class="form-label">Valid To</label><input id="validTo" class="form-control" type="date"></div>
<div><label class="form-label">Status</label><select id="scholarshipStatus" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
<div class="full"><label class="form-label">Reason / Remarks *</label><textarea id="remarks" class="form-control" rows="3" maxlength="500" required></textarea></div>
</div>
<div class="modal-footer"><button type="button" class="btn-ui" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn-ui btn-primary-ui">Save Scholarship</button></div>
</form></div></div>
</div>

<div class="modal fade" id="typeModal" tabindex="-1">
<div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><form id="typeForm">
<div class="modal-header"><h5 id="typeModalTitle" class="modal-title">Add Scholarship Type</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body sm-form-grid">
<input id="typeId" type="hidden">
<div><label class="form-label">Type Code *</label><input id="typeCode" class="form-control" maxlength="40" required></div>
<div><label class="form-label">Type Name *</label><input id="typeName" class="form-control" maxlength="120" required></div>
<div><label class="form-label">Default Scope</label><select id="typeScope" class="form-select"><option value="student">Student-wise</option><option value="class">Class-wise</option></select></div>
<div><label class="form-label">Calculation</label><select id="typeCalculation" class="form-select"><option value="percentage">Percentage</option><option value="fixed">Fixed Amount</option></select></div>
<div><label class="form-label">Default Value *</label><input id="typeValue" class="form-control" type="number" min="0.01" step="0.01" required></div>
<div><label class="form-label">Approval Required</label><select id="typeApproval" class="form-select"><option value="1">Yes</option><option value="0">No</option></select></div>
<div><label class="form-label">Status</label><select id="typeStatus" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
<div class="full"><label class="form-label">Description</label><textarea id="typeDescription" class="form-control" rows="3" maxlength="500"></textarea></div>
</div>
<div class="modal-footer"><button type="button" class="btn-ui" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn-ui btn-primary-ui">Save Type</button></div>
</form></div></div>
</div>

<script>
(function(){
'use strict';
const apiUrl=new URL('../api/scholarship-management.php',window.location.href).href;
let csrfToken=<?=json_encode($feeCsrf)?>;
let meta={years:[],classes:[],students:[],structures:[],types:[]};
let rows=[],types=[],page=1,permissions={approve:false,delete:false};
const $=id=>document.getElementById(id);
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
const money=v=>new Intl.NumberFormat('en-IN',{style:'currency',currency:'INR',maximumFractionDigits:2}).format(Number(v||0));
const badge=v=>`<span class="sm-badge ${esc(String(v||'').toLowerCase())}">${esc(v||'-')}</span>`;

async function request(action,data={},method='GET'){
 let response;
 if(method==='GET'){const url=new URL(apiUrl);url.searchParams.set('action',action);Object.entries(data).forEach(([k,v])=>{if(v!==''&&v!==null&&v!==undefined)url.searchParams.set(k,String(v))});response=await fetch(url,{headers:{Accept:'application/json'},credentials:'same-origin'});}
 else response=await fetch(apiUrl,{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json'},credentials:'same-origin',body:JSON.stringify({action,csrf_token:csrfToken,...data})});
 const text=await response.text();let result;try{result=JSON.parse(text)}catch{throw new Error(`Scholarship API returned HTTP ${response.status}. ${text.replace(/\s+/g,' ').trim().slice(0,220)||'Invalid server response.'}`)}
 if(!response.ok||!result.success)throw new Error(result.message||'Request failed.');if(result.data?.csrf_token)csrfToken=result.data.csrf_token;return result;
}
function message(text,ok=false){const b=$('smMessage');b.className='alert sm-message show '+(ok?'alert-success':'alert-danger');b.textContent=text}
function fill(id,items,key,label,first=''){const e=$(id);e.innerHTML=(first?`<option value="">${esc(first)}</option>`:'')+items.map(x=>`<option value="${esc(x[key])}">${esc(x[label])}</option>`).join('')}
function updateStats(s={}){$('statTotal').textContent=Number(s.total||0).toLocaleString('en-IN');$('statApproved').textContent=Number(s.approved||0).toLocaleString('en-IN');$('statPending').textContent=Number(s.pending||0).toLocaleString('en-IN');$('statBenefit').textContent=money(s.benefit||0)}
function renderScholarships(data){
 rows=data;
 $('scholarshipBody').innerHTML=data.map(r=>`<tr><td>${r.row_number}</td><td><strong>${esc(r.type_name)}</strong><small class="d-block text-muted">${esc(r.type_code)}</small></td><td>${esc(r.scope_label)}</td><td>${esc(r.target_name||'-')}</td><td>${esc(r.year_name)}</td><td>${esc(r.structure_name||'All Structures')}</td><td>${esc(r.calculation_type==='percentage'?r.scholarship_value+'%':money(r.scholarship_value))}</td><td><strong>${money(r.applied_amount)}</strong></td><td>${badge(r.approval_status)}</td><td>${badge(r.status)}</td><td><div class="sm-actions"><button class="sm-action js-edit" data-id="${r.id}" title="Edit"><i data-lucide="pencil"></i></button>${permissions.approve&&r.approval_status==='pending'?`<button class="sm-action success js-approve" data-id="${r.id}" title="Approve"><i data-lucide="check"></i></button><button class="sm-action danger js-reject" data-id="${r.id}" title="Reject"><i data-lucide="x"></i></button>`:''}${permissions.delete?`<button class="sm-action danger js-delete" data-id="${r.id}" title="Delete"><i data-lucide="trash-2"></i></button>`:''}</div></td></tr>`).join('')||'<tr><td colspan="11" class="sm-empty">No scholarships found.</td></tr>';
 document.querySelectorAll('.js-edit').forEach(b=>b.onclick=()=>openScholarship(Number(b.dataset.id)));document.querySelectorAll('.js-approve').forEach(b=>b.onclick=()=>approve(Number(b.dataset.id),'approved'));document.querySelectorAll('.js-reject').forEach(b=>b.onclick=()=>approve(Number(b.dataset.id),'rejected'));document.querySelectorAll('.js-delete').forEach(b=>b.onclick=()=>removeScholarship(Number(b.dataset.id)));window.lucide?.createIcons();
}
function renderTypes(data){
 types=data;
 $('typeBody').innerHTML=data.map(r=>`<tr><td><strong>${esc(r.type_code)}</strong></td><td>${esc(r.type_name)}</td><td>${esc(r.default_scope)}</td><td>${esc(r.calculation_type)}</td><td>${r.calculation_type==='percentage'?esc(r.default_value)+'%':money(r.default_value)}</td><td>${Number(r.approval_required)?'Yes':'No'}</td><td>${badge(r.status)}</td><td><div class="sm-actions"><button class="sm-action js-type-edit" data-id="${r.id}"><i data-lucide="pencil"></i></button><button class="sm-action danger js-type-delete" data-id="${r.id}"><i data-lucide="trash-2"></i></button></div></td></tr>`).join('')||'<tr><td colspan="8" class="sm-empty">No scholarship types found.</td></tr>';
 document.querySelectorAll('.js-type-edit').forEach(b=>b.onclick=()=>openType(Number(b.dataset.id)));document.querySelectorAll('.js-type-delete').forEach(b=>b.onclick=()=>removeType(Number(b.dataset.id)));window.lucide?.createIcons();
}
function renderHistory(data){$('historyBody').innerHTML=data.map(r=>`<tr><td>${esc(r.created_at)}</td><td>${esc(r.action_name)}</td><td>${esc(r.type_name||'-')}</td><td>${esc(r.target_name||'-')}</td><td>${money(r.amount||0)}</td><td>${esc(r.user_name||'-')}</td><td>${esc(r.remarks||'-')}</td></tr>`).join('')||'<tr><td colspan="7" class="sm-empty">No scholarship history.</td></tr>'}
function renderPagination(p={}){
 const total=Number(p.total||0),current=Number(p.page||1),per=Number(p.per_page||10),last=Math.max(1,Number(p.last_page||1)),start=total?((current-1)*per)+1:0,end=Math.min(current*per,total);
 $('recordInfo').textContent=`${total} scholarship${total===1?'':'s'}`;$('pageInfo').textContent=`Showing ${start}-${end} of ${total}`;
 let html=`<button class="sm-page-button" data-page="${current-1}" ${current<=1?'disabled':''}>‹</button>`;for(let x=Math.max(1,current-2);x<=Math.min(last,current+2);x++)html+=`<button class="sm-page-button ${x===current?'active':''}" data-page="${x}">${x}</button>`;html+=`<button class="sm-page-button" data-page="${current+1}" ${current>=last?'disabled':''}>›</button>`;$('pagination').innerHTML=html;document.querySelectorAll('.sm-page-button').forEach(b=>b.onclick=()=>{if(!b.disabled){page=Number(b.dataset.page);load()}});
}
async function load(){
 try{
  const r=await request('list',{search:$('search').value.trim(),academic_year_id:$('yearFilter').value,scope_type:$('scopeFilter').value,scholarship_type_id:$('typeFilter').value,approval_status:$('approvalFilter').value,status:$('statusFilter').value,page,per_page:10});
  meta=r.data.meta;permissions=r.data.permissions||permissions;renderScholarships(r.data.records||[]);renderTypes(r.data.types||[]);renderHistory(r.data.history||[]);renderPagination(r.data.pagination||{});updateStats(r.data.stats||{});
  if($('yearFilter').options.length<=1)fill('yearFilter',meta.years,'id','year_name','All Academic Years');if($('typeFilter').options.length<=1)fill('typeFilter',meta.types,'id','type_name','All Scholarship Types');
 }catch(e){message(e.message)}
}
function refreshTargets(){
 const year=Number($('academicYearId').value||0),scope=$('scopeType').value,oldClass=$('classId').value;
 fill('classId',meta.classes.filter(x=>!year||Number(x.academic_year_id)===year),'id','class_name','Select class');if(oldClass&&[...$('classId').options].some(o=>o.value===oldClass))$('classId').value=oldClass;
 const cls=Number($('classId').value||0);fill('studentId',meta.students.filter(x=>(!year||Number(x.academic_year_id)===year)&&(!cls||Number(x.class_id)===cls)),'id','student_name','Select student');
 fill('feeStructureId',meta.structures.filter(x=>(!year||Number(x.academic_year_id)===year)&&(!cls||Number(x.class_id)===cls)),'id','structure_name','All matching structures');
 $('studentId').closest('div').style.display=scope==='student'?'block':'none';
}
function openScholarship(id=0){
 const r=rows.find(x=>Number(x.id)===id);$('scholarshipId').value=r?.id||'';fill('scholarshipTypeId',meta.types,'id','type_name');fill('academicYearId',meta.years,'id','year_name');$('scopeType').value=r?.scope_type||'student';$('academicYearId').value=r?.academic_year_id||meta.years[0]?.id||'';refreshTargets();$('classId').value=r?.class_id||'';refreshTargets();$('studentId').value=r?.student_id||'';$('feeStructureId').value=r?.fee_structure_id||'';$('calculationType').value=r?.calculation_type||'percentage';$('scholarshipValue').value=r?.scholarship_value||'';$('validFrom').value=r?.valid_from||new Date().toISOString().slice(0,10);$('validTo').value=r?.valid_to||'';$('scholarshipStatus').value=r?.status||'active';$('remarks').value=r?.remarks||'';$('scholarshipTypeId').value=r?.scholarship_type_id||meta.types[0]?.id||'';$('scholarshipModalTitle').textContent=r?'Edit Scholarship':'Add Scholarship';bootstrap.Modal.getOrCreateInstance($('scholarshipModal')).show();
}
function openType(id=0){
 const r=types.find(x=>Number(x.id)===id);$('typeId').value=r?.id||'';$('typeCode').value=r?.type_code||'';$('typeName').value=r?.type_name||'';$('typeScope').value=r?.default_scope||'student';$('typeCalculation').value=r?.calculation_type||'percentage';$('typeValue').value=r?.default_value||'';$('typeApproval').value=String(Number(r?.approval_required??1));$('typeStatus').value=r?.status||'active';$('typeDescription').value=r?.description||'';$('typeModalTitle').textContent=r?'Edit Scholarship Type':'Add Scholarship Type';bootstrap.Modal.getOrCreateInstance($('typeModal')).show();
}
async function approve(id,status){const remarks=prompt(status==='approved'?'Approval remarks:':'Rejection reason:')??'';try{const r=await request('approve',{id,approval_status:status,remarks},'POST');message(r.message,true);await load()}catch(e){message(e.message)}}
async function removeScholarship(id){if(!confirm('Delete this scholarship? Fee assignments will be recalculated.'))return;try{const r=await request('delete',{id},'POST');message(r.message,true);await load()}catch(e){message(e.message)}}
async function removeType(id){if(!confirm('Delete this scholarship type?'))return;try{const r=await request('delete_type',{id},'POST');message(r.message,true);await load()}catch(e){message(e.message)}}
$('scholarshipForm').onsubmit=async e=>{e.preventDefault();try{const r=await request('save',{id:Number($('scholarshipId').value||0),scholarship_type_id:Number($('scholarshipTypeId').value),scope_type:$('scopeType').value,academic_year_id:Number($('academicYearId').value),class_id:Number($('classId').value||0),student_id:Number($('studentId').value||0),fee_structure_id:Number($('feeStructureId').value||0),calculation_type:$('calculationType').value,scholarship_value:Number($('scholarshipValue').value||0),valid_from:$('validFrom').value,valid_to:$('validTo').value,status:$('scholarshipStatus').value,remarks:$('remarks').value.trim()},'POST');bootstrap.Modal.getInstance($('scholarshipModal'))?.hide();message(r.message,true);await load()}catch(err){message(err.message)}};
$('typeForm').onsubmit=async e=>{e.preventDefault();try{const r=await request('save_type',{id:Number($('typeId').value||0),type_code:$('typeCode').value.trim(),type_name:$('typeName').value.trim(),default_scope:$('typeScope').value,calculation_type:$('typeCalculation').value,default_value:Number($('typeValue').value||0),approval_required:Number($('typeApproval').value),status:$('typeStatus').value,description:$('typeDescription').value.trim()},'POST');bootstrap.Modal.getInstance($('typeModal'))?.hide();message(r.message,true);await load()}catch(err){message(err.message)}};
document.querySelectorAll('.sm-tab').forEach(b=>b.onclick=()=>{document.querySelectorAll('.sm-tab').forEach(x=>x.classList.remove('active'));document.querySelectorAll('.sm-panel').forEach(x=>x.classList.remove('active'));b.classList.add('active');document.querySelector(`[data-panel="${b.dataset.tab}"]`)?.classList.add('active')});
$('addScholarshipBtn').onclick=()=>openScholarship();$('addTypeBtn').onclick=$('typeAddInside').onclick=()=>openType();$('refreshBtn').onclick=load;$('academicYearId').onchange=refreshTargets;$('classId').onchange=refreshTargets;$('scopeType').onchange=refreshTargets;
$('resetBtn').onclick=()=>{$('search').value='';['yearFilter','scopeFilter','typeFilter','approvalFilter','statusFilter'].forEach(id=>$(id).value='all');page=1;load()};['yearFilter','scopeFilter','typeFilter','approvalFilter','statusFilter'].forEach(id=>$(id).onchange=()=>{page=1;load()});let timer;$('search').oninput=()=>{clearTimeout(timer);timer=setTimeout(()=>{page=1;load()},300)};
document.querySelector('.fee-nav a[data-key="scholarships"]')?.classList.add('active');load();window.lucide?.createIcons();
})();
</script>

<?php require dirname(__DIR__).'/includes/layout-end.php'; ?>

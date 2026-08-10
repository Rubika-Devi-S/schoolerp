<?php
declare(strict_types=1);

$pageTitle='Discount Management';
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
.dm-page{display:grid;gap:16px}
.dm-page .page-title{font-size:28px;line-height:1.1}
.dm-page .page-subtitle{margin-top:4px}
.dm-message{display:none}.dm-message.show{display:block}
.dm-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.dm-stat{border-radius:14px;min-height:110px;padding:18px 20px;display:flex;align-items:center;gap:14px;color:#fff;position:relative;overflow:hidden;box-shadow:0 12px 28px rgba(15,23,42,.08)}
.dm-stat::after{content:"";position:absolute;width:110px;height:110px;border-radius:50%;right:-38px;top:-40px;background:rgba(255,255,255,.08)}
.dm-stat.purple{background:linear-gradient(135deg,#7448e8,#4b36cf)}
.dm-stat.green{background:linear-gradient(135deg,#45c783,#1ea568)}
.dm-stat.orange{background:linear-gradient(135deg,#ffb327,#ff8a17)}
.dm-stat.blue{background:linear-gradient(135deg,#4696ef,#2469d5)}
.dm-stat-icon{width:50px;height:50px;border-radius:50%;background:rgba(255,255,255,.16);display:grid;place-items:center;flex:0 0 auto}
.dm-stat-icon svg{width:25px;height:25px}
.dm-stat strong{display:block;font-size:25px;line-height:1}
.dm-stat small{display:block;font-size:11px;font-weight:700;opacity:.95;margin-bottom:6px}
.dm-stat .trend{font-size:9px;font-weight:700;opacity:.94;margin-top:8px}
.fee-nav{display:flex;gap:8px;overflow:auto;padding:10px}
.fee-nav a{flex:0 0 auto;display:inline-flex;align-items:center;gap:7px;padding:9px 12px;border:1px solid var(--border-soft,#e7ebf3);border-radius:9px;background:var(--card-bg,#fff);color:var(--text-main,#101a3b);font-size:11px;font-weight:800;text-decoration:none}
.fee-nav a.active{color:#fff;border-color:transparent;background:linear-gradient(135deg,#6547e8,#315ed8)}
.fee-nav svg{width:15px;height:15px}
.dm-tabs{display:flex;gap:8px;overflow:auto;padding:10px}
.dm-tab{border:1px solid var(--border-soft,#e7ebf3);background:var(--card-bg,#fff);color:var(--text-main,#101b46);border-radius:9px;padding:9px 12px;font-size:11px;font-weight:800;white-space:nowrap}
.dm-tab.active{color:#fff;border-color:transparent;background:linear-gradient(135deg,#6747e8,#2f62d7)}
.dm-panel{display:none}.dm-panel.active{display:block}
.dm-card{border-radius:14px;overflow:hidden}
.dm-card-head{padding:14px 16px;border-bottom:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;align-items:center;gap:10px}
.dm-card-actions{display:flex;gap:8px;flex-wrap:wrap}
.dm-filter{padding:14px 16px;display:grid;grid-template-columns:minmax(220px,1.4fr) repeat(5,minmax(135px,.7fr)) auto;gap:10px;border-bottom:1px solid var(--border-soft,#e7ebf3)}
.dm-table-wrap{overflow:auto}
.dm-table{min-width:1200px}
.dm-table th{font-size:10px}
.dm-table td{font-size:11px;vertical-align:middle}
.dm-empty{padding:40px 18px!important;text-align:center!important;color:var(--text-muted,#64748b)}
.dm-badge{display:inline-flex;align-items:center;padding:5px 9px;border-radius:999px;font-size:9px;font-weight:800;text-transform:capitalize}
.dm-badge.active,.dm-badge.approved{color:#16834f;background:#e8f8ef}
.dm-badge.inactive,.dm-badge.rejected{color:#dc2626;background:#fff0f1}
.dm-badge.pending{color:#9a6700;background:#fff7d6}
.dm-actions{display:flex;gap:5px;flex-wrap:wrap}
.dm-action{width:30px;height:30px;display:grid;place-items:center;border:1px solid #d7def1;border-radius:7px;background:var(--card-bg,#fff);color:#4f46e5}
.dm-action.danger{color:#dc2626}
.dm-action.success{color:#16834f}
.dm-action svg{width:13px;height:13px}
.dm-pagination{padding:14px 16px;border-top:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;align-items:center;gap:10px}
.dm-page-buttons{display:flex;gap:6px;flex-wrap:wrap}
.dm-page-button{min-width:34px;height:34px;border:1px solid var(--border-soft,#e7ebf3);background:#fff;border-radius:8px;font-size:11px;font-weight:800}
.dm-page-button.active{color:#fff;border-color:transparent;background:linear-gradient(135deg,#6547e8,#315ed8)}
.dm-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.dm-form-grid .full{grid-column:1/-1}
#discountModal .modal-dialog,#typeModal .modal-dialog,#viewModal .modal-dialog{max-height:calc(100dvh - 32px);margin:16px auto}
#discountModal .modal-content,#typeModal .modal-content,#viewModal .modal-content{max-height:calc(100dvh - 32px);overflow:hidden}
#discountModal form,#typeModal form{display:flex;flex-direction:column;max-height:calc(100dvh - 32px)}
#discountModal .modal-body,#typeModal .modal-body,#viewModal .modal-body{overflow-y:auto;min-height:0}
@media(max-width:1200px){.dm-filter{grid-template-columns:repeat(3,1fr)}}
@media(max-width:900px){.dm-stats{grid-template-columns:repeat(2,1fr)}}
@media(max-width:575px){.dm-stats,.dm-filter,.dm-form-grid{grid-template-columns:1fr}.dm-form-grid .full{grid-column:auto}.dm-card-head,.dm-pagination{align-items:flex-start;flex-direction:column}}
</style>

<div class="dm-page" data-page="discounts">
<div class="page-heading">
 <div><h1 class="page-title">Discount Management</h1><p class="page-subtitle">Configure, approve and monitor student fee discounts.</p></div>
 <div class="page-actions">
  <button id="addDiscountBtn" class="btn-ui btn-primary-ui" type="button"><i data-lucide="badge-percent"></i> Add Discount</button>
  <button id="addTypeBtn" class="btn-ui" type="button"><i data-lucide="plus"></i> Discount Type</button>
  <button id="refreshBtn" class="btn-ui" type="button"><i data-lucide="refresh-cw"></i> Refresh</button>
 </div>
</div>

<div id="dmMessage" class="alert dm-message"></div>

<section class="dm-stats">
 <article class="dm-stat purple"><span class="dm-stat-icon"><i data-lucide="badge-percent"></i></span><div><small>Total Discounts</small><strong id="statTotal">0</strong><div class="trend">All discount records</div></div></article>
 <article class="dm-stat green"><span class="dm-stat-icon"><i data-lucide="circle-check-big"></i></span><div><small>Approved Discounts</small><strong id="statApproved">0</strong><div class="trend">Applied to fee assignments</div></div></article>
 <article class="dm-stat orange"><span class="dm-stat-icon"><i data-lucide="clock-3"></i></span><div><small>Pending Approval</small><strong id="statPending">0</strong><div class="trend">Awaiting review</div></div></article>
 <article class="dm-stat blue"><span class="dm-stat-icon"><i data-lucide="indian-rupee"></i></span><div><small>Total Benefit</small><strong id="statBenefit">₹0</strong><div class="trend">Approved discount value</div></div></article>
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
<a href="fee-reports.php" data-key="reports"><i data-lucide="chart-column"></i> Reports</a>
</section>

<section class="ui-card dm-tabs">
 <button class="dm-tab active" data-tab="discounts" type="button">Discount Dashboard</button>
 <button class="dm-tab" data-tab="types" type="button">Discount Types</button>
 <button class="dm-tab" data-tab="history" type="button">Discount History</button>
</section>

<section class="dm-panel active" data-panel="discounts">
<section class="ui-card dm-card">
<div class="dm-card-head"><strong>Discount List</strong><div class="dm-card-actions"><small id="recordInfo" class="text-muted">Loading...</small></div></div>
<div class="dm-filter">
 <input id="search" class="form-control" placeholder="Student, admission no. or discount...">
 <select id="yearFilter" class="form-select"><option value="all">All Academic Years</option></select>
 <select id="scopeFilter" class="form-select"><option value="all">All Scopes</option><option value="student">Student-wise</option><option value="class">Class-wise</option><option value="sibling">Sibling</option><option value="staff_child">Staff Child</option></select>
 <select id="typeFilter" class="form-select"><option value="all">All Discount Types</option></select>
 <select id="approvalFilter" class="form-select"><option value="all">All Approval Status</option><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option></select>
 <select id="statusFilter" class="form-select"><option value="all">All Statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select>
 <button id="resetBtn" class="btn-ui" type="button"><i data-lucide="rotate-ccw"></i> Reset</button>
</div>
<div class="dm-table-wrap"><table class="data-table dm-table"><thead><tr><th>#</th><th>Discount</th><th>Scope</th><th>Student / Class</th><th>Academic Year</th><th>Fee Structure</th><th>Calculation</th><th>Benefit</th><th>Approval</th><th>Status</th><th>Actions</th></tr></thead><tbody id="discountBody"><tr><td colspan="11" class="dm-empty">Loading...</td></tr></tbody></table></div>
<div class="dm-pagination"><small id="pageInfo" class="text-muted"></small><div id="pagination" class="dm-page-buttons"></div></div>
</section>
</section>

<section class="dm-panel" data-panel="types">
<section class="ui-card dm-card">
<div class="dm-card-head"><strong>Discount Types</strong><div class="dm-card-actions"><button id="typeAddInside" class="btn-ui btn-primary-ui" type="button"><i data-lucide="plus"></i> Add Type</button></div></div>
<div class="dm-table-wrap"><table class="data-table dm-table"><thead><tr><th>Code</th><th>Name</th><th>Default Scope</th><th>Calculation</th><th>Default Value</th><th>Approval Required</th><th>Status</th><th>Actions</th></tr></thead><tbody id="typeBody"></tbody></table></div>
</section>
</section>

<section class="dm-panel" data-panel="history">
<section class="ui-card dm-card">
<div class="dm-card-head"><strong>Discount History</strong></div>
<div class="dm-table-wrap"><table class="data-table dm-table"><thead><tr><th>Date</th><th>Action</th><th>Discount</th><th>Student / Class</th><th>Amount</th><th>User</th><th>Remarks</th></tr></thead><tbody id="historyBody"></tbody></table></div>
</section>
</section>
</div>

<div class="modal fade" id="discountModal" tabindex="-1">
<div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content"><form id="discountForm">
<div class="modal-header"><h5 id="discountModalTitle" class="modal-title">Add Discount</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body dm-form-grid">
<input id="discountId" type="hidden">
<div><label class="form-label">Discount Type *</label><select id="discountTypeId" class="form-select" required></select></div>
<div><label class="form-label">Scope *</label><select id="scopeType" class="form-select"><option value="student">Student-wise</option><option value="class">Class-wise</option><option value="sibling">Sibling Discount</option><option value="staff_child">Staff Child Discount</option></select></div>
<div><label class="form-label">Academic Year *</label><select id="academicYearId" class="form-select" required></select></div>
<div><label class="form-label">Class</label><select id="classId" class="form-select"></select></div>
<div><label class="form-label">Student</label><select id="studentId" class="form-select"></select></div>
<div><label class="form-label">Fee Structure</label><select id="feeStructureId" class="form-select"></select></div>
<div><label class="form-label">Calculation *</label><select id="calculationType" class="form-select"><option value="percentage">Percentage</option><option value="fixed">Fixed Amount</option></select></div>
<div><label class="form-label">Discount Value *</label><input id="discountValue" class="form-control" type="number" min="0.01" step="0.01" required></div>
<div><label class="form-label">Start Date *</label><input id="startDate" class="form-control" type="date" required></div>
<div><label class="form-label">End Date</label><input id="endDate" class="form-control" type="date"></div>
<div><label class="form-label">Status</label><select id="discountStatus" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
<div class="full"><label class="form-label">Reason / Remarks *</label><textarea id="remarks" class="form-control" rows="3" maxlength="500" required></textarea></div>
</div>
<div class="modal-footer"><button type="button" class="btn-ui" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn-ui btn-primary-ui">Save Discount</button></div>
</form></div></div>
</div>

<div class="modal fade" id="typeModal" tabindex="-1">
<div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><form id="typeForm">
<div class="modal-header"><h5 id="typeModalTitle" class="modal-title">Add Discount Type</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body dm-form-grid">
<input id="typeId" type="hidden">
<div><label class="form-label">Type Code *</label><input id="typeCode" class="form-control" maxlength="40" required></div>
<div><label class="form-label">Type Name *</label><input id="typeName" class="form-control" maxlength="120" required></div>
<div><label class="form-label">Default Scope</label><select id="typeScope" class="form-select"><option value="student">Student-wise</option><option value="class">Class-wise</option><option value="sibling">Sibling</option><option value="staff_child">Staff Child</option></select></div>
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

const apiUrl=new URL('../api/discount-management.php',window.location.href).href;
let csrfToken=<?=json_encode($feeCsrf)?>;
let meta={years:[],classes:[],students:[],structures:[],types:[]};
let rows=[],types=[],page=1,permissions={approve:false,delete:false};
const $=id=>document.getElementById(id);
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
const money=v=>new Intl.NumberFormat('en-IN',{style:'currency',currency:'INR',maximumFractionDigits:2}).format(Number(v||0));
const badge=v=>`<span class="dm-badge ${esc(String(v||'').toLowerCase())}">${esc(v||'-')}</span>`;

async function request(action,data={},method='GET'){
 let response;
 if(method==='GET'){const url=new URL(apiUrl);url.searchParams.set('action',action);Object.entries(data).forEach(([k,v])=>{if(v!==''&&v!==null&&v!==undefined)url.searchParams.set(k,String(v))});response=await fetch(url,{headers:{Accept:'application/json'},credentials:'same-origin'});}
 else response=await fetch(apiUrl,{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json'},credentials:'same-origin',body:JSON.stringify({action,csrf_token:csrfToken,...data})});
 const text=await response.text();let result;try{result=JSON.parse(text)}catch{throw new Error(`Discount API returned HTTP ${response.status}. ${text.replace(/\s+/g,' ').trim().slice(0,220)||'Invalid server response.'}`)}
 if(!response.ok||!result.success)throw new Error(result.message||'Request failed.');if(result.data?.csrf_token)csrfToken=result.data.csrf_token;return result;
}
function message(text,ok=false){const b=$('dmMessage');b.className='alert dm-message show '+(ok?'alert-success':'alert-danger');b.textContent=text}
function fill(id,items,key,label,first=''){const e=$(id);e.innerHTML=(first?`<option value="">${esc(first)}</option>`:'')+items.map(x=>`<option value="${esc(x[key])}">${esc(x[label])}</option>`).join('')}
function updateStats(s={}){$('statTotal').textContent=Number(s.total||0).toLocaleString('en-IN');$('statApproved').textContent=Number(s.approved||0).toLocaleString('en-IN');$('statPending').textContent=Number(s.pending||0).toLocaleString('en-IN');$('statBenefit').textContent=money(s.benefit||0)}
function renderDiscounts(data){
 rows=data;
 $('discountBody').innerHTML=data.map(r=>`<tr><td>${r.row_number}</td><td><strong>${esc(r.type_name)}</strong><small class="d-block text-muted">${esc(r.type_code)}</small></td><td>${esc(r.scope_label)}</td><td>${esc(r.target_name||'-')}</td><td>${esc(r.year_name)}</td><td>${esc(r.structure_name||'All Structures')}</td><td>${esc(r.calculation_type==='percentage'?r.discount_value+'%':money(r.discount_value))}</td><td><strong>${money(r.applied_amount)}</strong></td><td>${badge(r.approval_status)}</td><td>${badge(r.status)}</td><td><div class="dm-actions"><button class="dm-action js-edit" data-id="${r.id}" title="Edit"><i data-lucide="pencil"></i></button>${permissions.approve&&r.approval_status==='pending'?`<button class="dm-action success js-approve" data-id="${r.id}" title="Approve"><i data-lucide="check"></i></button><button class="dm-action danger js-reject" data-id="${r.id}" title="Reject"><i data-lucide="x"></i></button>`:''}${permissions.delete?`<button class="dm-action danger js-delete" data-id="${r.id}" title="Delete"><i data-lucide="trash-2"></i></button>`:''}</div></td></tr>`).join('')||'<tr><td colspan="11" class="dm-empty">No discounts found.</td></tr>';
 document.querySelectorAll('.js-edit').forEach(b=>b.onclick=()=>openDiscount(Number(b.dataset.id)));document.querySelectorAll('.js-approve').forEach(b=>b.onclick=()=>approve(Number(b.dataset.id),'approved'));document.querySelectorAll('.js-reject').forEach(b=>b.onclick=()=>approve(Number(b.dataset.id),'rejected'));document.querySelectorAll('.js-delete').forEach(b=>b.onclick=()=>removeDiscount(Number(b.dataset.id)));window.lucide?.createIcons();
}
function renderTypes(data){
 types=data;
 $('typeBody').innerHTML=data.map(r=>`<tr><td><strong>${esc(r.type_code)}</strong></td><td>${esc(r.type_name)}</td><td>${esc(r.default_scope)}</td><td>${esc(r.calculation_type)}</td><td>${r.calculation_type==='percentage'?esc(r.default_value)+'%':money(r.default_value)}</td><td>${Number(r.approval_required)?'Yes':'No'}</td><td>${badge(r.status)}</td><td><div class="dm-actions"><button class="dm-action js-type-edit" data-id="${r.id}"><i data-lucide="pencil"></i></button><button class="dm-action danger js-type-delete" data-id="${r.id}"><i data-lucide="trash-2"></i></button></div></td></tr>`).join('')||'<tr><td colspan="8" class="dm-empty">No discount types found.</td></tr>';
 document.querySelectorAll('.js-type-edit').forEach(b=>b.onclick=()=>openType(Number(b.dataset.id)));document.querySelectorAll('.js-type-delete').forEach(b=>b.onclick=()=>removeType(Number(b.dataset.id)));window.lucide?.createIcons();
}
function renderHistory(data){$('historyBody').innerHTML=data.map(r=>`<tr><td>${esc(r.created_at)}</td><td>${esc(r.action_name)}</td><td>${esc(r.type_name||'-')}</td><td>${esc(r.target_name||'-')}</td><td>${money(r.amount||0)}</td><td>${esc(r.user_name||'-')}</td><td>${esc(r.remarks||'-')}</td></tr>`).join('')||'<tr><td colspan="7" class="dm-empty">No discount history.</td></tr>'}
function renderPagination(p={}){
 const total=Number(p.total||0),current=Number(p.page||1),per=Number(p.per_page||10),last=Math.max(1,Number(p.last_page||1)),start=total?((current-1)*per)+1:0,end=Math.min(current*per,total);
 $('recordInfo').textContent=`${total} discount${total===1?'':'s'}`;$('pageInfo').textContent=`Showing ${start}-${end} of ${total}`;
 let html=`<button class="dm-page-button" data-page="${current-1}" ${current<=1?'disabled':''}>‹</button>`;for(let i=Math.max(1,current-2);i<=Math.min(last,current+2);i++)html+=`<button class="dm-page-button ${i===current?'active':''}" data-page="${i}">${i}</button>`;html+=`<button class="dm-page-button" data-page="${current+1}" ${current>=last?'disabled':''}>›</button>`;$('pagination').innerHTML=html;document.querySelectorAll('.dm-page-button').forEach(b=>b.onclick=()=>{if(!b.disabled){page=Number(b.dataset.page);load()}});
}
async function load(){
 try{
  const r=await request('list',{search:$('search').value.trim(),academic_year_id:$('yearFilter').value,scope_type:$('scopeFilter').value,discount_type_id:$('typeFilter').value,approval_status:$('approvalFilter').value,status:$('statusFilter').value,page,per_page:10});
  meta=r.data.meta;permissions=r.data.permissions||permissions;renderDiscounts(r.data.records||[]);renderTypes(r.data.types||[]);renderHistory(r.data.history||[]);renderPagination(r.data.pagination||{});updateStats(r.data.stats||{});
  if($('yearFilter').options.length<=1)fill('yearFilter',meta.years,'id','year_name','All Academic Years');if($('typeFilter').options.length<=1)fill('typeFilter',meta.types,'id','type_name','All Discount Types');
 }catch(e){message(e.message)}
}
function refreshTargets(){
 const year=Number($('academicYearId').value||0),cls=Number($('classId').value||0),scope=$('scopeType').value;
 fill('classId',meta.classes.filter(x=>!year||Number(x.academic_year_id)===year),'id','class_name','Select class');
 const selectedClass=Number($('classId').value||0);fill('studentId',meta.students.filter(x=>(!year||Number(x.academic_year_id)===year)&&(!selectedClass||Number(x.class_id)===selectedClass)),'id','student_name','Select student');
 fill('feeStructureId',meta.structures.filter(x=>(!year||Number(x.academic_year_id)===year)&&(!selectedClass||Number(x.class_id)===selectedClass)),'id','structure_name','All matching structures');
 $('studentId').closest('div').style.display=scope==='student'||scope==='sibling'||scope==='staff_child'?'block':'none';
}
function openDiscount(id=0){
 const r=rows.find(x=>Number(x.id)===id);$('discountId').value=r?.id||'';fill('discountTypeId',meta.types,'id','type_name');fill('academicYearId',meta.years,'id','year_name');$('scopeType').value=r?.scope_type||'student';$('academicYearId').value=r?.academic_year_id||meta.years[0]?.id||'';refreshTargets();$('classId').value=r?.class_id||'';refreshTargets();$('studentId').value=r?.student_id||'';$('feeStructureId').value=r?.fee_structure_id||'';$('calculationType').value=r?.calculation_type||'percentage';$('discountValue').value=r?.discount_value||'';$('startDate').value=r?.start_date||new Date().toISOString().slice(0,10);$('endDate').value=r?.end_date||'';$('discountStatus').value=r?.status||'active';$('remarks').value=r?.remarks||'';$('discountTypeId').value=r?.discount_type_id||meta.types[0]?.id||'';$('discountModalTitle').textContent=r?'Edit Discount':'Add Discount';bootstrap.Modal.getOrCreateInstance($('discountModal')).show();
}
function openType(id=0){
 const r=types.find(x=>Number(x.id)===id);$('typeId').value=r?.id||'';$('typeCode').value=r?.type_code||'';$('typeName').value=r?.type_name||'';$('typeScope').value=r?.default_scope||'student';$('typeCalculation').value=r?.calculation_type||'percentage';$('typeValue').value=r?.default_value||'';$('typeApproval').value=String(Number(r?.approval_required??1));$('typeStatus').value=r?.status||'active';$('typeDescription').value=r?.description||'';$('typeModalTitle').textContent=r?'Edit Discount Type':'Add Discount Type';bootstrap.Modal.getOrCreateInstance($('typeModal')).show();
}
async function approve(id,status){const remarks=prompt(status==='approved'?'Approval remarks:':'Rejection reason:')??'';try{const r=await request('approve',{id,approval_status:status,remarks},'POST');message(r.message,true);await load()}catch(e){message(e.message)}}
async function removeDiscount(id){if(!confirm('Delete this discount? Applied fee assignments will be recalculated.'))return;try{const r=await request('delete',{id},'POST');message(r.message,true);await load()}catch(e){message(e.message)}}
async function removeType(id){if(!confirm('Delete this discount type?'))return;try{const r=await request('delete_type',{id},'POST');message(r.message,true);await load()}catch(e){message(e.message)}}
$('discountForm').onsubmit=async e=>{e.preventDefault();try{const r=await request('save',{id:Number($('discountId').value||0),discount_type_id:Number($('discountTypeId').value),scope_type:$('scopeType').value,academic_year_id:Number($('academicYearId').value),class_id:Number($('classId').value||0),student_id:Number($('studentId').value||0),fee_structure_id:Number($('feeStructureId').value||0),calculation_type:$('calculationType').value,discount_value:Number($('discountValue').value||0),start_date:$('startDate').value,end_date:$('endDate').value,status:$('discountStatus').value,remarks:$('remarks').value.trim()},'POST');bootstrap.Modal.getInstance($('discountModal'))?.hide();message(r.message,true);await load()}catch(err){message(err.message)}};
$('typeForm').onsubmit=async e=>{e.preventDefault();try{const r=await request('save_type',{id:Number($('typeId').value||0),type_code:$('typeCode').value.trim(),type_name:$('typeName').value.trim(),default_scope:$('typeScope').value,calculation_type:$('typeCalculation').value,default_value:Number($('typeValue').value||0),approval_required:Number($('typeApproval').value),status:$('typeStatus').value,description:$('typeDescription').value.trim()},'POST');bootstrap.Modal.getInstance($('typeModal'))?.hide();message(r.message,true);await load()}catch(err){message(err.message)}};
document.querySelectorAll('.dm-tab').forEach(b=>b.onclick=()=>{document.querySelectorAll('.dm-tab').forEach(x=>x.classList.remove('active'));document.querySelectorAll('.dm-panel').forEach(x=>x.classList.remove('active'));b.classList.add('active');document.querySelector(`[data-panel="${b.dataset.tab}"]`)?.classList.add('active')});
$('addDiscountBtn').onclick=()=>openDiscount();$('addTypeBtn').onclick=$('typeAddInside').onclick=()=>openType();$('refreshBtn').onclick=load;$('academicYearId').onchange=refreshTargets;$('classId').onchange=refreshTargets;$('scopeType').onchange=refreshTargets;
$('resetBtn').onclick=()=>{$('search').value='';['yearFilter','scopeFilter','typeFilter','approvalFilter','statusFilter'].forEach(id=>$(id).value='all');page=1;load()};['yearFilter','scopeFilter','typeFilter','approvalFilter','statusFilter'].forEach(id=>$(id).onchange=()=>{page=1;load()});let timer;$('search').oninput=()=>{clearTimeout(timer);timer=setTimeout(()=>{page=1;load()},300)};
document.querySelector('.fee-nav a[data-key="discounts"]')?.classList.add('active');load();window.lucide?.createIcons();
})();
</script>

<?php require dirname(__DIR__).'/includes/layout-end.php'; ?>

<?php
declare(strict_types=1);

$pageTitle='Fine Management';
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
.fm-page{display:grid;gap:16px}
.fm-page .page-title{font-size:28px;line-height:1.1}
.fm-page .page-subtitle{margin-top:4px}
.fm-message{display:none}.fm-message.show{display:block}
.fm-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.fm-stat{border-radius:14px;min-height:110px;padding:18px 20px;display:flex;align-items:center;gap:14px;color:#fff;position:relative;overflow:hidden;box-shadow:0 12px 28px rgba(15,23,42,.08)}
.fm-stat::after{content:"";position:absolute;width:110px;height:110px;border-radius:50%;right:-38px;top:-40px;background:rgba(255,255,255,.08)}
.fm-stat.purple{background:linear-gradient(135deg,#7448e8,#4b36cf)}
.fm-stat.green{background:linear-gradient(135deg,#45c783,#1ea568)}
.fm-stat.orange{background:linear-gradient(135deg,#ffb327,#ff8a17)}
.fm-stat.blue{background:linear-gradient(135deg,#4696ef,#2469d5)}
.fm-stat-icon{width:50px;height:50px;border-radius:50%;background:rgba(255,255,255,.16);display:grid;place-items:center;flex:0 0 auto}
.fm-stat-icon svg{width:25px;height:25px}
.fm-stat strong{display:block;font-size:25px;line-height:1}
.fm-stat small{display:block;font-size:11px;font-weight:700;opacity:.95;margin-bottom:6px}
.fm-stat .trend{font-size:9px;font-weight:700;opacity:.94;margin-top:8px}
.fee-nav{display:flex;gap:8px;overflow:auto;padding:10px}
.fee-nav a{flex:0 0 auto;display:inline-flex;align-items:center;gap:7px;padding:9px 12px;border:1px solid var(--border-soft,#e7ebf3);border-radius:9px;background:var(--card-bg,#fff);color:var(--text-main,#101a3b);font-size:11px;font-weight:800;text-decoration:none}
.fee-nav a.active{color:#fff;border-color:transparent;background:linear-gradient(135deg,#6547e8,#315ed8)}
.fee-nav svg{width:15px;height:15px}
.fm-tabs{display:flex;gap:8px;overflow:auto;padding:10px}
.fm-tab{border:1px solid var(--border-soft,#e7ebf3);background:var(--card-bg,#fff);color:var(--text-main,#101b46);border-radius:9px;padding:9px 12px;font-size:11px;font-weight:800;white-space:nowrap}
.fm-tab.active{color:#fff;border-color:transparent;background:linear-gradient(135deg,#6747e8,#2f62d7)}
.fm-panel{display:none}.fm-panel.active{display:block}
.fm-card{border-radius:14px;overflow:hidden}
.fm-card-head{padding:14px 16px;border-bottom:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;align-items:center;gap:10px}
.fm-card-actions{display:flex;gap:8px;flex-wrap:wrap}
.fm-filter{padding:14px 16px;display:grid;grid-template-columns:minmax(220px,1.4fr) repeat(5,minmax(135px,.7fr)) auto;gap:10px;border-bottom:1px solid var(--border-soft,#e7ebf3)}
.fm-table-wrap{overflow:auto}
.fm-table{min-width:1220px}
.fm-table th{font-size:10px}
.fm-table td{font-size:11px;vertical-align:middle}
.fm-empty{padding:40px 18px!important;text-align:center!important;color:var(--text-muted,#64748b)}
.fm-badge{display:inline-flex;align-items:center;padding:5px 9px;border-radius:999px;font-size:9px;font-weight:800;text-transform:capitalize}
.fm-badge.active,.fm-badge.paid{color:#16834f;background:#e8f8ef}
.fm-badge.inactive,.fm-badge.waived{color:#64748b;background:#eef2f7}
.fm-badge.pending,.fm-badge.overdue{color:#9a6700;background:#fff7d6}
.fm-actions{display:flex;gap:5px;flex-wrap:wrap}
.fm-action{width:30px;height:30px;display:grid;place-items:center;border:1px solid #d7def1;border-radius:7px;background:var(--card-bg,#fff);color:#4f46e5}
.fm-action.danger{color:#dc2626}.fm-action.success{color:#16834f}
.fm-action svg{width:13px;height:13px}
.fm-pagination{padding:14px 16px;border-top:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;align-items:center;gap:10px}
.fm-page-buttons{display:flex;gap:6px;flex-wrap:wrap}
.fm-page-button{min-width:34px;height:34px;border:1px solid var(--border-soft,#e7ebf3);background:#fff;border-radius:8px;font-size:11px;font-weight:800}
.fm-page-button.active{color:#fff;border-color:transparent;background:linear-gradient(135deg,#6547e8,#315ed8)}
.fm-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.fm-form-grid .full{grid-column:1/-1}
#ruleModal .modal-dialog,#fineModal .modal-dialog{max-height:calc(100dvh - 32px);margin:16px auto}
#ruleModal .modal-content,#fineModal .modal-content{max-height:calc(100dvh - 32px);overflow:hidden}
#ruleModal form,#fineModal form{display:flex;flex-direction:column;max-height:calc(100dvh - 32px)}
#ruleModal .modal-body,#fineModal .modal-body{overflow-y:auto;min-height:0}
@media(max-width:1200px){.fm-filter{grid-template-columns:repeat(3,1fr)}}
@media(max-width:900px){.fm-stats{grid-template-columns:repeat(2,1fr)}}
@media(max-width:575px){.fm-stats,.fm-filter,.fm-form-grid{grid-template-columns:1fr}.fm-form-grid .full{grid-column:auto}.fm-card-head,.fm-pagination{align-items:flex-start;flex-direction:column}}
</style>

<div class="fm-page" data-page="fines">
<div class="page-heading">
 <div><h1 class="page-title">Fine Management</h1><p class="page-subtitle">Configure late-payment rules, calculate fines and manage waivers.</p></div>
 <div class="page-actions">
  <button id="addRuleBtn" class="btn-ui btn-primary-ui" type="button"><i data-lucide="plus"></i> Add Fine Rule</button>
  <button id="manualFineBtn" class="btn-ui" type="button"><i data-lucide="user-round-plus"></i> Student Fine</button>
  <button id="calculateBtn" class="btn-ui" type="button"><i data-lucide="calculator"></i> Calculate Fines</button>
  <button id="refreshBtn" class="btn-ui" type="button"><i data-lucide="refresh-cw"></i> Refresh</button>
 </div>
</div>

<div id="fmMessage" class="alert fm-message"></div>

<section class="fm-stats">
 <article class="fm-stat purple"><span class="fm-stat-icon"><i data-lucide="landmark"></i></span><div><small>Total Applied Fine</small><strong id="statTotal">₹0</strong><div class="trend">All generated fines</div></div></article>
 <article class="fm-stat green"><span class="fm-stat-icon"><i data-lucide="circle-check-big"></i></span><div><small>Paid Fine</small><strong id="statPaid">₹0</strong><div class="trend">Fine collected</div></div></article>
 <article class="fm-stat orange"><span class="fm-stat-icon"><i data-lucide="clock-3"></i></span><div><small>Pending Fine</small><strong id="statPending">₹0</strong><div class="trend">Outstanding fine amount</div></div></article>
 <article class="fm-stat blue"><span class="fm-stat-icon"><i data-lucide="hand-heart"></i></span><div><small>Waived Fine</small><strong id="statWaived">₹0</strong><div class="trend">Approved waiver value</div></div></article>
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
</section>

<section class="ui-card fm-tabs">
 <button class="fm-tab active" data-tab="dashboard" type="button">Fine Dashboard</button>
 <button class="fm-tab" data-tab="rules" type="button">Fine Rules</button>
 <button class="fm-tab" data-tab="history" type="button">Fine History</button>
</section>

<section class="fm-panel active" data-panel="dashboard">
<section class="ui-card fm-card">
<div class="fm-card-head"><strong>Applied Fine List</strong><div class="fm-card-actions"><small id="recordInfo" class="text-muted">Loading...</small></div></div>
<div class="fm-filter">
 <input id="search" class="form-control" placeholder="Student, admission no. or fine rule...">
 <select id="yearFilter" class="form-select"><option value="all">All Academic Years</option></select>
 <select id="classFilter" class="form-select"><option value="all">All Classes</option></select>
 <select id="ruleFilter" class="form-select"><option value="all">All Fine Rules</option></select>
 <select id="statusFilter" class="form-select"><option value="all">All Fine Statuses</option><option value="pending">Pending</option><option value="paid">Paid</option><option value="waived">Waived</option></select>
 <select id="sourceFilter" class="form-select"><option value="all">All Sources</option><option value="automatic">Automatic</option><option value="manual">Manual</option></select>
 <button id="resetBtn" class="btn-ui" type="button"><i data-lucide="rotate-ccw"></i> Reset</button>
</div>
<div class="fm-table-wrap"><table class="data-table fm-table"><thead><tr><th>#</th><th>Student</th><th>Admission No.</th><th>Academic Year</th><th>Class</th><th>Fee Structure</th><th>Fine Rule</th><th>Due Date</th><th>Days Late</th><th>Fine Amount</th><th>Status</th><th>Actions</th></tr></thead><tbody id="fineBody"><tr><td colspan="12" class="fm-empty">Loading...</td></tr></tbody></table></div>
<div class="fm-pagination"><small id="pageInfo" class="text-muted"></small><div id="pagination" class="fm-page-buttons"></div></div>
</section>
</section>

<section class="fm-panel" data-panel="rules">
<section class="ui-card fm-card">
<div class="fm-card-head"><strong>Fine Rules Management</strong><div class="fm-card-actions"><button id="ruleAddInside" class="btn-ui btn-primary-ui" type="button"><i data-lucide="plus"></i> Add Rule</button></div></div>
<div class="fm-table-wrap"><table class="data-table fm-table"><thead><tr><th>Rule Code</th><th>Rule Name</th><th>Scope</th><th>Calculation</th><th>Fine Value</th><th>Grace Period</th><th>Maximum Fine</th><th>Status</th><th>Actions</th></tr></thead><tbody id="ruleBody"></tbody></table></div>
</section>
</section>

<section class="fm-panel" data-panel="history">
<section class="ui-card fm-card">
<div class="fm-card-head"><strong>Fine History</strong></div>
<div class="fm-table-wrap"><table class="data-table fm-table"><thead><tr><th>Date</th><th>Action</th><th>Student</th><th>Fine Rule</th><th>Amount</th><th>User</th><th>Remarks</th></tr></thead><tbody id="historyBody"></tbody></table></div>
</section>
</section>
</div>

<div class="modal fade" id="ruleModal" tabindex="-1">
<div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content"><form id="ruleForm">
<div class="modal-header"><h5 id="ruleModalTitle" class="modal-title">Add Fine Rule</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body fm-form-grid">
<input id="ruleId" type="hidden">
<div><label class="form-label">Rule Code *</label><input id="ruleCode" class="form-control" maxlength="40" required></div>
<div><label class="form-label">Rule Name *</label><input id="ruleName" class="form-control" maxlength="120" required></div>
<div><label class="form-label">Academic Year *</label><select id="ruleYearId" class="form-select" required></select></div>
<div><label class="form-label">Scope *</label><select id="ruleScope" class="form-select"><option value="all">All Assignments</option><option value="class">Class-wise</option><option value="student">Student-wise</option></select></div>
<div><label class="form-label">Class</label><select id="ruleClassId" class="form-select"></select></div>
<div><label class="form-label">Student</label><select id="ruleStudentId" class="form-select"></select></div>
<div><label class="form-label">Fee Structure</label><select id="ruleStructureId" class="form-select"></select></div>
<div><label class="form-label">Calculation Type *</label><select id="ruleCalculation"><option value="fixed">Fixed Amount</option><option value="daily">Per Day</option><option value="percentage">Percentage of Balance</option></select></div>
<div><label class="form-label">Fine Value *</label><input id="ruleValue" class="form-control" type="number" min="0.01" step="0.01" required></div>
<div><label class="form-label">Grace Period (Days)</label><input id="graceDays" class="form-control" type="number" min="0" step="1" value="0"></div>
<div><label class="form-label">Maximum Fine</label><input id="maxFine" class="form-control" type="number" min="0" step="0.01" value="0"></div>
<div><label class="form-label">Status</label><select id="ruleStatus" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
<div class="full"><label class="form-label">Description</label><textarea id="ruleDescription" class="form-control" rows="3" maxlength="500"></textarea></div>
</div>
<div class="modal-footer"><button type="button" class="btn-ui" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn-ui btn-primary-ui">Save Fine Rule</button></div>
</form></div></div>
</div>

<div class="modal fade" id="fineModal" tabindex="-1">
<div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><form id="fineForm">
<div class="modal-header"><h5 class="modal-title">Add Student Fine</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body fm-form-grid">
<div><label class="form-label">Academic Year *</label><select id="fineYearId" class="form-select" required></select></div>
<div><label class="form-label">Class *</label><select id="fineClassId" class="form-select" required></select></div>
<div><label class="form-label">Student *</label><select id="fineStudentId" class="form-select" required></select></div>
<div><label class="form-label">Fee Assignment *</label><select id="fineAssignmentId" class="form-select" required></select></div>
<div><label class="form-label">Fine Amount *</label><input id="manualAmount" class="form-control" type="number" min="0.01" step="0.01" required></div>
<div><label class="form-label">Fine Date *</label><input id="fineDate" class="form-control" type="date" required></div>
<div class="full"><label class="form-label">Reason *</label><textarea id="manualRemarks" class="form-control" rows="3" maxlength="500" required></textarea></div>
</div>
<div class="modal-footer"><button type="button" class="btn-ui" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn-ui btn-primary-ui">Apply Fine</button></div>
</form></div></div>
</div>

<script>
(function(){
'use strict';
const apiUrl=new URL('../api/fine-management.php',window.location.href).href;
let csrfToken=<?=json_encode($feeCsrf)?>;
let meta={years:[],classes:[],students:[],structures:[],rules:[],assignments:[]};
let fines=[],rules=[],page=1,permissions={waive:false,delete:false};
const $=id=>document.getElementById(id);
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
const money=v=>new Intl.NumberFormat('en-IN',{style:'currency',currency:'INR',maximumFractionDigits:2}).format(Number(v||0));
const badge=v=>`<span class="fm-badge ${esc(String(v||'').toLowerCase())}">${esc(v||'-')}</span>`;
async function request(action,data={},method='GET'){
 let response;
 if(method==='GET'){const url=new URL(apiUrl);url.searchParams.set('action',action);Object.entries(data).forEach(([k,v])=>{if(v!==''&&v!==null&&v!==undefined)url.searchParams.set(k,String(v))});response=await fetch(url,{headers:{Accept:'application/json'},credentials:'same-origin'});}
 else response=await fetch(apiUrl,{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json'},credentials:'same-origin',body:JSON.stringify({action,csrf_token:csrfToken,...data})});
 const text=await response.text();let result;try{result=JSON.parse(text)}catch{throw new Error(`Fine API returned HTTP ${response.status}. ${text.replace(/\s+/g,' ').trim().slice(0,220)||'Invalid server response.'}`)}
 if(!response.ok||!result.success)throw new Error(result.message||'Request failed.');if(result.data?.csrf_token)csrfToken=result.data.csrf_token;return result;
}
function message(text,ok=false){const b=$('fmMessage');b.className='alert fm-message show '+(ok?'alert-success':'alert-danger');b.textContent=text}
function fill(id,items,key,label,first=''){const e=$(id);e.innerHTML=(first?`<option value="">${esc(first)}</option>`:'')+items.map(x=>`<option value="${esc(x[key])}">${esc(x[label])}</option>`).join('')}
function updateStats(s={}){$('statTotal').textContent=money(s.total||0);$('statPaid').textContent=money(s.paid||0);$('statPending').textContent=money(s.pending||0);$('statWaived').textContent=money(s.waived||0)}
function renderFines(data){
 fines=data;$('fineBody').innerHTML=data.map(r=>`<tr><td>${r.row_number}</td><td><strong>${esc(r.student_name)}</strong></td><td>${esc(r.admission_no)}</td><td>${esc(r.year_name)}</td><td>${esc(r.class_name||'-')}</td><td>${esc(r.structure_name)}</td><td>${esc(r.rule_name||'Manual Fine')}</td><td>${esc(r.due_date||'-')}</td><td>${Number(r.days_late||0)}</td><td><strong>${money(r.fine_amount)}</strong></td><td>${badge(r.fine_status)}</td><td><div class="fm-actions">${permissions.waive&&r.fine_status==='pending'?`<button class="fm-action success js-waive" data-id="${r.id}" title="Waive"><i data-lucide="hand-heart"></i></button>`:''}${permissions.delete&&r.fine_status!=='paid'?`<button class="fm-action danger js-delete" data-id="${r.id}" title="Delete"><i data-lucide="trash-2"></i></button>`:''}</div></td></tr>`).join('')||'<tr><td colspan="12" class="fm-empty">No fines found.</td></tr>';
 document.querySelectorAll('.js-waive').forEach(b=>b.onclick=()=>waiveFine(Number(b.dataset.id)));document.querySelectorAll('.js-delete').forEach(b=>b.onclick=()=>deleteFine(Number(b.dataset.id)));window.lucide?.createIcons();
}
function renderRules(data){
 rules=data;$('ruleBody').innerHTML=data.map(r=>`<tr><td><strong>${esc(r.rule_code)}</strong></td><td>${esc(r.rule_name)}</td><td>${esc(r.scope_type)}</td><td>${esc(r.calculation_type)}</td><td>${r.calculation_type==='percentage'?esc(r.fine_value)+'%':money(r.fine_value)}</td><td>${Number(r.grace_days)} days</td><td>${Number(r.maximum_fine)>0?money(r.maximum_fine):'No Limit'}</td><td>${badge(r.status)}</td><td><div class="fm-actions"><button class="fm-action js-rule-edit" data-id="${r.id}"><i data-lucide="pencil"></i></button><button class="fm-action danger js-rule-delete" data-id="${r.id}"><i data-lucide="trash-2"></i></button></div></td></tr>`).join('')||'<tr><td colspan="9" class="fm-empty">No fine rules found.</td></tr>';
 document.querySelectorAll('.js-rule-edit').forEach(b=>b.onclick=()=>openRule(Number(b.dataset.id)));document.querySelectorAll('.js-rule-delete').forEach(b=>b.onclick=()=>deleteRule(Number(b.dataset.id)));window.lucide?.createIcons();
}
function renderHistory(data){$('historyBody').innerHTML=data.map(r=>`<tr><td>${esc(r.created_at)}</td><td>${esc(r.action_name)}</td><td>${esc(r.student_name||'-')}</td><td>${esc(r.rule_name||'Manual Fine')}</td><td>${money(r.amount||0)}</td><td>${esc(r.user_name||'-')}</td><td>${esc(r.remarks||'-')}</td></tr>`).join('')||'<tr><td colspan="7" class="fm-empty">No fine history.</td></tr>'}
function renderPagination(p={}){
 const total=Number(p.total||0),current=Number(p.page||1),per=Number(p.per_page||10),last=Math.max(1,Number(p.last_page||1)),start=total?((current-1)*per)+1:0,end=Math.min(current*per,total);
 $('recordInfo').textContent=`${total} fine${total===1?'':'s'}`;$('pageInfo').textContent=`Showing ${start}-${end} of ${total}`;
 let html=`<button class="fm-page-button" data-page="${current-1}" ${current<=1?'disabled':''}>‹</button>`;for(let x=Math.max(1,current-2);x<=Math.min(last,current+2);x++)html+=`<button class="fm-page-button ${x===current?'active':''}" data-page="${x}">${x}</button>`;html+=`<button class="fm-page-button" data-page="${current+1}" ${current>=last?'disabled':''}>›</button>`;$('pagination').innerHTML=html;document.querySelectorAll('.fm-page-button').forEach(b=>b.onclick=()=>{if(!b.disabled){page=Number(b.dataset.page);load()}});
}
async function load(){
 try{const r=await request('list',{search:$('search').value.trim(),academic_year_id:$('yearFilter').value,class_id:$('classFilter').value,fine_rule_id:$('ruleFilter').value,fine_status:$('statusFilter').value,source_type:$('sourceFilter').value,page,per_page:10});meta=r.data.meta;permissions=r.data.permissions||permissions;renderFines(r.data.records||[]);renderRules(r.data.rules||[]);renderHistory(r.data.history||[]);renderPagination(r.data.pagination||{});updateStats(r.data.stats||{});
 if($('yearFilter').options.length<=1)fill('yearFilter',meta.years,'id','year_name','All Academic Years');if($('classFilter').options.length<=1)fill('classFilter',meta.classes,'id','class_name','All Classes');if($('ruleFilter').options.length<=1)fill('ruleFilter',meta.rules,'id','rule_name','All Fine Rules');
 }catch(e){message(e.message)}
}
function refreshRuleTargets(){
 const year=Number($('ruleYearId').value||0),scope=$('ruleScope').value,oldClass=$('ruleClassId').value;
 fill('ruleClassId',meta.classes.filter(x=>!year||Number(x.academic_year_id)===year),'id','class_name','Select class');if(oldClass&&[...$('ruleClassId').options].some(o=>o.value===oldClass))$('ruleClassId').value=oldClass;
 const cls=Number($('ruleClassId').value||0);fill('ruleStudentId',meta.students.filter(x=>(!year||Number(x.academic_year_id)===year)&&(!cls||Number(x.class_id)===cls)),'id','student_name','Select student');fill('ruleStructureId',meta.structures.filter(x=>(!year||Number(x.academic_year_id)===year)&&(!cls||Number(x.class_id)===cls)),'id','structure_name','All matching structures');
 $('ruleClassId').closest('div').style.display=scope==='class'?'block':'none';$('ruleStudentId').closest('div').style.display=scope==='student'?'block':'none';
}
function openRule(id=0){
 const r=rules.find(x=>Number(x.id)===id);$('ruleId').value=r?.id||'';$('ruleCode').value=r?.rule_code||'';$('ruleName').value=r?.rule_name||'';fill('ruleYearId',meta.years,'id','year_name');$('ruleYearId').value=r?.academic_year_id||meta.years[0]?.id||'';$('ruleScope').value=r?.scope_type||'all';refreshRuleTargets();$('ruleClassId').value=r?.class_id||'';refreshRuleTargets();$('ruleStudentId').value=r?.student_id||'';$('ruleStructureId').value=r?.fee_structure_id||'';$('ruleCalculation').value=r?.calculation_type||'fixed';$('ruleValue').value=r?.fine_value||'';$('graceDays').value=r?.grace_days||0;$('maxFine').value=r?.maximum_fine||0;$('ruleStatus').value=r?.status||'active';$('ruleDescription').value=r?.description||'';$('ruleModalTitle').textContent=r?'Edit Fine Rule':'Add Fine Rule';bootstrap.Modal.getOrCreateInstance($('ruleModal')).show();
}
function refreshFineTargets(){
 const year=Number($('fineYearId').value||0),oldClass=$('fineClassId').value;fill('fineClassId',meta.classes.filter(x=>!year||Number(x.academic_year_id)===year),'id','class_name','Select class');if(oldClass&&[...$('fineClassId').options].some(o=>o.value===oldClass))$('fineClassId').value=oldClass;
 const cls=Number($('fineClassId').value||0);fill('fineStudentId',meta.students.filter(x=>(!year||Number(x.academic_year_id)===year)&&(!cls||Number(x.class_id)===cls)),'id','student_name','Select student');refreshAssignments();
}
function refreshAssignments(){const student=Number($('fineStudentId').value||0),year=Number($('fineYearId').value||0);fill('fineAssignmentId',meta.assignments.filter(x=>(!year||Number(x.academic_year_id)===year)&&(!student||Number(x.student_id)===student)),'id','assignment_name','Select assignment')}
function openManual(){fill('fineYearId',meta.years,'id','year_name');$('fineYearId').value=meta.years[0]?.id||'';refreshFineTargets();$('manualAmount').value='';$('fineDate').value=new Date().toISOString().slice(0,10);$('manualRemarks').value='';bootstrap.Modal.getOrCreateInstance($('fineModal')).show()}
async function waiveFine(id){const reason=prompt('Enter waiver reason:');if(reason===null||reason.trim().length<3){message('Waiver reason must be at least 3 characters.');return}try{const r=await request('waive',{id,reason:reason.trim()},'POST');message(r.message,true);await load()}catch(e){message(e.message)}}
async function deleteFine(id){if(!confirm('Delete this fine and restore the assignment balance?'))return;try{const r=await request('delete_fine',{id},'POST');message(r.message,true);await load()}catch(e){message(e.message)}}
async function deleteRule(id){if(!confirm('Delete this fine rule?'))return;try{const r=await request('delete_rule',{id},'POST');message(r.message,true);await load()}catch(e){message(e.message)}}
$('ruleForm').onsubmit=async e=>{e.preventDefault();try{const r=await request('save_rule',{id:Number($('ruleId').value||0),rule_code:$('ruleCode').value.trim(),rule_name:$('ruleName').value.trim(),academic_year_id:Number($('ruleYearId').value),scope_type:$('ruleScope').value,class_id:Number($('ruleClassId').value||0),student_id:Number($('ruleStudentId').value||0),fee_structure_id:Number($('ruleStructureId').value||0),calculation_type:$('ruleCalculation').value,fine_value:Number($('ruleValue').value||0),grace_days:Number($('graceDays').value||0),maximum_fine:Number($('maxFine').value||0),status:$('ruleStatus').value,description:$('ruleDescription').value.trim()},'POST');bootstrap.Modal.getInstance($('ruleModal'))?.hide();message(r.message,true);await load()}catch(err){message(err.message)}};
$('fineForm').onsubmit=async e=>{e.preventDefault();try{const r=await request('save_manual',{assignment_id:Number($('fineAssignmentId').value),fine_amount:Number($('manualAmount').value||0),fine_date:$('fineDate').value,remarks:$('manualRemarks').value.trim()},'POST');bootstrap.Modal.getInstance($('fineModal'))?.hide();message(r.message,true);await load()}catch(err){message(err.message)}};
document.querySelectorAll('.fm-tab').forEach(b=>b.onclick=()=>{document.querySelectorAll('.fm-tab').forEach(x=>x.classList.remove('active'));document.querySelectorAll('.fm-panel').forEach(x=>x.classList.remove('active'));b.classList.add('active');document.querySelector(`[data-panel="${b.dataset.tab}"]`)?.classList.add('active')});
$('addRuleBtn').onclick=$('ruleAddInside').onclick=()=>openRule();$('manualFineBtn').onclick=openManual;$('calculateBtn').onclick=async()=>{try{const r=await request('calculate',{},'POST');message(r.message,true);await load()}catch(e){message(e.message)}};$('refreshBtn').onclick=load;$('ruleYearId').onchange=refreshRuleTargets;$('ruleClassId').onchange=refreshRuleTargets;$('ruleScope').onchange=refreshRuleTargets;$('fineYearId').onchange=refreshFineTargets;$('fineClassId').onchange=refreshFineTargets;$('fineStudentId').onchange=refreshAssignments;
$('resetBtn').onclick=()=>{$('search').value='';['yearFilter','classFilter','ruleFilter','statusFilter','sourceFilter'].forEach(id=>$(id).value='all');page=1;load()};['yearFilter','classFilter','ruleFilter','statusFilter','sourceFilter'].forEach(id=>$(id).onchange=()=>{page=1;load()});let timer;$('search').oninput=()=>{clearTimeout(timer);timer=setTimeout(()=>{page=1;load()},300)};
document.querySelector('.fee-nav a[data-key="fines"]')?.classList.add('active');load();window.lucide?.createIcons();
})();
</script>

<?php require dirname(__DIR__).'/includes/layout-end.php'; ?>

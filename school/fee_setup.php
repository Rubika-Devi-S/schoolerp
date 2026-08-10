<?php
declare(strict_types=1);
$pageTitle='Fee Setup';
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
.fee-stat-icon{width:50px;height:50px;border-radius:50%;background:rgba(255,255,255,.16);display:grid;place-items:center;flex:0 0 auto}
.fee-stat-icon svg{width:25px;height:25px}
.fee-stat strong{display:block;font-size:25px;line-height:1}
.fee-stat small{display:block;font-size:11px;font-weight:700;opacity:.95;margin-bottom:6px}
.fee-stat .trend{font-size:9px;font-weight:700;opacity:.94;margin-top:8px}
.fee-grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(360px,.85fr);gap:16px;align-items:start}
.fee-card{border-radius:14px;overflow:hidden}
.fee-card-head{padding:14px 16px;border-bottom:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;align-items:center;gap:10px}
.fee-card-head strong{font-size:14px}
.fee-card-actions{display:flex;gap:8px;flex-wrap:wrap}
.fee-filter{padding:14px 16px;display:grid;grid-template-columns:minmax(220px,1.4fr) repeat(3,minmax(140px,.7fr)) auto;gap:10px;border-bottom:1px solid var(--border-soft,#e7ebf3)}
.fee-table-wrap{overflow:auto}
.fee-table{min-width:900px}
.fee-table th{font-size:10px}
.fee-table td{font-size:11px;vertical-align:middle}
.fee-empty{padding:40px 18px!important;text-align:center!important;color:var(--text-muted,#64748b)}
.fee-badge{display:inline-flex;align-items:center;padding:5px 9px;border-radius:999px;font-size:9px;font-weight:800;text-transform:capitalize}
.fee-badge.active,.fee-badge.success{color:#16834f;background:#e8f8ef}
.fee-badge.draft,.fee-badge.pending{color:#b96b00;background:#fff4df}
.fee-badge.inactive{color:#dc2626;background:#fff0f1}
.fee-actions{display:flex;gap:5px;flex-wrap:wrap}
.fee-action{width:30px;height:30px;border:1px solid #d7def1;border-radius:7px;background:var(--card-bg,#fff);display:grid;place-items:center;color:#4f46e5}
.fee-action.danger{color:#dc2626}
.fee-action svg{width:13px;height:13px}
.fee-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.fee-form-grid .full{grid-column:1/-1}
.fee-item{display:grid;grid-template-columns:1.25fr .75fr .9fr .7fr .65fr auto;gap:8px;align-items:end;margin-bottom:8px}
.fee-tabs{display:flex;gap:8px;overflow:auto;padding:10px}
.fee-tab{border:1px solid var(--border-soft,#e7ebf3);background:var(--card-bg,#fff);border-radius:8px;padding:8px 11px;font-size:10px;font-weight:800;white-space:nowrap}
.fee-tab.active{color:#fff;border-color:transparent;background:linear-gradient(135deg,#6547e8,#315ed8)}
.fee-panel{display:none}.fee-panel.active{display:block}
.fee-message{display:none}.fee-message.show{display:block}
#feeModal .modal-dialog,#viewModal .modal-dialog,#importModal .modal-dialog{max-height:calc(100dvh - 32px);margin:16px auto}
#feeModal .modal-content,#viewModal .modal-content,#importModal .modal-content{max-height:calc(100dvh - 32px);overflow:hidden}
#feeModal form,#importModal form{display:flex;flex-direction:column;max-height:calc(100dvh - 32px)}
#feeModal .modal-body,#viewModal .modal-body,#importModal .modal-body{overflow-y:auto;min-height:0}
@media(max-width:1200px){.fee-grid{grid-template-columns:1fr}.fee-filter{grid-template-columns:repeat(2,1fr)}}
@media(max-width:900px){.fee-stats{grid-template-columns:repeat(2,1fr)}.fee-item{grid-template-columns:repeat(2,1fr)}}
@media(max-width:575px){.fee-stats,.fee-filter,.fee-form-grid,.fee-item{grid-template-columns:1fr}.fee-form-grid .full{grid-column:auto}.fee-card-head{align-items:flex-start;flex-direction:column}}
</style>

<div class="fee-page" data-page="setup">
<div class="page-heading">
<div><h1 class="page-title">Fee Setup</h1><p class="page-subtitle">Configure fee heads, class-wise structures, installments, due dates, discounts, scholarships and fine rules.</p></div>
<div class="page-actions">
<button id="importBtn" class="btn-ui" type="button"><i data-lucide="upload"></i> Import</button>
<a id="exportBtn" class="btn-ui"><i data-lucide="download"></i> Export</a>
<button id="refreshAll" class="btn-ui" type="button"><i data-lucide="refresh-cw"></i> Refresh</button>
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
<article class="fee-stat purple"><span class="fee-stat-icon"><i data-lucide="tags"></i></span><div><small>Fee Categories / Heads</small><strong id="statHeads">0</strong><div class="trend">Configured fee components</div></div></article>
<article class="fee-stat blue"><span class="fee-stat-icon"><i data-lucide="list-tree"></i></span><div><small>Fee Structures</small><strong id="statStructures">0</strong><div class="trend">Academic year and class-wise</div></div></article>
<article class="fee-stat orange"><span class="fee-stat-icon"><i data-lucide="calendar-clock"></i></span><div><small>Installments</small><strong id="statInstallments">0</strong><div class="trend">Unique due-date groups</div></div></article>
<article class="fee-stat green"><span class="fee-stat-icon"><i data-lucide="badge-indian-rupee"></i></span><div><small>Configured Amount</small><strong id="statAmount">₹0</strong><div class="trend">Total structure amount</div></div></article>
</section>

<section class="ui-card fee-tabs">
<button class="fee-tab active" data-tab="heads" type="button">Fee Categories & Heads</button>
<button class="fee-tab" data-tab="structures" type="button">Fee Structures</button>
</section>

<section class="fee-panel active" data-panel="heads">
<section class="ui-card fee-card">
<div class="fee-card-head"><strong>Fee Categories / Fee Heads</strong><div class="fee-card-actions"><button id="addHead" class="btn-ui btn-primary-ui"><i data-lucide="plus"></i> Add Fee Head</button></div></div>
<div class="fee-filter">
<input id="headSearch" class="form-control" placeholder="Search code or fee head...">
<select id="headStatus" class="form-select"><option value="all">All Statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select>
<select id="headRefundable" class="form-select"><option value="all">All Types</option><option value="1">Refundable</option><option value="0">Non-refundable</option></select>
<select id="headSort" class="form-select"><option value="name_asc">Name A-Z</option><option value="name_desc">Name Z-A</option><option value="code_asc">Code A-Z</option></select>
<button id="refreshHeads" class="btn-ui" type="button">Refresh</button>
</div>
<div class="fee-table-wrap"><table class="data-table fee-table"><thead><tr><th>Code</th><th>Fee Category / Head</th><th>Refundable</th><th>Status</th><th>Actions</th></tr></thead><tbody id="headBody"></tbody></table></div>
</section>
</section>

<section class="fee-panel" data-panel="structures">
<section class="ui-card fee-card">
<div class="fee-card-head"><strong>Academic Year & Class-wise Fee Structures</strong><div class="fee-card-actions"><button id="addStructure" class="btn-ui btn-primary-ui"><i data-lucide="plus"></i> Add Structure</button></div></div>
<div class="fee-filter">
<input id="structureSearch" class="form-control" placeholder="Search structure...">
<select id="yearFilter" class="form-select"><option value="all">All Academic Years</option></select>
<select id="classFilter" class="form-select"><option value="all">All Classes</option></select>
<select id="structureStatus" class="form-select"><option value="all">All Statuses</option><option value="draft">Draft</option><option value="active">Active</option><option value="inactive">Inactive</option></select>
<button id="refreshStructures" class="btn-ui" type="button">Refresh</button>
</div>
<div class="fee-table-wrap"><table class="data-table fee-table"><thead><tr><th>Structure</th><th>Academic Year</th><th>Class</th><th>Installments</th><th>Total</th><th>Due From</th><th>Due To</th><th>Status</th><th>Actions</th></tr></thead><tbody id="structureBody"></tbody></table></div>
</section>
</section>

<div class="modal fade" id="feeModal" tabindex="-1">
<div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content"><form id="feeForm">
<div class="modal-header"><h5 id="modalTitle" class="modal-title"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body"><input id="recordType" type="hidden"><input id="recordId" type="hidden"><div id="dynamicFields" class="fee-form-grid"></div></div>
<div class="modal-footer"><button type="button" class="btn-ui" data-bs-dismiss="modal">Cancel</button><button id="saveBtn" class="btn-ui btn-primary-ui" type="submit">Save</button></div>
</form></div></div>
</div>

<div class="modal fade" id="viewModal" tabindex="-1">
<div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content">
<div class="modal-header"><div><h5 class="modal-title">Fee Structure Details</h5><small id="viewSubtitle" class="text-muted"></small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body"><div id="viewSummary" class="fee-stats mb-3"></div><div class="fee-table-wrap"><table class="data-table fee-table"><thead><tr><th>Fee Head</th><th>Amount</th><th>Due Date / Installment</th><th>Fine Type</th><th>Fine Value</th></tr></thead><tbody id="viewItems"></tbody></table></div></div>
<div class="modal-footer"><button type="button" class="btn-ui" data-bs-dismiss="modal">Close</button></div>
</div></div>
</div>

<div class="modal fade" id="importModal" tabindex="-1">
<div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><form id="importForm">
<div class="modal-header"><h5 class="modal-title">Import Fee Heads</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body"><div class="alert alert-light">CSV columns: <strong>head_code, head_name, is_refundable, status</strong></div><input id="importFile" class="form-control" type="file" accept=".csv,text/csv" required></div>
<div class="modal-footer"><button type="button" class="btn-ui" data-bs-dismiss="modal">Cancel</button><button class="btn-ui btn-primary-ui" type="submit">Import</button></div>
</form></div></div>
</div>

<script>
(function(){
'use strict';
const apiUrl=new URL('../api/fee-setup.php',window.location.href).href;
let csrfToken=<?=json_encode($feeCsrf)?>;
let meta={years:[],classes:[],heads:[]};
let heads=[],structures=[];
const $=id=>document.getElementById(id);
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
const money=v=>new Intl.NumberFormat('en-IN',{style:'currency',currency:'INR',maximumFractionDigits:2}).format(Number(v||0));
const badge=v=>`<span class="fee-badge ${esc(String(v||'').toLowerCase())}">${esc(v||'-')}</span>`;
const modal=()=>bootstrap.Modal.getOrCreateInstance($('feeModal'));
const viewModal=()=>bootstrap.Modal.getOrCreateInstance($('viewModal'));
const importModal=()=>bootstrap.Modal.getOrCreateInstance($('importModal'));

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
 try{result=JSON.parse(text)}catch{throw new Error(`Fee Setup API returned HTTP ${response.status}. Invalid server response.`)}
 if(!response.ok||!result.success)throw new Error(result.message||'Request failed.');
 if(result.data?.csrf_token)csrfToken=result.data.csrf_token;
 return result;
}
function message(text,ok=false){const box=$('feeMessage');box.className='alert fee-message show '+(ok?'alert-success':'alert-danger');box.textContent=text}
function fill(id,rows,key,label){const el=$(id);const first=el.options[0]?.outerHTML||'';el.innerHTML=first+rows.map(r=>`<option value="${esc(r[key])}">${esc(r[label])}</option>`).join('')}

function updateStats(stats={}){
 $('statHeads').textContent=Number(stats.heads||0).toLocaleString('en-IN');
 $('statStructures').textContent=Number(stats.structures||0).toLocaleString('en-IN');
 $('statInstallments').textContent=Number(stats.installments||0).toLocaleString('en-IN');
 $('statAmount').textContent=money(stats.amount||0);
}
function renderHeads(rows){
 heads=rows;
 $('headBody').innerHTML=rows.map(x=>`<tr>
 <td><strong>${esc(x.head_code)}</strong></td><td>${esc(x.head_name)}</td><td>${Number(x.is_refundable)?'Yes':'No'}</td><td>${badge(x.status)}</td>
 <td><div class="fee-actions"><button class="fee-action js-view-head" data-id="${x.id}" title="View"><i data-lucide="eye"></i></button><button class="fee-action js-edit-head" data-id="${x.id}" title="Edit"><i data-lucide="pencil"></i></button><button class="fee-action danger js-delete-head" data-id="${x.id}" title="Delete"><i data-lucide="trash-2"></i></button></div></td>
 </tr>`).join('')||'<tr><td colspan="5" class="fee-empty">No fee heads.</td></tr>';
 document.querySelectorAll('.js-view-head').forEach(b=>b.onclick=()=>openHead(heads.find(x=>Number(x.id)===Number(b.dataset.id)),true));
 document.querySelectorAll('.js-edit-head').forEach(b=>b.onclick=()=>openHead(heads.find(x=>Number(x.id)===Number(b.dataset.id))));
 document.querySelectorAll('.js-delete-head').forEach(b=>b.onclick=()=>removeRecord('head',Number(b.dataset.id)));
 window.lucide?.createIcons();
}
function renderStructures(rows){
 structures=rows;
 $('structureBody').innerHTML=rows.map(x=>`<tr>
 <td><strong>${esc(x.structure_name)}</strong></td><td>${esc(x.year_name)}</td><td>${esc(x.class_name)}</td><td>${esc(x.installment_count)}</td><td>${money(x.total_amount)}</td><td>${esc(x.first_due_date||'-')}</td><td>${esc(x.last_due_date||'-')}</td><td>${badge(x.status)}</td>
 <td><div class="fee-actions"><button class="fee-action js-view-structure" data-id="${x.id}" title="View"><i data-lucide="eye"></i></button><button class="fee-action js-edit-structure" data-id="${x.id}" title="Edit"><i data-lucide="pencil"></i></button><button class="fee-action danger js-delete-structure" data-id="${x.id}" title="Delete"><i data-lucide="trash-2"></i></button></div></td>
 </tr>`).join('')||'<tr><td colspan="9" class="fee-empty">No fee structures.</td></tr>';
 document.querySelectorAll('.js-view-structure').forEach(b=>b.onclick=()=>viewStructure(Number(b.dataset.id)));
 document.querySelectorAll('.js-edit-structure').forEach(b=>b.onclick=()=>openStructure(structures.find(x=>Number(x.id)===Number(b.dataset.id))));
 document.querySelectorAll('.js-delete-structure').forEach(b=>b.onclick=()=>removeRecord('structure',Number(b.dataset.id)));
 window.lucide?.createIcons();
}
async function load(){
 try{
  const response=await request('list',{
   head_search:$('headSearch').value.trim(),head_status:$('headStatus').value,head_refundable:$('headRefundable').value,head_sort:$('headSort').value,
   structure_search:$('structureSearch').value.trim(),academic_year_id:$('yearFilter').value,class_id:$('classFilter').value,structure_status:$('structureStatus').value
  });
  meta=response.data.meta;renderHeads(response.data.heads||[]);renderStructures(response.data.structures||[]);updateStats(response.data.stats||{});
  fill('yearFilter',meta.years||[],'id','year_name');fill('classFilter',meta.classes||[],'id','class_name');
  $('exportBtn').href=apiUrl+'?action=export&'+new URLSearchParams({academic_year_id:$('yearFilter').value,class_id:$('classFilter').value,structure_status:$('structureStatus').value});
 }catch(error){message(error.message)}
}
function openHead(x=null,viewOnly=false){
 $('recordType').value='head';$('recordId').value=x?.id||'';$('modalTitle').textContent=viewOnly?'View Fee Head':(x?'Edit Fee Head':'Add Fee Head');
 $('dynamicFields').innerHTML=`<div><label class="form-label">Head Code *</label><input id="headCode" class="form-control" required maxlength="40" value="${esc(x?.head_code||'')}" ${viewOnly?'readonly':''}></div><div><label class="form-label">Fee Category / Head Name *</label><input id="headName" class="form-control" required maxlength="120" value="${esc(x?.head_name||'')}" ${viewOnly?'readonly':''}></div><div><label class="form-label">Refundable</label><select id="refundable" class="form-select" ${viewOnly?'disabled':''}><option value="0">No</option><option value="1" ${Number(x?.is_refundable)?'selected':''}>Yes</option></select></div><div><label class="form-label">Status</label><select id="status" class="form-select" ${viewOnly?'disabled':''}><option value="active">Active</option><option value="inactive" ${x?.status==='inactive'?'selected':''}>Inactive</option></select></div>`;
 $('saveBtn').style.display=viewOnly?'none':'inline-flex';modal().show();
}
function itemRow(i={}){
 return `<div class="fee-item structure-item">
 <div><label class="form-label">Fee Head</label><select class="form-select item-head">${meta.heads.map(h=>`<option value="${h.id}" ${Number(i.fee_head_id)===Number(h.id)?'selected':''}>${esc(h.head_name)}</option>`).join('')}</select></div>
 <div><label class="form-label">Amount</label><input class="form-control item-amount" type="number" min="0.01" step="0.01" value="${esc(i.amount||'')}"></div>
 <div><label class="form-label">Due Date / Installment</label><input class="form-control item-date" type="date" value="${esc(i.due_date||'')}"></div>
 <div><label class="form-label">Fine Type</label><select class="form-select item-fine-type"><option value="none">None</option><option value="fixed" ${i.fine_type==='fixed'?'selected':''}>Fixed</option><option value="daily" ${i.fine_type==='daily'?'selected':''}>Daily</option></select></div>
 <div><label class="form-label">Fine Value</label><input class="form-control item-fine-value" type="number" min="0" step="0.01" value="${esc(i.fine_value||0)}"></div>
 <button class="fee-action danger remove-item" type="button"><i data-lucide="trash-2"></i></button></div>`;
}
function openStructure(x=null){
 $('recordType').value='structure';$('recordId').value=x?.id||'';$('modalTitle').textContent=x?'Edit Fee Structure':'Add Fee Structure';$('saveBtn').style.display='inline-flex';
 $('dynamicFields').innerHTML=`<div><label class="form-label">Academic Year *</label><select id="yearId" class="form-select" required>${meta.years.map(y=>`<option value="${y.id}" ${Number(x?.academic_year_id)===Number(y.id)?'selected':''}>${esc(y.year_name)}</option>`).join('')}</select></div><div><label class="form-label">Class *</label><select id="classId" class="form-select" required>${meta.classes.map(c=>`<option value="${c.id}" ${Number(x?.class_id)===Number(c.id)?'selected':''}>${esc(c.class_name)}</option>`).join('')}</select></div><div class="full"><label class="form-label">Structure Name *</label><input id="structureName" class="form-control" required maxlength="150" value="${esc(x?.structure_name||'')}"></div><div><label class="form-label">Status</label><select id="status" class="form-select"><option value="draft">Draft</option><option value="active" ${x?.status==='active'?'selected':''}>Active</option><option value="inactive" ${x?.status==='inactive'?'selected':''}>Inactive</option></select></div><div class="full"><div class="alert alert-light">Each due date represents an installment. Fine rules are configured per fee item. Discounts and scholarships are applied later during student fee assignment.</div><div id="itemsWrap">${(x?.items?.length?x.items:[{}]).map(itemRow).join('')}</div><button id="addItem" class="btn-ui mt-2" type="button"><i data-lucide="plus"></i> Add Item / Installment</button></div>`;
 $('addItem').onclick=()=>{ $('itemsWrap').insertAdjacentHTML('beforeend',itemRow({}));bindRemoveItems();window.lucide?.createIcons() };
 bindRemoveItems();modal().show();window.lucide?.createIcons();
}
function bindRemoveItems(){document.querySelectorAll('.remove-item').forEach(button=>button.onclick=()=>button.closest('.structure-item')?.remove())}
async function viewStructure(id){
 try{
  const response=await request('view',{id});const x=response.data.structure;
  $('viewSubtitle').textContent=`${x.structure_name} • ${x.year_name} • ${x.class_name}`;
  $('viewSummary').innerHTML=`<article class="fee-stat purple"><div><small>Total Amount</small><strong>${money(x.total_amount)}</strong></div></article><article class="fee-stat blue"><div><small>Installments</small><strong>${esc(x.installment_count)}</strong></div></article><article class="fee-stat orange"><div><small>First Due Date</small><strong>${esc(x.first_due_date||'-')}</strong></div></article><article class="fee-stat green"><div><small>Status</small><strong>${esc(x.status)}</strong></div></article>`;
  $('viewItems').innerHTML=(x.items||[]).map(i=>`<tr><td>${esc(i.head_name)}</td><td>${money(i.amount)}</td><td>${esc(i.due_date||'-')}</td><td>${esc(i.fine_type)}</td><td>${money(i.fine_value)}</td></tr>`).join('')||'<tr><td colspan="5" class="fee-empty">No items.</td></tr>';
  viewModal().show();
 }catch(error){message(error.message)}
}
async function removeRecord(type,id){
 if(!confirm(`Delete this fee ${type}?`))return;
 try{const response=await request('delete',{type,id},'POST');message(response.message,true);await load()}catch(error){message(error.message)}
}
$('feeForm').onsubmit=async event=>{
 event.preventDefault();
 try{
  const type=$('recordType').value;const data={id:Number($('recordId').value||0),type};
  if(type==='head'){
   Object.assign(data,{head_code:$('headCode').value.trim(),head_name:$('headName').value.trim(),is_refundable:Number($('refundable').value),status:$('status').value});
  }else{
   Object.assign(data,{academic_year_id:Number($('yearId').value),class_id:Number($('classId').value),structure_name:$('structureName').value.trim(),status:$('status').value,items:[...document.querySelectorAll('.structure-item')].map(row=>({fee_head_id:Number(row.querySelector('.item-head').value),amount:Number(row.querySelector('.item-amount').value||0),due_date:row.querySelector('.item-date').value,fine_type:row.querySelector('.item-fine-type').value,fine_value:Number(row.querySelector('.item-fine-value').value||0)}))});
  }
  const response=await request('save',data,'POST');modal().hide();message(response.message,true);await load();
 }catch(error){message(error.message)}
};
$('importForm').onsubmit=async event=>{
 event.preventDefault();const file=$('importFile').files[0];if(!file)return;
 const csv=await file.text();
 try{const response=await request('import',{csv},'POST');importModal().hide();message(response.message,true);await load()}catch(error){message(error.message)}
};
document.querySelectorAll('.fee-tab').forEach(button=>button.onclick=()=>{document.querySelectorAll('.fee-tab').forEach(t=>t.classList.remove('active'));document.querySelectorAll('.fee-panel').forEach(p=>p.classList.remove('active'));button.classList.add('active');document.querySelector(`[data-panel="${button.dataset.tab}"]`)?.classList.add('active')});
$('addHead').onclick=()=>openHead();$('addStructure').onclick=()=>openStructure();$('importBtn').onclick=()=>importModal().show();
$('refreshAll').onclick=$('refreshHeads').onclick=$('refreshStructures').onclick=load;
['headStatus','headRefundable','headSort','yearFilter','classFilter','structureStatus'].forEach(id=>$(id).addEventListener('change',load));
let timer;['headSearch','structureSearch'].forEach(id=>$(id).addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(load,300)}));
document.querySelector('.fee-nav a[data-key="setup"]')?.classList.add('active');
load();window.lucide?.createIcons();
})();
</script>
<?php require dirname(__DIR__).'/includes/layout-end.php'; ?>

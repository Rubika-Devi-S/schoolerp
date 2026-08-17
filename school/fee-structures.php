<?php
declare(strict_types=1);

/* Build: 2026-08-17-fee-structures-strict-branch-ui-v7 */

$pageTitle='Fee Structures';
$pageKey='fee_management';

require dirname(__DIR__).'/includes/layout-start.php';

if(session_status()!==PHP_SESSION_ACTIVE){session_start();}
if(empty($_SESSION['fee_csrf_token'])||!is_string($_SESSION['fee_csrf_token'])){
    $_SESSION['fee_csrf_token']=bin2hex(random_bytes(32));
}
$feeCsrf=$_SESSION['fee_csrf_token'];
?>
<style>
.fee-page{display:grid;gap:16px}
.fee-page .page-title{font-size:28px;line-height:1.1}
.fee-page .page-subtitle{margin-top:4px}
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
.fee-card{border-radius:14px;overflow:hidden}
.fee-card-head{padding:14px 16px;border-bottom:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;align-items:center;gap:10px}
.fee-card-head strong{font-size:14px}
.fee-filter{padding:14px 16px;display:grid;grid-template-columns:minmax(240px,1.4fr) repeat(4,minmax(140px,.7fr)) auto;gap:10px;border-bottom:1px solid var(--border-soft,#e7ebf3)}
.fee-table-wrap{overflow:auto}
.fee-table{min-width:1120px}
.fee-table th{font-size:10px}
.fee-table td{font-size:11px;vertical-align:middle}
.fee-empty{padding:40px 18px!important;text-align:center!important;color:var(--text-muted,#64748b)}
.fee-badge{display:inline-flex;align-items:center;padding:5px 9px;border-radius:999px;font-size:9px;font-weight:800;text-transform:capitalize}
.fee-badge.active{color:#16834f;background:#e8f8ef}
.fee-badge.inactive{color:#dc2626;background:#fff0f1}
.fee-actions{display:flex;gap:5px;flex-wrap:wrap}
.fee-action{width:30px;height:30px;border:1px solid #d7def1;border-radius:7px;background:var(--card-bg,#fff);display:grid;place-items:center;color:#4f46e5}
.fee-action.danger{color:#dc2626}
.fee-action svg{width:13px;height:13px}
.fee-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.fee-form-grid .full{grid-column:1/-1}
.fee-message{display:none}.fee-message.show{display:block}
.fee-pagination{padding:14px 16px;border-top:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;align-items:center;gap:10px}
.fee-page-buttons{display:flex;gap:6px;flex-wrap:wrap}
.fee-page-button{min-width:34px;height:34px;border:1px solid var(--border-soft,#e7ebf3);background:var(--card-bg,#fff);border-radius:8px;font-size:11px;font-weight:800}
.fee-page-button.active{color:#fff;border-color:transparent;background:linear-gradient(135deg,#6547e8,#315ed8)}
.fee-page-button:disabled{opacity:.45;cursor:not-allowed}
.fee-summary-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
.fee-summary-item{padding:12px;border:1px solid var(--border-soft,#e7ebf3);border-radius:10px}
.fee-summary-item small{display:block;color:var(--text-muted,#64748b);font-size:9px;font-weight:700}
.fee-summary-item strong{display:block;margin-top:4px;font-size:14px}
.fee-types-toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:10px}
.fee-types-toolbar-copy{display:grid;gap:2px}
.fee-types-toolbar-copy strong{font-size:13px}
.fee-types-toolbar-copy small{font-size:10px;color:var(--text-muted,#64748b)}
.add-fee-btn{white-space:nowrap;min-height:36px;padding:8px 13px}
.dynamic-fees{display:grid;gap:10px}
.dynamic-fee-row{display:grid;grid-template-columns:minmax(230px,1.25fr) minmax(150px,.65fr) minmax(130px,.55fr) minmax(130px,.55fr) 38px;gap:10px;align-items:end;padding:12px;border:1px solid var(--border-soft,#e7ebf3);border-radius:11px;background:var(--card-bg,#fff)}
.dynamic-fee-row.is-mandatory{border-color:rgba(79,70,229,.28);background:linear-gradient(180deg,rgba(79,70,229,.035),var(--card-bg,#fff))}
.dynamic-fee-name strong{display:flex;align-items:center;gap:7px;font-size:12px}
.dynamic-fee-name small{display:block;color:var(--text-muted,#64748b);margin-top:3px;font-size:10px}
.dynamic-required{color:#dc2626}
.dynamic-fee-meta{font-size:10px;color:var(--text-muted,#64748b)}
.dynamic-fee-chip{display:inline-flex;align-items:center;padding:3px 7px;border-radius:999px;background:#eef2ff;color:#4f46e5;font-size:8px;font-weight:800;letter-spacing:.02em}
.dynamic-fee-remove{width:34px;height:34px;border:1px solid #fecaca;border-radius:8px;background:#fff5f5;color:#dc2626;display:grid;place-items:center;transition:.18s ease}
.dynamic-fee-remove:hover{background:#fee2e2;border-color:#fca5a5;transform:translateY(-1px)}
.dynamic-fee-remove svg{width:14px;height:14px}
.dynamic-fee-lock{width:34px;height:34px;border:1px solid var(--border-soft,#e7ebf3);border-radius:8px;background:var(--body-bg,#f8fafc);color:var(--text-muted,#64748b);display:grid;place-items:center}
.dynamic-fee-lock svg{width:14px;height:14px}
.fee-picker-search{position:relative;margin-bottom:12px}
.fee-picker-search svg{position:absolute;left:12px;top:50%;width:16px;height:16px;transform:translateY(-50%);color:#94a3b8;pointer-events:none}
.fee-picker-search input{padding-left:38px}
.fee-picker-list{display:grid;gap:8px;max-height:420px;overflow:auto;padding-right:3px}
.fee-picker-option{display:grid;grid-template-columns:24px minmax(0,1fr) auto;gap:10px;align-items:center;padding:12px;border:1px solid var(--border-soft,#e7ebf3);border-radius:11px;background:var(--card-bg,#fff);cursor:pointer;transition:.18s ease}
.fee-picker-option:hover{border-color:#a5b4fc;background:rgba(79,70,229,.035)}
.fee-picker-option.is-selected{border-color:#818cf8;background:rgba(79,70,229,.07)}
.fee-picker-option input{width:17px;height:17px}
.fee-picker-option strong{display:block;font-size:12px}
.fee-picker-option small{display:block;margin-top:3px;color:var(--text-muted,#64748b);font-size:10px}
.fee-picker-default{font-size:9px;font-weight:800;color:#4f46e5;background:#eef2ff;border-radius:999px;padding:5px 8px;white-space:nowrap}
.fee-picker-empty{padding:28px 15px;text-align:center;color:var(--text-muted,#64748b);border:1px dashed var(--border-soft,#dbe2ee);border-radius:11px}
#feePickerModal .modal-dialog{max-width:640px}
#structureModal .modal-dialog,#viewModal .modal-dialog{max-height:calc(100dvh - 32px);margin:16px auto}
#structureModal .modal-content,#viewModal .modal-content{max-height:calc(100dvh - 32px);overflow:hidden}
#structureModal form{display:flex;flex-direction:column;max-height:calc(100dvh - 32px)}
#structureModal .modal-body,#viewModal .modal-body{overflow-y:auto;min-height:0}
@media(max-width:1200px){.fee-filter{grid-template-columns:repeat(2,1fr)}}
@media(max-width:900px){.fee-stats,.fee-summary-grid{grid-template-columns:repeat(2,1fr)}.dynamic-fee-row{grid-template-columns:repeat(2,1fr)}.dynamic-fee-remove,.dynamic-fee-lock{grid-column:2;justify-self:end}}
@media(max-width:575px){.fee-stats,.fee-filter,.fee-form-grid,.fee-summary-grid,.dynamic-fee-row{grid-template-columns:1fr}.fee-form-grid .full{grid-column:auto}.fee-card-head,.fee-pagination,.fee-types-toolbar{align-items:flex-start;flex-direction:column}.add-fee-btn{width:100%;justify-content:center}.dynamic-fee-remove,.dynamic-fee-lock{grid-column:1;justify-self:end}.fee-picker-option{grid-template-columns:24px minmax(0,1fr)}.fee-picker-default{grid-column:2;justify-self:start}}
</style>

<div class="fee-page">
<div class="page-heading">
 <div><h1 class="page-title">Fee Structures</h1><p class="page-subtitle">Create class-wise structures using Classes Management and enabled fee types from Fees Settings.</p></div>
 <div class="page-actions">
  <a class="btn-ui" href="Fees-settings.php"><i data-lucide="settings-2"></i> Fees Settings</a>
  <button id="extraFeesBtn" class="btn-ui" type="button"><i data-lucide="badge-plus"></i> Extra Fees</button>
  <button id="addStructureBtn" class="btn-ui btn-primary-ui" type="button"><i data-lucide="plus"></i> Add Structure</button>
  <button id="refreshBtn" class="btn-ui" type="button"><i data-lucide="refresh-cw"></i> Refresh</button>
 </div>
</div>

<section class="fee-stats">
 <article class="fee-stat purple"><span class="fee-stat-icon"><i data-lucide="list-tree"></i></span><div><small>Total Structures</small><strong id="statTotal">0</strong><div class="trend">All configured structures</div></div></article>
 <article class="fee-stat green"><span class="fee-stat-icon"><i data-lucide="circle-check-big"></i></span><div><small>Active Structures</small><strong id="statActive">0</strong><div class="trend">Available for assignment</div></div></article>
 <article class="fee-stat orange"><span class="fee-stat-icon"><i data-lucide="school"></i></span><div><small>Classes Covered</small><strong id="statClasses">0</strong><div class="trend">Academic year and class plans</div></div></article>
 <article class="fee-stat blue"><span class="fee-stat-icon"><i data-lucide="badge-indian-rupee"></i></span><div><small>Annual Fee Value</small><strong id="statAmount">₹0</strong><div class="trend">Dynamic annual structure value</div></div></article>
</section>

<div id="feeMessage" class="alert fee-message"></div>

<section class="ui-card fee-card">
 <div class="fee-card-head"><strong>Fee Structure List</strong><small id="recordInfo" class="text-muted">Loading...</small></div>
 <div class="fee-filter">
  <input id="search" class="form-control" placeholder="Search structure or class...">
  <select id="yearFilter" class="form-select"><option value="all">All Academic Years</option></select>
  <select id="classFilter" class="form-select"><option value="all">All Classes</option></select>
  <select id="statusFilter" class="form-select"><option value="all">All Statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select>
  <select id="sortFilter" class="form-select"><option value="newest">Newest First</option><option value="class_asc">Class Order</option><option value="amount_desc">Amount High-Low</option><option value="amount_asc">Amount Low-High</option></select>
  <button id="resetBtn" class="btn-ui" type="button"><i data-lucide="rotate-ccw"></i> Reset</button>
 </div>
 <div class="fee-table-wrap"><table class="data-table fee-table"><thead><tr><th>Academic Year</th><th>Class</th><th>Fee Structure</th><th>Fee Types</th><th>Annual Total</th><th>Status</th><th>Action</th></tr></thead><tbody id="structureBody"><tr><td colspan="7" class="fee-empty">Loading...</td></tr></tbody></table></div>
 <div class="fee-pagination"><small id="pageInfo" class="text-muted"></small><div id="pagination" class="fee-page-buttons"></div></div>
</section>
</div>

<div class="modal fade" id="structureModal" tabindex="-1">
<div class="modal-dialog modal-xl modal-dialog-centered">
<div class="modal-content">
<form id="structureForm" novalidate>
 <div class="modal-header"><div><h5 id="structureModalTitle" class="modal-title">Add Fee Structure</h5><small class="text-muted">Shows enabled fee types from Fees Settings. Frequency supports Monthly, Quarterly, Half-Yearly, Yearly and custom schedules.</small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
 <div class="modal-body">
  <input id="structureId" type="hidden">
  <div class="fee-form-grid mb-3">
   <div><label class="form-label">Academic Year *</label><select id="academicYearId" class="form-select" required></select></div>
   <div><label class="form-label">Class *</label><select id="classId" class="form-select" required></select></div>
   <div class="full"><label class="form-label">Fee Structure Name <span class="text-muted">(Optional)</span></label><input id="structureName" class="form-control" maxlength="150" placeholder="Automatically generated when left blank"></div>
   <div><label class="form-label">Status *</label><select id="structureStatus" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
  </div>
  <div class="fee-types-toolbar">
   <div class="fee-types-toolbar-copy">
    <strong>Selected Fee Types</strong>
    <small>Tuition Fee and Admission Fee are added automatically. You can add, remove or update Fee Types even after student assignment; existing paid history is preserved.</small>
   </div>
   <button id="addFeeBtn" class="btn-ui btn-primary-ui add-fee-btn" type="button">
    <i data-lucide="circle-plus"></i> Add Fee
   </button>
  </div>
  <div id="dynamicFeeTypes" class="dynamic-fees"><div class="fee-empty">Loading selected fee types...</div></div>
  <div class="alert alert-light mt-3 mb-0 d-flex justify-content-between align-items-center"><span>Estimated Annual Total</span><strong id="structureTotal">₹0</strong></div>
  <small id="totalFormula" class="text-muted d-block mt-2"></small>
 </div>
 <div class="modal-footer"><button class="btn-ui" type="button" data-bs-dismiss="modal">Cancel</button><button id="saveStructureBtn" class="btn-ui btn-primary-ui" type="submit"><i data-lucide="save"></i> Save Structure</button></div>
</form>
</div>
</div>
</div>

<div class="modal fade" id="feePickerModal" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">
 <div class="modal-header">
  <div>
   <h5 class="modal-title">Add Fee</h5>
   <small class="text-muted">Choose enabled Fee Structure options configured in Fees Settings.</small>
  </div>
  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
 </div>
 <div class="modal-body">
  <div class="fee-picker-search">
   <i data-lucide="search"></i>
   <input id="feePickerSearch" class="form-control" type="search" placeholder="Search fee name or code...">
  </div>
  <div id="feePickerList" class="fee-picker-list"></div>
 </div>
 <div class="modal-footer">
  <button class="btn-ui" type="button" data-bs-dismiss="modal">Cancel</button>
  <button id="addSelectedFeesBtn" class="btn-ui btn-primary-ui" type="button">
   <i data-lucide="plus"></i> Add Selected Fees
  </button>
 </div>
</div>
</div>
</div>

<div class="modal fade" id="viewModal" tabindex="-1">
<div class="modal-dialog modal-xl modal-dialog-centered">
<div class="modal-content">
 <div class="modal-header"><div><h5 class="modal-title">Fee Structure Details</h5><small id="viewSubtitle" class="text-muted"></small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
 <div class="modal-body">
  <div id="viewSummary" class="fee-summary-grid mb-3"></div>
  <div class="fee-table-wrap"><table class="data-table fee-table"><thead><tr><th>Fee Type</th><th>Amount</th><th>Frequency</th><th>Occurrences</th><th>Annual Total</th></tr></thead><tbody id="viewItems"></tbody></table></div>
 </div>
 <div class="modal-footer"><button class="btn-ui" type="button" data-bs-dismiss="modal">Close</button></div>
</div>
</div>
</div>


<div class="modal fade" id="extraFeeModal" tabindex="-1">
<div class="modal-dialog modal-xl modal-dialog-centered">
<div class="modal-content">
<form id="extraFeeForm" novalidate>
 <div class="modal-header">
  <div><h5 class="modal-title">Create Extra Fee</h5><small class="text-muted">Assigned only to students who already match the selected filters.</small></div>
  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
 </div>
 <div class="modal-body">
  <div class="fee-form-grid">
   <div><label class="form-label">Extra Fee Name *</label><select id="extraFeeTypeId" class="form-select" required><option value="">Select Extra Fee</option></select><small id="extraFeeTypeHint" class="text-muted d-block mt-1">Shows only enabled fee types configured for Extra Fees or Both.</small></div>
   <div><label class="form-label">Amount *</label><input id="extraFeeAmount" class="form-control" type="number" min="0.01" step="0.01" required></div>
   <div><label class="form-label">Academic Year *</label><select id="extraYearId" class="form-select" required></select></div>
   <div><label class="form-label">Class *</label><select id="extraClassId" class="form-select" required></select></div>
   <div><label class="form-label">Section</label><select id="extraSectionId" class="form-select"><option value="">All Sections</option></select></div>
   <div><label class="form-label">Gender</label><select id="extraGender" class="form-select"><option value="all">All Genders</option><option value="male">Male</option><option value="female">Female</option></select></div>
   <div><label class="form-label">Due Date *</label><input id="extraDueDate" class="form-control" type="date" required></div>
   <div class="full"><label class="form-label">Description</label><textarea id="extraDescription" class="form-control" rows="3" maxlength="500"></textarea></div>
  </div>
  <div class="alert alert-info mt-3 mb-0">
   This fee is assigned only to existing matching students when you click Save. It is never added automatically during future admissions.
  </div>
 </div>
 <div class="modal-footer">
  <button class="btn-ui" type="button" data-bs-dismiss="modal">Cancel</button>
  <button id="saveExtraFeeBtn" class="btn-ui btn-primary-ui" type="submit"><i data-lucide="save"></i> Save &amp; Assign</button>
 </div>
</form>
</div>
</div>
</div>

<script>
(function(){
'use strict';
const apiUrl=new URL('../api/fee-structures.php',window.location.href).href;
let csrfToken=<?=json_encode($feeCsrf)?>;
let meta={years:[],classes:[],sections:[],fee_types:[],structure_fee_types:[],extra_fee_types:[]};
let records=[];
let currentPage=1;
let searchTimer=null;
const $=id=>document.getElementById(id);
const esc=value=>String(value??'').replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
const money=value=>new Intl.NumberFormat('en-IN',{style:'currency',currency:'INR',maximumFractionDigits:2}).format(Number(value||0));
const badge=value=>`<span class="fee-badge ${esc(String(value||'').toLowerCase())}">${esc(value||'-')}</span>`;
const structureModal=()=>bootstrap.Modal.getOrCreateInstance($('structureModal'));
const feePickerModal=()=>bootstrap.Modal.getOrCreateInstance($('feePickerModal'));
const viewModal=()=>bootstrap.Modal.getOrCreateInstance($('viewModal'));
const extraFeeModal=()=>bootstrap.Modal.getOrCreateInstance($('extraFeeModal'));

async function request(action,data={},method='GET'){
 let response;
 if(method==='GET'){
  const url=new URL(apiUrl);url.searchParams.set('action',action);
  Object.entries(data).forEach(([key,value])=>{if(value!==''&&value!==null&&value!==undefined)url.searchParams.set(key,String(value));});
  response=await fetch(url,{headers:{Accept:'application/json'},credentials:'same-origin',cache:'no-store'});
 }else{
  response=await fetch(apiUrl,{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json'},credentials:'same-origin',cache:'no-store',body:JSON.stringify({action,csrf_token:csrfToken,...data})});
 }
 const text=await response.text();let result;
 try{result=JSON.parse(text)}catch{throw new Error(`Fee Structures API returned HTTP ${response.status}. ${text.replace(/\s+/g,' ').trim().slice(0,220)||'Invalid server response.'}`)}
 if(!response.ok||!result.success)throw new Error(result.message||'Request failed.');
 if(result.data?.csrf_token)csrfToken=result.data.csrf_token;
 return result;
}
function message(text,success=false){if(typeof window.showToast==='function'){window.showToast(success?'success':'warning',text);return}const box=$('feeMessage');box.className='alert fee-message show '+(success?'alert-success':'alert-danger');box.textContent=text;clearTimeout(box._timer);box._timer=setTimeout(()=>box.className='alert fee-message',6500)}
function fill(id,rows,key,label,first='',firstValue=''){const element=$(id);element.innerHTML=(first?`<option value="${esc(firstValue)}">${esc(first)}</option>`:'')+rows.map(row=>`<option value="${esc(row[key])}">${esc(typeof label==='function'?label(row):row[label])}</option>`).join('')}
function activeYear(){return (meta.years||[]).find(row=>Number(row.is_current)===1)||(meta.years||[])[0]||null}
async function refreshFeeSettingsMeta(){
 const result=await request('meta',{_ts:Date.now()});
 meta=result.data.meta||meta;
 return meta;
}
function refreshFormClasses(selected=''){const yearId=Number($('academicYearId').value||0);const rows=(meta.classes||[]).filter(row=>Number(row.academic_year_id)===yearId).sort((a,b)=>Number(a.display_order||0)-Number(b.display_order||0)||String(a.class_name||'').localeCompare(String(b.class_name||'')));fill('classId',rows,'id','class_name',rows.length?'Select Class':'No Classes Available','');$('classId').disabled=!rows.length;if(selected&&[...$('classId').options].some(option=>option.value===String(selected)))$('classId').value=String(selected)}
function refreshFilterClasses(){const yearValue=$('yearFilter').value;const old=$('classFilter').value;const yearId=yearValue==='all'?0:Number(yearValue||0);const rows=(meta.classes||[]).filter(row=>!yearId||Number(row.academic_year_id)===yearId);fill('classFilter',rows,'id','class_name','All Classes','all');if(old&&[...$('classFilter').options].some(option=>option.value===String(old)))$('classFilter').value=String(old)}
function frequencyLabel(value){return ({one_time:'One Time',monthly:'Monthly',term:'Term-wise',quarterly:'Quarterly',half_yearly:'Half-Yearly',yearly:'Yearly',annual:'Annual',custom:'Custom'})[value]||value}
function normalizedOccurrences(frequency,current=1,defaultValue=1){
 const value=Math.max(1,Number(current||defaultValue||1));
 const fixed={
  one_time:1,
  monthly:12,
  quarterly:4,
  half_yearly:2,
  yearly:1,
  annual:1
 };
 if(Object.prototype.hasOwnProperty.call(fixed,frequency))return fixed[frequency];
 return value;
}
function structureFeeTypes(){
 return Array.isArray(meta.structure_fee_types)
  ?meta.structure_fee_types
  :(Array.isArray(meta.fee_types)?meta.fee_types:[]);
}
function feeTypeById(id){return structureFeeTypes().find(type=>Number(type.id)===Number(id))||null}
function currentSelectedFeeIds(){return new Set([...document.querySelectorAll('.dynamic-fee-row[data-saveable="1"]')].map(row=>Number(row.dataset.feeType)).filter(Boolean))}
function feeRowMarkup(type,existing={}){
 const mandatory=Number(type.is_mandatory||0)===1;
 const amount=Number(existing.amount||0);
 const frequency=String(existing.frequency||type.default_frequency||'one_time');
 const occurrences=normalizedOccurrences(
  frequency,
  existing.occurrence_count,
  type.default_occurrence_count
 );
 return `<div class="dynamic-fee-row ${mandatory?'is-mandatory':''}" data-fee-type="${Number(type.id)}" data-saveable="1">
  <div class="dynamic-fee-name">
   <strong>${esc(type.fee_type_name)} ${mandatory?'<span class="dynamic-required">*</span><span class="dynamic-fee-chip">Mandatory</span>':''}</strong>
   <small>${esc(type.description||type.fee_type_code||'Configured in Fees Settings')}</small>
  </div>
  <div>
   <label class="form-label">Amount ${mandatory?'*':''}</label>
   <input class="form-control dynamic-fee-amount" type="number" min="0" step="0.01" value="${amount.toFixed(2)}" ${mandatory?'required':''}>
  </div>
  <div>
   <label class="form-label">Frequency</label>
   <select class="form-select dynamic-fee-frequency">
    <option value="one_time" ${frequency==='one_time'?'selected':''}>One Time</option>
    <option value="monthly" ${frequency==='monthly'?'selected':''}>Monthly</option>
    <option value="term" ${frequency==='term'?'selected':''}>Term-wise</option>
    <option value="quarterly" ${frequency==='quarterly'?'selected':''}>Quarterly</option>
    <option value="half_yearly" ${frequency==='half_yearly'?'selected':''}>Half-Yearly</option>
    <option value="yearly" ${frequency==='yearly'?'selected':''}>Yearly</option>
    <option value="annual" ${frequency==='annual'?'selected':''}>Annual</option>
    <option value="custom" ${frequency==='custom'?'selected':''}>Custom</option>
   </select>
  </div>
  <div>
   <label class="form-label">Occurrences</label>
   <input class="form-control dynamic-fee-occurrences" type="number" min="1" max="36" value="${occurrences}" required>
  </div>
  ${mandatory
    ?'<span class="dynamic-fee-lock" title="Mandatory fee cannot be removed"><i data-lucide="lock-keyhole"></i></span>'
    :'<button class="dynamic-fee-remove" type="button" title="Remove fee"><i data-lucide="trash-2"></i></button>'}
 </div>`;
}
function bindFeeRows(){
 document.querySelectorAll('.dynamic-fee-amount,.dynamic-fee-occurrences').forEach(input=>input.addEventListener('input',updateTotal));
 document.querySelectorAll('.dynamic-fee-frequency').forEach(select=>{
  select.addEventListener('change',()=>{
   const row=select.closest('.dynamic-fee-row');
   const occurrenceInput=row?.querySelector('.dynamic-fee-occurrences');
   if(occurrenceInput){
    const type=feeTypeById(Number(row?.dataset.feeType||0));
    const frequency=select.value||'one_time';
    const fallback=Math.max(
     1,
     Number(type?.default_occurrence_count||occurrenceInput.value||1)
    );
    occurrenceInput.value=String(
     normalizedOccurrences(
      frequency,
      occurrenceInput.value,
      fallback
     )
    );
   }
   updateTotal();
  });
 });
 document.querySelectorAll('.dynamic-fee-remove').forEach(button=>{
  button.addEventListener('click',()=>{
   const row=button.closest('.dynamic-fee-row');
   const type=feeTypeById(Number(row?.dataset.feeType||0));
   row?.remove();
   updateTotal();
   message(`${type?.fee_type_name||'Fee'} removed from this Fee Structure.`);
  });
 });
 window.lucide?.createIcons();
}
function renderFeeTypes(existingItems=[]){
 const types=structureFeeTypes();
 const itemMap=new Map((existingItems||[]).map(item=>[Number(item.fee_type_id),item]));
 const selectedIds=new Set();
 types.forEach(type=>{if(Number(type.is_mandatory)===1)selectedIds.add(Number(type.id))});
 itemMap.forEach((item,id)=>{if(feeTypeById(id))selectedIds.add(Number(id))});
 const selected=types.filter(type=>selectedIds.has(Number(type.id)));
 $('dynamicFeeTypes').innerHTML=selected.length
  ?selected.map(type=>feeRowMarkup(type,itemMap.get(Number(type.id))||{})).join('')
  :'<div class="fee-empty">No fee has been added yet. Click <strong>Add Fee</strong> to choose a fee from Fees Settings.</div>';
 bindFeeRows();
 updateTotal();
}
function appendSelectedFeeTypes(ids=[]){
 const selected=currentSelectedFeeIds();
 const rows=[];
 ids.forEach(id=>{
  const type=feeTypeById(id);
  if(!type||selected.has(Number(id)))return;
  selected.add(Number(id));
  rows.push(feeRowMarkup(type,{}));
 });
 if(!rows.length)return 0;
 if($('dynamicFeeTypes').querySelector('.fee-empty'))$('dynamicFeeTypes').innerHTML='';
 $('dynamicFeeTypes').insertAdjacentHTML('beforeend',rows.join(''));
 bindFeeRows();
 updateTotal();
 return rows.length;
}
function renderFeePicker(){
 const selected=currentSelectedFeeIds();
 const term=$('feePickerSearch').value.trim().toLowerCase();
 const rows=structureFeeTypes().filter(type=>{
  if(Number(type.is_enabled??1)!==1)return false;
  if(selected.has(Number(type.id)))return false;
  const haystack=`${type.fee_type_name||''} ${type.fee_type_code||''} ${type.description||''}`.toLowerCase();
  return term===''||haystack.includes(term);
 });
 $('feePickerList').innerHTML=rows.length?rows.map(type=>`<label class="fee-picker-option" data-fee-picker-id="${Number(type.id)}">
  <input class="form-check-input fee-picker-check" type="checkbox" value="${Number(type.id)}">
  <span><strong>${esc(type.fee_type_name)}</strong><small>${esc(type.description||type.fee_type_code||'Configured in Fees Settings')}</small></span>
  <span class="fee-picker-default">${esc(frequencyLabel(type.default_frequency||'one_time'))} · ${Math.max(1,Number(type.default_occurrence_count||1))}x</span>
 </label>`).join(''):'<div class="fee-picker-empty">No more enabled Fee Structure fees are available from Fees Settings.</div>';
 document.querySelectorAll('.fee-picker-option').forEach(label=>{
  const checkbox=label.querySelector('.fee-picker-check');
  checkbox?.addEventListener('change',()=>label.classList.toggle('is-selected',checkbox.checked));
 });
 window.lucide?.createIcons();
}
function openFeePicker(){
 if(!structureFeeTypes().length){message('No enabled Fee Types are available. Configure them in Fees Settings first.');return}
 $('feePickerSearch').value='';
 renderFeePicker();
 feePickerModal().show();
 setTimeout(()=>$('feePickerSearch')?.focus(),180);
}
function updateTotal(){
 let total=0;const parts=[];
 document.querySelectorAll('.dynamic-fee-row').forEach(row=>{
  const type=feeTypeById(Number(row.dataset.feeType));
  const amount=Math.max(0,Number(row.querySelector('.dynamic-fee-amount')?.value||0));
  const frequency=row.querySelector('.dynamic-fee-frequency')?.value||'one_time';
  const occurrenceInput=row.querySelector('.dynamic-fee-occurrences');
  const typeDefault=Math.max(1,Number(type?.default_occurrence_count||1));
  const occurrences=normalizedOccurrences(
   frequency,
   occurrenceInput?.value,
   typeDefault
  );
  if(
   occurrenceInput
   && !['term','custom'].includes(frequency)
  ){
   occurrenceInput.value=String(occurrences);
  }
  const annual=amount*occurrences;
  total+=annual;
  if(amount>0){
   parts.push(
    `${type?.fee_type_name||'Fee'} (${frequencyLabel(frequency)}) ${occurrences} × ${money(amount)}`
   );
  }
 });
 $('structureTotal').textContent=money(total);
 $('totalFormula').textContent=parts.length?parts.join(' + '):'Enter an amount for one or more enabled fee types.';
 return total;
}
function updateStats(stats={}){$('statTotal').textContent=Number(stats.total||0).toLocaleString('en-IN');$('statActive').textContent=Number(stats.active||0).toLocaleString('en-IN');$('statClasses').textContent=Number(stats.classes||0).toLocaleString('en-IN');$('statAmount').textContent=money(stats.amount||0)}
function render(rows){
 records=rows;
 $('structureBody').innerHTML=rows.map(row=>`<tr><td>${esc(row.year_name)}</td><td><strong>${esc(row.class_name)}</strong></td><td>${esc(row.structure_name)}</td><td>${Number(row.item_count||0)}</td><td><strong>${money(row.annual_total)}</strong></td><td>${badge(row.status)}</td><td><div class="fee-actions"><button class="fee-action js-view" data-id="${row.id}" type="button" title="View"><i data-lucide="eye"></i></button><button class="fee-action js-edit" data-id="${row.id}" type="button" title="Edit"><i data-lucide="pencil"></i></button><button class="fee-action danger js-delete" data-id="${row.id}" type="button" title="${Number(row.assignment_count)>0?'Set Inactive':'Delete'}"><i data-lucide="${Number(row.assignment_count)>0?'archive':'trash-2'}"></i></button></div></td></tr>`).join('')||'<tr><td colspan="7" class="fee-empty">No fee structures found.</td></tr>';
 document.querySelectorAll('.js-view').forEach(button=>button.onclick=()=>openView(Number(button.dataset.id)));
 document.querySelectorAll('.js-edit').forEach(button=>button.onclick=()=>openEdit(Number(button.dataset.id)));
 document.querySelectorAll('.js-delete').forEach(button=>button.onclick=()=>removeStructure(Number(button.dataset.id)));
 window.lucide?.createIcons();
}
function renderPagination(pagination={}){const total=Number(pagination.total||0),page=Number(pagination.page||1),per=Number(pagination.per_page||10),last=Math.max(1,Number(pagination.last_page||1)),start=total?((page-1)*per)+1:0,end=Math.min(page*per,total);$('recordInfo').textContent=`${total.toLocaleString('en-IN')} structure${total===1?'':'s'}`;$('pageInfo').textContent=`Showing ${start}-${end} of ${total}`;let html=`<button class="fee-page-button" data-page="${page-1}" ${page<=1?'disabled':''}>‹</button>`;for(let i=Math.max(1,page-2);i<=Math.min(last,page+2);i++)html+=`<button class="fee-page-button ${i===page?'active':''}" data-page="${i}">${i}</button>`;html+=`<button class="fee-page-button" data-page="${page+1}" ${page>=last?'disabled':''}>›</button>`;$('pagination').innerHTML=html;document.querySelectorAll('.fee-page-button').forEach(button=>button.onclick=()=>{if(!button.disabled){currentPage=Number(button.dataset.page);load()}})}
async function load(){
 try{
  const result=await request('list',{search:$('search').value.trim(),academic_year_id:$('yearFilter').value,class_id:$('classFilter').value,status:$('statusFilter').value,sort:$('sortFilter').value,page:currentPage,per_page:10});
  meta=result.data.meta||meta;
  render(result.data.records||[]);
  renderPagination(result.data.pagination||{});
  updateStats(result.data.stats||{});
  if($('yearFilter').options.length<=1)fill('yearFilter',meta.years||[],'id','year_name','All Academic Years','all');
  refreshFilterClasses();
 }catch(error){message(error.message)}
}
function clearForm(){
 const form=$('structureForm');form.reset();form.classList.remove('was-validated');
 $('structureId').value='';$('structureName').value='';$('structureStatus').value='active';
 fill('academicYearId',meta.years||[],'id','year_name','Select Academic Year','');
 const year=activeYear();$('academicYearId').value=year?String(year.id):'';
 refreshFormClasses();
 renderFeeTypes([]);
}
async function openCreate(){
 try{
  $('addStructureBtn').disabled=true;
  await refreshFeeSettingsMeta();
  clearForm();
  $('structureModalTitle').textContent='Add Fee Structure';
  $('saveStructureBtn').innerHTML='<i data-lucide="save"></i> Save Structure';
  structureModal().show();
  window.lucide?.createIcons();
 }catch(error){
  message(error.message);
 }finally{
  $('addStructureBtn').disabled=false;
 }
}
async function openEdit(id){
 const row=records.find(item=>Number(item.id)===id);if(!row)return;
 try{
  await refreshFeeSettingsMeta();
 }catch(error){
  message(error.message);
  return;
 }
 clearForm();$('structureId').value=row.id;$('structureName').value=row.structure_name||'';$('structureStatus').value=row.status;
 fill('academicYearId',meta.years||[],'id','year_name','Select Academic Year','');$('academicYearId').value=String(row.academic_year_id);
 refreshFormClasses(row.class_id);$('classId').value=String(row.class_id);
 renderFeeTypes(row.items||[]);
 $('structureModalTitle').textContent='Edit Fee Structure';$('saveStructureBtn').innerHTML='<i data-lucide="save"></i> Update Structure';
 structureModal().show();window.lucide?.createIcons();
}
function openView(id){
 const row=records.find(item=>Number(item.id)===id);if(!row)return;
 $('viewSubtitle').textContent=`${row.structure_name} • ${row.year_name} • ${row.class_name}`;
 $('viewSummary').innerHTML=[['Academic Year',row.year_name],['Class',row.class_name],['Fee Types',row.item_count],['Annual Total',money(row.annual_total)]].map(([label,value])=>`<div class="fee-summary-item"><small>${esc(label)}</small><strong>${esc(value)}</strong></div>`).join('');
 $('viewItems').innerHTML=(row.items||[]).map(item=>`<tr><td>${esc(item.fee_type_name)}</td><td>${money(item.amount)}</td><td>${esc(frequencyLabel(item.frequency))}</td><td>${Number(item.occurrence_count)}</td><td><strong>${money(item.annual_amount)}</strong></td></tr>`).join('')||'<tr><td colspan="5" class="fee-empty">No fee items.</td></tr>';
 viewModal().show();
}
async function removeStructure(id){
 const row=records.find(item=>Number(item.id)===id);if(!row)return;
 const assigned=Number(row.assignment_count||0)>0;
 const prompt=assigned
  ? `This fee structure is already assigned to students.\n\nFor data safety it will be set to Inactive instead of removing historical fee records.\n\nContinue?`
  : `Delete fee structure "${row.structure_name}"?`;
 if(!confirm(prompt))return;
 try{
  const result=await request('delete',{id},'POST');
  message(result.message,true);
  await load();
 }catch(error){
  message(error.message);
 }
}
$('structureForm').onsubmit=async event=>{
 event.preventDefault();const form=event.currentTarget;
 if(!form.checkValidity()){form.classList.add('was-validated');return}
 const items=[...document.querySelectorAll('.dynamic-fee-row[data-saveable="1"]')].map(row=>{
  const type=feeTypeById(Number(row.dataset.feeType));
  return{
   fee_type_id:Number(row.dataset.feeType),
   amount:Math.max(
    0,
    Number(row.querySelector('.dynamic-fee-amount')?.value||0)
   ),
   frequency:
    row.querySelector('.dynamic-fee-frequency')?.value
    ||type?.default_frequency
    ||'one_time',
   occurrence_count:normalizedOccurrences(
    row.querySelector('.dynamic-fee-frequency')?.value
     ||type?.default_frequency
     ||'one_time',
    row.querySelector('.dynamic-fee-occurrences')?.value,
    type?.default_occurrence_count||1
   ),
   is_mandatory:Number(type?.is_mandatory||0)
  };
 });
 if(!items.length){message('Add at least one Fee Type to the Fee Structure.');return}
 const missing=items.find(item=>item.is_mandatory===1&&item.amount<=0);
 if(missing){const type=feeTypeById(missing.fee_type_id);message(`${type?.fee_type_name||'Mandatory fee'} amount must be greater than zero.`);return}
 if(updateTotal()<=0){message('Enter at least one fee amount greater than zero.');return}
 try{
  $('saveStructureBtn').disabled=true;
  const result=await request(Number($('structureId').value||0)?'edit':'create',{
   id:Number($('structureId').value||0),
   academic_year_id:Number($('academicYearId').value||0),
   class_id:Number($('classId').value||0),
   structure_name:$('structureName').value.trim(),
   status:$('structureStatus').value,
   items
  },'POST');
  structureModal().hide();
  const savedTotal=Number(result?.data?.annual_total||0);
  message(
   savedTotal>0
    ?`${result.message} Annual Total: ${money(savedTotal)}`
    :result.message,
   true
  );
  await load();
 }catch(error){message(error.message)}
 finally{$('saveStructureBtn').disabled=false}
};

function refreshExtraClasses(){
 const yearId=Number($('extraYearId').value||0);
 const rows=(meta.classes||[]).filter(row=>Number(row.academic_year_id)===yearId);
 fill('extraClassId',rows,'id','class_name',rows.length?'Select Class':'No Classes Available','');
 $('extraClassId').disabled=!rows.length;
 refreshExtraSections();
}
function refreshExtraSections(){
 const classId=Number($('extraClassId').value||0);
 const rows=(meta.sections||[]).filter(row=>Number(row.class_id)===classId);
 fill('extraSectionId',rows,'id','section_name','All Sections','');
 $('extraSectionId').disabled=classId<=0;
}
async function openExtraFee(){
 try{
  $('extraFeesBtn').disabled=true;
  await refreshFeeSettingsMeta();
 }catch(error){
  message(error.message);
  $('extraFeesBtn').disabled=false;
  return;
 }

 $('extraFeeForm').reset();
 $('extraFeeForm').classList.remove('was-validated');

 const extraTypes=(Array.isArray(meta.extra_fee_types)?meta.extra_fee_types:[]).filter(type=>
  Number(type.is_enabled??1)===1
 );

 fill(
  'extraFeeTypeId',
  extraTypes,
  'id',
  type=>type.fee_type_name,
  extraTypes.length?'Select Extra Fee':'No Enabled Fee Types',
  ''
 );

 $('extraFeeTypeId').disabled=extraTypes.length===0;
 $('extraFeeTypeHint').textContent=extraTypes.length
  ?`${extraTypes.length} enabled fee type${extraTypes.length===1?'':'s'} available from Fees Settings.`
  :'In Fees Settings, enable a fee type and set Use In to Extra Fees or Both.';

 fill('extraYearId',meta.years||[],'id','year_name','Select Academic Year','');
 const year=activeYear();
 $('extraYearId').value=year?String(year.id):'';
 $('extraGender').value='all';
 $('extraDueDate').value=new Date().toISOString().slice(0,10);
 refreshExtraClasses();
 extraFeeModal().show();
 window.lucide?.createIcons();
 $('extraFeesBtn').disabled=false;
}
$('extraFeeForm').onsubmit=async event=>{
 event.preventDefault();
 const form=event.currentTarget;
 if(!form.checkValidity()){form.classList.add('was-validated');return}
 try{
  $('saveExtraFeeBtn').disabled=true;
  const result=await request('extra_create',{
   fee_type_id:Number($('extraFeeTypeId').value||0),
   amount:Number($('extraFeeAmount').value||0),
   academic_year_id:Number($('extraYearId').value||0),
   class_id:Number($('extraClassId').value||0),
   section_id:Number($('extraSectionId').value||0),
   gender:$('extraGender').value,
   due_date:$('extraDueDate').value,
   description:$('extraDescription').value.trim()
  },'POST');
  extraFeeModal().hide();
  message(result.message,true);
  await load();
 }catch(error){message(error.message)}
 finally{$('saveExtraFeeBtn').disabled=false}
};
$('addFeeBtn').onclick=openFeePicker;
$('feePickerSearch').oninput=renderFeePicker;
$('addSelectedFeesBtn').onclick=()=>{
 const ids=[...document.querySelectorAll('.fee-picker-check:checked')].map(input=>Number(input.value)).filter(Boolean);
 if(!ids.length){message('Select at least one fee to add.');return}
 const added=appendSelectedFeeTypes(ids);
 feePickerModal().hide();
 if(added>0)message(`${added} fee${added===1?'':'s'} added to this Fee Structure.`,true);
};
$('extraFeesBtn').onclick=openExtraFee;
$('extraYearId').onchange=refreshExtraClasses;
$('extraClassId').onchange=refreshExtraSections;

$('academicYearId').onchange=()=>refreshFormClasses();
$('yearFilter').onchange=()=>{refreshFilterClasses();currentPage=1;load()};
$('classFilter').onchange=()=>{currentPage=1;load()};
['statusFilter','sortFilter'].forEach(id=>$(id).onchange=()=>{currentPage=1;load()});
$('addStructureBtn').onclick=openCreate;
$('refreshBtn').onclick=load;
$('resetBtn').onclick=()=>{$('search').value='';$('yearFilter').value='all';refreshFilterClasses();$('classFilter').value='all';$('statusFilter').value='all';$('sortFilter').value='newest';currentPage=1;load()};
$('search').oninput=()=>{clearTimeout(searchTimer);searchTimer=setTimeout(()=>{currentPage=1;load()},300)};
load();window.lucide?.createIcons();
})();
</script>
<?php require dirname(__DIR__).'/includes/layout-end.php'; ?>

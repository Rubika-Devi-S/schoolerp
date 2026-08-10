<?php
declare(strict_types=1);
$pageTitle='Fee Reports';
$pageKey='fee_management';
require dirname(__DIR__).'/includes/layout-start.php';
?>
<style>
.fr-page{display:grid;gap:16px}.fr-page .page-title{font-size:28px;line-height:1.1}.fr-page .page-subtitle{margin-top:4px}
.fr-message{display:none}.fr-message.show{display:block}
.fr-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.fr-stat{border:0;border-radius:14px;min-height:110px;padding:18px 20px;display:flex;align-items:center;gap:14px;color:#fff;position:relative;overflow:hidden;box-shadow:0 12px 28px rgba(15,23,42,.08)}
.fr-stat::after{content:"";position:absolute;width:110px;height:110px;border-radius:50%;right:-38px;top:-40px;background:rgba(255,255,255,.08)}
.fr-stat.purple{background:linear-gradient(135deg,#7448e8,#4b36cf)}
.fr-stat.green{background:linear-gradient(135deg,#45c783,#1ea568)}
.fr-stat.orange{background:linear-gradient(135deg,#ffb327,#ff8a17)}
.fr-stat.blue{background:linear-gradient(135deg,#4696ef,#2469d5)}
.fr-stat-icon{width:50px;height:50px;border-radius:50%;background:rgba(255,255,255,.16);display:grid;place-items:center;flex:0 0 auto}
.fr-stat-icon svg{width:25px;height:25px}.fr-stat small{display:block;font-size:11px;font-weight:700;opacity:.95;margin-bottom:6px}
.fr-stat strong{display:block;font-size:25px;line-height:1}.fr-stat .trend{font-size:9px;font-weight:700;opacity:.94;margin-top:8px}
.fr-tabs{display:flex;gap:8px;padding:10px;border-bottom:1px solid var(--border-soft,#e7ebf3);overflow:auto}
.fr-tab{border:1px solid var(--border-soft,#e7ebf3);background:var(--card-bg,#fff);color:var(--text-main,#101b46);border-radius:9px;padding:9px 13px;font-size:11px;font-weight:800;white-space:nowrap}
.fr-tab.active{color:#fff;border-color:transparent;background:linear-gradient(135deg,#6747e8,#2f62d7)}
.fr-panel{display:none}.fr-panel.active{display:block}
.fr-card{border-radius:14px;overflow:hidden}
.fr-card-head{padding:14px 16px;border-bottom:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;align-items:center;gap:10px}
.fr-card-head strong{font-size:14px}.fr-actions{display:flex;gap:8px;flex-wrap:wrap}
.fr-filter{padding:14px 16px;display:grid;grid-template-columns:minmax(210px,1.3fr) repeat(6,minmax(130px,.7fr)) auto;gap:10px;border-bottom:1px solid var(--border-soft,#e7ebf3)}
.fr-class-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;padding:16px}
.fr-class-card{border:1px solid var(--border-soft,#e7ebf3);border-radius:13px;background:var(--card-bg,#fff);overflow:hidden}
.fr-class-card-head{padding:14px 15px;border-bottom:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;gap:10px}
.fr-class-card-head strong{font-size:14px}.fr-class-card-body{padding:14px 15px}
.fr-class-summary{display:grid;grid-template-columns:repeat(2,1fr);gap:9px}.fr-class-summary div{padding:10px;border-radius:9px;background:rgba(99,102,241,.045)}
.fr-class-summary small{display:block;font-size:9px;color:var(--text-muted,#64748b);font-weight:700}.fr-class-summary strong{display:block;margin-top:4px;font-size:13px}
.fr-class-footer{padding:12px 15px;border-top:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:flex-end}
.fr-student-search{padding:16px}.fr-student-results{display:grid;gap:8px;margin-top:10px}
.fr-student-result{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:11px 12px;border:1px solid var(--border-soft,#e7ebf3);border-radius:10px;background:var(--card-bg,#fff)}
.fr-student-result strong,.fr-student-result small{display:block}.fr-student-result small{color:var(--text-muted,#64748b);margin-top:3px;font-size:10px}
.fr-ledger-wrap{overflow:auto}.fr-table{min-width:1260px}.fr-table th{font-size:10px}.fr-table td{font-size:11px;vertical-align:middle}
.fr-badge{display:inline-flex;align-items:center;padding:5px 9px;border-radius:999px;font-size:9px;font-weight:800;text-transform:capitalize}
.fr-badge.paid,.fr-badge.credit{color:#16834f;background:#e8f8ef}.fr-badge.partial{color:#9a6700;background:#fff7d6}.fr-badge.unpaid,.fr-badge.debit,.fr-badge.pending{color:#dc2626;background:#fff0f1}
.fr-empty{padding:40px 18px!important;text-align:center!important;color:var(--text-muted,#64748b)}
.fr-ledger-summary{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px;padding:14px 16px;border-bottom:1px solid var(--border-soft,#e7ebf3)}
.fr-ledger-summary div{padding:11px;border:1px solid var(--border-soft,#e7ebf3);border-radius:10px;background:rgba(99,102,241,.035)}
.fr-ledger-summary small{display:block;font-size:9px;font-weight:700;color:var(--text-muted,#64748b)}.fr-ledger-summary strong{display:block;margin-top:4px;font-size:14px}
.fr-student-head{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px;padding:14px 16px;border-bottom:1px solid var(--border-soft,#e7ebf3)}
.fr-student-head div{padding:10px;border-radius:10px;background:rgba(99,102,241,.035)}.fr-student-head small{display:block;font-size:9px;color:var(--text-muted,#64748b);font-weight:700}.fr-student-head strong{display:block;margin-top:4px;font-size:12px}
@media(max-width:1200px){.fr-filter{grid-template-columns:repeat(3,1fr)}.fr-class-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:800px){.fr-stats,.fr-filter,.fr-class-grid,.fr-ledger-summary,.fr-student-head{grid-template-columns:1fr}.fr-card-head{align-items:flex-start;flex-direction:column}}
</style>

<div class="fr-page">
 <div class="page-heading">
  <div><h1 class="page-title">Fee Reports</h1><p class="page-subtitle">Accounting-style class and student fee ledgers.</p></div>
 </div>

 <div id="message" class="alert fr-message"></div>

 <section class="fr-stats">
  <article class="fr-stat purple"><span class="fr-stat-icon"><i data-lucide="wallet-cards"></i></span><div><small>Total Fee</small><strong id="statTotalFee">₹0</strong><div class="trend">Selected report period</div></div></article>
  <article class="fr-stat green"><span class="fr-stat-icon"><i data-lucide="badge-indian-rupee"></i></span><div><small>Total Collected</small><strong id="statCollected">₹0</strong><div class="trend">Payments received</div></div></article>
  <article class="fr-stat orange"><span class="fr-stat-icon"><i data-lucide="clock-3"></i></span><div><small>Total Pending</small><strong id="statPending">₹0</strong><div class="trend">Outstanding balance</div></div></article>
  <article class="fr-stat blue"><span class="fr-stat-icon"><i data-lucide="users-round"></i></span><div><small>Total Students</small><strong id="statStudents">0</strong><div class="trend">Students with fee records</div></div></article>
 </section>

 <section class="ui-card fr-card">
  <div class="fr-tabs">
   <button class="fr-tab active" data-panel="classPanel" type="button"><i data-lucide="school"></i> Class-wise Ledger</button>
   <button class="fr-tab" data-panel="studentPanel" type="button"><i data-lucide="user-round-search"></i> Student-wise Ledger</button>
  </div>

  <div id="classPanel" class="fr-panel active">
   <div class="fr-card-head">
    <strong>Class-wise Ledger</strong>
    <div class="fr-actions">
     <button id="classPrintBtn" class="btn-ui" type="button"><i data-lucide="printer"></i> Print</button>
     <button id="classExcelBtn" class="btn-ui" type="button"><i data-lucide="file-spreadsheet"></i> Excel</button>
     <button id="classPdfBtn" class="btn-ui btn-primary-ui" type="button"><i data-lucide="file-text"></i> PDF</button>
    </div>
   </div>
   <div class="fr-filter">
    <input id="classSearch" class="form-control" placeholder="Search class or student...">
    <select id="classYear" class="form-select"><option value="">Academic Year</option></select>
    <select id="classClass" class="form-select"><option value="all">All Classes</option></select>
    <select id="classSection" class="form-select" disabled><option value="all">All Sections</option></select>
    <select id="classFeeType" class="form-select"><option value="all">All Fee Types</option><option value="admission">Admission</option><option value="tuition">Tuition</option><option value="term">Term / Exam</option><option value="transport">Transport</option><option value="additional">Additional</option></select>
    <input id="classFrom" class="form-control" type="date">
    <input id="classTo" class="form-control" type="date">
    <select id="classStatus" class="form-select"><option value="all">All Status</option><option value="paid">Paid</option><option value="partial">Partial</option><option value="unpaid">Unpaid</option></select>
    <button id="classReset" class="btn-ui" type="button"><i data-lucide="rotate-ccw"></i> Reset</button>
   </div>
   <div id="classGrid" class="fr-class-grid"><div class="fr-empty">Loading...</div></div>
  </div>

  <div id="studentPanel" class="fr-panel">
   <div class="fr-card-head">
    <strong>Student-wise Ledger</strong>
    <div id="studentExportActions" class="fr-actions" style="display:none">
     <button id="studentPrintBtn" class="btn-ui" type="button"><i data-lucide="printer"></i> Print</button>
     <button id="studentExcelBtn" class="btn-ui" type="button"><i data-lucide="file-spreadsheet"></i> Excel</button>
     <button id="studentPdfBtn" class="btn-ui btn-primary-ui" type="button"><i data-lucide="file-text"></i> PDF</button>
    </div>
   </div>
   <div class="fr-filter">
    <input id="studentSearch" class="form-control" placeholder="Search student name or admission no...">
    <select id="studentYear" class="form-select"><option value="">Academic Year</option></select>
    <select id="studentClass" class="form-select"><option value="all">All Classes</option></select>
    <select id="studentSection" class="form-select" disabled><option value="all">All Sections</option></select>
    <select id="studentFeeType" class="form-select"><option value="all">All Fee Types</option><option value="admission">Admission</option><option value="tuition">Tuition</option><option value="term">Term / Exam</option><option value="transport">Transport</option><option value="additional">Additional</option></select>
    <input id="studentFrom" class="form-control" type="date">
    <input id="studentTo" class="form-control" type="date">
    <select id="studentStatus" class="form-select"><option value="all">All Status</option><option value="paid">Paid</option><option value="partial">Partial</option><option value="unpaid">Unpaid</option></select>
    <button id="studentReset" class="btn-ui" type="button"><i data-lucide="rotate-ccw"></i> Reset</button>
   </div>
   <div class="fr-student-search">
    <div id="studentResults" class="fr-student-results"><div class="fr-empty">Search and open a student.</div></div>
   </div>
   <div id="studentLedgerArea" style="display:none">
    <div id="studentHead" class="fr-student-head"></div>
    <div id="studentSummary" class="fr-ledger-summary"></div>
    <div class="fr-ledger-wrap">
     <table class="data-table fr-table">
      <thead><tr><th>Date</th><th>Reference</th><th>Description</th><th>Fee Type</th><th>Opening Balance</th><th>Charges</th><th>Payments</th><th>Discount</th><th>Fine</th><th>Balance</th><th>Running Balance</th><th>Status</th></tr></thead>
      <tbody id="studentLedgerBody"><tr><td colspan="12" class="fr-empty">No ledger loaded.</td></tr></tbody>
     </table>
    </div>
   </div>
  </div>
 </section>
</div>

<div class="modal fade" id="classLedgerModal" tabindex="-1">
 <div class="modal-dialog modal-xl modal-dialog-centered">
  <div class="modal-content">
   <div class="modal-header"><div><h5 id="classModalTitle" class="modal-title">Class Ledger</h5><small id="classModalSubtitle" class="text-muted"></small></div><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div>
   <div class="modal-body">
    <div id="classModalSummary" class="fr-ledger-summary"></div>
    <div class="fr-ledger-wrap">
     <table class="data-table fr-table">
      <thead><tr><th>Student</th><th>Admission No.</th><th>Fee Type</th><th>Total Fee</th><th>Paid</th><th>Balance</th><th>Payment Date</th><th>Payment Mode</th><th>Receipt No.</th><th>Status</th></tr></thead>
      <tbody id="classLedgerBody"><tr><td colspan="10" class="fr-empty">Loading...</td></tr></tbody>
     </table>
    </div>
   </div>
   <div class="modal-footer">
    <button id="classModalPrintBtn" class="btn-ui" type="button"><i data-lucide="printer"></i> Print</button>
    <button id="classModalExcelBtn" class="btn-ui" type="button"><i data-lucide="file-spreadsheet"></i> Excel</button>
    <button id="classModalPdfBtn" class="btn-ui btn-primary-ui" type="button"><i data-lucide="file-text"></i> PDF</button>
    <button class="btn-ui" type="button" data-bs-dismiss="modal">Close</button>
   </div>
  </div>
 </div>
</div>

<script>
(function(){
'use strict';
const apiUrl=new URL('../api/fee-reports.php',window.location.href).href;
let meta={years:[],classes:[],sections:[]},selectedClassId=0,selectedStudentId=0,timer=null;
const $=id=>document.getElementById(id);
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
const money=v=>new Intl.NumberFormat('en-IN',{style:'currency',currency:'INR',maximumFractionDigits:2}).format(Number(v||0));
const badge=v=>`<span class="fr-badge ${esc(String(v||'unpaid').toLowerCase())}">${esc(v||'Unpaid')}</span>`;
async function request(action,data={}){const url=new URL(apiUrl);url.searchParams.set('action',action);Object.entries(data).forEach(([k,v])=>{if(v!==''&&v!==null&&v!==undefined)url.searchParams.set(k,String(v))});const r=await fetch(url,{headers:{Accept:'application/json'},credentials:'same-origin',cache:'no-store'});const text=await r.text();let result;try{result=JSON.parse(text)}catch{throw new Error(`Fee Reports API returned HTTP ${r.status}.`)}if(!r.ok||!result.success)throw new Error(result.message||'Request failed.');return result}
function showError(text){const box=$('message');box.className='alert fr-message show alert-danger';box.textContent=text}
function fill(id,rows,key,label,first='',firstValue=''){const el=$(id);el.innerHTML=(first?`<option value="${esc(firstValue)}">${esc(first)}</option>`:'')+rows.map(r=>`<option value="${esc(r[key])}">${esc(r[label])}</option>`).join('')}
function initMeta(data){meta=data||meta;['class','student'].forEach(prefix=>{const y=$(`${prefix}Year`);if(y.options.length<=1){fill(`${prefix}Year`,meta.years||[],'id','year_name','Academic Year','');const current=(meta.years||[]).find(r=>Number(r.is_current)===1)||(meta.years||[])[0];if(current)y.value=String(current.id);refreshClasses(prefix)}})}
function refreshClasses(prefix){const year=Number($(`${prefix}Year`).value||0);fill(`${prefix}Class`,(meta.classes||[]).filter(r=>!year||Number(r.academic_year_id)===year),'id','class_name','All Classes','all');refreshSections(prefix)}
function refreshSections(prefix){const classId=Number($(`${prefix}Class`).value||0);fill(`${prefix}Section`,(meta.sections||[]).filter(r=>classId>0&&Number(r.class_id)===classId),'id','section_name','All Sections','all');$(`${prefix}Section`).disabled=classId<=0}
function classParams(extra={}){return{academic_year_id:$('classYear').value,class_id:$('classClass').value,section_id:$('classSection').value,item_type:$('classFeeType').value,date_from:$('classFrom').value,date_to:$('classTo').value,payment_status:$('classStatus').value,search:$('classSearch').value.trim(),...extra}}
function studentParams(extra={}){return{academic_year_id:$('studentYear').value,class_id:$('studentClass').value,section_id:$('studentSection').value,item_type:$('studentFeeType').value,date_from:$('studentFrom').value,date_to:$('studentTo').value,payment_status:$('studentStatus').value,search:$('studentSearch').value.trim(),...extra}}
function exportUrl(action,params){return apiUrl+'?'+new URLSearchParams({action,...params})}
async function loadClasses(){
 try{
  const result=await request('class_summary',classParams());initMeta(result.data.meta);const rows=result.data.classes||[],stats=result.data.stats||{};
  $('statTotalFee').textContent=money(stats.total_fee||0);
  $('statCollected').textContent=money(stats.paid_amount||0);
  $('statPending').textContent=money(stats.balance_amount||0);
  $('statStudents').textContent=Number(stats.students||0).toLocaleString('en-IN');
  $('classGrid').innerHTML=rows.map(r=>`<article class="fr-class-card"><div class="fr-class-card-head"><div><strong>${esc(r.class_name)}</strong><small class="d-block text-muted mt-1">${esc(r.year_name)} · ${Number(r.student_count)} students</small></div>${badge(r.payment_status)}</div><div class="fr-class-card-body"><div class="fr-class-summary"><div><small>Total Fee</small><strong>${money(r.total_fee)}</strong></div><div><small>Paid</small><strong>${money(r.paid_amount)}</strong></div><div><small>Balance</small><strong>${money(r.balance_amount)}</strong></div><div><small>Collection %</small><strong>${Number(r.collection_percentage||0).toFixed(1)}%</strong></div></div></div><div class="fr-class-footer"><button class="btn-ui btn-primary-ui js-class-open" data-id="${Number(r.class_id)}" type="button"><i data-lucide="book-open"></i> Open Ledger</button></div></article>`).join('')||'<div class="fr-empty">No class ledger records found.</div>';
  document.querySelectorAll('.js-class-open').forEach(b=>b.onclick=()=>openClass(Number(b.dataset.id)));window.lucide?.createIcons();
 }catch(error){showError(error.message)}
}
async function openClass(classId){
 selectedClassId=classId;
 try{
  const result=await request('class_ledger',classParams({class_id:classId})),rows=result.data.rows||[],s=result.data.summary||{},c=result.data.class||{};
  $('classModalTitle').textContent=`${c.class_name||'Class'} Ledger`;
  $('classModalSubtitle').textContent=`${c.year_name||''}${c.section_name?' · '+c.section_name:''}`;
  $('classModalSummary').innerHTML=`<div><small>Students</small><strong>${Number(s.students||0)}</strong></div><div><small>Total Fee</small><strong>${money(s.total_fee)}</strong></div><div><small>Paid</small><strong>${money(s.paid_amount)}</strong></div><div><small>Discount</small><strong>${money(s.discount_amount)}</strong></div><div><small>Balance</small><strong>${money(s.balance_amount)}</strong></div><div><small>Receipts</small><strong>${Number(s.receipts||0)}</strong></div>`;
  $('classLedgerBody').innerHTML=rows.map(r=>`<tr><td><strong>${esc(r.student_name)}</strong></td><td>${esc(r.admission_no)}</td><td>${esc(r.fee_type)}</td><td>${money(r.total_fee)}</td><td>${money(r.paid_amount)}</td><td><strong>${money(r.balance_amount)}</strong></td><td>${esc(r.payment_date_display||'-')}</td><td>${esc(r.payment_mode||'-')}</td><td>${esc(r.receipt_no||'-')}</td><td>${badge(r.payment_status)}</td></tr>`).join('')||'<tr><td colspan="10" class="fr-empty">No ledger rows found.</td></tr>';
  bootstrap.Modal.getOrCreateInstance($('classLedgerModal')).show();window.lucide?.createIcons();
 }catch(error){showError(error.message)}
}
async function searchStudents(){
 try{
  const result=await request('student_search',studentParams());initMeta(result.data.meta);const rows=result.data.students||[];
  $('studentResults').innerHTML=rows.map(r=>`<div class="fr-student-result"><div><strong>${esc(r.student_name)}</strong><small>${esc(r.admission_no)} · ${esc(r.class_name||'-')} / ${esc(r.section_name||'-')}</small></div><button class="btn-ui btn-primary-ui js-student-open" data-id="${Number(r.id)}" type="button"><i data-lucide="book-open"></i> Open Ledger</button></div>`).join('')||'<div class="fr-empty">No students found.</div>';
  document.querySelectorAll('.js-student-open').forEach(b=>b.onclick=()=>openStudent(Number(b.dataset.id)));window.lucide?.createIcons();
 }catch(error){showError(error.message)}
}
async function openStudent(studentId){
 selectedStudentId=studentId;
 try{
  const result=await request('student_ledger',studentParams({student_id:studentId})),rows=result.data.rows||[],s=result.data.summary||{},st=result.data.student||{};
  $('studentHead').innerHTML=`<div><small>Student</small><strong>${esc(st.student_name||'-')}</strong></div><div><small>Admission No.</small><strong>${esc(st.admission_no||'-')}</strong></div><div><small>Academic Year</small><strong>${esc(st.year_name||'-')}</strong></div><div><small>Class / Section</small><strong>${esc(st.class_name||'-')} / ${esc(st.section_name||'-')}</strong></div><div><small>Status</small><strong>${badge(st.payment_status||'unpaid')}</strong></div>`;
  $('studentSummary').innerHTML=`<div><small>Opening Balance</small><strong>${money(s.opening_balance)}</strong></div><div><small>Charges</small><strong>${money(s.charges)}</strong></div><div><small>Payments</small><strong>${money(s.payments)}</strong></div><div><small>Discount</small><strong>${money(s.discounts)}</strong></div><div><small>Fine</small><strong>${money(s.fines)}</strong></div><div><small>Closing Balance</small><strong>${money(s.closing_balance)}</strong></div>`;
  $('studentLedgerBody').innerHTML=rows.map(r=>`<tr><td>${esc(r.entry_date_display||r.entry_date||'-')}</td><td>${esc(r.reference_no||'-')}</td><td>${esc(r.description||'-')}</td><td>${esc(r.fee_type||'-')}</td><td>${money(r.opening_balance)}</td><td>${money(r.charge_amount)}</td><td>${money(r.payment_amount)}</td><td>${money(r.discount_amount)}</td><td>${money(r.fine_amount)}</td><td>${money(r.balance_amount)}</td><td><strong>${money(r.running_balance)}</strong></td><td>${badge(r.status)}</td></tr>`).join('')||'<tr><td colspan="12" class="fr-empty">No student ledger transactions found.</td></tr>';
  $('studentLedgerArea').style.display='block';$('studentExportActions').style.display='flex';window.lucide?.createIcons();
 }catch(error){showError(error.message)}
}
document.querySelectorAll('.fr-tab').forEach(tab=>tab.onclick=()=>{document.querySelectorAll('.fr-tab').forEach(t=>t.classList.remove('active'));document.querySelectorAll('.fr-panel').forEach(p=>p.classList.remove('active'));tab.classList.add('active');$(tab.dataset.panel).classList.add('active')});
['class','student'].forEach(prefix=>{$(`${prefix}Year`).onchange=()=>{refreshClasses(prefix);prefix==='class'?loadClasses():searchStudents()};$(`${prefix}Class`).onchange=()=>{refreshSections(prefix);prefix==='class'?loadClasses():searchStudents()};$(`${prefix}Section`).onchange=prefix==='class'?loadClasses:searchStudents;$(`${prefix}FeeType`).onchange=prefix==='class'?loadClasses:searchStudents;$(`${prefix}From`).onchange=prefix==='class'?loadClasses:searchStudents;$(`${prefix}To`).onchange=prefix==='class'?loadClasses:searchStudents;$(`${prefix}Status`).onchange=prefix==='class'?loadClasses:searchStudents});
$('classSearch').oninput=()=>{clearTimeout(timer);timer=setTimeout(loadClasses,300)};$('studentSearch').oninput=()=>{clearTimeout(timer);timer=setTimeout(searchStudents,300)};
$('classReset').onclick=()=>{['classSearch','classFrom','classTo'].forEach(id=>$(id).value='');$('classFeeType').value='all';$('classStatus').value='all';refreshClasses('class');loadClasses()};
$('studentReset').onclick=()=>{['studentSearch','studentFrom','studentTo'].forEach(id=>$(id).value='');$('studentFeeType').value='all';$('studentStatus').value='all';refreshClasses('student');$('studentLedgerArea').style.display='none';$('studentExportActions').style.display='none';searchStudents()};
$('classPrintBtn').onclick=()=>window.open(exportUrl('class_print',classParams()),'_blank');$('classExcelBtn').onclick=()=>location.href=exportUrl('class_excel',classParams());$('classPdfBtn').onclick=()=>window.open(exportUrl('class_pdf',classParams()),'_blank');
$('classModalPrintBtn').onclick=()=>window.open(exportUrl('class_print',classParams({class_id:selectedClassId})),'_blank');$('classModalExcelBtn').onclick=()=>location.href=exportUrl('class_excel',classParams({class_id:selectedClassId}));$('classModalPdfBtn').onclick=()=>window.open(exportUrl('class_pdf',classParams({class_id:selectedClassId})),'_blank');
$('studentPrintBtn').onclick=()=>window.open(exportUrl('student_print',studentParams({student_id:selectedStudentId})),'_blank');$('studentExcelBtn').onclick=()=>location.href=exportUrl('student_excel',studentParams({student_id:selectedStudentId}));$('studentPdfBtn').onclick=()=>window.open(exportUrl('student_pdf',studentParams({student_id:selectedStudentId})),'_blank');
loadClasses();searchStudents();window.lucide?.createIcons();
})();
</script>
<?php require dirname(__DIR__).'/includes/layout-end.php'; ?>

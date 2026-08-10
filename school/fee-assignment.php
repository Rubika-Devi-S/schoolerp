<?php
declare(strict_types=1);

$pageTitle='Fee Assignment';
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
.fee-card{border-radius:14px;overflow:hidden}
.fee-card-head{padding:14px 16px;border-bottom:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;align-items:center;gap:10px}
.fee-card-head strong{font-size:14px}
.fee-card-actions{display:flex;gap:8px;flex-wrap:wrap}
.fee-filter{padding:14px 16px;display:grid;grid-template-columns:minmax(220px,1.3fr) repeat(5,minmax(130px,.7fr)) auto;gap:10px;border-bottom:1px solid var(--border-soft,#e7ebf3)}
.fee-table-wrap{overflow:auto}
.fee-table{min-width:1250px}
.fee-table th{font-size:10px}
.fee-table td{font-size:11px;vertical-align:middle}
.fee-empty{padding:40px 18px!important;text-align:center!important;color:var(--text-muted,#64748b)}
.fee-badge{display:inline-flex;align-items:center;padding:5px 9px;border-radius:999px;font-size:9px;font-weight:800;text-transform:capitalize}
.fee-badge.active,.fee-badge.paid{color:#16834f;background:#e8f8ef}
.fee-badge.inactive,.fee-badge.unpaid,.fee-badge.overdue{color:#dc2626;background:#fff0f1}
.fee-badge.partial{color:#b96b00;background:#fff4df}
.fee-actions{display:flex;gap:5px;flex-wrap:wrap}
.fee-action{width:30px;height:30px;border:1px solid #d7def1;border-radius:7px;background:var(--card-bg,#fff);display:grid;place-items:center;color:#4f46e5}
.fee-action.danger{color:#dc2626}
.fee-action svg{width:13px;height:13px}
.fee-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.fee-form-grid .full{grid-column:1/-1}
.fee-student-list{border:1px solid var(--border-soft,#e7ebf3);border-radius:10px;max-height:280px;overflow:auto}
.fee-student-row{display:flex;align-items:center;gap:10px;padding:10px 12px;border-bottom:1px solid var(--border-soft,#e7ebf3)}
.fee-student-row:last-child{border-bottom:0}
.fee-student-row label{margin:0;flex:1;font-size:11px}
.fee-message{display:none}.fee-message.show{display:block}
.fee-pagination{padding:14px 16px;border-top:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;align-items:center;gap:10px}
.fee-page-buttons{display:flex;gap:6px;flex-wrap:wrap}
.fee-page-button{min-width:34px;height:34px;border:1px solid var(--border-soft,#e7ebf3);background:var(--card-bg,#fff);border-radius:8px;font-size:11px;font-weight:800;color:var(--text-main,#101a3b)}
.fee-page-button.active{color:#fff;border-color:transparent;background:linear-gradient(135deg,#6547e8,#315ed8)}
.fee-page-button:disabled{opacity:.45;cursor:not-allowed}
#assignmentModal .modal-dialog,#viewModal .modal-dialog{max-height:calc(100dvh - 32px);margin:16px auto}
#assignmentModal .modal-content,#viewModal .modal-content{max-height:calc(100dvh - 32px);overflow:hidden}
#assignmentModal form{display:flex;flex-direction:column;max-height:calc(100dvh - 32px)}
#assignmentModal .modal-body,#viewModal .modal-body{overflow-y:auto;min-height:0}
@media(max-width:1200px){.fee-filter{grid-template-columns:repeat(3,1fr)}}
@media(max-width:900px){.fee-stats{grid-template-columns:repeat(2,1fr)}}
@media(max-width:575px){.fee-stats,.fee-filter,.fee-form-grid{grid-template-columns:1fr}.fee-form-grid .full{grid-column:auto}.fee-card-head,.fee-pagination{align-items:flex-start;flex-direction:column}}
</style>

<div class="fee-page" data-page="assignment">
<div class="page-heading">
    <div>
        <h1 class="page-title">Fee Assignment</h1>
        <p class="page-subtitle">Assign fee structures to individual students or entire class and section groups.</p>
    </div>
    <div class="page-actions">
        <button id="individualBtn" class="btn-ui btn-primary-ui" type="button"><i data-lucide="user-plus"></i> Individual Assignment</button>
        <button id="bulkBtn" class="btn-ui" type="button"><i data-lucide="users-round"></i> Bulk Assignment</button>
        <button id="refreshBtn" class="btn-ui" type="button"><i data-lucide="refresh-cw"></i> Refresh</button>
    </div>
</div>

<section class="fee-stats">
<article class="fee-stat purple"><span class="fee-stat-icon"><i data-lucide="clipboard-list"></i></span><div><small>Total Assignments</small><strong id="statTotal">0</strong><div class="trend">All student fee assignments</div></div></article>
<article class="fee-stat green"><span class="fee-stat-icon"><i data-lucide="circle-check-big"></i></span><div><small>Active Assignments</small><strong id="statActive">0</strong><div class="trend">Currently enabled assignments</div></div></article>
<article class="fee-stat orange"><span class="fee-stat-icon"><i data-lucide="clock-3"></i></span><div><small>Pending Balance</small><strong id="statBalance">₹0</strong><div class="trend">Outstanding assigned fees</div></div></article>
<article class="fee-stat blue"><span class="fee-stat-icon"><i data-lucide="users"></i></span><div><small>Assigned Students</small><strong id="statStudents">0</strong><div class="trend">Distinct students assigned</div></div></article>
</section>

<section class="ui-card fee-nav">
<a href="fee-dashboard.php" data-key="dashboard"><i data-lucide="layout-dashboard"></i> Dashboard</a>
<a href="fee-setup.php" data-key="setup"><i data-lucide="settings-2"></i> Fee Setup</a>
<a href="fee-categories.php" data-key="categories"><i data-lucide="tags"></i> Fee Categories</a>
<a href="fee-structures.php" data-key="structures"><i data-lucide="list-tree"></i> Fee Structures</a>
<a href="fee-assignment.php" data-key="assignment"><i data-lucide="user-round-check"></i> Fee Assignment</a>
<a href="fee-collection.php" data-key="collection"><i data-lucide="indian-rupee"></i> Fee Collection</a>
<a href="student-fees.php" data-key="student_fees"><i data-lucide="users"></i> Student Fees</a>
<a href="fee-transactions.php" data-key="transactions"><i data-lucide="receipt-text"></i> Transactions</a>
<a href="fee-reports.php" data-key="reports"><i data-lucide="chart-column"></i> Reports</a>
</section>

<div id="feeMessage" class="alert fee-message" role="alert"></div>

<section class="ui-card fee-card">
<div class="fee-card-head"><strong>Fee Assignment List</strong><div class="fee-card-actions"><small id="recordInfo" class="text-muted">Loading...</small></div></div>
<div class="fee-filter">
<input id="search" class="form-control" placeholder="Search student, admission no. or structure...">
<select id="yearFilter" class="form-select"><option value="all">All Academic Years</option></select>
<select id="classFilter" class="form-select"><option value="all">All Classes</option></select>
<select id="sectionFilter" class="form-select"><option value="all">All Sections</option></select>
<select id="assignmentStatusFilter" class="form-select"><option value="all">All Assignment Status</option><option value="active">Active</option><option value="inactive">Inactive</option></select>
<select id="paymentStatusFilter" class="form-select"><option value="all">All Payment Status</option><option value="unpaid">Unpaid</option><option value="partial">Partial</option><option value="paid">Paid</option><option value="overdue">Overdue</option></select>
<button id="resetBtn" class="btn-ui" type="button"><i data-lucide="rotate-ccw"></i> Reset</button>
</div>
<div class="fee-table-wrap">
<table class="data-table fee-table">
<thead><tr><th>#</th><th>Student</th><th>Admission No.</th><th>Academic Year</th><th>Class</th><th>Section</th><th>Fee Structure</th><th>Assigned Date</th><th>Total</th><th>Paid</th><th>Balance</th><th>Assignment</th><th>Payment</th><th>Actions</th></tr></thead>
<tbody id="assignmentBody"><tr><td colspan="14" class="fee-empty">Loading...</td></tr></tbody>
</table>
</div>
<div class="fee-pagination"><small id="pageInfo" class="text-muted"></small><div id="pagination" class="fee-page-buttons"></div></div>
</section>
</div>

<div class="modal fade" id="assignmentModal" tabindex="-1">
<div class="modal-dialog modal-xl modal-dialog-centered">
<div class="modal-content">
<form id="assignmentForm">
<div class="modal-header"><h5 id="assignmentModalTitle" class="modal-title">Individual Fee Assignment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
<input id="assignmentId" type="hidden">
<input id="assignmentMode" type="hidden" value="individual">
<div class="fee-form-grid">
<div><label class="form-label">Academic Year *</label><select id="academicYearId" class="form-select" required></select></div>
<div><label class="form-label">Class *</label><select id="classId" class="form-select" required></select></div>
<div><label class="form-label">Section *</label><select id="sectionId" class="form-select" required></select></div>
<div><label class="form-label">Fee Structure *</label><select id="feeStructureId" class="form-select" required></select></div>
<div><label class="form-label">Assignment Date *</label><input id="assignmentDate" class="form-control" type="date" required></div>
<div><label class="form-label">Status</label><select id="assignmentStatus" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
<div><label class="form-label">Concession</label><input id="concessionAmount" class="form-control" type="number" min="0" step="0.01" value="0"></div>
<div><label class="form-label">Scholarship</label><input id="scholarshipAmount" class="form-control" type="number" min="0" step="0.01" value="0"></div>

<div id="individualStudentWrap" class="full"><label class="form-label">Student *</label><select id="studentId" class="form-select"></select></div>

<div id="bulkStudentWrap" class="full" style="display:none">
<div class="d-flex justify-content-between align-items-center mb-2"><label class="form-label mb-0">Students *</label><label class="small"><input id="selectAllStudents" type="checkbox"> Select all</label></div>
<div id="studentChecklist" class="fee-student-list"></div>
</div>

<div class="full"><div id="assignmentPreview" class="alert alert-light mb-0">Select a fee structure to view the assignment amount.</div></div>
</div>
</div>
<div class="modal-footer"><button class="btn-ui" type="button" data-bs-dismiss="modal">Cancel</button><button id="saveAssignmentBtn" class="btn-ui btn-primary-ui" type="submit">Save Assignment</button></div>
</form>
</div>
</div>
</div>

<div class="modal fade" id="viewModal" tabindex="-1">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
<div class="modal-header"><h5 class="modal-title">Fee Assignment Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div id="viewContent" class="modal-body"></div>
<div class="modal-footer"><button class="btn-ui" type="button" data-bs-dismiss="modal">Close</button></div>
</div>
</div>
</div>

<script>
(function(){
'use strict';

const apiUrl=new URL('../api/fee-assignment.php',window.location.href).href;
let csrfToken=<?=json_encode($feeCsrf)?>;
let meta={years:[],classes:[],sections:[],students:[],structures:[]};
let records=[];
let currentPage=1;
const $=id=>document.getElementById(id);
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
const money=v=>new Intl.NumberFormat('en-IN',{style:'currency',currency:'INR',maximumFractionDigits:2}).format(Number(v||0));
const badge=v=>`<span class="fee-badge ${esc(String(v||'').toLowerCase())}">${esc(v||'-')}</span>`;
const assignmentModal=()=>bootstrap.Modal.getOrCreateInstance($('assignmentModal'));
const viewModal=()=>bootstrap.Modal.getOrCreateInstance($('viewModal'));

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
 try{result=JSON.parse(text)}catch{throw new Error(`Fee Assignment API returned HTTP ${response.status}. ${text.replace(/\s+/g,' ').trim().slice(0,220)||'Invalid server response.'}`)}
 if(!response.ok||!result.success)throw new Error(result.message||'Request failed.');
 if(result.data?.csrf_token)csrfToken=result.data.csrf_token;
 return result;
}
function message(text,ok=false){const b=$('feeMessage');b.className='alert fee-message show '+(ok?'alert-success':'alert-danger');b.textContent=text}
function fill(id,rows,key,label,first=''){
 const e=$(id);e.innerHTML=(first?`<option value="all">${esc(first)}</option>`:'')+rows.map(r=>`<option value="${esc(r[key])}">${esc(r[label])}</option>`).join('');
}
function updateStats(s={}){
 $('statTotal').textContent=Number(s.total||0).toLocaleString('en-IN');
 $('statActive').textContent=Number(s.active||0).toLocaleString('en-IN');
 $('statBalance').textContent=money(s.balance||0);
 $('statStudents').textContent=Number(s.students||0).toLocaleString('en-IN');
}
function render(rows){
 records=rows;
 $('assignmentBody').innerHTML=rows.map(r=>`<tr>
 <td>${esc(r.row_number)}</td>
 <td><strong>${esc(r.student_name)}</strong></td>
 <td>${esc(r.admission_no)}</td>
 <td>${esc(r.year_name)}</td>
 <td>${esc(r.class_name||'-')}</td>
 <td>${esc(r.section_name||'-')}</td>
 <td>${esc(r.structure_name)}</td>
 <td>${esc(r.assignment_date)}</td>
 <td>${money(r.net_amount)}</td>
 <td>${money(r.paid_amount)}</td>
 <td><strong>${money(r.balance_amount)}</strong></td>
 <td>${badge(r.assignment_status)}</td>
 <td>${badge(r.payment_status)}</td>
 <td><div class="fee-actions">
  <button class="fee-action js-view" data-id="${r.id}" title="View"><i data-lucide="eye"></i></button>
  <button class="fee-action js-edit" data-id="${r.id}" title="Edit"><i data-lucide="pencil"></i></button>
  <button class="fee-action danger js-delete" data-id="${r.id}" title="Delete" ${Number(r.paid_amount)>0?'disabled':''}><i data-lucide="trash-2"></i></button>
 </div></td>
 </tr>`).join('')||'<tr><td colspan="14" class="fee-empty">No fee assignments found.</td></tr>';
 document.querySelectorAll('.js-view').forEach(b=>b.onclick=()=>openView(Number(b.dataset.id)));
 document.querySelectorAll('.js-edit').forEach(b=>b.onclick=()=>openEdit(Number(b.dataset.id)));
 document.querySelectorAll('.js-delete').forEach(b=>b.onclick=()=>removeAssignment(Number(b.dataset.id)));
 window.lucide?.createIcons();
}
function renderPagination(p={}){
 const total=Number(p.total||0),page=Number(p.page||1),per=Number(p.per_page||10),last=Math.max(1,Number(p.last_page||1));
 const start=total?((page-1)*per)+1:0,end=Math.min(page*per,total);
 $('recordInfo').textContent=`${total.toLocaleString('en-IN')} assignment${total===1?'':'s'}`;
 $('pageInfo').textContent=`Showing ${start}-${end} of ${total}`;
 let html=`<button class="fee-page-button" data-page="${page-1}" ${page<=1?'disabled':''}>‹</button>`;
 for(let i=Math.max(1,page-2);i<=Math.min(last,page+2);i++)html+=`<button class="fee-page-button ${i===page?'active':''}" data-page="${i}">${i}</button>`;
 html+=`<button class="fee-page-button" data-page="${page+1}" ${page>=last?'disabled':''}>›</button>`;
 $('pagination').innerHTML=html;
 document.querySelectorAll('.fee-page-button').forEach(b=>b.onclick=()=>{if(!b.disabled){currentPage=Number(b.dataset.page);load()}});
}
async function load(){
 try{
  const r=await request('list',{search:$('search').value.trim(),academic_year_id:$('yearFilter').value,class_id:$('classFilter').value,section_id:$('sectionFilter').value,assignment_status:$('assignmentStatusFilter').value,payment_status:$('paymentStatusFilter').value,page:currentPage,per_page:10});
  meta=r.data.meta;render(r.data.records||[]);renderPagination(r.data.pagination||{});updateStats(r.data.stats||{});
  if($('yearFilter').options.length<=1)fill('yearFilter',meta.years||[],'id','year_name','All Academic Years');
  if($('classFilter').options.length<=1)fill('classFilter',meta.classes||[],'id','class_name','All Classes');
  if($('sectionFilter').options.length<=1)fill('sectionFilter',meta.sections||[],'id','section_name','All Sections');
 }catch(e){message(e.message)}
}
function updateDependentOptions(){
 const year=Number($('academicYearId').value||0);
 const cls=Number($('classId').value||0);
 fill('classId',meta.classes.filter(x=>!year||Number(x.academic_year_id)===year),'id','class_name');
 if(cls&&[...$('classId').options].some(o=>Number(o.value)===cls))$('classId').value=String(cls);
 const selectedClass=Number($('classId').value||0);
 fill('sectionId',meta.sections.filter(x=>!selectedClass||Number(x.class_id)===selectedClass),'id','section_name');
 fill('feeStructureId',meta.structures.filter(x=>(!year||Number(x.academic_year_id)===year)&&(!selectedClass||Number(x.class_id)===selectedClass)),'id','structure_name');
 const students=meta.students.filter(x=>(!year||Number(x.academic_year_id)===year)&&(!selectedClass||Number(x.class_id)===selectedClass));
 fill('studentId',students,'id','student_name');
 renderStudentChecklist(students);
 updatePreview();
}
function renderStudentChecklist(students){
 $('studentChecklist').innerHTML=students.map(s=>`<div class="fee-student-row"><input class="bulk-student" type="checkbox" value="${s.id}" id="bulk-${s.id}"><label for="bulk-${s.id}"><strong>${esc(s.student_name)}</strong><small class="d-block text-muted">${esc(s.admission_no)} • ${esc(s.section_name||'-')}</small></label></div>`).join('')||'<div class="fee-empty">No students available.</div>';
}
function updatePreview(){
 const structure=meta.structures.find(x=>Number(x.id)===Number($('feeStructureId').value));
 const gross=Number(structure?.gross_amount||0);
 const concession=Math.max(0,Number($('concessionAmount').value||0));
 const scholarship=Math.max(0,Number($('scholarshipAmount').value||0));
 $('assignmentPreview').innerHTML=`Gross: <strong>${money(gross)}</strong> &nbsp; Net: <strong>${money(Math.max(0,gross-concession-scholarship))}</strong>`;
}
function clearForm(mode='individual'){
 $('assignmentId').value='';$('assignmentMode').value=mode;$('assignmentDate').value=new Date().toISOString().slice(0,10);$('assignmentStatus').value='active';$('concessionAmount').value='0';$('scholarshipAmount').value='0';
 fill('academicYearId',meta.years||[],'id','year_name');fill('classId',meta.classes||[],'id','class_name');updateDependentOptions();
 $('individualStudentWrap').style.display=mode==='individual'?'block':'none';$('bulkStudentWrap').style.display=mode==='bulk'?'block':'none';
 $('assignmentModalTitle').textContent=mode==='bulk'?'Bulk Fee Assignment':'Individual Fee Assignment';$('saveAssignmentBtn').textContent=mode==='bulk'?'Assign Selected Students':'Save Assignment';
}
function openCreate(mode){clearForm(mode);assignmentModal().show();window.lucide?.createIcons()}
function openEdit(id){
 const r=records.find(x=>Number(x.id)===id);if(!r)return;
 clearForm('individual');$('assignmentId').value=r.id;$('academicYearId').value=String(r.academic_year_id);updateDependentOptions();$('classId').value=String(r.class_id||'');updateDependentOptions();$('sectionId').value=String(r.section_id||'');$('studentId').value=String(r.student_id);$('feeStructureId').value=String(r.fee_structure_id);$('assignmentDate').value=r.assignment_date;$('assignmentStatus').value=r.assignment_status;$('concessionAmount').value=r.concession_amount;$('scholarshipAmount').value=r.scholarship_amount;updatePreview();
 $('assignmentModalTitle').textContent='Edit Fee Assignment';$('saveAssignmentBtn').textContent='Update Assignment';assignmentModal().show();
}
function openView(id){
 const r=records.find(x=>Number(x.id)===id);if(!r)return;
 $('viewContent').innerHTML=`<div class="fee-form-grid">
 <div><label class="form-label">Student</label><input class="form-control" readonly value="${esc(r.student_name)}"></div>
 <div><label class="form-label">Admission No.</label><input class="form-control" readonly value="${esc(r.admission_no)}"></div>
 <div><label class="form-label">Academic Year</label><input class="form-control" readonly value="${esc(r.year_name)}"></div>
 <div><label class="form-label">Class / Section</label><input class="form-control" readonly value="${esc(r.class_name||'-')} / ${esc(r.section_name||'-')}"></div>
 <div><label class="form-label">Fee Structure</label><input class="form-control" readonly value="${esc(r.structure_name)}"></div>
 <div><label class="form-label">Assignment Date</label><input class="form-control" readonly value="${esc(r.assignment_date)}"></div>
 <div><label class="form-label">Net Amount</label><input class="form-control" readonly value="${money(r.net_amount)}"></div>
 <div><label class="form-label">Balance</label><input class="form-control" readonly value="${money(r.balance_amount)}"></div>
 </div>`;
 viewModal().show();
}
async function removeAssignment(id){
 const r=records.find(x=>Number(x.id)===id);if(!r)return;
 if(Number(r.paid_amount)>0){message('Assignments with payments cannot be deleted.');return}
 if(!confirm(`Delete fee assignment for "${r.student_name}"?`))return;
 try{const res=await request('delete',{id},'POST');message(res.message,true);await load()}catch(e){message(e.message)}
}
$('assignmentForm').onsubmit=async e=>{
 e.preventDefault();
 try{
  const mode=$('assignmentMode').value;
  const studentIds=mode==='bulk'?[...document.querySelectorAll('.bulk-student:checked')].map(x=>Number(x.value)):[Number($('studentId').value)];
  const res=await request('save',{id:Number($('assignmentId').value||0),mode,academic_year_id:Number($('academicYearId').value),class_id:Number($('classId').value),section_id:Number($('sectionId').value),student_ids:studentIds,fee_structure_id:Number($('feeStructureId').value),assignment_date:$('assignmentDate').value,assignment_status:$('assignmentStatus').value,concession_amount:Number($('concessionAmount').value||0),scholarship_amount:Number($('scholarshipAmount').value||0)},'POST');
  assignmentModal().hide();message(res.message,true);await load();
 }catch(err){message(err.message)}
};
$('academicYearId').onchange=updateDependentOptions;$('classId').onchange=updateDependentOptions;
['feeStructureId','concessionAmount','scholarshipAmount'].forEach(id=>$(id).addEventListener('input',updatePreview));
$('selectAllStudents').onchange=e=>document.querySelectorAll('.bulk-student').forEach(x=>x.checked=e.target.checked);
$('individualBtn').onclick=()=>openCreate('individual');$('bulkBtn').onclick=()=>openCreate('bulk');$('refreshBtn').onclick=load;
$('resetBtn').onclick=()=>{$('search').value='';['yearFilter','classFilter','sectionFilter','assignmentStatusFilter','paymentStatusFilter'].forEach(id=>$(id).value='all');currentPage=1;load()};
['yearFilter','classFilter','sectionFilter','assignmentStatusFilter','paymentStatusFilter'].forEach(id=>$(id).addEventListener('change',()=>{currentPage=1;load()}));
let timer;$('search').addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(()=>{currentPage=1;load()},300)});
document.querySelector('.fee-nav a[data-key="assignment"]')?.classList.add('active');
load();window.lucide?.createIcons();
})();
</script>

<?php require dirname(__DIR__).'/includes/layout-end.php'; ?>

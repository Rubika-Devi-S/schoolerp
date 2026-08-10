<?php
declare(strict_types=1);

$pageTitle = 'Student Promotion';
$pageKey = 'student_promotion';
$sidebarFile = __DIR__ . '/sidebar.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (
    empty($_SESSION['student_promotion_csrf_token'])
    || !is_string($_SESSION['student_promotion_csrf_token'])
) {
    $_SESSION['student_promotion_csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['student_promotion_csrf_token'];

require dirname(__DIR__) . '/includes/layout-start.php';
?>
<style>
.promotion-page{display:grid;gap:16px}
.promotion-page .page-title{font-size:28px;line-height:1.1}
.promotion-page .page-subtitle{margin-top:4px}
.promotion-message{display:none}
.promotion-message.show{display:block}
.promotion-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.promotion-stat{border-radius:14px;min-height:110px;padding:18px 20px;display:flex;align-items:center;gap:14px;color:#fff;position:relative;overflow:hidden;box-shadow:0 12px 28px rgba(15,23,42,.08)}
.promotion-stat::after{content:"";position:absolute;width:110px;height:110px;border-radius:50%;right:-38px;top:-40px;background:rgba(255,255,255,.08)}
.promotion-stat.purple{background:linear-gradient(135deg,#7448e8,#4b36cf)}
.promotion-stat.green{background:linear-gradient(135deg,#45c783,#1ea568)}
.promotion-stat.orange{background:linear-gradient(135deg,#ffb327,#ff8a17)}
.promotion-stat.blue{background:linear-gradient(135deg,#4696ef,#2469d5)}
.promotion-stat-icon{width:50px;height:50px;border-radius:50%;background:rgba(255,255,255,.16);display:grid;place-items:center;flex:0 0 auto}
.promotion-stat-icon svg{width:25px;height:25px}
.promotion-stat strong{display:block;font-size:26px;line-height:1}
.promotion-stat small{display:block;font-size:11px;font-weight:700;opacity:.95;margin-bottom:6px}
.promotion-stat .trend{font-size:9px;font-weight:700;opacity:.94;margin-top:8px}
.promotion-card{border-radius:14px;overflow:hidden}
.promotion-card-head{padding:15px 16px;border-bottom:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;align-items:center;gap:12px}
.promotion-card-head strong{font-size:14px}
.promotion-card-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.promotion-filter{padding:14px 16px;display:grid;grid-template-columns:minmax(180px,1fr) repeat(4,minmax(150px,.75fr)) auto;gap:10px;border-bottom:1px solid var(--border-soft,#e7ebf3)}
.promotion-table-wrap{overflow:auto}
.promotion-table{min-width:1180px}
.promotion-table th{font-size:10px;white-space:nowrap}
.promotion-table td{font-size:11px;vertical-align:middle}
.promotion-student{display:flex;align-items:center;gap:9px}
.promotion-avatar{width:30px;height:30px;border-radius:50%;display:grid;place-items:center;color:#fff;font-size:11px;font-weight:800;background:linear-gradient(135deg,#6d4ce7,#345fe0)}
.promotion-badge{display:inline-flex;align-items:center;padding:5px 9px;border-radius:999px;font-size:9px;font-weight:800;text-transform:capitalize}
.promotion-badge.promoted{color:#16834f;background:#e8f8ef}
.promotion-badge.retained{color:#dc2626;background:#fff0f1}
.promotion-badge.pending{color:#b96b00;background:#fff4df}
.promotion-badge.active{color:#2563eb;background:#eaf2ff}
.promotion-badge.cancelled{color:#64748b;background:#eef2f7}
.promotion-empty{padding:42px 20px!important;text-align:center!important;color:var(--text-muted,#64748b)}
.promotion-form-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
.promotion-form-grid .full{grid-column:1/-1}
.promotion-selected{max-height:48vh;overflow:auto;border:1px solid var(--border-soft,#e7ebf3);border-radius:10px}
.promotion-selected-row{display:grid;grid-template-columns:36px minmax(220px,1.4fr) minmax(120px,.7fr) minmax(140px,.8fr) minmax(160px,1fr);gap:10px;align-items:center;padding:10px 12px;border-bottom:1px solid var(--border-soft,#e7ebf3)}
.promotion-selected-row:last-child{border-bottom:0}
.promotion-action{width:30px;height:30px;border:1px solid #d7def1;border-radius:7px;background:var(--card-bg,#fff);color:#4f46e5;display:grid;place-items:center}
.promotion-action svg{width:13px;height:13px}
#promotionModal .modal-dialog{max-height:calc(100dvh - 32px);margin:16px auto}
#promotionModal .modal-content{max-height:calc(100dvh - 32px);overflow:hidden}
#promotionModal form{display:flex;flex-direction:column;max-height:calc(100dvh - 32px)}
#promotionModal .modal-body{overflow-y:auto;min-height:0}
@media(max-width:1200px){.promotion-filter{grid-template-columns:repeat(3,1fr)}}
@media(max-width:900px){.promotion-stats{grid-template-columns:repeat(2,1fr)}.promotion-form-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:575px){.promotion-stats,.promotion-filter,.promotion-form-grid{grid-template-columns:1fr}.promotion-form-grid .full{grid-column:auto}.promotion-card-head{align-items:flex-start;flex-direction:column}.promotion-selected-row{grid-template-columns:32px 1fr}}
</style>

<div class="promotion-page">
    <div class="page-heading">
        <div>
            <h1 class="page-title">Student Promotion</h1>
            <p class="page-subtitle">Promote, retain and manage student academic progression.</p>
        </div>
        <div class="page-actions">
            <button id="openPromotionBtn" class="btn-ui btn-primary-ui" type="button">
                <i data-lucide="move-up-right"></i> Promote Students
            </button>
            <button id="refreshPromotionBtn" class="btn-ui" type="button">
                <i data-lucide="refresh-cw"></i> Refresh
            </button>
            <a id="promotionExportLink" class="btn-ui">
                <i data-lucide="download"></i> Export
            </a>
        </div>
    </div>

    <div id="promotionMessage" class="alert promotion-message"></div>

    <section class="promotion-stats">
        <article class="promotion-stat purple"><span class="promotion-stat-icon"><i data-lucide="users-round"></i></span><div><small>Eligible Students</small><strong id="eligibleCount">0</strong><div class="trend">Current source selection</div></div></article>
        <article class="promotion-stat green"><span class="promotion-stat-icon"><i data-lucide="circle-check-big"></i></span><div><small>Promoted</small><strong id="promotedCount">0</strong><div class="trend">Active promotion records</div></div></article>
        <article class="promotion-stat orange"><span class="promotion-stat-icon"><i data-lucide="clock-3"></i></span><div><small>Pending</small><strong id="pendingCount">0</strong><div class="trend">Awaiting final action</div></div></article>
        <article class="promotion-stat blue"><span class="promotion-stat-icon"><i data-lucide="rotate-ccw"></i></span><div><small>Retained</small><strong id="retainedCount">0</strong><div class="trend">Retained in current class</div></div></article>
    </section>

    <section class="ui-card promotion-card">
        <div class="promotion-card-head">
            <strong>Promotion History</strong>
            <div class="promotion-card-actions">
                <small id="promotionRecordCount" class="text-muted">Loading...</small>
            </div>
        </div>

        <div class="promotion-filter">
            <input id="promotionSearch" class="form-control" type="search" placeholder="Search student, class or remarks...">
            <select id="historyFromYear" class="form-select"><option value="all">All From Years</option></select>
            <select id="historyToYear" class="form-select"><option value="all">All To Years</option></select>
            <select id="historyResult" class="form-select">
                <option value="all">All Results</option>
                <option value="promoted">Promoted</option>
                <option value="retained">Retained</option>
                <option value="pending">Pending</option>
            </select>
            <select id="historyStatus" class="form-select">
                <option value="active">Active Records</option>
                <option value="cancelled">Cancelled Records</option>
                <option value="all">All Statuses</option>
            </select>
            <button id="resetPromotionFilters" class="btn-ui" type="button"><i data-lucide="rotate-ccw"></i> Reset</button>
        </div>

        <div class="promotion-table-wrap">
            <table class="data-table promotion-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Student</th>
                        <th>From Year</th>
                        <th>From Class</th>
                        <th>To Year</th>
                        <th>To Class</th>
                        <th>Result</th>
                        <th>Remarks</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="promotionHistoryBody">
                    <tr><td colspan="10" class="promotion-empty">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </section>
</div>

<div class="modal fade" id="promotionModal" tabindex="-1">
<div class="modal-dialog modal-xl modal-dialog-centered">
<div class="modal-content">
<form id="promotionForm" novalidate>
<div class="modal-header">
    <div>
        <h5 class="modal-title">Promote Students</h5>
        <small class="text-muted">Select source and destination academic details.</small>
    </div>
    <button class="btn-close" type="button" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    <div class="promotion-form-grid">
        <div>
            <label class="form-label">From Academic Year *</label>
            <select id="fromAcademicYear" class="form-select" required></select>
        </div>
        <div>
            <label class="form-label">From Class *</label>
            <select id="fromClass" class="form-select" required></select>
        </div>
        <div>
            <label class="form-label">From Section</label>
            <select id="fromSection" class="form-select"><option value="">All Sections</option></select>
        </div>
        <div>
            <label class="form-label">Promotion Date *</label>
            <input id="promotionDate" class="form-control" type="date" required>
        </div>
        <div>
            <label class="form-label">To Academic Year *</label>
            <select id="toAcademicYear" class="form-select" required></select>
        </div>
        <div>
            <label class="form-label">To Class *</label>
            <select id="toClass" class="form-select" required></select>
        </div>
        <div>
            <label class="form-label">To Section *</label>
            <select id="toSection" class="form-select" required></select>
        </div>
        <div>
            <label class="form-label">Default Result *</label>
            <select id="defaultResult" class="form-select">
                <option value="promoted">Promoted</option>
                <option value="retained">Retained</option>
                <option value="pending">Pending</option>
            </select>
        </div>
        <div class="full">
            <label class="form-label">General Remarks</label>
            <textarea id="promotionRemarks" class="form-control" rows="2" maxlength="1000"></textarea>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mt-4 mb-2">
        <strong>Eligible Students</strong>
        <div class="d-flex gap-2">
            <button id="selectAllStudents" class="btn-ui btn-sm" type="button">Select All</button>
            <button id="clearStudentSelection" class="btn-ui btn-sm" type="button">Clear</button>
        </div>
    </div>

    <div id="eligibleStudents" class="promotion-selected">
        <div class="promotion-empty">Select source academic year and class.</div>
    </div>
</div>
<div class="modal-footer">
    <span id="selectedStudentCount" class="me-auto text-muted">0 students selected</span>
    <button class="btn-ui" type="button" data-bs-dismiss="modal">Cancel</button>
    <button class="btn-ui btn-primary-ui" type="submit"><i data-lucide="move-up-right"></i> Process Promotion</button>
</div>
</form>
</div>
</div>
</div>

<script>
(function(){
'use strict';

const apiUrl=new URL('../api/student-promotion.php',window.location.href).href;
let csrfToken=<?=json_encode($csrfToken)?>;
let meta={academic_years:[],classes:[],sections:[]};
let permissions={};
let eligibleStudents=[];
let historyRows=[];

const $=id=>document.getElementById(id);
const esc=value=>String(value??'').replace(/[&<>"']/g,char=>({
 '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot',"'":'&#039;'
}[char]));

async function request(action,data={},method='GET'){
 let response;

 if(method==='GET'){
  const url=new URL(apiUrl,window.location.origin);
  url.searchParams.set('action',action);
  Object.entries(data).forEach(([key,value])=>{
   if(value!==''&&value!==null&&value!==undefined){
    url.searchParams.set(key,String(value));
   }
  });
  response=await fetch(url,{
   headers:{Accept:'application/json'},
   credentials:'same-origin'
  });
 }else{
  response=await fetch(apiUrl,{
   method:'POST',
   headers:{
    'Content-Type':'application/json',
    Accept:'application/json'
   },
   credentials:'same-origin',
   body:JSON.stringify({
    action,
    csrf_token:csrfToken,
    ...data
   })
  });
 }

 const text=await response.text();
 let result;

 try{
  result=JSON.parse(text);
 }catch{
  throw new Error(
   `Student Promotion API returned HTTP ${response.status}. Invalid server response.`
  );
 }

 if(!response.ok||!result.success){
  throw new Error(result.message||'Request failed.');
 }

 return result;
}

function showMessage(message,success){
 const box=$('promotionMessage');
 box.className='alert promotion-message show '+(
  success?'alert-success':'alert-danger'
 );
 box.textContent=message;
 window.scrollTo({top:0,behavior:'smooth'});
}

function fillSelect(id,rows,valueKey,labelKey,firstHtml=''){
 const element=$(id);
 element.innerHTML=firstHtml+rows.map(row=>
  `<option value="${esc(row[valueKey])}">${esc(row[labelKey])}</option>`
 ).join('');
}

function yearName(id){
 return meta.academic_years.find(row=>Number(row.id)===Number(id))?.year_name||'-';
}

function refreshLucide(){
 window.lucide?.createIcons();
}

function classesForYear(yearId){
 return meta.classes.filter(row=>Number(row.academic_year_id)===Number(yearId));
}

function sectionsForClass(classId){
 return meta.sections.filter(row=>Number(row.class_id)===Number(classId));
}

function syncSourceClasses(){
 const rows=classesForYear($('fromAcademicYear').value);
 fillSelect('fromClass',rows,'id','class_name','<option value="">Select class</option>');
 fillSelect('fromSection',[],'id','section_name','<option value="">All Sections</option>');
 eligibleStudents=[];
 renderEligible();
}

function syncSourceSections(){
 const rows=sectionsForClass($('fromClass').value);
 fillSelect('fromSection',rows,'id','section_name','<option value="">All Sections</option>');
 loadEligible();
}

function syncTargetClasses(){
 const rows=classesForYear($('toAcademicYear').value);
 fillSelect('toClass',rows,'id','class_name','<option value="">Select class</option>');
 fillSelect('toSection',[],'id','section_name','<option value="">Select section</option>');
}

function syncTargetSections(){
 const rows=sectionsForClass($('toClass').value);
 fillSelect('toSection',rows,'id','section_name','<option value="">Select section</option>');
}

function selectedEntries(){
 return [...document.querySelectorAll('.student-select:checked')].map(box=>{
  const row=box.closest('.promotion-selected-row');
  return {
   student_id:Number(box.value),
   result_status:row.querySelector('.student-result').value,
   remarks:row.querySelector('.student-remarks').value.trim()
  };
 });
}

function updateSelectedCount(){
 const count=document.querySelectorAll('.student-select:checked').length;
 $('selectedStudentCount').textContent=
  `${count} student${count===1?'':'s'} selected`;
}

function renderEligible(){
 $('eligibleCount').textContent=eligibleStudents.length;

 $('eligibleStudents').innerHTML=eligibleStudents.map(student=>`
  <div class="promotion-selected-row">
   <input class="form-check-input student-select" type="checkbox" value="${student.id}">
   <div class="promotion-student">
    <span class="promotion-avatar">${esc((student.student_name||'?').charAt(0).toUpperCase())}</span>
    <div>
     <strong>${esc(student.student_name)}</strong>
     <small class="d-block text-muted">${esc(student.admission_no||'')}</small>
    </div>
   </div>
   <span>${esc(student.roll_no||'-')}</span>
   <select class="form-select form-select-sm student-result">
    <option value="promoted">Promoted</option>
    <option value="retained">Retained</option>
    <option value="pending">Pending</option>
   </select>
   <input class="form-control form-control-sm student-remarks" maxlength="500" placeholder="Student remarks">
  </div>
 `).join('')||'<div class="promotion-empty">No eligible students found.</div>';

 document.querySelectorAll('.student-select').forEach(box=>{
  box.addEventListener('change',updateSelectedCount);
 });

 document.querySelectorAll('.student-result').forEach(select=>{
  select.value=$('defaultResult').value;
 });

 updateSelectedCount();
}

function renderHistory(){
 $('promotionHistoryBody').innerHTML=historyRows.map(row=>`
  <tr>
   <td>${esc(row.promotion_date)}</td>
   <td>
    <div class="promotion-student">
     <span class="promotion-avatar">${esc((row.student_name||'?').charAt(0).toUpperCase())}</span>
     <div>
      <strong>${esc(row.student_name)}</strong>
      <small class="d-block text-muted">${esc(row.admission_no||'')}</small>
     </div>
    </div>
   </td>
   <td>${esc(row.from_year_name||'-')}</td>
   <td>${esc(row.from_class_name||'-')} ${row.from_section_name?' - '+esc(row.from_section_name):''}</td>
   <td>${esc(row.to_year_name||'-')}</td>
   <td>${esc(row.to_class_name||'-')} ${row.to_section_name?' - '+esc(row.to_section_name):''}</td>
   <td><span class="promotion-badge ${esc(row.result_status)}">${esc(row.result_status)}</span></td>
   <td>${esc(row.remarks||'-')}</td>
   <td><span class="promotion-badge ${esc(row.status)}">${esc(row.status)}</span></td>
   <td>${permissions.delete&&row.status==='active'
    ?`<button class="promotion-action js-cancel-promotion" data-id="${row.id}" type="button" title="Cancel"><i data-lucide="ban"></i></button>`
    :''}</td>
  </tr>
 `).join('')||'<tr><td colspan="10" class="promotion-empty">No promotion records found.</td></tr>';

 $('promotionRecordCount').textContent=
  `${historyRows.length} record${historyRows.length===1?'':'s'}`;

 document.querySelectorAll('.js-cancel-promotion').forEach(button=>{
  button.addEventListener('click',()=>cancelPromotion(Number(button.dataset.id)));
 });

 refreshLucide();
}

function renderStats(stats={}){
 $('promotedCount').textContent=Number(stats.promoted||0).toLocaleString();
 $('pendingCount').textContent=Number(stats.pending||0).toLocaleString();
 $('retainedCount').textContent=Number(stats.retained||0).toLocaleString();
}

function historyFilters(){
 return {
  search:$('promotionSearch').value.trim(),
  from_year_id:$('historyFromYear').value,
  to_year_id:$('historyToYear').value,
  result_status:$('historyResult').value,
  status:$('historyStatus').value
 };
}

async function loadMeta(){
 const response=await request('meta');
 csrfToken=response.data.csrf_token||csrfToken;
 meta=response.data.meta||meta;
 permissions=response.data.permissions||{};

 fillSelect(
  'historyFromYear',
  meta.academic_years,
  'id',
  'year_name',
  '<option value="all">All From Years</option>'
 );
 fillSelect(
  'historyToYear',
  meta.academic_years,
  'id',
  'year_name',
  '<option value="all">All To Years</option>'
 );
 fillSelect(
  'fromAcademicYear',
  meta.academic_years,
  'id',
  'year_name',
  '<option value="">Select year</option>'
 );
 fillSelect(
  'toAcademicYear',
  meta.academic_years,
  'id',
  'year_name',
  '<option value="">Select year</option>'
 );

 const current=meta.academic_years.find(row=>Number(row.is_current)===1);
 if(current){
  $('fromAcademicYear').value=String(current.id);
  syncSourceClasses();
 }
 $('openPromotionBtn').style.display=permissions.create?'':'none';
 $('promotionExportLink').style.display=permissions.export?'':'none';
}

async function loadEligible(){
 const yearId=$('fromAcademicYear').value;
 const classId=$('fromClass').value;

 if(!yearId||!classId){
  eligibleStudents=[];
  renderEligible();
  return;
 }

 const response=await request('eligible',{
  academic_year_id:yearId,
  class_id:classId,
  section_id:$('fromSection').value
 });

 eligibleStudents=response.data.students||[];
 renderEligible();
}

async function loadHistory(){
 const response=await request('history',historyFilters());
 historyRows=response.data.records||[];
 renderHistory();
 renderStats(response.data.stats||{});
 $('promotionExportLink').href=
  apiUrl+'?action=export&'+new URLSearchParams(historyFilters());
}

async function cancelPromotion(id){
 if(!window.confirm('Cancel this promotion record?'))return;

 try{
  const response=await request('cancel',{id},'POST');
  showMessage(response.message,true);
  await loadHistory();
 }catch(error){
  showMessage(error.message,false);
 }
}

$('promotionForm').addEventListener('submit',async event=>{
 event.preventDefault();

 if(!event.currentTarget.checkValidity()){
  event.currentTarget.classList.add('was-validated');
  return;
 }

 const entries=selectedEntries();

 if(entries.length===0){
  showMessage('Select at least one student.',false);
  return;
 }

 try{
  const response=await request('promote',{
   from_academic_year_id:Number($('fromAcademicYear').value),
   from_class_id:Number($('fromClass').value),
   from_section_id:Number($('fromSection').value||0),
   to_academic_year_id:Number($('toAcademicYear').value),
   to_class_id:Number($('toClass').value),
   to_section_id:Number($('toSection').value),
   promotion_date:$('promotionDate').value,
   remarks:$('promotionRemarks').value.trim(),
   entries
  },'POST');

  bootstrap.Modal.getInstance($('promotionModal'))?.hide();
  showMessage(response.message,true);
  await Promise.all([loadEligible(),loadHistory()]);
 }catch(error){
  showMessage(error.message,false);
 }
});

$('openPromotionBtn').addEventListener('click',()=>{
 $('promotionForm').reset();
 $('promotionForm').classList.remove('was-validated');
 $('promotionDate').value=new Date().toISOString().slice(0,10);

 const current=meta.academic_years.find(row=>Number(row.is_current)===1);
 if(current){
  $('fromAcademicYear').value=String(current.id);
  syncSourceClasses();
 }

 eligibleStudents=[];
 renderEligible();
 bootstrap.Modal.getOrCreateInstance($('promotionModal')).show();
});

$('refreshPromotionBtn').addEventListener('click',()=>loadHistory());
$('fromAcademicYear').addEventListener('change',syncSourceClasses);
$('fromClass').addEventListener('change',syncSourceSections);
$('fromSection').addEventListener('change',loadEligible);
$('toAcademicYear').addEventListener('change',syncTargetClasses);
$('toClass').addEventListener('change',syncTargetSections);

$('defaultResult').addEventListener('change',()=>{
 document.querySelectorAll('.student-result').forEach(select=>{
  select.value=$('defaultResult').value;
 });
});

$('selectAllStudents').addEventListener('click',()=>{
 document.querySelectorAll('.student-select').forEach(box=>box.checked=true);
 updateSelectedCount();
});

$('clearStudentSelection').addEventListener('click',()=>{
 document.querySelectorAll('.student-select').forEach(box=>box.checked=false);
 updateSelectedCount();
});

$('resetPromotionFilters').addEventListener('click',()=>{
 $('promotionSearch').value='';
 $('historyFromYear').value='all';
 $('historyToYear').value='all';
 $('historyResult').value='all';
 $('historyStatus').value='active';
 loadHistory();
});

['historyFromYear','historyToYear','historyResult','historyStatus'].forEach(id=>{
 $(id).addEventListener('change',loadHistory);
});

let searchTimer=null;
$('promotionSearch').addEventListener('input',()=>{
 clearTimeout(searchTimer);
 searchTimer=setTimeout(loadHistory,300);
});

(async()=>{
 try{
  await loadMeta();
  await loadHistory();
  refreshLucide();
 }catch(error){
  showMessage(error.message,false);
 }
})();
})();
</script>
<?php require dirname(__DIR__) . '/includes/layout-end.php'; ?>

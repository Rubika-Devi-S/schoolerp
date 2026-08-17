<?php
declare(strict_types=1);

/* Student Transport UI - Build 2026-08-14-class-management-filter-v4 */

$pageTitle='Student Transport';
$pageKey='transport';

require dirname(__DIR__).'/includes/layout-start.php';
?>
<style>
.st-page{display:grid;gap:16px}
.st-page .page-title{font-size:28px;line-height:1.1}
.st-page .page-subtitle{margin-top:4px}
.st-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.st-stat{border-radius:14px;min-height:110px;padding:18px 20px;display:flex;align-items:center;gap:14px;color:#fff;position:relative;overflow:hidden;box-shadow:0 12px 28px rgba(15,23,42,.08)}
.st-stat::after{content:"";position:absolute;width:110px;height:110px;border-radius:50%;right:-38px;top:-40px;background:rgba(255,255,255,.08)}
.st-stat.purple{background:linear-gradient(135deg,#7448e8,#4b36cf)}
.st-stat.green{background:linear-gradient(135deg,#45c783,#1ea568)}
.st-stat.orange{background:linear-gradient(135deg,#ffb327,#ff8a17)}
.st-stat.blue{background:linear-gradient(135deg,#4696ef,#2469d5)}
.st-stat-icon{width:50px;height:50px;border-radius:50%;background:rgba(255,255,255,.16);display:grid;place-items:center}
.st-stat strong{display:block;font-size:25px;line-height:1}
.st-stat small{display:block;font-size:11px;font-weight:700;opacity:.95;margin-bottom:6px}
.st-stat .trend{font-size:9px;font-weight:700;opacity:.94;margin-top:8px}
.st-card{border-radius:14px;overflow:hidden}
.st-card-head{padding:14px 16px;border-bottom:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;align-items:center;gap:10px}
.st-filter{padding:14px 16px;display:grid;grid-template-columns:minmax(220px,1.4fr) repeat(3,minmax(145px,.75fr)) auto;gap:10px;border-bottom:1px solid var(--border-soft,#e7ebf3)}
.st-table-wrap{overflow:auto}
.st-table{min-width:1220px}
.st-table th{font-size:10px}
.st-table td{font-size:11px;vertical-align:middle}
.st-empty{padding:40px 18px!important;text-align:center!important;color:var(--text-muted,#64748b)}
.st-route{display:grid;gap:3px}
.st-route strong{font-size:11px}
.st-route small{font-size:9px;color:var(--text-muted,#64748b)}
@media(max-width:1000px){.st-filter{grid-template-columns:repeat(2,1fr)}}
@media(max-width:800px){.st-stats{grid-template-columns:repeat(2,1fr)}}
@media(max-width:575px){.st-stats,.st-filter{grid-template-columns:1fr}}
</style>

<div class="st-page">
<div class="page-heading">
 <div>
  <h1 class="page-title">Student Transport</h1>
  <p class="page-subtitle">Only students currently using school transport are shown.</p>
 </div>
 <div class="page-actions">
  <button id="refreshBtn" class="btn-ui" type="button">
   <i data-lucide="refresh-cw"></i> Refresh
  </button>
 </div>
</div>

<div id="message" class="alert d-none"></div>

<section class="st-stats">
 <article class="st-stat purple"><span class="st-stat-icon"><i data-lucide="users"></i></span><div><small>Transport Students</small><strong id="statStudents">0</strong><div class="trend">Students using school transport</div></div></article>
 <article class="st-stat green"><span class="st-stat-icon"><i data-lucide="route"></i></span><div><small>Active Routes</small><strong id="statRoutes">0</strong><div class="trend">Routes used by listed students</div></div></article>
 <article class="st-stat orange"><span class="st-stat-icon"><i data-lucide="indian-rupee"></i></span><div><small>Bus Fee Assigned</small><strong id="statFee">₹0</strong><div class="trend">Fixed transport fee assigned</div></div></article>
 <article class="st-stat blue"><span class="st-stat-icon"><i data-lucide="wallet-cards"></i></span><div><small>Total Balance</small><strong id="statBalance">₹0</strong><div class="trend">Complete student fee balance</div></div></article>
</section>

<section class="ui-card st-card">
 <div class="st-card-head">
  <strong>Transport Student List</strong>
  <small id="recordInfo" class="text-muted">Loading...</small>
 </div>

 <div class="st-filter">
  <input id="search" class="form-control" placeholder="Search student or admission no...">
  <select id="yearId" class="form-select"><option value="">Select Academic Year</option></select>
  <select id="classId" class="form-select"><option value="all">All Classes</option></select>
  <select id="routeId" class="form-select"><option value="all">All Routes</option></select>
  <button id="resetBtn" class="btn-ui" type="button"><i data-lucide="rotate-ccw"></i> Reset</button>
 </div>

 <div class="st-table-wrap">
  <table class="data-table st-table">
   <thead>
    <tr>
     <th>Student</th>
     <th>Admission No.</th>
     <th>Academic Year</th>
     <th>Class / Section</th>
     <th>Route</th>
     <th>Boarding Stop</th>
     <th>Bus Fee</th>
     <th>Total Fee</th>
     <th>Paid</th>
     <th>Balance</th>
    </tr>
   </thead>
   <tbody id="body"><tr><td colspan="10" class="st-empty">Loading...</td></tr></tbody>
  </table>
 </div>
</section>
</div>

<script>
(function(){
'use strict';

const apiUrl=new URL('../api/student-transport.php',window.location.href).href;
let meta={years:[],classes:[],routes:[]};
let records=[];
let timer=null;

const $=id=>document.getElementById(id);
const esc=value=>String(value??'').replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
const money=value=>new Intl.NumberFormat('en-IN',{style:'currency',currency:'INR',maximumFractionDigits:2}).format(Number(value||0));

async function request(action,data={}){
 const url=new URL(apiUrl);
 url.searchParams.set('action',action);

 Object.entries(data).forEach(([key,value])=>{
  if(value!==''&&value!==null&&value!==undefined){
   url.searchParams.set(key,String(value));
  }
 });

 const response=await fetch(url,{
  headers:{Accept:'application/json'},
  credentials:'same-origin'
 });
 const text=await response.text();
 let result;

 try{
  result=JSON.parse(text);
 }catch{
  console.error(text);
  throw new Error('Student Transport API returned an invalid response.');
 }

 if(!response.ok||!result.success){
  throw new Error(result.message||'Request failed.');
 }

 return result;
}

function fill(id,rows,key,label,first='',firstValue=''){
 const element=$(id);
 const selected=element.value;
 element.innerHTML=(first?`<option value="${esc(firstValue)}">${esc(first)}</option>`:'')
  +rows.map(row=>`<option value="${esc(row[key])}">${esc(row[label])}</option>`).join('');
 if([...element.options].some(option=>option.value===selected)){
  element.value=selected;
 }
}

function refreshClasses(){
 const yearId=Number($('yearId').value||0);
 fill(
  'classId',
  (meta.classes||[]).filter(row=>!yearId||Number(row.academic_year_id)===yearId),
  'id',
  'class_name',
  'All Classes',
  'all'
 );
}

function renderStats(stats={}){
 $('statStudents').textContent=Number(stats.students||0).toLocaleString('en-IN');
 $('statRoutes').textContent=Number(stats.routes||0).toLocaleString('en-IN');
 $('statFee').textContent=money(stats.bus_fee||0);
 $('statBalance').textContent=money(stats.balance||0);
}

function render(){
 $('body').innerHTML=records.map(row=>`
  <tr>
   <td><strong>${esc(row.student_name)}</strong></td>
   <td>${esc(row.admission_no)}</td>
   <td>${esc(row.year_name)}</td>
   <td>${esc(row.class_name||'-')} / ${esc(row.section_name||'-')}</td>
   <td>
    <div class="st-route">
     <strong>${esc(row.route_name||'-')}</strong>
     <small>${esc(row.route_code||'')}</small>
    </div>
   </td>
   <td>${esc(row.boarding_stop_name||'-')}</td>
   <td><strong>${money(row.bus_fee_amount)}</strong></td>
   <td>${money(row.net_amount)}</td>
   <td>${money(row.paid_amount)}</td>
   <td><strong>${money(row.balance_amount)}</strong></td>
  </tr>
 `).join('')
 ||'<tr><td colspan="10" class="st-empty">No transport students found.</td></tr>';

 $('recordInfo').textContent=`${records.length} transport student${records.length===1?'':'s'}`;
 window.lucide?.createIcons();
}

async function load(){
 try{
  const result=await request('list',{
   academic_year_id:$('yearId').value,
   class_id:$('classId').value,
   route_id:$('routeId').value,
   search:$('search').value.trim()
  });

  meta=result.data.meta||meta;
  records=result.data.records||[];

  if($('yearId').options.length<=1){
   fill('yearId',meta.years||[],'id','year_name','Select Academic Year','');
   const current=(meta.years||[]).find(row=>Number(row.is_current)===1)||(meta.years||[])[0];
   if(current)$('yearId').value=String(current.id);
  }

  fill('routeId',meta.routes||[],'id','route_name','All Routes','all');
  refreshClasses();
  renderStats(result.data.stats||{});
  render();

  const box=$('message');
  box.className='alert d-none';
  box.textContent='';
 }catch(error){
  const box=$('message');
  box.className='alert alert-danger';
  box.textContent=error.message;
 }
}

$('yearId').onchange=()=>{
 refreshClasses();
 $('classId').value='all';
 load();
};
$('classId').onchange=load;
$('routeId').onchange=load;
$('refreshBtn').onclick=load;

$('resetBtn').onclick=()=>{
 $('search').value='';
 const current=(meta.years||[]).find(row=>Number(row.is_current)===1)||(meta.years||[])[0];
 $('yearId').value=current?String(current.id):'';
 refreshClasses();
 $('classId').value='all';
 $('routeId').value='all';
 load();
};

$('search').oninput=()=>{
 window.clearTimeout(timer);
 timer=window.setTimeout(load,300);
};

load();
window.lucide?.createIcons();
})();
</script>

<?php require dirname(__DIR__).'/includes/layout-end.php'; ?>

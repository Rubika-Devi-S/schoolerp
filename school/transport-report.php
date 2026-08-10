<?php
declare(strict_types=1);

$pageTitle = 'Transport Report';
$pageKey = 'transport_management';
require dirname(__DIR__) . '/includes/layout-start.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
?>
<style>
*{box-sizing:border-box}
.tr-page{display:grid;gap:16px;min-width:0}
.tr-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px}
.tr-head h1{margin:0;font-size:clamp(24px,2vw,30px)}
.tr-head p{margin:5px 0 0}
.tr-actions{display:flex;gap:9px;flex-wrap:wrap}
.tr-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.tr-stat{min-height:112px;border-radius:14px;padding:18px;color:#fff;display:flex;align-items:center;gap:14px;position:relative;overflow:hidden}
.tr-stat:after{content:"";position:absolute;width:110px;height:110px;border-radius:50%;right:-35px;top:-40px;background:rgba(255,255,255,.1)}
.tr-stat.purple{background:linear-gradient(135deg,#7448e8,#4b36cf)}
.tr-stat.green{background:linear-gradient(135deg,#45c783,#1ea568)}
.tr-stat.orange{background:linear-gradient(135deg,#ffb327,#ff8a17)}
.tr-stat.blue{background:linear-gradient(135deg,#4696ef,#2469d5)}
.tr-stat-icon{width:48px;height:48px;border-radius:50%;display:grid;place-items:center;background:rgba(255,255,255,.16);position:relative;z-index:1;flex:0 0 auto}
.tr-stat-icon svg{width:24px}
.tr-stat div{position:relative;z-index:1;min-width:0}
.tr-stat small{display:block;font-weight:700;font-size:11px}
.tr-stat strong{display:block;font-size:25px;margin-top:5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.tr-card{border-radius:14px;overflow:hidden;min-width:0}
.tr-card-head{padding:14px 16px;border-bottom:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;gap:10px}
.tr-filter{padding:14px 16px;display:grid;grid-template-columns:minmax(240px,1.4fr) repeat(4,minmax(150px,.8fr)) auto;gap:10px;border-bottom:1px solid var(--border-soft,#e7ebf3)}
.tr-filter>*{min-width:0;min-height:40px}
.tr-tabs{display:flex;gap:8px;overflow-x:auto;padding:10px}
.tr-tab{border:1px solid var(--border-soft,#e7ebf3);background:var(--card-bg,#fff);color:var(--text-main,#101b46);border-radius:9px;padding:9px 12px;font-size:11px;font-weight:800;white-space:nowrap}
.tr-tab.active{color:#fff;border-color:transparent;background:linear-gradient(135deg,#6747e8,#2f62d7)}
.tr-panel{display:none}.tr-panel.active{display:block}
.tr-table-wrap{overflow-x:auto;width:100%;-webkit-overflow-scrolling:touch}
.tr-table{min-width:1380px;width:100%}
.tr-table.compact{min-width:1080px}
.tr-table th{white-space:nowrap;position:sticky;top:0;background:var(--card-bg,#fff);z-index:2;font-size:10px}
.tr-table td{font-size:11px;vertical-align:middle}
.tr-table th,.tr-table td{padding:11px 10px}
.tr-badge{display:inline-flex;padding:5px 9px;border-radius:999px;font-size:9px;font-weight:800;text-transform:capitalize}
.tr-badge.active,.tr-badge.assigned,.tr-badge.paid{color:#16834f;background:#e8f8ef}
.tr-badge.inactive,.tr-badge.unassigned,.tr-badge.expired{color:#dc2626;background:#fff0f1}
.tr-badge.partial,.tr-badge.expiring,.tr-badge.pending{color:#9a6700;background:#fff7d6}
.tr-empty{text-align:center!important;padding:40px 15px!important;color:var(--text-muted,#64748b)}
.tr-foot{padding:12px 16px;border-top:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap}
.tr-money{font-weight:800;color:#16834f;white-space:nowrap}
.tr-route-link{font-weight:800;color:#4f46e5;text-decoration:none}
@media(max-width:1200px){.tr-head{flex-direction:column}.tr-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.tr-filter{grid-template-columns:repeat(3,minmax(0,1fr))}.tr-filter #reportSearch{grid-column:1/-1}}
@media(max-width:767px){.tr-actions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));width:100%}.tr-actions .btn-ui{justify-content:center}.tr-stats{grid-template-columns:1fr}.tr-filter{grid-template-columns:1fr}.tr-filter #reportSearch{grid-column:auto}.tr-card-head,.tr-foot{flex-direction:column;align-items:flex-start}.tr-table{min-width:1200px}.tr-table.compact{min-width:900px}}
@media(max-width:575px){.tr-actions{grid-template-columns:1fr}}
@media print{.tr-actions,.tr-filter,.tr-tabs{display:none!important}.tr-panel{display:block!important;margin-bottom:18px}.tr-table-wrap{overflow:visible}.tr-table,.tr-table.compact{min-width:0;width:100%}.tr-table th,.tr-table td{font-size:8px;padding:5px}.tr-stats{grid-template-columns:repeat(4,1fr)}}

/* Extended responsive support */
.tr-page{width:100%;max-width:100%;min-width:0}.tr-head>div:first-child{min-width:0;flex:1 1 auto}.tr-actions{justify-content:flex-end}.tr-actions .btn-ui{min-height:40px;white-space:nowrap}.tr-card,.tr-panel,.tr-table-wrap{min-width:0;max-width:100%}.tr-tabs{-webkit-overflow-scrolling:touch;scrollbar-width:thin}.tr-filter .form-control,.tr-filter .form-select,.tr-filter .btn-ui{width:100%;min-width:0}.tr-table-wrap{scrollbar-width:thin}.tr-table td{max-width:280px;overflow-wrap:anywhere}
@media(min-width:1800px){.tr-page{gap:18px}.tr-stat{min-height:118px;padding:20px 22px}.tr-filter{grid-template-columns:minmax(320px,1.5fr) repeat(4,minmax(170px,.75fr)) auto}.tr-table,.tr-table.compact{min-width:100%}}
@media(min-width:1440px) and (max-width:1799px){.tr-filter{grid-template-columns:minmax(270px,1.4fr) repeat(4,minmax(150px,.75fr)) auto}.tr-table{min-width:1360px}.tr-table.compact{min-width:1080px}}
@media(min-width:1201px) and (max-width:1439px){.tr-filter{grid-template-columns:minmax(230px,1.3fr) repeat(4,minmax(135px,.75fr)) auto}.tr-table{min-width:1320px}.tr-table.compact{min-width:1040px}.tr-stat strong{font-size:22px}}
@media(min-width:992px) and (max-width:1200px){.tr-filter{grid-template-columns:repeat(3,minmax(0,1fr))}.tr-filter #reportSearch{grid-column:1/-1}.tr-table{min-width:1260px}.tr-table.compact{min-width:1000px}}
@media(min-width:768px) and (max-width:991px){.tr-head{flex-direction:column;align-items:stretch}.tr-actions{justify-content:flex-start}.tr-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.tr-filter{grid-template-columns:repeat(2,minmax(0,1fr));padding:12px}.tr-filter #reportSearch{grid-column:1/-1}.tr-table{min-width:1200px}.tr-table.compact{min-width:960px}}
@media(max-width:767px){.tr-page{gap:12px}.tr-head{flex-direction:column;align-items:stretch}.tr-head h1{font-size:24px}.tr-actions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));width:100%}.tr-actions .btn-ui{width:100%;justify-content:center}.tr-stats{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.tr-stat{min-height:102px;padding:14px}.tr-filter{grid-template-columns:1fr;padding:12px}.tr-filter #reportSearch{grid-column:auto}.tr-card-head,.tr-foot{flex-direction:column;align-items:flex-start}.tr-table{min-width:1120px}.tr-table.compact{min-width:900px}}
@media(max-width:480px){.tr-actions{grid-template-columns:1fr}.tr-stats{grid-template-columns:1fr}}
@media(max-height:760px) and (min-width:768px){.tr-page{gap:12px}.tr-stat{min-height:94px}}

</style>

<div class="tr-page">
 <div class="tr-head">
  <div><h1>Transport Report</h1><p class="text-muted">Routes, vehicles, drivers, boarding stops, students and transport fees.</p></div>
  <div class="tr-actions">
   <button id="exportReportBtn" class="btn-ui" type="button"><i data-lucide="download"></i> Export CSV</button>
   <button id="printReportBtn" class="btn-ui" type="button"><i data-lucide="printer"></i> Print</button>
   <button id="refreshReportBtn" class="btn-ui btn-primary-ui" type="button"><i data-lucide="refresh-cw"></i> Refresh</button>
  </div>
 </div>

 <section class="tr-stats">
  <article class="tr-stat purple"><span class="tr-stat-icon"><i data-lucide="route"></i></span><div><small>Active Routes</small><strong id="statRoutes">0</strong></div></article>
  <article class="tr-stat green"><span class="tr-stat-icon"><i data-lucide="bus-front"></i></span><div><small>Assigned Vehicles</small><strong id="statVehicles">0</strong></div></article>
  <article class="tr-stat orange"><span class="tr-stat-icon"><i data-lucide="users-round"></i></span><div><small>Transport Students</small><strong id="statStudents">0</strong></div></article>
  <article class="tr-stat blue"><span class="tr-stat-icon"><i data-lucide="indian-rupee"></i></span><div><small>Transport Fee</small><strong id="statFee">₹0</strong></div></article>
 </section>

 <section class="ui-card tr-card">
  <div class="tr-card-head"><strong>Report Filters</strong><small id="reportCount" class="text-muted">Loading...</small></div>
  <div class="tr-filter">
   <input id="reportSearch" class="form-control" type="search" placeholder="Search route, vehicle, driver, stop or student">
   <select id="routeFilter" class="form-select"><option value="all">All Routes</option></select>
   <select id="vehicleFilter" class="form-select"><option value="all">All Vehicles</option></select>
   <select id="driverFilter" class="form-select"><option value="all">All Drivers</option></select>
   <select id="statusFilter" class="form-select"><option value="all">All Statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select>
   <button id="resetFiltersBtn" class="btn-ui" type="button"><i data-lucide="rotate-ccw"></i> Reset</button>
  </div>
 </section>

 <section class="ui-card tr-tabs">
  <button class="tr-tab active" data-tab="routes" type="button">Route Summary</button>
  <button class="tr-tab" data-tab="students" type="button">Student Transport</button>
  <button class="tr-tab" data-tab="stops" type="button">Stop Fee Summary</button>
  <button class="tr-tab" data-tab="drivers" type="button">Driver & Vehicle</button>
 </section>

 <section class="tr-panel active" data-panel="routes">
  <section class="ui-card tr-card">
   <div class="tr-card-head"><strong>Route Summary</strong><small class="text-muted">Route-wise operational details</small></div>
   <div class="tr-table-wrap"><table class="data-table tr-table"><thead><tr><th>#</th><th>Route</th><th>Zone / Area</th><th>Start</th><th>End</th><th>Vehicle</th><th>Driver</th><th>Stops</th><th>Students</th><th>Monthly Fee</th><th>Status</th></tr></thead><tbody id="routeReportBody"><tr><td colspan="11" class="tr-empty">Loading...</td></tr></tbody></table></div>
  </section>
 </section>

 <section class="tr-panel" data-panel="students">
  <section class="ui-card tr-card">
   <div class="tr-card-head"><strong>Student Transport Report</strong><small class="text-muted">Current transport assignments</small></div>
   <div class="tr-table-wrap"><table class="data-table tr-table"><thead><tr><th>#</th><th>Admission No.</th><th>Student</th><th>Class</th><th>Route</th><th>Boarding Stop</th><th>Vehicle</th><th>Driver</th><th>Transport Fee</th><th>Status</th></tr></thead><tbody id="studentReportBody"><tr><td colspan="10" class="tr-empty">Loading...</td></tr></tbody></table></div>
  </section>
 </section>

 <section class="tr-panel" data-panel="stops">
  <section class="ui-card tr-card">
   <div class="tr-card-head"><strong>Stop Fee Summary</strong><small class="text-muted">Boarding-stop fee configuration and usage</small></div>
   <div class="tr-table-wrap"><table class="data-table tr-table compact"><thead><tr><th>#</th><th>Route</th><th>Stop Order</th><th>Boarding Stop</th><th>Configured Fee</th><th>Students</th><th>Expected Monthly Fee</th><th>Status</th></tr></thead><tbody id="stopReportBody"><tr><td colspan="8" class="tr-empty">Loading...</td></tr></tbody></table></div>
  </section>
 </section>

 <section class="tr-panel" data-panel="drivers">
  <section class="ui-card tr-card">
   <div class="tr-card-head"><strong>Driver & Vehicle Report</strong><small class="text-muted">Vehicle assignment and licence information</small></div>
   <div class="tr-table-wrap"><table class="data-table tr-table compact"><thead><tr><th>#</th><th>Vehicle</th><th>Type</th><th>Route</th><th>Driver</th><th>Mobile</th><th>Licence No.</th><th>Licence Expiry</th><th>Status</th></tr></thead><tbody id="driverReportBody"><tr><td colspan="9" class="tr-empty">Loading...</td></tr></tbody></table></div>
  </section>
 </section>

 <div class="tr-foot"><small id="reportInfo" class="text-muted">0 records</small><small class="text-muted">Transport fee totals use the current saved student stop fee.</small></div>
</div>

<script>
(function(){
'use strict';
const apiUrl=new URL('../api/transport-report.php',window.location.href).href;
let meta={routes:[],vehicles:[],drivers:[]},searchTimer=null;
const $=id=>document.getElementById(id);
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
const money=v=>new Intl.NumberFormat('en-IN',{style:'currency',currency:'INR',maximumFractionDigits:2}).format(Number(v||0));
const badge=v=>'<span class="tr-badge '+esc(String(v||'inactive').toLowerCase())+'">'+esc(v||'inactive')+'</span>';
async function request(action,data={}){const url=new URL(apiUrl);url.searchParams.set('action',action);Object.entries(data).forEach(([k,v])=>{if(v!==''&&v!==null&&v!==undefined)url.searchParams.set(k,String(v))});const response=await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json'},cache:'no-store'});const text=await response.text();let result;try{result=JSON.parse(text)}catch(e){throw new Error('Transport Report API returned HTTP '+response.status+'. '+(text.replace(/\s+/g,' ').trim().slice(0,220)||'Invalid response.'))}if(!response.ok||!result.success)throw new Error(result.message||'Unable to load transport report.');return result}
function fill(id,rows,label){const el=$(id),current=el.value;el.innerHTML='<option value="all">'+esc(label)+'</option>'+rows.map(r=>'<option value="'+Number(r.id)+'">'+esc(r.label)+'</option>').join('');if([...el.options].some(o=>o.value===current))el.value=current}
function filters(){return{search:$('reportSearch').value.trim(),route_id:$('routeFilter').value,vehicle_id:$('vehicleFilter').value,driver_id:$('driverFilter').value,status:$('statusFilter').value}}
function renderRoutes(rows){$('routeReportBody').innerHTML=rows.map((r,i)=>'<tr><td>'+(i+1)+'</td><td><a class="tr-route-link" href="route-stops.php?route_id='+Number(r.id)+'">'+esc(r.route_name)+'<small class="d-block text-muted">'+esc(r.route_code)+'</small></a></td><td>'+esc(r.zone_area||'-')+'</td><td>'+esc(r.start_point||'-')+'</td><td>'+esc(r.end_point||'-')+'</td><td>'+esc(r.vehicle_label||'Not Assigned')+'</td><td>'+esc(r.driver_name||'Not Assigned')+'</td><td>'+Number(r.stop_count||0)+'</td><td>'+Number(r.student_count||0)+'</td><td class="tr-money">'+money(r.monthly_fee||0)+'</td><td>'+badge(r.status)+'</td></tr>').join('')||'<tr><td colspan="11" class="tr-empty">No route records found.</td></tr>'}
function renderStudents(rows){$('studentReportBody').innerHTML=rows.map((r,i)=>'<tr><td>'+(i+1)+'</td><td><strong>'+esc(r.admission_number||'-')+'</strong></td><td>'+esc(r.student_name||'-')+'</td><td>'+esc(r.class_name||'-')+'</td><td>'+esc(r.route_name||'-')+'</td><td>'+esc(r.boarding_stop_name||'-')+'</td><td>'+esc(r.vehicle_name||'Not Assigned')+'</td><td>'+esc(r.driver_name||'Not Assigned')+'</td><td class="tr-money">'+money(r.transport_fee_amount||0)+'</td><td>'+badge(r.status||'active')+'</td></tr>').join('')||'<tr><td colspan="10" class="tr-empty">No student transport records found.</td></tr>'}
function renderStops(rows){$('stopReportBody').innerHTML=rows.map((r,i)=>'<tr><td>'+(i+1)+'</td><td>'+esc(r.route_name||'-')+'</td><td>'+Number(r.stop_order||0)+'</td><td><strong>'+esc(r.stop_name||'-')+'</strong></td><td class="tr-money">'+money(r.transport_fee||0)+'</td><td>'+Number(r.student_count||0)+'</td><td class="tr-money">'+money(r.expected_fee||0)+'</td><td>'+badge(r.status)+'</td></tr>').join('')||'<tr><td colspan="8" class="tr-empty">No stop records found.</td></tr>'}
function renderDrivers(rows){$('driverReportBody').innerHTML=rows.map((r,i)=>'<tr><td>'+(i+1)+'</td><td><strong>'+esc(r.vehicle_label||'-')+'</strong></td><td>'+esc(r.vehicle_type||'-')+'</td><td>'+esc(r.route_name||'Not Assigned')+'</td><td>'+esc(r.driver_name||'Not Assigned')+'</td><td>'+esc(r.mobile||'-')+'</td><td>'+esc(r.licence_number||'-')+'</td><td>'+esc(r.licence_expiry_display||'-')+'</td><td>'+badge(r.driver_status||r.vehicle_status||'inactive')+'</td></tr>').join('')||'<tr><td colspan="9" class="tr-empty">No driver or vehicle records found.</td></tr>'}
function renderStats(s={}){$('statRoutes').textContent=Number(s.active_routes||0);$('statVehicles').textContent=Number(s.assigned_vehicles||0);$('statStudents').textContent=Number(s.transport_students||0);$('statFee').textContent=money(s.transport_fee||0)}
async function load(){try{$('reportCount').textContent='Loading...';const r=await request('report',filters()),d=r.data;meta=d.meta||meta;fill('routeFilter',meta.routes||[],'All Routes');fill('vehicleFilter',meta.vehicles||[],'All Vehicles');fill('driverFilter',meta.drivers||[],'All Drivers');renderRoutes(d.routes||[]);renderStudents(d.students||[]);renderStops(d.stops||[]);renderDrivers(d.drivers||[]);renderStats(d.stats||{});const total=(d.routes||[]).length+(d.students||[]).length+(d.stops||[]).length+(d.drivers||[]).length;$('reportCount').textContent=total+' report records';$('reportInfo').textContent=total+' records displayed';window.lucide?.createIcons()}catch(e){['routeReportBody','studentReportBody','stopReportBody','driverReportBody'].forEach((id,index)=>$(id).innerHTML='<tr><td colspan="'+[11,10,8,9][index]+'" class="tr-empty">'+esc(e.message)+'</td></tr>')}}
function exportCsv(){const p=new URLSearchParams({action:'export',...filters()});window.location.href=apiUrl+'?'+p}
$('refreshReportBtn').onclick=load;$('exportReportBtn').onclick=exportCsv;$('printReportBtn').onclick=()=>window.print();$('resetFiltersBtn').onclick=()=>{$('reportSearch').value='';['routeFilter','vehicleFilter','driverFilter','statusFilter'].forEach(id=>$(id).value='all');load()};['routeFilter','vehicleFilter','driverFilter','statusFilter'].forEach(id=>$(id).onchange=load);$('reportSearch').oninput=()=>{clearTimeout(searchTimer);searchTimer=setTimeout(load,300)};document.querySelectorAll('.tr-tab').forEach(b=>b.onclick=()=>{document.querySelectorAll('.tr-tab').forEach(x=>x.classList.toggle('active',x===b));document.querySelectorAll('.tr-panel').forEach(x=>x.classList.toggle('active',x.dataset.panel===b.dataset.tab))});load();window.lucide?.createIcons();
})();
</script>
<?php require dirname(__DIR__) . '/includes/layout-end.php'; ?>

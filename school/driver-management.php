<?php
declare(strict_types=1);

$pageTitle = 'Driver Master';
$pageKey = 'transport_management';
require dirname(__DIR__) . '/includes/layout-start.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['driver_csrf_token']) || !is_string($_SESSION['driver_csrf_token'])) {
    $_SESSION['driver_csrf_token'] = bin2hex(random_bytes(32));
}

$driverCsrf = $_SESSION['driver_csrf_token'];
?>
<style>
*{box-sizing:border-box}
.driver-page{display:grid;gap:16px;min-width:0}
.driver-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px}
.driver-head h1{margin:0;font-size:clamp(24px,2vw,30px)}
.driver-head p{margin:5px 0 0}.driver-actions{display:flex;gap:9px;flex-wrap:wrap}
.driver-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.driver-stat{min-height:112px;border-radius:14px;padding:18px;color:#fff;display:flex;align-items:center;gap:14px;overflow:hidden;position:relative}
.driver-stat:after{content:"";position:absolute;width:110px;height:110px;border-radius:50%;right:-35px;top:-40px;background:rgba(255,255,255,.1)}
.driver-stat.purple{background:linear-gradient(135deg,#7448e8,#4b36cf)}
.driver-stat.green{background:linear-gradient(135deg,#45c783,#1ea568)}
.driver-stat.orange{background:linear-gradient(135deg,#ffb327,#ff8a17)}
.driver-stat.blue{background:linear-gradient(135deg,#4696ef,#2469d5)}
.driver-stat-icon{width:48px;height:48px;border-radius:50%;display:grid;place-items:center;background:rgba(255,255,255,.16);position:relative;z-index:1;flex:0 0 auto}
.driver-stat-icon svg{width:24px}.driver-stat div{position:relative;z-index:1;min-width:0}
.driver-stat small{display:block;font-weight:700;font-size:11px}
.driver-stat strong{display:block;font-size:26px;margin-top:5px}
.driver-card{border-radius:14px;overflow:hidden;min-width:0}
.driver-card-head{padding:14px 16px;border-bottom:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;gap:10px}
.driver-filter{padding:14px 16px;display:grid;grid-template-columns:minmax(280px,1.5fr) minmax(180px,.7fr) minmax(180px,.7fr) auto;gap:10px;border-bottom:1px solid var(--border-soft,#e7ebf3)}
.driver-filter>*{min-height:40px;min-width:0}
.driver-table-wrap{overflow-x:auto;width:100%;-webkit-overflow-scrolling:touch}
.driver-table{min-width:1240px;width:100%}
.driver-table th{white-space:nowrap;position:sticky;top:0;background:var(--card-bg,#fff);z-index:2;font-size:10px}
.driver-table td{font-size:11px;vertical-align:middle}
.driver-table th,.driver-table td{padding:11px 10px}
.driver-cell{display:flex;align-items:center;gap:9px;min-width:180px}
.driver-avatar{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;color:#fff;background:linear-gradient(135deg,#6d4ce7,#345fe0);font-size:11px;font-weight:800;flex:0 0 auto}
.driver-badge{display:inline-flex;padding:5px 9px;border-radius:999px;font-size:9px;font-weight:800;text-transform:capitalize}
.driver-badge.active,.driver-badge.valid{color:#16834f;background:#e8f8ef}
.driver-badge.inactive,.driver-badge.expired{color:#dc2626;background:#fff0f1}
.driver-badge.expiring{color:#9a6700;background:#fff7d6}
.driver-action-group{display:flex;gap:5px;flex-wrap:nowrap}
.driver-action{width:31px;height:31px;border:1px solid #d7def1;border-radius:7px;background:var(--card-bg,#fff);display:grid;place-items:center;color:#4f46e5;flex:0 0 auto}
.driver-action.edit{color:#2563eb}.driver-action.delete{color:#dc2626}.driver-action svg{width:14px}
.driver-empty{text-align:center!important;padding:40px 15px!important}
.driver-foot{padding:12px 16px;border-top:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap}
.driver-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.driver-form-grid .full{grid-column:1/-1}
.driver-view-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
.driver-view-item{padding:12px;border:1px solid var(--border-soft,#e3e8f1);border-radius:10px;background:rgba(99,102,241,.035)}
.driver-view-item small{display:block;font-size:9px;font-weight:700;color:var(--text-muted,#64748b)}
.driver-view-item strong{display:block;margin-top:5px;overflow-wrap:anywhere}
.driver-toast{position:fixed;right:18px;top:18px;z-index:2200;width:min(390px,calc(100vw - 36px));padding:13px 15px;border-radius:10px;color:#fff;box-shadow:0 18px 40px rgba(15,23,42,.18);opacity:0;visibility:hidden;transform:translateY(-8px);transition:.2s;font-size:12px;font-weight:700}
.driver-toast.show{opacity:1;visibility:visible;transform:translateY(0)}.driver-toast.success{background:#16834f}.driver-toast.error{background:#dc2626}
#driverModal .modal-dialog{width:min(980px,calc(100vw - 32px));max-width:980px}
#driverModal .modal-content,#viewDriverModal .modal-content,#deleteDriverModal .modal-content{max-height:calc(100dvh - 32px);overflow:hidden}
#driverModal form{display:flex;flex-direction:column;max-height:calc(100dvh - 32px)}
#driverModal .modal-body,#viewDriverModal .modal-body,#deleteDriverModal .modal-body{overflow-y:auto}
@media(max-width:1199px){.driver-head{flex-direction:column}.driver-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.driver-filter{grid-template-columns:repeat(2,minmax(0,1fr))}.driver-filter #driverSearch{grid-column:1/-1}}
@media(max-width:767px){.driver-actions{display:grid;grid-template-columns:1fr 1fr;width:100%}.driver-actions .btn-ui{justify-content:center}.driver-stats{grid-template-columns:1fr}.driver-filter,.driver-form-grid,.driver-view-grid{grid-template-columns:1fr}.driver-filter #driverSearch{grid-column:auto}.driver-table{min-width:1080px}.driver-card-head,.driver-foot{flex-direction:column;align-items:flex-start}}
@media(max-width:575px){#driverModal .modal-dialog,#viewDriverModal .modal-dialog,#deleteDriverModal .modal-dialog{width:calc(100vw - 16px);margin:8px auto}.modal-footer{display:grid;grid-template-columns:1fr}.modal-footer .btn-ui{width:100%;justify-content:center}.driver-actions{grid-template-columns:1fr}}
</style>

<div id="driverToast" class="driver-toast" role="status" aria-live="polite"></div>

<div class="driver-page">
 <div class="driver-head">
  <div><h1>Driver Master</h1><p class="text-muted">Manage school transport drivers, licences and vehicle assignments.</p></div>
  <div class="driver-actions">
   <button id="addDriverBtn" class="btn-ui btn-primary-ui" type="button"><i data-lucide="plus"></i> Add Driver</button>
   <button id="refreshDriverBtn" class="btn-ui" type="button"><i data-lucide="refresh-cw"></i> Refresh</button>
  </div>
 </div>

 <section class="driver-stats">
  <article class="driver-stat purple"><span class="driver-stat-icon"><i data-lucide="users-round"></i></span><div><small>Total Drivers</small><strong id="statTotal">0</strong></div></article>
  <article class="driver-stat green"><span class="driver-stat-icon"><i data-lucide="circle-check-big"></i></span><div><small>Active Drivers</small><strong id="statActive">0</strong></div></article>
  <article class="driver-stat orange"><span class="driver-stat-icon"><i data-lucide="bus-front"></i></span><div><small>Assigned Drivers</small><strong id="statAssigned">0</strong></div></article>
  <article class="driver-stat blue"><span class="driver-stat-icon"><i data-lucide="badge-alert"></i></span><div><small>Licence Expiring</small><strong id="statExpiring">0</strong></div></article>
 </section>

 <section class="ui-card driver-card">
  <div class="driver-card-head"><strong>Driver List</strong><small id="driverListCount" class="text-muted">Loading...</small></div>
  <div class="driver-filter">
   <input id="driverSearch" class="form-control" type="search" placeholder="Search by Driver Name, Mobile or Licence No." autocomplete="off">
   <select id="driverStatusFilter" class="form-select"><option value="all">All Statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select>
   <select id="driverVehicleFilter" class="form-select"><option value="all">All Assignments</option><option value="assigned">Assigned</option><option value="unassigned">Not Assigned</option></select>
   <button id="resetDriverFiltersBtn" class="btn-ui" type="button"><i data-lucide="rotate-ccw"></i> Reset</button>
  </div>
  <div class="driver-table-wrap">
   <table class="data-table driver-table">
    <thead><tr><th>#</th><th>Driver</th><th>Mobile</th><th>Licence Number</th><th>Licence Expiry</th><th>Assigned Vehicle</th><th>Experience</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody id="driverTableBody"><tr><td colspan="9" class="driver-empty">Loading drivers...</td></tr></tbody>
   </table>
  </div>
  <div class="driver-foot"><small id="driverRecordInfo" class="text-muted">0 drivers</small><small class="text-muted">One active driver can be assigned to one active vehicle.</small></div>
 </section>
</div>

<div class="modal fade" id="driverModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content"><form id="driverForm" novalidate>
 <div class="modal-header"><div><h5 id="driverModalTitle" class="modal-title">Add Driver</h5><small id="driverModalSubtitle" class="text-muted">Enter the driver information.</small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
 <div class="modal-body driver-form-grid">
  <input id="driverId" type="hidden">
  <div><label class="form-label">Driver Name *</label><input id="driverName" class="form-control" maxlength="150" required></div>
  <div><label class="form-label">Mobile Number *</label><input id="driverMobile" class="form-control" maxlength="20" inputmode="tel" required></div>
  <div><label class="form-label">Alternate Mobile</label><input id="alternateMobile" class="form-control" maxlength="20" inputmode="tel"></div>
  <div><label class="form-label">Driving Licence Number *</label><input id="licenceNumber" class="form-control" maxlength="60" required></div>
  <div><label class="form-label">Licence Expiry Date *</label><input id="licenceExpiry" class="form-control" type="date" required></div>
  <div><label class="form-label">Date of Birth</label><input id="dateOfBirth" class="form-control" type="date"></div>
  <div><label class="form-label">Joining Date</label><input id="joiningDate" class="form-control" type="date"></div>
  <div><label class="form-label">Experience (Years)</label><input id="experienceYears" class="form-control" type="number" min="0" max="60" value="0"></div>
  <div><label class="form-label">Assigned Vehicle</label><select id="vehicleId" class="form-select"><option value="">Not Assigned</option></select></div>
  <div><label class="form-label">Status *</label><select id="driverStatus" class="form-select" required><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
  <div class="full"><label class="form-label">Address</label><textarea id="driverAddress" class="form-control" rows="2" maxlength="500"></textarea></div>
  <div class="full"><label class="form-label">Notes</label><textarea id="driverNotes" class="form-control" rows="2" maxlength="500"></textarea></div>
 </div>
 <div class="modal-footer">
  <button id="deleteDriverFromFormBtn" type="button" class="btn-ui" hidden><i data-lucide="trash-2"></i> Delete</button>
  <button id="resetDriverFormBtn" type="button" class="btn-ui"><i data-lucide="rotate-ccw"></i> Reset</button>
  <button type="button" class="btn-ui" data-bs-dismiss="modal">Cancel</button>
  <button id="saveDriverBtn" type="submit" class="btn-ui btn-primary-ui"><i data-lucide="save"></i><span id="saveDriverBtnText">Add Driver</span></button>
 </div>
</form></div></div></div>

<div class="modal fade" id="viewDriverModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
 <div class="modal-header"><div><h5 class="modal-title">Driver Details</h5><small id="viewDriverSubtitle" class="text-muted"></small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
 <div class="modal-body"><div id="viewDriverContent" class="driver-view-grid"></div></div>
 <div class="modal-footer"><button type="button" class="btn-ui" data-bs-dismiss="modal">Close</button></div>
</div></div></div>

<div class="modal fade" id="deleteDriverModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
 <div class="modal-header"><div><h5 class="modal-title">Delete Driver</h5><small class="text-muted">Vehicle assignment will be cleared.</small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
 <div class="modal-body"><p>Are you sure you want to delete this driver?</p><div class="alert alert-warning mb-0"><strong id="deleteDriverLabel">-</strong></div></div>
 <div class="modal-footer"><button type="button" class="btn-ui" data-bs-dismiss="modal">Cancel</button><button id="confirmDeleteDriverBtn" type="button" class="btn-ui btn-primary-ui"><i data-lucide="trash-2"></i> Delete Driver</button></div>
</div></div></div>

<script>
(function(){
'use strict';
const apiUrl=new URL('../api/driver-management.php',window.location.href).href;
let csrfToken=<?=json_encode($driverCsrf)?>,drivers=[],vehicles=[],deleteDriverId=0,searchTimer=null,toastTimer=null;
const $=id=>document.getElementById(id);
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
function toast(m,s){const e=$('driverToast');e.className='driver-toast show '+(s?'success':'error');e.textContent=m;clearTimeout(toastTimer);toastTimer=setTimeout(()=>e.className='driver-toast',5000)}
async function request(action,data={},method='GET'){let response;if(method==='GET'){const url=new URL(apiUrl);url.searchParams.set('action',action);Object.keys(data).forEach(k=>{if(data[k]!==''&&data[k]!==null&&data[k]!==undefined)url.searchParams.set(k,String(data[k]))});response=await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json'}})}else{response=await fetch(apiUrl,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json'},body:JSON.stringify(Object.assign({action,csrf_token:csrfToken},data))})}const text=await response.text();let result;try{result=JSON.parse(text)}catch(e){throw new Error('Driver API returned HTTP '+response.status+'. '+(text.replace(/\s+/g,' ').trim().slice(0,220)||'Invalid server response.'))}if(!response.ok||!result.success)throw new Error(result.message||'Driver request failed.');if(result.data?.csrf_token)csrfToken=result.data.csrf_token;return result}
const initials=n=>String(n||'D').trim().split(/\s+/).slice(0,2).map(x=>x[0]||'').join('').toUpperCase()||'D';
const badge=s=>'<span class="driver-badge '+esc(String(s||'inactive').toLowerCase())+'">'+esc(s||'inactive')+'</span>';
function setVehicleOptions(records,selected=0){vehicles=Array.isArray(records)?records:[];const current=Number($('driverId').value||0);$('vehicleId').innerHTML='<option value="">Not Assigned</option>'+vehicles.map(v=>{const inactive=String(v.status||'').toLowerCase()!=='active',other=Number(v.driver_id||0)>0&&Number(v.driver_id)!==current,disabled=inactive||other,suffix=inactive?' (Inactive)':(other?' (Already Assigned)':'');return '<option value="'+Number(v.id)+'" '+(disabled?'disabled':'')+'>'+esc(v.vehicle_name+' • '+v.vehicle_number+suffix)+'</option>'}).join('');if(selected>0)$('vehicleId').value=String(selected)}
function stats(s={}){$('statTotal').textContent=Number(s.total_drivers||0);$('statActive').textContent=Number(s.active_drivers||0);$('statAssigned').textContent=Number(s.assigned_drivers||0);$('statExpiring').textContent=Number(s.expiring_licences||0)}
function render(data){drivers=data||[];$('driverListCount').textContent=drivers.length+' driver'+(drivers.length===1?'':'s');$('driverRecordInfo').textContent=drivers.length+' driver'+(drivers.length===1?'':'s')+' displayed';$('driverTableBody').innerHTML=drivers.map((d,i)=>'<tr><td>'+(i+1)+'</td><td><div class="driver-cell"><span class="driver-avatar">'+esc(initials(d.driver_name))+'</span><div><strong>'+esc(d.driver_name)+'</strong><small class="d-block text-muted">'+esc(d.alternate_mobile||'')+'</small></div></div></td><td>'+esc(d.mobile)+'</td><td><strong>'+esc(d.licence_number)+'</strong></td><td>'+badge(d.licence_status)+' <small>'+esc(d.licence_expiry_display||'-')+'</small></td><td>'+esc(d.vehicle_name?d.vehicle_name+' • '+d.vehicle_number:'Not Assigned')+'</td><td>'+Number(d.experience_years||0)+' year(s)</td><td>'+badge(d.status)+'</td><td><div class="driver-action-group"><button class="driver-action js-view" data-id="'+Number(d.id)+'"><i data-lucide="eye"></i></button><button class="driver-action edit js-edit" data-id="'+Number(d.id)+'"><i data-lucide="pencil"></i></button><button class="driver-action delete js-delete" data-id="'+Number(d.id)+'"><i data-lucide="trash-2"></i></button></div></td></tr>').join('')||'<tr><td colspan="9" class="driver-empty">No drivers found.</td></tr>';document.querySelectorAll('.js-view').forEach(b=>b.onclick=()=>viewDriver(Number(b.dataset.id)));document.querySelectorAll('.js-edit').forEach(b=>b.onclick=()=>openForm(Number(b.dataset.id)));document.querySelectorAll('.js-delete').forEach(b=>b.onclick=()=>openDelete(Number(b.dataset.id)));window.lucide?.createIcons()}
async function load(){try{const r=await request('list',{search:$('driverSearch').value.trim(),status:$('driverStatusFilter').value,assignment:$('driverVehicleFilter').value});setVehicleOptions(r.data.vehicles||vehicles,0);render(r.data.records||[]);stats(r.data.stats||{})}catch(e){$('driverTableBody').innerHTML='<tr><td colspan="9" class="driver-empty">'+esc(e.message)+'</td></tr>';toast(e.message,false)}}
function clearForm(){$('driverForm').reset();$('driverId').value='';$('driverStatus').value='active';$('experienceYears').value='0';$('driverModalTitle').textContent='Add Driver';$('driverModalSubtitle').textContent='Enter the driver information.';$('saveDriverBtnText').textContent='Add Driver';$('deleteDriverFromFormBtn').hidden=true;setVehicleOptions(vehicles,0)}
function fill(d){$('driverId').value=d.id;$('driverName').value=d.driver_name||'';$('driverMobile').value=d.mobile||'';$('alternateMobile').value=d.alternate_mobile||'';$('licenceNumber').value=d.licence_number||'';$('licenceExpiry').value=d.licence_expiry||'';$('dateOfBirth').value=d.date_of_birth||'';$('joiningDate').value=d.joining_date||'';$('experienceYears').value=Number(d.experience_years||0);$('driverAddress').value=d.address||'';$('driverNotes').value=d.notes||'';$('driverStatus').value=String(d.status||'active').toLowerCase();setVehicleOptions(vehicles,Number(d.vehicle_id||0));$('driverModalTitle').textContent='Update Driver';$('driverModalSubtitle').textContent=(d.licence_number||'')+' • '+(d.driver_name||'');$('saveDriverBtnText').textContent='Update Driver';$('deleteDriverFromFormBtn').hidden=false}
async function openForm(id=0){clearForm();if(id>0){try{const r=await request('detail',{id});vehicles=r.data.vehicles||vehicles;fill(r.data.record)}catch(e){toast(e.message,false);return}}bootstrap.Modal.getOrCreateInstance($('driverModal')).show();setTimeout(()=>$('driverName').focus(),250);window.lucide?.createIcons()}
function validate(){const d={id:Number($('driverId').value||0),driver_name:$('driverName').value.trim(),mobile:$('driverMobile').value.trim(),alternate_mobile:$('alternateMobile').value.trim(),licence_number:$('licenceNumber').value.trim().toUpperCase(),licence_expiry:$('licenceExpiry').value,date_of_birth:$('dateOfBirth').value,joining_date:$('joiningDate').value,experience_years:Number($('experienceYears').value||0),vehicle_id:Number($('vehicleId').value||0),address:$('driverAddress').value.trim(),notes:$('driverNotes').value.trim(),status:$('driverStatus').value};if(!d.driver_name)throw new Error('Driver Name is required.');if(!d.mobile)throw new Error('Mobile Number is required.');if(!d.licence_number)throw new Error('Driving Licence Number is required.');if(!d.licence_expiry)throw new Error('Licence Expiry Date is required.');return d}
async function save(e){e.preventDefault();let d;try{d=validate()}catch(err){toast(err.message,false);return}const b=$('saveDriverBtn');b.disabled=true;try{const r=await request('save',d,'POST');bootstrap.Modal.getInstance($('driverModal'))?.hide();toast(r.message,true);await load()}catch(err){toast(err.message,false)}finally{b.disabled=false}}
async function viewDriver(id){try{const r=await request('detail',{id}),d=r.data.record;$('viewDriverSubtitle').textContent=d.licence_number+' • '+d.driver_name;const fields=[['Driver Name',d.driver_name],['Mobile',d.mobile],['Alternate Mobile',d.alternate_mobile||'-'],['Licence Number',d.licence_number],['Licence Expiry',d.licence_expiry_display||'-'],['Date of Birth',d.date_of_birth_display||'-'],['Joining Date',d.joining_date_display||'-'],['Experience',Number(d.experience_years||0)+' year(s)'],['Assigned Vehicle',d.vehicle_name?d.vehicle_name+' • '+d.vehicle_number:'Not Assigned'],['Status',String(d.status||'').toUpperCase()],['Address',d.address||'-'],['Notes',d.notes||'-'],['Created At',d.created_at_display||'-']];$('viewDriverContent').innerHTML=fields.map(f=>'<div class="driver-view-item"><small>'+esc(f[0])+'</small><strong>'+esc(f[1])+'</strong></div>').join('');bootstrap.Modal.getOrCreateInstance($('viewDriverModal')).show()}catch(e){toast(e.message,false)}}
function openDelete(id){deleteDriverId=id;const d=drivers.find(x=>Number(x.id)===id);$('deleteDriverLabel').textContent=d?d.driver_name+' • '+d.licence_number:'Selected driver';bootstrap.Modal.getOrCreateInstance($('deleteDriverModal')).show();window.lucide?.createIcons()}
async function remove(){if(deleteDriverId<=0)return;const b=$('confirmDeleteDriverBtn');b.disabled=true;try{const r=await request('delete',{id:deleteDriverId},'POST');bootstrap.Modal.getInstance($('deleteDriverModal'))?.hide();bootstrap.Modal.getInstance($('driverModal'))?.hide();toast(r.message,true);deleteDriverId=0;await load()}catch(e){toast(e.message,false)}finally{b.disabled=false}}
$('addDriverBtn').onclick=()=>openForm(0);$('refreshDriverBtn').onclick=load;$('driverForm').onsubmit=save;$('licenceNumber').oninput=function(){this.value=this.value.toUpperCase()};$('confirmDeleteDriverBtn').onclick=remove;$('deleteDriverFromFormBtn').onclick=()=>openDelete(Number($('driverId').value||0));$('resetDriverFormBtn').onclick=()=>{const id=Number($('driverId').value||0);id?openForm(id):clearForm()};$('driverStatusFilter').onchange=load;$('driverVehicleFilter').onchange=load;$('driverSearch').oninput=()=>{clearTimeout(searchTimer);searchTimer=setTimeout(load,300)};$('resetDriverFiltersBtn').onclick=()=>{$('driverSearch').value='';$('driverStatusFilter').value='all';$('driverVehicleFilter').value='all';load()};load();window.lucide?.createIcons();
})();
</script>
<?php require dirname(__DIR__) . '/includes/layout-end.php'; ?>

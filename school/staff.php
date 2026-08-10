<?php
declare(strict_types=1);

$pageTitle = 'Staff Management';
$pageKey = 'teachers';
require dirname(__DIR__) . '/includes/layout-start.php';

$staffPermissions = function_exists('school_current_page_capabilities')
    ? school_current_page_capabilities($pageKey)
    : [
        'page_key' => $pageKey,
        'view' => false,
        'add' => false,
        'create' => false,
        'edit' => false,
        'delete' => false,
    ];

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['staff_csrf_token']) || !is_string($_SESSION['staff_csrf_token'])) {
    $_SESSION['staff_csrf_token'] = bin2hex(random_bytes(32));
}
$staffCsrf = $_SESSION['staff_csrf_token'];
?>
<style>
.sm-page{display:grid;gap:16px}
.sm-page .page-title{font-size:28px;line-height:1.1}
.sm-page .page-subtitle{margin-top:4px}
.sm-page .page-actions{gap:10px}
.sm-message{display:none}.sm-message.show{display:block}
.sm-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.sm-stat{border:0;border-radius:14px;min-height:110px;padding:18px 20px;display:flex;align-items:center;gap:14px;color:#fff;position:relative;overflow:hidden;box-shadow:0 12px 28px rgba(15,23,42,.08)}
.sm-stat::after{content:"";position:absolute;width:110px;height:110px;border-radius:50%;right:-38px;top:-40px;background:rgba(255,255,255,.08)}
.sm-stat.purple{background:linear-gradient(135deg,#7158e8,#4f46d9)}
.sm-stat.green{background:linear-gradient(135deg,#45c783,#1ea568)}
.sm-stat.blue{background:linear-gradient(135deg,#4696ef,#2469d5)}
.sm-stat.orange{background:linear-gradient(135deg,#ffb327,#ff8a17)}
.sm-stat-icon{width:50px;height:50px;border-radius:50%;background:rgba(255,255,255,.16);display:grid;place-items:center;flex:0 0 auto}
.sm-stat-icon svg{width:25px;height:25px}
.sm-stat strong{display:block;font-size:26px;line-height:1}
.sm-stat small{display:block;font-size:11px;font-weight:700;opacity:.94;margin-bottom:6px}
.sm-stat .trend{font-size:9px;font-weight:700;opacity:.94;margin-top:8px}
.sm-card{border-radius:14px;overflow:hidden}
.sm-card-head{padding:14px 16px;border-bottom:1px solid var(--border-soft,#e7ebf3);display:flex;align-items:center;justify-content:space-between;gap:10px}
.sm-card-head strong{font-size:14px}
.sm-filter{padding:14px 16px;display:grid;grid-template-columns:minmax(220px,1.35fr) repeat(4,minmax(140px,.7fr)) auto;gap:10px;border-bottom:1px solid var(--border-soft,#e7ebf3)}
.sm-table-wrap{overflow:auto}
.sm-table{min-width:1180px}
.sm-table th{font-size:10px;white-space:nowrap}
.sm-table td{font-size:11px;vertical-align:middle}
.sm-empty{padding:42px 18px!important;text-align:center!important;color:var(--text-muted,#64748b)}
.sm-staff{display:flex;align-items:center;gap:9px}
.sm-avatar{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;color:#fff;font-size:11px;font-weight:800;background:linear-gradient(135deg,#6d4ce7,#345fe0);flex:0 0 auto}
.sm-staff strong{display:block;font-size:12px}
.sm-staff small{display:block;margin-top:2px}
.sm-badge{display:inline-flex;align-items:center;padding:5px 9px;border-radius:999px;font-size:9px;font-weight:800;text-transform:capitalize;white-space:nowrap}
.sm-badge.active,.sm-badge.permanent{color:#16834f;background:#e8f8ef}
.sm-badge.inactive,.sm-badge.temporary{color:#9a6700;background:#fff7d6}
.sm-badge.left{color:#dc2626;background:#fff0f1}
.sm-badge.contract{color:#1d4ed8;background:#eaf2ff}
.sm-badge.part_time{color:#6d28d9;background:#f1eafe}
.sm-actions{display:flex;gap:5px;flex-wrap:wrap}
.sm-action{width:30px;height:30px;display:grid;place-items:center;border:1px solid #d7def1;border-radius:7px;background:var(--card-bg,#fff);color:#4f46e5}
.sm-action.warning{color:#c17a00}.sm-action.success{color:#16834f}.sm-action.danger{color:#dc2626}
.sm-action svg{width:13px;height:13px}
.sm-pagination{padding:14px 16px;border-top:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;gap:10px;align-items:center}
.sm-page-buttons{display:flex;gap:6px;flex-wrap:wrap}
.sm-page-button{min-width:34px;height:34px;border:1px solid var(--border-soft,#e7ebf3);background:var(--card-bg,#fff);border-radius:8px;font-size:11px;font-weight:800}
.sm-page-button.active{color:#fff;border-color:transparent;background:linear-gradient(135deg,#6547e8,#315ed8)}
.sm-page-button:disabled{opacity:.45}
.sm-form-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
.sm-form-grid .span-2{grid-column:span 2}.sm-form-grid .full{grid-column:1/-1}
.sm-section{grid-column:1/-1;display:flex;align-items:center;gap:9px;margin-top:5px;padding-bottom:8px;border-bottom:1px solid var(--border-soft,#e7ebf3)}
.sm-section strong{font-size:13px}.sm-section svg{width:16px;height:16px;color:#4f46e5}
#staffModal .modal-dialog{max-height:calc(100dvh - 32px);margin:16px auto}
#staffModal .modal-content{max-height:calc(100dvh - 32px);overflow:hidden}
#staffModal form{display:flex;flex-direction:column;max-height:calc(100dvh - 32px)}
#staffModal .modal-body{overflow-y:auto;min-height:0}
@media(max-width:1100px){.sm-filter{grid-template-columns:repeat(3,1fr)}.sm-form-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:900px){.sm-stats{grid-template-columns:repeat(2,1fr)}}
@media(max-width:575px){.sm-stats,.sm-filter,.sm-form-grid{grid-template-columns:1fr}.sm-form-grid .span-2,.sm-form-grid .full{grid-column:auto}.sm-card-head,.sm-pagination{align-items:flex-start;flex-direction:column}}
</style>

<div class="sm-page">
    <div class="page-heading">
        <div>
            <h1 class="page-title">Staff Management</h1>
            <p class="page-subtitle">Create and manage teachers, office staff, drivers and support employees.</p>
        </div>
        <div class="page-actions">
            <?php if (!empty($staffPermissions['add'])): ?>
                <button id="addStaffBtn" class="btn-ui btn-primary-ui" type="button" data-permission-action="add"><i data-lucide="user-plus"></i> Add Staff</button>
            <?php endif; ?>
            <a id="exportStaff" class="btn-ui" data-permission-action="view"><i data-lucide="download"></i> Export</a>
            <button id="refreshBtn" class="btn-ui" type="button" data-permission-action="view"><i data-lucide="refresh-cw"></i> Refresh</button>
        </div>
    </div>

    <div id="staffMessage" class="alert sm-message"></div>

    <section class="sm-stats">
        <article class="sm-stat purple"><span class="sm-stat-icon"><i data-lucide="users-round"></i></span><div><small>Total Staff</small><strong id="statTotal">0</strong><div class="trend">Current branch access</div></div></article>
        <article class="sm-stat green"><span class="sm-stat-icon"><i data-lucide="user-check"></i></span><div><small>Active Staff</small><strong id="statActive">0</strong><div class="trend">Currently working</div></div></article>
        <article class="sm-stat blue"><span class="sm-stat-icon"><i data-lucide="graduation-cap"></i></span><div><small>Teaching Staff</small><strong id="statTeaching">0</strong><div class="trend">Active teaching department</div></div></article>
        <article class="sm-stat orange"><span class="sm-stat-icon"><i data-lucide="building-2"></i></span><div><small>Departments</small><strong id="statDepartments">0</strong><div class="trend">Departments currently used</div></div></article>
    </section>

    <section class="ui-card sm-card">
        <div class="sm-card-head"><strong>Staff Directory</strong><small class="text-muted">Search, filter, edit or change staff status.</small></div>
        <div class="sm-filter">
            <input id="staffSearch" class="form-control" placeholder="Search staff code, name, mobile or email...">
            <select id="branchFilter" class="form-select"><option value="">All Branches</option></select>
            <select id="departmentFilter" class="form-select"><option value="">All Departments</option></select>
            <select id="designationFilter" class="form-select"><option value="">All Designations</option></select>
            <select id="statusFilter" class="form-select"><option value="all">All Status</option><option value="active">Active</option><option value="inactive">Inactive</option><option value="left">Left</option></select>
            <button id="filterReset" class="btn-ui" type="button"><i data-lucide="rotate-ccw"></i> Reset</button>
        </div>
        <div class="sm-table-wrap">
            <table class="data-table sm-table">
                <thead><tr><th>Staff</th><th>Staff Code</th><th>Branch</th><th>Department</th><th>Designation</th><th>Contact</th><th>Employment</th><th>Joining Date</th><th>Basic Salary</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody id="staffBody"><tr><td colspan="11" class="sm-empty">Loading staff...</td></tr></tbody>
            </table>
        </div>
        <div class="sm-pagination"><small id="recordInfo" class="text-muted">Loading...</small><div id="staffPagination" class="sm-page-buttons"></div></div>
    </section>
</div>

<div class="modal fade" id="staffModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <form id="staffForm" novalidate>
                <div class="modal-header">
                    <div><h5 id="staffModalTitle" class="modal-title">Add Staff</h5><small class="text-muted">Staff Code is generated automatically when left empty.</small></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input id="staffId" type="hidden">
                    <div class="sm-form-grid">
                        <div class="sm-section"><i data-lucide="contact"></i><strong>Personal Details</strong></div>
                        <div><label class="form-label">Staff Code</label><input id="staffCode" class="form-control" maxlength="40" placeholder="Auto generated"></div>
                        <div><label class="form-label">First Name *</label><input id="firstName" class="form-control" maxlength="100" required></div>
                        <div><label class="form-label">Last Name</label><input id="lastName" class="form-control" maxlength="100"></div>
                        <div><label class="form-label">Gender *</label><select id="gender" class="form-select" required><option value="male">Male</option><option value="female">Female</option><option value="other">Other</option></select></div>
                        <div><label class="form-label">Date of Birth</label><input id="dateOfBirth" class="form-control" type="date"></div>
                        <div><label class="form-label">Mobile Number *</label><input id="mobile" class="form-control" maxlength="20" required></div>
                        <div><label class="form-label">Alternate Mobile</label><input id="alternateMobile" class="form-control" maxlength="20"></div>
                        <div class="span-2"><label class="form-label">Email</label><input id="email" class="form-control" type="email" maxlength="150"></div>

                        <div class="sm-section"><i data-lucide="briefcase-business"></i><strong>Employment Details</strong></div>
                        <div><label class="form-label">Branch *</label><select id="branchId" class="form-select" required></select></div>
                        <div><label class="form-label">Department *</label><select id="departmentId" class="form-select" required></select></div>
                        <div><label class="form-label">Designation *</label><select id="designationId" class="form-select" required></select></div>
                        <div><label class="form-label">Employment Type *</label><select id="employmentType" class="form-select" required><option value="permanent">Permanent</option><option value="contract">Contract</option><option value="part_time">Part Time</option><option value="temporary">Temporary</option></select></div>
                        <div><label class="form-label">Joining Date *</label><input id="joiningDate" class="form-control" type="date" required></div>
                        <div><label class="form-label">Status *</label><select id="staffStatus" class="form-select" required><option value="active">Active</option><option value="inactive">Inactive</option><option value="left">Left</option></select></div>
                        <div class="span-2"><label class="form-label">Qualification</label><input id="qualification" class="form-control" maxlength="180" placeholder="Example: B.Ed, M.Sc Mathematics"></div>
                        <div><label class="form-label">Experience (Years)</label><input id="experienceYears" class="form-control" type="number" min="0" max="80" step="0.1" value="0"></div>
                        <div><label class="form-label">Basic Salary</label><input id="basicSalary" class="form-control" type="number" min="0" step="0.01" value="0"></div>

                        <div class="sm-section"><i data-lucide="shield-check"></i><strong>Contact and Other Details</strong></div>
                        <div><label class="form-label">Emergency Contact Name</label><input id="emergencyName" class="form-control" maxlength="150"></div>
                        <div><label class="form-label">Emergency Contact Mobile</label><input id="emergencyMobile" class="form-control" maxlength="20"></div>
                        <div class="full"><label class="form-label">Address</label><textarea id="address" class="form-control" rows="3"></textarea></div>
                        <div class="full"><label class="form-label">Notes</label><textarea id="notes" class="form-control" rows="3" maxlength="500"></textarea></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-ui" data-bs-dismiss="modal">Cancel</button>
                    <?php if (!empty($staffPermissions['add']) || !empty($staffPermissions['edit'])): ?>
                        <button id="saveStaffBtn" type="submit" class="btn-ui btn-primary-ui"><i data-lucide="save"></i> Save Staff</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="staffViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Staff Details</h5>
                    <small class="text-muted">Complete staff information.</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="staffViewDetails" class="sm-form-grid"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-ui" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
'use strict';
const apiUrl=new URL('../api/staff.php',window.location.href).href;
let permissions=<?=json_encode($staffPermissions, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;
let csrfToken=<?=json_encode($staffCsrf)?>,meta={branches:[],departments:[],designations:[]},rows=[],page=1,pagination={total:0,page:1,last_page:1,per_page:10},searchTimer=null;
const $=id=>document.getElementById(id);
const esc=value=>String(value??'').replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
const money=value=>new Intl.NumberFormat('en-IN',{style:'currency',currency:'INR',maximumFractionDigits:2}).format(Number(value||0));
const badge=value=>`<span class="sm-badge ${esc(String(value||'').toLowerCase())}">${esc(String(value||'-').replaceAll('_',' '))}</span>`;
function localToday(){const now=new Date();return new Date(now.getTime()-now.getTimezoneOffset()*60000).toISOString().slice(0,10)}
async function request(action,data={},method='GET'){
    let response;
    if(method==='GET'){
        const url=new URL(apiUrl);url.searchParams.set('action',action);
        Object.entries(data).forEach(([key,value])=>{if(value!==''&&value!==null&&value!==undefined)url.searchParams.set(key,String(value))});
        response=await fetch(url,{headers:{Accept:'application/json'},credentials:'same-origin'});
    }else{
        response=await fetch(apiUrl,{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json'},credentials:'same-origin',body:JSON.stringify({action,csrf_token:csrfToken,...data})});
    }
    const text=await response.text();let result;
    try{result=JSON.parse(text)}catch{throw new Error(`Staff API returned HTTP ${response.status}. ${text.replace(/\s+/g,' ').trim().slice(0,220)||'Invalid server response.'}`)}
    if(!response.ok||!result.success)throw new Error(result.message||'Request failed.');
    if(result.data?.csrf_token)csrfToken=result.data.csrf_token;
    return result;
}
function message(text,success=false){const box=$('staffMessage');box.className='alert sm-message show '+(success?'alert-success':'alert-danger');box.textContent=text;clearTimeout(box._timer);box._timer=setTimeout(()=>box.className='alert sm-message',7000)}
function fill(id,items,key,label,first='',firstValue=''){const el=$(id);el.innerHTML=(first?`<option value="${esc(firstValue)}">${esc(first)}</option>`:'')+items.map(item=>`<option value="${esc(item[key])}">${esc(typeof label==='function'?label(item):item[label])}</option>`).join('')}
function activeDepartments(){return(meta.departments||[]).filter(row=>row.status==='active')}
function activeDesignations(departmentId=0){return(meta.designations||[]).filter(row=>row.status==='active'&&(!departmentId||!Number(row.department_id)||Number(row.department_id)===departmentId))}
function refreshDesignationSelect(selectId,departmentId,selected=''){
    const items=activeDesignations(Number(departmentId||0));fill(selectId,items,'id','designation_name','Select Designation','');
    if(selected&&[...$(selectId).options].some(option=>option.value===String(selected)))$(selectId).value=String(selected);
}
function refreshDesignationFilter(){const departmentId=Number($('departmentFilter').value||0),selected=$('designationFilter').value;fill('designationFilter',activeDesignations(departmentId),'id','designation_name','All Designations','');if(selected&&[...$('designationFilter').options].some(option=>option.value===selected))$('designationFilter').value=selected}
function filters(){return{page,per_page:10,search:$('staffSearch').value.trim(),branch_id:$('branchFilter').value,department_id:$('departmentFilter').value,designation_id:$('designationFilter').value,status:$('statusFilter').value}}
function renderStats(stats={}){$('statTotal').textContent=Number(stats.total_staff||0);$('statActive').textContent=Number(stats.active_staff||0);$('statTeaching').textContent=Number(stats.teaching_staff||0);$('statDepartments').textContent=Number(stats.used_departments||0)}
function initials(row){return((row.first_name||'').charAt(0)+(row.last_name||'').charAt(0)).toUpperCase()||'?'}
function renderRows(){
    $('staffBody').innerHTML=rows.map(row=>{
        const nextStatus=row.status==='active'?'inactive':'active';
        const statusIcon=nextStatus==='active'?'user-check':'user-x';
        const statusClass=nextStatus==='active'?'success':'warning';
        let actions='';
        if(permissions.view){
            actions+=`<button class="sm-action js-view" data-id="${Number(row.id)}" title="View" type="button" data-permission-action="view"><i data-lucide="eye"></i></button>`;
        }
        if(permissions.edit){
            actions+=`<button class="sm-action js-edit" data-id="${Number(row.id)}" title="Edit" type="button" data-permission-action="edit"><i data-lucide="pencil"></i></button>`;
            actions+=`<button class="sm-action ${statusClass} js-status" data-id="${Number(row.id)}" data-status="${nextStatus}" title="${nextStatus==='active'?'Activate':'Deactivate'}" type="button" data-permission-action="edit"><i data-lucide="${statusIcon}"></i></button>`;
        }
        if(permissions.delete){
            actions+=`<button class="sm-action danger js-delete" data-id="${Number(row.id)}" title="Delete" type="button" data-permission-action="delete"><i data-lucide="trash-2"></i></button>`;
        }
        if(actions==='')actions='<span class="text-muted">—</span>';
        return `<tr>
            <td><div class="sm-staff"><span class="sm-avatar">${esc(initials(row))}</span><div><strong>${esc(row.staff_name)}</strong><small class="text-muted">${esc(row.email||'No email')}</small></div></div></td>
            <td><strong>${esc(row.staff_code)}</strong></td>
            <td>${esc(row.branch_name)}</td>
            <td>${esc(row.department_name)}</td>
            <td>${esc(row.designation_name)}</td>
            <td><strong>${esc(row.mobile)}</strong>${row.alternate_mobile?`<div class="text-muted">${esc(row.alternate_mobile)}</div>`:''}</td>
            <td>${badge(row.employment_type)}</td>
            <td>${esc(row.joining_date_display||row.joining_date)}</td>
            <td><strong>${money(row.basic_salary)}</strong></td>
            <td>${badge(row.status)}</td>
            <td><div class="sm-actions">${actions}</div></td>
        </tr>`;
    }).join('')||'<tr><td colspan="11" class="sm-empty">No staff records found.</td></tr>';
    document.querySelectorAll('.js-view').forEach(button=>button.onclick=()=>openView(Number(button.dataset.id)));
    document.querySelectorAll('.js-edit').forEach(button=>button.onclick=()=>openEdit(Number(button.dataset.id)));
    document.querySelectorAll('.js-status').forEach(button=>button.onclick=()=>changeStatus(Number(button.dataset.id),button.dataset.status));
    document.querySelectorAll('.js-delete').forEach(button=>button.onclick=()=>deleteStaff(Number(button.dataset.id)));
    window.lucide?.createIcons();
}
function renderPagination(){
    const total=Number(pagination.total||0),current=Number(pagination.page||1),last=Number(pagination.last_page||1),from=total?((current-1)*Number(pagination.per_page||10))+1:0,to=Math.min(total,current*Number(pagination.per_page||10));
    $('recordInfo').textContent=total?`Showing ${from}-${to} of ${total} staff`:'0 staff records';
    let html=`<button class="sm-page-button" data-page="${Math.max(1,current-1)}" ${current<=1?'disabled':''}>‹</button>`;
    const start=Math.max(1,current-2),end=Math.min(last,current+2);
    for(let i=start;i<=end;i++)html+=`<button class="sm-page-button ${i===current?'active':''}" data-page="${i}">${i}</button>`;
    html+=`<button class="sm-page-button" data-page="${Math.min(last,current+1)}" ${current>=last?'disabled':''}>›</button>`;
    $('staffPagination').innerHTML=html;
    document.querySelectorAll('#staffPagination [data-page]').forEach(button=>button.onclick=()=>{if(button.disabled)return;page=Number(button.dataset.page);loadStaff()});
}
function updateExport(){const query=new URLSearchParams({action:'export',search:$('staffSearch').value.trim(),branch_id:$('branchFilter').value,department_id:$('departmentFilter').value,designation_id:$('designationFilter').value,status:$('statusFilter').value});$('exportStaff').href=apiUrl+'?'+query}
async function loadStaff(){
    try{const result=await request('list',filters());rows=result.data.records||[];pagination=result.data.pagination||pagination;page=Number(pagination.page||1);renderStats(result.data.stats||{});renderRows();renderPagination();updateExport()}
    catch(error){message(error.message)}
}
function resetForm(){
    $('staffForm').reset();$('staffId').value='';$('staffCode').value='';$('gender').value='male';$('employmentType').value='permanent';$('staffStatus').value='active';$('joiningDate').value=localToday();$('experienceYears').value='0';$('basicSalary').value='0';
    fill('branchId',meta.branches||[],'id','branch_name','Select Branch','');if((meta.branches||[]).length===1)$('branchId').value=String(meta.branches[0].id);
    fill('departmentId',activeDepartments(),'id','department_name','Select Department','');refreshDesignationSelect('designationId',0);
}
function openCreate(){if(!permissions.add){message('You do not have permission to add staff.');return}resetForm();$('staffModalTitle').textContent='Add Staff';const save=$('saveStaffBtn');if(save){save.dataset.permissionAction='add';save.style.display=''}bootstrap.Modal.getOrCreateInstance($('staffModal')).show();setTimeout(()=>$('firstName').focus(),250)}
async function openView(id){
    if(!permissions.view){message('You do not have permission to view staff details.');return}
    try{
        const result=await request('detail',{id}),record=result.data.record;
        const fields=[
            ['Staff Code',record.staff_code],['Staff Name',record.staff_name||`${record.first_name||''} ${record.last_name||''}`.trim()],
            ['Branch',record.branch_name||'-'],['Department',record.department_name||'-'],['Designation',record.designation_name||'-'],
            ['Gender',record.gender],['Date of Birth',record.date_of_birth_display||record.date_of_birth||'-'],['Mobile',record.mobile],
            ['Alternate Mobile',record.alternate_mobile||'-'],['Email',record.email||'-'],['Employment Type',String(record.employment_type||'').replaceAll('_',' ')],
            ['Joining Date',record.joining_date_display||record.joining_date],['Qualification',record.qualification||'-'],['Experience',`${record.experience_years||0} year(s)`],
            ['Basic Salary',money(record.basic_salary)],['Status',record.status],['Emergency Contact',record.emergency_contact_name||'-'],
            ['Emergency Mobile',record.emergency_contact_mobile||'-'],['Address',record.address||'-'],['Notes',record.notes||'-']
        ];
        $('staffViewDetails').innerHTML=fields.map(([label,value])=>`<div><label class="form-label">${esc(label)}</label><div><strong>${esc(value??'-')}</strong></div></div>`).join('');
        bootstrap.Modal.getOrCreateInstance($('staffViewModal')).show();
    }catch(error){message(error.message)}
}
async function openEdit(id){
    if(!permissions.edit){message('You do not have permission to edit staff.');return}
    try{
        const result=await request('detail',{id}),record=result.data.record;resetForm();$('staffModalTitle').textContent='Edit Staff';$('staffId').value=String(record.id);$('staffCode').value=record.staff_code||'';$('firstName').value=record.first_name||'';$('lastName').value=record.last_name||'';$('gender').value=record.gender||'male';$('dateOfBirth').value=record.date_of_birth||'';$('mobile').value=record.mobile||'';$('alternateMobile').value=record.alternate_mobile||'';$('email').value=record.email||'';$('branchId').value=String(record.branch_id||'');$('departmentId').value=String(record.department_id||'');refreshDesignationSelect('designationId',record.department_id,record.designation_id);$('employmentType').value=record.employment_type||'permanent';$('joiningDate').value=record.joining_date||'';$('staffStatus').value=record.status||'active';$('qualification').value=record.qualification||'';$('experienceYears').value=record.experience_years||0;$('basicSalary').value=record.basic_salary||0;$('emergencyName').value=record.emergency_contact_name||'';$('emergencyMobile').value=record.emergency_contact_mobile||'';$('address').value=record.address||'';$('notes').value=record.notes||'';const save=$('saveStaffBtn');if(save){save.dataset.permissionAction='edit';save.style.display=''}bootstrap.Modal.getOrCreateInstance($('staffModal')).show();
    }catch(error){message(error.message)}
}
function formData(){return{id:Number($('staffId').value||0),staff_code:$('staffCode').value.trim(),first_name:$('firstName').value.trim(),last_name:$('lastName').value.trim(),gender:$('gender').value,date_of_birth:$('dateOfBirth').value,mobile:$('mobile').value.trim(),alternate_mobile:$('alternateMobile').value.trim(),email:$('email').value.trim(),branch_id:Number($('branchId').value||0),department_id:Number($('departmentId').value||0),designation_id:Number($('designationId').value||0),employment_type:$('employmentType').value,joining_date:$('joiningDate').value,status:$('staffStatus').value,qualification:$('qualification').value.trim(),experience_years:Number($('experienceYears').value||0),basic_salary:Number($('basicSalary').value||0),emergency_contact_name:$('emergencyName').value.trim(),emergency_contact_mobile:$('emergencyMobile').value.trim(),address:$('address').value.trim(),notes:$('notes').value.trim()}}
async function saveStaff(event){
    event.preventDefault();const isEdit=Number($('staffId').value||0)>0;const allowed=isEdit?permissions.edit:permissions.add;if(!allowed){message(`You do not have permission to ${isEdit?'edit':'add'} staff.`);return}if(!$('staffForm').checkValidity()){$('staffForm').reportValidity();return}
    const button=$('saveStaffBtn');if(!button)return;const original=button.innerHTML;button.disabled=true;button.textContent='Saving...';
    try{const result=await request('save',formData(),'POST');bootstrap.Modal.getInstance($('staffModal'))?.hide();message(result.message,true);await loadStaff()}
    catch(error){message(error.message)}finally{button.disabled=false;button.innerHTML=original;window.lucide?.createIcons()}
}
async function changeStatus(id,status){if(!permissions.edit){message('You do not have permission to edit staff status.');return}if(!confirm(`${status==='active'?'Activate':'Deactivate'} this staff member?`))return;try{const result=await request('status',{id,status},'POST');message(result.message,true);await loadStaff()}catch(error){message(error.message)}}
async function deleteStaff(id){if(!permissions.delete){message('You do not have permission to delete staff.');return}if(!confirm('Delete this staff record? The record will be removed from the active staff directory.'))return;try{const result=await request('delete',{id},'POST');message(result.message,true);await loadStaff()}catch(error){message(error.message)}}
async function init(){
    try{
        const result=await request('meta');meta=result.data;permissions={...permissions,...(result.data.capabilities||{})};
        fill('branchFilter',meta.branches||[],'id','branch_name','All Branches','');if((meta.branches||[]).length===1){$('branchFilter').value=String(meta.branches[0].id);$('branchFilter').disabled=true}
        fill('departmentFilter',activeDepartments(),'id','department_name','All Departments','');refreshDesignationFilter();resetForm();await loadStaff();
    }catch(error){message(error.message)}
}
if($('addStaffBtn'))$('addStaffBtn').onclick=openCreate;$('refreshBtn').onclick=()=>loadStaff();if($('saveStaffBtn'))$('staffForm').onsubmit=saveStaff;$('departmentId').onchange=()=>refreshDesignationSelect('designationId',$('departmentId').value);$('departmentFilter').onchange=()=>{refreshDesignationFilter();page=1;loadStaff()};$('designationFilter').onchange=()=>{page=1;loadStaff()};$('branchFilter').onchange=()=>{page=1;loadStaff()};$('statusFilter').onchange=()=>{page=1;loadStaff()};$('staffSearch').oninput=()=>{clearTimeout(searchTimer);searchTimer=setTimeout(()=>{page=1;loadStaff()},300)};$('filterReset').onclick=()=>{$('staffSearch').value='';if(!$('branchFilter').disabled)$('branchFilter').value='';$('departmentFilter').value='';refreshDesignationFilter();$('designationFilter').value='';$('statusFilter').value='all';page=1;loadStaff()};
init();window.lucide?.createIcons();
})();
</script>
<?php require dirname(__DIR__) . '/includes/layout-end.php'; ?>

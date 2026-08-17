<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_login();

$pageTitle = 'Branch Management';
$pageKey = 'super_admin_branches';
$sidebarFile = __DIR__ . '/sidebar.php';

$currentUser = function_exists('current_user') ? current_user() : [];
$currentUser = is_array($currentUser) ? $currentUser : [];
$roleId = (int)($currentUser['role_id'] ?? $_SESSION['role_id'] ?? 0);
$roleKey = strtolower(trim((string)($currentUser['role_key'] ?? $_SESSION['role_key'] ?? '')));

if ($roleKey === '' && isset($pdo) && $pdo instanceof PDO && $roleId > 0) {
    try {
        $roleStmt = $pdo->prepare("SELECT role_key FROM roles WHERE id=:id LIMIT 1");
        $roleStmt->execute(['id' => $roleId]);
        $roleKey = strtolower(trim((string)($roleStmt->fetchColumn() ?: '')));
    } catch (Throwable $e) {
        error_log('super-admin/branches.php role lookup: ' . $e->getMessage());
    }
}

$isSuperAdmin = $roleId === 1 || in_array($roleKey, [
    'super_admin', 'super-admin', 'superadministrator',
    'super_administrator', 'super-administrator'
], true);

if (!$isSuperAdmin) {
    http_response_code(403);
    exit('Access denied.');
}

if (empty($_SESSION['branch_management_csrf']) || !is_string($_SESSION['branch_management_csrf'])) {
    $_SESSION['branch_management_csrf'] = bin2hex(random_bytes(32));
}
$branchCsrf = $_SESSION['branch_management_csrf'];

$baseUrl = defined('BASE_URL') ? rtrim((string)BASE_URL, '/') . '/' : '../';

require dirname(__DIR__) . '/includes/layout-start.php';
?>
<style>
*{box-sizing:border-box}
.pbm-page{display:grid;gap:16px;width:100%;min-width:0}
.pbm-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px}
.pbm-head h1{margin:0;font-size:clamp(24px,2vw,30px)}
.pbm-head p{margin:5px 0 0}
.pbm-actions{display:flex;gap:9px;flex-wrap:wrap}
.pbm-actions .btn-ui{min-height:40px;white-space:nowrap}
.pbm-message{display:none;margin:0}.pbm-message.show{display:block}
.pbm-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:13px}
.pbm-stat{min-width:0;padding:16px;border-radius:14px;color:#fff;overflow:hidden;position:relative}
.pbm-stat:after{content:"";position:absolute;width:92px;height:92px;right:-28px;bottom:-36px;border-radius:50%;background:rgba(255,255,255,.13)}
.pbm-stat.purple{background:linear-gradient(135deg,#7047ef,#5137cf)}
.pbm-stat.green{background:linear-gradient(135deg,#45c783,#1ea568)}
.pbm-stat.orange{background:linear-gradient(135deg,#ffb327,#ff8a17)}
.pbm-stat.blue{background:linear-gradient(135deg,#4388ef,#2563d9)}
.pbm-stat small{display:block;opacity:.88;font-size:10px}.pbm-stat strong{display:block;margin-top:8px;font-size:24px;line-height:1}
.pbm-card{border-radius:14px;overflow:hidden;min-width:0}
.pbm-filter{display:grid;grid-template-columns:minmax(220px,1.4fr) repeat(3,minmax(150px,.65fr)) auto;gap:10px;padding:14px 16px;border-bottom:1px solid var(--border-soft,#e7ebf3)}
.pbm-filter .form-control,.pbm-filter .form-select,.pbm-filter .btn-ui{min-height:41px}
.pbm-table-wrap{overflow:auto;min-width:0}.pbm-table{min-width:1180px;margin:0}.pbm-table th{font-size:10px;white-space:nowrap}.pbm-table td{font-size:11px;vertical-align:middle}
.pbm-branch{display:flex;align-items:center;gap:10px;min-width:210px}.pbm-icon{width:42px;height:42px;border-radius:10px;flex:0 0 auto;display:grid;place-items:center;color:#fff;background:linear-gradient(135deg,#7047ef,#3559dc);font-weight:800}.pbm-icon svg{width:19px;height:19px}.pbm-copy{min-width:0}.pbm-copy strong,.pbm-copy small{display:block}.pbm-copy strong{font-size:11px;color:var(--text-main,#101b46)}.pbm-copy small{margin-top:3px;color:var(--text-muted,#64748b)}
.pbm-badge{display:inline-flex;align-items:center;padding:5px 9px;border-radius:999px;font-size:9px;font-weight:800;text-transform:capitalize}.pbm-badge.active{color:#16834f;background:#e8f8ef}.pbm-badge.inactive{color:#dc2626;background:#fff0f1}.pbm-badge.main{color:#4338ca;background:#eef2ff}.pbm-badge.branch{color:#475569;background:#f1f5f9}
.pbm-actions-cell{display:flex;gap:6px;flex-wrap:nowrap}.pbm-action{width:31px;height:31px;border:1px solid #d7def1;border-radius:8px;background:var(--card-bg,#fff);display:grid;place-items:center;color:#4f46e5}.pbm-action svg{width:14px;height:14px}.pbm-action.delete{color:#dc2626}.pbm-action:disabled{opacity:.45;cursor:not-allowed}
.pbm-empty{padding:46px 18px!important;text-align:center!important;color:var(--text-muted,#64748b)}
.pbm-pagination{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:13px 16px;border-top:1px solid var(--border-soft,#e7ebf3)}.pbm-pagination-actions{display:flex;align-items:center;gap:7px;flex-wrap:wrap}.pbm-page-btn{min-width:34px;height:34px;padding:0 10px;border:1px solid var(--border-soft,#dbe2ef);border-radius:8px;background:var(--card-bg,#fff);color:var(--text-main,#101b46)}.pbm-page-btn.active{color:#fff;background:var(--brand-1,#6747e8);border-color:var(--brand-1,#6747e8)}.pbm-page-btn:disabled{opacity:.5;cursor:not-allowed}
.pbm-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px}.pbm-form-grid .full{grid-column:1/-1}.pbm-form-grid .form-control,.pbm-form-grid .form-select{width:100%;min-width:0;min-height:42px}.pbm-section-title{grid-column:1/-1;display:flex;align-items:center;gap:8px;margin-top:4px;padding:10px 12px;border-radius:10px;color:#4338ca;background:#eef2ff;font-size:11px;font-weight:800}.pbm-section-title svg{width:16px}
.pbm-view-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:11px}.pbm-view-item{min-width:0;padding:12px;border:1px solid var(--border-soft,#e7ebf3);border-radius:10px;background:rgba(99,102,241,.035)}.pbm-view-item.full{grid-column:1/-1}.pbm-view-item small{display:block;color:var(--text-muted,#64748b);font-size:9px}.pbm-view-item strong{display:block;margin-top:5px;font-size:11px;overflow-wrap:anywhere}
#branchModal .modal-dialog,#viewBranchModal .modal-dialog{max-height:calc(100dvh - 32px);margin:16px auto}#branchModal .modal-content,#viewBranchModal .modal-content{max-height:calc(100dvh - 32px);overflow:hidden}#branchModal form{display:flex;flex-direction:column;max-height:calc(100dvh - 32px)}#branchModal .modal-body,#viewBranchModal .modal-body{overflow-y:auto;min-height:0}
@media(max-width:1100px){.pbm-filter{grid-template-columns:repeat(2,minmax(0,1fr))}.pbm-filter .search{grid-column:1/-1}}
@media(max-width:900px){.pbm-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.pbm-head{flex-direction:column;align-items:stretch}}
@media(max-width:767px){.pbm-actions{display:grid;grid-template-columns:1fr;width:100%}.pbm-actions .btn-ui{width:100%;justify-content:center}.pbm-form-grid,.pbm-view-grid{grid-template-columns:1fr}.pbm-form-grid .full,.pbm-view-item.full,.pbm-section-title{grid-column:auto}.pbm-pagination{flex-direction:column;align-items:stretch}.pbm-pagination-actions{justify-content:center}}
@media(max-width:575px){.pbm-stats,.pbm-filter{grid-template-columns:1fr}.pbm-filter .search{grid-column:auto}}
</style>

<div class="pbm-page">
    <div class="pbm-head">
        <div>
            <h1>Branch Management</h1>
            <p class="text-muted">View and manage school branches from the Super Admin panel.</p>
        </div>
        <div class="pbm-actions">
            <button id="refreshBranchesBtn" class="btn-ui" type="button"><i data-lucide="refresh-cw"></i> Refresh</button>
            <button id="addBranchBtn" class="btn-ui btn-primary-ui" type="button" data-permission-action="add"><i data-lucide="plus"></i> Add Branch</button>
        </div>
    </div>

    <div id="branchMessage" class="alert pbm-message" role="alert"></div>

    <section class="pbm-stats">
        <article class="pbm-stat purple"><small>Total Branches</small><strong id="totalBranches">0</strong></article>
        <article class="pbm-stat green"><small>Active Branches</small><strong id="activeBranches">0</strong></article>
        <article class="pbm-stat orange"><small>Main Branches</small><strong id="mainBranches">0</strong></article>
        <article class="pbm-stat blue"><small>Schools With Branches</small><strong id="branchSchools">0</strong></article>
    </section>

    <section class="ui-card pbm-card">
        <div class="pbm-filter">
            <input id="branchSearch" class="form-control search" type="search" placeholder="Search branch name, code, school, phone or address...">
            <select id="schoolFilter" class="form-select"><option value="">All Schools</option></select>
            <select id="branchTypeFilter" class="form-select">
                <option value="">All Branch Types</option>
                <option value="main">Main Branch</option>
                <option value="branch">Other Branch</option>
            </select>
            <select id="statusFilter" class="form-select">
                <option value="">All Statuses</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
            <button id="clearBranchFiltersBtn" class="btn-ui" type="button"><i data-lucide="filter-x"></i> Clear</button>
        </div>

        <div class="pbm-table-wrap">
            <table class="table data-table pbm-table">
                <thead><tr>
                    <th>Branch</th><th>Branch Code</th><th>School</th><th>Type</th><th>Phone</th><th>Users</th><th>Students</th><th>Registered</th><th>Status</th><th class="text-end">Actions</th>
                </tr></thead>
                <tbody id="branchTableBody"><tr><td colspan="10" class="pbm-empty">Loading branches...</td></tr></tbody>
            </table>
        </div>

        <div class="pbm-pagination">
            <small id="branchPaginationInfo" class="text-muted">Showing 0 records</small>
            <div id="branchPagination" class="pbm-pagination-actions"></div>
        </div>
    </section>
</div>

<div class="modal fade" id="branchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="branchForm" novalidate>
                <div class="modal-header">
                    <div><h5 id="branchModalTitle" class="modal-title">Add Branch</h5><small class="text-muted">Enter the branch information.</small></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input id="branchId" type="hidden" value="0">
                    <div class="pbm-form-grid">
                        <div class="pbm-section-title"><i data-lucide="git-branch"></i> Branch Information</div>
                        <div>
                            <label class="form-label" for="branchSchool">School *</label>
                            <select id="branchSchool" class="form-select" required><option value="">Select School</option></select>
                        </div>
                        <div>
                            <label class="form-label" for="branchName">Branch Name *</label>
                            <input id="branchName" class="form-control" maxlength="150" required placeholder="Example: Main Campus">
                        </div>
                        <div>
                            <label class="form-label" for="branchCode">Branch Code</label>
                            <input id="branchCode" class="form-control" maxlength="30" placeholder="Auto generated if empty">
                        </div>
                        <div>
                            <label class="form-label" for="branchPhone">Phone</label>
                            <input id="branchPhone" class="form-control" maxlength="20" inputmode="tel" placeholder="Branch contact number">
                        </div>
                        <div>
                            <label class="form-label" for="branchMain">Branch Type *</label>
                            <select id="branchMain" class="form-select" required>
                                <option value="0">Other Branch</option>
                                <option value="1">Main Branch</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" for="branchStatus">Status *</label>
                            <select id="branchStatus" class="form-select" required><option value="active">Active</option><option value="inactive">Inactive</option></select>
                        </div>
                        <div class="pbm-section-title"><i data-lucide="map-pin"></i> Address</div>
                        <div class="full">
                            <label class="form-label" for="branchAddress">Branch Address</label>
                            <textarea id="branchAddress" class="form-control" rows="4" maxlength="1000" placeholder="Complete branch address"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn-ui" type="button" data-bs-dismiss="modal">Cancel</button>
                    <button id="saveBranchBtn" class="btn-ui btn-primary-ui" type="submit"><i data-lucide="save"></i> <span id="saveBranchText">Save Branch</span></button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="viewBranchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Branch Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body"><div id="viewBranchContent" class="pbm-view-grid"></div></div>
            <div class="modal-footer"><button class="btn-ui" type="button" data-bs-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>

<script>
(function(){
'use strict';
const apiUrl = new URL(<?= json_encode($baseUrl . 'api/branches.php', JSON_UNESCAPED_SLASHES) ?>, window.location.href).href;
let csrfToken = <?= json_encode($branchCsrf, JSON_UNESCAPED_SLASHES) ?>;
let currentPage = 1, searchTimer = null, records = [], schools = [];
let pagination = {total:0,page:1,last_page:1,per_page:10};
const $ = id => document.getElementById(id);
const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
const formatDate = v => { if(!v) return '—'; const d = new Date(String(v).replace(' ','T')); return Number.isNaN(d.getTime()) ? String(v) : d.toLocaleDateString('en-GB'); };
const modal = () => window.bootstrap ? new window.bootstrap.Modal($('branchModal')) : null;
const viewModal = () => window.bootstrap ? new window.bootstrap.Modal($('viewBranchModal')) : null;
let branchModal = null, branchViewModal = null;

function message(text, ok=false){
    const box=$('branchMessage'); box.textContent=text; box.className='alert pbm-message show ' + (ok?'alert-success':'alert-danger');
    clearTimeout(message.timer); message.timer=setTimeout(()=>box.className='alert pbm-message',4500);
    if(typeof window.schoolToast==='function') window.schoolToast(ok?'success':'error',text,ok?'Success':'Action failed');
}

async function request(action,data={},method='GET'){
    const url=new URL(apiUrl); url.searchParams.set('action',action);
    const opt={method,credentials:'same-origin',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}};
    if(method==='GET') Object.entries(data).forEach(([k,v])=>{ if(v!==''&&v!==null&&v!==undefined) url.searchParams.set(k,String(v)); });
    else {
        const form=new FormData(); form.set('action',action); form.set('csrf_token',csrfToken);
        Object.entries(data).forEach(([k,v])=>form.set(k,String(v ?? ''))); opt.body=form;
    }
    const res=await fetch(url,opt), text=await res.text(); let json;
    try{json=JSON.parse(text);}catch{console.error(text);throw new Error(text || `HTTP ${res.status}`);}
    if(json.data?.csrf_token) csrfToken=json.data.csrf_token;
    if(!res.ok||!json.success) throw new Error(json.message||`HTTP ${res.status}`);
    return json.data||{};
}

function renderSchools(){
    const filterValue=$('schoolFilter').value, formValue=$('branchSchool').value;
    const options=schools.map(s=>`<option value="${Number(s.id)}">${esc(s.school_name)}${s.tenant_code?` (${esc(s.tenant_code)})`:''}</option>`).join('');
    $('schoolFilter').innerHTML='<option value="">All Schools</option>'+options;
    $('branchSchool').innerHTML='<option value="">Select School</option>'+options;
    if([...$('schoolFilter').options].some(o=>o.value===filterValue)) $('schoolFilter').value=filterValue;
    if([...$('branchSchool').options].some(o=>o.value===formValue)) $('branchSchool').value=formValue;
}

function stats(s={}){ $('totalBranches').textContent=Number(s.total||0); $('activeBranches').textContent=Number(s.active||0); $('mainBranches').textContent=Number(s.main||0); $('branchSchools').textContent=Number(s.schools||0); }

function renderTable(){
    if(!records.length){ $('branchTableBody').innerHTML='<tr><td colspan="10" class="pbm-empty">No branches found.</td></tr>'; window.lucide?.createIcons(); return; }
    $('branchTableBody').innerHTML=records.map(r=>`
        <tr>
            <td><div class="pbm-branch"><span class="pbm-icon"><i data-lucide="git-branch"></i></span><div class="pbm-copy"><strong>${esc(r.branch_name)}</strong><small>${esc(r.address||'Address not set')}</small></div></div></td>
            <td><strong>${esc(r.branch_code)}</strong></td>
            <td><div class="pbm-copy"><strong>${esc(r.school_name||'—')}</strong><small>${esc(r.tenant_code||'')}</small></div></td>
            <td><span class="pbm-badge ${Number(r.is_main)===1?'main':'branch'}">${Number(r.is_main)===1?'Main Branch':'Branch'}</span></td>
            <td>${esc(r.phone||'—')}</td>
            <td>${Number(r.user_count||0).toLocaleString('en-IN')}</td>
            <td>${Number(r.student_count||0).toLocaleString('en-IN')}</td>
            <td>${esc(formatDate(r.created_at))}</td>
            <td><span class="pbm-badge ${esc(r.status)}">${esc(r.status)}</span></td>
            <td><div class="pbm-actions-cell justify-content-end">
                <button class="pbm-action" type="button" title="View" data-view="${Number(r.id)}"><i data-lucide="eye"></i></button>
                <button class="pbm-action" type="button" title="Edit" data-edit="${Number(r.id)}"><i data-lucide="pencil"></i></button>
                <button class="pbm-action delete" type="button" title="Delete" data-delete="${Number(r.id)}" ${Number(r.is_main)===1?'disabled':''}><i data-lucide="trash-2"></i></button>
            </div></td>
        </tr>`).join('');
    window.lucide?.createIcons();
}

function renderPagination(){
    const p=pagination; const from=p.total?((p.page-1)*p.per_page)+1:0, to=Math.min(p.page*p.per_page,p.total);
    $('branchPaginationInfo').textContent=`Showing ${from}-${to} of ${p.total} branches`;
    const parts=[];
    parts.push(`<button class="pbm-page-btn" data-page="${p.page-1}" ${p.page<=1?'disabled':''}>‹</button>`);
    const start=Math.max(1,p.page-2), end=Math.min(p.last_page,p.page+2);
    for(let i=start;i<=end;i++) parts.push(`<button class="pbm-page-btn ${i===p.page?'active':''}" data-page="${i}">${i}</button>`);
    parts.push(`<button class="pbm-page-btn" data-page="${p.page+1}" ${p.page>=p.last_page?'disabled':''}>›</button>`);
    $('branchPagination').innerHTML=parts.join('');
}

async function loadMeta(){ const d=await request('meta'); schools=d.schools||[]; renderSchools(); }
async function loadBranches(page=currentPage){
    currentPage=page; $('branchTableBody').innerHTML='<tr><td colspan="10" class="pbm-empty">Loading branches...</td></tr>';
    const d=await request('list',{page,per_page:10,search:$('branchSearch').value.trim(),school_id:$('schoolFilter').value,branch_type:$('branchTypeFilter').value,status:$('statusFilter').value});
    records=d.records||[]; pagination=d.pagination||pagination; currentPage=Number(pagination.page||1); if(d.csrf_token)csrfToken=d.csrf_token; stats(d.stats||{}); if(Array.isArray(d.schools)){schools=d.schools;renderSchools();} renderTable(); renderPagination();
}

function resetForm(){ $('branchId').value='0'; $('branchSchool').value=''; $('branchName').value=''; $('branchCode').value=''; $('branchPhone').value=''; $('branchMain').value='0'; $('branchStatus').value='active'; $('branchAddress').value=''; $('branchModalTitle').textContent='Add Branch'; $('saveBranchText').textContent='Save Branch'; }

async function editBranch(id){ const d=await request('get',{id}); const r=d.branch||{}; resetForm(); $('branchId').value=String(r.id||0); $('branchSchool').value=String(r.tenant_id||''); $('branchName').value=r.branch_name||''; $('branchCode').value=r.branch_code||''; $('branchPhone').value=r.phone||''; $('branchMain').value=Number(r.is_main)===1?'1':'0'; $('branchStatus').value=r.status||'active'; $('branchAddress').value=r.address||''; $('branchModalTitle').textContent='Edit Branch'; $('saveBranchText').textContent='Update Branch'; branchModal.show(); }

async function viewBranch(id){ const d=await request('get',{id}); const r=d.branch||{}; $('viewBranchContent').innerHTML=`
    <div class="pbm-view-item"><small>Branch Name</small><strong>${esc(r.branch_name||'—')}</strong></div>
    <div class="pbm-view-item"><small>Branch Code</small><strong>${esc(r.branch_code||'—')}</strong></div>
    <div class="pbm-view-item"><small>School</small><strong>${esc(r.school_name||'—')} ${r.tenant_code?`(${esc(r.tenant_code)})`:''}</strong></div>
    <div class="pbm-view-item"><small>Branch Type</small><strong>${Number(r.is_main)===1?'Main Branch':'Other Branch'}</strong></div>
    <div class="pbm-view-item"><small>Phone</small><strong>${esc(r.phone||'—')}</strong></div>
    <div class="pbm-view-item"><small>Status</small><strong>${esc(r.status||'—')}</strong></div>
    <div class="pbm-view-item"><small>Users</small><strong>${Number(r.user_count||0).toLocaleString('en-IN')}</strong></div>
    <div class="pbm-view-item"><small>Students</small><strong>${Number(r.student_count||0).toLocaleString('en-IN')}</strong></div>
    <div class="pbm-view-item"><small>Created</small><strong>${esc(formatDate(r.created_at))}</strong></div>
    <div class="pbm-view-item full"><small>Address</small><strong>${esc(r.address||'—')}</strong></div>`; branchViewModal.show(); }

async function deleteBranch(id){ const r=records.find(x=>Number(x.id)===Number(id)); if(!r)return; if(Number(r.is_main)===1){message('Main branch cannot be deleted. Assign another main branch first.');return;} if(!confirm(`Delete branch "${r.branch_name}"?`))return; const d=await request('delete',{id},'POST'); message(d.message||'Branch deleted successfully.',true); await loadBranches(currentPage); }

async function saveForm(e){
    e.preventDefault();
    const schoolId=Number($('branchSchool').value||0), name=$('branchName').value.trim();
    if(!schoolId){message('Select a school.');return;} if(!name){message('Branch Name is required.');return;}
    $('saveBranchBtn').disabled=true;
    try{
        const d=await request('save',{id:$('branchId').value,tenant_id:schoolId,branch_name:name,branch_code:$('branchCode').value.trim(),phone:$('branchPhone').value.trim(),is_main:$('branchMain').value,status:$('branchStatus').value,address:$('branchAddress').value.trim()},'POST');
        message(d.message||'Branch saved successfully.',true); branchModal.hide(); await loadBranches(1);
    }catch(err){message(err.message);}finally{$('saveBranchBtn').disabled=false;}
}

function wire(){
    branchModal=modal(); branchViewModal=viewModal();
    $('refreshBranchesBtn').onclick=()=>Promise.all([loadMeta(),loadBranches(currentPage)]).catch(e=>message(e.message));
    $('addBranchBtn').onclick=()=>{resetForm();branchModal.show();};
    $('clearBranchFiltersBtn').onclick=()=>{$('branchSearch').value='';$('schoolFilter').value='';$('branchTypeFilter').value='';$('statusFilter').value='';loadBranches(1).catch(e=>message(e.message));};
    ['schoolFilter','branchTypeFilter','statusFilter'].forEach(id=>$(id).addEventListener('change',()=>loadBranches(1).catch(e=>message(e.message))));
    $('branchSearch').addEventListener('input',()=>{clearTimeout(searchTimer);searchTimer=setTimeout(()=>loadBranches(1).catch(e=>message(e.message)),350);});
    $('branchForm').addEventListener('submit',saveForm);
    $('branchPagination').addEventListener('click',e=>{const b=e.target.closest('[data-page]');if(!b||b.disabled)return;loadBranches(Number(b.dataset.page||1)).catch(err=>message(err.message));});
    $('branchTableBody').addEventListener('click',e=>{const v=e.target.closest('[data-view]'),ed=e.target.closest('[data-edit]'),del=e.target.closest('[data-delete]'); if(v)viewBranch(v.dataset.view).catch(err=>message(err.message)); else if(ed)editBranch(ed.dataset.edit).catch(err=>message(err.message)); else if(del&&!del.disabled)deleteBranch(del.dataset.delete).catch(err=>message(err.message));});
}

async function init(){wire();await loadMeta();await loadBranches(1);window.lucide?.createIcons();}
if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',()=>init().catch(e=>message(e.message))); else init().catch(e=>message(e.message));
})();
</script>
<?php require dirname(__DIR__) . '/includes/layout-end.php'; ?>

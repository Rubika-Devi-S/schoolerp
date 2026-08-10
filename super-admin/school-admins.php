<?php
declare(strict_types=1);

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/includes/bootstrap.php';

if (function_exists('require_login')) {
    require_login();
}

$pageTitle = 'Create School Admin User';
$pageKey = 'super_admin_school_admins';
$sidebarFile = __DIR__ . '/sidebar.php';

$currentUser = function_exists('current_user') ? current_user() : [];
$roleId = (int)($currentUser['role_id'] ?? $_SESSION['role_id'] ?? 0);
$roleKey = strtolower(trim((string)(
    $currentUser['role_key'] ?? $_SESSION['role_key'] ?? ''
)));

if ($roleKey === '' && $pdo instanceof PDO && $roleId > 0) {
    try {
        $stmt = $pdo->prepare(
            "SELECT role_key FROM roles
             WHERE id = :id AND status = 'active' LIMIT 1"
        );
        $stmt->execute(['id' => $roleId]);
        $roleKey = strtolower(trim((string)$stmt->fetchColumn()));
    } catch (Throwable $e) {
        error_log('School Admin page role lookup: ' . $e->getMessage());
    }
}

$isSuperAdmin = $roleId === 1 || in_array(
    $roleKey,
    ['super_admin', 'super-administrator', 'super_administrator'],
    true
);

if (!$isSuperAdmin) {
    http_response_code(403);
    require $projectRoot . '/includes/layout-start.php';
    echo '<div class="ui-card"><div class="ui-card-body">'
        . '<h1 class="page-title">Access denied</h1>'
        . '<p class="page-subtitle">Only a Super Administrator can manage School Admin users.</p>'
        . '</div></div>';
    require $projectRoot . '/includes/layout-end.php';
    exit;
}

$baseUrl = defined('BASE_URL')
    ? rtrim((string)BASE_URL, '/') . '/'
    : '../';

if (function_exists('csrfToken')) {
    $csrfToken = (string)csrfToken();
} elseif (function_exists('csrf_token')) {
    $csrfToken = (string)csrf_token();
} else {
    $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
    $csrfToken = (string)$_SESSION['csrf_token'];
}

require $projectRoot . '/includes/layout-start.php';
?>
<style>
.user-create-grid{display:grid;grid-template-columns:minmax(0,1fr) 310px;gap:16px}
.user-stack{display:grid;gap:16px;align-content:start}.user-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
.user-form-field.full{grid-column:1/-1}.user-form-field label,.user-filter-field label{display:block;margin-bottom:7px;font-size:11px;font-weight:750}
.user-required{color:var(--danger-color,#dc3545)}.user-form-help{display:block;margin-top:6px;color:var(--text-muted,#64748b);font-size:9px;line-height:1.5}
.user-photo-box{display:grid;gap:14px;justify-items:center;padding:18px;border:1px dashed var(--border-soft,#e7ebf3);border-radius:13px;background:var(--body-bg,#f6f8fc);text-align:center}
.user-photo-preview{width:112px;height:112px;display:grid;place-items:center;overflow:hidden;border-radius:50%;color:var(--brand-1,#6747e8);background:var(--card-bg,#fff);border:1px solid var(--border-soft,#e7ebf3);font-weight:800}
.user-photo-preview img{width:100%;height:100%;object-fit:cover}.user-photo-preview svg{width:38px;height:38px}
.user-security-list{display:grid;gap:10px;margin:0;padding:0;list-style:none}.user-security-list li{display:flex;align-items:flex-start;gap:9px;color:var(--text-muted,#64748b);font-size:10px;line-height:1.5}
.user-security-list svg{width:15px;min-width:15px;margin-top:1px;color:var(--success-color,#21ae71)}
.user-submit-row{display:flex;justify-content:flex-end;gap:10px;padding-top:4px}.user-message{display:none;margin:0 0 16px}.user-message.show{display:block}
.user-permission-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.user-permission-item{min-height:54px;display:flex;align-items:center;gap:10px;padding:11px 12px;border:1px solid var(--border-soft,#e7ebf3);border-radius:11px;background:var(--body-bg,#f6f8fc)}
.user-permission-item .form-check{margin:0}.user-permission-copy strong,.user-permission-copy small{display:block}
.user-permission-copy strong{font-size:10px}.user-permission-copy small{margin-top:3px;color:var(--text-muted,#64748b);font-size:8px;line-height:1.4}
.user-list-toolbar{display:grid;grid-template-columns:minmax(220px,1fr) minmax(190px,240px) minmax(150px,190px) auto;gap:12px;align-items:end;padding:16px}
.user-list-table{min-width:1540px}.user-list-empty{padding:30px;text-align:center;color:var(--text-muted,#64748b)}
.user-list-avatar{width:38px;height:38px;display:grid;place-items:center;overflow:hidden;border-radius:50%;color:var(--brand-1,#6747e8);background:rgba(101,71,232,.09);border:1px solid var(--border-soft,#e7ebf3);font-size:10px;font-weight:800}
.user-list-avatar img{width:100%;height:100%;object-fit:cover}.user-admin-copy strong,.user-admin-copy small{display:block}.user-admin-copy strong{font-size:11px}.user-admin-copy small{margin-top:3px;color:var(--text-muted,#64748b);font-size:9px}
.user-status{display:inline-flex;padding:4px 8px;border-radius:999px;font-size:9px;font-weight:800;text-transform:capitalize}.user-status.active{color:var(--success-color,#21ae71);background:rgba(33,174,113,.1)}.user-status.inactive{color:var(--danger-color,#f54267);background:rgba(245,66,103,.1)}
.user-actions{display:flex;flex-wrap:wrap;gap:6px}.user-action-btn{width:31px;height:31px;display:inline-grid;place-items:center;border:1px solid var(--border-soft,#e7ebf3);border-radius:9px;color:var(--text-main,#101b46);background:var(--card-bg,#fff)}
.user-action-btn:hover{color:var(--brand-1,#6747e8);background:var(--sidebar-hover-bg,#eef2ff)}.user-action-btn.danger:hover{color:var(--danger-color,#f54267)}.user-action-btn svg{width:14px;height:14px}
.user-view-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.user-view-item{padding:11px 12px;border:1px solid var(--border-soft,#e7ebf3);border-radius:10px;background:var(--body-bg,#f6f8fc)}
.user-view-item.full{grid-column:1/-1}.user-view-item span,.user-view-item strong{display:block}.user-view-item span{color:var(--text-muted,#64748b);font-size:8px;text-transform:uppercase;letter-spacing:.05em}.user-view-item strong{margin-top:5px;font-size:11px;white-space:pre-wrap}
.user-list-summary{padding:0 16px 14px;color:var(--text-muted,#64748b);font-size:9px}
@media(max-width:1199.98px){.user-list-toolbar{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:991.98px){.user-create-grid{grid-template-columns:1fr}}
@media(max-width:767.98px){.user-form-grid,.user-permission-grid,.user-view-grid,.user-list-toolbar{grid-template-columns:1fr}.user-form-field.full,.user-view-item.full{grid-column:auto}}
@media(max-width:575.98px){.user-submit-row{flex-direction:column-reverse}.user-submit-row .btn-ui{width:100%;justify-content:center}}
</style>

<div class="page-heading">
    <div>
        <h1 class="page-title">Create School Admin</h1>
        <p class="page-subtitle">Create and manage School Administrators with school, branch and module permissions.</p>
    </div>
    <div class="page-actions">
        <button id="newAdminButton" class="btn-ui" type="button"><i data-lucide="user-plus"></i>New School Admin</button>
        <a class="btn-ui btn-primary-ui" href="#schoolAdminList"><i data-lucide="list"></i>School Admin List</a>
    </div>
</div>

<div id="userMessage" class="alert user-message" role="alert"></div>

<form id="createUserForm" method="post" enctype="multipart/form-data" novalidate>
    <input id="formAction" type="hidden" name="action" value="create_school_admin_user">
    <input id="adminUserId" type="hidden" name="user_id" value="">
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

    <div class="user-create-grid">
        <div class="user-stack">
            <article class="ui-card">
                <div class="ui-card-header"><h2 class="ui-card-title">Login Information</h2><i data-lucide="key-round"></i></div>
                <div class="ui-card-body">
                    <div class="user-form-grid">
                        <div class="user-form-field">
                            <label for="name">School Admin Name <span class="user-required">*</span></label>
                            <input id="name" name="name" class="form-control" type="text" minlength="2" maxlength="150" autocomplete="name" required>
                            <div class="invalid-feedback">Enter the School Admin name.</div>
                        </div>
                        <div class="user-form-field">
                            <label for="username">Username <span class="user-required">*</span></label>
                            <input id="username" name="username" class="form-control" type="text" minlength="3" maxlength="100" autocomplete="username" required>
                            <small class="user-form-help">Letters, numbers, spaces, dots, underscores, @ and hyphens.</small>
                            <div class="invalid-feedback">Enter a valid username.</div>
                        </div>
                        <div class="user-form-field">
                            <label for="email">Email <span class="user-required">*</span></label>
                            <input id="email" name="email" class="form-control" type="email" maxlength="150" autocomplete="email" required>
                            <div class="invalid-feedback">Enter a valid email address.</div>
                        </div>
                        <div class="user-form-field">
                            <label for="mobile">Mobile Number</label>
                            <input id="mobile" name="mobile" class="form-control" type="tel" maxlength="20" autocomplete="tel" placeholder="+91 98765 43210">
                            <div class="invalid-feedback">Enter a valid mobile number.</div>
                        </div>
                        <div class="user-form-field">
                            <label for="password">Password <span id="passwordRequired" class="user-required">*</span></label>
                            <input id="password" name="password" class="form-control" type="password" minlength="6" maxlength="128" autocomplete="new-password" required>
                            <small class="user-form-help">Minimum 6 characters. Letters, numbers, symbols and spaces are supported. Leave blank during edit to keep the existing password.</small>
                            <div class="invalid-feedback">Enter a password with at least 6 characters.</div>
                        </div>
                        <div class="user-form-field">
                            <label for="confirm_password">Confirm Password <span id="confirmPasswordRequired" class="user-required">*</span></label>
                            <input id="confirm_password" name="confirm_password" class="form-control" type="password" minlength="6" maxlength="128" autocomplete="new-password" required>
                            <div class="invalid-feedback">Password confirmation must match.</div>
                        </div>
                    </div>
                </div>
            </article>

            <article class="ui-card">
                <div class="ui-card-header"><h2 class="ui-card-title">School Assignment</h2><i data-lucide="school"></i></div>
                <div class="ui-card-body">
                    <div class="user-form-grid">
                        <div class="user-form-field">
                            <label for="school_id">Select School <span class="user-required">*</span></label>
                            <select id="school_id" name="school_id" class="form-select" required><option value="">Loading schools...</option></select>
                            <div class="invalid-feedback">Select a school.</div>
                        </div>
                        <div class="user-form-field">
                            <label for="branch_id">Head / Default Branch <span class="text-muted">(Optional)</span></label>
                            <select id="branch_id" name="branch_id" class="form-select"><option value="">Automatic - Select school first</option></select>
                            <small class="user-form-help">The active Main / Head Branch is selected automatically.</small>
                        </div>
                        <div class="user-form-field">
                            <label for="employee_id">Employee ID</label>
                            <input id="employee_id" name="employee_id" class="form-control" type="text" value="Auto generated after save" readonly>
                        </div>
                        <div class="user-form-field">
                            <label for="role_name">Role</label>
                            <input id="role_name" class="form-control" type="text" value="School Admin" readonly>
                            <input id="role_id" name="role_id" type="hidden" value="">
                        </div>
                    </div>
                </div>
            </article>

            <article class="ui-card">
                <div class="ui-card-header"><h2 class="ui-card-title">Permissions</h2><i data-lucide="shield-check"></i></div>
                <div class="ui-card-body"><div id="permissionGrid" class="user-permission-grid"><div class="user-list-empty">Loading permissions...</div></div></div>
            </article>

            <article class="ui-card">
                <div class="ui-card-header"><h2 class="ui-card-title">Profile Information</h2><i data-lucide="contact-round"></i></div>
                <div class="ui-card-body">
                    <div class="user-form-grid">
                        <div class="user-form-field">
                            <label for="gender">Gender</label>
                            <select id="gender" name="gender" class="form-select">
                                <option value="">Select gender</option><option value="male">Male</option><option value="female">Female</option><option value="other">Other</option>
                            </select>
                        </div>
                        <div class="user-form-field">
                            <label for="date_of_birth">Date of Birth</label>
                            <input id="date_of_birth" name="date_of_birth" class="form-control" type="date" max="<?= e(date('Y-m-d')) ?>">
                        </div>
                        <div class="user-form-field full">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" class="form-control" rows="4" maxlength="1000"></textarea>
                        </div>
                    </div>
                </div>
            </article>

            <article class="ui-card">
                <div class="ui-card-header"><h2 class="ui-card-title">Account Settings</h2><i data-lucide="settings-2"></i></div>
                <div class="ui-card-body">
                    <div class="user-form-grid">
                        <div class="user-form-field">
                            <label for="status">Status <span class="user-required">*</span></label>
                            <select id="status" name="status" class="form-select" required><option value="active" selected>Active</option><option value="inactive">Inactive</option></select>
                        </div>
                        <div class="user-form-field">
                            <label for="last_login_display">Last Login</label>
                            <input id="last_login_display" class="form-control" type="text" value="Never" readonly>
                        </div>
                        <div class="user-form-field">
                            <label for="created_date_display">Created Date</label>
                            <input id="created_date_display" class="form-control" type="text" value="<?= e(date('d M Y')) ?>" readonly>
                        </div>
                        <div class="user-form-field full">
                            <div class="user-submit-row">
                                <button id="cancelEditButton" class="btn-ui" type="button">Cancel</button>
                                <button id="submitButton" class="btn-ui btn-primary-ui" type="submit"><i data-lucide="user-plus"></i><span id="submitButtonText">Create School Admin</span></button>
                            </div>
                        </div>
                    </div>
                </div>
            </article>
        </div>

        <div class="user-stack">
            <article class="ui-card">
                <div class="ui-card-header"><h2 class="ui-card-title">Profile Photo</h2><i data-lucide="image-up"></i></div>
                <div class="ui-card-body">
                    <div class="user-photo-box">
                        <div id="photoPreview" class="user-photo-preview"><i data-lucide="user-round"></i></div>
                        <div>
                            <label for="profile_photo" class="btn-ui"><i data-lucide="upload"></i>Choose Photo</label>
                            <input id="profile_photo" name="profile_photo" class="d-none" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                        </div>
                        <small class="user-form-help">JPG, PNG or WebP. Maximum file size: 2 MB.</small>
                    </div>
                </div>
            </article>
            <article class="ui-card">
                <div class="ui-card-header"><h2 class="ui-card-title">Security</h2><i data-lucide="shield-check"></i></div>
                <div class="ui-card-body">
                    <ul class="user-security-list">
                        <li><i data-lucide="check-circle-2"></i>Username, email and Employee ID are unique inside the assigned school.</li>
                        <li><i data-lucide="check-circle-2"></i>All password character combinations are supported and stored using BCRYPT.</li>
                        <li><i data-lucide="check-circle-2"></i>School and branch ownership are validated before every save.</li>
                        <li><i data-lucide="check-circle-2"></i>Module access is controlled by role and user permissions.</li>
                    </ul>
                </div>
            </article>
        </div>
    </div>
</form>

<section id="schoolAdminList" class="ui-card mt-3">
    <div class="ui-card-header"><h2 class="ui-card-title">School Admin List</h2><i data-lucide="users-round"></i></div>
    <div class="user-list-toolbar">
        <div class="user-filter-field"><label for="listSearch">Search</label><input id="listSearch" class="form-control" type="search" placeholder="Name, username, email, mobile or Employee ID"></div>
        <div class="user-filter-field"><label for="listSchoolFilter">School</label><select id="listSchoolFilter" class="form-select"><option value="">All schools</option></select></div>
        <div class="user-filter-field"><label for="listStatusFilter">Status</label><select id="listStatusFilter" class="form-select"><option value="">All status</option><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
        <button id="refreshListButton" class="btn-ui" type="button"><i data-lucide="refresh-cw"></i>Refresh</button>
    </div>
    <div class="table-responsive">
        <table class="data-table user-list-table">
            <thead><tr><th>Profile Photo</th><th>School Name</th><th>Branch Name</th><th>Admin Name</th><th>Username</th><th>Email</th><th>Mobile Number</th><th>Status</th><th>Last Login</th><th>Created Date</th><th>Actions</th></tr></thead>
            <tbody id="schoolAdminListBody"><tr><td colspan="11" class="user-list-empty">Loading School Admin users...</td></tr></tbody>
        </table>
    </div>
    <div id="schoolAdminListSummary" class="user-list-summary"></div>
</section>

<div class="modal fade" id="schoolAdminViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">School Admin Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body"><div id="schoolAdminViewContent" class="user-view-grid"></div></div>
            <div class="modal-footer"><button type="button" class="btn-ui" data-bs-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>

<script>
(function () {
'use strict';
const apiUrl=<?= json_encode($baseUrl.'api/auth.php',JSON_UNESCAPED_SLASHES) ?>;
const baseUrl=<?= json_encode($baseUrl,JSON_UNESCAPED_SLASHES) ?>;
let csrfToken=<?= json_encode($csrfToken,JSON_UNESCAPED_SLASHES) ?>,schools=[],branches=[],permissionDefinitions=[],listTimer=null;
const $=id=>document.getElementById(id),form=$('createUserForm'),formAction=$('formAction'),adminUserId=$('adminUserId'),school=$('school_id'),branch=$('branch_id'),roleId=$('role_id'),employeeId=$('employee_id'),password=$('password'),confirmPassword=$('confirm_password'),passwordRequired=$('passwordRequired'),confirmPasswordRequired=$('confirmPasswordRequired'),mobile=$('mobile'),username=$('username'),photoInput=$('profile_photo'),photoPreview=$('photoPreview'),permissionGrid=$('permissionGrid'),submitButton=$('submitButton'),submitButtonText=$('submitButtonText'),messageBox=$('userMessage'),listSearch=$('listSearch'),listSchoolFilter=$('listSchoolFilter'),listStatusFilter=$('listStatusFilter'),listBody=$('schoolAdminListBody'),listSummary=$('schoolAdminListSummary'),viewContent=$('schoolAdminViewContent');
const esc=v=>String(v??'').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;');
const initials=n=>{const p=String(n||'').trim().split(/\s+/u).filter(Boolean);return p.length>1?(p[0][0]+p.at(-1)[0]).toUpperCase():(p[0]||'SA').slice(0,2).toUpperCase()};
function msg(t,ok){messageBox.className='alert user-message show '+(ok?'alert-success':'alert-danger');messageBox.textContent=t;messageBox.scrollIntoView({behavior:'smooth',block:'nearest'})}
function clearMsg(){messageBox.className='alert user-message';messageBox.textContent=''}
async function readJson(r){const t=await r.text();if(!t.trim())throw new Error('The API returned an empty response.');try{return JSON.parse(t)}catch(e){console.error(t);throw new Error('The API returned invalid JSON. Check the PHP error log.')}}
function purl(p){p=String(p||'').trim();if(!p)return'';return /^(https?:\/\/|\/)/i.test(p)?p:baseUrl+p.replace(/^\/+/,'')}
function fdate(v,time=false){if(!v)return'Never';const d=new Date(String(v).replace(' ','T'));if(Number.isNaN(d.getTime()))return String(v);return new Intl.DateTimeFormat('en-IN',time?{day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'}:{day:'2-digit',month:'short',year:'numeric'}).format(d)}
function setPhoto(path,name=''){const u=purl(path);photoPreview.innerHTML=u?`<img src="${esc(u)}" alt="${esc(name||'Profile photo')}">`:`<span>${esc(initials(name))}</span>`}
function renderSchools(){school.innerHTML='<option value="">Select school</option>';listSchoolFilter.innerHTML='<option value="">All schools</option>';schools.forEach(s=>{const o=document.createElement('option');o.value=s.id;o.textContent=`${s.school_name} (${s.tenant_code})`;school.appendChild(o);listSchoolFilter.appendChild(o.cloneNode(true))})}
function renderBranches(selected=''){branch.innerHTML='<option value="">Automatic - Main / Head Branch</option>';const a=branches.filter(b=>String(b.tenant_id)===school.value);a.forEach(b=>{const o=document.createElement('option');o.value=b.id;o.textContent=`${b.branch_name} (${b.branch_code})${Number(b.is_main)===1?' - Main / Head Branch':''}`;branch.appendChild(o)});if(selected){branch.value=String(selected)}else{const m=a.find(b=>Number(b.is_main)===1)||a[0];branch.value=m?String(m.id):''}}
function renderPermissions(values=null){permissionGrid.innerHTML=permissionDefinitions.map(p=>{const c=values===null?Number(p.default_allowed)===1:Number(values[p.key]??0)===1;return `<label class="user-permission-item"><span class="form-check form-switch"><input class="form-check-input permission-input" type="checkbox" name="permissions[${esc(p.key)}]" value="1" data-permission-key="${esc(p.key)}" ${c?'checked':''}></span><span class="user-permission-copy"><strong>${esc(p.label)}</strong><small>${esc(p.description)}</small></span></label>`}).join('');const full=permissionGrid.querySelector('[data-permission-key="full_school_access"]');if(full)full.addEventListener('change',()=>{if(full.checked)permissionGrid.querySelectorAll('.permission-input').forEach(i=>i.checked=true)})}
async function employeePreview(){if(!school.value){employeeId.value='Select school first';return}try{const u=new URL(apiUrl,location.origin);u.searchParams.set('action','school_admin_employee_id');u.searchParams.set('school_id',school.value);const r=await fetch(u,{credentials:'same-origin',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}}),j=await readJson(r);if(!r.ok||!j.success)throw new Error(j.message||'Unable to generate Employee ID.');roleId.value=j.data.role_id||'';employeeId.value=j.data.employee_id_preview||'Auto generated after save'}catch(e){employeeId.value='Auto generated after save';msg(e.message||'Unable to generate Employee ID.',false)}}
async function loadMeta(){school.disabled=branch.disabled=true;try{const u=new URL(apiUrl,location.origin);u.searchParams.set('action','school_admin_user_meta');const r=await fetch(u,{credentials:'same-origin',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}}),j=await readJson(r);if(!r.ok||!j.success)throw new Error(j.message||'Unable to load School Admin details.');csrfToken=j.data.csrf_token||csrfToken;form.querySelector('[name="csrf_token"]').value=csrfToken;schools=Array.isArray(j.data.schools)?j.data.schools:[];branches=Array.isArray(j.data.branches)?j.data.branches:[];permissionDefinitions=Array.isArray(j.data.permissions)?j.data.permissions:[];renderSchools();renderBranches();renderPermissions();if(schools.length===1){school.value=schools[0].id;renderBranches();await employeePreview()}}catch(e){msg(e.message||'Unable to load form details.',false)}finally{school.disabled=branch.disabled=false}}
function validateUsername(){username.setCustomValidity(/^[\p{L}\p{N}][\p{L}\p{N} ._@-]{1,98}[\p{L}\p{N}]$/u.test(username.value.trim())?'':'Use 3 to 100 letters or numbers. Spaces, dots, underscores, @ and hyphens are allowed.')}
function validatePassword(){const edit=adminUserId.value!=='';if(edit&&!password.value&&!confirmPassword.value){password.setCustomValidity('');confirmPassword.setCustomValidity('');return}password.setCustomValidity(password.value.length>=6?'':'Use at least 6 characters. Any letters, numbers, symbols or spaces are allowed.');confirmPassword.setCustomValidity(password.value===confirmPassword.value?'':'Password confirmation does not match.')}
function validateMobile(){mobile.setCustomValidity(!mobile.value.trim()||/^\+?[0-9][0-9\s-]{6,19}$/.test(mobile.value.trim())?'':'Enter a valid mobile number.')}
function resetForm(){form.reset();form.classList.remove('was-validated');formAction.value='create_school_admin_user';adminUserId.value='';roleId.value='';employeeId.value='Auto generated after save';password.required=confirmPassword.required=true;passwordRequired.hidden=confirmPasswordRequired.hidden=false;submitButtonText.textContent='Create School Admin';$('last_login_display').value='Never';$('created_date_display').value=fdate(new Date().toISOString());renderBranches();renderPermissions();setPhoto('','');photoInput.value='';clearMsg();if(schools.length===1){school.value=schools[0].id;renderBranches();employeePreview()}form.scrollIntoView({behavior:'smooth',block:'start'})}
async function loadList(){try{const u=new URL(apiUrl,location.origin);u.searchParams.set('action','school_admin_list');if(listSearch.value.trim())u.searchParams.set('search',listSearch.value.trim());if(listSchoolFilter.value)u.searchParams.set('school_id',listSchoolFilter.value);if(listStatusFilter.value)u.searchParams.set('status',listStatusFilter.value);const r=await fetch(u,{credentials:'same-origin',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}}),j=await readJson(r);if(!r.ok||!j.success)throw new Error(j.message||'Unable to load School Admin users.');renderList(Array.isArray(j.data.items)?j.data.items:[])}catch(e){listBody.innerHTML=`<tr><td colspan="11" class="user-list-empty">${esc(e.message||'Unable to load School Admin users.')}</td></tr>`}}
function renderList(items){listSummary.textContent=`${items.length} School Admin${items.length===1?'':'s'}`;if(!items.length){listBody.innerHTML='<tr><td colspan="11" class="user-list-empty">No School Admin users found.</td></tr>';return}listBody.innerHTML=items.map(i=>{const photo=purl(i.profile_photo),active=i.status==='active';return `<tr><td><span class="user-list-avatar">${photo?`<img src="${esc(photo)}" alt="${esc(i.name)}">`:esc(initials(i.name))}</span></td><td><span class="user-admin-copy"><strong>${esc(i.school_name)}</strong><small>${esc(i.tenant_code)}</small></span></td><td>${esc(i.branch_name||'-')}</td><td><span class="user-admin-copy"><strong>${esc(i.name)}</strong><small>${esc(i.employee_id||'-')}</small></span></td><td>${esc(i.username)}</td><td>${esc(i.email||'-')}</td><td>${esc(i.mobile||'-')}</td><td><span class="user-status ${active?'active':'inactive'}">${esc(i.status)}</span></td><td>${esc(fdate(i.last_login_at,true))}</td><td>${esc(fdate(i.created_at))}</td><td><div class="user-actions"><button class="user-action-btn" type="button" title="View" data-action="view" data-id="${Number(i.id)}"><i data-lucide="eye"></i></button><button class="user-action-btn" type="button" title="Edit" data-action="edit" data-id="${Number(i.id)}"><i data-lucide="square-pen"></i></button><button class="user-action-btn" type="button" title="Reset Password" data-action="reset" data-id="${Number(i.id)}"><i data-lucide="key-round"></i></button><button class="user-action-btn" type="button" title="${active?'Deactivate':'Activate'}" data-action="toggle" data-id="${Number(i.id)}" data-status="${esc(i.status)}"><i data-lucide="${active?'user-x':'user-check'}"></i></button><button class="user-action-btn danger" type="button" title="Delete" data-action="delete" data-id="${Number(i.id)}"><i data-lucide="trash-2"></i></button></div></td></tr>`}).join('');if(window.lucide)window.lucide.createIcons()}
async function fetchAdmin(id){const u=new URL(apiUrl,location.origin);u.searchParams.set('action','school_admin_view');u.searchParams.set('user_id',id);const r=await fetch(u,{credentials:'same-origin',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}}),j=await readJson(r);if(!r.ok||!j.success)throw new Error(j.message||'Unable to load School Admin details.');return j.data.item}
function permLabels(i){return permissionDefinitions.filter(p=>Number(i.permissions?.[p.key]??0)===1).map(p=>p.label).join(', ')||'No module access'}
async function viewAdmin(id){try{const i=await fetchAdmin(id);viewContent.innerHTML=[['School',i.school_name],['Default Branch',i.branch_name||'-'],['Admin Name',i.name],['Employee ID',i.employee_id||'-'],['Username',i.username],['Email',i.email||'-'],['Mobile',i.mobile||'-'],['Status',i.status],['Gender',i.gender||'-'],['Date of Birth',fdate(i.date_of_birth)],['Last Login',fdate(i.last_login_at,true)],['Created Date',fdate(i.created_at)]].map(x=>`<div class="user-view-item"><span>${esc(x[0])}</span><strong>${esc(x[1])}</strong></div>`).join('')+`<div class="user-view-item full"><span>Address</span><strong>${esc(i.address||'-')}</strong></div><div class="user-view-item full"><span>Permissions</span><strong>${esc(permLabels(i))}</strong></div>`;if(window.bootstrap?.Modal)window.bootstrap.Modal.getOrCreateInstance($('schoolAdminViewModal')).show()}catch(e){msg(e.message||'Unable to load School Admin details.',false)}}
async function editAdmin(id){try{const i=await fetchAdmin(id);adminUserId.value=i.id;formAction.value='update_school_admin_user';$('name').value=i.name||'';username.value=i.username||'';$('email').value=i.email||'';mobile.value=i.mobile||'';school.value=i.tenant_id;renderBranches(i.default_branch_id);roleId.value=i.role_id||'';employeeId.value=i.employee_id||'';$('gender').value=i.gender||'';$('date_of_birth').value=i.date_of_birth||'';$('address').value=i.address||'';$('status').value=i.status||'active';$('last_login_display').value=fdate(i.last_login_at,true);$('created_date_display').value=fdate(i.created_at);password.value=confirmPassword.value='';password.required=confirmPassword.required=false;passwordRequired.hidden=confirmPasswordRequired.hidden=true;submitButtonText.textContent='Update School Admin';renderPermissions(i.permissions||{});setPhoto(i.profile_photo,i.name);clearMsg();form.scrollIntoView({behavior:'smooth',block:'start'})}catch(e){msg(e.message||'Unable to edit School Admin.',false)}}
async function post(action,payload){const r=await fetch(apiUrl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json',Accept:'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action,csrf_token:csrfToken,...payload})}),j=await readJson(r);if(!r.ok||!j.success)throw new Error(j.message||'Unable to complete the action.');return j}
async function resetPassword(id){const p=prompt('Enter a new password with at least 6 characters. Letters, numbers, symbols and spaces are allowed:');if(p===null)return;const c=prompt('Confirm the new password:');if(c===null)return;try{const j=await post('reset_school_admin_password',{user_id:id,password:p,confirm_password:c});msg(j.message,true)}catch(e){msg(e.message||'Unable to reset password.',false)}}
async function toggleStatus(id,status){const next=status==='active'?'inactive':'active';if(!confirm(`${next==='active'?'Activate':'Deactivate'} this School Admin?`))return;try{const j=await post('toggle_school_admin_status',{user_id:id,status:next});msg(j.message,true);await loadList()}catch(e){msg(e.message||'Unable to update status.',false)}}
async function deleteAdmin(id){if(!confirm('Delete this School Admin? Login history and audit records will be preserved.'))return;try{const j=await post('delete_school_admin',{user_id:id});msg(j.message,true);if(Number(adminUserId.value)===Number(id))resetForm();await loadList()}catch(e){msg(e.message||'Unable to delete School Admin.',false)}}
school.addEventListener('change',async()=>{renderBranches();await employeePreview()});username.addEventListener('input',validateUsername);username.addEventListener('blur',()=>{username.value=username.value.trim().replace(/\s+/gu,' ');validateUsername()});password.addEventListener('input',validatePassword);confirmPassword.addEventListener('input',validatePassword);mobile.addEventListener('input',validateMobile);
photoInput.addEventListener('change',()=>{const f=photoInput.files?.[0];if(!f){setPhoto('',$('name').value);return}if(!['image/jpeg','image/png','image/webp'].includes(f.type)||f.size>2*1024*1024){photoInput.value='';photoInput.setCustomValidity('Choose a JPG, PNG or WebP file below 2 MB.');setPhoto('',$('name').value);return}photoInput.setCustomValidity('');const r=new FileReader();r.onload=e=>{photoPreview.innerHTML=`<img src="${esc(e.target.result||'')}" alt="Profile preview">`};r.readAsDataURL(f)});
form.addEventListener('submit',async e=>{e.preventDefault();validateUsername();validatePassword();validateMobile();form.classList.add('was-validated');if(!form.checkValidity())return;submitButton.disabled=true;try{const r=await fetch(apiUrl,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},body:new FormData(form)}),j=await readJson(r);if(!r.ok||!j.success)throw new Error(j.message||'Unable to save the School Admin.');msg(j.message,true);resetForm();await loadList()}catch(x){msg(x.message||'Unable to save the School Admin.',false)}finally{submitButton.disabled=false}});
listBody.addEventListener('click',e=>{const b=e.target.closest('button[data-action][data-id]');if(!b)return;const id=Number(b.dataset.id);if(b.dataset.action==='view')viewAdmin(id);else if(b.dataset.action==='edit')editAdmin(id);else if(b.dataset.action==='reset')resetPassword(id);else if(b.dataset.action==='toggle')toggleStatus(id,b.dataset.status||'inactive');else if(b.dataset.action==='delete')deleteAdmin(id)});
listSearch.addEventListener('input',()=>{clearTimeout(listTimer);listTimer=setTimeout(loadList,300)});listSchoolFilter.addEventListener('change',loadList);listStatusFilter.addEventListener('change',loadList);$('refreshListButton').addEventListener('click',loadList);$('cancelEditButton').addEventListener('click',resetForm);$('newAdminButton').addEventListener('click',resetForm);
Promise.resolve().then(loadMeta).then(loadList);
})();
</script>
<?php require $projectRoot . '/includes/layout-end.php'; ?>

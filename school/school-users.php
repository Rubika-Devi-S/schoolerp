<?php
declare(strict_types=1);

$pageTitle = 'User Management';
$pageKey = 'users';

require dirname(__DIR__) . '/includes/layout-start.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['user_management_csrf_token']) || !is_string($_SESSION['user_management_csrf_token'])) {
    $_SESSION['user_management_csrf_token'] = bin2hex(random_bytes(32));
}

$userManagementCsrf = $_SESSION['user_management_csrf_token'];
?>

<style>
*{box-sizing:border-box}
.um-page{display:grid;gap:16px;width:100%;min-width:0}
.um-page .page-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;min-width:0}
.um-page .page-heading>div:first-child{min-width:0}
.um-page .page-title{margin:0;font-size:clamp(24px,2vw,30px);line-height:1.15}
.um-page .page-subtitle{margin-top:5px;max-width:820px}
.um-page .page-actions{display:flex;align-items:center;justify-content:flex-end;flex-wrap:wrap;gap:9px;min-width:0}
.um-page .page-actions .btn-ui{min-height:40px;white-space:nowrap}
.um-message{display:none;position:relative}.um-message.show{display:block}
.um-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.um-stat{border:0;border-radius:14px;min-height:116px;padding:18px 20px;display:flex;align-items:center;gap:14px;color:#fff;position:relative;overflow:hidden;min-width:0;box-shadow:0 12px 28px rgba(15,23,42,.08)}
.um-stat::before{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(255,255,255,.03),rgba(15,23,42,.05));pointer-events:none}
.um-stat::after{content:"";position:absolute;width:118px;height:118px;border-radius:50%;right:-40px;top:-42px;background:rgba(255,255,255,.09)}
.um-stat.purple{background:linear-gradient(135deg,#7448e8,#4b36cf)}
.um-stat.green{background:linear-gradient(135deg,#45c783,#1ea568)}
.um-stat.orange{background:linear-gradient(135deg,#ffb327,#ff8a17)}
.um-stat.blue{background:linear-gradient(135deg,#4696ef,#2469d5)}
.um-stat-icon{width:50px;height:50px;border-radius:50%;background:rgba(255,255,255,.16);display:grid;place-items:center;flex:0 0 auto;position:relative;z-index:1}
.um-stat-icon svg{width:25px;height:25px}
.um-stat>div{position:relative;z-index:1;min-width:0}
.um-stat strong{display:block;font-size:clamp(21px,1.8vw,27px);line-height:1.05;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.um-stat small{display:block;font-size:11px;font-weight:700;opacity:.96;margin-bottom:6px}
.um-stat .trend{font-size:9px;font-weight:700;opacity:.94;margin-top:8px;white-space:normal}
.um-card{border-radius:14px;overflow:hidden;min-width:0}
.um-card-head{padding:14px 16px;border-bottom:1px solid var(--border-soft,#e7ebf3);display:flex;align-items:center;justify-content:space-between;gap:10px;min-width:0}
.um-card-head strong{font-size:14px}
.um-filter{padding:14px 16px;display:grid;grid-template-columns:minmax(280px,1.6fr) minmax(180px,.72fr) minmax(175px,.7fr) auto;gap:10px;align-items:center;border-bottom:1px solid var(--border-soft,#e7ebf3)}
.um-filter .form-control,.um-filter .form-select,.um-filter .btn-ui{width:100%;min-width:0;min-height:40px}
.um-table-wrap{width:100%;max-width:100%;overflow-x:auto;overflow-y:visible;-webkit-overflow-scrolling:touch;scrollbar-width:thin}
.um-table{width:100%;min-width:1380px;border-collapse:separate;border-spacing:0}
.um-table th{font-size:10px;white-space:nowrap;position:sticky;top:0;z-index:2;background:var(--card-bg,#fff)}
.um-table td{font-size:11px;vertical-align:middle}
.um-table th,.um-table td{padding:11px 10px}
.um-table tbody tr:hover{background:rgba(79,70,229,.025)}
.um-empty{padding:42px 18px!important;text-align:center!important;color:var(--text-muted,#64748b)}
.um-user{display:flex;align-items:center;gap:10px;min-width:190px}
.um-user>div{min-width:0}
.um-user strong{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:230px}
.um-user small{display:block;color:var(--text-muted,#64748b);margin-top:2px}
.um-avatar{width:38px;height:38px;border-radius:50%;display:grid;place-items:center;flex:0 0 auto;color:#fff;font-size:12px;font-weight:800;background:linear-gradient(135deg,#6d4ce7,#345fe0);overflow:hidden;box-shadow:0 5px 13px rgba(79,70,229,.18)}
.um-avatar.large{width:74px;height:74px;font-size:22px}
.um-avatar img{width:100%;height:100%;object-fit:cover;display:block}
.um-badge{display:inline-flex;align-items:center;gap:5px;padding:5px 9px;border-radius:999px;font-size:9px;font-weight:800;text-transform:capitalize;white-space:nowrap}
.um-badge.active{color:#16834f;background:#e8f8ef}
.um-badge.inactive,.um-badge.left,.um-badge.locked{color:#dc2626;background:#fff0f1}
.um-badge.not_created{color:#9a6700;background:#fff7d6}
.um-role{display:inline-flex;padding:5px 9px;border-radius:999px;background:#eef2ff;color:#4338ca;font-size:9px;font-weight:800;white-space:nowrap}
.um-actions{display:flex;gap:5px;flex-wrap:nowrap}
.um-action{width:30px;height:30px;display:grid;place-items:center;flex:0 0 auto;border:1px solid #d7def1;border-radius:7px;background:var(--card-bg,#fff);color:#4f46e5}
.um-action:hover{background:#f5f3ff}.um-action.danger{color:#dc2626}.um-action.success{color:#16834f}.um-action.warning{color:#d97706}.um-action svg{width:13px;height:13px}
.um-pagination{padding:14px 16px;border-top:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;gap:10px;align-items:center;flex-wrap:wrap}
.um-page-buttons{display:flex;gap:6px;flex-wrap:wrap}
.um-page-button{min-width:34px;height:34px;border:1px solid var(--border-soft,#e7ebf3);background:#fff;border-radius:8px;font-size:11px;font-weight:800}
.um-page-button.active{color:#fff;border-color:transparent;background:linear-gradient(135deg,#6547e8,#315ed8)}
.um-page-button:disabled{opacity:.45}
.um-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.um-form-grid>div{min-width:0}.um-form-grid .full{grid-column:1/-1}
.um-section{grid-column:1/-1;display:flex;align-items:center;gap:9px;margin-top:4px;padding-bottom:8px;border-bottom:1px solid var(--border-soft,#e7ebf3)}
.um-section strong{font-size:13px}.um-section svg{width:16px;height:16px;color:#4f46e5}
.um-photo-box{grid-column:1/-1;display:flex;align-items:center;gap:16px;padding:14px;border:1px solid var(--border-soft,#e7ebf3);border-radius:12px;background:rgba(99,102,241,.035)}
.um-photo-box>div:last-child{min-width:0;flex:1}
.um-help{font-size:10px;color:var(--text-muted,#64748b);margin-top:5px}
.um-password-note{grid-column:1/-1;padding:10px 12px;border:1px solid #dbeafe;border-radius:10px;background:#eff6ff;color:#1e40af;font-size:10px}
.um-staff-card{grid-column:1/-1;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;padding:12px;border:1px solid var(--border-soft,#e7ebf3);border-radius:12px;background:rgba(99,102,241,.035)}
.um-staff-card>div{padding:10px;border-radius:10px;background:var(--card-bg,#fff);min-width:0}
.um-staff-card small{display:block;color:var(--text-muted,#64748b);font-size:9px;font-weight:700}
.um-staff-card strong{display:block;margin-top:4px;font-size:12px;overflow-wrap:anywhere}
.um-view-head{display:flex;align-items:center;gap:14px;padding:4px 0 18px;border-bottom:1px solid var(--border-soft,#e7ebf3)}
.um-view-head h5{margin:0;font-size:18px}.um-view-head p{margin:4px 0 0;color:var(--text-muted,#64748b);font-size:11px}
.um-view-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:16px}
.um-view-grid>div{padding:12px;border:1px solid var(--border-soft,#e7ebf3);border-radius:10px;background:rgba(99,102,241,.025);min-width:0}
.um-view-grid small{display:block;color:var(--text-muted,#64748b);font-size:9px;font-weight:700}.um-view-grid strong{display:block;margin-top:5px;font-size:12px;overflow-wrap:anywhere}
#userModal .modal-dialog,#viewUserModal .modal-dialog,#resetPasswordModal .modal-dialog{max-height:calc(100dvh - 32px);margin:16px auto}
#userModal .modal-content,#viewUserModal .modal-content,#resetPasswordModal .modal-content{max-height:calc(100dvh - 32px);overflow:hidden}
#userModal form,#resetPasswordModal form{display:flex;flex-direction:column;max-height:calc(100dvh - 32px)}
#userModal .modal-body,#viewUserModal .modal-body,#resetPasswordModal .modal-body{overflow-y:auto;min-height:0}
@media(min-width:1600px){.um-page{gap:18px}.um-stats{gap:16px}.um-stat{min-height:122px;padding:20px 22px}.um-table{min-width:100%}}
@media(max-width:1199px){.um-page .page-heading{flex-direction:column;align-items:stretch}.um-page .page-actions{justify-content:flex-start}.um-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.um-filter{grid-template-columns:repeat(2,minmax(0,1fr))}.um-filter input:first-child{grid-column:1/-1}.um-view-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.um-staff-card{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:767px){.um-page{gap:12px}.um-page .page-actions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));width:100%}.um-page .page-actions .btn-ui{width:100%;justify-content:center}.um-stats{gap:10px}.um-stat{min-height:108px;padding:15px}.um-filter{padding:12px}.um-form-grid{grid-template-columns:1fr}.um-form-grid .full,.um-photo-box,.um-section,.um-password-note,.um-staff-card{grid-column:auto}.um-card-head,.um-pagination{align-items:flex-start;flex-direction:column}.um-view-grid,.um-staff-card{grid-template-columns:1fr}}
@media(max-width:575px){.um-page .page-title{font-size:24px}.um-page .page-actions{grid-template-columns:1fr}.um-stats,.um-filter{grid-template-columns:1fr}.um-filter input:first-child{grid-column:auto}.um-stat{min-height:102px}.um-photo-box{align-items:flex-start;flex-direction:column}.um-pagination{padding:12px}#userModal .modal-dialog,#viewUserModal .modal-dialog,#resetPasswordModal .modal-dialog{width:calc(100vw - 16px);margin:8px auto;max-height:calc(100dvh - 16px)}#userModal .modal-content,#viewUserModal .modal-content,#resetPasswordModal .modal-content,#userModal form,#resetPasswordModal form{max-height:calc(100dvh - 16px)}}
</style>

<div class="um-page" data-page="user-management">
    <div class="page-heading">
        <div>
            <h1 class="page-title">User Management</h1>
            <p class="page-subtitle">Create login credentials and assign access for staff members already registered in Staff Management. Staff personal and employment details are not created again here.</p>
        </div>
        <div class="page-actions">
            <button id="addUserBtn" class="btn-ui btn-primary-ui" type="button">
                <i data-lucide="key-round"></i> Create Staff Login
            </button>
            <button id="refreshBtn" class="btn-ui" type="button">
                <i data-lucide="refresh-cw"></i> Refresh
            </button>
        </div>
    </div>

    <div id="userMessage" class="alert um-message"></div>

    <section class="um-stats">
        <article class="um-stat purple">
            <span class="um-stat-icon"><i data-lucide="users-round"></i></span>
            <div><small>Total Staff</small><strong id="statTotalStaff">0</strong><div class="trend">Available from Staff Management</div></div>
        </article>
        <article class="um-stat blue">
            <span class="um-stat-icon"><i data-lucide="key-square"></i></span>
            <div><small>Login Accounts</small><strong id="statLoginAccounts">0</strong><div class="trend">Staff with login credentials</div></div>
        </article>
        <article class="um-stat green">
            <span class="um-stat-icon"><i data-lucide="user-check"></i></span>
            <div><small>Active Access</small><strong id="statActiveAccounts">0</strong><div class="trend">Can currently sign in</div></div>
        </article>
        <article class="um-stat orange">
            <span class="um-stat-icon"><i data-lucide="user-round-x"></i></span>
            <div><small>No Login Access</small><strong id="statWithoutLogin">0</strong><div class="trend">Credentials not created</div></div>
        </article>
    </section>

    <section class="ui-card um-card">
        <div class="um-card-head">
            <strong>Staff Login Access</strong>
            <small id="recordInfo" class="text-muted">Loading...</small>
        </div>

        <div class="um-filter">
            <input id="searchUser" class="form-control" placeholder="Search staff code, name, username, mobile, department or designation...">
            <select id="roleFilter" class="form-select"><option value="0">All Roles</option></select>
            <select id="statusFilter" class="form-select">
                <option value="all">All Access Status</option>
                <option value="not_created">Login Not Created</option>
                <option value="active">Active Access</option>
                <option value="inactive">Inactive Access</option>
            </select>
            <button id="resetFilterBtn" class="btn-ui" type="button"><i data-lucide="rotate-ccw"></i> Reset</button>
        </div>

        <div class="um-table-wrap">
            <table class="data-table um-table">
                <thead>
                    <tr>
                        <th>Staff ID</th>
                        <th>Photo</th>
                        <th>Staff Name</th>
                        <th>Department / Designation</th>
                        <th>User ID</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Mobile</th>
                        <th>Access Status</th>
                        <th>Last Login</th>
                        <th>Created Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="userBody"><tr><td colspan="12" class="um-empty">Loading staff login access...</td></tr></tbody>
            </table>
        </div>

        <div class="um-pagination">
            <small id="pageInfo" class="text-muted"></small>
            <div id="pagination" class="um-page-buttons"></div>
        </div>
    </section>
</div>

<!-- Create / Edit Staff Login -->
<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <form id="userForm" enctype="multipart/form-data" novalidate>
                <div class="modal-header">
                    <div>
                        <h5 id="userModalTitle" class="modal-title">Create Staff Login</h5>
                        <small id="userModalSubtitle" class="text-muted">Select an existing staff member and create login credentials.</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body um-form-grid">
                    <input id="userId" name="user_id" type="hidden">

                    <div class="um-section"><i data-lucide="contact-round"></i><strong>Staff Selection</strong></div>
                    <div class="full">
                        <label class="form-label">Select Staff Member *</label>
                        <select id="staffId" name="staff_id" class="form-select" required>
                            <option value="">Select staff member</option>
                        </select>
                        <div class="um-help">Only staff created in Staff Management are available. Staff with existing credentials cannot be selected again.</div>
                    </div>

                    <div id="selectedStaffCard" class="um-staff-card" style="display:none">
                        <div><small>Staff Code</small><strong id="selectedStaffCode">-</strong></div>
                        <div><small>Full Name</small><strong id="selectedStaffName">-</strong></div>
                        <div><small>Mobile / Email</small><strong id="selectedStaffContact">-</strong></div>
                        <div><small>Branch</small><strong id="selectedStaffBranch">-</strong></div>
                        <div><small>Department</small><strong id="selectedStaffDepartment">-</strong></div>
                        <div><small>Designation</small><strong id="selectedStaffDesignation">-</strong></div>
                        <div><small>Staff Status</small><strong id="selectedStaffStatus">Active</strong></div>
                        <div><small>User ID</small><strong id="selectedUserCode">Auto Generate</strong></div>
                    </div>

                    <div class="um-section"><i data-lucide="shield-check"></i><strong>Login Credentials and Access</strong></div>

                    <div class="um-photo-box">
                        <span id="photoPreview" class="um-avatar large">U</span>
                        <div>
                            <label class="form-label">Login Profile Photo <span class="text-muted">(Optional)</span></label>
                            <input id="profilePhoto" name="profile_photo" class="form-control" type="file" accept="image/jpeg,image/png,image/webp">
                            <div class="um-help">This is used only for the login account. JPG, PNG or WEBP, maximum 3 MB.</div>
                            <label id="removePhotoWrap" class="form-check mt-2" style="display:none">
                                <input id="removePhoto" class="form-check-input" type="checkbox" value="1">
                                <span class="form-check-label">Remove current photo</span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="form-label">Username *</label>
                        <input id="username" name="username" class="form-control" maxlength="100" autocomplete="off" required placeholder="Enter username">
                        <div class="um-help">Letters, numbers, dot, underscore, @ and hyphen are allowed.</div>
                    </div>
                    <div>
                        <label class="form-label">Role *</label>
                        <select id="roleId" name="role_id" class="form-select" required><option value="">Select Role</option></select>
                    </div>
                    <div>
                        <label class="form-label">Access Status *</label>
                        <select id="userStatus" name="status" class="form-select" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div></div>
                    <div>
                        <label id="passwordLabel" class="form-label">Password *</label>
                        <input id="password" name="password" class="form-control" type="password" minlength="6" autocomplete="new-password" placeholder="Enter password">
                    </div>
                    <div>
                        <label id="confirmPasswordLabel" class="form-label">Confirm Password *</label>
                        <input id="confirmPassword" name="confirm_password" class="form-control" type="password" minlength="6" autocomplete="new-password" placeholder="Confirm password">
                    </div>
                    <div class="um-password-note">
                        Password must contain at least 6 characters. A 6-digit numeric password such as <strong>123456</strong> is supported.
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-ui" data-bs-dismiss="modal">Cancel</button>
                    <button id="saveUserBtn" type="submit" class="btn-ui btn-primary-ui"><i data-lucide="save"></i> Save Login</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Staff Login -->
<div class="modal fade" id="viewUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Staff Login Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="um-view-head">
                    <span id="viewAvatar" class="um-avatar large">U</span>
                    <div><h5 id="viewName">-</h5><p id="viewSubtitle">-</p></div>
                </div>
                <div id="viewGrid" class="um-view-grid"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn-ui" data-bs-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>

<!-- Reset Password -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <form id="resetPasswordForm" novalidate>
                <div class="modal-header">
                    <div><h5 class="modal-title">Reset Password</h5><small id="resetPasswordUser" class="text-muted"></small></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input id="resetUserId" type="hidden">
                    <div class="mb-3"><label class="form-label">New Password *</label><input id="resetPassword" class="form-control" type="password" minlength="6" autocomplete="new-password" required></div>
                    <div class="mb-3"><label class="form-label">Confirm Password *</label><input id="resetConfirmPassword" class="form-control" type="password" minlength="6" autocomplete="new-password" required></div>
                    <div class="um-password-note">Minimum 6 characters. A 6-digit numeric password is allowed.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-ui" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-ui btn-primary-ui"><i data-lucide="key-round"></i> Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function(){
    'use strict';

    const apiUrl = new URL('../api/user-management.php', window.location.href).href;
    let csrfToken = <?= json_encode($userManagementCsrf, JSON_UNESCAPED_SLASHES) ?>;
    let records = [];
    let roles = [];
    let staffCandidates = [];
    let page = 1;
    let permissions = {view:true,create:false,edit:false,delete:false};
    let searchTimer = null;
    let messageTimer = null;

    const $ = id => document.getElementById(id);
    const esc = value => String(value ?? '').replace(/[&<>"']/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[character]);
    const initials = name => String(name || 'User').trim().split(/\s+/).slice(0,2).map(part => part.charAt(0).toUpperCase()).join('') || 'U';
    const ucfirst = value => String(value || '').replace(/_/g,' ').replace(/\b\w/g, letter => letter.toUpperCase());

    function dateTime(value){
        if(!value) return 'Never';
        const date = new Date(String(value).replace(' ','T'));
        if(Number.isNaN(date.getTime())) return String(value);
        return date.toLocaleString('en-IN',{day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'});
    }

    function dateOnly(value){
        if(!value) return '-';
        const date = new Date(String(value).substring(0,10) + 'T00:00:00');
        if(Number.isNaN(date.getTime())) return String(value);
        return date.toLocaleDateString('en-IN',{day:'2-digit',month:'short',year:'numeric'});
    }

    function avatarHtml(record, large=false){
        const className = large ? 'um-avatar large' : 'um-avatar';
        if(record?.profile_photo_url){
            return `<span class="${className}"><img src="${esc(record.profile_photo_url)}" alt="${esc(record.name || 'User')}"></span>`;
        }
        return `<span class="${className}">${esc(initials(record?.name || record?.staff_name))}</span>`;
    }

    function badge(value){
        const key = String(value || 'not_created').toLowerCase().replace(/\s+/g,'_');
        const label = key === 'not_created' ? 'Not Created' : ucfirst(key);
        return `<span class="um-badge ${esc(key)}">${esc(label)}</span>`;
    }

    function showMessage(text, ok=false){
        const box = $('userMessage');
        if(!box) return;
        clearTimeout(messageTimer);
        box.className = 'alert um-message show ' + (ok ? 'alert-success' : 'alert-danger');
        box.textContent = text;
        window.scrollTo({top:0,behavior:'smooth'});
        messageTimer = setTimeout(() => box.classList.remove('show'),5500);
    }

    async function parseResponse(response){
        const text = await response.text();
        let result;
        try{ result = JSON.parse(text); }
        catch(error){
            console.error('User Management invalid response:',text);
            throw new Error('The User Management API returned an invalid response.');
        }
        if(!response.ok || !result.success){
            throw new Error(result.message || `HTTP ${response.status}`);
        }
        if(result.data?.csrf_token) csrfToken = result.data.csrf_token;
        if(result.data?.meta?.csrf_token) csrfToken = result.data.meta.csrf_token;
        return result;
    }

    async function request(action,data={},method='GET'){
        if(method === 'GET'){
            const url = new URL(apiUrl);
            url.searchParams.set('action',action);
            Object.entries(data).forEach(([key,value]) => {
                if(value !== '' && value !== null && value !== undefined) url.searchParams.set(key,String(value));
            });
            const response = await fetch(url.toString(),{
                headers:{Accept:'application/json'},credentials:'same-origin',cache:'no-store'
            });
            return parseResponse(response);
        }

        const response = await fetch(apiUrl,{
            method:'POST',
            headers:{'Content-Type':'application/json',Accept:'application/json'},
            credentials:'same-origin',
            body:JSON.stringify({action,csrf_token:csrfToken,...data})
        });
        return parseResponse(response);
    }

    async function submitLogin(formData){
        formData.set('action','save');
        formData.set('csrf_token',csrfToken);
        const response = await fetch(apiUrl,{
            method:'POST',headers:{Accept:'application/json'},credentials:'same-origin',body:formData
        });
        return parseResponse(response);
    }

    function fillRoles(){
        const filterValue = $('roleFilter').value;
        const formValue = $('roleId').value;
        $('roleFilter').innerHTML = '<option value="0">All Roles</option>' + roles.map(role => `<option value="${Number(role.id)}">${esc(role.role_name)}</option>`).join('');
        $('roleId').innerHTML = '<option value="">Select Role</option>' + roles.map(role => `<option value="${Number(role.id)}">${esc(role.role_name)}</option>`).join('');
        if([...$('roleFilter').options].some(option => option.value === filterValue)) $('roleFilter').value = filterValue;
        if([...$('roleId').options].some(option => option.value === formValue)) $('roleId').value = formValue;
    }

    function fillStaffSelect(currentStaffId=0){
        const selected = String(currentStaffId || '');
        const options = staffCandidates.map(staff => {
            const linked = Number(staff.user_id || 0) > 0;
            const disabled = linked && Number(staff.staff_id) !== Number(currentStaffId);
            const suffix = linked ? ' — Login Created' : '';
            return `<option value="${Number(staff.staff_id)}" ${disabled ? 'disabled' : ''}>${esc(staff.staff_code)} — ${esc(staff.staff_name)}${esc(suffix)}</option>`;
        }).join('');
        $('staffId').innerHTML = '<option value="">Select staff member</option>' + options;
        if(selected && [...$('staffId').options].some(option => option.value === selected)) $('staffId').value = selected;
    }

    function updateStats(stats={}){
        $('statTotalStaff').textContent = Number(stats.total_staff || 0).toLocaleString('en-IN');
        $('statLoginAccounts').textContent = Number(stats.login_accounts || 0).toLocaleString('en-IN');
        $('statActiveAccounts').textContent = Number(stats.active_accounts || 0).toLocaleString('en-IN');
        $('statWithoutLogin').textContent = Number(stats.without_login || 0).toLocaleString('en-IN');
    }

    function renderRows(data=[]){
        records = data;
        const body = $('userBody');
        if(!data.length){
            body.innerHTML = '<tr><td colspan="12" class="um-empty">No staff records found.</td></tr>';
            return;
        }

        body.innerHTML = data.map(record => {
            const hasAccount = Boolean(record.account_exists);
            const nextStatus = record.status === 'active' ? 'inactive' : 'active';
            const createButton = record.can_create
                ? `<button class="um-action success js-create" data-staff-id="${Number(record.staff_id)}" title="Create Login" type="button"><i data-lucide="key-round"></i></button>` : '';
            const accountActions = hasAccount ? `
                <button class="um-action js-view" data-staff-id="${Number(record.staff_id)}" title="View" type="button"><i data-lucide="eye"></i></button>
                ${record.can_edit ? `<button class="um-action js-edit" data-staff-id="${Number(record.staff_id)}" title="Edit Login" type="button"><i data-lucide="pencil"></i></button>` : ''}
                ${record.can_edit ? `<button class="um-action warning js-reset" data-user-id="${Number(record.user_id)}" title="Reset Password" type="button"><i data-lucide="key"></i></button>` : ''}
                ${record.can_edit ? `<button class="um-action ${nextStatus === 'active' ? 'success' : 'warning'} js-status" data-user-id="${Number(record.user_id)}" data-status="${nextStatus}" title="${nextStatus === 'active' ? 'Activate' : 'Deactivate'} Login" type="button"><i data-lucide="${nextStatus === 'active' ? 'user-check' : 'user-x'}"></i></button>` : ''}
                ${record.can_delete ? `<button class="um-action danger js-delete" data-user-id="${Number(record.user_id)}" title="Remove Login Credentials" type="button"><i data-lucide="trash-2"></i></button>` : ''}` : createButton;

            return `<tr>
                <td><strong>${esc(record.staff_code)}</strong><div class="text-muted">${badge(record.staff_status)}</div></td>
                <td>${avatarHtml(record)}</td>
                <td><div class="um-user"><div><strong>${esc(record.staff_name)}</strong><small>${esc(record.branch_name || '-')}</small></div></div></td>
                <td><strong>${esc(record.department_name || '-')}</strong><div class="text-muted">${esc(record.designation_name || '-')}</div></td>
                <td><strong>${esc(record.user_code || 'Not Created')}</strong></td>
                <td>${hasAccount ? `<strong>${esc(record.username || '-')}</strong>` : '<span class="text-muted">Not created</span>'}</td>
                <td>${hasAccount ? `<span class="um-role">${esc(record.role_name || '-')}</span>` : '-'}</td>
                <td><strong>${esc(record.staff_mobile || record.mobile || '-')}</strong>${record.staff_email ? `<div class="text-muted">${esc(record.staff_email)}</div>` : ''}</td>
                <td>${badge(record.status)}</td>
                <td>${hasAccount ? esc(dateTime(record.effective_last_login || record.last_login_at)) : '-'}</td>
                <td>${hasAccount ? esc(dateOnly(record.user_created_at || record.created_at)) : '-'}</td>
                <td><div class="um-actions">${accountActions || '<span class="text-muted">Unavailable</span>'}</div></td>
            </tr>`;
        }).join('');

        document.querySelectorAll('.js-create').forEach(button => button.onclick = () => openUser(Number(button.dataset.staffId),false));
        document.querySelectorAll('.js-view').forEach(button => button.onclick = () => viewUser(Number(button.dataset.staffId)));
        document.querySelectorAll('.js-edit').forEach(button => button.onclick = () => openUser(Number(button.dataset.staffId),true));
        document.querySelectorAll('.js-reset').forEach(button => button.onclick = () => openResetPassword(Number(button.dataset.userId)));
        document.querySelectorAll('.js-status').forEach(button => button.onclick = () => changeStatus(Number(button.dataset.userId),button.dataset.status));
        document.querySelectorAll('.js-delete').forEach(button => button.onclick = () => deleteLogin(Number(button.dataset.userId)));
        if(window.lucide) window.lucide.createIcons();
    }

    function renderPagination(pagination={}){
        const total = Number(pagination.total || 0);
        const current = Number(pagination.page || 1);
        const perPage = Number(pagination.per_page || 10);
        const lastPage = Math.max(1,Number(pagination.last_page || 1));
        const start = total ? ((current - 1) * perPage) + 1 : 0;
        const end = Math.min(current * perPage,total);
        $('recordInfo').textContent = `${total} staff record${total === 1 ? '' : 's'}`;
        $('pageInfo').textContent = `Showing ${start}-${end} of ${total}`;

        let html = `<button class="um-page-button" data-page="${current - 1}" ${current <= 1 ? 'disabled' : ''}>‹</button>`;
        for(let value=Math.max(1,current-2);value<=Math.min(lastPage,current+2);value++){
            html += `<button class="um-page-button ${value === current ? 'active' : ''}" data-page="${value}">${value}</button>`;
        }
        html += `<button class="um-page-button" data-page="${current + 1}" ${current >= lastPage ? 'disabled' : ''}>›</button>`;
        $('pagination').innerHTML = html;
        document.querySelectorAll('.um-page-button').forEach(button => {
            button.onclick = () => {
                if(button.disabled) return;
                page = Number(button.dataset.page);
                loadUsers();
            };
        });
    }

    async function loadUsers(){
        try{
            const response = await request('list',{
                search:$('searchUser').value.trim(),
                role_id:$('roleFilter').value,
                status:$('statusFilter').value,
                page,
                per_page:10
            });
            permissions = response.data.permissions || permissions;
            roles = response.data.meta?.roles || roles;
            staffCandidates = response.data.meta?.staff_candidates || staffCandidates;
            fillRoles();
            fillStaffSelect();
            updateStats(response.data.stats || {});
            renderRows(response.data.records || []);
            renderPagination(response.data.pagination || {});
            $('addUserBtn').style.display = permissions.create ? '' : 'none';
        }catch(error){
            console.error(error);
            $('userBody').innerHTML = '<tr><td colspan="12" class="um-empty">Unable to load staff login access.</td></tr>';
            showMessage(error.message || 'Unable to load staff login access.');
        }
    }

    function setPreview(record=null){
        const preview = $('photoPreview');
        if(record?.profile_photo_url){
            preview.innerHTML = `<img src="${esc(record.profile_photo_url)}" alt="${esc(record.staff_name || 'User')}">`;
        }else{
            preview.textContent = initials(record?.staff_name || 'User');
        }
    }

    function showSelectedStaff(staff,record=null){
        const card = $('selectedStaffCard');
        if(!staff){
            card.style.display = 'none';
            setPreview(null);
            return;
        }
        card.style.display = '';
        $('selectedStaffCode').textContent = staff.staff_code || '-';
        $('selectedStaffName').textContent = staff.staff_name || '-';
        $('selectedStaffContact').textContent = [staff.mobile || staff.staff_mobile || '-',staff.email || staff.staff_email || ''].filter(Boolean).join(' / ');
        $('selectedStaffBranch').textContent = staff.branch_name || '-';
        $('selectedStaffDepartment').textContent = staff.department_name || '-';
        $('selectedStaffDesignation').textContent = staff.designation_name || '-';
        $('selectedStaffStatus').textContent = ucfirst(staff.staff_status || 'active');
        $('selectedUserCode').textContent = record?.user_code || 'Auto Generate';
        setPreview(record || {staff_name:staff.staff_name});
    }

    async function openUser(staffId=0,isEdit=false){
        if(isEdit && !permissions.edit) return;
        if(!isEdit && !permissions.create) return;

        $('userForm').reset();
        $('userId').value = '';
        $('removePhoto').checked = false;
        $('removePhotoWrap').style.display = 'none';
        $('staffId').disabled = false;
        $('password').required = true;
        $('confirmPassword').required = true;
        $('passwordLabel').textContent = 'Password *';
        $('confirmPasswordLabel').textContent = 'Confirm Password *';
        $('userModalTitle').textContent = 'Create Staff Login';
        $('userModalSubtitle').textContent = 'Select an existing staff member and create login credentials.';
        fillStaffSelect(staffId);
        showSelectedStaff(staffCandidates.find(item => Number(item.staff_id) === Number(staffId)) || null);

        if(isEdit){
            try{
                const response = await request('detail',{staff_id:staffId});
                const record = response.data.record;
                $('userId').value = record.user_id || '';
                fillStaffSelect(record.staff_id);
                $('staffId').value = String(record.staff_id);
                $('staffId').disabled = true;
                $('username').value = record.username || '';
                $('roleId').value = String(record.role_id || '');
                $('userStatus').value = record.status === 'active' ? 'active' : 'inactive';
                $('password').required = false;
                $('confirmPassword').required = false;
                $('passwordLabel').textContent = 'Password (Optional)';
                $('confirmPasswordLabel').textContent = 'Confirm Password (Optional)';
                $('userModalTitle').textContent = 'Edit Staff Login';
                $('userModalSubtitle').textContent = `${record.staff_code} • ${record.staff_name}`;
                $('removePhotoWrap').style.display = record.profile_photo_url ? '' : 'none';
                showSelectedStaff(record,record);
            }catch(error){
                showMessage(error.message);
                return;
            }
        }

        bootstrap.Modal.getOrCreateInstance($('userModal')).show();
        if(window.lucide) window.lucide.createIcons();
    }

    async function viewUser(staffId){
        try{
            const response = await request('detail',{staff_id:staffId});
            const record = response.data.record;
            $('viewAvatar').outerHTML = avatarHtml(record,true).replace('<span','<span id="viewAvatar"');
            $('viewName').textContent = record.staff_name || '-';
            $('viewSubtitle').textContent = `${record.staff_code || '-'} • ${record.user_code || 'Login Not Created'}`;
            const fields = [
                ['Username',record.username || 'Not Created'],
                ['Role',record.role_name || '-'],
                ['Access Status',ucfirst(record.status || '-')],
                ['Staff Status',ucfirst(record.staff_status || '-')],
                ['Mobile',record.staff_mobile || '-'],
                ['Email',record.staff_email || '-'],
                ['Branch',record.branch_name || '-'],
                ['Department',record.department_name || '-'],
                ['Designation',record.designation_name || '-'],
                ['Last Login',dateTime(record.effective_last_login || record.last_login_at)],
                ['Created Date',dateTime(record.user_created_at || record.created_at)],
                ['Updated Date',dateTime(record.user_updated_at || record.updated_at)]
            ];
            $('viewGrid').innerHTML = fields.map(([label,value]) => `<div><small>${esc(label)}</small><strong>${esc(value)}</strong></div>`).join('');
            bootstrap.Modal.getOrCreateInstance($('viewUserModal')).show();
        }catch(error){ showMessage(error.message); }
    }

    function openResetPassword(userId){
        const record = records.find(item => Number(item.user_id) === Number(userId));
        $('resetPasswordForm').reset();
        $('resetUserId').value = userId;
        $('resetPasswordUser').textContent = record ? `${record.staff_name} (${record.username})` : '';
        bootstrap.Modal.getOrCreateInstance($('resetPasswordModal')).show();
    }

    async function changeStatus(userId,status){
        const record = records.find(item => Number(item.user_id) === Number(userId));
        const verb = status === 'active' ? 'activate' : 'deactivate';
        if(!confirm(`Are you sure you want to ${verb} login access for ${record?.staff_name || 'this staff member'}?`)) return;
        try{
            const response = await request('toggle_status',{user_id:userId,status},'POST');
            showMessage(response.message,true);
            await loadUsers();
        }catch(error){ showMessage(error.message); }
    }

    async function deleteLogin(userId){
        const record = records.find(item => Number(item.user_id) === Number(userId));
        if(!confirm(`Remove login credentials for ${record?.staff_name || 'this staff member'}? The Staff Management record will not be deleted.`)) return;
        try{
            const response = await request('delete',{user_id:userId},'POST');
            showMessage(response.message,true);
            await loadUsers();
        }catch(error){ showMessage(error.message); }
    }

    $('staffId').onchange = () => {
        const selected = staffCandidates.find(item => Number(item.staff_id) === Number($('staffId').value));
        showSelectedStaff(selected || null);
        if(selected && !$('username').value){
            const base = String(selected.staff_name || selected.staff_code || '').toLowerCase().replace(/[^a-z0-9]+/g,'.').replace(/^\.|\.$/g,'');
            $('username').value = base || String(selected.staff_code || '').toLowerCase();
        }
    };

    $('userForm').onsubmit = async event => {
        event.preventDefault();
        const form = event.currentTarget;
        if(!form.reportValidity()) return;

        const isEdit = Number($('userId').value || 0) > 0;
        if(!isEdit && (!$('password').value || !$('confirmPassword').value)){
            showMessage('Password and Confirm Password are required when creating login credentials.');
            return;
        }
        if(($('password').value || $('confirmPassword').value) && $('password').value.length < 6){
            showMessage('Password must contain at least 6 characters. A 6-digit numeric password is allowed.');
            return;
        }
        if(($('password').value || $('confirmPassword').value) && $('password').value !== $('confirmPassword').value){
            showMessage('Password confirmation does not match.');
            return;
        }

        const button = $('saveUserBtn');
        const originalHtml = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';

        try{
            const formData = new FormData();
            formData.set('user_id',$('userId').value || '0');
            formData.set('staff_id',$('staffId').value);
            formData.set('username',$('username').value.trim());
            formData.set('role_id',$('roleId').value);
            formData.set('status',$('userStatus').value);
            formData.set('password',$('password').value);
            formData.set('confirm_password',$('confirmPassword').value);
            formData.set('remove_photo',$('removePhoto').checked ? '1' : '0');
            if($('profilePhoto').files[0]) formData.set('profile_photo',$('profilePhoto').files[0]);

            const response = await submitLogin(formData);
            bootstrap.Modal.getInstance($('userModal'))?.hide();
            showMessage(response.message,true);
            await loadUsers();
        }catch(error){ showMessage(error.message); }
        finally{
            button.disabled = false;
            button.innerHTML = originalHtml;
            if(window.lucide) window.lucide.createIcons();
        }
    };

    $('resetPasswordForm').onsubmit = async event => {
        event.preventDefault();
        if(!event.currentTarget.reportValidity()) return;
        if($('resetPassword').value.length < 6){
            showMessage('Password must contain at least 6 characters. A 6-digit numeric password is allowed.');
            return;
        }
        if($('resetPassword').value !== $('resetConfirmPassword').value){
            showMessage('Password confirmation does not match.');
            return;
        }
        try{
            const response = await request('reset_password',{
                user_id:Number($('resetUserId').value || 0),
                password:$('resetPassword').value,
                confirm_password:$('resetConfirmPassword').value
            },'POST');
            bootstrap.Modal.getInstance($('resetPasswordModal'))?.hide();
            showMessage(response.message,true);
        }catch(error){ showMessage(error.message); }
    };

    $('profilePhoto').onchange = () => {
        const file = $('profilePhoto').files[0];
        if(!file){
            const staff = staffCandidates.find(item => Number(item.staff_id) === Number($('staffId').value));
            setPreview(staff ? {staff_name:staff.staff_name} : null);
            return;
        }
        const reader = new FileReader();
        reader.onload = event => { $('photoPreview').innerHTML = `<img src="${esc(event.target.result)}" alt="Preview">`; };
        reader.readAsDataURL(file);
    };

    $('addUserBtn').onclick = () => openUser(0,false);
    $('refreshBtn').onclick = loadUsers;
    $('resetFilterBtn').onclick = () => {
        $('searchUser').value = '';
        $('roleFilter').value = '0';
        $('statusFilter').value = 'all';
        page = 1;
        loadUsers();
    };
    $('roleFilter').onchange = $('statusFilter').onchange = () => { page = 1; loadUsers(); };
    $('searchUser').oninput = () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => { page = 1; loadUsers(); },300);
    };

    loadUsers();
    if(window.lucide) window.lucide.createIcons();
})();
</script>

<?php require dirname(__DIR__) . '/includes/layout-end.php'; ?>

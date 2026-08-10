<?php
declare(strict_types=1);

$pageTitle = 'Roles & Permissions';
$pageKey = 'roles_permissions';

require dirname(__DIR__) . '/includes/layout-start.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (
    empty($_SESSION['roles_permissions_csrf_token'])
    || !is_string($_SESSION['roles_permissions_csrf_token'])
) {
    $_SESSION['roles_permissions_csrf_token'] = bin2hex(random_bytes(32));
}

$rolesPermissionsCsrf = (string)$_SESSION['roles_permissions_csrf_token'];
?>
<style>
.rp-page{display:grid;gap:16px}.rp-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.rp-stat{min-height:122px;padding:18px 20px;display:flex;align-items:center;gap:16px;border:0;border-radius:14px;color:#fff;position:relative;overflow:hidden;background:linear-gradient(135deg,#7448e8,#4b36cf);box-shadow:0 12px 28px rgba(15,23,42,.08)}.rp-stat::after{content:"";position:absolute;width:118px;height:118px;border-radius:50%;right:-38px;top:-42px;background:rgba(255,255,255,.08)}.rp-stat.green{background:linear-gradient(135deg,#45c783,#1ea568)}.rp-stat.orange{background:linear-gradient(135deg,#ffb327,#ff8a17)}.rp-stat.blue{background:linear-gradient(135deg,#4696ef,#2469d5)}.rp-stat-icon{width:52px;height:52px;flex:0 0 52px;display:grid;place-items:center;border-radius:50%;background:rgba(255,255,255,.16);color:#fff;position:relative;z-index:1}.rp-stat-icon svg{width:25px;height:25px}.rp-stat>div{position:relative;z-index:1}.rp-stat small,.rp-stat strong{display:block;color:#fff}.rp-stat small{font-size:11px;font-weight:700;opacity:.95}.rp-stat strong{margin-top:7px;font-size:27px;line-height:1;font-weight:850}.rp-stat .trend{margin-top:8px;font-size:9px;font-weight:700;color:#fff;opacity:.94}.rp-card{overflow:hidden}.rp-card-head{padding:15px 16px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border-soft,#e7ebf3)}.rp-card-head strong{font-size:14px}.rp-filter{padding:14px 16px;display:grid;grid-template-columns:minmax(240px,1fr) 190px auto;gap:10px;border-bottom:1px solid var(--border-soft,#e7ebf3)}.rp-table-wrap{overflow:auto}.rp-table{min-width:980px}.rp-role{display:flex;align-items:center;gap:10px}.rp-role-icon{width:36px;height:36px;display:grid;place-items:center;flex:0 0 36px;border-radius:10px;background:#f1efff;color:#6547e8}.rp-role-icon svg{width:16px;height:16px}.rp-role strong,.rp-role small{display:block}.rp-role strong{font-size:11px}.rp-role small{max-width:280px;margin-top:3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:9px;color:#64748b}.rp-key{display:inline-flex;padding:4px 7px;border-radius:7px;background:#f8fafc;color:#475569;font:600 9px/1.2 ui-monospace,SFMono-Regular,Menlo,monospace}.rp-count{font-size:11px;font-weight:800}.rp-badge{display:inline-flex;padding:4px 8px;border-radius:20px;font-size:8px;font-weight:850;text-transform:uppercase}.rp-badge.active,.rp-badge.custom{background:#e9fff3;color:#16834f}.rp-badge.inactive{background:#fff0f0;color:#dc2626}.rp-badge.system{background:#eef2ff;color:#4f46e5}.rp-actions{display:flex;gap:6px}.rp-action{width:31px;height:31px;display:grid;place-items:center;border:1px solid #e5e7eb;border-radius:9px;background:#fff;color:#64748b}.rp-action:hover{color:#6547e8;background:#f5f3ff}.rp-action.info{color:#2563eb}.rp-action.success{color:#16834f}.rp-action.warning{color:#d97706}.rp-action.danger{color:#dc2626}.rp-action svg{width:13px;height:13px}.rp-empty,.rp-loading{padding:42px 18px!important;text-align:center!important;color:#64748b}.rp-pagination{padding:12px 16px;display:flex;align-items:center;justify-content:space-between;border-top:1px solid #e7ebf3}.rp-page-buttons{display:flex;gap:5px}.rp-page-button{min-width:31px;height:31px;border:1px solid #e5e7eb;border-radius:8px;background:#fff;color:#475569;font-size:10px;font-weight:750}.rp-page-button.active{color:#fff;border-color:#6547e8;background:#6547e8}.rp-page-button:disabled{opacity:.45}.rp-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.rp-form-grid .full{grid-column:1/-1}.rp-matrix-note{padding:11px 15px;border-bottom:1px solid #e7ebf3;background:#f8fafc;color:#475569;font-size:10px}.rp-matrix-toolbar{padding:12px 14px;display:grid;grid-template-columns:minmax(220px,1fr) auto auto;gap:9px;border-bottom:1px solid #e7ebf3}.rp-matrix-wrap{max-height:65vh;overflow:auto}.rp-matrix-table{min-width:970px}.rp-matrix-table th{position:sticky;top:0;z-index:3;background:#f8fafc;white-space:nowrap}.rp-menu-cell{min-width:300px}.rp-menu{display:flex;align-items:center;gap:10px}.rp-menu-icon{width:34px;height:34px;display:grid;place-items:center;flex:0 0 34px;border-radius:9px;background:#eef2ff;color:#4f46e5}.rp-menu-icon svg{width:15px;height:15px}.rp-menu strong,.rp-menu small{display:block}.rp-sequence{width:28px;flex:0 0 28px;text-align:right;color:#94a3b8;font:800 9px/1 ui-monospace,SFMono-Regular,Menlo,monospace}.rp-menu strong{font-size:10px}.rp-menu small{margin-top:3px;font-size:8px;color:#64748b}.rp-route{display:inline-block;max-width:190px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;padding:4px 6px;border-radius:6px;background:#f8fafc;color:#64748b;font-size:8px}.rp-check-cell{text-align:center;min-width:80px}.rp-check{width:16px;height:16px}.rp-locked{display:inline-flex;align-items:center;gap:5px;padding:5px 8px;border-radius:20px;background:#fff4df;color:#b45309;font-size:9px;font-weight:800}.rp-modal-wide{--bs-modal-width:calc(100vw - 48px)}
@media(max-width:991.98px){.rp-stats{grid-template-columns:repeat(2,1fr)}.rp-filter{grid-template-columns:1fr 1fr}.rp-filter .btn-ui{grid-column:1/-1}.rp-matrix-toolbar{grid-template-columns:1fr 1fr}.rp-matrix-toolbar input{grid-column:1/-1}}
@media(max-width:575.98px){.rp-stats,.rp-filter,.rp-form-grid,.rp-matrix-toolbar{grid-template-columns:1fr}.rp-form-grid .full,.rp-filter .btn-ui,.rp-matrix-toolbar input{grid-column:auto}.rp-pagination{align-items:flex-start;gap:10px;flex-direction:column}}
</style>

<div class="rp-page">
    <div class="page-heading">
        <div>
            <h1 class="page-title">Roles &amp; Permissions</h1>
            <p class="page-subtitle">Manage role access only for sidebar modules assigned to this school by Super Admin.</p>
        </div>
        <div class="page-actions">
            <button id="addRoleButton" class="btn-ui btn-primary-ui" type="button"><i data-lucide="shield-plus"></i> Add Role</button>
            <button id="refreshButton" class="btn-ui" type="button"><i data-lucide="refresh-cw"></i> Refresh</button>
        </div>
    </div>

    <section class="rp-stats">
        <article class="rp-stat"><span class="rp-stat-icon"><i data-lucide="shield-check"></i></span><div><small>Total Roles</small><strong id="statTotal">0</strong><div class="trend">School login roles</div></div></article>
        <article class="rp-stat green"><span class="rp-stat-icon"><i data-lucide="badge-check"></i></span><div><small>Active Roles</small><strong id="statActive">0</strong><div class="trend">Available for assignment</div></div></article>
        <article class="rp-stat orange"><span class="rp-stat-icon"><i data-lucide="lock-keyhole"></i></span><div><small>Protected Roles</small><strong id="statProtected">0</strong><div class="trend">System-managed access</div></div></article>
        <article class="rp-stat blue"><span class="rp-stat-icon"><i data-lucide="users"></i></span><div><small>Assigned Users</small><strong id="statUsers">0</strong><div class="trend">Login accounts</div></div></article>
    </section>

    <section class="ui-card rp-card">
        <div class="rp-card-head"><strong>School Roles</strong><small id="recordInfo" class="text-muted">Loading...</small></div>
        <div class="rp-filter">
            <input id="roleSearch" class="form-control" type="search" placeholder="Search role name, key or description...">
            <select id="statusFilter" class="form-select"><option value="all">All Statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select>
            <button id="resetFilterButton" class="btn-ui" type="button"><i data-lucide="rotate-ccw"></i> Reset</button>
        </div>
        <div class="rp-table-wrap">
            <table class="data-table rp-table">
                <thead><tr><th>Role</th><th>Role Key</th><th>Users</th><th>Visible Menus</th><th>Type</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
                <tbody id="roleTableBody"><tr><td colspan="8" class="rp-empty">Loading roles...</td></tr></tbody>
            </table>
        </div>
        <div class="rp-pagination"><small id="pageInfo" class="text-muted"></small><div id="pagination" class="rp-page-buttons"></div></div>
    </section>
</div>

<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:1100">
    <div id="roleToast" class="toast border-0" role="alert"><div class="toast-header"><i id="toastIcon" data-lucide="circle-check" class="me-2"></i><strong id="toastTitle" class="me-auto">Success</strong><button type="button" class="btn-close" data-bs-dismiss="toast"></button></div><div id="toastBody" class="toast-body"></div></div>
</div>

<div class="modal fade" id="roleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><form id="roleForm" novalidate>
        <div class="modal-header"><div><h5 id="roleModalTitle" class="modal-title">Add Role</h5><small class="text-muted">Create or update a school login role.</small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><input id="roleId" type="hidden"><div class="rp-form-grid">
            <div><label class="form-label fw-semibold">Role Name *</label><input id="roleName" class="form-control" maxlength="120" required></div>
            <div><label class="form-label fw-semibold">Role Key *</label><input id="roleKey" class="form-control" maxlength="80" pattern="[a-z0-9_]+" required><div class="form-text">Lowercase letters, numbers and underscores.</div></div>
            <div><label class="form-label fw-semibold">Status *</label><select id="roleStatus" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
            <div class="full"><label class="form-label fw-semibold">Description</label><textarea id="roleDescription" class="form-control" rows="3" maxlength="500"></textarea></div>
        </div></div>
        <div class="modal-footer"><button type="button" class="btn-ui" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn-ui btn-primary-ui"><i data-lucide="save"></i> Save Role</button></div>
    </form></div></div>
</div>

<div class="modal fade" id="copyRoleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered"><div class="modal-content"><form id="copyRoleForm" novalidate>
        <div class="modal-header"><div><h5 class="modal-title">Copy Role</h5><small id="copyRoleHint" class="text-muted"></small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><input id="copySourceRoleId" type="hidden"><div class="rp-form-grid">
            <div class="full"><label class="form-label fw-semibold">New Role Name *</label><input id="copyRoleName" class="form-control" maxlength="120" required></div>
            <div class="full"><label class="form-label fw-semibold">New Role Key *</label><input id="copyRoleKey" class="form-control" maxlength="80" pattern="[a-z0-9_]+" required></div>
        </div></div>
        <div class="modal-footer"><button type="button" class="btn-ui" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn-ui btn-primary-ui"><i data-lucide="copy"></i> Copy Role</button></div>
    </form></div></div>
</div>

<div class="modal fade" id="permissionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered rp-modal-wide"><div class="modal-content"><form id="permissionForm">
        <div class="modal-header"><div><h5 id="permissionTitle" class="modal-title">Manage Permissions</h5><small id="permissionSubtitle" class="text-muted">Only Super Admin-assigned sidebar options are available, in the same order as the School Admin sidebar.</small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body p-0"><input id="permissionRoleId" type="hidden">
            <div class="rp-matrix-toolbar"><input id="matrixSearch" class="form-control" type="search" placeholder="Search assigned sidebar menu..."><button id="selectAllButton" class="btn-ui" type="button"><i data-lucide="check-check"></i> Select All</button><button id="clearAllButton" class="btn-ui" type="button"><i data-lucide="square"></i> Clear All</button></div>
            <div id="matrixNote" class="rp-matrix-note">Menus follow the exact School Admin sidebar structure and sequence. View controls visibility. Add, Edit and Delete are enforced on buttons and server-side API actions; turning off a parent clears all child permissions.</div>
            <div id="matrixContent" class="rp-matrix-wrap"><div class="rp-loading">Loading permission matrix...</div></div>
        </div>
        <div class="modal-footer"><small id="matrixCount" class="text-muted me-auto">0 visible menus</small><button type="button" class="btn-ui" data-bs-dismiss="modal">Close</button><button id="savePermissionButton" type="submit" class="btn-ui btn-primary-ui"><i data-lucide="save"></i> Save Permissions</button></div>
    </form></div></div>
</div>

<script>
(function(){
    'use strict';

    const apiUrl = new URL('../api/roles-permissions.php', window.location.href).href;
    let csrfToken = <?= json_encode($rolesPermissionsCsrf, JSON_UNESCAPED_SLASHES) ?>;
    let page = 1;
    let roles = [];
    let activeMatrix = null;
    let capabilities = {view:false,create:false,edit:false,delete:false,manage:false};
    let searchTimer = null;

    const $ = id => document.getElementById(id);
    const esc = value => String(value ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    const number = value => Number(value || 0).toLocaleString('en-IN');
    const titleCase = value => String(value || '').replace(/_/g,' ').replace(/\b\w/g, char => char.toUpperCase());
    const dateTime = value => value ? new Date(String(value).replace(' ','T')).toLocaleString('en-IN',{day:'2-digit',month:'short',year:'numeric'}) : '-';
    const slug = value => String(value || '').toLowerCase().trim().replace(/[^a-z0-9]+/g,'_').replace(/^_+|_+$/g,'');

    function modal(id){
        const element = $(id);
        return element && window.bootstrap?.Modal ? bootstrap.Modal.getOrCreateInstance(element) : null;
    }

    function toast(message, success=true){
        const element = $('roleToast');
        if(!element){ alert(message); return; }
        element.classList.remove('text-bg-success','text-bg-danger');
        element.classList.add(success ? 'text-bg-success' : 'text-bg-danger');
        $('toastTitle').textContent = success ? 'Success' : 'Error';
        $('toastBody').textContent = message;
        $('toastIcon').setAttribute('data-lucide', success ? 'circle-check' : 'circle-alert');
        window.lucide?.createIcons();
        window.bootstrap?.Toast ? bootstrap.Toast.getOrCreateInstance(element,{delay:4500}).show() : alert(message);
    }

    async function parseResponse(response){
        const text = await response.text();
        let result;
        try{ result = JSON.parse(text); }
        catch(error){ console.error(text); throw new Error('The Roles & Permissions API returned an invalid response.'); }
        if(result.data?.csrf_token) csrfToken = result.data.csrf_token;
        if(!response.ok || !result.success) throw new Error(result.message || `HTTP ${response.status}`);
        return result;
    }

    async function request(action, data={}, method='GET'){
        if(method === 'GET'){
            const url = new URL(apiUrl);
            url.searchParams.set('action',action);
            Object.entries(data).forEach(([key,value]) => {
                if(value !== '' && value !== null && value !== undefined) url.searchParams.set(key,String(value));
            });
            return parseResponse(await fetch(url,{headers:{Accept:'application/json'},credentials:'same-origin',cache:'no-store'}));
        }
        return parseResponse(await fetch(apiUrl,{
            method:'POST',
            headers:{'Content-Type':'application/json',Accept:'application/json'},
            credentials:'same-origin',
            body:JSON.stringify({action,csrf_token:csrfToken,...data})
        }));
    }

    function badge(value){
        const key = String(value || '').toLowerCase();
        return `<span class="rp-badge ${esc(key)}">${esc(titleCase(value || '-'))}</span>`;
    }

    function updateStats(stats={}){
        $('statTotal').textContent = number(stats.total_roles);
        $('statActive').textContent = number(stats.active_roles);
        $('statProtected').textContent = number(stats.protected_roles);
        $('statUsers').textContent = number(stats.assigned_users);
    }

    function renderRoles(records=[]){
        roles = records;
        if(!records.length){
            $('roleTableBody').innerHTML = '<tr><td colspan="8" class="rp-empty">No roles found.</td></tr>';
            return;
        }

        $('roleTableBody').innerHTML = records.map(role => {
            const protectedRole = Boolean(role.is_protected);
            const canEdit = capabilities.edit && role.can_edit;
            const canManage = capabilities.manage && role.can_manage_permissions;
            const nextStatus = role.status === 'active' ? 'inactive' : 'active';
            return `<tr>
                <td><div class="rp-role"><span class="rp-role-icon"><i data-lucide="shield"></i></span><div><strong>${esc(role.role_name)}</strong><small>${esc(role.description || 'No description')}</small></div></div></td>
                <td><span class="rp-key">${esc(role.role_key)}</span></td>
                <td><span class="rp-count">${number(role.user_count)}</span></td>
                <td><span class="rp-count">${number(role.permission_count)}</span></td>
                <td>${badge(protectedRole ? 'system' : 'custom')}</td>
                <td>${badge(role.status)}</td>
                <td>${esc(dateTime(role.created_at))}</td>
                <td><div class="rp-actions">
                    ${canEdit ? `<button class="rp-action js-edit" data-id="${Number(role.id)}" title="Edit" type="button"><i data-lucide="pencil"></i></button>` : ''}
                    ${canManage ? `<button class="rp-action info js-permissions" data-id="${Number(role.id)}" title="Permissions" type="button"><i data-lucide="key-round"></i></button>` : ''}
                    ${capabilities.create ? `<button class="rp-action js-copy" data-id="${Number(role.id)}" title="Copy Role" type="button"><i data-lucide="copy"></i></button>` : ''}
                    ${canEdit && !protectedRole ? `<button class="rp-action ${nextStatus === 'active' ? 'success' : 'warning'} js-status" data-id="${Number(role.id)}" title="${nextStatus === 'active' ? 'Activate' : 'Deactivate'}" type="button"><i data-lucide="${nextStatus === 'active' ? 'badge-check' : 'ban'}"></i></button>` : ''}
                    ${capabilities.delete && role.can_delete ? `<button class="rp-action danger js-delete" data-id="${Number(role.id)}" title="Delete" type="button"><i data-lucide="trash-2"></i></button>` : ''}
                </div></td>
            </tr>`;
        }).join('');

        document.querySelectorAll('.js-edit').forEach(button => button.onclick = () => openRole(Number(button.dataset.id)));
        document.querySelectorAll('.js-permissions').forEach(button => button.onclick = () => openPermissions(Number(button.dataset.id)));
        document.querySelectorAll('.js-copy').forEach(button => button.onclick = () => openCopy(Number(button.dataset.id)));
        document.querySelectorAll('.js-status').forEach(button => button.onclick = () => toggleStatus(Number(button.dataset.id)));
        document.querySelectorAll('.js-delete').forEach(button => button.onclick = () => deleteRole(Number(button.dataset.id)));
        window.lucide?.createIcons();
    }

    function renderPagination(pagination={}){
        const total = Number(pagination.total || 0);
        const current = Number(pagination.page || 1);
        const perPage = Number(pagination.per_page || 10);
        const last = Math.max(1,Number(pagination.last_page || 1));
        const from = total ? ((current - 1) * perPage) + 1 : 0;
        const to = Math.min(total,current * perPage);
        $('recordInfo').textContent = `${total} role${total === 1 ? '' : 's'}`;
        $('pageInfo').textContent = `Showing ${from}-${to} of ${total}`;
        let html = `<button class="rp-page-button" data-page="${Math.max(1,current-1)}" ${current <= 1 ? 'disabled' : ''}>‹</button>`;
        for(let index=Math.max(1,current-2);index<=Math.min(last,current+2);index++) html += `<button class="rp-page-button ${index===current?'active':''}" data-page="${index}">${index}</button>`;
        html += `<button class="rp-page-button" data-page="${Math.min(last,current+1)}" ${current >= last ? 'disabled' : ''}>›</button>`;
        $('pagination').innerHTML = html;
        document.querySelectorAll('.rp-page-button').forEach(button => button.onclick = () => {
            if(button.disabled) return;
            page = Number(button.dataset.page);
            loadRoles();
        });
    }

    async function loadRoles(){
        try{
            const response = await request('list',{
                search:$('roleSearch').value.trim(),
                status:$('statusFilter').value,
                page,
                per_page:10
            });
            capabilities = response.data.permissions || capabilities;
            $('addRoleButton').style.display = capabilities.create ? '' : 'none';
            updateStats(response.data.stats || {});
            renderRoles(response.data.records || []);
            renderPagination(response.data.pagination || {});
        }catch(error){
            $('roleTableBody').innerHTML = '<tr><td colspan="8" class="rp-empty">Unable to load roles.</td></tr>';
            toast(error.message,false);
        }
    }

    function resetRoleForm(){
        $('roleForm').reset();
        $('roleId').value = '';
        $('roleStatus').value = 'active';
        $('roleKey').readOnly = false;
    }

    async function openRole(id=0){
        resetRoleForm();
        $('roleModalTitle').textContent = id ? 'Edit Role' : 'Add Role';
        if(id){
            try{
                const response = await request('detail',{id});
                const role = response.data.record;
                $('roleId').value = role.id;
                $('roleName').value = role.role_name || '';
                $('roleKey').value = role.role_key || '';
                $('roleDescription').value = role.description || '';
                $('roleStatus').value = role.status || 'active';
                $('roleKey').readOnly = Boolean(role.is_system);
            }catch(error){ toast(error.message,false); return; }
        }
        modal('roleModal')?.show();
    }

    function openCopy(id){
        const role = roles.find(item => Number(item.id) === id);
        if(!role) return;
        $('copySourceRoleId').value = id;
        $('copyRoleHint').textContent = `Copy permissions from ${role.role_name}.`;
        $('copyRoleName').value = `${role.role_name} Copy`;
        $('copyRoleKey').value = slug(`${role.role_key}_copy`);
        modal('copyRoleModal')?.show();
    }

    async function toggleStatus(id){
        if(!confirm('Change this role status?')) return;
        try{ const response = await request('toggle_status',{id},'POST'); toast(response.message,true); await loadRoles(); }
        catch(error){ toast(error.message,false); }
    }

    async function deleteRole(id){
        if(!confirm('Delete this role? This action cannot be undone.')) return;
        try{ const response = await request('delete',{id},'POST'); toast(response.message,true); await loadRoles(); }
        catch(error){ toast(error.message,false); }
    }

    function matrixRow(id){
        return document.querySelector(`tr[data-sidebar-id="${Number(id)}"]`);
    }

    function viewBox(row){ return row?.querySelector('.js-view') || null; }
    function rowBoxes(row){ return [...(row?.querySelectorAll('.js-permission') || [])]; }

    function enableParents(row){
        let parentId = Number(row?.dataset.parentId || 0);
        const visited = new Set();
        while(parentId > 0 && !visited.has(parentId)){
            visited.add(parentId);
            const parentRow = matrixRow(parentId);
            if(!parentRow) break;
            const box = viewBox(parentRow);
            if(box && !box.disabled) box.checked = true;
            parentId = Number(parentRow.dataset.parentId || 0);
        }
    }

    function clearChildren(parentId){
        document.querySelectorAll(`tr[data-parent-id="${Number(parentId)}"]`).forEach(row => {
            rowBoxes(row).forEach(box => { if(!box.disabled) box.checked = false; });
            clearChildren(Number(row.dataset.sidebarId || 0));
        });
    }

    function normalizeRow(row, changed=null){
        const boxes = rowBoxes(row);
        const view = boxes.find(box => box.dataset.action === 'view');
        const full = boxes.find(box => box.dataset.action === 'full');
        const actions = boxes.filter(box => !['view','full'].includes(box.dataset.action));
        if(changed === full){
            boxes.filter(box => box !== full).forEach(box => { if(!box.disabled) box.checked = full.checked; });
        }
        if(changed && changed !== view && changed !== full && changed.checked && view && !view.disabled){
            view.checked = true;
        }
        if(changed === view && !view.checked){
            actions.forEach(box => { if(!box.disabled) box.checked = false; });
            if(full && !full.disabled) full.checked = false;
            clearChildren(Number(row.dataset.sidebarId || 0));
        }
        if(full && !full.disabled){
            full.checked = boxes.filter(box => box !== full && !box.disabled).every(box => box.checked);
        }
        if(view?.checked) enableParents(row);
    }

    function syncVisibleMatrixRows(){
        if(!activeMatrix) return;
        const byId = new Map((activeMatrix.items || []).map(item => [Number(item.sidebar_item_id),item]));
        document.querySelectorAll('tr[data-sidebar-id]').forEach(row => {
            const item = byId.get(Number(row.dataset.sidebarId));
            if(!item) return;
            item.can_view = row.querySelector('.js-view')?.checked ? 1 : 0;
            item.can_add = row.querySelector('.js-add')?.checked ? 1 : 0;
            item.can_edit = row.querySelector('.js-edit')?.checked ? 1 : 0;
            item.can_delete = row.querySelector('.js-delete')?.checked ? 1 : 0;
        });
    }

    function clearMatrixDescendants(parentId){
        if(!activeMatrix) return;
        const children = (activeMatrix.items || []).filter(item => Number(item.parent_id || 0) === Number(parentId));
        children.forEach(item => {
            item.can_view = 0;
            item.can_add = 0;
            item.can_edit = 0;
            item.can_delete = 0;
            clearMatrixDescendants(Number(item.sidebar_item_id));
        });
    }

    function enableMatrixAncestors(item){
        if(!activeMatrix || !item) return;
        const byId = new Map((activeMatrix.items || []).map(value => [Number(value.sidebar_item_id),value]));
        let parentId = Number(item.parent_id || 0);
        const visited = new Set();
        while(parentId > 0 && !visited.has(parentId)){
            visited.add(parentId);
            const parent = byId.get(parentId);
            if(!parent) break;
            parent.can_view = 1;
            parentId = Number(parent.parent_id || 0);
        }
    }

    function updateMatrixCount(){
        const visible = (activeMatrix?.items || []).filter(item => Number(item.can_view) === 1).length;
        $('matrixCount').textContent = `${visible} visible sidebar item${visible === 1 ? '' : 's'}`;
    }

    function renderMatrix(){
        if(!activeMatrix){ return; }
        const query = $('matrixSearch').value.trim().toLowerCase();
        const items = (activeMatrix.items || []).filter(item => [item.menu_title,item.menu_key,item.route].join(' ').toLowerCase().includes(query));
        if(!items.length){
            $('matrixContent').innerHTML = '<div class="rp-loading">No assigned sidebar options found.</div>';
            updateMatrixCount();
            return;
        }
        const locked = Boolean(activeMatrix.is_locked);
        $('matrixContent').innerHTML = `<table class="data-table rp-matrix-table"><thead><tr><th>School Admin Sidebar Order</th><th>Route</th><th>View</th><th>Add</th><th>Edit</th><th>Delete</th><th>Full</th></tr></thead><tbody>${items.map(item => `<tr data-sidebar-id="${Number(item.sidebar_item_id)}" data-parent-id="${Number(item.parent_id || 0)}">
            <td class="rp-menu-cell"><div class="rp-menu" style="padding-left:${Number(item.depth || 0) * 18}px"><span class="rp-sequence">${String(Number(item.sidebar_sequence || 0)).padStart(2, '0')}</span><span class="rp-menu-icon"><i data-lucide="${esc(item.icon || 'circle')}"></i></span><div><strong>${esc(item.menu_title)}</strong><small>${esc(item.menu_key)}${item.is_container ? ' • Module' : ' • Page'}</small></div></div></td>
            <td><span class="rp-route" title="${esc(item.route)}">${esc(item.route)}</span></td>
            ${['view','add','edit','delete'].map(action => `<td class="rp-check-cell"><input class="form-check-input rp-check js-permission js-${action}" type="checkbox" data-action="${action}" ${Number(item[`can_${action}`]) === 1 ? 'checked' : ''} ${locked ? 'disabled' : ''}></td>`).join('')}
            <td class="rp-check-cell"><input class="form-check-input rp-check js-permission js-full" type="checkbox" data-action="full" ${Number(item.can_view) === 1 && Number(item.can_add) === 1 && Number(item.can_edit) === 1 && Number(item.can_delete) === 1 ? 'checked' : ''} ${locked ? 'disabled' : ''}></td>
        </tr>`).join('')}</tbody></table>`;
        document.querySelectorAll('.js-permission').forEach(box => box.onchange = () => {
            const row = box.closest('tr');
            normalizeRow(row,box);
            syncVisibleMatrixRows();
            const item = (activeMatrix.items || []).find(value => Number(value.sidebar_item_id) === Number(row.dataset.sidebarId));
            if(item){
                if(box.dataset.action === 'view' && !box.checked){
                    clearMatrixDescendants(Number(item.sidebar_item_id));
                }else if(Number(item.can_view) === 1){
                    enableMatrixAncestors(item);
                }
            }
            renderMatrix();
        });
        window.lucide?.createIcons();
        updateMatrixCount();
    }

    async function openPermissions(roleId){
        $('permissionRoleId').value = roleId;
        $('matrixSearch').value = '';
        $('matrixContent').innerHTML = '<div class="rp-loading">Loading permission matrix...</div>';
        modal('permissionModal')?.show();
        try{
            const response = await request('matrix',{role_id:roleId});
            activeMatrix = response.data;
            $('permissionTitle').textContent = `Permissions - ${activeMatrix.role.role_name}`;
            $('permissionSubtitle').textContent = activeMatrix.is_locked ? 'Protected School Administrator access is synchronized with every sidebar option assigned by Super Admin.' : 'Only sidebar options assigned to this school are listed in the exact School Admin sidebar order.';
            $('matrixNote').innerHTML = activeMatrix.is_locked ? '<span class="rp-locked"><i data-lucide="lock-keyhole"></i> Protected role: all assigned menus are enabled.</span>' : 'Menus follow the exact School Admin sidebar structure and sequence. View controls visibility. Add, Edit and Delete are enforced on buttons and server-side API actions; turning off a parent clears all child permissions.';
            $('selectAllButton').disabled = activeMatrix.is_locked;
            $('clearAllButton').disabled = activeMatrix.is_locked;
            $('savePermissionButton').disabled = false;
            renderMatrix();
        }catch(error){
            activeMatrix = null;
            $('matrixContent').innerHTML = '<div class="rp-loading">Unable to load permission matrix.</div>';
            toast(error.message,false);
        }
    }

    $('roleForm').onsubmit = async event => {
        event.preventDefault();
        if(!event.currentTarget.reportValidity()) return;
        try{
            const response = await request('save_role',{
                id:Number($('roleId').value || 0),
                role_name:$('roleName').value.trim(),
                role_key:$('roleKey').value.trim(),
                description:$('roleDescription').value.trim(),
                status:$('roleStatus').value
            },'POST');
            modal('roleModal')?.hide();
            toast(response.message,true);
            await loadRoles();
        }catch(error){ toast(error.message,false); }
    };

    $('copyRoleForm').onsubmit = async event => {
        event.preventDefault();
        if(!event.currentTarget.reportValidity()) return;
        try{
            const response = await request('copy_role',{
                source_role_id:Number($('copySourceRoleId').value),
                role_name:$('copyRoleName').value.trim(),
                role_key:$('copyRoleKey').value.trim()
            },'POST');
            modal('copyRoleModal')?.hide();
            toast(response.message,true);
            await loadRoles();
        }catch(error){ toast(error.message,false); }
    };

    $('permissionForm').onsubmit = async event => {
        event.preventDefault();
        if(!activeMatrix) return;
        syncVisibleMatrixRows();
        const items = (activeMatrix.items || []).map(item => ({
            sidebar_item_id:Number(item.sidebar_item_id),
            can_view:Number(item.can_view) === 1 ? 1 : 0,
            can_add:Number(item.can_add) === 1 ? 1 : 0,
            can_edit:Number(item.can_edit) === 1 ? 1 : 0,
            can_delete:Number(item.can_delete) === 1 ? 1 : 0
        }));
        try{
            const response = await request('save_permissions',{
                role_id:Number($('permissionRoleId').value),
                items
            },'POST');
            toast(response.message,true);
            await openPermissions(Number($('permissionRoleId').value));
            await loadRoles();
        }catch(error){ toast(error.message,false); }
    };

    $('roleName').oninput = () => { if(!$('roleId').value && document.activeElement !== $('roleKey')) $('roleKey').value = slug($('roleName').value); };
    $('copyRoleName').oninput = () => $('copyRoleKey').value = slug($('copyRoleName').value);
    $('addRoleButton').onclick = () => openRole();
    $('refreshButton').onclick = loadRoles;
    $('resetFilterButton').onclick = () => { $('roleSearch').value=''; $('statusFilter').value='all'; page=1; loadRoles(); };
    $('statusFilter').onchange = () => { page=1; loadRoles(); };
    $('roleSearch').oninput = () => { clearTimeout(searchTimer); searchTimer=setTimeout(() => { page=1; loadRoles(); },300); };
    $('matrixSearch').oninput = renderMatrix;
    $('selectAllButton').onclick = () => {
        if(!activeMatrix || activeMatrix.is_locked) return;
        (activeMatrix.items || []).forEach(item => {
            item.can_view = 1;
            item.can_add = 1;
            item.can_edit = 1;
            item.can_delete = 1;
        });
        renderMatrix();
    };
    $('clearAllButton').onclick = () => {
        if(!activeMatrix || activeMatrix.is_locked) return;
        (activeMatrix.items || []).forEach(item => {
            item.can_view = 0;
            item.can_add = 0;
            item.can_edit = 0;
            item.can_delete = 0;
        });
        renderMatrix();
    };

    loadRoles();
    window.lucide?.createIcons();
})();
</script>

<?php require dirname(__DIR__) . '/includes/layout-end.php'; ?>

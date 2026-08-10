<?php
declare(strict_types=1);

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/bootstrap.php';

require_login();

$pageTitle = 'Multi-School Roles & Permissions';
$pageKey = 'platform_roles';
$sidebarFile = __DIR__ . '/sidebar.php';

if (!current_user_has_platform_role()) {
    http_response_code(403);
    exit('Access denied.');
}

if (!has_permission('platform_roles', 'view')) {
    http_response_code(403);
    exit('Access denied.');
}

$baseUrl = defined('BASE_URL')
    ? rtrim((string)BASE_URL, '/') . '/'
    : '../';

$csrfToken = function_exists('csrfToken')
    ? (string)csrfToken()
    : (
        function_exists('csrf_token')
            ? (string)csrf_token()
            : ''
    );

$canCreate = has_permission('platform_roles', 'create');
$canEdit = has_permission('platform_roles', 'edit');
$canDelete = has_permission('platform_roles', 'delete');
$canManage = has_permission('platform_permissions', 'manage');
$canImport = has_permission('platform_permissions', 'import');
$canExport = has_permission('platform_permissions', 'export');
$canAssign = has_permission('platform_users', 'manage')
    || has_permission('platform_users', 'edit');
$canAudit = has_permission('platform_activity_logs', 'view');

require $projectRoot . '/includes/layout-start.php';
?>

<style>
.rbac-page{display:grid;gap:16px}
.rbac-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.rbac-kpi{padding:16px}
.rbac-kpi small,.rbac-kpi strong{display:block}
.rbac-kpi small{color:var(--text-muted,#64748b);font-size:9px;font-weight:750}
.rbac-kpi strong{margin-top:5px;font-size:22px;font-weight:850}
.rbac-toolbar{display:grid;grid-template-columns:minmax(210px,1.15fr) minmax(190px,1fr) minmax(190px,1fr) minmax(150px,.7fr) auto;gap:12px;align-items:end;padding:16px}
.rbac-field label{display:block;margin-bottom:6px;font-size:10px;font-weight:750}
.rbac-table{min-width:1260px}
.rbac-empty{padding:30px;color:var(--text-muted,#64748b);text-align:center}
.rbac-role-copy strong,.rbac-role-copy small{display:block}
.rbac-role-copy strong{font-size:11px}
.rbac-role-copy small{margin-top:3px;color:var(--text-muted,#64748b);font-size:9px}
.rbac-status{display:inline-flex;padding:4px 8px;border-radius:999px;font-size:9px;font-weight:800;text-transform:capitalize}
.rbac-status.active{color:var(--success-color,#21ae71);background:rgba(33,174,113,.1)}
.rbac-status.inactive{color:var(--danger-color,#f54267);background:rgba(245,66,103,.1)}
.rbac-mode{display:inline-flex;padding:4px 8px;border-radius:999px;background:var(--body-bg,#f6f8fc);font-size:9px;font-weight:800;text-transform:capitalize}
.rbac-actions{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:6px}
.rbac-action-btn{width:31px;height:31px;display:inline-grid;place-items:center;border:1px solid var(--border-soft,#e7ebf3);border-radius:9px;color:var(--text-main,#101b46);background:var(--card-bg,#fff)}
.rbac-action-btn:hover{color:var(--brand-1,#6747e8);background:var(--sidebar-hover-bg,#eef2ff)}
.rbac-action-btn.danger:hover{color:var(--danger-color,#f54267)}
.rbac-action-btn svg{width:14px;height:14px}
.rbac-permission-wrap{height:100%;min-height:0;max-height:none;overflow:auto;overscroll-behavior:contain;scroll-behavior:smooth;-webkit-overflow-scrolling:touch;border:1px solid var(--border-soft,#e7ebf3);border-radius:12px;scrollbar-gutter:stable both-edges}
#permissionModal .modal-dialog{height:calc(100dvh - 32px);max-height:calc(100dvh - 32px);margin:16px auto}
#permissionModal .modal-content{height:100%;max-height:100%;overflow:hidden}
#permissionModal #permissionForm{display:flex;flex-direction:column;height:100%;min-height:0}
#permissionModal .modal-header,#permissionModal .modal-footer{flex:0 0 auto}
#permissionModal .modal-body{display:flex;flex:1 1 auto;flex-direction:column;min-height:0;overflow:hidden}
#permissionModal .rbac-scope-grid,#permissionModal #bulkRoleBox,#permissionModal #inheritNote,#permissionModal .nav-tabs{flex:0 0 auto}
#permissionModal .rbac-tab-content{display:flex;flex:1 1 auto;min-height:0;overflow:hidden;padding-top:14px}
#permissionModal .tab-pane{width:100%;height:100%;min-height:0;overflow:hidden}
#permissionModal .tab-pane.active{display:flex;flex-direction:column}
#permissionModal #permissionMatrixTab>.d-flex,#permissionModal #sidebarMatrixTab>.d-flex{flex:0 0 auto}
#permissionModal #permissionMatrixTab .rbac-permission-wrap{flex:1 1 auto;min-height:0}
#permissionModal #sidebarPermissionList{flex:1 1 auto;min-height:0;overflow:auto;overscroll-behavior:contain;scroll-behavior:smooth;-webkit-overflow-scrolling:touch;padding-right:4px;scrollbar-gutter:stable}

.rbac-permission-table{min-width:1660px;margin:0}
.rbac-permission-table th,.rbac-permission-table td{padding:9px 8px;border-right:1px solid var(--border-soft,#e7ebf3);border-bottom:1px solid var(--border-soft,#e7ebf3);vertical-align:middle}
.rbac-permission-table thead th{position:sticky;top:0;z-index:3;background:var(--card-bg,#fff);font-size:9px;text-transform:uppercase;white-space:nowrap}
.rbac-permission-table .module-row td{background:var(--body-bg,#f6f8fc);font-weight:850}
.rbac-page-cell{min-width:250px}
.rbac-page-cell strong,.rbac-page-cell small{display:block}
.rbac-page-cell small{margin-top:3px;color:var(--text-muted,#64748b);font-size:8px}
.rbac-permission-check,.rbac-column-check,.rbac-page-check,.rbac-module-check,.rbac-all-check,.rbac-role-select{width:18px;height:18px;cursor:pointer}
.rbac-scope-grid{display:grid;grid-template-columns:minmax(200px,1fr) minmax(200px,1fr) minmax(190px,1fr) minmax(190px,1fr);gap:12px;padding:13px;border:1px solid var(--border-soft,#e7ebf3);border-radius:12px;background:var(--body-bg,#f6f8fc)}
.rbac-bulk-box{display:none;padding:12px;border:1px dashed var(--border-soft,#e7ebf3);border-radius:12px}
.rbac-bulk-box.show{display:block}
.rbac-bulk-roles{display:flex;flex-wrap:wrap;gap:7px;margin-top:8px}
.rbac-bulk-role{display:inline-flex;align-items:center;gap:6px;padding:6px 9px;border-radius:999px;background:var(--card-bg,#fff);font-size:9px;font-weight:750}
.rbac-sidebar-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}
.rbac-sidebar-item{display:grid;grid-template-columns:auto 38px 1fr;align-items:center;gap:10px;padding:10px;border:1px solid var(--border-soft,#e7ebf3);border-radius:11px}
.rbac-sidebar-item.child{margin-left:20px}
.rbac-sidebar-icon{width:34px;height:34px;display:grid;place-items:center;border-radius:10px;background:rgba(101,71,232,.09);color:var(--brand-1,#6547e8)}
.rbac-sidebar-icon svg{width:16px}
.rbac-sidebar-copy strong,.rbac-sidebar-copy small{display:block}
.rbac-sidebar-copy strong{font-size:10px}
.rbac-sidebar-copy small{margin-top:2px;font-size:8px;color:var(--text-muted,#64748b)}
.rbac-users-grid{display:grid;gap:10px}
.rbac-user-role-row{display:grid;grid-template-columns:minmax(220px,1fr) 1.5fr;gap:14px;align-items:center;padding:12px;border:1px solid var(--border-soft,#e7ebf3);border-radius:11px}
.rbac-user-copy strong,.rbac-user-copy small{display:block}
.rbac-user-copy strong{font-size:11px}
.rbac-user-copy small{margin-top:3px;color:var(--text-muted,#64748b);font-size:9px}
.rbac-role-checkboxes{display:flex;flex-wrap:wrap;gap:8px}
.rbac-role-option{display:inline-flex;align-items:center;gap:6px;padding:7px 9px;border:1px solid var(--border-soft,#e7ebf3);border-radius:999px;background:var(--body-bg,#f6f8fc);font-size:9px;font-weight:700}
.rbac-message{display:none;margin:0}
.rbac-message.show{display:block}
.rbac-audit-table{min-width:1050px}
.rbac-tab-content{padding-top:14px}
.rbac-disabled-note{display:none}
.rbac-disabled-note.show{display:block}
@media(max-width:1199.98px){.rbac-toolbar{grid-template-columns:repeat(2,minmax(0,1fr)) auto}.rbac-scope-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:991.98px){.rbac-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.rbac-user-role-row{grid-template-columns:1fr}.rbac-sidebar-list{grid-template-columns:1fr}}
@media(max-width:767.98px){.rbac-toolbar,.rbac-scope-grid{grid-template-columns:1fr}}
@media(max-width:575.98px){.rbac-kpis{grid-template-columns:1fr}#permissionModal .modal-dialog{height:100dvh;max-height:100dvh;margin:0;max-width:none}#permissionModal .modal-content{border-radius:0}#permissionModal .modal-body{padding:12px}#permissionModal .rbac-scope-grid{gap:9px;padding:10px}.rbac-permission-table{min-width:1500px}}
@supports not (height:100dvh){#permissionModal .modal-dialog{height:calc(100vh - 32px);max-height:calc(100vh - 32px)}@media(max-width:575.98px){#permissionModal .modal-dialog{height:100vh;max-height:100vh}}}
</style>

<div class="rbac-page">
    <div class="page-heading">
        <div>
            <h1 class="page-title">Roles & Permissions</h1>
            <p class="page-subtitle">
                Manage school-wise and branch-wise roles, permissions,
                sidebar visibility and inheritance.
            </p>
        </div>

        <div class="page-actions">
            <?php if ($canAudit): ?>
                <button id="auditButton" class="btn-ui" type="button">
                    <i data-lucide="history"></i>Audit Log
                </button>
            <?php endif; ?>

            <?php if ($canImport): ?>
                <button id="importButton" class="btn-ui" type="button">
                    <i data-lucide="file-up"></i>Import
                </button>
            <?php endif; ?>

            <?php if ($canAssign): ?>
                <button id="assignUsersButton" class="btn-ui" type="button">
                    <i data-lucide="users-round"></i>Assign Users
                </button>
            <?php endif; ?>

            <?php if ($canCreate): ?>
                <button
                    id="addRoleButton"
                    class="btn-ui btn-primary-ui"
                    type="button"
                >
                    <i data-lucide="shield-plus"></i>Add Role
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div id="rbacMessage" class="alert rbac-message"></div>

    <div class="rbac-kpis">
        <article class="ui-card rbac-kpi">
            <small>Total Schools</small>
            <strong id="schoolCount">0</strong>
        </article>
        <article class="ui-card rbac-kpi">
            <small>Total Branches</small>
            <strong id="branchCount">0</strong>
        </article>
        <article class="ui-card rbac-kpi">
            <small>School Roles</small>
            <strong id="roleCount">0</strong>
        </article>
        <article class="ui-card rbac-kpi">
            <small>Permission Definitions</small>
            <strong id="permissionCount">0</strong>
        </article>
    </div>

    <section class="ui-card">
        <div class="rbac-toolbar">
            <div class="rbac-field">
                <label for="schoolFilter">School</label>
                <select id="schoolFilter" class="form-select">
                    <option value="">All Schools</option>
                </select>
            </div>

            <div class="rbac-field">
                <label for="branchFilter">Branch</label>
                <select id="branchFilter" class="form-select" disabled>
                    <option value="">School-level permissions</option>
                </select>
            </div>

            <div class="rbac-field">
                <label for="roleSearch">Search roles</label>
                <input
                    id="roleSearch"
                    class="form-control"
                    type="search"
                    placeholder="School, branch, role or key"
                >
            </div>

            <div class="rbac-field">
                <label for="roleStatusFilter">Status</label>
                <select id="roleStatusFilter" class="form-select">
                    <option value="">All status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            <button id="refreshButton" class="btn-ui" type="button">
                <i data-lucide="refresh-cw"></i>Refresh
            </button>
        </div>

        <div class="px-3 pb-3 d-flex flex-wrap gap-2">
            <?php if ($canManage): ?>
                <button id="bulkPermissionButton" class="btn-ui" type="button">
                    <i data-lucide="list-checks"></i>
                    Bulk Assign Permissions
                </button>
            <?php endif; ?>

            <button id="clearSelectionButton" class="btn-ui" type="button">
                Clear Selection
            </button>
        </div>

        <div class="table-responsive">
            <table class="data-table rbac-table">
                <thead>
                    <tr>
                        <th>
                            <input
                                id="selectAllRoles"
                                class="form-check-input"
                                type="checkbox"
                                aria-label="Select all roles"
                            >
                        </th>
                        <th>School</th>
                        <th>Role</th>
                        <th>Role Key</th>
                        <th>Users</th>
                        <th>Permissions</th>
                        <th>Branch Permission</th>
                        <th>Branch Sidebar</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>

                <tbody id="rolesBody">
                    <tr>
                        <td colspan="10" class="rbac-empty">
                            Loading roles...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</div>

<div class="modal fade" id="roleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="roleForm" novalidate>
                <div class="modal-header">
                    <div>
                        <h5 id="roleModalTitle" class="modal-title">
                            Add School Role
                        </h5>
                        <small class="text-muted">
                            Create a database-driven role for one school.
                        </small>
                    </div>
                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                    ></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" name="action" value="save_role">
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($csrfToken) ?>"
                    >
                    <input id="roleId" type="hidden" name="role_id">

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="roleSchool">
                            School <span class="text-danger">*</span>
                        </label>
                        <select
                            id="roleSchool"
                            name="school_id"
                            class="form-select"
                            required
                        ></select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="roleName">
                            Role Name <span class="text-danger">*</span>
                        </label>
                        <input
                            id="roleName"
                            name="role_name"
                            class="form-control"
                            maxlength="120"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="roleKey">
                            Role Key
                        </label>
                        <input
                            id="roleKey"
                            name="role_key"
                            class="form-control"
                            maxlength="80"
                            placeholder="Auto generated"
                        >
                        <div class="form-text">
                            Unique inside the selected school.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label
                            class="form-label fw-semibold"
                            for="roleDescription"
                        >
                            Description
                        </label>
                        <textarea
                            id="roleDescription"
                            name="description"
                            class="form-control"
                            rows="3"
                            maxlength="500"
                        ></textarea>
                    </div>

                    <div>
                        <label class="form-label fw-semibold" for="roleStatus">
                            Status
                        </label>
                        <select
                            id="roleStatus"
                            name="status"
                            class="form-select"
                        >
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn-ui"
                        data-bs-dismiss="modal"
                    >
                        Cancel
                    </button>
                    <button
                        id="saveRoleButton"
                        type="submit"
                        class="btn-ui btn-primary-ui"
                    >
                        Save Role
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="copyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="copyForm">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Copy Role</h5>
                        <small id="copySourceText" class="text-muted"></small>
                    </div>
                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                    ></button>
                </div>

                <div class="modal-body">
                    <input id="copySourceRoleId" type="hidden">

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="copySchool">
                            Target School
                        </label>
                        <select id="copySchool" class="form-select" required>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="copyName">
                            New Role Name
                        </label>
                        <input
                            id="copyName"
                            class="form-control"
                            maxlength="120"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="copyKey">
                            New Role Key
                        </label>
                        <input
                            id="copyKey"
                            class="form-control"
                            maxlength="80"
                            required
                        >
                    </div>

                    <label class="form-check">
                        <input
                            id="copyBranchOverrides"
                            class="form-check-input"
                            type="checkbox"
                            value="1"
                        >
                        <span class="form-check-label">
                            Copy branch overrides when the target is the same
                            school
                        </span>
                    </label>
                </div>

                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn-ui"
                        data-bs-dismiss="modal"
                    >
                        Cancel
                    </button>
                    <button
                        id="copyRoleButton"
                        type="submit"
                        class="btn-ui btn-primary-ui"
                    >
                        Copy Role
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div
    class="modal fade"
    id="permissionModal"
    tabindex="-1"
    aria-hidden="true"
>
    <div
        class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"
    >
        <div class="modal-content">
            <form id="permissionForm">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">
                            School & Branch Permissions
                        </h5>
                        <small id="permissionRoleText" class="text-muted">
                            Configure role access.
                        </small>
                    </div>
                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                    ></button>
                </div>

                <div class="modal-body">
                    <input id="permissionRoleId" type="hidden">

                    <div id="bulkRoleBox" class="rbac-bulk-box mb-3">
                        <strong class="small">Bulk target roles</strong>
                        <div id="bulkRoleNames" class="rbac-bulk-roles"></div>
                    </div>

                    <div class="rbac-scope-grid mb-3">
                        <div class="rbac-field">
                            <label for="permissionSchool">School</label>
                            <select
                                id="permissionSchool"
                                class="form-select"
                                disabled
                            ></select>
                        </div>

                        <div class="rbac-field">
                            <label for="permissionBranch">
                                Branch
                            </label>
                            <select
                                id="permissionBranch"
                                class="form-select"
                            >
                                <option value="">
                                    School-level permissions
                                </option>
                            </select>
                        </div>

                        <div class="rbac-field">
                            <label for="permissionMode">
                                Permission inheritance
                            </label>
                            <select
                                id="permissionMode"
                                class="form-select"
                            >
                                <option value="inherit">
                                    Inherit School Permissions
                                </option>
                                <option value="custom">
                                    Custom Branch Permissions
                                </option>
                            </select>
                        </div>

                        <div class="rbac-field">
                            <label for="sidebarMode">
                                Sidebar inheritance
                            </label>
                            <select
                                id="sidebarMode"
                                class="form-select"
                            >
                                <option value="inherit">
                                    Inherit School Sidebar
                                </option>
                                <option value="custom">
                                    Custom Branch Sidebar
                                </option>
                            </select>
                        </div>
                    </div>

                    <div
                        id="inheritNote"
                        class="alert alert-info rbac-disabled-note"
                    >
                        This branch currently inherits the school-level
                        permissions. Select Custom to edit branch overrides.
                    </div>

                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button
                                class="nav-link active"
                                data-bs-toggle="tab"
                                data-bs-target="#permissionMatrixTab"
                                type="button"
                            >
                                Permission Matrix
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button
                                class="nav-link"
                                data-bs-toggle="tab"
                                data-bs-target="#sidebarMatrixTab"
                                type="button"
                            >
                                Sidebar Menus
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content rbac-tab-content">
                        <div
                            id="permissionMatrixTab"
                            class="tab-pane fade show active"
                        >
                            <div
                                class="d-flex flex-wrap justify-content-between gap-3 mb-3"
                            >
                                <label class="fw-semibold">
                                    <input
                                        id="selectAllPermissions"
                                        class="form-check-input rbac-all-check me-2"
                                        type="checkbox"
                                    >
                                    Select All Permissions
                                </label>

                                <div
                                    id="permissionColumnSelectors"
                                    class="d-flex flex-wrap gap-3"
                                ></div>
                            </div>

                            <div class="rbac-permission-wrap">
                                <table
                                    class="table rbac-permission-table"
                                >
                                    <thead id="permissionHead"></thead>
                                    <tbody id="permissionBody"></tbody>
                                </table>
                            </div>
                        </div>

                        <div
                            id="sidebarMatrixTab"
                            class="tab-pane fade"
                        >
                            <div
                                class="d-flex justify-content-between align-items-center mb-3"
                            >
                                <label class="fw-semibold">
                                    <input
                                        id="selectAllSidebar"
                                        class="form-check-input me-2"
                                        type="checkbox"
                                    >
                                    Enable All Sidebar Menus
                                </label>
                            </div>

                            <div
                                id="sidebarPermissionList"
                                class="rbac-sidebar-list"
                            ></div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn-ui"
                        data-bs-dismiss="modal"
                    >
                        Cancel
                    </button>

                    <?php if ($canManage): ?>
                        <button
                            id="savePermissionsButton"
                            type="submit"
                            class="btn-ui btn-primary-ui"
                        >
                            Save Permissions
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="importForm" enctype="multipart/form-data">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Import Role Permissions</h5>
                        <small class="text-muted">
                            Import a JSON file created by this module.
                        </small>
                    </div>
                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                    ></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="importSchool">
                            Target School
                        </label>
                        <select
                            id="importSchool"
                            name="school_id"
                            class="form-select"
                            required
                        ></select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="importRole">
                            Update Existing Role
                        </label>
                        <select
                            id="importRole"
                            name="role_id"
                            class="form-select"
                        >
                            <option value="">Create a new role</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="importName">
                            Role Name Override
                        </label>
                        <input
                            id="importName"
                            name="role_name"
                            class="form-control"
                            maxlength="120"
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="importKey">
                            Role Key Override
                        </label>
                        <input
                            id="importKey"
                            name="role_key"
                            class="form-control"
                            maxlength="80"
                        >
                    </div>

                    <div>
                        <label class="form-label fw-semibold" for="importFile">
                            JSON File
                        </label>
                        <input
                            id="importFile"
                            name="import_file"
                            class="form-control"
                            type="file"
                            accept=".json,application/json"
                            required
                        >
                    </div>
                </div>

                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn-ui"
                        data-bs-dismiss="modal"
                    >
                        Cancel
                    </button>
                    <button
                        id="importSaveButton"
                        type="submit"
                        class="btn-ui btn-primary-ui"
                    >
                        Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="userRolesModal" tabindex="-1" aria-hidden="true">
    <div
        class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"
    >
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Assign School Roles</h5>
                    <small class="text-muted">
                        Assign one or multiple roles to users of the selected
                        school.
                    </small>
                </div>
                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                ></button>
            </div>
            <div class="modal-body">
                <div id="userRolesGrid" class="rbac-users-grid">
                    <div class="rbac-empty">Select a school first.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button
                    type="button"
                    class="btn-ui"
                    data-bs-dismiss="modal"
                >
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="auditModal" tabindex="-1" aria-hidden="true">
    <div
        class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"
    >
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Role Permission Audit Log</h5>
                    <small class="text-muted">
                        Who changed permissions, where and when.
                    </small>
                </div>
                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                ></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="data-table rbac-audit-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>School</th>
                                <th>Branch</th>
                                <th>Changed By</th>
                                <th>Action</th>
                                <th>Description</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody id="auditBody">
                            <tr>
                                <td colspan="7" class="rbac-empty">
                                    Loading audit history...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button
                    type="button"
                    class="btn-ui"
                    data-bs-dismiss="modal"
                >
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    const apiUrl = <?= json_encode(
        $baseUrl . 'api/roles.php',
        JSON_UNESCAPED_SLASHES
    ) ?>;

    const csrfToken = <?= json_encode(
        $csrfToken,
        JSON_UNESCAPED_SLASHES
    ) ?>;

    const capabilities = {
        create: <?= $canCreate ? 'true' : 'false' ?>,
        edit: <?= $canEdit ? 'true' : 'false' ?>,
        delete: <?= $canDelete ? 'true' : 'false' ?>,
        manage: <?= $canManage ? 'true' : 'false' ?>,
        import: <?= $canImport ? 'true' : 'false' ?>,
        export: <?= $canExport ? 'true' : 'false' ?>,
        assign: <?= $canAssign ? 'true' : 'false' ?>,
        audit: <?= $canAudit ? 'true' : 'false' ?>
    };

    let schools = [];
    let branches = [];
    let roles = [];
    let users = [];
    let actions = [];
    let permissionModules = [];
    let sidebarItems = [];
    let selectedRoleIds = new Set();
    let matrixRoleIds = [];
    let currentMatrixRole = null;
    let timer = null;

    const byId = id => document.getElementById(id);

    const escapeHtml = value => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    /*
     * layout-end.php may load Bootstrap after this page script.
     * Resolve the modal instance only when show/hide is requested,
     * so an early `bootstrap is not defined` error cannot stop AJAX,
     * buttons, filters, role loading, or database values.
     */
    function modalController(elementId) {
        function instance() {
            const element = byId(elementId);

            if (
                !element
                || !window.bootstrap
                || !window.bootstrap.Modal
            ) {
                return null;
            }

            return window.bootstrap.Modal.getOrCreateInstance(
                element
            );
        }

        return {
            show() {
                const modal = instance();

                if (!modal) {
                    message(
                        'Bootstrap JavaScript is not loaded. Check layout-end.php.',
                        false
                    );
                    return;
                }

                modal.show();
            },

            hide() {
                instance()?.hide();
            }
        };
    }

    const roleModal = modalController('roleModal');
    const copyModal = modalController('copyModal');
    const permissionModal = modalController('permissionModal');
    const importModal = modalController('importModal');
    const userRolesModal = modalController('userRolesModal');
    const auditModal = modalController('auditModal');

    window.addEventListener('error', event => {
        const errorMessage = event.error?.message
            || event.message
            || 'A JavaScript error stopped the page.';

        console.error(event.error || eventMessage);

        const box = byId('rbacMessage');

        if (box) {
            box.className =
                'alert rbac-message show alert-danger';
            box.textContent = errorMessage;
        }
    });

    function message(text, success) {
        const box = byId('rbacMessage');
        box.className = 'alert rbac-message show '
            + (success ? 'alert-success' : 'alert-danger');
        box.textContent = text;
        box.scrollIntoView({behavior: 'smooth', block: 'nearest'});
    }

    async function parseJson(response) {
        const text = await response.text();

        if (!text.trim()) {
            throw new Error('The API returned an empty response.');
        }

        try {
            return JSON.parse(text);
        } catch (error) {
            console.error(text);
            throw new Error(
                'The API returned invalid JSON. Check the PHP error log.'
            );
        }
    }

    async function request(
        action,
        {method = 'GET', data = {}} = {}
    ) {
        const url = new URL(apiUrl, window.location.origin);
        const options = {
            method,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        };

        if (method === 'GET') {
            url.searchParams.set('action', action);

            Object.entries(data).forEach(([key, value]) => {
                if (value !== '' && value !== null && value !== undefined) {
                    url.searchParams.set(key, String(value));
                }
            });
        } else {
            const formData = data instanceof FormData
                ? data
                : new FormData();

            if (!(data instanceof FormData)) {
                Object.entries(data).forEach(([key, value]) => {
                    if (Array.isArray(value)) {
                        value.forEach(item => {
                            formData.append(key + '[]', String(item));
                        });
                    } else {
                        formData.set(key, String(value ?? ''));
                    }
                });
            }

            formData.set('action', action);
            formData.set('csrf_token', csrfToken);
            options.body = formData;
        }

        const response = await fetch(url, options);
        const result = await parseJson(response);

        if (!response.ok || !result.success) {
            throw new Error(
                result.message || 'Unable to complete the request.'
            );
        }

        return result;
    }

    function slug(value) {
        return String(value || '')
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '')
            .slice(0, 80);
    }

    function formatDate(value) {
        if (!value) {
            return '-';
        }

        const date = new Date(String(value).replace(' ', 'T'));

        if (Number.isNaN(date.getTime())) {
            return String(value);
        }

        return new Intl.DateTimeFormat(
            'en-IN',
            {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            }
        ).format(date);
    }

    function schoolName(id) {
        return schools.find(item => Number(item.id) === Number(id))
            ?.school_name || '-';
    }

    function fillSchoolSelect(select, includeAll = false) {
        const current = select.value;

        select.innerHTML = includeAll
            ? '<option value="">All Schools</option>'
            : '<option value="">Select School</option>';

        schools.forEach(school => {
            const option = document.createElement('option');
            option.value = String(school.id);
            option.textContent = school.school_name
                + ' (' + school.tenant_code + ')';
            select.appendChild(option);
        });

        if ([...select.options].some(
            option => option.value === current
        )) {
            select.value = current;
        }
    }

    function fillBranchSelect(select, schoolId, schoolLevel = true) {
        const current = select.value;

        select.innerHTML = schoolLevel
            ? '<option value="">School-level permissions</option>'
            : '<option value="">Select Branch</option>';

        branches
            .filter(branch => Number(branch.tenant_id) === Number(schoolId))
            .forEach(branch => {
                const option = document.createElement('option');
                option.value = String(branch.id);
                option.textContent = branch.branch_name
                    + (Number(branch.is_main) === 1 ? ' · Head Branch' : '');
                select.appendChild(option);
            });

        if ([...select.options].some(
            option => option.value === current
        )) {
            select.value = current;
        }
    }

    function updateStats(stats) {
        byId('schoolCount').textContent = Number(stats.schools || 0);
        byId('branchCount').textContent = Number(stats.branches || 0);
        byId('roleCount').textContent = Number(stats.roles || 0);
        byId('permissionCount').textContent =
            Number(stats.permissions || 0);
    }

    async function loadMeta() {
        const schoolId = Number(byId('schoolFilter').value || 0);
        const branchId = Number(byId('branchFilter').value || 0);

        const result = await request(
            'meta',
            {
                data: {
                    school_id: schoolId || '',
                    branch_id: branchId || ''
                }
            }
        );

        schools = Array.isArray(result.data.schools)
            ? result.data.schools
            : [];

        branches = Array.isArray(result.data.branches)
            ? result.data.branches
            : [];

        roles = Array.isArray(result.data.roles)
            ? result.data.roles
            : [];

        users = Array.isArray(result.data.users)
            ? result.data.users
            : [];

        actions = Array.isArray(result.data.actions)
            ? result.data.actions
            : [];

        fillSchoolSelect(byId('schoolFilter'), true);
        fillSchoolSelect(byId('roleSchool'));
        fillSchoolSelect(byId('copySchool'));
        fillSchoolSelect(byId('importSchool'));

        const selectedSchoolId = Number(
            byId('schoolFilter').value || 0
        );

        byId('branchFilter').disabled = selectedSchoolId <= 0;
        fillBranchSelect(
            byId('branchFilter'),
            selectedSchoolId,
            true
        );

        updateStats(result.data.stats || {});
        renderRoles();
        renderUsers();
        renderImportRoles();

        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    function renderRoles() {
        const query = byId('roleSearch').value.trim().toLowerCase();
        const status = byId('roleStatusFilter').value;
        const selectedSchool = Number(
            byId('schoolFilter').value || 0
        );

        const visible = roles.filter(role => {
            const searchable = [
                role.school_name,
                role.role_name,
                role.role_key,
                role.description,
                role.permission_mode,
                role.sidebar_mode
            ].join(' ').toLowerCase();

            return (
                query === '' || searchable.includes(query)
            ) && (
                status === '' || role.status === status
            ) && (
                selectedSchool === 0
                || role.role_scope === 'platform'
                || Number(role.tenant_id) === selectedSchool
            );
        });

        if (visible.length === 0) {
            byId('rolesBody').innerHTML = `
                <tr>
                    <td colspan="10" class="rbac-empty">
                        No matching roles found.
                    </td>
                </tr>
            `;
            return;
        }

        byId('rolesBody').innerHTML = visible.map(role => {
            const roleId = Number(role.id);
            const platform = role.role_scope === 'platform';
            const system = Number(role.is_system) === 1;
            const active = role.status === 'active';
            const checked = selectedRoleIds.has(roleId);

            return `
                <tr>
                    <td>
                        ${platform ? '' : `
                            <input
                                class="form-check-input rbac-role-select"
                                type="checkbox"
                                value="${roleId}"
                                ${checked ? 'checked' : ''}
                            >
                        `}
                    </td>

                    <td>
                        ${platform
                            ? '<span class="rbac-mode">SaaS Platform</span>'
                            : escapeHtml(role.school_name || '-')
                        }
                    </td>

                    <td>
                        <span class="rbac-role-copy">
                            <strong>${escapeHtml(role.role_name)}</strong>
                            <small>${escapeHtml(role.description || '')}</small>
                        </span>
                    </td>

                    <td><code>${escapeHtml(role.role_key)}</code></td>
                    <td>${Number(role.user_count || 0)}</td>
                    <td>${Number(role.permission_count || 0)}</td>

                    <td>
                        ${platform
                            ? '-'
                            : `<span class="rbac-mode">${escapeHtml(role.permission_mode || 'inherit')}</span>`
                        }
                    </td>

                    <td>
                        ${platform
                            ? '-'
                            : `<span class="rbac-mode">${escapeHtml(role.sidebar_mode || 'inherit')}</span>`
                        }
                    </td>

                    <td>
                        <span class="rbac-status ${active ? 'active' : 'inactive'}">
                            ${escapeHtml(role.status)}
                        </span>
                    </td>

                    <td>
                        <div class="rbac-actions">
                            ${!platform && capabilities.manage ? `
                                <button
                                    class="rbac-action-btn role-permission"
                                    type="button"
                                    title="View / Manage Permissions"
                                    data-role-id="${roleId}"
                                >
                                    <i data-lucide="key-round"></i>
                                </button>
                            ` : ''}

                            ${!platform && capabilities.export ? `
                                <button
                                    class="rbac-action-btn role-export"
                                    type="button"
                                    title="Export Role"
                                    data-role-id="${roleId}"
                                    data-school-id="${Number(role.tenant_id)}"
                                >
                                    <i data-lucide="download"></i>
                                </button>
                            ` : ''}

                            ${!platform && capabilities.create ? `
                                <button
                                    class="rbac-action-btn role-copy"
                                    type="button"
                                    title="Copy Role"
                                    data-role="${escapeHtml(JSON.stringify(role))}"
                                >
                                    <i data-lucide="copy"></i>
                                </button>
                            ` : ''}

                            ${!platform && capabilities.edit ? `
                                <button
                                    class="rbac-action-btn role-edit"
                                    type="button"
                                    title="Edit Role"
                                    data-role="${escapeHtml(JSON.stringify(role))}"
                                >
                                    <i data-lucide="square-pen"></i>
                                </button>
                            ` : ''}

                            ${!platform && !system && capabilities.edit ? `
                                <button
                                    class="rbac-action-btn role-toggle"
                                    type="button"
                                    title="${active ? 'Deactivate' : 'Activate'}"
                                    data-role-id="${roleId}"
                                    data-school-id="${Number(role.tenant_id)}"
                                >
                                    <i data-lucide="${active ? 'power-off' : 'power'}"></i>
                                </button>
                            ` : ''}

                            ${!platform
                                && !system
                                && capabilities.delete
                                && Number(role.user_count || 0) === 0 ? `
                                <button
                                    class="rbac-action-btn danger role-delete"
                                    type="button"
                                    title="Delete Role"
                                    data-role-id="${roleId}"
                                    data-school-id="${Number(role.tenant_id)}"
                                >
                                    <i data-lucide="trash-2"></i>
                                </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        refreshRoleSelectionState();

        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    function refreshRoleSelectionState() {
        const checks = [
            ...document.querySelectorAll('.rbac-role-select')
        ];
        const selected = checks.filter(check => check.checked).length;
        const all = byId('selectAllRoles');

        all.checked = checks.length > 0 && selected === checks.length;
        all.indeterminate = selected > 0 && selected < checks.length;
    }

    function openRole(role = null) {
        byId('roleForm').reset();
        byId('roleId').value = role ? String(role.id) : '';
        byId('roleModalTitle').textContent = role
            ? 'Edit School Role'
            : 'Add School Role';

        fillSchoolSelect(byId('roleSchool'));

        const selectedSchool = role
            ? Number(role.tenant_id)
            : Number(byId('schoolFilter').value || 0);

        if (selectedSchool > 0) {
            byId('roleSchool').value = String(selectedSchool);
        }

        byId('roleSchool').disabled = Boolean(role);
        byId('roleName').value = role?.role_name || '';
        byId('roleKey').value = role?.role_key || '';
        byId('roleDescription').value = role?.description || '';
        byId('roleStatus').value = role?.status || 'active';

        roleModal.show();
    }

    function openCopy(role) {
        byId('copyForm').reset();
        fillSchoolSelect(byId('copySchool'));
        byId('copySourceRoleId').value = String(role.id);
        byId('copySourceText').textContent =
            `Copy ${role.role_name} from ${role.school_name}`;
        byId('copySchool').value = String(role.tenant_id);
        byId('copyName').value = role.role_name + ' Copy';
        byId('copyKey').value = slug(role.role_key + '_copy');
        copyModal.show();
    }

    async function openPermissionMatrix(
        roleIds,
        schoolId,
        branchId = 0
    ) {
        if (!schoolId || roleIds.length === 0) {
            throw new Error('Select a school and at least one school role.');
        }

        matrixRoleIds = [...new Set(roleIds.map(Number))];

        const firstRoleId = matrixRoleIds[0];
        const result = await request(
            'permission_matrix',
            {
                data: {
                    school_id: schoolId,
                    branch_id: branchId || '',
                    role_id: firstRoleId
                }
            }
        );

        currentMatrixRole = result.data.role || null;
        actions = result.data.actions || [];
        permissionModules = result.data.modules || [];
        sidebarItems = result.data.sidebar || [];

        byId('permissionRoleId').value = String(firstRoleId);
        fillSchoolSelect(byId('permissionSchool'));
        byId('permissionSchool').value = String(schoolId);

        branches = branches.filter(
            branch => Number(branch.tenant_id) === Number(schoolId)
        );

        if (branches.length === 0) {
            const meta = await request('meta', {
                data: {school_id: schoolId}
            });
            branches = meta.data.branches || [];
        }

        fillBranchSelect(
            byId('permissionBranch'),
            schoolId,
            true
        );
        byId('permissionBranch').value = branchId
            ? String(branchId)
            : '';

        byId('permissionMode').value =
            branchId > 0
                ? result.data.setting.permission_mode || 'inherit'
                : 'custom';

        byId('sidebarMode').value =
            branchId > 0
                ? result.data.setting.sidebar_mode || 'inherit'
                : 'custom';

        byId('permissionMode').disabled = branchId === 0;
        byId('sidebarMode').disabled = branchId === 0;

        const roleNames = matrixRoleIds.map(id => {
            return roles.find(role => Number(role.id) === id)?.role_name
                || `Role #${id}`;
        });

        byId('permissionRoleText').textContent =
            matrixRoleIds.length > 1
                ? `Bulk permission assignment for ${matrixRoleIds.length} roles`
                : `Configure ${currentMatrixRole?.role_name || 'role'} for school or branch`;

        byId('bulkRoleBox').classList.toggle(
            'show',
            matrixRoleIds.length > 1
        );

        byId('bulkRoleNames').innerHTML = roleNames.map(name => `
            <span class="rbac-bulk-role">
                <i data-lucide="shield"></i>${escapeHtml(name)}
            </span>
        `).join('');

        renderPermissionMatrix();
        renderSidebarMatrix();
        applyInheritanceState();

        permissionModal.show();

        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    function permissionChecked(permission) {
        return Number(permission?.effective_allowed || 0) === 1;
    }

    function renderPermissionMatrix() {
        byId('permissionHead').innerHTML = `
            <tr>
                <th>Module / Page</th>
                ${actions.map(action => `
                    <th class="text-center">
                        ${escapeHtml(action.action_name)}
                    </th>
                `).join('')}
            </tr>
        `;

        byId('permissionColumnSelectors').innerHTML =
            actions.map(action => `
                <label class="small fw-semibold">
                    <input
                        class="form-check-input rbac-column-check me-1"
                        type="checkbox"
                        data-action="${escapeHtml(action.action_key)}"
                    >
                    All ${escapeHtml(action.action_name)}
                </label>
            `).join('');

        byId('permissionBody').innerHTML =
            permissionModules.map(module => {
                const moduleId = Number(module.id);

                return `
                    <tr class="module-row">
                        <td colspan="${actions.length + 1}">
                            <label>
                                <input
                                    class="form-check-input rbac-module-check me-2"
                                    type="checkbox"
                                    data-module-id="${moduleId}"
                                >
                                ${escapeHtml(module.module_name)}
                            </label>
                        </td>
                    </tr>

                    ${(module.pages || []).map(page => `
                        <tr>
                            <td class="rbac-page-cell">
                                <label>
                                    <input
                                        class="form-check-input rbac-page-check me-2"
                                        type="checkbox"
                                        data-page-id="${Number(page.id)}"
                                        data-module-id="${moduleId}"
                                    >
                                    <strong>${escapeHtml(page.page_name)}</strong>
                                </label>
                                <small>
                                    ${escapeHtml(page.page_key)}
                                    · ${escapeHtml(page.route)}
                                </small>
                            </td>

                            ${actions.map(action => {
                                const permission =
                                    page.permissions?.[action.action_key]
                                    || {};

                                return `
                                    <td class="text-center">
                                        <input
                                            class="form-check-input rbac-permission-check"
                                            type="checkbox"
                                            data-permission-id="${Number(permission.permission_id || 0)}"
                                            data-action="${escapeHtml(action.action_key)}"
                                            data-page-id="${Number(page.id)}"
                                            data-module-id="${moduleId}"
                                            ${permissionChecked(permission) ? 'checked' : ''}
                                        >
                                    </td>
                                `;
                            }).join('')}
                        </tr>
                    `).join('')}
                `;
            }).join('');

        bindMatrixEvents();
        refreshPermissionAggregates();
    }

    function renderSidebarMatrix() {
        const parentIds = new Set(
            sidebarItems
                .filter(item => Number(item.parent_id || 0) > 0)
                .map(item => Number(item.parent_id))
        );

        byId('sidebarPermissionList').innerHTML =
            sidebarItems.map(item => {
                const child = Number(item.parent_id || 0) > 0;
                const isParent = parentIds.has(Number(item.id));

                return `
                    <label class="rbac-sidebar-item ${child ? 'child' : ''}">
                        <input
                            class="form-check-input rbac-sidebar-check"
                            type="checkbox"
                            value="${Number(item.id)}"
                            data-parent-id="${Number(item.parent_id || 0)}"
                            data-is-parent="${isParent ? '1' : '0'}"
                            ${Number(item.effective_show || 0) === 1 ? 'checked' : ''}
                        >
                        <span class="rbac-sidebar-icon">
                            <i data-lucide="${escapeHtml(item.icon || 'circle')}"></i>
                        </span>
                        <span class="rbac-sidebar-copy">
                            <strong>${escapeHtml(item.menu_title)}</strong>
                            <small>
                                ${escapeHtml(item.menu_key)}
                                · ${escapeHtml(item.route)}
                            </small>
                        </span>
                    </label>
                `;
            }).join('') || `
                <div class="rbac-empty">No school sidebar menus found.</div>
            `;

        document.querySelectorAll('.rbac-sidebar-check')
            .forEach(check => {
                check.addEventListener('change', () => {
                    if (check.checked) {
                        const parentId = Number(check.dataset.parentId || 0);
                        const parent = document.querySelector(
                            `.rbac-sidebar-check[value="${parentId}"]`
                        );
                        if (parent) {
                            parent.checked = true;
                        }
                    }

                    if (
                        check.dataset.isParent === '1'
                        && !check.checked
                    ) {
                        document.querySelectorAll(
                            `.rbac-sidebar-check[data-parent-id="${check.value}"]`
                        ).forEach(child => {
                            child.checked = false;
                        });
                    }

                    refreshSidebarAggregate();
                });
            });

        refreshSidebarAggregate();

        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    function aggregate(input, values) {
        const checked = values.filter(Boolean).length;
        input.checked = values.length > 0 && checked === values.length;
        input.indeterminate = checked > 0 && checked < values.length;
    }

    function refreshPermissionAggregates() {
        document.querySelectorAll('.rbac-page-check')
            .forEach(input => {
                aggregate(
                    input,
                    [...document.querySelectorAll(
                        `.rbac-permission-check[data-page-id="${input.dataset.pageId}"]`
                    )].map(check => check.checked)
                );
            });

        document.querySelectorAll('.rbac-module-check')
            .forEach(input => {
                aggregate(
                    input,
                    [...document.querySelectorAll(
                        `.rbac-permission-check[data-module-id="${input.dataset.moduleId}"]`
                    )].map(check => check.checked)
                );
            });

        document.querySelectorAll('.rbac-column-check')
            .forEach(input => {
                aggregate(
                    input,
                    [...document.querySelectorAll(
                        `.rbac-permission-check[data-action="${input.dataset.action}"]`
                    )].map(check => check.checked)
                );
            });

        aggregate(
            byId('selectAllPermissions'),
            [...document.querySelectorAll('.rbac-permission-check')]
                .map(check => check.checked)
        );
    }

    function refreshSidebarAggregate() {
        aggregate(
            byId('selectAllSidebar'),
            [...document.querySelectorAll('.rbac-sidebar-check')]
                .map(check => check.checked)
        );
    }

    function bindMatrixEvents() {
        document.querySelectorAll('.rbac-permission-check')
            .forEach(input => {
                input.addEventListener('change', () => {
                    const pageId = input.dataset.pageId;
                    const action = input.dataset.action;
                    const pageChecks = [
                        ...document.querySelectorAll(
                            `.rbac-permission-check[data-page-id="${pageId}"]`
                        )
                    ];
                    const fullAccess = pageChecks.find(
                        check => check.dataset.action === 'full_access'
                    );
                    const view = pageChecks.find(
                        check => check.dataset.action === 'view'
                    );

                    if (action === 'full_access') {
                        pageChecks.forEach(check => {
                            check.checked = input.checked;
                        });
                    } else {
                        if (input.checked && view) {
                            view.checked = true;
                        }

                        if (!input.checked && fullAccess) {
                            fullAccess.checked = false;
                        }

                        if (action === 'view' && !input.checked) {
                            pageChecks.forEach(check => {
                                check.checked = false;
                            });
                        }
                    }

                    refreshPermissionAggregates();
                });
            });

        document.querySelectorAll('.rbac-page-check')
            .forEach(input => {
                input.addEventListener('change', () => {
                    document.querySelectorAll(
                        `.rbac-permission-check[data-page-id="${input.dataset.pageId}"]`
                    ).forEach(check => {
                        check.checked = input.checked;
                    });
                    refreshPermissionAggregates();
                });
            });

        document.querySelectorAll('.rbac-module-check')
            .forEach(input => {
                input.addEventListener('change', () => {
                    document.querySelectorAll(
                        `.rbac-permission-check[data-module-id="${input.dataset.moduleId}"]`
                    ).forEach(check => {
                        check.checked = input.checked;
                    });
                    refreshPermissionAggregates();
                });
            });

        document.querySelectorAll('.rbac-column-check')
            .forEach(input => {
                input.addEventListener('change', () => {
                    document.querySelectorAll(
                        `.rbac-permission-check[data-action="${input.dataset.action}"]`
                    ).forEach(check => {
                        check.checked = input.checked;

                        if (
                            input.dataset.action !== 'view'
                            && input.checked
                        ) {
                            const view = document.querySelector(
                                `.rbac-permission-check[data-page-id="${check.dataset.pageId}"][data-action="view"]`
                            );
                            if (view) {
                                view.checked = true;
                            }
                        }
                    });

                    refreshPermissionAggregates();
                });
            });
    }

    function applyInheritanceState() {
        const branchSelected = Number(
            byId('permissionBranch').value || 0
        ) > 0;

        const permissionInherited =
            branchSelected
            && byId('permissionMode').value === 'inherit';

        const sidebarInherited =
            branchSelected
            && byId('sidebarMode').value === 'inherit';

        document.querySelectorAll(
            '.rbac-permission-check,.rbac-page-check,.rbac-module-check,.rbac-column-check'
        ).forEach(input => {
            input.disabled = permissionInherited || !capabilities.manage;
        });

        byId('selectAllPermissions').disabled =
            permissionInherited || !capabilities.manage;

        document.querySelectorAll('.rbac-sidebar-check')
            .forEach(input => {
                input.disabled = sidebarInherited || !capabilities.manage;
            });

        byId('selectAllSidebar').disabled =
            sidebarInherited || !capabilities.manage;

        byId('inheritNote').classList.toggle(
            'show',
            permissionInherited || sidebarInherited
        );
    }

    async function reloadMatrixForBranch() {
        if (!currentMatrixRole || matrixRoleIds.length === 0) {
            return;
        }

        await openPermissionMatrix(
            matrixRoleIds,
            Number(byId('permissionSchool').value),
            Number(byId('permissionBranch').value || 0)
        );
    }

    function renderUsers() {
        const schoolId = Number(byId('schoolFilter').value || 0);
        const schoolRoles = roles.filter(role =>
            role.role_scope === 'school'
            && Number(role.tenant_id) === schoolId
            && role.status === 'active'
        );

        if (schoolId <= 0) {
            byId('userRolesGrid').innerHTML = `
                <div class="rbac-empty">Select a school first.</div>
            `;
            return;
        }

        if (users.length === 0) {
            byId('userRolesGrid').innerHTML = `
                <div class="rbac-empty">
                    No users found for the selected school.
                </div>
            `;
            return;
        }

        byId('userRolesGrid').innerHTML = users.map(user => {
            const assigned = new Set(
                (user.role_ids || []).map(Number)
            );

            return `
                <article class="rbac-user-role-row">
                    <span class="rbac-user-copy">
                        <strong>${escapeHtml(user.name)}</strong>
                        <small>
                            ${escapeHtml(user.username)}
                            · ${escapeHtml(user.email || '-')}
                        </small>
                    </span>

                    <div>
                        <div class="rbac-role-checkboxes">
                            ${schoolRoles.map(role => `
                                <label class="rbac-role-option">
                                    <input
                                        class="form-check-input user-role-check"
                                        type="checkbox"
                                        data-user-id="${Number(user.id)}"
                                        value="${Number(role.id)}"
                                        ${assigned.has(Number(role.id)) ? 'checked' : ''}
                                    >
                                    ${escapeHtml(role.role_name)}
                                </label>
                            `).join('')}
                        </div>

                        ${capabilities.assign ? `
                            <button
                                class="btn-ui mt-2 save-user-roles"
                                type="button"
                                data-user-id="${Number(user.id)}"
                            >
                                <i data-lucide="save"></i>Save Roles
                            </button>
                        ` : ''}
                    </div>
                </article>
            `;
        }).join('');

        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    function renderImportRoles() {
        const schoolId = Number(byId('importSchool').value || 0);

        byId('importRole').innerHTML =
            '<option value="">Create a new role</option>';

        roles
            .filter(role =>
                role.role_scope === 'school'
                && Number(role.tenant_id) === schoolId
            )
            .forEach(role => {
                const option = document.createElement('option');
                option.value = String(role.id);
                option.textContent = role.role_name;
                byId('importRole').appendChild(option);
            });
    }

    async function exportRole(roleId, schoolId) {
        const result = await request(
            'export_role',
            {
                data: {
                    role_id: roleId,
                    school_id: schoolId
                }
            }
        );

        const content = JSON.stringify(
            result.data.export,
            null,
            2
        );

        const blob = new Blob(
            [content],
            {type: 'application/json;charset=utf-8'}
        );

        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = result.data.file_name
            || 'role-permissions.json';
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(link.href);

        message('Role permissions exported successfully.', true);
    }

    async function loadAudit() {
        const result = await request(
            'audit',
            {
                data: {
                    school_id: byId('schoolFilter').value,
                    branch_id: byId('branchFilter').value
                }
            }
        );

        const rows = result.data.rows || [];

        byId('auditBody').innerHTML = rows.length
            ? rows.map(row => `
                <tr>
                    <td>${escapeHtml(formatDate(row.created_at))}</td>
                    <td>${escapeHtml(row.school_name || '-')}</td>
                    <td>${escapeHtml(row.branch_name || 'School Level')}</td>
                    <td>${escapeHtml(row.changed_by || 'System')}</td>
                    <td><code>${escapeHtml(row.action_key)}</code></td>
                    <td>${escapeHtml(row.description || '-')}</td>
                    <td>${escapeHtml(row.ip_address || '-')}</td>
                </tr>
            `).join('')
            : `
                <tr>
                    <td colspan="7" class="rbac-empty">
                        No role permission changes found.
                    </td>
                </tr>
            `;

        auditModal.show();
    }

    byId('schoolFilter').addEventListener('change', async () => {
        selectedRoleIds.clear();
        byId('branchFilter').value = '';

        try {
            await loadMeta();
        } catch (error) {
            message(error.message, false);
        }
    });

    byId('branchFilter').addEventListener('change', async () => {
        try {
            await loadMeta();
        } catch (error) {
            message(error.message, false);
        }
    });

    byId('roleSearch').addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(renderRoles, 200);
    });

    byId('roleStatusFilter').addEventListener('change', renderRoles);

    byId('refreshButton').addEventListener('click', () => {
        loadMeta().catch(error => message(error.message, false));
    });

    byId('clearSelectionButton').addEventListener('click', () => {
        selectedRoleIds.clear();
        renderRoles();
    });

    byId('selectAllRoles').addEventListener('change', event => {
        document.querySelectorAll('.rbac-role-select')
            .forEach(check => {
                check.checked = event.target.checked;
                const id = Number(check.value);

                if (check.checked) {
                    selectedRoleIds.add(id);
                } else {
                    selectedRoleIds.delete(id);
                }
            });

        refreshRoleSelectionState();
    });

    byId('rolesBody').addEventListener('change', event => {
        const check = event.target.closest('.rbac-role-select');

        if (!check) {
            return;
        }

        const id = Number(check.value);

        if (check.checked) {
            selectedRoleIds.add(id);
        } else {
            selectedRoleIds.delete(id);
        }

        refreshRoleSelectionState();
    });

    byId('rolesBody').addEventListener('click', async event => {
        const permission = event.target.closest('.role-permission');
        const edit = event.target.closest('.role-edit');
        const copy = event.target.closest('.role-copy');
        const toggle = event.target.closest('.role-toggle');
        const remove = event.target.closest('.role-delete');
        const exportButton = event.target.closest('.role-export');

        try {
            if (permission) {
                const roleId = Number(permission.dataset.roleId);
                const role = roles.find(item => Number(item.id) === roleId);

                await openPermissionMatrix(
                    [roleId],
                    Number(role.tenant_id),
                    Number(byId('branchFilter').value || 0)
                );
                return;
            }

            if (edit) {
                openRole(JSON.parse(edit.dataset.role));
                return;
            }

            if (copy) {
                openCopy(JSON.parse(copy.dataset.role));
                return;
            }

            if (exportButton) {
                await exportRole(
                    Number(exportButton.dataset.roleId),
                    Number(exportButton.dataset.schoolId)
                );
                return;
            }

            if (
                toggle
                && window.confirm('Change this role status?')
            ) {
                const result = await request(
                    'toggle_role',
                    {
                        method: 'POST',
                        data: {
                            role_id: Number(toggle.dataset.roleId),
                            school_id: Number(toggle.dataset.schoolId)
                        }
                    }
                );
                message(result.message, true);
                await loadMeta();
                return;
            }

            if (
                remove
                && window.confirm(
                    'Delete this role and its branch overrides?'
                )
            ) {
                const result = await request(
                    'delete_role',
                    {
                        method: 'POST',
                        data: {
                            role_id: Number(remove.dataset.roleId),
                            school_id: Number(remove.dataset.schoolId)
                        }
                    }
                );
                message(result.message, true);
                selectedRoleIds.delete(
                    Number(remove.dataset.roleId)
                );
                await loadMeta();
            }
        } catch (error) {
            message(error.message, false);
        }
    });

    byId('addRoleButton')?.addEventListener('click', () => {
        openRole();
    });

    byId('roleName').addEventListener('input', event => {
        if (!byId('roleId').value) {
            byId('roleKey').value = slug(event.target.value);
        }
    });

    byId('roleForm').addEventListener('submit', async event => {
        event.preventDefault();

        const button = byId('saveRoleButton');
        button.disabled = true;

        const formData = new FormData(event.target);
        formData.set('school_id', byId('roleSchool').value);

        try {
            const result = await request(
                'save_role',
                {
                    method: 'POST',
                    data: formData
                }
            );
            roleModal.hide();
            message(result.message, true);
            await loadMeta();
        } catch (error) {
            message(error.message, false);
        } finally {
            button.disabled = false;
        }
    });

    byId('copyName').addEventListener('input', event => {
        byId('copyKey').value = slug(event.target.value);
    });

    byId('copyForm').addEventListener('submit', async event => {
        event.preventDefault();

        const button = byId('copyRoleButton');
        button.disabled = true;

        try {
            const result = await request(
                'copy_role',
                {
                    method: 'POST',
                    data: {
                        source_role_id: Number(
                            byId('copySourceRoleId').value
                        ),
                        school_id: Number(byId('copySchool').value),
                        role_name: byId('copyName').value,
                        role_key: byId('copyKey').value,
                        copy_branch_overrides:
                            byId('copyBranchOverrides').checked ? 1 : 0
                    }
                }
            );
            copyModal.hide();
            message(result.message, true);
            await loadMeta();
        } catch (error) {
            message(error.message, false);
        } finally {
            button.disabled = false;
        }
    });

    byId('bulkPermissionButton')?.addEventListener('click', async () => {
        const schoolId = Number(byId('schoolFilter').value || 0);
        const selected = [...selectedRoleIds];

        if (schoolId <= 0) {
            message('Select a school before bulk assignment.', false);
            return;
        }

        if (selected.length === 0) {
            message('Select at least one school role.', false);
            return;
        }

        const invalid = selected.some(id => {
            const role = roles.find(item => Number(item.id) === id);
            return !role || Number(role.tenant_id) !== schoolId;
        });

        if (invalid) {
            message(
                'Bulk roles must belong to the selected school.',
                false
            );
            return;
        }

        try {
            await openPermissionMatrix(
                selected,
                schoolId,
                Number(byId('branchFilter').value || 0)
            );
        } catch (error) {
            message(error.message, false);
        }
    });

    byId('permissionBranch').addEventListener('change', () => {
        reloadMatrixForBranch().catch(
            error => message(error.message, false)
        );
    });

    byId('permissionMode').addEventListener(
        'change',
        applyInheritanceState
    );

    byId('sidebarMode').addEventListener(
        'change',
        applyInheritanceState
    );

    byId('selectAllPermissions').addEventListener(
        'change',
        event => {
            document.querySelectorAll('.rbac-permission-check')
                .forEach(check => {
                    check.checked = event.target.checked;
                });
            refreshPermissionAggregates();
        }
    );

    byId('selectAllSidebar').addEventListener(
        'change',
        event => {
            document.querySelectorAll('.rbac-sidebar-check')
                .forEach(check => {
                    check.checked = event.target.checked;
                });
            refreshSidebarAggregate();
        }
    );

    byId('permissionForm').addEventListener(
        'submit',
        async event => {
            event.preventDefault();

            const button = byId('savePermissionsButton');

            if (!button) {
                return;
            }

            button.disabled = true;

            const permissionIds = [
                ...document.querySelectorAll(
                    '.rbac-permission-check:checked'
                )
            ].map(check => Number(check.dataset.permissionId))
                .filter(Boolean);

            const sidebarIds = [
                ...document.querySelectorAll(
                    '.rbac-sidebar-check:checked'
                )
            ].map(check => Number(check.value));

            try {
                const result = await request(
                    'save_permissions',
                    {
                        method: 'POST',
                        data: {
                            school_id: Number(
                                byId('permissionSchool').value
                            ),
                            branch_id: Number(
                                byId('permissionBranch').value || 0
                            ),
                            role_ids: matrixRoleIds,
                            permission_mode:
                                byId('permissionMode').value,
                            sidebar_mode:
                                byId('sidebarMode').value,
                            permission_ids: permissionIds,
                            sidebar_ids: sidebarIds
                        }
                    }
                );

                permissionModal.hide();
                message(result.message, true);
                await loadMeta();
            } catch (error) {
                message(error.message, false);
            } finally {
                button.disabled = false;
            }
        }
    );

    byId('importButton')?.addEventListener('click', () => {
        byId('importForm').reset();
        fillSchoolSelect(byId('importSchool'));

        const selectedSchool = Number(
            byId('schoolFilter').value || 0
        );

        if (selectedSchool > 0) {
            byId('importSchool').value = String(selectedSchool);
        }

        renderImportRoles();
        importModal.show();
    });

    byId('importSchool').addEventListener(
        'change',
        renderImportRoles
    );

    byId('importForm').addEventListener('submit', async event => {
        event.preventDefault();

        const button = byId('importSaveButton');
        button.disabled = true;

        const formData = new FormData(event.target);

        try {
            const result = await request(
                'import_role',
                {
                    method: 'POST',
                    data: formData
                }
            );
            importModal.hide();
            message(result.message, true);
            await loadMeta();
        } catch (error) {
            message(error.message, false);
        } finally {
            button.disabled = false;
        }
    });

    byId('assignUsersButton')?.addEventListener('click', () => {
        if (!byId('schoolFilter').value) {
            message('Select a school before assigning users.', false);
            return;
        }

        renderUsers();
        userRolesModal.show();
    });

    byId('userRolesGrid').addEventListener('click', async event => {
        const button = event.target.closest('.save-user-roles');

        if (!button) {
            return;
        }

        const userId = Number(button.dataset.userId);
        const roleIds = [
            ...document.querySelectorAll(
                `.user-role-check[data-user-id="${userId}"]:checked`
            )
        ].map(check => Number(check.value));

        if (roleIds.length === 0) {
            message('Assign at least one role to this user.', false);
            return;
        }

        button.disabled = true;

        try {
            const result = await request(
                'save_user_roles',
                {
                    method: 'POST',
                    data: {
                        school_id: Number(
                            byId('schoolFilter').value
                        ),
                        user_id: userId,
                        role_ids: roleIds
                    }
                }
            );
            message(result.message, true);
            await loadMeta();
            userRolesModal.show();
        } catch (error) {
            message(error.message, false);
        } finally {
            button.disabled = false;
        }
    });

    byId('auditButton')?.addEventListener('click', () => {
        loadAudit().catch(error => message(error.message, false));
    });

    loadMeta().catch(error => message(error.message, false));
})();
</script>

<?php require $projectRoot . '/includes/layout-end.php'; ?>

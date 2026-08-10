<?php
declare(strict_types=1);

$pageTitle = 'School Sidebar Permissions';
$pageKey = 'school_sidebar_permissions';
$sidebarFile = __DIR__ . '/sidebar.php';

require dirname(__DIR__) . '/includes/layout-start.php';

$currentUser = function_exists('current_user') ? current_user() : [];
$currentRoleId = (int)($currentUser['role_id'] ?? $_SESSION['role_id'] ?? 0);
$roleKey = strtolower(trim((string)($_SESSION['role_key'] ?? '')));

if ($roleKey === '' && isset($pdo) && $pdo instanceof PDO && $currentRoleId > 0) {
    try {
        $statement = $pdo->prepare(
            "SELECT role_key
             FROM roles
             WHERE id = :role_id
               AND status = 'active'
             LIMIT 1"
        );
        $statement->execute(['role_id' => $currentRoleId]);
        $roleKey = strtolower(trim((string)$statement->fetchColumn()));
    } catch (Throwable $exception) {
        error_log('school sidebar permission access: ' . $exception->getMessage());
    }
}

$isSuperAdmin = $currentRoleId === 1 || in_array(
    $roleKey,
    ['super_admin', 'super-administrator', 'super_administrator'],
    true
);

if (!$isSuperAdmin) {
    http_response_code(403);
    ?>
    <div class="ui-card">
        <div class="ui-card-body sidebar-options-denied">
            <span class="sidebar-options-denied-icon">
                <i data-lucide="shield-alert"></i>
            </span>
            <h1 class="page-title">Access denied</h1>
            <p class="page-subtitle">Only a Super Administrator can manage school sidebar permissions.</p>
        </div>
    </div>
    <?php
    require dirname(__DIR__) . '/includes/layout-end.php';
    exit;
}

$baseUrl = defined('BASE_URL') ? rtrim((string)BASE_URL, '/') . '/' : '../';
$csrfToken = function_exists('csrfToken') ? csrfToken() : '';
?>
<style>

.sidebar-options-page {
    display: grid;
    gap: 16px;
}

.sidebar-options-message {
    display: none;
    margin: 0;
    border: 0;
    border-radius: 12px;
}

.sidebar-options-message.show {
    display: block;
}

.sidebar-options-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
}

.sidebar-stat-card {
    min-height: 94px;
    padding: 16px;
    display: flex;
    align-items: center;
    gap: 13px;
    background: var(--card-bg, #fff);
    border: 1px solid var(--border-soft, #e7ebf3);
    border-radius: 13px;
    box-shadow: 0 5px 18px rgba(15, 23, 42, .04);
}

.sidebar-stat-icon {
    width: 44px;
    height: 44px;
    flex: 0 0 44px;
    display: grid;
    place-items: center;
    border-radius: 12px;
    color: var(--brand-1, #6547e8);
    background: rgba(101, 71, 232, .1);
}

.sidebar-stat-icon svg {
    width: 21px;
    height: 21px;
}

.sidebar-stat-card strong,
.sidebar-stat-card small {
    display: block;
}

.sidebar-stat-card strong {
    font-size: 22px;
    font-weight: 800;
    line-height: 1.1;
}

.sidebar-stat-card small {
    margin-top: 5px;
    color: var(--text-muted, #64748b);
    font-size: 11px;
    font-weight: 600;
}

.sidebar-options-control-card {
    overflow: visible;
}

.sidebar-options-control-body {
    padding: 16px;
    display: grid;
    grid-template-columns: minmax(220px, .8fr) minmax(260px, 1.25fr) minmax(190px, .75fr);
    gap: 14px;
    align-items: end;
}

.sidebar-options-field label {
    margin-bottom: 6px;
    display: block;
    color: var(--text-main, #101a3b);
    font-size: 11px;
    font-weight: 750;
}

.sidebar-options-search {
    position: relative;
}

.sidebar-options-search svg {
    position: absolute;
    top: 50%;
    left: 13px;
    width: 17px;
    height: 17px;
    color: var(--text-muted, #64748b);
    transform: translateY(-50%);
    pointer-events: none;
}

.sidebar-options-search input {
    padding-left: 39px;
}

.sidebar-options-bulk {
    padding: 0 16px 16px;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.sidebar-options-bulk-label {
    margin-right: 2px;
    color: var(--text-muted, #64748b);
    font-size: 11px;
    font-weight: 700;
}

.sidebar-bulk-button {
    height: 34px;
    padding: 0 11px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: 1px solid var(--border-soft, #e7ebf3);
    border-radius: 9px;
    background: #fff;
    color: var(--text-main, #101a3b);
    font-size: 11px;
    font-weight: 700;
}

.sidebar-bulk-button:hover {
    color: var(--brand-1, #6547e8);
    border-color: rgba(101, 71, 232, .35);
    background: rgba(101, 71, 232, .05);
}

.sidebar-bulk-button svg {
    width: 14px;
    height: 14px;
}

.sidebar-options-workspace {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 300px;
    gap: 16px;
    align-items: start;
}

.sidebar-options-editor {
    min-width: 0;
}

.sidebar-options-editor .ui-card-header {
    gap: 12px;
}

.sidebar-options-header-copy {
    min-width: 0;
}

.sidebar-options-header-copy h2,
.sidebar-options-header-copy p {
    margin: 0;
}

.sidebar-options-header-copy h2 {
    font-size: 14px;
    font-weight: 800;
}

.sidebar-options-header-copy p {
    margin-top: 4px;
    color: var(--text-muted, #64748b);
    font-size: 10px;
}

.sidebar-options-unsaved {
    display: none;
    align-items: center;
    gap: 6px;
    margin-left: auto;
    padding: 5px 9px;
    border-radius: 30px;
    color: #b45309;
    background: #fff4dc;
    font-size: 10px;
    font-weight: 800;
    white-space: nowrap;
}

.sidebar-options-unsaved.show {
    display: inline-flex;
}

.sidebar-options-unsaved svg {
    width: 13px;
    height: 13px;
}

.sidebar-options-table {
    min-width: 1120px;
}

.sidebar-options-table th {
    white-space: nowrap;
}

.sidebar-options-table td {
    vertical-align: middle;
}

.sidebar-options-table tr[hidden] {
    display: none;
}

.sidebar-menu-cell {
    min-width: 225px;
}

.sidebar-menu-identity {
    display: grid;
    grid-template-columns: 36px minmax(0, 1fr);
    gap: 10px;
    align-items: center;
}

.sidebar-menu-identity.child {
    padding-left: 22px;
}

.sidebar-menu-icon {
    width: 36px;
    height: 36px;
    display: grid;
    place-items: center;
    border-radius: 10px;
    color: var(--brand-1, #6547e8);
    background: rgba(101, 71, 232, .09);
}

.sidebar-menu-icon svg {
    width: 17px;
    height: 17px;
}

.sidebar-menu-copy {
    min-width: 0;
}

.sidebar-menu-copy strong,
.sidebar-menu-copy small {
    display: block;
}

.sidebar-menu-copy strong {
    overflow: hidden;
    font-size: 11px;
    font-weight: 750;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.sidebar-menu-copy small {
    margin-top: 3px;
    color: var(--text-muted, #64748b);
    font-size: 9px;
}

.sidebar-menu-type {
    display: inline-flex;
    align-items: center;
    margin-top: 5px;
    padding: 3px 7px;
    border-radius: 20px;
    color: #4f46e5;
    background: #eef2ff;
    font-size: 8px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .04em;
}

.sidebar-menu-type.child {
    color: #0f766e;
    background: #e6fffb;
}

.sidebar-route-code {
    max-width: 175px;
    display: inline-block;
    overflow: hidden;
    padding: 5px 7px;
    border-radius: 7px;
    color: #475569;
    background: #f8fafc;
    font-size: 9px;
    text-overflow: ellipsis;
    vertical-align: middle;
    white-space: nowrap;
}

.sidebar-switch-cell {
    min-width: 118px;
}

.sidebar-switch-wrap {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.sidebar-switch-copy {
    min-width: 48px;
    color: var(--text-muted, #64748b);
    font-size: 9px;
    font-weight: 700;
}

.sidebar-switch-copy.on {
    color: #168448;
}

.sidebar-options-table .form-control {
    min-width: 140px;
    font-size: 11px;
}

.sidebar-icon-field {
    min-width: 165px;
    display: grid;
    grid-template-columns: minmax(125px, 1fr) 34px;
    gap: 6px;
}

.sidebar-icon-preview {
    width: 34px;
    height: 31px;
    display: grid;
    place-items: center;
    border: 1px solid var(--border-soft, #e7ebf3);
    border-radius: 8px;
    color: var(--brand-1, #6547e8);
    background: #fff;
}

.sidebar-icon-preview svg {
    width: 15px;
    height: 15px;
}

.sidebar-order-input {
    width: 72px;
    min-width: 72px !important;
    text-align: center;
}

.sidebar-row-actions {
    display: flex;
    align-items: center;
    gap: 6px;
}

.sidebar-row-edit,
.sidebar-row-reset {
    width: 32px;
    height: 32px;
    display: grid;
    place-items: center;
    border: 1px solid var(--border-soft, #e7ebf3);
    border-radius: 9px;
    color: #64748b;
    background: #fff;
}

.sidebar-row-edit:hover {
    color: var(--brand-1, #6547e8);
    border-color: rgba(101, 71, 232, .35);
    background: rgba(101, 71, 232, .05);
}

.sidebar-row-reset:hover {
    color: #dc2626;
    border-color: #fecaca;
    background: #fff5f5;
}

.sidebar-row-edit svg,
.sidebar-row-reset svg {
    width: 14px;
    height: 14px;
}

.sidebar-options-empty {
    padding: 42px 20px !important;
    text-align: center !important;
    color: var(--text-muted, #64748b);
}

.sidebar-preview-card {
    position: sticky;
    top: 96px;
    overflow: hidden;
}

.sidebar-preview-header {
    padding: 15px 16px;
    border-bottom: 1px solid rgba(255, 255, 255, .14);
    color: #fff;
    background: linear-gradient(110deg, var(--header-gradient-start, #6845df), var(--header-gradient-end, #1656c9));
}

.sidebar-preview-header span,
.sidebar-preview-header strong,
.sidebar-preview-header small {
    display: block;
}

.sidebar-preview-header span {
    margin-bottom: 10px;
    font-size: 9px;
    font-weight: 800;
    opacity: .8;
    text-transform: uppercase;
    letter-spacing: .08em;
}

.sidebar-preview-header strong {
    font-size: 14px;
}

.sidebar-preview-header small {
    margin-top: 4px;
    font-size: 10px;
    opacity: .82;
}

.sidebar-preview-list {
    max-height: calc(100vh - 250px);
    overflow-y: auto;
    padding: 12px 10px 16px;
    background: var(--sidebar-bg, #fff);
}

.sidebar-preview-item {
    min-height: 40px;
    padding: 9px 10px;
    display: flex;
    align-items: center;
    gap: 10px;
    border-radius: 9px;
    color: var(--sidebar-text, #1e293b);
    font-size: 11px;
    font-weight: 650;
}

.sidebar-preview-item.child {
    min-height: 35px;
    padding-left: 34px;
    font-size: 10px;
}

.sidebar-preview-item.active-preview {
    color: var(--sidebar-active-text, #fff);
    background: linear-gradient(135deg, var(--sidebar-active-bg-1, #6547e8), var(--sidebar-active-bg-2, #315ed8));
}

.sidebar-preview-item svg {
    width: 16px;
    height: 16px;
    flex: 0 0 auto;
}

.sidebar-preview-item span {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.sidebar-preview-empty {
    padding: 34px 18px;
    color: var(--text-muted, #64748b);
    font-size: 11px;
    text-align: center;
}

.sidebar-options-denied {
    padding: 44px 20px;
    text-align: center;
}

.sidebar-options-denied-icon {
    width: 58px;
    height: 58px;
    margin: 0 auto 16px;
    display: grid;
    place-items: center;
    border-radius: 16px;
    color: #dc2626;
    background: #fee2e2;
}

.sidebar-options-denied-icon svg {
    width: 27px;
    height: 27px;
}


.sidebar-edit-preview {
    width: 48px;
    height: 48px;
    display: grid;
    place-items: center;
    border: 1px solid var(--border-soft, #e7ebf3);
    border-radius: 12px;
    color: var(--brand-1, #6547e8);
    background: rgba(101, 71, 232, .08);
}

.sidebar-edit-preview svg {
    width: 21px;
    height: 21px;
}

.sidebar-edit-meta {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

@media (max-width: 575.98px) {
    .sidebar-edit-meta {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 1399.98px) {
    .sidebar-options-workspace {
        grid-template-columns: 1fr;
    }

    .sidebar-preview-card {
        position: static;
    }

    .sidebar-preview-list {
        max-height: 360px;
    }
}

@media (max-width: 991.98px) {
    .sidebar-options-stats {
        grid-template-columns: repeat(2, 1fr);
    }

    .sidebar-options-control-body {
        grid-template-columns: 1fr 1fr;
    }

    .sidebar-options-field-search {
        grid-column: 1 / -1;
    }
}

@media (max-width: 575.98px) {
    .sidebar-options-stats,
    .sidebar-options-control-body {
        grid-template-columns: 1fr;
    }

    .sidebar-options-field-search {
        grid-column: auto;
    }

    .sidebar-bulk-button {
        flex: 1 1 calc(50% - 8px);
        justify-content: center;
    }
}


.sidebar-options-control-body.school-scope {
    grid-template-columns:
        minmax(220px, .8fr)
        minmax(220px, .8fr)
        minmax(260px, 1.2fr)
        minmax(180px, .65fr);
}

.sidebar-action-checkbox {
    min-width: 78px;
    text-align: center;
}

.sidebar-action-checkbox .form-check-input {
    float: none;
    margin: 0;
}

.sidebar-add-edit-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.sidebar-add-edit-grid .full {
    grid-column: 1 / -1;
}

.sidebar-row-delete {
    width: 32px;
    height: 32px;
    display: grid;
    place-items: center;
    border: 1px solid var(--border-soft, #e7ebf3);
    border-radius: 9px;
    color: #dc2626;
    background: #fff;
}

.sidebar-row-delete:hover {
    border-color: #fecaca;
    background: #fff5f5;
}

.sidebar-row-delete svg {
    width: 14px;
    height: 14px;
}

@media (max-width: 1199.98px) {
    .sidebar-options-control-body.school-scope {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 575.98px) {
    .sidebar-options-control-body.school-scope,
    .sidebar-add-edit-grid {
        grid-template-columns: 1fr;
    }

    .sidebar-add-edit-grid .full {
        grid-column: auto;
    }
}

</style>

<div class="sidebar-options-page">
    <div class="page-heading">
        <div>
            <h1 class="page-title">School Sidebar Permissions</h1>
            <p class="page-subtitle">Manage database-driven School Admin menus, school assignments and action permissions.</p>
        </div>

        <div class="page-actions">
            <button id="addSidebarButton" class="btn-ui" type="button">
                <i data-lucide="plus-circle"></i>
                Add Sidebar
            </button>

            <button id="refreshSidebarButton" class="btn-ui" type="button">
                <i data-lucide="refresh-cw"></i>
                Refresh
            </button>

            <button id="saveSidebarButton" class="btn-ui btn-primary-ui" type="button" disabled>
                <i data-lucide="save"></i>
                Save Changes
            </button>
        </div>
    </div>

    <div id="sidebarOptionsMessage" class="alert sidebar-options-message" role="alert"></div>

    <section class="sidebar-options-stats" aria-label="Sidebar summary">
        <article class="sidebar-stat-card">
            <span class="sidebar-stat-icon"><i data-lucide="school"></i></span>
            <div><strong id="schoolCount">0</strong><small>Available schools</small></div>
        </article>

        <article class="sidebar-stat-card">
            <span class="sidebar-stat-icon"><i data-lucide="panels-top-left"></i></span>
            <div><strong id="sidebarTotalCount">0</strong><small>Total menu items</small></div>
        </article>

        <article class="sidebar-stat-card">
            <span class="sidebar-stat-icon"><i data-lucide="eye"></i></span>
            <div><strong id="sidebarVisibleCount">0</strong><small>Visible for selected school</small></div>
        </article>

        <article class="sidebar-stat-card">
            <span class="sidebar-stat-icon"><i data-lucide="shield-check"></i></span>
            <div><strong id="sidebarPermissionCount">0</strong><small>Granted action permissions</small></div>
        </article>
    </section>

    <section class="ui-card sidebar-options-control-card">
        <div class="sidebar-options-control-body school-scope">
            <div class="sidebar-options-field">
                <label for="schoolSelect">School</label>
                <select id="schoolSelect" class="form-select">
                    <option value="">Loading schools...</option>
                </select>
            </div>

            <div class="sidebar-options-field">
                <label for="sidebarRoleSelect">School Role</label>
                <select id="sidebarRoleSelect" class="form-select">
                    <option value="">Select a school first</option>
                </select>
            </div>

            <div class="sidebar-options-field sidebar-options-field-search">
                <label for="sidebarMenuSearch">Search Menu</label>
                <div class="sidebar-options-search">
                    <i data-lucide="search"></i>
                    <input id="sidebarMenuSearch" class="form-control" type="search"
                           placeholder="Search title, key or route..." autocomplete="off">
                </div>
            </div>

            <div class="sidebar-options-field">
                <label for="sidebarMenuFilter">Menu Filter</label>
                <select id="sidebarMenuFilter" class="form-select">
                    <option value="all">All menu items</option>
                    <option value="visible">Visible</option>
                    <option value="hidden">Hidden</option>
                    <option value="parent">Parent menus</option>
                    <option value="child">Submenus</option>
                    <option value="custom">School customized</option>
                </select>
            </div>
        </div>

        <div class="sidebar-options-bulk">
            <span class="sidebar-options-bulk-label">Bulk actions apply to filtered rows:</span>

            <button class="sidebar-bulk-button" type="button" data-bulk-action="show">
                <i data-lucide="eye"></i> Show
            </button>
            <button class="sidebar-bulk-button" type="button" data-bulk-action="hide">
                <i data-lucide="eye-off"></i> Hide
            </button>
            <button class="sidebar-bulk-button" type="button" data-bulk-action="grant-all">
                <i data-lucide="badge-check"></i> Grant All
            </button>
            <button class="sidebar-bulk-button" type="button" data-bulk-action="remove-all">
                <i data-lucide="shield-off"></i> Remove All
            </button>
            <button class="sidebar-bulk-button" type="button" data-bulk-action="normalize-order">
                <i data-lucide="arrow-down-0-1"></i> Normalize Order
            </button>
        </div>
    </section>

    <div class="sidebar-options-workspace">
        <section class="ui-card sidebar-options-editor">
            <div class="ui-card-header">
                <div class="sidebar-options-header-copy">
                    <h2>School Sidebar Configuration</h2>
                    <p id="sidebarFilteredCopy">Select a school and role to load menus.</p>
                </div>

                <span id="sidebarUnsavedBadge" class="sidebar-options-unsaved">
                    <i data-lucide="circle-dot"></i>
                    Unsaved changes
                </span>
            </div>

            <div class="table-responsive">
                <table class="data-table sidebar-options-table" style="min-width:1650px">
                    <thead>
                        <tr>
                            <th>Menu</th>
                            <th>Route</th>
                            <th>View</th>
                            <th>Add</th>
                            <th>Edit</th>
                            <th>Delete</th>
                            <th>Hide/Show</th>
                            <th>Custom Title</th>
                            <th>Lucide Icon</th>
                            <th>Order</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="sidebarOptionsBody">
                        <tr>
                            <td colspan="11" class="sidebar-options-empty">Select a school and role.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <aside class="ui-card sidebar-preview-card">
            <div class="sidebar-preview-header">
                <span>Live preview</span>
                <strong id="sidebarPreviewSchool">Selected School</strong>
                <small>Only visible menus with View permission are shown.</small>
            </div>

            <div id="sidebarPreviewList" class="sidebar-preview-list">
                <div class="sidebar-preview-empty">Select a school to preview its sidebar.</div>
            </div>
        </aside>
    </div>
</div>

<div class="modal fade" id="sidebarItemModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="sidebarItemForm" novalidate>
                <div class="modal-header">
                    <div>
                        <h5 id="sidebarModalTitle" class="modal-title">Add Sidebar</h5>
                        <small class="text-muted">Create or modify a school-specific menu item.</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <input id="modalItemId" type="hidden">

                    <div class="sidebar-add-edit-grid">
                        <div>
                            <label class="form-label fw-semibold" for="modalMenuTitle">Menu Title</label>
                            <input id="modalMenuTitle" class="form-control" maxlength="120" required>
                        </div>

                        <div>
                            <label class="form-label fw-semibold" for="modalMenuKey">Menu Key</label>
                            <input id="modalMenuKey" class="form-control" maxlength="100" required>
                        </div>

                        <div>
                            <label class="form-label fw-semibold" for="modalParentId">Parent Menu</label>
                            <select id="modalParentId" class="form-select">
                                <option value="">Top-level menu</option>
                            </select>
                        </div>

                        <div>
                            <label class="form-label fw-semibold" for="modalIcon">Lucide Icon</label>
                            <input id="modalIcon" class="form-control" maxlength="80" list="schoolSidebarIconOptions" value="circle" required>
                        </div>

                        <div class="full">
                            <label class="form-label fw-semibold" for="modalRoute">Route</label>
                            <input id="modalRoute" class="form-control" maxlength="255" value="#" required>
                        </div>

                        <div>
                            <label class="form-label fw-semibold" for="modalOrder">Display Order</label>
                            <input id="modalOrder" class="form-control" type="number" min="0" max="9999" value="100">
                        </div>

                        <div>
                            <label class="form-label fw-semibold">School Assignment</label>
                            <input id="modalSchoolName" class="form-control" readonly>
                        </div>

                        <div class="full">
                            <div class="sidebar-edit-meta">
                                <div class="form-check form-switch">
                                    <input id="modalView" class="form-check-input" type="checkbox" checked>
                                    <label class="form-check-label" for="modalView">View</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input id="modalAdd" class="form-check-input" type="checkbox" checked>
                                    <label class="form-check-label" for="modalAdd">Add</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input id="modalEdit" class="form-check-input" type="checkbox" checked>
                                    <label class="form-check-label" for="modalEdit">Edit</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input id="modalDelete" class="form-check-input" type="checkbox" checked>
                                    <label class="form-check-label" for="modalDelete">Delete</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input id="modalVisible" class="form-check-input" type="checkbox" checked>
                                    <label class="form-check-label" for="modalVisible">Show in Sidebar</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-ui" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-ui btn-primary-ui">
                        <i data-lucide="save"></i>
                        Save Sidebar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<datalist id="schoolSidebarIconOptions"></datalist>

<script>
(function() {
    'use strict';

    const apiUrl = <?= json_encode($baseUrl . 'api/school-sidebar-management.php', JSON_UNESCAPED_SLASHES) ?>;
    let csrfToken = <?= json_encode($csrfToken, JSON_UNESCAPED_SLASHES) ?>;
    let items = [];
    let initialSnapshot = '[]';
    let busy = false;

    const schoolSelect = document.getElementById('schoolSelect');
    const roleSelect = document.getElementById('sidebarRoleSelect');
    const searchInput = document.getElementById('sidebarMenuSearch');
    const filterSelect = document.getElementById('sidebarMenuFilter');
    const body = document.getElementById('sidebarOptionsBody');
    const saveButton = document.getElementById('saveSidebarButton');
    const addButton = document.getElementById('addSidebarButton');
    const refreshButton = document.getElementById('refreshSidebarButton');
    const messageBox = document.getElementById('sidebarOptionsMessage');
    const unsavedBadge = document.getElementById('sidebarUnsavedBadge');
    const previewList = document.getElementById('sidebarPreviewList');
    const previewSchool = document.getElementById('sidebarPreviewSchool');
    const filteredCopy = document.getElementById('sidebarFilteredCopy');

    const esc = value => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    /*
     * Default Lucide icon options grouped by School Admin sidebar section.
     *
     * Every known sidebar menu has at least five suitable choices.
     * New database-driven menus are handled automatically by keyword-based
     * section matching and finally by the generic fallback group.
     */
    const DEFAULT_SIDEBAR_ICON_OPTIONS = {
        dashboard: {
            dashboard: [
                'layout-dashboard',
                'house',
                'gauge',
                'panels-top-left',
                'chart-no-axes-combined'
            ]
        },

        academic: {
            academic_management: [
                'graduation-cap',
                'school',
                'book-open-check',
                'library-big',
                'notebook-tabs'
            ],
            academic_year: [
                'calendar-range',
                'calendar-days',
                'calendar-check-2',
                'history',
                'clock-3'
            ],
            classes: [
                'presentation',
                'school',
                'layout-grid',
                'users-round',
                'book-open'
            ],
            sections: [
                'panels-top-left',
                'columns-3',
                'layout-list',
                'split-square-vertical',
                'rows-3'
            ],
            subjects: [
                'book-open-text',
                'notebook-text',
                'library',
                'books',
                'file-text'
            ],
            timetable: [
                'calendar-clock',
                'table-2',
                'calendar-days',
                'clock-9',
                'list-clock'
            ]
        },

        students: {
            student_management: [
                'users',
                'user-round-search',
                'graduation-cap',
                'contact-round',
                'school'
            ],
            student_admission: [
                'user-round-plus',
                'clipboard-plus',
                'file-plus-2',
                'badge-plus',
                'user-plus'
            ],
            student_list: [
                'list',
                'users-round',
                'contact-round',
                'rows-3',
                'table-properties'
            ],
            student_attendance: [
                'calendar-check',
                'user-check',
                'clipboard-check',
                'list-checks',
                'badge-check'
            ],
            student_promotion: [
                'trending-up',
                'move-up-right',
                'badge-up',
                'chevrons-up',
                'graduation-cap'
            ],
            transfer_certificate: [
                'file-check-2',
                'file-badge',
                'scroll-text',
                'stamp',
                'badge-check'
            ]
        },

        teachers_staff: {
            teacher_management: [
                'presentation',
                'user-round-cog',
                'briefcase-business',
                'users',
                'school'
            ],
            teachers: [
                'presentation',
                'user-round',
                'badge',
                'briefcase',
                'graduation-cap'
            ],
            staff: [
                'users-round',
                'contact',
                'briefcase-business',
                'id-card',
                'user-cog'
            ],
            staff_attendance: [
                'calendar-check-2',
                'clipboard-check',
                'users-round',
                'user-check',
                'list-checks'
            ],
            leave_management: [
                'calendar-off',
                'calendar-minus',
                'plane',
                'clock-alert',
                'file-clock'
            ]
        },

        examination: {
            examination: [
                'file-check-2',
                'clipboard-list',
                'notebook-pen',
                'badge-check',
                'file-search'
            ],
            exam_setup: [
                'settings-2',
                'list-cog',
                'clipboard-pen',
                'file-cog',
                'sliders-horizontal'
            ],
            exam_schedule: [
                'calendar-clock',
                'calendar-range',
                'alarm-clock',
                'clipboard-list',
                'list-clock'
            ],
            marks_entry: [
                'square-pen',
                'clipboard-pen-line',
                'file-pen-line',
                'notebook-pen',
                'table-properties'
            ],
            grade_management: [
                'badge',
                'award',
                'medal',
                'chart-no-axes-column-increasing',
                'star'
            ],
            report_cards: [
                'file-chart-column',
                'file-text',
                'clipboard-check',
                'chart-bar-big',
                'notebook-tabs'
            ]
        },

        fees_accounts: {
            fee_management: [
                'badge-indian-rupee',
                'wallet-cards',
                'landmark',
                'receipt-indian-rupee',
                'circle-dollar-sign'
            ],
            fee_structure: [
                'table-properties',
                'list-tree',
                'receipt-text',
                'calculator',
                'wallet'
            ],
            fee_collection: [
                'hand-coins',
                'wallet-cards',
                'badge-indian-rupee',
                'receipt',
                'circle-dollar-sign'
            ],
            fee_reports: [
                'file-chart-column',
                'chart-no-axes-combined',
                'receipt-text',
                'table-properties',
                'file-spreadsheet'
            ],
            due_fees: [
                'circle-alert',
                'badge-alert',
                'receipt-indian-rupee',
                'clock-alert',
                'wallet-minimal'
            ]
        },

        library: {
            library: [
                'library',
                'book-open',
                'books',
                'library-big',
                'book-copy'
            ],
            books: [
                'books',
                'book-open-text',
                'book-copy',
                'notebook',
                'library'
            ],
            issue_return: [
                'arrow-left-right',
                'book-up-2',
                'book-down',
                'repeat-2',
                'refresh-cw'
            ]
        },

        transport: {
            transport: [
                'bus-front',
                'route',
                'map-pinned',
                'navigation',
                'traffic-cone'
            ],
            vehicles: [
                'bus-front',
                'car-front',
                'truck',
                'van',
                'circle-gauge'
            ],
            routes: [
                'route',
                'map',
                'map-pinned',
                'signpost',
                'navigation-2'
            ],
            student_transport: [
                'bus-front',
                'users-round',
                'map-pinned',
                'route',
                'school'
            ]
        },

        communication: {
            communication: [
                'messages-square',
                'message-circle-more',
                'megaphone',
                'mail',
                'radio'
            ],
            notice_board: [
                'clipboard-list',
                'panels-top-left',
                'message-square-text',
                'megaphone',
                'sticky-note'
            ],
            sms: [
                'message-square-text',
                'smartphone',
                'messages-square',
                'send',
                'message-circle'
            ],
            email: [
                'mail',
                'mail-open',
                'send',
                'inbox',
                'at-sign'
            ],
            announcements: [
                'megaphone',
                'bell-ring',
                'radio',
                'message-square-more',
                'volume-2'
            ],
            notifications: [
                'bell',
                'bell-ring',
                'badge-alert',
                'message-circle-warning',
                'mail-warning'
            ],
            events_calendar: [
                'calendar-days',
                'calendar-heart',
                'calendar-check-2',
                'party-popper',
                'clock'
            ]
        },

        reports: {
            reports: [
                'chart-no-axes-combined',
                'file-chart-column',
                'chart-bar-big',
                'clipboard-list',
                'file-spreadsheet'
            ],
            student_reports: [
                'users-round',
                'file-chart-column',
                'clipboard-list',
                'chart-bar-big',
                'file-spreadsheet'
            ],
            attendance_reports: [
                'calendar-check-2',
                'file-chart-column',
                'clipboard-check',
                'chart-no-axes-column-increasing',
                'table-properties'
            ],
            examination_reports: [
                'file-check-2',
                'file-chart-column',
                'clipboard-list',
                'chart-bar-big',
                'notebook-tabs'
            ],
            fee_reports: [
                'receipt-text',
                'file-chart-column',
                'chart-no-axes-combined',
                'file-spreadsheet',
                'wallet-cards'
            ]
        },

        users_permissions: {
            user_management: [
                'users',
                'user-cog',
                'users-round',
                'contact-round',
                'id-card'
            ],
            users: [
                'users',
                'users-round',
                'contact',
                'user-round',
                'id-card'
            ],
            roles_permissions: [
                'shield-check',
                'key-round',
                'user-cog',
                'badge-check',
                'lock-keyhole'
            ],
            role_permission: [
                'shield-check',
                'key-round',
                'user-cog',
                'badge-check',
                'lock-keyhole'
            ]
        },

        settings: {
            settings: [
                'settings',
                'settings-2',
                'sliders-horizontal',
                'wrench',
                'cog'
            ],
            school_profile: [
                'school',
                'building-2',
                'badge-info',
                'landmark',
                'map-pin-house'
            ],
            general_settings: [
                'sliders-horizontal',
                'settings-2',
                'list-cog',
                'wrench',
                'panel-top'
            ],
            theme_settings: [
                'palette',
                'paintbrush',
                'swatch-book',
                'sun-moon',
                'wand-sparkles'
            ],
            backup_restore: [
                'database-backup',
                'database',
                'archive-restore',
                'refresh-ccw',
                'hard-drive-download'
            ],
            profile: [
                'circle-user-round',
                'user-round',
                'contact-round',
                'badge',
                'id-card'
            ],
            change_password: [
                'key-round',
                'lock-keyhole',
                'shield-check',
                'key-square',
                'fingerprint'
            ],
            sidebar_options: [
                'panel-left',
                'panels-top-left',
                'panel-left-dashed',
                'layout-panel-left',
                'list-tree'
            ],
            system_settings: [
                'settings',
                'server-cog',
                'database-cog',
                'wrench',
                'shield-cog'
            ]
        },

        support_account: {
            help_support: [
                'circle-help',
                'life-buoy',
                'headphones',
                'message-circle-question',
                'badge-help'
            ],
            logout: [
                'log-out',
                'door-open',
                'power',
                'move-right',
                'circle-off'
            ]
        },

        generic: {
            default: [
                'circle',
                'square-menu',
                'panel-left',
                'layout-grid',
                'list'
            ]
        }
    };

    const ICON_SECTION_KEYWORDS = {
        academic: [
            'academic', 'class', 'section', 'subject',
            'timetable', 'year'
        ],
        students: [
            'student', 'admission', 'promotion',
            'transfer', 'certificate'
        ],
        teachers_staff: [
            'teacher', 'staff', 'employee', 'leave'
        ],
        examination: [
            'exam', 'mark', 'grade', 'report_card'
        ],
        fees_accounts: [
            'fee', 'payment', 'account', 'finance',
            'invoice', 'collection', 'due'
        ],
        library: [
            'library', 'book', 'issue', 'return'
        ],
        transport: [
            'transport', 'vehicle', 'route', 'bus'
        ],
        communication: [
            'communication', 'notice', 'sms', 'email',
            'announcement', 'notification', 'event'
        ],
        reports: [
            'report', 'analytics', 'statistics'
        ],
        users_permissions: [
            'user', 'role', 'permission'
        ],
        settings: [
            'setting', 'profile', 'theme', 'backup',
            'restore', 'sidebar', 'system'
        ],
        support_account: [
            'support', 'help', 'logout'
        ]
    };

    function normalizeMenuIdentifier(value) {
        return String(value || '')
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '');
    }

    function iconOptionsFor(menuKey, menuTitle = '') {
        const key = normalizeMenuIdentifier(menuKey);
        const titleKey = normalizeMenuIdentifier(menuTitle);
        const candidates = [key, titleKey].filter(Boolean);

        for (const section of Object.values(
            DEFAULT_SIDEBAR_ICON_OPTIONS
        )) {
            for (const candidate of candidates) {
                if (Array.isArray(section[candidate])) {
                    return [...section[candidate]];
                }
            }
        }

        const searchable = `${key} ${titleKey}`;

        for (const [sectionName, keywords] of Object.entries(
            ICON_SECTION_KEYWORDS
        )) {
            if (keywords.some(keyword => searchable.includes(keyword))) {
                const section =
                    DEFAULT_SIDEBAR_ICON_OPTIONS[sectionName];

                if (section) {
                    const firstOptions = Object.values(section).find(
                        options => Array.isArray(options)
                    );

                    if (firstOptions) {
                        return [...firstOptions];
                    }
                }
            }
        }

        return [
            ...DEFAULT_SIDEBAR_ICON_OPTIONS.generic.default
        ];
    }

    function defaultIconFor(menuKey, menuTitle = '') {
        return iconOptionsFor(menuKey, menuTitle)[0] || 'circle';
    }

    function populateIconDatalist(menuKey, menuTitle = '') {
        const datalist = document.getElementById(
            'schoolSidebarIconOptions'
        );

        if (!datalist) {
            return;
        }

        const options = iconOptionsFor(menuKey, menuTitle);

        datalist.innerHTML = options.map(iconName =>
            `<option value="${esc(iconName)}"></option>`
        ).join('');
    }

    function showMessage(text, success) {
        messageBox.className = 'alert sidebar-options-message show ' +
            (success ? 'alert-success' : 'alert-danger');
        messageBox.textContent = text;
    }

    function selectedText(select, fallback) {
        return select.options[select.selectedIndex]?.textContent || fallback;
    }

    function modal() {
        const element = document.getElementById('sidebarItemModal');

        if (!element || !window.bootstrap || !window.bootstrap.Modal) {
            showMessage('Bootstrap JavaScript is not loaded.', false);
            return null;
        }

        return window.bootstrap.Modal.getOrCreateInstance(element);
    }

    function setBusy(value) {
        busy = value;
        schoolSelect.disabled = value;
        roleSelect.disabled = value;
        addButton.disabled = value || !schoolSelect.value || !roleSelect.value;
        refreshButton.disabled = value;
        document.querySelectorAll('[data-bulk-action]').forEach(button => {
            button.disabled = value;
        });
        updateDirty();
    }

    async function readJson(response) {
        const text = await response.text();

        try {
            return JSON.parse(text);
        } catch (error) {
            throw new Error(text || 'Server returned an invalid response.');
        }
    }

    async function request(action, data = {}, method = 'GET') {
        let response;

        if (method === 'GET') {
            const url = new URL(apiUrl, location.origin);
            url.searchParams.set('action', action);

            Object.entries(data).forEach(([key, value]) => {
                if (value !== '' && value !== null && value !== undefined) {
                    url.searchParams.set(key, String(value));
                }
            });

            response = await fetch(url, {
                headers: {Accept: 'application/json'},
                credentials: 'same-origin'
            });
        } else {
            response = await fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    action,
                    csrf_token: csrfToken,
                    ...data
                })
            });
        }

        const result = await readJson(response);

        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Request failed.');
        }

        return result;
    }

    function buildTree() {
        const children = new Map();
        const ordered = [];
        const visited = new Set();

        items.forEach(item => {
            const parentId = Number(item.parent_id || 0);

            if (!children.has(parentId)) {
                children.set(parentId, []);
            }

            children.get(parentId).push(item);
        });

        children.forEach(list => list.sort((left, right) =>
            Number(left.display_order || 0) - Number(right.display_order || 0)
            || Number(left.sidebar_item_id) - Number(right.sidebar_item_id)
        ));

        function walk(parentId, depth) {
            (children.get(parentId) || []).forEach(item => {
                const id = Number(item.sidebar_item_id);

                if (visited.has(id)) return;

                visited.add(id);
                ordered.push({...item, _depth: depth});
                walk(id, depth + 1);
            });
        }

        walk(0, 0);

        items.forEach(item => {
            if (!visited.has(Number(item.sidebar_item_id))) {
                ordered.push({...item, _depth: 0});
            }
        });

        return ordered;
    }

    function collectItems() {
        body.querySelectorAll('tr[data-id]').forEach(row => {
            const item = items.find(value =>
                Number(value.sidebar_item_id) === Number(row.dataset.id)
            );

            if (!item) return;

            item.can_view = row.querySelector('.js-view').checked ? 1 : 0;
            item.can_add = row.querySelector('.js-add').checked ? 1 : 0;
            item.can_edit = row.querySelector('.js-edit').checked ? 1 : 0;
            item.can_delete = row.querySelector('.js-delete').checked ? 1 : 0;
            item.is_visible = row.querySelector('.js-visible').checked ? 1 : 0;
            item.custom_title = row.querySelector('.js-title').value.trim();
            item.custom_icon = row.querySelector('.js-icon').value.trim();
            item.display_order = Math.max(
                0,
                Number(row.querySelector('.js-order').value || 0)
            );
        });

        return items;
    }

    function isDirty() {
        return JSON.stringify(collectItems()) !== initialSnapshot;
    }

    function updateDirty() {
        const dirty = isDirty();
        unsavedBadge.classList.toggle('show', dirty);
        saveButton.disabled = busy || !dirty || !schoolSelect.value || !roleSelect.value;
    }

    function render() {
        const query = searchInput.value.trim().toLowerCase();
        const filter = filterSelect.value;

        const rows = buildTree().filter(item => {
            const visible = Number(item.is_visible) === 1;
            const isChild = Number(item.parent_id || 0) > 0;
            const customized = Boolean(
                item.custom_title
                || item.custom_icon
                || Number(item.display_order) !== Number(item.default_order)
            );

            const matchesSearch = [
                item.menu_title,
                item.menu_key,
                item.route,
                item.custom_title,
                item.custom_icon
            ].join(' ').toLowerCase().includes(query);

            let matchesFilter = true;
            if (filter === 'visible') matchesFilter = visible;
            if (filter === 'hidden') matchesFilter = !visible;
            if (filter === 'parent') matchesFilter = !isChild;
            if (filter === 'child') matchesFilter = isChild;
            if (filter === 'custom') matchesFilter = customized;

            return matchesSearch && matchesFilter;
        });

        body.innerHTML = rows.map(item => {
            const title = item.custom_title || item.menu_title || 'Menu';
            const icon = item.custom_icon
                || item.icon
                || defaultIconFor(item.menu_key, item.menu_title);
            const depth = Number(item._depth || 0);

            return `<tr data-id="${Number(item.sidebar_item_id)}">
                <td class="sidebar-menu-cell">
                    <div class="sidebar-menu-identity ${depth ? 'child' : ''}" style="padding-left:${depth * 18}px">
                        <span class="sidebar-menu-icon"><i data-lucide="${esc(icon)}"></i></span>
                        <div class="sidebar-menu-copy">
                            <strong>${esc(title)}</strong>
                            <small>${esc(item.menu_key)}</small>
                            <span class="sidebar-menu-type ${depth ? 'child' : ''}">${depth ? 'Submenu' : 'Parent menu'}</span>
                        </div>
                    </div>
                </td>
                <td><code class="sidebar-route-code" title="${esc(item.route)}">${esc(item.route)}</code></td>
                <td class="sidebar-action-checkbox"><input class="form-check-input js-view" type="checkbox" ${Number(item.can_view) === 1 ? 'checked' : ''}></td>
                <td class="sidebar-action-checkbox"><input class="form-check-input js-add" type="checkbox" ${Number(item.can_add) === 1 ? 'checked' : ''}></td>
                <td class="sidebar-action-checkbox"><input class="form-check-input js-edit" type="checkbox" ${Number(item.can_edit) === 1 ? 'checked' : ''}></td>
                <td class="sidebar-action-checkbox"><input class="form-check-input js-delete" type="checkbox" ${Number(item.can_delete) === 1 ? 'checked' : ''}></td>
                <td class="sidebar-action-checkbox"><input class="form-check-input js-visible" type="checkbox" ${Number(item.is_visible) === 1 ? 'checked' : ''}></td>
                <td><input class="form-control form-control-sm js-title" maxlength="120" value="${esc(item.custom_title || '')}" placeholder="${esc(item.menu_title)}"></td>
                <td><input
                    class="form-control form-control-sm js-icon"
                    maxlength="80"
                    list="schoolSidebarIconOptions"
                    data-menu-key="${esc(item.menu_key)}"
                    data-menu-title="${esc(item.menu_title)}"
                    value="${esc(item.custom_icon || '')}"
                    placeholder="${esc(
                        item.icon
                        || defaultIconFor(
                            item.menu_key,
                            item.menu_title
                        )
                    )}"
                ></td>
                <td><input class="form-control form-control-sm sidebar-order-input js-order" type="number" min="0" max="9999" value="${Number(item.display_order || 0)}"></td>
                <td>
                    <div class="sidebar-row-actions">
                        <button class="sidebar-row-edit js-row-edit" type="button" title="Edit"><i data-lucide="square-pen"></i></button>
                        <button class="sidebar-row-delete js-row-delete" type="button" title="Delete"><i data-lucide="trash-2"></i></button>
                    </div>
                </td>
            </tr>`;
        }).join('') || '<tr><td colspan="11" class="sidebar-options-empty">No matching sidebar items found.</td></tr>';

        body.querySelectorAll('input').forEach(input => {
            input.addEventListener(
                input.type === 'checkbox' ? 'change' : 'input',
                () => {
                    collectItems();
                    renderPreview();
                    updateSummary();
                    updateDirty();
                }
            );
        });

        body.querySelectorAll('.js-icon').forEach(input => {
            input.addEventListener('focus', () => {
                populateIconDatalist(
                    input.dataset.menuKey || '',
                    input.dataset.menuTitle || ''
                );
            });
        });

        body.querySelectorAll('.js-row-edit').forEach(button => {
            button.addEventListener('click', () => {
                const row = button.closest('tr[data-id]');
                openModal(items.find(item =>
                    Number(item.sidebar_item_id) === Number(row.dataset.id)
                ));
            });
        });

        body.querySelectorAll('.js-row-delete').forEach(button => {
            button.addEventListener('click', async () => {
                const row = button.closest('tr[data-id]');
                const item = items.find(value =>
                    Number(value.sidebar_item_id) === Number(row.dataset.id)
                );

                if (!item || !confirm(`Delete or disable "${item.custom_title || item.menu_title}" for this school?`)) {
                    return;
                }

                try {
                    setBusy(true);
                    const result = await request('delete', {
                        school_id: Number(schoolSelect.value),
                        role_id: Number(roleSelect.value),
                        sidebar_item_id: Number(item.sidebar_item_id)
                    }, 'POST');
                    showMessage(result.message, true);
                    await loadItems();
                } catch (error) {
                    showMessage(error.message, false);
                } finally {
                    setBusy(false);
                }
            });
        });

        filteredCopy.textContent = `Showing ${rows.length} of ${items.length} menu items`;
        refreshIcons();
        updateSummary();
        renderPreview();
        updateDirty();
    }

    function renderPreview() {
        previewSchool.textContent = selectedText(schoolSelect, 'Selected School');

        const visibleIds = new Set(
            items.filter(item =>
                Number(item.can_view) === 1
                && Number(item.is_visible) === 1
            ).map(item => Number(item.sidebar_item_id))
        );

        const previewItems = buildTree().filter(item => {
            if (!visibleIds.has(Number(item.sidebar_item_id))) return false;
            if (!item.parent_id) return true;
            return visibleIds.has(Number(item.parent_id));
        });

        previewList.innerHTML = previewItems.map((item, index) => {
            const title = item.custom_title || item.menu_title;
            const icon = item.custom_icon
                || item.icon
                || defaultIconFor(item.menu_key, item.menu_title);
            const child = Number(item.parent_id || 0) > 0;

            return `<div class="sidebar-preview-item ${child ? 'child' : ''} ${index === 0 ? 'active-preview' : ''}">
                <i data-lucide="${esc(icon)}"></i>
                <span>${esc(title)}</span>
            </div>`;
        }).join('') || '<div class="sidebar-preview-empty">No menus are visible for this school and role.</div>';

        refreshIcons();
    }

    function updateSummary() {
        document.getElementById('sidebarTotalCount').textContent = String(items.length);
        document.getElementById('sidebarVisibleCount').textContent = String(
            items.filter(item => Number(item.is_visible) === 1).length
        );

        let granted = 0;
        items.forEach(item => {
            granted += Number(item.can_view) === 1 ? 1 : 0;
            granted += Number(item.can_add) === 1 ? 1 : 0;
            granted += Number(item.can_edit) === 1 ? 1 : 0;
            granted += Number(item.can_delete) === 1 ? 1 : 0;
        });

        document.getElementById('sidebarPermissionCount').textContent = String(granted);
    }

    function refreshIcons() {
        if (window.lucide) window.lucide.createIcons();
    }

    function parentOptions(currentId = 0) {
        return '<option value="">Top-level menu</option>' +
            buildTree()
                .filter(item => Number(item.sidebar_item_id) !== Number(currentId))
                .map(item => `<option value="${Number(item.sidebar_item_id)}">${'— '.repeat(Number(item._depth || 0))}${esc(item.custom_title || item.menu_title)} (${esc(item.menu_key)})</option>`)
                .join('');
    }

    function openModal(item = null) {
        if (!schoolSelect.value || !roleSelect.value) {
            showMessage('Select a school and role first.', false);
            return;
        }

        document.getElementById('sidebarModalTitle').textContent = item ? 'Edit Sidebar' : 'Add Sidebar';
        document.getElementById('modalItemId').value = item ? item.sidebar_item_id : '';
        document.getElementById('modalMenuTitle').value = item ? (item.custom_title || item.menu_title) : '';
        document.getElementById('modalMenuKey').value = item ? item.menu_key : '';
        document.getElementById('modalMenuKey').readOnly = Boolean(item);
        document.getElementById('modalParentId').innerHTML = parentOptions(item?.sidebar_item_id || 0);
        document.getElementById('modalParentId').value = item?.parent_id ? String(item.parent_id) : '';
        document.getElementById('modalRoute').value = item ? item.route : '#';

        const modalMenuKey = item
            ? item.menu_key
            : document.getElementById('modalMenuKey').value;
        const modalMenuTitle = item
            ? item.menu_title
            : document.getElementById('modalMenuTitle').value;

        populateIconDatalist(modalMenuKey, modalMenuTitle);

        document.getElementById('modalIcon').value = item
            ? (
                item.custom_icon
                || item.icon
                || defaultIconFor(item.menu_key, item.menu_title)
            )
            : defaultIconFor(modalMenuKey, modalMenuTitle);
        document.getElementById('modalOrder').value = item ? Number(item.display_order || 0) : 100;
        document.getElementById('modalSchoolName').value = selectedText(schoolSelect, '');
        document.getElementById('modalView').checked = item ? Number(item.can_view) === 1 : true;
        document.getElementById('modalAdd').checked = item ? Number(item.can_add) === 1 : true;
        document.getElementById('modalEdit').checked = item ? Number(item.can_edit) === 1 : true;
        document.getElementById('modalDelete').checked = item ? Number(item.can_delete) === 1 : true;
        document.getElementById('modalVisible').checked = item ? Number(item.is_visible) === 1 : true;
        modal()?.show();
    }

    async function loadMeta() {
        const result = await request('meta');
        csrfToken = result.data.csrf_token || csrfToken;
        const schools = result.data.schools || [];

        document.getElementById('schoolCount').textContent = String(schools.length);
        schoolSelect.innerHTML = '<option value="">Select school</option>' +
            schools.map(school => `<option value="${school.id}">${esc(school.school_name)} (${esc(school.tenant_code)})</option>`).join('');
    }

    async function loadRoles() {
        items = [];
        render();

        if (!schoolSelect.value) {
            roleSelect.innerHTML = '<option value="">Select a school first</option>';
            return;
        }

        const result = await request('roles', {
            school_id: schoolSelect.value
        });

        const roles = result.data.roles || [];
        roleSelect.innerHTML = '<option value="">Select role</option>' +
            roles.map(role => `<option value="${role.id}">${esc(role.role_name)} (${esc(role.role_key)})</option>`).join('');

        if (roles.length) {
            roleSelect.value = String(roles[0].id);
            await loadItems();
        }
    }

    async function loadItems() {
        if (!schoolSelect.value || !roleSelect.value) return;

        setBusy(true);

        try {
            const result = await request('items', {
                school_id: schoolSelect.value,
                role_id: roleSelect.value
            });

            items = result.data.items || [];
            initialSnapshot = JSON.stringify(items);
            render();
        } catch (error) {
            showMessage(error.message, false);
        } finally {
            setBusy(false);
        }
    }

    function applyBulk(action) {
        const query = searchInput.value.trim().toLowerCase();

        items.filter(item => [
            item.menu_title,
            item.menu_key,
            item.route,
            item.custom_title
        ].join(' ').toLowerCase().includes(query)).forEach((item, index) => {
            if (action === 'show') item.is_visible = 1;
            if (action === 'hide') item.is_visible = 0;

            if (action === 'grant-all') {
                item.can_view = 1;
                item.can_add = 1;
                item.can_edit = 1;
                item.can_delete = 1;
            }

            if (action === 'remove-all') {
                item.can_view = 0;
                item.can_add = 0;
                item.can_edit = 0;
                item.can_delete = 0;
            }

            if (action === 'normalize-order') {
                item.display_order = (index + 1) * 10;
            }
        });

        render();
    }

    schoolSelect.addEventListener('change', loadRoles);
    roleSelect.addEventListener('change', loadItems);
    searchInput.addEventListener('input', render);
    filterSelect.addEventListener('change', render);
    addButton.addEventListener('click', () => openModal());
    refreshButton.addEventListener('click', loadItems);

    document.querySelectorAll('[data-bulk-action]').forEach(button => {
        button.addEventListener('click', () => applyBulk(button.dataset.bulkAction));
    });

    saveButton.addEventListener('click', async () => {
        try {
            setBusy(true);
            const result = await request('save', {
                school_id: Number(schoolSelect.value),
                role_id: Number(roleSelect.value),
                items: collectItems()
            }, 'POST');
            showMessage(result.message, true);
            await loadItems();
        } catch (error) {
            showMessage(error.message, false);
        } finally {
            setBusy(false);
        }
    });

    document.getElementById('modalMenuTitle').addEventListener(
        'input',
        () => {
            const menuKey =
                document.getElementById('modalMenuKey').value;
            const menuTitle =
                document.getElementById('modalMenuTitle').value;

            populateIconDatalist(menuKey, menuTitle);
        }
    );

    document.getElementById('modalMenuKey').addEventListener(
        'input',
        () => {
            const menuKey =
                document.getElementById('modalMenuKey').value;
            const menuTitle =
                document.getElementById('modalMenuTitle').value;

            populateIconDatalist(menuKey, menuTitle);
        }
    );

    document.getElementById('sidebarItemForm').addEventListener('submit', async event => {
        event.preventDefault();

        if (!event.currentTarget.checkValidity()) {
            event.currentTarget.classList.add('was-validated');
            return;
        }

        const itemId = document.getElementById('modalItemId').value;

        try {
            setBusy(true);

            const result = await request(itemId ? 'edit' : 'add', {
                school_id: Number(schoolSelect.value),
                role_id: Number(roleSelect.value),
                sidebar_item_id: itemId ? Number(itemId) : 0,
                menu_title: document.getElementById('modalMenuTitle').value.trim(),
                menu_key: document.getElementById('modalMenuKey').value.trim(),
                parent_id: document.getElementById('modalParentId').value === ''
                    ? null
                    : Number(document.getElementById('modalParentId').value),
                route: document.getElementById('modalRoute').value.trim(),
                icon: document.getElementById('modalIcon').value.trim(),
                display_order: Number(document.getElementById('modalOrder').value || 0),
                can_view: document.getElementById('modalView').checked ? 1 : 0,
                can_add: document.getElementById('modalAdd').checked ? 1 : 0,
                can_edit: document.getElementById('modalEdit').checked ? 1 : 0,
                can_delete: document.getElementById('modalDelete').checked ? 1 : 0,
                is_visible: document.getElementById('modalVisible').checked ? 1 : 0
            }, 'POST');

            modal()?.hide();
            showMessage(result.message, true);
            await loadItems();
        } catch (error) {
            showMessage(error.message, false);
        } finally {
            setBusy(false);
        }
    });

    loadMeta().catch(error => showMessage(error.message, false));
})();
</script>

<?php require dirname(__DIR__) . '/includes/layout-end.php'; ?>

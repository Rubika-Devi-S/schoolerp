<?php
declare(strict_types=1);

$pageTitle = 'Super Admin Sidebar Options';
$pageKey = 'super_admin_sidebar_options';
$sidebarFile = __DIR__ . '/sidebar.php';
require dirname(__DIR__) . '/includes/layout-start.php';

$currentUser = function_exists('current_user') ? current_user() : [];
$roleId = (int)($currentUser['role_id'] ?? $_SESSION['role_id'] ?? 0);
$roleKey = strtolower(trim((string)($_SESSION['role_key'] ?? '')));
if ($roleKey === '' && isset($pdo) && $pdo instanceof PDO && $roleId > 0) {
    try {
        $stmt = $pdo->prepare("SELECT role_key FROM roles WHERE id=:id AND status='active' LIMIT 1");
        $stmt->execute(['id' => $roleId]);
        $roleKey = strtolower(trim((string)$stmt->fetchColumn()));
    } catch (Throwable $e) { error_log($e->getMessage()); }
}
$isSuperAdmin = $roleId === 1 || in_array(
    $roleKey,
    ['super_admin','super-administrator','super_administrator'],
    true
);
if (!$isSuperAdmin) {
    http_response_code(403);
    echo '<div class="ui-card"><div class="ui-card-body"><h1 class="page-title">Access denied</h1><p class="page-subtitle">Only a Super Administrator can manage this sidebar.</p></div></div>';
    require dirname(__DIR__) . '/includes/layout-end.php';
    exit;
}
$baseUrl = defined('BASE_URL') ? rtrim((string)BASE_URL, '/') . '/' : '../';
$csrfToken = function_exists('csrfToken') ? csrfToken() : '';
?>
<style>
.sa-options{
    display:grid;
    gap:16px;
    width:100%;
    min-width:0;
}

.sa-message{
    display:none;
    margin:0;
}

.sa-message.show{
    display:block;
}

.sa-toolbar{
    padding:16px;
    display:grid;
    grid-template-columns:minmax(220px,280px) minmax(260px,1fr);
    gap:14px;
    align-items:end;
}

.sa-field label{
    display:block;
    margin-bottom:6px;
    font-size:11px;
    font-weight:750;
}

.sa-table{
    min-width:1420px;
}

.sa-menu{
    min-width:230px;
}

.sa-menu-wrap{
    display:flex;
    align-items:center;
    gap:10px;
}

.sa-menu-wrap.child{
    padding-left:28px;
}

.sa-icon,
.sa-icon-preview{
    width:38px;
    height:38px;
    display:grid;
    place-items:center;
    flex:0 0 auto;
    border-radius:11px;
    color:var(--brand-1,#6547e8);
    background:rgba(101,71,232,.10);
    border:1px solid rgba(101,71,232,.12);
}

.sa-icon svg,
.sa-icon-preview svg{
    width:18px;
    height:18px;
}

.sa-icon-fallback{
    font-size:17px;
    line-height:1;
}

.sa-copy strong,
.sa-copy small{
    display:block;
}

.sa-copy strong{
    font-size:11px;
}

.sa-copy small{
    margin-top:3px;
    color:var(--text-muted,#64748b);
    font-size:9px;
}

.sa-parent{
    min-width:190px;
}

.sa-route{
    min-width:230px;
}

.sa-order{
    width:80px;
}

.sa-actions{
    display:flex;
    gap:6px;
    align-items:center;
}

.sa-edit,
.sa-delete,
.sa-icon-button{
    width:34px;
    height:34px;
    display:inline-grid;
    place-items:center;
    border:1px solid var(--border-soft,#e7ebf3);
    border-radius:9px;
    background:var(--card-bg,#fff);
}

.sa-edit,
.sa-icon-button{
    color:var(--brand-1,#6547e8);
}

.sa-edit:hover,
.sa-icon-button:hover{
    background:rgba(101,71,232,.08);
}

.sa-delete{
    color:var(--danger-color,#dc3545);
}

.sa-delete:hover{
    background:rgba(220,53,69,.08);
}

.sa-edit svg,
.sa-delete svg,
.sa-icon-button svg{
    width:15px;
    height:15px;
}

.sa-empty{
    padding:30px;
    text-align:center;
    color:var(--text-muted,#64748b);
}

.sa-icon-field{
    display:grid;
    grid-template-columns:44px minmax(0,1fr) auto;
    gap:9px;
    align-items:center;
}

.sa-icon-field .form-control{
    min-width:0;
}

.sa-icon-help{
    display:block;
    margin-top:6px;
    color:var(--text-muted,#64748b);
    font-size:9px;
}

.sa-icon-picker{
    display:grid;
    gap:12px;
}

.sa-icon-search{
    position:sticky;
    top:0;
    z-index:3;
    padding-bottom:3px;
    background:var(--card-bg,#fff);
}

.sa-icon-grid{
    display:grid;
    grid-template-columns:repeat(6,minmax(0,1fr));
    gap:9px;
    max-height:430px;
    overflow:auto;
    padding:2px;
}

.sa-icon-option{
    min-height:74px;
    display:grid;
    place-items:center;
    gap:7px;
    padding:10px 7px;
    border:1px solid var(--border-soft,#e7ebf3);
    border-radius:11px;
    color:var(--text-main,#101b46);
    background:var(--body-bg,#f6f8fc);
    text-align:center;
    cursor:pointer;
}

.sa-icon-option:hover,
.sa-icon-option.active{
    color:var(--brand-1,#6547e8);
    border-color:var(--brand-1,#6547e8);
    background:rgba(101,71,232,.08);
}

.sa-icon-option svg{
    width:21px;
    height:21px;
}

.sa-icon-option span{
    font-size:8px;
    overflow-wrap:anywhere;
}

.sa-icon-empty{
    grid-column:1/-1;
    padding:28px 10px;
    text-align:center;
    color:var(--text-muted,#64748b);
}

.sa-live-row-icon{
    display:flex;
    align-items:center;
    gap:8px;
    min-width:190px;
}

.sa-live-row-icon .form-control{
    min-width:0;
}

#iconPickerModal .modal-dialog{
    max-width:920px;
}

#iconPickerModal .modal-content{
    max-height:calc(100dvh - 32px);
}

#iconPickerModal .modal-body{
    overflow:auto;
}

@media(max-width:991.98px){
    .sa-icon-grid{
        grid-template-columns:repeat(4,minmax(0,1fr));
    }
}

@media(max-width:767.98px){
    .sa-toolbar{
        grid-template-columns:1fr;
    }

    .sa-icon-grid{
        grid-template-columns:repeat(3,minmax(0,1fr));
    }
}

@media(max-width:575.98px){
    .sa-icon-grid{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }

    .sa-icon-field{
        grid-template-columns:42px minmax(0,1fr);
    }

    .sa-icon-field .sa-icon-button{
        grid-column:1/-1;
        width:100%;
    }
}
</style>
<div class="sa-options">
    <div class="page-heading">
        <div><h1 class="page-title">Super Admin Sidebar Options</h1><p class="page-subtitle">Manage only Super Admin menu visibility, titles, icons and order.</p></div>
        <div class="page-actions">
            <button id="addBtn" class="btn-ui" type="button"><i data-lucide="plus-circle"></i>Add Sidebar Option</button>
            <button id="repairBtn" class="btn-ui" type="button"><i data-lucide="wrench"></i>Repair Consistency</button>
            <button id="resetBtn" class="btn-ui" type="button"><i data-lucide="rotate-ccw"></i>Reset</button>
            <button id="saveBtn" class="btn-ui btn-primary-ui" type="button"><i data-lucide="save"></i>Save Changes</button>
        </div>
    </div>
    <div id="message" class="alert sa-message"></div>
    <section class="ui-card">
        <div class="sa-toolbar">
            <div class="sa-field"><label for="roleSelect">Super Admin Role</label><select id="roleSelect" class="form-select"><option>Loading...</option></select></div>
            <div class="sa-field"><label for="searchInput">Search menus</label><input id="searchInput" class="form-control" type="search" placeholder="Search title, key or route"></div>
        </div>
        <div class="table-responsive"><table class="data-table sa-table">
            <thead><tr><th>Menu</th><th>Parent</th><th>Route</th><th>Role Access</th><th>Visible</th><th>Active</th><th>Menu Title</th><th>Lucide Icon</th><th>Order</th><th>Action</th></tr></thead>
            <tbody id="itemsBody"><tr><td colspan="10" class="sa-empty">Loading Super Admin sidebar options...</td></tr></tbody>
        </table></div>
    </section>
</div>

<div class="modal fade" id="addSidebarModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="addSidebarForm" novalidate>
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Add Sidebar Option</h5>
                        <small class="text-muted">Create a new database-driven Super Admin sidebar menu.</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="newMenuTitle">Menu Title</label>
                            <input id="newMenuTitle" name="menu_title" class="form-control" maxlength="120" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="newMenuKey">Menu Key</label>
                            <input id="newMenuKey" name="menu_key" class="form-control" maxlength="100" placeholder="Auto generated" required>
                            <div class="form-text">Lowercase letters, numbers and underscores only.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="newParentId">Parent Menu</label>
                            <select id="newParentId" name="parent_id" class="form-select">
                                <option value="">Top-level menu</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="newIcon">Menu Icon</label>
                            <div class="sa-icon-field">
                                <span id="newIconPreview" class="sa-icon-preview"></span>
                                <input id="newIcon" name="icon" class="form-control" maxlength="80" value="circle" readonly>
                                <button id="chooseNewIconBtn" class="sa-icon-button" type="button" title="Choose icon">
                                    <i data-lucide="search"></i>
                                </button>
                            </div>
                            <small class="sa-icon-help">Choose from all icons available in the currently loaded Lucide library.</small>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label fw-semibold" for="newRoute">Route</label>
                            <input id="newRoute" name="route" class="form-control" maxlength="255" placeholder="super-admin/example.php or #">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="newOrder">Display Order</label>
                            <input id="newOrder" name="display_order" class="form-control" type="number" min="0" max="9999" value="100">
                        </div>

                        <div class="col-md-4">
                            <div class="form-check form-switch mt-4 pt-2">
                                <input id="newRoleAccess" name="can_show" class="form-check-input" type="checkbox" checked>
                                <label class="form-check-label" for="newRoleAccess">Role Access</label>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-check form-switch mt-4 pt-2">
                                <input id="newVisible" name="is_visible" class="form-check-input" type="checkbox" checked>
                                <label class="form-check-label" for="newVisible">Visible</label>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-check form-switch mt-4 pt-2">
                                <input id="newActive" name="is_active" class="form-check-input" type="checkbox" checked>
                                <label class="form-check-label" for="newActive">Active</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-ui" data-bs-dismiss="modal">Cancel</button>
                    <button id="createSidebarBtn" type="submit" class="btn-ui btn-primary-ui">
                        <i data-lucide="plus-circle"></i>
                        Create Sidebar Option
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editSidebarModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="editSidebarForm" novalidate>
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Edit Sidebar Option</h5>
                        <small class="text-muted">Update the selected Super Admin sidebar item.</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <input id="editSidebarId" type="hidden">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="editMenuTitle">Menu Title</label>
                            <input id="editMenuTitle" class="form-control" maxlength="120" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="editMenuKey">Menu Key</label>
                            <input id="editMenuKey" class="form-control" readonly>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="editParentId">Parent Menu</label>
                            <select id="editParentId" class="form-select">
                                <option value="">Top-level menu</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="editIcon">Menu Icon</label>
                            <div class="sa-icon-field">
                                <span id="editIconPreview" class="sa-icon-preview"></span>
                                <input id="editIcon" class="form-control" maxlength="80" value="circle" readonly required>
                                <button id="chooseEditIconBtn" class="sa-icon-button" type="button" title="Choose icon">
                                    <i data-lucide="search"></i>
                                </button>
                            </div>
                            <small class="sa-icon-help">Choose from all icons available in the currently loaded Lucide library.</small>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label fw-semibold" for="editRoute">Route</label>
                            <input id="editRoute" class="form-control" maxlength="255" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="editOrder">Display Order</label>
                            <input id="editOrder" class="form-control" type="number" min="0" max="9999" required>
                        </div>

                        <div class="col-md-4">
                            <div class="form-check form-switch mt-4 pt-2">
                                <input id="editCanShow" class="form-check-input" type="checkbox">
                                <label class="form-check-label" for="editCanShow">Role Access</label>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-check form-switch mt-4 pt-2">
                                <input id="editVisible" class="form-check-input" type="checkbox">
                                <label class="form-check-label" for="editVisible">Visible</label>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-check form-switch mt-4 pt-2">
                                <input id="editActive" class="form-check-input" type="checkbox">
                                <label class="form-check-label" for="editActive">Active</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-ui" data-bs-dismiss="modal">Cancel</button>
                    <button id="applySidebarEditBtn" type="submit" class="btn-ui btn-primary-ui">
                        <i data-lucide="save"></i>
                        Update Sidebar Option
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<div class="modal fade" id="iconPickerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Choose Menu Icon</h5>
                    <small class="text-muted">Search and select any icon available in the currently loaded Lucide library.</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="sa-icon-picker">
                    <div class="sa-icon-search">
                        <input id="iconPickerSearch" class="form-control" type="search" placeholder="Search icons: school, users, settings, report...">
                    </div>
                    <div id="iconPickerGrid" class="sa-icon-grid"></div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-ui" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
'use strict';

const apiUrl = new URL(
    <?= json_encode(
        $baseUrl . 'api/super-admin-sidebar-settings.php',
        JSON_UNESCAPED_SLASHES
    ) ?>,
    window.location.href
).href;

let csrf = <?= json_encode(
    $csrfToken,
    JSON_UNESCAPED_SLASHES
) ?>;

let items = [];
let activeIconTarget = null;

const fallbackIconCatalog = Object.freeze([
    'circle','layout-dashboard','school','graduation-cap','book-open',
    'library','users','users-round','user-round','user-plus',
    'contact','building-2','landmark','network','layers',
    'folder','folder-open','files','file-text','calendar',
    'calendar-days','clock','history','indian-rupee','wallet',
    'credit-card','receipt','chart-bar','chart-line','chart-pie',
    'settings','settings-2','shield','shield-check','key-round',
    'bell','mail','message-circle','map-pin','navigation',
    'bus','car','route','package','boxes','store',
    'briefcase-business','badge-check','award','trophy','star',
    'image','camera','upload','download','printer','save',
    'plus','plus-circle','square-pen','pencil','trash-2',
    'refresh-cw','rotate-ccw','wrench','circle-help','info',
    'triangle-alert','circle-check','circle-x','eye','eye-off',
    'search','filter','menu','panel-left','home'
]);

let cachedLucideIconCatalog = null;

function lucideKeyToKebab(value) {
    return String(value || '')
        .replace(/Icon$/, '')
        .replace(/([A-Z]+)([A-Z][a-z])/g, '$1-$2')
        .replace(/([a-z0-9])([A-Z])/g, '$1-$2')
        .replace(/[_\s]+/g, '-')
        .replace(/[^A-Za-z0-9-]+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-+|-+$/g, '')
        .toLowerCase();
}

function getLucideIconCatalog(force = false) {
    if (cachedLucideIconCatalog && !force) {
        return cachedLucideIconCatalog;
    }

    const names = new Set(fallbackIconCatalog);

    const lucideIcons =
        window.lucide
        && window.lucide.icons
        && typeof window.lucide.icons === 'object'
            ? window.lucide.icons
            : {};

    Object.keys(lucideIcons).forEach(key => {
        const iconName = lucideKeyToKebab(key);

        if (
            iconName
            && /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(iconName)
        ) {
            names.add(iconName);
        }
    });

    cachedLucideIconCatalog = Array.from(names)
        .sort((a, b) => a.localeCompare(b));

    return cachedLucideIconCatalog;
}

const $ = id => document.getElementById(id);

const role = $('roleSelect');
const body = $('itemsBody');
const search = $('searchInput');
const save = $('saveBtn');
const reset = $('resetBtn');
const repair = $('repairBtn');
const add = $('addBtn');
const msg = $('message');
const addForm = $('addSidebarForm');
const parentSelect = $('newParentId');
const titleInput = $('newMenuTitle');
const keyInput = $('newMenuKey');
const createButton = $('createSidebarBtn');
const editForm = $('editSidebarForm');
const editId = $('editSidebarId');
const editParent = $('editParentId');
const editTitle = $('editMenuTitle');
const editKey = $('editMenuKey');
const editRoute = $('editRoute');
const editIcon = $('editIcon');
const editOrder = $('editOrder');
const editCanShow = $('editCanShow');
const editVisible = $('editVisible');
const editActive = $('editActive');
const editSaveButton = $('applySidebarEditBtn');
const newIcon = $('newIcon');
const newIconPreview = $('newIconPreview');
const editIconPreview = $('editIconPreview');
const iconPickerSearch = $('iconPickerSearch');
const iconPickerGrid = $('iconPickerGrid');

const esc = value => String(value ?? '')
    .replaceAll('&','&amp;')
    .replaceAll('<','&lt;')
    .replaceAll('>','&gt;')
    .replaceAll('"','&quot;')
    .replaceAll("'",'&#039;');

function message(text, success) {
    msg.className =
        'alert sa-message show ' +
        (success ? 'alert-success' : 'alert-danger');
    msg.textContent = text;
}

function busy(value) {
    role.disabled = value;
    save.disabled = value;
    reset.disabled = value;
    repair.disabled = value;
    add.disabled = value;
}

function modalController(id) {
    return {
        show() {
            const element = $(id);

            if (
                !element
                || !window.bootstrap
                || !window.bootstrap.Modal
            ) {
                message(
                    'Bootstrap JavaScript is not loaded. Check layout-end.php.',
                    false
                );
                return;
            }

            window.bootstrap.Modal
                .getOrCreateInstance(element)
                .show();
        },

        hide() {
            const element = $(id);

            if (
                element
                && window.bootstrap
                && window.bootstrap.Modal
            ) {
                window.bootstrap.Modal
                    .getOrCreateInstance(element)
                    .hide();
            }
        }
    };
}

const addModal = modalController('addSidebarModal');
const editModal = modalController('editSidebarModal');
const iconPickerModal = modalController('iconPickerModal');

function normalizeIcon(value) {
    const icon = String(value || '')
        .trim()
        .replace(/([a-z0-9])([A-Z])/g, '$1-$2')
        .toLowerCase()
        .replace(/[^a-z0-9-]+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-+|-+$/g, '');

    return icon || 'circle';
}

function iconMarkup(iconName) {
    const icon = normalizeIcon(iconName);
    return `<i data-lucide="${esc(icon)}"></i>`;
}

function refreshLucide(scope = document) {
    if (!window.lucide?.createIcons) {
        return;
    }

    window.lucide.createIcons({
        attrs: {
            'stroke-width': 1.9
        },
        nameAttr: 'data-lucide'
    });
}

function setIconPreview(element, iconName) {
    if (!element) return;

    const normalized = normalizeIcon(iconName);

    element.innerHTML = iconMarkup(normalized);
    element.dataset.icon = normalized;

    refreshLucide(element);
}

function openIconPicker(targetInput) {
    activeIconTarget = targetInput;
    iconPickerSearch.value = '';

    cachedLucideIconCatalog = null;
    renderIconPicker('');
    iconPickerModal.show();

    setTimeout(() => {
        iconPickerSearch.focus();
    }, 180);
}

function renderIconPicker(query = '') {
    const normalizedQuery = String(query || '')
        .trim()
        .toLowerCase();

    const selected = normalizeIcon(
        activeIconTarget?.value || 'circle'
    );

    const allIcons = getLucideIconCatalog();

    const icons = normalizedQuery === ''
        ? allIcons
        : allIcons.filter(icon =>
            icon.includes(normalizedQuery)
        );

    iconPickerGrid.innerHTML = icons.length
        ? icons.map(icon => `
            <button
                class="sa-icon-option ${icon === selected ? 'active' : ''}"
                type="button"
                data-icon="${esc(icon)}"
                title="${esc(icon)}"
            >
                ${iconMarkup(icon)}
                <span>${esc(icon)}</span>
            </button>
        `).join('')
        : '<div class="sa-icon-empty">No matching Lucide icons found.</div>';

    refreshLucide(iconPickerGrid);
}

function chooseIcon(iconName) {
    if (!activeIconTarget) return;

    const icon = normalizeIcon(iconName);
    activeIconTarget.value = icon;

    if (activeIconTarget === newIcon) {
        setIconPreview(newIconPreview, icon);
    } else if (activeIconTarget === editIcon) {
        setIconPreview(editIconPreview, icon);
    } else {
        const row = activeIconTarget.closest('tr[data-id]');
        const preview = row?.querySelector('.sa-live-row-icon .sa-icon');

        if (preview) {
            preview.innerHTML = iconMarkup(icon);
            refreshLucide(preview);
        }
    }

    activeIconTarget.dispatchEvent(
        new Event('change', {bubbles:true})
    );

    iconPickerModal.hide();
}

const slug = value => String(value || '')
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g,'_')
    .replace(/^_+|_+$/g,'')
    .slice(0,100);

function childrenMap() {
    const map = new Map();

    items.forEach(item => {
        const parentId = Number(item.parent_id || 0);

        if (!map.has(parentId)) {
            map.set(parentId, []);
        }

        map.get(parentId).push(item);
    });

    map.forEach(list => list.sort((a,b) => {
        const firstOrder = Number(
            a.display_order ?? a.default_order ?? 0
        );

        const secondOrder = Number(
            b.display_order ?? b.default_order ?? 0
        );

        return firstOrder - secondOrder
            || String(
                a.effective_title
                || a.menu_title
                || ''
            ).localeCompare(
                String(
                    b.effective_title
                    || b.menu_title
                    || ''
                )
            )
            || Number(a.sidebar_item_id)
                - Number(b.sidebar_item_id);
    }));

    return map;
}

function treeItems() {
    const map = childrenMap();
    const ordered = [];
    const visited = new Set();

    function walk(parentId, depthValue) {
        (map.get(parentId) || []).forEach(item => {
            const id = Number(item.sidebar_item_id);

            if (visited.has(id)) return;

            visited.add(id);
            ordered.push({
                ...item,
                _depth: depthValue
            });

            walk(id, depthValue + 1);
        });
    }

    walk(0, 0);

    items.forEach(item => {
        const id = Number(item.sidebar_item_id);

        if (!visited.has(id)) {
            ordered.push({
                ...item,
                _depth: 0,
                _orphan: true
            });
        }
    });

    return ordered;
}

function descendantIds(itemId) {
    const map = childrenMap();
    const result = new Set();
    const stack = [Number(itemId)];

    while (stack.length) {
        const current = stack.pop();

        (map.get(current) || []).forEach(child => {
            const childId = Number(child.sidebar_item_id);

            if (!result.has(childId)) {
                result.add(childId);
                stack.push(childId);
            }
        });
    }

    return result;
}

function renderParents() {
    const selected = parentSelect.value;

    parentSelect.innerHTML =
        '<option value="">Top-level menu</option>';

    treeItems().forEach(item => {
        const option = document.createElement('option');

        option.value = String(item.sidebar_item_id);

        option.textContent =
            '— '.repeat(Number(item._depth || 0))
            + String(
                item.effective_title
                || item.menu_title
            )
            + ' ('
            + String(item.menu_key)
            + ')';

        parentSelect.appendChild(option);
    });

    parentSelect.value = selected;
}

function renderRoles(roles, selectedId) {
    role.innerHTML = '';

    roles.forEach(roleItem => {
        const option = document.createElement('option');

        option.value = roleItem.id;
        option.textContent =
            roleItem.role_name
            + ' ('
            + roleItem.role_key
            + ')';

        option.selected =
            Number(roleItem.id) === Number(selectedId);

        role.appendChild(option);
    });
}

function parentOptions(item) {
    const ownId = Number(item.sidebar_item_id);
    const excluded = descendantIds(ownId);

    const options = [
        '<option value="">Top-level menu</option>'
    ];

    treeItems().forEach(candidate => {
        const candidateId = Number(
            candidate.sidebar_item_id
        );

        if (
            candidateId === ownId
            || excluded.has(candidateId)
        ) {
            return;
        }

        const selected =
            Number(item.parent_id || 0) === candidateId
                ? ' selected'
                : '';

        const indent =
            '— '.repeat(Number(candidate._depth || 0));

        options.push(
            `<option value="${candidateId}"${selected}>`
            + `${esc(indent)}${esc(
                candidate.effective_title
                || candidate.menu_title
            )}`
            + ` (${esc(candidate.menu_key)})</option>`
        );
    });

    return options.join('');
}

function render() {
    renderParents();

    const query = search.value.trim().toLowerCase();

    const list = treeItems().filter(item => [
        item.effective_title,
        item.menu_title,
        item.menu_key,
        item.route,
        item.effective_icon,
        item.icon
    ].join(' ').toLowerCase().includes(query));

    body.innerHTML = list.length
        ? list.map(item => {
            const depthValue = Number(item._depth || 0);

            const title = String(
                item.effective_title
                || item.custom_title
                || item.menu_title
                || 'Menu'
            );

            const icon = normalizeIcon(
                item.effective_icon
                || item.custom_icon
                || item.icon
                || 'circle'
            );

            const order = Number(
                item.display_order
                ?? item.default_order
                ?? 0
            );

            return `<tr data-id="${Number(item.sidebar_item_id)}">
                <td class="sa-menu">
                    <div class="sa-menu-wrap ${depthValue ? 'child' : ''}"
                         style="padding-left:${depthValue * 18}px">
                        <span class="sa-icon">
                            ${iconMarkup(icon)}
                        </span>
                        <span class="sa-copy">
                            <strong>${esc(title)}</strong>
                            <small>${esc(item.menu_key)}</small>
                        </span>
                    </div>
                </td>

                <td>
                    <select class="form-select form-select-sm sa-parent parent">
                        ${parentOptions(item)}
                    </select>
                </td>

                <td>
                    <input
                        class="form-control form-control-sm sa-route route"
                        maxlength="255"
                        value="${esc(item.route || '#')}"
                    >
                </td>

                <td>
                    <div class="form-check form-switch">
                        <input class="form-check-input can"
                               type="checkbox"
                               ${Number(item.can_show) === 1 ? 'checked' : ''}>
                    </div>
                </td>

                <td>
                    <div class="form-check form-switch">
                        <input class="form-check-input visible"
                               type="checkbox"
                               ${Number(item.is_visible) === 1 ? 'checked' : ''}>
                    </div>
                </td>

                <td>
                    <div class="form-check form-switch">
                        <input class="form-check-input active"
                               type="checkbox"
                               ${Number(item.is_active) === 1 ? 'checked' : ''}>
                    </div>
                </td>

                <td>
                    <input
                        class="form-control form-control-sm title"
                        maxlength="120"
                        value="${esc(title)}"
                    >
                </td>

                <td>
                    <div class="sa-live-row-icon">
                        <span class="sa-icon">${iconMarkup(icon)}</span>
                        <input
                            class="form-control form-control-sm icon"
                            maxlength="80"
                            value="${esc(icon)}"
                            readonly
                        >
                        <button
                            class="sa-icon-button choose-row-icon"
                            type="button"
                            title="Choose icon"
                        >
                            <i data-lucide="search"></i>
                        </button>
                    </div>
                </td>

                <td>
                    <input
                        class="form-control form-control-sm sa-order order"
                        type="number"
                        min="0"
                        max="9999"
                        value="${order}"
                    >
                </td>

                <td>
                    <div class="sa-actions">
                        <button
                            class="sa-edit edit-item"
                            type="button"
                            title="Edit sidebar item"
                            data-id="${Number(item.sidebar_item_id)}"
                        >
                            <i data-lucide="square-pen"></i>
                        </button>

                        <button
                            class="sa-delete delete-item"
                            type="button"
                            title="Delete sidebar item"
                            data-id="${Number(item.sidebar_item_id)}"
                            data-title="${esc(title)}"
                        >
                            <i data-lucide="trash-2"></i>
                        </button>
                    </div>
                </td>
            </tr>`;
        }).join('')
        : '<tr><td colspan="10" class="sa-empty">No matching menus found.</td></tr>';

    refreshLucide(body);
}

function sync() {
    body.querySelectorAll('tr[data-id]').forEach(row => {
        const item = items.find(value =>
            Number(value.sidebar_item_id)
            === Number(row.dataset.id)
        );

        if (!item) return;

        const parentValue =
            row.querySelector('.parent').value;

        const title =
            row.querySelector('.title').value.trim();

        const icon = normalizeIcon(
            row.querySelector('.icon').value
        );

        item.parent_id =
            parentValue === ''
                ? null
                : Number(parentValue);

        item.route =
            row.querySelector('.route').value.trim()
            || '#';

        item.can_show =
            row.querySelector('.can').checked ? 1 : 0;

        item.is_visible =
            row.querySelector('.visible').checked ? 1 : 0;

        item.is_active =
            row.querySelector('.active').checked ? 1 : 0;

        item.menu_title = title;
        item.effective_title = title;
        item.custom_title = '';

        item.icon = icon;
        item.effective_icon = icon;
        item.custom_icon = '';

        item.display_order = Number(
            row.querySelector('.order').value || 0
        );
    });
}

async function readJson(response) {
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

async function load(roleId = '') {
    busy(true);

    try {
        const url = new URL(apiUrl, window.location.href);

        if (roleId) {
            url.searchParams.set('role_id', roleId);
        }

        const response = await fetch(url.href, {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const result = await readJson(response);

        if (!response.ok || !result.success) {
            throw new Error(
                result.message || 'Unable to load sidebar options.'
            );
        }

        csrf = result.data.csrf_token || csrf;
        items = Array.isArray(result.data.items)
            ? result.data.items
            : [];

        renderRoles(
            result.data.roles || [],
            result.data.role_id
        );

        render();
    } catch (error) {
        message(
            error.message || 'Unable to load sidebar options.',
            false
        );
    } finally {
        busy(false);
    }
}

async function post(action) {
    sync();
    busy(true);

    try {
        const response = await fetch(apiUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                action,
                role_id: Number(role.value),
                csrf_token: csrf,
                items: action === 'save' ? items : []
            })
        });

        const result = await readJson(response);

        if (!response.ok || !result.success) {
            throw new Error(
                result.message || 'Unable to save sidebar options.'
            );
        }

        message(result.message, true);
        await load(role.value);
    } catch (error) {
        message(
            error.message || 'Unable to save sidebar options.',
            false
        );
    } finally {
        busy(false);
    }
}

function renderEditParents(item) {
    editParent.innerHTML =
        '<option value="">Top-level menu</option>';

    const ownId = Number(item.sidebar_item_id);
    const excluded = descendantIds(ownId);

    treeItems().forEach(candidate => {
        const candidateId = Number(
            candidate.sidebar_item_id
        );

        if (
            candidateId === ownId
            || excluded.has(candidateId)
        ) {
            return;
        }

        const option = document.createElement('option');

        option.value = String(candidateId);

        option.textContent =
            '— '.repeat(Number(candidate._depth || 0))
            + String(
                candidate.effective_title
                || candidate.menu_title
            )
            + ' ('
            + String(candidate.menu_key)
            + ')';

        editParent.appendChild(option);
    });

    editParent.value =
        item.parent_id === null
        || item.parent_id === ''
            ? ''
            : String(item.parent_id);
}

function openEditSidebar(id) {
    sync();

    const item = items.find(value =>
        Number(value.sidebar_item_id) === Number(id)
    );

    if (!item) {
        message('Sidebar item was not found.', false);
        return;
    }

    renderEditParents(item);

    editId.value = String(item.sidebar_item_id);

    editTitle.value = String(
        item.effective_title
        || item.menu_title
        || ''
    );

    editKey.value = String(item.menu_key || '');
    editRoute.value = String(item.route || '#');

    editIcon.value = normalizeIcon(
        item.effective_icon
        || item.icon
        || 'circle'
    );

    setIconPreview(
        editIconPreview,
        editIcon.value
    );

    editOrder.value = String(
        Number(
            item.display_order
            ?? item.default_order
            ?? 0
        )
    );

    editCanShow.checked =
        Number(item.can_show) === 1;

    editVisible.checked =
        Number(item.is_visible) === 1;

    editActive.checked =
        Number(item.is_active) === 1;

    editModal.show();
}

async function applySidebarEdit() {
    const item = items.find(value =>
        Number(value.sidebar_item_id)
        === Number(editId.value)
    );

    if (!item) {
        message('Sidebar item was not found.', false);
        return;
    }

    const title = editTitle.value.trim();
    const routeValue = editRoute.value.trim() || '#';
    const icon = normalizeIcon(editIcon.value);
    const parentId =
        editParent.value === ''
            ? null
            : Number(editParent.value);

    if (!title) {
        message('Menu Title is required.', false);
        editTitle.focus();
        return;
    }

    editSaveButton.disabled = true;

    try {
        const response = await fetch(apiUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                action: 'update_item',
                role_id: Number(role.value),
                csrf_token: csrf,
                sidebar_item_id: Number(item.sidebar_item_id),
                parent_id: parentId,
                menu_title: title,
                route: routeValue,
                icon,
                display_order: Number(editOrder.value || 0),
                can_show: editCanShow.checked ? 1 : 0,
                is_visible: editVisible.checked ? 1 : 0,
                is_active: editActive.checked ? 1 : 0
            })
        });

        const result = await readJson(response);

        if (!response.ok || !result.success) {
            throw new Error(
                result.message
                || 'Unable to update sidebar option.'
            );
        }

        if (result.data?.csrf_token) {
            csrf = result.data.csrf_token;
        }

        editModal.hide();

        message(
            result.message
            || 'Sidebar option updated successfully.',
            true
        );

        await load(role.value);
    } catch (error) {
        message(
            error.message
            || 'Unable to update sidebar option.',
            false
        );
    } finally {
        editSaveButton.disabled = false;
    }
}
async function deleteSidebarOption(id, title) {
    if (!window.confirm(
        `Delete "${title}" and all of its child sidebar items?`
    )) {
        return;
    }

    busy(true);

    try {
        const response = await fetch(apiUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                action: 'delete_item',
                role_id: Number(role.value),
                sidebar_item_id: Number(id),
                csrf_token: csrf
            })
        });

        const result = await readJson(response);

        if (!response.ok || !result.success) {
            throw new Error(
                result.message
                || 'Unable to delete sidebar option.'
            );
        }

        message(result.message, true);
        await load(role.value);
    } catch (error) {
        message(
            error.message
            || 'Unable to delete sidebar option.',
            false
        );
    } finally {
        busy(false);
    }
}

async function repairConsistency() {
    if (!window.confirm(
        'Repair sidebar labels, icons, hierarchy, status and order?'
    )) {
        return;
    }

    await post('repair_consistency');
}

async function createSidebarOption() {
    createButton.disabled = true;

    try {
        const data = {
            action: 'create_item',
            role_id: Number(role.value),
            csrf_token: csrf,
            menu_title: titleInput.value.trim(),
            menu_key: keyInput.value.trim(),
            parent_id:
                parentSelect.value === ''
                    ? null
                    : Number(parentSelect.value),
            route: $('newRoute').value.trim(),
            icon: normalizeIcon(newIcon.value),
            display_order:
                Number($('newOrder').value || 0),
            can_show:
                $('newRoleAccess').checked ? 1 : 0,
            is_visible:
                $('newVisible').checked ? 1 : 0,
            is_active:
                $('newActive').checked ? 1 : 0
        };

        const response = await fetch(apiUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(data)
        });

        const result = await readJson(response);

        if (!response.ok || !result.success) {
            throw new Error(
                result.message
                || 'Unable to create sidebar option.'
            );
        }

        message(result.message, true);

        addForm.reset();
        newIcon.value = 'circle';
        setIconPreview(newIconPreview, 'circle');

        $('newOrder').value = '100';
        $('newRoleAccess').checked = true;
        $('newVisible').checked = true;
        $('newActive').checked = true;

        addModal.hide();
        await load(role.value);
    } catch (error) {
        message(
            error.message
            || 'Unable to create sidebar option.',
            false
        );
    } finally {
        createButton.disabled = false;
    }
}

body.addEventListener('click', event => {
    const iconButton =
        event.target.closest('.choose-row-icon');

    if (iconButton) {
        const row = iconButton.closest('tr[data-id]');
        const input = row?.querySelector('.icon');

        if (input) {
            openIconPicker(input);
        }

        return;
    }

    const editButton =
        event.target.closest('.edit-item');

    if (editButton) {
        openEditSidebar(
            Number(editButton.dataset.id)
        );
        return;
    }

    const deleteButton =
        event.target.closest('.delete-item');

    if (!deleteButton) {
        return;
    }

    sync();

    deleteSidebarOption(
        Number(deleteButton.dataset.id),
        deleteButton.dataset.title
        || 'this sidebar item'
    );
});

iconPickerGrid.addEventListener('click', event => {
    const option = event.target.closest('[data-icon]');

    if (!option) return;

    chooseIcon(option.dataset.icon);
});

iconPickerSearch.addEventListener('input', () => {
    renderIconPicker(iconPickerSearch.value);
});

$('chooseNewIconBtn').addEventListener('click', () => {
    openIconPicker(newIcon);
});

$('chooseEditIconBtn').addEventListener('click', () => {
    openIconPicker(editIcon);
});

role.addEventListener('change', () => {
    load(role.value);
});

search.addEventListener('input', () => {
    sync();
    render();
});

save.addEventListener('click', () => {
    post('save');
});

repair.addEventListener('click', repairConsistency);

reset.addEventListener('click', () => {
    if (window.confirm(
        'Reset Super Admin sidebar options?'
    )) {
        post('reset_role');
    }
});

add.addEventListener('click', () => {
    renderParents();
    newIcon.value = normalizeIcon(newIcon.value);
    setIconPreview(newIconPreview, newIcon.value);
    addModal.show();
});

titleInput.addEventListener('input', () => {
    if (!keyInput.dataset.manual) {
        keyInput.value = slug(titleInput.value);
    }
});

keyInput.addEventListener('input', () => {
    keyInput.dataset.manual =
        keyInput.value.trim() !== '' ? '1' : '';
});

addForm.addEventListener('submit', event => {
    event.preventDefault();

    if (!addForm.checkValidity()) {
        addForm.classList.add('was-validated');
        return;
    }

    createSidebarOption();
});

editForm.addEventListener('submit', async event => {
    event.preventDefault();

    if (!editForm.checkValidity()) {
        editForm.classList.add('was-validated');
        return;
    }

    await applySidebarEdit();
});

function initializePage() {
    setIconPreview(newIconPreview, newIcon.value);
    setIconPreview(editIconPreview, editIcon.value);
    refreshLucide(document);
    load();
}

if (document.readyState === 'loading') {
    document.addEventListener(
        'DOMContentLoaded',
        initializePage,
        {once:true}
    );
} else {
    initializePage();
}
})();
</script>
<?php require dirname(__DIR__) . '/includes/layout-end.php'; ?>

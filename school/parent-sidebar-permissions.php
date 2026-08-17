<?php
declare(strict_types=1);

/* School Admin Parent Sidebar Permission - Build 2026-08-13-total-parents-card-ui-v26 */

$pageTitle = 'Parent Sidebar Permission';
$pageKey = 'parent_sidebar_permissions';

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/permission-chain.php';

$currentUser = function_exists('current_user') ? current_user() : [];
$currentRole = pc_role_key((string)($currentUser['role_key'] ?? $_SESSION['role_key'] ?? ''));

if ($currentRole !== 'school_admin') {
    http_response_code(403);
    exit('Only School Administrator can manage Parent Sidebar Permission.');
}

/*
 * Total Parent accounts for this school.
 * Count the existing Parent-role users only; this page never creates or copies
 * Parent/student records. A safe fallback keeps the permission page usable if
 * an older installation is missing one of these tables/columns.
 */
$tenantId = (int)(
    $currentUser['tenant_id']
    ?? $currentUser['school_id']
    ?? $_SESSION['tenant_id']
    ?? $_SESSION['school_id']
    ?? 0
);
$totalParentAccounts = 0;

if ($tenantId > 0 && isset($pdo) && $pdo instanceof PDO) {
    try {
        $parentCountStmt = $pdo->prepare(
            "SELECT COUNT(DISTINCT u.id)
             FROM users u
             INNER JOIN roles r
                ON r.id = u.role_id
               AND r.tenant_id = u.tenant_id
             WHERE u.tenant_id = :tenant_id
               AND r.role_key = 'parent'
               AND r.status = 'active'
               AND u.status = 'active'"
        );
        $parentCountStmt->execute(['tenant_id' => $tenantId]);
        $totalParentAccounts = (int)$parentCountStmt->fetchColumn();
    } catch (Throwable $exception) {
        $totalParentAccounts = 0;
    }
}

require_once dirname(__DIR__) . '/includes/layout-start.php';
?>
<style>
/* =========================================================
   PARENT SIDEBAR PERMISSION
   Easy Enable UI + Dashboard-style statistic cards
   ========================================================= */
.psp-page{
    display:grid;
    gap:16px;
}

.psp-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:14px;
    flex-wrap:wrap;
}

.psp-head .page-actions{
    display:flex;
    gap:10px;
    align-items:center;
    flex-wrap:wrap;
}

/* Dashboard-style statistic cards */
.psp-summary{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:14px;
}

.psp-stat-card{
    position:relative;
    min-width:0;
    min-height:124px;
    padding:20px 22px;
    display:flex;
    align-items:center;
    gap:16px;
    overflow:hidden;
    color:#fff;
    border:0;
    border-radius:15px;
    box-shadow:0 10px 24px rgba(15,23,42,.08);
    isolation:isolate;
}

.psp-stat-card::before{
    content:"";
    position:absolute;
    z-index:-1;
    width:132px;
    height:132px;
    right:-42px;
    top:-55px;
    border-radius:50%;
    background:rgba(255,255,255,.085);
}

.psp-stat-card::after{
    content:"";
    position:absolute;
    z-index:-1;
    width:52px;
    height:52px;
    right:14px;
    bottom:-31px;
    border-radius:50%;
    background:rgba(255,255,255,.045);
}

.psp-purple{background:linear-gradient(135deg,#7548ee 0%,#5033d5 100%)}
.psp-green{background:linear-gradient(135deg,#43c987 0%,#20aa6f 100%)}
.psp-pink{background:linear-gradient(135deg,#ff4c78 0%,#ef2f62 100%)}
.psp-orange{background:linear-gradient(135deg,#ffb22a 0%,#ff8b19 100%)}

.psp-stat-icon{
    width:54px;
    height:54px;
    flex:0 0 54px;
    display:grid;
    place-items:center;
    color:#fff;
    background:rgba(255,255,255,.17);
    border:1px solid rgba(255,255,255,.09);
    border-radius:50%;
}

.psp-stat-icon svg{
    width:27px;
    height:27px;
    stroke-width:1.9;
}

.psp-stat-copy{
    position:relative;
    z-index:1;
    min-width:0;
}

.psp-stat-copy small{
    display:block;
    margin:0 0 5px;
    color:rgba(255,255,255,.94);
    font-size:11px;
    font-weight:700;
    line-height:1.2;
}

.psp-stat-value{
    overflow:hidden;
    color:#fff;
    font-size:34px;
    font-weight:800;
    line-height:1;
    letter-spacing:-.035em;
    text-overflow:ellipsis;
    white-space:nowrap;
}

.psp-stat-note{
    margin-top:8px;
    overflow:hidden;
    color:rgba(255,255,255,.92);
    font-size:9.5px;
    font-weight:650;
    line-height:1.3;
    text-overflow:ellipsis;
    white-space:nowrap;
}

.psp-note{
    padding:12px 14px;
    border:1px solid #dbeafe;
    background:#eff6ff;
    color:#1e40af;
    border-radius:12px;
    font-size:10px;
    line-height:1.55;
}

.psp-card{
    background:var(--card-bg,#fff);
    border:1px solid var(--border-soft,#e7ebf3);
    border-radius:13px;
    overflow:hidden;
    box-shadow:0 5px 18px rgba(15,23,42,.04);
}

.psp-toolbar{
    padding:12px 14px;
    border-bottom:1px solid var(--border-soft,#e7ebf3);
    display:flex;
    gap:10px;
    align-items:center;
    justify-content:space-between;
    flex-wrap:wrap;
}

.psp-search-wrap{
    flex:1 1 360px;
    max-width:520px;
    position:relative;
}

.psp-search-wrap > i,
.psp-search-wrap > svg{
    position:absolute;
    left:12px;
    top:50%;
    width:17px;
    height:17px;
    color:#8b96aa;
    transform:translateY(-50%);
    pointer-events:none;
}

.psp-search-wrap .form-control{
    padding-left:38px;
}

.psp-list{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:12px;
    padding:14px;
    background:#f8fafc;
}

.psp-row{
    min-width:0;
    padding:15px;
    border:1px solid var(--border-soft,#e7ebf3);
    border-radius:12px;
    background:var(--card-bg,#fff);
    box-shadow:0 3px 12px rgba(15,23,42,.035);
    transition:border-color .15s ease,box-shadow .15s ease,transform .15s ease;
}

.psp-row:hover{
    border-color:#d9def0;
    box-shadow:0 7px 18px rgba(15,23,42,.055);
    transform:translateY(-1px);
}

.psp-main{
    display:grid;
    grid-template-columns:minmax(0,1fr) auto;
    gap:14px;
    align-items:center;
}

.psp-menu{
    min-width:0;
    display:flex;
    gap:11px;
    align-items:center;
}

.psp-icon{
    width:42px;
    height:42px;
    flex:0 0 42px;
    border-radius:12px;
    display:grid;
    place-items:center;
    background:#eef2ff;
    color:#4f46e5;
}

.psp-icon svg{
    width:19px;
    height:19px;
}

.psp-menu-copy{
    min-width:0;
}

.psp-menu strong,
.psp-menu small{
    display:block;
}

.psp-menu strong{
    color:var(--text-main,#101a3b);
    font-size:11px;
    font-weight:750;
}

.psp-menu small{
    margin-top:3px;
    overflow:hidden;
    color:#64748b;
    font-size:8.5px;
    text-overflow:ellipsis;
    white-space:nowrap;
}

/* One easy enable control per menu */
.psp-enable{
    display:flex;
    align-items:center;
    gap:10px;
}

.psp-enable-state{
    min-width:58px;
    padding:4px 7px;
    text-align:center;
    color:#64748b;
    background:#f1f5f9;
    border-radius:999px;
    font-size:8.5px;
    font-weight:800;
}

.psp-enable-state.on{
    color:#158458;
    background:#e9f8f1;
}

.psp-switch-control{
    position:relative;
    width:46px;
    height:25px;
    flex:0 0 46px;
}

.psp-switch-control input{
    position:absolute;
    inset:0;
    width:100%;
    height:100%;
    margin:0;
    opacity:0;
    cursor:pointer;
    z-index:2;
}

.psp-switch-track{
    position:absolute;
    inset:0;
    border-radius:999px;
    background:#dfe4ee;
    transition:.18s ease;
}

.psp-switch-track::after{
    content:"";
    position:absolute;
    width:19px;
    height:19px;
    left:3px;
    top:3px;
    border-radius:50%;
    background:#fff;
    box-shadow:0 2px 5px rgba(15,23,42,.16);
    transition:.18s ease;
}

.psp-switch-control input:checked + .psp-switch-track{
    background:linear-gradient(135deg,#6547e8,#315ed8);
}

.psp-switch-control input:checked + .psp-switch-track::after{
    transform:translateX(21px);
}

.psp-switch-control input:disabled{
    cursor:not-allowed;
}

.psp-switch-control input:disabled + .psp-switch-track{
    opacity:.45;
}

.psp-caps{
    display:flex;
    align-items:center;
    flex-wrap:wrap;
    gap:6px;
    margin-top:10px;
    padding-left:51px;
}

.psp-cap-label{
    color:#8a94a8;
    font-size:8px;
    font-weight:700;
}

.psp-cap{
    display:inline-flex;
    align-items:center;
    min-height:22px;
    padding:3px 7px;
    color:#4f46e5;
    background:#eef2ff;
    border-radius:999px;
    font-size:8px;
    font-weight:700;
}

.psp-cap.blocked{
    color:#8a94a8;
    background:#f1f4f8;
    text-decoration:line-through;
}

.psp-empty{
    padding:32px;
    text-align:center;
    color:#64748b;
    font-size:10px;
}

@media(max-width:1199.98px){
    .psp-summary{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }
}

@media(max-width:991.98px){
    .psp-list{
        grid-template-columns:1fr;
    }
}

@media(max-width:760px){
    .psp-summary{
        grid-template-columns:1fr;
    }

    .psp-main{
        grid-template-columns:1fr;
        align-items:start;
    }

    .psp-enable{
        justify-content:flex-start;
        padding-left:51px;
    }

    .psp-enable-state{
        min-width:0;
        text-align:left;
    }

    .psp-toolbar{
        align-items:stretch;
    }

    .psp-search-wrap{
        max-width:none;
        flex-basis:100%;
    }
}

@media(max-width:575.98px){
    .psp-stat-card{
        min-height:108px;
        padding:17px 18px;
    }

    .psp-stat-icon{
        width:48px;
        height:48px;
        flex-basis:48px;
    }

    .psp-stat-icon svg{
        width:24px;
        height:24px;
    }

    .psp-stat-value{
        font-size:28px;
    }

    .psp-caps,
    .psp-enable{
        padding-left:0;
    }
}
</style>

<div class="psp-page">
    <div class="psp-head">
        <div>
            <h1 class="page-title">Parent Sidebar Permission</h1>
            <p class="page-subtitle">Enable the Parent Portal options required for this school.</p>
        </div>

        <div class="page-actions">
            <button id="pspReload" class="btn-ui" type="button">
                <i data-lucide="refresh-cw"></i>
                Refresh
            </button>

            <button id="pspSave" class="btn-ui btn-primary-ui" type="button">
                <i data-lucide="save"></i>
                Save Permission
            </button>
        </div>
    </div>

    <section class="psp-summary">
        <article class="psp-stat-card psp-purple">
            <div class="psp-stat-icon"><i data-lucide="layout-grid"></i></div>
            <div class="psp-stat-copy">
                <small>Total Parent Options</small>
                <div id="pspAvailable" class="psp-stat-value">0</div>
                <div class="psp-stat-note">Allowed by Super Admin</div>
            </div>
        </article>

        <article class="psp-stat-card psp-green">
            <div class="psp-stat-icon"><i data-lucide="circle-check-big"></i></div>
            <div class="psp-stat-copy">
                <small>Enabled Options</small>
                <div id="pspEnabled" class="psp-stat-value">0</div>
                <div class="psp-stat-note">Visible to Parent logins</div>
            </div>
        </article>

        <article class="psp-stat-card psp-pink">
            <div class="psp-stat-icon"><i data-lucide="circle-off"></i></div>
            <div class="psp-stat-copy">
                <small>Disabled Options</small>
                <div id="pspDisabled" class="psp-stat-value">0</div>
                <div class="psp-stat-note">Hidden from Parent logins</div>
            </div>
        </article>

        <article class="psp-stat-card psp-orange">
            <div class="psp-stat-icon"><i data-lucide="users-round"></i></div>
            <div class="psp-stat-copy">
                <small>Total Parents</small>
                <div id="pspParents" class="psp-stat-value"><?= (int)$totalParentAccounts ?></div>
                <div class="psp-stat-note">Active Parent login accounts</div>
            </div>
        </article>
    </section>

    <div class="psp-note">
        <strong>Parent Portal access:</strong>
        Use one switch for each menu. ON shows the menu to Parent logins; OFF hides it.
        The existing Super Admin permission limits are still applied automatically in the background.
    </div>

    <section class="psp-card">
        <div class="psp-toolbar">
            <div class="psp-search-wrap">
                <i data-lucide="search"></i>
                <input
                    id="pspSearch"
                    class="form-control"
                    placeholder="Search Parent option..."
                    autocomplete="off"
                >
            </div>

            <div style="font-size:9px;color:#64748b;font-weight:700">
                Enable only the Parent Portal menus required for this school.
            </div>
        </div>

        <div id="pspList" class="psp-list">
            <div class="psp-empty">Loading...</div>
        </div>
    </section>
</div>

<script>
(function(){
'use strict';

const apiUrl = new URL('../api/parent-sidebar-permissions.php', window.location.href).href;
let csrfToken = '';
let items = [];
let actions = [];

const $ = id => document.getElementById(id);
const esc = value => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

async function request(action, data = {}, method = 'GET') {
    const url = new URL(apiUrl);
    const options = {
        method,
        credentials: 'same-origin',
        headers: {Accept: 'application/json'}
    };

    if (method === 'GET') {
        url.searchParams.set('action', action);
        Object.entries(data).forEach(([key, value]) => {
            url.searchParams.set(key, String(value));
        });
    } else {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify({
            action,
            csrf_token: csrfToken,
            ...data
        });
    }

    const response = await fetch(url, options);
    const text = await response.text();
    let result;

    try {
        result = JSON.parse(text);
    } catch (error) {
        console.error(text);
        throw new Error('Invalid Parent Permission API response.');
    }

    if (result.data?.csrf_token) {
        csrfToken = result.data.csrf_token;
    }

    if (!response.ok || !result.success) {
        throw new Error(result.message || `HTTP ${response.status}`);
    }

    return result;
}

function toast(message, success = true) {
    if (window.SchoolToast?.[success ? 'success' : 'error']) {
        window.SchoolToast[success ? 'success' : 'error'](message);
        return;
    }

    alert(message);
}

function actionLabel(actionKey) {
    const match = actions.find(row => String(row.action_key) === String(actionKey));
    return match?.action_name || String(actionKey).replaceAll('_', ' ');
}

function itemCanView(item) {
    if (Object.prototype.hasOwnProperty.call(item.caps || {}, 'view')) {
        return Number(item.caps?.view || 0) === 1;
    }

    return true;
}

function isEnabled(item) {
    return Number(item.permissions?.view || 0) === 1;
}

/*
 * Easy Enable rule:
 * ON  = View + every Super Admin allowed action becomes 1.
 * OFF = Every permission becomes 0.
 */
function applyEnableState(item, enabled) {
    item.permissions = item.permissions || {};

    const keys = new Set([
        'view',
        ...actions.map(row => String(row.action_key || '')).filter(Boolean),
        ...Object.keys(item.permissions || {}),
        ...Object.keys(item.caps || {})
    ]);

    keys.forEach(key => {
        if (!enabled) {
            item.permissions[key] = 0;
            return;
        }

        if (key === 'view') {
            item.permissions[key] = itemCanView(item) ? 1 : 0;
            return;
        }

        item.permissions[key] = Number(item.caps?.[key] || 0) === 1 ? 1 : 0;
    });
}

function summary() {
    const enabledCount = items.filter(isEnabled).length;
    const disabledCount = Math.max(0, items.length - enabledCount);

    $('pspAvailable').textContent = String(items.length);
    $('pspEnabled').textContent = String(enabledCount);
    $('pspDisabled').textContent = String(disabledCount);
}

function render() {
    const query = $('pspSearch').value.trim().toLowerCase();

    const rows = items.filter(item => {
        if (!query) return true;

        return [
            item.menu_title,
            item.menu_key,
            item.route
        ].join(' ').toLowerCase().includes(query);
    });

    if (!rows.length) {
        $('pspList').innerHTML = '<div class="psp-empty">No Parent options found.</div>';
        summary();
        window.lucide?.createIcons();
        return;
    }

    $('pspList').innerHTML = rows.map(item => {
        const id = Number(item.sidebar_item_id || 0);
        const enabled = isEnabled(item);
        const canView = itemCanView(item);

        return `
            <div class="psp-row">
                <div class="psp-main">
                    <div class="psp-menu">
                        <span class="psp-icon">
                            <i data-lucide="${esc(item.icon || 'circle')}"></i>
                        </span>

                        <div class="psp-menu-copy">
                            <strong>${esc(item.menu_title || 'Parent Option')}</strong>
                            <small>${esc(item.menu_key || '')} · ${esc(item.route || '#')}</small>
                        </div>
                    </div>

                    <div class="psp-enable">
                        <span class="psp-enable-state ${enabled ? 'on' : ''}">
                            ${canView ? (enabled ? 'Enabled' : 'Disabled') : 'Blocked'}
                        </span>

                        <label class="psp-switch-control" title="${canView ? 'Enable / Disable this Parent option' : 'Blocked by Super Admin'}">
                            <input
                                class="js-psp-enable"
                                type="checkbox"
                                data-item="${id}"
                                ${enabled ? 'checked' : ''}
                                ${canView ? '' : 'disabled'}
                            >
                            <span class="psp-switch-track"></span>
                        </label>
                    </div>
                </div>

            </div>`;
    }).join('');

    document.querySelectorAll('.js-psp-enable').forEach(toggle => {
        toggle.addEventListener('change', () => {
            const item = items.find(row =>
                Number(row.sidebar_item_id || 0) === Number(toggle.dataset.item || 0)
            );

            if (!item) return;

            applyEnableState(item, toggle.checked);
            render();
        });
    });

    summary();
    window.lucide?.createIcons();
}

async function load() {
    $('pspList').innerHTML = '<div class="psp-empty">Loading...</div>';

    const result = await request('load');
    items = Array.isArray(result.data?.items) ? result.data.items : [];
    actions = Array.isArray(result.data?.actions) ? result.data.actions : [];
    csrfToken = result.data?.csrf_token || csrfToken;

    render();
}

$('pspSave').addEventListener('click', async () => {
    try {
        const result = await request(
            'save',
            {
                items: items.map(item => ({
                    sidebar_item_id: Number(item.sidebar_item_id || 0),
                    permissions: {...(item.permissions || {})}
                }))
            },
            'POST'
        );

        toast(result.message || 'Parent permissions saved successfully.', true);
        await load();
    } catch (error) {
        toast(error.message || 'Unable to save Parent permission.', false);
    }
});

$('pspReload').addEventListener('click', () => {
    load().catch(error => toast(error.message, false));
});

$('pspSearch').addEventListener('input', render);

load().catch(error => {
    $('pspList').innerHTML = `<div class="psp-empty text-danger">${esc(error.message)}</div>`;
    toast(error.message, false);
});
})();
</script>

<?php require_once dirname(__DIR__) . '/includes/layout-end.php'; ?>

<?php
declare(strict_types=1);

$pageTitle = 'Academic Year Management';
$pageKey = 'academic_years';
$sidebarFile = __DIR__ . '/sidebar.php';

/*
 * This screen contains several Academic Year submodules on one PHP page.
 * The old academic_year_management key is disabled in the current sidebar
 * hierarchy, so Bootstrap must evaluate the active child permission keys.
 */
const SCHOOL_PAGE_PERMISSION_KEYS = [
    'academic_years',
    'academic_calendar',
    'terms_semesters',
    'holidays',
];

const SCHOOL_PAGE_KEY_MAP = [
    'academic_years' => 'academic_years',
    'academic_calendar' => 'academic_calendar',
    'terms_semesters' => 'terms_semesters',
    'holidays' => 'holidays',
];

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$currentUser = function_exists('current_user') ? current_user() : [];
$tenantId = (int)($currentUser['tenant_id'] ?? $currentUser['school_id'] ?? $_SESSION['tenant_id'] ?? $_SESSION['school_id'] ?? 0);
$roleId = (int)($currentUser['role_id'] ?? $_SESSION['role_id'] ?? 0);
$userId = (int)($currentUser['id'] ?? $currentUser['user_id'] ?? $_SESSION['user_id'] ?? 0);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (function_exists('csrfToken')) {
    $csrfToken = (string)csrfToken();
} else {
    if (empty($_SESSION['academic_year_csrf_token']) || !is_string($_SESSION['academic_year_csrf_token'])) {
        $_SESSION['academic_year_csrf_token'] = bin2hex(random_bytes(32));
    }
    $csrfToken = (string)$_SESSION['academic_year_csrf_token'];
}

$moduleDefinitions = [
    'academic_years' => ['title' => 'Academic Years', 'icon' => 'calendar-range'],
    'academic_calendar' => ['title' => 'Academic Calendar', 'icon' => 'calendar-days'],
    'terms_semesters' => ['title' => 'Terms / Semesters', 'icon' => 'notebook-tabs'],
    'holidays' => ['title' => 'Holidays', 'icon' => 'calendar-off']
];

$fieldSchemas = [
    'academic_years' => [
        ['name' => 'year_name', 'label' => 'Academic Year', 'type' => 'text', 'required' => true],
        ['name' => 'start_date', 'label' => 'Start Date', 'type' => 'date', 'required' => true],
        ['name' => 'end_date', 'label' => 'End Date', 'type' => 'date', 'required' => true],
        ['name' => 'is_current', 'label' => 'Current', 'type' => 'select', 'options' => ['0' => 'No', '1' => 'Yes']],
        ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['active' => 'Active', 'closed' => 'Closed']]
    ],
    'academic_calendar' => [
        ['name' => 'event_name', 'label' => 'Event Name', 'type' => 'text', 'required' => true],
        ['name' => 'event_date', 'label' => 'Event Date', 'type' => 'date', 'required' => true],
        ['name' => 'event_type', 'label' => 'Event Type', 'type' => 'select', 'options' => ['academic' => 'Academic', 'exam' => 'Exam', 'event' => 'Event', 'other' => 'Other']],
        ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
        ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['active' => 'Active', 'inactive' => 'Inactive']]
    ],
    'terms_semesters' => [
        ['name' => 'term_name', 'label' => 'Term / Semester', 'type' => 'text', 'required' => true],
        ['name' => 'start_date', 'label' => 'Start Date', 'type' => 'date', 'required' => true],
        ['name' => 'end_date', 'label' => 'End Date', 'type' => 'date', 'required' => true],
        ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
        ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['active' => 'Active', 'inactive' => 'Inactive']]
    ],
    'holidays' => [
        ['name' => 'holiday_name', 'label' => 'Holiday Name', 'type' => 'text', 'required' => true],
        ['name' => 'holiday_date', 'label' => 'Holiday Date', 'type' => 'date', 'required' => true],
        ['name' => 'holiday_type', 'label' => 'Holiday Type', 'type' => 'select', 'options' => ['public' => 'Public', 'school' => 'School', 'optional' => 'Optional']],
        ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
        ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['active' => 'Active', 'inactive' => 'Inactive']]
    ]
];

function academicYearCan(string $moduleKey, string $action): bool
{
    $moduleKey = strtolower(trim($moduleKey));
    $permissionAction = strtolower(trim($action));

    $permissionAction = match ($permissionAction) {
        'add', 'create', 'store', 'insert' => 'create',
        'edit', 'update', 'archive', 'status' => 'edit',
        'delete', 'remove', 'destroy' => 'delete',
        default => 'view',
    };

    if (function_exists('is_super_admin') && is_super_admin()) {
        return true;
    }

    if (!function_exists('school_effective_permission')) {
        return false;
    }

    try {
        /*
         * The exact child permission is authoritative. A denied child must not
         * be widened by the old academic_year_management parent permission.
         */
        if (function_exists('school_sidebar_permission_decision')) {
            $decision = school_sidebar_permission_decision(
                $moduleKey,
                $permissionAction
            );

            if ($decision !== null) {
                return $decision;
            }
        }

        /* Compatibility only for installations that still have one simple
         * Academic Year sidebar item and no child catalogue entries. */
        return school_effective_permission('academic_year', $permissionAction);
    } catch (Throwable $exception) {
        error_log('Academic Year page permission: ' . $exception->getMessage());
        return false;
    }
}

require dirname(__DIR__) . '/includes/layout-start.php';

$visibleModules = [];
foreach ($moduleDefinitions as $moduleKey => $module) {
    if (academicYearCan($moduleKey, 'view')) {
        $visibleModules[$moduleKey] = $module;
    }
}
?>
<style>
.academic-suite{display:grid;gap:16px}
.academic-suite .page-title{font-size:28px;line-height:1.1}
.academic-suite .page-subtitle{margin-top:4px}
.academic-message{display:none;margin:0;border:0;border-radius:12px}
.academic-message.show{display:block}
.academic-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.academic-stat{border:0;border-radius:14px;min-height:112px;padding:18px 20px;display:flex;align-items:center;gap:14px;color:#fff;position:relative;overflow:hidden;box-shadow:0 12px 28px rgba(15,23,42,.08)}
.academic-stat::after{content:"";position:absolute;width:110px;height:110px;border-radius:50%;right:-38px;top:-40px;background:rgba(255,255,255,.08)}
.academic-stat.purple{background:linear-gradient(135deg,#7448e8,#4b36cf)}
.academic-stat.green{background:linear-gradient(135deg,#45c783,#1ea568)}
.academic-stat.orange{background:linear-gradient(135deg,#ffb327,#ff8a17)}
.academic-stat.blue{background:linear-gradient(135deg,#4696ef,#2469d5)}
.academic-stat-icon{width:50px;height:50px;border-radius:50%;background:rgba(255,255,255,.16);display:grid;place-items:center;flex:0 0 auto}
.academic-stat-icon svg{width:25px;height:25px}
.academic-stat strong{display:block;font-size:26px;line-height:1}
.academic-stat small{display:block;font-size:11px;font-weight:700;opacity:.94;margin-bottom:6px}
.academic-stat .trend{font-size:9px;font-weight:700;opacity:.94;margin-top:8px}
.academic-tabs{display:flex;gap:8px;overflow-x:auto;padding:10px}
.academic-tab{flex:0 0 auto;display:inline-flex;align-items:center;gap:7px;padding:9px 12px;border:1px solid var(--border-soft,#e7ebf3);border-radius:9px;background:var(--card-bg,#fff);color:var(--text-main,#101a3b);font-size:11px;font-weight:700;cursor:pointer}
.academic-tab.active{color:#fff;border-color:transparent;background:linear-gradient(135deg,#6547e8,#315ed8)}
.academic-tab svg{width:15px;height:15px}
.academic-panel{display:none}.academic-panel.active{display:block}
.academic-panel-grid{display:grid;gap:16px}
.academic-card{border-radius:14px;overflow:hidden;background:#fff;border:1px solid var(--border-soft,#e7ebf3)}
.academic-card-head{padding:16px;border-bottom:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}
.academic-card-head strong{font-size:14px}
.academic-card-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.academic-filter{padding:14px 16px;display:grid;grid-template-columns:minmax(200px,1fr) minmax(150px,0.5fr) auto;gap:10px;border-bottom:1px solid var(--border-soft,#e7ebf3)}
.academic-table-wrap{overflow:auto;padding:0 4px}
.academic-table{min-width:920px;width:100%;border-collapse:collapse}
.academic-table th{font-size:10px;text-transform:uppercase;letter-spacing:0.5px;padding:10px 12px;background:#f8fafc;border-bottom:2px solid var(--border-soft,#e7ebf3);text-align:left;font-weight:700;color:#64748b}
.academic-table td{font-size:11px;vertical-align:middle;padding:10px 12px;border-bottom:1px solid var(--border-soft,#e7ebf3)}
.academic-table tr:hover td{background:#f8fafc}
.academic-badge{display:inline-flex;align-items:center;padding:4px 10px;border-radius:999px;font-size:9px;font-weight:800;text-transform:capitalize}
.academic-badge.active{color:#16834f;background:#e8f8ef}
.academic-badge.closed{color:#dc2626;background:#fff0f1}
.academic-current{display:inline-flex;align-items:center;padding:4px 10px;border-radius:999px;color:#2563eb;background:#eaf2ff;font-size:9px;font-weight:800}
.academic-actions{display:flex;gap:5px;flex-wrap:wrap}
.academic-action{width:30px;height:30px;display:grid;place-items:center;border:1px solid #d7def1;border-radius:7px;background:#fff;color:#334155;cursor:pointer;transition:all 0.2s}
.academic-action:hover{background:#f0f4ff;border-color:#4f46e5}
.academic-action.danger{color:#dc2626}
.academic-action.danger:hover{background:#fef2f2;border-color:#dc2626}
.academic-action svg{width:13px;height:13px}
.academic-empty{padding:42px 20px!important;text-align:center!important;color:var(--text-muted,#64748b)}
.academic-year-cell{display:flex;align-items:center;gap:9px}
.academic-avatar{width:30px;height:30px;border-radius:50%;display:grid;place-items:center;color:#fff;font-size:11px;font-weight:800;background:linear-gradient(135deg,#6d4ce7,#345fe0)}
#academicRecordModal .modal-dialog{max-height:calc(100dvh - 32px);margin:16px auto}
#academicRecordModal .modal-content{max-height:calc(100dvh - 32px);overflow:hidden}
#academicRecordModal form{display:flex;flex-direction:column;max-height:calc(100dvh - 32px)}
#academicRecordModal .modal-body{overflow-y:auto;min-height:0}
@media(max-width:900px){.academic-stats{grid-template-columns:repeat(2,1fr)}}
@media(max-width:575px){.academic-stats,.academic-filter{grid-template-columns:1fr}.academic-card-head{flex-direction:column;align-items:flex-start}.academic-card-actions{width:100%}}
</style>

<div class="academic-suite">
    <div class="page-heading">
        <div>
            <h1 class="page-title">Academic Year Management</h1>
            <p class="page-subtitle">Manage academic years, terms, holidays and calendars.</p>
        </div>
        <div class="page-actions">
            <?php if (academicYearCan('academic_years', 'add')): ?>
                <button class="btn-ui btn-primary-ui js-add" type="button" data-module="academic_years" data-permission-key="academic_years" data-permission-action="add">
                    <i data-lucide="plus"></i> Add Academic Year
                </button>
            <?php endif; ?>
        </div>
    </div>

    <section class="academic-stats">
        <article class="academic-stat purple">
            <span class="academic-stat-icon"><i data-lucide="calendar-range"></i></span>
            <div>
                <small>Total Academic Years</small>
                <strong id="academicTotalYears">0</strong>
                <div class="trend">All configured academic years</div>
            </div>
        </article>
        <article class="academic-stat green">
            <span class="academic-stat-icon"><i data-lucide="calendar-check-2"></i></span>
            <div>
                <small>Active Years</small>
                <strong id="academicActiveYears">0</strong>
                <div class="trend">Currently active records</div>
            </div>
        </article>
        <article class="academic-stat orange">
            <span class="academic-stat-icon"><i data-lucide="calendar-clock"></i></span>
            <div>
                <small>Current Year</small>
                <strong id="academicCurrentYear">-</strong>
                <div class="trend">Selected school year</div>
            </div>
        </article>
        <article class="academic-stat blue">
            <span class="academic-stat-icon"><i data-lucide="calendar-off"></i></span>
            <div>
                <small>Closed / Archived</small>
                <strong id="academicClosedYears">0</strong>
                <div class="trend">Completed academic years</div>
            </div>
        </article>
    </section>

    <?php if (empty($visibleModules)): ?>
        <div class="ui-card"><div class="academic-empty">No Academic Year sections are enabled for your role.</div></div>
    <?php else: ?>
        <section class="ui-card academic-tabs" id="academicTabs">
            <?php $first = true; foreach ($visibleModules as $key => $module): ?>
                <button class="academic-tab <?= $first ? 'active' : '' ?>" type="button" data-module="<?= e($key) ?>">
                    <i data-lucide="<?= e((string)$module['icon']) ?>"></i>
                    <?= e((string)$module['title']) ?>
                </button>
            <?php $first = false; endforeach; ?>
        </section>

        <?php $first = true; foreach ($visibleModules as $key => $module): ?>
            <section class="academic-panel <?= $first ? 'active' : '' ?>" data-panel="<?= e($key) ?>">
                <div class="academic-panel-grid">
                    <section class="ui-card academic-card">
                        <div class="academic-card-head">
                            <strong><?= e((string)$module['title']) ?></strong>
                            <div class="academic-card-actions">
                                <button class="btn-ui js-refresh" type="button" data-module="<?= e($key) ?>">
                                    <i data-lucide="refresh-cw"></i> Refresh
                                </button>
                                <?php if (academicYearCan($key, 'add')): ?>
                                    <button class="btn-ui btn-primary-ui js-add" type="button" data-module="<?= e($key) ?>" data-permission-key="<?= e($key) ?>" data-permission-action="add">
                                        <i data-lucide="plus"></i>
                                        <?= $key === 'academic_years' ? 'Add Academic Year' : 'Add Record' ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="alert academic-message" data-message="<?= e($key) ?>"></div>

                        <div class="academic-filter">
                            <input class="form-control js-search" data-module="<?= e($key) ?>" type="search" placeholder="Search <?= e((string)$module['title']) ?>...">
                            <select class="form-select js-status" data-module="<?= e($key) ?>">
                                <option value="all">All statuses</option>
                                <option value="active">Active</option>
                                <?php if ($key === 'academic_years'): ?>
                                    <option value="closed">Closed</option>
                                <?php else: ?>
                                    <option value="inactive">Inactive</option>
                                <?php endif; ?>
                            </select>
                            <button class="btn-ui js-reset" type="button" data-module="<?= e($key) ?>">
                                <i data-lucide="rotate-ccw"></i> Reset
                            </button>
                        </div>

                        <div class="academic-table-wrap">
                            <table class="data-table academic-table">
                                <thead><tr>
                                    <?php foreach ($fieldSchemas[$key] as $field): ?>
                                        <?php if (($field['name'] ?? '') === 'status') continue; ?>
                                        <th><?= e((string)$field['label']) ?></th>
                                    <?php endforeach; ?>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr></thead>
                                <tbody data-body="<?= e($key) ?>">
                                    <tr><td colspan="<?= count(array_filter($fieldSchemas[$key], static fn(array $field): bool => ($field['name'] ?? '') !== 'status')) + 2 ?>" class="academic-empty">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="academic-card-head">
                            <small class="text-muted" data-copy="<?= e($key) ?>">Loading...</small>
                        </div>
                    </section>
                </div>
            </section>
        <?php $first = false; endforeach; ?>
    <?php endif; ?>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="academicRecordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="academicRecordForm" novalidate>
                <div class="modal-header">
                    <div>
                        <h5 id="academicModalTitle" class="modal-title">Add Record</h5>
                        <small class="text-muted">Fill in the details below</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input id="academicRecordId" type="hidden">
                    <input id="academicModuleKey" type="hidden">
                    <div id="academicDynamicFields" class="row g-3"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-ui" data-bs-dismiss="modal">Cancel</button>
                    <button id="academicSaveButton" type="submit" class="btn-ui btn-primary-ui" data-permission-action="add">
                        <i data-lucide="save"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function(){
'use strict';

// Resolve the API relative to this page so sub-folder installations work.
const endpoint = new URL('../api/academic-years.php', window.location.href).href;

const schemas = <?= json_encode($fieldSchemas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const permissions = <?= json_encode(array_combine(
    array_keys($visibleModules),
    array_map(
        static fn(string $key): array => [
            'add' => academicYearCan($key, 'add'),
            'edit' => academicYearCan($key, 'edit'),
            'delete' => academicYearCan($key, 'delete'),
        ],
        array_keys($visibleModules)
    )
), JSON_UNESCAPED_SLASHES) ?>;

let csrfToken = <?= json_encode($csrfToken) ?>;
const records = {};
let currentTab = '';

const esc = v => String(v ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');

function getModal() {
    const el = document.getElementById('academicRecordModal');
    if (!el) return null;
    if (window.bootstrap?.Modal) {
        return window.bootstrap.Modal.getOrCreateInstance(el);
    }
    return { show: () => { el.style.display = 'block'; }, hide: () => { el.style.display = 'none'; } };
}

async function request(moduleKey, action, data = {}, method = 'GET') {
    try {
        let response;
        const url = new URL(endpoint);
        
        if (method === 'GET') {
            url.searchParams.set('module_key', moduleKey);
            url.searchParams.set('action', action);
            Object.entries(data).forEach(([k, v]) => {
                if (v !== '' && v !== null && v !== undefined) {
                    url.searchParams.set(k, String(v));
                }
            });
                response = await fetch(url.toString(), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin'
            });
        } else {
            const bodyData = { module_key: moduleKey, action, csrf_token: csrfToken, ...data };
                response = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(bodyData)
            });
        }

        const text = await response.text();
        
        let result;
        try {
            result = JSON.parse(text);
        } catch (e) {
            throw new Error(`Academic Year API returned HTTP ${response.status}. ${text.replace(/\s+/g,' ').trim().slice(0,220) || 'Invalid server response.'}`);
        }

        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Request failed.');
        }

        if (result.data?.csrf_token) {
            csrfToken = result.data.csrf_token;
        }
        return result;
    } catch (error) {
        throw error;
    }
}

function showMessage(moduleKey, text, success) {
    const box = document.querySelector(`[data-message="${moduleKey}"]`);
    if (!box) return;
    box.className = 'alert academic-message show ' + (success ? 'alert-success' : 'alert-danger');
    box.textContent = text;
    setTimeout(() => { box.className = 'alert academic-message'; }, 5000);
}

function filtered(moduleKey) {
    const q = document.querySelector(`.js-search[data-module="${moduleKey}"]`)?.value.trim().toLowerCase() || '';
    const status = document.querySelector(`.js-status[data-module="${moduleKey}"]`)?.value || 'all';
    return (records[moduleKey] || []).filter(r => {
        const dataStr = JSON.stringify(r.data || {}).toLowerCase();
        const matchesSearch = dataStr.includes(q);
        const matchesStatus = status === 'all' || (r.status || 'active') === status;
        return matchesSearch && matchesStatus;
    });
}

function updateAcademicDashboard() {
    const years = records.academic_years || [];
    const active = years.filter(r => (r.data?.status || r.status) === 'active');
    const closed = years.filter(r => ['closed', 'archived', 'inactive'].includes(r.data?.status || r.status));
    const current = years.find(r => Number(r.data?.is_current || 0) === 1);
    
    document.getElementById('academicTotalYears').textContent = years.length;
    document.getElementById('academicActiveYears').textContent = active.length;
    document.getElementById('academicCurrentYear').textContent = current?.data?.year_name || '-';
    document.getElementById('academicClosedYears').textContent = closed.length;
}

function displayValue(field, value) {
    if (field.options && Object.prototype.hasOwnProperty.call(field.options, String(value))) {
        return field.options[String(value)];
    }
    if (value === null || value === undefined || value === '') return '-';
    return String(value);
}

function render(moduleKey) {
    const schema = schemas[moduleKey] || [];
    const displaySchema = schema.filter(field => field.name !== 'status');
    const rows = filtered(moduleKey);
    const body = document.querySelector(`[data-body="${moduleKey}"]`);
    if (!body) return;

    if (rows.length === 0) {
        body.innerHTML = `<tr><td colspan="${displaySchema.length + 2}" class="academic-empty">No records found.</td></tr>`;
        const copy = document.querySelector(`[data-copy="${moduleKey}"]`);
        if (copy) copy.textContent = 'No records found';
        return;
    }

    body.innerHTML = rows.map(row => {
        const cells = displaySchema.map(field => {
            const value = displayValue(field, row.data?.[field.name]);
            
            if (moduleKey === 'academic_years' && field.name === 'year_name') {
                return `<td>
                    <div class="academic-year-cell">
                        <span class="academic-avatar">${esc(String(value).charAt(0).toUpperCase() || '?')}</span>
                        <strong>${esc(value)}</strong>
                    </div>
                </td>`;
            }
            
            if (moduleKey === 'academic_years' && field.name === 'is_current') {
                const isCurrent = Number(row.data?.is_current || 0) === 1;
                return `<td>${isCurrent ? '<span class="academic-current">Current</span>' : '<span class="text-muted">No</span>'}</td>`;
            }

            return `<td>${esc(value)}</td>`;
        }).join('');

        const statusVal = row.status || 'active';
        const statusBadge = `<td><span class="academic-badge ${esc(statusVal)}">${esc(statusVal)}</span></td>`;

        return `<tr data-id="${row.id}">
            ${cells}
            ${statusBadge}
            <td>
                <div class="academic-actions">
                    ${permissions[moduleKey]?.edit ? 
                        `<button class="academic-action js-edit-row" data-module="${moduleKey}" data-id="${row.id}" data-permission-key="${moduleKey}" data-permission-action="edit" type="button" title="Edit">
                            <i data-lucide="pencil"></i>
                        </button>` : ''
                    }
                    ${permissions[moduleKey]?.delete ? 
                        `<button class="academic-action danger js-delete-row" data-module="${moduleKey}" data-id="${row.id}" data-permission-key="${moduleKey}" data-permission-action="delete" type="button" title="Delete">
                            <i data-lucide="trash-2"></i>
                        </button>` : ''
                    }
                    ${moduleKey === 'academic_years' && permissions[moduleKey]?.edit && row.data?.status !== 'closed' ? 
                        `<button class="academic-action js-archive-row" data-module="${moduleKey}" data-id="${row.id}" data-permission-key="${moduleKey}" data-permission-action="edit" type="button" title="Archive">
                            <i data-lucide="archive"></i>
                        </button>` : ''
                    }
                </div>
            </td>
        </tr>`;
    }).join('');

    const copy = document.querySelector(`[data-copy="${moduleKey}"]`);
    if (copy) copy.textContent = `Showing ${rows.length} record${rows.length === 1 ? '' : 's'}`;

    // Event listeners
    body.querySelectorAll('.js-edit-row').forEach(button => {
        button.addEventListener('click', () => {
            const record = (records[moduleKey] || []).find(
                row => Number(row.id) === Number(button.dataset.id)
            );
            openForm(moduleKey, record || null);
        });
    });

    body.querySelectorAll('.js-delete-row').forEach(button => {
        button.addEventListener('click', async () => {
            if (!window.confirm('Delete this record? This action cannot be undone.')) return;
            try {
                const result = await request(moduleKey, 'delete', { id: Number(button.dataset.id) }, 'POST');
                showMessage(moduleKey, result.message, true);
                await load(moduleKey);
            } catch (error) {
                showMessage(moduleKey, error.message, false);
            }
        });
    });

    body.querySelectorAll('.js-archive-row').forEach(button => {
        button.addEventListener('click', async () => {
            if (!window.confirm('Archive this academic year?')) return;
            try {
                const result = await request(moduleKey, 'archive', { id: Number(button.dataset.id) }, 'POST');
                showMessage(moduleKey, result.message, true);
                await load(moduleKey);
            } catch (error) {
                showMessage(moduleKey, error.message, false);
            }
        });
    });

    updateAcademicDashboard();
    if (window.lucide) window.lucide.createIcons();
}

function openForm(moduleKey, record = null) {
    const requiredAction = record ? 'edit' : 'add';
    if (!permissions[moduleKey]?.[requiredAction]) {
        showMessage(moduleKey, `You do not have ${requiredAction} permission for this section.`, false);
        return;
    }

    const form = document.getElementById('academicRecordForm');
    const idField = document.getElementById('academicRecordId');
    const moduleField = document.getElementById('academicModuleKey');
    const title = document.getElementById('academicModalTitle');
    const wrap = document.getElementById('academicDynamicFields');

    if (!form || !idField || !moduleField || !title || !wrap) return;

    form.reset();
    form.classList.remove('was-validated');
    idField.value = record?.id || '';
    moduleField.value = moduleKey;

    const moduleTitle = moduleKey === 'academic_years' ? 'Academic Year' : 
        (document.querySelector(`.academic-tab[data-module="${moduleKey}"]`)?.textContent.trim() || 'Record');

    title.textContent = (record ? 'Edit ' : 'Add ') + moduleTitle;

    const saveButton = document.getElementById('academicSaveButton');
    if (saveButton) {
        saveButton.dataset.permissionKey = moduleKey;
        saveButton.dataset.permissionAction = record ? 'edit' : 'add';
        saveButton.style.display = (record ? permissions[moduleKey]?.edit : permissions[moduleKey]?.add) ? '' : 'none';
    }

    wrap.innerHTML = (schemas[moduleKey] || []).map(field => {
        const value = record?.data?.[field.name] ?? '';
        const required = field.required ? 'required' : '';

        if (field.type === 'textarea') {
            return `<div class="col-12">
                <label class="form-label fw-semibold">${esc(field.label)} ${field.required ? '*' : ''}</label>
                <textarea class="form-control js-field" data-name="${esc(field.name)}" ${required} rows="3">${esc(value)}</textarea>
            </div>`;
        }

        if (field.type === 'select') {
            return `<div class="col-md-6">
                <label class="form-label fw-semibold">${esc(field.label)} ${field.required ? '*' : ''}</label>
                <select class="form-select js-field" data-name="${esc(field.name)}" ${required}>
                    ${Object.entries(field.options || {}).map(([key, label]) =>
                        `<option value="${esc(key)}" ${String(value) === String(key) ? 'selected' : ''}>${esc(label)}</option>`
                    ).join('')}
                </select>
            </div>`;
        }

        return `<div class="col-md-6">
            <label class="form-label fw-semibold">${esc(field.label)} ${field.required ? '*' : ''}</label>
            <input class="form-control js-field" data-name="${esc(field.name)}" 
                type="${esc(field.type || 'text')}" value="${esc(value)}" ${required}>
        </div>`;
    }).join('');

    const modal = getModal();
    if (modal) modal.show();
}

async function load(moduleKey) {
    try {
        const x = await request(moduleKey, 'list');
        csrfToken = x.data.csrf_token || csrfToken;
        records[moduleKey] = x.data.records || [];
        render(moduleKey);
    } catch (e) {
        showMessage(moduleKey, e.message, false);
        const body = document.querySelector(`[data-body="${moduleKey}"]`);
        if (body) {
            body.innerHTML = `<tr><td colspan="${(schemas[moduleKey] || []).filter(field => field.name !== 'status').length + 2}" class="academic-empty">
                Error loading data: ${esc(e.message)}
            </td></tr>`;
        }
    }
}

// ============================================
// EVENT LISTENERS
// ============================================

// Tab switching
document.querySelectorAll('.academic-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.academic-tab').forEach(x => x.classList.remove('active'));
        document.querySelectorAll('.academic-panel').forEach(x => x.classList.remove('active'));
        tab.classList.add('active');
        const panel = document.querySelector(`[data-panel="${tab.dataset.module}"]`);
        if (panel) panel.classList.add('active');
        currentTab = tab.dataset.module;
        load(currentTab);
    });
});

// Add buttons
document.querySelectorAll('.js-add').forEach(b => {
    b.addEventListener('click', () => openForm(b.dataset.module));
});

// Refresh buttons
document.querySelectorAll('.js-refresh').forEach(b => {
    b.addEventListener('click', () => load(b.dataset.module));
});

// Search and filter
document.querySelectorAll('.js-search, .js-status').forEach(el => {
    const eventType = el.matches('.js-search') ? 'input' : 'change';
    el.addEventListener(eventType, () => render(el.dataset.module));
});

// Reset buttons
document.querySelectorAll('.js-reset').forEach(b => {
    b.addEventListener('click', () => {
        const moduleKey = b.dataset.module;
        const search = document.querySelector(`.js-search[data-module="${moduleKey}"]`);
        const status = document.querySelector(`.js-status[data-module="${moduleKey}"]`);
        if (search) search.value = '';
        if (status) status.value = 'all';
        render(moduleKey);
    });
});

// Form submit
document.getElementById('academicRecordForm')?.addEventListener('submit', async e => {
    e.preventDefault();
    const form = e.currentTarget;
    
    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return;
    }

    const moduleKey = document.getElementById('academicModuleKey').value;
    const id = Number(document.getElementById('academicRecordId').value || 0);
    const data = {};
    
    document.querySelectorAll('.js-field').forEach(f => {
        const val = f.value.trim();
        if (f.type === 'number') {
            data[f.dataset.name] = parseFloat(val) || 0;
        } else if (f.type === 'date') {
            data[f.dataset.name] = val;
        } else {
            data[f.dataset.name] = val;
        }
    });

    try {
        const action = id ? 'update' : 'create';
        const result = await request(moduleKey, action, { id, data, status: data.status || 'active' }, 'POST');
        
        const modal = getModal();
        if (modal) modal.hide();
        
        showMessage(moduleKey, result.message, true);
        await load(moduleKey);
        if (moduleKey === 'academic_years') updateAcademicDashboard();
    } catch (err) {
        showMessage(moduleKey, err.message, false);
    }
});

// ============================================
// INITIAL LOAD
// ============================================

const firstTab = document.querySelector('.academic-tab.active')?.dataset.module;
if (firstTab) {
    load(firstTab);
} else {
    const first = document.querySelector('.academic-tab');
    if (first) {
        first.classList.add('active');
        const panel = document.querySelector(`[data-panel="${first.dataset.module}"]`);
        if (panel) panel.classList.add('active');
        load(first.dataset.module);
    }
}

if (window.lucide) window.lucide.createIcons();

})();
</script>

<?php require dirname(__DIR__) . '/includes/layout-end.php'; ?>
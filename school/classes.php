<?php
declare(strict_types=1);

/* Build: 2026-08-15-classes-branch-visibility-v56 */

$pageTitle = 'Class Management';
$pageKey = 'classes';
$sidebarFile = __DIR__ . '/sidebar.php';

require dirname(__DIR__) . '/includes/layout-start.php';

/*
 * Explicitly use the existing common School ERP toast helper.
 * Render locally only when bootstrap does not already inject it.
 */
require_once dirname(__DIR__) . '/includes/common-toast.php';

$renderCommonToastLocally = !function_exists(
    'school_enable_common_toast_ui'
);

$baseUrl = defined('BASE_URL')
    ? rtrim((string)BASE_URL, '/') . '/'
    : '../';

$csrfToken = function_exists('csrfToken')
    ? csrfToken()
    : '';

$pageCapabilities = function_exists('school_current_page_capabilities')
    ? school_current_page_capabilities($pageKey)
    : [
        'view' => true,
        'add' => true,
        'create' => true,
        'edit' => true,
        'delete' => true,
        'print' => true,
        'pdf' => true,
        'export' => true,
        'import' => true,
    ];

$canClassView = !empty($pageCapabilities['view']);
$canClassAdd = !empty(
    $pageCapabilities['add']
    ?? $pageCapabilities['create']
    ?? false
);
$canClassEdit = !empty($pageCapabilities['edit']);
$canClassDelete = !empty($pageCapabilities['delete']);
$canClassPrint = !empty($pageCapabilities['print']);
$canClassPdf = !empty($pageCapabilities['pdf']);
$canClassExport = !empty($pageCapabilities['export']);
$canClassImport = !empty($pageCapabilities['import']);
?>
<style>
.cm{display:grid;gap:16px}
.cm .page-title{font-size:28px;line-height:1.1}
.cm .page-subtitle{margin-top:4px}
.cm .page-actions{gap:10px}

.cm-message{display:none}
.cm-message.show{display:block}

.cm-stats{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:14px;
}
.cm-stat{
    border:0;
    border-radius:14px;
    min-height:112px;
    padding:18px 20px;
    display:flex;
    align-items:center;
    gap:14px;
    color:#fff;
    position:relative;
    overflow:hidden;
    box-shadow:0 12px 28px rgba(15,23,42,.08);
}
.cm-stat::after{
    content:"";
    position:absolute;
    width:110px;
    height:110px;
    border-radius:50%;
    right:-38px;
    top:-40px;
    background:rgba(255,255,255,.08);
}
.cm-stat.purple{background:linear-gradient(135deg,#7448e8,#4b36cf)}
.cm-stat.green{background:linear-gradient(135deg,#45c783,#1ea568)}
.cm-stat.pink{background:linear-gradient(135deg,#ff527c,#ed2f63)}
.cm-stat.orange{background:linear-gradient(135deg,#ffb327,#ff8a17)}
.cm-stat-icon{
    width:50px;
    height:50px;
    border-radius:50%;
    background:rgba(255,255,255,.16);
    display:grid;
    place-items:center;
    flex:0 0 auto;
}
.cm-stat-icon svg{width:25px;height:25px}
.cm-stat strong{display:block;font-size:26px;line-height:1}
.cm-stat small{display:block;font-size:11px;font-weight:700;opacity:.94;margin-bottom:6px}
.cm-stat .trend{font-size:9px;font-weight:700;opacity:.94;margin-top:8px}

.cm-tabs{
    display:flex;
    gap:8px;
    overflow:auto;
    padding:10px;
}
.cm-tab{
    white-space:nowrap;
    border:1px solid var(--border-soft,#e7ebf3);
    background:var(--card-bg,#fff);
    color:var(--text-main,#101b46);
    border-radius:9px;
    padding:9px 12px;
    font-size:11px;
    font-weight:700;
}
.cm-tab.active{
    color:#fff;
    border-color:transparent;
    background:linear-gradient(135deg,#6747e8,#2f62d7);
}
.cm-panel{display:none}
.cm-panel.active{display:block}

.cm-layout{
    display:grid;
    grid-template-columns:minmax(0,1fr);
    gap:16px;
    align-items:start;
}
.cm-card{
    border-radius:14px;
    overflow:hidden;
}
.cm-card-head{
    padding:16px;
    border-bottom:1px solid var(--border-soft,#e7ebf3);
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
}
.cm-card-head strong{font-size:14px}
.cm-card-head-actions{display:flex;gap:8px;align-items:center}
.cm-card-head-actions .form-select{min-width:145px}

.cm-filter{
    padding:14px 16px;
    display:grid;
    grid-template-columns:minmax(230px,1.5fr) repeat(3,minmax(125px,.75fr));
    gap:9px;
    border-bottom:1px solid var(--border-soft,#e7ebf3);
}
.cm-table-wrap{overflow:auto}
.cm-table{min-width:1050px}
.cm-table th{font-size:10px}
.cm-table td{font-size:11px;vertical-align:middle}
.cm-class-cell{display:flex;align-items:center;gap:9px}
.cm-avatar{
    width:30px;
    height:30px;
    border-radius:50%;
    display:grid;
    place-items:center;
    color:#fff;
    font-size:11px;
    font-weight:800;
    background:linear-gradient(135deg,#6d4ce7,#345fe0);
}
.cm-badge{
    display:inline-flex;
    align-items:center;
    padding:5px 9px;
    border-radius:999px;
    font-size:9px;
    font-weight:800;
    text-transform:capitalize;
}
.cm-badge.active{color:#16834f;background:#e8f8ef}
.cm-badge.inactive{color:#9a6700;background:#fff7d6}
.cm-badge.archived{color:#dc2626;background:#fff0f1}

.cm-actions{display:flex;gap:6px}
.cm-action{
    width:30px;
    height:30px;
    display:grid;
    place-items:center;
    border:1px solid #d7def1;
    border-radius:7px;
    background:var(--card-bg,#fff);
    color:#334155;
}
.cm-action svg{width:13px;height:13px}
.cm-footer{
    padding:12px 16px;
    border-top:1px solid var(--border-soft,#e7ebf3);
    display:flex;
    justify-content:space-between;
    align-items:center;
}
.cm-footer small{font-size:10px;color:var(--text-muted,#64748b)}
.cm-pages{display:flex;gap:5px}
.cm-pages button{
    width:30px;
    height:30px;
    border:1px solid var(--border-soft,#e7ebf3);
    background:var(--card-bg,#fff);
    border-radius:7px;
    font-size:10px;
}
.cm-pages button.active{
    color:#fff;
    border-color:transparent;
    background:linear-gradient(135deg,#6547e8,#315ed8);
}

.cm-related-panel .page-heading{margin-top:2px}

#cmModal .modal-dialog,
#relatedModal .modal-dialog{
    max-height:calc(100dvh - 32px);
    margin:16px auto;
}
#cmModal .modal-content,
#relatedModal .modal-content{
    max-height:calc(100dvh - 32px);
    overflow:hidden;
}
#cmModal form,
#relatedModal form{
    display:flex;
    flex-direction:column;
    max-height:calc(100dvh - 32px);
}
#cmModal .modal-body,
#relatedModal .modal-body{
    overflow-y:auto;
    min-height:0;
}

@media(max-width:1250px){
    .cm-layout{grid-template-columns:1fr}
    .cm-filter{grid-template-columns:repeat(2,1fr)}
}
@media(max-width:900px){
    .cm-stats{grid-template-columns:repeat(2,1fr)}
}
@media(max-width:575px){
    .cm-stats,.cm-filter{grid-template-columns:1fr}
    .cm-card-head{align-items:flex-start;flex-direction:column}
    .cm-card-head-actions{width:100%}
    .cm-card-head-actions .form-select{flex:1}
}
/* Common toast: solid/sharp display, no blur. */
#schoolToastContainer .school-toast{
    -webkit-backdrop-filter:none !important;
    backdrop-filter:none !important;
    filter:none !important;
    background:#fff !important;
}
#schoolToastContainer{
    -webkit-filter:none !important;
    filter:none !important;
}
</style>

<div class="cm">
    <div class="page-heading">
        <div>
            <h1 class="page-title">Class Management</h1>
            <p class="page-subtitle">Dashboard › Class Management</p>
        </div>
        <div class="page-actions">
            <?php if ($canClassPrint): ?>
                <a
                    id="cmPrint"
                    class="btn-ui"
                    target="_blank"
                    data-permission-action="print"
                >
                    <i data-lucide="printer"></i> Print
                </a>
            <?php endif; ?>

            <?php if ($canClassPdf): ?>
                <a
                    id="cmPdf"
                    class="btn-ui"
                    data-permission-action="pdf"
                >
                    <i data-lucide="file-text"></i> PDF
                </a>
            <?php endif; ?>

            <?php if ($canClassExport): ?>
                <a
                    id="cmExcel"
                    class="btn-ui"
                    data-permission-action="export"
                >
                    <i data-lucide="download"></i> Export
                </a>
            <?php endif; ?>

            <?php if ($canClassAdd): ?>
                <button
                    id="cmAdd"
                    class="btn-ui btn-primary-ui"
                    type="button"
                    data-permission-action="create"
                >
                    <i data-lucide="plus"></i> Add Class
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div id="cmMessage" class="alert cm-message"></div>

    <section class="cm-stats">
        <article class="cm-stat purple">
            <span class="cm-stat-icon"><i data-lucide="layout-grid"></i></span>
            <div>
                <small>Total Classes</small>
                <strong id="cmTotal">0</strong>
                <div class="trend">All configured classes</div>
            </div>
        </article>

        <article class="cm-stat green">
            <span class="cm-stat-icon"><i data-lucide="users-round"></i></span>
            <div>
                <small>Current Strength</small>
                <strong id="cmStudents">0</strong>
                <div class="trend">Live total for the displayed class records</div>
            </div>
        </article>

        <article class="cm-stat pink">
            <span class="cm-stat-icon"><i data-lucide="presentation"></i></span>
            <div>
                <small>Teachers Assigned</small>
                <strong id="cmTeachers">0</strong>
                <div class="trend">Classes with teachers</div>
            </div>
        </article>

        <article class="cm-stat orange">
            <span class="cm-stat-icon"><i data-lucide="circle-check-big"></i></span>
            <div>
                <small>Active Classes</small>
                <strong id="cmActive">0</strong>
                <div class="trend">Currently active records</div>
            </div>
        </article>
    </section>



    <section class="cm-panel active" data-panel="classes">
        <section class="cm-layout">
            <section class="ui-card cm-card">
                <div class="cm-card-head">
                    <strong>All Classes</strong>
                    <div class="cm-card-head-actions">
                        <select id="cmYear" class="form-select">
                            <option value="all">All Academic Years</option>
                        </select>
                        <button id="cmFilterToggle" class="btn-ui" type="button">
                            <i data-lucide="list-filter"></i> Filter
                        </button>
                    </div>
                </div>

                <div id="cmAdvancedFilters" class="cm-filter" style="display:none">
                    <input
                        id="cmSearch"
                        class="form-control"
                        placeholder="Search class, code, section or teacher..."
                    >
                    <select id="cmMedium" class="form-select">
                        <option value="all">All Mediums</option>
                    </select>
                    <select id="cmShift" class="form-select">
                        <option value="all">All Shift Times</option>
                    </select>
                    <select id="cmStatus" class="form-select">
                        <option value="all">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="archived">Archived</option>
                    </select>
                </div>

                <div class="cm-table-wrap">
                    <table class="data-table cm-table">
                        <thead>
                            <tr>
                                <th>Class</th>
                                <th>Code</th>
                                <th>Academic Year</th>
                                <th>Section</th>
                                <th>Medium</th>
                                <th>Shift Time</th>
                                <th>Class Teacher</th>
                                <th>Classroom</th>
                                <th>Strength</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="cmBody">
                            <tr>
                                <td colspan="11" class="text-center py-5">
                                    Loading...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="cm-footer">
                    <small id="cmCopy">Loading...</small>
                    <div class="cm-pages">
                        <button type="button">‹</button>
                        <button type="button" class="active">1</button>
                        <button type="button">2</button>
                        <button type="button">3</button>
                        <button type="button">›</button>
                    </div>
                </div>
            </section>


        </section>
    </section>

    <?php foreach([
        'subjects'=>'Subject Allocation',
        'teachers'=>'Teacher Allocation',
        'timetable'=>'Timetable',
        'transfers'=>'Student Transfer',
        'promotions'=>'Class Promotion'
    ] as $k=>$v): ?>
        <section class="cm-panel cm-related-panel" data-panel="<?= e($k) ?>">
            <div class="page-heading">
                <div>
                    <h2 class="page-title"><?= e($v) ?></h2>
                    <p class="page-subtitle">Select a class and manage records.</p>
                </div>
                <button
                    class="btn-ui btn-primary-ui js-related-add"
                    data-permission-action="add"
                    data-type="<?= e($k) ?>"
                    type="button"
                >
                    <i data-lucide="plus"></i> Add Record
                </button>
            </div>

            <section class="ui-card cm-card">
                <div class="p-3">
                    <select
                        class="form-select js-related-class"
                        data-type="<?= e($k) ?>"
                    >
                        <option value="">Select class</option>
                    </select>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead
                            class="js-related-head"
                            data-type="<?= e($k) ?>"
                        ></thead>
                        <tbody
                            class="js-related-body"
                            data-type="<?= e($k) ?>"
                        >
                            <tr>
                                <td class="text-center py-5">Select a class.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </section>
    <?php endforeach; ?>
</div>

<div class="modal fade" id="cmModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content"><form id="cmForm"><div class="modal-header"><h5 id="cmModalTitle" class="modal-title">Add Class</h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><input id="cmId" type="hidden"><div class="row g-3"><div class="col-md-4"><label class="form-label">Class Name</label><input id="fName" class="form-control" required></div><div class="col-md-4"><label class="form-label">Class Code</label><input id="fCode" class="form-control text-uppercase" required></div><div class="col-md-4"><label class="form-label">Academic Year</label><select id="fYear" class="form-select" required></select></div><div class="col-md-4"><label class="form-label">Section</label><input id="fSection" class="form-control" required></div><div class="col-md-4"><label class="form-label">Medium</label><select id="fMedium" class="form-select"></select></div><div class="col-md-4"><label class="form-label">Shift Time</label><select id="fShift" class="form-select" required></select><small id="fShiftHint" class="text-muted d-block mt-1">Loaded from General Settings.</small></div><div class="col-md-4"><label class="form-label">Class Teacher User ID</label><input id="fTeacherId" class="form-control" type="number" min="0"></div><div class="col-md-4"><label class="form-label">Class Teacher</label><input id="fTeacherName" class="form-control"></div><div class="col-md-4"><label class="form-label">Classroom</label><input id="fRoom" class="form-control"></div><div class="col-md-3"><label class="form-label">Maximum Strength</label><input id="fMax" class="form-control" type="number" min="1" max="500" value="40" required></div><div class="col-md-3"><label class="form-label">Current Strength (Auto)</label><input id="fCurrent" class="form-control" type="number" min="0" value="0" readonly tabindex="-1"><small class="text-muted d-block mt-1">Calculated from active Student List enrollments.</small></div><div class="col-md-3"><label class="form-label">Status</label><select id="fStatus" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option><option value="archived">Archived</option></select></div><div class="col-md-3"><label class="form-label">Display Order</label><input id="fOrder" class="form-control" type="number" min="0" value="0"></div><div class="col-12"><label class="form-label">Description</label><textarea id="fDescription" class="form-control" rows="3"></textarea></div></div></div><div class="modal-footer"><button class="btn-ui" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn-ui btn-primary-ui" type="submit"><i data-lucide="save"></i> Save Class</button></div></form></div></div></div>
<div class="modal fade" id="relatedModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><form id="relatedForm"><div class="modal-header"><h5 id="relatedTitle" class="modal-title">Add Record</h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><input id="relatedType" type="hidden"><input id="relatedClassId" type="hidden"><div id="relatedFields" class="row g-3"></div></div><div class="modal-footer"><button class="btn-ui" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn-ui btn-primary-ui" type="submit">Save</button></div></form></div></div></div>
<?php
if (
    $renderCommonToastLocally
    && function_exists('school_toast_markup')
) {
    echo school_toast_markup();
}
?>

<script>
(function () {
    'use strict';

    const api = <?= json_encode($baseUrl . 'api/classes.php', JSON_UNESCAPED_SLASHES) ?>;
    const exportUrl = <?= json_encode($baseUrl . 'api/classes-export.php', JSON_UNESCAPED_SLASHES) ?>;

    const cmPrint = document.getElementById('cmPrint');
    const cmPdf = document.getElementById('cmPdf');
    const cmExcel = document.getElementById('cmExcel');
    const cmAdd = document.getElementById('cmAdd');

    const serverPermissions = <?= json_encode([
        'view' => $canClassView,
        'add' => $canClassAdd,
        'create' => $canClassAdd,
        'edit' => $canClassEdit,
        'delete' => $canClassDelete,
        'print' => $canClassPrint,
        'pdf' => $canClassPdf,
        'export' => $canClassExport,
        'import' => $canClassImport,
    ], JSON_UNESCAPED_SLASHES) ?>;

    let csrf = <?= json_encode($csrfToken) ?>;
    let rows = [];
    let meta = {};
    let classStats = {};
    let branchDataEnabled = true;
    let permissions = {
        view: Boolean(serverPermissions.view),
        add: Boolean(serverPermissions.add),
        create: Boolean(serverPermissions.create),
        edit: Boolean(serverPermissions.edit),
        delete: Boolean(serverPermissions.delete),
        print: Boolean(serverPermissions.print),
        pdf: Boolean(serverPermissions.pdf),
        export: Boolean(serverPermissions.export),
        import: Boolean(serverPermissions.import),
    };

    const schemas = {
        subjects: [
            ['subject_name', 'Subject Name', 'text'],
            ['subject_code', 'Subject Code', 'text'],
            ['teacher_user_id', 'Teacher User ID', 'number'],
            ['teacher_name', 'Teacher Name', 'text'],
            ['periods_per_week', 'Periods / Week', 'number'],
        ],
        teachers: [
            ['teacher_user_id', 'Teacher User ID', 'number'],
            ['teacher_name', 'Teacher Name', 'text'],
            ['assignment_type', 'Assignment Type', 'select', ['class_teacher', 'subject_teacher', 'assistant']],
            ['subject_name', 'Subject Name', 'text'],
        ],
        timetable: [
            ['day_name', 'Day', 'select', ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']],
            ['period_no', 'Period No', 'number'],
            ['start_time', 'Start Time', 'time'],
            ['end_time', 'End Time', 'time'],
            ['subject_name', 'Subject', 'text'],
            ['teacher_name', 'Teacher', 'text'],
            ['classroom_name', 'Classroom', 'text'],
        ],
        transfers: [
            ['student_id', 'Student ID', 'number'],
            ['student_name', 'Student Name', 'text'],
            ['from_class_name', 'From Class', 'text'],
            ['to_class_name', 'To Class', 'text'],
            ['transfer_date', 'Transfer Date', 'date'],
            ['reason', 'Reason', 'textarea'],
        ],
        promotions: [
            ['student_id', 'Student ID', 'number'],
            ['student_name', 'Student Name', 'text'],
            ['from_class_name', 'From Class', 'text'],
            ['to_class_name', 'To Class', 'text'],
            ['promotion_date', 'Promotion Date', 'date'],
            ['result_status', 'Result', 'select', ['promoted', 'retained', 'pending']],
            ['remarks', 'Remarks', 'textarea'],
        ],
    };

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    function can(action) {
        const key = action === 'create' ? 'add' : action;
        return Boolean(permissions[key] || (key === 'add' && permissions.create));
    }

    function showMessage(text, success = false, type = '') {
        const message = String(text ?? '').trim();
        if (!message) {
            return;
        }

        const toastType = type || (success ? 'success' : 'error');

        /*
         * Common School ERP toast system.
         * layout-start.php installs window.showToast globally, so every Classes
         * AJAX success/error uses the same project-wide notification component.
         */
        if (typeof window.showToast === 'function') {
            window.showToast(toastType, message);

            // Never leave an old fallback alert visible after toast support loads.
            const oldBox = document.getElementById('cmMessage');
            if (oldBox) {
                oldBox.className = 'alert cm-message';
                oldBox.textContent = '';
            }
            return;
        }

        /* Fallback only when the common toast asset failed to load. */
        const box = document.getElementById('cmMessage');
        if (!box) {
            return;
        }
        box.className = 'alert cm-message show '
            + (toastType === 'success' ? 'alert-success'
                : (toastType === 'warning' ? 'alert-warning'
                    : (toastType === 'info' ? 'alert-info' : 'alert-danger')));
        box.textContent = message;
    }

    async function request(action, data = {}, method = 'GET') {
        let response;

        if (method === 'GET') {
            const url = new URL(api, location.origin);
            url.searchParams.set('action', action);
            Object.entries(data).forEach(([key, value]) => {
                if (value !== '' && value !== null && value !== undefined) {
                    url.searchParams.set(key, String(value));
                }
            });
            response = await fetch(url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
        } else {
            response = await fetch(api, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ action, csrf_token: csrf, ...data }),
            });
        }

        const text = await response.text();
        let result;
        try {
            result = JSON.parse(text);
        } catch (error) {
            throw new Error(text || `Invalid server response (${response.status}).`);
        }

        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Request failed.');
        }

        if (result.data?.csrf_token) {
            csrf = result.data.csrf_token;
        }

        return result;
    }

    function filters() {
        return {
            search: cmSearch.value,
            academic_year_id: cmYear.value,
            medium: cmMedium.value,
            shift_name: cmShift.value,
            status: cmStatus.value,
        };
    }

    function updateExportLinks() {
        const query = new URLSearchParams(filters());

        if (cmPrint && can('print')) {
            const printQuery = new URLSearchParams(query);
            printQuery.set('permission_action', 'print');
            cmPrint.href = `${exportUrl}?format=print&${printQuery}`;
        }

        if (cmPdf && can('pdf')) {
            const pdfQuery = new URLSearchParams(query);
            pdfQuery.set('permission_action', 'pdf');
            cmPdf.href = `${exportUrl}?format=pdf&${pdfQuery}`;
        }

        if (cmExcel && can('export')) {
            const exportQuery = new URLSearchParams(query);
            exportQuery.set('permission_action', 'export');
            cmExcel.href = `${exportUrl}?format=excel&${exportQuery}`;
        }
    }

    function applyPermissionVisibility() {
        const classDataAvailable =
            Boolean(branchDataEnabled);

        if (cmAdd) {
            const visible =
                classDataAvailable
                && can('add');

            cmAdd.hidden = !visible;
            cmAdd.style.display = visible ? '' : 'none';
        }

        document.querySelectorAll('.js-related-add').forEach((button) => {
            const visible =
                classDataAvailable
                && can('add');

            button.hidden = !visible;
            button.style.display = visible ? '' : 'none';
        });

        if (cmPrint) {
            const visible =
                classDataAvailable
                && can('print');

            cmPrint.hidden = !visible;
            cmPrint.style.display = visible ? '' : 'none';
        }

        if (cmPdf) {
            const visible =
                classDataAvailable
                && can('pdf');

            cmPdf.hidden = !visible;
            cmPdf.style.display = visible ? '' : 'none';
        }

        if (cmExcel) {
            const visible =
                classDataAvailable
                && can('export');

            cmExcel.hidden = !visible;
            cmExcel.style.display = visible ? '' : 'none';
        }
    }

    function render() {
        cmBody.innerHTML = rows.map((record) => {
            const viewButton = can('view')
                ? '<button class="cm-action js-view" title="View" type="button" data-permission-action="view"><i data-lucide="eye"></i></button>'
                : '';
            const editButton = can('edit')
                ? '<button class="cm-action js-edit" title="Edit" type="button" data-permission-action="edit"><i data-lucide="pencil"></i></button>'
                : '';
            const deleteButton = can('delete')
                ? '<button class="cm-action js-delete" title="Delete" type="button" data-permission-action="delete"><i data-lucide="trash-2"></i></button>'
                : '';

            return `
                <tr data-id="${Number(record.id)}">
                    <td><div class="cm-class-cell"><span class="cm-avatar">${escapeHtml((record.class_name || '?').charAt(0).toUpperCase())}</span><strong>${escapeHtml(record.class_name)}</strong></div></td>
                    <td>${escapeHtml(record.class_code)}</td>
                    <td>${escapeHtml(record.academic_year_name || '-')}</td>
                    <td>${escapeHtml(record.section_name)}</td>
                    <td>${escapeHtml(record.medium)}</td>
                    <td>${shiftTableHtml(record.shift_name)}</td>
                    <td>${escapeHtml(record.class_teacher_name || '-')}</td>
                    <td>${escapeHtml(record.classroom_name || '-')}</td>
                    <td>${Number(record.current_strength || 0)}/${Number(record.maximum_strength || 0)}</td>
                    <td><span class="cm-badge ${escapeHtml(record.status)}">${escapeHtml(record.status)}</span></td>
                    <td><div class="cm-actions">${viewButton}${editButton}${deleteButton}</div></td>
                </tr>`;
        }).join('') || (
            branchDataEnabled
                ? '<tr><td colspan="11" class="text-center py-5">No classes found.</td></tr>'
                : '<tr><td colspan="11" class="text-center py-5">Classes File is OFF for the active Branch in Branch Settings.</td></tr>'
        );

        cmCopy.textContent = `Showing ${rows.length} class${rows.length === 1 ? '' : 'es'}`;
        cmTotal.textContent = String(rows.length);
        cmStudents.textContent = String(Number(
            classStats.current_strength
            ?? rows.reduce((sum, record) => sum + Number(record.current_strength || 0), 0)
        ));
        cmTeachers.textContent = String(rows.filter((record) => Number(record.class_teacher_user_id) > 0).length);
        cmActive.textContent = String(rows.filter((record) => record.status === 'active').length);

        cmBody.querySelectorAll('.js-view').forEach((button) => {
            button.onclick = () => openClass(
                rows.find((record) => Number(record.id) === Number(button.closest('tr').dataset.id)),
                true
            );
        });

        cmBody.querySelectorAll('.js-edit').forEach((button) => {
            button.onclick = () => openClass(
                rows.find((record) => Number(record.id) === Number(button.closest('tr').dataset.id)),
                false
            );
        });

        cmBody.querySelectorAll('.js-delete').forEach((button) => {
            button.onclick = async () => {
                if (!can('delete')) {
                    showMessage('Delete permission is not assigned.', false, 'warning');
                    return;
                }
                if (!confirm('Delete this class from Class Management? Linked student enrollment records will be preserved.')) {
                    return;
                }

                try {
                    const result = await request(
                        'delete',
                        { id: Number(button.closest('tr').dataset.id) },
                        'POST'
                    );
                    showMessage(result.message, true);
                    await load();
                } catch (error) {
                    showMessage(error.message);
                }
            };
        });

        window.lucide?.createIcons();
        updateRelatedClassOptions();
        updateExportLinks();
    }

    function openClass(record = null, viewOnly = false) {
        if (!branchDataEnabled) {
            showMessage(
                'Classes File is OFF for the active Branch in Branch Settings.',
                false,
                'warning'
            );
            return;
        }

        const isEdit = Boolean(record && Number(record.id) > 0);
        const requiredAction = isEdit ? 'edit' : 'add';

        if (viewOnly && !can('view')) {
            showMessage('View permission is not assigned.', false, 'warning');
            return;
        }
        if (!viewOnly && !can(requiredAction)) {
            showMessage(`${isEdit ? 'Edit' : 'Add'} permission is not assigned.`, false, 'warning');
            return;
        }

        cmForm.reset();
        cmId.value = record?.id || '';
        cmModalTitle.textContent = viewOnly ? 'View Class' : (isEdit ? 'Edit Class' : 'Add Class');

        const fields = {
            fName: 'class_name',
            fCode: 'class_code',
            fYear: 'academic_year_id',
            fSection: 'section_name',
            fMedium: 'medium',
            fShift: 'shift_name',
            fTeacherId: 'class_teacher_user_id',
            fTeacherName: 'class_teacher_name',
            fRoom: 'classroom_name',
            fMax: 'maximum_strength',
            fCurrent: 'current_strength',
            fStatus: 'status',
            fOrder: 'display_order',
            fDescription: 'description',
        };

        Object.entries(fields).forEach(([id, key]) => {
            document.getElementById(id).value = record?.[key]
                ?? (id === 'fMax' ? 40 : (id === 'fCurrent' || id === 'fOrder' ? 0 : ''));
        });

        if (!viewOnly && isEdit && record?.shift_name && !configuredShiftByName(record.shift_name)) {
            fShift.value = '';
            showMessage(
                `The existing ${record.shift_name} shift is disabled. Select an enabled Shift Time before saving.`,
                false,
                'warning'
            );
        }

        document.querySelectorAll('#cmForm input,#cmForm select,#cmForm textarea')
            .forEach((element) => {
                element.disabled = viewOnly;
            });
        fCurrent.readOnly = true;
        if (!viewOnly) {
            fShift.disabled = !(Array.isArray(meta.shifts) && meta.shifts.length);
        }

        const submitButton = document.querySelector('#cmForm button[type="submit"]');
        submitButton.style.display = viewOnly ? 'none' : '';
        submitButton.dataset.permissionAction = isEdit ? 'edit' : 'add';

        bootstrap.Modal.getOrCreateInstance(document.getElementById('cmModal')).show();
    }

    async function load() {
        if (!can('view')) {
            return;
        }

        try {
            const result = await request('list', filters());

            if (
                result.data
                && Object.prototype.hasOwnProperty.call(
                    result.data,
                    'branch_data_enabled'
                )
            ) {
                branchDataEnabled =
                    Boolean(
                        result.data.branch_data_enabled
                    );

                applyPermissionVisibility();
            }

            rows = result.data.classes || [];
            classStats = result.data.stats || {};
            render();
        } catch (error) {
            showMessage(error.message);
        }
    }

    function fill(id, items, key = null, label = null, keepFirst = false) {
        const element = document.getElementById(id);
        const first = keepFirst ? (element.options[0]?.outerHTML || '') : '';
        element.innerHTML = first + items.map((value) => key
            ? `<option value="${escapeHtml(value[key])}">${escapeHtml(value[label])}</option>`
            : `<option value="${escapeHtml(value)}">${escapeHtml(value)}</option>`
        ).join('');
    }

    function configuredShiftByName(name) {
        const needle = String(name || '').trim().toLowerCase();
        return (meta.shifts || []).find(row =>
            String(row.shift_name || '').trim().toLowerCase() === needle
        ) || null;
    }

    function fillShiftSelect(id, filter = false) {
        const element = document.getElementById(id);
        const selected = element.value;
        const shifts = Array.isArray(meta.shifts) ? meta.shifts : [];

        element.innerHTML = filter
            ? '<option value="all">All Shift Times</option>'
            : '<option value="">Select Shift Time</option>';

        shifts.forEach(shift => {
            const option = document.createElement('option');
            option.value = String(shift.shift_name || '');
            option.textContent = String(
                shift.display_label
                || shift.shift_name
                || ''
            );
            element.appendChild(option);
        });

        if (filter) {
            element.value = selected && [...element.options].some(o => o.value === selected)
                ? selected
                : 'all';
        } else if (selected && [...element.options].some(o => o.value === selected)) {
            element.value = selected;
        }

        if (!filter) {
            element.disabled = shifts.length === 0;
            const hint = document.getElementById('fShiftHint');
            if (hint) {
                hint.textContent = shifts.length
                    ? `${shifts.length} enabled shift${shifts.length === 1 ? '' : 's'} loaded from General Settings.`
                    : 'No enabled Shift Time is configured. Open General Settings and enable at least one shift.';
                hint.classList.toggle('text-danger', shifts.length === 0);
            }
        }
    }

    function shiftTableHtml(name) {
        const shift = configuredShiftByName(name);
        const label = String(name || '-');
        if (!shift) return escapeHtml(label);

        const fullLabel = String(shift.display_label || label);
        const timing = fullLabel.startsWith(label)
            ? fullLabel.slice(label.length).trim().replace(/^\(|\)$/g, '')
            : '';

        return `<strong>${escapeHtml(label)}</strong>${timing ? `<small class="d-block text-muted mt-1">${escapeHtml(timing)}</small>` : ''}`;
    }

    function updateRelatedClassOptions() {
        document.querySelectorAll('.js-related-class').forEach((select) => {
            const selected = select.value;
            select.innerHTML = '<option value="">Select class</option>'
                + rows.map((record) => `<option value="${Number(record.id)}">${escapeHtml(record.class_name + ' - ' + record.section_name)}</option>`).join('');
            select.value = selected;
        });
    }

    async function loadRelated(type, classId) {
        const head = document.querySelector(`.js-related-head[data-type="${type}"]`);
        const body = document.querySelector(`.js-related-body[data-type="${type}"]`);
        const schema = schemas[type];

        head.innerHTML = '<tr>'
            + schema.map((field) => `<th>${escapeHtml(field[1])}</th>`).join('')
            + '<th>Status</th><th>Action</th></tr>';

        if (!classId) {
            body.innerHTML = '<tr><td class="text-center py-5">Select a class.</td></tr>';
            return;
        }

        try {
            const result = await request('related', { type, class_id: classId });
            const records = result.data.records || [];
            body.innerHTML = records.map((record) => {
                const removeButton = can('delete')
                    ? `<button class="cm-action js-rdel" data-id="${Number(record.id)}" type="button" title="Delete" data-permission-action="delete"><i data-lucide="trash-2"></i></button>`
                    : '';
                return `<tr>${schema.map((field) => `<td>${escapeHtml(record[field[0]] ?? '-')}</td>`).join('')}<td>${escapeHtml(record.status || 'active')}</td><td>${removeButton}</td></tr>`;
            }).join('') || `<tr><td colspan="${schema.length + 2}" class="text-center py-5">No records.</td></tr>`;

            body.querySelectorAll('.js-rdel').forEach((button) => {
                button.onclick = async () => {
                    if (!can('delete')) {
                        showMessage('Delete permission is not assigned.', false, 'warning');
                        return;
                    }
                    if (!confirm('Delete record?')) {
                        return;
                    }
                    try {
                        const response = await request(
                            'delete_related',
                            { type, id: Number(button.dataset.id) },
                            'POST'
                        );
                        showMessage(response.message, true);
                        await loadRelated(type, classId);
                    } catch (error) {
                        showMessage(error.message);
                    }
                };
            });

            window.lucide?.createIcons();
        } catch (error) {
            showMessage(error.message);
        }
    }

    function openRelated(type) {
        if (!branchDataEnabled) {
            showMessage(
                'Classes File is OFF for the active Branch in Branch Settings.',
                false,
                'warning'
            );
            return;
        }

        if (!can('add')) {
            showMessage('Add permission is not assigned.', false, 'warning');
            return;
        }

        const select = document.querySelector(`.js-related-class[data-type="${type}"]`);
        if (!select.value) {
            showMessage('Select a class first.', false, 'warning');
            return;
        }

        relatedType.value = type;
        relatedClassId.value = select.value;
        relatedTitle.textContent = 'Add ' + document.querySelector(`.cm-tab[data-tab="${type}"]`).textContent;
        relatedFields.innerHTML = schemas[type].map((field) => {
            if (field[2] === 'textarea') {
                return `<div class="col-12"><label class="form-label">${escapeHtml(field[1])}</label><textarea class="form-control js-rf" data-name="${escapeHtml(field[0])}"></textarea></div>`;
            }
            if (field[2] === 'select') {
                return `<div class="col-md-6"><label class="form-label">${escapeHtml(field[1])}</label><select class="form-select js-rf" data-name="${escapeHtml(field[0])}">${field[3].map((value) => `<option value="${escapeHtml(value)}">${escapeHtml(value)}</option>`).join('')}</select></div>`;
            }
            return `<div class="col-md-6"><label class="form-label">${escapeHtml(field[1])}</label><input class="form-control js-rf" data-name="${escapeHtml(field[0])}" type="${escapeHtml(field[2])}"></div>`;
        }).join('');

        const submit = document.querySelector('#relatedForm button[type="submit"]');
        submit.dataset.permissionAction = 'add';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('relatedModal')).show();
    }

    cmForm.onsubmit = async (event) => {
        event.preventDefault();
        const id = Number(cmId.value || 0);
        const requiredAction = id > 0 ? 'edit' : 'add';
        if (!can(requiredAction)) {
            showMessage(`${id > 0 ? 'Edit' : 'Add'} permission is not assigned.`, false, 'warning');
            return;
        }

        const data = {
            id,
            class_name: fName.value,
            class_code: fCode.value,
            academic_year_id: Number(fYear.value),
            section_name: fSection.value,
            medium: fMedium.value,
            shift_name: fShift.value,
            class_teacher_user_id: Number(fTeacherId.value || 0),
            class_teacher_name: fTeacherName.value,
            classroom_name: fRoom.value,
            maximum_strength: Number(fMax.value),
            current_strength: Number(fCurrent.value),
            status: fStatus.value,
            display_order: Number(fOrder.value || 0),
            description: fDescription.value,
        };

        try {
            const result = await request('save', data, 'POST');
            bootstrap.Modal.getInstance(document.getElementById('cmModal'))?.hide();
            showMessage(result.message, true);
            await load();
        } catch (error) {
            showMessage(error.message);
        }
    };

    relatedForm.onsubmit = async (event) => {
        event.preventDefault();
        if (!can('add')) {
            showMessage('Add permission is not assigned.', false, 'warning');
            return;
        }

        const data = {
            type: relatedType.value,
            class_id: Number(relatedClassId.value),
            status: 'active',
        };
        document.querySelectorAll('.js-rf').forEach((field) => {
            data[field.dataset.name] = field.value;
        });

        try {
            const result = await request('save_related', data, 'POST');
            bootstrap.Modal.getInstance(document.getElementById('relatedModal'))?.hide();
            showMessage(result.message, true);
            await loadRelated(data.type, data.class_id);
        } catch (error) {
            showMessage(error.message);
        }
    };

    cmFilterToggle.onclick = () => {
        cmAdvancedFilters.style.display = cmAdvancedFilters.style.display === 'none'
            ? 'grid'
            : 'none';
    };

    if (cmAdd) {
        cmAdd.onclick = () => openClass();
    }

    document.querySelectorAll('.cm-tab').forEach((tab) => {
        tab.onclick = () => {
            document.querySelectorAll('.cm-tab,.cm-panel').forEach((element) => element.classList.remove('active'));
            tab.classList.add('active');
            document.querySelector(`[data-panel="${tab.dataset.tab}"]`).classList.add('active');
        };
    });

    document.querySelectorAll('.js-related-class').forEach((select) => {
        select.onchange = () => loadRelated(select.dataset.type, select.value);
    });

    document.querySelectorAll('.js-related-add').forEach((button) => {
        button.onclick = () => openRelated(button.dataset.type);
    });

    ['cmSearch', 'cmYear', 'cmMedium', 'cmShift', 'cmStatus'].forEach((id) => {
        document.getElementById(id).addEventListener(id === 'cmSearch' ? 'input' : 'change', load);
    });

    async function init() {
        try {
            const result = await request('meta');
            csrf = result.data.csrf_token || csrf;
            meta = result.data.meta || {};
            branchDataEnabled =
                result.data?.branch_data_enabled !== false
                && meta.branch_data_enabled !== false;

            permissions = {
                view: Boolean(result.data.permissions?.view),
                add: Boolean(
                    result.data.permissions?.add
                    || result.data.permissions?.create
                ),
                create: Boolean(
                    result.data.permissions?.create
                    || result.data.permissions?.add
                ),
                edit: Boolean(result.data.permissions?.edit),
                delete: Boolean(result.data.permissions?.delete),
                print: Boolean(result.data.permissions?.print),
                pdf: Boolean(result.data.permissions?.pdf),
                export: Boolean(result.data.permissions?.export),
                import: Boolean(result.data.permissions?.import),
            };

            applyPermissionVisibility();
            fill('fYear', meta.academic_years || [], 'id', 'year_name');
            fill('cmYear', meta.academic_years || [], 'id', 'year_name', true);
            fill('fMedium', meta.mediums || []);
            fill('cmMedium', meta.mediums || [], null, null, true);
            fillShiftSelect('fShift', false);
            fillShiftSelect('cmShift', true);
            await load();
        } catch (error) {
            showMessage(error.message);
        }
    }

    window.addEventListener(
        'storage',
        event => {
            if (
                event.key
                !== 'schoolerp_classes_visibility_changed'
            ) {
                return;
            }

            /*
             * Re-load metadata/list so another Branch Settings tab can
             * immediately hide/show the active Branch without stale data.
             */
            init();
        }
    );

    init();
    window.lucide?.createIcons();
})();
</script>
<?php require dirname(__DIR__).'/includes/layout-end.php';?>

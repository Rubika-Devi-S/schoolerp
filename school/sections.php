<?php
declare(strict_types=1);

$pageTitle = 'Sections Management';
$pageKey = 'sections';
$sidebarFile = __DIR__ . '/sidebar.php';

require dirname(__DIR__)
    . '/includes/layout-start.php';

$baseUrl = defined('BASE_URL')
    ? rtrim((string)BASE_URL, '/') . '/'
    : '../';

$csrfToken = function_exists('csrfToken')
    ? csrfToken()
    : '';

/*
 * Section action permissions.
 *
 * Platform Owner / Super Admin:
 *   Full access to all Section actions.
 *
 * Other School users:
 *   Controlled by School Sidebar Permissions.
 *
 * Unknown / missing permissions fail closed, so buttons are hidden.
 */
$sectionsPermissionUser = function_exists('current_user')
    ? current_user()
    : [];

$sectionsPermissionUser = is_array($sectionsPermissionUser)
    ? $sectionsPermissionUser
    : [];

$sectionsRoleText = strtolower(
    trim(
        (string)(
            $sectionsPermissionUser['role_key']
            ?? $sectionsPermissionUser['role_name']
            ?? $sectionsPermissionUser['role']
            ?? $sectionsPermissionUser['user_type']
            ?? $_SESSION['role_key']
            ?? $_SESSION['role_name']
            ?? $_SESSION['role']
            ?? ''
        )
    )
);

$sectionsRoleKey = preg_replace(
    '/[^a-z0-9]+/',
    '_',
    $sectionsRoleText
) ?? '';

$sectionsPlatformFullAccess = in_array(
    trim($sectionsRoleKey, '_'),
    [
        'platform_owner',
        'platformowner',
        'platform_admin',
        'platformadministrator',
        'super_admin',
        'superadministrator',
    ],
    true
);

if (function_exists('is_super_admin')) {
    try {
        $sectionsPlatformFullAccess =
            $sectionsPlatformFullAccess
            || (bool)is_super_admin();
    } catch (Throwable) {
        // Continue with role-key matching.
    }
}

$sectionsCapabilities = $sectionsPlatformFullAccess
    ? [
        'view' => true,
        'add' => true,
        'create' => true,
        'edit' => true,
        'delete' => true,
        'print' => true,
        'pdf' => true,
        'export' => true,
    ]
    : (
        function_exists('school_current_page_capabilities')
            ? school_current_page_capabilities($pageKey)
            : []
    );

$canSectionView = !empty($sectionsCapabilities['view']);

$canSectionAdd = !empty(
    $sectionsCapabilities['add']
    ?? $sectionsCapabilities['create']
    ?? false
);

$canSectionEdit = !empty(
    $sectionsCapabilities['edit']
);

$canSectionDelete = !empty(
    $sectionsCapabilities['delete']
);

$canSectionPrint = !empty(
    $sectionsCapabilities['print']
);

$canSectionPdf = !empty(
    $sectionsCapabilities['pdf']
);

$canSectionExport = !empty(
    $sectionsCapabilities['export']
);

/*
 * General Settings runtime:
 * - School Start / End Time
 * - 12 / 24 hour format
 * - Enabled Morning / General / Evening Shift Time records
 */
$generalSettingsRuntime =
    dirname(__DIR__)
    . '/includes/general-settings-runtime.php';

if (is_file($generalSettingsRuntime)) {
    require_once $generalSettingsRuntime;
}

$resolvedCurrentUser = function_exists('current_user')
    ? current_user()
    : [];

$resolvedCurrentUser = is_array($resolvedCurrentUser)
    ? $resolvedCurrentUser
    : [];

$sectionsTenantId = (int)(
    $resolvedCurrentUser['tenant_id']
    ?? $resolvedCurrentUser['school_id']
    ?? $_SESSION['tenant_id']
    ?? $_SESSION['school_id']
    ?? $_SESSION['tenant']['id']
    ?? 0
);

$sectionsGeneralSettings = (
    isset($pdo)
    && $pdo instanceof PDO
    && $sectionsTenantId > 0
    && function_exists('school_settings_get')
)
    ? school_settings_get(
        $pdo,
        $sectionsTenantId
    )
    : [
        'school_start_time' => '08:30:00',
        'school_end_time' => '16:00:00',
        'time_format' => '12',
    ];

$sectionsConfiguredShifts = (
    isset($pdo)
    && $pdo instanceof PDO
    && $sectionsTenantId > 0
    && function_exists('school_settings_enabled_shifts')
)
    ? school_settings_enabled_shifts(
        $pdo,
        $sectionsTenantId
    )
    : [];

/*
 * Backward-compatible fallback:
 * When Shift Time Configuration has not been saved yet,
 * General uses School Start Time / End Time.
 */
if (
    empty($sectionsConfiguredShifts)
    && $sectionsTenantId > 0
) {
    $generalStart = trim(
        (string)(
            $sectionsGeneralSettings['school_start_time']
            ?? '08:30:00'
        )
    );

    $generalEnd = trim(
        (string)(
            $sectionsGeneralSettings['school_end_time']
            ?? '16:00:00'
        )
    );

    if (
        $generalStart !== ''
        && $generalEnd !== ''
        && $generalStart < $generalEnd
    ) {
        $formattedStart = function_exists(
            'school_settings_format_time'
        )
            ? school_settings_format_time(
                $generalStart,
                $sectionsGeneralSettings
            )
            : $generalStart;

        $formattedEnd = function_exists(
            'school_settings_format_time'
        )
            ? school_settings_format_time(
                $generalEnd,
                $sectionsGeneralSettings
            )
            : $generalEnd;

        $sectionsConfiguredShifts = [[
            'shift_key' => 'general',
            'shift_name' => 'General',
            'start_time' => $generalStart,
            'end_time' => $generalEnd,
            'display_order' => 20,
            'display_label' =>
                'General ('
                . $formattedStart
                . ' - '
                . $formattedEnd
                . ')',
        ]];
    }
}
?>
<style>
.sections-page{display:grid;gap:16px}
.sections-page .page-title{font-size:28px;line-height:1.1}
.sections-page .page-subtitle{margin-top:4px}
.sections-page .page-actions{gap:10px}

.sections-message{display:none}
.sections-message.show{display:block}

.sections-stats{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:14px;
}
.sections-stat{
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
.sections-stat::after{
    content:"";
    position:absolute;
    width:110px;
    height:110px;
    border-radius:50%;
    right:-38px;
    top:-40px;
    background:rgba(255,255,255,.08);
}
.sections-stat.purple{background:linear-gradient(135deg,#7448e8,#4b36cf)}
.sections-stat.green{background:linear-gradient(135deg,#45c783,#1ea568)}
.sections-stat.pink{background:linear-gradient(135deg,#ff527c,#ed2f63)}
.sections-stat.orange{background:linear-gradient(135deg,#ffb327,#ff8a17)}
.sections-stat-icon{
    width:50px;
    height:50px;
    border-radius:50%;
    background:rgba(255,255,255,.16);
    display:grid;
    place-items:center;
    flex:0 0 auto;
}
.sections-stat-icon svg{width:25px;height:25px}
.sections-stat strong{display:block;font-size:26px;line-height:1}
.sections-stat small{display:block;font-size:11px;font-weight:700;opacity:.94;margin-bottom:6px}
.sections-stat .trend{font-size:9px;font-weight:700;opacity:.94;margin-top:8px}

.sections-layout{
    display:grid;
    grid-template-columns:minmax(0,1fr);
    gap:16px;
    align-items:start;
}
.sections-card{
    border-radius:14px;
    overflow:hidden;
}
.sections-card-head{
    padding:16px;
    border-bottom:1px solid var(--border-soft,#e7ebf3);
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
}
.sections-card-head strong{font-size:14px}
.sections-card-head-actions{display:flex;gap:8px;align-items:center}
.sections-card-head-actions .form-select{min-width:145px}

.sections-filter{
    padding:14px 16px;
    display:grid;
    grid-template-columns:minmax(230px,1.5fr) repeat(4,minmax(120px,.75fr));
    gap:9px;
    border-bottom:1px solid var(--border-soft,#e7ebf3);
}
.sections-table-wrap{overflow:auto}
.sections-table{min-width:1050px}
.sections-table th{font-size:10px}
.sections-table td{font-size:11px;vertical-align:middle}
.sections-table td strong{
    font-size:10px;
    font-weight:800;
    color:var(--text-main,#101b46);
}
.sections-name-cell{display:flex;align-items:center;gap:9px}
.sections-avatar{
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
.sections-badge{
    display:inline-flex;
    align-items:center;
    padding:5px 9px;
    border-radius:999px;
    font-size:9px;
    font-weight:800;
    text-transform:capitalize;
}
.sections-badge.active{color:#16834f;background:#e8f8ef}
.sections-badge.inactive{color:#9a6700;background:#fff7d6}
.sections-badge.archived{color:#dc2626;background:#fff0f1}

.sections-actions{display:flex;gap:6px}
.sections-action{
    width:30px;
    height:30px;
    display:grid;
    place-items:center;
    border:1px solid #d7def1;
    border-radius:7px;
    background:var(--card-bg,#fff);
    color:#334155;
}
.sections-action svg{width:13px;height:13px}

.sections-footer{
    padding:12px 16px;
    border-top:1px solid var(--border-soft,#e7ebf3);
    display:flex;
    justify-content:space-between;
    align-items:center;
}
.sections-footer small{font-size:10px;color:var(--text-muted,#64748b)}
.sections-pages{display:flex;gap:5px}
.sections-pages button{
    width:30px;
    height:30px;
    border:1px solid var(--border-soft,#e7ebf3);
    background:var(--card-bg,#fff);
    border-radius:7px;
    font-size:10px;
}
.sections-pages button.active{
    color:#fff;
    border-color:transparent;
    background:linear-gradient(135deg,#6547e8,#315ed8);
}


#sectionModal .modal-dialog,
#assignmentModal .modal-dialog{
    max-height:calc(100dvh - 32px);
    margin:16px auto;
}
#sectionModal .modal-content,
#assignmentModal .modal-content{
    max-height:calc(100dvh - 32px);
    overflow:hidden;
}
#sectionModal form,
#assignmentModal form{
    display:flex;
    flex-direction:column;
    max-height:calc(100dvh - 32px);
}
#sectionModal .modal-body,
#assignmentModal .modal-body{
    overflow-y:auto;
    min-height:0;
}

@media(max-width:1250px){
    .sections-filter{grid-template-columns:repeat(2,1fr)}
}
@media(max-width:900px){
    .sections-stats{grid-template-columns:repeat(2,1fr)}
}
@media(max-width:575px){
    .sections-stats,.sections-filter{grid-template-columns:1fr}
    .sections-card-head{align-items:flex-start;flex-direction:column}
    .sections-card-head-actions{width:100%}
    .sections-card-head-actions .form-select{flex:1}
}
</style>

<!-- Build: 2026-08-10-sections-role-permissions-superadmin-platform-v3 -->
<div class="sections-page">
    <div class="page-heading">
        <div>
            <h1 class="page-title">Sections Management</h1>
            <p class="page-subtitle">Dashboard › Sections Management</p>
        </div>

        <div class="page-actions">
            <?php if ($canSectionPrint): ?>
                <a
                    id="printSections"
                    class="btn-ui"
                    target="_blank"
                    data-permission-action="print"
                >
                    <i data-lucide="printer"></i> Print
                </a>
            <?php endif; ?>

            <?php if ($canSectionPdf): ?>
                <a
                    id="exportSectionsPdf"
                    class="btn-ui"
                    target="_blank"
                    data-permission-action="pdf"
                >
                    <i data-lucide="file-text"></i> PDF
                </a>
            <?php endif; ?>

            <?php if ($canSectionExport): ?>
                <a
                    id="exportSectionsExcel"
                    class="btn-ui"
                    data-permission-action="export"
                >
                    <i data-lucide="download"></i> Export
                </a>
            <?php endif; ?>

            <?php if ($canSectionAdd): ?>
                <button
                    id="addSection"
                    class="btn-ui btn-primary-ui"
                    type="button"
                    data-permission-action="create"
                >
                    <i data-lucide="plus"></i> Add Section
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div id="sectionsMessage" class="alert sections-message"></div>

    <section class="sections-stats">
        <article class="sections-stat purple">
            <span class="sections-stat-icon">
                <i data-lucide="panels-top-left"></i>
            </span>
            <div>
                <small>Total Sections</small>
                <strong id="totalSections">0</strong>
                <div class="trend">All configured sections</div>
            </div>
        </article>

        <article class="sections-stat green">
            <span class="sections-stat-icon">
                <i data-lucide="school"></i>
            </span>
            <div>
                <small>Classes Mapped</small>
                <strong id="mappedClasses">0</strong>
                <div class="trend">Unique linked classes</div>
            </div>
        </article>

        <article class="sections-stat pink">
            <span class="sections-stat-icon">
                <i data-lucide="presentation"></i>
            </span>
            <div>
                <small>Class Teachers</small>
                <strong id="assignedSectionTeachers">0</strong>
                <div class="trend">Teacher assignments</div>
            </div>
        </article>

        <article class="sections-stat orange">
            <span class="sections-stat-icon">
                <i data-lucide="circle-check-big"></i>
            </span>
            <div>
                <small>Active Sections</small>
                <strong id="activeSections">0</strong>
                <div class="trend">Currently active records</div>
            </div>
        </article>
    </section>



    <section class="sections-panel active" data-panel="sections">
        <section class="sections-layout">
            <section class="ui-card sections-card">
                <div class="sections-card-head">
                    <strong>All Sections</strong>

                    <div class="sections-card-head-actions">
                        <select id="sectionsYear" class="form-select">
                            <option value="all">All Academic Years</option>
                        </select>

                        <button id="sectionsFilterToggle" class="btn-ui" type="button">
                            <i data-lucide="list-filter"></i> Filter
                        </button>
                    </div>
                </div>

                <div id="sectionsAdvancedFilters" class="sections-filter" style="display:none">
                    <input
                        id="sectionsSearch"
                        class="form-control"
                        placeholder="Search section, code, room, class or teacher..."
                    >

                    <select id="sectionsClass" class="form-select">
                        <option value="all">All Classes</option>
                    </select>

                    <select id="sectionsMedium" class="form-select">
                        <option value="all">All Mediums</option>
                    </select>

                    <select id="sectionsShift" class="form-select">
                        <option value="all">All Shifts</option>
                    </select>

                    <select id="sectionsStatus" class="form-select">
                        <option value="all">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="archived">Archived</option>
                    </select>
                </div>

                <div class="sections-table-wrap">
                    <table class="data-table sections-table">
                        <thead>
                            <tr>
                                <th>Section</th>
                                <th>Code</th>
                                <th>Class</th>
                                <th>Academic Year</th>
                                <th>Medium</th>
                                <th>Shift Time</th>
                                <th>Room</th>
                                <th>Capacity</th>
                                <th>Class Teacher</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody id="sectionsBody">
                            <tr>
                                <td colspan="11" class="text-center py-5">
                                    Loading sections...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="sections-footer">
                    <small id="sectionsCopy">Loading...</small>

                    <div class="sections-pages">
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


</div>

<div class="modal fade"
     id="sectionModal"
     tabindex="-1">
    <div class="modal-dialog modal-xl
                modal-dialog-centered">
        <div class="modal-content">
            <form id="sectionForm">
                <div class="modal-header">
                    <h5 id="sectionModalTitle"
                        class="modal-title">
                        Add Section
                    </h5>

                    <button class="btn-close"
                            type="button"
                            data-bs-dismiss="modal">
                    </button>
                </div>

                <div class="modal-body">
                    <input id="sectionId"
                           type="hidden">

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">
                                Section Name
                            </label>

                            <input id="sectionName"
                                   class="form-control"
                                   maxlength="100"
                                   required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">
                                Section Code
                            </label>

                            <input id="sectionCode"
                                   class="form-control
                                          text-uppercase"
                                   maxlength="30"
                                   required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">
                                Academic Year
                            </label>

                            <select id="sectionAcademicYear"
                                    class="form-select"
                                    required>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">
                                Class
                            </label>

                            <select id="sectionClass"
                                    class="form-select"
                                    required>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">
                                Medium
                            </label>

                            <select id="sectionMedium"
                                    class="form-select">
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">
                                Shift <span class="text-muted">(Optional)</span>
                            </label>

                            <select id="sectionShift"
                                    class="form-select">
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">
                                Room Number
                            </label>

                            <input id="sectionRoom"
                                   class="form-control"
                                   maxlength="50">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">
                                Maximum Student Capacity
                            </label>

                            <input id="sectionCapacity"
                                   class="form-control"
                                   type="number"
                                   min="1"
                                   max="500"
                                   value="40"
                                   required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">
                                Existing Class Teacher
                            </label>

                            <select id="sectionTeacher"
                                    class="form-select">
                                <option value="">
                                    Not assigned
                                </option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">
                                Status
                            </label>

                            <select id="sectionStatus"
                                    class="form-select">
                                <option value="active">
                                    Active
                                </option>
                                <option value="inactive">
                                    Inactive
                                </option>
                                <option value="archived">
                                    Archived
                                </option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">
                                Display Order
                            </label>

                            <input id="sectionDisplayOrder"
                                   class="form-control"
                                   type="number"
                                   min="0"
                                   value="0">
                        </div>

                        <div class="col-12">
                            <label class="form-label">
                                Description
                            </label>

                            <textarea id="sectionDescription"
                                      class="form-control"
                                      rows="3">
                            </textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn-ui"
                            type="button"
                            data-bs-dismiss="modal">
                        Cancel
                    </button>

                    <button class="btn-ui
                                   btn-primary-ui"
                            type="submit">
                        <i data-lucide="save"></i>
                        Save Section
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<script>
(function () {
    'use strict';

    /*
     * Resolve APIs relative to the current School Admin page.
     * This works correctly at:
     * /git/schoolerp/school/sections.php
     * without depending on BASE_URL configuration.
     */
    const apiUrl = new URL(
        '../api/sections.php',
        window.location.href
    ).href;

    const exportUrl = new URL(
        '../includes/lib/sections-export.php',
        window.location.href
    ).href;

    let csrfToken = <?= json_encode($csrfToken) ?>;
    let sections = [];
    let meta = {};

    let permissions = <?= json_encode([
        'view' => $canSectionView,
        'add' => $canSectionAdd,
        'create' => $canSectionAdd,
        'edit' => $canSectionEdit,
        'delete' => $canSectionDelete,
        'print' => $canSectionPrint,
        'pdf' => $canSectionPdf,
        'export' => $canSectionExport,
        'platform_full_access' => $sectionsPlatformFullAccess,
    ], JSON_UNESCAPED_SLASHES) ?>;

    /*
     * Existing General Settings data.
     * No new settings or toast system is created here.
     */
    const generalSettings = <?= json_encode(
        $sectionsGeneralSettings,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    ) ?>;

    const configuredShifts = <?= json_encode(
        $sectionsConfiguredShifts,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    ) ?>;

    const configuredShiftMap = new Map(
        configuredShifts.map(shift => [
            String(
                shift.shift_name
                || ''
            ).trim().toLowerCase(),
            shift
        ])
    );

    const escapeHtml = value => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    async function request(
        action,
        data = {},
        method = 'GET'
    ) {
        let response;

        if (method === 'GET') {
            const url = new URL(
                apiUrl,
                window.location.origin
            );

            url.searchParams.set(
                'action',
                action
            );

            Object.entries(data).forEach(
                ([key, value]) => {
                    if (
                        value !== ''
                        && value !== null
                        && value !== undefined
                    ) {
                        url.searchParams.set(
                            key,
                            String(value)
                        );
                    }
                }
            );

            response = await fetch(url, {
                headers: {
                    Accept: 'application/json'
                },
                credentials: 'same-origin'
            });
        } else {
            response = await fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type':
                        'application/json',
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

        const text = await response.text();
        let result;

        try {
            result = JSON.parse(text);
        } catch (error) {
            const preview = String(text || '')
                .replace(/<[^>]*>/g, ' ')
                .replace(/\s+/g, ' ')
                .trim()
                .slice(0, 300);

            throw new Error(
                `Sections API returned HTTP ${response.status}. `
                + (
                    preview
                        ? preview
                        : 'Invalid server response.'
                )
            );
        }

        if (!response.ok || !result.success) {
            throw new Error(
                result.message || 'Request failed.'
            );
        }

        return result;
    }

    function showMessage(
        text,
        success = false,
        title = ''
    ) {
        const type = success
            ? 'success'
            : 'error';

        /*
         * Use the ERP's existing global common toast only.
         * bootstrap/layout-start loads includes/common-toast.php.
         */
        if (
            typeof window.schoolToast
            === 'function'
        ) {
            window.schoolToast(
                type,
                text,
                title || (
                    success
                        ? 'Success'
                        : 'Action failed'
                )
            );

            return;
        }

        if (
            typeof window.showToast
            === 'function'
        ) {
            window.showToast(
                type,
                text,
                title || (
                    success
                        ? 'Success'
                        : 'Action failed'
                )
            );

            return;
        }

        /*
         * Plain alert fallback only if the global common toast
         * was not loaded for any reason.
         */
        const box = document.getElementById(
            'sectionsMessage'
        );

        box.className =
            'alert sections-message show '
            + (
                success
                    ? 'alert-success'
                    : 'alert-danger'
            );

        box.textContent = text;
    }

    function normalizeShiftName(value) {
        return String(
            value || ''
        )
            .trim()
            .toLowerCase();
    }

    function formatTimeFromGeneralSettings(value) {
        const text = String(
            value || ''
        ).trim();

        if (!text) {
            return '';
        }

        const match = text.match(
            /^(\d{1,2}):(\d{2})/
        );

        if (!match) {
            return text;
        }

        let hour = Number(
            match[1]
        );

        const minute =
            match[2];

        if (
            String(
                generalSettings.time_format
                || '12'
            ) === '24'
        ) {
            return String(hour)
                .padStart(2, '0')
                + ':'
                + minute;
        }

        const period =
            hour >= 12
                ? 'PM'
                : 'AM';

        hour = hour % 12 || 12;

        return hour
            + ':'
            + minute
            + ' '
            + period;
    }

    function configuredShiftByName(
        shiftName
    ) {
        return configuredShiftMap.get(
            normalizeShiftName(
                shiftName
            )
        ) || null;
    }

    function sectionShiftDisplay(
        shiftName
    ) {
        const name = String(
            shiftName || ''
        ).trim();

        if (!name) {
            return '-';
        }

        const shift =
            configuredShiftByName(
                name
            );

        if (!shift) {
            return name;
        }

        if (
            String(
                shift.display_label
                || ''
            ).trim()
        ) {
            return String(
                shift.display_label
            );
        }

        const start =
            formatTimeFromGeneralSettings(
                shift.start_time
            );

        const end =
            formatTimeFromGeneralSettings(
                shift.end_time
            );

        return (
            start
            && end
        )
            ? `${name} (${start} - ${end})`
            : name;
    }

    function sectionShiftTimeOnly(
        shiftName
    ) {
        const shift =
            configuredShiftByName(
                shiftName
            );

        if (!shift) {
            return '';
        }

        const start =
            formatTimeFromGeneralSettings(
                shift.start_time
            );

        const end =
            formatTimeFromGeneralSettings(
                shift.end_time
            );

        return (
            start
            && end
        )
            ? `${start} - ${end}`
            : '';
    }

    function currentFilters() {
        return {
            search:
                document.getElementById(
                    'sectionsSearch'
                ).value,
            academic_year_id:
                document.getElementById(
                    'sectionsYear'
                ).value,
            class_id:
                document.getElementById(
                    'sectionsClass'
                ).value,
            medium:
                document.getElementById(
                    'sectionsMedium'
                ).value,
            shift_name:
                document.getElementById(
                    'sectionsShift'
                ).value,
            status:
                document.getElementById(
                    'sectionsStatus'
                ).value
        };
    }

    function updateExportLinks() {
        const query = new URLSearchParams(
            currentFilters()
        );

        query.set(
            'page_key',
            'sections'
        );

        const printButton =
            document.getElementById(
                'printSections'
            );

        const pdfButton =
            document.getElementById(
                'exportSectionsPdf'
            );

        const exportButton =
            document.getElementById(
                'exportSectionsExcel'
            );

        if (
            printButton
            && permissions.print
        ) {
            const printQuery =
                new URLSearchParams(
                    query
                );

            printQuery.set(
                'permission_action',
                'print'
            );

            printButton.href =
                exportUrl
                + '?format=print&'
                + printQuery;
        }

        if (
            pdfButton
            && permissions.pdf
        ) {
            const pdfQuery =
                new URLSearchParams(
                    query
                );

            pdfQuery.set(
                'permission_action',
                'pdf'
            );

            pdfButton.href =
                exportUrl
                + '?format=pdf&'
                + pdfQuery;
        }

        if (
            exportButton
            && permissions.export
        ) {
            const exportQuery =
                new URLSearchParams(
                    query
                );

            exportQuery.set(
                'permission_action',
                'export'
            );

            exportButton.href =
                exportUrl
                + '?format=excel&'
                + exportQuery;
        }
    }

    function applySectionActionPermissions() {
        const buttonMap = {
            print: 'printSections',
            pdf: 'exportSectionsPdf',
            export: 'exportSectionsExcel',
            create: 'addSection'
        };

        Object.entries(
            buttonMap
        ).forEach(
            ([action, elementId]) => {
                const element =
                    document.getElementById(
                        elementId
                    );

                if (!element) {
                    return;
                }

                const allowed =
                    action === 'create'
                        ? Boolean(
                            permissions.create
                            || permissions.add
                        )
                        : Boolean(
                            permissions[action]
                        );

                element.style.display =
                    allowed
                        ? ''
                        : 'none';
            }
        );
    }

    function renderSections() {
        const body = document.getElementById('sectionsBody');

        body.innerHTML = sections.map(section => `
            <tr data-id="${Number(section.id)}">
                <td>
                    <div class="sections-name-cell">
                        <span class="sections-avatar">
                            ${escapeHtml((section.section_name || '?').charAt(0).toUpperCase())}
                        </span>
                        <strong>${escapeHtml(section.section_name)}</strong>
                    </div>
                </td>
                <td>${escapeHtml(section.section_code)}</td>
                <td>${escapeHtml(section.class_name_snapshot || '-')}</td>
                <td>${escapeHtml(section.academic_year_name || '-')}</td>
                <td>${escapeHtml(section.medium)}</td>
                <td>
                    <strong>
                        ${escapeHtml(
                            section.shift_name
                            || '-'
                        )}
                    </strong>
                    ${
                        sectionShiftTimeOnly(
                            section.shift_name
                        )
                            ? `
                                <small
                                    class="d-block text-muted"
                                    style="font-size:8px;margin-top:2px"
                                >
                                    ${escapeHtml(
                                        sectionShiftTimeOnly(
                                            section.shift_name
                                        )
                                    )}
                                </small>
                            `
                            : ''
                    }
                </td>
                <td>${escapeHtml(section.room_number || '-')}</td>
                <td>${Number(section.maximum_student_capacity)}</td>
                <td>${escapeHtml(section.class_teacher_name || '-')}</td>
                <td>
                    <span class="sections-badge ${escapeHtml(section.status)}">
                        ${escapeHtml(section.status)}
                    </span>
                </td>
                <td>
                    <div class="sections-actions">
                        ${permissions.view ? `
                            <button
                                class="sections-action js-section-view"
                                type="button"
                                title="View"
                            >
                                <i data-lucide="eye"></i>
                            </button>
                        ` : ''}

                        ${permissions.edit ? `
                            <button
                                class="sections-action js-section-edit"
                                type="button"
                                title="Edit"
                            >
                                <i data-lucide="pencil"></i>
                            </button>
                        ` : ''}

                        ${permissions.delete ? `
                            <button
                                class="sections-action js-section-delete"
                                type="button"
                                title="Delete"
                            >
                                <i data-lucide="trash-2"></i>
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `).join('') || `
            <tr>
                <td colspan="11" class="text-center py-5">
                    No sections found.
                </td>
            </tr>
        `;

        document.getElementById('sectionsCopy').textContent =
            `Showing ${sections.length} section${sections.length === 1 ? '' : 's'}`;

        document.getElementById('totalSections').textContent =
            String(sections.length);

        document.getElementById('mappedClasses').textContent =
            String(new Set(sections.map(section => section.class_id)).size);

        document.getElementById('assignedSectionTeachers').textContent =
            String(sections.filter(
                section => Number(section.class_teacher_user_id) > 0
            ).length);

        document.getElementById('activeSections').textContent =
            String(sections.filter(
                section => section.status === 'active'
            ).length);

        body.querySelectorAll(
            '.js-section-view, .js-section-edit'
        ).forEach(button => {
            button.addEventListener('click', () => {
                const row = button.closest('tr[data-id]');
                const section = sections.find(
                    value => Number(value.id) === Number(row.dataset.id)
                );

                openSection(
                    section,
                    button.classList.contains('js-section-view')
                );
            });
        });

        body.querySelectorAll('.js-section-delete').forEach(button => {
            button.addEventListener('click', async () => {
                if (!permissions.delete) {
                    showMessage(
                        'You do not have permission to delete sections.',
                        false
                    );

                    return;
                }

                const row = button.closest('tr[data-id]');

                if (!window.confirm(
                    'Delete this section and its assignments?'
                )) {
                    return;
                }

                try {
                    const result = await request(
                        'delete',
                        { id: Number(row.dataset.id) },
                        'POST'
                    );

                    showMessage(result.message, true);
                    await loadSections();
                } catch (error) {
                    showMessage(error.message, false);
                }
            });
        });

        updateExportLinks();
        window.lucide?.createIcons();
    }

    function openSection(
        section = null,
        viewOnly = false
    ) {
        if (
            viewOnly
            && !permissions.view
        ) {
            showMessage(
                'You do not have permission to view sections.',
                false
            );

            return;
        }

        if (
            section
            && !viewOnly
            && !permissions.edit
        ) {
            showMessage(
                'You do not have permission to edit sections.',
                false
            );

            return;
        }

        if (
            !section
            && !viewOnly
            && !(
                permissions.add
                || permissions.create
            )
        ) {
            showMessage(
                'You do not have permission to add sections.',
                false
            );

            return;
        }

        const form = document.getElementById(
            'sectionForm'
        );

        form.reset();

        document.getElementById(
            'sectionId'
        ).value = section?.id || '';

        document.getElementById(
            'sectionModalTitle'
        ).textContent = viewOnly
            ? 'View Section'
            : (
                section
                    ? 'Edit Section'
                    : 'Add Section'
            );

        const fieldMap = {
            sectionName: 'section_name',
            sectionCode: 'section_code',
            sectionAcademicYear:
                'academic_year_id',
            sectionClass: 'class_id',
            sectionMedium: 'medium',
            sectionShift: 'shift_name',
            sectionRoom: 'room_number',
            sectionCapacity:
                'maximum_student_capacity',
            sectionTeacher:
                'class_teacher_user_id',
            sectionStatus: 'status',
            sectionDescription: 'description',
            sectionDisplayOrder:
                'display_order'
        };

        Object.entries(fieldMap).forEach(
            ([elementId, key]) => {
                const element =
                    document.getElementById(
                        elementId
                    );

                const defaultValue =
                    elementId === 'sectionCapacity'
                        ? 40
                        : (
                            elementId
                            === 'sectionDisplayOrder'
                                ? 0
                                : ''
                        );

                element.value =
                    section?.[key]
                    ?? defaultValue;
            }
        );

        /*
         * Existing rows may contain a Shift that is now disabled.
         * Keep it visible while viewing/editing that old record,
         * but new records can choose only enabled General Settings shifts.
         */
        if (
            section
            && section.shift_name
            && !configuredShiftByName(
                section.shift_name
            )
        ) {
            const shiftSelect =
                document.getElementById(
                    'sectionShift'
                );

            const option =
                document.createElement(
                    'option'
                );

            option.value =
                section.shift_name;

            option.textContent =
                section.shift_name
                + ' (Disabled in General Settings)';

            shiftSelect.appendChild(
                option
            );

            shiftSelect.value =
                section.shift_name;
        }

        document.querySelectorAll(
            '#sectionForm input, '
            + '#sectionForm select, '
            + '#sectionForm textarea'
        ).forEach(element => {
            element.disabled = viewOnly;
        });

        document.querySelector(
            '#sectionForm button[type="submit"]'
        ).style.display = viewOnly
            ? 'none'
            : '';

        window.bootstrap.Modal
            .getOrCreateInstance(
                document.getElementById(
                    'sectionModal'
                )
            )
            .show();
    }

    async function loadSections() {
        try {
            const result = await request(
                'list',
                currentFilters()
            );

            sections =
                result.data.sections || [];

            renderSections();
        } catch (error) {
            showMessage(
                error.message,
                false
            );
        }
    }

    function fillSelect(
        elementId,
        rows,
        valueKey,
        labelKey,
        preserveFirst = false
    ) {
        const element =
            document.getElementById(elementId);

        const first = preserveFirst
            ? element.options[0]?.outerHTML || ''
            : '';

        element.innerHTML =
            first
            + rows.map(
                row => `
                    <option value="${
                        escapeHtml(row[valueKey])
                    }">
                        ${escapeHtml(row[labelKey])}
                    </option>
                `
            ).join('');
    }

    function fillSimple(
        elementId,
        values,
        preserveFirst = false
    ) {
        const element =
            document.getElementById(elementId);

        const first = preserveFirst
            ? element.options[0]?.outerHTML || ''
            : '';

        element.innerHTML =
            first
            + values.map(
                value => `
                    <option value="${
                        escapeHtml(value)
                    }">
                        ${escapeHtml(value)}
                    </option>
                `
            ).join('');
    }

    async function initialize() {
        try {
            const result = await request('meta');

            csrfToken =
                result.data.csrf_token
                || csrfToken;

            meta = result.data.meta || {};

            permissions = {
                ...permissions,
                ...(
                    result.data.permissions
                    || {}
                )
            };

            applySectionActionPermissions();

            fillSelect(
                'sectionAcademicYear',
                meta.academic_years || [],
                'id',
                'year_name'
            );

            fillSelect(
                'sectionsYear',
                meta.academic_years || [],
                'id',
                'year_name',
                true
            );

            fillSelect(
                'sectionClass',
                meta.classes || [],
                'id',
                'class_name'
            );

            fillSelect(
                'sectionsClass',
                meta.classes || [],
                'id',
                'class_name',
                true
            );

            fillSelect(
                'sectionTeacher',
                meta.teachers || [],
                'id',
                'teacher_name',
                true
            );

            fillSimple(
                'sectionMedium',
                meta.mediums || []
            );

            fillSimple(
                'sectionsMedium',
                meta.mediums || [],
                true
            );

            /*
             * Shift Time comes only from General Settings.
             * Disabled shifts are not shown.
             */
            const enabledShiftNames =
                configuredShifts.map(
                    shift =>
                        String(
                            shift.shift_name
                            || ''
                        ).trim()
                ).filter(Boolean);

            const sectionShiftSelect =
                document.getElementById(
                    'sectionShift'
                );

            sectionShiftSelect.innerHTML =
                `
                    <option value="">
                        No Shift / Optional
                    </option>
                `
                + enabledShiftNames.map(
                    shiftName => `
                        <option
                            value="${escapeHtml(shiftName)}"
                        >
                            ${escapeHtml(
                                sectionShiftDisplay(
                                    shiftName
                                )
                            )}
                        </option>
                    `
                ).join('');

            const sectionShiftFilter =
                document.getElementById(
                    'sectionsShift'
                );

            sectionShiftFilter.innerHTML =
                `
                    <option value="all">
                        All Shifts
                    </option>
                `
                + enabledShiftNames.map(
                    shiftName => `
                        <option
                            value="${escapeHtml(shiftName)}"
                        >
                            ${escapeHtml(
                                sectionShiftDisplay(
                                    shiftName
                                )
                            )}
                        </option>
                    `
                ).join('');

            /*
             * Keep metadata in sync for code that already reads meta.shifts.
             */
            meta.shifts =
                configuredShifts;

            await loadSections();
        } catch (error) {
            showMessage(
                error.message,
                false
            );
        }
    }

    document.getElementById(
        'sectionForm'
    ).addEventListener(
        'submit',
        async event => {
            event.preventDefault();

            const sectionId = Number(
                document.getElementById(
                    'sectionId'
                ).value || 0
            );

            if (
                sectionId > 0
                && !permissions.edit
            ) {
                showMessage(
                    'You do not have permission to edit sections.',
                    false
                );

                return;
            }

            if (
                sectionId <= 0
                && !(
                    permissions.add
                    || permissions.create
                )
            ) {
                showMessage(
                    'You do not have permission to add sections.',
                    false
                );

                return;
            }

            const classSelect =
                document.getElementById(
                    'sectionClass'
                );

            const teacherSelect =
                document.getElementById(
                    'sectionTeacher'
                );

            const selectedShiftName =
                document.getElementById(
                    'sectionShift'
                ).value;

            /*
             * Shift is optional.
             * Validate only when a Shift is selected.
             */
            if (
                selectedShiftName
                && !configuredShiftByName(
                    selectedShiftName
                )
            ) {
                showMessage(
                    'Selected Shift Time is disabled in General Settings. Enable it first or choose No Shift.',
                    false,
                    'Shift Not Available'
                );
                return;
            }

            const data = {
                id:
                    sectionId,
                section_name:
                    document.getElementById(
                        'sectionName'
                    ).value,
                section_code:
                    document.getElementById(
                        'sectionCode'
                    ).value,
                academic_year_id: Number(
                    document.getElementById(
                        'sectionAcademicYear'
                    ).value
                ),
                class_id: Number(
                    classSelect.value
                ),
                class_name_snapshot:
                    classSelect.options[
                        classSelect.selectedIndex
                    ]?.text || '',
                medium:
                    document.getElementById(
                        'sectionMedium'
                    ).value,
                shift_name:
                    selectedShiftName,
                room_number:
                    document.getElementById(
                        'sectionRoom'
                    ).value,
                maximum_student_capacity:
                    Number(
                        document.getElementById(
                            'sectionCapacity'
                        ).value
                    ),
                class_teacher_user_id:
                    Number(
                        teacherSelect.value || 0
                    ),
                class_teacher_name:
                    teacherSelect.options[
                        teacherSelect.selectedIndex
                    ]?.text || '',
                status:
                    document.getElementById(
                        'sectionStatus'
                    ).value,
                description:
                    document.getElementById(
                        'sectionDescription'
                    ).value,
                display_order:
                    Number(
                        document.getElementById(
                            'sectionDisplayOrder'
                        ).value || 0
                    )
            };

            try {
                const result = await request(
                    'save',
                    data,
                    'POST'
                );

                window.bootstrap.Modal
                    .getInstance(
                        document.getElementById(
                            'sectionModal'
                        )
                    )
                    ?.hide();

                showMessage(
                    result.message,
                    true
                );

                await loadSections();
            } catch (error) {
                showMessage(
                    error.message,
                    false
                );
            }
        }
    );

    document.getElementById(
        'sectionsFilterToggle'
    ).addEventListener('click', () => {
        const box = document.getElementById(
            'sectionsAdvancedFilters'
        );

        box.style.display =
            box.style.display === 'none'
                ? 'grid'
                : 'none';
    });


    const addSectionButton =
        document.getElementById(
            'addSection'
        );

    if (addSectionButton) {
        addSectionButton.addEventListener(
            'click',
            () => openSection()
        );
    }

    [
        'sectionsSearch',
        'sectionsYear',
        'sectionsClass',
        'sectionsMedium',
        'sectionsShift',
        'sectionsStatus'
    ].forEach(id => {
        const element =
            document.getElementById(id);

        element.addEventListener(
            id === 'sectionsSearch'
                ? 'input'
                : 'change',
            loadSections
        );
    });

    initialize();
})();
</script>

<?php require dirname(__DIR__)
    . '/includes/layout-end.php'; ?>

<?php
declare(strict_types=1);

$pageTitle = 'Subject Management';
$pageKey = 'subjects';
$sidebarFile = __DIR__ . '/sidebar.php';

require dirname(__DIR__) . '/includes/layout-start.php';

$csrfToken = function_exists('csrfToken') ? csrfToken() : '';

$subjectCurrentUser = function_exists('current_user')
    ? current_user()
    : [];

$subjectCurrentUser = is_array($subjectCurrentUser)
    ? $subjectCurrentUser
    : [];

$subjectRoleText = strtolower(trim((string)(
    $subjectCurrentUser['role_key']
    ?? $subjectCurrentUser['role_name']
    ?? $subjectCurrentUser['role']
    ?? $subjectCurrentUser['user_type']
    ?? $_SESSION['role_key']
    ?? $_SESSION['role_name']
    ?? $_SESSION['role']
    ?? ''
)));

$subjectRoleKey = preg_replace('/[^a-z0-9]+/', '_', $subjectRoleText) ?? '';

$subjectPlatformFullAccess = in_array(
    trim($subjectRoleKey, '_'),
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
        $subjectPlatformFullAccess =
            $subjectPlatformFullAccess
            || (bool)is_super_admin();
    } catch (Throwable) {
    }
}

$capabilities = $subjectPlatformFullAccess
    ? [
        'view' => true,
        'add' => true,
        'create' => true,
        'edit' => true,
        'delete' => true,
        'print' => true,
        'pdf' => true,
        'export' => true,
        'import' => true,
    ]
    : (
        function_exists('school_current_page_capabilities')
            ? school_current_page_capabilities($pageKey)
            : []
    );

$canView = !empty($capabilities['view']);
$canAdd = !empty($capabilities['add'] ?? $capabilities['create'] ?? false);
$canEdit = !empty($capabilities['edit']);
$canDelete = !empty($capabilities['delete']);
$canPrint = !empty($capabilities['print']);
$canPdf = !empty($capabilities['pdf']);
$canExport = !empty($capabilities['export']);
$canImport = !empty($capabilities['import']);
?>
<style>
.subject-page{display:grid;gap:16px}
.subject-page .page-title{font-size:28px;line-height:1.1}
.subject-page .page-subtitle{margin-top:4px}
.subject-page .page-actions{display:flex;align-items:center;gap:9px;flex-wrap:wrap}
.subject-message{display:none}
.subject-message.show{display:block}

.subject-stats{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:14px;
}
.subject-stat{
    position:relative;
    min-height:112px;
    overflow:hidden;
    display:flex;
    align-items:center;
    gap:14px;
    padding:18px 20px;
    border:0;
    border-radius:14px;
    color:#fff;
    box-shadow:0 12px 28px rgba(15,23,42,.08);
}
.subject-stat::after{
    content:"";
    position:absolute;
    width:112px;
    height:112px;
    right:-38px;
    top:-42px;
    border-radius:50%;
    background:rgba(255,255,255,.09);
}
.subject-stat.purple{background:linear-gradient(135deg,#7448e8,#4b36cf)}
.subject-stat.green{background:linear-gradient(135deg,#45c783,#1ea568)}
.subject-stat.pink{background:linear-gradient(135deg,#ff527c,#ed2f63)}
.subject-stat.orange{background:linear-gradient(135deg,#ffb327,#ff8a17)}
.subject-stat-icon{
    width:50px;height:50px;flex:0 0 auto;
    display:grid;place-items:center;border-radius:50%;
    background:rgba(255,255,255,.16)
}
.subject-stat-icon svg{width:25px;height:25px}
.subject-stat small{display:block;margin-bottom:6px;font-size:11px;font-weight:700;opacity:.94}
.subject-stat strong{display:block;font-size:26px;line-height:1}
.subject-stat .trend{margin-top:8px;font-size:9px;font-weight:700;opacity:.94}

.subject-filter-card{padding:14px 16px}
.subject-filter-grid{
    display:grid;
    grid-template-columns:minmax(230px,1.4fr) minmax(180px,.75fr) minmax(160px,.65fr) auto;
    gap:10px;
    align-items:center;
}
.subject-reset{min-height:42px}

.subject-class-card{overflow:hidden;border-radius:14px}
.subject-class-head{
    padding:16px 18px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    border-bottom:1px solid var(--border-soft,#e7ebf3);
}
.subject-class-head strong{display:block;color:var(--text-main,#111b46);font-size:14px}
.subject-class-head small{display:block;margin-top:3px;color:var(--text-muted,#64748b);font-size:9px}
.subject-view-all{
    border:0;padding:0;color:#2563eb;background:transparent;
    font-size:10px;font-weight:800;text-decoration:underline;text-underline-offset:2px;
}
.subject-class-tabs{
    display:flex;gap:8px;overflow:auto;min-width:0;
    padding:14px 16px;
    border-bottom:1px solid var(--border-soft,#e7ebf3);
}
.subject-class-tab{
    display:inline-flex;align-items:center;gap:9px;flex:0 0 auto;
    min-height:42px;padding:8px 12px;
    border:1px solid var(--border-soft,#e1e7f0);
    border-radius:10px;background:var(--card-bg,#fff);
    color:var(--text-main,#111b46);
    font-size:10px;font-weight:850;white-space:nowrap;transition:.18s ease;
}
.subject-class-tab:hover{border-color:#b6c4ec;transform:translateY(-1px)}
.subject-class-tab.active{
    border-color:transparent;color:#fff;
    background:linear-gradient(135deg,#6949e8,#315fde);
    box-shadow:0 8px 20px rgba(79,70,229,.16);
}
.subject-class-count{
    min-width:23px;height:23px;display:grid;place-items:center;
    padding:0 6px;border-radius:999px;background:#eef1f6;
    color:#64748b;font-size:9px;font-weight:850;
}
.subject-class-tab.active .subject-class-count{background:rgba(255,255,255,.18);color:#fff}

.subject-table-wrap{overflow:auto}
.subject-table{min-width:1050px}
.subject-table th{font-size:10px;white-space:nowrap}
.subject-table td{font-size:11px;vertical-align:middle}
.subject-name{display:flex;align-items:center;gap:9px}
.subject-avatar{
    width:31px;height:31px;flex:0 0 auto;display:grid;place-items:center;
    border-radius:50%;color:#fff;font-size:11px;font-weight:850;
    background:linear-gradient(135deg,#6d4ce7,#345fe0)
}
.subject-name strong{display:block;font-size:11px}
.subject-name small{display:block;margin-top:2px;color:#8490a3;font-size:8px}
.subject-book{max-width:240px;white-space:normal;color:#475569}
.subject-status{
    display:inline-flex;align-items:center;padding:5px 9px;border-radius:999px;
    font-size:9px;font-weight:800;text-transform:capitalize
}
.subject-status.active{color:#16834f;background:#e8f8ef}
.subject-status.inactive{color:#9a6700;background:#fff7d6}
.subject-status.archived{color:#dc2626;background:#fff0f1}
.subject-actions{display:flex;align-items:center;gap:6px}
.subject-action{
    width:30px;height:30px;display:grid;place-items:center;
    border:1px solid #d7def1;border-radius:7px;background:var(--card-bg,#fff);color:#334155
}
.subject-action:hover{color:#315ed8;border-color:#9eb2ef}
.subject-action.danger:hover{color:#dc2626;border-color:#fecaca;background:#fff7f7}
.subject-action svg{width:13px;height:13px}
.subject-table-footer{
    padding:12px 16px;display:flex;align-items:center;justify-content:space-between;
    gap:12px;border-top:1px solid var(--border-soft,#e7ebf3)
}
.subject-table-footer small{color:var(--text-muted,#64748b);font-size:9px}

.subject-all-groups{display:grid;gap:14px;padding:14px 16px 16px}
.subject-group{
    overflow:hidden;border:1px solid var(--border-soft,#e7ebf3);
    border-radius:12px;background:var(--card-bg,#fff)
}
.subject-group-head{
    padding:11px 13px;display:flex;align-items:center;justify-content:space-between;
    gap:10px;background:#f8faff;border-bottom:1px solid var(--border-soft,#e7ebf3)
}
.subject-group-head strong{font-size:11px;color:#18234b}
.subject-group-head span{
    padding:4px 7px;border-radius:999px;background:#eef2ff;
    color:#4f46e5;font-size:8px;font-weight:850
}
.subject-empty{padding:45px 16px!important;text-align:center}
.subject-empty-icon{
    width:46px;height:46px;display:grid;place-items:center;margin:0 auto 9px;
    border-radius:14px;color:#5c4bd8;background:#f0efff
}
.subject-empty-icon svg{width:22px;height:22px}
.subject-empty strong{display:block;color:#25314f;font-size:11px}
.subject-empty small{display:block;margin-top:4px;color:#8792a5;font-size:9px}

#subjectModal .modal-dialog{max-height:calc(100dvh - 32px);margin:16px auto}
#subjectModal .modal-content{max-height:calc(100dvh - 32px);overflow:hidden}
#subjectModal form{display:flex;flex-direction:column;max-height:calc(100dvh - 32px)}
#subjectModal .modal-body{min-height:0;overflow-y:auto}
.subject-modal-note{
    padding:10px 12px;margin-bottom:14px;border:1px solid #dce7f5;
    border-radius:10px;background:#f8fbff;color:#64748b;font-size:9px;line-height:1.5
}

@media(max-width:1050px){
    .subject-filter-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
    .subject-stats{grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media(max-width:650px){
    .subject-page .page-heading{align-items:flex-start;gap:12px}
    .subject-page .page-actions{width:100%}
    .subject-page .page-actions .btn-ui{flex:1 1 auto;justify-content:center}
    .subject-filter-grid,.subject-stats{grid-template-columns:1fr}
    .subject-class-head{align-items:flex-start}
}
</style>

<div class="subject-page">
    <div class="page-heading">
        <div>
            <h1 class="page-title">Subject Management</h1>
            <p class="page-subtitle">Dashboard › Class-Wise Subject Management</p>
        </div>

        <div class="page-actions">
            <?php if ($canPrint): ?>
                <a id="printSubjects" class="btn-ui" target="_blank" data-permission-action="print">
                    <i data-lucide="printer"></i> Print
                </a>
            <?php endif; ?>

            <?php if ($canPdf): ?>
                <a id="exportSubjectsPdf" class="btn-ui" target="_blank" data-permission-action="pdf">
                    <i data-lucide="file-text"></i> PDF
                </a>
            <?php endif; ?>

            <?php if ($canExport): ?>
                <a id="exportSubjectsExcel" class="btn-ui" data-permission-action="export">
                    <i data-lucide="download"></i> Export
                </a>
            <?php endif; ?>

            <?php if ($canImport): ?>
                <button id="importSubjects" class="btn-ui" type="button" data-permission-action="import">
                    <i data-lucide="upload"></i> Import Subjects
                </button>
            <?php endif; ?>

            <?php if ($canAdd): ?>
                <button id="addSubject" class="btn-ui btn-primary-ui" type="button" data-permission-action="create">
                    <i data-lucide="plus"></i> Add Subject
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div id="subjectMessage" class="alert subject-message"></div>

    <section class="subject-stats">
        <article class="subject-stat purple">
            <span class="subject-stat-icon"><i data-lucide="book-open-text"></i></span>
            <div>
                <small>Total Subjects</small>
                <strong id="totalSubjects">0</strong>
                <div class="trend">Current academic year</div>
            </div>
        </article>

        <article class="subject-stat green">
            <span class="subject-stat-icon"><i data-lucide="layout-grid"></i></span>
            <div>
                <small>Classes With Subjects</small>
                <strong id="classesWithSubjects">0</strong>
                <div class="trend">Class-wise assignments</div>
            </div>
        </article>

        <article class="subject-stat pink">
            <span class="subject-stat-icon"><i data-lucide="book-marked"></i></span>
            <div>
                <small>Books Assigned</small>
                <strong id="booksAssigned">0</strong>
                <div class="trend">Books linked to subjects</div>
            </div>
        </article>

        <article class="subject-stat orange">
            <span class="subject-stat-icon"><i data-lucide="circle-check-big"></i></span>
            <div>
                <small>Active Subjects</small>
                <strong id="activeSubjects">0</strong>
                <div class="trend">Currently active records</div>
            </div>
        </article>
    </section>

    <section class="ui-card subject-filter-card">
        <div class="subject-filter-grid">
            <input id="subjectSearch" class="form-control"
                   placeholder="Search subject, subject code, book or teacher...">

            <select id="subjectYear" class="form-select"></select>

            <select id="subjectStatusFilter" class="form-select">
                <option value="all">All Statuses</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="archived">Archived</option>
            </select>

            <button id="resetSubjectFilters" class="btn-ui subject-reset" type="button">
                <i data-lucide="rotate-ccw"></i> Reset
            </button>
        </div>
    </section>

    <section class="ui-card subject-class-card">
        <div class="subject-class-head">
            <div>
                <strong id="selectedClassTitle">Class Subjects</strong>
                <small id="selectedClassSubtitle">Loading class-wise subjects...</small>
            </div>

            <button id="viewAllSubjects" class="subject-view-all" type="button">
                View All
            </button>
        </div>

        <div id="subjectClassTabs" class="subject-class-tabs"></div>

        <div id="singleClassView">
            <div class="subject-table-wrap">
                <table class="data-table subject-table">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Subject Code</th>
                            <th>Book Name</th>
                            <th>Academic Year</th>
                            <th>Type</th>
                            <th>Teacher</th>
                            <th>Marks</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="subjectBody">
                        <tr><td colspan="9" class="text-center py-5">Loading subjects...</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="subject-table-footer">
                <small id="subjectCopy">Loading...</small>
                <small id="subjectYearCopy"></small>
            </div>
        </div>

        <div id="allClassView" class="subject-all-groups" style="display:none"></div>
    </section>
</div>


<?php if ($canImport): ?>
<div class="modal fade" id="subjectImportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="subjectImportForm" enctype="multipart/form-data">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Import Subjects</h5>
                        <small class="text-muted">Bulk import class-wise subjects using CSV or XLSX.</small>
                    </div>
                    <button class="btn-close" type="button" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="subject-modal-note">
                        <strong>Required:</strong> Academic Year, Class, Subject Name, Subject Code.<br>
                        <strong>Optional:</strong> Book Name, Status, Subject Type, Department,
                        Subject Group, Maximum Marks, Pass Marks, Subject Teacher, Description.<br>
                        Academic Year and Class must match existing records for this school.
                    </div>

                    <div class="d-flex gap-2 flex-wrap mb-3">
                        <a id="downloadSubjectCsvTemplate" class="btn-ui" href="#" target="_blank">
                            <i data-lucide="file-down"></i> Download CSV Template
                        </a>
                        <a id="downloadSubjectXlsxTemplate" class="btn-ui" href="#" target="_blank">
                            <i data-lucide="file-spreadsheet"></i> Download XLSX Template
                        </a>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">CSV / XLSX File *</label>
                        <input
                            id="subjectImportFile"
                            name="import_file"
                            class="form-control"
                            type="file"
                            accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                            required
                        >
                        <small class="text-muted">Maximum 2,000 rows and 10 MB.</small>
                    </div>

                    <div id="subjectImportResult" class="alert alert-light border" style="display:none"></div>
                </div>
                <div class="modal-footer">
                    <button class="btn-ui" type="button" data-bs-dismiss="modal">Cancel</button>
                    <button id="startSubjectImport" class="btn-ui btn-primary-ui" type="submit">
                        <i data-lucide="upload"></i> Import Subjects
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="modal fade" id="subjectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <form id="subjectForm">
                <div class="modal-header">
                    <div>
                        <h5 id="subjectModalTitle" class="modal-title">Add Subject</h5>
                        <small class="text-muted">
                            Add the subject separately to its Academic Year and Class.
                        </small>
                    </div>
                    <button class="btn-close" type="button" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <input id="subjectId" type="hidden">

                    <div class="subject-modal-note">
                        A subject is isolated by <strong>Academic Year + Class</strong>.
                        Adding Mathematics to Class 1 does not add it to Class 2.
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Academic Year *</label>
                            <select id="subjectAcademicYear" class="form-select" required></select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Class *</label>
                            <select id="subjectClass" class="form-select" required></select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Status *</label>
                            <select id="subjectStatus" class="form-select" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Subject Name *</label>
                            <input id="subjectName" class="form-control" maxlength="150" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Subject Code *</label>
                            <input id="subjectCode" class="form-control text-uppercase" maxlength="50" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Book Name</label>
                            <input id="subjectBookName" class="form-control" maxlength="200">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Subject Type</label>
                            <select id="subjectType" class="form-select">
                                <option value="core">Core</option>
                                <option value="elective">Elective</option>
                                <option value="language">Language</option>
                                <option value="practical">Practical</option>
                                <option value="activity">Activity</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Department</label>
                            <input id="subjectDepartment" class="form-control" maxlength="100">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Subject Group</label>
                            <input id="subjectGroup" class="form-control" maxlength="100">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Maximum Marks *</label>
                            <input id="subjectMaximumMarks" class="form-control" type="number" min="1" max="1000" value="100" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Pass Marks *</label>
                            <input id="subjectPassMarks" class="form-control" type="number" min="0" max="1000" value="35" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Subject Teacher</label>
                            <select id="subjectTeacher" class="form-select">
                                <option value="">Not assigned</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea id="subjectDescription" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn-ui" type="button" data-bs-dismiss="modal">Cancel</button>
                    <button id="saveSubjectButton" class="btn-ui btn-primary-ui" type="submit">
                        <i data-lucide="save"></i> Save Subject
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function(){
'use strict';

const apiUrl=new URL('../api/subjects.php',window.location.href).href;
let csrfToken=<?= json_encode($csrfToken) ?>;
let meta={};
let allSubjects=[];
let filteredSubjects=[];
let selectedClassId=0;
let viewAllMode=false;
let permissions=<?= json_encode([
    'view'=>true,
    'create'=>$canAdd,
    'add'=>$canAdd,
    'edit'=>$canEdit,
    'delete'=>$canDelete,
    'print'=>$canPrint,
    'pdf'=>$canPdf,
    'export'=>$canExport,
    'import'=>$canImport,
    'platform_full_access'=>$subjectPlatformFullAccess,
],JSON_UNESCAPED_SLASHES) ?>;

const $=id=>document.getElementById(id);
const esc=value=>String(value??'')
    .replace(/&/g,'&amp;')
    .replace(/</g,'&lt;')
    .replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;')
    .replace(/'/g,'&#039;');

function showMessage(text,success=false,title=''){
    const type=success?'success':'error';

    if(typeof window.schoolToast==='function'){
        window.schoolToast(type,text,title||(success?'Success':'Action failed'));
        return;
    }

    if(typeof window.showToast==='function'){
        window.showToast(type,text,title||(success?'Success':'Action failed'));
        return;
    }

    const box=$('subjectMessage');
    box.className='alert subject-message show '+(success?'alert-success':'alert-danger');
    box.textContent=text;
}

async function request(action,data={},method='GET'){
    let response;

    if(method==='GET'){
        const url=new URL(apiUrl);
        url.searchParams.set('action',action);

        Object.entries(data).forEach(([key,value])=>{
            if(value!==''&&value!==null&&value!==undefined){
                url.searchParams.set(key,String(value));
            }
        });

        response=await fetch(url,{
            headers:{Accept:'application/json'},
            credentials:'same-origin'
        });
    }else{
        response=await fetch(apiUrl,{
            method:'POST',
            headers:{
                'Content-Type':'application/json',
                Accept:'application/json'
            },
            credentials:'same-origin',
            body:JSON.stringify({
                action,
                csrf_token:csrfToken,
                ...data
            })
        });
    }

    const text=await response.text();
    let result;

    try{
        result=JSON.parse(text);
    }catch{
        const preview=String(text||'')
            .replace(/<[^>]*>/g,' ')
            .replace(/\s+/g,' ')
            .trim()
            .slice(0,300);

        throw new Error(
            `Subjects API returned HTTP ${response.status}. ${preview||'Invalid server response.'}`
        );
    }

    if(!response.ok||!result.success){
        throw new Error(result.message||'Request failed.');
    }

    return result;
}


async function requestUpload(action,formData){
    formData.set('action',action);
    formData.set('csrf_token',csrfToken);

    const response=await fetch(apiUrl,{
        method:'POST',
        credentials:'same-origin',
        headers:{Accept:'application/json'},
        body:formData
    });

    const text=await response.text();
    let result;

    try{
        result=JSON.parse(text);
    }catch{
        const preview=String(text||'')
            .replace(/<[^>]*>/g,' ')
            .replace(/\s+/g,' ')
            .trim()
            .slice(0,300);
        throw new Error(`Subjects Import API returned HTTP ${response.status}. ${preview||'Invalid server response.'}`);
    }

    if(!response.ok||!result.success){
        throw new Error(result.message||'Subject import failed.');
    }

    return result;
}

function applyActionPermissions(){
    const actionMap={
        print:'printSubjects',
        pdf:'exportSubjectsPdf',
        export:'exportSubjectsExcel',
        import:'importSubjects',
        create:'addSubject'
    };

    Object.entries(actionMap).forEach(([action,id])=>{
        const element=$(id);
        if(!element)return;

        const allowed=action==='create'
            ?Boolean(permissions.create||permissions.add)
            :Boolean(permissions[action]);

        element.style.display=allowed?'':'none';
    });
}

function selectedYearId(){
    return Number($('subjectYear').value||0);
}

function classesForYear(yearId){
    return (meta.classes||[]).filter(
        row=>Number(row.academic_year_id)===Number(yearId)
    );
}

function currentYear(){
    return (meta.academic_years||[]).find(
        row=>Number(row.id)===selectedYearId()
    )||null;
}

function currentClass(){
    return (meta.classes||[]).find(
        row=>Number(row.id)===Number(selectedClassId)
    )||null;
}

function subjectCountsByClass(){
    const counts={};

    allSubjects.forEach(subject=>{
        const id=Number(subject.class_id||0);
        if(id<=0)return;
        counts[id]=(counts[id]||0)+1;
    });

    return counts;
}

function updateStatistics(){
    const classSet=new Set(
        allSubjects.map(row=>Number(row.class_id||0)).filter(id=>id>0)
    );

    $('totalSubjects').textContent=String(allSubjects.length);
    $('classesWithSubjects').textContent=String(classSet.size);
    $('booksAssigned').textContent=String(
        allSubjects.filter(row=>String(row.book_name||'').trim()!=='').length
    );
    $('activeSubjects').textContent=String(
        allSubjects.filter(row=>row.status==='active').length
    );
}

function updateExportLinks(){
    const data={
        academic_year_id:selectedYearId(),
        search:$('subjectSearch').value.trim(),
        status:$('subjectStatusFilter').value
    };

    if(!viewAllMode&&selectedClassId>0){
        data.class_id=selectedClassId;
    }

    const base=new URLSearchParams(data);
    const print=$('printSubjects');
    const pdf=$('exportSubjectsPdf');
    const excel=$('exportSubjectsExcel');

    if(print){
        const q=new URLSearchParams(base);
        q.set('action','export');
        q.set('format','print');
        q.set('permission_action','print');
        print.href=`${apiUrl}?${q}`;
    }

    if(pdf){
        const q=new URLSearchParams(base);
        q.set('action','export');
        q.set('format','pdf');
        q.set('permission_action','pdf');
        pdf.href=`${apiUrl}?${q}`;
    }

    if(excel){
        const q=new URLSearchParams(base);
        q.set('action','export');
        q.set('format','excel');
        q.set('permission_action','export');
        excel.href=`${apiUrl}?${q}`;
    }
}

function subjectRow(subject){
    const marks=`${Number(subject.pass_marks||0)}/${Number(subject.maximum_marks||0)}`;

    return `
        <tr data-id="${Number(subject.id)}">
            <td>
                <div class="subject-name">
                    <span class="subject-avatar">
                        ${esc((subject.subject_name||'?').charAt(0).toUpperCase())}
                    </span>
                    <span>
                        <strong>${esc(subject.subject_name)}</strong>
                        <small>${esc(subject.department_name||'No department')}</small>
                    </span>
                </div>
            </td>
            <td>${esc(subject.subject_code||'-')}</td>
            <td class="subject-book">${esc(subject.book_name||'-')}</td>
            <td>${esc(subject.academic_year_name||'-')}</td>
            <td>${esc(subject.subject_type||'-')}</td>
            <td>${esc(subject.subject_teacher_name||'-')}</td>
            <td>${esc(marks)}</td>
            <td>
                <span class="subject-status ${esc(subject.status)}">
                    ${esc(subject.status)}
                </span>
            </td>
            <td>
                <div class="subject-actions">
                    <button class="subject-action js-subject-view" type="button" title="View">
                        <i data-lucide="eye"></i>
                    </button>
                    ${permissions.edit?`
                        <button class="subject-action js-subject-edit" type="button" title="Edit">
                            <i data-lucide="pencil"></i>
                        </button>
                    `:''}
                    ${permissions.delete?`
                        <button class="subject-action danger js-subject-delete" type="button" title="Delete">
                            <i data-lucide="trash-2"></i>
                        </button>
                    `:''}
                </div>
            </td>
        </tr>
    `;
}

function bindRowActions(root){
    root.querySelectorAll('.js-subject-view,.js-subject-edit').forEach(button=>{
        button.onclick=()=>{
            const id=Number(button.closest('tr').dataset.id);
            const subject=allSubjects.find(row=>Number(row.id)===id);
            openSubject(subject,button.classList.contains('js-subject-view'));
        };
    });

    root.querySelectorAll('.js-subject-delete').forEach(button=>{
        button.onclick=async()=>{
            const id=Number(button.closest('tr').dataset.id);
            const subject=allSubjects.find(row=>Number(row.id)===id);

            if(!confirm(`Delete "${subject?.subject_name||'this subject'}" from ${subject?.class_name||'this class'}?`)){
                return;
            }

            try{
                const result=await request('delete',{id},'POST');
                showMessage(result.message,true);
                await loadSubjects();
            }catch(error){
                showMessage(error.message,false);
            }
        };
    });
}

function renderClassTabs(){
    const classes=classesForYear(selectedYearId());
    const counts=subjectCountsByClass();

    if(
        selectedClassId<=0
        ||!classes.some(row=>Number(row.id)===Number(selectedClassId))
    ){
        selectedClassId=Number(classes[0]?.id||0);
    }

    $('subjectClassTabs').innerHTML=
        classes.map(row=>{
            const id=Number(row.id);
            const active=!viewAllMode&&id===Number(selectedClassId);
            const count=Number(counts[id]||0);

            return `
                <button class="subject-class-tab ${active?'active':''}"
                        type="button" data-id="${id}">
                    ${esc(row.class_name)}
                    <span class="subject-class-count">${count}</span>
                </button>
            `;
        }).join('')
        ||'<span class="text-muted small">No classes configured for this Academic Year.</span>';

    $('subjectClassTabs').querySelectorAll('.subject-class-tab').forEach(button=>{
        button.onclick=()=>{
            selectedClassId=Number(button.dataset.id);
            viewAllMode=false;
            renderCurrentView();
        };
    });
}

function filteredForClass(classId){
    return allSubjects.filter(row=>Number(row.class_id)===Number(classId));
}

function applyLocalFilters(rows){
    const search=$('subjectSearch').value.trim().toLowerCase();
    const status=$('subjectStatusFilter').value;

    return rows.filter(row=>{
        const matchesStatus=status==='all'||row.status===status;
        if(!matchesStatus)return false;
        if(search==='')return true;

        return [
            row.subject_name,
            row.subject_code,
            row.book_name,
            row.subject_teacher_name,
            row.department_name,
            row.subject_group
        ].some(value=>String(value||'').toLowerCase().includes(search));
    });
}

function renderSingleClass(){
    const year=currentYear();
    const cls=currentClass();
    const rows=applyLocalFilters(filteredForClass(selectedClassId));

    filteredSubjects=rows;

    $('selectedClassTitle').textContent=
        cls?`${cls.class_name} Subjects (${rows.length})`:'Class Subjects';

    $('selectedClassSubtitle').textContent=
        cls&&year
            ?`${year.year_name} · Subjects assigned only to ${cls.class_name}`
            :'Select a class.';

    $('subjectBody').innerHTML=
        rows.map(row=>subjectRow(row)).join('')
        ||`
            <tr>
                <td colspan="9" class="subject-empty">
                    <span class="subject-empty-icon">
                        <i data-lucide="book-open"></i>
                    </span>
                    <strong>No subjects found for this class</strong>
                    <small>Add a subject specifically to this Academic Year + Class.</small>
                </td>
            </tr>
        `;

    $('subjectCopy').textContent=
        cls?`Showing ${rows.length} subject${rows.length===1?'':'s'} in ${cls.class_name}`:'No class selected';

    $('subjectYearCopy').textContent=year?.year_name||'';
    bindRowActions($('subjectBody'));
}

function renderAllClasses(){
    const classes=classesForYear(selectedYearId());
    const year=currentYear();
    filteredSubjects=applyLocalFilters(allSubjects);

    $('selectedClassTitle').textContent=
        `All Classes – Subjects (${filteredSubjects.length})`;

    $('selectedClassSubtitle').textContent=
        year?`${year.year_name} · Grouped class-wise`:'Grouped class-wise';

    $('allClassView').innerHTML=
        classes.map(cls=>{
            const rows=applyLocalFilters(filteredForClass(Number(cls.id)));

            return `
                <section class="subject-group">
                    <div class="subject-group-head">
                        <strong>${esc(cls.class_name)} Subjects</strong>
                        <span>${rows.length} subject${rows.length===1?'':'s'}</span>
                    </div>
                    <div class="subject-table-wrap">
                        <table class="data-table subject-table">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Subject Code</th>
                                    <th>Book Name</th>
                                    <th>Academic Year</th>
                                    <th>Type</th>
                                    <th>Teacher</th>
                                    <th>Marks</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${
                                    rows.length
                                        ?rows.map(row=>subjectRow(row)).join('')
                                        :`<tr><td colspan="9" class="text-center py-4 text-muted">
                                            No subjects assigned to ${esc(cls.class_name)}.
                                          </td></tr>`
                                }
                            </tbody>
                        </table>
                    </div>
                </section>
            `;
        }).join('')
        ||'<div class="subject-empty"><strong>No classes configured</strong><small>Add classes before assigning subjects.</small></div>';

    bindRowActions($('allClassView'));
}

function renderCurrentView(){
    renderClassTabs();

    $('singleClassView').style.display=viewAllMode?'none':'';
    $('allClassView').style.display=viewAllMode?'grid':'none';
    $('viewAllSubjects').textContent=viewAllMode?'Selected Class':'View All';

    if(viewAllMode){
        renderAllClasses();
    }else{
        renderSingleClass();
    }

    updateExportLinks();
    window.lucide?.createIcons();
}

function fillAcademicYears(){
    const years=meta.academic_years||[];

    $('subjectYear').innerHTML=
        years.map(row=>`<option value="${Number(row.id)}">${esc(row.year_name)}</option>`).join('');

    $('subjectAcademicYear').innerHTML=$('subjectYear').innerHTML;

    const current=years.find(row=>Number(row.is_current)===1)||years[0];

    if(current){
        $('subjectYear').value=String(current.id);
        $('subjectAcademicYear').value=String(current.id);
    }
}

function fillModalClasses(yearId,preferredClassId=0){
    const classes=classesForYear(yearId);

    $('subjectClass').innerHTML=
        classes.map(row=>`
            <option value="${Number(row.id)}">
                ${esc(row.class_name)}${row.class_code?` (${esc(row.class_code)})`:''}
            </option>
        `).join('')
        ||'<option value="">No classes available</option>';

    if(
        preferredClassId>0
        &&classes.some(row=>Number(row.id)===Number(preferredClassId))
    ){
        $('subjectClass').value=String(preferredClassId);
    }
}

function fillTeachers(){
    const rows=meta.teachers||[];

    $('subjectTeacher').innerHTML=
        '<option value="">Not assigned</option>'
        +rows.map(row=>`<option value="${Number(row.id)}">${esc(row.teacher_name)}</option>`).join('');
}

function openSubject(subject=null,viewOnly=false){
    if(!subject&&!viewOnly&&!(permissions.create||permissions.add)){
        showMessage('You do not have permission to create subjects.',false);
        return;
    }

    $('subjectForm').reset();
    $('subjectId').value=subject?.id||'';
    $('subjectModalTitle').textContent=
        viewOnly?'View Subject':(subject?'Edit Subject':'Add Subject');

    const yearId=Number(subject?.academic_year_id||selectedYearId()||0);
    const classId=Number(subject?.class_id||selectedClassId||0);

    $('subjectAcademicYear').value=yearId?String(yearId):'';
    fillModalClasses(yearId,classId);

    $('subjectName').value=subject?.subject_name||'';
    $('subjectCode').value=subject?.subject_code||'';
    $('subjectBookName').value=subject?.book_name||'';
    $('subjectType').value=subject?.subject_type||'core';
    $('subjectDepartment').value=subject?.department_name||'';
    $('subjectGroup').value=subject?.subject_group||'';
    $('subjectMaximumMarks').value=Number(subject?.maximum_marks||100);
    $('subjectPassMarks').value=Number(subject?.pass_marks??35);
    $('subjectTeacher').value=String(subject?.subject_teacher_user_id||'');
    $('subjectStatus').value=subject?.status||'active';
    $('subjectDescription').value=subject?.description||'';

    $('subjectForm').querySelectorAll('input,select,textarea').forEach(element=>{
        if(element.id==='subjectId')return;
        element.disabled=viewOnly;
    });

    $('saveSubjectButton').style.display=viewOnly?'none':'';

    bootstrap.Modal.getOrCreateInstance($('subjectModal')).show();
    window.lucide?.createIcons();
}

async function loadSubjects(){
    if(selectedYearId()<=0){
        allSubjects=[];
        updateStatistics();
        renderCurrentView();
        return;
    }

    try{
        const result=await request('list',{
            academic_year_id:selectedYearId()
        });

        allSubjects=result.data.subjects||[];
        updateStatistics();
        renderCurrentView();
    }catch(error){
        showMessage(error.message,false);
    }
}

async function initialize(preserveYear=false){
    try{
        const previousYear=preserveYear?selectedYearId():0;
        const previousClass=selectedClassId;

        const result=await request('meta');
        csrfToken=result.data.csrf_token||csrfToken;
        meta=result.data.meta||{};
        permissions={...permissions,...(result.data.permissions||{})};
        applyActionPermissions();

        fillAcademicYears();
        fillTeachers();

        if(
            previousYear>0
            &&(meta.academic_years||[]).some(row=>Number(row.id)===previousYear)
        ){
            $('subjectYear').value=String(previousYear);
        }

        const classes=classesForYear(selectedYearId());

        selectedClassId=
            classes.some(row=>Number(row.id)===Number(previousClass))
                ?Number(previousClass)
                :Number(classes[0]?.id||0);

        await loadSubjects();
    }catch(error){
        showMessage(error.message,false);
    }
}

$('subjectYear').addEventListener('change',async()=>{
    const classes=classesForYear(selectedYearId());
    selectedClassId=Number(classes[0]?.id||0);
    viewAllMode=false;
    await loadSubjects();
});

$('subjectSearch').addEventListener('input',renderCurrentView);
$('subjectStatusFilter').addEventListener('change',renderCurrentView);

$('resetSubjectFilters').addEventListener('click',()=>{
    $('subjectSearch').value='';
    $('subjectStatusFilter').value='all';
    renderCurrentView();
});

$('viewAllSubjects').addEventListener('click',()=>{
    viewAllMode=!viewAllMode;
    renderCurrentView();
});

$('subjectAcademicYear').addEventListener('change',()=>{
    fillModalClasses(Number($('subjectAcademicYear').value||0));
});

const addButton=$('addSubject');
if(addButton){
    addButton.addEventListener('click',()=>openSubject());
}

$('subjectForm').addEventListener('submit',async event=>{
    event.preventDefault();

    const teacher=$('subjectTeacher');

    const data={
        id:Number($('subjectId').value||0),
        academic_year_id:Number($('subjectAcademicYear').value||0),
        class_id:Number($('subjectClass').value||0),
        subject_name:$('subjectName').value.trim(),
        subject_code:$('subjectCode').value.trim().toUpperCase(),
        book_name:$('subjectBookName').value.trim(),
        subject_type:$('subjectType').value,
        department_name:$('subjectDepartment').value.trim(),
        subject_group:$('subjectGroup').value.trim(),
        maximum_marks:Number($('subjectMaximumMarks').value||0),
        pass_marks:Number($('subjectPassMarks').value||0),
        subject_teacher_user_id:Number(teacher.value||0),
        subject_teacher_name:
            Number(teacher.value||0)>0
                ?(teacher.options[teacher.selectedIndex]?.text||'')
                :'',
        status:$('subjectStatus').value,
        description:$('subjectDescription').value.trim()
    };

    try{
        const result=await request('save',data,'POST');

        bootstrap.Modal.getInstance($('subjectModal'))?.hide();

        $('subjectYear').value=String(data.academic_year_id);
        selectedClassId=data.class_id;
        viewAllMode=false;

        showMessage(result.message,true);
        await initialize(true);
    }catch(error){
        showMessage(error.message,false);
    }
});


const importButton=$('importSubjects');
if(importButton){
    importButton.addEventListener('click',()=>{
        if(!permissions.import){
            showMessage('You do not have permission to import subjects.',false);
            return;
        }

        const resultBox=$('subjectImportResult');
        if(resultBox){
            resultBox.style.display='none';
            resultBox.textContent='';
        }

        const fileInput=$('subjectImportFile');
        if(fileInput)fileInput.value='';

        bootstrap.Modal.getOrCreateInstance($('subjectImportModal')).show();
        window.lucide?.createIcons();
    });
}

const csvTemplate=$('downloadSubjectCsvTemplate');
const xlsxTemplate=$('downloadSubjectXlsxTemplate');

if(csvTemplate)csvTemplate.href=`${apiUrl}?action=template&format=csv`;
if(xlsxTemplate)xlsxTemplate.href=`${apiUrl}?action=template&format=xlsx`;

const importForm=$('subjectImportForm');
if(importForm){
    importForm.addEventListener('submit',async event=>{
        event.preventDefault();

        if(!permissions.import){
            showMessage('You do not have permission to import subjects.',false);
            return;
        }

        const file=$('subjectImportFile')?.files?.[0];
        if(!file){
            showMessage('Choose a CSV or XLSX file to import.',false);
            return;
        }

        const extension=String(file.name.split('.').pop()||'').toLowerCase();
        if(!['csv','xlsx'].includes(extension)){
            showMessage('Only CSV and XLSX files are supported.',false);
            return;
        }

        const submitButton=$('startSubjectImport');
        const resultBox=$('subjectImportResult');

        submitButton.disabled=true;
        submitButton.innerHTML='<span class="spinner-border spinner-border-sm me-2"></span>Importing...';

        try{
            const formData=new FormData();
            formData.append('import_file',file);

            const result=await requestUpload('import',formData);
            const summary=result.data||{};
            const imported=Number(summary.imported||0);
            const skipped=Number(summary.skipped||0);
            const failed=Number(summary.failed||0);
            const errors=Array.isArray(summary.errors)?summary.errors:[];

            if(resultBox){
                resultBox.style.display='block';
                resultBox.className='alert '+(failed>0?'alert-warning':'alert-success');
                resultBox.innerHTML=
                    `<strong>${esc(result.message)}</strong>`
                    +`<div class="mt-2">Imported: <strong>${imported}</strong> · Skipped: <strong>${skipped}</strong> · Failed: <strong>${failed}</strong></div>`
                    +(errors.length
                        ?`<div class="mt-2 small">${errors.slice(0,10).map(item=>esc(item)).join('<br>')}</div>`
                        :'');
            }

            showMessage(
                `${imported} subject${imported===1?'':'s'} imported.`
                +(skipped?` ${skipped} skipped.`:'')
                +(failed?` ${failed} failed.`:''),
                failed===0,
                failed===0?'Import Completed':'Import Completed With Warnings'
            );

            await initialize(true);
        }catch(error){
            if(resultBox){
                resultBox.style.display='block';
                resultBox.className='alert alert-danger';
                resultBox.textContent=error.message;
            }
            showMessage(error.message,false,'Import Failed');
        }finally{
            submitButton.disabled=false;
            submitButton.innerHTML='<i data-lucide="upload"></i> Import Subjects';
            window.lucide?.createIcons();
        }
    });
}

initialize();
})();
</script>

<?php require dirname(__DIR__) . '/includes/layout-end.php'; ?>

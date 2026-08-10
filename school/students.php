<?php
declare(strict_types=1);

$pageTitle = 'Students Management';
$pageKey = 'student_management';
$sidebarFile = __DIR__ . '/sidebar.php';

require dirname(__DIR__) . '/includes/layout-start.php';

$csrfToken = function_exists('csrfToken') ? csrfToken() : '';
?>
<style>
.student-page{display:grid;gap:16px}
.student-page .page-title{font-size:28px;line-height:1.1}
.student-page .page-subtitle{margin-top:4px}
.student-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.student-kpi{border-radius:14px;color:#fff;min-height:112px;padding:18px 20px;display:flex;align-items:center;gap:14px;position:relative;overflow:hidden;box-shadow:0 12px 28px rgba(15,23,42,.08)}
.student-kpi::after{content:"";position:absolute;width:110px;height:110px;border-radius:50%;right:-38px;top:-40px;background:rgba(255,255,255,.08)}
.student-kpi.purple{background:linear-gradient(135deg,#7448e8,#4b36cf)}
.student-kpi.green{background:linear-gradient(135deg,#45c783,#1ea568)}
.student-kpi.pink{background:linear-gradient(135deg,#ff527c,#ed2f63)}
.student-kpi.orange{background:linear-gradient(135deg,#ffb327,#ff8a17)}
.student-kpi-icon{width:50px;height:50px;border-radius:50%;display:grid;place-items:center;background:rgba(255,255,255,.16);flex:0 0 auto}
.student-kpi-icon svg{width:25px;height:25px}
.student-kpi strong{display:block;font-size:26px;line-height:1}
.student-kpi small{display:block;font-size:11px;font-weight:700;opacity:.94;margin-bottom:6px}
.student-kpi .trend{font-size:9px;font-weight:700;opacity:.94;margin-top:8px}
.student-filter-card{padding:14px;display:grid;grid-template-columns:minmax(240px,1.4fr) repeat(4,minmax(125px,.8fr)) auto;gap:10px}
.student-layout{display:grid;grid-template-columns:minmax(0,1fr);gap:16px;align-items:start}
.student-card{border-radius:14px;overflow:hidden}
.student-card-head{padding:16px;border-bottom:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;align-items:center;gap:10px}
.student-card-head strong{font-size:14px}
.student-class-nav{padding:14px 16px;border-bottom:1px solid var(--border-soft,#e7ebf3);display:flex;gap:9px;flex-wrap:wrap;background:var(--card-bg,#fff)}
.student-class-button{border:1px solid var(--border-soft,#dce3f0);background:var(--card-bg,#fff);color:var(--text-main,#1e293b);border-radius:10px;padding:9px 13px;display:flex;align-items:center;gap:8px;font-size:11px;font-weight:800;transition:.18s ease}
.student-class-button:hover{border-color:#7558e8;color:#5b42d6}
.student-class-button.active{color:#fff;border-color:transparent;background:linear-gradient(135deg,#6d4ce7,#345fe0);box-shadow:0 7px 16px rgba(79,70,229,.2)}
.student-class-count{min-width:22px;height:22px;padding:0 6px;border-radius:999px;display:inline-grid;place-items:center;background:rgba(100,116,139,.12);font-size:9px}
.student-class-button.active .student-class-count{background:rgba(255,255,255,.2)}
.student-table-wrap{overflow:auto}
.student-table{min-width:980px}
.student-table th{font-size:10px}
.student-table td{font-size:11px;vertical-align:middle}
.student-cell{display:flex;align-items:center;gap:9px}
.student-avatar{width:30px;height:30px;border-radius:50%;display:grid;place-items:center;color:#fff;font-size:11px;font-weight:800;background:linear-gradient(135deg,#6d4ce7,#345fe0)}
.student-badge{display:inline-flex;align-items:center;padding:5px 9px;border-radius:999px;font-size:9px;font-weight:800;text-transform:capitalize}
.student-badge.active{color:#16834f;background:#e8f8ef}
.student-badge.inactive,.student-badge.withdrawn{color:#dc2626;background:#fff0f1}
.student-badge.tc,.student-badge.alumni{color:#9a6700;background:#fff7d6}
.student-actions{display:flex;gap:6px}
.student-action{width:30px;height:30px;border:1px solid #d7def1;border-radius:7px;background:var(--card-bg,#fff);display:grid;place-items:center;color:#334155}
.student-action svg{width:13px;height:13px}
.student-pagination{padding:12px 16px;border-top:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;align-items:center}
.student-pagination small{font-size:10px;color:var(--text-muted,#64748b)}
.chart-wrap{padding:18px}
.donut{width:180px;height:180px;border-radius:50%;margin:0 auto 16px;position:relative;background:conic-gradient(#4a8df6 0 20%,#31b56d 20% 40%,#ff9c1b 40% 60%,#f23d68 60% 80%,#a75bd7 80% 100%)}
.donut::after{content:"";position:absolute;inset:30px;border-radius:50%;background:var(--card-bg,#fff)}
.chart-legend{display:flex;flex-wrap:wrap;gap:10px;justify-content:center}
.legend-item{display:flex;align-items:center;gap:5px;font-size:10px}
.legend-dot{width:9px;height:9px;border-radius:50%}
.activity-list{display:grid}
.activity-item{padding:12px 16px;border-bottom:1px solid var(--border-soft,#e7ebf3);font-size:11px}
.activity-item:last-child{border-bottom:0}
.activity-item strong,.activity-item small{display:block}
.activity-item small{color:var(--text-muted,#64748b);margin-top:3px}
.student-message{display:none}.student-message.show{display:block}
.student-empty{padding:38px 18px;text-align:center;color:var(--text-muted,#64748b)}
.student-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
.student-grid .full{grid-column:1/-1}
.student-fee-preview{grid-column:1/-1;border:1px solid var(--border-soft,#e7ebf3);border-radius:11px;background:var(--card-bg,#fff);overflow:hidden;display:none}
.student-fee-preview.show{display:block}
.student-fee-preview-head{padding:11px 13px;border-bottom:1px solid var(--border-soft,#e7ebf3);display:flex;align-items:center;justify-content:space-between;gap:10px}
.student-fee-preview-head strong{font-size:12px}
.student-fee-preview-total{font-size:14px;color:#4f46e5}
.student-fee-preview-list{display:grid}
.student-fee-preview-row{display:grid;grid-template-columns:minmax(180px,1fr) 120px 100px 130px;gap:10px;align-items:center;padding:9px 13px;border-bottom:1px solid var(--border-soft,#eef1f6);font-size:10px}
.student-fee-preview-row:last-child{border-bottom:0}
.student-fee-preview-row strong{font-size:11px}
.student-fee-preview-row .amount{text-align:right;font-weight:800}
.student-fee-zero{color:var(--text-muted,#64748b)}
@media(max-width:700px){.student-fee-preview-row{grid-template-columns:1fr 1fr}.student-fee-preview-row .amount{text-align:left}}

#studentModal{overflow-y:auto;padding-right:0!important}
#studentModal .modal-dialog{width:min(1120px,calc(100vw - 24px));max-width:1120px;height:calc(100dvh - 32px);margin:16px auto}
#studentModal .modal-content{height:100%;overflow:hidden}
#studentModal #studentForm{display:flex;flex-direction:column;height:100%;min-height:0}
#studentModal .modal-header,#studentModal .modal-footer{flex:0 0 auto}
#studentModal .modal-body{flex:1 1 auto;min-height:0;overflow-y:auto!important}
@media(max-width:1250px){.student-layout{grid-template-columns:1fr}.student-filter-card{grid-template-columns:repeat(3,1fr)}}
@media(max-width:900px){.student-kpis{grid-template-columns:repeat(2,1fr)}.student-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:575px){.student-kpis,.student-filter-card,.student-grid{grid-template-columns:1fr}.student-grid .full{grid-column:auto}#studentModal .modal-dialog{width:100%;height:100dvh;margin:0}#studentModal .modal-content{border-radius:0}}
.student-transport-title{grid-column:1/-1;padding:10px 12px;border-radius:10px;background:rgba(79,70,229,.06);display:flex;align-items:center;justify-content:space-between;gap:10px}
.student-transport-title strong{font-size:12px}
.student-transport-readonly{background:rgba(100,116,139,.06)!important}

.student-import-drop{border:1px dashed #b9c4dc;border-radius:12px;padding:18px;text-align:center;background:rgba(99,102,241,.035)}
.student-import-drop input{max-width:520px;margin:10px auto 0}
.student-template-actions{display:flex;gap:8px;flex-wrap:wrap}
.student-import-result{display:none;font-size:11px;padding:12px;margin-bottom:0}.student-import-result.show{display:block}
.student-import-options{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:14px}
.student-import-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin-top:10px}
.student-import-card{appearance:none;border:1px solid rgba(99,102,241,.13);padding:10px;border-radius:10px;background:rgba(99,102,241,.055);text-align:center;cursor:pointer;min-width:0;transition:.18s}
.student-import-card:hover,.student-import-card:focus{transform:translateY(-1px);border-color:#6366f1;box-shadow:0 8px 18px rgba(79,70,229,.1);outline:none}
.student-import-card.active{color:#fff;background:linear-gradient(135deg,#6747e8,#2f62d7);border-color:transparent}
.student-import-card strong,.student-import-card small{display:block}
.student-import-card strong{font-size:19px}
.student-import-card small{font-size:9px;color:inherit;opacity:.82}
.student-import-card .student-import-view-label{margin-top:4px;font-size:9px;font-weight:800;text-decoration:underline}
.student-import-details{display:none;margin-top:10px;border:1px solid rgba(99,102,241,.14);border-radius:10px;background:var(--card-bg,#fff);overflow:hidden}
.student-import-details.show{display:block}
.student-import-details-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 12px;border-bottom:1px solid var(--border-soft,#e7ebf3)}
.student-import-details-body{max-height:230px;overflow:auto;-webkit-overflow-scrolling:touch}
.student-import-detail-table{width:100%;min-width:650px;border-collapse:collapse}
.student-import-detail-table th,.student-import-detail-table td{padding:9px 10px;border-bottom:1px solid var(--border-soft,#edf0f5);text-align:left;vertical-align:top}
.student-import-detail-table th{position:sticky;top:0;background:var(--card-bg,#fff);z-index:1;font-size:9px;text-transform:uppercase;white-space:nowrap}
.student-import-detail-table td{font-size:10px}
.student-import-detail-empty{padding:22px;text-align:center;color:var(--text-muted,#64748b)}
.student-missing-columns-action{display:none;margin-top:12px;padding:12px;border:1px solid #f5c76b;border-radius:11px;background:#fff9e8}.student-missing-columns-action.show{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}.student-missing-columns-action strong{font-size:11px}.student-missing-columns-action small{display:block;margin-top:3px;color:#8a6510;font-size:9px}.student-missing-columns-panel{display:none;margin-top:12px;border:1px solid rgba(99,102,241,.18);border-radius:12px;background:var(--card-bg,#fff);overflow:hidden}.student-missing-columns-panel.show{display:block}.student-missing-columns-head{padding:12px 14px;border-bottom:1px solid var(--border-soft,#e7ebf3);display:flex;align-items:flex-start;justify-content:space-between;gap:10px;background:rgba(99,102,241,.045)}.student-missing-columns-head strong,.student-missing-columns-head small{display:block}.student-missing-columns-head strong{font-size:12px}.student-missing-columns-head small{margin-top:3px;color:var(--text-muted,#64748b);font-size:9px}.student-missing-columns-body{padding:14px;display:grid;gap:12px}.student-missing-columns-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.student-missing-column-row{padding:11px;border:1px solid var(--border-soft,#e7ebf3);border-radius:10px;background:rgba(248,250,252,.72)}.student-missing-column-title{display:flex;align-items:center;gap:8px;margin-bottom:9px}.student-missing-column-title label{margin:0;font-size:10px;font-weight:800}.student-missing-column-row .form-select,.student-missing-column-row .form-control{font-size:11px}.student-missing-file{padding:12px;border:1px dashed #b9c4dc;border-radius:10px;background:rgba(99,102,241,.025)}.student-missing-file strong,.student-missing-file small{display:block}.student-missing-file strong{font-size:11px}.student-missing-file small{margin:3px 0 8px;color:var(--text-muted,#64748b);font-size:9px}.student-missing-unsupported{padding:9px 10px;border-radius:8px;color:#b42318;background:#fff1f0;font-size:9px}.student-missing-column-count{min-width:22px;height:22px;padding:0 6px;display:inline-grid;place-items:center;border-radius:999px;background:rgba(180,83,9,.12);font-size:9px;font-weight:800}@media(max-width:767px){.student-missing-columns-grid{grid-template-columns:1fr}}\n#importModal{overflow:hidden}
#importModal .modal-dialog{width:min(980px,calc(100vw - 24px));max-width:980px;height:calc(100dvh - 24px);margin:12px auto}
#importModal .modal-content{height:100%;max-height:none;overflow:hidden}
#importModal #importForm{display:flex;flex-direction:column;height:100%;min-height:0}
#importModal .modal-header,#importModal .modal-footer{flex:0 0 auto}
#importModal .modal-body{flex:1 1 auto;min-height:0;overflow-y:auto;-webkit-overflow-scrolling:touch}
#importModal .modal-footer{position:relative;z-index:2;background:var(--card-bg,#fff);box-shadow:0 -8px 20px rgba(15,23,42,.06)}
#importModal .modal-footer .btn-ui{min-height:42px}
@media(min-width:1400px){#importModal .modal-dialog{width:min(1040px,calc(100vw - 40px));max-width:1040px;height:min(850px,calc(100dvh - 40px));margin:20px auto}}
@media(max-width:900px){#importModal .modal-dialog{width:calc(100vw - 16px);height:calc(100dvh - 16px);margin:8px auto}.student-import-drop{padding:15px}.student-template-actions{width:100%}.student-template-actions .btn-ui{flex:1 1 0;justify-content:center}}
@media(max-width:767px){.student-import-options{grid-template-columns:1fr}.student-import-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.student-import-details-head{align-items:flex-start;flex-direction:column}}
@media(max-width:575px){#importModal .modal-dialog{width:100%;height:100dvh;margin:0}#importModal .modal-content{border-radius:0}#importModal .modal-header{padding:14px}#importModal .modal-body{padding:12px}#importModal .modal-footer{display:grid;grid-template-columns:1fr 1fr;padding:10px 12px}#importModal .modal-footer .btn-ui{width:100%;justify-content:center}.student-import-summary{grid-template-columns:1fr 1fr}.student-import-drop input{font-size:12px}.student-import-detail-table{min-width:560px}}
@media(max-height:720px) and (min-width:576px){#importModal .modal-dialog{height:calc(100dvh - 12px);margin:6px auto}#importModal .modal-header{padding-top:10px;padding-bottom:10px}#importModal .modal-body{padding-top:10px;padding-bottom:10px}#importModal .modal-footer{padding-top:8px;padding-bottom:8px}}
</style>

<div class="student-page">
    <div class="page-heading">
        <div>
            <h1 class="page-title">Students Management</h1>
            <p class="page-subtitle">Dashboard › Students Management</p>
        </div>
        <div class="page-actions">
            <a id="addStudentButton" class="btn-ui btn-primary-ui" href="student-admission.php"><i data-lucide="plus"></i> Add Student</a>
            <button id="importButton" class="btn-ui" type="button"><i data-lucide="upload"></i> Import</button>
            <a id="exportLink" class="btn-ui"><i data-lucide="download"></i> Export</a>
        </div>
    </div>

    <div id="studentMessage" class="alert student-message"></div>

    <section class="student-kpis">
        <article class="student-kpi purple"><span class="student-kpi-icon"><i data-lucide="users-round"></i></span><div><small>Total Students</small><strong id="statTotal">0</strong><div class="trend">Live student strength</div></div></article>
        <article class="student-kpi green"><span class="student-kpi-icon"><i data-lucide="user-round-check"></i></span><div><small>Active Students</small><strong id="statActive">0</strong><div class="trend" id="activePercent">0% of total students</div></div></article>
        <article class="student-kpi pink"><span class="student-kpi-icon"><i data-lucide="user-round-plus"></i></span><div><small>New Admissions</small><strong id="statNew">0</strong><div class="trend">Current academic year</div></div></article>
        <article class="student-kpi orange"><span class="student-kpi-icon"><i data-lucide="user-round-x"></i></span><div><small>Alumni / Inactive</small><strong id="statInactive">0</strong><div class="trend" id="inactivePercent">0% of total students</div></div></article>
    </section>

    <section class="ui-card student-filter-card">
        <input id="searchFilter" class="form-control" placeholder="Search by name, admission no. or parent...">
        <select id="classFilter" class="form-select"><option value="all">All Classes</option></select>
        <select id="sectionFilter" class="form-select"><option value="all">All Sections</option></select>
        <select id="genderFilter" class="form-select"><option value="all">All Genders</option><option value="Male">Male</option><option value="Female">Female</option><option value="Other">Other</option></select>
        <select id="statusFilter" class="form-select"><option value="all">All Status</option></select>
        <button id="resetButton" class="btn-ui" type="button"><i data-lucide="rotate-ccw"></i> Reset</button>
    </section>

    <section class="student-layout">
        <section class="ui-card student-card">
            <div class="student-card-head"><strong id="studentListTitle">Students List</strong><button id="viewAllButton" class="btn btn-link btn-sm p-0" type="button">View All</button></div>
            <div id="classWiseNavigation" class="student-class-nav"><span class="text-muted">Loading classes...</span></div>
            <div class="student-table-wrap">
                <table class="data-table student-table">
                    <thead><tr><th>Admission No</th><th>Student Name</th><th>Class</th><th>Section</th><th>Parent</th><th>Mobile</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody id="studentBody"><tr><td colspan="8" class="student-empty">Loading...</td></tr></tbody>
                </table>
            </div>
            <div class="student-pagination"><small id="recordCount">Loading...</small><div><button class="btn-ui btn-sm" type="button">1</button></div></div>
        </section>

    </section>
</div>

<div class="modal fade" id="studentModal" tabindex="-1">
<div class="modal-dialog modal-xl">
<div class="modal-content">
<form id="studentForm" novalidate>
<div class="modal-header"><div><h5 id="studentModalTitle" class="modal-title">Add Student</h5><small class="text-muted">Student, parent and academic details</small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
<input id="studentId" type="hidden">
<div class="student-grid">
<div><label class="form-label">Academic Year *</label><select id="academicYearId" class="form-select" required></select></div>
<div><label class="form-label">Branch *</label><select id="branchId" class="form-select" required></select></div>
<div><label class="form-label">Class *</label><select id="classId" class="form-select" required></select></div>
<div><label class="form-label">Section</label><select id="sectionId" class="form-select"><option value="">Not Assigned</option></select></div>
<div><label class="form-label">Fee Structure *</label><select id="feeStructureId" class="form-select" disabled required><option value="">Auto-select active structure</option></select><small id="feeStructureHint" class="text-muted">The matching active structure is selected automatically.</small></div><div id="feeStructurePreview" class="student-fee-preview">
<div class="student-fee-preview-head"><strong id="feePreviewTitle">Fee Structure Details</strong><strong id="feePreviewTotal" class="student-fee-preview-total">₹0</strong></div>
<div id="feePreviewList" class="student-fee-preview-list"></div>
</div>
<div class="student-transport-title">
<strong>Transport Management</strong>
<small id="transportFeeHint" class="text-muted">No transport fee will be added.</small>
</div>
<div><label class="form-label">Transport Required *</label>
<select id="transportRequired" class="form-select" required>
<option value="0">No</option>
<option value="1">Yes</option>
</select></div>
<div><label class="form-label">Select Route</label>
<select id="transportRouteId" class="form-select" disabled>
<option value="">Select Route</option>
</select></div>
<div><label class="form-label">Select Boarding Stop</label>
<select id="transportStopId" class="form-select" disabled>
<option value="">Select Boarding Stop</option>
</select></div>
<div><label class="form-label">Assigned Vehicle</label>
<input id="transportVehicle" class="form-control student-transport-readonly" readonly value="-"></div>
<div><label class="form-label">Assigned Driver</label>
<input id="transportDriver" class="form-control student-transport-readonly" readonly value="-"></div>
<div><label class="form-label">Transport Fee</label>
<input id="transportFee" class="form-control student-transport-readonly" readonly value="₹0.00">
<input id="transportFeeAmount" type="hidden" value="0"></div>
<div><label class="form-label">Admission Number *</label><input id="admissionNumber" class="form-control" required maxlength="60"></div>
<div><label class="form-label">Roll Number</label><input id="rollNumber" class="form-control" maxlength="40"></div>
<div><label class="form-label">Student Name *</label><input id="studentName" class="form-control" required maxlength="150"></div>
<div><label class="form-label">Date of Birth *</label><input id="dateOfBirth" class="form-control" type="date" required></div>
<div><label class="form-label">Gender *</label><select id="gender" class="form-select" required><option value="">Select</option><option>Male</option><option>Female</option><option>Other</option></select></div>
<div><label class="form-label">Blood Group</label><select id="bloodGroup" class="form-select"><option value="">Not Specified</option><option>A+</option><option>A-</option><option>B+</option><option>B-</option><option>AB+</option><option>AB-</option><option>O+</option><option>O-</option></select></div>
<div><label class="form-label">Admission Date *</label><input id="admissionDate" class="form-control" type="date" required></div>
<div><label class="form-label">Status *</label><select id="studentStatus" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option><option value="tc">TC / Transferred</option><option value="alumni">Alumni</option></select></div>
<div><label class="form-label">Parent / Guardian Name *</label><input id="parentName" class="form-control" required maxlength="150"></div>
<div><label class="form-label">Relationship</label><select id="relationship" class="form-select"><option>Father</option><option>Mother</option><option>Guardian</option></select></div>
<div><label class="form-label">Mobile *</label><input id="mobile" class="form-control" required maxlength="20"></div>
<div><label class="form-label">Email</label><input id="email" class="form-control" type="email" maxlength="190"></div>
<div class="full"><label class="form-label">Address</label><textarea id="address" class="form-control" rows="2"></textarea></div>
<div class="full"><label class="form-label">Notes</label><textarea id="notes" class="form-control" rows="3"></textarea></div>
</div>
</div>
<div class="modal-footer"><button class="btn-ui" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn-ui btn-primary-ui" type="submit"><i data-lucide="save"></i> Save Student</button></div>
</form>
</div>
</div>
</div>

<div class="modal fade" id="importModal" tabindex="-1" data-bs-backdrop="static">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
<form id="importForm" enctype="multipart/form-data" novalidate>
<div class="modal-header"><div><h5 class="modal-title">Import Students</h5><small class="text-muted">Upload Excel (.xlsx) or CSV files using the provided template.</small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
 <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
  <div><strong>Sample Templates</strong><small class="d-block text-muted">Download, fill the student rows, then upload the file.</small></div>
  <div class="student-template-actions">
   <a id="downloadXlsxTemplate" class="btn-ui btn-sm"><i data-lucide="file-spreadsheet"></i> Excel Template</a>
   <a id="downloadCsvTemplate" class="btn-ui btn-sm"><i data-lucide="file-text"></i> CSV Template</a>
  </div>
 </div>
 <div class="student-import-drop">
  <i data-lucide="sheet" style="width:34px;height:34px"></i>
  <strong class="d-block mt-2">Choose Excel or CSV File</strong>
  <small class="text-muted">Supported: .xlsx and .csv · Maximum 10 MB · Up to 2,000 rows</small>
  <input id="importFile" name="import_file" class="form-control" type="file" accept=".xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
 </div>
 <div class="student-import-options">
  <div>
   <label class="form-label" for="duplicateMode">Duplicate Admission Number</label>
   <select id="duplicateMode" name="duplicate_mode" class="form-select">
    <option value="skip">Skip existing students</option>
    <option value="update">Update existing students</option>
   </select>
  </div>
  <div>
   <label class="form-label">Supported Date Formats</label>
   <input class="form-control" value="YYYY-MM-DD, DD-MM-YYYY, DD/MM/YYYY" readonly>
  </div>
 </div>
 <div class="alert alert-info mt-3 mb-2">
   Required: academic_year_id, branch_id, admission_number, student_name, date_of_birth, gender, admission_date, parent_name and mobile. Provide either class_id or class_name. Fee Structure is auto-selected when fee_structure_id is blank.
  </div>
  <div id="missingColumnsAction" class="student-missing-columns-action">
   <div>
    <strong>Some required columns are missing from the selected file.</strong>
    <small>Complete them once and the values will be mapped to every imported student row.</small>
   </div>
   <button id="missingColumnsButton" class="btn-ui btn-sm" type="button">
    <i data-lucide="columns-3"></i>
    Missing Required Columns
    <span id="missingColumnsCount" class="student-missing-column-count">0</span>
   </button>
  </div>
  <div id="missingColumnsPanel" class="student-missing-columns-panel">
   <div class="student-missing-columns-head">
    <div>
     <strong>Missing Required Columns Import Options</strong>
     <small>Select the missing columns, choose their values, select the XLSX/CSV file and click Import Students.</small>
    </div>
    <button id="closeMissingColumnsButton" class="btn-ui btn-sm" type="button">Close</button>
   </div>
   <div class="student-missing-columns-body">
    <div id="missingColumnsFields" class="student-missing-columns-grid"></div>
    <div class="student-missing-file">
     <strong>Choose XLSX / CSV File</strong>
     <small>You can keep the original file or choose it again here.</small>
     <input id="missingImportFile" class="form-control" type="file" accept=".xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
    </div>
    <div class="alert alert-light border mb-0 py-2 px-3" style="font-size:10px">
     Selected values are used only when the corresponding Excel/CSV column is missing or blank. Existing row values are preserved.
    </div>
   </div>
  </div>
 <div id="importResult" class="alert student-import-result"></div>
 <div id="importDetails" class="student-import-details">
  <div class="student-import-details-head">
   <div>
    <strong id="importDetailsTitle">Import Details</strong>
    <small id="importDetailsSubtitle" class="d-block text-muted">Click a summary card to view students.</small>
   </div>
   <button id="closeImportDetailsButton" class="btn-ui btn-sm" type="button">Close Details</button>
  </div>
  <div id="importDetailsBody" class="student-import-details-body"></div>
 </div>
</div>
<div class="modal-footer"><button class="btn-ui" type="button" data-bs-dismiss="modal">Cancel</button><button id="importSubmitButton" class="btn-ui btn-primary-ui" type="submit"><i data-lucide="upload"></i> Import Students</button></div>
</form>
</div>
</div>
</div>

<script>
(function(){
'use strict';

const apiUrl = new URL('../api/students.php', window.location.href).href;
let csrfToken = <?= json_encode($csrfToken) ?>;
let meta = {};
let permissions = {};
let records = [];
let activeClassKey = '';
let searchTimer = null;
let detectedMissingColumns = [];
let importResultData = {
    created_rows: [],
    updated_rows: [],
    skipped_rows: [],
    failed_rows: []
};

const $ = id => document.getElementById(id);
const esc = value => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

async function request(action, data = {}, method = 'GET') {
    let response;

    if (method === 'GET') {
        const url = new URL(apiUrl, window.location.origin);
        url.searchParams.set('action', action);
        Object.entries(data).forEach(([key, value]) => {
            if (value !== '' && value !== null && value !== undefined) {
                url.searchParams.set(key, String(value));
            }
        });
        response = await fetch(url.toString(), {
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
            body: JSON.stringify({action, csrf_token: csrfToken, ...data})
        });
    }

    const text = await response.text();
    let result;
    try {
        result = JSON.parse(text);
    } catch (error) {
        console.error('Students API invalid response:', text);
        throw new Error(`Students API returned HTTP ${response.status}. Invalid server response.`);
    }

    if (!response.ok || !result.success) {
        throw new Error(result.message || 'Request failed.');
    }

    if (result.data?.csrf_token) {
        csrfToken = result.data.csrf_token;
    }
    return result;
}

async function uploadImportFile(file, fallbackValues = {}) {
    const formData = new FormData();
    formData.append('action', 'import_file');
    formData.append('csrf_token', csrfToken);
    formData.append('import_file', file);
    formData.append(
        'duplicate_mode',
        $('duplicateMode')?.value || 'skip'
    );

    Object.entries(fallbackValues || {}).forEach(([column, value]) => {
        if (value !== '' && value !== null && value !== undefined) {
            formData.append(`fallback_${column}`, String(value));
        }
    });

    const response = await fetch(apiUrl, {
        method: 'POST',
        credentials: 'same-origin',
        cache: 'no-store',
        body: formData
    });

    const text = await response.text();
    let result;
    try {
        result = JSON.parse(text);
    } catch (error) {
        throw new Error(`Students API returned HTTP ${response.status}. Invalid import response.`);
    }

    if (result.data?.csrf_token) {
        csrfToken = result.data.csrf_token;
    }

    if (!response.ok || !result.success) {
        const importError = new Error(
            result.message || 'Student import failed.'
        );
        importError.result = result;
        throw importError;
    }

    return result;
}

function importDetailRows(type) {
    const key = `${type}_rows`;
    return Array.isArray(importResultData[key])
        ? importResultData[key]
        : [];
}

function showImportDetails(type) {
    const labels = {
        created: 'Created Students',
        updated: 'Updated Students',
        skipped: 'Skipped Students',
        failed: 'Failed Students'
    };

    const rows = importDetailRows(type);
    const box = $('importDetails');
    const body = $('importDetailsBody');

    document.querySelectorAll('.student-import-card').forEach(card => {
        card.classList.toggle(
            'active',
            card.dataset.type === type
        );
    });

    $('importDetailsTitle').textContent =
        labels[type] || 'Import Details';

    $('importDetailsSubtitle').textContent =
        `${rows.length} record${rows.length === 1 ? '' : 's'}`;

    if (!rows.length) {
        body.innerHTML =
            '<div class="student-import-detail-empty">' +
            'No students are available in this category.' +
            '</div>';
    } else {
        body.innerHTML =
            '<div style="overflow-x:auto">' +
            '<table class="student-import-detail-table">' +
            '<thead><tr>' +
            '<th>Excel Row</th>' +
            '<th>Admission Number</th>' +
            '<th>Student Name</th>' +
            '<th>Purpose / Reason</th>' +
            '<th>View</th>' +
            '</tr></thead><tbody>' +
            rows.map(row => {
                const studentId = Number(row.student_id || 0);
                const viewButton = studentId > 0
                    ? `<button class="btn-ui btn-sm js-import-view-student"
                         type="button"
                         data-id="${studentId}">
                         View Student
                       </button>`
                    : '<span class="text-muted">Not Available</span>';

                return `<tr>
                    <td>${esc(row.row || '-')}</td>
                    <td><strong>${esc(row.admission_number || '-')}</strong></td>
                    <td>${esc(row.student_name || '-')}</td>
                    <td>${esc(row.reason || '-')}</td>
                    <td>${viewButton}</td>
                </tr>`;
            }).join('') +
            '</tbody></table></div>';
    }

    box.classList.add('show');

    body.querySelectorAll('.js-import-view-student').forEach(button => {
        button.onclick = async () => {
            const studentId = Number(button.dataset.id || 0);

            bootstrap.Modal.getInstance(
                $('importModal')
            )?.hide();

            window.setTimeout(() => {
                openForm(studentId, true);
            }, 250);
        };
    });

    box.scrollIntoView({
        behavior: 'smooth',
        block: 'nearest'
    });
}

function missingColumnLabel(column) {
    return ({
        academic_year_id: 'Academic Year',
        branch_id: 'Branch',
        admission_date: 'Admission Date',
        admission_number: 'Admission Number',
        student_name: 'Student Name',
        date_of_birth: 'Date of Birth',
        gender: 'Gender',
        parent_name: 'Parent / Guardian Name',
        mobile: 'Mobile Number',
        'class_id or class_name': 'Class ID or Class Name'
    })[column] || column;
}

function resetMissingColumnsFlow() {
    detectedMissingColumns = [];
    $('missingColumnsAction').classList.remove('show');
    $('missingColumnsPanel').classList.remove('show');
    $('missingColumnsCount').textContent = '0';
    $('missingColumnsFields').innerHTML = '';
    $('missingImportFile').value = '';
}

function renderMissingColumnsFlow(columns) {
    detectedMissingColumns = Array.isArray(columns)
        ? [...new Set(columns.map(value => String(value)))]
        : [];

    if (!detectedMissingColumns.length) {
        resetMissingColumnsFlow();
        return;
    }

    const supported = new Set([
        'academic_year_id',
        'branch_id',
        'admission_date'
    ]);

    const currentAcademicYear = Number(
        meta.current_academic_year_id || 0
    );
    const currentBranch = Number(
        meta.current_branch_id || 0
    );
    const today = new Date().toISOString().slice(0, 10);

    $('missingColumnsFields').innerHTML = detectedMissingColumns
        .map(column => {
            const label = missingColumnLabel(column);
            const isSupported = supported.has(column);

            let control = '';

            if (column === 'academic_year_id') {
                control = `<select class="form-select" data-missing-value="academic_year_id">
                    <option value="">Select Academic Year</option>
                    ${(meta.academic_years || []).map(row => `
                        <option value="${Number(row.id)}" ${Number(row.id) === currentAcademicYear ? 'selected' : ''}>
                            ${esc(row.year_name || row.id)}
                        </option>
                    `).join('')}
                </select>`;
            } else if (column === 'branch_id') {
                control = `<select class="form-select" data-missing-value="branch_id">
                    <option value="">Select Branch</option>
                    ${(meta.branches || []).map(row => `
                        <option value="${Number(row.id)}" ${Number(row.id) === currentBranch ? 'selected' : ''}>
                            ${esc(row.branch_name || row.id)}
                        </option>
                    `).join('')}
                </select>`;
            } else if (column === 'admission_date') {
                control = `<input class="form-control" data-missing-value="admission_date" type="date" value="${today}">`;
            } else {
                control = `<div class="student-missing-unsupported">
                    This value is student-specific and must exist as a column in the XLSX/CSV file.
                </div>`;
            }

            return `<div class="student-missing-column-row">
                <div class="student-missing-column-title">
                    <input class="form-check-input" type="checkbox"
                           data-missing-check="${esc(column)}"
                           ${isSupported ? 'checked' : 'disabled'}>
                    <label>${esc(label)} <small class="text-muted">(${esc(column)})</small></label>
                </div>
                ${control}
            </div>`;
        })
        .join('');

    $('missingColumnsCount').textContent = String(
        detectedMissingColumns.length
    );
    $('missingColumnsAction').classList.add('show');
    $('missingColumnsPanel').classList.remove('show');
    window.lucide?.createIcons();
}

function collectMissingColumnFallbacks() {
    if (!detectedMissingColumns.length) {
        return {};
    }

    const supported = new Set([
        'academic_year_id',
        'branch_id',
        'admission_date'
    ]);
    const fallbacks = {};

    for (const column of detectedMissingColumns) {
        if (!supported.has(column)) {
            throw new Error(
                `${missingColumnLabel(column)} must be added to the XLSX/CSV file before import.`
            );
        }

        const checkbox = document.querySelector(
            `[data-missing-check="${CSS.escape(column)}"]`
        );

        if (!checkbox?.checked) {
            throw new Error(
                `Select the missing required column: ${missingColumnLabel(column)}.`
            );
        }

        const input = document.querySelector(
            `[data-missing-value="${CSS.escape(column)}"]`
        );
        const value = String(input?.value || '').trim();

        if (!value) {
            throw new Error(
                `Choose a value for ${missingColumnLabel(column)}.`
            );
        }

        fallbacks[column] = value;
    }

    return fallbacks;
}

function renderImportResult(result, success = true) {
    const box = $('importResult');
    const data = result?.data || {};
    const errors = Array.isArray(data.errors)
        ? data.errors
        : [];

    importResultData = {
        created_rows: Array.isArray(data.created_rows)
            ? data.created_rows
            : [],
        updated_rows: Array.isArray(data.updated_rows)
            ? data.updated_rows
            : [],
        skipped_rows: Array.isArray(data.skipped_rows)
            ? data.skipped_rows
            : [],
        failed_rows: Array.isArray(data.failed_rows)
            ? data.failed_rows
            : errors.map((error, index) => ({
                row: index + 1,
                admission_number: '',
                student_name: '',
                reason: error
            }))
    };

    const counts = {
        created: Number(data.created || 0),
        updated: Number(data.updated || 0),
        skipped: Number(data.skipped || 0),
        failed: Number(data.failed || errors.length || 0)
    };

    box.className =
        `alert student-import-result show ${
            success ? 'alert-success' : 'alert-danger'
        }`;

    box.innerHTML =
        `<strong>${esc(result.message || '')}</strong>` +
        `<div class="student-import-summary">
            ${Object.entries(counts).map(([type, count]) => `
                <button class="student-import-card"
                        type="button"
                        data-type="${type}">
                    <strong>${count}</strong>
                    <small>${type.charAt(0).toUpperCase() + type.slice(1)}</small>
                    <span class="student-import-view-label">Click to View</span>
                </button>
            `).join('')}
        </div>`;

    $('importDetails').classList.remove('show');
    $('importDetailsBody').innerHTML = '';

    box.querySelectorAll('.student-import-card').forEach(card => {
        card.onclick = () => showImportDetails(card.dataset.type);
    });
}

function message(text, success = false) {
    const box = $('studentMessage');
    box.className = 'alert student-message show ' + (success ? 'alert-success' : 'alert-danger');
    box.textContent = text;
    window.clearTimeout(box._timer);
    box._timer = window.setTimeout(() => {
        box.className = 'alert student-message';
    }, 6000);
}

function setOptions(elementId, rows, placeholder, selectedValue = '', labelCallback = null) {
    const element = $(elementId);
    const selected = String(selectedValue ?? '');
    element.innerHTML = `<option value="">${esc(placeholder)}</option>` + rows.map(row => {
        const label = labelCallback ? labelCallback(row) : (row.name ?? row.label ?? row.id);
        return `<option value="${esc(row.id)}">${esc(label)}</option>`;
    }).join('');
    element.value = selected;
}

function normalizeName(value) {
    return String(value ?? '').trim().toLowerCase();
}

function uniqueByName(rows, key) {
    const seen = new Set();
    return rows.filter(row => {
        const value = normalizeName(row[key]);
        if (!value || seen.has(value)) return false;
        seen.add(value);
        return true;
    });
}

function currentClassRow() {
    const classId = Number($('classId').value || 0);
    const academicYearId = Number($('academicYearId').value || 0);
    const rows = meta.classes || [];

    return rows.find(row =>
        Number(row.id) === classId
        && (!academicYearId || Number(row.academic_year_id) === academicYearId)
    ) || rows.find(row => Number(row.id) === classId) || null;
}

function refreshFormClasses(selectedValue = '') {
    const academicYearId = Number($('academicYearId').value || 0);
    const allRows = [...(meta.classes || [])].sort((a, b) =>
        Number(b.academic_year_id || 0) - Number(a.academic_year_id || 0)
        || Number(a.display_order || 0) - Number(b.display_order || 0)
        || String(a.class_name || '').localeCompare(String(b.class_name || ''))
    );

    let rows = allRows.filter(row =>
        !academicYearId || Number(row.academic_year_id) === academicYearId
    );

    /*
     * A newly-created academic year may not yet have separate class rows.
     * In that case, use the latest active class definitions as templates.
     * The API creates/uses the canonical class for the selected year while saving.
     */
    if (academicYearId > 0 && rows.length === 0) {
        rows = uniqueByName(allRows, 'class_name');
    }

    rows = uniqueByName(rows, 'class_name');
    setOptions('classId', rows, rows.length ? 'Select Class' : 'No Classes Available', selectedValue, row => row.class_name);
    $('classId').disabled = rows.length === 0;
}

function refreshFormSections(selectedValue = '') {
    const academicYearId = Number($('academicYearId').value || 0);
    const selectedClass = currentClassRow();
    const className = normalizeName(selectedClass?.class_name || '');
    const selectedClassId = Number(selectedClass?.id || 0);
    const allRows = [...(meta.sections || [])].sort((a, b) =>
        Number(b.academic_year_id || 0) - Number(a.academic_year_id || 0)
        || Number(a.display_order || 0) - Number(b.display_order || 0)
        || String(a.section_name || '').localeCompare(String(b.section_name || ''))
    );

    if (!selectedClass) {
        setOptions('sectionId', [], 'Not Assigned');
        $('sectionId').disabled = false;
        return;
    }

    const matchesClass = row =>
        normalizeName(row.class_name_snapshot || '') === className
        || Number(row.class_id || 0) === selectedClassId;

    let rows = allRows.filter(row =>
        Number(row.academic_year_id || 0) === academicYearId && matchesClass(row)
    );

    /*
     * Never reuse a section from another academic year.
     * A section ID is year-specific and the API correctly rejects a mismatch.
     */
    rows = uniqueByName(rows, 'section_name');

    const validSelectedValue = rows.some(
        row => String(row.id) === String(selectedValue || '')
    ) ? String(selectedValue) : '';

    setOptions(
        'sectionId',
        rows,
        rows.length ? 'Not Assigned' : 'No Sections Available',
        validSelectedValue,
        row => `${row.section_name}${row.section_code ? ` (${row.section_code})` : ''}`
    );

    $('sectionId').disabled = false;
}

function money(value) {
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 2
    }).format(Number(value || 0));
}

function feeFrequencyLabel(value) {
    return ({
        one_time: 'One Time',
        monthly: 'Monthly',
        term: 'Term-wise',
        annual: 'Annual',
        custom: 'Custom'
    })[String(value || '').toLowerCase()] || String(value || 'One Time');
}

function renderSelectedFeeStructure() {
    const structureId = Number($('feeStructureId').value || 0);
    const structure = (meta.fee_structures || []).find(
        row => Number(row.id || 0) === structureId
    );
    const preview = $('feeStructurePreview');

    if (!structure) {
        preview.classList.remove('show');
        $('feePreviewList').innerHTML = '';
        $('feePreviewTotal').textContent = money(0);
        return;
    }

    const items = Array.isArray(structure.items)
        ? structure.items
        : (Array.isArray(structure.fee_items) ? structure.fee_items : []);

    const activeItems = items.filter(
        item => String(item.item_status || item.status || 'active').toLowerCase() === 'active'
    );

    $('feePreviewTitle').textContent = structure.structure_name || 'Fee Structure Details';
    $('feePreviewList').innerHTML = activeItems.length
        ? activeItems.map(item => {
            const amount = Number(item.amount || 0);
            const occurrences = Math.max(1, Number(item.occurrence_count || 1));
            const annual = Number(
                item.annual_amount !== undefined
                    ? item.annual_amount
                    : amount * occurrences
            );

            return `<div class="student-fee-preview-row">
                <div><strong>${esc(item.fee_type_name || item.head_name || 'Fee')}</strong><small class="d-block text-muted">${esc(item.description || '')}</small></div>
                <span>${esc(feeFrequencyLabel(item.frequency))}</span>
                <span>${occurrences} time${occurrences === 1 ? '' : 's'}</span>
                <span class="amount ${amount === 0 ? 'student-fee-zero' : ''}">${money(amount)}${occurrences > 1 ? ` × ${occurrences} = ${money(annual)}` : ''}</span>
            </div>`;
        }).join('')
        : '<div class="student-empty">No active fee items are configured for this structure.</div>';

    const total = activeItems.reduce((sum, item) => {
        const amount = Number(item.amount || 0);
        const occurrences = Math.max(1, Number(item.occurrence_count || 1));
        return sum + Number(
            item.annual_amount !== undefined
                ? item.annual_amount
                : amount * occurrences
        );
    }, 0);

    structure.annual_total = total;
    structure.total_amount = total;
    $('feePreviewTotal').textContent = money(total);
    preview.classList.add('show');
    window.lucide?.createIcons();
}

function refreshFeeStructures(selectedValue = '', allowYearFallback = true) {
    const academicYearId = Number($('academicYearId').value || 0);
    const selectedClass = currentClassRow();
    const className = normalizeName(selectedClass?.class_name || '');
    const classId = Number(selectedClass?.id || 0);
    const allActive = (meta.fee_structures || []).filter(
        row => String(row.status || '').toLowerCase() === 'active'
    );

    const exactRows = allActive.filter(row =>
        Number(row.academic_year_id || 0) === academicYearId
        && (
            Number(row.class_id || 0) === classId
            || normalizeName(row.class_name || '') === className
        )
    );

    let rows = exactRows;
    let usedFallback = false;

    /*
     * When no structure exists for the currently selected year, reuse the
     * active structure for the same class name by moving the form to that
     * structure's own Academic Year and canonical Class. This keeps the
     * Student enrollment and Fee Structure IDs fully consistent.
     */
    if (rows.length === 0 && selectedClass && allowYearFallback) {
        const fallbackRows = allActive
            .filter(row => normalizeName(row.class_name || '') === className)
            .sort((a, b) =>
                Number(b.academic_year_id || 0) - Number(a.academic_year_id || 0)
                || Number(b.id || 0) - Number(a.id || 0)
            );

        if (fallbackRows.length > 0) {
            const fallback = fallbackRows[0];
            const fallbackYearId = Number(fallback.academic_year_id || 0);
            const fallbackClass = (meta.classes || []).find(row =>
                Number(row.id || 0) === Number(fallback.class_id || 0)
                && Number(row.academic_year_id || 0) === fallbackYearId
            ) || (meta.classes || []).find(row =>
                Number(row.academic_year_id || 0) === fallbackYearId
                && normalizeName(row.class_name || '') === className
            );

            if (fallbackYearId > 0 && fallbackClass) {
                $('academicYearId').value = String(fallbackYearId);
                refreshFormClasses(fallbackClass.id);
                $('classId').value = String(fallbackClass.id);
                refreshFormSections();
                rows = fallbackRows.filter(row =>
                    Number(row.academic_year_id || 0) === fallbackYearId
                    && (
                        Number(row.class_id || 0) === Number(fallbackClass.id)
                        || normalizeName(row.class_name || '') === className
                    )
                );
                usedFallback = true;
            }
        }
    }

    let selected = String(selectedValue || '');

    if (
        selected
        && !rows.some(row => String(row.id) === selected)
    ) {
        selected = '';
    }

    if (!selected && rows.length === 1) {
        selected = String(rows[0].id);
    }

    const currentYearId = Number($('academicYearId').value || 0);
    const currentClass = currentClassRow();
    const currentYearName = (
        (meta.academic_years || []).find(
            row => Number(row.id) === currentYearId
        )?.year_name || ''
    );

    const placeholder = rows.length
        ? 'Select Fee Structure'
        : (
            currentClass
                ? `No Active Fee Structure - ${currentYearName} / ${currentClass.class_name}`
                : 'Select Class First'
        );

    setOptions(
        'feeStructureId',
        rows,
        placeholder,
        selected,
        row => `${row.structure_name} · ₹${Number(row.annual_total || row.total_amount || 0).toLocaleString('en-IN')}`
    );

    $('feeStructureId').disabled = !currentClass || rows.length === 0;
    $('feeStructureId').required = true;

    renderSelectedFeeStructure();

    if (rows.length) {
        const selectedRow = rows.find(row => String(row.id) === String($('feeStructureId').value))
            || rows[0];

        $('feeStructureHint').textContent = usedFallback
            ? `Academic Year changed to ${currentYearName} because ${selectedRow.structure_name} is the active Fee Structure for ${currentClass.class_name}. Annual Fee: ₹${Number(selectedRow.annual_total || selectedRow.total_amount || 0).toLocaleString('en-IN')}`
            : `Loaded ${selectedRow.structure_name}. Annual Fee: ₹${Number(selectedRow.annual_total || selectedRow.total_amount || 0).toLocaleString('en-IN')}`;
    } else {
        $('feeStructureHint').textContent = currentClass
            ? `Create one Active Fee Structure for ${currentYearName} / ${currentClass.class_name} before saving the student.`
            : 'Select Academic Year and Class to load the Fee Structure.';
    }
}

function activeTransportRoutes() {
    const branchId = Number($('branchId').value || 0);

    return (meta.transport_routes || []).filter(row =>
        String(row.status || '').toLowerCase() === 'active'
        && (
            Number(row.branch_id || 0) === 0
            || Number(row.branch_id || 0) === branchId
        )
    );
}

function selectedTransportRoute() {
    const routeId = Number($('transportRouteId').value || 0);

    return activeTransportRoutes().find(
        row => Number(row.id) === routeId
    ) || null;
}

function selectedTransportStop() {
    const routeId = Number($('transportRouteId').value || 0);
    const stopId = Number($('transportStopId').value || 0);

    return (meta.transport_stops || []).find(
        row =>
            Number(row.id) === stopId
            && Number(row.route_id) === routeId
            && String(row.status || '').toLowerCase() === 'active'
    ) || null;
}

function updateTransportDetails() {
    const required = $('transportRequired').value === '1';
    const route = selectedTransportRoute();
    const stop = selectedTransportStop();

    $('transportVehicle').value = required && route
        ? (
            route.vehicle_name
                ? `${route.vehicle_name}${route.vehicle_number ? ` (${route.vehicle_number})` : ''}`
                : 'Not Assigned'
        )
        : '-';

    $('transportDriver').value = required && route
        ? (route.assigned_driver || 'Not Assigned')
        : '-';

    const fee = required && stop
        ? Number(stop.transport_fee || 0)
        : 0;

    $('transportFeeAmount').value = fee.toFixed(2);
    $('transportFee').value = money(fee);

    $('transportFeeHint').textContent = !required
        ? 'No transport fee will be added.'
        : !route
            ? 'Select a route.'
            : !stop
                ? 'Select a boarding stop to load its transport fee.'
                : `${stop.stop_name}: ${money(fee)} will be added automatically to Fee Collection.`;
}

function refreshTransportStops(selectedValue = '') {
    const required = $('transportRequired').value === '1';
    const routeId = Number($('transportRouteId').value || 0);

    const rows = (meta.transport_stops || [])
        .filter(row =>
            Number(row.route_id) === routeId
            && String(row.status || '').toLowerCase() === 'active'
        )
        .sort((a, b) =>
            Number(a.stop_order || 0) - Number(b.stop_order || 0)
            || String(a.stop_name || '').localeCompare(
                String(b.stop_name || '')
            )
        );

    let selected = String(selectedValue || '');

    if (
        selected
        && !rows.some(row => String(row.id) === selected)
    ) {
        selected = '';
    }

    if (!selected && required && rows.length === 1) {
        selected = String(rows[0].id);
    }

    setOptions(
        'transportStopId',
        rows,
        routeId
            ? (
                rows.length
                    ? 'Select Boarding Stop'
                    : 'No Active Stops'
            )
            : 'Select Route First',
        selected,
        row =>
            `${row.stop_name} · ${money(row.transport_fee || 0)}`
    );

    $('transportStopId').disabled =
        !required || routeId <= 0 || rows.length === 0;
    $('transportStopId').required = required;

    updateTransportDetails();
}

function refreshTransportRoutes(
    selectedRouteValue = '',
    selectedStopValue = ''
) {
    const required = $('transportRequired').value === '1';
    const rows = activeTransportRoutes();

    let selected = String(selectedRouteValue || '');

    if (
        selected
        && !rows.some(row => String(row.id) === selected)
    ) {
        selected = '';
    }

    if (!selected && required && rows.length === 1) {
        selected = String(rows[0].id);
    }

    setOptions(
        'transportRouteId',
        rows,
        rows.length ? 'Select Route' : 'No Active Routes',
        selected,
        row =>
            `${row.route_name}${row.route_code ? ` (${row.route_code})` : ''}`
    );

    $('transportRouteId').disabled = !required || rows.length === 0;
    $('transportRouteId').required = required;

    refreshTransportStops(selectedStopValue);
}

function toggleTransport(
    selectedRouteValue = '',
    selectedStopValue = ''
) {
    const required = $('transportRequired').value === '1';

    if (!required) {
        $('transportRouteId').value = '';
        $('transportStopId').value = '';
    }

    refreshTransportRoutes(
        selectedRouteValue,
        selectedStopValue
    );
}

function populateFilterOptions() {
    const classFilter = $('classFilter');
    const currentClass = classFilter.value || 'all';
    classFilter.innerHTML = '<option value="all">All Classes</option>' + (meta.classes || []).map(row => {
        const year = (meta.academic_years || []).find(item => Number(item.id) === Number(row.academic_year_id));
        const label = year ? `${row.class_name} · ${year.year_name}` : row.class_name;
        return `<option value="${esc(row.id)}">${esc(label)}</option>`;
    }).join('');
    classFilter.value = currentClass;

    const sectionFilter = $('sectionFilter');
    const currentSection = sectionFilter.value || 'all';
    sectionFilter.innerHTML = '<option value="all">All Sections</option>' + (meta.sections || []).map(row => {
        const classPart = row.class_name_snapshot ? `${row.class_name_snapshot} - ` : '';
        return `<option value="${esc(row.id)}">${esc(classPart + row.section_name)}</option>`;
    }).join('');
    sectionFilter.value = currentSection;

    const statusFilter = $('statusFilter');
    const currentStatus = statusFilter.value || 'all';
    statusFilter.innerHTML = '<option value="all">All Status</option>' + (meta.statuses || []).map(status => {
        const label = status === 'tc' ? 'TC / Transferred' : status.charAt(0).toUpperCase() + status.slice(1);
        return `<option value="${esc(status)}">${esc(label)}</option>`;
    }).join('');
    statusFilter.value = currentStatus;
}

function filters() {
    return {
        search: $('searchFilter').value.trim(),
        class_id: $('classFilter').value,
        section_id: $('sectionFilter').value,
        gender: $('genderFilter').value,
        status: $('statusFilter').value
    };
}

function renderStats(stats = {}) {
    const total = Number(stats.total || 0);
    const active = Number(stats.active || 0);
    const inactive = Number(stats.inactive || 0);
    $('statTotal').textContent = total.toLocaleString();
    $('statActive').textContent = active.toLocaleString();
    $('statNew').textContent = Number(stats.new_admissions || 0).toLocaleString();
    $('statInactive').textContent = inactive.toLocaleString();
    $('activePercent').textContent = (total ? ((active / total) * 100).toFixed(1) : 0) + '% of total students';
    $('inactivePercent').textContent = (total ? ((inactive / total) * 100).toFixed(1) : 0) + '% of total students';
}

function actionButtons(row) {
    return `<div class="student-actions">
        <button class="student-action js-view" data-id="${row.id}" title="View"><i data-lucide="eye"></i></button>
        ${permissions.edit ? `<button class="student-action js-edit" data-id="${row.id}" title="Edit"><i data-lucide="pencil"></i></button>` : ''}
        ${permissions.delete ? `<button class="student-action js-delete" data-id="${row.id}" title="Archive"><i data-lucide="trash-2"></i></button>` : ''}
    </div>`;
}

function classKey(row) {
    const id = Number(row.class_id || 0);
    return id > 0 ? `id:${id}` : `name:${normalizeName(row.class_name || 'Unassigned')}`;
}

function classGroups() {
    const groups = new Map();
    records.forEach(row => {
        const key = classKey(row);
        if (!groups.has(key)) {
            groups.set(key, {
                key,
                name: row.class_name || 'Unassigned',
                students: []
            });
        }
        groups.get(key).students.push(row);
    });

    return [...groups.values()].sort((a, b) =>
        String(a.name).localeCompare(String(b.name), undefined, {numeric: true, sensitivity: 'base'})
    );
}

function renderClassNavigation(groups) {
    const box = $('classWiseNavigation');

    if (!groups.length) {
        box.innerHTML = '<span class="text-muted">No classes found.</span>';
        activeClassKey = '';
        return;
    }

    if (!groups.some(group => group.key === activeClassKey)) {
        activeClassKey = groups[0].key;
    }

    box.innerHTML = groups.map(group => `
        <button type="button" class="student-class-button${group.key === activeClassKey ? ' active' : ''}" data-class-key="${esc(group.key)}">
            <span>${esc(group.name)}</span>
            <span class="student-class-count">${group.students.length}</span>
        </button>
    `).join('');

    box.querySelectorAll('.student-class-button').forEach(button => {
        button.onclick = () => {
            activeClassKey = button.dataset.classKey || '';
            render();
        };
    });
}

function render() {
    const groups = classGroups();
    renderClassNavigation(groups);
    const activeGroup = groups.find(group => group.key === activeClassKey);
    const visibleRecords = activeGroup?.students || [];

    $('studentBody').innerHTML = visibleRecords.map(row => `<tr>
        <td><strong>${esc(row.admission_number)}</strong></td>
        <td><div class="student-cell"><span class="student-avatar">${esc((row.student_name || '?').charAt(0).toUpperCase())}</span><strong>${esc(row.student_name)}</strong></div></td>
        <td>${esc(row.class_name || '-')}</td>
        <td>${esc(row.section_name || '-')}</td>
        <td>${esc(row.parent_name || '-')}</td>
        <td>${esc(row.mobile || '-')}</td>
        <td><span class="student-badge ${esc(row.status)}">${esc(row.status === 'tc' ? 'TC / Transferred' : row.status)}</span></td>
        <td>${actionButtons(row)}</td>
    </tr>`).join('') || '<tr><td colspan="8" class="student-empty">No students found in this class.</td></tr>';

    const className = activeGroup?.name || 'Students';
    $('studentListTitle').textContent = `${className} Students (${visibleRecords.length})`;
    $('recordCount').textContent = `Showing ${visibleRecords.length} student${visibleRecords.length === 1 ? '' : 's'} in ${className}`;
    bindActions();
    window.lucide?.createIcons();
}

function enableForm(enabled) {
    document.querySelectorAll('#studentForm input,#studentForm select,#studentForm textarea').forEach(element => {
        element.disabled = !enabled;
    });
    document.querySelector('#studentForm button[type=submit]').style.display = enabled ? '' : 'none';
}

function openForm(row = null, viewOnly = false) {
    const form = $('studentForm');
    form.reset();
    form.classList.remove('was-validated');
    enableForm(true);

    $('studentId').value = row?.id || '';
    $('studentModalTitle').textContent = viewOnly ? 'View Student' : (row ? 'Edit Student' : 'Add Student');

    const defaultYear = row?.academic_year_id || meta.current_academic_year_id || '';
    const defaultBranch = row?.branch_id || meta.current_branch_id || '';
    $('academicYearId').value = String(defaultYear);
    $('branchId').value = String(defaultBranch);

    refreshFormClasses(row?.class_id || '');
    $('classId').value = String(row?.class_id || '');
    refreshFormSections(row?.section_id || '');
    $('sectionId').value = String(row?.section_id || '');

    refreshFeeStructures(row?.fee_structure_id || '');
    $('feeStructureId').value = String(row?.fee_structure_id || $('feeStructureId').value || '');

    $('transportRequired').value =
        Number(row?.transport_required || 0) === 1 ? '1' : '0';

    toggleTransport(
        row?.transport_route_id || '',
        row?.transport_stop_id || ''
    );

    const map = {
        admissionNumber: 'admission_number',
        rollNumber: 'roll_number',
        studentName: 'student_name',
        dateOfBirth: 'date_of_birth',
        gender: 'gender',
        bloodGroup: 'blood_group',
        admissionDate: 'admission_date',
        studentStatus: 'status',
        parentName: 'parent_name',
        relationship: 'relationship',
        mobile: 'mobile',
        email: 'email',
        address: 'address',
        notes: 'notes'
    };

    Object.entries(map).forEach(([elementId, key]) => {
        $(elementId).value = row?.[key] ?? '';
    });

    if (!row) {
        $('studentStatus').value = 'active';
        $('relationship').value = 'Father';
        $('admissionDate').value = new Date().toISOString().slice(0, 10);
        $('transportRequired').value = '0';
        toggleTransport();
    }

    enableForm(!viewOnly);
    bootstrap.Modal.getOrCreateInstance($('studentModal')).show();
}

function bindActions() {
    document.querySelectorAll('.js-view').forEach(button => {
        button.onclick = () => openForm(records.find(row => Number(row.id) === Number(button.dataset.id)), true);
    });
    document.querySelectorAll('.js-edit').forEach(button => {
        button.onclick = () => openForm(records.find(row => Number(row.id) === Number(button.dataset.id)), false);
    });
    document.querySelectorAll('.js-delete').forEach(button => {
        button.onclick = async () => {
            if (!confirm('Archive this student?')) return;
            try {
                const result = await request('delete', {id: Number(button.dataset.id)}, 'POST');
                message(result.message, true);
                await load();
            } catch (error) {
                message(error.message, false);
            }
        };
    });
}

async function loadMeta() {
    const result = await request('meta');
    meta = result.data.meta || {};
    permissions = result.data.permissions || {};
    csrfToken = result.data.csrf_token || csrfToken;

    setOptions('academicYearId', meta.academic_years || [], 'Select Academic Year', meta.current_academic_year_id || '', row => row.year_name);
    setOptions('branchId', meta.branches || [], 'Select Branch', meta.current_branch_id || '', row => row.branch_name);
    refreshFormClasses();
    refreshFormSections();
    refreshFeeStructures();
    refreshTransportRoutes();
    populateFilterOptions();

    $('addStudentButton').style.display = permissions.add ? '' : 'none';
    $('importButton').style.display = permissions.import ? '' : 'none';
    $('exportLink').style.display = permissions.export ? '' : 'none';
}

async function load() {
    const result = await request('list', filters());
    records = result.data.records || [];
    renderStats(result.data.stats || {});
    render();
    $('exportLink').href = apiUrl + '?action=export&format=csv&' + new URLSearchParams(filters());
}

$('academicYearId').addEventListener('change', () => {
    $('classId').value = '';
    $('sectionId').value = '';
    refreshFormClasses();
    refreshFormSections();
    refreshFeeStructures();
    refreshTransportRoutes();
});

$('classId').addEventListener('change', () => {
    $('sectionId').value = '';
    $('feeStructureId').value = '';
    refreshFormSections();
    refreshFeeStructures();
    refreshTransportRoutes();
});

$('feeStructureId').addEventListener('change', () => {
    renderSelectedFeeStructure();
    const structureId = Number($('feeStructureId').value || 0);
    const structure = (meta.fee_structures || []).find(
        row => Number(row.id || 0) === structureId
    );

    if (!structure) {
        return;
    }

    const yearId = Number(structure.academic_year_id || 0);
    const classRow = (meta.classes || []).find(row =>
        Number(row.id || 0) === Number(structure.class_id || 0)
        && Number(row.academic_year_id || 0) === yearId
    ) || (meta.classes || []).find(row =>
        Number(row.academic_year_id || 0) === yearId
        && normalizeName(row.class_name || '') === normalizeName(structure.class_name || '')
    );

    if (
        yearId > 0
        && classRow
        && (
            Number($('academicYearId').value || 0) !== yearId
            || Number($('classId').value || 0) !== Number(classRow.id)
        )
    ) {
        $('academicYearId').value = String(yearId);
        refreshFormClasses(classRow.id);
        $('classId').value = String(classRow.id);
        refreshFormSections();
        refreshFeeStructures(String(structure.id), false);
    }
});

$('branchId').addEventListener('change', () => {
    $('transportRouteId').value = '';
    $('transportStopId').value = '';
    toggleTransport();
});

$('transportRequired').addEventListener('change', () => {
    $('transportRouteId').value = '';
    $('transportStopId').value = '';
    toggleTransport();
});

$('transportRouteId').addEventListener('change', () => {
    $('transportStopId').value = '';
    refreshTransportStops();
});

$('transportStopId').addEventListener('change', () => {
    updateTransportDetails();
});

$('studentForm').onsubmit = async event => {
    event.preventDefault();
    const form = event.currentTarget;
    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return;
    }

    const year = $('academicYearId');
    const branch = $('branchId');
    const classSelect = $('classId');
    const section = $('sectionId');

    const data = {
        id: Number($('studentId').value || 0),
        academic_year_id: Number(year.value),
        academic_year_name: year.options[year.selectedIndex]?.text || '',
        branch_id: Number(branch.value),
        branch_name: branch.options[branch.selectedIndex]?.text || '',
        class_id: Number(classSelect.value),
        class_name: classSelect.options[classSelect.selectedIndex]?.text || '',
        section_id: Number(section.value || 0),
        section_name: section.options[section.selectedIndex]?.text || '',
        fee_structure_id: Number($('feeStructureId').value || 0),
        transport_required:
            Number($('transportRequired').value || 0),
        transport_route_id:
            Number($('transportRouteId').value || 0),
        transport_stop_id:
            Number($('transportStopId').value || 0),
        admission_number: $('admissionNumber').value.trim(),
        roll_number: $('rollNumber').value.trim(),
        student_name: $('studentName').value.trim(),
        date_of_birth: $('dateOfBirth').value,
        gender: $('gender').value,
        blood_group: $('bloodGroup').value,
        admission_date: $('admissionDate').value,
        status: $('studentStatus').value,
        parent_name: $('parentName').value.trim(),
        relationship: $('relationship').value,
        mobile: $('mobile').value.trim(),
        email: $('email').value.trim(),
        address: $('address').value.trim(),
        notes: $('notes').value.trim()
    };

    if (
        data.transport_required === 1
        && data.transport_route_id <= 0
    ) {
        message('Select a Route.', false);
        return;
    }

    if (
        data.transport_required === 1
        && data.transport_stop_id <= 0
    ) {
        message('Select a Boarding Stop.', false);
        return;
    }

    try {
        const result = await request('save', data, 'POST');
        bootstrap.Modal.getInstance($('studentModal'))?.hide();
        message(result.message, true);
        await load();
    } catch (error) {
        message(error.message, false);
    }
};

$('importForm').onsubmit = async event => {
    event.preventDefault();

    const file = $('missingImportFile').files?.[0]
        || $('importFile').files?.[0];

    if (!file) {
        renderImportResult({message: 'Choose an Excel or CSV file first.', data: {errors: []}}, false);
        return;
    }

    const extension = String(file.name || '').split('.').pop().toLowerCase();
    if (!['xlsx', 'csv'].includes(extension)) {
        renderImportResult({message: 'Only .xlsx and .csv files are supported.', data: {errors: []}}, false);
        return;
    }
    if (file.size > 10 * 1024 * 1024) {
        renderImportResult({message: 'Import file cannot exceed 10 MB.', data: {errors: []}}, false);
        return;
    }

    let fallbackValues = {};
    try {
        fallbackValues = collectMissingColumnFallbacks();
    } catch (error) {
        renderImportResult({message: error.message, data: {errors: []}}, false);
        return;
    }

    try {
        $('importSubmitButton').disabled = true;
        $('importSubmitButton').innerHTML = '<span class="spinner-border spinner-border-sm"></span> Importing...';
        const result = await uploadImportFile(file, fallbackValues);
        resetMissingColumnsFlow();
        renderImportResult(result, true);
        await load();
    } catch (error) {
        const result = error?.result || null;
        const missingColumns = Array.isArray(result?.data?.missing_columns)
            ? result.data.missing_columns
            : [];

        if (result?.data?.type === 'missing_required_columns' && missingColumns.length) {
            renderImportResult(result, false);
            renderMissingColumnsFlow(missingColumns);
        } else {
            renderImportResult({message: error.message, data: {errors: []}}, false);
        }
    } finally {
        $('importSubmitButton').disabled = false;
        $('importSubmitButton').innerHTML = '<i data-lucide="upload"></i> Import Students';
        window.lucide?.createIcons();
    }
};

$('searchFilter').addEventListener('input', () => {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(load, 300);
});
['classFilter', 'sectionFilter', 'genderFilter', 'statusFilter'].forEach(id => $(id).addEventListener('change', load));

$('resetButton').onclick = () => {
    $('searchFilter').value = '';
    ['classFilter', 'sectionFilter', 'genderFilter', 'statusFilter'].forEach(id => $(id).value = 'all');
    load();
};
$('missingColumnsButton').onclick = () => {
    $('missingColumnsPanel').classList.toggle('show');
    if ($('missingColumnsPanel').classList.contains('show')) {
        $('missingColumnsPanel').scrollIntoView({
            behavior: 'smooth',
            block: 'nearest'
        });
    }
};

$('closeMissingColumnsButton').onclick = () => {
    $('missingColumnsPanel').classList.remove('show');
};

$('importFile').addEventListener('change', () => {
    resetMissingColumnsFlow();
});

$('closeImportDetailsButton').onclick = () => {
    $('importDetails').classList.remove('show');
    document.querySelectorAll('.student-import-card').forEach(
        card => card.classList.remove('active')
    );
};

$('importButton').onclick = () => {
    $('importForm').reset();
    resetMissingColumnsFlow();
    $('duplicateMode').value = 'skip';
    $('importResult').className = 'alert student-import-result';
    $('importResult').innerHTML = '';
    $('importDetails').classList.remove('show');
    $('importDetailsBody').innerHTML = '';
    importResultData = {
        created_rows: [],
        updated_rows: [],
        skipped_rows: [],
        failed_rows: []
    };
    $('downloadXlsxTemplate').href = `${apiUrl}?action=import_template&format=xlsx`;
    $('downloadCsvTemplate').href = `${apiUrl}?action=import_template&format=csv`;
    bootstrap.Modal.getOrCreateInstance($('importModal')).show();
    window.lucide?.createIcons();
};
$('viewAllButton').onclick = () => {
    $('searchFilter').value = '';
    $('classFilter').value = 'all';
    activeClassKey = '';
    load();
};

(async () => {
    try {
        await loadMeta();
        await load();
        window.lucide?.createIcons();
    } catch (error) {
        message(error.message, false);
    }
})();
})();
</script>
<?php require dirname(__DIR__) . '/includes/layout-end.php'; ?>

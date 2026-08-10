<?php
declare(strict_types=1);

ob_start();
$pageTitle='Timetable Management';
$pageKey='timetable';
$sidebarFile=__DIR__.'/sidebar.php';
require dirname(__DIR__).'/includes/layout-start.php';

$csrfToken=function_exists('csrfToken')?csrfToken():'';
?>
<style>
.tt-page{display:grid;gap:16px}
.tt-page .page-title{font-size:28px;line-height:1.1}
.tt-page .page-subtitle{margin-top:4px}
.tt-page .page-actions{gap:10px}
.tt-message{display:none}
.tt-message.show{display:block}

.tt-stats{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:14px;
}
.tt-stat{
    border:0;
    border-radius:14px;
    min-height:110px;
    padding:18px 20px;
    display:flex;
    align-items:center;
    gap:14px;
    color:#fff;
    position:relative;
    overflow:hidden;
    box-shadow:0 12px 28px rgba(15,23,42,.08);
}
.tt-stat::after{
    content:"";
    position:absolute;
    width:110px;
    height:110px;
    border-radius:50%;
    right:-38px;
    top:-40px;
    background:rgba(255,255,255,.08);
}
.tt-stat.green{background:linear-gradient(135deg,#45c783,#1ea568)}
.tt-stat.pink{background:linear-gradient(135deg,#ff527c,#ed2f63)}
.tt-stat.orange{background:linear-gradient(135deg,#ffb327,#ff8a17)}
.tt-stat.blue{background:linear-gradient(135deg,#4696ef,#2469d5)}
.tt-stat-icon{
    width:50px;
    height:50px;
    border-radius:50%;
    background:rgba(255,255,255,.16);
    display:grid;
    place-items:center;
    flex:0 0 auto;
}
.tt-stat-icon svg{width:25px;height:25px}
.tt-stat strong{display:block;font-size:26px;line-height:1}
.tt-stat small{display:block;font-size:11px;font-weight:700;opacity:.94;margin-bottom:6px}
.tt-stat .trend{font-size:9px;font-weight:700;opacity:.94;margin-top:8px}

.tt-tabs{
    display:flex;
    gap:8px;
    overflow:auto;
    padding:10px;
}
.tt-tab{
    border:1px solid var(--border-soft,#e7ebf3);
    background:var(--card-bg,#fff);
    color:var(--text-main,#101b46);
    border-radius:9px;
    padding:9px 12px;
    font-size:11px;
    font-weight:700;
    white-space:nowrap;
}
.tt-tab.active{
    color:#fff;
    border-color:transparent;
    background:linear-gradient(135deg,#6747e8,#2f62d7);
}
.tt-panel{display:none}
.tt-panel.active{display:block}

.tt-filter-card{border-radius:14px;overflow:hidden}
.tt-filter{
    padding:14px 16px;
    display:grid;
    grid-template-columns:minmax(230px,1.5fr) repeat(7,minmax(120px,.7fr));
    gap:9px;
}
.tt-export-row{
    padding:0 16px 14px;
    display:flex;
    gap:8px;
    flex-wrap:wrap;
}

.tt-overview-layout{
    display:grid;
    grid-template-columns:minmax(0,1fr);
    gap:16px;
    align-items:start;
}
.tt-card{border-radius:14px;overflow:hidden}
.tt-card-head{
    padding:14px 16px;
    border-bottom:1px solid var(--border-soft,#e7ebf3);
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
}
.tt-card-head strong{font-size:14px}
.tt-week-wrap{
    width:100%;
    overflow-x:auto;
    overflow-y:hidden;
    -webkit-overflow-scrolling:touch;
    scrollbar-gutter:stable;
}
.tt-week{
    width:100%;
    min-width:920px;
    table-layout:fixed;
}
.tt-week th,.tt-week td{
    min-width:120px;
    vertical-align:top;
    font-size:10px;
    white-space:normal;
    word-break:break-word;
}
.tt-week th:first-child,.tt-week td:first-child{
    width:92px;
    min-width:92px;
    position:sticky;
    left:0;
    z-index:2;
    background:var(--card-bg,#fff);
}
.tt-week thead th:first-child{z-index:3}
.tt-week .dropCell{height:92px}
.tt-slot{
    padding:8px;
    border-radius:8px;
    background:rgba(99,102,241,.09);
    margin-bottom:5px;
    font-size:10px;
    cursor:grab;
}
.tt-slot strong,.tt-slot small{display:block}
.tt-slot small{color:var(--text-muted,#64748b);margin-top:2px}
.dropCell{min-height:48px}
.drag-over{outline:2px dashed var(--brand-1,#6547e8)}

.tt-entry-table{min-width:980px}
.tt-entry-table th{font-size:10px}
.tt-entry-table td{font-size:11px;vertical-align:middle}
.tt-student-cell{display:flex;align-items:center;gap:9px}
.tt-avatar{
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
.tt-badge{
    display:inline-flex;
    align-items:center;
    padding:5px 9px;
    border-radius:999px;
    font-size:9px;
    font-weight:800;
    text-transform:capitalize;
}
.tt-badge.approved{color:#16834f;background:#e8f8ef}
.tt-badge.pending{color:#9a6700;background:#fff7d6}
.tt-badge.draft{color:#2563eb;background:#eaf2ff}
.tt-badge.rejected,.tt-badge.archived{color:#dc2626;background:#fff0f1}

.tt-actions{display:flex;gap:5px;flex-wrap:wrap}
.tt-action{
    width:30px;
    height:30px;
    display:grid;
    place-items:center;
    border:1px solid #d7def1;
    border-radius:7px;
    background:var(--card-bg,#fff);
    color:#4f46e5;
}
.tt-action svg{width:13px;height:13px}

.tt-grid{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:12px;
}
.tt-grid .full{grid-column:1/-1}
.tt-empty{padding:40px 18px;text-align:center;color:var(--text-muted,#64748b)}
.tt-conflict{padding:12px;border:1px solid #fecaca;border-radius:10px;background:#fff7f7;margin-bottom:8px}
.tt-log{padding:12px;border-bottom:1px solid var(--border-soft,#e7ebf3)}

#entryModal .modal-dialog,
#periodModal .modal-dialog,
#assignModal .modal-dialog,
#subModal .modal-dialog,
#importModal .modal-dialog{
    max-height:calc(100dvh - 32px);
    margin:16px auto;
}
#entryModal .modal-content,
#periodModal .modal-content,
#assignModal .modal-content,
#subModal .modal-content,
#importModal .modal-content{
    max-height:calc(100dvh - 32px);
    overflow:hidden;
}
#entryModal form,
#periodModal form,
#assignModal form,
#subModal form,
#importModal form{
    display:flex;
    flex-direction:column;
    max-height:calc(100dvh - 32px);
}
#entryModal .modal-body,
#periodModal .modal-body,
#assignModal .modal-body,
#subModal .modal-body,
#importModal .modal-body{
    overflow-y:auto;
    min-height:0;
}

@media(max-width:1400px){
    .tt-filter{grid-template-columns:repeat(4,1fr)}
}
@media(max-width:1150px){
    .tt-overview-layout{grid-template-columns:1fr}
}
@media(max-width:900px){
    .tt-stats{grid-template-columns:repeat(2,1fr)}
    .tt-grid,.tt-filter{grid-template-columns:repeat(2,1fr)}
}
@media(max-width:575px){
    .tt-stats,.tt-grid,.tt-filter{grid-template-columns:1fr}
    .tt-grid .full{grid-column:auto}
    .tt-card-head{align-items:flex-start;flex-direction:column}
    .tt-card-head .form-select{width:100%!important}
    .tt-week{min-width:760px}
    .tt-week th,.tt-week td{min-width:105px}
    .tt-week th:first-child,.tt-week td:first-child{
        width:78px;
        min-width:78px;
    }
}
</style>
<div class="tt-page">
    <div class="page-heading">
        <div>
            <h1 class="page-title">Timetable Management</h1>
            <p class="page-subtitle">Plan, monitor and manage school timetables efficiently</p>
        </div>
        <div class="page-actions">
            <button id="addBtn" class="btn-ui btn-primary-ui" type="button">
                <i data-lucide="plus"></i> Add Timetable
            </button>
            <button id="autoBtn" class="btn-ui" type="button">
                <i data-lucide="calendar-sync"></i> Auto Generate
            </button>
            <a id="excelLink" class="btn-ui">
                <i data-lucide="download"></i> Export Report
            </a>
            <button id="filterToggleBtn" class="btn-ui" type="button">
                <i data-lucide="list-filter"></i> Filters
            </button>
            <button id="notifyBtn" class="d-none" type="button"></button>
        </div>
    </div>

    <div id="msg" class="alert tt-message"></div>

    <section class="tt-stats">
        <article class="tt-stat green">
            <span class="tt-stat-icon"><i data-lucide="calendar-check-2"></i></span>
            <div>
                <small>Total Entries</small>
                <strong id="sTotal">0</strong>
                <div class="trend"><span id="sTeachers">0</span> teachers scheduled</div>
            </div>
        </article>

        <article class="tt-stat pink">
            <span class="tt-stat-icon"><i data-lucide="badge-check"></i></span>
            <div>
                <small>Approved Timetables</small>
                <strong id="sApproved">0</strong>
                <div class="trend"><span id="sRooms">0</span> rooms allocated</div>
            </div>
        </article>

        <article class="tt-stat orange">
            <span class="tt-stat-icon"><i data-lucide="clock-3"></i></span>
            <div>
                <small>Pending Approval</small>
                <strong id="sPending">0</strong>
                <div class="trend">Waiting for review</div>
            </div>
        </article>

        <article class="tt-stat blue">
            <span class="tt-stat-icon"><i data-lucide="triangle-alert"></i></span>
            <div>
                <small>Detected Conflicts</small>
                <strong id="sConflicts">0</strong>
                <div class="trend">Teacher and room clashes</div>
            </div>
        </article>
    </section>

    <section class="ui-card tt-tabs">
        <button class="tt-tab active" data-tab="weekly" type="button">Weekly Overview</button>
        <button class="tt-tab" data-tab="class" type="button">Class</button>
        <button class="tt-tab" data-tab="section" type="button">Section</button>
        <button class="tt-tab" data-tab="teacher" type="button">Teacher</button>
        <button class="tt-tab" data-tab="room" type="button">Room</button>
        <button class="tt-tab" data-tab="periods" type="button">Periods</button>
        <button class="tt-tab" data-tab="assignments" type="button">Subject Assignment</button>
        <button class="tt-tab" data-tab="generator" type="button">Generator</button>
        <button class="tt-tab" data-tab="substitutes" type="button">Substitutes</button>
        <button class="tt-tab" data-tab="conflicts" type="button">Conflicts</button>
        <button class="tt-tab" data-tab="availability" type="button">Availability</button>
        <button class="tt-tab" data-tab="history" type="button">Logs & History</button>
    </section>

    <section id="advancedFilters" class="ui-card tt-filter-card" style="display:none">
        <div class="tt-filter">
            <input id="fSearch" class="form-control" placeholder="Search timetable, class, subject, teacher or room...">
            <select id="fYear" class="form-select"><option value="all">All years</option></select>
            <select id="fBranch" class="form-select"><option value="all">All branches</option></select>
            <select id="fShift" class="form-select"><option value="all">All shifts</option></select>
            <select id="fDay" class="form-select"><option value="all">All days</option></select>
            <select id="fClass" class="form-select"><option value="all">All classes</option></select>
            <select id="fTeacher" class="form-select"><option value="all">All teachers</option></select>
            <select id="fStatus" class="form-select"><option value="all">All statuses</option></select>
        </div>
        <div class="tt-export-row">
            <a id="printLink" class="btn-ui" target="_blank"><i data-lucide="printer"></i> Print</a>
            <a id="pdfLink" class="btn-ui"><i data-lucide="file-text"></i> PDF</a>
            <a id="csvLink" class="btn-ui"><i data-lucide="file-down"></i> CSV</a>
            <button id="importBtn" class="btn-ui" type="button"><i data-lucide="file-up"></i> Import</button>
            <button id="refreshBtn" class="btn-ui" type="button"><i data-lucide="refresh-cw"></i> Refresh</button>
        </div>
    </section>

    <section class="tt-panel active" data-panel="weekly">
        <section class="tt-overview-layout">
            <section class="ui-card tt-card">
                <div class="tt-card-head">
                    <strong>Weekly Class Timetable Overview</strong>
                    <select id="weeklyViewSelect" class="form-select form-select-sm" style="width:auto">
                        <option>This Week</option>
                    </select>
                </div>
                <div class="tt-week-wrap">
                    <table class="data-table tt-week">
                        <thead id="weekHead"></thead>
                        <tbody id="weekBody"></tbody>
                    </table>
                </div>
            </section>

        </section>
    </section>

    <?php foreach(
        [
            'class'=>'Class Timetable',
            'section'=>'Section Timetable',
            'teacher'=>'Teacher Timetable',
            'room'=>'Room Timetable'
        ] as $k=>$v
    ): ?>
        <section class="tt-panel" data-panel="<?=e($k)?>">
            <section class="ui-card tt-card">
                <div class="tt-card-head"><strong><?=e($v)?></strong></div>
                <div class="table-responsive">
                    <table class="data-table tt-entry-table">
                        <thead>
                            <tr>
                                <th>Day</th><th>Period</th><th>Class</th><th>Section</th>
                                <th>Subject</th><th>Teacher</th><th>Room</th><th>Status</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody class="entryList"></tbody>
                    </table>
                </div>
            </section>
        </section>
    <?php endforeach; ?>

<section class="tt-panel" data-panel="periods"><div class="page-heading"><div><h2 class="page-title">Period Management</h2></div><button id="addPeriod" type="button" class="btn-ui btn-primary-ui">Add Period</button></div><section class="ui-card"><div class="table-responsive"><table class="data-table"><thead><tr><th>Period</th><th>Code</th><th>Year</th><th>Shift</th><th>Start</th><th>End</th><th>Break</th><th>Status</th><th>Actions</th></tr></thead><tbody id="periodBody"></tbody></table></div></section></section>
<section class="tt-panel" data-panel="assignments"><div class="page-heading"><div><h2 class="page-title">Subject Assignment</h2></div><button id="addAssign" type="button" class="btn-ui btn-primary-ui">Add Assignment</button></div><section class="ui-card"><div class="table-responsive"><table class="data-table"><thead><tr><th>Class</th><th>Section</th><th>Subject</th><th>Teacher</th><th>Periods/Week</th><th>Status</th><th>Actions</th></tr></thead><tbody id="assignBody"></tbody></table></div></section></section>
<section class="tt-panel" data-panel="generator"><section class="ui-card"><div class="ui-card-header"><strong>Timetable Generator</strong></div><div class="p-3 tt-grid"><div><label class="form-label">Academic Year</label><select id="genYear" class="form-select"></select></div><div><label class="form-label">Shift</label><select id="genShift" class="form-select"></select></div><div><label class="form-label">Mode</label><select id="genMode" class="form-select"><option value="balanced">Balanced</option><option value="teacher_priority">Teacher Priority</option><option value="room_priority">Room Priority</option></select></div><div><label class="form-label">Max Attempts</label><input id="genAttempts" type="number" class="form-control" value="500"></div><div class="full"><div class="alert alert-info">Uses subject assignments, periods and conflict rules. Generated rows are Draft.</div></div><div class="full"><button id="runGen" type="button" class="btn-ui btn-primary-ui">Run Generator</button></div></div></section></section>
<section class="tt-panel" data-panel="substitutes"><div class="page-heading"><div><h2 class="page-title">Substitute Management</h2></div><button id="addSub" type="button" class="btn-ui btn-primary-ui">Add Substitute</button></div><section class="ui-card"><div class="table-responsive"><table class="data-table"><thead><tr><th>Date</th><th>Class</th><th>Subject</th><th>Original</th><th>Substitute</th><th>Reason</th><th>Status</th></tr></thead><tbody id="subBody"></tbody></table></div></section></section>
<section class="tt-panel" data-panel="conflicts"><section class="ui-card"><div class="ui-card-header"><strong>Conflict Detection</strong><button id="checkConflicts" type="button" class="btn-ui">Check Again</button></div><div class="p-3 tt-grid"><div><h3 class="h6">Teacher Conflicts</h3><div id="teacherConflicts"></div></div><div><h3 class="h6">Room Conflicts</h3><div id="roomConflicts"></div></div></div></section></section>
<section class="tt-panel" data-panel="availability"><section class="ui-card"><div class="ui-card-header"><strong>Teacher & Room Availability</strong></div><div class="p-3"><div class="alert alert-info mb-0">Availability tables are future-ready. Current check uses conflict detection against existing timetable entries.</div></div></section></section>
<section class="tt-panel" data-panel="history"><section class="ui-card"><div class="ui-card-header"><strong>Activity Logs, Audit Logs & Version History</strong><button id="loadLogs" type="button" class="btn-ui">Load Logs</button></div><div id="logsBody"></div></section></section>
</div>
<div class="modal fade" id="entryModal"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content"><form id="entryForm"><div class="modal-header"><h5 id="entryTitle" class="modal-title">Add Timetable Entry</h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><input id="entryId" type="hidden"><div class="tt-grid"><div><label class="form-label">Academic Year</label><select id="entryYear" class="form-select" required></select></div><div><label class="form-label">Branch</label><select id="entryBranch" class="form-select"><option value="0">School Level</option></select></div><div><label class="form-label">Shift</label><select id="entryShift" class="form-select"></select></div><div><label class="form-label">Name</label><input id="entryName" class="form-control" value="Weekly Timetable"></div><div><label class="form-label">Day</label><select id="entryDay" class="form-select"></select></div><div><label class="form-label">Period</label><select id="entryPeriod" class="form-select"></select></div><div><label class="form-label">Class</label><select id="entryClass" class="form-select"></select></div><div><label class="form-label">Section</label><select id="entrySection" class="form-select"><option value="">All Sections</option></select></div><div><label class="form-label">Subject</label><select id="entrySubject" class="form-select"></select></div><div><label class="form-label">Teacher</label><select id="entryTeacher" class="form-select"><option value="">Not Assigned</option></select></div><div><label class="form-label">Room</label><select id="entryRoom" class="form-select"><option value="">Not Assigned</option></select></div><div><label class="form-label">Type</label><select id="entryType" class="form-select"><option value="regular">Regular</option><option value="lab">Lab</option><option value="activity">Activity</option><option value="break">Break</option><option value="assembly">Assembly</option><option value="special">Special</option></select></div><div><label class="form-label">Status</label><select id="entryStatus" class="form-select"><option value="draft">Draft</option><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option><option value="archived">Archived</option></select></div><div><label class="form-label">Change Note</label><input id="entryChange" class="form-control"></div><div class="full"><label class="form-label">Approval Note</label><textarea id="entryNote" class="form-control" rows="2"></textarea></div></div></div><div class="modal-footer"><button class="btn-ui" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn-ui btn-primary-ui" type="submit">Save Entry</button></div></form></div></div></div>
<div class="modal fade" id="periodModal"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><form id="periodForm"><div class="modal-header"><h5 class="modal-title">Period Management</h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><input id="periodId" type="hidden"><div class="tt-grid"><div><label class="form-label">Academic Year</label><select id="periodYear" class="form-select"></select></div><div><label class="form-label">Branch</label><select id="periodBranch" class="form-select"><option value="0">School Level</option></select></div><div><label class="form-label">Shift</label><select id="periodShift" class="form-select"></select></div><div><label class="form-label">Name</label><input id="periodName" class="form-control"></div><div><label class="form-label">Code</label><input id="periodCode" class="form-control"></div><div><label class="form-label">Start</label><input id="periodStart" type="time" class="form-control"></div><div><label class="form-label">End</label><input id="periodEnd" type="time" class="form-control"></div><div><label class="form-label">Order</label><input id="periodOrder" type="number" class="form-control" value="0"></div><div><label class="form-label">Status</label><select id="periodStatus" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select></div><div class="d-flex align-items-end"><div class="form-check form-switch mb-2"><input id="periodBreak" type="checkbox" class="form-check-input"><label class="form-check-label">Break</label></div></div></div></div><div class="modal-footer"><button class="btn-ui" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn-ui btn-primary-ui" type="submit">Save Period</button></div></form></div></div></div>
<div class="modal fade" id="assignModal"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><form id="assignForm"><div class="modal-header"><h5 class="modal-title">Subject Assignment</h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><input id="assignId" type="hidden"><div class="tt-grid"><div><label class="form-label">Academic Year</label><select id="assignYear" class="form-select"></select></div><div><label class="form-label">Branch</label><select id="assignBranch" class="form-select"><option value="0">School Level</option></select></div><div><label class="form-label">Class</label><select id="assignClass" class="form-select"></select></div><div><label class="form-label">Section</label><select id="assignSection" class="form-select"><option value="">All Sections</option></select></div><div><label class="form-label">Subject</label><select id="assignSubject" class="form-select"></select></div><div><label class="form-label">Teacher</label><select id="assignTeacher" class="form-select"><option value="">Not Assigned</option></select></div><div><label class="form-label">Periods/Week</label><input id="assignPeriods" type="number" class="form-control" value="5"></div><div><label class="form-label">Status</label><select id="assignStatus" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select></div></div></div><div class="modal-footer"><button class="btn-ui" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn-ui btn-primary-ui" type="submit">Save Assignment</button></div></form></div></div></div>
<div class="modal fade" id="subModal"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><form id="subForm"><div class="modal-header"><h5 class="modal-title">Add Substitute</h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="tt-grid"><div class="full"><label class="form-label">Timetable Entry</label><select id="subEntry" class="form-select"></select></div><div><label class="form-label">Date</label><input id="subDate" type="date" class="form-control"></div><div><label class="form-label">Teacher</label><select id="subTeacher" class="form-select"></select></div><div><label class="form-label">Status</label><select id="subStatus" class="form-select"><option value="pending">Pending</option><option value="approved">Approved</option><option value="completed">Completed</option><option value="rejected">Rejected</option></select></div><div class="full"><label class="form-label">Reason</label><textarea id="subReason" class="form-control"></textarea></div></div></div><div class="modal-footer"><button class="btn-ui" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn-ui btn-primary-ui" type="submit">Save Substitute</button></div></form></div></div></div>
<div class="modal fade" id="importModal"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><form id="importForm"><div class="modal-header"><h5 class="modal-title">Import CSV</h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="alert alert-info">Paste CSV with fields such as academic_year_id, shift_name, day_name, period_id, class_id, subject_id and names.</div><textarea id="csvText" class="form-control" rows="13"></textarea></div><div class="modal-footer"><button class="btn-ui" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn-ui btn-primary-ui" type="submit">Import</button></div></form></div></div></div>
<script>
(()=>{'use strict';const api=new URL('../api/timetable-api.php?api=1',location.href).href;let csrf=<?=json_encode($csrfToken)?>,meta={},perms={},entries=[],periods=[],assignments=[],subs=[];const days=['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];const $=id=>document.getElementById(id);const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));async function req(action,data={},method='GET'){let r;if(method==='GET'){const u=new URL(api,location.origin);u.searchParams.set('action',action);Object.entries(data).forEach(([k,v])=>{if(v!==''&&v!==null&&v!==undefined)u.searchParams.set(k,v)});r=await fetch(u,{headers:{Accept:'application/json'},credentials:'same-origin'})}else r=await fetch(api,{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json'},credentials:'same-origin',body:JSON.stringify({action,csrf_token:csrf,...data})});const t=await r.text();let j;try{j=JSON.parse(t)}catch{throw Error(`Timetable API returned HTTP ${r.status}. ${String(t).replace(/<[^>]*>/g,' ').replace(/\s+/g,' ').trim().slice(0,350)||'Invalid server response.'}`)}if(!r.ok||!j.success)throw Error(j.message||'Request failed');return j}function msg(t,ok){$('msg').className='alert tt-message show '+(ok?'alert-success':'alert-danger');$('msg').textContent=t}function fill(id,rows,v,l,keep=false,data={}){const e=$(id);if(!e)return;const first=keep?(e.options[0]?.outerHTML||''):'';e.innerHTML=first+rows.map(r=>`<option value="${esc(r[v])}"${Object.entries(data).map(([dk,rk])=>` data-${dk}="${esc(r[rk]??'')}"`).join('')}>${esc(r[l])}</option>`).join('')}function simple(id,vals,keep=false){fill(id,vals.map(v=>({v})), 'v','v',keep)}function filters(){return{search:$('fSearch').value,academic_year_id:$('fYear').value,branch_id:$('fBranch').value,shift_name:$('fShift').value,day_name:$('fDay').value,class_id:$('fClass').value,teacher_user_id:$('fTeacher').value,status:$('fStatus').value}}function links(){const q=new URLSearchParams(filters());$('printLink').href=api+'&action=export&format=print&'+q;$('pdfLink').href=api+'&action=export&format=pdf&'+q;$('excelLink').href=api+'&action=export&format=excel&'+q;$('csvLink').href=api+'&action=export&format=csv&'+q}function dashboard(s){$('sTotal').textContent=s.total_entries||0;$('sApproved').textContent=s.approved_entries||0;$('sPending').textContent=s.pending_entries||0;$('sTeachers').textContent=s.teachers_used||0;$('sRooms').textContent=s.rooms_used||0;$('sConflicts').textContent=s.conflicts||0}function renderWeek(){const ps=periods.filter(p=>!Number(p.is_break)&&p.status==='active');$('weekHead').innerHTML='<tr><th>Day</th>'+ps.map(p=>`<th>${esc(p.period_name)}<small class="d-block text-muted">${esc(p.start_time)}-${esc(p.end_time)}</small></th>`).join('')+'</tr>';$('weekBody').innerHTML=days.map(d=>`<tr><th>${d}</th>${ps.map(p=>`<td class="dropCell" data-day="${d}" data-period="${p.id}">${entries.filter(e=>e.day_name===d&&Number(e.period_id)===Number(p.id)).map(e=>`<div class="tt-slot" draggable="true" data-id="${e.id}"><strong>${esc(e.class_name)} ${esc(e.section_name||'')}</strong>${esc(e.subject_name)}<small>${esc(e.teacher_name||'No teacher')} · ${esc(e.room_name||'No room')}</small></div>`).join('')}</td>`).join('')}</tr>`).join('');dragReady()}function actions(e){return `<div class="tt-actions"><button class="tt-action view" data-id="${e.id}"><i data-lucide="eye"></i></button>${perms.edit?`<button class="tt-action edit" data-id="${e.id}"><i data-lucide="square-pen"></i></button>`:''}${perms.add?`<button class="tt-action copy" data-id="${e.id}"><i data-lucide="copy-plus"></i></button>`:''}${perms.approve?`<button class="tt-action approve" data-id="${e.id}"><i data-lucide="badge-check"></i></button>`:''}${perms.delete?`<button class="tt-action del" data-id="${e.id}"><i data-lucide="trash-2"></i></button>`:''}</div>`}function renderLists(){
    document.querySelectorAll('.entryList').forEach(body=>{
        body.innerHTML=entries.map(e=>`
            <tr>
                <td>${esc(e.day_name)}</td>
                <td>${esc(e.period_name)}</td>
                <td>
                    <div class="tt-student-cell">
                        <span class="tt-avatar">${esc((e.class_name||'?').charAt(0).toUpperCase())}</span>
                        <strong>${esc(e.class_name)}</strong>
                    </div>
                </td>
                <td>${esc(e.section_name||'-')}</td>
                <td>${esc(e.subject_name)}</td>
                <td>${esc(e.teacher_name||'-')}</td>
                <td>${esc(e.room_name||'-')}</td>
                <td><span class="tt-badge ${esc(e.status)}">${esc(e.status)}</span></td>
                <td>${actions(e)}</td>
            </tr>
        `).join('')||'<tr><td colspan="9" class="tt-empty">No timetable entries.</td></tr>';
    });
    bindActions();
}function renderPeriods(){$('periodBody').innerHTML=periods.map(p=>`<tr><td>${esc(p.period_name)}</td><td>${esc(p.period_code)}</td><td>${esc(meta.academic_years?.find(y=>Number(y.id)===Number(p.academic_year_id))?.year_name||'-')}</td><td>${esc(p.shift_name)}</td><td>${esc(p.start_time)}</td><td>${esc(p.end_time)}</td><td>${Number(p.is_break)?'Yes':'No'}</td><td>${esc(p.status)}</td><td><div class="tt-actions"><button class="tt-action pedit" data-id="${p.id}"><i data-lucide="square-pen"></i></button><button class="tt-action pdel" data-id="${p.id}"><i data-lucide="trash-2"></i></button></div></td></tr>`).join('')||'<tr><td colspan="9" class="tt-empty">No periods.</td></tr>';document.querySelectorAll('.pedit').forEach(b=>b.onclick=()=>openPeriod(periods.find(p=>Number(p.id)===Number(b.dataset.id))));document.querySelectorAll('.pdel').forEach(b=>b.onclick=async()=>{if(!confirm('Delete period?'))return;try{const r=await req('delete_period',{id:Number(b.dataset.id)},'POST');msg(r.message,true);await loadPeriods()}catch(e){msg(e.message,false)}})}function renderAssign(){$('assignBody').innerHTML=assignments.map(a=>`<tr><td>${esc(a.class_name)}</td><td>${esc(a.section_name||'All')}</td><td>${esc(a.subject_name)}</td><td>${esc(a.teacher_name||'-')}</td><td>${a.periods_per_week}</td><td>${esc(a.status)}</td><td><div class="tt-actions"><button class="tt-action aedit" data-id="${a.id}"><i data-lucide="square-pen"></i></button><button class="tt-action adel" data-id="${a.id}"><i data-lucide="trash-2"></i></button></div></td></tr>`).join('')||'<tr><td colspan="7" class="tt-empty">No assignments.</td></tr>';document.querySelectorAll('.aedit').forEach(b=>b.onclick=()=>openAssign(assignments.find(a=>Number(a.id)===Number(b.dataset.id))));document.querySelectorAll('.adel').forEach(b=>b.onclick=async()=>{if(!confirm('Delete assignment?'))return;try{const r=await req('delete_assignment',{id:Number(b.dataset.id)},'POST');msg(r.message,true);await loadAssign()}catch(e){msg(e.message,false)}})}function renderSubs(){$('subBody').innerHTML=subs.map(s=>`<tr><td>${esc(s.substitute_date)}</td><td>${esc(s.class_name||'-')} ${esc(s.section_name||'')}</td><td>${esc(s.subject_name||'-')}</td><td>${esc(s.original_teacher_name||'-')}</td><td>${esc(s.substitute_teacher_name)}</td><td>${esc(s.reason||'-')}</td><td>${esc(s.status)}</td></tr>`).join('')||'<tr><td colspan="7" class="tt-empty">No substitutes.</td></tr>'}function bindActions(){document.querySelectorAll('.view,.edit').forEach(b=>b.onclick=()=>openEntry(entries.find(e=>Number(e.id)===Number(b.dataset.id)),b.classList.contains('view')));document.querySelectorAll('.copy').forEach(b=>b.onclick=async()=>{try{const r=await req('duplicate',{id:Number(b.dataset.id)},'POST');msg(r.message,true);await reload()}catch(e){msg(e.message,false)}});document.querySelectorAll('.approve').forEach(b=>b.onclick=async()=>{const n=prompt('Approval note:','Approved');if(n===null)return;try{const r=await req('approve_entry',{id:Number(b.dataset.id),status:'approved',approval_note:n},'POST');msg(r.message,true);await reload()}catch(e){msg(e.message,false)}});document.querySelectorAll('.del').forEach(b=>b.onclick=async()=>{if(!confirm('Delete entry?'))return;try{const r=await req('delete_entry',{id:Number(b.dataset.id)},'POST');msg(r.message,true);await reload()}catch(e){msg(e.message,false)}})}function dragReady(){let dragged=null;document.querySelectorAll('.tt-slot').forEach(s=>s.ondragstart=()=>dragged=Number(s.dataset.id));document.querySelectorAll('.dropCell').forEach(c=>{c.ondragover=e=>{e.preventDefault();c.classList.add('drag-over')};c.ondragleave=()=>c.classList.remove('drag-over');c.ondrop=e=>{e.preventDefault();c.classList.remove('drag-over');msg('Drag-and-drop target captured. Persistence hook is future-ready.',true);console.log({dragged_entry_id:dragged,day_name:c.dataset.day,period_id:Number(c.dataset.period)})}})}function openEntry(e=null,view=false){$('entryForm').reset();$('entryId').value=e?.id||'';$('entryTitle').textContent=view?'View Timetable Entry':(e?'Edit Timetable Entry':'Add Timetable Entry');const map={entryYear:'academic_year_id',entryBranch:'branch_id',entryShift:'shift_name',entryName:'timetable_name',entryDay:'day_name',entryPeriod:'period_id',entryClass:'class_id',entrySection:'section_id',entrySubject:'subject_id',entryTeacher:'teacher_user_id',entryRoom:'room_id',entryType:'entry_type',entryStatus:'status',entryNote:'approval_note'};Object.entries(map).forEach(([id,k])=>$(id).value=e?.[k]??(id==='entryName'?'Weekly Timetable':id==='entryShift'?'General':''));document.querySelectorAll('#entryForm input,#entryForm select,#entryForm textarea').forEach(x=>x.disabled=view);document.querySelector('#entryForm button[type=submit]').style.display=view?'none':'';bootstrap.Modal.getOrCreateInstance($('entryModal')).show()}function openPeriod(p=null){$('periodForm').reset();$('periodId').value=p?.id||'';const map={periodYear:'academic_year_id',periodBranch:'branch_id',periodShift:'shift_name',periodName:'period_name',periodCode:'period_code',periodStart:'start_time',periodEnd:'end_time',periodOrder:'display_order',periodStatus:'status'};Object.entries(map).forEach(([id,k])=>$(id).value=p?.[k]??(id==='periodShift'?'General':id==='periodStatus'?'active':''));$('periodBreak').checked=Number(p?.is_break||0)===1;bootstrap.Modal.getOrCreateInstance($('periodModal')).show()}function openAssign(a=null){$('assignForm').reset();$('assignId').value=a?.id||'';const map={assignYear:'academic_year_id',assignBranch:'branch_id',assignClass:'class_id',assignSection:'section_id',assignSubject:'subject_id',assignTeacher:'teacher_user_id',assignPeriods:'periods_per_week',assignStatus:'status'};Object.entries(map).forEach(([id,k])=>$(id).value=a?.[k]??(id==='assignPeriods'?5:id==='assignStatus'?'active':''));bootstrap.Modal.getOrCreateInstance($('assignModal')).show()}function openSub(){$('subForm').reset();$('subDate').value=new Date().toISOString().slice(0,10);$('subEntry').innerHTML='<option value="">Select entry</option>'+entries.map(e=>`<option value="${e.id}">${esc(e.day_name+' · '+e.period_name+' · '+e.class_name+' '+(e.section_name||'')+' · '+e.subject_name)}</option>`).join('');bootstrap.Modal.getOrCreateInstance($('subModal')).show()}async function loadMeta(){const r=await req('meta');csrf=r.data.csrf_token||csrf;meta=r.data.meta||{};perms=r.data.permissions||{};periods=meta.periods||[];fill('fYear',meta.academic_years||[],'id','year_name',true);fill('fBranch',meta.branches||[],'id','branch_name',true);simple('fShift',meta.shifts||[],true);simple('fDay',meta.days||[],true);fill('fClass',meta.classes||[],'id','class_name',true);fill('fTeacher',meta.teachers||[],'id','teacher_name',true);simple('fStatus',meta.statuses||[],true);['entryYear','periodYear','assignYear','genYear'].forEach(id=>fill(id,meta.academic_years||[],'id','year_name'));['entryBranch','periodBranch','assignBranch'].forEach(id=>fill(id,meta.branches||[],'id','branch_name',true));['entryShift','periodShift','genShift'].forEach(id=>simple(id,meta.shifts||[]));simple('entryDay',meta.days||[]);fill('entryPeriod',periods,'id','period_name');fill('entryClass',meta.classes||[],'id','class_name');fill('assignClass',meta.classes||[],'id','class_name');fill('entrySection',meta.sections||[],'id','section_name',true);fill('assignSection',meta.sections||[],'id','section_name',true);fill('entrySubject',meta.subjects||[],'id','subject_name');fill('assignSubject',meta.subjects||[],'id','subject_name');fill('entryTeacher',meta.teachers||[],'id','teacher_name',true);fill('assignTeacher',meta.teachers||[],'id','teacher_name',true);fill('subTeacher',meta.teachers||[],'id','teacher_name');fill('entryRoom',meta.rooms||[],'id','room_name',true);$('addBtn').style.display=perms.add?'':'none';$('importBtn').style.display=perms.import?'':'none';$('autoBtn').style.display=perms.manage?'':'none'}async function loadDash(){dashboard((await req('dashboard')).data.summary||{})}async function loadEntries(){entries=(await req('list',filters())).data.entries||[];renderWeek();renderLists();links()}async function loadPeriods(){periods=(await req('periods')).data.periods||[];renderPeriods();fill('entryPeriod',periods,'id','period_name');renderWeek()}async function loadAssign(){assignments=(await req('assignments')).data.assignments||[];renderAssign()}async function loadSubs(){subs=(await req('substitutes')).data.substitutes||[];renderSubs()}async function conflicts(){try{const r=await req('conflicts'),t=r.data.teacher_conflicts||[],rm=r.data.room_conflicts||[];$('teacherConflicts').innerHTML=t.map(x=>`<div class="tt-conflict"><strong>${esc(x.teacher_name||'Teacher')}</strong><small class="d-block">${esc(x.day_name)} · ${esc(x.period_name)} · ${esc(x.affected_classes)}</small></div>`).join('')||'<div class="alert alert-success">No teacher conflicts.</div>';$('roomConflicts').innerHTML=rm.map(x=>`<div class="tt-conflict"><strong>${esc(x.room_name||'Room')}</strong><small class="d-block">${esc(x.day_name)} · ${esc(x.period_name)} · ${esc(x.affected_classes)}</small></div>`).join('')||'<div class="alert alert-success">No room conflicts.</div>'}catch(e){msg(e.message,false)}}async function reload(){await Promise.all([loadDash(),loadEntries(),loadPeriods(),loadAssign(),loadSubs()]);window.lucide?.createIcons()}
$('entryForm').onsubmit=async e=>{e.preventDefault();const p=$('entryPeriod'),c=$('entryClass'),s=$('entrySection'),sub=$('entrySubject'),t=$('entryTeacher'),r=$('entryRoom');try{const res=await req('save_entry',{id:Number($('entryId').value||0),academic_year_id:Number($('entryYear').value),branch_id:Number($('entryBranch').value||0),shift_name:$('entryShift').value,timetable_name:$('entryName').value,day_name:$('entryDay').value,period_id:Number(p.value),period_name:p.options[p.selectedIndex]?.text||'',class_id:Number(c.value),class_name:c.options[c.selectedIndex]?.text||'',section_id:Number(s.value||0),section_name:s.options[s.selectedIndex]?.text||'',subject_id:Number(sub.value),subject_name:sub.options[sub.selectedIndex]?.text||'',teacher_user_id:Number(t.value||0),teacher_name:t.options[t.selectedIndex]?.text||'',room_id:Number(r.value||0),room_name:r.options[r.selectedIndex]?.text||'',entry_type:$('entryType').value,status:$('entryStatus').value,approval_note:$('entryNote').value,change_note:$('entryChange').value},'POST');bootstrap.Modal.getInstance($('entryModal'))?.hide();msg(res.message,true);await reload()}catch(err){msg(err.message,false)}};
$('periodForm').onsubmit=async e=>{e.preventDefault();try{const res=await req('save_period',{id:Number($('periodId').value||0),academic_year_id:Number($('periodYear').value),branch_id:Number($('periodBranch').value||0),shift_name:$('periodShift').value,period_name:$('periodName').value,period_code:$('periodCode').value,start_time:$('periodStart').value,end_time:$('periodEnd').value,display_order:Number($('periodOrder').value||0),status:$('periodStatus').value,is_break:$('periodBreak').checked?1:0},'POST');bootstrap.Modal.getInstance($('periodModal'))?.hide();msg(res.message,true);await loadPeriods()}catch(err){msg(err.message,false)}};
$('assignForm').onsubmit=async e=>{e.preventDefault();const c=$('assignClass'),s=$('assignSection'),sub=$('assignSubject'),t=$('assignTeacher');try{const res=await req('save_assignment',{id:Number($('assignId').value||0),academic_year_id:Number($('assignYear').value),branch_id:Number($('assignBranch').value||0),class_id:Number(c.value),class_name:c.options[c.selectedIndex]?.text||'',section_id:Number(s.value||0),section_name:s.options[s.selectedIndex]?.text||'',subject_id:Number(sub.value),subject_name:sub.options[sub.selectedIndex]?.text||'',teacher_user_id:Number(t.value||0),teacher_name:t.options[t.selectedIndex]?.text||'',periods_per_week:Number($('assignPeriods').value||1),status:$('assignStatus').value},'POST');bootstrap.Modal.getInstance($('assignModal'))?.hide();msg(res.message,true);await loadAssign()}catch(err){msg(err.message,false)}};
$('subForm').onsubmit=async e=>{e.preventDefault();const t=$('subTeacher');try{const res=await req('save_substitute',{timetable_entry_id:Number($('subEntry').value),substitute_date:$('subDate').value,substitute_teacher_user_id:Number(t.value),substitute_teacher_name:t.options[t.selectedIndex]?.text||'',status:$('subStatus').value,reason:$('subReason').value},'POST');bootstrap.Modal.getInstance($('subModal'))?.hide();msg(res.message,true);await loadSubs()}catch(err){msg(err.message,false)}};$('importForm').onsubmit=async e=>{e.preventDefault();try{const r=await req('import_csv',{csv_text:$('csvText').value},'POST');bootstrap.Modal.getInstance($('importModal'))?.hide();msg(r.message+(r.data.errors?.length?' '+r.data.errors.join(' | '):''),!r.data.errors?.length);await reload()}catch(err){msg(err.message,false)}};document.querySelectorAll('.tt-tab').forEach(t=>t.onclick=()=>{document.querySelectorAll('.tt-tab,.tt-panel').forEach(x=>x.classList.remove('active'));t.classList.add('active');document.querySelector(`[data-panel="${t.dataset.tab}"]`)?.classList.add('active');if(t.dataset.tab==='conflicts')conflicts()});['fSearch','fYear','fBranch','fShift','fDay','fClass','fTeacher','fStatus'].forEach(id=>$(id).addEventListener(id==='fSearch'?'input':'change',loadEntries));$('filterToggleBtn').onclick=()=>{
    const box=$('advancedFilters');
    box.style.display=box.style.display==='none'?'block':'none';
};
$('addBtn').onclick=()=>openEntry();$('addPeriod').onclick=()=>openPeriod();$('addAssign').onclick=()=>openAssign();$('addSub').onclick=openSub;$('importBtn').onclick=()=>bootstrap.Modal.getOrCreateInstance($('importModal')).show();$('refreshBtn').onclick=reload;$('checkConflicts').onclick=conflicts;$('autoBtn').onclick=()=>document.querySelector('[data-tab="generator"]').click();$('runGen').onclick=async()=>{try{const r=await req('auto_generate',{academic_year_id:Number($('genYear').value),shift_name:$('genShift').value,mode:$('genMode').value,max_attempts:Number($('genAttempts').value||500)},'POST');msg(r.message,true);await reload()}catch(e){msg(e.message,false)}};$('loadLogs').onclick=async()=>{try{const r=await req('logs');$('logsBody').innerHTML=(r.data.logs||[]).map(l=>`<div class="tt-log"><strong>${esc(l.action_name)}</strong><small class="d-block text-muted">${esc(l.entity_type)} #${esc(l.entity_id||'-')} · ${esc(l.created_at)}</small><span>${esc(l.description||'')}</span></div>`).join('')||'<div class="tt-empty">No logs.</div>'}catch(e){msg(e.message,false)}};$('notifyBtn').onclick=()=>msg('Notification event queued for timetable stakeholders.',true);(async()=>{try{await loadMeta();await reload();links();window.lucide?.createIcons()}catch(e){msg(e.message,false)}})();})();
</script>
<?php require dirname(__DIR__).'/includes/layout-end.php'; ?>

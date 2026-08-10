<?php
declare(strict_types=1);

$pageTitle = 'Student Reports';
$pageKey = 'student_reports';
require dirname(__DIR__) . '/includes/layout-start.php';
?>

<style>
*{box-sizing:border-box}
.sr-page{display:grid;gap:16px;width:100%;min-width:0}
.sr-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px}
.sr-head h1{margin:0;font-size:clamp(24px,2vw,30px)}
.sr-head p{margin:5px 0 0}
.sr-actions{display:flex;gap:9px;flex-wrap:wrap}
.sr-actions .btn-ui{min-height:40px;white-space:nowrap}
.sr-card{border-radius:14px;overflow:hidden;min-width:0}
.sr-filter-body{padding:16px}
.sr-filter-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;align-items:end}
.sr-filter-grid>div{min-width:0}
.sr-filter-grid .span-2{grid-column:span 2}
.sr-filter-grid .full{grid-column:1/-1}
.sr-filter-actions{display:flex;gap:9px;flex-wrap:wrap;justify-content:flex-end}
.sr-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
.sr-summary-card{padding:15px 16px;display:flex;align-items:center;justify-content:space-between;gap:12px;min-width:0}
.sr-summary-card .sr-summary-icon{width:40px;height:40px;border-radius:12px;display:grid;place-items:center;background:rgba(79,70,229,.09);color:#4f46e5;flex:0 0 auto}
.sr-summary-card .sr-summary-icon svg{width:19px;height:19px}
.sr-summary-card small{display:block;color:var(--text-muted,#64748b);font-size:11px}
.sr-summary-card strong{display:block;margin-top:3px;font-size:21px;line-height:1.1;overflow-wrap:anywhere}
.sr-table-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 16px;border-bottom:1px solid var(--border-soft,#e7ebf3)}
.sr-table-title{display:flex;align-items:center;gap:10px;min-width:0}
.sr-table-title span{width:34px;height:34px;border-radius:10px;display:grid;place-items:center;color:#4f46e5;background:#eef2ff;flex:0 0 auto}
.sr-table-title span svg{width:17px}
.sr-table-title strong{display:block;font-size:13px}
.sr-table-title small{display:block;margin-top:2px;color:var(--text-muted,#64748b)}
.sr-table-tools{display:flex;align-items:center;gap:9px;flex-wrap:wrap}
.sr-table-wrap{width:100%;overflow:auto}
.sr-table{width:100%;border-collapse:collapse;min-width:980px}
.sr-table th,.sr-table td{padding:11px 12px;border-bottom:1px solid var(--border-soft,#e7ebf3);vertical-align:middle;text-align:left;font-size:12px}
.sr-table th{position:sticky;top:0;z-index:2;background:var(--card-bg,#fff);font-size:11px;text-transform:uppercase;letter-spacing:.03em;white-space:nowrap}
.sr-table tbody tr:hover{background:rgba(79,70,229,.025)}
.sr-table td{overflow-wrap:anywhere}
.sr-student{display:flex;align-items:center;gap:9px;min-width:190px}
.sr-avatar{width:34px;height:34px;border-radius:10px;display:grid;place-items:center;overflow:hidden;background:#eef2ff;color:#4f46e5;font-weight:800;flex:0 0 auto}
.sr-avatar img{width:100%;height:100%;object-fit:cover}
.sr-student strong{display:block;font-size:12px}
.sr-student small{display:block;color:var(--text-muted,#64748b);margin-top:2px}
.sr-badge{display:inline-flex;align-items:center;gap:5px;min-height:24px;padding:4px 8px;border-radius:999px;font-size:10px;font-weight:700;white-space:nowrap;background:#f1f5f9;color:#334155}
.sr-badge.active,.sr-badge.paid,.sr-badge.present{background:#dcfce7;color:#166534}
.sr-badge.inactive,.sr-badge.absent,.sr-badge.unpaid{background:#fee2e2;color:#991b1b}
.sr-badge.partial,.sr-badge.late,.sr-badge.tc{background:#fef3c7;color:#92400e}
.sr-badge.alumni,.sr-badge.not_assigned{background:#e2e8f0;color:#475569}
.sr-money{font-variant-numeric:tabular-nums;white-space:nowrap}
.sr-empty{padding:42px 18px;text-align:center;color:var(--text-muted,#64748b)}
.sr-empty svg{width:34px;margin-bottom:8px}
.sr-loader{display:none;padding:30px;text-align:center;color:var(--text-muted,#64748b)}
.sr-loader.show{display:block}
.sr-pagination{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:13px 16px}
.sr-pagination-info{color:var(--text-muted,#64748b);font-size:11px}
.sr-pagination-actions{display:flex;gap:7px;align-items:center;flex-wrap:wrap}
.sr-page-number{min-width:38px;text-align:center;font-size:11px;font-weight:700}
.sr-message{display:none;margin:0}
.sr-message.show{display:block}
.sr-modal-backdrop{position:fixed;inset:0;z-index:1090;background:rgba(15,23,42,.55);display:none;align-items:center;justify-content:center;padding:18px}
.sr-modal-backdrop.show{display:flex}
.sr-modal{width:min(860px,100%);max-height:calc(100dvh - 36px);display:flex;flex-direction:column;border-radius:16px;background:var(--card-bg,#fff);box-shadow:0 24px 70px rgba(15,23,42,.28);overflow:hidden}
.sr-modal-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:15px 17px;border-bottom:1px solid var(--border-soft,#e7ebf3)}
.sr-modal-head h3{margin:0;font-size:17px}
.sr-modal-body{padding:16px;overflow:auto}
.sr-detail-hero{display:grid;grid-template-columns:auto minmax(0,1fr);gap:14px;align-items:center;padding:14px;border-radius:12px;background:rgba(79,70,229,.04)}
.sr-detail-avatar{width:70px;height:70px;border-radius:16px;display:grid;place-items:center;overflow:hidden;background:#eef2ff;color:#4f46e5;font-size:22px;font-weight:800}
.sr-detail-avatar img{width:100%;height:100%;object-fit:cover}
.sr-detail-hero h4{margin:0;font-size:18px}
.sr-detail-hero p{margin:4px 0 0;color:var(--text-muted,#64748b);font-size:11px}
.sr-detail-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:12px}
.sr-detail-item{padding:11px;border:1px solid var(--border-soft,#e7ebf3);border-radius:10px;min-width:0}
.sr-detail-item small{display:block;color:var(--text-muted,#64748b);font-size:10px}
.sr-detail-item strong{display:block;margin-top:4px;font-size:12px;overflow-wrap:anywhere}
.sr-detail-section{margin-top:15px}
.sr-detail-section h5{margin:0 0 9px;font-size:13px}
.sr-print-title{display:none}
@media(max-width:1250px){.sr-filter-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.sr-summary{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:800px){.sr-head{flex-direction:column;align-items:stretch}.sr-actions{display:grid;grid-template-columns:1fr 1fr}.sr-actions .btn-ui{justify-content:center}.sr-filter-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.sr-filter-grid .span-2{grid-column:span 2}.sr-table-head{align-items:flex-start;flex-direction:column}.sr-table-tools{width:100%}.sr-detail-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:520px){.sr-actions,.sr-summary,.sr-filter-grid{grid-template-columns:1fr}.sr-filter-grid .span-2,.sr-filter-grid .full{grid-column:auto}.sr-filter-actions{display:grid;grid-template-columns:1fr 1fr}.sr-filter-actions .btn-ui{justify-content:center}.sr-pagination{flex-direction:column;align-items:stretch}.sr-pagination-actions{justify-content:center}.sr-detail-grid{grid-template-columns:1fr}.sr-detail-hero{grid-template-columns:1fr;text-align:center;justify-items:center}}
@media print{
    .sidebar,.topbar,.app-header,.sr-head,.sr-filter-card,.sr-actions,.sr-table-tools,.sr-pagination,.sr-row-action,.sr-message{display:none!important}
    .main-content,.content-wrapper,.page-content{margin:0!important;padding:0!important;width:100%!important}
    .sr-page{display:block}.sr-summary{grid-template-columns:repeat(4,1fr);margin-bottom:10px}.sr-summary-card{border:1px solid #d8dee9;box-shadow:none!important}
    .sr-card{box-shadow:none!important;border:0!important}.sr-table-wrap{overflow:visible}.sr-table{min-width:0;font-size:9px}.sr-table th,.sr-table td{padding:5px;border:1px solid #d6dbe5;font-size:8px}.sr-table th{position:static;background:#eef2ff!important}
    .sr-print-title{display:block;text-align:center;margin-bottom:12px}.sr-print-title h2{margin:0;font-size:18px}.sr-print-title p{margin:4px 0 0;font-size:10px;color:#475569}
    @page{size:A4 landscape;margin:9mm}
}
</style>

<div class="sr-page">
    <div class="sr-print-title">
        <h2 id="printSchoolName">School</h2>
        <p id="printReportName">Student Report</p>
    </div>

    <div class="sr-head">
        <div>
            <h1>Student Reports</h1>
            <p class="text-muted">Generate student, admission, class, guardian, attendance, fee and transport reports.</p>
        </div>
        <div class="sr-actions">
            <button id="refreshReportBtn" class="btn-ui" type="button" data-permission-action="view">
                <i data-lucide="refresh-cw"></i> Refresh
            </button>
            <button id="exportReportBtn" class="btn-ui" type="button" data-permission-action="view">
                <i data-lucide="file-down"></i> Export CSV
            </button>
            <button id="printReportBtn" class="btn-ui btn-primary-ui" type="button" data-permission-action="view">
                <i data-lucide="printer"></i> Print
            </button>
        </div>
    </div>

    <div id="reportMessage" class="alert sr-message"></div>

    <section class="ui-card sr-card sr-filter-card">
        <div class="sr-filter-body">
            <div class="sr-filter-grid">
                <div class="span-2">
                    <label class="form-label" for="reportType">Report Type</label>
                    <select id="reportType" class="form-select">
                        <option value="directory">Student Directory</option>
                        <option value="admissions">Admission Register</option>
                        <option value="class_strength">Class Strength</option>
                        <option value="guardian_contacts">Guardian Contacts</option>
                        <option value="attendance_summary">Attendance Summary</option>
                        <option value="fee_summary">Fee Summary</option>
                        <option value="transport">Transport Students</option>
                    </select>
                </div>
                <div id="branchFilterWrap">
                    <label class="form-label" for="branchFilter">Branch</label>
                    <select id="branchFilter" class="form-select"><option value="0">All Branches</option></select>
                </div>
                <div>
                    <label class="form-label" for="academicYearFilter">Academic Year</label>
                    <select id="academicYearFilter" class="form-select"><option value="0">All Academic Years</option></select>
                </div>
                <div>
                    <label class="form-label" for="classFilter">Class</label>
                    <select id="classFilter" class="form-select"><option value="0">All Classes</option></select>
                </div>
                <div>
                    <label class="form-label" for="sectionFilter">Section</label>
                    <select id="sectionFilter" class="form-select"><option value="0">All Sections</option></select>
                </div>
                <div>
                    <label class="form-label" for="statusFilter">Student Status</label>
                    <select id="statusFilter" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="tc">TC</option>
                        <option value="alumni">Alumni</option>
                    </select>
                </div>
                <div>
                    <label class="form-label" for="genderFilter">Gender</label>
                    <select id="genderFilter" class="form-select">
                        <option value="">All Genders</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div id="dateFromWrap">
                    <label id="dateFromLabel" class="form-label" for="dateFrom">From Date</label>
                    <input id="dateFrom" class="form-control" type="date">
                </div>
                <div id="dateToWrap">
                    <label id="dateToLabel" class="form-label" for="dateTo">To Date</label>
                    <input id="dateTo" class="form-control" type="date">
                </div>
                <div class="span-2">
                    <label class="form-label" for="searchFilter">Search</label>
                    <input id="searchFilter" class="form-control" maxlength="120" placeholder="Student name, admission number, EMIS, mobile or email">
                </div>
                <div>
                    <label class="form-label" for="perPage">Rows Per Page</label>
                    <select id="perPage" class="form-select">
                        <option value="10">10</option>
                        <option value="20" selected>20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                <div class="sr-filter-actions">
                    <button id="resetFiltersBtn" class="btn-ui" type="button"><i data-lucide="rotate-ccw"></i> Reset</button>
                    <button id="generateReportBtn" class="btn-ui btn-primary-ui" type="button"><i data-lucide="bar-chart-3"></i> Generate</button>
                </div>
            </div>
        </div>
    </section>

    <section class="sr-summary">
        <article class="ui-card sr-summary-card">
            <div><small>Total Students</small><strong id="summaryTotalStudents">0</strong></div>
            <span class="sr-summary-icon"><i data-lucide="users"></i></span>
        </article>
        <article class="ui-card sr-summary-card">
            <div><small>Active Students</small><strong id="summaryActiveStudents">0</strong></div>
            <span class="sr-summary-icon"><i data-lucide="user-check"></i></span>
        </article>
        <article class="ui-card sr-summary-card">
            <div><small>Report Rows</small><strong id="summaryReportRows">0</strong></div>
            <span class="sr-summary-icon"><i data-lucide="rows-3"></i></span>
        </article>
        <article class="ui-card sr-summary-card">
            <div><small id="summaryExtraLabel">Branches</small><strong id="summaryExtraValue">0</strong></div>
            <span class="sr-summary-icon"><i data-lucide="building-2"></i></span>
        </article>
    </section>

    <section class="ui-card sr-card">
        <div class="sr-table-head">
            <div class="sr-table-title">
                <span><i data-lucide="file-user"></i></span>
                <div>
                    <strong id="reportTableTitle">Student Directory</strong>
                    <small id="reportTableSubtitle">Loading report...</small>
                </div>
            </div>
            <div class="sr-table-tools">
                <span id="activeScopeText" class="text-muted" style="font-size:11px"></span>
            </div>
        </div>
        <div id="reportLoader" class="sr-loader"><i data-lucide="loader-circle"></i><div>Loading report...</div></div>
        <div class="sr-table-wrap">
            <table class="sr-table">
                <thead id="reportTableHead"></thead>
                <tbody id="reportTableBody"></tbody>
            </table>
        </div>
        <div id="emptyReport" class="sr-empty" style="display:none">
            <i data-lucide="file-search"></i>
            <div>No records found for the selected filters.</div>
        </div>
        <div class="sr-pagination">
            <div id="paginationInfo" class="sr-pagination-info">0 records</div>
            <div class="sr-pagination-actions">
                <button id="firstPageBtn" class="btn-ui" type="button"><i data-lucide="chevrons-left"></i></button>
                <button id="prevPageBtn" class="btn-ui" type="button"><i data-lucide="chevron-left"></i></button>
                <span id="pageNumber" class="sr-page-number">1 / 1</span>
                <button id="nextPageBtn" class="btn-ui" type="button"><i data-lucide="chevron-right"></i></button>
                <button id="lastPageBtn" class="btn-ui" type="button"><i data-lucide="chevrons-right"></i></button>
            </div>
        </div>
    </section>
</div>

<div id="studentDetailModal" class="sr-modal-backdrop" aria-hidden="true">
    <div class="sr-modal" role="dialog" aria-modal="true" aria-labelledby="studentDetailTitle">
        <div class="sr-modal-head">
            <h3 id="studentDetailTitle">Student Details</h3>
            <button id="closeStudentDetailBtn" class="btn-ui" type="button"><i data-lucide="x"></i></button>
        </div>
        <div id="studentDetailBody" class="sr-modal-body"></div>
    </div>
</div>

<script>
(function(){
'use strict';

const apiUrl = new URL('../api/student-reports.php', window.location.href).href;
const state = {meta:null, rows:[], page:1, pages:1, total:0, loading:false};
const $ = id => document.getElementById(id);

const reportDefinitions = {
    directory: {
        title: 'Student Directory',
        columns: [
            ['student','Student'],['branch_name','Branch'],['year_name','Academic Year'],['class_section','Class / Section'],
            ['roll_no','Roll No'],['gender','Gender'],['mobile','Mobile'],['guardian','Guardian'],['status','Status'],['actions','Action']
        ]
    },
    admissions: {
        title: 'Admission Register',
        columns: [
            ['admission_date','Admission Date'],['student','Student'],['branch_name','Branch'],['year_name','Academic Year'],
            ['class_section','Class / Section'],['gender','Gender'],['guardian','Guardian'],['status','Status'],['actions','Action']
        ]
    },
    class_strength: {
        title: 'Class Strength',
        columns: [
            ['branch_name','Branch'],['year_name','Academic Year'],['class_section','Class / Section'],['capacity','Capacity'],
            ['total_students','Total'],['male_students','Male'],['female_students','Female'],['other_students','Other'],
            ['active_students','Active'],['inactive_students','Inactive']
        ]
    },
    guardian_contacts: {
        title: 'Guardian Contacts',
        columns: [
            ['student','Student'],['branch_name','Branch'],['class_section','Class / Section'],['guardian_name','Guardian'],
            ['relationship','Relationship'],['guardian_mobile','Mobile'],['guardian_email','Email'],['occupation','Occupation'],['actions','Action']
        ]
    },
    attendance_summary: {
        title: 'Attendance Summary',
        columns: [
            ['student','Student'],['branch_name','Branch'],['class_section','Class / Section'],['marked_days','Marked'],
            ['present_days','Present'],['absent_days','Absent'],['late_days','Late'],['leave_days','Leave'],
            ['attendance_percentage','Attendance %'],['actions','Action']
        ]
    },
    fee_summary: {
        title: 'Fee Summary',
        columns: [
            ['student','Student'],['branch_name','Branch'],['class_section','Class / Section'],['gross_amount','Gross'],
            ['paid_amount','Paid'],['balance_amount','Balance'],['payment_status','Payment Status'],['status','Student Status'],['actions','Action']
        ]
    },
    transport: {
        title: 'Transport Students',
        columns: [
            ['student','Student'],['branch_name','Branch'],['class_section','Class / Section'],['route_name','Route'],
            ['boarding_stop_name','Boarding Stop'],['vehicle_name','Vehicle'],['driver_name','Driver'],
            ['transport_fee_amount','Fee'],['actions','Action']
        ]
    }
};

function escapeHtml(value){
    return String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
}
function titleCase(value){return String(value||'').replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase())}
function number(value){return new Intl.NumberFormat('en-IN').format(Number(value||0))}
function money(value){return new Intl.NumberFormat('en-IN',{style:'currency',currency:'INR',maximumFractionDigits:2}).format(Number(value||0))}
function initials(name){return String(name||'S').trim().split(/\s+/).slice(0,2).map(v=>v[0]?.toUpperCase()||'').join('')||'S'}
function showMessage(message, success=false){const box=$('reportMessage');box.className='alert sr-message show '+(success?'alert-success':'alert-danger');box.textContent=message}
function clearMessage(){$('reportMessage').className='alert sr-message';$('reportMessage').textContent=''}
function setLoading(loading){state.loading=loading;$('reportLoader').classList.toggle('show',loading);$('generateReportBtn').disabled=loading;$('refreshReportBtn').disabled=loading}

async function request(action, params={}){
    const url=new URL(apiUrl);
    url.searchParams.set('action',action);
    Object.entries(params).forEach(([key,value])=>{if(value!==''&&value!==null&&value!==undefined)url.searchParams.set(key,String(value))});
    const response=await fetch(url,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});
    const text=await response.text();
    let result;
    try{result=JSON.parse(text)}catch(error){throw new Error(`Student Reports API returned HTTP ${response.status}.`)}
    if(!response.ok||!result.success)throw new Error(result.message||'Student Reports request failed.');
    return result;
}

function populateSelect(select, rows, valueKey, labelBuilder, firstLabel, selectedValue='0'){
    select.innerHTML=`<option value="0">${escapeHtml(firstLabel)}</option>`;
    rows.forEach(row=>{const option=document.createElement('option');option.value=String(row[valueKey]);option.textContent=labelBuilder(row);select.appendChild(option)});
    select.value=String(selectedValue||'0');
}

function refreshClassOptions(){
    if(!state.meta)return;
    const yearId=Number($('academicYearFilter').value||0);
    const currentClass=Number($('classFilter').value||0);
    const classes=state.meta.classes.filter(row=>yearId===0||Number(row.academic_year_id)===yearId);
    populateSelect($('classFilter'),classes,'id',row=>row.class_name,'All Classes',classes.some(r=>Number(r.id)===currentClass)?currentClass:0);
    refreshSectionOptions();
}
function refreshSectionOptions(){
    if(!state.meta)return;
    const classId=Number($('classFilter').value||0);
    const currentSection=Number($('sectionFilter').value||0);
    const sections=state.meta.sections.filter(row=>classId===0||Number(row.class_id)===classId);
    populateSelect($('sectionFilter'),sections,'id',row=>row.section_name,'All Sections',sections.some(r=>Number(r.id)===currentSection)?currentSection:0);
}

function updateDateVisibility(){
    const type=$('reportType').value;
    const visible=['admissions','attendance_summary'].includes(type);
    $('dateFromWrap').style.display=visible?'':'none';
    $('dateToWrap').style.display=visible?'':'none';
    $('dateFromLabel').textContent=type==='admissions'?'Admission From':'Attendance From';
    $('dateToLabel').textContent=type==='admissions'?'Admission To':'Attendance To';
}

function filters(){
    return {
        report_type:$('reportType').value,
        branch_id:$('branchFilter').value,
        academic_year_id:$('academicYearFilter').value,
        class_id:$('classFilter').value,
        section_id:$('sectionFilter').value,
        status:$('statusFilter').value,
        gender:$('genderFilter').value,
        date_from:$('dateFrom').value,
        date_to:$('dateTo').value,
        search:$('searchFilter').value.trim(),
        per_page:$('perPage').value,
        page:state.page
    };
}

function studentCell(row){
    const photo=String(row.photo_path||'');
    const avatar=photo?`<img src="../${escapeHtml(photo.replace(/^\/+/,''))}" alt="">`:escapeHtml(initials(row.student_name));
    return `<div class="sr-student"><span class="sr-avatar">${avatar}</span><span><strong>${escapeHtml(row.student_name||'-')}</strong><small>${escapeHtml(row.admission_no||'-')}</small></span></div>`;
}
function badge(value){const key=String(value||'').toLowerCase();return `<span class="sr-badge ${escapeHtml(key)}">${escapeHtml(titleCase(key||'-'))}</span>`}
function classSection(row){return `${escapeHtml(row.class_name||'-')} / ${escapeHtml(row.section_name||'-')}`}
function guardian(row){const name=row.guardian_name||'-';const mobile=row.guardian_mobile||'';return `<strong>${escapeHtml(name)}</strong>${mobile?`<div class="text-muted" style="font-size:10px">${escapeHtml(mobile)}</div>`:''}`}
function actionButton(row){if(!row.student_id)return '';return `<button class="btn-ui sr-row-action" type="button" data-view-student="${Number(row.student_id)}" title="View Student"><i data-lucide="eye"></i></button>`}

function renderCell(key,row){
    switch(key){
        case 'student': return studentCell(row);
        case 'class_section': return classSection(row);
        case 'guardian': return guardian(row);
        case 'status': case 'payment_status': return badge(row[key]);
        case 'gender': return titleCase(row.gender||'-');
        case 'attendance_percentage': return `<strong>${Number(row.attendance_percentage||0).toFixed(2)}%</strong>`;
        case 'gross_amount': case 'paid_amount': case 'balance_amount': case 'transport_fee_amount': return `<span class="sr-money">${money(row[key])}</span>`;
        case 'actions': return actionButton(row);
        default: return escapeHtml(row[key]??'-');
    }
}

function renderTable(type,rows){
    const definition=reportDefinitions[type]||reportDefinitions.directory;
    $('reportTableTitle').textContent=definition.title;
    $('printReportName').textContent=definition.title;
    $('reportTableHead').innerHTML='<tr>'+definition.columns.map(([,label])=>`<th>${escapeHtml(label)}</th>`).join('')+'</tr>';
    $('reportTableBody').innerHTML=rows.map(row=>'<tr>'+definition.columns.map(([key])=>`<td>${renderCell(key,row)}</td>`).join('')+'</tr>').join('');
    $('emptyReport').style.display=rows.length?'none':'block';
    window.lucide?.createIcons();
}

function renderSummary(summary){
    $('summaryTotalStudents').textContent=number(summary.total_students);
    $('summaryActiveStudents').textContent=number(summary.active_students);
    $('summaryReportRows').textContent=number(summary.report_rows);
    $('summaryExtraLabel').textContent=summary.extra_label||'Branches';
    let value=summary.extra_value||0;
    if(summary.extra_format==='currency')value=money(value);
    else if(summary.extra_format==='percent')value=Number(value).toFixed(2)+'%';
    else value=number(value);
    $('summaryExtraValue').textContent=value;
}

function renderPagination(pagination){
    state.page=Number(pagination.page||1);state.pages=Number(pagination.pages||1);state.total=Number(pagination.total||0);
    const perPage=Number(pagination.per_page||20);
    const start=state.total===0?0:(state.page-1)*perPage+1;
    const end=Math.min(state.total,state.page*perPage);
    $('paginationInfo').textContent=`Showing ${number(start)}–${number(end)} of ${number(state.total)} records`;
    $('pageNumber').textContent=`${state.page} / ${state.pages}`;
    $('firstPageBtn').disabled=state.page<=1;$('prevPageBtn').disabled=state.page<=1;$('nextPageBtn').disabled=state.page>=state.pages;$('lastPageBtn').disabled=state.page>=state.pages;
}

function activeScopeText(){
    if(!state.meta)return '';
    const branchId=Number($('branchFilter').value||0);
    const yearId=Number($('academicYearFilter').value||0);
    const branch=state.meta.branches.find(row=>Number(row.id)===branchId);
    const year=state.meta.academic_years.find(row=>Number(row.id)===yearId);
    return [branch?.branch_name||'All Branches',year?.year_name||'All Academic Years'].join(' · ');
}

async function loadMeta(){
    clearMessage();setLoading(true);
    try{
        const result=await request('meta');
        state.meta=result.data.meta;
        populateSelect($('branchFilter'),state.meta.branches,'id',row=>row.branch_name,'All Branches',state.meta.can_all_branches?0:state.meta.current_branch_id);
        $('branchFilterWrap').style.display=state.meta.can_all_branches?'':'none';
        populateSelect($('academicYearFilter'),state.meta.academic_years,'id',row=>row.year_name+(Number(row.is_current)===1?' (Current)':''),'All Academic Years',state.meta.current_academic_year_id);
        refreshClassOptions();
        $('printSchoolName').textContent=state.meta.school?.school_name||'School';
        const features=state.meta.features||{};
        [...$('reportType').options].forEach(option=>{
            if(option.value==='guardian_contacts'&&!features.guardians)option.disabled=true;
            if(option.value==='attendance_summary'&&!features.attendance)option.disabled=true;
            if(option.value==='fee_summary'&&!features.fees)option.disabled=true;
            if(option.value==='transport'&&!features.transport)option.disabled=true;
        });
        updateDateVisibility();
        await loadReport();
    }catch(error){showMessage(error.message,false)}finally{setLoading(false);window.lucide?.createIcons()}
}

async function loadReport(){
    clearMessage();setLoading(true);
    try{
        const result=await request('list',filters());
        state.rows=result.data.rows||[];
        renderSummary(result.data.summary||{});
        renderTable(result.data.report_type||$('reportType').value,state.rows);
        renderPagination(result.data.pagination||{});
        $('reportTableSubtitle').textContent=`${number(state.total)} record${state.total===1?'':'s'} found`;
        $('activeScopeText').textContent=activeScopeText();
    }catch(error){showMessage(error.message,false);renderTable($('reportType').value,[])}finally{setLoading(false)}
}

function resetFilters(){
    $('reportType').value='directory';
    $('branchFilter').value=state.meta?.can_all_branches?'0':String(state.meta?.current_branch_id||0);
    $('academicYearFilter').value=String(state.meta?.current_academic_year_id||0);
    $('statusFilter').value='';$('genderFilter').value='';$('dateFrom').value='';$('dateTo').value='';$('searchFilter').value='';$('perPage').value='20';
    refreshClassOptions();state.page=1;updateDateVisibility();loadReport();
}

function exportReport(){
    const url=new URL(apiUrl);url.searchParams.set('action','export');
    Object.entries(filters()).forEach(([key,value])=>{if(key!=='page'&&value!==''&&value!==null)url.searchParams.set(key,String(value))});
    window.location.href=url.href;
}

async function openStudentDetail(studentId){
    try{
        const result=await request('detail',{student_id:studentId,academic_year_id:$('academicYearFilter').value,branch_id:$('branchFilter').value});
        const detail=result.data.detail||{};const student=detail.student||{};const attendance=detail.attendance||{};const fees=detail.fees||{};const transport=detail.transport||{};
        const photo=student.photo_path?`<img src="../${escapeHtml(String(student.photo_path).replace(/^\/+/,''))}" alt="">`:escapeHtml(initials(student.student_name));
        $('studentDetailTitle').textContent=student.student_name||'Student Details';
        $('studentDetailBody').innerHTML=`
            <div class="sr-detail-hero"><div class="sr-detail-avatar">${photo}</div><div><h4>${escapeHtml(student.student_name||'-')}</h4><p>${escapeHtml(student.admission_no||'-')} · ${escapeHtml(student.class_name||'-')} / ${escapeHtml(student.section_name||'-')} · ${escapeHtml(student.year_name||'-')}</p></div></div>
            <div class="sr-detail-section"><h5>Student Information</h5><div class="sr-detail-grid">
                ${detailItem('Branch',student.branch_name)}${detailItem('Roll Number',student.roll_no)}${detailItem('Gender',titleCase(student.gender))}${detailItem('Date of Birth',student.date_of_birth)}${detailItem('Blood Group',student.blood_group)}${detailItem('Status',titleCase(student.status))}${detailItem('Mobile',student.mobile)}${detailItem('Email',student.email)}${detailItem('Admission Date',student.admission_date)}
            </div></div>
            <div class="sr-detail-section"><h5>Guardian</h5><div class="sr-detail-grid">${detailItem('Name',student.guardian_name)}${detailItem('Relationship',student.guardian_relationship)}${detailItem('Mobile',student.guardian_mobile)}${detailItem('Email',student.guardian_email)}${detailItem('Occupation',student.guardian_occupation)}</div></div>
            <div class="sr-detail-section"><h5>Attendance and Fees</h5><div class="sr-detail-grid">${detailItem('Marked Days',attendance.marked_days)}${detailItem('Present Days',attendance.present_days)}${detailItem('Attendance',Number(attendance.attendance_percentage||0).toFixed(2)+'%')}${detailItem('Gross Fee',money(fees.gross_amount))}${detailItem('Paid Fee',money(fees.paid_amount))}${detailItem('Balance Fee',money(fees.balance_amount))}</div></div>
            <div class="sr-detail-section"><h5>Transport</h5><div class="sr-detail-grid">${detailItem('Required',Number(transport.transport_required||0)===1?'Yes':'No')}${detailItem('Route',transport.route_name)}${detailItem('Boarding Stop',transport.boarding_stop_name)}${detailItem('Vehicle',transport.vehicle_name)}${detailItem('Driver',transport.driver_name)}${detailItem('Fee',money(transport.transport_fee_amount||transport.bus_fee_amount||0))}</div></div>`;
        $('studentDetailModal').classList.add('show');$('studentDetailModal').setAttribute('aria-hidden','false');window.lucide?.createIcons();
    }catch(error){showMessage(error.message,false)}
}
function detailItem(label,value){return `<div class="sr-detail-item"><small>${escapeHtml(label)}</small><strong>${escapeHtml(value===null||value===undefined||value===''?'-':value)}</strong></div>`}
function closeStudentDetail(){$('studentDetailModal').classList.remove('show');$('studentDetailModal').setAttribute('aria-hidden','true')}

$('academicYearFilter').addEventListener('change',()=>{refreshClassOptions();state.page=1});
$('classFilter').addEventListener('change',()=>{refreshSectionOptions();state.page=1});
$('reportType').addEventListener('change',()=>{state.page=1;updateDateVisibility()});
$('generateReportBtn').addEventListener('click',()=>{state.page=1;loadReport()});
$('refreshReportBtn').addEventListener('click',loadReport);
$('resetFiltersBtn').addEventListener('click',resetFilters);
$('exportReportBtn').addEventListener('click',exportReport);
$('printReportBtn').addEventListener('click',()=>window.print());
$('firstPageBtn').addEventListener('click',()=>{if(state.page>1){state.page=1;loadReport()}});
$('prevPageBtn').addEventListener('click',()=>{if(state.page>1){state.page--;loadReport()}});
$('nextPageBtn').addEventListener('click',()=>{if(state.page<state.pages){state.page++;loadReport()}});
$('lastPageBtn').addEventListener('click',()=>{if(state.page<state.pages){state.page=state.pages;loadReport()}});
$('searchFilter').addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();state.page=1;loadReport()}});
$('reportTableBody').addEventListener('click',event=>{const button=event.target.closest('[data-view-student]');if(button)openStudentDetail(Number(button.dataset.viewStudent))});
$('closeStudentDetailBtn').addEventListener('click',closeStudentDetail);
$('studentDetailModal').addEventListener('click',event=>{if(event.target===$('studentDetailModal'))closeStudentDetail()});
document.addEventListener('keydown',event=>{if(event.key==='Escape')closeStudentDetail()});

loadMeta();
window.lucide?.createIcons();
})();
</script>

<?php require dirname(__DIR__) . '/includes/layout-end.php'; ?>

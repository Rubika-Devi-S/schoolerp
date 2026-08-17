<?php
declare(strict_types=1);

/* Build: 2026-08-17-attendance-strict-branch-v3 */

$pageTitle = 'Attendance Management';
$pageKey = 'student_attendance';
$sidebarFile = __DIR__ . '/sidebar.php';

if (isset($_GET['api']) && $_GET['api'] === '1') {
    /*
     * Keep the existing same-page API fallback, but route it through the
     * single strict branch-aware API so both endpoints always behave alike.
     */
    require dirname(__DIR__) . '/api/attendance.php';
    exit;
}

require dirname(__DIR__) . '/includes/layout-start.php';

/*
 * Use the ERP's existing global common toast component.
 *
 * The project common-toast.php exposes:
 *   showToast(type, message, title)
 *
 * No second toast implementation is created on this page.
 */
$commonToastFile =
    dirname(__DIR__)
    . '/includes/common-toast.php';

if (is_file($commonToastFile)) {
    require_once $commonToastFile;
}

unset($commonToastFile);


if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (
    empty($_SESSION['attendance_csrf_token'])
    || !is_string($_SESSION['attendance_csrf_token'])
) {
    $_SESSION['attendance_csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['attendance_csrf_token'];
?>
<style>
*{box-sizing:border-box}

.att-page{
    display:grid;
    gap:16px;
    width:100%;
    min-width:0;
}

.att-page .page-heading{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:16px;
    min-width:0;
}

.att-page .page-heading>div:first-child{min-width:0}

.att-page .page-title{
    margin:0;
    font-size:clamp(24px,2vw,30px);
    line-height:1.15;
}

.att-page .page-subtitle{
    margin-top:5px;
    max-width:760px;
}

.att-page .page-actions{
    display:flex;
    align-items:center;
    justify-content:flex-end;
    flex-wrap:wrap;
    gap:9px;
    min-width:0;
}

.att-page .page-actions .btn-ui{
    min-height:40px;
    white-space:nowrap;
}

.att-message{display:none}
.att-message.show{display:block}

.att-stats{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:14px;
}

.att-stat{
    border-radius:14px;
    min-height:116px;
    padding:18px 20px;
    display:flex;
    align-items:center;
    gap:14px;
    color:#fff;
    position:relative;
    overflow:hidden;
    min-width:0;
    box-shadow:0 12px 28px rgba(15,23,42,.08);
}

.att-stat::before{
    content:"";
    position:absolute;
    inset:0;
    background:linear-gradient(180deg,rgba(255,255,255,.03),rgba(15,23,42,.05));
    pointer-events:none;
}

.att-stat::after{
    content:"";
    position:absolute;
    width:118px;
    height:118px;
    border-radius:50%;
    right:-40px;
    top:-42px;
    background:rgba(255,255,255,.09);
}

.att-stat.green{background:linear-gradient(135deg,#45c783,#1ea568)}
.att-stat.pink{background:linear-gradient(135deg,#ff527c,#ed2f63)}
.att-stat.orange{background:linear-gradient(135deg,#ffb327,#ff8a17)}
.att-stat.blue{background:linear-gradient(135deg,#4696ef,#2469d5)}

.att-stat-icon{
    width:50px;
    height:50px;
    border-radius:50%;
    background:rgba(255,255,255,.16);
    display:grid;
    place-items:center;
    flex:0 0 auto;
    position:relative;
    z-index:1;
}

.att-stat-icon svg{width:25px;height:25px}

.att-stat>div{
    position:relative;
    z-index:1;
    min-width:0;
}

.att-stat strong{
    display:block;
    font-size:clamp(21px,1.8vw,27px);
    line-height:1.05;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}

.att-stat small{
    display:block;
    font-size:11px;
    font-weight:700;
    opacity:.96;
    margin-bottom:6px;
}

.att-stat .trend{
    font-size:9px;
    font-weight:700;
    opacity:.94;
    margin-top:8px;
    white-space:normal;
}

.att-filters{
    display:none;
    padding:14px 16px;
    grid-template-columns:minmax(220px,1.4fr) repeat(5,minmax(140px,.76fr));
    gap:10px;
    align-items:center;
}

.att-filters.show{display:grid}

.att-filters .form-control,
.att-filters .form-select{
    width:100%;
    min-width:0;
    min-height:40px;
}

.att-layout{
    display:block;
    min-width:0;
}

.att-card{
    border-radius:14px;
    overflow:hidden;
    min-width:0;
}

.att-card-head{
    padding:14px 16px;
    border-bottom:1px solid var(--border-soft,#e7ebf3);
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
    min-width:0;
}

.att-card-head strong{font-size:14px}

.att-card-head .form-select,
.att-card-head .form-control{
    width:auto;
    min-width:145px;
    min-height:38px;
}

.att-register-card{
    width:100%;
    min-width:0;
    box-shadow:0 10px 28px rgba(15,23,42,.06);
}

.att-register-head{padding:16px 18px}

.att-register-title{
    display:flex;
    align-items:center;
    gap:10px;
    min-width:0;
}

.att-register-icon{
    width:38px;
    height:38px;
    border-radius:10px;
    display:grid;
    place-items:center;
    color:#4f46e5;
    background:#eef2ff;
    flex:0 0 auto;
}

.att-register-icon svg{width:19px;height:19px}

.att-register-copy{min-width:0}

.att-register-copy strong{
    display:block;
    font-size:15px;
}

.att-register-copy small{
    display:block;
    margin-top:3px;
    color:var(--text-muted,#64748b);
    font-size:10px;
}

.att-register-tools{
    display:flex;
    align-items:center;
    justify-content:flex-end;
    gap:10px;
    flex-wrap:wrap;
}

.att-record-count{
    display:inline-flex;
    align-items:center;
    min-height:38px;
    padding:0 11px;
    border:1px solid var(--border-soft,#e7ebf3);
    border-radius:8px;
    color:var(--text-muted,#64748b);
    background:var(--card-bg,#fff);
    font-size:10px;
    font-weight:700;
    white-space:nowrap;
}

.att-register-date{min-width:160px!important}

.att-class-browser{
    padding:12px 16px;
    border-bottom:1px solid var(--border-soft,#e7ebf3);
    background:var(--card-bg,#fff);
}

.att-class-browser-title{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    margin-bottom:10px;
    flex-wrap:wrap;
}

.att-class-browser-title strong{font-size:12px}

.att-class-browser-title small{
    font-size:9px;
    color:var(--text-muted,#64748b);
}

.att-class-tabs{
    display:flex;
    gap:8px;
    overflow-x:auto;
    overflow-y:hidden;
    padding-bottom:3px;
    scrollbar-width:thin;
}

.att-class-tab{
    border:1px solid var(--border-soft,#dce3ef);
    background:var(--card-bg,#fff);
    color:var(--text-main,#1e293b);
    border-radius:9px;
    padding:9px 12px;
    display:inline-flex;
    align-items:center;
    gap:8px;
    white-space:nowrap;
    flex:0 0 auto;
    font-size:11px;
    font-weight:700;
    transition:.18s ease;
}

.att-class-tab:hover{
    border-color:#7c6cf2;
    color:#5b4bd8;
    background:#f7f5ff;
}

.att-class-tab.active{
    color:#fff;
    border-color:transparent;
    background:linear-gradient(135deg,#6d4ce7,#345fe0);
    box-shadow:0 7px 18px rgba(79,70,229,.2);
}

.att-class-count{
    min-width:22px;
    height:22px;
    padding:0 6px;
    border-radius:999px;
    display:inline-grid;
    place-items:center;
    background:rgba(100,116,139,.12);
    font-size:9px;
}

.att-class-tab.active .att-class-count{background:rgba(255,255,255,.2)}

.att-class-empty{
    font-size:11px;
    color:var(--text-muted,#64748b);
    padding:4px 0;
}

.att-table-wrap{
    width:100%;
    max-width:100%;
    overflow-x:auto;
    overflow-y:visible;
    -webkit-overflow-scrolling:touch;
    scrollbar-width:thin;
}

.att-table{
    width:100%;
    min-width:1050px;
    border-collapse:separate;
    border-spacing:0;
}

.att-table th{
    font-size:10px;
    white-space:nowrap;
    position:sticky;
    top:0;
    z-index:2;
    background:var(--card-bg,#fff);
}

.att-table td{
    font-size:11px;
    vertical-align:middle;
}

.att-table th,
.att-table td{padding:11px 10px}

.att-table tbody tr:hover{background:rgba(79,70,229,.025)}

.att-student{
    display:flex;
    align-items:center;
    gap:9px;
    min-width:0;
}

.att-student>div{min-width:0}

.att-avatar{
    width:30px;
    height:30px;
    border-radius:50%;
    display:grid;
    place-items:center;
    color:#fff;
    font-size:11px;
    font-weight:800;
    flex:0 0 auto;
    background:linear-gradient(135deg,#6d4ce7,#345fe0);
}

.att-badge{
    display:inline-flex;
    align-items:center;
    padding:5px 9px;
    border-radius:999px;
    font-size:9px;
    font-weight:800;
    white-space:nowrap;
}

.att-badge.present{color:#16834f;background:#e8f8ef}
.att-badge.absent{color:#dc2626;background:#fff0f1}
.att-badge.leave{color:#b96b00;background:#fff4df}
.att-badge.late{color:#7c3aed;background:#f3e8ff}
.att-badge.holiday{color:#2563eb;background:#eaf2ff}

.att-action{
    width:30px;
    height:30px;
    border:1px solid #d7def1;
    border-radius:7px;
    background:var(--card-bg,#fff);
    color:#4f46e5;
    display:grid;
    place-items:center;
    flex:0 0 auto;
}

.att-action svg{width:13px;height:13px}

.att-empty{
    padding:42px 18px!important;
    text-align:center!important;
    color:var(--text-muted,#64748b);
}

.att-grid{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:12px;
}

.att-grid>div{min-width:0}
.att-grid .full{grid-column:1/-1}

.bulk-list{
    max-height:52vh;
    overflow:auto;
    border:1px solid var(--border-soft,#e7ebf3);
    border-radius:10px;
    scrollbar-width:thin;
}

.bulk-header,
.bulk-row{
    display:grid;
    grid-template-columns:42px minmax(220px,1.5fr) minmax(100px,.5fr) minmax(125px,.6fr) minmax(180px,1fr);
    gap:10px;
    align-items:center;
    padding:10px 12px;
    min-width:760px;
}

.bulk-header{
    position:sticky;
    top:0;
    z-index:2;
    background:var(--card-bg,#fff);
    border-bottom:1px solid var(--border-soft,#e7ebf3);
    font-size:10px;
    font-weight:800;
    text-transform:uppercase;
    color:var(--text-muted,#64748b);
}

.bulk-row{
    border-bottom:1px solid var(--border-soft,#e7ebf3);
}

.bulk-row:last-child{border-bottom:0}

.bulk-status-select{min-width:145px}

.bulk-summary{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:10px;
    margin:14px 0;
}

.bulk-summary-card{
    border:1px solid var(--border-soft,#e7ebf3);
    border-radius:10px;
    padding:11px 13px;
    background:var(--card-bg,#fff);
    min-width:0;
}

.bulk-summary-card small{
    display:block;
    font-size:10px;
    font-weight:700;
    color:var(--text-muted,#64748b);
    margin-bottom:5px;
}

.bulk-summary-card strong{font-size:21px}
.bulk-summary-card.present strong{color:#16834f}
.bulk-summary-card.absent strong{color:#dc2626}
.bulk-summary-card.leave strong{color:#b96b00}

.bulk-register-toolbar{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
    margin-bottom:10px;
    flex-wrap:wrap;
}

.bulk-register-note{
    font-size:11px;
    color:var(--text-muted,#64748b);
}

.bulk-selection-panel{
    display:grid;
    grid-template-columns:minmax(220px,1fr) auto minmax(170px,.55fr);
    gap:10px;
    align-items:end;
    margin-bottom:12px;
    padding:12px;
    border:1px solid var(--border-soft,#e7ebf3);
    border-radius:10px;
    background:var(--card-bg,#fff);
}

.bulk-selection-panel>div{min-width:0}

.bulk-selection-panel .form-label{
    font-size:10px;
    font-weight:700;
    margin-bottom:5px;
}

.bulk-select-all{
    min-height:40px;
    display:flex;
    align-items:center;
    gap:8px;
    padding:0 12px;
    border:1px solid var(--border-soft,#e7ebf3);
    border-radius:8px;
    white-space:nowrap;
    font-size:11px;
    font-weight:700;
}

.bulk-selected-count{
    font-size:10px;
    color:var(--text-muted,#64748b);
    margin-top:5px;
}

.bulk-check{
    width:17px;
    height:17px;
    cursor:pointer;
}

.bulk-row.is-hidden{display:none}
.bulk-row.selected{background:rgba(79,70,229,.045)}
.bulk-load-status{display:none;margin:10px 0}
.bulk-load-status.show{display:block}

#attendanceModal .modal-dialog,
#bulkModal .modal-dialog{
    max-height:calc(100dvh - 32px);
    margin:16px auto;
}

#attendanceModal .modal-dialog{
    width:min(980px,calc(100vw - 32px));
    max-width:980px;
}

#bulkModal .modal-dialog{
    width:min(1200px,calc(100vw - 32px));
    max-width:1200px;
}

#attendanceModal .modal-content,
#bulkModal .modal-content{
    max-height:calc(100dvh - 32px);
    overflow:hidden;
}

#attendanceModal form,
#bulkModal form{
    display:flex;
    flex-direction:column;
    max-height:calc(100dvh - 32px);
}

#attendanceModal .modal-body,
#bulkModal .modal-body{
    overflow-y:auto;
    min-height:0;
}

#attendanceModal .modal-footer,
#bulkModal .modal-footer{
    flex-wrap:wrap;
}

/* Large desktop monitors */
@media(min-width:1600px){
    .att-page{gap:18px}
    .att-stats{gap:16px}
    .att-stat{min-height:122px;padding:20px 22px}
    .att-filters{
        grid-template-columns:minmax(280px,1.5fr) repeat(5,minmax(155px,.72fr));
    }
    .att-table{min-width:100%}
    .att-table th,
    .att-table td{padding:12px}
    .att-grid{grid-template-columns:repeat(4,minmax(0,1fr))}
}

/* Standard desktop and 1366px laptop */
@media(max-width:1399px){
    .att-page .page-actions{max-width:620px}

    .att-filters{
        grid-template-columns:minmax(220px,1.35fr) repeat(3,minmax(145px,.78fr));
    }

    .att-grid{grid-template-columns:repeat(3,minmax(0,1fr))}

    .bulk-selection-panel{
        grid-template-columns:minmax(220px,1fr) auto;
    }
}

/* Small desktop and laptop */
@media(max-width:1199px){
    .att-page .page-heading{
        flex-direction:column;
        align-items:stretch;
    }

    .att-page .page-actions{
        justify-content:flex-start;
        max-width:none;
    }

    .att-stats{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }

    .att-filters{
        grid-template-columns:repeat(3,minmax(0,1fr));
    }

    .att-filters #searchFilter{
        grid-column:span 2;
    }

    .att-grid{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }

    .att-card-head{
        align-items:flex-start;
        flex-wrap:wrap;
    }

    .att-register-tools{
        justify-content:flex-start;
    }

    .bulk-summary{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }
}

/* Tablet */
@media(max-width:767px){
    .att-page{gap:12px}

    .att-page .page-actions{
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        width:100%;
    }

    .att-page .page-actions .btn-ui{
        width:100%;
        justify-content:center;
    }

    .att-stats{
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:10px;
    }

    .att-stat{
        min-height:108px;
        padding:15px;
    }

    .att-filters{
        grid-template-columns:repeat(2,minmax(0,1fr));
        padding:12px;
    }

    .att-filters #searchFilter{
        grid-column:1/-1;
    }

    .att-card-head{
        flex-direction:column;
        align-items:stretch;
    }

    .att-card-head .form-select,
    .att-card-head .form-control{
        width:100%;
    }

    .att-register-tools{
        width:100%;
        display:grid;
        grid-template-columns:1fr 1fr;
    }

    .att-record-count,
    .att-register-date{
        width:100%!important;
    }

    .att-grid{
        grid-template-columns:1fr;
    }

    .att-grid .full{grid-column:auto}

    .bulk-selection-panel{
        grid-template-columns:1fr;
    }

    .bulk-register-toolbar{
        align-items:flex-start;
        flex-direction:column;
    }

    .att-table{min-width:920px}
}

/* Mobile */
@media(max-width:575px){
    .att-page .page-title{font-size:24px}

    .att-page .page-actions{
        grid-template-columns:1fr 1fr;
    }

    .att-page .page-actions .btn-ui:first-child{
        grid-column:1/-1;
    }

    .att-stats,
    .att-filters,
    .bulk-summary{
        grid-template-columns:1fr;
    }

    .att-stat{min-height:102px}

    .att-filters #searchFilter{grid-column:auto}

    .att-register-tools{
        grid-template-columns:1fr;
    }

    .att-class-browser-title{
        align-items:flex-start;
        flex-direction:column;
    }

    .bulk-list{
        overflow-x:auto;
    }

    .bulk-header{display:none}

    .bulk-row{
        min-width:0;
        grid-template-columns:1fr;
        gap:8px;
        padding:12px;
    }

    .bulk-status-select{min-width:0;width:100%}

    #attendanceModal .modal-dialog,
    #bulkModal .modal-dialog{
        width:calc(100vw - 16px);
        margin:8px auto;
        max-height:calc(100dvh - 16px);
    }

    #attendanceModal .modal-content,
    #bulkModal .modal-content,
    #attendanceModal form,
    #bulkModal form{
        max-height:calc(100dvh - 16px);
    }

    #attendanceModal .modal-footer,
    #bulkModal .modal-footer{
        display:grid;
        grid-template-columns:1fr;
    }

    #attendanceModal .modal-footer .btn-ui,
    #bulkModal .modal-footer .btn-ui{
        width:100%;
        justify-content:center;
    }
}
</style>

<!-- Build: 2026-08-12-attendance-year-wise-class-management-filter-v5 -->
<div class="att-page">
    <div class="page-heading">
        <div>
            <h1 class="page-title">Attendance Management</h1>
            <p class="page-subtitle">Track and manage student attendance efficiently</p>
        </div>
        <div class="page-actions">
            <button id="markAttendanceBtn" class="btn-ui btn-primary-ui" type="button"><i data-lucide="plus"></i> Mark Attendance</button>
            <button id="bulkUpdateBtn" class="btn-ui" type="button"><i data-lucide="clipboard-check"></i> Take Attendance</button>
            <a id="exportLink" class="btn-ui"><i data-lucide="download"></i> Export Report</a>
            <button id="filterBtn" class="btn-ui" type="button"><i data-lucide="list-filter"></i> Filters</button>
        </div>
    </div>

    <div id="attendanceMessage" class="alert att-message"></div>

    <section class="att-stats">
        <article class="att-stat green"><span class="att-stat-icon"><i data-lucide="user-round-check"></i></span><div><small>Present Today</small><strong id="presentToday">0</strong><div class="trend" id="presentPercent">0% of total students</div></div></article>
        <article class="att-stat pink"><span class="att-stat-icon"><i data-lucide="user-round-x"></i></span><div><small>Absent Today</small><strong id="absentToday">0</strong><div class="trend" id="absentPercent">0% of total students</div></div></article>
        <article class="att-stat orange"><span class="att-stat-icon"><i data-lucide="calendar-clock"></i></span><div><small>Leave Today</small><strong id="leaveToday">0</strong><div class="trend" id="leavePercent">0% of total students</div></div></article>
        <article class="att-stat blue"><span class="att-stat-icon"><i data-lucide="chart-pie"></i></span><div><small>Attendance Percentage</small><strong id="attendancePercent">0%</strong><div class="trend">Based on marked attendance</div></div></article>
    </section>

    <section id="attendanceFilters" class="ui-card att-filters">
        <input id="searchFilter" class="form-control" placeholder="Search student, admission no. or mobile...">
        <select id="yearFilter" class="form-select"><option value="all">All Academic Years</option></select>
        <select id="classFilter" class="form-select"><option value="all">All Classes</option></select>
        <select id="sectionFilter" class="form-select"><option value="all">All Sections</option></select>
        <select id="statusFilter" class="form-select"><option value="all">All Statuses</option></select>
        <button id="resetFiltersBtn" class="btn-ui" type="button"><i data-lucide="rotate-ccw"></i> Reset</button>
    </section>

    <section class="att-layout">
        <section class="ui-card att-card att-register-card">
            <div class="att-card-head att-register-head">
                <div class="att-register-title">
                    <span class="att-register-icon"><i data-lucide="clipboard-list"></i></span>
                    <div class="att-register-copy">
                        <strong>Daily Attendance Register</strong>
                        <small>Review and manage attendance records for the selected date.</small>
                    </div>
                </div>
                <div class="att-register-tools">
                    <span id="attendanceRecordCount" class="att-record-count">0 records</span>
                    <input id="attendanceDate" class="form-control form-control-sm att-register-date" type="date">
                </div>
            </div>
            <div class="att-class-browser">
                <div class="att-class-browser-title">
                    <strong>Classes</strong>
                    <small>Open a class to view only its students.</small>
                </div>
                <div id="attendanceClassTabs" class="att-class-tabs">
                    <span class="att-class-empty">Loading classes...</span>
                </div>
            </div>
            <div class="att-table-wrap">
                <table class="data-table att-table">
                    <thead><tr><th>Date</th><th>Student</th><th>Class</th><th>Section</th><th>Status</th><th>Check-in</th><th>Remarks</th><th>Action</th></tr></thead>
                    <tbody id="attendanceBody"><tr><td colspan="8" class="att-empty">Loading...</td></tr></tbody>
                </table>
            </div>
        </section>
    </section>
</div>

<div class="modal fade" id="attendanceModal" tabindex="-1">
<div class="modal-dialog modal-xl modal-dialog-centered">
<div class="modal-content">
<form id="attendanceForm" novalidate>
<div class="modal-header"><div><h5 id="attendanceModalTitle" class="modal-title">Mark Attendance</h5><small class="text-muted">Student attendance details</small></div><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
<input id="attendanceId" type="hidden">
<div class="att-grid">
<div><label class="form-label">Date *</label><input id="formDate" class="form-control" type="date" required></div>
<div><label class="form-label">Academic Year *</label><select id="formYear" class="form-select" required></select></div>
<div><label class="form-label">Class *</label><select id="formClass" class="form-select" required></select></div>
<div><label class="form-label">Section</label><select id="formSection" class="form-select"><option value="">All Sections</option></select></div>
<div class="full"><label class="form-label">Student *</label><select id="formStudent" class="form-select" required></select></div>
<div><label class="form-label">Status *</label><select id="formStatus" class="form-select"><option value="present">Present</option><option value="absent">Absent</option><option value="leave">Leave</option><option value="late">Late</option><option value="half_day">Half Day</option><option value="on_duty">On Duty</option><option value="holiday">Holiday</option></select></div>
<div><label class="form-label">Check-in Time</label><input id="formCheckIn" class="form-control" type="time"></div>
<div><label class="form-label">Check-out Time</label><input id="formCheckOut" class="form-control" type="time"></div>
<div><label class="form-label">Attendance Source</label><select id="formSource" class="form-select"><option value="manual">Manual</option><option value="biometric">Biometric</option><option value="mobile">Mobile</option><option value="import">Import</option></select></div>
<div class="full"><label class="form-label">Remarks</label><textarea id="formRemarks" class="form-control" rows="3"></textarea></div>
</div>
</div>
<div class="modal-footer"><button class="btn-ui" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn-ui btn-primary-ui" type="submit"><i data-lucide="save"></i> Update Selected</button></div>
</form>
</div>
</div>
</div>

<div class="modal fade" id="bulkModal" tabindex="-1">
<div class="modal-dialog modal-xl modal-dialog-centered">
<div class="modal-content">
<form id="bulkForm" novalidate>
<div class="modal-header"><div><h5 class="modal-title">Take Student Attendance</h5><small class="text-muted">Load students, select the required students and apply one attendance status</small></div><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
<div class="att-grid">
<div><label class="form-label">Academic Year *</label><select id="bulkYear" class="form-select" required></select></div>
<div><label class="form-label">Class *</label><select id="bulkClass" class="form-select" required><option value="">Select Class</option></select></div>
<div><label class="form-label">Section *</label><select id="bulkSection" class="form-select" required><option value="">Select Section</option></select></div>
<div><label class="form-label">Date *</label><input id="bulkDate" class="form-control" type="date" required></div>
<div class="full d-flex justify-content-end"><button id="bulkLoadBtn" class="btn-ui btn-primary-ui" type="button"><i data-lucide="users"></i> Load Students</button></div>
</div>

<div id="bulkLoadStatus" class="alert bulk-load-status"></div>

<section class="bulk-summary">
<div class="bulk-summary-card"><small>Total Students</small><strong id="bulkTotalCount">0</strong></div>
<div class="bulk-summary-card present"><small>Present</small><strong id="bulkPresentCount">0</strong></div>
<div class="bulk-summary-card absent"><small>Absent</small><strong id="bulkAbsentCount">0</strong></div>
<div class="bulk-summary-card leave"><small>Leave</small><strong id="bulkLeaveCount">0</strong></div>
</section>

<div class="bulk-register-toolbar">
<div class="bulk-register-note" id="bulkRegisterNote">Select Academic Year, Class, Section and Date, then click Load Students.</div>
</div>

<div id="bulkSelectionPanel" class="bulk-selection-panel" style="display:none">
<div>
<label class="form-label" for="bulkStudentSearch">Search Students</label>
<input id="bulkStudentSearch" class="form-control" placeholder="Search name, admission number or roll number...">
<div id="bulkSelectedCount" class="bulk-selected-count">0 students selected · all others stay Present</div>
</div>
<label class="bulk-select-all"><input id="bulkSelectAll" class="bulk-check" type="checkbox"> Select All Visible</label>
<div>
<label class="form-label" for="bulkApplyStatus">Apply Status to Selected *</label>
<select id="bulkApplyStatus" class="form-select">
<option value="present">Present</option>
<option value="absent">Absent</option>
<option value="half_day">Half Day</option>
<option value="late">Late</option>
<option value="leave">Leave</option>
<option value="on_duty">On Duty</option>
<option value="holiday">Holiday</option>
</select>
</div>
</div>

<div class="bulk-list" id="bulkStudents">
<div class="att-empty">No students loaded.</div>
</div>
</div>
<div class="modal-footer">
<button class="btn-ui" type="button" data-bs-dismiss="modal">Cancel</button>
<button id="bulkSaveBtn" class="btn-ui btn-primary-ui" type="submit" disabled><i data-lucide="save"></i> Save Attendance</button>
</div>
</form>
</div>
</div>
</div>

<script>
(function(){
'use strict';
const projectBase=window.location.pathname.replace(
 /\/school\/attendance\.php(?:\/.*)?$/i,
 ''
);
const apiCandidates=[
 window.location.origin+projectBase+'/api/attendance.php',
 new URL('./attendance.php?api=1',window.location.href).href
];
let apiUrl=apiCandidates[0];
let csrfToken=<?=json_encode($csrfToken)?>;
let meta={},permissions={},records=[],students=[],activeAttendanceClass='';
let bulkLoaded=false,bulkExisting=false,bulkRows=[];
const $=id=>document.getElementById(id);
function refreshLucideIcons(){
 document.querySelectorAll('[data-lucide]').forEach(icon=>{
  const name=(icon.getAttribute('data-lucide')||'').trim();

  if(name==='fas fa-school'||name==='fa-school'){
   icon.setAttribute('data-lucide','school');
  }else if(
   name.startsWith('fas ')
   ||name.startsWith('far ')
   ||name.startsWith('fab ')
  ){
   icon.removeAttribute('data-lucide');
  }
 });

 window.lucide?.createIcons();
}

const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));

async function request(action,data={},method='GET'){
 let lastError=null;

 for(const candidate of apiCandidates){
  let response;

  try{
   if(method==='GET'){
    const url=new URL(candidate,window.location.origin);
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
    response=await fetch(candidate,{
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

   if(response.status===404){
    lastError=new Error(
     `Attendance API not found: ${candidate}`
    );
    continue;
   }

   const text=await response.text();
   let result;

   try{
    result=JSON.parse(text);
   }catch{
    throw new Error(
     `Attendance API returned HTTP ${response.status}. Invalid server response.`
    );
   }

   if(!response.ok||!result.success){
    throw new Error(result.message||'Request failed.');
   }

   apiUrl=candidate;
   return result;
  }catch(error){
   lastError=error;

   if(response&&response.status!==404){
    throw error;
   }
  }
 }

 throw lastError||new Error(
  'Attendance API file was not found.'
 );
}
function showMessage(
 text,
 success=false,
 title=''
){
 const message=
  String(text||'').trim();

 if(!message){
  return;
 }

 const type=
  success
   ?'success'
   :'error';

 const toastTitle=
  String(
   title
   ||(
    success
     ?'Success'
     :'Action Failed'
   )
  );

 /*
  * Exact API from includes/common-toast.php.
  *
  * showToast is checked first because layout-start.php may provide
  * a temporary pre-load queue before the toast JS finishes loading.
  */
 if(
  typeof window.showToast
  ==='function'
 ){
  window.showToast(
   type,
   message,
   toastTitle
  );

  return;
 }

 /*
  * Same common toast also exposes:
  * SchoolToast.show(type, message, title)
  */
 if(
  window.SchoolToast
  &&typeof window.SchoolToast.show
   ==='function'
 ){
  window.SchoolToast.show(
   type,
   message,
   toastTitle
  );

  return;
 }

 /*
  * Legacy compatibility only.
  */
 if(
  typeof window.schoolToast
  ==='function'
 ){
  window.schoolToast(
   type,
   message,
   toastTitle
  );

  return;
 }

 /*
  * Existing inline alert is retained only as a final fallback.
  */
 const box=
  $('attendanceMessage');

 if(!box){
  return;
 }

 box.className=
  'alert att-message show '
  +(
   success
    ?'alert-success'
    :'alert-danger'
  );

 box.textContent=
  message;

 window.clearTimeout(
  box._attendanceTimer
 );

 box._attendanceTimer=
  window.setTimeout(
   ()=>{
    box.className=
     'alert att-message';
   },
   5000
  );
}

function fillSelect(id,rows,valueKey,labelKey,keepFirst=false){const el=$(id);const first=keepFirst?(el.options[0]?.outerHTML||''):'';el.innerHTML=first+rows.map(r=>`<option value="${esc(r[valueKey])}">${esc(r[labelKey])}</option>`).join('')}
function filters(){return{date:$('attendanceDate').value,search:$('searchFilter').value,academic_year_id:$('yearFilter').value,class_id:$('classFilter').value,section_id:$('sectionFilter').value,status:$('statusFilter').value}}
function statusBadge(status){return `<span class="att-badge ${esc(status)}">${esc(status)}</span>`}
function renderStats(stats={}){
 const total=Number(stats.total_students||0),present=Number(stats.present||0),absent=Number(stats.absent||0),leave=Number(stats.leave_count||0);
 $('presentToday').textContent=present.toLocaleString();
 $('absentToday').textContent=absent.toLocaleString();
 $('leaveToday').textContent=leave.toLocaleString();
 $('presentPercent').textContent=(total?((present/total)*100).toFixed(1):0)+'% of total students';
 $('absentPercent').textContent=(total?((absent/total)*100).toFixed(1):0)+'% of total students';
 $('leavePercent').textContent=(total?((leave/total)*100).toFixed(1):0)+'% of total students';
 $('attendancePercent').textContent=(Number(stats.attendance_percentage||0)).toFixed(1)+'%';
}
function attendanceClassKey(row){
 return String(row.class_id||row.class_name||'unassigned');
}
function attendanceClassGroups(){
 const groups=new Map();

 records.forEach(row=>{
  const key=attendanceClassKey(row);

  if(!groups.has(key)){
   groups.set(key,{
    key,
    name:String(row.class_name||'Unassigned Class'),
    count:0
   });
  }

  groups.get(key).count++;
 });

 return [...groups.values()].sort((a,b)=>{
  const aNumber=Number((a.name.match(/\d+/)||[])[0]||999999);
  const bNumber=Number((b.name.match(/\d+/)||[])[0]||999999);

  return aNumber-bNumber||a.name.localeCompare(b.name,undefined,{
   numeric:true,
   sensitivity:'base'
  });
 });
}
function renderAttendanceClassTabs(){
 const box=$('attendanceClassTabs');
 const groups=attendanceClassGroups();

 if(!box)return;

 if(groups.length===0){
  activeAttendanceClass='';
  box.innerHTML='<span class="att-class-empty">No classes found for the selected date and filters.</span>';
  return;
 }

 if(!groups.some(group=>group.key===activeAttendanceClass)){
  activeAttendanceClass=groups[0].key;
 }

 box.innerHTML=groups.map(group=>`
  <button
   class="att-class-tab ${group.key===activeAttendanceClass?'active':''}"
   data-class-key="${esc(group.key)}"
   type="button"
  >
   <span>${esc(group.name)}</span>
   <span class="att-class-count">${group.count}</span>
  </button>
 `).join('');

 box.querySelectorAll('.att-class-tab').forEach(button=>{
  button.onclick=()=>{
   activeAttendanceClass=String(button.dataset.classKey||'');
   renderRecords();
  };
 });
}
function renderRecords(){
 renderAttendanceClassTabs();

 const visibleRecords=activeAttendanceClass
  ?records.filter(row=>attendanceClassKey(row)===activeAttendanceClass)
  :[];

 $('attendanceBody').innerHTML=visibleRecords.map(row=>`<tr>
  <td>${esc(row.attendance_date)}</td>
  <td><div class="att-student"><span class="att-avatar">${esc((row.student_name||'?').charAt(0).toUpperCase())}</span><strong>${esc(row.student_name)}</strong></div></td>
  <td>${esc(row.class_name||'-')}</td>
  <td>${esc(row.section_name||'-')}</td>
  <td>${statusBadge(row.status)}</td>
  <td>${esc(row.check_in_time||'-')}</td>
  <td>${esc(row.remarks||'-')}</td>
  <td>${permissions.edit?`<button class="att-action js-edit" data-id="${row.id}" type="button"><i data-lucide="pencil"></i></button>`:''}</td>
 </tr>`).join('')||'<tr><td colspan="8" class="att-empty">No attendance records found in this class.</td></tr>';

 document.querySelectorAll('.js-edit').forEach(button=>{
  button.onclick=()=>openForm(
   records.find(row=>Number(row.id)===Number(button.dataset.id))
  );
 });

 const countLabel=$('attendanceRecordCount');

 if(countLabel){
  const selectedGroup=attendanceClassGroups().find(
   group=>group.key===activeAttendanceClass
  );
  const className=selectedGroup?.name||'Selected Class';
  countLabel.textContent=`${className}: ${visibleRecords.length.toLocaleString()} record${visibleRecords.length===1?'':'s'}`;
 }

 refreshLucideIcons();
}
function openForm(row=null){
 $('attendanceForm').reset();
 $('attendanceId').value=row?.id||'';
 $('attendanceModalTitle').textContent=row?'Edit Attendance':'Mark Attendance';
 $('formDate').value=row?.attendance_date||$('attendanceDate').value;

 const defaultYear=Number(
  row?.academic_year_id
  ||meta.current_academic_year_id
  ||meta.academic_years?.[0]?.id
  ||0
 );
 $('formYear').value=defaultYear?String(defaultYear):'';
 refreshFormClasses(row?.class_id||'');
 refreshFormSections(row?.section_id||'');
 refreshFormStudents(row?.student_id||'');

 $('formStatus').value=row?.status||'present';
 $('formCheckIn').value=row?.check_in_time||'';
 $('formCheckOut').value=row?.check_out_time||'';
 $('formSource').value=row?.source||'manual';
 $('formRemarks').value=row?.remarks||'';
 bootstrap.Modal.getOrCreateInstance($('attendanceModal')).show();
}
function attendanceClassesForYear(yearId){
 const normalized=String(yearId??'').trim();
 return (meta.classes||[]).filter(row=>
  normalized===''||normalized==='all'
   ||Number(row.academic_year_id)===Number(normalized)
 );
}
function attendanceSectionsForClass(yearId,classId){
 const normalizedYear=String(yearId??'').trim();
 const normalizedClass=String(classId??'').trim();
 return (meta.sections||[]).filter(row=>
  (normalizedYear===''||normalizedYear==='all'
   ||Number(row.academic_year_id)===Number(normalizedYear))
  &&(normalizedClass===''||normalizedClass==='all'
   ||Number(row.class_id)===Number(normalizedClass))
 );
}
function attendanceStudentsForSelection(yearId,classId,sectionId){
 const normalizedYear=String(yearId??'').trim();
 const normalizedClass=String(classId??'').trim();
 const normalizedSection=String(sectionId??'').trim();

 return (students||[]).filter(row=>
  (normalizedYear===''||normalizedYear==='all'
   ||Number(row.academic_year_id)===Number(normalizedYear))
  &&(normalizedClass===''||normalizedClass==='all'
   ||Number(row.class_id)===Number(normalizedClass))
  &&(normalizedSection===''||normalizedSection==='all'
   ||Number(row.section_id)===Number(normalizedSection))
 );
}
function refreshBulkClasses(selectedValue=''){
 const yearId=$('bulkYear').value;
 const rows=attendanceClassesForYear(yearId);
 fillSelect('bulkClass',rows,'id','class_name',true);
 $('bulkClass').value=String(selectedValue||'');
 refreshBulkSections();
}
function refreshBulkSections(selectedValue=''){
 const yearId=$('bulkYear').value;
 const classId=$('bulkClass').value;
 const rows=attendanceSectionsForClass(yearId,classId);
 fillSelect('bulkSection',rows,'id','section_name',true);
 $('bulkSection').value=String(selectedValue||'');
}
function refreshFilterClasses(selectedValue='all'){
 const yearId=$('yearFilter').value;
 const rows=attendanceClassesForYear(yearId);
 fillSelect('classFilter',rows,'id','class_name',true);

 const allowed=rows.some(row=>String(row.id)===String(selectedValue));
 $('classFilter').value=allowed?String(selectedValue):'all';
 refreshFilterSections();
}
function refreshFilterSections(selectedValue='all'){
 const yearId=$('yearFilter').value;
 const classId=$('classFilter').value;
 const rows=attendanceSectionsForClass(yearId,classId);
 fillSelect('sectionFilter',rows,'id','section_name',true);

 const allowed=rows.some(row=>String(row.id)===String(selectedValue));
 $('sectionFilter').value=allowed?String(selectedValue):'all';
}
function refreshFormClasses(selectedValue=''){
 const yearId=$('formYear').value;
 const rows=attendanceClassesForYear(yearId);
 const el=$('formClass');
 el.innerHTML='<option value="">Select Class</option>'
  +rows.map(row=>`<option value="${esc(row.id)}">${esc(row.class_name)}</option>`).join('');

 const allowed=rows.some(row=>String(row.id)===String(selectedValue));
 el.value=allowed?String(selectedValue):'';
 refreshFormSections();
}
function refreshFormSections(selectedValue=''){
 const yearId=$('formYear').value;
 const classId=$('formClass').value;
 const rows=attendanceSectionsForClass(yearId,classId);
 const el=$('formSection');
 el.innerHTML='<option value="">Select Section</option>'
  +rows.map(row=>`<option value="${esc(row.id)}">${esc(row.section_name)}</option>`).join('');

 const allowed=rows.some(row=>String(row.id)===String(selectedValue));
 el.value=allowed?String(selectedValue):'';
 refreshFormStudents();
}
function refreshFormStudents(selectedValue=''){
 const rows=attendanceStudentsForSelection(
  $('formYear').value,
  $('formClass').value,
  $('formSection').value
 );
 const el=$('formStudent');
 el.innerHTML='<option value="">Select Student</option>'
  +rows.map(row=>`<option value="${esc(row.id)}">${esc(row.student_name)}</option>`).join('');

 const allowed=rows.some(row=>String(row.id)===String(selectedValue));
 el.value=allowed?String(selectedValue):'';
}
function setBulkStatus(messageText,type='info'){
 const box=$('bulkLoadStatus');
 box.className='alert bulk-load-status show alert-'+type;
 box.textContent=messageText;
}
function clearBulkStatus(){
 const box=$('bulkLoadStatus');
 box.className='alert bulk-load-status';
 box.textContent='';
}
function resetBulkRegister(messageText='Select Academic Year, Class, Section and Date, then click Load Students.'){
 bulkLoaded=false;
 bulkExisting=false;
 bulkRows=[];
 $('bulkStudents').innerHTML='<div class="att-empty">No students loaded.</div>';
 $('bulkTotalCount').textContent='0';
 $('bulkPresentCount').textContent='0';
 $('bulkAbsentCount').textContent='0';
 $('bulkLeaveCount').textContent='0';
 $('bulkRegisterNote').textContent=messageText;
 $('bulkSaveBtn').disabled=true;
 $('bulkSelectionPanel').style.display='none';
 $('bulkStudentSearch').value='';
 $('bulkSelectAll').checked=false;
 $('bulkSelectAll').indeterminate=false;
 $('bulkApplyStatus').value='present';
 $('bulkSelectedCount').textContent='0 students selected';
 $('bulkSaveBtn').innerHTML='<i data-lucide="save"></i> Save Attendance';
 clearBulkStatus();
 refreshLucideIcons();
}
function normalizedBulkStatus(status){
 const allowed=['present','absent','half_day','late','leave','on_duty','holiday'];
 return allowed.includes(status)?status:'present';
}
function bulkStatusLabel(status){
 return ({
  present:'Present',
  absent:'Absent',
  half_day:'Half Day',
  late:'Late',
  leave:'Leave',
  on_duty:'On Duty',
  holiday:'Holiday'
 })[status]||status;
}
function setBulkRowStatus(row,status){
 const normalized=
  normalizedBulkStatus(status);

 row.dataset.currentStatus=
  normalized;

 const badge=
  row.querySelector('.att-badge');

 if(badge){
  badge.className=
   `att-badge ${normalized}`;

  badge.textContent=
   bulkStatusLabel(normalized);
 }

 return normalized;
}

function applyStatusToSelectedRows(){
 const selected=
  selectedBulkRows();

 if(!selected.length){
  return;
 }

 const status=
  normalizedBulkStatus(
   $('bulkApplyStatus').value
  );

 selected.forEach(row=>{
  setBulkRowStatus(
   row,
   status
  );
 });

 updateBulkCounts();
 updateBulkSelectionState();
}
function updateBulkCounts(){
 const rows=[...document.querySelectorAll('.bulk-row[data-student]')];
 const counts={total:rows.length,present:0,absent:0,leave:0};

 rows.forEach(row=>{
  const status=normalizedBulkStatus(row.dataset.currentStatus||'present');

  if(status==='absent'){
   counts.absent++;
  }else if(status==='leave'){
   counts.leave++;
  }else{
   counts.present++;
  }
 });

 $('bulkTotalCount').textContent=counts.total.toLocaleString();
 $('bulkPresentCount').textContent=counts.present.toLocaleString();
 $('bulkAbsentCount').textContent=counts.absent.toLocaleString();
 $('bulkLeaveCount').textContent=counts.leave.toLocaleString();
}
function visibleBulkRows(){
 return [...document.querySelectorAll('.bulk-row[data-student]')]
  .filter(row=>!row.classList.contains('is-hidden'));
}
function selectedBulkRows(){
 return [...document.querySelectorAll('.bulk-row[data-student]')]
  .filter(row=>row.querySelector('.bulk-student-check')?.checked);
}
function updateBulkSelectionState(){
 const visible=visibleBulkRows();
 const visibleChecked=visible.filter(
  row=>row.querySelector('.bulk-student-check')?.checked
 );
 const selected=selectedBulkRows();

 $('bulkSelectedCount').textContent=
  `${selected.length.toLocaleString()} student${selected.length===1?'':'s'} selected · all unselected students remain Present`;

 $('bulkSelectAll').checked=
  visible.length>0&&visibleChecked.length===visible.length;
 $('bulkSelectAll').indeterminate=
  visibleChecked.length>0&&visibleChecked.length<visible.length;

 document.querySelectorAll('.bulk-row[data-student]').forEach(row=>{
  row.classList.toggle(
   'selected',
   Boolean(row.querySelector('.bulk-student-check')?.checked)
  );
 });

 $('bulkSaveBtn').disabled=
  !bulkLoaded
  ||document.querySelectorAll(
   '.bulk-row[data-student]'
  ).length===0;
}
function filterBulkStudents(){
 const query=$('bulkStudentSearch').value.trim().toLowerCase();

 document.querySelectorAll('.bulk-row[data-student]').forEach(row=>{
  const searchable=String(row.dataset.search||'').toLowerCase();
  row.classList.toggle('is-hidden',query!==''&&!searchable.includes(query));
 });

 updateBulkSelectionState();
}
function renderBulkStudents(rows=[]){
 bulkRows=rows;

 $('bulkStudents').innerHTML=rows.length
  ?`<div class="bulk-header"><span></span><span>Student</span><span>Roll No</span><span>Current Status</span><span>Remarks</span></div>`+
   rows.map(row=>{
    const saved=normalizedBulkStatus(String(row.status||'present'));
    const searchText=[
     row.student_name||'',
     row.admission_number||'',
     row.roll_no||''
    ].join(' ').toLowerCase();

    return `<div class="bulk-row" data-student="${row.id}" data-current-status="${esc(saved)}" data-search="${esc(searchText)}">
      <input class="bulk-check bulk-student-check" type="checkbox" aria-label="Select ${esc(row.student_name||'student')}">
      <div class="att-student"><span class="att-avatar">${esc((row.student_name||'?').charAt(0).toUpperCase())}</span><div><strong>${esc(row.student_name)}</strong><small class="d-block text-muted">${esc(row.admission_number||'')}</small></div></div>
      <span>${esc(row.roll_no||'-')}</span>
      <span class="att-badge ${esc(saved)}">${esc(bulkStatusLabel(saved))}</span>
      <input class="form-control form-control-sm bulk-remark" maxlength="255" placeholder="Optional remarks" value="${esc(row.remarks||'')}">
     </div>`;
   }).join('')
  :'<div class="att-empty">No active students found for the selected class and section.</div>';

 $('bulkSelectionPanel').style.display=rows.length?'grid':'none';
 $('bulkStudentSearch').value='';
 $('bulkSelectAll').checked=false;
 $('bulkSelectAll').indeterminate=false;

 document.querySelectorAll(
  '.bulk-student-check'
 ).forEach(check=>{
  check.addEventListener(
   'change',
   ()=>{
    const row=
     check.closest(
      '.bulk-row[data-student]'
     );

    if(
     check.checked
     &&row
     &&$('bulkApplyStatus').value!=='present'
    ){
     setBulkRowStatus(
      row,
      $('bulkApplyStatus').value
     );

     updateBulkCounts();
    }

    updateBulkSelectionState();
   }
  );
 });

 updateBulkCounts();
 updateBulkSelectionState();
 refreshLucideIcons();
}
async function loadBulkStudents(){
 const yearId=Number($('bulkYear').value||0);
 const classId=Number($('bulkClass').value||0);
 const sectionId=Number($('bulkSection').value||0);
 const attendanceDate=$('bulkDate').value;

 if(!yearId||!classId||!sectionId||!attendanceDate){
  setBulkStatus('Select Academic Year, Class, Section and Date first.','warning');
  return;
 }

 $('bulkLoadBtn').disabled=true;
 $('bulkLoadBtn').innerHTML='<span class="spinner-border spinner-border-sm"></span> Loading...';

 try{
  const result=await request('load_students',{
   academic_year_id:yearId,
   class_id:classId,
   section_id:sectionId,
   attendance_date:attendanceDate
  });

  bulkLoaded=true;
  bulkExisting=Boolean(result.data.attendance_exists);
  renderBulkStudents(result.data.students||[]);

  const total=Number(result.data.counts?.total||0);
  $('bulkSaveBtn').disabled=total===0;
  $('bulkSaveBtn').innerHTML=bulkExisting
   ?'<i data-lucide="save"></i> Update Attendance'
   :'<i data-lucide="save"></i> Save Attendance';
  $('bulkRegisterNote').textContent=bulkExisting
   ?`Existing attendance loaded for ${total} student${total===1?'':'s'}. Existing statuses are preserved; change only the students you need.`
   :`${total} student${total===1?'':'s'} loaded. All students are Present by default. Select only exceptions such as Absent, Leave, Late or Half Day.`;

  setBulkStatus(
   bulkExisting
    ?'Existing attendance values loaded. Select only the students whose status you want to change.'
    :'All students are Present by default. Select students only when you need to change their status.',
   bulkExisting?'warning':'success'
  );
 }catch(error){
  resetBulkRegister();
  setBulkStatus(
   error.message,
   'danger'
  );

  showMessage(
   error.message,
   false,
   'Attendance Error'
  );
 }finally{
  $('bulkLoadBtn').disabled=false;
  $('bulkLoadBtn').innerHTML='<i data-lucide="users"></i> Load Students';
  refreshLucideIcons();
 }
}
function openBulkAttendance(){
 const defaultYear=Number(meta.current_academic_year_id||meta.academic_years?.[0]?.id||0);
 $('bulkForm').reset();
 $('bulkYear').value=defaultYear?String(defaultYear):'';
 refreshBulkClasses();
 $('bulkDate').value=$('attendanceDate').value||new Date().toISOString().slice(0,10);
 resetBulkRegister();
 bootstrap.Modal.getOrCreateInstance($('bulkModal')).show();
}

async function loadMeta(){
 const r=await request('meta');
 csrfToken=r.data.csrf_token||csrfToken;
 meta=r.data.meta||{};
 permissions=r.data.permissions||{};
 students=meta.students||[];

 fillSelect('yearFilter',meta.academic_years||[],'id','year_name',true);
 fillSelect('statusFilter',(meta.statuses||[]).map(v=>({id:v,name:v})),'id','name',true);
 refreshFilterClasses();

 fillSelect('formYear',meta.academic_years||[],'id','year_name');
 const formDefaultYear=Number(meta.current_academic_year_id||meta.academic_years?.[0]?.id||0);
 $('formYear').value=formDefaultYear?String(formDefaultYear):'';
 refreshFormClasses();

 fillSelect('bulkYear',meta.academic_years||[],'id','year_name');
 const defaultYear=Number(meta.current_academic_year_id||meta.academic_years?.[0]?.id||0);
 $('bulkYear').value=defaultYear?String(defaultYear):'';
 refreshBulkClasses();

 $('markAttendanceBtn').style.display=permissions.add?'':'none';
 $('bulkUpdateBtn').style.display=(permissions.edit||permissions.add)?'':'none';
}
async function load(){
 const r=await request('list',filters());
 records=r.data.records||[];
 renderStats(r.data.stats||{});
 renderRecords();
 $('exportLink').href=apiUrl+'?action=export&'+new URLSearchParams(filters());
}
$('attendanceForm').onsubmit=async e=>{
 e.preventDefault();
 if(!e.currentTarget.checkValidity()){e.currentTarget.classList.add('was-validated');return}
 const student=$('formStudent'),year=$('formYear'),cls=$('formClass'),sec=$('formSection');
 try{
  const r=await request('save',{id:Number($('attendanceId').value||0),attendance_date:$('formDate').value,academic_year_id:Number(year.value),academic_year_name:year.options[year.selectedIndex]?.text||'',class_id:Number(cls.value),class_name:cls.options[cls.selectedIndex]?.text||'',section_id:Number(sec.value||0),section_name:sec.options[sec.selectedIndex]?.text||'',student_id:Number(student.value),student_name:student.options[student.selectedIndex]?.text||'',status:$('formStatus').value,check_in_time:$('formCheckIn').value,check_out_time:$('formCheckOut').value,source:$('formSource').value,remarks:$('formRemarks').value},'POST');
  bootstrap.Modal.getInstance(
   $('attendanceModal')
  )?.hide();

  showMessage(
   r.message,
   true,
   'Attendance Saved'
  );

  await load();
 }catch(err){
  showMessage(
   err.message,
   false,
   'Attendance Error'
  );
 }
};
$('bulkForm').onsubmit=async e=>{
 e.preventDefault();

 if(!bulkLoaded){
  setBulkStatus('Click Load Students before saving attendance.','warning');
  return;
 }

 const allRows=[
  ...document.querySelectorAll(
   '.bulk-row[data-student]'
  )
 ];

 const entries=
  allRows.map(row=>({
   student_id:
    Number(
     row.dataset.student
    ),
   status:
    normalizedBulkStatus(
     row.dataset.currentStatus
     ||'present'
    ),
   remarks:
    row.querySelector(
     '.bulk-remark'
    )?.value
    ||''
  }));

 if(entries.length===0){
  setBulkStatus(
   'No students are loaded for attendance.',
   'warning'
  );
  return;
 }

 const saveButton=$('bulkSaveBtn');
 saveButton.disabled=true;
 saveButton.innerHTML='<span class="spinner-border spinner-border-sm"></span> Saving...';

 try{
  const year=$('bulkYear'),cls=$('bulkClass'),sec=$('bulkSection');
  const result=await request('bulk_save',{
   attendance_date:$('bulkDate').value,
   academic_year_id:Number(year.value),
   academic_year_name:year.options[year.selectedIndex]?.text||'',
   class_id:Number(cls.value),
   class_name:cls.options[cls.selectedIndex]?.text||'',
   section_id:Number(sec.value),
   section_name:sec.options[sec.selectedIndex]?.text||'',
   entries
  },'POST');

  bulkExisting=true;

  document.querySelectorAll(
   '.bulk-student-check'
  ).forEach(checkbox=>{
   checkbox.checked=false;
  });

  updateBulkCounts();
  updateBulkSelectionState();

  showMessage(
   result.message,
   true,
   'Attendance Saved'
  );

  setBulkStatus(
   `${result.message} ${entries.length} student${entries.length===1?'':'s'} saved. Unchanged students remain Present.`,
   'success'
  );

  $('bulkSaveBtn').innerHTML=
   '<i data-lucide="save"></i> Update Attendance';

  await load();
 }catch(error){
  setBulkStatus(
   error.message,
   'danger'
  );

  showMessage(
   error.message,
   false,
   'Attendance Error'
  );
 }finally{
  saveButton.disabled=false;
  refreshLucideIcons();
 }
};
$('markAttendanceBtn').onclick=()=>openForm();
$('bulkUpdateBtn').onclick=openBulkAttendance;
$('bulkLoadBtn').onclick=loadBulkStudents;
$('bulkStudentSearch').addEventListener('input',filterBulkStudents);
$('bulkSelectAll').addEventListener('change',()=>{
 visibleBulkRows().forEach(row=>{
  const checkbox=row.querySelector('.bulk-student-check');
  if(checkbox)checkbox.checked=$('bulkSelectAll').checked;
 });
 updateBulkSelectionState();
});
$('bulkApplyStatus').addEventListener(
 'change',
 ()=>{
  applyStatusToSelectedRows();
 }
);
$('filterBtn').onclick=()=>$('attendanceFilters').classList.toggle('show');
$('resetFiltersBtn').onclick=()=>{
 $('searchFilter').value='';
 $('yearFilter').value='all';
 $('statusFilter').value='all';
 refreshFilterClasses();
 load();
};
$('bulkYear').onchange=()=>{
 refreshBulkClasses();
 resetBulkRegister('Class list updated. Select Class and Section, then click Load Students.');
};
$('bulkClass').onchange=()=>{
 refreshBulkSections();
 resetBulkRegister('Section list updated. Select Section, then click Load Students.');
};
$('bulkSection').onchange=()=>resetBulkRegister();
$('bulkDate').onchange=()=>resetBulkRegister();

$('yearFilter').addEventListener('change',()=>{
 refreshFilterClasses();
 load();
});
$('classFilter').addEventListener('change',()=>{
 refreshFilterSections();
 load();
});
$('sectionFilter').addEventListener('change',load);
$('statusFilter').addEventListener('change',load);
$('attendanceDate').addEventListener('change',load);

$('formYear').addEventListener('change',()=>{
 refreshFormClasses();
});
$('formClass').addEventListener('change',()=>{
 refreshFormSections();
});
$('formSection').addEventListener('change',()=>{
 refreshFormStudents();
});

$('searchFilter').addEventListener('input',load);
$('attendanceDate').value=new Date().toISOString().slice(0,10);
$('bulkDate').value=$('attendanceDate').value;
(async()=>{
 try{
  await loadMeta();
  await load();
  window.lucide?.createIcons();
 }catch(e){
  showMessage(
   e.message,
   false,
   'Attendance Error'
  );
 }
})();
})();
</script>
<?php require dirname(__DIR__) . '/includes/layout-end.php'; ?>

<?php
declare(strict_types=1);

$pageTitle = 'Examination Management';
$pageKey = 'examination_management';

require dirname(__DIR__) . '/includes/layout-start.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['exam_csrf_token']) || !is_string($_SESSION['exam_csrf_token'])) {
    $_SESSION['exam_csrf_token'] = bin2hex(random_bytes(32));
}
$examCsrf = $_SESSION['exam_csrf_token'];
?>

<style>
/* Base styles */
.exam-page{display:grid;gap:16px}
.exam-page .page-title{font-size:28px;line-height:1.1}
.exam-page .page-subtitle{margin-top:4px}
.exam-message{display:none}.exam-message.show{display:block}

/* Stats Cards */
.exam-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.exam-stat{border-radius:14px;min-height:110px;padding:18px 20px;display:flex;align-items:center;gap:14px;color:#fff;position:relative;overflow:hidden;box-shadow:0 12px 28px rgba(15,23,42,.08)}
.exam-stat::after{content:"";position:absolute;width:110px;height:110px;border-radius:50%;right:-38px;top:-40px;background:rgba(255,255,255,.08)}
.exam-stat.purple{background:linear-gradient(135deg,#7448e8,#4b36cf)}
.exam-stat.green{background:linear-gradient(135deg,#45c783,#1ea568)}
.exam-stat.orange{background:linear-gradient(135deg,#ffb327,#ff8a17)}
.exam-stat.blue{background:linear-gradient(135deg,#4696ef,#2469d5)}
.exam-stat-icon{width:50px;height:50px;border-radius:50%;background:rgba(255,255,255,.16);display:grid;place-items:center;flex:0 0 auto}
.exam-stat-icon svg{width:25px;height:25px}
.exam-stat strong{display:block;font-size:25px;line-height:1}
.exam-stat small{display:block;font-size:11px;font-weight:700;opacity:.95;margin-bottom:6px}
.exam-stat .trend{font-size:9px;font-weight:700;opacity:.94;margin-top:8px}

/* Tabs */
.exam-tabs{display:flex;gap:8px;overflow:auto;padding:10px;border-bottom:1px solid var(--border-soft,#e7ebf3)}
.exam-tab{border:1px solid var(--border-soft,#e7ebf3);background:var(--card-bg,#fff);color:var(--text-main,#101b46);border-radius:9px;padding:9px 16px;font-size:12px;font-weight:700;white-space:nowrap;cursor:pointer;transition:all 0.2s}
.exam-tab:hover{background:#f0f4ff}
.exam-tab.active{color:#fff;border-color:transparent;background:linear-gradient(135deg,#6747e8,#2f62d7)}
.exam-panel{display:none;padding-top:16px}.exam-panel.active{display:block}

/* Cards */
.exam-card{border-radius:14px;overflow:hidden;background:#fff;border:1px solid var(--border-soft,#e7ebf3)}
.exam-card-head{padding:14px 16px;border-bottom:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}
.exam-card-head strong{font-size:15px}

/* Filters */
.exam-filter{padding:14px 16px;display:grid;grid-template-columns:minmax(200px,1fr) repeat(4,minmax(130px,.7fr)) auto;gap:10px;border-bottom:1px solid var(--border-soft,#e7ebf3)}
.exam-filter .form-control,.exam-filter .form-select{font-size:13px;padding:8px 12px}

/* Table */
.exam-table-wrap{overflow:auto;padding:0 4px}
.exam-table{min-width:1000px;width:100%;border-collapse:collapse}
.exam-table th{font-size:10px;text-transform:uppercase;letter-spacing:0.5px;padding:10px 12px;background:#f8fafc;border-bottom:2px solid var(--border-soft,#e7ebf3);text-align:left;font-weight:700;color:#64748b}
.exam-table td{font-size:12px;padding:10px 12px;border-bottom:1px solid var(--border-soft,#e7ebf3);vertical-align:middle}
.exam-table tr:hover td{background:#f8fafc}
.exam-empty{padding:40px 18px!important;text-align:center!important;color:var(--text-muted,#64748b)}

/* Badges */
.exam-badge{display:inline-flex;align-items:center;padding:4px 10px;border-radius:999px;font-size:10px;font-weight:700;text-transform:capitalize}
.exam-badge.draft{color:#64748b;background:#f1f5f9}
.exam-badge.scheduled{color:#9a6700;background:#fff7d6}
.exam-badge.ongoing{color:#0d9488;background:#ccfbf1}
.exam-badge.completed{color:#16834f;background:#e8f8ef}
.exam-badge.published{color:#4f46e5;background:#eef2ff}

/* Actions */
.exam-actions{display:flex;gap:5px;flex-wrap:wrap}
.exam-action{width:30px;height:30px;display:grid;place-items:center;border:1px solid #d7def1;border-radius:7px;background:#fff;color:#4f46e5;cursor:pointer;transition:all 0.2s}
.exam-action:hover{background:#f0f4ff;border-color:#4f46e5}
.exam-action.danger{color:#dc2626}
.exam-action.danger:hover{background:#fef2f2;border-color:#dc2626}
.exam-action.success{color:#16834f}
.exam-action.success:hover{background:#f0fdf4;border-color:#16834f}
.exam-action svg{width:14px;height:14px}

/* Pagination */
.exam-pagination{padding:14px 16px;border-top:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}
.exam-page-buttons{display:flex;gap:5px;flex-wrap:wrap}
.exam-page-button{min-width:34px;height:34px;border:1px solid var(--border-soft,#e7ebf3);background:#fff;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;transition:all 0.2s}
.exam-page-button:hover:not(:disabled){background:#f0f4ff}
.exam-page-button.active{color:#fff;border-color:transparent;background:linear-gradient(135deg,#6547e8,#315ed8)}
.exam-page-button:disabled{opacity:0.5;cursor:not-allowed}

/* Modal Forms */
.exam-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.exam-form-grid .full{grid-column:1/-1}
.exam-form-grid label{font-size:12px;font-weight:700;color:#475569;display:block;margin-bottom:4px}
.exam-form-grid .form-control,.exam-form-grid .form-select{font-size:13px;padding:8px 12px;border-radius:8px;border:1px solid #d7def1;width:100%;transition:border-color 0.2s}
.exam-form-grid .form-control:focus,.exam-form-grid .form-select:focus{border-color:#4f46e5;outline:none;box-shadow:0 0 0 3px rgba(79,70,229,0.1)}

/* Marks Entry Table */
.marks-table{width:100%;border-collapse:collapse}
.marks-table th{font-size:10px;text-transform:uppercase;letter-spacing:0.5px;padding:8px 10px;background:#f8fafc;border-bottom:2px solid var(--border-soft,#e7ebf3);text-align:left;font-weight:700;color:#64748b;position:sticky;top:0;z-index:10}
.marks-table td{padding:8px 10px;border-bottom:1px solid var(--border-soft,#e7ebf3);vertical-align:middle}
.marks-table .form-control{font-size:13px;padding:6px 10px;border-radius:6px;border:1px solid #d7def1;width:100px;transition:border-color 0.2s}
.marks-table .form-control:focus{border-color:#4f46e5;outline:none;box-shadow:0 0 0 3px rgba(79,70,229,0.1)}
.marks-table .form-control.blank{background:#fef2f2;border-color:#dc2626}
.marks-table .form-control.absent{background:#f1f5f9;color:#94a3b8}
.marks-table input[type="checkbox"]{width:18px;height:18px;accent-color:#4f46e5;cursor:pointer}

/* Responsive */
@media(max-width:1200px){.exam-filter{grid-template-columns:repeat(3,1fr)}}
@media(max-width:900px){.exam-stats{grid-template-columns:repeat(2,1fr)}}
@media(max-width:575px){.exam-stats,.exam-filter,.exam-form-grid{grid-template-columns:1fr}.exam-form-grid .full{grid-column:auto}.exam-card-head,.exam-pagination{flex-direction:column;align-items:flex-start}}

/* Scrollable marks table */
.marks-scroll{max-height:500px;overflow:auto;position:relative}

/* Buttons */
.btn-exam{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:700;border:none;cursor:pointer;transition:all 0.2s}
.btn-exam-primary{background:linear-gradient(135deg,#6747e8,#2f62d7);color:#fff}
.btn-exam-primary:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(79,70,229,0.3)}
.btn-exam-success{background:#16834f;color:#fff}
.btn-exam-success:hover{background:#0d6e42}
.btn-exam-danger{background:#dc2626;color:#fff}
.btn-exam-danger:hover{background:#b91c1c}
.btn-exam-outline{background:transparent;border:1px solid #d7def1;color:#475569}
.btn-exam-outline:hover{background:#f8fafc}

/* Result view */
.result-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:16px}
.result-summary>div{background:#f8fafc;padding:14px;border-radius:10px;text-align:center}
.result-summary small{font-size:10px;color:#64748b;display:block}
.result-summary strong{font-size:20px;display:block;margin-top:4px}
.result-summary .passed{color:#16834f}
.result-summary .failed{color:#dc2626}
</style>

<div class="exam-page" data-page="exam">
    <div class="page-heading">
        <div>
            <h1 class="page-title">Examination Management</h1>
            <p class="page-subtitle">Plan, conduct and manage all school examinations efficiently.</p>
        </div>
        <div class="page-actions">
            <button id="addExamBtn" class="btn-exam btn-exam-primary" type="button"><i data-lucide="plus"></i> Add Exam</button>
            <button id="refreshBtn" class="btn-exam btn-exam-outline" type="button"><i data-lucide="refresh-cw"></i> Refresh</button>
        </div>
    </div>

    <div id="examMessage" class="alert exam-message"></div>

    <!-- Stats -->
    <section class="exam-stats">
        <article class="exam-stat purple">
            <span class="exam-stat-icon"><i data-lucide="book-open"></i></span>
            <div>
                <small>Total Exams</small>
                <strong id="statTotal">0</strong>
                <div class="trend">All examinations</div>
            </div>
        </article>
        <article class="exam-stat green">
            <span class="exam-stat-icon"><i data-lucide="check-circle"></i></span>
            <div>
                <small>Completed</small>
                <strong id="statCompleted">0</strong>
                <div class="trend">Exams finished</div>
            </div>
        </article>
        <article class="exam-stat orange">
            <span class="exam-stat-icon"><i data-lucide="clock"></i></span>
            <div>
                <small>Ongoing</small>
                <strong id="statOngoing">0</strong>
                <div class="trend">Currently running</div>
            </div>
        </article>
        <article class="exam-stat blue">
            <span class="exam-stat-icon"><i data-lucide="file-text"></i></span>
            <div>
                <small>Results Published</small>
                <strong id="statPublished">0</strong>
                <div class="trend">Results available</div>
            </div>
        </article>
    </section>

    <!-- Tabs -->
    <section class="ui-card">
        <div class="exam-tabs">
            <button class="exam-tab active" data-tab="list" type="button">Exams</button>
            <button class="exam-tab" data-tab="exam-types" type="button">Exam Types</button>
            <button class="exam-tab" data-tab="schedule" type="button">Schedule</button>
            <button class="exam-tab" data-tab="results" type="button">Results</button>
            <button class="exam-tab" data-tab="grade-system" type="button">Grade System</button>
        </div>

        <!-- Exams List Panel -->
        <div class="exam-panel active" data-panel="list">
            <div class="exam-card">
                <div class="exam-card-head">
                    <strong>Exam List</strong>
                    <small id="recordInfo" class="text-muted">Loading...</small>
                </div>
                <div class="exam-filter">
                    <input id="search" class="form-control" placeholder="Search exam name, number...">
                    <select id="examTypeFilter" class="form-select">
                        <option value="all">All Exam Types</option>
                    </select>
                    <select id="statusFilter" class="form-select">
                        <option value="all">All Statuses</option>
                        <option value="draft">Draft</option>
                        <option value="scheduled">Scheduled</option>
                        <option value="ongoing">Ongoing</option>
                        <option value="completed">Completed</option>
                        <option value="published">Published</option>
                    </select>
                    <select id="academicYearFilter" class="form-select">
                        <option value="all">All Academic Years</option>
                    </select>
                    <select id="classFilter" class="form-select">
                        <option value="all">All Classes</option>
                    </select>
                    <button id="resetBtn" class="btn-exam btn-exam-outline" type="button"><i data-lucide="rotate-ccw"></i> Reset</button>
                </div>
                <div class="exam-table-wrap">
                    <table class="exam-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Exam No.</th>
                                <th>Exam Name</th>
                                <th>Type</th>
                                <th>Class</th>
                                <th>Start Date</th>
                                <th>Status</th>
                                <th>Subjects</th>
                                <th>Students</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="examBody">
                            <tr><td colspan="10" class="exam-empty">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="exam-pagination">
                    <small id="pageInfo" class="text-muted"></small>
                    <div id="pagination" class="exam-page-buttons"></div>
                </div>
            </div>
        </div>

        <!-- Exam Types Panel -->
        <div class="exam-panel" data-panel="exam-types">
            <div class="exam-card">
                <div class="exam-card-head">
                    <strong>Exam Types</strong>
                    <button id="addExamTypeBtn" class="btn-exam btn-exam-primary" type="button"><i data-lucide="plus"></i> Add Type</button>
                </div>
                <div class="exam-table-wrap">
                    <table class="exam-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Type Code</th>
                                <th>Type Name</th>
                                <th>Description</th>
                                <th>Weightage</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="examTypeBody">
                            <tr><td colspan="7" class="exam-empty">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Schedule Panel -->
        <div class="exam-panel" data-panel="schedule">
            <div class="exam-card">
                <div class="exam-card-head">
                    <strong>Exam Schedule</strong>
                    <div>
                        <button id="addScheduleBtn" class="btn-exam btn-exam-primary" type="button"><i data-lucide="plus"></i> Add Schedule</button>
                    </div>
                </div>
                <div class="exam-filter">
                    <select id="scheduleExamFilter" class="form-select">
                        <option value="all">Select Exam</option>
                    </select>
                    <button id="loadScheduleBtn" class="btn-exam btn-exam-primary" type="button">Load Schedule</button>
                </div>
                <div class="exam-table-wrap">
                    <table class="exam-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Subject</th>
                                <th>Date</th>
                                <th>Start Time</th>
                                <th>End Time</th>
                                <th>Room</th>
                                <th>Invigilator</th>
                                <th>Max Marks</th>
                                <th>Passing</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="scheduleBody">
                            <tr><td colspan="10" class="exam-empty">Select an exam to view schedule</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Results Panel -->
        <div class="exam-panel" data-panel="results">
            <div class="exam-card">
                <div class="exam-card-head">
                    <strong>Exam Results</strong>
                    <div>
                        <button id="processResultsBtn" class="btn-exam btn-exam-success" type="button"><i data-lucide="calculator"></i> Process Results</button>
                        <button id="publishResultsBtn" class="btn-exam btn-exam-primary" type="button"><i data-lucide="send"></i> Publish</button>
                        <button id="exportResultsBtn" class="btn-exam btn-exam-outline" type="button"><i data-lucide="download"></i> Export</button>
                    </div>
                </div>
                <div class="exam-filter">
                    <select id="resultExamFilter" class="form-select">
                        <option value="all">Select Exam</option>
                    </select>
                    <button id="loadResultsBtn" class="btn-exam btn-exam-primary" type="button">Load Results</button>
                </div>
                <div id="resultSummary" class="result-summary"></div>
                <div class="exam-table-wrap">
                    <table class="exam-table">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Admission No</th>
                                <th>Student Name</th>
                                <th>Total Marks</th>
                                <th>Obtained</th>
                                <th>Percentage</th>
                                <th>Grade</th>
                                <th>Grade Point</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="resultBody">
                            <tr><td colspan="9" class="exam-empty">Select an exam to view results</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Grade System Panel -->
        <div class="exam-panel" data-panel="grade-system">
            <div class="exam-card">
                <div class="exam-card-head">
                    <strong>Grade System</strong>
                </div>
                <div class="exam-table-wrap">
                    <table class="exam-table">
                        <thead>
                            <tr>
                                <th>Grade</th>
                                <th>Min Percentage</th>
                                <th>Max Percentage</th>
                                <th>Grade Point</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody id="gradeBody">
                            <tr><td colspan="5" class="exam-empty">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Add/Edit Exam Modal -->
<div class="modal fade" id="examModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <form id="examForm">
                <div class="modal-header">
                    <h5 id="examModalTitle" class="modal-title">Add Exam</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body exam-form-grid">
                    <input id="examId" type="hidden">
                    <input id="examNo" type="hidden">
                    
                    <div>
                        <label class="form-label">Exam Type *</label>
                        <select id="examTypeId" class="form-select" required>
                            <option value="">Select Type</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Exam Name *</label>
                        <input id="examName" class="form-control" required placeholder="e.g., Unit Test 1">
                    </div>
                    <div>
                        <label class="form-label">Academic Year *</label>
                        <select id="academicYearId" class="form-select" required>
                            <option value="">Select Academic Year</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Class <span class="text-muted">(Optional)</span></label>
                        <select id="classId" class="form-select">
                            <option value="">All Classes / Auto from Section</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Section</label>
                        <select id="sectionId" class="form-select">
                            <option value="">All Sections</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Status</label>
                        <select id="examStatus" class="form-select">
                            <option value="draft">Draft</option>
                            <option value="scheduled">Scheduled</option>
                            <option value="ongoing">Ongoing</option>
                            <option value="completed">Completed</option>
                            <option value="published">Published</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Start Date *</label>
                        <input id="startDate" class="form-control" type="date" required>
                    </div>
                    <div>
                        <label class="form-label">End Date *</label>
                        <input id="endDate" class="form-control" type="date" required>
                    </div>
                    <div>
                        <label class="form-label">Total Marks</label>
                        <input id="totalMarks" class="form-control" type="number" step="0.01" value="0">
                    </div>
                    <div>
                        <label class="form-label">Passing Marks</label>
                        <input id="passingMarks" class="form-control" type="number" step="0.01" value="0">
                    </div>
                    <div class="full">
                        <label class="form-label">Description</label>
                        <textarea id="examDescription" class="form-control" rows="2" placeholder="Additional details about the exam"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-exam btn-exam-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-exam btn-exam-primary">Save Exam</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Exam Type Modal -->
<div class="modal fade" id="examTypeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="examTypeForm">
                <div class="modal-header">
                    <h5 id="examTypeModalTitle" class="modal-title">Add Exam Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body exam-form-grid">
                    <input id="examTypeRecordId" type="hidden">
                    <div>
                        <label class="form-label">Type Code *</label>
                        <input id="examTypeCode" class="form-control" required placeholder="e.g., UT1">
                    </div>
                    <div>
                        <label class="form-label">Type Name *</label>
                        <input id="examTypeName" class="form-control" required placeholder="e.g., Unit Test 1">
                    </div>
                    <div>
                        <label class="form-label">Weightage (%)</label>
                        <input id="examTypeWeightage" class="form-control" type="number" step="0.01" value="0">
                    </div>
                    <div>
                        <label class="form-label">Status</label>
                        <select id="examTypeActive" class="form-select">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                    <div class="full">
                        <label class="form-label">Description</label>
                        <textarea id="examTypeDescription" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-exam btn-exam-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-exam btn-exam-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Schedule Modal -->
<div class="modal fade" id="scheduleModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="scheduleForm">
                <div class="modal-header">
                    <h5 id="scheduleModalTitle" class="modal-title">Add Schedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body exam-form-grid">
                    <input id="scheduleId" type="hidden">
                    <input id="scheduleExamId" type="hidden">
                    
                    <div>
                        <label class="form-label">Subject *</label>
                        <select id="subjectId" class="form-select" required>
                            <option value="">Select Subject</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Exam Date *</label>
                        <input id="examDate" class="form-control" type="date" required>
                    </div>
                    <div>
                        <label class="form-label">Start Time *</label>
                        <input id="startTime" class="form-control" type="time" required>
                    </div>
                    <div>
                        <label class="form-label">End Time *</label>
                        <input id="endTime" class="form-control" type="time" required>
                    </div>
                    <div>
                        <label class="form-label">Room No</label>
                        <input id="roomNo" class="form-control" placeholder="e.g., Room 101">
                    </div>
                    <div>
                        <label class="form-label">Invigilator</label>
                        <select id="invigilatorId" class="form-select">
                            <option value="">Select Invigilator</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Max Marks</label>
                        <input id="maxMarks" class="form-control" type="number" step="0.01" value="100">
                    </div>
                    <div>
                        <label class="form-label">Passing Marks</label>
                        <input id="passingMarksSchedule" class="form-control" type="number" step="0.01" value="35">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-exam btn-exam-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-exam btn-exam-primary">Save Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Marks Entry Modal -->
<div class="modal fade" id="marksModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Marks Entry</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="marksInfo" class="alert alert-info"></div>
                <form id="marksForm">
                    <input id="marksScheduleId" type="hidden">
                    <div class="marks-scroll">
                        <table class="marks-table">
                            <thead>
                                <tr>
                                    <th style="width:50px">#</th>
                                    <th>Admission No</th>
                                    <th>Student Name</th>
                                    <th style="width:120px">Marks Obtained</th>
                                    <th style="width:80px">Absent</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody id="marksBody">
                                <tr><td colspan="6" class="exam-empty">Loading students...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn-exam btn-exam-primary">Save Marks</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    // API URL - adjust based on your setup
    const apiUrl = new URL('../api/examinations.php', window.location.href).href;
    
    console.log('API URL:', apiUrl);

    let csrfToken = <?= json_encode($examCsrf) ?>;
    let rows = [];
    let page = 1;
    let permissions = { edit: true, delete: true };
    let loadTimer = null;
    let currentExamId = 0;
    let examFormMeta = { sections: [] };

    const $ = id => document.getElementById(id);
    const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[c]);
    const money = v => new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 2 }).format(Number(v || 0));
    const ucfirst = str => str ? String(str).charAt(0).toUpperCase() + String(str).slice(1) : '';
    const badge = (v, type = 'status') => {
        const classes = {
            'draft': 'draft',
            'scheduled': 'scheduled',
            'ongoing': 'ongoing',
            'completed': 'completed',
            'published': 'published'
        };
        return `<span class="exam-badge ${classes[v] || 'draft'}">${esc(v || 'draft')}</span>`;
    };
    const formatDate = date => {
        if (!date) return '-';
        try {
            const d = new Date(date + 'T00:00:00');
            return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
        } catch { return date; }
    };

    async function request(action, data = {}, method = 'GET') {
        try {
            let response;
            if (method === 'GET') {
                const url = new URL(apiUrl, window.location.origin);
                url.searchParams.set('action', action);
                Object.entries(data).forEach(([k, v]) => {
                    if (v !== '' && v !== null && v !== undefined) url.searchParams.set(k, String(v));
                });
                console.log('Request URL:', url.toString());
                response = await fetch(url.toString(), { 
                    headers: { Accept: 'application/json' }, 
                    credentials: 'same-origin' 
                });
            } else {
                console.log('POST Request to:', apiUrl);
                response = await fetch(apiUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ action, csrf_token: csrfToken, ...data })
                });
            }

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const text = await response.text();
            console.log('Response:', text.substring(0, 200));
            
            let result;
            try {
                result = JSON.parse(text);
            } catch (e) {
                console.error('Invalid JSON:', text);
                throw new Error('API returned invalid JSON response');
            }

            if (!result.success) {
                throw new Error(result.message || 'Request failed.');
            }

            if (result.data?.csrf_token) csrfToken = result.data.csrf_token;
            return result;
        } catch (error) {
            console.error('API Request Error:', error);
            throw error;
        }
    }

    function message(text, ok = false) {
        const b = $('examMessage');
        if (!b) return;
        b.className = 'alert exam-message show ' + (ok ? 'alert-success' : 'alert-danger');
        b.textContent = text;
        setTimeout(() => { b.className = 'alert exam-message'; }, 5000);
    }

    // ============================================
    // DASHBOARD STATS
    // ============================================
    async function loadStats() {
        try {
            const r = await request('dashboard_stats');
            const s = r.data.stats || {};
            $('statTotal').textContent = s.total_exams || 0;
            $('statCompleted').textContent = s.completed_exams || 0;
            $('statOngoing').textContent = s.ongoing_exams || 0;
            $('statPublished').textContent = s.published_results || 0;
        } catch (e) {
            console.error('Stats error:', e);
        }
    }

    // ============================================
    // LOAD EXAMS
    // ============================================
    async function loadExams() {
        try {
            const r = await request('list_exams', {
                search: $('search').value.trim(),
                exam_type_id: $('examTypeFilter').value,
                status: $('statusFilter').value,
                academic_year_id: $('academicYearFilter').value,
                class_id: $('classFilter').value,
                page: page,
                per_page: 10
            });

            // Populate filters
            populateFilters(r.data.filters);

            // Render table
            renderExams(r.data.records || []);
            renderPagination(r.data.pagination || {});
            
            return r.data;
        } catch (e) {
            message(e.message);
            $('examBody').innerHTML = '<tr><td colspan="10" class="exam-empty">Error loading exams</td></tr>';
        }
    }

    function renderExams(data) {
        rows = data;
        const body = $('examBody');
        if (!body) return;
        
        body.innerHTML = data.map((r, index) => `
            <tr>
                <td>${index + 1}</td>
                <td><strong>${esc(r.exam_no)}</strong></td>
                <td>${esc(r.exam_name)}</td>
                <td>${esc(r.exam_type_name || '-')}</td>
                <td>${esc(r.class_name || '-')} ${r.section_name ? esc(r.section_name) : ''}</td>
                <td>${formatDate(r.start_date)}</td>
                <td>${badge(r.exam_status)}</td>
                <td>${r.subject_count || 0}</td>
                <td>${r.student_count || 0}</td>
                <td>
                    <div class="exam-actions">
                        <button class="exam-action js-view" data-id="${r.id}" title="View">
                            <i data-lucide="eye"></i>
                        </button>
                        ${permissions.edit ? `<button class="exam-action js-edit" data-id="${r.id}" title="Edit"><i data-lucide="pencil"></i></button>` : ''}
                        ${permissions.delete ? `<button class="exam-action danger js-delete" data-id="${r.id}" title="Delete"><i data-lucide="trash-2"></i></button>` : ''}
                        <button class="exam-action js-schedule" data-id="${r.id}" title="Manage Schedule">
                            <i data-lucide="calendar"></i>
                        </button>
                        <button class="exam-action js-marks" data-id="${r.id}" title="Enter Marks">
                            <i data-lucide="edit-3"></i>
                        </button>
                        ${r.exam_status === 'completed' ? `<button class="exam-action success js-results" data-id="${r.id}" title="View Results"><i data-lucide="bar-chart-2"></i></button>` : ''}
                    </div>
                </td>
            </tr>
        `).join('') || '<tr><td colspan="10" class="exam-empty">No exams found.</td></tr>';

        // Event listeners
        document.querySelectorAll('.js-view').forEach(b => b.onclick = () => viewExam(Number(b.dataset.id)));
        document.querySelectorAll('.js-edit').forEach(b => b.onclick = () => openExam(Number(b.dataset.id)));
        document.querySelectorAll('.js-delete').forEach(b => b.onclick = () => deleteExam(Number(b.dataset.id)));
        document.querySelectorAll('.js-schedule').forEach(b => b.onclick = () => loadSchedule(Number(b.dataset.id)));
        document.querySelectorAll('.js-marks').forEach(b => b.onclick = () => openMarksEntry(Number(b.dataset.id)));
        document.querySelectorAll('.js-results').forEach(b => b.onclick = () => loadResults(Number(b.dataset.id)));
        
        if (window.lucide) window.lucide.createIcons();
    }

    function populateFilters(filters) {
        // Exam Types
        const typeFilter = $('examTypeFilter');
        if (typeFilter && filters.exam_types) {
            const currentVal = typeFilter.value;
            typeFilter.innerHTML = '<option value="all">All Exam Types</option>';
            filters.exam_types.forEach(t => {
                typeFilter.innerHTML += `<option value="${t.id}">${esc(t.exam_type_name)}</option>`;
            });
            typeFilter.value = currentVal || 'all';
        }

        // Academic Years
        const yearFilter = $('academicYearFilter');
        if (yearFilter && filters.academic_years) {
            const currentVal = yearFilter.value;
            yearFilter.innerHTML = '<option value="all">All Academic Years</option>';
            filters.academic_years.forEach(y => {
                yearFilter.innerHTML += `<option value="${y.id}">${esc(y.academic_year_name)}</option>`;
            });
            yearFilter.value = currentVal || 'all';
        }

        // Classes
        const classFilter = $('classFilter');
        if (classFilter && filters.classes) {
            const currentVal = classFilter.value;
            classFilter.innerHTML = '<option value="all">All Classes</option>';
            filters.classes.forEach(c => {
                classFilter.innerHTML += `<option value="${c.id}">${esc(c.class_name)}</option>`;
            });
            classFilter.value = currentVal || 'all';
        }
    }

    function renderPagination(p) {
        const total = Number(p.total || 0);
        const current = Number(p.page || 1);
        const per = Number(p.per_page || 10);
        const last = Math.max(1, Number(p.last_page || 1));
        const start = total ? ((current - 1) * per) + 1 : 0;
        const end = Math.min(current * per, total);

        $('recordInfo').textContent = `${total} exam${total === 1 ? '' : 's'}`;
        $('pageInfo').textContent = `Showing ${start}-${end} of ${total}`;

        let html = `<button class="exam-page-button" data-page="${current - 1}" ${current <= 1 ? 'disabled' : ''}>‹</button>`;
        for (let x = Math.max(1, current - 2); x <= Math.min(last, current + 2); x++) {
            html += `<button class="exam-page-button ${x === current ? 'active' : ''}" data-page="${x}">${x}</button>`;
        }
        html += `<button class="exam-page-button" data-page="${current + 1}" ${current >= last ? 'disabled' : ''}>›</button>`;

        $('pagination').innerHTML = html;
        document.querySelectorAll('.exam-page-button').forEach(b => {
            b.onclick = () => {
                if (!b.disabled) {
                    page = Number(b.dataset.page);
                    loadExams();
                }
            };
        });
    }

    // ============================================
    // EXAM CRUD OPERATIONS
    // ============================================
    async function openExam(id = 0) {
        try {
            const formData = await request('form_data');
            populateExamForm(formData.data || {});

            if (id > 0) {
                const r = await request('exam_detail', { id });
                const x = r.data.record || {};

                // Class and academic year must be selected before loading their sections.
                $('academicYearId').value = x.academic_year_id || '';
                $('classId').value = x.class_id || '';
                await loadSections(x.section_id || '');
                fillExamForm(x);
                $('examModalTitle').textContent = 'Edit Exam';
            } else {
                $('examId').value = '';
                $('examNo').value = '';
                $('examModalTitle').textContent = 'Add Exam';
                resetExamForm();
                renderSectionOptions(examFormMeta.sections || []);
            }

            const modal = new bootstrap.Modal($('examModal'));
            modal.show();
        } catch (e) {
            message(e.message);
        }
    }

    function populateExamForm(data) {
        examFormMeta = {
            ...data,
            sections: Array.isArray(data.sections) ? data.sections : []
        };

        // Exam Types
        const typeSelect = $('examTypeId');
        typeSelect.innerHTML = '<option value="">Select Type</option>';
        (data.exam_types || []).forEach(t => {
            typeSelect.innerHTML += `<option value="${Number(t.id)}">${esc(t.exam_type_name)}</option>`;
        });

        // Academic Years
        const yearSelect = $('academicYearId');
        yearSelect.innerHTML = '<option value="">Select Academic Year</option>';
        (data.academic_years || []).forEach(y => {
            yearSelect.innerHTML += `<option value="${Number(y.id)}">${esc(y.academic_year_name)}</option>`;
        });

        // Classes
        const classSelect = $('classId');
        classSelect.innerHTML = '<option value="">All Classes / Auto from Section</option>';
        (data.classes || []).forEach(c => {
            classSelect.innerHTML += `<option value="${Number(c.id)}">${esc(c.class_name)}</option>`;
        });

        // Display every active section initially. Selecting a class/year filters this list.
        renderSectionOptions(examFormMeta.sections);
    }

    function renderSectionOptions(records, selectedId = '') {
        const sectionSelect = $('sectionId');
        if (!sectionSelect) return;

        const selected = String(selectedId || sectionSelect.value || '');
        const rows = Array.isArray(records) ? records : [];

        sectionSelect.innerHTML = '<option value="">All Sections</option>';
        rows.forEach(section => {
            const className = String(section.class_name_snapshot || '').trim();
            const sectionName = String(section.section_name || '').trim();
            const sectionCode = String(section.section_code || '').trim();
            const label = [className, sectionName].filter(Boolean).join(' - ')
                + (sectionCode ? ` (${sectionCode})` : '');

            sectionSelect.insertAdjacentHTML(
                'beforeend',
                `<option value="${Number(section.id)}" data-class-id="${Number(section.class_id || 0)}" data-academic-year-id="${Number(section.academic_year_id || 0)}">${esc(label || sectionName || sectionCode || ('Section ' + section.id))}</option>`
            );
        });

        if (selected && [...sectionSelect.options].some(option => option.value === selected)) {
            sectionSelect.value = selected;
        } else {
            sectionSelect.value = '';
        }
    }

    function syncClassFromSelectedSection() {
        const sectionSelect = $('sectionId');
        const selectedOption = sectionSelect?.options[sectionSelect.selectedIndex];
        if (!selectedOption || !selectedOption.value) return;

        const sectionClassId = Number(selectedOption.dataset.classId || 0);
        const sectionYearId = Number(selectedOption.dataset.academicYearId || 0);

        // Class is optional for the user. When a section is selected, use the
        // section's own class automatically so the saved exam remains consistent.
        if (sectionClassId > 0) {
            const classSelect = $('classId');
            if ([...classSelect.options].some(option => Number(option.value) === sectionClassId)) {
                classSelect.value = String(sectionClassId);
            }
        }

        // Academic Year remains required, but can be completed automatically
        // from the selected section when it is still empty.
        if (sectionYearId > 0 && !$('academicYearId').value) {
            const yearSelect = $('academicYearId');
            if ([...yearSelect.options].some(option => Number(option.value) === sectionYearId)) {
                yearSelect.value = String(sectionYearId);
            }
        }
    }

    async function loadSections(selectedId = '') {
        const sectionSelect = $('sectionId');
        if (!sectionSelect) return;

        const classId = Number($('classId').value || 0);
        const academicYearId = Number($('academicYearId').value || 0);

        // Before class/year selection, show all active sections returned by form_data.
        if (classId <= 0 && academicYearId <= 0) {
            renderSectionOptions(examFormMeta.sections || [], selectedId);
            return;
        }

        sectionSelect.disabled = true;
        sectionSelect.innerHTML = '<option value="">Loading sections...</option>';

        try {
            const response = await request('get_sections', {
                class_id: classId || '',
                academic_year_id: academicYearId || ''
            });

            const records = Array.isArray(response.data?.sections)
                ? response.data.sections
                : [];

            renderSectionOptions(records, selectedId);

            if (records.length === 0) {
                sectionSelect.innerHTML = '<option value="">No active sections found</option>';
            }
        } catch (error) {
            renderSectionOptions([], '');
            sectionSelect.innerHTML = '<option value="">Unable to load sections</option>';
            message(error.message);
        } finally {
            sectionSelect.disabled = false;
        }
    }

    function fillExamForm(data) {
        $('examId').value = data.id || '';
        $('examNo').value = data.exam_no || '';
        $('examTypeId').value = data.exam_type_id || '';
        $('examName').value = data.exam_name || '';
        $('academicYearId').value = data.academic_year_id || '';
        $('classId').value = data.class_id || '';
        $('sectionId').value = data.section_id || '';
        $('examStatus').value = data.exam_status || 'draft';
        $('startDate').value = data.start_date || '';
        $('endDate').value = data.end_date || '';
        $('totalMarks').value = data.total_marks || 0;
        $('passingMarks').value = data.passing_marks || 0;
        $('examDescription').value = data.description || '';
    }

    function resetExamForm() {
        $('examTypeId').value = '';
        $('examName').value = '';
        $('academicYearId').value = '';
        $('classId').value = '';
        renderSectionOptions(examFormMeta.sections || []);
        $('sectionId').value = '';
        $('examStatus').value = 'draft';
        $('startDate').value = '';
        $('endDate').value = '';
        $('totalMarks').value = 0;
        $('passingMarks').value = 0;
        $('examDescription').value = '';
    }

    async function viewExam(id) {
        try {
            const r = await request('exam_detail', { id });
            const x = r.data.record;
            
            let html = `
                <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-bottom:16px">
                    <div><strong>Exam No:</strong> ${esc(x.exam_no)}</div>
                    <div><strong>Exam Name:</strong> ${esc(x.exam_name)}</div>
                    <div><strong>Type:</strong> ${esc(x.exam_type_name || '-')}</div>
                    <div><strong>Status:</strong> ${badge(x.exam_status)}</div>
                    <div><strong>Class:</strong> ${esc(x.class_name || '-')}</div>
                    <div><strong>Section:</strong> ${esc(x.section_name || 'All')}</div>
                    <div><strong>Start Date:</strong> ${formatDate(x.start_date)}</div>
                    <div><strong>End Date:</strong> ${formatDate(x.end_date)}</div>
                    <div><strong>Total Marks:</strong> ${x.total_marks || 0}</div>
                    <div><strong>Passing Marks:</strong> ${x.passing_marks || 0}</div>
                </div>
                ${x.description ? `<div><strong>Description:</strong><p>${esc(x.description)}</p></div>` : ''}
                <hr>
                <h6>Schedule</h6>
                <table class="exam-table">
                    <thead><tr><th>Subject</th><th>Date</th><th>Time</th><th>Room</th><th>Invigilator</th></tr></thead>
                    <tbody>
                        ${(x.schedules || []).map(s => `
                            <tr>
                                <td>${esc(s.subject_name || '-')}</td>
                                <td>${formatDate(s.exam_date)}</td>
                                <td>${s.start_time} - ${s.end_time}</td>
                                <td>${esc(s.room_no || '-')}</td>
                                <td>${esc(s.invigilator_name || '-')}</td>
                            </tr>
                        `).join('') || '<tr><td colspan="5">No schedule found</td></tr>'}
                    </tbody>
                </table>
            `;
            
            // Show in a modal or alert
            const modal = new bootstrap.Modal(document.createElement('div'));
            // For simplicity, show in alert - you can create a view modal
            alert(html.replace(/<[^>]*>/g, '\n').replace(/\n{3,}/g, '\n\n'));
            
        } catch (e) {
            message(e.message);
        }
    }

    async function deleteExam(id) {
        if (!confirm('Delete this exam? This will also delete all schedules and results.')) return;
        try {
            const r = await request('delete_exam', { id }, 'POST');
            message(r.message, true);
            await loadExams();
            await loadStats();
        } catch (e) {
            message(e.message);
        }
    }

    // ============================================
    // EXAM TYPES
    // ============================================
    async function loadExamTypes() {
        try {
            const r = await request('list_exam_types');
            const body = $('examTypeBody');
            body.innerHTML = (r.data.exam_types || []).map((t, i) => `
                <tr>
                    <td>${i + 1}</td>
                    <td><strong>${esc(t.exam_type_code)}</strong></td>
                    <td>${esc(t.exam_type_name)}</td>
                    <td>${esc(t.description || '-')}</td>
                    <td>${t.weightage || 0}%</td>
                    <td>${t.is_active ? '✅ Active' : '❌ Inactive'}</td>
                    <td>
                        <div class="exam-actions">
                            <button class="exam-action js-edit-type" data-id="${t.id}" title="Edit"><i data-lucide="pencil"></i></button>
                        </div>
                    </td>
                </tr>
            `).join('') || '<tr><td colspan="7" class="exam-empty">No exam types found.</td></tr>';

            document.querySelectorAll('.js-edit-type').forEach(b => b.onclick = () => openExamType(Number(b.dataset.id)));
            if (window.lucide) window.lucide.createIcons();
        } catch (e) {
            $('examTypeBody').innerHTML = '<tr><td colspan="7" class="exam-empty">Error loading exam types</td></tr>';
        }
    }

    function openExamType(id = 0) {
        // Implementation for exam type edit
        $('examTypeModalTitle').textContent = id ? 'Edit Exam Type' : 'Add Exam Type';
        $('examTypeRecordId').value = '';
        $('examTypeCode').value = '';
        $('examTypeName').value = '';
        $('examTypeWeightage').value = 0;
        $('examTypeActive').value = 1;
        $('examTypeDescription').value = '';
        
        if (id > 0) {
            // Load exam type data and fill
            // For simplicity, we'll reload the list
        }
        
        const modal = new bootstrap.Modal($('examTypeModal'));
        modal.show();
    }

    // ============================================
    // SCHEDULE MANAGEMENT
    // ============================================
    async function loadSchedule(examId = 0) {
        if (examId > 0) {
            currentExamId = examId;
            $('scheduleExamFilter').value = examId;
        }
        
        const id = $('scheduleExamFilter').value;
        if (!id || id === 'all') {
            $('scheduleBody').innerHTML = '<tr><td colspan="10" class="exam-empty">Select an exam to view schedule</td></tr>';
            return;
        }

        try {
            const r = await request('list_schedules', { exam_id: id });
            const body = $('scheduleBody');
            body.innerHTML = (r.data.records || []).map((s, i) => `
                <tr>
                    <td>${i + 1}</td>
                    <td>${esc(s.subject_name || '-')}</td>
                    <td>${formatDate(s.exam_date)}</td>
                    <td>${s.start_time}</td>
                    <td>${s.end_time}</td>
                    <td>${esc(s.room_no || '-')}</td>
                    <td>${esc(s.invigilator_name || '-')}</td>
                    <td>${s.max_marks || 0}</td>
                    <td>${s.passing_marks || 0}</td>
                    <td>
                        <div class="exam-actions">
                            <button class="exam-action js-edit-schedule" data-id="${s.id}" title="Edit"><i data-lucide="pencil"></i></button>
                            <button class="exam-action danger js-delete-schedule" data-id="${s.id}" title="Delete"><i data-lucide="trash-2"></i></button>
                        </div>
                    </td>
                </tr>
            `).join('') || '<tr><td colspan="10" class="exam-empty">No schedule found for this exam</td></tr>';

            document.querySelectorAll('.js-edit-schedule').forEach(b => b.onclick = () => openSchedule(Number(b.dataset.id)));
            document.querySelectorAll('.js-delete-schedule').forEach(b => b.onclick = () => deleteSchedule(Number(b.dataset.id)));
            if (window.lucide) window.lucide.createIcons();
        } catch (e) {
            $('scheduleBody').innerHTML = '<tr><td colspan="10" class="exam-empty">Error loading schedule</td></tr>';
        }
    }

    async function openSchedule(id = 0) {
        // Load subjects and teachers for the dropdowns
        try {
            const formData = await request('form_data');
            const subjectSelect = $('subjectId');
            subjectSelect.innerHTML = '<option value="">Select Subject</option>';
            (formData.data.subjects || []).forEach(s => {
                subjectSelect.innerHTML += `<option value="${s.id}">${esc(s.subject_name)} (${esc(s.subject_code)})</option>`;
            });

            const invigilatorSelect = $('invigilatorId');
            invigilatorSelect.innerHTML = '<option value="">Select Invigilator</option>';
            (formData.data.teachers || []).forEach(t => {
                invigilatorSelect.innerHTML += `<option value="${t.id}">${esc(t.name)}</option>`;
            });

            // Set exam ID
            $('scheduleExamId').value = $('scheduleExamFilter').value;

            if (id > 0) {
                // Load schedule details
                $('scheduleModalTitle').textContent = 'Edit Schedule';
            } else {
                $('scheduleModalTitle').textContent = 'Add Schedule';
                resetScheduleForm();
            }

            const modal = new bootstrap.Modal($('scheduleModal'));
            modal.show();
        } catch (e) {
            message(e.message);
        }
    }

    function resetScheduleForm() {
        $('scheduleId').value = '';
        $('subjectId').value = '';
        $('examDate').value = '';
        $('startTime').value = '';
        $('endTime').value = '';
        $('roomNo').value = '';
        $('invigilatorId').value = '';
        $('maxMarks').value = 100;
        $('passingMarksSchedule').value = 35;
    }

    async function deleteSchedule(id) {
        if (!confirm('Delete this schedule?')) return;
        try {
            const r = await request('delete_schedule', { id }, 'POST');
            message(r.message, true);
            await loadSchedule();
        } catch (e) {
            message(e.message);
        }
    }

    // ============================================
    // MARKS ENTRY
    // ============================================
    async function openMarksEntry(examId) {
        try {
            // First, we need to get the schedule ID - for now, we'll list schedules
            const schedules = await request('list_schedules', { exam_id: examId });
            const scheduleList = schedules.data.records || [];
            
            if (scheduleList.length === 0) {
                message('No schedules found for this exam. Please create schedules first.');
                return;
            }

            // For simplicity, use the first schedule
            const scheduleId = scheduleList[0].id;
            await loadMarksEntry(scheduleId);
        } catch (e) {
            message(e.message);
        }
    }

    async function loadMarksEntry(scheduleId) {
        try {
            const r = await request('marks_entry', { schedule_id: scheduleId });
            const data = r.data;
            
            $('marksScheduleId').value = scheduleId;
            $('marksInfo').innerHTML = `
                <strong>${esc(data.schedule.subject_name)}</strong> - 
                Max Marks: ${data.schedule.max_marks} | 
                Passing: ${data.schedule.passing_marks}
            `;

            const body = $('marksBody');
            body.innerHTML = (data.students || []).map((student, i) => {
                const mark = data.marks[student.student_id] || {};
                const marksObtained = mark.marks_obtained !== null && mark.marks_obtained !== undefined ? mark.marks_obtained : '';
                const isAbsent = mark.is_absent || 0;
                const remarks = mark.remarks || '';
                
                return `
                    <tr>
                        <td>${i + 1}</td>
                        <td>${esc(student.admission_no || '-')}</td>
                        <td>${esc(student.student_name)}</td>
                        <td>
                            <input type="number" class="form-control marks-input" 
                                   name="marks[${student.student_id}][marks_obtained]" 
                                   value="${marksObtained}" 
                                   ${isAbsent ? 'disabled' : ''}
                                   step="0.01" min="0" max="${data.schedule.max_marks}">
                        </td>
                        <td>
                            <input type="checkbox" class="absent-checkbox" 
                                   name="marks[${student.student_id}][is_absent]" 
                                   value="1" ${isAbsent ? 'checked' : ''}>
                        </td>
                        <td>
                            <input type="text" class="form-control" 
                                   name="marks[${student.student_id}][remarks]" 
                                   value="${esc(remarks)}" style="width:100%">
                        </td>
                    </tr>
                `;
            }).join('') || '<tr><td colspan="6" class="exam-empty">No students found</td></tr>';

            // Handle absent checkbox
            document.querySelectorAll('.absent-checkbox').forEach(cb => {
                cb.onchange = function() {
                    const row = this.closest('tr');
                    const marksInput = row.querySelector('.marks-input');
                    if (this.checked) {
                        marksInput.disabled = true;
                        marksInput.value = '';
                    } else {
                        marksInput.disabled = false;
                    }
                };
            });

            const modal = new bootstrap.Modal($('marksModal'));
            modal.show();
            
        } catch (e) {
            message(e.message);
        }
    }

    // ============================================
    // RESULTS
    // ============================================
    async function loadResults(examId = 0) {
        if (examId > 0) {
            $('resultExamFilter').value = examId;
        }
        
        const id = $('resultExamFilter').value;
        if (!id || id === 'all') {
            $('resultBody').innerHTML = '<tr><td colspan="9" class="exam-empty">Select an exam to view results</td></tr>';
            $('resultSummary').innerHTML = '';
            return;
        }

        try {
            const r = await request('exam_results', { exam_id: id });
            const results = r.data.records || [];
            
            // Calculate summary
            const total = results.length;
            const passed = results.filter(r => r.is_passed).length;
            const failed = total - passed;
            const passPercentage = total > 0 ? (passed / total * 100) : 0;
            
            $('resultSummary').innerHTML = `
                <div><small>Total Students</small><strong>${total}</strong></div>
                <div><small>Passed</small><strong class="passed">${passed}</strong></div>
                <div><small>Failed</small><strong class="failed">${failed}</strong></div>
                <div><small>Pass %</small><strong>${passPercentage.toFixed(1)}%</strong></div>
            `;

            const body = $('resultBody');
            body.innerHTML = results.map((r, i) => `
                <tr>
                    <td><strong>${r.rank || '-'}</strong></td>
                    <td>${esc(r.admission_no || '-')}</td>
                    <td>${esc(r.student_name)}</td>
                    <td>${r.total_marks || 0}</td>
                    <td>${r.obtained_marks || 0}</td>
                    <td>${r.percentage ? Number(r.percentage).toFixed(2) : 0}%</td>
                    <td><strong>${esc(r.grade || '-')}</strong></td>
                    <td>${r.grade_point || 0}</td>
                    <td>${r.is_passed ? '✅ Passed' : '❌ Failed'}</td>
                </tr>
            `).join('') || '<tr><td colspan="9" class="exam-empty">No results found</td></tr>';

            if (window.lucide) window.lucide.createIcons();
        } catch (e) {
            message(e.message);
        }
    }

    // ============================================
    // GRADE SYSTEM
    // ============================================
    async function loadGradeSystem() {
        try {
            const r = await request('grade_system');
            const body = $('gradeBody');
            body.innerHTML = (r.data.grades || []).map(g => `
                <tr>
                    <td><strong>${esc(g.grade)}</strong></td>
                    <td>${g.min_percentage}%</td>
                    <td>${g.max_percentage}%</td>
                    <td>${g.grade_point}</td>
                    <td>${esc(g.description)}</td>
                </tr>
            `).join('') || '<tr><td colspan="5" class="exam-empty">No grade system found</td></tr>';
        } catch (e) {
            $('gradeBody').innerHTML = '<tr><td colspan="5" class="exam-empty">Error loading grade system</td></tr>';
        }
    }

    // ============================================
    // PROCESS & PUBLISH RESULTS
    // ============================================
    async function processResults() {
        const examId = $('resultExamFilter').value;
        if (!examId || examId === 'all') {
            message('Please select an exam first');
            return;
        }
        if (!confirm('Process results for this exam? This will calculate grades and ranks.')) return;
        
        try {
            const r = await request('process_results', { exam_id: examId }, 'POST');
            message(r.message, true);
            await loadResults(examId);
            await loadExams();
            await loadStats();
        } catch (e) {
            message(e.message);
        }
    }

    async function publishResults() {
        const examId = $('resultExamFilter').value;
        if (!examId || examId === 'all') {
            message('Please select an exam first');
            return;
        }
        if (!confirm('Publish results for this exam? This will make results visible to students.')) return;
        
        try {
            const r = await request('publish_results', { exam_id: examId }, 'POST');
            message(r.message, true);
            await loadResults(examId);
            await loadExams();
            await loadStats();
        } catch (e) {
            message(e.message);
        }
    }

    // ============================================
    // POPULATE FILTER DROPDOWNS
    // ============================================
    async function populateFilterDropdowns() {
        try {
            const formData = await request('form_data');
            
            // Schedule exam filter
            const scheduleFilter = $('scheduleExamFilter');
            if (scheduleFilter) {
                scheduleFilter.innerHTML = '<option value="all">Select Exam</option>';
                // Get exams for dropdown
                const exams = await request('list_exams', { per_page: 100 });
                (exams.data.records || []).forEach(e => {
                    scheduleFilter.innerHTML += `<option value="${e.id}">${esc(e.exam_no)} - ${esc(e.exam_name)}</option>`;
                });
            }

            // Results exam filter
            const resultFilter = $('resultExamFilter');
            if (resultFilter) {
                resultFilter.innerHTML = '<option value="all">Select Exam</option>';
                const exams = await request('list_exams', { per_page: 100 });
                (exams.data.records || []).forEach(e => {
                    resultFilter.innerHTML += `<option value="${e.id}">${esc(e.exam_no)} - ${esc(e.exam_name)}</option>`;
                });
            }
        } catch (e) {
            console.error('Error populating filters:', e);
        }
    }

    // ============================================
    // EVENT LISTENERS
    // ============================================

    // Exam Form Submit
    $('examForm').onsubmit = async e => {
        e.preventDefault();
        try {
            syncClassFromSelectedSection();

            const selectedSectionOption = $('sectionId').options[$('sectionId').selectedIndex];
            const derivedClassId = Number(
                $('classId').value
                || selectedSectionOption?.dataset.classId
                || 0
            );

            const r = await request('save_exam', {
                id: Number($('examId').value || 0),
                exam_no: $('examNo').value,
                exam_type_id: Number($('examTypeId').value),
                exam_name: $('examName').value.trim(),
                academic_year_id: Number($('academicYearId').value),
                class_id: derivedClassId || null,
                section_id: $('sectionId').value ? Number($('sectionId').value) : null,
                start_date: $('startDate').value,
                end_date: $('endDate').value,
                exam_status: $('examStatus').value,
                total_marks: parseFloat($('totalMarks').value) || 0,
                passing_marks: parseFloat($('passingMarks').value) || 0,
                description: $('examDescription').value.trim()
            }, 'POST');

            const modal = bootstrap.Modal.getInstance($('examModal'));
            if (modal) modal.hide();
            message(r.message, true);
            await loadExams();
            await loadStats();
            await populateFilterDropdowns();
        } catch (err) {
            message(err.message);
        }
    };

    // Exam Type Form Submit
    $('examTypeForm').onsubmit = async e => {
        e.preventDefault();
        try {
            const r = await request('save_exam_type', {
                id: Number($('examTypeRecordId').value || 0),
                exam_type_code: $('examTypeCode').value.trim().toUpperCase(),
                exam_type_name: $('examTypeName').value.trim(),
                description: $('examTypeDescription').value.trim(),
                weightage: parseFloat($('examTypeWeightage').value) || 0,
                is_active: Number($('examTypeActive').value)
            }, 'POST');

            const modal = bootstrap.Modal.getInstance($('examTypeModal'));
            if (modal) modal.hide();
            message(r.message, true);
            await loadExamTypes();
        } catch (err) {
            message(err.message);
        }
    };

    // Schedule Form Submit
    $('scheduleForm').onsubmit = async e => {
        e.preventDefault();
        try {
            const r = await request('save_schedule', {
                id: Number($('scheduleId').value || 0),
                exam_id: Number($('scheduleExamId').value),
                subject_id: Number($('subjectId').value),
                exam_date: $('examDate').value,
                start_time: $('startTime').value,
                end_time: $('endTime').value,
                room_no: $('roomNo').value.trim(),
                invigilator_id: $('invigilatorId').value ? Number($('invigilatorId').value) : null,
                max_marks: parseFloat($('maxMarks').value) || 100,
                passing_marks: parseFloat($('passingMarksSchedule').value) || 35
            }, 'POST');

            const modal = bootstrap.Modal.getInstance($('scheduleModal'));
            if (modal) modal.hide();
            message(r.message, true);
            await loadSchedule();
        } catch (err) {
            message(err.message);
        }
    };

    // Marks Form Submit
    $('marksForm').onsubmit = async e => {
        e.preventDefault();
        try {
            const formData = new FormData(e.target);
            const marksData = {};
            
            // Process form data
            for (let [key, value] of formData.entries()) {
                if (key.startsWith('marks[')) {
                    const match = key.match(/marks\[(\d+)\]\[(\w+)\]/);
                    if (match) {
                        const studentId = match[1];
                        const field = match[2];
                        if (!marksData[studentId]) marksData[studentId] = {};
                        marksData[studentId][field] = value;
                    }
                }
            }

            const r = await request('save_marks', {
                schedule_id: Number($('marksScheduleId').value),
                marks: marksData
            }, 'POST');

            message(r.message, true);
            
            // Reload marks entry
            await loadMarksEntry(Number($('marksScheduleId').value));
        } catch (err) {
            message(err.message);
        }
    };

    // Button Events
    $('addExamBtn').onclick = () => openExam();
    $('addExamTypeBtn').onclick = () => openExamType();
    $('addScheduleBtn').onclick = () => openSchedule();
    $('refreshBtn').onclick = () => { loadExams(); loadStats(); };
    $('loadScheduleBtn').onclick = () => loadSchedule();
    $('loadResultsBtn').onclick = () => loadResults();
    $('processResultsBtn').onclick = processResults;
    $('publishResultsBtn').onclick = publishResults;
    $('resetBtn').onclick = () => {
        $('search').value = '';
        ['examTypeFilter', 'statusFilter', 'academicYearFilter', 'classFilter'].forEach(id => {
            const el = $(id);
            if (el) el.value = 'all';
        });
        page = 1;
        loadExams();
    };

    // Dynamically load sections from school_sections whenever class or academic year changes.
    $('academicYearId').onchange = () => loadSections();
    $('classId').onchange = () => loadSections();
    $('sectionId').onchange = syncClassFromSelectedSection;

    // Filter changes
    ['examTypeFilter', 'statusFilter', 'academicYearFilter', 'classFilter'].forEach(id => {
        const el = $(id);
        if (el) {
            el.onchange = () => {
                page = 1;
                loadExams();
            };
        }
    });

    // Search with debounce
    $('search').oninput = () => {
        clearTimeout(loadTimer);
        loadTimer = setTimeout(() => {
            page = 1;
            loadExams();
        }, 300);
    };

    // Tab switching
    document.querySelectorAll('.exam-tab').forEach(b => {
        b.onclick = () => {
            document.querySelectorAll('.exam-tab').forEach(x => x.classList.remove('active'));
            document.querySelectorAll('.exam-panel').forEach(x => x.classList.remove('active'));
            b.classList.add('active');
            const panel = document.querySelector(`[data-panel="${b.dataset.tab}"]`);
            if (panel) panel.classList.add('active');
            
            // Load data based on tab
            const tab = b.dataset.tab;
            if (tab === 'exam-types') loadExamTypes();
            else if (tab === 'grade-system') loadGradeSystem();
            else if (tab === 'schedule') populateFilterDropdowns();
            else if (tab === 'results') populateFilterDropdowns();
        };
    });

    // ============================================
    // INITIAL LOAD
    // ============================================
    loadExams();
    loadStats();
    loadExamTypes();
    loadGradeSystem();
    populateFilterDropdowns();
    if (window.lucide) window.lucide.createIcons();

})();
</script>

<?php require dirname(__DIR__) . '/includes/layout-end.php'; ?>
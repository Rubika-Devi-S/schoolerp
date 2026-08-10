<?php
declare(strict_types=1);

$pageTitle = 'Transfer Certificate';
$pageKey = 'transfer_certificate';
$sidebarFile = __DIR__ . '/sidebar.php';

require dirname(__DIR__) . '/includes/layout-start.php';

$csrfToken = function_exists('csrfToken') ? csrfToken() : '';
?>
<style>
.certificate-page{display:grid;gap:16px}
.certificate-page .page-title{font-size:28px;line-height:1.1}
.certificate-page .page-subtitle{margin-top:4px}
.certificate-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.certificate-kpi{border-radius:14px;color:#fff;min-height:112px;padding:18px 20px;display:flex;align-items:center;gap:14px;position:relative;overflow:hidden;box-shadow:0 12px 28px rgba(15,23,42,.08)}
.certificate-kpi::after{content:"";position:absolute;width:110px;height:110px;border-radius:50%;right:-38px;top:-40px;background:rgba(255,255,255,.08)}
.certificate-kpi.purple{background:linear-gradient(135deg,#7448e8,#4b36cf)}
.certificate-kpi.green{background:linear-gradient(135deg,#45c783,#1ea568)}
.certificate-kpi.pink{background:linear-gradient(135deg,#ff527c,#ed2f63)}
.certificate-kpi.orange{background:linear-gradient(135deg,#ffb327,#ff8a17)}
.certificate-kpi-icon{width:50px;height:50px;border-radius:50%;display:grid;place-items:center;background:rgba(255,255,255,.16);flex:0 0 auto}
.certificate-kpi-icon svg{width:25px;height:25px}
.certificate-kpi strong{display:block;font-size:26px;line-height:1}
.certificate-kpi small{display:block;font-size:11px;font-weight:700;opacity:.94;margin-bottom:6px}
.certificate-kpi .trend{font-size:9px;font-weight:700;opacity:.94;margin-top:8px}
.certificate-filter-card{padding:14px;display:grid;grid-template-columns:minmax(250px,1.5fr) repeat(3,minmax(150px,.8fr)) auto;gap:10px}
.certificate-card{border-radius:14px;overflow:hidden}
.certificate-card-head{padding:16px;border-bottom:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;align-items:center;gap:10px}
.certificate-card-head strong{font-size:14px}
.certificate-table-wrap{overflow:auto}
.certificate-table{min-width:1100px}
.certificate-table th{font-size:10px}
.certificate-table td{font-size:11px;vertical-align:middle}
.certificate-student{display:flex;align-items:center;gap:9px}
.certificate-avatar{width:30px;height:30px;border-radius:50%;display:grid;place-items:center;color:#fff;font-size:11px;font-weight:800;background:linear-gradient(135deg,#6d4ce7,#345fe0)}
.certificate-badge{display:inline-flex;align-items:center;padding:5px 9px;border-radius:999px;font-size:9px;font-weight:800;text-transform:capitalize}
.certificate-badge.draft{color:#9a6700;background:#fff7d6}
.certificate-badge.issued{color:#16834f;background:#e8f8ef}
.certificate-badge.cancelled{color:#dc2626;background:#fff0f1}
.certificate-actions{display:flex;gap:6px;flex-wrap:wrap}
.certificate-action{width:30px;height:30px;border:1px solid #d7def1;border-radius:7px;background:var(--card-bg,#fff);display:grid;place-items:center;color:#334155}
.certificate-action svg{width:13px;height:13px}
.certificate-pagination{padding:12px 16px;border-top:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;align-items:center}
.certificate-pagination small{font-size:10px;color:var(--text-muted,#64748b)}
.certificate-message{display:none}.certificate-message.show{display:block}
.certificate-empty{padding:38px 18px;text-align:center;color:var(--text-muted,#64748b)}
.certificate-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
.certificate-grid .full{grid-column:1/-1}
#certificateModal{overflow-y:auto;padding-right:0!important}
#certificateModal .modal-dialog{width:min(1000px,calc(100vw - 24px));max-width:1000px;height:calc(100dvh - 32px);margin:16px auto}
#certificateModal .modal-content{height:100%;overflow:hidden}
#certificateModal #certificateForm{display:flex;flex-direction:column;height:100%;min-height:0}
#certificateModal .modal-header,#certificateModal .modal-footer{flex:0 0 auto}
#certificateModal .modal-body{flex:1 1 auto;min-height:0;overflow-y:auto!important}
.certificate-help{padding:10px 12px;border-radius:9px;background:#eef6ff;color:#31547a;font-size:11px;line-height:1.5}
@media(max-width:1100px){.certificate-filter-card{grid-template-columns:repeat(2,1fr)}}
@media(max-width:900px){.certificate-kpis{grid-template-columns:repeat(2,1fr)}.certificate-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:575px){.certificate-kpis,.certificate-filter-card,.certificate-grid{grid-template-columns:1fr}.certificate-grid .full{grid-column:auto}#certificateModal .modal-dialog{width:100%;height:100dvh;margin:0}#certificateModal .modal-content{border-radius:0}}
</style>

<div class="certificate-page">
    <div class="page-heading">
        <div>
            <h1 class="page-title">Student Certificates</h1>
            <p class="page-subtitle">Dashboard › Students Management › Certificates</p>
        </div>
        <div class="page-actions">
            <button id="addCertificateButton" class="btn-ui btn-primary-ui" type="button">
                <i data-lucide="plus"></i> Create Certificate
            </button>
        </div>
    </div>

    <div id="certificateMessage" class="alert certificate-message"></div>

    <section class="certificate-kpis">
        <article class="certificate-kpi purple">
            <span class="certificate-kpi-icon"><i data-lucide="award"></i></span>
            <div><small>Total Certificates</small><strong id="statTotal">0</strong><div class="trend">All certificate records</div></div>
        </article>
        <article class="certificate-kpi green">
            <span class="certificate-kpi-icon"><i data-lucide="badge-check"></i></span>
            <div><small>Issued</small><strong id="statIssued">0</strong><div class="trend">Completed certificates</div></div>
        </article>
        <article class="certificate-kpi pink">
            <span class="certificate-kpi-icon"><i data-lucide="file-pen-line"></i></span>
            <div><small>Draft</small><strong id="statDraft">0</strong><div class="trend">Pending issue</div></div>
        </article>
        <article class="certificate-kpi orange">
            <span class="certificate-kpi-icon"><i data-lucide="file-x-2"></i></span>
            <div><small>Cancelled</small><strong id="statCancelled">0</strong><div class="trend">Cancelled records</div></div>
        </article>
    </section>

    <section class="ui-card certificate-filter-card">
        <input id="searchFilter" class="form-control" placeholder="Search certificate no., student or admission no...">
        <select id="typeFilter" class="form-select"><option value="all">All Certificate Types</option></select>
        <select id="statusFilter" class="form-select">
            <option value="all">All Statuses</option>
            <option value="draft">Draft</option>
            <option value="issued">Issued</option>
            <option value="cancelled">Cancelled</option>
        </select>
        <select id="yearFilter" class="form-select"><option value="all">All Academic Years</option></select>
        <button id="resetButton" class="btn-ui" type="button"><i data-lucide="rotate-ccw"></i> Reset</button>
    </section>

    <section class="ui-card certificate-card">
        <div class="certificate-card-head">
            <strong id="certificateListTitle">Certificates List</strong>
            <button id="viewAllButton" class="btn btn-link btn-sm p-0" type="button">View All</button>
        </div>
        <div class="certificate-table-wrap">
            <table class="data-table certificate-table">
                <thead>
                    <tr>
                        <th>Certificate No.</th>
                        <th>Student</th>
                        <th>Class</th>
                        <th>Certificate Type</th>
                        <th>Issue Date</th>
                        <th>Purpose</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="certificateBody">
                    <tr><td colspan="8" class="certificate-empty">Loading...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="certificate-pagination">
            <small id="recordCount">Loading...</small>
            <div><button class="btn-ui btn-sm" type="button">1</button></div>
        </div>
    </section>
</div>

<div class="modal fade" id="certificateModal" tabindex="-1">
<div class="modal-dialog modal-xl">
<div class="modal-content">
<form id="certificateForm" novalidate>
    <div class="modal-header">
        <div>
            <h5 id="certificateModalTitle" class="modal-title">Create Certificate</h5>
            <small class="text-muted">Student certificate details</small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
        <input id="certificateId" type="hidden">
        <div class="certificate-grid">
            <div class="full certificate-help">
                Certificate number is generated automatically when the record is saved.
            </div>
            <div>
                <label class="form-label">Academic Year *</label>
                <select id="certificateAcademicYearId" class="form-select" required></select>
            </div>
            <div>
                <label class="form-label">Class *</label>
                <select id="certificateClassId" class="form-select" required disabled>
                    <option value="">Select Class</option>
                </select>
            </div>
            <div>
                <label class="form-label">Section *</label>
                <select id="certificateSectionId" class="form-select" required disabled>
                    <option value="">Select Section</option>
                </select>
            </div>
            <div>
                <label class="form-label">Student *</label>
                <select id="studentId" class="form-select" required disabled>
                    <option value="">Select Student</option>
                </select>
            </div>
            <div>
                <label class="form-label">Certificate Type *</label>
                <select id="certificateType" class="form-select" required></select>
            </div>
            <div>
                <label class="form-label">Issue Date *</label>
                <input id="issueDate" class="form-control" type="date" required>
            </div>
            <div class="full">
                <label class="form-label">Purpose</label>
                <input id="purpose" class="form-control" maxlength="255" placeholder="Scholarship, bank account, passport, higher studies...">
            </div>
            <div class="full" id="conductField">
                <label class="form-label">Conduct / Character</label>
                <input id="conductText" class="form-control" maxlength="255" value="Good">
            </div>
            <div class="full" id="customTitleField" style="display:none">
                <label class="form-label">Custom Certificate Title *</label>
                <input id="customTitle" class="form-control" maxlength="180">
            </div>
            <div class="full" id="customBodyField" style="display:none">
                <label class="form-label">Custom Certificate Body *</label>
                <textarea id="customBody" class="form-control" rows="7" placeholder="You may use: {{student_name}}, {{admission_no}}, {{class_name}}, {{section_name}}, {{academic_year}}, {{school_name}}, {{issue_date}}"></textarea>
            </div>
            <div>
                <label class="form-label">Initial Status</label>
                <select id="certificateStatus" class="form-select">
                    <option value="draft">Draft</option>
                    <option value="issued">Issued</option>
                </select>
            </div>
            <div class="full">
                <label class="form-label">Remarks</label>
                <textarea id="remarks" class="form-control" rows="3"></textarea>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button class="btn-ui" type="button" data-bs-dismiss="modal">Cancel</button>
        <button class="btn-ui btn-primary-ui" type="submit"><i data-lucide="save"></i> Save Certificate</button>
    </div>
</form>
</div>
</div>
</div>

<script>
(function(){
'use strict';

const apiUrl = new URL('../api/student-certificates.php', window.location.href).href;
let csrfToken = <?= json_encode($csrfToken) ?>;
let meta = {};
let permissions = {};
let records = [];
let searchTimer = null;

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
            headers: {'Content-Type': 'application/json', Accept: 'application/json'},
            credentials: 'same-origin',
            body: JSON.stringify({action, csrf_token: csrfToken, ...data})
        });
    }

    const text = await response.text();
    let result;
    try {
        result = JSON.parse(text);
    } catch (error) {
        console.error('Student Certificates API invalid response:', text);
        throw new Error(`Student Certificates API returned HTTP ${response.status}. Invalid server response.`);
    }

    if (!response.ok || !result.success) {
        throw new Error(result.message || 'Request failed.');
    }

    if (result.data?.csrf_token) {
        csrfToken = result.data.csrf_token;
    }

    return result;
}

function message(text, success = false) {
    const box = $('certificateMessage');
    box.className = 'alert certificate-message show ' + (success ? 'alert-success' : 'alert-danger');
    box.textContent = text;
    window.clearTimeout(box._timer);
    box._timer = window.setTimeout(() => {
        box.className = 'alert certificate-message';
    }, 6000);
}

function filters() {
    return {
        search: $('searchFilter').value.trim(),
        certificate_type: $('typeFilter').value,
        status: $('statusFilter').value,
        academic_year_id: $('yearFilter').value
    };
}

function typeLabel(value) {
    return meta.certificate_types?.[value] || value || '-';
}

function setCertificateOptions(elementId, rows, placeholder, selectedValue, labelCallback) {
    const element = $(elementId);
    const selected = String(selectedValue ?? '');

    element.innerHTML = `<option value="">${esc(placeholder)}</option>` + rows.map(row =>
        `<option value="${Number(row.id)}">${esc(labelCallback(row))}</option>`
    ).join('');

    element.value = selected;
}

function refreshCertificateClasses(selectedValue = '') {
    const academicYearId = Number(
        $('certificateAcademicYearId').value || 0
    );

    const rows = (meta.classes || []).filter(row =>
        academicYearId > 0
        && Number(row.academic_year_id) === academicYearId
    );

    setCertificateOptions(
        'certificateClassId',
        rows,
        'Select Class',
        selectedValue,
        row => row.class_name
    );

    $('certificateClassId').disabled = academicYearId <= 0;
}

function refreshCertificateSections(selectedValue = '') {
    const academicYearId = Number(
        $('certificateAcademicYearId').value || 0
    );
    const classId = Number(
        $('certificateClassId').value || 0
    );

    const rows = (meta.sections || []).filter(row =>
        academicYearId > 0
        && classId > 0
        && Number(row.academic_year_id) === academicYearId
        && Number(row.class_id) === classId
    );

    setCertificateOptions(
        'certificateSectionId',
        rows,
        'Select Section',
        selectedValue,
        row => row.section_name
    );

    $('certificateSectionId').disabled =
        academicYearId <= 0 || classId <= 0;
}

function refreshCertificateStudents(selectedValue = '') {
    const academicYearId = Number(
        $('certificateAcademicYearId').value || 0
    );
    const classId = Number(
        $('certificateClassId').value || 0
    );
    const sectionId = Number(
        $('certificateSectionId').value || 0
    );

    const rows = (meta.students || []).filter(row =>
        academicYearId > 0
        && classId > 0
        && sectionId > 0
        && Number(row.academic_year_id) === academicYearId
        && Number(row.class_id) === classId
        && Number(row.section_id) === sectionId
    );

    setCertificateOptions(
        'studentId',
        rows,
        rows.length ? 'Select Student' : 'No students found',
        selectedValue,
        row => {
            const roll = row.roll_no
                ? ` · Roll ${row.roll_no}`
                : '';
            return `${row.student_name} · ${row.admission_no}${roll}`;
        }
    );

    $('studentId').disabled =
        academicYearId <= 0
        || classId <= 0
        || sectionId <= 0
        || rows.length === 0;
}

function renderStats(stats = {}) {
    $('statTotal').textContent = Number(stats.total || 0).toLocaleString();
    $('statIssued').textContent = Number(stats.issued || 0).toLocaleString();
    $('statDraft').textContent = Number(stats.draft || 0).toLocaleString();
    $('statCancelled').textContent = Number(stats.cancelled || 0).toLocaleString();
}

function actionButtons(row) {
    const id = Number(row.id);
    return `<div class="certificate-actions">
        <button class="certificate-action js-view" data-id="${id}" title="View"><i data-lucide="eye"></i></button>
        <a class="certificate-action" href="${apiUrl}?action=print&id=${id}" target="_blank" title="Print"><i data-lucide="printer"></i></a>
        ${permissions.edit && row.status === 'draft' ? `<button class="certificate-action js-edit" data-id="${id}" title="Edit"><i data-lucide="pencil"></i></button>` : ''}
        ${permissions.issue && row.status === 'draft' ? `<button class="certificate-action js-issue" data-id="${id}" title="Issue"><i data-lucide="badge-check"></i></button>` : ''}
        ${permissions.cancel && row.status === 'issued' ? `<button class="certificate-action js-cancel" data-id="${id}" title="Cancel"><i data-lucide="ban"></i></button>` : ''}
        ${permissions.delete && row.status === 'draft' ? `<button class="certificate-action js-delete" data-id="${id}" title="Delete Draft"><i data-lucide="trash-2"></i></button>` : ''}
    </div>`;
}

function render() {
    $('certificateBody').innerHTML = records.map(row => `<tr>
        <td><strong>${esc(row.certificate_no)}</strong></td>
        <td>
            <div class="certificate-student">
                <span class="certificate-avatar">${esc((row.student_name || '?').charAt(0).toUpperCase())}</span>
                <div>
                    <strong>${esc(row.student_name)}</strong>
                    <div class="text-muted">${esc(row.admission_no || '-')}</div>
                </div>
            </div>
        </td>
        <td>${esc(row.class_name || '-')}${row.section_name ? ` - ${esc(row.section_name)}` : ''}<div class="text-muted">${esc(row.academic_year_name || '-')}</div></td>
        <td>${esc(typeLabel(row.certificate_type))}</td>
        <td>${esc(row.issue_date_display || row.issue_date || '-')}</td>
        <td>${esc(row.purpose || '-')}</td>
        <td><span class="certificate-badge ${esc(row.status)}">${esc(row.status)}</span></td>
        <td>${actionButtons(row)}</td>
    </tr>`).join('') || '<tr><td colspan="8" class="certificate-empty">No certificates found.</td></tr>';

    $('certificateListTitle').textContent = `Certificates List (${records.length})`;
    $('recordCount').textContent = `Showing ${records.length} certificate${records.length === 1 ? '' : 's'}`;

    bindActions();
    window.lucide?.createIcons();
}

function enableForm(enabled) {
    document.querySelectorAll('#certificateForm input,#certificateForm select,#certificateForm textarea').forEach(element => {
        element.disabled = !enabled;
    });
    document.querySelector('#certificateForm button[type=submit]').style.display = enabled ? '' : 'none';
}

function toggleCustomFields() {
    const custom = $('certificateType').value === 'custom';
    $('customTitleField').style.display = custom ? '' : 'none';
    $('customBodyField').style.display = custom ? '' : 'none';
    $('customTitle').required = custom;
    $('customBody').required = custom;
}

function openForm(row = null, viewOnly = false) {
    if (!row && !(permissions.create || permissions.add)) {
        message('You do not have permission to create certificates.', false);
        return;
    }

    const form = $('certificateForm');
    form.reset();
    form.classList.remove('was-validated');
    enableForm(true);

    $('certificateId').value = row?.id || '';
    $('certificateModalTitle').textContent = viewOnly
        ? 'View Certificate'
        : (row ? 'Edit Certificate' : 'Create Certificate');

    const defaultYearId = Number(
        row?.academic_year_id
        || meta.current_academic_year_id
        || 0
    );

    $('certificateAcademicYearId').value =
        defaultYearId > 0 ? String(defaultYearId) : '';

    refreshCertificateClasses(row?.class_id || '');
    $('certificateClassId').value = String(row?.class_id || '');

    refreshCertificateSections(row?.section_id || '');
    $('certificateSectionId').value =
        String(row?.section_id || '');

    refreshCertificateStudents(row?.student_id || '');
    $('studentId').value = String(row?.student_id || '');

    $('certificateType').value =
        row?.certificate_type || 'bonafide';
    $('issueDate').value =
        row?.issue_date || new Date().toISOString().slice(0, 10);
    $('purpose').value = row?.purpose || '';
    $('conductText').value = row?.conduct_text || 'Good';
    $('customTitle').value = row?.certificate_title || '';
    $('customBody').value = row?.custom_body || '';
    $('certificateStatus').value =
        row?.status === 'issued' ? 'issued' : 'draft';
    $('remarks').value = row?.remarks || '';

    if (row?.status === 'cancelled') {
        $('certificateStatus').value = 'draft';
    }

    toggleCustomFields();
    enableForm(!viewOnly && (!row || row.status === 'draft'));

    /*
     * enableForm() enables every field. Restore cascade locking so users
     * must select Academic Year -> Class -> Section -> Student in order.
     */
    if (!viewOnly && (!row || row.status === 'draft')) {
        $('certificateClassId').disabled =
            Number($('certificateAcademicYearId').value || 0) <= 0;
        $('certificateSectionId').disabled =
            Number($('certificateClassId').value || 0) <= 0;
        $('studentId').disabled =
            Number($('certificateSectionId').value || 0) <= 0
            || $('studentId').options.length <= 1;
    }

    bootstrap.Modal.getOrCreateInstance(
        $('certificateModal')
    ).show();
}

function bindActions() {
    document.querySelectorAll('.js-view').forEach(button => {
        button.onclick = () => openForm(
            records.find(row => Number(row.id) === Number(button.dataset.id)),
            true
        );
    });

    document.querySelectorAll('.js-edit').forEach(button => {
        button.onclick = () => openForm(
            records.find(row => Number(row.id) === Number(button.dataset.id)),
            false
        );
    });

    document.querySelectorAll('.js-issue').forEach(button => {
        button.onclick = async () => {
            if (!confirm('Issue this certificate now?')) return;
            try {
                const result = await request('issue', {id: Number(button.dataset.id)}, 'POST');
                message(result.message, true);
                await load();
            } catch (error) {
                message(error.message, false);
            }
        };
    });

    document.querySelectorAll('.js-cancel').forEach(button => {
        button.onclick = async () => {
            const reason = prompt('Enter cancellation reason:');
            if (reason === null) return;
            try {
                const result = await request('cancel', {id: Number(button.dataset.id), reason}, 'POST');
                message(result.message, true);
                await load();
            } catch (error) {
                message(error.message, false);
            }
        };
    });

    document.querySelectorAll('.js-delete').forEach(button => {
        button.onclick = async () => {
            if (!confirm('Delete this draft certificate?')) return;
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

    $('certificateAcademicYearId').innerHTML =
        '<option value="">Select Academic Year</option>'
        + (meta.academic_years || []).map(row =>
            `<option value="${Number(row.id)}">${esc(row.year_name)}</option>`
        ).join('');

    $('certificateClassId').innerHTML =
        '<option value="">Select Class</option>';
    $('certificateSectionId').innerHTML =
        '<option value="">Select Section</option>';
    $('studentId').innerHTML =
        '<option value="">Select Student</option>';

    $('certificateClassId').disabled = true;
    $('certificateSectionId').disabled = true;
    $('studentId').disabled = true;

    const typeOptions = Object.entries(
        meta.certificate_types || {}
    );

    $('certificateType').innerHTML = typeOptions.map(
        ([value, label]) =>
            `<option value="${esc(value)}">${esc(label)}</option>`
    ).join('');

    $('typeFilter').innerHTML =
        '<option value="all">All Certificate Types</option>'
        + typeOptions.map(([value, label]) =>
            `<option value="${esc(value)}">${esc(label)}</option>`
        ).join('');

    $('yearFilter').innerHTML =
        '<option value="all">All Academic Years</option>'
        + (meta.academic_years || []).map(row =>
            `<option value="${Number(row.id)}">${esc(row.year_name)}</option>`
        ).join('');

    const canCreate = Boolean(
        permissions.create || permissions.add
    );
    $('addCertificateButton').style.display =
        canCreate ? '' : 'none';
    $('addCertificateButton').disabled = !canCreate;
}

async function load() {
    try {
        const result = await request('list', filters());
        records = result.data.records || [];
        renderStats(result.data.stats || {});
        render();
    } catch (error) {
        message(error.message, false);
    }
}

$('certificateAcademicYearId').addEventListener('change', () => {
    refreshCertificateClasses();
    refreshCertificateSections();
    refreshCertificateStudents();
});

$('certificateClassId').addEventListener('change', () => {
    refreshCertificateSections();
    refreshCertificateStudents();
});

$('certificateSectionId').addEventListener('change', () => {
    refreshCertificateStudents();
});

$('certificateType').addEventListener('change', toggleCustomFields);

$('certificateForm').onsubmit = async event => {
    event.preventDefault();
    const form = event.currentTarget;

    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return;
    }

    const data = {
        id: Number($('certificateId').value || 0),
        academic_year_id: Number(
            $('certificateAcademicYearId').value || 0
        ),
        class_id: Number(
            $('certificateClassId').value || 0
        ),
        section_id: Number(
            $('certificateSectionId').value || 0
        ),
        student_id: Number($('studentId').value || 0),
        certificate_type: $('certificateType').value,
        issue_date: $('issueDate').value,
        purpose: $('purpose').value.trim(),
        conduct_text: $('conductText').value.trim(),
        certificate_title: $('customTitle').value.trim(),
        custom_body: $('customBody').value.trim(),
        status: $('certificateStatus').value,
        remarks: $('remarks').value.trim()
    };

    try {
        const result = await request('save', data, 'POST');
        bootstrap.Modal.getInstance($('certificateModal'))?.hide();
        message(result.message, true);
        await load();
    } catch (error) {
        message(error.message, false);
    }
};

$('searchFilter').addEventListener('input', () => {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(load, 300);
});

['typeFilter', 'statusFilter', 'yearFilter'].forEach(id => {
    $(id).addEventListener('change', load);
});

$('resetButton').onclick = () => {
    $('searchFilter').value = '';
    $('typeFilter').value = 'all';
    $('statusFilter').value = 'all';
    $('yearFilter').value = 'all';
    load();
};

$('addCertificateButton').onclick = () => openForm();

$('viewAllButton').onclick = () => {
    $('searchFilter').value = '';
    $('typeFilter').value = 'all';
    $('statusFilter').value = 'all';
    $('yearFilter').value = 'all';
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

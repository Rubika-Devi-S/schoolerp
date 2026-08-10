<?php
declare(strict_types=1);

$pageTitle = 'Student Promotion';
$pageKey = 'student_promotion';
$sidebarFile = __DIR__ . '/sidebar.php';

require dirname(__DIR__) . '/includes/layout-start.php';

$csrfToken = function_exists('csrfToken') ? csrfToken() : '';
?>
<style>
.promotion-page{display:grid;gap:16px}
.promotion-page .page-title{font-size:28px;line-height:1.1}
.promotion-page .page-subtitle{margin-top:4px}
.promotion-message{display:none}.promotion-message.show{display:block}
.promotion-card{border-radius:14px;overflow:hidden}
.promotion-card-head{padding:16px 18px;border-bottom:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;align-items:center;gap:12px}
.promotion-card-head strong{font-size:15px}
.promotion-form{padding:18px;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;align-items:end}
.promotion-form .form-label{font-size:11px;font-weight:700;color:var(--text-muted,#64748b);margin-bottom:6px}
.promotion-load{grid-column:1/-1;display:flex;justify-content:flex-end}
.promotion-summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;padding:14px 18px;background:var(--soft-bg,#f8fafc);border-bottom:1px solid var(--border-soft,#e7ebf3)}
.promotion-summary-item{display:flex;align-items:center;gap:10px}
.promotion-summary-icon{width:38px;height:38px;border-radius:10px;display:grid;place-items:center;background:#eef2ff;color:#4f46e5}
.promotion-summary-icon svg{width:18px;height:18px}
.promotion-summary small{display:block;color:var(--text-muted,#64748b);font-size:10px;font-weight:700}
.promotion-summary strong{display:block;font-size:20px;line-height:1.1}
.promotion-table-wrap{overflow:auto}
.promotion-table{min-width:780px}
.promotion-table th{font-size:10px;white-space:nowrap}
.promotion-table td{font-size:11px;vertical-align:middle}
.promotion-check{width:17px;height:17px}
.promotion-student{display:flex;align-items:center;gap:9px}
.promotion-avatar{width:32px;height:32px;border-radius:50%;display:grid;place-items:center;color:#fff;font-weight:800;background:linear-gradient(135deg,#6d4ce7,#345fe0)}
.promotion-badge{display:inline-flex;align-items:center;padding:5px 9px;border-radius:999px;font-size:9px;font-weight:800}
.promotion-badge.ready{color:#176b42;background:#e9f8ef}
.promotion-badge.done{color:#6b4f00;background:#fff6d9}
.promotion-empty{padding:42px 18px;text-align:center;color:var(--text-muted,#64748b)}
.promotion-footer{padding:14px 18px;border-top:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
.promotion-footer-note{font-size:10px;color:var(--text-muted,#64748b)}
.promotion-actions{display:flex;gap:10px;align-items:center}
.class-count-note{font-size:10px;color:var(--text-muted,#64748b);margin-top:5px}
@media(max-width:1000px){.promotion-form{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:650px){.promotion-form,.promotion-summary{grid-template-columns:1fr}.promotion-load{grid-column:auto}.promotion-footer{align-items:stretch}.promotion-actions{width:100%;flex-direction:column}.promotion-actions .btn-ui{width:100%;justify-content:center}}
</style>

<div class="promotion-page">
    <div class="page-heading">
        <div>
            <h1 class="page-title">Student Promotion</h1>
            <p class="page-subtitle">Promote students to the next academic year while preserving previous-year history.</p>
        </div>
    </div>

    <div id="promotionMessage" class="alert promotion-message"></div>

    <section class="ui-card promotion-card">
        <div class="promotion-card-head">
            <div>
                <strong>Promotion Details</strong>
                <div class="text-muted small mt-1">Choose the academic years and classes, then load students.</div>
            </div>
            <i data-lucide="graduation-cap"></i>
        </div>

        <div class="promotion-form">
            <div>
                <label class="form-label" for="fromAcademicYear">From Academic Year *</label>
                <select id="fromAcademicYear" class="form-select" required>
                    <option value="">Select From Year</option>
                </select>
            </div>

            <div>
                <label class="form-label" for="toAcademicYear">To Academic Year *</label>
                <select id="toAcademicYear" class="form-select" required>
                    <option value="">Select To Year</option>
                </select>
            </div>

            <div>
                <label class="form-label" for="currentClass">Current Class *</label>
                <select id="currentClass" class="form-select" required disabled>
                    <option value="">Select Current Class</option>
                </select>
                <div id="currentClassHint" class="class-count-note"></div>
            </div>

            <div>
                <label class="form-label" for="targetClass">Promote To Class *</label>
                <select id="targetClass" class="form-select" required disabled>
                    <option value="">Select Promote To Class</option>
                </select>
            </div>

            <div class="promotion-load">
                <button id="loadStudentsButton" class="btn-ui btn-primary-ui" type="button">
                    <i data-lucide="users"></i> Load Students
                </button>
            </div>
        </div>
    </section>

    <section class="ui-card promotion-card">
        <div class="promotion-card-head">
            <div>
                <strong id="studentListTitle">Students</strong>
                <div class="text-muted small mt-1" id="studentListSubtitle">Select promotion details and load students.</div>
            </div>
            <label class="d-flex align-items-center gap-2 mb-0 small fw-semibold" for="selectAllStudents">
                <input id="selectAllStudents" class="form-check-input promotion-check" type="checkbox" disabled>
                Select All
            </label>
        </div>

        <div class="promotion-summary">
            <div class="promotion-summary-item">
                <span class="promotion-summary-icon"><i data-lucide="users-round"></i></span>
                <div><small>Total Students</small><strong id="totalStudents">0</strong></div>
            </div>
            <div class="promotion-summary-item">
                <span class="promotion-summary-icon"><i data-lucide="check-square"></i></span>
                <div><small>Selected</small><strong id="selectedStudents">0</strong></div>
            </div>
            <div class="promotion-summary-item">
                <span class="promotion-summary-icon"><i data-lucide="badge-check"></i></span>
                <div><small>Already Promoted</small><strong id="alreadyPromoted">0</strong></div>
            </div>
        </div>

        <div class="promotion-table-wrap">
            <table class="data-table promotion-table">
                <thead>
                    <tr>
                        <th style="width:48px">Select</th>
                        <th>Admission No</th>
                        <th>Student</th>
                        <th>Roll No</th>
                        <th>Current Class</th>
                        <th>Section</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="promotionStudentBody">
                    <tr><td colspan="7" class="promotion-empty">Select promotion details and click Load Students.</td></tr>
                </tbody>
            </table>
        </div>

        <div class="promotion-footer">
            <div class="promotion-footer-note">
                Previous academic-year enrollment records will remain available as history.
            </div>
            <div class="promotion-actions">
                <button id="clearSelectionButton" class="btn-ui" type="button" disabled>
                    <i data-lucide="x"></i> Clear Selection
                </button>
                <button id="promoteStudentsButton" class="btn-ui btn-primary-ui" type="button" disabled>
                    <i data-lucide="arrow-up-right"></i> Promote Selected Students
                </button>
            </div>
        </div>
    </section>
</div>

<script>
(function(){
'use strict';

const apiUrl = new URL('../api/student-promotion.php', window.location.href).href;
let csrfToken = <?= json_encode($csrfToken) ?>;
let meta = {};
let permissions = {};
let students = [];
let lastLoadMessage = '';

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
        console.error('Student Promotion API invalid response:', text);
        throw new Error(`Student Promotion API returned HTTP ${response.status}. Invalid server response.`);
    }

    if (!response.ok || !result.success) {
        throw new Error(result.message || 'Request failed.');
    }

    if (result.data?.csrf_token) {
        csrfToken = result.data.csrf_token;
    }

    return result;
}

function showMessage(text, success = false) {
    const box = $('promotionMessage');
    box.className = 'alert promotion-message show ' + (success ? 'alert-success' : 'alert-danger');
    box.textContent = text;
    window.clearTimeout(box._timer);
    box._timer = window.setTimeout(() => {
        box.className = 'alert promotion-message';
    }, 7000);
}

function optionLabel(row, labelSource) {
    if (typeof labelSource === 'function') {
        return labelSource(row);
    }
    return row[labelSource];
}

function setOptions(element, rows, placeholder, valueKey, labelSource, selectedValue = '') {
    element.innerHTML = `<option value="">${esc(placeholder)}</option>` + rows.map(row =>
        `<option value="${esc(row[valueKey])}">${esc(optionLabel(row, labelSource))}</option>`
    ).join('');
    element.value = String(selectedValue ?? '');
}

function updateYearDefaults() {
    const years = meta.academic_years || [];
    if (!years.length) return;

    const currentId = Number(meta.current_academic_year_id || 0);
    const currentYear = years.find(row => Number(row.id) === currentId) || years[0];
    $('fromAcademicYear').value = String(currentYear.id);

    const currentStart = Date.parse(currentYear.start_date || '');
    const nextYears = years
        .filter(row => Number(row.id) !== Number(currentYear.id))
        .filter(row => {
            const start = Date.parse(row.start_date || '');
            return Number.isFinite(currentStart) && Number.isFinite(start) && start > currentStart;
        })
        .sort((a, b) => Date.parse(a.start_date) - Date.parse(b.start_date));

    const nextYear = nextYears[0]
        || years.find(row => Number(row.id) !== Number(currentYear.id));
    $('toAcademicYear').value = nextYear ? String(nextYear.id) : '';
}

function selectedClassRow() {
    const id = Number($('currentClass').value || 0);
    return (meta.classes || []).find(row => Number(row.id) === id) || null;
}

function updateCurrentClassHint() {
    const row = selectedClassRow();
    if (!row) {
        $('currentClassHint').textContent = '';
        return;
    }
    const count = Number(row.student_count || 0);
    $('currentClassHint').textContent = `${count} active student${count === 1 ? '' : 's'} available in this class.`;
}

function refreshCurrentClasses() {
    const yearId = Number($('fromAcademicYear').value || 0);
    const rows = (meta.classes || []).filter(row => Number(row.academic_year_id) === yearId);
    const previous = Number($('currentClass').value || 0);
    const previousExists = rows.some(row => Number(row.id) === previous);
    const firstWithStudents = rows.find(row => Number(row.student_count || 0) > 0);
    const selected = previousExists
        ? previous
        : Number(firstWithStudents?.id || rows[0]?.id || 0);

    setOptions(
        $('currentClass'),
        rows,
        'Select Current Class',
        'id',
        row => `${row.class_name} (${Number(row.student_count || 0)} student${Number(row.student_count || 0) === 1 ? '' : 's'})`,
        selected
    );

    $('currentClass').disabled = rows.length === 0;
    updateCurrentClassHint();
    refreshTargetClasses();
}

function refreshTargetClasses() {
    const currentClass = selectedClassRow();
    const currentName = String(currentClass?.class_name || '').toLowerCase();
    const currentRank = Number(
        (meta.class_templates || []).find(row =>
            String(row.class_name || '').toLowerCase() === currentName
        )?.rank || 0
    );

    const rows = (meta.class_templates || []).filter(row =>
        String(row.class_name || '').toLowerCase() !== currentName
    );

    const previous = $('targetClass').value;
    const previousExists = rows.some(row => String(row.class_name) === String(previous));
    const nextClass = rows
        .filter(row => Number(row.rank || 999) > currentRank)
        .sort((a, b) => Number(a.rank || 999) - Number(b.rank || 999))[0];
    const selected = previousExists
        ? previous
        : String(nextClass?.class_name || rows[0]?.class_name || '');

    setOptions(
        $('targetClass'),
        rows,
        'Select Promote To Class',
        'class_name',
        'class_name',
        selected
    );
    $('targetClass').disabled = rows.length === 0;
}

function clearStudents(message = 'Select promotion details and click Load Students.') {
    students = [];
    lastLoadMessage = message;
    $('promotionStudentBody').innerHTML = `<tr><td colspan="7" class="promotion-empty">${esc(message)}</td></tr>`;
    $('studentListTitle').textContent = 'Students';
    $('studentListSubtitle').textContent = 'Select promotion details and load students.';
    $('totalStudents').textContent = '0';
    $('selectedStudents').textContent = '0';
    $('alreadyPromoted').textContent = '0';
    $('selectAllStudents').checked = false;
    $('selectAllStudents').indeterminate = false;
    $('selectAllStudents').disabled = true;
    $('clearSelectionButton').disabled = true;
    $('promoteStudentsButton').disabled = true;
    window.lucide?.createIcons();
}

function selectedIds() {
    return [...document.querySelectorAll('.js-student-check:checked')]
        .map(input => Number(input.value));
}

function updateSelectionState() {
    const selectable = [...document.querySelectorAll('.js-student-check:not(:disabled)')];
    const selected = selectable.filter(input => input.checked);

    $('selectedStudents').textContent = String(selected.length);
    $('selectAllStudents').checked = selectable.length > 0 && selected.length === selectable.length;
    $('selectAllStudents').indeterminate = selected.length > 0 && selected.length < selectable.length;
    $('clearSelectionButton').disabled = selected.length === 0;
    $('promoteStudentsButton').disabled = selected.length === 0 || !permissions.promote;
}

function renderStudents() {
    const currentClass = selectedClassRow();
    const targetClassName = $('targetClass').value || 'selected class';
    const alreadyCount = students.filter(row => Number(row.already_promoted) === 1).length;

    $('promotionStudentBody').innerHTML = students.map(row => {
        const already = Number(row.already_promoted) === 1;
        return `<tr>
            <td><input class="form-check-input promotion-check js-student-check" type="checkbox" value="${Number(row.id)}" ${already ? 'disabled' : ''}></td>
            <td><strong>${esc(row.admission_number)}</strong></td>
            <td><div class="promotion-student"><span class="promotion-avatar">${esc((row.student_name || '?').charAt(0).toUpperCase())}</span><strong>${esc(row.student_name)}</strong></div></td>
            <td>${esc(row.roll_no || '-')}</td>
            <td>${esc(row.class_name || currentClass?.class_name || '-')}</td>
            <td>${esc(row.section_name || 'General')}</td>
            <td>${already
                ? '<span class="promotion-badge done">Already Promoted</span>'
                : `<span class="promotion-badge ready">Ready for ${esc(targetClassName)}</span>`}
            </td>
        </tr>`;
    }).join('') || `<tr><td colspan="7" class="promotion-empty">${esc(lastLoadMessage || 'No active students were found in the selected class.')}</td></tr>`;

    $('studentListTitle').textContent = `Students (${students.length})`;
    $('studentListSubtitle').textContent = currentClass
        ? `${currentClass.class_name} students available for promotion.`
        : 'Students available for promotion.';
    $('totalStudents').textContent = String(students.length);
    $('alreadyPromoted').textContent = String(alreadyCount);
    $('selectedStudents').textContent = '0';

    const selectableCount = students.length - alreadyCount;
    $('selectAllStudents').checked = false;
    $('selectAllStudents').indeterminate = false;
    $('selectAllStudents').disabled = selectableCount === 0;
    $('clearSelectionButton').disabled = true;
    $('promoteStudentsButton').disabled = true;

    document.querySelectorAll('.js-student-check').forEach(input => {
        input.addEventListener('change', updateSelectionState);
    });

    window.lucide?.createIcons();
}

async function loadMeta() {
    const result = await request('meta');
    meta = result.data.meta || {};
    permissions = result.data.permissions || {};
    csrfToken = result.data.csrf_token || csrfToken;

    setOptions($('fromAcademicYear'), meta.academic_years || [], 'Select From Year', 'id', 'year_name');
    setOptions($('toAcademicYear'), meta.academic_years || [], 'Select To Year', 'id', 'year_name');
    updateYearDefaults();
    refreshCurrentClasses();
}

async function loadStudents() {
    const fromYearId = Number($('fromAcademicYear').value || 0);
    const toYearId = Number($('toAcademicYear').value || 0);
    const currentClassId = Number($('currentClass').value || 0);

    if (!fromYearId || !toYearId || !currentClassId || !$('targetClass').value) {
        showMessage('Select From Academic Year, To Academic Year, Current Class and Promote To Class.');
        return;
    }

    if (fromYearId === toYearId) {
        showMessage('From Academic Year and To Academic Year must be different.');
        return;
    }

    const button = $('loadStudentsButton');
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Loading...';

    try {
        const result = await request('students', {
            from_academic_year_id: fromYearId,
            to_academic_year_id: toYearId,
            current_class_id: currentClassId
        });
        students = result.data.students || [];
        lastLoadMessage = result.message || '';
        renderStudents();

        if (!students.length) {
            showMessage(result.message || 'No active students were found in the selected class.');
        }
    } catch (error) {
        clearStudents(error.message);
        showMessage(error.message);
    } finally {
        button.disabled = false;
        button.innerHTML = '<i data-lucide="users"></i> Load Students';
        window.lucide?.createIcons();
    }
}

async function promoteStudents() {
    const ids = selectedIds();
    if (ids.length === 0) {
        showMessage('Select at least one student.');
        return;
    }

    const targetClassName = $('targetClass').value;
    const toYearName = $('toAcademicYear').options[$('toAcademicYear').selectedIndex]?.text || '';

    if (!confirm(`Promote ${ids.length} selected student${ids.length === 1 ? '' : 's'} to ${targetClassName} for ${toYearName}?`)) {
        return;
    }

    const button = $('promoteStudentsButton');
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Promoting...';

    try {
        const result = await request('promote', {
            from_academic_year_id: Number($('fromAcademicYear').value),
            to_academic_year_id: Number($('toAcademicYear').value),
            current_class_id: Number($('currentClass').value),
            target_class_name: targetClassName,
            student_ids: ids
        }, 'POST');

        showMessage(result.message, true);
        await loadMeta();
        await loadStudents();
    } catch (error) {
        showMessage(error.message);
    } finally {
        button.innerHTML = '<i data-lucide="arrow-up-right"></i> Promote Selected Students';
        updateSelectionState();
        window.lucide?.createIcons();
    }
}

$('fromAcademicYear').addEventListener('change', () => {
    refreshCurrentClasses();
    clearStudents();
});
$('toAcademicYear').addEventListener('change', clearStudents);
$('currentClass').addEventListener('change', () => {
    updateCurrentClassHint();
    refreshTargetClasses();
    clearStudents();
});
$('targetClass').addEventListener('change', () => {
    if (students.length) renderStudents();
});
$('loadStudentsButton').addEventListener('click', loadStudents);
$('promoteStudentsButton').addEventListener('click', promoteStudents);
$('selectAllStudents').addEventListener('change', event => {
    document.querySelectorAll('.js-student-check:not(:disabled)').forEach(input => {
        input.checked = event.target.checked;
    });
    updateSelectionState();
});
$('clearSelectionButton').addEventListener('click', () => {
    document.querySelectorAll('.js-student-check').forEach(input => {
        input.checked = false;
    });
    updateSelectionState();
});

(async function init(){
    try {
        await loadMeta();
        window.lucide?.createIcons();
    } catch (error) {
        showMessage(error.message);
    }
})();
})();
</script>

<?php require dirname(__DIR__) . '/includes/layout-end.php'; ?>

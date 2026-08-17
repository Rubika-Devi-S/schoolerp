<?php
declare(strict_types=1);

/* Build: 2026-08-17-student-promotion-all-classes-style-v10 */

$pageTitle = 'Student Promotion';
$pageKey = 'student_promotion';
$sidebarFile = __DIR__ . '/sidebar.php';

require dirname(__DIR__) . '/includes/layout-start.php';
require_once dirname(__DIR__) . '/includes/common-toast.php';

$csrfToken = function_exists('csrfToken') ? csrfToken() : '';
?>
<style>
.promotion-page{display:grid;gap:16px}
.promotion-page .page-title{font-size:28px;line-height:1.1}
.promotion-page .page-subtitle{margin-top:4px}
.promotion-message{display:none}
.promotion-message.show{display:block}

/* Same visual template used by Sections Management. */
.promotion-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.promotion-stat{border:0;border-radius:14px;min-height:112px;padding:18px 20px;display:flex;align-items:center;gap:14px;color:#fff;position:relative;overflow:hidden;box-shadow:0 12px 28px rgba(15,23,42,.08)}
.promotion-stat::after{content:"";position:absolute;width:110px;height:110px;border-radius:50%;right:-38px;top:-40px;background:rgba(255,255,255,.08)}
.promotion-stat.purple{background:linear-gradient(135deg,#7448e8,#4b36cf)}
.promotion-stat.green{background:linear-gradient(135deg,#45c783,#1ea568)}
.promotion-stat.pink{background:linear-gradient(135deg,#ff527c,#ed2f63)}
.promotion-stat.orange{background:linear-gradient(135deg,#ffb327,#ff8a17)}
.promotion-stat-icon{width:50px;height:50px;border-radius:50%;background:rgba(255,255,255,.16);display:grid;place-items:center;flex:0 0 auto}
.promotion-stat-icon svg{width:25px;height:25px}
.promotion-stat strong{display:block;font-size:26px;line-height:1}
.promotion-stat small{display:block;font-size:11px;font-weight:700;opacity:.94;margin-bottom:6px}
.promotion-stat .trend{font-size:9px;font-weight:700;opacity:.94;margin-top:8px}
.promotion-stat .mode-value{font-size:21px}

.promotion-layout{display:grid;grid-template-columns:minmax(0,1fr);gap:16px;align-items:start}
.promotion-card{
    border-radius:14px;
    overflow:hidden;
}
.promotion-card-head{
    min-height:72px;
    padding:16px;
    border-bottom:1px solid var(--border-soft,#e7ebf3);
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
}
.promotion-card-head strong{
    color:var(--text-main,#101b46);
    font-size:14px;
    font-weight:800;
}
.promotion-card-head .head-copy{
    min-width:0;
}

.promotion-form{padding:16px;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;align-items:end}
.promotion-form .form-label{font-size:10px;font-weight:800;color:var(--text-muted,#64748b);margin-bottom:6px}
.promotion-form .form-control,.promotion-form .form-select{font-size:11px}
.promotion-load{grid-column:1/-1;display:flex;justify-content:flex-end;padding-top:2px}
.class-count-note{font-size:9px;color:var(--text-muted,#64748b);margin-top:5px;line-height:1.45}
.promotion-route{font-size:10px;color:var(--text-muted,#64748b);margin-top:5px;font-weight:600}
.promotion-fee-ok{color:#16834f;font-weight:800}.promotion-fee-missing{color:#dc2626;font-weight:800}.promotion-fee-lines{display:flex;flex-wrap:wrap;gap:5px;margin-top:6px}.promotion-fee-chip{display:inline-flex;align-items:center;padding:4px 7px;border-radius:999px;background:#eef2ff;color:#4338ca;font-size:8px;font-weight:800}.promotion-transport-chip{display:inline-flex;align-items:center;padding:4px 7px;border-radius:999px;background:#ecfdf5;color:#047857;font-size:8px;font-weight:800}.promotion-no-transport{color:#64748b;font-size:9px}.promotion-previous-chip{display:inline-flex;align-items:center;padding:4px 7px;border-radius:999px;background:#fff7ed;color:#c2410c;font-size:8px;font-weight:800}.promotion-total-chip{display:inline-flex;align-items:center;padding:4px 7px;border-radius:999px;background:#eefbf3;color:#166534;font-size:8px;font-weight:900}

.promotion-table-wrap{overflow:auto}
.promotion-table{min-width:900px}
.promotion-table th{font-size:10px;white-space:nowrap}
.promotion-table td{font-size:11px;vertical-align:middle}
.promotion-check{width:17px;height:17px}
.promotion-student{display:flex;align-items:center;gap:9px}
.promotion-avatar{width:30px;height:30px;border-radius:50%;display:grid;place-items:center;color:#fff;font-size:11px;font-weight:800;background:linear-gradient(135deg,#6d4ce7,#345fe0)}
.promotion-badge{display:inline-flex;align-items:center;padding:5px 9px;border-radius:999px;font-size:9px;font-weight:800}
.promotion-badge.ready{color:#16834f;background:#e8f8ef}
.promotion-badge.done{color:#9a6700;background:#fff7d6}
.promotion-empty{padding:42px 18px!important;text-align:center!important;color:var(--text-muted,#64748b)}

.promotion-footer{padding:12px 16px;border-top:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
.promotion-footer-note{font-size:10px;color:var(--text-muted,#64748b);max-width:760px;line-height:1.5}
.promotion-actions{display:flex;gap:8px;align-items:center}

@media(max-width:1100px){.promotion-form{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:900px){.promotion-stats{grid-template-columns:repeat(2,1fr)}}
@media(max-width:650px){
    .promotion-stats,.promotion-form{grid-template-columns:1fr}
    .promotion-load{grid-column:auto}
    .promotion-footer{align-items:stretch}
    .promotion-actions{width:100%;flex-direction:column}
    .promotion-actions .btn-ui{width:100%;justify-content:center}
}
</style>

<div class="promotion-page">
    <div class="page-heading">
        <div>
            <h1 class="page-title">Student Promotion</h1>
            <p class="page-subtitle">Dashboard › Student Promotion</p>
        </div>
    </div>

    <div id="promotionMessage" class="alert promotion-message"></div>

    <section class="promotion-stats">
        <article class="promotion-stat purple">
            <span class="promotion-stat-icon"><i data-lucide="users-round"></i></span>
            <div>
                <small>Total Students</small>
                <strong id="totalStudents">0</strong>
                <div class="trend">Students loaded from the selected class</div>
            </div>
        </article>

        <article class="promotion-stat green">
            <span class="promotion-stat-icon"><i data-lucide="check-square"></i></span>
            <div>
                <small>Selected Students</small>
                <strong id="selectedStudents">0</strong>
                <div class="trend">Students selected for promotion</div>
            </div>
        </article>

        <article class="promotion-stat pink">
            <span class="promotion-stat-icon"><i data-lucide="badge-check"></i></span>
            <div>
                <small>Already Promoted</small>
                <strong id="alreadyPromoted">0</strong>
                <div class="trend">Students already available in the target year</div>
            </div>
        </article>

        <article class="promotion-stat orange">
            <span class="promotion-stat-icon"><i data-lucide="graduation-cap"></i></span>
            <div>
                <small>Promotion Mode</small>
                <strong class="mode-value">Year-wise</strong>
                <div class="trend">Class and section based promotion</div>
            </div>
        </article>
    </section>

    <section class="promotion-layout">
        <section class="ui-card promotion-card">
            <div class="promotion-card-head">
                <div class="head-copy">
                    <strong>Promotion Details</strong>
                </div>
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
                    <label class="form-label" for="promotionDate">Promotion Date *</label>
                    <input id="promotionDate" class="form-control" type="date" required>
                </div>

                <div>
                    <label class="form-label" for="currentClass">Current Class *</label>
                    <select id="currentClass" class="form-select" required disabled>
                        <option value="">Select Current Class</option>
                    </select>
                    <div id="currentClassHint" class="class-count-note"></div>
                </div>

                <div>
                    <label class="form-label" for="targetClass">Promote To Class / Section *</label>
                    <select id="targetClass" class="form-select" required disabled>
                        <option value="">Select Promote To Class / Section</option>
                    </select>
                    <div id="targetClassHint" class="class-count-note">Only classes and sections configured for the selected To Academic Year are shown.</div>
                </div>

                <div>
                    <label class="form-label" for="promotionRemarks">Remarks</label>
                    <input id="promotionRemarks" class="form-control" maxlength="500" placeholder="Optional promotion remarks">
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
                <div class="head-copy">
                    <strong id="studentListTitle">Students</strong>
                    <div class="text-muted small mt-1" id="studentListSubtitle">Select promotion details and load students.</div>
                    <div id="routeLabel" class="promotion-route"></div>
                </div>
                <label class="d-flex align-items-center gap-2 mb-0 small fw-semibold" for="selectAllStudents">
                    <input id="selectAllStudents" class="form-check-input promotion-check" type="checkbox" disabled>
                    Select All
                </label>
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
                            <th>Target Fees</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="promotionStudentBody">
                        <tr><td colspan="8" class="promotion-empty">Select promotion details and click Load Students.</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="promotion-footer">
                <div class="promotion-footer-note">
                    Promotion assigns the new Academic Year Fee Structure, carries forward every unpaid previous-year balance, and carries the configured transport fee once as a fixed amount for students currently using transport.
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

const $ = id => document.getElementById(id);
const esc = value => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

const money = value => {
    const amount = Number(value || 0);
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 2
    }).format(amount);
};

function targetFeeItemSummary(target) {
    const items = Array.isArray(target?.fee_items)
        ? target.fee_items
        : [];

    if (!items.length) {
        return '';
    }

    return items
        .map(item =>
            `${item.fee_name}: ${money(item.annual_amount || 0)}`
        )
        .join(' • ');
}

function targetFeeChips(target) {
    const items = Array.isArray(target?.fee_items)
        ? target.fee_items
        : [];

    return items
        .map(item =>
            `<span class="promotion-fee-chip">`
            + `${esc(item.fee_name || 'Fee')} ${esc(money(item.annual_amount || 0))}`
            + `</span>`
        )
        .join('');
}

function localToday() {
    const now = new Date();
    const offset = now.getTimezoneOffset();
    return new Date(now.getTime() - offset * 60000).toISOString().slice(0, 10);
}

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
        throw new Error(text || `Invalid server response (${response.status}).`);
    }

    if (!response.ok || !result.success) {
        throw new Error(result.message || 'Request failed.');
    }

    if (result.data?.csrf_token) {
        csrfToken = result.data.csrf_token;
    }

    return result;
}

function showMessage(text, success = false, type = '') {
    const message = String(text ?? '').trim();
    if (!message) return;

    const toastType = type || (success ? 'success' : 'error');

    if (typeof window.showToast === 'function') {
        window.showToast(toastType, message);
        return;
    }

    const box = $('promotionMessage');
    box.className = 'alert promotion-message show ' + (success ? 'alert-success' : 'alert-danger');
    box.textContent = message;
    window.clearTimeout(box._timer);
    box._timer = window.setTimeout(() => {
        box.className = 'alert promotion-message';
    }, 7000);
}

function setOptions(element, rows, placeholder, selectedValue = '') {
    element.innerHTML = `<option value="">${esc(placeholder)}</option>` + rows.map(row =>
        `<option value="${Number(row.id)}">${esc(row.label || row.class_name || row.year_name || '')}</option>`
    ).join('');
    element.value = String(selectedValue || '');
}

function classIdentity(row) {
    return `${String(row.class_name || '').trim().toLowerCase()}|${String(row.section_name || 'General').trim().toLowerCase()}`;
}

function selectedCurrentClass() {
    const id = Number($('currentClass').value || 0);
    return (meta.classes || []).find(row => Number(row.id) === id) || null;
}

function selectedTargetClass() {
    const id = Number($('targetClass').value || 0);
    return (meta.classes || []).find(row => Number(row.id) === id) || null;
}

function updateYearDefaults() {
    const years = [...(meta.academic_years || [])];
    if (!years.length) return;

    years.sort((a, b) => Date.parse(a.start_date || '1970-01-01') - Date.parse(b.start_date || '1970-01-01'));

    const currentId = Number(meta.current_academic_year_id || 0);
    const currentIndex = years.findIndex(row => Number(row.id) === currentId);

    if (currentIndex > 0) {
        $('fromAcademicYear').value = String(years[currentIndex - 1].id);
        $('toAcademicYear').value = String(years[currentIndex].id);
        return;
    }

    if (currentIndex >= 0 && currentIndex < years.length - 1) {
        $('fromAcademicYear').value = String(years[currentIndex].id);
        $('toAcademicYear').value = String(years[currentIndex + 1].id);
        return;
    }

    if (years.length >= 2) {
        $('fromAcademicYear').value = String(years[years.length - 2].id);
        $('toAcademicYear').value = String(years[years.length - 1].id);
    } else {
        $('fromAcademicYear').value = String(years[0].id);
        $('toAcademicYear').value = '';
    }
}

function refreshCurrentClasses() {
    const yearId = Number(
        $('fromAcademicYear').value || 0
    );

    /*
     * Source promotion is class-wise.
     *
     * Class Management can contain more than one row for the same class
     * because each section has its own management row. Show the class only
     * once and use the class-wide student_count returned by the API.
     */
    const classMap = new Map();

    (meta.classes || [])
        .filter(row =>
            Number(row.academic_year_id) === yearId
            && Number(row.canonical_class_id || 0) > 0
        )
        .sort((a, b) => {
            const order =
                Number(a.display_order || 0)
                - Number(b.display_order || 0);

            if (order !== 0) return order;

            return String(
                a.class_name || ''
            ).localeCompare(
                String(b.class_name || ''),
                undefined,
                {
                    numeric: true,
                    sensitivity: 'base'
                }
            );
        })
        .forEach(row => {
            const key =
                Number(row.canonical_class_id || 0);

            if (!classMap.has(key)) {
                classMap.set(key, row);
                return;
            }

            /*
             * Prefer General as the representative management row when it
             * exists. It changes only the ID sent to the API; the API itself
             * loads the full class across all sections.
             */
            const current = classMap.get(key);
            const currentSection =
                String(
                    current?.section_name || ''
                ).trim().toLowerCase();

            const candidateSection =
                String(
                    row.section_name || ''
                ).trim().toLowerCase();

            if (
                currentSection !== 'general'
                && candidateSection === 'general'
            ) {
                classMap.set(key, row);
            }
        });

    const rows = [...classMap.values()]
        .map(row => {
            const count =
                Number(row.student_count || 0);

            return {
                ...row,
                label:
                    `${row.class_name} `
                    + `(${count} student`
                    + `${count === 1 ? '' : 's'})`
            };
        });

    const previous =
        Number($('currentClass').value || 0);

    const previousRow =
        (meta.classes || []).find(
            row =>
                Number(row.id) === previous
        );

    const previousCanonicalClassId =
        Number(
            previousRow?.canonical_class_id
            || 0
        );

    const previousRepresentative =
        rows.find(
            row =>
                Number(row.canonical_class_id)
                === previousCanonicalClassId
        );

    const firstWithStudents =
        rows.find(
            row =>
                Number(row.student_count || 0) > 0
        );

    const selected =
        Number(
            previousRepresentative?.id
            || firstWithStudents?.id
            || rows[0]?.id
            || 0
        );

    setOptions(
        $('currentClass'),
        rows,
        'Select Current Class',
        selected
    );

    $('currentClass').disabled =
        rows.length === 0;

    updateCurrentClassHint();
    refreshTargetClasses();
}

function updateCurrentClassHint() {
    const row = selectedCurrentClass();
    if (!row) {
        $('currentClassHint').textContent = '';
        return;
    }

    const count =
        Number(row.student_count || 0);

    $('currentClassHint').textContent =
        `${row.class_name} has ${count} active student`
        + `${count === 1 ? '' : 's'} across all sections `
        + `in the selected Academic Year.`;
}

function refreshTargetClasses() {
    const toYearId = Number($('toAcademicYear').value || 0);
    const current = selectedCurrentClass();

    let rows = (meta.classes || [])
        .filter(row =>
            Number(row.academic_year_id) === toYearId
            && Number(row.canonical_class_id || 0) > 0
            && Number(row.canonical_section_id || 0) > 0
        )
        .sort((a, b) => {
            const order = Number(a.display_order || 0) - Number(b.display_order || 0);
            if (order !== 0) return order;
            const name = String(a.class_name || '').localeCompare(String(b.class_name || ''));
            if (name !== 0) return name;
            return String(a.section_name || '').localeCompare(String(b.section_name || ''));
        });

    const previous = Number($('targetClass').value || 0);
    let selected = rows.some(row => Number(row.id) === previous) ? previous : 0;

    if (!selected && current) {
        const currentOrder = Number(current.display_order || 0);
        const currentSection = String(current.section_name || 'General').trim().toLowerCase();

        const nextSameSection = rows.find(row =>
            Number(row.display_order || 0) > currentOrder
            && String(row.section_name || 'General').trim().toLowerCase() === currentSection
        );

        const nextByOrder = rows.find(row => Number(row.display_order || 0) > currentOrder);

        const sameClassDifferentYear = rows.find(row =>
            classIdentity(row) === classIdentity(current)
        );

        selected = Number(nextSameSection?.id || nextByOrder?.id || sameClassDifferentYear?.id || rows[0]?.id || 0);
    }

    rows = rows.map(row => ({
        ...row,
        label: Number(row.fee_structure_id || 0) > 0
            ? `${row.class_name} - ${row.section_name || 'General'} • Fee Total ${money(row.fee_amount || 0)}`
            : `${row.class_name} - ${row.section_name || 'General'} • No Fee Structure`
    }));

    setOptions($('targetClass'), rows, 'Select Promote To Class / Section', selected);
    $('targetClass').disabled = rows.length === 0;

    const target = selectedTargetClass();
    if (!target) {
        $('targetClassHint').className = 'class-count-note';
        $('targetClassHint').textContent =
            'Only classes configured for the selected To Academic Year are shown.';
    } else if (Number(target.fee_structure_id || 0) > 0) {
        $('targetClassHint').className =
            'class-count-note promotion-fee-ok';
        const feeSummary = targetFeeItemSummary(target);
        $('targetClassHint').textContent =
            `Target: ${target.class_name} / ${target.section_name || 'General'} • `
            + `${target.fee_structure_name} • ${money(target.fee_amount || 0)}`
            + (feeSummary ? ` • ${feeSummary}` : '');
    } else {
        $('targetClassHint').className =
            'class-count-note promotion-fee-missing';
        $('targetClassHint').textContent =
            `Target: ${target.class_name} / ${target.section_name || 'General'} • `
            + 'No Active Fee Structure for this Academic Year + Class.';
    }
}

function clearStudents(message = 'Select promotion details and click Load Students.') {
    students = [];
    $('promotionStudentBody').innerHTML = `<tr><td colspan="8" class="promotion-empty">${esc(message)}</td></tr>`;
    $('studentListTitle').textContent = 'Students';
    $('studentListSubtitle').textContent = 'Select the promotion details and load students.';
    $('routeLabel').textContent = '';
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
    const current = selectedCurrentClass();
    const target = selectedTargetClass();
    const alreadyCount = students.filter(row => Number(row.already_promoted) === 1).length;

    $('promotionStudentBody').innerHTML = students.map(row => {
        const already = Number(row.already_promoted) === 1;

        return `<tr>
            <td><input class="form-check-input promotion-check js-student-check" type="checkbox" value="${Number(row.id)}" ${already ? 'disabled' : ''}></td>
            <td><strong>${esc(row.admission_number || '-')}</strong></td>
            <td><div class="promotion-student"><span class="promotion-avatar">${esc((row.student_name || '?').charAt(0).toUpperCase())}</span><strong>${esc(row.student_name || '-')}</strong></div></td>
            <td>${esc(row.roll_no || '-')}</td>
            <td>${esc(current?.class_name || row.class_name || '-')}</td>
            <td>${esc(row.section_name || current?.section_name || 'General')}</td>
            <td>
                <div class="promotion-fee-lines">
                    ${Number(row.previous_year_pending || 0) > 0
                        ? `<span class="promotion-previous-chip">Previous Year Pending ${esc(money(row.previous_year_pending || 0))}</span>`
                        : ''}
                    ${targetFeeChips(target)}
                    ${Number(row.transport_required || 0) === 1 && Number(row.transport_fee_amount || 0) > 0
                        ? `<span class="promotion-transport-chip">Transport Fixed ${esc(money(row.transport_fixed_amount ?? row.transport_fee_amount ?? 0))}</span>`
                        : '<span class="promotion-no-transport">No Transport Fee</span>'}
                    <span class="promotion-total-chip">Combined Total ${esc(money(row.combined_fee_total || 0))}</span>
                </div>
            </td>
            <td>${already
                ? '<span class="promotion-badge done">Already In Target Year</span>'
                : `<span class="promotion-badge ready">Ready for ${esc(target?.class_name || 'Target')} / ${esc(target?.section_name || 'General')}</span>`}
            </td>
        </tr>`;
    }).join('') || '<tr><td colspan="8" class="promotion-empty">No active students were found in the selected Class / Section.</td></tr>';

    $('studentListTitle').textContent = `Students (${students.length})`;
    $('studentListSubtitle').textContent = current
        ? `${current.class_name} students from all sections available for promotion.`
        : 'Students available for promotion.';

    $('routeLabel').textContent =
        current && target
            ? `${current.class_name} → ${target.class_name} / ${target.section_name || 'General'}`
            : '';

    $('totalStudents').textContent = String(students.length);
    $('alreadyPromoted').textContent = String(alreadyCount);
    $('selectedStudents').textContent = '0';

    const selectable = [...document.querySelectorAll('.js-student-check:not(:disabled)')];
    selectable.forEach(input => input.checked = true);

    $('selectAllStudents').disabled = selectable.length === 0;
    $('selectAllStudents').checked = selectable.length > 0;
    $('selectAllStudents').indeterminate = false;

    document.querySelectorAll('.js-student-check').forEach(input => {
        input.addEventListener('change', updateSelectionState);
    });

    updateSelectionState();
    window.lucide?.createIcons();
}

async function loadMeta() {
    const result = await request('meta');
    meta = result.data.meta || {};
    permissions = result.data.permissions || {};
    csrfToken = result.data.csrf_token || csrfToken;

    const years = meta.academic_years || [];
    setOptions(
        $('fromAcademicYear'),
        years.map(row => ({...row, label: row.year_name})),
        'Select From Year'
    );
    setOptions(
        $('toAcademicYear'),
        years.map(row => ({...row, label: row.year_name})),
        'Select To Year'
    );

    updateYearDefaults();
    refreshCurrentClasses();
}

async function loadStudents() {
    const fromYearId = Number($('fromAcademicYear').value || 0);
    const toYearId = Number($('toAcademicYear').value || 0);
    const currentManagementId = Number($('currentClass').value || 0);
    const targetManagementId = Number($('targetClass').value || 0);

    if (!fromYearId || !toYearId || !currentManagementId || !targetManagementId) {
        showMessage('Select From Academic Year, To Academic Year, Current Class / Section and Promote To Class / Section.');
        return;
    }

    if (fromYearId === toYearId) {
        showMessage('From Academic Year and To Academic Year must be different.');
        return;
    }

    const target = selectedTargetClass();
    if (!target || Number(target.fee_structure_id || 0) <= 0) {
        showMessage(
            'No Active Fee Structure exists for the selected To Academic Year and Class. Configure Fee Structure first.'
        );
        return;
    }

    const button = $('loadStudentsButton');
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Loading...';

    try {
        const result = await request('students', {
            from_academic_year_id: fromYearId,
            to_academic_year_id: toYearId,
            current_management_id: currentManagementId,
            target_management_id: targetManagementId
        });

        students = result.data.students || [];
        renderStudents();

        const targetFee = result.data.target_fee_structure || null;
        if (targetFee && Number(targetFee.id || 0) > 0) {
            const feeNames = Array.isArray(targetFee.items)
                ? targetFee.items.map(item =>
                    `${item.fee_name} ${money(item.annual_amount || 0)}`
                ).join(' • ')
                : '';

            $('routeLabel').textContent =
                `Fee Structure: ${targetFee.structure_name} • `
                + `${money(targetFee.annual_amount || 0)} class fees`
                + (feeNames ? ` • ${feeNames}` : '')
                + ' • Previous-year unpaid balances are added automatically'
                + ' • The configured transport fee is carried forward once as a fixed amount for students currently using transport.';
        }

        if (!students.length) {
            showMessage(result.message || 'No active students were found.', false, 'warning');
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
    const studentIds = [...document.querySelectorAll('.js-student-check:checked')]
        .map(input => Number(input.value))
        .filter(Boolean);

    if (!studentIds.length) {
        showMessage('Select at least one student.');
        return;
    }

    const current = selectedCurrentClass();
    const target = selectedTargetClass();

    if (!current || !target) {
        showMessage('Select Current Class and Promote To Class / Section.');
        return;
    }

    if (Number(target.fee_structure_id || 0) <= 0) {
        showMessage(
            'No Active Fee Structure exists for the selected target Academic Year and Class.'
        );
        return;
    }

    if (!confirm(
        `Promote ${studentIds.length} selected student${studentIds.length === 1 ? '' : 's'} from `
        + `${current.class_name} to `
        + `${target.class_name} / ${target.section_name || 'General'}?\n`
        + `Fee Structure: ${target.fee_structure_name} (${money(target.fee_amount || 0)} base class fees per student)\n`
        + `Includes: ${targetFeeItemSummary(target) || 'Configured Fee Structure items'}\n`
        + `Previous Year Pending: automatically carried forward for each student when applicable.\n`
        + `Transport Fee: the configured fixed amount is carried forward once for students who currently have transport.`
    )) {
        return;
    }

    const button = $('promoteStudentsButton');
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Promoting...';

    try {
        const result = await request('promote', {
            from_academic_year_id: Number($('fromAcademicYear').value),
            to_academic_year_id: Number($('toAcademicYear').value),
            current_management_id: Number($('currentClass').value),
            target_management_id: Number($('targetClass').value),
            promotion_date: $('promotionDate').value,
            remarks: $('promotionRemarks').value.trim(),
            student_ids: studentIds
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
$('toAcademicYear').addEventListener('change', () => {
    refreshTargetClasses();
    clearStudents();
});
$('currentClass').addEventListener('change', () => {
    updateCurrentClassHint();
    refreshTargetClasses();
    clearStudents();
});
$('targetClass').addEventListener('change', () => {
    const target = selectedTargetClass();

    if (!target) {
        $('targetClassHint').className = 'class-count-note';
        $('targetClassHint').textContent =
            'Only classes configured for the selected To Academic Year are shown.';
    } else if (Number(target.fee_structure_id || 0) > 0) {
        $('targetClassHint').className =
            'class-count-note promotion-fee-ok';
        const feeSummary = targetFeeItemSummary(target);
        $('targetClassHint').textContent =
            `Target: ${target.class_name} / ${target.section_name || 'General'} • `
            + `${target.fee_structure_name} • ${money(target.fee_amount || 0)}`
            + (feeSummary ? ` • ${feeSummary}` : '');
    } else {
        $('targetClassHint').className =
            'class-count-note promotion-fee-missing';
        $('targetClassHint').textContent =
            `Target: ${target.class_name} / ${target.section_name || 'General'} • `
            + 'No Active Fee Structure for this Academic Year + Class.';
    }

    clearStudents();
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
    $('promotionDate').value = localToday();

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

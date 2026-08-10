<?php
declare(strict_types=1);

$pageTitle = 'Student Admission';
$pageKey = 'student_management';
$sidebarFile = __DIR__ . '/sidebar.php';

require dirname(__DIR__) . '/includes/layout-start.php';

$csrfToken = function_exists('csrfToken') ? csrfToken() : '';
?>

<style>
*{box-sizing:border-box}

.admission-page{
    display:grid;
    gap:16px;
    width:100%;
    min-width:0;
}

.admission-heading{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:16px;
}

.admission-heading h1{
    margin:0;
    font-size:clamp(24px,2vw,30px);
    line-height:1.15;
}

.admission-heading p{
    margin:5px 0 0;
}

.admission-actions{
    display:flex;
    gap:9px;
    flex-wrap:wrap;
}

.admission-alert{
    display:none;
    margin:0;
}

.admission-alert.show{
    display:block;
}

.admission-form{
    display:grid;
    gap:16px;
}

.admission-card{
    border-radius:14px;
    overflow:hidden;
}

.admission-card-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    padding:14px 16px;
    border-bottom:1px solid var(--border-soft,#e7ebf3);
}

.admission-card-head div{
    display:flex;
    align-items:center;
    gap:10px;
}

.admission-card-head span{
    width:34px;
    height:34px;
    border-radius:10px;
    display:grid;
    place-items:center;
    color:#4f46e5;
    background:#eef2ff;
}

.admission-card-head span svg{
    width:17px;
    height:17px;
}

.admission-card-head strong{
    font-size:14px;
}

.admission-card-head small{
    display:block;
    margin-top:2px;
    color:var(--text-muted,#64748b);
}

.admission-card-body{
    padding:16px;
}

.admission-grid{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:13px;
}

.admission-grid>div{
    min-width:0;
}

.admission-grid .span-2{
    grid-column:span 2;
}

.admission-grid .full{
    grid-column:1/-1;
}

.admission-fee-hint{
    display:block;
    margin-top:5px;
}


.admission-number-control{
    display:grid;
    grid-template-columns:minmax(0,1fr) 120px;
    gap:8px;
    align-items:center;
}

.admission-number-control .form-control[readonly]{
    background:rgba(99,102,241,.055);
    color:var(--text-main,#101b46);
    font-weight:750;
    letter-spacing:.02em;
}

.admission-number-mode{
    min-width:0;
}

.admission-number-status{
    display:flex;
    align-items:center;
    gap:6px;
    margin-top:6px;
    min-height:18px;
    font-size:10px;
    color:var(--text-muted,#64748b);
}

.admission-number-status .auto-badge{
    display:inline-flex;
    align-items:center;
    gap:4px;
    padding:3px 7px;
    border-radius:999px;
    background:#ecfdf5;
    color:#047857;
    font-size:9px;
    font-weight:800;
}

.admission-number-status .manual-badge{
    background:#fff7ed;
    color:#c2410c;
}

@media(max-width:575px){
    .admission-number-control{
        grid-template-columns:1fr;
    }
}

.transport-required-row{
    display:grid;
    grid-template-columns:minmax(220px,320px) minmax(0,1fr);
    gap:14px;
    align-items:end;
}

.transport-status{
    min-height:40px;
    border:1px solid var(--border-soft,#e2e8f0);
    border-radius:10px;
    padding:10px 12px;
    background:rgba(99,102,241,.035);
    color:var(--text-muted,#64748b);
    font-size:11px;
    display:flex;
    align-items:center;
}

.transport-fields{
    margin-top:14px;
    padding-top:14px;
    border-top:1px solid var(--border-soft,#e7ebf3);
}

.transport-fields[hidden]{
    display:none!important;
}

.transport-grid{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:13px;
}

.transport-readonly{
    background:rgba(100,116,139,.06)!important;
    font-weight:700;
}

.transport-fee{
    color:#16834f;
    font-size:15px;
}

.admission-footer{
    position:sticky;
    bottom:0;
    z-index:10;
    border-radius:14px;
    padding:13px 16px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    box-shadow:0 -8px 26px rgba(15,23,42,.08);
}

.admission-footer small{
    color:var(--text-muted,#64748b);
}

.admission-footer-actions{
    display:flex;
    gap:9px;
    flex-wrap:wrap;
}

@media(max-width:1100px){
    .admission-grid,
    .transport-grid{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }
}

@media(max-width:767px){
    .admission-heading{
        flex-direction:column;
    }

    .admission-actions{
        width:100%;
    }

    .admission-actions .btn-ui{
        flex:1 1 0;
        justify-content:center;
    }

    .transport-required-row{
        grid-template-columns:1fr;
    }

    .admission-footer{
        align-items:stretch;
        flex-direction:column;
    }

    .admission-footer-actions{
        display:grid;
        grid-template-columns:1fr 1fr;
        width:100%;
    }

    .admission-footer-actions .btn-ui{
        width:100%;
        justify-content:center;
    }
}

@media(max-width:575px){
    .admission-grid,
    .transport-grid{
        grid-template-columns:1fr;
    }

    .admission-grid .span-2,
    .admission-grid .full{
        grid-column:auto;
    }

    .admission-card-body{
        padding:13px;
    }
}
</style>

<div class="admission-page">
    <header class="admission-heading">
        <div>
            <h1>Student Admission</h1>
            <p class="text-muted">
                Add student, academic, parent, fee and transport details.
            </p>
        </div>

        <div class="admission-actions">
            <a class="btn-ui" href="students.php">
                <i data-lucide="arrow-left"></i>
                Students List
            </a>

            <button id="resetAdmissionButton" class="btn-ui" type="button">
                <i data-lucide="rotate-ccw"></i>
                Reset
            </button>
        </div>
    </header>

    <div id="admissionAlert" class="alert admission-alert" role="alert"></div>

    <form id="admissionForm" class="admission-form" novalidate>
        <section class="ui-card admission-card">
            <div class="admission-card-head">
                <div>
                    <span><i data-lucide="graduation-cap"></i></span>
                    <div>
                        <strong>Academic and Fee Details</strong>
                        <small>Select the admission year, class and fee structure.</small>
                    </div>
                </div>
            </div>

            <div class="admission-card-body">
                <div class="admission-grid">
                    <div>
                        <label class="form-label" for="academicYearId">
                            Academic Year *
                        </label>
                        <select id="academicYearId" class="form-select" required></select>
                    </div>

                    <div>
                        <label class="form-label" for="branchId">Branch *</label>
                        <select id="branchId" class="form-select" required></select>
                    </div>

                    <div>
                        <label class="form-label" for="classId">Class *</label>
                        <select id="classId" class="form-select" required></select>
                    </div>

                    <div>
                        <label class="form-label" for="sectionId">Section</label>
                        <select id="sectionId" class="form-select">
                            <option value="">Not Assigned</option>
                        </select>
                    </div>

                    <div class="span-2">
                        <label class="form-label" for="feeStructureId">
                            Fee Structure *
                        </label>
                        <select
                            id="feeStructureId"
                            class="form-select"
                            disabled
                            required
                        >
                            <option value="">Select Academic Year and Class</option>
                        </select>
                        <small id="feeStructureHint" class="text-muted admission-fee-hint">
                            The matching active fee structure is selected automatically.
                        </small>
                    </div>
                </div>
            </div>
        </section>

        <section class="ui-card admission-card">
            <div class="admission-card-head">
                <div>
                    <span><i data-lucide="user-round-plus"></i></span>
                    <div>
                        <strong>Student Details</strong>
                        <small>Enter the student's primary admission information.</small>
                    </div>
                </div>
            </div>

            <div class="admission-card-body">
                <div class="admission-grid">
                    <div>
                        <label class="form-label" for="admissionNumber">
                            Admission Number *
                        </label>

                        <div class="admission-number-control">
                            <input
                                id="admissionNumber"
                                class="form-control"
                                maxlength="50"
                                autocomplete="off"
                                readonly
                                required
                            >

                            <select
                                id="admissionNumberMode"
                                class="form-select admission-number-mode"
                                aria-label="Admission Number method"
                            >
                                <option value="auto">Auto</option>
                                <option value="manual">Manual</option>
                            </select>
                        </div>

                        <div id="admissionNumberHint" class="admission-number-status"></div>
                    </div>

                    <div>
                        <label class="form-label" for="rollNumber">Roll Number</label>
                        <input
                            id="rollNumber"
                            class="form-control"
                            maxlength="40"
                            autocomplete="off"
                        >
                    </div>

                    <div class="span-2">
                        <label class="form-label" for="studentName">Student Name *</label>
                        <input
                            id="studentName"
                            class="form-control"
                            maxlength="150"
                            autocomplete="off"
                            required
                        >
                    </div>

                    <div>
                        <label class="form-label" for="dateOfBirth">Date of Birth *</label>
                        <input id="dateOfBirth" class="form-control" type="date" required>
                    </div>

                    <div>
                        <label class="form-label" for="gender">Gender *</label>
                        <select id="gender" class="form-select" required>
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label" for="bloodGroup">Blood Group</label>
                        <select id="bloodGroup" class="form-select">
                            <option value="">Not Specified</option>
                            <option>A+</option><option>A-</option>
                            <option>B+</option><option>B-</option>
                            <option>AB+</option><option>AB-</option>
                            <option>O+</option><option>O-</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label" for="admissionDate">Admission Date *</label>
                        <input id="admissionDate" class="form-control" type="date" required>
                    </div>

                    <div>
                        <label class="form-label" for="studentStatus">Status *</label>
                        <select id="studentStatus" class="form-select" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="tc">TC / Transferred</option>
                            <option value="alumni">Alumni</option>
                        </select>
                    </div>
                </div>
            </div>
        </section>

        <section class="ui-card admission-card">
            <div class="admission-card-head">
                <div>
                    <span><i data-lucide="users-round"></i></span>
                    <div>
                        <strong>Parent / Guardian Details</strong>
                        <small>Enter the primary contact information.</small>
                    </div>
                </div>
            </div>

            <div class="admission-card-body">
                <div class="admission-grid">
                    <div class="span-2">
                        <label class="form-label" for="parentName">
                            Parent / Guardian Name *
                        </label>
                        <input
                            id="parentName"
                            class="form-control"
                            maxlength="150"
                            autocomplete="off"
                            required
                        >
                    </div>

                    <div>
                        <label class="form-label" for="relationship">Relationship</label>
                        <select id="relationship" class="form-select">
                            <option value="Father">Father</option>
                            <option value="Mother">Mother</option>
                            <option value="Guardian">Guardian</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label" for="mobile">Mobile *</label>
                        <input
                            id="mobile"
                            class="form-control"
                            maxlength="20"
                            autocomplete="tel"
                            required
                        >
                    </div>

                    <div class="span-2">
                        <label class="form-label" for="email">Email</label>
                        <input
                            id="email"
                            class="form-control"
                            type="email"
                            maxlength="190"
                            autocomplete="email"
                        >
                    </div>

                    <div class="span-2">
                        <label class="form-label" for="address">Address</label>
                        <textarea id="address" class="form-control" rows="3"></textarea>
                    </div>
                </div>
            </div>
        </section>

        <section class="ui-card admission-card">
            <div class="admission-card-head">
                <div>
                    <span><i data-lucide="bus-front"></i></span>
                    <div>
                        <strong>Transport Management</strong>
                        <small>Transport fields appear only when transport is required.</small>
                    </div>
                </div>
            </div>

            <div class="admission-card-body">
                <div class="transport-required-row">
                    <div>
                        <label class="form-label" for="transportRequired">
                            Transport Required? *
                        </label>
                        <select id="transportRequired" class="form-select" required>
                            <option value="0">No</option>
                            <option value="1">Yes</option>
                        </select>
                    </div>

                    <div id="transportStatus" class="transport-status">
                        No transport fee will be added.
                    </div>
                </div>

                <div id="transportFields" class="transport-fields" hidden>
                    <div class="transport-grid">
                        <div>
                            <label class="form-label" for="transportRouteId">
                                Transport Route *
                            </label>
                            <select id="transportRouteId" class="form-select" disabled>
                                <option value="">Select Route</option>
                            </select>
                        </div>

                        <div>
                            <label class="form-label" for="transportStopId">
                                Transport Stop *
                            </label>
                            <select id="transportStopId" class="form-select" disabled>
                                <option value="">Select Route First</option>
                            </select>
                        </div>

                        <div>
                            <label class="form-label" for="transportVehicle">
                                Assigned Vehicle
                            </label>
                            <input
                                id="transportVehicle"
                                class="form-control transport-readonly"
                                readonly
                                value="-"
                            >
                        </div>

                        <div>
                            <label class="form-label" for="transportFee">
                                Transport Fee
                            </label>
                            <input
                                id="transportFee"
                                class="form-control transport-readonly transport-fee"
                                readonly
                                value="₹0.00"
                            >
                            <input id="transportFeeAmount" type="hidden" value="0">
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="ui-card admission-card">
            <div class="admission-card-head">
                <div>
                    <span><i data-lucide="notebook-pen"></i></span>
                    <div>
                        <strong>Additional Notes</strong>
                        <small>Optional internal admission notes.</small>
                    </div>
                </div>
            </div>

            <div class="admission-card-body">
                <label class="form-label" for="notes">Notes</label>
                <textarea id="notes" class="form-control" rows="3"></textarea>
            </div>
        </section>

        <section class="ui-card admission-footer">
            <small>
                Fields marked with * are required.
            </small>

            <div class="admission-footer-actions">
                <a class="btn-ui" href="students.php">
                    Cancel
                </a>

                <button id="saveStudentButton" class="btn-ui btn-primary-ui" type="submit">
                    <i data-lucide="save"></i>
                    <span id="saveStudentButtonText">Save Student</span>
                </button>
            </div>
        </section>
    </form>
</div>

<script>
(function(){
'use strict';

const apiUrl = new URL('../api/students.php', window.location.href).href;
let csrfToken = <?=json_encode($csrfToken)?>;
let meta = {};
let permissions = {};

const $ = id => document.getElementById(id);

const esc = value => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

function showMessage(text, success = false) {
    const box = $('admissionAlert');
    box.className =
        'alert admission-alert show ' +
        (success ? 'alert-success' : 'alert-danger');
    box.textContent = text;
    box.scrollIntoView({behavior: 'smooth', block: 'center'});
}

function clearMessage() {
    $('admissionAlert').className = 'alert admission-alert';
    $('admissionAlert').textContent = '';
}

async function request(action, data = {}, method = 'GET') {
    let response;

    if (method === 'GET') {
        const url = new URL(apiUrl);
        url.searchParams.set('action', action);

        Object.entries(data).forEach(([key, value]) => {
            if (value !== '' && value !== null && value !== undefined) {
                url.searchParams.set(key, String(value));
            }
        });

        response = await fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {Accept: 'application/json'}
        });
    } else {
        response = await fetch(apiUrl, {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json'
            },
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
        throw new Error(
            `Students API returned HTTP ${response.status}. Invalid server response.`
        );
    }

    if (!response.ok || !result.success) {
        throw new Error(result.message || 'Student request failed.');
    }

    if (result.data?.csrf_token) {
        csrfToken = result.data.csrf_token;
    }

    return result;
}

function setOptions(
    elementId,
    rows,
    placeholder,
    selectedValue = '',
    labelCallback = null
) {
    const element = $(elementId);
    const selected = String(selectedValue ?? '');

    element.innerHTML =
        `<option value="">${esc(placeholder)}</option>` +
        rows.map(row => {
            const label = labelCallback
                ? labelCallback(row)
                : (row.name ?? row.label ?? row.id);

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

        if (!value || seen.has(value)) {
            return false;
        }

        seen.add(value);
        return true;
    });
}

function money(value) {
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(Number(value || 0));
}

function currentClass() {
    const classId = Number($('classId').value || 0);
    const yearId = Number($('academicYearId').value || 0);

    return (meta.classes || []).find(row =>
        Number(row.id) === classId
        && Number(row.academic_year_id) === yearId
    ) || (meta.classes || []).find(row =>
        Number(row.id) === classId
    ) || null;
}

function refreshClasses(selectedValue = '') {
    const yearId = Number($('academicYearId').value || 0);
    const allRows = [...(meta.classes || [])].sort((a, b) =>
        Number(a.display_order || 0) - Number(b.display_order || 0)
        || String(a.class_name || '').localeCompare(String(b.class_name || ''))
    );

    let rows = allRows.filter(row =>
        !yearId || Number(row.academic_year_id) === yearId
    );

    if (yearId > 0 && rows.length === 0) {
        rows = uniqueByName(allRows, 'class_name');
    }

    rows = uniqueByName(rows, 'class_name');

    setOptions(
        'classId',
        rows,
        rows.length ? 'Select Class' : 'No Classes Available',
        selectedValue,
        row => row.class_name
    );

    $('classId').disabled = rows.length === 0;
}

function refreshSections(selectedValue = '') {
    const yearId = Number($('academicYearId').value || 0);
    const classRow = currentClass();
    const className = normalizeName(classRow?.class_name || '');
    const classId = Number(classRow?.id || 0);

    if (!classRow) {
        setOptions('sectionId', [], 'Not Assigned');
        return;
    }

    const rows = uniqueByName(
        (meta.sections || [])
            .filter(row =>
                Number(row.academic_year_id || 0) === yearId
                && (
                    Number(row.class_id || 0) === classId
                    || normalizeName(row.class_name_snapshot || '') === className
                )
            )
            .sort((a, b) =>
                Number(a.display_order || 0) - Number(b.display_order || 0)
                || String(a.section_name || '').localeCompare(
                    String(b.section_name || '')
                )
            ),
        'section_name'
    );

    setOptions(
        'sectionId',
        rows,
        rows.length ? 'Not Assigned' : 'No Sections Available',
        selectedValue,
        row =>
            `${row.section_name}${row.section_code ? ` (${row.section_code})` : ''}`
    );
}

function refreshFeeStructures(selectedValue = '') {
    const yearId = Number($('academicYearId').value || 0);
    const classRow = currentClass();
    const classId = Number(classRow?.id || 0);
    const className = normalizeName(classRow?.class_name || '');

    const rows = (meta.fee_structures || []).filter(row =>
        String(row.status || '').toLowerCase() === 'active'
        && Number(row.academic_year_id || 0) === yearId
        && (
            Number(row.class_id || 0) === classId
            || normalizeName(row.class_name || '') === className
        )
    );

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

    setOptions(
        'feeStructureId',
        rows,
        classRow
            ? (
                rows.length
                    ? 'Select Fee Structure'
                    : 'No Active Fee Structure'
            )
            : 'Select Class First',
        selected,
        row =>
            `${row.structure_name} · ${money(row.annual_total || row.total_amount || 0)}`
    );

    $('feeStructureId').disabled = !classRow || rows.length === 0;
    $('feeStructureId').required = true;

    const selectedRow = rows.find(
        row => String(row.id) === String($('feeStructureId').value)
    );

    $('feeStructureHint').textContent = selectedRow
        ? `${selectedRow.structure_name}: ${money(selectedRow.annual_total || selectedRow.total_amount || 0)}`
        : (
            classRow
                ? 'Create an active Fee Structure for the selected Academic Year and Class.'
                : 'Select Academic Year and Class to load the Fee Structure.'
        );
}

function activeRoutes() {
    const branchId = Number($('branchId').value || 0);

    return (meta.transport_routes || []).filter(row =>
        String(row.status || '').toLowerCase() === 'active'
        && (
            Number(row.branch_id || 0) === 0
            || Number(row.branch_id || 0) === branchId
        )
    );
}

function selectedRoute() {
    const routeId = Number($('transportRouteId').value || 0);

    return activeRoutes().find(row =>
        Number(row.id) === routeId
    ) || null;
}

function selectedStop() {
    const routeId = Number($('transportRouteId').value || 0);
    const stopId = Number($('transportStopId').value || 0);

    return (meta.transport_stops || []).find(row =>
        Number(row.id) === stopId
        && Number(row.route_id) === routeId
        && String(row.status || '').toLowerCase() === 'active'
    ) || null;
}

function updateTransportDisplay() {
    const required = $('transportRequired').value === '1';
    const route = selectedRoute();
    const stop = selectedStop();

    const vehicle = required && route
        ? (
            route.vehicle_name
                ? `${route.vehicle_name}${route.vehicle_number ? ` (${route.vehicle_number})` : ''}`
                : 'Not Assigned — assign later in Route Master'
        )
        : '-';

    const fee = required && stop
        ? Number(stop.transport_fee || 0)
        : 0;

    $('transportVehicle').value = vehicle;
    $('transportFeeAmount').value = fee.toFixed(2);
    $('transportFee').value = money(fee);

    $('transportStatus').textContent = !required
        ? 'No transport fee will be added.'
        : !route
            ? 'Select a Transport Route.'
            : !stop
                ? 'Select a Transport Stop to load its fee.'
                : (
                    route.vehicle_name
                        ? `${stop.stop_name}: ${money(fee)} will be added automatically.`
                        : `${stop.stop_name}: ${money(fee)} will be added. Vehicle can be assigned later.`
                );
}

function refreshStops(selectedValue = '') {
    const required = $('transportRequired').value === '1';
    const routeId = Number($('transportRouteId').value || 0);

    const rows = (meta.transport_stops || [])
        .filter(row =>
            Number(row.route_id) === routeId
            && String(row.status || '').toLowerCase() === 'active'
        )
        .sort((a, b) =>
            Number(a.stop_order || 0) - Number(b.stop_order || 0)
            || String(a.stop_name || '').localeCompare(String(b.stop_name || ''))
        );

    let selected = String(selectedValue || '');

    if (
        selected
        && !rows.some(row => String(row.id) === selected)
    ) {
        selected = '';
    }

    setOptions(
        'transportStopId',
        rows,
        routeId
            ? (
                rows.length
                    ? 'Select Transport Stop'
                    : 'No Active Stops'
            )
            : 'Select Route First',
        selected,
        row => `${row.stop_name} · ${money(row.transport_fee || 0)}`
    );

    $('transportStopId').disabled =
        !required || routeId <= 0 || rows.length === 0;
    $('transportStopId').required = required;

    updateTransportDisplay();
}

function refreshRoutes(selectedRouteValue = '', selectedStopValue = '') {
    const required = $('transportRequired').value === '1';
    const rows = activeRoutes();

    let selected = String(selectedRouteValue || '');

    if (
        selected
        && !rows.some(row => String(row.id) === selected)
    ) {
        selected = '';
    }

    setOptions(
        'transportRouteId',
        rows,
        rows.length ? 'Select Transport Route' : 'No Active Routes',
        selected,
        row =>
            `${row.route_name}${row.route_code ? ` (${row.route_code})` : ''}`
    );

    $('transportRouteId').disabled = !required || rows.length === 0;
    $('transportRouteId').required = required;

    refreshStops(selectedStopValue);
}

function toggleTransport() {
    const required = $('transportRequired').value === '1';

    $('transportFields').hidden = !required;

    if (!required) {
        $('transportRouteId').value = '';
        $('transportStopId').value = '';
        $('transportRouteId').required = false;
        $('transportStopId').required = false;
        $('transportRouteId').disabled = true;
        $('transportStopId').disabled = true;
        updateTransportDisplay();
        return;
    }

    refreshRoutes();
}

function admissionPrefix(){
    return String(
        meta?.general_settings?.admission_number_prefix
        || 'ADM'
    ).trim() || 'ADM';
}

function nextAdmissionPreview(){
    return String(meta?.next_admission_number || `${admissionPrefix()}0001`);
}

function refreshAdmissionNumberMode(resetValue=false){
    const mode=$('admissionNumberMode').value==='manual'?'manual':'auto';
    const input=$('admissionNumber');
    const hint=$('admissionNumberHint');
    const prefix=admissionPrefix();

    if(mode==='auto'){
        input.readOnly=true;
        input.required=true;
        input.classList.remove('is-invalid');
        input.value=nextAdmissionPreview();
        input.placeholder=nextAdmissionPreview();
        hint.innerHTML=`<span class="auto-badge">Auto</span><span>Generated from General Settings prefix <strong>${prefix}</strong>. Final unique number is reserved when saved.</span>`;
        return;
    }

    input.readOnly=false;
    input.required=true;
    if(resetValue || input.value===nextAdmissionPreview()){
        input.value='';
    }
    input.placeholder=`Enter admission number, e.g. ${prefix}1001`;
    hint.innerHTML='<span class="auto-badge manual-badge">Manual</span><span>Enter a custom unique Admission Number. Switch back to Auto anytime.</span>';
    window.setTimeout(()=>input.focus(),0);
}

function resetForm() {
    $('admissionForm').reset();
    clearMessage();

    $('academicYearId').value =
        String(meta.current_academic_year_id || '');
    $('branchId').value =
        String(meta.current_branch_id || '');

    refreshClasses();
    refreshSections();
    refreshFeeStructures();

    $('studentStatus').value = 'active';
    $('relationship').value = 'Father';
    $('transportRequired').value = '0';
    $('admissionDate').value =
        new Date().toISOString().slice(0, 10);

    toggleTransport();
    $('admissionNumberMode').value = 'auto';
    refreshAdmissionNumberMode(true);
    $('studentName').focus();
}

async function loadMeta() {
    const result = await request('meta');

    meta = result.data.meta || {};
    permissions = result.data.permissions || {};
    csrfToken = result.data.csrf_token || csrfToken;

    if (!permissions.add && !permissions.create) {
        throw new Error(
            'You do not have permission to add students.'
        );
    }

    setOptions(
        'academicYearId',
        meta.academic_years || [],
        'Select Academic Year',
        meta.current_academic_year_id || '',
        row => row.year_name
    );

    setOptions(
        'branchId',
        meta.branches || [],
        'Select Branch',
        meta.current_branch_id || '',
        row => row.branch_name
    );

    resetForm();
}

function buildPayload() {
    const year = $('academicYearId');
    const branch = $('branchId');
    const classSelect = $('classId');
    const section = $('sectionId');

    return {
        id: 0,
        academic_year_id: Number(year.value || 0),
        academic_year_name:
            year.options[year.selectedIndex]?.text || '',
        branch_id: Number(branch.value || 0),
        branch_name:
            branch.options[branch.selectedIndex]?.text || '',
        class_id: Number(classSelect.value || 0),
        class_name:
            classSelect.options[classSelect.selectedIndex]?.text || '',
        section_id: Number(section.value || 0),
        section_name:
            section.options[section.selectedIndex]?.text || '',
        fee_structure_id:
            Number($('feeStructureId').value || 0),
        transport_required:
            Number($('transportRequired').value || 0),
        transport_route_id:
            Number($('transportRouteId').value || 0),
        transport_stop_id:
            Number($('transportStopId').value || 0),
        admission_number_mode:
            $('admissionNumberMode').value,
        /*
         * Always send the visible Admission Number.
         *
         * Auto mode previously sent an empty string even though the field
         * displayed ADM0001. Any required-value validation therefore failed
         * with "Missing required value(s): admission_number."
         *
         * The API still reserves the final unique sequence during save when
         * admission_number_mode === 'auto'.
         */
        admission_number:
            $('admissionNumber').value.trim(),
        admission_number_auto:
            $('admissionNumberMode').value === 'auto' ? 1 : 0,
        roll_number:
            $('rollNumber').value.trim(),
        student_name:
            $('studentName').value.trim(),
        date_of_birth:
            $('dateOfBirth').value,
        gender:
            $('gender').value,
        blood_group:
            $('bloodGroup').value,
        admission_date:
            $('admissionDate').value,
        status:
            $('studentStatus').value,
        parent_name:
            $('parentName').value.trim(),
        relationship:
            $('relationship').value,
        mobile:
            $('mobile').value.trim(),
        email:
            $('email').value.trim(),
        address:
            $('address').value.trim(),
        notes:
            $('notes').value.trim()
    };
}

async function saveStudent(event) {
    event.preventDefault();
    clearMessage();

    const form = event.currentTarget;

    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        showMessage('Complete all required fields.', false);
        return;
    }

    const data = buildPayload();

    if (data.admission_number === '') {
        if (data.admission_number_mode === 'auto') {
            /*
             * Rebuild the preview once if metadata was refreshed or the input
             * was unexpectedly cleared before submission.
             */
            applyAdmissionNumberMode();
            data.admission_number = $('admissionNumber').value.trim();
        }

        if (data.admission_number === '') {
            showMessage(
                data.admission_number_mode === 'manual'
                    ? 'Enter the manual Admission Number or change the method to Auto.'
                    : 'Unable to generate the Admission Number. Refresh General Settings and try again.',
                false
            );
            $('admissionNumber').focus();
            return;
        }
    }

    if (data.fee_structure_id <= 0) {
        showMessage(
            'Select a valid Fee Structure.',
            false
        );
        return;
    }

    if (
        data.transport_required === 1
        && data.transport_route_id <= 0
    ) {
        showMessage('Select a Transport Route.', false);
        return;
    }

    if (
        data.transport_required === 1
        && data.transport_stop_id <= 0
    ) {
        showMessage('Select a Transport Stop.', false);
        return;
    }

    const button = $('saveStudentButton');
    button.disabled = true;
    $('saveStudentButtonText').textContent = 'Saving...';

    try {
        const result = await request('save', data, 'POST');
        const admissionNo = result.data?.admission_number || '';
        showMessage(admissionNo ? `${result.message} Admission No: ${admissionNo}` : result.message, true);

        window.setTimeout(() => {
            window.location.href = 'students.php';
        }, 700);
    } catch (error) {
        showMessage(error.message, false);
    } finally {
        button.disabled = false;
        $('saveStudentButtonText').textContent = 'Save Student';
    }
}

$('admissionNumberMode').addEventListener('change', () => {
    refreshAdmissionNumberMode(true);
});

$('admissionNumber').addEventListener('input', () => {
    if ($('admissionNumberMode').value === 'manual') {
        $('admissionNumber').value = $('admissionNumber').value.toUpperCase();
    }
});

$('academicYearId').addEventListener('change', () => {
    $('classId').value = '';
    $('sectionId').value = '';
    $('feeStructureId').value = '';
    refreshClasses();
    refreshSections();
    refreshFeeStructures();
});

$('branchId').addEventListener('change', () => {
    $('transportRouteId').value = '';
    $('transportStopId').value = '';

    if ($('transportRequired').value === '1') {
        refreshRoutes();
    }
});

$('classId').addEventListener('change', () => {
    $('sectionId').value = '';
    $('feeStructureId').value = '';
    refreshSections();
    refreshFeeStructures();
});

$('feeStructureId').addEventListener('change', () => {
    refreshFeeStructures($('feeStructureId').value);
});

$('transportRequired').addEventListener('change', toggleTransport);

$('transportRouteId').addEventListener('change', () => {
    $('transportStopId').value = '';
    refreshStops();
});

$('transportStopId').addEventListener('change', updateTransportDisplay);

$('resetAdmissionButton').addEventListener('click', resetForm);
$('admissionForm').addEventListener('submit', saveStudent);

loadMeta().catch(error => {
    showMessage(error.message, false);
    $('saveStudentButton').disabled = true;
});

window.lucide?.createIcons();
})();
</script>

<?php require dirname(__DIR__) . '/includes/layout-end.php'; ?>

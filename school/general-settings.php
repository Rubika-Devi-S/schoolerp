<?php
declare(strict_types=1);

$pageTitle = 'General Settings';
$pageKey = 'general_settings';

require dirname(__DIR__) . '/includes/layout-start.php';
require_once dirname(__DIR__) . '/includes/common-toast.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (
    empty($_SESSION['general_settings_csrf'])
    || !is_string($_SESSION['general_settings_csrf'])
) {
    $_SESSION['general_settings_csrf'] = bin2hex(random_bytes(32));
}

$generalSettingsCsrf = $_SESSION['general_settings_csrf'];
?>

<style>
*{box-sizing:border-box}

.gs-page{
    display:grid;
    gap:16px;
    width:100%;
    min-width:0;
}

.gs-head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:16px;
}

.gs-head>div:first-child{
    min-width:0;
}

.gs-head h1{
    margin:0;
    font-size:clamp(24px,2vw,30px);
}

.gs-head p{
    margin:5px 0 0;
}

.gs-head-actions{
    display:flex;
    gap:9px;
    flex-wrap:wrap;
}

.gs-head-actions .btn-ui{
    min-height:40px;
    white-space:nowrap;
}

.gs-message{
    display:none;
    margin:0;
}

.gs-message.show{
    display:block;
}

.gs-card{
    border-radius:14px;
    overflow:hidden;
    min-width:0;
}

.gs-card-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    padding:14px 16px;
    border-bottom:1px solid var(--border-soft,#e7ebf3);
}

.gs-card-title{
    display:flex;
    align-items:center;
    gap:10px;
    min-width:0;
}

.gs-card-icon{
    width:36px;
    height:36px;
    border-radius:10px;
    display:grid;
    place-items:center;
    color:#4f46e5;
    background:#eef2ff;
    flex:0 0 auto;
}

.gs-card-icon svg{
    width:18px;
}

.gs-card-title strong{
    display:block;
    font-size:13px;
}

.gs-card-title small{
    display:block;
    margin-top:2px;
    color:var(--text-muted,#64748b);
}

.gs-card-body{
    padding:16px;
}

.gs-form{
    display:grid;
    gap:16px;
}

.gs-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:14px;
}

.gs-grid>div{
    min-width:0;
}

.gs-grid .full{
    grid-column:1/-1;
}

.gs-grid .form-control,
.gs-grid .form-select{
    width:100%;
    min-width:0;
    min-height:42px;
}

.gs-setting-hint{
    display:block;
    margin-top:5px;
    color:var(--text-muted,#64748b);
    font-size:10px;
    line-height:1.5;
}

.gs-switch-row{
    min-height:82px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:16px;
    padding:14px;
    border:1px solid var(--border-soft,#e7ebf3);
    border-radius:12px;
    background:rgba(99,102,241,.035);
}

.gs-switch-copy{
    min-width:0;
}

.gs-switch-copy strong{
    display:block;
    font-size:12px;
}

.gs-switch-copy small{
    display:block;
    margin-top:4px;
    color:var(--text-muted,#64748b);
    line-height:1.5;
}

.gs-switch{
    position:relative;
    width:54px;
    height:30px;
    flex:0 0 auto;
}

.gs-switch input{
    position:absolute;
    opacity:0;
    pointer-events:none;
}

.gs-switch-slider{
    position:absolute;
    inset:0;
    border-radius:999px;
    background:#cbd5e1;
    cursor:pointer;
    transition:.2s;
}

.gs-switch-slider:before{
    content:"";
    position:absolute;
    width:24px;
    height:24px;
    left:3px;
    top:3px;
    border-radius:50%;
    background:#fff;
    box-shadow:0 2px 6px rgba(15,23,42,.2);
    transition:.2s;
}

.gs-switch input:checked + .gs-switch-slider{
    background:#4f46e5;
}

.gs-switch input:checked + .gs-switch-slider:before{
    transform:translateX(24px);
}

.gs-summary{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:10px;
}

.gs-summary-item{
    min-width:0;
    padding:13px;
    border:1px solid var(--border-soft,#e7ebf3);
    border-radius:11px;
    background:rgba(99,102,241,.035);
}

.gs-summary-item small{
    display:block;
    font-size:9px;
    color:var(--text-muted,#64748b);
}

.gs-summary-item strong{
    display:block;
    margin-top:5px;
    font-size:12px;
    overflow-wrap:anywhere;
}

.gs-footer{
    position:sticky;
    bottom:0;
    z-index:15;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    padding:13px 16px;
    border-radius:14px;
    box-shadow:0 -8px 26px rgba(15,23,42,.08);
}

.gs-footer-actions{
    display:flex;
    gap:9px;
    flex-wrap:wrap;
}

.gs-footer-actions .btn-ui{
    min-height:42px;
}


.gs-shifts{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
.gs-shift-card{border:1px solid var(--border-soft,#e7ebf3);border-radius:14px;padding:15px;background:linear-gradient(180deg,rgba(99,102,241,.035),transparent);transition:.18s ease}
.gs-shift-card.enabled{border-color:rgba(79,70,229,.3);box-shadow:0 8px 22px rgba(79,70,229,.08)}
.gs-shift-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:13px}
.gs-shift-title{display:flex;align-items:center;gap:9px;min-width:0}
.gs-shift-icon{width:34px;height:34px;border-radius:10px;display:grid;place-items:center;background:#eef2ff;color:#4f46e5}
.gs-shift-icon svg{width:17px;height:17px}
.gs-shift-title strong{display:block;font-size:12px}.gs-shift-title small{display:block;margin-top:2px;color:var(--text-muted,#64748b);font-size:9px}
.gs-shift-times{display:grid;grid-template-columns:1fr 1fr;gap:10px}.gs-shift-times label{font-size:10px;font-weight:750;margin-bottom:5px}.gs-shift-preview{margin-top:11px;padding:9px 10px;border-radius:9px;background:rgba(15,23,42,.035);font-size:10px;color:var(--text-muted,#64748b)}.gs-shift-preview strong{color:var(--text-main,#0f172a)}
@media(max-width:1050px){.gs-shifts{grid-template-columns:1fr}}
@media(max-width:575px){.gs-shift-times{grid-template-columns:1fr}}

@media(min-width:1500px){
    .gs-grid{
        grid-template-columns:repeat(3,minmax(0,1fr));
    }

    .gs-grid .full{
        grid-column:1/-1;
    }
}

@media(max-width:900px){
    .gs-head{
        flex-direction:column;
        align-items:stretch;
    }

    .gs-head-actions{
        justify-content:flex-start;
    }

    .gs-summary{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }
}

@media(max-width:767px){
    .gs-grid{
        grid-template-columns:1fr;
    }

    .gs-grid .full{
        grid-column:auto;
    }

    .gs-head-actions{
        display:grid;
        grid-template-columns:1fr;
        width:100%;
    }

    .gs-head-actions .btn-ui{
        width:100%;
        justify-content:center;
    }

    .gs-summary{
        grid-template-columns:1fr;
    }

    .gs-footer{
        flex-direction:column;
        align-items:stretch;
    }

    .gs-footer-actions{
        display:grid;
        grid-template-columns:1fr 1fr;
        width:100%;
    }

    .gs-footer-actions .btn-ui{
        width:100%;
        justify-content:center;
    }
}

@media(max-width:480px){
    .gs-card-body{
        padding:13px;
    }

    .gs-footer-actions{
        grid-template-columns:1fr;
    }

    .gs-switch-row{
        align-items:flex-start;
    }
}
</style>

<div class="gs-page">
    <div class="gs-head">
        <div>
            <h1>General Settings</h1>
            <p class="text-muted">
                Configure the basic academic, date, time, currency and numbering preferences.
            </p>
        </div>

        <div class="gs-head-actions">
            <button id="refreshSettingsBtn" class="btn-ui" type="button">
                <i data-lucide="refresh-cw"></i>
                Refresh
            </button>
        </div>
    </div>

    <div id="settingsMessage" class="alert gs-message" role="alert"></div>

    <form id="generalSettingsForm" class="gs-form" novalidate>
        <section class="ui-card gs-card">
            <div class="gs-card-head">
                <div class="gs-card-title">
                    <span class="gs-card-icon">
                        <i data-lucide="settings-2"></i>
                    </span>

                    <div>
                        <strong>School Preferences</strong>
                        <small>These settings apply across the School ERP.</small>
                    </div>
                </div>
            </div>

            <div class="gs-card-body">
                <div class="gs-grid">
                    <div>
                        <label class="form-label" for="academicYearId">
                            Academic Year *
                        </label>

                        <select
                            id="academicYearId"
                            class="form-select"
                            required
                        >
                            <option value="">Select Academic Year</option>
                        </select>

                        <small class="gs-setting-hint">
                            Used as the default academic year for school operations.
                        </small>
                    </div>

                    <div>
                        <label class="form-label" for="schoolStartTime">
                            School Start Time *
                        </label>

                        <input
                            id="schoolStartTime"
                            class="form-control"
                            type="time"
                            required
                        >
                    </div>

                    <div>
                        <label class="form-label" for="schoolEndTime">
                            School End Time *
                        </label>

                        <input
                            id="schoolEndTime"
                            class="form-control"
                            type="time"
                            required
                        >
                    </div>

                    <div>
                        <label class="form-label" for="dateFormat">
                            Date Format *
                        </label>

                        <select
                            id="dateFormat"
                            class="form-select"
                            required
                        >
                            <option value="d-m-Y">DD-MM-YYYY</option>
                            <option value="d/m/Y">DD/MM/YYYY</option>
                            <option value="Y-m-d">YYYY-MM-DD</option>
                            <option value="m/d/Y">MM/DD/YYYY</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label" for="timeFormat">
                            Time Format *
                        </label>

                        <select
                            id="timeFormat"
                            class="form-select"
                            required
                        >
                            <option value="12">12 Hour</option>
                            <option value="24">24 Hour</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label" for="languageCode">
                            Language *
                        </label>

                        <select
                            id="languageCode"
                            class="form-select"
                            required
                        >
                            <option value="en">English</option>
                            <option value="ta">Tamil</option>
                            <option value="hi">Hindi</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label" for="currencyCode">
                            Currency *
                        </label>

                        <select
                            id="currencyCode"
                            class="form-select"
                            required
                        >
                            <option value="INR">₹ INR - Indian Rupee</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label" for="admissionPrefix">
                            Admission Number Prefix *
                        </label>

                        <input
                            id="admissionPrefix"
                            class="form-control"
                            maxlength="30"
                            placeholder="ADM"
                            required
                        >

                        <small class="gs-setting-hint">
                            Example: ADM0001, ADM0002
                        </small>
                    </div>

                    <div>
                        <label class="form-label" for="receiptPrefix">
                            Receipt Number Prefix *
                        </label>

                        <input
                            id="receiptPrefix"
                            class="form-control"
                            maxlength="30"
                            placeholder="RCP"
                            required
                        >

                        <small class="gs-setting-hint">
                            Example: RCP0001, RCP0002
                        </small>
                    </div>

                    <div class="full">
                        <div class="gs-switch-row">
                            <div class="gs-switch-copy">
                                <strong>Maintenance Mode</strong>
                                <small>
                                    When enabled, normal users can be restricted while administrators perform maintenance.
                                </small>
                            </div>

                            <label class="gs-switch">
                                <input
                                    id="maintenanceMode"
                                    type="checkbox"
                                    value="1"
                                >
                                <span class="gs-switch-slider"></span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="ui-card gs-card">
            <div class="gs-card-head">
                <div class="gs-card-title">
                    <span class="gs-card-icon"><i data-lucide="clock-3"></i></span>
                    <div>
                        <strong>Shift Time Configuration</strong>
                        <small>Enable only the shifts used by this school. Classes will show only enabled shifts.</small>
                    </div>
                </div>
            </div>
            <div class="gs-card-body">
                <div class="gs-shifts">
                    <article class="gs-shift-card" data-shift-card="morning">
                        <div class="gs-shift-head">
                            <div class="gs-shift-title"><span class="gs-shift-icon"><i data-lucide="sunrise"></i></span><span><strong>Morning</strong><small>Morning shift timing</small></span></div>
                            <label class="gs-switch"><input id="shiftMorningEnabled" type="checkbox"><span class="gs-switch-slider"></span></label>
                        </div>
                        <div class="gs-shift-times">
                            <div><label class="form-label" for="shiftMorningStart">Start Time</label><input id="shiftMorningStart" class="form-control" type="time"></div>
                            <div><label class="form-label" for="shiftMorningEnd">End Time</label><input id="shiftMorningEnd" class="form-control" type="time"></div>
                        </div>
                        <div class="gs-shift-preview">Preview: <strong id="shiftMorningPreview">Disabled</strong></div>
                    </article>
                    <article class="gs-shift-card" data-shift-card="general">
                        <div class="gs-shift-head">
                            <div class="gs-shift-title"><span class="gs-shift-icon"><i data-lucide="school"></i></span><span><strong>General</strong><small>Default school shift</small></span></div>
                            <label class="gs-switch"><input id="shiftGeneralEnabled" type="checkbox"><span class="gs-switch-slider"></span></label>
                        </div>
                        <div class="gs-shift-times">
                            <div><label class="form-label" for="shiftGeneralStart">Start Time</label><input id="shiftGeneralStart" class="form-control" type="time"></div>
                            <div><label class="form-label" for="shiftGeneralEnd">End Time</label><input id="shiftGeneralEnd" class="form-control" type="time"></div>
                        </div>
                        <div class="gs-shift-preview">Preview: <strong id="shiftGeneralPreview">-</strong></div>
                    </article>
                    <article class="gs-shift-card" data-shift-card="evening">
                        <div class="gs-shift-head">
                            <div class="gs-shift-title"><span class="gs-shift-icon"><i data-lucide="sunset"></i></span><span><strong>Evening</strong><small>Evening shift timing</small></span></div>
                            <label class="gs-switch"><input id="shiftEveningEnabled" type="checkbox"><span class="gs-switch-slider"></span></label>
                        </div>
                        <div class="gs-shift-times">
                            <div><label class="form-label" for="shiftEveningStart">Start Time</label><input id="shiftEveningStart" class="form-control" type="time"></div>
                            <div><label class="form-label" for="shiftEveningEnd">End Time</label><input id="shiftEveningEnd" class="form-control" type="time"></div>
                        </div>
                        <div class="gs-shift-preview">Preview: <strong id="shiftEveningPreview">Disabled</strong></div>
                    </article>
                </div>
                <small class="gs-setting-hint mt-3 d-block">General is enabled by default using School Start/End Time. Morning and Evening appear in Classes only after they are enabled and saved.</small>
            </div>
        </section>

        <section class="ui-card gs-card">
            <div class="gs-card-head">
                <div class="gs-card-title">
                    <span class="gs-card-icon">
                        <i data-lucide="eye"></i>
                    </span>

                    <div>
                        <strong>Current Settings Preview</strong>
                        <small>Updates automatically when fields change.</small>
                    </div>
                </div>
            </div>

            <div class="gs-card-body">
                <div class="gs-summary">
                    <div class="gs-summary-item">
                        <small>Academic Year</small>
                        <strong id="previewAcademicYear">-</strong>
                    </div>

                    <div class="gs-summary-item">
                        <small>School Hours</small>
                        <strong id="previewSchoolHours">-</strong>
                    </div>

                    <div class="gs-summary-item">
                        <small>Date / Time Format</small>
                        <strong id="previewFormat">-</strong>
                    </div>

                    <div class="gs-summary-item">
                        <small>Language</small>
                        <strong id="previewLanguage">-</strong>
                    </div>

                    <div class="gs-summary-item">
                        <small>Currency</small>
                        <strong id="previewCurrency">₹ INR</strong>
                    </div>

                    <div class="gs-summary-item">
                        <small>Next Admission Number</small>
                        <strong id="previewAdmissionNumber">ADM0001</strong>
                    </div>

                    <div class="gs-summary-item">
                        <small>Next Receipt Number</small>
                        <strong id="previewReceiptNumber">RCP0001</strong>
                    </div>

                    <div class="gs-summary-item">
                        <small>Enabled Shifts</small>
                        <strong id="previewEnabledShifts">General</strong>
                    </div>

                    <div class="gs-summary-item">
                        <small>Maintenance Mode</small>
                        <strong id="previewMaintenance">Off</strong>
                    </div>
                </div>
            </div>
        </section>

        <section class="ui-card gs-footer">
            <small class="text-muted">
                Existing settings will be updated without creating duplicate records.
            </small>

            <div class="gs-footer-actions">
                <button id="resetSettingsBtn" class="btn-ui" type="button">
                    <i data-lucide="rotate-ccw"></i>
                    Reset
                </button>

                <button id="saveSettingsBtn" class="btn-ui btn-primary-ui" type="submit">
                    <i data-lucide="save"></i>
                    <span id="saveSettingsText">Save Settings</span>
                </button>
            </div>
        </section>
    </form>
</div>

<script>
(function(){
'use strict';

const apiUrl = new URL(
    '../api/general-settings.php',
    window.location.href
).href;

let csrfToken = <?=json_encode($generalSettingsCsrf)?>;
let currentSettings = {};
let academicYears = [];
let numberingPreview = {};
let currentShiftSettings = [];

const $ = id => document.getElementById(id);

function showMessage(message, success = false, type = '') {
    const text = String(message ?? '').trim();
    if (!text) return;

    const toastType = type || (success ? 'success' : 'error');
    if (typeof window.showToast === 'function') {
        window.showToast(toastType, text);
        clearMessage();
        return;
    }

    const box = $('settingsMessage');
    box.className = 'alert gs-message show ' +
        (toastType === 'success' ? 'alert-success' :
        (toastType === 'warning' ? 'alert-warning' : 'alert-danger'));
    box.textContent = text;
}

function clearMessage() {
    $('settingsMessage').className = 'alert gs-message';
    $('settingsMessage').textContent = '';
}

async function request(action, data = null) {
    let response;

    if (data === null) {
        const url = new URL(apiUrl);
        url.searchParams.set('action', action);

        response = await fetch(url, {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                Accept: 'application/json'
            }
        });
    } else {
        response = await fetch(apiUrl, {
            method: 'POST',
            credentials: 'same-origin',
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
            `General Settings API returned HTTP ${response.status}.`
        );
    }

    if (!response.ok || !result.success) {
        throw new Error(
            result.message || 'General Settings request failed.'
        );
    }

    if (result.data?.csrf_token) {
        csrfToken = result.data.csrf_token;
    }

    return result;
}

function fillAcademicYears(records, selectedId = 0) {
    academicYears = Array.isArray(records)
        ? records
        : [];

    $('academicYearId').innerHTML =
        '<option value="">Select Academic Year</option>' +
        academicYears.map(year => {
            const id = Number(year.id || 0);
            const name = String(
                year.year_name
                || year.academic_year
                || year.name
                || ''
            );

            return `<option value="${id}">${name}</option>`;
        }).join('');

    if (Number(selectedId) > 0) {
        $('academicYearId').value = String(selectedId);
    }
}

const SHIFT_DEFINITIONS = [
    {key:'morning', name:'Morning', enabled:'shiftMorningEnabled', start:'shiftMorningStart', end:'shiftMorningEnd', preview:'shiftMorningPreview'},
    {key:'general', name:'General', enabled:'shiftGeneralEnabled', start:'shiftGeneralStart', end:'shiftGeneralEnd', preview:'shiftGeneralPreview'},
    {key:'evening', name:'Evening', enabled:'shiftEveningEnabled', start:'shiftEveningStart', end:'shiftEveningEnd', preview:'shiftEveningPreview'}
];

function shiftTimeValue(value) {
    return String(value || '').slice(0, 5);
}

function fillShiftSettings(records, settings = {}) {
    const map = new Map((Array.isArray(records) ? records : []).map(row => [String(row.shift_key || '').toLowerCase(), row]));

    SHIFT_DEFINITIONS.forEach(definition => {
        const row = map.get(definition.key) || {};
        const isGeneral = definition.key === 'general';
        $(definition.enabled).checked = Number(row.is_enabled ?? (isGeneral ? 1 : 0)) === 1;
        $(definition.start).value = shiftTimeValue(row.start_time || (isGeneral ? settings.school_start_time : ''));
        $(definition.end).value = shiftTimeValue(row.end_time || (isGeneral ? settings.school_end_time : ''));
    });

    updateShiftPreviews();
}

function collectShiftSettings() {
    return SHIFT_DEFINITIONS.map((definition, index) => ({
        shift_key: definition.key,
        shift_name: definition.name,
        start_time: $(definition.start).value,
        end_time: $(definition.end).value,
        is_enabled: $(definition.enabled).checked ? 1 : 0,
        display_order: (index + 1) * 10
    }));
}

function validateShiftSettings() {
    const shifts = collectShiftSettings();
    const enabled = shifts.filter(row => Number(row.is_enabled) === 1);

    if (!enabled.length) {
        showMessage('Enable at least one School Shift.', false, 'warning');
        return false;
    }

    for (const shift of enabled) {
        if (!shift.start_time || !shift.end_time) {
            showMessage(`${shift.shift_name} Shift requires Start Time and End Time.`, false, 'warning');
            return false;
        }
        if (shift.start_time >= shift.end_time) {
            showMessage(`${shift.shift_name} Shift End Time must be later than Start Time.`, false, 'warning');
            return false;
        }
    }

    return true;
}

function updateShiftPreviews() {
    const enabledNames = [];

    SHIFT_DEFINITIONS.forEach(definition => {
        const enabled = $(definition.enabled).checked;
        const start = $(definition.start).value;
        const end = $(definition.end).value;
        const card = document.querySelector(`[data-shift-card="${definition.key}"]`);
        card?.classList.toggle('enabled', enabled);
        $(definition.start).disabled = !enabled;
        $(definition.end).disabled = !enabled;

        if (enabled) enabledNames.push(definition.name);
        $(definition.preview).textContent = enabled
            ? (start && end ? `${formatTime(start)} - ${formatTime(end)}` : 'Set start and end time')
            : 'Disabled';
    });

    if ($('previewEnabledShifts')) {
        $('previewEnabledShifts').textContent = enabledNames.length
            ? enabledNames.join(', ')
            : 'None';
    }
}

function fillForm(settings) {
    currentSettings = settings || {};

    $('academicYearId').value =
        String(settings.academic_year_id || '');

    $('schoolStartTime').value =
        String(settings.school_start_time || '08:30').slice(0, 5);

    $('schoolEndTime').value =
        String(settings.school_end_time || '16:00').slice(0, 5);

    $('dateFormat').value =
        settings.date_format || 'd-m-Y';

    $('timeFormat').value =
        settings.time_format || '12';

    $('languageCode').value =
        settings.language_code || 'en';

    $('currencyCode').value =
        settings.currency_code || 'INR';

    $('admissionPrefix').value =
        settings.admission_number_prefix || 'ADM';

    $('receiptPrefix').value =
        settings.receipt_number_prefix || 'RCP';

    $('maintenanceMode').checked =
        Number(settings.maintenance_mode || 0) === 1;

    fillShiftSettings(currentShiftSettings, settings);
    updatePreview();
}

function selectedAcademicYearName() {
    const option = $('academicYearId').selectedOptions?.[0];

    return option && option.value
        ? option.textContent.trim()
        : '-';
}

function formatTime(value) {
    if (!value) {
        return '-';
    }

    if ($('timeFormat').value === '24') {
        return value;
    }

    const [hourValue, minute] = value.split(':');
    let hour = Number(hourValue || 0);
    const period = hour >= 12 ? 'PM' : 'AM';

    hour = hour % 12 || 12;

    return `${hour}:${minute || '00'} ${period}`;
}

function updatePreview() {
    updateShiftPreviews();

    const languageNames = {
        en: 'English',
        ta: 'Tamil',
        hi: 'Hindi'
    };

    const dateFormats = {
        'd-m-Y': 'DD-MM-YYYY',
        'd/m/Y': 'DD/MM/YYYY',
        'Y-m-d': 'YYYY-MM-DD',
        'm/d/Y': 'MM/DD/YYYY'
    };

    $('previewAcademicYear').textContent =
        selectedAcademicYearName();

    $('previewSchoolHours').textContent =
        `${formatTime($('schoolStartTime').value)} - ` +
        `${formatTime($('schoolEndTime').value)}`;

    $('previewFormat').textContent =
        `${dateFormats[$('dateFormat').value] || '-'} / ` +
        `${$('timeFormat').value} Hour`;

    $('previewLanguage').textContent =
        languageNames[$('languageCode').value] || '-';

    $('previewCurrency').textContent =
        $('currencyCode').value === 'INR'
            ? '₹ INR'
            : $('currencyCode').value;

    $('previewMaintenance').textContent =
        $('maintenanceMode').checked
            ? 'On'
            : 'Off';

    const admissionPrefix = ($('admissionPrefix').value.trim().toUpperCase() || 'ADM');
    const receiptPrefix = ($('receiptPrefix').value.trim().toUpperCase() || 'RCP');
    const currentAdmission = String(numberingPreview.next_admission_number || '');
    const currentReceipt = String(numberingPreview.next_receipt_number || '');
    const admissionSuffix = currentAdmission.match(/(\d+)$/)?.[1] || '0001';
    const receiptSuffix = currentReceipt.match(/(\d+)$/)?.[1] || '0001';
    $('previewAdmissionNumber').textContent = admissionPrefix + admissionSuffix;
    $('previewReceiptNumber').textContent = receiptPrefix + receiptSuffix;
}

async function loadSettings() {
    clearMessage();

    try {
        const result = await request('get');
        const settings = result.data.settings || {};
        currentShiftSettings = result.data.shift_settings || [];
        numberingPreview = result.data.numbering_preview || {};

        fillAcademicYears(
            result.data.academic_years || [],
            settings.academic_year_id || 0
        );

        fillForm(settings);
    } catch (error) {
        showMessage(error.message, false);
    }
}

async function saveSettings(event) {
    event.preventDefault();
    clearMessage();

    const form = event.currentTarget;

    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        showMessage(
            'Complete all required General Settings fields.',
            false
        );
        return;
    }

    const startTime = $('schoolStartTime').value;
    const endTime = $('schoolEndTime').value;

    if (startTime >= endTime) {
        showMessage(
            'School End Time must be later than School Start Time.',
            false
        );
        return;
    }

    if (!validateShiftSettings()) {
        return;
    }

    const data = {
        academic_year_id: Number(
            $('academicYearId').value || 0
        ),
        school_start_time: startTime,
        school_end_time: endTime,
        date_format: $('dateFormat').value,
        time_format: $('timeFormat').value,
        language_code: $('languageCode').value,
        currency_code: $('currencyCode').value,
        admission_number_prefix:
            $('admissionPrefix').value.trim(),
        receipt_number_prefix:
            $('receiptPrefix').value.trim(),
        maintenance_mode:
            $('maintenanceMode').checked ? 1 : 0,
        shift_settings: collectShiftSettings()
    };

    const button = $('saveSettingsBtn');
    button.disabled = true;
    $('saveSettingsText').textContent = 'Saving...';

    try {
        const result = await request('save', data);
        numberingPreview = result.data.numbering_preview || {};
        currentShiftSettings = result.data.shift_settings || [];
        fillForm(result.data.settings || {});
        showMessage(result.message, true);
    } catch (error) {
        showMessage(error.message, false);
    } finally {
        button.disabled = false;
        $('saveSettingsText').textContent = 'Save Settings';
    }
}

document.querySelectorAll(
    '#generalSettingsForm input, ' +
    '#generalSettingsForm select'
).forEach(element => {
    element.addEventListener('input', updatePreview);
    element.addEventListener('change', updatePreview);
});

$('generalSettingsForm').addEventListener(
    'submit',
    saveSettings
);

$('refreshSettingsBtn').addEventListener(
    'click',
    loadSettings
);

$('resetSettingsBtn').addEventListener(
    'click',
    () => {
        fillForm(currentSettings);
        clearMessage();
    }
);

loadSettings();
window.lucide?.createIcons();
})();
</script>

<?php require dirname(__DIR__) . '/includes/layout-end.php'; ?>

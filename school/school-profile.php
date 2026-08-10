<?php
declare(strict_types=1);

$pageTitle = 'School Profile';
$pageKey = 'school_profile';
require dirname(__DIR__) . '/includes/layout-start.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (
    empty($_SESSION['school_profile_csrf'])
    || !is_string($_SESSION['school_profile_csrf'])
) {
    $_SESSION['school_profile_csrf'] = bin2hex(random_bytes(32));
}

$schoolProfileCsrf = $_SESSION['school_profile_csrf'];
?>

<style>
*{box-sizing:border-box}

.sp-page{
    display:grid;
    gap:16px;
    width:100%;
    min-width:0;
}

.sp-head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:16px;
}

.sp-head h1{
    margin:0;
    font-size:clamp(24px,2vw,30px);
}

.sp-head p{
    margin:5px 0 0;
}

.sp-actions{
    display:flex;
    gap:9px;
    flex-wrap:wrap;
}

.sp-actions .btn-ui{
    min-height:40px;
    white-space:nowrap;
}

.sp-layout{
    display:grid;
    grid-template-columns:minmax(0,1.5fr) minmax(300px,.7fr);
    gap:16px;
    align-items:start;
}

.sp-card{
    border-radius:14px;
    overflow:hidden;
    min-width:0;
}

.sp-card-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    padding:14px 16px;
    border-bottom:1px solid var(--border-soft,#e7ebf3);
}

.sp-card-head>div{
    display:flex;
    align-items:center;
    gap:10px;
    min-width:0;
}

.sp-card-head span{
    width:34px;
    height:34px;
    border-radius:10px;
    display:grid;
    place-items:center;
    color:#4f46e5;
    background:#eef2ff;
    flex:0 0 auto;
}

.sp-card-head span svg{
    width:17px;
}

.sp-card-head strong{
    display:block;
    font-size:13px;
}

.sp-card-head small{
    display:block;
    margin-top:2px;
    color:var(--text-muted,#64748b);
}

.sp-card-body{
    padding:16px;
}

.sp-form{
    display:grid;
    gap:16px;
}

.sp-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:13px;
}

.sp-grid>div{
    min-width:0;
}

.sp-grid .full{
    grid-column:1/-1;
}

.sp-grid .span-2{
    grid-column:span 2;
}

.sp-form .form-control,
.sp-form .form-select{
    width:100%;
    min-width:0;
}

.sp-logo-box{
    display:grid;
    grid-template-columns:120px minmax(0,1fr);
    gap:16px;
    align-items:center;
}

.sp-logo-preview{
    width:120px;
    height:120px;
    border-radius:16px;
    border:1px dashed #b9c4dc;
    background:rgba(99,102,241,.035);
    display:grid;
    place-items:center;
    overflow:hidden;
}

.sp-logo-preview img{
    width:100%;
    height:100%;
    object-fit:contain;
    display:none;
}

.sp-logo-preview img.show{
    display:block;
}

.sp-logo-placeholder{
    text-align:center;
    color:var(--text-muted,#64748b);
}

.sp-logo-placeholder svg{
    width:30px;
    margin-bottom:5px;
}

.sp-logo-help{
    display:grid;
    gap:8px;
}

.sp-logo-adjust{
    margin-top:14px;
    padding:14px;
    border:1px solid var(--border-soft,#e7ebf3);
    border-radius:12px;
    background:rgba(99,102,241,.035);
}

.sp-logo-adjust-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    margin-bottom:12px;
}

.sp-logo-adjust-head strong{
    font-size:12px;
}

.sp-logo-adjust-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:12px;
}

.sp-adjust-range{
    display:grid;
    gap:6px;
}

.sp-adjust-range-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:8px;
}

.sp-adjust-range-head label{
    margin:0;
    font-size:10px;
    font-weight:700;
}

.sp-adjust-value{
    min-width:46px;
    padding:3px 7px;
    border-radius:999px;
    background:#eef2ff;
    color:#4f46e5;
    text-align:center;
    font-size:9px;
    font-weight:800;
}

.sp-adjust-range input[type="range"]{
    width:100%;
    accent-color:#4f46e5;
}

.sp-logo-preview,
.sp-preview-logo{
    isolation:isolate;
}

.sp-logo-preview img,
.sp-preview-logo img{
    transform-origin:center center;
    transition:transform .16s ease, object-position .16s ease, border-radius .16s ease;
}

@media(max-width:767px){
    .sp-logo-adjust-grid{grid-template-columns:1fr}
}

.sp-preview-card{
    position:sticky;
    top:16px;
}

.sp-preview{
    padding:20px;
    text-align:center;
}

.sp-preview-logo{
    width:92px;
    height:92px;
    margin:0 auto 12px;
    border-radius:14px;
    border:1px solid var(--border-soft,#e7ebf3);
    display:grid;
    place-items:center;
    overflow:hidden;
    background:#fff;
}

.sp-preview-logo img{
    width:100%;
    height:100%;
    object-fit:contain;
    display:none;
}

.sp-preview-logo img.show{
    display:block;
}

.sp-preview-school{
    margin:0;
    font-size:22px;
    line-height:1.2;
    overflow-wrap:anywhere;
}

.sp-preview-code{
    margin-top:5px;
    color:var(--text-muted,#64748b);
    font-size:11px;
}

.sp-preview-address,
.sp-preview-contact{
    margin-top:10px;
    color:var(--text-muted,#64748b);
    font-size:11px;
    line-height:1.6;
    overflow-wrap:anywhere;
}

.sp-preview-divider{
    height:1px;
    background:var(--border-soft,#e7ebf3);
    margin:16px 0;
}

.sp-preview-details{
    display:grid;
    gap:9px;
    text-align:left;
}

.sp-preview-row{
    display:flex;
    justify-content:space-between;
    gap:12px;
    padding:9px 10px;
    border-radius:9px;
    background:rgba(99,102,241,.035);
}

.sp-preview-row small{
    color:var(--text-muted,#64748b);
}

.sp-preview-row strong{
    text-align:right;
    overflow-wrap:anywhere;
}

.sp-message{
    display:none;
    margin:0;
}

.sp-message.show{
    display:block;
}

.sp-footer{
    position:sticky;
    bottom:0;
    z-index:10;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    padding:13px 16px;
    border-radius:14px;
    box-shadow:0 -8px 26px rgba(15,23,42,.08);
}

.sp-footer-actions{
    display:flex;
    gap:9px;
    flex-wrap:wrap;
}

@media(min-width:1600px){
    .sp-layout{
        grid-template-columns:minmax(0,1.7fr) minmax(340px,.65fr);
    }

    .sp-grid{
        grid-template-columns:repeat(3,minmax(0,1fr));
    }

    .sp-grid .span-2{
        grid-column:span 2;
    }

    .sp-grid .full{
        grid-column:1/-1;
    }
}

@media(max-width:1100px){
    .sp-layout{
        grid-template-columns:1fr;
    }

    .sp-preview-card{
        position:static;
    }

    .sp-preview-details{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }
}

@media(max-width:767px){
    .sp-head{
        flex-direction:column;
        align-items:stretch;
    }

    .sp-actions{
        display:grid;
        grid-template-columns:1fr 1fr;
        width:100%;
    }

    .sp-actions .btn-ui{
        width:100%;
        justify-content:center;
    }

    .sp-grid{
        grid-template-columns:1fr;
    }

    .sp-grid .full,
    .sp-grid .span-2{
        grid-column:auto;
    }

    .sp-logo-box{
        grid-template-columns:1fr;
        justify-items:center;
        text-align:center;
    }

    .sp-logo-help{
        width:100%;
    }

    .sp-preview-details{
        grid-template-columns:1fr;
    }

    .sp-footer{
        flex-direction:column;
        align-items:stretch;
    }

    .sp-footer-actions{
        display:grid;
        grid-template-columns:1fr 1fr;
        width:100%;
    }

    .sp-footer-actions .btn-ui{
        width:100%;
        justify-content:center;
    }
}

@media(max-width:480px){
    .sp-actions,
    .sp-footer-actions{
        grid-template-columns:1fr;
    }

    .sp-card-body{
        padding:13px;
    }
}
</style>

<div class="sp-page">
    <div class="sp-head">
        <div>
            <h1>School Profile</h1>
            <p class="text-muted">
                Manage school identity, contact, registration and academic information.
            </p>
        </div>

        <div class="sp-actions">
            <button id="refreshProfileBtn" class="btn-ui" type="button">
                <i data-lucide="refresh-cw"></i>
                Refresh
            </button>

            <button id="printProfileBtn" class="btn-ui" type="button">
                <i data-lucide="printer"></i>
                Print Profile
            </button>
        </div>
    </div>

    <div id="profileMessage" class="alert sp-message"></div>

    <form id="schoolProfileForm" class="sp-form" enctype="multipart/form-data" novalidate>
        <div class="sp-layout">
            <div class="sp-form">
                <section class="ui-card sp-card">
                    <div class="sp-card-head">
                        <div>
                            <span><i data-lucide="school"></i></span>
                            <div>
                                <strong>School Identity</strong>
                                <small>Basic school information and branding.</small>
                            </div>
                        </div>
                    </div>

                    <div class="sp-card-body">
                        <div class="sp-logo-box">
                            <div class="sp-logo-preview">
                                <img id="logoPreview" alt="School Logo">
                                <div id="logoPlaceholder" class="sp-logo-placeholder">
                                    <i data-lucide="image"></i>
                                    <small>School Logo</small>
                                </div>
                            </div>

                            <div class="sp-logo-help">
                                <div>
                                    <label class="form-label" for="schoolLogo">Upload School Logo</label>
                                    <input
                                        id="schoolLogo"
                                        class="form-control"
                                        type="file"
                                        accept=".jpg,.jpeg,.png,.webp"
                                    >
                                </div>

                                <small class="text-muted">
                                    Supported: JPG, PNG and WEBP. Maximum 2 MB.
                                </small>

                                <label class="form-check">
                                    <input id="removeLogo" class="form-check-input" type="checkbox">
                                    <span class="form-check-label">Remove existing logo</span>
                                </label>
                            </div>
                        </div>

                        <div class="sp-logo-adjust">
                            <div class="sp-logo-adjust-head">
                                <div>
                                    <strong>Logo / Profile Photo Adjustment</strong>
                                    <small class="d-block text-muted mt-1">
                                        Adjust the logo without changing the original uploaded image.
                                    </small>
                                </div>
                                <button id="resetLogoAdjustmentBtn" class="btn-ui" type="button">
                                    <i data-lucide="rotate-ccw"></i> Reset
                                </button>
                            </div>

                            <div class="sp-logo-adjust-grid">
                                <div>
                                    <label class="form-label" for="logoFit">Image Fit</label>
                                    <select id="logoFit" class="form-select">
                                        <option value="contain">Contain - show full image</option>
                                        <option value="cover">Cover - fill box</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="form-label" for="logoShape">Logo Shape</label>
                                    <select id="logoShape" class="form-select">
                                        <option value="rounded">Rounded</option>
                                        <option value="circle">Circle</option>
                                        <option value="square">Square</option>
                                    </select>
                                </div>

                                <div class="sp-adjust-range">
                                    <div class="sp-adjust-range-head">
                                        <label for="logoZoom">Zoom</label>
                                        <span id="logoZoomValue" class="sp-adjust-value">100%</span>
                                    </div>
                                    <input id="logoZoom" type="range" min="50" max="200" step="1" value="100">
                                </div>

                                <div class="sp-adjust-range">
                                    <div class="sp-adjust-range-head">
                                        <label for="logoRotation">Rotation</label>
                                        <span id="logoRotationValue" class="sp-adjust-value">0°</span>
                                    </div>
                                    <input id="logoRotation" type="range" min="-180" max="180" step="1" value="0">
                                </div>

                                <div class="sp-adjust-range">
                                    <div class="sp-adjust-range-head">
                                        <label for="logoPositionX">Horizontal Position</label>
                                        <span id="logoPositionXValue" class="sp-adjust-value">50%</span>
                                    </div>
                                    <input id="logoPositionX" type="range" min="0" max="100" step="1" value="50">
                                </div>

                                <div class="sp-adjust-range">
                                    <div class="sp-adjust-range-head">
                                        <label for="logoPositionY">Vertical Position</label>
                                        <span id="logoPositionYValue" class="sp-adjust-value">50%</span>
                                    </div>
                                    <input id="logoPositionY" type="range" min="0" max="100" step="1" value="50">
                                </div>
                            </div>
                        </div>

                        <div class="sp-grid mt-3">
                            <div class="span-2">
                                <label class="form-label" for="schoolName">School Name *</label>
                                <input id="schoolName" class="form-control" maxlength="200" required>
                            </div>

                            <div>
                                <label class="form-label" for="schoolCode">School Code</label>
                                <input id="schoolCode" class="form-control" maxlength="50">
                            </div>

                            <div>
                                <label class="form-label" for="schoolType">School Type</label>
                                <select id="schoolType" class="form-select">
                                    <option value="">Select Type</option>
                                    <option value="Government">Government</option>
                                    <option value="Government Aided">Government Aided</option>
                                    <option value="Private">Private</option>
                                    <option value="International">International</option>
                                    <option value="Matriculation">Matriculation</option>
                                    <option value="CBSE">CBSE</option>
                                    <option value="ICSE">ICSE</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <div>
                                <label class="form-label" for="boardName">Board / Affiliation</label>
                                <input id="boardName" class="form-control" maxlength="120">
                            </div>

                            <div>
                                <label class="form-label" for="affiliationNumber">Affiliation Number</label>
                                <input id="affiliationNumber" class="form-control" maxlength="100">
                            </div>

                            <div>
                                <label class="form-label" for="udiseNumber">UDISE Number</label>
                                <input id="udiseNumber" class="form-control" maxlength="50">
                            </div>

                            <div>
                                <label class="form-label" for="establishedYear">Established Year</label>
                                <input id="establishedYear" class="form-control" type="number" min="1800" max="2100">
                            </div>

                            <div class="full">
                                <label class="form-label" for="schoolMotto">School Motto</label>
                                <input id="schoolMotto" class="form-control" maxlength="250">
                            </div>
                        </div>
                    </div>
                </section>

                <section class="ui-card sp-card">
                    <div class="sp-card-head">
                        <div>
                            <span><i data-lucide="map-pin"></i></span>
                            <div>
                                <strong>Address and Contact</strong>
                                <small>Official communication details.</small>
                            </div>
                        </div>
                    </div>

                    <div class="sp-card-body">
                        <div class="sp-grid">
                            <div class="full">
                                <label class="form-label" for="addressLine1">Address Line 1 *</label>
                                <input id="addressLine1" class="form-control" maxlength="250" required>
                            </div>

                            <div class="full">
                                <label class="form-label" for="addressLine2">Address Line 2</label>
                                <input id="addressLine2" class="form-control" maxlength="250">
                            </div>

                            <div>
                                <label class="form-label" for="city">City *</label>
                                <input id="city" class="form-control" maxlength="100" required>
                            </div>

                            <div>
                                <label class="form-label" for="district">District</label>
                                <input id="district" class="form-control" maxlength="100">
                            </div>

                            <div>
                                <label class="form-label" for="stateName">State *</label>
                                <input id="stateName" class="form-control" maxlength="100" required>
                            </div>

                            <div>
                                <label class="form-label" for="countryName">Country *</label>
                                <input id="countryName" class="form-control" maxlength="100" value="India" required>
                            </div>

                            <div>
                                <label class="form-label" for="postalCode">Postal Code *</label>
                                <input id="postalCode" class="form-control" maxlength="15" required>
                            </div>

                            <div>
                                <label class="form-label" for="phoneNumber">Primary Phone *</label>
                                <input id="phoneNumber" class="form-control" maxlength="20" required>
                            </div>

                            <div>
                                <label class="form-label" for="alternatePhone">Alternate Phone</label>
                                <input id="alternatePhone" class="form-control" maxlength="20">
                            </div>

                            <div>
                                <label class="form-label" for="emailAddress">Email *</label>
                                <input id="emailAddress" class="form-control" type="email" maxlength="190" required>
                            </div>

                            <div class="full">
                                <label class="form-label" for="websiteUrl">Website</label>
                                <input id="websiteUrl" class="form-control" type="url" maxlength="250" placeholder="https://example.com">
                            </div>
                        </div>
                    </div>
                </section>

                <section class="ui-card sp-card">
                    <div class="sp-card-head">
                        <div>
                            <span><i data-lucide="user-round"></i></span>
                            <div>
                                <strong>Principal and Administration</strong>
                                <small>Principal and official contact details.</small>
                            </div>
                        </div>
                    </div>

                    <div class="sp-card-body">
                        <div class="sp-grid">
                            <div>
                                <label class="form-label" for="principalName">Principal Name</label>
                                <input id="principalName" class="form-control" maxlength="150">
                            </div>

                            <div>
                                <label class="form-label" for="principalMobile">Principal Mobile</label>
                                <input id="principalMobile" class="form-control" maxlength="20">
                            </div>

                            <div>
                                <label class="form-label" for="principalEmail">Principal Email</label>
                                <input id="principalEmail" class="form-control" type="email" maxlength="190">
                            </div>

                            <div>
                                <label class="form-label" for="officeContactName">Office Contact Person</label>
                                <input id="officeContactName" class="form-control" maxlength="150">
                            </div>
                        </div>
                    </div>
                </section>

                <section class="ui-card sp-card">
                    <div class="sp-card-head">
                        <div>
                            <span><i data-lucide="settings-2"></i></span>
                            <div>
                                <strong>Academic and System Settings</strong>
                                <small>Default school operation settings.</small>
                            </div>
                        </div>
                    </div>

                    <div class="sp-card-body">
                        <div class="sp-grid">
                            <div>
                                <label class="form-label" for="academicStartMonth">Academic Start Month</label>
                                <select id="academicStartMonth" class="form-select">
                                    <option value="1">January</option>
                                    <option value="2">February</option>
                                    <option value="3">March</option>
                                    <option value="4">April</option>
                                    <option value="5">May</option>
                                    <option value="6" selected>June</option>
                                    <option value="7">July</option>
                                    <option value="8">August</option>
                                    <option value="9">September</option>
                                    <option value="10">October</option>
                                    <option value="11">November</option>
                                    <option value="12">December</option>
                                </select>
                            </div>

                            <div>
                                <label class="form-label" for="currencyCode">Currency</label>
                                <select id="currencyCode" class="form-select">
                                    <option value="INR">INR - Indian Rupee</option>
                                    <option value="USD">USD - US Dollar</option>
                                    <option value="AED">AED - UAE Dirham</option>
                                    <option value="SAR">SAR - Saudi Riyal</option>
                                </select>
                            </div>

                            <div>
                                <label class="form-label" for="timezoneName">Timezone</label>
                                <select id="timezoneName" class="form-select">
                                    <option value="Asia/Kolkata">Asia/Kolkata</option>
                                    <option value="Asia/Dubai">Asia/Dubai</option>
                                    <option value="Asia/Riyadh">Asia/Riyadh</option>
                                    <option value="UTC">UTC</option>
                                </select>
                            </div>

                            <div>
                                <label class="form-label" for="dateFormat">Date Format</label>
                                <select id="dateFormat" class="form-select">
                                    <option value="d-m-Y">DD-MM-YYYY</option>
                                    <option value="d/m/Y">DD/MM/YYYY</option>
                                    <option value="Y-m-d">YYYY-MM-DD</option>
                                </select>
                            </div>

                            <div class="full">
                                <label class="form-label" for="profileNotes">Profile Notes</label>
                                <textarea id="profileNotes" class="form-control" rows="3" maxlength="1000"></textarea>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <aside class="ui-card sp-card sp-preview-card">
                <div class="sp-card-head">
                    <div>
                        <span><i data-lucide="eye"></i></span>
                        <div>
                            <strong>Live Profile Preview</strong>
                            <small>Updates as you type.</small>
                        </div>
                    </div>
                </div>

                <div class="sp-preview">
                    <div class="sp-preview-logo">
                        <img id="previewLogo" alt="School Logo">
                        <i id="previewLogoIcon" data-lucide="school"></i>
                    </div>

                    <h2 id="previewSchoolName" class="sp-preview-school">
                        School Name
                    </h2>

                    <div id="previewSchoolCode" class="sp-preview-code">
                        School Code
                    </div>

                    <div id="previewAddress" class="sp-preview-address">
                        School address will appear here.
                    </div>

                    <div id="previewContact" class="sp-preview-contact">
                        Contact details will appear here.
                    </div>

                    <div class="sp-preview-divider"></div>

                    <div class="sp-preview-details">
                        <div class="sp-preview-row">
                            <small>School Type</small>
                            <strong id="previewSchoolType">-</strong>
                        </div>

                        <div class="sp-preview-row">
                            <small>Board</small>
                            <strong id="previewBoard">-</strong>
                        </div>

                        <div class="sp-preview-row">
                            <small>Principal</small>
                            <strong id="previewPrincipal">-</strong>
                        </div>

                        <div class="sp-preview-row">
                            <small>Academic Start</small>
                            <strong id="previewAcademicStart">June</strong>
                        </div>

                        <div class="sp-preview-row">
                            <small>Currency</small>
                            <strong id="previewCurrency">INR</strong>
                        </div>
                    </div>
                </div>
            </aside>
        </div>

        <section class="ui-card sp-footer">
            <small class="text-muted">
                Changes apply throughout the School ERP.
            </small>

            <div class="sp-footer-actions">
                <button id="resetProfileBtn" class="btn-ui" type="button">
                    <i data-lucide="rotate-ccw"></i>
                    Reset
                </button>

                <button id="saveProfileBtn" class="btn-ui btn-primary-ui" type="submit">
                    <i data-lucide="save"></i>
                    <span id="saveProfileText">Save Profile</span>
                </button>
            </div>
        </section>
    </form>
</div>

<script>
(function(){
'use strict';

const apiUrl = new URL(
    '../api/school-profile.php',
    window.location.href
).href;

let csrfToken = <?=json_encode($schoolProfileCsrf)?>;
let currentProfile = {};
let currentLogoUrl = '';

const $ = id => document.getElementById(id);

function showMessage(message, success = false, type = '') {
    const text = String(message ?? '').trim();
    if (!text) return;

    const toastType = type || (success ? 'success' : 'error');

    // Use the same common toast method as General Settings.
    if (typeof window.showToast === 'function') {
        window.showToast(toastType, text);
        clearMessage();
        return;
    }

    // Safe fallback only when the global toast script is unavailable.
    const box = $('profileMessage');
    box.className = 'alert sp-message show ' +
        (toastType === 'success' ? 'alert-success' :
        (toastType === 'warning' ? 'alert-warning' :
        (toastType === 'info' ? 'alert-info' : 'alert-danger')));
    box.textContent = text;
}

function clearMessage() {
    $('profileMessage').className = 'alert sp-message';
    $('profileMessage').textContent = '';
}

async function request(action, formData = null) {
    let response;

    if (formData instanceof FormData) {
        formData.append('action', action);
        formData.append('csrf_token', csrfToken);

        response = await fetch(apiUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData,
            headers: {
                Accept: 'application/json'
            }
        });
    } else {
        const url = new URL(apiUrl);
        url.searchParams.set('action', action);

        response = await fetch(url, {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                Accept: 'application/json'
            }
        });
    }

    const text = await response.text();
    let result;

    try {
        result = JSON.parse(text);
    } catch (error) {
        throw new Error(
            `School Profile API returned HTTP ${response.status}.`
        );
    }

    if (!response.ok || !result.success) {
        throw new Error(
            result.message || 'School Profile request failed.'
        );
    }

    if (result.data?.csrf_token) {
        csrfToken = result.data.csrf_token;
    }

    return result;
}

function monthName(month) {
    return [
        '',
        'January',
        'February',
        'March',
        'April',
        'May',
        'June',
        'July',
        'August',
        'September',
        'October',
        'November',
        'December'
    ][Number(month || 6)] || 'June';
}

function logoAdjustmentState(profile = null) {
    const source = profile || {};

    return {
        fit: String(source.logo_fit ?? $('logoFit')?.value ?? 'contain'),
        zoom: Number(source.logo_zoom ?? $('logoZoom')?.value ?? 100),
        x: Number(source.logo_position_x ?? $('logoPositionX')?.value ?? 50),
        y: Number(source.logo_position_y ?? $('logoPositionY')?.value ?? 50),
        rotation: Number(source.logo_rotation ?? $('logoRotation')?.value ?? 0),
        shape: String(source.logo_shape ?? $('logoShape')?.value ?? 'rounded')
    };
}

function applyLogoAdjustmentToImage(image, adjustment = null) {
    if (!image) return;

    const values = adjustment || logoAdjustmentState();
    const zoom = Math.max(50, Math.min(200, Number(values.zoom || 100)));
    const x = Math.max(0, Math.min(100, Number(values.x ?? 50)));
    const y = Math.max(0, Math.min(100, Number(values.y ?? 50)));
    const rotation = Math.max(-180, Math.min(180, Number(values.rotation || 0)));
    const fit = values.fit === 'cover' ? 'cover' : 'contain';
    const shape = ['square', 'rounded', 'circle'].includes(values.shape)
        ? values.shape
        : 'rounded';

    image.style.objectFit = fit;
    image.style.objectPosition = `${x}% ${y}%`;
    image.style.transform = `rotate(${rotation}deg) scale(${zoom / 100})`;
    image.style.borderRadius =
        shape === 'circle' ? '50%' :
        (shape === 'square' ? '0' : '12px');
}

function updateLogoAdjustmentPreview() {
    const values = logoAdjustmentState();

    $('logoZoomValue').textContent = `${Math.round(values.zoom)}%`;
    $('logoPositionXValue').textContent = `${Math.round(values.x)}%`;
    $('logoPositionYValue').textContent = `${Math.round(values.y)}%`;
    $('logoRotationValue').textContent = `${Math.round(values.rotation)}°`;

    applyLogoAdjustmentToImage($('logoPreview'), values);
    applyLogoAdjustmentToImage($('previewLogo'), values);
}

function logoDisplay(url) {
    currentLogoUrl = String(url || '');

    ['logoPreview', 'previewLogo'].forEach(id => {
        const image = $(id);

        if (currentLogoUrl) {
            image.src = currentLogoUrl;
            image.classList.add('show');
            applyLogoAdjustmentToImage(image);
        } else {
            image.removeAttribute('src');
            image.classList.remove('show');
        }
    });

    $('logoPlaceholder').style.display =
        currentLogoUrl ? 'none' : '';

    $('previewLogoIcon').style.display =
        currentLogoUrl ? 'none' : '';

    updateLogoAdjustmentPreview();
}

function schoolMonogram(name) {
    const words = String(name || '')
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2);

    return words.map(word => word.charAt(0).toUpperCase()).join('') || 'SC';
}

function updateHeaderBranding(profile) {
    const name = String(profile?.school_name || '').trim();
    const logoUrl = String(profile?.logo_url || '').trim();
    const nameElement = document.querySelector(
        '.sidebar-brand .school-name-primary'
    );
    const logoElement = document.querySelector(
        '.sidebar-brand .brand-logo'
    );

    if (nameElement && name) {
        nameElement.textContent = name;
    }

    if (!logoElement) {
        return;
    }

    if (logoUrl) {
        logoElement.innerHTML = '';
        const image = document.createElement('img');
        image.src = logoUrl;
        image.alt = name || 'School Logo';
        applyLogoAdjustmentToImage(image, logoAdjustmentState(profile));
        logoElement.appendChild(image);
    } else {
        logoElement.innerHTML = '';
        const monogram = document.createElement('span');
        monogram.className = 'brand-monogram';
        monogram.textContent = schoolMonogram(name);
        logoElement.appendChild(monogram);
    }
}

function updatePreview() {
    const address = [
        $('addressLine1').value.trim(),
        $('addressLine2').value.trim(),
        $('city').value.trim(),
        $('district').value.trim(),
        $('stateName').value.trim(),
        $('postalCode').value.trim(),
        $('countryName').value.trim()
    ].filter(Boolean).join(', ');

    const contact = [
        $('phoneNumber').value.trim(),
        $('alternatePhone').value.trim(),
        $('emailAddress').value.trim(),
        $('websiteUrl').value.trim()
    ].filter(Boolean).join(' · ');

    $('previewSchoolName').textContent =
        $('schoolName').value.trim() || 'School Name';

    $('previewSchoolCode').textContent =
        $('schoolCode').value.trim() || 'School Code';

    $('previewAddress').textContent =
        address || 'School address will appear here.';

    $('previewContact').textContent =
        contact || 'Contact details will appear here.';

    $('previewSchoolType').textContent =
        $('schoolType').value || '-';

    $('previewBoard').textContent =
        $('boardName').value.trim() || '-';

    $('previewPrincipal').textContent =
        $('principalName').value.trim() || '-';

    $('previewAcademicStart').textContent =
        monthName($('academicStartMonth').value);

    $('previewCurrency').textContent =
        $('currencyCode').value || 'INR';
}

function fillForm(profile) {
    currentProfile = profile || {};

    const fields = {
        schoolName: 'school_name',
        schoolCode: 'school_code',
        schoolType: 'school_type',
        boardName: 'board_name',
        affiliationNumber: 'affiliation_number',
        udiseNumber: 'udise_number',
        establishedYear: 'established_year',
        schoolMotto: 'school_motto',
        logoFit: 'logo_fit',
        logoZoom: 'logo_zoom',
        logoPositionX: 'logo_position_x',
        logoPositionY: 'logo_position_y',
        logoRotation: 'logo_rotation',
        logoShape: 'logo_shape',
        addressLine1: 'address_line1',
        addressLine2: 'address_line2',
        city: 'city',
        district: 'district',
        stateName: 'state_name',
        countryName: 'country_name',
        postalCode: 'postal_code',
        phoneNumber: 'phone_number',
        alternatePhone: 'alternate_phone',
        emailAddress: 'email_address',
        websiteUrl: 'website_url',
        principalName: 'principal_name',
        principalMobile: 'principal_mobile',
        principalEmail: 'principal_email',
        officeContactName: 'office_contact_name',
        academicStartMonth: 'academic_start_month',
        currencyCode: 'currency_code',
        timezoneName: 'timezone_name',
        dateFormat: 'date_format',
        profileNotes: 'notes'
    };

    Object.entries(fields).forEach(([elementId, key]) => {
        const element = $(elementId);

        if (element) {
            element.value =
                profile?.[key] ??
                element.defaultValue ??
                '';
        }
    });

    if (!$('countryName').value) {
        $('countryName').value = 'India';
    }

    if (!$('academicStartMonth').value) {
        $('academicStartMonth').value = '6';
    }

    if (!$('currencyCode').value) {
        $('currencyCode').value = 'INR';
    }

    if (!$('timezoneName').value) {
        $('timezoneName').value = 'Asia/Kolkata';
    }

    if (!$('dateFormat').value) {
        $('dateFormat').value = 'd-m-Y';
    }

    if (!$('logoFit').value) $('logoFit').value = 'contain';
    if (!$('logoZoom').value) $('logoZoom').value = '100';
    if (!$('logoPositionX').value) $('logoPositionX').value = '50';
    if (!$('logoPositionY').value) $('logoPositionY').value = '50';
    if ($('logoRotation').value === '') $('logoRotation').value = '0';
    if (!$('logoShape').value) $('logoShape').value = 'rounded';

    $('removeLogo').checked = false;
    $('schoolLogo').value = '';

    logoDisplay(profile?.logo_url || '');
    updatePreview();
}

async function loadProfile() {
    clearMessage();

    try {
        const result = await request('get');
        fillForm(result.data.profile || {});
    } catch (error) {
        showMessage(error.message, false);
    }
}

async function saveProfile(event) {
    event.preventDefault();
    clearMessage();

    const form = event.currentTarget;

    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        showMessage('Complete all required School Profile fields.', false, 'warning');
        return;
    }

    const formData = new FormData();

    const fields = {
        school_name: $('schoolName').value.trim(),
        school_code: $('schoolCode').value.trim(),
        school_type: $('schoolType').value,
        board_name: $('boardName').value.trim(),
        affiliation_number: $('affiliationNumber').value.trim(),
        udise_number: $('udiseNumber').value.trim(),
        established_year: $('establishedYear').value,
        school_motto: $('schoolMotto').value.trim(),
        logo_fit: $('logoFit').value,
        logo_zoom: $('logoZoom').value,
        logo_position_x: $('logoPositionX').value,
        logo_position_y: $('logoPositionY').value,
        logo_rotation: $('logoRotation').value,
        logo_shape: $('logoShape').value,
        address_line1: $('addressLine1').value.trim(),
        address_line2: $('addressLine2').value.trim(),
        city: $('city').value.trim(),
        district: $('district').value.trim(),
        state_name: $('stateName').value.trim(),
        country_name: $('countryName').value.trim(),
        postal_code: $('postalCode').value.trim(),
        phone_number: $('phoneNumber').value.trim(),
        alternate_phone: $('alternatePhone').value.trim(),
        email_address: $('emailAddress').value.trim(),
        website_url: $('websiteUrl').value.trim(),
        principal_name: $('principalName').value.trim(),
        principal_mobile: $('principalMobile').value.trim(),
        principal_email: $('principalEmail').value.trim(),
        office_contact_name: $('officeContactName').value.trim(),
        academic_start_month: $('academicStartMonth').value,
        currency_code: $('currencyCode').value,
        timezone_name: $('timezoneName').value,
        date_format: $('dateFormat').value,
        notes: $('profileNotes').value.trim(),
        remove_logo: $('removeLogo').checked ? '1' : '0'
    };

    Object.entries(fields).forEach(([key, value]) => {
        formData.append(key, value);
    });

    const logoFile = $('schoolLogo').files?.[0];

    if (logoFile) {
        formData.append('school_logo', logoFile);
    }

    const button = $('saveProfileBtn');
    button.disabled = true;
    $('saveProfileText').textContent = 'Saving...';

    try {
        const result = await request('save', formData);
        const savedProfile = result.data.profile || {};
        fillForm(savedProfile);
        updateHeaderBranding(savedProfile);
        showMessage(result.message, true);
    } catch (error) {
        showMessage(error.message, false);
    } finally {
        button.disabled = false;
        $('saveProfileText').textContent = 'Save Profile';
    }
}

$('schoolLogo').addEventListener('change', () => {
    const file = $('schoolLogo').files?.[0];

    if (!file) {
        logoDisplay(
            $('removeLogo').checked
                ? ''
                : currentProfile.logo_url || ''
        );
        return;
    }

    if (file.size > 2 * 1024 * 1024) {
        showMessage('School logo cannot exceed 2 MB.', false, 'warning');
        $('schoolLogo').value = '';
        return;
    }

    const url = URL.createObjectURL(file);
    logoDisplay(url);
    $('removeLogo').checked = false;
});

$('removeLogo').addEventListener('change', () => {
    if ($('removeLogo').checked) {
        $('schoolLogo').value = '';
        logoDisplay('');
    } else {
        logoDisplay(currentProfile.logo_url || '');
    }
});

['logoFit', 'logoZoom', 'logoPositionX', 'logoPositionY', 'logoRotation', 'logoShape']
    .forEach(id => {
        $(id).addEventListener('input', updateLogoAdjustmentPreview);
        $(id).addEventListener('change', updateLogoAdjustmentPreview);
    });

$('resetLogoAdjustmentBtn').addEventListener('click', () => {
    $('logoFit').value = 'contain';
    $('logoZoom').value = '100';
    $('logoPositionX').value = '50';
    $('logoPositionY').value = '50';
    $('logoRotation').value = '0';
    $('logoShape').value = 'rounded';
    updateLogoAdjustmentPreview();
    showMessage('Logo adjustment reset to default.', true, 'info');
});

document.querySelectorAll(
    '#schoolProfileForm input:not([type="file"]):not([type="checkbox"]), ' +
    '#schoolProfileForm select, ' +
    '#schoolProfileForm textarea'
).forEach(element => {
    element.addEventListener('input', updatePreview);
    element.addEventListener('change', updatePreview);
});

$('schoolProfileForm').addEventListener('submit', saveProfile);

$('refreshProfileBtn').addEventListener('click', loadProfile);

$('resetProfileBtn').addEventListener('click', () => {
    fillForm(currentProfile);
    clearMessage();
});

$('printProfileBtn').addEventListener('click', () => {
    window.print();
});

loadProfile();
window.lucide?.createIcons();
})();
</script>

<?php require dirname(__DIR__) . '/includes/layout-end.php'; ?>

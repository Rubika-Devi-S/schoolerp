<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_login();

$pageTitle = 'School Management';
$pageKey = 'platform_school_management';
$sidebarFile = __DIR__ . '/sidebar.php';

$currentUser = function_exists('current_user')
    ? current_user()
    : [];

$currentUser = is_array($currentUser)
    ? $currentUser
    : [];

$roleId = (int)(
    $currentUser['role_id']
    ?? $_SESSION['role_id']
    ?? 0
);

$roleKey = strtolower(trim((string)(
    $currentUser['role_key']
    ?? $_SESSION['role_key']
    ?? ''
)));

if (
    $roleKey === ''
    && isset($pdo)
    && $pdo instanceof PDO
    && $roleId > 0
) {
    try {
        $roleStatement = $pdo->prepare(
            "SELECT role_key
             FROM roles
             WHERE id = :role_id
             LIMIT 1"
        );

        $roleStatement->execute([
            'role_id' => $roleId,
        ]);

        $roleKey = strtolower(trim((string)(
            $roleStatement->fetchColumn() ?: ''
        )));
    } catch (Throwable $exception) {
        error_log(
            'School Management role lookup: '
            . $exception->getMessage()
        );
    }
}

$isSuperAdmin = $roleId === 1
    || in_array(
        $roleKey,
        [
            'super_admin',
            'super-admin',
            'superadministrator',
            'super_administrator',
            'super-administrator',
        ],
        true
    );

if (!$isSuperAdmin) {
    http_response_code(403);
    exit('Access denied.');
}

if (
    empty($_SESSION['school_management_csrf'])
    || !is_string($_SESSION['school_management_csrf'])
) {
    $_SESSION['school_management_csrf'] =
        bin2hex(random_bytes(32));
}

$schoolManagementCsrf =
    $_SESSION['school_management_csrf'];

$baseUrl = defined('BASE_URL')
    ? rtrim((string)BASE_URL, '/') . '/'
    : '../';

require dirname(__DIR__) . '/includes/layout-start.php';
?>

<style>
*{box-sizing:border-box}

.psm-page{
    display:grid;
    gap:16px;
    width:100%;
    min-width:0;
}

.psm-head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:16px;
}

.psm-head h1{
    margin:0;
    font-size:clamp(24px,2vw,30px);
}

.psm-head p{
    margin:5px 0 0;
}

.psm-actions{
    display:flex;
    gap:9px;
    flex-wrap:wrap;
}

.psm-actions .btn-ui{
    min-height:40px;
    white-space:nowrap;
}

.psm-message{
    display:none;
    margin:0;
}

.psm-message.show{
    display:block;
}

.psm-stats{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:13px;
}

.psm-stat{
    min-width:0;
    padding:16px;
    border-radius:14px;
    color:#fff;
    overflow:hidden;
    position:relative;
}

.psm-stat:after{
    content:"";
    position:absolute;
    width:92px;
    height:92px;
    right:-28px;
    bottom:-36px;
    border-radius:50%;
    background:rgba(255,255,255,.13);
}

.psm-stat.purple{
    background:linear-gradient(135deg,#7047ef,#5137cf);
}

.psm-stat.green{
    background:linear-gradient(135deg,#45c783,#1ea568);
}

.psm-stat.orange{
    background:linear-gradient(135deg,#ffb327,#ff8a17);
}

.psm-stat.blue{
    background:linear-gradient(135deg,#4388ef,#2563d9);
}

.psm-stat small{
    display:block;
    opacity:.88;
    font-size:10px;
}

.psm-stat strong{
    display:block;
    margin-top:8px;
    font-size:24px;
    line-height:1;
}

.psm-card{
    border-radius:14px;
    overflow:hidden;
    min-width:0;
}

.psm-filter{
    display:grid;
    grid-template-columns:minmax(220px,1.4fr) repeat(3,minmax(150px,.65fr)) auto;
    gap:10px;
    padding:14px 16px;
    border-bottom:1px solid var(--border-soft,#e7ebf3);
}

.psm-filter .form-control,
.psm-filter .form-select{
    min-height:41px;
}

.psm-filter .btn-ui{
    min-height:41px;
}

.psm-table-wrap{
    overflow:auto;
    min-width:0;
}

.psm-table{
    min-width:1180px;
    margin:0;
}

.psm-table th{
    font-size:10px;
    white-space:nowrap;
}

.psm-table td{
    font-size:11px;
    vertical-align:middle;
}

.psm-school{
    display:flex;
    align-items:center;
    gap:10px;
    min-width:230px;
}

.psm-logo{
    width:42px;
    height:42px;
    border-radius:10px;
    flex:0 0 auto;
    display:grid;
    place-items:center;
    overflow:hidden;
    color:#fff;
    background:linear-gradient(135deg,#7047ef,#3559dc);
    font-weight:800;
}

.psm-logo img{
    width:100%;
    height:100%;
    object-fit:cover;
}

.psm-school-copy{
    min-width:0;
}

.psm-school-copy strong,
.psm-school-copy small{
    display:block;
}

.psm-school-copy strong{
    font-size:11px;
    color:var(--text-main,#101b46);
}

.psm-school-copy small{
    margin-top:3px;
    color:var(--text-muted,#64748b);
}

.psm-badge{
    display:inline-flex;
    align-items:center;
    padding:5px 9px;
    border-radius:999px;
    font-size:9px;
    font-weight:800;
    text-transform:capitalize;
}

.psm-badge.active{
    color:#16834f;
    background:#e8f8ef;
}

.psm-badge.inactive{
    color:#dc2626;
    background:#fff0f1;
}

.psm-actions-cell{
    display:flex;
    gap:6px;
    flex-wrap:nowrap;
}

.psm-action{
    width:31px;
    height:31px;
    border:1px solid #d7def1;
    border-radius:8px;
    background:var(--card-bg,#fff);
    display:grid;
    place-items:center;
    color:#4f46e5;
}

.psm-action svg{
    width:14px;
    height:14px;
}

.psm-action.delete{
    color:#dc2626;
}

.psm-empty{
    padding:46px 18px!important;
    text-align:center!important;
    color:var(--text-muted,#64748b);
}

.psm-pagination{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    padding:13px 16px;
    border-top:1px solid var(--border-soft,#e7ebf3);
}

.psm-pagination-actions{
    display:flex;
    align-items:center;
    gap:7px;
    flex-wrap:wrap;
}

.psm-page-btn{
    min-width:34px;
    height:34px;
    padding:0 10px;
    border:1px solid var(--border-soft,#dbe2ef);
    border-radius:8px;
    background:var(--card-bg,#fff);
    color:var(--text-main,#101b46);
}

.psm-page-btn.active{
    color:#fff;
    background:var(--brand-1,#6747e8);
    border-color:var(--brand-1,#6747e8);
}

.psm-page-btn:disabled{
    opacity:.5;
    cursor:not-allowed;
}

.psm-form-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:13px;
}

.psm-form-grid .full{
    grid-column:1/-1;
}

.psm-form-grid .form-control,
.psm-form-grid .form-select{
    width:100%;
    min-width:0;
    min-height:42px;
}

.psm-section-title{
    grid-column:1/-1;
    display:flex;
    align-items:center;
    gap:8px;
    margin-top:4px;
    padding:10px 12px;
    border-radius:10px;
    color:#4338ca;
    background:#eef2ff;
    font-size:11px;
    font-weight:800;
}

.psm-section-title svg{
    width:16px;
}

.psm-logo-upload{
    display:grid;
    grid-template-columns:92px minmax(0,1fr);
    gap:13px;
    align-items:center;
}

.psm-logo-preview{
    width:92px;
    height:92px;
    border-radius:13px;
    display:grid;
    place-items:center;
    overflow:hidden;
    color:#fff;
    background:linear-gradient(135deg,#7047ef,#3559dc);
    font-size:24px;
    font-weight:800;
}

.psm-logo-preview img{
    width:100%;
    height:100%;
    object-fit:cover;
}

.psm-upload-copy small{
    display:block;
    margin-top:5px;
    color:var(--text-muted,#64748b);
    line-height:1.5;
}

.psm-view-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:11px;
}

.psm-view-item{
    min-width:0;
    padding:12px;
    border:1px solid var(--border-soft,#e7ebf3);
    border-radius:10px;
    background:rgba(99,102,241,.035);
}

.psm-view-item.full{
    grid-column:1/-1;
}

.psm-view-item small{
    display:block;
    color:var(--text-muted,#64748b);
    font-size:9px;
}

.psm-view-item strong{
    display:block;
    margin-top:5px;
    font-size:11px;
    overflow-wrap:anywhere;
}

#schoolModal .modal-dialog,
#viewSchoolModal .modal-dialog{
    max-height:calc(100dvh - 32px);
    margin:16px auto;
}

#schoolModal .modal-content,
#viewSchoolModal .modal-content{
    max-height:calc(100dvh - 32px);
    overflow:hidden;
}

#schoolModal form{
    display:flex;
    flex-direction:column;
    max-height:calc(100dvh - 32px);
}

#schoolModal .modal-body,
#viewSchoolModal .modal-body{
    overflow-y:auto;
    min-height:0;
}

@media(max-width:1100px){
    .psm-filter{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }

    .psm-filter .search{
        grid-column:1/-1;
    }
}

@media(max-width:900px){
    .psm-stats{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }

    .psm-head{
        flex-direction:column;
        align-items:stretch;
    }
}

@media(max-width:767px){
    .psm-actions{
        display:grid;
        grid-template-columns:1fr;
        width:100%;
    }

    .psm-actions .btn-ui{
        width:100%;
        justify-content:center;
    }

    .psm-form-grid,
    .psm-view-grid{
        grid-template-columns:1fr;
    }

    .psm-form-grid .full,
    .psm-view-item.full,
    .psm-section-title{
        grid-column:auto;
    }

    .psm-pagination{
        flex-direction:column;
        align-items:stretch;
    }

    .psm-pagination-actions{
        justify-content:center;
    }
}

@media(max-width:575px){
    .psm-stats,
    .psm-filter{
        grid-template-columns:1fr;
    }

    .psm-filter .search{
        grid-column:auto;
    }

    .psm-logo-upload{
        grid-template-columns:1fr;
    }
}
</style>

<div class="psm-page">
    <div class="psm-head">
        <div>
            <h1>School Management</h1>
            <p class="text-muted">
                Create and manage schools from the Super Admin panel.
            </p>
        </div>

        <div class="psm-actions">
            <button id="refreshSchoolsBtn" class="btn-ui" type="button">
                <i data-lucide="refresh-cw"></i>
                Refresh
            </button>

            <button
                id="addSchoolBtn"
                class="btn-ui btn-primary-ui"
                type="button"
                data-permission-action="add"
            >
                <i data-lucide="plus"></i>
                Add School
            </button>
        </div>
    </div>

    <div id="schoolMessage" class="alert psm-message" role="alert"></div>

    <section class="psm-stats">
        <article class="psm-stat purple">
            <small>Total Schools</small>
            <strong id="totalSchools">0</strong>
        </article>

        <article class="psm-stat green">
            <small>Active Schools</small>
            <strong id="activeSchools">0</strong>
        </article>

        <article class="psm-stat orange">
            <small>Inactive Schools</small>
            <strong id="inactiveSchools">0</strong>
        </article>

        <article class="psm-stat blue">
            <small>Boards</small>
            <strong id="boardCount">0</strong>
        </article>
    </section>

    <section class="ui-card psm-card">
        <div class="psm-filter">
            <input
                id="schoolSearch"
                class="form-control search"
                type="search"
                placeholder="Search school name, code, email, mobile or city..."
            >

            <select id="schoolTypeFilter" class="form-select">
                <option value="">All School Types</option>
                <option value="Nursery">Nursery</option>
                <option value="Primary">Primary</option>
                <option value="Middle">Middle</option>
                <option value="Secondary">Secondary</option>
                <option value="Higher Secondary">Higher Secondary</option>
                <option value="International">International</option>
            </select>

            <select id="boardFilter" class="form-select">
                <option value="">All Boards</option>
                <option value="State Board">State Board</option>
                <option value="CBSE">CBSE</option>
                <option value="ICSE">ICSE</option>
                <option value="IB">IB</option>
                <option value="Cambridge">Cambridge</option>
                <option value="Other">Other</option>
            </select>

            <select id="statusFilter" class="form-select">
                <option value="">All Statuses</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>

            <button id="clearSchoolFiltersBtn" class="btn-ui" type="button">
                <i data-lucide="filter-x"></i>
                Clear
            </button>
        </div>

        <div class="psm-table-wrap">
            <table class="table data-table psm-table">
                <thead>
                    <tr>
                        <th>School</th>
                        <th>School ID</th>
                        <th>Code</th>
                        <th>Type</th>
                        <th>Board</th>
                        <th>Academic Year</th>
                        <th>Principal</th>
                        <th>Contact</th>
                        <th>City / State</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="schoolTableBody">
                    <tr>
                        <td colspan="11" class="psm-empty">
                            Loading schools...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="psm-pagination">
            <small id="schoolPaginationInfo" class="text-muted">
                Showing 0 records
            </small>

            <div id="schoolPagination" class="psm-pagination-actions"></div>
        </div>
    </section>
</div>

<div class="modal fade" id="schoolModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <form id="schoolForm" novalidate enctype="multipart/form-data">
                <div class="modal-header">
                    <div>
                        <h5 id="schoolModalTitle" class="modal-title">
                            Add School
                        </h5>
                        <small class="text-muted">
                            Enter the complete school information.
                        </small>
                    </div>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"
                    ></button>
                </div>

                <div class="modal-body">
                    <input id="schoolId" type="hidden" value="0">
                    <input id="removeLogo" type="hidden" value="0">

                    <div class="psm-form-grid">
                        <div class="psm-section-title">
                            <i data-lucide="school"></i>
                            School Information
                        </div>

                        <div>
                            <label class="form-label" for="schoolName">
                                School Name *
                            </label>
                            <input
                                id="schoolName"
                                class="form-control"
                                maxlength="180"
                                required
                            >
                        </div>

                        <div>
                            <label class="form-label" for="schoolCode">
                                School Code
                            </label>
                            <input
                                id="schoolCode"
                                class="form-control"
                                value="Auto Generated"
                                readonly
                            >
                        </div>

                        <div>
                            <label class="form-label" for="schoolType">
                                School Type *
                            </label>
                            <select id="schoolType" class="form-select" required>
                                <option value="">Select School Type</option>
                                <option value="Nursery">Nursery</option>
                                <option value="Primary">Primary</option>
                                <option value="Middle">Middle</option>
                                <option value="Secondary">Secondary</option>
                                <option value="Higher Secondary">Higher Secondary</option>
                                <option value="International">International</option>
                            </select>
                        </div>

                        <div>
                            <label class="form-label" for="boardName">
                                Board *
                            </label>
                            <select id="boardName" class="form-select" required>
                                <option value="">Select Board</option>
                                <option value="State Board">State Board</option>
                                <option value="CBSE">CBSE</option>
                                <option value="ICSE">ICSE</option>
                                <option value="IB">IB</option>
                                <option value="Cambridge">Cambridge</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div>
                            <label class="form-label" for="academicYear">
                                Academic Year *
                            </label>
                            <input
                                id="academicYear"
                                class="form-control"
                                placeholder="2026 - 2027"
                                maxlength="20"
                                required
                            >
                        </div>

                        <div>
                            <label class="form-label" for="principalName">
                                Principal Name *
                            </label>
                            <input
                                id="principalName"
                                class="form-control"
                                maxlength="150"
                                required
                            >
                        </div>

                        <div class="psm-section-title">
                            <i data-lucide="contact"></i>
                            Contact Information
                        </div>

                        <div>
                            <label class="form-label" for="emailAddress">
                                Email Address *
                            </label>
                            <input
                                id="emailAddress"
                                class="form-control"
                                type="email"
                                maxlength="190"
                                required
                            >
                        </div>

                        <div>
                            <label class="form-label" for="mobileNumber">
                                Mobile Number *
                            </label>
                            <input
                                id="mobileNumber"
                                class="form-control"
                                inputmode="numeric"
                                maxlength="20"
                                required
                            >
                        </div>

                        <div>
                            <label class="form-label" for="alternateContact">
                                Alternate Contact Number
                            </label>
                            <input
                                id="alternateContact"
                                class="form-control"
                                inputmode="numeric"
                                maxlength="20"
                            >
                        </div>

                        <div>
                            <label class="form-label" for="websiteUrl">
                                School Website
                            </label>
                            <input
                                id="websiteUrl"
                                class="form-control"
                                type="url"
                                maxlength="255"
                                placeholder="https://school.example.com"
                            >
                        </div>

                        <div class="psm-section-title">
                            <i data-lucide="map-pin"></i>
                            Address Details
                        </div>

                        <div class="full">
                            <label class="form-label" for="schoolAddress">
                                Address *
                            </label>
                            <textarea
                                id="schoolAddress"
                                class="form-control"
                                rows="3"
                                maxlength="500"
                                required
                            ></textarea>
                        </div>

                        <div>
                            <label class="form-label" for="cityName">
                                City *
                            </label>
                            <input
                                id="cityName"
                                class="form-control"
                                maxlength="100"
                                required
                            >
                        </div>

                        <div>
                            <label class="form-label" for="stateName">
                                State *
                            </label>
                            <input
                                id="stateName"
                                class="form-control"
                                maxlength="100"
                                required
                            >
                        </div>

                        <div>
                            <label class="form-label" for="countryName">
                                Country *
                            </label>
                            <input
                                id="countryName"
                                class="form-control"
                                value="India"
                                maxlength="100"
                                required
                            >
                        </div>

                        <div>
                            <label class="form-label" for="pinCode">
                                PIN Code *
                            </label>
                            <input
                                id="pinCode"
                                class="form-control"
                                inputmode="numeric"
                                maxlength="12"
                                required
                            >
                        </div>

                        <div class="psm-section-title">
                            <i data-lucide="image"></i>
                            Branding and Status
                        </div>

                        <div class="full">
                            <label class="form-label">
                                School Logo
                            </label>

                            <div class="psm-logo-upload">
                                <div id="schoolLogoPreview" class="psm-logo-preview">
                                    SC
                                </div>

                                <div class="psm-upload-copy">
                                    <input
                                        id="schoolLogo"
                                        class="form-control"
                                        type="file"
                                        accept=".jpg,.jpeg,.png,.webp"
                                    >

                                    <small>
                                        JPG, PNG or WEBP. Maximum file size: 2 MB.
                                    </small>

                                    <button
                                        id="removeSchoolLogoBtn"
                                        class="btn-ui btn-sm mt-2"
                                        type="button"
                                    >
                                        <i data-lucide="trash-2"></i>
                                        Remove Logo
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="form-label" for="schoolStatus">
                                Status *
                            </label>
                            <select id="schoolStatus" class="form-select" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button
                        class="btn-ui"
                        type="button"
                        data-bs-dismiss="modal"
                    >
                        Cancel
                    </button>

                    <button
                        id="saveSchoolBtn"
                        class="btn-ui btn-primary-ui"
                        type="submit"
                    >
                        <i data-lucide="save"></i>
                        <span id="saveSchoolText">Save School</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="viewSchoolModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">School Details</h5>
                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Close"
                ></button>
            </div>

            <div class="modal-body">
                <div id="viewSchoolContent" class="psm-view-grid"></div>
            </div>

            <div class="modal-footer">
                <button
                    class="btn-ui"
                    type="button"
                    data-bs-dismiss="modal"
                >
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
'use strict';

function initializeSchoolManagement() {
const apiUrl = new URL(
    <?= json_encode(
        $baseUrl . 'api/schools.php',
        JSON_UNESCAPED_SLASHES
    ) ?>,
    window.location.href
).href;

let csrfToken = <?= json_encode(
    $schoolManagementCsrf,
    JSON_UNESCAPED_SLASHES
) ?>;

let currentPage = 1;
let searchTimer = null;
let records = [];
let pagination = {
    total: 0,
    page: 1,
    last_page: 1,
    per_page: 10
};

const $ = id => document.getElementById(id);
const schoolModal = new window.bootstrap.Modal(
    $('schoolModal')
);
const viewSchoolModal = new window.bootstrap.Modal(
    $('viewSchoolModal')
);

function escapeHtml(value) {
    return String(value ?? '').replace(
        /[&<>"']/g,
        character => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        })[character]
    );
}

function showMessage(message, success = false) {
    const box = $('schoolMessage');

    box.className =
        'alert psm-message show ' +
        (success ? 'alert-success' : 'alert-danger');

    box.textContent = message;

    clearTimeout(box._timer);
    box._timer = setTimeout(() => {
        box.className = 'alert psm-message';
    }, 7000);
}

async function request(action, data = {}, method = 'GET') {
    let response;

    if (method === 'GET') {
        const url = new URL(
            apiUrl,
            window.location.href
        );

        url.searchParams.set('action', action);

        Object.entries(data).forEach(([key, value]) => {
            if (
                value !== ''
                && value !== null
                && value !== undefined
            ) {
                url.searchParams.set(key, String(value));
            }
        });

        response = await fetch(url.href, {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                Accept: 'application/json'
            }
        });
    } else {
        const formData = data instanceof FormData
            ? data
            : new FormData();

        if (!(data instanceof FormData)) {
            Object.entries(data).forEach(([key, value]) => {
                formData.append(key, String(value ?? ''));
            });
        }

        formData.set('action', action);
        formData.set('csrf_token', csrfToken);

        response = await fetch(apiUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        });
    }

    const text = await response.text();
    let result;

    try {
        result = JSON.parse(text);
    } catch (error) {
        throw new Error(
            `School API returned HTTP ${response.status}.`
        );
    }

    if (!response.ok || !result.success) {
        throw new Error(
            result.message || 'School request failed.'
        );
    }

    if (result.data?.csrf_token) {
        csrfToken = result.data.csrf_token;
    }

    return result;
}

function schoolInitials(name) {
    return String(name || 'School')
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map(word => word.charAt(0).toUpperCase())
        .join('') || 'SC';
}

function logoHtml(row, large = false) {
    const className = large
        ? 'psm-logo-preview'
        : 'psm-logo';

    if (row.logo_url) {
        return `<span class="${className}">
            <img src="${escapeHtml(row.logo_url)}"
                 alt="${escapeHtml(row.school_name)}">
        </span>`;
    }

    return `<span class="${className}">
        ${escapeHtml(schoolInitials(row.school_name))}
    </span>`;
}

function statusBadge(status) {
    const value = String(status || 'inactive').toLowerCase();

    return `<span class="psm-badge ${escapeHtml(value)}">
        ${escapeHtml(value)}
    </span>`;
}

function renderRows() {
    const body = $('schoolTableBody');

    if (!records.length) {
        body.innerHTML = `
            <tr>
                <td colspan="11" class="psm-empty">
                    No schools found.
                </td>
            </tr>
        `;
        return;
    }

    body.innerHTML = records.map(row => `
        <tr>
            <td>
                <div class="psm-school">
                    ${logoHtml(row)}
                    <div class="psm-school-copy">
                        <strong>${escapeHtml(row.school_name)}</strong>
                        <small>${escapeHtml(row.email_address)}</small>
                    </div>
                </div>
            </td>
            <td>${escapeHtml(row.school_uid)}</td>
            <td>${escapeHtml(row.school_code)}</td>
            <td>${escapeHtml(row.school_type)}</td>
            <td>${escapeHtml(row.board_name)}</td>
            <td>${escapeHtml(row.academic_year)}</td>
            <td>${escapeHtml(row.principal_name)}</td>
            <td>
                ${escapeHtml(row.mobile_number)}
                ${row.alternate_contact
                    ? `<br><small class="text-muted">
                        ${escapeHtml(row.alternate_contact)}
                       </small>`
                    : ''}
            </td>
            <td>
                ${escapeHtml(row.city_name)}
                <br>
                <small class="text-muted">
                    ${escapeHtml(row.state_name)}
                </small>
            </td>
            <td>${statusBadge(row.status)}</td>
            <td>
                <div class="psm-actions-cell justify-content-end">
                    <button
                        class="psm-action js-view"
                        type="button"
                        data-id="${Number(row.id)}"
                        title="View"
                    >
                        <i data-lucide="eye"></i>
                    </button>

                    <button
                        class="psm-action js-edit"
                        type="button"
                        data-id="${Number(row.id)}"
                        data-permission-action="edit"
                        title="Edit"
                    >
                        <i data-lucide="pencil"></i>
                    </button>

                    <button
                        class="psm-action delete js-delete"
                        type="button"
                        data-id="${Number(row.id)}"
                        data-permission-action="delete"
                        title="Delete"
                    >
                        <i data-lucide="trash-2"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');

    window.lucide?.createIcons();
    window.schoolApplyPermissions?.(body);
}

function renderPagination() {
    const total = Number(pagination.total || 0);
    const page = Number(pagination.page || 1);
    const lastPage = Math.max(
        1,
        Number(pagination.last_page || 1)
    );
    const perPage = Number(pagination.per_page || 10);

    const start = total === 0
        ? 0
        : ((page - 1) * perPage) + 1;

    const end = Math.min(page * perPage, total);

    $('schoolPaginationInfo').textContent =
        `Showing ${start}-${end} of ${total} schools`;

    const pages = [];

    for (
        let index = Math.max(1, page - 2);
        index <= Math.min(lastPage, page + 2);
        index++
    ) {
        pages.push(index);
    }

    $('schoolPagination').innerHTML = `
        <button
            class="psm-page-btn"
            type="button"
            data-page="${page - 1}"
            ${page <= 1 ? 'disabled' : ''}
        >
            ‹
        </button>

        ${pages.map(index => `
            <button
                class="psm-page-btn ${index === page ? 'active' : ''}"
                type="button"
                data-page="${index}"
            >
                ${index}
            </button>
        `).join('')}

        <button
            class="psm-page-btn"
            type="button"
            data-page="${page + 1}"
            ${page >= lastPage ? 'disabled' : ''}
        >
            ›
        </button>
    `;
}

function renderStats(stats) {
    $('totalSchools').textContent =
        Number(stats.total || 0);

    $('activeSchools').textContent =
        Number(stats.active || 0);

    $('inactiveSchools').textContent =
        Number(stats.inactive || 0);

    $('boardCount').textContent =
        Number(stats.boards || 0);
}

async function loadSchools(page = currentPage) {
    currentPage = Math.max(1, Number(page || 1));

    try {
        const result = await request('list', {
            page: currentPage,
            per_page: 10,
            search: $('schoolSearch').value.trim(),
            school_type: $('schoolTypeFilter').value,
            board_name: $('boardFilter').value,
            status: $('statusFilter').value
        });

        records = result.data.records || [];
        pagination = result.data.pagination || pagination;

        renderRows();
        renderPagination();
        renderStats(result.data.stats || {});
    } catch (error) {
        showMessage(error.message, false);
        $('schoolTableBody').innerHTML = `
            <tr>
                <td colspan="11" class="psm-empty">
                    Unable to load schools.
                </td>
            </tr>
        `;
    }
}

function resetForm() {
    $('schoolForm').reset();
    $('schoolForm').classList.remove('was-validated');

    $('schoolId').value = '0';
    $('schoolCode').value = 'Auto Generated';
    $('countryName').value = 'India';
    $('schoolStatus').value = 'active';
    $('removeLogo').value = '0';
    $('schoolLogo').value = '';
    $('schoolLogoPreview').innerHTML = 'SC';
    $('schoolModalTitle').textContent = 'Add School';
    $('saveSchoolText').textContent = 'Save School';
}

function fillForm(row) {
    resetForm();

    $('schoolId').value = String(row.id || 0);
    $('schoolName').value = row.school_name || '';
    $('schoolCode').value = row.school_code || '';
    $('schoolType').value = row.school_type || '';
    $('boardName').value = row.board_name || '';
    $('academicYear').value = row.academic_year || '';
    $('principalName').value = row.principal_name || '';
    $('emailAddress').value = row.email_address || '';
    $('mobileNumber').value = row.mobile_number || '';
    $('alternateContact').value = row.alternate_contact || '';
    $('websiteUrl').value = row.website_url || '';
    $('schoolAddress').value = row.address_text || '';
    $('cityName').value = row.city_name || '';
    $('stateName').value = row.state_name || '';
    $('countryName').value = row.country_name || 'India';
    $('pinCode').value = row.pin_code || '';
    $('schoolStatus').value = row.status || 'active';

    $('schoolLogoPreview').innerHTML =
        row.logo_url
            ? `<img src="${escapeHtml(row.logo_url)}"
                    alt="${escapeHtml(row.school_name)}">`
            : escapeHtml(schoolInitials(row.school_name));

    $('schoolModalTitle').textContent = 'Edit School';
    $('saveSchoolText').textContent = 'Update School';
}

async function openEdit(id) {
    try {
        const result = await request('get', {id});
        fillForm(result.data.school || {});
        schoolModal.show();
    } catch (error) {
        showMessage(error.message, false);
    }
}

async function openView(id) {
    try {
        const result = await request('get', {id});
        const row = result.data.school || {};

        $('viewSchoolContent').innerHTML = `
            <div class="psm-view-item full">
                <div class="d-flex align-items-center gap-3">
                    ${logoHtml(row, true)}
                    <div>
                        <strong>${escapeHtml(row.school_name)}</strong>
                        <small>${escapeHtml(row.school_code)}</small>
                    </div>
                </div>
            </div>
            ${[
                ['School ID', row.school_uid],
                ['School Type', row.school_type],
                ['Board', row.board_name],
                ['Academic Year', row.academic_year],
                ['Principal', row.principal_name],
                ['Email', row.email_address],
                ['Mobile', row.mobile_number],
                ['Alternate Contact', row.alternate_contact || '-'],
                ['City', row.city_name],
                ['State', row.state_name],
                ['Country', row.country_name],
                ['PIN Code', row.pin_code],
                ['Website', row.website_url || '-'],
                ['Status', row.status]
            ].map(([label, value]) => `
                <div class="psm-view-item">
                    <small>${escapeHtml(label)}</small>
                    <strong>${escapeHtml(value || '-')}</strong>
                </div>
            `).join('')}
            <div class="psm-view-item full">
                <small>Address</small>
                <strong>${escapeHtml(row.address_text || '-')}</strong>
            </div>
        `;

        viewSchoolModal.show();
    } catch (error) {
        showMessage(error.message, false);
    }
}

async function deleteSchool(id) {
    const row = records.find(
        item => Number(item.id) === Number(id)
    );

    const name = row?.school_name || 'this school';

    if (!window.confirm(
        `Delete ${name}? This action cannot be undone.`
    )) {
        return;
    }

    try {
        const result = await request(
            'delete',
            {id},
            'POST'
        );

        showMessage(result.message, true);
        await loadSchools(
            records.length === 1 && currentPage > 1
                ? currentPage - 1
                : currentPage
        );
    } catch (error) {
        showMessage(error.message, false);
    }
}

function buildFormData() {
    const formData = new FormData();

    formData.append('id', $('schoolId').value);
    formData.append('school_name', $('schoolName').value.trim());
    formData.append('school_type', $('schoolType').value);
    formData.append('board_name', $('boardName').value);
    formData.append('academic_year', $('academicYear').value.trim());
    formData.append('principal_name', $('principalName').value.trim());
    formData.append('email_address', $('emailAddress').value.trim());
    formData.append('mobile_number', $('mobileNumber').value.trim());
    formData.append(
        'alternate_contact',
        $('alternateContact').value.trim()
    );
    formData.append('website_url', $('websiteUrl').value.trim());
    formData.append('address_text', $('schoolAddress').value.trim());
    formData.append('city_name', $('cityName').value.trim());
    formData.append('state_name', $('stateName').value.trim());
    formData.append('country_name', $('countryName').value.trim());
    formData.append('pin_code', $('pinCode').value.trim());
    formData.append('status', $('schoolStatus').value);
    formData.append('remove_logo', $('removeLogo').value);

    if ($('schoolLogo').files[0]) {
        formData.append('school_logo', $('schoolLogo').files[0]);
    }

    return formData;
}

$('schoolForm').addEventListener('submit', async event => {
    event.preventDefault();

    const form = event.currentTarget;

    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        showMessage(
            'Complete all required school fields.',
            false
        );
        return;
    }

    const button = $('saveSchoolBtn');
    button.disabled = true;
    $('saveSchoolText').textContent = 'Saving...';

    try {
        const result = await request(
            'save',
            buildFormData(),
            'POST'
        );

        schoolModal.hide();
        showMessage(result.message, true);
        await loadSchools(1);
    } catch (error) {
        showMessage(error.message, false);
    } finally {
        button.disabled = false;
        $('saveSchoolText').textContent =
            Number($('schoolId').value || 0) > 0
                ? 'Update School'
                : 'Save School';
    }
});

$('schoolTableBody').addEventListener('click', event => {
    const button = event.target.closest('button[data-id]');

    if (!button) {
        return;
    }

    const id = Number(button.dataset.id || 0);

    if (button.classList.contains('js-view')) {
        openView(id);
    } else if (button.classList.contains('js-edit')) {
        openEdit(id);
    } else if (button.classList.contains('js-delete')) {
        deleteSchool(id);
    }
});

$('schoolPagination').addEventListener('click', event => {
    const button = event.target.closest('[data-page]');

    if (!button || button.disabled) {
        return;
    }

    loadSchools(Number(button.dataset.page || 1));
});

$('addSchoolBtn').addEventListener('click', () => {
    resetForm();
    schoolModal.show();
});

$('refreshSchoolsBtn').addEventListener('click', () => {
    loadSchools(currentPage);
});

$('clearSchoolFiltersBtn').addEventListener('click', () => {
    $('schoolSearch').value = '';
    $('schoolTypeFilter').value = '';
    $('boardFilter').value = '';
    $('statusFilter').value = '';
    loadSchools(1);
});

$('schoolSearch').addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        loadSchools(1);
    }, 350);
});

[
    'schoolTypeFilter',
    'boardFilter',
    'statusFilter'
].forEach(id => {
    $(id).addEventListener('change', () => {
        loadSchools(1);
    });
});

$('schoolLogo').addEventListener('change', () => {
    const file = $('schoolLogo').files[0];

    if (!file) {
        return;
    }

    if (file.size > 2 * 1024 * 1024) {
        $('schoolLogo').value = '';
        showMessage(
            'School Logo cannot exceed 2 MB.',
            false
        );
        return;
    }

    const reader = new FileReader();

    reader.addEventListener('load', () => {
        $('schoolLogoPreview').innerHTML =
            `<img src="${reader.result}" alt="School Logo Preview">`;
        $('removeLogo').value = '0';
    });

    reader.readAsDataURL(file);
});

$('removeSchoolLogoBtn').addEventListener('click', () => {
    $('schoolLogo').value = '';
    $('removeLogo').value = '1';
    $('schoolLogoPreview').textContent =
        schoolInitials($('schoolName').value);
});

$('schoolName').addEventListener('input', () => {
    if (
        !$('schoolLogo').files[0]
        && $('removeLogo').value === '1'
    ) {
        $('schoolLogoPreview').textContent =
            schoolInitials($('schoolName').value);
    }
});

loadSchools();
window.lucide?.createIcons();
}

function startSchoolManagement() {
    if (!window.bootstrap || !window.bootstrap.Modal) {
        console.error(
            'Bootstrap JavaScript bundle is not loaded. ' +
            'Make sure bootstrap.bundle.min.js is included in layout-end.php.'
        );

        const messageBox = document.getElementById('schoolMessage');

        if (messageBox) {
            messageBox.className =
                'alert alert-danger psm-message show';

            messageBox.textContent =
                'Bootstrap JavaScript is not loaded. ' +
                'Please include bootstrap.bundle.min.js in the shared layout.';
        }

        return;
    }

    initializeSchoolManagement();
}

if (document.readyState === 'loading') {
    document.addEventListener(
        'DOMContentLoaded',
        startSchoolManagement,
        {once: true}
    );
} else {
    startSchoolManagement();
}
})();
</script>

<?php require dirname(__DIR__) . '/includes/layout-end.php'; ?>

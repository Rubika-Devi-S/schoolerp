<?php
declare(strict_types=1);

/*
 * School Admin Branch Settings / Request
 * Location: school/branch-settings.php
 * Build: 2026-08-17-remove-classes-file-toggle-v60
 *
 * Important:
 * New branches are NOT inserted into branches here.
 * School Admin submits a request for Super Admin approval.
 */

$pageTitle = 'Branch Settings';
$pageKey = 'branch_settings';

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/permission-chain.php';
require_once dirname(__DIR__) . '/includes/branch-isolation.php';

$currentUser = function_exists('current_user')
    ? current_user()
    : [];

$currentUser = is_array($currentUser)
    ? $currentUser
    : [];

$tenantId = (int)(
    $currentUser['tenant_id']
    ?? $currentUser['school_id']
    ?? $_SESSION['tenant_id']
    ?? $_SESSION['school_id']
    ?? 0
);

$roleId = (int)(
    $currentUser['role_id']
    ?? $_SESSION['role_id']
    ?? 0
);

$role = (
    isset($pdo)
    && $pdo instanceof PDO
    && $tenantId > 0
)
    ? pc_role($pdo, $tenantId, $roleId)
    : [];

$roleKey = pc_role_key(
    (string)(
        $role['role_key']
        ?? $currentUser['role_key']
        ?? $_SESSION['role_key']
        ?? ''
    )
);

if ($roleKey !== 'school_admin') {
    http_response_code(403);
    exit('Only School Administrator can manage Branch Settings.');
}

require_once dirname(__DIR__) . '/includes/layout-start.php';
?>

<style>
/* =========================================================
   PARENT SIDEBAR PERMISSION
   Easy Enable UI + Dashboard-style statistic cards
   ========================================================= */
.psp-page{
    display:grid;
    gap:16px;
}

.psp-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:14px;
    flex-wrap:wrap;
}

.psp-head .page-actions{
    display:flex;
    gap:10px;
    align-items:center;
    flex-wrap:wrap;
}

/* Dashboard-style statistic cards */
.psp-summary{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:14px;
}

.psp-stat-card{
    position:relative;
    min-width:0;
    min-height:124px;
    padding:20px 22px;
    display:flex;
    align-items:center;
    gap:16px;
    overflow:hidden;
    color:#fff;
    border:0;
    border-radius:15px;
    box-shadow:0 10px 24px rgba(15,23,42,.08);
    isolation:isolate;
}

.psp-stat-card::before{
    content:"";
    position:absolute;
    z-index:-1;
    width:132px;
    height:132px;
    right:-42px;
    top:-55px;
    border-radius:50%;
    background:rgba(255,255,255,.085);
}

.psp-stat-card::after{
    content:"";
    position:absolute;
    z-index:-1;
    width:52px;
    height:52px;
    right:14px;
    bottom:-31px;
    border-radius:50%;
    background:rgba(255,255,255,.045);
}

.psp-purple{background:linear-gradient(135deg,#7548ee 0%,#5033d5 100%)}
.psp-green{background:linear-gradient(135deg,#43c987 0%,#20aa6f 100%)}
.psp-pink{background:linear-gradient(135deg,#ff4c78 0%,#ef2f62 100%)}
.psp-orange{background:linear-gradient(135deg,#ffb22a 0%,#ff8b19 100%)}

.psp-stat-icon{
    width:54px;
    height:54px;
    flex:0 0 54px;
    display:grid;
    place-items:center;
    color:#fff;
    background:rgba(255,255,255,.17);
    border:1px solid rgba(255,255,255,.09);
    border-radius:50%;
}

.psp-stat-icon svg{
    width:27px;
    height:27px;
    stroke-width:1.9;
}

.psp-stat-copy{
    position:relative;
    z-index:1;
    min-width:0;
}

.psp-stat-copy small{
    display:block;
    margin:0 0 5px;
    color:rgba(255,255,255,.94);
    font-size:11px;
    font-weight:700;
    line-height:1.2;
}

.psp-stat-value{
    overflow:hidden;
    color:#fff;
    font-size:34px;
    font-weight:800;
    line-height:1;
    letter-spacing:-.035em;
    text-overflow:ellipsis;
    white-space:nowrap;
}

.psp-stat-note{
    margin-top:8px;
    overflow:hidden;
    color:rgba(255,255,255,.92);
    font-size:9.5px;
    font-weight:650;
    line-height:1.3;
    text-overflow:ellipsis;
    white-space:nowrap;
}

.psp-note{
    padding:12px 14px;
    border:1px solid #dbeafe;
    background:#eff6ff;
    color:#1e40af;
    border-radius:12px;
    font-size:10px;
    line-height:1.55;
}

.psp-card{
    background:var(--card-bg,#fff);
    border:1px solid var(--border-soft,#e7ebf3);
    border-radius:13px;
    overflow:hidden;
    box-shadow:0 5px 18px rgba(15,23,42,.04);
}

.psp-toolbar{
    padding:12px 14px;
    border-bottom:1px solid var(--border-soft,#e7ebf3);
    display:flex;
    gap:10px;
    align-items:center;
    justify-content:space-between;
    flex-wrap:wrap;
}

.psp-search-wrap{
    flex:1 1 360px;
    max-width:520px;
    position:relative;
}

.psp-search-wrap > i,
.psp-search-wrap > svg{
    position:absolute;
    left:12px;
    top:50%;
    width:17px;
    height:17px;
    color:#8b96aa;
    transform:translateY(-50%);
    pointer-events:none;
}

.psp-search-wrap .form-control{
    padding-left:38px;
}

.psp-list{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:12px;
    padding:14px;
    background:#f8fafc;
}

.psp-row{
    min-width:0;
    padding:15px;
    border:1px solid var(--border-soft,#e7ebf3);
    border-radius:12px;
    background:var(--card-bg,#fff);
    box-shadow:0 3px 12px rgba(15,23,42,.035);
    transition:border-color .15s ease,box-shadow .15s ease,transform .15s ease;
}

.psp-row:hover{
    border-color:#d9def0;
    box-shadow:0 7px 18px rgba(15,23,42,.055);
    transform:translateY(-1px);
}

.psp-main{
    display:grid;
    grid-template-columns:minmax(0,1fr) auto;
    gap:14px;
    align-items:center;
}

.psp-menu{
    min-width:0;
    display:flex;
    gap:11px;
    align-items:center;
}

.psp-icon{
    width:42px;
    height:42px;
    flex:0 0 42px;
    border-radius:12px;
    display:grid;
    place-items:center;
    background:#eef2ff;
    color:#4f46e5;
}

.psp-icon svg{
    width:19px;
    height:19px;
}

.psp-menu-copy{
    min-width:0;
}

.psp-menu strong,
.psp-menu small{
    display:block;
}

.psp-menu strong{
    color:var(--text-main,#101a3b);
    font-size:11px;
    font-weight:750;
}

.psp-menu small{
    margin-top:3px;
    overflow:hidden;
    color:#64748b;
    font-size:8.5px;
    text-overflow:ellipsis;
    white-space:nowrap;
}

/* One easy enable control per menu */
.psp-enable{
    display:flex;
    align-items:center;
    gap:10px;
}

.psp-enable-state{
    min-width:58px;
    padding:4px 7px;
    text-align:center;
    color:#64748b;
    background:#f1f5f9;
    border-radius:999px;
    font-size:8.5px;
    font-weight:800;
}

.psp-enable-state.on{
    color:#158458;
    background:#e9f8f1;
}

.psp-switch-control{
    position:relative;
    width:46px;
    height:25px;
    flex:0 0 46px;
}

.psp-switch-control input{
    position:absolute;
    inset:0;
    width:100%;
    height:100%;
    margin:0;
    opacity:0;
    cursor:pointer;
    z-index:2;
}

.psp-switch-track{
    position:absolute;
    inset:0;
    border-radius:999px;
    background:#dfe4ee;
    transition:.18s ease;
}

.psp-switch-track::after{
    content:"";
    position:absolute;
    width:19px;
    height:19px;
    left:3px;
    top:3px;
    border-radius:50%;
    background:#fff;
    box-shadow:0 2px 5px rgba(15,23,42,.16);
    transition:.18s ease;
}

.psp-switch-control input:checked + .psp-switch-track{
    background:linear-gradient(135deg,#6547e8,#315ed8);
}

.psp-switch-control input:checked + .psp-switch-track::after{
    transform:translateX(21px);
}

.psp-switch-control input:disabled{
    cursor:not-allowed;
}

.psp-switch-control input:disabled + .psp-switch-track{
    opacity:.45;
}

.psp-caps{
    display:flex;
    align-items:center;
    flex-wrap:wrap;
    gap:6px;
    margin-top:10px;
    padding-left:51px;
}

.psp-cap-label{
    color:#8a94a8;
    font-size:8px;
    font-weight:700;
}

.psp-cap{
    display:inline-flex;
    align-items:center;
    min-height:22px;
    padding:3px 7px;
    color:#4f46e5;
    background:#eef2ff;
    border-radius:999px;
    font-size:8px;
    font-weight:700;
}

.psp-cap.blocked{
    color:#8a94a8;
    background:#f1f4f8;
    text-decoration:line-through;
}

.psp-empty{
    padding:32px;
    text-align:center;
    color:#64748b;
    font-size:10px;
}

@media(max-width:1199.98px){
    .psp-summary{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }
}

@media(max-width:991.98px){
    .psp-list{
        grid-template-columns:1fr;
    }
}

@media(max-width:760px){
    .psp-summary{
        grid-template-columns:1fr;
    }

    .psp-main{
        grid-template-columns:1fr;
        align-items:start;
    }

    .psp-enable{
        justify-content:flex-start;
        padding-left:51px;
    }

    .psp-enable-state{
        min-width:0;
        text-align:left;
    }

    .psp-toolbar{
        align-items:stretch;
    }

    .psp-search-wrap{
        max-width:none;
        flex-basis:100%;
    }
}

@media(max-width:575.98px){
    .psp-stat-card{
        min-height:108px;
        padding:17px 18px;
    }

    .psp-stat-icon{
        width:48px;
        height:48px;
        flex-basis:48px;
    }

    .psp-stat-icon svg{
        width:24px;
        height:24px;
    }

    .psp-stat-value{
        font-size:28px;
    }

    .psp-caps,
    .psp-enable{
        padding-left:0;
    }
}

/* Branch workflow content only - existing shared ERP UI remains unchanged. */
.branch-request-form{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:12px;
}
.branch-request-form .full{grid-column:1/-1}
.branch-request-form label{
    display:block;
    margin-bottom:6px;
    color:var(--text-main,#101a3b);
    font-size:10px;
    font-weight:750;
}
.branch-request-form .form-control{
    min-height:42px;
}
.branch-request-form textarea.form-control{
    min-height:96px;
    resize:vertical;
}
.branch-status{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:72px;
    min-height:24px;
    padding:4px 9px;
    border-radius:999px;
    font-size:8.5px;
    font-weight:800;
    text-transform:capitalize;
}
.branch-status.pending{
    color:#9a6508;
    background:#fff6dd;
}
.branch-status.approved,
.branch-status.active{
    color:#158458;
    background:#e9f8f1;
}
.branch-status.rejected,
.branch-status.inactive{
    color:#c83a55;
    background:#fff0f3;
}

.branch-switch-actions{
    display:flex;
    align-items:center;
    gap:7px;
    flex-wrap:wrap;
}
.branch-switch-actions .btn-ui{
    min-height:31px;
    padding:6px 10px;
    font-size:8.5px;
}

.branch-current-badge{
    display:inline-flex;
    align-items:center;
    gap:5px;
    min-height:27px;
    padding:5px 9px;
    border-radius:999px;
    color:#158458;
    background:#e9f8f1;
    font-size:8.5px;
    font-weight:800;
}
.branch-meta{
    display:flex;
    align-items:center;
    gap:7px;
    flex-wrap:wrap;
    margin-top:8px;
    padding-left:53px;
}
.branch-meta span{
    color:#64748b;
    font-size:8.5px;
    font-weight:650;
}
.branch-section-space{margin-top:16px}
.branch-modal-note{
    padding:10px 12px;
    margin-bottom:14px;
    color:#1e40af;
    background:#eff6ff;
    border:1px solid #dbeafe;
    border-radius:10px;
    font-size:9px;
    line-height:1.5;
}
@media(max-width:575.98px){
    .branch-request-form{grid-template-columns:1fr}
    .branch-request-form .full{grid-column:auto}
    .branch-meta{padding-left:0}
}
</style>

<div class="psp-page">
    <div class="psp-head">
        <div>
            <h1 class="page-title">Branch Settings</h1>
            <p class="page-subtitle">
                View your school branches and request a new branch for Super Admin approval.
            </p>
        </div>

        <div class="page-actions">
            <button id="branchReload" class="btn-ui" type="button">
                <i data-lucide="refresh-cw"></i>
                Refresh
            </button>

            <button
                id="branchRequestBtn"
                class="btn-ui btn-primary-ui"
                type="button"
            >
                <i data-lucide="git-pull-request"></i>
                Request New Branch
            </button>
        </div>
    </div>

    <section class="psp-summary">
        <article class="psp-stat-card psp-purple">
            <div class="psp-stat-icon">
                <i data-lucide="git-branch"></i>
            </div>
            <div class="psp-stat-copy">
                <small>Active Branches</small>
                <div id="activeBranches" class="psp-stat-value">0</div>
                <div class="psp-stat-note">Approved branches for this school</div>
            </div>
        </article>

        <article class="psp-stat-card psp-orange">
            <div class="psp-stat-icon">
                <i data-lucide="clock-3"></i>
            </div>
            <div class="psp-stat-copy">
                <small>Pending Requests</small>
                <div id="pendingRequests" class="psp-stat-value">0</div>
                <div class="psp-stat-note">Waiting for Super Admin</div>
            </div>
        </article>

        <article class="psp-stat-card psp-green">
            <div class="psp-stat-icon">
                <i data-lucide="circle-check-big"></i>
            </div>
            <div class="psp-stat-copy">
                <small>Approved Requests</small>
                <div id="approvedRequests" class="psp-stat-value">0</div>
                <div class="psp-stat-note">Created after approval</div>
            </div>
        </article>

        <article class="psp-stat-card psp-pink">
            <div class="psp-stat-icon">
                <i data-lucide="circle-x"></i>
            </div>
            <div class="psp-stat-copy">
                <small>Rejected Requests</small>
                <div id="rejectedRequests" class="psp-stat-value">0</div>
                <div class="psp-stat-note">Rejected by Super Admin</div>
            </div>
        </article>
    </section>

    <div class="psp-note">
        <strong>Branch approval:</strong>
        A new branch is not created immediately. Your request is sent to Super Admin.
        The branch becomes active only after Super Admin approval.
    </div>

    <div class="psp-note">
        <strong>Current Branch:</strong>
        <span id="currentBranchName">Loading...</span>
        · The school remains the same, but all operational records belong only to this selected Branch. Switching Branch never copies data.
    </div>

    <section class="psp-card">
        <div class="psp-toolbar">
            <div class="psp-search-wrap">
                <i data-lucide="search"></i>
                <input
                    id="branchSearch"
                    class="form-control"
                    placeholder="Search your branches..."
                    autocomplete="off"
                >
            </div>

            <div style="font-size:9px;color:#64748b;font-weight:700">
                Only branches linked to your school are displayed.
            </div>
        </div>

        <div id="branchList" class="psp-list">
            <div class="psp-empty">Loading branches...</div>
        </div>
    </section>

    <section class="psp-card branch-section-space">
        <div class="psp-toolbar">
            <div>
                <strong style="font-size:11px;color:var(--text-main,#101a3b)">
                    Branch Request History
                </strong>
                <div style="margin-top:3px;font-size:8.5px;color:#64748b">
                    Pending, approved and rejected requests for this school only.
                </div>
            </div>
        </div>

        <div id="requestList" class="psp-list">
            <div class="psp-empty">Loading requests...</div>
        </div>
    </section>
</div>

<div
    class="modal fade"
    id="branchRequestModal"
    tabindex="-1"
    aria-hidden="true"
>
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="branchRequestForm" novalidate>
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Request New Branch</h5>
                        <small class="text-muted">
                            The branch will be created only after Super Admin approval.
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
                    <div class="branch-modal-note">
                        This request is automatically linked to your logged-in school.
                        You cannot create a branch for another school.
                    </div>

                    <div class="branch-request-form">
                        <div>
                            <label for="requestBranchName">Branch Name *</label>
                            <input
                                id="requestBranchName"
                                class="form-control"
                                maxlength="150"
                                required
                                placeholder="Example: North Campus"
                            >
                        </div>

                        <div>
                            <label for="requestBranchCode">Branch Code</label>
                            <input
                                id="requestBranchCode"
                                class="form-control"
                                maxlength="30"
                                placeholder="Auto generated if blank"
                            >
                        </div>

                        <div>
                            <label for="requestPhone">Phone</label>
                            <input
                                id="requestPhone"
                                class="form-control"
                                maxlength="20"
                                inputmode="tel"
                                placeholder="Branch contact number"
                            >
                        </div>

                        <div class="full">
                            <label for="requestAddress">Address</label>
                            <textarea
                                id="requestAddress"
                                class="form-control"
                                maxlength="1000"
                                placeholder="Complete branch address"
                            ></textarea>
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
                        id="submitBranchRequest"
                        class="btn-ui btn-primary-ui"
                        type="submit"
                    >
                        <i data-lucide="send"></i>
                        Send for Approval
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(() => {
    'use strict';

    const $ = id => document.getElementById(id);
    const apiUrl = new URL(
        '../api/branch-requests.php',
        window.location.href
    ).href;

    let csrfToken = '';
    let branches = [];
    let requests = [];
    let currentBranchId = 0;

    let branchRequestModal = null;

    function getBranchRequestModal() {
        if (!window.bootstrap?.Modal) {
            throw new Error(
                'Bootstrap modal library is not ready. Refresh the page and try again.'
            );
        }

        if (!branchRequestModal) {
            branchRequestModal =
                window.bootstrap.Modal.getOrCreateInstance(
                    $('branchRequestModal')
                );
        }

        return branchRequestModal;
    }

    const esc = value => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    function toast(message, success = true) {
        if (
            window.SchoolToast
            ?.[success ? 'success' : 'error']
        ) {
            window.SchoolToast[
                success ? 'success' : 'error'
            ](message);
            return;
        }

        alert(message);
    }

    function dateText(value) {
        if (!value) return '—';

        const date = new Date(
            String(value).replace(' ', 'T')
        );

        return Number.isNaN(date.getTime())
            ? String(value)
            : date.toLocaleDateString('en-GB');
    }

    async function request(
        action,
        data = {},
        method = 'GET'
    ) {
        const url = new URL(apiUrl);

        const options = {
            method,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json'
            }
        };

        if (method === 'GET') {
            url.searchParams.set('action', action);

            Object.entries(data)
                .forEach(([key, value]) => {
                    url.searchParams.set(
                        key,
                        String(value)
                    );
                });
        } else {
            options.headers['Content-Type'] =
                'application/json';

            options.body = JSON.stringify({
                action,
                csrf_token: csrfToken,
                ...data
            });
        }

        const response = await fetch(
            url,
            options
        );

        const text = await response.text();
        let result;

        try {
            result = JSON.parse(text);
        } catch (error) {
            console.error(text);
            throw new Error(
                'Invalid Branch Request API response.'
            );
        }

        if (result.data?.csrf_token) {
            csrfToken =
                result.data.csrf_token;
        }

        if (
            !response.ok
            || !result.success
        ) {
            throw new Error(
                result.message
                || `HTTP ${response.status}`
            );
        }

        return result;
    }

    function renderStats(stats = {}) {
        $('activeBranches').textContent =
            String(stats.active_branches || 0);

        $('pendingRequests').textContent =
            String(stats.pending_requests || 0);

        $('approvedRequests').textContent =
            String(stats.approved_requests || 0);

        $('rejectedRequests').textContent =
            String(stats.rejected_requests || 0);
    }

    function renderBranches() {
        const query =
            $('branchSearch')
                .value
                .trim()
                .toLowerCase();

        const rows = branches.filter(
            branch => {
                if (!query) return true;

                return [
                    branch.branch_name,
                    branch.branch_code,
                    branch.phone,
                    branch.address
                ]
                    .join(' ')
                    .toLowerCase()
                    .includes(query);
            }
        );

        if (!rows.length) {
            $('branchList').innerHTML = `
                <div class="psp-empty">
                    No branches found for your school.
                </div>
            `;

            return;
        }

        $('branchList').innerHTML =
            rows.map(branch => `
                <div class="psp-row">
                    <div class="psp-main">
                        <div class="psp-menu">
                            <span class="psp-icon">
                                <i data-lucide="git-branch"></i>
                            </span>

                            <div class="psp-menu-copy">
                                <strong>
                                    ${esc(
                                        branch.branch_name
                                        || 'Branch'
                                    )}
                                </strong>

                                <small>
                                    ${esc(
                                        branch.branch_code
                                        || '—'
                                    )}
                                    ·
                                    ${esc(
                                        branch.phone
                                        || 'No phone'
                                    )}
                                </small>
                            </div>
                        </div>

                        <div class="branch-switch-actions">
                            ${
                                Number(branch.id) === Number(currentBranchId)
                                    ? `
                                        <span class="branch-current-badge">
                                            <i data-lucide="circle-check"></i>
                                            Current Branch
                                        </span>
                                    `
                                    : `
                                        <button
                                            type="button"
                                            class="btn-ui js-switch-branch"
                                            data-id="${Number(branch.id)}"
                                            data-name="${esc(
                                                branch.branch_name
                                                || 'Branch'
                                            )}"
                                        >
                                            <i data-lucide="repeat-2"></i>
                                            Use Branch
                                        </button>
                                    `
                            }

                            <span class="branch-status ${
                                esc(
                                    branch.status
                                    || 'active'
                                )
                            }">
                                ${esc(
                                    branch.status
                                    || 'active'
                                )}
                            </span>
                        </div>
                    </div>

                    <div class="branch-meta">
                        <span>
                            ${Number(branch.is_main) === 1
                                ? 'Main Branch'
                                : 'Branch'}
                        </span>
                        <span>
                            ${esc(
                                branch.address
                                || 'Address not set'
                            )}
                        </span>
                    </div>
                </div>
            `).join('');

        document
            .querySelectorAll(
                '.js-switch-branch'
            )
            .forEach(button => {
                button.addEventListener(
                    'click',
                    () => switchBranch(
                        Number(button.dataset.id),
                        String(
                            button.dataset.name
                            || 'Branch'
                        )
                    )
                );
            });

        window.lucide?.createIcons();
    }

    async function switchBranch(
        branchId,
        branchName
    ) {
        if (
            !Number.isInteger(branchId)
            || branchId <= 0
        ) {
            toast(
                'Invalid Branch.',
                false
            );
            return;
        }

        if (
            !window.confirm(
                `Switch active Branch to "${branchName}"?`
            )
        ) {
            return;
        }

        try {
            const result = await request(
                'switch_branch',
                {
                    branch_id: branchId
                },
                'POST'
            );

            currentBranchId =
                Number(
                    result.data?.current_branch_id
                    || branchId
                );

            toast(
                result.message
                || 'Branch changed successfully.',
                true
            );

            /*
             * Reload so the shared header, current_user() and every subsequent
             * page request use the newly selected default_branch_id.
             */
            window.location.reload();
        } catch (error) {
            toast(
                error.message
                || 'Unable to switch Branch.',
                false
            );
        }
    }

    function renderRequests() {
        if (!requests.length) {
            $('requestList').innerHTML = `
                <div class="psp-empty">
                    No branch requests found.
                </div>
            `;
            return;
        }

        $('requestList').innerHTML =
            requests.map(row => `
                <div class="psp-row">
                    <div class="psp-main">
                        <div class="psp-menu">
                            <span class="psp-icon">
                                <i data-lucide="git-pull-request"></i>
                            </span>

                            <div class="psp-menu-copy">
                                <strong>
                                    ${esc(
                                        row.branch_name
                                        || 'Branch Request'
                                    )}
                                </strong>

                                <small>
                                    ${esc(
                                        row.branch_code
                                        || '—'
                                    )}
                                    · Requested
                                    ${esc(
                                        dateText(
                                            row.created_at
                                        )
                                    )}
                                </small>
                            </div>
                        </div>

                        <span class="branch-status ${
                            esc(row.status || 'pending')
                        }">
                            ${esc(row.status || 'pending')}
                        </span>
                    </div>

                    <div class="branch-meta">
                        <span>
                            ${esc(
                                row.phone
                                || 'No phone'
                            )}
                        </span>

                        <span>
                            ${esc(
                                row.address
                                || 'Address not set'
                            )}
                        </span>

                        ${
                            row.decision_notes
                                ? `<span>Note: ${
                                    esc(
                                        row.decision_notes
                                    )
                                }</span>`
                                : ''
                        }
                    </div>
                </div>
            `).join('');

        window.lucide?.createIcons();
    }

    async function load() {
        $('branchList').innerHTML =
            '<div class="psp-empty">Loading branches...</div>';

        $('requestList').innerHTML =
            '<div class="psp-empty">Loading requests...</div>';

        const result =
            await request('load');

        branches =
            Array.isArray(
                result.data?.branches
            )
                ? result.data.branches
                : [];

        requests =
            Array.isArray(
                result.data?.requests
            )
                ? result.data.requests
                : [];

        csrfToken =
            result.data?.csrf_token
            || csrfToken;

        currentBranchId =
            Number(
                result.data?.current_branch_id
                || 0
            );

        const currentBranch =
            branches.find(
                row =>
                    Number(row.id)
                    === Number(currentBranchId)
            );

        $('currentBranchName').textContent =
            currentBranch?.branch_name
            || result.data?.current_branch_name
            || 'Not selected';

        renderStats(
            result.data?.stats || {}
        );

        renderBranches();
        renderRequests();

        window.lucide?.createIcons();
    }

    function wire() {
        $('branchRequestBtn')
            .addEventListener(
                'click',
                () => {
                    $('branchRequestForm')
                        .reset();

                    $('branchRequestForm')
                        .classList
                        .remove('was-validated');

                    try {
                        getBranchRequestModal().show();
                    } catch (error) {
                        toast(
                            error.message
                            || 'Unable to open Branch Request form.',
                            false
                        );
                        return;
                    }

                    setTimeout(
                        () => $('requestBranchName')
                            .focus(),
                        150
                    );
                }
            );

        $('branchRequestForm')
            .addEventListener(
                'submit',
                async event => {
                    event.preventDefault();

                    if (
                        !$('branchRequestForm')
                            .checkValidity()
                    ) {
                        $('branchRequestForm')
                            .classList
                            .add('was-validated');
                        return;
                    }

                    const button =
                        $('submitBranchRequest');

                    button.disabled = true;

                    try {
                        const result =
                            await request(
                                'create_request',
                                {
                                    branch_name:
                                        $('requestBranchName')
                                            .value
                                            .trim(),
                                    branch_code:
                                        $('requestBranchCode')
                                            .value
                                            .trim(),
                                    phone:
                                        $('requestPhone')
                                            .value
                                            .trim(),
                                    address:
                                        $('requestAddress')
                                            .value
                                            .trim()
                                },
                                'POST'
                            );

                        getBranchRequestModal().hide();

                        toast(
                            result.message
                            || 'Branch request sent successfully.',
                            true
                        );

                        await load();
                    } catch (error) {
                        toast(
                            error.message
                            || 'Unable to send branch request.',
                            false
                        );
                    } finally {
                        button.disabled = false;
                    }
                }
            );

        $('branchReload')
            .addEventListener(
                'click',
                () => {
                    load().catch(
                        error => toast(
                            error.message,
                            false
                        )
                    );
                }
            );

        $('branchSearch')
            .addEventListener(
                'input',
                renderBranches
            );
    }

    async function init() {
        wire();
        await load();
        window.lucide?.createIcons();
    }

    const start = () => {
        init().catch(error => {
            $('branchList').innerHTML = `
                <div class="psp-empty text-danger">
                    ${esc(error.message)}
                </div>
            `;

            $('requestList').innerHTML = '';

            toast(
                error.message,
                false
            );
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            start,
            {once:true}
        );
    } else {
        start();
    }
})();
</script>

<?php
require_once dirname(__DIR__) . '/includes/layout-end.php';
?>

<?php
declare(strict_types=1);

/*
 * Super Admin Branch Requests
 * Location: super-admin/branch-requests.php
 * Build: 2026-08-14-strict-branch-isolation-v48
 */

$pageTitle = 'Branch Requests';
$pageKey = 'sa_branch_requests';
$sidebarFile = __DIR__ . '/sidebar.php';

require_once dirname(__DIR__) . '/includes/bootstrap.php';

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

$roleKey = strtolower(
    trim(
        (string)(
            $currentUser['role_key']
            ?? $_SESSION['role_key']
            ?? ''
        )
    )
);

if (
    $roleKey === ''
    && isset($pdo)
    && $pdo instanceof PDO
    && $roleId > 0
) {
    try {
        $roleStmt = $pdo->prepare(
            "SELECT role_key
             FROM roles
             WHERE id = :id
               AND status = 'active'
             LIMIT 1"
        );

        $roleStmt->execute([
            'id' => $roleId,
        ]);

        $roleKey = strtolower(
            trim(
                (string)$roleStmt
                    ->fetchColumn()
            )
        );
    } catch (Throwable $exception) {
        error_log(
            'Branch Requests role: '
            . $exception->getMessage()
        );
    }
}

$isSuperAdmin =
    $roleId === 1
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
    exit(
        'Only Super Administrator can manage Branch Requests.'
    );
}


/*
 * Same-page Branch Request API bridge.
 *
 * IMPORTANT:
 * Do not call /api/super-admin-branch-requests.php directly from the browser.
 * The global Bootstrap correctly treats an unregistered /api/ route as a
 * School Support Access endpoint.
 *
 * Browser AJAX instead calls this registered Super Admin page with
 * ?branch_request_api=1. The request first passes the normal platform page
 * permission check, then this page delegates internally to the API handler.
 */
if (
    isset($_GET['branch_request_api'])
    && (string)$_GET['branch_request_api'] === '1'
) {
    require dirname(__DIR__) . '/api/super-admin-branch-requests.php';
    exit;
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

.branch-request-actions{
    display:flex;
    align-items:center;
    gap:6px;
    flex-wrap:wrap;
}
.branch-request-actions .btn-ui{
    min-height:32px;
    padding:6px 10px;
    font-size:8.5px;
}
.branch-request-actions .approve{
    color:#158458;
    border-color:#bfe9d6;
    background:#f1fbf6;
}
.branch-request-actions .reject{
    color:#c83a55;
    border-color:#ffd6df;
    background:#fff6f8;
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
.branch-status.approved{
    color:#158458;
    background:#e9f8f1;
}
.branch-status.rejected{
    color:#c83a55;
    background:#fff0f3;
}
.branch-request-meta{
    display:flex;
    flex-wrap:wrap;
    align-items:center;
    gap:7px;
    margin-top:9px;
    padding-left:53px;
}
.branch-request-meta span{
    color:#64748b;
    font-size:8.5px;
    font-weight:650;
}
.branch-filter{
    display:flex;
    align-items:center;
    gap:8px;
    flex-wrap:wrap;
}
.branch-filter .form-select{
    width:auto;
    min-width:150px;
}
@media(max-width:760px){
    .branch-request-meta{padding-left:0}
    .branch-filter{width:100%}
    .branch-filter .form-select{width:100%}
}
</style>

<div class="psp-page">
    <div class="psp-head">
        <div>
            <h1 class="page-title">Branch Requests</h1>
            <p class="page-subtitle">
                Review School Admin branch creation requests before branches are activated.
            </p>
        </div>

        <div class="page-actions">
            <button
                id="requestReload"
                class="btn-ui"
                type="button"
            >
                <i data-lucide="refresh-cw"></i>
                Refresh
            </button>
        </div>
    </div>

    <section class="psp-summary">
        <article class="psp-stat-card psp-orange">
            <div class="psp-stat-icon">
                <i data-lucide="clock-3"></i>
            </div>
            <div class="psp-stat-copy">
                <small>Pending Requests</small>
                <div id="pendingCount" class="psp-stat-value">0</div>
                <div class="psp-stat-note">Require your decision</div>
            </div>
        </article>

        <article class="psp-stat-card psp-green">
            <div class="psp-stat-icon">
                <i data-lucide="circle-check-big"></i>
            </div>
            <div class="psp-stat-copy">
                <small>Approved</small>
                <div id="approvedCount" class="psp-stat-value">0</div>
                <div class="psp-stat-note">Branches officially created</div>
            </div>
        </article>

        <article class="psp-stat-card psp-pink">
            <div class="psp-stat-icon">
                <i data-lucide="circle-x"></i>
            </div>
            <div class="psp-stat-copy">
                <small>Rejected</small>
                <div id="rejectedCount" class="psp-stat-value">0</div>
                <div class="psp-stat-note">Requests not created</div>
            </div>
        </article>

        <article class="psp-stat-card psp-purple">
            <div class="psp-stat-icon">
                <i data-lucide="git-pull-request"></i>
            </div>
            <div class="psp-stat-copy">
                <small>Total Requests</small>
                <div id="totalCount" class="psp-stat-value">0</div>
                <div class="psp-stat-note">Across all schools</div>
            </div>
        </article>
    </section>

    <div class="psp-note">
        <strong>Approval rule:</strong>
        Approve creates the branch inside the requesting school's tenant and activates it.
        Reject keeps the request history but does not create a branch.
    </div>

    <section class="psp-card">
        <div class="psp-toolbar">
            <div class="psp-search-wrap">
                <i data-lucide="search"></i>
                <input
                    id="requestSearch"
                    class="form-control"
                    placeholder="Search school, branch, code or requester..."
                    autocomplete="off"
                >
            </div>

            <div class="branch-filter">
                <select
                    id="requestStatus"
                    class="form-select"
                >
                    <option value="pending">Pending</option>
                    <option value="all">All Requests</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
        </div>

        <div id="requestList" class="psp-list">
            <div class="psp-empty">Loading Branch Requests...</div>
        </div>
    </section>
</div>

<script>
(() => {
    'use strict';

    const $ = id => document.getElementById(id);

    /*
     * Use this registered Super Admin page as the browser-facing endpoint.
     * It internally delegates to the Branch Request API after platform
     * permission validation, so no School Support Access session is needed.
     */
    const apiUrl = (() => {
        const url = new URL(
            window.location.href
        );

        url.search = '';
        url.hash = '';
        url.searchParams.set(
            'branch_request_api',
            '1'
        );

        return url.href;
    })();

    let csrfToken = '';
    let records = [];

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
            : date.toLocaleString('en-GB');
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
            url.searchParams.set(
                'action',
                action
            );

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

        const response =
            await fetch(url, options);

        const text =
            await response.text();

        let result;

        try {
            result = JSON.parse(text);
        } catch (error) {
            console.error(
                'Branch Request same-page API raw response:',
                text
            );

            const cleaned = String(text || '')
                .replace(/<[^>]*>/g, ' ')
                .replace(/\s+/g, ' ')
                .trim();

            throw new Error(
                cleaned
                    ? `Branch Request API error: ${cleaned.slice(0, 220)}`
                    : `Branch Request API returned an empty response (HTTP ${response.status}).`
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
        $('pendingCount').textContent =
            String(stats.pending || 0);

        $('approvedCount').textContent =
            String(stats.approved || 0);

        $('rejectedCount').textContent =
            String(stats.rejected || 0);

        $('totalCount').textContent =
            String(stats.total || 0);
    }

    function render() {
        const query =
            $('requestSearch')
                .value
                .trim()
                .toLowerCase();

        const rows = records.filter(
            row => {
                if (!query) return true;

                return [
                    row.school_name,
                    row.tenant_code,
                    row.branch_name,
                    row.branch_code,
                    row.requested_by_name,
                    row.phone,
                    row.address
                ]
                    .join(' ')
                    .toLowerCase()
                    .includes(query);
            }
        );

        if (!rows.length) {
            $('requestList').innerHTML = `
                <div class="psp-empty">
                    No Branch Requests found.
                </div>
            `;
            return;
        }

        $('requestList').innerHTML =
            rows.map(row => {
                const pending =
                    row.status === 'pending';

                return `
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
                                            row.school_name
                                            || 'School'
                                        )}
                                        ${
                                            row.tenant_code
                                                ? `· ${esc(
                                                    row.tenant_code
                                                )}`
                                                : ''
                                        }
                                    </small>
                                </div>
                            </div>

                            ${
                                pending
                                    ? `
                                        <div class="branch-request-actions">
                                            <button
                                                class="btn-ui approve js-approve"
                                                type="button"
                                                data-id="${Number(row.id)}"
                                            >
                                                <i data-lucide="check"></i>
                                                Approve
                                            </button>

                                            <button
                                                class="btn-ui reject js-reject"
                                                type="button"
                                                data-id="${Number(row.id)}"
                                            >
                                                <i data-lucide="x"></i>
                                                Reject
                                            </button>
                                        </div>
                                    `
                                    : `
                                        <span class="branch-status ${
                                            esc(row.status)
                                        }">
                                            ${esc(row.status)}
                                        </span>
                                    `
                            }
                        </div>

                        <div class="branch-request-meta">
                            <span>
                                Code: ${esc(
                                    row.branch_code || '—'
                                )}
                            </span>

                            <span>
                                Requested by:
                                ${esc(
                                    row.requested_by_name
                                    || 'School Admin'
                                )}
                            </span>

                            <span>
                                ${esc(
                                    dateText(row.created_at)
                                )}
                            </span>

                            ${
                                row.phone
                                    ? `<span>Phone: ${
                                        esc(row.phone)
                                    }</span>`
                                    : ''
                            }

                            ${
                                row.address
                                    ? `<span>${
                                        esc(row.address)
                                    }</span>`
                                    : ''
                            }

                            ${
                                row.decision_notes
                                    ? `<span>Decision note: ${
                                        esc(
                                            row.decision_notes
                                        )
                                    }</span>`
                                    : ''
                            }
                        </div>
                    </div>
                `;
            }).join('');

        document
            .querySelectorAll(
                '.js-approve'
            )
            .forEach(button => {
                button.addEventListener(
                    'click',
                    () => decide(
                        Number(
                            button.dataset.id
                        ),
                        'approve'
                    )
                );
            });

        document
            .querySelectorAll(
                '.js-reject'
            )
            .forEach(button => {
                button.addEventListener(
                    'click',
                    () => decide(
                        Number(
                            button.dataset.id
                        ),
                        'reject'
                    )
                );
            });

        window.lucide?.createIcons();
    }

    async function load() {
        $('requestList').innerHTML =
            '<div class="psp-empty">Loading Branch Requests...</div>';

        const result =
            await request(
                'load',
                {
                    status:
                        $('requestStatus')
                            .value
                }
            );

        records =
            Array.isArray(
                result.data?.records
            )
                ? result.data.records
                : [];

        csrfToken =
            result.data?.csrf_token
            || csrfToken;

        renderStats(
            result.data?.stats || {}
        );

        render();
    }

    async function decide(
        requestId,
        decision
    ) {
        const isApprove =
            decision === 'approve';

        let notes = '';

        if (isApprove) {
            if (
                !window.confirm(
                    'Approve this Branch Request and create the branch as Active?'
                )
            ) {
                return;
            }
        } else {
            notes =
                window.prompt(
                    'Enter rejection reason (optional):',
                    ''
                );

            if (notes === null) {
                return;
            }
        }

        try {
            const result =
                await request(
                    'decide',
                    {
                        request_id:
                            requestId,
                        decision,
                        decision_notes:
                            notes
                    },
                    'POST'
                );

            toast(
                result.message,
                true
            );

            await load();
        } catch (error) {
            toast(
                error.message,
                false
            );
        }
    }

    function wire() {
        $('requestReload')
            .addEventListener(
                'click',
                () => load().catch(
                    error => toast(
                        error.message,
                        false
                    )
                )
            );

        $('requestStatus')
            .addEventListener(
                'change',
                () => load().catch(
                    error => toast(
                        error.message,
                        false
                    )
                )
            );

        $('requestSearch')
            .addEventListener(
                'input',
                render
            );
    }

    async function init() {
        wire();
        await load();
        window.lucide?.createIcons();
    }

    const start = () => {
        init().catch(error => {
            $('requestList').innerHTML = `
                <div class="psp-empty text-danger">
                    ${esc(error.message)}
                </div>
            `;

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

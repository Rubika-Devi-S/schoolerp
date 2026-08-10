<?php
declare(strict_types=1);

$pageTitle = 'Staff Leave Management';
$pageKey = 'leave_management';

require dirname(__DIR__) . '/includes/layout-start.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (
    empty($_SESSION['staff_leave_csrf'])
    || !is_string($_SESSION['staff_leave_csrf'])
) {
    $_SESSION['staff_leave_csrf'] = bin2hex(random_bytes(32));
}

$leaveCsrf = (string)$_SESSION['staff_leave_csrf'];

$leavePermissions = function_exists('school_current_page_capabilities')
    ? school_current_page_capabilities($pageKey)
    : [
        'page_key' => $pageKey,
        'view' => function_exists('has_permission')
            ? has_permission($pageKey, 'view')
            : false,
        'add' => function_exists('has_permission')
            ? has_permission($pageKey, 'create')
            : false,
        'create' => function_exists('has_permission')
            ? has_permission($pageKey, 'create')
            : false,
        'edit' => function_exists('has_permission')
            ? has_permission($pageKey, 'edit')
            : false,
        'delete' => function_exists('has_permission')
            ? has_permission($pageKey, 'delete')
            : false,
    ];

if (empty($leavePermissions['view'])) {
    http_response_code(403);
    exit('You do not have permission to view this page.');
}
?>
<style>
.sl-page{display:grid;gap:16px}
.sl-page .page-title{font-size:28px;line-height:1.1}
.sl-page .page-subtitle{margin-top:4px}
.sl-message{display:none}
.sl-message.show{display:block}
.sl-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.sl-stat{border:0;border-radius:14px;min-height:100px;padding:17px 18px;color:#fff;display:flex;align-items:center;gap:13px;position:relative;overflow:hidden}
.sl-stat::after{content:"";position:absolute;width:100px;height:100px;border-radius:50%;right:-34px;top:-38px;background:rgba(255,255,255,.08)}
.sl-stat.purple{background:linear-gradient(135deg,#7158e8,#4f46d9)}
.sl-stat.orange{background:linear-gradient(135deg,#ffb327,#ff8a17)}
.sl-stat.green{background:linear-gradient(135deg,#45c783,#1ea568)}
.sl-stat.red{background:linear-gradient(135deg,#ff647d,#dc3545)}
.sl-stat span{width:44px;height:44px;border-radius:50%;background:rgba(255,255,255,.16);display:grid;place-items:center}
.sl-stat svg{width:22px;height:22px}
.sl-stat strong{display:block;font-size:23px;line-height:1}
.sl-stat small{display:block;font-size:10px;font-weight:800;margin-bottom:6px}
.sl-card{border-radius:14px;overflow:hidden}
.sl-card-head{padding:14px 16px;border-bottom:1px solid var(--border-soft,#e7ebf3);display:flex;align-items:center;justify-content:space-between;gap:10px}
.sl-filter{padding:14px 16px;display:grid;grid-template-columns:minmax(250px,1.4fr) minmax(170px,.7fr) auto;gap:10px}
.sl-table-wrap{overflow:auto}
.sl-table{min-width:1050px}
.sl-table th{font-size:10px;white-space:nowrap}
.sl-table td{font-size:11px;vertical-align:middle}
.sl-empty{padding:42px 18px!important;text-align:center!important;color:var(--text-muted,#64748b)}
.sl-badge{display:inline-flex;padding:5px 9px;border-radius:999px;font-size:9px;font-weight:800;text-transform:capitalize}
.sl-badge.pending{color:#9a6700;background:#fff7d6}
.sl-badge.approved{color:#16834f;background:#e8f8ef}
.sl-badge.rejected{color:#dc2626;background:#fff0f1}
.sl-actions{display:flex;gap:5px;flex-wrap:wrap}
.sl-footer{padding:14px 16px;border-top:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;gap:10px;align-items:center}
.sl-pages{display:flex;gap:6px;flex-wrap:wrap}
.sl-page-btn{min-width:34px;height:34px;border:1px solid var(--border-soft,#e7ebf3);background:#fff;border-radius:8px;font-size:11px;font-weight:800}
.sl-page-btn.active{color:#fff;border-color:transparent;background:linear-gradient(135deg,#6547e8,#315ed8)}
.sl-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.sl-form-grid .full{grid-column:1/-1}
.sl-view-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.sl-view-grid div{padding:12px;border:1px solid var(--border-soft,#e7ebf3);border-radius:10px}
.sl-view-grid small{display:block;color:var(--text-muted,#64748b);font-size:9px;font-weight:700}
.sl-view-grid strong{display:block;margin-top:4px;font-size:12px}
@media(max-width:800px){
    .sl-stats,.sl-filter,.sl-form-grid,.sl-view-grid{grid-template-columns:1fr}
    .sl-form-grid .full{grid-column:auto}
    .sl-card-head,.sl-footer{align-items:flex-start;flex-direction:column}
}
</style>

<div class="sl-page">
    <div class="page-heading">
        <div>
            <h1 class="page-title">Staff Leave Management</h1>
            <p class="page-subtitle">Manage staff leave requests and approvals.</p>
        </div>

        <div class="page-actions">
            <?php if (!empty($leavePermissions['add'])): ?>
                <button
                    id="addLeaveBtn"
                    class="btn-ui btn-primary-ui"
                    type="button"
                    data-permission-action="add"
                >
                    <i data-lucide="plus"></i>
                    Add Leave
                </button>
            <?php endif; ?>

            <button id="refreshBtn" class="btn-ui" type="button">
                <i data-lucide="refresh-cw"></i>
                Refresh
            </button>
        </div>
    </div>

    <div id="slMessage" class="alert sl-message"></div>

    <section class="sl-stats">
        <article class="sl-stat purple">
            <span><i data-lucide="calendar-days"></i></span>
            <div><small>Total Requests</small><strong id="statTotal">0</strong></div>
        </article>
        <article class="sl-stat orange">
            <span><i data-lucide="clock-3"></i></span>
            <div><small>Pending</small><strong id="statPending">0</strong></div>
        </article>
        <article class="sl-stat green">
            <span><i data-lucide="circle-check"></i></span>
            <div><small>Approved</small><strong id="statApproved">0</strong></div>
        </article>
        <article class="sl-stat red">
            <span><i data-lucide="circle-x"></i></span>
            <div><small>Rejected</small><strong id="statRejected">0</strong></div>
        </article>
    </section>

    <section class="ui-card sl-card">
        <div class="sl-card-head">
            <strong>Staff Leave List</strong>
            <small class="text-muted">Search and manage leave requests.</small>
        </div>

        <div class="sl-filter">
            <input
                id="leaveSearch"
                class="form-control"
                placeholder="Search staff name, Staff ID or mobile..."
            >
            <select id="statusFilter" class="form-select">
                <option value="all">All Statuses</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
            </select>
            <button id="resetBtn" class="btn-ui" type="button">
                <i data-lucide="rotate-ccw"></i>
                Reset
            </button>
        </div>

        <div class="sl-table-wrap">
            <table class="data-table sl-table">
                <thead>
                    <tr>
                        <th>Staff Name</th>
                        <th>Leave Type</th>
                        <th>From Date</th>
                        <th>To Date</th>
                        <th>Total Days</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="leaveBody">
                    <tr>
                        <td colspan="8" class="sl-empty">Loading leave requests...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="sl-footer">
            <small id="recordInfo" class="text-muted"></small>
            <div id="pagination" class="sl-pages"></div>
        </div>
    </section>
</div>

<div class="modal fade" id="leaveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="leaveForm" novalidate>
                <div class="modal-header">
                    <div>
                        <h5 id="leaveModalTitle" class="modal-title">Add Staff Leave</h5>
                        <small class="text-muted">Enter leave request details.</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <input id="leaveId" type="hidden">

                    <div class="sl-form-grid">
                        <div class="full">
                            <label class="form-label">Staff Name *</label>
                            <select id="staffId" class="form-select" required></select>
                        </div>

                        <div>
                            <label class="form-label">Leave Type *</label>
                            <select id="leaveType" class="form-select" required>
                                <option value="casual">Casual</option>
                                <option value="sick">Sick</option>
                                <option value="earned">Earned</option>
                                <option value="loss_of_pay">Loss of Pay</option>
                            </select>
                        </div>

                        <div>
                            <label class="form-label">Status *</label>
                            <select id="leaveStatus" class="form-select" required>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>

                        <div>
                            <label class="form-label">From Date *</label>
                            <input id="fromDate" type="date" class="form-control" required>
                        </div>

                        <div>
                            <label class="form-label">To Date *</label>
                            <input id="toDate" type="date" class="form-control" required>
                        </div>

                        <div>
                            <label class="form-label">Total Days</label>
                            <input id="totalDays" class="form-control" readonly>
                        </div>

                        <div class="full">
                            <label class="form-label">Reason *</label>
                            <textarea
                                id="reason"
                                class="form-control"
                                rows="4"
                                maxlength="500"
                                required
                            ></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-ui" data-bs-dismiss="modal">Cancel</button>
                    <button
                        id="saveLeaveBtn"
                        type="submit"
                        class="btn-ui btn-primary-ui"
                        data-permission-action="add"
                    >
                        <i data-lucide="save"></i>
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="viewLeaveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Leave Details</h5>
                    <small class="text-muted">Complete leave request information.</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div id="viewLeaveDetails" class="sl-view-grid"></div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-ui" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    const apiUrl = new URL('../api/staff-leave.php', window.location.href).href;
    let csrfToken = <?= json_encode($leaveCsrf, JSON_UNESCAPED_SLASHES) ?>;

    const permissions = <?= json_encode([
        'view' => !empty($leavePermissions['view']),
        'add' => !empty($leavePermissions['add']),
        'edit' => !empty($leavePermissions['edit']),
        'delete' => !empty($leavePermissions['delete']),
    ], JSON_UNESCAPED_SLASHES) ?>;

    let rows = [];
    let staff = [];
    let page = 1;
    let lastPage = 1;
    let timer = null;

    const $ = id => document.getElementById(id);
    const esc = value => String(value ?? '').replace(
        /[&<>"']/g,
        character => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        })[character]
    );

    const can = action => permissions[action] === true;

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
                `Staff Leave API returned HTTP ${response.status}. `
                + (text.replace(/\s+/g, ' ').trim().slice(0, 220)
                    || 'Invalid response.')
            );
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
        const box = $('slMessage');
        if (!box) return;

        box.className = 'alert sl-message show '
            + (success ? 'alert-success' : 'alert-danger');
        box.textContent = text;

        clearTimeout(box._timer);
        box._timer = setTimeout(() => {
            box.className = 'alert sl-message';
        }, 6000);
    }

    function badge(value) {
        return `<span class="sl-badge ${esc(value)}">${
            esc(String(value).replace('_', ' '))
        }</span>`;
    }

    function typeLabel(value) {
        return ({
            casual: 'Casual',
            sick: 'Sick',
            earned: 'Earned',
            loss_of_pay: 'Loss of Pay'
        })[value] || value;
    }

    function calcDays() {
        const from = $('fromDate')?.value || '';
        const to = $('toDate')?.value || '';

        if (!from || !to) {
            $('totalDays').value = '';
            return;
        }

        const start = new Date(from + 'T00:00:00');
        const end = new Date(to + 'T00:00:00');

        $('totalDays').value = end >= start
            ? Math.floor((end - start) / 86400000) + 1
            : '';
    }

    function fillStaff() {
        $('staffId').innerHTML = '<option value="">Select Staff</option>'
            + staff.map(item => (
                `<option value="${item.id}">${
                    esc(item.staff_code)
                } - ${esc(item.staff_name)}</option>`
            )).join('');
    }

    function actionButtons(record) {
        const buttons = [];

        if (can('view')) {
            buttons.push(
                `<button class="btn-ui btn-sm js-view" `
                + `data-id="${record.id}" type="button" `
                + `data-permission-action="view">View</button>`
            );
        }

        if (can('edit')) {
            buttons.push(
                `<button class="btn-ui btn-sm js-edit" `
                + `data-id="${record.id}" type="button" `
                + `data-permission-action="edit">Edit</button>`
            );

            if (record.status === 'pending') {
                buttons.push(
                    `<button class="btn-ui btn-sm js-status" `
                    + `data-id="${record.id}" data-status="approved" `
                    + `type="button" data-permission-action="edit">Approve</button>`
                );
                buttons.push(
                    `<button class="btn-ui btn-sm js-status" `
                    + `data-id="${record.id}" data-status="rejected" `
                    + `type="button" data-permission-action="edit">Reject</button>`
                );
            }
        }

        if (can('delete')) {
            buttons.push(
                `<button class="btn-ui btn-sm js-delete" `
                + `data-id="${record.id}" type="button" `
                + `data-permission-action="delete">Delete</button>`
            );
        }

        return `<div class="sl-actions">${buttons.join('')}</div>`;
    }

    function render() {
        $('leaveBody').innerHTML = rows.map(record => `
            <tr>
                <td>
                    <strong>${esc(record.staff_name)}</strong>
                    <div class="text-muted">${esc(record.staff_code)}</div>
                </td>
                <td>${esc(typeLabel(record.leave_type))}</td>
                <td>${esc(record.from_date_display)}</td>
                <td>${esc(record.to_date_display)}</td>
                <td><strong>${esc(record.total_days)}</strong></td>
                <td title="${esc(record.reason)}">${esc(record.reason_short)}</td>
                <td>${badge(record.status)}</td>
                <td>${actionButtons(record)}</td>
            </tr>
        `).join('') || `
            <tr>
                <td colspan="8" class="sl-empty">No leave requests found.</td>
            </tr>
        `;

        document.querySelectorAll('.js-view').forEach(button => {
            button.addEventListener('click', () => openView(Number(button.dataset.id)));
        });

        document.querySelectorAll('.js-edit').forEach(button => {
            button.addEventListener('click', () => openEdit(Number(button.dataset.id)));
        });

        document.querySelectorAll('.js-status').forEach(button => {
            button.addEventListener('click', () => changeStatus(
                Number(button.dataset.id),
                String(button.dataset.status || '')
            ));
        });

        document.querySelectorAll('.js-delete').forEach(button => {
            button.addEventListener('click', () => removeLeave(Number(button.dataset.id)));
        });

        window.lucide?.createIcons();
    }

    function renderPages() {
        const box = $('pagination');
        box.innerHTML = '';

        for (
            let number = Math.max(1, page - 2);
            number <= Math.min(lastPage, page + 2);
            number += 1
        ) {
            const button = document.createElement('button');
            button.className = 'sl-page-btn' + (number === page ? ' active' : '');
            button.type = 'button';
            button.textContent = String(number);
            button.addEventListener('click', () => {
                page = number;
                load();
            });
            box.appendChild(button);
        }
    }

    async function load() {
        try {
            const result = await request('list', {
                page,
                per_page: 10,
                search: $('leaveSearch').value.trim(),
                status: $('statusFilter').value
            });

            rows = result.data.records || [];
            page = Number(result.data.pagination.page || 1);
            lastPage = Number(result.data.pagination.last_page || 1);

            $('recordInfo').textContent =
                `Showing ${rows.length} of ${result.data.pagination.total} request(s)`;

            const stats = result.data.stats || {};
            $('statTotal').textContent = stats.total || 0;
            $('statPending').textContent = stats.pending || 0;
            $('statApproved').textContent = stats.approved || 0;
            $('statRejected').textContent = stats.rejected || 0;

            render();
            renderPages();
        } catch (error) {
            message(error.message);
        }
    }

    function resetForm() {
        $('leaveForm').reset();
        $('leaveId').value = '';
        $('leaveStatus').value = 'pending';
        $('leaveStatus').disabled = !can('edit');
        $('leaveModalTitle').textContent = 'Add Staff Leave';
        $('totalDays').value = '';
        $('saveLeaveBtn').dataset.permissionAction = 'add';
        fillStaff();
    }

    function openAdd() {
        if (!can('add')) return;

        resetForm();
        bootstrap.Modal.getOrCreateInstance($('leaveModal')).show();
    }

    function openEdit(id) {
        if (!can('edit')) return;

        const record = rows.find(item => Number(item.id) === id);
        if (!record) return;

        resetForm();
        $('leaveModalTitle').textContent = 'Edit Staff Leave';
        $('leaveId').value = String(id);
        $('staffId').value = String(record.staff_id);
        $('leaveType').value = record.leave_type;
        $('fromDate').value = record.from_date;
        $('toDate').value = record.to_date;
        $('reason').value = record.reason;
        $('leaveStatus').disabled = false;
        $('leaveStatus').value = record.status;
        $('saveLeaveBtn').dataset.permissionAction = 'edit';
        calcDays();

        bootstrap.Modal.getOrCreateInstance($('leaveModal')).show();
    }

    async function openView(id) {
        if (!can('view')) return;

        try {
            const record = (await request('detail', {id})).data.record;

            $('viewLeaveDetails').innerHTML = `
                <div><small>Staff</small><strong>${
                    esc(record.staff_name)
                } (${esc(record.staff_code)})</strong></div>
                <div><small>Leave Type</small><strong>${
                    esc(typeLabel(record.leave_type))
                }</strong></div>
                <div><small>From Date</small><strong>${
                    esc(record.from_date_display)
                }</strong></div>
                <div><small>To Date</small><strong>${
                    esc(record.to_date_display)
                }</strong></div>
                <div><small>Total Days</small><strong>${
                    esc(record.total_days)
                }</strong></div>
                <div><small>Status</small><strong>${
                    badge(record.status)
                }</strong></div>
                <div class="full"><small>Reason</small><strong>${
                    esc(record.reason)
                }</strong></div>
                <div><small>Created By</small><strong>${
                    esc(record.created_by_name || '-')
                }</strong></div>
                <div><small>Decision By</small><strong>${
                    esc(record.decided_by_name || '-')
                }</strong></div>
            `;

            bootstrap.Modal.getOrCreateInstance($('viewLeaveModal')).show();
        } catch (error) {
            message(error.message);
        }
    }

    async function changeStatus(id, status) {
        if (!can('edit')) return;

        const label = status === 'approved' ? 'Approve' : 'Reject';
        if (!window.confirm(`${label} this leave request?`)) return;

        try {
            const result = await request('status', {id, status}, 'POST');
            message(result.message, true);
            await load();
        } catch (error) {
            message(error.message);
        }
    }

    async function removeLeave(id) {
        if (!can('delete')) return;
        if (!window.confirm('Delete this leave request?')) return;

        try {
            const result = await request('delete', {id}, 'POST');
            message(result.message, true);
            await load();
        } catch (error) {
            message(error.message);
        }
    }

    $('leaveForm')?.addEventListener('submit', async event => {
        event.preventDefault();

        const id = Number($('leaveId').value || 0);
        const requiredAction = id > 0 ? 'edit' : 'add';

        if (!can(requiredAction)) {
            message(`You do not have ${requiredAction} permission for Staff Leave.`);
            return;
        }

        const form = event.currentTarget;
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return;
        }

        try {
            const result = await request('save', {
                id,
                staff_id: Number($('staffId').value || 0),
                leave_type: $('leaveType').value,
                from_date: $('fromDate').value,
                to_date: $('toDate').value,
                reason: $('reason').value.trim(),
                status: $('leaveStatus').value
            }, 'POST');

            bootstrap.Modal.getInstance($('leaveModal'))?.hide();
            message(result.message, true);
            await load();
        } catch (error) {
            message(error.message);
        }
    });

    $('fromDate')?.addEventListener('change', calcDays);
    $('toDate')?.addEventListener('change', calcDays);
    $('addLeaveBtn')?.addEventListener('click', openAdd);
    $('refreshBtn')?.addEventListener('click', load);

    $('resetBtn')?.addEventListener('click', () => {
        $('leaveSearch').value = '';
        $('statusFilter').value = 'all';
        page = 1;
        load();
    });

    $('statusFilter')?.addEventListener('change', () => {
        page = 1;
        load();
    });

    $('leaveSearch')?.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            page = 1;
            load();
        }, 300);
    });

    (async () => {
        try {
            const result = await request('meta');
            staff = result.data.staff || [];

            if (result.data.permissions) {
                permissions.view = result.data.permissions.view === true;
                permissions.add = result.data.permissions.add === true;
                permissions.edit = result.data.permissions.edit === true;
                permissions.delete = result.data.permissions.delete === true;
            }

            fillStaff();
            await load();
        } catch (error) {
            message(error.message);
        }
    })();

    window.lucide?.createIcons();
})();
</script>

<?php require dirname(__DIR__) . '/includes/layout-end.php'; ?>

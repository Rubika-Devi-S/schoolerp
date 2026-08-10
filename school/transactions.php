<?php
declare(strict_types=1);

$pageTitle = 'Transaction Management';
$pageKey = 'transaction_management';

require dirname(__DIR__) . '/includes/layout-start.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['transaction_csrf_token']) || !is_string($_SESSION['transaction_csrf_token'])) {
    $_SESSION['transaction_csrf_token'] = bin2hex(random_bytes(32));
}
$transactionCsrf = $_SESSION['transaction_csrf_token'];
?>

<style>
.transaction-page{display:grid;gap:16px}
.transaction-page .page-title{font-size:28px;line-height:1.1}
.transaction-page .page-subtitle{margin-top:4px}
.transaction-message{display:none}.transaction-message.show{display:block}
.transaction-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.transaction-stat{border-radius:14px;min-height:110px;padding:18px 20px;display:flex;align-items:center;gap:14px;color:#fff;position:relative;overflow:hidden;box-shadow:0 12px 28px rgba(15,23,42,.08)}
.transaction-stat::after{content:"";position:absolute;width:110px;height:110px;border-radius:50%;right:-38px;top:-40px;background:rgba(255,255,255,.08)}
.transaction-stat.green{background:linear-gradient(135deg,#45c783,#1ea568)}
.transaction-stat.red{background:linear-gradient(135deg,#ef5350,#c62828)}
.transaction-stat.blue{background:linear-gradient(135deg,#4696ef,#2469d5)}
.transaction-stat.orange{background:linear-gradient(135deg,#ffb327,#ff8a17)}
.transaction-stat-icon{width:50px;height:50px;border-radius:50%;background:rgba(255,255,255,.16);display:grid;place-items:center;flex:0 0 auto}
.transaction-stat-icon svg{width:25px;height:25px}
.transaction-stat strong{display:block;font-size:25px;line-height:1}
.transaction-stat small{display:block;font-size:11px;font-weight:700;opacity:.95;margin-bottom:6px}
.transaction-stat .trend{font-size:9px;font-weight:700;opacity:.94;margin-top:8px}
.transaction-tabs{display:flex;gap:8px;overflow:auto;padding:10px}
.transaction-tab{border:1px solid var(--border-soft,#e7ebf3);background:var(--card-bg,#fff);color:var(--text-main,#101b46);border-radius:9px;padding:9px 12px;font-size:11px;font-weight:800;white-space:nowrap}
.transaction-tab.active{color:#fff;border-color:transparent;background:linear-gradient(135deg,#6747e8,#2f62d7)}
.transaction-panel{display:none}.transaction-panel.active{display:block}
.transaction-card{border-radius:14px;overflow:hidden}
.transaction-card-head{padding:14px 16px;border-bottom:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;align-items:center;gap:10px}
.transaction-filter{padding:14px 16px;display:grid;grid-template-columns:minmax(200px,1fr) repeat(5,minmax(120px,.6fr)) auto;gap:10px;border-bottom:1px solid var(--border-soft,#e7ebf3)}
.transaction-table-wrap{overflow:auto}
.transaction-table{min-width:1100px}
.transaction-table th{font-size:10px}
.transaction-table td{font-size:11px;vertical-align:middle}
.transaction-empty{padding:40px 18px!important;text-align:center!important;color:var(--text-muted,#64748b)}
.transaction-badge{display:inline-flex;align-items:center;padding:5px 9px;border-radius:999px;font-size:9px;font-weight:800;text-transform:capitalize}
.transaction-badge.completed{color:#16834f;background:#e8f8ef}
.transaction-badge.pending{color:#9a6700;background:#fff7d6}
.transaction-badge.cancelled{color:#dc2626;background:#fff0f1}
.transaction-badge.income{color:#16834f;background:#e8f8ef}
.transaction-badge.expense{color:#dc2626;background:#fff0f1}
.transaction-actions{display:flex;gap:5px;flex-wrap:wrap}
.transaction-action{width:30px;height:30px;display:grid;place-items:center;border:1px solid #d7def1;border-radius:7px;background:var(--card-bg,#fff);color:#4f46e5}
.transaction-action.danger{color:#dc2626}.transaction-action.success{color:#16834f}
.transaction-action svg{width:13px;height:13px}
.transaction-pagination{padding:14px 16px;border-top:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;align-items:center;gap:10px}
.transaction-page-buttons{display:flex;gap:6px;flex-wrap:wrap}
.transaction-page-button{min-width:34px;height:34px;border:1px solid var(--border-soft,#e7ebf3);background:#fff;border-radius:8px;font-size:11px;font-weight:800}
.transaction-page-button.active{color:#fff;border-color:transparent;background:linear-gradient(135deg,#6547e8,#315ed8)}
.transaction-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.transaction-form-grid .full{grid-column:1/-1}
.transaction-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
.transaction-summary>div{border:1px solid var(--border-soft,#e7ebf3);border-radius:10px;padding:12px}
.transaction-summary small{display:block;font-size:9px;color:var(--text-muted,#64748b);font-weight:700}
.transaction-summary strong{display:block;margin-top:4px;font-size:14px}
#transactionModal .modal-dialog,#viewTransactionModal .modal-dialog{max-height:calc(100dvh - 32px);margin:16px auto}
#transactionModal .modal-content,#viewTransactionModal .modal-content{max-height:calc(100dvh - 32px);overflow:hidden}
#transactionModal form{display:flex;flex-direction:column;max-height:calc(100dvh - 32px)}
#transactionModal .modal-body,#viewTransactionModal .modal-body{overflow-y:auto;min-height:0}
@media(max-width:1200px){.transaction-filter{grid-template-columns:repeat(3,1fr)}}
@media(max-width:900px){.transaction-stats,.transaction-summary{grid-template-columns:repeat(2,1fr)}}
@media(max-width:575px){.transaction-stats,.transaction-filter,.transaction-form-grid,.transaction-summary{grid-template-columns:1fr}.transaction-form-grid .full{grid-column:auto}.transaction-card-head,.transaction-pagination{align-items:flex-start;flex-direction:column}}
</style>

<div class="transaction-page" data-page="transaction">
    <div class="page-heading">
        <div>
            <h1 class="page-title">Transaction Management</h1>
            <p class="page-subtitle">Manage all financial transactions, track income and expenses.</p>
        </div>
        <div class="page-actions">
            <button id="addTransactionBtn" class="btn-ui btn-primary-ui" type="button"><i data-lucide="plus"></i> Add Transaction</button>
            <button id="exportBtn" class="btn-ui" type="button"><i data-lucide="download"></i> Export</button>
            <button id="printBtn" class="btn-ui" type="button"><i data-lucide="printer"></i> Print</button>
            <button id="refreshBtn" class="btn-ui" type="button"><i data-lucide="refresh-cw"></i> Refresh</button>
        </div>
    </div>

    <div id="transactionMessage" class="alert transaction-message"></div>

    <section class="transaction-stats">
        <article class="transaction-stat green">
            <span class="transaction-stat-icon"><i data-lucide="indian-rupee"></i></span>
            <div>
                <small>Total Income</small>
                <strong id="statIncome">₹0</strong>
                <div class="trend">All time income</div>
            </div>
        </article>
        <article class="transaction-stat red">
            <span class="transaction-stat-icon"><i data-lucide="indian-rupee"></i></span>
            <div>
                <small>Total Expense</small>
                <strong id="statExpense">₹0</strong>
                <div class="trend">All time expenses</div>
            </div>
        </article>
        <article class="transaction-stat blue">
            <span class="transaction-stat-icon"><i data-lucide="wallet"></i></span>
            <div>
                <small>Net Balance</small>
                <strong id="statBalance">₹0</strong>
                <div class="trend">Income - Expenses</div>
            </div>
        </article>
        <article class="transaction-stat orange">
            <span class="transaction-stat-icon"><i data-lucide="clock-3"></i></span>
            <div>
                <small>Pending</small>
                <strong id="statPending">0</strong>
                <div class="trend">Awaiting completion</div>
            </div>
        </article>
    </section>

    <section class="ui-card transaction-tabs">
        <button class="transaction-tab active" data-tab="list" type="button">All Transactions</button>
        <button class="transaction-tab" data-tab="summary" type="button">Summary</button>
    </section>

    <section class="transaction-panel active" data-panel="list">
        <section class="ui-card transaction-card">
            <div class="transaction-card-head">
                <strong>Transaction List</strong>
                <small id="recordInfo" class="text-muted">Loading...</small>
            </div>
            <div class="transaction-filter">
                <input id="search" class="form-control" placeholder="Search transaction no, party...">
                <select id="typeFilter" class="form-select">
                    <option value="all">All Types</option>
                    <option value="income">Income</option>
                    <option value="expense">Expense</option>
                </select>
                <select id="categoryFilter" class="form-select">
                    <option value="all">All Categories</option>
                </select>
                <select id="statusFilter" class="form-select">
                    <option value="all">All Statuses</option>
                    <option value="completed">Completed</option>
                    <option value="pending">Pending</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                <input id="dateFrom" class="form-control" type="date" placeholder="Date From">
                <input id="dateTo" class="form-control" type="date" placeholder="Date To">
                <button id="resetBtn" class="btn-ui" type="button"><i data-lucide="rotate-ccw"></i> Reset</button>
            </div>
            <div class="transaction-table-wrap">
                <table class="data-table transaction-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Transaction No.</th>
                            <th>Type</th>
                            <th>Category</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Payment</th>
                            <th>Party</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="transactionBody">
                        <tr>
                            <td colspan="10" class="transaction-empty">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="transaction-pagination">
                <small id="pageInfo" class="text-muted"></small>
                <div id="pagination" class="transaction-page-buttons"></div>
            </div>
        </section>
    </section>

    <section class="transaction-panel" data-panel="summary">
        <section class="ui-card transaction-card">
            <div class="transaction-card-head"><strong>Transaction Summary by Category</strong></div>
            <div class="transaction-table-wrap">
                <table class="data-table transaction-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Transactions</th>
                            <th>Total Income</th>
                            <th>Total Expense</th>
                            <th>Net</th>
                        </tr>
                    </thead>
                    <tbody id="summaryBody"></tbody>
                </table>
            </div>
        </section>
    </section>
</div>

<!-- Add/Edit Transaction Modal -->
<div class="modal fade" id="transactionModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <form id="transactionForm">
                <div class="modal-header">
                    <h5 id="transactionModalTitle" class="modal-title">Add Transaction</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body transaction-form-grid">
                    <input id="transactionId" type="hidden">
                    <input id="transactionNo" type="hidden">
                    
                    <div>
                        <label class="form-label">Transaction Type *</label>
                        <select id="transactionType" class="form-select" required>
                            <option value="income">Income</option>
                            <option value="expense">Expense</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Category *</label>
                        <select id="category" class="form-select" required>
                            <option value="fees">Fees</option>
                            <option value="salary">Salary</option>
                            <option value="utilities">Utilities</option>
                            <option value="stationery">Stationery</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="transport">Transport</option>
                            <option value="donation">Donation</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Amount *</label>
                        <input id="amount" class="form-control" type="number" step="0.01" required placeholder="0.00">
                    </div>
                    <div>
                        <label class="form-label">Transaction Date *</label>
                        <input id="transactionDate" class="form-control" type="date" required>
                    </div>
                    <div>
                        <label class="form-label">Payment Method</label>
                        <select id="paymentMethod" class="form-select">
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cheque">Cheque</option>
                            <option value="online">Online</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Reference No</label>
                        <input id="referenceNo" class="form-control" placeholder="Receipt/Invoice/Cheque No">
                    </div>
                    <div>
                        <label class="form-label">Party Name</label>
                        <input id="partyName" class="form-control" placeholder="Customer/Vendor/Student name">
                    </div>
                    <div>
                        <label class="form-label">Status *</label>
                        <select id="transactionStatus" class="form-select">
                            <option value="pending">Pending</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="full">
                        <label class="form-label">Description *</label>
                        <textarea id="description" class="form-control" rows="2" required placeholder="Transaction description..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-ui" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-ui btn-primary-ui">Save Transaction</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Transaction Modal -->
<div class="modal fade" id="viewTransactionModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Transaction Details</h5>
                    <small id="viewSubtitle" class="text-muted"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="viewContent" class="transaction-summary"></div>
                <div id="viewDescription" class="alert alert-light mt-3"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-ui" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    // ============================================
    // FIXED: CORRECT API URL PATH
    // ============================================
    // Since frontend is in school/ folder and API is in api/ folder (sibling to school/)
    // we need to go up one level using ../
    const apiUrl = '../api/transactions.php';
    
    // For debugging - check in browser console
    console.log('API URL:', apiUrl);
    console.log('Current page:', window.location.href);
    console.log('Expected API path:', window.location.origin + '/api/transactions.php');

    let csrfToken = <?= json_encode($transactionCsrf) ?>;
    let rows = [];
    let page = 1;
    let permissions = { edit: true, delete: true };
    let loadTimer = null;

    const $ = id => document.getElementById(id);
    const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[c]);
    const money = v => new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 2 }).format(Number(v || 0));
    const badge = v => `<span class="transaction-badge ${esc(String(v || '').toLowerCase())}">${esc(v || '-')}</span>`;
    const ucfirst = str => str ? String(str).charAt(0).toUpperCase() + String(str).slice(1) : '';
    const formatDate = date => {
        if (!date) return '-';
        try {
            const d = new Date(date + 'T00:00:00');
            return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
        } catch { return date; }
    };

    async function request(action, data = {}, method = 'GET') {
        try {
            let response;
            if (method === 'GET') {
                // Build URL with query parameters
                const url = new URL(apiUrl, window.location.origin + '/school/');
                url.searchParams.set('action', action);
                Object.entries(data).forEach(([k, v]) => {
                    if (v !== '' && v !== null && v !== undefined) url.searchParams.set(k, String(v));
                });
                console.log('Request URL:', url.toString());
                response = await fetch(url.toString(), { 
                    headers: { Accept: 'application/json' }, 
                    credentials: 'same-origin' 
                });
            } else {
                console.log('POST Request to:', apiUrl);
                console.log('POST Data:', { action, csrf_token: csrfToken, ...data });
                response = await fetch(apiUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ action, csrf_token: csrfToken, ...data })
                });
            }

            // Check if response is OK
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const text = await response.text();
            console.log('Response text (first 200 chars):', text.substring(0, 200));
            
            let result;
            try {
                result = JSON.parse(text);
            } catch (e) {
                console.error('Invalid JSON response:', text);
                throw new Error('API returned invalid JSON response. The API file may not exist or has PHP errors.');
            }

            if (!result.success) {
                throw new Error(result.message || 'Request failed.');
            }

            if (result.data?.csrf_token) csrfToken = result.data.csrf_token;
            return result;
        } catch (error) {
            console.error('API Request Error:', error);
            throw error;
        }
    }

    function message(text, ok = false) {
        const b = $('transactionMessage');
        if (!b) return;
        b.className = 'alert transaction-message show ' + (ok ? 'alert-success' : 'alert-danger');
        b.textContent = text;
    }

    function updateStats(s = {}) {
        const els = {
            statIncome: $('statIncome'),
            statExpense: $('statExpense'),
            statBalance: $('statBalance'),
            statPending: $('statPending')
        };
        if (els.statIncome) els.statIncome.textContent = money(s.total_income || 0);
        if (els.statExpense) els.statExpense.textContent = money(s.total_expense || 0);
        if (els.statBalance) els.statBalance.textContent = money(s.net_balance || 0);
        if (els.statPending) els.statPending.textContent = Number(s.pending_count || 0);
    }

    function render(data) {
        rows = data;
        const body = $('transactionBody');
        if (!body) return;
        
        body.innerHTML = data.map((r, index) => `
            <tr>
                <td>${index + 1}</td>
                <td><strong>${esc(r.transaction_no)}</strong></td>
                <td>${badge(r.transaction_type)}</td>
                <td>${esc(ucfirst(r.category || '-'))}</td>
                <td><strong>${money(r.amount)}</strong></td>
                <td>${formatDate(r.transaction_date)}</td>
                <td>${esc(ucfirst(r.payment_method || '-'))}</td>
                <td>${esc(r.party_name || '-')}</td>
                <td>${badge(r.status)}</td>
                <td>
                    <div class="transaction-actions">
                        <button class="transaction-action js-view" data-id="${r.id}" title="View">
                            <i data-lucide="eye"></i>
                        </button>
                        ${permissions.edit ? `<button class="transaction-action js-edit" data-id="${r.id}" title="Edit"><i data-lucide="pencil"></i></button>` : ''}
                        ${permissions.delete ? `<button class="transaction-action danger js-delete" data-id="${r.id}" title="Delete"><i data-lucide="trash-2"></i></button>` : ''}
                        ${r.status === 'pending' ? `<button class="transaction-action success js-complete" data-id="${r.id}" title="Mark Complete"><i data-lucide="check"></i></button>` : ''}
                    </div>
                </td>
            </tr>
        `).join('') || '<tr><td colspan="10" class="transaction-empty">No transactions found.</td></tr>';

        document.querySelectorAll('.js-view').forEach(b => b.onclick = () => viewTransaction(Number(b.dataset.id)));
        document.querySelectorAll('.js-edit').forEach(b => b.onclick = () => openTransaction(Number(b.dataset.id)));
        document.querySelectorAll('.js-delete').forEach(b => b.onclick = () => deleteTransaction(Number(b.dataset.id)));
        document.querySelectorAll('.js-complete').forEach(b => b.onclick = () => updateStatus(Number(b.dataset.id), 'completed'));
        
        if (window.lucide) window.lucide.createIcons();
    }

    function renderSummary(data) {
        const body = $('summaryBody');
        if (!body) return;
        
        body.innerHTML = data.map(r => {
            const net = (r.total_income || 0) - (r.total_expense || 0);
            return `
                <tr>
                    <td><strong>${esc(ucfirst(r.category || 'Unknown'))}</strong></td>
                    <td>${Number(r.transaction_count || 0)}</td>
                    <td class="text-success">${money(r.total_income || 0)}</td>
                    <td class="text-danger">${money(r.total_expense || 0)}</td>
                    <td><strong>${money(net)}</strong></td>
                </tr>
            `;
        }).join('') || '<tr><td colspan="5" class="transaction-empty">No summary available.</td></tr>';
    }

    function renderPagination(p = {}) {
        const total = Number(p.total || 0);
        const current = Number(p.page || 1);
        const per = Number(p.per_page || 10);
        const last = Math.max(1, Number(p.last_page || 1));
        const start = total ? ((current - 1) * per) + 1 : 0;
        const end = Math.min(current * per, total);

        const recordInfo = $('recordInfo');
        const pageInfo = $('pageInfo');
        const pagination = $('pagination');
        
        if (recordInfo) recordInfo.textContent = `${total} transaction${total === 1 ? '' : 's'}`;
        if (pageInfo) pageInfo.textContent = `Showing ${start}-${end} of ${total}`;
        if (!pagination) return;

        let html = `<button class="transaction-page-button" data-page="${current - 1}" ${current <= 1 ? 'disabled' : ''}>‹</button>`;
        for (let x = Math.max(1, current - 2); x <= Math.min(last, current + 2); x++) {
            html += `<button class="transaction-page-button ${x === current ? 'active' : ''}" data-page="${x}">${x}</button>`;
        }
        html += `<button class="transaction-page-button" data-page="${current + 1}" ${current >= last ? 'disabled' : ''}>›</button>`;

        pagination.innerHTML = html;
        document.querySelectorAll('.transaction-page-button').forEach(b => {
            b.onclick = () => {
                if (!b.disabled) {
                    page = Number(b.dataset.page);
                    load();
                }
            };
        });
    }

    async function load() {
        try {
            const r = await request('list', {
                search: $('search').value.trim(),
                type: $('typeFilter').value,
                category: $('categoryFilter').value,
                status: $('statusFilter').value,
                date_from: $('dateFrom').value,
                date_to: $('dateTo').value,
                page: page,
                per_page: 10
            });

            permissions = r.data.permissions || permissions;

            // Populate category dropdown
            const categories = r.data.categories || [];
            const catFilter = $('categoryFilter');
            if (catFilter && catFilter.options.length <= 1) {
                catFilter.innerHTML = '<option value="all">All Categories</option>';
                categories.forEach(c => {
                    catFilter.innerHTML += `<option value="${esc(c)}">${esc(ucfirst(c))}</option>`;
                });
            }

            render(r.data.records || []);
            renderSummary(r.data.summary || []);
            renderPagination(r.data.pagination || {});
            updateStats(r.data.stats || {});

        } catch (e) {
            message(e.message || 'Error loading transactions');
            const body = $('transactionBody');
            if (body) {
                body.innerHTML = '<tr><td colspan="10" class="transaction-empty">Unable to load transactions.<br><br>Check console (F12) for errors.<br>API URL: ' + apiUrl + '</td></tr>';
            }
        }
    }

    function openTransaction(id = 0) {
        const r = rows.find(x => Number(x.id) === id);

        $('transactionId').value = r?.id || '';
        $('transactionNo').value = r?.transaction_no || '';
        $('transactionType').value = r?.transaction_type || 'income';
        $('category').value = r?.category || 'other';
        $('amount').value = r?.amount || '';
        $('transactionDate').value = r?.transaction_date || new Date().toISOString().split('T')[0];
        $('paymentMethod').value = r?.payment_method || 'cash';
        $('referenceNo').value = r?.reference_no || '';
        $('partyName').value = r?.party_name || '';
        $('transactionStatus').value = r?.status || 'pending';
        $('description').value = r?.description || '';

        $('transactionModalTitle').textContent = r ? 'Edit Transaction' : 'Add Transaction';
        const modal = new bootstrap.Modal($('transactionModal'));
        modal.show();
    }

    async function viewTransaction(id) {
        try {
            const r = await request('detail', { id });
            const x = r.data.record;

            $('viewSubtitle').textContent = `${x.transaction_no} • ${formatDate(x.transaction_date)}`;

            $('viewContent').innerHTML = [
                ['Type', ucfirst(x.transaction_type)],
                ['Category', ucfirst(x.category)],
                ['Amount', money(x.amount)],
                ['Payment Method', ucfirst(x.payment_method)],
                ['Reference No', x.reference_no || '-'],
                ['Party Name', x.party_name || '-'],
                ['Status', ucfirst(x.status)],
                ['Created By', x.created_by_name || '-']
            ].map(([l, v]) => `
                <div>
                    <small>${esc(l)}</small>
                    <strong>${esc(v)}</strong>
                </div>
            `).join('');

            $('viewDescription').innerHTML = `
                <strong>Description</strong>
                <p class="mt-2 mb-0">${esc(x.description || 'No description.')}</p>
            `;

            const modal = new bootstrap.Modal($('viewTransactionModal'));
            modal.show();
        } catch (e) {
            message(e.message);
        }
    }

    async function deleteTransaction(id) {
        if (!confirm('Delete this transaction?')) return;
        try {
            const r = await request('delete', { id }, 'POST');
            message(r.message, true);
            await load();
        } catch (e) {
            message(e.message);
        }
    }

    async function updateStatus(id, status) {
        if (!confirm(`Mark this transaction as ${status}?`)) return;
        try {
            const r = await request('update_status', { id, status }, 'POST');
            message(r.message, true);
            await load();
        } catch (e) {
            message(e.message);
        }
    }

    // ============================================
    // Event Listeners
    // ============================================

    $('transactionForm').onsubmit = async e => {
        e.preventDefault();
        try {
            const r = await request('save', {
                id: Number($('transactionId').value || 0),
                transaction_no: $('transactionNo').value,
                transaction_type: $('transactionType').value,
                category: $('category').value,
                amount: parseFloat($('amount').value) || 0,
                transaction_date: $('transactionDate').value,
                payment_method: $('paymentMethod').value,
                reference_no: $('referenceNo').value.trim(),
                party_name: $('partyName').value.trim(),
                status: $('transactionStatus').value,
                description: $('description').value.trim()
            }, 'POST');

            const modal = bootstrap.Modal.getInstance($('transactionModal'));
            if (modal) modal.hide();
            message(r.message, true);
            await load();
        } catch (err) {
            message(err.message);
        }
    };

    $('addTransactionBtn').onclick = () => openTransaction();
    $('refreshBtn').onclick = load;

    $('exportBtn').onclick = () => {
        const p = new URLSearchParams({
            action: 'export',
            search: $('search').value.trim(),
            type: $('typeFilter').value,
            category: $('categoryFilter').value,
            status: $('statusFilter').value,
            date_from: $('dateFrom').value,
            date_to: $('dateTo').value
        });
        window.location.href = apiUrl + '?' + p;
    };

    $('printBtn').onclick = () => {
        const p = new URLSearchParams({
            action: 'print',
            search: $('search').value.trim(),
            type: $('typeFilter').value,
            category: $('categoryFilter').value,
            status: $('statusFilter').value,
            date_from: $('dateFrom').value,
            date_to: $('dateTo').value
        });
        window.open(apiUrl + '?' + p, '_blank');
    };

    $('resetBtn').onclick = () => {
        $('search').value = '';
        ['typeFilter', 'categoryFilter', 'statusFilter'].forEach(id => {
            const el = $(id);
            if (el) el.value = 'all';
        });
        $('dateFrom').value = '';
        $('dateTo').value = '';
        page = 1;
        load();
    };

    ['typeFilter', 'categoryFilter', 'statusFilter', 'dateFrom', 'dateTo'].forEach(id => {
        const el = $(id);
        if (el) {
            el.onchange = () => {
                page = 1;
                load();
            };
        }
    });

    $('search').oninput = () => {
        clearTimeout(loadTimer);
        loadTimer = setTimeout(() => {
            page = 1;
            load();
        }, 300);
    };

    document.querySelectorAll('.transaction-tab').forEach(b => {
        b.onclick = () => {
            document.querySelectorAll('.transaction-tab').forEach(x => x.classList.remove('active'));
            document.querySelectorAll('.transaction-panel').forEach(x => x.classList.remove('active'));
            b.classList.add('active');
            document.querySelector(`[data-panel="${b.dataset.tab}"]`)?.classList.add('active');
        };
    });

    // Initial load
    load();
    if (window.lucide) window.lucide.createIcons();

})();
</script>

<?php require dirname(__DIR__) . '/includes/layout-end.php'; ?>
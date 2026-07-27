<?php

declare(strict_types=1);

$pageTitle = 'Fees & Payments';
$pageKey   = 'fees';

$feeRows = [
    [
        'receipt_no'    => 'BRPS/24-25/1024',
        'student_name'  => 'Aarav Sharma',
        'class_section' => '6-A',
        'fee_term'      => 'Q1 (Apr-Jun)',
        'amount'        => 18500,
        'paid'          => 18500,
        'balance'       => 0,
        'status'        => 'Paid',
    ],
    [
        'receipt_no'    => 'BRPS/24-25/1023',
        'student_name'  => 'Diya Patel',
        'class_section' => '8-B',
        'fee_term'      => 'Q1 (Apr-Jun)',
        'amount'        => 21000,
        'paid'          => 21000,
        'balance'       => 0,
        'status'        => 'Paid',
    ],
    [
        'receipt_no'    => 'BRPS/24-25/1022',
        'student_name'  => 'Reyansh Verma',
        'class_section' => '4-C',
        'fee_term'      => 'Q1 (Apr-Jun)',
        'amount'        => 16750,
        'paid'          => 10000,
        'balance'       => 6750,
        'status'        => 'Partially Paid',
    ],
    [
        'receipt_no'    => 'BRPS/24-25/1021',
        'student_name'  => 'Myra Singh',
        'class_section' => '7-A',
        'fee_term'      => 'Q1 (Apr-Jun)',
        'amount'        => 19250,
        'paid'          => 0,
        'balance'       => 19250,
        'status'        => 'Overdue',
    ],
    [
        'receipt_no'    => 'BRPS/24-25/1020',
        'student_name'  => 'Kabir Joshi',
        'class_section' => '3-B',
        'fee_term'      => 'Q1 (Apr-Jun)',
        'amount'        => 14000,
        'paid'          => 0,
        'balance'       => 14000,
        'status'        => 'Overdue',
    ],
];

$recentTransactions = [
    [
        'receipt_no'   => 'BRPS/24-25/1024',
        'student_name' => 'Aarav Sharma',
        'class'        => 'Class 6-A',
        'amount'       => 18500,
        'time'         => 'Today, 10:24 AM',
        'method'       => 'Online Payment',
        'icon'         => 'credit-card',
        'icon_class'   => 'transaction-blue',
    ],
    [
        'receipt_no'   => 'BRPS/24-25/1023',
        'student_name' => 'Diya Patel',
        'class'        => 'Class 8-B',
        'amount'       => 21000,
        'time'         => 'Today, 09:15 AM',
        'method'       => 'Bank Transfer',
        'icon'         => 'landmark',
        'icon_class'   => 'transaction-green',
    ],
    [
        'receipt_no'   => 'BRPS/24-25/1022',
        'student_name' => 'Reyansh Verma',
        'class'        => 'Class 4-C',
        'amount'       => 16750,
        'time'         => 'Yesterday, 04:45 PM',
        'method'       => 'UPI',
        'icon'         => 'smartphone',
        'icon_class'   => 'transaction-orange',
    ],
    [
        'receipt_no'   => 'BRPS/24-25/1021',
        'student_name' => 'Myra Singh',
        'class'        => 'Class 7-A',
        'amount'       => 19250,
        'time'         => 'Yesterday, 02:30 PM',
        'method'       => 'Cash',
        'icon'         => 'banknote',
        'icon_class'   => 'transaction-purple',
    ],
    [
        'receipt_no'   => 'BRPS/24-25/1020',
        'student_name' => 'Kabir Joshi',
        'class'        => 'Class 3-B',
        'amount'       => 14000,
        'time'         => '20 May 2024, 11:20 AM',
        'method'       => 'Cheque',
        'icon'         => 'receipt',
        'icon_class'   => 'transaction-cyan',
    ],
];

require __DIR__ . '/includes/layout-start.php';

$canView = !function_exists('has_permission')
    || has_permission($pageKey, 'view');

$canCreate = !function_exists('has_permission')
    || has_permission($pageKey, 'create');

$canPrint = !function_exists('has_permission')
    || has_permission($pageKey, 'print');

$canExport = !function_exists('has_permission')
    || has_permission($pageKey, 'export');

$canEdit = !function_exists('has_permission')
    || has_permission($pageKey, 'edit');

if (!$canView) {
    http_response_code(403);
    ?>
<div class="alert alert-danger">
    You do not have permission to view the Fees & Payments module.
</div>
<?php
    require __DIR__ . '/includes/layout-end.php';
    exit;
}

function feeStatusClass(string $status): string
{
    return match ($status) {
        'Paid'           => 'fee-badge-success',
        'Partially Paid' => 'fee-badge-warning',
        'Overdue'        => 'fee-badge-danger',
        default          => 'fee-badge-info',
    };
}

function formatInr(int|float $amount): string
{
    return '₹' . number_format((float)$amount, 0);
}
?>

<style>
.fees-chart-grid {
    display: grid;
    grid-template-columns: 1.35fr .85fr .9fr;
    gap: 16px;
    align-items: stretch;
}

.fee-card {
    overflow: hidden;
    background: var(--card-bg, #ffffff);
    border: 1px solid var(--border-soft, #e7ebf3);
    border-radius: 13px;
    box-shadow: 0 5px 18px rgba(15, 23, 42, .04);
}

.fee-card-header {
    min-height: 54px;
    padding: 13px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    border-bottom: 1px solid var(--border-soft, #e7ebf3);
}

.fee-card-header h2 {
    margin: 0;
    font-size: 14px;
    font-weight: 800;
}

.fee-card-header a {
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
}

.fee-chart-box {
    position: relative;
    height: 285px;
    padding: 16px;
}

.payment-mode-chart {
    position: relative;
    height: 285px;
    padding: 18px;
}

.payment-chart-total {
    position: absolute;
    top: 50%;
    left: 50%;
    z-index: 2;
    width: 115px;
    text-align: center;
    pointer-events: none;
    transform: translate(-50%, -58%);
}

.payment-chart-total strong,
.payment-chart-total small {
    display: block;
}

.payment-chart-total strong {
    font-size: 15px;
    color: var(--text-main, #101a3b);
}

.payment-chart-total small {
    margin-top: 3px;
    color: var(--text-muted, #64748b);
    font-size: 10px;
}

.transaction-list {
    padding: 2px 15px 5px;
}

.transaction-item {
    padding: 11px 0;
    display: grid;
    grid-template-columns: 36px minmax(0, 1fr) auto;
    align-items: center;
    gap: 10px;
    border-bottom: 1px solid var(--border-soft, #e7ebf3);
}

.transaction-item:last-child {
    border-bottom: 0;
}

.transaction-icon {
    width: 36px;
    height: 36px;
    display: grid;
    place-items: center;
    border-radius: 10px;
}

.transaction-icon svg {
    width: 17px;
    height: 17px;
}

.transaction-blue {
    color: #3169d8;
    background: #eaf1ff;
}

.transaction-green {
    color: #1c9557;
    background: #e9f8ef;
}

.transaction-orange {
    color: #dc7b08;
    background: #fff2df;
}

.transaction-purple {
    color: #6c48dd;
    background: #f0ebff;
}

.transaction-cyan {
    color: #138da2;
    background: #e7f8fb;
}

.transaction-content {
    min-width: 0;
}

.transaction-content strong,
.transaction-content small {
    display: block;
}

.transaction-content strong {
    overflow: hidden;
    font-size: 11px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.transaction-content small {
    margin-top: 2px;
    color: var(--text-muted, #64748b);
    font-size: 9px;
}

.transaction-amount {
    text-align: right;
}

.transaction-amount strong,
.transaction-amount small {
    display: block;
}

.transaction-amount strong {
    color: #149351;
    font-size: 12px;
}

.transaction-amount small {
    margin-top: 2px;
    color: var(--text-muted, #64748b);
    font-size: 8px;
    white-space: nowrap;
}

.fee-table-toolbar {
    display: flex;
    align-items: center;
    gap: 8px;
}

.fee-table-toolbar .form-select {
    width: auto;
    min-width: 125px;
    font-size: 12px;
}

.fee-search {
    min-width: 230px;
}

.fee-table-wrap {
    overflow-x: auto;
}

.fee-table {
    width: 100%;
    min-width: 1030px;
    border-collapse: collapse;
}

.fee-table th,
.fee-table td {
    padding: 11px 12px;
    border-bottom: 1px solid var(--border-soft, #e7ebf3);
    font-size: 12px;
    text-align: left;
    vertical-align: middle;
}

.fee-table th {
    color: #475569;
    background: #f8fafc;
    font-weight: 800;
}

.fee-receipt-link {
    color: var(--brand-1, #6547e8);
    font-weight: 800;
    text-decoration: none;
}

.fee-student {
    display: flex;
    align-items: center;
    gap: 9px;
    font-weight: 700;
}

.fee-avatar {
    width: 30px;
    height: 30px;
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    border-radius: 50%;
    color: #ffffff;
    background: linear-gradient(135deg,
            var(--brand-1, #6547e8),
            var(--brand-2, #315ed8));
}

.fee-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 8px;
    border-radius: 30px;
    font-size: 10px;
    font-weight: 800;
    white-space: nowrap;
}

.fee-badge-success {
    color: #168448;
    background: #e8f8ef;
}

.fee-badge-warning {
    color: #d97706;
    background: #fff2dc;
}

.fee-badge-danger {
    color: #dc263f;
    background: #feeaee;
}

.fee-badge-info {
    color: #3164ce;
    background: #e8f1ff;
}

.fee-actions {
    display: flex;
    gap: 6px;
}

.fee-actions button {
    width: 30px;
    height: 30px;
    display: grid;
    place-items: center;
    border: 1px solid var(--border-soft, #e7ebf3);
    border-radius: 8px;
    color: #475569;
    background: #ffffff;
}

.fee-actions button:hover {
    color: var(--brand-1, #6547e8);
    background: #f8fafc;
}

.fee-actions svg {
    width: 14px;
    height: 14px;
}

.fee-table-footer {
    padding: 13px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    color: var(--text-muted, #64748b);
    font-size: 12px;
}

.fee-pagination {
    display: flex;
    gap: 6px;
}

.fee-pagination button {
    min-width: 30px;
    height: 30px;
    padding: 0 8px;
    border: 1px solid var(--border-soft, #e7ebf3);
    border-radius: 7px;
    color: #475569;
    background: #ffffff;
}

.fee-pagination button.active {
    color: #ffffff;
    border-color: transparent;
    background: linear-gradient(135deg,
            var(--brand-1, #6547e8),
            var(--brand-2, #315ed8));
}

.quick-insight-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
    margin-top: 16px;
}

.quick-insight {
    padding: 15px;
    display: flex;
    align-items: center;
    gap: 11px;
    background: var(--card-bg, #ffffff);
    border: 1px solid var(--border-soft, #e7ebf3);
    border-radius: 12px;
}

.quick-insight-icon {
    width: 40px;
    height: 40px;
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    border-radius: 50%;
    color: var(--brand-1, #6547e8);
    background: #eeeafd;
}

.quick-insight-icon svg {
    width: 19px;
    height: 19px;
}

.quick-insight strong,
.quick-insight small {
    display: block;
}

.quick-insight strong {
    margin-top: 2px;
    font-size: 15px;
}

.quick-insight small {
    color: var(--text-muted, #64748b);
    font-size: 10px;
}

@media (max-width: 1299.98px) {
    .fees-chart-grid {
        grid-template-columns: 1.2fr .8fr;
    }

    .fees-chart-grid>section:last-child {
        grid-column: 1 / -1;
    }

    .transaction-list {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0 18px;
    }
}

@media (max-width: 991.98px) {
    .fees-chart-grid {
        grid-template-columns: 1fr;
    }

    .fees-chart-grid>section:last-child {
        grid-column: auto;
    }

    .quick-insight-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .fee-table-toolbar {
        display: none;
    }
}

@media (max-width: 767.98px) {
    .transaction-list {
        grid-template-columns: 1fr;
    }

    .quick-insight-grid {
        grid-template-columns: 1fr;
    }

    .fee-table-footer {
        align-items: flex-start;
        flex-direction: column;
    }

    .fee-chart-box,
    .payment-mode-chart {
        height: 250px;
    }
}
</style>

<div class="page-heading">
    <div>
        <h1 class="page-title">
            Fees & Payments
        </h1>

        <p class="page-subtitle">
            Manage fee collections, payments and outstanding dues
        </p>
    </div>

    <div class="page-actions">
        <?php if ($canCreate): ?>
        <button type="button" class="btn-ui btn-primary-ui" data-demo-action="Collect Fees">
            <i data-lucide="circle-plus"></i>
            Collect Fees
        </button>
        <?php endif; ?>

        <?php if ($canPrint): ?>
        <button type="button" class="btn-ui">
            <i data-lucide="printer"></i>
            Print Receipt
        </button>
        <?php endif; ?>

        <button type="button" class="btn-ui">
            <i data-lucide="send"></i>
            Send Reminder
        </button>

        <?php if ($canExport): ?>
        <button type="button" class="btn-ui">
            <i data-lucide="download"></i>
            Export
        </button>
        <?php endif; ?>
    </div>
</div>

<section class="metric-grid">
    <article class="metric-card metric-purple">
        <div class="metric-icon">
            <i data-lucide="wallet"></i>
        </div>

        <div>
            <small>Total Collection</small>
            <div class="metric-value">
                ₹1,24,68,500
            </div>
            <div class="metric-trend">
                ↑ 21.4% from last month
            </div>
        </div>
    </article>

    <article class="metric-card metric-orange">
        <div class="metric-icon">
            <i data-lucide="indian-rupee"></i>
        </div>

        <div>
            <small>Pending Fee Amount</small>
            <div class="metric-value">
                ₹28,75,400
            </div>
            <div class="metric-trend">
                ↑ 8.7% from last month
            </div>
        </div>
    </article>

    <article class="metric-card metric-blue">
        <div class="metric-icon">
            <i data-lucide="users"></i>
        </div>

        <div>
            <small>Paid Students</small>
            <div class="metric-value">
                2,156
            </div>
            <div class="metric-trend">
                ↑ 6.2% from last month
            </div>
        </div>
    </article>

    <article class="metric-card metric-pink">
        <div class="metric-icon">
            <i data-lucide="circle-alert"></i>
        </div>

        <div>
            <small>Overdue Accounts</small>
            <div class="metric-value">
                312
            </div>
            <div class="metric-trend">
                ↑ 5.4% from last month
            </div>
        </div>
    </article>
</section>

<div class="fees-chart-grid">
    <section class="fee-card">
        <div class="fee-card-header">
            <h2>
                Monthly Fee Collection
            </h2>

            <select class="form-select form-select-sm w-auto">
                <option>This Year</option>
                <option>Last Year</option>
            </select>
        </div>

        <div class="fee-chart-box">
            <canvas id="feeTrendChart"></canvas>
        </div>
    </section>

    <section class="fee-card">
        <div class="fee-card-header">
            <h2>
                Payment Mode Breakdown
            </h2>

            <a href="reports.php">
                View Report
            </a>
        </div>

        <div class="payment-mode-chart">
            <div class="payment-chart-total">
                <strong>₹1,24,68,500</strong>
                <small>Total Collection</small>
            </div>

            <canvas id="paymentModeChart"></canvas>
        </div>
    </section>

    <section class="fee-card">
        <div class="fee-card-header">
            <h2>
                Recent Transactions
            </h2>

            <a href="#">
                View All
            </a>
        </div>

        <div class="transaction-list">
            <?php foreach ($recentTransactions as $transaction): ?>
            <div class="transaction-item">
                <div class="transaction-icon <?= e($transaction['icon_class']) ?>">
                    <i data-lucide="<?= e($transaction['icon']) ?>"></i>
                </div>

                <div class="transaction-content">
                    <strong>
                        Receipt #<?= e($transaction['receipt_no']) ?>
                    </strong>

                    <small>
                        <?= e($transaction['student_name']) ?>
                        ·
                        <?= e($transaction['class']) ?>
                    </small>

                    <small>
                        <?= e($transaction['method']) ?>
                    </small>
                </div>

                <div class="transaction-amount">
                    <strong>
                        <?= e(formatInr($transaction['amount'])) ?>
                    </strong>

                    <small>
                        <?= e($transaction['time']) ?>
                    </small>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<section class="fee-card mt-3">
    <div class="fee-card-header">
        <h2>
            Fee Dues / Receipts
        </h2>

        <div class="fee-table-toolbar">
            <select class="form-select form-select-sm">
                <option>All Classes</option>
                <option>Class 3</option>
                <option>Class 4</option>
                <option>Class 6</option>
                <option>Class 7</option>
                <option>Class 8</option>
            </select>

            <select class="form-select form-select-sm">
                <option>All Fee Terms</option>
                <option>Q1 (Apr-Jun)</option>
                <option>Q2 (Jul-Sep)</option>
                <option>Q3 (Oct-Dec)</option>
                <option>Q4 (Jan-Mar)</option>
            </select>

            <select class="form-select form-select-sm">
                <option>All Status</option>
                <option>Paid</option>
                <option>Partially Paid</option>
                <option>Overdue</option>
            </select>

            <input type="search" class="form-control form-control-sm fee-search"
                placeholder="Search student or receipt no...">
        </div>
    </div>

    <div class="fee-table-wrap">
        <table class="fee-table">
            <thead>
                <tr>
                    <th>Receipt No</th>
                    <th>Student</th>
                    <th>Class</th>
                    <th>Fee Term</th>
                    <th>Amount</th>
                    <th>Paid</th>
                    <th>Balance</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($feeRows as $fee): ?>
                <tr>
                    <td>
                        <a href="#" class="fee-receipt-link">
                            <?= e($fee['receipt_no']) ?>
                        </a>
                    </td>

                    <td>
                        <div class="fee-student">
                            <span class="fee-avatar">
                                <?= e(
                                        strtoupper(
                                            substr(
                                                $fee['student_name'],
                                                0,
                                                1
                                            )
                                        )
                                    ) ?>
                            </span>

                            <?= e($fee['student_name']) ?>
                        </div>
                    </td>

                    <td>
                        <?= e($fee['class_section']) ?>
                    </td>

                    <td>
                        <?= e($fee['fee_term']) ?>
                    </td>

                    <td>
                        <?= e(formatInr($fee['amount'])) ?>
                    </td>

                    <td>
                        <?= e(formatInr($fee['paid'])) ?>
                    </td>

                    <td>
                        <?= e(formatInr($fee['balance'])) ?>
                    </td>

                    <td>
                        <span class="fee-badge <?= e(
                                    feeStatusClass(
                                        $fee['status']
                                    )
                                ) ?>">
                            <?= e($fee['status']) ?>
                        </span>
                    </td>

                    <td>
                        <div class="fee-actions">
                            <button type="button" title="View receipt" aria-label="View receipt">
                                <i data-lucide="eye"></i>
                            </button>

                            <?php if ($canPrint): ?>
                            <button type="button" title="Print receipt" aria-label="Print receipt">
                                <i data-lucide="printer"></i>
                            </button>
                            <?php endif; ?>

                            <?php if ($canEdit): ?>
                            <button type="button" title="More actions" aria-label="More actions">
                                <i data-lucide="ellipsis-vertical"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="fee-table-footer">
        <span>
            Showing 1 to 5 of 25 entries
        </span>

        <div class="fee-pagination">
            <button type="button">
                <i data-lucide="chevron-left"></i>
            </button>

            <button type="button" class="active">
                1
            </button>

            <button type="button">
                2
            </button>

            <button type="button">
                3
            </button>

            <button type="button">
                4
            </button>

            <button type="button">
                5
            </button>

            <button type="button">
                <i data-lucide="chevron-right"></i>
            </button>
        </div>
    </div>
</section>

<section class="quick-insight-grid">
    <article class="quick-insight">
        <div class="quick-insight-icon">
            <i data-lucide="wallet-cards"></i>
        </div>

        <div>
            <small>Average Collection / Month</small>
            <strong>₹15,58,563</strong>
        </div>
    </article>

    <article class="quick-insight">
        <div class="quick-insight-icon">
            <i data-lucide="circle-percent"></i>
        </div>

        <div>
            <small>Collection Efficiency</small>
            <strong>92.3%</strong>
        </div>
    </article>

    <article class="quick-insight">
        <div class="quick-insight-icon">
            <i data-lucide="users"></i>
        </div>

        <div>
            <small>Active Students</small>
            <strong>2,458</strong>
        </div>
    </article>

    <article class="quick-insight">
        <div class="quick-insight-icon">
            <i data-lucide="file-stack"></i>
        </div>

        <div>
            <small>Active Fee Structures</small>
            <strong>8</strong>
        </div>
    </article>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.Chart === 'undefined') {
        return;
    }

    const feeTrendElement =
        document.getElementById('feeTrendChart');

    if (feeTrendElement) {
        new Chart(feeTrendElement, {
            type: 'bar',

            data: {
                labels: [
                    'Apr',
                    'May',
                    'Jun',
                    'Jul',
                    'Aug',
                    'Sep',
                    'Oct',
                    'Nov',
                    'Dec',
                    'Jan',
                    'Feb',
                    'Mar'
                ],

                datasets: [{
                    label: 'Collection in Lakhs',

                    data: [
                        9.4,
                        11.2,
                        12.8,
                        10.6,
                        14.9,
                        13.7,
                        16.3,
                        15.1,
                        18.7,
                        20.2,
                        21.4,
                        24.3
                    ],

                    backgroundColor: '#6556e8',
                    borderRadius: 6,
                    borderSkipped: false,
                    maxBarThickness: 30
                }]
            },

            options: {
                responsive: true,
                maintainAspectRatio: false,

                plugins: {
                    legend: {
                        display: false
                    },

                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return '₹' +
                                    context.raw +
                                    ' lakh';
                            }
                        }
                    }
                },

                scales: {
                    x: {
                        grid: {
                            display: false
                        },

                        ticks: {
                            font: {
                                size: 10
                            }
                        }
                    },

                    y: {
                        beginAtZero: true,

                        grid: {
                            color: '#edf0f6'
                        },

                        ticks: {
                            font: {
                                size: 10
                            },

                            callback: function(value) {
                                return '₹' + value + 'L';
                            }
                        }
                    }
                }
            }
        });
    }

    const paymentModeElement =
        document.getElementById('paymentModeChart');

    if (paymentModeElement) {
        new Chart(paymentModeElement, {
            type: 'doughnut',

            data: {
                labels: [
                    'Online Payment',
                    'UPI',
                    'Bank Transfer',
                    'Cash',
                    'Cheque / DD'
                ],

                datasets: [{
                    label: 'Payments',

                    data: [
                        49.8,
                        22.8,
                        13.9,
                        7.6,
                        5.9
                    ],

                    backgroundColor: [
                        '#6757e9',
                        '#3f8df0',
                        '#2eb36b',
                        '#ff9f1c',
                        '#ef476f'
                    ],

                    borderWidth: 0,
                    hoverOffset: 6
                }]
            },

            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',

                plugins: {
                    legend: {
                        position: 'bottom',

                        labels: {
                            usePointStyle: true,
                            boxWidth: 8,
                            padding: 12,
                            font: {
                                size: 10
                            }
                        }
                    },

                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label +
                                    ': ' +
                                    context.raw +
                                    '%';
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
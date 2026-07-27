<?php

declare(strict_types=1);

$pageTitle = 'Reports & Analytics';
$pageKey   = 'reports';

$reportCategories = [
    [
        'title'       => 'Student Reports',
        'icon'        => 'users',
        'description' => 'Demographics, performance, enrollment and student summaries',
        'count'       => 18,
        'color_class' => 'report-purple',
    ],
    [
        'title'       => 'Attendance Reports',
        'icon'        => 'calendar-check',
        'description' => 'Daily, monthly and class-wise attendance insights',
        'count'       => 14,
        'color_class' => 'report-blue',
    ],
    [
        'title'       => 'Exam Reports',
        'icon'        => 'clipboard-check',
        'description' => 'Results, grade analysis, rankings and performance trends',
        'count'       => 22,
        'color_class' => 'report-green',
    ],
    [
        'title'       => 'Fee Reports',
        'icon'        => 'indian-rupee',
        'description' => 'Collections, dues, refunds and financial summaries',
        'count'       => 16,
        'color_class' => 'report-orange',
    ],
    [
        'title'       => 'Payroll Reports',
        'icon'        => 'badge-indian-rupee',
        'description' => 'Salaries, deductions, advances and payroll summaries',
        'count'       => 12,
        'color_class' => 'report-pink',
    ],
    [
        'title'       => 'Transport Reports',
        'icon'        => 'bus-front',
        'description' => 'Routes, vehicles, attendance and transport fee reports',
        'count'       => 10,
        'color_class' => 'report-cyan',
    ],
];

$recentReports = [
    [
        'report_name'  => 'Monthly Fee Collection Summary',
        'module'       => 'Fee Reports',
        'date_range'   => '01 Apr 2024 - 30 Apr 2024',
        'generated_by' => 'Aarav Sharma',
        'generated_at' => '12 May 2024, 10:30 AM',
        'format'       => 'PDF',
        'status'       => 'Completed',
        'icon'         => 'file-chart-column',
        'icon_class'   => 'recent-purple',
    ],
    [
        'report_name'  => 'Student Attendance Overview',
        'module'       => 'Attendance Reports',
        'date_range'   => '01 Apr 2024 - 30 Apr 2024',
        'generated_by' => 'Diya Patel',
        'generated_at' => '11 May 2024, 04:15 PM',
        'format'       => 'Excel',
        'status'       => 'Completed',
        'icon'         => 'calendar-check',
        'icon_class'   => 'recent-green',
    ],
    [
        'report_name'  => 'Class 10 - Term 1 Result Analysis',
        'module'       => 'Exam Reports',
        'date_range'   => 'Term 1 - 2024',
        'generated_by' => 'Reyansh Verma',
        'generated_at' => '11 May 2024, 11:20 AM',
        'format'       => 'PDF',
        'status'       => 'Completed',
        'icon'         => 'clipboard-check',
        'icon_class'   => 'recent-orange',
    ],
    [
        'report_name'  => 'Staff Salary Register - April 2024',
        'module'       => 'Payroll Reports',
        'date_range'   => 'April 2024',
        'generated_by' => 'Myra Singh',
        'generated_at' => '10 May 2024, 09:45 AM',
        'format'       => 'Excel',
        'status'       => 'Completed',
        'icon'         => 'badge-indian-rupee',
        'icon_class'   => 'recent-blue',
    ],
    [
        'report_name'  => 'Transport Route Utilization Report',
        'module'       => 'Transport Reports',
        'date_range'   => '01 Apr 2024 - 30 Apr 2024',
        'generated_by' => 'Kabir Joshi',
        'generated_at' => '09 May 2024, 02:30 PM',
        'format'       => 'PDF',
        'status'       => 'Processing',
        'icon'         => 'bus-front',
        'icon_class'   => 'recent-cyan',
    ],
];

require __DIR__ . '/includes/layout-start.php';

$canView = !function_exists('has_permission')
    || has_permission($pageKey, 'view');

$canCreate = !function_exists('has_permission')
    || has_permission($pageKey, 'create');

$canExport = !function_exists('has_permission')
    || has_permission($pageKey, 'export');

$canPrint = !function_exists('has_permission')
    || has_permission($pageKey, 'print');

if (!$canView) {
    http_response_code(403);
    ?>
<div class="alert alert-danger">
    You do not have permission to view the Reports module.
</div>
<?php
    require __DIR__ . '/includes/layout-end.php';
    exit;
}

$reportStatusClass = static function (string $status): string {
    return match ($status) {
        'Completed'  => 'report-status-success',
        'Processing' => 'report-status-warning',
        'Failed'     => 'report-status-danger',
        default      => 'report-status-info',
    };
};

$formatClass = static function (string $format): string {
    return match ($format) {
        'PDF'   => 'format-pdf',
        'Excel' => 'format-excel',
        'CSV'   => 'format-csv',
        default => 'format-default',
    };
};
?>

<style>
.reports-main-grid {
    display: grid;
    grid-template-columns: 1.3fr .85fr .85fr;
    gap: 16px;
    align-items: stretch;
}

.report-card {
    overflow: hidden;
    background: var(--card-bg, #ffffff);
    border: 1px solid var(--border-soft, #e7ebf3);
    border-radius: 13px;
    box-shadow: 0 5px 18px rgba(15, 23, 42, .04);
}

.report-card-header {
    min-height: 54px;
    padding: 13px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    border-bottom: 1px solid var(--border-soft, #e7ebf3);
}

.report-card-header h2 {
    margin: 0;
    font-size: 14px;
    font-weight: 800;
}

.report-card-header p {
    margin: 3px 0 0;
    color: var(--text-muted, #64748b);
    font-size: 10px;
}

.report-card-header a {
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
}

.report-category-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    padding: 15px;
}

.report-category {
    min-height: 150px;
    padding: 15px;
    display: flex;
    flex-direction: column;
    border: 1px solid var(--border-soft, #e7ebf3);
    border-radius: 12px;
    background: #ffffff;
    transition:
        transform .2s ease,
        box-shadow .2s ease,
        border-color .2s ease;
}

.report-category:hover {
    transform: translateY(-2px);
    border-color: #d8d5fa;
    box-shadow: 0 10px 24px rgba(15, 23, 42, .08);
}

.report-category-icon {
    width: 43px;
    height: 43px;
    margin-bottom: 13px;
    display: grid;
    place-items: center;
    border-radius: 11px;
}

.report-category-icon svg {
    width: 21px;
    height: 21px;
}

.report-purple {
    color: #6747dd;
    background: #eeeafd;
}

.report-blue {
    color: #3168d8;
    background: #eaf1ff;
}

.report-green {
    color: #189354;
    background: #e9f8ef;
}

.report-orange {
    color: #df7d0b;
    background: #fff2df;
}

.report-pink {
    color: #e93e68;
    background: #feeaf0;
}

.report-cyan {
    color: #178da2;
    background: #e7f8fb;
}

.report-category strong {
    font-size: 12px;
}

.report-category>small {
    margin-top: 5px;
    color: var(--text-muted, #64748b);
    font-size: 10px;
    line-height: 1.45;
}

.report-category-footer {
    margin-top: auto;
    padding-top: 13px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    color: var(--brand-1, #6547e8);
    font-size: 11px;
    font-weight: 800;
}

.report-category-footer svg {
    width: 15px;
    height: 15px;
}

.report-chart-summary {
    padding: 15px 16px 0;
}

.report-chart-summary strong,
.report-chart-summary small {
    display: block;
}

.report-chart-summary strong {
    font-size: 21px;
}

.report-chart-summary small {
    margin-top: 3px;
    color: #169454;
    font-size: 10px;
    font-weight: 700;
}

.report-chart-box {
    height: 265px;
    padding: 8px 14px 16px;
}

.report-filter {
    width: auto;
    min-width: 100px;
    font-size: 11px;
}

.reports-table-toolbar {
    display: flex;
    align-items: center;
    gap: 8px;
}

.reports-table-toolbar .form-select {
    width: auto;
    min-width: 130px;
    font-size: 11px;
}

.reports-search {
    width: 215px;
}

.reports-table-wrap {
    overflow-x: auto;
}

.reports-table {
    width: 100%;
    min-width: 1080px;
    border-collapse: collapse;
}

.reports-table th,
.reports-table td {
    padding: 11px 12px;
    border-bottom: 1px solid var(--border-soft, #e7ebf3);
    font-size: 12px;
    text-align: left;
    vertical-align: middle;
}

.reports-table th {
    color: #475569;
    background: #f8fafc;
    font-weight: 800;
}

.report-name-cell {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 250px;
}

.report-name-icon {
    width: 36px;
    height: 36px;
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    border-radius: 10px;
}

.report-name-icon svg {
    width: 17px;
    height: 17px;
}

.recent-purple {
    color: #6747dd;
    background: #eeeafd;
}

.recent-green {
    color: #189354;
    background: #e9f8ef;
}

.recent-orange {
    color: #df7d0b;
    background: #fff2df;
}

.recent-blue {
    color: #3168d8;
    background: #eaf1ff;
}

.recent-cyan {
    color: #178da2;
    background: #e7f8fb;
}

.report-name-copy strong,
.report-name-copy small {
    display: block;
}

.report-name-copy strong {
    font-size: 12px;
}

.report-name-copy small {
    margin-top: 3px;
    color: var(--text-muted, #64748b);
    font-size: 9px;
}

.generated-user {
    display: flex;
    align-items: center;
    gap: 8px;
}

.generated-avatar {
    width: 29px;
    height: 29px;
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    border-radius: 50%;
    color: #ffffff;
    background: linear-gradient(135deg,
            var(--brand-1, #6547e8),
            var(--brand-2, #315ed8));
}

.generated-user strong,
.generated-user small {
    display: block;
}

.generated-user strong {
    font-size: 11px;
}

.generated-user small {
    margin-top: 2px;
    color: var(--text-muted, #64748b);
    font-size: 9px;
}

.report-format {
    display: inline-flex;
    align-items: center;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 10px;
    font-weight: 800;
}

.format-pdf {
    color: #d83951;
    background: #feecef;
}

.format-excel {
    color: #17864d;
    background: #e7f8ee;
}

.format-csv {
    color: #2f66d3;
    background: #e9f1ff;
}

.format-default {
    color: #5f6b7c;
    background: #eef1f5;
}

.report-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 8px;
    border-radius: 30px;
    font-size: 10px;
    font-weight: 800;
}

.report-status::before {
    width: 6px;
    height: 6px;
    content: "";
    border-radius: 50%;
    background: currentColor;
}

.report-status-success {
    color: #168448;
    background: #e8f8ef;
}

.report-status-warning {
    color: #d97706;
    background: #fff2dc;
}

.report-status-danger {
    color: #dc263f;
    background: #feeaee;
}

.report-status-info {
    color: #3164ce;
    background: #e8f1ff;
}

.report-actions {
    display: flex;
    gap: 6px;
}

.report-actions button {
    width: 30px;
    height: 30px;
    display: grid;
    place-items: center;
    border: 1px solid var(--border-soft, #e7ebf3);
    border-radius: 8px;
    color: #475569;
    background: #ffffff;
}

.report-actions button:hover {
    color: var(--brand-1, #6547e8);
    background: #f8fafc;
}

.report-actions svg {
    width: 14px;
    height: 14px;
}

.reports-table-footer {
    padding: 13px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    color: var(--text-muted, #64748b);
    font-size: 12px;
}

.reports-pagination {
    display: flex;
    gap: 6px;
}

.reports-pagination button {
    min-width: 30px;
    height: 30px;
    padding: 0 8px;
    border: 1px solid var(--border-soft, #e7ebf3);
    border-radius: 7px;
    color: #475569;
    background: #ffffff;
}

.reports-pagination button.active {
    color: #ffffff;
    border-color: transparent;
    background: linear-gradient(135deg,
            var(--brand-1, #6547e8),
            var(--brand-2, #315ed8));
}

.export-excel-button {
    color: #ffffff;
    border-color: transparent;
    background: #179454;
}

.export-excel-button:hover {
    color: #ffffff;
    background: #117d45;
}

@media (max-width: 1399.98px) {
    .reports-main-grid {
        grid-template-columns: 1fr 1fr;
    }

    .reports-main-grid>section:first-child {
        grid-column: 1 / -1;
    }
}

@media (max-width: 991.98px) {
    .reports-main-grid {
        grid-template-columns: 1fr;
    }

    .reports-main-grid>section:first-child {
        grid-column: auto;
    }

    .report-category-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .reports-table-toolbar {
        display: none;
    }
}

@media (max-width: 767.98px) {
    .report-category-grid {
        grid-template-columns: 1fr;
    }

    .reports-table-footer {
        align-items: flex-start;
        flex-direction: column;
    }

    .report-chart-box {
        height: 240px;
    }
}
</style>

<div class="page-heading">
    <div>
        <h1 class="page-title">
            Reports & Analytics
        </h1>

        <p class="page-subtitle">
            Generate, analyze and export institutional data with ease.
        </p>
    </div>

    <div class="page-actions">
        <?php if ($canCreate): ?>
        <button type="button" class="btn-ui btn-primary-ui" data-demo-action="Generate Report">
            <i data-lucide="plus"></i>
            Generate Report
        </button>

        <button type="button" class="btn-ui">
            <i data-lucide="clock-3"></i>
            Schedule Report
        </button>
        <?php endif; ?>

        <?php if ($canExport): ?>
        <button type="button" class="btn-ui">
            <i data-lucide="file-down"></i>
            Export PDF
        </button>

        <button type="button" class="btn-ui export-excel-button">
            <i data-lucide="sheet"></i>
            Export Excel
        </button>
        <?php endif; ?>
    </div>
</div>

<section class="metric-grid">
    <article class="metric-card metric-purple">
        <div class="metric-icon">
            <i data-lucide="files"></i>
        </div>

        <div>
            <small>Generated Reports</small>
            <div class="metric-value">248</div>
            <div class="metric-trend">
                ↑ 18.4% from last month
            </div>
        </div>
    </article>

    <article class="metric-card metric-blue">
        <div class="metric-icon">
            <i data-lucide="clock-3"></i>
        </div>

        <div>
            <small>Scheduled Reports</small>
            <div class="metric-value">26</div>
            <div class="metric-trend">
                ↑ 8.2% from last month
            </div>
        </div>
    </article>

    <article class="metric-card metric-green">
        <div class="metric-icon">
            <i data-lucide="cloud-download"></i>
        </div>

        <div>
            <small>Exports This Month</small>
            <div class="metric-value">142</div>
            <div class="metric-trend">
                ↑ 21.6% from last month
            </div>
        </div>
    </article>

    <article class="metric-card metric-orange">
        <div class="metric-icon">
            <i data-lucide="layout-dashboard"></i>
        </div>

        <div>
            <small>Active Dashboards</small>
            <div class="metric-value">12</div>
            <div class="metric-trend">
                No change from last month
            </div>
        </div>
    </article>
</section>

<div class="reports-main-grid">
    <section class="report-card">
        <div class="report-card-header">
            <div>
                <h2>Report Center</h2>

                <p>
                    Choose a category to explore detailed insights.
                </p>
            </div>
        </div>

        <div class="report-category-grid">
            <?php foreach ($reportCategories as $category): ?>
            <article class="report-category">
                <div class="report-category-icon <?= e(
                            $category['color_class']
                        ) ?>">
                    <i data-lucide="<?= e(
                                $category['icon']
                            ) ?>"></i>
                </div>

                <strong>
                    <?= e($category['title']) ?>
                </strong>

                <small>
                    <?= e($category['description']) ?>
                </small>

                <div class="report-category-footer">
                    <span>
                        <?= (int)$category['count'] ?>
                        Reports
                    </span>

                    <i data-lucide="chevron-right"></i>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="report-card">
        <div class="report-card-header">
            <h2>
                Fee Collection Trend
            </h2>

            <select class="form-select form-select-sm report-filter">
                <option>This Year</option>
                <option>Last Year</option>
            </select>
        </div>

        <div class="report-chart-summary">
            <strong>
                ₹1,48,75,200
            </strong>

            <small>
                ↑ 16.2% from last year
            </small>
        </div>

        <div class="report-chart-box">
            <canvas id="reportFeeChart"></canvas>
        </div>
    </section>

    <section class="report-card">
        <div class="report-card-header">
            <h2>
                Student Performance Trend
            </h2>

            <select class="form-select form-select-sm report-filter">
                <option>This Year</option>
                <option>Last Year</option>
            </select>
        </div>

        <div class="report-chart-summary">
            <strong>
                82.6%
            </strong>

            <small>
                ↑ 6.7% from last year
            </small>
        </div>

        <div class="report-chart-box">
            <canvas id="performanceChart"></canvas>
        </div>
    </section>
</div>

<section class="report-card mt-3">
    <div class="report-card-header">
        <h2>
            Recent Reports
        </h2>

        <div class="reports-table-toolbar">
            <select class="form-select form-select-sm">
                <option>All Modules</option>
                <option>Student Reports</option>
                <option>Attendance Reports</option>
                <option>Exam Reports</option>
                <option>Fee Reports</option>
                <option>Payroll Reports</option>
                <option>Transport Reports</option>
            </select>

            <select class="form-select form-select-sm">
                <option>All Formats</option>
                <option>PDF</option>
                <option>Excel</option>
                <option>CSV</option>
            </select>

            <input type="search" class="form-control form-control-sm reports-search" placeholder="Search report...">

            <a href="#" class="small text-decoration-none fw-bold">
                View All Reports
            </a>
        </div>
    </div>

    <div class="reports-table-wrap">
        <table class="reports-table">
            <thead>
                <tr>
                    <th>Report Name</th>
                    <th>Module</th>
                    <th>Date Range</th>
                    <th>Generated By</th>
                    <th>Format</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($recentReports as $report): ?>
                <tr>
                    <td>
                        <div class="report-name-cell">
                            <div class="report-name-icon <?= e(
                                        $report['icon_class']
                                    ) ?>">
                                <i data-lucide="<?= e(
                                            $report['icon']
                                        ) ?>"></i>
                            </div>

                            <div class="report-name-copy">
                                <strong>
                                    <?= e($report['report_name']) ?>
                                </strong>

                                <small>
                                    Generated report
                                </small>
                            </div>
                        </div>
                    </td>

                    <td>
                        <?= e($report['module']) ?>
                    </td>

                    <td>
                        <?= e($report['date_range']) ?>
                    </td>

                    <td>
                        <div class="generated-user">
                            <span class="generated-avatar">
                                <?= e(
                                        strtoupper(
                                            substr(
                                                $report['generated_by'],
                                                0,
                                                1
                                            )
                                        )
                                    ) ?>
                            </span>

                            <div>
                                <strong>
                                    <?= e($report['generated_by']) ?>
                                </strong>

                                <small>
                                    <?= e($report['generated_at']) ?>
                                </small>
                            </div>
                        </div>
                    </td>

                    <td>
                        <span class="report-format <?= e(
                                    $formatClass(
                                        $report['format']
                                    )
                                ) ?>">
                            <?= e($report['format']) ?>
                        </span>
                    </td>

                    <td>
                        <span class="report-status <?= e(
                                    $reportStatusClass(
                                        $report['status']
                                    )
                                ) ?>">
                            <?= e($report['status']) ?>
                        </span>
                    </td>

                    <td>
                        <div class="report-actions">
                            <?php if ($canExport): ?>
                            <button type="button" title="Download report" aria-label="Download report">
                                <i data-lucide="download"></i>
                            </button>
                            <?php endif; ?>

                            <?php if ($canPrint): ?>
                            <button type="button" title="Print report" aria-label="Print report">
                                <i data-lucide="printer"></i>
                            </button>
                            <?php endif; ?>

                            <button type="button" title="More actions" aria-label="More actions">
                                <i data-lucide="ellipsis-vertical"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="reports-table-footer">
        <span>
            Showing 1 to 5 of 25 reports
        </span>

        <div class="reports-pagination">
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

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.Chart === 'undefined') {
        return;
    }

    const months = [
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
    ];

    const feeCanvas =
        document.getElementById('reportFeeChart');

    if (feeCanvas) {
        const feeContext = feeCanvas.getContext('2d');

        const feeGradient =
            feeContext.createLinearGradient(0, 0, 0, 260);

        feeGradient.addColorStop(
            0,
            'rgba(101, 71, 232, .32)'
        );

        feeGradient.addColorStop(
            1,
            'rgba(101, 71, 232, 0)'
        );

        new Chart(feeCanvas, {
            type: 'line',

            data: {
                labels: months,

                datasets: [{
                    label: 'Fee Collection',

                    data: [
                        70,
                        100,
                        119,
                        128,
                        105,
                        140,
                        160,
                        139,
                        145,
                        166,
                        172,
                        210
                    ],

                    borderColor: '#6550df',
                    backgroundColor: feeGradient,
                    borderWidth: 3,
                    fill: true,
                    tension: .38,
                    pointRadius: 2,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#6550df'
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
                                size: 9
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
                                size: 9
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

    const performanceCanvas =
        document.getElementById('performanceChart');

    if (performanceCanvas) {
        const performanceContext =
            performanceCanvas.getContext('2d');

        const performanceGradient =
            performanceContext.createLinearGradient(
                0,
                0,
                0,
                260
            );

        performanceGradient.addColorStop(
            0,
            'rgba(44, 175, 108, .28)'
        );

        performanceGradient.addColorStop(
            1,
            'rgba(44, 175, 108, 0)'
        );

        new Chart(performanceCanvas, {
            type: 'line',

            data: {
                labels: months,

                datasets: [{
                    label: 'Student Performance',

                    data: [
                        65,
                        68,
                        72,
                        71,
                        74,
                        77,
                        79,
                        83,
                        81,
                        84,
                        86,
                        88
                    ],

                    borderColor: '#2caf6c',
                    backgroundColor: performanceGradient,
                    borderWidth: 3,
                    fill: true,
                    tension: .38,
                    pointRadius: 2,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#2caf6c'
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
                                return context.raw + '%';
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
                                size: 9
                            }
                        }
                    },

                    y: {
                        beginAtZero: true,
                        max: 100,

                        grid: {
                            color: '#edf0f6'
                        },

                        ticks: {
                            font: {
                                size: 9
                            },

                            callback: function(value) {
                                return value + '%';
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
<?php

declare(strict_types=1);

$pageTitle = 'Attendance Management';
$pageKey   = 'attendance';

$attendanceRows = [
    [
        'date'         => '17 May 2024',
        'student_name' => 'Aarav Sharma',
        'class'        => 'Class 6',
        'section'      => 'A',
        'status'       => 'Present',
        'check_in'     => '08:17 AM',
        'remarks'      => 'On time',
    ],
    [
        'date'         => '17 May 2024',
        'student_name' => 'Diya Patel',
        'class'        => 'Class 6',
        'section'      => 'A',
        'status'       => 'Absent',
        'check_in'     => '—',
        'remarks'      => 'Absent without leave',
    ],
    [
        'date'         => '17 May 2024',
        'student_name' => 'Reyansh Verma',
        'class'        => 'Class 6',
        'section'      => 'A',
        'status'       => 'Leave',
        'check_in'     => '—',
        'remarks'      => 'Medical leave',
    ],
    [
        'date'         => '17 May 2024',
        'student_name' => 'Myra Singh',
        'class'        => 'Class 6',
        'section'      => 'A',
        'status'       => 'Present',
        'check_in'     => '08:21 AM',
        'remarks'      => 'Late by 6 minutes',
    ],
    [
        'date'         => '17 May 2024',
        'student_name' => 'Kabir Joshi',
        'class'        => 'Class 6',
        'section'      => 'A',
        'status'       => 'Present',
        'check_in'     => '08:10 AM',
        'remarks'      => 'On time',
    ],
    [
        'date'         => '17 May 2024',
        'student_name' => 'Ananya Gupta',
        'class'        => 'Class 6',
        'section'      => 'A',
        'status'       => 'Absent',
        'check_in'     => '—',
        'remarks'      => 'Absent without leave',
    ],
    [
        'date'         => '17 May 2024',
        'student_name' => 'Vivaan Malhotra',
        'class'        => 'Class 6',
        'section'      => 'A',
        'status'       => 'Present',
        'check_in'     => '08:15 AM',
        'remarks'      => 'On time',
    ],
    [
        'date'         => '17 May 2024',
        'student_name' => 'Sara Khan',
        'class'        => 'Class 6',
        'section'      => 'A',
        'status'       => 'Leave',
        'check_in'     => '—',
        'remarks'      => 'Family function',
    ],
];

$weeklyAttendance = [
    [
        'class'   => 'Class 6-A',
        'monday'  => 93,
        'tuesday' => 91,
        'wednesday' => 95,
        'thursday'  => 92,
        'friday'    => 90,
        'average'   => 92.2,
    ],
    [
        'class'   => 'Class 7-B',
        'monday'  => 88,
        'tuesday' => 90,
        'wednesday' => 89,
        'thursday'  => 86,
        'friday'    => 91,
        'average'   => 88.8,
    ],
    [
        'class'   => 'Class 8-A',
        'monday'  => 94,
        'tuesday' => 93,
        'wednesday' => 92,
        'thursday'  => 95,
        'friday'    => 93,
        'average'   => 93.4,
    ],
    [
        'class'   => 'Class 9-C',
        'monday'  => 90,
        'tuesday' => 87,
        'wednesday' => 89,
        'thursday'  => 91,
        'friday'    => 88,
        'average'   => 89.0,
    ],
    [
        'class'   => 'Class 10-A',
        'monday'  => 92,
        'tuesday' => 94,
        'wednesday' => 93,
        'thursday'  => 92,
        'friday'    => 91,
        'average'   => 92.4,
    ],
];

$pendingApprovals = [
    [
        'request_id'  => 'ATT-2024-105',
        'student_name'=> 'Diya Patel',
        'class'       => 'Class 6-A',
        'dates'       => '15 May 2024',
        'reason'      => 'Medical appointment',
        'requested_on'=> '15 May 2024, 09:12 AM',
    ],
    [
        'request_id'  => 'ATT-2024-104',
        'student_name'=> 'Reyansh Verma',
        'class'       => 'Class 6-A',
        'dates'       => '16–17 May 2024',
        'reason'      => 'Fever',
        'requested_on'=> '16 May 2024, 08:45 AM',
    ],
    [
        'request_id'  => 'ATT-2024-103',
        'student_name'=> 'Sara Khan',
        'class'       => 'Class 6-A',
        'dates'       => '17 May 2024',
        'reason'      => 'Family function',
        'requested_on'=> '17 May 2024, 07:55 AM',
    ],
];

require __DIR__ . '/includes/layout-start.php';

$canView = !function_exists('has_permission')
    || has_permission($pageKey, 'view');

$canCreate = !function_exists('has_permission')
    || has_permission($pageKey, 'create');

$canEdit = !function_exists('has_permission')
    || has_permission($pageKey, 'edit');

$canApprove = !function_exists('has_permission')
    || has_permission($pageKey, 'approve');

$canExport = !function_exists('has_permission')
    || has_permission($pageKey, 'export');

if (!$canView) {
    http_response_code(403);
    ?>
<div class="alert alert-danger">
    You do not have permission to view the Attendance module.
</div>
<?php
    require __DIR__ . '/includes/layout-end.php';
    exit;
}

$statusClass = static function (string $status): string {
    return match ($status) {
        'Present' => 'attendance-badge-success',
        'Absent'  => 'attendance-badge-danger',
        'Leave'   => 'attendance-badge-warning',
        default   => 'attendance-badge-info',
    };
};

$heatClass = static function (float $percentage): string {
    if ($percentage >= 92) {
        return 'heat-excellent';
    }

    if ($percentage >= 90) {
        return 'heat-good';
    }

    if ($percentage >= 88) {
        return 'heat-medium';
    }

    return 'heat-low';
};
?>

<style>
.attendance-overview-grid {
    display: grid;
    grid-template-columns: .85fr 1.65fr;
    gap: 16px;
    align-items: stretch;
}

.attendance-bottom-grid {
    display: grid;
    grid-template-columns: .7fr 1.5fr;
    gap: 16px;
    margin-top: 16px;
}

.attendance-card {
    overflow: hidden;
    background: var(--card-bg, #ffffff);
    border: 1px solid var(--border-soft, #e7ebf3);
    border-radius: 13px;
    box-shadow: 0 5px 18px rgba(15, 23, 42, .04);
}

.attendance-card-header {
    min-height: 54px;
    padding: 13px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    border-bottom: 1px solid var(--border-soft, #e7ebf3);
}

.attendance-card-header h2 {
    margin: 0;
    font-size: 14px;
    font-weight: 800;
}

.attendance-card-header a {
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
}

.attendance-header-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.attendance-header-actions .form-control {
    width: 155px;
    font-size: 12px;
}

.attendance-table-wrap {
    overflow-x: auto;
}

.attendance-table {
    width: 100%;
    min-width: 950px;
    border-collapse: collapse;
}

.attendance-table th,
.attendance-table td {
    padding: 10px 11px;
    border-bottom: 1px solid var(--border-soft, #e7ebf3);
    font-size: 12px;
    text-align: left;
    vertical-align: middle;
}

.attendance-table th {
    color: #475569;
    background: #f8fafc;
    font-weight: 800;
}

.attendance-student {
    display: flex;
    align-items: center;
    gap: 9px;
    font-weight: 700;
}

.attendance-avatar {
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

.attendance-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 9px;
    border-radius: 30px;
    font-size: 10px;
    font-weight: 800;
    white-space: nowrap;
}

.attendance-badge-success {
    color: #168448;
    background: #e8f8ef;
}

.attendance-badge-danger {
    color: #dc263f;
    background: #feeaee;
}

.attendance-badge-warning {
    color: #d97706;
    background: #fff2dc;
}

.attendance-badge-info {
    color: #3164ce;
    background: #e8f1ff;
}

.attendance-action {
    width: 30px;
    height: 30px;
    display: grid;
    place-items: center;
    border: 1px solid var(--border-soft, #e7ebf3);
    border-radius: 8px;
    color: var(--brand-1, #6547e8);
    background: #ffffff;
}

.attendance-action svg {
    width: 14px;
    height: 14px;
}

.attendance-footer {
    padding: 13px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    color: var(--text-muted, #64748b);
    font-size: 12px;
}

.attendance-pagination {
    display: flex;
    align-items: center;
    gap: 6px;
}

.attendance-pagination button {
    min-width: 30px;
    height: 30px;
    padding: 0 8px;
    border: 1px solid var(--border-soft, #e7ebf3);
    border-radius: 7px;
    color: #475569;
    background: #ffffff;
}

.attendance-pagination button.active {
    color: #ffffff;
    border-color: transparent;
    background: linear-gradient(135deg,
            var(--brand-1, #6547e8),
            var(--brand-2, #315ed8));
}

.weekly-table-wrap {
    overflow-x: auto;
    padding: 8px 12px 14px;
}

.weekly-table {
    width: 100%;
    min-width: 520px;
    border-collapse: separate;
    border-spacing: 0;
}

.weekly-table th,
.weekly-table td {
    padding: 11px 9px;
    border-bottom: 1px solid var(--border-soft, #e7ebf3);
    font-size: 11px;
    text-align: center;
}

.weekly-table th:first-child,
.weekly-table td:first-child {
    text-align: left;
    font-weight: 800;
}

.weekly-table th {
    color: #475569;
    background: #f8fafc;
    font-weight: 800;
}

.heat-excellent {
    color: #137c43;
    background: #dff7e9;
    font-weight: 800;
}

.heat-good {
    color: #24814c;
    background: #eaf8ef;
    font-weight: 800;
}

.heat-medium {
    color: #b76508;
    background: #fff4df;
    font-weight: 800;
}

.heat-low {
    color: #ca4a24;
    background: #ffebe4;
    font-weight: 800;
}

.weekly-average {
    color: #2f65d4;
    font-weight: 900;
}

.attendance-summary {
    display: grid;
    grid-template-columns: minmax(220px, 1fr) 160px;
    gap: 12px;
    padding: 16px;
    align-items: center;
}

.attendance-chart-box {
    position: relative;
    height: 230px;
}

.attendance-chart-center {
    position: absolute;
    top: 50%;
    left: 50%;
    z-index: 2;
    text-align: center;
    pointer-events: none;
    transform: translate(-50%, -56%);
}

.attendance-chart-center strong,
.attendance-chart-center small {
    display: block;
}

.attendance-chart-center strong {
    font-size: 24px;
}

.attendance-chart-center small {
    color: var(--text-muted, #64748b);
    font-size: 10px;
}

.attendance-small-stat {
    padding: 13px;
    margin-bottom: 10px;
    border-radius: 11px;
    background: #edf9f2;
}

.attendance-small-stat:last-child {
    margin-bottom: 0;
    background: #eef4ff;
}

.attendance-small-stat small,
.attendance-small-stat strong,
.attendance-small-stat em {
    display: block;
}

.attendance-small-stat small {
    color: var(--text-muted, #64748b);
    font-size: 10px;
}

.attendance-small-stat strong {
    margin-top: 3px;
    font-size: 17px;
}

.attendance-small-stat em {
    margin-top: 2px;
    color: #149351;
    font-size: 10px;
    font-style: normal;
    font-weight: 800;
}

.approval-table {
    width: 100%;
    min-width: 850px;
    border-collapse: collapse;
}

.approval-table th,
.approval-table td {
    padding: 10px 11px;
    border-bottom: 1px solid var(--border-soft, #e7ebf3);
    font-size: 11px;
    text-align: left;
}

.approval-table th {
    color: #475569;
    background: #f8fafc;
    font-weight: 800;
}

.approval-student {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 700;
}

.approval-actions {
    display: flex;
    gap: 6px;
}

.approval-button {
    width: 32px;
    height: 28px;
    display: grid;
    place-items: center;
    border-radius: 7px;
    background: #ffffff;
}

.approval-button.approve {
    color: #159152;
    border: 1px solid #bce8cf;
}

.approval-button.reject {
    color: #dc263f;
    border: 1px solid #fac4cc;
}

.approval-button svg {
    width: 14px;
    height: 14px;
}

@media (max-width: 1199.98px) {
    .attendance-overview-grid {
        grid-template-columns: 1fr;
    }

    .attendance-bottom-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 767.98px) {
    .attendance-header-actions {
        display: none;
    }

    .attendance-summary {
        grid-template-columns: 1fr;
    }

    .attendance-footer {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>

<div class="page-heading">
    <div>
        <h1 class="page-title">
            Attendance Management
        </h1>

        <p class="page-subtitle">
            Track and manage student attendance efficiently
        </p>
    </div>

    <div class="page-actions">
        <?php if ($canCreate): ?>
        <button type="button" class="btn-ui btn-primary-ui" data-demo-action="Mark Attendance">
            <i data-lucide="plus"></i>
            Mark Attendance
        </button>

        <button type="button" class="btn-ui">
            <i data-lucide="calendar-sync"></i>
            Bulk Update
        </button>
        <?php endif; ?>

        <?php if ($canExport): ?>
        <button type="button" class="btn-ui">
            <i data-lucide="download"></i>
            Export Report
        </button>
        <?php endif; ?>

        <button type="button" class="btn-ui">
            <i data-lucide="filter"></i>
            Filters
        </button>
    </div>
</div>

<section class="metric-grid">
    <article class="metric-card metric-green">
        <div class="metric-icon">
            <i data-lucide="user-check"></i>
        </div>

        <div>
            <small>Present Today</small>
            <div class="metric-value">2,178</div>
            <div class="metric-trend">
                85.4% of total students
            </div>
        </div>
    </article>

    <article class="metric-card metric-pink">
        <div class="metric-icon">
            <i data-lucide="user-x"></i>
        </div>

        <div>
            <small>Absent Today</small>
            <div class="metric-value">284</div>
            <div class="metric-trend">
                11.1% of total students
            </div>
        </div>
    </article>

    <article class="metric-card metric-orange">
        <div class="metric-icon">
            <i data-lucide="calendar-clock"></i>
        </div>

        <div>
            <small>Leave Today</small>
            <div class="metric-value">93</div>
            <div class="metric-trend">
                3.6% of total students
            </div>
        </div>
    </article>

    <article class="metric-card metric-blue">
        <div class="metric-icon">
            <i data-lucide="chart-pie"></i>
        </div>

        <div>
            <small>Attendance Percentage</small>
            <div class="metric-value">91.2%</div>
            <div class="metric-trend">
                ↑ 3.2% from last week
            </div>
        </div>
    </article>
</section>

<div class="attendance-overview-grid">
    <section class="attendance-card">
        <div class="attendance-card-header">
            <h2>
                Weekly Class Attendance Overview
            </h2>

            <select class="form-select form-select-sm w-auto">
                <option>This Week</option>
                <option>Last Week</option>
                <option>This Month</option>
            </select>
        </div>

        <div class="weekly-table-wrap">
            <table class="weekly-table">
                <thead>
                    <tr>
                        <th>Class</th>
                        <th>Mon</th>
                        <th>Tue</th>
                        <th>Wed</th>
                        <th>Thu</th>
                        <th>Fri</th>
                        <th>Average</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($weeklyAttendance as $weekly): ?>
                    <tr>
                        <td>
                            <?= e($weekly['class']) ?>
                        </td>

                        <?php
                            foreach (
                                [
                                    'monday',
                                    'tuesday',
                                    'wednesday',
                                    'thursday',
                                    'friday',
                                ] as $day
                            ):
                                $value = (float)$weekly[$day];
                                ?>
                        <td class="<?= e($heatClass($value)) ?>">
                            <?= e(number_format($value, 0)) ?>%
                        </td>
                        <?php endforeach; ?>

                        <td class="weekly-average">
                            <?= e(number_format(
                                    (float)$weekly['average'],
                                    1
                                )) ?>%
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="attendance-card">
        <div class="attendance-card-header">
            <h2>
                Attendance Entry / List
            </h2>

            <div class="attendance-header-actions">
                <input type="date" class="form-control form-control-sm" value="2024-05-17">
            </div>
        </div>

        <div class="attendance-table-wrap">
            <table class="attendance-table">
                <thead>
                    <tr>
                        <th>
                            <input type="checkbox" class="form-check-input">
                        </th>
                        <th>Date</th>
                        <th>Student</th>
                        <th>Class</th>
                        <th>Section</th>
                        <th>Status</th>
                        <th>Check-in</th>
                        <th>Remarks</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($attendanceRows as $attendance): ?>
                    <tr>
                        <td>
                            <input type="checkbox" class="form-check-input">
                        </td>

                        <td>
                            <?= e($attendance['date']) ?>
                        </td>

                        <td>
                            <div class="attendance-student">
                                <span class="attendance-avatar">
                                    <?= e(strtoupper(substr(
                                            $attendance['student_name'],
                                            0,
                                            1
                                        ))) ?>
                                </span>

                                <?= e($attendance['student_name']) ?>
                            </div>
                        </td>

                        <td>
                            <?= e($attendance['class']) ?>
                        </td>

                        <td>
                            <?= e($attendance['section']) ?>
                        </td>

                        <td>
                            <span class="attendance-badge <?= e(
                                        $statusClass(
                                            $attendance['status']
                                        )
                                    ) ?>">
                                <?= e($attendance['status']) ?>
                            </span>
                        </td>

                        <td>
                            <?= e($attendance['check_in']) ?>
                        </td>

                        <td>
                            <?= e($attendance['remarks']) ?>
                        </td>

                        <td>
                            <?php if ($canEdit): ?>
                            <button type="button" class="attendance-action" title="Edit attendance">
                                <i data-lucide="pencil"></i>
                            </button>
                            <?php else: ?>
                            <button type="button" class="attendance-action" title="View attendance">
                                <i data-lucide="eye"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="attendance-footer">
            <span>
                Showing 1 to 8 of 32 students
            </span>

            <div class="attendance-pagination">
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
                    <i data-lucide="chevron-right"></i>
                </button>
            </div>
        </div>
    </section>
</div>

<div class="attendance-bottom-grid">
    <section class="attendance-card">
        <div class="attendance-card-header">
            <h2>
                Today’s Attendance Summary
            </h2>
        </div>

        <div class="attendance-summary">
            <div class="attendance-chart-box">
                <div class="attendance-chart-center">
                    <strong>2,555</strong>
                    <small>Total Students</small>
                </div>

                <canvas id="attendanceSummaryChart"></canvas>
            </div>

            <div>
                <div class="attendance-small-stat">
                    <small>Classes Held</small>
                    <strong>42 / 42</strong>
                    <em>100%</em>
                </div>

                <div class="attendance-small-stat">
                    <small>Subjects Scheduled</small>
                    <strong>168 / 168</strong>
                    <em>100%</em>
                </div>
            </div>
        </div>
    </section>

    <section class="attendance-card">
        <div class="attendance-card-header">
            <h2>
                Pending Attendance Approvals
            </h2>

            <a href="#">
                View All
            </a>
        </div>

        <div class="attendance-table-wrap">
            <table class="approval-table">
                <thead>
                    <tr>
                        <th>Request ID</th>
                        <th>Student</th>
                        <th>Class</th>
                        <th>Date(s)</th>
                        <th>Reason</th>
                        <th>Requested On</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($pendingApprovals as $approval): ?>
                    <tr>
                        <td>
                            #<?= e($approval['request_id']) ?>
                        </td>

                        <td>
                            <div class="approval-student">
                                <span class="attendance-avatar">
                                    <?= e(strtoupper(substr(
                                            $approval['student_name'],
                                            0,
                                            1
                                        ))) ?>
                                </span>

                                <?= e($approval['student_name']) ?>
                            </div>
                        </td>

                        <td>
                            <?= e($approval['class']) ?>
                        </td>

                        <td>
                            <?= e($approval['dates']) ?>
                        </td>

                        <td>
                            <?= e($approval['reason']) ?>
                        </td>

                        <td>
                            <?= e($approval['requested_on']) ?>
                        </td>

                        <td>
                            <?php if ($canApprove): ?>
                            <div class="approval-actions">
                                <button type="button" class="approval-button approve" title="Approve">
                                    <i data-lucide="check"></i>
                                </button>

                                <button type="button" class="approval-button reject" title="Reject">
                                    <i data-lucide="x"></i>
                                </button>
                            </div>
                            <?php else: ?>
                            <button type="button" class="attendance-action">
                                <i data-lucide="eye"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const chartElement =
        document.getElementById('attendanceSummaryChart');

    if (
        !chartElement ||
        typeof window.Chart === 'undefined'
    ) {
        return;
    }

    new Chart(chartElement, {
        type: 'doughnut',

        data: {
            labels: [
                'Present',
                'Absent',
                'Leave'
            ],

            datasets: [{
                label: 'Attendance',

                data: [
                    2178,
                    284,
                    93
                ],

                backgroundColor: [
                    '#2caf6c',
                    '#f04468',
                    '#ffad1f'
                ],

                borderWidth: 0,
                hoverOffset: 6
            }]
        },

        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',

            plugins: {
                legend: {
                    position: 'bottom',

                    labels: {
                        usePointStyle: true,
                        boxWidth: 8,
                        padding: 14,
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
                                context.raw;
                        }
                    }
                }
            }
        }
    });
});
</script>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
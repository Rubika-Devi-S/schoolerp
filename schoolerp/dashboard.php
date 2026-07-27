<?php

declare(strict_types=1);

$pageTitle = 'Dashboard';
$pageKey   = 'dashboard';

$recentAdmissions = [
    [
        'application_no' => 'APP-2024-2501',
        'student_name'   => 'Aaradhya Singh',
        'class'          => 'Class 1',
        'parent_name'    => 'Rajeev Singh',
        'submitted'      => '15 May 2024',
        'status'         => 'Under Review',
    ],
    [
        'application_no' => 'APP-2024-2487',
        'student_name'   => 'Vihaan Mehta',
        'class'          => 'Class 3',
        'parent_name'    => 'Amit Mehta',
        'submitted'      => '14 May 2024',
        'status'         => 'Approved',
    ],
    [
        'application_no' => 'APP-2024-2475',
        'student_name'   => 'Myra Patel',
        'class'          => 'Class 5',
        'parent_name'    => 'Pankaj Patel',
        'submitted'      => '13 May 2024',
        'status'         => 'Under Review',
    ],
    [
        'application_no' => 'APP-2024-2461',
        'student_name'   => 'Kabir Verma',
        'class'          => 'Class 2',
        'parent_name'    => 'Sandeep Verma',
        'submitted'      => '12 May 2024',
        'status'         => 'Approved',
    ],
    [
        'application_no' => 'APP-2024-2449',
        'student_name'   => 'Ananya Gupta',
        'class'          => 'Class 6',
        'parent_name'    => 'Vivek Gupta',
        'submitted'      => '11 May 2024',
        'status'         => 'Pending Payment',
    ],
];

$todayEvents = [
    [
        'title'    => 'Staff Meeting',
        'time'     => '09:00 AM - 10:00 AM',
        'location' => 'Staff Room',
        'icon'     => 'calendar-check',
        'class'    => 'event-purple',
    ],
    [
        'title'    => 'Class 10 - Mathematics',
        'time'     => '10:30 AM - 12:30 PM',
        'location' => 'Room 101',
        'icon'     => 'book-open-check',
        'class'    => 'event-blue',
    ],
    [
        'title'    => 'Parent Teacher Meeting',
        'time'     => '02:00 PM - 04:00 PM',
        'location' => 'Conference Hall',
        'icon'     => 'users-round',
        'class'    => 'event-green',
    ],
    [
        'title'    => 'Sports Practice',
        'time'     => '04:30 PM - 06:30 PM',
        'location' => 'Play Ground',
        'icon'     => 'dumbbell',
        'class'    => 'event-orange',
    ],
];

$recentActivities = [
    [
        'title' => 'New admission application received',
        'time'  => '2 minutes ago',
    ],
    [
        'title' => 'Fee payment of ₹18,500 received',
        'time'  => '15 minutes ago',
    ],
    [
        'title' => 'Attendance marked for Class 6-A',
        'time'  => '1 hour ago',
    ],
    [
        'title' => 'Exam results published for Class 10',
        'time'  => '2 hours ago',
    ],
    [
        'title' => 'Staff salary processed successfully',
        'time'  => '3 hours ago',
    ],
];

$upcomingExams = [
    [
        'title' => 'Unit Test - 1',
        'class' => 'Class 10 - All Sections',
        'days'  => '2 Days',
        'badge' => 'blue',
        'icon'  => 'calendar-days',
    ],
    [
        'title' => 'Half Yearly Examination',
        'class' => 'Class 6 - 12',
        'days'  => '9 Days',
        'badge' => 'orange',
        'icon'  => 'calendar-days',
    ],
    [
        'title' => 'Science Practical',
        'class' => 'Class 9 - 10',
        'days'  => '19 Days',
        'badge' => 'green',
        'icon'  => 'flask-conical',
    ],
];

require __DIR__ . '/includes/layout-start.php';

$canViewReports = !function_exists('has_permission')
    || has_permission('reports', 'view');

function dashboardAdmissionBadge(string $status): string
{
    return match ($status) {
        'Approved'        => 'dashboard-badge-success',
        'Pending Payment' => 'dashboard-badge-warning',
        default           => 'dashboard-badge-info',
    };
}
?>

<style>
.dashboard-page {
    display: grid;
    gap: 16px;
}

.dashboard-main-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.4fr) minmax(310px, .85fr) minmax(300px, .8fr);
    gap: 16px;
    align-items: stretch;
}

.dashboard-lower-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.7fr) minmax(300px, .65fr);
    gap: 16px;
    align-items: stretch;
}

.dashboard-card {
    overflow: hidden;
    background: var(--card-bg, #ffffff);
    border: 1px solid var(--border-soft, #e7ebf3);
    border-radius: 13px;
    box-shadow: 0 5px 18px rgba(15, 23, 42, .04);
}

.dashboard-card-header {
    min-height: 54px;
    padding: 13px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    border-bottom: 1px solid var(--border-soft, #e7ebf3);
}

.dashboard-card-header h2 {
    margin: 0;
    color: var(--text-main, #101a3b);
    font-size: 14px;
    font-weight: 700;
    letter-spacing: -.15px;
}

.dashboard-card-header a {
    font-size: 11px;
    font-weight: 700;
    text-decoration: none;
}

.dashboard-chart-summary {
    padding: 16px 16px 0;
}

.dashboard-chart-summary strong,
.dashboard-chart-summary small {
    display: block;
}

.dashboard-chart-summary strong {
    font-size: 22px;
    font-weight: 800;
    letter-spacing: -.5px;
}

.dashboard-chart-summary small {
    margin-top: 3px;
    color: #169454;
    font-size: 10px;
    font-weight: 700;
}

.dashboard-chart-box {
    height: 245px;
    padding: 5px 13px 15px;
}

.attendance-widget {
    padding: 18px;
    display: grid;
    grid-template-columns: minmax(170px, 1fr) minmax(120px, .7fr);
    align-items: center;
    gap: 14px;
}

.attendance-chart-wrap {
    position: relative;
    height: 210px;
}

.attendance-center {
    position: absolute;
    inset: 50% auto auto 50%;
    z-index: 2;
    text-align: center;
    transform: translate(-50%, -52%);
    pointer-events: none;
}

.attendance-center strong,
.attendance-center small {
    display: block;
}

.attendance-center strong {
    font-size: 22px;
    font-weight: 800;
}

.attendance-center small {
    margin-top: 2px;
    color: var(--text-muted, #64748b);
    font-size: 9px;
}

.attendance-legend {
    display: grid;
    gap: 14px;
}

.attendance-legend-row {
    display: grid;
    grid-template-columns: 9px 1fr;
    gap: 8px;
    align-items: start;
    font-size: 11px;
}

.attendance-legend-row span:first-child {
    width: 9px;
    height: 9px;
    margin-top: 4px;
    border-radius: 50%;
}

.attendance-legend-row strong,
.attendance-legend-row small {
    display: block;
}

.attendance-legend-row strong {
    font-size: 11px;
}

.attendance-legend-row small {
    margin-top: 3px;
    color: var(--text-muted, #64748b);
    font-size: 10px;
}

.event-list {
    padding: 3px 15px 8px;
}

.event-item {
    padding: 12px 0;
    display: grid;
    grid-template-columns: 40px minmax(0, 1fr);
    gap: 11px;
    align-items: center;
    border-bottom: 1px solid var(--border-soft, #e7ebf3);
}

.event-item:last-child {
    border-bottom: 0;
}

.event-icon {
    width: 40px;
    height: 40px;
    display: grid;
    place-items: center;
    border-radius: 50%;
}

.event-icon svg {
    width: 18px;
    height: 18px;
}

.event-purple {
    color: #6747dd;
    background: #eeeafd;
}

.event-blue {
    color: #3168d8;
    background: #eaf1ff;
}

.event-green {
    color: #189354;
    background: #e9f8ef;
}

.event-orange {
    color: #df7d0b;
    background: #fff2df;
}

.event-copy strong,
.event-copy small {
    display: block;
}

.event-copy strong {
    font-size: 11px;
}

.event-copy small {
    margin-top: 2px;
    color: var(--text-muted, #64748b);
    font-size: 9px;
    line-height: 1.4;
}

.dashboard-table-wrap {
    overflow-x: auto;
}

.dashboard-table {
    width: 100%;
    min-width: 850px;
    border-collapse: collapse;
}

.dashboard-table th,
.dashboard-table td {
    padding: 10px 12px;
    border-bottom: 1px solid var(--border-soft, #e7ebf3);
    font-size: 11px;
    text-align: left;
    vertical-align: middle;
}

.dashboard-table th {
    color: #475569;
    background: #f8fafc;
    font-weight: 700;
}

.dashboard-student {
    display: flex;
    align-items: center;
    gap: 9px;
    font-weight: 700;
}

.dashboard-avatar {
    width: 28px;
    height: 28px;
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    border-radius: 50%;
    color: #ffffff;
    background: linear-gradient(135deg,
            var(--brand-1, #6547e8),
            var(--brand-2, #315ed8));
    font-size: 10px;
    font-weight: 800;
}

.dashboard-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 8px;
    border-radius: 30px;
    font-size: 9px;
    font-weight: 800;
    white-space: nowrap;
}

.dashboard-badge-success {
    color: #168448;
    background: #e8f8ef;
}

.dashboard-badge-info {
    color: #3164ce;
    background: #e8f1ff;
}

.dashboard-badge-warning {
    color: #d97706;
    background: #fff2dc;
}

.activity-timeline {
    padding: 10px 16px 14px;
}

.activity-timeline-item {
    position: relative;
    padding: 8px 0 10px 23px;
}

.activity-timeline-item::before {
    position: absolute;
    top: 14px;
    left: 4px;
    width: 7px;
    height: 7px;
    content: "";
    border-radius: 50%;
    background: linear-gradient(135deg,
            var(--brand-1, #6547e8),
            var(--brand-2, #315ed8));
}

.activity-timeline-item::after {
    position: absolute;
    top: 21px;
    bottom: -3px;
    left: 7px;
    width: 1px;
    content: "";
    background: #dfe4ef;
}

.activity-timeline-item:last-child::after {
    display: none;
}

.activity-timeline-item strong,
.activity-timeline-item small {
    display: block;
}

.activity-timeline-item strong {
    font-size: 11px;
    font-weight: 650;
}

.activity-timeline-item small {
    margin-top: 3px;
    color: var(--text-muted, #64748b);
    font-size: 9px;
}

.dashboard-extra-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.quick-summary-list {
    padding: 6px 16px 12px;
}

.quick-summary-row {
    padding: 11px 0;
    display: flex;
    align-items: center;
    gap: 10px;
    border-bottom: 1px solid var(--border-soft, #e7ebf3);
}

.quick-summary-row:last-child {
    border-bottom: 0;
}

.quick-summary-row svg {
    width: 18px;
    height: 18px;
    color: var(--brand-1, #6547e8);
}

.quick-summary-row div {
    min-width: 0;
}

.quick-summary-row strong,
.quick-summary-row small {
    display: block;
}

.quick-summary-row strong {
    font-size: 11px;
}

.quick-summary-row small {
    margin-top: 2px;
    color: var(--text-muted, #64748b);
    font-size: 9px;
}

.quick-summary-row>b {
    margin-left: auto;
    font-size: 12px;
}

.exam-list {
    padding: 4px 16px 10px;
}

.exam-list .list-clean li {
    padding: 14px 0;
}

.exam-list .list-clean svg {
    width: 19px;
    height: 19px;
}

@media (max-width: 1399.98px) {
    .dashboard-main-grid {
        grid-template-columns: 1fr 1fr;
    }

    .dashboard-main-grid>article:first-child {
        grid-column: 1 / -1;
    }

    .dashboard-lower-grid {
        grid-template-columns: 1.35fr .65fr;
    }
}

@media (max-width: 991.98px) {

    .dashboard-main-grid,
    .dashboard-lower-grid,
    .dashboard-extra-grid {
        grid-template-columns: 1fr;
    }

    .dashboard-main-grid>article:first-child {
        grid-column: auto;
    }
}

@media (max-width: 575.98px) {
    .attendance-widget {
        grid-template-columns: 1fr;
    }

    .dashboard-chart-box {
        height: 225px;
    }
}
</style>

<div class="dashboard-page">
    <div class="page-heading">
        <div>
            <h1 class="page-title">
                School Dashboard
            </h1>

            <p class="page-subtitle">
                Welcome back. Here is today’s school overview.
            </p>
        </div>

        <div class="page-actions">
            <a href="admissions.php" class="btn-ui">
                <i data-lucide="user-plus"></i>
                New Admission
            </a>

            <a href="attendance.php" class="btn-ui btn-primary-ui">
                <i data-lucide="calendar-check"></i>
                Mark Attendance
            </a>
        </div>
    </div>

    <section class="metric-grid">
        <article class="metric-card metric-purple">
            <div class="metric-icon">
                <i data-lucide="users"></i>
            </div>

            <div>
                <small>Total Students</small>
                <div class="metric-value">2,458</div>
                <div class="metric-trend">
                    ↑ 4.6% from last month
                </div>
            </div>
        </article>

        <article class="metric-card metric-blue">
            <div class="metric-icon">
                <i data-lucide="presentation"></i>
            </div>

            <div>
                <small>Total Teachers</small>
                <div class="metric-value">186</div>
                <div class="metric-trend">
                    ↑ 2.3% from last month
                </div>
            </div>
        </article>

        <article class="metric-card metric-green">
            <div class="metric-icon">
                <i data-lucide="user-check"></i>
            </div>

            <div>
                <small>Today’s Attendance</small>
                <div class="metric-value">2,178</div>
                <div class="metric-trend">
                    85.4% present
                </div>
            </div>
        </article>

        <article class="metric-card metric-orange">
            <div class="metric-icon">
                <i data-lucide="indian-rupee"></i>
            </div>

            <div>
                <small>Monthly Collection</small>
                <div class="metric-value">
                    ₹1,12,45,300
                </div>
                <div class="metric-trend">
                    ↑ 18.7% from last year
                </div>
            </div>
        </article>
    </section>

    <section class="dashboard-main-grid">
        <article class="dashboard-card">
            <div class="dashboard-card-header">
                <h2>Monthly Fee Collection</h2>

                <span class="status blue">
                    This Year
                </span>
            </div>

            <div class="dashboard-chart-summary">
                <strong>₹1,12,45,300</strong>
                <small>↑ 18.7% from last year</small>
            </div>

            <div class="dashboard-chart-box">
                <canvas id="dashboardFeeChart"></canvas>
            </div>
        </article>

        <article class="dashboard-card">
            <div class="dashboard-card-header">
                <h2>Student Attendance Today</h2>

                <span class="status green">
                    91.2%
                </span>
            </div>

            <div class="attendance-widget">
                <div class="attendance-chart-wrap">
                    <div class="attendance-center">
                        <strong>91.2%</strong>
                        <small>Attendance</small>
                    </div>

                    <canvas id="dashboardAttendanceChart"></canvas>
                </div>

                <div class="attendance-legend">
                    <div class="attendance-legend-row">
                        <span style="background:#2caf6c"></span>

                        <div>
                            <strong>Present</strong>
                            <small>2,178 (85.4%)</small>
                        </div>
                    </div>

                    <div class="attendance-legend-row">
                        <span style="background:#ffad1f"></span>

                        <div>
                            <strong>Leave</strong>
                            <small>93 (3.6%)</small>
                        </div>
                    </div>

                    <div class="attendance-legend-row">
                        <span style="background:#f04468"></span>

                        <div>
                            <strong>Absent</strong>
                            <small>284 (11.1%)</small>
                        </div>
                    </div>
                </div>
            </div>
        </article>

        <article class="dashboard-card">
            <div class="dashboard-card-header">
                <h2>Today’s Events</h2>

                <a href="academic-calendar.php">
                    View Calendar
                </a>
            </div>

            <div class="event-list">
                <?php foreach ($todayEvents as $event): ?>
                <div class="event-item">
                    <div class="event-icon <?= e($event['class']) ?>">
                        <i data-lucide="<?= e($event['icon']) ?>"></i>
                    </div>

                    <div class="event-copy">
                        <strong>
                            <?= e($event['title']) ?>
                        </strong>

                        <small>
                            <?= e($event['time']) ?>
                        </small>

                        <small>
                            <?= e($event['location']) ?>
                        </small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </article>
    </section>

    <section class="dashboard-lower-grid">
        <article class="dashboard-card">
            <div class="dashboard-card-header">
                <h2>Recent Admissions</h2>

                <a href="admissions.php">
                    View All
                </a>
            </div>

            <div class="dashboard-table-wrap">
                <table class="dashboard-table">
                    <thead>
                        <tr>
                            <th>Application No</th>
                            <th>Student Name</th>
                            <th>Class</th>
                            <th>Parent Name</th>
                            <th>Submitted</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($recentAdmissions as $admission): ?>
                        <tr>
                            <td>
                                <?= e($admission['application_no']) ?>
                            </td>

                            <td>
                                <div class="dashboard-student">
                                    <span class="dashboard-avatar">
                                        <?= e(strtoupper(substr(
                                                $admission['student_name'],
                                                0,
                                                1
                                            ))) ?>
                                    </span>

                                    <?= e($admission['student_name']) ?>
                                </div>
                            </td>

                            <td>
                                <?= e($admission['class']) ?>
                            </td>

                            <td>
                                <?= e($admission['parent_name']) ?>
                            </td>

                            <td>
                                <?= e($admission['submitted']) ?>
                            </td>

                            <td>
                                <span class="dashboard-badge <?= e(
                                            dashboardAdmissionBadge(
                                                $admission['status']
                                            )
                                        ) ?>">
                                    <?= e($admission['status']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </article>

        <article class="dashboard-card">
            <div class="dashboard-card-header">
                <h2>Recent Activity</h2>

                <a href="activity-logs.php">
                    View All
                </a>
            </div>

            <div class="activity-timeline">
                <?php foreach ($recentActivities as $activity): ?>
                <div class="activity-timeline-item">
                    <strong>
                        <?= e($activity['title']) ?>
                    </strong>

                    <small>
                        <?= e($activity['time']) ?>
                    </small>
                </div>
                <?php endforeach; ?>
            </div>
        </article>
    </section>

    <section class="dashboard-extra-grid">
        <article class="dashboard-card">
            <div class="dashboard-card-header">
                <h2>Upcoming Exams</h2>

                <a href="examinations.php">
                    View All
                </a>
            </div>

            <div class="exam-list">
                <ul class="list-clean">
                    <?php foreach ($upcomingExams as $exam): ?>
                    <li>
                        <i data-lucide="<?= e($exam['icon']) ?>"></i>

                        <div>
                            <strong>
                                <?= e($exam['title']) ?>
                            </strong>

                            <div class="small text-muted">
                                <?= e($exam['class']) ?>
                            </div>
                        </div>

                        <span class="status <?= e($exam['badge']) ?> ms-auto">
                            <?= e($exam['days']) ?>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </article>

        <article class="dashboard-card">
            <div class="dashboard-card-header">
                <h2>Quick School Summary</h2>

                <?php if ($canViewReports): ?>
                <a href="reports.php">
                    Open Reports
                </a>
                <?php endif; ?>
            </div>

            <div class="quick-summary-list">
                <div class="quick-summary-row">
                    <i data-lucide="school"></i>

                    <div>
                        <strong>Classes & Sections</strong>
                        <small>12 classes and 36 active sections</small>
                    </div>

                    <b>48</b>
                </div>

                <div class="quick-summary-row">
                    <i data-lucide="bus-front"></i>

                    <div>
                        <strong>Transport Routes</strong>
                        <small>18 active routes and 24 vehicles</small>
                    </div>

                    <b>18</b>
                </div>

                <div class="quick-summary-row">
                    <i data-lucide="book-open"></i>

                    <div>
                        <strong>Library Books</strong>
                        <small>12,480 books with 386 issued</small>
                    </div>

                    <b>12.4K</b>
                </div>

                <div class="quick-summary-row">
                    <i data-lucide="wallet-cards"></i>

                    <div>
                        <strong>Pending Fee Accounts</strong>
                        <small>512 students have pending balances</small>
                    </div>

                    <b>512</b>
                </div>
            </div>
        </article>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.Chart === 'undefined') {
        return;
    }

    const feeCanvas =
        document.getElementById('dashboardFeeChart');

    if (feeCanvas) {
        const context = feeCanvas.getContext('2d');

        const gradient =
            context.createLinearGradient(0, 0, 0, 245);

        gradient.addColorStop(
            0,
            'rgba(101, 80, 223, .32)'
        );

        gradient.addColorStop(
            1,
            'rgba(101, 80, 223, 0)'
        );

        new Chart(feeCanvas, {
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
                        42,
                        68,
                        51,
                        82,
                        69,
                        94,
                        83,
                        102,
                        91,
                        108,
                        118,
                        132
                    ],

                    backgroundColor: '#6550df',
                    borderRadius: 6,
                    borderSkipped: false,
                    maxBarThickness: 28
                }]
            },

            options: {
                responsive: true,
                maintainAspectRatio: false,

                plugins: {
                    legend: {
                        display: false
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
                                return value + 'L';
                            }
                        }
                    }
                }
            }
        });
    }

    const attendanceCanvas =
        document.getElementById('dashboardAttendanceChart');

    if (attendanceCanvas) {
        new Chart(attendanceCanvas, {
            type: 'doughnut',

            data: {
                labels: [
                    'Present',
                    'Leave',
                    'Absent'
                ],

                datasets: [{
                    data: [
                        2178,
                        93,
                        284
                    ],

                    backgroundColor: [
                        '#2caf6c',
                        '#ffad1f',
                        '#f04468'
                    ],

                    borderWidth: 0,
                    hoverOffset: 5
                }]
            },

            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',

                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }
});
</script>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
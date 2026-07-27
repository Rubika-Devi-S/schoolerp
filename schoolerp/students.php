<?php

declare(strict_types=1);

$pageTitle = 'Students Management';
$pageKey   = 'students';

$rows = [
    [
        'admission_no' => 'BPS/24-25/1001',
        'student_name' => 'Aarav Sharma',
        'class'        => '6',
        'section'      => 'A',
        'parent_name'  => 'Rajesh Sharma',
        'mobile'       => '98765 43210',
        'status'       => 'Active',
    ],
    [
        'admission_no' => 'BPS/24-25/1002',
        'student_name' => 'Diya Patel',
        'class'        => '6',
        'section'      => 'B',
        'parent_name'  => 'Vijay Patel',
        'mobile'       => '98765 43211',
        'status'       => 'Active',
    ],
    [
        'admission_no' => 'BPS/24-25/1003',
        'student_name' => 'Reyansh Verma',
        'class'        => '7',
        'section'      => 'A',
        'parent_name'  => 'Amit Verma',
        'mobile'       => '98765 43212',
        'status'       => 'Active',
    ],
    [
        'admission_no' => 'BPS/24-25/1004',
        'student_name' => 'Myra Singh',
        'class'        => '7',
        'section'      => 'B',
        'parent_name'  => 'Harpreet Singh',
        'mobile'       => '98765 43213',
        'status'       => 'Active',
    ],
    [
        'admission_no' => 'BPS/24-25/1005',
        'student_name' => 'Kabir Joshi',
        'class'        => '8',
        'section'      => 'A',
        'parent_name'  => 'Pankaj Joshi',
        'mobile'       => '98765 43214',
        'status'       => 'Active',
    ],
    [
        'admission_no' => 'BPS/24-25/1006',
        'student_name' => 'Ananya Gupta',
        'class'        => '8',
        'section'      => 'B',
        'parent_name'  => 'Sandeep Gupta',
        'mobile'       => '98765 43215',
        'status'       => 'Active',
    ],
    [
        'admission_no' => 'BPS/24-25/1007',
        'student_name' => 'Vivaan Mehta',
        'class'        => '9',
        'section'      => 'A',
        'parent_name'  => 'Nitin Mehta',
        'mobile'       => '98765 43216',
        'status'       => 'Active',
    ],
    [
        'admission_no' => 'BPS/24-25/1008',
        'student_name' => 'Ishita Nair',
        'class'        => '9',
        'section'      => 'B',
        'parent_name'  => 'Rohan Nair',
        'mobile'       => '98765 43217',
        'status'       => 'Inactive',
    ],
];

require __DIR__ . '/includes/layout-start.php';

$canView = !function_exists('has_permission')
    || has_permission($pageKey, 'view');

$canCreate = !function_exists('has_permission')
    || has_permission($pageKey, 'create');

$canEdit = !function_exists('has_permission')
    || has_permission($pageKey, 'edit');

$canDelete = !function_exists('has_permission')
    || has_permission($pageKey, 'delete');

$canExport = !function_exists('has_permission')
    || has_permission($pageKey, 'export');

if (!$canView) {
    http_response_code(403);
    ?>
<div class="alert alert-danger">
    You do not have permission to view the Students module.
</div>
<?php
    require __DIR__ . '/includes/layout-end.php';
    exit;
}
?>

<style>
.students-content-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 360px;
    gap: 16px;
    align-items: start;
}

.students-filter-grid {
    display: grid;
    grid-template-columns: minmax(220px, 1.5fr) repeat(4, minmax(130px, 1fr)) auto;
    gap: 12px;
    padding: 14px;
    margin-bottom: 16px;
}

.students-card {
    background: var(--card-bg, #ffffff);
    border: 1px solid var(--border-soft, #e7ebf3);
    border-radius: 13px;
    box-shadow: 0 5px 18px rgba(15, 23, 42, .04);
    overflow: hidden;
}

.students-card-header {
    min-height: 54px;
    padding: 13px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid var(--border-soft, #e7ebf3);
}

.students-card-header h2 {
    margin: 0;
    font-size: 14px;
    font-weight: 800;
}

.students-card-header a {
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
}

.students-table-wrap {
    overflow-x: auto;
}

.students-table {
    width: 100%;
    min-width: 930px;
    border-collapse: collapse;
}

.students-table th,
.students-table td {
    padding: 11px 12px;
    border-bottom: 1px solid var(--border-soft, #e7ebf3);
    font-size: 12px;
    text-align: left;
    vertical-align: middle;
}

.students-table th {
    color: #475569;
    background: #f8fafc;
    font-weight: 800;
}

.student-cell {
    display: flex;
    align-items: center;
    gap: 9px;
    font-weight: 700;
}

.tiny-avatar {
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

.badge-ui {
    display: inline-flex;
    align-items: center;
    padding: 4px 8px;
    border-radius: 30px;
    font-size: 10px;
    font-weight: 800;
}

.badge-success {
    color: #168448;
    background: #e8f8ef;
}

.badge-warning {
    color: #d97706;
    background: #fff2dc;
}

.action-icons {
    display: flex;
    gap: 6px;
}

.action-icons button {
    width: 30px;
    height: 30px;
    display: grid;
    place-items: center;
    border: 1px solid var(--border-soft, #e7ebf3);
    border-radius: 8px;
    color: #475569;
    background: #ffffff;
}

.action-icons button:hover {
    color: var(--brand-1, #6547e8);
    background: #f8fafc;
}

.action-icons button.danger:hover {
    color: #dc2626;
}

.action-icons svg {
    width: 14px;
    height: 14px;
}

.pagination-ui {
    padding: 13px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    color: var(--text-muted, #64748b);
    font-size: 12px;
}

.pagination-pages {
    display: flex;
    gap: 6px;
}

.pagination-pages span {
    min-width: 30px;
    height: 30px;
    padding: 0 8px;
    display: grid;
    place-items: center;
    border: 1px solid var(--border-soft, #e7ebf3);
    border-radius: 7px;
    background: #ffffff;
}

.pagination-pages span.active {
    color: #ffffff;
    border-color: transparent;
    background: linear-gradient(135deg,
            var(--brand-1, #6547e8),
            var(--brand-2, #315ed8));
}

.chart-box {
    height: 280px;
    padding: 18px;
}

.activity-list {
    padding: 4px 16px;
}

.activity-item {
    padding: 12px 0;
    display: flex;
    align-items: center;
    gap: 11px;
    border-bottom: 1px solid var(--border-soft, #e7ebf3);
}

.activity-item:last-child {
    border-bottom: 0;
}

.activity-dot {
    width: 36px;
    height: 36px;
    display: grid;
    place-items: center;
    border-radius: 50%;
    color: var(--brand-1, #6547e8);
    background: #eeeafd;
}

.activity-dot svg {
    width: 17px;
    height: 17px;
}

.activity-item strong,
.activity-item small {
    display: block;
}

.activity-item strong {
    font-size: 12px;
}

.activity-item small {
    margin-top: 3px;
    color: var(--text-muted, #64748b);
    font-size: 10px;
}

@media (max-width: 1199.98px) {
    .students-content-grid {
        grid-template-columns: 1fr;
    }

    .students-content-grid>aside {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }

    .students-content-grid>aside .mt-3 {
        margin-top: 0 !important;
    }

    .students-filter-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 767.98px) {
    .students-filter-grid {
        grid-template-columns: 1fr;
    }

    .students-content-grid>aside {
        grid-template-columns: 1fr;
    }

    .pagination-ui {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>

<div class="page-heading">
    <div>
        <h1 class="page-title">Students Management</h1>

        <p class="page-subtitle">
            Dashboard › Students Management
        </p>
    </div>

    <div class="page-actions">
        <?php if ($canCreate): ?>
        <button type="button" class="btn-ui btn-primary-ui" data-demo-action="Add Student">
            <i data-lucide="plus"></i>
            Add Student
        </button>

        <button type="button" class="btn-ui">
            <i data-lucide="upload"></i>
            Import
        </button>
        <?php endif; ?>

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

    <article class="metric-card metric-green">
        <div class="metric-icon">
            <i data-lucide="user-check"></i>
        </div>

        <div>
            <small>Active Students</small>
            <div class="metric-value">2,148</div>
            <div class="metric-trend">
                87.4% of total students
            </div>
        </div>
    </article>

    <article class="metric-card metric-pink">
        <div class="metric-icon">
            <i data-lucide="user-plus"></i>
        </div>

        <div>
            <small>New Admissions</small>
            <div class="metric-value">156</div>
            <div class="metric-trend">
                ↑ 12.3% from last year
            </div>
        </div>
    </article>

    <article class="metric-card metric-orange">
        <div class="metric-icon">
            <i data-lucide="user-x"></i>
        </div>

        <div>
            <small>Alumni / Inactive</small>
            <div class="metric-value">310</div>
            <div class="metric-trend">
                12.6% of total students
            </div>
        </div>
    </article>
</section>

<section class="students-card students-filter-grid">
    <input type="search" class="form-control" placeholder="Search by name, admission no. or parent...">

    <select class="form-select">
        <option value="">All Classes</option>
        <option value="6">Class 6</option>
        <option value="7">Class 7</option>
        <option value="8">Class 8</option>
        <option value="9">Class 9</option>
    </select>

    <select class="form-select">
        <option value="">All Sections</option>
        <option value="A">Section A</option>
        <option value="B">Section B</option>
    </select>

    <select class="form-select">
        <option value="">All Genders</option>
        <option value="male">Male</option>
        <option value="female">Female</option>
    </select>

    <select class="form-select">
        <option value="">All Status</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
    </select>

    <button type="button" class="btn-ui">
        <i data-lucide="rotate-ccw"></i>
        Reset
    </button>
</section>

<div class="students-content-grid">
    <section class="students-card">
        <div class="students-card-header">
            <h2>Students List (2,458)</h2>

            <a href="students.php">
                View All
            </a>
        </div>

        <div class="students-table-wrap">
            <table class="students-table">
                <thead>
                    <tr>
                        <th>Admission No</th>
                        <th>Student Name</th>
                        <th>Class</th>
                        <th>Section</th>
                        <th>Parent</th>
                        <th>Mobile</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($rows as $row): ?>
                    <tr>
                        <td>
                            <?= e($row['admission_no']) ?>
                        </td>

                        <td>
                            <div class="student-cell">
                                <span class="tiny-avatar">
                                    <?= e(
                                            strtoupper(
                                                substr(
                                                    $row['student_name'],
                                                    0,
                                                    1
                                                )
                                            )
                                        ) ?>
                                </span>

                                <?= e($row['student_name']) ?>
                            </div>
                        </td>

                        <td>
                            <?= e($row['class']) ?>
                        </td>

                        <td>
                            <?= e($row['section']) ?>
                        </td>

                        <td>
                            <?= e($row['parent_name']) ?>
                        </td>

                        <td>
                            <?= e($row['mobile']) ?>
                        </td>

                        <td>
                            <span class="badge-ui <?= $row['status'] === 'Active'
                                        ? 'badge-success'
                                        : 'badge-warning' ?>">
                                <?= e($row['status']) ?>
                            </span>
                        </td>

                        <td>
                            <div class="action-icons">
                                <button type="button" title="View student" aria-label="View student">
                                    <i data-lucide="eye"></i>
                                </button>

                                <?php if ($canEdit): ?>
                                <button type="button" title="Edit student" aria-label="Edit student">
                                    <i data-lucide="pencil"></i>
                                </button>
                                <?php endif; ?>

                                <?php if ($canDelete): ?>
                                <button type="button" class="danger" title="Delete student" aria-label="Delete student">
                                    <i data-lucide="trash-2"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="pagination-ui">
            <span>
                Showing 1 to 8 of 2,458 students
            </span>

            <div class="pagination-pages">
                <span class="active">1</span>
                <span>2</span>
                <span>3</span>
                <span>…</span>
                <span>308</span>
            </div>
        </div>
    </section>

    <aside>
        <section class="students-card">
            <div class="students-card-header">
                <h2>Student Distribution by Class</h2>

                <a href="reports.php">
                    View Report
                </a>
            </div>

            <div class="chart-box">
                <canvas id="studentChart"></canvas>
            </div>
        </section>

        <section class="students-card mt-3">
            <div class="students-card-header">
                <h2>Recent Student Activity</h2>

                <a href="activity-logs.php">
                    View All
                </a>
            </div>

            <div class="activity-list">
                <?php
                $activities = [
                    'New admission added',
                    'Student information updated',
                    'Document uploaded',
                    'Status changed to inactive',
                ];
                ?>

                <?php foreach ($activities as $activity): ?>
                <div class="activity-item">
                    <div class="activity-dot">
                        <i data-lucide="activity"></i>
                    </div>

                    <div>
                        <strong>
                            <?= e($activity) ?>
                        </strong>

                        <small>
                            Today, recent activity
                        </small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
    </aside>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const chartElement = document.getElementById('studentChart');

    if (!chartElement || typeof window.Chart === 'undefined') {
        return;
    }

    new Chart(chartElement, {
        type: 'doughnut',

        data: {
            labels: [
                'Class 6',
                'Class 7',
                'Class 8',
                'Class 9',
                'Class 10'
            ],

            datasets: [{
                label: 'Students',

                data: [
                    512,
                    498,
                    476,
                    492,
                    480
                ],

                backgroundColor: [
                    '#4f8ef7',
                    '#31b56a',
                    '#ff9f1c',
                    '#f04468',
                    '#a65bd4'
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
                        padding: 15
                    }
                }
            }
        }
    });
});
</script>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
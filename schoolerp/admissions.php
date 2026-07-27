<?php

declare(strict_types=1);

$pageTitle = 'Admissions';
$pageKey   = 'admissions';

$applications = [
    [
        'application_no' => 'APP-2024-2501',
        'student_name'   => 'Aaradhya Singh',
        'applied_class'  => 'Class 1',
        'parent_name'    => 'Rajeev Singh',
        'mobile'         => '98765 43210',
        'submitted_date' => '15 May 2024',
        'status'         => 'Under Review',
    ],
    [
        'application_no' => 'APP-2024-2487',
        'student_name'   => 'Vihaan Mehta',
        'applied_class'  => 'Class 3',
        'parent_name'    => 'Amit Mehta',
        'mobile'         => '98765 12345',
        'submitted_date' => '14 May 2024',
        'status'         => 'Approved',
    ],
    [
        'application_no' => 'APP-2024-2475',
        'student_name'   => 'Myra Patel',
        'applied_class'  => 'Class 5',
        'parent_name'    => 'Pankaj Patel',
        'mobile'         => '98201 23456',
        'submitted_date' => '13 May 2024',
        'status'         => 'Under Review',
    ],
    [
        'application_no' => 'APP-2024-2461',
        'student_name'   => 'Kabir Verma',
        'applied_class'  => 'Class 2',
        'parent_name'    => 'Sandeep Verma',
        'mobile'         => '98123 45678',
        'submitted_date' => '12 May 2024',
        'status'         => 'Approved',
    ],
    [
        'application_no' => 'APP-2024-2449',
        'student_name'   => 'Ananya Gupta',
        'applied_class'  => 'Class 6',
        'parent_name'    => 'Vivek Gupta',
        'mobile'         => '98712 34567',
        'submitted_date' => '11 May 2024',
        'status'         => 'Pending Payment',
    ],
];

$recentApplications = [
    [
        'name'   => 'Ishaan Kapoor',
        'class'  => 'Class 4',
        'date'   => '15 May 2024',
        'status' => 'Under Review',
    ],
    [
        'name'   => 'Sara Khan',
        'class'  => 'Class 1',
        'date'   => '15 May 2024',
        'status' => 'Under Review',
    ],
    [
        'name'   => 'Atharv Joshi',
        'class'  => 'Class 3',
        'date'   => '14 May 2024',
        'status' => 'Approved',
    ],
    [
        'name'   => 'Diya Rathi',
        'class'  => 'Class 2',
        'date'   => '13 May 2024',
        'status' => 'Under Review',
    ],
];

$documents = [
    [
        'document' => 'Birth Certificate',
        'required' => 236,
        'verified' => 162,
        'pending'  => 74,
    ],
    [
        'document' => 'Previous School Report',
        'required' => 168,
        'verified' => 96,
        'pending'  => 72,
    ],
    [
        'document' => 'Aadhaar Card',
        'required' => 236,
        'verified' => 180,
        'pending'  => 56,
    ],
    [
        'document' => 'Passport Size Photograph',
        'required' => 236,
        'verified' => 210,
        'pending'  => 26,
    ],
    [
        'document' => 'Address Proof',
        'required' => 236,
        'verified' => 145,
        'pending'  => 91,
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
    You do not have permission to view the Admissions module.
</div>
<?php
    require __DIR__ . '/includes/layout-end.php';
    exit;
}

/**
 * Return the CSS badge class for an admission status.
 */
function admissionBadgeClass(string $status): string
{
    return match ($status) {
        'Approved'        => 'admission-badge-success',
        'Pending Payment' => 'admission-badge-warning',
        'Rejected',
        'On Hold'         => 'admission-badge-danger',
        default           => 'admission-badge-info',
    };
}
?>

<style>
.admissions-main-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 355px;
    gap: 16px;
    align-items: start;
}

.admissions-lower-grid {
    display: grid;
    grid-template-columns: .85fr 1.35fr;
    gap: 16px;
    margin-top: 16px;
}

.admission-card {
    overflow: hidden;
    background: var(--card-bg, #ffffff);
    border: 1px solid var(--border-soft, #e7ebf3);
    border-radius: 13px;
    box-shadow: 0 5px 18px rgba(15, 23, 42, .04);
}

.admission-card-header {
    min-height: 54px;
    padding: 13px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    border-bottom: 1px solid var(--border-soft, #e7ebf3);
}

.admission-card-header h2 {
    margin: 0;
    font-size: 14px;
    font-weight: 800;
}

.admission-card-header a {
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
}

.application-toolbar {
    display: flex;
    align-items: center;
    gap: 8px;
}

.application-toolbar .form-select {
    min-width: 130px;
    font-size: 12px;
}

.admission-table-wrap {
    overflow-x: auto;
}

.admission-table {
    width: 100%;
    min-width: 960px;
    border-collapse: collapse;
}

.admission-table th,
.admission-table td {
    padding: 11px 12px;
    border-bottom: 1px solid var(--border-soft, #e7ebf3);
    font-size: 12px;
    text-align: left;
    vertical-align: middle;
}

.admission-table th {
    color: #475569;
    background: #f8fafc;
    font-weight: 800;
}

.admission-student {
    display: flex;
    align-items: center;
    gap: 9px;
    font-weight: 700;
}

.admission-avatar {
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

.admission-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 8px;
    border-radius: 30px;
    font-size: 10px;
    font-weight: 800;
    white-space: nowrap;
}

.admission-badge-success {
    color: #168448;
    background: #e8f8ef;
}

.admission-badge-info {
    color: #3164ce;
    background: #e8f1ff;
}

.admission-badge-warning {
    color: #d97706;
    background: #fff2dc;
}

.admission-badge-danger {
    color: #dc2626;
    background: #feecec;
}

.admission-review-btn {
    min-height: 30px;
    padding: 4px 10px;
    border: 1px solid #d9ddf7;
    border-radius: 7px;
    color: var(--brand-1, #6547e8);
    background: #ffffff;
    font-size: 11px;
    font-weight: 800;
}

.admission-review-btn:hover {
    color: #ffffff;
    background: var(--brand-1, #6547e8);
}

.application-footer {
    padding: 13px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    color: var(--text-muted, #64748b);
    font-size: 12px;
}

.admission-pagination {
    display: flex;
    gap: 6px;
}

.admission-pagination button {
    min-width: 30px;
    height: 30px;
    padding: 0 8px;
    border: 1px solid var(--border-soft, #e7ebf3);
    border-radius: 7px;
    color: #475569;
    background: #ffffff;
}

.admission-pagination button.active {
    color: #ffffff;
    border-color: transparent;
    background: linear-gradient(135deg,
            var(--brand-1, #6547e8),
            var(--brand-2, #315ed8));
}

.admission-funnel {
    padding: 22px 20px 16px;
}

.funnel-stage {
    height: 56px;
    margin: 0 auto 6px;
    display: grid;
    place-items: center;
    clip-path: polygon(8% 0, 92% 0, 80% 100%, 20% 100%);
    color: #ffffff;
    font-size: 19px;
    font-weight: 800;
}

.funnel-stage:nth-child(1) {
    width: 100%;
    background: linear-gradient(135deg, #7757e7, #5940ce);
}

.funnel-stage:nth-child(2) {
    width: 81%;
    background: linear-gradient(135deg, #4b94f0, #2c68db);
}

.funnel-stage:nth-child(3) {
    width: 61%;
    background: linear-gradient(135deg, #42be7d, #1fa363);
}

.funnel-stage:nth-child(4) {
    width: 41%;
    background: linear-gradient(135deg, #ff5a7c, #e93358);
}

.funnel-legend {
    padding: 4px 18px 18px;
}

.funnel-legend-row {
    padding: 9px 0;
    display: flex;
    align-items: center;
    gap: 9px;
    border-bottom: 1px solid var(--border-soft, #e7ebf3);
    font-size: 12px;
}

.funnel-legend-row:last-child {
    border-bottom: 0;
}

.funnel-color {
    width: 9px;
    height: 9px;
    flex: 0 0 auto;
    border-radius: 50%;
}

.funnel-legend-row strong {
    margin-left: auto;
}

.recent-application-list {
    padding: 3px 16px;
}

.recent-application {
    padding: 11px 0;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto auto;
    align-items: center;
    gap: 12px;
    border-bottom: 1px solid var(--border-soft, #e7ebf3);
}

.recent-application:last-child {
    border-bottom: 0;
}

.recent-student {
    display: flex;
    align-items: center;
    gap: 9px;
    min-width: 0;
}

.recent-student strong,
.recent-student small {
    display: block;
}

.recent-student strong {
    overflow: hidden;
    font-size: 12px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.recent-student small,
.recent-date {
    color: var(--text-muted, #64748b);
    font-size: 10px;
}

.document-table-wrap {
    overflow-x: auto;
}

.document-table {
    width: 100%;
    min-width: 660px;
    border-collapse: collapse;
}

.document-table th,
.document-table td {
    padding: 11px 12px;
    border-bottom: 1px solid var(--border-soft, #e7ebf3);
    font-size: 12px;
    text-align: left;
}

.document-table th {
    color: #475569;
    background: #f8fafc;
    font-weight: 800;
}

.document-verified {
    color: #159152;
    font-weight: 800;
}

.document-pending {
    color: #dc7a08;
    font-weight: 800;
}

.document-action {
    padding: 5px 9px;
    border: 1px solid #d9ddf7;
    border-radius: 7px;
    color: var(--brand-1, #6547e8);
    background: #ffffff;
    font-size: 10px;
    font-weight: 800;
    white-space: nowrap;
}

@media (max-width: 1199.98px) {
    .admissions-main-grid {
        grid-template-columns: minmax(0, 1fr) 310px;
    }

    .admissions-lower-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 991.98px) {
    .admissions-main-grid {
        grid-template-columns: 1fr;
    }

    .admissions-main-grid>aside {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }

    .admissions-main-grid>aside .mt-3 {
        margin-top: 0 !important;
    }
}

@media (max-width: 767.98px) {
    .admissions-main-grid>aside {
        grid-template-columns: 1fr;
    }

    .application-toolbar {
        display: none;
    }

    .application-footer {
        align-items: flex-start;
        flex-direction: column;
    }

    .recent-application {
        grid-template-columns: minmax(0, 1fr) auto;
    }

    .recent-date {
        display: none;
    }
}
</style>

<div class="page-heading">
    <div>
        <h1 class="page-title">
            Admissions
        </h1>

        <p class="page-subtitle">
            Manage and track all admission applications
        </p>
    </div>

    <div class="page-actions">
        <?php if ($canExport): ?>
        <button type="button" class="btn-ui">
            <i data-lucide="download"></i>
            Export
        </button>
        <?php endif; ?>

        <?php if ($canCreate): ?>
        <button type="button" class="btn-ui btn-primary-ui" data-demo-action="Add Application">
            <i data-lucide="plus"></i>
            Add Application
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
            <small>New Applications</small>
            <div class="metric-value">236</div>
            <div class="metric-trend">
                ↑ 18.4% from last month
            </div>
        </div>
    </article>

    <article class="metric-card metric-blue">
        <div class="metric-icon">
            <i data-lucide="circle-check-big"></i>
        </div>

        <div>
            <small>Approved Admissions</small>
            <div class="metric-value">142</div>
            <div class="metric-trend">
                ↑ 12.7% from last month
            </div>
        </div>
    </article>

    <article class="metric-card metric-orange">
        <div class="metric-icon">
            <i data-lucide="clock-3"></i>
        </div>

        <div>
            <small>Pending Review</small>
            <div class="metric-value">78</div>
            <div class="metric-trend">
                ↓ 5.3% from last month
            </div>
        </div>
    </article>

    <article class="metric-card metric-pink">
        <div class="metric-icon">
            <i data-lucide="circle-x"></i>
        </div>

        <div>
            <small>Rejected / On Hold</small>
            <div class="metric-value">16</div>
            <div class="metric-trend">
                ↓ 3.1% from last month
            </div>
        </div>
    </article>
</section>

<div class="admissions-main-grid">
    <section class="admission-card">
        <div class="admission-card-header">
            <h2>
                All Applications
            </h2>

            <div class="application-toolbar">
                <select class="form-select form-select-sm">
                    <option value="">All Classes</option>
                    <option value="1">Class 1</option>
                    <option value="2">Class 2</option>
                    <option value="3">Class 3</option>
                    <option value="4">Class 4</option>
                    <option value="5">Class 5</option>
                    <option value="6">Class 6</option>
                </select>

                <button type="button" class="btn-ui">
                    <i data-lucide="filter"></i>
                    Filter
                </button>
            </div>
        </div>

        <div class="admission-table-wrap">
            <table class="admission-table">
                <thead>
                    <tr>
                        <th>Application No</th>
                        <th>Student</th>
                        <th>Applied Class</th>
                        <th>Parent</th>
                        <th>Mobile</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($applications as $application): ?>
                    <tr>
                        <td>
                            <?= e($application['application_no']) ?>
                        </td>

                        <td>
                            <div class="admission-student">
                                <span class="admission-avatar">
                                    <?= e(
                                            strtoupper(
                                                substr(
                                                    $application['student_name'],
                                                    0,
                                                    1
                                                )
                                            )
                                        ) ?>
                                </span>

                                <?= e($application['student_name']) ?>
                            </div>
                        </td>

                        <td>
                            <?= e($application['applied_class']) ?>
                        </td>

                        <td>
                            <?= e($application['parent_name']) ?>
                        </td>

                        <td>
                            <?= e($application['mobile']) ?>
                        </td>

                        <td>
                            <?= e($application['submitted_date']) ?>
                        </td>

                        <td>
                            <span class="admission-badge <?= e(
                                        admissionBadgeClass(
                                            $application['status']
                                        )
                                    ) ?>">
                                <?= e($application['status']) ?>
                            </span>
                        </td>

                        <td>
                            <?php if ($canEdit || $canApprove): ?>
                            <button type="button" class="admission-review-btn">
                                Review
                            </button>
                            <?php else: ?>
                            <button type="button" class="admission-review-btn">
                                View
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="application-footer">
            <span>
                Showing 1 to 5 of 25 applications
            </span>

            <div class="admission-pagination">
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

    <aside>
        <section class="admission-card">
            <div class="admission-card-header">
                <h2>
                    Admission Funnel
                </h2>

                <a href="reports.php">
                    View Report
                </a>
            </div>

            <div class="admission-funnel">
                <div class="funnel-stage">
                    236
                </div>

                <div class="funnel-stage">
                    78
                </div>

                <div class="funnel-stage">
                    142
                </div>

                <div class="funnel-stage">
                    16
                </div>
            </div>

            <div class="funnel-legend">
                <div class="funnel-legend-row">
                    <span class="funnel-color" style="background:#7050df"></span>

                    Application Received

                    <strong>
                        236
                    </strong>
                </div>

                <div class="funnel-legend-row">
                    <span class="funnel-color" style="background:#3d83ec"></span>

                    Under Review

                    <strong>
                        78
                    </strong>
                </div>

                <div class="funnel-legend-row">
                    <span class="funnel-color" style="background:#2caf6c"></span>

                    Approved

                    <strong>
                        142
                    </strong>
                </div>

                <div class="funnel-legend-row">
                    <span class="funnel-color" style="background:#f04468"></span>

                    Rejected / On Hold

                    <strong>
                        16
                    </strong>
                </div>
            </div>
        </section>
    </aside>
</div>

<div class="admissions-lower-grid">
    <section class="admission-card">
        <div class="admission-card-header">
            <h2>
                Recent Applications
            </h2>

            <a href="#">
                View All
            </a>
        </div>

        <div class="recent-application-list">
            <?php foreach ($recentApplications as $recent): ?>
            <div class="recent-application">
                <div class="recent-student">
                    <span class="admission-avatar">
                        <?= e(
                                strtoupper(
                                    substr(
                                        $recent['name'],
                                        0,
                                        1
                                    )
                                )
                            ) ?>
                    </span>

                    <div>
                        <strong>
                            <?= e($recent['name']) ?>
                        </strong>

                        <small>
                            <?= e($recent['class']) ?>
                        </small>
                    </div>
                </div>

                <span class="recent-date">
                    <?= e($recent['date']) ?>
                </span>

                <span class="admission-badge <?= e(
                            admissionBadgeClass(
                                $recent['status']
                            )
                        ) ?>">
                    <?= e($recent['status']) ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="admission-card">
        <div class="admission-card-header">
            <h2>
                Document Verification Checklist
            </h2>

            <a href="#">
                View Guidelines
            </a>
        </div>

        <div class="document-table-wrap">
            <table class="document-table">
                <thead>
                    <tr>
                        <th>Document</th>
                        <th>Required</th>
                        <th>Verified</th>
                        <th>Pending</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($documents as $document): ?>
                    <tr>
                        <td>
                            <?= e($document['document']) ?>
                        </td>

                        <td>
                            <?= (int)$document['required'] ?>
                        </td>

                        <td class="document-verified">
                            <?= (int)$document['verified'] ?>
                        </td>

                        <td class="document-pending">
                            <?= (int)$document['pending'] ?>
                        </td>

                        <td>
                            <button type="button" class="document-action">
                                Verify Documents
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
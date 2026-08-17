<?php
declare(strict_types=1);

/*
 * Super Admin Audit Logs
 * Build: 2026-08-15-super-admin-audit-logs-4-cards-v4
 * Route already registered in current database:
 * super-admin/activity-logs.php
 */

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/bootstrap.php';
require_once $projectRoot . '/includes/audit-log.php';

if (function_exists('require_login')) {
    require_login();
}

$pageTitle = 'Audit Logs';
$pageKey = 'platform_activity_logs';
$sidebarFile = __DIR__ . '/sidebar.php';

$currentUser = function_exists('current_user')
    ? current_user()
    : [];
$currentUser = is_array($currentUser) ? $currentUser : [];

$roleId = (int)(
    $currentUser['role_id']
    ?? $_SESSION['role_id']
    ?? 0
);

$roleKey = strtolower(trim((string)(
    $currentUser['role_key']
    ?? $_SESSION['role_key']
    ?? ''
)));

if ($roleKey === ''
    && isset($pdo)
    && $pdo instanceof PDO
    && $roleId > 0) {
    try {
        $roleStatement = $pdo->prepare(
            "SELECT role_key, role_scope
             FROM roles
             WHERE id = :role_id
               AND status = 'active'
             LIMIT 1"
        );
        $roleStatement->execute(['role_id' => $roleId]);
        $roleRow = $roleStatement->fetch(PDO::FETCH_ASSOC) ?: [];
        $roleKey = strtolower(trim((string)($roleRow['role_key'] ?? '')));
    } catch (Throwable $exception) {
        error_log(
            'Super Admin Audit Logs role lookup failed: '
            . $exception->getMessage()
        );
    }
}

$isSuperAdmin = $roleId === 1
    || in_array(
        $roleKey,
        ['super_admin', 'super-administrator', 'super_administrator'],
        true
    );

if (!$isSuperAdmin) {
    http_response_code(403);
    exit('Only a Super Administrator can access Audit Logs.');
}

/*
 * Four-card dashboard summary.
 *
 * Kept inside this existing Super Admin page so no extra API file is needed.
 * This endpoint runs under /super-admin/activity-logs.php and therefore keeps
 * the existing Support Access protection for genuine School APIs unchanged.
 */
if (
    isset($_GET['audit_cards'])
    && (string)$_GET['audit_cards'] === '1'
) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    try {
        if (!isset($pdo) || !($pdo instanceof PDO)) {
            throw new RuntimeException('Database connection is unavailable.');
        }

        $where = ['1 = 1'];
        $params = [];

        $schoolId = max(0, (int)($_GET['school_id'] ?? 0));
        if ($schoolId > 0) {
            $where[] = 'al.tenant_id = :school_id';
            $params['school_id'] = $schoolId;
        }

        $branchId = max(0, (int)($_GET['branch_id'] ?? 0));
        if ($branchId > 0) {
            $where[] = 'COALESCE(al.branch_id, u.default_branch_id) = :branch_id';
            $params['branch_id'] = $branchId;
        }

        $academicYearId = max(
            0,
            (int)($_GET['academic_year_id'] ?? 0)
        );

        if ($academicYearId > 0) {
            $where[] = "EXISTS (
                SELECT 1
                FROM academic_years ay
                WHERE ay.id = :academic_year_id
                  AND ay.tenant_id = al.tenant_id
                  AND (
                    CAST(
                        NULLIF(
                            JSON_UNQUOTE(
                                JSON_EXTRACT(
                                    CASE
                                        WHEN JSON_VALID(al.new_values)
                                        THEN al.new_values
                                        ELSE '{}'
                                    END,
                                    '$.academic_year_id'
                                )
                            ),
                            ''
                        ) AS UNSIGNED
                    ) = ay.id
                    OR CAST(
                        NULLIF(
                            JSON_UNQUOTE(
                                JSON_EXTRACT(
                                    CASE
                                        WHEN JSON_VALID(al.old_values)
                                        THEN al.old_values
                                        ELSE '{}'
                                    END,
                                    '$.academic_year_id'
                                )
                            ),
                            ''
                        ) AS UNSIGNED
                    ) = ay.id
                    OR DATE(al.created_at)
                        BETWEEN ay.start_date AND ay.end_date
                  )
            )";

            $params['academic_year_id'] = $academicYearId;
        }

        $userId = max(0, (int)($_GET['user_id'] ?? 0));
        if ($userId > 0) {
            $where[] = 'al.user_id = :user_id';
            $params['user_id'] = $userId;
        }

        $roleFilterId = max(0, (int)($_GET['role_id'] ?? 0));
        if ($roleFilterId > 0) {
            $where[] = 'COALESCE(al.role_id, u.role_id) = :role_id';
            $params['role_id'] = $roleFilterId;
        }

        $moduleFilter = trim((string)($_GET['module'] ?? ''));
        if ($moduleFilter !== '') {
            $where[] = 'al.module_name = :module_name';
            $params['module_name'] = $moduleFilter;
        }

        $exactAction = trim((string)($_GET['action_key'] ?? ''));
        if ($exactAction !== '') {
            $where[] = 'al.action_key = :action_key';
            $params['action_key'] = $exactAction;
        }

        $tableFilter = trim((string)($_GET['table_name'] ?? ''));
        if ($tableFilter !== '') {
            $where[] = 'al.table_name = :table_name';
            $params['table_name'] = $tableFilter;
        }

        $fromDate = trim((string)($_GET['from_date'] ?? ''));
        if (
            $fromDate !== ''
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) === 1
        ) {
            $where[] = 'al.created_at >= :from_date';
            $params['from_date'] = $fromDate . ' 00:00:00';
        }

        $toDate = trim((string)($_GET['to_date'] ?? ''));
        if (
            $toDate !== ''
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate) === 1
        ) {
            $where[] = 'al.created_at <= :to_date';
            $params['to_date'] = $toDate . ' 23:59:59';
        }

        $search = trim(
            (string)(
                $_GET['q']
                ?? $_GET['search']
                ?? ''
            )
        );

        if ($search !== '') {
            $where[] = "(
                t.school_name LIKE :search
                OR t.tenant_code LIKE :search
                OR b.branch_name LIKE :search
                OR b.branch_code LIKE :search
                OR u.name LIKE :search
                OR u.username LIKE :search
                OR u.email LIKE :search
                OR r.role_name LIKE :search
                OR r.role_key LIKE :search
                OR al.module_name LIKE :search
                OR al.action_key LIKE :search
                OR al.table_name LIKE :search
                OR CAST(al.record_id AS CHAR) LIKE :search
                OR al.description LIKE :search
                OR al.ip_address LIKE :search
            )";

            $params['search'] = '%' . $search . '%';
        }

        $actionGroup = strtolower(
            trim(
                (string)(
                    $_GET['action_group']
                    ?? ''
                )
            )
        );

        $actionGroupExpression = "
            CASE
                WHEN LOWER(al.action_key) LIKE '%import%'
                    THEN 'import'
                WHEN LOWER(al.action_key) LIKE '%export%'
                    OR LOWER(al.action_key) LIKE '%download%'
                    THEN 'export'
                WHEN LOWER(al.action_key) LIKE '%print%'
                    OR LOWER(al.action_key) LIKE '%reprint%'
                    THEN 'print'
                WHEN LOWER(al.action_key) LIKE '%delete%'
                    OR LOWER(al.action_key) LIKE '%remove%'
                    OR LOWER(al.action_key) LIKE '%cancel%'
                    THEN 'delete'
                WHEN LOWER(al.action_key) LIKE '%edit%'
                    OR LOWER(al.action_key) LIKE '%update%'
                    OR LOWER(al.action_key) LIKE '%save%'
                    OR LOWER(al.action_key) LIKE '%status%'
                    OR LOWER(al.action_key) LIKE '%approve%'
                    OR LOWER(al.action_key) LIKE '%reject%'
                    OR LOWER(al.action_key) LIKE '%restore%'
                    THEN 'edit'
                WHEN LOWER(al.action_key) LIKE '%create%'
                    OR LOWER(al.action_key) LIKE '%created%'
                    OR LOWER(al.action_key) LIKE '%add%'
                    OR LOWER(al.action_key) LIKE '%insert%'
                    THEN 'create'
                WHEN LOWER(al.action_key) LIKE '%view%'
                    OR LOWER(al.action_key) LIKE '%open%'
                    OR LOWER(al.action_key) LIKE '%read%'
                    OR LOWER(al.action_key) LIKE '%detail%'
                    OR LOWER(al.action_key) = 'list'
                    THEN 'view'
                WHEN LOWER(al.action_key) LIKE '%login%'
                    OR LOWER(al.action_key) LIKE '%logout%'
                    OR LOWER(al.action_key) LIKE '%sign_in%'
                    OR LOWER(al.action_key) LIKE '%sign_out%'
                    THEN 'login'
                ELSE 'other'
            END
        ";

        if (
            in_array(
                $actionGroup,
                [
                    'view',
                    'create',
                    'edit',
                    'delete',
                    'import',
                    'export',
                    'print',
                    'login',
                    'other',
                ],
                true
            )
        ) {
            $where[] = '(' . $actionGroupExpression . ') = :action_group';
            $params['action_group'] = $actionGroup;
        }

        $loginExpression = "(
            LOWER(al.action_key) LIKE '%login%'
            OR LOWER(al.action_key) LIKE '%logout%'
            OR LOWER(al.action_key) LIKE '%sign_in%'
            OR LOWER(al.action_key) LIKE '%sign_out%'
            OR LOWER(al.action_key) LIKE '%signin%'
            OR LOWER(al.action_key) LIKE '%signout%'
            OR LOWER(al.action_key) LIKE '%session%'
            OR LOWER(al.module_name) LIKE '%auth%'
        )";

        /*
         * Suspicious is based only on actions already recorded in activity_logs.
         * It does not invent security events. Common failed/denied/blocked/error
         * action keys and descriptions are grouped here for quick Super Admin review.
         */
        $suspiciousExpression = "(
            LOWER(al.action_key) LIKE '%fail%'
            OR LOWER(al.action_key) LIKE '%failed%'
            OR LOWER(al.action_key) LIKE '%denied%'
            OR LOWER(al.action_key) LIKE '%unauthor%'
            OR LOWER(al.action_key) LIKE '%invalid%'
            OR LOWER(al.action_key) LIKE '%error%'
            OR LOWER(al.action_key) LIKE '%blocked%'
            OR LOWER(al.action_key) LIKE '%suspicious%'
            OR LOWER(al.action_key) LIKE '%security%'
            OR LOWER(COALESCE(al.description, '')) LIKE '%failed%'
            OR LOWER(COALESCE(al.description, '')) LIKE '%access denied%'
            OR LOWER(COALESCE(al.description, '')) LIKE '%unauthorized%'
            OR LOWER(COALESCE(al.description, '')) LIKE '%invalid login%'
            OR LOWER(COALESCE(al.description, '')) LIKE '%blocked%'
            OR LOWER(COALESCE(al.description, '')) LIKE '%suspicious%'
        )";

        $sql = "
            SELECT
                COUNT(*) AS total,
                SUM(
                    DATE(al.created_at) = CURRENT_DATE()
                ) AS today,
                SUM(
                    CASE
                        WHEN $loginExpression THEN 1
                        ELSE 0
                    END
                ) AS login_count,
                SUM(
                    CASE
                        WHEN $suspiciousExpression THEN 1
                        ELSE 0
                    END
                ) AS suspicious_count
            FROM activity_logs al
            LEFT JOIN tenants t
                ON t.id = al.tenant_id
            LEFT JOIN users u
                ON u.id = al.user_id
               AND u.tenant_id = al.tenant_id
            LEFT JOIN branches b
                ON b.id = COALESCE(al.branch_id, u.default_branch_id)
               AND b.tenant_id = al.tenant_id
            LEFT JOIN roles r
                ON r.id = COALESCE(al.role_id, u.role_id)
            WHERE " . implode(' AND ', $where);

        $statement = $pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $statement->bindValue(
                ':' . $key,
                $value,
                is_int($value)
                    ? PDO::PARAM_INT
                    : PDO::PARAM_STR
            );
        }

        $statement->execute();

        $summary = $statement->fetch(PDO::FETCH_ASSOC) ?: [];

        echo json_encode(
            [
                'success' => true,
                'message' => 'Audit dashboard statistics loaded.',
                'data' => [
                    'total' => (int)($summary['total'] ?? 0),
                    'today' => (int)($summary['today'] ?? 0),
                    'login_count' => (int)($summary['login_count'] ?? 0),
                    'suspicious_count' => (int)($summary['suspicious_count'] ?? 0),
                ],
            ],
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_INVALID_UTF8_SUBSTITUTE
        );

        exit;
    } catch (Throwable $exception) {
        error_log(
            'Super Admin Audit card summary: '
            . $exception->getMessage()
        );

        http_response_code(500);

        echo json_encode(
            [
                'success' => false,
                'message' => 'Unable to load Audit dashboard statistics.',
                'data' => [],
            ],
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        );

        exit;
    }
}

/*
 * Internal Super Admin API dispatch.
 *
 * This deliberately runs under the /super-admin/activity-logs.php request
 * instead of a direct /api/... request. It keeps the existing Bootstrap
 * Support Access protection unchanged for real School APIs.
 */
if (
    isset($_GET['audit_api'])
    && (string)$_GET['audit_api'] === '1'
) {
    require $projectRoot . '/api/super-admin-activity-logs.php';
    exit;
}

$baseUrl = defined('BASE_URL')
    ? rtrim((string)BASE_URL, '/') . '/'
    : '../';

/*
 * IMPORTANT:
 * Do not call /api/super-admin-activity-logs.php directly from a platform user.
 * The current Bootstrap treats an unknown /api/*.php route as a School API and
 * correctly requires Support Access.
 *
 * Instead, proxy Audit Logs AJAX through this registered Super Admin page.
 * Bootstrap sees /super-admin/activity-logs.php, validates the platform page,
 * then this file includes the matching API below.
 */
$apiUrl = $baseUrl . 'super-admin/activity-logs.php?audit_api=1';

/*
 * Record direct Audit Logs page access as a View action.
 * This is intentionally one log per page load, not one per AJAX refresh.
 */
if (isset($pdo) && $pdo instanceof PDO) {
    schoolerp_audit_log(
        $pdo,
        'Audit Logs',
        'view',
        'activity_logs',
        null,
        null,
        null,
        'Viewed Super Admin Audit Logs'
    );
}

require $projectRoot . '/includes/layout-start.php';
?>

<style>
.audit-page{--audit-radius:16px}
.audit-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:18px}
.audit-heading .page-title{margin:0 0 4px;font-size:2rem;line-height:1.08;font-weight:900;letter-spacing:-.03em}
.audit-heading .page-subtitle{max-width:760px;color:var(--text-muted,#64748b);font-size:.86rem}
.audit-heading-actions{display:flex;align-items:center;flex-wrap:wrap;gap:10px}
.audit-heading-actions .btn-ui{min-height:44px;padding:0 17px;border-radius:12px;font-weight:800}
.audit-heading-actions .btn-ui i{width:18px;height:18px}

.audit-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:16px}
.audit-stat{position:relative;min-height:138px;padding:18px 20px;overflow:hidden;border:0;border-radius:16px;color:#fff;box-shadow:0 10px 24px rgba(15,23,42,.08);isolation:isolate}
.audit-stat::before,.audit-stat::after{content:"";position:absolute;z-index:-1;border-radius:999px;background:rgba(255,255,255,.10)}
.audit-stat::before{width:128px;height:128px;top:-58px;right:-20px}
.audit-stat::after{width:90px;height:90px;right:8px;bottom:-54px;background:rgba(255,255,255,.07)}
.audit-stat-purple{background:linear-gradient(135deg,#7145ee 0%,#5531d8 100%)}
.audit-stat-orange{background:linear-gradient(135deg,#ffb02e 0%,#ff8a18 100%)}
.audit-stat-green{background:linear-gradient(135deg,#49c98d 0%,#1fb977 100%)}
.audit-stat-red{background:linear-gradient(135deg,#ff5b80 0%,#f23665 100%)}
.audit-stat-inner{position:relative;z-index:2;display:flex;align-items:center;gap:16px;min-height:100%}
.audit-stat-icon{width:58px;height:58px;flex:0 0 58px;display:grid;place-items:center;border-radius:50%;border:1px solid rgba(255,255,255,.16);background:rgba(255,255,255,.16)}
.audit-stat-icon i{width:28px;height:28px;stroke-width:1.8}
.audit-stat-copy{min-width:0}
.audit-stat-title{display:block;margin-bottom:2px;font-size:.73rem;font-weight:800;opacity:.96}
.audit-stat strong{display:block;margin:0;color:#fff;font-size:2.25rem;line-height:1;font-weight:900}
.audit-stat-note{display:block;margin-top:8px;font-size:.68rem;font-weight:700;opacity:.92}

.audit-info-strip{display:flex;align-items:flex-start;gap:9px;margin-bottom:16px;padding:12px 15px;border:1px solid #d7e8ff;border-radius:12px;background:linear-gradient(90deg,#eef6ff 0%,#f8fbff 100%);color:#1d4ed8;font-size:.72rem;line-height:1.5}
.audit-info-strip i{width:16px;height:16px;margin-top:1px;flex:0 0 16px}
.audit-info-strip strong{font-weight:850}

.audit-filter-card,.audit-history-card{overflow:hidden;border:1px solid var(--border-soft,#e5e9f2);border-radius:16px;background:var(--card-bg,#fff);box-shadow:0 7px 22px rgba(15,23,42,.04)}
.audit-filter-card .ui-card-header,.audit-history-card .ui-card-header{padding:16px 18px;border-bottom:1px solid var(--border-soft,#e5e9f2)}
.audit-filter-card .ui-card-body{padding:16px 18px 18px}
.audit-filters{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:13px}
.audit-filter-wide{grid-column:span 2}
.audit-filter label{display:block;margin-bottom:6px;color:var(--text-muted,#64748b);font-size:.66rem;font-weight:850;text-transform:uppercase;letter-spacing:.04em}
.audit-filter .form-control,.audit-filter .form-select{min-height:42px;border-radius:10px}
.audit-filter-actions{display:flex;align-items:flex-end;gap:8px}
.audit-filter-actions>*{flex:1;min-height:42px}

.audit-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.audit-results{color:var(--text-muted,#64748b);font-size:.73rem}
.audit-table{margin:0}
.audit-table thead th{padding:12px 13px;white-space:nowrap;border-bottom:1px solid var(--border-soft,#e5e9f2);background:color-mix(in srgb,var(--body-bg,#f8fafc) 78%,#fff 22%);color:var(--text-muted,#64748b);font-size:.66rem;font-weight:850;text-transform:uppercase;letter-spacing:.035em}
.audit-table tbody td{padding:13px;vertical-align:top;border-color:var(--border-soft,#eef1f6);font-size:.77rem}
.audit-table tbody tr:hover{background:color-mix(in srgb,var(--brand-1,#6747e8) 3%,transparent)}
.audit-school strong,.audit-user strong{display:block;color:var(--text-main,#0f172a);font-weight:800}
.audit-school small,.audit-user small,.audit-cell-meta{display:block;margin-top:2px;color:var(--text-muted,#64748b);font-size:.65rem}
.audit-action{display:inline-flex;align-items:center;gap:5px;padding:5px 9px;border:1px solid var(--border-soft,#e5e9f2);border-radius:999px;background:var(--body-bg,#f8fafc);color:var(--text-main,#0f172a);font-size:.64rem;font-weight:850;white-space:nowrap}
.audit-action[data-group="create"]{color:#15803d;background:#f0fdf4;border-color:#bbf7d0}
.audit-action[data-group="edit"]{color:#b45309;background:#fffbeb;border-color:#fde68a}
.audit-action[data-group="delete"]{color:#b91c1c;background:#fff1f2;border-color:#fecdd3}
.audit-action[data-group="import"]{color:#7c3aed;background:#f5f3ff;border-color:#ddd6fe}
.audit-action[data-group="export"]{color:#0369a1;background:#f0f9ff;border-color:#bae6fd}
.audit-action[data-group="view"]{color:#1d4ed8;background:#eff6ff;border-color:#bfdbfe}
.audit-action[data-group="print"]{color:#475569;background:#f8fafc;border-color:#cbd5e1}
.audit-description{min-width:210px;max-width:360px;white-space:normal;line-height:1.45}
.audit-view-button{width:36px;height:36px;min-width:36px;padding:0;display:grid;place-items:center;border-radius:10px}
.audit-view-button i{width:16px;height:16px}
.audit-pagination{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:14px 18px 16px;border-top:1px solid var(--border-soft,#e5e9f2)}
.audit-pagination-buttons{display:flex;gap:6px;flex-wrap:wrap}
.audit-pagination-buttons .btn-ui{min-width:36px;height:36px;padding:0 10px;border-radius:9px}

.audit-detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.audit-detail-item{padding:11px 12px;border:1px solid var(--border-soft,#e5e9f2);border-radius:11px;background:var(--body-bg,#f8fafc)}
.audit-detail-item.full{grid-column:1/-1}
.audit-detail-item small{display:block;margin-bottom:4px;color:var(--text-muted,#64748b);font-size:.64rem;font-weight:800;text-transform:uppercase}
.audit-detail-item strong,.audit-detail-item span{color:var(--text-main,#0f172a);font-size:.78rem;overflow-wrap:anywhere}
.audit-json-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.audit-json{min-height:120px;max-height:330px;margin:0;padding:12px;overflow:auto;border:1px solid var(--border-soft,#e5e9f2);border-radius:11px;background:#0f172a;color:#e2e8f0;font-size:.7rem;white-space:pre-wrap;word-break:break-word}
.audit-loading,.audit-empty{padding:46px 20px!important;text-align:center;color:var(--text-muted,#64748b)}

@media(max-width:1199.98px){.audit-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.audit-filters{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:991.98px){.audit-filters{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:767.98px){
.audit-heading{flex-direction:column}
.audit-heading-actions{width:100%}
.audit-heading-actions .btn-ui{flex:1}
.audit-stats,.audit-filters,.audit-detail-grid,.audit-json-grid{grid-template-columns:1fr}
.audit-filter-wide,.audit-detail-item.full{grid-column:auto}
.audit-pagination{align-items:stretch;flex-direction:column}
}
@media print{
.sidebar,#sidebar,.topbar,#topbar,.audit-heading-actions,.audit-filter-card,.audit-info-strip,.audit-pagination,.audit-view-button{display:none!important}
.audit-page{padding:0!important}
.audit-stats{grid-template-columns:repeat(4,1fr)!important}
.audit-stat{color:#000!important;background:#fff!important;border:1px solid #ddd!important;box-shadow:none!important}
.audit-stat strong{color:#000!important}
}
</style>

<div class="audit-page">
    <div class="audit-heading">
        <div>
            <h1 class="page-title mb-1">Super Admin Audit Logs</h1>
            <p class="page-subtitle mb-0">
                Check complete ERP activity school-wise, branch-wise,
                academic-year-wise, user-wise and action-wise.
            </p>
        </div>

        <div class="audit-heading-actions">
            <button class="btn-ui" type="button" id="refreshAuditLogs">
                <i data-lucide="refresh-cw"></i>
                Refresh
            </button>

            <button class="btn-ui" type="button" id="printAuditLogs">
                <i data-lucide="printer"></i>
                Print
            </button>

            <button class="btn-ui btn-primary-ui" type="button" id="exportAuditLogs">
                <i data-lucide="download"></i>
                Export CSV
            </button>
        </div>
    </div>

    <div class="audit-stats">
        <div class="audit-stat audit-stat-purple">
            <div class="audit-stat-inner">
                <div class="audit-stat-icon">
                    <i data-lucide="activity"></i>
                </div>
                <div class="audit-stat-copy">
                    <span class="audit-stat-title">Total Activities</span>
                    <strong id="statTotal">0</strong>
                    <span class="audit-stat-note">
                        All recorded ERP activities
                    </span>
                </div>
            </div>
        </div>

        <div class="audit-stat audit-stat-orange">
            <div class="audit-stat-inner">
                <div class="audit-stat-icon">
                    <i data-lucide="calendar-check-2"></i>
                </div>
                <div class="audit-stat-copy">
                    <span class="audit-stat-title">Today's Activities</span>
                    <strong id="statToday">0</strong>
                    <span class="audit-stat-note">
                        Activities recorded today
                    </span>
                </div>
            </div>
        </div>

        <div class="audit-stat audit-stat-green">
            <div class="audit-stat-inner">
                <div class="audit-stat-icon">
                    <i data-lucide="log-in"></i>
                </div>
                <div class="audit-stat-copy">
                    <span class="audit-stat-title">Login Activities</span>
                    <strong id="statLogin">0</strong>
                    <span class="audit-stat-note">
                        Login, logout and session activity
                    </span>
                </div>
            </div>
        </div>

        <div class="audit-stat audit-stat-red">
            <div class="audit-stat-inner">
                <div class="audit-stat-icon">
                    <i data-lucide="shield-alert"></i>
                </div>
                <div class="audit-stat-copy">
                    <span class="audit-stat-title">
                        Failed / Suspicious Activities
                    </span>
                    <strong id="statSuspicious">0</strong>
                    <span class="audit-stat-note">
                        Failed, denied or blocked logged actions
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="audit-info-strip">
        <i data-lucide="shield-check"></i>
        <div>
            <strong>Audit Trail:</strong>
            View-only Super Admin history. Use the filters below to check
            school, branch, academic year, user, role, module and action activity.
        </div>
    </div>

    <article class="ui-card mb-3 audit-filter-card">
        <div class="ui-card-header">
            <h2 class="ui-card-title">Audit Filters</h2>
            <i data-lucide="list-filter"></i>
        </div>

        <div class="ui-card-body">
            <div class="audit-filters">
                <div class="audit-filter audit-filter-wide">
                    <label for="auditSearch">Search</label>
                    <input
                        id="auditSearch"
                        class="form-control"
                        type="search"
                        placeholder="School, branch, user, module, action, table, record or IP"
                    >
                </div>

                <div class="audit-filter">
                    <label for="schoolFilter">School</label>
                    <select id="schoolFilter" class="form-select">
                        <option value="">All Schools</option>
                    </select>
                </div>

                <div class="audit-filter">
                    <label for="branchFilter">Branch</label>
                    <select id="branchFilter" class="form-select">
                        <option value="">All Branches</option>
                    </select>
                </div>

                <div class="audit-filter">
                    <label for="yearFilter">Academic Year</label>
                    <select id="yearFilter" class="form-select">
                        <option value="">All Academic Years</option>
                    </select>
                </div>

                <div class="audit-filter">
                    <label for="userFilter">User</label>
                    <select id="userFilter" class="form-select">
                        <option value="">All Users</option>
                    </select>
                </div>

                <div class="audit-filter">
                    <label for="roleFilter">Role</label>
                    <select id="roleFilter" class="form-select">
                        <option value="">All Roles</option>
                    </select>
                </div>

                <div class="audit-filter">
                    <label for="moduleFilter">Module</label>
                    <select id="moduleFilter" class="form-select">
                        <option value="">All Modules</option>
                    </select>
                </div>

                <div class="audit-filter">
                    <label for="actionGroupFilter">Action Type</label>
                    <select id="actionGroupFilter" class="form-select">
                        <option value="">All Action Types</option>
                        <option value="view">View / Open</option>
                        <option value="create">Create / Add</option>
                        <option value="edit">Edit / Update / Status</option>
                        <option value="delete">Delete / Remove / Cancel</option>
                        <option value="import">Import</option>
                        <option value="export">Export / Download</option>
                        <option value="print">Print / Reprint</option>
                        <option value="login">Login / Logout</option>
                        <option value="other">Other Actions</option>
                    </select>
                </div>

                <div class="audit-filter">
                    <label for="actionFilter">Exact Action</label>
                    <select id="actionFilter" class="form-select">
                        <option value="">All Actions</option>
                    </select>
                </div>

                <div class="audit-filter">
                    <label for="tableFilter">Database Table</label>
                    <select id="tableFilter" class="form-select">
                        <option value="">All Tables</option>
                    </select>
                </div>

                <div class="audit-filter">
                    <label for="fromDate">From Date</label>
                    <input id="fromDate" class="form-control" type="date">
                </div>

                <div class="audit-filter">
                    <label for="toDate">To Date</label>
                    <input id="toDate" class="form-control" type="date">
                </div>

                <div class="audit-filter">
                    <label for="perPage">Rows</label>
                    <select id="perPage" class="form-select">
                        <option value="10">10</option>
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>

                <div class="audit-filter audit-filter-actions">
                    <button
                        type="button"
                        class="btn-ui btn-primary-ui"
                        id="applyAuditFilters"
                    >
                        <i data-lucide="search"></i>
                        Apply
                    </button>
                    <button
                        type="button"
                        class="btn-ui"
                        id="clearAuditFilters"
                    >
                        <i data-lucide="rotate-ccw"></i>
                        Clear
                    </button>
                </div>
            </div>
        </div>
    </article>

    <article class="ui-card audit-history-card">
        <div class="ui-card-header audit-toolbar">
            <div>
                <h2 class="ui-card-title mb-1">Audit History</h2>
                <div class="audit-results" id="auditResultInfo">
                    Loading records...
                </div>
            </div>
            <span class="badge text-bg-light">
                Read-only audit trail
            </span>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0 audit-table">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>School / Branch</th>
                        <th>Academic Year</th>
                        <th>User / Role</th>
                        <th>Module</th>
                        <th>Action</th>
                        <th>Table / Record</th>
                        <th>Description</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="auditRows">
                    <tr>
                        <td colspan="9" class="audit-loading">
                            Loading Audit Logs...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="ui-card-body audit-pagination">
            <div class="audit-results" id="auditPageInfo"></div>
            <div
                class="audit-pagination-buttons"
                id="auditPagination"
            ></div>
        </div>
    </article>
</div>

<div
    class="modal fade"
    id="auditDetailModal"
    tabindex="-1"
    aria-hidden="true"
>
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Audit Log Details</h5>
                    <small
                        class="text-muted"
                        id="auditDetailSubTitle"
                    ></small>
                </div>
                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Close"
                ></button>
            </div>
            <div class="modal-body" id="auditDetailBody">
                Loading...
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    const apiUrl = <?= json_encode(
        $apiUrl,
        JSON_UNESCAPED_SLASHES
    ) ?>;

    const cardSummaryUrl = <?= json_encode(
        $baseUrl . 'super-admin/activity-logs.php?audit_cards=1',
        JSON_UNESCAPED_SLASHES
    ) ?>;

    const state = {
        meta: {
            schools: [],
            branches: [],
            years: [],
            users: [],
            roles: [],
            modules: [],
            actions: [],
            tables: []
        },
        page: 1,
        pagination: {
            page: 1,
            pages: 1,
            total: 0,
            from: 0,
            to: 0
        }
    };

    const el = id => document.getElementById(id);

    const html = value => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const number = value =>
        new Intl.NumberFormat('en-IN').format(Number(value || 0));

    function notify(type, message) {
        if (typeof window.showToast === 'function') {
            window.showToast(type, message);
            return;
        }

        console[type === 'error' ? 'error' : 'log'](message);
    }

    async function request(params) {
        const url = new URL(apiUrl, window.location.href);

        Object.entries(params).forEach(([key, value]) => {
            if (value !== '' && value !== null && value !== undefined) {
                url.searchParams.set(key, String(value));
            }
        });

        const response = await fetch(url.toString(), {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const text = await response.text();
        let payload;

        try {
            payload = JSON.parse(text);
        } catch (error) {
            throw new Error(
                text.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim()
                || 'Audit Logs API returned invalid JSON.'
            );
        }

        if (!response.ok || !payload.success) {
            throw new Error(
                payload.message || 'Audit Logs request failed.'
            );
        }

        return payload.data || {};
    }

    function selectedSchoolId() {
        return Number(el('schoolFilter').value || 0);
    }

    function option(value, label, selectedValue = '') {
        return `<option value="${html(value)}"${
            String(value) === String(selectedValue)
                ? ' selected'
                : ''
        }>${html(label)}</option>`;
    }

    function refillSchoolDependentFilters() {
        const schoolId = selectedSchoolId();

        const currentBranch = el('branchFilter').value;
        const currentYear = el('yearFilter').value;
        const currentUser = el('userFilter').value;
        const currentRole = el('roleFilter').value;

        const branches = state.meta.branches.filter(row =>
            !schoolId || Number(row.tenant_id) === schoolId
        );
        const years = state.meta.years.filter(row =>
            !schoolId || Number(row.tenant_id) === schoolId
        );
        const users = state.meta.users.filter(row =>
            !schoolId || Number(row.tenant_id) === schoolId
        );
        const roles = state.meta.roles.filter(row =>
            !schoolId
            || row.tenant_id === null
            || Number(row.tenant_id) === schoolId
        );

        el('branchFilter').innerHTML =
            '<option value="">All Branches</option>'
            + branches.map(row => option(
                row.id,
                `${row.branch_name} (${row.branch_code})`,
                currentBranch
            )).join('');

        el('yearFilter').innerHTML =
            '<option value="">All Academic Years</option>'
            + years.map(row => option(
                row.id,
                `${row.year_name}${Number(row.is_current) ? ' - Current' : ''}`,
                currentYear
            )).join('');

        el('userFilter').innerHTML =
            '<option value="">All Users</option>'
            + users.map(row => option(
                row.id,
                `${row.name} (${row.username})`,
                currentUser
            )).join('');

        el('roleFilter').innerHTML =
            '<option value="">All Roles</option>'
            + roles.map(row => option(
                row.id,
                `${row.role_name} - ${row.role_scope}`,
                currentRole
            )).join('');
    }

    function fillMeta(meta) {
        state.meta = {
            ...state.meta,
            ...meta
        };

        el('schoolFilter').innerHTML =
            '<option value="">All Schools</option>'
            + state.meta.schools.map(row => option(
                row.id,
                `${row.school_name} (${row.tenant_code})`
            )).join('');

        refillSchoolDependentFilters();

        el('moduleFilter').innerHTML =
            '<option value="">All Modules</option>'
            + state.meta.modules.map(value => option(
                value,
                value
            )).join('');

        el('actionFilter').innerHTML =
            '<option value="">All Actions</option>'
            + state.meta.actions.map(value => option(
                value,
                value.replaceAll('_', ' ')
            )).join('');

        el('tableFilter').innerHTML =
            '<option value="">All Tables</option>'
            + state.meta.tables.map(value => option(
                value,
                value
            )).join('');
    }

    function filters(includePage = true) {
        const data = {
            q: el('auditSearch').value.trim(),
            school_id: el('schoolFilter').value,
            branch_id: el('branchFilter').value,
            academic_year_id: el('yearFilter').value,
            user_id: el('userFilter').value,
            role_id: el('roleFilter').value,
            module: el('moduleFilter').value,
            action_group: el('actionGroupFilter').value,
            action_key: el('actionFilter').value,
            table_name: el('tableFilter').value,
            from_date: el('fromDate').value,
            to_date: el('toDate').value,
            per_page: el('perPage').value
        };

        if (includePage) {
            data.page = state.page;
        }

        return data;
    }

    function actionLabel(row) {
        const raw = String(row.action_key || '');
        const label = raw
            .replaceAll('_', ' ')
            .replace(/\b\w/g, letter => letter.toUpperCase());

        return label || 'Action';
    }

    function dateTime(value) {
        if (!value) {
            return ['—', ''];
        }

        const date = new Date(String(value).replace(' ', 'T'));

        if (Number.isNaN(date.getTime())) {
            return [String(value), ''];
        }

        return [
            date.toLocaleDateString('en-IN', {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            }),
            date.toLocaleTimeString('en-IN', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            })
        ];
    }

    function renderSummary(summary) {
        el('statTotal').textContent = number(summary.total);
        el('statToday').textContent = number(summary.today);
        el('statLogin').textContent = number(summary.login_count);
        el('statSuspicious').textContent = number(summary.suspicious_count);
    }

    async function loadCardSummary() {
        const url = new URL(
            cardSummaryUrl,
            window.location.href
        );

        Object.entries(filters(false)).forEach(([key, value]) => {
            if (
                value !== ''
                && value !== null
                && value !== undefined
            ) {
                url.searchParams.set(
                    key,
                    String(value)
                );
            }
        });

        const response = await fetch(
            url.toString(),
            {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }
        );

        const text = await response.text();
        let payload;

        try {
            payload = JSON.parse(text);
        } catch (error) {
            throw new Error(
                'Audit dashboard statistics returned invalid JSON.'
            );
        }

        if (
            !response.ok
            || !payload.success
        ) {
            throw new Error(
                payload.message
                || 'Unable to load Audit dashboard statistics.'
            );
        }

        renderSummary(payload.data || {});
    }

    function renderRows(rows) {
        const body = el('auditRows');

        if (!rows.length) {
            body.innerHTML = `
                <tr>
                    <td colspan="9" class="audit-empty">
                        No Audit Logs found for the selected filters.
                    </td>
                </tr>
            `;
            return;
        }

        body.innerHTML = rows.map(row => {
            const [date, time] = dateTime(row.created_at);

            return `
                <tr>
                    <td>
                        <strong>${html(date)}</strong>
                        <span class="audit-cell-meta">${html(time)}</span>
                        <span class="audit-cell-meta">#${html(row.id)}</span>
                    </td>

                    <td>
                        <div class="audit-school">
                            <strong>${html(row.school_name || 'Unknown School')}</strong>
                            <small>${html(row.tenant_code || '')}</small>
                            <small>
                                ${html(row.branch_name || 'School-wide')}
                                ${row.branch_code ? `(${html(row.branch_code)})` : ''}
                            </small>
                        </div>
                    </td>

                    <td>
                        <strong>${html(row.academic_year_name || '—')}</strong>
                    </td>

                    <td>
                        <div class="audit-user">
                            <strong>${html(row.user_name || 'System / Unknown')}</strong>
                            <small>${html(row.username || '')}</small>
                            <small>${html(row.role_name || row.role_key || '—')}</small>
                        </div>
                    </td>

                    <td>
                        <strong>${html(row.module_name || '—')}</strong>
                    </td>

                    <td>
                        <span
                            class="audit-action"
                            data-group="${html(row.action_group || 'other')}"
                            title="${html(row.action_key || '')}"
                        >
                            ${html(actionLabel(row))}
                        </span>
                        <span class="audit-cell-meta">
                            ${html(row.action_group || 'other')}
                        </span>
                    </td>

                    <td>
                        <strong>${html(row.table_name || '—')}</strong>
                        <span class="audit-cell-meta">
                            ${row.record_id ? `Record #${html(row.record_id)}` : 'No record ID'}
                        </span>
                    </td>

                    <td>
                        <div class="audit-description">
                            ${html(row.description || 'No description')}
                        </div>
                        <span class="audit-cell-meta">
                            IP: ${html(row.ip_address || '—')}
                        </span>
                    </td>

                    <td>
                        <button
                            class="btn-ui audit-view-button"
                            type="button"
                            data-audit-id="${html(row.id)}"
                            title="View Audit Details"
                        >
                            <i data-lucide="eye"></i>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');

        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    function renderPagination(pagination) {
        state.pagination = pagination;

        el('auditResultInfo').textContent =
            `Showing ${number(pagination.from)}–${number(pagination.to)}`
            + ` of ${number(pagination.total)} matching records`;

        el('auditPageInfo').textContent =
            `Page ${number(pagination.page)} of ${number(pagination.pages)}`;

        const container = el('auditPagination');
        const buttons = [];

        const add = (label, page, disabled = false, active = false) => {
            buttons.push(`
                <button
                    type="button"
                    class="btn-ui ${active ? 'btn-primary-ui' : ''}"
                    data-page="${page}"
                    ${disabled ? 'disabled' : ''}
                >${label}</button>
            `);
        };

        add(
            '&laquo;',
            pagination.page - 1,
            pagination.page <= 1
        );

        const start = Math.max(1, pagination.page - 2);
        const end = Math.min(pagination.pages, pagination.page + 2);

        for (let page = start; page <= end; page += 1) {
            add(String(page), page, false, page === pagination.page);
        }

        add(
            '&raquo;',
            pagination.page + 1,
            pagination.page >= pagination.pages
        );

        container.innerHTML = buttons.join('');
    }

    async function loadLogs() {
        el('auditRows').innerHTML = `
            <tr>
                <td colspan="9" class="audit-loading">
                    Loading Audit Logs...
                </td>
            </tr>
        `;

        try {
            const data = await request({
                action: 'list',
                ...filters(true)
            });

            renderRows(data.rows || []);
            renderPagination(data.pagination || {
                page: 1,
                pages: 1,
                total: 0,
                from: 0,
                to: 0
            });

            try {
                await loadCardSummary();
            } catch (cardError) {
                console.error(cardError);
            }
        } catch (error) {
            el('auditRows').innerHTML = `
                <tr>
                    <td colspan="9" class="audit-empty">
                        ${html(error.message)}
                    </td>
                </tr>
            `;
            notify('error', error.message);
        }
    }

    function prettyJson(value) {
        if (!value) {
            return 'No data';
        }

        try {
            return JSON.stringify(JSON.parse(value), null, 2);
        } catch (error) {
            return String(value);
        }
    }

    async function showDetail(id) {
        const modalElement = el('auditDetailModal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalElement);

        el('auditDetailSubTitle').textContent = `Log #${id}`;
        el('auditDetailBody').innerHTML = 'Loading...';
        modal.show();

        try {
            const data = await request({
                action: 'detail',
                id
            });
            const row = data.log || {};
            const [date, time] = dateTime(row.created_at);

            el('auditDetailBody').innerHTML = `
                <div class="audit-detail-grid mb-3">
                    <div class="audit-detail-item">
                        <small>Date & Time</small>
                        <strong>${html(date)} ${html(time)}</strong>
                    </div>
                    <div class="audit-detail-item">
                        <small>School</small>
                        <strong>
                            ${html(row.school_name || 'Unknown School')}
                            ${row.tenant_code ? `(${html(row.tenant_code)})` : ''}
                        </strong>
                    </div>
                    <div class="audit-detail-item">
                        <small>Branch</small>
                        <strong>
                            ${html(row.branch_name || 'School-wide')}
                            ${row.branch_code ? `(${html(row.branch_code)})` : ''}
                        </strong>
                    </div>
                    <div class="audit-detail-item">
                        <small>Academic Year</small>
                        <strong>${html(row.academic_year_name || '—')}</strong>
                    </div>
                    <div class="audit-detail-item">
                        <small>User</small>
                        <strong>
                            ${html(row.user_name || 'System / Unknown')}
                            ${row.username ? `(@${html(row.username)})` : ''}
                        </strong>
                    </div>
                    <div class="audit-detail-item">
                        <small>Role</small>
                        <strong>${html(row.role_name || row.role_key || '—')}</strong>
                    </div>
                    <div class="audit-detail-item">
                        <small>Module</small>
                        <strong>${html(row.module_name || '—')}</strong>
                    </div>
                    <div class="audit-detail-item">
                        <small>Action</small>
                        <strong>
                            ${html(row.action_key || '—')}
                            / ${html(row.action_group || 'other')}
                        </strong>
                    </div>
                    <div class="audit-detail-item">
                        <small>Table / Record</small>
                        <strong>
                            ${html(row.table_name || '—')}
                            ${row.record_id ? ` / #${html(row.record_id)}` : ''}
                        </strong>
                    </div>
                    <div class="audit-detail-item">
                        <small>IP Address</small>
                        <strong>${html(row.ip_address || '—')}</strong>
                    </div>
                    <div class="audit-detail-item full">
                        <small>Description</small>
                        <span>${html(row.description || 'No description')}</span>
                    </div>
                    <div class="audit-detail-item full">
                        <small>User Agent</small>
                        <span>${html(row.user_agent || '—')}</span>
                    </div>
                </div>

                <div class="audit-json-grid">
                    <div>
                        <div class="fw-bold small mb-2">Old Values</div>
                        <pre class="audit-json">${html(prettyJson(row.old_values))}</pre>
                    </div>
                    <div>
                        <div class="fw-bold small mb-2">New Values</div>
                        <pre class="audit-json">${html(prettyJson(row.new_values))}</pre>
                    </div>
                </div>
            `;
        } catch (error) {
            el('auditDetailBody').innerHTML = `
                <div class="alert alert-danger mb-0">
                    ${html(error.message)}
                </div>
            `;
        }
    }

    function clearFilters() {
        [
            'auditSearch',
            'schoolFilter',
            'branchFilter',
            'yearFilter',
            'userFilter',
            'roleFilter',
            'moduleFilter',
            'actionGroupFilter',
            'actionFilter',
            'tableFilter',
            'fromDate',
            'toDate'
        ].forEach(id => {
            el(id).value = '';
        });

        el('perPage').value = '25';
        state.page = 1;
        refillSchoolDependentFilters();
        loadLogs();
    }

    async function init() {
        try {
            fillMeta(await request({action: 'meta'}));
            await loadLogs();
        } catch (error) {
            notify('error', error.message);
            el('auditRows').innerHTML = `
                <tr>
                    <td colspan="9" class="audit-empty">
                        ${html(error.message)}
                    </td>
                </tr>
            `;
        }
    }

    el('schoolFilter').addEventListener('change', () => {
        refillSchoolDependentFilters();
    });

    el('applyAuditFilters').addEventListener('click', () => {
        state.page = 1;
        loadLogs();
    });

    el('clearAuditFilters').addEventListener('click', clearFilters);

    el('auditSearch').addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            state.page = 1;
            loadLogs();
        }
    });

    el('auditPagination').addEventListener('click', event => {
        const button = event.target.closest('[data-page]');

        if (!button || button.disabled) {
            return;
        }

        const page = Number(button.dataset.page || 1);

        if (page < 1 || page > state.pagination.pages) {
            return;
        }

        state.page = page;
        loadLogs();
    });

    el('auditRows').addEventListener('click', event => {
        const button = event.target.closest('[data-audit-id]');

        if (button) {
            showDetail(Number(button.dataset.auditId));
        }
    });

    el('exportAuditLogs').addEventListener('click', () => {
        const url = new URL(apiUrl, window.location.href);
        url.searchParams.set('action', 'export');

        Object.entries(filters(false)).forEach(([key, value]) => {
            if (value !== '') {
                url.searchParams.set(key, String(value));
            }
        });

        window.location.href = url.toString();
    });

    el('refreshAuditLogs').addEventListener('click', () => {
        state.page = 1;
        loadLogs();
    });

    el('printAuditLogs').addEventListener('click', () => {
        window.print();
    });

    init();
})();
</script>

<?php require $projectRoot . '/includes/layout-end.php'; ?>

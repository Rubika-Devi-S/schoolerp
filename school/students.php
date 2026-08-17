<?php
declare(strict_types=1);

/* Build: 2026-08-15-student-strict-school-branch-ui-v15 */

$pageTitle = 'Students Management';
$pageKey = 'student_management';
$sidebarFile = __DIR__ . '/sidebar.php';

require dirname(__DIR__) . '/includes/layout-start.php';

/*
 * Use the existing common toast system.
 */
$commonToastFile =
    dirname(__DIR__)
    . '/includes/common-toast.php';

if (is_file($commonToastFile)) {
    require_once $commonToastFile;
}

/*
 * IMPORTANT
 * ---------
 * Import / Export permissions on this page must come ONLY from students.php.
 *
 * Do not use the parent "Students Management" / student_management permission
 * for these two actions.
 */
if (!function_exists('students_page_file_permission')) {
    function students_page_file_permission(
        string $action
    ): bool {
        if (
            function_exists('is_super_admin')
            && is_super_admin()
        ) {
            return true;
        }

        $action = strtolower(
            trim($action)
        );

        if (
            !in_array(
                $action,
                ['import', 'export'],
                true
            )
        ) {
            return false;
        }

        $user = function_exists('current_user')
            ? current_user()
            : [];

        $user = is_array($user)
            ? $user
            : [];

        $tenantId = (int)(
            $user['tenant_id']
            ?? $user['school_id']
            ?? $_SESSION['tenant_id']
            ?? $_SESSION['school_id']
            ?? 0
        );

        $userId = (int)(
            $user['id']
            ?? $user['user_id']
            ?? $_SESSION['user_id']
            ?? 0
        );

        /*
         * Exact School Sidebar item decision.
         * The route is supplied explicitly so this resolves students.php,
         * not its parent Student Management sidebar item.
         */
        if (
            function_exists(
                'school_sidebar_permission_decision'
            )
        ) {
            $decision =
                school_sidebar_permission_decision(
                    'students',
                    $action,
                    $tenantId,
                    $userId > 0
                        ? $userId
                        : null,
                    'school/students.php'
                );

            if ($decision !== null) {
                return (bool)$decision;
            }
        }

        /*
         * Normalized permission fallback: students only.
         * Never fall back to student_management.
         */
        if (
            function_exists(
                'school_effective_permission'
            )
        ) {
            return (bool)
                school_effective_permission(
                    'students',
                    $action,
                    $tenantId,
                    0,
                    $userId > 0
                        ? $userId
                        : null
                );
        }

        if (
            function_exists('has_permission')
        ) {
            return (bool)
                has_permission(
                    'students',
                    $action
                );
        }

        return false;
    }
}

$canStudentsImport =
    students_page_file_permission(
        'import'
    );

$canStudentsExport =
    students_page_file_permission(
        'export'
    );

$csrfToken = function_exists('csrfToken') ? csrfToken() : '';
?>
<style>
.student-page{display:grid;gap:16px}
.student-page .page-title{font-size:28px;line-height:1.1}
.student-page .page-subtitle{margin-top:4px}
.student-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.student-kpi{border-radius:14px;color:#fff;min-height:112px;padding:18px 20px;display:flex;align-items:center;gap:14px;position:relative;overflow:hidden;box-shadow:0 12px 28px rgba(15,23,42,.08)}
.student-kpi::after{content:"";position:absolute;width:110px;height:110px;border-radius:50%;right:-38px;top:-40px;background:rgba(255,255,255,.08)}
.student-kpi.purple{background:linear-gradient(135deg,#7448e8,#4b36cf)}
.student-kpi.green{background:linear-gradient(135deg,#45c783,#1ea568)}
.student-kpi.pink{background:linear-gradient(135deg,#ff527c,#ed2f63)}
.student-kpi.orange{background:linear-gradient(135deg,#ffb327,#ff8a17)}
.student-kpi-icon{width:50px;height:50px;border-radius:50%;display:grid;place-items:center;background:rgba(255,255,255,.16);flex:0 0 auto}
.student-kpi-icon svg{width:25px;height:25px}
.student-kpi strong{display:block;font-size:26px;line-height:1}
.student-kpi small{display:block;font-size:11px;font-weight:700;opacity:.94;margin-bottom:6px}
.student-kpi .trend{font-size:9px;font-weight:700;opacity:.94;margin-top:8px}
.student-filter-card{padding:14px;display:grid;grid-template-columns:minmax(220px,1.35fr) minmax(145px,.75fr) repeat(4,minmax(125px,.72fr)) auto;gap:10px}
.student-layout{display:grid;grid-template-columns:minmax(0,1fr);gap:16px;align-items:start}
.student-card{border-radius:14px;overflow:hidden}
.student-card-head{padding:16px;border-bottom:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;align-items:center;gap:10px}
.student-card-head strong{font-size:14px}
.student-class-nav{padding:14px 16px;border-bottom:1px solid var(--border-soft,#e7ebf3);display:flex;gap:9px;flex-wrap:wrap;background:var(--card-bg,#fff)}
.student-class-button{border:1px solid var(--border-soft,#dce3f0);background:var(--card-bg,#fff);color:var(--text-main,#1e293b);border-radius:10px;padding:9px 13px;display:flex;align-items:center;gap:8px;font-size:11px;font-weight:800;transition:.18s ease}
.student-class-button:hover{border-color:#7558e8;color:#5b42d6}
.student-class-button.active{color:#fff;border-color:transparent;background:linear-gradient(135deg,#6d4ce7,#345fe0);box-shadow:0 7px 16px rgba(79,70,229,.2)}
.student-class-count{min-width:22px;height:22px;padding:0 6px;border-radius:999px;display:inline-grid;place-items:center;background:rgba(100,116,139,.12);font-size:9px}
.student-class-button.active .student-class-count{background:rgba(255,255,255,.2)}
.student-table-wrap{overflow:auto}
.student-table{min-width:980px}
.student-table th{font-size:10px}
.student-table td{font-size:11px;vertical-align:middle}
.student-cell{display:flex;align-items:center;gap:9px}
.student-avatar{width:30px;height:30px;border-radius:50%;display:grid;place-items:center;color:#fff;font-size:11px;font-weight:800;background:linear-gradient(135deg,#6d4ce7,#345fe0)}
.student-badge{display:inline-flex;align-items:center;padding:5px 9px;border-radius:999px;font-size:9px;font-weight:800;text-transform:capitalize}
.student-badge.active{color:#16834f;background:#e8f8ef}
.student-badge.inactive,.student-badge.withdrawn{color:#dc2626;background:#fff0f1}
.student-badge.tc,.student-badge.alumni{color:#9a6700;background:#fff7d6}
.student-actions{display:flex;gap:6px}
.student-action{width:30px;height:30px;border:1px solid #d7def1;border-radius:7px;background:var(--card-bg,#fff);display:grid;place-items:center;color:#334155}
.student-action svg{width:13px;height:13px}
.student-pagination{padding:12px 16px;border-top:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;align-items:center}
.student-pagination small{font-size:10px;color:var(--text-muted,#64748b)}
.chart-wrap{padding:18px}
.donut{width:180px;height:180px;border-radius:50%;margin:0 auto 16px;position:relative;background:conic-gradient(#4a8df6 0 20%,#31b56d 20% 40%,#ff9c1b 40% 60%,#f23d68 60% 80%,#a75bd7 80% 100%)}
.donut::after{content:"";position:absolute;inset:30px;border-radius:50%;background:var(--card-bg,#fff)}
.chart-legend{display:flex;flex-wrap:wrap;gap:10px;justify-content:center}
.legend-item{display:flex;align-items:center;gap:5px;font-size:10px}
.legend-dot{width:9px;height:9px;border-radius:50%}
.activity-list{display:grid}
.activity-item{padding:12px 16px;border-bottom:1px solid var(--border-soft,#e7ebf3);font-size:11px}
.activity-item:last-child{border-bottom:0}
.activity-item strong,.activity-item small{display:block}
.activity-item small{color:var(--text-muted,#64748b);margin-top:3px}
.student-message{display:none}.student-message.show{display:block}
.student-empty{padding:38px 18px;text-align:center;color:var(--text-muted,#64748b)}
.student-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
.student-grid .full{grid-column:1/-1}
.student-fee-preview{grid-column:1/-1;border:1px solid var(--border-soft,#e7ebf3);border-radius:11px;background:var(--card-bg,#fff);overflow:hidden;display:none}
.student-fee-preview.show{display:block}
.student-fee-preview-head{padding:11px 13px;border-bottom:1px solid var(--border-soft,#e7ebf3);display:flex;align-items:center;justify-content:space-between;gap:10px}
.student-fee-preview-head strong{font-size:12px}
.student-fee-preview-total{font-size:14px;color:#4f46e5}
.student-fee-preview-list{display:grid}
.student-fee-preview-row{display:grid;grid-template-columns:minmax(180px,1fr) 120px 100px 130px;gap:10px;align-items:center;padding:9px 13px;border-bottom:1px solid var(--border-soft,#eef1f6);font-size:10px}
.student-fee-preview-row:last-child{border-bottom:0}
.student-fee-preview-row strong{font-size:11px}
.student-fee-preview-row .amount{text-align:right;font-weight:800}
.student-fee-zero{color:var(--text-muted,#64748b)}
@media(max-width:700px){.student-fee-preview-row{grid-template-columns:1fr 1fr}.student-fee-preview-row .amount{text-align:left}}

#studentModal{overflow-y:auto;padding-right:0!important}
#studentModal .modal-dialog{width:min(1120px,calc(100vw - 24px));max-width:1120px;height:calc(100dvh - 32px);margin:16px auto}
#studentModal .modal-content{height:100%;overflow:hidden}
#studentModal #studentForm{display:flex;flex-direction:column;height:100%;min-height:0}
#studentModal .modal-header,#studentModal .modal-footer{flex:0 0 auto}
#studentModal .modal-body{flex:1 1 auto;min-height:0;overflow-y:auto!important}
@media(max-width:1250px){.student-layout{grid-template-columns:1fr}.student-filter-card{grid-template-columns:repeat(3,1fr)}}
@media(max-width:900px){.student-kpis{grid-template-columns:repeat(2,1fr)}.student-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:575px){.student-kpis,.student-filter-card,.student-grid{grid-template-columns:1fr}.student-grid .full{grid-column:auto}#studentModal .modal-dialog{width:100%;height:100dvh;margin:0}#studentModal .modal-content{border-radius:0}}
.student-transport-title{grid-column:1/-1;padding:10px 12px;border-radius:10px;background:rgba(79,70,229,.06);display:flex;align-items:center;justify-content:space-between;gap:10px}
.student-transport-title strong{font-size:12px}
.student-transport-readonly{background:rgba(100,116,139,.06)!important}

.student-import-drop{border:1px dashed #b9c4dc;border-radius:12px;padding:18px;text-align:center;background:rgba(99,102,241,.035)}
.student-import-drop input{max-width:520px;margin:10px auto 0}
.student-template-actions{display:flex;gap:8px;flex-wrap:wrap}
.student-import-result{display:none;font-size:11px;padding:12px;margin-bottom:0}.student-import-result.show{display:block}
.student-import-options{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:14px}
.student-import-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin-top:10px}
.student-import-card{appearance:none;border:1px solid rgba(99,102,241,.13);padding:10px;border-radius:10px;background:rgba(99,102,241,.055);text-align:center;cursor:pointer;min-width:0;transition:.18s}
.student-import-card:hover,.student-import-card:focus{transform:translateY(-1px);border-color:#6366f1;box-shadow:0 8px 18px rgba(79,70,229,.1);outline:none}
.student-import-card.active{color:#fff;background:linear-gradient(135deg,#6747e8,#2f62d7);border-color:transparent}
.student-import-card strong,.student-import-card small{display:block}
.student-import-card strong{font-size:19px}
.student-import-card small{font-size:9px;color:inherit;opacity:.82}
.student-import-card .student-import-view-label{margin-top:4px;font-size:9px;font-weight:800;text-decoration:underline}
.student-import-details{display:none;margin-top:10px;border:1px solid rgba(99,102,241,.14);border-radius:10px;background:var(--card-bg,#fff);overflow:hidden}
.student-import-details.show{display:block}
.student-import-details-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 12px;border-bottom:1px solid var(--border-soft,#e7ebf3)}
.student-import-details-body{max-height:230px;overflow:auto;-webkit-overflow-scrolling:touch}
.student-import-detail-table{width:100%;min-width:650px;border-collapse:collapse}
.student-import-detail-table th,.student-import-detail-table td{padding:9px 10px;border-bottom:1px solid var(--border-soft,#edf0f5);text-align:left;vertical-align:top}
.student-import-detail-table th{position:sticky;top:0;background:var(--card-bg,#fff);z-index:1;font-size:9px;text-transform:uppercase;white-space:nowrap}
.student-import-detail-table td{font-size:10px}
.student-import-detail-empty{padding:22px;text-align:center;color:var(--text-muted,#64748b)}
.student-missing-columns-action{display:none;margin-top:12px;padding:12px;border:1px solid #f5c76b;border-radius:11px;background:#fff9e8}.student-missing-columns-action.show{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}.student-missing-columns-action strong{font-size:11px}.student-missing-columns-action small{display:block;margin-top:3px;color:#8a6510;font-size:9px}.student-missing-columns-panel{display:none;margin-top:12px;border:1px solid rgba(99,102,241,.18);border-radius:12px;background:var(--card-bg,#fff);overflow:hidden}.student-missing-columns-panel.show{display:block}.student-missing-columns-head{padding:12px 14px;border-bottom:1px solid var(--border-soft,#e7ebf3);display:flex;align-items:flex-start;justify-content:space-between;gap:10px;background:rgba(99,102,241,.045)}.student-missing-columns-head strong,.student-missing-columns-head small{display:block}.student-missing-columns-head strong{font-size:12px}.student-missing-columns-head small{margin-top:3px;color:var(--text-muted,#64748b);font-size:9px}.student-missing-columns-body{padding:14px;display:grid;gap:12px}.student-missing-columns-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.student-missing-column-row{padding:11px;border:1px solid var(--border-soft,#e7ebf3);border-radius:10px;background:rgba(248,250,252,.72)}.student-missing-column-title{display:flex;align-items:center;gap:8px;margin-bottom:9px}.student-missing-column-title label{margin:0;font-size:10px;font-weight:800}.student-missing-column-row .form-select,.student-missing-column-row .form-control{font-size:11px}.student-missing-file{padding:12px;border:1px dashed #b9c4dc;border-radius:10px;background:rgba(99,102,241,.025)}.student-missing-file strong,.student-missing-file small{display:block}.student-missing-file strong{font-size:11px}.student-missing-file small{margin:3px 0 8px;color:var(--text-muted,#64748b);font-size:9px}.student-missing-unsupported{padding:9px 10px;border-radius:8px;color:#b42318;background:#fff1f0;font-size:9px}.student-missing-column-count{min-width:22px;height:22px;padding:0 6px;display:inline-grid;place-items:center;border-radius:999px;background:rgba(180,83,9,.12);font-size:9px;font-weight:800}@media(max-width:767px){.student-missing-columns-grid{grid-template-columns:1fr}}\n#importModal{overflow:hidden}
#importModal .modal-dialog{width:min(980px,calc(100vw - 24px));max-width:980px;height:calc(100dvh - 24px);margin:12px auto}
#importModal .modal-content{height:100%;max-height:none;overflow:hidden}
#importModal #importForm{display:flex;flex-direction:column;height:100%;min-height:0}
#importModal .modal-header,#importModal .modal-footer{flex:0 0 auto}
#importModal .modal-body{flex:1 1 auto;min-height:0;overflow-y:auto;-webkit-overflow-scrolling:touch}
#importModal .modal-footer{position:relative;z-index:2;background:var(--card-bg,#fff);box-shadow:0 -8px 20px rgba(15,23,42,.06)}
#importModal .modal-footer .btn-ui{min-height:42px}
@media(min-width:1400px){#importModal .modal-dialog{width:min(1040px,calc(100vw - 40px));max-width:1040px;height:min(850px,calc(100dvh - 40px));margin:20px auto}}
@media(max-width:900px){#importModal .modal-dialog{width:calc(100vw - 16px);height:calc(100dvh - 16px);margin:8px auto}.student-import-drop{padding:15px}.student-template-actions{width:100%}.student-template-actions .btn-ui{flex:1 1 0;justify-content:center}}
@media(max-width:767px){.student-import-options{grid-template-columns:1fr}.student-import-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.student-import-details-head{align-items:flex-start;flex-direction:column}}
@media(max-width:575px){#importModal .modal-dialog{width:100%;height:100dvh;margin:0}#importModal .modal-content{border-radius:0}#importModal .modal-header{padding:14px}#importModal .modal-body{padding:12px}#importModal .modal-footer{display:grid;grid-template-columns:1fr 1fr;padding:10px 12px}#importModal .modal-footer .btn-ui{width:100%;justify-content:center}.student-import-summary{grid-template-columns:1fr 1fr}.student-import-drop input{font-size:12px}.student-import-detail-table{min-width:560px}}
@media(max-height:720px) and (min-width:576px){#importModal .modal-dialog{height:calc(100dvh - 12px);margin:6px auto}#importModal .modal-header{padding-top:10px;padding-bottom:10px}#importModal .modal-body{padding-top:10px;padding-bottom:10px}#importModal .modal-footer{padding-top:8px;padding-bottom:8px}}

.parent-login-bulk-summary{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:10px;
    margin-top:12px
}
.parent-login-bulk-stat{
    padding:12px;
    border:1px solid var(--border-soft,#e7ebf3);
    border-radius:11px;
    background:rgba(99,102,241,.035);
    text-align:center
}
.parent-login-bulk-stat strong,
.parent-login-bulk-stat small{display:block}
.parent-login-bulk-stat strong{font-size:20px}
.parent-login-bulk-stat small{
    margin-top:4px;
    font-size:9px;
    color:var(--text-muted,#64748b)
}
.parent-login-bulk-note{
    padding:11px 12px;
    border:1px solid #dbeafe;
    border-radius:10px;
    background:#eff6ff;
    color:#1e40af;
    font-size:10px;
    line-height:1.55
}
.parent-login-result-actions{
    display:flex;
    gap:8px;
    flex-wrap:wrap
}
.parent-login-result-wrap{
    max-height:52vh;
    overflow:auto;
    border:1px solid var(--border-soft,#e7ebf3);
    border-radius:10px
}
.parent-login-result-table{
    width:100%;
    min-width:1050px;
    border-collapse:collapse
}
.parent-login-result-table th,
.parent-login-result-table td{
    padding:9px 10px;
    border-bottom:1px solid var(--border-soft,#edf0f5);
    text-align:left;
    vertical-align:top;
    font-size:10px
}
.parent-login-result-table th{
    position:sticky;
    top:0;
    z-index:2;
    background:var(--card-bg,#fff);
    font-size:9px;
    text-transform:uppercase;
    white-space:nowrap
}
.parent-login-password{
    display:inline-block;
    padding:4px 7px;
    border-radius:7px;
    background:#fff7d6;
    color:#8a5c00;
    font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;
    font-weight:900;
    letter-spacing:.04em
}
.parent-login-status-list{
    margin-top:12px;
    display:grid;
    gap:7px
}
.parent-login-status-item{
    padding:9px 10px;
    border:1px solid var(--border-soft,#e7ebf3);
    border-radius:9px;
    font-size:9px
}
#parentLoginBulkModal .modal-dialog,
#parentLoginResultModal .modal-dialog{
    width:min(1120px,calc(100vw - 24px));
    max-width:1120px
}
@media(max-width:767px){
    .parent-login-bulk-summary{grid-template-columns:repeat(2,minmax(0,1fr))}
    .parent-login-result-actions{width:100%}
    .parent-login-result-actions .btn-ui{flex:1 1 auto;justify-content:center}
}
@media(max-width:575px){
    .parent-login-bulk-summary{grid-template-columns:1fr 1fr}
    #parentLoginBulkModal .modal-dialog,
    #parentLoginResultModal .modal-dialog{
        width:100%;
        margin:0;
        min-height:100dvh
    }
    #parentLoginBulkModal .modal-content,
    #parentLoginResultModal .modal-content{
        min-height:100dvh;
        border-radius:0
    }
}


.parent-login-select-table-wrap{
    max-height:52vh;
    overflow:auto;
    border:1px solid var(--border-soft,#e7ebf3);
    border-radius:10px
}
.parent-login-select-table{
    width:100%;
    min-width:1180px;
    border-collapse:collapse
}
.parent-login-select-table th,
.parent-login-select-table td{
    padding:9px 10px;
    border-bottom:1px solid var(--border-soft,#edf0f5);
    text-align:left;
    vertical-align:middle;
    font-size:10px
}
.parent-login-select-table th{
    position:sticky;
    top:0;
    z-index:2;
    background:var(--card-bg,#fff);
    font-size:9px;
    text-transform:uppercase;
    white-space:nowrap
}
.parent-login-select-table input[type=checkbox]{
    width:16px;
    height:16px;
    cursor:pointer
}
.parent-login-select-table input[type=checkbox]:disabled{
    cursor:not-allowed;
    opacity:.45
}
.parent-login-muted-password{
    color:var(--text-muted,#64748b);
    font-size:9px;
    font-weight:700
}
.parent-login-generated-now{
    color:#166534;
    background:#dcfce7;
    border-radius:7px;
    padding:4px 6px;
    display:inline-block;
    font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;
    font-weight:900
}
.parent-login-toolbar{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
    margin:12px 0
}
.parent-login-toolbar-left,
.parent-login-toolbar-right{
    display:flex;
    gap:8px;
    align-items:center;
    flex-wrap:wrap
}
.parent-login-selection-count{
    font-size:10px;
    font-weight:800
}
@media(max-width:575px){
    .parent-login-toolbar-left,
    .parent-login-toolbar-right{
        width:100%
    }
    .parent-login-toolbar .btn-ui{
        flex:1 1 auto;
        justify-content:center
    }
}


.parent-login-filter-grid{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:10px;
    margin:14px 0
}
.parent-login-filter-grid label{
    display:block;
    margin-bottom:5px;
    font-size:9px;
    font-weight:800;
    color:var(--text-muted,#64748b)
}
.parent-login-filter-grid .btn-ui{
    width:100%;
    min-height:40px;
    justify-content:center
}
.parent-login-select-all-wrap{
    display:inline-flex;
    align-items:center;
    gap:7px;
    font-size:10px;
    font-weight:800
}
.parent-login-select-all-wrap input{
    width:16px;
    height:16px;
    cursor:pointer
}
.parent-login-confirm-panel{
    display:none;
    margin-top:14px;
    border:1px solid #c7d2fe;
    border-radius:12px;
    overflow:hidden;
    background:#f8faff
}
.parent-login-confirm-panel.show{display:block}
.parent-login-confirm-head{
    padding:12px 14px;
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:10px;
    border-bottom:1px solid #dbe3ff
}
.parent-login-confirm-head strong{display:block;font-size:12px}
.parent-login-confirm-head small{display:block;margin-top:3px;color:#64748b;font-size:9px}
.parent-login-confirm-body{max-height:260px;overflow:auto}
.parent-login-confirm-table{width:100%;min-width:850px;border-collapse:collapse}
.parent-login-confirm-table th,
.parent-login-confirm-table td{
    padding:9px 10px;
    border-bottom:1px solid #e6eaff;
    text-align:left;
    font-size:10px;
    vertical-align:top
}
.parent-login-confirm-table th{
    position:sticky;
    top:0;
    background:#eef2ff;
    z-index:1;
    font-size:9px;
    text-transform:uppercase
}
.parent-login-confirm-actions{
    padding:12px 14px;
    display:flex;
    align-items:center;
    justify-content:flex-end;
    gap:8px;
    border-top:1px solid #dbe3ff
}
.parent-login-filter-hint{
    margin-top:8px;
    font-size:9px;
    color:var(--text-muted,#64748b)
}
@media(max-width:900px){
    .parent-login-filter-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media(max-width:575px){
    .parent-login-filter-grid{grid-template-columns:1fr}
    .parent-login-confirm-actions{display:grid;grid-template-columns:1fr}
    .parent-login-confirm-actions .btn-ui{width:100%;justify-content:center}
}

.student-parent-login-view{
    grid-column:1/-1;
    display:none;
    border:1px solid var(--border-soft,#e7ebf3);
    border-radius:11px;
    overflow:hidden;
    background:var(--card-bg,#fff)
}
.student-parent-login-view.show{display:block}
.student-parent-login-view-head{
    padding:10px 12px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
    border-bottom:1px solid var(--border-soft,#e7ebf3);
    background:rgba(79,70,229,.045)
}
.student-parent-login-view-head strong{font-size:12px}
.student-parent-login-view-body{
    padding:12px;
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:12px
}
.student-parent-login-view-body .full{grid-column:1/-1}
.parent-login-password-field{display:flex;gap:7px;align-items:center}
.parent-login-password-field .form-control{min-width:0}
.parent-login-password-actions{display:flex;gap:7px;flex-wrap:wrap;margin-top:7px}
.parent-login-view-note{font-size:9px;color:var(--text-muted,#64748b);line-height:1.5}
@media(max-width:575px){.student-parent-login-view-body{grid-template-columns:1fr}}

</style>

<!-- Build: 2026-08-10-students-page-import-export-permission-toast-v1 -->
<div class="student-page">
    <div class="page-heading">
        <div>
            <h1 class="page-title">Students Management</h1>
            <p class="page-subtitle">Dashboard › Students Management</p>
        </div>
        <div class="page-actions">
            <button id="addStudentButton" class="btn-ui btn-primary-ui" type="button"><i data-lucide="plus"></i> Add Student</button>

            <button
                id="generateParentLoginButton"
                class="btn-ui"
                type="button"
            >
                <i data-lucide="key-round"></i>
                Generate Parent Login
            </button>

            <button
                id="viewGeneratedParentLoginsButton"
                class="btn-ui"
                type="button"
                style="display:none"
            >
                <i data-lucide="eye"></i>
                View Generated Credentials
            </button>

            <?php if ($canStudentsImport): ?>
                <button
                    id="importButton"
                    class="btn-ui"
                    type="button"
                    data-permission-source="students.php"
                    data-permission-action="import"
                >
                    <i data-lucide="upload"></i>
                    Import
                </button>
            <?php endif; ?>

            <?php if ($canStudentsExport): ?>
                <a
                    id="exportLink"
                    class="btn-ui"
                    data-permission-source="students.php"
                    data-permission-action="export"
                >
                    <i data-lucide="download"></i>
                    Export
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div id="studentMessage" class="alert student-message"></div>

    <section class="student-kpis">
        <article class="student-kpi purple"><span class="student-kpi-icon"><i data-lucide="users-round"></i></span><div><small>Total Students</small><strong id="statTotal">0</strong><div class="trend">Live student strength</div></div></article>
        <article class="student-kpi green"><span class="student-kpi-icon"><i data-lucide="user-round-check"></i></span><div><small>Active Students</small><strong id="statActive">0</strong><div class="trend" id="activePercent">0% of total students</div></div></article>
        <article class="student-kpi pink"><span class="student-kpi-icon"><i data-lucide="user-round-plus"></i></span><div><small>New Admissions</small><strong id="statNew">0</strong><div class="trend">Current academic year</div></div></article>
        <article class="student-kpi orange"><span class="student-kpi-icon"><i data-lucide="user-round-x"></i></span><div><small>Alumni / Inactive</small><strong id="statInactive">0</strong><div class="trend" id="inactivePercent">0% of total students</div></div></article>
    </section>

    <section class="ui-card student-filter-card">
        <input id="searchFilter" class="form-control" placeholder="Search by name, admission no. or parent...">
        <select id="yearFilter" class="form-select"><option value="">Academic Year</option></select>
        <select id="classFilter" class="form-select"><option value="all">All Classes</option></select>
        <select id="sectionFilter" class="form-select"><option value="all">All Sections</option></select>
        <select id="genderFilter" class="form-select"><option value="all">All Genders</option><option value="Male">Male</option><option value="Female">Female</option><option value="Other">Other</option></select>
        <select id="statusFilter" class="form-select"><option value="all">All Status</option></select>
        <button id="resetButton" class="btn-ui" type="button"><i data-lucide="rotate-ccw"></i> Reset</button>
    </section>

    <section class="student-layout">
        <section class="ui-card student-card">
            <div class="student-card-head"><strong id="studentListTitle">Students List</strong><button id="viewAllButton" class="btn btn-link btn-sm p-0" type="button">View All</button></div>
            <div id="classWiseNavigation" class="student-class-nav"><span class="text-muted">Loading classes...</span></div>
            <div class="student-table-wrap">
                <table class="data-table student-table">
                    <thead><tr><th>Admission No</th><th>Student Name</th><th>Class</th><th>Section</th><th>Parent</th><th>Mobile</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody id="studentBody"><tr><td colspan="8" class="student-empty">Loading...</td></tr></tbody>
                </table>
            </div>
            <div class="student-pagination"><small id="recordCount">Loading...</small><div><button class="btn-ui btn-sm" type="button">1</button></div></div>
        </section>

    </section>
</div>

<div class="modal fade" id="studentModal" tabindex="-1">
<div class="modal-dialog modal-xl">
<div class="modal-content">
<form id="studentForm" novalidate>
<div class="modal-header"><div><h5 id="studentModalTitle" class="modal-title">Add Student</h5><small class="text-muted">Student, parent and academic details</small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
<input id="studentId" type="hidden">
<div class="student-grid">
<div><label class="form-label">Academic Year *</label><select id="academicYearId" class="form-select" required></select></div>
<div><label class="form-label">Branch *</label><select id="branchId" class="form-select student-transport-readonly" required disabled></select><small class="text-muted">Current login branch</small></div>
<div><label class="form-label">Class *</label><select id="classId" class="form-select" required></select></div>
<div><label class="form-label">Section *</label><select id="sectionId" class="form-select" required><option value="">Select Section</option></select></div>
<div><label class="form-label">Fee Structure *</label><select id="feeStructureId" class="form-select" disabled required><option value="">Auto-select active structure</option></select><small id="feeStructureHint" class="text-muted">The matching active structure is selected automatically.</small></div><div id="feeStructurePreview" class="student-fee-preview">
<div class="student-fee-preview-head"><strong id="feePreviewTitle">Fee Structure Details</strong><div class="text-end"><small class="text-muted d-block">Total Fee</small><strong id="feePreviewTotal" class="student-fee-preview-total">₹0</strong></div></div>
<div id="feePreviewList" class="student-fee-preview-list"></div>
</div>
<div class="student-transport-title">
<strong>Transport Management</strong>
<small id="transportFeeHint" class="text-muted">No transport fee will be added.</small>
</div>
<div><label class="form-label">Transport Required *</label>
<select id="transportRequired" class="form-select" required>
<option value="0">No</option>
<option value="1">Yes</option>
</select></div>
<div><label class="form-label">Select Route</label>
<select id="transportRouteId" class="form-select" disabled>
<option value="">Select Route</option>
</select></div>
<div><label class="form-label">Select Boarding Stop</label>
<select id="transportStopId" class="form-select" disabled>
<option value="">Select Boarding Stop</option>
</select></div>
<div><label class="form-label">Assigned Vehicle</label>
<input id="transportVehicle" class="form-control student-transport-readonly" readonly value="-"></div>
<div><label class="form-label">Assigned Driver</label>
<input id="transportDriver" class="form-control student-transport-readonly" readonly value="-"></div>
<div><label class="form-label">Transport Fee</label>
<input id="transportFee" class="form-control student-transport-readonly" readonly value="₹0.00">
<input id="transportFeeAmount" type="hidden" value="0"></div>
<div><label class="form-label">Admission Number *</label><input id="admissionNumber" class="form-control" required maxlength="60"></div>
<div><label class="form-label">Roll Number</label><input id="rollNumber" class="form-control" maxlength="40"></div>
<div><label class="form-label">Student Name *</label><input id="studentName" class="form-control" required maxlength="150"></div>
<div><label class="form-label">Date of Birth *</label><input id="dateOfBirth" class="form-control" type="date" required></div>
<div><label class="form-label">Gender *</label><select id="gender" class="form-select" required><option value="">Select</option><option>Male</option><option>Female</option><option>Other</option></select></div>
<div><label class="form-label">Blood Group</label><select id="bloodGroup" class="form-select"><option value="">Not Specified</option><option>A+</option><option>A-</option><option>B+</option><option>B-</option><option>AB+</option><option>AB-</option><option>O+</option><option>O-</option></select></div>
<div><label class="form-label">Admission Date *</label><input id="admissionDate" class="form-control" type="date" required></div>
<div><label class="form-label">Status *</label><select id="studentStatus" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option><option value="tc">TC / Transferred</option><option value="alumni">Alumni</option></select></div>
<div><label class="form-label">Parent / Guardian Name *</label><input id="parentName" class="form-control" required maxlength="150"></div>
<div><label class="form-label">Relationship</label><select id="relationship" class="form-select"><option>Father</option><option>Mother</option><option>Guardian</option></select></div>
<div><label class="form-label">Mobile *</label><input id="mobile" class="form-control" required maxlength="20"></div>
<div><label class="form-label">Email</label><input id="email" class="form-control" type="email" maxlength="190"></div>
<div id="parentLoginRequiredWrap">
    <label class="form-label">Parent Login Required *</label>
    <select id="parentLoginRequired" class="form-select">
        <option value="no" selected>No</option>
        <option value="yes">Yes</option>
    </select>
    <small class="text-muted">
        If Yes is selected for a new student, a unique Parent Username and temporary Password will be generated after saving.
    </small>
</div>
<div id="parentLoginViewWrap" class="student-parent-login-view">
    <div class="student-parent-login-view-head">
        <strong>Parent Login Credentials</strong>
        <small id="parentLoginViewStatus" class="text-muted">Loading...</small>
    </div>
    <div class="student-parent-login-view-body">
        <div>
            <label class="form-label">Parent Username</label>
            <input id="parentUsernameView" class="form-control student-transport-readonly" readonly value="-">
        </div>
        <div>
            <label class="form-label">Parent Password</label>
            <div class="parent-login-password-field">
                <input id="parentPasswordView" class="form-control student-transport-readonly" type="password" readonly value="">
                <button id="parentPasswordToggle" class="btn-ui btn-sm" type="button" style="display:none" title="Show / Hide Password">
                    <i data-lucide="eye"></i>
                </button>
            </div>
            <div class="parent-login-password-actions">
                <button id="parentPasswordResetButton" class="btn-ui btn-sm" type="button" style="display:none">
                    <i data-lucide="refresh-cw"></i> Generate New Password
                </button>
            </div>
        </div>
        <div class="full">
            <small id="parentLoginViewHelp" class="parent-login-view-note"></small>
        </div>
    </div>
</div>
<div class="full"><label class="form-label">Address</label><textarea id="address" class="form-control" rows="2"></textarea></div>
<div class="full"><label class="form-label">Notes</label><textarea id="notes" class="form-control" rows="3"></textarea></div>
</div>
</div>
<div class="modal-footer"><button class="btn-ui" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn-ui btn-primary-ui" type="submit"><i data-lucide="save"></i> Save Student</button></div>
</form>
</div>
</div>
</div>

<div class="modal fade" id="importModal" tabindex="-1" data-bs-backdrop="static">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
<form id="importForm" enctype="multipart/form-data" novalidate>
<div class="modal-header"><div><h5 class="modal-title">Import Students</h5><small class="text-muted">Upload Excel (.xlsx) or CSV files using the provided template.</small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
 <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
  <div><strong>Sample Templates</strong><small class="d-block text-muted">Download, fill the student rows, then upload the file.</small></div>
  <div class="student-template-actions">
   <a id="downloadXlsxTemplate" class="btn-ui btn-sm"><i data-lucide="file-spreadsheet"></i> Excel Template</a>
   <a id="downloadCsvTemplate" class="btn-ui btn-sm"><i data-lucide="file-text"></i> CSV Template</a>
  </div>
 </div>
 <div class="student-import-drop">
  <i data-lucide="sheet" style="width:34px;height:34px"></i>
  <strong class="d-block mt-2">Choose Excel or CSV File</strong>
  <small class="text-muted">Supported: .xlsx and .csv · Maximum 10 MB · Up to 2,000 rows</small>
  <input id="importFile" name="import_file" class="form-control" type="file" accept=".xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
 </div>
 <div class="student-import-options">
  <div>
   <label class="form-label" for="duplicateMode">Duplicate Admission Number</label>
   <select id="duplicateMode" name="duplicate_mode" class="form-select">
    <option value="skip">Skip existing students</option>
    <option value="update">Update existing students</option>
   </select>
  </div>
  <div>
   <label class="form-label">Supported Date Formats</label>
   <input class="form-control" value="YYYY-MM-DD, DD-MM-YYYY, DD/MM/YYYY" readonly>
  </div>
 </div>
 <div class="alert alert-info mt-3 mb-2">
   Required: academic_year_id, admission_number, student_name, date_of_birth, gender, admission_date, parent_name and mobile. Provide either class_id or class_name. Branch is assigned automatically from the current login branch. Fee Structure is auto-selected when fee_structure_id is blank.
  </div>
  <div id="missingColumnsAction" class="student-missing-columns-action">
   <div>
    <strong>Some required columns are missing from the selected file.</strong>
    <small>Complete them once and the values will be mapped to every imported student row.</small>
   </div>
   <button id="missingColumnsButton" class="btn-ui btn-sm" type="button">
    <i data-lucide="columns-3"></i>
    Missing Required Columns
    <span id="missingColumnsCount" class="student-missing-column-count">0</span>
   </button>
  </div>
  <div id="missingColumnsPanel" class="student-missing-columns-panel">
   <div class="student-missing-columns-head">
    <div>
     <strong>Missing Required Columns Import Options</strong>
     <small>Select the missing columns, choose their values, select the XLSX/CSV file and click Import Students.</small>
    </div>
    <button id="closeMissingColumnsButton" class="btn-ui btn-sm" type="button">Close</button>
   </div>
   <div class="student-missing-columns-body">
    <div id="missingColumnsFields" class="student-missing-columns-grid"></div>
    <div class="student-missing-file">
     <strong>Choose XLSX / CSV File</strong>
     <small>You can keep the original file or choose it again here.</small>
     <input id="missingImportFile" class="form-control" type="file" accept=".xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
    </div>
    <div class="alert alert-light border mb-0 py-2 px-3" style="font-size:10px">
     Selected values are used only when the corresponding Excel/CSV column is missing or blank. Existing row values are preserved.
    </div>
   </div>
  </div>
 <div id="importResult" class="alert student-import-result"></div>
 <div id="importDetails" class="student-import-details">
  <div class="student-import-details-head">
   <div>
    <strong id="importDetailsTitle">Import Details</strong>
    <small id="importDetailsSubtitle" class="d-block text-muted">Click a summary card to view students.</small>
   </div>
   <button id="closeImportDetailsButton" class="btn-ui btn-sm" type="button">Close Details</button>
  </div>
  <div id="importDetailsBody" class="student-import-details-body"></div>
 </div>
</div>
<div class="modal-footer"><button class="btn-ui" type="button" data-bs-dismiss="modal">Cancel</button><button id="importSubmitButton" class="btn-ui btn-primary-ui" type="submit"><i data-lucide="upload"></i> Import Students</button></div>
</form>
</div>
</div>
</div>


<!-- Filtered / Selective Parent Login Generation -->
<div class="modal fade" id="parentLoginBulkModal" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-xl modal-dialog-centered">
<div class="modal-content">
<div class="modal-header">
<div>
    <h5 class="modal-title">Generate Parent Login</h5>
    <small class="text-muted">Filter by Academic Year → Class → Section, then select one or more students.</small>
</div>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    <div class="parent-login-bulk-note">
        Existing Parent Login accounts are shown but cannot be selected. Existing usernames and passwords are never overwritten or reset.
    </div>

    <div class="parent-login-filter-grid">
        <div>
            <label>Academic Year *</label>
            <select id="parentLoginAcademicYear" class="form-select">
                <option value="">Select Academic Year</option>
            </select>
        </div>
        <div>
            <label>Class *</label>
            <select id="parentLoginClass" class="form-select" disabled>
                <option value="">Select Class</option>
            </select>
        </div>
        <div>
            <label>Section *</label>
            <select id="parentLoginSection" class="form-select" disabled>
                <option value="">Select Section</option>
            </select>
        </div>
        <div>
            <label>&nbsp;</label>
            <button id="parentLoginLoadStudents" class="btn-ui btn-primary-ui" type="button">
                <i data-lucide="users"></i> Load Students
            </button>
        </div>
    </div>

    <div id="parentLoginFilterHint" class="parent-login-filter-hint">
        Select Academic Year, Class and Section to load students.
    </div>

    <div id="parentLoginListSummary" class="parent-login-bulk-summary"></div>

    <div class="parent-login-toolbar">
        <div class="parent-login-toolbar-left">
            <label class="parent-login-select-all-wrap">
                <input id="parentLoginSelectAll" type="checkbox" disabled>
                <span>Select All Available</span>
            </label>
            <span id="parentLoginSelectionCount" class="parent-login-selection-count">0 selected</span>
        </div>
        <div class="parent-login-toolbar-right">
            <button id="parentLoginClearSelection" class="btn-ui btn-sm" type="button">
                <i data-lucide="square"></i> Clear Selection
            </button>
        </div>
    </div>

    <div id="parentLoginListWrap" class="parent-login-select-table-wrap">
        <table class="parent-login-select-table">
            <thead>
                <tr>
                    <th>Select</th>
                    <th>Student Name</th>
                    <th>Admission Number</th>
                    <th>Academic Year</th>
                    <th>Class</th>
                    <th>Section</th>
                    <th>Parent / Guardian</th>
                    <th>Parent Username</th>
                    <th>Parent Password</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="parentLoginStudentRows">
                <tr>
                    <td colspan="11" class="student-empty">
                        Select Academic Year, Class and Section, then click Load Students.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div id="parentLoginConfirmationBox" class="parent-login-confirm-panel">
        <div class="parent-login-confirm-head">
            <div>
                <strong>Confirm Selected Students</strong>
                <small>Review the students below before generating unique Parent Login credentials.</small>
            </div>
            <span id="parentLoginConfirmCount" class="student-badge active">0 selected</span>
        </div>
        <div class="parent-login-confirm-body">
            <table class="parent-login-confirm-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Admission No.</th>
                        <th>Academic Year</th>
                        <th>Class / Section</th>
                        <th>Parent / Guardian</th>
                        <th>Mobile</th>
                    </tr>
                </thead>
                <tbody id="parentLoginConfirmRows"></tbody>
            </table>
        </div>
        <div class="parent-login-confirm-actions">
            <button id="parentLoginBackToSelection" class="btn-ui" type="button">
                <i data-lucide="arrow-left"></i> Back to Selection
            </button>
            <button id="confirmParentLoginGenerate" class="btn-ui btn-primary-ui" type="button">
                <i data-lucide="key-round"></i> Confirm & Generate
            </button>
        </div>
    </div>
</div>
<div class="modal-footer">
    <button class="btn-ui" type="button" data-bs-dismiss="modal">Cancel</button>
    <button id="generateParentLoginSubmit" class="btn-ui btn-primary-ui" type="button" disabled>
        <i data-lucide="list-checks"></i>
        Review Selected
    </button>
</div>
</div>
</div>
</div>

<!-- Generated Parent Credentials -->
<div class="modal fade" id="parentLoginResultModal" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-xl modal-dialog-centered">
<div class="modal-content">
<div class="modal-header">
<div>
    <h5 class="modal-title">Generated Parent Login Credentials</h5>
    <small class="text-muted">Temporary passwords are shown only for this generation result.</small>
</div>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    <div class="parent-login-bulk-note">
        Print, download, or share these credentials now. Temporary passwords are shown only when generated; the database stores only secure password hashes.
    </div>

    <div id="parentLoginResultSummary" class="parent-login-bulk-summary"></div>

    <div class="parent-login-result-actions my-3">
        <button id="printParentCredentialsButton" class="btn-ui" type="button">
            <i data-lucide="printer"></i> Print
        </button>
        <button id="downloadParentCredentialsButton" class="btn-ui" type="button">
            <i data-lucide="download"></i> Download CSV
        </button>
    </div>

    <div id="parentLoginCredentialsTable"></div>
    <div id="parentLoginOtherResults" class="parent-login-status-list"></div>
</div>
<div class="modal-footer">
    <button class="btn-ui" type="button" data-bs-dismiss="modal">Close</button>
</div>
</div>
</div>
</div>

<script>
(function(){
'use strict';

const apiUrl = new URL('../api/students.php', window.location.href).href;
let csrfToken = <?= json_encode($csrfToken) ?>;
let meta = {};
let permissions = {};
let records = [];
let activeClassKey = '';
let searchTimer = null;
let currentFormRow = null;
let currentFormViewOnly = false;
let detectedMissingColumns = [];
let importResultData = {
    created_rows: [],
    updated_rows: [],
    skipped_rows: [],
    failed_rows: []
};

let parentLoginRows = [];
let generatedParentPasswordMap = {};
let generatedParentLoginData = {
    credentials: [],
    skipped_rows: [],
    failed_rows: [],
    created: 0,
    skipped: 0,
    failed: 0
};

const $ = id => document.getElementById(id);
const esc = value => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');


function csvCell(value) {
    const text = String(value ?? '');
    return `"${text.replace(/"/g, '""')}"`;
}

function parentLoginScope() {
    return 'active';
}

function parentLoginFilters() {
    return {
        academic_year_id: Number($('parentLoginAcademicYear')?.value || 0),
        class_id: Number($('parentLoginClass')?.value || 0),
        section_id: Number($('parentLoginSection')?.value || 0)
    };
}

function parentLoginFiltersComplete() {
    const filters = parentLoginFilters();
    return filters.academic_year_id > 0
        && filters.class_id > 0
        && filters.section_id > 0;
}

function parentLoginSelectedIds() {
    return [...document.querySelectorAll(
        '.js-parent-login-select:checked'
    )].map(input =>
        Number(input.dataset.studentId || 0)
    ).filter(id => id > 0);
}

function parentLoginSelectedRows() {
    const selected = new Set(parentLoginSelectedIds());
    return parentLoginRows.filter(row =>
        selected.has(Number(row.student_id || 0))
    );
}

function hideParentLoginConfirmation() {
    $('parentLoginConfirmationBox')?.classList.remove('show');
    if ($('parentLoginConfirmRows')) {
        $('parentLoginConfirmRows').innerHTML = '';
    }
    if ($('parentLoginConfirmCount')) {
        $('parentLoginConfirmCount').textContent = '0 selected';
    }
}

function updateParentLoginSelectionCount() {
    const selectedIds = parentLoginSelectedIds();
    const count = selectedIds.length;
    const selectable = [...document.querySelectorAll(
        '.js-parent-login-select:not(:disabled)'
    )];
    const checked = selectable.filter(input => input.checked);

    $('parentLoginSelectionCount').textContent =
        `${count} selected`;

    $('generateParentLoginSubmit').disabled = count === 0;

    const selectAll = $('parentLoginSelectAll');
    if (selectAll) {
        selectAll.disabled = selectable.length === 0;
        selectAll.checked =
            selectable.length > 0
            && checked.length === selectable.length;
        selectAll.indeterminate =
            checked.length > 0
            && checked.length < selectable.length;
    }
}

function parentLoginSelectionChanged() {
    hideParentLoginConfirmation();
    updateParentLoginSelectionCount();
}

function clearParentLoginList(
    text = 'Select Academic Year, Class and Section, then click Load Students.'
) {
    parentLoginRows = [];

    $('parentLoginStudentRows').innerHTML = `
        <tr>
            <td colspan="11" class="student-empty">
                ${esc(text)}
            </td>
        </tr>`;

    $('parentLoginListSummary').innerHTML = '';
    $('parentLoginSelectAll').checked = false;
    $('parentLoginSelectAll').indeterminate = false;
    $('parentLoginSelectAll').disabled = true;
    hideParentLoginConfirmation();
    updateParentLoginSelectionCount();
}

function refreshParentLoginClasses() {
    const yearId = Number($('parentLoginAcademicYear').value || 0);

    const rows = [...(meta.classes || [])]
        .filter(row =>
            Number(row.academic_year_id || 0) === yearId
            && String(row.status || 'active').toLowerCase() === 'active'
        )
        .sort((a, b) =>
            Number(a.display_order || 0) - Number(b.display_order || 0)
            || String(a.class_name || '').localeCompare(
                String(b.class_name || ''),
                undefined,
                {numeric: true, sensitivity: 'base'}
            )
        );

    setOptions(
        'parentLoginClass',
        rows,
        yearId
            ? (rows.length ? 'Select Class' : 'No Classes For This Year')
            : 'Select Academic Year First',
        '',
        row => row.class_name
    );

    $('parentLoginClass').disabled = yearId <= 0 || rows.length === 0;
    refreshParentLoginSections();
}

function refreshParentLoginSections() {
    const yearId = Number($('parentLoginAcademicYear').value || 0);
    const classId = Number($('parentLoginClass').value || 0);

    const rows = [...(meta.sections || [])]
        .filter(row =>
            Number(row.academic_year_id || 0) === yearId
            && Number(row.class_id || 0) === classId
            && String(row.status || 'active').toLowerCase() === 'active'
        )
        .sort((a, b) =>
            Number(a.display_order || 0) - Number(b.display_order || 0)
            || String(a.section_name || '').localeCompare(
                String(b.section_name || ''),
                undefined,
                {numeric: true, sensitivity: 'base'}
            )
        );

    setOptions(
        'parentLoginSection',
        rows,
        classId
            ? (rows.length ? 'Select Section' : 'No Sections For This Class')
            : 'Select Class First',
        '',
        row => row.section_name
    );

    $('parentLoginSection').disabled = classId <= 0 || rows.length === 0;
}

function initializeParentLoginFilters() {
    const years = [...(meta.academic_years || [])]
        .filter(row =>
            String(row.status || 'active').toLowerCase() === 'active'
            || Number(row.is_current || 0) === 1
        );

    setOptions(
        'parentLoginAcademicYear',
        years,
        'Select Academic Year',
        meta.current_academic_year_id || '',
        row => row.year_name
    );

    refreshParentLoginClasses();
    clearParentLoginList();

    $('parentLoginFilterHint').textContent =
        'Select Academic Year, Class and Section to load students.';
}

function renderParentLoginConfirmation() {
    const rows = parentLoginSelectedRows();

    if (!rows.length) {
        message('Select at least one student.', false);
        return false;
    }

    $('parentLoginConfirmRows').innerHTML = rows.map(row => `
        <tr>
            <td><strong>${esc(row.student_name || '-')}</strong></td>
            <td>${esc(row.admission_number || '-')}</td>
            <td>${esc(row.academic_year_name || '-')}</td>
            <td>${esc(
                [row.class_name || '', row.section_name || '']
                    .filter(Boolean)
                    .join(' / ') || '-'
            )}</td>
            <td>
                <strong>${esc(row.guardian_name || '-')}</strong>
                ${row.relationship
                    ? `<div class="text-muted">${esc(row.relationship)}</div>`
                    : ''}
            </td>
            <td>${esc(row.guardian_mobile || '-')}</td>
        </tr>
    `).join('');

    $('parentLoginConfirmCount').textContent =
        `${rows.length} selected`;

    $('parentLoginConfirmationBox').classList.add('show');
    $('parentLoginConfirmationBox').scrollIntoView({
        behavior: 'smooth',
        block: 'nearest'
    });
    window.lucide?.createIcons();

    return true;
}

function parentLoginPasswordForRow(row) {
    const generated =
        generatedParentPasswordMap[
            Number(row.student_id || 0)
        ];

    if (generated) {
        return `<span class="parent-login-generated-now">${esc(generated)}</span>`;
    }

    if (row.has_parent_login) {
        return `<span class="parent-login-muted-password">Previously Generated</span>`;
    }

    return `<span class="parent-login-muted-password">Not Generated</span>`;
}

function renderParentLoginRows(data = {}) {
    parentLoginRows = Array.isArray(data.rows)
        ? data.rows
        : [];

    const summary = data.summary || {};

    $('parentLoginListSummary').innerHTML = `
        <div class="parent-login-bulk-stat">
            <strong>${Number(summary.total_students || 0).toLocaleString('en-IN')}</strong>
            <small>Matched Students</small>
        </div>
        <div class="parent-login-bulk-stat">
            <strong>${Number(summary.eligible || 0).toLocaleString('en-IN')}</strong>
            <small>Available to Generate</small>
        </div>
        <div class="parent-login-bulk-stat">
            <strong>${Number(summary.existing || 0).toLocaleString('en-IN')}</strong>
            <small>Already Generated</small>
        </div>
        <div class="parent-login-bulk-stat">
            <strong>${Number(summary.without_guardian || 0).toLocaleString('en-IN')}</strong>
            <small>No Guardian</small>
        </div>`;

    const body = $('parentLoginStudentRows');

    if (!parentLoginRows.length) {
        body.innerHTML = `
            <tr>
                <td colspan="11" class="student-empty">
                    No students found for the selected Academic Year, Class and Section.
                </td>
            </tr>`;
        $('parentLoginSelectAll').disabled = true;
        updateParentLoginSelectionCount();
        return;
    }

    body.innerHTML = parentLoginRows.map(row => {
        const canGenerate =
            Boolean(row.can_generate_parent_login);

        const hasLogin =
            Boolean(row.has_parent_login);

        const status = hasLogin
            ? 'Generated'
            : (
                Number(row.guardian_id || 0) > 0
                    ? 'Not Generated'
                    : 'No Guardian'
            );

        return `
            <tr>
                <td>
                    <input
                        class="form-check-input js-parent-login-select"
                        type="checkbox"
                        data-student-id="${Number(row.student_id || 0)}"
                        ${canGenerate ? '' : 'disabled'}
                    >
                </td>
                <td><strong>${esc(row.student_name || '-')}</strong></td>
                <td>${esc(row.admission_number || '-')}</td>
                <td>${esc(row.academic_year_name || '-')}</td>
                <td>${esc(row.class_name || '-')}</td>
                <td>${esc(row.section_name || '-')}</td>
                <td>
                    <strong>${esc(row.guardian_name || '-')}</strong>
                    ${row.relationship ? `<div class="text-muted">${esc(row.relationship)}</div>` : ''}
                </td>
                <td>
                    ${hasLogin
                        ? `<strong>${esc(row.parent_username || '-')}</strong>`
                        : '<span class="text-muted">Not Generated</span>'}
                </td>
                <td>${parentLoginPasswordForRow(row)}</td>
                <td>
                    <span class="student-badge ${hasLogin ? 'active' : 'inactive'}">
                        ${esc(status)}
                    </span>
                </td>
                <td>
                    ${canGenerate
                        ? `<button
                                class="btn-ui btn-sm js-parent-login-single"
                                type="button"
                                data-student-id="${Number(row.student_id || 0)}"
                            >
                                <i data-lucide="key-round"></i> Generate
                           </button>`
                        : `<span class="text-muted">${hasLogin ? 'Existing' : 'Unavailable'}</span>`}
                </td>
            </tr>`;
    }).join('');

    document.querySelectorAll(
        '.js-parent-login-select'
    ).forEach(input => {
        input.checked = false;
        input.onchange = parentLoginSelectionChanged;
    });

    document.querySelectorAll(
        '.js-parent-login-single'
    ).forEach(button => {
        button.onclick = () => {
            document.querySelectorAll(
                '.js-parent-login-select:not(:disabled)'
            ).forEach(input => {
                input.checked =
                    Number(input.dataset.studentId || 0)
                    === Number(button.dataset.studentId || 0);
            });

            parentLoginSelectionChanged();
            renderParentLoginConfirmation();
        };
    });

    $('parentLoginSelectAll').checked = false;
    $('parentLoginSelectAll').indeterminate = false;
    $('parentLoginSelectAll').disabled =
        !document.querySelector('.js-parent-login-select:not(:disabled)');

    hideParentLoginConfirmation();
    updateParentLoginSelectionCount();
    window.lucide?.createIcons();
}

async function loadParentLoginList() {
    if (!parentLoginFiltersComplete()) {
        clearParentLoginList(
            'Select Academic Year, Class and Section before loading students.'
        );
        message(
            'Select Academic Year, Class and Section first.',
            false
        );
        return;
    }

    $('parentLoginStudentRows').innerHTML = `
        <tr>
            <td colspan="11" class="student-empty">
                Loading students...
            </td>
        </tr>`;

    const filters = parentLoginFilters();

    const result = await request(
        'parent_login_list',
        {
            student_scope: parentLoginScope(),
            academic_year_id: filters.academic_year_id,
            class_id: filters.class_id,
            section_id: filters.section_id
        }
    );

    renderParentLoginRows(result.data || {});

    const year = (meta.academic_years || []).find(row =>
        Number(row.id) === filters.academic_year_id
    );
    const classRow = (meta.classes || []).find(row =>
        Number(row.id) === filters.class_id
    );
    const section = (meta.sections || []).find(row =>
        Number(row.id) === filters.section_id
    );

    $('parentLoginFilterHint').textContent =
        `${year?.year_name || 'Selected Year'} → `
        + `${classRow?.class_name || 'Selected Class'} → `
        + `${section?.section_name || 'Selected Section'} · `
        + `${Number(result.data?.summary?.total_students || 0)} student(s) found.`;
}

function parentCredentialTableHtml(credentials = []) {
    if (!credentials.length) {
        return `
            <div class="student-empty">
                No new Parent credentials were generated in this action.
            </div>`;
    }

    return `
        <div class="parent-login-result-wrap">
        <table class="parent-login-result-table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Admission No</th>
                    <th>Class / Section</th>
                    <th>Parent</th>
                    <th>Mobile</th>
                    <th>Username</th>
                    <th>Temporary Password</th>
                    <th>Share</th>
                </tr>
            </thead>
            <tbody>
                ${credentials.map(row => `
                    <tr>
                        <td><strong>${esc(row.student_name || '-')}</strong></td>
                        <td>${esc(row.admission_number || '-')}</td>
                        <td>${esc(
                            [
                                row.class_name || '',
                                row.section_name
                                    ? `Section ${row.section_name}`
                                    : ''
                            ].filter(Boolean).join(' / ') || '-'
                        )}</td>
                        <td>
                            <strong>${esc(row.guardian_name || '-')}</strong>
                            ${row.relationship ? `<div class="text-muted">${esc(row.relationship)}</div>` : ''}
                        </td>
                        <td>${esc(row.mobile || '-')}</td>
                        <td><strong>${esc(row.username || '-')}</strong></td>
                        <td><span class="parent-login-password">${esc(row.temporary_password || '-')}</span></td>
                        <td>
                            <button
                                class="btn-ui btn-sm js-share-parent-credential"
                                type="button"
                                data-student-id="${Number(row.student_id || 0)}"
                            >
                                <i data-lucide="share-2"></i> Share
                            </button>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
        </div>`;
}

function renderParentLoginOtherResults(data = {}) {
    const rows = [];

    (data.skipped_rows || []).forEach(row => rows.push({
        type: 'Skipped',
        text: `${row.student_name || '-'} — ${row.guardian_name || 'Parent'}: ${row.reason || ''}`
    }));

    (data.failed_rows || []).forEach(row => rows.push({
        type: 'Failed',
        text: `${row.student_name || '-'} — ${row.guardian_name || 'Parent'}: ${row.reason || ''}`
    }));

    $('parentLoginOtherResults').innerHTML = rows.length
        ? rows.map(row => `
            <div class="parent-login-status-item">
                <strong>${esc(row.type)}:</strong>
                ${esc(row.text)}
            </div>
        `).join('')
        : '';
}

function bindParentCredentialShareButtons() {
    document.querySelectorAll(
        '.js-share-parent-credential'
    ).forEach(button => {
        button.onclick = async () => {
            const studentId =
                Number(button.dataset.studentId || 0);

            const credential =
                (generatedParentLoginData.credentials || [])
                    .find(row =>
                        Number(row.student_id || 0)
                        === studentId
                    );

            if (!credential) {
                message(
                    'Generated credential is not available in this session.',
                    false
                );
                return;
            }

            await shareParentCredential(credential);
        };
    });
}

function renderParentLoginResult(
    data = generatedParentLoginData
) {
    generatedParentLoginData = {
        ...generatedParentLoginData,
        ...(data || {})
    };

    const created =
        Number(generatedParentLoginData.created || 0);
    const skipped =
        Number(generatedParentLoginData.skipped || 0);
    const failed =
        Number(generatedParentLoginData.failed || 0);

    $('parentLoginResultSummary').innerHTML = `
        <div class="parent-login-bulk-stat">
            <strong>${created}</strong>
            <small>Generated</small>
        </div>
        <div class="parent-login-bulk-stat">
            <strong>${skipped}</strong>
            <small>Existing / Skipped</small>
        </div>
        <div class="parent-login-bulk-stat">
            <strong>${failed}</strong>
            <small>Failed</small>
        </div>
        <div class="parent-login-bulk-stat">
            <strong>${(generatedParentLoginData.credentials || []).length}</strong>
            <small>Passwords Available Now</small>
        </div>`;

    $('parentLoginCredentialsTable').innerHTML =
        parentCredentialTableHtml(
            generatedParentLoginData.credentials || []
        );

    renderParentLoginOtherResults(
        generatedParentLoginData
    );

    const hasCredentials =
        (generatedParentLoginData.credentials || [])
            .length > 0;

    $('printParentCredentialsButton').disabled =
        !hasCredentials;
    $('downloadParentCredentialsButton').disabled =
        !hasCredentials;
    $('viewGeneratedParentLoginsButton').style.display =
        hasCredentials ? '' : 'none';

    bindParentCredentialShareButtons();
    window.lucide?.createIcons();
}

function downloadParentCredentialsCsv() {
    const credentials =
        generatedParentLoginData.credentials || [];

    if (!credentials.length) {
        message(
            'No generated Parent credentials are available to download.',
            false
        );
        return;
    }

    const rows = [
        [
            'Student',
            'Admission Number',
            'Academic Year',
            'Class',
            'Section',
            'Parent / Guardian',
            'Relationship',
            'Mobile',
            'Email',
            'Parent Username',
            'Parent Password'
        ],
        ...credentials.map(row => [
            row.student_name || '',
            row.admission_number || '',
            row.academic_year || '',
            row.class_name || '',
            row.section_name || '',
            row.guardian_name || '',
            row.relationship || '',
            row.mobile || '',
            row.email || '',
            row.username || '',
            row.temporary_password || ''
        ])
    ];

    const csv =
        '\uFEFF'
        + rows.map(row =>
            row.map(csvCell).join(',')
        ).join('\r\n');

    const blob = new Blob(
        [csv],
        {type: 'text/csv;charset=utf-8'}
    );

    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    const stamp = new Date()
        .toISOString()
        .replace(/[:T]/g, '-')
        .slice(0, 16);

    link.href = url;
    link.download =
        `parent-login-credentials-${stamp}.csv`;

    document.body.appendChild(link);
    link.click();
    link.remove();

    window.setTimeout(
        () => URL.revokeObjectURL(url),
        1000
    );
}

function parentCredentialShareText(row) {
    return [
        'Parent Login Credentials',
        '',
        `Student: ${row.student_name || '-'}`,
        `Admission Number: ${row.admission_number || '-'}`,
        `Class: ${row.class_name || '-'}`,
        `Section: ${row.section_name || '-'}`,
        `Parent / Guardian: ${row.guardian_name || '-'}`,
        `Username: ${row.username || '-'}`,
        `Temporary Password: ${row.temporary_password || '-'}`,
        '',
        'Please change the temporary password after login if the portal provides that option.'
    ].join('\n');
}

async function shareParentCredential(row) {
    const text = parentCredentialShareText(row);

    try {
        if (navigator.share) {
            await navigator.share({
                title: `Parent Login - ${row.student_name || 'Student'}`,
                text
            });
            return;
        }

        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(text);
            message(
                'Parent Login credential copied. You can paste and share it with the parent.',
                true
            );
            return;
        }

        window.prompt(
            'Copy Parent Login credential:',
            text
        );
    } catch (error) {
        if (error?.name !== 'AbortError') {
            message(
                'Unable to share the Parent Login credential.',
                false
            );
        }
    }
}

function printParentCredentials() {
    const credentials =
        generatedParentLoginData.credentials || [];

    if (!credentials.length) {
        message(
            'No generated Parent credentials are available to print.',
            false
        );
        return;
    }

    const printWindow =
        window.open('', '_blank', 'width=1100,height=750');

    if (!printWindow) {
        message(
            'Allow pop-ups to print Parent credentials.',
            false
        );
        return;
    }

    const rows = credentials.map((row, index) => `
        <tr>
            <td>${index + 1}</td>
            <td>${esc(row.student_name || '-')}</td>
            <td>${esc(row.admission_number || '-')}</td>
            <td>${esc(row.class_name || '-')}</td>
            <td>${esc(row.section_name || '-')}</td>
            <td>${esc(row.guardian_name || '-')}</td>
            <td>${esc(row.mobile || '-')}</td>
            <td><strong>${esc(row.username || '-')}</strong></td>
            <td><strong>${esc(row.temporary_password || '-')}</strong></td>
        </tr>
    `).join('');

    printWindow.document.write(`
        <!doctype html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Parent Login Credentials</title>
            <style>
                body{font-family:Arial,sans-serif;padding:22px;color:#111827}
                h2{margin:0 0 5px}
                p{margin:0 0 18px;color:#6b7280;font-size:12px}
                table{width:100%;border-collapse:collapse}
                th,td{border:1px solid #d1d5db;padding:7px;font-size:10px;text-align:left;vertical-align:top}
                th{background:#f3f4f6}
                @media print{body{padding:0}}
            </style>
        </head>
        <body>
            <h2>Parent Login Credentials</h2>
            <p>Generated ${esc(new Date().toLocaleString('en-IN'))}</p>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Admission No</th>
                        <th>Class</th>
                        <th>Section</th>
                        <th>Parent</th>
                        <th>Mobile</th>
                        <th>Username</th>
                        <th>Temporary Password</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
            <script>
                window.onload = () => {
                    window.print();
                };
            <\/script>
        </body>
        </html>
    `);

    printWindow.document.close();
}

async function request(action, data = {}, method = 'GET') {
    let response;

    if (method === 'GET') {
        const url = new URL(apiUrl, window.location.origin);
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
            body: JSON.stringify({action, csrf_token: csrfToken, ...data})
        });
    }

    const text = await response.text();
    let result;
    try {
        result = JSON.parse(text);
    } catch (error) {
        console.error('Students API invalid response:', text);
        throw new Error(`Students API returned HTTP ${response.status}. Invalid server response.`);
    }

    if (!response.ok || !result.success) {
        throw new Error(result.message || 'Request failed.');
    }

    if (result.data?.csrf_token) {
        csrfToken = result.data.csrf_token;
    }
    return result;
}

async function uploadImportFile(file, fallbackValues = {}) {
    const formData = new FormData();
    formData.append('action', 'import_file');
    formData.append('csrf_token', csrfToken);
    formData.append('import_file', file);
    formData.append(
        'duplicate_mode',
        $('duplicateMode')?.value || 'skip'
    );

    Object.entries(fallbackValues || {}).forEach(([column, value]) => {
        if (value !== '' && value !== null && value !== undefined) {
            formData.append(`fallback_${column}`, String(value));
        }
    });

    const response = await fetch(apiUrl, {
        method: 'POST',
        credentials: 'same-origin',
        cache: 'no-store',
        body: formData
    });

    const text = await response.text();
    let result;
    try {
        result = JSON.parse(text);
    } catch (error) {
        throw new Error(`Students API returned HTTP ${response.status}. Invalid import response.`);
    }

    if (result.data?.csrf_token) {
        csrfToken = result.data.csrf_token;
    }

    if (!response.ok || !result.success) {
        const importError = new Error(
            result.message || 'Student import failed.'
        );
        importError.result = result;
        throw importError;
    }

    return result;
}

function importDetailRows(type) {
    const key = `${type}_rows`;
    return Array.isArray(importResultData[key])
        ? importResultData[key]
        : [];
}

function showImportDetails(type) {
    const labels = {
        created: 'Created Students',
        updated: 'Updated Students',
        skipped: 'Skipped Students',
        failed: 'Failed Students'
    };

    const rows = importDetailRows(type);
    const box = $('importDetails');
    const body = $('importDetailsBody');

    document.querySelectorAll('.student-import-card').forEach(card => {
        card.classList.toggle(
            'active',
            card.dataset.type === type
        );
    });

    $('importDetailsTitle').textContent =
        labels[type] || 'Import Details';

    $('importDetailsSubtitle').textContent =
        `${rows.length} record${rows.length === 1 ? '' : 's'}`;

    if (!rows.length) {
        body.innerHTML =
            '<div class="student-import-detail-empty">' +
            'No students are available in this category.' +
            '</div>';
    } else {
        body.innerHTML =
            '<div style="overflow-x:auto">' +
            '<table class="student-import-detail-table">' +
            '<thead><tr>' +
            '<th>Excel Row</th>' +
            '<th>Admission Number</th>' +
            '<th>Student Name</th>' +
            '<th>Purpose / Reason</th>' +
            '<th>View</th>' +
            '</tr></thead><tbody>' +
            rows.map(row => {
                const studentId = Number(row.student_id || 0);
                const viewButton = studentId > 0
                    ? `<button class="btn-ui btn-sm js-import-view-student"
                         type="button"
                         data-id="${studentId}">
                         View Student
                       </button>`
                    : '<span class="text-muted">Not Available</span>';

                return `<tr>
                    <td>${esc(row.row || '-')}</td>
                    <td><strong>${esc(row.admission_number || '-')}</strong></td>
                    <td>${esc(row.student_name || '-')}</td>
                    <td>${esc(row.reason || '-')}</td>
                    <td>${viewButton}</td>
                </tr>`;
            }).join('') +
            '</tbody></table></div>';
    }

    box.classList.add('show');

    body.querySelectorAll('.js-import-view-student').forEach(button => {
        button.onclick = async () => {
            const studentId = Number(button.dataset.id || 0);

            bootstrap.Modal.getInstance(
                $('importModal')
            )?.hide();

            window.setTimeout(() => {
                openForm(studentId, true);
            }, 250);
        };
    });

    box.scrollIntoView({
        behavior: 'smooth',
        block: 'nearest'
    });
}

function missingColumnLabel(column) {
    return ({
        academic_year_id: 'Academic Year',
        admission_date: 'Admission Date',
        admission_number: 'Admission Number',
        student_name: 'Student Name',
        date_of_birth: 'Date of Birth',
        gender: 'Gender',
        parent_name: 'Parent / Guardian Name',
        mobile: 'Mobile Number',
        'class_id or class_name': 'Class ID or Class Name'
    })[column] || column;
}

function resetMissingColumnsFlow() {
    detectedMissingColumns = [];
    $('missingColumnsAction').classList.remove('show');
    $('missingColumnsPanel').classList.remove('show');
    $('missingColumnsCount').textContent = '0';
    $('missingColumnsFields').innerHTML = '';
    $('missingImportFile').value = '';
}

function renderMissingColumnsFlow(columns) {
    detectedMissingColumns = Array.isArray(columns)
        ? [...new Set(columns.map(value => String(value)))]
        : [];

    if (!detectedMissingColumns.length) {
        resetMissingColumnsFlow();
        return;
    }

    const supported = new Set([
        'academic_year_id',
        'admission_date'
    ]);

    const currentAcademicYear = Number(
        meta.current_academic_year_id || 0
    );
    const today = new Date().toISOString().slice(0, 10);

    $('missingColumnsFields').innerHTML = detectedMissingColumns
        .map(column => {
            const label = missingColumnLabel(column);
            const isSupported = supported.has(column);

            let control = '';

            if (column === 'academic_year_id') {
                control = `<select class="form-select" data-missing-value="academic_year_id">
                    <option value="">Select Academic Year</option>
                    ${(meta.academic_years || []).map(row => `
                        <option value="${Number(row.id)}" ${Number(row.id) === currentAcademicYear ? 'selected' : ''}>
                            ${esc(row.year_name || row.id)}
                        </option>
                    `).join('')}
                </select>`;
            } else if (column === 'admission_date') {
                control = `<input class="form-control" data-missing-value="admission_date" type="date" value="${today}">`;
            } else {
                control = `<div class="student-missing-unsupported">
                    This value is student-specific and must exist as a column in the XLSX/CSV file.
                </div>`;
            }

            return `<div class="student-missing-column-row">
                <div class="student-missing-column-title">
                    <input class="form-check-input" type="checkbox"
                           data-missing-check="${esc(column)}"
                           ${isSupported ? 'checked' : 'disabled'}>
                    <label>${esc(label)} <small class="text-muted">(${esc(column)})</small></label>
                </div>
                ${control}
            </div>`;
        })
        .join('');

    $('missingColumnsCount').textContent = String(
        detectedMissingColumns.length
    );
    $('missingColumnsAction').classList.add('show');
    $('missingColumnsPanel').classList.remove('show');
    window.lucide?.createIcons();
}

function collectMissingColumnFallbacks() {
    if (!detectedMissingColumns.length) {
        return {};
    }

    const supported = new Set([
        'academic_year_id',
        'admission_date'
    ]);
    const fallbacks = {};

    for (const column of detectedMissingColumns) {
        if (!supported.has(column)) {
            throw new Error(
                `${missingColumnLabel(column)} must be added to the XLSX/CSV file before import.`
            );
        }

        const checkbox = document.querySelector(
            `[data-missing-check="${CSS.escape(column)}"]`
        );

        if (!checkbox?.checked) {
            throw new Error(
                `Select the missing required column: ${missingColumnLabel(column)}.`
            );
        }

        const input = document.querySelector(
            `[data-missing-value="${CSS.escape(column)}"]`
        );
        const value = String(input?.value || '').trim();

        if (!value) {
            throw new Error(
                `Choose a value for ${missingColumnLabel(column)}.`
            );
        }

        fallbacks[column] = value;
    }

    return fallbacks;
}

function renderImportResult(result, success = true) {
    const box = $('importResult');
    const data = result?.data || {};
    const errors = Array.isArray(data.errors)
        ? data.errors
        : [];

    importResultData = {
        created_rows: Array.isArray(data.created_rows)
            ? data.created_rows
            : [],
        updated_rows: Array.isArray(data.updated_rows)
            ? data.updated_rows
            : [],
        skipped_rows: Array.isArray(data.skipped_rows)
            ? data.skipped_rows
            : [],
        failed_rows: Array.isArray(data.failed_rows)
            ? data.failed_rows
            : errors.map((error, index) => ({
                row: index + 1,
                admission_number: '',
                student_name: '',
                reason: error
            }))
    };

    const counts = {
        created: Number(data.created || 0),
        updated: Number(data.updated || 0),
        skipped: Number(data.skipped || 0),
        failed: Number(data.failed || errors.length || 0)
    };

    box.className =
        `alert student-import-result show ${
            success ? 'alert-success' : 'alert-danger'
        }`;

    message(
        result.message || (
            success
                ? 'Student import completed successfully.'
                : 'Student import failed.'
        ),
        success,
        success
            ? 'Import Completed'
            : 'Import Failed'
    );

    box.innerHTML =
        `<strong>${esc(result.message || '')}</strong>` +
        `<div class="student-import-summary">
            ${Object.entries(counts).map(([type, count]) => `
                <button class="student-import-card"
                        type="button"
                        data-type="${type}">
                    <strong>${count}</strong>
                    <small>${type.charAt(0).toUpperCase() + type.slice(1)}</small>
                    <span class="student-import-view-label">Click to View</span>
                </button>
            `).join('')}
        </div>`;

    $('importDetails').classList.remove('show');
    $('importDetailsBody').innerHTML = '';

    box.querySelectorAll('.student-import-card').forEach(card => {
        card.onclick = () => showImportDetails(card.dataset.type);
    });
}

function message(
    text,
    success = false,
    title = ''
) {
    const cleanMessage =
        String(text || '').trim();

    if (!cleanMessage) {
        return;
    }

    const type =
        success
            ? 'success'
            : 'error';

    /*
     * Existing includes/common-toast.php.
     */
    if (
        typeof window.schoolToast
        === 'function'
    ) {
        window.schoolToast(
            type,
            cleanMessage,
            title || (
                success
                    ? 'Success'
                    : 'Action failed'
            )
        );

        return;
    }

    if (
        typeof window.showToast
        === 'function'
    ) {
        window.showToast(
            type,
            cleanMessage,
            title || (
                success
                    ? 'Success'
                    : 'Action failed'
            )
        );

        return;
    }

    /*
     * Existing inline message is retained only as a fallback.
     */
    const box =
        $('studentMessage');

    if (!box) {
        return;
    }

    box.className =
        'alert student-message show '
        + (
            success
                ? 'alert-success'
                : 'alert-danger'
        );

    box.textContent =
        cleanMessage;

    window.clearTimeout(
        box._timer
    );

    box._timer =
        window.setTimeout(
            () => {
                box.className =
                    'alert student-message';
            },
            6000
        );
}

function setOptions(elementId, rows, placeholder, selectedValue = '', labelCallback = null) {
    const element = $(elementId);
    const selected = String(selectedValue ?? '');
    element.innerHTML = `<option value="">${esc(placeholder)}</option>` + rows.map(row => {
        const label = labelCallback ? labelCallback(row) : (row.name ?? row.label ?? row.id);
        return `<option value="${esc(row.id)}">${esc(label)}</option>`;
    }).join('');
    element.value = selected;
}

function normalizeName(value) {
    return String(value ?? '').trim().toLowerCase();
}

function uniqueByName(rows, key) {
    const seen = new Set();
    return rows.filter(row => {
        const value = normalizeName(row[key]);
        if (!value || seen.has(value)) return false;
        seen.add(value);
        return true;
    });
}

function currentClassRow() {
    const classId = Number($('classId').value || 0);
    const academicYearId = Number($('academicYearId').value || 0);
    const rows = meta.classes || [];

    return rows.find(row =>
        Number(row.id) === classId
        && (!academicYearId || Number(row.academic_year_id) === academicYearId)
    ) || rows.find(row => Number(row.id) === classId) || null;
}

function refreshFormClasses(selectedValue = '') {
    const academicYearId = Number($('academicYearId').value || 0);
    const selectedId = Number(selectedValue || 0);

    const rows = [...(meta.classes || [])]
        .filter(row =>
            Number(row.academic_year_id || 0) === academicYearId
            && (
                String(row.status || 'active').toLowerCase() === 'active'
                || Number(row.id || 0) === selectedId
            )
        )
        .sort((a, b) =>
            Number(a.display_order || 0) - Number(b.display_order || 0)
            || String(a.class_name || '').localeCompare(
                String(b.class_name || ''),
                undefined,
                {numeric: true, sensitivity: 'base'}
            )
        );

    const validSelectedValue = rows.some(
        row => String(row.id) === String(selectedValue || '')
    ) ? String(selectedValue) : '';

    setOptions(
        'classId',
        rows,
        academicYearId
            ? (rows.length ? 'Select Class' : 'No Classes Created For This Year')
            : 'Select Academic Year First',
        validSelectedValue,
        row => row.class_name
    );

    $('classId').disabled = !academicYearId || rows.length === 0;
}

function refreshFormSections(selectedValue = '') {
    const academicYearId = Number($('academicYearId').value || 0);
    const selectedClass = currentClassRow();
    const selectedClassId = Number(selectedClass?.id || 0);
    const selectedId = Number(selectedValue || 0);

    const rows = [...(meta.sections || [])]
        .filter(row =>
            academicYearId > 0
            && selectedClassId > 0
            && Number(row.academic_year_id || 0) === academicYearId
            && Number(row.class_id || 0) === selectedClassId
            && (
                String(row.status || 'active').toLowerCase() === 'active'
                || Number(row.id || 0) === selectedId
            )
        )
        .sort((a, b) =>
            Number(a.display_order || 0) - Number(b.display_order || 0)
            || String(a.section_name || '').localeCompare(
                String(b.section_name || ''),
                undefined,
                {numeric: true, sensitivity: 'base'}
            )
        );

    const validSelectedValue = rows.some(
        row => String(row.id) === String(selectedValue || '')
    ) ? String(selectedValue) : '';

    setOptions(
        'sectionId',
        rows,
        selectedClass
            ? (rows.length ? 'Select Section' : 'No Sections Created For This Class')
            : 'Select Class First',
        validSelectedValue,
        row => {
            const code = String(row.section_code || '').trim();
            return code
                ? `${row.section_name} (${code})`
                : row.section_name;
        }
    );

    $('sectionId').disabled = !selectedClass || rows.length === 0;
    $('sectionId').required = true;
}

function money(value) {
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 2
    }).format(Number(value || 0));
}

function feeFrequencyLabel(value) {
    return ({
        one_time: 'One Time',
        monthly: 'Monthly',
        term: 'Term-wise',
        annual: 'Annual',
        custom: 'Custom'
    })[String(value || '').toLowerCase()] || String(value || 'One Time');
}

function isAdmissionFeeItem(item) {
    const key = normalizeName(
        item?.fee_type_code
        || item?.fee_type_key
        || item?.fee_type_name
        || item?.head_name
        || ''
    );
    return key.includes('admission');
}

function formIsNewAdmission() {
    if (!currentFormRow) {
        return true;
    }
    return Number(currentFormRow.fee_is_new_admission || 0) === 1;
}

function monthCountInclusive(startDate, endDate) {
    const start = new Date(`${startDate}T00:00:00`);
    const end = new Date(`${endDate}T00:00:00`);
    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime()) || start > end) {
        return 0;
    }
    return Math.max(
        0,
        (end.getFullYear() - start.getFullYear()) * 12
        + (end.getMonth() - start.getMonth())
        + 1
    );
}

function feeScheduleStart(structure) {
    let start = String(structure?.start_date || '');
    const assignmentDate = String(
        currentFormRow?.fee_assignment_date
        || $('admissionDate').value
        || start
    );

    if (formIsNewAdmission() && assignmentDate && (!start || assignmentDate > start)) {
        start = assignmentDate;
    }

    return start;
}

function applicableFeeOccurrences(item, structure) {
    const frequency = String(item?.frequency || 'one_time').toLowerCase();
    const configured = Math.max(1, Number(item?.occurrence_count || 1));
    const start = feeScheduleStart(structure);
    const end = String(structure?.end_date || '');

    if (frequency === 'monthly') {
        return Math.min(configured, monthCountInclusive(start, end));
    }

    if (frequency === 'quarterly') return 4;
    if (frequency === 'half_yearly') return 2;
    if (['yearly', 'annual', 'one_time'].includes(frequency)) return 1;
    if (['term', 'custom'].includes(frequency)) return configured;
    return 1;
}

function renderSelectedFeeStructure() {
    const structureId = Number($('feeStructureId').value || 0);
    const structure = (meta.fee_structures || []).find(
        row => Number(row.id || 0) === structureId
    );
    const preview = $('feeStructurePreview');

    if (!structure) {
        preview.classList.remove('show');
        $('feePreviewList').innerHTML = '';
        $('feePreviewTotal').textContent = money(0);
        return;
    }

    /*
     * View mode must show the fee schedule actually assigned in the database,
     * not a fresh monthly calculation from the Fee Structure master.
     * This keeps promoted students, carried-forward dues and fixed Bus Fee
     * identical to Fee Collection.
     */
    const sameAssignedYear = currentFormRow
        && Number(currentFormRow.academic_year_id || 0) === Number($('academicYearId').value || 0);

    if (
        currentFormViewOnly
        && sameAssignedYear
        && Number(currentFormRow.fee_assignment_id || 0) > 0
    ) {
        const assignedRows = [];
        const tuition = Number(currentFormRow.fee_tuition_amount || 0);
        const admission = Number(currentFormRow.fee_admission_amount || 0);
        const other = Number(currentFormRow.fee_other_amount || 0);
        const bus = Number(currentFormRow.transport_fee_amount || 0);
        const oldPending = Number(currentFormRow.previous_year_pending_amount || 0);

        if (tuition > 0) assignedRows.push(`<div class="student-fee-preview-row"><div><strong>Tuition Fee</strong><small class="d-block text-muted">Assigned current-year tuition</small></div><span>Assigned</span><span>1 time</span><span class="amount">${money(tuition)}</span></div>`);
        if (admission > 0) assignedRows.push(`<div class="student-fee-preview-row"><div><strong>Admission Fee</strong><small class="d-block text-muted">Assigned current-year admission fee</small></div><span>Assigned</span><span>1 time</span><span class="amount">${money(admission)}</span></div>`);
        if (other > 0) assignedRows.push(`<div class="student-fee-preview-row"><div><strong>Other Fees</strong><small class="d-block text-muted">Other assigned current-year fees</small></div><span>Assigned</span><span>1 time</span><span class="amount">${money(other)}</span></div>`);
        if (bus > 0) assignedRows.push(`<div class="student-fee-preview-row"><div><strong>Bus Fee</strong><small class="d-block text-muted">${esc(currentFormRow.transport_stop_name || 'Assigned Boarding Stop')}</small></div><span>Fixed</span><span>1 time</span><span class="amount">${money(bus)}</span></div>`);
        if (oldPending > 0) assignedRows.push(`<div class="student-fee-preview-row"><div><strong>Previous / Old Balance</strong><small class="d-block text-muted">Carried forward from the previous academic year</small></div><span>Carry Forward</span><span>Pending</span><span class="amount">${money(oldPending)}</span></div>`);

        $('feePreviewList').innerHTML = assignedRows.length
            ? assignedRows.join('')
            : '<div class="student-empty">No assigned fee items are available for this student.</div>';
        $('feePreviewTotal').textContent = money(
            Number(currentFormRow.fee_display_total_amount || currentFormRow.total_fee_amount || 0)
        );
        preview.classList.add('show');
        window.lucide?.createIcons();
        return;
    }

    const items = Array.isArray(structure.items)
        ? structure.items
        : (Array.isArray(structure.fee_items) ? structure.fee_items : []);

    const activeItems = items.filter(item => {
        const active = String(
            item.item_status || item.status || 'active'
        ).toLowerCase() === 'active';
        if (!active) return false;
        return formIsNewAdmission() || !isAdmissionFeeItem(item);
    });

    $('feePreviewTitle').textContent = structure.structure_name || 'Fee Structure Details';

    let total = 0;
    const feeRows = activeItems.map(item => {
        const amount = Number(item.amount || 0);
        const occurrences = applicableFeeOccurrences(item, structure);
        const applicableAmount = amount * occurrences;
        total += applicableAmount;

        return `<div class="student-fee-preview-row">
            <div><strong>${esc(item.fee_type_name || item.head_name || 'Fee')}</strong><small class="d-block text-muted">${esc(item.description || '')}</small></div>
            <span>${esc(feeFrequencyLabel(item.frequency))}</span>
            <span>${occurrences} time${occurrences === 1 ? '' : 's'}</span>
            <span class="amount ${amount === 0 ? 'student-fee-zero' : ''}">${money(amount)}${occurrences > 1 ? ` × ${occurrences} = ${money(applicableAmount)}` : ''}</span>
        </div>`;
    });

    const transportRequired = $('transportRequired').value === '1';
    const stop = selectedTransportStop();
    const busFee = transportRequired && stop
        ? Number(stop.transport_fee || 0)
        : 0;
    /* Bus Fee is a fixed one-time amount. Never multiply by months. */
    if (busFee > 0) {
        total += busFee;
        feeRows.push(`<div class="student-fee-preview-row">
            <div><strong>Bus Fee</strong><small class="d-block text-muted">${esc(stop?.stop_name || 'Selected Boarding Stop')}</small></div>
            <span>Fixed</span>
            <span>1 time</span>
            <span class="amount">${money(busFee)}</span>
        </div>`);
    }

    const oldPending = currentFormRow
        && Number(currentFormRow.academic_year_id || 0) === Number($('academicYearId').value || 0)
        ? Number(currentFormRow.previous_year_pending_amount || 0)
        : 0;

    if (oldPending > 0) {
        total += oldPending;
        feeRows.push(`<div class="student-fee-preview-row">
            <div><strong>Previous / Old Balance</strong><small class="d-block text-muted">Carried forward from previous academic year</small></div>
            <span>Carry Forward</span>
            <span>Pending</span>
            <span class="amount">${money(oldPending)}</span>
        </div>`);
    }

    $('feePreviewList').innerHTML = feeRows.length
        ? feeRows.join('')
        : '<div class="student-empty">No applicable fee items are configured for this student.</div>';

    $('feePreviewTotal').textContent = money(total);
    preview.classList.add('show');
    window.lucide?.createIcons();
}
function refreshFeeStructures(selectedValue = '') {
    const academicYearId = Number($('academicYearId').value || 0);
    const selectedClass = currentClassRow();
    const classId = Number(selectedClass?.id || 0);

    /*
     * Keep fee assignment in the exact same Academic Year/Class selected on
     * the Student form. Never move the student to another year because an old
     * fee structure happens to exist there.
     */
    const rows = (meta.fee_structures || [])
        .filter(row =>
            String(row.status || '').toLowerCase() === 'active'
            && Number(row.academic_year_id || 0) === academicYearId
            && Number(row.class_id || 0) === classId
        )
        .sort((a, b) => Number(b.id || 0) - Number(a.id || 0));

    let selected = String(selectedValue || '');

    if (
        selected
        && !rows.some(row => String(row.id) === selected)
    ) {
        selected = '';
    }

    if (!selected && rows.length === 1) {
        selected = String(rows[0].id);
    }

    const currentYearName = (
        (meta.academic_years || []).find(
            row => Number(row.id) === academicYearId
        )?.year_name || ''
    );

    const placeholder = rows.length
        ? 'Select Fee Structure'
        : (
            selectedClass
                ? `No Active Fee Structure - ${currentYearName} / ${selectedClass.class_name}`
                : 'Select Class First'
        );

    setOptions(
        'feeStructureId',
        rows,
        placeholder,
        selected,
        row => `${row.structure_name} · ₹${Number(
            row.annual_total || row.total_amount || 0
        ).toLocaleString('en-IN')}`
    );

    $('feeStructureId').disabled = !selectedClass || rows.length === 0;
    $('feeStructureId').required = true;

    renderSelectedFeeStructure();

    if (rows.length) {
        const selectedRow = rows.find(
            row => String(row.id) === String($('feeStructureId').value)
        ) || rows[0];

        $('feeStructureHint').textContent =
            `Loaded ${selectedRow.structure_name}. Annual Fee: ₹${Number(
                selectedRow.annual_total || selectedRow.total_amount || 0
            ).toLocaleString('en-IN')}`;
    } else {
        $('feeStructureHint').textContent = selectedClass
            ? `Create one Active Fee Structure for ${currentYearName} / ${selectedClass.class_name} before saving the student.`
            : 'Select Academic Year and Class to load the Fee Structure.';
    }
}

function activeTransportRoutes() {
    const branchId = Number(meta.current_branch_id || $('branchId').value || 0);

    return (meta.transport_routes || []).filter(row =>
        String(row.status || '').toLowerCase() === 'active'
        && (
            Number(row.branch_id || 0) === 0
            || Number(row.branch_id || 0) === branchId
        )
    );
}

function selectedTransportRoute() {
    const routeId = Number($('transportRouteId').value || 0);

    return activeTransportRoutes().find(
        row => Number(row.id) === routeId
    ) || null;
}

function selectedTransportStop() {
    const routeId = Number($('transportRouteId').value || 0);
    const stopId = Number($('transportStopId').value || 0);

    return (meta.transport_stops || []).find(
        row =>
            Number(row.id) === stopId
            && Number(row.route_id) === routeId
            && String(row.status || '').toLowerCase() === 'active'
    ) || null;
}

function updateTransportDetails() {
    const required = $('transportRequired').value === '1';
    const route = selectedTransportRoute();
    const stop = selectedTransportStop();

    $('transportVehicle').value = required && route
        ? (
            route.vehicle_name
                ? `${route.vehicle_name}${route.vehicle_number ? ` (${route.vehicle_number})` : ''}`
                : 'Not Assigned'
        )
        : '-';

    $('transportDriver').value = required && route
        ? (route.assigned_driver || 'Not Assigned')
        : '-';

    const fee = required && stop
        ? Number(stop.transport_fee || 0)
        : 0;

    $('transportFeeAmount').value = fee.toFixed(2);
    $('transportFee').value = money(fee);

    $('transportFeeHint').textContent = !required
        ? 'No transport fee will be added.'
        : !route
            ? 'Select a route.'
            : !stop
                ? 'Select a boarding stop to load its transport fee.'
                : `${stop.stop_name}: ${money(fee)} will be added automatically to Fee Collection.`;

    renderSelectedFeeStructure();
}

function refreshTransportStops(selectedValue = '') {
    const required = $('transportRequired').value === '1';
    const routeId = Number($('transportRouteId').value || 0);

    const rows = (meta.transport_stops || [])
        .filter(row =>
            Number(row.route_id) === routeId
            && String(row.status || '').toLowerCase() === 'active'
        )
        .sort((a, b) =>
            Number(a.stop_order || 0) - Number(b.stop_order || 0)
            || String(a.stop_name || '').localeCompare(
                String(b.stop_name || '')
            )
        );

    let selected = String(selectedValue || '');

    if (
        selected
        && !rows.some(row => String(row.id) === selected)
    ) {
        selected = '';
    }

    if (!selected && required && rows.length === 1) {
        selected = String(rows[0].id);
    }

    setOptions(
        'transportStopId',
        rows,
        routeId
            ? (
                rows.length
                    ? 'Select Boarding Stop'
                    : 'No Active Stops'
            )
            : 'Select Route First',
        selected,
        row =>
            `${row.stop_name} · ${money(row.transport_fee || 0)}`
    );

    $('transportStopId').disabled =
        !required || routeId <= 0 || rows.length === 0;
    $('transportStopId').required = required;

    updateTransportDetails();
}

function refreshTransportRoutes(
    selectedRouteValue = '',
    selectedStopValue = ''
) {
    const required = $('transportRequired').value === '1';
    const rows = activeTransportRoutes();

    let selected = String(selectedRouteValue || '');

    if (
        selected
        && !rows.some(row => String(row.id) === selected)
    ) {
        selected = '';
    }

    if (!selected && required && rows.length === 1) {
        selected = String(rows[0].id);
    }

    setOptions(
        'transportRouteId',
        rows,
        rows.length ? 'Select Route' : 'No Active Routes',
        selected,
        row =>
            `${row.route_name}${row.route_code ? ` (${row.route_code})` : ''}`
    );

    $('transportRouteId').disabled = !required || rows.length === 0;
    $('transportRouteId').required = required;

    refreshTransportStops(selectedStopValue);
}

function toggleTransport(
    selectedRouteValue = '',
    selectedStopValue = ''
) {
    const required = $('transportRequired').value === '1';

    if (!required) {
        $('transportRouteId').value = '';
        $('transportStopId').value = '';
    }

    refreshTransportRoutes(
        selectedRouteValue,
        selectedStopValue
    );
}

function populateFilterOptions() {
    const yearFilter = $('yearFilter');
    const previousYear = yearFilter.value || '';
    const currentYearId = Number(meta.current_academic_year_id || 0);

    const years = [...(meta.academic_years || [])]
        .sort((a, b) =>
            Number(b.is_current || 0) - Number(a.is_current || 0)
            || String(b.start_date || '').localeCompare(String(a.start_date || ''))
            || Number(b.id || 0) - Number(a.id || 0)
        );

    yearFilter.innerHTML = years.map(row =>
        `<option value="${esc(row.id)}">${esc(row.year_name || row.academic_year_code || row.id)}</option>`
    ).join('');

    const preferredYear = years.some(
        row => String(row.id) === String(previousYear)
    )
        ? String(previousYear)
        : (
            years.some(row => Number(row.id) === currentYearId)
                ? String(currentYearId)
                : String(years[0]?.id || '')
        );

    yearFilter.value = preferredYear;
    const selectedYearId = Number(yearFilter.value || 0);

    /*
     * The main Student List follows the selected Academic Year and the
     * Class Management database only. The API maps Class Management rows to
     * the canonical classes/sections used by student_enrollments.
     */
    const classFilter = $('classFilter');
    const previousClass = classFilter.value || 'all';

    const classRows = [...(meta.classes || [])]
        .filter(row =>
            Number(row.academic_year_id || 0) === selectedYearId
            && String(row.status || 'active').toLowerCase() === 'active'
        )
        .sort((a, b) =>
            Number(a.display_order || 0) - Number(b.display_order || 0)
            || String(a.class_name || '').localeCompare(
                String(b.class_name || ''),
                undefined,
                {numeric: true, sensitivity: 'base'}
            )
        );

    classFilter.innerHTML =
        '<option value="all">All Classes</option>'
        + classRows.map(row =>
            `<option value="${esc(row.id)}">${esc(row.class_name)}</option>`
        ).join('');

    classFilter.value = classRows.some(
        row => String(row.id) === String(previousClass)
    ) ? String(previousClass) : 'all';

    const selectedClassId =
        classFilter.value !== 'all'
            ? Number(classFilter.value || 0)
            : 0;

    const sectionFilter = $('sectionFilter');
    const previousSection = sectionFilter.value || 'all';

    const sectionRows = [...(meta.sections || [])]
        .filter(row =>
            Number(row.academic_year_id || 0) === selectedYearId
            && String(row.status || 'active').toLowerCase() === 'active'
            && (
                selectedClassId <= 0
                || Number(row.class_id || 0) === selectedClassId
            )
        )
        .sort((a, b) =>
            Number(a.display_order || 0) - Number(b.display_order || 0)
            || String(a.class_name_snapshot || '').localeCompare(
                String(b.class_name_snapshot || ''),
                undefined,
                {numeric: true, sensitivity: 'base'}
            )
            || String(a.section_name || '').localeCompare(
                String(b.section_name || ''),
                undefined,
                {numeric: true, sensitivity: 'base'}
            )
        );

    sectionFilter.innerHTML =
        '<option value="all">All Sections</option>'
        + sectionRows.map(row => {
            const classPart = selectedClassId <= 0 && row.class_name_snapshot
                ? `${row.class_name_snapshot} - `
                : '';

            return `<option value="${esc(row.id)}">${esc(
                classPart + row.section_name
            )}</option>`;
        }).join('');

    sectionFilter.value = sectionRows.some(
        row => String(row.id) === String(previousSection)
    ) ? String(previousSection) : 'all';

    const statusFilter = $('statusFilter');
    const currentStatus = statusFilter.value || 'all';
    statusFilter.innerHTML =
        '<option value="all">All Status</option>'
        + (meta.statuses || []).map(status => {
            const label = status === 'tc'
                ? 'TC / Transferred'
                : status.charAt(0).toUpperCase() + status.slice(1);

            return `<option value="${esc(status)}">${esc(label)}</option>`;
        }).join('');
    statusFilter.value = currentStatus;
}
function filters() {
    return {
        search: $('searchFilter').value.trim(),
        academic_year_id: $('yearFilter').value,
        class_id: $('classFilter').value,
        section_id: $('sectionFilter').value,
        gender: $('genderFilter').value,
        status: $('statusFilter').value
    };
}

function renderStats(stats = {}) {
    const total = Number(stats.total || 0);
    const active = Number(stats.active || 0);
    const inactive = Number(stats.inactive || 0);
    $('statTotal').textContent = total.toLocaleString();
    $('statActive').textContent = active.toLocaleString();
    $('statNew').textContent = Number(stats.new_admissions || 0).toLocaleString();
    $('statInactive').textContent = inactive.toLocaleString();
    $('activePercent').textContent = (total ? ((active / total) * 100).toFixed(1) : 0) + '% of total students';
    $('inactivePercent').textContent = (total ? ((inactive / total) * 100).toFixed(1) : 0) + '% of total students';
}

function actionButtons(row) {
    return `<div class="student-actions">
        <button class="student-action js-view" data-id="${row.id}" title="View"><i data-lucide="eye"></i></button>
        ${permissions.edit ? `<button class="student-action js-edit" data-id="${row.id}" title="Edit"><i data-lucide="pencil"></i></button>` : ''}
        ${permissions.delete ? `<button class="student-action js-delete" data-id="${row.id}" title="Archive"><i data-lucide="trash-2"></i></button>` : ''}
    </div>`;
}

function classKey(row) {
    const id = Number(row.class_id || 0);
    return id > 0 ? `id:${id}` : `name:${normalizeName(row.class_name || 'Unassigned')}`;
}

function classGroups() {
    const groups = new Map();
    records.forEach(row => {
        const key = classKey(row);
        if (!groups.has(key)) {
            groups.set(key, {
                key,
                name: row.class_name || 'Unassigned',
                students: []
            });
        }
        groups.get(key).students.push(row);
    });

    return [...groups.values()].sort((a, b) =>
        String(a.name).localeCompare(String(b.name), undefined, {numeric: true, sensitivity: 'base'})
    );
}

function renderClassNavigation(groups) {
    const box = $('classWiseNavigation');

    if (!groups.length) {
        box.innerHTML = '<span class="text-muted">No classes found.</span>';
        activeClassKey = '';
        return;
    }

    if (!groups.some(group => group.key === activeClassKey)) {
        activeClassKey = groups[0].key;
    }

    box.innerHTML = groups.map(group => `
        <button type="button" class="student-class-button${group.key === activeClassKey ? ' active' : ''}" data-class-key="${esc(group.key)}">
            <span>${esc(group.name)}</span>
            <span class="student-class-count">${group.students.length}</span>
        </button>
    `).join('');

    box.querySelectorAll('.student-class-button').forEach(button => {
        button.onclick = () => {
            activeClassKey = button.dataset.classKey || '';
            render();
        };
    });
}

function render() {
    const groups = classGroups();
    renderClassNavigation(groups);
    const activeGroup = groups.find(group => group.key === activeClassKey);
    const visibleRecords = activeGroup?.students || [];

    $('studentBody').innerHTML = visibleRecords.map(row => `<tr>
        <td><strong>${esc(row.admission_number)}</strong></td>
        <td><div class="student-cell"><span class="student-avatar">${esc((row.student_name || '?').charAt(0).toUpperCase())}</span><strong>${esc(row.student_name)}</strong></div></td>
        <td>${esc(row.class_name || '-')}</td>
        <td>${esc(row.section_name || '-')}</td>
        <td>${esc(row.parent_name || '-')}</td>
        <td>${esc(row.mobile || '-')}</td>
        <td><span class="student-badge ${esc(row.status)}">${esc(row.status === 'tc' ? 'TC / Transferred' : row.status)}</span></td>
        <td>${actionButtons(row)}</td>
    </tr>`).join('') || '<tr><td colspan="8" class="student-empty">No students found in this class.</td></tr>';

    const className = activeGroup?.name || 'Students';
    $('studentListTitle').textContent = `${className} Students (${visibleRecords.length})`;
    $('recordCount').textContent = `Showing ${visibleRecords.length} student${visibleRecords.length === 1 ? '' : 's'} in ${className}`;
    bindActions();
    window.lucide?.createIcons();
}

function enableForm(enabled) {
    document.querySelectorAll('#studentForm input,#studentForm select,#studentForm textarea').forEach(element => {
        element.disabled = !enabled;
    });
    const branch = $('branchId');
    if (branch) branch.disabled = true;
    document.querySelector('#studentForm button[type=submit]').style.display = enabled ? '' : 'none';
}


function resetParentLoginView() {
    const wrap = $('parentLoginViewWrap');
    if (!wrap) return;

    wrap.classList.remove('show');
    $('parentUsernameView').value = '-';
    $('parentPasswordView').value = '';
    $('parentPasswordView').type = 'password';
    $('parentLoginViewStatus').textContent = '';
    $('parentLoginViewHelp').textContent = '';
    $('parentPasswordToggle').style.display = 'none';
    $('parentPasswordResetButton').style.display = 'none';
    $('parentPasswordResetButton').dataset.studentId = '';
}

function renderParentLoginViewCredential(credential = {}) {
    const wrap = $('parentLoginViewWrap');
    if (!wrap) return;

    wrap.classList.add('show');

    const hasLogin = Boolean(credential.has_parent_login);
    const passwordAvailable = Boolean(credential.password_available);
    const username = String(credential.username || '');
    const password = String(credential.temporary_password || '');

    $('parentUsernameView').value = hasLogin && username ? username : 'Not Generated';
    $('parentPasswordView').value = passwordAvailable ? password : '';
    $('parentPasswordView').type = 'password';

    $('parentLoginViewStatus').textContent = hasLogin
        ? 'Parent Login Active'
        : 'Not Generated';

    $('parentPasswordToggle').style.display = passwordAvailable ? '' : 'none';
    $('parentPasswordResetButton').style.display = hasLogin ? '' : 'none';

    $('parentLoginViewHelp').textContent = hasLogin
        ? (
            passwordAvailable
                ? `Temporary password available. Generated ${credential.generated_at || 'in this session'}. Use the eye button to show or hide it.`
                : 'For security, existing passwords are stored only as a secure hash and cannot be read back. Use Generate New Password to create and display a new temporary password.'
        )
        : 'Generate Parent Login from the Generate Parent Login option to create credentials for this student.';

    window.lucide?.createIcons();
}

async function loadParentLoginViewCredential(studentId) {
    resetParentLoginView();

    if (!studentId) return;

    const wrap = $('parentLoginViewWrap');
    wrap?.classList.add('show');
    $('parentLoginViewStatus').textContent = 'Loading...';
    $('parentLoginViewHelp').textContent = 'Loading Parent Login details...';

    try {
        const result = await request(
            'parent_login_student_credentials',
            {student_id: Number(studentId)}
        );

        const credential = result.data?.credential || {};
        renderParentLoginViewCredential(credential);
        $('parentPasswordResetButton').dataset.studentId = String(studentId);
    } catch (error) {
        wrap?.classList.add('show');
        $('parentLoginViewStatus').textContent = 'Unable to Load';
        $('parentUsernameView').value = '-';
        $('parentPasswordView').value = '';
        $('parentPasswordToggle').style.display = 'none';
        $('parentPasswordResetButton').style.display = 'none';
        $('parentLoginViewHelp').textContent = error.message;
    }
}

async function resetParentPasswordFromStudentView() {
    const button = $('parentPasswordResetButton');
    const studentId = Number(button?.dataset.studentId || 0);

    if (!studentId) return;

    if (!confirm(
        'Generate a new temporary Parent Login password? The old Parent password will stop working.'
    )) {
        return;
    }

    const originalHtml = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Generating...';

    try {
        const result = await request(
            'parent_login_reset_password',
            {student_id: studentId},
            'POST'
        );

        const credential = result.data?.credential || {};
        renderParentLoginViewCredential(credential);
        button.dataset.studentId = String(studentId);
        message(result.message, true);
    } catch (error) {
        message(error.message, false);
    } finally {
        button.disabled = false;
        button.innerHTML = originalHtml;
        window.lucide?.createIcons();
    }
}

function openForm(row = null, viewOnly = false) {
    currentFormRow = row || null;
    currentFormViewOnly = Boolean(viewOnly);
    resetParentLoginView();
    const form = $('studentForm');
    form.reset();
    form.classList.remove('was-validated');
    enableForm(true);

    $('studentId').value = row?.id || '';
    $('studentModalTitle').textContent = viewOnly ? 'View Student' : (row ? 'Edit Student' : 'Add Student');

    const defaultYear = row?.academic_year_id || meta.current_academic_year_id || '';
    const defaultBranch = meta.current_branch_id || row?.branch_id || '';
    $('academicYearId').value = String(defaultYear);
    $('branchId').value = String(defaultBranch);

    refreshFormClasses(row?.class_id || '');
    $('classId').value = String(row?.class_id || '');
    refreshFormSections(row?.section_id || '');
    $('sectionId').value = String(row?.section_id || '');

    refreshFeeStructures(row?.fee_structure_id || '');
    $('feeStructureId').value = String(row?.fee_structure_id || $('feeStructureId').value || '');

    $('transportRequired').value =
        Number(row?.transport_required || 0) === 1 ? '1' : '0';

    toggleTransport(
        row?.transport_route_id || '',
        row?.transport_stop_id || ''
    );

    const map = {
        admissionNumber: 'admission_number',
        rollNumber: 'roll_number',
        studentName: 'student_name',
        dateOfBirth: 'date_of_birth',
        gender: 'gender',
        bloodGroup: 'blood_group',
        admissionDate: 'admission_date',
        studentStatus: 'status',
        parentName: 'parent_name',
        relationship: 'relationship',
        mobile: 'mobile',
        email: 'email',
        address: 'address',
        notes: 'notes'
    };

    Object.entries(map).forEach(([elementId, key]) => {
        $(elementId).value = row?.[key] ?? '';
    });

    const parentLoginRequiredWrap =
        $('parentLoginRequiredWrap');

    if (parentLoginRequiredWrap) {
        parentLoginRequiredWrap.style.display =
            row ? 'none' : '';
    }

    if ($('parentLoginRequired')) {
        $('parentLoginRequired').value = 'no';
    }

    if (!row) {
        $('studentStatus').value = 'active';
        $('relationship').value = 'Father';
        $('admissionDate').value = new Date().toISOString().slice(0, 10);
        $('transportRequired').value = '0';
        $('parentLoginRequired').value = 'no';
        toggleTransport();
    }

    enableForm(!viewOnly);

    if (viewOnly && row && Number(row.id || 0) > 0) {
        loadParentLoginViewCredential(Number(row.id));
    }

    bootstrap.Modal.getOrCreateInstance($('studentModal')).show();
}

function bindActions() {
    document.querySelectorAll('.js-view').forEach(button => {
        button.onclick = () => openForm(records.find(row => Number(row.id) === Number(button.dataset.id)), true);
    });
    document.querySelectorAll('.js-edit').forEach(button => {
        button.onclick = () => openForm(records.find(row => Number(row.id) === Number(button.dataset.id)), false);
    });
    document.querySelectorAll('.js-delete').forEach(button => {
        button.onclick = async () => {
            if (!confirm('Archive this student?')) return;
            try {
                const result = await request('delete', {id: Number(button.dataset.id)}, 'POST');
                message(result.message, true);
                await load();
            } catch (error) {
                message(error.message, false);
            }
        };
    });
}


$('parentPasswordToggle').onclick = () => {
    const input = $('parentPasswordView');
    const button = $('parentPasswordToggle');

    input.type = input.type === 'password' ? 'text' : 'password';
    button.innerHTML = input.type === 'password'
        ? '<i data-lucide="eye"></i>'
        : '<i data-lucide="eye-off"></i>';
    window.lucide?.createIcons();
};

$('parentPasswordResetButton').onclick = resetParentPasswordFromStudentView;

async function loadMeta() {
    const result = await request('meta');
    meta = result.data.meta || {};
    permissions = result.data.permissions || {};
    csrfToken = result.data.csrf_token || csrfToken;

    setOptions('academicYearId', meta.academic_years || [], 'Select Academic Year', meta.current_academic_year_id || '', row => row.year_name);
    setOptions('branchId', meta.branches || [], 'Current Branch', meta.current_branch_id || '', row => row.branch_name);
    $('branchId').disabled = true;
    refreshFormClasses();
    refreshFormSections();
    refreshFeeStructures();
    refreshTransportRoutes();
    populateFilterOptions();

    const addStudentButton =
        $('addStudentButton');

    const importButton =
        $('importButton');

    const exportLink =
        $('exportLink');

    if (addStudentButton) {
        addStudentButton.style.display =
            permissions.add
                ? ''
                : 'none';
    }

    const generateParentLoginButton =
        $('generateParentLoginButton');

    if (generateParentLoginButton) {
        generateParentLoginButton.style.display =
            permissions.add
                ? ''
                : 'none';
    }

    if (importButton) {
        importButton.style.display =
            permissions.import
                ? ''
                : 'none';
    }

    if (exportLink) {
        exportLink.style.display =
            permissions.export
                ? ''
                : 'none';
    }
}

async function load() {
    const result = await request('list', filters());
    records = result.data.records || [];
    renderStats(result.data.stats || {});
    render();
    const exportLink =
        $('exportLink');

    if (
        exportLink
        && permissions.export
    ) {
        exportLink.href =
            apiUrl
            + '?action=export&format=csv&'
            + new URLSearchParams(
                filters()
            );
    }
}

$('academicYearId').addEventListener('change', () => {
    $('classId').value = '';
    $('sectionId').value = '';
    refreshFormClasses();
    refreshFormSections();
    refreshFeeStructures();
    refreshTransportRoutes();
});

$('classId').addEventListener('change', () => {
    $('sectionId').value = '';
    $('feeStructureId').value = '';
    refreshFormSections();
    refreshFeeStructures();
    refreshTransportRoutes();
});

$('feeStructureId').addEventListener('change', () => {
    renderSelectedFeeStructure();
    const structureId = Number($('feeStructureId').value || 0);
    const structure = (meta.fee_structures || []).find(
        row => Number(row.id || 0) === structureId
    );

    if (!structure) {
        return;
    }

    const yearId = Number(structure.academic_year_id || 0);
    const classRow = (meta.classes || []).find(row =>
        Number(row.id || 0) === Number(structure.class_id || 0)
        && Number(row.academic_year_id || 0) === yearId
    ) || (meta.classes || []).find(row =>
        Number(row.academic_year_id || 0) === yearId
        && normalizeName(row.class_name || '') === normalizeName(structure.class_name || '')
    );

    if (
        yearId > 0
        && classRow
        && (
            Number($('academicYearId').value || 0) !== yearId
            || Number($('classId').value || 0) !== Number(classRow.id)
        )
    ) {
        $('academicYearId').value = String(yearId);
        refreshFormClasses(classRow.id);
        $('classId').value = String(classRow.id);
        refreshFormSections();
        refreshFeeStructures(String(structure.id), false);
    }
});

$('branchId').addEventListener('change', () => {
    $('transportRouteId').value = '';
    $('transportStopId').value = '';
    toggleTransport();
});

$('transportRequired').addEventListener('change', () => {
    $('transportRouteId').value = '';
    $('transportStopId').value = '';
    toggleTransport();
});

$('transportRouteId').addEventListener('change', () => {
    $('transportStopId').value = '';
    refreshTransportStops();
});

$('transportStopId').addEventListener('change', () => {
    updateTransportDetails();
});

$('admissionDate').addEventListener('change', () => {
    renderSelectedFeeStructure();
});

$('studentForm').onsubmit = async event => {
    event.preventDefault();
    const form = event.currentTarget;
    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return;
    }

    const year = $('academicYearId');
    const branch = $('branchId');
    const classSelect = $('classId');
    const section = $('sectionId');

    const data = {
        id: Number($('studentId').value || 0),
        academic_year_id: Number(year.value),
        academic_year_name: year.options[year.selectedIndex]?.text || '',
        branch_id: Number(meta.current_branch_id || branch.value || 0),
        branch_name: branch.options[branch.selectedIndex]?.text || '',
        class_id: Number(classSelect.value),
        class_name: classSelect.options[classSelect.selectedIndex]?.text || '',
        section_id: Number(section.value || 0),
        section_name: section.options[section.selectedIndex]?.text || '',
        fee_structure_id: Number($('feeStructureId').value || 0),
        transport_required:
            Number($('transportRequired').value || 0),
        transport_route_id:
            Number($('transportRouteId').value || 0),
        transport_stop_id:
            Number($('transportStopId').value || 0),
        admission_number: $('admissionNumber').value.trim(),
        roll_number: $('rollNumber').value.trim(),
        student_name: $('studentName').value.trim(),
        date_of_birth: $('dateOfBirth').value,
        gender: $('gender').value,
        blood_group: $('bloodGroup').value,
        admission_date: $('admissionDate').value,
        status: $('studentStatus').value,
        parent_name: $('parentName').value.trim(),
        relationship: $('relationship').value,
        mobile: $('mobile').value.trim(),
        email: $('email').value.trim(),
        address: $('address').value.trim(),
        notes: $('notes').value.trim(),
        parent_login_required:
            Number($('studentId').value || 0) === 0
                ? $('parentLoginRequired').value
                : 'no'
    };

    if (data.academic_year_id <= 0) {
        message('Select an Academic Year.', false);
        return;
    }

    if (data.class_id <= 0) {
        message('Select a Class created for the selected Academic Year.', false);
        return;
    }

    if (data.section_id <= 0) {
        message('Select a Section configured for the selected Class.', false);
        return;
    }

    if (
        data.transport_required === 1
        && data.transport_route_id <= 0
    ) {
        message('Select a Route.', false);
        return;
    }

    if (
        data.transport_required === 1
        && data.transport_stop_id <= 0
    ) {
        message('Select a Boarding Stop.', false);
        return;
    }

    try {
        const result = await request('save', data, 'POST');
        bootstrap.Modal.getInstance($('studentModal'))?.hide();
        message(result.message, true);

        const credential =
            result.data?.parent_login_credential || null;

        if (credential) {
            generatedParentPasswordMap[
                Number(credential.student_id || 0)
            ] = credential.temporary_password || '';

            generatedParentLoginData = {
                credentials: [credential],
                skipped_rows: [],
                failed_rows: [],
                created: 1,
                skipped: 0,
                failed: 0
            };

            renderParentLoginResult(
                generatedParentLoginData
            );

            window.setTimeout(() => {
                bootstrap.Modal
                    .getOrCreateInstance(
                        $('parentLoginResultModal')
                    )
                    .show();
            }, 220);
        }

        await load();
    } catch (error) {
        message(error.message, false);
    }
};

$('importForm').onsubmit = async event => {
    event.preventDefault();

    const file = $('missingImportFile').files?.[0]
        || $('importFile').files?.[0];

    if (!file) {
        renderImportResult({message: 'Choose an Excel or CSV file first.', data: {errors: []}}, false);
        return;
    }

    const extension = String(file.name || '').split('.').pop().toLowerCase();
    if (!['xlsx', 'csv'].includes(extension)) {
        renderImportResult({message: 'Only .xlsx and .csv files are supported.', data: {errors: []}}, false);
        return;
    }
    if (file.size > 10 * 1024 * 1024) {
        renderImportResult({message: 'Import file cannot exceed 10 MB.', data: {errors: []}}, false);
        return;
    }

    let fallbackValues = {};
    try {
        fallbackValues = collectMissingColumnFallbacks();
    } catch (error) {
        renderImportResult({message: error.message, data: {errors: []}}, false);
        return;
    }

    try {
        $('importSubmitButton').disabled = true;
        $('importSubmitButton').innerHTML = '<span class="spinner-border spinner-border-sm"></span> Importing...';
        const result = await uploadImportFile(file, fallbackValues);
        resetMissingColumnsFlow();
        renderImportResult(result, true);
        await load();
    } catch (error) {
        const result = error?.result || null;
        const missingColumns = Array.isArray(result?.data?.missing_columns)
            ? result.data.missing_columns
            : [];

        if (result?.data?.type === 'missing_required_columns' && missingColumns.length) {
            renderImportResult(result, false);
            renderMissingColumnsFlow(missingColumns);
        } else {
            renderImportResult({message: error.message, data: {errors: []}}, false);
        }
    } finally {
        $('importSubmitButton').disabled = false;
        $('importSubmitButton').innerHTML = '<i data-lucide="upload"></i> Import Students';
        window.lucide?.createIcons();
    }
};

$('searchFilter').addEventListener('input', () => {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(load, 300);
});
$('yearFilter').addEventListener('change', () => {
    $('classFilter').value = 'all';
    $('sectionFilter').value = 'all';
    populateFilterOptions();
    load();
});
$('classFilter').addEventListener('change', () => {
    $('sectionFilter').value = 'all';
    populateFilterOptions();
    load();
});

['sectionFilter', 'genderFilter', 'statusFilter'].forEach(
    id => $(id).addEventListener('change', load)
);

$('resetButton').onclick = () => {
    $('searchFilter').value = '';
    $('yearFilter').value = String(meta.current_academic_year_id || '');
    ['classFilter', 'sectionFilter', 'genderFilter', 'statusFilter'].forEach(
        id => $(id).value = 'all'
    );
    populateFilterOptions();
    load();
};
$('missingColumnsButton').onclick = () => {
    $('missingColumnsPanel').classList.toggle('show');
    if ($('missingColumnsPanel').classList.contains('show')) {
        $('missingColumnsPanel').scrollIntoView({
            behavior: 'smooth',
            block: 'nearest'
        });
    }
};

$('closeMissingColumnsButton').onclick = () => {
    $('missingColumnsPanel').classList.remove('show');
};

$('importFile').addEventListener('change', () => {
    resetMissingColumnsFlow();
});

$('closeImportDetailsButton').onclick = () => {
    $('importDetails').classList.remove('show');
    document.querySelectorAll('.student-import-card').forEach(
        card => card.classList.remove('active')
    );
};

const importButton =
    $('importButton');

if (importButton) {
    importButton.onclick = () => {
        if (!permissions.import) {
            message(
                'You do not have Import permission for students.php.',
                false
            );
            return;
        }

        $('importForm').reset();
        resetMissingColumnsFlow();
        $('duplicateMode').value = 'skip';
        $('importResult').className = 'alert student-import-result';
        $('importResult').innerHTML = '';
        $('importDetails').classList.remove('show');
        $('importDetailsBody').innerHTML = '';

        importResultData = {
            created_rows: [],
            updated_rows: [],
            skipped_rows: [],
            failed_rows: []
        };

        $('downloadXlsxTemplate').href =
            `${apiUrl}?action=import_template&format=xlsx`;

        $('downloadCsvTemplate').href =
            `${apiUrl}?action=import_template&format=csv`;

        bootstrap.Modal
            .getOrCreateInstance(
                $('importModal')
            )
            .show();

        window.lucide?.createIcons();
    };
}

$('addStudentButton').onclick = () => {
    if (!permissions.add) {
        message(
            'You do not have permission to add students.',
            false
        );
        return;
    }

    openForm(null, false);
};

$('generateParentLoginButton').onclick = () => {
    if (!permissions.add) {
        message(
            'You do not have permission to generate Parent Login accounts.',
            false
        );
        return;
    }

    initializeParentLoginFilters();

    bootstrap.Modal
        .getOrCreateInstance(
            $('parentLoginBulkModal')
        )
        .show();

    window.lucide?.createIcons();
};

$('parentLoginAcademicYear').onchange = () => {
    refreshParentLoginClasses();
    clearParentLoginList();
    $('parentLoginFilterHint').textContent =
        'Select Class and Section, then click Load Students.';
};

$('parentLoginClass').onchange = () => {
    refreshParentLoginSections();
    clearParentLoginList();
    $('parentLoginFilterHint').textContent =
        'Select Section, then click Load Students.';
};

$('parentLoginSection').onchange = () => {
    clearParentLoginList();
    $('parentLoginFilterHint').textContent =
        parentLoginFiltersComplete()
            ? 'Click Load Students to display only students in the selected Year + Class + Section.'
            : 'Select Academic Year, Class and Section.';
};

$('parentLoginLoadStudents').onclick = async () => {
    const button = $('parentLoginLoadStudents');
    const originalHtml = button.innerHTML;

    button.disabled = true;
    button.innerHTML =
        '<span class="spinner-border spinner-border-sm"></span> Loading...';

    try {
        await loadParentLoginList();
    } catch (error) {
        clearParentLoginList(error.message);
        message(error.message, false);
    } finally {
        button.disabled = false;
        button.innerHTML = originalHtml;
        window.lucide?.createIcons();
    }
};

$('parentLoginSelectAll').onchange = () => {
    const checked = $('parentLoginSelectAll').checked;

    document.querySelectorAll(
        '.js-parent-login-select:not(:disabled)'
    ).forEach(input => {
        input.checked = checked;
    });

    parentLoginSelectionChanged();
};

$('parentLoginClearSelection').onclick = () => {
    document.querySelectorAll(
        '.js-parent-login-select'
    ).forEach(input => {
        input.checked = false;
    });

    parentLoginSelectionChanged();
};

$('generateParentLoginSubmit').onclick = () => {
    renderParentLoginConfirmation();
};

$('parentLoginBackToSelection').onclick = () => {
    hideParentLoginConfirmation();
    $('parentLoginListWrap').scrollIntoView({
        behavior: 'smooth',
        block: 'nearest'
    });
};

$('confirmParentLoginGenerate').onclick = async () => {
    const studentIds = parentLoginSelectedIds();

    if (!studentIds.length) {
        message(
            'Select at least one student.',
            false
        );
        return;
    }

    if (!parentLoginFiltersComplete()) {
        message(
            'Academic Year, Class and Section are required.',
            false
        );
        return;
    }

    const filters = parentLoginFilters();
    const button = $('confirmParentLoginGenerate');
    const originalHtml = button.innerHTML;

    button.disabled = true;
    button.innerHTML =
        '<span class="spinner-border spinner-border-sm"></span> Generating...';

    try {
        const result = await request(
            'parent_login_create_selected',
            {
                student_ids: studentIds,
                academic_year_id: filters.academic_year_id,
                class_id: filters.class_id,
                section_id: filters.section_id
            },
            'POST'
        );

        generatedParentLoginData =
            result.data || {};

        (generatedParentLoginData.credentials || [])
            .forEach(row => {
                generatedParentPasswordMap[
                    Number(row.student_id || 0)
                ] = row.temporary_password || '';
            });

        renderParentLoginResult(
            generatedParentLoginData
        );

        await loadParentLoginList();

        bootstrap.Modal
            .getInstance(
                $('parentLoginBulkModal')
            )
            ?.hide();

        bootstrap.Modal
            .getOrCreateInstance(
                $('parentLoginResultModal')
            )
            .show();

        message(result.message, true);
    } catch (error) {
        message(error.message, false);
    } finally {
        button.disabled = false;
        button.innerHTML = originalHtml;
        updateParentLoginSelectionCount();
        window.lucide?.createIcons();
    }
};

$('viewGeneratedParentLoginsButton').onclick = () => {
    renderParentLoginResult(
        generatedParentLoginData
    );

    bootstrap.Modal
        .getOrCreateInstance(
            $('parentLoginResultModal')
        )
        .show();
};

$('downloadParentCredentialsButton').onclick =
    downloadParentCredentialsCsv;

$('printParentCredentialsButton').onclick =
    printParentCredentials;

$('viewAllButton').onclick = () => {
    $('searchFilter').value = '';
    $('classFilter').value = 'all';
    activeClassKey = '';
    load();
};

(async () => {
    try {
        await loadMeta();
        await load();
        window.lucide?.createIcons();
    } catch (error) {
        message(error.message, false);
    }
})();
})();
</script>
<?php require dirname(__DIR__) . '/includes/layout-end.php'; ?>

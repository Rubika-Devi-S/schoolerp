<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors','0');

/* This endpoint returns only the current tenant sidebar revision. */
$originalScriptName=(string)($_SERVER['SCRIPT_NAME']??'');
$_SERVER['SCRIPT_NAME']='/login.php';
require_once dirname(__DIR__).'/includes/bootstrap.php';
$_SERVER['SCRIPT_NAME']=$originalScriptName;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function schoolSidebarStateOut(bool $success,array $data=[],int $status=200):never
{
    while (ob_get_level()>0) ob_end_clean();
    http_response_code($status);
    echo json_encode(['success'=>$success,'data'=>$data],JSON_UNESCAPED_SLASHES);
    exit;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    schoolSidebarStateOut(false,[],500);
}
if (function_exists('is_logged_in') && !is_logged_in()) {
    schoolSidebarStateOut(false,[],401);
}
$user=function_exists('current_user')?current_user():[];
$tenantId=(int)($user['tenant_id']??$_SESSION['tenant_id']??0);
if ($tenantId<=0) schoolSidebarStateOut(false,[],401);

$version=0;
try {
    if (function_exists('school_table_exists')
        && school_table_exists($pdo,'school_sidebar_versions')) {
        $stmt=$pdo->prepare(
            "SELECT version FROM school_sidebar_versions
             WHERE tenant_id=:tenant_id LIMIT 1"
        );
        $stmt->execute(['tenant_id'=>$tenantId]);
        $version=(int)($stmt->fetchColumn()?:0);
    }
} catch (Throwable $exception) {
    $version=0;
}
schoolSidebarStateOut(true,['version'=>$version]);

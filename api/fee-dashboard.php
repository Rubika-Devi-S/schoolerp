<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors','0');
error_reporting(E_ALL);

require_once dirname(__DIR__).'/includes/bootstrap.php';

function out(bool $ok,string $message='',array $data=[],int $status=200):never{
    while(ob_get_level()>0)ob_end_clean();
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['success'=>$ok,'message'=>$message,'data'=>$data],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function scopeData():array{
    $u=function_exists('current_user')?current_user():[];
    return[
        'tenant_id'=>(int)($u['tenant_id']??$u['school_id']??$_SESSION['tenant_id']??$_SESSION['school_id']??0),
        'branch_id'=>(int)($u['branch_id']??$_SESSION['branch_id']??$_SESSION['default_branch_id']??0)
    ];
}
function nameSql(string $a='s'):string{
    return "TRIM(CONCAT(COALESCE($a.first_name,''),CASE WHEN COALESCE($a.last_name,'')='' THEN '' ELSE CONCAT(' ',$a.last_name) END))";
}
function currentYearId(PDO $pdo,int $tenantId):int{
    foreach(['academic_year_id','selected_academic_year_id','current_academic_year_id'] as $key){
        $id=(int)($_SESSION[$key]??0);
        if($id>0){
            $q=$pdo->prepare("SELECT id FROM academic_years WHERE id=:id AND tenant_id=:t LIMIT 1");
            $q->execute(['id'=>$id,'t'=>$tenantId]);
            if($q->fetchColumn())return $id;
        }
    }
    $q=$pdo->prepare("SELECT id FROM academic_years WHERE tenant_id=:t ORDER BY is_current DESC,start_date DESC,id DESC LIMIT 1");
    $q->execute(['t'=>$tenantId]);
    return(int)$q->fetchColumn();
}

if(!isset($pdo)||!$pdo instanceof PDO)out(false,'Database connection unavailable.',[],500);
if(session_status()!==PHP_SESSION_ACTIVE)session_start();

$s=scopeData();
if($s['tenant_id']<=0)out(false,'School tenant session was not found.',[],401);

$action=strtolower(trim((string)($_GET['action']??'')));

try{
    if($action!=='dashboard')out(false,'Invalid fee dashboard action.',[],400);

    $yearId=currentYearId($pdo,$s['tenant_id']);
    if($yearId<=0)out(false,'No academic year is configured.',[],422);

    $q=$pdo->prepare("SELECT COALESCE(SUM(a.balance_amount),0) pending_fees,COUNT(DISTINCT a.student_id) students_fee_assigned
        FROM student_fee_assignments a
        INNER JOIN students st ON st.id=a.student_id AND st.tenant_id=a.tenant_id
        WHERE a.tenant_id=:t AND a.academic_year_id=:y
          AND (:bs=0 OR st.branch_id=:b)
          AND st.deleted_at IS NULL");
    $q->execute(['t'=>$s['tenant_id'],'y'=>$yearId,'bs'=>$s['branch_id'],'b'=>$s['branch_id']]);
    $stats=$q->fetch(PDO::FETCH_ASSOC)?:['pending_fees'=>0,'students_fee_assigned'=>0];

    $q=$pdo->prepare("SELECT COALESCE(SUM(r.paid_amount),0)
        FROM fee_receipts r
        WHERE r.tenant_id=:t
          AND (:bs=0 OR r.branch_id=:b)
          AND r.payment_status<>'reversed'
          AND EXISTS(
              SELECT 1
              FROM student_fee_assignments a
              WHERE a.tenant_id=r.tenant_id
                AND a.student_id=r.student_id
                AND a.academic_year_id=:y
          )");
    $q->execute(['t'=>$s['tenant_id'],'y'=>$yearId,'bs'=>$s['branch_id'],'b'=>$s['branch_id']]);
    $stats['total_fee_collected']=(float)$q->fetchColumn();

    $q=$pdo->prepare("SELECT COALESCE(SUM(r.paid_amount),0)
        FROM fee_receipts r
        WHERE r.tenant_id=:t
          AND (:bs=0 OR r.branch_id=:b)
          AND DATE(r.receipt_date)=CURDATE()
          AND r.payment_status<>'reversed'
          AND EXISTS(
              SELECT 1
              FROM student_fee_assignments a
              WHERE a.tenant_id=r.tenant_id
                AND a.student_id=r.student_id
                AND a.academic_year_id=:y
          )");
    $q->execute(['t'=>$s['tenant_id'],'y'=>$yearId,'bs'=>$s['branch_id'],'b'=>$s['branch_id']]);
    $stats['today_collection']=(float)$q->fetchColumn();

    $n=nameSql('st');
    $q=$pdo->prepare("SELECT r.receipt_no,r.receipt_date,r.paid_amount,r.payment_status,$n student_name
        FROM fee_receipts r
        INNER JOIN students st ON st.id=r.student_id AND st.tenant_id=r.tenant_id
        WHERE r.tenant_id=:t
          AND (:bs=0 OR r.branch_id=:b)
          AND EXISTS(
              SELECT 1
              FROM student_fee_assignments a
              WHERE a.tenant_id=r.tenant_id
                AND a.student_id=r.student_id
                AND a.academic_year_id=:y
          )
        ORDER BY r.receipt_date DESC,r.id DESC LIMIT 10");
    $q->execute(['t'=>$s['tenant_id'],'y'=>$yearId,'bs'=>$s['branch_id'],'b'=>$s['branch_id']]);
    $recent=$q->fetchAll(PDO::FETCH_ASSOC);

    $q=$pdo->prepare("SELECT payment_status,COALESCE(SUM(paid_amount),0) amount
        FROM fee_receipts
        WHERE tenant_id=:t
          AND (:bs=0 OR branch_id=:b)
          AND EXISTS(
              SELECT 1
              FROM student_fee_assignments a
              WHERE a.tenant_id=fee_receipts.tenant_id
                AND a.student_id=fee_receipts.student_id
                AND a.academic_year_id=:y
          )
        GROUP BY payment_status
        ORDER BY payment_status");
    $q->execute(['t'=>$s['tenant_id'],'y'=>$yearId,'bs'=>$s['branch_id'],'b'=>$s['branch_id']]);

    out(true,'Dashboard loaded.',[
        'stats'=>[
            'total_fee_collected'=>(float)$stats['total_fee_collected'],
            'pending_fees'=>(float)$stats['pending_fees'],
            'today_collection'=>(float)$stats['today_collection'],
            'students_fee_assigned'=>(int)$stats['students_fee_assigned']
        ],
        'recent'=>$recent,
        'summary'=>$q->fetchAll(PDO::FETCH_ASSOC)
    ]);
}catch(Throwable $e){
    error_log('fee-dashboard: '.$e->getMessage());
    $host=strtolower((string)($_SERVER['HTTP_HOST']??''));
    out(false,(str_contains($host,'localhost')||str_contains($host,'127.0.0.1'))?'Fee dashboard request failed: '.$e->getMessage():'Unable to load fee dashboard.',[],500);
}

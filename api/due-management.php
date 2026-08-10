<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors','0');
error_reporting(E_ALL);

require_once dirname(__DIR__).'/includes/bootstrap.php';

function duOut(bool $ok,string $message='',array $data=[],int $status=200):never{
    while(ob_get_level()>0)ob_end_clean();
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control:no-store');
    echo json_encode(['success'=>$ok,'message'=>$message,'data'=>$data],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function duInput():array{$j=json_decode((string)file_get_contents('php://input'),true);return is_array($j)?$j:$_POST;}
function duScope():array{$u=function_exists('current_user')?current_user():[];return['tenant_id'=>(int)($u['tenant_id']??$u['school_id']??$_SESSION['tenant_id']??0),'branch_id'=>(int)($u['branch_id']??$_SESSION['branch_id']??0),'user_id'=>(int)($u['id']??$u['user_id']??$_SESSION['user_id']??0),'role'=>(string)($u['role']??$u['role_name']??$_SESSION['role']??'')];}
function duCsrf(array $i):void{$s=(string)($_SESSION['fee_csrf_token']??'');$r=(string)($i['csrf_token']??'');if($s===''||$r===''||!hash_equals($s,$r))duOut(false,'Invalid or expired CSRF token. Refresh the page.',[],419);}
function duName(string $a='s'):string{return "TRIM(CONCAT(COALESCE($a.first_name,''),CASE WHEN COALESCE($a.last_name,'')='' THEN '' ELSE CONCAT(' ',$a.last_name) END))";}
function duCan(array $s,string $action):bool{
    if(function_exists('has_permission')){try{return has_permission('fee_management',$action);}catch(Throwable $e){}}
    $role=strtolower($s['role']);
    return in_array($role,['super_admin','super admin','admin','school_admin','school admin','accountant','principal'],true);
}
function duEnsure(PDO $pdo):void{
    $pdo->exec("CREATE TABLE IF NOT EXISTS fee_due_followups(
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        tenant_id BIGINT UNSIGNED NOT NULL,
        branch_id BIGINT UNSIGNED NULL,
        assignment_id BIGINT UNSIGNED NOT NULL,
        student_id BIGINT UNSIGNED NOT NULL,
        followup_date DATE NOT NULL,
        followup_mode ENUM('phone','sms','email','whatsapp','meeting') NOT NULL,
        outcome ENUM('promised','unreachable','disputed','partial_commitment','no_response') NOT NULL,
        next_followup_date DATE NULL,
        notes VARCHAR(500) NOT NULL,
        user_id BIGINT UNSIGNED NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(id),
        KEY idx_due_followup(tenant_id,assignment_id,followup_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS fee_due_reminders(
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        tenant_id BIGINT UNSIGNED NOT NULL,
        branch_id BIGINT UNSIGNED NULL,
        assignment_id BIGINT UNSIGNED NOT NULL,
        student_id BIGINT UNSIGNED NOT NULL,
        channel ENUM('sms','email','whatsapp') NOT NULL,
        recipient VARCHAR(190) NULL,
        due_amount DECIMAL(12,2) NOT NULL,
        message_text VARCHAR(1000) NOT NULL,
        send_status ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
        provider_reference VARCHAR(190) NULL,
        failure_reason VARCHAR(500) NULL,
        sent_by BIGINT UNSIGNED NULL,
        sent_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(id),
        KEY idx_due_reminder(tenant_id,assignment_id,created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function duBaseWhere(array $s,array $f,array &$p):array{
    $where=['a.tenant_id=:t','a.balance_amount>0.01','(:bs=0 OR st.branch_id=:b)'];
    $p=['t'=>$s['tenant_id'],'bs'=>$s['branch_id'],'b'=>$s['branch_id']];
    $search=trim((string)($f['search']??''));if($search!==''){$where[]='(st.first_name LIKE :q OR st.last_name LIKE :q OR st.admission_no LIKE :q OR fs.structure_name LIKE :q)';$p['q']='%'.$search.'%';}
    if(!empty($f['academic_year_id'])&&$f['academic_year_id']!=='all'){$where[]='a.academic_year_id=:y';$p['y']=(int)$f['academic_year_id'];}
    if(!empty($f['class_id'])&&$f['class_id']!=='all'){$where[]='e.class_id=:c';$p['c']=(int)$f['class_id'];}
    if(!empty($f['section_id'])&&$f['section_id']!=='all'){$where[]='e.section_id=:sec';$p['sec']=(int)$f['section_id'];}
    if(!empty($f['due_before'])){$where[]='COALESCE((SELECT MIN(fsi.due_date) FROM fee_structure_items fsi WHERE fsi.fee_structure_id=a.fee_structure_id AND fsi.due_date IS NOT NULL),a.assignment_date)<=:db';$p['db']=$f['due_before'];}
    $installment=(string)($f['installment_filter']??'all');
    if($installment==='with_installment')$where[]='(SELECT COUNT(DISTINCT fsi.due_date) FROM fee_structure_items fsi WHERE fsi.fee_structure_id=a.fee_structure_id AND fsi.due_date IS NOT NULL)>1';
    elseif($installment==='without_installment')$where[]='(SELECT COUNT(DISTINCT fsi.due_date) FROM fee_structure_items fsi WHERE fsi.fee_structure_id=a.fee_structure_id AND fsi.due_date IS NOT NULL)<=1';
    $status=(string)($f['due_status']??'all');
    if($status==='overdue')$where[]='COALESCE((SELECT MIN(fsi.due_date) FROM fee_structure_items fsi WHERE fsi.fee_structure_id=a.fee_structure_id AND fsi.due_date IS NOT NULL),a.assignment_date)<CURDATE()';
    elseif($status==='partial')$where[]='a.paid_amount>0';
    elseif($status==='due')$where[]='a.paid_amount<=0';
    return $where;
}
function duRows(PDO $pdo,array $s,array $f):array{
    $p=[];$where=duBaseWhere($s,$f,$p);
    $page=max(1,(int)($f['page']??1));$per=min(100,max(5,(int)($f['per_page']??10)));
    $count=$pdo->prepare("SELECT COUNT(*) FROM student_fee_assignments a INNER JOIN students st ON st.id=a.student_id AND st.tenant_id=a.tenant_id LEFT JOIN student_enrollments e ON e.student_id=a.student_id AND e.academic_year_id=a.academic_year_id AND e.tenant_id=a.tenant_id INNER JOIN fee_structures fs ON fs.id=a.fee_structure_id AND fs.tenant_id=a.tenant_id WHERE ".implode(' AND ',$where));
    $count->execute($p);$total=(int)$count->fetchColumn();$last=max(1,(int)ceil($total/$per));$page=min($page,$last);$offset=($page-1)*$per;
    $name=duName('st');
    $sql="SELECT a.id,a.student_id,a.academic_year_id,a.fee_structure_id,a.gross_amount,a.net_amount,a.paid_amount,a.balance_amount,a.payment_status,
      st.admission_no,st.mobile,st.email,$name student_name,ay.year_name,c.class_name,sec.section_name,fs.structure_name,
      COALESCE((SELECT MIN(fsi.due_date) FROM fee_structure_items fsi WHERE fsi.fee_structure_id=a.fee_structure_id AND fsi.due_date IS NOT NULL),a.assignment_date) due_date,
      (SELECT COUNT(DISTINCT fsi.due_date) FROM fee_structure_items fsi WHERE fsi.fee_structure_id=a.fee_structure_id AND fsi.due_date IS NOT NULL) installment_count
      FROM student_fee_assignments a
      INNER JOIN students st ON st.id=a.student_id AND st.tenant_id=a.tenant_id
      INNER JOIN academic_years ay ON ay.id=a.academic_year_id AND ay.tenant_id=a.tenant_id
      INNER JOIN fee_structures fs ON fs.id=a.fee_structure_id AND fs.tenant_id=a.tenant_id
      LEFT JOIN student_enrollments e ON e.student_id=a.student_id AND e.academic_year_id=a.academic_year_id AND e.tenant_id=a.tenant_id
      LEFT JOIN classes c ON c.id=e.class_id AND c.tenant_id=e.tenant_id
      LEFT JOIN sections sec ON sec.id=e.section_id AND sec.tenant_id=e.tenant_id
      WHERE ".implode(' AND ',$where)." ORDER BY due_date ASC,st.first_name,a.id LIMIT {$per} OFFSET {$offset}";
    $q=$pdo->prepare($sql);$q->execute($p);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as $idx=>&$r){
        $r['row_number']=$offset+$idx+1;
        $r['installment_label']=(int)$r['installment_count']>1?((int)$r['installment_count'].' Installments'):'Single';
        $r['due_status']=!empty($r['due_date'])&&$r['due_date']<date('Y-m-d')?'overdue':((float)$r['paid_amount']>0?'partial':'due');
    }
    return['records'=>$rows,'pagination'=>['total'=>$total,'page'=>$page,'per_page'=>$per,'last_page'=>$last]];
}
function duMeta(PDO $pdo,array $s):array{
    $q=$pdo->prepare("SELECT id,year_name FROM academic_years WHERE tenant_id=:t ORDER BY is_current DESC,start_date DESC,id DESC");$q->execute(['t'=>$s['tenant_id']]);$years=$q->fetchAll(PDO::FETCH_ASSOC);
    $q=$pdo->prepare("SELECT id,class_name,academic_year_id FROM classes WHERE tenant_id=:t AND status='active' ORDER BY display_order,class_name");$q->execute(['t'=>$s['tenant_id']]);$classes=$q->fetchAll(PDO::FETCH_ASSOC);
    $q=$pdo->prepare("SELECT id,section_name,class_id FROM sections WHERE tenant_id=:t AND status='active' ORDER BY section_name");$q->execute(['t'=>$s['tenant_id']]);$sections=$q->fetchAll(PDO::FETCH_ASSOC);
    return compact('years','classes','sections');
}
function duAssignment(PDO $pdo,array $s,int $id):array{
    $x=duRows($pdo,$s,['page'=>1,'per_page'=>100]);foreach($x['records'] as $r)if((int)$r['id']===$id)return$r;
    $q=$pdo->prepare("SELECT id FROM student_fee_assignments WHERE id=:id AND tenant_id=:t");$q->execute(['id'=>$id,'t'=>$s['tenant_id']]);if(!$q->fetchColumn())throw new InvalidArgumentException('Due assignment not found.');
    throw new InvalidArgumentException('Due assignment is not accessible in the current branch.');
}
function duSend(PDO $pdo,array $s,array $assignment,string $channel,string $recipient,string $message):array{
    $status='queued';$reference=null;$failure=null;
    if($channel==='email'&&function_exists('send_email')){
        try{send_email($recipient,'Fee Due Reminder',$message);$status='sent';$reference='EMAIL-'.date('YmdHis');}catch(Throwable $e){$status='failed';$failure=$e->getMessage();}
    }elseif($channel==='sms'&&function_exists('send_sms')){
        try{send_sms($recipient,$message);$status='sent';$reference='SMS-'.date('YmdHis');}catch(Throwable $e){$status='failed';$failure=$e->getMessage();}
    }elseif($channel==='whatsapp'&&function_exists('send_whatsapp')){
        try{send_whatsapp($recipient,$message);$status='sent';$reference='WA-'.date('YmdHis');}catch(Throwable $e){$status='failed';$failure=$e->getMessage();}
    }
    $q=$pdo->prepare("INSERT INTO fee_due_reminders(tenant_id,branch_id,assignment_id,student_id,channel,recipient,due_amount,message_text,send_status,provider_reference,failure_reason,sent_by,sent_at) VALUES(:t,:b,:a,:st,:c,:r,:amt,:m,:s,:ref,:f,:u,:sent)");
    $q->execute(['t'=>$s['tenant_id'],'b'=>$s['branch_id']?:null,'a'=>$assignment['id'],'st'=>$assignment['student_id'],'c'=>$channel,'r'=>$recipient?:null,'amt'=>$assignment['balance_amount'],'m'=>$message,'s'=>$status,'ref'=>$reference,'f'=>$failure,'u'=>$s['user_id'],'sent'=>$status==='sent'?date('Y-m-d H:i:s'):null]);
    return['status'=>$status,'reference'=>$reference,'failure'=>$failure];
}

if(!isset($pdo)||!$pdo instanceof PDO)duOut(false,'Database connection unavailable.',[],500);
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
if(empty($_SESSION['fee_csrf_token']))$_SESSION['fee_csrf_token']=bin2hex(random_bytes(32));
$s=duScope();if($s['tenant_id']<=0||$s['user_id']<=0)duOut(false,'Tenant or user session is missing.',[],401);
duEnsure($pdo);
$i=duInput();$action=strtolower(trim((string)($i['action']??$_GET['action']??'')));

try{
    if($action==='list'){
        $x=duRows($pdo,$s,$_GET);
        $name=duName('st');
        try{$q=$pdo->prepare("SELECT f.*,st.admission_no,$name student_name,fs.structure_name,COALESCE(u.name,u.username,u.email) user_name FROM fee_due_followups f INNER JOIN students st ON st.id=f.student_id INNER JOIN student_fee_assignments a ON a.id=f.assignment_id INNER JOIN fee_structures fs ON fs.id=a.fee_structure_id LEFT JOIN users u ON u.id=f.user_id WHERE f.tenant_id=:t ORDER BY f.id DESC LIMIT 100");$q->execute(['t'=>$s['tenant_id']]);$followups=$q->fetchAll(PDO::FETCH_ASSOC);}catch(Throwable $e){$q=$pdo->prepare("SELECT f.*,st.admission_no,$name student_name,fs.structure_name,NULL user_name FROM fee_due_followups f INNER JOIN students st ON st.id=f.student_id INNER JOIN student_fee_assignments a ON a.id=f.assignment_id INNER JOIN fee_structures fs ON fs.id=a.fee_structure_id WHERE f.tenant_id=:t ORDER BY f.id DESC LIMIT 100");$q->execute(['t'=>$s['tenant_id']]);$followups=$q->fetchAll(PDO::FETCH_ASSOC);}
        $q=$pdo->prepare("SELECT r.*,$name student_name FROM fee_due_reminders r INNER JOIN students st ON st.id=r.student_id WHERE r.tenant_id=:t ORDER BY r.id DESC LIMIT 100");$q->execute(['t'=>$s['tenant_id']]);$reminders=$q->fetchAll(PDO::FETCH_ASSOC);
        $q=$pdo->prepare("SELECT COALESCE(SUM(a.balance_amount),0) pending_amount,COUNT(DISTINCT a.student_id) students_with_due,COUNT(DISTINCT CASE WHEN COALESCE((SELECT MIN(fsi.due_date) FROM fee_structure_items fsi WHERE fsi.fee_structure_id=a.fee_structure_id AND fsi.due_date IS NOT NULL),a.assignment_date)<CURDATE() THEN a.student_id END) overdue_students FROM student_fee_assignments a INNER JOIN students st ON st.id=a.student_id AND st.tenant_id=a.tenant_id WHERE a.tenant_id=:t AND a.balance_amount>0.01 AND (:bs=0 OR st.branch_id=:b)");
        $q->execute(['t'=>$s['tenant_id'],'bs'=>$s['branch_id'],'b'=>$s['branch_id']]);$stats=$q->fetch(PDO::FETCH_ASSOC)?:[];
        $q=$pdo->prepare("SELECT COUNT(*) FROM fee_due_reminders WHERE tenant_id=:t AND DATE(created_at)=CURDATE()");$q->execute(['t'=>$s['tenant_id']]);$stats['reminders_sent']=(int)$q->fetchColumn();
        duOut(true,'Due management loaded.',['records'=>$x['records'],'pagination'=>$x['pagination'],'followups'=>$followups,'reminders'=>$reminders,'meta'=>duMeta($pdo,$s),'stats'=>['pending_amount'=>(float)($stats['pending_amount']??0),'students_with_due'=>(int)($stats['students_with_due']??0),'overdue_students'=>(int)($stats['overdue_students']??0),'reminders_sent'=>(int)($stats['reminders_sent']??0)],'permissions'=>['remind'=>duCan($s,'add'),'followup'=>duCan($s,'add')],'csrf_token'=>$_SESSION['fee_csrf_token']]);
    }
    if($action==='detail'){
        $id=(int)($_GET['id']??0);$record=duAssignment($pdo,$s,$id);$timeline=[];
        $q=$pdo->prepare("SELECT 'installment' timeline_type,due_date timeline_date,CONCAT(fh.head_name,' installment') description,fsi.amount,'due' status FROM fee_structure_items fsi INNER JOIN fee_heads fh ON fh.id=fsi.fee_head_id WHERE fsi.fee_structure_id=:fs ORDER BY due_date,fh.head_name");$q->execute(['fs'=>$record['fee_structure_id']]);$timeline=array_merge($timeline,$q->fetchAll(PDO::FETCH_ASSOC));
        $q=$pdo->prepare("SELECT 'payment' timeline_type,DATE(r.receipt_date) timeline_date,CONCAT('Receipt ',r.receipt_no) description,r.paid_amount amount,r.payment_status status FROM fee_receipts r WHERE r.tenant_id=:t AND r.student_id=:st AND r.academic_year_id=:y ORDER BY r.receipt_date");$q->execute(['t'=>$s['tenant_id'],'st'=>$record['student_id'],'y'=>$record['academic_year_id']]);$timeline=array_merge($timeline,$q->fetchAll(PDO::FETCH_ASSOC));
        usort($timeline,fn($a,$b)=>strcmp((string)$a['timeline_date'],(string)$b['timeline_date']));
        duOut(true,'Due details loaded.',['record'=>$record,'timeline'=>$timeline]);
    }
    if($action==='save_followup'){
        duCsrf($i);if(!duCan($s,'add'))duOut(false,'You do not have permission to add follow-ups.',[],403);
        $assignment=duAssignment($pdo,$s,(int)($i['assignment_id']??0));$date=(string)($i['followup_date']??'');$mode=(string)($i['followup_mode']??'phone');$outcome=(string)($i['outcome']??'promised');$next=trim((string)($i['next_followup_date']??''));$notes=trim((string)($i['notes']??''));
        if($date===''||$notes==='')throw new InvalidArgumentException('Follow-up date and notes are required.');if($next!==''&&$next<$date)throw new InvalidArgumentException('Next follow-up cannot be before the follow-up date.');
        $q=$pdo->prepare("INSERT INTO fee_due_followups(tenant_id,branch_id,assignment_id,student_id,followup_date,followup_mode,outcome,next_followup_date,notes,user_id) VALUES(:t,:b,:a,:st,:d,:m,:o,:n,:notes,:u)");
        $q->execute(['t'=>$s['tenant_id'],'b'=>$s['branch_id']?:null,'a'=>$assignment['id'],'st'=>$assignment['student_id'],'d'=>$date,'m'=>$mode,'o'=>$outcome,'n'=>$next?:null,'notes'=>$notes,'u'=>$s['user_id']]);duOut(true,'Payment follow-up saved successfully.');
    }
    if($action==='send_reminder'){
        duCsrf($i);if(!duCan($s,'add'))duOut(false,'You do not have permission to send reminders.',[],403);
        $assignment=duAssignment($pdo,$s,(int)($i['assignment_id']??0));$channel=(string)($i['channel']??'sms');$recipient=trim((string)($i['recipient']??''));$message=trim((string)($i['message_text']??''));
        if(!in_array($channel,['sms','email','whatsapp'],true)||$message==='')throw new InvalidArgumentException('Valid reminder channel and message are required.');
        if($recipient==='')$recipient=$channel==='email'?(string)($assignment['email']??''):(string)($assignment['mobile']??'');
        if($recipient==='')throw new InvalidArgumentException('Recipient contact is unavailable.');
        $result=duSend($pdo,$s,$assignment,$channel,$recipient,$message);
        duOut(true,$result['status']==='sent'?'Reminder sent successfully.':'Reminder queued because no active provider integration is available.',['send_status'=>$result['status']]);
    }
    if($action==='bulk_reminder'){
        duCsrf($i);if(!duCan($s,'add'))duOut(false,'You do not have permission to send reminders.',[],403);
        $filters=['academic_year_id'=>$i['academic_year_id']??'all','class_id'=>$i['class_id']??'all','section_id'=>$i['section_id']??'all','due_before'=>$i['due_before']??'','due_status'=>'overdue','page'=>1,'per_page'=>100];
        $records=duRows($pdo,$s,$filters)['records'];$count=0;
        foreach($records as $assignment){
            $recipient=(string)($assignment['mobile']??'');if($recipient==='')continue;
            $message='Dear '.$assignment['student_name'].', fee balance ₹'.number_format((float)$assignment['balance_amount'],2).' is overdue. Please make payment at the earliest.';
            duSend($pdo,$s,$assignment,'sms',$recipient,$message);$count++;
        }
        duOut(true,$count.' reminder(s) queued or sent successfully.');
    }
    if($action==='export'){
        $rows=duRows($pdo,$s,array_merge($_GET,['page'=>1,'per_page'=>100]))['records'];
        while(ob_get_level()>0)ob_end_clean();header('Content-Type:text/csv; charset=utf-8');header('Content-Disposition:attachment; filename="due-report-'.date('Ymd-His').'.csv"');
        $f=fopen('php://output','wb');fputcsv($f,['Student','Admission No','Academic Year','Class','Section','Fee Structure','Installment','Due Date','Total Fee','Paid','Pending','Status']);
        foreach($rows as $r)fputcsv($f,[$r['student_name'],$r['admission_no'],$r['year_name'],$r['class_name'],$r['section_name'],$r['structure_name'],$r['installment_label'],$r['due_date'],$r['net_amount'],$r['paid_amount'],$r['balance_amount'],$r['due_status']]);
        fclose($f);exit;
    }
    duOut(false,'Invalid Due Management action.',[],400);
}catch(InvalidArgumentException $e){if($pdo->inTransaction())$pdo->rollBack();duOut(false,$e->getMessage(),[],422);}
catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('due-management.php: '.$e->getMessage());$h=strtolower((string)($_SERVER['HTTP_HOST']??''));duOut(false,(str_contains($h,'localhost')||str_contains($h,'127.0.0.1'))?'Due request failed: '.$e->getMessage():'Unable to complete the due request.',[],500);}

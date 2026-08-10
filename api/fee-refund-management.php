<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors','0');
error_reporting(E_ALL);

require_once dirname(__DIR__).'/includes/bootstrap.php';

function rfOut(bool $ok,string $message='',array $data=[],int $status=200):never{
    while(ob_get_level()>0)ob_end_clean();
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control:no-store');
    echo json_encode(['success'=>$ok,'message'=>$message,'data'=>$data],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function rfInput():array{$j=json_decode((string)file_get_contents('php://input'),true);return is_array($j)?$j:$_POST;}
function rfScope():array{$u=function_exists('current_user')?current_user():[];return['tenant_id'=>(int)($u['tenant_id']??$u['school_id']??$_SESSION['tenant_id']??0),'branch_id'=>(int)($u['branch_id']??$_SESSION['branch_id']??0),'user_id'=>(int)($u['id']??$u['user_id']??$_SESSION['user_id']??0),'role'=>(string)($u['role']??$u['role_name']??$_SESSION['role']??'')];}
function rfCsrf(array $i):void{$s=(string)($_SESSION['fee_csrf_token']??'');$r=(string)($i['csrf_token']??'');if($s===''||$r===''||!hash_equals($s,$r))rfOut(false,'Invalid or expired CSRF token. Refresh the page.',[],419);}
function rfName(string $a='s'):string{return "TRIM(CONCAT(COALESCE($a.first_name,''),CASE WHEN COALESCE($a.last_name,'')='' THEN '' ELSE CONCAT(' ',$a.last_name) END))";}
function rfCan(array $s,string $action):bool{
    if(function_exists('has_permission')){try{return has_permission('fee_management',$action);}catch(Throwable $e){}}
    $role=strtolower($s['role']);
    if(in_array($role,['super_admin','super admin','admin','school_admin','school admin'],true))return true;
    if($action==='approve')return in_array($role,['principal','accountant'],true);
    if($action==='complete')return in_array($role,['accountant'],true);
    return false;
}
function rfEnsure(PDO $pdo):void{
    $pdo->exec("CREATE TABLE IF NOT EXISTS fee_refunds(
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        tenant_id BIGINT UNSIGNED NOT NULL,
        branch_id BIGINT UNSIGNED NULL,
        refund_no VARCHAR(60) NOT NULL,
        student_id BIGINT UNSIGNED NOT NULL,
        academic_year_id BIGINT UNSIGNED NOT NULL,
        receipt_id BIGINT UNSIGNED NOT NULL,
        refund_type ENUM('full','partial','excess','cancellation') NOT NULL DEFAULT 'partial',
        refund_amount DECIMAL(12,2) NOT NULL,
        request_date DATE NOT NULL,
        refund_reason VARCHAR(500) NOT NULL,
        refund_status ENUM('pending','approved','rejected','completed') NOT NULL DEFAULT 'pending',
        approval_remarks VARCHAR(500) NULL,
        approved_by BIGINT UNSIGNED NULL,
        approved_at DATETIME NULL,
        payment_method_id BIGINT UNSIGNED NULL,
        refund_date DATE NULL,
        transaction_reference VARCHAR(120) NULL,
        completion_remarks VARCHAR(500) NULL,
        completed_by BIGINT UNSIGNED NULL,
        completed_at DATETIME NULL,
        created_by BIGINT UNSIGNED NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY(id),
        UNIQUE KEY uq_refund_no(tenant_id,refund_no),
        KEY idx_refund_filter(tenant_id,academic_year_id,refund_status),
        KEY idx_refund_receipt(tenant_id,receipt_id),
        KEY idx_refund_student(tenant_id,student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS fee_refund_history(
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        tenant_id BIGINT UNSIGNED NOT NULL,
        branch_id BIGINT UNSIGNED NULL,
        refund_id BIGINT UNSIGNED NULL,
        action_name VARCHAR(60) NOT NULL,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        remarks VARCHAR(500) NULL,
        user_id BIGINT UNSIGNED NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(id),
        KEY idx_refund_history(tenant_id,created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS fee_refund_account_entries(
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        tenant_id BIGINT UNSIGNED NOT NULL,
        branch_id BIGINT UNSIGNED NULL,
        refund_id BIGINT UNSIGNED NOT NULL,
        entry_date DATE NOT NULL,
        entry_type ENUM('refund_payable','refund_paid') NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        payment_method_id BIGINT UNSIGNED NULL,
        reference_no VARCHAR(120) NULL,
        remarks VARCHAR(500) NULL,
        created_by BIGINT UNSIGNED NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(id),
        KEY idx_refund_accounts(tenant_id,entry_date,entry_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function rfHistory(PDO $pdo,array $s,?int $id,string $action,float $amount,string $remarks):void{
    $q=$pdo->prepare("INSERT INTO fee_refund_history(tenant_id,branch_id,refund_id,action_name,amount,remarks,user_id) VALUES(:t,:b,:r,:a,:m,:remarks,:u)");
    $q->execute(['t'=>$s['tenant_id'],'b'=>$s['branch_id']?:null,'r'=>$id,'a'=>$action,'m'=>$amount,'remarks'=>$remarks?:null,'u'=>$s['user_id']?:null]);
}
function rfNumber(PDO $pdo,int $tenant):string{
    for($i=0;$i<20;$i++){
        $no='REF-'.date('Ymd').'-'.str_pad((string)random_int(1,999999),6,'0',STR_PAD_LEFT);
        $q=$pdo->prepare("SELECT COUNT(*) FROM fee_refunds WHERE tenant_id=:t AND refund_no=:n");$q->execute(['t'=>$tenant,'n'=>$no]);
        if(!(int)$q->fetchColumn())return $no;
    }
    throw new RuntimeException('Unable to generate refund number.');
}
function rfReceiptAvailable(PDO $pdo,int $tenant,int $receiptId,int $excludeRefundId=0):array{
    $q=$pdo->prepare("SELECT r.*,COALESCE((SELECT SUM(fr.refund_amount) FROM fee_refunds fr WHERE fr.tenant_id=r.tenant_id AND fr.receipt_id=r.id AND fr.refund_status IN('approved','completed') AND fr.id<>:ex),0) refunded_amount FROM fee_receipts r WHERE r.id=:id AND r.tenant_id=:t AND r.payment_status<>'reversed'");
    $q->execute(['ex'=>$excludeRefundId,'id'=>$receiptId,'t'=>$tenant]);$r=$q->fetch(PDO::FETCH_ASSOC);
    if(!$r)throw new InvalidArgumentException('Paid receipt not found or already reversed.');
    $r['available_refund']=max(0,round((float)$r['paid_amount']-(float)$r['refunded_amount'],2));
    return $r;
}
function rfMeta(PDO $pdo,array $s):array{
    $q=$pdo->prepare("SELECT id,year_name FROM academic_years WHERE tenant_id=:t ORDER BY is_current DESC,start_date DESC,id DESC");$q->execute(['t'=>$s['tenant_id']]);$years=$q->fetchAll(PDO::FETCH_ASSOC);
    $name=rfName('st');$q=$pdo->prepare("SELECT DISTINCT st.id,CONCAT($name,' (',st.admission_no,')') student_name,e.academic_year_id FROM students st INNER JOIN student_enrollments e ON e.student_id=st.id AND e.tenant_id=st.tenant_id WHERE st.tenant_id=:t AND (:bs=0 OR st.branch_id=:b) AND st.status='active' AND st.deleted_at IS NULL ORDER BY st.first_name");$q->execute(['t'=>$s['tenant_id'],'bs'=>$s['branch_id'],'b'=>$s['branch_id']]);$students=$q->fetchAll(PDO::FETCH_ASSOC);
    $q=$pdo->prepare("SELECT r.id,r.student_id,r.academic_year_id,r.receipt_no,r.receipt_date,r.paid_amount,GROUP_CONCAT(DISTINCT pm.method_name ORDER BY pm.method_name SEPARATOR ', ') payment_methods,COALESCE((SELECT SUM(fr.refund_amount) FROM fee_refunds fr WHERE fr.tenant_id=r.tenant_id AND fr.receipt_id=r.id AND fr.refund_status IN('approved','completed')),0) refunded_amount FROM fee_receipts r LEFT JOIN fee_payments fp ON fp.receipt_id=r.id AND fp.tenant_id=r.tenant_id LEFT JOIN payment_methods pm ON pm.id=fp.payment_method_id AND pm.tenant_id=fp.tenant_id WHERE r.tenant_id=:t AND (:bs=0 OR r.branch_id=:b) AND r.payment_status<>'reversed' GROUP BY r.id ORDER BY r.receipt_date DESC,r.id DESC");
    $q->execute(['t'=>$s['tenant_id'],'bs'=>$s['branch_id'],'b'=>$s['branch_id']]);$receipts=$q->fetchAll(PDO::FETCH_ASSOC);
    foreach($receipts as &$r){$r['available_refund']=max(0,round((float)$r['paid_amount']-(float)$r['refunded_amount'],2));$r['receipt_label']=$r['receipt_no'].' - ₹'.number_format((float)$r['available_refund'],2).' available';}
    $q=$pdo->prepare("SELECT id,method_name,method_key FROM payment_methods WHERE tenant_id=:t AND status='active' ORDER BY method_name");$q->execute(['t'=>$s['tenant_id']]);$methods=$q->fetchAll(PDO::FETCH_ASSOC);
    return compact('years','students','receipts','methods');
}

if(!isset($pdo)||!$pdo instanceof PDO)rfOut(false,'Database connection unavailable.',[],500);
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
if(empty($_SESSION['fee_csrf_token']))$_SESSION['fee_csrf_token']=bin2hex(random_bytes(32));
$s=rfScope();if($s['tenant_id']<=0||$s['user_id']<=0)rfOut(false,'Tenant or user session is missing.',[],401);
rfEnsure($pdo);
$i=rfInput();$action=strtolower(trim((string)($i['action']??$_GET['action']??'')));

try{
    if($action==='list'){
        $where=['fr.tenant_id=:t','(:bs=0 OR fr.branch_id=:b)'];$p=['t'=>$s['tenant_id'],'bs'=>$s['branch_id'],'b'=>$s['branch_id']];
        $search=trim((string)($_GET['search']??''));if($search!==''){$where[]='(fr.refund_no LIKE :q OR st.first_name LIKE :q OR st.last_name LIKE :q OR st.admission_no LIKE :q OR r.receipt_no LIKE :q)';$p['q']='%'.$search.'%';}
        if(!empty($_GET['academic_year_id'])&&$_GET['academic_year_id']!=='all'){$where[]='fr.academic_year_id=:y';$p['y']=(int)$_GET['academic_year_id'];}
        foreach(['refund_type','refund_status'] as $k){$v=(string)($_GET[$k]??'all');if($v!==''&&$v!=='all'){$where[]="fr.$k=:$k";$p[$k]=$v;}}
        if(!empty($_GET['payment_method_id'])&&$_GET['payment_method_id']!=='all'){$where[]='fr.payment_method_id=:pm';$p['pm']=(int)$_GET['payment_method_id'];}
        if(!empty($_GET['from_date'])){$where[]='fr.request_date>=:fd';$p['fd']=$_GET['from_date'];}
        $page=max(1,(int)($_GET['page']??1));$per=min(100,max(5,(int)($_GET['per_page']??10)));
        $count=$pdo->prepare("SELECT COUNT(*) FROM fee_refunds fr INNER JOIN students st ON st.id=fr.student_id INNER JOIN fee_receipts r ON r.id=fr.receipt_id WHERE ".implode(' AND ',$where));$count->execute($p);$total=(int)$count->fetchColumn();$last=max(1,(int)ceil($total/$per));$page=min($page,$last);$offset=($page-1)*$per;
        $name=rfName('st');$q=$pdo->prepare("SELECT fr.*,$name student_name,st.admission_no,ay.year_name,r.receipt_no,r.paid_amount receipt_paid_amount,pm.method_name refund_method_name FROM fee_refunds fr INNER JOIN students st ON st.id=fr.student_id INNER JOIN academic_years ay ON ay.id=fr.academic_year_id INNER JOIN fee_receipts r ON r.id=fr.receipt_id LEFT JOIN payment_methods pm ON pm.id=fr.payment_method_id WHERE ".implode(' AND ',$where)." ORDER BY fr.id DESC LIMIT {$per} OFFSET {$offset}");
        $q->execute($p);$records=$q->fetchAll(PDO::FETCH_ASSOC);foreach($records as $idx=>&$r)$r['row_number']=$offset+$idx+1;
        try{$h=$pdo->prepare("SELECT h.*,fr.refund_no,fr.refund_status,".rfName('st')." student_name,COALESCE(u.name,u.username,u.email) user_name FROM fee_refund_history h LEFT JOIN fee_refunds fr ON fr.id=h.refund_id LEFT JOIN students st ON st.id=fr.student_id LEFT JOIN users u ON u.id=h.user_id WHERE h.tenant_id=:t ORDER BY h.id DESC LIMIT 100");$h->execute(['t'=>$s['tenant_id']]);$history=$h->fetchAll(PDO::FETCH_ASSOC);}catch(Throwable $e){$h=$pdo->prepare("SELECT h.*,fr.refund_no,fr.refund_status,".rfName('st')." student_name,NULL user_name FROM fee_refund_history h LEFT JOIN fee_refunds fr ON fr.id=h.refund_id LEFT JOIN students st ON st.id=fr.student_id WHERE h.tenant_id=:t ORDER BY h.id DESC LIMIT 100");$h->execute(['t'=>$s['tenant_id']]);$history=$h->fetchAll(PDO::FETCH_ASSOC);}
        $q=$pdo->prepare("SELECT COUNT(*) total,SUM(refund_status='pending') pending,COALESCE(SUM(CASE WHEN refund_status='approved' THEN refund_amount ELSE 0 END),0) approved,COALESCE(SUM(CASE WHEN refund_status='completed' THEN refund_amount ELSE 0 END),0) completed FROM fee_refunds WHERE tenant_id=:t AND (:bs=0 OR branch_id=:b)");$q->execute(['t'=>$s['tenant_id'],'bs'=>$s['branch_id'],'b'=>$s['branch_id']]);$stats=$q->fetch(PDO::FETCH_ASSOC)?:[];
        rfOut(true,'Refund management loaded.',['records'=>$records,'history'=>$history,'meta'=>rfMeta($pdo,$s),'pagination'=>['total'=>$total,'page'=>$page,'per_page'=>$per,'last_page'=>$last],'stats'=>['total'=>(int)($stats['total']??0),'pending'=>(int)($stats['pending']??0),'approved'=>(float)($stats['approved']??0),'completed'=>(float)($stats['completed']??0)],'permissions'=>['approve'=>rfCan($s,'approve'),'complete'=>rfCan($s,'complete'),'delete'=>rfCan($s,'delete')],'csrf_token'=>$_SESSION['fee_csrf_token']]);
    }
    if($action==='detail'){
        $id=(int)($_GET['id']??0);$name=rfName('st');$q=$pdo->prepare("SELECT fr.*,$name student_name,st.admission_no,ay.year_name,r.receipt_no,r.paid_amount receipt_paid_amount,pm.method_name refund_method_name FROM fee_refunds fr INNER JOIN students st ON st.id=fr.student_id INNER JOIN academic_years ay ON ay.id=fr.academic_year_id INNER JOIN fee_receipts r ON r.id=fr.receipt_id LEFT JOIN payment_methods pm ON pm.id=fr.payment_method_id WHERE fr.id=:id AND fr.tenant_id=:t AND (:bs=0 OR fr.branch_id=:b)");$q->execute(['id'=>$id,'t'=>$s['tenant_id'],'bs'=>$s['branch_id'],'b'=>$s['branch_id']]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)throw new InvalidArgumentException('Refund not found.');rfOut(true,'Refund loaded.',['refund'=>$r]);
    }
    if($action==='export'){
        $params=$_GET;$params['page']=1;$params['per_page']=100;
        $_GET=$params;
        $where=['fr.tenant_id=:t','(:bs=0 OR fr.branch_id=:b)'];$p=['t'=>$s['tenant_id'],'bs'=>$s['branch_id'],'b'=>$s['branch_id']];
        $name=rfName('st');$q=$pdo->prepare("SELECT fr.*,$name student_name,st.admission_no,ay.year_name,r.receipt_no,r.paid_amount receipt_paid_amount,pm.method_name refund_method_name FROM fee_refunds fr INNER JOIN students st ON st.id=fr.student_id INNER JOIN academic_years ay ON ay.id=fr.academic_year_id INNER JOIN fee_receipts r ON r.id=fr.receipt_id LEFT JOIN payment_methods pm ON pm.id=fr.payment_method_id WHERE ".implode(' AND ',$where)." ORDER BY fr.id DESC");$q->execute($p);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
        while(ob_get_level()>0)ob_end_clean();header('Content-Type:text/csv; charset=utf-8');header('Content-Disposition:attachment; filename="fee-refunds-'.date('Ymd-His').'.csv"');$f=fopen('php://output','wb');fputcsv($f,['Refund No','Request Date','Student','Admission No','Academic Year','Receipt No','Paid Amount','Refund Type','Refund Amount','Status','Refund Mode','Reference']);foreach($rows as $r)fputcsv($f,[$r['refund_no'],$r['request_date'],$r['student_name'],$r['admission_no'],$r['year_name'],$r['receipt_no'],$r['receipt_paid_amount'],$r['refund_type'],$r['refund_amount'],$r['refund_status'],$r['refund_method_name'],$r['transaction_reference']]);fclose($f);exit;
    }
    if($action==='save'){
        rfCsrf($i);$id=(int)($i['id']??0);$year=(int)($i['academic_year_id']??0);$student=(int)($i['student_id']??0);$receipt=(int)($i['receipt_id']??0);$type=(string)($i['refund_type']??'partial');$amount=round((float)($i['refund_amount']??0),2);$date=(string)($i['request_date']??'');$reason=trim((string)($i['refund_reason']??''));
        if($year<=0||$student<=0||$receipt<=0||$amount<=0||$date===''||$reason==='')throw new InvalidArgumentException('Academic year, student, receipt, amount, date and reason are required.');if(!in_array($type,['full','partial','excess','cancellation'],true))throw new InvalidArgumentException('Invalid refund type.');
        $r=rfReceiptAvailable($pdo,$s['tenant_id'],$receipt,$id);if((int)$r['student_id']!==$student||(int)$r['academic_year_id']!==$year)throw new InvalidArgumentException('Receipt does not belong to the selected student and academic year.');if($amount>$r['available_refund']+0.01)throw new InvalidArgumentException('Refund amount exceeds the available refundable amount.');if($type==='full'&&abs($amount-$r['available_refund'])>0.01)throw new InvalidArgumentException('Full refund amount must equal the available refundable amount.');
        $pdo->beginTransaction();
        if($id){$q=$pdo->prepare("SELECT refund_status FROM fee_refunds WHERE id=:id AND tenant_id=:t FOR UPDATE");$q->execute(['id'=>$id,'t'=>$s['tenant_id']]);$status=$q->fetchColumn();if($status===false)throw new InvalidArgumentException('Refund not found.');if($status!=='pending')throw new InvalidArgumentException('Only pending refund requests can be edited.');$q=$pdo->prepare("UPDATE fee_refunds SET student_id=:st,academic_year_id=:y,receipt_id=:r,refund_type=:type,refund_amount=:a,request_date=:d,refund_reason=:reason WHERE id=:id AND tenant_id=:t");$q->execute(['st'=>$student,'y'=>$year,'r'=>$receipt,'type'=>$type,'a'=>$amount,'d'=>$date,'reason'=>$reason,'id'=>$id,'t'=>$s['tenant_id']]);$message='Refund request updated successfully.';}else{$no=rfNumber($pdo,$s['tenant_id']);$q=$pdo->prepare("INSERT INTO fee_refunds(tenant_id,branch_id,refund_no,student_id,academic_year_id,receipt_id,refund_type,refund_amount,request_date,refund_reason,refund_status,created_by) VALUES(:t,:b,:no,:st,:y,:r,:type,:a,:d,:reason,'pending',:u)");$q->execute(['t'=>$s['tenant_id'],'b'=>$s['branch_id']?:null,'no'=>$no,'st'=>$student,'y'=>$year,'r'=>$receipt,'type'=>$type,'a'=>$amount,'d'=>$date,'reason'=>$reason,'u'=>$s['user_id']]);$id=(int)$pdo->lastInsertId();$message='Refund request created successfully.';}rfHistory($pdo,$s,$id,'save_refund',$amount,$reason);$pdo->commit();rfOut(true,$message,['id'=>$id]);
    }
    if($action==='approve'){
        rfCsrf($i);if(!rfCan($s,'approve'))rfOut(false,'You do not have permission to approve refunds.',[],403);$id=(int)($i['id']??0);$status=(string)($i['refund_status']??'');$remarks=trim((string)($i['remarks']??''));if(!in_array($status,['approved','rejected'],true))throw new InvalidArgumentException('Invalid approval status.');
        $pdo->beginTransaction();$q=$pdo->prepare("SELECT * FROM fee_refunds WHERE id=:id AND tenant_id=:t FOR UPDATE");$q->execute(['id'=>$id,'t'=>$s['tenant_id']]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r||$r['refund_status']!=='pending')throw new InvalidArgumentException('Pending refund request not found.');rfReceiptAvailable($pdo,$s['tenant_id'],(int)$r['receipt_id'],$id);
        $pdo->prepare("UPDATE fee_refunds SET refund_status=:status,approval_remarks=:remarks,approved_by=:u,approved_at=NOW() WHERE id=:id AND tenant_id=:t")->execute(['status'=>$status,'remarks'=>$remarks?:null,'u'=>$s['user_id'],'id'=>$id,'t'=>$s['tenant_id']]);
        if($status==='approved'){$pdo->prepare("INSERT INTO fee_refund_account_entries(tenant_id,branch_id,refund_id,entry_date,entry_type,amount,remarks,created_by) VALUES(:t,:b,:r,CURDATE(),'refund_payable',:a,:remarks,:u)")->execute(['t'=>$s['tenant_id'],'b'=>$s['branch_id']?:null,'r'=>$id,'a'=>$r['refund_amount'],'remarks'=>$remarks?:'Refund approved','u'=>$s['user_id']]);}
        rfHistory($pdo,$s,$id,$status.'_refund',(float)$r['refund_amount'],$remarks);$pdo->commit();rfOut(true,'Refund '.$status.' successfully.');
    }
    if($action==='complete'){
        rfCsrf($i);if(!rfCan($s,'complete'))rfOut(false,'You do not have permission to complete refunds.',[],403);$id=(int)($i['id']??0);$method=(int)($i['payment_method_id']??0);$date=(string)($i['refund_date']??'');$reference=trim((string)($i['transaction_reference']??''));$remarks=trim((string)($i['completion_remarks']??''));
        if($method<=0||$date==='')throw new InvalidArgumentException('Refund payment mode and refund date are required.');$q=$pdo->prepare("SELECT method_key FROM payment_methods WHERE id=:id AND tenant_id=:t AND status='active'");$q->execute(['id'=>$method,'t'=>$s['tenant_id']]);$methodKey=$q->fetchColumn();if($methodKey===false)throw new InvalidArgumentException('Invalid refund payment mode.');if(strtolower((string)$methodKey)!=='cash'&&$reference==='')throw new InvalidArgumentException('Transaction reference is required for non-cash refunds.');
        $pdo->beginTransaction();$q=$pdo->prepare("SELECT * FROM fee_refunds WHERE id=:id AND tenant_id=:t FOR UPDATE");$q->execute(['id'=>$id,'t'=>$s['tenant_id']]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r||$r['refund_status']!=='approved')throw new InvalidArgumentException('Approved refund request not found.');$receipt=rfReceiptAvailable($pdo,$s['tenant_id'],(int)$r['receipt_id'],$id);if((float)$r['refund_amount']>$receipt['available_refund']+0.01)throw new InvalidArgumentException('Refund amount is no longer available.');
        $pdo->prepare("UPDATE fee_refunds SET refund_status='completed',payment_method_id=:pm,refund_date=:d,transaction_reference=:ref,completion_remarks=:remarks,completed_by=:u,completed_at=NOW() WHERE id=:id AND tenant_id=:t")->execute(['pm'=>$method,'d'=>$date,'ref'=>$reference?:null,'remarks'=>$remarks?:null,'u'=>$s['user_id'],'id'=>$id,'t'=>$s['tenant_id']]);
        $pdo->prepare("INSERT INTO fee_refund_account_entries(tenant_id,branch_id,refund_id,entry_date,entry_type,amount,payment_method_id,reference_no,remarks,created_by) VALUES(:t,:b,:r,:d,'refund_paid',:a,:pm,:ref,:remarks,:u)")->execute(['t'=>$s['tenant_id'],'b'=>$s['branch_id']?:null,'r'=>$id,'d'=>$date,'a'=>$r['refund_amount'],'pm'=>$method,'ref'=>$reference?:null,'remarks'=>$remarks?:'Refund completed','u'=>$s['user_id']]);
        $assignmentId=0;if(preg_match('/\[assignment:(\d+)\]/',(string)$receipt['notes'],$m))$assignmentId=(int)$m[1];
        if($assignmentId>0){
            $q=$pdo->prepare("SELECT * FROM student_fee_assignments WHERE id=:id AND tenant_id=:t FOR UPDATE");$q->execute(['id'=>$assignmentId,'t'=>$s['tenant_id']]);$a=$q->fetch(PDO::FETCH_ASSOC);
            if($a){$paid=max(0,round((float)$a['paid_amount']-(float)$r['refund_amount'],2));$balance=max(0,round((float)$a['net_amount']-$paid,2));$ps=$balance<=0.01?'paid':($paid>0?'partial':'unpaid');$pdo->prepare("UPDATE student_fee_assignments SET paid_amount=:p,balance_amount=:b,payment_status=:s WHERE id=:id AND tenant_id=:t")->execute(['p'=>$paid,'b'=>$balance,'s'=>$ps,'id'=>$assignmentId,'t'=>$s['tenant_id']]);}
        }
        rfHistory($pdo,$s,$id,'complete_refund',(float)$r['refund_amount'],$remarks);$pdo->commit();rfOut(true,'Refund completed and student fee balance updated.');
    }
    if($action==='delete'){
        rfCsrf($i);if(!rfCan($s,'delete'))rfOut(false,'You do not have permission to delete refunds.',[],403);$id=(int)($i['id']??0);$q=$pdo->prepare("SELECT refund_status FROM fee_refunds WHERE id=:id AND tenant_id=:t");$q->execute(['id'=>$id,'t'=>$s['tenant_id']]);$status=$q->fetchColumn();if($status===false)throw new InvalidArgumentException('Refund not found.');if(!in_array($status,['pending','rejected'],true))throw new InvalidArgumentException('Only pending or rejected refunds can be deleted.');$pdo->prepare("DELETE FROM fee_refunds WHERE id=:id AND tenant_id=:t")->execute(['id'=>$id,'t'=>$s['tenant_id']]);rfHistory($pdo,$s,$id,'delete_refund',0,'Refund request deleted');rfOut(true,'Refund request deleted.');
    }
    rfOut(false,'Invalid Fee Refund action.',[],400);
}catch(InvalidArgumentException $e){if($pdo->inTransaction())$pdo->rollBack();rfOut(false,$e->getMessage(),[],422);}
catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('fee-refund-management.php: '.$e->getMessage());$h=strtolower((string)($_SERVER['HTTP_HOST']??''));rfOut(false,(str_contains($h,'localhost')||str_contains($h,'127.0.0.1'))?'Refund request failed: '.$e->getMessage():'Unable to complete the refund request.',[],500);}

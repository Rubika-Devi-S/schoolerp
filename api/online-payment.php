<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors','0');
error_reporting(E_ALL);

require_once dirname(__DIR__).'/includes/bootstrap.php';

function opOut(bool $ok,string $message='',array $data=[],int $status=200):never{
    while(ob_get_level()>0)ob_end_clean();
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control:no-store');
    echo json_encode(['success'=>$ok,'message'=>$message,'data'=>$data],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function opInput():array{$j=json_decode((string)file_get_contents('php://input'),true);return is_array($j)?$j:$_POST;}
function opScope():array{$u=function_exists('current_user')?current_user():[];return['tenant_id'=>(int)($u['tenant_id']??$u['school_id']??$_SESSION['tenant_id']??0),'branch_id'=>(int)($u['branch_id']??$_SESSION['branch_id']??0),'user_id'=>(int)($u['id']??$u['user_id']??$_SESSION['user_id']??0),'role'=>(string)($u['role']??$u['role_name']??$_SESSION['role']??'')];}
function opCsrf(array $i):void{$s=(string)($_SESSION['fee_csrf_token']??'');$r=(string)($i['csrf_token']??'');if($s===''||$r===''||!hash_equals($s,$r))opOut(false,'Invalid or expired CSRF token. Refresh the page.',[],419);}
function opName(string $a='s'):string{return "TRIM(CONCAT(COALESCE($a.first_name,''),CASE WHEN COALESCE($a.last_name,'')='' THEN '' ELSE CONCAT(' ',$a.last_name) END))";}
function opCan(array $s,string $action):bool{
    if(function_exists('has_permission')){try{return has_permission('fee_management',$action)||has_permission('online_payment',$action);}catch(Throwable $e){}}
    $role=strtolower($s['role']);
    if(in_array($role,['super_admin','super admin','admin','school_admin','school admin'],true))return true;
    return $action==='view'||($action==='refund'&&in_array($role,['accountant','principal'],true));
}
function opEnsure(PDO $pdo):void{
    $pdo->exec("CREATE TABLE IF NOT EXISTS online_payment_transactions(
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        tenant_id BIGINT UNSIGNED NOT NULL,
        branch_id BIGINT UNSIGNED NULL,
        transaction_no VARCHAR(70) NOT NULL,
        assignment_id BIGINT UNSIGNED NOT NULL,
        student_id BIGINT UNSIGNED NOT NULL,
        academic_year_id BIGINT UNSIGNED NOT NULL,
        fee_structure_id BIGINT UNSIGNED NOT NULL,
        gateway_name VARCHAR(40) NOT NULL DEFAULT 'razorpay',
        payment_method ENUM('upi','card','netbanking','wallet') NOT NULL,
        payment_type ENUM('partial','full') NOT NULL DEFAULT 'partial',
        amount DECIMAL(12,2) NOT NULL,
        currency VARCHAR(3) NOT NULL DEFAULT 'INR',
        gateway_order_id VARCHAR(120) NULL,
        gateway_payment_id VARCHAR(120) NULL,
        gateway_signature VARCHAR(255) NULL,
        gateway_reference VARCHAR(190) NULL,
        payment_status ENUM('pending','processing','successful','failed','cancelled','refunded') NOT NULL DEFAULT 'pending',
        receipt_id BIGINT UNSIGNED NULL,
        payer_notes VARCHAR(500) NULL,
        failure_reason VARCHAR(500) NULL,
        paid_at DATETIME NULL,
        created_by BIGINT UNSIGNED NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY(id),
        UNIQUE KEY uq_online_txn_no(tenant_id,transaction_no),
        UNIQUE KEY uq_gateway_order(tenant_id,gateway_order_id),
        UNIQUE KEY uq_gateway_payment(tenant_id,gateway_payment_id),
        KEY idx_online_payment_filter(tenant_id,academic_year_id,payment_status,created_at),
        KEY idx_online_payment_assignment(tenant_id,assignment_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS online_payment_events(
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        tenant_id BIGINT UNSIGNED NOT NULL,
        transaction_id BIGINT UNSIGNED NOT NULL,
        event_name VARCHAR(80) NOT NULL,
        old_status VARCHAR(30) NULL,
        new_status VARCHAR(30) NULL,
        gateway_reference VARCHAR(190) NULL,
        message_text VARCHAR(500) NULL,
        payload_json JSON NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(id),
        KEY idx_online_event(tenant_id,transaction_id,created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS online_payment_refunds(
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        tenant_id BIGINT UNSIGNED NOT NULL,
        branch_id BIGINT UNSIGNED NULL,
        transaction_id BIGINT UNSIGNED NOT NULL,
        refund_reference VARCHAR(120) NULL,
        amount DECIMAL(12,2) NOT NULL,
        refund_status ENUM('pending','processing','successful','failed') NOT NULL DEFAULT 'pending',
        reason VARCHAR(500) NOT NULL,
        created_by BIGINT UNSIGNED NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY(id),
        KEY idx_online_refund(tenant_id,transaction_id,refund_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function opSetting(PDO $pdo,int $tenant,string $key,string $default=''):string{
    try{$q=$pdo->prepare("SELECT setting_value FROM fee_settings WHERE tenant_id=:t AND setting_key=:k LIMIT 1");$q->execute(['t'=>$tenant,'k'=>$key]);$v=$q->fetchColumn();return $v===false?$default:(string)$v;}catch(Throwable $e){return$default;}
}
function opEvent(PDO $pdo,array $s,int $txn,string $event,?string $old,?string $new,?string $ref,string $message,array $payload=[]):void{
    $q=$pdo->prepare("INSERT INTO online_payment_events(tenant_id,transaction_id,event_name,old_status,new_status,gateway_reference,message_text,payload_json) VALUES(:t,:x,:e,:o,:n,:r,:m,:p)");
    $q->execute(['t'=>$s['tenant_id'],'x'=>$txn,'e'=>$event,'o'=>$old,'n'=>$new,'r'=>$ref,'m'=>$message,'p'=>json_encode($payload)]);
}
function opTxnNo(PDO $pdo,int $tenant):string{
    for($i=0;$i<20;$i++){
        $no='OPT-'.date('Ymd').'-'.str_pad((string)random_int(1,999999),6,'0',STR_PAD_LEFT);
        $q=$pdo->prepare("SELECT COUNT(*) FROM online_payment_transactions WHERE tenant_id=:t AND transaction_no=:n");$q->execute(['t'=>$tenant,'n'=>$no]);
        if(!(int)$q->fetchColumn())return$no;
    }
    throw new RuntimeException('Unable to generate transaction number.');
}
function opGatewayConfig(PDO $pdo,int $tenant):array{
    return[
        'gateway'=>strtolower(opSetting($pdo,$tenant,'online_gateway','razorpay')),
        'key_id'=>opSetting($pdo,$tenant,'razorpay_key_id',''),
        'key_secret'=>opSetting($pdo,$tenant,'razorpay_key_secret',''),
        'webhook_secret'=>opSetting($pdo,$tenant,'razorpay_webhook_secret',''),
        'currency'=>strtoupper(opSetting($pdo,$tenant,'currency_code','INR'))
    ];
}
function opHttp(string $url,array $payload,string $key,string $secret,string $method='POST'):array{
    if(!function_exists('curl_init'))throw new RuntimeException('PHP cURL extension is required for payment gateway integration.');
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPAUTH=>CURLAUTH_BASIC,CURLOPT_USERPWD=>$key.':'.$secret,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload)]);
    $body=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$error=curl_error($ch);curl_close($ch);
    if($body===false||$error!=='')throw new RuntimeException('Gateway connection failed: '.$error);
    $json=json_decode((string)$body,true);
    if($code<200||$code>=300)throw new RuntimeException('Gateway rejected the request: '.($json['error']['description']??('HTTP '.$code)));
    if(!is_array($json))throw new RuntimeException('Invalid gateway response.');
    return$json;
}
function opReceiptNo(PDO $pdo,int $tenant):string{
    $prefix=opSetting($pdo,$tenant,'receipt_prefix','REC');
    for($i=0;$i<20;$i++){
        $no=$prefix.'/'.date('Y').'/'.str_pad((string)random_int(1,999999),6,'0',STR_PAD_LEFT);
        $q=$pdo->prepare("SELECT COUNT(*) FROM fee_receipts WHERE tenant_id=:t AND receipt_no=:n");$q->execute(['t'=>$tenant,'n'=>$no]);
        if(!(int)$q->fetchColumn())return$no;
    }
    throw new RuntimeException('Unable to generate receipt number.');
}
function opComplete(PDO $pdo,array $s,array $txn,string $gatewayPaymentId,string $signature,string $reference=''):int{
    $q=$pdo->prepare("SELECT * FROM online_payment_transactions WHERE id=:id AND tenant_id=:t FOR UPDATE");$q->execute(['id'=>$txn['id'],'t'=>$s['tenant_id']]);$current=$q->fetch(PDO::FETCH_ASSOC);
    if(!$current)throw new InvalidArgumentException('Online transaction not found.');
    if($current['payment_status']==='successful')return(int)$current['receipt_id'];

    $q=$pdo->prepare("SELECT * FROM student_fee_assignments WHERE id=:id AND tenant_id=:t FOR UPDATE");$q->execute(['id'=>$current['assignment_id'],'t'=>$s['tenant_id']]);$a=$q->fetch(PDO::FETCH_ASSOC);
    if(!$a)throw new InvalidArgumentException('Fee assignment not found.');
    $amount=(float)$current['amount'];if($amount<=0||$amount>(float)$a['balance_amount']+0.01)throw new InvalidArgumentException('Payment amount exceeds the current outstanding balance.');

    $receiptNo=opReceiptNo($pdo,$s['tenant_id']);
    $q=$pdo->prepare("INSERT INTO fee_receipts(tenant_id,branch_id,receipt_no,student_id,academic_year_id,gross_amount,discount_amount,fine_amount,paid_amount,payment_status,receipt_date,notes,created_by) VALUES(:t,:b,:no,:st,:y,:g,:d,:f,:p,'paid',NOW(),:notes,:u)");
    $q->execute(['t'=>$s['tenant_id'],'b'=>$s['branch_id']?:null,'no'=>$receiptNo,'st'=>$a['student_id'],'y'=>$a['academic_year_id'],'g'=>$amount,'d'=>0,'f'=>0,'p'=>$amount,'notes'=>'Online payment '.$current['transaction_no'].' [assignment:'.$a['id'].']','u'=>$s['user_id']]);
    $receiptId=(int)$pdo->lastInsertId();

    $methodQ=$pdo->prepare("SELECT id FROM payment_methods WHERE tenant_id=:t AND method_key IN(:k,'online') AND status='active' ORDER BY method_key=:k DESC LIMIT 1");
    $methodQ->execute(['t'=>$s['tenant_id'],'k'=>$current['payment_method']]);$methodId=(int)($methodQ->fetchColumn()?:0);
    if($methodId<=0){
        $ins=$pdo->prepare("INSERT INTO payment_methods(tenant_id,method_name,method_key,status) VALUES(:t,:n,:k,'active')");
        $ins->execute(['t'=>$s['tenant_id'],'n'=>ucwords(str_replace('netbanking','Net Banking',$current['payment_method'])),'k'=>$current['payment_method']]);$methodId=(int)$pdo->lastInsertId();
    }
    $q=$pdo->prepare("INSERT INTO fee_payments(tenant_id,branch_id,receipt_id,student_id,academic_year_id,payment_method_id,amount,reference_no,status,paid_at,created_by) VALUES(:t,:b,:r,:st,:y,:pm,:a,:ref,'paid',NOW(),:u)");
    $q->execute(['t'=>$s['tenant_id'],'b'=>$s['branch_id']?:null,'r'=>$receiptId,'st'=>$a['student_id'],'y'=>$a['academic_year_id'],'pm'=>$methodId,'a'=>$amount,'ref'=>$gatewayPaymentId,'u'=>$s['user_id']]);

    $newPaid=round((float)$a['paid_amount']+$amount,2);$balance=max(0,round((float)$a['net_amount']-$newPaid,2));$status=$balance<=0.01?'paid':'partial';
    $pdo->prepare("UPDATE student_fee_assignments SET paid_amount=:p,balance_amount=:b,payment_status=:s WHERE id=:id AND tenant_id=:t")->execute(['p'=>$newPaid,'b'=>$balance,'s'=>$status,'id'=>$a['id'],'t'=>$s['tenant_id']]);

    $pdo->prepare("UPDATE online_payment_transactions SET gateway_payment_id=:gp,gateway_signature=:sig,gateway_reference=:ref,payment_status='successful',receipt_id=:r,paid_at=NOW(),failure_reason=NULL WHERE id=:id AND tenant_id=:t")->execute(['gp'=>$gatewayPaymentId,'sig'=>$signature,'ref'=>$reference?:$gatewayPaymentId,'r'=>$receiptId,'id'=>$current['id'],'t'=>$s['tenant_id']]);
    opEvent($pdo,$s,(int)$current['id'],'payment_success',$current['payment_status'],'successful',$gatewayPaymentId,'Payment verified and receipt generated.');
    return$receiptId;
}
function opMeta(PDO $pdo,array $s):array{
    $q=$pdo->prepare("SELECT id,year_name FROM academic_years WHERE tenant_id=:t ORDER BY is_current DESC,start_date DESC,id DESC");$q->execute(['t'=>$s['tenant_id']]);$years=$q->fetchAll(PDO::FETCH_ASSOC);
    $name=opName('st');$q=$pdo->prepare("SELECT DISTINCT st.id,CONCAT($name,' (',st.admission_no,')') student_name,e.academic_year_id FROM students st INNER JOIN student_enrollments e ON e.student_id=st.id AND e.tenant_id=st.tenant_id WHERE st.tenant_id=:t AND (:bs=0 OR st.branch_id=:b) AND st.status='active' AND st.deleted_at IS NULL ORDER BY st.first_name");$q->execute(['t'=>$s['tenant_id'],'bs'=>$s['branch_id'],'b'=>$s['branch_id']]);$students=$q->fetchAll(PDO::FETCH_ASSOC);
    $q=$pdo->prepare("SELECT a.id,a.student_id,a.academic_year_id,a.net_amount,a.paid_amount,a.balance_amount,fs.structure_name,CONCAT(fs.structure_name,' - Outstanding ₹',FORMAT(a.balance_amount,2)) assignment_label,COALESCE((SELECT SUM(sf.fine_amount-sf.paid_amount-sf.waived_amount) FROM student_fines sf WHERE sf.tenant_id=a.tenant_id AND sf.assignment_id=a.id AND sf.fine_status='pending'),0) fine_amount FROM student_fee_assignments a INNER JOIN fee_structures fs ON fs.id=a.fee_structure_id AND fs.tenant_id=a.tenant_id WHERE a.tenant_id=:t AND a.balance_amount>0.01 ORDER BY fs.structure_name");$q->execute(['t'=>$s['tenant_id']]);$assignments=$q->fetchAll(PDO::FETCH_ASSOC);
    $g=opGatewayConfig($pdo,$s['tenant_id']);
    return['years'=>$years,'students'=>$students,'assignments'=>$assignments,'gateway'=>['name'=>$g['gateway'],'configured'=>$g['key_id']!==''&&$g['key_secret']!=='']];
}
if(!isset($pdo)||!$pdo instanceof PDO)opOut(false,'Database connection unavailable.',[],500);
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
if(empty($_SESSION['fee_csrf_token']))$_SESSION['fee_csrf_token']=bin2hex(random_bytes(32));
$s=opScope();if(!opCan($s,'view'))opOut(false,'You do not have permission to access online payments.',[],403);
opEnsure($pdo);
$i=opInput();$action=strtolower(trim((string)($i['action']??$_GET['action']??'')));

try{
    if($action==='list'){
        $where=['t.tenant_id=:tenant','(:bs=0 OR t.branch_id=:b)'];$p=['tenant'=>$s['tenant_id'],'bs'=>$s['branch_id'],'b'=>$s['branch_id']];
        $search=trim((string)($_GET['search']??''));if($search!==''){$where[]='(t.transaction_no LIKE :q OR t.gateway_payment_id LIKE :q OR r.receipt_no LIKE :q OR st.first_name LIKE :q OR st.last_name LIKE :q OR st.admission_no LIKE :q)';$p['q']='%'.$search.'%';}
        if(!empty($_GET['academic_year_id'])&&$_GET['academic_year_id']!=='all'){$where[]='t.academic_year_id=:y';$p['y']=(int)$_GET['academic_year_id'];}
        foreach(['payment_method','payment_status'] as $k){$v=(string)($_GET[$k]??'all');if($v!==''&&$v!=='all'){$where[]="t.$k=:$k";$p[$k]=$v;}}
        if(!empty($_GET['from_date'])){$where[]='DATE(t.created_at)>=:fd';$p['fd']=$_GET['from_date'];}if(!empty($_GET['to_date'])){$where[]='DATE(t.created_at)<=:td';$p['td']=$_GET['to_date'];}
        $page=max(1,(int)($_GET['page']??1));$per=min(100,max(5,(int)($_GET['per_page']??10)));
        $count=$pdo->prepare("SELECT COUNT(*) FROM online_payment_transactions t INNER JOIN students st ON st.id=t.student_id LEFT JOIN fee_receipts r ON r.id=t.receipt_id WHERE ".implode(' AND ',$where));$count->execute($p);$total=(int)$count->fetchColumn();$last=max(1,(int)ceil($total/$per));$page=min($page,$last);$offset=($page-1)*$per;
        $q=$pdo->prepare("SELECT t.*,".opName('st')." student_name,st.admission_no,ay.year_name,fs.structure_name,r.receipt_no FROM online_payment_transactions t INNER JOIN students st ON st.id=t.student_id INNER JOIN academic_years ay ON ay.id=t.academic_year_id INNER JOIN fee_structures fs ON fs.id=t.fee_structure_id LEFT JOIN fee_receipts r ON r.id=t.receipt_id WHERE ".implode(' AND ',$where)." ORDER BY t.id DESC LIMIT {$per} OFFSET {$offset}");$q->execute($p);$records=$q->fetchAll(PDO::FETCH_ASSOC);foreach($records as $idx=>&$r)$r['row_number']=$offset+$idx+1;
        $q=$pdo->prepare("SELECT e.*,t.transaction_no FROM online_payment_events e INNER JOIN online_payment_transactions t ON t.id=e.transaction_id WHERE e.tenant_id=:t ORDER BY e.id DESC LIMIT 100");$q->execute(['t'=>$s['tenant_id']]);$history=$q->fetchAll(PDO::FETCH_ASSOC);
        $q=$pdo->prepare("SELECT COALESCE(SUM(CASE WHEN payment_status='successful' THEN amount ELSE 0 END),0) total_collection,SUM(payment_status='successful') successful,SUM(payment_status IN('pending','processing')) pending FROM online_payment_transactions WHERE tenant_id=:t");$q->execute(['t'=>$s['tenant_id']]);$stats=$q->fetch(PDO::FETCH_ASSOC)?:[];
        $q=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM online_payment_refunds WHERE tenant_id=:t AND refund_status='successful'");$q->execute(['t'=>$s['tenant_id']]);$stats['refunded_amount']=(float)$q->fetchColumn();
        opOut(true,'Online payment dashboard loaded.',['records'=>$records,'history'=>$history,'meta'=>opMeta($pdo,$s),'pagination'=>['total'=>$total,'page'=>$page,'per_page'=>$per,'last_page'=>$last],'stats'=>['total_collection'=>(float)($stats['total_collection']??0),'successful'=>(int)($stats['successful']??0),'pending'=>(int)($stats['pending']??0),'refunded_amount'=>(float)$stats['refunded_amount']],'permissions'=>['refund'=>opCan($s,'refund')],'csrf_token'=>$_SESSION['fee_csrf_token']]);
    }
    if($action==='detail'){
        $id=(int)($_GET['id']??0);$q=$pdo->prepare("SELECT t.*,".opName('st')." student_name,st.admission_no,fs.structure_name,r.receipt_no FROM online_payment_transactions t INNER JOIN students st ON st.id=t.student_id INNER JOIN fee_structures fs ON fs.id=t.fee_structure_id LEFT JOIN fee_receipts r ON r.id=t.receipt_id WHERE t.id=:id AND t.tenant_id=:t");$q->execute(['id'=>$id,'t'=>$s['tenant_id']]);$x=$q->fetch(PDO::FETCH_ASSOC);if(!$x)throw new InvalidArgumentException('Online payment not found.');opOut(true,'Online payment loaded.',['transaction'=>$x]);
    }
    if($action==='initiate'){
        opCsrf($i);$assignment=(int)($i['assignment_id']??0);$method=(string)($i['payment_method']??'upi');$type=(string)($i['payment_type']??'partial');$amount=round((float)($i['amount']??0),2);$notes=trim((string)($i['payer_notes']??''));
        if(!in_array($method,['upi','card','netbanking','wallet'],true))throw new InvalidArgumentException('Invalid online payment method.');if(!in_array($type,['partial','full'],true))throw new InvalidArgumentException('Invalid payment type.');if($amount<=0)throw new InvalidArgumentException('Payment amount must be greater than zero.');
        $q=$pdo->prepare("SELECT a.*,fs.structure_name,".opName('st')." student_name,st.email,st.mobile FROM student_fee_assignments a INNER JOIN fee_structures fs ON fs.id=a.fee_structure_id INNER JOIN students st ON st.id=a.student_id WHERE a.id=:id AND a.tenant_id=:t AND a.balance_amount>0.01");$q->execute(['id'=>$assignment,'t'=>$s['tenant_id']]);$a=$q->fetch(PDO::FETCH_ASSOC);if(!$a)throw new InvalidArgumentException('Outstanding fee assignment not found.');if($type==='full')$amount=(float)$a['balance_amount'];if($amount>(float)$a['balance_amount']+0.01)throw new InvalidArgumentException('Payment amount exceeds the outstanding balance.');
        $g=opGatewayConfig($pdo,$s['tenant_id']);if($g['gateway']!=='razorpay'||$g['key_id']===''||$g['key_secret']==='')throw new RuntimeException('Razorpay credentials are not configured in Fee Settings.');
        $txnNo=opTxnNo($pdo,$s['tenant_id']);$subunits=(int)round($amount*100);
        $gateway=opHttp('https://api.razorpay.com/v1/orders',['amount'=>$subunits,'currency'=>$g['currency'],'receipt'=>$txnNo,'notes'=>['assignment_id'=>(string)$assignment,'student_id'=>(string)$a['student_id']]],$g['key_id'],$g['key_secret']);
        $q=$pdo->prepare("INSERT INTO online_payment_transactions(tenant_id,branch_id,transaction_no,assignment_id,student_id,academic_year_id,fee_structure_id,gateway_name,payment_method,payment_type,amount,currency,gateway_order_id,payment_status,payer_notes,created_by) VALUES(:t,:b,:no,:a,:st,:y,:fs,'razorpay',:pm,:pt,:amt,:cur,:go,'processing',:notes,:u)");
        $q->execute(['t'=>$s['tenant_id'],'b'=>$s['branch_id']?:null,'no'=>$txnNo,'a'=>$assignment,'st'=>$a['student_id'],'y'=>$a['academic_year_id'],'fs'=>$a['fee_structure_id'],'pm'=>$method,'pt'=>$type,'amt'=>$amount,'cur'=>$g['currency'],'go'=>$gateway['id'],'notes'=>$notes?:null,'u'=>$s['user_id']]);$id=(int)$pdo->lastInsertId();opEvent($pdo,$s,$id,'order_created','pending','processing',$gateway['id'],'Gateway order created.',$gateway);
        opOut(true,'Secure payment order created.',['transaction_id'=>$id,'transaction_no'=>$txnNo,'gateway'=>'razorpay','key_id'=>$g['key_id'],'gateway_order_id'=>$gateway['id'],'amount_subunits'=>$subunits,'currency'=>$g['currency'],'student_name'=>$a['student_name'],'email'=>$a['email'],'mobile'=>$a['mobile'],'description'=>$a['structure_name'],'school_name'=>opSetting($pdo,$s['tenant_id'],'school_name','School Fee')]);
    }
    if($action==='verify'){
        opCsrf($i);$id=(int)($i['transaction_id']??0);$order=(string)($i['razorpay_order_id']??'');$payment=(string)($i['razorpay_payment_id']??'');$signature=(string)($i['razorpay_signature']??'');
        $q=$pdo->prepare("SELECT * FROM online_payment_transactions WHERE id=:id AND tenant_id=:t FOR UPDATE");$pdo->beginTransaction();$q->execute(['id'=>$id,'t'=>$s['tenant_id']]);$txn=$q->fetch(PDO::FETCH_ASSOC);if(!$txn)throw new InvalidArgumentException('Online transaction not found.');if(!hash_equals((string)$txn['gateway_order_id'],$order))throw new InvalidArgumentException('Gateway order mismatch.');
        $g=opGatewayConfig($pdo,$s['tenant_id']);$expected=hash_hmac('sha256',$order.'|'.$payment,$g['key_secret']);if(!hash_equals($expected,$signature)){opEvent($pdo,$s,$id,'signature_failed',$txn['payment_status'],'failed',$payment,'Gateway signature validation failed.');$pdo->prepare("UPDATE online_payment_transactions SET payment_status='failed',failure_reason='Signature validation failed' WHERE id=:id")->execute(['id'=>$id]);$pdo->commit();opOut(false,'Payment signature verification failed.',[],422);}
        $receiptId=opComplete($pdo,$s,$txn,$payment,$signature,$payment);$pdo->commit();opOut(true,'Payment successful. Receipt generated.',['receipt_id'=>$receiptId]);
    }
    if($action==='cancel'){
        opCsrf($i);$id=(int)($i['transaction_id']??0);$q=$pdo->prepare("SELECT payment_status FROM online_payment_transactions WHERE id=:id AND tenant_id=:t");$q->execute(['id'=>$id,'t'=>$s['tenant_id']]);$old=$q->fetchColumn();if($old!==false&&in_array($old,['pending','processing'],true)){$pdo->prepare("UPDATE online_payment_transactions SET payment_status='cancelled' WHERE id=:id AND tenant_id=:t")->execute(['id'=>$id,'t'=>$s['tenant_id']]);opEvent($pdo,$s,$id,'checkout_cancelled',$old,'cancelled',null,'Checkout closed by payer.');}opOut(true,'Payment cancelled.');
    }
    if($action==='refund'){
        opCsrf($i);if(!opCan($s,'refund'))opOut(false,'You do not have permission to refund online payments.',[],403);$id=(int)($i['id']??0);$amount=round((float)($i['amount']??0),2);$reason=trim((string)($i['reason']??''));if($amount<=0||$reason==='')throw new InvalidArgumentException('Refund amount and reason are required.');
        $pdo->beginTransaction();$q=$pdo->prepare("SELECT * FROM online_payment_transactions WHERE id=:id AND tenant_id=:t FOR UPDATE");$q->execute(['id'=>$id,'t'=>$s['tenant_id']]);$txn=$q->fetch(PDO::FETCH_ASSOC);if(!$txn||$txn['payment_status']!=='successful'||empty($txn['gateway_payment_id']))throw new InvalidArgumentException('Successful online payment not found.');$q=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM online_payment_refunds WHERE tenant_id=:t AND transaction_id=:x AND refund_status IN('processing','successful')");$q->execute(['t'=>$s['tenant_id'],'x'=>$id]);$already=(float)$q->fetchColumn();if($amount>$txn['amount']-$already+0.01)throw new InvalidArgumentException('Refund amount exceeds the remaining refundable amount.');
        $g=opGatewayConfig($pdo,$s['tenant_id']);$refund=opHttp('https://api.razorpay.com/v1/payments/'.rawurlencode($txn['gateway_payment_id']).'/refund',['amount'=>(int)round($amount*100),'notes'=>['reason'=>$reason]],$g['key_id'],$g['key_secret']);
        $q=$pdo->prepare("INSERT INTO online_payment_refunds(tenant_id,branch_id,transaction_id,refund_reference,amount,refund_status,reason,created_by) VALUES(:t,:b,:x,:r,:a,'processing',:reason,:u)");$q->execute(['t'=>$s['tenant_id'],'b'=>$s['branch_id']?:null,'x'=>$id,'r'=>$refund['id']??null,'a'=>$amount,'reason'=>$reason,'u'=>$s['user_id']]);opEvent($pdo,$s,$id,'refund_created','successful','successful',$refund['id']??null,'Refund initiated for ₹'.number_format($amount,2),$refund);$pdo->commit();opOut(true,'Refund initiated successfully. Gateway confirmation will update the final status.');
    }
    if($action==='export'){
        $_GET['page']=1;$_GET['per_page']=100;
        $where=['t.tenant_id=:tenant'];$p=['tenant'=>$s['tenant_id']];
        $q=$pdo->prepare("SELECT t.transaction_no,t.created_at,".opName('st')." student_name,st.admission_no,ay.year_name,fs.structure_name,t.payment_method,t.amount,t.gateway_payment_id,r.receipt_no,t.payment_status FROM online_payment_transactions t INNER JOIN students st ON st.id=t.student_id INNER JOIN academic_years ay ON ay.id=t.academic_year_id INNER JOIN fee_structures fs ON fs.id=t.fee_structure_id LEFT JOIN fee_receipts r ON r.id=t.receipt_id WHERE ".implode(' AND ',$where)." ORDER BY t.id DESC");$q->execute($p);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
        while(ob_get_level()>0)ob_end_clean();header('Content-Type:text/csv; charset=utf-8');header('Content-Disposition:attachment; filename="online-payments-'.date('Ymd-His').'.csv"');$f=fopen('php://output','wb');fputcsv($f,['Transaction','Date','Student','Admission No','Academic Year','Fee Structure','Method','Amount','Gateway Payment','Receipt','Status']);foreach($rows as $r)fputcsv($f,$r);fclose($f);exit;
    }
    if(in_array($action,['receipt_print','receipt_pdf'],true)){
        $id=(int)($_GET['id']??0);$q=$pdo->prepare("SELECT t.*,r.receipt_no,r.receipt_date,r.paid_amount receipt_amount,".opName('st')." student_name,st.admission_no,fs.structure_name FROM online_payment_transactions t INNER JOIN fee_receipts r ON r.id=t.receipt_id INNER JOIN students st ON st.id=t.student_id INNER JOIN fee_structures fs ON fs.id=t.fee_structure_id WHERE t.id=:id AND t.tenant_id=:tenant");$q->execute(['id'=>$id,'tenant'=>$s['tenant_id']]);$x=$q->fetch(PDO::FETCH_ASSOC);if(!$x)throw new InvalidArgumentException('Receipt not found.');
        if($action==='receipt_print'){while(ob_get_level()>0)ob_end_clean();header('Content-Type:text/html; charset=utf-8');echo '<!doctype html><html><head><meta charset="utf-8"><title>Receipt '.htmlspecialchars($x['receipt_no']).'</title><style>body{font-family:Arial;margin:32px}.box{max-width:700px;margin:auto;border:1px solid #ddd;padding:24px}.row{display:flex;justify-content:space-between;border-bottom:1px solid #eee;padding:9px 0}.amount{font-size:24px;font-weight:bold;text-align:center;margin:24px}</style></head><body><div class="box"><h2 style="text-align:center">Online Fee Receipt</h2><div class="row"><b>Receipt</b><span>'.htmlspecialchars($x['receipt_no']).'</span></div><div class="row"><b>Student</b><span>'.htmlspecialchars($x['student_name']).'</span></div><div class="row"><b>Admission No.</b><span>'.htmlspecialchars($x['admission_no']).'</span></div><div class="row"><b>Fee Structure</b><span>'.htmlspecialchars($x['structure_name']).'</span></div><div class="row"><b>Gateway Payment ID</b><span>'.htmlspecialchars($x['gateway_payment_id']).'</span></div><div class="amount">₹'.number_format((float)$x['receipt_amount'],2).'</div><div style="text-align:center"><button onclick="window.print()">Print</button></div></div></body></html>';exit;}
        $text="ONLINE FEE RECEIPT\nReceipt: {$x['receipt_no']}\nStudent: {$x['student_name']}\nAdmission No: {$x['admission_no']}\nFee Structure: {$x['structure_name']}\nGateway Payment: {$x['gateway_payment_id']}\nAmount: INR ".number_format((float)$x['receipt_amount'],2);
        $safe=str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$text);$content="BT /F1 11 Tf 50 780 Td (".str_replace("\n",") Tj 0 -20 Td (",$safe).") Tj ET";$objects=["1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj","2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj","3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >> endobj","4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj","5 0 obj << /Length ".strlen($content)." >> stream\n$content\nendstream endobj"];$pdf="%PDF-1.4\n";$offsets=[0];foreach($objects as $o){$offsets[]=strlen($pdf);$pdf.=$o."\n";}$xref=strlen($pdf);$pdf.="xref\n0 6\n0000000000 65535 f \n";for($j=1;$j<=5;$j++)$pdf.=sprintf("%010d 00000 n \n",$offsets[$j]);$pdf.="trailer << /Size 6 /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";while(ob_get_level()>0)ob_end_clean();header('Content-Type:application/pdf');header('Content-Disposition:attachment; filename="receipt-'.$x['receipt_no'].'.pdf"');echo$pdf;exit;
    }
    opOut(false,'Invalid Online Payment action.',[],400);
}catch(InvalidArgumentException $e){if($pdo->inTransaction())$pdo->rollBack();opOut(false,$e->getMessage(),[],422);}
catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('online-payment.php: '.$e->getMessage());$h=strtolower((string)($_SERVER['HTTP_HOST']??''));opOut(false,(str_contains($h,'localhost')||str_contains($h,'127.0.0.1'))?'Online payment request failed: '.$e->getMessage():'Unable to complete the online payment request.',[],500);}

<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors','0');
error_reporting(E_ALL);

require_once dirname(__DIR__).'/includes/bootstrap.php';

function rmOut(bool $ok,string $message='',array $data=[],int $status=200):never{
    while(ob_get_level()>0)ob_end_clean();
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control:no-store');
    echo json_encode(['success'=>$ok,'message'=>$message,'data'=>$data],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function rmInput():array{$j=json_decode((string)file_get_contents('php://input'),true);return is_array($j)?$j:$_POST;}
function rmScope():array{$u=function_exists('current_user')?current_user():[];return['tenant_id'=>(int)($u['tenant_id']??$u['school_id']??$_SESSION['tenant_id']??0),'branch_id'=>(int)($u['branch_id']??$_SESSION['branch_id']??0),'user_id'=>(int)($u['id']??$u['user_id']??$_SESSION['user_id']??0),'role'=>(string)($u['role']??$u['role_name']??$_SESSION['role']??'')];}
function rmCsrf(array $i):void{$s=(string)($_SESSION['fee_csrf_token']??'');$r=(string)($i['csrf_token']??'');if($s===''||$r===''||!hash_equals($s,$r))rmOut(false,'Invalid or expired CSRF token. Refresh the page.',[],419);}
function rmName(string $a='s'):string{return "TRIM(CONCAT(COALESCE($a.first_name,''),CASE WHEN COALESCE($a.last_name,'')='' THEN '' ELSE CONCAT(' ',$a.last_name) END))";}
function rmCanCancel(array $s):bool{
    if(function_exists('has_permission')){
        try{return has_permission('fee_management','delete')||has_permission('fee_management','cancel');}catch(Throwable $e){}
    }
    $role=strtolower($s['role']);
    return in_array($role,['super_admin','super admin','admin','school_admin','school admin','accountant'],true);
}
function rmClean(?string $notes):string{return trim(preg_replace('/\s*\[(assignment|cancelled|duplicate):[^\]]+\]\s*/',' ',(string)$notes)??(string)$notes);}
function rmAssignmentId(?string $notes):int{return preg_match('/\[assignment:(\d+)\]/',(string)$notes,$m)?(int)$m[1]:0;}
function rmStatus(array $r):string{
    $notes=(string)($r['notes']??'');
    if(str_contains($notes,'[cancelled:'))return'cancelled';
    return $r['payment_status']==='reversed'?'reversed':$r['payment_status'];
}
function rmRows(PDO $pdo,array $s,array $f):array{
    $where=['r.tenant_id=:t','(:bs=0 OR r.branch_id=:b)'];$p=['t'=>$s['tenant_id'],'bs'=>$s['branch_id'],'b'=>$s['branch_id']];
    $search=trim((string)($f['search']??''));if($search!==''){$where[]='(r.receipt_no LIKE :q OR st.first_name LIKE :q OR st.last_name LIKE :q OR st.admission_no LIKE :q)';$p['q']='%'.$search.'%';}
    if(!empty($f['academic_year_id'])&&$f['academic_year_id']!=='all'){$where[]='r.academic_year_id=:y';$p['y']=(int)$f['academic_year_id'];}
    if(!empty($f['from_date'])){$where[]='DATE(r.receipt_date)>=:fd';$p['fd']=$f['from_date'];}
    if(!empty($f['to_date'])){$where[]='DATE(r.receipt_date)<=:td';$p['td']=$f['to_date'];}
    if(!empty($f['payment_method_id'])&&$f['payment_method_id']!=='all'){$where[]='EXISTS(SELECT 1 FROM fee_payments px WHERE px.tenant_id=r.tenant_id AND px.receipt_id=r.id AND px.payment_method_id=:pm)';$p['pm']=(int)$f['payment_method_id'];}
    $status=(string)($f['status']??'all');
    if($status==='cancelled')$where[]="r.notes LIKE '%[cancelled:%'";
    elseif($status==='reversed')$where[]="r.payment_status='reversed' AND r.notes NOT LIKE '%[cancelled:%'";
    elseif(in_array($status,['paid','partial'],true)){$where[]='r.payment_status=:ps';$where[]="r.notes NOT LIKE '%[cancelled:%'";$p['ps']=$status;}
    $page=max(1,(int)($f['page']??1));$per=min(100,max(5,(int)($f['per_page']??10)));
    $count=$pdo->prepare("SELECT COUNT(*) FROM fee_receipts r INNER JOIN students st ON st.id=r.student_id AND st.tenant_id=r.tenant_id WHERE ".implode(' AND ',$where));$count->execute($p);$total=(int)$count->fetchColumn();$last=max(1,(int)ceil($total/$per));$page=min($page,$last);$offset=($page-1)*$per;
    $name=rmName('st');$sql="SELECT r.*,$name student_name,st.admission_no,ay.year_name,
      GROUP_CONCAT(DISTINCT pm.method_name ORDER BY pm.method_name SEPARATOR ', ') payment_methods,
      GROUP_CONCAT(DISTINCT NULLIF(fp.reference_no,'') ORDER BY fp.id SEPARATOR ', ') reference_numbers,
      GROUP_CONCAT(DISTINCT fs.structure_name ORDER BY fs.structure_name SEPARATOR ', ') structure_names
      FROM fee_receipts r
      INNER JOIN students st ON st.id=r.student_id AND st.tenant_id=r.tenant_id
      LEFT JOIN academic_years ay ON ay.id=r.academic_year_id AND ay.tenant_id=r.tenant_id
      LEFT JOIN fee_payments fp ON fp.receipt_id=r.id AND fp.tenant_id=r.tenant_id
      LEFT JOIN payment_methods pm ON pm.id=fp.payment_method_id AND pm.tenant_id=fp.tenant_id
      LEFT JOIN student_fee_assignments a ON a.tenant_id=r.tenant_id AND a.student_id=r.student_id AND a.academic_year_id=r.academic_year_id
      LEFT JOIN fee_structures fs ON fs.id=a.fee_structure_id AND fs.tenant_id=a.tenant_id
      WHERE ".implode(' AND ',$where)." GROUP BY r.id ORDER BY r.receipt_date DESC,r.id DESC LIMIT {$per} OFFSET {$offset}";
    $q=$pdo->prepare($sql);$q->execute($p);$rows=$q->fetchAll(PDO::FETCH_ASSOC);foreach($rows as $idx=>&$r){$r['row_number']=$offset+$idx+1;$r['display_status']=rmStatus($r);$r['clean_notes']=rmClean($r['notes']??'');}
    return['records'=>$rows,'pagination'=>['total'=>$total,'page'=>$page,'per_page'=>$per,'last_page'=>$last]];
}
function rmReceipt(PDO $pdo,array $s,int $id):array{$x=rmRows($pdo,$s,['id'=>$id,'page'=>1,'per_page'=>5]);if(!$x['records']){$q=$pdo->prepare("SELECT id FROM fee_receipts WHERE id=:id AND tenant_id=:t AND (:bs=0 OR branch_id=:b)");$q->execute(['id'=>$id,'t'=>$s['tenant_id'],'bs'=>$s['branch_id'],'b'=>$s['branch_id']]);if(!$q->fetchColumn())throw new InvalidArgumentException('Receipt not found.');$x=rmRows($pdo,$s,['page'=>1,'per_page'=>100]);foreach($x['records'] as $r)if((int)$r['id']===$id)return$r;throw new InvalidArgumentException('Receipt not found.');}return$x['records'][0];}
function rmPdf(array $r,bool $duplicate=false):string{
    $lines=[
        $duplicate?'DUPLICATE RECEIPT':'SCHOOL FEE RECEIPT',
        'Receipt No: '.$r['receipt_no'],
        'Date: '.$r['receipt_date'],
        'Student: '.$r['student_name'],
        'Admission No: '.$r['admission_no'],
        'Academic Year: '.($r['year_name']?:'-'),
        'Fee Structure: '.($r['structure_names']?:'-'),
        'Payment Mode: '.($r['payment_methods']?:'-'),
        'Gross: Rs. '.number_format((float)$r['gross_amount'],2),
        'Discount: Rs. '.number_format((float)$r['discount_amount'],2),
        'Fine: Rs. '.number_format((float)$r['fine_amount'],2),
        'Paid: Rs. '.number_format((float)$r['paid_amount'],2),
        'Status: '.ucfirst($r['display_status'])
    ];
    $content="BT /F1 12 Tf 50 790 Td ";$first=true;foreach($lines as $line){$safe=str_replace(['\\','(',')',"\r","\n"],['\\\\','\\(','\\)',' ',' '],$line);$content.=($first?'':'0 -22 Td ')."($safe) Tj ";$first=false;}$content.="ET";
    $objects=["1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj","2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj","3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >> endobj","4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj","5 0 obj << /Length ".strlen($content)." >> stream\n$content\nendstream endobj"];
    $pdf="%PDF-1.4\n";$offsets=[0];foreach($objects as $o){$offsets[]=strlen($pdf);$pdf.=$o."\n";}$xref=strlen($pdf);$pdf.="xref\n0 6\n0000000000 65535 f \n";for($i=1;$i<=5;$i++)$pdf.=sprintf("%010d 00000 n \n",$offsets[$i]);$pdf.="trailer << /Size 6 /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";return$pdf;
}
function rmAudit(PDO $pdo,array $s,string $action,int $recordId,array $details=[]):void{
    try{
        $q=$pdo->prepare("INSERT INTO fee_audit_logs(tenant_id,branch_id,user_id,action_name,table_name,record_id,details) VALUES(:t,:b,:u,:a,'fee_receipts',:id,:d)");
        $q->execute(['t'=>$s['tenant_id'],'b'=>$s['branch_id']?:null,'u'=>$s['user_id']?:null,'a'=>$action,'id'=>$recordId,'d'=>json_encode($details,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
    }catch(Throwable $ignored){}
}

if(!isset($pdo)||!$pdo instanceof PDO)rmOut(false,'Database connection unavailable.',[],500);
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
if(empty($_SESSION['fee_csrf_token']))$_SESSION['fee_csrf_token']=bin2hex(random_bytes(32));
$s=rmScope();if($s['tenant_id']<=0||$s['user_id']<=0)rmOut(false,'Tenant or user session is missing.',[],401);
$i=rmInput();$action=strtolower(trim((string)($i['action']??$_GET['action']??'')));

try{
    if($action==='list'){
        $x=rmRows($pdo,$s,$_GET);
        $q=$pdo->prepare("SELECT id,year_name FROM academic_years WHERE tenant_id=:t ORDER BY is_current DESC,start_date DESC,id DESC");$q->execute(['t'=>$s['tenant_id']]);$years=$q->fetchAll(PDO::FETCH_ASSOC);
        $q=$pdo->prepare("SELECT id,method_name FROM payment_methods WHERE tenant_id=:t AND status='active' ORDER BY method_name");$q->execute(['t'=>$s['tenant_id']]);$methods=$q->fetchAll(PDO::FETCH_ASSOC);
        $q=$pdo->prepare("SELECT COUNT(*) total,COALESCE(SUM(CASE WHEN payment_status<>'reversed' AND notes NOT LIKE '%[cancelled:%' THEN paid_amount ELSE 0 END),0) collected,SUM(DATE(receipt_date)=CURDATE()) today,SUM(payment_status='reversed' OR notes LIKE '%[cancelled:%') cancelled FROM fee_receipts WHERE tenant_id=:t AND (:bs=0 OR branch_id=:b)");
        $q->execute(['t'=>$s['tenant_id'],'bs'=>$s['branch_id'],'b'=>$s['branch_id']]);$stats=$q->fetch(PDO::FETCH_ASSOC)?:[];
        rmOut(true,'Receipts loaded.',['records'=>$x['records'],'pagination'=>$x['pagination'],'meta'=>['years'=>$years,'methods'=>$methods],'stats'=>['total'=>(int)($stats['total']??0),'collected'=>(float)($stats['collected']??0),'today'=>(int)($stats['today']??0),'cancelled'=>(int)($stats['cancelled']??0)],'permissions'=>['cancel'=>rmCanCancel($s)],'csrf_token'=>$_SESSION['fee_csrf_token']]);
    }
    if($action==='detail'){
        $r=rmReceipt($pdo,$s,(int)($_GET['id']??0));
        $q=$pdo->prepare("SELECT fp.*,pm.method_name FROM fee_payments fp INNER JOIN payment_methods pm ON pm.id=fp.payment_method_id AND pm.tenant_id=fp.tenant_id WHERE fp.tenant_id=:t AND fp.receipt_id=:r ORDER BY fp.id");
        $q->execute(['t'=>$s['tenant_id'],'r'=>$r['id']]);rmOut(true,'Receipt loaded.',['receipt'=>$r,'payments'=>$q->fetchAll(PDO::FETCH_ASSOC)]);
    }
    if($action==='export'){
        $x=rmRows($pdo,$s,array_merge($_GET,['page'=>1,'per_page'=>100]));while(ob_get_level()>0)ob_end_clean();header('Content-Type:text/csv; charset=utf-8');header('Content-Disposition:attachment; filename="receipts-'.date('Ymd-His').'.csv"');$f=fopen('php://output','wb');fputcsv($f,['Receipt No','Date','Student','Admission No','Academic Year','Fee Structure','Payment Mode','Reference','Gross','Discount','Fine','Paid','Status']);foreach($x['records'] as $r)fputcsv($f,[$r['receipt_no'],$r['receipt_date'],$r['student_name'],$r['admission_no'],$r['year_name'],$r['structure_names'],$r['payment_methods'],$r['reference_numbers'],$r['gross_amount'],$r['discount_amount'],$r['fine_amount'],$r['paid_amount'],$r['display_status']]);fclose($f);exit;
    }
    if(in_array($action,['print','pdf','duplicate_print'],true)){
        $r=rmReceipt($pdo,$s,(int)($_GET['id']??0));$duplicate=$action==='duplicate_print';
        if($action==='pdf'){while(ob_get_level()>0)ob_end_clean();header('Content-Type:application/pdf');header('Content-Disposition:attachment; filename="'.($duplicate?'DUPLICATE-':'').$r['receipt_no'].'.pdf"');echo rmPdf($r,$duplicate);exit;}
        while(ob_get_level()>0)ob_end_clean();header('Content-Type:text/html; charset=utf-8');$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');echo '<!doctype html><html><head><meta charset="utf-8"><title>'.$e($r['receipt_no']).'</title><style>body{font-family:Arial;margin:30px}.box{max-width:760px;margin:auto;border:1px solid #ddd;padding:24px;border-radius:12px}h2{text-align:center}.duplicate{text-align:center;color:#b45309;font-weight:700}.row{display:flex;justify-content:space-between;border-bottom:1px dashed #ddd;padding:9px 0}.actions{text-align:center;margin-top:20px}@media print{.actions{display:none}}</style></head><body><div class="box">'.($duplicate?'<div class="duplicate">DUPLICATE RECEIPT</div>':'').'<h2>School Fee Receipt</h2>';foreach([['Receipt No',$r['receipt_no']],['Date',$r['receipt_date']],['Student',$r['student_name']],['Admission No',$r['admission_no']],['Academic Year',$r['year_name']],['Fee Structure',$r['structure_names']],['Payment Mode',$r['payment_methods']],['Reference',$r['reference_numbers']],['Gross','Rs. '.number_format((float)$r['gross_amount'],2)],['Discount','Rs. '.number_format((float)$r['discount_amount'],2)],['Fine','Rs. '.number_format((float)$r['fine_amount'],2)],['Paid','Rs. '.number_format((float)$r['paid_amount'],2)],['Status',ucfirst($r['display_status'])]] as [$l,$v])echo '<div class="row"><span>'.$e($l).'</span><strong>'.$e($v?:'-').'</strong></div>';echo '<div class="actions"><button onclick="window.print()">Print Receipt</button></div></div></body></html>';exit;
    }
    if($action==='duplicate'){
        rmCsrf($i);$id=(int)($i['id']??0);$r=rmReceipt($pdo,$s,$id);rmAudit($pdo,$s,'duplicate_receipt',$id,['receipt_no'=>$r['receipt_no']]);rmOut(true,'Duplicate receipt generated.',['duplicate_url'=>'../api/receipt-management.php?action=duplicate_print&id='.$id]);
    }
    if($action==='cancel'){
        rmCsrf($i);if(!rmCanCancel($s))rmOut(false,'You do not have permission to cancel receipts.',[],403);
        $id=(int)($i['id']??0);$reason=trim((string)($i['reason']??''));if(strlen($reason)<3)throw new InvalidArgumentException('Cancellation reason must be at least 3 characters.');
        $pdo->beginTransaction();$q=$pdo->prepare("SELECT * FROM fee_receipts WHERE id=:id AND tenant_id=:t AND (:bs=0 OR branch_id=:b) FOR UPDATE");$q->execute(['id'=>$id,'t'=>$s['tenant_id'],'bs'=>$s['branch_id'],'b'=>$s['branch_id']]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)throw new InvalidArgumentException('Receipt not found.');if(rmStatus($r)==='cancelled'||$r['payment_status']==='reversed')throw new InvalidArgumentException('Receipt is already cancelled or reversed.');
        $aid=rmAssignmentId($r['notes']??'');if($aid<=0)throw new InvalidArgumentException('Linked fee assignment was not found in this receipt.');
        $q=$pdo->prepare("SELECT * FROM student_fee_assignments WHERE id=:id AND tenant_id=:t AND student_id=:st AND academic_year_id=:y FOR UPDATE");$q->execute(['id'=>$aid,'t'=>$s['tenant_id'],'st'=>$r['student_id'],'y'=>$r['academic_year_id']]);$a=$q->fetch(PDO::FETCH_ASSOC);if(!$a)throw new InvalidArgumentException('Linked fee assignment not found.');
        $paid=max(0,round((float)$a['paid_amount']-(float)$r['paid_amount'],2));$net=max(0,round((float)$a['net_amount']+(float)$r['discount_amount']-(float)$r['fine_amount'],2));$con=max(0,round((float)$a['concession_amount']-(float)$r['discount_amount'],2));$bal=max(0,round($net-$paid,2));$ps=$bal<=0.01?'paid':($paid>0?'partial':'unpaid');
        $pdo->prepare("UPDATE student_fee_assignments SET concession_amount=:c,net_amount=:n,paid_amount=:p,balance_amount=:b,payment_status=:ps WHERE id=:id AND tenant_id=:t")->execute(['c'=>$con,'n'=>$net,'p'=>$paid,'b'=>$bal,'ps'=>$ps,'id'=>$aid,'t'=>$s['tenant_id']]);
        $notes=trim((string)$r['notes'].' [cancelled:'.date('Y-m-d H:i:s').'|user:'.$s['user_id'].'|reason:'.str_replace([']','|'],['', '-'],$reason).']');
        $pdo->prepare("UPDATE fee_receipts SET payment_status='reversed',notes=:n WHERE id=:id AND tenant_id=:t")->execute(['n'=>$notes,'id'=>$id,'t'=>$s['tenant_id']]);$pdo->prepare("UPDATE fee_payments SET status='reversed' WHERE tenant_id=:t AND receipt_id=:r")->execute(['t'=>$s['tenant_id'],'r'=>$id]);rmAudit($pdo,$s,'cancel_receipt',$id,['reason'=>$reason]);$pdo->commit();rmOut(true,'Receipt cancelled and fee balance restored.');
    }
    rmOut(false,'Invalid Receipt Management action.',[],400);
}catch(InvalidArgumentException $e){if($pdo->inTransaction())$pdo->rollBack();rmOut(false,$e->getMessage(),[],422);}
catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('receipt-management.php: '.$e->getMessage());$h=strtolower((string)($_SERVER['HTTP_HOST']??''));rmOut(false,(str_contains($h,'localhost')||str_contains($h,'127.0.0.1'))?'Receipt request failed: '.$e->getMessage():'Unable to complete the receipt request.',[],500);}

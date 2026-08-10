<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors','0');
error_reporting(E_ALL);

require_once dirname(__DIR__).'/includes/bootstrap.php';

function assignmentOut(bool $success,string $message='',array $data=[],int $status=200):never{
    while(ob_get_level()>0)ob_end_clean();
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['success'=>$success,'message'=>$message,'data'=>$data],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function assignmentInput():array{
    $json=json_decode((string)file_get_contents('php://input'),true);
    return is_array($json)?$json:$_POST;
}
function assignmentScope():array{
    $u=function_exists('current_user')?current_user():[];
    return[
        'tenant_id'=>(int)($u['tenant_id']??$u['school_id']??$_SESSION['tenant_id']??$_SESSION['school_id']??0),
        'branch_id'=>(int)($u['branch_id']??$_SESSION['branch_id']??$_SESSION['default_branch_id']??0)
    ];
}
function assignmentCsrf(array $input):void{
    $s=(string)($_SESSION['fee_csrf_token']??'');
    $r=(string)($input['csrf_token']??'');
    if($s===''||$r===''||!hash_equals($s,$r))assignmentOut(false,'Invalid or expired CSRF token. Refresh the page.',[],419);
}
function assignmentNameSql(string $a='s'):string{
    return "TRIM(CONCAT(COALESCE($a.first_name,''),CASE WHEN COALESCE($a.last_name,'')='' THEN '' ELSE CONCAT(' ',$a.last_name) END))";
}
function assignmentEnsureColumns(PDO $pdo):void{
    $columns=$pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='student_fee_assignments'")->fetchAll(PDO::FETCH_COLUMN);
    if(!in_array('assignment_date',$columns,true)){
        $pdo->exec("ALTER TABLE student_fee_assignments ADD COLUMN assignment_date DATE NULL AFTER fee_structure_id");
    }
    if(!in_array('assignment_status',$columns,true)){
        $pdo->exec("ALTER TABLE student_fee_assignments ADD COLUMN assignment_status ENUM('active','inactive') NOT NULL DEFAULT 'active' AFTER assignment_date");
    }
}
function assignmentMeta(PDO $pdo,array $scope):array{
    $q=$pdo->prepare("SELECT id,year_name FROM academic_years WHERE tenant_id=:t ORDER BY is_current DESC,start_date DESC,id DESC");$q->execute(['t'=>$scope['tenant_id']]);$years=$q->fetchAll(PDO::FETCH_ASSOC);
    $q=$pdo->prepare("SELECT id,class_name,academic_year_id FROM classes WHERE tenant_id=:t AND status='active' ORDER BY display_order,class_name");$q->execute(['t'=>$scope['tenant_id']]);$classes=$q->fetchAll(PDO::FETCH_ASSOC);
    $q=$pdo->prepare("SELECT id,class_id,section_name FROM sections WHERE tenant_id=:t AND status='active' ORDER BY section_name");$q->execute(['t'=>$scope['tenant_id']]);$sections=$q->fetchAll(PDO::FETCH_ASSOC);
    $name=assignmentNameSql('s');
    $q=$pdo->prepare("SELECT s.id,s.admission_no,CONCAT($name,' (',s.admission_no,')') student_name,e.academic_year_id,e.class_id,e.section_id,sec.section_name
        FROM students s INNER JOIN student_enrollments e ON e.student_id=s.id AND e.tenant_id=s.tenant_id
        LEFT JOIN sections sec ON sec.id=e.section_id AND sec.tenant_id=e.tenant_id
        WHERE s.tenant_id=:t AND (:bs=0 OR s.branch_id=:b) AND s.status='active' AND s.deleted_at IS NULL AND e.enrollment_status='active'
        ORDER BY s.first_name,s.last_name");
    $q->execute(['t'=>$scope['tenant_id'],'bs'=>$scope['branch_id'],'b'=>$scope['branch_id']]);$students=$q->fetchAll(PDO::FETCH_ASSOC);
    $q=$pdo->prepare("SELECT fs.id,fs.structure_name,fs.academic_year_id,fs.class_id,COALESCE(SUM(fsi.amount),0) gross_amount FROM fee_structures fs LEFT JOIN fee_structure_items fsi ON fsi.fee_structure_id=fs.id WHERE fs.tenant_id=:t AND fs.status='active' GROUP BY fs.id ORDER BY fs.structure_name");
    $q->execute(['t'=>$scope['tenant_id']]);$structures=$q->fetchAll(PDO::FETCH_ASSOC);
    return compact('years','classes','sections','students','structures');
}
function assignmentList(PDO $pdo,array $scope,array $f):array{
    $where=['a.tenant_id=:t','(:bs=0 OR s.branch_id=:b)','s.deleted_at IS NULL'];
    $p=['t'=>$scope['tenant_id'],'bs'=>$scope['branch_id'],'b'=>$scope['branch_id']];
    $search=trim((string)($f['search']??''));
    if($search!==''){$where[]='(s.first_name LIKE :q OR s.last_name LIKE :q OR s.admission_no LIKE :q OR fs.structure_name LIKE :q)';$p['q']='%'.$search.'%';}
    foreach(['academic_year_id'=>'a.academic_year_id','class_id'=>'e.class_id','section_id'=>'e.section_id'] as $key=>$col){$v=(string)($f[$key]??'all');if($v!=='all'&&$v!==''){$where[]="$col=:$key";$p[$key]=(int)$v;}}
    $as=(string)($f['assignment_status']??'all');if(in_array($as,['active','inactive'],true)){$where[]='a.assignment_status=:as';$p['as']=$as;}
    $ps=(string)($f['payment_status']??'all');if(in_array($ps,['unpaid','partial','paid','overdue'],true)){$where[]='a.payment_status=:ps';$p['ps']=$ps;}
    $page=max(1,(int)($f['page']??1));$per=min(100,max(5,(int)($f['per_page']??10)));
    $count=$pdo->prepare("SELECT COUNT(*) FROM student_fee_assignments a INNER JOIN students s ON s.id=a.student_id AND s.tenant_id=a.tenant_id LEFT JOIN student_enrollments e ON e.student_id=a.student_id AND e.academic_year_id=a.academic_year_id AND e.tenant_id=a.tenant_id INNER JOIN fee_structures fs ON fs.id=a.fee_structure_id WHERE ".implode(' AND ',$where));$count->execute($p);$total=(int)$count->fetchColumn();
    $last=max(1,(int)ceil($total/$per));$page=min($page,$last);$offset=($page-1)*$per;
    $name=assignmentNameSql('s');
    $q=$pdo->prepare("SELECT a.*,COALESCE(a.assignment_date,CURDATE()) assignment_date,$name student_name,s.admission_no,ay.year_name,c.class_name,sec.section_name,e.class_id,e.section_id,fs.structure_name
        FROM student_fee_assignments a
        INNER JOIN students s ON s.id=a.student_id AND s.tenant_id=a.tenant_id
        INNER JOIN fee_structures fs ON fs.id=a.fee_structure_id AND fs.tenant_id=a.tenant_id
        LEFT JOIN student_enrollments e ON e.student_id=a.student_id AND e.academic_year_id=a.academic_year_id AND e.tenant_id=a.tenant_id
        LEFT JOIN classes c ON c.id=e.class_id AND c.tenant_id=e.tenant_id
        LEFT JOIN sections sec ON sec.id=e.section_id AND sec.tenant_id=e.tenant_id
        LEFT JOIN academic_years ay ON ay.id=a.academic_year_id AND ay.tenant_id=a.tenant_id
        WHERE ".implode(' AND ',$where)." ORDER BY a.id DESC LIMIT {$per} OFFSET {$offset}");
    $q->execute($p);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as $i=>&$r)$r['row_number']=$offset+$i+1;
    return['records'=>$rows,'pagination'=>['total'=>$total,'page'=>$page,'per_page'=>$per,'last_page'=>$last]];
}

if(!isset($pdo)||!$pdo instanceof PDO)assignmentOut(false,'Database connection unavailable.',[],500);
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
if(empty($_SESSION['fee_csrf_token']))$_SESSION['fee_csrf_token']=bin2hex(random_bytes(32));
assignmentEnsureColumns($pdo);
$scope=assignmentScope();
if($scope['tenant_id']<=0)assignmentOut(false,'School tenant session was not found.',[],401);
$input=assignmentInput();$action=strtolower(trim((string)($input['action']??$_GET['action']??'')));

try{
 if($action==='list'){
  $list=assignmentList($pdo,$scope,$_GET);
  $q=$pdo->prepare("SELECT COUNT(*) total,SUM(assignment_status='active') active,COALESCE(SUM(balance_amount),0) balance,COUNT(DISTINCT student_id) students FROM student_fee_assignments WHERE tenant_id=:t");$q->execute(['t'=>$scope['tenant_id']]);$stats=$q->fetch(PDO::FETCH_ASSOC)?:[];
  assignmentOut(true,'Fee assignments loaded.',['meta'=>assignmentMeta($pdo,$scope),'records'=>$list['records'],'pagination'=>$list['pagination'],'stats'=>['total'=>(int)($stats['total']??0),'active'=>(int)($stats['active']??0),'balance'=>(float)($stats['balance']??0),'students'=>(int)($stats['students']??0)],'csrf_token'=>$_SESSION['fee_csrf_token']]);
 }

 if($action==='save'){
  assignmentCsrf($input);
  $id=(int)($input['id']??0);$mode=(string)($input['mode']??'individual');$year=(int)($input['academic_year_id']??0);$class=(int)($input['class_id']??0);$section=(int)($input['section_id']??0);$structure=(int)($input['fee_structure_id']??0);$date=(string)($input['assignment_date']??'');$status=(string)($input['assignment_status']??'active');$con=round(max(0,(float)($input['concession_amount']??0)),2);$sch=round(max(0,(float)($input['scholarship_amount']??0)),2);$studentIds=array_values(array_unique(array_filter(array_map('intval',(array)($input['student_ids']??[])))));
  if($year<=0||$class<=0||$section<=0||$structure<=0||!$studentIds)throw new InvalidArgumentException('Academic year, class, section, student and fee structure are required.');
  if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))throw new InvalidArgumentException('Valid assignment date is required.');
  if(!in_array($status,['active','inactive'],true))throw new InvalidArgumentException('Invalid assignment status.');
  $q=$pdo->prepare("SELECT fs.id,fs.academic_year_id,fs.class_id,COALESCE(SUM(fsi.amount),0) gross FROM fee_structures fs LEFT JOIN fee_structure_items fsi ON fsi.fee_structure_id=fs.id WHERE fs.id=:id AND fs.tenant_id=:t AND fs.status='active' GROUP BY fs.id");$q->execute(['id'=>$structure,'t'=>$scope['tenant_id']]);$fs=$q->fetch(PDO::FETCH_ASSOC);
  if(!$fs||(int)$fs['academic_year_id']!==$year||(int)$fs['class_id']!==$class)throw new InvalidArgumentException('Fee structure does not match the selected academic year and class.');
  $gross=round((float)$fs['gross'],2);if($gross<=0)throw new InvalidArgumentException('Fee structure has no valid amount.');if($con+$sch>$gross)throw new InvalidArgumentException('Concession and scholarship cannot exceed gross fee.');$net=round($gross-$con-$sch,2);

  $pdo->beginTransaction();$saved=0;
  foreach($studentIds as $studentId){
   $q=$pdo->prepare("SELECT e.student_id FROM student_enrollments e INNER JOIN students s ON s.id=e.student_id AND s.tenant_id=e.tenant_id WHERE e.tenant_id=:t AND e.student_id=:st AND e.academic_year_id=:y AND e.class_id=:c AND e.section_id=:sec AND e.enrollment_status='active' AND (:bs=0 OR s.branch_id=:b) LIMIT 1");
   $q->execute(['t'=>$scope['tenant_id'],'st'=>$studentId,'y'=>$year,'c'=>$class,'sec'=>$section,'bs'=>$scope['branch_id'],'b'=>$scope['branch_id']]);
   if(!$q->fetchColumn())throw new InvalidArgumentException('One or more selected students do not belong to the selected class and section.');
   if($id>0){
    $q=$pdo->prepare("SELECT paid_amount FROM student_fee_assignments WHERE id=:id AND tenant_id=:t FOR UPDATE");$q->execute(['id'=>$id,'t'=>$scope['tenant_id']]);$old=$q->fetch(PDO::FETCH_ASSOC);if(!$old)throw new InvalidArgumentException('Fee assignment not found.');$paid=(float)$old['paid_amount'];if($net+0.01<$paid)throw new InvalidArgumentException('Net amount cannot be lower than already paid amount.');$balance=max(0,$net-$paid);$pay=$balance<=0.01?'paid':($paid>0?'partial':'unpaid');
    $u=$pdo->prepare("UPDATE student_fee_assignments SET student_id=:st,academic_year_id=:y,fee_structure_id=:fs,assignment_date=:d,assignment_status=:as,gross_amount=:g,concession_amount=:c,scholarship_amount=:s,net_amount=:n,balance_amount=:b,payment_status=:ps WHERE id=:id AND tenant_id=:t");
    $u->execute(['st'=>$studentId,'y'=>$year,'fs'=>$structure,'d'=>$date,'as'=>$status,'g'=>$gross,'c'=>$con,'s'=>$sch,'n'=>$net,'b'=>$balance,'ps'=>$pay,'id'=>$id,'t'=>$scope['tenant_id']]);$saved=1;break;
   }else{
    $q=$pdo->prepare("INSERT INTO student_fee_assignments(tenant_id,student_id,academic_year_id,fee_structure_id,assignment_date,assignment_status,gross_amount,concession_amount,scholarship_amount,net_amount,paid_amount,balance_amount,payment_status) VALUES(:t,:st,:y,:fs,:d,:as,:g,:c,:s,:n,0,:b,'unpaid') ON DUPLICATE KEY UPDATE assignment_date=VALUES(assignment_date),assignment_status=VALUES(assignment_status),gross_amount=VALUES(gross_amount),concession_amount=VALUES(concession_amount),scholarship_amount=VALUES(scholarship_amount),net_amount=VALUES(net_amount),balance_amount=GREATEST(0,VALUES(net_amount)-paid_amount),payment_status=CASE WHEN paid_amount>=VALUES(net_amount) THEN 'paid' WHEN paid_amount>0 THEN 'partial' ELSE 'unpaid' END");
    $q->execute(['t'=>$scope['tenant_id'],'st'=>$studentId,'y'=>$year,'fs'=>$structure,'d'=>$date,'as'=>$status,'g'=>$gross,'c'=>$con,'s'=>$sch,'n'=>$net,'b'=>$net]);$saved++;
   }
  }
  $pdo->commit();assignmentOut(true,$mode==='bulk'?$saved.' student fee assignment(s) saved successfully.':($id>0?'Fee assignment updated successfully.':'Fee assignment created successfully.'));
 }

 if($action==='delete'){
  assignmentCsrf($input);$id=(int)($input['id']??0);if($id<=0)throw new InvalidArgumentException('Valid assignment ID is required.');
  $q=$pdo->prepare("SELECT paid_amount FROM student_fee_assignments WHERE id=:id AND tenant_id=:t");$q->execute(['id'=>$id,'t'=>$scope['tenant_id']]);$paid=$q->fetchColumn();if($paid===false)throw new InvalidArgumentException('Fee assignment not found.');if((float)$paid>0)throw new InvalidArgumentException('Assignments with payments cannot be deleted.');
  $q=$pdo->prepare("DELETE FROM student_fee_assignments WHERE id=:id AND tenant_id=:t");$q->execute(['id'=>$id,'t'=>$scope['tenant_id']]);assignmentOut(true,'Fee assignment deleted successfully.');
 }

 assignmentOut(false,'Invalid Fee Assignment action.',[],400);
}catch(InvalidArgumentException $e){
 if($pdo->inTransaction())$pdo->rollBack();assignmentOut(false,$e->getMessage(),[],422);
}catch(Throwable $e){
 if($pdo->inTransaction())$pdo->rollBack();error_log('fee-assignment.php: '.$e->getMessage());$h=strtolower((string)($_SERVER['HTTP_HOST']??''));assignmentOut(false,(str_contains($h,'localhost')||str_contains($h,'127.0.0.1'))?'Fee Assignment request failed: '.$e->getMessage():'Unable to complete the Fee Assignment request.',[],500);
}

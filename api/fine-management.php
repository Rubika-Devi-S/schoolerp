<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors','0');
error_reporting(E_ALL);

require_once dirname(__DIR__).'/includes/bootstrap.php';

function fmOut(bool $ok,string $message='',array $data=[],int $status=200):never{
    while(ob_get_level()>0)ob_end_clean();
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control:no-store');
    echo json_encode(['success'=>$ok,'message'=>$message,'data'=>$data],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function fmInput():array{$j=json_decode((string)file_get_contents('php://input'),true);return is_array($j)?$j:$_POST;}
function fmScope():array{$u=function_exists('current_user')?current_user():[];return['tenant_id'=>(int)($u['tenant_id']??$u['school_id']??$_SESSION['tenant_id']??0),'branch_id'=>(int)($u['branch_id']??$_SESSION['branch_id']??0),'user_id'=>(int)($u['id']??$u['user_id']??$_SESSION['user_id']??0),'role'=>(string)($u['role']??$u['role_name']??$_SESSION['role']??'')];}
function fmCsrf(array $i):void{$s=(string)($_SESSION['fee_csrf_token']??'');$r=(string)($i['csrf_token']??'');if($s===''||$r===''||!hash_equals($s,$r))fmOut(false,'Invalid or expired CSRF token. Refresh the page.',[],419);}
function fmName(string $a='s'):string{return "TRIM(CONCAT(COALESCE($a.first_name,''),CASE WHEN COALESCE($a.last_name,'')='' THEN '' ELSE CONCAT(' ',$a.last_name) END))";}
function fmCan(array $s,string $action):bool{
    if(function_exists('has_permission')){try{return has_permission('fee_management',$action);}catch(Throwable $e){}}
    $role=strtolower($s['role']);
    if(in_array($role,['super_admin','super admin','admin','school_admin','school admin'],true))return true;
    return $action==='waive'&&in_array($role,['accountant','principal'],true);
}
function fmEnsure(PDO $pdo):void{
    $pdo->exec("CREATE TABLE IF NOT EXISTS fine_rules(
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        tenant_id BIGINT UNSIGNED NOT NULL,
        branch_id BIGINT UNSIGNED NULL,
        rule_code VARCHAR(40) NOT NULL,
        rule_name VARCHAR(120) NOT NULL,
        academic_year_id BIGINT UNSIGNED NOT NULL,
        scope_type ENUM('all','class','student') NOT NULL DEFAULT 'all',
        class_id BIGINT UNSIGNED NULL,
        student_id BIGINT UNSIGNED NULL,
        fee_structure_id BIGINT UNSIGNED NULL,
        calculation_type ENUM('fixed','daily','percentage') NOT NULL DEFAULT 'fixed',
        fine_value DECIMAL(12,2) NOT NULL,
        grace_days INT UNSIGNED NOT NULL DEFAULT 0,
        maximum_fine DECIMAL(12,2) NOT NULL DEFAULT 0,
        description VARCHAR(500) NULL,
        status ENUM('active','inactive') NOT NULL DEFAULT 'active',
        created_by BIGINT UNSIGNED NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY(id),
        UNIQUE KEY uq_fine_rule_code(tenant_id,rule_code),
        UNIQUE KEY uq_fine_rule_name(tenant_id,rule_name),
        KEY idx_fine_rule_filter(tenant_id,academic_year_id,status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_fines(
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        tenant_id BIGINT UNSIGNED NOT NULL,
        branch_id BIGINT UNSIGNED NULL,
        assignment_id BIGINT UNSIGNED NOT NULL,
        fine_rule_id BIGINT UNSIGNED NULL,
        student_id BIGINT UNSIGNED NOT NULL,
        academic_year_id BIGINT UNSIGNED NOT NULL,
        fee_structure_id BIGINT UNSIGNED NOT NULL,
        due_date DATE NULL,
        calculated_on DATE NOT NULL,
        days_late INT UNSIGNED NOT NULL DEFAULT 0,
        fine_amount DECIMAL(12,2) NOT NULL,
        paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        waived_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        fine_status ENUM('pending','paid','waived') NOT NULL DEFAULT 'pending',
        source_type ENUM('automatic','manual') NOT NULL DEFAULT 'automatic',
        remarks VARCHAR(500) NULL,
        waived_by BIGINT UNSIGNED NULL,
        waived_at DATETIME NULL,
        created_by BIGINT UNSIGNED NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY(id),
        UNIQUE KEY uq_auto_fine(tenant_id,assignment_id,fine_rule_id),
        KEY idx_student_fine_filter(tenant_id,academic_year_id,fine_status),
        KEY idx_student_fine_assignment(tenant_id,assignment_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS fine_history(
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        tenant_id BIGINT UNSIGNED NOT NULL,
        branch_id BIGINT UNSIGNED NULL,
        fine_id BIGINT UNSIGNED NULL,
        action_name VARCHAR(60) NOT NULL,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        remarks VARCHAR(500) NULL,
        user_id BIGINT UNSIGNED NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(id),
        KEY idx_fine_history(tenant_id,created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function fmHistory(PDO $pdo,array $s,?int $id,string $action,float $amount,string $remarks):void{
    $q=$pdo->prepare("INSERT INTO fine_history(tenant_id,branch_id,fine_id,action_name,amount,remarks,user_id) VALUES(:t,:b,:f,:a,:m,:r,:u)");
    $q->execute(['t'=>$s['tenant_id'],'b'=>$s['branch_id']?:null,'f'=>$id,'a'=>$action,'m'=>$amount,'r'=>$remarks?:null,'u'=>$s['user_id']?:null]);
}
function fmAssignmentFineTotal(PDO $pdo,int $tenant,int $assignmentId):float{
    $q=$pdo->prepare("SELECT COALESCE(SUM(CASE WHEN fine_status='pending' THEN fine_amount-paid_amount-waived_amount ELSE 0 END),0) FROM student_fines WHERE tenant_id=:t AND assignment_id=:a");
    $q->execute(['t'=>$tenant,'a'=>$assignmentId]);return max(0,(float)$q->fetchColumn());
}
function fmSyncAssignment(PDO $pdo,int $tenant,int $assignmentId,float $delta):void{
    $q=$pdo->prepare("SELECT * FROM student_fee_assignments WHERE id=:id AND tenant_id=:t FOR UPDATE");$q->execute(['id'=>$assignmentId,'t'=>$tenant]);$a=$q->fetch(PDO::FETCH_ASSOC);if(!$a)return;
    $net=max((float)$a['paid_amount'],round((float)$a['net_amount']+$delta,2));
    $balance=max(0,round($net-(float)$a['paid_amount'],2));
    $status=$balance<=0.01?'paid':((float)$a['paid_amount']>0?'partial':'unpaid');
    $q=$pdo->prepare("UPDATE student_fee_assignments SET net_amount=:n,balance_amount=:b,payment_status=:s WHERE id=:id AND tenant_id=:t");
    $q->execute(['n'=>$net,'b'=>$balance,'s'=>$status,'id'=>$assignmentId,'t'=>$tenant]);
}
function fmCalculate(PDO $pdo,array $s):int{
    $q=$pdo->prepare("SELECT r.* FROM fine_rules r WHERE r.tenant_id=:t AND r.status='active'");$q->execute(['t'=>$s['tenant_id']]);$rules=$q->fetchAll(PDO::FETCH_ASSOC);
    $created=0;
    foreach($rules as $rule){
        $where=['a.tenant_id=:t','a.academic_year_id=:y','a.balance_amount>0.01'];
        $p=['t'=>$s['tenant_id'],'y'=>$rule['academic_year_id']];
        if((int)$rule['fee_structure_id']>0){$where[]='a.fee_structure_id=:fs';$p['fs']=$rule['fee_structure_id'];}
        if($rule['scope_type']==='student'){$where[]='a.student_id=:st';$p['st']=$rule['student_id'];}
        elseif($rule['scope_type']==='class'){$where[]='EXISTS(SELECT 1 FROM student_enrollments e WHERE e.tenant_id=a.tenant_id AND e.student_id=a.student_id AND e.academic_year_id=a.academic_year_id AND e.class_id=:c AND e.enrollment_status="active")';$p['c']=$rule['class_id'];}
        $sql="SELECT a.*,(SELECT MIN(due_date) FROM fee_structure_items WHERE fee_structure_id=a.fee_structure_id AND due_date IS NOT NULL) due_date FROM student_fee_assignments a WHERE ".implode(' AND ',$where);
        $aQ=$pdo->prepare($sql);$aQ->execute($p);
        foreach($aQ->fetchAll(PDO::FETCH_ASSOC) as $a){
            if(empty($a['due_date']))continue;
            $graceDate=(new DateTimeImmutable($a['due_date']))->modify('+'.(int)$rule['grace_days'].' days');
            $today=new DateTimeImmutable('today');if($today<=$graceDate)continue;
            $days=(int)$graceDate->diff($today)->format('%a');
            $amount=match($rule['calculation_type']){
                'daily'=>(float)$rule['fine_value']*$days,
                'percentage'=>(float)$a['balance_amount']*(float)$rule['fine_value']/100,
                default=>(float)$rule['fine_value']
            };
            if((float)$rule['maximum_fine']>0)$amount=min($amount,(float)$rule['maximum_fine']);
            $amount=round(max(0,$amount),2);if($amount<=0)continue;
            $check=$pdo->prepare("SELECT * FROM student_fines WHERE tenant_id=:t AND assignment_id=:a AND fine_rule_id=:r FOR UPDATE");$check->execute(['t'=>$s['tenant_id'],'a'=>$a['id'],'r'=>$rule['id']]);$existing=$check->fetch(PDO::FETCH_ASSOC);
            if($existing){
                if($existing['fine_status']!=='pending')continue;
                $delta=round($amount-(float)$existing['fine_amount'],2);
                if(abs($delta)>0.001){$pdo->prepare("UPDATE student_fines SET days_late=:d,fine_amount=:f,calculated_on=CURDATE() WHERE id=:id")->execute(['d'=>$days,'f'=>$amount,'id'=>$existing['id']]);fmSyncAssignment($pdo,$s['tenant_id'],(int)$a['id'],$delta);}
            }else{
                $ins=$pdo->prepare("INSERT INTO student_fines(tenant_id,branch_id,assignment_id,fine_rule_id,student_id,academic_year_id,fee_structure_id,due_date,calculated_on,days_late,fine_amount,fine_status,source_type,remarks,created_by) VALUES(:t,:b,:a,:r,:st,:y,:fs,:dd,CURDATE(),:days,:f,'pending','automatic',:remarks,:u)");
                $ins->execute(['t'=>$s['tenant_id'],'b'=>$s['branch_id']?:null,'a'=>$a['id'],'r'=>$rule['id'],'st'=>$a['student_id'],'y'=>$a['academic_year_id'],'fs'=>$a['fee_structure_id'],'dd'=>$a['due_date'],'days'=>$days,'f'=>$amount,'remarks'=>'Automatically calculated from '.$rule['rule_name'],'u'=>$s['user_id']]);$fineId=(int)$pdo->lastInsertId();fmSyncAssignment($pdo,$s['tenant_id'],(int)$a['id'],$amount);fmHistory($pdo,$s,$fineId,'automatic_fine',$amount,$rule['rule_name']);$created++;
            }
        }
    }
    return $created;
}
function fmMeta(PDO $pdo,array $s):array{
    $q=$pdo->prepare("SELECT id,year_name FROM academic_years WHERE tenant_id=:t ORDER BY is_current DESC,start_date DESC,id DESC");$q->execute(['t'=>$s['tenant_id']]);$years=$q->fetchAll(PDO::FETCH_ASSOC);
    $q=$pdo->prepare("SELECT id,class_name,academic_year_id FROM classes WHERE tenant_id=:t AND status='active' ORDER BY display_order,class_name");$q->execute(['t'=>$s['tenant_id']]);$classes=$q->fetchAll(PDO::FETCH_ASSOC);
    $name=fmName('st');$q=$pdo->prepare("SELECT st.id,CONCAT($name,' (',st.admission_no,')') student_name,e.academic_year_id,e.class_id FROM students st INNER JOIN student_enrollments e ON e.student_id=st.id AND e.tenant_id=st.tenant_id WHERE st.tenant_id=:t AND (:bs=0 OR st.branch_id=:b) AND st.status='active' AND st.deleted_at IS NULL AND e.enrollment_status='active' ORDER BY st.first_name");$q->execute(['t'=>$s['tenant_id'],'bs'=>$s['branch_id'],'b'=>$s['branch_id']]);$students=$q->fetchAll(PDO::FETCH_ASSOC);
    $q=$pdo->prepare("SELECT id,structure_name,academic_year_id,class_id FROM fee_structures WHERE tenant_id=:t AND status='active' ORDER BY structure_name");$q->execute(['t'=>$s['tenant_id']]);$structures=$q->fetchAll(PDO::FETCH_ASSOC);
    $q=$pdo->prepare("SELECT id,rule_name FROM fine_rules WHERE tenant_id=:t ORDER BY rule_name");$q->execute(['t'=>$s['tenant_id']]);$rules=$q->fetchAll(PDO::FETCH_ASSOC);
    $q=$pdo->prepare("SELECT a.id,a.student_id,a.academic_year_id,CONCAT(fs.structure_name,' - Balance ₹',FORMAT(a.balance_amount,2)) assignment_name FROM student_fee_assignments a INNER JOIN fee_structures fs ON fs.id=a.fee_structure_id AND fs.tenant_id=a.tenant_id WHERE a.tenant_id=:t AND a.balance_amount>0 ORDER BY fs.structure_name");$q->execute(['t'=>$s['tenant_id']]);$assignments=$q->fetchAll(PDO::FETCH_ASSOC);
    return compact('years','classes','students','structures','rules','assignments');
}

if(!isset($pdo)||!$pdo instanceof PDO)fmOut(false,'Database connection unavailable.',[],500);
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
if(empty($_SESSION['fee_csrf_token']))$_SESSION['fee_csrf_token']=bin2hex(random_bytes(32));
$s=fmScope();if($s['tenant_id']<=0||$s['user_id']<=0)fmOut(false,'Tenant or user session is missing.',[],401);
fmEnsure($pdo);
$i=fmInput();$action=strtolower(trim((string)($i['action']??$_GET['action']??'')));

try{
    if($action==='list'){
        $where=['f.tenant_id=:t'];$p=['t'=>$s['tenant_id']];
        $search=trim((string)($_GET['search']??''));if($search!==''){$where[]='(st.first_name LIKE :q OR st.last_name LIKE :q OR st.admission_no LIKE :q OR r.rule_name LIKE :q)';$p['q']='%'.$search.'%';}
        foreach(['academic_year_id'=>'f.academic_year_id','fine_rule_id'=>'f.fine_rule_id'] as $k=>$col){$v=(string)($_GET[$k]??'all');if($v!==''&&$v!=='all'){$where[]="$col=:$k";$p[$k]=(int)$v;}}
        if(!empty($_GET['class_id'])&&$_GET['class_id']!=='all'){$where[]='e.class_id=:class_id';$p['class_id']=(int)$_GET['class_id'];}
        foreach(['fine_status','source_type'] as $k){$v=(string)($_GET[$k]??'all');if($v!==''&&$v!=='all'){$where[]="f.$k=:$k";$p[$k]=$v;}}
        $page=max(1,(int)($_GET['page']??1));$per=min(100,max(5,(int)($_GET['per_page']??10)));
        $count=$pdo->prepare("SELECT COUNT(*) FROM student_fines f INNER JOIN students st ON st.id=f.student_id LEFT JOIN fine_rules r ON r.id=f.fine_rule_id LEFT JOIN student_enrollments e ON e.student_id=f.student_id AND e.academic_year_id=f.academic_year_id WHERE ".implode(' AND ',$where));$count->execute($p);$total=(int)$count->fetchColumn();$last=max(1,(int)ceil($total/$per));$page=min($page,$last);$offset=($page-1)*$per;
        $name=fmName('st');$q=$pdo->prepare("SELECT f.*,$name student_name,st.admission_no,ay.year_name,c.class_name,fs.structure_name,r.rule_name FROM student_fines f INNER JOIN students st ON st.id=f.student_id INNER JOIN academic_years ay ON ay.id=f.academic_year_id INNER JOIN fee_structures fs ON fs.id=f.fee_structure_id LEFT JOIN fine_rules r ON r.id=f.fine_rule_id LEFT JOIN student_enrollments e ON e.student_id=f.student_id AND e.academic_year_id=f.academic_year_id LEFT JOIN classes c ON c.id=e.class_id WHERE ".implode(' AND ',$where)." ORDER BY f.id DESC LIMIT {$per} OFFSET {$offset}");
        $q->execute($p);$records=$q->fetchAll(PDO::FETCH_ASSOC);foreach($records as $idx=>&$r)$r['row_number']=$offset+$idx+1;
        $q=$pdo->prepare("SELECT * FROM fine_rules WHERE tenant_id=:t ORDER BY id DESC");$q->execute(['t'=>$s['tenant_id']]);$rules=$q->fetchAll(PDO::FETCH_ASSOC);
        try{$h=$pdo->prepare("SELECT h.*,r.rule_name,".fmName('st')." student_name,COALESCE(u.name,u.username,u.email) user_name FROM fine_history h LEFT JOIN student_fines f ON f.id=h.fine_id LEFT JOIN fine_rules r ON r.id=f.fine_rule_id LEFT JOIN students st ON st.id=f.student_id LEFT JOIN users u ON u.id=h.user_id WHERE h.tenant_id=:t ORDER BY h.id DESC LIMIT 100");$h->execute(['t'=>$s['tenant_id']]);$history=$h->fetchAll(PDO::FETCH_ASSOC);}catch(Throwable $e){$h=$pdo->prepare("SELECT h.*,r.rule_name,".fmName('st')." student_name,NULL user_name FROM fine_history h LEFT JOIN student_fines f ON f.id=h.fine_id LEFT JOIN fine_rules r ON r.id=f.fine_rule_id LEFT JOIN students st ON st.id=f.student_id WHERE h.tenant_id=:t ORDER BY h.id DESC LIMIT 100");$h->execute(['t'=>$s['tenant_id']]);$history=$h->fetchAll(PDO::FETCH_ASSOC);}
        $q=$pdo->prepare("SELECT COALESCE(SUM(fine_amount),0) total,COALESCE(SUM(CASE WHEN fine_status='paid' THEN paid_amount ELSE 0 END),0) paid,COALESCE(SUM(CASE WHEN fine_status='pending' THEN fine_amount-paid_amount-waived_amount ELSE 0 END),0) pending,COALESCE(SUM(waived_amount),0) waived FROM student_fines WHERE tenant_id=:t");$q->execute(['t'=>$s['tenant_id']]);$stats=$q->fetch(PDO::FETCH_ASSOC)?:[];
        fmOut(true,'Fine management loaded.',['records'=>$records,'rules'=>$rules,'history'=>$history,'meta'=>fmMeta($pdo,$s),'pagination'=>['total'=>$total,'page'=>$page,'per_page'=>$per,'last_page'=>$last],'stats'=>array_map('floatval',$stats),'permissions'=>['waive'=>fmCan($s,'waive'),'delete'=>fmCan($s,'delete')],'csrf_token'=>$_SESSION['fee_csrf_token']]);
    }
    if($action==='calculate'){fmCsrf($i);$pdo->beginTransaction();$created=fmCalculate($pdo,$s);$pdo->commit();fmOut(true,$created.' new fine(s) calculated successfully.');}
    if($action==='save_rule'){
        fmCsrf($i);$id=(int)($i['id']??0);$code=strtoupper(trim((string)($i['rule_code']??'')));$name=trim((string)($i['rule_name']??''));$year=(int)($i['academic_year_id']??0);$scope=(string)($i['scope_type']??'all');$class=(int)($i['class_id']??0);$student=(int)($i['student_id']??0);$structure=(int)($i['fee_structure_id']??0);$calc=(string)($i['calculation_type']??'fixed');$value=round((float)($i['fine_value']??0),2);$grace=max(0,(int)($i['grace_days']??0));$max=round(max(0,(float)($i['maximum_fine']??0)),2);$status=(string)($i['status']??'active');$description=trim((string)($i['description']??''));
        if($code===''||$name===''||$year<=0||$value<=0)throw new InvalidArgumentException('Rule code, name, academic year and positive fine value are required.');if($calc==='percentage'&&$value>100)throw new InvalidArgumentException('Percentage cannot exceed 100.');if($scope==='class'&&$class<=0)throw new InvalidArgumentException('Class is required.');if($scope==='student'&&$student<=0)throw new InvalidArgumentException('Student is required.');
        $q=$pdo->prepare("SELECT id FROM fine_rules WHERE tenant_id=:t AND id<>:id AND (UPPER(rule_code)=UPPER(:c) OR LOWER(rule_name)=LOWER(:n))");$q->execute(['t'=>$s['tenant_id'],'id'=>$id,'c'=>$code,'n'=>$name]);if($q->fetchColumn())throw new InvalidArgumentException('Fine rule code or name already exists.');
        if($id){$q=$pdo->prepare("UPDATE fine_rules SET rule_code=:c,rule_name=:n,academic_year_id=:y,scope_type=:sc,class_id=:cl,student_id=:st,fee_structure_id=:fs,calculation_type=:ct,fine_value=:v,grace_days=:g,maximum_fine=:m,description=:d,status=:status WHERE id=:id AND tenant_id=:t");$q->execute(['c'=>$code,'n'=>$name,'y'=>$year,'sc'=>$scope,'cl'=>$class?:null,'st'=>$student?:null,'fs'=>$structure?:null,'ct'=>$calc,'v'=>$value,'g'=>$grace,'m'=>$max,'d'=>$description?:null,'status'=>$status,'id'=>$id,'t'=>$s['tenant_id']]);}else{$q=$pdo->prepare("INSERT INTO fine_rules(tenant_id,branch_id,rule_code,rule_name,academic_year_id,scope_type,class_id,student_id,fee_structure_id,calculation_type,fine_value,grace_days,maximum_fine,description,status,created_by) VALUES(:t,:b,:c,:n,:y,:sc,:cl,:st,:fs,:ct,:v,:g,:m,:d,:status,:u)");$q->execute(['t'=>$s['tenant_id'],'b'=>$s['branch_id']?:null,'c'=>$code,'n'=>$name,'y'=>$year,'sc'=>$scope,'cl'=>$class?:null,'st'=>$student?:null,'fs'=>$structure?:null,'ct'=>$calc,'v'=>$value,'g'=>$grace,'m'=>$max,'d'=>$description?:null,'status'=>$status,'u'=>$s['user_id']]);$id=(int)$pdo->lastInsertId();}fmOut(true,'Fine rule saved successfully.',['id'=>$id]);
    }
    if($action==='delete_rule'){fmCsrf($i);$id=(int)($i['id']??0);$q=$pdo->prepare("SELECT COUNT(*) FROM student_fines WHERE tenant_id=:t AND fine_rule_id=:id");$q->execute(['t'=>$s['tenant_id'],'id'=>$id]);if((int)$q->fetchColumn()>0)throw new InvalidArgumentException('Fine rule is already used and cannot be deleted.');$pdo->prepare("DELETE FROM fine_rules WHERE id=:id AND tenant_id=:t")->execute(['id'=>$id,'t'=>$s['tenant_id']]);fmOut(true,'Fine rule deleted.');}
    if($action==='save_manual'){
        fmCsrf($i);$assignment=(int)($i['assignment_id']??0);$amount=round((float)($i['fine_amount']??0),2);$date=(string)($i['fine_date']??'');$remarks=trim((string)($i['remarks']??''));if($assignment<=0||$amount<=0||$date===''||$remarks==='')throw new InvalidArgumentException('Assignment, fine amount, date and reason are required.');
        $pdo->beginTransaction();$q=$pdo->prepare("SELECT * FROM student_fee_assignments WHERE id=:id AND tenant_id=:t FOR UPDATE");$q->execute(['id'=>$assignment,'t'=>$s['tenant_id']]);$a=$q->fetch(PDO::FETCH_ASSOC);if(!$a)throw new InvalidArgumentException('Fee assignment not found.');$ins=$pdo->prepare("INSERT INTO student_fines(tenant_id,branch_id,assignment_id,fine_rule_id,student_id,academic_year_id,fee_structure_id,due_date,calculated_on,days_late,fine_amount,fine_status,source_type,remarks,created_by) VALUES(:t,:b,:a,NULL,:st,:y,:fs,NULL,:d,0,:f,'pending','manual',:r,:u)");$ins->execute(['t'=>$s['tenant_id'],'b'=>$s['branch_id']?:null,'a'=>$assignment,'st'=>$a['student_id'],'y'=>$a['academic_year_id'],'fs'=>$a['fee_structure_id'],'d'=>$date,'f'=>$amount,'r'=>$remarks,'u'=>$s['user_id']]);$id=(int)$pdo->lastInsertId();fmSyncAssignment($pdo,$s['tenant_id'],$assignment,$amount);fmHistory($pdo,$s,$id,'manual_fine',$amount,$remarks);$pdo->commit();fmOut(true,'Student fine applied successfully.');
    }
    if($action==='waive'){
        fmCsrf($i);if(!fmCan($s,'waive'))fmOut(false,'You do not have permission to waive fines.',[],403);$id=(int)($i['id']??0);$reason=trim((string)($i['reason']??''));if(strlen($reason)<3)throw new InvalidArgumentException('Waiver reason must be at least 3 characters.');
        $pdo->beginTransaction();$q=$pdo->prepare("SELECT * FROM student_fines WHERE id=:id AND tenant_id=:t FOR UPDATE");$q->execute(['id'=>$id,'t'=>$s['tenant_id']]);$f=$q->fetch(PDO::FETCH_ASSOC);if(!$f||$f['fine_status']!=='pending')throw new InvalidArgumentException('Pending fine not found.');$remaining=max(0,(float)$f['fine_amount']-(float)$f['paid_amount']-(float)$f['waived_amount']);$pdo->prepare("UPDATE student_fines SET waived_amount=waived_amount+:w,fine_status='waived',waived_by=:u,waived_at=NOW(),remarks=CONCAT(COALESCE(remarks,''),' | Waived: ',:r) WHERE id=:id AND tenant_id=:t")->execute(['w'=>$remaining,'u'=>$s['user_id'],'r'=>$reason,'id'=>$id,'t'=>$s['tenant_id']]);fmSyncAssignment($pdo,$s['tenant_id'],(int)$f['assignment_id'],-$remaining);fmHistory($pdo,$s,$id,'waive_fine',$remaining,$reason);$pdo->commit();fmOut(true,'Fine waived and assignment balance updated.');
    }
    if($action==='delete_fine'){
        fmCsrf($i);if(!fmCan($s,'delete'))fmOut(false,'You do not have permission to delete fines.',[],403);$id=(int)($i['id']??0);$pdo->beginTransaction();$q=$pdo->prepare("SELECT * FROM student_fines WHERE id=:id AND tenant_id=:t FOR UPDATE");$q->execute(['id'=>$id,'t'=>$s['tenant_id']]);$f=$q->fetch(PDO::FETCH_ASSOC);if(!$f)throw new InvalidArgumentException('Fine not found.');if($f['fine_status']==='paid'||(float)$f['paid_amount']>0)throw new InvalidArgumentException('Paid fine cannot be deleted.');$remaining=max(0,(float)$f['fine_amount']-(float)$f['waived_amount']);$pdo->prepare("DELETE FROM student_fines WHERE id=:id AND tenant_id=:t")->execute(['id'=>$id,'t'=>$s['tenant_id']]);fmSyncAssignment($pdo,$s['tenant_id'],(int)$f['assignment_id'],-$remaining);fmHistory($pdo,$s,$id,'delete_fine',$remaining,$f['remarks']??'');$pdo->commit();fmOut(true,'Fine deleted and assignment balance restored.');
    }
    fmOut(false,'Invalid Fine Management action.',[],400);
}catch(InvalidArgumentException $e){if($pdo->inTransaction())$pdo->rollBack();fmOut(false,$e->getMessage(),[],422);}
catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('fine-management.php: '.$e->getMessage());$h=strtolower((string)($_SERVER['HTTP_HOST']??''));fmOut(false,(str_contains($h,'localhost')||str_contains($h,'127.0.0.1'))?'Fine request failed: '.$e->getMessage():'Unable to complete the fine request.',[],500);}

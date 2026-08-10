<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors','0');
error_reporting(E_ALL);

require_once dirname(__DIR__).'/includes/bootstrap.php';

function smOut(bool $ok,string $message='',array $data=[],int $status=200):never{
    while(ob_get_level()>0)ob_end_clean();
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control:no-store');
    echo json_encode(['success'=>$ok,'message'=>$message,'data'=>$data],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function smInput():array{$j=json_decode((string)file_get_contents('php://input'),true);return is_array($j)?$j:$_POST;}
function smScope():array{$u=function_exists('current_user')?current_user():[];return['tenant_id'=>(int)($u['tenant_id']??$u['school_id']??$_SESSION['tenant_id']??0),'branch_id'=>(int)($u['branch_id']??$_SESSION['branch_id']??0),'user_id'=>(int)($u['id']??$u['user_id']??$_SESSION['user_id']??0),'role'=>(string)($u['role']??$u['role_name']??$_SESSION['role']??'')];}
function smCsrf(array $i):void{$s=(string)($_SESSION['fee_csrf_token']??'');$r=(string)($i['csrf_token']??'');if($s===''||$r===''||!hash_equals($s,$r))smOut(false,'Invalid or expired CSRF token. Refresh the page.',[],419);}
function smName(string $a='s'):string{return "TRIM(CONCAT(COALESCE($a.first_name,''),CASE WHEN COALESCE($a.last_name,'')='' THEN '' ELSE CONCAT(' ',$a.last_name) END))";}
function smCan(array $s,string $action):bool{
    if(function_exists('has_permission')){try{return has_permission('fee_management',$action);}catch(Throwable $e){}}
    $role=strtolower($s['role']);
    if(in_array($role,['super_admin','super admin','admin','school_admin','school admin'],true))return true;
    return $action==='approve'&&in_array($role,['accountant','principal'],true);
}
function smEnsure(PDO $pdo):void{
    $pdo->exec("CREATE TABLE IF NOT EXISTS scholarship_types(
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        tenant_id BIGINT UNSIGNED NOT NULL,
        type_code VARCHAR(40) NOT NULL,
        type_name VARCHAR(120) NOT NULL,
        default_scope ENUM('student','class') NOT NULL DEFAULT 'student',
        calculation_type ENUM('percentage','fixed') NOT NULL DEFAULT 'percentage',
        default_value DECIMAL(12,2) NOT NULL DEFAULT 0,
        approval_required TINYINT(1) NOT NULL DEFAULT 1,
        description VARCHAR(500) NULL,
        status ENUM('active','inactive') NOT NULL DEFAULT 'active',
        created_by BIGINT UNSIGNED NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY(id),
        UNIQUE KEY uq_scholarship_type_code(tenant_id,type_code),
        UNIQUE KEY uq_scholarship_type_name(tenant_id,type_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS fee_scholarships(
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        tenant_id BIGINT UNSIGNED NOT NULL,
        branch_id BIGINT UNSIGNED NULL,
        scholarship_type_id BIGINT UNSIGNED NOT NULL,
        scope_type ENUM('student','class') NOT NULL,
        academic_year_id BIGINT UNSIGNED NOT NULL,
        class_id BIGINT UNSIGNED NULL,
        student_id BIGINT UNSIGNED NULL,
        fee_structure_id BIGINT UNSIGNED NULL,
        calculation_type ENUM('percentage','fixed') NOT NULL,
        scholarship_value DECIMAL(12,2) NOT NULL,
        applied_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        valid_from DATE NOT NULL,
        valid_to DATE NULL,
        approval_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        approved_by BIGINT UNSIGNED NULL,
        approved_at DATETIME NULL,
        status ENUM('active','inactive') NOT NULL DEFAULT 'active',
        remarks VARCHAR(500) NOT NULL,
        created_by BIGINT UNSIGNED NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY(id),
        KEY idx_scholarship_filter(tenant_id,academic_year_id,scope_type,status,approval_status),
        KEY idx_scholarship_student(tenant_id,student_id),
        KEY idx_scholarship_class(tenant_id,class_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS scholarship_history(
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        tenant_id BIGINT UNSIGNED NOT NULL,
        branch_id BIGINT UNSIGNED NULL,
        scholarship_id BIGINT UNSIGNED NULL,
        action_name VARCHAR(60) NOT NULL,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        remarks VARCHAR(500) NULL,
        user_id BIGINT UNSIGNED NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(id),
        KEY idx_scholarship_history(tenant_id,created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function smHistory(PDO $pdo,array $s,?int $id,string $action,float $amount,string $remarks):void{
    $q=$pdo->prepare("INSERT INTO scholarship_history(tenant_id,branch_id,scholarship_id,action_name,amount,remarks,user_id) VALUES(:t,:b,:d,:a,:m,:r,:u)");
    $q->execute(['t'=>$s['tenant_id'],'b'=>$s['branch_id']?:null,'d'=>$id,'a'=>$action,'m'=>$amount,'r'=>$remarks?:null,'u'=>$s['user_id']?:null]);
}
function smTargetAssignments(PDO $pdo,array $scholarship):array{
    $where=['a.tenant_id=:t','a.academic_year_id=:y'];$p=['t'=>$scholarship['tenant_id'],'y'=>$scholarship['academic_year_id']];
    if((int)$scholarship['fee_structure_id']>0){$where[]='a.fee_structure_id=:fs';$p['fs']=$scholarship['fee_structure_id'];}
    if($scholarship['scope_type']==='student'){$where[]='a.student_id=:st';$p['st']=$scholarship['student_id'];}
    else{$where[]='EXISTS(SELECT 1 FROM student_enrollments e WHERE e.tenant_id=a.tenant_id AND e.student_id=a.student_id AND e.academic_year_id=a.academic_year_id AND e.class_id=:c AND e.enrollment_status="active")';$p['c']=$scholarship['class_id'];}
    $q=$pdo->prepare("SELECT a.* FROM student_fee_assignments a WHERE ".implode(' AND ',$where)." FOR UPDATE");$q->execute($p);return$q->fetchAll(PDO::FETCH_ASSOC);
}
function smRecalculateAssignment(PDO $pdo,int $tenant,int $assignmentId):void{
    $q=$pdo->prepare("SELECT * FROM student_fee_assignments WHERE id=:id AND tenant_id=:t FOR UPDATE");$q->execute(['id'=>$assignmentId,'t'=>$tenant]);$a=$q->fetch(PDO::FETCH_ASSOC);if(!$a)return;
    $q=$pdo->prepare("SELECT s.* FROM fee_scholarships s WHERE s.tenant_id=:t AND s.approval_status='approved' AND s.status='active' AND CURDATE()>=s.valid_from AND (s.valid_to IS NULL OR CURDATE()<=s.valid_to) AND (s.fee_structure_id IS NULL OR s.fee_structure_id=0 OR s.fee_structure_id=:fs) AND s.academic_year_id=:y AND ((s.scope_type='student' AND s.student_id=:st) OR (s.scope_type='class' AND EXISTS(SELECT 1 FROM student_enrollments e WHERE e.tenant_id=s.tenant_id AND e.student_id=:st2 AND e.academic_year_id=s.academic_year_id AND e.class_id=s.class_id AND e.enrollment_status='active')))");
    $q->execute(['t'=>$tenant,'fs'=>$a['fee_structure_id'],'y'=>$a['academic_year_id'],'st'=>$a['student_id'],'st2'=>$a['student_id']]);
    $scholarship=0.0;
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){
        $value=$row['calculation_type']==='percentage'?((float)$a['gross_amount']*(float)$row['scholarship_value']/100):(float)$row['scholarship_value'];
        $scholarship+=$value;
    }
    $max=max(0,(float)$a['gross_amount']-(float)$a['concession_amount']);
    $scholarship=min($max,round($scholarship,2));
    $net=max(0,round((float)$a['gross_amount']-(float)$a['concession_amount']-$scholarship,2));
    $paid=(float)$a['paid_amount'];$balance=max(0,round($net-$paid,2));$status=$balance<=0.01?'paid':($paid>0?'partial':'unpaid');
    $q=$pdo->prepare("UPDATE student_fee_assignments SET scholarship_amount=:s,net_amount=:n,balance_amount=:b,payment_status=:ps WHERE id=:id AND tenant_id=:t");
    $q->execute(['s'=>$scholarship,'n'=>$net,'b'=>$balance,'ps'=>$status,'id'=>$assignmentId,'t'=>$tenant]);
}
function smApply(PDO $pdo,array $s,int $scholarshipId):float{
    $q=$pdo->prepare("SELECT * FROM fee_scholarships WHERE id=:id AND tenant_id=:t FOR UPDATE");$q->execute(['id'=>$scholarshipId,'t'=>$s['tenant_id']]);$d=$q->fetch(PDO::FETCH_ASSOC);if(!$d)return 0;
    $amount=0.0;$ids=[];
    foreach(smTargetAssignments($pdo,$d) as $a){
        $value=$d['calculation_type']==='percentage'?((float)$a['gross_amount']*(float)$d['scholarship_value']/100):(float)$d['scholarship_value'];
        $amount+=min(max(0,(float)$a['gross_amount']-(float)$a['concession_amount']),$value);
        $ids[]=(int)$a['id'];
    }
    $pdo->prepare("UPDATE fee_scholarships SET applied_amount=:a WHERE id=:id AND tenant_id=:t")->execute(['a'=>round($amount,2),'id'=>$scholarshipId,'t'=>$s['tenant_id']]);
    foreach(array_unique($ids) as $id)smRecalculateAssignment($pdo,$s['tenant_id'],$id);
    return round($amount,2);
}
function smMeta(PDO $pdo,array $s):array{
    $q=$pdo->prepare("SELECT id,year_name FROM academic_years WHERE tenant_id=:t ORDER BY is_current DESC,start_date DESC,id DESC");$q->execute(['t'=>$s['tenant_id']]);$years=$q->fetchAll(PDO::FETCH_ASSOC);
    $q=$pdo->prepare("SELECT id,class_name,academic_year_id FROM classes WHERE tenant_id=:t AND status='active' ORDER BY display_order,class_name");$q->execute(['t'=>$s['tenant_id']]);$classes=$q->fetchAll(PDO::FETCH_ASSOC);
    $name=smName('st');$q=$pdo->prepare("SELECT st.id,CONCAT($name,' (',st.admission_no,')') student_name,e.academic_year_id,e.class_id FROM students st INNER JOIN student_enrollments e ON e.student_id=st.id AND e.tenant_id=st.tenant_id WHERE st.tenant_id=:t AND (:bs=0 OR st.branch_id=:b) AND st.status='active' AND st.deleted_at IS NULL AND e.enrollment_status='active' ORDER BY st.first_name");$q->execute(['t'=>$s['tenant_id'],'bs'=>$s['branch_id'],'b'=>$s['branch_id']]);$students=$q->fetchAll(PDO::FETCH_ASSOC);
    $q=$pdo->prepare("SELECT id,structure_name,academic_year_id,class_id FROM fee_structures WHERE tenant_id=:t AND status='active' ORDER BY structure_name");$q->execute(['t'=>$s['tenant_id']]);$structures=$q->fetchAll(PDO::FETCH_ASSOC);
    $q=$pdo->prepare("SELECT * FROM scholarship_types WHERE tenant_id=:t ORDER BY type_name");$q->execute(['t'=>$s['tenant_id']]);$types=$q->fetchAll(PDO::FETCH_ASSOC);
    return compact('years','classes','students','structures','types');
}

if(!isset($pdo)||!$pdo instanceof PDO)smOut(false,'Database connection unavailable.',[],500);
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
if(empty($_SESSION['fee_csrf_token']))$_SESSION['fee_csrf_token']=bin2hex(random_bytes(32));
$s=smScope();if($s['tenant_id']<=0||$s['user_id']<=0)smOut(false,'Tenant or user session is missing.',[],401);
smEnsure($pdo);
$i=smInput();$action=strtolower(trim((string)($i['action']??$_GET['action']??'')));

try{
    if($action==='list'){
        $where=['s.tenant_id=:t'];$p=['t'=>$s['tenant_id']];
        $search=trim((string)($_GET['search']??''));if($search!==''){$where[]='(stt.type_name LIKE :q OR st.first_name LIKE :q OR st.last_name LIKE :q OR st.admission_no LIKE :q OR c.class_name LIKE :q)';$p['q']='%'.$search.'%';}
        foreach(['academic_year_id'=>'s.academic_year_id','scholarship_type_id'=>'s.scholarship_type_id'] as $k=>$col){$v=(string)($_GET[$k]??'all');if($v!==''&&$v!=='all'){$where[]="$col=:$k";$p[$k]=(int)$v;}}
        foreach(['scope_type','approval_status','status'] as $k){$v=(string)($_GET[$k]??'all');if($v!==''&&$v!=='all'){$where[]="s.$k=:$k";$p[$k]=$v;}}
        $page=max(1,(int)($_GET['page']??1));$per=min(100,max(5,(int)($_GET['per_page']??10)));
        $count=$pdo->prepare("SELECT COUNT(*) FROM fee_scholarships s INNER JOIN scholarship_types stt ON stt.id=s.scholarship_type_id LEFT JOIN students st ON st.id=s.student_id LEFT JOIN classes c ON c.id=s.class_id WHERE ".implode(' AND ',$where));$count->execute($p);$total=(int)$count->fetchColumn();$last=max(1,(int)ceil($total/$per));$page=min($page,$last);$offset=($page-1)*$per;
        $name=smName('st');$q=$pdo->prepare("SELECT s.*,stt.type_name,stt.type_code,ay.year_name,c.class_name,$name student_name,fs.structure_name,CASE s.scope_type WHEN 'student' THEN 'Student-wise' ELSE 'Class-wise' END scope_label,CASE WHEN s.scope_type='class' THEN c.class_name ELSE $name END target_name FROM fee_scholarships s INNER JOIN scholarship_types stt ON stt.id=s.scholarship_type_id INNER JOIN academic_years ay ON ay.id=s.academic_year_id LEFT JOIN classes c ON c.id=s.class_id LEFT JOIN students st ON st.id=s.student_id LEFT JOIN fee_structures fs ON fs.id=s.fee_structure_id WHERE ".implode(' AND ',$where)." ORDER BY s.id DESC LIMIT {$per} OFFSET {$offset}");
        $q->execute($p);$records=$q->fetchAll(PDO::FETCH_ASSOC);foreach($records as $idx=>&$r)$r['row_number']=$offset+$idx+1;
        $meta=smMeta($pdo,$s);
        try{$h=$pdo->prepare("SELECT h.*,stt.type_name,CASE WHEN s.scope_type='class' THEN c.class_name ELSE ".smName('st')." END target_name,COALESCE(u.name,u.username,u.email) user_name FROM scholarship_history h LEFT JOIN fee_scholarships s ON s.id=h.scholarship_id LEFT JOIN scholarship_types stt ON stt.id=s.scholarship_type_id LEFT JOIN classes c ON c.id=s.class_id LEFT JOIN students st ON st.id=s.student_id LEFT JOIN users u ON u.id=h.user_id WHERE h.tenant_id=:t ORDER BY h.id DESC LIMIT 100");$h->execute(['t'=>$s['tenant_id']]);$history=$h->fetchAll(PDO::FETCH_ASSOC);}catch(Throwable $e){$h=$pdo->prepare("SELECT h.*,stt.type_name,CASE WHEN s.scope_type='class' THEN c.class_name ELSE ".smName('st')." END target_name,NULL user_name FROM scholarship_history h LEFT JOIN fee_scholarships s ON s.id=h.scholarship_id LEFT JOIN scholarship_types stt ON stt.id=s.scholarship_type_id LEFT JOIN classes c ON c.id=s.class_id LEFT JOIN students st ON st.id=s.student_id WHERE h.tenant_id=:t ORDER BY h.id DESC LIMIT 100");$h->execute(['t'=>$s['tenant_id']]);$history=$h->fetchAll(PDO::FETCH_ASSOC);}
        $q=$pdo->prepare("SELECT COUNT(*) total,SUM(approval_status='approved') approved,SUM(approval_status='pending') pending,COALESCE(SUM(CASE WHEN approval_status='approved' AND status='active' THEN applied_amount ELSE 0 END),0) benefit FROM fee_scholarships WHERE tenant_id=:t");$q->execute(['t'=>$s['tenant_id']]);$stats=$q->fetch(PDO::FETCH_ASSOC)?:[];
        smOut(true,'Scholarship management loaded.',['records'=>$records,'types'=>$meta['types'],'history'=>$history,'meta'=>$meta,'pagination'=>['total'=>$total,'page'=>$page,'per_page'=>$per,'last_page'=>$last],'stats'=>['total'=>(int)($stats['total']??0),'approved'=>(int)($stats['approved']??0),'pending'=>(int)($stats['pending']??0),'benefit'=>(float)($stats['benefit']??0)],'permissions'=>['approve'=>smCan($s,'approve'),'delete'=>smCan($s,'delete')],'csrf_token'=>$_SESSION['fee_csrf_token']]);
    }
    if($action==='save_type'){
        smCsrf($i);$id=(int)($i['id']??0);$code=strtoupper(trim((string)($i['type_code']??'')));$name=trim((string)($i['type_name']??''));$scope=(string)($i['default_scope']??'student');$calc=(string)($i['calculation_type']??'percentage');$value=round((float)($i['default_value']??0),2);$approval=(int)($i['approval_required']??1);$status=(string)($i['status']??'active');$description=trim((string)($i['description']??''));
        if($code===''||$name===''||$value<=0)throw new InvalidArgumentException('Code, name and positive default value are required.');if($calc==='percentage'&&$value>100)throw new InvalidArgumentException('Percentage cannot exceed 100.');
        $q=$pdo->prepare("SELECT id FROM scholarship_types WHERE tenant_id=:t AND id<>:id AND (UPPER(type_code)=UPPER(:c) OR LOWER(type_name)=LOWER(:n))");$q->execute(['t'=>$s['tenant_id'],'id'=>$id,'c'=>$code,'n'=>$name]);if($q->fetchColumn())throw new InvalidArgumentException('Scholarship type code or name already exists.');
        if($id){$q=$pdo->prepare("UPDATE scholarship_types SET type_code=:c,type_name=:n,default_scope=:sc,calculation_type=:ct,default_value=:v,approval_required=:a,description=:d,status=:st WHERE id=:id AND tenant_id=:t");$q->execute(['c'=>$code,'n'=>$name,'sc'=>$scope,'ct'=>$calc,'v'=>$value,'a'=>$approval?1:0,'d'=>$description?:null,'st'=>$status,'id'=>$id,'t'=>$s['tenant_id']]);}else{$q=$pdo->prepare("INSERT INTO scholarship_types(tenant_id,type_code,type_name,default_scope,calculation_type,default_value,approval_required,description,status,created_by) VALUES(:t,:c,:n,:sc,:ct,:v,:a,:d,:st,:u)");$q->execute(['t'=>$s['tenant_id'],'c'=>$code,'n'=>$name,'sc'=>$scope,'ct'=>$calc,'v'=>$value,'a'=>$approval?1:0,'d'=>$description?:null,'st'=>$status,'u'=>$s['user_id']]);$id=(int)$pdo->lastInsertId();}smHistory($pdo,$s,null,'save_type',0,$name);smOut(true,'Scholarship type saved successfully.',['id'=>$id]);
    }
    if($action==='delete_type'){
        smCsrf($i);$id=(int)($i['id']??0);$q=$pdo->prepare("SELECT COUNT(*) FROM fee_scholarships WHERE tenant_id=:t AND scholarship_type_id=:id");$q->execute(['t'=>$s['tenant_id'],'id'=>$id]);if((int)$q->fetchColumn()>0)throw new InvalidArgumentException('Scholarship type is already used and cannot be deleted.');$pdo->prepare("DELETE FROM scholarship_types WHERE id=:id AND tenant_id=:t")->execute(['id'=>$id,'t'=>$s['tenant_id']]);smOut(true,'Scholarship type deleted.');
    }
    if($action==='save'){
        smCsrf($i);$id=(int)($i['id']??0);$type=(int)($i['scholarship_type_id']??0);$scope=(string)($i['scope_type']??'student');$year=(int)($i['academic_year_id']??0);$class=(int)($i['class_id']??0);$student=(int)($i['student_id']??0);$structure=(int)($i['fee_structure_id']??0);$calc=(string)($i['calculation_type']??'percentage');$value=round((float)($i['scholarship_value']??0),2);$from=(string)($i['valid_from']??'');$to=trim((string)($i['valid_to']??''));$status=(string)($i['status']??'active');$remarks=trim((string)($i['remarks']??''));
        if($type<=0||$year<=0||$value<=0||$from===''||$remarks==='')throw new InvalidArgumentException('Scholarship type, academic year, value, valid-from date and remarks are required.');if($calc==='percentage'&&$value>100)throw new InvalidArgumentException('Percentage cannot exceed 100.');if($scope==='class'&&$class<=0)throw new InvalidArgumentException('Class is required for class-wise scholarship.');if($scope==='student'&&$student<=0)throw new InvalidArgumentException('Student is required for student-wise scholarship.');if($to!==''&&$to<$from)throw new InvalidArgumentException('Valid-to date cannot be before valid-from date.');
        $q=$pdo->prepare("SELECT approval_required FROM scholarship_types WHERE id=:id AND tenant_id=:t AND status='active'");$q->execute(['id'=>$type,'t'=>$s['tenant_id']]);$approvalRequired=$q->fetchColumn();if($approvalRequired===false)throw new InvalidArgumentException('Scholarship type is invalid or inactive.');$approval=(int)$approvalRequired?'pending':'approved';
        $pdo->beginTransaction();
        if($id){$old=$pdo->prepare("SELECT * FROM fee_scholarships WHERE id=:id AND tenant_id=:t FOR UPDATE");$old->execute(['id'=>$id,'t'=>$s['tenant_id']]);$previous=$old->fetch(PDO::FETCH_ASSOC);if(!$previous)throw new InvalidArgumentException('Scholarship not found.');$approval=$previous['approval_status']==='approved'?'pending':$previous['approval_status'];$q=$pdo->prepare("UPDATE fee_scholarships SET scholarship_type_id=:dt,scope_type=:sc,academic_year_id=:y,class_id=:c,student_id=:st,fee_structure_id=:fs,calculation_type=:ct,scholarship_value=:v,valid_from=:vf,valid_to=:vt,approval_status=:ap,approved_by=NULL,approved_at=NULL,status=:status,remarks=:r,applied_amount=0 WHERE id=:id AND tenant_id=:t");$q->execute(['dt'=>$type,'sc'=>$scope,'y'=>$year,'c'=>$class?:null,'st'=>$student?:null,'fs'=>$structure?:null,'ct'=>$calc,'v'=>$value,'vf'=>$from,'vt'=>$to?:null,'ap'=>$approval,'status'=>$status,'r'=>$remarks,'id'=>$id,'t'=>$s['tenant_id']]);}else{$q=$pdo->prepare("INSERT INTO fee_scholarships(tenant_id,branch_id,scholarship_type_id,scope_type,academic_year_id,class_id,student_id,fee_structure_id,calculation_type,scholarship_value,valid_from,valid_to,approval_status,status,remarks,created_by) VALUES(:t,:b,:dt,:sc,:y,:c,:st,:fs,:ct,:v,:vf,:vt,:ap,:status,:r,:u)");$q->execute(['t'=>$s['tenant_id'],'b'=>$s['branch_id']?:null,'dt'=>$type,'sc'=>$scope,'y'=>$year,'c'=>$class?:null,'st'=>$student?:null,'fs'=>$structure?:null,'ct'=>$calc,'v'=>$value,'vf'=>$from,'vt'=>$to?:null,'ap'=>$approval,'status'=>$status,'r'=>$remarks,'u'=>$s['user_id']]);$id=(int)$pdo->lastInsertId();}
        $applied=$approval==='approved'?smApply($pdo,$s,$id):0;smHistory($pdo,$s,$id,'save_scholarship',$applied,$remarks);$pdo->commit();smOut(true,$approval==='approved'?'Scholarship saved and applied successfully.':'Scholarship saved and sent for approval.',['id'=>$id]);
    }
    if($action==='approve'){
        smCsrf($i);if(!smCan($s,'approve'))smOut(false,'You do not have permission to approve scholarships.',[],403);$id=(int)($i['id']??0);$approval=(string)($i['approval_status']??'');$remarks=trim((string)($i['remarks']??''));if(!in_array($approval,['approved','rejected'],true))throw new InvalidArgumentException('Invalid approval status.');
        $pdo->beginTransaction();$q=$pdo->prepare("SELECT * FROM fee_scholarships WHERE id=:id AND tenant_id=:t FOR UPDATE");$q->execute(['id'=>$id,'t'=>$s['tenant_id']]);if(!$q->fetch())throw new InvalidArgumentException('Scholarship not found.');$pdo->prepare("UPDATE fee_scholarships SET approval_status=:ap,approved_by=:u,approved_at=NOW(),applied_amount=0 WHERE id=:id AND tenant_id=:t")->execute(['ap'=>$approval,'u'=>$s['user_id'],'id'=>$id,'t'=>$s['tenant_id']]);$amount=$approval==='approved'?smApply($pdo,$s,$id):0;smHistory($pdo,$s,$id,$approval.'_scholarship',$amount,$remarks);$pdo->commit();smOut(true,'Scholarship '.$approval.' successfully.');
    }
    if($action==='delete'){
        smCsrf($i);if(!smCan($s,'delete'))smOut(false,'You do not have permission to delete scholarships.',[],403);$id=(int)($i['id']??0);$pdo->beginTransaction();$q=$pdo->prepare("SELECT * FROM fee_scholarships WHERE id=:id AND tenant_id=:t FOR UPDATE");$q->execute(['id'=>$id,'t'=>$s['tenant_id']]);$d=$q->fetch(PDO::FETCH_ASSOC);if(!$d)throw new InvalidArgumentException('Scholarship not found.');$assignments=smTargetAssignments($pdo,$d);$pdo->prepare("DELETE FROM fee_scholarships WHERE id=:id AND tenant_id=:t")->execute(['id'=>$id,'t'=>$s['tenant_id']]);foreach($assignments as $a)smRecalculateAssignment($pdo,$s['tenant_id'],(int)$a['id']);smHistory($pdo,$s,$id,'delete_scholarship',0,$d['remarks']);$pdo->commit();smOut(true,'Scholarship deleted and fee assignments recalculated.');
    }
    smOut(false,'Invalid Scholarship Management action.',[],400);
}catch(InvalidArgumentException $e){if($pdo->inTransaction())$pdo->rollBack();smOut(false,$e->getMessage(),[],422);}
catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('scholarship-management.php: '.$e->getMessage());$h=strtolower((string)($_SERVER['HTTP_HOST']??''));smOut(false,(str_contains($h,'localhost')||str_contains($h,'127.0.0.1'))?'Scholarship request failed: '.$e->getMessage():'Unable to complete the scholarship request.',[],500);}

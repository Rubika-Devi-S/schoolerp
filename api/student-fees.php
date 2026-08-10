<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors','0');

require_once dirname(__DIR__).'/includes/bootstrap.php';

function sfOut(bool $success,string $message='',array $data=[],int $status=200):never{
    while(ob_get_level()>0)ob_end_clean();
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['success'=>$success,'message'=>$message,'data'=>$data],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function sfInput():array{
    $json=json_decode((string)file_get_contents('php://input'),true);
    return is_array($json)?$json:$_POST;
}
function sfScope():array{
    $user=function_exists('current_user')?current_user():[];
    return[
        'tenant_id'=>(int)($user['tenant_id']??$user['school_id']??$_SESSION['tenant_id']??$_SESSION['school_id']??0),
        'branch_id'=>(int)($user['branch_id']??$_SESSION['branch_id']??$_SESSION['default_branch_id']??0),
        'user_id'=>(int)($user['id']??$user['user_id']??$_SESSION['user_id']??0),
    ];
}
function sfCsrf(array $input):void{
    $session=(string)($_SESSION['fee_csrf_token']??'');
    $request=(string)($input['csrf_token']??'');
    if($session===''||$request===''||!hash_equals($session,$request)){
        sfOut(false,'Invalid or expired CSRF token. Refresh the page.',[],419);
    }
}
function sfNameSql(string $alias='s'):string{
    return "TRIM(CONCAT(COALESCE({$alias}.first_name,''),CASE WHEN COALESCE({$alias}.last_name,'')='' THEN '' ELSE CONCAT(' ',{$alias}.last_name) END))";
}
function sfDueDateSql(string $structureAlias='fs'):string{
    return "(SELECT MIN(fsi_due.due_date) FROM fee_structure_items fsi_due WHERE fsi_due.fee_structure_id={$structureAlias}.id)";
}
function sfFineSql(string $structureAlias='fs'):string{
    return "(SELECT COALESCE(SUM(CASE
        WHEN fsi.fine_type='fixed' AND fsi.due_date<CURDATE() THEN fsi.fine_value
        WHEN fsi.fine_type='daily' AND fsi.due_date<CURDATE() THEN DATEDIFF(CURDATE(),fsi.due_date)*fsi.fine_value
        ELSE 0 END),0)
        FROM fee_structure_items fsi WHERE fsi.fee_structure_id={$structureAlias}.id)";
}
function sfMeta(PDO $pdo,array $scope):array{
    $q=$pdo->prepare("SELECT id,year_name FROM academic_years WHERE tenant_id=:tenant_id ORDER BY is_current DESC,start_date DESC,id DESC");
    $q->execute(['tenant_id'=>$scope['tenant_id']]);
    $years=$q->fetchAll(PDO::FETCH_ASSOC);

    $q=$pdo->prepare("SELECT id,class_name,academic_year_id FROM classes WHERE tenant_id=:tenant_id AND status='active' ORDER BY display_order,class_name");
    $q->execute(['tenant_id'=>$scope['tenant_id']]);
    $classes=$q->fetchAll(PDO::FETCH_ASSOC);

    $q=$pdo->prepare("SELECT id,class_id,section_name FROM sections WHERE tenant_id=:tenant_id AND status='active' ORDER BY section_name");
    $q->execute(['tenant_id'=>$scope['tenant_id']]);
    $sections=$q->fetchAll(PDO::FETCH_ASSOC);

    $name=sfNameSql('s');
    $q=$pdo->prepare("SELECT s.id,CONCAT($name,' (',s.admission_no,')') AS student_name
        FROM students s
        WHERE s.tenant_id=:tenant_id AND (:branch_scope=0 OR s.branch_id=:branch_id)
          AND s.status='active' AND s.deleted_at IS NULL
        ORDER BY s.first_name,s.last_name");
    $q->execute(['tenant_id'=>$scope['tenant_id'],'branch_scope'=>$scope['branch_id'],'branch_id'=>$scope['branch_id']]);
    $students=$q->fetchAll(PDO::FETCH_ASSOC);

    $q=$pdo->prepare("SELECT fs.id,fs.structure_name,fs.academic_year_id,fs.class_id,
        COALESCE(SUM(fsi.amount),0) AS gross_amount
        FROM fee_structures fs
        LEFT JOIN fee_structure_items fsi ON fsi.fee_structure_id=fs.id
        WHERE fs.tenant_id=:tenant_id AND fs.status='active'
        GROUP BY fs.id
        ORDER BY fs.structure_name");
    $q->execute(['tenant_id'=>$scope['tenant_id']]);
    $structures=$q->fetchAll(PDO::FETCH_ASSOC);

    return compact('years','classes','sections','students','structures');
}
function sfRows(PDO $pdo,array $scope,array $filters):array{
    $dueDate=sfDueDateSql('fs');
    $where=[
        'a.tenant_id=:tenant_id',
        '(:branch_scope=0 OR s.branch_id=:branch_id)',
        "s.deleted_at IS NULL",
    ];
    $params=[
        'tenant_id'=>$scope['tenant_id'],
        'branch_scope'=>$scope['branch_id'],
        'branch_id'=>$scope['branch_id'],
    ];

    $year=trim((string)($filters['academic_year_id']??''));
    $class=trim((string)($filters['class_id']??''));
    $section=trim((string)($filters['section_id']??''));
    $status=trim((string)($filters['status']??''));
    $dueType=trim((string)($filters['due_type']??''));
    $search=trim((string)($filters['search']??''));

    if($year!==''&&$year!=='all'){$where[]='a.academic_year_id=:year';$params['year']=(int)$year;}
    if($class!==''&&$class!=='all'){$where[]='e.class_id=:class';$params['class']=(int)$class;}
    if($section!==''&&$section!=='all'){$where[]='e.section_id=:section';$params['section']=(int)$section;}
    if($status!==''&&$status!=='all'){$where[]='a.payment_status=:status';$params['status']=$status;}
    if($dueType==='pending')$where[]='a.balance_amount>0';
    if($dueType==='overdue')$where[]="a.balance_amount>0 AND ($dueDate<CURDATE() OR a.payment_status='overdue')";
    if($search!==''){
        $where[]='(CAST(s.id AS CHAR) LIKE :search OR s.admission_no LIKE :search OR s.first_name LIKE :search OR s.last_name LIKE :search)';
        $params['search']='%'.$search.'%';
    }

    $name=sfNameSql('s');
    $sql="SELECT
        a.id AS assignment_id,a.student_id,a.academic_year_id,a.fee_structure_id,
        a.gross_amount,a.concession_amount,a.scholarship_amount,a.net_amount,
        a.paid_amount,a.balance_amount,a.payment_status,
        s.admission_no,s.mobile,$name AS student_name,
        e.class_id,c.class_name,e.section_id,sec.section_name,
        ay.year_name,fs.structure_name,$dueDate AS due_date,
        CASE WHEN a.balance_amount>0 AND $dueDate<CURDATE() THEN 'overdue' ELSE a.payment_status END AS display_status
      FROM student_fee_assignments a
      INNER JOIN students s ON s.id=a.student_id AND s.tenant_id=a.tenant_id
      INNER JOIN fee_structures fs ON fs.id=a.fee_structure_id AND fs.tenant_id=a.tenant_id
      LEFT JOIN student_enrollments e ON e.student_id=a.student_id AND e.academic_year_id=a.academic_year_id AND e.tenant_id=a.tenant_id
      LEFT JOIN classes c ON c.id=e.class_id AND c.tenant_id=e.tenant_id
      LEFT JOIN sections sec ON sec.id=e.section_id AND sec.tenant_id=e.tenant_id
      LEFT JOIN academic_years ay ON ay.id=a.academic_year_id AND ay.tenant_id=a.tenant_id
      WHERE ".implode(' AND ',$where)."
      ORDER BY s.first_name,s.last_name,fs.structure_name";
    $q=$pdo->prepare($sql);
    $q->execute($params);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}
function sfStats(array $rows):array{
    $students=[];$total=0.0;$paid=0.0;$balance=0.0;
    foreach($rows as $row){
        $students[(int)$row['student_id']]=true;
        $total+=(float)$row['net_amount'];
        $paid+=(float)$row['paid_amount'];
        $balance+=(float)$row['balance_amount'];
    }
    return['students'=>count($students),'total'=>$total,'paid'=>$paid,'balance'=>$balance];
}
function sfAssignment(PDO $pdo,array $scope,int $id):array{
    $dueDate=sfDueDateSql('fs');
    $fine=sfFineSql('fs');
    $name=sfNameSql('s');
    $q=$pdo->prepare("SELECT a.id AS assignment_id,a.*,s.admission_no,s.mobile,$name AS student_name,
        e.class_id,c.class_name,e.section_id,sec.section_name,ay.year_name,fs.structure_name,
        $dueDate AS due_date,$fine AS fine_total,
        CASE WHEN a.balance_amount>0 AND $dueDate<CURDATE() THEN 'overdue' ELSE a.payment_status END AS display_status
        FROM student_fee_assignments a
        INNER JOIN students s ON s.id=a.student_id AND s.tenant_id=a.tenant_id
        INNER JOIN fee_structures fs ON fs.id=a.fee_structure_id AND fs.tenant_id=a.tenant_id
        LEFT JOIN student_enrollments e ON e.student_id=a.student_id AND e.academic_year_id=a.academic_year_id AND e.tenant_id=a.tenant_id
        LEFT JOIN classes c ON c.id=e.class_id AND c.tenant_id=e.tenant_id
        LEFT JOIN sections sec ON sec.id=e.section_id AND sec.tenant_id=e.tenant_id
        LEFT JOIN academic_years ay ON ay.id=a.academic_year_id AND ay.tenant_id=a.tenant_id
        WHERE a.id=:id AND a.tenant_id=:tenant_id AND (:branch_scope=0 OR s.branch_id=:branch_id)
        LIMIT 1");
    $q->execute(['id'=>$id,'tenant_id'=>$scope['tenant_id'],'branch_scope'=>$scope['branch_id'],'branch_id'=>$scope['branch_id']]);
    $row=$q->fetch(PDO::FETCH_ASSOC);
    if(!$row)throw new InvalidArgumentException('Student fee assignment not found.');
    return $row;
}
function sfPayments(PDO $pdo,array $scope,array $assignment):array{
    $marker='%[assignment:'.(int)$assignment['assignment_id'].']%';
    $q=$pdo->prepare("SELECT r.id,r.receipt_no,r.receipt_date,r.gross_amount,r.discount_amount,r.fine_amount,r.paid_amount,r.payment_status,
        GROUP_CONCAT(DISTINCT pm.method_name ORDER BY pm.method_name SEPARATOR ', ') AS payment_methods
        FROM fee_receipts r
        LEFT JOIN fee_payments p ON p.receipt_id=r.id AND p.tenant_id=r.tenant_id
        LEFT JOIN payment_methods pm ON pm.id=p.payment_method_id AND pm.tenant_id=p.tenant_id
        WHERE r.tenant_id=:tenant_id AND (:branch_scope=0 OR r.branch_id=:branch_id)
          AND r.student_id=:student_id AND r.academic_year_id=:year_id
          AND (r.notes LIKE :marker OR :allow_all=1)
        GROUP BY r.id
        ORDER BY r.receipt_date DESC,r.id DESC");
    $q->execute([
        'tenant_id'=>$scope['tenant_id'],'branch_scope'=>$scope['branch_id'],'branch_id'=>$scope['branch_id'],
        'student_id'=>$assignment['student_id'],'year_id'=>$assignment['academic_year_id'],
        'marker'=>$marker,'allow_all'=>0
    ]);
    $rows=$q->fetchAll(PDO::FETCH_ASSOC);
    if(!$rows){
        $q=$pdo->prepare("SELECT r.id,r.receipt_no,r.receipt_date,r.gross_amount,r.discount_amount,r.fine_amount,r.paid_amount,r.payment_status,
            GROUP_CONCAT(DISTINCT pm.method_name ORDER BY pm.method_name SEPARATOR ', ') AS payment_methods
            FROM fee_receipts r
            LEFT JOIN fee_payments p ON p.receipt_id=r.id AND p.tenant_id=r.tenant_id
            LEFT JOIN payment_methods pm ON pm.id=p.payment_method_id AND pm.tenant_id=p.tenant_id
            WHERE r.tenant_id=:tenant_id AND (:branch_scope=0 OR r.branch_id=:branch_id)
              AND r.student_id=:student_id AND r.academic_year_id=:year_id
            GROUP BY r.id ORDER BY r.receipt_date DESC,r.id DESC");
        $q->execute(['tenant_id'=>$scope['tenant_id'],'branch_scope'=>$scope['branch_id'],'branch_id'=>$scope['branch_id'],'student_id'=>$assignment['student_id'],'year_id'=>$assignment['academic_year_id']]);
        $rows=$q->fetchAll(PDO::FETCH_ASSOC);
    }
    return $rows;
}

if(!isset($pdo)||!$pdo instanceof PDO)sfOut(false,'Database connection unavailable.',[],500);
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
if(empty($_SESSION['fee_csrf_token']))$_SESSION['fee_csrf_token']=bin2hex(random_bytes(32));

$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES,true);
$scope=sfScope();
if($scope['tenant_id']<=0)sfOut(false,'School tenant session was not found.',[],401);

$input=sfInput();
$action=strtolower(trim((string)($input['action']??$_GET['action']??'')));

try{
    if($action==='meta'){
        sfOut(true,'Metadata loaded.',sfMeta($pdo,$scope)+['csrf_token'=>$_SESSION['fee_csrf_token']]);
    }

    if($action==='list'){
        $rows=sfRows($pdo,$scope,$_GET);
        sfOut(true,'Student fees loaded.',['records'=>$rows,'stats'=>sfStats($rows)]);
    }

    if($action==='assign'){
        sfCsrf($input);
        $studentId=(int)($input['student_id']??0);
        $yearId=(int)($input['academic_year_id']??0);
        $structureId=(int)($input['fee_structure_id']??0);
        $concession=round(max(0,(float)($input['concession_amount']??0)),2);
        $scholarship=round(max(0,(float)($input['scholarship_amount']??0)),2);

        if($studentId<=0||$yearId<=0||$structureId<=0)throw new InvalidArgumentException('Student, academic year and fee structure are required.');

        $q=$pdo->prepare("SELECT s.id FROM students s WHERE s.id=:student_id AND s.tenant_id=:tenant_id AND (:branch_scope=0 OR s.branch_id=:branch_id) AND s.status='active' AND s.deleted_at IS NULL");
        $q->execute(['student_id'=>$studentId,'tenant_id'=>$scope['tenant_id'],'branch_scope'=>$scope['branch_id'],'branch_id'=>$scope['branch_id']]);
        if(!$q->fetchColumn())throw new InvalidArgumentException('Selected student is invalid or inactive.');

        $q=$pdo->prepare("SELECT fs.id,fs.academic_year_id,fs.class_id,COALESCE(SUM(fsi.amount),0) AS gross_amount
            FROM fee_structures fs LEFT JOIN fee_structure_items fsi ON fsi.fee_structure_id=fs.id
            WHERE fs.id=:id AND fs.tenant_id=:tenant_id AND fs.status='active' GROUP BY fs.id");
        $q->execute(['id'=>$structureId,'tenant_id'=>$scope['tenant_id']]);
        $structure=$q->fetch(PDO::FETCH_ASSOC);
        if(!$structure)throw new InvalidArgumentException('Selected fee structure is invalid or inactive.');
        if((int)$structure['academic_year_id']!==$yearId)throw new InvalidArgumentException('Fee structure does not belong to the selected academic year.');

        $q=$pdo->prepare("SELECT class_id FROM student_enrollments WHERE tenant_id=:tenant_id AND student_id=:student_id AND academic_year_id=:year_id LIMIT 1");
        $q->execute(['tenant_id'=>$scope['tenant_id'],'student_id'=>$studentId,'year_id'=>$yearId]);
        $studentClass=(int)$q->fetchColumn();
        if($studentClass<=0)throw new InvalidArgumentException('Student enrollment was not found for the selected academic year.');
        if((int)$structure['class_id']!==$studentClass)throw new InvalidArgumentException('Fee structure does not match the student class.');

        $gross=round((float)$structure['gross_amount'],2);
        if($gross<=0)throw new InvalidArgumentException('Fee structure has no valid fee items.');
        if($concession+$scholarship>$gross)throw new InvalidArgumentException('Concession and scholarship cannot exceed the gross fee.');

        $net=round($gross-$concession-$scholarship,2);

        $pdo->beginTransaction();
        $q=$pdo->prepare("SELECT * FROM student_fee_assignments WHERE tenant_id=:tenant_id AND student_id=:student_id AND academic_year_id=:year_id AND fee_structure_id=:structure_id FOR UPDATE");
        $q->execute(['tenant_id'=>$scope['tenant_id'],'student_id'=>$studentId,'year_id'=>$yearId,'structure_id'=>$structureId]);
        $existing=$q->fetch(PDO::FETCH_ASSOC);

        if($existing){
            $paid=(float)$existing['paid_amount'];
            if($net+0.01<$paid)throw new InvalidArgumentException('Net fee cannot be less than the amount already paid.');
            $balance=max(0,round($net-$paid,2));
            $status=$balance<=0.01?'paid':($paid>0?'partial':'unpaid');
            $update=$pdo->prepare("UPDATE student_fee_assignments SET gross_amount=:gross,concession_amount=:concession,scholarship_amount=:scholarship,net_amount=:net,balance_amount=:balance,payment_status=:status WHERE id=:id AND tenant_id=:tenant_id");
            $update->execute(['gross'=>$gross,'concession'=>$concession,'scholarship'=>$scholarship,'net'=>$net,'balance'=>$balance,'status'=>$status,'id'=>$existing['id'],'tenant_id'=>$scope['tenant_id']]);
            $assignmentId=(int)$existing['id'];
            $message='Student fee assignment updated successfully.';
        }else{
            $insert=$pdo->prepare("INSERT INTO student_fee_assignments(tenant_id,student_id,academic_year_id,fee_structure_id,gross_amount,concession_amount,scholarship_amount,net_amount,paid_amount,balance_amount,payment_status) VALUES(:tenant_id,:student_id,:year_id,:structure_id,:gross,:concession,:scholarship,:net,0,:balance,'unpaid')");
            $insert->execute(['tenant_id'=>$scope['tenant_id'],'student_id'=>$studentId,'year_id'=>$yearId,'structure_id'=>$structureId,'gross'=>$gross,'concession'=>$concession,'scholarship'=>$scholarship,'net'=>$net,'balance'=>$net]);
            $assignmentId=(int)$pdo->lastInsertId();
            $message='Fee assigned successfully.';
        }
        $pdo->commit();
        sfOut(true,$message,['assignment_id'=>$assignmentId]);
    }

    if($action==='detail'){
        $assignment=sfAssignment($pdo,$scope,(int)($_GET['assignment_id']??0));

        $q=$pdo->prepare("SELECT fsi.id,fh.head_name,fh.head_code,fsi.amount,fsi.due_date,fsi.fine_type,fsi.fine_value
            FROM fee_structure_items fsi
            INNER JOIN fee_heads fh ON fh.id=fsi.fee_head_id
            WHERE fsi.fee_structure_id=:structure_id
            ORDER BY fsi.due_date,fh.head_name");
        $q->execute(['structure_id'=>$assignment['fee_structure_id']]);
        $items=$q->fetchAll(PDO::FETCH_ASSOC);

        $q=$pdo->prepare("SELECT COALESCE(DATE_FORMAT(fsi.due_date,'%Y-%m-%d'),'No Due Date') AS due_date,
            GROUP_CONCAT(fh.head_name ORDER BY fh.head_name SEPARATOR ', ') AS heads,
            SUM(fsi.amount) AS amount,
            SUM(CASE
                WHEN fsi.fine_type='fixed' AND fsi.due_date<CURDATE() THEN fsi.fine_value
                WHEN fsi.fine_type='daily' AND fsi.due_date<CURDATE() THEN DATEDIFF(CURDATE(),fsi.due_date)*fsi.fine_value
                ELSE 0 END) AS fine_amount
            FROM fee_structure_items fsi
            INNER JOIN fee_heads fh ON fh.id=fsi.fee_head_id
            WHERE fsi.fee_structure_id=:structure_id
            GROUP BY fsi.due_date
            ORDER BY fsi.due_date");
        $q->execute(['structure_id'=>$assignment['fee_structure_id']]);
        $installments=$q->fetchAll(PDO::FETCH_ASSOC);
        foreach($installments as &$installment){
            $installment['status']=($installment['due_date']!=='No Due Date'&&$installment['due_date']<date('Y-m-d')&&(float)$assignment['balance_amount']>0)?'overdue':'pending';
        }

        $payments=sfPayments($pdo,$scope,$assignment);

        $ledger=[];
        $running=0.0;
        $running+=(float)$assignment['net_amount'];
        $ledger[]=['entry_date'=>$assignment['due_date']?:'-','entry_type'=>'Fee Assignment','reference_no'=>$assignment['structure_name'],'debit'=>$assignment['net_amount'],'credit'=>0,'balance'=>$running,'status'=>$assignment['payment_status']];
        foreach(array_reverse($payments) as $payment){
            if($payment['payment_status']==='reversed')continue;
            $running=max(0,$running-(float)$payment['paid_amount']);
            $ledger[]=['entry_date'=>$payment['receipt_date'],'entry_type'=>'Payment','reference_no'=>$payment['receipt_no'],'debit'=>0,'credit'=>$payment['paid_amount'],'balance'=>$running,'status'=>$payment['payment_status']];
        }

        sfOut(true,'Student fee details loaded.',[
            'assignment'=>$assignment,
            'items'=>$items,
            'installments'=>$installments,
            'payments'=>$payments,
            'ledger'=>$ledger,
            'fine_total'=>(float)$assignment['fine_total'],
            'due_amount'=>(float)$assignment['balance_amount']+(float)$assignment['fine_total'],
        ]);
    }

    if($action==='print_receipt'){
        $id=(int)($_GET['id']??0);
        $name=sfNameSql('s');
        $q=$pdo->prepare("SELECT r.*,s.admission_no,$name AS student_name,ay.year_name,
            GROUP_CONCAT(DISTINCT pm.method_name ORDER BY pm.method_name SEPARATOR ', ') AS payment_methods
            FROM fee_receipts r
            INNER JOIN students s ON s.id=r.student_id AND s.tenant_id=r.tenant_id
            LEFT JOIN academic_years ay ON ay.id=r.academic_year_id AND ay.tenant_id=r.tenant_id
            LEFT JOIN fee_payments p ON p.receipt_id=r.id AND p.tenant_id=r.tenant_id
            LEFT JOIN payment_methods pm ON pm.id=p.payment_method_id AND pm.tenant_id=p.tenant_id
            WHERE r.id=:id AND r.tenant_id=:tenant_id AND (:branch_scope=0 OR r.branch_id=:branch_id)
            GROUP BY r.id LIMIT 1");
        $q->execute(['id'=>$id,'tenant_id'=>$scope['tenant_id'],'branch_scope'=>$scope['branch_id'],'branch_id'=>$scope['branch_id']]);
        $receipt=$q->fetch(PDO::FETCH_ASSOC);
        if(!$receipt){http_response_code(404);echo 'Receipt not found.';exit;}

        while(ob_get_level()>0)ob_end_clean();
        header('Content-Type:text/html; charset=utf-8');
        $e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
        echo '<!doctype html><html><head><meta charset="utf-8"><title>'.$e($receipt['receipt_no']).'</title><style>body{font-family:Arial,sans-serif;margin:30px;color:#111}.box{max-width:760px;margin:auto;border:1px solid #ddd;padding:24px;border-radius:12px}h2{text-align:center}.row{display:flex;justify-content:space-between;border-bottom:1px dashed #ddd;padding:9px 0}.total{font-size:18px;font-weight:700}.actions{text-align:center;margin-top:20px}@media print{.actions{display:none}}</style></head><body><div class="box"><h2>School Fee Receipt</h2><div class="row"><span>Receipt No</span><strong>'.$e($receipt['receipt_no']).'</strong></div><div class="row"><span>Date</span><span>'.$e($receipt['receipt_date']).'</span></div><div class="row"><span>Student</span><span>'.$e($receipt['student_name']).'</span></div><div class="row"><span>Admission No</span><span>'.$e($receipt['admission_no']).'</span></div><div class="row"><span>Academic Year</span><span>'.$e($receipt['year_name']).'</span></div><div class="row"><span>Method</span><span>'.$e($receipt['payment_methods']?:'-').'</span></div><div class="row"><span>Discount</span><span>₹'.number_format((float)$receipt['discount_amount'],2).'</span></div><div class="row"><span>Fine</span><span>₹'.number_format((float)$receipt['fine_amount'],2).'</span></div><div class="row total"><span>Paid</span><span>₹'.number_format((float)$receipt['paid_amount'],2).'</span></div><div class="row"><span>Status</span><span>'.$e(ucfirst($receipt['payment_status'])).'</span></div><div class="actions"><button onclick="window.print()">Print</button></div></div></body></html>';
        exit;
    }

    sfOut(false,'Invalid Student Fees action.',[],400);
}catch(InvalidArgumentException $error){
    if($pdo->inTransaction())$pdo->rollBack();
    sfOut(false,$error->getMessage(),[],422);
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    error_log('student-fees.php: '.$error->getMessage());
    sfOut(false,'Student Fees request failed: '.$error->getMessage(),[],500);
}

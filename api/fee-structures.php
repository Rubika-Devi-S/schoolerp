<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors','0');
error_reporting(E_ALL);
require_once dirname(__DIR__).'/includes/bootstrap.php';

function fsOut(bool $success,string $message='',array $data=[],int $status=200):never{
    while(ob_get_level()>0){ob_end_clean();}
    if(!headers_sent()){http_response_code($status);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');}
    echo json_encode(['success'=>$success,'message'=>$message,'data'=>$data],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function fsInput():array{
    $type=strtolower((string)($_SERVER['CONTENT_TYPE']??''));
    if(str_contains($type,'application/json')){
        $data=json_decode((string)file_get_contents('php://input'),true);
        return is_array($data)?$data:[];
    }
    return $_POST;
}
function fsScope():array{
    $user=function_exists('current_user')?current_user():[];
    $user=is_array($user)?$user:[];
    return[
        'tenant_id'=>(int)($user['tenant_id']??$user['school_id']??$_SESSION['tenant_id']??$_SESSION['school_id']??0),
        'user_id'=>(int)($user['id']??$user['user_id']??$_SESSION['user_id']??0),
    ];
}
function fsCsrf(array $input):void{
    $session=(string)($_SESSION['fee_csrf_token']??'');
    $request=(string)($input['csrf_token']??'');
    if($session===''||$request===''||!hash_equals($session,$request))fsOut(false,'Invalid or expired CSRF token. Refresh the page.',[],419);
}
function fsTable(PDO $pdo,string $table):bool{
    $s=$pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:table");
    $s->execute(['table'=>$table]);
    return (int)$s->fetchColumn()>0;
}
function fsColumn(PDO $pdo,string $table,string $column):bool{
    $s=$pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=:table AND column_name=:column");
    $s->execute(['table'=>$table,'column'=>$column]);
    return (int)$s->fetchColumn()>0;
}
function fsIndex(PDO $pdo,string $table,string $index):bool{
    $s=$pdo->prepare("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=:table AND index_name=:index");
    $s->execute(['table'=>$table,'index'=>$index]);
    return (int)$s->fetchColumn()>0;
}
function fsEnsure(PDO $pdo,int $tenantId):void{
    foreach(['academic_years','classes','fee_heads','fee_structures','fee_structure_items','student_fee_assignments'] as $table){
        if(!fsTable($pdo,$table))throw new RuntimeException('Missing required database table: '.$table.'.');
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS fee_types(
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        tenant_id BIGINT UNSIGNED NOT NULL,
        fee_type_name VARCHAR(120) NOT NULL,
        fee_type_code VARCHAR(50) NOT NULL,
        description VARCHAR(500) NULL,
        default_frequency ENUM('one_time','monthly','term','quarterly','half_yearly','yearly','annual','custom') NOT NULL DEFAULT 'one_time',
        default_occurrence_count SMALLINT UNSIGNED NOT NULL DEFAULT 1,
        is_mandatory TINYINT(1) NOT NULL DEFAULT 0,
        usage_scope ENUM('structure','extra','both') NOT NULL DEFAULT 'structure',
        is_enabled TINYINT(1) NOT NULL DEFAULT 1,
        display_order INT NOT NULL DEFAULT 0,
        created_by BIGINT UNSIGNED NULL,
        updated_by BIGINT UNSIGNED NULL,
        deleted_at DATETIME NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY(id),
        UNIQUE KEY uk_fee_types_tenant_code(tenant_id,fee_type_code),
        KEY idx_fee_types_list(tenant_id,is_enabled,display_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if(!fsColumn($pdo,'fee_types','usage_scope')){
        $pdo->exec(
            "ALTER TABLE fee_types
             ADD COLUMN usage_scope
             ENUM('structure','extra','both')
             NOT NULL DEFAULT 'structure'
             AFTER is_mandatory"
        );
    }

    try{
        $pdo->exec(
            "ALTER TABLE fee_types
             MODIFY default_frequency
             ENUM(
                'one_time','monthly','term','quarterly',
                'half_yearly','yearly','annual','custom'
             )
             NOT NULL DEFAULT 'one_time'"
        );
    }catch(Throwable $ignored){
        // Column may already use the expanded frequency enum.
    }

    $columns=[
        'fee_type_id'=>"BIGINT UNSIGNED NULL AFTER fee_head_id",
        'frequency'=>"VARCHAR(20) NULL AFTER amount",
        'occurrence_count'=>"SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER frequency",
        'display_order'=>"INT NOT NULL DEFAULT 0 AFTER occurrence_count",
        'status'=>"ENUM('active','inactive') NOT NULL DEFAULT 'active' AFTER display_order",
    ];
    foreach($columns as $column=>$definition){
        if(!fsColumn($pdo,'fee_structure_items',$column)){
            $pdo->exec("ALTER TABLE fee_structure_items ADD COLUMN `$column` $definition");
        }
    }
    if(!fsIndex($pdo,'fee_structure_items','idx_fsi_fee_type')){
        $pdo->exec("ALTER TABLE fee_structure_items ADD KEY idx_fsi_fee_type(fee_type_id)");
    }

    /*
     * Older installations may have frequency as a restricted ENUM.
     * Expand it so Quarterly, Half-Yearly and Yearly values save correctly.
     */
    try{
        $pdo->exec(
            "ALTER TABLE fee_structure_items
             MODIFY COLUMN frequency
             ENUM(
                'one_time',
                'monthly',
                'term',
                'quarterly',
                'half_yearly',
                'yearly',
                'annual',
                'custom'
             )
             NULL DEFAULT NULL"
        );
    }catch(Throwable $e){
        /*
         * Fall back to VARCHAR when the server rejects ENUM modification.
         * All application validation still restricts accepted frequency values.
         */
        $pdo->exec(
            "ALTER TABLE fee_structure_items
             MODIFY COLUMN frequency VARCHAR(30) NULL DEFAULT NULL"
        );
    }

    $count=$pdo->prepare("SELECT COUNT(*) FROM fee_types WHERE tenant_id=:tenant_id AND deleted_at IS NULL");
    $count->execute(['tenant_id'=>$tenantId]);
    if((int)$count->fetchColumn()===0){
        $defaults=[
            ['Tuition Fee','TUITION_FEE','Monthly tuition charge','monthly',12,1,10],
            ['Admission Fee','ADMISSION_FEE','One-time admission charge','one_time',1,1,20],
            ['Transport Fee','TRANSPORT_FEE','School transport charge','monthly',12,0,30],
            ['Hostel Fee','HOSTEL_FEE','Hostel accommodation charge','monthly',12,0,40],
            ['Exam Fee','EXAM_FEE','Examination charge','term',3,0,50],
            ['Library Fee','LIBRARY_FEE','Library facility charge','annual',1,0,60],
            ['Laboratory Fee','LABORATORY_FEE','Laboratory facility charge','annual',1,0,70],
            ['Sports Fee','SPORTS_FEE','Sports and activity charge','annual',1,0,80],
            ['ID Card Fee','ID_CARD_FEE','Student identity card charge','one_time',1,0,90],
            ['Uniform Fee','UNIFORM_FEE','School uniform charge','annual',1,0,100],
            ['Books Fee','BOOKS_FEE','Books and study materials charge','annual',1,0,110],
            ['Miscellaneous Fee','MISCELLANEOUS_FEE','Other approved school charges','custom',1,0,120],
        ];
        $insert=$pdo->prepare("INSERT INTO fee_types(tenant_id,fee_type_name,fee_type_code,description,default_frequency,default_occurrence_count,is_mandatory,is_enabled,display_order) VALUES(:tenant_id,:name,:code,:description,:frequency,:occurrences,:mandatory,1,:display_order)");
        foreach($defaults as [$name,$code,$description,$frequency,$occurrences,$mandatory,$order]){
            $insert->execute(['tenant_id'=>$tenantId,'name'=>$name,'code'=>$code,'description'=>$description,'frequency'=>$frequency,'occurrences'=>$occurrences,'mandatory'=>$mandatory,'display_order'=>$order]);
        }
    }


    $pdo->exec("CREATE TABLE IF NOT EXISTS extra_fee_batches(
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        tenant_id BIGINT UNSIGNED NOT NULL,
        academic_year_id BIGINT UNSIGNED NOT NULL,
        class_id BIGINT UNSIGNED NOT NULL,
        section_id BIGINT UNSIGNED NULL,
        fee_type_id BIGINT UNSIGNED NULL,
        gender ENUM('all','male','female') NOT NULL DEFAULT 'all',
        fee_name VARCHAR(150) NOT NULL,
        description VARCHAR(500) NULL,
        amount DECIMAL(12,2) NOT NULL,
        due_date DATE NOT NULL,
        assigned_count INT NOT NULL DEFAULT 0,
        status ENUM('active','cancelled') NOT NULL DEFAULT 'active',
        created_by BIGINT UNSIGNED NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(id),
        KEY idx_extra_fee_batch(tenant_id,academic_year_id,class_id,status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    if(!fsColumn($pdo,'extra_fee_batches','fee_type_id')){
        $pdo->exec(
            "ALTER TABLE extra_fee_batches
             ADD COLUMN fee_type_id BIGINT UNSIGNED NULL AFTER section_id"
        );
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS extra_fee_student_assignments(
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        tenant_id BIGINT UNSIGNED NOT NULL,
        batch_id BIGINT UNSIGNED NOT NULL,
        student_id BIGINT UNSIGNED NOT NULL,
        fee_assignment_id BIGINT UNSIGNED NOT NULL,
        student_fee_item_id BIGINT UNSIGNED NOT NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(id),
        UNIQUE KEY uk_extra_fee_student(batch_id,student_id),
        KEY idx_extra_fee_student_item(student_fee_item_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    fsSyncManagedClasses($pdo,$tenantId);

    /* Migrate legacy Admission/Tuition/Term items when possible. */
    if(fsColumn($pdo,'fee_structure_items','fee_type_key')){
        $map=['admission'=>'ADMISSION_FEE','tuition'=>'TUITION_FEE','term'=>'EXAM_FEE'];
        foreach($map as $legacy=>$code){
            $s=$pdo->prepare("UPDATE fee_structure_items fsi INNER JOIN fee_types ft ON ft.tenant_id=:tenant_id AND ft.fee_type_code=:code SET fsi.fee_type_id=ft.id WHERE fsi.fee_type_id IS NULL AND fsi.fee_type_key=:legacy");
            $s->execute(['tenant_id'=>$tenantId,'code'=>$code,'legacy'=>$legacy]);
        }
    }
}

/**
 * Synchronize Class Management rows into the canonical classes/sections tables
 * used by Fee Structures, Students, Attendance and Fee Collection.
 */
function fsSyncManagedClasses(PDO $pdo,int $tenantId):void{
    if(!fsTable($pdo,'class_management_classes')){
        return;
    }

    $source=$pdo->prepare(
        "SELECT
            id,academic_year_id,class_name,section_name,
            maximum_strength,display_order,status
         FROM class_management_classes
         WHERE tenant_id=:tenant_id
           AND status='active'
         ORDER BY academic_year_id,display_order,class_name,section_name,id"
    );
    $source->execute(['tenant_id'=>$tenantId]);
    $rows=$source->fetchAll(PDO::FETCH_ASSOC);

    if(!$rows){
        return;
    }

    $findClass=$pdo->prepare(
        "SELECT id
         FROM classes
         WHERE tenant_id=:tenant_id
           AND academic_year_id=:academic_year_id
           AND class_name=:class_name
         LIMIT 1"
    );
    $insertClass=$pdo->prepare(
        "INSERT INTO classes(
            tenant_id,academic_year_id,class_name,display_order,status
         ) VALUES(
            :tenant_id,:academic_year_id,:class_name,:display_order,'active'
         )"
    );
    $updateClass=$pdo->prepare(
        "UPDATE classes
         SET display_order=:display_order,status='active'
         WHERE id=:id AND tenant_id=:tenant_id"
    );

    $findSection=$pdo->prepare(
        "SELECT id
         FROM sections
         WHERE tenant_id=:tenant_id
           AND class_id=:class_id
           AND section_name=:section_name
         LIMIT 1"
    );
    $insertSection=$pdo->prepare(
        "INSERT INTO sections(
            tenant_id,class_id,section_name,capacity,status
         ) VALUES(
            :tenant_id,:class_id,:section_name,:capacity,'active'
         )"
    );
    $updateSection=$pdo->prepare(
        "UPDATE sections
         SET capacity=:capacity,status='active'
         WHERE id=:id AND tenant_id=:tenant_id"
    );

    foreach($rows as $row){
        $yearId=(int)$row['academic_year_id'];
        $className=trim((string)$row['class_name']);
        $sectionName=trim((string)$row['section_name']);

        if($yearId<=0||$className===''){
            continue;
        }

        $findClass->execute([
            'tenant_id'=>$tenantId,
            'academic_year_id'=>$yearId,
            'class_name'=>$className,
        ]);
        $classId=(int)($findClass->fetchColumn()?:0);

        if($classId<=0){
            $insertClass->execute([
                'tenant_id'=>$tenantId,
                'academic_year_id'=>$yearId,
                'class_name'=>$className,
                'display_order'=>(int)($row['display_order']??0),
            ]);
            $classId=(int)$pdo->lastInsertId();
        }else{
            $updateClass->execute([
                'display_order'=>(int)($row['display_order']??0),
                'id'=>$classId,
                'tenant_id'=>$tenantId,
            ]);
        }

        if($sectionName===''){
            continue;
        }

        $findSection->execute([
            'tenant_id'=>$tenantId,
            'class_id'=>$classId,
            'section_name'=>$sectionName,
        ]);
        $sectionId=(int)($findSection->fetchColumn()?:0);
        $capacity=max(1,(int)($row['maximum_strength']??40));

        if($sectionId<=0){
            $insertSection->execute([
                'tenant_id'=>$tenantId,
                'class_id'=>$classId,
                'section_name'=>$sectionName,
                'capacity'=>$capacity,
            ]);
        }else{
            $updateSection->execute([
                'capacity'=>$capacity,
                'id'=>$sectionId,
                'tenant_id'=>$tenantId,
            ]);
        }
    }
}

function fsMeta(PDO $pdo,int $tenantId):array{
    $y=$pdo->prepare("SELECT id,year_name,start_date,end_date,is_current,status FROM academic_years WHERE tenant_id=:tenant_id ORDER BY is_current DESC,start_date DESC,id DESC");
    $y->execute(['tenant_id'=>$tenantId]);
    $c=$pdo->prepare(
        "SELECT id,academic_year_id,class_name,display_order
         FROM classes
         WHERE tenant_id=:tenant_id
           AND status='active'
         ORDER BY academic_year_id DESC,display_order,class_name,id"
    );
    $c->execute(['tenant_id'=>$tenantId]);
    $s=$pdo->prepare("SELECT sec.id,sec.class_id,sec.section_name,c.academic_year_id FROM sections sec INNER JOIN classes c ON c.id=sec.class_id AND c.tenant_id=sec.tenant_id WHERE sec.tenant_id=:tenant_id AND sec.status='active' ORDER BY c.display_order,sec.section_name");
    $s->execute(['tenant_id'=>$tenantId]);
    $structureTypes=$pdo->prepare(
        "SELECT
            id,fee_type_name,fee_type_code,description,
            default_frequency,default_occurrence_count,
            is_mandatory,is_enabled,usage_scope,display_order
         FROM fee_types
         WHERE tenant_id=:tenant_id
           AND is_enabled=1
           AND deleted_at IS NULL
           AND COALESCE(usage_scope,'structure') IN('structure','both')
         ORDER BY display_order,fee_type_name,id"
    );
    $structureTypes->execute(['tenant_id'=>$tenantId]);

    $extraTypes=$pdo->prepare(
        "SELECT
            id,fee_type_name,fee_type_code,description,
            default_frequency,default_occurrence_count,
            is_mandatory,is_enabled,usage_scope,display_order
         FROM fee_types
         WHERE tenant_id=:tenant_id
           AND is_enabled=1
           AND deleted_at IS NULL
           AND COALESCE(usage_scope,'structure') IN('extra','both')
         ORDER BY display_order,fee_type_name,id"
    );
    $extraTypes->execute(['tenant_id'=>$tenantId]);

    $structureRows=$structureTypes->fetchAll(PDO::FETCH_ASSOC);
    $extraRows=$extraTypes->fetchAll(PDO::FETCH_ASSOC);

    return[
        'years'=>$y->fetchAll(PDO::FETCH_ASSOC),
        'classes'=>$c->fetchAll(PDO::FETCH_ASSOC),
        'sections'=>$s->fetchAll(PDO::FETCH_ASSOC),
        // Backward-compatible key for older pages.
        'fee_types'=>$structureRows,
        'structure_fee_types'=>$structureRows,
        'extra_fee_types'=>$extraRows,
    ];
}
function fsHeadId(PDO $pdo,int $tenantId,array $type):int{
    $code=(string)$type['fee_type_code'];
    $name=(string)$type['fee_type_name'];
    $s=$pdo->prepare("SELECT id FROM fee_heads WHERE tenant_id=:tenant_id AND UPPER(head_code)=UPPER(:code) LIMIT 1");
    $s->execute(['tenant_id'=>$tenantId,'code'=>$code]);
    $id=(int)($s->fetchColumn()?:0);
    if($id>0)return $id;

    $insert=$pdo->prepare("INSERT INTO fee_heads(tenant_id,head_name,head_code,is_refundable,status) VALUES(:tenant_id,:name,:code,0,'active')");
    $insert->execute(['tenant_id'=>$tenantId,'name'=>$name,'code'=>$code]);
    return (int)$pdo->lastInsertId();
}
function fsItemRows(PDO $pdo,int $structureId):array{
    $s=$pdo->prepare("SELECT fsi.id,fsi.fee_structure_id,fsi.fee_type_id,fsi.amount,fsi.frequency,fsi.occurrence_count,fsi.display_order,fsi.status,ft.fee_type_name,ft.fee_type_code,ft.description,ft.is_mandatory,ft.is_enabled FROM fee_structure_items fsi LEFT JOIN fee_types ft ON ft.id=fsi.fee_type_id WHERE fsi.fee_structure_id=:id ORDER BY fsi.display_order,fsi.id");
    $s->execute(['id'=>$structureId]);
    $items=$s->fetchAll(PDO::FETCH_ASSOC);
    foreach($items as &$item){
        $item['fee_type_name']=$item['fee_type_name']?:('Fee Type #'.(int)$item['fee_type_id']);
        $item['frequency']=$item['frequency']?:'one_time';
        $item['occurrence_count']=max(1,(int)$item['occurrence_count']);
        $item['annual_amount']=round((float)$item['amount']*$item['occurrence_count'],2);
    }
    unset($item);
    return $items;
}
function fsList(PDO $pdo,int $tenantId,array $filters):array{
    $where=['fs.tenant_id=:tenant_id'];$params=['tenant_id'=>$tenantId];
    $search=trim((string)($filters['search']??''));
    if($search!==''){$where[]="(fs.structure_name LIKE :search_structure OR c.class_name LIKE :search_class OR ay.year_name LIKE :search_year)";$searchValue='%'.$search.'%';$params['search_structure']=$searchValue;$params['search_class']=$searchValue;$params['search_year']=$searchValue;}
    foreach(['academic_year_id'=>'fs.academic_year_id','class_id'=>'fs.class_id'] as $key=>$column){$value=(string)($filters[$key]??'all');if($value!==''&&$value!=='all'){$where[]="$column=:$key";$params[$key]=(int)$value;}}
    $status=strtolower(trim((string)($filters['status']??'all')));if(in_array($status,['active','inactive'],true)){$where[]='fs.status=:status';$params['status']=$status;}
    $page=max(1,(int)($filters['page']??1));$per=min(100,max(5,(int)($filters['per_page']??10)));
    $base=" FROM fee_structures fs INNER JOIN academic_years ay ON ay.id=fs.academic_year_id AND ay.tenant_id=fs.tenant_id INNER JOIN classes c ON c.id=fs.class_id AND c.tenant_id=fs.tenant_id WHERE ".implode(' AND ',$where);
    $count=$pdo->prepare("SELECT COUNT(*)".$base);$count->execute($params);$total=(int)$count->fetchColumn();$last=max(1,(int)ceil($total/$per));$page=min($page,$last);$offset=($page-1)*$per;
    $sort=match((string)($filters['sort']??'')){'class_asc'=>'ay.start_date DESC,c.display_order,c.class_name','amount_desc'=>'fs.id DESC','amount_asc'=>'fs.id ASC',default=>'fs.id DESC'};
    $s=$pdo->prepare("SELECT fs.*,ay.year_name,c.class_name,c.display_order,(SELECT COUNT(*) FROM student_fee_assignments a WHERE a.tenant_id=fs.tenant_id AND a.fee_structure_id=fs.id) assignment_count".$base." ORDER BY $sort LIMIT $per OFFSET $offset");
    $s->execute($params);$rows=$s->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as &$row){
        $row['items']=fsItemRows($pdo,(int)$row['id']);
        $row['item_count']=count($row['items']);
        $row['annual_total']=round(array_sum(array_column($row['items'],'annual_amount')),2);
    }
    unset($row);
    if(($filters['sort']??'')==='amount_desc')usort($rows,fn($a,$b)=>(float)$b['annual_total']<=>(float)$a['annual_total']);
    if(($filters['sort']??'')==='amount_asc')usort($rows,fn($a,$b)=>(float)$a['annual_total']<=>(float)$b['annual_total']);
    return['records'=>$rows,'pagination'=>['total'=>$total,'page'=>$page,'per_page'=>$per,'last_page'=>$last]];
}
function fsStats(PDO $pdo,int $tenantId):array{
    $list=fsList($pdo,$tenantId,['page'=>1,'per_page'=>10000]);$rows=$list['records'];$classes=[];$amount=0.0;$active=0;
    foreach($rows as $row){if($row['status']==='active')$active++;$classes[$row['academic_year_id'].':'.$row['class_id']]=true;$amount+=(float)$row['annual_total'];}
    return['total'=>count($rows),'active'=>$active,'classes'=>count($classes),'amount'=>$amount];
}


function fsAggregateAssignment(PDO $pdo,int $tenantId,int $assignmentId):void{
    $s=$pdo->prepare("SELECT COALESCE(SUM(original_amount),0) gross,COALESCE(SUM(discount_amount),0) discount,COALESCE(SUM(paid_amount),0) paid,COALESCE(SUM(balance_amount),0) balance,COALESCE(SUM(CASE WHEN item_type<>'transport' THEN original_amount ELSE 0 END),0) base,COALESCE(SUM(CASE WHEN item_type='transport' THEN original_amount ELSE 0 END),0) transport FROM student_fee_items WHERE tenant_id=:tenant_id AND assignment_id=:assignment_id AND item_status<>'cancelled'");
    $s->execute(['tenant_id'=>$tenantId,'assignment_id'=>$assignmentId]);
    $t=$s->fetch(PDO::FETCH_ASSOC)?:[];
    $status=(float)($t['balance']??0)<=0.009?'paid':((float)($t['paid']??0)>0?'partial':'unpaid');
    $u=$pdo->prepare("UPDATE student_fee_assignments SET base_fee_amount=:base,transport_fee_amount=:transport,gross_amount=:gross,net_amount=:net,paid_amount=:paid,balance_amount=:balance,payment_status=:status WHERE id=:id AND tenant_id=:tenant_id");
    $u->execute([
        'base'=>(float)($t['base']??0),'transport'=>(float)($t['transport']??0),
        'gross'=>(float)($t['gross']??0),'net'=>max(0,(float)($t['gross']??0)-(float)($t['discount']??0)),
        'paid'=>(float)($t['paid']??0),'balance'=>(float)($t['balance']??0),
        'status'=>$status,'id'=>$assignmentId,'tenant_id'=>$tenantId
    ]);
}

if(!isset($pdo)||!$pdo instanceof PDO)fsOut(false,'Database connection unavailable.',[],500);
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
if(empty($_SESSION['fee_csrf_token']))$_SESSION['fee_csrf_token']=bin2hex(random_bytes(32));
$scope=fsScope();
if($scope['tenant_id']<=0||$scope['user_id']<=0)fsOut(false,'School tenant or user session was not found.',[],401);
try{fsEnsure($pdo,$scope['tenant_id']);}catch(Throwable $e){fsOut(false,'Unable to initialize Fee Structures: '.$e->getMessage(),[],500);}
$input=fsInput();$action=strtolower(trim((string)($input['action']??$_GET['action']??'')));

try{
    if($action==='meta'){
        fsOut(true,'Latest Fee Settings loaded.',[
            'meta'=>fsMeta($pdo,$scope['tenant_id']),
            'csrf_token'=>$_SESSION['fee_csrf_token'],
        ]);
    }

    if($action==='list'){
        $list=fsList($pdo,$scope['tenant_id'],array_merge($_GET,$input));
        fsOut(true,'Fee structures loaded.',['meta'=>fsMeta($pdo,$scope['tenant_id']),'records'=>$list['records'],'pagination'=>$list['pagination'],'stats'=>fsStats($pdo,$scope['tenant_id']),'csrf_token'=>$_SESSION['fee_csrf_token']]);
    }


    if($action==='extra_create'){
        fsCsrf($input);
        foreach(['students','student_enrollments','sections','student_fee_items'] as $table){
            if(!fsTable($pdo,$table))throw new RuntimeException('Missing required table: '.$table.'.');
        }
        $yearId=(int)($input['academic_year_id']??0);
        $classId=(int)($input['class_id']??0);
        $sectionId=(int)($input['section_id']??0);
        $gender=strtolower(trim((string)($input['gender']??'all')));
        $feeTypeId=(int)($input['fee_type_id']??0);
        $description=trim((string)($input['description']??''));
        $amount=round((float)($input['amount']??0),2);
        $dueDate=trim((string)($input['due_date']??''));

        if($yearId<=0||$classId<=0)throw new InvalidArgumentException('Academic Year and Class are required.');
        if($feeTypeId<=0)throw new InvalidArgumentException('Select an Extra Fee Name.');
        if($amount<=0)throw new InvalidArgumentException('Extra Fee Amount must be greater than zero.');

        $feeTypeStmt=$pdo->prepare(
            "SELECT id,fee_type_name,fee_type_code,description
             FROM fee_types
             WHERE id=:fee_type_id
               AND tenant_id=:tenant_id
               AND is_enabled=1
               AND deleted_at IS NULL
               AND COALESCE(usage_scope,'structure') IN('extra','both')
             LIMIT 1"
        );
        $feeTypeStmt->execute([
            'fee_type_id'=>$feeTypeId,
            'tenant_id'=>$scope['tenant_id'],
        ]);
        $feeType=$feeTypeStmt->fetch(PDO::FETCH_ASSOC);

        if(!$feeType){
            throw new InvalidArgumentException(
                'The selected fee type is not enabled for Extra Fees. Update Fees Settings → Use In to Extra Fees or Both.'
            );
        }

        $feeName=trim((string)$feeType['fee_type_name']);
        if($description===''){
            $description=trim((string)($feeType['description']??''));
        }
        if(!in_array($gender,['all','male','female'],true))throw new InvalidArgumentException('Invalid Gender filter.');
        $date=DateTimeImmutable::createFromFormat('Y-m-d',$dueDate);
        if(!$date||$date->format('Y-m-d')!==$dueDate)throw new InvalidArgumentException('Due Date is invalid.');

        $check=$pdo->prepare("SELECT id FROM classes WHERE id=:class_id AND academic_year_id=:year_id AND tenant_id=:tenant_id AND status='active'");
        $check->execute(['class_id'=>$classId,'year_id'=>$yearId,'tenant_id'=>$scope['tenant_id']]);
        if(!$check->fetchColumn())throw new InvalidArgumentException('Selected Class does not belong to the selected Academic Year.');
        if($sectionId>0){
            $sec=$pdo->prepare("SELECT id FROM sections WHERE id=:section_id AND class_id=:class_id AND tenant_id=:tenant_id AND status='active'");
            $sec->execute(['section_id'=>$sectionId,'class_id'=>$classId,'tenant_id'=>$scope['tenant_id']]);
            if(!$sec->fetchColumn())throw new InvalidArgumentException('Selected Section does not belong to the selected Class.');
        }

        $pdo->beginTransaction();
        $batch=$pdo->prepare("INSERT INTO extra_fee_batches(tenant_id,academic_year_id,class_id,section_id,fee_type_id,gender,fee_name,description,amount,due_date,created_by) VALUES(:tenant_id,:year_id,:class_id,:section_id,:fee_type_id,:gender,:fee_name,:description,:amount,:due_date,:created_by)");
        $batch->execute([
            'tenant_id'=>$scope['tenant_id'],'year_id'=>$yearId,'class_id'=>$classId,
            'section_id'=>$sectionId?:null,'fee_type_id'=>$feeTypeId,
            'gender'=>$gender,'fee_name'=>$feeName,
            'description'=>$description?:null,'amount'=>$amount,'due_date'=>$dueDate,
            'created_by'=>$scope['user_id']
        ]);
        $batchId=(int)$pdo->lastInsertId();

        $where=["e.tenant_id=:tenant_id","e.academic_year_id=:year_id","e.class_id=:class_id","e.enrollment_status='active'","st.status='active'","st.deleted_at IS NULL"];
        $params=['tenant_id'=>$scope['tenant_id'],'year_id'=>$yearId,'class_id'=>$classId];
        if($sectionId>0){$where[]='e.section_id=:section_id';$params['section_id']=$sectionId;}
        if($gender!=='all'){$where[]='LOWER(st.gender)=:gender';$params['gender']=$gender;}
        $students=$pdo->prepare("SELECT DISTINCT st.id FROM student_enrollments e INNER JOIN students st ON st.id=e.student_id AND st.tenant_id=e.tenant_id WHERE ".implode(' AND ',$where));
        $students->execute($params);
        $studentIds=array_map('intval',$students->fetchAll(PDO::FETCH_COLUMN));

        $assigned=0;$skipped=0;
        foreach($studentIds as $studentId){
            $a=$pdo->prepare("SELECT id FROM student_fee_assignments WHERE tenant_id=:tenant_id AND student_id=:student_id AND academic_year_id=:year_id AND assignment_status='active' ORDER BY id DESC LIMIT 1");
            $a->execute(['tenant_id'=>$scope['tenant_id'],'student_id'=>$studentId,'year_id'=>$yearId]);
            $assignmentId=(int)($a->fetchColumn()?:0);
            if($assignmentId<=0){$skipped++;continue;}

            $item=$pdo->prepare("INSERT INTO student_fee_items(tenant_id,assignment_id,student_id,academic_year_id,fee_structure_item_id,transport_route_id,source_receipt_id,item_type,item_name,period_key,period_label,due_date,original_amount,discount_amount,paid_amount,balance_amount,item_status) VALUES(:tenant_id,:assignment_id,:student_id,:year_id,NULL,NULL,NULL,'additional',:item_name,:period_key,:period_label,:due_date,:amount,0,0,:balance,'unpaid')");
            $item->execute([
                'tenant_id'=>$scope['tenant_id'],'assignment_id'=>$assignmentId,
                'student_id'=>$studentId,'year_id'=>$yearId,'item_name'=>$feeName,
                'period_key'=>'extra_fee_'.$batchId,'period_label'=>$feeName,
                'due_date'=>$dueDate,'amount'=>$amount,'balance'=>$amount
            ]);
            $itemId=(int)$pdo->lastInsertId();
            $link=$pdo->prepare("INSERT INTO extra_fee_student_assignments(tenant_id,batch_id,student_id,fee_assignment_id,student_fee_item_id) VALUES(:tenant_id,:batch_id,:student_id,:assignment_id,:item_id)");
            $link->execute(['tenant_id'=>$scope['tenant_id'],'batch_id'=>$batchId,'student_id'=>$studentId,'assignment_id'=>$assignmentId,'item_id'=>$itemId]);
            fsAggregateAssignment($pdo,$scope['tenant_id'],$assignmentId);
            $assigned++;
        }
        $pdo->prepare("UPDATE extra_fee_batches SET assigned_count=:count WHERE id=:id AND tenant_id=:tenant_id")->execute(['count'=>$assigned,'id'=>$batchId,'tenant_id'=>$scope['tenant_id']]);
        $pdo->commit();

        $message="Extra Fee assigned to $assigned existing student".($assigned===1?'':'s').".";
        if($skipped>0)$message.=" $skipped student".($skipped===1?' was':'s were')." skipped because no active Fee Structure assignment exists.";
        fsOut(true,$message,['batch_id'=>$batchId,'assigned_count'=>$assigned,'skipped_count'=>$skipped]);
    }

    if(in_array($action,['create','edit'],true)){
        fsCsrf($input);
        $id=(int)($input['id']??0);
        if($action==='edit'&&$id<=0)throw new InvalidArgumentException('Valid Fee Structure ID is required.');
        $yearId=(int)($input['academic_year_id']??0);
        $classId=(int)($input['class_id']??0);
        $name=trim((string)($input['structure_name']??''));
        $status=strtolower(trim((string)($input['status']??'active')));
        $items=$input['items']??[];

        if($yearId<=0||$classId<=0)throw new InvalidArgumentException('Academic Year and Class are required.');
        if(!in_array($status,['active','inactive'],true))throw new InvalidArgumentException('Invalid structure status.');
        if(!is_array($items)||$items===[])throw new InvalidArgumentException('At least one enabled Fee Type is required.');

        $classStmt=$pdo->prepare("SELECT c.id,c.class_name,ay.year_name FROM classes c INNER JOIN academic_years ay ON ay.id=c.academic_year_id AND ay.tenant_id=c.tenant_id WHERE c.id=:class_id AND c.academic_year_id=:year_id AND c.tenant_id=:tenant_id AND c.status='active' LIMIT 1");
        $classStmt->execute(['class_id'=>$classId,'year_id'=>$yearId,'tenant_id'=>$scope['tenant_id']]);
        $class=$classStmt->fetch(PDO::FETCH_ASSOC);
        if(!$class)throw new InvalidArgumentException('Selected Class does not belong to the selected Academic Year.');
        if($name==='')$name=$class['class_name'].' Fee Structure';
        if(mb_strlen($name)>150)throw new InvalidArgumentException('Fee Structure Name cannot exceed 150 characters.');

        $typesStmt=$pdo->prepare(
            "SELECT *
             FROM fee_types
             WHERE tenant_id=:tenant_id
               AND is_enabled=1
               AND deleted_at IS NULL
               AND COALESCE(usage_scope,'structure') IN('structure','both')
             ORDER BY display_order,id"
        );
        $typesStmt->execute(['tenant_id'=>$scope['tenant_id']]);
        $types=[];foreach($typesStmt->fetchAll(PDO::FETCH_ASSOC) as $type)$types[(int)$type['id']]=$type;
        if($types===[])throw new InvalidArgumentException('No enabled Fee Types are available for Fee Structures. Configure Fees Settings → Use In.');

        $normalized=[];$total=0.0;
        foreach($items as $item){
            if(!is_array($item))continue;
            $typeId=(int)($item['fee_type_id']??0);
            if($typeId<=0)continue;
            if(!isset($types[$typeId])){
                throw new InvalidArgumentException(
                    'One selected Fee Type is disabled or is not configured for Fee Structure.'
                );
            }
            $amount=$item['amount']??0;
            if(!is_numeric($amount)||(float)$amount<0)throw new InvalidArgumentException($types[$typeId]['fee_type_name'].' amount must be zero or greater.');
            $amount=round((float)$amount,2);
            if((int)$types[$typeId]['is_mandatory']===1&&$amount<=0)throw new InvalidArgumentException($types[$typeId]['fee_type_name'].' is mandatory and must be greater than zero.');

            $frequency=strtolower(trim((string)($item['frequency']??$types[$typeId]['default_frequency']??'one_time')));
            $allowedFrequencies=['one_time','monthly','term','quarterly','half_yearly','yearly','annual','custom'];
            if(!in_array($frequency,$allowedFrequencies,true)){
                throw new InvalidArgumentException($types[$typeId]['fee_type_name'].' frequency is invalid.');
            }

            $occurrences=max(1,min(36,(int)($item['occurrence_count']??$types[$typeId]['default_occurrence_count']??1)));
            $frequencyOccurrences=[
                'one_time'=>1,
                'monthly'=>12,
                'term'=>max(1,$occurrences),
                'quarterly'=>4,
                'half_yearly'=>2,
                'yearly'=>1,
                'annual'=>1,
                'custom'=>$occurrences,
            ];
            $occurrences=$frequencyOccurrences[$frequency]??$occurrences;

            $normalized[$typeId]=[
                'amount'=>$amount,
                'frequency'=>$frequency,
                'occurrences'=>$occurrences,
                'type'=>$types[$typeId]
            ];
            $total+=$amount*$occurrences;
        }
        foreach($types as $typeId=>$type){
            if((int)$type['is_mandatory']===1&&!isset($normalized[$typeId]))throw new InvalidArgumentException($type['fee_type_name'].' configuration is required.');
        }
        if($total<=0)throw new InvalidArgumentException('Enter at least one fee amount greater than zero.');

        $pdo->beginTransaction();
        $duplicate=$pdo->prepare("SELECT id FROM fee_structures WHERE tenant_id=:tenant_id AND academic_year_id=:year_id AND class_id=:class_id AND LOWER(structure_name)=LOWER(:name) AND id<>:id LIMIT 1 FOR UPDATE");
        $duplicate->execute(['tenant_id'=>$scope['tenant_id'],'year_id'=>$yearId,'class_id'=>$classId,'name'=>$name,'id'=>$id]);
        if($duplicate->fetchColumn())throw new InvalidArgumentException('A Fee Structure with this name already exists for the selected Academic Year and Class.');

        if($status==='active'){
            $active=$pdo->prepare("SELECT id,structure_name FROM fee_structures WHERE tenant_id=:tenant_id AND academic_year_id=:year_id AND class_id=:class_id AND status='active' AND id<>:id LIMIT 1 FOR UPDATE");
            $active->execute(['tenant_id'=>$scope['tenant_id'],'year_id'=>$yearId,'class_id'=>$classId,'id'=>$id]);
            $other=$active->fetch(PDO::FETCH_ASSOC);
            if($other)throw new InvalidArgumentException('Only one active Fee Structure is allowed. Deactivate "'.$other['structure_name'].'" first.');
        }

        $existing=null;$assignmentCount=0;
        if($id>0){
            $e=$pdo->prepare("SELECT * FROM fee_structures WHERE id=:id AND tenant_id=:tenant_id FOR UPDATE");
            $e->execute(['id'=>$id,'tenant_id'=>$scope['tenant_id']]);$existing=$e->fetch(PDO::FETCH_ASSOC);
            if(!$existing)throw new InvalidArgumentException('Fee Structure not found.');
            $assigned=$pdo->prepare("SELECT COUNT(*) FROM student_fee_assignments WHERE tenant_id=:tenant_id AND fee_structure_id=:id");
            $assigned->execute(['tenant_id'=>$scope['tenant_id'],'id'=>$id]);$assignmentCount=(int)$assigned->fetchColumn();
            if($assignmentCount>0){
                $oldItems=[];
                foreach(fsItemRows($pdo,$id) as $row){
                    $oldItems[(int)$row['fee_type_id']]=[
                        'amount'=>(float)$row['amount'],
                        'frequency'=>(string)$row['frequency'],
                        'occurrences'=>(int)$row['occurrence_count'],
                    ];
                }

                /*
                 * Once assigned, the fee amount and fee-type composition remain
                 * protected. Frequency and occurrences may be changed because
                 * Fee Collection can safely rebuild only unpaid installments.
                 */
                if(count($oldItems)!==count($normalized)){
                    throw new InvalidArgumentException(
                        'Fee types cannot be added or removed after this structure is assigned to students. Frequency and occurrences may still be updated.'
                    );
                }

                foreach($normalized as $typeId=>$item){
                    if(!array_key_exists($typeId,$oldItems)){
                        throw new InvalidArgumentException(
                            'Fee types cannot be added or removed after this structure is assigned to students.'
                        );
                    }

                    if(abs($oldItems[$typeId]['amount']-$item['amount'])>0.009){
                        throw new InvalidArgumentException(
                            'Fee amount cannot be changed after this structure is assigned to students. You may update only Frequency and Occurrences.'
                        );
                    }
                }

                if(
                    (int)$existing['academic_year_id']!==$yearId
                    || (int)$existing['class_id']!==$classId
                ){
                    throw new InvalidArgumentException(
                        'Academic Year and Class cannot be changed after assignment.'
                    );
                }
            }
        }

        if($id>0){
            $u=$pdo->prepare("UPDATE fee_structures SET academic_year_id=:year_id,class_id=:class_id,structure_name=:name,status=:status WHERE id=:id AND tenant_id=:tenant_id");
            $u->execute(['year_id'=>$yearId,'class_id'=>$classId,'name'=>$name,'status'=>$status,'id'=>$id,'tenant_id'=>$scope['tenant_id']]);
            $message=$assignmentCount>0
                ?'Fee Structure updated successfully. Unpaid student schedules will synchronize automatically in Fee Collection.'
                :'Fee Structure updated successfully.';
        }else{
            $i=$pdo->prepare("INSERT INTO fee_structures(tenant_id,academic_year_id,class_id,structure_name,status) VALUES(:tenant_id,:year_id,:class_id,:name,:status)");
            $i->execute(['tenant_id'=>$scope['tenant_id'],'year_id'=>$yearId,'class_id'=>$classId,'name'=>$name,'status'=>$status]);
            $id=(int)$pdo->lastInsertId();$message='Fee Structure created successfully.';
        }

        $existingItem=$pdo->prepare("SELECT id FROM fee_structure_items WHERE fee_structure_id=:structure_id AND fee_type_id=:fee_type_id LIMIT 1");
        $updateItem=$pdo->prepare("UPDATE fee_structure_items SET fee_head_id=:head_id,amount=:amount,frequency=:frequency,occurrence_count=:occurrences,display_order=:display_order,status='active' WHERE id=:id");
        $insertItem=$pdo->prepare("INSERT INTO fee_structure_items(fee_structure_id,fee_head_id,fee_type_id,amount,due_date,fine_type,fine_value,frequency,occurrence_count,display_order,status) VALUES(:structure_id,:head_id,:fee_type_id,:amount,NULL,'none',0,:frequency,:occurrences,:display_order,'active')");

        foreach($normalized as $typeId=>$item){
            $type=$item['type'];$headId=fsHeadId($pdo,$scope['tenant_id'],$type);
            $existingItem->execute(['structure_id'=>$id,'fee_type_id'=>$typeId]);$itemId=(int)$existingItem->fetchColumn();
            $params=[
                'head_id'=>$headId,
                'amount'=>$item['amount'],
                'frequency'=>$item['frequency'],
                'occurrences'=>$item['occurrences'],
                'display_order'=>(int)$type['display_order']
            ];
            if($itemId>0){$params['id']=$itemId;$updateItem->execute($params);}
            else{$params['structure_id']=$id;$params['fee_type_id']=$typeId;$insertItem->execute($params);}
        }

        /*
         * Unused enabled optional types are removed only before assignment.
         * Disabled historical fee types are preserved for reporting.
         */
        if($assignmentCount===0){
            $ids=array_keys($normalized);
            if($ids!==[]){
                $marks=implode(',',array_fill(0,count($ids),'?'));
                $delete=$pdo->prepare("DELETE fsi FROM fee_structure_items fsi INNER JOIN fee_types ft ON ft.id=fsi.fee_type_id WHERE fsi.fee_structure_id=? AND ft.is_enabled=1 AND fsi.fee_type_id NOT IN ($marks)");
                $delete->execute([$id,...$ids]);
            }
        }

        $pdo->commit();
        fsOut(true,$message,['id'=>$id,'annual_total'=>round($total,2)]);
    }

    if($action==='delete'){
        fsCsrf($input);$id=(int)($input['id']??0);
        if($id<=0)throw new InvalidArgumentException('Valid Fee Structure ID is required.');
        $count=$pdo->prepare("SELECT COUNT(*) FROM student_fee_assignments WHERE tenant_id=:tenant_id AND fee_structure_id=:id");
        $count->execute(['tenant_id'=>$scope['tenant_id'],'id'=>$id]);
        if((int)$count->fetchColumn()>0)throw new InvalidArgumentException('This Fee Structure is assigned to students. Set it to Inactive instead of deleting it.');
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM fee_structure_items WHERE fee_structure_id=:id")->execute(['id'=>$id]);
        $delete=$pdo->prepare("DELETE FROM fee_structures WHERE id=:id AND tenant_id=:tenant_id");
        $delete->execute(['id'=>$id,'tenant_id'=>$scope['tenant_id']]);
        if($delete->rowCount()===0)throw new InvalidArgumentException('Fee Structure not found.');
        $pdo->commit();fsOut(true,'Fee Structure deleted successfully.');
    }

    fsOut(false,'Invalid Fee Structures action.',[],400);
}catch(InvalidArgumentException $e){
    if($pdo->inTransaction())$pdo->rollBack();
    fsOut(false,$e->getMessage(),[],422);
}catch(PDOException $e){
    if($pdo->inTransaction())$pdo->rollBack();
    error_log('fee-structures.php: '.$e->getMessage());
    fsOut(false,$e->getCode()==='23000'
        ?'Duplicate Fee Structure detected.'
        :(str_contains($e->getMessage(),'Data truncated for column')&&str_contains($e->getMessage(),'frequency')
            ?'The frequency database column is outdated. Refresh once after replacing the API so it can upgrade automatically.'
            :$e->getMessage()),[],422);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    error_log('fee-structures.php: '.$e->getMessage());
    fsOut(false,'Unable to complete Fee Structures request.',[],500);
}

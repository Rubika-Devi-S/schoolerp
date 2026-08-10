<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors','0');
error_reporting(E_ALL);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

function fstOut(bool $success,string $message='',array $data=[],int $status=200):never{
    while(ob_get_level()>0){ob_end_clean();}
    if(!headers_sent()){http_response_code($status);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');}
    echo json_encode(['success'=>$success,'message'=>$message,'data'=>$data],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function fstInput():array{
    $type=strtolower((string)($_SERVER['CONTENT_TYPE']??''));
    if(str_contains($type,'application/json')){
        $data=json_decode((string)file_get_contents('php://input'),true);
        return is_array($data)?$data:[];
    }
    return $_POST;
}
function fstScope():array{
    $user=function_exists('current_user')?current_user():[];
    $user=is_array($user)?$user:[];
    return[
        'tenant_id'=>(int)($user['tenant_id']??$user['school_id']??$_SESSION['tenant_id']??$_SESSION['school_id']??0),
        'user_id'=>(int)($user['id']??$user['user_id']??$_SESSION['user_id']??0),
    ];
}
function fstCsrf(array $input):void{
    $session=(string)($_SESSION['fee_csrf_token']??'');
    $request=(string)($input['csrf_token']??'');
    if($session===''||$request===''||!hash_equals($session,$request)){
        fstOut(false,'Invalid or expired CSRF token. Refresh the page.',[],419);
    }
}
function fstTable(PDO $pdo,string $table):bool{
    $s=$pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:table");
    $s->execute(['table'=>$table]);
    return (int)$s->fetchColumn()>0;
}
function fstColumn(PDO $pdo,string $table,string $column):bool{
    $s=$pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=:table AND column_name=:column");
    $s->execute(['table'=>$table,'column'=>$column]);
    return (int)$s->fetchColumn()>0;
}
function fstIndex(PDO $pdo,string $table,string $index):bool{
    $s=$pdo->prepare("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=:table AND index_name=:index");
    $s->execute(['table'=>$table,'index'=>$index]);
    return (int)$s->fetchColumn()>0;
}
function fstEnsure(PDO $pdo,int $tenantId):void{
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
        KEY idx_fee_types_list(tenant_id,is_enabled,display_order),
        KEY idx_fee_types_deleted(tenant_id,deleted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if(!fstColumn($pdo,'fee_types','usage_scope')){
        $pdo->exec("ALTER TABLE fee_types ADD COLUMN usage_scope ENUM('structure','extra','both') NOT NULL DEFAULT 'structure' AFTER is_mandatory");
    }
    try{
        $pdo->exec("ALTER TABLE fee_types MODIFY default_frequency ENUM('one_time','monthly','term','quarterly','half_yearly','yearly','annual','custom') NOT NULL DEFAULT 'one_time'");
    }catch(Throwable $ignored){
        // Existing database may already have the expanded enum.
    }

    if(fstTable($pdo,'fee_structure_items')){
        $columns=[
            'fee_type_id'=>"BIGINT UNSIGNED NULL AFTER fee_head_id",
            'frequency'=>"VARCHAR(20) NULL AFTER amount",
            'occurrence_count'=>"SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER frequency",
            'display_order'=>"INT NOT NULL DEFAULT 0 AFTER occurrence_count",
            'status'=>"ENUM('active','inactive') NOT NULL DEFAULT 'active' AFTER display_order",
        ];
        foreach($columns as $column=>$definition){
            if(!fstColumn($pdo,'fee_structure_items',$column)){
                $pdo->exec("ALTER TABLE fee_structure_items ADD COLUMN `$column` $definition");
            }
        }
        if(!fstIndex($pdo,'fee_structure_items','idx_fsi_fee_type')){
            $pdo->exec("ALTER TABLE fee_structure_items ADD KEY idx_fsi_fee_type(fee_type_id)");
        }
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
        $insert=$pdo->prepare("INSERT INTO fee_types(tenant_id,fee_type_name,fee_type_code,description,default_frequency,default_occurrence_count,is_mandatory,usage_scope,is_enabled,display_order) VALUES(:tenant_id,:name,:code,:description,:frequency,:occurrences,:mandatory,'structure',1,:display_order)");
        foreach($defaults as [$name,$code,$description,$frequency,$occurrences,$mandatory,$order]){
            $insert->execute(['tenant_id'=>$tenantId,'name'=>$name,'code'=>$code,'description'=>$description,'frequency'=>$frequency,'occurrences'=>$occurrences,'mandatory'=>$mandatory,'display_order'=>$order]);
        }
    }
}
function fstNormalizeCode(string $value):string{
    $value=strtoupper(trim($value));
    $value=preg_replace('/[^A-Z0-9]+/','_',$value)??'';
    return trim($value,'_');
}
function fstList(PDO $pdo,int $tenantId,array $filters):array{
    $where=['ft.tenant_id=:tenant_id','ft.deleted_at IS NULL'];
    $params=['tenant_id'=>$tenantId];
    $search=trim((string)($filters['search']??''));
    if($search!==''){
        $where[]="(ft.fee_type_name LIKE :search_name OR ft.fee_type_code LIKE :search_code OR ft.description LIKE :search_description)";
        $searchValue='%'.$search.'%';
        $params['search_name']=$searchValue;
        $params['search_code']=$searchValue;
        $params['search_description']=$searchValue;
    }
    $status=strtolower(trim((string)($filters['status']??'all')));
    if($status==='enabled')$where[]='ft.is_enabled=1';
    if($status==='disabled')$where[]='ft.is_enabled=0';
    $sql="SELECT ft.*,
        (SELECT COUNT(*) FROM fee_structure_items fsi WHERE fsi.fee_type_id=ft.id) AS structure_count
        FROM fee_types ft
        WHERE ".implode(' AND ',$where)."
        ORDER BY ft.display_order,ft.fee_type_name,ft.id";
    $s=$pdo->prepare($sql);
    $s->execute($params);
    return $s->fetchAll(PDO::FETCH_ASSOC);
}
function fstStats(PDO $pdo,int $tenantId):array{
    $s=$pdo->prepare("SELECT
        COUNT(*) total,
        SUM(is_enabled=1) enabled,
        SUM(is_enabled=0) disabled,
        SUM(is_mandatory=1) mandatory,
        SUM(is_enabled=1 AND usage_scope IN('structure','both')) structure_enabled,
        SUM(is_enabled=1 AND usage_scope IN('extra','both')) extra_enabled
        FROM fee_types WHERE tenant_id=:tenant_id AND deleted_at IS NULL");
    $s->execute(['tenant_id'=>$tenantId]);
    $row=$s->fetch(PDO::FETCH_ASSOC)?:[];
    return[
        'total'=>(int)($row['total']??0),
        'enabled'=>(int)($row['enabled']??0),
        'disabled'=>(int)($row['disabled']??0),
        'mandatory'=>(int)($row['mandatory']??0),
        'structure_enabled'=>(int)($row['structure_enabled']??0),
        'extra_enabled'=>(int)($row['extra_enabled']??0),
    ];
}

if(!isset($pdo)||!$pdo instanceof PDO)fstOut(false,'Database connection unavailable.',[],500);
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
if(empty($_SESSION['fee_csrf_token']))$_SESSION['fee_csrf_token']=bin2hex(random_bytes(32));
$scope=fstScope();
if($scope['tenant_id']<=0||$scope['user_id']<=0)fstOut(false,'School tenant or user session was not found.',[],401);
try{fstEnsure($pdo,$scope['tenant_id']);}catch(Throwable $e){fstOut(false,'Unable to initialize Fees Settings: '.$e->getMessage(),[],500);}
$input=fstInput();
$action=strtolower(trim((string)($input['action']??$_GET['action']??'')));

try{
    if($action==='list'){
        fstOut(true,'Fee types loaded.',[
            'records'=>fstList($pdo,$scope['tenant_id'],array_merge($_GET,$input)),
            'stats'=>fstStats($pdo,$scope['tenant_id']),
            'csrf_token'=>$_SESSION['fee_csrf_token'],
        ]);
    }

    if(in_array($action,['create','edit'],true)){
        fstCsrf($input);
        $id=(int)($input['id']??0);

        if($action==='edit'&&$id<=0)throw new InvalidArgumentException('Valid Fee Type ID is required.');

        if((int)($input['toggle_only']??0)===1){
            $enabled=(int)($input['is_enabled']??0)===1?1:0;
            $update=$pdo->prepare("UPDATE fee_types SET is_enabled=:enabled,updated_by=:user_id WHERE id=:id AND tenant_id=:tenant_id AND deleted_at IS NULL");
            $update->execute(['enabled'=>$enabled,'user_id'=>$scope['user_id'],'id'=>$id,'tenant_id'=>$scope['tenant_id']]);
            if($update->rowCount()===0)throw new InvalidArgumentException('Fee Type not found.');
            fstOut(true,$enabled?'Fee Type enabled successfully.':'Fee Type disabled successfully.');
        }

        $name=trim((string)($input['fee_type_name']??''));
        $code=fstNormalizeCode((string)($input['fee_type_code']??$name));
        $description=trim((string)($input['description']??''));
        $frequency=strtolower(trim((string)($input['default_frequency']??'one_time')));
        $occurrences=(int)($input['default_occurrence_count']??1);
        $mandatory=(int)($input['is_mandatory']??0)===1?1:0;
        $usageScope=strtolower(trim((string)($input['usage_scope']??'structure')));
        $enabled=(int)($input['is_enabled']??1)===1?1:0;
        $order=max(0,min(9999,(int)($input['display_order']??0)));

        if($name===''||mb_strlen($name)>120)throw new InvalidArgumentException('Fee Type Name is required and must not exceed 120 characters.');
        if($code===''||mb_strlen($code)>50)throw new InvalidArgumentException('Fee Type Code is required and must not exceed 50 characters.');
        if(mb_strlen($description)>500)throw new InvalidArgumentException('Description cannot exceed 500 characters.');
        if(!in_array($frequency,['one_time','monthly','term','quarterly','half_yearly','yearly','annual','custom'],true))throw new InvalidArgumentException('Invalid Fee Type frequency.');
        if(!in_array($usageScope,['structure','extra','both'],true))throw new InvalidArgumentException('Invalid Use In option.');
        if($occurrences<1||$occurrences>36)throw new InvalidArgumentException('Occurrences must be between 1 and 36.');

        $duplicate=$pdo->prepare("SELECT id FROM fee_types WHERE tenant_id=:tenant_id AND fee_type_code=:code AND deleted_at IS NULL AND id<>:id LIMIT 1");
        $duplicate->execute(['tenant_id'=>$scope['tenant_id'],'code'=>$code,'id'=>$id]);
        if($duplicate->fetchColumn())throw new InvalidArgumentException('Fee Type Code already exists.');

        if($id>0){
            $update=$pdo->prepare("UPDATE fee_types SET fee_type_name=:name,fee_type_code=:code,description=:description,default_frequency=:frequency,default_occurrence_count=:occurrences,is_mandatory=:mandatory,usage_scope=:usage_scope,is_enabled=:enabled,display_order=:display_order,updated_by=:user_id WHERE id=:id AND tenant_id=:tenant_id AND deleted_at IS NULL");
            $update->execute(['name'=>$name,'code'=>$code,'description'=>$description?:null,'frequency'=>$frequency,'occurrences'=>$occurrences,'mandatory'=>$mandatory,'usage_scope'=>$usageScope,'enabled'=>$enabled,'display_order'=>$order,'user_id'=>$scope['user_id'],'id'=>$id,'tenant_id'=>$scope['tenant_id']]);
            if($update->rowCount()===0){
                $check=$pdo->prepare("SELECT id FROM fee_types WHERE id=:id AND tenant_id=:tenant_id AND deleted_at IS NULL");
                $check->execute(['id'=>$id,'tenant_id'=>$scope['tenant_id']]);
                if(!$check->fetchColumn())throw new InvalidArgumentException('Fee Type not found.');
            }
            fstOut(true,'Fee Type updated successfully.',['id'=>$id]);
        }

        $insert=$pdo->prepare("INSERT INTO fee_types(tenant_id,fee_type_name,fee_type_code,description,default_frequency,default_occurrence_count,is_mandatory,usage_scope,is_enabled,display_order,created_by,updated_by) VALUES(:tenant_id,:name,:code,:description,:frequency,:occurrences,:mandatory,:usage_scope,:enabled,:display_order,:created_by,:updated_by)");
        $insert->execute(['tenant_id'=>$scope['tenant_id'],'name'=>$name,'code'=>$code,'description'=>$description?:null,'frequency'=>$frequency,'occurrences'=>$occurrences,'mandatory'=>$mandatory,'usage_scope'=>$usageScope,'enabled'=>$enabled,'display_order'=>$order,'created_by'=>$scope['user_id'],'updated_by'=>$scope['user_id']]);
        fstOut(true,'Fee Type created successfully.',['id'=>(int)$pdo->lastInsertId()]);
    }

    if($action==='delete'){
        fstCsrf($input);
        $id=(int)($input['id']??0);
        if($id<=0)throw new InvalidArgumentException('Valid Fee Type ID is required.');

        $used=$pdo->prepare("SELECT COUNT(*) FROM fee_structure_items WHERE fee_type_id=:id");
        $used->execute(['id'=>$id]);
        if((int)$used->fetchColumn()>0)throw new InvalidArgumentException('This Fee Type is used in Fee Structures. Disable it instead of deleting it.');

        $delete=$pdo->prepare("UPDATE fee_types SET deleted_at=NOW(),is_enabled=0,updated_by=:user_id WHERE id=:id AND tenant_id=:tenant_id AND deleted_at IS NULL");
        $delete->execute(['user_id'=>$scope['user_id'],'id'=>$id,'tenant_id'=>$scope['tenant_id']]);
        if($delete->rowCount()===0)throw new InvalidArgumentException('Fee Type not found.');
        fstOut(true,'Fee Type deleted successfully.');
    }

    fstOut(false,'Invalid Fees Settings action.',[],400);
}catch(InvalidArgumentException $e){
    fstOut(false,$e->getMessage(),[],422);
}catch(PDOException $e){
    error_log('fee-settings.php: '.$e->getMessage());
    fstOut(false,$e->getCode()==='23000'?'Duplicate Fee Type detected.':$e->getMessage(),[],422);
}catch(Throwable $e){
    error_log('fee-settings.php: '.$e->getMessage());
    fstOut(false,'Unable to complete Fees Settings request.',[],500);
}

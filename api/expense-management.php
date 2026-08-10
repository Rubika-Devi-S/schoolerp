<?php
declare(strict_types=1);

if (!defined('SCHOOL_API_PAGE_KEY')) {
    define('SCHOOL_API_PAGE_KEY', 'school_expenses');
}

if (!defined('SCHOOL_API_PERMISSION_KEYS')) {
    define('SCHOOL_API_PERMISSION_KEYS', ['school_expenses']);
}

ob_start();
ini_set('display_errors','0');
error_reporting(E_ALL);

require_once dirname(__DIR__).'/includes/bootstrap.php';

if(session_status()!==PHP_SESSION_ACTIVE){
    session_start();
}

const EXPENSE_BUILD='2026-08-07-expense-permission-api-link-v3';

function exOut(bool $ok,string $message='',array $data=[],int $status=200):never{
    while(ob_get_level()>0)ob_end_clean();
    if(!headers_sent()){
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }
    echo json_encode(
        ['success'=>$ok,'message'=>$message,'data'=>$data],
        JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES
    );
    exit;
}

function exInput():array{
    $type=strtolower((string)($_SERVER['CONTENT_TYPE']??''));
    if(str_contains($type,'application/json')){
        $data=json_decode((string)file_get_contents('php://input'),true);
        return is_array($data)?$data:[];
    }
    return $_POST;
}

function exScope():array{
    $user=function_exists('current_user')?current_user():[];
    $user=is_array($user)?$user:[];
    return [
        'tenant_id'=>(int)($user['tenant_id']??$user['school_id']??$_SESSION['tenant_id']??0),
        'branch_id'=>(int)($user['branch_id']??$_SESSION['branch_id']??0),
        'user_id'=>(int)($user['id']??$user['user_id']??$_SESSION['user_id']??0),
    ];
}

function exCsrf(array $input):void{
    $session=(string)($_SESSION['expense_csrf_token']??'');
    $request=(string)($input['csrf_token']??'');
    if($session===''||$request===''||!hash_equals($session,$request)){
        exOut(false,'Invalid or expired CSRF token. Refresh the page.',[],419);
    }
}

function exTable(PDO $pdo,string $table):bool{
    $q=$pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema=DATABASE() AND table_name=:table_name"
    );
    $q->execute(['table_name'=>$table]);
    return (int)$q->fetchColumn()>0;
}

function exColumn(PDO $pdo,string $table,string $column):bool{
    $q=$pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema=DATABASE()
           AND table_name=:table_name
           AND column_name=:column_name"
    );
    $q->execute(['table_name'=>$table,'column_name'=>$column]);
    return (int)$q->fetchColumn()>0;
}

function exIndex(PDO $pdo,string $table,string $index):bool{
    $q=$pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.statistics
         WHERE table_schema=DATABASE()
           AND table_name=:table_name
           AND index_name=:index_name"
    );
    $q->execute(['table_name'=>$table,'index_name'=>$index]);
    return (int)$q->fetchColumn()>0;
}

function exEnsureColumn(PDO $pdo,string $table,string $column,string $definition):void{
    if(!exColumn($pdo,$table,$column)){
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

function exEnsure(PDO $pdo,int $tenantId):void{
    if(!exTable($pdo,'expenses')){
        $pdo->exec(
            "CREATE TABLE expenses(
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id BIGINT UNSIGNED NOT NULL,
                branch_id BIGINT UNSIGNED NULL,
                expense_no VARCHAR(70) NOT NULL,
                expense_date DATE NOT NULL,
                purpose VARCHAR(150) NOT NULL,
                paid_to VARCHAR(150) NOT NULL,
                payment_method VARCHAR(50) NOT NULL,
                amount DECIMAL(14,2) NOT NULL,
                tax DECIMAL(14,2) NOT NULL DEFAULT 0,
                reference_no VARCHAR(100) NULL,
                description VARCHAR(500) NULL,
                status ENUM('paid','pending','cancelled') NOT NULL DEFAULT 'paid',
                created_by BIGINT UNSIGNED NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY(id),
                UNIQUE KEY uq_expense_no(tenant_id,expense_no),
                KEY idx_expense_filter(tenant_id,branch_id,expense_date,status)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    exEnsureColumn($pdo,'expenses','purpose_code',"VARCHAR(60) NULL AFTER purpose");
    exEnsureColumn($pdo,'expenses','purpose_option',"VARCHAR(120) NULL AFTER purpose_code");
    exEnsureColumn($pdo,'expenses','transaction_type',"ENUM('expense','refund','adjustment') NOT NULL DEFAULT 'expense' AFTER purpose_option");
    exEnsureColumn($pdo,'expenses','parent_expense_id',"BIGINT UNSIGNED NULL AFTER transaction_type");
    exEnsureColumn($pdo,'expenses','vehicle_id',"BIGINT UNSIGNED NULL AFTER parent_expense_id");
    exEnsureColumn($pdo,'expenses','vehicle_label',"VARCHAR(150) NULL AFTER vehicle_id");
    exEnsureColumn($pdo,'expenses','issue_type',"VARCHAR(120) NULL AFTER vehicle_label");
    exEnsureColumn($pdo,'expenses','vendor_name',"VARCHAR(150) NULL AFTER issue_type");
    exEnsureColumn($pdo,'expenses','bill_no',"VARCHAR(100) NULL AFTER vendor_name");
    exEnsureColumn($pdo,'expenses','odometer_reading',"VARCHAR(50) NULL AFTER bill_no");
    exEnsureColumn($pdo,'expenses','fuel_quantity',"DECIMAL(12,3) NULL AFTER odometer_reading");
    exEnsureColumn($pdo,'expenses','fuel_unit',"VARCHAR(20) NULL AFTER fuel_quantity");
    exEnsureColumn($pdo,'expenses','purpose_details',"LONGTEXT NULL AFTER fuel_unit");
    exEnsureColumn($pdo,'expenses','updated_by',"BIGINT UNSIGNED NULL AFTER created_by");

    if(!exIndex($pdo,'expenses','idx_expense_parent')){
        $pdo->exec("ALTER TABLE expenses ADD KEY idx_expense_parent(tenant_id,parent_expense_id)");
    }
    if(!exIndex($pdo,'expenses','idx_expense_purpose_code')){
        $pdo->exec("ALTER TABLE expenses ADD KEY idx_expense_purpose_code(tenant_id,purpose_code)");
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS expense_purposes(
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id BIGINT UNSIGNED NOT NULL,
            purpose_code VARCHAR(60) NOT NULL,
            purpose_name VARCHAR(150) NOT NULL,
            purpose_group VARCHAR(60) NOT NULL DEFAULT 'general',
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            display_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY(id),
            UNIQUE KEY uq_expense_purpose(tenant_id,purpose_code),
            UNIQUE KEY uq_expense_purpose_name(tenant_id,purpose_name)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS expense_purpose_options(
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id BIGINT UNSIGNED NOT NULL,
            purpose_code VARCHAR(60) NOT NULL,
            option_code VARCHAR(60) NOT NULL,
            option_name VARCHAR(150) NOT NULL,
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            display_order INT NOT NULL DEFAULT 0,
            PRIMARY KEY(id),
            UNIQUE KEY uq_expense_option(tenant_id,purpose_code,option_code),
            KEY idx_expense_option_lookup(tenant_id,purpose_code,status)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $purposes=[
        ['bus','Bus / Transport','bus',10],
        ['salary','Salary / Wages','staff',20],
        ['maintenance','Building Maintenance','maintenance',30],
        ['electricity','Electricity','utility',40],
        ['water','Water','utility',50],
        ['internet','Internet / Telephone','utility',60],
        ['stationery','Stationery / Printing','general',70],
        ['academic','Academic Materials','academic',80],
        ['event','Event / Function','event',90],
        ['medical','Medical / First Aid','general',100],
        ['rent','Rent / Lease','general',110],
        ['tax','Tax / Government Fee','general',120],
        ['other','Other Expense','general',999],
    ];

    $q=$pdo->prepare(
        "INSERT INTO expense_purposes(
            tenant_id,purpose_code,purpose_name,purpose_group,status,display_order
         ) VALUES(:tenant,:code,:name,:group_name,'active',:display_order)
         ON DUPLICATE KEY UPDATE
            purpose_name=VALUES(purpose_name),
            purpose_group=VALUES(purpose_group),
            status='active',
            display_order=VALUES(display_order)"
    );
    foreach($purposes as [$code,$name,$group,$order]){
        $q->execute([
            'tenant'=>$tenantId,'code'=>$code,'name'=>$name,
            'group_name'=>$group,'display_order'=>$order
        ]);
    }

    $options=[
        ['bus','fuel','Fuel / Diesel',10],
        ['bus','service','Periodic Service',20],
        ['bus','repair','Repair / Breakdown',30],
        ['bus','tyre','Tyre / Tube',40],
        ['bus','insurance','Insurance',50],
        ['bus','permit','Permit / Fitness / Tax',60],
        ['bus','driver','Driver / Cleaner Payment',70],
        ['bus','washing','Washing / Cleaning',80],
        ['bus','toll','Toll / Parking',90],
        ['bus','other','Other Bus Expense',100],
        ['salary','monthly_salary','Monthly Salary',10],
        ['salary','advance','Salary Advance',20],
        ['salary','overtime','Overtime',30],
        ['salary','temporary_wages','Temporary Wages',40],
        ['maintenance','electrical','Electrical Work',10],
        ['maintenance','plumbing','Plumbing Work',20],
        ['maintenance','civil','Civil Work',30],
        ['maintenance','painting','Painting',40],
        ['maintenance','cleaning','Cleaning',50],
        ['stationery','office','Office Stationery',10],
        ['stationery','printing','Printing / Xerox',20],
        ['academic','books','Books',10],
        ['academic','lab','Laboratory Materials',20],
        ['academic','sports','Sports Materials',30],
        ['event','function','School Function',10],
        ['event','competition','Competition',20],
        ['event','tour','Tour / Trip',30],
    ];
    $q=$pdo->prepare(
        "INSERT INTO expense_purpose_options(
            tenant_id,purpose_code,option_code,option_name,status,display_order
         ) VALUES(:tenant,:purpose,:code,:name,'active',:display_order)
         ON DUPLICATE KEY UPDATE
            option_name=VALUES(option_name),
            status='active',
            display_order=VALUES(display_order)"
    );
    foreach($options as [$purpose,$code,$name,$order]){
        $q->execute([
            'tenant'=>$tenantId,'purpose'=>$purpose,'code'=>$code,
            'name'=>$name,'display_order'=>$order
        ]);
    }
}

function exNumber(PDO $pdo,int $tenantId):string{
    for($i=0;$i<30;$i++){
        $number='EXP-'.date('Ymd').'-'.str_pad((string)random_int(1,999999),6,'0',STR_PAD_LEFT);
        $q=$pdo->prepare("SELECT COUNT(*) FROM expenses WHERE tenant_id=:tenant AND expense_no=:number");
        $q->execute(['tenant'=>$tenantId,'number'=>$number]);
        if((int)$q->fetchColumn()===0)return $number;
    }
    throw new RuntimeException('Unable to generate Expense Number.');
}

function exPurpose(PDO $pdo,int $tenantId,string $code,string $name=''):array{
    $code=strtolower(trim($code));
    if($code!==''){
        $q=$pdo->prepare(
            "SELECT purpose_code,purpose_name,purpose_group
             FROM expense_purposes
             WHERE tenant_id=:tenant AND purpose_code=:code AND status='active'
             LIMIT 1"
        );
        $q->execute(['tenant'=>$tenantId,'code'=>$code]);
        $row=$q->fetch(PDO::FETCH_ASSOC);
        if($row)return $row;
    }

    $name=trim($name);
    if($name!==''){
        $q=$pdo->prepare(
            "SELECT purpose_code,purpose_name,purpose_group
             FROM expense_purposes
             WHERE tenant_id=:tenant AND LOWER(purpose_name)=LOWER(:name)
             AND status='active' LIMIT 1"
        );
        $q->execute(['tenant'=>$tenantId,'name'=>$name]);
        $row=$q->fetch(PDO::FETCH_ASSOC);
        if($row)return $row;
    }

    throw new InvalidArgumentException('Select a valid Expense Purpose.');
}


function exPurposeDetails(mixed $value):array{
    if(is_string($value)){
        $decoded=json_decode($value,true);
        $value=is_array($decoded)?$decoded:[];
    }
    if(!is_array($value))return [];

    $clean=[];
    foreach($value as $key=>$item){
        $key=strtolower(trim((string)$key));
        if($key===''||!preg_match('/^[a-z0-9_]{1,60}$/',$key))continue;
        if(is_bool($item))$item=$item?'1':'0';
        if(!is_scalar($item)&&$item!==null)continue;
        $text=trim((string)($item??''));
        if(mb_strlen($text)>500)$text=mb_substr($text,0,500);
        $clean[$key]=$text;
        if(count($clean)>=50)break;
    }
    return $clean;
}

function exVehicleRows(PDO $pdo,int $tenantId,int $branchId):array{
    /*
     * Primary Vehicle Master used by the Transport module.
     * Shows active buses/vehicles for the current school. Vehicles assigned
     * to the current branch and school-level vehicles (branch_id NULL) are
     * available in the Expense form.
     */
    if(exTable($pdo,'school_vehicles')&&exColumn($pdo,'school_vehicles','id')){
        $name=exColumn($pdo,'school_vehicles','vehicle_name')?'vehicle_name':"''";
        $number=exColumn($pdo,'school_vehicles','vehicle_number')
            ?'vehicle_number'
            :(exColumn($pdo,'school_vehicles','registration_number')?'registration_number':"''");
        $registration=exColumn($pdo,'school_vehicles','registration_number')
            ?'registration_number'
            :"''";

        $where=[];
        $params=[];

        if(exColumn($pdo,'school_vehicles','tenant_id')){
            $where[]='tenant_id=:tenant';
            $params['tenant']=$tenantId;
        }
        if($branchId>0&&exColumn($pdo,'school_vehicles','branch_id')){
            $where[]='(branch_id=:branch OR branch_id IS NULL)';
            $params['branch']=$branchId;
        }
        if(exColumn($pdo,'school_vehicles','status')){
            $where[]="status='active'";
        }

        $sql="SELECT id,
                    TRIM(CONCAT_WS(' • ',
                        NULLIF(CAST($number AS CHAR),''),
                        NULLIF(CAST($name AS CHAR),''),
                        NULLIF(CAST($registration AS CHAR),'')
                    )) AS vehicle_label
              FROM school_vehicles";
        if($where)$sql.=' WHERE '.implode(' AND ',$where);
        $sql.=" ORDER BY $number,$name LIMIT 500";

        try{
            $q=$pdo->prepare($sql);
            $q->execute($params);
            $rows=$q->fetchAll(PDO::FETCH_ASSOC);
            if($rows)return $rows;
        }catch(Throwable $exception){
            error_log('Expense school vehicle metadata: '.$exception->getMessage());
        }
    }

    $candidates=[
        ['transport_vehicles',['vehicle_no','vehicle_number','registration_no','registration_number','bus_no','vehicle_name','name']],
        ['school_buses',['bus_no','vehicle_no','vehicle_number','registration_no','registration_number','name']],
        ['buses',['bus_no','vehicle_no','vehicle_number','registration_no','registration_number','name']],
        ['vehicles',['vehicle_no','vehicle_number','registration_no','registration_number','bus_no','vehicle_name','name']],
    ];

    foreach($candidates as [$table,$labels]){
        if(!exTable($pdo,$table)||!exColumn($pdo,$table,'id'))continue;

        $labelColumn='';
        foreach($labels as $candidate){
            if(exColumn($pdo,$table,$candidate)){
                $labelColumn=$candidate;
                break;
            }
        }
        if($labelColumn==='')continue;

        $where=[];
        $params=[];

        if(exColumn($pdo,$table,'tenant_id')){
            $where[]='tenant_id=:tenant';
            $params['tenant']=$tenantId;
            if($branchId>0&&exColumn($pdo,$table,'branch_id')){
                $where[]='(branch_id=:branch OR branch_id IS NULL)';
                $params['branch']=$branchId;
            }
        }elseif($branchId>0&&exColumn($pdo,$table,'branch_id')){
            $where[]='branch_id=:branch';
            $params['branch']=$branchId;
        }

        if(exColumn($pdo,$table,'status'))$where[]="status='active'";

        $sql="SELECT id,CAST(`$labelColumn` AS CHAR) vehicle_label FROM `$table`";
        if($where)$sql.=' WHERE '.implode(' AND ',$where);
        $sql.=" ORDER BY `$labelColumn` LIMIT 500";

        try{
            $q=$pdo->prepare($sql);
            $q->execute($params);
            $rows=$q->fetchAll(PDO::FETCH_ASSOC);
            if($rows)return $rows;
        }catch(Throwable $e){
            error_log('Vehicle metadata: '.$e->getMessage());
        }
    }
    return [];
}

function exMeta(PDO $pdo,array $scope):array{
    $q=$pdo->prepare(
        "SELECT purpose_code,purpose_name,purpose_group
         FROM expense_purposes
         WHERE tenant_id=:tenant AND status='active'
         ORDER BY display_order,purpose_name"
    );
    $q->execute(['tenant'=>$scope['tenant_id']]);
    $purposes=$q->fetchAll(PDO::FETCH_ASSOC);

    $q=$pdo->prepare(
        "SELECT purpose_code,option_code,option_name
         FROM expense_purpose_options
         WHERE tenant_id=:tenant AND status='active'
         ORDER BY purpose_code,display_order,option_name"
    );
    $q->execute(['tenant'=>$scope['tenant_id']]);
    $options=$q->fetchAll(PDO::FETCH_ASSOC);

    $q=$pdo->prepare(
        "SELECT id,expense_no,expense_date,purpose,paid_to,amount,tax,
                COALESCE((
                    SELECT SUM(r.amount+r.tax)
                    FROM expenses r
                    WHERE r.tenant_id=e.tenant_id
                      AND r.parent_expense_id=e.id
                      AND r.transaction_type IN('refund','adjustment')
                      AND r.status='paid'
                ),0) returned_amount
         FROM expenses e
         WHERE e.tenant_id=:tenant
           AND (:branch_scope=0 OR e.branch_id=:branch)
           AND e.transaction_type='expense'
           AND e.status<>'cancelled'
         ORDER BY e.id DESC LIMIT 500"
    );
    $q->execute([
        'tenant'=>$scope['tenant_id'],
        'branch_scope'=>$scope['branch_id'],
        'branch'=>$scope['branch_id'],
    ]);
    $parents=$q->fetchAll(PDO::FETCH_ASSOC);
    foreach($parents as &$row){
        $row['available_amount']=max(
            0,
            (float)$row['amount']+(float)$row['tax']-(float)$row['returned_amount']
        );
    }
    unset($row);

    return [
        'purposes'=>$purposes,
        'options'=>$options,
        'vehicles'=>exVehicleRows($pdo,$scope['tenant_id'],$scope['branch_id']),
        'parent_expenses'=>$parents,
        'payment_methods'=>['Cash','UPI','Bank Transfer','Cheque','Card','Other'],
        'csrf_token'=>$_SESSION['expense_csrf_token'],
        'api_build'=>EXPENSE_BUILD,
    ];
}

function exWhere(array $scope,array $filter,array &$params):array{
    $where=['e.tenant_id=:tenant','(:branch_scope=0 OR e.branch_id=:branch)'];
    $params=[
        'tenant'=>$scope['tenant_id'],
        'branch_scope'=>$scope['branch_id'],
        'branch'=>$scope['branch_id'],
    ];

    $search=trim((string)($filter['search']??''));
    if($search!==''){
        $where[]="(
            e.expense_no LIKE :search_expense OR
            e.paid_to LIKE :search_paid_to OR
            e.reference_no LIKE :search_reference OR
            e.purpose LIKE :search_purpose OR
            e.vehicle_label LIKE :search_vehicle OR
            e.issue_type LIKE :search_issue
        )";
        $value='%'.$search.'%';
        foreach(['search_expense','search_paid_to','search_reference','search_purpose','search_vehicle','search_issue'] as $key){
            $params[$key]=$value;
        }
    }

    $purpose=(string)($filter['purpose']??'all');
    if($purpose!==''&&$purpose!=='all'){
        $where[]='e.purpose_code=:purpose';
        $params['purpose']=$purpose;
    }

    $status=(string)($filter['status']??'all');
    if($status!==''&&$status!=='all'){
        $where[]='e.status=:status';
        $params['status']=$status;
    }

    $type=(string)($filter['transaction_type']??'all');
    if($type!==''&&$type!=='all'){
        $where[]='e.transaction_type=:transaction_type';
        $params['transaction_type']=$type;
    }

    $method=(string)($filter['payment_method']??'all');
    if($method!==''&&$method!=='all'){
        $where[]='e.payment_method=:payment_method';
        $params['payment_method']=$method;
    }

    if(!empty($filter['from_date'])){
        $where[]='e.expense_date>=:from_date';
        $params['from_date']=$filter['from_date'];
    }
    if(!empty($filter['to_date'])){
        $where[]='e.expense_date<=:to_date';
        $params['to_date']=$filter['to_date'];
    }

    return $where;
}

function exRows(PDO $pdo,array $scope,array $filter,int $page,int $perPage):array{
    $params=[];
    $where=exWhere($scope,$filter,$params);
    $whereSql=implode(' AND ',$where);

    $q=$pdo->prepare("SELECT COUNT(*) FROM expenses e WHERE $whereSql");
    $q->execute($params);
    $total=(int)$q->fetchColumn();

    $last=max(1,(int)ceil($total/$perPage));
    $page=min(max(1,$page),$last);
    $offset=($page-1)*$perPage;

    $sql="SELECT
            e.*,
            CASE
                WHEN e.transaction_type IN('refund','adjustment')
                    THEN -(e.amount+COALESCE(e.tax,0))
                ELSE e.amount+COALESCE(e.tax,0)
            END net_amount,
            parent.expense_no parent_expense_no,
            u.name created_by_name,
            b.branch_name
          FROM expenses e
          LEFT JOIN expenses parent
            ON parent.id=e.parent_expense_id
           AND parent.tenant_id=e.tenant_id
          LEFT JOIN users u ON u.id=e.created_by
          LEFT JOIN branches b ON b.id=e.branch_id
          WHERE $whereSql
          ORDER BY e.expense_date DESC,e.id DESC
          LIMIT :offset,:per_page";
    $q=$pdo->prepare($sql);
    foreach($params as $key=>$value)$q->bindValue(':'.$key,$value);
    $q->bindValue(':offset',$offset,PDO::PARAM_INT);
    $q->bindValue(':per_page',$perPage,PDO::PARAM_INT);
    $q->execute();
    $records=$q->fetchAll(PDO::FETCH_ASSOC);

    $statsSql="SELECT
        COUNT(*) total_entries,
        COALESCE(SUM(
            CASE
                WHEN e.status='paid' AND e.transaction_type='expense'
                    THEN e.amount+COALESCE(e.tax,0)
                WHEN e.status='paid' AND e.transaction_type IN('refund','adjustment')
                    THEN -(e.amount+COALESCE(e.tax,0))
                ELSE 0
            END
        ),0) total_expense,
        COALESCE(SUM(
            CASE
                WHEN e.status='paid' AND e.expense_date=CURDATE()
                     AND e.transaction_type='expense'
                    THEN e.amount+COALESCE(e.tax,0)
                WHEN e.status='paid' AND e.expense_date=CURDATE()
                     AND e.transaction_type IN('refund','adjustment')
                    THEN -(e.amount+COALESCE(e.tax,0))
                ELSE 0
            END
        ),0) today_expense,
        COALESCE(SUM(
            CASE WHEN e.status='pending' THEN e.amount+COALESCE(e.tax,0) ELSE 0 END
        ),0) pending_expense,
        COALESCE(SUM(
            CASE WHEN e.status='paid' AND e.transaction_type IN('refund','adjustment')
                THEN e.amount+COALESCE(e.tax,0) ELSE 0 END
        ),0) returned_amount
        FROM expenses e WHERE $whereSql";
    $q=$pdo->prepare($statsSql);
    $q->execute($params);
    $stats=$q->fetch(PDO::FETCH_ASSOC)?:[];

    $q=$pdo->prepare(
        "SELECT
            COALESCE(NULLIF(e.purpose,''),'Unknown') purpose,
            COUNT(*) entries,
            COALESCE(SUM(CASE WHEN e.status='paid' AND e.transaction_type='expense'
                THEN e.amount+COALESCE(e.tax,0) ELSE 0 END),0) paid_amount,
            COALESCE(SUM(CASE WHEN e.status='pending'
                THEN e.amount+COALESCE(e.tax,0) ELSE 0 END),0) pending_amount,
            COALESCE(SUM(CASE WHEN e.status='cancelled'
                THEN e.amount+COALESCE(e.tax,0) ELSE 0 END),0) cancelled_amount,
            COALESCE(SUM(CASE WHEN e.status='paid' AND e.transaction_type IN('refund','adjustment')
                THEN e.amount+COALESCE(e.tax,0) ELSE 0 END),0) returned_amount,
            COALESCE(SUM(CASE
                WHEN e.status='paid' AND e.transaction_type='expense'
                    THEN e.amount+COALESCE(e.tax,0)
                WHEN e.status='paid' AND e.transaction_type IN('refund','adjustment')
                    THEN -(e.amount+COALESCE(e.tax,0))
                ELSE 0 END),0) net_amount
         FROM expenses e
         WHERE e.tenant_id=:tenant
           AND (:branch_scope=0 OR e.branch_id=:branch)
         GROUP BY e.purpose
         ORDER BY net_amount DESC"
    );
    $q->execute([
        'tenant'=>$scope['tenant_id'],
        'branch_scope'=>$scope['branch_id'],
        'branch'=>$scope['branch_id'],
    ]);
    $summary=$q->fetchAll(PDO::FETCH_ASSOC);

    $history=array_map(
        static fn(array $r):array=>[
            'created_at'=>$r['created_at'],
            'action_name'=>match($r['transaction_type']){
                'refund'=>'Refund / Return Recorded',
                'adjustment'=>'Adjustment Recorded',
                default=>'Expense Recorded',
            },
            'expense_no'=>$r['expense_no'],
            'user_name'=>$r['created_by_name']??'-',
            'details_text'=>$r['purpose'].' • '.$r['paid_to'].' • '.
                (($r['transaction_type']==='expense')?'Paid ':'Returned ').
                number_format((float)$r['amount']+(float)$r['tax'],2),
        ],
        array_slice($records,0,50)
    );

    return [
        'records'=>$records,
        'stats'=>$stats,
        'summary'=>$summary,
        'history'=>$history,
        'pagination'=>[
            'total'=>$total,'page'=>$page,'per_page'=>$perPage,'last_page'=>$last
        ],
    ];
}

if(!isset($pdo)||!$pdo instanceof PDO){
    exOut(false,'Database connection missing.',[],500);
}

$scope=exScope();
if($scope['tenant_id']<=0||$scope['user_id']<=0){
    exOut(false,'Tenant or user session is missing.',[],401);
}

if(empty($_SESSION['expense_csrf_token'])||!is_string($_SESSION['expense_csrf_token'])){
    $_SESSION['expense_csrf_token']=bin2hex(random_bytes(32));
}

$input=exInput();
$action=strtolower(trim((string)($input['action']??$_GET['action']??'')));

try{
    exEnsure($pdo,$scope['tenant_id']);

    if($action==='meta'){
        exOut(true,'Expense metadata loaded.',exMeta($pdo,$scope));
    }

    if($action==='list'){
        $page=max(1,(int)($_GET['page']??$input['page']??1));
        $perPage=min(100,max(5,(int)($_GET['per_page']??$input['per_page']??10)));
        $data=exRows($pdo,$scope,array_merge($_GET,$input),$page,$perPage);
        $data['meta']=exMeta($pdo,$scope);
        $data['permissions']=['create'=>true,'edit'=>true,'delete'=>true,'view'=>true];
        exOut(true,'Expenses loaded.',$data);
    }

    if($action==='save'){
        exCsrf($input);

        $id=(int)($input['id']??0);
        $transactionType=strtolower(trim((string)($input['transaction_type']??'expense')));
        if(!in_array($transactionType,['expense','refund','adjustment'],true)){
            throw new InvalidArgumentException('Transaction Type is invalid.');
        }

        $purpose=exPurpose(
            $pdo,
            $scope['tenant_id'],
            (string)($input['purpose_code']??''),
            (string)($input['purpose']??'')
        );

        $date=trim((string)($input['expense_date']??''));
        $dateObject=DateTimeImmutable::createFromFormat('Y-m-d',$date);
        if(!$dateObject||$dateObject->format('Y-m-d')!==$date){
            throw new InvalidArgumentException('Expense Date is invalid.');
        }

        $amount=round((float)($input['amount']??0),2);
        $tax=round((float)($input['tax']??0),2);
        if($amount<=0)throw new InvalidArgumentException('Amount must be greater than zero.');
        if($tax<0)throw new InvalidArgumentException('Tax cannot be negative.');

        $paidTo=trim((string)($input['paid_to']??''));
        $paymentMethod=trim((string)($input['payment_method']??''));
        if($paidTo==='')throw new InvalidArgumentException('Paid To / Received From is required.');
        if($paymentMethod==='')throw new InvalidArgumentException('Payment Method is required.');

        $purposeDetails=exPurposeDetails($input['purpose_details']??[]);
        $vehicleId=(int)($input['vehicle_id']??($purposeDetails['vehicle_id']??0));
        $fuelQuantity=round((float)($input['fuel_quantity']??($purposeDetails['fuel_quantity']??0)),3);
        $fuelUnit=trim((string)($input['fuel_unit']??($purposeDetails['fuel_unit']??'')));
        if($fuelQuantity<0)throw new InvalidArgumentException('Fuel quantity cannot be negative.');

        $purposeSearch=strtolower(
            (string)$purpose['purpose_code'].' '.
            (string)$purpose['purpose_name'].' '.
            (string)$purpose['purpose_group'].' '.
            (string)($input['purpose_option']??'')
        );
        $needsVehicle=$transactionType==='expense'
            && preg_match('/bus|transport|vehicle|fuel|diesel|petrol/',$purposeSearch);

        if($needsVehicle&&$vehicleId<=0){
            throw new InvalidArgumentException('Select the Bus / Vehicle for this Expense Purpose.');
        }

        $vehicleLabel=trim((string)($input['vehicle_label']??($purposeDetails['vehicle_label']??'')));
        if($vehicleId>0){
            $availableVehicles=exVehicleRows($pdo,$scope['tenant_id'],$scope['branch_id']);
            $vehicleRecord=null;
            foreach($availableVehicles as $vehicle){
                if((int)$vehicle['id']===$vehicleId){
                    $vehicleRecord=$vehicle;
                    break;
                }
            }
            if(!$vehicleRecord){
                throw new InvalidArgumentException('The selected Bus / Vehicle is not available.');
            }
            $vehicleLabel=trim((string)($vehicleRecord['vehicle_label']??$vehicleLabel));
            $purposeDetails['vehicle_id']=(string)$vehicleId;
            $purposeDetails['vehicle_label']=$vehicleLabel;
        }

        if($fuelQuantity>0)$purposeDetails['fuel_quantity']=(string)$fuelQuantity;
        if($fuelUnit!=='')$purposeDetails['fuel_unit']=$fuelUnit;
        $purposeDetailsJson=$purposeDetails
            ?json_encode($purposeDetails,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
            :null;

        $parentId=(int)($input['parent_expense_id']??0);
        if($transactionType!=='expense'){
            if($parentId<=0){
                throw new InvalidArgumentException('Select the original Expense for refund or adjustment.');
            }

            $q=$pdo->prepare(
                "SELECT e.id,e.amount,e.tax,
                    COALESCE((
                        SELECT SUM(r.amount+r.tax)
                        FROM expenses r
                        WHERE r.tenant_id=e.tenant_id
                          AND r.parent_expense_id=e.id
                          AND r.transaction_type IN('refund','adjustment')
                          AND r.status='paid'
                          AND (:current_id=0 OR r.id<>:current_id_again)
                    ),0) returned_amount
                 FROM expenses e
                 WHERE e.id=:id
                   AND e.tenant_id=:tenant
                   AND e.transaction_type='expense'
                 LIMIT 1"
            );
            $q->execute([
                'current_id'=>$id,
                'current_id_again'=>$id,
                'id'=>$parentId,
                'tenant'=>$scope['tenant_id'],
            ]);
            $parent=$q->fetch(PDO::FETCH_ASSOC);
            if(!$parent)throw new InvalidArgumentException('Original Expense was not found.');

            $available=(float)$parent['amount']+(float)$parent['tax']-(float)$parent['returned_amount'];
            if(($amount+$tax)>$available+0.009){
                throw new InvalidArgumentException(
                    'Refund or adjustment cannot exceed the available amount of ₹'.
                    number_format(max(0,$available),2).'.'
                );
            }
        }else{
            $parentId=0;
        }

        $status=strtolower(trim((string)($input['status']??'paid')));
        if(!in_array($status,['paid','pending','cancelled'],true)){
            throw new InvalidArgumentException('Status is invalid.');
        }

        $params=[
            'expense_date'=>$date,
            'purpose'=>$purpose['purpose_name'],
            'purpose_code'=>$purpose['purpose_code'],
            'purpose_option'=>trim((string)($input['purpose_option']??''))?:null,
            'transaction_type'=>$transactionType,
            'parent_expense_id'=>$parentId?:null,
            'vehicle_id'=>$vehicleId?:null,
            'vehicle_label'=>$vehicleLabel?:null,
            'issue_type'=>trim((string)($input['issue_type']??''))?:null,
            'vendor_name'=>trim((string)($input['vendor_name']??''))?:null,
            'bill_no'=>trim((string)($input['bill_no']??''))?:null,
            'odometer_reading'=>trim((string)($input['odometer_reading']??($purposeDetails['odometer_reading']??'')))?:null,
            'fuel_quantity'=>$fuelQuantity>0?$fuelQuantity:null,
            'fuel_unit'=>$fuelUnit!==''?$fuelUnit:null,
            'purpose_details'=>$purposeDetailsJson,
            'paid_to'=>$paidTo,
            'payment_method'=>$paymentMethod,
            'amount'=>$amount,
            'tax'=>$tax,
            'reference_no'=>trim((string)($input['reference_no']??''))?:null,
            'description'=>trim((string)($input['description']??''))?:null,
            'status'=>$status,
            'updated_by'=>$scope['user_id'],
            'tenant'=>$scope['tenant_id'],
        ];

        if($id>0){
            $params['id']=$id;
            $q=$pdo->prepare(
                "UPDATE expenses SET
                    expense_date=:expense_date,
                    purpose=:purpose,
                    purpose_code=:purpose_code,
                    purpose_option=:purpose_option,
                    transaction_type=:transaction_type,
                    parent_expense_id=:parent_expense_id,
                    vehicle_id=:vehicle_id,
                    vehicle_label=:vehicle_label,
                    issue_type=:issue_type,
                    vendor_name=:vendor_name,
                    bill_no=:bill_no,
                    odometer_reading=:odometer_reading,
                    fuel_quantity=:fuel_quantity,
                    fuel_unit=:fuel_unit,
                    purpose_details=:purpose_details,
                    paid_to=:paid_to,
                    payment_method=:payment_method,
                    amount=:amount,
                    tax=:tax,
                    reference_no=:reference_no,
                    description=:description,
                    status=:status,
                    updated_by=:updated_by
                 WHERE id=:id AND tenant_id=:tenant"
            );
            $q->execute($params);
            exOut(true,'Expense transaction updated successfully.');
        }

        $params['branch']=$scope['branch_id']?:null;
        $params['expense_no']=exNumber($pdo,$scope['tenant_id']);
        $params['created_by']=$scope['user_id'];

        $q=$pdo->prepare(
            "INSERT INTO expenses(
                tenant_id,branch_id,expense_no,expense_date,
                purpose,purpose_code,purpose_option,transaction_type,
                parent_expense_id,vehicle_id,vehicle_label,issue_type,
                vendor_name,bill_no,odometer_reading,
                fuel_quantity,fuel_unit,purpose_details,
                paid_to,payment_method,amount,tax,reference_no,
                description,status,created_by,updated_by
             ) VALUES(
                :tenant,:branch,:expense_no,:expense_date,
                :purpose,:purpose_code,:purpose_option,:transaction_type,
                :parent_expense_id,:vehicle_id,:vehicle_label,:issue_type,
                :vendor_name,:bill_no,:odometer_reading,
                :fuel_quantity,:fuel_unit,:purpose_details,
                :paid_to,:payment_method,:amount,:tax,:reference_no,
                :description,:status,:created_by,:updated_by
             )"
        );
        $q->execute($params);

        exOut(
            true,
            $transactionType==='expense'
                ?'Expense created successfully.'
                :'Refund / adjustment recorded successfully.',
            ['expense_no'=>$params['expense_no']]
        );
    }

    if($action==='detail'){
        $id=(int)($_GET['id']??$input['id']??0);
        $q=$pdo->prepare(
            "SELECT e.*,parent.expense_no parent_expense_no,
                    u.name created_by_name,b.branch_name
             FROM expenses e
             LEFT JOIN expenses parent
                ON parent.id=e.parent_expense_id
               AND parent.tenant_id=e.tenant_id
             LEFT JOIN users u ON u.id=e.created_by
             LEFT JOIN branches b ON b.id=e.branch_id
             WHERE e.id=:id AND e.tenant_id=:tenant
             LIMIT 1"
        );
        $q->execute(['id'=>$id,'tenant'=>$scope['tenant_id']]);
        $row=$q->fetch(PDO::FETCH_ASSOC);
        if(!$row)throw new InvalidArgumentException('Expense record not found.');
        exOut(true,'Expense detail loaded.',['record'=>$row]);
    }

    if($action==='delete'){
        exCsrf($input);
        $id=(int)($input['id']??0);

        $q=$pdo->prepare(
            "SELECT COUNT(*) FROM expenses
             WHERE tenant_id=:tenant AND parent_expense_id=:id"
        );
        $q->execute(['tenant'=>$scope['tenant_id'],'id'=>$id]);
        if((int)$q->fetchColumn()>0){
            throw new InvalidArgumentException(
                'This Expense has refund or adjustment entries and cannot be deleted.'
            );
        }

        $q=$pdo->prepare("DELETE FROM expenses WHERE id=:id AND tenant_id=:tenant");
        $q->execute(['id'=>$id,'tenant'=>$scope['tenant_id']]);
        exOut(true,'Expense transaction deleted successfully.');
    }

    if($action==='export'||$action==='print'){
        $data=exRows($pdo,$scope,array_merge($_GET,$input),1,1000);
        $records=$data['records'];

        if($action==='export'){
            while(ob_get_level()>0)ob_end_clean();
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="expenses-'.date('Ymd-His').'.csv"');
            $output=fopen('php://output','wb');
            fwrite($output,"\xEF\xBB\xBF");
            fputcsv($output,[
                'Expense No','Date','Transaction Type','Purpose','Sub-option',
                'Bus / Vehicle','Issue Type','Paid To / Received From',
                'Amount','Tax','Net Amount','Payment Method',
                'Reference','Status','Description'
            ]);
            foreach($records as $row){
                fputcsv($output,[
                    $row['expense_no'],$row['expense_date'],$row['transaction_type'],
                    $row['purpose'],$row['purpose_option'],$row['vehicle_label'],
                    $row['issue_type'],$row['paid_to'],$row['amount'],$row['tax'],
                    $row['net_amount'],$row['payment_method'],$row['reference_no'],
                    $row['status'],$row['description']
                ]);
            }
            fclose($output);
            exit;
        }

        while(ob_get_level()>0)ob_end_clean();
        header('Content-Type: text/html; charset=utf-8');
        $total=array_sum(array_map(static fn($r)=>(float)$r['net_amount'],$records));
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Expense Report</title>';
        echo '<style>body{font-family:Arial;padding:24px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ddd;padding:7px;font-size:12px}th{background:#f3f4f6}.right{text-align:right}@media print{button{display:none}}</style></head><body>';
        echo '<button onclick="window.print()">Print</button><h2>Expense Report</h2><table><thead><tr><th>No.</th><th>Date</th><th>Type</th><th>Purpose</th><th>Details</th><th>Party</th><th class="right">Net Amount</th><th>Status</th></tr></thead><tbody>';
        foreach($records as $row){
            echo '<tr><td>'.htmlspecialchars((string)$row['expense_no']).'</td>';
            echo '<td>'.htmlspecialchars((string)$row['expense_date']).'</td>';
            echo '<td>'.htmlspecialchars(ucfirst((string)$row['transaction_type'])).'</td>';
            echo '<td>'.htmlspecialchars((string)$row['purpose']).'</td>';
            echo '<td>'.htmlspecialchars(trim((string)$row['purpose_option'].' '.(string)$row['vehicle_label'].' '.(string)$row['issue_type'])).'</td>';
            echo '<td>'.htmlspecialchars((string)$row['paid_to']).'</td>';
            echo '<td class="right">₹'.number_format((float)$row['net_amount'],2).'</td>';
            echo '<td>'.htmlspecialchars(ucfirst((string)$row['status'])).'</td></tr>';
        }
        echo '</tbody><tfoot><tr><th colspan="6" class="right">Net Expense</th><th class="right">₹'.number_format($total,2).'</th><th></th></tr></tfoot></table></body></html>';
        exit;
    }

    exOut(false,'Invalid Expense action.',[],400);
}catch(InvalidArgumentException $e){
    if($pdo->inTransaction())$pdo->rollBack();
    exOut(false,$e->getMessage(),[],422);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    error_log(
        'expense-management.php build='.EXPENSE_BUILD.
        ' action='.$action.' line='.$e->getLine().' error='.$e->getMessage()
    );
    $host=strtolower((string)($_SERVER['HTTP_HOST']??''));
    exOut(
        false,
        (str_contains($host,'localhost')||str_contains($host,'127.0.0.1'))
            ?'Expense request failed ['.EXPENSE_BUILD.']: '.$e->getMessage()
            :'Unable to complete Expense request.',
        [],
        500
    );
}

<?php
declare(strict_types=1);
ob_start();ini_set('display_errors','0');error_reporting(E_ALL);
require_once dirname(__DIR__).'/includes/bootstrap.php';
const FEE_COLLECTION_BUILD='2026-08-12-old-balance-fixed-transport-v10.1-hy093';

function fcOut(bool $success,string $message='',array $data=[],int $status=200):never{while(ob_get_level()>0)ob_end_clean();if(!headers_sent()){http_response_code($status);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');}echo json_encode(['success'=>$success,'message'=>$message,'data'=>$data],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
function fcInput():array{$type=strtolower((string)($_SERVER['CONTENT_TYPE']??''));if(str_contains($type,'application/json')){$data=json_decode((string)file_get_contents('php://input'),true);return is_array($data)?$data:[];}return $_POST;}
function fcScope():array{$u=function_exists('current_user')?current_user():[];$u=is_array($u)?$u:[];return['tenant_id'=>(int)($u['tenant_id']??$u['school_id']??$_SESSION['tenant_id']??$_SESSION['school_id']??0),'branch_id'=>(int)($u['branch_id']??$_SESSION['branch_id']??0),'user_id'=>(int)($u['id']??$u['user_id']??$_SESSION['user_id']??0)];}
function fcCsrf(array $input):void{$session=(string)($_SESSION['fee_csrf_token']??'');$request=(string)($input['csrf_token']??'');if($session===''||$request===''||!hash_equals($session,$request))fcOut(false,'Invalid or expired CSRF token. Refresh the page.',[],419);}
function fcTable(PDO $pdo,string $table):bool{$s=$pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:table_name");$s->execute(['table_name'=>$table]);return (int)$s->fetchColumn()>0;}
function fcColumn(PDO $pdo,string $table,string $column):bool{$s=$pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=:table_name AND column_name=:column_name");$s->execute(['table_name'=>$table,'column_name'=>$column]);return (int)$s->fetchColumn()>0;}
function fcIndex(PDO $pdo,string $table,string $index):bool{$s=$pdo->prepare("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=:table_name AND index_name=:index_name");$s->execute(['table_name'=>$table,'index_name'=>$index]);return (int)$s->fetchColumn()>0;}
function fcEnsureColumn(PDO $pdo,string $table,string $column,string $definition):void{if(!fcColumn($pdo,$table,$column)){$pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");}}
function fcColumnDefinition(PDO $pdo,string $table,string $column):array{
    $s=$pdo->prepare(
        "SELECT COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA
         FROM information_schema.columns
         WHERE table_schema=DATABASE()
           AND table_name=:table_name
           AND column_name=:column_name
         LIMIT 1"
    );
    $s->execute(['table_name'=>$table,'column_name'=>$column]);
    $row=$s->fetch(PDO::FETCH_ASSOC);
    return is_array($row)?$row:[];
}
function fcEnsureLegacyFeeSchema(PDO $pdo,int $tenantId):void{
    /*
     * student_fee_items legacy compatibility.
     *
     * Older installations contain fee_head_id as NOT NULL without a default.
     * The current schedule is linked through fee_structure_item_id and some
     * rows, such as transport/additional/previous due, do not have one fee head.
     * Therefore fee_head_id must be nullable.
     */
    if(fcTable($pdo,'student_fee_items')&&fcColumn($pdo,'student_fee_items','fee_head_id')){
        $definition=fcColumnDefinition($pdo,'student_fee_items','fee_head_id');

        /*
         * Backfill fee_head_id for structure-linked rows whenever the old
         * fee_structure_items table contains fee_head_id.
         */
        if(fcColumn($pdo,'fee_structure_items','fee_head_id')
            &&fcColumn($pdo,'student_fee_items','fee_structure_item_id')){
            $pdo->exec(
                "UPDATE student_fee_items sfi
                 INNER JOIN fee_structure_items fsi
                    ON fsi.id=sfi.fee_structure_item_id
                 SET sfi.fee_head_id=fsi.fee_head_id
                 WHERE sfi.fee_head_id IS NULL
                   AND fsi.fee_head_id IS NOT NULL"
            );
        }

        if(strtoupper((string)($definition['IS_NULLABLE']??'NO'))!=='YES'){
            $columnType=trim((string)($definition['COLUMN_TYPE']??'bigint unsigned'));
            if($columnType==='')$columnType='bigint unsigned';

            /*
             * Preserve the existing numeric type while changing only NULL
             * compatibility. Existing foreign keys remain valid.
             */
            $pdo->exec(
                "ALTER TABLE student_fee_items
                 MODIFY COLUMN fee_head_id ".$columnType." NULL DEFAULT NULL"
            );
        }
    }

    /* payment_methods */
    fcEnsureColumn($pdo,'payment_methods','tenant_id','BIGINT UNSIGNED NULL AFTER id');
    fcEnsureColumn($pdo,'payment_methods','method_name',"VARCHAR(100) NOT NULL DEFAULT ''");
    fcEnsureColumn($pdo,'payment_methods','method_key',"VARCHAR(40) NULL AFTER method_name");
    fcEnsureColumn($pdo,'payment_methods','status',"ENUM('active','inactive') NOT NULL DEFAULT 'active'");
    $pdo->prepare("UPDATE payment_methods SET tenant_id=:tenant_id WHERE tenant_id IS NULL OR tenant_id=0")->execute(['tenant_id'=>$tenantId]);
    $pdo->prepare("UPDATE payment_methods SET method_key=LOWER(REPLACE(TRIM(method_name),' ','_')) WHERE method_key IS NULL OR method_key='' ")->execute();
    if(!fcIndex($pdo,'payment_methods','idx_payment_methods_tenant')){$pdo->exec("ALTER TABLE payment_methods ADD KEY idx_payment_methods_tenant(tenant_id,status)");}

    /* fee_receipts */
    fcEnsureColumn($pdo,'fee_receipts','tenant_id','BIGINT UNSIGNED NULL AFTER id');
    fcEnsureColumn($pdo,'fee_receipts','branch_id','BIGINT UNSIGNED NULL AFTER tenant_id');
    fcEnsureColumn($pdo,'fee_receipts','academic_year_id','BIGINT UNSIGNED NULL');
    fcEnsureColumn($pdo,'fee_receipts','fine_amount','DECIMAL(12,2) NOT NULL DEFAULT 0');
    fcEnsureColumn($pdo,'fee_receipts','discount_amount','DECIMAL(12,2) NOT NULL DEFAULT 0');
    fcEnsureColumn($pdo,'fee_receipts','gross_amount','DECIMAL(12,2) NOT NULL DEFAULT 0');
    fcEnsureColumn($pdo,'fee_receipts','paid_amount','DECIMAL(12,2) NOT NULL DEFAULT 0');
    fcEnsureColumn($pdo,'fee_receipts','payment_status',"ENUM('due','partial','paid','reversed') NOT NULL DEFAULT 'due'");
    fcEnsureColumn($pdo,'fee_receipts','collected_by','BIGINT UNSIGNED NULL');
    fcEnsureColumn($pdo,'fee_receipts','notes','VARCHAR(255) NULL');
    $pdo->prepare("UPDATE fee_receipts SET tenant_id=:tenant_id WHERE tenant_id IS NULL OR tenant_id=0")->execute(['tenant_id'=>$tenantId]);
    if(!fcIndex($pdo,'fee_receipts','idx_fee_receipts_tenant')){$pdo->exec("ALTER TABLE fee_receipts ADD KEY idx_fee_receipts_tenant(tenant_id,academic_year_id,student_id)");}

    /* fee_payments */
    fcEnsureColumn($pdo,'fee_payments','tenant_id','BIGINT UNSIGNED NULL AFTER id');
    fcEnsureColumn($pdo,'fee_payments','payment_method_id','BIGINT UNSIGNED NULL');
    fcEnsureColumn($pdo,'fee_payments','reference_no','VARCHAR(100) NULL');
    fcEnsureColumn($pdo,'fee_payments','paid_at','DATETIME NULL');
    fcEnsureColumn($pdo,'fee_payments','status',"ENUM('success','failed','reversed') NOT NULL DEFAULT 'success'");
    $pdo->prepare("UPDATE fee_payments SET tenant_id=:tenant_id WHERE tenant_id IS NULL OR tenant_id=0")->execute(['tenant_id'=>$tenantId]);
    if(!fcIndex($pdo,'fee_payments','idx_fee_payments_tenant')){$pdo->exec("ALTER TABLE fee_payments ADD KEY idx_fee_payments_tenant(tenant_id,receipt_id)");}

    /* fee_receipt_items */
    if(fcTable($pdo,'fee_receipt_items')){
        fcEnsureColumn($pdo,'fee_receipt_items','tenant_id','BIGINT UNSIGNED NULL AFTER id');
        fcEnsureColumn($pdo,'fee_receipt_items','receipt_id','BIGINT UNSIGNED NULL AFTER tenant_id');
        fcEnsureColumn($pdo,'fee_receipt_items','student_fee_item_id','BIGINT UNSIGNED NULL AFTER receipt_id');
        fcEnsureColumn($pdo,'fee_receipt_items','item_type',"VARCHAR(40) NOT NULL DEFAULT 'additional' AFTER student_fee_item_id");
        fcEnsureColumn($pdo,'fee_receipt_items','item_name',"VARCHAR(180) NOT NULL DEFAULT 'Fee' AFTER item_type");
        fcEnsureColumn($pdo,'fee_receipt_items','period_label','VARCHAR(120) NULL AFTER item_name');
        fcEnsureColumn($pdo,'fee_receipt_items','gross_amount','DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER period_label');
        fcEnsureColumn($pdo,'fee_receipt_items','discount_amount','DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER gross_amount');
        fcEnsureColumn($pdo,'fee_receipt_items','paid_amount','DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER discount_amount');
        fcEnsureColumn($pdo,'fee_receipt_items','balance_after','DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER paid_amount');

        /*
         * Do not remove the legacy amount column. When it exists, the collect
         * insert supplies it explicitly. New tables continue to use paid_amount.
         */
        fcEnsureColumn($pdo,'fee_receipt_items','created_at','TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER balance_after');

        $pdo->prepare(
            "UPDATE fee_receipt_items
             SET tenant_id=:tenant_id
             WHERE tenant_id IS NULL OR tenant_id=0"
        )->execute(['tenant_id'=>$tenantId]);

        if(!fcIndex($pdo,'fee_receipt_items','idx_receipt_item_receipt')){
            $pdo->exec(
                "ALTER TABLE fee_receipt_items
                 ADD KEY idx_receipt_item_receipt(tenant_id,receipt_id)"
            );
        }

        if(!fcIndex($pdo,'fee_receipt_items','idx_receipt_item_fee_item')){
            $pdo->exec(
                "ALTER TABLE fee_receipt_items
                 ADD KEY idx_receipt_item_fee_item(student_fee_item_id)"
            );
        }
    }

    /* Previous Academic Year carry-forward compatibility. */
    if(fcTable($pdo,'student_fee_items')){
        fcEnsureColumn($pdo,'student_fee_items','source_academic_year_id','BIGINT UNSIGNED NULL AFTER academic_year_id');
        fcEnsureColumn($pdo,'student_fee_items','source_student_fee_item_id','BIGINT UNSIGNED NULL AFTER source_academic_year_id');
        fcEnsureColumn($pdo,'student_fee_items','carried_forward_at','DATETIME NULL AFTER source_student_fee_item_id');

        $typeDefinition=fcColumnDefinition($pdo,'student_fee_items','item_type');
        $itemType=strtolower((string)($typeDefinition['COLUMN_TYPE']??''));
        if($itemType!==''&&!str_contains($itemType,"'previous_due'")){
            $pdo->exec(
                "ALTER TABLE student_fee_items
                 MODIFY COLUMN item_type
                 ENUM('admission','tuition','term','transport','additional','previous_due')
                 NOT NULL"
            );
        }

        $statusDefinition=fcColumnDefinition($pdo,'student_fee_items','item_status');
        $itemStatus=strtolower((string)($statusDefinition['COLUMN_TYPE']??''));
        if($itemStatus!==''&&!str_contains($itemStatus,"'carried_forward'")){
            $pdo->exec(
                "ALTER TABLE student_fee_items
                 MODIFY COLUMN item_status
                 ENUM('unpaid','partial','paid','waived','cancelled','carried_forward')
                 NOT NULL DEFAULT 'unpaid'"
            );
        }
    }

    /* fee_additional_charge_types */
    if(fcTable($pdo,'fee_additional_charge_types')){
        fcEnsureColumn($pdo,'fee_additional_charge_types','tenant_id','BIGINT UNSIGNED NULL AFTER id');
        fcEnsureColumn($pdo,'fee_additional_charge_types','charge_code',"VARCHAR(40) NOT NULL DEFAULT ''");
        fcEnsureColumn($pdo,'fee_additional_charge_types','charge_name',"VARCHAR(120) NOT NULL DEFAULT ''");
        fcEnsureColumn($pdo,'fee_additional_charge_types','status',"ENUM('active','inactive') NOT NULL DEFAULT 'active'");
        fcEnsureColumn($pdo,'fee_additional_charge_types','display_order','INT NOT NULL DEFAULT 0');
        $pdo->prepare("UPDATE fee_additional_charge_types SET tenant_id=:tenant_id WHERE tenant_id IS NULL OR tenant_id=0")->execute(['tenant_id'=>$tenantId]);
    }
}
function fcEnsure(PDO $pdo,int $tenantId):void{
    foreach(['academic_years','classes','sections','students','student_enrollments','student_fee_assignments','fee_structures','fee_structure_items','fee_heads','fee_receipts','fee_payments','payment_methods'] as $table){if(!fcTable($pdo,$table))throw new RuntimeException('Missing required database table: '.$table.'.',500);}
    fcEnsureLegacyFeeSchema($pdo,$tenantId);
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_fee_items(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,tenant_id BIGINT UNSIGNED NOT NULL,assignment_id BIGINT UNSIGNED NOT NULL,student_id BIGINT UNSIGNED NOT NULL,academic_year_id BIGINT UNSIGNED NOT NULL,fee_structure_item_id BIGINT UNSIGNED NULL,transport_route_id BIGINT UNSIGNED NULL,source_receipt_id BIGINT UNSIGNED NULL,item_type ENUM('admission','tuition','term','transport','additional','previous_due') NOT NULL,item_name VARCHAR(180) NOT NULL,period_key VARCHAR(80) NOT NULL,period_label VARCHAR(120) NOT NULL,due_date DATE NOT NULL,original_amount DECIMAL(12,2) NOT NULL DEFAULT 0,discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,balance_amount DECIMAL(12,2) NOT NULL DEFAULT 0,item_status ENUM('unpaid','partial','paid','waived','cancelled') NOT NULL DEFAULT 'unpaid',created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY uk_student_fee_period(assignment_id,item_type,period_key),KEY idx_student_fee_due(tenant_id,student_id,due_date,item_status),KEY idx_student_fee_assignment(assignment_id,item_status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    /*
     * CREATE TABLE IF NOT EXISTS does not repair an existing legacy table.
     * Run compatibility repair again after table creation.
     */
    fcEnsureLegacyFeeSchema($pdo,$tenantId);

    $pdo->exec("CREATE TABLE IF NOT EXISTS fee_additional_charge_types(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,tenant_id BIGINT UNSIGNED NOT NULL,charge_code VARCHAR(40) NOT NULL,charge_name VARCHAR(120) NOT NULL,status ENUM('active','inactive') NOT NULL DEFAULT 'active',display_order INT NOT NULL DEFAULT 0,created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY uk_fee_charge_type(tenant_id,charge_code)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS fee_receipt_items(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,tenant_id BIGINT UNSIGNED NOT NULL,receipt_id BIGINT UNSIGNED NOT NULL,student_fee_item_id BIGINT UNSIGNED NULL,item_type VARCHAR(40) NOT NULL,item_name VARCHAR(180) NOT NULL,period_label VARCHAR(120) NULL,gross_amount DECIMAL(12,2) NOT NULL DEFAULT 0,discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,balance_after DECIMAL(12,2) NOT NULL DEFAULT 0,created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),KEY idx_receipt_item_receipt(tenant_id,receipt_id),KEY idx_receipt_item_fee_item(student_fee_item_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    /*
     * Run the repair again after CREATE TABLE IF NOT EXISTS.
     * CREATE TABLE does not add missing columns to an already existing table.
     */
    fcEnsureLegacyFeeSchema($pdo,$tenantId);

    $methods=[['Cash','cash'],['UPI','upi'],['Card','card'],['Bank','bank']];$m=$pdo->prepare("INSERT INTO payment_methods(tenant_id,method_name,method_key,status) VALUES(:tenant_id,:name,:code,'active') ON DUPLICATE KEY UPDATE method_name=:updated_name,status='active'");foreach($methods as [$name,$code])$m->execute(['tenant_id'=>$tenantId,'name'=>$name,'code'=>$code,'updated_name'=>$name]);
    $charges=[['BREAKAGE','Breakage Fee',10],['FINE','Fine',20],['BOOK','Book Fee',30],['ID_CARD','ID Card Fee',40],['UNIFORM','Uniform Fee',50],['EXAM','Exam Fee',60],['OTHER','Other Charges',70]];$c=$pdo->prepare("INSERT INTO fee_additional_charge_types(tenant_id,charge_code,charge_name,status,display_order) VALUES(:tenant_id,:code,:name,'active',:display_order) ON DUPLICATE KEY UPDATE charge_name=:updated_name,status='active',display_order=:updated_order");foreach($charges as [$code,$name,$order])$c->execute(['tenant_id'=>$tenantId,'code'=>$code,'name'=>$name,'display_order'=>$order,'updated_name'=>$name,'updated_order'=>$order]);
}

function fcFallbackFeeHeadId(PDO $pdo,int $tenantId):int{
    if(!fcTable($pdo,'fee_heads')){
        return 0;
    }

    $where=['tenant_id=:tenant_id'];
    if(fcColumn($pdo,'fee_heads','deleted_at')){
        $where[]='deleted_at IS NULL';
    }
    if(fcColumn($pdo,'fee_heads','status')){
        $where[]="status='active'";
    }

    $sql='SELECT id FROM fee_heads WHERE '.implode(' AND ',$where).' ORDER BY id LIMIT 1';
    $s=$pdo->prepare($sql);
    $s->execute(['tenant_id'=>$tenantId]);
    return (int)($s->fetchColumn()?:0);
}

function fcResolveFeeHeadId(
    PDO $pdo,
    int $tenantId,
    ?int $feeStructureItemId=null,
    ?int $preferredFeeHeadId=null
):int{
    if(($preferredFeeHeadId??0)>0){
        return (int)$preferredFeeHeadId;
    }

    if(($feeStructureItemId??0)>0&&fcTable($pdo,'fee_structure_items')){
        if(fcColumn($pdo,'fee_structure_items','fee_head_id')){
            $s=$pdo->prepare(
                "SELECT fee_head_id
                 FROM fee_structure_items
                 WHERE id=:id
                   AND tenant_id=:tenant_id
                 LIMIT 1"
            );
            $s->execute([
                'id'=>$feeStructureItemId,
                'tenant_id'=>$tenantId,
            ]);
            $id=(int)($s->fetchColumn()?:0);
            if($id>0){
                return $id;
            }
        }

        /*
         * Some schemas use fee_type_id in fee_structure_items and store the
         * matching legacy fee-head relation in fee_types.fee_head_id.
         */
        if(fcColumn($pdo,'fee_structure_items','fee_type_id')
            &&fcTable($pdo,'fee_types')
            &&fcColumn($pdo,'fee_types','fee_head_id')){
            $s=$pdo->prepare(
                "SELECT ft.fee_head_id
                 FROM fee_structure_items fsi
                 INNER JOIN fee_types ft
                    ON ft.id=fsi.fee_type_id
                   AND ft.tenant_id=fsi.tenant_id
                 WHERE fsi.id=:id
                   AND fsi.tenant_id=:tenant_id
                 LIMIT 1"
            );
            $s->execute([
                'id'=>$feeStructureItemId,
                'tenant_id'=>$tenantId,
            ]);
            $id=(int)($s->fetchColumn()?:0);
            if($id>0){
                return $id;
            }
        }
    }

    return fcFallbackFeeHeadId($pdo,$tenantId);
}

function fcRequireFeeHeadId(PDO $pdo,int $tenantId,?int $structureItemId=null):int{
    $id=fcResolveFeeHeadId($pdo,$tenantId,$structureItemId);

    if($id<=0){
        throw new RuntimeException(
            'No Fee Head is available. Create at least one active Fee Head before collecting fees.'
        );
    }

    return $id;
}


function fcEnsureIncomeTables(PDO $pdo):void{
    $pdo->exec("CREATE TABLE IF NOT EXISTS income_purposes(
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      tenant_id BIGINT UNSIGNED NOT NULL,
      purpose_code VARCHAR(60) NOT NULL,
      purpose_name VARCHAR(120) NOT NULL,
      is_system TINYINT(1) NOT NULL DEFAULT 1,
      status ENUM('active','inactive') NOT NULL DEFAULT 'active',
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY(id),
      UNIQUE KEY uq_income_purpose_code(tenant_id,purpose_code),
      UNIQUE KEY uq_income_purpose_name(tenant_id,purpose_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS income_transactions(
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      tenant_id BIGINT UNSIGNED NOT NULL,
      branch_id BIGINT UNSIGNED NULL,
      income_no VARCHAR(70) NOT NULL,
      purpose_id BIGINT UNSIGNED NOT NULL,
      income_date DATE NOT NULL,
      amount DECIMAL(14,2) NOT NULL,
      payment_mode_id BIGINT UNSIGNED NULL,
      payer_name VARCHAR(150) NOT NULL,
      reference_no VARCHAR(120) NULL,
      income_status ENUM('received','pending','cancelled') NOT NULL DEFAULT 'received',
      student_id BIGINT UNSIGNED NULL,
      academic_year_id BIGINT UNSIGNED NULL,
      receipt_id BIGINT UNSIGNED NULL,
      description VARCHAR(500) NULL,
      source_module VARCHAR(80) NULL,
      source_record_id BIGINT UNSIGNED NULL,
      created_by BIGINT UNSIGNED NULL,
      updated_by BIGINT UNSIGNED NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY(id),
      UNIQUE KEY uq_income_no(tenant_id,income_no),
      KEY idx_income_filter(tenant_id,branch_id,income_date,income_status),
      KEY idx_income_source(tenant_id,source_module,source_record_id),
      KEY idx_income_receipt(tenant_id,receipt_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function fcIncomeNo(PDO $pdo,int $tenantId):string{
    for($i=0;$i<30;$i++){
        $no='INC-'.date('Ymd').'-'.str_pad((string)random_int(1,999999),6,'0',STR_PAD_LEFT);
        $s=$pdo->prepare("SELECT COUNT(*) FROM income_transactions WHERE tenant_id=:tenant_id AND income_no=:income_no");
        $s->execute(['tenant_id'=>$tenantId,'income_no'=>$no]);
        if((int)$s->fetchColumn()===0)return $no;
    }
    throw new RuntimeException('Unable to generate Income Number.');
}

function fcIncomePurpose(PDO $pdo,int $tenantId,string $code,string $name):int{
    $s=$pdo->prepare(
        "INSERT INTO income_purposes(
            tenant_id,purpose_code,purpose_name,is_system,status
         ) VALUES(
            :tenant_id,:code,:name,1,'active'
         )
         ON DUPLICATE KEY UPDATE
            purpose_name=VALUES(purpose_name),
            status='active'"
    );
    $s->execute(['tenant_id'=>$tenantId,'code'=>$code,'name'=>$name]);

    $s=$pdo->prepare(
        "SELECT id FROM income_purposes
         WHERE tenant_id=:tenant_id AND purpose_code=:code
         LIMIT 1"
    );
    $s->execute(['tenant_id'=>$tenantId,'code'=>$code]);
    return (int)$s->fetchColumn();
}

function fcReceiptIncomeCategory(PDO $pdo,int $tenantId,int $receiptId):array{
    $s=$pdo->prepare(
        "SELECT item_type,COALESCE(SUM(paid_amount),0) amount
         FROM fee_receipt_items
         WHERE tenant_id=:tenant_id AND receipt_id=:receipt_id
         GROUP BY item_type
         ORDER BY amount DESC"
    );
    $s->execute(['tenant_id'=>$tenantId,'receipt_id'=>$receiptId]);
    $types=$s->fetchAll(PDO::FETCH_ASSOC);

    if(count($types)===1){
        return match(strtolower((string)$types[0]['item_type'])){
            'admission'=>['admission_fee','Admission Fee Income'],
            'transport'=>['transport_fee','Transport Fee Income'],
            'term'=>['examination_fee','Examination Fee Income'],
            'additional'=>['other','Other Income'],
            default=>['student_fee','Student Fee Income'],
        };
    }
    return ['student_fee','Student Fee Income'];
}

function fcSyncReceiptIncome(PDO $pdo,array $scope,int $receiptId,bool $cancel=false):void{
    /* Income tables are initialized before any fee transaction starts.
       Do not execute CREATE TABLE here: MySQL DDL implicitly commits the
       active fee transaction and causes PDO::commit() to throw
       'There is no active transaction' after the receipt was already saved. */
    if(!fcTable($pdo,'income_purposes')||!fcTable($pdo,'income_transactions')){
        throw new RuntimeException('Income integration tables are unavailable. Refresh the page and try again.');
    }

    $s=$pdo->prepare(
        "SELECT r.*,TRIM(CONCAT(
             COALESCE(st.first_name,''),
             CASE WHEN COALESCE(st.last_name,'')='' THEN '' ELSE CONCAT(' ',st.last_name) END
         )) student_name,
         fp.payment_method_id,
         fp.reference_no payment_reference
         FROM fee_receipts r
         INNER JOIN students st
            ON st.id=r.student_id
           AND st.tenant_id=r.tenant_id
         LEFT JOIN fee_payments fp
            ON fp.receipt_id=r.id
           AND fp.tenant_id=r.tenant_id
           AND fp.status='success'
         WHERE r.id=:receipt_id
           AND r.tenant_id=:tenant_id
         ORDER BY fp.id
         LIMIT 1"
    );
    $s->execute([
        'receipt_id'=>$receiptId,
        'tenant_id'=>$scope['tenant_id'],
    ]);
    $receipt=$s->fetch(PDO::FETCH_ASSOC);
    if(!$receipt)return;

    [$purposeCode,$purposeName]=fcReceiptIncomeCategory(
        $pdo,
        $scope['tenant_id'],
        $receiptId
    );
    $purposeId=fcIncomePurpose(
        $pdo,
        $scope['tenant_id'],
        $purposeCode,
        $purposeName
    );

    $existing=$pdo->prepare(
        "SELECT id,income_no
         FROM income_transactions
         WHERE tenant_id=:tenant_id
           AND (
                (source_module='fee_collection' AND source_record_id=:source_id)
                OR receipt_id=:receipt_id
           )
         ORDER BY id
         LIMIT 1
         FOR UPDATE"
    );
    $existing->execute([
        'tenant_id'=>$scope['tenant_id'],
        'source_id'=>$receiptId,
        'receipt_id'=>$receiptId,
    ]);
    $income=$existing->fetch(PDO::FETCH_ASSOC);

    $status=$cancel||$receipt['payment_status']==='reversed'?'cancelled':'received';
    $description='Automatically recorded from Fee Collection receipt '.$receipt['receipt_no'].'.';

    if($income){
        $q=$pdo->prepare(
            "UPDATE income_transactions SET
                branch_id=:branch_id,
                purpose_id=:purpose_id,
                income_date=:income_date,
                amount=:amount,
                payment_mode_id=:payment_mode_id,
                payer_name=:payer_name,
                reference_no=:reference_no,
                income_status=:income_status,
                student_id=:student_id,
                academic_year_id=:academic_year_id,
                receipt_id=:receipt_id,
                description=:description,
                source_module='fee_collection',
                source_record_id=:source_record_id,
                updated_by=:updated_by
             WHERE id=:id AND tenant_id=:tenant_id"
        );
        $q->execute([
            'branch_id'=>(int)$receipt['branch_id']?:null,
            'purpose_id'=>$purposeId,
            'income_date'=>substr((string)$receipt['receipt_date'],0,10),
            'amount'=>(float)$receipt['paid_amount'],
            'payment_mode_id'=>(int)$receipt['payment_method_id']?:null,
            'payer_name'=>(string)$receipt['student_name'],
            'reference_no'=>(string)($receipt['payment_reference']?:$receipt['receipt_no']),
            'income_status'=>$status,
            'student_id'=>(int)$receipt['student_id'],
            'academic_year_id'=>(int)$receipt['academic_year_id'],
            'receipt_id'=>$receiptId,
            'description'=>$description,
            'source_record_id'=>$receiptId,
            'updated_by'=>$scope['user_id']?:null,
            'id'=>(int)$income['id'],
            'tenant_id'=>$scope['tenant_id'],
        ]);
        return;
    }

    $q=$pdo->prepare(
        "INSERT INTO income_transactions(
            tenant_id,branch_id,income_no,purpose_id,income_date,amount,
            payment_mode_id,payer_name,reference_no,income_status,
            student_id,academic_year_id,receipt_id,description,
            source_module,source_record_id,created_by,updated_by
         ) VALUES(
            :tenant_id,:branch_id,:income_no,:purpose_id,:income_date,:amount,
            :payment_mode_id,:payer_name,:reference_no,:income_status,
            :student_id,:academic_year_id,:receipt_id,:description,
            'fee_collection',:source_record_id,:created_by,:updated_by
         )"
    );
    $q->execute([
        'tenant_id'=>$scope['tenant_id'],
        'branch_id'=>(int)$receipt['branch_id']?:null,
        'income_no'=>fcIncomeNo($pdo,$scope['tenant_id']),
        'purpose_id'=>$purposeId,
        'income_date'=>substr((string)$receipt['receipt_date'],0,10),
        'amount'=>(float)$receipt['paid_amount'],
        'payment_mode_id'=>(int)$receipt['payment_method_id']?:null,
        'payer_name'=>(string)$receipt['student_name'],
        'reference_no'=>(string)($receipt['payment_reference']?:$receipt['receipt_no']),
        'income_status'=>$status,
        'student_id'=>(int)$receipt['student_id'],
        'academic_year_id'=>(int)$receipt['academic_year_id'],
        'receipt_id'=>$receiptId,
        'description'=>$description,
        'source_record_id'=>$receiptId,
        'created_by'=>$scope['user_id']?:null,
        'updated_by'=>$scope['user_id']?:null,
    ]);
}

function fcName(string $a='s'):string{return "TRIM(CONCAT(COALESCE($a.first_name,''),CASE WHEN COALESCE($a.last_name,'')='' THEN '' ELSE CONCAT(' ',$a.last_name) END))";}
function fcValidDate(string $value,string $label):string{$date=DateTimeImmutable::createFromFormat('Y-m-d',$value);$errors=DateTimeImmutable::getLastErrors();$bad=is_array($errors)&&(($errors['warning_count']??0)>0||($errors['error_count']??0)>0);if(!$date||$bad||$date->format('Y-m-d')!==$value)throw new InvalidArgumentException($label.' is invalid.');return $value;}
function fcReceiptNo(PDO $pdo,int $tenantId):string{for($i=0;$i<20;$i++){$number='FEE-'.date('YmdHis').'-'.str_pad((string)random_int(1,9999),4,'0',STR_PAD_LEFT);$s=$pdo->prepare("SELECT COUNT(*) FROM fee_receipts WHERE tenant_id=:tenant_id AND receipt_no=:receipt_no");$s->execute(['tenant_id'=>$tenantId,'receipt_no'=>$number]);if((int)$s->fetchColumn()===0)return $number;}throw new RuntimeException('Unable to generate Receipt Number.');}
function fcClean(string $notes):string{return trim($notes);}
function fcMethod(PDO $pdo,int $tenantId,int $methodId):array{$s=$pdo->prepare("SELECT id,method_name,method_key FROM payment_methods WHERE id=:id AND tenant_id=:tenant_id AND status='active' AND method_key IN('cash','upi','card','bank') LIMIT 1");$s->execute(['id'=>$methodId,'tenant_id'=>$tenantId]);$row=$s->fetch(PDO::FETCH_ASSOC);if(!$row)throw new InvalidArgumentException('Selected Payment Mode is invalid or inactive.');return $row;}
function fcMeta(PDO $pdo,array $scope):array{
    $y=$pdo->prepare("SELECT id,year_name,start_date,end_date,is_current,status FROM academic_years WHERE tenant_id=:tenant_id ORDER BY is_current DESC,start_date DESC,id DESC");
    $y->execute(['tenant_id'=>$scope['tenant_id']]);

    $c=$pdo->prepare("SELECT id,academic_year_id,class_name,display_order FROM classes WHERE tenant_id=:tenant_id AND status='active' ORDER BY academic_year_id DESC,display_order,class_name");
    $c->execute(['tenant_id'=>$scope['tenant_id']]);

    $s=$pdo->prepare("SELECT sec.id,sec.class_id,sec.section_name,c.academic_year_id FROM sections sec INNER JOIN classes c ON c.id=sec.class_id AND c.tenant_id=sec.tenant_id WHERE sec.tenant_id=:tenant_id AND sec.status='active' ORDER BY c.academic_year_id DESC,c.display_order,sec.section_name");
    $s->execute(['tenant_id'=>$scope['tenant_id']]);

    $m=$pdo->prepare("SELECT id,method_name,method_key FROM payment_methods WHERE tenant_id=:tenant_id AND status='active' AND method_key IN('cash','upi','card','bank') ORDER BY FIELD(method_key,'cash','upi','card','bank')");
    $m->execute(['tenant_id'=>$scope['tenant_id']]);

    /*
     * Charge Type suggestions now come from Fees Settings first.
     * Legacy fee_additional_charge_types remain available for backward
     * compatibility. Duplicate names are shown only once.
     */
    $chargeOptions=[];
    $seen=[];

    if(fcTable($pdo,'fee_types')
        &&fcColumn($pdo,'fee_types','fee_type_name')){
        $conditions=['tenant_id=:tenant_id'];
        if(fcColumn($pdo,'fee_types','is_enabled'))$conditions[]='is_enabled=1';
        if(fcColumn($pdo,'fee_types','deleted_at'))$conditions[]='deleted_at IS NULL';

        $codeExpr=fcColumn($pdo,'fee_types','fee_type_code')
            ?"COALESCE(fee_type_code,'')"
            :"''";
        $orderExpr=fcColumn($pdo,'fee_types','display_order')
            ?'display_order,fee_type_name,id'
            :'fee_type_name,id';

        $feeTypes=$pdo->prepare(
            "SELECT id,$codeExpr charge_code,fee_type_name charge_name
             FROM fee_types
             WHERE ".implode(' AND ',$conditions)."
             ORDER BY $orderExpr"
        );
        $feeTypes->execute(['tenant_id'=>$scope['tenant_id']]);

        foreach($feeTypes->fetchAll(PDO::FETCH_ASSOC) as $row){
            $name=trim((string)($row['charge_name']??''));
            if($name==='')continue;
            $key=mb_strtolower($name);
            if(isset($seen[$key]))continue;
            $seen[$key]=true;
            $chargeOptions[]=[
                'id'=>(int)$row['id'],
                'charge_key'=>'fee:'.(int)$row['id'],
                'charge_code'=>(string)($row['charge_code']??''),
                'charge_name'=>$name,
                'source'=>'fee_type',
                'source_label'=>'Fee Settings',
            ];
        }
    }

    if(fcTable($pdo,'fee_additional_charge_types')){
        $legacy=$pdo->prepare(
            "SELECT id,charge_code,charge_name
             FROM fee_additional_charge_types
             WHERE tenant_id=:tenant_id
               AND status='active'
             ORDER BY display_order,charge_name"
        );
        $legacy->execute(['tenant_id'=>$scope['tenant_id']]);

        foreach($legacy->fetchAll(PDO::FETCH_ASSOC) as $row){
            $name=trim((string)($row['charge_name']??''));
            if($name==='')continue;
            $key=mb_strtolower($name);
            if(isset($seen[$key]))continue;
            $seen[$key]=true;
            $chargeOptions[]=[
                'id'=>(int)$row['id'],
                'charge_key'=>'legacy:'.(int)$row['id'],
                'charge_code'=>(string)($row['charge_code']??''),
                'charge_name'=>$name,
                'source'=>'legacy',
                'source_label'=>'Additional Charge Settings',
            ];
        }
    }

    return[
        'years'=>$y->fetchAll(PDO::FETCH_ASSOC),
        'classes'=>$c->fetchAll(PDO::FETCH_ASSOC),
        'sections'=>$s->fetchAll(PDO::FETCH_ASSOC),
        'methods'=>$m->fetchAll(PDO::FETCH_ASSOC),
        'charge_types'=>$chargeOptions,
        'csrf_token'=>$_SESSION['fee_csrf_token']
    ];
}
/**
 * Repair a missing previous-year carry-forward row for a promoted student.
 * Promotion normally creates item_type=previous_due. This recovery keeps old
 * installations/data consistent when that target row is missing.
 */
function fcRepairPreviousYearCarryForward(PDO $pdo,array $scope,int $studentId,int $targetYearId,int $targetAssignmentId):float{
    if($studentId<=0||$targetYearId<=0||$targetAssignmentId<=0||!fcTable($pdo,'student_fee_items'))return 0.0;

    $existing=$pdo->prepare(
        "SELECT COUNT(*) item_count,COALESCE(SUM(balance_amount),0) pending
         FROM student_fee_items
         WHERE tenant_id=:tenant_id
           AND assignment_id=:assignment_id
           AND (item_type='previous_due' OR source_academic_year_id IS NOT NULL)
           AND item_status<>'cancelled'"
    );
    $existing->execute(['tenant_id'=>$scope['tenant_id'],'assignment_id'=>$targetAssignmentId]);
    $existingRow=$existing->fetch(PDO::FETCH_ASSOC)?:[];
    if((int)($existingRow['item_count']??0)>0)return round((float)($existingRow['pending']??0),2);

    $targetYear=$pdo->prepare(
        "SELECT start_date FROM academic_years
         WHERE id=:year_id AND tenant_id=:tenant_id LIMIT 1"
    );
    $targetYear->execute(['year_id'=>$targetYearId,'tenant_id'=>$scope['tenant_id']]);
    $targetStart=trim((string)$targetYear->fetchColumn());
    if($targetStart==='')return 0.0;

    $sourceAssignment=$pdo->prepare(
        "SELECT a.id,a.academic_year_id,ay.year_name
         FROM student_fee_assignments a
         INNER JOIN academic_years ay
            ON ay.id=a.academic_year_id AND ay.tenant_id=a.tenant_id
         WHERE a.tenant_id=:tenant_id
           AND a.student_id=:student_id
           AND ay.start_date<:target_start
         ORDER BY ay.start_date DESC,a.id DESC
         LIMIT 1"
    );
    $sourceAssignment->execute([
        'tenant_id'=>$scope['tenant_id'],
        'student_id'=>$studentId,
        'target_start'=>$targetStart,
    ]);
    $source=$sourceAssignment->fetch(PDO::FETCH_ASSOC);
    if(!is_array($source))return 0.0;

    $sourceId=(int)$source['id'];
    $sourceYearId=(int)$source['academic_year_id'];
    $sourceYearName=trim((string)($source['year_name']??''));

    $sourceItems=$pdo->prepare(
        "SELECT id,item_name,period_label,original_amount,discount_amount,paid_amount,balance_amount,item_status
         FROM student_fee_items
         WHERE tenant_id=:tenant_id
           AND assignment_id=:assignment_id
           AND item_status IN('unpaid','partial','carried_forward')
         ORDER BY due_date,id"
    );
    $sourceItems->execute(['tenant_id'=>$scope['tenant_id'],'assignment_id'=>$sourceId]);
    $rows=$sourceItems->fetchAll(PDO::FETCH_ASSOC);
    if(!$rows)return 0.0;

    $dueStmt=$pdo->prepare(
        "SELECT COALESCE(NULLIF(assignment_date,'0000-00-00'),CURDATE())
         FROM student_fee_assignments
         WHERE id=:id AND tenant_id=:tenant_id LIMIT 1"
    );
    $dueStmt->execute(['id'=>$targetAssignmentId,'tenant_id'=>$scope['tenant_id']]);
    $dueDate=trim((string)$dueStmt->fetchColumn());
    if($dueDate==='')$dueDate=date('Y-m-d');

    $insert=$pdo->prepare(
        "INSERT INTO student_fee_items(
            tenant_id,assignment_id,student_id,academic_year_id,
            source_academic_year_id,source_student_fee_item_id,
            fee_structure_item_id,transport_route_id,
            item_type,item_name,period_key,period_label,due_date,
            original_amount,discount_amount,paid_amount,balance_amount,item_status
         ) VALUES(
            :tenant_id,:assignment_id,:student_id,:academic_year_id,
            :source_year_id,:source_item_id,
            NULL,NULL,
            'previous_due',:item_name,:period_key,:period_label,:due_date,
            :original_amount,0,0,:balance_amount,'unpaid'
         )
         ON DUPLICATE KEY UPDATE
            item_name=VALUES(item_name),
            period_label=VALUES(period_label),
            source_academic_year_id=VALUES(source_academic_year_id),
            source_student_fee_item_id=VALUES(source_student_fee_item_id),
            original_amount=CASE WHEN paid_amount<=0.009 THEN VALUES(original_amount) ELSE original_amount END,
            balance_amount=CASE WHEN paid_amount<=0.009 THEN VALUES(balance_amount) ELSE balance_amount END"
    );

    $markSource=$pdo->prepare(
        "UPDATE student_fee_items
         SET balance_amount=0,item_status='carried_forward',carried_forward_at=NOW()
         WHERE id=:id AND tenant_id=:tenant_id
           AND item_status IN('unpaid','partial')"
    );

    $total=0.0;
    foreach($rows as $row){
        $status=strtolower((string)($row['item_status']??''));
        $calculated=max(0.0,
            (float)($row['original_amount']??0)
            -(float)($row['discount_amount']??0)
            -(float)($row['paid_amount']??0)
        );
        $amount=$status==='carried_forward'
            ? $calculated
            : max((float)($row['balance_amount']??0),$calculated);
        $amount=round($amount,2);
        if($amount<=0.009)continue;

        $sourceItemId=(int)$row['id'];
        $name=trim((string)($row['item_name']??'Fee'))?:'Fee';
        $period=trim((string)($row['period_label']??''));
        $insert->execute([
            'tenant_id'=>$scope['tenant_id'],
            'assignment_id'=>$targetAssignmentId,
            'student_id'=>$studentId,
            'academic_year_id'=>$targetYearId,
            'source_year_id'=>$sourceYearId,
            'source_item_id'=>$sourceItemId,
            'item_name'=>'Previous Year Pending - '.$name,
            'period_key'=>'CF-'.$sourceYearId.'-'.$sourceItemId,
            'period_label'=>($sourceYearName!==''?$sourceYearName:'Previous Academic Year').($period!==''?' • '.$period:''),
            'due_date'=>$dueDate,
            'original_amount'=>$amount,
            'balance_amount'=>$amount,
        ]);
        if($status!=='carried_forward'){
            $markSource->execute(['id'=>$sourceItemId,'tenant_id'=>$scope['tenant_id']]);
        }
        $total+=$amount;
    }

    if($total>0.009){
        fcAggregate($pdo,$scope['tenant_id'],$sourceId);
        fcAggregate($pdo,$scope['tenant_id'],$targetAssignmentId);
    }
    return round($total,2);
}

/** Keep transport as one fixed amount; remove untouched monthly BUS rows. */
function fcNormalizeFixedTransportSchedule(PDO $pdo,array $scope,int $studentId,int $yearId,int $assignmentId):void{
    if($studentId<=0||$yearId<=0||$assignmentId<=0||!fcTable($pdo,'student_transport_assignments'))return;

    $amountColumn=fcColumn($pdo,'student_transport_assignments','transport_fee_amount')
        ? 'transport_fee_amount'
        : (fcColumn($pdo,'student_transport_assignments','bus_fee_amount')?'bus_fee_amount':'');
    if($amountColumn==='')return;

    $feeStmt=$pdo->prepare(
        "SELECT COALESCE(`{$amountColumn}`,0) fixed_fee
         FROM student_transport_assignments
         WHERE tenant_id=:tenant_id AND student_id=:student_id
           AND academic_year_id=:year_id AND status='active'
         ORDER BY id DESC LIMIT 1"
    );
    $feeStmt->execute(['tenant_id'=>$scope['tenant_id'],'student_id'=>$studentId,'year_id'=>$yearId]);
    $fixed=round((float)$feeStmt->fetchColumn(),2);
    if($fixed<=0.009)return;

    $rowsStmt=$pdo->prepare(
        "SELECT * FROM student_fee_items
         WHERE tenant_id=:tenant_id AND assignment_id=:assignment_id
           AND item_type='transport' AND item_status<>'cancelled'
         ORDER BY id"
    );
    $rowsStmt->execute(['tenant_id'=>$scope['tenant_id'],'assignment_id'=>$assignmentId]);
    $rows=$rowsStmt->fetchAll(PDO::FETCH_ASSOC);

    if(count($rows)===1
        &&(string)($rows[0]['period_key']??'')==='BUS-FIXED'
        &&abs((float)$rows[0]['original_amount']-$fixed)<=0.01)return;

    $protected=[];$protectedGross=0.0;
    foreach($rows as $row){
        if((float)($row['paid_amount']??0)>0.009
            ||(float)($row['discount_amount']??0)>0.009
            ||in_array((string)($row['item_status']??''),['paid','partial','waived'],true)){
            $protected[]=(int)$row['id'];
            $protectedGross+=(float)($row['original_amount']??0);
        }
    }

    $deleteSql="DELETE FROM student_fee_items
                WHERE tenant_id=:tenant_id AND assignment_id=:assignment_id
                  AND item_type='transport'";
    $deleteParams=['tenant_id'=>$scope['tenant_id'],'assignment_id'=>$assignmentId];
    if($protected){
        $placeholders=[];
        foreach($protected as $i=>$id){$key='p'.$i;$placeholders[]=':'.$key;$deleteParams[$key]=$id;}
        $deleteSql.=' AND id NOT IN('.implode(',',$placeholders).')';
    }
    $pdo->prepare($deleteSql)->execute($deleteParams);

    $remaining=round(max(0.0,$fixed-$protectedGross),2);
    if($remaining>0.009){
        $dueStmt=$pdo->prepare("SELECT assignment_date FROM student_fee_assignments WHERE id=:id AND tenant_id=:tenant_id LIMIT 1");
        $dueStmt->execute(['id'=>$assignmentId,'tenant_id'=>$scope['tenant_id']]);
        $dueDate=trim((string)$dueStmt->fetchColumn())?:date('Y-m-d');
        $insert=$pdo->prepare(
            "INSERT INTO student_fee_items(
                tenant_id,assignment_id,student_id,academic_year_id,
                fee_structure_item_id,transport_route_id,item_type,item_name,
                period_key,period_label,due_date,original_amount,
                discount_amount,paid_amount,balance_amount,item_status
             ) VALUES(
                :tenant_id,:assignment_id,:student_id,:year_id,
                NULL,NULL,'transport','Bus Fee','BUS-FIXED','Fixed',:due_date,
                :original_amount,0,0,:balance_amount,'unpaid'
             )
             ON DUPLICATE KEY UPDATE
                original_amount=CASE WHEN paid_amount<=0.009 THEN VALUES(original_amount) ELSE original_amount END,
                balance_amount=CASE WHEN paid_amount<=0.009 THEN VALUES(balance_amount) ELSE balance_amount END,
                period_label='Fixed'"
        );
        $insert->execute([
            'tenant_id'=>$scope['tenant_id'],'assignment_id'=>$assignmentId,
            'student_id'=>$studentId,
            'year_id'=>$yearId,
            'due_date'=>$dueDate,
            'original_amount'=>$remaining,
            'balance_amount'=>$remaining,
        ]);
    }
    fcAggregate($pdo,$scope['tenant_id'],$assignmentId);
}

function fcStudents(PDO $pdo,array $scope,array $filters):array{
    $yearId=(int)($filters['academic_year_id']??0);if($yearId<=0)return['records'=>[],'stats'=>['total_fee'=>0,'paid_amount'=>0,'balance_amount'=>0]];
    $where=['e.tenant_id=:tenant_id','e.academic_year_id=:year_id',"e.enrollment_status='active'","st.status='active'",'st.deleted_at IS NULL'];$params=['tenant_id'=>$scope['tenant_id'],'year_id'=>$yearId];if($scope['branch_id']>0){$where[]='st.branch_id=:branch_id';$params['branch_id']=$scope['branch_id'];}
    $classId=(int)($filters['class_id']??0);if($classId>0){$where[]='e.class_id=:class_id';$params['class_id']=$classId;}$sectionId=(int)($filters['section_id']??0);if($sectionId>0){$where[]='e.section_id=:section_id';$params['section_id']=$sectionId;}$search=trim((string)($filters['search']??''));if($search!==''){$where[]="(st.admission_no LIKE :search_admission OR st.first_name LIKE :search_first OR st.last_name LIKE :search_last OR CONCAT_WS(' ',st.first_name,st.last_name) LIKE :search_full OR st.mobile LIKE :search_mobile)";$v='%'.$search.'%';$params['search_admission']=$v;$params['search_first']=$v;$params['search_last']=$v;$params['search_full']=$v;$params['search_mobile']=$v;}
    $name=fcName('st');$stmt=$pdo->prepare("SELECT DISTINCT st.id,st.admission_no,st.mobile,$name student_name,e.academic_year_id,ay.year_name academic_year_name,e.class_id,c.class_name,e.section_id,sec.section_name,COALESCE(a.id,0) assignment_id,COALESCE(a.gross_amount,0) total_fee,COALESCE(a.paid_amount,0) paid_amount,COALESCE(a.balance_amount,0) balance_amount,COALESCE(a.transport_fee_amount,0) transport_fee_amount,tr.route_name transport_route_name FROM student_enrollments e INNER JOIN students st ON st.id=e.student_id AND st.tenant_id=e.tenant_id INNER JOIN academic_years ay ON ay.id=e.academic_year_id AND ay.tenant_id=e.tenant_id INNER JOIN classes c ON c.id=e.class_id AND c.tenant_id=e.tenant_id LEFT JOIN sections sec ON sec.id=e.section_id AND sec.tenant_id=e.tenant_id LEFT JOIN student_fee_assignments a ON a.student_id=e.student_id AND a.academic_year_id=e.academic_year_id AND a.tenant_id=e.tenant_id AND a.assignment_status='active' LEFT JOIN transport_routes tr ON tr.id=a.transport_route_id AND tr.tenant_id=a.tenant_id WHERE ".implode(' AND ',$where)." ORDER BY (COALESCE(a.balance_amount,0)>0) DESC,c.display_order,sec.section_name,st.first_name,st.last_name");$stmt->execute($params);$records=$stmt->fetchAll(PDO::FETCH_ASSOC);
    $refreshAssignment=$pdo->prepare("SELECT gross_amount,paid_amount,balance_amount,transport_fee_amount FROM student_fee_assignments WHERE id=:id AND tenant_id=:tenant_id LIMIT 1");
    $previousPendingStmt=$pdo->prepare("SELECT COALESCE(SUM(balance_amount),0) FROM student_fee_items WHERE tenant_id=:tenant_id AND assignment_id=:assignment_id AND (item_type='previous_due' OR source_academic_year_id IS NOT NULL) AND item_status<>'cancelled'");
    foreach($records as &$row){
        $assignmentId=(int)($row['assignment_id']??0);
        if($assignmentId>0){
            fcNormalizeFixedTransportSchedule($pdo,$scope,(int)$row['id'],(int)$row['academic_year_id'],$assignmentId);
            fcRepairPreviousYearCarryForward($pdo,$scope,(int)$row['id'],(int)$row['academic_year_id'],$assignmentId);
            $refreshAssignment->execute(['id'=>$assignmentId,'tenant_id'=>$scope['tenant_id']]);
            $fresh=$refreshAssignment->fetch(PDO::FETCH_ASSOC)?:[];
            if($fresh){
                $row['total_fee']=$fresh['gross_amount'];
                $row['paid_amount']=$fresh['paid_amount'];
                $row['balance_amount']=$fresh['balance_amount'];
                $row['transport_fee_amount']=$fresh['transport_fee_amount'];
            }
            $previousPendingStmt->execute(['tenant_id'=>$scope['tenant_id'],'assignment_id'=>$assignmentId]);
            $row['previous_year_pending']=round((float)$previousPendingStmt->fetchColumn(),2);
        }else{$row['previous_year_pending']=0.0;}
        $total=(float)$row['total_fee'];$paid=(float)$row['paid_amount'];$balance=(float)$row['balance_amount'];$row['fee_status']=$total<=0.009?'no_fee':($balance<=0.009?'paid':($paid>0.009?'partial':'pending'));
    }unset($row);
    $status=strtolower((string)($filters['fee_status']??'all'));if($status!=='all'&&$status!=='')$records=array_values(array_filter($records,fn($r)=>$status==='pending'?in_array($r['fee_status'],['pending','partial'],true):$r['fee_status']===$status));
    $stats=['total_fee'=>0.0,'paid_amount'=>0.0,'balance_amount'=>0.0];foreach($records as $row){$stats['total_fee']+=(float)$row['total_fee'];$stats['paid_amount']+=(float)$row['paid_amount'];$stats['balance_amount']+=(float)$row['balance_amount'];}
    return['records'=>$records,'stats'=>$stats];
}
function fcAssignment(PDO $pdo,array $scope,int $studentId,int $yearId,bool $lock=false):array{
    $where=['a.tenant_id=:tenant_id','a.student_id=:student_id','a.academic_year_id=:year_id',"a.assignment_status='active'"];$params=['tenant_id'=>$scope['tenant_id'],'student_id'=>$studentId,'year_id'=>$yearId];if($scope['branch_id']>0){$where[]='st.branch_id=:branch_id';$params['branch_id']=$scope['branch_id'];}
    $sql="SELECT a.*,fs.structure_name,st.branch_id,st.mobile,".fcName('st')." student_name,st.admission_no,c.class_name,sec.section_name,ay.year_name academic_year_name,ay.start_date academic_start_date,ay.end_date academic_end_date,tr.route_name transport_route_name,tr.bus_fee route_bus_fee FROM student_fee_assignments a INNER JOIN students st ON st.id=a.student_id AND st.tenant_id=a.tenant_id INNER JOIN fee_structures fs ON fs.id=a.fee_structure_id AND fs.tenant_id=a.tenant_id INNER JOIN academic_years ay ON ay.id=a.academic_year_id AND ay.tenant_id=a.tenant_id LEFT JOIN student_enrollments e ON e.student_id=a.student_id AND e.academic_year_id=a.academic_year_id AND e.tenant_id=a.tenant_id LEFT JOIN classes c ON c.id=e.class_id AND c.tenant_id=e.tenant_id LEFT JOIN sections sec ON sec.id=e.section_id AND sec.tenant_id=e.tenant_id LEFT JOIN transport_routes tr ON tr.id=a.transport_route_id AND tr.tenant_id=a.tenant_id WHERE ".implode(' AND ',$where)." ORDER BY a.id DESC LIMIT 1".($lock?' FOR UPDATE':'');$s=$pdo->prepare($sql);$s->execute($params);$row=$s->fetch(PDO::FETCH_ASSOC);if(!$row)throw new InvalidArgumentException('No active Fee Structure is assigned to this student for the selected Academic Year.');return $row;
}

function fcInstallmentPeriods(
    string $frequency,
    string $startDate,
    string $endDate,
    int $occurrenceCount,
    string $code,
    string $name
): array {
    $frequency=strtolower(trim($frequency));
    $occurrenceCount=max(1,min(36,$occurrenceCount));
    $count=match($frequency){
        'monthly'=>12,
        'quarterly'=>4,
        'half_yearly'=>2,
        'yearly','annual','one_time'=>1,
        'term','custom'=>$occurrenceCount,
        default=>1,
    };

    $start=new DateTimeImmutable($startDate);
    $end=new DateTimeImmutable($endDate);
    $days=max(1,(int)$start->diff($end)->format('%a'));
    $prefix=strtoupper(trim(preg_replace('/[^A-Z0-9]+/i','-',$code)??'FEE','-'))?:'FEE';
    $labels=[
        'monthly'=>'Month',
        'quarterly'=>'Quarter',
        'half_yearly'=>'Half-Year',
        'yearly'=>'Yearly',
        'annual'=>'Annual',
        'one_time'=>$name,
        'term'=>'Term',
        'custom'=>'Installment',
    ];
    $rows=[];

    for($i=1;$i<=$count;$i++){
        if($frequency==='monthly'){
            $due=$start->modify('+'.($i-1).' months');
            if($due>$end)break;
            $label=$due->format('F Y');
        }else{
            $offset=(int)floor(($i-1)*$days/$count);
            $due=$start->modify('+'.$offset.' days');
            $label=$count===1&&in_array($frequency,['one_time','yearly','annual'],true)
                ?($frequency==='one_time'?$name:$labels[$frequency])
                :($labels[$frequency]??'Installment').' '.$i;
        }

        $rows[]=[
            'key'=>$prefix.'-'.strtoupper(str_replace('_','-',$frequency)).'-'.$i,
            'label'=>$label,
            'due_date'=>$due->format('Y-m-d'),
        ];
    }
    return $rows;
}

function fcSyncScheduleFromStructure(PDO $pdo,array $scope,array $assignment):void{
    $assignmentId=(int)$assignment['id'];

    $structureStmt=$pdo->prepare(
        "SELECT
            fsi.id,
            fsi.amount,
            COALESCE(NULLIF(fsi.frequency,''),ft.default_frequency,'one_time') frequency,
            GREATEST(
                1,
                COALESCE(
                    NULLIF(fsi.occurrence_count,0),
                    ft.default_occurrence_count,
                    1
                )
            ) occurrence_count,
            ft.fee_type_code,
            ft.fee_type_name
         FROM fee_structure_items fsi
         INNER JOIN fee_types ft
            ON ft.id=fsi.fee_type_id
           AND ft.tenant_id=:tenant_id
           AND ft.deleted_at IS NULL
         WHERE fsi.fee_structure_id=:structure_id
           AND COALESCE(fsi.status,'active')='active'
           AND fsi.amount>0
         ORDER BY COALESCE(fsi.display_order,ft.display_order,0),fsi.id"
    );
    $structureStmt->execute([
        'tenant_id'=>$scope['tenant_id'],
        'structure_id'=>(int)$assignment['fee_structure_id'],
    ]);
    $structureItems=$structureStmt->fetchAll(PDO::FETCH_ASSOC);

    if(!$structureItems){
        return;
    }

    $existingStmt=$pdo->prepare(
        "SELECT *
         FROM student_fee_items
         WHERE tenant_id=:tenant_id
           AND assignment_id=:assignment_id
           AND fee_structure_item_id IS NOT NULL
           AND item_status<>'cancelled'
         ORDER BY fee_structure_item_id,due_date,id"
    );
    $existingStmt->execute([
        'tenant_id'=>$scope['tenant_id'],
        'assignment_id'=>$assignmentId,
    ]);

    $existingByStructureItem=[];
    foreach($existingStmt->fetchAll(PDO::FETCH_ASSOC) as $row){
        $existingByStructureItem[(int)$row['fee_structure_item_id']][]=$row;
    }

    $changes=[];

    foreach($structureItems as $structureItem){
        $structureItemId=(int)$structureItem['id'];
        $periods=fcInstallmentPeriods(
            (string)$structureItem['frequency'],
            (string)$assignment['academic_start_date'],
            (string)$assignment['academic_end_date'],
            (int)$structureItem['occurrence_count'],
            (string)$structureItem['fee_type_code'],
            (string)$structureItem['fee_type_name']
        );

        $existingRows=$existingByStructureItem[$structureItemId]??[];
        $protectedRows=array_values(array_filter(
            $existingRows,
            static fn(array $row):bool =>
                (float)($row['paid_amount']??0)>0.009
                || (float)($row['discount_amount']??0)>0.009
                || in_array(
                    (string)($row['item_status']??''),
                    ['paid','partial','waived'],
                    true
                )
        ));

        $unpaidRows=array_values(array_filter(
            $existingRows,
            static fn(array $row):bool =>
                (float)($row['paid_amount']??0)<=0.009
                && (float)($row['discount_amount']??0)<=0.009
                && in_array(
                    (string)($row['item_status']??'unpaid'),
                    ['unpaid'],
                    true
                )
        ));

        /*
         * Paid/discounted rows are historical records and are never deleted.
         * They consume the earliest configured periods. Remaining configured
         * periods are regenerated as unpaid rows.
         */
        $protectedCount=count($protectedRows);
        $remainingPeriods=array_slice($periods,min($protectedCount,count($periods)));

        $currentUnpaidSignature=array_map(
            static fn(array $row):string =>
                (string)$row['period_key'].'|'
                .(string)$row['period_label'].'|'
                .(string)$row['due_date'].'|'
                .number_format((float)$row['original_amount'],2,'.',''),
            $unpaidRows
        );

        $expectedUnpaidSignature=array_map(
            static fn(array $period):string =>
                (string)$period['key'].'|'
                .(string)$period['label'].'|'
                .(string)$period['due_date'].'|'
                .number_format((float)$structureItem['amount'],2,'.',''),
            $remainingPeriods
        );

        sort($currentUnpaidSignature);
        sort($expectedUnpaidSignature);

        if($currentUnpaidSignature!==$expectedUnpaidSignature){
            $changes[]=[
                'structure_item'=>$structureItem,
                'remaining_periods'=>$remainingPeriods,
            ];
        }
    }

    if(!$changes){
        return;
    }

    $startedTransaction=false;
    if(!$pdo->inTransaction()){
        $pdo->beginTransaction();
        $startedTransaction=true;
    }

    try{
        $deleteUnpaid=$pdo->prepare(
            "DELETE FROM student_fee_items
             WHERE tenant_id=:tenant_id
               AND assignment_id=:assignment_id
               AND fee_structure_item_id=:structure_item_id
               AND paid_amount<=0.009
               AND discount_amount<=0.009
               AND item_status='unpaid'"
        );

        $studentItemsHaveFeeHead=fcColumn($pdo,'student_fee_items','fee_head_id');

        $insertSql=$studentItemsHaveFeeHead
            ?"INSERT INTO student_fee_items(
                tenant_id,assignment_id,student_id,academic_year_id,
                fee_structure_item_id,fee_head_id,transport_route_id,item_type,item_name,
                period_key,period_label,due_date,original_amount,
                discount_amount,paid_amount,balance_amount,item_status
              ) VALUES(
                :tenant_id,:assignment_id,:student_id,:academic_year_id,
                :structure_item_id,:fee_head_id,NULL,:item_type,:item_name,
                :period_key,:period_label,:due_date,:amount,
                0,0,:balance,'unpaid'
              )"
            :"INSERT INTO student_fee_items(
                tenant_id,assignment_id,student_id,academic_year_id,
                fee_structure_item_id,transport_route_id,item_type,item_name,
                period_key,period_label,due_date,original_amount,
                discount_amount,paid_amount,balance_amount,item_status
              ) VALUES(
                :tenant_id,:assignment_id,:student_id,:academic_year_id,
                :structure_item_id,NULL,:item_type,:item_name,
                :period_key,:period_label,:due_date,:amount,
                0,0,:balance,'unpaid'
              )";

        $insert=$pdo->prepare($insertSql);

        foreach($changes as $change){
            $item=$change['structure_item'];
            $structureItemId=(int)$item['id'];

            $deleteUnpaid->execute([
                'tenant_id'=>$scope['tenant_id'],
                'assignment_id'=>$assignmentId,
                'structure_item_id'=>$structureItemId,
            ]);

            $code=strtolower((string)$item['fee_type_code']);
            $itemType=match(true){
                str_contains($code,'admission')=>'admission',
                str_contains($code,'tuition')=>'tuition',
                str_contains($code,'exam'),str_contains($code,'term')=>'term',
                default=>'additional',
            };

            foreach($change['remaining_periods'] as $period){
                $insertParams=[
                    'tenant_id'=>$scope['tenant_id'],
                    'assignment_id'=>$assignmentId,
                    'student_id'=>(int)$assignment['student_id'],
                    'academic_year_id'=>(int)$assignment['academic_year_id'],
                    'structure_item_id'=>$structureItemId,
                    'item_type'=>$itemType,
                    'item_name'=>(string)$item['fee_type_name'],
                    'period_key'=>(string)$period['key'],
                    'period_label'=>(string)$period['label'],
                    'due_date'=>(string)$period['due_date'],
                    'amount'=>(float)$item['amount'],
                    'balance'=>(float)$item['amount'],
                ];

                if($studentItemsHaveFeeHead){
                    $insertParams['fee_head_id']=fcRequireFeeHeadId(
                        $pdo,
                        $scope['tenant_id'],
                        $structureItemId
                    );
                }

                $insert->execute($insertParams);
            }
        }

        fcAggregate($pdo,$scope['tenant_id'],$assignmentId);

        if($startedTransaction){
            $pdo->commit();
        }
    }catch(Throwable $e){
        if($startedTransaction&&$pdo->inTransaction()){
            $pdo->rollBack();
        }
        throw $e;
    }
}

function fcItemRows(PDO $pdo,int $tenantId,int $studentId,string $paymentDate,int $currentAssignmentId,bool $lock=false):array{
    $date=new DateTimeImmutable($paymentDate);
    $monthStart=$date->modify('first day of this month')->format('Y-m-d');
    $monthEnd=$date->modify('last day of this month')->format('Y-m-d');
    $lockSql=$lock?' FOR UPDATE':'';

    $s=$pdo->prepare(
        "SELECT
            sfi.*,
            DATE_FORMAT(sfi.due_date,'%d-%m-%Y') due_date_display,
            cy.year_name current_academic_year_name,
            sy.year_name source_academic_year_name,
            CASE
                WHEN sfi.item_type='previous_due'
                     OR sfi.source_academic_year_id IS NOT NULL
                THEN 'previous'
                ELSE 'current'
            END fee_year_scope,
            COALESCE(sy.year_name,cy.year_name) fee_academic_year_name
         FROM student_fee_items sfi
         INNER JOIN student_fee_assignments a
            ON a.id=sfi.assignment_id
           AND a.tenant_id=sfi.tenant_id
           AND a.assignment_status='active'
         LEFT JOIN academic_years cy
            ON cy.id=sfi.academic_year_id
           AND cy.tenant_id=sfi.tenant_id
         LEFT JOIN academic_years sy
            ON sy.id=sfi.source_academic_year_id
           AND sy.tenant_id=sfi.tenant_id
         WHERE sfi.tenant_id=:tenant_id
           AND sfi.student_id=:student_id
           AND sfi.assignment_id=:assignment_id
           AND sfi.balance_amount>0.009
           AND sfi.item_status IN('unpaid','partial')
         ORDER BY
            CASE
                WHEN sfi.item_type='previous_due'
                     OR sfi.source_academic_year_id IS NOT NULL
                THEN 0 ELSE 1
            END,
            sfi.due_date,
            sfi.id".$lockSql
    );
    $s->execute([
        'tenant_id'=>$tenantId,
        'student_id'=>$studentId,
        'assignment_id'=>$currentAssignmentId,
    ]);
    $rows=$s->fetchAll(PDO::FETCH_ASSOC);

    $previous=0.0;$current=0.0;$future=0.0;$outstanding=0.0;

    foreach($rows as &$row){
        $dueDate=(string)$row['due_date'];
        if(($row['fee_year_scope']??'')==='previous'){
            $row['bucket']='previous_year';
            $previous+=(float)$row['balance_amount'];
        }elseif($dueDate<$monthStart){
            $row['bucket']='previous';
            $previous+=(float)$row['balance_amount'];
        }elseif($dueDate>$monthEnd){
            $row['bucket']='future';
            $future+=(float)$row['balance_amount'];
        }else{
            $row['bucket']='current';
            $current+=(float)$row['balance_amount'];
        }
        $outstanding+=(float)$row['balance_amount'];
    }
    unset($row);

    return[
        'items'=>$rows,
        'previous_due'=>round($previous,2),
        'current_due'=>round($current,2),
        'future_due'=>round($future,2),
        'outstanding_balance'=>round($outstanding,2),
        'current_fee'=>round($outstanding,2),
    ];
}
function fcEnsureAssignmentSchedule(PDO $pdo,array $assignment,string $dueDate):void{
    $tenantId=(int)$assignment['tenant_id'];
    $assignmentId=(int)$assignment['id'];
    $studentId=(int)$assignment['student_id'];
    $yearId=(int)$assignment['academic_year_id'];

    $count=$pdo->prepare("SELECT COUNT(*) FROM student_fee_items WHERE tenant_id=:tenant_id AND assignment_id=:assignment_id AND item_status<>'cancelled'");
    $count->execute(['tenant_id'=>$tenantId,'assignment_id'=>$assignmentId]);
    if((int)$count->fetchColumn()>0)return;

    $gross=round((float)($assignment['gross_amount']??0),2);
    $paid=round((float)($assignment['paid_amount']??0),2);
    $balance=round((float)($assignment['balance_amount']??0),2);
    $net=round((float)($assignment['net_amount']??$gross),2);
    $discount=max(0,round($gross-$net,2));

    if($gross<=0.009&&$balance<=0.009)return;
    if($gross<=0.009)$gross=round($paid+$balance+$discount,2);

    $status=$balance<=0.009?($paid>0?'paid':'waived'):($paid>0?'partial':'unpaid');
    $name=trim((string)($assignment['structure_name']??'Fee Structure'));
    if($name==='')$name='Fee Structure';

    $studentItemsHaveFeeHead=fcColumn($pdo,'student_fee_items','fee_head_id');
    $insertSql=$studentItemsHaveFeeHead
        ?"INSERT INTO student_fee_items(
            tenant_id,assignment_id,student_id,academic_year_id,
            fee_structure_item_id,fee_head_id,transport_route_id,source_receipt_id,
            item_type,item_name,period_key,period_label,due_date,
            original_amount,discount_amount,paid_amount,balance_amount,item_status
          ) VALUES(
            :tenant_id,:assignment_id,:student_id,:year_id,
            NULL,:fee_head_id,NULL,NULL,
            'additional',:item_name,:period_key,'Outstanding Fee',:due_date,
            :gross,:discount,:paid,:balance,:status
          )"
        :"INSERT INTO student_fee_items(
            tenant_id,assignment_id,student_id,academic_year_id,
            fee_structure_item_id,transport_route_id,source_receipt_id,
            item_type,item_name,period_key,period_label,due_date,
            original_amount,discount_amount,paid_amount,balance_amount,item_status
          ) VALUES(
            :tenant_id,:assignment_id,:student_id,:year_id,
            NULL,NULL,NULL,
            'additional',:item_name,:period_key,'Outstanding Fee',:due_date,
            :gross,:discount,:paid,:balance,:status
          )";
    $insert=$pdo->prepare($insertSql);
    $insertParams=[
        'tenant_id'=>$tenantId,
        'assignment_id'=>$assignmentId,
        'student_id'=>$studentId,
        'year_id'=>$yearId,
        'item_name'=>$name,
        'period_key'=>'ASSIGNMENT-'.$assignmentId,
        'due_date'=>$dueDate,
        'gross'=>$gross,
        'discount'=>$discount,
        'paid'=>$paid,
        'balance'=>$balance,
        'status'=>$status,
    ];

    if($studentItemsHaveFeeHead){
        $insertParams['fee_head_id']=fcRequireFeeHeadId($pdo,$tenantId);
    }

    $insert->execute($insertParams);
}

function fcSchedule(PDO $pdo,int $tenantId,int $assignmentId):array{
    $s=$pdo->prepare(
        "SELECT
            sfi.*,
            DATE_FORMAT(sfi.due_date,'%d-%m-%Y') due_date_display,
            cy.year_name current_academic_year_name,
            sy.year_name source_academic_year_name,
            CASE
                WHEN sfi.item_type='previous_due'
                     OR sfi.source_academic_year_id IS NOT NULL
                THEN 'previous'
                ELSE 'current'
            END fee_year_scope,
            COALESCE(sy.year_name,cy.year_name) fee_academic_year_name
         FROM student_fee_items sfi
         LEFT JOIN academic_years cy
           ON cy.id=sfi.academic_year_id
          AND cy.tenant_id=sfi.tenant_id
         LEFT JOIN academic_years sy
           ON sy.id=sfi.source_academic_year_id
          AND sy.tenant_id=sfi.tenant_id
         WHERE sfi.tenant_id=:tenant_id
           AND sfi.assignment_id=:assignment_id
           AND sfi.item_status<>'cancelled'
         ORDER BY
            CASE
                WHEN sfi.item_type='previous_due'
                     OR sfi.source_academic_year_id IS NOT NULL
                THEN 0 ELSE 1
            END,
            sfi.due_date,
            sfi.id"
    );
    $s->execute([
        'tenant_id'=>$tenantId,
        'assignment_id'=>$assignmentId,
    ]);
    return $s->fetchAll(PDO::FETCH_ASSOC);
}
function fcAggregate(PDO $pdo,int $tenantId,int $assignmentId):void{$s=$pdo->prepare("SELECT COALESCE(SUM(original_amount),0) gross,COALESCE(SUM(discount_amount),0) discount,COALESCE(SUM(paid_amount),0) paid,COALESCE(SUM(balance_amount),0) balance,COALESCE(SUM(CASE WHEN item_type NOT IN('transport','previous_due') THEN original_amount ELSE 0 END),0) base,COALESCE(SUM(CASE WHEN item_type='transport' THEN original_amount ELSE 0 END),0) transport FROM student_fee_items WHERE tenant_id=:tenant_id AND assignment_id=:assignment_id AND item_status<>'cancelled'");$s->execute(['tenant_id'=>$tenantId,'assignment_id'=>$assignmentId]);$t=$s->fetch(PDO::FETCH_ASSOC);$status=(float)$t['balance']<=0.009?'paid':((float)$t['paid']>0?'partial':'unpaid');$pdo->prepare("UPDATE student_fee_assignments SET base_fee_amount=:base,transport_fee_amount=:transport,gross_amount=:gross,net_amount=:net,paid_amount=:paid,balance_amount=:balance,payment_status=:status WHERE id=:id AND tenant_id=:tenant_id")->execute(['base'=>$t['base'],'transport'=>$t['transport'],'gross'=>$t['gross'],'net'=>max(0,(float)$t['gross']-(float)$t['discount']),'paid'=>$t['paid'],'balance'=>$t['balance'],'status'=>$status,'id'=>$assignmentId,'tenant_id'=>$tenantId]);}
function fcReceipts(PDO $pdo,array $scope,array $filters):array{
    $where=['r.tenant_id=:tenant_id'];$params=['tenant_id'=>$scope['tenant_id']];if($scope['branch_id']>0){$where[]='r.branch_id=:branch_id';$params['branch_id']=$scope['branch_id'];}
    $year=(int)($filters['academic_year_id']??0);if($year>0){$where[]='r.academic_year_id=:year_id';$params['year_id']=$year;}$id=(int)($filters['id']??0);if($id>0){$where[]='r.id=:receipt_id';$params['receipt_id']=$id;}$search=trim((string)($filters['search']??''));if($search!==''){$where[]="(r.receipt_no LIKE :search_receipt OR st.admission_no LIKE :search_admission OR st.first_name LIKE :search_first OR st.last_name LIKE :search_last OR CONCAT_WS(' ',st.first_name,st.last_name) LIKE :search_full)";$v='%'.$search.'%';$params['search_receipt']=$v;$params['search_admission']=$v;$params['search_first']=$v;$params['search_last']=$v;$params['search_full']=$v;}if(!empty($filters['from_date'])){$where[]='DATE(r.receipt_date)>=:from_date';$params['from_date']=$filters['from_date'];}if(!empty($filters['to_date'])){$where[]='DATE(r.receipt_date)<=:to_date';$params['to_date']=$filters['to_date'];}$status=strtolower((string)($filters['status']??'all'));if(in_array($status,['due','partial','paid','reversed'],true)){$where[]='r.payment_status=:payment_status';$params['payment_status']=$status;}$method=(string)($filters['payment_method_id']??'all');if($method!==''&&$method!=='all'){$where[]='EXISTS(SELECT 1 FROM fee_payments pf WHERE pf.receipt_id=r.id AND pf.tenant_id=r.tenant_id AND pf.payment_method_id=:method_id)';$params['method_id']=(int)$method;}
    $page=max(1,(int)($filters['page']??1));$per=min(500,max(5,(int)($filters['per_page']??10)));$count=$pdo->prepare("SELECT COUNT(*) FROM fee_receipts r INNER JOIN students st ON st.id=r.student_id AND st.tenant_id=r.tenant_id WHERE ".implode(' AND ',$where));$count->execute($params);$total=(int)$count->fetchColumn();$last=max(1,(int)ceil($total/$per));$page=min($page,$last);$offset=($page-1)*$per;$name=fcName('st');$s=$pdo->prepare("SELECT r.*,$name student_name,st.admission_no,DATE_FORMAT(r.receipt_date,'%d-%m-%Y %h:%i %p') receipt_date_display,GROUP_CONCAT(DISTINCT pm.method_name ORDER BY pm.method_name SEPARATOR ', ') payment_methods,GROUP_CONCAT(DISTINCT NULLIF(p.reference_no,'') ORDER BY p.id SEPARATOR ', ') reference_numbers,GREATEST(0,r.gross_amount-r.discount_amount-r.paid_amount) due_amount FROM fee_receipts r INNER JOIN students st ON st.id=r.student_id AND st.tenant_id=r.tenant_id LEFT JOIN fee_payments p ON p.receipt_id=r.id AND p.tenant_id=r.tenant_id LEFT JOIN payment_methods pm ON pm.id=p.payment_method_id AND pm.tenant_id=p.tenant_id WHERE ".implode(' AND ',$where)." GROUP BY r.id ORDER BY r.receipt_date DESC,r.id DESC LIMIT $per OFFSET $offset");$s->execute($params);$rows=$s->fetchAll(PDO::FETCH_ASSOC);foreach($rows as &$row)$row['clean_notes']=fcClean((string)($row['notes']??''));unset($row);return['records'=>$rows,'pagination'=>['total'=>$total,'page'=>$page,'per_page'=>$per,'last_page'=>$last]];
}
function fcReceipt(PDO $pdo,array $scope,int $id):array{$result=fcReceipts($pdo,$scope,['id'=>$id,'page'=>1,'per_page'=>5]);if(!$result['records'])throw new InvalidArgumentException('Receipt not found.');return $result['records'][0];}
function fcPdf(string $title,array $lines):string{$content="BT /F1 11 Tf 45 800 Td ";$first=true;foreach(array_merge([$title],$lines) as $line){$safe=str_replace(['\\','(',')',"\r","\n"],['\\\\','\\(','\\)',' ',' '],$line);$content.=($first?'':'0 -18 Td ')."($safe) Tj ";$first=false;}$content.='ET';$objects=["1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj","2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj","3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >> endobj","4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj","5 0 obj << /Length ".strlen($content)." >> stream\n$content\nendstream endobj"];$pdf="%PDF-1.4\n";$offsets=[0];foreach($objects as $object){$offsets[]=strlen($pdf);$pdf.=$object."\n";}$xref=strlen($pdf);$pdf.="xref\n0 6\n0000000000 65535 f \n";for($i=1;$i<=5;$i++)$pdf.=sprintf("%010d 00000 n \n",$offsets[$i]);return $pdf."trailer << /Size 6 /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";}

function fcReceiptSchoolProfile(PDO $pdo,array $scope,int $branchId):array{
    $profile=[];

    if(fcTable($pdo,'school_profile')){
        $sql="SELECT * FROM school_profile
              WHERE tenant_id=:tenant_id
                AND (branch_id=:branch_id OR branch_id IS NULL)
              ORDER BY (branch_id=:branch_order) DESC,id DESC
              LIMIT 1";
        $s=$pdo->prepare($sql);
        $s->execute([
            'tenant_id'=>$scope['tenant_id'],
            'branch_id'=>$branchId,
            'branch_order'=>$branchId,
        ]);
        $profile=$s->fetch(PDO::FETCH_ASSOC)?:[];
    }

    $tenant=[];
    if(fcTable($pdo,'tenants')){
        $s=$pdo->prepare("SELECT school_name,email,mobile,address,logo_path FROM tenants WHERE id=:tenant_id LIMIT 1");
        $s->execute(['tenant_id'=>$scope['tenant_id']]);
        $tenant=$s->fetch(PDO::FETCH_ASSOC)?:[];
    }

    $branch=[];
    if($branchId>0&&fcTable($pdo,'branches')){
        $s=$pdo->prepare("SELECT branch_code,branch_name,address,phone FROM branches WHERE id=:branch_id AND tenant_id=:tenant_id LIMIT 1");
        $s->execute(['branch_id'=>$branchId,'tenant_id'=>$scope['tenant_id']]);
        $branch=$s->fetch(PDO::FETCH_ASSOC)?:[];
    }

    $schoolName=trim((string)($profile['school_name']??$tenant['school_name']??'School'));
    $logoPath=trim((string)($profile['logo_path']??$tenant['logo_path']??''));
    $phone=trim((string)($profile['phone_number']??$tenant['mobile']??$branch['phone']??''));
    $email=trim((string)($profile['email_address']??$tenant['email']??''));

    $address1=trim(implode(', ',array_values(array_filter([
        trim((string)($profile['address_line1']??'')),
        trim((string)($profile['address_line2']??'')),
    ],static fn($v)=>$v!==''))));

    $cityState=[];
    foreach(['city','district','state_name'] as $key){
        $v=trim((string)($profile[$key]??''));
        if($v!==''&&!in_array(mb_strtolower($v),array_map('mb_strtolower',$cityState),true))$cityState[]=$v;
    }
    $address2=implode(', ',$cityState);
    $postal=trim((string)($profile['postal_code']??''));
    if($postal!=='')$address2.=($address2!==''?' - ':'').$postal;

    if($address1===''&&$address2===''){
        $fallback=trim((string)($branch['address']??$tenant['address']??''));
        $address1=$fallback;
    }

    return[
        'school_name'=>$schoolName!==''?$schoolName:'School',
        'logo_path'=>$logoPath,
        'phone'=>$phone,
        'email'=>$email,
        'address1'=>$address1,
        'address2'=>$address2,
        'branch_name'=>trim((string)($branch['branch_name']??'')),
        'branch_code'=>trim((string)($branch['branch_code']??'')),
    ];
}

function fcReceiptAssetUrl(string $path):string{
    $path=trim(str_replace('\\','/',$path));
    if($path==='')return'';
    if(preg_match('~^(?:https?:)?//~i',$path)||str_starts_with($path,'data:'))return $path;
    $path=ltrim($path,'/');
    if(function_exists('app_url'))return app_url($path);
    return '../'.$path;
}

function fcReceiptStudentContext(PDO $pdo,array $scope,array $receipt):array{
    $sql="SELECT
              st.mobile,
              ay.year_name academic_year_name,
              c.class_name,
              COALESCE(sec.section_name,'') section_name,
              COALESCE(b.branch_name,'') branch_name,
              COALESCE(b.branch_code,'') branch_code,
              COALESCE(u.name,'') collected_by_name,
              COALESCE(
                (SELECT g1.guardian_name
                   FROM student_guardians sg1
                   INNER JOIN guardians g1 ON g1.id=sg1.guardian_id AND g1.tenant_id=st.tenant_id
                  WHERE sg1.student_id=st.id
                    AND LOWER(COALESCE(g1.relationship,''))='father'
                  ORDER BY sg1.is_primary DESC,g1.id
                  LIMIT 1),
                (SELECT g2.guardian_name
                   FROM student_guardians sg2
                   INNER JOIN guardians g2 ON g2.id=sg2.guardian_id AND g2.tenant_id=st.tenant_id
                  WHERE sg2.student_id=st.id
                  ORDER BY sg2.is_primary DESC,g2.id
                  LIMIT 1),
                ''
              ) father_name
          FROM students st
          INNER JOIN academic_years ay
             ON ay.id=:academic_year_id
            AND ay.tenant_id=st.tenant_id
          LEFT JOIN student_enrollments e
             ON e.student_id=st.id
            AND e.tenant_id=st.tenant_id
            AND e.academic_year_id=:academic_year_id_enrollment
          LEFT JOIN classes c
             ON c.id=e.class_id
            AND c.tenant_id=e.tenant_id
          LEFT JOIN sections sec
             ON sec.id=e.section_id
            AND sec.tenant_id=e.tenant_id
          LEFT JOIN branches b
             ON b.id=:branch_id
            AND b.tenant_id=st.tenant_id
          LEFT JOIN users u
             ON u.id=:collected_by
            AND u.tenant_id=st.tenant_id
          WHERE st.id=:student_id
            AND st.tenant_id=:tenant_id
          ORDER BY (e.enrollment_status='active') DESC,e.id DESC
          LIMIT 1";
    $s=$pdo->prepare($sql);
    $s->execute([
        'academic_year_id'=>(int)$receipt['academic_year_id'],
        'academic_year_id_enrollment'=>(int)$receipt['academic_year_id'],
        'branch_id'=>(int)$receipt['branch_id'],
        'collected_by'=>(int)$receipt['collected_by'],
        'student_id'=>(int)$receipt['student_id'],
        'tenant_id'=>$scope['tenant_id'],
    ]);
    return $s->fetch(PDO::FETCH_ASSOC)?:[];
}

function fcIndianIntegerWords(int $number):string{
    if($number===0)return'Zero';
    $ones=['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine','Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen'];
    $tens=['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
    $under100=static function(int $n)use($ones,$tens):string{
        if($n<20)return $ones[$n];
        return trim($tens[intdiv($n,10)].' '.$ones[$n%10]);
    };
    $under1000=static function(int $n)use($under100):string{
        if($n<100)return $under100($n);
        return trim($under100(intdiv($n,100)).' Hundred '.($n%100?$under100($n%100):''));
    };

    $parts=[];
    $crore=intdiv($number,10000000);$number%=10000000;
    $lakh=intdiv($number,100000);$number%=100000;
    $thousand=intdiv($number,1000);$number%=1000;

    if($crore)$parts[]=fcIndianIntegerWords($crore).' Crore';
    if($lakh)$parts[]=$under1000($lakh).' Lakh';
    if($thousand)$parts[]=$under1000($thousand).' Thousand';
    if($number)$parts[]=$under1000($number);
    return trim(implode(' ',$parts));
}

function fcAmountWords(float $amount):string{
    $amount=round(max(0,$amount),2);
    $rupees=(int)floor($amount+0.00001);
    $paise=(int)round(($amount-$rupees)*100);
    if($paise>=100){$rupees++;$paise-=100;}
    $text=fcIndianIntegerWords($rupees).' Rupee'.($rupees===1?'':'s');
    if($paise>0)$text.=' and '.fcIndianIntegerWords($paise).' Paise';
    return $text.' Only';
}

function fcReceiptPrintLines(PDO $pdo,array $scope,int $receiptId):array{
    $s=$pdo->prepare("SELECT *
                      FROM fee_receipt_items
                      WHERE tenant_id=:tenant_id
                        AND receipt_id=:receipt_id
                        AND (paid_amount>0.009 OR discount_amount>0.009)
                      ORDER BY id");
    $s->execute(['tenant_id'=>$scope['tenant_id'],'receipt_id'=>$receiptId]);
    $rows=$s->fetchAll(PDO::FETCH_ASSOC);
    if($rows)return $rows;

    $s=$pdo->prepare("SELECT * FROM fee_receipt_items WHERE tenant_id=:tenant_id AND receipt_id=:receipt_id ORDER BY id");
    $s->execute(['tenant_id'=>$scope['tenant_id'],'receipt_id'=>$receiptId]);
    return $s->fetchAll(PDO::FETCH_ASSOC);
}

function fcRenderReceiptHtml(PDO $pdo,array $scope,array $receipt,array $lines,bool $autoPrint,string $returnUrl):string{
    $e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
    $profile=fcReceiptSchoolProfile($pdo,$scope,(int)$receipt['branch_id']);
    $student=fcReceiptStudentContext($pdo,$scope,$receipt);
    $logoUrl=fcReceiptAssetUrl((string)$profile['logo_path']);
    $schoolName=mb_strtoupper(trim((string)$profile['school_name']));
    $branchName=trim((string)($student['branch_name']?:$profile['branch_name']));
    $branchCode=trim((string)($student['branch_code']?:$profile['branch_code']));
    $branchDisplay=$branchName;
    if($branchCode!=='')$branchDisplay.=($branchDisplay!==''?' ':'').'('.$branchCode.')';
    if($branchDisplay==='')$branchDisplay='-';

    $receiptDate=(string)($receipt['receipt_date']??'');
    $receiptDateDisplay=$receiptDate!==''?date('d-m-Y',strtotime($receiptDate)):(string)($receipt['receipt_date_display']??'');
    $paymentMode=trim((string)($receipt['payment_methods']??''));
    if($paymentMode==='')$paymentMode='-';
    $collector=trim((string)($student['collected_by_name']??''));
    if($collector==='')$collector='-';
    $paidAmount=(float)$receipt['paid_amount'];
    $status=strtolower((string)$receipt['payment_status']);
    $watermark=match($status){
        'paid'=>'PAID RECEIPT',
        'partial'=>'PARTIAL RECEIPT',
        'reversed'=>'REVERSED RECEIPT',
        default=>'FEE RECEIPT',
    };

    ob_start();
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $e($receipt['receipt_no']) ?> - Fee Payment Receipt</title>
<style>
@page{size:A4 portrait;margin:0}
*{box-sizing:border-box}
html,body{margin:0;padding:0;background:#eef1f6;color:#141927;font-family:Arial,Helvetica,sans-serif;-webkit-print-color-adjust:exact;print-color-adjust:exact}
body{padding:14px}
.receipt-sheet{position:relative;width:210mm;min-height:297mm;margin:0 auto;background:#fff;padding:8mm;border:1.4px solid #151b2a;overflow:hidden}
.receipt-content{position:relative;z-index:2}
.receipt-watermark{position:absolute;z-index:1;left:28mm;top:135mm;transform:rotate(-30deg);font-weight:900;font-size:40px;letter-spacing:3px;color:rgba(20,25,39,.055);white-space:nowrap;pointer-events:none}
.school-head{display:grid;grid-template-columns:38mm 1fr;gap:6mm;align-items:center;min-height:44mm;padding:2mm 3mm 5mm;border-bottom:1.5px solid #111827}
.logo-wrap{width:34mm;height:34mm;display:flex;align-items:center;justify-content:center}
.logo-wrap img{display:block;max-width:100%;max-height:100%;object-fit:contain}
.logo-fallback{width:32mm;height:32mm;border:1px solid #cbd5e1;border-radius:50%;display:grid;place-items:center;font-weight:800;font-size:10px;text-align:center;padding:3mm;color:#475569}
.school-name{margin:0 0 2mm;font-size:20px;line-height:1.05;font-weight:900;letter-spacing:.1px;color:#111827}
.school-address,.school-contact{font-size:10.5px;line-height:1.5;color:#374151}
.receipt-title{margin:3.5mm 0;border:1px solid #374151;text-align:center;padding:3.1mm 2mm;font-size:17px;font-weight:900;letter-spacing:.5px}
.details-grid{display:grid;grid-template-columns:1fr 1fr;gap:3mm;margin-bottom:3mm}
.detail-box{border:1px solid #9ca3af;min-height:60mm;padding:3.4mm}
.detail-title{font-size:12px;font-weight:900;border-bottom:1px solid #cbd5e1;padding-bottom:2mm;margin-bottom:2.5mm}
.detail-row{display:grid;grid-template-columns:36mm 4mm 1fr;gap:0;align-items:start;font-size:10.5px;line-height:1.45;margin:1.25mm 0}
.detail-row .label{font-weight:700;color:#4b5563}.detail-row .colon{font-weight:700}.detail-row .value{font-weight:800;overflow-wrap:anywhere}
.fee-table{width:100%;border-collapse:collapse;table-layout:fixed;font-size:10.5px}
.fee-table th,.fee-table td{border:1px solid #1f2937;padding:3mm 2mm;vertical-align:middle}
.fee-table th{text-align:center;font-weight:900;font-size:10px}
.fee-table td.center{text-align:center}.fee-table td.amount{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}.fee-table td.strong{font-weight:800}
.amount-words{border:1px solid #1f2937;border-top:0;padding:3mm 2.5mm;font-size:10.5px;font-weight:700}
.total-box{width:46%;margin:4mm 0 0 auto;border-collapse:collapse;font-size:11px}
.total-box td{border:1px solid #1f2937;padding:3mm}.total-box td:first-child{font-weight:900}.total-box td:last-child{text-align:right;font-variant-numeric:tabular-nums}.total-box .grand td{font-size:14px;font-weight:900}
.bottom-area{display:grid;grid-template-columns:1.6fr .9fr;gap:8mm;align-items:end;margin-top:12mm}
.note-box{border:1px dashed #9ca3af;padding:3mm;font-size:9.5px;line-height:1.45;color:#374151;min-height:23mm}.note-box strong{display:block;margin-bottom:1mm;color:#1f2937;font-size:10px}
.signature{text-align:center;font-size:10px;font-weight:900;padding-bottom:4mm}
.action-bar{width:210mm;margin:10px auto 0;display:flex;justify-content:center;gap:10px}.action-bar button,.action-bar a{border:0;border-radius:8px;padding:10px 18px;background:#4f46e5;color:#fff;text-decoration:none;font-weight:800;cursor:pointer;font-size:13px}.action-bar a{background:#475569}
.status-tag{position:absolute;right:10mm;bottom:8mm;border:1px solid #d1d5db;padding:1.5mm 3mm;font-size:9px;font-weight:900;letter-spacing:.4px;color:#6b7280}
@media(max-width:900px){body{padding:0;background:#fff}.receipt-sheet{width:100%;min-height:auto;border:0;padding:14px}.school-head{grid-template-columns:90px 1fr;min-height:auto}.logo-wrap{width:80px;height:80px}.details-grid{grid-template-columns:1fr}.detail-box{min-height:0}.detail-row{grid-template-columns:125px 14px 1fr}.fee-table{font-size:9px}.action-bar{width:100%}}
@media print{html,body{background:#fff;width:210mm;height:297mm}body{padding:0}.receipt-sheet{margin:0;width:210mm;height:297mm;min-height:297mm;border:1.4px solid #151b2a;box-shadow:none;page-break-after:avoid}.action-bar{display:none!important}}
</style>
</head>
<body>
<div class="receipt-sheet">
  <div class="receipt-watermark"><?= $e($watermark) ?></div>
  <div class="receipt-content">
    <header class="school-head">
      <div class="logo-wrap">
        <?php if($logoUrl!==''): ?>
          <img src="<?= $e($logoUrl) ?>" alt="School Logo">
        <?php else: ?>
          <div class="logo-fallback">SCHOOL<br>LOGO</div>
        <?php endif; ?>
      </div>
      <div>
        <h1 class="school-name"><?= $e($schoolName) ?></h1>
        <?php if($profile['address1']!==''): ?><div class="school-address"><?= $e($profile['address1']) ?></div><?php endif; ?>
        <?php if($profile['address2']!==''): ?><div class="school-address"><?= $e($profile['address2']) ?></div><?php endif; ?>
        <?php if($profile['phone']!==''||$profile['email']!==''): ?>
          <div class="school-contact">
            <?php if($profile['phone']!==''): ?>Phone: <?= $e($profile['phone']) ?><?php endif; ?>
            <?php if($profile['phone']!==''&&$profile['email']!==''): ?> | <?php endif; ?>
            <?php if($profile['email']!==''): ?>Email: <?= $e($profile['email']) ?><?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </header>

    <div class="receipt-title">FEE PAYMENT RECEIPT</div>

    <section class="details-grid">
      <div class="detail-box">
        <div class="detail-title">RECEIPT DETAILS</div>
        <div class="detail-row"><span class="label">Receipt No</span><span class="colon">:</span><span class="value"><?= $e($receipt['receipt_no']) ?></span></div>
        <div class="detail-row"><span class="label">Receipt Date</span><span class="colon">:</span><span class="value"><?= $e($receiptDateDisplay) ?></span></div>
        <div class="detail-row"><span class="label">Payment Mode</span><span class="colon">:</span><span class="value"><?= $e($paymentMode) ?></span></div>
        <div class="detail-row"><span class="label">GST Type</span><span class="colon">:</span><span class="value">WITHOUT GST</span></div>
        <div class="detail-row"><span class="label">Collected By</span><span class="colon">:</span><span class="value"><?= $e($collector) ?></span></div>
      </div>

      <div class="detail-box">
        <div class="detail-title">STUDENT DETAILS</div>
        <div class="detail-row"><span class="label">Student ID</span><span class="colon">:</span><span class="value"><?= $e($receipt['admission_no']?:'-') ?></span></div>
        <div class="detail-row"><span class="label">Name</span><span class="colon">:</span><span class="value"><?= $e($receipt['student_name']?:'-') ?></span></div>
        <div class="detail-row"><span class="label">Mobile</span><span class="colon">:</span><span class="value"><?= $e($student['mobile']?:'-') ?></span></div>
        <div class="detail-row"><span class="label">Father Name</span><span class="colon">:</span><span class="value"><?= $e($student['father_name']?:'-') ?></span></div>
        <div class="detail-row"><span class="label">Class</span><span class="colon">:</span><span class="value"><?= $e($student['class_name']?:'-') ?></span></div>
        <div class="detail-row"><span class="label">Section</span><span class="colon">:</span><span class="value"><?= $e($student['section_name']?:'-') ?></span></div>
        <div class="detail-row"><span class="label">Branch</span><span class="colon">:</span><span class="value"><?= $e($branchDisplay) ?></span></div>
      </div>
    </section>

    <table class="fee-table">
      <colgroup><col style="width:7%"><col style="width:21%"><col style="width:29%"><col style="width:15%"><col style="width:13%"><col style="width:15%"></colgroup>
      <thead><tr><th>S.NO</th><th>FEE CATEGORY</th><th>DESCRIPTION</th><th>PERIOD</th><th>INSTALLMENT</th><th>AMOUNT</th></tr></thead>
      <tbody>
      <?php if($lines): foreach($lines as $i=>$line):
          $category=ucwords(str_replace('_',' ',(string)($line['item_type']??'Fee')));
          $period=trim((string)($line['period_label']??''));
          $lineAmount=(float)($line['paid_amount']??$line['amount']??0);
      ?>
        <tr>
          <td class="center"><?= $i+1 ?></td>
          <td class="strong"><?= $e($category!==''?$category:'Fee') ?></td>
          <td><?= $e($line['item_name']??'Fee') ?></td>
          <td class="center"><?= $e($period!==''?$period:'-') ?></td>
          <td class="center"><?= $i+1 ?></td>
          <td class="amount">&#8377;<?= number_format($lineAmount,2) ?></td>
        </tr>
      <?php endforeach; else: ?>
        <tr><td class="center">1</td><td class="strong">Fee</td><td>Fee Payment</td><td class="center">-</td><td class="center">1</td><td class="amount">&#8377;<?= number_format($paidAmount,2) ?></td></tr>
      <?php endif; ?>
      </tbody>
    </table>

    <div class="amount-words">Amount in Words: <?= $e(fcAmountWords($paidAmount)) ?></div>

    <table class="total-box">
      <tr><td>Subtotal</td><td>&#8377;<?= number_format($paidAmount,2) ?></td></tr>
      <tr class="grand"><td>Grand Total</td><td>&#8377;<?= number_format($paidAmount,2) ?></td></tr>
    </table>

    <section class="bottom-area">
      <div class="note-box"><strong>Note:</strong>This receipt is generated based on payment records from <b>fee_receipts</b> and <b>fee_receipt_items</b>. Please keep this receipt for future reference.</div>
      <div class="signature">Authorized Signature</div>
    </section>
  </div>
  <div class="status-tag"><?= $e($watermark) ?></div>
</div>
<div class="action-bar"><button type="button" onclick="window.print()">Print Receipt</button><a href="<?= $e($returnUrl) ?>">Back to Fee Collection</a></div>
<?php if($autoPrint): ?>
<script>
(function(){
  "use strict";
  const returnUrl=<?= json_encode($returnUrl,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
  let returned=false;
  function goBack(){
    if(returned)return;
    returned=true;
    try{
      if(window.opener&&!window.opener.closed){
        window.opener.focus();
        window.close();
        setTimeout(function(){if(!window.closed)window.location.replace(returnUrl);},150);
        return;
      }
    }catch(e){}
    window.location.replace(returnUrl);
  }
  window.addEventListener('afterprint',function(){setTimeout(goBack,80);},{once:true});
  window.addEventListener('load',function(){setTimeout(function(){try{window.focus();window.print();}finally{setTimeout(goBack,220);}},280);},{once:true});
})();
</script>
<?php endif; ?>
</body>
</html>
<?php
    return (string)ob_get_clean();
}


if(!isset($pdo)||!$pdo instanceof PDO)fcOut(false,'Database connection unavailable.',[],500);if(session_status()!==PHP_SESSION_ACTIVE)session_start();if(empty($_SESSION['fee_csrf_token']))$_SESSION['fee_csrf_token']=bin2hex(random_bytes(32));$scope=fcScope();if($scope['tenant_id']<=0||$scope['user_id']<=0)fcOut(false,'Tenant or user session is missing.',[],401);try{fcEnsure($pdo,$scope['tenant_id']);fcEnsureIncomeTables($pdo);}catch(Throwable $e){fcOut(false,'Unable to initialize Fee Collection: '.$e->getMessage(),[],500);}$input=fcInput();$action=strtolower(trim((string)($input['action']??$_GET['action']??'')));
try{
 if($action==='meta'){
    $meta=fcMeta($pdo,$scope);
    $meta['api_build']=FEE_COLLECTION_BUILD;
    fcOut(true,'Fee Collection metadata loaded.',$meta);
}
 if($action==='students')fcOut(true,'Students loaded.',fcStudents($pdo,$scope,array_merge($_GET,$input)));
 if($action==='detail'){
    $studentId=(int)($_GET['student_id']??0);
    $yearId=(int)($_GET['academic_year_id']??0);
    $date=fcValidDate((string)($_GET['payment_date']??date('Y-m-d')),'Payment Date');
    $assignment=fcAssignment($pdo,$scope,$studentId,$yearId);
    fcEnsureAssignmentSchedule($pdo,$assignment,$date);
    fcSyncScheduleFromStructure($pdo,$scope,$assignment);
    fcNormalizeFixedTransportSchedule($pdo,$scope,$studentId,$yearId,(int)$assignment['id']);
    fcRepairPreviousYearCarryForward($pdo,$scope,$studentId,$yearId,(int)$assignment['id']);
    $assignment=fcAssignment($pdo,$scope,$studentId,$yearId);
    $due=fcItemRows($pdo,$scope['tenant_id'],$studentId,$date,(int)$assignment['id']);
    $schedule=fcSchedule($pdo,$scope['tenant_id'],(int)$assignment['id']);

    $assignedTotal=round((float)($assignment['gross_amount']??0),2);
    $existingPaid=round((float)($assignment['paid_amount']??0),2);
    $outstanding=round((float)($assignment['balance_amount']??0),2);
    $netAmount=round((float)($assignment['net_amount']??$assignedTotal),2);
    $existingDiscount=max(0,round($assignedTotal-$netAmount,2));

    /*
     * Repair stale assignment aggregates from the live schedule when needed.
     * This keeps the list and modal aligned with student_fee_items.
     */
    $scheduleGross=0.0;
    $scheduleDiscount=0.0;
    $schedulePaid=0.0;
    $scheduleBalance=0.0;
    $feeBreakdown=[
        'previous_year_pending'=>0.0,
        'current_year_tuition'=>0.0,
        'current_year_admission'=>0.0,
        'current_year_transport'=>0.0,
        'current_year_extra'=>0.0,
        'current_year_other'=>0.0,
        'current_year_total'=>0.0,
        'total_fees'=>0.0,
        'discount'=>0.0,
        'amount_paid'=>0.0,
        'remaining_balance'=>0.0,
    ];

    foreach($schedule as $item){
        $gross=(float)($item['original_amount']??0);
        $itemDiscount=(float)($item['discount_amount']??0);
        $itemPaid=(float)($item['paid_amount']??0);
        $itemBalance=(float)($item['balance_amount']??0);
        $type=strtolower((string)($item['item_type']??'additional'));
        $yearScope=(string)($item['fee_year_scope']??'current');

        $scheduleGross+=$gross;
        $scheduleDiscount+=$itemDiscount;
        $schedulePaid+=$itemPaid;
        $scheduleBalance+=$itemBalance;

        if($yearScope==='previous'||$type==='previous_due'){
            $feeBreakdown['previous_year_pending']+=$itemBalance;
            continue;
        }

        $feeBreakdown['current_year_total']+=$gross;

        if($type==='tuition'){
            $feeBreakdown['current_year_tuition']+=$gross;
        }elseif($type==='admission'){
            $feeBreakdown['current_year_admission']+=$gross;
        }elseif($type==='transport'){
            $feeBreakdown['current_year_transport']+=$gross;
        }elseif($type==='additional'){
            $feeBreakdown['current_year_extra']+=$gross;
        }else{
            $feeBreakdown['current_year_other']+=$gross;
        }
    }

    $feeBreakdown['total_fees']=$scheduleGross;
    $feeBreakdown['discount']=$scheduleDiscount;
    $feeBreakdown['amount_paid']=$schedulePaid;
    $feeBreakdown['remaining_balance']=$scheduleBalance;

    foreach($feeBreakdown as $key=>$value){
        $feeBreakdown[$key]=round((float)$value,2);
    }

    if(
        count($schedule)>0
        &&(
            abs($assignedTotal-$scheduleGross)>0.01
            || abs($existingPaid-$schedulePaid)>0.01
            || abs($outstanding-$scheduleBalance)>0.01
        )
    ){
        fcAggregate($pdo,$scope['tenant_id'],(int)$assignment['id']);
        $assignment=fcAssignment($pdo,$scope,$studentId,$yearId);
        $assignedTotal=round((float)($assignment['gross_amount']??$scheduleGross),2);
        $existingPaid=round((float)($assignment['paid_amount']??$schedulePaid),2);
        $outstanding=round((float)($assignment['balance_amount']??$scheduleBalance),2);
        $netAmount=round((float)($assignment['net_amount']??($assignedTotal-$scheduleDiscount)),2);
        $existingDiscount=max(0,round($assignedTotal-$netAmount,2));
        $due=fcItemRows($pdo,$scope['tenant_id'],$studentId,$date,(int)$assignment['id']);
        $schedule=fcSchedule($pdo,$scope['tenant_id'],(int)$assignment['id']);
    }

    fcOut(true,'Fee details loaded.',[
        'student'=>$assignment,
        'items'=>$due['items'],
        'schedule'=>$schedule,
        'assigned_total'=>$assignedTotal,
        'existing_discount'=>$existingDiscount,
        'previously_paid'=>$existingPaid,
        'outstanding_balance'=>$outstanding,
        'previous_due'=>$due['previous_due'],
        'current_due'=>$due['current_due'],
        'future_due'=>$due['future_due'],
        'fee_breakdown_summary'=>$feeBreakdown,
        // Backward compatibility.
        'current_fee'=>$outstanding,
    ]);
}

 if($action==='student_history'){
    $studentId=(int)($_GET['student_id']??0);
    $yearId=(int)($_GET['academic_year_id']??0);

    if($studentId<=0||$yearId<=0){
        throw new InvalidArgumentException('Student and Academic Year are required.');
    }

    $assignment=fcAssignment($pdo,$scope,$studentId,$yearId);
    fcEnsureAssignmentSchedule($pdo,$assignment,date('Y-m-d'));
    fcSyncScheduleFromStructure($pdo,$scope,$assignment);
    fcNormalizeFixedTransportSchedule($pdo,$scope,$studentId,$yearId,(int)$assignment['id']);
    fcRepairPreviousYearCarryForward($pdo,$scope,$studentId,$yearId,(int)$assignment['id']);
    $assignment=fcAssignment($pdo,$scope,$studentId,$yearId);
    $schedule=fcSchedule(
        $pdo,
        $scope['tenant_id'],
        (int)$assignment['id']
    );

    $receiptWhere=[
        'r.tenant_id=:tenant_id',
        'r.student_id=:student_id',
        'r.academic_year_id=:year_id'
    ];
    $receiptParams=[
        'tenant_id'=>$scope['tenant_id'],
        'student_id'=>$studentId,
        'year_id'=>$yearId,
    ];

    if($scope['branch_id']>0){
        $receiptWhere[]='r.branch_id=:branch_id';
        $receiptParams['branch_id']=$scope['branch_id'];
    }

    $receiptSql="
        SELECT
            r.id,
            r.receipt_no,
            r.receipt_date,
            DATE_FORMAT(r.receipt_date,'%d-%m-%Y %h:%i %p') receipt_date_display,
            r.gross_amount,
            r.discount_amount,
            r.paid_amount,
            GREATEST(
                0,
                COALESCE(r.gross_amount,0)
                - COALESCE(r.discount_amount,0)
                - COALESCE(r.paid_amount,0)
            ) due_amount,
            r.payment_status,
            GROUP_CONCAT(
                DISTINCT pm.method_name
                ORDER BY pm.method_name
                SEPARATOR ', '
            ) payment_methods
        FROM fee_receipts r
        LEFT JOIN fee_payments p
          ON p.receipt_id=r.id
         AND p.tenant_id=r.tenant_id
        LEFT JOIN payment_methods pm
          ON pm.id=p.payment_method_id
         AND pm.tenant_id=p.tenant_id
        WHERE ".implode(' AND ',$receiptWhere)."
        GROUP BY
            r.id,r.receipt_no,r.receipt_date,
            r.gross_amount,r.discount_amount,
            r.paid_amount,r.payment_status
        ORDER BY r.receipt_date DESC,r.id DESC
    ";

    $receiptStmt=$pdo->prepare($receiptSql);
    $receiptStmt->execute($receiptParams);
    $receipts=$receiptStmt->fetchAll(PDO::FETCH_ASSOC);

    $summaryStmt=$pdo->prepare(
        "SELECT
            COALESCE(SUM(original_amount),0) total_assigned,
            COALESCE(SUM(discount_amount),0) total_discount,
            COALESCE(SUM(paid_amount),0) total_paid,
            COALESCE(SUM(balance_amount),0) balance_amount
         FROM student_fee_items
         WHERE tenant_id=:tenant_id
           AND assignment_id=:assignment_id
           AND item_status<>'cancelled'"
    );
    $summaryStmt->execute([
        'tenant_id'=>$scope['tenant_id'],
        'assignment_id'=>(int)$assignment['id'],
    ]);
    $summary=$summaryStmt->fetch(PDO::FETCH_ASSOC)?:[];

    $summary['previous_year_pending']=0.0;
    $summary['current_year_tuition']=0.0;
    $summary['current_year_admission']=0.0;
    $summary['current_year_transport']=0.0;
    $summary['current_year_extra']=0.0;
    $summary['current_year_other']=0.0;
    $summary['current_year_total']=0.0;

    foreach($schedule as $item){
        $gross=(float)($item['original_amount']??0);
        $itemBalance=(float)($item['balance_amount']??0);
        $type=strtolower((string)($item['item_type']??'additional'));
        $yearScope=(string)($item['fee_year_scope']??'current');

        if($yearScope==='previous'||$type==='previous_due'){
            $summary['previous_year_pending']+=$itemBalance;
            continue;
        }

        $summary['current_year_total']+=$gross;

        if($type==='tuition'){
            $summary['current_year_tuition']+=$gross;
        }elseif($type==='admission'){
            $summary['current_year_admission']+=$gross;
        }elseif($type==='transport'){
            $summary['current_year_transport']+=$gross;
        }elseif($type==='additional'){
            $summary['current_year_extra']+=$gross;
        }else{
            $summary['current_year_other']+=$gross;
        }
    }

    foreach([
        'previous_year_pending',
        'current_year_tuition',
        'current_year_admission',
        'current_year_transport',
        'current_year_extra',
        'current_year_other',
        'current_year_total',
    ] as $summaryKey){
        $summary[$summaryKey]=round((float)$summary[$summaryKey],2);
    }

    $student=[
        'id'=>(int)$assignment['student_id'],
        'student_name'=>$assignment['student_name']??'',
        'admission_no'=>$assignment['admission_no']??'',
        'mobile'=>$assignment['mobile']??'',
        'academic_year_name'=>$assignment['academic_year_name']??'',
        'class_name'=>$assignment['class_name']??'',
        'section_name'=>$assignment['section_name']??'',
        'structure_name'=>$assignment['structure_name']??'',
        'transport_route_name'=>$assignment['transport_route_name']??'',
        'payment_status'=>$assignment['payment_status']??'unpaid',
    ];

    $summary['receipt_count']=count($receipts);

    fcOut(true,'Student fee history loaded.',[
        'student'=>$student,
        'summary'=>$summary,
        'schedule'=>$schedule,
        'receipts'=>$receipts,
    ]);
}

 if($action==='receipts')fcOut(true,'Receipts loaded.',fcReceipts($pdo,$scope,array_merge($_GET,$input)));
 if($action==='receipt_detail'){$id=(int)($_GET['id']??0);$receipt=fcReceipt($pdo,$scope,$id);$p=$pdo->prepare("SELECT p.*,pm.method_name,pm.method_key FROM fee_payments p INNER JOIN payment_methods pm ON pm.id=p.payment_method_id AND pm.tenant_id=p.tenant_id WHERE p.tenant_id=:tenant_id AND p.receipt_id=:receipt_id ORDER BY p.id");$p->execute(['tenant_id'=>$scope['tenant_id'],'receipt_id'=>$id]);$items=$pdo->prepare("SELECT * FROM fee_receipt_items WHERE tenant_id=:tenant_id AND receipt_id=:receipt_id ORDER BY id");$items->execute(['tenant_id'=>$scope['tenant_id'],'receipt_id'=>$id]);fcOut(true,'Receipt loaded.',['receipt'=>$receipt,'payments'=>$p->fetchAll(PDO::FETCH_ASSOC),'items'=>$items->fetchAll(PDO::FETCH_ASSOC)]);}
 if($action==='export_receipts'){$result=fcReceipts($pdo,$scope,array_merge($_GET,['page'=>1,'per_page'=>500]));while(ob_get_level()>0)ob_end_clean();header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="fee-receipts-'.date('Ymd-His').'.csv"');$out=fopen('php://output','wb');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,['Receipt No','Date','Student','Admission No','Gross','Discount','Paid','Due','Payment Mode','Status']);foreach($result['records'] as $r)fputcsv($out,[$r['receipt_no'],$r['receipt_date'],$r['student_name'],$r['admission_no'],$r['gross_amount'],$r['discount_amount'],$r['paid_amount'],$r['due_amount'],$r['payment_methods'],$r['payment_status']]);fclose($out);exit;}
 if($action==='print_receipt'||$action==='pdf_receipt'){
    $id=(int)($_GET['id']??0);
    $receipt=fcReceipt($pdo,$scope,$id);
    $lines=fcReceiptPrintLines($pdo,$scope,$id);

    if($action==='pdf_receipt'){
        /*
         * The browser-print receipt is the canonical template because it keeps
         * the supplied A4 design, school logo and live database values intact.
         * This legacy lightweight PDF endpoint remains available for existing
         * links; Save & Print uses print_receipt below.
         */
        $summary=[
            'Receipt: '.$receipt['receipt_no'],
            'Date: '.$receipt['receipt_date_display'],
            'Student: '.$receipt['student_name'],
            'Admission No: '.$receipt['admission_no'],
        ];
        foreach($lines as $line){
            if((float)($line['paid_amount']??0)<=0)continue;
            $summary[]=(string)$line['item_name'].' - '.((string)($line['period_label']??'')?:'-')
                .' | Paid Rs. '.number_format((float)$line['paid_amount'],2);
        }
        $summary[]='Grand Total: Rs. '.number_format((float)$receipt['paid_amount'],2);
        $summary[]='Status: '.ucfirst((string)$receipt['payment_status']);
        while(ob_get_level()>0)ob_end_clean();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="'.$receipt['receipt_no'].'.pdf"');
        echo fcPdf('Fee Payment Receipt',$summary);
        exit;
    }

    while(ob_get_level()>0)ob_end_clean();
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    $autoPrint=((string)($_GET['autoprint']??'0'))==='1';
    $returnUrl=trim((string)($_GET['return']??''));
    if($returnUrl===''){
        $returnUrl=function_exists('app_url')
            ?app_url('school/fee-collection.php')
            :'../school/fee-collection.php';
    }

    echo fcRenderReceiptHtml($pdo,$scope,$receipt,$lines,$autoPrint,$returnUrl);
    exit;
}

 if($action==='collect'){
    fcCsrf($input);$studentId=(int)($input['student_id']??0);$yearId=(int)($input['academic_year_id']??0);$paymentDate=fcValidDate((string)($input['payment_date']??date('Y-m-d')),'Payment Date');$paidAmount=round((float)($input['paid_amount']??0),2);$discount=round(max(0,(float)($input['discount_amount']??0)),2);if($studentId<=0||$yearId<=0)throw new InvalidArgumentException('Student and Academic Year are required.');if($paidAmount<=0)throw new InvalidArgumentException('Paid Amount must be greater than zero.');$method=fcMethod($pdo,$scope['tenant_id'],(int)($input['payment_method_id']??0));$reference=mb_substr(trim((string)($input['reference_no']??'')),0,100);$remarks=mb_substr(trim((string)($input['remarks']??'')),0,255);$additional=$input['additional_charges']??[];if(!is_array($additional))throw new InvalidArgumentException('Additional Charge is invalid.');if(count($additional)>1)throw new InvalidArgumentException('Only one Charge Type can be added per Fee Collection.');
    $pdo->beginTransaction();$assignment=fcAssignment($pdo,$scope,$studentId,$yearId,true);fcEnsureAssignmentSchedule($pdo,$assignment,$paymentDate);$due=fcItemRows($pdo,$scope['tenant_id'],$studentId,$paymentDate,(int)$assignment['id'],true);$baseItems=$due['items'];
    $charges=[];$additionalTotal=0.0;$fineTotal=0.0;
    foreach($additional as $index=>$charge){
        if(!is_array($charge))continue;
        $amount=round((float)($charge['amount']??0),2);
        if($amount<=0)continue;

        $typedName=mb_substr(trim(preg_replace('/\s+/u',' ',(string)($charge['charge_type_name']??''))??''),0,120);
        if($typedName===''){
            throw new InvalidArgumentException('Charge Type is required when an Additional Charge amount is entered.');
        }

        $chargeKey=strtolower(trim((string)($charge['charge_key']??'')));
        $type=null;

        if(preg_match('/^fee:(\d+)$/',$chargeKey,$match)&&fcTable($pdo,'fee_types')){
            $conditions=['id=:id','tenant_id=:tenant_id'];
            if(fcColumn($pdo,'fee_types','is_enabled'))$conditions[]='is_enabled=1';
            if(fcColumn($pdo,'fee_types','deleted_at'))$conditions[]='deleted_at IS NULL';
            $codeExpr=fcColumn($pdo,'fee_types','fee_type_code')?"COALESCE(fee_type_code,'')":"''";
            $feeType=$pdo->prepare(
                "SELECT id,$codeExpr charge_code,fee_type_name charge_name
                 FROM fee_types
                 WHERE ".implode(' AND ',$conditions)."
                 LIMIT 1"
            );
            $feeType->execute(['id'=>(int)$match[1],'tenant_id'=>$scope['tenant_id']]);
            $type=$feeType->fetch(PDO::FETCH_ASSOC)?:null;
        }elseif(preg_match('/^legacy:(\d+)$/',$chargeKey,$match)&&fcTable($pdo,'fee_additional_charge_types')){
            $legacy=$pdo->prepare(
                "SELECT id,charge_code,charge_name
                 FROM fee_additional_charge_types
                 WHERE id=:id
                   AND tenant_id=:tenant_id
                   AND status='active'
                 LIMIT 1"
            );
            $legacy->execute(['id'=>(int)$match[1],'tenant_id'=>$scope['tenant_id']]);
            $type=$legacy->fetch(PDO::FETCH_ASSOC)?:null;
        }

        /*
         * A typed value that does not exist in Fee Settings is valid by design.
         * It is saved directly on this collection/receipt without creating
         * another permanent settings record.
         */
        if(!$type){
            $type=[
                'id'=>0,
                'charge_code'=>'CUSTOM',
                'charge_name'=>$typedName,
            ];
        }

        $description=mb_substr(trim((string)($charge['description']??'')),0,120);
        $baseName=trim((string)($type['charge_name']??$typedName));
        if($baseName==='')$baseName=$typedName;
        $name=$baseName.($description!==''?' - '.$description:'');

        $charges[]=[
            'type'=>$type,
            'name'=>$name,
            'amount'=>$amount,
            'index'=>$index,
        ];
        $additionalTotal+=$amount;
        if(strtoupper((string)($type['charge_code']??''))==='FINE')$fineTotal+=$amount;
    }
    $baseTotal=0.0;
    foreach($baseItems as $item)$baseTotal+=(float)$item['balance_amount'];

    $assignmentBalance=round((float)($assignment['balance_amount']??0),2);
    if($baseItems&&abs($baseTotal-$assignmentBalance)>0.01){
        fcAggregate($pdo,$scope['tenant_id'],(int)$assignment['id']);
        $assignment=fcAssignment($pdo,$scope,$studentId,$yearId,true);
        $due=fcItemRows($pdo,$scope['tenant_id'],$studentId,$paymentDate,(int)$assignment['id'],true);
        $baseItems=$due['items'];
        $baseTotal=0.0;
        foreach($baseItems as $item)$baseTotal+=(float)$item['balance_amount'];
    }

    $gross=round($baseTotal+$additionalTotal,2);if($gross<=0)throw new InvalidArgumentException('There is no payable fee for the selected date.');if($discount>$gross)throw new InvalidArgumentException('Discount cannot exceed Total Payable.');$grand=round($gross-$discount,2);if($paidAmount>$grand+0.01)throw new InvalidArgumentException('Paid Amount cannot exceed Grand Total.');$receiptNo=fcReceiptNo($pdo,$scope['tenant_id']);$status=$paidAmount+0.01>=$grand?'paid':'partial';
    $receipt=$pdo->prepare("INSERT INTO fee_receipts(tenant_id,branch_id,receipt_no,student_id,academic_year_id,receipt_date,gross_amount,discount_amount,fine_amount,paid_amount,payment_status,collected_by,notes) VALUES(:tenant_id,:branch_id,:receipt_no,:student_id,:year_id,:receipt_date,:gross,:discount,:fine,:paid,:status,:user_id,:notes)");$receipt->execute(['tenant_id'=>$scope['tenant_id'],'branch_id'=>$assignment['branch_id'],'receipt_no'=>$receiptNo,'student_id'=>$studentId,'year_id'=>$yearId,'receipt_date'=>$paymentDate.' '.date('H:i:s'),'gross'=>$gross,'discount'=>$discount,'fine'=>$fineTotal,'paid'=>$paidAmount,'status'=>$status,'user_id'=>$scope['user_id'],'notes'=>$remarks!==''?$remarks:null]);$receiptId=(int)$pdo->lastInsertId();
    $studentItemsHaveFeeHead=fcColumn($pdo,'student_fee_items','fee_head_id');
    $receiptItemsHaveFeeHead=fcColumn($pdo,'fee_receipt_items','fee_head_id');
    $fallbackFeeHeadId=($studentItemsHaveFeeHead||$receiptItemsHaveFeeHead)
        ?fcRequireFeeHeadId($pdo,$scope['tenant_id'])
        :0;

    $additionalSql=$studentItemsHaveFeeHead
        ?"INSERT INTO student_fee_items(
            tenant_id,assignment_id,student_id,academic_year_id,
            fee_head_id,source_receipt_id,item_type,item_name,period_key,
            period_label,due_date,original_amount,discount_amount,paid_amount,
            balance_amount,item_status
          ) VALUES(
            :tenant_id,:assignment_id,:student_id,:year_id,
            :fee_head_id,:receipt_id,'additional',:name,:period_key,
            :period_label,:due_date,:amount,0,0,:balance,'unpaid'
          )"
        :"INSERT INTO student_fee_items(
            tenant_id,assignment_id,student_id,academic_year_id,
            source_receipt_id,item_type,item_name,period_key,
            period_label,due_date,original_amount,discount_amount,paid_amount,
            balance_amount,item_status
          ) VALUES(
            :tenant_id,:assignment_id,:student_id,:year_id,
            :receipt_id,'additional',:name,:period_key,
            :period_label,:due_date,:amount,0,0,:balance,'unpaid'
          )";
    $insertAdditional=$pdo->prepare($additionalSql);

    foreach($charges as $line){
        $additionalParams=[
            'tenant_id'=>$scope['tenant_id'],
            'assignment_id'=>$assignment['id'],
            'student_id'=>$studentId,
            'year_id'=>$yearId,
            'receipt_id'=>$receiptId,
            'name'=>$line['name'],
            'period_key'=>'ADD-'.$receiptId.'-'.$line['index'],
            'period_label'=>'Additional Charge',
            'due_date'=>$paymentDate,
            'amount'=>$line['amount'],
            'balance'=>$line['amount'],
        ];
        if($studentItemsHaveFeeHead){
            $additionalParams['fee_head_id']=$fallbackFeeHeadId;
        }
        $insertAdditional->execute($additionalParams);
        $id=(int)$pdo->lastInsertId();
        $baseItems[]=[
            'id'=>$id,
            'assignment_id'=>$assignment['id'],
            'fee_head_id'=>$fallbackFeeHeadId,
            'item_type'=>'additional',
            'item_name'=>$line['name'],
            'period_label'=>'Additional Charge',
            'due_date'=>$paymentDate,
            'balance_amount'=>$line['amount'],
        ];
    }

    usort(
        $baseItems,
        fn($a,$b)=>strcmp((string)$a['due_date'],(string)$b['due_date'])
            ?:((int)$a['id']<=>(int)$b['id'])
    );

    $discountRemaining=$discount;
    $paidRemaining=$paidAmount;
    $updateItem=$pdo->prepare(
        "UPDATE student_fee_items
         SET discount_amount=discount_amount+:discount,
             paid_amount=paid_amount+:paid,
             balance_amount=:balance,
             item_status=:status
         WHERE id=:id
           AND tenant_id=:tenant_id"
    );

    /*
     * Build the receipt-item INSERT from the real database schema.
     *
     * Your fee_receipt_items table contains two legacy required fields:
     * fee_head_id and amount. Both are added only when the columns exist,
     * so this remains compatible with newer installations too.
     */
    $receiptItemsHaveAmount=fcColumn($pdo,'fee_receipt_items','amount');

    $receiptItemColumns=[
        'tenant_id',
        'receipt_id',
        'student_fee_item_id',
    ];
    $receiptItemValues=[
        ':tenant_id',
        ':receipt_id',
        ':fee_item_id',
    ];

    if($receiptItemsHaveFeeHead){
        $receiptItemColumns[]='fee_head_id';
        $receiptItemValues[]=':fee_head_id';
    }

    $receiptItemColumns=[
        ...$receiptItemColumns,
        'item_type',
        'item_name',
        'period_label',
        'gross_amount',
        'discount_amount',
        'paid_amount',
        'balance_after',
    ];
    $receiptItemValues=[
        ...$receiptItemValues,
        ':item_type',
        ':item_name',
        ':period_label',
        ':gross',
        ':discount',
        ':paid',
        ':balance',
    ];

    if($receiptItemsHaveAmount){
        $receiptItemColumns[]='amount';
        $receiptItemValues[]=':amount';
    }

    $receiptItemSql=
        'INSERT INTO fee_receipt_items('.
        implode(',',$receiptItemColumns).
        ') VALUES('.
        implode(',',$receiptItemValues).
        ')';

    $insertReceiptItem=$pdo->prepare($receiptItemSql);
    $assignmentIds=[];
    foreach($baseItems as $item){$opening=round((float)$item['balance_amount'],2);if($opening<=0)continue;$lineDiscount=min($discountRemaining,$opening);$afterDiscount=round($opening-$lineDiscount,2);$discountRemaining=round($discountRemaining-$lineDiscount,2);$linePaid=min($paidRemaining,$afterDiscount);$balance=round($afterDiscount-$linePaid,2);$paidRemaining=round($paidRemaining-$linePaid,2);$itemStatus=$balance<=0.009?($linePaid>0?'paid':'waived'):($linePaid>0?'partial':'unpaid');$updateItem->execute(['discount'=>$lineDiscount,'paid'=>$linePaid,'balance'=>$balance,'status'=>$itemStatus,'id'=>$item['id'],'tenant_id'=>$scope['tenant_id']]);$receiptItemParams=[
        'tenant_id'=>$scope['tenant_id'],
        'receipt_id'=>$receiptId,
        'fee_item_id'=>$item['id'],
        'item_type'=>$item['item_type'],
        'item_name'=>$item['item_name'],
        'period_label'=>$item['period_label'],
        'gross'=>$opening,
        'discount'=>$lineDiscount,
        'paid'=>$linePaid,
        'balance'=>$balance,
    ];

    /*
     * In your database, fee_receipt_items.amount is NOT NULL.
     * Store the amount allocated/paid against this particular fee item.
     */
    if($receiptItemsHaveAmount){
        $receiptItemParams['amount']=$linePaid;
    }

    if($receiptItemsHaveFeeHead){
        $resolvedFeeHeadId=(int)($item['fee_head_id']??0);

        /*
         * Query student_fee_items.fee_head_id only when that legacy column
         * really exists. Previously this SELECT was executed whenever
         * fee_receipt_items had fee_head_id, even if student_fee_items did not,
         * causing MySQL error 1054: Unknown column 'fee_head_id'.
         */
        if($resolvedFeeHeadId<=0&&$studentItemsHaveFeeHead){
            $feeItemHead=$pdo->prepare(
                "SELECT fee_head_id
                 FROM student_fee_items
                 WHERE id=:id
                   AND tenant_id=:tenant_id
                 LIMIT 1"
            );
            $feeItemHead->execute([
                'id'=>$item['id'],
                'tenant_id'=>$scope['tenant_id'],
            ]);
            $resolvedFeeHeadId=(int)($feeItemHead->fetchColumn()?:0);
        }

        $receiptItemParams['fee_head_id']=$resolvedFeeHeadId>0
            ?$resolvedFeeHeadId
            :$fallbackFeeHeadId;
    }

    $insertReceiptItem->execute($receiptItemParams);
    $assignmentIds[(int)$item['assignment_id']]=true;
}
    if($paidRemaining>0.01)throw new RuntimeException('Payment allocation failed. No data was saved.');
    $paymentsHaveFeeHead=fcColumn($pdo,'fee_payments','fee_head_id');
    $paymentSql=$paymentsHaveFeeHead
        ?"INSERT INTO fee_payments(
            tenant_id,receipt_id,payment_method_id,fee_head_id,
            amount,reference_no,paid_at,status
          ) VALUES(
            :tenant_id,:receipt_id,:method_id,:fee_head_id,
            :amount,:reference,:paid_at,'success'
          )"
        :"INSERT INTO fee_payments(
            tenant_id,receipt_id,payment_method_id,
            amount,reference_no,paid_at,status
          ) VALUES(
            :tenant_id,:receipt_id,:method_id,
            :amount,:reference,:paid_at,'success'
          )";
    $payment=$pdo->prepare($paymentSql);
    $paymentParams=[
        'tenant_id'=>$scope['tenant_id'],
        'receipt_id'=>$receiptId,
        'method_id'=>$method['id'],
        'amount'=>$paidAmount,
        'reference'=>$reference!==''?$reference:null,
        'paid_at'=>$paymentDate.' '.date('H:i:s'),
    ];
    if($paymentsHaveFeeHead){
        $paymentParams['fee_head_id']=$fallbackFeeHeadId;
    }
    $payment->execute($paymentParams);

    foreach(array_keys($assignmentIds) as $assignmentId){
        fcAggregate($pdo,$scope['tenant_id'],$assignmentId);
    }

    fcSyncReceiptIncome($pdo,$scope,$receiptId,false);

    if(!$pdo->inTransaction()){
        throw new RuntimeException('Fee transaction was closed unexpectedly before completion.');
    }
    $pdo->commit();
    fcOut(
        true,
        'Fee collected successfully. Receipt: '.$receiptNo,
        ['receipt_id'=>$receiptId,'receipt_no'=>$receiptNo]
    );
 }
 if($action==='update_receipt'){fcCsrf($input);$id=(int)($input['id']??0);$method=fcMethod($pdo,$scope['tenant_id'],(int)($input['payment_method_id']??0));$reference=mb_substr(trim((string)($input['reference_no']??'')),0,100);$remarks=mb_substr(trim((string)($input['remarks']??'')),0,255);$receipt=fcReceipt($pdo,$scope,$id);if($receipt['payment_status']==='reversed')throw new InvalidArgumentException('Reversed Receipt cannot be edited.');$payments=$pdo->prepare("SELECT id FROM fee_payments WHERE tenant_id=:tenant_id AND receipt_id=:receipt_id AND status='success' ORDER BY id");$payments->execute(['tenant_id'=>$scope['tenant_id'],'receipt_id'=>$id]);$ids=$payments->fetchAll(PDO::FETCH_COLUMN);if(count($ids)!==1)throw new InvalidArgumentException('Only single-mode Receipts can be edited.');$pdo->beginTransaction();$pdo->prepare("UPDATE fee_receipts SET notes=:notes WHERE id=:id AND tenant_id=:tenant_id")->execute(['notes'=>$remarks!==''?$remarks:null,'id'=>$id,'tenant_id'=>$scope['tenant_id']]);$pdo->prepare("UPDATE fee_payments SET payment_method_id=:method_id,reference_no=:reference WHERE id=:id AND tenant_id=:tenant_id")->execute(['method_id'=>$method['id'],'reference'=>$reference!==''?$reference:null,'id'=>$ids[0],'tenant_id'=>$scope['tenant_id']]);fcSyncReceiptIncome($pdo,$scope,$id,false);$pdo->commit();fcOut(true,'Receipt updated successfully.');}
 if($action==='reverse'){
    fcCsrf($input);$id=(int)($input['id']??0);$pdo->beginTransaction();$receipt=fcReceipt($pdo,$scope,$id);if($receipt['payment_status']==='reversed')throw new InvalidArgumentException('Receipt is already reversed.');$items=$pdo->prepare("SELECT fri.*,sfi.assignment_id,sfi.original_amount,sfi.discount_amount current_discount,sfi.paid_amount current_paid FROM fee_receipt_items fri INNER JOIN student_fee_items sfi ON sfi.id=fri.student_fee_item_id AND sfi.tenant_id=fri.tenant_id WHERE fri.tenant_id=:tenant_id AND fri.receipt_id=:receipt_id FOR UPDATE");$items->execute(['tenant_id'=>$scope['tenant_id'],'receipt_id'=>$id]);$rows=$items->fetchAll(PDO::FETCH_ASSOC);$update=$pdo->prepare("UPDATE student_fee_items SET discount_amount=:discount,paid_amount=:paid,balance_amount=:balance,item_status=:status WHERE id=:id AND tenant_id=:tenant_id");$assignmentIds=[];foreach($rows as $row){$discount=max(0,(float)$row['current_discount']-(float)$row['discount_amount']);$paid=max(0,(float)$row['current_paid']-(float)$row['paid_amount']);$balance=max(0,(float)$row['original_amount']-$discount-$paid);$status=$balance<=0.009?($paid>0?'paid':'waived'):($paid>0?'partial':'unpaid');$update->execute(['discount'=>$discount,'paid'=>$paid,'balance'=>$balance,'status'=>$status,'id'=>$row['student_fee_item_id'],'tenant_id'=>$scope['tenant_id']]);$assignmentIds[(int)$row['assignment_id']]=true;}$pdo->prepare("UPDATE fee_payments SET status='reversed' WHERE tenant_id=:tenant_id AND receipt_id=:receipt_id")->execute(['tenant_id'=>$scope['tenant_id'],'receipt_id'=>$id]);$pdo->prepare("UPDATE fee_receipts SET payment_status='reversed' WHERE id=:id AND tenant_id=:tenant_id")->execute(['id'=>$id,'tenant_id'=>$scope['tenant_id']]);foreach(array_keys($assignmentIds) as $assignmentId)fcAggregate($pdo,$scope['tenant_id'],$assignmentId);fcSyncReceiptIncome($pdo,$scope,$id,true);$pdo->commit();fcOut(true,'Receipt reversed and linked Income entry cancelled.');
 }
 if($action==='delete'){fcCsrf($input);$id=(int)($input['id']??0);$receipt=fcReceipt($pdo,$scope,$id);if($receipt['payment_status']!=='reversed')throw new InvalidArgumentException('Only a reversed Receipt can be deleted.');$pdo->beginTransaction();$pdo->prepare("DELETE FROM fee_payments WHERE tenant_id=:tenant_id AND receipt_id=:receipt_id")->execute(['tenant_id'=>$scope['tenant_id'],'receipt_id'=>$id]);$pdo->prepare("DELETE FROM fee_receipt_items WHERE tenant_id=:tenant_id AND receipt_id=:receipt_id")->execute(['tenant_id'=>$scope['tenant_id'],'receipt_id'=>$id]);if(fcTable($pdo,'income_transactions')){$pdo->prepare("DELETE FROM income_transactions WHERE tenant_id=:tenant_id AND source_module='fee_collection' AND source_record_id=:receipt_id")->execute(['tenant_id'=>$scope['tenant_id'],'receipt_id'=>$id]);}$pdo->prepare("DELETE FROM fee_receipts WHERE tenant_id=:tenant_id AND id=:id")->execute(['tenant_id'=>$scope['tenant_id'],'id'=>$id]);$pdo->commit();fcOut(true,'Reversed Receipt deleted successfully.');}
 fcOut(false,'Invalid Fee Collection action.',[],400);
}catch(InvalidArgumentException $e){if($pdo->inTransaction())$pdo->rollBack();fcOut(false,$e->getMessage(),[],422);}catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        error_log(
            'fee-collection.php action='.(string)($action??'unknown').
            ' line='.$e->getLine().
            ' error='.$e->getMessage()
        );
        $host=strtolower((string)($_SERVER['HTTP_HOST']??''));
        fcOut(
            false,
            (str_contains($host,'localhost')||str_contains($host,'127.0.0.1'))
                ?'Fee Collection request failed ['.(string)($action??'unknown').'] ['.FEE_COLLECTION_BUILD.']: '.$e->getMessage()
                :'Unable to complete Fee Collection request.',
            [],
            500
        );
    }

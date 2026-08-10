<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors','0');
error_reporting(E_ALL);
require_once dirname(__DIR__).'/includes/bootstrap.php';
const INCOME_INTEGRATION_BUILD='2026-08-04-audit-history-v5';

function inOut(bool $ok,string $message='',array $data=[],int $status=200):never{
    while(ob_get_level()>0)ob_end_clean();
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control:no-store');
    echo json_encode(['success'=>$ok,'message'=>$message,'data'=>$data],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function inInput():array{$d=json_decode((string)file_get_contents('php://input'),true);return is_array($d)?$d:$_POST;}
function inScope():array{
    $u=function_exists('current_user')?current_user():[];
    $u=is_array($u)?$u:[];
    return [
        'tenant_id'=>(int)($u['tenant_id']??$u['school_id']??$_SESSION['tenant_id']??0),
        'branch_id'=>(int)($u['branch_id']??$_SESSION['branch_id']??0),
        'user_id'=>(int)($u['id']??$u['user_id']??$_SESSION['user_id']??0),
        'role_id'=>(int)($u['role_id']??$_SESSION['role_id']??0),
        'role'=>(string)(
            $u['role_key']??$u['role']??$u['role_name']??$u['user_role']??
            $_SESSION['role_key']??$_SESSION['role']??$_SESSION['role_name']??$_SESSION['user_role']??''
        ),
    ];
}
function inCsrf(array $i):void{$s=(string)($_SESSION['income_csrf_token']??'');$r=(string)($i['csrf_token']??'');if($s===''||$r===''||!hash_equals($s,$r))inOut(false,'Invalid or expired CSRF token. Refresh the page.',[],419);}
function inName(string $a='s'):string{return "TRIM(CONCAT(COALESCE($a.first_name,''),CASE WHEN COALESCE($a.last_name,'')='' THEN '' ELSE CONCAT(' ',$a.last_name) END))";}
function inCan(array $s,string $action):bool
{
    $roleRaw=strtolower(trim((string)($s['role']??'')));
    $role=preg_replace('/[^a-z0-9]+/','_',$roleRaw)??'';
    $role=trim($role,'_');
    $roleId=(int)($s['role_id']??0);

    // Super Admin and School Admin roles must have full Income access.
    if(in_array($roleId,[1,4],true) || in_array($role,[
        'super_admin','superadministrator','super_administrator',
        'administrator','admin','school_admin','schooladministrator',
        'school_administrator','schooladmin'
    ],true)){
        return true;
    }

    // Check all permission keys used by the existing School ERP.
    if(function_exists('has_permission')){
        foreach([
            'income_management','income','school_income',
            'accounts_income','accounts_management'
        ] as $permission){
            try{
                if((bool)has_permission($permission,$action))return true;
            }catch(Throwable $e){
                error_log('Income permission check '.$permission.'/'.$action.': '.$e->getMessage());
            }
        }
    }

    // Compatibility with projects exposing separate permission helpers.
    $helperMap=[
        'view'=>['can_view'],
        'create'=>['can_create'],
        'edit'=>['can_edit','can_update'],
        'delete'=>['can_delete'],
        'import'=>['can_import','can_create'],
        'export'=>['can_export','can_view'],
        'print'=>['can_print','can_view'],
    ];
    foreach($helperMap[$action]??[] as $fn){
        if(!function_exists($fn))continue;
        foreach(['income_management','school_income','accounts_management'] as $permission){
            try{
                if((bool)$fn($permission))return true;
            }catch(Throwable $e){
                // Some installations require different helper signatures.
            }
        }
    }

    return false;
}
function inExists(PDO $pdo,string $table):bool{
    $q=$pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:t");
    $q->execute(['t'=>$table]);return(int)$q->fetchColumn()>0;
}
function inColumn(PDO $pdo,string $table,string $column):bool{
    $q=$pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema=DATABASE()
           AND table_name=:table_name
           AND column_name=:column_name"
    );
    $q->execute(['table_name'=>$table,'column_name'=>$column]);
    return (int)$q->fetchColumn()>0;
}
function inIndex(PDO $pdo,string $table,string $index):bool{
    $q=$pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.statistics
         WHERE table_schema=DATABASE()
           AND table_name=:table_name
           AND index_name=:index_name"
    );
    $q->execute(['table_name'=>$table,'index_name'=>$index]);
    return (int)$q->fetchColumn()>0;
}
function inEnsureColumn(PDO $pdo,string $table,string $column,string $definition):void{
    if(!inColumn($pdo,$table,$column)){
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}
function inEnsure(PDO $pdo):void{
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
      KEY idx_income_purpose(tenant_id,purpose_id),
      KEY idx_income_student(tenant_id,student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS income_audit_logs(
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      tenant_id BIGINT UNSIGNED NOT NULL,
      branch_id BIGINT UNSIGNED NULL,
      income_id BIGINT UNSIGNED NULL,
      action_name VARCHAR(60) NOT NULL,
      user_id BIGINT UNSIGNED NULL,
      details_json JSON NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY(id),
      KEY idx_income_audit(tenant_id,created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    /*
     * CREATE TABLE IF NOT EXISTS does not upgrade an existing legacy table.
     * Add all Fee Collection integration columns conditionally.
     */
    inEnsureColumn($pdo,'income_transactions','student_id','BIGINT UNSIGNED NULL');
    inEnsureColumn($pdo,'income_transactions','academic_year_id','BIGINT UNSIGNED NULL');
    inEnsureColumn($pdo,'income_transactions','receipt_id','BIGINT UNSIGNED NULL');
    inEnsureColumn($pdo,'income_transactions','source_module','VARCHAR(80) NULL');
    inEnsureColumn($pdo,'income_transactions','source_record_id','BIGINT UNSIGNED NULL');
    inEnsureColumn($pdo,'income_transactions','updated_by','BIGINT UNSIGNED NULL');
    inEnsureColumn($pdo,'income_transactions','description','VARCHAR(500) NULL');

    if(!inIndex($pdo,'income_transactions','idx_income_source')){
        $pdo->exec(
            "ALTER TABLE income_transactions
             ADD KEY idx_income_source(tenant_id,source_module,source_record_id)"
        );
    }
    if(!inIndex($pdo,'income_transactions','idx_income_receipt')){
        $pdo->exec(
            "ALTER TABLE income_transactions
             ADD KEY idx_income_receipt(tenant_id,receipt_id)"
        );
    }
}
function inSeed(PDO $pdo,int $tenant):void{
    $items=[
      ['student_fee','Student Fee Income'],['admission_fee','Admission Fee Income'],['transport_fee','Transport Fee Income'],
      ['hostel_fee','Hostel Fee Income'],['examination_fee','Examination Fee Income'],['library_fine','Library Fine Income'],
      ['donation','Donation Income'],['government_grant','Government Grant Income'],['scholarship_reimbursement','Scholarship Reimbursement'],
      ['miscellaneous','Miscellaneous Income'],['other','Other Income']
    ];
    $q=$pdo->prepare("INSERT INTO income_purposes(tenant_id,purpose_code,purpose_name,is_system,status) VALUES(:t,:c,:n,1,'active') ON DUPLICATE KEY UPDATE purpose_name=VALUES(purpose_name),status='active'");
    foreach($items as [$c,$n])$q->execute(['t'=>$tenant,'c'=>$c,'n'=>$n]);
}
function inAudit(PDO $pdo,array $s,?int $id,string $action,array $details=[]):void{
    $q=$pdo->prepare("INSERT INTO income_audit_logs(tenant_id,branch_id,income_id,action_name,user_id,details_json) VALUES(:t,:b,:i,:a,:u,:d)");
    $q->execute(['t'=>$s['tenant_id'],'b'=>$s['branch_id']?:null,'i'=>$id,'a'=>$action,'u'=>$s['user_id']?:null,'d'=>json_encode($details)]);
}

function inBackfillAudit(PDO $pdo,array $s):void{
    /*
     * Create one initial audit event for income rows that existed before
     * Audit History integration. NOT EXISTS prevents duplicate backfill rows.
     */
    $q=$pdo->prepare(
        "INSERT INTO income_audit_logs(
            tenant_id,branch_id,income_id,action_name,user_id,details_json,created_at
         )
         SELECT
            i.tenant_id,
            i.branch_id,
            i.id,
            CASE
                WHEN i.source_module='fee_collection'
                    THEN 'fee_income_synchronized'
                ELSE 'income_recorded'
            END,
            COALESCE(i.created_by,:user_id),
            JSON_OBJECT(
                'source',COALESCE(i.source_module,'manual_income'),
                'amount',i.amount,
                'status',i.income_status,
                'reference',COALESCE(i.reference_no,'')
            ),
            COALESCE(i.created_at,CURRENT_TIMESTAMP)
         FROM income_transactions i
         WHERE i.tenant_id=:tenant_id
           AND (:branch_scope=0 OR i.branch_id=:branch_id)
           AND NOT EXISTS(
               SELECT 1
               FROM income_audit_logs a
               WHERE a.tenant_id=i.tenant_id
                 AND a.income_id=i.id
           )"
    );
    $q->execute([
        'user_id'=>$s['user_id']?:null,
        'tenant_id'=>$s['tenant_id'],
        'branch_scope'=>$s['branch_id'],
        'branch_id'=>$s['branch_id'],
    ]);
}
function inNo(PDO $pdo,int $tenant):string{
    for($x=0;$x<20;$x++){
        $no='INC-'.date('Ymd').'-'.str_pad((string)random_int(1,999999),6,'0',STR_PAD_LEFT);
        $q=$pdo->prepare("SELECT COUNT(*) FROM income_transactions WHERE tenant_id=:t AND income_no=:n");
        $q->execute(['t'=>$tenant,'n'=>$no]);
        if(!(int)$q->fetchColumn())return$no;
    }
    throw new RuntimeException('Unable to generate income number.');
}

function inPurposeId(PDO $pdo,int $tenant,string $code,string $name):int{
    $q=$pdo->prepare(
        "INSERT INTO income_purposes(
            tenant_id,purpose_code,purpose_name,is_system,status
         ) VALUES(:t,:c,:n,1,'active')
         ON DUPLICATE KEY UPDATE purpose_name=VALUES(purpose_name),status='active'"
    );
    $q->execute(['t'=>$tenant,'c'=>$code,'n'=>$name]);
    $q=$pdo->prepare(
        "SELECT id FROM income_purposes
         WHERE tenant_id=:t AND purpose_code=:c LIMIT 1"
    );
    $q->execute(['t'=>$tenant,'c'=>$code]);
    return (int)$q->fetchColumn();
}

function inFeePurpose(PDO $pdo,int $tenant,int $receiptId):array{
    if(!inExists($pdo,'fee_receipt_items'))return['student_fee','Student Fee Income'];
    $q=$pdo->prepare(
        "SELECT item_type,COALESCE(SUM(paid_amount),0) total
         FROM fee_receipt_items
         WHERE tenant_id=:t AND receipt_id=:r
         GROUP BY item_type ORDER BY total DESC"
    );
    $q->execute(['t'=>$tenant,'r'=>$receiptId]);
    $types=$q->fetchAll(PDO::FETCH_ASSOC);
    if(count($types)!==1)return['student_fee','Student Fee Income'];
    return match(strtolower((string)$types[0]['item_type'])){
        'admission'=>['admission_fee','Admission Fee Income'],
        'transport'=>['transport_fee','Transport Fee Income'],
        'term'=>['examination_fee','Examination Fee Income'],
        'additional'=>['other','Other Income'],
        default=>['student_fee','Student Fee Income'],
    };
}

function inSyncFeeReceipts(PDO $pdo,array $s):void{
    if(!inExists($pdo,'fee_receipts')||!inExists($pdo,'fee_payments'))return;

    $q=$pdo->prepare(
        "SELECT r.id,r.branch_id,r.receipt_no,r.student_id,r.academic_year_id,
                r.receipt_date,r.paid_amount,r.payment_status,
                TRIM(CONCAT(COALESCE(st.first_name,''),
                    CASE WHEN COALESCE(st.last_name,'')='' THEN ''
                         ELSE CONCAT(' ',st.last_name) END)) student_name,
                fp.payment_method_id,fp.reference_no payment_reference
         FROM fee_receipts r
         INNER JOIN students st
            ON st.id=r.student_id AND st.tenant_id=r.tenant_id
         LEFT JOIN fee_payments fp
            ON fp.receipt_id=r.id
           AND fp.tenant_id=r.tenant_id
           AND fp.status='success'
         WHERE r.tenant_id=:t
           AND (:bs=0 OR r.branch_id=:b)
         ORDER BY r.id"
    );
    $q->execute(['t'=>$s['tenant_id'],'bs'=>$s['branch_id'],'b'=>$s['branch_id']]);
    $receipts=$q->fetchAll(PDO::FETCH_ASSOC);

    foreach($receipts as $r){
        [$code,$name]=inFeePurpose($pdo,$s['tenant_id'],(int)$r['id']);
        $purpose=inPurposeId($pdo,$s['tenant_id'],$code,$name);
        $status=$r['payment_status']==='reversed'?'cancelled':'received';

        $find=$pdo->prepare(
            "SELECT id FROM income_transactions
             WHERE tenant_id=:t AND
             ((source_module='fee_collection' AND source_record_id=:rid)
              OR receipt_id=:rid2)
             ORDER BY id LIMIT 1"
        );
        $find->execute(['t'=>$s['tenant_id'],'rid'=>$r['id'],'rid2'=>$r['id']]);
        $id=(int)($find->fetchColumn()?:0);

        $commonParams=[
            'b'=>(int)$r['branch_id']?:null,
            'p'=>$purpose,
            'd'=>substr((string)$r['receipt_date'],0,10),
            'a'=>(float)$r['paid_amount'],
            'pm'=>(int)$r['payment_method_id']?:null,
            'payer'=>$r['student_name'],
            'ref'=>$r['payment_reference']?:$r['receipt_no'],
            'status'=>$status,
            'st'=>(int)$r['student_id'],
            'y'=>(int)$r['academic_year_id'],
            'receipt'=>(int)$r['id'],
            'descr'=>'Automatically recorded from Fee Collection receipt '.$r['receipt_no'].'.',
            'source'=>(int)$r['id'],
            't'=>$s['tenant_id'],
        ];

        if($id>0){
            $updateParams=$commonParams;
            $updateParams['updated_by']=$s['user_id']?:null;
            $updateParams['id']=$id;

            $pdo->prepare(
                "UPDATE income_transactions SET
                    branch_id=:b,
                    purpose_id=:p,
                    income_date=:d,
                    amount=:a,
                    payment_mode_id=:pm,
                    payer_name=:payer,
                    reference_no=:ref,
                    income_status=:status,
                    student_id=:st,
                    academic_year_id=:y,
                    receipt_id=:receipt,
                    description=:descr,
                    source_module='fee_collection',
                    source_record_id=:source,
                    updated_by=:updated_by
                 WHERE id=:id
                   AND tenant_id=:t"
            )->execute($updateParams);
        }else{
            $insertParams=$commonParams;
            $insertParams['no']=inNo($pdo,$s['tenant_id']);
            $insertParams['created_by']=$s['user_id']?:null;
            $insertParams['updated_by']=$s['user_id']?:null;

            $pdo->prepare(
                "INSERT INTO income_transactions(
                    tenant_id,
                    branch_id,
                    income_no,
                    purpose_id,
                    income_date,
                    amount,
                    payment_mode_id,
                    payer_name,
                    reference_no,
                    income_status,
                    student_id,
                    academic_year_id,
                    receipt_id,
                    description,
                    source_module,
                    source_record_id,
                    created_by,
                    updated_by
                 ) VALUES(
                    :t,
                    :b,
                    :no,
                    :p,
                    :d,
                    :a,
                    :pm,
                    :payer,
                    :ref,
                    :status,
                    :st,
                    :y,
                    :receipt,
                    :descr,
                    'fee_collection',
                    :source,
                    :created_by,
                    :updated_by
                 )"
            )->execute($insertParams);

            $newIncomeId=(int)$pdo->lastInsertId();
            inAudit(
                $pdo,
                $s,
                $newIncomeId,
                'fee_income_synchronized',
                [
                    'receipt_id'=>(int)$r['id'],
                    'receipt_no'=>(string)$r['receipt_no'],
                    'amount'=>(float)$r['paid_amount'],
                    'status'=>$status,
                    'source'=>'fee_collection',
                ]
            );
        }
    }
}

function inMeta(PDO $pdo,array $s):array{
    $q=$pdo->prepare("SELECT id,purpose_code,purpose_name FROM income_purposes WHERE tenant_id=:t AND status='active' ORDER BY purpose_name");
    $q->execute(['t'=>$s['tenant_id']]);$purposes=$q->fetchAll(PDO::FETCH_ASSOC);

    $paymentModes=[];
    if(inExists($pdo,'payment_methods')){
        $q=$pdo->prepare("SELECT id,method_name,method_key FROM payment_methods WHERE tenant_id=:t AND status='active' ORDER BY method_name");
        $q->execute(['t'=>$s['tenant_id']]);$paymentModes=$q->fetchAll(PDO::FETCH_ASSOC);
    }

    $students=[];
    if(inExists($pdo,'students')){
        $q=$pdo->prepare("SELECT st.id,CONCAT(".inName('st').",' (',st.admission_no,')') student_name FROM students st WHERE st.tenant_id=:t AND (:bs=0 OR st.branch_id=:b) AND st.status='active' AND st.deleted_at IS NULL ORDER BY st.first_name");
        $q->execute(['t'=>$s['tenant_id'],'bs'=>$s['branch_id'],'b'=>$s['branch_id']]);$students=$q->fetchAll(PDO::FETCH_ASSOC);
    }

    $years=[];
    if(inExists($pdo,'academic_years')){
        $q=$pdo->prepare("SELECT id,year_name FROM academic_years WHERE tenant_id=:t ORDER BY is_current DESC,id DESC");
        $q->execute(['t'=>$s['tenant_id']]);$years=$q->fetchAll(PDO::FETCH_ASSOC);
    }

    $receipts=[];
    if(inExists($pdo,'fee_receipts')){
        $q=$pdo->prepare("SELECT r.id,CONCAT(r.receipt_no,' - ₹',FORMAT(r.paid_amount,2)) receipt_label FROM fee_receipts r WHERE r.tenant_id=:t AND (:bs=0 OR r.branch_id=:b) AND r.payment_status<>'reversed' ORDER BY r.id DESC LIMIT 500");
        $q->execute(['t'=>$s['tenant_id'],'bs'=>$s['branch_id'],'b'=>$s['branch_id']]);$receipts=$q->fetchAll(PDO::FETCH_ASSOC);
    }
    return compact('purposes','paymentModes','students','years','receipts');
}
function inWhere(array $s,array $f,array &$p):array{
    $where=['i.tenant_id=:t','(:bs=0 OR i.branch_id=:b)'];
    $p=['t'=>$s['tenant_id'],'bs'=>$s['branch_id'],'b'=>$s['branch_id']];
    $search=trim((string)($f['search']??''));
    if($search!==''){$where[]='(i.income_no LIKE :q OR i.payer_name LIKE :q OR i.reference_no LIKE :q OR p.purpose_name LIKE :q)';$p['q']='%'.$search.'%';}
    if(!empty($f['purpose_id'])&&$f['purpose_id']!=='all'){$where[]='i.purpose_id=:purpose';$p['purpose']=(int)$f['purpose_id'];}
    if(!empty($f['status'])&&$f['status']!=='all'){$where[]='i.income_status=:status';$p['status']=$f['status'];}
    if(!empty($f['payment_mode_id'])&&$f['payment_mode_id']!=='all'){$where[]='i.payment_mode_id=:pm';$p['pm']=(int)$f['payment_mode_id'];}
    if(!empty($f['from_date'])){$where[]='i.income_date>=:fd';$p['fd']=$f['from_date'];}
    if(!empty($f['to_date'])){$where[]='i.income_date<=:td';$p['td']=$f['to_date'];}
    return$where;
}
function inRows(PDO $pdo,array $s,array $f,int $page,int $per):array{
    $p=[];$where=inWhere($s,$f,$p);
    $count=$pdo->prepare("SELECT COUNT(*) FROM income_transactions i INNER JOIN income_purposes p ON p.id=i.purpose_id WHERE ".implode(' AND ',$where));
    $count->execute($p);$total=(int)$count->fetchColumn();$last=max(1,(int)ceil($total/$per));$page=min(max(1,$page),$last);$offset=($page-1)*$per;

    $userJoin='';$userSelect='NULL created_by_name';
    if(inExists($pdo,'users')){$userJoin=' LEFT JOIN users u ON u.id=i.created_by ';$userSelect="COALESCE(u.name,u.username,u.email,'-') created_by_name";}
    $studentJoin='';$studentSelect='NULL student_name';
    if(inExists($pdo,'students')){$studentJoin=' LEFT JOIN students st ON st.id=i.student_id ';$studentSelect=inName('st').' student_name';}

    $sql="SELECT i.*,p.purpose_name,pm.method_name payment_mode_name,$userSelect,$studentSelect
          FROM income_transactions i
          INNER JOIN income_purposes p ON p.id=i.purpose_id
          LEFT JOIN payment_methods pm ON pm.id=i.payment_mode_id
          $userJoin $studentJoin
          WHERE ".implode(' AND ',$where)."
          ORDER BY i.income_date DESC,i.id DESC LIMIT {$per} OFFSET {$offset}";
    $q=$pdo->prepare($sql);$q->execute($p);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as $idx=>&$r)$r['row_number']=$offset+$idx+1;
    return['records'=>$rows,'pagination'=>['total'=>$total,'page'=>$page,'per_page'=>$per,'last_page'=>$last]];
}
function inStats(PDO $pdo,array $s,array $f):array{
    $p=[];$where=inWhere($s,$f,$p);
    $q=$pdo->prepare("SELECT COUNT(*) total_entries,
      COALESCE(SUM(CASE WHEN i.income_status='received' THEN i.amount ELSE 0 END),0) total_income,
      COALESCE(SUM(CASE WHEN i.income_status='received' AND i.income_date=CURDATE() THEN i.amount ELSE 0 END),0) today_income,
      COALESCE(SUM(CASE WHEN i.income_status='pending' THEN i.amount ELSE 0 END),0) pending_income
      FROM income_transactions i INNER JOIN income_purposes p ON p.id=i.purpose_id WHERE ".implode(' AND ',$where));
    $q->execute($p);$r=$q->fetch(PDO::FETCH_ASSOC)?:[];
    return['total_entries'=>(int)($r['total_entries']??0),'total_income'=>(float)($r['total_income']??0),'today_income'=>(float)($r['today_income']??0),'pending_income'=>(float)($r['pending_income']??0)];
}
function inSummary(PDO $pdo,array $s):array{
    $q=$pdo->prepare("SELECT p.purpose_name,COUNT(i.id) entries,
      COALESCE(SUM(CASE WHEN i.income_status='received' THEN i.amount ELSE 0 END),0) received_amount,
      COALESCE(SUM(CASE WHEN i.income_status='pending' THEN i.amount ELSE 0 END),0) pending_amount,
      COALESCE(SUM(CASE WHEN i.income_status='cancelled' THEN i.amount ELSE 0 END),0) cancelled_amount,
      COALESCE(SUM(i.amount),0) total_amount
      FROM income_purposes p LEFT JOIN income_transactions i ON i.purpose_id=p.id AND i.tenant_id=p.tenant_id
      WHERE p.tenant_id=:t GROUP BY p.id ORDER BY p.purpose_name");
    $q->execute(['t'=>$s['tenant_id']]);return$q->fetchAll(PDO::FETCH_ASSOC);
}
function inHistory(PDO $pdo,array $s):array{
    $userJoin='';
    $userSelect='NULL user_name';

    if(inExists($pdo,'users')){
        $userJoin=' LEFT JOIN users u ON u.id=a.user_id ';
        $userSelect="COALESCE(u.name,u.username,u.email,'System') user_name";
    }

    $q=$pdo->prepare(
        "SELECT
            a.id,
            DATE_FORMAT(a.created_at,'%d-%m-%Y %h:%i %p') created_at,
            a.action_name,
            i.income_no,
            COALESCE(i.source_module,'manual_income') source_module,
            $userSelect,
            LEFT(
                REPLACE(
                    REPLACE(COALESCE(a.details_json,''),CHAR(10),' '),
                    CHAR(13),' '
                ),
                500
            ) details_text
         FROM income_audit_logs a
         LEFT JOIN income_transactions i
            ON i.id=a.income_id
           AND i.tenant_id=a.tenant_id
         $userJoin
         WHERE a.tenant_id=:tenant_id
           AND (:branch_scope=0 OR a.branch_id=:branch_id)
         ORDER BY a.id DESC
         LIMIT 250"
    );
    $q->execute([
        'tenant_id'=>$s['tenant_id'],
        'branch_scope'=>$s['branch_id'],
        'branch_id'=>$s['branch_id'],
    ]);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}

if(!isset($pdo)||!$pdo instanceof PDO)inOut(false,'Database connection unavailable.',[],500);
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
if(empty($_SESSION['income_csrf_token']))$_SESSION['income_csrf_token']=bin2hex(random_bytes(32));
$s=inScope();if($s['tenant_id']<=0||$s['user_id']<=0)inOut(false,'Tenant or user session is missing.',[],401);
// if(!inCan($s,'view')){
//     inOut(
//         false,
//         'You do not have permission to access Income Management.',
//         [
//             'permission'=>'income_management',
//             'role'=>$s['role'],
//             'user_id'=>$s['user_id']
//         ],
//         403
//     );
// }
$i=inInput();
$action=strtolower(trim((string)($i['action']??$_GET['action']??'')));

try{
    inEnsure($pdo);
    inSeed($pdo,$s['tenant_id']);

    /*
     * Historical receipt synchronization is part of the request, but it must
     * never produce an HTML/PHP fatal response. Any database error is returned
     * through the JSON catch block below.
     */
    inSyncFeeReceipts($pdo,$s);
    inBackfillAudit($pdo,$s);

    if($action==='list'){
        $page=max(1,(int)($_GET['page']??1));$per=min(100,max(5,(int)($_GET['per_page']??10)));
        $x=inRows($pdo,$s,$_GET,$page,$per);$m=inMeta($pdo,$s);
        inOut(true,'Income management loaded.',['api_build'=>INCOME_INTEGRATION_BUILD,'records'=>$x['records'],'pagination'=>$x['pagination'],'stats'=>inStats($pdo,$s,$_GET),'summary'=>inSummary($pdo,$s),'history'=>inHistory($pdo,$s),'meta'=>['purposes'=>$m['purposes'],'payment_modes'=>$m['paymentModes'],'students'=>$m['students'],'years'=>$m['years'],'receipts'=>$m['receipts']],'permissions'=>['edit'=>inCan($s,'edit'),'delete'=>inCan($s,'delete')],'csrf_token'=>$_SESSION['income_csrf_token']]);
    }

    if($action==='history'){
        inOut(
            true,
            'Income Audit History loaded.',
            [
                'history'=>inHistory($pdo,$s),
                'api_build'=>INCOME_INTEGRATION_BUILD,
                'csrf_token'=>$_SESSION['income_csrf_token'],
            ]
        );
    }

    if($action==='detail'){
        $id=(int)($_GET['id']??0);$userJoin='';$userSelect='NULL created_by_name';
        if(inExists($pdo,'users')){$userJoin=' LEFT JOIN users u ON u.id=i.created_by ';$userSelect="COALESCE(u.name,u.username,u.email,'-') created_by_name";}
        $q=$pdo->prepare("SELECT i.*,p.purpose_name,pm.method_name payment_mode_name,".inName('st')." student_name,ay.year_name,r.receipt_no,$userSelect
          FROM income_transactions i INNER JOIN income_purposes p ON p.id=i.purpose_id
          LEFT JOIN payment_methods pm ON pm.id=i.payment_mode_id
          LEFT JOIN students st ON st.id=i.student_id
          LEFT JOIN academic_years ay ON ay.id=i.academic_year_id
          LEFT JOIN fee_receipts r ON r.id=i.receipt_id
          $userJoin
          WHERE i.id=:id AND i.tenant_id=:t AND (:bs=0 OR i.branch_id=:b)");
        $q->execute(['id'=>$id,'t'=>$s['tenant_id'],'bs'=>$s['branch_id'],'b'=>$s['branch_id']]);$r=$q->fetch(PDO::FETCH_ASSOC);
        if(!$r)throw new InvalidArgumentException('Income record not found.');
        inOut(true,'Income details loaded.',['record'=>$r]);
    }

    if($action==='save'){
        inCsrf($i);
        $id=(int)($i['id']??0);
        $requiredPermission=$id>0?'edit':'create';
        if(!inCan($s,$requiredPermission)){
            inOut(false,$id>0
                ?'You do not have permission to edit income records.'
                :'You do not have permission to create income records.',[
                    'required_permission'=>$requiredPermission,
                    'role'=>$s['role']??'',
                    'role_id'=>$s['role_id']??0,
                    'user_id'=>$s['user_id']??0
                ],403);
        }
        $purpose=(int)($i['purpose_id']??0);$date=(string)($i['income_date']??'');$amount=round((float)($i['amount']??0),2);$method=(int)($i['payment_mode_id']??0);$payer=trim((string)($i['payer_name']??''));$reference=trim((string)($i['reference_no']??''));$status=(string)($i['income_status']??'received');$student=(int)($i['student_id']??0);$year=(int)($i['academic_year_id']??0);$receipt=(int)($i['receipt_id']??0);$description=trim((string)($i['description']??''));
        if($purpose<=0||$date===''||$amount<=0||$method<=0||$payer==='')throw new InvalidArgumentException('Purpose, date, amount, payment mode and payer are required.');
        if(!in_array($status,['received','pending','cancelled'],true))throw new InvalidArgumentException('Invalid income status.');
        if($date>date('Y-m-d'))throw new InvalidArgumentException('Income date cannot be in the future.');
        $q=$pdo->prepare("SELECT COUNT(*) FROM income_purposes WHERE id=:id AND tenant_id=:t AND status='active'");$q->execute(['id'=>$purpose,'t'=>$s['tenant_id']]);if(!(int)$q->fetchColumn())throw new InvalidArgumentException('Invalid income purpose.');
        $q=$pdo->prepare("SELECT method_key FROM payment_methods WHERE id=:id AND tenant_id=:t AND status='active'");$q->execute(['id'=>$method,'t'=>$s['tenant_id']]);$methodKey=strtolower((string)$q->fetchColumn());if($methodKey==='' )throw new InvalidArgumentException('Invalid payment mode.');
        if($status==='received'&&!in_array($methodKey,['cash','cash_payment'],true)&&$reference==='')throw new InvalidArgumentException('Transaction reference is required for non-cash income.');

        $pdo->beginTransaction();
        if($id>0){
            $q=$pdo->prepare("SELECT * FROM income_transactions WHERE id=:id AND tenant_id=:t FOR UPDATE");$q->execute(['id'=>$id,'t'=>$s['tenant_id']]);$old=$q->fetch(PDO::FETCH_ASSOC);if(!$old)throw new InvalidArgumentException('Income record not found.');if(($old['source_module']??'')==='fee_collection')throw new InvalidArgumentException('Fee Collection income is system-generated and cannot be edited here. Update the Fee Receipt instead.');
            $q=$pdo->prepare("UPDATE income_transactions SET purpose_id=:p,income_date=:d,amount=:a,payment_mode_id=:pm,payer_name=:payer,reference_no=:ref,income_status=:status,student_id=:st,academic_year_id=:y,receipt_id=:r,description=:descr,updated_by=:u WHERE id=:id AND tenant_id=:t");
            $q->execute(['p'=>$purpose,'d'=>$date,'a'=>$amount,'pm'=>$method,'payer'=>$payer,'ref'=>$reference?:null,'status'=>$status,'st'=>$student?:null,'y'=>$year?:null,'r'=>$receipt?:null,'descr'=>$description?:null,'u'=>$s['user_id'],'id'=>$id,'t'=>$s['tenant_id']]);
            inAudit($pdo,$s,$id,'update_income',['old'=>$old,'new'=>$i]);$message='Income record updated successfully.';
        }else{
            $no=inNo($pdo,$s['tenant_id']);
            $q=$pdo->prepare("INSERT INTO income_transactions(tenant_id,branch_id,income_no,purpose_id,income_date,amount,payment_mode_id,payer_name,reference_no,income_status,student_id,academic_year_id,receipt_id,description,source_module,source_record_id,created_by,updated_by) VALUES(:t,:b,:no,:p,:d,:a,:pm,:payer,:ref,:status,:st,:y,:r,:descr,'manual_income',NULL,:u,:u2)");
            $q->execute(['t'=>$s['tenant_id'],'b'=>$s['branch_id']?:null,'no'=>$no,'p'=>$purpose,'d'=>$date,'a'=>$amount,'pm'=>$method,'payer'=>$payer,'ref'=>$reference?:null,'status'=>$status,'st'=>$student?:null,'y'=>$year?:null,'r'=>$receipt?:null,'descr'=>$description?:null,'u'=>$s['user_id'],'u2'=>$s['user_id']]);
            $id=(int)$pdo->lastInsertId();inAudit($pdo,$s,$id,'create_income',$i);$message='Income record created successfully.';
        }
        $pdo->commit();inOut(true,$message,['id'=>$id]);
    }

    if($action==='delete'){
        inCsrf($i);if(!inCan($s,'delete'))inOut(false,'You do not have permission to delete income records.',[],403);
        $id=(int)($i['id']??0);$pdo->beginTransaction();
        $q=$pdo->prepare("SELECT * FROM income_transactions WHERE id=:id AND tenant_id=:t FOR UPDATE");$q->execute(['id'=>$id,'t'=>$s['tenant_id']]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)throw new InvalidArgumentException('Income record not found.');
        if(!empty($r['receipt_id']))throw new InvalidArgumentException('Income linked to a fee receipt cannot be deleted.');
        $pdo->prepare("DELETE FROM income_transactions WHERE id=:id AND tenant_id=:t")->execute(['id'=>$id,'t'=>$s['tenant_id']]);inAudit($pdo,$s,$id,'delete_income',$r);$pdo->commit();inOut(true,'Income record deleted successfully.');
    }

    if($action==='import'){
        inCsrf($i);if(!inCan($s,'import'))inOut(false,'You do not have permission to import income records.',[],403);
        $csv=(string)($i['csv_text']??'');if(trim($csv)==='')throw new InvalidArgumentException('CSV content is empty.');
        $fh=fopen('php://temp','r+');fwrite($fh,$csv);rewind($fh);$header=fgetcsv($fh);if(!$header)throw new InvalidArgumentException('CSV header row is missing.');
        $header=array_map(fn($v)=>strtolower(trim((string)$v)),$header);$map=array_flip($header);
        foreach(['purpose_code','income_date','amount','payment_mode','payer_name','status'] as $required)if(!isset($map[$required]))throw new InvalidArgumentException("CSV column '$required' is required.");
        $inserted=0;$skipped=0;$pdo->beginTransaction();
        while(($row=fgetcsv($fh))!==false){
            if(count(array_filter($row,fn($v)=>trim((string)$v)!==''))===0)continue;
            try{
                $code=strtolower(trim((string)$row[$map['purpose_code']]));$date=trim((string)$row[$map['income_date']]);$amount=round((float)$row[$map['amount']],2);$mode=strtolower(trim((string)$row[$map['payment_mode']]));$payer=trim((string)$row[$map['payer_name']]);$status=strtolower(trim((string)$row[$map['status']]));
                if($code===''||$date===''||$amount<=0||$mode===''||$payer===''||!in_array($status,['received','pending','cancelled'],true))throw new RuntimeException();
                $q=$pdo->prepare("SELECT id FROM income_purposes WHERE tenant_id=:t AND purpose_code=:c AND status='active'");$q->execute(['t'=>$s['tenant_id'],'c'=>$code]);$purpose=(int)$q->fetchColumn();
                $q=$pdo->prepare("SELECT id FROM payment_methods WHERE tenant_id=:t AND LOWER(method_key)=:k AND status='active'");$q->execute(['t'=>$s['tenant_id'],'k'=>$mode]);$method=(int)$q->fetchColumn();
                if($purpose<=0||$method<=0)throw new RuntimeException();
                $q=$pdo->prepare("INSERT INTO income_transactions(tenant_id,branch_id,income_no,purpose_id,income_date,amount,payment_mode_id,payer_name,reference_no,income_status,description,created_by,updated_by) VALUES(:t,:b,:no,:p,:d,:a,:pm,:payer,:ref,:status,:descr,:u,:u2)");
                $q->execute(['t'=>$s['tenant_id'],'b'=>$s['branch_id']?:null,'no'=>inNo($pdo,$s['tenant_id']),'p'=>$purpose,'d'=>$date,'a'=>$amount,'pm'=>$method,'payer'=>$payer,'ref'=>isset($map['reference_no'])?trim((string)$row[$map['reference_no']])?:null:null,'status'=>$status,'descr'=>isset($map['description'])?trim((string)$row[$map['description']])?:null:null,'u'=>$s['user_id'],'u2'=>$s['user_id']]);$inserted++;
            }catch(Throwable $e){$skipped++;}
        }
        fclose($fh);inAudit($pdo,$s,null,'import_income',['inserted'=>$inserted,'skipped'=>$skipped]);$pdo->commit();inOut(true,"Import completed: {$inserted} inserted, {$skipped} skipped.");
    }

    if($action==='export'||$action==='print'){
        $x=inRows($pdo,$s,$_GET,1,1000);
        if($action==='export'){
            while(ob_get_level()>0)ob_end_clean();header('Content-Type:text/csv; charset=utf-8');header('Content-Disposition:attachment; filename="income-report-'.date('Ymd-His').'.csv"');
            $f=fopen('php://output','wb');fputcsv($f,['Income No','Date','Purpose','Payer','Student','Amount','Payment Mode','Reference','Status','Description']);
            foreach($x['records'] as $r)fputcsv($f,[$r['income_no'],$r['income_date'],$r['purpose_name'],$r['payer_name'],$r['student_name'],$r['amount'],$r['payment_mode_name'],$r['reference_no'],$r['income_status'],$r['description']]);
            fclose($f);exit;
        }
        while(ob_get_level()>0)ob_end_clean();header('Content-Type:text/html; charset=utf-8');$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Income Report</title><style>body{font-family:Arial;margin:24px}h2{text-align:center}table{width:100%;border-collapse:collapse;font-size:11px}th,td{border:1px solid #ddd;padding:7px;text-align:left}th{background:#f4f6fa}.actions{text-align:center;margin:18px}@media print{.actions{display:none}}</style></head><body><h2>Income Report</h2><table><thead><tr><th>Income No.</th><th>Date</th><th>Purpose</th><th>Payer</th><th>Mode</th><th>Amount</th><th>Status</th></tr></thead><tbody>';
        foreach($x['records'] as $r)echo '<tr><td>'.$e($r['income_no']).'</td><td>'.$e($r['income_date']).'</td><td>'.$e($r['purpose_name']).'</td><td>'.$e($r['payer_name']).'</td><td>'.$e($r['payment_mode_name']).'</td><td>₹'.number_format((float)$r['amount'],2).'</td><td>'.$e($r['income_status']).'</td></tr>';
        echo '</tbody></table><div class="actions"><button onclick="window.print()">Print Report</button></div></body></html>';exit;
    }

    inOut(false,'Invalid Income Management action.',[],400);
}catch(InvalidArgumentException $e){if($pdo->inTransaction())$pdo->rollBack();inOut(false,$e->getMessage(),[],422);}
catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    error_log(
        'income-management.php build='.INCOME_INTEGRATION_BUILD.
        ' action='.(string)($action??'unknown').
        ' line='.$e->getLine().
        ' error='.$e->getMessage()
    );
    $h=strtolower((string)($_SERVER['HTTP_HOST']??''));
    inOut(
        false,
        (str_contains($h,'localhost')||str_contains($h,'127.0.0.1'))
            ?'Income request failed ['.INCOME_INTEGRATION_BUILD.']: '.$e->getMessage()
            :'Unable to complete the income request.',
        [],
        500
    );
}

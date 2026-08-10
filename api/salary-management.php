<?php
declare(strict_types=1);
ob_start();
ini_set('display_errors','0');
error_reporting(E_ALL);
require_once dirname(__DIR__).'/includes/bootstrap.php';
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
const SALARY_BUILD='2026-08-05-auto-description-v16';
function out(bool $ok,string $message='',array $data=[],int $status=200):never{while(ob_get_level()>0)ob_end_clean();if(!headers_sent()){http_response_code($status);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');}echo json_encode(['success'=>$ok,'message'=>$message,'data'=>$data],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
function input():array{$x=json_decode((string)file_get_contents('php://input'),true);return is_array($x)?$x:$_POST;}
function tableExists(PDO $pdo,string $table):bool{$q=$pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?");$q->execute([$table]);return(int)$q->fetchColumn()>0;}
function columnExists(PDO $pdo,string $table,string $column):bool{$q=$pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?");$q->execute([$table,$column]);return(int)$q->fetchColumn()>0;}
function ensureColumn(PDO $pdo,string $column,string $definition):void{if(!columnExists($pdo,'staff_salary_records',$column))$pdo->exec("ALTER TABLE staff_salary_records ADD COLUMN `$column` $definition");}
function scope():array{$u=function_exists('current_user')?current_user():[];$u=is_array($u)?$u:[];return['tenant'=>(int)($u['tenant_id']??$_SESSION['tenant_id']??0),'branch'=>(int)($u['branch_id']??$_SESSION['branch_id']??0),'user'=>(int)($u['id']??$_SESSION['user_id']??0)];}
function csrf(array $in):void{$a=(string)($_SESSION['salary_csrf_token']??'');$b=(string)($in['csrf_token']??'');if($a===''||$b===''||!hash_equals($a,$b))out(false,'Invalid or expired CSRF token.',[],419);}
function monthDate(string $month):string{if(!preg_match('/^\d{4}-\d{2}$/',$month))throw new InvalidArgumentException('Invalid salary month.');$d=DateTimeImmutable::createFromFormat('Y-m-d',$month.'-01');if(!$d||$d->format('Y-m')!==$month)throw new InvalidArgumentException('Invalid salary month.');return$d->format('Y-m-01');}
function monthLabel(string $date):string{return date('F Y',strtotime($date));}
function staffName(string $a='sm'):string{return"TRIM(CONCAT(COALESCE($a.first_name,''),CASE WHEN COALESCE($a.last_name,'')='' THEN '' ELSE CONCAT(' ',$a.last_name) END))";}
function ensureLedgerColumn(PDO $pdo,string $column,string $definition):void{
 if(!columnExists($pdo,'staff_salary_ledger',$column)){
  $pdo->exec("ALTER TABLE staff_salary_ledger ADD COLUMN `$column` $definition");
 }
}

function ensureSchema(PDO $pdo):void{
 if(!tableExists($pdo,'staff_members'))throw new RuntimeException('staff_members table is missing.');
 if(!tableExists($pdo,'staff_salary_records'))throw new RuntimeException('staff_salary_records table is missing.');
 ensureColumn($pdo,'advance_adjustment',"DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER deductions");
 ensureColumn($pdo,'bank_account_id',"BIGINT UNSIGNED NULL AFTER payment_method");
 ensureColumn($pdo,'updated_by',"BIGINT UNSIGNED NULL AFTER created_by");

 if(!tableExists($pdo,'staff_salary_ledger')){
  $pdo->exec("CREATE TABLE staff_salary_ledger(
   id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
   tenant_id BIGINT UNSIGNED NOT NULL,
   branch_id BIGINT UNSIGNED NOT NULL,
   salary_record_id BIGINT UNSIGNED NOT NULL,
   staff_id BIGINT UNSIGNED NOT NULL,
   salary_month DATE NOT NULL,
   entry_type VARCHAR(40) NOT NULL,
   amount DECIMAL(12,2) NOT NULL DEFAULT 0,
   balance_before DECIMAL(12,2) NOT NULL DEFAULT 0,
   balance_after DECIMAL(12,2) NOT NULL DEFAULT 0,
   transaction_at DATETIME NOT NULL,
   payment_method VARCHAR(30) NULL,
   reference_no VARCHAR(120) NULL,
   remarks VARCHAR(255) NULL,
   source_key VARCHAR(100) NULL,
   created_by BIGINT UNSIGNED NULL,
   created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
   PRIMARY KEY(id),
   UNIQUE KEY uk_salary_ledger_source(tenant_id,source_key),
   KEY idx_salary_ledger_record(salary_record_id,id),
   KEY idx_salary_ledger_staff_month(tenant_id,staff_id,salary_month,id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 }

 ensureLedgerColumn($pdo,'purpose',"VARCHAR(150) NULL AFTER payment_method");
 ensureLedgerColumn($pdo,'description',"VARCHAR(255) NULL AFTER purpose");

 if(!tableExists($pdo,'staff_salary_transactions')){
  $pdo->exec("CREATE TABLE staff_salary_transactions(
   id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
   tenant_id BIGINT UNSIGNED NOT NULL,
   branch_id BIGINT UNSIGNED NOT NULL,
   salary_record_id BIGINT UNSIGNED NOT NULL,
   staff_id BIGINT UNSIGNED NOT NULL,
   salary_month DATE NOT NULL,
   payment_no VARCHAR(40) NOT NULL,
   payment_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
   balance_before DECIMAL(12,2) NOT NULL DEFAULT 0,
   balance_after DECIMAL(12,2) NOT NULL DEFAULT 0,
   payment_date DATE NOT NULL,
   payment_method VARCHAR(30) NOT NULL DEFAULT 'cash',
   bank_account_id BIGINT UNSIGNED NULL,
   reference_no VARCHAR(120) NULL,
   remarks VARCHAR(255) NULL,
   created_by BIGINT UNSIGNED NULL,
   created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
   PRIMARY KEY(id),UNIQUE KEY uk_salary_payment_no(payment_no),
   KEY idx_salary_tx_record(salary_record_id),KEY idx_salary_tx_staff_month(tenant_id,staff_id,salary_month)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 }
 migrateLegacyPayments($pdo);
}
function migrateLegacyPayments(PDO $pdo):void{
 $q=$pdo->query("SELECT ssr.* FROM staff_salary_records ssr LEFT JOIN staff_salary_transactions tx ON tx.salary_record_id=ssr.id WHERE tx.id IS NULL AND ssr.payment_status='paid'");
 foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r){
  $base=round((float)$r['basic_salary']+(float)$r['allowances']-(float)$r['deductions'],2);
  $legacyAdvance=round((float)($r['advance_adjustment']??0),2);
  $storedNet=round((float)$r['net_salary'],2);
  $looksLikeOldPayment=$legacyAdvance>0&&abs(($base-$legacyAdvance)-$storedNet)<0.02;
  $payment=$looksLikeOldPayment?$legacyAdvance:$storedNet;
  $total=$looksLikeOldPayment?$base:$storedNet;
  if($payment<=0)continue;
  $after=max(0,$total-$payment);
  $no='SAL-LEG-'.$r['id'];
  $ins=$pdo->prepare("INSERT IGNORE INTO staff_salary_transactions(tenant_id,branch_id,salary_record_id,staff_id,salary_month,payment_no,payment_amount,balance_before,balance_after,payment_date,payment_method,bank_account_id,reference_no,remarks,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
  $ins->execute([(int)$r['tenant_id'],(int)$r['branch_id'],(int)$r['id'],(int)$r['staff_id'],$r['salary_month'],$no,$payment,$total,$after,$r['payment_date']?:date('Y-m-d'),$r['payment_method']?:'cash',$r['bank_account_id']?:null,$r['reference_no']?:null,'Migrated from previous salary payment record',$r['created_by']?:null]);
  if($looksLikeOldPayment){$u=$pdo->prepare("UPDATE staff_salary_records SET advance_adjustment=0,net_salary=?,payment_status=?,updated_at=COALESCE(updated_at,CURRENT_TIMESTAMP) WHERE id=?");try{$u->execute([$total,$after<=0.009?'paid':'pending',(int)$r['id']]);}catch(Throwable){$u=$pdo->prepare("UPDATE staff_salary_records SET advance_adjustment=0,net_salary=?,payment_status=? WHERE id=?");$u->execute([$total,$after<=0.009?'paid':'pending',(int)$r['id']]);}}
 }
}

function ledgerLastBalance(PDO $pdo,int $salaryRecordId,bool $lock=false):float{
 $sql="SELECT balance_after
       FROM staff_salary_ledger
       WHERE salary_record_id=?
       ORDER BY id DESC
       LIMIT 1";
 if($lock)$sql.=" FOR UPDATE";
 $q=$pdo->prepare($sql);
 $q->execute([$salaryRecordId]);
 $value=$q->fetchColumn();
 return $value===false?0.0:round((float)$value,2);
}

function ledgerAdd(
 PDO $pdo,
 array $scope,
 int $salaryRecordId,
 int $staffId,
 string $salaryMonth,
 string $entryType,
 float $amount,
 float $balanceBefore,
 float $balanceAfter,
 string $transactionAt,
 ?string $paymentMethod,
 ?string $referenceNo,
 ?string $remarks,
 ?string $sourceKey,
 ?string $purpose=null,
 ?string $description=null
):void{
 $q=$pdo->prepare(
  "INSERT IGNORE INTO staff_salary_ledger(
    tenant_id,branch_id,salary_record_id,staff_id,salary_month,
    entry_type,amount,balance_before,balance_after,transaction_at,
    payment_method,purpose,description,reference_no,remarks,source_key,created_by
   ) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
 );
 $q->execute([
  $scope['tenant'],
  $scope['branch']>0?$scope['branch']:1,
  $salaryRecordId,
  $staffId,
  $salaryMonth,
  $entryType,
  round($amount,2),
  round($balanceBefore,2),
  round($balanceAfter,2),
  $transactionAt,
  $paymentMethod,
  $purpose,
  $description,
  $referenceNo,
  $remarks,
  $sourceKey,
  $scope['user']?:null
 ]);
}

function ensureSalaryLedger(PDO $pdo,array $scope,int $salaryRecordId):void{
 $q=$pdo->prepare(
  "SELECT ssr.*,sm.branch_id AS staff_branch_id
   FROM staff_salary_records ssr
   INNER JOIN staff_members sm
     ON sm.id=ssr.staff_id
    AND sm.tenant_id=ssr.tenant_id
   WHERE ssr.id=? AND ssr.tenant_id=?
   LIMIT 1"
 );
 $q->execute([$salaryRecordId,$scope['tenant']]);
 $record=$q->fetch(PDO::FETCH_ASSOC);
 if(!$record)return;

 $existing=$pdo->prepare(
  "SELECT COUNT(*) FROM staff_salary_ledger
   WHERE salary_record_id=?"
 );
 $existing->execute([$salaryRecordId]);
 if((int)$existing->fetchColumn()>0)return;

 $localScope=$scope;
 $localScope['branch']=(int)($record['branch_id']??$record['staff_branch_id']??1);

 $month=(string)$record['salary_month'];
 $at=(string)($record['created_at']??date('Y-m-d H:i:s'));
 $balance=0.0;

 $fixed=round((float)$record['basic_salary'],2);
 if($fixed>0){
  ledgerAdd(
   $pdo,$localScope,$salaryRecordId,(int)$record['staff_id'],$month,
   'opening_salary',$fixed,$balance,$balance+$fixed,$at,
   null,null,'Monthly fixed salary','opening-'.$salaryRecordId
  );
  $balance+=$fixed;
 }

 $allowance=round((float)$record['allowances'],2);
 if($allowance>0){
  ledgerAdd(
   $pdo,$localScope,$salaryRecordId,(int)$record['staff_id'],$month,
   'allowance',$allowance,$balance,$balance+$allowance,$at,
   null,null,'Allowance amount','allowance-initial-'.$salaryRecordId
  );
  $balance+=$allowance;
 }

 $deduction=round((float)$record['deductions'],2);
 if($deduction>0){
  ledgerAdd(
   $pdo,$localScope,$salaryRecordId,(int)$record['staff_id'],$month,
   'deduction',$deduction,$balance,max(0,$balance-$deduction),$at,
   null,null,'Deduction amount','deduction-initial-'.$salaryRecordId
  );
  $balance=max(0,$balance-$deduction);
 }

 $tx=$pdo->prepare(
  "SELECT *
   FROM staff_salary_transactions
   WHERE salary_record_id=?
   ORDER BY payment_date,id"
 );
 $tx->execute([$salaryRecordId]);

 foreach($tx->fetchAll(PDO::FETCH_ASSOC) as $payment){
  $amount=round((float)$payment['payment_amount'],2);
  $before=$balance;
  $after=max(0,round($before-$amount,2));
  $transactionAt=trim((string)($payment['created_at']??''));
  if($transactionAt===''){
   $transactionAt=(string)$payment['payment_date'].' 00:00:00';
  }
  ledgerAdd(
   $pdo,$localScope,$salaryRecordId,(int)$record['staff_id'],$month,
   'payment',$amount,$before,$after,$transactionAt,
   (string)($payment['payment_method']??'cash'),
   ($payment['reference_no']??null)?:null,
   ($payment['remarks']??null)?:null,
   'payment-'.$payment['id']
  );
  $balance=$after;
 }
}

function banks(PDO $pdo,array $s):array{if(!tableExists($pdo,'company_bank_accounts'))return[];$ifsc=columnExists($pdo,'company_bank_accounts','ifsc')?'ifsc AS ifsc_code':(columnExists($pdo,'company_bank_accounts','ifsc_code')?'ifsc_code':'NULL AS ifsc_code');$sql="SELECT id,account_name,bank_name,account_number,$ifsc FROM company_bank_accounts WHERE tenant_id=? AND status='active'";$p=[$s['tenant']];if($s['branch']>0){$sql.=" AND (branch_id=? OR branch_id IS NULL)";$p[]=$s['branch'];}$sql.=" ORDER BY account_name";$q=$pdo->prepare($sql);$q->execute($p);return$q->fetchAll(PDO::FETCH_ASSOC);}
function one(PDO $pdo,array $s,int $staffId,string $month):array{$q=$pdo->prepare("SELECT sm.id staff_id,sm.branch_id,sm.staff_code,".staffName()." staff_name,sm.basic_salary fixed_salary,sd.department_name,sdes.designation_name,ssr.id salary_record_id,COALESCE(ssr.allowances,0) allowances,COALESCE(ssr.deductions,0) deductions,COALESCE(ssr.net_salary,sm.basic_salary) total_salary,COALESCE((SELECT SUM(tx.payment_amount) FROM staff_salary_transactions tx WHERE tx.salary_record_id=ssr.id),0) total_paid,ssr.remarks FROM staff_members sm LEFT JOIN staff_departments sd ON sd.id=sm.department_id AND sd.tenant_id=sm.tenant_id LEFT JOIN staff_designations sdes ON sdes.id=sm.designation_id AND sdes.tenant_id=sm.tenant_id LEFT JOIN staff_salary_records ssr ON ssr.staff_id=sm.id AND ssr.tenant_id=sm.tenant_id AND ssr.salary_month=? WHERE sm.id=? AND sm.tenant_id=? AND sm.status='active' AND sm.deleted_at IS NULL LIMIT 1");$q->execute([monthDate($month),$staffId,$s['tenant']]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)throw new InvalidArgumentException('Staff member not found.');$r['balance_amount']=max(0,(float)$r['total_salary']-(float)$r['total_paid']);$r['payment_status']=$r['balance_amount']<=0.009?'paid':((float)$r['total_paid']>0?'partial':'pending');return$r;}
function listing(PDO $pdo,array $s,string $month,string $search,string $status):array{$where=["sm.tenant_id=?","sm.status='active'","sm.deleted_at IS NULL","sm.basic_salary>0","(?=0 OR sm.branch_id=?)"];$p=[monthDate($month),$s['tenant'],$s['branch'],$s['branch']];if($search!==''){$where[]="(sm.staff_code LIKE ? OR sm.first_name LIKE ? OR sm.last_name LIKE ? OR sd.department_name LIKE ? OR sdes.designation_name LIKE ?)";$v='%'.$search.'%';array_push($p,$v,$v,$v,$v,$v);}$sql="SELECT sm.id staff_id,sm.staff_code,".staffName()." staff_name,sm.basic_salary fixed_salary,sd.department_name,sdes.designation_name,ssr.id salary_record_id,COALESCE(ssr.allowances,0) allowances,COALESCE(ssr.deductions,0) deductions,COALESCE(ssr.net_salary,sm.basic_salary) total_salary,COALESCE(tx.total_paid,0) total_paid,tx.last_payment_date FROM staff_members sm LEFT JOIN staff_departments sd ON sd.id=sm.department_id AND sd.tenant_id=sm.tenant_id LEFT JOIN staff_designations sdes ON sdes.id=sm.designation_id AND sdes.tenant_id=sm.tenant_id LEFT JOIN staff_salary_records ssr ON ssr.staff_id=sm.id AND ssr.tenant_id=sm.tenant_id AND ssr.salary_month=? LEFT JOIN (SELECT salary_record_id,SUM(payment_amount) total_paid,MAX(payment_date) last_payment_date FROM staff_salary_transactions GROUP BY salary_record_id) tx ON tx.salary_record_id=ssr.id WHERE ".implode(' AND ',$where)." ORDER BY sm.first_name,sm.last_name";$q=$pdo->prepare($sql);$q->execute($p);$rows=$q->fetchAll(PDO::FETCH_ASSOC);foreach($rows as &$r){$r['balance_amount']=max(0,(float)$r['total_salary']-(float)$r['total_paid']);$r['computed_status']=$r['balance_amount']<=0.009?'paid':((float)$r['total_paid']>0?'partial':'pending');}unset($r);if($status!=='all')$rows=array_values(array_filter($rows,fn($r)=>$r['computed_status']===$status));return$rows;}
$in=input();$action=strtolower(trim((string)($in['action']??$_GET['action']??'')));$s=scope();if($action==='')out(false,'Salary API action is missing.',['build'=>SALARY_BUILD],400);if(!isset($pdo)||!$pdo instanceof PDO)out(false,'Database connection missing.',[],500);if($s['tenant']<=0||$s['user']<=0)out(false,'User session is missing.',[],401);if(empty($_SESSION['salary_csrf_token']))$_SESSION['salary_csrf_token']=bin2hex(random_bytes(32));
try{
 ensureSchema($pdo);
 if($action==='list'){$month=trim((string)($_GET['month']??$in['month']??date('Y-m')));$rows=listing($pdo,$s,$month,trim((string)($_GET['search']??$in['search']??'')),trim((string)($_GET['status']??$in['status']??'all')));$stats=['total_payroll'=>0,'paid_payroll'=>0,'pending_count'=>0,'staff_count'=>count($rows)];foreach($rows as$r){$stats['total_payroll']+=(float)$r['total_salary'];$stats['paid_payroll']+=(float)$r['total_paid'];if($r['balance_amount']>0.009)$stats['pending_count']++;}out(true,'Salary payroll loaded.',['records'=>$rows,'stats'=>$stats,'banks'=>banks($pdo,$s),'month_label'=>monthLabel(monthDate($month)),'csrf_token'=>$_SESSION['salary_csrf_token'],'build'=>SALARY_BUILD]);}
 if($action==='detail')out(true,'Salary payment details loaded.',['record'=>one($pdo,$s,(int)($_GET['staff_id']??$in['staff_id']??0),trim((string)($_GET['month']??$in['month']??date('Y-m')))),'banks'=>banks($pdo,$s),'csrf_token'=>$_SESSION['salary_csrf_token']]);
 if($action==='history'){
  $staffId=(int)($_GET['staff_id']??$in['staff_id']??0);
  $month=trim((string)($_GET['month']??$in['month']??date('Y-m')));
  if($staffId<=0)throw new InvalidArgumentException('Select a valid staff member.');

  $record=one($pdo,$s,$staffId,$month);
  $recordId=(int)($record['salary_record_id']??0);

  $staff=[
   'staff_name'=>$record['staff_name']??'-',
   'staff_code'=>$record['staff_code']??'-',
   'department_name'=>$record['department_name']??'-',
   'designation_name'=>$record['designation_name']??'-'
  ];

  if($recordId<=0){
   out(true,'No salary ledger entries found.',[
    'staff'=>$staff,
    'summary'=>[
     'total_salary'=>(float)($record['fixed_salary']??0),
     'total_paid'=>0,
     'remaining_balance'=>(float)($record['fixed_salary']??0),
     'entry_count'=>0
    ],
    'records'=>[],
    'csrf_token'=>$_SESSION['salary_csrf_token'],
    'build'=>SALARY_BUILD
   ]);
  }

  ensureSalaryLedger($pdo,$s,$recordId);

  $q=$pdo->prepare(
   "SELECT
      id,entry_type,amount,balance_before,balance_after,
      transaction_at,payment_method,purpose,description,reference_no,remarks
    FROM staff_salary_ledger
    WHERE salary_record_id=? AND tenant_id=?
    ORDER BY transaction_at DESC,id DESC"
  );
  $q->execute([$recordId,$s['tenant']]);
  $rows=$q->fetchAll(PDO::FETCH_ASSOC);

  $typeLabels=[
   'opening_salary'=>'Opening Salary',
   'allowance'=>'Allowance Added',
   'allowance_reversal'=>'Allowance Reduced',
   'deduction'=>'Deduction Applied',
   'deduction_reversal'=>'Deduction Reduced',
   'payment'=>'Salary Payment'
  ];

  foreach($rows as &$row){
   $row['entry_label']=$typeLabels[$row['entry_type']]??ucwords(str_replace('_',' ',$row['entry_type']));
   $row['transaction_display']=date(
    'd-m-Y h:i A',
    strtotime((string)$row['transaction_at'])
   );
  }
  unset($row);

  $totalSalary=round(
   (float)$record['fixed_salary']
   +(float)$record['allowances']
   -(float)$record['deductions'],
   2
  );
  $totalPaid=round((float)$record['total_paid'],2);
  $balance=max(0,round($totalSalary-$totalPaid,2));

  out(true,'Salary ledger loaded.',[
   'staff'=>$staff,
   'summary'=>[
    'total_salary'=>$totalSalary,
    'total_paid'=>$totalPaid,
    'remaining_balance'=>$balance,
    'entry_count'=>count($rows)
   ],
   'records'=>$rows,
   'csrf_token'=>$_SESSION['salary_csrf_token'],
   'build'=>SALARY_BUILD
  ]);
 }
 if($action==='save'){
  csrf($in);

  $staffId=(int)($in['staff_id']??0);
  $month=trim((string)($in['month']??''));
  $staff=one($pdo,$s,$staffId,$month);

  $fixed=round((float)$staff['fixed_salary'],2);
  $oldAllowance=round((float)($staff['allowances']??0),2);
  $oldDeduction=round((float)($staff['deductions']??0),2);

  $allowanceAmount=round(max(0,(float)($in['allowance_amount']??0)),2);
  $allowanceDescription=mb_substr(
   trim((string)($in['allowance_description']??'')),
   0,
   255
  );

  $deductionAmount=round(max(0,(float)($in['deduction_amount']??0)),2);
  $deductionDescription=mb_substr(
   trim((string)($in['deduction_description']??'')),
   0,
   255
  );

  if($allowanceAmount>0&&$allowanceDescription===''){
   throw new InvalidArgumentException(
    'Allowance Description is required when adding an allowance.'
   );
  }

  if($deductionAmount>0&&$deductionDescription===''){
   throw new InvalidArgumentException(
    'Deduction Description is required when adding a deduction.'
   );
  }

  $newAllowanceTotal=round($oldAllowance+$allowanceAmount,2);
  $newDeductionTotal=round($oldDeduction+$deductionAmount,2);

  if($newDeductionTotal>$fixed+$newAllowanceTotal+0.009){
   throw new InvalidArgumentException(
    'Total Deductions cannot exceed Fixed Salary plus Allowances.'
   );
  }

  $total=round($fixed+$newAllowanceTotal-$newDeductionTotal,2);
  $payment=round(max(0,(float)($in['payment_amount']??0)),2);

  $method=strtolower(trim((string)($in['payment_method']??'cash')));
  $date=trim((string)($in['payment_date']??''));
  $bankId=(int)($in['bank_account_id']??0);
  $ref=trim((string)($in['reference_no']??''));
  $remarks=mb_substr(trim((string)($in['remarks']??'')),0,255);

  if($payment>0){
   if(!in_array($method,['cash','bank','upi'],true)){
    throw new InvalidArgumentException('Invalid payment mode.');
   }
   if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)){
    throw new InvalidArgumentException('Payment date is required.');
   }
   if($method==='bank'&&$bankId<=0){
    throw new InvalidArgumentException('Select the company bank account.');
   }
   if(in_array($method,['bank','upi'],true)&&$ref===''){
    throw new InvalidArgumentException(
     'Transaction / Reference Number is required.'
    );
   }
   if($method!=='bank')$bankId=0;
  }else{
   $date='';
   $method='cash';
   $bankId=0;
   $ref='';
  }

  $pdo->beginTransaction();

  $branch=$s['branch']?:((int)$staff['branch_id']?:1);
  $recordId=(int)($staff['salary_record_id']??0);
  $salaryMonth=monthDate($month);
  $transactionAt=($date!==''?$date:date('Y-m-d')).' '.date('H:i:s');

  if($recordId<=0){
   $q=$pdo->prepare(
    "INSERT INTO staff_salary_records(
      tenant_id,branch_id,staff_id,salary_month,
      basic_salary,allowances,deductions,advance_adjustment,
      net_salary,payment_date,payment_status,payment_method,
      bank_account_id,reference_no,remarks,created_by,updated_by
     ) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
   );
   $q->execute([
    $s['tenant'],$branch,$staffId,$salaryMonth,
    $fixed,$newAllowanceTotal,$newDeductionTotal,0,$total,
    null,'pending',null,null,null,$remarks?:null,
    $s['user'],$s['user']
   ]);
   $recordId=(int)$pdo->lastInsertId();

   ledgerAdd(
    $pdo,$s,$recordId,$staffId,$salaryMonth,
    'opening_salary',$fixed,0,$fixed,$transactionAt,
    null,null,'Monthly fixed salary','opening-'.$recordId,
    'Monthly Salary','Monthly fixed salary'
   );
  }else{
   ensureSalaryLedger($pdo,$s,$recordId);
  }

  $balance=ledgerLastBalance($pdo,$recordId,true);

  if($allowanceAmount>0){
   $after=round($balance+$allowanceAmount,2);
   ledgerAdd(
    $pdo,$s,$recordId,$staffId,$salaryMonth,
    'allowance',$allowanceAmount,$balance,$after,$transactionAt,
    null,null,$allowanceDescription,
    'allowance-'.$recordId.'-'.date('YmdHis').'-'.random_int(100,999),
    'Allowance',$allowanceDescription
   );
   $balance=$after;
  }

  if($deductionAmount>0){
   $after=max(0,round($balance-$deductionAmount,2));
   ledgerAdd(
    $pdo,$s,$recordId,$staffId,$salaryMonth,
    'deduction',$deductionAmount,$balance,$after,$transactionAt,
    null,null,$deductionDescription,
    'deduction-'.$recordId.'-'.date('YmdHis').'-'.random_int(100,999),
    'Deduction',$deductionDescription
   );
   $balance=$after;
  }

  $q=$pdo->prepare(
   "UPDATE staff_salary_records SET
     branch_id=?,basic_salary=?,allowances=?,deductions=?,
     advance_adjustment=0,net_salary=?,remarks=?,updated_by=?
    WHERE id=? AND tenant_id=?"
  );
  $q->execute([
   $branch,$fixed,$newAllowanceTotal,$newDeductionTotal,
   $total,$remarks?:null,$s['user'],$recordId,$s['tenant']
  ]);

  $sum=$pdo->prepare(
   "SELECT COALESCE(SUM(payment_amount),0)
    FROM staff_salary_transactions
    WHERE salary_record_id=?"
  );
  $sum->execute([$recordId]);
  $paid=round((float)$sum->fetchColumn(),2);
  $balance=max(0,round($total-$paid,2));

  if($payment>$balance+0.009){
   throw new InvalidArgumentException(
    'Payment amount cannot exceed the current balance of ₹'.
    number_format($balance,2).'.'
   );
  }

  if($payment<=0){
   $status=$balance<=0.009?'paid':($paid>0?'partial':'pending');
   $q=$pdo->prepare(
    "UPDATE staff_salary_records
     SET payment_status=?,updated_by=?
     WHERE id=? AND tenant_id=?"
   );
   $q->execute([$status,$s['user'],$recordId,$s['tenant']]);
   $pdo->commit();

   out(true,'Salary adjustment saved successfully.',[
    'id'=>$recordId,
    'allowance_added'=>$allowanceAmount,
    'deduction_added'=>$deductionAmount,
    'balance_amount'=>$balance,
    'status'=>$status,
    'csrf_token'=>$_SESSION['salary_csrf_token']
   ]);
  }

  $after=max(0,round($balance-$payment,2));
  $paymentNo='SAL-'.date('ymd').'-'.
   str_pad((string)random_int(1,999999),6,'0',STR_PAD_LEFT);

  $q=$pdo->prepare(
   "INSERT INTO staff_salary_transactions(
     tenant_id,branch_id,salary_record_id,staff_id,salary_month,
     payment_no,payment_amount,balance_before,balance_after,
     payment_date,payment_method,bank_account_id,
     reference_no,remarks,created_by
    ) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
  );
  $q->execute([
   $s['tenant'],$branch,$recordId,$staffId,$salaryMonth,
   $paymentNo,$payment,$balance,$after,
   $date,$method,$bankId?:null,$ref?:null,$remarks?:null,$s['user']
  ]);
  $paymentId=(int)$pdo->lastInsertId();

  ledgerAdd(
   $pdo,$s,$recordId,$staffId,$salaryMonth,
   'payment',$payment,$balance,$after,$transactionAt,
   $method,$ref?:null,$remarks?:null,
   'payment-'.$paymentId,
   'Salary Payment',$remarks?:null
  );

  $status=$after<=0.009?'paid':'partial';
  $q=$pdo->prepare(
   "UPDATE staff_salary_records SET
     payment_date=?,payment_status=?,payment_method=?,
     bank_account_id=?,reference_no=?,remarks=?,updated_by=?
    WHERE id=? AND tenant_id=?"
  );
  $q->execute([
   $date,$status,$method,$bankId?:null,$ref?:null,
   $remarks?:null,$s['user'],$recordId,$s['tenant']
  ]);

  $pdo->commit();

  out(true,'Payment saved. Remaining balance: ₹'.number_format($after,2),[
   'id'=>$recordId,
   'payment_no'=>$paymentNo,
   'allowance_added'=>$allowanceAmount,
   'deduction_added'=>$deductionAmount,
   'paid_amount'=>$payment,
   'balance_amount'=>$after,
   'status'=>$status,
   'csrf_token'=>$_SESSION['salary_csrf_token']
  ]);
 }
 if($action==='payslip'){$id=(int)($_GET['id']??0);$q=$pdo->prepare("SELECT ssr.*,sm.staff_code,".staffName()." staff_name,sd.department_name,sdes.designation_name,COALESCE((SELECT SUM(tx.payment_amount) FROM staff_salary_transactions tx WHERE tx.salary_record_id=ssr.id),0) total_paid FROM staff_salary_records ssr INNER JOIN staff_members sm ON sm.id=ssr.staff_id AND sm.tenant_id=ssr.tenant_id LEFT JOIN staff_departments sd ON sd.id=sm.department_id AND sd.tenant_id=sm.tenant_id LEFT JOIN staff_designations sdes ON sdes.id=sm.designation_id AND sdes.tenant_id=sm.tenant_id WHERE ssr.id=? AND ssr.tenant_id=? LIMIT 1");$q->execute([$id,$s['tenant']]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)die('Salary record not found.');$balance=max(0,(float)$r['net_salary']-(float)$r['total_paid']);while(ob_get_level()>0)ob_end_clean();header('Content-Type:text/html;charset=utf-8');?><!doctype html><html><head><meta charset="utf-8"><title>Salary Payslip</title><style>*{box-sizing:border-box}body{font-family:Arial;background:#eef2f7;padding:28px;color:#172033}.actions,.slip{max-width:850px;margin:auto}.actions{margin-bottom:12px}.actions button{padding:10px 18px;border:0;border-radius:7px;background:#1d4ed8;color:#fff;font-weight:700}.slip{background:#fff;border:1px solid #dce3ef}.head{padding:25px 30px;background:#173b83;color:#fff;display:flex;justify-content:space-between}.body{padding:25px 30px}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.box{border:1px solid #e3e8f1;border-radius:8px;padding:11px}.box small{display:block;color:#64748b;font-size:10px}.box strong{display:block;margin-top:5px}table{width:100%;border-collapse:collapse;margin-top:18px}th,td{border:1px solid #e3e8f1;padding:10px;font-size:12px}th{background:#f8fafc;text-align:left}.right{text-align:right}.summary{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:15px}.sum{padding:14px;border-radius:9px;border:1px solid #dce3ef}.sum strong{display:block;font-size:22px;margin-top:5px}.paid{background:#ecfdf5}.balance{background:#fff7ed}@media print{body{padding:0;background:#fff}.actions{display:none}.slip{border:0}}</style></head><body><div class="actions"><button onclick="window.print()">Print Payslip</button></div><section class="slip"><header class="head"><div><h1><?=htmlspecialchars($_SESSION['school_name']??'School Salary Payslip')?></h1><p><?=htmlspecialchars(monthLabel($r['salary_month']))?></p></div><strong><?=htmlspecialchars($balance<=0.009?'Paid':'Partial')?></strong></header><div class="body"><div class="grid"><div class="box"><small>Staff</small><strong><?=htmlspecialchars($r['staff_name'])?></strong></div><div class="box"><small>Staff Code</small><strong><?=htmlspecialchars($r['staff_code'])?></strong></div><div class="box"><small>Department</small><strong><?=htmlspecialchars($r['department_name']??'-')?></strong></div></div><table><tr><th>Description</th><th class="right">Amount</th></tr><tr><td>Fixed Salary</td><td class="right">₹<?=number_format((float)$r['basic_salary'],2)?></td></tr><tr><td>Allowances</td><td class="right">₹<?=number_format((float)$r['allowances'],2)?></td></tr><tr><td>Deductions</td><td class="right">- ₹<?=number_format((float)$r['deductions'],2)?></td></tr></table><div class="summary"><div class="sum"><small>Total Salary</small><strong>₹<?=number_format((float)$r['net_salary'],2)?></strong></div><div class="sum paid"><small>Total Paid</small><strong>₹<?=number_format((float)$r['total_paid'],2)?></strong></div><div class="sum balance"><small>Balance</small><strong>₹<?=number_format($balance,2)?></strong></div></div></div></section></body></html><?php exit;}
 out(false,'Invalid Salary action.',[],400);
}catch(InvalidArgumentException $e){if($pdo->inTransaction())$pdo->rollBack();out(false,$e->getMessage(),[],422);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('salary '.SALARY_BUILD.' '.$e->getMessage());$local=str_contains(strtolower((string)($_SERVER['HTTP_HOST']??'')),'localhost')||str_contains((string)($_SERVER['HTTP_HOST']??''),'127.0.0.1');out(false,$local?'Salary request failed ['.SALARY_BUILD.']: '.$e->getMessage():'Unable to complete Salary request.',[],500);}

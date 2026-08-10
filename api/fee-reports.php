<?php
declare(strict_types=1);
ob_start();ini_set('display_errors','0');error_reporting(E_ALL);require_once dirname(__DIR__).'/includes/bootstrap.php';

function frOut(bool $success,string $message='',array $data=[],int $status=200):never{while(ob_get_level()>0){ob_end_clean();}if(!headers_sent()){http_response_code($status);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');}echo json_encode(['success'=>$success,'message'=>$message,'data'=>$data],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
function frScope():array{$u=function_exists('current_user')?current_user():[];$u=is_array($u)?$u:[];return['tenant_id'=>(int)($u['tenant_id']??$u['school_id']??$_SESSION['tenant_id']??$_SESSION['school_id']??0),'branch_id'=>(int)($u['branch_id']??$_SESSION['branch_id']??0)];}
function frTable(PDO $pdo,string $table):bool{$s=$pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:table");$s->execute(['table'=>$table]);return(int)$s->fetchColumn()>0;}
function frName():string{return"TRIM(CONCAT(COALESCE(st.first_name,''),CASE WHEN COALESCE(st.last_name,'')='' THEN '' ELSE CONCAT(' ',st.last_name) END))";}
function frMeta(PDO $pdo,int $tenantId):array{$y=$pdo->prepare("SELECT id,year_name,is_current,start_date,end_date FROM academic_years WHERE tenant_id=:tenant_id ORDER BY is_current DESC,start_date DESC,id DESC");$y->execute(['tenant_id'=>$tenantId]);$c=$pdo->prepare("SELECT id,academic_year_id,class_name,display_order FROM classes WHERE tenant_id=:tenant_id AND status='active' ORDER BY academic_year_id DESC,display_order,class_name");$c->execute(['tenant_id'=>$tenantId]);$s=$pdo->prepare("SELECT sec.id,sec.class_id,sec.section_name,c.academic_year_id FROM sections sec INNER JOIN classes c ON c.id=sec.class_id AND c.tenant_id=sec.tenant_id WHERE sec.tenant_id=:tenant_id AND sec.status='active' ORDER BY c.display_order,sec.section_name");$s->execute(['tenant_id'=>$tenantId]);return['years'=>$y->fetchAll(PDO::FETCH_ASSOC),'classes'=>$c->fetchAll(PDO::FETCH_ASSOC),'sections'=>$s->fetchAll(PDO::FETCH_ASSOC)];}
function frFilters(PDO $pdo,array $scope,array $input):array{$meta=frMeta($pdo,$scope['tenant_id']);$yearId=(int)($input['academic_year_id']??0);if($yearId<=0&&$meta['years'])$yearId=(int)$meta['years'][0]['id'];$where=['sfi.tenant_id=:tenant_id','sfi.academic_year_id=:year_id',"sfi.item_status<>'cancelled'","a.assignment_status='active'"];$params=['tenant_id'=>$scope['tenant_id'],'year_id'=>$yearId];if($scope['branch_id']>0){$where[]='st.branch_id=:branch_id';$params['branch_id']=$scope['branch_id'];}$class=(string)($input['class_id']??'all');if($class!==''&&$class!=='all'){$where[]='e.class_id=:class_id';$params['class_id']=(int)$class;}$section=(string)($input['section_id']??'all');if($section!==''&&$section!=='all'){$where[]='e.section_id=:section_id';$params['section_id']=(int)$section;}$type=strtolower((string)($input['item_type']??'all'));if(in_array($type,['admission','tuition','term','transport','additional'],true)){$where[]='sfi.item_type=:item_type';$params['item_type']=$type;}$from=trim((string)($input['date_from']??''));if($from!==''){$where[]='sfi.due_date>=:date_from';$params['date_from']=$from;}$to=trim((string)($input['date_to']??''));if($to!==''){$where[]='sfi.due_date<=:date_to';$params['date_to']=$to;}$search=trim((string)($input['search']??''));if($search!==''){$where[]="(st.admission_no LIKE :sa OR st.first_name LIKE :sf OR st.last_name LIKE :sl OR CONCAT_WS(' ',st.first_name,st.last_name) LIKE :sn OR c.class_name LIKE :sc)";$v='%'.$search.'%';$params+=['sa'=>$v,'sf'=>$v,'sl'=>$v,'sn'=>$v,'sc'=>$v];}return compact('meta','yearId','where','params');}
function frBase():string{return" FROM student_fee_items sfi INNER JOIN student_fee_assignments a ON a.id=sfi.assignment_id AND a.tenant_id=sfi.tenant_id INNER JOIN students st ON st.id=sfi.student_id AND st.tenant_id=sfi.tenant_id LEFT JOIN student_enrollments e ON e.student_id=sfi.student_id AND e.academic_year_id=sfi.academic_year_id AND e.tenant_id=sfi.tenant_id AND e.enrollment_status='active' LEFT JOIN classes c ON c.id=e.class_id AND c.tenant_id=e.tenant_id LEFT JOIN sections sec ON sec.id=e.section_id AND sec.tenant_id=e.tenant_id LEFT JOIN academic_years ay ON ay.id=sfi.academic_year_id AND ay.tenant_id=sfi.tenant_id ";}

function frClassSummary(PDO $pdo,array $scope,array $input):array{$f=frFilters($pdo,$scope,$input);$sql="SELECT e.class_id,c.class_name,c.display_order,ay.year_name,COUNT(DISTINCT sfi.student_id) student_count,SUM(sfi.original_amount) total_fee,SUM(sfi.paid_amount) paid_amount,SUM(sfi.balance_amount) balance_amount".frBase()." WHERE ".implode(' AND ',$f['where'])." GROUP BY e.class_id,c.class_name,c.display_order,ay.year_name ORDER BY c.display_order,c.class_name";$s=$pdo->prepare($sql);$s->execute($f['params']);$rows=$s->fetchAll(PDO::FETCH_ASSOC);$status=strtolower((string)($input['payment_status']??'all'));foreach($rows as &$r){$gross=(float)$r['total_fee'];$paid=(float)$r['paid_amount'];$bal=(float)$r['balance_amount'];$r['payment_status']=$bal<=.009?'paid':($paid>0?'partial':'unpaid');$r['collection_percentage']=$gross>0?round(($paid/$gross)*100,2):0;}unset($r);if(in_array($status,['paid','partial','unpaid'],true))$rows=array_values(array_filter($rows,fn($r)=>$r['payment_status']===$status));
$stats=['total_fee'=>0.0,'paid_amount'=>0.0,'balance_amount'=>0.0,'students'=>0,'classes'=>count($rows)];
$studentCount=0;
foreach($rows as $row){
    $stats['total_fee']+=(float)$row['total_fee'];
    $stats['paid_amount']+=(float)$row['paid_amount'];
    $stats['balance_amount']+=(float)$row['balance_amount'];
    $studentCount+=(int)$row['student_count'];
}
$stats['students']=$studentCount;
return['meta'=>$f['meta'],'classes'=>$rows,'stats'=>$stats];}

function frReceiptMap(PDO $pdo,array $scope,int $yearId,array $studentIds):array{if(!$studentIds||!frTable($pdo,'fee_receipts'))return[];$ph=implode(',',array_fill(0,count($studentIds),'?'));$params=[$scope['tenant_id'],$yearId,...$studentIds];$sql="SELECT r.student_id,MAX(r.receipt_date) payment_date,DATE_FORMAT(MAX(r.receipt_date),'%d-%m-%Y') payment_date_display,GROUP_CONCAT(DISTINCT r.receipt_no ORDER BY r.receipt_date DESC SEPARATOR ', ') receipt_no";if(frTable($pdo,'fee_payments')&&frTable($pdo,'payment_methods'))$sql.=",GROUP_CONCAT(DISTINCT pm.method_name ORDER BY pm.method_name SEPARATOR ', ') payment_mode";else$sql.=",'' payment_mode";$sql.=" FROM fee_receipts r";if(frTable($pdo,'fee_payments')&&frTable($pdo,'payment_methods'))$sql.=" LEFT JOIN fee_payments fp ON fp.receipt_id=r.id AND fp.tenant_id=r.tenant_id LEFT JOIN payment_methods pm ON pm.id=fp.payment_method_id AND pm.tenant_id=fp.tenant_id";$sql.=" WHERE r.tenant_id=? AND r.academic_year_id=? AND r.student_id IN($ph)";if($scope['branch_id']>0){$sql.=" AND r.branch_id=?";$params[]=$scope['branch_id'];}$sql.=" GROUP BY r.student_id";$s=$pdo->prepare($sql);$s->execute($params);$map=[];foreach($s->fetchAll(PDO::FETCH_ASSOC) as $r)$map[(int)$r['student_id']]=$r;return$map;}

function frClassLedger(PDO $pdo,array $scope,array $input):array{$f=frFilters($pdo,$scope,$input);if((string)($input['class_id']??'all')==='all')throw new InvalidArgumentException('Select a class.');$name=frName();$sql="SELECT sfi.student_id,$name student_name,st.admission_no,e.class_id,c.class_name,sec.section_name,ay.year_name,sfi.item_type fee_type,SUM(sfi.original_amount) total_fee,SUM(sfi.paid_amount) paid_amount,SUM(sfi.balance_amount) balance_amount,SUM(sfi.discount_amount) discount_amount".frBase()." WHERE ".implode(' AND ',$f['where'])." GROUP BY sfi.student_id,st.first_name,st.last_name,st.admission_no,e.class_id,c.class_name,sec.section_name,ay.year_name,sfi.item_type ORDER BY st.first_name,sfi.item_type";$s=$pdo->prepare($sql);$s->execute($f['params']);$rows=$s->fetchAll(PDO::FETCH_ASSOC);$map=frReceiptMap($pdo,$scope,$f['yearId'],array_values(array_unique(array_map(fn($r)=>(int)$r['student_id'],$rows))));$summary=['students'=>0,'total_fee'=>0,'paid_amount'=>0,'discount_amount'=>0,'balance_amount'=>0,'receipts'=>0];$studentSet=[];$receiptSet=[];$statusFilter=strtolower((string)($input['payment_status']??'all'));foreach($rows as &$r){$paid=(float)$r['paid_amount'];$bal=(float)$r['balance_amount'];$r['payment_status']=$bal<=.009?'paid':($paid>0?'partial':'unpaid');$receipt=$map[(int)$r['student_id']]??[];$r+=['payment_date_display'=>$receipt['payment_date_display']??'','payment_mode'=>$receipt['payment_mode']??'','receipt_no'=>$receipt['receipt_no']??''];$studentSet[(int)$r['student_id']]=true;if($r['receipt_no']!=='')$receiptSet[$r['receipt_no']]=true;$summary['total_fee']+=(float)$r['total_fee'];$summary['paid_amount']+=(float)$r['paid_amount'];$summary['discount_amount']+=(float)$r['discount_amount'];$summary['balance_amount']+=(float)$r['balance_amount'];}unset($r);if(in_array($statusFilter,['paid','partial','unpaid'],true))$rows=array_values(array_filter($rows,fn($r)=>$r['payment_status']===$statusFilter));$summary['students']=count($studentSet);$summary['receipts']=count($receiptSet);$class=$rows[0]??['class_id'=>(int)$input['class_id'],'class_name'=>'Class','year_name'=>'','section_name'=>''];return['meta'=>$f['meta'],'rows'=>$rows,'summary'=>$summary,'class'=>$class];}

function frStudentSearch(PDO $pdo,array $scope,array $input):array{$f=frFilters($pdo,$scope,$input);$name=frName();$sql="SELECT DISTINCT st.id,$name student_name,st.admission_no,c.class_name,sec.section_name,ay.year_name".frBase()." WHERE ".implode(' AND ',$f['where'])." ORDER BY st.first_name,st.last_name LIMIT 100";$s=$pdo->prepare($sql);$s->execute($f['params']);return['meta'=>$f['meta'],'students'=>$s->fetchAll(PDO::FETCH_ASSOC)];}

function frStudentLedger(PDO $pdo,array $scope,array $input):array{$studentId=(int)($input['student_id']??0);if($studentId<=0)throw new InvalidArgumentException('Select a student.');$f=frFilters($pdo,$scope,$input);$f['where'][]='sfi.student_id=:student_id';$f['params']['student_id']=$studentId;$name=frName();$chargesSql="SELECT sfi.due_date entry_date,DATE_FORMAT(sfi.due_date,'%d-%m-%Y') entry_date_display,CONCAT('FEE-',sfi.id) reference_no,sfi.item_name description,sfi.item_type fee_type,sfi.original_amount charge_amount,0 payment_amount,sfi.discount_amount discount_amount,0 fine_amount,sfi.balance_amount balance_amount,sfi.item_status status".frBase()." WHERE ".implode(' AND ',$f['where']);$s=$pdo->prepare($chargesSql);$s->execute($f['params']);$entries=$s->fetchAll(PDO::FETCH_ASSOC);
if(frTable($pdo,'fee_receipts')){$params=['tenant_id'=>$scope['tenant_id'],'year_id'=>$f['yearId'],'student_id'=>$studentId];$where=['r.tenant_id=:tenant_id','r.academic_year_id=:year_id','r.student_id=:student_id'];if($scope['branch_id']>0){$where[]='r.branch_id=:branch_id';$params['branch_id']=$scope['branch_id'];}$from=trim((string)($input['date_from']??''));if($from!==''){$where[]='DATE(r.receipt_date)>=:date_from';$params['date_from']=$from;}$to=trim((string)($input['date_to']??''));if($to!==''){$where[]='DATE(r.receipt_date)<=:date_to';$params['date_to']=$to;}$sql="SELECT DATE(r.receipt_date) entry_date,DATE_FORMAT(r.receipt_date,'%d-%m-%Y') entry_date_display,r.receipt_no reference_no,CONCAT('Payment Receipt ',r.receipt_no) description,'payment' fee_type,0 charge_amount,r.paid_amount payment_amount,r.discount_amount discount_amount,0 fine_amount,0 balance_amount,r.payment_status status FROM fee_receipts r WHERE ".implode(' AND ',$where);$rs=$pdo->prepare($sql);$rs->execute($params);$entries=array_merge($entries,$rs->fetchAll(PDO::FETCH_ASSOC));}
usort($entries,fn($a,$b)=>strcmp((string)$a['entry_date'],(string)$b['entry_date'])?:strcmp((string)$a['reference_no'],(string)$b['reference_no']));$running=0.0;$summary=['opening_balance'=>0.0,'charges'=>0.0,'payments'=>0.0,'discounts'=>0.0,'fines'=>0.0,'closing_balance'=>0.0];foreach($entries as &$e){$opening=$running;$charge=(float)$e['charge_amount'];$payment=(float)$e['payment_amount'];$discount=(float)$e['discount_amount'];$fine=(float)$e['fine_amount'];$running=$opening+$charge+$fine-$payment-$discount;$e['opening_balance']=round($opening,2);$e['running_balance']=round($running,2);$e['balance_amount']=round(max(0,$running),2);$summary['charges']+=$charge;$summary['payments']+=$payment;$summary['discounts']+=$discount;$summary['fines']+=$fine;}unset($e);$summary['closing_balance']=round($running,2);
$st=$pdo->prepare("SELECT st.id,$name student_name,st.admission_no,c.class_name,sec.section_name,ay.year_name,a.payment_status FROM students st LEFT JOIN student_enrollments e ON e.student_id=st.id AND e.academic_year_id=:year_id AND e.tenant_id=st.tenant_id LEFT JOIN classes c ON c.id=e.class_id LEFT JOIN sections sec ON sec.id=e.section_id LEFT JOIN academic_years ay ON ay.id=e.academic_year_id LEFT JOIN student_fee_assignments a ON a.student_id=st.id AND a.academic_year_id=:year_id2 AND a.tenant_id=st.tenant_id AND a.assignment_status='active' WHERE st.id=:student_id AND st.tenant_id=:tenant_id LIMIT 1");$st->execute(['year_id'=>$f['yearId'],'year_id2'=>$f['yearId'],'student_id'=>$studentId,'tenant_id'=>$scope['tenant_id']]);$student=$st->fetch(PDO::FETCH_ASSOC)?:[];return['meta'=>$f['meta'],'student'=>$student,'rows'=>$entries,'summary'=>$summary];}

function frHtml(string $title,array $rows,array $summary,array $headers,bool $autoPrint=false):never{while(ob_get_level()>0){ob_end_clean();}header('Content-Type:text/html; charset=utf-8');echo'<!doctype html><html><head><meta charset="utf-8"><title>'.htmlspecialchars($title).'</title><style>body{font-family:Arial,sans-serif;margin:24px;color:#111827}h1{font-size:22px;margin-bottom:4px}.muted{font-size:12px;color:#64748b}.summary{display:flex;gap:10px;flex-wrap:wrap;margin:16px 0}.summary div{border:1px solid #dbe2ef;border-radius:8px;padding:10px 14px}table{width:100%;border-collapse:collapse;font-size:10px}th,td{border:1px solid #dbe2ef;padding:6px}th{background:#eef2ff}@media print{button{display:none}}</style></head><body><button onclick="window.print()">Print / Save PDF</button><h1>'.htmlspecialchars($title).'</h1><div class="muted">Generated '.date('d-m-Y h:i A').'</div><div class="summary">';foreach($summary as $k=>$v)echo'<div><small>'.htmlspecialchars(ucwords(str_replace('_',' ',$k))).'</small><strong>'.(is_numeric($v)?number_format((float)$v,2):htmlspecialchars((string)$v)).'</strong></div>';echo'</div><table><thead><tr>';foreach($headers as $label=>$key)echo'<th>'.htmlspecialchars($label).'</th>';echo'</tr></thead><tbody>';foreach($rows as $r){echo'<tr>';foreach($headers as $key)echo'<td>'.htmlspecialchars((string)($r[$key]??'')).'</td>';echo'</tr>';}echo'</tbody></table>';if($autoPrint)echo'<script>window.onload=()=>window.print()</script>';echo'</body></html>';exit;}

function frStudentPrint(array $data,bool $autoPrint=true):never{
    while(ob_get_level()>0){ob_end_clean();}
    header('Content-Type:text/html; charset=utf-8');

    $student=$data['student']??[];
    $rows=$data['rows']??[];
    $studentName=(string)($student['student_name']??'Student');
    $admissionNo=(string)($student['admission_no']??'-');
    $yearName=(string)($student['year_name']??'-');
    $className=(string)($student['class_name']??'-');
    $sectionName=(string)($student['section_name']??'-');

    echo '<!doctype html><html><head><meta charset="utf-8">';
    echo '<title>'.htmlspecialchars($studentName).' - Fee Ledger</title>';
    echo '<style>
        @page{size:A4 landscape;margin:10mm}
        *{box-sizing:border-box}
        body{font-family:Arial,sans-serif;color:#111827;margin:0;background:#fff}
        .toolbar{display:flex;justify-content:flex-end;margin-bottom:12px}
        .toolbar button{border:0;border-radius:7px;background:#4f46e5;color:#fff;padding:9px 15px;font-weight:700;cursor:pointer}
        .report-title{text-align:center;margin-bottom:14px}
        .report-title h1{font-size:21px;margin:0 0 5px}
        .report-title p{font-size:11px;color:#64748b;margin:0}
        .student-info{width:100%;border-collapse:collapse;margin-bottom:12px;font-size:11px}
        .student-info td{border:1px solid #cfd7e6;padding:7px}
        .student-info .label{font-weight:700;background:#f4f6fb;width:13%}
        .ledger{width:100%;border-collapse:collapse;font-size:9px}
        .ledger th,.ledger td{border:1px solid #cfd7e6;padding:5px;text-align:left}
        .ledger th{background:#e9edff;font-weight:700}
        .amount{text-align:right!important;white-space:nowrap}
        .running{font-weight:700}
        .footer-note{font-size:9px;color:#64748b;margin-top:8px;text-align:right}
        @media print{
            .toolbar{display:none}
            body{-webkit-print-color-adjust:exact;print-color-adjust:exact}
        }
    </style></head><body>';

    echo '<div class="toolbar"><button onclick="window.print()">Print Ledger</button></div>';
    echo '<div class="report-title"><h1>Student Fee Ledger</h1><p>Complete chronological fee transaction report</p></div>';

    echo '<table class="student-info">';
    echo '<tr><td class="label">Student Name</td><td>'.htmlspecialchars($studentName).'</td>';
    echo '<td class="label">Admission No.</td><td>'.htmlspecialchars($admissionNo).'</td></tr>';
    echo '<tr><td class="label">Academic Year</td><td>'.htmlspecialchars($yearName).'</td>';
    echo '<td class="label">Class / Section</td><td>'.htmlspecialchars($className.' / '.$sectionName).'</td></tr>';
    echo '</table>';

    echo '<table class="ledger"><thead><tr>';
    echo '<th>Date</th><th>Reference</th><th>Description</th><th>Fee Type</th>';
    echo '<th>Opening Balance</th><th>Charges</th><th>Payments</th><th>Discount</th>';
    echo '<th>Fine</th><th>Balance</th><th>Running Balance</th><th>Status</th>';
    echo '</tr></thead><tbody>';

    if($rows===[]){
        echo '<tr><td colspan="12" style="text-align:center;padding:20px">No ledger transactions found.</td></tr>';
    }else{
        foreach($rows as $row){
            echo '<tr>';
            echo '<td>'.htmlspecialchars((string)($row['entry_date_display']??$row['entry_date']??'-')).'</td>';
            echo '<td>'.htmlspecialchars((string)($row['reference_no']??'-')).'</td>';
            echo '<td>'.htmlspecialchars((string)($row['description']??'-')).'</td>';
            echo '<td>'.htmlspecialchars((string)($row['fee_type']??'-')).'</td>';
            echo '<td class="amount">'.number_format((float)($row['opening_balance']??0),2).'</td>';
            echo '<td class="amount">'.number_format((float)($row['charge_amount']??0),2).'</td>';
            echo '<td class="amount">'.number_format((float)($row['payment_amount']??0),2).'</td>';
            echo '<td class="amount">'.number_format((float)($row['discount_amount']??0),2).'</td>';
            echo '<td class="amount">'.number_format((float)($row['fine_amount']??0),2).'</td>';
            echo '<td class="amount">'.number_format((float)($row['balance_amount']??0),2).'</td>';
            echo '<td class="amount running">'.number_format((float)($row['running_balance']??0),2).'</td>';
            echo '<td>'.htmlspecialchars((string)($row['status']??'-')).'</td>';
            echo '</tr>';
        }
    }

    echo '</tbody></table>';
    echo '<div class="footer-note">Generated on '.date('d-m-Y h:i A').'</div>';

    if($autoPrint){
        echo '<script>window.addEventListener("load",()=>setTimeout(()=>window.print(),250));</script>';
    }
    echo '</body></html>';
    exit;
}

function frExcel(string $filename,array $rows,array $headers):never{while(ob_get_level()>0){ob_end_clean();}header('Content-Type:application/vnd.ms-excel; charset=utf-8');header('Content-Disposition:attachment; filename="'.$filename.'-'.date('Ymd-His').'.xls"');echo"\xEF\xBB\xBF<table border='1'><tr>";foreach($headers as $label=>$key)echo'<th>'.htmlspecialchars($label).'</th>';echo'</tr>';foreach($rows as $r){echo'<tr>';foreach($headers as $key)echo'<td>'.htmlspecialchars((string)($r[$key]??'')).'</td>';echo'</tr>';}echo'</table>';exit;}

if(!isset($pdo)||!$pdo instanceof PDO)frOut(false,'Database unavailable.',[],500);$scope=frScope();if($scope['tenant_id']<=0)frOut(false,'School tenant session was not found.',[],401);$action=strtolower(trim((string)($_GET['action']??'class_summary')));
try{
if($action==='class_summary')frOut(true,'Class summary loaded.',frClassSummary($pdo,$scope,$_GET));
if($action==='class_ledger')frOut(true,'Class ledger loaded.',frClassLedger($pdo,$scope,$_GET));
if($action==='student_search')frOut(true,'Students loaded.',frStudentSearch($pdo,$scope,$_GET));
if($action==='student_ledger')frOut(true,'Student ledger loaded.',frStudentLedger($pdo,$scope,$_GET));
if(in_array($action,['class_print','class_pdf','class_excel'],true)){$data=((string)($_GET['class_id']??'all')==='all')?frClassSummary($pdo,$scope,$_GET):frClassLedger($pdo,$scope,$_GET);$rows=$data['rows']??$data['classes']??[];$summary=$data['summary']??[];$headers=isset($data['rows'])?['Student'=>'student_name','Admission No.'=>'admission_no','Fee Type'=>'fee_type','Total Fee'=>'total_fee','Paid'=>'paid_amount','Balance'=>'balance_amount','Payment Date'=>'payment_date_display','Payment Mode'=>'payment_mode','Receipt No.'=>'receipt_no','Status'=>'payment_status']:['Academic Year'=>'year_name','Class'=>'class_name','Students'=>'student_count','Total Fee'=>'total_fee','Paid'=>'paid_amount','Balance'=>'balance_amount','Status'=>'payment_status'];if($action==='class_excel')frExcel('class-fee-ledger',$rows,$headers);frHtml('Class-wise Fee Ledger',$rows,$summary,$headers,$action==='class_pdf');}
if(in_array($action,['student_print','student_pdf','student_excel'],true)){
    $data=frStudentLedger($pdo,$scope,$_GET);
    $headers=['Date'=>'entry_date_display','Reference'=>'reference_no','Description'=>'description','Fee Type'=>'fee_type','Opening Balance'=>'opening_balance','Charges'=>'charge_amount','Payments'=>'payment_amount','Discount'=>'discount_amount','Fine'=>'fine_amount','Balance'=>'balance_amount','Running Balance'=>'running_balance','Status'=>'status'];
    if($action==='student_excel')frExcel('student-fee-ledger',$data['rows'],$headers);
    frStudentPrint($data,true);
}
frOut(false,'Invalid Fee Reports action.',[],400);
}catch(Throwable $e){error_log('fee-reports.php: '.$e->getMessage());$host=strtolower((string)($_SERVER['HTTP_HOST']??''));frOut(false,(str_contains($host,'localhost')||str_contains($host,'127.0.0.1'))?'Fee Reports request failed: '.$e->getMessage():'Unable to load Fee Reports.',[],500);}

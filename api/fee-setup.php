<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors','0');

require_once dirname(__DIR__).'/includes/bootstrap.php';

function fsOut(bool $success,string $message='',array $data=[],int $status=200):never{
    while(ob_get_level()>0)ob_end_clean();
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['success'=>$success,'message'=>$message,'data'=>$data],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function fsInput():array{
    $json=json_decode((string)file_get_contents('php://input'),true);
    return is_array($json)?$json:$_POST;
}
function fsScope():array{
    $user=function_exists('current_user')?current_user():[];
    return[
        'tenant_id'=>(int)($user['tenant_id']??$user['school_id']??$_SESSION['tenant_id']??$_SESSION['school_id']??0),
        'branch_id'=>(int)($user['branch_id']??$_SESSION['branch_id']??$_SESSION['default_branch_id']??0),
        'user_id'=>(int)($user['id']??$user['user_id']??$_SESSION['user_id']??0),
    ];
}
function fsCsrf(array $input):void{
    $session=(string)($_SESSION['fee_csrf_token']??'');
    $request=(string)($input['csrf_token']??'');
    if($session===''||$request===''||!hash_equals($session,$request)){
        fsOut(false,'Invalid or expired CSRF token. Refresh the page.',[],419);
    }
}
function fsMeta(PDO $pdo,array $scope):array{
    $q=$pdo->prepare("SELECT id,year_name FROM academic_years WHERE tenant_id=:tenant_id ORDER BY is_current DESC,start_date DESC,id DESC");
    $q->execute(['tenant_id'=>$scope['tenant_id']]);$years=$q->fetchAll(PDO::FETCH_ASSOC);

    $q=$pdo->prepare("SELECT id,class_name,academic_year_id FROM classes WHERE tenant_id=:tenant_id AND status='active' ORDER BY display_order,class_name");
    $q->execute(['tenant_id'=>$scope['tenant_id']]);$classes=$q->fetchAll(PDO::FETCH_ASSOC);

    $q=$pdo->prepare("SELECT id,head_name,head_code,is_refundable,status FROM fee_heads WHERE tenant_id=:tenant_id ORDER BY head_name");
    $q->execute(['tenant_id'=>$scope['tenant_id']]);$heads=$q->fetchAll(PDO::FETCH_ASSOC);

    return compact('years','classes','heads');
}
function fsHeads(PDO $pdo,array $scope,array $filters):array{
    $where=['tenant_id=:tenant_id'];$params=['tenant_id'=>$scope['tenant_id']];
    $search=trim((string)($filters['head_search']??''));
    $status=trim((string)($filters['head_status']??''));
    $refundable=trim((string)($filters['head_refundable']??''));
    if($search!==''){$where[]='(head_code LIKE :search OR head_name LIKE :search)';$params['search']='%'.$search.'%';}
    if($status!==''&&$status!=='all'){$where[]='status=:status';$params['status']=$status;}
    if($refundable!==''&&$refundable!=='all'){$where[]='is_refundable=:refundable';$params['refundable']=(int)$refundable;}
    $sort=match((string)($filters['head_sort']??'')){'name_desc'=>'head_name DESC','code_asc'=>'head_code ASC',default=>'head_name ASC'};
    $q=$pdo->prepare("SELECT id,head_name,head_code,is_refundable,status FROM fee_heads WHERE ".implode(' AND ',$where)." ORDER BY $sort");
    $q->execute($params);return $q->fetchAll(PDO::FETCH_ASSOC);
}
function fsStructures(PDO $pdo,array $scope,array $filters):array{
    $where=['fs.tenant_id=:tenant_id'];$params=['tenant_id'=>$scope['tenant_id']];
    $search=trim((string)($filters['structure_search']??''));
    $year=trim((string)($filters['academic_year_id']??''));
    $class=trim((string)($filters['class_id']??''));
    $status=trim((string)($filters['structure_status']??''));
    if($search!==''){$where[]='fs.structure_name LIKE :search';$params['search']='%'.$search.'%';}
    if($year!==''&&$year!=='all'){$where[]='fs.academic_year_id=:year';$params['year']=(int)$year;}
    if($class!==''&&$class!=='all'){$where[]='fs.class_id=:class';$params['class']=(int)$class;}
    if($status!==''&&$status!=='all'){$where[]='fs.status=:status';$params['status']=$status;}
    $q=$pdo->prepare("SELECT fs.*,ay.year_name,c.class_name,COALESCE(SUM(fsi.amount),0) AS total_amount,
        COUNT(DISTINCT fsi.due_date) AS installment_count,MIN(fsi.due_date) AS first_due_date,MAX(fsi.due_date) AS last_due_date
        FROM fee_structures fs
        INNER JOIN academic_years ay ON ay.id=fs.academic_year_id AND ay.tenant_id=fs.tenant_id
        INNER JOIN classes c ON c.id=fs.class_id AND c.tenant_id=fs.tenant_id
        LEFT JOIN fee_structure_items fsi ON fsi.fee_structure_id=fs.id
        WHERE ".implode(' AND ',$where)."
        GROUP BY fs.id ORDER BY ay.start_date DESC,c.display_order,fs.structure_name");
    $q->execute($params);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
    if($rows){
        $item=$pdo->prepare("SELECT fsi.*,fh.head_name,fh.head_code FROM fee_structure_items fsi INNER JOIN fee_heads fh ON fh.id=fsi.fee_head_id WHERE fsi.fee_structure_id=:id ORDER BY fsi.due_date,fh.head_name");
        foreach($rows as &$row){$item->execute(['id'=>$row['id']]);$row['items']=$item->fetchAll(PDO::FETCH_ASSOC);}
    }
    return $rows;
}
function fsStats(array $heads,array $structures):array{
    $installments=[];$amount=0.0;
    foreach($structures as $structure){
        $amount+=(float)$structure['total_amount'];
        foreach($structure['items']??[] as $item){if(!empty($item['due_date']))$installments[$structure['id'].'|'.$item['due_date']]=true;}
    }
    return['heads'=>count($heads),'structures'=>count($structures),'installments'=>count($installments),'amount'=>$amount];
}
function fsStructure(PDO $pdo,array $scope,int $id):array{
    $rows=fsStructures($pdo,$scope,['structure_search'=>'','academic_year_id'=>'all','class_id'=>'all','structure_status'=>'all']);
    foreach($rows as $row)if((int)$row['id']===$id)return $row;
    throw new InvalidArgumentException('Fee structure not found.');
}

if(!isset($pdo)||!$pdo instanceof PDO)fsOut(false,'Database connection unavailable.',[],500);
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
if(empty($_SESSION['fee_csrf_token']))$_SESSION['fee_csrf_token']=bin2hex(random_bytes(32));

$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES,true);
$scope=fsScope();
if($scope['tenant_id']<=0)fsOut(false,'School tenant session was not found.',[],401);

$input=fsInput();
$action=strtolower(trim((string)($input['action']??$_GET['action']??'')));

try{
    if($action==='list'){
        $meta=fsMeta($pdo,$scope);
        $heads=fsHeads($pdo,$scope,$_GET);
        $structures=fsStructures($pdo,$scope,$_GET);
        fsOut(true,'Fee setup loaded.',['meta'=>$meta,'heads'=>$heads,'structures'=>$structures,'stats'=>fsStats($heads,$structures),'csrf_token'=>$_SESSION['fee_csrf_token']]);
    }

    if($action==='view'){
        fsOut(true,'Fee structure loaded.',['structure'=>fsStructure($pdo,$scope,(int)($_GET['id']??0))]);
    }

    if($action==='save'){
        fsCsrf($input);
        $type=trim((string)($input['type']??''));
        $id=(int)($input['id']??0);

        if($type==='head'){
            $code=strtoupper(trim((string)($input['head_code']??'')));
            $name=trim((string)($input['head_name']??''));
            $refundable=(int)($input['is_refundable']??0);
            $status=(string)($input['status']??'active');
            if($code===''||$name==='')throw new InvalidArgumentException('Fee head code and name are required.');
            if(!preg_match('/^[A-Z0-9_-]{2,40}$/',$code))throw new InvalidArgumentException('Fee head code may contain only letters, numbers, underscore and hyphen.');
            if(!in_array($status,['active','inactive'],true))throw new InvalidArgumentException('Invalid fee head status.');

            $q=$pdo->prepare("SELECT id FROM fee_heads WHERE tenant_id=:tenant_id AND head_code=:code AND id<>:id LIMIT 1");
            $q->execute(['tenant_id'=>$scope['tenant_id'],'code'=>$code,'id'=>$id]);
            if($q->fetchColumn())throw new InvalidArgumentException('Fee head code already exists.');

            if($id>0){
                $q=$pdo->prepare("UPDATE fee_heads SET head_code=:code,head_name=:name,is_refundable=:refundable,status=:status WHERE id=:id AND tenant_id=:tenant_id");
                $q->execute(['code'=>$code,'name'=>$name,'refundable'=>$refundable,'status'=>$status,'id'=>$id,'tenant_id'=>$scope['tenant_id']]);
                if($q->rowCount()===0){
                    $check=$pdo->prepare("SELECT id FROM fee_heads WHERE id=:id AND tenant_id=:tenant_id");$check->execute(['id'=>$id,'tenant_id'=>$scope['tenant_id']]);
                    if(!$check->fetchColumn())throw new InvalidArgumentException('Fee head not found.');
                }
            }else{
                $q=$pdo->prepare("INSERT INTO fee_heads(tenant_id,head_name,head_code,is_refundable,status) VALUES(:tenant_id,:name,:code,:refundable,:status)");
                $q->execute(['tenant_id'=>$scope['tenant_id'],'name'=>$name,'code'=>$code,'refundable'=>$refundable,'status'=>$status]);
                $id=(int)$pdo->lastInsertId();
            }
            fsOut(true,'Fee head saved successfully.',['id'=>$id]);
        }

        if($type==='structure'){
            $yearId=(int)($input['academic_year_id']??0);
            $classId=(int)($input['class_id']??0);
            $name=trim((string)($input['structure_name']??''));
            $status=(string)($input['status']??'draft');
            $items=is_array($input['items']??null)?$input['items']:[];
            if($yearId<=0||$classId<=0||$name==='')throw new InvalidArgumentException('Academic year, class and structure name are required.');
            if(!in_array($status,['draft','active','inactive'],true))throw new InvalidArgumentException('Invalid structure status.');
            if(!$items)throw new InvalidArgumentException('At least one fee item is required.');

            $q=$pdo->prepare("SELECT id FROM classes WHERE id=:class_id AND tenant_id=:tenant_id AND academic_year_id=:year_id AND status='active'");
            $q->execute(['class_id'=>$classId,'tenant_id'=>$scope['tenant_id'],'year_id'=>$yearId]);
            if(!$q->fetchColumn())throw new InvalidArgumentException('Selected class does not belong to the academic year.');

            $normalized=[];$seen=[];
            foreach($items as $index=>$item){
                $headId=(int)($item['fee_head_id']??0);
                $amount=round((float)($item['amount']??0),2);
                $dueDate=trim((string)($item['due_date']??''));
                $fineType=(string)($item['fine_type']??'none');
                $fineValue=round(max(0,(float)($item['fine_value']??0)),2);
                if($headId<=0||$amount<=0)throw new InvalidArgumentException('Every fee item requires a valid fee head and amount.');
                if(!in_array($fineType,['none','fixed','daily'],true))throw new InvalidArgumentException('Invalid fine type.');
                if($fineType!=='none'&&$fineValue<=0)throw new InvalidArgumentException('Fine value must be greater than zero when a fine rule is selected.');
                if($fineType==='none')$fineValue=0;
                if($dueDate!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$dueDate))throw new InvalidArgumentException('Invalid due date.');
                $key=$headId.'|'.$dueDate;
                if(isset($seen[$key]))throw new InvalidArgumentException('The same fee head cannot be repeated for the same installment date.');
                $seen[$key]=true;
                $normalized[]=['fee_head_id'=>$headId,'amount'=>$amount,'due_date'=>$dueDate?:null,'fine_type'=>$fineType,'fine_value'=>$fineValue];
            }

            $pdo->beginTransaction();
            $q=$pdo->prepare("SELECT id FROM fee_structures WHERE tenant_id=:tenant_id AND academic_year_id=:year_id AND class_id=:class_id AND structure_name=:name AND id<>:id");
            $q->execute(['tenant_id'=>$scope['tenant_id'],'year_id'=>$yearId,'class_id'=>$classId,'name'=>$name,'id'=>$id]);
            if($q->fetchColumn())throw new InvalidArgumentException('A fee structure with this name already exists for the selected class and academic year.');

            if($id>0){
                $q=$pdo->prepare("UPDATE fee_structures SET academic_year_id=:year_id,class_id=:class_id,structure_name=:name,status=:status WHERE id=:id AND tenant_id=:tenant_id");
                $q->execute(['year_id'=>$yearId,'class_id'=>$classId,'name'=>$name,'status'=>$status,'id'=>$id,'tenant_id'=>$scope['tenant_id']]);
                $pdo->prepare("DELETE FROM fee_structure_items WHERE fee_structure_id=:id")->execute(['id'=>$id]);
            }else{
                $q=$pdo->prepare("INSERT INTO fee_structures(tenant_id,academic_year_id,class_id,structure_name,status) VALUES(:tenant_id,:year_id,:class_id,:name,:status)");
                $q->execute(['tenant_id'=>$scope['tenant_id'],'year_id'=>$yearId,'class_id'=>$classId,'name'=>$name,'status'=>$status]);
                $id=(int)$pdo->lastInsertId();
            }

            $insert=$pdo->prepare("INSERT INTO fee_structure_items(fee_structure_id,fee_head_id,amount,due_date,fine_type,fine_value) VALUES(:structure_id,:head_id,:amount,:due_date,:fine_type,:fine_value)");
            foreach($normalized as $item)$insert->execute(['structure_id'=>$id,'head_id'=>$item['fee_head_id'],'amount'=>$item['amount'],'due_date'=>$item['due_date'],'fine_type'=>$item['fine_type'],'fine_value'=>$item['fine_value']]);
            $pdo->commit();
            fsOut(true,'Fee structure saved successfully.',['id'=>$id]);
        }

        throw new InvalidArgumentException('Invalid fee setup record type.');
    }

    if($action==='delete'){
        fsCsrf($input);
        $type=(string)($input['type']??'');
        $id=(int)($input['id']??0);
        if($id<=0)throw new InvalidArgumentException('Valid record ID is required.');

        if($type==='head'){
            $q=$pdo->prepare("SELECT COUNT(*) FROM fee_structure_items fsi INNER JOIN fee_structures fs ON fs.id=fsi.fee_structure_id WHERE fsi.fee_head_id=:id AND fs.tenant_id=:tenant_id");
            $q->execute(['id'=>$id,'tenant_id'=>$scope['tenant_id']]);
            if((int)$q->fetchColumn()>0)throw new InvalidArgumentException('This fee head is used in a fee structure and cannot be deleted.');
            $q=$pdo->prepare("DELETE FROM fee_heads WHERE id=:id AND tenant_id=:tenant_id");$q->execute(['id'=>$id,'tenant_id'=>$scope['tenant_id']]);
            if($q->rowCount()===0)throw new InvalidArgumentException('Fee head not found.');
            fsOut(true,'Fee head deleted successfully.');
        }

        if($type==='structure'){
            $q=$pdo->prepare("SELECT COUNT(*) FROM student_fee_assignments WHERE tenant_id=:tenant_id AND fee_structure_id=:id");
            $q->execute(['tenant_id'=>$scope['tenant_id'],'id'=>$id]);
            if((int)$q->fetchColumn()>0)throw new InvalidArgumentException('This structure is assigned to students and cannot be deleted.');
            $pdo->beginTransaction();
            $q=$pdo->prepare("SELECT id FROM fee_structures WHERE id=:id AND tenant_id=:tenant_id FOR UPDATE");$q->execute(['id'=>$id,'tenant_id'=>$scope['tenant_id']]);
            if(!$q->fetchColumn())throw new InvalidArgumentException('Fee structure not found.');
            $pdo->prepare("DELETE FROM fee_structure_items WHERE fee_structure_id=:id")->execute(['id'=>$id]);
            $pdo->prepare("DELETE FROM fee_structures WHERE id=:id AND tenant_id=:tenant_id")->execute(['id'=>$id,'tenant_id'=>$scope['tenant_id']]);
            $pdo->commit();
            fsOut(true,'Fee structure deleted successfully.');
        }
        throw new InvalidArgumentException('Invalid delete type.');
    }

    if($action==='import'){
        fsCsrf($input);
        $csv=(string)($input['csv']??'');
        if(trim($csv)==='')throw new InvalidArgumentException('CSV file is empty.');
        $stream=fopen('php://temp','r+');fwrite($stream,$csv);rewind($stream);
        $header=fgetcsv($stream);
        if(!$header)throw new InvalidArgumentException('CSV header is missing.');
        $header=array_map(fn($v)=>strtolower(trim((string)$v)),$header);
        $required=['head_code','head_name','is_refundable','status'];
        foreach($required as $column)if(!in_array($column,$header,true))throw new InvalidArgumentException('Missing CSV column: '.$column);
        $map=array_flip($header);$count=0;
        $pdo->beginTransaction();
        $upsert=$pdo->prepare("INSERT INTO fee_heads(tenant_id,head_name,head_code,is_refundable,status) VALUES(:tenant_id,:name,:code,:refundable,:status) ON DUPLICATE KEY UPDATE head_name=VALUES(head_name),is_refundable=VALUES(is_refundable),status=VALUES(status)");
        while(($row=fgetcsv($stream))!==false){
            if(count(array_filter($row,fn($v)=>trim((string)$v)!==''))===0)continue;
            $code=strtoupper(trim((string)($row[$map['head_code']]??'')));
            $name=trim((string)($row[$map['head_name']]??''));
            $refundable=(int)($row[$map['is_refundable']]??0);
            $status=strtolower(trim((string)($row[$map['status']]??'active')));
            if($code===''||$name===''||!in_array($status,['active','inactive'],true))throw new InvalidArgumentException('Invalid CSV row near fee head: '.$code);
            $upsert->execute(['tenant_id'=>$scope['tenant_id'],'name'=>$name,'code'=>$code,'refundable'=>$refundable?1:0,'status'=>$status]);$count++;
        }
        fclose($stream);$pdo->commit();
        fsOut(true,$count.' fee head record(s) imported successfully.');
    }

    if($action==='export'){
        $rows=fsStructures($pdo,$scope,$_GET);
        while(ob_get_level()>0)ob_end_clean();
        header('Content-Type:text/csv; charset=utf-8');
        header('Content-Disposition:attachment; filename="fee-setup-'.date('Ymd-His').'.csv"');
        $file=fopen('php://output','wb');
        fputcsv($file,['Structure','Academic Year','Class','Fee Head','Amount','Due Date','Fine Type','Fine Value','Status']);
        foreach($rows as $structure){
            foreach($structure['items'] as $item){
                fputcsv($file,[$structure['structure_name'],$structure['year_name'],$structure['class_name'],$item['head_name'],$item['amount'],$item['due_date'],$item['fine_type'],$item['fine_value'],$structure['status']]);
            }
        }
        fclose($file);exit;
    }

    fsOut(false,'Invalid Fee Setup action.',[],400);
}catch(InvalidArgumentException $error){
    if($pdo->inTransaction())$pdo->rollBack();
    fsOut(false,$error->getMessage(),[],422);
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    error_log('fee-setup.php: '.$error->getMessage());
    fsOut(false,'Fee Setup request failed: '.$error->getMessage(),[],500);
}

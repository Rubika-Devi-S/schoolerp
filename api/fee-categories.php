<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors','0');
error_reporting(E_ALL);

require_once dirname(__DIR__).'/includes/bootstrap.php';

function categoryOut(bool $success,string $message='',array $data=[],int $status=200):never{
    while(ob_get_level()>0){
        ob_end_clean();
    }

    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    echo json_encode(
        ['success'=>$success,'message'=>$message,'data'=>$data],
        JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES
    );
    exit;
}

function categoryInput():array{
    $json=json_decode((string)file_get_contents('php://input'),true);
    return is_array($json)?$json:$_POST;
}

function categoryScope():array{
    $user=function_exists('current_user')?current_user():[];

    return[
        'tenant_id'=>(int)(
            $user['tenant_id']
            ??$user['school_id']
            ??$_SESSION['tenant_id']
            ??$_SESSION['school_id']
            ??0
        ),
        'user_id'=>(int)(
            $user['id']
            ??$user['user_id']
            ??$_SESSION['user_id']
            ??0
        )
    ];
}

function categoryCsrf(array $input):void{
    $session=(string)($_SESSION['fee_csrf_token']??'');
    $request=(string)($input['csrf_token']??'');

    if($session===''||$request===''||!hash_equals($session,$request)){
        categoryOut(false,'Invalid or expired CSRF token. Refresh the page.',[],419);
    }
}

if(!isset($pdo)||!$pdo instanceof PDO){
    categoryOut(false,'Database connection unavailable.',[],500);
}

if(session_status()!==PHP_SESSION_ACTIVE){
    session_start();
}

if(empty($_SESSION['fee_csrf_token'])){
    $_SESSION['fee_csrf_token']=bin2hex(random_bytes(32));
}

$scope=categoryScope();

if($scope['tenant_id']<=0){
    categoryOut(false,'School tenant session was not found.',[],401);
}

$input=categoryInput();
$action=strtolower(trim((string)($input['action']??$_GET['action']??'')));

try{
    if($action==='list'){
        $search=trim((string)($_GET['search']??''));
        $status=trim((string)($_GET['status']??'all'));
        $usage=trim((string)($_GET['usage']??'all'));
        $sort=trim((string)($_GET['sort']??'name_asc'));
        $page=max(1,(int)($_GET['page']??1));
        $perPage=min(100,max(5,(int)($_GET['per_page']??10)));

        $where=['fh.tenant_id=:tenant_id'];
        $params=['tenant_id'=>$scope['tenant_id']];

        if($search!==''){
            $where[]='(fh.head_name LIKE :search OR fh.head_code LIKE :search)';
            $params['search']='%'.$search.'%';
        }

        if(in_array($status,['active','inactive'],true)){
            $where[]='fh.status=:status';
            $params['status']=$status;
        }

        if($usage==='used'){
            $where[]='EXISTS(SELECT 1 FROM fee_structure_items fsi WHERE fsi.fee_head_id=fh.id)';
        }elseif($usage==='unused'){
            $where[]='NOT EXISTS(SELECT 1 FROM fee_structure_items fsi WHERE fsi.fee_head_id=fh.id)';
        }

        $orderBy=match($sort){
            'name_desc'=>'fh.head_name DESC',
            'code_asc'=>'fh.head_code ASC',
            'newest'=>'fh.id DESC',
            default=>'fh.head_name ASC'
        };

        $countQuery=$pdo->prepare(
            "SELECT COUNT(*)
             FROM fee_heads fh
             WHERE ".implode(' AND ',$where)
        );
        $countQuery->execute($params);
        $total=(int)$countQuery->fetchColumn();

        $lastPage=max(1,(int)ceil($total/$perPage));
        $page=min($page,$lastPage);
        $offset=($page-1)*$perPage;

        $sql="SELECT
                fh.id,
                fh.head_code,
                fh.head_name,
                fh.is_refundable,
                fh.status,
                (
                    SELECT COUNT(DISTINCT fsi.fee_structure_id)
                    FROM fee_structure_items fsi
                    WHERE fsi.fee_head_id=fh.id
                ) AS structure_count
              FROM fee_heads fh
              WHERE ".implode(' AND ',$where)."
              ORDER BY {$orderBy}
              LIMIT {$perPage} OFFSET {$offset}";

        $query=$pdo->prepare($sql);
        $query->execute($params);
        $records=$query->fetchAll(PDO::FETCH_ASSOC);

        foreach($records as $index=>&$record){
            $record['row_number']=$offset+$index+1;
        }

        $statsQuery=$pdo->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN status='inactive' THEN 1 ELSE 0 END) AS inactive,
                SUM(
                    CASE WHEN EXISTS(
                        SELECT 1
                        FROM fee_structure_items fsi
                        WHERE fsi.fee_head_id=fee_heads.id
                    ) THEN 1 ELSE 0 END
                ) AS used
             FROM fee_heads
             WHERE tenant_id=:tenant_id"
        );
        $statsQuery->execute(['tenant_id'=>$scope['tenant_id']]);
        $stats=$statsQuery->fetch(PDO::FETCH_ASSOC)?:[];

        categoryOut(true,'Fee categories loaded.',[
            'records'=>$records,
            'stats'=>[
                'total'=>(int)($stats['total']??0),
                'active'=>(int)($stats['active']??0),
                'inactive'=>(int)($stats['inactive']??0),
                'used'=>(int)($stats['used']??0)
            ],
            'pagination'=>[
                'total'=>$total,
                'page'=>$page,
                'per_page'=>$perPage,
                'last_page'=>$lastPage
            ],
            'csrf_token'=>$_SESSION['fee_csrf_token']
        ]);
    }

    if($action==='save'){
        categoryCsrf($input);

        $id=(int)($input['id']??0);
        $code=strtoupper(trim((string)($input['head_code']??'')));
        $name=trim((string)($input['head_name']??''));
        $refundable=(int)($input['is_refundable']??0);
        $status=(string)($input['status']??'active');

        if($code===''||$name===''){
            throw new InvalidArgumentException('Category code and category name are required.');
        }

        if(!preg_match('/^[A-Z0-9_-]{2,40}$/',$code)){
            throw new InvalidArgumentException('Category code may contain only letters, numbers, underscore and hyphen.');
        }

        if(mb_strlen($name)<2||mb_strlen($name)>120){
            throw new InvalidArgumentException('Category name must be between 2 and 120 characters.');
        }

        if(!in_array($status,['active','inactive'],true)){
            throw new InvalidArgumentException('Invalid category status.');
        }

        $duplicate=$pdo->prepare(
            "SELECT id
             FROM fee_heads
             WHERE tenant_id=:tenant_id
               AND id<>:id
               AND (
                    UPPER(head_code)=UPPER(:head_code)
                    OR LOWER(head_name)=LOWER(:head_name)
               )
             LIMIT 1"
        );
        $duplicate->execute([
            'tenant_id'=>$scope['tenant_id'],
            'id'=>$id,
            'head_code'=>$code,
            'head_name'=>$name
        ]);

        if($duplicate->fetchColumn()){
            throw new InvalidArgumentException('A fee category with the same name or code already exists.');
        }

        if($id>0){
            $query=$pdo->prepare(
                "UPDATE fee_heads
                 SET head_code=:head_code,
                     head_name=:head_name,
                     is_refundable=:is_refundable,
                     status=:status
                 WHERE id=:id
                   AND tenant_id=:tenant_id"
            );
            $query->execute([
                'head_code'=>$code,
                'head_name'=>$name,
                'is_refundable'=>$refundable?1:0,
                'status'=>$status,
                'id'=>$id,
                'tenant_id'=>$scope['tenant_id']
            ]);

            $check=$pdo->prepare(
                "SELECT id
                 FROM fee_heads
                 WHERE id=:id
                   AND tenant_id=:tenant_id"
            );
            $check->execute([
                'id'=>$id,
                'tenant_id'=>$scope['tenant_id']
            ]);

            if(!$check->fetchColumn()){
                throw new InvalidArgumentException('Fee category not found.');
            }

            categoryOut(true,'Fee category updated successfully.',['id'=>$id]);
        }

        $query=$pdo->prepare(
            "INSERT INTO fee_heads(
                tenant_id,
                head_name,
                head_code,
                is_refundable,
                status
             ) VALUES(
                :tenant_id,
                :head_name,
                :head_code,
                :is_refundable,
                :status
             )"
        );
        $query->execute([
            'tenant_id'=>$scope['tenant_id'],
            'head_name'=>$name,
            'head_code'=>$code,
            'is_refundable'=>$refundable?1:0,
            'status'=>$status
        ]);

        categoryOut(true,'Fee category created successfully.',[
            'id'=>(int)$pdo->lastInsertId()
        ]);
    }

    if($action==='delete'){
        categoryCsrf($input);

        $id=(int)($input['id']??0);

        if($id<=0){
            throw new InvalidArgumentException('Valid fee category ID is required.');
        }

        $query=$pdo->prepare(
            "SELECT fh.id,fh.head_name,
                (
                    SELECT COUNT(*)
                    FROM fee_structure_items fsi
                    WHERE fsi.fee_head_id=fh.id
                ) AS usage_count
             FROM fee_heads fh
             WHERE fh.id=:id
               AND fh.tenant_id=:tenant_id
             LIMIT 1"
        );
        $query->execute([
            'id'=>$id,
            'tenant_id'=>$scope['tenant_id']
        ]);

        $category=$query->fetch(PDO::FETCH_ASSOC);

        if(!$category){
            throw new InvalidArgumentException('Fee category not found.');
        }

        if((int)$category['usage_count']>0){
            throw new InvalidArgumentException(
                'This fee category is already used in a fee structure and cannot be deleted.'
            );
        }

        $delete=$pdo->prepare(
            "DELETE FROM fee_heads
             WHERE id=:id
               AND tenant_id=:tenant_id"
        );
        $delete->execute([
            'id'=>$id,
            'tenant_id'=>$scope['tenant_id']
        ]);

        categoryOut(true,'Fee category deleted successfully.');
    }

    categoryOut(false,'Invalid Fee Categories action.',[],400);
}catch(InvalidArgumentException $error){
    categoryOut(false,$error->getMessage(),[],422);
}catch(Throwable $error){
    error_log('fee-categories.php: '.$error->getMessage());

    $host=strtolower((string)($_SERVER['HTTP_HOST']??''));
    $isLocal=str_contains($host,'localhost')||str_contains($host,'127.0.0.1');

    categoryOut(
        false,
        $isLocal
            ?'Fee Categories request failed: '.$error->getMessage()
            :'Unable to complete the Fee Categories request.',
        [],
        500
    );
}

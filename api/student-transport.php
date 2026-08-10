<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors','0');
error_reporting(E_ALL);

require_once dirname(__DIR__).'/includes/bootstrap.php';

function stOut(
    bool $success,
    string $message='',
    array $data=[],
    int $status=200
):never{
    while(ob_get_level()>0){
        ob_end_clean();
    }

    if(!headers_sent()){
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }

    echo json_encode(
        [
            'success'=>$success,
            'message'=>$message,
            'data'=>$data,
        ],
        JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES
    );
    exit;
}

function stScope():array
{
    $user=function_exists('current_user')
        ?current_user()
        :[];

    $user=is_array($user)?$user:[];

    return[
        'tenant_id'=>(int)(
            $user['tenant_id']
            ??$user['school_id']
            ??$_SESSION['tenant_id']
            ??$_SESSION['school_id']
            ??0
        ),
        'branch_id'=>(int)(
            $user['branch_id']
            ??$_SESSION['branch_id']
            ??0
        ),
    ];
}

if(!isset($pdo)||!$pdo instanceof PDO){
    stOut(false,'Database unavailable.',[],500);
}

$scope=stScope();

if($scope['tenant_id']<=0){
    stOut(false,'School tenant session was not found.',[],401);
}

$action=strtolower(
    trim((string)($_GET['action']??''))
);

try{
    if($action!=='list'){
        stOut(false,'Invalid Student Transport action.',[],400);
    }

    $yearStatement=$pdo->prepare(
        "SELECT
            id,
            year_name,
            is_current,
            start_date
         FROM academic_years
         WHERE tenant_id=:tenant_id
         ORDER BY
            is_current DESC,
            start_date DESC,
            id DESC"
    );
    $yearStatement->execute([
        'tenant_id'=>$scope['tenant_id'],
    ]);
    $years=$yearStatement->fetchAll(
        PDO::FETCH_ASSOC
    );

    $classStatement=$pdo->prepare(
        "SELECT
            id,
            class_name,
            academic_year_id,
            display_order
         FROM classes
         WHERE tenant_id=:tenant_id
           AND status='active'
         ORDER BY
            academic_year_id DESC,
            display_order,
            class_name"
    );
    $classStatement->execute([
        'tenant_id'=>$scope['tenant_id'],
    ]);

    $routeStatement=$pdo->prepare(
        "SELECT id,route_name
         FROM transport_routes
         WHERE tenant_id=:tenant_id
         ORDER BY route_name"
    );
    $routeStatement->execute([
        'tenant_id'=>$scope['tenant_id'],
    ]);

    $academicYearId=(int)(
        $_GET['academic_year_id']??0
    );

    if($academicYearId<=0&&$years){
        $academicYearId=(int)$years[0]['id'];
    }

    $where=[
        'sta.tenant_id=:tenant_id',
        'sta.academic_year_id=:academic_year_id',
        "sta.status='active'",
    ];
    $params=[
        'tenant_id'=>$scope['tenant_id'],
        'academic_year_id'=>$academicYearId,
    ];

    if($scope['branch_id']>0){
        $where[]='st.branch_id=:branch_id';
        $params['branch_id']=$scope['branch_id'];
    }

    $classId=(string)(
        $_GET['class_id']??'all'
    );

    if($classId!==''&&$classId!=='all'){
        $where[]='e.class_id=:class_id';
        $params['class_id']=(int)$classId;
    }

    $routeId=(string)(
        $_GET['route_id']??'all'
    );

    if($routeId!==''&&$routeId!=='all'){
        $where[]='sta.route_id=:route_id';
        $params['route_id']=(int)$routeId;
    }

    $search=trim(
        (string)($_GET['search']??'')
    );

    if($search!==''){
        $where[]="(
            st.admission_no LIKE :admission_search
            OR st.first_name LIKE :first_search
            OR st.last_name LIKE :last_search
            OR CONCAT_WS(' ',st.first_name,st.last_name)
                LIKE :full_search
        )";
        $value='%'.$search.'%';
        $params['admission_search']=$value;
        $params['first_search']=$value;
        $params['last_search']=$value;
        $params['full_search']=$value;
    }

    $statement=$pdo->prepare(
        "SELECT
            sta.*,
            TRIM(
                CONCAT_WS(
                    ' ',
                    st.first_name,
                    st.last_name
                )
            ) AS student_name,
            st.admission_no,
            ay.year_name,
            c.class_name,
            sec.section_name,
            tr.route_name,
            COALESCE(sfa.net_amount,0) AS net_amount,
            COALESCE(sfa.paid_amount,0) AS paid_amount,
            COALESCE(sfa.balance_amount,0) AS balance_amount
         FROM student_transport_assignments sta
         INNER JOIN students st
            ON st.id=sta.student_id
           AND st.tenant_id=sta.tenant_id
         INNER JOIN academic_years ay
            ON ay.id=sta.academic_year_id
           AND ay.tenant_id=sta.tenant_id
         LEFT JOIN student_enrollments e
            ON e.student_id=sta.student_id
           AND e.academic_year_id=sta.academic_year_id
           AND e.tenant_id=sta.tenant_id
         LEFT JOIN classes c
            ON c.id=e.class_id
           AND c.tenant_id=e.tenant_id
         LEFT JOIN sections sec
            ON sec.id=e.section_id
           AND sec.tenant_id=e.tenant_id
         LEFT JOIN transport_routes tr
            ON tr.id=sta.route_id
           AND tr.tenant_id=sta.tenant_id
         LEFT JOIN student_fee_assignments sfa
            ON sfa.student_id=sta.student_id
           AND sfa.academic_year_id=sta.academic_year_id
           AND sfa.tenant_id=sta.tenant_id
           AND sfa.assignment_status='active'
         WHERE ".implode(' AND ',$where)."
         ORDER BY
            sta.transport_required DESC,
            tr.route_name,
            c.display_order,
            st.first_name,
            st.last_name"
    );
    $statement->execute($params);
    $records=$statement->fetchAll(
        PDO::FETCH_ASSOC
    );

    $stats=[
        'students'=>0,
        'routes'=>0,
        'bus_fee'=>0.0,
        'balance'=>0.0,
    ];
    $routes=[];

    foreach($records as $record){
        if((int)$record['transport_required']===1){
            $stats['students']++;
            $stats['bus_fee']+=
                (float)$record['bus_fee_amount'];

            if((int)$record['route_id']>0){
                $routes[(int)$record['route_id']]=true;
            }
        }

        $stats['balance']+=
            (float)$record['balance_amount'];
    }

    $stats['routes']=count($routes);

    stOut(
        true,
        'Student transport records loaded.',
        [
            'meta'=>[
                'years'=>$years,
                'classes'=>$classStatement->fetchAll(
                    PDO::FETCH_ASSOC
                ),
                'routes'=>$routeStatement->fetchAll(
                    PDO::FETCH_ASSOC
                ),
            ],
            'records'=>$records,
            'stats'=>$stats,
        ]
    );
}catch(Throwable $exception){
    error_log(
        'student-transport.php: '
        .$exception->getMessage()
    );

    $host=strtolower(
        (string)($_SERVER['HTTP_HOST']??'')
    );

    stOut(
        false,
        (
            str_contains($host,'localhost')
            ||str_contains($host,'127.0.0.1')
        )
            ?'Student Transport request failed: '
                .$exception->getMessage()
            :'Unable to load Student Transport records.',
        [],
        500
    );
}

<?php
declare(strict_types=1);

/* Student Transport API - Build 2026-08-15-transport-amount-fix-v5 */

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
            ??$user['default_branch_id']
            ??$_SESSION['branch_id']
            ??$_SESSION['default_branch_id']
            ??0
        ),
    ];
}

function stTableExists(PDO $pdo,string $table):bool
{
    if(function_exists('school_table_exists')){
        return school_table_exists($pdo,$table);
    }

    $stmt=$pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema=DATABASE()
           AND table_name=:table"
    );
    $stmt->execute(['table'=>$table]);

    return (int)$stmt->fetchColumn()>0;
}

if(!isset($pdo)||!$pdo instanceof PDO){
    stOut(false,'Database unavailable.',[],500);
}

$scope=stScope();

if($scope['tenant_id']<=0){
    stOut(false,'School tenant session was not found.',[],401);
}

$requiredTables=[
    'academic_years',
    'classes',
    'sections',
    'class_management_classes',
    'students',
    'student_enrollments',
    'student_transport_assignments',
    'school_routes',
    'school_route_stops',
];

foreach($requiredTables as $table){
    if(!stTableExists($pdo,$table)){
        stOut(
            false,
            'Missing required database table: '.$table.'.',
            [],
            500
        );
    }
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
    $years=$yearStatement->fetchAll(PDO::FETCH_ASSOC);

    /*
     * The dropdown must use the actual Class Management master, not the
     * canonical `classes` template table. One dropdown option represents one
     * class name; multiple sections of the same class are grouped together.
     */
    $classWhere=[
        'cmc.tenant_id=:class_tenant_id',
        "cmc.status='active'",
    ];
    $classParams=[
        'class_tenant_id'=>$scope['tenant_id'],
    ];

    if($scope['branch_id']>0){
        $classWhere[]='cmc.branch_id=:class_branch_id';
        $classParams['class_branch_id']=$scope['branch_id'];
    }

    $classStatement=$pdo->prepare(
        "SELECT
            MIN(cmc.id) AS id,
            cmc.academic_year_id,
            MIN(cmc.class_name) AS class_name,
            MIN(cmc.display_order) AS display_order
         FROM class_management_classes cmc
         WHERE ".implode(' AND ',$classWhere)."
         GROUP BY
            cmc.academic_year_id,
            LOWER(TRIM(cmc.class_name))
         ORDER BY
            cmc.academic_year_id DESC,
            display_order,
            class_name"
    );
    $classStatement->execute($classParams);
    $classes=$classStatement->fetchAll(PDO::FETCH_ASSOC);

    $routeWhere=[
        'r.tenant_id=:tenant_id',
        "r.status='active'",
    ];
    $routeParams=[
        'tenant_id'=>$scope['tenant_id'],
    ];

    if($scope['branch_id']>0){
        $routeWhere[]='(r.branch_id IS NULL OR r.branch_id=:branch_id)';
        $routeParams['branch_id']=$scope['branch_id'];
    }

    $routeStatement=$pdo->prepare(
        "SELECT
            r.id,
            r.route_code,
            r.route_name,
            r.start_point,
            r.end_point
         FROM school_routes r
         WHERE ".implode(' AND ',$routeWhere)."
         ORDER BY r.route_name,r.route_code"
    );
    $routeStatement->execute($routeParams);
    $routes=$routeStatement->fetchAll(PDO::FETCH_ASSOC);

    $academicYearId=(int)(
        $_GET['academic_year_id']??0
    );

    if($academicYearId<=0&&$years){
        $currentYear=null;

        foreach($years as $year){
            if((int)($year['is_current']??0)===1){
                $currentYear=$year;
                break;
            }
        }

        $academicYearId=(int)(
            $currentYear['id']
            ??$years[0]['id']
            ??0
        );
    }

    if($academicYearId<=0){
        stOut(
            true,
            'No academic year is available.',
            [
                'meta'=>[
                    'years'=>$years,
                    'classes'=>$classes,
                    'routes'=>$routes,
                ],
                'records'=>[],
                'stats'=>[
                    'students'=>0,
                    'routes'=>0,
                    'bus_fee'=>0,
                    'balance'=>0,
                ],
            ]
        );
    }

    $where=[
        'sta.tenant_id=:tenant_id',
        'sta.academic_year_id=:academic_year_id',
        "sta.status='active'",
        'sta.transport_required=1',
    ];
    $params=[
        'tenant_id'=>$scope['tenant_id'],
        'academic_year_id'=>$academicYearId,
    ];

    if($scope['branch_id']>0){
        $where[]='st.branch_id=:student_branch_id';
        $params['student_branch_id']=$scope['branch_id'];
        $where[]='(sr.branch_id IS NULL OR sr.branch_id=:route_branch_id)';
        $params['route_branch_id']=$scope['branch_id'];
    }

    $classId=(string)(
        $_GET['class_id']??'all'
    );

    if($classId!==''&&$classId!=='all'){
        /*
         * class_id received by this page belongs to class_management_classes.
         * student_enrollments.class_id belongs to the canonical classes table,
         * so comparing those IDs directly is incorrect. Resolve the selected
         * Class Management row and filter students by normalized class name.
         */
        $selectedClassWhere=[
            'cmc.id=:selected_class_id',
            'cmc.tenant_id=:selected_class_tenant_id',
            'cmc.academic_year_id=:selected_class_year_id',
            "cmc.status='active'",
        ];
        $selectedClassParams=[
            'selected_class_id'=>(int)$classId,
            'selected_class_tenant_id'=>$scope['tenant_id'],
            'selected_class_year_id'=>$academicYearId,
        ];

        if($scope['branch_id']>0){
            $selectedClassWhere[]='cmc.branch_id=:selected_class_branch_id';
            $selectedClassParams['selected_class_branch_id']=$scope['branch_id'];
        }

        $selectedClassStatement=$pdo->prepare(
            "SELECT cmc.class_name
             FROM class_management_classes cmc
             WHERE ".implode(' AND ',$selectedClassWhere)."
             LIMIT 1"
        );
        $selectedClassStatement->execute($selectedClassParams);
        $selectedClassName=trim((string)$selectedClassStatement->fetchColumn());

        if($selectedClassName===''){
            stOut(
                false,
                'Selected class is invalid for this Academic Year.',
                [],
                422
            );
        }

        $where[]='LOWER(TRIM(c.class_name))=LOWER(TRIM(:class_name_filter))';
        $params['class_name_filter']=$selectedClassName;
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

    /*
     * Transport page amounts must come only from Transport Fee rows.
     * student_fee_assignments totals include tuition/admission/term/etc.
     */
    $feeJoin='';

    if(stTableExists($pdo,'student_fee_items')){
        $feeJoin="
         LEFT JOIN (
            SELECT
                tenant_id,
                student_id,
                academic_year_id,
                COUNT(*) AS transport_item_count,
                COALESCE(
                    SUM(
                        GREATEST(
                            0,
                            COALESCE(original_amount,0)
                            - COALESCE(discount_amount,0)
                        )
                    ),
                    0
                ) AS transport_net_amount,
                COALESCE(
                    SUM(COALESCE(paid_amount,0)),
                    0
                ) AS transport_paid_amount
            FROM student_fee_items
            WHERE item_type='transport'
              AND item_status<>'cancelled'
            GROUP BY
                tenant_id,
                student_id,
                academic_year_id
         ) tfi
            ON tfi.student_id=sta.student_id
           AND tfi.academic_year_id=sta.academic_year_id
           AND tfi.tenant_id=sta.tenant_id";

        $feeFields="
            CASE
                WHEN COALESCE(tfi.transport_item_count,0)>0
                THEN GREATEST(
                    0,
                    COALESCE(tfi.transport_net_amount,0)
                )
                ELSE
                    CASE
                        WHEN srs.id IS NOT NULL
                        THEN GREATEST(
                            0,
                            COALESCE(srs.transport_fee,0)
                        )
                        ELSE GREATEST(
                            0,
                            COALESCE(
                                NULLIF(sta.bus_fee_amount,0),
                                NULLIF(sta.transport_fee_amount,0),
                                0
                            )
                        )
                    END
            END AS net_amount,

            CASE
                WHEN COALESCE(tfi.transport_item_count,0)>0
                THEN LEAST(
                    GREATEST(
                        0,
                        COALESCE(tfi.transport_paid_amount,0)
                    ),
                    GREATEST(
                        0,
                        COALESCE(tfi.transport_net_amount,0)
                    )
                )
                ELSE 0
            END AS paid_amount,

            CASE
                WHEN COALESCE(tfi.transport_item_count,0)>0
                THEN GREATEST(
                    0,
                    COALESCE(tfi.transport_net_amount,0)
                    - LEAST(
                        GREATEST(
                            0,
                            COALESCE(tfi.transport_paid_amount,0)
                        ),
                        GREATEST(
                            0,
                            COALESCE(tfi.transport_net_amount,0)
                        )
                    )
                )
                ELSE
                    CASE
                        WHEN srs.id IS NOT NULL
                        THEN GREATEST(
                            0,
                            COALESCE(srs.transport_fee,0)
                        )
                        ELSE GREATEST(
                            0,
                            COALESCE(
                                NULLIF(sta.bus_fee_amount,0),
                                NULLIF(sta.transport_fee_amount,0),
                                0
                            )
                        )
                    END
            END AS balance_amount";
    }else{
        $feeFields="
            CASE
                WHEN srs.id IS NOT NULL
                THEN GREATEST(
                    0,
                    COALESCE(srs.transport_fee,0)
                )
                ELSE GREATEST(
                    0,
                    COALESCE(
                        NULLIF(sta.bus_fee_amount,0),
                        NULLIF(sta.transport_fee_amount,0),
                        0
                    )
                )
            END AS net_amount,
            0 AS paid_amount,
            CASE
                WHEN srs.id IS NOT NULL
                THEN GREATEST(
                    0,
                    COALESCE(srs.transport_fee,0)
                )
                ELSE GREATEST(
                    0,
                    COALESCE(
                        NULLIF(sta.bus_fee_amount,0),
                        NULLIF(sta.transport_fee_amount,0),
                        0
                    )
                )
            END AS balance_amount";
    }

    $statement=$pdo->prepare(
        "SELECT
            sta.id,
            sta.student_id,
            sta.academic_year_id,
            sta.route_id,
            sta.stop_id,
            sta.transport_required,
            sta.assigned_on,
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
            COALESCE(
                NULLIF(sr.route_name,''),
                NULLIF(sta.route_name,'')
            ) AS route_name,
            sr.route_code,
            COALESCE(
                NULLIF(srs.stop_name,''),
                NULLIF(sta.boarding_stop_name,'')
            ) AS boarding_stop_name,
            CASE
                WHEN srs.id IS NOT NULL
                THEN GREATEST(
                    0,
                    COALESCE(srs.transport_fee,0)
                )
                ELSE GREATEST(
                    0,
                    COALESCE(
                        NULLIF(sta.bus_fee_amount,0),
                        NULLIF(sta.transport_fee_amount,0),
                        0
                    )
                )
            END AS bus_fee_amount,
            {$feeFields}
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
           AND e.enrollment_status IN('active','promoted')
         LEFT JOIN classes c
            ON c.id=e.class_id
           AND c.tenant_id=e.tenant_id
         LEFT JOIN sections sec
            ON sec.id=e.section_id
           AND sec.tenant_id=e.tenant_id
         LEFT JOIN school_routes sr
            ON sr.id=sta.route_id
           AND sr.tenant_id=sta.tenant_id
         LEFT JOIN school_route_stops srs
            ON srs.id=sta.stop_id
           AND srs.route_id=sta.route_id
           AND srs.tenant_id=sta.tenant_id
         {$feeJoin}
         WHERE ".implode(' AND ',$where)."
         ORDER BY
            sr.route_name,
            srs.stop_order,
            c.display_order,
            st.first_name,
            st.last_name"
    );

    $statement->execute($params);
    $records=$statement->fetchAll(PDO::FETCH_ASSOC);

    $stats=[
        'students'=>count($records),
        'routes'=>0,
        'bus_fee'=>0.0,
        'balance'=>0.0,
    ];
    $usedRoutes=[];

    foreach($records as $record){
        $stats['bus_fee']+=(float)(
            $record['bus_fee_amount']??0
        );
        $stats['balance']+=(float)(
            $record['balance_amount']??0
        );

        $usedRouteId=(int)($record['route_id']??0);
        if($usedRouteId>0){
            $usedRoutes[$usedRouteId]=true;
        }
    }

    $stats['routes']=count($usedRoutes);

    stOut(
        true,
        'Transport students loaded successfully.',
        [
            'meta'=>[
                'years'=>$years,
                'classes'=>$classes,
                'routes'=>$routes,
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

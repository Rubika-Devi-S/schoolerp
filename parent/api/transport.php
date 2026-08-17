<?php
declare(strict_types=1);

/*
 * Parent Transport API
 * Location: parent/api/transport.php
 * Build: 2026-08-13-parent-transport-v38
 *
 * Read-only.
 * Uses existing student_transport_assignments, school_routes,
 * school_route_stops and school_vehicles data.
 */

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/sidebar-manager.php';
require_once dirname(__DIR__, 2) . '/includes/permission-chain.php';

if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('Asia/Kolkata');
}

function pdJson(bool $success, string $message = '', array $data = [], int $status = 200): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }

    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}

function pdUser(): array
{
    $user = function_exists('current_user') ? current_user() : [];
    return is_array($user) ? $user : [];
}

function pdScope(): array
{
    $user = pdUser();

    return [
        'tenant_id' => (int)(
            $user['tenant_id']
            ?? $user['school_id']
            ?? $_SESSION['tenant_id']
            ?? $_SESSION['school_id']
            ?? 0
        ),
        'branch_id' => (int)(
            $user['default_branch_id']
            ?? $user['branch_id']
            ?? $_SESSION['branch_id']
            ?? 0
        ),
        'user_id' => (int)(
            $user['id']
            ?? $user['user_id']
            ?? $_SESSION['user_id']
            ?? 0
        ),
        'email' => trim((string)(
            $user['email']
            ?? $_SESSION['email']
            ?? $_SESSION['user_email']
            ?? ''
        )),
        'mobile' => trim((string)(
            $user['mobile']
            ?? $_SESSION['mobile']
            ?? $_SESSION['user_mobile']
            ?? ''
        )),
    ];
}

function pdTableExists(PDO $pdo, string $table): bool
{
    static $cache = [];

    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = :table_name'
    );
    $stmt->execute(['table_name' => $table]);

    return $cache[$table] = ((int)$stmt->fetchColumn() > 0);
}

function pdColumns(PDO $pdo, string $table): array
{
    static $cache = [];

    if (isset($cache[$table])) {
        return $cache[$table];
    }

    if (!pdTableExists($pdo, $table)) {
        return $cache[$table] = [];
    }

    $quoted = str_replace('`', '``', $table);
    $rows = $pdo->query("SHOW COLUMNS FROM `{$quoted}`")->fetchAll(PDO::FETCH_ASSOC);

    $columns = [];
    foreach ($rows as $row) {
        $columns[] = (string)$row['Field'];
    }

    return $cache[$table] = $columns;
}

function pdMobile(string $value): string
{
    return preg_replace('/\D+/', '', $value) ?? '';
}

function pdGuardian(PDO $pdo, array $scope): array
{
    $tenantId=(int)$scope['tenant_id'];$userId=(int)$scope['user_id'];
    if($tenantId<=0)throw new RuntimeException('School session was not found.',401);

    if($userId>0&&pdTableExists($pdo,'student_parent_logins')){
        $q=$pdo->prepare(
            "SELECT DISTINCT g.id,g.tenant_id,g.guardian_name,g.relationship,g.mobile,g.email,g.occupation,g.address
             FROM student_parent_logins spl
             INNER JOIN guardians g ON g.id=spl.guardian_id AND g.tenant_id=spl.tenant_id
             WHERE spl.tenant_id=:tenant_id AND spl.user_id=:user_id AND spl.status='active'
             ORDER BY spl.id LIMIT 1"
        );
        $q->execute(['tenant_id'=>$tenantId,'user_id'=>$userId]);
        if($g=$q->fetch(PDO::FETCH_ASSOC)){$_SESSION['guardian_id']=(int)$g['id'];return $g;}
    }

    if($userId>0&&in_array('user_id',pdColumns($pdo,'guardians'),true)){
        $q=$pdo->prepare(
            "SELECT id,tenant_id,guardian_name,relationship,mobile,email,occupation,address
             FROM guardians WHERE tenant_id=:tenant_id AND user_id=:user_id ORDER BY id LIMIT 1"
        );
        $q->execute(['tenant_id'=>$tenantId,'user_id'=>$userId]);
        if($g=$q->fetch(PDO::FETCH_ASSOC)){$_SESSION['guardian_id']=(int)$g['id'];return $g;}
    }

    $guardianId=(int)($_SESSION['guardian_id']??$_SESSION['parent_guardian_id']??$_SESSION['parent_id']??0);
    if($guardianId>0){
        $q=$pdo->prepare(
            "SELECT id,tenant_id,guardian_name,relationship,mobile,email,occupation,address
             FROM guardians WHERE id=:id AND tenant_id=:tenant_id LIMIT 1"
        );
        $q->execute(['id'=>$guardianId,'tenant_id'=>$tenantId]);
        if($g=$q->fetch(PDO::FETCH_ASSOC))return $g;
    }

    $email=strtolower(trim((string)$scope['email']));$mobile=pdMobile((string)$scope['mobile']);
    if($email===''&&$mobile==='')throw new RuntimeException('This login is not linked to a Parent/Guardian record.',403);

    $q=$pdo->prepare(
        "SELECT id,tenant_id,guardian_name,relationship,mobile,email,occupation,address
         FROM guardians WHERE tenant_id=:tenant_id ORDER BY id"
    );
    $q->execute(['tenant_id'=>$tenantId]);
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $g){
        $ge=strtolower(trim((string)($g['email']??'')));$gm=pdMobile((string)($g['mobile']??''));
        if(($email!==''&&$ge!==''&&$ge===$email)||($mobile!==''&&$gm!==''&&$gm===$mobile)){
            $_SESSION['guardian_id']=(int)$g['id'];return $g;
        }
    }
    throw new RuntimeException('No Parent/Guardian record matches this login.',403);
}

function pdMappedStudentIds(PDO $pdo,int $tenantId,int $userId): array
{
    if($tenantId<=0||$userId<=0||!pdTableExists($pdo,'student_parent_logins'))return [];
    $q=$pdo->prepare("SELECT DISTINCT student_id FROM student_parent_logins
        WHERE tenant_id=:tenant_id AND user_id=:user_id AND status='active'");
    $q->execute(['tenant_id'=>$tenantId,'user_id'=>$userId]);
    return array_values(array_filter(array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN)),fn(int $id)=>$id>0));
}

function pdParentMenuKeys(PDO $pdo,array $scope): array
{
    $u=pdUser();$roleId=(int)($u['role_id']??$_SESSION['role_id']??0);
    if($roleId<=0)return [];
    $items=school_sidebar_get_items($pdo,$roleId,(int)$scope['tenant_id']);
    return array_values(array_unique(array_filter(array_map(
        fn(array $i)=>(string)($i['menu_key']??''),$items
    ))));
}

function pdChildren(PDO $pdo, int $tenantId, int $guardianId, int $userId = 0): array
{
    $stmt = $pdo->prepare(
        "SELECT
            s.id,
            s.tenant_id,
            s.branch_id,
            s.admission_no,
            s.emis_no,
            TRIM(CONCAT_WS(' ', s.first_name, s.last_name)) AS student_name,
            s.first_name,
            s.last_name,
            s.gender,
            s.date_of_birth,
            s.blood_group,
            s.mobile,
            s.email,
            s.address,
            s.photo_path,
            s.admission_date,
            s.status,
            sg.is_primary,
            se.id AS enrollment_id,
            se.academic_year_id,
            ay.year_name AS academic_year_name,
            ay.start_date AS academic_year_start,
            ay.end_date AS academic_year_end,
            se.class_id,
            c.class_name,
            se.section_id,
            sec.section_name,
            se.roll_no,
            se.enrollment_status,
            se.enrolled_on,
            spe.notes AS profile_notes
         FROM student_guardians sg
         INNER JOIN students s
            ON s.id = sg.student_id
           AND s.tenant_id = :tenant_id
         LEFT JOIN student_enrollments se
            ON se.id = (
                SELECT se2.id
                FROM student_enrollments se2
                INNER JOIN academic_years ay2
                   ON ay2.id = se2.academic_year_id
                  AND ay2.tenant_id = se2.tenant_id
                WHERE se2.tenant_id = s.tenant_id
                  AND se2.student_id = s.id
                ORDER BY
                    ay2.start_date DESC,
                    FIELD(se2.enrollment_status, 'active', 'promoted', 'transferred', 'completed'),
                    se2.id DESC
                LIMIT 1
            )
         LEFT JOIN academic_years ay
            ON ay.id = se.academic_year_id
           AND ay.tenant_id = s.tenant_id
         LEFT JOIN classes c
            ON c.id = se.class_id
           AND c.tenant_id = s.tenant_id
         LEFT JOIN sections sec
            ON sec.id = se.section_id
           AND sec.tenant_id = s.tenant_id
         LEFT JOIN student_profile_extras spe
            ON spe.tenant_id = s.tenant_id
           AND spe.student_id = s.id
         WHERE sg.guardian_id = :guardian_id
           AND s.deleted_at IS NULL
         ORDER BY sg.is_primary DESC, s.first_name, s.last_name, s.id"
    );
    $stmt->execute([
        'tenant_id' => $tenantId,
        'guardian_id' => $guardianId,
    ]);

    $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    $mapped=pdMappedStudentIds($pdo,$tenantId,$userId);
    if($mapped!==[]){
        $allowed=array_fill_keys($mapped,true);
        $rows=array_values(array_filter($rows,fn(array $row)=>isset($allowed[(int)($row['id']??0)])));
    }
    return $rows;
}

function pdSelectedChild(array $children, int $requestedStudentId): array
{
    if (!$children) {
        throw new RuntimeException(
            'No students are linked to this Parent/Guardian account.',
            404
        );
    }

    if ($requestedStudentId > 0) {
        foreach ($children as $child) {
            if ((int)$child['id'] === $requestedStudentId) {
                $_SESSION['parent_selected_student_id'] = $requestedStudentId;
                return $child;
            }
        }

        throw new RuntimeException(
            'The selected student is not linked to this Parent/Guardian account.',
            403
        );
    }

    $sessionId = (int)($_SESSION['parent_selected_student_id'] ?? 0);
    if ($sessionId > 0) {
        foreach ($children as $child) {
            if ((int)$child['id'] === $sessionId) {
                return $child;
            }
        }
    }

    $_SESSION['parent_selected_student_id'] = (int)$children[0]['id'];
    return $children[0];
}

function ptFirst(array $source, array $keys, $default = '')
{
    foreach ($keys as $key) {
        if (
            array_key_exists($key, $source)
            && $source[$key] !== null
            && $source[$key] !== ''
        ) {
            return $source[$key];
        }
    }

    return $default;
}

function ptSelectExpression(
    array $columns,
    string $alias,
    string $column,
    string $resultAlias,
    string $fallback = 'NULL'
): string {
    if (in_array($column, $columns, true)) {
        $safeColumn = str_replace('`', '``', $column);
        $safeAlias = str_replace('`', '``', $resultAlias);

        return "{$alias}.`{$safeColumn}` AS `{$safeAlias}`";
    }

    $safeAlias = str_replace('`', '``', $resultAlias);
    return "{$fallback} AS `{$safeAlias}`";
}

function ptTransport(
    PDO $pdo,
    int $tenantId,
    array $child
): array {
    if (!pdTableExists($pdo, 'student_transport_assignments')) {
        return [
            'assigned' => false,
            'configured' => false,
            'message' => 'Transport module is not configured.',
            'details' => null,
        ];
    }

    $staColumns = pdColumns(
        $pdo,
        'student_transport_assignments'
    );

    $routeExists = pdTableExists($pdo, 'school_routes');
    $stopExists = pdTableExists($pdo, 'school_route_stops');
    $vehicleExists = pdTableExists($pdo, 'school_vehicles');
    $driverExists = pdTableExists($pdo, 'school_drivers');

    $routeColumns = $routeExists
        ? pdColumns($pdo, 'school_routes')
        : [];

    $stopColumns = $stopExists
        ? pdColumns($pdo, 'school_route_stops')
        : [];

    $vehicleColumns = $vehicleExists
        ? pdColumns($pdo, 'school_vehicles')
        : [];

    $driverColumns = $driverExists
        ? pdColumns($pdo, 'school_drivers')
        : [];

    $select = ['sta.*'];
    $joins = [];

    if (
        $routeExists
        && in_array('route_id', $staColumns, true)
    ) {
        $joins[] =
            "LEFT JOIN school_routes r
               ON r.id = sta.route_id
              AND r.tenant_id = sta.tenant_id";

        foreach ([
            ['route_name', 'master_route_name'],
            ['route_code', 'master_route_code'],
            ['start_point', 'master_start_point'],
            ['end_point', 'master_end_point'],
            ['driver_name', 'route_driver_name'],
            ['driver_mobile', 'route_driver_mobile'],
        ] as [$column, $alias]) {
            $select[] = ptSelectExpression(
                $routeColumns,
                'r',
                $column,
                $alias
            );
        }
    } else {
        foreach ([
            'master_route_name',
            'master_route_code',
            'master_start_point',
            'master_end_point',
            'route_driver_name',
            'route_driver_mobile',
        ] as $alias) {
            $select[] = "NULL AS `{$alias}`";
        }
    }

    if (
        $stopExists
        && in_array('stop_id', $staColumns, true)
    ) {
        $joins[] =
            "LEFT JOIN school_route_stops rs
               ON rs.id = sta.stop_id
              AND rs.tenant_id = sta.tenant_id";

        foreach ([
            ['stop_name', 'master_stop_name'],
            ['pickup_time', 'master_pickup_time'],
            ['drop_time', 'master_drop_time'],
            ['transport_fee', 'master_transport_fee'],
        ] as [$column, $alias]) {
            $select[] = ptSelectExpression(
                $stopColumns,
                'rs',
                $column,
                $alias,
                $column === 'transport_fee' ? '0' : 'NULL'
            );
        }
    } else {
        $select[] = 'NULL AS master_stop_name';
        $select[] = 'NULL AS master_pickup_time';
        $select[] = 'NULL AS master_drop_time';
        $select[] = '0 AS master_transport_fee';
    }

    if (
        $vehicleExists
        && in_array('vehicle_id', $staColumns, true)
    ) {
        $joins[] =
            "LEFT JOIN school_vehicles v
               ON v.id = sta.vehicle_id
              AND v.tenant_id = sta.tenant_id";

        foreach ([
            ['vehicle_name', 'master_vehicle_name'],
            ['vehicle_number', 'master_vehicle_number'],
            ['registration_number', 'master_registration_number'],
            ['driver_name', 'vehicle_driver_name'],
            ['driver_mobile', 'vehicle_driver_mobile'],
            ['driver_id', 'vehicle_driver_id'],
        ] as [$column, $alias]) {
            $select[] = ptSelectExpression(
                $vehicleColumns,
                'v',
                $column,
                $alias
            );
        }
    } else {
        foreach ([
            'master_vehicle_name',
            'master_vehicle_number',
            'master_registration_number',
            'vehicle_driver_name',
            'vehicle_driver_mobile',
            'vehicle_driver_id',
        ] as $alias) {
            $select[] = "NULL AS `{$alias}`";
        }
    }

    if (
        $driverExists
        && $vehicleExists
        && in_array('driver_id', $vehicleColumns, true)
    ) {
        $joins[] =
            "LEFT JOIN school_drivers d
               ON d.id = v.driver_id
              AND d.tenant_id = v.tenant_id";

        foreach ([
            ['driver_name', 'master_driver_name'],
            ['name', 'master_driver_name_alt'],
            ['mobile', 'master_driver_mobile'],
            ['phone', 'master_driver_phone'],
        ] as [$column, $alias]) {
            $select[] = ptSelectExpression(
                $driverColumns,
                'd',
                $column,
                $alias
            );
        }
    } else {
        $select[] = 'NULL AS master_driver_name';
        $select[] = 'NULL AS master_driver_name_alt';
        $select[] = 'NULL AS master_driver_mobile';
        $select[] = 'NULL AS master_driver_phone';
    }

    $where = [];
    $params = [];

    if (in_array('tenant_id', $staColumns, true)) {
        $where[] = 'sta.tenant_id = :tenant_id';
        $params['tenant_id'] = $tenantId;
    }

    if (in_array('student_id', $staColumns, true)) {
        $where[] = 'sta.student_id = :student_id';
        $params['student_id'] = (int)$child['id'];
    } else {
        return [
            'assigned' => false,
            'configured' => true,
            'message' => 'Transport assignment does not contain student mapping.',
            'details' => null,
        ];
    }

    if (
        in_array('academic_year_id', $staColumns, true)
        && (int)($child['academic_year_id'] ?? 0) > 0
    ) {
        $where[] = 'sta.academic_year_id = :academic_year_id';
        $params['academic_year_id'] =
            (int)$child['academic_year_id'];
    }

    if (in_array('status', $staColumns, true)) {
        $where[] = "sta.status = 'active'";
    }

    if (in_array('deleted_at', $staColumns, true)) {
        $where[] = 'sta.deleted_at IS NULL';
    }

    $order = in_array('id', $staColumns, true)
        ? 'sta.id DESC'
        : '1';

    $sql =
        'SELECT '
        . implode(', ', $select)
        . ' FROM student_transport_assignments sta '
        . implode(' ', $joins)
        . ' WHERE '
        . implode(' AND ', $where)
        . " ORDER BY {$order} LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return [
            'assigned' => false,
            'configured' => true,
            'message' => 'School transport is not assigned to this child.',
            'details' => null,
        ];
    }

    $transportRequired = in_array(
        'transport_required',
        $staColumns,
        true
    )
        ? (int)($row['transport_required'] ?? 0) === 1
        : (
            (int)($row['route_id'] ?? 0) > 0
            || (int)($row['vehicle_id'] ?? 0) > 0
            || trim((string)ptFirst(
                $row,
                ['route_name', 'master_route_name'],
                ''
            )) !== ''
        );

    if (!$transportRequired) {
        return [
            'assigned' => false,
            'configured' => true,
            'message' => 'School transport is not assigned to this child.',
            'details' => null,
        ];
    }

    $routeName = (string)ptFirst(
        $row,
        ['route_name', 'master_route_name'],
        ''
    );

    $routeCode = (string)ptFirst(
        $row,
        ['route_code', 'master_route_code'],
        ''
    );

    $busNumber = (string)ptFirst(
        $row,
        [
            'vehicle_number',
            'bus_number',
            'master_vehicle_number',
            'registration_number',
            'master_registration_number',
            'vehicle_name',
            'master_vehicle_name',
        ],
        ''
    );

    $driverName = (string)ptFirst(
        $row,
        [
            'driver_name',
            'route_driver_name',
            'vehicle_driver_name',
            'master_driver_name',
            'master_driver_name_alt',
        ],
        ''
    );

    $driverMobile = (string)ptFirst(
        $row,
        [
            'driver_mobile',
            'route_driver_mobile',
            'vehicle_driver_mobile',
            'master_driver_mobile',
            'master_driver_phone',
        ],
        ''
    );

    $stopName = (string)ptFirst(
        $row,
        [
            'boarding_stop_name',
            'stop_name',
            'pickup_stop_name',
            'master_stop_name',
        ],
        ''
    );

    $pickupPoint = (string)ptFirst(
        $row,
        [
            'pickup_point',
            'pickup_stop_name',
            'boarding_stop_name',
            'stop_name',
            'master_stop_name',
        ],
        ''
    );

    $dropPoint = (string)ptFirst(
        $row,
        [
            'drop_point',
            'drop_stop_name',
            'alighting_stop_name',
            'deboarding_stop_name',
            'stop_name',
            'master_stop_name',
        ],
        ''
    );

    /*
     * Existing ERP uses the same boarding stop for both directions when
     * separate pickup/drop fields are not stored.
     */
    if ($pickupPoint === '' && $stopName !== '') {
        $pickupPoint = $stopName;
    }

    if ($dropPoint === '' && $stopName !== '') {
        $dropPoint = $stopName;
    }

    $transportFee = (float)ptFirst(
        $row,
        [
            'transport_fee_amount',
            'bus_fee_amount',
            'transport_fee',
            'bus_fee',
            'master_transport_fee',
        ],
        0
    );

    return [
        'assigned' => true,
        'configured' => true,
        'message' => 'Transport details loaded.',
        'details' => [
            'child_name' =>
                (string)($child['student_name'] ?? ''),
            'academic_year' =>
                (string)($child['academic_year_name'] ?? ''),
            'class_name' =>
                (string)($child['class_name'] ?? ''),
            'section_name' =>
                (string)($child['section_name'] ?? ''),
            'route_name' => $routeName,
            'route_code' => $routeCode,
            'route_start' => (string)ptFirst(
                $row,
                ['start_point', 'master_start_point'],
                ''
            ),
            'route_end' => (string)ptFirst(
                $row,
                ['end_point', 'master_end_point'],
                ''
            ),
            'bus_number' => $busNumber,
            'vehicle_name' => (string)ptFirst(
                $row,
                ['vehicle_name', 'master_vehicle_name'],
                ''
            ),
            'driver_name' => $driverName,
            'driver_mobile' => $driverMobile,
            'pickup_point' => $pickupPoint,
            'drop_point' => $dropPoint,
            'pickup_time' => (string)ptFirst(
                $row,
                ['pickup_time', 'master_pickup_time'],
                ''
            ),
            'drop_time' => (string)ptFirst(
                $row,
                ['drop_time', 'master_drop_time'],
                ''
            ),
            'transport_fee' => round($transportFee, 2),
            'assigned_on' => (string)ptFirst(
                $row,
                ['assigned_on', 'created_at'],
                ''
            ),
        ],
    ];
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    pdJson(
        false,
        'Database connection unavailable.',
        [],
        500
    );
}

try {
    $scope = pdScope();

    $user = pdUser();
    $roleId = (int)(
        $user['role_id']
        ?? $_SESSION['role_id']
        ?? 0
    );

    $role = pc_role(
        $pdo,
        (int)$scope['tenant_id'],
        $roleId
    );

    if (
        pc_role_key(
            (string)($role['role_key'] ?? '')
        ) !== 'parent'
    ) {
        throw new RuntimeException(
            'Transport is available only to Parent accounts.',
            403
        );
    }

    $allowedMenus = pdParentMenuKeys(
        $pdo,
        $scope
    );

    if (
        !in_array(
            'parent_transport',
            $allowedMenus,
            true
        )
    ) {
        throw new RuntimeException(
            'Transport is disabled for this Parent account.',
            403
        );
    }

    $guardian = pdGuardian(
        $pdo,
        $scope
    );

    $children = pdChildren(
        $pdo,
        (int)$scope['tenant_id'],
        (int)$guardian['id'],
        (int)$scope['user_id']
    );

    $child = pdSelectedChild(
        $children,
        (int)($_GET['student_id'] ?? 0)
    );

    $transport = ptTransport(
        $pdo,
        (int)$scope['tenant_id'],
        $child
    );

    pdJson(
        true,
        'Parent Transport loaded.',
        [
            'parent' => [
                'guardian_id' => (int)$guardian['id'],
                'guardian_name' =>
                    (string)$guardian['guardian_name'],
            ],
            'children' => $children,
            'selected_child' => $child,
            'allowed_menu_keys' => $allowedMenus,
            'transport' => $transport,
            'generated_at' => date('c'),
        ]
    );
} catch (Throwable $exception) {
    $status = (int)$exception->getCode();

    if ($status < 400 || $status > 599) {
        $status = 500;
    }

    error_log(
        'Parent Transport API: '
        . $exception->getMessage()
    );

    pdJson(
        false,
        $status >= 500
            ? 'Unable to load Parent Transport.'
            : $exception->getMessage(),
        [],
        $status
    );
}

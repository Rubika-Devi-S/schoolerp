<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

const TRANSPORT_REPORT_BUILD = '2026-08-06-transport-report-fixed-v2';

function trOut(
    bool $success,
    string $message = '',
    array $data = array(),
    int $status = 200
): void {
    while (ob_get_level() > 0) ob_end_clean();

    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
    }

    echo json_encode(
        array(
            'success' => $success,
            'message' => $message,
            'data' => $data
        ),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function trScope(): array
{
    $user = function_exists('current_user')
        ? current_user()
        : array();

    $user = is_array($user) ? $user : array();

    return array(
        'tenant_id' => (int)(
            $user['tenant_id']
            ?? $user['school_id']
            ?? $_SESSION['tenant_id']
            ?? $_SESSION['school_id']
            ?? 0
        ),
        'branch_id' => (int)(
            $user['branch_id']
            ?? $_SESSION['branch_id']
            ?? 0
        ),
        'user_id' => (int)(
            $user['id']
            ?? $user['user_id']
            ?? $_SESSION['user_id']
            ?? 0
        )
    );
}

function trTableExists(PDO $pdo, string $table): bool
{
    $query = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = ?'
    );

    $query->execute(array($table));

    return (int)$query->fetchColumn() > 0;
}

function trColumnExists(
    PDO $pdo,
    string $table,
    string $column
): bool {
    $query = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = ?
           AND column_name = ?'
    );

    $query->execute(array($table, $column));

    return (int)$query->fetchColumn() > 0;
}

function trFirstExistingColumn(PDO $pdo,string $table,array $columns): ?string
{
    foreach ($columns as $column) {
        if (trColumnExists($pdo,$table,$column)) return $column;
    }
    return null;
}

function trMeta(PDO $pdo, array $scope): array
{
    $meta = array(
        'routes' => array(),
        'vehicles' => array(),
        'drivers' => array()
    );

    if (trTableExists($pdo, 'school_routes')) {
        $query = $pdo->prepare(
            "SELECT id, CONCAT(route_name, ' (', route_code, ')') AS label
             FROM school_routes
             WHERE tenant_id = ?
               AND (? = 0 OR branch_id = ? OR branch_id IS NULL)
             ORDER BY route_name"
        );
        $query->execute(array(
            $scope['tenant_id'],
            $scope['branch_id'],
            $scope['branch_id']
        ));
        $meta['routes'] = $query->fetchAll(PDO::FETCH_ASSOC);
    }

    if (trTableExists($pdo, 'school_vehicles')) {
        $query = $pdo->prepare(
            "SELECT id, CONCAT(vehicle_name, ' • ', vehicle_number) AS label
             FROM school_vehicles
             WHERE tenant_id = ?
               AND (? = 0 OR branch_id = ? OR branch_id IS NULL)
             ORDER BY vehicle_name, vehicle_number"
        );
        $query->execute(array(
            $scope['tenant_id'],
            $scope['branch_id'],
            $scope['branch_id']
        ));
        $meta['vehicles'] = $query->fetchAll(PDO::FETCH_ASSOC);
    }

    if (trTableExists($pdo, 'school_drivers')) {
        $query = $pdo->prepare(
            "SELECT id, CONCAT(driver_name, ' • ', licence_number) AS label
             FROM school_drivers
             WHERE tenant_id = ?
               AND (? = 0 OR branch_id = ? OR branch_id IS NULL)
             ORDER BY driver_name"
        );
        $query->execute(array(
            $scope['tenant_id'],
            $scope['branch_id'],
            $scope['branch_id']
        ));
        $meta['drivers'] = $query->fetchAll(PDO::FETCH_ASSOC);
    }

    return $meta;
}

function trFilters(array $input): array
{
    return array(
        'search' => trim((string)($input['search'] ?? '')),
        'route_id' => (int)($input['route_id'] ?? 0),
        'vehicle_id' => (int)($input['vehicle_id'] ?? 0),
        'driver_id' => (int)($input['driver_id'] ?? 0),
        'status' => strtolower(
            trim((string)($input['status'] ?? 'all'))
        )
    );
}

function trRouteRows(
    PDO $pdo,
    array $scope,
    array $filters
): array {
    if (!trTableExists($pdo, 'school_routes')) return array();

    $hasStops = trTableExists($pdo, 'school_route_stops');
    $hasVehicles = trTableExists($pdo, 'school_vehicles');
    $hasAssignments = trTableExists(
        $pdo,
        'student_transport_assignments'
    );

    $where = array(
        'r.tenant_id = ?',
        '(? = 0 OR r.branch_id = ? OR r.branch_id IS NULL)'
    );
    $params = array(
        $scope['tenant_id'],
        $scope['branch_id'],
        $scope['branch_id']
    );

    if ($filters['route_id'] > 0) {
        $where[] = 'r.id = ?';
        $params[] = $filters['route_id'];
    }

    if ($filters['vehicle_id'] > 0) {
        $where[] = 'r.vehicle_id = ?';
        $params[] = $filters['vehicle_id'];
    }

    if ($filters['status'] !== '' && $filters['status'] !== 'all') {
        $where[] = 'r.status = ?';
        $params[] = $filters['status'];
    }

    if ($filters['search'] !== '') {
        $where[] =
            '(r.route_name LIKE ?
              OR r.route_code LIKE ?
              OR r.zone_area LIKE ?)';
        $like = '%' . $filters['search'] . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $stopCount = $hasStops
        ? "(SELECT COUNT(*)
            FROM school_route_stops rs
            WHERE rs.tenant_id = r.tenant_id
              AND rs.route_id = r.id)"
        : '0';

    $studentCount = $hasAssignments
        ? "(SELECT COUNT(*)
            FROM student_transport_assignments sta
            WHERE sta.tenant_id = r.tenant_id
              AND sta.route_id = r.id
              AND sta.transport_required = 1
              AND sta.status = 'active')"
        : '0';

    $monthlyFee = $hasAssignments
        ? "(SELECT COALESCE(SUM(
                COALESCE(
                    sta.transport_fee_amount,
                    sta.bus_fee_amount,
                    0
                )
            ),0)
            FROM student_transport_assignments sta
            WHERE sta.tenant_id = r.tenant_id
              AND sta.route_id = r.id
              AND sta.transport_required = 1
              AND sta.status = 'active')"
        : '0';

    $vehicleJoin = $hasVehicles
        ? "LEFT JOIN school_vehicles v
             ON v.id = r.vehicle_id
            AND v.tenant_id = r.tenant_id"
        : '';

    $vehicleLabel = $hasVehicles
        ? "CASE
              WHEN v.id IS NULL THEN 'Not Assigned'
              ELSE CONCAT(v.vehicle_name, ' • ', v.vehicle_number)
           END"
        : "'Not Assigned'";

    $query = $pdo->prepare(
        "SELECT
            r.id,
            r.route_name,
            r.route_code,
            r.zone_area,
            r.start_point,
            r.end_point,
            r.vehicle_id,
            r.driver_name,
            r.status,
            {$vehicleLabel} AS vehicle_label,
            {$stopCount} AS stop_count,
            {$studentCount} AS student_count,
            {$monthlyFee} AS monthly_fee
         FROM school_routes r
         {$vehicleJoin}
         WHERE " . implode(' AND ', $where) . "
         ORDER BY
            CASE WHEN r.status = 'active' THEN 0 ELSE 1 END,
            r.route_name"
    );

    $query->execute($params);

    return $query->fetchAll(PDO::FETCH_ASSOC);
}

function trStudentRows(PDO $pdo,array $scope,array $filters): array
{
    if (!trTableExists($pdo,'student_transport_assignments') || !trTableExists($pdo,'students')) return array();
    $admissionColumn=trFirstExistingColumn($pdo,'students',array('admission_number','admission_no','admission_num','admission_id','student_code','student_id_number'));
    $studentNameColumn=trFirstExistingColumn($pdo,'students',array('student_name','name','full_name','student_full_name'));
    $classNameColumn=trFirstExistingColumn($pdo,'students',array('class_name','class','standard_name','grade_name'));
    $admissionExpression=$admissionColumn!==null?'s.`'.$admissionColumn.'`':"CONCAT('STU-',s.id)";
    $studentNameExpression=$studentNameColumn!==null?'s.`'.$studentNameColumn.'`':"CONCAT('Student ',s.id)";
    $classNameExpression=$classNameColumn!==null?"COALESCE(s.`".$classNameColumn."`,'')":"''";
    $where=array('sta.tenant_id = ?','sta.transport_required = 1'); $params=array($scope['tenant_id']);
    if($filters['route_id']>0){$where[]='sta.route_id = ?';$params[]=$filters['route_id'];}
    if($filters['vehicle_id']>0){$where[]='sta.vehicle_id = ?';$params[]=$filters['vehicle_id'];}
    if($filters['status']!==''&&$filters['status']!=='all'){$where[]='sta.status = ?';$params[]=$filters['status'];}
    if($filters['search']!==''){
        $parts=array('sta.route_name LIKE ?','sta.boarding_stop_name LIKE ?','sta.vehicle_name LIKE ?','sta.driver_name LIKE ?');
        if($studentNameColumn!==null)$parts[]='s.`'.$studentNameColumn.'` LIKE ?';
        if($admissionColumn!==null)$parts[]='s.`'.$admissionColumn.'` LIKE ?';
        if($classNameColumn!==null)$parts[]='s.`'.$classNameColumn.'` LIKE ?';
        $where[]='('.implode(' OR ',$parts).')'; $like='%'.$filters['search'].'%'; foreach($parts as $x)$params[]=$like;
    }
    $q=$pdo->prepare("SELECT sta.id,{$admissionExpression} AS admission_number,{$studentNameExpression} AS student_name,{$classNameExpression} AS class_name,sta.route_id,sta.stop_id,sta.vehicle_id,COALESCE(sta.route_name,'') AS route_name,COALESCE(sta.boarding_stop_name,'') AS boarding_stop_name,COALESCE(sta.vehicle_name,'') AS vehicle_name,COALESCE(sta.driver_name,'') AS driver_name,COALESCE(sta.transport_fee_amount,sta.bus_fee_amount,0) AS transport_fee_amount,sta.status FROM student_transport_assignments sta INNER JOIN students s ON s.id=sta.student_id AND s.tenant_id=sta.tenant_id WHERE ".implode(' AND ',$where)." ORDER BY sta.route_name,sta.boarding_stop_name,{$studentNameExpression}");
    $q->execute($params); return $q->fetchAll(PDO::FETCH_ASSOC);
}

function trStopRows(
    PDO $pdo,
    array $scope,
    array $filters
): array {
    if (
        !trTableExists($pdo, 'school_route_stops')
        || !trTableExists($pdo, 'school_routes')
    ) {
        return array();
    }

    $hasAssignments = trTableExists(
        $pdo,
        'student_transport_assignments'
    );

    $where = array('rs.tenant_id = ?');
    $params = array($scope['tenant_id']);
    if (trColumnExists($pdo,'school_route_stops','branch_id')) {
        $where[]='(? = 0 OR rs.branch_id = ? OR rs.branch_id IS NULL)';
        $params[]=$scope['branch_id']; $params[]=$scope['branch_id'];
    }

    if ($filters['route_id'] > 0) {
        $where[] = 'rs.route_id = ?';
        $params[] = $filters['route_id'];
    }

    if ($filters['status'] !== '' && $filters['status'] !== 'all') {
        $where[] = 'rs.status = ?';
        $params[] = $filters['status'];
    }

    if ($filters['search'] !== '') {
        $where[] =
            '(r.route_name LIKE ?
              OR r.route_code LIKE ?
              OR rs.stop_name LIKE ?)';
        $like = '%' . $filters['search'] . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $studentCount = $hasAssignments
        ? "(SELECT COUNT(*)
            FROM student_transport_assignments sta
            WHERE sta.tenant_id = rs.tenant_id
              AND sta.stop_id = rs.id
              AND sta.transport_required = 1
              AND sta.status = 'active')"
        : '0';

    $feeColumn = trColumnExists(
        $pdo,
        'school_route_stops',
        'transport_fee'
    )
        ? 'COALESCE(rs.transport_fee,0)'
        : '0';

    $query = $pdo->prepare(
        "SELECT
            rs.id,
            rs.route_id,
            r.route_name,
            r.route_code,
            rs.stop_name,
            rs.stop_order,
            {$feeColumn} AS transport_fee,
            {$studentCount} AS student_count,
            ({$feeColumn} * {$studentCount}) AS expected_fee,
            rs.status
         FROM school_route_stops rs
         INNER JOIN school_routes r
           ON r.id = rs.route_id
          AND r.tenant_id = rs.tenant_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY r.route_name, rs.stop_order, rs.stop_name"
    );

    $query->execute($params);

    return $query->fetchAll(PDO::FETCH_ASSOC);
}

function trDriverRows(
    PDO $pdo,
    array $scope,
    array $filters
): array {
    if (!trTableExists($pdo, 'school_vehicles')) {
        return array();
    }

    $hasDrivers = trTableExists($pdo, 'school_drivers');
    $hasRoutes = trTableExists($pdo, 'school_routes');

    $where = array(
        'v.tenant_id = ?',
        '(? = 0 OR v.branch_id = ? OR v.branch_id IS NULL)'
    );
    $params = array(
        $scope['tenant_id'],
        $scope['branch_id'],
        $scope['branch_id']
    );

    if ($filters['vehicle_id'] > 0) {
        $where[] = 'v.id = ?';
        $params[] = $filters['vehicle_id'];
    }

    if (
        $filters['driver_id'] > 0
        && $hasDrivers
        && trColumnExists($pdo, 'school_vehicles', 'driver_id')
    ) {
        $where[] = 'v.driver_id = ?';
        $params[] = $filters['driver_id'];
    }

    if ($filters['status'] !== '' && $filters['status'] !== 'all') {
        $where[] = 'v.status = ?';
        $params[] = $filters['status'];
    }

    if ($filters['search'] !== '') {
        $where[] =
            '(v.vehicle_name LIKE ?
              OR v.vehicle_number LIKE ?
              OR v.driver_name LIKE ?)';
        $like = '%' . $filters['search'] . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $driverJoin = $hasDrivers
        && trColumnExists($pdo, 'school_vehicles', 'driver_id')
        ? "LEFT JOIN school_drivers d
             ON d.id = v.driver_id
            AND d.tenant_id = v.tenant_id"
        : '';

    $routeJoin = $hasRoutes
        ? "LEFT JOIN school_routes r
             ON r.id = v.route_id
            AND r.tenant_id = v.tenant_id"
        : '';

    $driverFields = $hasDrivers
        && trColumnExists($pdo, 'school_vehicles', 'driver_id')
        ? "COALESCE(NULLIF(d.driver_name,''),v.driver_name,'') AS driver_name,
           COALESCE(d.mobile,'') AS mobile,
           COALESCE(d.licence_number,'') AS licence_number,
           CASE
              WHEN d.licence_expiry IS NULL THEN '-'
              ELSE DATE_FORMAT(d.licence_expiry,'%d-%m-%Y')
           END AS licence_expiry_display,
           COALESCE(d.status,v.status) AS driver_status"
        : "COALESCE(v.driver_name,'') AS driver_name,
           '' AS mobile,
           '' AS licence_number,
           '-' AS licence_expiry_display,
           v.status AS driver_status";

    $routeField = $hasRoutes
        ? "COALESCE(r.route_name, v.route_name, '')"
        : "COALESCE(v.route_name, '')";

    $query = $pdo->prepare(
        "SELECT
            v.id,
            CONCAT(v.vehicle_name, ' • ', v.vehicle_number)
                AS vehicle_label,
            v.vehicle_type,
            v.status AS vehicle_status,
            {$routeField} AS route_name,
            {$driverFields}
         FROM school_vehicles v
         {$driverJoin}
         {$routeJoin}
         WHERE " . implode(' AND ', $where) . "
         ORDER BY v.vehicle_name, v.vehicle_number"
    );

    $query->execute($params);

    return $query->fetchAll(PDO::FETCH_ASSOC);
}

function trStats(
    array $routes,
    array $students
): array {
    $activeRoutes = 0;
    $assignedVehicles = array();

    foreach ($routes as $route) {
        if (
            strtolower((string)($route['status'] ?? ''))
            === 'active'
        ) {
            $activeRoutes++;
        }

        if ((int)($route['vehicle_id'] ?? 0) > 0) {
            $assignedVehicles[
                (int)$route['vehicle_id']
            ] = true;
        }
    }

    $fee = 0;

    foreach ($students as $student) {
        $fee += (float)(
            $student['transport_fee_amount'] ?? 0
        );
    }

    return array(
        'active_routes' => $activeRoutes,
        'assigned_vehicles' => count($assignedVehicles),
        'transport_students' => count($students),
        'transport_fee' => round($fee, 2)
    );
}

function trCsv(
    array $routes,
    array $students,
    array $stops,
    array $drivers
): void {
    while (ob_get_level() > 0) ob_end_clean();

    header('Content-Type: text/csv; charset=utf-8');
    header(
        'Content-Disposition: attachment; ' .
        'filename="transport-report-' .
        date('Y-m-d') .
        '.csv"'
    );

    $output = fopen('php://output', 'wb');
    fwrite($output, "\xEF\xBB\xBF");

    fputcsv($output, array('ROUTE SUMMARY'));
    fputcsv($output, array(
        'Route Code',
        'Route Name',
        'Vehicle',
        'Driver',
        'Stops',
        'Students',
        'Monthly Fee',
        'Status'
    ));

    foreach ($routes as $row) {
        fputcsv($output, array(
            $row['route_code'],
            $row['route_name'],
            $row['vehicle_label'],
            $row['driver_name'],
            $row['stop_count'],
            $row['student_count'],
            $row['monthly_fee'],
            $row['status']
        ));
    }

    fputcsv($output, array());
    fputcsv($output, array('STUDENT TRANSPORT'));
    fputcsv($output, array(
        'Admission No.',
        'Student',
        'Class',
        'Route',
        'Stop',
        'Vehicle',
        'Driver',
        'Fee',
        'Status'
    ));

    foreach ($students as $row) {
        fputcsv($output, array(
            $row['admission_number'],
            $row['student_name'],
            $row['class_name'],
            $row['route_name'],
            $row['boarding_stop_name'],
            $row['vehicle_name'],
            $row['driver_name'],
            $row['transport_fee_amount'],
            $row['status']
        ));
    }

    fputcsv($output, array());
    fputcsv($output, array('STOP FEE SUMMARY'));
    fputcsv($output, array(
        'Route',
        'Order',
        'Stop',
        'Fee',
        'Students',
        'Expected Fee',
        'Status'
    ));

    foreach ($stops as $row) {
        fputcsv($output, array(
            $row['route_name'],
            $row['stop_order'],
            $row['stop_name'],
            $row['transport_fee'],
            $row['student_count'],
            $row['expected_fee'],
            $row['status']
        ));
    }

    fputcsv($output, array());
    fputcsv($output, array('DRIVER AND VEHICLE'));
    fputcsv($output, array(
        'Vehicle',
        'Type',
        'Route',
        'Driver',
        'Mobile',
        'Licence No.',
        'Licence Expiry',
        'Status'
    ));

    foreach ($drivers as $row) {
        fputcsv($output, array(
            $row['vehicle_label'],
            $row['vehicle_type'],
            $row['route_name'],
            $row['driver_name'],
            $row['mobile'],
            $row['licence_number'],
            $row['licence_expiry_display'],
            $row['driver_status']
        ));
    }

    fclose($output);
    exit;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    trOut(
        false,
        'Database connection is missing.',
        array('build' => TRANSPORT_REPORT_BUILD),
        500
    );
}

$scope = trScope();

if ($scope['tenant_id'] <= 0 || $scope['user_id'] <= 0) {
    trOut(
        false,
        'Tenant or user session is missing.',
        array('build' => TRANSPORT_REPORT_BUILD),
        401
    );
}

$action = strtolower(
    trim((string)($_GET['action'] ?? 'report'))
);

try {
    $filters = trFilters($_GET);
    $routes = trRouteRows($pdo, $scope, $filters);
    $students = trStudentRows($pdo, $scope, $filters);
    $stops = trStopRows($pdo, $scope, $filters);
    $drivers = trDriverRows($pdo, $scope, $filters);

    if ($action === 'export') {
        trCsv($routes, $students, $stops, $drivers);
    }

    trOut(
        true,
        'Transport report loaded.',
        array(
            'routes' => $routes,
            'students' => $students,
            'stops' => $stops,
            'drivers' => $drivers,
            'stats' => trStats($routes, $students),
            'meta' => trMeta($pdo, $scope),
            'build' => TRANSPORT_REPORT_BUILD
        )
    );
} catch (PDOException $exception) {
    $host = strtolower(
        (string)($_SERVER['HTTP_HOST'] ?? '')
    );

    $local =
        strpos($host, 'localhost') !== false
        || strpos($host, '127.0.0.1') !== false;

    error_log(
        'Transport Report [' .
        TRANSPORT_REPORT_BUILD .
        ']: ' .
        $exception->getMessage()
    );

    trOut(
        false,
        $local
            ? 'Database operation failed [' .
                TRANSPORT_REPORT_BUILD .
                ']: ' .
                $exception->getMessage()
            : 'Database operation failed.',
        array('build' => TRANSPORT_REPORT_BUILD),
        500
    );
} catch (Throwable $exception) {
    error_log(
        'Transport Report [' .
        TRANSPORT_REPORT_BUILD .
        ']: ' .
        $exception->getMessage()
    );

    trOut(
        false,
        'Transport report failed [' .
        TRANSPORT_REPORT_BUILD .
        ']: ' .
        $exception->getMessage(),
        array('build' => TRANSPORT_REPORT_BUILD),
        500
    );
}

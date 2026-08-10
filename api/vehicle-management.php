<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

const VEHICLE_BUILD = '2026-08-05-database-fixed-v9';

function vehicleOut(
    bool $success,
    string $message = '',
    array $data = array(),
    int $status = 200
): void {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

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

function vehicleInput(): array
{
    $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));

    if (strpos($contentType, 'application/json') !== false) {
        $decoded = json_decode(
            (string)file_get_contents('php://input'),
            true
        );

        return is_array($decoded) ? $decoded : array();
    }

    return $_POST;
}

function vehicleScope(): array
{
    $user = function_exists('current_user') ? current_user() : array();
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

function vehicleCsrf(array $input): void
{
    $sessionToken = (string)($_SESSION['vehicle_csrf_token'] ?? '');
    $requestToken = (string)($input['csrf_token'] ?? '');

    if (
        $sessionToken === '' ||
        $requestToken === '' ||
        !hash_equals($sessionToken, $requestToken)
    ) {
        vehicleOut(
            false,
            'Invalid or expired CSRF token. Refresh the page.',
            array(),
            419
        );
    }
}

function vehicleTableExists(PDO $pdo, string $table): bool
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

function vehicleColumnExists(
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

function vehicleIndexExists(
    PDO $pdo,
    string $table,
    string $indexName
): bool {
    $query = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.statistics
         WHERE table_schema = DATABASE()
           AND table_name = ?
           AND index_name = ?'
    );

    $query->execute(array($table, $indexName));

    return (int)$query->fetchColumn() > 0;
}

function vehicleColumnAllowsNull(
    PDO $pdo,
    string $table,
    string $column
): bool {
    $query = $pdo->prepare(
        'SELECT IS_NULLABLE
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = ?
           AND column_name = ?
         LIMIT 1'
    );

    $query->execute(array($table, $column));

    return strtoupper((string)$query->fetchColumn()) === 'YES';
}

function vehicleSafeSchemaExec(
    PDO $pdo,
    string $sql,
    string $label
): void {
    try {
        $pdo->exec($sql);
    } catch (Throwable $exception) {
        error_log(
            'Vehicle optional schema update [' .
            VEHICLE_BUILD .
            '] ' .
            $label .
            ': ' .
            $exception->getMessage()
        );
    }
}

function vehicleEnsureColumn(
    PDO $pdo,
    string $column,
    string $definition
): void {
    if (!vehicleColumnExists($pdo, 'school_vehicles', $column)) {
        $pdo->exec(
            "ALTER TABLE school_vehicles
             ADD COLUMN `$column` $definition"
        );
    }
}

function vehicleEnsureRouteSchema(PDO $pdo): void
{
    if (vehicleTableExists($pdo, 'school_routes')) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE school_routes (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id BIGINT UNSIGNED NOT NULL,
            branch_id BIGINT UNSIGNED NULL,
            route_code VARCHAR(50) NOT NULL,
            route_name VARCHAR(150) NOT NULL,
            start_point VARCHAR(150) NOT NULL,
            end_point VARCHAR(150) NOT NULL,
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_by BIGINT UNSIGNED NULL,
            updated_by BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_school_route_code (tenant_id, route_code),
            KEY idx_school_route_filter (tenant_id, branch_id, status)
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci"
    );
}

function vehicleEnsureSchema(PDO $pdo): void
{
    vehicleEnsureRouteSchema($pdo);

    if (!vehicleTableExists($pdo, 'school_vehicles')) {
        $pdo->exec(
            "CREATE TABLE school_vehicles (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id BIGINT UNSIGNED NOT NULL,
                branch_id BIGINT UNSIGNED NULL,
                vehicle_name VARCHAR(120) NOT NULL,
                vehicle_number VARCHAR(50) NOT NULL,
                vehicle_type ENUM('bus','van') NOT NULL,
                capacity INT UNSIGNED NOT NULL,
                driver_name VARCHAR(120) NOT NULL,
                helper_name VARCHAR(120) NULL,
                route_id BIGINT UNSIGNED NULL,
                route_name VARCHAR(160) NOT NULL DEFAULT '',
                status ENUM('active','inactive') NOT NULL DEFAULT 'active',
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_school_vehicle_number (
                    tenant_id,
                    vehicle_number
                ),
                KEY idx_school_vehicle_filter (
                    tenant_id,
                    branch_id,
                    status
                ),
                KEY idx_school_vehicle_route (
                    tenant_id,
                    route_id
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci"
        );

        return;
    }

    vehicleEnsureColumn(
        $pdo,
        'branch_id',
        'BIGINT UNSIGNED NULL AFTER tenant_id'
    );
    vehicleEnsureColumn(
        $pdo,
        'vehicle_name',
        "VARCHAR(120) NOT NULL DEFAULT '' AFTER branch_id"
    );
    vehicleEnsureColumn(
        $pdo,
        'vehicle_number',
        "VARCHAR(50) NOT NULL DEFAULT '' AFTER vehicle_name"
    );
    vehicleEnsureColumn(
        $pdo,
        'vehicle_type',
        "ENUM('bus','van') NOT NULL DEFAULT 'bus' AFTER vehicle_number"
    );
    vehicleEnsureColumn(
        $pdo,
        'capacity',
        'INT UNSIGNED NOT NULL DEFAULT 1 AFTER vehicle_type'
    );
    vehicleEnsureColumn(
        $pdo,
        'driver_name',
        "VARCHAR(120) NOT NULL DEFAULT '' AFTER capacity"
    );
    vehicleEnsureColumn(
        $pdo,
        'helper_name',
        'VARCHAR(120) NULL AFTER driver_name'
    );
    vehicleEnsureColumn(
        $pdo,
        'route_id',
        'BIGINT UNSIGNED NULL AFTER helper_name'
    );

    if (
        vehicleColumnExists(
            $pdo,
            'school_vehicles',
            'route_id'
        )
    ) {
        vehicleSafeSchemaExec(
            $pdo,
            "ALTER TABLE school_vehicles
             MODIFY route_id BIGINT UNSIGNED NULL DEFAULT NULL",
            'make route_id optional'
        );
    }
    vehicleEnsureColumn(
        $pdo,
        'route_name',
        "VARCHAR(160) NOT NULL DEFAULT '' AFTER route_id"
    );
    vehicleEnsureColumn(
        $pdo,
        'status',
        "ENUM('active','inactive') NOT NULL DEFAULT 'active' AFTER route_name"
    );
    vehicleEnsureColumn(
        $pdo,
        'created_by',
        'BIGINT UNSIGNED NULL AFTER status'
    );
    vehicleEnsureColumn(
        $pdo,
        'updated_by',
        'BIGINT UNSIGNED NULL AFTER created_by'
    );

    /*
     * Registration Number was removed from Vehicle Master.
     * Keep an existing old column harmless for backward compatibility.
     */
    if (
        vehicleIndexExists(
            $pdo,
            'school_vehicles',
            'uq_school_vehicle_registration'
        )
    ) {
        vehicleSafeSchemaExec(
            $pdo,
            "ALTER TABLE school_vehicles
             DROP INDEX uq_school_vehicle_registration",
            'drop legacy registration unique index'
        );
    }

    if (
        vehicleColumnExists(
            $pdo,
            'school_vehicles',
            'registration_number'
        )
    ) {
        vehicleSafeSchemaExec(
            $pdo,
            "ALTER TABLE school_vehicles
             MODIFY registration_number
             VARCHAR(50) NULL DEFAULT NULL",
            'make registration_number optional'
        );

        vehicleSafeSchemaExec(
            $pdo,
            "UPDATE school_vehicles
             SET registration_number = NULL
             WHERE TRIM(COALESCE(registration_number, '')) = ''",
            'clean blank registration numbers'
        );
    }

    /*
     * Older Vehicle tables may still contain driver_mobile as
     * NOT NULL without a default. The current form no longer uses it.
     */
    if (
        vehicleColumnExists(
            $pdo,
            'school_vehicles',
            'driver_mobile'
        )
    ) {
        vehicleSafeSchemaExec(
            $pdo,
            "ALTER TABLE school_vehicles
             MODIFY driver_mobile VARCHAR(15) NULL DEFAULT NULL",
            'make legacy driver_mobile optional'
        );

        vehicleSafeSchemaExec(
            $pdo,
            "UPDATE school_vehicles
             SET driver_mobile = NULL
             WHERE TRIM(COALESCE(driver_mobile, '')) = ''",
            'clean blank driver mobile values'
        );
    }

    /*
     * Map previous text route values to Route Master where possible.
     */
    $pdo->exec(
        "UPDATE school_vehicles v
         SET v.route_id = (
             SELECT MIN(r.id)
             FROM school_routes r
             WHERE r.tenant_id = v.tenant_id
               AND LOWER(TRIM(r.route_name)) =
                   LOWER(TRIM(v.route_name))
               AND (
                    v.branch_id IS NULL OR
                    r.branch_id = v.branch_id OR
                    r.branch_id IS NULL
               )
         )
         WHERE (v.route_id IS NULL OR v.route_id = 0)
           AND TRIM(COALESCE(v.route_name, '')) <> ''"
    );
}

function vehicleNormalizeNumber(string $number): string
{
    $number = strtoupper(trim($number));
    $number = preg_replace('/\s+/', ' ', $number);

    return is_string($number) ? $number : '';
}


function vehicleRouteOptions(
    PDO $pdo,
    array $scope
): array {
    if (!vehicleTableExists($pdo, 'school_routes')) {
        return array();
    }

    $query = $pdo->prepare(
        "SELECT
            id,
            route_code,
            route_name,
            start_point,
            end_point,
            status
         FROM school_routes
         WHERE tenant_id = ?
           AND status = 'active'
           AND (
                ? = 0 OR
                branch_id = ? OR
                branch_id IS NULL
           )
         ORDER BY
            CASE WHEN status = 'active' THEN 0 ELSE 1 END,
            route_name,
            route_code"
    );

    $query->execute(
        array(
            $scope['tenant_id'],
            $scope['branch_id'],
            $scope['branch_id']
        )
    );

    return $query->fetchAll(PDO::FETCH_ASSOC);
}

function vehicleResolveRoute(
    PDO $pdo,
    array $scope,
    int $routeId
): ?array {
    if ($routeId <= 0) {
        return null;
    }

    if (!vehicleTableExists($pdo, 'school_routes')) {
        throw new RuntimeException(
            'Route Management table is missing.'
        );
    }

    $query = $pdo->prepare(
        "SELECT
            id,
            route_code,
            route_name,
            start_point,
            end_point,
            status
         FROM school_routes
         WHERE id = ?
           AND tenant_id = ?
           AND status = 'active'
           AND (
                ? = 0 OR
                branch_id = ? OR
                branch_id IS NULL
           )
         LIMIT 1"
    );

    $query->execute(
        array(
            $routeId,
            $scope['tenant_id'],
            $scope['branch_id'],
            $scope['branch_id']
        )
    );

    $route = $query->fetch(PDO::FETCH_ASSOC);

    if (!$route) {
        throw new InvalidArgumentException(
            'The selected Route Assignment was not found.'
        );
    }

    return $route;
}

function vehicleValidate(array $input): array
{
    $vehicleName = trim((string)($input['vehicle_name'] ?? ''));
    $vehicleNumber = vehicleNormalizeNumber(
        (string)($input['vehicle_number'] ?? '')
    );
    $vehicleType = strtolower(
        trim((string)($input['vehicle_type'] ?? ''))
    );
    $capacity = (int)($input['capacity'] ?? 0);
    $driverName = trim((string)($input['driver_name'] ?? ''));
    $helperName = trim((string)($input['helper_name'] ?? ''));
    $routeId = (int)($input['route_id'] ?? 0);
    $status = strtolower(trim((string)($input['status'] ?? 'active')));

    if ($vehicleName === '') {
        throw new InvalidArgumentException(
            'Vehicle Name is required.'
        );
    }

    if (strlen($vehicleName) > 120) {
        throw new InvalidArgumentException(
            'Vehicle Name cannot exceed 120 characters.'
        );
    }

    if ($vehicleNumber === '') {
        throw new InvalidArgumentException(
            'Vehicle Number is required.'
        );
    }

    if (strlen($vehicleNumber) > 50) {
        throw new InvalidArgumentException(
            'Vehicle Number cannot exceed 50 characters.'
        );
    }

    if (!in_array($vehicleType, array('bus', 'van'), true)) {
        throw new InvalidArgumentException(
            'Vehicle Type must be Bus or Van.'
        );
    }

    if ($capacity < 1 || $capacity > 200) {
        throw new InvalidArgumentException(
            'Capacity must be between 1 and 200.'
        );
    }

    if (strlen($driverName) > 120) {
        throw new InvalidArgumentException(
            'Driver cannot exceed 120 characters.'
        );
    }

    if (strlen($helperName) > 120) {
        throw new InvalidArgumentException(
            'Helper / Attender cannot exceed 120 characters.'
        );
    }

    if (!in_array($status, array('active', 'inactive'), true)) {
        throw new InvalidArgumentException(
            'Status must be Active or Inactive.'
        );
    }

    return array(
        'vehicle_name' => $vehicleName,
        'vehicle_number' => $vehicleNumber,
        'vehicle_type' => $vehicleType,
        'capacity' => $capacity,
        'driver_name' => $driverName,
        'helper_name' => $helperName,
        'route_id' => $routeId,
        'status' => $status
    );
}

function vehicleFind(
    PDO $pdo,
    array $scope,
    int $id
): array {
    $query = $pdo->prepare(
        "SELECT
            v.*,
            COALESCE(
                r.route_name,
                NULLIF(v.route_name, ''),
                'Not Assigned'
            ) AS assigned_route_name,
            r.route_code,
            r.start_point AS route_start_point,
            r.end_point AS route_end_point,
            r.status AS route_status
         FROM school_vehicles v
         LEFT JOIN school_routes r
           ON r.id = v.route_id
          AND r.tenant_id = v.tenant_id
         WHERE v.id = ?
           AND v.tenant_id = ?
           AND (
                ? = 0 OR
                v.branch_id = ? OR
                v.branch_id IS NULL
           )
         LIMIT 1"
    );

    $query->execute(
        array(
            $id,
            $scope['tenant_id'],
            $scope['branch_id'],
            $scope['branch_id']
        )
    );

    $record = $query->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        throw new InvalidArgumentException(
            'Vehicle was not found.'
        );
    }

    $record['route_name'] = $record['assigned_route_name'];
    $record['created_at_display'] = !empty($record['created_at'])
        ? date('d-m-Y h:i A', strtotime((string)$record['created_at']))
        : '-';

    return $record;
}

function vehicleLegacyRegistrationValue(
    array $scope,
    string $vehicleNumber
): string {
    $value = 'AUTO-' .
        (int)$scope['tenant_id'] .
        '-' .
        preg_replace('/[^A-Z0-9]/', '', strtoupper($vehicleNumber));

    return substr($value, 0, 50);
}

function vehicleRouteStorageId(
    PDO $pdo,
    ?array $route
) {
    if ($route !== null) {
        return (int)$route['id'];
    }

    if (
        vehicleColumnExists(
            $pdo,
            'school_vehicles',
            'route_id'
        ) &&
        !vehicleColumnAllowsNull(
            $pdo,
            'school_vehicles',
            'route_id'
        )
    ) {
        return 0;
    }

    return null;
}

function vehicleDuplicateExists(
    PDO $pdo,
    int $tenantId,
    string $field,
    string $value,
    int $excludeId = 0
): bool {
    if (!in_array(
        $field,
        array('vehicle_number'),
        true
    )) {
        throw new InvalidArgumentException(
            'Invalid duplicate check field.'
        );
    }

    $query = $pdo->prepare(
        "SELECT id
         FROM school_vehicles
         WHERE tenant_id = ?
           AND `$field` = ?
           AND id <> ?
         LIMIT 1"
    );

    $query->execute(
        array(
            $tenantId,
            $value,
            $excludeId
        )
    );

    return (bool)$query->fetchColumn();
}

/*
 * A Vehicle Master record contains only one route_id.
 * This check also blocks duplicate active records for the same physical
 * vehicle, so one vehicle cannot be active on multiple routes.
 */
function vehicleActiveRouteConflict(
    PDO $pdo,
    int $tenantId,
    string $vehicleNumber,
    int $routeId,
    int $excludeId = 0
): bool {
    $query = $pdo->prepare(
        "SELECT id
         FROM school_vehicles
         WHERE tenant_id = ?
           AND status = 'active'
           AND id <> ?
           AND vehicle_number = ?
           AND COALESCE(route_id, 0) <> ?
         LIMIT 1"
    );

    $query->execute(
        array(
            $tenantId,
            $excludeId,
            $vehicleNumber,
            $routeId
        )
    );

    return (bool)$query->fetchColumn();
}

function vehicleList(
    PDO $pdo,
    array $scope,
    array $filters
): array {
    $where = array(
        'v.tenant_id = ?',
        '(? = 0 OR v.branch_id = ? OR v.branch_id IS NULL)'
    );

    $params = array(
        $scope['tenant_id'],
        $scope['branch_id'],
        $scope['branch_id']
    );

    $search = trim((string)($filters['search'] ?? ''));

    if ($search !== '') {
        $where[] = '(
            v.vehicle_name LIKE ? OR
            v.vehicle_number LIKE ?
        )';

        $searchValue = '%' . $search . '%';
        $params[] = $searchValue;
        $params[] = $searchValue;
    }

    $status = strtolower(
        trim((string)($filters['status'] ?? 'all'))
    );

    if ($status !== '' && $status !== 'all') {
        if (!in_array($status, array('active', 'inactive'), true)) {
            throw new InvalidArgumentException(
                'Status filter is invalid.'
            );
        }

        $where[] = 'v.status = ?';
        $params[] = $status;
    }

    $query = $pdo->prepare(
        "SELECT
            v.id,
            v.vehicle_name,
            v.vehicle_number,
            v.vehicle_type,
            v.capacity,
            v.driver_name,
            v.helper_name,
            v.route_id,
            COALESCE(
                r.route_name,
                NULLIF(v.route_name, ''),
                'Not Assigned'
            ) AS route_name,
            r.route_code,
            r.start_point AS route_start_point,
            r.end_point AS route_end_point,
            v.status,
            v.created_at,
            v.updated_at
         FROM school_vehicles v
         LEFT JOIN school_routes r
           ON r.id = v.route_id
          AND r.tenant_id = v.tenant_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY
            CASE WHEN v.status = 'active' THEN 0 ELSE 1 END,
            v.vehicle_name,
            v.vehicle_number
         LIMIT 500"
    );

    $query->execute($params);

    return $query->fetchAll(PDO::FETCH_ASSOC);
}

function vehicleStats(
    PDO $pdo,
    array $scope
): array {
    $query = $pdo->prepare(
        "SELECT
            COUNT(*) AS total_vehicles,
            COALESCE(SUM(status = 'active'), 0) AS active_vehicles,
            COALESCE(SUM(status = 'inactive'), 0) AS inactive_vehicles,
            COALESCE(SUM(capacity), 0) AS total_capacity
         FROM school_vehicles
         WHERE tenant_id = ?
           AND (
                ? = 0 OR
                branch_id = ? OR
                branch_id IS NULL
           )"
    );

    $query->execute(
        array(
            $scope['tenant_id'],
            $scope['branch_id'],
            $scope['branch_id']
        )
    );

    $stats = $query->fetch(PDO::FETCH_ASSOC);

    return is_array($stats)
        ? $stats
        : array(
            'total_vehicles' => 0,
            'active_vehicles' => 0,
            'inactive_vehicles' => 0,
            'total_capacity' => 0
        );
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    vehicleOut(
        false,
        'Database connection is missing.',
        array('build' => VEHICLE_BUILD),
        500
    );
}

$scope = vehicleScope();

if ($scope['tenant_id'] <= 0 || $scope['user_id'] <= 0) {
    vehicleOut(
        false,
        'Tenant or user session is missing.',
        array('build' => VEHICLE_BUILD),
        401
    );
}

if (
    empty($_SESSION['vehicle_csrf_token']) ||
    !is_string($_SESSION['vehicle_csrf_token'])
) {
    $_SESSION['vehicle_csrf_token'] = bin2hex(random_bytes(32));
}

$input = vehicleInput();
$action = strtolower(
    trim((string)($input['action'] ?? $_GET['action'] ?? ''))
);

try {
    vehicleEnsureSchema($pdo);

    if ($action === 'list') {
        $records = vehicleList(
            $pdo,
            $scope,
            array_merge($_GET, $input)
        );

        vehicleOut(
            true,
            'Vehicles loaded successfully.',
            array(
                'records' => $records,
                'stats' => vehicleStats($pdo, $scope),
                'routes' => vehicleRouteOptions($pdo, $scope),
                'csrf_token' => $_SESSION['vehicle_csrf_token'],
                'build' => VEHICLE_BUILD
            )
        );
    }

    if ($action === 'routes') {
        vehicleOut(
            true,
            'Route assignments loaded.',
            array(
                'routes' => vehicleRouteOptions($pdo, $scope),
                'csrf_token' => $_SESSION['vehicle_csrf_token'],
                'build' => VEHICLE_BUILD
            )
        );
    }

    if ($action === 'detail' || $action === 'view') {
        $id = (int)($_GET['id'] ?? $input['id'] ?? 0);

        if ($id <= 0) {
            throw new InvalidArgumentException(
                'Select a valid vehicle.'
            );
        }

        vehicleOut(
            true,
            'Vehicle details loaded.',
            array(
                'record' => vehicleFind($pdo, $scope, $id),
                'routes' => vehicleRouteOptions($pdo, $scope),
                'csrf_token' => $_SESSION['vehicle_csrf_token'],
                'build' => VEHICLE_BUILD
            )
        );
    }

    if ($action === 'save' || $action === 'update') {
        vehicleCsrf($input);

        $id = (int)($input['id'] ?? 0);
        $data = vehicleValidate($input);
        $route = vehicleResolveRoute(
            $pdo,
            $scope,
            $data['route_id']
        );
        $routeStorageId = vehicleRouteStorageId(
            $pdo,
            $route
        );

        if (
            $data['status'] === 'active' &&
            $data['route_id'] > 0 &&
            vehicleActiveRouteConflict(
                $pdo,
                $scope['tenant_id'],
                $data['vehicle_number'],
                $data['route_id'],
                $id
            )
        ) {
            throw new InvalidArgumentException(
                'This vehicle is already assigned to another active route.'
            );
        }

        if (
            vehicleDuplicateExists(
                $pdo,
                $scope['tenant_id'],
                'vehicle_number',
                $data['vehicle_number'],
                $id
            )
        ) {
            throw new InvalidArgumentException(
                'Vehicle Number already exists.'
            );
        }

        $branchId = $scope['branch_id'] > 0
            ? $scope['branch_id']
            : null;

        if ($id > 0) {
            vehicleFind($pdo, $scope, $id);

            $query = $pdo->prepare(
                "UPDATE school_vehicles SET
                    branch_id = ?,
                    vehicle_name = ?,
                    vehicle_number = ?,
                    vehicle_type = ?,
                    capacity = ?,
                    driver_name = ?,
                    helper_name = ?,
                    route_id = ?,
                    route_name = ?,
                    status = ?,
                    updated_by = ?
                 WHERE id = ?
                   AND tenant_id = ?"
            );

            $query->execute(
                array(
                    $branchId,
                    $data['vehicle_name'],
                    $data['vehicle_number'],
                    $data['vehicle_type'],
                    $data['capacity'],
                    $data['driver_name'],
                    $data['helper_name'] !== ''
                        ? $data['helper_name']
                        : null,
                    $routeStorageId,
                    $route !== null ? (string)$route['route_name'] : '',
                    $data['status'],
                    $scope['user_id'],
                    $id,
                    $scope['tenant_id']
                )
            );

            vehicleOut(
                true,
                'Vehicle updated successfully.',
                array(
                    'id' => $id,
                    'record' => vehicleFind(
                        $pdo,
                        $scope,
                        $id
                    ),
                    'csrf_token' => $_SESSION['vehicle_csrf_token'],
                    'build' => VEHICLE_BUILD
                )
            );
        }

        $insertColumns = array(
            'tenant_id',
            'branch_id',
            'vehicle_name',
            'vehicle_number',
            'vehicle_type',
            'capacity',
            'driver_name',
            'helper_name',
            'route_id',
            'route_name',
            'status',
            'created_by',
            'updated_by'
        );

        $insertValues = array(
            $scope['tenant_id'],
            $branchId,
            $data['vehicle_name'],
            $data['vehicle_number'],
            $data['vehicle_type'],
            $data['capacity'],
            $data['driver_name'],
            $data['helper_name'],
            $routeStorageId,
            $route !== null ? (string)$route['route_name'] : '',
            $data['status'],
            $scope['user_id'],
            $scope['user_id']
        );

        /*
         * Supply values for columns left behind by older versions.
         * This prevents strict-mode errors such as:
         * "Field driver_mobile doesn't have a default value".
         */
        if (
            vehicleColumnExists(
                $pdo,
                'school_vehicles',
                'driver_mobile'
            )
        ) {
            $insertColumns[] = 'driver_mobile';
            $insertValues[] = '';
        }

        if (
            vehicleColumnExists(
                $pdo,
                'school_vehicles',
                'registration_number'
            )
        ) {
            $insertColumns[] = 'registration_number';
            $insertValues[] = vehicleLegacyRegistrationValue(
                $scope,
                $data['vehicle_number']
            );
        }

        $columnSql = implode(
            ', ',
            array_map(
                static function (string $column): string {
                    return '`' . $column . '`';
                },
                $insertColumns
            )
        );

        $placeholderSql = implode(
            ', ',
            array_fill(0, count($insertColumns), '?')
        );

        $query = $pdo->prepare(
            "INSERT INTO school_vehicles (" .
            $columnSql .
            ") VALUES (" .
            $placeholderSql .
            ")"
        );

        $query->execute($insertValues);

        $newId = (int)$pdo->lastInsertId();

        vehicleOut(
            true,
            'Vehicle added successfully.',
            array(
                'id' => $newId,
                'record' => vehicleFind(
                    $pdo,
                    $scope,
                    $newId
                ),
                'csrf_token' => $_SESSION['vehicle_csrf_token'],
                'build' => VEHICLE_BUILD
            ),
            201
        );
    }

    if ($action === 'delete') {
        vehicleCsrf($input);

        $id = (int)($input['id'] ?? 0);

        if ($id <= 0) {
            throw new InvalidArgumentException(
                'Select a valid vehicle.'
            );
        }

        $record = vehicleFind($pdo, $scope, $id);

        $query = $pdo->prepare(
            "DELETE FROM school_vehicles
             WHERE id = ?
               AND tenant_id = ?"
        );

        $query->execute(
            array(
                $id,
                $scope['tenant_id']
            )
        );

        if ($query->rowCount() < 1) {
            throw new RuntimeException(
                'Vehicle could not be deleted.'
            );
        }

        vehicleOut(
            true,
            'Vehicle ' .
                $record['vehicle_number'] .
                ' deleted successfully.',
            array(
                'id' => $id,
                'csrf_token' => $_SESSION['vehicle_csrf_token'],
                'build' => VEHICLE_BUILD
            )
        );
    }

    vehicleOut(
        false,
        'Vehicle API action is missing or invalid.',
        array('build' => VEHICLE_BUILD),
        400
    );
} catch (InvalidArgumentException $exception) {
    vehicleOut(
        false,
        $exception->getMessage(),
        array('build' => VEHICLE_BUILD),
        422
    );
} catch (PDOException $exception) {
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $isLocal =
        strpos($host, 'localhost') !== false ||
        strpos($host, '127.0.0.1') !== false;

    $message = 'Database operation failed.';

    if ((string)$exception->getCode() === '23000') {
        $message = 'Vehicle Number already exists.';
    } elseif ($isLocal) {
        $message =
            'Database operation failed [' .
            VEHICLE_BUILD .
            ']: ' .
            $exception->getMessage();
    }

    error_log(
        'Vehicle Management [' .
        VEHICLE_BUILD .
        ']: ' .
        $exception->getMessage()
    );

    vehicleOut(
        false,
        $message,
        array('build' => VEHICLE_BUILD),
        500
    );
} catch (Throwable $exception) {
    error_log(
        'Vehicle Management [' .
        VEHICLE_BUILD .
        ']: ' .
        $exception->getMessage()
    );

    vehicleOut(
        false,
        'Vehicle request failed [' .
            VEHICLE_BUILD .
            ']: ' .
            $exception->getMessage(),
        array('build' => VEHICLE_BUILD),
        500
    );
}

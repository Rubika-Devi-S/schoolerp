<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

const ROUTE_BUILD = '2026-08-05-route-master-v3';

function routeOut(
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

function routeInput(): array
{
    $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));

    if (strpos($contentType, 'application/json') !== false) {
        $decoded = json_decode((string)file_get_contents('php://input'), true);
        return is_array($decoded) ? $decoded : array();
    }

    return $_POST;
}

function routeScope(): array
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

function routeCsrf(array $input): void
{
    $sessionToken = (string)($_SESSION['route_csrf_token'] ?? '');
    $requestToken = (string)($input['csrf_token'] ?? '');

    if (
        $sessionToken === '' ||
        $requestToken === '' ||
        !hash_equals($sessionToken, $requestToken)
    ) {
        routeOut(false, 'Invalid or expired CSRF token. Refresh the page.', array(), 419);
    }
}

function routeTableExists(PDO $pdo, string $table): bool
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

function routeColumnExists(PDO $pdo, string $table, string $column): bool
{
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

function routeEnsureColumn(
    PDO $pdo,
    string $table,
    string $column,
    string $definition
): void {
    if (!routeColumnExists($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

function routeEnsureSchema(PDO $pdo): void
{
    if (!routeTableExists($pdo, 'school_routes')) {
        $pdo->exec(
            "CREATE TABLE school_routes (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id BIGINT UNSIGNED NOT NULL,
                branch_id BIGINT UNSIGNED NULL,
                route_name VARCHAR(150) NOT NULL,
                route_code VARCHAR(50) NOT NULL,
                zone_area VARCHAR(150) NOT NULL DEFAULT '',
                start_point VARCHAR(150) NOT NULL,
                end_point VARCHAR(150) NOT NULL,
                vehicle_id BIGINT UNSIGNED NULL,
                driver_name VARCHAR(120) NULL,
                status ENUM('active','inactive') NOT NULL DEFAULT 'active',
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_school_route_code (tenant_id, route_code),
                KEY idx_school_route_filter (tenant_id, branch_id, status),
                KEY idx_school_route_vehicle (tenant_id, vehicle_id, status)
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci"
        );
    } else {
        routeEnsureColumn($pdo, 'school_routes', 'branch_id', 'BIGINT UNSIGNED NULL AFTER tenant_id');
        routeEnsureColumn($pdo, 'school_routes', 'route_name', "VARCHAR(150) NOT NULL DEFAULT '' AFTER branch_id");
        routeEnsureColumn($pdo, 'school_routes', 'route_code', "VARCHAR(50) NOT NULL DEFAULT '' AFTER route_name");
        routeEnsureColumn($pdo, 'school_routes', 'zone_area', "VARCHAR(150) NOT NULL DEFAULT '' AFTER route_code");
        routeEnsureColumn($pdo, 'school_routes', 'start_point', "VARCHAR(150) NOT NULL DEFAULT '' AFTER zone_area");
        routeEnsureColumn($pdo, 'school_routes', 'end_point', "VARCHAR(150) NOT NULL DEFAULT '' AFTER start_point");
        routeEnsureColumn($pdo, 'school_routes', 'vehicle_id', 'BIGINT UNSIGNED NULL AFTER end_point');
        routeEnsureColumn($pdo, 'school_routes', 'driver_name', 'VARCHAR(120) NULL AFTER vehicle_id');
        routeEnsureColumn($pdo, 'school_routes', 'status', "ENUM('active','inactive') NOT NULL DEFAULT 'active' AFTER driver_name");
        routeEnsureColumn($pdo, 'school_routes', 'created_by', 'BIGINT UNSIGNED NULL AFTER status');
        routeEnsureColumn($pdo, 'school_routes', 'updated_by', 'BIGINT UNSIGNED NULL AFTER created_by');
    }

    if (!routeTableExists($pdo, 'school_route_stops')) {
        $pdo->exec(
            "CREATE TABLE school_route_stops (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id BIGINT UNSIGNED NOT NULL,
                branch_id BIGINT UNSIGNED NULL,
                route_id BIGINT UNSIGNED NOT NULL,
                stop_name VARCHAR(150) NOT NULL,
                stop_order INT UNSIGNED NOT NULL DEFAULT 1,
                status ENUM('active','inactive') NOT NULL DEFAULT 'active',
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_route_stop_order (tenant_id, route_id, stop_order),
                KEY idx_route_stop_list (tenant_id, route_id, status)
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci"
        );
    }
}

function routeNormalizeCode(string $value): string
{
    $value = strtoupper(trim($value));
    $value = preg_replace('/\\s+/', ' ', $value);
    return is_string($value) ? $value : '';
}

function routeVehicleOptions(PDO $pdo, array $scope): array
{
    if (!routeTableExists($pdo, 'school_vehicles')) {
        return array();
    }

    $query = $pdo->prepare(
        "SELECT
            id,
            vehicle_name,
            vehicle_number,
            vehicle_type,
            driver_name,
            route_id,
            status
         FROM school_vehicles
         WHERE tenant_id = ?
           AND (? = 0 OR branch_id = ? OR branch_id IS NULL)
         ORDER BY
            CASE WHEN status = 'active' THEN 0 ELSE 1 END,
            vehicle_name,
            vehicle_number"
    );
    $query->execute(array(
        $scope['tenant_id'],
        $scope['branch_id'],
        $scope['branch_id']
    ));
    return $query->fetchAll(PDO::FETCH_ASSOC);
}

function routeResolveVehicle(
    PDO $pdo,
    array $scope,
    int $vehicleId,
    string $routeStatus
): array {
    if ($vehicleId <= 0) {
        throw new InvalidArgumentException('Assigned Vehicle is required.');
    }

    if (!routeTableExists($pdo, 'school_vehicles')) {
        throw new RuntimeException('Vehicle Master table was not found.');
    }

    $query = $pdo->prepare(
        "SELECT
            id,
            vehicle_name,
            vehicle_number,
            driver_name,
            route_id,
            status
         FROM school_vehicles
         WHERE id = ?
           AND tenant_id = ?
           AND (? = 0 OR branch_id = ? OR branch_id IS NULL)
         LIMIT 1"
    );
    $query->execute(array(
        $vehicleId,
        $scope['tenant_id'],
        $scope['branch_id'],
        $scope['branch_id']
    ));
    $vehicle = $query->fetch(PDO::FETCH_ASSOC);

    if (!$vehicle) {
        throw new InvalidArgumentException('The selected vehicle was not found.');
    }

    if ($routeStatus === 'active' && strtolower((string)$vehicle['status']) !== 'active') {
        throw new InvalidArgumentException('Only an active vehicle can be assigned to an active route.');
    }

    return $vehicle;
}

function routeValidate(array $input): array
{
    $routeName = trim((string)($input['route_name'] ?? ''));
    $routeCode = routeNormalizeCode((string)($input['route_code'] ?? ''));
    $zoneArea = trim((string)($input['zone_area'] ?? ''));
    $startPoint = trim((string)($input['start_point'] ?? ''));
    $endPoint = trim((string)($input['end_point'] ?? ''));
    $vehicleId = (int)($input['vehicle_id'] ?? 0);
    $status = strtolower(trim((string)($input['status'] ?? 'active')));

    if ($routeName === '') throw new InvalidArgumentException('Route Name is required.');
    if (strlen($routeName) > 150) throw new InvalidArgumentException('Route Name cannot exceed 150 characters.');
    if ($routeCode === '') throw new InvalidArgumentException('Route Code is required.');
    if (strlen($routeCode) > 50) throw new InvalidArgumentException('Route Code cannot exceed 50 characters.');
    if ($zoneArea === '') throw new InvalidArgumentException('Zone / Area is required.');
    if (strlen($zoneArea) > 150) throw new InvalidArgumentException('Zone / Area cannot exceed 150 characters.');
    if ($startPoint === '') throw new InvalidArgumentException('Start Location is required.');
    if (strlen($startPoint) > 150) throw new InvalidArgumentException('Start Location cannot exceed 150 characters.');
    if ($endPoint === '') throw new InvalidArgumentException('End Location is required.');
    if (strlen($endPoint) > 150) throw new InvalidArgumentException('End Location cannot exceed 150 characters.');
    if ($vehicleId <= 0) throw new InvalidArgumentException('Assigned Vehicle is required.');
    if (!in_array($status, array('active', 'inactive'), true)) {
        throw new InvalidArgumentException('Status must be Active or Inactive.');
    }

    return array(
        'route_name' => $routeName,
        'route_code' => $routeCode,
        'zone_area' => $zoneArea,
        'start_point' => $startPoint,
        'end_point' => $endPoint,
        'vehicle_id' => $vehicleId,
        'status' => $status
    );
}

function routeDuplicateCode(
    PDO $pdo,
    int $tenantId,
    string $routeCode,
    int $excludeId
): bool {
    $query = $pdo->prepare(
        "SELECT id FROM school_routes
         WHERE tenant_id = ? AND route_code = ? AND id <> ? LIMIT 1"
    );
    $query->execute(array($tenantId, $routeCode, $excludeId));
    return (bool)$query->fetchColumn();
}

function routeVehicleConflict(
    PDO $pdo,
    int $tenantId,
    int $vehicleId,
    int $excludeRouteId
): bool {
    $query = $pdo->prepare(
        "SELECT id
         FROM school_routes
         WHERE tenant_id = ?
           AND vehicle_id = ?
           AND status = 'active'
           AND id <> ?
         LIMIT 1"
    );
    $query->execute(array($tenantId, $vehicleId, $excludeRouteId));
    if ($query->fetchColumn()) return true;

    if (routeTableExists($pdo, 'school_vehicles')) {
        $query = $pdo->prepare(
            "SELECT r.id
             FROM school_vehicles v
             INNER JOIN school_routes r
                ON r.id = v.route_id
               AND r.tenant_id = v.tenant_id
             WHERE v.id = ?
               AND v.tenant_id = ?
               AND r.status = 'active'
               AND r.id <> ?
             LIMIT 1"
        );
        $query->execute(array($vehicleId, $tenantId, $excludeRouteId));
        return (bool)$query->fetchColumn();
    }

    return false;
}

function routeFind(PDO $pdo, array $scope, int $id): array
{
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
            r.created_at,
            r.updated_at,
            v.vehicle_name,
            v.vehicle_number,
            (SELECT COUNT(*)
             FROM school_route_stops rs
             WHERE rs.tenant_id = r.tenant_id
               AND rs.route_id = r.id) AS stop_count
         FROM school_routes r
         LEFT JOIN school_vehicles v
           ON v.id = r.vehicle_id
          AND v.tenant_id = r.tenant_id
         WHERE r.id = ?
           AND r.tenant_id = ?
           AND (? = 0 OR r.branch_id = ? OR r.branch_id IS NULL)
         LIMIT 1"
    );
    $query->execute(array(
        $id,
        $scope['tenant_id'],
        $scope['branch_id'],
        $scope['branch_id']
    ));
    $record = $query->fetch(PDO::FETCH_ASSOC);
    if (!$record) throw new InvalidArgumentException('Route was not found.');
    $record['created_at_display'] = !empty($record['created_at'])
        ? date('d-m-Y h:i A', strtotime((string)$record['created_at']))
        : '-';
    return $record;
}

function routeList(PDO $pdo, array $scope, array $filters): array
{
    $where = array(
        'r.tenant_id = ?',
        '(? = 0 OR r.branch_id = ? OR r.branch_id IS NULL)'
    );
    $params = array(
        $scope['tenant_id'],
        $scope['branch_id'],
        $scope['branch_id']
    );

    $search = trim((string)($filters['search'] ?? ''));
    if ($search !== '') {
        $where[] = 'r.route_name LIKE ?';
        $params[] = '%' . $search . '%';
    }

    $status = strtolower(trim((string)($filters['status'] ?? 'all')));
    if ($status !== '' && $status !== 'all') {
        if (!in_array($status, array('active', 'inactive'), true)) {
            throw new InvalidArgumentException('Status filter is invalid.');
        }
        $where[] = 'r.status = ?';
        $params[] = $status;
    }

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
            r.created_at,
            r.updated_at,
            v.vehicle_name,
            v.vehicle_number,
            (SELECT COUNT(*)
             FROM school_route_stops rs
             WHERE rs.tenant_id = r.tenant_id
               AND rs.route_id = r.id) AS stop_count
         FROM school_routes r
         LEFT JOIN school_vehicles v
           ON v.id = r.vehicle_id
          AND v.tenant_id = r.tenant_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY
            CASE WHEN r.status = 'active' THEN 0 ELSE 1 END,
            r.route_name,
            r.route_code
         LIMIT 500"
    );
    $query->execute($params);
    return $query->fetchAll(PDO::FETCH_ASSOC);
}

function routeStats(PDO $pdo, array $scope): array
{
    $query = $pdo->prepare(
        "SELECT
            COUNT(*) AS total_routes,
            COALESCE(SUM(status = 'active'), 0) AS active_routes,
            COALESCE(SUM(status = 'inactive'), 0) AS inactive_routes
         FROM school_routes
         WHERE tenant_id = ?
           AND (? = 0 OR branch_id = ? OR branch_id IS NULL)"
    );
    $query->execute(array(
        $scope['tenant_id'],
        $scope['branch_id'],
        $scope['branch_id']
    ));
    return $query->fetch(PDO::FETCH_ASSOC) ?: array(
        'total_routes' => 0,
        'active_routes' => 0,
        'inactive_routes' => 0
    );
}

function routeSyncVehicle(
    PDO $pdo,
    int $tenantId,
    int $routeId,
    string $routeName,
    int $selectedVehicleId,
    int $oldVehicleId,
    string $status
): void {
    if (!routeTableExists($pdo, 'school_vehicles')) return;

    $clear = $pdo->prepare(
        "UPDATE school_vehicles
         SET route_id = NULL, route_name = ''
         WHERE tenant_id = ?
           AND route_id = ?
           AND id <> ?"
    );
    $clear->execute(array($tenantId, $routeId, $selectedVehicleId));

    if ($oldVehicleId > 0 && $oldVehicleId !== $selectedVehicleId) {
        $clearOld = $pdo->prepare(
            "UPDATE school_vehicles
             SET route_id = NULL, route_name = ''
             WHERE tenant_id = ? AND id = ? AND route_id = ?"
        );
        $clearOld->execute(array($tenantId, $oldVehicleId, $routeId));
    }

    if ($status === 'active') {
        $assign = $pdo->prepare(
            "UPDATE school_vehicles
             SET route_id = ?, route_name = ?
             WHERE tenant_id = ? AND id = ?"
        );
        $assign->execute(array($routeId, $routeName, $tenantId, $selectedVehicleId));
    } else {
        $clearSelected = $pdo->prepare(
            "UPDATE school_vehicles
             SET route_id = NULL, route_name = ''
             WHERE tenant_id = ? AND id = ? AND route_id = ?"
        );
        $clearSelected->execute(array($tenantId, $selectedVehicleId, $routeId));
    }
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    routeOut(false, 'Database connection is missing.', array('build' => ROUTE_BUILD), 500);
}

$scope = routeScope();
if ($scope['tenant_id'] <= 0 || $scope['user_id'] <= 0) {
    routeOut(false, 'Tenant or user session is missing.', array('build' => ROUTE_BUILD), 401);
}

if (empty($_SESSION['route_csrf_token']) || !is_string($_SESSION['route_csrf_token'])) {
    $_SESSION['route_csrf_token'] = bin2hex(random_bytes(32));
}

$input = routeInput();
$action = strtolower(trim((string)($input['action'] ?? $_GET['action'] ?? '')));

try {
    routeEnsureSchema($pdo);

    if ($action === 'list') {
        routeOut(true, 'Routes loaded successfully.', array(
            'records' => routeList($pdo, $scope, array_merge($_GET, $input)),
            'vehicles' => routeVehicleOptions($pdo, $scope),
            'stats' => routeStats($pdo, $scope),
            'csrf_token' => $_SESSION['route_csrf_token'],
            'build' => ROUTE_BUILD
        ));
    }

    if ($action === 'vehicles') {
        routeOut(true, 'Vehicles loaded successfully.', array(
            'vehicles' => routeVehicleOptions($pdo, $scope),
            'csrf_token' => $_SESSION['route_csrf_token'],
            'build' => ROUTE_BUILD
        ));
    }

    if ($action === 'detail' || $action === 'view') {
        $id = (int)($_GET['id'] ?? $input['id'] ?? 0);
        if ($id <= 0) throw new InvalidArgumentException('Select a valid route.');
        routeOut(true, 'Route details loaded.', array(
            'record' => routeFind($pdo, $scope, $id),
            'vehicles' => routeVehicleOptions($pdo, $scope),
            'csrf_token' => $_SESSION['route_csrf_token'],
            'build' => ROUTE_BUILD
        ));
    }

    if ($action === 'save' || $action === 'update') {
        routeCsrf($input);
        $id = (int)($input['id'] ?? 0);
        $data = routeValidate($input);
        $vehicle = routeResolveVehicle($pdo, $scope, $data['vehicle_id'], $data['status']);

        if (routeDuplicateCode($pdo, $scope['tenant_id'], $data['route_code'], $id)) {
            throw new InvalidArgumentException('Route Code already exists.');
        }

        if (
            $data['status'] === 'active' &&
            routeVehicleConflict($pdo, $scope['tenant_id'], $data['vehicle_id'], $id)
        ) {
            throw new InvalidArgumentException(
                'This vehicle is already assigned to another active route.'
            );
        }

        $branchId = $scope['branch_id'] > 0 ? $scope['branch_id'] : null;
        $pdo->beginTransaction();

        if ($id > 0) {
            $existing = routeFind($pdo, $scope, $id);
            $oldVehicleId = (int)($existing['vehicle_id'] ?? 0);

            $query = $pdo->prepare(
                "UPDATE school_routes SET
                    branch_id = ?,
                    route_name = ?,
                    route_code = ?,
                    zone_area = ?,
                    start_point = ?,
                    end_point = ?,
                    vehicle_id = ?,
                    driver_name = ?,
                    status = ?,
                    updated_by = ?
                 WHERE id = ? AND tenant_id = ?"
            );
            $query->execute(array(
                $branchId,
                $data['route_name'],
                $data['route_code'],
                $data['zone_area'],
                $data['start_point'],
                $data['end_point'],
                $data['vehicle_id'],
                (string)($vehicle['driver_name'] ?? ''),
                $data['status'],
                $scope['user_id'],
                $id,
                $scope['tenant_id']
            ));

            routeSyncVehicle(
                $pdo,
                $scope['tenant_id'],
                $id,
                $data['route_name'],
                $data['vehicle_id'],
                $oldVehicleId,
                $data['status']
            );

            $pdo->commit();
            routeOut(true, 'Route updated successfully.', array(
                'id' => $id,
                'record' => routeFind($pdo, $scope, $id),
                'csrf_token' => $_SESSION['route_csrf_token'],
                'build' => ROUTE_BUILD
            ));
        }

        $query = $pdo->prepare(
            "INSERT INTO school_routes (
                tenant_id,
                branch_id,
                route_name,
                route_code,
                zone_area,
                start_point,
                end_point,
                vehicle_id,
                driver_name,
                status,
                created_by,
                updated_by
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $query->execute(array(
            $scope['tenant_id'],
            $branchId,
            $data['route_name'],
            $data['route_code'],
            $data['zone_area'],
            $data['start_point'],
            $data['end_point'],
            $data['vehicle_id'],
            (string)($vehicle['driver_name'] ?? ''),
            $data['status'],
            $scope['user_id'],
            $scope['user_id']
        ));
        $newId = (int)$pdo->lastInsertId();

        routeSyncVehicle(
            $pdo,
            $scope['tenant_id'],
            $newId,
            $data['route_name'],
            $data['vehicle_id'],
            0,
            $data['status']
        );

        $pdo->commit();
        routeOut(true, 'Route added successfully.', array(
            'id' => $newId,
            'record' => routeFind($pdo, $scope, $newId),
            'csrf_token' => $_SESSION['route_csrf_token'],
            'build' => ROUTE_BUILD
        ), 201);
    }

    if ($action === 'delete') {
        routeCsrf($input);
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) throw new InvalidArgumentException('Select a valid route.');
        $record = routeFind($pdo, $scope, $id);

        $pdo->beginTransaction();
        $deleteStops = $pdo->prepare(
            'DELETE FROM school_route_stops WHERE tenant_id = ? AND route_id = ?'
        );
        $deleteStops->execute(array($scope['tenant_id'], $id));

        if (routeTableExists($pdo, 'school_vehicles')) {
            $clearVehicle = $pdo->prepare(
                "UPDATE school_vehicles
                 SET route_id = NULL, route_name = ''
                 WHERE tenant_id = ? AND route_id = ?"
            );
            $clearVehicle->execute(array($scope['tenant_id'], $id));
        }

        $query = $pdo->prepare(
            'DELETE FROM school_routes WHERE id = ? AND tenant_id = ?'
        );
        $query->execute(array($id, $scope['tenant_id']));
        if ($query->rowCount() < 1) throw new RuntimeException('Route could not be deleted.');
        $pdo->commit();

        routeOut(true, 'Route ' . $record['route_code'] . ' deleted successfully.', array(
            'id' => $id,
            'csrf_token' => $_SESSION['route_csrf_token'],
            'build' => ROUTE_BUILD
        ));
    }

    routeOut(false, 'Route API action is missing or invalid.', array('build' => ROUTE_BUILD), 400);
} catch (InvalidArgumentException $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    routeOut(false, $exception->getMessage(), array('build' => ROUTE_BUILD), 422);
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $local = strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false;
    $message = (string)$exception->getCode() === '23000'
        ? 'Route Code already exists or this vehicle is already assigned.'
        : ($local
            ? 'Database operation failed [' . ROUTE_BUILD . ']: ' . $exception->getMessage()
            : 'Database operation failed.');
    error_log('Route Master [' . ROUTE_BUILD . ']: ' . $exception->getMessage());
    routeOut(false, $message, array('build' => ROUTE_BUILD), 500);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Route Master [' . ROUTE_BUILD . ']: ' . $exception->getMessage());
    routeOut(false, 'Route request failed [' . ROUTE_BUILD . ']: ' . $exception->getMessage(), array('build' => ROUTE_BUILD), 500);
}

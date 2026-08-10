<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

const ROUTE_STOPS_BUILD = '2026-08-05-transport-fee-v5';

function stopOut(
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

function stopInput(): array
{
    $contentType = strtolower(
        (string)($_SERVER['CONTENT_TYPE'] ?? '')
    );

    if (strpos($contentType, 'application/json') !== false) {
        $decoded = json_decode(
            (string)file_get_contents('php://input'),
            true
        );

        return is_array($decoded) ? $decoded : array();
    }

    return $_POST;
}

function stopScope(): array
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

function stopCsrf(array $input): void
{
    $sessionToken = (string)(
        $_SESSION['route_stops_csrf_token'] ?? ''
    );
    $requestToken = (string)($input['csrf_token'] ?? '');

    if (
        $sessionToken === ''
        || $requestToken === ''
        || !hash_equals($sessionToken, $requestToken)
    ) {
        stopOut(
            false,
            'Invalid or expired CSRF token. Refresh the page.',
            array('build' => ROUTE_STOPS_BUILD),
            419
        );
    }
}

function stopTableExists(PDO $pdo, string $table): bool
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

function stopColumnExists(
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

function stopIndexExists(
    PDO $pdo,
    string $table,
    string $index
): bool {
    $query = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.statistics
         WHERE table_schema = DATABASE()
           AND table_name = ?
           AND index_name = ?'
    );

    $query->execute(array($table, $index));

    return (int)$query->fetchColumn() > 0;
}

function stopSafeSchemaExec(
    PDO $pdo,
    string $sql,
    string $label
): void {
    try {
        $pdo->exec($sql);
    } catch (Throwable $exception) {
        error_log(
            'Route Stops schema [' .
            ROUTE_STOPS_BUILD .
            '] ' .
            $label .
            ': ' .
            $exception->getMessage()
        );
    }
}

function stopEnsureColumn(
    PDO $pdo,
    string $column,
    string $definition
): void {
    if (
        !stopColumnExists(
            $pdo,
            'school_route_stops',
            $column
        )
    ) {
        stopSafeSchemaExec(
            $pdo,
            "ALTER TABLE school_route_stops
             ADD COLUMN `$column` $definition",
            'add ' . $column
        );
    }
}

function stopEnsureSchema(PDO $pdo): void
{
    if (!stopTableExists($pdo, 'school_routes')) {
        throw new RuntimeException(
            'Route Master table was not found. Install Route Master first.'
        );
    }

    if (!stopTableExists($pdo, 'school_route_stops')) {
        $pdo->exec(
            "CREATE TABLE school_route_stops (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id BIGINT UNSIGNED NOT NULL,
                branch_id BIGINT UNSIGNED NULL,
                route_id BIGINT UNSIGNED NOT NULL,
                stop_name VARCHAR(150) NOT NULL,
                stop_order INT UNSIGNED NOT NULL DEFAULT 1,
                transport_fee DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                status ENUM('active','inactive')
                    NOT NULL DEFAULT 'active',
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                created_at TIMESTAMP NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_route_stop_order (
                    tenant_id,
                    route_id,
                    stop_order
                ),
                KEY idx_route_stop_list (
                    tenant_id,
                    route_id,
                    status
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci"
        );

        return;
    }

    stopEnsureColumn(
        $pdo,
        'tenant_id',
        'BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER id'
    );
    stopEnsureColumn(
        $pdo,
        'branch_id',
        'BIGINT UNSIGNED NULL AFTER tenant_id'
    );
    stopEnsureColumn(
        $pdo,
        'route_id',
        'BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER branch_id'
    );
    stopEnsureColumn(
        $pdo,
        'stop_name',
        "VARCHAR(150) NOT NULL DEFAULT '' AFTER route_id"
    );
    stopEnsureColumn(
        $pdo,
        'stop_order',
        'INT UNSIGNED NOT NULL DEFAULT 1 AFTER stop_name'
    );
    stopEnsureColumn(
        $pdo,
        'transport_fee',
        'DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER stop_order'
    );
    stopEnsureColumn(
        $pdo,
        'status',
        "ENUM('active','inactive')
         NOT NULL DEFAULT 'active' AFTER transport_fee"
    );
    stopEnsureColumn(
        $pdo,
        'created_by',
        'BIGINT UNSIGNED NULL AFTER status'
    );
    stopEnsureColumn(
        $pdo,
        'updated_by',
        'BIGINT UNSIGNED NULL AFTER created_by'
    );
    stopEnsureColumn(
        $pdo,
        'created_at',
        'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
         AFTER updated_by'
    );
    stopEnsureColumn(
        $pdo,
        'updated_at',
        'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
         ON UPDATE CURRENT_TIMESTAMP AFTER created_at'
    );

    if (
        !stopIndexExists(
            $pdo,
            'school_route_stops',
            'idx_route_stop_list'
        )
    ) {
        stopSafeSchemaExec(
            $pdo,
            "ALTER TABLE school_route_stops
             ADD INDEX idx_route_stop_list
             (tenant_id, route_id, status)",
            'add route stop list index'
        );
    }
}

function stopRouteColumnExpression(
    PDO $pdo,
    string $preferred,
    ?string $fallback,
    string $alias
): string {
    if (
        stopColumnExists(
            $pdo,
            'school_routes',
            $preferred
        )
    ) {
        return '`' . $preferred . '` AS `' . $alias . '`';
    }

    if (
        $fallback !== null
        && stopColumnExists(
            $pdo,
            'school_routes',
            $fallback
        )
    ) {
        return '`' . $fallback . '` AS `' . $alias . '`';
    }

    return "'' AS `" . $alias . '`';
}

function stopRoute(
    PDO $pdo,
    array $scope,
    int $routeId
): array {
    $routeNameExpression = stopRouteColumnExpression(
        $pdo,
        'route_name',
        null,
        'route_name'
    );
    $routeCodeExpression = stopRouteColumnExpression(
        $pdo,
        'route_code',
        null,
        'route_code'
    );
    $zoneExpression = stopRouteColumnExpression(
        $pdo,
        'zone_area',
        'zone',
        'zone_area'
    );
    $startExpression = stopRouteColumnExpression(
        $pdo,
        'start_point',
        'start_location',
        'start_point'
    );
    $endExpression = stopRouteColumnExpression(
        $pdo,
        'end_point',
        'end_location',
        'end_point'
    );
    $statusExpression = stopRouteColumnExpression(
        $pdo,
        'status',
        null,
        'status'
    );

    $where = array(
        'id = ?',
        'tenant_id = ?'
    );

    $params = array(
        $routeId,
        $scope['tenant_id']
    );

    if (
        stopColumnExists(
            $pdo,
            'school_routes',
            'branch_id'
        )
    ) {
        $where[] = '(? = 0 OR branch_id = ? OR branch_id IS NULL)';
        $params[] = $scope['branch_id'];
        $params[] = $scope['branch_id'];
    }

    $query = $pdo->prepare(
        "SELECT
            id,
            $routeNameExpression,
            $routeCodeExpression,
            $zoneExpression,
            $startExpression,
            $endExpression,
            $statusExpression
         FROM school_routes
         WHERE " . implode(' AND ', $where) . "
         LIMIT 1"
    );

    $query->execute($params);
    $route = $query->fetch(PDO::FETCH_ASSOC);

    if (!$route) {
        throw new InvalidArgumentException(
            'Route ID ' .
            $routeId .
            ' was not found for the current school.'
        );
    }

    $route['route_name'] = trim(
        (string)($route['route_name'] ?? '')
    ) !== ''
        ? (string)$route['route_name']
        : 'Route ' . $routeId;

    $route['route_code'] = trim(
        (string)($route['route_code'] ?? '')
    ) !== ''
        ? (string)$route['route_code']
        : 'Route ID: ' . $routeId;

    return $route;
}

function stopFind(
    PDO $pdo,
    array $scope,
    int $routeId,
    int $id
): array {
    $query = $pdo->prepare(
        "SELECT *
         FROM school_route_stops
         WHERE id = ?
           AND route_id = ?
           AND tenant_id = ?
         LIMIT 1"
    );

    $query->execute(
        array(
            $id,
            $routeId,
            $scope['tenant_id']
        )
    );

    $record = $query->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        throw new InvalidArgumentException(
            'Route stop was not found.'
        );
    }

    return $record;
}

function stopList(
    PDO $pdo,
    array $scope,
    int $routeId,
    string $search
): array {
    $sql =
        "SELECT
            id,
            stop_name,
            stop_order,
            transport_fee,
            status,
            created_at,
            updated_at
         FROM school_route_stops
         WHERE tenant_id = ?
           AND route_id = ?";

    $params = array(
        $scope['tenant_id'],
        $routeId
    );

    if ($search !== '') {
        $sql .= ' AND stop_name LIKE ?';
        $params[] = '%' . $search . '%';
    }

    $sql .= ' ORDER BY stop_order, stop_name';

    $query = $pdo->prepare($sql);
    $query->execute($params);

    return $query->fetchAll(PDO::FETCH_ASSOC);
}

function stopStats(
    PDO $pdo,
    array $scope,
    int $routeId
): array {
    $query = $pdo->prepare(
        "SELECT
            COUNT(*) AS total_stops,
            COALESCE(SUM(status = 'active'), 0)
                AS active_stops,
            COALESCE(SUM(status = 'inactive'), 0)
                AS inactive_stops
         FROM school_route_stops
         WHERE tenant_id = ?
           AND route_id = ?"
    );

    $query->execute(
        array(
            $scope['tenant_id'],
            $routeId
        )
    );

    $stats = $query->fetch(PDO::FETCH_ASSOC);

    return is_array($stats)
        ? $stats
        : array(
            'total_stops' => 0,
            'active_stops' => 0,
            'inactive_stops' => 0
        );
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    stopOut(
        false,
        'Database connection is missing.',
        array('build' => ROUTE_STOPS_BUILD),
        500
    );
}

$scope = stopScope();

if ($scope['tenant_id'] <= 0 || $scope['user_id'] <= 0) {
    stopOut(
        false,
        'Tenant or user session is missing.',
        array('build' => ROUTE_STOPS_BUILD),
        401
    );
}

if (
    empty($_SESSION['route_stops_csrf_token'])
    || !is_string($_SESSION['route_stops_csrf_token'])
) {
    $_SESSION['route_stops_csrf_token'] =
        bin2hex(random_bytes(32));
}

$input = stopInput();
$action = strtolower(
    trim(
        (string)(
            $input['action']
            ?? $_GET['action']
            ?? ''
        )
    )
);

try {
    stopEnsureSchema($pdo);

    $routeId = (int)(
        $input['route_id']
        ?? $_GET['route_id']
        ?? 0
    );

    if ($routeId <= 0) {
        throw new InvalidArgumentException(
            'Select a valid route.'
        );
    }

    $route = stopRoute(
        $pdo,
        $scope,
        $routeId
    );

    if ($action === 'list') {
        stopOut(
            true,
            'Route stops loaded.',
            array(
                'route' => $route,
                'records' => stopList(
                    $pdo,
                    $scope,
                    $routeId,
                    trim(
                        (string)(
                            $_GET['search']
                            ?? $input['search']
                            ?? ''
                        )
                    )
                ),
                'stats' => stopStats(
                    $pdo,
                    $scope,
                    $routeId
                ),
                'csrf_token' =>
                    $_SESSION['route_stops_csrf_token'],
                'build' => ROUTE_STOPS_BUILD
            )
        );
    }

    if ($action === 'detail') {
        $id = (int)(
            $_GET['id']
            ?? $input['id']
            ?? 0
        );

        if ($id <= 0) {
            throw new InvalidArgumentException(
                'Select a valid stop.'
            );
        }

        stopOut(
            true,
            'Stop details loaded.',
            array(
                'record' => stopFind(
                    $pdo,
                    $scope,
                    $routeId,
                    $id
                ),
                'csrf_token' =>
                    $_SESSION['route_stops_csrf_token'],
                'build' => ROUTE_STOPS_BUILD
            )
        );
    }

    if ($action === 'save') {
        stopCsrf($input);

        $id = (int)($input['id'] ?? 0);
        $name = trim(
            (string)($input['stop_name'] ?? '')
        );
        $order = (int)($input['stop_order'] ?? 0);
        $transportFeeRaw = $input['transport_fee'] ?? 0;
        $transportFee = is_numeric($transportFeeRaw)
            ? round((float)$transportFeeRaw, 2)
            : -1;
        $status = strtolower(
            trim(
                (string)(
                    $input['status']
                    ?? 'active'
                )
            )
        );

        if ($name === '') {
            throw new InvalidArgumentException(
                'Stop Name is required.'
            );
        }

        if (strlen($name) > 150) {
            throw new InvalidArgumentException(
                'Stop Name cannot exceed 150 characters.'
            );
        }

        if ($order < 1 || $order > 999) {
            throw new InvalidArgumentException(
                'Stop Order must be between 1 and 999.'
            );
        }

        if (
            $transportFee < 0
            || $transportFee > 99999999.99
        ) {
            throw new InvalidArgumentException(
                'Transport Fee must be a valid amount between 0 and 99999999.99.'
            );
        }

        if (
            !in_array(
                $status,
                array('active', 'inactive'),
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Select a valid status.'
            );
        }

        $duplicate = $pdo->prepare(
            "SELECT id
             FROM school_route_stops
             WHERE tenant_id = ?
               AND route_id = ?
               AND stop_order = ?
               AND id <> ?
             LIMIT 1"
        );

        $duplicate->execute(
            array(
                $scope['tenant_id'],
                $routeId,
                $order,
                $id
            )
        );

        if ($duplicate->fetchColumn()) {
            throw new InvalidArgumentException(
                'This Stop Order is already used in the route.'
            );
        }

        $branchId = $scope['branch_id'] > 0
            ? $scope['branch_id']
            : null;

        if ($id > 0) {
            stopFind(
                $pdo,
                $scope,
                $routeId,
                $id
            );

            $query = $pdo->prepare(
                "UPDATE school_route_stops SET
                    stop_name = ?,
                    stop_order = ?,
                    transport_fee = ?,
                    status = ?,
                    updated_by = ?
                 WHERE id = ?
                   AND route_id = ?
                   AND tenant_id = ?"
            );

            $query->execute(
                array(
                    $name,
                    $order,
                    $transportFee,
                    $status,
                    $scope['user_id'],
                    $id,
                    $routeId,
                    $scope['tenant_id']
                )
            );

            stopOut(
                true,
                'Route stop updated successfully.',
                array(
                    'id' => $id,
                    'csrf_token' =>
                        $_SESSION['route_stops_csrf_token'],
                    'build' => ROUTE_STOPS_BUILD
                )
            );
        }

        $query = $pdo->prepare(
            "INSERT INTO school_route_stops (
                tenant_id,
                branch_id,
                route_id,
                stop_name,
                stop_order,
                transport_fee,
                status,
                created_by,
                updated_by
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $query->execute(
            array(
                $scope['tenant_id'],
                $branchId,
                $routeId,
                $name,
                $order,
                $transportFee,
                $status,
                $scope['user_id'],
                $scope['user_id']
            )
        );

        stopOut(
            true,
            'Route stop added successfully.',
            array(
                'id' => (int)$pdo->lastInsertId(),
                'csrf_token' =>
                    $_SESSION['route_stops_csrf_token'],
                'build' => ROUTE_STOPS_BUILD
            ),
            201
        );
    }

    if ($action === 'delete') {
        stopCsrf($input);

        $id = (int)($input['id'] ?? 0);

        if ($id <= 0) {
            throw new InvalidArgumentException(
                'Select a valid stop.'
            );
        }

        stopFind(
            $pdo,
            $scope,
            $routeId,
            $id
        );

        $query = $pdo->prepare(
            "DELETE FROM school_route_stops
             WHERE id = ?
               AND route_id = ?
               AND tenant_id = ?"
        );

        $query->execute(
            array(
                $id,
                $routeId,
                $scope['tenant_id']
            )
        );

        stopOut(
            true,
            'Route stop deleted successfully.',
            array(
                'id' => $id,
                'csrf_token' =>
                    $_SESSION['route_stops_csrf_token'],
                'build' => ROUTE_STOPS_BUILD
            )
        );
    }

    stopOut(
        false,
        'Route Stops API action is missing or invalid.',
        array('build' => ROUTE_STOPS_BUILD),
        400
    );
} catch (InvalidArgumentException $exception) {
    stopOut(
        false,
        $exception->getMessage(),
        array('build' => ROUTE_STOPS_BUILD),
        422
    );
} catch (PDOException $exception) {
    $host = strtolower(
        (string)($_SERVER['HTTP_HOST'] ?? '')
    );
    $local =
        strpos($host, 'localhost') !== false
        || strpos($host, '127.0.0.1') !== false;

    $message =
        (string)$exception->getCode() === '23000'
            ? 'This Stop Order is already used in the route.'
            : (
                $local
                    ? 'Database operation failed [' .
                        ROUTE_STOPS_BUILD .
                        ']: ' .
                        $exception->getMessage()
                    : 'Database operation failed.'
            );

    error_log(
        'Route Stops [' .
        ROUTE_STOPS_BUILD .
        ']: ' .
        $exception->getMessage()
    );

    stopOut(
        false,
        $message,
        array('build' => ROUTE_STOPS_BUILD),
        500
    );
} catch (Throwable $exception) {
    $host = strtolower(
        (string)($_SERVER['HTTP_HOST'] ?? '')
    );
    $local =
        strpos($host, 'localhost') !== false
        || strpos($host, '127.0.0.1') !== false;

    error_log(
        'Route Stops [' .
        ROUTE_STOPS_BUILD .
        ']: ' .
        $exception->getMessage()
    );

    stopOut(
        false,
        $local
            ? 'Route Stops request failed [' .
                ROUTE_STOPS_BUILD .
                ']: ' .
                $exception->getMessage()
            : 'Unable to complete Route Stops request.',
        array('build' => ROUTE_STOPS_BUILD),
        500
    );
}

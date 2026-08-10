<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

const DRIVER_BUILD = '2026-08-06-driver-master-v1';

function driverOut(
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

function driverInput(): array
{
    $type = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));

    if (strpos($type, 'application/json') !== false) {
        $decoded = json_decode(
            (string)file_get_contents('php://input'),
            true
        );

        return is_array($decoded) ? $decoded : array();
    }

    return $_POST;
}

function driverScope(): array
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

function driverCsrf(array $input): void
{
    $sessionToken = (string)(
        $_SESSION['driver_csrf_token'] ?? ''
    );
    $requestToken = (string)($input['csrf_token'] ?? '');

    if (
        $sessionToken === ''
        || $requestToken === ''
        || !hash_equals($sessionToken, $requestToken)
    ) {
        driverOut(
            false,
            'Invalid or expired CSRF token. Refresh the page.',
            array('build' => DRIVER_BUILD),
            419
        );
    }
}

function driverTableExists(PDO $pdo, string $table): bool
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

function driverColumnExists(
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

function driverSafeSchemaExec(
    PDO $pdo,
    string $sql,
    string $label
): void {
    try {
        $pdo->exec($sql);
    } catch (Throwable $exception) {
        error_log(
            'Driver Master schema [' .
            DRIVER_BUILD .
            '] ' .
            $label .
            ': ' .
            $exception->getMessage()
        );
    }
}

function driverEnsureColumn(
    PDO $pdo,
    string $table,
    string $column,
    string $definition
): void {
    if (!driverColumnExists($pdo, $table, $column)) {
        driverSafeSchemaExec(
            $pdo,
            "ALTER TABLE `$table`
             ADD COLUMN `$column` $definition",
            'add ' . $table . '.' . $column
        );
    }
}

function driverEnsureSchema(PDO $pdo): void
{
    if (!driverTableExists($pdo, 'school_drivers')) {
        $pdo->exec(
            "CREATE TABLE school_drivers (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id BIGINT UNSIGNED NOT NULL,
                branch_id BIGINT UNSIGNED NULL,
                driver_name VARCHAR(150) NOT NULL,
                mobile VARCHAR(20) NOT NULL,
                alternate_mobile VARCHAR(20) NULL,
                licence_number VARCHAR(60) NOT NULL,
                licence_expiry DATE NOT NULL,
                date_of_birth DATE NULL,
                joining_date DATE NULL,
                experience_years INT UNSIGNED NOT NULL DEFAULT 0,
                vehicle_id BIGINT UNSIGNED NULL,
                address VARCHAR(500) NULL,
                notes VARCHAR(500) NULL,
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
                UNIQUE KEY uq_school_driver_licence (
                    tenant_id,
                    licence_number
                ),
                KEY idx_school_driver_filter (
                    tenant_id,
                    branch_id,
                    status
                ),
                KEY idx_school_driver_vehicle (
                    tenant_id,
                    vehicle_id,
                    status
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (driverTableExists($pdo, 'school_vehicles')) {
        driverEnsureColumn(
            $pdo,
            'school_vehicles',
            'driver_id',
            'BIGINT UNSIGNED NULL AFTER driver_name'
        );
    }
}

function driverNormalizeLicence(string $value): string
{
    $value = strtoupper(trim($value));
    $value = preg_replace('/\s+/', ' ', $value);

    return is_string($value) ? $value : '';
}

function driverDate(
    string $value,
    string $label,
    bool $required
): ?string {
    $value = trim($value);

    if ($value === '') {
        if ($required) {
            throw new InvalidArgumentException(
                $label . ' is required.'
            );
        }

        return null;
    }

    $date = DateTimeImmutable::createFromFormat(
        'Y-m-d',
        $value
    );

    $errors = DateTimeImmutable::getLastErrors();
    $invalid = is_array($errors)
        && (
            ($errors['warning_count'] ?? 0) > 0
            || ($errors['error_count'] ?? 0) > 0
        );

    if (
        !$date
        || $invalid
        || $date->format('Y-m-d') !== $value
    ) {
        throw new InvalidArgumentException(
            $label . ' is invalid.'
        );
    }

    return $value;
}

function driverValidate(array $input): array
{
    $name = trim((string)($input['driver_name'] ?? ''));
    $mobile = trim((string)($input['mobile'] ?? ''));
    $alternate = trim(
        (string)($input['alternate_mobile'] ?? '')
    );
    $licence = driverNormalizeLicence(
        (string)($input['licence_number'] ?? '')
    );
    $expiry = driverDate(
        (string)($input['licence_expiry'] ?? ''),
        'Licence Expiry Date',
        true
    );
    $dob = driverDate(
        (string)($input['date_of_birth'] ?? ''),
        'Date of Birth',
        false
    );
    $joining = driverDate(
        (string)($input['joining_date'] ?? ''),
        'Joining Date',
        false
    );
    $experience = (int)(
        $input['experience_years'] ?? 0
    );
    $vehicleId = (int)($input['vehicle_id'] ?? 0);
    $address = trim((string)($input['address'] ?? ''));
    $notes = trim((string)($input['notes'] ?? ''));
    $status = strtolower(
        trim((string)($input['status'] ?? 'active'))
    );

    if ($name === '') {
        throw new InvalidArgumentException(
            'Driver Name is required.'
        );
    }

    if ($mobile === '') {
        throw new InvalidArgumentException(
            'Mobile Number is required.'
        );
    }

    if ($licence === '') {
        throw new InvalidArgumentException(
            'Driving Licence Number is required.'
        );
    }

    if ($experience < 0 || $experience > 60) {
        throw new InvalidArgumentException(
            'Experience must be between 0 and 60 years.'
        );
    }

    if (!in_array($status, array('active', 'inactive'), true)) {
        throw new InvalidArgumentException(
            'Status must be Active or Inactive.'
        );
    }

    return array(
        'driver_name' => $name,
        'mobile' => $mobile,
        'alternate_mobile' =>
            $alternate !== '' ? $alternate : null,
        'licence_number' => $licence,
        'licence_expiry' => $expiry,
        'date_of_birth' => $dob,
        'joining_date' => $joining,
        'experience_years' => $experience,
        'vehicle_id' => $vehicleId,
        'address' => $address !== '' ? $address : null,
        'notes' => $notes !== '' ? $notes : null,
        'status' => $status
    );
}

function driverVehicleOptions(
    PDO $pdo,
    array $scope
): array {
    if (!driverTableExists($pdo, 'school_vehicles')) {
        return array();
    }

    $driverIdExpression = driverColumnExists(
        $pdo,
        'school_vehicles',
        'driver_id'
    )
        ? 'driver_id'
        : 'NULL AS driver_id';

    $query = $pdo->prepare(
        "SELECT
            id,
            vehicle_name,
            vehicle_number,
            vehicle_type,
            {$driverIdExpression},
            driver_name,
            status
         FROM school_vehicles
         WHERE tenant_id = ?
           AND (
                ? = 0
                OR branch_id = ?
                OR branch_id IS NULL
           )
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

function driverResolveVehicle(
    PDO $pdo,
    array $scope,
    int $vehicleId,
    int $driverId,
    string $driverStatus
): ?array {
    if ($vehicleId <= 0) {
        return null;
    }

    if (!driverTableExists($pdo, 'school_vehicles')) {
        throw new RuntimeException(
            'Vehicle Master table was not found.'
        );
    }

    $driverIdExpression = driverColumnExists(
        $pdo,
        'school_vehicles',
        'driver_id'
    )
        ? 'driver_id'
        : 'NULL AS driver_id';

    $query = $pdo->prepare(
        "SELECT
            id,
            vehicle_name,
            vehicle_number,
            {$driverIdExpression},
            driver_name,
            status
         FROM school_vehicles
         WHERE id = ?
           AND tenant_id = ?
           AND (
                ? = 0
                OR branch_id = ?
                OR branch_id IS NULL
           )
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
        throw new InvalidArgumentException(
            'The selected vehicle was not found.'
        );
    }

    if (
        $driverStatus === 'active'
        && strtolower((string)$vehicle['status']) !== 'active'
    ) {
        throw new InvalidArgumentException(
            'Only an active vehicle can be assigned to an active driver.'
        );
    }

    $assigned = (int)($vehicle['driver_id'] ?? 0);

    if ($assigned > 0 && $assigned !== $driverId) {
        throw new InvalidArgumentException(
            'This vehicle is already assigned to another driver.'
        );
    }

    return $vehicle;
}

function driverDuplicateLicence(
    PDO $pdo,
    int $tenantId,
    string $licence,
    int $excludeId
): bool {
    $query = $pdo->prepare(
        "SELECT id
         FROM school_drivers
         WHERE tenant_id = ?
           AND licence_number = ?
           AND id <> ?
         LIMIT 1"
    );

    $query->execute(array(
        $tenantId,
        $licence,
        $excludeId
    ));

    return (bool)$query->fetchColumn();
}

function driverFind(
    PDO $pdo,
    array $scope,
    int $id
): array {
    $query = $pdo->prepare(
        "SELECT
            d.*,
            v.vehicle_name,
            v.vehicle_number
         FROM school_drivers d
         LEFT JOIN school_vehicles v
           ON v.id = d.vehicle_id
          AND v.tenant_id = d.tenant_id
         WHERE d.id = ?
           AND d.tenant_id = ?
           AND (
                ? = 0
                OR d.branch_id = ?
                OR d.branch_id IS NULL
           )
         LIMIT 1"
    );

    $query->execute(array(
        $id,
        $scope['tenant_id'],
        $scope['branch_id'],
        $scope['branch_id']
    ));

    $record = $query->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        throw new InvalidArgumentException(
            'Driver was not found.'
        );
    }

    foreach (
        array(
            'licence_expiry',
            'date_of_birth',
            'joining_date'
        ) as $column
    ) {
        $record[$column . '_display'] =
            !empty($record[$column])
                ? date(
                    'd-m-Y',
                    strtotime((string)$record[$column])
                )
                : '-';
    }

    $record['created_at_display'] =
        !empty($record['created_at'])
            ? date(
                'd-m-Y h:i A',
                strtotime((string)$record['created_at'])
            )
            : '-';

    return $record;
}

function driverList(
    PDO $pdo,
    array $scope,
    array $filters
): array {
    $where = array(
        'd.tenant_id = ?',
        '(
            ? = 0
            OR d.branch_id = ?
            OR d.branch_id IS NULL
        )'
    );

    $params = array(
        $scope['tenant_id'],
        $scope['branch_id'],
        $scope['branch_id']
    );

    $search = trim(
        (string)($filters['search'] ?? '')
    );

    if ($search !== '') {
        $where[] =
            '(d.driver_name LIKE ?
              OR d.mobile LIKE ?
              OR d.licence_number LIKE ?)';

        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $status = strtolower(
        trim((string)($filters['status'] ?? 'all'))
    );

    if ($status !== '' && $status !== 'all') {
        $where[] = 'd.status = ?';
        $params[] = $status;
    }

    $assignment = strtolower(
        trim((string)($filters['assignment'] ?? 'all'))
    );

    if ($assignment === 'assigned') {
        $where[] = 'd.vehicle_id IS NOT NULL';
    } elseif ($assignment === 'unassigned') {
        $where[] = 'd.vehicle_id IS NULL';
    }

    $query = $pdo->prepare(
        "SELECT
            d.*,
            v.vehicle_name,
            v.vehicle_number,
            CASE
                WHEN d.licence_expiry < CURDATE()
                THEN 'expired'
                WHEN d.licence_expiry <= DATE_ADD(
                    CURDATE(),
                    INTERVAL 30 DAY
                )
                THEN 'expiring'
                ELSE 'valid'
            END AS licence_status,
            DATE_FORMAT(
                d.licence_expiry,
                '%d-%m-%Y'
            ) AS licence_expiry_display
         FROM school_drivers d
         LEFT JOIN school_vehicles v
           ON v.id = d.vehicle_id
          AND v.tenant_id = d.tenant_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY
            CASE WHEN d.status = 'active' THEN 0 ELSE 1 END,
            d.driver_name,
            d.licence_number
         LIMIT 500"
    );

    $query->execute($params);

    return $query->fetchAll(PDO::FETCH_ASSOC);
}

function driverStats(
    PDO $pdo,
    array $scope
): array {
    $query = $pdo->prepare(
        "SELECT
            COUNT(*) AS total_drivers,
            COALESCE(SUM(status = 'active'), 0)
                AS active_drivers,
            COALESCE(SUM(vehicle_id IS NOT NULL), 0)
                AS assigned_drivers,
            COALESCE(
                SUM(
                    licence_expiry >= CURDATE()
                    AND licence_expiry <= DATE_ADD(
                        CURDATE(),
                        INTERVAL 30 DAY
                    )
                ),
                0
            ) AS expiring_licences
         FROM school_drivers
         WHERE tenant_id = ?
           AND (
                ? = 0
                OR branch_id = ?
                OR branch_id IS NULL
           )"
    );

    $query->execute(array(
        $scope['tenant_id'],
        $scope['branch_id'],
        $scope['branch_id']
    ));

    return $query->fetch(PDO::FETCH_ASSOC) ?: array(
        'total_drivers' => 0,
        'active_drivers' => 0,
        'assigned_drivers' => 0,
        'expiring_licences' => 0
    );
}

function driverSyncVehicle(
    PDO $pdo,
    int $tenantId,
    int $driverId,
    string $driverName,
    int $selectedVehicleId,
    int $oldVehicleId,
    string $status
): void {
    if (!driverTableExists($pdo, 'school_vehicles')) {
        return;
    }

    $hasDriverId = driverColumnExists(
        $pdo,
        'school_vehicles',
        'driver_id'
    );

    if ($oldVehicleId > 0 && $oldVehicleId !== $selectedVehicleId) {
        $sql = $hasDriverId
            ? "UPDATE school_vehicles
               SET driver_id = NULL, driver_name = ''
               WHERE tenant_id = ?
                 AND id = ?
                 AND driver_id = ?"
            : "UPDATE school_vehicles
               SET driver_name = ''
               WHERE tenant_id = ?
                 AND id = ?
                 AND driver_name = ?";

        $clear = $pdo->prepare($sql);
        $clear->execute(array(
            $tenantId,
            $oldVehicleId,
            $hasDriverId ? $driverId : $driverName
        ));
    }

    if ($selectedVehicleId <= 0 || $status !== 'active') {
        return;
    }

    if ($hasDriverId) {
        $assign = $pdo->prepare(
            "UPDATE school_vehicles
             SET driver_id = ?, driver_name = ?
             WHERE tenant_id = ? AND id = ?"
        );

        $assign->execute(array(
            $driverId,
            $driverName,
            $tenantId,
            $selectedVehicleId
        ));
    } else {
        $assign = $pdo->prepare(
            "UPDATE school_vehicles
             SET driver_name = ?
             WHERE tenant_id = ? AND id = ?"
        );

        $assign->execute(array(
            $driverName,
            $tenantId,
            $selectedVehicleId
        ));
    }
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    driverOut(
        false,
        'Database connection is missing.',
        array('build' => DRIVER_BUILD),
        500
    );
}

$scope = driverScope();

if ($scope['tenant_id'] <= 0 || $scope['user_id'] <= 0) {
    driverOut(
        false,
        'Tenant or user session is missing.',
        array('build' => DRIVER_BUILD),
        401
    );
}

if (
    empty($_SESSION['driver_csrf_token'])
    || !is_string($_SESSION['driver_csrf_token'])
) {
    $_SESSION['driver_csrf_token'] =
        bin2hex(random_bytes(32));
}

$input = driverInput();

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
    driverEnsureSchema($pdo);

    if ($action === 'list') {
        driverOut(
            true,
            'Drivers loaded successfully.',
            array(
                'records' => driverList(
                    $pdo,
                    $scope,
                    array_merge($_GET, $input)
                ),
                'vehicles' => driverVehicleOptions(
                    $pdo,
                    $scope
                ),
                'stats' => driverStats($pdo, $scope),
                'csrf_token' =>
                    $_SESSION['driver_csrf_token'],
                'build' => DRIVER_BUILD
            )
        );
    }

    if ($action === 'detail' || $action === 'view') {
        $id = (int)(
            $_GET['id']
            ?? $input['id']
            ?? 0
        );

        if ($id <= 0) {
            throw new InvalidArgumentException(
                'Select a valid driver.'
            );
        }

        driverOut(
            true,
            'Driver details loaded.',
            array(
                'record' => driverFind(
                    $pdo,
                    $scope,
                    $id
                ),
                'vehicles' => driverVehicleOptions(
                    $pdo,
                    $scope
                ),
                'csrf_token' =>
                    $_SESSION['driver_csrf_token'],
                'build' => DRIVER_BUILD
            )
        );
    }

    if ($action === 'save' || $action === 'update') {
        driverCsrf($input);

        $id = (int)($input['id'] ?? 0);
        $data = driverValidate($input);

        if (
            driverDuplicateLicence(
                $pdo,
                $scope['tenant_id'],
                $data['licence_number'],
                $id
            )
        ) {
            throw new InvalidArgumentException(
                'Driving Licence Number already exists.'
            );
        }

        driverResolveVehicle(
            $pdo,
            $scope,
            $data['vehicle_id'],
            $id,
            $data['status']
        );

        $branchId = $scope['branch_id'] > 0
            ? $scope['branch_id']
            : null;

        $pdo->beginTransaction();

        if ($id > 0) {
            $existing = driverFind(
                $pdo,
                $scope,
                $id
            );

            $oldVehicleId = (int)(
                $existing['vehicle_id'] ?? 0
            );

            $query = $pdo->prepare(
                "UPDATE school_drivers SET
                    branch_id = ?,
                    driver_name = ?,
                    mobile = ?,
                    alternate_mobile = ?,
                    licence_number = ?,
                    licence_expiry = ?,
                    date_of_birth = ?,
                    joining_date = ?,
                    experience_years = ?,
                    vehicle_id = ?,
                    address = ?,
                    notes = ?,
                    status = ?,
                    updated_by = ?
                 WHERE id = ?
                   AND tenant_id = ?"
            );

            $query->execute(array(
                $branchId,
                $data['driver_name'],
                $data['mobile'],
                $data['alternate_mobile'],
                $data['licence_number'],
                $data['licence_expiry'],
                $data['date_of_birth'],
                $data['joining_date'],
                $data['experience_years'],
                $data['vehicle_id'] > 0
                    ? $data['vehicle_id']
                    : null,
                $data['address'],
                $data['notes'],
                $data['status'],
                $scope['user_id'],
                $id,
                $scope['tenant_id']
            ));

            driverSyncVehicle(
                $pdo,
                $scope['tenant_id'],
                $id,
                $data['driver_name'],
                $data['vehicle_id'],
                $oldVehicleId,
                $data['status']
            );

            $pdo->commit();

            driverOut(
                true,
                'Driver updated successfully.',
                array(
                    'id' => $id,
                    'record' => driverFind(
                        $pdo,
                        $scope,
                        $id
                    ),
                    'csrf_token' =>
                        $_SESSION['driver_csrf_token'],
                    'build' => DRIVER_BUILD
                )
            );
        }

        $query = $pdo->prepare(
            "INSERT INTO school_drivers (
                tenant_id,
                branch_id,
                driver_name,
                mobile,
                alternate_mobile,
                licence_number,
                licence_expiry,
                date_of_birth,
                joining_date,
                experience_years,
                vehicle_id,
                address,
                notes,
                status,
                created_by,
                updated_by
             ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?
             )"
        );

        $query->execute(array(
            $scope['tenant_id'],
            $branchId,
            $data['driver_name'],
            $data['mobile'],
            $data['alternate_mobile'],
            $data['licence_number'],
            $data['licence_expiry'],
            $data['date_of_birth'],
            $data['joining_date'],
            $data['experience_years'],
            $data['vehicle_id'] > 0
                ? $data['vehicle_id']
                : null,
            $data['address'],
            $data['notes'],
            $data['status'],
            $scope['user_id'],
            $scope['user_id']
        ));

        $newId = (int)$pdo->lastInsertId();

        driverSyncVehicle(
            $pdo,
            $scope['tenant_id'],
            $newId,
            $data['driver_name'],
            $data['vehicle_id'],
            0,
            $data['status']
        );

        $pdo->commit();

        driverOut(
            true,
            'Driver added successfully.',
            array(
                'id' => $newId,
                'record' => driverFind(
                    $pdo,
                    $scope,
                    $newId
                ),
                'csrf_token' =>
                    $_SESSION['driver_csrf_token'],
                'build' => DRIVER_BUILD
            ),
            201
        );
    }

    if ($action === 'delete') {
        driverCsrf($input);

        $id = (int)($input['id'] ?? 0);

        if ($id <= 0) {
            throw new InvalidArgumentException(
                'Select a valid driver.'
            );
        }

        $record = driverFind(
            $pdo,
            $scope,
            $id
        );

        $pdo->beginTransaction();

        driverSyncVehicle(
            $pdo,
            $scope['tenant_id'],
            $id,
            (string)$record['driver_name'],
            0,
            (int)($record['vehicle_id'] ?? 0),
            'inactive'
        );

        $query = $pdo->prepare(
            "DELETE FROM school_drivers
             WHERE id = ?
               AND tenant_id = ?"
        );

        $query->execute(array(
            $id,
            $scope['tenant_id']
        ));

        if ($query->rowCount() < 1) {
            throw new RuntimeException(
                'Driver could not be deleted.'
            );
        }

        $pdo->commit();

        driverOut(
            true,
            'Driver ' .
            $record['driver_name'] .
            ' deleted successfully.',
            array(
                'id' => $id,
                'csrf_token' =>
                    $_SESSION['driver_csrf_token'],
                'build' => DRIVER_BUILD
            )
        );
    }

    driverOut(
        false,
        'Driver API action is missing or invalid.',
        array('build' => DRIVER_BUILD),
        400
    );
} catch (InvalidArgumentException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    driverOut(
        false,
        $exception->getMessage(),
        array('build' => DRIVER_BUILD),
        422
    );
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $host = strtolower(
        (string)($_SERVER['HTTP_HOST'] ?? '')
    );

    $local =
        strpos($host, 'localhost') !== false
        || strpos($host, '127.0.0.1') !== false;

    $message =
        (string)$exception->getCode() === '23000'
            ? 'Driving Licence Number already exists or the vehicle is already assigned.'
            : (
                $local
                    ? 'Database operation failed [' .
                        DRIVER_BUILD .
                        ']: ' .
                        $exception->getMessage()
                    : 'Database operation failed.'
            );

    error_log(
        'Driver Master [' .
        DRIVER_BUILD .
        ']: ' .
        $exception->getMessage()
    );

    driverOut(
        false,
        $message,
        array('build' => DRIVER_BUILD),
        500
    );
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Driver Master [' .
        DRIVER_BUILD .
        ']: ' .
        $exception->getMessage()
    );

    driverOut(
        false,
        'Driver request failed [' .
        DRIVER_BUILD .
        ']: ' .
        $exception->getMessage(),
        array('build' => DRIVER_BUILD),
        500
    );
}

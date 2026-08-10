<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

/*
 * Link each API request to the exact Academic Year sidebar child. Bootstrap
 * reads JSON requests and applies View/Add/Edit/Delete before this API runs.
 */
const SCHOOL_API_PAGE_KEY_MAP = [
    'academic_years' => 'academic_years',
    'academic_calendar' => 'academic_calendar',
    'terms_semesters' => 'terms_semesters',
    'holidays' => 'holidays',
];

require_once dirname(__DIR__) . '/includes/bootstrap.php';

function ayJson(bool $success, string $message = '', array $data = [], int $status = 200): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }

    echo json_encode(
        ['success' => $success, 'message' => $message, 'data' => $data],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function ayInput(): array
{
    if (function_exists('school_request_payload')) {
        return school_request_payload();
    }

    $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    if (str_contains($contentType, 'application/json')) {
        $decoded = json_decode((string)file_get_contents('php://input'), true);
        return is_array($decoded) ? $decoded : [];
    }

    return $_POST;
}

function ayScope(): array
{
    $user = function_exists('current_user') ? current_user() : [];

    return [
        'tenant_id' => (int)($user['tenant_id'] ?? $user['school_id'] ?? $_SESSION['tenant_id'] ?? $_SESSION['school_id'] ?? 0),
        'branch_id' => (int)($user['branch_id'] ?? $_SESSION['branch_id'] ?? 0),
        'user_id' => (int)($user['id'] ?? $user['user_id'] ?? $_SESSION['user_id'] ?? 0),
        'role_name' => (string)($user['role_name'] ?? $user['role'] ?? $_SESSION['role_name'] ?? $_SESSION['role'] ?? ''),
    ];
}

function ayNormalizeAction(string $action): string
{
    $action = strtolower(trim($action));
    return $action === 'add' ? 'create' : $action;
}

function ayCan(string $moduleKey, string $action): bool
{
    $moduleKey = strtolower(trim($moduleKey));
    $action = ayNormalizeAction($action);

    $action = match ($action) {
        'add', 'create', 'store', 'insert' => 'create',
        'edit', 'update', 'archive', 'status' => 'edit',
        'delete', 'remove', 'destroy' => 'delete',
        default => 'view',
    };

    if (function_exists('is_super_admin') && is_super_admin()) {
        return true;
    }

    if (!function_exists('school_effective_permission')) {
        return false;
    }

    try {
        if (function_exists('school_sidebar_permission_decision')) {
            $decision = school_sidebar_permission_decision($moduleKey, $action);
            if ($decision !== null) {
                return $decision;
            }
        }

        return school_effective_permission('academic_year', $action);
    } catch (Throwable $exception) {
        error_log('Academic Year permission check: ' . $exception->getMessage());
        return false;
    }
}

function ayCsrfToken(): string
{
    if (function_exists('csrfToken')) {
        return (string)csrfToken();
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['academic_year_csrf_token']) || !is_string($_SESSION['academic_year_csrf_token'])) {
        $_SESSION['academic_year_csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string)$_SESSION['academic_year_csrf_token'];
}

function ayCsrf(array $input): void
{
    $token = trim((string)($input['csrf_token'] ?? ''));
    $valid = false;

    if ($token !== '' && function_exists('csrf_is_valid')) {
        try {
            $valid = csrf_is_valid($token);
        } catch (Throwable $exception) {
            error_log('Academic Year CSRF helper: ' . $exception->getMessage());
        }
    }

    if (!$valid && session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (!$valid) {
        foreach (['academic_year_csrf_token', 'csrf_token'] as $sessionKey) {
            $sessionToken = (string)($_SESSION[$sessionKey] ?? '');
            if ($sessionToken !== '' && $token !== '' && hash_equals($sessionToken, $token)) {
                $valid = true;
                break;
            }
        }
    }

    if (!$valid) {
        ayJson(false, 'Invalid or expired CSRF token. Refresh the page and try again.', [], 419);
    }
}

function ayTableExists(PDO $pdo, string $table): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table_name'
    );
    $statement->execute(['table_name' => $table]);
    return (int)$statement->fetchColumn() > 0;
}

function ayEnsure(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS academic_year_module_records (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        tenant_id BIGINT UNSIGNED NOT NULL,
        module_key VARCHAR(80) NOT NULL,
        record_data JSON NOT NULL,
        status ENUM('active','inactive') NOT NULL DEFAULT 'active',
        created_by BIGINT UNSIGNED NULL,
        updated_by BIGINT UNSIGNED NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_academic_module (tenant_id,module_key,status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function ayDateValid(string $date): bool
{
    $value = DateTime::createFromFormat('Y-m-d', $date);
    return $value !== false && $value->format('Y-m-d') === $date;
}

function ayCleanText(mixed $value, int $maxLength = 255): string
{
    $text = trim((string)$value);
    return mb_substr($text, 0, $maxLength);
}

function ayValidateModuleData(string $moduleKey, array $data): array
{
    switch ($moduleKey) {
        case 'academic_years':
            $yearName = ayCleanText($data['year_name'] ?? '', 30);
            $startDate = ayCleanText($data['start_date'] ?? '', 10);
            $endDate = ayCleanText($data['end_date'] ?? '', 10);
            $isCurrent = (int)($data['is_current'] ?? 0) === 1 ? 1 : 0;
            $status = strtolower(ayCleanText($data['status'] ?? 'active', 20));

            if ($yearName === '' || $startDate === '' || $endDate === '') {
                throw new InvalidArgumentException('Academic year, start date and end date are required.');
            }
            if (!ayDateValid($startDate) || !ayDateValid($endDate)) {
                throw new InvalidArgumentException('Enter valid start and end dates.');
            }
            if ($startDate > $endDate) {
                throw new InvalidArgumentException('End date must be on or after the start date.');
            }
            if (!in_array($status, ['active', 'closed'], true)) {
                throw new InvalidArgumentException('Invalid academic year status.');
            }

            return [
                'academic_year_code' => ayCleanText($data['academic_year_code'] ?? $yearName, 50) ?: $yearName,
                'year_name' => $yearName,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'is_current' => $isCurrent,
                'status' => $status,
            ];

        case 'academic_calendar':
            $eventName = ayCleanText($data['event_name'] ?? '', 150);
            $eventDate = ayCleanText($data['event_date'] ?? '', 10);
            $eventType = strtolower(ayCleanText($data['event_type'] ?? 'academic', 30));
            $description = ayCleanText($data['description'] ?? '', 1000);
            $status = strtolower(ayCleanText($data['status'] ?? 'active', 20));

            if ($eventName === '' || !ayDateValid($eventDate)) {
                throw new InvalidArgumentException('Event name and a valid event date are required.');
            }
            if (!in_array($eventType, ['academic', 'exam', 'event', 'other'], true)) {
                throw new InvalidArgumentException('Invalid event type.');
            }
            if (!in_array($status, ['active', 'inactive'], true)) {
                throw new InvalidArgumentException('Invalid status.');
            }

            return [
                'event_name' => $eventName,
                'event_date' => $eventDate,
                'event_type' => $eventType,
                'description' => $description,
                'status' => $status,
            ];

        case 'terms_semesters':
            $termName = ayCleanText($data['term_name'] ?? '', 120);
            $startDate = ayCleanText($data['start_date'] ?? '', 10);
            $endDate = ayCleanText($data['end_date'] ?? '', 10);
            $description = ayCleanText($data['description'] ?? '', 1000);
            $status = strtolower(ayCleanText($data['status'] ?? 'active', 20));

            if ($termName === '' || !ayDateValid($startDate) || !ayDateValid($endDate)) {
                throw new InvalidArgumentException('Term name and valid dates are required.');
            }
            if ($startDate > $endDate) {
                throw new InvalidArgumentException('Term end date must be on or after the start date.');
            }
            if (!in_array($status, ['active', 'inactive'], true)) {
                throw new InvalidArgumentException('Invalid status.');
            }

            return [
                'term_name' => $termName,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'description' => $description,
                'status' => $status,
            ];

        case 'holidays':
            $holidayName = ayCleanText($data['holiday_name'] ?? '', 150);
            $holidayDate = ayCleanText($data['holiday_date'] ?? '', 10);
            $holidayType = strtolower(ayCleanText($data['holiday_type'] ?? 'school', 30));
            $description = ayCleanText($data['description'] ?? '', 1000);
            $status = strtolower(ayCleanText($data['status'] ?? 'active', 20));

            if ($holidayName === '' || !ayDateValid($holidayDate)) {
                throw new InvalidArgumentException('Holiday name and a valid date are required.');
            }
            if (!in_array($holidayType, ['public', 'school', 'optional'], true)) {
                throw new InvalidArgumentException('Invalid holiday type.');
            }
            if (!in_array($status, ['active', 'inactive'], true)) {
                throw new InvalidArgumentException('Invalid status.');
            }

            return [
                'holiday_name' => $holidayName,
                'holiday_date' => $holidayDate,
                'holiday_type' => $holidayType,
                'description' => $description,
                'status' => $status,
            ];
    }

    throw new InvalidArgumentException('Invalid Academic Year module.');
}

function ayAudit(PDO $pdo, array $scope, string $action, string $tableName, ?int $recordId, array $oldValues = [], array $newValues = []): void
{
    if (!ayTableExists($pdo, 'activity_logs')) {
        return;
    }

    try {
        $statement = $pdo->prepare("INSERT INTO activity_logs
            (tenant_id,branch_id,user_id,role_id,module_name,action_key,table_name,record_id,old_values,new_values,description,ip_address,user_agent)
            VALUES (:tenant_id,:branch_id,:user_id,NULL,'Academic Year Management',:action_key,:table_name,:record_id,:old_values,:new_values,:description,:ip_address,:user_agent)");
        $statement->execute([
            'tenant_id' => $scope['tenant_id'],
            'branch_id' => $scope['branch_id'] ?: null,
            'user_id' => $scope['user_id'] ?: null,
            'action_key' => $action,
            'table_name' => $tableName,
            'record_id' => $recordId,
            'old_values' => $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'new_values' => $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'description' => ucfirst(str_replace('_', ' ', $action)),
            'ip_address' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
            'user_agent' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1000),
        ]);
    } catch (Throwable $exception) {
        error_log('Academic Year audit: ' . $exception->getMessage());
    }
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    ayJson(false, 'Database connection unavailable.', [], 500);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$scope = ayScope();
if ($scope['tenant_id'] <= 0 || $scope['user_id'] <= 0) {
    ayJson(false, 'Active School Admin login required.', [], 401);
}

if (!ayTableExists($pdo, 'academic_years')) {
    ayJson(false, 'Missing database table: academic_years.', [], 500);
}

ayEnsure($pdo);

$input = ayInput();
$action = strtolower(trim((string)($input['action'] ?? $_GET['action'] ?? '')));
$moduleKey = strtolower(trim((string)($input['module_key'] ?? $_GET['module_key'] ?? '')));
$validModules = ['academic_years', 'academic_calendar', 'terms_semesters', 'holidays'];

try {
    if (!in_array($moduleKey, $validModules, true)) {
        ayJson(false, 'Invalid Academic Year module.', ['module_key' => $moduleKey], 400);
    }

    if ($action === 'list') {
        if (!ayCan($moduleKey, 'view')) {
            ayJson(false, 'You do not have permission to view this section.', [], 403);
        }

        if ($moduleKey === 'academic_years') {
            $statement = $pdo->prepare("SELECT id,academic_year_code,year_name,start_date,end_date,is_current,status
                FROM academic_years WHERE tenant_id=:tenant_id ORDER BY is_current DESC,start_date DESC,id DESC");
            $statement->execute(['tenant_id' => $scope['tenant_id']]);
            $records = array_map(
                static fn(array $row): array => [
                    'id' => (int)$row['id'],
                    'status' => (string)$row['status'],
                    'data' => [
                        'academic_year_code' => (string)$row['academic_year_code'],
                        'year_name' => (string)$row['year_name'],
                        'start_date' => (string)$row['start_date'],
                        'end_date' => (string)$row['end_date'],
                        'is_current' => (int)$row['is_current'],
                        'status' => (string)$row['status'],
                    ],
                ],
                $statement->fetchAll(PDO::FETCH_ASSOC)
            );
        } else {
            $statement = $pdo->prepare("SELECT id,record_data,status FROM academic_year_module_records
                WHERE tenant_id=:tenant_id AND module_key=:module_key ORDER BY id DESC");
            $statement->execute(['tenant_id' => $scope['tenant_id'], 'module_key' => $moduleKey]);
            $records = [];
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $data = json_decode((string)$row['record_data'], true);
                $data = is_array($data) ? $data : [];
                $data['status'] = (string)$row['status'];
                $records[] = [
                    'id' => (int)$row['id'],
                    'status' => (string)$row['status'],
                    'data' => $data,
                ];
            }
        }

        ayJson(true, 'Records loaded successfully.', [
            'records' => $records,
            'csrf_token' => ayCsrfToken(),
        ]);
    }

    if (in_array($action, ['create', 'update'], true)) {
        if (!ayCan($moduleKey, $action === 'create' ? 'create' : 'edit')) {
            ayJson(false, 'You do not have permission to save this section.', [], 403);
        }

        ayCsrf($input);
        $id = (int)($input['id'] ?? 0);
        $rawData = is_array($input['data'] ?? null) ? $input['data'] : [];
        $data = ayValidateModuleData($moduleKey, $rawData);

        $pdo->beginTransaction();

        if ($moduleKey === 'academic_years') {
            $duplicate = $pdo->prepare("SELECT id FROM academic_years
                WHERE tenant_id=:tenant_id AND year_name=:year_name AND id<>:id LIMIT 1");
            $duplicate->execute([
                'tenant_id' => $scope['tenant_id'],
                'year_name' => $data['year_name'],
                'id' => $id,
            ]);
            if ($duplicate->fetchColumn()) {
                throw new InvalidArgumentException('This academic year already exists.');
            }

            if ((int)$data['is_current'] === 1) {
                $pdo->prepare("UPDATE academic_years SET is_current=0 WHERE tenant_id=:tenant_id")
                    ->execute(['tenant_id' => $scope['tenant_id']]);
            }

            if ($id > 0) {
                $oldStatement = $pdo->prepare("SELECT * FROM academic_years WHERE id=:id AND tenant_id=:tenant_id FOR UPDATE");
                $oldStatement->execute(['id' => $id, 'tenant_id' => $scope['tenant_id']]);
                $old = $oldStatement->fetch(PDO::FETCH_ASSOC);
                if (!$old) {
                    throw new InvalidArgumentException('Academic year record not found.');
                }

                $statement = $pdo->prepare("UPDATE academic_years SET
                    academic_year_code=:academic_year_code,year_name=:year_name,start_date=:start_date,
                    end_date=:end_date,is_current=:is_current,status=:status
                    WHERE id=:id AND tenant_id=:tenant_id");
                $statement->execute($data + ['id' => $id, 'tenant_id' => $scope['tenant_id']]);
                ayAudit($pdo, $scope, 'update_academic_year', 'academic_years', $id, $old, $data);
                $message = 'Academic year updated successfully.';
            } else {
                $statement = $pdo->prepare("INSERT INTO academic_years
                    (tenant_id,academic_year_code,year_name,start_date,end_date,is_current,status)
                    VALUES (:tenant_id,:academic_year_code,:year_name,:start_date,:end_date,:is_current,:status)");
                $statement->execute(['tenant_id' => $scope['tenant_id']] + $data);
                $id = (int)$pdo->lastInsertId();
                ayAudit($pdo, $scope, 'create_academic_year', 'academic_years', $id, [], $data);
                $message = 'Academic year created successfully.';
            }
        } else {
            $status = (string)($data['status'] ?? 'active');
            unset($data['status']);
            $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                throw new RuntimeException('Unable to encode record data.');
            }

            if ($id > 0) {
                $oldStatement = $pdo->prepare("SELECT * FROM academic_year_module_records
                    WHERE id=:id AND tenant_id=:tenant_id AND module_key=:module_key FOR UPDATE");
                $oldStatement->execute(['id' => $id, 'tenant_id' => $scope['tenant_id'], 'module_key' => $moduleKey]);
                $old = $oldStatement->fetch(PDO::FETCH_ASSOC);
                if (!$old) {
                    throw new InvalidArgumentException('Record not found.');
                }

                $statement = $pdo->prepare("UPDATE academic_year_module_records SET
                    record_data=:record_data,status=:status,updated_by=:updated_by
                    WHERE id=:id AND tenant_id=:tenant_id AND module_key=:module_key");
                $statement->execute([
                    'record_data' => $json,
                    'status' => $status,
                    'updated_by' => $scope['user_id'],
                    'id' => $id,
                    'tenant_id' => $scope['tenant_id'],
                    'module_key' => $moduleKey,
                ]);
                ayAudit($pdo, $scope, 'update_' . $moduleKey, 'academic_year_module_records', $id, $old ?: [], $data + ['status' => $status]);
                $message = 'Record updated successfully.';
            } else {
                $statement = $pdo->prepare("INSERT INTO academic_year_module_records
                    (tenant_id,module_key,record_data,status,created_by,updated_by)
                    VALUES (:tenant_id,:module_key,:record_data,:status,:created_by,:updated_by)");
                $statement->execute([
                    'tenant_id' => $scope['tenant_id'],
                    'module_key' => $moduleKey,
                    'record_data' => $json,
                    'status' => $status,
                    'created_by' => $scope['user_id'],
                    'updated_by' => $scope['user_id'],
                ]);
                $id = (int)$pdo->lastInsertId();
                ayAudit($pdo, $scope, 'create_' . $moduleKey, 'academic_year_module_records', $id, [], $data + ['status' => $status]);
                $message = 'Record created successfully.';
            }
        }

        $pdo->commit();
        ayJson(true, $message, ['id' => $id, 'csrf_token' => ayCsrfToken()]);
    }

    if ($action === 'archive') {
        if ($moduleKey !== 'academic_years') {
            ayJson(false, 'Archive is only available for academic years.', [], 400);
        }
        if (!ayCan($moduleKey, 'edit')) {
            ayJson(false, 'You do not have permission to archive academic years.', [], 403);
        }

        ayCsrf($input);
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid academic year record.');
        }

        $statement = $pdo->prepare("UPDATE academic_years SET status='closed',is_current=0
            WHERE id=:id AND tenant_id=:tenant_id");
        $statement->execute(['id' => $id, 'tenant_id' => $scope['tenant_id']]);
        if ($statement->rowCount() === 0) {
            throw new InvalidArgumentException('Academic year record not found.');
        }
        ayAudit($pdo, $scope, 'archive_academic_year', 'academic_years', $id);
        ayJson(true, 'Academic year archived successfully.', ['csrf_token' => ayCsrfToken()]);
    }

    if ($action === 'delete') {
        if (!ayCan($moduleKey, 'delete')) {
            ayJson(false, 'You do not have permission to delete this record.', [], 403);
        }

        ayCsrf($input);
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid record ID.');
        }

        if ($moduleKey === 'academic_years') {
            $check = $pdo->prepare("SELECT is_current FROM academic_years WHERE id=:id AND tenant_id=:tenant_id");
            $check->execute(['id' => $id, 'tenant_id' => $scope['tenant_id']]);
            $isCurrent = $check->fetchColumn();
            if ($isCurrent === false) {
                throw new InvalidArgumentException('Academic year record not found.');
            }
            if ((int)$isCurrent === 1) {
                throw new InvalidArgumentException('The current academic year cannot be deleted. Archive it after selecting another current year.');
            }

            try {
                $statement = $pdo->prepare("DELETE FROM academic_years WHERE id=:id AND tenant_id=:tenant_id");
                $statement->execute(['id' => $id, 'tenant_id' => $scope['tenant_id']]);
            } catch (PDOException $exception) {
                if ((string)$exception->getCode() === '23000') {
                    throw new InvalidArgumentException('This academic year is already used by other records and cannot be deleted. Archive it instead.');
                }
                throw $exception;
            }
            ayAudit($pdo, $scope, 'delete_academic_year', 'academic_years', $id);
        } else {
            $statement = $pdo->prepare("DELETE FROM academic_year_module_records
                WHERE id=:id AND tenant_id=:tenant_id AND module_key=:module_key");
            $statement->execute(['id' => $id, 'tenant_id' => $scope['tenant_id'], 'module_key' => $moduleKey]);
            if ($statement->rowCount() === 0) {
                throw new InvalidArgumentException('Record not found.');
            }
            ayAudit($pdo, $scope, 'delete_' . $moduleKey, 'academic_year_module_records', $id);
        }

        ayJson(true, 'Record deleted successfully.', ['csrf_token' => ayCsrfToken()]);
    }

    ayJson(false, 'Invalid Academic Year action.', [
        'module_key' => $moduleKey,
        'action' => $action,
        'allowed_actions' => ['list', 'create', 'update', 'archive', 'delete'],
    ], 400);
} catch (InvalidArgumentException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    ayJson(false, $exception->getMessage(), [], 422);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('academic-years.php: ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $message = (str_contains($host, 'localhost') || str_contains($host, '127.0.0.1'))
        ? 'Academic Year request failed: ' . $exception->getMessage()
        : 'Unable to complete the Academic Year request.';
    ayJson(false, $message, [], 500);
}

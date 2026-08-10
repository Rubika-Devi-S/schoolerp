<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

if (!defined('SCHOOL_API_PAGE_KEY')) { define('SCHOOL_API_PAGE_KEY', 'general_settings'); }
if (!defined('SCHOOL_API_PERMISSION_KEYS')) { define('SCHOOL_API_PERMISSION_KEYS', ['general_settings']); }

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/general-settings-runtime.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

const GENERAL_SETTINGS_BUILD =
    '2026-08-07-general-settings-shifts-v3';

function gsOut(
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
        header(
            'Content-Type: application/json; charset=utf-8'
        );
        header(
            'Cache-Control: no-store, no-cache, must-revalidate'
        );
    }

    echo json_encode(
        array(
            'success' => $success,
            'message' => $message,
            'data' => $data
        ),
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    );

    exit;
}

function gsInput(): array
{
    $contentType = strtolower(
        (string)($_SERVER['CONTENT_TYPE'] ?? '')
    );

    if (
        strpos(
            $contentType,
            'application/json'
        ) !== false
    ) {
        $decoded = json_decode(
            (string)file_get_contents('php://input'),
            true
        );

        return is_array($decoded)
            ? $decoded
            : array();
    }

    return $_POST;
}

function gsScope(): array
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
        'user_id' => (int)(
            $user['id']
            ?? $user['user_id']
            ?? $_SESSION['user_id']
            ?? 0
        )
    );
}

function gsCsrf(array $input): void
{
    $sessionToken = (string)(
        $_SESSION['general_settings_csrf'] ?? ''
    );

    $requestToken = (string)(
        $input['csrf_token'] ?? ''
    );

    if (
        $sessionToken === ''
        || $requestToken === ''
        || !hash_equals(
            $sessionToken,
            $requestToken
        )
    ) {
        gsOut(
            false,
            'Invalid or expired CSRF token. Refresh the page.',
            array(
                'build' => GENERAL_SETTINGS_BUILD
            ),
            419
        );
    }
}

function gsTableExists(
    PDO $pdo,
    string $table
): bool {
    $statement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = ?'
    );

    $statement->execute(array($table));

    return (int)$statement->fetchColumn() > 0;
}

function gsColumnExists(
    PDO $pdo,
    string $table,
    string $column
): bool {
    $statement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = ?
           AND column_name = ?'
    );

    $statement->execute(array(
        $table,
        $column
    ));

    return (int)$statement->fetchColumn() > 0;
}

function gsEnsureSchema(PDO $pdo): void
{
    school_settings_ensure_schema($pdo);
}

function gsAcademicYears(
    PDO $pdo,
    int $tenantId
): array {
    $candidateTables = array(
        'academic_years',
        'school_academic_years'
    );

    foreach ($candidateTables as $table) {
        if (!gsTableExists($pdo, $table)) {
            continue;
        }

        $nameColumn = null;

        foreach (
            array(
                'year_name',
                'academic_year',
                'name',
                'title'
            )
            as $column
        ) {
            if (
                gsColumnExists(
                    $pdo,
                    $table,
                    $column
                )
            ) {
                $nameColumn = $column;
                break;
            }
        }

        if ($nameColumn === null) {
            continue;
        }

        $where = array();
        $params = array();

        if (
            gsColumnExists(
                $pdo,
                $table,
                'tenant_id'
            )
        ) {
            $where[] = 'tenant_id = ?';
            $params[] = $tenantId;
        }

        if (
            gsColumnExists(
                $pdo,
                $table,
                'status'
            )
        ) {
            $where[] =
                "LOWER(COALESCE(status, 'active')) " .
                "IN ('active','current')";
        }

        $sql =
            "SELECT
                id,
                `$nameColumn` AS year_name
             FROM `$table`";

        if ($where) {
            $sql .=
                ' WHERE ' .
                implode(' AND ', $where);
        }

        $sql .= ' ORDER BY id DESC';

        $statement = $pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll(
            PDO::FETCH_ASSOC
        );
    }

    return array();
}

function gsDefaultSettings(): array
{
    return school_settings_defaults();
}

function gsSettings(PDO $pdo, int $tenantId): array
{
    return school_settings_get($pdo, $tenantId);
}

function gsTime(
    mixed $value,
    string $label
): string {
    $value = trim((string)$value);

    if (
        !preg_match(
            '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
            $value
        )
    ) {
        throw new InvalidArgumentException(
            $label . ' is invalid.'
        );
    }

    return $value . ':00';
}

function gsPrefix(
    mixed $value,
    string $label
): string {
    $value = strtoupper(
        trim((string)$value)
    );

    if ($value === '') {
        throw new InvalidArgumentException(
            $label . ' is required.'
        );
    }

    if (strlen($value) > 30) {
        throw new InvalidArgumentException(
            $label .
            ' cannot exceed 30 characters.'
        );
    }

    if (
        !preg_match(
            '/^[A-Z0-9_-]+$/',
            $value
        )
    ) {
        throw new InvalidArgumentException(
            $label .
            ' can contain only letters, numbers, hyphen and underscore.'
        );
    }

    return $value;
}

function gsValidate(
    PDO $pdo,
    int $tenantId,
    array $input
): array {
    $academicYearId = (int)(
        $input['academic_year_id'] ?? 0
    );

    if ($academicYearId <= 0) {
        throw new InvalidArgumentException(
            'Academic Year is required.'
        );
    }

    $academicYears = gsAcademicYears(
        $pdo,
        $tenantId
    );

    if ($academicYears) {
        $validAcademicYear = false;

        foreach ($academicYears as $year) {
            if (
                (int)($year['id'] ?? 0)
                === $academicYearId
            ) {
                $validAcademicYear = true;
                break;
            }
        }

        if (!$validAcademicYear) {
            throw new InvalidArgumentException(
                'Selected Academic Year is invalid.'
            );
        }
    }

    $startTime = gsTime(
        $input['school_start_time'] ?? '',
        'School Start Time'
    );

    $endTime = gsTime(
        $input['school_end_time'] ?? '',
        'School End Time'
    );

    if ($startTime >= $endTime) {
        throw new InvalidArgumentException(
            'School End Time must be later than School Start Time.'
        );
    }

    $dateFormat = trim(
        (string)(
            $input['date_format'] ?? 'd-m-Y'
        )
    );

    if (
        !in_array(
            $dateFormat,
            array(
                'd-m-Y',
                'd/m/Y',
                'Y-m-d',
                'm/d/Y'
            ),
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Date Format is invalid.'
        );
    }

    $timeFormat = trim(
        (string)(
            $input['time_format'] ?? '12'
        )
    );

    if (
        !in_array(
            $timeFormat,
            array('12', '24'),
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Time Format is invalid.'
        );
    }

    $languageCode = strtolower(
        trim(
            (string)(
                $input['language_code'] ?? 'en'
            )
        )
    );

    if (
        !in_array(
            $languageCode,
            array('en', 'ta', 'hi'),
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Language is invalid.'
        );
    }

    $currencyCode = strtoupper(
        trim(
            (string)(
                $input['currency_code'] ?? 'INR'
            )
        )
    );

    if ($currencyCode !== 'INR') {
        throw new InvalidArgumentException(
            'Currency must be INR.'
        );
    }

    return array(
        'academic_year_id' => $academicYearId,
        'school_start_time' => $startTime,
        'school_end_time' => $endTime,
        'date_format' => $dateFormat,
        'time_format' => $timeFormat,
        'language_code' => $languageCode,
        'currency_code' => $currencyCode,
        'admission_number_prefix' =>
            gsPrefix(
                $input['admission_number_prefix']
                ?? '',
                'Admission Number Prefix'
            ),
        'receipt_number_prefix' =>
            gsPrefix(
                $input['receipt_number_prefix']
                ?? '',
                'Receipt Number Prefix'
            ),
        'maintenance_mode' =>
            (int)(
                $input['maintenance_mode'] ?? 0
            ) === 1
                ? 1
                : 0
    );
}

function gsValidateShifts(
    array $input,
    string $schoolStartTime,
    string $schoolEndTime
): array {
    $definitions = school_settings_shift_definitions();
    $raw = $input['shift_settings'] ?? [];
    if (!is_array($raw)) {
        throw new InvalidArgumentException('Shift Time Configuration is invalid.');
    }

    $byKey = [];
    foreach ($raw as $row) {
        if (!is_array($row)) continue;
        $key = strtolower(trim((string)($row['shift_key'] ?? '')));
        if (isset($definitions[$key])) $byKey[$key] = $row;
    }

    $result = [];
    $enabledCount = 0;

    foreach ($definitions as $key => $definition) {
        $row = $byKey[$key] ?? [];
        $enabled = (int)($row['is_enabled'] ?? ($key === 'general' ? 1 : 0)) === 1;
        $start = trim((string)($row['start_time'] ?? ''));
        $end = trim((string)($row['end_time'] ?? ''));

        if ($key === 'general') {
            if ($start === '') $start = substr($schoolStartTime, 0, 5);
            if ($end === '') $end = substr($schoolEndTime, 0, 5);
        }

        if ($enabled) {
            $enabledCount++;
            $start = gsTime($start, $definition['shift_name'] . ' Shift Start Time');
            $end = gsTime($end, $definition['shift_name'] . ' Shift End Time');

            if ($start >= $end) {
                throw new InvalidArgumentException(
                    $definition['shift_name'] . ' Shift End Time must be later than Start Time.'
                );
            }
        } else {
            $start = $start !== '' ? gsTime($start, $definition['shift_name'] . ' Shift Start Time') : '';
            $end = $end !== '' ? gsTime($end, $definition['shift_name'] . ' Shift End Time') : '';
        }

        $result[] = [
            'shift_key' => $key,
            'shift_name' => $definition['shift_name'],
            'start_time' => $start,
            'end_time' => $end,
            'is_enabled' => $enabled ? 1 : 0,
            'display_order' => $definition['display_order'],
        ];
    }

    if ($enabledCount <= 0) {
        throw new InvalidArgumentException('Enable at least one School Shift.');
    }

    return $result;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    gsOut(
        false,
        'Database connection is missing.',
        array(
            'build' => GENERAL_SETTINGS_BUILD
        ),
        500
    );
}

$scope = gsScope();

if (
    $scope['tenant_id'] <= 0
    || $scope['user_id'] <= 0
) {
    gsOut(
        false,
        'Tenant or user session is missing.',
        array(
            'build' => GENERAL_SETTINGS_BUILD
        ),
        401
    );
}

if (
    empty($_SESSION['general_settings_csrf'])
    || !is_string(
        $_SESSION['general_settings_csrf']
    )
) {
    $_SESSION['general_settings_csrf'] =
        bin2hex(random_bytes(32));
}

$input = gsInput();

$action = strtolower(
    trim(
        (string)(
            $input['action']
            ?? $_GET['action']
            ?? 'get'
        )
    )
);

try {
    gsEnsureSchema($pdo);

    if ($action === 'get') {
        gsOut(
            true,
            'General Settings loaded.',
            array(
                'settings' => gsSettings(
                    $pdo,
                    $scope['tenant_id']
                ),
                'academic_years' =>
                    gsAcademicYears(
                        $pdo,
                        $scope['tenant_id']
                    ),
                'shift_settings' => school_settings_shift_settings(
                    $pdo,
                    $scope['tenant_id']
                ),
                'numbering_preview' => [
                    'next_admission_number' => school_settings_peek_number($pdo, $scope['tenant_id'], 'admission'),
                    'next_receipt_number' => school_settings_peek_number($pdo, $scope['tenant_id'], 'receipt'),
                ],
                'csrf_token' =>
                    $_SESSION[
                        'general_settings_csrf'
                    ],
                'build' =>
                    GENERAL_SETTINGS_BUILD
            )
        );
    }

    if ($action === 'save') {
        gsCsrf($input);

        $data = gsValidate(
            $pdo,
            $scope['tenant_id'],
            $input
        );

        $shiftSettings = gsValidateShifts(
            $input,
            $data['school_start_time'],
            $data['school_end_time']
        );

        $pdo->beginTransaction();

        /*
         * One row per tenant.
         *
         * The UNIQUE tenant_id key prevents duplicate records and the
         * prepared ON DUPLICATE KEY UPDATE statement updates the existing
         * settings row safely.
         */
        $statement = $pdo->prepare(
            "INSERT INTO school_general_settings (
                tenant_id,
                academic_year_id,
                school_start_time,
                school_end_time,
                date_format,
                time_format,
                language_code,
                currency_code,
                admission_number_prefix,
                receipt_number_prefix,
                maintenance_mode,
                created_by,
                updated_by
             ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
             )
             ON DUPLICATE KEY UPDATE
                academic_year_id =
                    VALUES(academic_year_id),
                school_start_time =
                    VALUES(school_start_time),
                school_end_time =
                    VALUES(school_end_time),
                date_format =
                    VALUES(date_format),
                time_format =
                    VALUES(time_format),
                language_code =
                    VALUES(language_code),
                currency_code =
                    VALUES(currency_code),
                admission_number_prefix =
                    VALUES(admission_number_prefix),
                receipt_number_prefix =
                    VALUES(receipt_number_prefix),
                maintenance_mode =
                    VALUES(maintenance_mode),
                updated_by =
                    VALUES(updated_by)"
        );

        $statement->execute(array(
            $scope['tenant_id'],
            $data['academic_year_id'],
            $data['school_start_time'],
            $data['school_end_time'],
            $data['date_format'],
            $data['time_format'],
            $data['language_code'],
            $data['currency_code'],
            $data['admission_number_prefix'],
            $data['receipt_number_prefix'],
            $data['maintenance_mode'],
            $scope['user_id'],
            $scope['user_id']
        ));


        school_settings_save_shifts(
            $pdo,
            $scope['tenant_id'],
            $shiftSettings,
            $scope['user_id']
        );

        $pdo->commit();

        gsOut(
            true,
            'General Settings saved successfully.',
            array(
                'settings' => gsSettings(
                    $pdo,
                    $scope['tenant_id']
                ),
                'academic_years' =>
                    gsAcademicYears(
                        $pdo,
                        $scope['tenant_id']
                    ),
                'shift_settings' => school_settings_shift_settings(
                    $pdo,
                    $scope['tenant_id']
                ),
                'numbering_preview' => [
                    'next_admission_number' => school_settings_peek_number($pdo, $scope['tenant_id'], 'admission'),
                    'next_receipt_number' => school_settings_peek_number($pdo, $scope['tenant_id'], 'receipt'),
                ],
                'csrf_token' =>
                    $_SESSION[
                        'general_settings_csrf'
                    ],
                'build' =>
                    GENERAL_SETTINGS_BUILD
            )
        );
    }

    gsOut(
        false,
        'General Settings action is invalid.',
        array(
            'build' => GENERAL_SETTINGS_BUILD
        ),
        400
    );
} catch (InvalidArgumentException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    gsOut(
        false,
        $exception->getMessage(),
        array(
            'build' => GENERAL_SETTINGS_BUILD
        ),
        422
    );
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    $host = strtolower(
        (string)(
            $_SERVER['HTTP_HOST'] ?? ''
        )
    );

    $local =
        strpos($host, 'localhost') !== false
        || strpos(
            $host,
            '127.0.0.1'
        ) !== false;

    error_log(
        'General Settings [' .
        GENERAL_SETTINGS_BUILD .
        ']: ' .
        $exception->getMessage()
    );

    gsOut(
        false,
        $local
            ? 'Database operation failed [' .
                GENERAL_SETTINGS_BUILD .
                ']: ' .
                $exception->getMessage()
            : 'Database operation failed.',
        array(
            'build' => GENERAL_SETTINGS_BUILD
        ),
        500
    );
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log(
        'General Settings [' .
        GENERAL_SETTINGS_BUILD .
        ']: ' .
        $exception->getMessage()
    );

    gsOut(
        false,
        'General Settings request failed [' .
        GENERAL_SETTINGS_BUILD .
        ']: ' .
        $exception->getMessage(),
        array(
            'build' => GENERAL_SETTINGS_BUILD
        ),
        500
    );
}

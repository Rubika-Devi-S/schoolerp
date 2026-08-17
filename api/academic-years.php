<?php
declare(strict_types=1);

/* Build: 2026-08-15-academic-year-branch-isolation-v8 */

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

/*
 * The Academic Year suite has custom actions such as export, import and
 * template. Load bootstrap for authentication/session, then enforce all
 * module/action permissions inside this API so custom actions are not
 * incorrectly mapped by a shared generic API guard.
 */
$originalScriptName =
    (string)($_SERVER['SCRIPT_NAME'] ?? '');

$_SERVER['SCRIPT_NAME'] = '/login.php';

require_once dirname(__DIR__)
    . '/includes/bootstrap.php';

$_SERVER['SCRIPT_NAME'] =
    $originalScriptName;

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

function ayScope(PDO $pdo): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $user = function_exists('current_user') ? current_user() : [];
    $user = is_array($user) ? $user : [];

    $tenantId = (int)(
        $user['tenant_id']
        ?? $user['school_id']
        ?? $_SESSION['tenant_id']
        ?? $_SESSION['school_id']
        ?? ($_SESSION['tenant']['id'] ?? 0)
    );

    $userId = (int)(
        $user['id']
        ?? $user['user_id']
        ?? $_SESSION['user_id']
        ?? 0
    );

    $branchId = (int)(
        $user['branch_id']
        ?? $user['default_branch_id']
        ?? $_SESSION['branch_id']
        ?? $_SESSION['default_branch_id']
        ?? 0
    );

    $roleId = (int)(
        $user['role_id']
        ?? $_SESSION['role_id']
        ?? 0
    );

    if ($tenantId <= 0 || $userId <= 0) {
        throw new RuntimeException(
            'Active School Admin login required.',
            401
        );
    }

    /* Older sessions may have School ID but no active branch. */
    if ($branchId <= 0 && ayTableExists($pdo, 'users')) {
        try {
            $statement = $pdo->prepare(
                "SELECT default_branch_id
                 FROM users
                 WHERE id=:user_id
                   AND tenant_id=:tenant_id
                   AND deleted_at IS NULL
                 LIMIT 1"
            );
            $statement->execute([
                'user_id' => $userId,
                'tenant_id' => $tenantId,
            ]);
            $branchId = (int)$statement->fetchColumn();
        } catch (Throwable) {
            /* Compatibility with users tables that do not have deleted_at. */
            $statement = $pdo->prepare(
                "SELECT default_branch_id
                 FROM users
                 WHERE id=:user_id
                   AND tenant_id=:tenant_id
                 LIMIT 1"
            );
            $statement->execute([
                'user_id' => $userId,
                'tenant_id' => $tenantId,
            ]);
            $branchId = (int)$statement->fetchColumn();
        }
    }

    /* School-level admins without a default branch use that school's main branch. */
    if ($branchId <= 0 && ayTableExists($pdo, 'branches')) {
        $statement = $pdo->prepare(
            "SELECT id
             FROM branches
             WHERE tenant_id=:tenant_id
               AND status='active'
             ORDER BY is_main DESC,id ASC
             LIMIT 1"
        );
        $statement->execute(['tenant_id' => $tenantId]);
        $branchId = (int)$statement->fetchColumn();
    }

    if ($branchId <= 0) {
        throw new RuntimeException(
            'Active School and Branch context is required. Assign an active branch to this School Admin.',
            422
        );
    }

    if (ayTableExists($pdo, 'branches')) {
        $statement = $pdo->prepare(
            "SELECT branch_name
             FROM branches
             WHERE id=:branch_id
               AND tenant_id=:tenant_id
               AND status='active'
             LIMIT 1"
        );
        $statement->execute([
            'branch_id' => $branchId,
            'tenant_id' => $tenantId,
        ]);
        $branchName = (string)($statement->fetchColumn() ?: '');

        if ($branchName === '') {
            throw new RuntimeException(
                'The active Branch does not belong to this School.',
                403
            );
        }
    } else {
        $branchName = 'Branch #' . $branchId;
    }

    /* DB triggers use these connection variables for branch isolation. */
    $statement = $pdo->prepare(
        "SET @schoolerp_tenant_id=:tenant_id,
             @schoolerp_branch_id=:branch_id"
    );
    $statement->execute([
        'tenant_id' => $tenantId,
        'branch_id' => $branchId,
    ]);

    return [
        'tenant_id' => $tenantId,
        'branch_id' => $branchId,
        'branch_name' => $branchName,
        'user_id' => $userId,
        'role_id' => $roleId,
        'role_name' => (string)(
            $user['role_name']
            ?? $user['role']
            ?? $_SESSION['role_name']
            ?? $_SESSION['role']
            ?? ''
        ),
    ];
}

function ayNormalizeAction(
    string $action
): string {
    $action = strtolower(
        trim($action)
    );

    return match ($action) {
        'add', 'create', 'store', 'insert' => 'create',
        'edit', 'update', 'archive', 'status' => 'edit',
        'delete', 'remove', 'destroy' => 'delete',
        'export', 'download' => 'export',
        'import', 'upload', 'template' => 'import',
        default => 'view',
    };
}

function ayFullAccess(): bool
{
    if (function_exists('is_super_admin')) {
        try {
            if ((bool)is_super_admin()) {
                return true;
            }
        } catch (Throwable) {
        }
    }

    $user = function_exists('current_user')
        ? current_user()
        : [];

    $roleText = strtolower(trim((string)(
        $user['role_key']
        ?? $user['role_name']
        ?? $user['role']
        ?? $user['user_type']
        ?? $_SESSION['role_key']
        ?? $_SESSION['role_name']
        ?? $_SESSION['role']
        ?? ''
    )));

    $roleKey = preg_replace(
        '/[^a-z0-9]+/',
        '_',
        $roleText
    ) ?? '';

    return in_array(
        trim($roleKey, '_'),
        [
            'platform_owner',
            'platformowner',
            'platform_admin',
            'platformadministrator',
            'super_admin',
            'superadministrator',
        ],
        true
    );
}

function ayCan(
    string $moduleKey,
    string $action
): bool {
    $moduleKey = strtolower(
        trim($moduleKey)
    );

    $permissionAction =
        ayNormalizeAction($action);

    if (ayFullAccess()) {
        return true;
    }

    try {
        if (
            function_exists(
                'school_sidebar_permission_decision'
            )
        ) {
            $decision =
                school_sidebar_permission_decision(
                    $moduleKey,
                    $permissionAction
                );

            if ($decision !== null) {
                return (bool)$decision;
            }
        }

        if (
            function_exists(
                'school_effective_permission'
            )
        ) {
            if (
                school_effective_permission(
                    $moduleKey,
                    $permissionAction
                )
            ) {
                return true;
            }

            return (bool)school_effective_permission(
                'academic_year',
                $permissionAction
            );
        }
    } catch (Throwable $exception) {
        error_log(
            'Academic Year permission check: '
            . $exception->getMessage()
        );
    }

    return false;
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

function ayColumnExists(PDO $pdo, string $table, string $column): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema=DATABASE()
           AND table_name=:table_name
           AND column_name=:column_name'
    );
    $statement->execute([
        'table_name' => $table,
        'column_name' => $column,
    ]);
    return (int)$statement->fetchColumn() > 0;
}

function ayIndexExists(PDO $pdo, string $table, string $index): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.statistics
         WHERE table_schema=DATABASE()
           AND table_name=:table_name
           AND index_name=:index_name'
    );
    $statement->execute([
        'table_name' => $table,
        'index_name' => $index,
    ]);
    return (int)$statement->fetchColumn() > 0;
}


function ayAcademicYears(
    PDO $pdo,
    int $tenantId,
    int $branchId
): array {
    $statement = $pdo->prepare(
        "SELECT
            id,
            branch_id,
            academic_year_code,
            year_name,
            start_date,
            end_date,
            is_current,
            status
         FROM academic_years
         WHERE tenant_id=:tenant_id
           AND branch_id=:branch_id
         ORDER BY
            is_current DESC,
            start_date DESC,
            id DESC"
    );

    $statement->execute([
        'tenant_id' => $tenantId,
        'branch_id' => $branchId,
    ]);

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function ayCurrentAcademicYearId(
    PDO $pdo,
    int $tenantId,
    int $branchId
): int {
    foreach (ayAcademicYears($pdo, $tenantId, $branchId) as $year) {
        if ((int)($year['is_current'] ?? 0) === 1) {
            return (int)$year['id'];
        }
    }

    $years = ayAcademicYears($pdo, $tenantId, $branchId);
    return !empty($years) ? (int)$years[0]['id'] : 0;
}

function ayAcademicYearExists(
    PDO $pdo,
    int $tenantId,
    int $branchId,
    int $academicYearId
): bool {
    if ($academicYearId <= 0) {
        return false;
    }

    $statement = $pdo->prepare(
        "SELECT COUNT(*)
         FROM academic_years
         WHERE id=:id
           AND tenant_id=:tenant_id
           AND branch_id=:branch_id"
    );

    $statement->execute([
        'id' => $academicYearId,
        'tenant_id' => $tenantId,
        'branch_id' => $branchId,
    ]);

    return (int)$statement->fetchColumn() > 0;
}

function aySelectedAcademicYearId(
    PDO $pdo,
    array $scope,
    array $input
): int {
    $selected = (int)(
        $input['academic_year_id']
        ?? $_GET['academic_year_id']
        ?? 0
    );

    if ($selected <= 0) {
        $selected = ayCurrentAcademicYearId(
            $pdo,
            (int)$scope['tenant_id'],
            (int)$scope['branch_id']
        );
    }

    if (
        $selected > 0
        && !ayAcademicYearExists(
            $pdo,
            (int)$scope['tenant_id'],
            (int)$scope['branch_id'],
            $selected
        )
    ) {
        throw new InvalidArgumentException(
            'Selected Academic Year was not found for the active Branch.'
        );
    }

    return $selected;
}

function ayCsvDownload(
    string $filename,
    array $headers,
    array $rows
): never {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (!headers_sent()) {
        header(
            'Content-Type: text/csv; charset=UTF-8'
        );

        header(
            'Content-Disposition: attachment; filename="'
            . str_replace(
                ['"', "\r", "\n"],
                '',
                $filename
            )
            . '"'
        );

        header(
            'Cache-Control: no-store'
        );
    }

    $output = fopen(
        'php://output',
        'wb'
    );

    if ($output === false) {
        exit;
    }

    fwrite(
        $output,
        "\xEF\xBB\xBF"
    );

    fputcsv(
        $output,
        $headers
    );

    foreach ($rows as $row) {
        fputcsv(
            $output,
            $row
        );
    }

    fclose($output);
    exit;
}

function ayNormalizeHeader(
    string $header
): string {
    $header = strtolower(
        trim($header)
    );

    $header = preg_replace(
        '/[^a-z0-9]+/',
        '_',
        $header
    ) ?? '';

    return trim(
        $header,
        '_'
    );
}

function ayImportHeaders(
    string $moduleKey
): array {
    return match ($moduleKey) {
        'academic_years' => [
            'year_name',
            'start_date',
            'end_date',
            'is_current',
            'status',
        ],
        'academic_calendar' => [
            'event_name',
            'event_date',
            'event_type',
            'description',
            'status',
        ],
        'terms_semesters' => [
            'term_name',
            'start_date',
            'end_date',
            'description',
            'status',
        ],
        'holidays' => [
            'holiday_name',
            'holiday_date',
            'holiday_type',
            'description',
            'status',
        ],
        default => [],
    };
}

function aySpreadsheetRows(
    array $file
): array {
    $tmpName = (string)(
        $file['tmp_name']
        ?? ''
    );

    $originalName = (string)(
        $file['name']
        ?? ''
    );

    if (
        $tmpName === ''
        || !is_uploaded_file($tmpName)
    ) {
        throw new InvalidArgumentException(
            'Choose a valid import file.'
        );
    }

    if (
        (int)($file['size'] ?? 0)
        > 10 * 1024 * 1024
    ) {
        throw new InvalidArgumentException(
            'Import file must be 10 MB or smaller.'
        );
    }

    $extension = strtolower(
        pathinfo(
            $originalName,
            PATHINFO_EXTENSION
        )
    );

    if ($extension === 'csv') {
        $handle = fopen(
            $tmpName,
            'rb'
        );

        if ($handle === false) {
            throw new RuntimeException(
                'Unable to open CSV file.'
            );
        }

        $rows = [];

        while (
            ($row = fgetcsv($handle))
            !== false
        ) {
            $rows[] = array_map(
                static fn($value): string =>
                    trim((string)$value),
                $row
            );

            if (count($rows) > 2001) {
                fclose($handle);

                throw new InvalidArgumentException(
                    'Import supports a maximum of 2,000 data rows.'
                );
            }
        }

        fclose($handle);
        return $rows;
    }

    if ($extension !== 'xlsx') {
        throw new InvalidArgumentException(
            'Only CSV and XLSX files are supported.'
        );
    }

    if (!class_exists('ZipArchive')) {
        throw new RuntimeException(
            'XLSX import requires the PHP Zip extension. Use CSV or enable ZipArchive.'
        );
    }

    $zip = new ZipArchive();

    if ($zip->open($tmpName) !== true) {
        throw new InvalidArgumentException(
            'Unable to open the XLSX file.'
        );
    }

    $sharedStrings = [];

    $sharedXml =
        $zip->getFromName(
            'xl/sharedStrings.xml'
        );

    if ($sharedXml !== false) {
        $xml =
            simplexml_load_string(
                $sharedXml
            );

        if ($xml !== false) {
            $namespaces =
                $xml->getNamespaces(true);

            $main =
                isset($namespaces[''])
                    ? $xml->children(
                        $namespaces['']
                    )
                    : $xml;

            foreach ($main->si as $si) {
                $parts = [];

                if (isset($si->t)) {
                    $parts[] =
                        (string)$si->t;
                }

                if (isset($si->r)) {
                    foreach ($si->r as $run) {
                        $parts[] =
                            (string)$run->t;
                    }
                }

                $sharedStrings[] =
                    implode('', $parts);
            }
        }
    }

    $sheetXml =
        $zip->getFromName(
            'xl/worksheets/sheet1.xml'
        );

    if ($sheetXml === false) {
        $zip->close();

        throw new InvalidArgumentException(
            'The XLSX file does not contain Sheet1.'
        );
    }

    $sheet =
        simplexml_load_string(
            $sheetXml
        );

    if ($sheet === false) {
        $zip->close();

        throw new InvalidArgumentException(
            'Unable to read the XLSX worksheet.'
        );
    }

    $sheetNamespaces =
        $sheet->getNamespaces(true);

    $sheetMain =
        isset($sheetNamespaces[''])
            ? $sheet->children(
                $sheetNamespaces['']
            )
            : $sheet;

    $rows = [];

    foreach (
        $sheetMain->sheetData->row
        as $row
    ) {
        $values = [];
        $lastColumn = 0;

        foreach ($row->c as $cell) {
            $reference =
                (string)$cell['r'];

            preg_match(
                '/([A-Z]+)/',
                $reference,
                $matches
            );

            $letters =
                $matches[1]
                ?? 'A';

            $column = 0;

            foreach (
                str_split($letters)
                as $letter
            ) {
                $column =
                    $column * 26
                    + ord($letter)
                    - 64;
            }

            while (
                $lastColumn + 1
                < $column
            ) {
                $values[] = '';
                $lastColumn++;
            }

            $type =
                (string)$cell['t'];

            $value = '';

            if ($type === 's') {
                $index =
                    (int)$cell->v;

                $value =
                    $sharedStrings[$index]
                    ?? '';
            } elseif (
                $type === 'inlineStr'
            ) {
                $value =
                    (string)$cell
                        ->is->t;
            } else {
                $value =
                    (string)$cell->v;
            }

            $values[] =
                trim($value);

            $lastColumn =
                $column;
        }

        $rows[] = $values;

        if (count($rows) > 2001) {
            $zip->close();

            throw new InvalidArgumentException(
                'Import supports a maximum of 2,000 data rows.'
            );
        }
    }

    $zip->close();
    return $rows;
}

function ayNormalizeImportValue(
    string $header,
    string $value
): string {
    $value = trim($value);

    if (
        str_ends_with(
            $header,
            '_date'
        )
        && $value !== ''
        && is_numeric($value)
    ) {
        $serial = (float)$value;

        if ($serial > 0) {
            $timestamp =
                (int)round(
                    ($serial - 25569)
                    * 86400
                );

            if ($timestamp > 0) {
                return gmdate(
                    'Y-m-d',
                    $timestamp
                );
            }
        }
    }

    if ($header === 'is_current') {
        $normalized = strtolower(
            $value
        );

        if (
            in_array(
                $normalized,
                ['yes', 'true', 'current'],
                true
            )
        ) {
            return '1';
        }

        if (
            in_array(
                $normalized,
                ['no', 'false'],
                true
            )
        ) {
            return '0';
        }
    }

    return $value;
}

function ayRowsToAssociative(
    array $rows
): array {
    if (empty($rows)) {
        throw new InvalidArgumentException(
            'The import file is empty.'
        );
    }

    $headerRow =
        array_shift($rows);

    $headers =
        array_map(
            static fn($value): string =>
                ayNormalizeHeader(
                    (string)$value
                ),
            $headerRow
        );

    $records = [];

    foreach (
        $rows as $rowIndex => $row
    ) {
        $record = [];
        $hasValue = false;

        foreach (
            $headers as $index => $header
        ) {
            if ($header === '') {
                continue;
            }

            $value =
                ayNormalizeImportValue(
                    $header,
                    (string)(
                        $row[$index]
                        ?? ''
                    )
                );

            if ($value !== '') {
                $hasValue = true;
            }

            $record[$header] =
                $value;
        }

        if (!$hasValue) {
            continue;
        }

        $records[] = [
            'row' => $rowIndex + 2,
            'data' => $record,
        ];
    }

    return $records;
}

function ayEnsure(PDO $pdo, array $scope): void
{
    $tenantId = (int)($scope['tenant_id'] ?? 0);
    $branchId = (int)($scope['branch_id'] ?? 0);

    if ($tenantId <= 0 || $branchId <= 0) {
        throw new RuntimeException('Active School and Branch context is required.', 422);
    }

    /*
     * IMPORTANT:
     * The ERP has BEFORE UPDATE triggers that enforce the active School/Branch
     * connection variables. Schema/backfill work can touch historical rows from
     * other branches, so temporarily disable that trigger context only while the
     * one-time migration runs. The current request context is restored in finally.
     *
     * Normal CRUD below always runs with the real current School + Branch.
     */
    $pdo->exec('SET @schoolerp_tenant_id=0, @schoolerp_branch_id=0');

    try {
        $academicYearBranchAdded = false;

        if (!ayColumnExists($pdo, 'academic_years', 'branch_id')) {
            $pdo->exec(
                "ALTER TABLE academic_years
                 ADD COLUMN branch_id BIGINT UNSIGNED NULL AFTER tenant_id"
            );
            $academicYearBranchAdded = true;
        }

        /*
         * Backfill only records that do not yet have branch ownership.
         * Prefer the branch captured by the original activity log; otherwise
         * use the school's active main branch. This block is idempotent.
         */
        if (ayColumnExists($pdo, 'academic_years', 'branch_id')) {
            if (ayTableExists($pdo, 'activity_logs') && ayTableExists($pdo, 'branches')) {
                $pdo->exec(
                    "UPDATE academic_years ay
                     JOIN (
                         SELECT tenant_id,record_id,MAX(branch_id) AS branch_id
                         FROM activity_logs
                         WHERE table_name='academic_years'
                           AND record_id IS NOT NULL
                           AND branch_id IS NOT NULL
                           AND branch_id>0
                         GROUP BY tenant_id,record_id
                     ) logged
                       ON logged.tenant_id=ay.tenant_id
                      AND logged.record_id=ay.id
                     JOIN branches valid_branch
                       ON valid_branch.id=logged.branch_id
                      AND valid_branch.tenant_id=ay.tenant_id
                      AND valid_branch.status='active'
                     SET ay.branch_id=logged.branch_id
                     WHERE ay.branch_id IS NULL OR ay.branch_id=0"
                );
            }

            if (ayTableExists($pdo, 'branches')) {
                $pdo->exec(
                    "UPDATE academic_years ay
                     JOIN branches b
                       ON b.id=(
                           SELECT b2.id
                           FROM branches b2
                           WHERE b2.tenant_id=ay.tenant_id
                             AND b2.status='active'
                           ORDER BY b2.is_main DESC,b2.id ASC
                           LIMIT 1
                       )
                      AND b.tenant_id=ay.tenant_id
                     SET ay.branch_id=b.id
                     WHERE ay.branch_id IS NULL OR ay.branch_id=0"
                );
            }
        }

        /*
         * IMPORTANT INDEX ORDER
         * ---------------------
         * academic_years.tenant_id has the fk_year_tenant foreign key.
         * In older databases MySQL uses uk_academic_year(tenant_id,year_name)
         * as the supporting index for that FK. Dropping it first causes:
         *   SQLSTATE[HY000] 1553 Cannot drop index 'uk_academic_year'
         *
         * Therefore create replacement tenant/branch indexes FIRST, then
         * remove the old school-wide unique index.
         */
        if (!ayIndexExists($pdo, 'academic_years', 'idx_academic_year_tenant_fk')) {
            $pdo->exec(
                "ALTER TABLE academic_years
                 ADD KEY idx_academic_year_tenant_fk (tenant_id)"
            );
        }

        if (!ayIndexExists($pdo, 'academic_years', 'idx_academic_year_branch')) {
            $pdo->exec(
                "ALTER TABLE academic_years
                 ADD KEY idx_academic_year_branch
                 (tenant_id,branch_id,is_current,status)"
            );
        }

        /* Add the new branch-wise uniqueness before removing the legacy one. */
        if (!ayIndexExists($pdo, 'academic_years', 'uq_academic_year_branch')) {
            $pdo->exec(
                "ALTER TABLE academic_years
                 ADD UNIQUE KEY uq_academic_year_branch
                 (tenant_id,branch_id,year_name)"
            );
        }

        /* Safe now: the tenant FK has replacement indexes available. */
        if (ayIndexExists($pdo, 'academic_years', 'uk_academic_year')) {
            $pdo->exec('ALTER TABLE academic_years DROP INDEX uk_academic_year');
        }

        /* Existing installs already have this table. Keep creation backward-safe. */
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS academic_year_module_records (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id BIGINT UNSIGNED NOT NULL,
                branch_id BIGINT UNSIGNED NULL,
                module_key VARCHAR(80) NOT NULL,
                record_data JSON NOT NULL,
                status ENUM('active','inactive') NOT NULL DEFAULT 'active',
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_academic_module (tenant_id,branch_id,module_key,status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $moduleBranchAdded = false;
        if (!ayColumnExists($pdo, 'academic_year_module_records', 'branch_id')) {
            $pdo->exec(
                "ALTER TABLE academic_year_module_records
                 ADD COLUMN branch_id BIGINT UNSIGNED NULL AFTER tenant_id"
            );
            $moduleBranchAdded = true;
        }

        /*
         * DO NOT bulk-update this table on every API request. Its ERP triggers
         * are branch-protected. Backfill is required only when branch_id was
         * newly added to an older installation.
         */
        if ($moduleBranchAdded) {
            if (ayTableExists($pdo, 'activity_logs') && ayTableExists($pdo, 'branches')) {
                $pdo->exec(
                    "UPDATE academic_year_module_records r
                     JOIN (
                         SELECT tenant_id,record_id,MAX(branch_id) AS branch_id
                         FROM activity_logs
                         WHERE table_name='academic_year_module_records'
                           AND record_id IS NOT NULL
                           AND branch_id IS NOT NULL
                           AND branch_id>0
                         GROUP BY tenant_id,record_id
                     ) logged
                       ON logged.tenant_id=r.tenant_id
                      AND logged.record_id=r.id
                     JOIN branches valid_branch
                       ON valid_branch.id=logged.branch_id
                      AND valid_branch.tenant_id=r.tenant_id
                      AND valid_branch.status='active'
                     SET r.branch_id=logged.branch_id
                     WHERE r.branch_id IS NULL OR r.branch_id=0"
                );
            }

            if (ayTableExists($pdo, 'branches')) {
                $pdo->exec(
                    "UPDATE academic_year_module_records r
                     JOIN branches b
                       ON b.id=(
                           SELECT b2.id
                           FROM branches b2
                           WHERE b2.tenant_id=r.tenant_id
                             AND b2.status='active'
                           ORDER BY b2.is_main DESC,b2.id ASC
                           LIMIT 1
                       )
                      AND b.tenant_id=r.tenant_id
                     SET r.branch_id=b.id
                     WHERE r.branch_id IS NULL OR r.branch_id=0"
                );
            }
        }

        if (!ayIndexExists($pdo, 'academic_year_module_records', 'idx_ay_module_branch')) {
            $pdo->exec(
                "ALTER TABLE academic_year_module_records
                 ADD KEY idx_ay_module_branch
                 (tenant_id,branch_id,module_key,status)"
            );
        }
    } finally {
        /* Restore the live request's strict branch context for all normal CRUD. */
        $statement = $pdo->prepare(
            "SET @schoolerp_tenant_id=:tenant_id,
                 @schoolerp_branch_id=:branch_id"
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
        ]);
    }
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
            VALUES (:tenant_id,:branch_id,:user_id,:role_id,'Academic Year Management',:action_key,:table_name,:record_id,:old_values,:new_values,:description,:ip_address,:user_agent)");
        $statement->execute([
            'tenant_id' => $scope['tenant_id'],
            'branch_id' => $scope['branch_id'] ?: null,
            'user_id' => $scope['user_id'] ?: null,
            'role_id' => $scope['role_id'] ?: null,
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

try {
if (!isset($pdo) || !($pdo instanceof PDO)) {
    ayJson(false, 'Database connection unavailable.', [], 500);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$scope = ayScope($pdo);

if (!ayTableExists($pdo, 'academic_years')) {
    ayJson(false, 'Missing database table: academic_years.', [], 500);
}

ayEnsure($pdo, $scope);

$input = ayInput();
$action = strtolower(trim((string)($input['action'] ?? $_GET['action'] ?? '')));
$moduleKey = strtolower(trim((string)($input['module_key'] ?? $_GET['module_key'] ?? '')));
$validModules = ['academic_years', 'academic_calendar', 'terms_semesters', 'holidays'];

    if (!in_array($moduleKey, $validModules, true)) {
        ayJson(false, 'Invalid Academic Year module.', ['module_key' => $moduleKey], 400);
    }

    if ($action === 'list') {
        if (!ayCan($moduleKey, 'view')) {
            ayJson(false, 'You do not have permission to view this section.', [], 403);
        }

        if ($moduleKey === 'academic_years') {
            $statement = $pdo->prepare("SELECT id,branch_id,academic_year_code,year_name,start_date,end_date,is_current,status
                FROM academic_years
                WHERE tenant_id=:tenant_id AND branch_id=:branch_id
                ORDER BY is_current DESC,start_date DESC,id DESC");
            $statement->execute([
                'tenant_id' => $scope['tenant_id'],
                'branch_id' => $scope['branch_id'],
            ]);
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
            $selectedAcademicYearId =
                aySelectedAcademicYearId(
                    $pdo,
                    $scope,
                    $input
                );

            $currentAcademicYearId =
                ayCurrentAcademicYearId(
                    $pdo,
                    (int)$scope['tenant_id'],
                    (int)$scope['branch_id']
                );

            $statement = $pdo->prepare(
                "SELECT
                    id,
                    record_data,
                    status
                 FROM academic_year_module_records
                 WHERE tenant_id=:tenant_id
                   AND branch_id=:branch_id
                   AND module_key=:module_key
                 ORDER BY id DESC"
            );

            $statement->execute([
                'tenant_id' =>
                    $scope['tenant_id'],
                'branch_id' =>
                    $scope['branch_id'],
                'module_key' =>
                    $moduleKey,
            ]);

            $records = [];

            foreach (
                $statement->fetchAll(
                    PDO::FETCH_ASSOC
                ) as $row
            ) {
                $data = json_decode(
                    (string)$row[
                        'record_data'
                    ],
                    true
                );

                $data = is_array($data)
                    ? $data
                    : [];

                /*
                 * Legacy records created before Academic Year context
                 * existed are assigned to the current Academic Year.
                 */
                $recordAcademicYearId =
                    (int)(
                        $data[
                            'academic_year_id'
                        ]
                        ?? $currentAcademicYearId
                    );

                if (
                    $selectedAcademicYearId > 0
                    && $recordAcademicYearId
                        !== $selectedAcademicYearId
                ) {
                    continue;
                }

                $data['academic_year_id'] =
                    $recordAcademicYearId;

                $data['status'] =
                    (string)$row['status'];

                $records[] = [
                    'id' =>
                        (int)$row['id'],
                    'status' =>
                        (string)$row['status'],
                    'data' =>
                        $data,
                ];
            }
        }

        ayJson(
            true,
            'Records loaded successfully.',
            [
                'records' => $records,
                'academic_years' =>
                    ayAcademicYears(
                        $pdo,
                        (int)$scope['tenant_id'],
                        (int)$scope['branch_id']
                    ),
                'selected_academic_year_id' =>
                    $moduleKey === 'academic_years'
                        ? ayCurrentAcademicYearId(
                            $pdo,
                            (int)$scope['tenant_id'],
                            (int)$scope['branch_id']
                        )
                        : (
                            $selectedAcademicYearId
                            ?? 0
                        ),
                'branch' => [
                    'id' => (int)$scope['branch_id'],
                    'name' => (string)$scope['branch_name'],
                ],
                'csrf_token' =>
                    ayCsrfToken(),
            ]
        );
    }

    if ($action === 'export') {
        if (!ayCan($moduleKey, 'export')) {
            ayJson(
                false,
                'You do not have permission to export this section.',
                [],
                403
            );
        }

        $headers =
            ayImportHeaders(
                $moduleKey
            );

        $rows = [];

        if ($moduleKey === 'academic_years') {
            foreach (
                ayAcademicYears(
                    $pdo,
                    (int)$scope['tenant_id'],
                    (int)$scope['branch_id']
                ) as $year
            ) {
                $rows[] = [
                    (string)$year['year_name'],
                    (string)$year['start_date'],
                    (string)$year['end_date'],
                    (int)$year['is_current']
                        === 1
                            ? '1'
                            : '0',
                    (string)$year['status'],
                ];
            }

            ayCsvDownload(
                'academic-years.csv',
                $headers,
                $rows
            );
        }

        $selectedAcademicYearId =
            aySelectedAcademicYearId(
                $pdo,
                $scope,
                $input
            );

        $currentAcademicYearId =
            ayCurrentAcademicYearId(
                $pdo,
                (int)$scope['tenant_id'],
                (int)$scope['branch_id']
            );

        $statement = $pdo->prepare(
            "SELECT
                record_data,
                status
             FROM academic_year_module_records
             WHERE tenant_id=:tenant_id
               AND branch_id=:branch_id
               AND module_key=:module_key
             ORDER BY id DESC"
        );

        $statement->execute([
            'tenant_id' =>
                $scope['tenant_id'],
            'branch_id' =>
                $scope['branch_id'],
            'module_key' =>
                $moduleKey,
        ]);

        foreach (
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) as $row
        ) {
            $data = json_decode(
                (string)$row[
                    'record_data'
                ],
                true
            );

            $data = is_array($data)
                ? $data
                : [];

            $recordAcademicYearId =
                (int)(
                    $data[
                        'academic_year_id'
                    ]
                    ?? $currentAcademicYearId
                );

            if (
                $recordAcademicYearId
                !== $selectedAcademicYearId
            ) {
                continue;
            }

            $exportRow = [];

            foreach ($headers as $header) {
                if ($header === 'status') {
                    $exportRow[] =
                        (string)$row['status'];
                } else {
                    $exportRow[] =
                        (string)(
                            $data[$header]
                            ?? ''
                        );
                }
            }

            $rows[] = $exportRow;
        }

        ayCsvDownload(
            $moduleKey
            . '-'
            . $selectedAcademicYearId
            . '.csv',
            $headers,
            $rows
        );
    }

    if ($action === 'template') {
        if (!ayCan($moduleKey, 'import')) {
            ayJson(
                false,
                'You do not have permission to import this section.',
                [],
                403
            );
        }

        ayCsvDownload(
            $moduleKey
            . '-import-template.csv',
            ayImportHeaders($moduleKey),
            []
        );
    }

    if ($action === 'import') {
        if (!ayCan($moduleKey, 'import')) {
            ayJson(
                false,
                'You do not have permission to import this section.',
                [],
                403
            );
        }

        ayCsrf($input);

        $file = $_FILES[
            'import_file'
        ] ?? null;

        if (!is_array($file)) {
            throw new InvalidArgumentException(
                'Choose a CSV or XLSX file.'
            );
        }

        $rows =
            ayRowsToAssociative(
                aySpreadsheetRows($file)
            );

        $expectedHeaders =
            ayImportHeaders(
                $moduleKey
            );

        $imported = 0;
        $skipped = 0;
        $errors = [];

        $selectedAcademicYearId = 0;

        if (
            $moduleKey
            !== 'academic_years'
        ) {
            $selectedAcademicYearId =
                aySelectedAcademicYearId(
                    $pdo,
                    $scope,
                    $input
                );

            if (
                $selectedAcademicYearId
                <= 0
            ) {
                throw new InvalidArgumentException(
                    'Select an Academic Year before importing.'
                );
            }
        }

        foreach ($rows as $rowInfo) {
            $rowNumber =
                (int)$rowInfo['row'];

            $raw =
                (array)$rowInfo['data'];

            $source = [];

            foreach (
                $expectedHeaders
                as $header
            ) {
                $source[$header] =
                    $raw[$header]
                    ?? '';
            }

            try {
                $data =
                    ayValidateModuleData(
                        $moduleKey,
                        $source
                    );

                if (
                    $moduleKey
                    === 'academic_years'
                ) {
                    $duplicate =
                        $pdo->prepare(
                            "SELECT id
                             FROM academic_years
                             WHERE tenant_id=:tenant_id
                               AND branch_id=:branch_id
                               AND year_name=:year_name
                             LIMIT 1"
                        );

                    $duplicate->execute([
                        'tenant_id' =>
                            $scope['tenant_id'],
                        'branch_id' =>
                            $scope['branch_id'],
                        'year_name' =>
                            $data['year_name'],
                    ]);

                    if (
                        $duplicate->fetchColumn()
                    ) {
                        $skipped++;
                        $errors[] =
                            "Row {$rowNumber}: Academic Year already exists.";
                        continue;
                    }

                    if (
                        (int)$data[
                            'is_current'
                        ] === 1
                    ) {
                        $pdo->prepare(
                            "UPDATE academic_years
                             SET is_current=0
                             WHERE tenant_id=:tenant_id
                               AND branch_id=:branch_id"
                        )->execute([
                            'tenant_id' =>
                                $scope['tenant_id'],
                            'branch_id' =>
                                $scope['branch_id'],
                        ]);
                    }

                    $statement =
                        $pdo->prepare(
                            "INSERT INTO academic_years
                                (
                                    tenant_id,
                                    branch_id,
                                    academic_year_code,
                                    year_name,
                                    start_date,
                                    end_date,
                                    is_current,
                                    status
                                )
                             VALUES
                                (
                                    :tenant_id,
                                    :branch_id,
                                    :academic_year_code,
                                    :year_name,
                                    :start_date,
                                    :end_date,
                                    :is_current,
                                    :status
                                )"
                        );

                    $statement->execute(
                        [
                            'tenant_id' =>
                                $scope['tenant_id'],
                            'branch_id' =>
                                $scope['branch_id'],
                        ]
                        + $data
                    );

                    $recordId =
                        (int)$pdo
                            ->lastInsertId();

                    ayAudit(
                        $pdo,
                        $scope,
                        'import_academic_year',
                        'academic_years',
                        $recordId,
                        [],
                        $data
                    );
                } else {
                    $status =
                        (string)(
                            $data['status']
                            ?? 'active'
                        );

                    unset(
                        $data['status']
                    );

                    $data[
                        'academic_year_id'
                    ] =
                        $selectedAcademicYearId;

                    $json =
                        json_encode(
                            $data,
                            JSON_UNESCAPED_UNICODE
                            | JSON_UNESCAPED_SLASHES
                        );

                    if ($json === false) {
                        throw new RuntimeException(
                            'Unable to encode imported record.'
                        );
                    }

                    $statement =
                        $pdo->prepare(
                            "INSERT INTO academic_year_module_records
                                (
                                    tenant_id,
                                    branch_id,
                                    module_key,
                                    record_data,
                                    status,
                                    created_by,
                                    updated_by
                                )
                             VALUES
                                (
                                    :tenant_id,
                                    :branch_id,
                                    :module_key,
                                    :record_data,
                                    :status,
                                    :created_by,
                                    :updated_by
                                )"
                        );

                    $statement->execute([
                        'tenant_id' =>
                            $scope['tenant_id'],
                        'branch_id' =>
                            $scope['branch_id'],
                        'module_key' =>
                            $moduleKey,
                        'record_data' =>
                            $json,
                        'status' =>
                            $status,
                        'created_by' =>
                            $scope['user_id'],
                        'updated_by' =>
                            $scope['user_id'],
                    ]);

                    $recordId =
                        (int)$pdo
                            ->lastInsertId();

                    ayAudit(
                        $pdo,
                        $scope,
                        'import_' . $moduleKey,
                        'academic_year_module_records',
                        $recordId,
                        [],
                        $data
                        + [
                            'status' =>
                                $status,
                        ]
                    );
                }

                $imported++;
            } catch (Throwable $exception) {
                $skipped++;

                $errors[] =
                    "Row {$rowNumber}: "
                    . $exception
                        ->getMessage();
            }
        }

        ayJson(
            true,
            "Import completed. {$imported} imported, {$skipped} skipped.",
            [
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => array_slice(
                    $errors,
                    0,
                    100
                ),
                'csrf_token' =>
                    ayCsrfToken(),
            ]
        );
    }

    if (in_array($action, ['create', 'update'], true)) {
        if (!ayCan($moduleKey, $action === 'create' ? 'create' : 'edit')) {
            ayJson(false, 'You do not have permission to save this section.', [], 403);
        }

        ayCsrf($input);
        $id = (int)($input['id'] ?? 0);
        $rawData =
            is_array(
                $input['data']
                ?? null
            )
                ? $input['data']
                : [];

        $data =
            ayValidateModuleData(
                $moduleKey,
                $rawData
            );

        if (
            $moduleKey
            !== 'academic_years'
        ) {
            $selectedAcademicYearId =
                aySelectedAcademicYearId(
                    $pdo,
                    $scope,
                    $input
                );

            if (
                $selectedAcademicYearId
                <= 0
            ) {
                throw new InvalidArgumentException(
                    'Select an Academic Year first.'
                );
            }

            $data[
                'academic_year_id'
            ] =
                $selectedAcademicYearId;
        }

        $pdo->beginTransaction();

        if ($moduleKey === 'academic_years') {
            $duplicate = $pdo->prepare("SELECT id FROM academic_years
                WHERE tenant_id=:tenant_id AND branch_id=:branch_id AND year_name=:year_name AND id<>:id LIMIT 1");
            $duplicate->execute([
                'tenant_id' => $scope['tenant_id'],
                'branch_id' => $scope['branch_id'],
                'year_name' => $data['year_name'],
                'id' => $id,
            ]);
            if ($duplicate->fetchColumn()) {
                throw new InvalidArgumentException('This academic year already exists.');
            }

            if ((int)$data['is_current'] === 1) {
                $pdo->prepare("UPDATE academic_years SET is_current=0 WHERE tenant_id=:tenant_id AND branch_id=:branch_id")
                    ->execute([
                        'tenant_id' => $scope['tenant_id'],
                        'branch_id' => $scope['branch_id'],
                    ]);
            }

            if ($id > 0) {
                $oldStatement = $pdo->prepare("SELECT * FROM academic_years WHERE id=:id AND tenant_id=:tenant_id AND branch_id=:branch_id FOR UPDATE");
                $oldStatement->execute([
                    'id' => $id,
                    'tenant_id' => $scope['tenant_id'],
                    'branch_id' => $scope['branch_id'],
                ]);
                $old = $oldStatement->fetch(PDO::FETCH_ASSOC);
                if (!$old) {
                    throw new InvalidArgumentException('Academic year record not found.');
                }

                $statement = $pdo->prepare("UPDATE academic_years SET
                    academic_year_code=:academic_year_code,year_name=:year_name,start_date=:start_date,
                    end_date=:end_date,is_current=:is_current,status=:status
                    WHERE id=:id AND tenant_id=:tenant_id AND branch_id=:branch_id");
                $statement->execute($data + [
                    'id' => $id,
                    'tenant_id' => $scope['tenant_id'],
                    'branch_id' => $scope['branch_id'],
                ]);
                ayAudit($pdo, $scope, 'update_academic_year', 'academic_years', $id, $old, $data);
                $message = 'Academic year updated successfully.';
            } else {
                $statement = $pdo->prepare("INSERT INTO academic_years
                    (tenant_id,branch_id,academic_year_code,year_name,start_date,end_date,is_current,status)
                    VALUES (:tenant_id,:branch_id,:academic_year_code,:year_name,:start_date,:end_date,:is_current,:status)");
                $statement->execute([
                    'tenant_id' => $scope['tenant_id'],
                    'branch_id' => $scope['branch_id'],
                ] + $data);
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
                    WHERE id=:id AND tenant_id=:tenant_id AND branch_id=:branch_id AND module_key=:module_key FOR UPDATE");
                $oldStatement->execute([
                    'id' => $id,
                    'tenant_id' => $scope['tenant_id'],
                    'branch_id' => $scope['branch_id'],
                    'module_key' => $moduleKey,
                ]);
                $old = $oldStatement->fetch(PDO::FETCH_ASSOC);
                if (!$old) {
                    throw new InvalidArgumentException('Record not found.');
                }

                $statement = $pdo->prepare("UPDATE academic_year_module_records SET
                    record_data=:record_data,status=:status,updated_by=:updated_by
                    WHERE id=:id AND tenant_id=:tenant_id AND branch_id=:branch_id AND module_key=:module_key");
                $statement->execute([
                    'record_data' => $json,
                    'status' => $status,
                    'updated_by' => $scope['user_id'],
                    'id' => $id,
                    'tenant_id' => $scope['tenant_id'],
                    'branch_id' => $scope['branch_id'],
                    'module_key' => $moduleKey,
                ]);
                ayAudit($pdo, $scope, 'update_' . $moduleKey, 'academic_year_module_records', $id, $old ?: [], $data + ['status' => $status]);
                $message = 'Record updated successfully.';
            } else {
                $statement = $pdo->prepare("INSERT INTO academic_year_module_records
                    (tenant_id,branch_id,module_key,record_data,status,created_by,updated_by)
                    VALUES (:tenant_id,:branch_id,:module_key,:record_data,:status,:created_by,:updated_by)");
                $statement->execute([
                    'tenant_id' => $scope['tenant_id'],
                    'branch_id' => $scope['branch_id'],
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
            WHERE id=:id AND tenant_id=:tenant_id AND branch_id=:branch_id");
        $statement->execute([
            'id' => $id,
            'tenant_id' => $scope['tenant_id'],
            'branch_id' => $scope['branch_id'],
        ]);
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
            $check = $pdo->prepare("SELECT is_current FROM academic_years WHERE id=:id AND tenant_id=:tenant_id AND branch_id=:branch_id");
            $check->execute([
                'id' => $id,
                'tenant_id' => $scope['tenant_id'],
                'branch_id' => $scope['branch_id'],
            ]);
            $isCurrent = $check->fetchColumn();
            if ($isCurrent === false) {
                throw new InvalidArgumentException('Academic year record not found.');
            }
            if ((int)$isCurrent === 1) {
                throw new InvalidArgumentException('The current academic year cannot be deleted. Archive it after selecting another current year.');
            }

            try {
                $statement = $pdo->prepare("DELETE FROM academic_years WHERE id=:id AND tenant_id=:tenant_id AND branch_id=:branch_id");
                $statement->execute([
                    'id' => $id,
                    'tenant_id' => $scope['tenant_id'],
                    'branch_id' => $scope['branch_id'],
                ]);
            } catch (PDOException $exception) {
                if ((string)$exception->getCode() === '23000') {
                    throw new InvalidArgumentException('This academic year is already used by other records and cannot be deleted. Archive it instead.');
                }
                throw $exception;
            }
            ayAudit($pdo, $scope, 'delete_academic_year', 'academic_years', $id);
        } else {
            $statement = $pdo->prepare("DELETE FROM academic_year_module_records
                WHERE id=:id AND tenant_id=:tenant_id AND branch_id=:branch_id AND module_key=:module_key");
            $statement->execute([
                'id' => $id,
                'tenant_id' => $scope['tenant_id'],
                'branch_id' => $scope['branch_id'],
                'module_key' => $moduleKey,
            ]);
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
        'allowed_actions' => ['list', 'create', 'update', 'archive', 'delete', 'export', 'import', 'template'],
    ], 400);
} catch (InvalidArgumentException $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    ayJson(false, $exception->getMessage(), [], 422);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('academic-years.php: ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $message = (str_contains($host, 'localhost') || str_contains($host, '127.0.0.1'))
        ? 'Academic Year request failed: ' . $exception->getMessage()
        : 'Unable to complete the Academic Year request.';
    ayJson(false, $message, [], 500);
}

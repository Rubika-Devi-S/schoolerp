<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

const SCHOOL_MANAGEMENT_BUILD =
    '2026-08-06-super-admin-schools-tenants-synced-v4';

function smOut(
    bool $success,
    string $message = '',
    array $data = [],
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
        [
            'success' => $success,
            'message' => $message,
            'data' => $data,
        ],
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}

function smUser(): array
{
    $user = function_exists('current_user')
        ? current_user()
        : [];

    return is_array($user)
        ? $user
        : [];
}

function smRequireSuperAdmin(PDO $pdo): array
{
    $user = smUser();

    $userId = (int)(
        $user['id']
        ?? $user['user_id']
        ?? $_SESSION['user_id']
        ?? 0
    );

    $roleId = (int)(
        $user['role_id']
        ?? $_SESSION['role_id']
        ?? 0
    );

    $roleKey = strtolower(trim((string)(
        $user['role_key']
        ?? $_SESSION['role_key']
        ?? ''
    )));

    if (
        $roleKey === ''
        && $roleId > 0
    ) {
        try {
            $statement = $pdo->prepare(
                "SELECT role_key
                 FROM roles
                 WHERE id = :role_id
                 LIMIT 1"
            );

            $statement->execute([
                'role_id' => $roleId,
            ]);

            $roleKey = strtolower(trim((string)(
                $statement->fetchColumn() ?: ''
            )));
        } catch (Throwable $exception) {
            error_log(
                'School API role lookup: '
                . $exception->getMessage()
            );
        }
    }

    $allowed = $roleId === 1
        || in_array(
            $roleKey,
            [
                'super_admin',
                'super-admin',
                'superadministrator',
                'super_administrator',
                'super-administrator',
            ],
            true
        );

    if ($userId <= 0) {
        smOut(
            false,
            'Login session is required.',
            ['build' => SCHOOL_MANAGEMENT_BUILD],
            401
        );
    }

    if (!$allowed) {
        smOut(
            false,
            'Access denied.',
            ['build' => SCHOOL_MANAGEMENT_BUILD],
            403
        );
    }

    return [
        'user_id' => $userId,
        'role_id' => $roleId,
        'role_key' => $roleKey,
    ];
}

function smCsrf(array $input): void
{
    $sessionToken = (string)(
        $_SESSION['school_management_csrf'] ?? ''
    );

    $requestToken = (string)(
        $input['csrf_token'] ?? ''
    );

    if (
        $sessionToken === ''
        || $requestToken === ''
        || !hash_equals($sessionToken, $requestToken)
    ) {
        smOut(
            false,
            'Invalid or expired CSRF token. Refresh the page.',
            ['build' => SCHOOL_MANAGEMENT_BUILD],
            419
        );
    }
}

function smInput(): array
{
    return $_POST;
}

function smTableExists(
    PDO $pdo,
    string $table
): bool {
    $statement = $pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = ?"
    );

    $statement->execute([$table]);

    return (int)$statement->fetchColumn() > 0;
}

function smEnsureSchema(PDO $pdo): void
{
    if (smTableExists($pdo, 'platform_schools')) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE platform_schools (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id BIGINT UNSIGNED DEFAULT NULL,
            school_uid VARCHAR(40) NOT NULL,
            school_code VARCHAR(40) NOT NULL,
            school_name VARCHAR(180) NOT NULL,
            school_name_normalized VARCHAR(180) NOT NULL,
            school_type VARCHAR(60) NOT NULL,
            board_name VARCHAR(80) NOT NULL,
            academic_year VARCHAR(20) NOT NULL,
            principal_name VARCHAR(150) NOT NULL,
            email_address VARCHAR(190) NOT NULL,
            mobile_number VARCHAR(20) NOT NULL,
            alternate_contact VARCHAR(20) DEFAULT NULL,
            address_text VARCHAR(500) NOT NULL,
            city_name VARCHAR(100) NOT NULL,
            state_name VARCHAR(100) NOT NULL,
            country_name VARCHAR(100) NOT NULL,
            pin_code VARCHAR(12) NOT NULL,
            logo_path VARCHAR(255) DEFAULT NULL,
            website_url VARCHAR(255) DEFAULT NULL,
            status ENUM('active','inactive')
                NOT NULL DEFAULT 'active',
            created_by BIGINT UNSIGNED DEFAULT NULL,
            updated_by BIGINT UNSIGNED DEFAULT NULL,
            created_at TIMESTAMP NOT NULL
                DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL
                DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_platform_school_tenant (tenant_id),
            UNIQUE KEY uq_platform_school_uid (school_uid),
            UNIQUE KEY uq_platform_school_code (school_code),
            UNIQUE KEY uq_platform_school_email (email_address),
            KEY idx_platform_school_name (school_name_normalized),
            KEY idx_platform_school_status (status),
            KEY idx_platform_school_board (board_name)
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci"
    );
}


function smColumnExists(
    PDO $pdo,
    string $table,
    string $column
): bool {
    $statement = $pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = ?
           AND column_name = ?"
    );

    $statement->execute([$table, $column]);

    return (int)$statement->fetchColumn() > 0;
}

function smEnsureTenantLink(PDO $pdo): void
{
    if (!smColumnExists($pdo, 'platform_schools', 'tenant_id')) {
        $pdo->exec(
            "ALTER TABLE platform_schools
             ADD COLUMN tenant_id BIGINT UNSIGNED NULL
             AFTER id"
        );
    }

    try {
        $pdo->exec(
            "ALTER TABLE platform_schools
             ADD UNIQUE KEY uq_platform_school_tenant (tenant_id)"
        );
    } catch (Throwable $exception) {
        // Key already exists or old duplicate data requires manual cleanup.
    }
}

function smSyncTenants(PDO $pdo): void
{
    if (
        !smTableExists($pdo, 'tenants')
        || !smTableExists($pdo, 'platform_schools')
    ) {
        return;
    }

    smEnsureTenantLink($pdo);

    $tenants = $pdo->query(
        "SELECT
            id,
            tenant_code,
            school_name,
            email,
            mobile,
            address,
            logo_path,
            status
         FROM tenants
         ORDER BY id"
    )->fetchAll(PDO::FETCH_ASSOC);

    $findStatement = $pdo->prepare(
        "SELECT id
         FROM platform_schools
         WHERE tenant_id = ?
            OR school_code = ?
         LIMIT 1"
    );

    $insertStatement = $pdo->prepare(
        "INSERT INTO platform_schools (
            tenant_id,
            school_uid,
            school_code,
            school_name,
            school_name_normalized,
            school_type,
            board_name,
            academic_year,
            principal_name,
            email_address,
            mobile_number,
            alternate_contact,
            address_text,
            city_name,
            state_name,
            country_name,
            pin_code,
            logo_path,
            website_url,
            status,
            created_by,
            updated_by
         ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
            ?, NULL, ?, ?, ?, ?, ?, ?, NULL, ?, NULL, NULL
         )"
    );

    $updateStatement = $pdo->prepare(
        "UPDATE platform_schools SET
            tenant_id = ?,
            school_code = ?,
            school_name = ?,
            school_name_normalized = ?,
            email_address = CASE
                WHEN email_address = '' OR email_address IS NULL
                THEN ?
                ELSE email_address
            END,
            mobile_number = CASE
                WHEN mobile_number = '' OR mobile_number IS NULL
                THEN ?
                ELSE mobile_number
            END,
            address_text = CASE
                WHEN address_text = '' OR address_text IS NULL
                THEN ?
                ELSE address_text
            END,
            logo_path = CASE
                WHEN logo_path = '' OR logo_path IS NULL
                THEN ?
                ELSE logo_path
            END,
            status = ?
         WHERE id = ?"
    );

    foreach ($tenants as $tenant) {
        $tenantId = (int)$tenant['id'];
        $tenantCode = trim((string)$tenant['tenant_code']);
        $schoolName = trim((string)$tenant['school_name']);

        if ($schoolName === '') {
            continue;
        }

        $managementStatus = in_array(
            strtolower((string)$tenant['status']),
            ['active', 'trial'],
            true
        ) ? 'active' : 'inactive';

        $normalizedName = mb_strtolower(
            preg_replace('/\s+/', ' ', $schoolName) ?: $schoolName
        );

        $findStatement->execute([
            $tenantId,
            $tenantCode,
        ]);

        $existingId = (int)($findStatement->fetchColumn() ?: 0);

        if ($existingId > 0) {
            $updateStatement->execute([
                $tenantId,
                $tenantCode,
                $schoolName,
                $normalizedName,
                trim((string)($tenant['email'] ?? '')),
                trim((string)($tenant['mobile'] ?? '')),
                trim((string)($tenant['address'] ?? '')),
                trim((string)($tenant['logo_path'] ?? '')),
                $managementStatus,
                $existingId,
            ]);
            continue;
        }

        $schoolUid = 'SCL-TEN-' . str_pad(
            (string)$tenantId,
            5,
            '0',
            STR_PAD_LEFT
        );

        $insertStatement->execute([
            $tenantId,
            $schoolUid,
            $tenantCode,
            $schoolName,
            $normalizedName,
            'Higher Secondary',
            'State Board',
            date('Y') . ' - ' . ((int)date('Y') + 1),
            'Not Updated',
            trim((string)($tenant['email'] ?? '')),
            trim((string)($tenant['mobile'] ?? '')),
            trim((string)($tenant['address'] ?? '')),
            '',
            '',
            'India',
            '',
            trim((string)($tenant['logo_path'] ?? '')),
            $managementStatus,
        ]);
    }
}

function smCreateTenant(
    PDO $pdo,
    array $data,
    string $schoolCode,
    ?string $logoPath
): int {
    if (!smTableExists($pdo, 'tenants')) {
        return 0;
    }

    $tenantStatus = $data['status'] === 'active'
        ? 'active'
        : 'suspended';

    $statement = $pdo->prepare(
        "INSERT INTO tenants (
            tenant_code,
            school_name,
            legal_name,
            email,
            mobile,
            address,
            logo_path,
            status
         ) VALUES (?, ?, NULL, ?, ?, ?, ?, ?)"
    );

    $statement->execute([
        $schoolCode,
        $data['school_name'],
        $data['email_address'] ?: null,
        $data['mobile_number'] ?: null,
        $data['address_text'] ?: null,
        $logoPath ?: null,
        $tenantStatus,
    ]);

    return (int)$pdo->lastInsertId();
}

function smUpdateTenant(
    PDO $pdo,
    int $tenantId,
    array $data,
    string $schoolCode,
    ?string $logoPath
): void {
    if (
        $tenantId <= 0
        || !smTableExists($pdo, 'tenants')
    ) {
        return;
    }

    $tenantStatus = $data['status'] === 'active'
        ? 'active'
        : 'suspended';

    $statement = $pdo->prepare(
        "UPDATE tenants SET
            tenant_code = ?,
            school_name = ?,
            email = ?,
            mobile = ?,
            address = ?,
            logo_path = ?,
            status = ?
         WHERE id = ?"
    );

    $statement->execute([
        $schoolCode,
        $data['school_name'],
        $data['email_address'] ?: null,
        $data['mobile_number'] ?: null,
        $data['address_text'] ?: null,
        $logoPath ?: null,
        $tenantStatus,
        $tenantId,
    ]);
}

function smClean(
    mixed $value,
    int $maxLength,
    string $label,
    bool $required = true
): string {
    $value = trim((string)$value);

    if ($required && $value === '') {
        throw new InvalidArgumentException(
            $label . ' is required.'
        );
    }

    if (mb_strlen($value) > $maxLength) {
        throw new InvalidArgumentException(
            $label . ' cannot exceed '
            . $maxLength
            . ' characters.'
        );
    }

    return $value;
}

function smPhone(
    mixed $value,
    string $label,
    bool $required = true
): string {
    $value = trim((string)$value);

    if (!$required && $value === '') {
        return '';
    }

    if (
        !preg_match(
            '/^[0-9+\-\s()]{7,20}$/',
            $value
        )
    ) {
        throw new InvalidArgumentException(
            $label . ' is invalid.'
        );
    }

    return $value;
}

function smValidate(array $input): array
{
    $email = strtolower(
        smClean(
            $input['email_address'] ?? '',
            190,
            'Email Address'
        )
    );

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException(
            'Email Address is invalid.'
        );
    }

    $website = smClean(
        $input['website_url'] ?? '',
        255,
        'School Website',
        false
    );

    if (
        $website !== ''
        && !filter_var($website, FILTER_VALIDATE_URL)
    ) {
        throw new InvalidArgumentException(
            'School Website is invalid.'
        );
    }

    $academicYear = smClean(
        $input['academic_year'] ?? '',
        20,
        'Academic Year'
    );

    if (
        !preg_match(
            '/^\d{4}\s*(?:-|\/)\s*\d{4}$/',
            $academicYear
        )
    ) {
        throw new InvalidArgumentException(
            'Academic Year must be like 2026 - 2027.'
        );
    }

    $status = strtolower(
        trim((string)($input['status'] ?? 'active'))
    );

    if (!in_array($status, ['active', 'inactive'], true)) {
        throw new InvalidArgumentException(
            'Status is invalid.'
        );
    }

    $schoolName = smClean(
        $input['school_name'] ?? '',
        180,
        'School Name'
    );

    return [
        'id' => max(0, (int)($input['id'] ?? 0)),
        'school_name' => $schoolName,
        'school_name_normalized' =>
            mb_strtolower(
                preg_replace('/\s+/', ' ', $schoolName) ?: $schoolName
            ),
        'school_type' => smClean(
            $input['school_type'] ?? '',
            60,
            'School Type'
        ),
        'board_name' => smClean(
            $input['board_name'] ?? '',
            80,
            'Board'
        ),
        'academic_year' => preg_replace(
            '/\s+/',
            ' ',
            $academicYear
        ),
        'principal_name' => smClean(
            $input['principal_name'] ?? '',
            150,
            'Principal Name'
        ),
        'email_address' => $email,
        'mobile_number' => smPhone(
            $input['mobile_number'] ?? '',
            'Mobile Number'
        ),
        'alternate_contact' => smPhone(
            $input['alternate_contact'] ?? '',
            'Alternate Contact Number',
            false
        ),
        'address_text' => smClean(
            $input['address_text'] ?? '',
            500,
            'Address'
        ),
        'city_name' => smClean(
            $input['city_name'] ?? '',
            100,
            'City'
        ),
        'state_name' => smClean(
            $input['state_name'] ?? '',
            100,
            'State'
        ),
        'country_name' => smClean(
            $input['country_name'] ?? '',
            100,
            'Country'
        ),
        'pin_code' => smClean(
            $input['pin_code'] ?? '',
            12,
            'PIN Code'
        ),
        'website_url' => $website,
        'status' => $status,
        'remove_logo' =>
            (int)($input['remove_logo'] ?? 0) === 1,
    ];
}

function smSchoolCode(PDO $pdo): array
{
    for ($attempt = 0; $attempt < 20; $attempt++) {
        $random = strtoupper(
            substr(
                bin2hex(random_bytes(4)),
                0,
                6
            )
        );

        $year = date('y');

        $schoolUid = 'SCL-' . $year . '-' . $random;
        $schoolCode = 'SCH-' . $year . '-' . $random;

        $statement = $pdo->prepare(
            "SELECT COUNT(*)
             FROM platform_schools
             WHERE school_uid = ?
                OR school_code = ?"
        );

        $statement->execute([
            $schoolUid,
            $schoolCode,
        ]);

        if ((int)$statement->fetchColumn() === 0) {
            return [$schoolUid, $schoolCode];
        }
    }

    throw new RuntimeException(
        'Unable to generate a unique School ID.'
    );
}

function smUploadLogo(
    array $file,
    int $schoolId,
    string $schoolUid
): string {
    if (
        !isset($file['error'])
        || (int)$file['error'] === UPLOAD_ERR_NO_FILE
    ) {
        return '';
    }

    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException(
            'School Logo upload failed.'
        );
    }

    if ((int)$file['size'] > 2 * 1024 * 1024) {
        throw new InvalidArgumentException(
            'School Logo cannot exceed 2 MB.'
        );
    }

    $mimeType = '';

    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo) {
            $mimeType = (string)finfo_file(
                $finfo,
                (string)$file['tmp_name']
            );
            finfo_close($finfo);
        }
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowed[$mimeType])) {
        throw new InvalidArgumentException(
            'School Logo must be JPG, PNG or WEBP.'
        );
    }

    $relativeDirectory =
        'uploads/schools/'
        . $schoolId;

    $absoluteDirectory =
        dirname(__DIR__)
        . '/'
        . $relativeDirectory;

    if (
        !is_dir($absoluteDirectory)
        && !mkdir(
            $absoluteDirectory,
            0755,
            true
        )
        && !is_dir($absoluteDirectory)
    ) {
        throw new RuntimeException(
            'Unable to create the School Logo directory.'
        );
    }

    $filename =
        strtolower($schoolUid)
        . '-'
        . bin2hex(random_bytes(5))
        . '.'
        . $allowed[$mimeType];

    $absolutePath =
        $absoluteDirectory
        . '/'
        . $filename;

    if (
        !move_uploaded_file(
            (string)$file['tmp_name'],
            $absolutePath
        )
    ) {
        throw new RuntimeException(
            'Unable to save the School Logo.'
        );
    }

    return $relativeDirectory . '/' . $filename;
}

function smDeleteLogo(?string $path): void
{
    $path = trim((string)$path);

    if ($path === '') {
        return;
    }

    $absolutePath =
        dirname(__DIR__)
        . '/'
        . ltrim($path, '/');

    if (is_file($absolutePath)) {
        @unlink($absolutePath);
    }
}

function smSchool(
    PDO $pdo,
    int $id
): array {
    $statement = $pdo->prepare(
        "SELECT *
         FROM platform_schools
         WHERE id = ?
         LIMIT 1"
    );

    $statement->execute([$id]);

    $school = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$school) {
        smOut(
            false,
            'School record was not found.',
            ['build' => SCHOOL_MANAGEMENT_BUILD],
            404
        );
    }

    $school['logo_url'] =
        !empty($school['logo_path'])
            ? '../' . ltrim(
                (string)$school['logo_path'],
                '/'
            )
            : '';

    return $school;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    smOut(
        false,
        'Database connection is missing.',
        ['build' => SCHOOL_MANAGEMENT_BUILD],
        500
    );
}

$user = smRequireSuperAdmin($pdo);

if (
    empty($_SESSION['school_management_csrf'])
    || !is_string($_SESSION['school_management_csrf'])
) {
    $_SESSION['school_management_csrf'] =
        bin2hex(random_bytes(32));
}

try {
    smEnsureSchema($pdo);
    smEnsureTenantLink($pdo);
    smSyncTenants($pdo);

    $action = strtolower(trim((string)(
        $_POST['action']
        ?? $_GET['action']
        ?? 'list'
    )));

    if ($action === 'list') {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = max(
            5,
            min(100, (int)($_GET['per_page'] ?? 10))
        );

        $search = trim((string)($_GET['search'] ?? ''));
        $schoolType = trim(
            (string)($_GET['school_type'] ?? '')
        );
        $boardName = trim(
            (string)($_GET['board_name'] ?? '')
        );
        $status = strtolower(trim(
            (string)($_GET['status'] ?? '')
        ));

        $where = [];
        $params = [];

        if ($search !== '') {
            $where[] =
                "(school_name LIKE :search
                  OR school_code LIKE :search
                  OR school_uid LIKE :search
                  OR email_address LIKE :search
                  OR mobile_number LIKE :search
                  OR city_name LIKE :search)";

            $params['search'] = '%' . $search . '%';
        }

        if ($schoolType !== '') {
            $where[] = 'school_type = :school_type';
            $params['school_type'] = $schoolType;
        }

        if ($boardName !== '') {
            $where[] = 'board_name = :board_name';
            $params['board_name'] = $boardName;
        }

        if (in_array($status, ['active', 'inactive'], true)) {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }

        $whereSql = $where
            ? ' WHERE ' . implode(' AND ', $where)
            : '';

        $countStatement = $pdo->prepare(
            "SELECT COUNT(*)
             FROM platform_schools"
             . $whereSql
        );

        $countStatement->execute($params);
        $total = (int)$countStatement->fetchColumn();

        $lastPage = max(
            1,
            (int)ceil($total / $perPage)
        );

        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;

        $listStatement = $pdo->prepare(
            "SELECT *
             FROM platform_schools"
             . $whereSql .
            " ORDER BY id DESC
              LIMIT :limit OFFSET :offset"
        );

        foreach ($params as $key => $value) {
            $listStatement->bindValue(
                ':' . $key,
                $value,
                PDO::PARAM_STR
            );
        }

        $listStatement->bindValue(
            ':limit',
            $perPage,
            PDO::PARAM_INT
        );
        $listStatement->bindValue(
            ':offset',
            $offset,
            PDO::PARAM_INT
        );

        $listStatement->execute();

        $records = $listStatement->fetchAll(
            PDO::FETCH_ASSOC
        );

        foreach ($records as &$record) {
            $record['logo_url'] =
                !empty($record['logo_path'])
                    ? '../' . ltrim(
                        (string)$record['logo_path'],
                        '/'
                    )
                    : '';
        }
        unset($record);

        $statsStatement = $pdo->query(
            "SELECT
                COUNT(*) AS total,
                SUM(status = 'active') AS active,
                SUM(status = 'inactive') AS inactive,
                COUNT(DISTINCT board_name) AS boards
             FROM platform_schools"
        );

        $stats = $statsStatement->fetch(
            PDO::FETCH_ASSOC
        ) ?: [];

        smOut(
            true,
            'Schools loaded successfully.',
            [
                'records' => $records,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'last_page' => $lastPage,
                    'per_page' => $perPage,
                ],
                'stats' => [
                    'total' => (int)($stats['total'] ?? 0),
                    'active' => (int)($stats['active'] ?? 0),
                    'inactive' =>
                        (int)($stats['inactive'] ?? 0),
                    'boards' => (int)($stats['boards'] ?? 0),
                ],
                'csrf_token' =>
                    $_SESSION['school_management_csrf'],
                'build' => SCHOOL_MANAGEMENT_BUILD,
            ]
        );
    }

    if ($action === 'get') {
        $id = max(0, (int)($_GET['id'] ?? 0));

        smOut(
            true,
            'School loaded successfully.',
            [
                'school' => smSchool($pdo, $id),
                'csrf_token' =>
                    $_SESSION['school_management_csrf'],
                'build' => SCHOOL_MANAGEMENT_BUILD,
            ]
        );
    }

    if ($action === 'save') {
        $input = smInput();
        smCsrf($input);

        $data = smValidate($input);

        $duplicateStatement = $pdo->prepare(
            "SELECT id
             FROM platform_schools
             WHERE (
                    school_name_normalized =
                        :school_name_normalized
                    OR email_address = :email_address
                    OR mobile_number = :mobile_number
             )
               AND id <> :id
             LIMIT 1"
        );

        $duplicateStatement->execute([
            'school_name_normalized' =>
                $data['school_name_normalized'],
            'email_address' => $data['email_address'],
            'mobile_number' => $data['mobile_number'],
            'id' => $data['id'],
        ]);

        if ((int)$duplicateStatement->fetchColumn() > 0) {
            throw new InvalidArgumentException(
                'A school with the same name, email or mobile number already exists.'
            );
        }

        $pdo->beginTransaction();

        $oldLogoPath = '';
        $schoolUid = '';
        $schoolCode = '';

        if ($data['id'] > 0) {
            $existing = smSchool($pdo, $data['id']);
            $schoolUid = (string)$existing['school_uid'];
            $schoolCode = (string)$existing['school_code'];
            $oldLogoPath = (string)(
                $existing['logo_path'] ?? ''
            );
        } else {
            [$schoolUid, $schoolCode] =
                smSchoolCode($pdo);

            $insertStatement = $pdo->prepare(
                "INSERT INTO platform_schools (
                    tenant_id,
                    school_uid,
                    school_code,
                    school_name,
                    school_name_normalized,
                    school_type,
                    board_name,
                    academic_year,
                    principal_name,
                    email_address,
                    mobile_number,
                    alternate_contact,
                    address_text,
                    city_name,
                    state_name,
                    country_name,
                    pin_code,
                    website_url,
                    status,
                    created_by,
                    updated_by
                 ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                 )"
            );

            $insertStatement->execute([
                null,
                $schoolUid,
                $schoolCode,
                $data['school_name'],
                $data['school_name_normalized'],
                $data['school_type'],
                $data['board_name'],
                $data['academic_year'],
                $data['principal_name'],
                $data['email_address'],
                $data['mobile_number'],
                $data['alternate_contact'] ?: null,
                $data['address_text'],
                $data['city_name'],
                $data['state_name'],
                $data['country_name'],
                $data['pin_code'],
                $data['website_url'] ?: null,
                $data['status'],
                $user['user_id'],
                $user['user_id'],
            ]);

            $data['id'] = (int)$pdo->lastInsertId();
        }

        $newLogoPath = '';

        if (
            isset($_FILES['school_logo'])
            && is_array($_FILES['school_logo'])
        ) {
            $newLogoPath = smUploadLogo(
                $_FILES['school_logo'],
                $data['id'],
                $schoolUid
            );
        }

        $finalLogoPath = $oldLogoPath;

        if ($data['remove_logo']) {
            $finalLogoPath = '';
        }

        if ($newLogoPath !== '') {
            $finalLogoPath = $newLogoPath;
        }

        $tenantId = 0;

        if ($data['id'] > 0) {
            $tenantLookup = $pdo->prepare(
                "SELECT tenant_id
                 FROM platform_schools
                 WHERE id = ?
                 LIMIT 1"
            );
            $tenantLookup->execute([$data['id']]);
            $tenantId = (int)($tenantLookup->fetchColumn() ?: 0);
        }

        if ($tenantId <= 0) {
            $tenantId = smCreateTenant(
                $pdo,
                $data,
                $schoolCode,
                $finalLogoPath
            );
        } else {
            smUpdateTenant(
                $pdo,
                $tenantId,
                $data,
                $schoolCode,
                $finalLogoPath
            );
        }

        $updateStatement = $pdo->prepare(
            "UPDATE platform_schools SET
                tenant_id = ?,
                school_name = ?,
                school_name_normalized = ?,
                school_type = ?,
                board_name = ?,
                academic_year = ?,
                principal_name = ?,
                email_address = ?,
                mobile_number = ?,
                alternate_contact = ?,
                address_text = ?,
                city_name = ?,
                state_name = ?,
                country_name = ?,
                pin_code = ?,
                logo_path = ?,
                website_url = ?,
                status = ?,
                updated_by = ?
             WHERE id = ?"
        );

        $updateStatement->execute([
            $tenantId > 0 ? $tenantId : null,
            $data['school_name'],
            $data['school_name_normalized'],
            $data['school_type'],
            $data['board_name'],
            $data['academic_year'],
            $data['principal_name'],
            $data['email_address'],
            $data['mobile_number'],
            $data['alternate_contact'] ?: null,
            $data['address_text'],
            $data['city_name'],
            $data['state_name'],
            $data['country_name'],
            $data['pin_code'],
            $finalLogoPath ?: null,
            $data['website_url'] ?: null,
            $data['status'],
            $user['user_id'],
            $data['id'],
        ]);

        $pdo->commit();

        if (
            ($data['remove_logo'] || $newLogoPath !== '')
            && $oldLogoPath !== ''
            && $oldLogoPath !== $finalLogoPath
        ) {
            smDeleteLogo($oldLogoPath);
        }

        smOut(
            true,
            $input['id'] > 0
                ? 'School updated successfully.'
                : 'School created successfully.',
            [
                'school' => smSchool($pdo, $data['id']),
                'csrf_token' =>
                    $_SESSION['school_management_csrf'],
                'build' => SCHOOL_MANAGEMENT_BUILD,
            ]
        );
    }

    if ($action === 'delete') {
        $input = smInput();
        smCsrf($input);

        $id = max(0, (int)($input['id'] ?? 0));
        $school = smSchool($pdo, $id);

        $deleteStatement = $pdo->prepare(
            "DELETE FROM platform_schools
             WHERE id = ?"
        );

        $deleteStatement->execute([$id]);

        smDeleteLogo(
            (string)($school['logo_path'] ?? '')
        );

        smOut(
            true,
            'School deleted successfully.',
            [
                'csrf_token' =>
                    $_SESSION['school_management_csrf'],
                'build' => SCHOOL_MANAGEMENT_BUILD,
            ]
        );
    }

    smOut(
        false,
        'School action is invalid.',
        ['build' => SCHOOL_MANAGEMENT_BUILD],
        400
    );
} catch (InvalidArgumentException $exception) {
    if (
        isset($pdo)
        && $pdo instanceof PDO
        && $pdo->inTransaction()
    ) {
        $pdo->rollBack();
    }

    smOut(
        false,
        $exception->getMessage(),
        ['build' => SCHOOL_MANAGEMENT_BUILD],
        422
    );
} catch (PDOException $exception) {
    if (
        isset($pdo)
        && $pdo instanceof PDO
        && $pdo->inTransaction()
    ) {
        $pdo->rollBack();
    }

    error_log(
        'School Management DB ['
        . SCHOOL_MANAGEMENT_BUILD
        . ']: '
        . $exception->getMessage()
    );

    smOut(
        false,
        'Database operation failed.',
        ['build' => SCHOOL_MANAGEMENT_BUILD],
        500
    );
} catch (Throwable $exception) {
    if (
        isset($pdo)
        && $pdo instanceof PDO
        && $pdo->inTransaction()
    ) {
        $pdo->rollBack();
    }

    error_log(
        'School Management ['
        . SCHOOL_MANAGEMENT_BUILD
        . ']: '
        . $exception->getMessage()
    );

    smOut(
        false,
        'School request failed.',
        ['build' => SCHOOL_MANAGEMENT_BUILD],
        500
    );
}

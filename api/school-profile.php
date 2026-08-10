<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

const SCHOOL_PROFILE_BUILD =
    '2026-08-07-school-profile-global-sync-v5';

function spOut(
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

function spScope(): array
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

function spCsrf(array $input): void
{
    $sessionToken = (string)(
        $_SESSION['school_profile_csrf'] ?? ''
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
        spOut(
            false,
            'Invalid or expired CSRF token. Refresh the page.',
            array(
                'build' => SCHOOL_PROFILE_BUILD
            ),
            419
        );
    }
}

function spTableExists(
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

function spColumnExists(
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

    $statement->execute(array($table, $column));

    return (int)$statement->fetchColumn() > 0;
}

function spEnsureSchema(PDO $pdo): void
{
    if (!spTableExists($pdo, 'school_profile')) {
        $pdo->exec(
            "CREATE TABLE school_profile (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id BIGINT UNSIGNED NOT NULL,
                branch_id BIGINT UNSIGNED NULL,
                school_name VARCHAR(200) NOT NULL,
                school_code VARCHAR(50) NULL,
                school_type VARCHAR(80) NULL,
                board_name VARCHAR(120) NULL,
                affiliation_number VARCHAR(100) NULL,
                udise_number VARCHAR(50) NULL,
                established_year SMALLINT UNSIGNED NULL,
                school_motto VARCHAR(250) NULL,
                logo_path VARCHAR(500) NULL,
                logo_fit VARCHAR(20) NOT NULL DEFAULT 'contain',
                logo_zoom SMALLINT UNSIGNED NOT NULL DEFAULT 100,
                logo_position_x TINYINT UNSIGNED NOT NULL DEFAULT 50,
                logo_position_y TINYINT UNSIGNED NOT NULL DEFAULT 50,
                logo_rotation SMALLINT NOT NULL DEFAULT 0,
                logo_shape VARCHAR(20) NOT NULL DEFAULT 'rounded',
                address_line1 VARCHAR(250) NOT NULL,
                address_line2 VARCHAR(250) NULL,
                city VARCHAR(100) NOT NULL,
                district VARCHAR(100) NULL,
                state_name VARCHAR(100) NOT NULL,
                country_name VARCHAR(100) NOT NULL DEFAULT 'India',
                postal_code VARCHAR(15) NOT NULL,
                phone_number VARCHAR(20) NOT NULL,
                alternate_phone VARCHAR(20) NULL,
                email_address VARCHAR(190) NOT NULL,
                website_url VARCHAR(250) NULL,
                principal_name VARCHAR(150) NULL,
                principal_mobile VARCHAR(20) NULL,
                principal_email VARCHAR(190) NULL,
                office_contact_name VARCHAR(150) NULL,
                academic_start_month TINYINT UNSIGNED NOT NULL DEFAULT 6,
                currency_code VARCHAR(10) NOT NULL DEFAULT 'INR',
                timezone_name VARCHAR(80) NOT NULL DEFAULT 'Asia/Kolkata',
                date_format VARCHAR(20) NOT NULL DEFAULT 'd-m-Y',
                notes VARCHAR(1000) NULL,
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_school_profile_scope (
                    tenant_id,
                    branch_id
                ),
                KEY idx_school_profile_tenant (
                    tenant_id,
                    branch_id
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci"
        );
    }

    /*
     * Existing installations are upgraded automatically.
     * No separate SQL migration is required.
     */
    $columns = array(
        'logo_fit' =>
            "ALTER TABLE school_profile
             ADD COLUMN logo_fit VARCHAR(20)
             NOT NULL DEFAULT 'contain'
             AFTER logo_path",
        'logo_zoom' =>
            "ALTER TABLE school_profile
             ADD COLUMN logo_zoom SMALLINT UNSIGNED
             NOT NULL DEFAULT 100
             AFTER logo_fit",
        'logo_position_x' =>
            "ALTER TABLE school_profile
             ADD COLUMN logo_position_x TINYINT UNSIGNED
             NOT NULL DEFAULT 50
             AFTER logo_zoom",
        'logo_position_y' =>
            "ALTER TABLE school_profile
             ADD COLUMN logo_position_y TINYINT UNSIGNED
             NOT NULL DEFAULT 50
             AFTER logo_position_x",
        'logo_rotation' =>
            "ALTER TABLE school_profile
             ADD COLUMN logo_rotation SMALLINT
             NOT NULL DEFAULT 0
             AFTER logo_position_y",
        'logo_shape' =>
            "ALTER TABLE school_profile
             ADD COLUMN logo_shape VARCHAR(20)
             NOT NULL DEFAULT 'rounded'
             AFTER logo_rotation"
    );

    foreach ($columns as $column => $sql) {
        if (!spColumnExists($pdo, 'school_profile', $column)) {
            $pdo->exec($sql);
        }
    }
}

function spProfile(
    PDO $pdo,
    array $scope
): array {
    /*
     * School Profile is tenant-wide branding.
     *
     * Older saves may contain branch-specific rows or multiple NULL
     * branch rows because MySQL unique indexes permit multiple NULLs.
     * Always load the most recently updated tenant profile so the saved
     * values remain visible after refresh and on every School ERP page.
     */
    $statement = $pdo->prepare(
        "SELECT *
         FROM school_profile
         WHERE tenant_id = ?
         ORDER BY
            CASE
                WHEN branch_id IS NULL THEN 0
                ELSE 1
            END,
            updated_at DESC,
            id DESC
         LIMIT 1"
    );

    $statement->execute(array(
        $scope['tenant_id']
    ));

    $profile = $statement->fetch(
        PDO::FETCH_ASSOC
    );

    if (!$profile) {
        return array(
            'id' => 0,
            'school_name' => '',
            'school_code' => '',
            'school_type' => '',
            'board_name' => '',
            'affiliation_number' => '',
            'udise_number' => '',
            'established_year' => '',
            'school_motto' => '',
            'logo_path' => '',
            'logo_url' => '',
            'logo_fit' => 'contain',
            'logo_zoom' => 100,
            'logo_position_x' => 50,
            'logo_position_y' => 50,
            'logo_rotation' => 0,
            'logo_shape' => 'rounded',
            'address_line1' => '',
            'address_line2' => '',
            'city' => '',
            'district' => '',
            'state_name' => '',
            'country_name' => 'India',
            'postal_code' => '',
            'phone_number' => '',
            'alternate_phone' => '',
            'email_address' => '',
            'website_url' => '',
            'principal_name' => '',
            'principal_mobile' => '',
            'principal_email' => '',
            'office_contact_name' => '',
            'academic_start_month' => 6,
            'currency_code' => 'INR',
            'timezone_name' => 'Asia/Kolkata',
            'date_format' => 'd-m-Y',
            'notes' => ''
        );
    }

    $profile['logo_url'] =
        !empty($profile['logo_path'])
            ? '../' . ltrim(
                (string)$profile['logo_path'],
                '/'
            )
            : '';

    return $profile;
}

function spExistingProfileId(
    PDO $pdo,
    int $tenantId
): int {
    $statement = $pdo->prepare(
        "SELECT id
         FROM school_profile
         WHERE tenant_id = ?
         ORDER BY
            CASE
                WHEN branch_id IS NULL THEN 0
                ELSE 1
            END,
            updated_at DESC,
            id DESC
         LIMIT 1"
    );

    $statement->execute(array($tenantId));

    return (int)($statement->fetchColumn() ?: 0);
}

function spValue(
    array $input,
    string $key,
    int $maxLength = 0
): string {
    $value = trim(
        (string)($input[$key] ?? '')
    );

    if (
        $maxLength > 0
        && strlen($value) > $maxLength
    ) {
        throw new InvalidArgumentException(
            str_replace(
                '_',
                ' ',
                ucfirst($key)
            ) .
            ' cannot exceed ' .
            $maxLength .
            ' characters.'
        );
    }

    return $value;
}

function spValidate(array $input): array
{
    $data = array(
        'school_name' =>
            spValue($input, 'school_name', 200),
        'school_code' =>
            spValue($input, 'school_code', 50),
        'school_type' =>
            spValue($input, 'school_type', 80),
        'board_name' =>
            spValue($input, 'board_name', 120),
        'affiliation_number' =>
            spValue(
                $input,
                'affiliation_number',
                100
            ),
        'udise_number' =>
            spValue($input, 'udise_number', 50),
        'established_year' =>
            (int)($input['established_year'] ?? 0),
        'school_motto' =>
            spValue($input, 'school_motto', 250),
        'logo_fit' =>
            strtolower(spValue($input, 'logo_fit', 20) ?: 'contain'),
        'logo_zoom' =>
            (int)($input['logo_zoom'] ?? 100),
        'logo_position_x' =>
            (int)($input['logo_position_x'] ?? 50),
        'logo_position_y' =>
            (int)($input['logo_position_y'] ?? 50),
        'logo_rotation' =>
            (int)($input['logo_rotation'] ?? 0),
        'logo_shape' =>
            strtolower(spValue($input, 'logo_shape', 20) ?: 'rounded'),
        'address_line1' =>
            spValue($input, 'address_line1', 250),
        'address_line2' =>
            spValue($input, 'address_line2', 250),
        'city' =>
            spValue($input, 'city', 100),
        'district' =>
            spValue($input, 'district', 100),
        'state_name' =>
            spValue($input, 'state_name', 100),
        'country_name' =>
            spValue($input, 'country_name', 100),
        'postal_code' =>
            spValue($input, 'postal_code', 15),
        'phone_number' =>
            spValue($input, 'phone_number', 20),
        'alternate_phone' =>
            spValue($input, 'alternate_phone', 20),
        'email_address' =>
            spValue($input, 'email_address', 190),
        'website_url' =>
            spValue($input, 'website_url', 250),
        'principal_name' =>
            spValue($input, 'principal_name', 150),
        'principal_mobile' =>
            spValue($input, 'principal_mobile', 20),
        'principal_email' =>
            spValue($input, 'principal_email', 190),
        'office_contact_name' =>
            spValue(
                $input,
                'office_contact_name',
                150
            ),
        'academic_start_month' =>
            (int)($input['academic_start_month'] ?? 6),
        'currency_code' =>
            spValue($input, 'currency_code', 10),
        'timezone_name' =>
            spValue($input, 'timezone_name', 80),
        'date_format' =>
            spValue($input, 'date_format', 20),
        'notes' =>
            spValue($input, 'notes', 1000)
    );

    foreach (
        [
            'school_name' => 'School Name',
            'address_line1' => 'Address Line 1',
            'city' => 'City',
            'state_name' => 'State',
            'country_name' => 'Country',
            'postal_code' => 'Postal Code',
            'phone_number' => 'Primary Phone',
            'email_address' => 'Email'
        ]
        as $key => $label
    ) {
        if ($data[$key] === '') {
            throw new InvalidArgumentException(
                $label . ' is required.'
            );
        }
    }

    if (
        !filter_var(
            $data['email_address'],
            FILTER_VALIDATE_EMAIL
        )
    ) {
        throw new InvalidArgumentException(
            'School Email is invalid.'
        );
    }

    if (
        $data['principal_email'] !== ''
        && !filter_var(
            $data['principal_email'],
            FILTER_VALIDATE_EMAIL
        )
    ) {
        throw new InvalidArgumentException(
            'Principal Email is invalid.'
        );
    }

    if (
        $data['website_url'] !== ''
        && !filter_var(
            $data['website_url'],
            FILTER_VALIDATE_URL
        )
    ) {
        throw new InvalidArgumentException(
            'Website URL is invalid.'
        );
    }

    if (
        $data['established_year'] !== 0
        && (
            $data['established_year'] < 1800
            || $data['established_year'] > 2100
        )
    ) {
        throw new InvalidArgumentException(
            'Established Year must be between 1800 and 2100.'
        );
    }

    if (
        $data['academic_start_month'] < 1
        || $data['academic_start_month'] > 12
    ) {
        throw new InvalidArgumentException(
            'Academic Start Month is invalid.'
        );
    }

    if (!in_array($data['logo_fit'], array('contain', 'cover'), true)) {
        throw new InvalidArgumentException(
            'Logo Fit option is invalid.'
        );
    }

    if ($data['logo_zoom'] < 50 || $data['logo_zoom'] > 200) {
        throw new InvalidArgumentException(
            'Logo Zoom must be between 50% and 200%.'
        );
    }

    if (
        $data['logo_position_x'] < 0
        || $data['logo_position_x'] > 100
        || $data['logo_position_y'] < 0
        || $data['logo_position_y'] > 100
    ) {
        throw new InvalidArgumentException(
            'Logo Position must be between 0 and 100.'
        );
    }

    if ($data['logo_rotation'] < -180 || $data['logo_rotation'] > 180) {
        throw new InvalidArgumentException(
            'Logo Rotation must be between -180 and 180 degrees.'
        );
    }

    if (
        !in_array(
            $data['logo_shape'],
            array('square', 'rounded', 'circle'),
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Logo Shape option is invalid.'
        );
    }

    return $data;
}

function spUploadLogo(
    array $file,
    int $tenantId
): string {
    $error = (int)(
        $file['error'] ?? UPLOAD_ERR_NO_FILE
    );

    if ($error === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    if ($error !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException(
            'School logo upload failed.'
        );
    }

    $size = (int)($file['size'] ?? 0);

    if ($size <= 0 || $size > 2 * 1024 * 1024) {
        throw new InvalidArgumentException(
            'School logo must be below 2 MB.'
        );
    }

    $temporaryPath = (string)(
        $file['tmp_name'] ?? ''
    );

    if (
        $temporaryPath === ''
        || !is_uploaded_file($temporaryPath)
    ) {
        throw new InvalidArgumentException(
            'School logo upload is invalid.'
        );
    }

    $mimeType = mime_content_type(
        $temporaryPath
    );

    $extensions = array(
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    );

    if (!isset($extensions[$mimeType])) {
        throw new InvalidArgumentException(
            'School logo must be JPG, PNG or WEBP.'
        );
    }

    $relativeDirectory =
        'uploads/school-profile/' .
        $tenantId;

    $absoluteDirectory =
        dirname(__DIR__) .
        '/' .
        $relativeDirectory;

    if (
        !is_dir($absoluteDirectory)
        && !mkdir(
            $absoluteDirectory,
            0775,
            true
        )
        && !is_dir($absoluteDirectory)
    ) {
        throw new RuntimeException(
            'Unable to create the School Profile upload folder.',
            500
        );
    }

    $fileName =
        'school-logo-' .
        time() .
        '-' .
        bin2hex(random_bytes(4)) .
        '.' .
        $extensions[$mimeType];

    $absolutePath =
        $absoluteDirectory .
        '/' .
        $fileName;

    if (
        !move_uploaded_file(
            $temporaryPath,
            $absolutePath
        )
    ) {
        throw new RuntimeException(
            'Unable to save the School Logo.',
            500
        );
    }

    return $relativeDirectory .
        '/' .
        $fileName;
}

function spDeleteLogo(string $relativePath): void
{
    $relativePath = trim($relativePath);

    if ($relativePath === '') {
        return;
    }

    $normalized = str_replace(
        array('../', '..\\'),
        '',
        $relativePath
    );

    $absolutePath =
        dirname(__DIR__) .
        '/' .
        ltrim($normalized, '/');

    if (
        is_file($absolutePath)
        && strpos(
            realpath($absolutePath) ?: '',
            realpath(
                dirname(__DIR__) .
                '/uploads/school-profile'
            ) ?: ''
        ) === 0
    ) {
        @unlink($absolutePath);
    }
}

/**
 * Synchronize older branding sources after the canonical School Profile saves.
 * This keeps legacy pages from showing stale school name/logo/tagline values.
 */
function spSyncLegacyBranding(PDO $pdo, int $tenantId, array $profile): void
{
    try {
        if (spTableExists($pdo, 'tenants')) {
            $sets = array();
            $params = array();

            if (spColumnExists($pdo, 'tenants', 'school_name')) {
                $sets[] = 'school_name = ?';
                $params[] = (string)($profile['school_name'] ?? '');
            } elseif (spColumnExists($pdo, 'tenants', 'name')) {
                $sets[] = 'name = ?';
                $params[] = (string)($profile['school_name'] ?? '');
            }

            if (spColumnExists($pdo, 'tenants', 'logo_path')) {
                $sets[] = 'logo_path = ?';
                $params[] = ($profile['logo_path'] ?? '') !== ''
                    ? (string)$profile['logo_path']
                    : null;
            }

            if ($sets) {
                $params[] = $tenantId;
                $statement = $pdo->prepare(
                    'UPDATE tenants SET ' . implode(', ', $sets) . ' WHERE id = ?'
                );
                $statement->execute($params);
            }
        }

        if (spTableExists($pdo, 'tenant_branding')) {
            $sets = array();
            $params = array();

            if (spColumnExists($pdo, 'tenant_branding', 'school_name')) {
                $sets[] = 'school_name = ?';
                $params[] = (string)($profile['school_name'] ?? '');
            }
            if (spColumnExists($pdo, 'tenant_branding', 'tagline')) {
                $sets[] = 'tagline = ?';
                $params[] = (string)($profile['school_motto'] ?? '');
            }
            if (spColumnExists($pdo, 'tenant_branding', 'logo_path')) {
                $sets[] = 'logo_path = ?';
                $params[] = ($profile['logo_path'] ?? '') !== ''
                    ? (string)$profile['logo_path']
                    : null;
            }

            if ($sets) {
                $where = 'tenant_id = ?';
                $params[] = $tenantId;
                if (spColumnExists($pdo, 'tenant_branding', 'is_active')) {
                    $where .= ' AND is_active = 1';
                }
                $statement = $pdo->prepare(
                    'UPDATE tenant_branding SET ' . implode(', ', $sets) . ' WHERE ' . $where
                );
                $statement->execute($params);
            }
        }
    } catch (Throwable $exception) {
        error_log('School Profile legacy branding sync: ' . $exception->getMessage());
    }

    $_SESSION['school_name'] = (string)($profile['school_name'] ?? '');
    $_SESSION['school_logo'] = (string)($profile['logo_path'] ?? '');
    $_SESSION['school_motto'] = (string)($profile['school_motto'] ?? '');
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    spOut(
        false,
        'Database connection is missing.',
        array(
            'build' => SCHOOL_PROFILE_BUILD
        ),
        500
    );
}

$scope = spScope();

if (
    $scope['tenant_id'] <= 0
    || $scope['user_id'] <= 0
) {
    spOut(
        false,
        'Tenant or user session is missing.',
        array(
            'build' => SCHOOL_PROFILE_BUILD
        ),
        401
    );
}

if (
    empty($_SESSION['school_profile_csrf'])
    || !is_string(
        $_SESSION['school_profile_csrf']
    )
) {
    $_SESSION['school_profile_csrf'] =
        bin2hex(random_bytes(32));
}

$action = strtolower(
    trim(
        (string)(
            $_POST['action']
            ?? $_GET['action']
            ?? 'get'
        )
    )
);

try {
    spEnsureSchema($pdo);

    if ($action === 'get') {
        spOut(
            true,
            'School Profile loaded.',
            array(
                'profile' => spProfile(
                    $pdo,
                    $scope
                ),
                'csrf_token' =>
                    $_SESSION[
                        'school_profile_csrf'
                    ],
                'build' => SCHOOL_PROFILE_BUILD
            )
        );
    }

    if ($action === 'save') {
        spCsrf($_POST);

        $data = spValidate($_POST);
        $currentProfile = spProfile(
            $pdo,
            $scope
        );

        $oldLogoPath = (string)(
            $currentProfile['logo_path'] ?? ''
        );

        $newLogoPath = '';

        if (
            isset($_FILES['school_logo'])
            && is_array($_FILES['school_logo'])
        ) {
            $newLogoPath = spUploadLogo(
                $_FILES['school_logo'],
                $scope['tenant_id']
            );
        }

        $removeLogo =
            (string)(
                $_POST['remove_logo'] ?? '0'
            ) === '1';

        $finalLogoPath = $oldLogoPath;

        if ($removeLogo) {
            $finalLogoPath = '';
        }

        if ($newLogoPath !== '') {
            $finalLogoPath = $newLogoPath;
        }

        /*
         * Persist one canonical School Profile per tenant.
         *
         * Do not rely on ON DUPLICATE KEY with nullable branch_id:
         * MySQL permits multiple NULL values in a unique index, causing
         * duplicate rows and inconsistent reload behaviour.
         */
        $existingProfileId = spExistingProfileId(
            $pdo,
            $scope['tenant_id']
        );

        $pdo->beginTransaction();

        $values = array(
            $data['school_name'],
            $data['school_code'] ?: null,
            $data['school_type'] ?: null,
            $data['board_name'] ?: null,
            $data['affiliation_number'] ?: null,
            $data['udise_number'] ?: null,
            $data['established_year'] > 0
                ? $data['established_year']
                : null,
            $data['school_motto'] ?: null,
            $finalLogoPath ?: null,
            $data['logo_fit'],
            $data['logo_zoom'],
            $data['logo_position_x'],
            $data['logo_position_y'],
            $data['logo_rotation'],
            $data['logo_shape'],
            $data['address_line1'],
            $data['address_line2'] ?: null,
            $data['city'],
            $data['district'] ?: null,
            $data['state_name'],
            $data['country_name'],
            $data['postal_code'],
            $data['phone_number'],
            $data['alternate_phone'] ?: null,
            $data['email_address'],
            $data['website_url'] ?: null,
            $data['principal_name'] ?: null,
            $data['principal_mobile'] ?: null,
            $data['principal_email'] ?: null,
            $data['office_contact_name'] ?: null,
            $data['academic_start_month'],
            $data['currency_code'] ?: 'INR',
            $data['timezone_name']
                ?: 'Asia/Kolkata',
            $data['date_format'] ?: 'd-m-Y',
            $data['notes'] ?: null,
            $scope['user_id']
        );

        if ($existingProfileId > 0) {
            $statement = $pdo->prepare(
                "UPDATE school_profile SET
                    branch_id = NULL,
                    school_name = ?,
                    school_code = ?,
                    school_type = ?,
                    board_name = ?,
                    affiliation_number = ?,
                    udise_number = ?,
                    established_year = ?,
                    school_motto = ?,
                    logo_path = ?,
                    logo_fit = ?,
                    logo_zoom = ?,
                    logo_position_x = ?,
                    logo_position_y = ?,
                    logo_rotation = ?,
                    logo_shape = ?,
                    address_line1 = ?,
                    address_line2 = ?,
                    city = ?,
                    district = ?,
                    state_name = ?,
                    country_name = ?,
                    postal_code = ?,
                    phone_number = ?,
                    alternate_phone = ?,
                    email_address = ?,
                    website_url = ?,
                    principal_name = ?,
                    principal_mobile = ?,
                    principal_email = ?,
                    office_contact_name = ?,
                    academic_start_month = ?,
                    currency_code = ?,
                    timezone_name = ?,
                    date_format = ?,
                    notes = ?,
                    updated_by = ?
                 WHERE id = ?
                   AND tenant_id = ?"
            );

            $statement->execute(array_merge(
                $values,
                array(
                    $existingProfileId,
                    $scope['tenant_id']
                )
            ));
        } else {
            $statement = $pdo->prepare(
                "INSERT INTO school_profile (
                    tenant_id,
                    branch_id,
                    school_name,
                    school_code,
                    school_type,
                    board_name,
                    affiliation_number,
                    udise_number,
                    established_year,
                    school_motto,
                    logo_path,
                    logo_fit,
                    logo_zoom,
                    logo_position_x,
                    logo_position_y,
                    logo_rotation,
                    logo_shape,
                    address_line1,
                    address_line2,
                    city,
                    district,
                    state_name,
                    country_name,
                    postal_code,
                    phone_number,
                    alternate_phone,
                    email_address,
                    website_url,
                    principal_name,
                    principal_mobile,
                    principal_email,
                    office_contact_name,
                    academic_start_month,
                    currency_code,
                    timezone_name,
                    date_format,
                    notes,
                    created_by,
                    updated_by
                 ) VALUES (
                    ?, NULL,
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?
                 )"
            );

            $statement->execute(array_merge(
                array($scope['tenant_id']),
                $values,
                array($scope['user_id'])
            ));
        }

        $pdo->commit();

        $savedProfile = spProfile($pdo, $scope);
        spSyncLegacyBranding(
            $pdo,
            $scope['tenant_id'],
            $savedProfile
        );

        if (
            $oldLogoPath !== ''
            && $oldLogoPath !== $finalLogoPath
        ) {
            spDeleteLogo($oldLogoPath);
        }

        spOut(
            true,
            'School Profile saved successfully.',
            array(
                'profile' => $savedProfile,
                'csrf_token' =>
                    $_SESSION[
                        'school_profile_csrf'
                    ],
                'build' => SCHOOL_PROFILE_BUILD
            )
        );
    }

    spOut(
        false,
        'School Profile action is invalid.',
        array(
            'build' => SCHOOL_PROFILE_BUILD
        ),
        400
    );
} catch (InvalidArgumentException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    spOut(
        false,
        $exception->getMessage(),
        array(
            'build' => SCHOOL_PROFILE_BUILD
        ),
        422
    );
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

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
        'School Profile [' .
        SCHOOL_PROFILE_BUILD .
        ']: ' .
        $exception->getMessage()
    );

    spOut(
        false,
        $local
            ? 'Database operation failed [' .
                SCHOOL_PROFILE_BUILD .
                ']: ' .
                $exception->getMessage()
            : 'Database operation failed.',
        array(
            'build' => SCHOOL_PROFILE_BUILD
        ),
        500
    );
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'School Profile [' .
        SCHOOL_PROFILE_BUILD .
        ']: ' .
        $exception->getMessage()
    );

    spOut(
        false,
        'School Profile request failed [' .
        SCHOOL_PROFILE_BUILD .
        ']: ' .
        $exception->getMessage(),
        array(
            'build' => SCHOOL_PROFILE_BUILD
        ),
        500
    );
}

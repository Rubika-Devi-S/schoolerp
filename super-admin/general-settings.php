<?php
declare(strict_types=1);

/* Build: 2026-08-15-super-admin-general-settings-extended-v3 */

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/bootstrap.php';

require_login();

$pageTitle = 'General Settings';
$pageKey = 'platform_general_settings';
$sidebarFile = __DIR__ . '/sidebar.php';

if (!current_user_has_platform_role()) {
    http_response_code(403);
    exit('Access denied.');
}

$baseUrl = defined('BASE_URL')
    ? rtrim((string)BASE_URL, '/') . '/'
    : '../';

$csrfToken = function_exists('csrfToken')
    ? (string)csrfToken()
    : (
        function_exists('csrf_token')
            ? (string)csrf_token()
            : ''
    );

function pgsJson(
    bool $success,
    string $message = '',
    array $data = [],
    int $status = 200
): never {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

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


function pgsColumnExists(PDO $pdo, string $column): bool
{
    $statement = $pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = 'platform_general_settings'
           AND column_name = :column_name"
    );

    $statement->execute([
        'column_name' => $column,
    ]);

    return (int)$statement->fetchColumn() > 0;
}

function pgsEnsureColumn(
    PDO $pdo,
    string $column,
    string $definition
): void {
    if (pgsColumnExists($pdo, $column)) {
        return;
    }

    if (
        preg_match('/^[a-z0-9_]+$/i', $column) !== 1
    ) {
        throw new RuntimeException(
            'Invalid General Settings schema column.'
        );
    }

    $pdo->exec(
        "ALTER TABLE platform_general_settings
         ADD COLUMN `{$column}` {$definition}"
    );
}

function pgsPlatformUpload(
    string $projectRoot,
    ?array $upload,
    string $currentPath,
    string $prefix,
    int $maxBytes,
    array $allowedMime
): string {
    if (
        !is_array($upload)
        || (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE)
            === UPLOAD_ERR_NO_FILE
    ) {
        return $currentPath;
    }

    $uploadError = (int)(
        $upload['error']
        ?? UPLOAD_ERR_NO_FILE
    );

    if ($uploadError !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException(
            'Unable to upload the selected image.'
        );
    }

    $uploadSize = (int)($upload['size'] ?? 0);

    if (
        $uploadSize <= 0
        || $uploadSize > $maxBytes
    ) {
        throw new InvalidArgumentException(
            'The selected image is too large.'
        );
    }

    $temporaryPath = (string)(
        $upload['tmp_name']
        ?? ''
    );

    if (
        $temporaryPath === ''
        || !is_uploaded_file($temporaryPath)
    ) {
        throw new InvalidArgumentException(
            'Invalid image upload.'
        );
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($temporaryPath);

    if (!isset($allowedMime[$mime])) {
        throw new InvalidArgumentException(
            'Unsupported image format.'
        );
    }

    $uploadDirectory =
        $projectRoot . '/uploads/platform';

    if (
        !is_dir($uploadDirectory)
        && !mkdir(
            $uploadDirectory,
            0775,
            true
        )
        && !is_dir($uploadDirectory)
    ) {
        throw new RuntimeException(
            'Unable to create the platform upload folder.'
        );
    }

    $fileName =
        $prefix
        . '-'
        . bin2hex(random_bytes(8))
        . '.'
        . $allowedMime[$mime];

    $destination =
        $uploadDirectory
        . '/'
        . $fileName;

    if (
        !move_uploaded_file(
            $temporaryPath,
            $destination
        )
    ) {
        throw new RuntimeException(
            'Unable to save the uploaded image.'
        );
    }

    pgsDeleteBrandImage(
        $projectRoot,
        $currentPath
    );

    return 'uploads/platform/' . $fileName;
}

function pgsEnsureSchema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS platform_general_settings (
            id TINYINT UNSIGNED NOT NULL DEFAULT 1,
            topbar_brand_name VARCHAR(120) NOT NULL DEFAULT 'School ERP',
            topbar_brand_type ENUM('icon','image') NOT NULL DEFAULT 'icon',
            topbar_brand_icon VARCHAR(80) NOT NULL DEFAULT 'school',
            topbar_brand_image_path VARCHAR(255) NULL,
            system_name VARCHAR(120) NOT NULL DEFAULT 'School ERP',
            browser_title VARCHAR(160) NOT NULL DEFAULT 'School ERP',
            favicon_path VARCHAR(255) NULL,
            support_email VARCHAR(190) NULL,
            support_phone VARCHAR(30) NULL,
            timezone VARCHAR(80) NOT NULL DEFAULT 'Asia/Kolkata',
            date_format VARCHAR(30) NOT NULL DEFAULT 'd-m-Y',
            default_rows_per_page SMALLINT UNSIGNED NOT NULL DEFAULT 25,
            login_title VARCHAR(160) NOT NULL DEFAULT 'School ERP Login',
            login_logo_path VARCHAR(255) NULL,
            footer_text VARCHAR(255) NULL,
            maintenance_mode TINYINT(1) NOT NULL DEFAULT 0,
            maintenance_message VARCHAR(500) NULL,
            show_notifications TINYINT(1) NOT NULL DEFAULT 1,
            show_messages TINYINT(1) NOT NULL DEFAULT 1,
            show_profile_menu TINYINT(1) NOT NULL DEFAULT 1,
            updated_by BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci"
    );

    $columns = [
        'system_name' =>
            "VARCHAR(120) NOT NULL DEFAULT 'School ERP' AFTER topbar_brand_image_path",
        'browser_title' =>
            "VARCHAR(160) NOT NULL DEFAULT 'School ERP' AFTER system_name",
        'favicon_path' =>
            "VARCHAR(255) NULL AFTER browser_title",
        'support_email' =>
            "VARCHAR(190) NULL AFTER favicon_path",
        'support_phone' =>
            "VARCHAR(30) NULL AFTER support_email",
        'timezone' =>
            "VARCHAR(80) NOT NULL DEFAULT 'Asia/Kolkata' AFTER support_phone",
        'date_format' =>
            "VARCHAR(30) NOT NULL DEFAULT 'd-m-Y' AFTER timezone",
        'default_rows_per_page' =>
            "SMALLINT UNSIGNED NOT NULL DEFAULT 25 AFTER date_format",
        'login_title' =>
            "VARCHAR(160) NOT NULL DEFAULT 'School ERP Login' AFTER default_rows_per_page",
        'login_logo_path' =>
            "VARCHAR(255) NULL AFTER login_title",
        'footer_text' =>
            "VARCHAR(255) NULL AFTER login_logo_path",
        'maintenance_mode' =>
            "TINYINT(1) NOT NULL DEFAULT 0 AFTER footer_text",
        'maintenance_message' =>
            "VARCHAR(500) NULL AFTER maintenance_mode",
        'show_profile_menu' =>
            "TINYINT(1) NOT NULL DEFAULT 1 AFTER show_messages",
    ];

    foreach ($columns as $column => $definition) {
        pgsEnsureColumn(
            $pdo,
            $column,
            $definition
        );
    }

    $pdo->exec(
        "INSERT IGNORE INTO platform_general_settings
            (
                id,
                topbar_brand_name,
                topbar_brand_type,
                topbar_brand_icon,
                system_name,
                browser_title,
                timezone,
                date_format,
                default_rows_per_page,
                login_title,
                show_notifications,
                show_messages,
                show_profile_menu
            )
         VALUES
            (
                1,
                'School ERP',
                'icon',
                'school',
                'School ERP',
                'School ERP',
                'Asia/Kolkata',
                'd-m-Y',
                25,
                'School ERP Login',
                1,
                1,
                1
            )"
    );
}

function pgsLoad(PDO $pdo): array
{
    pgsEnsureSchema($pdo);

    $statement = $pdo->query(
        "SELECT
            id,
            topbar_brand_name,
            topbar_brand_type,
            topbar_brand_icon,
            topbar_brand_image_path,
            system_name,
            browser_title,
            favicon_path,
            support_email,
            support_phone,
            timezone,
            date_format,
            default_rows_per_page,
            login_title,
            login_logo_path,
            footer_text,
            maintenance_mode,
            maintenance_message,
            show_notifications,
            show_messages,
            show_profile_menu,
            updated_at
         FROM platform_general_settings
         WHERE id = 1
         LIMIT 1"
    );

    $row = $statement->fetch(PDO::FETCH_ASSOC);

    return is_array($row)
        ? $row
        : [
            'id' => 1,
            'topbar_brand_name' => 'School ERP',
            'topbar_brand_type' => 'icon',
            'topbar_brand_icon' => 'school',
            'topbar_brand_image_path' => null,
            'system_name' => 'School ERP',
            'browser_title' => 'School ERP',
            'favicon_path' => null,
            'support_email' => null,
            'support_phone' => null,
            'timezone' => 'Asia/Kolkata',
            'date_format' => 'd-m-Y',
            'default_rows_per_page' => 25,
            'login_title' => 'School ERP Login',
            'login_logo_path' => null,
            'footer_text' => null,
            'maintenance_mode' => 0,
            'maintenance_message' => null,
            'show_notifications' => 1,
            'show_messages' => 1,
            'show_profile_menu' => 1,
            'updated_at' => null,
        ];
}

function pgsDeleteBrandImage(
    string $projectRoot,
    ?string $relativePath
): void {
    $relativePath = trim((string)$relativePath);

    if (
        $relativePath === ''
        || !str_starts_with(
            str_replace('\\', '/', $relativePath),
            'uploads/platform/'
        )
    ) {
        return;
    }

    $absolute = $projectRoot
        . DIRECTORY_SEPARATOR
        . str_replace(
            '/',
            DIRECTORY_SEPARATOR,
            ltrim($relativePath, '/')
        );

    if (is_file($absolute)) {
        @unlink($absolute);
    }
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Database connection unavailable.');
}

try {
    pgsEnsureSchema($pdo);
} catch (Throwable $exception) {
    error_log(
        'super-admin/general-settings.php schema: '
        . $exception->getMessage()
    );

    http_response_code(500);
    exit('Unable to prepare General Settings.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ob_start();

    try {
        if (!current_user_has_platform_role()) {
            pgsJson(false, 'Access denied.', [], 403);
        }

        $action = trim((string)($_POST['action'] ?? ''));

        if ($action !== 'save') {
            pgsJson(false, 'Invalid General Settings action.', [], 400);
        }

        $postedCsrf = trim((string)($_POST['csrf_token'] ?? ''));
        $csrfValid = function_exists('csrf_is_valid')
            ? csrf_is_valid($postedCsrf)
            : (
                function_exists('verify_csrf_token')
                    ? verify_csrf_token($postedCsrf)
                    : (
                        $postedCsrf !== ''
                        && isset($_SESSION['csrf_token'])
                        && hash_equals(
                            (string)$_SESSION['csrf_token'],
                            $postedCsrf
                        )
                    )
            );

        if (!$csrfValid) {
            pgsJson(false, 'Invalid or expired CSRF token.', [], 419);
        }

        $current = pgsLoad($pdo);

        $brandName = trim(
            (string)($_POST['topbar_brand_name'] ?? '')
        );

        if ($brandName === '') {
            pgsJson(
                false,
                'Sidebar Header Name is required.',
                [],
                422
            );
        }

        if (mb_strlen($brandName) > 120) {
            pgsJson(
                false,
                'Sidebar Header Name must be 120 characters or less.',
                [],
                422
            );
        }

        $brandType = strtolower(
            trim((string)($_POST['topbar_brand_type'] ?? 'icon'))
        );

        if (!in_array($brandType, ['icon', 'image'], true)) {
            $brandType = 'icon';
        }

        $brandIcon = strtolower(
            trim((string)($_POST['topbar_brand_icon'] ?? 'school'))
        );

        if (
            $brandIcon === ''
            || preg_match(
                '/^[a-z0-9][a-z0-9-]{0,79}$/',
                $brandIcon
            ) !== 1
        ) {
            pgsJson(
                false,
                'Enter a valid Lucide icon name such as school, building-2 or graduation-cap.',
                [],
                422
            );
        }

        $showNotifications = !empty($_POST['show_notifications'])
            ? 1
            : 0;

        $showMessages = !empty($_POST['show_messages'])
            ? 1
            : 0;

        $showProfileMenu = !empty($_POST['show_profile_menu'])
            ? 1
            : 0;

        $systemName = trim(
            (string)($_POST['system_name'] ?? 'School ERP')
        );

        if ($systemName === '' || mb_strlen($systemName) > 120) {
            pgsJson(
                false,
                'System Name is required and must be 120 characters or less.',
                [],
                422
            );
        }

        $browserTitle = trim(
            (string)($_POST['browser_title'] ?? $systemName)
        );

        if ($browserTitle === '' || mb_strlen($browserTitle) > 160) {
            pgsJson(
                false,
                'Browser Title is required and must be 160 characters or less.',
                [],
                422
            );
        }

        $supportEmail = trim(
            (string)($_POST['support_email'] ?? '')
        );

        if (
            $supportEmail !== ''
            && filter_var(
                $supportEmail,
                FILTER_VALIDATE_EMAIL
            ) === false
        ) {
            pgsJson(
                false,
                'Enter a valid Support Email.',
                [],
                422
            );
        }

        $supportPhone = trim(
            (string)($_POST['support_phone'] ?? '')
        );

        if (mb_strlen($supportPhone) > 30) {
            pgsJson(
                false,
                'Support Phone must be 30 characters or less.',
                [],
                422
            );
        }

        $timezone = trim(
            (string)($_POST['timezone'] ?? 'Asia/Kolkata')
        );

        if (
            !in_array(
                $timezone,
                timezone_identifiers_list(),
                true
            )
        ) {
            pgsJson(
                false,
                'Select a valid Time Zone.',
                [],
                422
            );
        }

        $dateFormat = trim(
            (string)($_POST['date_format'] ?? 'd-m-Y')
        );

        $allowedDateFormats = [
            'd-m-Y',
            'd/m/Y',
            'Y-m-d',
            'd M Y',
            'M d, Y',
        ];

        if (
            !in_array(
                $dateFormat,
                $allowedDateFormats,
                true
            )
        ) {
            $dateFormat = 'd-m-Y';
        }

        $defaultRowsPerPage = (int)(
            $_POST['default_rows_per_page']
            ?? 25
        );

        if (
            !in_array(
                $defaultRowsPerPage,
                [10, 25, 50, 100, 200],
                true
            )
        ) {
            $defaultRowsPerPage = 25;
        }

        $loginTitle = trim(
            (string)($_POST['login_title'] ?? 'School ERP Login')
        );

        if ($loginTitle === '' || mb_strlen($loginTitle) > 160) {
            pgsJson(
                false,
                'Login Page Title is required and must be 160 characters or less.',
                [],
                422
            );
        }

        $footerText = trim(
            (string)($_POST['footer_text'] ?? '')
        );

        if (mb_strlen($footerText) > 255) {
            pgsJson(
                false,
                'Footer Text must be 255 characters or less.',
                [],
                422
            );
        }

        $maintenanceMode =
            !empty($_POST['maintenance_mode'])
                ? 1
                : 0;

        $maintenanceMessage = trim(
            (string)(
                $_POST['maintenance_message']
                ?? ''
            )
        );

        if (mb_strlen($maintenanceMessage) > 500) {
            pgsJson(
                false,
                'Maintenance Message must be 500 characters or less.',
                [],
                422
            );
        }

        $brandImagePath = trim(
            (string)($current['topbar_brand_image_path'] ?? '')
        );

        if (!empty($_POST['remove_brand_image'])) {
            pgsDeleteBrandImage(
                $projectRoot,
                $brandImagePath
            );

            $brandImagePath = '';
        }

        $upload = $_FILES['topbar_brand_image'] ?? null;

        if (
            is_array($upload)
            && (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE)
                !== UPLOAD_ERR_NO_FILE
        ) {
            $uploadError = (int)(
                $upload['error']
                ?? UPLOAD_ERR_NO_FILE
            );

            if ($uploadError !== UPLOAD_ERR_OK) {
                pgsJson(
                    false,
                    'Unable to upload the Sidebar image.',
                    [],
                    422
                );
            }

            $uploadSize = (int)($upload['size'] ?? 0);

            if (
                $uploadSize <= 0
                || $uploadSize > 2 * 1024 * 1024
            ) {
                pgsJson(
                    false,
                    'Sidebar image must be 2 MB or smaller.',
                    [],
                    422
                );
            }

            $temporaryPath = (string)($upload['tmp_name'] ?? '');

            if (
                $temporaryPath === ''
                || !is_uploaded_file($temporaryPath)
            ) {
                pgsJson(
                    false,
                    'Invalid Sidebar image upload.',
                    [],
                    422
                );
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = (string)$finfo->file($temporaryPath);

            $extensions = [
                'image/png' => 'png',
                'image/jpeg' => 'jpg',
                'image/webp' => 'webp',
            ];

            if (!isset($extensions[$mime])) {
                pgsJson(
                    false,
                    'Use PNG, JPG or WEBP for the Sidebar image.',
                    [],
                    422
                );
            }

            $uploadDirectory = $projectRoot
                . '/uploads/platform';

            if (
                !is_dir($uploadDirectory)
                && !mkdir(
                    $uploadDirectory,
                    0775,
                    true
                )
                && !is_dir($uploadDirectory)
            ) {
                pgsJson(
                    false,
                    'Unable to create the Sidebar upload folder.',
                    [],
                    500
                );
            }

            $fileName = 'topbar-brand-'
                . bin2hex(random_bytes(8))
                . '.'
                . $extensions[$mime];

            $destination = $uploadDirectory
                . '/'
                . $fileName;

            if (
                !move_uploaded_file(
                    $temporaryPath,
                    $destination
                )
            ) {
                pgsJson(
                    false,
                    'Unable to save the Sidebar image.',
                    [],
                    500
                );
            }

            pgsDeleteBrandImage(
                $projectRoot,
                $brandImagePath
            );

            $brandImagePath =
                'uploads/platform/'
                . $fileName;
        }

        if (
            $brandType === 'image'
            && $brandImagePath === ''
        ) {
            pgsJson(
                false,
                'Upload a Sidebar image before selecting Image mode.',
                [],
                422
            );
        }

        $faviconPath = trim(
            (string)($current['favicon_path'] ?? '')
        );

        if (!empty($_POST['remove_favicon'])) {
            pgsDeleteBrandImage(
                $projectRoot,
                $faviconPath
            );
            $faviconPath = '';
        }

        $faviconPath = pgsPlatformUpload(
            $projectRoot,
            $_FILES['favicon'] ?? null,
            $faviconPath,
            'favicon',
            1024 * 1024,
            [
                'image/png' => 'png',
                'image/jpeg' => 'jpg',
                'image/webp' => 'webp',
                'image/x-icon' => 'ico',
                'image/vnd.microsoft.icon' => 'ico',
                'application/octet-stream' => 'ico',
            ]
        );

        $loginLogoPath = trim(
            (string)($current['login_logo_path'] ?? '')
        );

        if (!empty($_POST['remove_login_logo'])) {
            pgsDeleteBrandImage(
                $projectRoot,
                $loginLogoPath
            );
            $loginLogoPath = '';
        }

        $loginLogoPath = pgsPlatformUpload(
            $projectRoot,
            $_FILES['login_logo'] ?? null,
            $loginLogoPath,
            'login-logo',
            2 * 1024 * 1024,
            [
                'image/png' => 'png',
                'image/jpeg' => 'jpg',
                'image/webp' => 'webp',
            ]
        );

        $statement = $pdo->prepare(
            "INSERT INTO platform_general_settings
                (
                    id,
                    topbar_brand_name,
                    topbar_brand_type,
                    topbar_brand_icon,
                    topbar_brand_image_path,
                    system_name,
                    browser_title,
                    favicon_path,
                    support_email,
                    support_phone,
                    timezone,
                    date_format,
                    default_rows_per_page,
                    login_title,
                    login_logo_path,
                    footer_text,
                    maintenance_mode,
                    maintenance_message,
                    show_notifications,
                    show_messages,
                    show_profile_menu,
                    updated_by
                )
             VALUES
                (
                    1,
                    :brand_name,
                    :brand_type,
                    :brand_icon,
                    :brand_image_path,
                    :system_name,
                    :browser_title,
                    :favicon_path,
                    :support_email,
                    :support_phone,
                    :timezone,
                    :date_format,
                    :default_rows_per_page,
                    :login_title,
                    :login_logo_path,
                    :footer_text,
                    :maintenance_mode,
                    :maintenance_message,
                    :show_notifications,
                    :show_messages,
                    :show_profile_menu,
                    :updated_by
                )
             ON DUPLICATE KEY UPDATE
                topbar_brand_name = VALUES(topbar_brand_name),
                topbar_brand_type = VALUES(topbar_brand_type),
                topbar_brand_icon = VALUES(topbar_brand_icon),
                topbar_brand_image_path = VALUES(topbar_brand_image_path),
                system_name = VALUES(system_name),
                browser_title = VALUES(browser_title),
                favicon_path = VALUES(favicon_path),
                support_email = VALUES(support_email),
                support_phone = VALUES(support_phone),
                timezone = VALUES(timezone),
                date_format = VALUES(date_format),
                default_rows_per_page = VALUES(default_rows_per_page),
                login_title = VALUES(login_title),
                login_logo_path = VALUES(login_logo_path),
                footer_text = VALUES(footer_text),
                maintenance_mode = VALUES(maintenance_mode),
                maintenance_message = VALUES(maintenance_message),
                show_notifications = VALUES(show_notifications),
                show_messages = VALUES(show_messages),
                show_profile_menu = VALUES(show_profile_menu),
                updated_by = VALUES(updated_by)"
        );

        $statement->execute([
            'brand_name' => $brandName,
            'brand_type' => $brandType,
            'brand_icon' => $brandIcon,
            'brand_image_path' =>
                $brandImagePath !== ''
                    ? $brandImagePath
                    : null,
            'system_name' => $systemName,
            'browser_title' => $browserTitle,
            'favicon_path' =>
                $faviconPath !== ''
                    ? $faviconPath
                    : null,
            'support_email' =>
                $supportEmail !== ''
                    ? $supportEmail
                    : null,
            'support_phone' =>
                $supportPhone !== ''
                    ? $supportPhone
                    : null,
            'timezone' => $timezone,
            'date_format' => $dateFormat,
            'default_rows_per_page' =>
                $defaultRowsPerPage,
            'login_title' => $loginTitle,
            'login_logo_path' =>
                $loginLogoPath !== ''
                    ? $loginLogoPath
                    : null,
            'footer_text' =>
                $footerText !== ''
                    ? $footerText
                    : null,
            'maintenance_mode' => $maintenanceMode,
            'maintenance_message' =>
                $maintenanceMessage !== ''
                    ? $maintenanceMessage
                    : null,
            'show_notifications' => $showNotifications,
            'show_messages' => $showMessages,
            'show_profile_menu' => $showProfileMenu,
            'updated_by' =>
                (int)($_SESSION['user_id'] ?? 0)
                    ?: null,
        ]);

        if (school_table_exists($pdo, 'activity_logs')) {
            try {
                $log = $pdo->prepare(
                    "INSERT INTO activity_logs
                        (
                            tenant_id,
                            branch_id,
                            user_id,
                            role_id,
                            module_name,
                            action_key,
                            table_name,
                            record_id,
                            new_values,
                            description,
                            ip_address,
                            user_agent
                        )
                     VALUES
                        (
                            :tenant_id,
                            NULL,
                            :user_id,
                            :role_id,
                            'Platform General Settings',
                            'update',
                            'platform_general_settings',
                            1,
                            :new_values,
                            :description,
                            :ip_address,
                            :user_agent
                        )"
                );

                $log->execute([
                    'tenant_id' =>
                        max(
                            1,
                            (int)(
                                $_SESSION['tenant_id']
                                ?? $_SESSION['school_id']
                                ?? 1
                            )
                        ),
                    'user_id' =>
                        (int)($_SESSION['user_id'] ?? 0)
                            ?: null,
                    'role_id' =>
                        (int)($_SESSION['role_id'] ?? 0)
                            ?: null,
                    'new_values' => json_encode(
                        [
                            'topbar_brand_name' => $brandName,
                            'topbar_brand_type' => $brandType,
                            'topbar_brand_icon' => $brandIcon,
                            'topbar_brand_image_path' =>
                                $brandImagePath,
                            'show_notifications' =>
                                $showNotifications,
                            'show_messages' =>
                                $showMessages,
                            'show_profile_menu' =>
                                $showProfileMenu,
                            'system_name' => $systemName,
                            'browser_title' => $browserTitle,
                            'favicon_path' => $faviconPath,
                            'support_email' => $supportEmail,
                            'support_phone' => $supportPhone,
                            'timezone' => $timezone,
                            'date_format' => $dateFormat,
                            'default_rows_per_page' =>
                                $defaultRowsPerPage,
                            'login_title' => $loginTitle,
                            'login_logo_path' =>
                                $loginLogoPath,
                            'footer_text' => $footerText,
                            'maintenance_mode' =>
                                $maintenanceMode,
                            'maintenance_message' =>
                                $maintenanceMessage,
                        ],
                        JSON_UNESCAPED_UNICODE
                        | JSON_UNESCAPED_SLASHES
                    ),
                    'description' =>
                        'Updated platform General Settings, branding, support, regional and system controls.',
                    'ip_address' =>
                        substr(
                            (string)($_SERVER['REMOTE_ADDR'] ?? ''),
                            0,
                            45
                        )
                        ?: null,
                    'user_agent' =>
                        substr(
                            (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
                            0,
                            1000
                        )
                        ?: null,
                ]);
            } catch (Throwable $logException) {
                error_log(
                    'General Settings audit: '
                    . $logException->getMessage()
                );
            }
        }

        pgsJson(
            true,
            'General Settings saved successfully.',
            [
                'settings' => pgsLoad($pdo),
            ]
        );
    } catch (Throwable $exception) {
        error_log(
            'super-admin/general-settings.php save: '
            . $exception->getMessage()
        );

        pgsJson(
            false,
            'Unable to save General Settings.',
            [],
            500
        );
    }
}

$settings = pgsLoad($pdo);

$brandName = trim(
    (string)($settings['topbar_brand_name'] ?? 'School ERP')
);

$brandType = strtolower(
    trim(
        (string)(
            $settings['topbar_brand_type']
            ?? 'icon'
        )
    )
);

$brandIcon = trim(
    (string)($settings['topbar_brand_icon'] ?? 'school')
);

$brandImagePath = trim(
    (string)(
        $settings['topbar_brand_image_path']
        ?? ''
    )
);

$showNotifications =
    (int)($settings['show_notifications'] ?? 1) === 1;

$showMessages =
    (int)($settings['show_messages'] ?? 1) === 1;

$showProfileMenu =
    (int)($settings['show_profile_menu'] ?? 1) === 1;

$systemName = trim(
    (string)($settings['system_name'] ?? 'School ERP')
);

$browserTitle = trim(
    (string)($settings['browser_title'] ?? $systemName)
);

$faviconPath = trim(
    (string)($settings['favicon_path'] ?? '')
);

$supportEmail = trim(
    (string)($settings['support_email'] ?? '')
);

$supportPhone = trim(
    (string)($settings['support_phone'] ?? '')
);

$timezone = trim(
    (string)($settings['timezone'] ?? 'Asia/Kolkata')
);

$dateFormat = trim(
    (string)($settings['date_format'] ?? 'd-m-Y')
);

$defaultRowsPerPage = (int)(
    $settings['default_rows_per_page']
    ?? 25
);

$loginTitle = trim(
    (string)($settings['login_title'] ?? 'School ERP Login')
);

$loginLogoPath = trim(
    (string)($settings['login_logo_path'] ?? '')
);

$footerText = trim(
    (string)($settings['footer_text'] ?? '')
);

$maintenanceMode =
    (int)($settings['maintenance_mode'] ?? 0) === 1;

$maintenanceMessage = trim(
    (string)($settings['maintenance_message'] ?? '')
);

$brandImageUrl = $brandImagePath !== ''
    ? $baseUrl . ltrim($brandImagePath, '/')
    : '';

$faviconUrl = $faviconPath !== ''
    ? $baseUrl . ltrim($faviconPath, '/')
    : '';

$loginLogoUrl = $loginLogoPath !== ''
    ? $baseUrl . ltrim($loginLogoPath, '/')
    : '';

$timezoneOptions = [
    'Asia/Kolkata',
    'Asia/Dubai',
    'Asia/Singapore',
    'Asia/Kuala_Lumpur',
    'Asia/Colombo',
    'UTC',
    'Europe/London',
    'America/New_York',
    'America/Chicago',
    'America/Los_Angeles',
];

if (
    $timezone !== ''
    && !in_array(
        $timezone,
        $timezoneOptions,
        true
    )
) {
    array_unshift(
        $timezoneOptions,
        $timezone
    );
}

require $projectRoot . '/includes/layout-start.php';
?>

<style>
.gs-page{display:grid;gap:16px}
.gs-grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(300px,.65fr);gap:16px;align-items:start}
.gs-card{padding:18px}
.gs-card-title{display:flex;align-items:center;gap:10px;margin-bottom:4px}
.gs-card-title i{width:18px;height:18px;color:var(--brand-1,#6747e8)}
.gs-card-title h3{margin:0;font-size:14px;font-weight:850}
.gs-card-subtitle{margin:0 0 18px;color:var(--text-muted,#64748b);font-size:10px}
.gs-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.gs-field.full{grid-column:1/-1}
.gs-field label{display:block;margin-bottom:6px;font-size:10px;font-weight:800}
.gs-choice{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.gs-choice label{display:flex;align-items:center;gap:9px;margin:0;padding:11px 12px;border:1px solid var(--border-soft,#e7ebf3);border-radius:11px;background:var(--body-bg,#f6f8fc);cursor:pointer}
.gs-choice input{width:17px;height:17px}
.gs-help{margin-top:6px;color:var(--text-muted,#64748b);font-size:9px;line-height:1.45}
.gs-switch-row{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:13px 0;border-bottom:1px solid var(--border-soft,#e7ebf3)}
.gs-switch-row:last-child{border-bottom:0}
.gs-switch-copy strong,.gs-switch-copy small{display:block}
.gs-switch-copy strong{font-size:11px}
.gs-switch-copy small{margin-top:3px;color:var(--text-muted,#64748b);font-size:9px}
.gs-preview{padding:18px;position:sticky;top:16px}
.gs-preview-topbar{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px;border:1px solid var(--border-soft,#e7ebf3);border-radius:12px;background:var(--card-bg,#fff);box-shadow:0 8px 24px rgba(15,23,42,.05)}
.gs-preview-left,.gs-preview-brand,.gs-preview-actions{display:flex;align-items:center;gap:9px;min-width:0}
.gs-preview-menu,.gs-preview-action{width:34px;height:34px;display:grid;place-items:center;border:1px solid var(--border-soft,#e7ebf3);border-radius:10px;background:var(--body-bg,#f6f8fc)}
.gs-preview-menu i,.gs-preview-action i{width:15px;height:15px}
.gs-preview-mark{width:34px;height:34px;display:grid;place-items:center;overflow:hidden;border-radius:10px;background:rgba(103,71,232,.10);color:var(--brand-1,#6747e8);flex:0 0 34px}
.gs-preview-mark img{width:100%;height:100%;object-fit:cover}
.gs-preview-mark i{width:18px;height:18px}
.gs-preview-name{font-size:11px;font-weight:850;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:150px}
.gs-message{display:none;margin:0}
.gs-message.show{display:block}
.gs-image-current{display:flex;align-items:center;gap:10px;margin-top:10px;padding:10px;border:1px solid var(--border-soft,#e7ebf3);border-radius:10px}
.gs-image-current img{width:46px;height:46px;object-fit:cover;border-radius:10px;border:1px solid var(--border-soft,#e7ebf3)}
.gs-image-current small{display:block;color:var(--text-muted,#64748b);font-size:9px}
.gs-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:16px;padding-top:16px;border-top:1px solid var(--border-soft,#e7ebf3)}
.gs-section{margin-top:24px;padding-top:20px;border-top:1px solid var(--border-soft,#e7ebf3)}
.gs-section:first-of-type{margin-top:0;padding-top:0;border-top:0}
.gs-current-file{display:flex;align-items:center;gap:10px;margin-top:10px;padding:10px;border:1px solid var(--border-soft,#e7ebf3);border-radius:10px;background:rgba(99,102,241,.025)}
.gs-current-file img{width:46px;height:46px;object-fit:contain;border-radius:9px;border:1px solid var(--border-soft,#e7ebf3);background:#fff}
.gs-preview-summary{display:grid;gap:9px;margin-top:14px}
.gs-preview-row{display:flex;justify-content:space-between;gap:12px;padding:9px 10px;border:1px solid var(--border-soft,#e7ebf3);border-radius:9px;font-size:10px}
.gs-preview-row span{color:var(--text-muted,#64748b)}
.gs-preview-row strong{text-align:right;overflow-wrap:anywhere}
.gs-warning{margin-top:10px;padding:10px 12px;border-radius:10px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;font-size:9px;line-height:1.5}
@media(max-width:991.98px){.gs-grid{grid-template-columns:1fr}.gs-preview{position:static}}
@media(max-width:767.98px){.gs-form-grid,.gs-choice{grid-template-columns:1fr}}
</style>

<div class="gs-page">
    <div class="page-heading">
        <div>
            <h1 class="page-title">General Settings</h1>
            <p class="page-subtitle">
                Manage platform identity, branding, support, regional defaults, login settings and Super Admin action visibility.
            </p>
        </div>
    </div>

    <div id="gsMessage" class="alert gs-message"></div>

    <div class="gs-grid">
        <form
            id="generalSettingsForm"
            class="ui-card gs-card"
            enctype="multipart/form-data"
            novalidate
        >
            <input type="hidden" name="action" value="save">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($csrfToken) ?>"
            >

            <div class="gs-card-title">
                <i data-lucide="panel-left"></i>
                <h3>Sidebar Header Branding</h3>
            </div>

            <p class="gs-card-subtitle">
                Change the name and logo/icon shown in the Super Admin sidebar header. Existing navigation and layout remain unchanged.
            </p>

            <div class="gs-form-grid">
                <div class="gs-field full">
                    <label for="topbarBrandName">Sidebar Header Name</label>
                    <input
                        id="topbarBrandName"
                        name="topbar_brand_name"
                        class="form-control"
                        maxlength="120"
                        value="<?= e($brandName) ?>"
                        placeholder="School ERP"
                        required
                    >
                </div>

                <div class="gs-field full">
                    <label>Display Type</label>

                    <div class="gs-choice">
                        <label>
                            <input
                                id="brandTypeIcon"
                                type="radio"
                                name="topbar_brand_type"
                                value="icon"
                                <?= $brandType === 'icon' ? 'checked' : '' ?>
                            >
                            <span>
                                <strong>Lucide Icon</strong>
                                <small class="d-block text-muted">
                                    Use an icon such as school or building-2.
                                </small>
                            </span>
                        </label>

                        <label>
                            <input
                                id="brandTypeImage"
                                type="radio"
                                name="topbar_brand_type"
                                value="image"
                                <?= $brandType === 'image' ? 'checked' : '' ?>
                            >
                            <span>
                                <strong>Image / Logo</strong>
                                <small class="d-block text-muted">
                                    Upload PNG, JPG or WEBP.
                                </small>
                            </span>
                        </label>
                    </div>
                </div>

                <div
                    id="iconField"
                    class="gs-field full"
                >
                    <label for="topbarBrandIcon">Lucide Icon Name</label>

                    <input
                        id="topbarBrandIcon"
                        name="topbar_brand_icon"
                        class="form-control"
                        maxlength="80"
                        value="<?= e($brandIcon) ?>"
                        list="topbarIconSuggestions"
                        placeholder="school"
                    >

                    <datalist id="topbarIconSuggestions">
                        <option value="school"></option>
                        <option value="building-2"></option>
                        <option value="graduation-cap"></option>
                        <option value="landmark"></option>
                        <option value="shield-check"></option>
                        <option value="layout-dashboard"></option>
                    </datalist>

                    <div class="gs-help">
                        Any valid Lucide icon name is supported.
                    </div>
                </div>

                <div
                    id="imageField"
                    class="gs-field full"
                >
                    <label for="topbarBrandImage">Sidebar Image / Logo</label>

                    <input
                        id="topbarBrandImage"
                        name="topbar_brand_image"
                        class="form-control"
                        type="file"
                        accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp"
                    >

                    <div class="gs-help">
                        Maximum 2 MB. Recommended square or transparent logo.
                    </div>

                    <?php if ($brandImagePath !== ''): ?>
                        <div
                            id="currentImageBox"
                            class="gs-image-current"
                        >
                            <img
                                src="<?= e($brandImageUrl) ?>"
                                alt="Current Sidebar image"
                            >

                            <div>
                                <strong>Current image</strong>
                                <small>
                                    <?= e(basename($brandImagePath)) ?>
                                </small>

                                <label class="mt-2 mb-0 d-flex align-items-center gap-2">
                                    <input
                                        id="removeBrandImage"
                                        type="checkbox"
                                        name="remove_brand_image"
                                        value="1"
                                    >
                                    Remove current image
                                </label>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>


            <section class="gs-section">
                <div class="gs-card-title">
                    <i data-lucide="settings-2"></i>
                    <h3>Platform Identity & Browser</h3>
                </div>

                <p class="gs-card-subtitle">
                    Store the common platform name, browser title and favicon used by your ERP.
                </p>

                <div class="gs-form-grid">
                    <div class="gs-field">
                        <label for="systemName">System Name</label>
                        <input
                            id="systemName"
                            name="system_name"
                            class="form-control"
                            maxlength="120"
                            value="<?= e($systemName) ?>"
                            required
                        >
                    </div>

                    <div class="gs-field">
                        <label for="browserTitle">Browser Title</label>
                        <input
                            id="browserTitle"
                            name="browser_title"
                            class="form-control"
                            maxlength="160"
                            value="<?= e($browserTitle) ?>"
                            required
                        >
                    </div>

                    <div class="gs-field full">
                        <label for="faviconInput">Favicon</label>
                        <input
                            id="faviconInput"
                            name="favicon"
                            class="form-control"
                            type="file"
                            accept=".ico,.png,.jpg,.jpeg,.webp,image/x-icon,image/png,image/jpeg,image/webp"
                        >
                        <div class="gs-help">
                            Maximum 1 MB. ICO, PNG, JPG or WEBP.
                        </div>

                        <?php if ($faviconPath !== ''): ?>
                            <div class="gs-current-file">
                                <img src="<?= e($faviconUrl) ?>" alt="Current favicon">
                                <div>
                                    <strong>Current favicon</strong>
                                    <small class="d-block text-muted">
                                        <?= e(basename($faviconPath)) ?>
                                    </small>
                                    <label class="mt-2 mb-0 d-flex align-items-center gap-2">
                                        <input
                                            type="checkbox"
                                            name="remove_favicon"
                                            value="1"
                                        >
                                        Remove favicon
                                    </label>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="gs-section">
                <div class="gs-card-title">
                    <i data-lucide="headphones"></i>
                    <h3>Support & Regional Defaults</h3>
                </div>

                <p class="gs-card-subtitle">
                    Configure support contact information and common date/display defaults.
                </p>

                <div class="gs-form-grid">
                    <div class="gs-field">
                        <label for="supportEmail">Support Email</label>
                        <input
                            id="supportEmail"
                            name="support_email"
                            class="form-control"
                            type="email"
                            maxlength="190"
                            value="<?= e($supportEmail) ?>"
                            placeholder="support@example.com"
                        >
                    </div>

                    <div class="gs-field">
                        <label for="supportPhone">Support Phone</label>
                        <input
                            id="supportPhone"
                            name="support_phone"
                            class="form-control"
                            maxlength="30"
                            value="<?= e($supportPhone) ?>"
                            placeholder="+91 98765 43210"
                        >
                    </div>

                    <div class="gs-field">
                        <label for="timezone">Time Zone</label>
                        <select
                            id="timezone"
                            name="timezone"
                            class="form-select"
                        >
                            <?php foreach ($timezoneOptions as $zone): ?>
                                <option
                                    value="<?= e($zone) ?>"
                                    <?= $timezone === $zone ? 'selected' : '' ?>
                                >
                                    <?= e($zone) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="gs-field">
                        <label for="dateFormat">Date Format</label>
                        <select
                            id="dateFormat"
                            name="date_format"
                            class="form-select"
                        >
                            <?php foreach ([
                                'd-m-Y' => 'DD-MM-YYYY',
                                'd/m/Y' => 'DD/MM/YYYY',
                                'Y-m-d' => 'YYYY-MM-DD',
                                'd M Y' => '15 Aug 2026',
                                'M d, Y' => 'Aug 15, 2026',
                            ] as $formatValue => $formatLabel): ?>
                                <option
                                    value="<?= e($formatValue) ?>"
                                    <?= $dateFormat === $formatValue ? 'selected' : '' ?>
                                >
                                    <?= e($formatLabel) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="gs-field full">
                        <label for="defaultRows">Default Rows Per Page</label>
                        <select
                            id="defaultRows"
                            name="default_rows_per_page"
                            class="form-select"
                        >
                            <?php foreach ([10,25,50,100,200] as $rowCount): ?>
                                <option
                                    value="<?= $rowCount ?>"
                                    <?= $defaultRowsPerPage === $rowCount ? 'selected' : '' ?>
                                >
                                    <?= $rowCount ?> rows
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </section>

            <section class="gs-section">
                <div class="gs-card-title">
                    <i data-lucide="log-in"></i>
                    <h3>Login & Footer</h3>
                </div>

                <p class="gs-card-subtitle">
                    Store the platform login title/logo and footer text.
                </p>

                <div class="gs-form-grid">
                    <div class="gs-field full">
                        <label for="loginTitle">Login Page Title</label>
                        <input
                            id="loginTitle"
                            name="login_title"
                            class="form-control"
                            maxlength="160"
                            value="<?= e($loginTitle) ?>"
                            required
                        >
                    </div>

                    <div class="gs-field full">
                        <label for="loginLogo">Login Logo</label>
                        <input
                            id="loginLogo"
                            name="login_logo"
                            class="form-control"
                            type="file"
                            accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp"
                        >
                        <div class="gs-help">
                            Maximum 2 MB. PNG, JPG or WEBP.
                        </div>

                        <?php if ($loginLogoPath !== ''): ?>
                            <div class="gs-current-file">
                                <img src="<?= e($loginLogoUrl) ?>" alt="Current login logo">
                                <div>
                                    <strong>Current login logo</strong>
                                    <small class="d-block text-muted">
                                        <?= e(basename($loginLogoPath)) ?>
                                    </small>
                                    <label class="mt-2 mb-0 d-flex align-items-center gap-2">
                                        <input
                                            type="checkbox"
                                            name="remove_login_logo"
                                            value="1"
                                        >
                                        Remove login logo
                                    </label>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="gs-field full">
                        <label for="footerText">Footer Text</label>
                        <input
                            id="footerText"
                            name="footer_text"
                            class="form-control"
                            maxlength="255"
                            value="<?= e($footerText) ?>"
                            placeholder="© 2026 School ERP. All rights reserved."
                        >
                    </div>
                </div>
            </section>

            <section class="gs-section">
                <div class="gs-card-title">
                    <i data-lucide="shield-cog"></i>
                    <h3>System Controls</h3>
                </div>

                <p class="gs-card-subtitle">
                    Configure maintenance state and common Super Admin visibility options.
                </p>

                <div class="gs-switch-row">
                    <div class="gs-switch-copy">
                        <strong>Maintenance Mode</strong>
                        <small>
                            Store whether the platform should be treated as under maintenance.
                        </small>
                    </div>
                    <div class="form-check form-switch m-0">
                        <input
                            id="maintenanceMode"
                            class="form-check-input"
                            type="checkbox"
                            name="maintenance_mode"
                            value="1"
                            <?= $maintenanceMode ? 'checked' : '' ?>
                        >
                    </div>
                </div>

                <div class="gs-field mt-3">
                    <label for="maintenanceMessage">Maintenance Message</label>
                    <textarea
                        id="maintenanceMessage"
                        name="maintenance_message"
                        class="form-control"
                        rows="3"
                        maxlength="500"
                        placeholder="System maintenance is in progress. Please try again shortly."
                    ><?= e($maintenanceMessage) ?></textarea>
                </div>

                <div class="gs-switch-row mt-2">
                    <div class="gs-switch-copy">
                        <strong>Show Profile Menu</strong>
                        <small>
                            Store whether the Super Admin profile/account menu should be visible.
                        </small>
                    </div>
                    <div class="form-check form-switch m-0">
                        <input
                            id="showProfileMenu"
                            class="form-check-input"
                            type="checkbox"
                            name="show_profile_menu"
                            value="1"
                            <?= $showProfileMenu ? 'checked' : '' ?>
                        >
                    </div>
                </div>
            </section>

            <section class="gs-section">
                <div class="gs-card-title">
                    <i data-lucide="bell-ring"></i>
                    <h3>Topbar Actions</h3>
                </div>

                <p class="gs-card-subtitle">
                    Enable or hide the existing Notification and Message buttons.
                </p>

                <div class="gs-switch-row">
                    <div class="gs-switch-copy">
                        <strong>Show Notifications</strong>
                        <small>
                            Display the existing bell button and notification count.
                        </small>
                    </div>

                    <div class="form-check form-switch m-0">
                        <input
                            id="showNotifications"
                            class="form-check-input"
                            type="checkbox"
                            name="show_notifications"
                            value="1"
                            <?= $showNotifications ? 'checked' : '' ?>
                        >
                    </div>
                </div>

                <div class="gs-switch-row">
                    <div class="gs-switch-copy">
                        <strong>Show Messages</strong>
                        <small>
                            Display the existing message button and unread count.
                        </small>
                    </div>

                    <div class="form-check form-switch m-0">
                        <input
                            id="showMessages"
                            class="form-check-input"
                            type="checkbox"
                            name="show_messages"
                            value="1"
                            <?= $showMessages ? 'checked' : '' ?>
                        >
                    </div>
                </div>
            </section>

            <div class="gs-actions">
                <button
                    id="saveSettingsButton"
                    class="btn-ui btn-primary-ui"
                    type="submit"
                >
                    <i data-lucide="save"></i>
                    Save Settings
                </button>
            </div>
        </form>

        <aside class="ui-card gs-preview">
            <div class="gs-card-title">
                <i data-lucide="eye"></i>
                <h3>Sidebar Header Preview</h3>
            </div>

            <p class="gs-card-subtitle">
                Preview only. This branding is shown in the Super Admin sidebar header.
            </p>

            <div class="gs-preview-topbar">
                <div class="gs-preview-left">
                    <div class="gs-preview-brand">
                        <span
                            id="previewBrandMark"
                            class="gs-preview-mark"
                        >
                            <?php if (
                                $brandType === 'image'
                                && $brandImageUrl !== ''
                            ): ?>
                                <img
                                    src="<?= e($brandImageUrl) ?>"
                                    alt=""
                                >
                            <?php else: ?>
                                <i
                                    data-lucide="<?= e($brandIcon) ?>"
                                ></i>
                            <?php endif; ?>
                        </span>

                        <span
                            id="previewBrandName"
                            class="gs-preview-name"
                        >
                            <?= e($brandName) ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="gs-preview-summary">
                <div class="gs-preview-row">
                    <span>System Name</span>
                    <strong id="previewSystemName"><?= e($systemName) ?></strong>
                </div>
                <div class="gs-preview-row">
                    <span>Browser Title</span>
                    <strong id="previewBrowserTitle"><?= e($browserTitle) ?></strong>
                </div>
                <div class="gs-preview-row">
                    <span>Time Zone</span>
                    <strong><?= e($timezone) ?></strong>
                </div>
                <div class="gs-preview-row">
                    <span>Rows / Page</span>
                    <strong><?= (int)$defaultRowsPerPage ?></strong>
                </div>
                <div class="gs-preview-row">
                    <span>Maintenance</span>
                    <strong><?= $maintenanceMode ? 'Enabled' : 'Disabled' ?></strong>
                </div>
            </div>

            <div class="gs-warning">
                These settings are stored centrally in
                <strong>platform_general_settings</strong>.
                Pages such as login/layout must read these values to apply
                Login Logo, Footer, Maintenance Mode and global Browser Title
                everywhere.
            </div>
        </aside>
    </div>
</div>

<script>
(() => {
    const form = document.getElementById('generalSettingsForm');
    const message = document.getElementById('gsMessage');
    const saveButton = document.getElementById('saveSettingsButton');

    const nameInput = document.getElementById('topbarBrandName');
    const iconInput = document.getElementById('topbarBrandIcon');
    const imageInput = document.getElementById('topbarBrandImage');

    const iconRadio = document.getElementById('brandTypeIcon');
    const imageRadio = document.getElementById('brandTypeImage');

    const iconField = document.getElementById('iconField');
    const imageField = document.getElementById('imageField');

    const previewName = document.getElementById('previewBrandName');
    const previewMark = document.getElementById('previewBrandMark');
    const showNotifications = document.getElementById('showNotifications');
    const showMessages = document.getElementById('showMessages');
    const systemNameInput = document.getElementById('systemName');
    const browserTitleInput = document.getElementById('browserTitle');
    const previewSystemName = document.getElementById('previewSystemName');
    const previewBrowserTitle = document.getElementById('previewBrowserTitle');

    const existingImageUrl =
        <?= json_encode(
            $brandImageUrl,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
        ) ?>;

    let selectedImageUrl = '';

    function setMessage(type, text) {
        if (!message) return;

        message.className =
            `alert gs-message show alert-${type}`;

        message.textContent = text;
    }

    function selectedType() {
        return imageRadio?.checked
            ? 'image'
            : 'icon';
    }

    function renderFields() {
        const type = selectedType();

        if (iconField) {
            iconField.hidden = type !== 'icon';
        }

        if (imageField) {
            imageField.hidden = type !== 'image';
        }
    }

    function renderPreview() {
        if (previewName) {
            previewName.textContent =
                String(nameInput?.value || 'School ERP').trim()
                || 'School ERP';
        }

        if (previewSystemName) {
            previewSystemName.textContent =
                String(systemNameInput?.value || 'School ERP').trim()
                || 'School ERP';
        }

        if (previewBrowserTitle) {
            previewBrowserTitle.textContent =
                String(browserTitleInput?.value || 'School ERP').trim()
                || 'School ERP';
        }

        if (!previewMark) return;

        if (selectedType() === 'image') {
            const source =
                selectedImageUrl
                || existingImageUrl;

            if (source) {
                previewMark.innerHTML =
                    `<img src="${source}" alt="">`;

                return;
            }
        }

        const iconName =
            String(iconInput?.value || 'school')
                .trim()
            || 'school';

        previewMark.innerHTML =
            `<i data-lucide="${iconName.replace(/[^a-z0-9-]/gi,'')}"></i>`;

        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    nameInput?.addEventListener(
        'input',
        renderPreview
    );

    iconInput?.addEventListener(
        'input',
        renderPreview
    );

    systemNameInput?.addEventListener(
        'input',
        renderPreview
    );

    browserTitleInput?.addEventListener(
        'input',
        renderPreview
    );

    iconRadio?.addEventListener(
        'change',
        () => {
            renderFields();
            renderPreview();
        }
    );

    imageRadio?.addEventListener(
        'change',
        () => {
            renderFields();
            renderPreview();
        }
    );

    showNotifications?.addEventListener(
        'change',
        renderPreview
    );

    showMessages?.addEventListener(
        'change',
        renderPreview
    );

    imageInput?.addEventListener(
        'change',
        () => {
            const file = imageInput.files?.[0];

            if (!file) {
                selectedImageUrl = '';
                renderPreview();
                return;
            }

            if (selectedImageUrl) {
                URL.revokeObjectURL(
                    selectedImageUrl
                );
            }

            selectedImageUrl =
                URL.createObjectURL(file);

            renderPreview();
        }
    );

    form?.addEventListener(
        'submit',
        async event => {
            event.preventDefault();

            const brandName =
                String(nameInput?.value || '').trim();

            if (!brandName) {
                setMessage(
                    'danger',
                    'Sidebar Header Name is required.'
                );
                nameInput?.focus();
                return;
            }

            saveButton.disabled = true;

            const oldHtml =
                saveButton.innerHTML;

            saveButton.innerHTML =
                '<span class="spinner-border spinner-border-sm"></span> Saving...';

            try {
                const response = await fetch(
                    window.location.href,
                    {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'X-Requested-With':
                                'XMLHttpRequest',
                            'Accept':
                                'application/json'
                        },
                        body: new FormData(form)
                    }
                );

                const responseText =
                    await response.text();

                let result;

                try {
                    result =
                        JSON.parse(responseText);
                } catch (_) {
                    throw new Error(
                        'General Settings returned an invalid server response.'
                    );
                }

                if (
                    !response.ok
                    || !result.success
                ) {
                    throw new Error(
                        result.message
                        || 'Unable to save General Settings.'
                    );
                }

                setMessage(
                    'success',
                    result.message
                    || 'General Settings saved successfully.'
                );

                window.setTimeout(
                    () => window.location.reload(),
                    500
                );
            } catch (error) {
                setMessage(
                    'danger',
                    error?.message
                    || 'Unable to save General Settings.'
                );
            } finally {
                saveButton.disabled = false;
                saveButton.innerHTML = oldHtml;

                if (window.lucide) {
                    window.lucide.createIcons();
                }
            }
        }
    );

    renderFields();
    renderPreview();

    document.title =
        <?= json_encode(
            $browserTitle,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        ) ?>;

    <?php if ($faviconUrl !== ''): ?>
    (() => {
        let favicon =
            document.querySelector('link[rel="icon"]');

        if (!favicon) {
            favicon = document.createElement('link');
            favicon.rel = 'icon';
            document.head.appendChild(favicon);
        }

        favicon.href =
            <?= json_encode(
                $faviconUrl,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            ) ?>;
    })();
    <?php endif; ?>

    if (window.lucide) {
        window.lucide.createIcons();
    }
})();
</script>

<?php
require $projectRoot . '/includes/layout-end.php';

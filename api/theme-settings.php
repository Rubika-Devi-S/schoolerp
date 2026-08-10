<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| JSON-only Theme Settings API
|--------------------------------------------------------------------------
*/
ob_start();
ini_set('display_errors', '0');

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/theme-config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (!function_exists('theme_settings_response')) {
    function theme_settings_response(
        bool $success,
        string $message,
        array $data = [],
        int $status = 200
    ): never {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');

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
}

if (!function_exists('theme_settings_request_data')) {
    function theme_settings_request_data(): array
    {
        $rawBody = (string)file_get_contents('php://input');

        if ($rawBody === '') {
            return $_POST;
        }

        $decoded = json_decode($rawBody, true);

        if (!is_array($decoded)) {
            theme_settings_response(
                false,
                'Invalid JSON request body.',
                [],
                400
            );
        }

        return $decoded;
    }
}

if (!function_exists('theme_settings_csrf_valid')) {
    function theme_settings_csrf_valid(string $token): bool
    {
        if (function_exists('csrf_is_valid')) {
            return csrf_is_valid($token);
        }

        if (function_exists('verify_csrf_token')) {
            return verify_csrf_token($token);
        }

        $storedToken = (string)(
            $_SESSION['csrf_token']
            ?? $_SESSION['_csrf']
            ?? $_SESSION['csrf']
            ?? ''
        );

        return $token !== ''
            && $storedToken !== ''
            && hash_equals($storedToken, $token);
    }
}

try {
    if (
        strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'))
        !== 'POST'
    ) {
        theme_settings_response(
            false,
            'Only POST requests are allowed.',
            [],
            405
        );
    }

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        theme_settings_response(
            false,
            'Database connection is unavailable.',
            [],
            500
        );
    }

    if (!function_exists('school_table_exists')
        || !school_table_exists(
            $pdo,
            'website_color_settings'
        )) {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS website_color_settings (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id BIGINT UNSIGNED NOT NULL,
                setting_key VARCHAR(100) NOT NULL,
                setting_value VARCHAR(100) NOT NULL,
                setting_label VARCHAR(150) DEFAULT NULL,
                setting_group VARCHAR(50) NOT NULL DEFAULT 'theme',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                updated_by BIGINT UNSIGNED DEFAULT NULL,
                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_tenant_setting (
                    tenant_id,
                    setting_key
                ),
                KEY idx_theme_updated_by (updated_by)
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci"
        );
    }

    $currentUser = function_exists('current_user')
        ? current_user()
        : [];

    $currentUserId = (int)(
        $currentUser['id']
        ?? $currentUser['user_id']
        ?? $_SESSION['user_id']
        ?? 0
    );

    if ($currentUserId <= 0) {
        theme_settings_response(
            false,
            'Login session is required.',
            [],
            401
        );
    }

    $input = theme_settings_request_data();

    if (!theme_settings_csrf_valid(
        trim((string)($input['csrf_token'] ?? ''))
    )) {
        theme_settings_response(
            false,
            'Invalid or expired CSRF token. Refresh the page and try again.',
            [],
            419
        );
    }

    $settings = $input['settings'] ?? null;

    if (!is_array($settings)) {
        theme_settings_response(
            false,
            'Theme settings are missing.',
            [],
            422
        );
    }

    $defaults = school_theme_default_settings();
    $fontOptions = school_theme_font_options();
    $presets = school_theme_presets();

    $specialKeys = [
        'layout_density',
        'theme_preset',
        'app_font_size',
        'app_font_style',
    ];

    $colorKeys = array_values(array_filter(
        array_keys($defaults),
        static fn(string $key): bool =>
            !in_array($key, $specialKeys, true)
    ));

    $cleanSettings = [];

    foreach ($colorKeys as $key) {
        $value = strtolower(trim((string)(
            $settings[$key]
            ?? $defaults[$key]
        )));

        if (preg_match('/^#[0-9a-f]{6}$/', $value) !== 1) {
            theme_settings_response(
                false,
                'Invalid color value for '
                    . ucwords(str_replace('_', ' ', $key))
                    . '.',
                [],
                422
            );
        }

        $cleanSettings[$key] = [
            'value' => $value,
            'label' => ucwords(str_replace('_', ' ', $key)),
            'group' => in_array(
                $key,
                ['brand_1', 'brand_2'],
                true
            ) ? 'brand' : 'layout',
        ];
    }

    $density = strtolower(trim((string)(
        $settings['layout_density']
        ?? $defaults['layout_density']
    )));

    if (!in_array(
        $density,
        ['compact', 'comfortable', 'spacious'],
        true
    )) {
        theme_settings_response(
            false,
            'Invalid layout density.',
            [],
            422
        );
    }

    $preset = school_theme_normalize_preset(
        (string)(
            $settings['theme_preset']
            ?? $defaults['theme_preset']
        )
    );

    if (!array_key_exists($preset, $presets)
        && $preset !== 'custom') {
        theme_settings_response(
            false,
            'Invalid theme preset.',
            [],
            422
        );
    }

    $fontStyle = strtolower(trim((string)(
        $settings['app_font_style']
        ?? $defaults['app_font_style']
    )));

    if (!array_key_exists($fontStyle, $fontOptions)) {
        theme_settings_response(
            false,
            'Invalid font style.',
            [],
            422
        );
    }

    $fontSize = (int)(
        $settings['app_font_size']
        ?? $defaults['app_font_size']
    );

    if ($fontSize < 14 || $fontSize > 20) {
        theme_settings_response(
            false,
            'Font size must be between 14px and 20px.',
            [],
            422
        );
    }

    $cleanSettings['layout_density'] = [
        'value' => $density,
        'label' => 'Layout Density',
        'group' => 'layout',
    ];

    $cleanSettings['theme_preset'] = [
        'value' => $preset,
        'label' => 'Theme Preset',
        'group' => 'theme',
    ];

    $cleanSettings['app_font_style'] = [
        'value' => $fontStyle,
        'label' => 'Application Font Style',
        'group' => 'typography',
    ];

    $cleanSettings['app_font_size'] = [
        'value' => (string)$fontSize,
        'label' => 'Application Font Size',
        'group' => 'typography',
    ];

    $tenantId = max(
        1,
        (int)(
            $currentUser['tenant_id']
            ?? $_SESSION['tenant_id']
            ?? 1
        )
    );

    $userId = $currentUserId;

    $stmt = $pdo->prepare(
        "INSERT INTO website_color_settings (
            tenant_id,
            setting_key,
            setting_value,
            setting_label,
            setting_group,
            is_active,
            updated_by
        ) VALUES (
            :tenant_id,
            :setting_key,
            :setting_value,
            :setting_label,
            :setting_group,
            1,
            :updated_by
        )
        ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            setting_label = VALUES(setting_label),
            setting_group = VALUES(setting_group),
            is_active = 1,
            updated_by = VALUES(updated_by),
            updated_at = CURRENT_TIMESTAMP"
    );

    $pdo->beginTransaction();

    foreach ($cleanSettings as $key => $setting) {
        $stmt->execute([
            'tenant_id' => $tenantId,
            'setting_key' => $key,
            'setting_value' => $setting['value'],
            'setting_label' => $setting['label'],
            'setting_group' => $setting['group'],
            'updated_by' => $userId > 0 ? $userId : null,
        ]);
    }

    $pdo->commit();

    $savedSettings = school_theme_load_settings(
        $pdo,
        $tenantId
    );

    $_SESSION['school_theme_settings'] = $savedSettings;

    theme_settings_response(
        true,
        'Theme, font style, font size and colors saved successfully.',
        [
            'settings' => $savedSettings,
            'tenant_id' => $tenantId,
            'build' =>
                '2026-08-06-theme-more-fonts-v6',
        ]
    );
} catch (Throwable $e) {
    if (isset($pdo)
        && $pdo instanceof PDO
        && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Theme settings API error: '
        . $e->getMessage()
    );

    theme_settings_response(
        false,
        'Unable to save Theme Settings. Check the PHP error log.',
        [],
        500
    );
}

<?php
declare(strict_types=1);

/* Build: 2026-08-15-shared-school-platform-theme-api-v8 */

/*
|--------------------------------------------------------------------------
| Shared JSON-only Theme Settings API
|--------------------------------------------------------------------------
| School Admin requests keep the existing tenant-wise behaviour.
| Super Admin sends theme_scope=platform and saves isolated platform_* keys.
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

if (!function_exists('theme_settings_is_platform_admin')) {
    function theme_settings_is_platform_admin(
        PDO $pdo,
        array $user
    ): bool {
        if (function_exists('current_user_has_platform_role')) {
            try {
                if (current_user_has_platform_role()) {
                    return true;
                }
            } catch (Throwable $ignored) {
            }
        }

        $roleId = (int)(
            $user['role_id']
            ?? $_SESSION['role_id']
            ?? 0
        );

        if ($roleId === 1) {
            return true;
        }

        $roleKey = strtolower(trim((string)(
            $user['role_key']
            ?? $_SESSION['role_key']
            ?? ''
        )));

        if (in_array(
            $roleKey,
            ['super_admin', 'super-administrator', 'super_administrator'],
            true
        )) {
            return true;
        }

        if ($roleId <= 0) {
            return false;
        }

        try {
            $statement = $pdo->prepare(
                "SELECT role_key
                 FROM roles
                 WHERE id = :role_id
                   AND status = 'active'
                 LIMIT 1"
            );
            $statement->execute(['role_id' => $roleId]);

            return in_array(
                strtolower(trim((string)$statement->fetchColumn())),
                ['super_admin', 'super-administrator', 'super_administrator'],
                true
            );
        } catch (Throwable $exception) {
            error_log(
                'theme_settings_is_platform_admin: '
                . $exception->getMessage()
            );
            return false;
        }
    }
}


if (!function_exists('platform_theme_storage_tenant_id')) {
    function platform_theme_storage_tenant_id(
        ?PDO $pdo,
        array $user = []
    ): int {
        if ($pdo instanceof PDO
            && function_exists('school_table_exists')
            && school_table_exists($pdo, 'tenants')) {
            try {
                $statement = $pdo->query(
                    "SELECT id
                     FROM tenants
                     WHERE status = 'active'
                     ORDER BY id ASC
                     LIMIT 1"
                );

                $tenantId = (int)$statement->fetchColumn();

                if ($tenantId > 0) {
                    return $tenantId;
                }
            } catch (Throwable $exception) {
                error_log(
                    'platform_theme_storage_tenant_id: '
                    . $exception->getMessage()
                );
            }
        }

        $fallback = (int)(
            $user['tenant_id']
            ?? $user['school_id']
            ?? $_SESSION['tenant_id']
            ?? $_SESSION['school_id']
            ?? 0
        );

        return max(0, $fallback);
    }
}

if (!function_exists('platform_theme_load_settings')) {
    /** @return array<string,string> */
    function platform_theme_load_settings(
        ?PDO $pdo,
        int $storageTenantId
    ): array {
        $settings = school_theme_default_settings();

        if ($storageTenantId <= 0
            || !($pdo instanceof PDO)
            || !function_exists('school_table_exists')
            || !school_table_exists($pdo, 'website_color_settings')) {
            return $settings;
        }

        try {
            $statement = $pdo->prepare(
                "SELECT setting_key, setting_value
                 FROM website_color_settings
                 WHERE tenant_id = :tenant_id
                   AND setting_key LIKE 'platform\\_%' ESCAPE '\\\\'
                   AND is_active = 1"
            );

            $statement->execute([
                'tenant_id' => $storageTenantId,
            ]);

            $fontOptions = school_theme_font_options();
            $presets = school_theme_presets();

            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $storedKey = trim((string)($row['setting_key'] ?? ''));

                if (!str_starts_with($storedKey, 'platform_')) {
                    continue;
                }

                $key = substr($storedKey, 9);
                $value = trim((string)($row['setting_value'] ?? ''));

                if (!array_key_exists($key, $settings)) {
                    continue;
                }

                if ($key === 'layout_density') {
                    if (in_array(
                        $value,
                        ['compact', 'comfortable', 'spacious'],
                        true
                    )) {
                        $settings[$key] = $value;
                    }
                    continue;
                }

                if ($key === 'theme_preset') {
                    $normalizedPreset = school_theme_normalize_preset($value);

                    if (array_key_exists($normalizedPreset, $presets)
                        || $normalizedPreset === 'custom') {
                        $settings[$key] = $normalizedPreset;
                    }
                    continue;
                }

                if ($key === 'app_font_style') {
                    if (array_key_exists($value, $fontOptions)) {
                        $settings[$key] = $value;
                    }
                    continue;
                }

                if ($key === 'app_font_size') {
                    $fontSize = (int)$value;

                    if ($fontSize >= 14 && $fontSize <= 20) {
                        $settings[$key] = (string)$fontSize;
                    }
                    continue;
                }

                if (preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1) {
                    $settings[$key] = strtolower($value);
                }
            }
        } catch (Throwable $exception) {
            error_log(
                'platform_theme_load_settings: '
                . $exception->getMessage()
            );
        }

        return $settings;
    }
}

if (!function_exists('theme_settings_validate')) {
    /**
     * @return array{clean:array<string,array{value:string,label:string,group:string}>,settings:array<string,string>}
     */
    function theme_settings_validate(array $settings): array
    {
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
        $normalized = $defaults;

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

            $normalized[$key] = $value;
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

        $normalized['layout_density'] = $density;
        $normalized['theme_preset'] = $preset;
        $normalized['app_font_style'] = $fontStyle;
        $normalized['app_font_size'] = (string)$fontSize;

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

        return [
            'clean' => $cleanSettings,
            'settings' => $normalized,
        ];
    }
}

try {
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
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
        || !school_table_exists($pdo, 'website_color_settings')) {
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

    $currentUser = is_array($currentUser)
        ? $currentUser
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

    if (!is_array($settings)
        && isset($input['settings_json'])) {
        $decodedSettings = json_decode(
            (string)$input['settings_json'],
            true
        );
        $settings = is_array($decodedSettings)
            ? $decodedSettings
            : null;
    }

    if (!is_array($settings)) {
        theme_settings_response(
            false,
            'Theme settings are missing.',
            [],
            422
        );
    }

    $validated = theme_settings_validate($settings);
    $cleanSettings = $validated['clean'];

    $scope = strtolower(trim((string)(
        $input['theme_scope']
        ?? 'school'
    )));

    $isPlatformScope = $scope === 'platform';

    /*
     * PLATFORM / SUPER ADMIN PATH
     * Explicit only. School Admin requests never enter this block.
     */
    if ($isPlatformScope) {
        if (!theme_settings_is_platform_admin($pdo, $currentUser)) {
            theme_settings_response(
                false,
                'Only a Super Administrator can update the platform theme.',
                [],
                403
            );
        }

        $storageTenantId = platform_theme_storage_tenant_id(
            $pdo,
            $currentUser
        );

        if ($storageTenantId <= 0) {
            theme_settings_response(
                false,
                'Platform theme storage is unavailable.',
                [],
                500
            );
        }

        $statement = $pdo->prepare(
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
            $statement->execute([
                'tenant_id' => $storageTenantId,
                'setting_key' => 'platform_' . $key,
                'setting_value' => $setting['value'],
                'setting_label' => 'Platform ' . $setting['label'],
                'setting_group' => 'platform_' . $setting['group'],
                'updated_by' => $currentUserId,
            ]);
        }

        $pdo->commit();

        $savedSettings = platform_theme_load_settings(
            $pdo,
            $storageTenantId
        );

        theme_settings_response(
            true,
            'Super Admin theme, font style and text size saved successfully.',
            [
                'settings' => $savedSettings,
                'theme_scope' => 'platform',
                'build' => '2026-08-15-shared-school-platform-theme-api-v8',
            ]
        );
    }

    /*
     * SCHOOL ADMIN PATH — existing working v7 behaviour.
     */
    $tenantId = function_exists('school_theme_context_tenant_id')
        ? school_theme_context_tenant_id($currentUser)
        : (int)(
            $_SESSION['school_id']
            ?? $_SESSION['tenant_id']
            ?? 0
        );

    if ($tenantId <= 0) {
        theme_settings_response(
            false,
            'A valid School ID is required to save Theme Settings.',
            [],
            403
        );
    }

    if (function_exists('school_table_exists')
        && school_table_exists($pdo, 'tenants')) {
        $tenantCheck = $pdo->prepare(
            "SELECT id
             FROM tenants
             WHERE id = :tenant_id
             LIMIT 1"
        );
        $tenantCheck->execute([
            'tenant_id' => $tenantId,
        ]);

        if (!(int)$tenantCheck->fetchColumn()) {
            theme_settings_response(
                false,
                'The selected School ID is invalid or unavailable.',
                [],
                403
            );
        }
    }

    $statement = $pdo->prepare(
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
        $statement->execute([
            'tenant_id' => $tenantId,
            'setting_key' => $key,
            'setting_value' => $setting['value'],
            'setting_label' => $setting['label'],
            'setting_group' => $setting['group'],
            'updated_by' => $currentUserId,
        ]);
    }

    $pdo->commit();

    $savedSettings = school_theme_load_settings(
        $pdo,
        $tenantId
    );

    if (function_exists('school_theme_cache_session_settings')) {
        school_theme_cache_session_settings(
            $tenantId,
            $savedSettings
        );
    }

    theme_settings_response(
        true,
        'Theme, font style, font size and colors saved successfully.',
        [
            'settings' => $savedSettings,
            'tenant_id' => $tenantId,
            'theme_scope' => 'school',
            'build' => '2026-08-15-shared-school-platform-theme-api-v8',
        ]
    );
} catch (Throwable $exception) {
    if (isset($pdo)
        && $pdo instanceof PDO
        && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Theme settings API error: '
        . $exception->getMessage()
    );

    theme_settings_response(
        false,
        'Unable to save Theme Settings. Check the PHP error log.',
        [],
        500
    );
}

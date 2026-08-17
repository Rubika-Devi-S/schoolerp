<?php
declare(strict_types=1);

/* Build: 2026-08-17-school-platform-theme-loader-v9 */

require_once __DIR__ . '/theme-config.php';

/**
 * Loads and applies the saved tenant theme on every application page.
 *
 * Include this file after the main application stylesheet.
 */

$currentThemeUser = function_exists('current_user')
    ? current_user()
    : [];

$currentThemeTenantId = function_exists('school_theme_context_tenant_id')
    ? school_theme_context_tenant_id(
        is_array($currentThemeUser) ? $currentThemeUser : []
    )
    : (int)($_SESSION['school_id'] ?? $_SESSION['tenant_id'] ?? 0);

$themeLoaderRequestPath = str_replace(
    '\\',
    '/',
    (string)(
        $_SERVER['SCRIPT_NAME']
        ?? $_SERVER['PHP_SELF']
        ?? ''
    )
);

$themeLoaderPageKey = isset($pageKey)
    ? strtolower(trim((string)$pageKey))
    : '';

$themeLoaderIsPlatformRoute =
    str_contains($themeLoaderRequestPath, '/super-admin/')
    || str_starts_with($themeLoaderPageKey, 'super_admin_')
    || str_starts_with($themeLoaderPageKey, 'platform_');

$themeLoaderIsPlatformUser = false;

if ($themeLoaderIsPlatformRoute) {
    if (function_exists('current_user_has_platform_role')) {
        try {
            $themeLoaderIsPlatformUser = current_user_has_platform_role();
        } catch (Throwable $ignored) {
            $themeLoaderIsPlatformUser = false;
        }
    }

    if (!$themeLoaderIsPlatformUser) {
        $themeLoaderRoleId = (int)(
            $currentThemeUser['role_id']
            ?? $_SESSION['role_id']
            ?? 0
        );

        $themeLoaderRoleKey = strtolower(trim((string)(
            $currentThemeUser['role_key']
            ?? $_SESSION['role_key']
            ?? ''
        )));

        $themeLoaderIsPlatformUser =
            $themeLoaderRoleId === 1
            || in_array(
                $themeLoaderRoleKey,
                [
                    'super_admin',
                    'super-administrator',
                    'super_administrator',
                ],
                true
            );
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

$themeLoaderStorageTenantId = platform_theme_storage_tenant_id(
    isset($pdo) && $pdo instanceof PDO ? $pdo : null,
    is_array($currentThemeUser) ? $currentThemeUser : []
);

$schoolThemeSettings = $themeLoaderIsPlatformUser
    ? platform_theme_load_settings(
        isset($pdo) && $pdo instanceof PDO ? $pdo : null,
        $themeLoaderStorageTenantId
    )
    : school_theme_load_settings(
        isset($pdo) && $pdo instanceof PDO ? $pdo : null,
        $currentThemeTenantId
    );

$themeStorageScopeId = $themeLoaderIsPlatformUser
    ? 'platform'
    : (
        $currentThemeTenantId > 0
            ? (string)$currentThemeTenantId
            : 'no_school'
    );

$fontOptions = school_theme_font_options();
$selectedFontKey = (string)(
    $schoolThemeSettings['app_font_style']
    ?? 'plus_jakarta'
);

$selectedFont = $fontOptions[$selectedFontKey]
    ?? $fontOptions['plus_jakarta'];

$selectedFontStack = (string)$selectedFont['stack'];
$selectedFontSize = max(
    14,
    min(
        20,
        (int)($schoolThemeSettings['app_font_size'] ?? 16)
    )
);

$selectedPreset = school_theme_normalize_preset(
    (string)($schoolThemeSettings['theme_preset'] ?? 'light')
);

$themePresets = school_theme_presets();
$selectedThemeMode = (string)(
    $themePresets[$selectedPreset]['mode']
    ?? (
        in_array($selectedPreset, ['dark', 'midnight'], true)
            ? 'dark'
            : 'light'
    )
);

$themeCssMap = [
    'sidebar_bg' => '--sidebar-bg',
    'sidebar_text' => '--sidebar-text',
    'sidebar_active_bg_1' => '--sidebar-active-bg-1',
    'sidebar_active_bg_2' => '--sidebar-active-bg-2',
    'sidebar_active_text' => '--sidebar-active-text',
    'sidebar_hover_bg' => '--sidebar-hover-bg',
    'sidebar_hover_text' => '--sidebar-hover-text',
    'topbar_bg_1' => '--topbar-bg-1',
    'topbar_bg_2' => '--topbar-bg-2',
    'topbar_text' => '--topbar-text',
    'body_bg' => '--body-bg',
    'card_bg' => '--card-bg',
    'text_main' => '--text-main',
    'text_muted' => '--text-muted',
    'border_soft' => '--border-soft',
    'brand_1' => '--brand-1',
    'brand_2' => '--brand-2',
    'header_gradient_start' => '--header-gradient-start',
    'header_gradient_end' => '--header-gradient-end',
    'header_text' => '--header-text',
];

$fontStackMap = array_map(
    static fn(array $font): string => (string)$font['stack'],
    $fontOptions
);

$themeModeMap = array_map(
    static fn(array $preset): string => (string)$preset['mode'],
    $themePresets
);
?>
<style id="schoolThemeFonts">
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Figtree:wght@400;500;600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&family=Inter:wght@400;500;600;700;800&family=Lato:wght@400;700;900&family=Manrope:wght@400;500;600;700;800&family=Merriweather+Sans:wght@400;500;600;700&family=Montserrat:wght@400;500;600;700;800&family=Mulish:wght@400;500;600;700;800&family=Noto+Sans:wght@400;500;600;700;800&family=Nunito:wght@400;500;600;700;800&family=Open+Sans:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&family=Roboto:wght@400;500;700;900&family=Rubik:wght@400;500;600;700;800&family=Source+Sans+3:wght@400;500;600;700;800&family=Ubuntu:wght@400;500;700&family=Work+Sans:wght@400;500;600;700;800&display=swap');
</style>

<style id="schoolDynamicTheme">
:root,
body {
<?php foreach ($themeCssMap as $settingKey => $cssVariable): ?>
    <?= $cssVariable ?>: <?= htmlspecialchars(
        (string)$schoolThemeSettings[$settingKey],
        ENT_QUOTES,
        'UTF-8'
    ) ?> !important;
<?php endforeach; ?>

    /*
     * Compatibility aliases for older/current stylesheet variants.
     * --topbar-bg is still referenced by some School ERP topbar CSS.
     */
    --topbar-bg: <?= htmlspecialchars(
        (string)$schoolThemeSettings['topbar_bg_1'],
        ENT_QUOTES,
        'UTF-8'
    ) ?> !important;

    --app-font-family: <?= htmlspecialchars(
        $selectedFontStack,
        ENT_QUOTES,
        'UTF-8'
    ) ?> !important;
    --app-font-size: <?= $selectedFontSize ?>px !important;
}

html {
    font-size: var(--app-font-size, 16px);
}

html[data-theme-mode="dark"] {
    color-scheme: dark;
}

body,
button,
input,
select,
textarea,
.form-control,
.form-select,
.btn,
.btn-ui,
.dropdown-menu,
.tooltip,
.popover {
    font-family: var(--app-font-family) !important;
}

body {
    background-color: var(--body-bg, #f6f8fc);
    color: var(--text-main, #101b46);
}

#sidebar {
    background-color: var(--sidebar-bg, #ffffff);
}

.sidebar-link {
    color: var(--sidebar-text, #334155);
}

.sidebar-link:hover {
    background-color: var(--sidebar-hover-bg, #eef2ff);
    color: var(--sidebar-hover-text, #27305f);
}

.sidebar-link.active {
    background: linear-gradient(
        135deg,
        var(--sidebar-active-bg-1, #6d4df2),
        var(--sidebar-active-bg-2, #3559dc)
    );
    color: var(--sidebar-active-text, #ffffff);
}

.ui-card,
.modal-content,
.offcanvas,
.dropdown-menu {
    background-color: var(--card-bg, #ffffff);
    color: var(--text-main, #101b46);
    border-color: var(--border-soft, #e7ebf3);
}

.page-title,
.ui-card-title,
.data-table,
.table {
    color: var(--text-main, #101b46);
}

.page-subtitle,
.text-muted {
    color: var(--text-muted, #64748b) !important;
}

.form-control,
.form-select {
    background-color: var(--card-bg, #ffffff);
    color: var(--text-main, #101b46);
    border-color: var(--border-soft, #e7ebf3);
}

.form-control::placeholder {
    color: var(--text-muted, #64748b);
}

html[data-layout-density="compact"] .ui-card-body,
html[data-layout-density="compact"] .theme-body {
    padding: 12px;
}

html[data-layout-density="compact"] .data-table th,
html[data-layout-density="compact"] .data-table td {
    padding-top: 8px;
    padding-bottom: 8px;
}

html[data-layout-density="spacious"] .ui-card-body,
html[data-layout-density="spacious"] .theme-body {
    padding: 22px;
}

html[data-layout-density="spacious"] .data-table th,
html[data-layout-density="spacious"] .data-table td {
    padding-top: 15px;
    padding-bottom: 15px;
}
</style>

<script>
(function () {
    'use strict';

    const initialSettings = <?= json_encode(
        $schoolThemeSettings,
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_INVALID_UTF8_SUBSTITUTE
    ) ?>;

    const cssVariableMap = <?= json_encode(
        $themeCssMap,
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    ) ?>;

    const cssVariableAliases = {
        topbar_bg_1: ['--topbar-bg']
    };

    const fontStackMap = <?= json_encode(
        $fontStackMap,
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    ) ?>;

    const themeModeMap = <?= json_encode(
        $themeModeMap,
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    ) ?>;

    const themeStorageScopeId = <?= json_encode(
        $themeStorageScopeId,
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    ) ?>;
    const storageKey =
        `school_erp_saved_theme_${themeStorageScopeId}`;
    const channelName =
        `school_erp_theme_channel_${themeStorageScopeId}`;
    const channel = 'BroadcastChannel' in window
        ? new BroadcastChannel(channelName)
        : null;

    function clampFontSize(value) {
        const parsed = Number.parseInt(String(value), 10);

        if (Number.isNaN(parsed)) {
            return 16;
        }

        return Math.max(14, Math.min(20, parsed));
    }

    function apply(settings, broadcast) {
        const root = document.documentElement;
        const nextSettings = {
            ...(window.SCHOOL_THEME_SETTINGS || initialSettings),
            ...settings
        };

        const body = document.body;

        Object.entries(cssVariableMap).forEach(([key, variable]) => {
            const value = String(nextSettings[key] || '');

            if (!/^#[0-9a-fA-F]{6}$/.test(value)) {
                return;
            }

            /*
             * BODY is intentional here. Older Super Admin theme versions wrote
             * custom properties on body:has(...), which otherwise overrides
             * :root through the CSS inheritance rules.
             */
            root.style.setProperty(variable, value, 'important');

            if (body) {
                body.style.setProperty(variable, value, 'important');
            }

            (cssVariableAliases[key] || []).forEach(alias => {
                root.style.setProperty(alias, value, 'important');

                if (body) {
                    body.style.setProperty(alias, value, 'important');
                }
            });
        });

        const fontKey = nextSettings.app_font_style || 'plus_jakarta';
        const fontStack = fontStackMap[fontKey]
            || fontStackMap.plus_jakarta;
        const fontSize =
            clampFontSize(nextSettings.app_font_size) + 'px';

        root.style.setProperty(
            '--app-font-family',
            fontStack,
            'important'
        );
        root.style.setProperty(
            '--app-font-size',
            fontSize,
            'important'
        );

        if (body) {
            body.style.setProperty(
                '--app-font-family',
                fontStack,
                'important'
            );
            body.style.setProperty(
                '--app-font-size',
                fontSize,
                'important'
            );
        }

        root.dataset.layoutDensity =
            nextSettings.layout_density || 'comfortable';

        root.dataset.themePreset =
            nextSettings.theme_preset || 'custom';

        root.dataset.themeMode =
            themeModeMap[nextSettings.theme_preset]
            || (
                ['dark', 'midnight'].includes(
                    nextSettings.theme_preset
                )
                    ? 'dark'
                    : 'light'
            );

        window.SCHOOL_THEME_SETTINGS = nextSettings;

        window.dispatchEvent(
            new CustomEvent('school-theme-changed', {
                detail: nextSettings
            })
        );

        if (broadcast === true) {
            try {
                localStorage.setItem(
                    storageKey,
                    JSON.stringify(nextSettings)
                );
            } catch (error) {
                console.warn('Unable to store theme locally.', error);
            }

            if (channel) {
                channel.postMessage(nextSettings);
            }
        }

        return nextSettings;
    }

    window.SchoolTheme = {
        apply: apply,
        get: function () {
            return {
                ...(window.SCHOOL_THEME_SETTINGS || initialSettings)
            };
        }
    };

    window.addEventListener('storage', event => {
        if (event.key !== storageKey || !event.newValue) {
            return;
        }

        try {
            apply(JSON.parse(event.newValue), false);
        } catch (error) {
            console.warn('Unable to synchronize theme.', error);
        }
    });

    if (channel) {
        channel.addEventListener('message', event => {
            if (event.data && typeof event.data === 'object') {
                apply(event.data, false);
            }
        });
    }

    /*
     * The database-loaded server settings are canonical on every page.
     * Refresh local cache from the database result instead of allowing an
     * older browser value to replace the saved theme.
     */
    apply(initialSettings, false);

    try {
        localStorage.setItem(
            storageKey,
            JSON.stringify(initialSettings)
        );
    } catch (error) {
        console.warn(
            'Unable to refresh the local theme cache.',
            error
        );
    }
})();
</script>

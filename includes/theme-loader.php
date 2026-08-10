<?php
declare(strict_types=1);

require_once __DIR__ . '/theme-config.php';

/**
 * Loads and applies the saved tenant theme on every application page.
 *
 * Include this file after the main application stylesheet.
 */

$currentThemeUser = function_exists('current_user')
    ? current_user()
    : [];

$currentThemeTenantId = max(
    1,
    (int)(
        $currentThemeUser['tenant_id']
        ?? $_SESSION['tenant_id']
        ?? 1
    )
);

$schoolThemeSettings = school_theme_load_settings(
    isset($pdo) && $pdo instanceof PDO ? $pdo : null,
    $currentThemeTenantId
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
:root {
<?php foreach ($themeCssMap as $settingKey => $cssVariable): ?>
    <?= $cssVariable ?>: <?= htmlspecialchars(
        (string)$schoolThemeSettings[$settingKey],
        ENT_QUOTES,
        'UTF-8'
    ) ?>;
<?php endforeach; ?>
    --app-font-family: <?= htmlspecialchars(
        $selectedFontStack,
        ENT_QUOTES,
        'UTF-8'
    ) ?>;
    --app-font-size: <?= $selectedFontSize ?>px;
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

    const tenantId = <?= (int)$currentThemeTenantId ?>;
    const storageKey =
        `school_erp_saved_theme_${tenantId}`;
    const channelName =
        `school_erp_theme_channel_${tenantId}`;
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

        Object.entries(cssVariableMap).forEach(([key, variable]) => {
            const value = nextSettings[key];

            if (/^#[0-9a-fA-F]{6}$/.test(String(value || ''))) {
                root.style.setProperty(variable, value);
            }
        });

        const fontKey = nextSettings.app_font_style || 'plus_jakarta';
        const fontStack = fontStackMap[fontKey]
            || fontStackMap.plus_jakarta;

        root.style.setProperty('--app-font-family', fontStack);
        root.style.setProperty(
            '--app-font-size',
            clampFontSize(nextSettings.app_font_size) + 'px'
        );

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

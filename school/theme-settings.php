<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/theme-config.php';

require_login();

/*
 * Use the existing Settings permission scope instead of a hard-coded
 * Super Admin role check. The normal layout/permission system remains
 * responsible for deciding which authenticated users can open the page.
 */
$pageTitle = 'Theme Settings';
$pageKey = 'settings';

/* Build: 2026-08-14-school-id-theme-isolation-v7 */

$currentUser = function_exists('current_user')
    ? current_user()
    : [];

$baseUrl = defined('BASE_URL')
    ? rtrim((string)BASE_URL, '/') . '/'
    : '../';

$tenantId = function_exists('school_theme_context_tenant_id')
    ? school_theme_context_tenant_id(
        is_array($currentUser) ? $currentUser : []
    )
    : (int)($_SESSION['school_id'] ?? $_SESSION['tenant_id'] ?? 0);

if ($tenantId <= 0) {
    http_response_code(403);
    exit('A valid School ID is required to manage Theme Settings.');
}

if (function_exists('csrfToken')) {
    $csrfToken = (string)csrfToken();
} elseif (function_exists('csrf_token')) {
    $csrfToken = (string)csrf_token();
} else {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    $csrfToken = (string)$_SESSION['csrf_token'];
}

$defaults = school_theme_default_settings();
$presets = school_theme_presets();
$fontOptions = school_theme_font_options();

$theme = school_theme_load_settings(
    isset($pdo) && $pdo instanceof PDO ? $pdo : null,
    $tenantId
);

$fields = [
    'sidebar_bg' => 'Sidebar Background',
    'sidebar_text' => 'Sidebar Text',
    'sidebar_active_bg_1' => 'Active Menu Start',
    'sidebar_active_bg_2' => 'Active Menu End',
    'topbar_bg_1' => 'Topbar Start',
    'topbar_bg_2' => 'Topbar End',
    'brand_1' => 'Primary Brand',
    'brand_2' => 'Secondary Brand',
];

require dirname(__DIR__) . '/includes/layout-start.php';
?>

<style>
.theme-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.theme-body {
    padding: 16px;
}

.theme-row {
    display: grid;
    grid-template-columns: 52px 1fr 115px;
    gap: 12px;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid var(--border-soft, #e7ebf3);
}

.theme-row:last-child {
    border-bottom: 0;
}

.theme-color {
    width: 52px;
    height: 42px;
    padding: 3px;
    border: 1px solid var(--border-soft, #e7ebf3);
    border-radius: 10px;
}

.theme-copy strong,
.theme-copy small {
    display: block;
}

.theme-copy small {
    color: var(--text-muted, #64748b);
    font-size: .72rem;
}

.theme-preview {
    padding: 18px;
    border: 1px solid var(--border-soft, #e7ebf3);
    border-radius: 13px;
    background: var(--body-bg, #f6f8fc);
}

.preview-head {
    height: 48px;
    border-radius: 10px;
    background: linear-gradient(
        105deg,
        var(--topbar-bg-1, #653dd8),
        var(--topbar-bg-2, #1959c8)
    );
}

.preview-btn {
    display: inline-flex;
    margin-top: 16px;
    padding: 9px 13px;
    border-radius: 8px;
    color: #fff;
    background: linear-gradient(
        135deg,
        var(--brand-1, #6747e8),
        var(--brand-2, #2f62d7)
    );
    font-size: .72rem;
    font-weight: 800;
}

.theme-msg {
    display: none;
}

.theme-msg.show {
    display: block;
}

.theme-presets {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 12px;
}

.theme-preset {
    width: 100%;
    padding: 12px;
    border: 1px solid var(--border-soft, #e7ebf3);
    border-radius: 12px;
    background: var(--card-bg, #ffffff);
    color: var(--text-main, #101b46);
    text-align: left;
    transition:
        border-color .18s ease,
        box-shadow .18s ease,
        transform .18s ease;
}

.theme-preset:hover {
    transform: translateY(-1px);
}

.theme-preset.active {
    border-color: var(--brand-1, #6747e8);
    box-shadow: 0 0 0 3px
        color-mix(in srgb, var(--brand-1, #6747e8) 14%, transparent);
}

.theme-preset-swatches {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    height: 32px;
    margin-bottom: 9px;
    overflow: hidden;
    border: 1px solid var(--border-soft, #e7ebf3);
    border-radius: 8px;
}

.theme-preset strong,
.theme-preset small {
    display: block;
}

.theme-preset strong {
    font-size: .78rem;
    font-weight: 800;
}

.theme-preset small {
    margin-top: 3px;
    color: var(--text-muted, #64748b);
    font-size: .67rem;
    line-height: 1.4;
}

.typography-control {
    display: grid;
    gap: 14px;
}

.typography-label {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.typography-label strong,
.typography-label small {
    display: block;
}

.typography-label small {
    color: var(--text-muted, #64748b);
    font-size: .72rem;
}

.font-size-control {
    display: grid;
    grid-template-columns: 42px 1fr 58px 42px;
    gap: 10px;
    align-items: center;
}

.font-size-button {
    width: 42px;
    height: 38px;
    justify-content: center;
    padding: 0;
}

.font-size-value {
    min-width: 58px;
    padding: 8px 9px;
    border: 1px solid var(--border-soft, #e7ebf3);
    border-radius: 9px;
    background: var(--body-bg, #f6f8fc);
    color: var(--text-main, #101b46);
    text-align: center;
    font-size: .75rem;
    font-weight: 800;
}

.typography-sample {
    padding: 15px;
    border: 1px solid var(--border-soft, #e7ebf3);
    border-radius: 11px;
    background: var(--body-bg, #f6f8fc);
}

.typography-sample strong,
.typography-sample small {
    display: block;
}

.typography-sample small {
    margin-top: 5px;
    color: var(--text-muted, #64748b);
}

@media (max-width: 1199.98px) {
    .theme-presets {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 991.98px) {
    .theme-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 767.98px) {
    .theme-presets {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 575.98px) {
    .theme-row {
        grid-template-columns: 48px 1fr;
    }

    .theme-hex {
        grid-column: 1 / -1;
    }

    .theme-presets {
        grid-template-columns: 1fr;
    }

    .font-size-control {
        grid-template-columns: 42px 1fr 42px;
    }

    .font-size-value {
        grid-column: 1 / -1;
        grid-row: 2;
    }
}
</style>

<div class="page-heading">
    <div>
        <h1 class="page-title">Theme Settings</h1>
        <p class="page-subtitle">
            Manage themes and typography without changing the current template.
        </p>
    </div>

    <div class="page-actions">
        <button id="resetTheme" class="btn-ui" type="button">
            <i data-lucide="rotate-ccw"></i>
            Reset Preview
        </button>

        <button
            id="saveTheme"
            class="btn-ui btn-primary-ui"
            type="button"
        >
            <i data-lucide="save"></i>
            Save Theme
        </button>
    </div>
</div>

<div
    id="themeMsg"
    class="alert theme-msg"
    role="alert"
></div>

<article class="ui-card mb-3">
    <div class="ui-card-header">
        <h2 class="ui-card-title">Professional Themes</h2>
        <i data-lucide="swatch-book"></i>
    </div>

    <div class="theme-body">
        <div class="theme-presets">
            <?php foreach ($presets as $presetKey => $preset): ?>
                <?php $presetSettings = $preset['settings']; ?>

                <button
                    type="button"
                    class="theme-preset <?= (
                        $theme['theme_preset'] ?? ''
                    ) === $presetKey ? 'active' : '' ?>"
                    data-preset="<?= e($presetKey) ?>"
                >
                    <span class="theme-preset-swatches">
                        <span style="background:<?= e(
                            $presetSettings['sidebar_active_bg_1']
                        ) ?>"></span>
                        <span style="background:<?= e(
                            $presetSettings['topbar_bg_2']
                        ) ?>"></span>
                        <span style="background:<?= e(
                            $presetSettings['body_bg']
                        ) ?>"></span>
                        <span style="background:<?= e(
                            $presetSettings['card_bg']
                        ) ?>"></span>
                    </span>

                    <strong><?= e($preset['name']) ?></strong>
                    <small><?= e($preset['description']) ?></small>
                </button>
            <?php endforeach; ?>
        </div>
    </div>
</article>

<div class="theme-grid">
    <article class="ui-card">
        <div class="ui-card-header">
            <h2 class="ui-card-title">Theme Colors</h2>
            <i data-lucide="palette"></i>
        </div>

        <div class="theme-body">
            <?php foreach ($fields as $key => $label): ?>
                <div class="theme-row">
                    <input
                        class="theme-color color"
                        type="color"
                        data-key="<?= e($key) ?>"
                        value="<?= e($theme[$key]) ?>"
                        aria-label="<?= e($label) ?>"
                    >

                    <div class="theme-copy">
                        <strong><?= e($label) ?></strong>
                        <small>
                            Applied throughout the current interface.
                        </small>
                    </div>

                    <input
                        class="form-control form-control-sm theme-hex hex"
                        type="text"
                        data-key="<?= e($key) ?>"
                        value="<?= e($theme[$key]) ?>"
                        maxlength="7"
                        spellcheck="false"
                    >
                </div>
            <?php endforeach; ?>
        </div>
    </article>

    <div>
        <article class="ui-card">
            <div class="ui-card-header">
                <h2 class="ui-card-title">Typography</h2>
                <i data-lucide="type"></i>
            </div>

            <div class="theme-body typography-control">
                <div>
                    <div class="typography-label mb-2">
                        <div>
                            <strong>Application Font</strong>
                            <small>
                                Applied to all pages, forms and navigation.
                            </small>
                        </div>
                    </div>

                    <select id="fontStyle" class="form-select">
                        <?php foreach ($fontOptions as $fontKey => $font): ?>
                            <option
                                value="<?= e($fontKey) ?>"
                                <?= $theme['app_font_style'] === $fontKey
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= e($font['name']) ?>
                                — <?= e($font['description']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <small class="text-muted d-block mt-2">
                        The selected font is saved for the school and applied
                        automatically across all ERP pages.
                    </small>
                </div>

                <div>
                    <div class="typography-label mb-2">
                        <div>
                            <strong>Text Size</strong>
                            <small>
                                Use the buttons or slider to adjust the system.
                            </small>
                        </div>
                    </div>

                    <div class="font-size-control">
                        <button
                            id="decreaseFont"
                            class="btn-ui font-size-button"
                            type="button"
                            aria-label="Decrease text size"
                        >
                            <i data-lucide="minus"></i>
                        </button>

                        <input
                            id="fontSizeSlider"
                            class="form-range"
                            type="range"
                            min="14"
                            max="20"
                            step="1"
                            value="<?= e($theme['app_font_size']) ?>"
                        >

                        <output
                            id="fontSizeValue"
                            class="font-size-value"
                            for="fontSizeSlider"
                        >
                            <?= e($theme['app_font_size']) ?>px
                        </output>

                        <button
                            id="increaseFont"
                            class="btn-ui font-size-button"
                            type="button"
                            aria-label="Increase text size"
                        >
                            <i data-lucide="plus"></i>
                        </button>
                    </div>
                </div>

                <div id="typographySample" class="typography-sample">
                    <strong>School ERP Typography Preview</strong>
                    <small>
                        Students, academics, fees and reports remain clear
                        across the complete system.
                    </small>
                </div>
            </div>
        </article>

        <article class="ui-card mt-3">
            <div class="ui-card-header">
                <h2 class="ui-card-title">Live Preview</h2>
                <i data-lucide="monitor"></i>
            </div>

            <div class="ui-card-body">
                <div class="theme-preview">
                    <div class="preview-head"></div>

                    <h2 class="ui-card-title mt-3">
                        School ERP Dashboard
                    </h2>

                    <p class="page-subtitle">
                        Theme and typography update immediately.
                    </p>

                    <span class="preview-btn">
                        Primary Action
                    </span>
                </div>
            </div>
        </article>

        <article class="ui-card mt-3">
            <div class="ui-card-header">
                <h2 class="ui-card-title">Layout Density</h2>
                <i data-lucide="rows-3"></i>
            </div>

            <div class="theme-body">
                <select id="density" class="form-select">
                    <option
                        value="compact"
                        <?= $theme['layout_density'] === 'compact'
                            ? 'selected'
                            : '' ?>
                    >
                        Compact
                    </option>

                    <option
                        value="comfortable"
                        <?= $theme['layout_density'] === 'comfortable'
                            ? 'selected'
                            : '' ?>
                    >
                        Comfortable
                    </option>

                    <option
                        value="spacious"
                        <?= $theme['layout_density'] === 'spacious'
                            ? 'selected'
                            : '' ?>
                    >
                        Spacious
                    </option>
                </select>
            </div>
        </article>
    </div>
</div>

<script>
(function () {
    'use strict';

    const apiUrl = <?= json_encode(
        $baseUrl . 'api/theme-settings.php',
        JSON_UNESCAPED_SLASHES
    ) ?>;

    const csrfToken = <?= json_encode(
        $csrfToken,
        JSON_UNESCAPED_SLASHES
    ) ?>;

    const defaults = <?= json_encode(
        $defaults,
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    ) ?>;

    const presets = <?= json_encode(
        array_map(
            static fn(array $preset): array => $preset['settings'],
            $presets
        ),
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    ) ?>;

    let currentSettings = <?= json_encode(
        $theme,
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    ) ?>;

    const message = document.getElementById('themeMsg');
    const saveButton = document.getElementById('saveTheme');
    const density = document.getElementById('density');
    const fontStyle = document.getElementById('fontStyle');
    const fontSizeSlider = document.getElementById('fontSizeSlider');
    const fontSizeValue = document.getElementById('fontSizeValue');

    const validHex = value =>
        /^#[0-9a-fA-F]{6}$/.test(value);

    const clampFontSize = value =>
        Math.max(14, Math.min(20, Number.parseInt(value, 10) || 16));

    function updatePresetSelection(presetKey) {
        document
            .querySelectorAll('.theme-preset')
            .forEach(button => {
                button.classList.toggle(
                    'active',
                    button.dataset.preset === presetKey
                );
            });
    }

    function updateVisibleFields(settings) {
        document.querySelectorAll('[data-key]').forEach(input => {
            const key = input.dataset.key;

            if (Object.prototype.hasOwnProperty.call(settings, key)) {
                input.value = settings[key];
            }
        });

        density.value =
            settings.layout_density || 'comfortable';

        fontStyle.value =
            settings.app_font_style || 'plus_jakarta';

        const fontSize = clampFontSize(
            settings.app_font_size || 16
        );

        fontSizeSlider.value = String(fontSize);
        fontSizeValue.textContent = fontSize + 'px';
    }

    function applySettings(settings, broadcast) {
        currentSettings = {
            ...currentSettings,
            ...settings
        };

        if (window.SchoolTheme) {
            window.SchoolTheme.apply(currentSettings, broadcast === true);
        } else {
            Object.entries(currentSettings).forEach(([key, value]) => {
                if (validHex(String(value))) {
                    document.documentElement.style.setProperty(
                        '--' + key.replaceAll('_', '-'),
                        value
                    );
                }
            });

            document.documentElement.style.setProperty(
                '--app-font-size',
                clampFontSize(currentSettings.app_font_size) + 'px'
            );
        }

        updateVisibleFields(currentSettings);
        updatePresetSelection(
            currentSettings.theme_preset || 'custom'
        );
    }

    function markColorsAsCustom() {
        currentSettings.theme_preset = 'custom';
        updatePresetSelection('custom');
    }

    function showMessage(text, success) {
        message.className =
            'alert theme-msg show '
            + (success ? 'alert-success' : 'alert-danger');

        message.textContent = text;
    }

    async function readJsonResponse(response) {
        const responseText = await response.text();

        if (responseText.trim() === '') {
            throw new Error(
                'Theme API returned an empty response.'
            );
        }

        try {
            return JSON.parse(responseText);
        } catch (error) {
            console.error(
                'Theme API returned non-JSON output:',
                responseText
            );

            const readable = responseText
                .replace(/<style[\s\S]*?<\/style>/gi, ' ')
                .replace(/<script[\s\S]*?<\/script>/gi, ' ')
                .replace(/<[^>]+>/g, ' ')
                .replace(/\s+/g, ' ')
                .trim();

            throw new Error(
                readable
                    ? 'Server error: ' + readable.substring(0, 300)
                    : 'Theme API returned invalid JSON.'
            );
        }
    }

    document.querySelectorAll('.theme-preset').forEach(button => {
        button.addEventListener('click', () => {
            const presetKey = button.dataset.preset;
            const preset = presets[presetKey];

            if (!preset) {
                return;
            }

            applySettings(
                {
                    ...preset,
                    theme_preset: presetKey
                },
                false
            );

            showMessage(
                button.querySelector('strong').textContent
                    + ' applied to preview. Save to keep it.',
                true
            );
        });
    });

    document.querySelectorAll('.color').forEach(input => {
        input.addEventListener('input', () => {
            const value = input.value;

            if (!validHex(value)) {
                return;
            }

            markColorsAsCustom();

            applySettings(
                {
                    [input.dataset.key]: value,
                    theme_preset: 'custom'
                },
                false
            );
        });
    });

    document.querySelectorAll('.hex').forEach(input => {
        input.addEventListener('input', () => {
            const value = input.value.trim();

            if (!validHex(value)) {
                return;
            }

            markColorsAsCustom();

            applySettings(
                {
                    [input.dataset.key]: value,
                    theme_preset: 'custom'
                },
                false
            );
        });
    });

    fontStyle.addEventListener('change', () => {
        applySettings(
            {
                app_font_style: fontStyle.value
            },
            false
        );
    });

    function setFontSize(value) {
        const fontSize = clampFontSize(value);

        applySettings(
            {
                app_font_size: String(fontSize)
            },
            false
        );
    }

    fontSizeSlider.addEventListener('input', () => {
        setFontSize(fontSizeSlider.value);
    });

    document
        .getElementById('decreaseFont')
        .addEventListener('click', () => {
            setFontSize(
                clampFontSize(currentSettings.app_font_size) - 1
            );
        });

    document
        .getElementById('increaseFont')
        .addEventListener('click', () => {
            setFontSize(
                clampFontSize(currentSettings.app_font_size) + 1
            );
        });

    density.addEventListener('change', () => {
        applySettings(
            {
                layout_density: density.value
            },
            false
        );
    });

    document
        .getElementById('resetTheme')
        .addEventListener('click', () => {
            applySettings(defaults, false);

            showMessage(
                'Default Light theme and typography applied to preview. '
                    + 'Save to keep it.',
                true
            );
        });

    saveButton.addEventListener('click', async () => {
        saveButton.disabled = true;

        try {
            const response = await fetch(apiUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    csrf_token: csrfToken,
                    settings: currentSettings
                })
            });

            const result = await readJsonResponse(response);

            if (!response.ok || !result.success) {
                throw new Error(
                    result.message
                    || 'Unable to save Theme Settings.'
                );
            }

            currentSettings = {
                ...currentSettings,
                ...(result.data.settings || {})
            };

            applySettings(
                currentSettings,
                true
            );

            showMessage(result.message, true);
        } catch (error) {
            showMessage(
                error instanceof Error
                    ? error.message
                    : 'Unable to save Theme Settings.',
                false
            );
        } finally {
            saveButton.disabled = false;
        }
    });

    applySettings(currentSettings, false);
})();
</script>

<?php require dirname(__DIR__) . '/includes/layout-end.php'; ?>

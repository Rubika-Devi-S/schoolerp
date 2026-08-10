<?php
declare(strict_types=1);

/**
 * Shared theme definitions and database loader.
 *
 * This file has no HTML output, so it is safe to include from normal pages,
 * layout files and JSON APIs.
 */

if (!function_exists('school_theme_font_options')) {
    /**
     * @return array<string,array{name:string,stack:string,description:string}>
     */
    function school_theme_font_options(): array
    {
        return [
            'plus_jakarta' => [
                'name' => 'Plus Jakarta Sans',
                'stack' => "'Plus Jakarta Sans', Arial, sans-serif",
                'description' => 'Modern and professional',
            ],
            'inter' => [
                'name' => 'Inter',
                'stack' => "'Inter', Arial, sans-serif",
                'description' => 'Clean interface font',
            ],
            'poppins' => [
                'name' => 'Poppins',
                'stack' => "'Poppins', Arial, sans-serif",
                'description' => 'Friendly geometric style',
            ],
            'roboto' => [
                'name' => 'Roboto',
                'stack' => "'Roboto', Arial, sans-serif",
                'description' => 'Clear and familiar',
            ],
            'open_sans' => [
                'name' => 'Open Sans',
                'stack' => "'Open Sans', Arial, sans-serif",
                'description' => 'Highly readable',
            ],
            'lato' => [
                'name' => 'Lato',
                'stack' => "'Lato', Arial, sans-serif",
                'description' => 'Balanced and elegant',
            ],
            'montserrat' => [
                'name' => 'Montserrat',
                'stack' => "'Montserrat', Arial, sans-serif",
                'description' => 'Strong modern headings',
            ],
            'nunito' => [
                'name' => 'Nunito',
                'stack' => "'Nunito', Arial, sans-serif",
                'description' => 'Soft and approachable',
            ],
            'source_sans' => [
                'name' => 'Source Sans 3',
                'stack' => "'Source Sans 3', Arial, sans-serif",
                'description' => 'Professional application UI',
            ],
            'merriweather_sans' => [
                'name' => 'Merriweather Sans',
                'stack' => "'Merriweather Sans', Arial, sans-serif",
                'description' => 'Distinct and readable',
            ],
            'noto_sans' => [
                'name' => 'Noto Sans',
                'stack' => "'Noto Sans', Arial, sans-serif",
                'description' => 'Excellent multilingual support',
            ],
            'manrope' => [
                'name' => 'Manrope',
                'stack' => "'Manrope', Arial, sans-serif",
                'description' => 'Modern and highly readable',
            ],
            'dm_sans' => [
                'name' => 'DM Sans',
                'stack' => "'DM Sans', Arial, sans-serif",
                'description' => 'Clean administrative interface',
            ],
            'work_sans' => [
                'name' => 'Work Sans',
                'stack' => "'Work Sans', Arial, sans-serif",
                'description' => 'Optimized for screen readability',
            ],
            'rubik' => [
                'name' => 'Rubik',
                'stack' => "'Rubik', Arial, sans-serif",
                'description' => 'Rounded and professional',
            ],
            'mulish' => [
                'name' => 'Mulish',
                'stack' => "'Mulish', Arial, sans-serif",
                'description' => 'Simple and comfortable reading',
            ],
            'ubuntu' => [
                'name' => 'Ubuntu',
                'stack' => "'Ubuntu', Arial, sans-serif",
                'description' => 'Friendly and distinctive',
            ],
            'figtree' => [
                'name' => 'Figtree',
                'stack' => "'Figtree', Arial, sans-serif",
                'description' => 'Contemporary application font',
            ],
            'ibm_plex_sans' => [
                'name' => 'IBM Plex Sans',
                'stack' => "'IBM Plex Sans', Arial, sans-serif",
                'description' => 'Technical and professional',
            ],
            'system_ui' => [
                'name' => 'System UI',
                'stack' => "system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif",
                'description' => 'Fast native device font',
            ],
            'arial' => [
                'name' => 'Arial',
                'stack' => "Arial, Helvetica, sans-serif",
                'description' => 'Classic universal font',
            ],
            'trebuchet' => [
                'name' => 'Trebuchet MS',
                'stack' => "'Trebuchet MS', Arial, sans-serif",
                'description' => 'Clear and compact',
            ],
        ];
    }
}

if (!function_exists('school_theme_presets')) {
    /**
     * @return array<string,array{
     *     name:string,
     *     description:string,
     *     mode:string,
     *     settings:array<string,string>
     * }>
     */
    function school_theme_presets(): array
    {
        return [
            'light' => [
                'name' => 'Light',
                'description' => 'Clean neutral workspace',
                'mode' => 'light',
                'settings' => [
                    'sidebar_bg' => '#ffffff',
                    'sidebar_text' => '#334155',
                    'sidebar_active_bg_1' => '#6d4df2',
                    'sidebar_active_bg_2' => '#3559dc',
                    'sidebar_active_text' => '#ffffff',
                    'sidebar_hover_bg' => '#eef2ff',
                    'sidebar_hover_text' => '#27305f',
                    'topbar_bg_1' => '#653dd8',
                    'topbar_bg_2' => '#1959c8',
                    'topbar_text' => '#ffffff',
                    'body_bg' => '#f6f8fc',
                    'card_bg' => '#ffffff',
                    'text_main' => '#101b46',
                    'text_muted' => '#64748b',
                    'border_soft' => '#e7ebf3',
                    'brand_1' => '#6747e8',
                    'brand_2' => '#2f62d7',
                    'header_gradient_start' => '#653dd8',
                    'header_gradient_end' => '#1959c8',
                    'header_text' => '#ffffff',
                    'theme_preset' => 'light',
                ],
            ],
            'dark' => [
                'name' => 'Dark',
                'description' => 'Comfortable dark interface',
                'mode' => 'dark',
                'settings' => [
                    'sidebar_bg' => '#171923',
                    'sidebar_text' => '#d8deea',
                    'sidebar_active_bg_1' => '#7c5cff',
                    'sidebar_active_bg_2' => '#4f46e5',
                    'sidebar_active_text' => '#ffffff',
                    'sidebar_hover_bg' => '#252938',
                    'sidebar_hover_text' => '#ffffff',
                    'topbar_bg_1' => '#252938',
                    'topbar_bg_2' => '#171923',
                    'topbar_text' => '#f8fafc',
                    'body_bg' => '#0f1118',
                    'card_bg' => '#1a1e29',
                    'text_main' => '#f1f5f9',
                    'text_muted' => '#9ba8bb',
                    'border_soft' => '#303747',
                    'brand_1' => '#8b72ff',
                    'brand_2' => '#5b64ff',
                    'header_gradient_start' => '#2e3344',
                    'header_gradient_end' => '#181c27',
                    'header_text' => '#ffffff',
                    'theme_preset' => 'dark',
                ],
            ],
            'blue' => [
                'name' => 'Blue',
                'description' => 'Confident academic blue',
                'mode' => 'light',
                'settings' => [
                    'sidebar_bg' => '#f8fbff',
                    'sidebar_text' => '#1e3a5f',
                    'sidebar_active_bg_1' => '#1473e6',
                    'sidebar_active_bg_2' => '#1553b7',
                    'sidebar_active_text' => '#ffffff',
                    'sidebar_hover_bg' => '#e8f2ff',
                    'sidebar_hover_text' => '#15456f',
                    'topbar_bg_1' => '#1473e6',
                    'topbar_bg_2' => '#1747a2',
                    'topbar_text' => '#ffffff',
                    'body_bg' => '#f3f7fc',
                    'card_bg' => '#ffffff',
                    'text_main' => '#102a43',
                    'text_muted' => '#627d98',
                    'border_soft' => '#d9e6f3',
                    'brand_1' => '#1473e6',
                    'brand_2' => '#2563eb',
                    'header_gradient_start' => '#1473e6',
                    'header_gradient_end' => '#1747a2',
                    'header_text' => '#ffffff',
                    'theme_preset' => 'blue',
                ],
            ],
            'green' => [
                'name' => 'Green',
                'description' => 'Fresh calm green palette',
                'mode' => 'light',
                'settings' => [
                    'sidebar_bg' => '#f8fffb',
                    'sidebar_text' => '#1f4d3a',
                    'sidebar_active_bg_1' => '#16a36f',
                    'sidebar_active_bg_2' => '#087a55',
                    'sidebar_active_text' => '#ffffff',
                    'sidebar_hover_bg' => '#e7f8ef',
                    'sidebar_hover_text' => '#185f45',
                    'topbar_bg_1' => '#159a68',
                    'topbar_bg_2' => '#0d6f55',
                    'topbar_text' => '#ffffff',
                    'body_bg' => '#f4faf7',
                    'card_bg' => '#ffffff',
                    'text_main' => '#143c2d',
                    'text_muted' => '#648276',
                    'border_soft' => '#dceee5',
                    'brand_1' => '#16a36f',
                    'brand_2' => '#0d7c59',
                    'header_gradient_start' => '#159a68',
                    'header_gradient_end' => '#0d6f55',
                    'header_text' => '#ffffff',
                    'theme_preset' => 'green',
                ],
            ],
            'purple' => [
                'name' => 'Purple',
                'description' => 'Premium violet workspace',
                'mode' => 'light',
                'settings' => [
                    'sidebar_bg' => '#fbf9ff',
                    'sidebar_text' => '#46355f',
                    'sidebar_active_bg_1' => '#7c3aed',
                    'sidebar_active_bg_2' => '#5b21b6',
                    'sidebar_active_text' => '#ffffff',
                    'sidebar_hover_bg' => '#f0e9ff',
                    'sidebar_hover_text' => '#5b2b91',
                    'topbar_bg_1' => '#7c3aed',
                    'topbar_bg_2' => '#4c1d95',
                    'topbar_text' => '#ffffff',
                    'body_bg' => '#f8f5fc',
                    'card_bg' => '#ffffff',
                    'text_main' => '#35204d',
                    'text_muted' => '#78668b',
                    'border_soft' => '#e7def1',
                    'brand_1' => '#7c3aed',
                    'brand_2' => '#5b21b6',
                    'header_gradient_start' => '#7c3aed',
                    'header_gradient_end' => '#4c1d95',
                    'header_text' => '#ffffff',
                    'theme_preset' => 'purple',
                ],
            ],
            'red' => [
                'name' => 'Red',
                'description' => 'Bold professional red',
                'mode' => 'light',
                'settings' => [
                    'sidebar_bg' => '#fff9f9',
                    'sidebar_text' => '#5d2a31',
                    'sidebar_active_bg_1' => '#e23b4f',
                    'sidebar_active_bg_2' => '#b91c31',
                    'sidebar_active_text' => '#ffffff',
                    'sidebar_hover_bg' => '#fdecef',
                    'sidebar_hover_text' => '#7f1d2d',
                    'topbar_bg_1' => '#e23b4f',
                    'topbar_bg_2' => '#9f1239',
                    'topbar_text' => '#ffffff',
                    'body_bg' => '#fff6f7',
                    'card_bg' => '#ffffff',
                    'text_main' => '#4c1822',
                    'text_muted' => '#8b626a',
                    'border_soft' => '#f0d9de',
                    'brand_1' => '#e23b4f',
                    'brand_2' => '#be123c',
                    'header_gradient_start' => '#e23b4f',
                    'header_gradient_end' => '#9f1239',
                    'header_text' => '#ffffff',
                    'theme_preset' => 'red',
                ],
            ],
            'orange' => [
                'name' => 'Orange',
                'description' => 'Warm energetic interface',
                'mode' => 'light',
                'settings' => [
                    'sidebar_bg' => '#fffaf5',
                    'sidebar_text' => '#5c3a24',
                    'sidebar_active_bg_1' => '#f97316',
                    'sidebar_active_bg_2' => '#dc4c13',
                    'sidebar_active_text' => '#ffffff',
                    'sidebar_hover_bg' => '#fff0e4',
                    'sidebar_hover_text' => '#7a3e16',
                    'topbar_bg_1' => '#f97316',
                    'topbar_bg_2' => '#c2410c',
                    'topbar_text' => '#ffffff',
                    'body_bg' => '#fff7f1',
                    'card_bg' => '#ffffff',
                    'text_main' => '#4a2c1a',
                    'text_muted' => '#876957',
                    'border_soft' => '#f1dfd2',
                    'brand_1' => '#f97316',
                    'brand_2' => '#dc4c13',
                    'header_gradient_start' => '#f97316',
                    'header_gradient_end' => '#c2410c',
                    'header_text' => '#ffffff',
                    'theme_preset' => 'orange',
                ],
            ],
            'teal' => [
                'name' => 'Teal',
                'description' => 'Balanced teal workspace',
                'mode' => 'light',
                'settings' => [
                    'sidebar_bg' => '#f7fffe',
                    'sidebar_text' => '#184a48',
                    'sidebar_active_bg_1' => '#0f9f9a',
                    'sidebar_active_bg_2' => '#0f766e',
                    'sidebar_active_text' => '#ffffff',
                    'sidebar_hover_bg' => '#e4f8f6',
                    'sidebar_hover_text' => '#135f5b',
                    'topbar_bg_1' => '#0f9f9a',
                    'topbar_bg_2' => '#0b6964',
                    'topbar_text' => '#ffffff',
                    'body_bg' => '#f2faf9',
                    'card_bg' => '#ffffff',
                    'text_main' => '#123b39',
                    'text_muted' => '#60817e',
                    'border_soft' => '#d8ece9',
                    'brand_1' => '#0f9f9a',
                    'brand_2' => '#0f766e',
                    'header_gradient_start' => '#0f9f9a',
                    'header_gradient_end' => '#0b6964',
                    'header_text' => '#ffffff',
                    'theme_preset' => 'teal',
                ],
            ],
            'gray' => [
                'name' => 'Gray',
                'description' => 'Minimal graphite neutral',
                'mode' => 'light',
                'settings' => [
                    'sidebar_bg' => '#fafafa',
                    'sidebar_text' => '#3f4652',
                    'sidebar_active_bg_1' => '#697386',
                    'sidebar_active_bg_2' => '#424b5a',
                    'sidebar_active_text' => '#ffffff',
                    'sidebar_hover_bg' => '#eceff3',
                    'sidebar_hover_text' => '#303743',
                    'topbar_bg_1' => '#697386',
                    'topbar_bg_2' => '#3f4754',
                    'topbar_text' => '#ffffff',
                    'body_bg' => '#f4f5f7',
                    'card_bg' => '#ffffff',
                    'text_main' => '#252b35',
                    'text_muted' => '#6f7784',
                    'border_soft' => '#dfe3e8',
                    'brand_1' => '#697386',
                    'brand_2' => '#4b5563',
                    'header_gradient_start' => '#697386',
                    'header_gradient_end' => '#3f4754',
                    'header_text' => '#ffffff',
                    'theme_preset' => 'gray',
                ],
            ],
            'midnight' => [
                'name' => 'Midnight',
                'description' => 'Deep navy premium theme',
                'mode' => 'dark',
                'settings' => [
                    'sidebar_bg' => '#071426',
                    'sidebar_text' => '#c6d5e8',
                    'sidebar_active_bg_1' => '#2563eb',
                    'sidebar_active_bg_2' => '#4338ca',
                    'sidebar_active_text' => '#ffffff',
                    'sidebar_hover_bg' => '#112742',
                    'sidebar_hover_text' => '#ffffff',
                    'topbar_bg_1' => '#102a4c',
                    'topbar_bg_2' => '#071426',
                    'topbar_text' => '#f8fafc',
                    'body_bg' => '#06101e',
                    'card_bg' => '#0d1d31',
                    'text_main' => '#eef5ff',
                    'text_muted' => '#90a7c1',
                    'border_soft' => '#203955',
                    'brand_1' => '#3b82f6',
                    'brand_2' => '#6366f1',
                    'header_gradient_start' => '#163a68',
                    'header_gradient_end' => '#0a1830',
                    'header_text' => '#ffffff',
                    'theme_preset' => 'midnight',
                ],
            ],
        ];
    }
}

if (!function_exists('school_theme_default_settings')) {
    /** @return array<string,string> */
    function school_theme_default_settings(): array
    {
        $light = school_theme_presets()['light']['settings'];

        return array_merge($light, [
            'layout_density' => 'comfortable',
            'app_font_size' => '16',
            'app_font_style' => 'plus_jakarta',
        ]);
    }
}

if (!function_exists('school_theme_normalize_preset')) {
    function school_theme_normalize_preset(string $preset): string
    {
        $preset = strtolower(trim($preset));

        $legacyMap = [
            'classic_purple' => 'purple',
            'ocean_blue' => 'blue',
            'emerald_green' => 'green',
            'sunset_orange' => 'orange',
            'rose_magenta' => 'red',
        ];

        return $legacyMap[$preset] ?? $preset;
    }
}

if (!function_exists('school_theme_load_settings')) {
    /** @return array<string,string> */
    function school_theme_load_settings(
        ?PDO $pdo,
        int $tenantId
    ): array {
        $settings = school_theme_default_settings();

        if (!($pdo instanceof PDO)
            || !function_exists('school_table_exists')
            || !school_table_exists($pdo, 'website_color_settings')) {
            $sessionSettings = $_SESSION['school_theme_settings'] ?? [];

            return is_array($sessionSettings)
                ? array_merge($settings, $sessionSettings)
                : $settings;
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT setting_key, setting_value
                 FROM website_color_settings
                 WHERE tenant_id = :tenant_id
                   AND is_active = 1"
            );

            $stmt->execute(['tenant_id' => max(1, $tenantId)]);

            $fontOptions = school_theme_font_options();
            $presets = school_theme_presets();

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $key = trim((string)($row['setting_key'] ?? ''));
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
        } catch (Throwable $e) {
            error_log(
                'school_theme_load_settings: '
                . $e->getMessage()
            );
        }

        $_SESSION['school_theme_settings'] = $settings;

        return $settings;
    }
}

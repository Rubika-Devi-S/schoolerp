<?php
declare(strict_types=1);

/**
 * School ERP - Common Premium Toast System
 *
 * This file DEFINES helpers only. The shared layout calls
 * school_toast_render() once on normal HTML pages.
 * JSON API endpoints should not render this file.
 */

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

if (!function_exists('school_toast_normalize_type')) {
    function school_toast_normalize_type(string $type): string
    {
        $type = strtolower(trim($type));
        if (in_array($type, ['danger', 'failed', 'failure'], true)) {
            $type = 'error';
        }
        return in_array($type, ['success', 'error', 'warning', 'info'], true)
            ? $type
            : 'info';
    }
}

if (!function_exists('school_toast_clean_text')) {
    function school_toast_clean_text($value): string
    {
        if (!is_scalar($value) && !($value instanceof Stringable)) {
            return '';
        }

        $text = trim((string)$value);
        if ($text === '') {
            return '';
        }

        $text = strip_tags($text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return function_exists('mb_substr')
            ? mb_substr($text, 0, 2000, 'UTF-8')
            : substr($text, 0, 2000);
    }
}

if (!function_exists('school_toast_array_is_list')) {
    function school_toast_array_is_list(array $value): bool
    {
        $expected = 0;
        foreach ($value as $key => $_) {
            if ($key !== $expected++) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('school_toast_push')) {
    function school_toast_push(
        string $type,
        string $message,
        string $title = ''
    ): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $message = school_toast_clean_text($message);
        if ($message === '') {
            return;
        }

        if (!isset($_SESSION['_school_toasts']) || !is_array($_SESSION['_school_toasts'])) {
            $_SESSION['_school_toasts'] = [];
        }

        $_SESSION['_school_toasts'][] = [
            'type' => school_toast_normalize_type($type),
            'message' => $message,
            'title' => school_toast_clean_text($title),
        ];

        if (count($_SESSION['_school_toasts']) > 30) {
            $_SESSION['_school_toasts'] = array_slice($_SESSION['_school_toasts'], -30);
        }
    }
}

if (!function_exists('school_toast_success')) {
    function school_toast_success(string $message, string $title = ''): void
    {
        school_toast_push('success', $message, $title);
    }
}

if (!function_exists('school_toast_error')) {
    function school_toast_error(string $message, string $title = ''): void
    {
        school_toast_push('error', $message, $title);
    }
}

if (!function_exists('school_toast_warning')) {
    function school_toast_warning(string $message, string $title = ''): void
    {
        school_toast_push('warning', $message, $title);
    }
}

if (!function_exists('school_toast_info')) {
    function school_toast_info(string $message, string $title = ''): void
    {
        school_toast_push('info', $message, $title);
    }
}

if (!function_exists('school_toast_consume_session')) {
    function school_toast_consume_session(): array
    {
        static $consumed = false;

        if ($consumed || session_status() !== PHP_SESSION_ACTIVE) {
            return [];
        }
        $consumed = true;

        $toasts = [];

        $append = static function (string $defaultType, $value, string $defaultTitle = '') use (&$toasts): void {
            if (is_array($value)) {
                $isStructured = !school_toast_array_is_list($value)
                    && (isset($value['message']) || isset($value['text']) || isset($value['type']));

                if ($isStructured) {
                    $message = school_toast_clean_text($value['message'] ?? $value['text'] ?? '');
                    if ($message !== '') {
                        $toasts[] = [
                            'type' => school_toast_normalize_type((string)($value['type'] ?? $defaultType)),
                            'message' => $message,
                            'title' => school_toast_clean_text($value['title'] ?? $defaultTitle),
                        ];
                    }
                    return;
                }

                foreach ($value as $entry) {
                    if (is_array($entry)) {
                        $message = school_toast_clean_text($entry['message'] ?? $entry['text'] ?? '');
                        if ($message === '') {
                            continue;
                        }
                        $toasts[] = [
                            'type' => school_toast_normalize_type((string)($entry['type'] ?? $defaultType)),
                            'message' => $message,
                            'title' => school_toast_clean_text($entry['title'] ?? $defaultTitle),
                        ];
                    } else {
                        $message = school_toast_clean_text($entry);
                        if ($message !== '') {
                            $toasts[] = [
                                'type' => school_toast_normalize_type($defaultType),
                                'message' => $message,
                                'title' => school_toast_clean_text($defaultTitle),
                            ];
                        }
                    }
                }
                return;
            }

            $message = school_toast_clean_text($value);
            if ($message !== '') {
                $toasts[] = [
                    'type' => school_toast_normalize_type($defaultType),
                    'message' => $message,
                    'title' => school_toast_clean_text($defaultTitle),
                ];
            }
        };

        if (!empty($_SESSION['_school_toasts'])) {
            $append('info', $_SESSION['_school_toasts']);
            unset($_SESSION['_school_toasts']);
        }

        $keys = [
            'success_message' => 'success',
            'error_message' => 'error',
            'warning_message' => 'warning',
            'info_message' => 'info',
            'success' => 'success',
            'error' => 'error',
            'warning' => 'warning',
            'info' => 'info',
            'flash_success' => 'success',
            'flash_error' => 'error',
            'flash_warning' => 'warning',
            'flash_info' => 'info',
            'toast_success' => 'success',
            'toast_error' => 'error',
            'toast_warning' => 'warning',
            'toast_info' => 'info',
        ];

        foreach ($keys as $key => $type) {
            if (!array_key_exists($key, $_SESSION)) {
                continue;
            }
            $append($type, $_SESSION[$key]);
            unset($_SESSION[$key]);
        }

        if (array_key_exists('toast', $_SESSION)) {
            $append('info', $_SESSION['toast']);
            unset($_SESSION['toast']);
        }

        return array_slice($toasts, 0, 30);
    }
}

if (!function_exists('school_toast_markup')) {
    function school_toast_markup(): string
    {
        $payload = school_toast_consume_session();
        $json = json_encode(
            $payload,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if (!is_string($json)) {
            $json = '[]';
        }

        return '<div id="schoolToastContainer" class="school-toast-container" aria-live="polite" aria-atomic="false" aria-relevant="additions"></div>'
            . '<script type="application/json" id="schoolToastSessionPayload">' . $json . '</script>';
    }
}

if (!function_exists('school_toast_render')) {
    function school_toast_render(): void
    {
        static $rendered = false;
        if ($rendered) {
            return;
        }
        $rendered = true;
        echo school_toast_markup();
    }
}

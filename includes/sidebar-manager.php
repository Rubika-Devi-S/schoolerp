<?php
declare(strict_types=1);

/* Build: 2026-08-12-school-admin-display-order-fix-v22 */

require_once __DIR__ . '/sidebar-options.php';
require_once __DIR__ . '/permission-chain.php';

/*
 * Universal icon support is embedded in this file intentionally.
 * No extra helper file is required. Existing sidebar permission/runtime logic
 * below is unchanged.
 */

/**
 * Universal, database-safe sidebar icon support.
 *
 * Accepted values include:
 * - Lucide: house, lucide:house, <i data-lucide="house"></i>
 * - CSS icon classes: fa-solid fa-house, bi bi-house, ri-home-line,
 *   ti ti-home, mdi mdi-home, bx bx-home, ph ph-house, las la-home, etc.
 * - Material: material-icons:home, material-symbols-outlined:home
 * - Ionicons: ion:home-outline, <ion-icon name="home-outline"></ion-icon>
 * - Images: img:/assets/icon.svg, img:https://example.com/icon.png
 * - Emoji: emoji:🎓
 * - Sanitized inline SVG markup.
 */

if (!function_exists('school_sidebar_icon_resources_html')) {
    function school_sidebar_icon_resources_html(): string
    {
        static $emitted = false;
        if ($emitted) {
            return '';
        }
        $emitted = true;

        return <<<'HTML'
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Round|Material+Icons+Sharp|Material+Icons+Two+Tone">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&family=Material+Symbols+Sharp:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.9.1/fonts/remixicon.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css">
<link rel="stylesheet" href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/css/line-awesome.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/iconoir-icons/iconoir@main/css/iconoir.css">
<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.2/src/regular/style.css">
<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.2/src/fill/style.css">
<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.2/src/bold/style.css">
<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.2/src/duotone/style.css">
<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.2/src/light/style.css">
<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.2/src/thin/style.css">
<script src="https://cdn.jsdelivr.net/npm/iconify-icon@3.0.2/dist/iconify-icon.min.js"></script>
<script type="module" src="https://cdn.jsdelivr.net/npm/ionicons@7.4.0/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://cdn.jsdelivr.net/npm/ionicons@7.4.0/dist/ionicons/ionicons.js"></script>
<style>
.sidebar-universal-icon {
    width: 20px;
    height: 20px;
    min-width: 20px;
    flex: 0 0 20px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    overflow: visible;
    font-size: 19px;
    line-height: 1;
    vertical-align: middle;
}
iconify-icon.sidebar-universal-icon {
    font-size: 20px;
}
.sidebar-universal-icon.material-icons,
.sidebar-universal-icon.material-icons-outlined,
.sidebar-universal-icon.material-icons-round,
.sidebar-universal-icon.material-icons-sharp,
.sidebar-universal-icon.material-icons-two-tone,
.sidebar-universal-icon.material-symbols-outlined,
.sidebar-universal-icon.material-symbols-rounded,
.sidebar-universal-icon.material-symbols-sharp {
    font-size: 20px;
    font-weight: normal;
    font-style: normal;
    line-height: 1;
    letter-spacing: normal;
    text-transform: none;
    white-space: nowrap;
    word-wrap: normal;
    direction: ltr;
    -webkit-font-feature-settings: 'liga';
    -webkit-font-smoothing: antialiased;
    font-feature-settings: 'liga';
}
.sidebar-universal-icon-image {
    object-fit: contain;
    object-position: center;
}
.sidebar-universal-svg > svg,
.sidebar-universal-icon svg {
    width: 100%;
    height: 100%;
    display: block;
}
.sidebar-universal-emoji {
    font-family: "Apple Color Emoji", "Segoe UI Emoji", "Noto Color Emoji", sans-serif;
    font-size: 18px;
}
</style>
HTML;
    }
}

if (!function_exists('school_sidebar_icon_escape')) {
    function school_sidebar_icon_escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('school_sidebar_icon_safe_url')) {
    function school_sidebar_icon_safe_url(string $url): bool
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '' || preg_match('/[\x00-\x20<>"\'`]/u', $url)) {
            return false;
        }

        if (preg_match('~^https?://~i', $url)) {
            return filter_var($url, FILTER_VALIDATE_URL) !== false;
        }

        if (preg_match('~^data:image/(?:svg\+xml|png|gif|jpe?g|webp);base64,[a-z0-9+/=]+$~i', $url)) {
            return strlen($url) <= 4096;
        }

        if (preg_match('~^(?:/|\./|\.\./|[a-z0-9_./-]+\.(?:svg|png|gif|jpe?g|webp|ico)(?:\?[^#]*)?(?:#.*)?)~i', $url)) {
            return !str_contains($url, 'javascript:');
        }

        return false;
    }
}


if (!function_exists('school_sidebar_icon_sanitize_svg_fallback')) {
    function school_sidebar_icon_sanitize_svg_fallback(string $svg): string
    {
        $svg = trim(preg_replace('/<!--.*?-->/s', '', $svg) ?? '');
        if ($svg === '' || strlen($svg) > 4096
            || !preg_match('/^<svg\b/i', $svg)
            || preg_match('/<\s*(?:script|style|foreignObject|iframe|object|embed|use|image|animate|set|audio|video|canvas|link|meta)\b/i', $svg)
            || str_contains($svg, '<!')
            || str_contains($svg, '<?')) {
            return '';
        }

        $allowedElements = array_fill_keys([
            'svg', 'g', 'path', 'circle', 'ellipse', 'rect', 'line',
            'polyline', 'polygon', 'defs', 'lineargradient', 'radialgradient',
            'stop', 'clippath', 'mask', 'title', 'desc'
        ], true);
        $attributeNames = [
            'xmlns' => 'xmlns', 'viewbox' => 'viewBox', 'width' => 'width',
            'height' => 'height', 'fill' => 'fill', 'stroke' => 'stroke',
            'stroke-width' => 'stroke-width', 'stroke-linecap' => 'stroke-linecap',
            'stroke-linejoin' => 'stroke-linejoin', 'stroke-miterlimit' => 'stroke-miterlimit',
            'stroke-dasharray' => 'stroke-dasharray', 'stroke-dashoffset' => 'stroke-dashoffset',
            'd' => 'd', 'points' => 'points', 'cx' => 'cx', 'cy' => 'cy', 'r' => 'r',
            'rx' => 'rx', 'ry' => 'ry', 'x' => 'x', 'y' => 'y', 'x1' => 'x1',
            'y1' => 'y1', 'x2' => 'x2', 'y2' => 'y2', 'transform' => 'transform',
            'opacity' => 'opacity', 'fill-opacity' => 'fill-opacity',
            'stroke-opacity' => 'stroke-opacity', 'fill-rule' => 'fill-rule',
            'clip-rule' => 'clip-rule', 'offset' => 'offset', 'stop-color' => 'stop-color',
            'stop-opacity' => 'stop-opacity', 'id' => 'id', 'class' => 'class',
            'role' => 'role', 'aria-hidden' => 'aria-hidden', 'aria-label' => 'aria-label',
            'focusable' => 'focusable', 'preserveaspectratio' => 'preserveAspectRatio'
        ];

        $rootSeen = false;
        $failed = false;
        $clean = preg_replace_callback(
            '/<\s*(\/?)\s*([a-zA-Z][a-zA-Z0-9:-]*)([^>]*)>/s',
            static function (array $match) use (&$rootSeen, &$failed, $allowedElements, $attributeNames): string {
                $closing = $match[1] === '/';
                $tag = strtolower($match[2]);
                if (!isset($allowedElements[$tag])) {
                    $failed = true;
                    return '';
                }

                if ($closing) {
                    return '</' . $tag . '>';
                }

                $rawAttributes = $match[3];
                $selfClosing = preg_match('/\/\s*$/', $rawAttributes) === 1;
                $rawAttributes = preg_replace('/\/\s*$/', '', $rawAttributes) ?? '';
                $attributes = [];
                $consumed = preg_replace_callback(
                    '/([a-zA-Z_:][-a-zA-Z0-9_:.]*)\s*=\s*(["\'])(.*?)\2/s',
                    static function (array $attributeMatch) use (&$attributes, &$failed, $attributeNames): string {
                        $nameLower = strtolower($attributeMatch[1]);
                        $value = trim(html_entity_decode($attributeMatch[3], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                        $unsafeValue = preg_match('/(?:javascript:|data:text|<|>|expression\s*\()/i', $value);
                        $unsafeUrl = str_contains(strtolower($value), 'url(')
                            && !preg_match('/^url\(#[a-z0-9_-]+\)$/i', $value);
                        if (str_starts_with($nameLower, 'on')
                            || $nameLower === 'style'
                            || $nameLower === 'href'
                            || $nameLower === 'xlink:href'
                            || !isset($attributeNames[$nameLower])
                            || $unsafeValue
                            || $unsafeUrl) {
                            return '';
                        }
                        $attributes[$attributeNames[$nameLower]] = $value;
                        return '';
                    },
                    $rawAttributes
                );

                if ($consumed === null || trim($consumed) !== '') {
                    $failed = true;
                    return '';
                }

                if (!$rootSeen) {
                    if ($tag !== 'svg') {
                        $failed = true;
                        return '';
                    }
                    $rootSeen = true;
                    $attributes['aria-hidden'] = 'true';
                    $attributes['focusable'] = 'false';
                }

                $html = '<' . $tag;
                foreach ($attributes as $name => $value) {
                    $html .= ' ' . $name . '="'
                        . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                        . '"';
                }
                return $html . ($selfClosing ? '/>' : '>');
            },
            $svg
        );

        if ($clean === null || $failed || !$rootSeen
            || !preg_match('/^<svg\b/i', $clean)
            || !preg_match('/<\/svg>\s*$/i', $clean)
            || strlen($clean) > 4096) {
            return '';
        }

        return trim($clean);
    }
}

if (!function_exists('school_sidebar_icon_sanitize_svg')) {
    function school_sidebar_icon_sanitize_svg(string $svg): string
    {
        $svg = trim($svg);
        if ($svg === '' || strlen($svg) > 4096 || !str_starts_with(strtolower($svg), '<svg')) {
            return '';
        }

        if (!class_exists('DOMDocument')) {
            return school_sidebar_icon_sanitize_svg_fallback($svg);
        }

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        $loaded = $document->loadXML(
            $svg,
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NOBLANKS
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded || !$document->documentElement
            || strtolower($document->documentElement->localName) !== 'svg') {
            return school_sidebar_icon_sanitize_svg_fallback($svg);
        }

        $allowedElements = array_fill_keys([
            'svg', 'g', 'path', 'circle', 'ellipse', 'rect', 'line',
            'polyline', 'polygon', 'defs', 'lineargradient', 'radialgradient',
            'stop', 'clippath', 'mask', 'title', 'desc'
        ], true);

        $allowedAttributes = array_fill_keys([
            'xmlns', 'viewbox', 'width', 'height', 'fill', 'stroke',
            'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit',
            'stroke-dasharray', 'stroke-dashoffset', 'd', 'points', 'cx', 'cy',
            'r', 'rx', 'ry', 'x', 'y', 'x1', 'y1', 'x2', 'y2', 'transform',
            'opacity', 'fill-opacity', 'stroke-opacity', 'fill-rule', 'clip-rule',
            'offset', 'stop-color', 'stop-opacity', 'id', 'class', 'role',
            'aria-hidden', 'aria-label', 'focusable', 'preserveaspectratio'
        ], true);

        $walk = function (DOMNode $node) use (&$walk, $allowedElements, $allowedAttributes): void {
            for ($child = $node->firstChild; $child !== null;) {
                $next = $child->nextSibling;

                if ($child instanceof DOMElement) {
                    $name = strtolower($child->localName);
                    if (!isset($allowedElements[$name])) {
                        $node->removeChild($child);
                        $child = $next;
                        continue;
                    }

                    for ($index = $child->attributes->length - 1; $index >= 0; $index--) {
                        $attribute = $child->attributes->item($index);
                        if (!$attribute) {
                            continue;
                        }

                        $attributeName = strtolower($attribute->localName);
                        $value = trim($attribute->value);
                        $unsafeValue = preg_match('/(?:javascript:|data:text|<|>|expression\s*\()/i', $value);
                        $unsafeUrl = str_contains(strtolower($value), 'url(')
                            && !preg_match('/^url\(#[a-z0-9_-]+\)$/i', $value);

                        if (str_starts_with($attributeName, 'on')
                            || $attributeName === 'style'
                            || $attributeName === 'href'
                            || $attributeName === 'xlink:href'
                            || !isset($allowedAttributes[$attributeName])
                            || $unsafeValue
                            || $unsafeUrl) {
                            $child->removeAttributeNode($attribute);
                        }
                    }

                    $walk($child);
                } elseif (!($child instanceof DOMText)) {
                    $node->removeChild($child);
                }

                $child = $next;
            }
        };

        for ($index = $document->documentElement->attributes->length - 1; $index >= 0; $index--) {
            $attribute = $document->documentElement->attributes->item($index);
            if (!$attribute) {
                continue;
            }
            $attributeName = strtolower($attribute->localName);
            $value = trim($attribute->value);
            $unsafeValue = preg_match('/(?:javascript:|data:text|<|>|expression\s*\()/i', $value);
            $unsafeUrl = str_contains(strtolower($value), 'url(')
                && !preg_match('/^url\(#[a-z0-9_-]+\)$/i', $value);
            if (str_starts_with($attributeName, 'on')
                || $attributeName === 'style'
                || $attributeName === 'href'
                || $attributeName === 'xlink:href'
                || !isset($allowedAttributes[$attributeName])
                || $unsafeValue
                || $unsafeUrl) {
                $document->documentElement->removeAttributeNode($attribute);
            }
        }

        $walk($document->documentElement);
        $document->documentElement->setAttribute('aria-hidden', 'true');
        $document->documentElement->setAttribute('focusable', 'false');

        $clean = trim((string)$document->saveXML($document->documentElement));
        return strlen($clean) <= 4096 ? $clean : '';
    }
}

if (!function_exists('school_sidebar_icon_class_string')) {
    function school_sidebar_icon_class_string(string $classes): string
    {
        $classes = html_entity_decode($classes, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $classes = preg_replace('/\s+/u', ' ', trim($classes)) ?? '';
        if ($classes === '') {
            return '';
        }

        $tokens = preg_split('/\s+/', $classes) ?: [];
        if (count($tokens) > 12) {
            return '';
        }

        $clean = [];
        foreach ($tokens as $token) {
            $token = ltrim($token, '.');
            if ($token === '' || !preg_match('/^[a-zA-Z0-9_-]{1,80}$/', $token)) {
                return '';
            }
            $clean[] = $token;
        }

        $classes = implode(' ', array_values(array_unique($clean)));
        $lower = strtolower($classes);

        if (count($clean) === 1) {
            $token = $clean[0];
            $tokenLower = strtolower($token);
            if (str_starts_with($tokenLower, 'fa-')) return 'fa-solid ' . $token;
            if (str_starts_with($tokenLower, 'material-icons') || str_starts_with($tokenLower, 'material-symbols-')) return $token;
            if (str_starts_with($tokenLower, 'bi-')) return 'bi ' . $token;
            if (str_starts_with($tokenLower, 'ti-')) return 'ti ' . $token;
            if (str_starts_with($tokenLower, 'mdi-')) return 'mdi ' . $token;
            if (preg_match('/^bx[sl]?-/', $tokenLower)) return 'bx ' . $token;
            if (str_starts_with($tokenLower, 'ph-')) return 'ph ' . $token;
            if (str_starts_with($tokenLower, 'la-')) return 'las ' . $token;
            if (str_starts_with($tokenLower, 'iconoir-')) return $token;
            if (str_starts_with($tokenLower, 'ri-')) return $token;
        }

        $known = preg_match(
            '/(?:^|\s)(?:fa\w*|fas|far|fab|fal|fad|fat|fass|fasr|fasl|bi|ti|mdi|bx|bxs|bxl|ph|ph-fill|ph-bold|ph-duotone|ph-light|ph-thin|las|lar|lab|la|iconoir-[a-z0-9-]+|ri-[a-z0-9-]+)(?:\s|$)/i',
            $lower
        );

        // Safe class-only fallback keeps future icon-webfont libraries usable.
        return $known || preg_match('/(?:^|\s)(?:icon|ico)-[a-z0-9-]+(?:\s|$)/i', $lower)
            ? $classes
            : (count($clean) > 1 ? $classes : '');
    }
}

if (!function_exists('school_sidebar_icon_normalize')) {
    function school_sidebar_icon_normalize(string $value, string $fallback = 'circle'): string
    {
        $value = trim(str_replace("\0", '', $value));
        if ($value === '') {
            return $fallback;
        }
        if (strlen($value) > 4096) {
            return $fallback;
        }

        $lower = strtolower($value);

        if (str_starts_with($lower, 'svg:')) {
            $value = trim(substr($value, 4));
            $lower = strtolower($value);
        }

        if (str_starts_with($lower, '<svg')) {
            return school_sidebar_icon_sanitize_svg($value) ?: $fallback;
        }

        if (str_starts_with($value, '<')) {
            if (preg_match('/\bdata-lucide\s*=\s*(["\'])([^"\']+)\1/i', $value, $match)) {
                $value = 'lucide:' . trim($match[2]);
            } elseif (preg_match('/<iconify-icon\b[^>]*\bicon\s*=\s*(["\'])([^"\']+)\1/i', $value, $match)) {
                $value = 'iconify:' . trim($match[2]);
            } elseif (preg_match('/<ion-icon\b[^>]*\bname\s*=\s*(["\'])([^"\']+)\1/i', $value, $match)) {
                $value = 'ion:' . trim($match[2]);
            } elseif (preg_match('/<img\b[^>]*\bsrc\s*=\s*(["\'])([^"\']+)\1/i', $value, $match)) {
                $value = 'img:' . trim($match[2]);
            } elseif (preg_match('/<(?:i|span)\b[^>]*\bclass\s*=\s*(["\'])([^"\']+)\1[^>]*>(.*?)<\/(?:i|span)>/is', $value, $match)) {
                $classes = school_sidebar_icon_class_string($match[2]);
                $text = trim(strip_tags(html_entity_decode($match[3], ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                if ($classes !== '' && preg_match('/(?:^|\s)(material-icons(?:-[a-z]+)?|material-symbols-(?:outlined|rounded|sharp))(?:\s|$)/i', $classes, $material)) {
                    $value = strtolower($material[1]) . ':' . $text;
                } else {
                    $value = $classes;
                }
            } elseif (preg_match('/<(?:i|span)\b[^>]*\bclass\s*=\s*(["\'])([^"\']+)\1/i', $value, $match)) {
                $value = school_sidebar_icon_class_string($match[2]);
            } else {
                return $fallback;
            }
        }

        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? '';
        if ($value === '') {
            return $fallback;
        }

        if (preg_match('/^(lucide):([a-z0-9-]+)$/i', $value, $match)) {
            return 'lucide:' . strtolower($match[2]);
        }

        if (preg_match('/^(material-icons(?:-[a-z]+)?|material-symbols-(?:outlined|rounded|sharp)):([a-z0-9_-]+)$/i', $value, $match)) {
            return strtolower($match[1]) . ':' . strtolower($match[2]);
        }

        if (preg_match('/^iconify:([a-z0-9-]+:[a-z0-9-]+)$/i', $value, $match)) {
            return 'iconify:' . strtolower($match[1]);
        }

        if (preg_match('/^ion:([a-z0-9-]+)$/i', $value, $match)) {
            return 'ion:' . strtolower($match[1]);
        }

        if (str_starts_with(strtolower($value), 'img:')) {
            $url = trim(substr($value, 4));
            return school_sidebar_icon_safe_url($url) ? 'img:' . $url : $fallback;
        }

        // Direct image URL/path support: https://.../icon.svg, /assets/icon.png, data:image/...
        if (school_sidebar_icon_safe_url($value)) {
            $looksLikeImage = preg_match('~(?:\.(?:svg|png|gif|jpe?g|webp|ico)(?:[?#].*)?$|^data:image/)~i', $value) === 1;
            if ($looksLikeImage) {
                return 'img:' . $value;
            }
        }

        if (str_starts_with(strtolower($value), 'emoji:')) {
            $emoji = trim(substr($value, 6));
            $emojiLength = function_exists('mb_strlen')
                ? mb_strlen($emoji, 'UTF-8')
                : preg_match_all('/./us', $emoji, $matches);
            return $emoji !== '' && $emojiLength !== false && $emojiLength <= 16
                && !preg_match('/[<>&]/u', $emoji)
                ? 'emoji:' . $emoji
                : $fallback;
        }

        // Direct emoji/glyph support without requiring the emoji: prefix.
        if (preg_match('/[^\x00-\x7F]/u', $value) === 1) {
            $emojiLength = function_exists('mb_strlen')
                ? mb_strlen($value, 'UTF-8')
                : preg_match_all('/./us', $value, $matches);
            if ($emojiLength !== false && $emojiLength > 0 && $emojiLength <= 16
                && !preg_match('/[<>&]/u', $value)) {
                return 'emoji:' . $value;
            }
        }

        $classes = school_sidebar_icon_class_string($value);
        if ($classes !== '') {
            return $classes;
        }

        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/i', $value)) {
            return strtolower($value);
        }

        return $fallback;
    }
}

if (!function_exists('school_sidebar_icon_html')) {
    function school_sidebar_icon_html(string $value): string
    {
        $icon = school_sidebar_icon_normalize($value, 'circle');

        if (str_starts_with(strtolower($icon), '<svg')) {
            return '<span class="sidebar-universal-icon sidebar-universal-svg" aria-hidden="true">'
                . $icon
                . '</span>';
        }

        if (str_starts_with($icon, 'img:')) {
            $url = substr($icon, 4);
            return '<img class="sidebar-universal-icon sidebar-universal-icon-image" src="'
                . school_sidebar_icon_escape($url)
                . '" alt="" loading="lazy" decoding="async">';
        }

        if (str_starts_with($icon, 'emoji:')) {
            return '<span class="sidebar-universal-icon sidebar-universal-emoji" aria-hidden="true">'
                . school_sidebar_icon_escape(substr($icon, 6))
                . '</span>';
        }

        if (preg_match('/^(material-icons(?:-[a-z]+)?|material-symbols-(?:outlined|rounded|sharp)):(.+)$/i', $icon, $match)) {
            return '<span class="sidebar-universal-icon '
                . school_sidebar_icon_escape(strtolower($match[1]))
                . '" aria-hidden="true">'
                . school_sidebar_icon_escape($match[2])
                . '</span>';
        }

        if (str_starts_with($icon, 'iconify:')) {
            return '<iconify-icon class="sidebar-universal-icon" icon="'
                . school_sidebar_icon_escape(substr($icon, 8))
                . '" aria-hidden="true"></iconify-icon>';
        }

        if (str_starts_with($icon, 'ion:')) {
            return '<ion-icon class="sidebar-universal-icon" name="'
                . school_sidebar_icon_escape(substr($icon, 4))
                . '" aria-hidden="true"></ion-icon>';
        }

        if (str_starts_with($icon, 'lucide:')) {
            return '<i class="sidebar-universal-icon" data-lucide="'
                . school_sidebar_icon_escape(substr($icon, 7))
                . '" aria-hidden="true"></i>';
        }

        if (str_contains($icon, ' ')
            || preg_match('/^(?:fa|bi-|ri-|ti-|mdi-|bx[sl]?-|ph-|la-|iconoir-)/i', $icon)) {
            $classes = school_sidebar_icon_class_string($icon);
            if ($classes !== '') {
                return '<i class="sidebar-universal-icon '
                    . school_sidebar_icon_escape($classes)
                    . '" aria-hidden="true"></i>';
            }
        }

        return '<i class="sidebar-universal-icon" data-lucide="'
            . school_sidebar_icon_escape($icon)
            . '" aria-hidden="true"></i>';
    }
}


if (!function_exists('school_sidebar_menu_icon_html')) {
    function school_sidebar_menu_icon_html(string $icon): string
    {
        /*
         * Load the required icon web-font/component resources only once.
         * They are emitted with the first sidebar icon, so this one-file fix
         * works without changing layout-start.php, sidebar.php or bootstrap.php.
         */
        $resources = function_exists('school_sidebar_icon_resources_html')
            ? school_sidebar_icon_resources_html()
            : '';

        if (function_exists('school_sidebar_icon_html')) {
            return $resources . school_sidebar_icon_html($icon);
        }

        return $resources . '<i data-lucide="'
            . e($icon !== '' ? $icon : 'circle')
            . '"></i>';
    }
}

if (!function_exists('school_sidebar_table_exists')) {
    function school_sidebar_table_exists(PDO $pdo, string $table): bool
    {
        if (function_exists('school_table_exists')) {
            return school_table_exists($pdo, $table);
        }

        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = :table_name'
        );
        $stmt->execute(['table_name' => $table]);

        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('school_sidebar_column_exists')) {
    function school_sidebar_column_exists(
        PDO $pdo,
        string $table,
        string $column
    ): bool {
        if (function_exists('school_column_exists')) {
            return school_column_exists($pdo, $table, $column);
        }

        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = :table_name
               AND column_name = :column_name'
        );
        $stmt->execute([
            'table_name' => $table,
            'column_name' => $column,
        ]);

        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('school_sidebar_role_key')) {
    function school_sidebar_role_key(PDO $pdo, int $roleId): string
    {
        if ($roleId <= 0
            || !school_sidebar_table_exists($pdo, 'roles')) {
            return '';
        }

        $stmt = $pdo->prepare(
            "SELECT role_key
             FROM roles
             WHERE id = :role_id
               AND status = 'active'
             LIMIT 1"
        );
        $stmt->execute(['role_id' => $roleId]);

        return strtolower(trim((string)$stmt->fetchColumn()));
    }
}


if (!function_exists('school_sidebar_role_master_key')) {
    function school_sidebar_role_master_key(string $roleKey): string
    {
        $key = strtolower(trim($roleKey));
        $key = str_replace('-', '_', $key);

        return match ($key) {
            'school_administrator', 'schooladmin', 'school_admin_user',
            'branch_administrator', 'branch_admin', 'administrator', 'admin'
                => 'school_admin',
            'super_administrator' => 'super_admin',
            default => $key,
        };
    }
}

if (!function_exists('school_sidebar_is_super_admin_role')) {
    function school_sidebar_is_super_admin_role(string $roleKey): bool
    {
        return in_array(
            $roleKey,
            [
                'super_admin',
                'super-administrator',
                'super_administrator',
            ],
            true
        );
    }
}


if (!function_exists('school_sidebar_is_school_admin_role')) {
    function school_sidebar_is_school_admin_role(string $roleKey): bool
    {
        return in_array(
            strtolower(trim($roleKey)),
            [
                'school_admin',
                'school-administrator',
                'school_administrator',
            ],
            true
        );
    }
}

if (!function_exists('school_sidebar_load_database_items')) {
    /**
     * Load the EFFECTIVE School sidebar for one tenant + role.
     *
     * Visibility source of truth:
     *   1. sidebar_items                 = shared/default master definition
     *   2. sidebar_default_settings      = Default Sidebar ON/OFF + order
     *   3. tenant_sidebar_items          = OPTIONAL school override
     *
     * IMPORTANT:
     * - Missing tenant_sidebar_items row means INHERIT Default Sidebar.
     * - A school override affects only that school.
     * - A newly-added Default Sidebar item is immediately eligible for every
     *   school without inserting a tenant_sidebar_items row for every tenant.
     * - tenant_modules is intentionally NOT used as a second visibility gate.
     *   School Sidebar Permissions is the authoritative sidebar visibility UI.
     *
     * View permission precedence:
     *   school_sidebar_permission_grants (explicit normalized grant)
     *   -> school_sidebar_action_permissions (legacy/current action matrix)
     *   -> role_sidebar_permissions (older compatibility)
     *   -> School Administrator default allow when no explicit row exists.
     *
     * @return array<int,array<string,mixed>>
     */
    function school_sidebar_load_database_items(
        PDO $pdo,
        int $roleId,
        int $tenantId
    ): array {
        if (
            $roleId <= 0
            || $tenantId <= 0
            || !school_sidebar_table_exists($pdo, 'sidebar_items')
        ) {
            return [];
        }

        $hasTenantOverrides = school_sidebar_table_exists(
            $pdo,
            'tenant_sidebar_items'
        );
        $hasDefaultSettings = school_sidebar_table_exists(
            $pdo,
            'sidebar_default_settings'
        );
        $hasInheritance = $hasTenantOverrides
            && school_sidebar_column_exists(
                $pdo,
                'tenant_sidebar_items',
                'inherit_default'
            );

        $hasDynamicPermissions = school_sidebar_table_exists(
            $pdo,
            'school_sidebar_permission_grants'
        );
        $hasActionPermissions = school_sidebar_table_exists(
            $pdo,
            'school_sidebar_action_permissions'
        );
        $hasLegacyPermissions = school_sidebar_table_exists(
            $pdo,
            'role_sidebar_permissions'
        );

        $roleKey = school_sidebar_role_key($pdo, $roleId);
        $isSchoolAdmin = school_sidebar_is_school_admin_role($roleKey);
        $masterRoleKey = school_sidebar_role_master_key($roleKey);
        $hasRoleMaster = $masterRoleKey !== ''
            && $masterRoleKey !== 'super_admin'
            && school_sidebar_table_exists(
                $pdo,
                'sidebar_role_master_items'
            );

        $roleMasterJoin = $hasRoleMaster
            ? "LEFT JOIN sidebar_role_master_items AS srm
                   ON srm.sidebar_item_id = si.id
                  AND srm.role_key = :master_role_key"
            : '';

        $roleMasterCondition = '';
        if ($hasRoleMaster) {
            /*
             * Shared items must belong to the selected role master. Existing
             * school-owned custom menus remain compatible for non-Parent roles.
             * Parent never inherits School Admin/school-owned menu definitions.
             */
            if ($masterRoleKey === 'parent') {
                $roleMasterCondition =
                    ' AND srm.sidebar_item_id IS NOT NULL'
                    . ' AND COALESCE(srm.is_enabled,0)=1';
            } else {
                $roleMasterCondition = school_sidebar_column_exists(
                    $pdo,
                    'sidebar_items',
                    'owner_tenant_id'
                )
                    ? " AND (
                        (
                            srm.sidebar_item_id IS NOT NULL
                            AND COALESCE(srm.is_enabled,0)=1
                        )
                        OR si.owner_tenant_id=:role_master_owner_id
                    )"
                    : ' AND srm.sidebar_item_id IS NOT NULL'
                        . ' AND COALESCE(srm.is_enabled,0)=1';
            }
        }

        $hasPortalScope = school_sidebar_column_exists(
            $pdo,
            'sidebar_items',
            'portal_scope'
        );
        $hasOwnerTenant = school_sidebar_column_exists(
            $pdo,
            'sidebar_items',
            'owner_tenant_id'
        );
        $hasCustomRoute = $hasTenantOverrides
            && school_sidebar_column_exists(
                $pdo,
                'tenant_sidebar_items',
                'custom_route'
            );
        $hasCustomParent = $hasTenantOverrides
            && school_sidebar_column_exists(
                $pdo,
                'tenant_sidebar_items',
                'custom_parent_id'
            );
        $hasCustomTitle = $hasTenantOverrides
            && school_sidebar_column_exists(
                $pdo,
                'tenant_sidebar_items',
                'custom_title'
            );
        $hasCustomIcon = $hasTenantOverrides
            && school_sidebar_column_exists(
                $pdo,
                'tenant_sidebar_items',
                'custom_icon'
            );
        $hasOverrideOrder = $hasTenantOverrides
            && school_sidebar_column_exists(
                $pdo,
                'tenant_sidebar_items',
                'display_order'
            );

        $defaultJoin = $hasDefaultSettings
            ? "LEFT JOIN sidebar_default_settings AS sds
                   ON sds.sidebar_item_id = si.id"
            : '';

        $overrideJoin = $hasTenantOverrides
            ? "LEFT JOIN tenant_sidebar_items AS tsi
                   ON tsi.sidebar_item_id = si.id
                  AND tsi.tenant_id = :override_tenant_id"
            : '';

        if ($hasTenantOverrides) {
            /*
             * New model: absence of a tenant row means inherit. Old databases
             * may still contain inherit_default=1 placeholder rows; treat those
             * exactly like absence so upgrades remain safe.
             */
            $inheritsDefault = $hasInheritance
                ? '(tsi.id IS NULL OR COALESCE(tsi.inherit_default,1)=1)'
                : '(tsi.id IS NULL)';

            $effectiveParent = $hasCustomParent
                ? "CASE WHEN {$inheritsDefault}
                     THEN si.parent_id
                     ELSE COALESCE(tsi.custom_parent_id,si.parent_id)
                   END"
                : 'si.parent_id';

            $effectiveRoute = $hasCustomRoute
                ? "CASE WHEN {$inheritsDefault}
                     THEN si.route
                     ELSE COALESCE(NULLIF(TRIM(tsi.custom_route),''),si.route)
                   END"
                : 'si.route';

            $displayTitle = $hasCustomTitle
                ? "CASE WHEN {$inheritsDefault}
                     THEN si.menu_title
                     ELSE COALESCE(NULLIF(TRIM(tsi.custom_title),''),si.menu_title)
                   END"
                : 'si.menu_title';

            $displayIcon = $hasCustomIcon
                ? "CASE WHEN {$inheritsDefault}
                     THEN si.icon
                     ELSE COALESCE(NULLIF(TRIM(tsi.custom_icon),''),si.icon)
                   END"
                : 'si.icon';

            $defaultOrder = $hasDefaultSettings
                ? 'COALESCE(sds.display_order,si.display_order)'
                : 'si.display_order';

            $effectiveOrder = $hasOverrideOrder
                ? "CASE WHEN {$inheritsDefault}
                     THEN {$defaultOrder}
                     ELSE COALESCE(tsi.display_order,{$defaultOrder})
                   END"
                : $defaultOrder;

            $defaultVisible = $hasDefaultSettings
                ? 'COALESCE(sds.is_enabled,1)'
                : '1';

            $effectiveVisible = "CASE WHEN {$inheritsDefault}
                THEN {$defaultVisible}
                ELSE COALESCE(tsi.is_visible,0)
            END";
        } else {
            $effectiveParent = 'si.parent_id';
            $effectiveRoute = 'si.route';
            $displayTitle = 'si.menu_title';
            $displayIcon = 'si.icon';
            $effectiveOrder = $hasDefaultSettings
                ? 'COALESCE(sds.display_order,si.display_order)'
                : 'si.display_order';
            $effectiveVisible = $hasDefaultSettings
                ? 'COALESCE(sds.is_enabled,1)'
                : '1';
        }

        if ($hasRoleMaster) {
            /*
             * DISPLAY ORDER PRECEDENCE
             * ------------------------
             * School Admin must use the exact same order precedence as the
             * Super Admin School Sidebar Permissions screen:
             *
             *   explicit school override
             *   -> Default Sidebar order
             *   -> School Admin role-master snapshot
             *   -> sidebar_items order
             *
             * V21 put srm.display_order FIRST for every role. Because the
             * School Admin role-master is a compatibility snapshot, that could
             * be stale after Super Admin changed the Default/School order and
             * caused the runtime sidebar to appear in the old order.
             *
             * Parent/other role masters remain role-master-first because their
             * master ordering is intentionally independent from School Admin.
             */
            if ($isSchoolAdmin) {
                $schoolAdminDefaultOrder = $hasDefaultSettings
                    ? 'COALESCE(sds.display_order,srm.display_order,si.display_order)'
                    : 'COALESCE(srm.display_order,si.display_order)';

                if ($hasTenantOverrides && $hasOverrideOrder) {
                    $effectiveOrder = "CASE WHEN {$inheritsDefault}
                         THEN {$schoolAdminDefaultOrder}
                         ELSE COALESCE(tsi.display_order,{$schoolAdminDefaultOrder})
                       END";
                } else {
                    $effectiveOrder = $schoolAdminDefaultOrder;
                }
            } else {
                $effectiveOrder =
                    "COALESCE(srm.display_order,{$effectiveOrder})";
            }
        }

        $scopeCondition = $hasPortalScope
            ? "si.portal_scope IN ('school','all')"
            : "si.menu_key NOT LIKE 'sa\\_%'";

        if ($hasOwnerTenant) {
            $scopeCondition .= " AND (
                si.owner_tenant_id IS NULL
                OR si.owner_tenant_id = :owner_tenant_id
            )";
        }

        /*
         * Join every permission generation that may exist. This is deliberate:
         * merely having the newer grant table must not make older valid
         * can_view rows disappear. An explicit normalized grant wins first.
         */
        $permissionJoins = [];
        if ($hasDynamicPermissions) {
            $permissionJoins[] = "LEFT JOIN school_sidebar_permission_grants AS spg
                ON spg.sidebar_item_id = si.id
               AND spg.tenant_id = :grant_tenant_id
               AND spg.role_id = :grant_role_id
               AND spg.action_key = 'view'";
        }
        if ($hasActionPermissions) {
            $permissionJoins[] = "LEFT JOIN school_sidebar_action_permissions AS sap
                ON sap.sidebar_item_id = si.id
               AND sap.tenant_id = :action_tenant_id
               AND sap.role_id = :action_role_id";
        }
        if ($hasLegacyPermissions) {
            $permissionJoins[] = "LEFT JOIN role_sidebar_permissions AS rsp
                ON rsp.sidebar_item_id = si.id
               AND rsp.role_id = :legacy_role_id";
        }

        $fallbackView = $isSchoolAdmin ? '1' : '0';
        $viewCases = [];
        if ($hasDynamicPermissions) {
            $viewCases[] = 'WHEN spg.sidebar_item_id IS NOT NULL THEN COALESCE(spg.is_allowed,0)';
        }
        if ($hasActionPermissions) {
            $viewCases[] = 'WHEN sap.sidebar_item_id IS NOT NULL THEN COALESCE(sap.can_view,0)';
        }
        if ($hasLegacyPermissions) {
            $viewCases[] = 'WHEN rsp.sidebar_item_id IS NOT NULL THEN COALESCE(rsp.can_show,0)';
        }

        $actionSelect = $viewCases
            ? 'CASE ' . implode(' ', $viewCases) . ' ELSE ' . $fallbackView . ' END AS can_view'
            : $fallbackView . ' AS can_view';

        $sql = "SELECT
                    si.id,
                    {$effectiveParent} AS parent_id,
                    si.module_id,
                    si.menu_key,
                    {$effectiveRoute} AS route,
                    si.badge_text,
                    si.badge_variant,
                    si.display_order,
                    si.is_active,
                    si.show_in_sidebar,
                    {$actionSelect},
                    {$displayTitle} AS display_title,
                    {$displayIcon} AS display_icon,
                    {$effectiveOrder} AS effective_order,
                    {$effectiveVisible} AS is_visible
                FROM sidebar_items AS si
                {$defaultJoin}
                {$overrideJoin}
                {$roleMasterJoin}
                " . implode("\n", $permissionJoins) . "
                WHERE si.is_active=1
                  AND si.show_in_sidebar=1
                  AND ({$effectiveVisible})=1
                  AND {$scopeCondition}
                  {$roleMasterCondition}
                ORDER BY
                    COALESCE({$effectiveParent},0),
                    effective_order,
                    si.id";

        $statement = $pdo->prepare($sql);
        $parameters = [];

        if ($hasTenantOverrides) {
            $parameters['override_tenant_id'] = $tenantId;
        }
        if ($hasDynamicPermissions) {
            $parameters['grant_tenant_id'] = $tenantId;
            $parameters['grant_role_id'] = $roleId;
        }
        if ($hasActionPermissions) {
            $parameters['action_tenant_id'] = $tenantId;
            $parameters['action_role_id'] = $roleId;
        }
        if ($hasLegacyPermissions) {
            $parameters['legacy_role_id'] = $roleId;
        }
        if ($hasOwnerTenant) {
            $parameters['owner_tenant_id'] = $tenantId;
        }
        if ($hasRoleMaster) {
            $parameters['master_role_key'] = $masterRoleKey;
            if ($masterRoleKey !== 'parent' && $hasOwnerTenant) {
                $parameters['role_master_owner_id'] = $tenantId;
            }
        }

        $statement->execute($parameters);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows) || $rows === []) {
            return [];
        }

        $itemsById = [];
        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $row['id'] = $id;
            $row['parent_id'] = !empty($row['parent_id'])
                ? (int)$row['parent_id']
                : null;
            $row['display_order'] = (int)(
                $row['effective_order']
                ?? $row['display_order']
                ?? 0
            );
            $row['is_visible'] = (int)($row['is_visible'] ?? 0);
            $row['can_view'] = pc_effective_role_action(
                $pdo,
                $tenantId,
                $roleId,
                $id,
                'view'
            ) ? 1 : 0;
            $itemsById[$id] = $row;
        }

        /*
         * A child is visible only when its complete parent chain is enabled and
         * View-permitted. A malformed parent chain fails closed.
         */
        $allowedCache = [];
        $visiting = [];
        $isAllowed = static function (int $itemId)
            use (&$isAllowed, &$allowedCache, &$visiting, $itemsById): bool {
            if (array_key_exists($itemId, $allowedCache)) {
                return $allowedCache[$itemId];
            }
            if (isset($visiting[$itemId]) || !isset($itemsById[$itemId])) {
                return $allowedCache[$itemId] = false;
            }

            $visiting[$itemId] = true;
            $item = $itemsById[$itemId];
            if ((int)$item['is_visible'] !== 1
                || (int)$item['can_view'] !== 1) {
                unset($visiting[$itemId]);
                return $allowedCache[$itemId] = false;
            }

            $parentId = (int)($item['parent_id'] ?? 0);
            if ($parentId <= 0) {
                unset($visiting[$itemId]);
                return $allowedCache[$itemId] = true;
            }

            $allowed = $isAllowed($parentId);
            unset($visiting[$itemId]);
            return $allowedCache[$itemId] = $allowed;
        };

        $visibleItems = [];
        foreach ($itemsById as $id => $item) {
            if ($isAllowed($id)) {
                unset($item['can_view'], $item['is_visible'], $item['effective_order']);
                $visibleItems[$id] = $item;
            }
        }

        return array_values($visibleItems);
    }
}

if (!function_exists('school_sidebar_get_items')) {
    /**
     * @return array<int,array<string,mixed>>
     */
    function school_sidebar_get_items(
        ?PDO $pdo,
        int $roleId,
        int $tenantId
    ): array {
        if ($pdo instanceof PDO) {
            try {
                $databaseItems = school_sidebar_load_database_items(
                    $pdo,
                    $roleId,
                    $tenantId
                );

                /*
                 * An empty result with installed tables means the role has no
                 * permission. Do not expose the full fallback menu.
                 */
                if (
                    school_sidebar_table_exists($pdo, 'sidebar_items')
                    && (
                        school_sidebar_table_exists(
                            $pdo,
                            'school_sidebar_action_permissions'
                        )
                        || school_sidebar_table_exists(
                            $pdo,
                            'role_sidebar_permissions'
                        )
                    )
                ) {
                    return $databaseItems;
                }
            } catch (Throwable $e) {
                error_log(
                    'School Admin sidebar database load failed: '
                    . $e->getMessage()
                );

                /*
                 * Fail closed. A database error must never expose every
                 * fallback menu to a school user.
                 */
                if (school_sidebar_table_exists($pdo, 'sidebar_items')) {
                    return [];
                }
            }
        }

        return school_sidebar_fallback_menus();
    }
}

if (!function_exists('school_sidebar_build_tree')) {
    /**
     * @param array<int,array<string,mixed>> $items
     * @return array<int,array<string,mixed>>
     */
    function school_sidebar_build_tree(array $items): array
    {
        $lookup = [];
        $tree = [];

        foreach ($items as $item) {
            $id = (int)($item['id'] ?? 0);

            if ($id <= 0) {
                continue;
            }

            $item += [
                'parent_id' => null,
                'menu_key' => '',
                'display_title' => 'Menu',
                'route' => '#',
                'display_icon' => 'circle',
                'badge_text' => null,
                'badge_variant' => null,
                'display_order' => 0,
            ];

            $item['children'] = [];
            $lookup[$id] = $item;
        }

        foreach ($lookup as $id => $item) {
            $parentId = (int)($item['parent_id'] ?? 0);

            if ($parentId > 0 && isset($lookup[$parentId])) {
                $lookup[$parentId]['children'][] = &$lookup[$id];
            }
        }

        foreach ($lookup as $id => &$item) {
            $parentId = (int)($item['parent_id'] ?? 0);

            if ($parentId <= 0 || !isset($lookup[$parentId])) {
                $tree[] = &$item;
            }
        }
        unset($item);

        /*
         * Do not rely on SQL row order after the hierarchy is assembled.
         * Sort every sibling group explicitly by the effective display_order
         * used by the Super Admin sidebar configuration, then by id for a
         * deterministic tie-breaker. This mirrors the permission-page tree.
         */
        $sortTree = static function (array &$nodes) use (&$sortTree): void {
            usort(
                $nodes,
                static fn(array $a, array $b): int => [
                    (int)($a['display_order'] ?? 0),
                    (int)($a['id'] ?? 0),
                ] <=> [
                    (int)($b['display_order'] ?? 0),
                    (int)($b['id'] ?? 0),
                ]
            );

            foreach ($nodes as &$node) {
                if (
                    isset($node['children'])
                    && is_array($node['children'])
                    && $node['children'] !== []
                ) {
                    $sortTree($node['children']);
                }
            }
            unset($node);
        };

        $sortTree($tree);

        return $tree;
    }
}

if (!function_exists('school_sidebar_resolve_route')) {
    function school_sidebar_resolve_route(string $route): string
    {
        $route = trim($route);

        if ($route === '' || $route === '#'
            || preg_match('~^(?:https?:)?//~i', $route)) {
            return $route === '' ? '#' : $route;
        }

        if (defined('SCHOOL_ADMIN_ROUTE_PREFIX')) {
            $prefix = trim((string)SCHOOL_ADMIN_ROUTE_PREFIX, '/');

            if ($prefix !== '') {
                return $prefix . '/' . ltrim($route, '/');
            }
        }

        /*
         * Support projects where School Admin pages are kept in /school.
         * Existing root routes continue working without configuration.
         */
        if (defined('PROJECT_ROOT')) {
            $rootPath = rtrim((string)PROJECT_ROOT, '/\\');
            $routePath = ltrim(
                (string)(parse_url($route, PHP_URL_PATH) ?: $route),
                '/'
            );

            if (!is_file($rootPath . '/' . $routePath)
                && is_file($rootPath . '/school/' . $routePath)) {
                return 'school/' . $routePath;
            }
        }

        return ltrim($route, '/');
    }
}

if (!function_exists('school_sidebar_href')) {
    function school_sidebar_href(string $route, string $baseUrl): string
    {
        $resolvedRoute = school_sidebar_resolve_route($route);

        if ($resolvedRoute === '#'
            || preg_match('~^(?:https?:)?//~i', $resolvedRoute)) {
            return $resolvedRoute;
        }

        return $baseUrl . ltrim($resolvedRoute, '/');
    }
}

if (!function_exists('school_sidebar_item_active')) {
    /**
     * @param array<string,mixed> $item
     */
    function school_sidebar_item_active(
        array $item,
        string $currentRequestPath,
        string $baseUrl,
        string $activePageKey
    ): bool {
        $menuKey = trim((string)($item['menu_key'] ?? ''));

        if ($activePageKey !== '' && $menuKey === $activePageKey) {
            return true;
        }

        $route = trim((string)($item['route'] ?? '#'));

        if ($route === '' || $route === '#') {
            return false;
        }

        $routePath = trim(
            (string)(
                parse_url(
                    school_sidebar_href($route, $baseUrl),
                    PHP_URL_PATH
                ) ?: ''
            ),
            '/'
        );

        if ($routePath === '') {
            return false;
        }

        return $currentRequestPath === $routePath
            || str_ends_with($currentRequestPath, '/' . $routePath);
    }
}

if (!function_exists('school_sidebar_has_active_child')) {
    /**
     * @param array<string,mixed> $item
     */
    function school_sidebar_has_active_child(
        array $item,
        string $currentRequestPath,
        string $baseUrl,
        string $activePageKey
    ): bool {
        foreach (($item['children'] ?? []) as $child) {
            if (school_sidebar_item_active(
                $child,
                $currentRequestPath,
                $baseUrl,
                $activePageKey
            )) {
                return true;
            }

            if (school_sidebar_has_active_child(
                $child,
                $currentRequestPath,
                $baseUrl,
                $activePageKey
            )) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('school_sidebar_render_items')) {
    /**
     * @param array<int,array<string,mixed>> $items
     */
    function school_sidebar_render_items(
        array $items,
        string $currentRequestPath,
        string $baseUrl,
        string $activePageKey,
        int $level = 0
    ): void {
        foreach ($items as $item) {
            $children = is_array($item['children'] ?? null)
                ? $item['children']
                : [];

            $mainActive = school_sidebar_item_active(
                $item,
                $currentRequestPath,
                $baseUrl,
                $activePageKey
            );

            $childActive = school_sidebar_has_active_child(
                $item,
                $currentRequestPath,
                $baseUrl,
                $activePageKey
            );

            $active = $mainActive || $childActive;
            $hasChildren = count($children) > 0;
            $collapseId = 'schoolSidebarMenu'
                . (int)($item['id'] ?? 0);

            $childClass = $level > 0 ? ' sidebar-child' : '';
            $nestedPadding = $level > 1
                ? ' style="padding-left:'
                    . (34 + (($level - 1) * 14))
                    . 'px"'
                : '';

            $badgeVariant = trim(
                (string)($item['badge_variant'] ?? '')
            );

            $badgeClass = $badgeVariant !== ''
                ? ' ' . preg_replace(
                    '/[^a-zA-Z0-9_-]/',
                    '',
                    $badgeVariant
                )
                : '';
            ?>
            <?php if ($hasChildren): ?>
                <button
                    type="button"
                    class="sidebar-link sidebar-parent<?= $childClass ?> <?= $active ? 'active' : '' ?>"
                    data-bs-toggle="collapse"
                    data-bs-target="#<?= e($collapseId) ?>"
                    aria-expanded="<?= $childActive ? 'true' : 'false' ?>"
                    aria-controls="<?= e($collapseId) ?>"
                    <?= $nestedPadding ?>
                >
                    <?= school_sidebar_menu_icon_html(
                        (string)($item['display_icon'] ?? 'circle')
                    ) ?>

                    <span><?= e(
                        $item['display_title'] ?? 'Menu'
                    ) ?></span>

                    <?php if (!empty($item['badge_text'])): ?>
                        <em class="sidebar-badge<?= e($badgeClass) ?>">
                            <?= e($item['badge_text']) ?>
                        </em>
                    <?php endif; ?>

                    <i
                        class="submenu-chevron"
                        data-lucide="chevron-down"
                    ></i>
                </button>

                <div
                    id="<?= e($collapseId) ?>"
                    class="collapse sidebar-submenu <?= $childActive ? 'show' : '' ?>"
                >
                    <?php school_sidebar_render_items(
                        $children,
                        $currentRequestPath,
                        $baseUrl,
                        $activePageKey,
                        $level + 1
                    ); ?>
                </div>
            <?php else: ?>
                <a
                    class="sidebar-link<?= $childClass ?> <?= $active ? 'active' : '' ?>"
                    href="<?= e(school_sidebar_href(
                        (string)($item['route'] ?? '#'),
                        $baseUrl
                    )) ?>"
                    <?= $nestedPadding ?>
                >
                    <?= school_sidebar_menu_icon_html(
                        (string)($item['display_icon'] ?? 'circle')
                    ) ?>

                    <span><?= e(
                        $item['display_title'] ?? 'Menu'
                    ) ?></span>

                    <?php if (!empty($item['badge_text'])): ?>
                        <em class="sidebar-badge<?= e($badgeClass) ?>">
                            <?= e($item['badge_text']) ?>
                        </em>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
            <?php
        }
    }
}

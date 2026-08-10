<?php
declare(strict_types=1);

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

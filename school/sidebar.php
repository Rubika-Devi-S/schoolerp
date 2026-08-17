<?php
declare(strict_types=1);

/* Build: 2026-08-12-role-master-sidebar-runtime-v21 */

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout_helpers.php';
require_once dirname(__DIR__) . '/includes/sidebar-manager.php';

$currentRequestPath = trim(
    (string)(
        parse_url(
            $_SERVER['REQUEST_URI'] ?? '',
            PHP_URL_PATH
        ) ?: ''
    ),
    '/'
);

$currentUser = function_exists('current_user')
    ? current_user()
    : [];

$roleId = (int)(
    $currentUser['role_id']
    ?? $_SESSION['role_id']
    ?? 0
);

$roleKey = function_exists('school_normalize_role_key')
    ? school_normalize_role_key(
        (string)(
            $currentUser['role_key']
            ?? $_SESSION['role_key']
            ?? ''
        ),
        (string)(
            $currentUser['role_name']
            ?? $_SESSION['role_name']
            ?? ''
        )
    )
    : strtolower(trim((string)(
        $currentUser['role_key']
        ?? $_SESSION['role_key']
        ?? ''
    )));

$isParentSidebar = $roleKey === 'parent';

$tenantId = max(
    1,
    (int)(
        $currentUser['tenant_id']
        ?? $_SESSION['tenant_id']
        ?? 1
    )
);

$activePageKey = trim((string)($pageKey ?? ''));

$baseUrl = defined('BASE_URL')
    ? rtrim((string)BASE_URL, '/') . '/'
    : '';

$sidebarItems = school_sidebar_get_items(
    isset($pdo) && $pdo instanceof PDO ? $pdo : null,
    $roleId,
    $tenantId
);

$sidebarTree = school_sidebar_build_tree($sidebarItems);

$schoolSidebarVersion = 0;
if (
    isset($pdo)
    && $pdo instanceof PDO
    && school_sidebar_table_exists($pdo, 'school_sidebar_versions')
) {
    try {
        $versionStatement = $pdo->prepare(
            "SELECT version FROM school_sidebar_versions
             WHERE tenant_id=:tenant_id LIMIT 1"
        );
        $versionStatement->execute(['tenant_id' => $tenantId]);
        $schoolSidebarVersion = (int)($versionStatement->fetchColumn() ?: 0);
    } catch (Throwable $exception) {
        $schoolSidebarVersion = 0;
    }
}

$branding = function_exists('current_tenant_branding')
    ? current_tenant_branding()
    : [];

$schoolName = trim((string)(
    $branding['school_name']
    ?? $branding['name']
    ?? APP_NAME
));

$tagline = trim((string)(
    $branding['tagline']
    ?? 'School Administration'
));

$logo = trim((string)($branding['logo_path'] ?? ''));
$logoAbsolutePath = defined('PROJECT_ROOT')
    ? rtrim((string)PROJECT_ROOT, '/\\')
        . '/'
        . ltrim($logo, '/')
    : dirname(__DIR__) . '/' . ltrim($logo, '/');

$hasLogo = $logo !== '' && is_file($logoAbsolutePath);

$logoFit = strtolower((string)($branding['logo_fit'] ?? 'contain'));
$logoFit = in_array($logoFit, ['contain', 'cover'], true) ? $logoFit : 'contain';
$logoZoom = max(50, min(200, (int)($branding['logo_zoom'] ?? 100)));
$logoPositionX = max(0, min(100, (int)($branding['logo_position_x'] ?? 50)));
$logoPositionY = max(0, min(100, (int)($branding['logo_position_y'] ?? 50)));
$logoRotation = max(-180, min(180, (int)($branding['logo_rotation'] ?? 0)));
$logoShape = strtolower((string)($branding['logo_shape'] ?? 'rounded'));
$logoShape = in_array($logoShape, ['rounded', 'circle', 'square'], true) ? $logoShape : 'rounded';
$logoRadius = $logoShape === 'circle' ? '50%' : ($logoShape === 'square' ? '0' : '10px');
$logoScale = number_format($logoZoom / 100, 3, '.', '');
$logoStyle = sprintf(
    'object-fit:%s;object-position:%d%% %d%%;transform:rotate(%ddeg) scale(%s);border-radius:%s;',
    $logoFit,
    $logoPositionX,
    $logoPositionY,
    $logoRotation,
    $logoScale,
    $logoRadius
);
$logoVersion = $hasLogo ? (int)(@filemtime($logoAbsolutePath) ?: 0) : 0;
$logoUrl = $hasLogo
    ? $baseUrl . ltrim($logo, '/') . ($logoVersion > 0 ? '?v=' . $logoVersion : '')
    : '';

$monogram = '';

foreach (preg_split('/\s+/', $schoolName) ?: [] as $word) {
    if ($word !== '') {
        $monogram .= strtoupper(substr($word, 0, 1));
    }

    if (strlen($monogram) >= 2) {
        break;
    }
}

$monogram = $monogram !== '' ? $monogram : 'SE';
?>
<div id="sidebarBackdrop" class="sidebar-backdrop"></div>

<aside id="sidebar">
    <div class="sidebar-brand">
        <a
            href="<?= e(
                $isParentSidebar
                    ? $baseUrl . 'parent/s_dashboard.php'
                    : school_sidebar_href(
                        'dashboard.php',
                        $baseUrl
                    )
            ) ?>"
            class="brand-link"
        >
            <span class="brand-logo">
                <?php if ($hasLogo): ?>
                    <img
                        src="<?= e($logoUrl) ?>"
                        alt="<?= e($schoolName) ?>"
                        style="<?= e($logoStyle) ?>"
                    >
                <?php else: ?>
                    <span class="brand-monogram">
                        <?= e($monogram) ?>
                    </span>
                <?php endif; ?>
            </span>

            <span class="brand-copy">
                <strong class="school-name-primary">
                    <?= e($schoolName) ?>
                </strong>

                <?php if ($tagline !== ''): ?>
                    <small><?= e($tagline) ?></small>
                <?php endif; ?>
            </span>
        </a>

        <button
            id="sidebarMobileClose"
            class="sidebar-close d-xl-none"
            type="button"
            aria-label="Close sidebar"
        >
            <i data-lucide="x"></i>
        </button>
    </div>

    <nav class="sidebar-nav" aria-label="<?= e($isParentSidebar ? 'Parent navigation' : 'School Admin navigation') ?>">
        <?php if ($sidebarTree): ?>
            <?php school_sidebar_render_items(
                $sidebarTree,
                $currentRequestPath,
                $baseUrl,
                $activePageKey
            ); ?>
        <?php else: ?>
            <div class="px-3 py-4 text-center text-muted small">
                No sidebar permissions are assigned to this role.
            </div>
        <?php endif; ?>
    </nav>

    <div class="sidebar-promo">
        <i data-lucide="graduation-cap"></i>
        <strong>
            <?= e(
                $isParentSidebar
                    ? 'Parent Portal'
                    : (
                        $branding['sidebar_footer_title']
                        ?? 'School Administration'
                    )
            ) ?>
        </strong>
        <small>
            <?= e(
                $isParentSidebar
                    ? 'View your children\'s school information and updates.'
                    : (
                        $branding['sidebar_footer_text']
                        ?? 'Manage academics, students, staff and school operations.'
                    )
            ) ?>
        </small>
    </div>
</aside>

<?php
$schoolPagePermissions = function_exists('school_current_page_capabilities')
    ? school_current_page_capabilities($activePageKey)
    : [
        'page_key' => $activePageKey,
        'view' => true,
        'add' => true,
        'create' => true,
        'edit' => true,
        'delete' => true,
    ];
?>
<script>
(function(){
    'use strict';

    const permissions = Object.freeze(<?= json_encode(
        $schoolPagePermissions,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    ) ?>);

    window.SCHOOL_PAGE_PERMISSIONS = permissions;
    window.schoolCan = function(action){
        const key = String(action || 'view').toLowerCase();
        const normalized = key === 'create' ? 'add' : key;
        return Boolean(permissions[normalized]);
    };

    const actionFromElement = element => {
        if (!(element instanceof Element)) return '';

        const explicit = String(
            element.dataset.permissionAction
            || element.dataset.requiredPermission
            || element.getAttribute('data-required-action')
            || ''
        ).toLowerCase().trim();

        if (['add','create','edit','delete'].includes(explicit)) {
            return explicit === 'create' ? 'add' : explicit;
        }

        const tag = element.tagName.toLowerCase();
        const buttonLike = tag === 'button'
            || (tag === 'input' && ['button','submit'].includes(String(element.type).toLowerCase()))
            || (tag === 'a' && (
                element.getAttribute('role') === 'button'
                || /(?:btn|button|action)/i.test(element.className || '')
            ));

        if (!buttonLike || element.closest('#sidebar,.sidebar-nav')) return '';

        const source = [
            element.id,
            element.className,
            element.getAttribute('title'),
            element.getAttribute('aria-label'),
            element.getAttribute('name'),
            element.getAttribute('data-action'),
            element.textContent,
        ].filter(Boolean).join(' ')
            .replace(/([a-z0-9])([A-Z])/g,'$1 $2')
            .toLowerCase()
            .replace(/[_-]+/g,' ');

        if (/(^|\s)(delete|remove|trash|destroy)(\s|$)/.test(source)
            || /(?:delete|remove|trash|destroy)(?:btn|button|action)/.test(source.replace(/\s+/g,''))) {
            return 'delete';
        }
        if (/(^|\s)(edit|update|modify)(\s|$)/.test(source)
            || /(?:edit|update|modify)(?:btn|button|action)/.test(source.replace(/\s+/g,''))) {
            return 'edit';
        }
        if (/(^|\s)(add|create|new|copy|duplicate)(\s|$)/.test(source)
            || /(?:add|create|new|copy|duplicate)(?:btn|button|action)/.test(source.replace(/\s+/g,''))) {
            return 'add';
        }

        return '';
    };

    const apply = root => {
        const elements = [];
        if (root instanceof Element) elements.push(root);
        if (root?.querySelectorAll) {
            elements.push(...root.querySelectorAll(
                'button,a[role="button"],a.btn,a[class*="action"],input[type="button"],input[type="submit"]'
            ));
        }

        elements.forEach(element => {
            const action = actionFromElement(element);
            if (!action) return;
            const allowed = window.schoolCan(action);
            element.hidden = !allowed;
            element.setAttribute('aria-hidden', allowed ? 'false' : 'true');
            if ('disabled' in element) element.disabled = !allowed;
            element.dataset.permissionApplied = '1';
        });
    };

    window.schoolApplyPermissions = apply;

    document.addEventListener('click', event => {
        const target = event.target instanceof Element
            ? event.target.closest('button,a,input[type="button"],input[type="submit"]')
            : null;
        const action = actionFromElement(target);
        if (action && !window.schoolCan(action)) {
            event.preventDefault();
            event.stopImmediatePropagation();
            alert(`You do not have ${action.toUpperCase()} permission for this page.`);
        }
    }, true);

    const start = () => {
        apply(document);
        const observer = new MutationObserver(records => {
            records.forEach(record => record.addedNodes.forEach(node => {
                if (node instanceof Element) apply(node);
            }));
        });
        observer.observe(document.documentElement,{childList:true,subtree:true});
        document.dispatchEvent(new CustomEvent('school:permissions-ready',{detail:permissions}));
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded',start,{once:true});
    } else {
        start();
    }
})();
</script>


<script>
(function(){
'use strict';
if (window.__SCHOOL_SIDEBAR_VERSION_WATCH__) return;
window.__SCHOOL_SIDEBAR_VERSION_WATCH__ = true;

let version = <?= json_encode($schoolSidebarVersion) ?>;
const stateUrl = <?= json_encode($baseUrl . 'api/school-sidebar-state.php', JSON_UNESCAPED_SLASHES) ?>;

const checkSidebarVersion = async () => {
    try {
        const response = await fetch(stateUrl, {
            credentials:'same-origin',
            cache:'no-store',
            headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}
        });
        if (!response.ok) return;
        const result = await response.json();
        const next = Number(result?.data?.version || 0);
        if (next > 0 && next !== version) {
            window.location.reload();
            return;
        }
        version = next || version;
    } catch (error) {
        /* Network errors must never interrupt normal sidebar use. */
    }
};

setInterval(checkSidebarVersion, 5000);
})();
</script>

<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Load the shared application bootstrap and PDO connection
|--------------------------------------------------------------------------
| bootstrap.php starts the session and loads includes/db.php.
| layout_helpers.php is retained because the existing UI depends on it.
*/
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout_helpers.php';
require_once __DIR__ . '/sidebar/master.php';

$pdo = $GLOBALS['pdo'] ?? null;

$currentRequestPath = trim(
    (string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: ''),
    '/'
);

$roleId   = (int)($_SESSION['role_id'] ?? 0);
$tenantId = max(1, (int)($_SESSION['tenant_id'] ?? 1));
$baseUrl  = defined('BASE_URL')
    ? rtrim((string)BASE_URL, '/') . '/'
    : '../';

$currentPageKey = strtolower(trim((string)($pageKey ?? '')));
$currentQueryParams = $_GET;

$fallbackMenus = superAdminSidebarMasterFallback();


/**
 * Resolve a route against the existing project files.
 *
 * The first existing candidate is used. When no dedicated page exists,
 * the master registry provides a safe existing-page fallback so the link
 * does not produce a broken route.
 *
 * @param array<int,string> $candidates
 */
if (!function_exists('superAdminSidebarResolveRoute')) {
    function superAdminSidebarResolveRoute(array $candidates): string
    {
        $projectRoot = defined('PROJECT_ROOT')
            ? rtrim((string)PROJECT_ROOT, '/\\')
            : dirname(__DIR__);

        foreach ($candidates as $candidate) {
            $candidate = trim((string)$candidate);

            if ($candidate === '' || $candidate === '#') {
                continue;
            }

            if (preg_match('~^(?:https?:)?//~i', $candidate)) {
                return $candidate;
            }

            $path = ltrim(
                (string)(parse_url($candidate, PHP_URL_PATH) ?: ''),
                '/'
            );

            if ($path !== '' && is_file($projectRoot . '/' . $path)) {
                return $candidate;
            }
        }

        return trim((string)($candidates[0] ?? '#')) ?: '#';
    }
}

/**
 * Merge the master registry with database-loaded role permissions.
 *
 * Existing database titles/icons remain intact. Required hierarchy,
 * routes, order and active-key metadata are normalized. Existing menu
 * records not present in the registry are retained.
 *
 * @param array<int,array<string,mixed>> $menus
 * @return array<int,array<string,mixed>>
 */
if (!function_exists('superAdminIntegrateMasterMenus')) {
    function superAdminIntegrateMasterMenus(
        array $menus,
        bool $addMissingItems
    ): array {
        $definitions = superAdminSidebarMasterOptions();
        $definitionByKey = [];

        foreach ($definitions as $definition) {
            $definitionByKey[(string)$definition['menu_key']] =
                $definition;
        }

        $indexByKey = [];
        $maxId = 10000;

        foreach ($menus as $index => $menu) {
            if (!is_array($menu)) {
                continue;
            }

            $id = (int)($menu['id'] ?? 0);
            $key = trim((string)($menu['menu_key'] ?? ''));

            $maxId = max($maxId, $id);

            if ($key !== '' && !isset($indexByKey[$key])) {
                $indexByKey[$key] = $index;
            }
        }

        if ($addMissingItems) {
            foreach ($definitions as $definition) {
                $key = (string)$definition['menu_key'];

                if (isset($indexByKey[$key])) {
                    continue;
                }

                $maxId++;

                $menus[] = [
                    'id' => $maxId,
                    'parent_id' => null,
                    'menu_key' => $key,
                    'display_title' => (string)$definition['title'],
                    'route' => superAdminSidebarResolveRoute(
                        (array)$definition['routes']
                    ),
                    'route_candidates' =>
                        (array)$definition['routes'],
                    'display_icon' => (string)$definition['icon'],
                    'badge_text' => null,
                    'display_order' => (int)$definition['order'],
                    'page_keys' => $definition['page_keys'] ?? [],
                    'exclude_query_keys' =>
                        $definition['exclude_query_keys'] ?? [],
                ];

                $indexByKey[$key] = array_key_last($menus);
            }
        }

        /*
         * Normalize only the master entries that the current role is
         * permitted to load. Unrelated database menus are left untouched.
         */
        foreach ($definitionByKey as $key => $definition) {
            if (!isset($indexByKey[$key])) {
                continue;
            }

            $index = $indexByKey[$key];
            $parentKey = $definition['parent_key'] ?? null;

            if ($parentKey !== null
                && isset($indexByKey[(string)$parentKey])) {
                $menus[$index]['parent_id'] = (int)(
                    $menus[$indexByKey[(string)$parentKey]]['id'] ?? 0
                );
            } else {
                $menus[$index]['parent_id'] = null;
            }

            $currentRoute = trim((string)(
                $menus[$index]['route'] ?? ''
            ));

            $candidates = [];

            if ($currentRoute !== '' && $currentRoute !== '#') {
                $candidates[] = $currentRoute;
            }

            foreach ((array)$definition['routes'] as $candidate) {
                if (!in_array($candidate, $candidates, true)) {
                    $candidates[] = $candidate;
                }
            }

            if (($definition['routes'][0] ?? '#') === '#') {
                $menus[$index]['route'] = '#';
            } else {
                $menus[$index]['route'] =
                    superAdminSidebarResolveRoute($candidates);
            }

            $menus[$index]['route_candidates'] = $candidates;
            $menus[$index]['display_order'] =
                (int)$definition['order'];
            $menus[$index]['page_keys'] =
                $definition['page_keys'] ?? [];
            $menus[$index]['exclude_query_keys'] =
                $definition['exclude_query_keys'] ?? [];

            if (trim((string)(
                $menus[$index]['display_title'] ?? ''
            )) === '') {
                $menus[$index]['display_title'] =
                    (string)$definition['title'];
            }

            if (trim((string)(
                $menus[$index]['display_icon'] ?? ''
            )) === '') {
                $menus[$index]['display_icon'] =
                    (string)$definition['icon'];
            }
        }

        return $menus;
    }
}

$menus = [];
$sidebarLoadedFromDatabase = false;
$sidebarDatabaseAvailable = false;

if (isset($pdo) && $pdo instanceof PDO
    && function_exists('school_table_exists')
    && school_table_exists($pdo, 'sidebar_items')
    && school_table_exists($pdo, 'role_sidebar_permissions')
    && $roleId > 0) {
    try {
        $hasTenantOverrides = school_table_exists($pdo, 'tenant_sidebar_items');
        $hasPortalScope = function_exists('school_column_exists')
            && school_column_exists($pdo, 'sidebar_items', 'portal_scope');

        $selectOverride = $hasTenantOverrides
            ? "COALESCE(NULLIF(tsi.custom_title, ''), si.menu_title) AS display_title,
               COALESCE(NULLIF(tsi.custom_icon, ''), si.icon) AS display_icon"
            : "si.menu_title AS display_title,
               si.icon AS display_icon";

        $joinOverride = $hasTenantOverrides
            ? "LEFT JOIN tenant_sidebar_items tsi
                   ON tsi.sidebar_item_id = si.id
                  AND tsi.tenant_id = :tenant_id"
            : '';

        $visibleOverride = $hasTenantOverrides
            ? 'AND COALESCE(tsi.is_visible, 1) = 1'
            : '';

        $orderOverride = $hasTenantOverrides
            ? 'COALESCE(tsi.display_order, si.display_order)'
            : 'si.display_order';

        $scopeFilter = $hasPortalScope
            ? "AND si.portal_scope IN ('super_admin', 'all')"
            : "AND si.menu_key LIKE 'sa\\_%'";

        $sql = "SELECT
                    si.id,
                    si.parent_id,
                    si.menu_key,
                    si.route,
                    si.badge_text,
                    si.is_active,
                    si.show_in_sidebar,
                    {$orderOverride} AS display_order,
                    {$selectOverride}
                FROM sidebar_items si
                INNER JOIN role_sidebar_permissions rsp
                    ON rsp.sidebar_item_id = si.id
                   AND rsp.role_id = :role_id
                   AND rsp.can_show = 1
                {$joinOverride}
                WHERE si.is_active = 1
                  AND si.show_in_sidebar = 1
                  {$scopeFilter}
                  {$visibleOverride}
                ORDER BY {$orderOverride}, si.id";

        $stmt = $pdo->prepare($sql);
        $params = ['role_id' => $roleId];

        if ($hasTenantOverrides) {
            $params['tenant_id'] = $tenantId;
        }

        $stmt->execute($params);
        $loadedMenus = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $sidebarDatabaseAvailable = true;
        $sidebarLoadedFromDatabase = true;
        $menus = is_array($loadedMenus)
            ? $loadedMenus
            : [];
    } catch (Throwable $e) {
        error_log('super-admin/sidebar.php: ' . $e->getMessage());
    }
}

/*
 * The database is the source of truth whenever the sidebar tables are
 * available. Hardcoded master definitions are used only as an emergency
 * fallback when the database cannot be queried.
 */
if (!$sidebarDatabaseAvailable) {
    $menus = $fallbackMenus;
    $menus = superAdminIntegrateMasterMenus(
        $menus,
        true
    );
}

$menuTree = [];
$menuLookup = [];

foreach ($menus as $menu) {
    if (!is_array($menu)) continue;

    $menu += [
        'id' => 0,
        'parent_id' => null,
        'menu_key' => '',
        'display_title' => 'Menu',
        'route' => '#',
        'display_icon' => 'circle',
        'badge_text' => null,
        'display_order' => 0,
        'children' => [],
    ];

    $menu['id'] = (int)$menu['id'];
    $menu['parent_id'] = !empty($menu['parent_id'])
        ? (int)$menu['parent_id']
        : null;
    $menu['display_order'] = (int)$menu['display_order'];
    $menu['children'] = [];

    $menuLookup[$menu['id']] = $menu;
}

foreach ($menuLookup as $id => $menu) {
    $parentId = $menu['parent_id'];

    if (
        $parentId !== null
        && $parentId !== $id
        && isset($menuLookup[$parentId])
    ) {
        $menuLookup[$parentId]['children'][] = &$menuLookup[$id];
    }
}

foreach ($menuLookup as $id => &$menu) {
    $parentId = $menu['parent_id'];

    if (
        $parentId === null
        || $parentId === $id
        || !isset($menuLookup[$parentId])
    ) {
        $menuTree[] = &$menu;
    }
}
unset($menu);

if (!function_exists('superAdminSortSidebarTree')) {
    function superAdminSortSidebarTree(array &$items): void
    {
        usort(
            $items,
            static function (array $left, array $right): int {
                return ((int)($left['display_order'] ?? 0))
                    <=> ((int)($right['display_order'] ?? 0))
                    ?: strcmp(
                        (string)($left['display_title'] ?? ''),
                        (string)($right['display_title'] ?? '')
                    )
                    ?: ((int)($left['id'] ?? 0) <=> (int)($right['id'] ?? 0));
            }
        );

        foreach ($items as &$item) {
            if (!empty($item['children']) && is_array($item['children'])) {
                superAdminSortSidebarTree($item['children']);
            }
        }
        unset($item);
    }
}

superAdminSortSidebarTree($menuTree);

if (!function_exists('superAdminSidebarHref')) {
    function superAdminSidebarHref(string $route, string $baseUrl): string
    {
        $route = trim($route);

        if ($route === '' || $route === '#') {
            return '#';
        }

        if (preg_match('~^(?:https?:)?//~i', $route)) {
            return $route;
        }

        return $baseUrl . ltrim($route, '/');
    }
}

if (!function_exists('superAdminSidebarActive')) {
    /**
     * @param array<string,mixed> $menu
     * @param array<string,mixed> $currentQueryParams
     */
    function superAdminSidebarActive(
        array $menu,
        string $currentRequestPath,
        string $baseUrl,
        string $currentPageKey = '',
        array $currentQueryParams = []
    ): bool {
        $menuKey = strtolower(trim((string)(
            $menu['menu_key'] ?? ''
        )));

        $pageKeys = array_map(
            static fn(mixed $value): string =>
                strtolower(trim((string)$value)),
            (array)($menu['page_keys'] ?? [])
        );

        if ($currentPageKey !== '') {
            if ($menuKey === $currentPageKey
                || in_array($currentPageKey, $pageKeys, true)
                || (
                    str_starts_with($menuKey, 'sa_')
                    && substr($menuKey, 3) === $currentPageKey
                )) {
                return true;
            }
        }

        $route = trim((string)($menu['route'] ?? '#'));

        if ($route === '' || $route === '#') {
            return false;
        }

        $routeUrl = superAdminSidebarHref($route, $baseUrl);
        $routePath = trim(
            (string)(parse_url($routeUrl, PHP_URL_PATH) ?: ''),
            '/'
        );

        $pathMatches = $routePath !== ''
            && (
                $currentRequestPath === $routePath
                || str_ends_with(
                    $currentRequestPath,
                    '/' . $routePath
                )
            );

        if (!$pathMatches) {
            return false;
        }

        $routeQuery = [];
        parse_str(
            (string)(parse_url($routeUrl, PHP_URL_QUERY) ?: ''),
            $routeQuery
        );

        if ($routeQuery) {
            foreach ($routeQuery as $key => $expectedValue) {
                $actualValue = $currentQueryParams[$key] ?? null;

                if ((string)$actualValue !== (string)$expectedValue) {
                    return false;
                }
            }

            return true;
        }

        foreach ((array)($menu['exclude_query_keys'] ?? []) as $key) {
            if (array_key_exists((string)$key, $currentQueryParams)) {
                return false;
            }
        }

        return true;
    }
}


if (!function_exists('superAdminSidebarHasActiveChild')) {
    function superAdminSidebarHasActiveChild(
        array $menu,
        string $currentRequestPath,
        string $baseUrl,
        string $currentPageKey = '',
        array $currentQueryParams = []
    ): bool {
        foreach (($menu['children'] ?? []) as $child) {
            if (superAdminSidebarActive(
                $child,
                $currentRequestPath,
                $baseUrl,
                $currentPageKey,
                $currentQueryParams
            )) {
                return true;
            }

            if (superAdminSidebarHasActiveChild(
                $child,
                $currentRequestPath,
                $baseUrl,
                $currentPageKey,
                $currentQueryParams
            )) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('superAdminRenderSidebarMenu')) {
    function superAdminRenderSidebarMenu(
        array $menus,
        string $currentRequestPath,
        string $baseUrl,
        string $currentPageKey = '',
        array $currentQueryParams = [],
        int $level = 0
    ): void {
        foreach ($menus as $menu) {
            $children = is_array($menu['children'] ?? null)
                ? $menu['children']
                : [];

            $route = (string)($menu['route'] ?? '#');
            $mainActive = superAdminSidebarActive(
                $menu,
                $currentRequestPath,
                $baseUrl,
                $currentPageKey,
                $currentQueryParams
            );
            $childActive = superAdminSidebarHasActiveChild(
                $menu,
                $currentRequestPath,
                $baseUrl,
                $currentPageKey,
                $currentQueryParams
            );
            $active = $mainActive || $childActive;
            $hasChildren = count($children) > 0;
            $collapseId = 'superAdminSidebarMenu' . (int)($menu['id'] ?? 0);
            $childClass = $level > 0 ? ' sidebar-child' : '';
            $padding = $level > 1
                ? ' style="padding-left:' . (34 + (($level - 1) * 14)) . 'px"'
                : '';
            ?>
            <?php if ($hasChildren): ?>
                <button
                    type="button"
                    class="sidebar-link sidebar-parent<?= $childClass ?> <?= $active ? 'active' : '' ?>"
                    data-bs-toggle="collapse"
                    data-bs-target="#<?= e($collapseId) ?>"
                    aria-expanded="<?= $childActive ? 'true' : 'false' ?>"
                    <?= $padding ?>
                >
                    <i data-lucide="<?= e($menu['display_icon'] ?? 'circle') ?>"></i>
                    <span><?= e($menu['display_title'] ?? 'Menu') ?></span>

                    <?php if (!empty($menu['badge_text'])): ?>
                        <em class="sidebar-badge">
                            <?= e($menu['badge_text']) ?>
                        </em>
                    <?php endif; ?>

                    <i class="submenu-chevron" data-lucide="chevron-down"></i>
                </button>

                <div
                    id="<?= e($collapseId) ?>"
                    class="collapse sidebar-submenu <?= $childActive ? 'show' : '' ?>"
                >
                    <?php superAdminRenderSidebarMenu(
                        $children,
                        $currentRequestPath,
                        $baseUrl,
                        $currentPageKey,
                        $currentQueryParams,
                        $level + 1
                    ); ?>
                </div>
            <?php else: ?>
                <a
                    class="sidebar-link<?= $childClass ?> <?= $active ? 'active' : '' ?>"
                    href="<?= e(superAdminSidebarHref($route, $baseUrl)) ?>"
                    <?= $padding ?>
                >
                    <i data-lucide="<?= e($menu['display_icon'] ?? 'circle') ?>"></i>
                    <span><?= e($menu['display_title'] ?? 'Menu') ?></span>

                    <?php if (!empty($menu['badge_text'])): ?>
                        <em class="sidebar-badge">
                            <?= e($menu['badge_text']) ?>
                        </em>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
            <?php
        }
    }
}

$school = function_exists('current_tenant_branding')
    ? current_tenant_branding()
    : [];

$platformName = defined('APP_NAME')
    ? (string)APP_NAME
    : 'School ERP';

$logo = trim((string)($school['logo_path'] ?? ''));
$logoAbsolutePath = dirname(__DIR__) . '/' . ltrim($logo, '/');
$hasValidLogo = $logo !== '' && is_file($logoAbsolutePath);
?>
<div id="sidebarBackdrop" class="sidebar-backdrop"></div>

<aside id="sidebar">
    <div class="sidebar-brand">
        <a href="<?= e($baseUrl . 'super-admin/dashboard.php') ?>" class="brand-link">
            <span class="brand-logo">
                <?php if ($hasValidLogo): ?>
                    <img
                        src="<?= e($baseUrl . ltrim($logo, '/')) ?>"
                        alt="<?= e($platformName) ?>"
                    >
                <?php else: ?>
                    <span class="brand-monogram">SA</span>
                <?php endif; ?>
            </span>

            <span class="brand-copy">
                <strong class="school-name-primary">
                    <?= e($platformName) ?>
                </strong>
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

    <nav class="sidebar-nav">
        <?php superAdminRenderSidebarMenu(
            $menuTree,
            $currentRequestPath,
            $baseUrl,
            $currentPageKey,
            $currentQueryParams
        ); ?>
    </nav>

    <div class="sidebar-promo">
        <i data-lucide="shield-check"></i>
        <strong>Platform Administration</strong>
        <small>Manage schools, subscriptions, users and system access.</small>
    </div>
</aside>

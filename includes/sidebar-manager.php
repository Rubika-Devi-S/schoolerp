<?php
declare(strict_types=1);

require_once __DIR__ . '/sidebar-options.php';
if (is_file(__DIR__ . '/sidebar-icon-helper.php')) {
    require_once __DIR__ . '/sidebar-icon-helper.php';
}


if (!function_exists('school_sidebar_menu_icon_html')) {
    function school_sidebar_menu_icon_html(string $icon): string
    {
        if (function_exists('school_sidebar_icon_html')) {
            return school_sidebar_icon_html($icon);
        }

        return '<i data-lucide="'
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
     * STRICT School sidebar loader.
     * A menu exists for a school only when tenant_sidebar_items contains an
     * explicit visible assignment for that tenant. Default catalogue rows are
     * never used as a runtime fallback.
     *
     * @return array<int,array<string,mixed>>
     */
    function school_sidebar_load_database_items(
        PDO $pdo,
        int $roleId,
        int $tenantId
    ): array {
        if (
            $roleId<=0 || $tenantId<=0
            || !school_sidebar_table_exists($pdo,'sidebar_items')
            || !school_sidebar_table_exists($pdo,'tenant_sidebar_items')
        ) return [];

        $hasDynamic=school_sidebar_table_exists($pdo,'school_sidebar_permission_grants');
        $hasMatrix=school_sidebar_table_exists($pdo,'school_sidebar_action_permissions');
        $hasLegacy=school_sidebar_table_exists($pdo,'role_sidebar_permissions');
        $roleKey=school_sidebar_role_key($pdo,$roleId);
        $schoolAdmin=school_sidebar_is_school_admin_role($roleKey);

        $hasCustomRoute=school_sidebar_column_exists($pdo,'tenant_sidebar_items','custom_route');
        $hasCustomParent=school_sidebar_column_exists($pdo,'tenant_sidebar_items','custom_parent_id');
        $hasCustomTitle=school_sidebar_column_exists($pdo,'tenant_sidebar_items','custom_title');
        $hasCustomIcon=school_sidebar_column_exists($pdo,'tenant_sidebar_items','custom_icon');
        $hasInherit=school_sidebar_column_exists($pdo,'tenant_sidebar_items','inherit_default');
        $hasOwner=school_sidebar_column_exists($pdo,'sidebar_items','owner_tenant_id');
        $hasPortal=school_sidebar_column_exists($pdo,'sidebar_items','portal_scope');
        $hasTenantModules=school_sidebar_table_exists($pdo,'tenant_modules');

        $parent=$hasCustomParent?'COALESCE(tsi.custom_parent_id,si.parent_id)':'si.parent_id';
        $route=$hasCustomRoute?"COALESCE(NULLIF(tsi.custom_route,''),si.route)":'si.route';
        $title=$hasCustomTitle?"COALESCE(NULLIF(tsi.custom_title,''),si.menu_title)":'si.menu_title';
        $icon=$hasCustomIcon?"COALESCE(NULLIF(tsi.custom_icon,''),si.icon)":'si.icon';
        $order='COALESCE(tsi.display_order,si.display_order)';

        if ($hasDynamic) {
            $actionSelect=$schoolAdmin
                ? 'CASE WHEN spg.id IS NULL THEN 1 ELSE COALESCE(spg.is_allowed,0) END AS can_view'
                : 'COALESCE(spg.is_allowed,0) AS can_view';
            $actionJoin="LEFT JOIN school_sidebar_permission_grants spg
                ON spg.sidebar_item_id=si.id
               AND spg.tenant_id=:permission_tenant_id
               AND spg.role_id=:permission_role_id
               AND spg.action_key='view'";
        } elseif ($hasMatrix) {
            $actionSelect=$schoolAdmin
                ? 'CASE WHEN sap.id IS NULL THEN 1 ELSE COALESCE(sap.can_view,0) END AS can_view'
                : 'COALESCE(sap.can_view,0) AS can_view';
            $actionJoin="LEFT JOIN school_sidebar_action_permissions sap
                ON sap.sidebar_item_id=si.id
               AND sap.tenant_id=:permission_tenant_id
               AND sap.role_id=:permission_role_id";
        } elseif ($hasLegacy) {
            $actionSelect=$schoolAdmin
                ? 'CASE WHEN rsp.role_id IS NULL THEN 1 ELSE COALESCE(rsp.can_show,0) END AS can_view'
                : 'COALESCE(rsp.can_show,0) AS can_view';
            $actionJoin="LEFT JOIN role_sidebar_permissions rsp
                ON rsp.sidebar_item_id=si.id
               AND rsp.role_id=:legacy_role_id";
        } else {
            $actionSelect=$schoolAdmin?'1 AS can_view':'0 AS can_view';
            $actionJoin='';
        }

        $moduleJoin=$hasTenantModules
            ? "LEFT JOIN tenant_modules tm
                 ON tm.module_id=si.module_id
                AND tm.tenant_id=:module_tenant_id"
            : '';
        $moduleFilter=$hasTenantModules
            ? 'AND (si.module_id IS NULL OR COALESCE(tm.is_enabled,1)=1)'
            : '';
        $portal=$hasPortal?"AND si.portal_scope IN ('school','all')":"AND si.menu_key NOT LIKE 'sa\\_%'";
        $owner=$hasOwner?'AND (si.owner_tenant_id IS NULL OR si.owner_tenant_id=:owner_tenant_id)':'';
        $inherit=$hasInherit?'AND COALESCE(tsi.inherit_default,0)=0':'';

        $sql="SELECT
                si.id,{$parent} AS parent_id,si.module_id,si.menu_key,
                {$route} AS route,si.badge_text,si.badge_variant,
                si.display_order,si.is_active,si.show_in_sidebar,
                {$actionSelect},{$title} AS display_title,
                {$icon} AS display_icon,{$order} AS effective_order,
                1 AS is_visible
              FROM tenant_sidebar_items tsi
              INNER JOIN sidebar_items si ON si.id=tsi.sidebar_item_id
              {$actionJoin}
              {$moduleJoin}
              WHERE tsi.tenant_id=:override_tenant_id
                AND tsi.is_visible=1
                {$inherit}
                AND si.is_active=1
                AND si.show_in_sidebar=1
                {$portal}
                {$owner}
                {$moduleFilter}
              ORDER BY COALESCE({$parent},0),effective_order,si.id";
        $stmt=$pdo->prepare($sql);
        $params=['override_tenant_id'=>$tenantId];
        if ($hasDynamic || $hasMatrix) {
            $params['permission_tenant_id']=$tenantId;
            $params['permission_role_id']=$roleId;
        } elseif ($hasLegacy) {
            $params['legacy_role_id']=$roleId;
        }
        if ($hasTenantModules) $params['module_tenant_id']=$tenantId;
        if ($hasOwner) $params['owner_tenant_id']=$tenantId;
        $stmt->execute($params);
        $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows) || $rows===[]) return [];

        $itemsById=[];
        foreach ($rows as $row) {
            $id=(int)($row['id']??0); if ($id<=0) continue;
            $row['id']=$id;
            $row['parent_id']=!empty($row['parent_id'])?(int)$row['parent_id']:null;
            $row['display_order']=(int)($row['effective_order']??$row['display_order']??0);
            $row['is_visible']=1;
            $row['can_view']=(int)($row['can_view']??0);
            $itemsById[$id]=$row;
        }

        $allowedCache=[];
        $isAllowed=static function(int $itemId) use (&$isAllowed,&$allowedCache,$itemsById):bool {
            if (array_key_exists($itemId,$allowedCache)) return $allowedCache[$itemId];
            if (!isset($itemsById[$itemId])) return $allowedCache[$itemId]=false;
            $item=$itemsById[$itemId];
            if ((int)$item['can_view']!==1) return $allowedCache[$itemId]=false;
            $parentId=(int)($item['parent_id']??0);
            if ($parentId<=0) return $allowedCache[$itemId]=true;
            if ($parentId===$itemId) return $allowedCache[$itemId]=false;
            return $allowedCache[$itemId]=$isAllowed($parentId);
        };
        $flat=[];
        foreach ($itemsById as $id=>$item) if ($isAllowed($id)) $flat[]=$item;
        usort($flat,static function(array $a,array $b):int {
            return [(int)($a['parent_id']??0),(int)($a['display_order']??0),(int)($a['id']??0)]
                <=> [(int)($b['parent_id']??0),(int)($b['display_order']??0),(int)($b['id']??0)];
        });
        return $flat;
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
                        || school_sidebar_table_exists(
                            $pdo,
                            'school_sidebar_permission_grants'
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

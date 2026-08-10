<?php
declare(strict_types=1);

/**
 * School ERP - Strict School Sidebar Service
 * Build: 2026-08-07-strict-school-sidebar-v17
 *
 * Data model:
 *   sidebar_items              = shared/default master structure
 *   sidebar_default_settings   = default catalogue enable/order
 *   tenant_sidebar_items       = explicit school assignment/override
 *
 * Missing tenant_sidebar_items row means NOT assigned to that school.
 */

if (!function_exists('school_sidebar_service_table')) {
    function school_sidebar_service_table(PDO $pdo, string $table): bool
    {
        if (function_exists('school_table_exists')) {
            return school_table_exists($pdo, $table);
        }
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema=DATABASE() AND table_name=:table_name"
        );
        $stmt->execute(['table_name' => $table]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('school_sidebar_service_column')) {
    function school_sidebar_service_column(
        PDO $pdo,
        string $table,
        string $column
    ): bool {
        if (function_exists('school_column_exists')) {
            return school_column_exists($pdo, $table, $column);
        }
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema=DATABASE()
               AND table_name=:table_name
               AND column_name=:column_name"
        );
        $stmt->execute([
            'table_name' => $table,
            'column_name' => $column,
        ]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('school_sidebar_service_ids')) {
    /** @return array<int,int> */
    function school_sidebar_service_ids(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id > 0) $out[$id] = $id;
        }
        return array_values($out);
    }
}

if (!function_exists('school_sidebar_service_marks')) {
    function school_sidebar_service_marks(array $ids): string
    {
        return implode(',', array_fill(0, count($ids), '?'));
    }
}

if (!function_exists('school_sidebar_service_ensure')) {
    function school_sidebar_service_ensure(PDO $pdo): void
    {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS school_sidebar_versions (
                tenant_id BIGINT UNSIGNED NOT NULL,
                version BIGINT UNSIGNED NOT NULL DEFAULT 1,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (tenant_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci"
        );

        if (!school_sidebar_service_table($pdo, 'sidebar_items')) {
            return;
        }

        /*
         * Legacy builds stored a custom menu in sidebar_items with a school
         * owner. The strict V17 model uses ONE shared master definition and a
         * tenant_sidebar_items row for the school assignment. Promoting the
         * master does not expose it to other schools because runtime access is
         * assignment-only.
         */
        if (
            school_sidebar_service_column($pdo, 'sidebar_items', 'owner_tenant_id')
            && school_sidebar_service_table($pdo, 'sidebar_default_settings')
        ) {
            $pdo->exec(
                "INSERT IGNORE INTO sidebar_default_settings
                    (sidebar_item_id,is_enabled,display_order,updated_by)
                 SELECT id,1,display_order,NULL
                 FROM sidebar_items
                 WHERE owner_tenant_id IS NOT NULL
                   AND is_active=1
                   AND show_in_sidebar=1
                   AND portal_scope IN ('school','all')"
            );

            $pdo->exec(
                "UPDATE sidebar_items
                 SET owner_tenant_id=NULL
                 WHERE owner_tenant_id IS NOT NULL
                   AND portal_scope IN ('school','all')"
            );
        }

        /* Old inherited placeholder rows are not explicit assignments. */
        if (
            school_sidebar_service_table($pdo, 'tenant_sidebar_items')
            && school_sidebar_service_column(
                $pdo,
                'tenant_sidebar_items',
                'inherit_default'
            )
        ) {
            $pdo->exec(
                "DELETE FROM tenant_sidebar_items
                 WHERE COALESCE(inherit_default,0)=1"
            );
            $pdo->exec(
                "UPDATE tenant_sidebar_items
                 SET inherit_default=0
                 WHERE inherit_default<>0"
            );
        }

        /* Every active shared menu belongs to the editable Default catalogue. */
        if (school_sidebar_service_table($pdo, 'sidebar_default_settings')) {
            $owner = school_sidebar_service_column(
                $pdo,
                'sidebar_items',
                'owner_tenant_id'
            ) ? 'AND owner_tenant_id IS NULL' : '';

            $pdo->exec(
                "INSERT IGNORE INTO sidebar_default_settings
                    (sidebar_item_id,is_enabled,display_order,updated_by)
                 SELECT id,1,display_order,NULL
                 FROM sidebar_items
                 WHERE is_active=1
                   AND show_in_sidebar=1
                   AND portal_scope IN ('school','all')
                   {$owner}"
            );
        }
    }
}

if (!function_exists('school_sidebar_service_bump_version')) {
    function school_sidebar_service_bump_version(
        PDO $pdo,
        ?int $tenantId = null
    ): void {
        if (!school_sidebar_service_table($pdo, 'school_sidebar_versions')) {
            return;
        }

        if ($tenantId !== null && $tenantId > 0) {
            $stmt = $pdo->prepare(
                "INSERT INTO school_sidebar_versions(tenant_id,version)
                 VALUES(:tenant_id,1)
                 ON DUPLICATE KEY UPDATE
                    version=version+1,
                    updated_at=CURRENT_TIMESTAMP"
            );
            $stmt->execute(['tenant_id' => $tenantId]);
            return;
        }

        if (school_sidebar_service_table($pdo, 'tenants')) {
            $pdo->exec(
                "INSERT INTO school_sidebar_versions(tenant_id,version)
                 SELECT id,1 FROM tenants
                 WHERE status IN ('active','trial')
                 ON DUPLICATE KEY UPDATE
                    version=school_sidebar_versions.version+1,
                    updated_at=CURRENT_TIMESTAMP"
            );
        } else {
            $pdo->exec(
                "UPDATE school_sidebar_versions
                 SET version=version+1,updated_at=CURRENT_TIMESTAMP"
            );
        }
    }
}

if (!function_exists('school_sidebar_service_version')) {
    function school_sidebar_service_version(PDO $pdo, int $tenantId): int
    {
        if (
            $tenantId <= 0
            || !school_sidebar_service_table($pdo, 'school_sidebar_versions')
        ) {
            return 0;
        }
        $stmt = $pdo->prepare(
            "SELECT version FROM school_sidebar_versions
             WHERE tenant_id=:tenant_id LIMIT 1"
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        return (int)($stmt->fetchColumn() ?: 0);
    }
}

if (!function_exists('school_sidebar_service_master_item')) {
    function school_sidebar_service_master_item(
        PDO $pdo,
        int $itemId
    ): array {
        if ($itemId <= 0) return [];
        $stmt = $pdo->prepare(
            "SELECT * FROM sidebar_items
             WHERE id=:id
               AND portal_scope IN ('school','all')
             LIMIT 1"
        );
        $stmt->execute(['id' => $itemId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : [];
    }
}

if (!function_exists('school_sidebar_service_default_upsert')) {
    function school_sidebar_service_default_upsert(
        PDO $pdo,
        int $itemId,
        int $enabled,
        int $displayOrder,
        ?int $userId = null
    ): void {
        if (!school_sidebar_service_table($pdo, 'sidebar_default_settings')) {
            return;
        }
        $stmt = $pdo->prepare(
            "INSERT INTO sidebar_default_settings
                (sidebar_item_id,is_enabled,display_order,updated_by)
             VALUES
                (:item_id,:enabled,:display_order,:updated_by)
             ON DUPLICATE KEY UPDATE
                is_enabled=VALUES(is_enabled),
                display_order=VALUES(display_order),
                updated_by=VALUES(updated_by),
                updated_at=CURRENT_TIMESTAMP"
        );
        $stmt->execute([
            'item_id' => $itemId,
            'enabled' => $enabled ? 1 : 0,
            'display_order' => max(0, $displayOrder),
            'updated_by' => $userId,
        ]);
    }
}

if (!function_exists('school_sidebar_service_assign_item')) {
    function school_sidebar_service_assign_item(
        PDO $pdo,
        int $tenantId,
        int $itemId,
        array $overrides = []
    ): void {
        if (
            $tenantId <= 0
            || $itemId <= 0
            || !school_sidebar_service_table($pdo, 'tenant_sidebar_items')
        ) {
            return;
        }

        $master = school_sidebar_service_master_item($pdo, $itemId);
        if ($master === []) {
            throw new RuntimeException('Sidebar item was not found.');
        }

        $columns = ['tenant_id','sidebar_item_id','display_order','is_visible'];
        $values = [':tenant_id',':sidebar_item_id',':display_order','1'];
        $params = [
            'tenant_id' => $tenantId,
            'sidebar_item_id' => $itemId,
            'display_order' => max(
                0,
                (int)($overrides['display_order'] ?? $master['display_order'] ?? 0)
            ),
        ];
        $updates = ['is_visible=1'];

        if (school_sidebar_service_column(
            $pdo,
            'tenant_sidebar_items',
            'inherit_default'
        )) {
            $columns[] = 'inherit_default';
            $values[] = '0';
            $updates[] = 'inherit_default=0';
        }

        $overrideMap = [
            'custom_title' => 'custom_title',
            'custom_icon' => 'custom_icon',
            'custom_route' => 'custom_route',
            'custom_parent_id' => 'custom_parent_id',
        ];
        foreach ($overrideMap as $inputKey => $columnName) {
            if (
                array_key_exists($inputKey, $overrides)
                && school_sidebar_service_column(
                    $pdo,
                    'tenant_sidebar_items',
                    $columnName
                )
            ) {
                $columns[] = $columnName;
                $values[] = ':' . $columnName;
                $params[$columnName] = $overrides[$inputKey];
                $updates[] = $columnName . '=VALUES(' . $columnName . ')';
            }
        }

        if (array_key_exists('display_order', $overrides)) {
            $updates[] = 'display_order=VALUES(display_order)';
        }

        $sql = "INSERT INTO tenant_sidebar_items(" . implode(',', $columns) . ")
                VALUES(" . implode(',', $values) . ")
                ON DUPLICATE KEY UPDATE " . implode(',', array_unique($updates));
        if (school_sidebar_service_column(
            $pdo,
            'tenant_sidebar_items',
            'updated_at'
        )) {
            $sql .= ',updated_at=CURRENT_TIMESTAMP';
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }
}

if (!function_exists('school_sidebar_service_parent_chain')) {
    /** @return array<int,int> item then ancestors */
    function school_sidebar_service_parent_chain(
        PDO $pdo,
        int $itemId
    ): array {
        $chain = [];
        $visited = [];
        $current = $itemId;
        while ($current > 0 && !isset($visited[$current]) && count($chain) < 100) {
            $visited[$current] = true;
            $master = school_sidebar_service_master_item($pdo, $current);
            if ($master === []) break;
            $chain[] = $current;
            $current = (int)($master['parent_id'] ?? 0);
        }
        return $chain;
    }
}

if (!function_exists('school_sidebar_service_assign_parent_chain')) {
    function school_sidebar_service_assign_parent_chain(
        PDO $pdo,
        int $tenantId,
        int $itemId
    ): void {
        $chain = array_reverse(
            school_sidebar_service_parent_chain($pdo, $itemId)
        );
        foreach ($chain as $id) {
            school_sidebar_service_assign_item($pdo, $tenantId, $id);
        }
    }
}

if (!function_exists('school_sidebar_service_master_descendants')) {
    /** @return array<int,int> root first */
    function school_sidebar_service_master_descendants(
        PDO $pdo,
        int $rootId
    ): array {
        if ($rootId <= 0) return [];
        $stmt = $pdo->query(
            "SELECT id,parent_id FROM sidebar_items
             WHERE portal_scope IN ('school','all')"
        );
        $children = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $parent = (int)($row['parent_id'] ?? 0);
            $children[$parent][] = (int)$row['id'];
        }
        $out = [];
        $seen = [];
        $walk = static function (int $id) use (&$walk,&$out,&$seen,$children): void {
            if ($id <= 0 || isset($seen[$id])) return;
            $seen[$id] = true;
            $out[] = $id;
            foreach ($children[$id] ?? [] as $childId) {
                $walk((int)$childId);
            }
        };
        $walk($rootId);
        return $out;
    }
}

if (!function_exists('school_sidebar_service_school_descendants')) {
    /** @return array<int,int> effective assigned tree, root first */
    function school_sidebar_service_school_descendants(
        PDO $pdo,
        int $tenantId,
        int $rootId
    ): array {
        if (
            $tenantId <= 0
            || $rootId <= 0
            || !school_sidebar_service_table($pdo, 'tenant_sidebar_items')
        ) {
            return [];
        }

        $parent = school_sidebar_service_column(
            $pdo,
            'tenant_sidebar_items',
            'custom_parent_id'
        )
            ? 'COALESCE(tsi.custom_parent_id,si.parent_id)'
            : 'si.parent_id';

        $sql = "SELECT si.id,{$parent} AS parent_id
                FROM tenant_sidebar_items tsi
                INNER JOIN sidebar_items si ON si.id=tsi.sidebar_item_id
                WHERE tsi.tenant_id=:tenant_id";
        if (school_sidebar_service_column(
            $pdo,
            'tenant_sidebar_items',
            'inherit_default'
        )) {
            $sql .= ' AND COALESCE(tsi.inherit_default,0)=0';
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['tenant_id' => $tenantId]);
        $children = [];
        $exists = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $id = (int)$row['id'];
            $exists[$id] = true;
            $children[(int)($row['parent_id'] ?? 0)][] = $id;
        }
        if (!isset($exists[$rootId])) return [];

        $out=[];$seen=[];
        $walk = static function (int $id) use (&$walk,&$out,&$seen,$children): void {
            if ($id<=0 || isset($seen[$id])) return;
            $seen[$id]=true;
            $out[]=$id;
            foreach ($children[$id] ?? [] as $childId) $walk((int)$childId);
        };
        $walk($rootId);
        return $out;
    }
}

if (!function_exists('school_sidebar_service_remove_school_items')) {
    function school_sidebar_service_remove_school_items(
        PDO $pdo,
        int $tenantId,
        array $itemIds
    ): void {
        $itemIds = school_sidebar_service_ids($itemIds);
        if ($tenantId <= 0 || $itemIds === []) return;
        $marks = school_sidebar_service_marks($itemIds);

        foreach ([
            'school_sidebar_permission_grants',
            'school_sidebar_action_permissions',
            'tenant_sidebar_items',
        ] as $table) {
            if (
                school_sidebar_service_table($pdo, $table)
                && school_sidebar_service_column($pdo, $table, 'tenant_id')
                && school_sidebar_service_column($pdo, $table, 'sidebar_item_id')
            ) {
                $stmt = $pdo->prepare(
                    "DELETE FROM `{$table}`
                     WHERE tenant_id=? AND sidebar_item_id IN ({$marks})"
                );
                $stmt->execute([$tenantId, ...$itemIds]);
            }
        }

        /* Other tenant-scoped extension tables. */
        $tableStmt = $pdo->prepare(
            "SELECT DISTINCT c.TABLE_NAME
             FROM information_schema.COLUMNS c
             INNER JOIN information_schema.COLUMNS t
                ON t.TABLE_SCHEMA=c.TABLE_SCHEMA
               AND t.TABLE_NAME=c.TABLE_NAME
               AND t.COLUMN_NAME='tenant_id'
             WHERE c.TABLE_SCHEMA=DATABASE()
               AND c.COLUMN_NAME='sidebar_item_id'"
        );
        $tableStmt->execute();
        $skip = [
            'sidebar_items' => true,
            'school_sidebar_permission_grants' => true,
            'school_sidebar_action_permissions' => true,
            'tenant_sidebar_items' => true,
        ];
        foreach ($tableStmt->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $table = (string)$table;
            if (isset($skip[$table]) || !preg_match('/^[A-Za-z0-9_]+$/',$table)) continue;
            $stmt = $pdo->prepare(
                "DELETE FROM `{$table}`
                 WHERE tenant_id=? AND sidebar_item_id IN ({$marks})"
            );
            $stmt->execute([$tenantId, ...$itemIds]);
        }

        /* Role-scoped legacy permission tables. */
        if (
            school_sidebar_service_table($pdo, 'roles')
            && school_sidebar_service_column($pdo, 'roles', 'tenant_id')
        ) {
            foreach (['role_sidebar_permissions','role_page_permissions'] as $table) {
                if (
                    !school_sidebar_service_table($pdo, $table)
                    || !school_sidebar_service_column($pdo, $table, 'role_id')
                    || !school_sidebar_service_column($pdo, $table, 'sidebar_item_id')
                ) continue;
                $stmt = $pdo->prepare(
                    "DELETE p FROM `{$table}` p
                     INNER JOIN roles r ON r.id=p.role_id
                     WHERE r.tenant_id=?
                       AND p.sidebar_item_id IN ({$marks})"
                );
                $stmt->execute([$tenantId, ...$itemIds]);
            }
        }

        if (
            school_sidebar_service_table($pdo, 'user_page_permission_overrides')
            && school_sidebar_service_table($pdo, 'users')
            && school_sidebar_service_column($pdo, 'users', 'tenant_id')
            && school_sidebar_service_column(
                $pdo,
                'user_page_permission_overrides',
                'user_id'
            )
            && school_sidebar_service_column(
                $pdo,
                'user_page_permission_overrides',
                'sidebar_item_id'
            )
        ) {
            $stmt = $pdo->prepare(
                "DELETE p FROM user_page_permission_overrides p
                 INNER JOIN users u ON u.id=p.user_id
                 WHERE u.tenant_id=?
                   AND p.sidebar_item_id IN ({$marks})"
            );
            $stmt->execute([$tenantId, ...$itemIds]);
        }
    }
}

if (!function_exists('school_sidebar_service_clear_school')) {
    function school_sidebar_service_clear_school(PDO $pdo, int $tenantId): void
    {
        if (!school_sidebar_service_table($pdo, 'tenant_sidebar_items')) return;
        $stmt = $pdo->prepare(
            "SELECT sidebar_item_id FROM tenant_sidebar_items
             WHERE tenant_id=:tenant_id"
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        school_sidebar_service_remove_school_items($pdo, $tenantId, $ids);
    }
}

if (!function_exists('school_sidebar_service_cleanup_global_dependencies')) {
    function school_sidebar_service_cleanup_global_dependencies(
        PDO $pdo,
        array $itemIds
    ): void {
        $itemIds = school_sidebar_service_ids($itemIds);
        if ($itemIds === []) return;
        $marks = school_sidebar_service_marks($itemIds);

        /* School overrides that use a deleted menu as a custom parent. */
        if (
            school_sidebar_service_table($pdo, 'tenant_sidebar_items')
            && school_sidebar_service_column(
                $pdo,
                'tenant_sidebar_items',
                'custom_parent_id'
            )
        ) {
            $stmt = $pdo->prepare(
                "SELECT tenant_id,sidebar_item_id
                 FROM tenant_sidebar_items
                 WHERE custom_parent_id IN ({$marks})"
            );
            $stmt->execute($itemIds);
            $extra = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $tenant = (int)$row['tenant_id'];
                $child = (int)$row['sidebar_item_id'];
                if ($tenant<=0 || $child<=0) continue;
                foreach (
                    school_sidebar_service_school_descendants($pdo,$tenant,$child)
                    as $descendantId
                ) {
                    $extra[$tenant][$descendantId] = $descendantId;
                }
            }
            foreach ($extra as $tenantId => $ids) {
                school_sidebar_service_remove_school_items(
                    $pdo,
                    (int)$tenantId,
                    array_values($ids)
                );
            }
        }

        /* Every table that directly stores sidebar_item_id. */
        $stmt = $pdo->prepare(
            "SELECT DISTINCT TABLE_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA=DATABASE()
               AND COLUMN_NAME='sidebar_item_id'"
        );
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $table = (string)$table;
            if ($table === 'sidebar_items' || !preg_match('/^[A-Za-z0-9_]+$/',$table)) continue;
            $delete = $pdo->prepare(
                "DELETE FROM `{$table}` WHERE sidebar_item_id IN ({$marks})"
            );
            $delete->execute($itemIds);
        }

        /* Foreign keys that use another column name. */
        $fk = $pdo->prepare(
            "SELECT DISTINCT TABLE_NAME,COLUMN_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA=DATABASE()
               AND REFERENCED_TABLE_SCHEMA=DATABASE()
               AND REFERENCED_TABLE_NAME='sidebar_items'
               AND REFERENCED_COLUMN_NAME='id'"
        );
        $fk->execute();
        foreach ($fk->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $table = (string)($row['TABLE_NAME'] ?? '');
            $column = (string)($row['COLUMN_NAME'] ?? '');
            if ($table === 'sidebar_items') continue;
            if (!preg_match('/^[A-Za-z0-9_]+$/',$table)
                || !preg_match('/^[A-Za-z0-9_]+$/',$column)) continue;
            $delete = $pdo->prepare(
                "DELETE FROM `{$table}` WHERE `{$column}` IN ({$marks})"
            );
            $delete->execute($itemIds);
        }
    }
}

if (!function_exists('school_sidebar_service_create_menu')) {
    /** @return array{sidebar_item_id:int,reused:bool} */
    function school_sidebar_service_create_menu(
        PDO $pdo,
        int $tenantId,
        array $data,
        ?int $userId = null
    ): array {
        $title = trim((string)($data['menu_title'] ?? ''));
        $key = strtolower(trim((string)($data['menu_key'] ?? '')));
        if ($key === '') {
            $key = strtolower((string)preg_replace('/[^a-z0-9]+/i','_',$title));
        }
        $key = trim((string)preg_replace('/[^a-z0-9_]+/','_',$key),'_');
        if ($title === '' || $key === '') {
            throw new InvalidArgumentException('Menu Title and Menu Key are required.');
        }
        if (strlen($key) > 100) {
            throw new InvalidArgumentException('Menu Key is too long.');
        }

        $route = trim((string)($data['route'] ?? '#')) ?: '#';
        $icon = trim((string)($data['icon'] ?? 'circle')) ?: 'circle';
        $parentId = max(0,(int)($data['parent_id'] ?? 0));
        $visible = !empty($data['is_visible']) ? 1 : 0;
        $order = max(0,min(99999,(int)($data['display_order'] ?? 0)));
        if ($order <= 0) {
            $order = (int)$pdo->query(
                "SELECT COALESCE(MAX(display_order),0)+10
                 FROM sidebar_items
                 WHERE portal_scope IN ('school','all')"
            )->fetchColumn();
        }

        if ($parentId > 0) {
            $parent = school_sidebar_service_master_item($pdo,$parentId);
            if ($parent === []) {
                throw new InvalidArgumentException('Selected parent menu is unavailable.');
            }
        }

        $find = $pdo->prepare(
            "SELECT id,owner_tenant_id
             FROM sidebar_items
             WHERE LOWER(menu_key)=LOWER(:menu_key)
             ORDER BY id LIMIT 1"
        );
        $find->execute(['menu_key'=>$key]);
        $existing = $find->fetch(PDO::FETCH_ASSOC);
        $reused = is_array($existing);

        if ($reused) {
            $itemId = (int)$existing['id'];
            if ($parentId === $itemId
                || in_array(
                    $parentId,
                    school_sidebar_service_master_descendants($pdo,$itemId),
                    true
                )) {
                throw new InvalidArgumentException('Invalid parent hierarchy.');
            }
            $ownerSet = school_sidebar_service_column(
                $pdo,'sidebar_items','owner_tenant_id'
            ) ? ',owner_tenant_id=NULL' : '';
            $stmt = $pdo->prepare(
                "UPDATE sidebar_items
                 SET menu_title=:title,route=:route,icon=:icon,
                     parent_id=:parent_id,display_order=:display_order,
                     portal_scope='school',show_in_sidebar=1,is_active=1
                     {$ownerSet}
                 WHERE id=:id"
            );
            $stmt->execute([
                'title'=>substr($title,0,120),
                'route'=>substr($route,0,255),
                'icon'=>substr($icon,0,4096),
                'parent_id'=>$parentId>0?$parentId:null,
                'display_order'=>$order,
                'id'=>$itemId,
            ]);
        } else {
            $hasOwner = school_sidebar_service_column(
                $pdo,'sidebar_items','owner_tenant_id'
            );
            $ownerColumn = $hasOwner ? ',owner_tenant_id' : '';
            $ownerValue = $hasOwner ? ',NULL' : '';
            $stmt = $pdo->prepare(
                "INSERT INTO sidebar_items(
                    parent_id,module_id,menu_key,menu_title,route,icon,
                    badge_text,badge_variant,portal_scope{$ownerColumn},
                    display_order,show_in_sidebar,is_active
                 ) VALUES(
                    :parent_id,NULL,:menu_key,:menu_title,:route,:icon,
                    NULL,NULL,'school'{$ownerValue},:display_order,1,1
                 )"
            );
            $stmt->execute([
                'parent_id'=>$parentId>0?$parentId:null,
                'menu_key'=>$key,
                'menu_title'=>substr($title,0,120),
                'route'=>substr($route,0,255),
                'icon'=>substr($icon,0,4096),
                'display_order'=>$order,
            ]);
            $itemId=(int)$pdo->lastInsertId();
        }

        /* Always create/update the same structure in Default Sidebar. */
        school_sidebar_service_default_upsert(
            $pdo,$itemId,$visible,$order,$userId
        );

        if ($tenantId > 0) {
            school_sidebar_service_assign_parent_chain($pdo,$tenantId,$itemId);
            school_sidebar_service_assign_item(
                $pdo,
                $tenantId,
                $itemId,
                [
                    'custom_title'=>substr($title,0,120),
                    'custom_icon'=>substr($icon,0,4096),
                    'custom_route'=>substr($route,0,255),
                    'custom_parent_id'=>$parentId>0?$parentId:null,
                    'display_order'=>$order,
                ]
            );
        }

        return [
            'sidebar_item_id'=>$itemId,
            'reused'=>$reused,
        ];
    }
}

if (!function_exists('school_sidebar_service_edit_menu')) {
    function school_sidebar_service_edit_menu(
        PDO $pdo,
        int $tenantId,
        int $itemId,
        array $data,
        ?int $userId = null
    ): void {
        $master = school_sidebar_service_master_item($pdo,$itemId);
        if ($master === []) throw new RuntimeException('Sidebar menu was not found.');

        $title=trim((string)($data['menu_title']??''));
        if ($title==='') throw new InvalidArgumentException('Menu Title is required.');
        $route=trim((string)($data['route']??'#'))?:'#';
        $icon=trim((string)($data['icon']??'circle'))?:'circle';
        $parentId=max(0,(int)($data['parent_id']??0));
        $order=max(0,min(99999,(int)($data['display_order']??0)));
        $visible=!empty($data['is_visible'])?1:0;

        $descendants = $tenantId>0
            ? school_sidebar_service_school_descendants($pdo,$tenantId,$itemId)
            : school_sidebar_service_master_descendants($pdo,$itemId);
        if ($parentId===$itemId || ($parentId>0 && in_array($parentId,$descendants,true))) {
            throw new InvalidArgumentException('Invalid parent hierarchy.');
        }
        if ($parentId>0 && school_sidebar_service_master_item($pdo,$parentId)===[]) {
            throw new InvalidArgumentException('Selected parent menu is unavailable.');
        }

        if ($tenantId<=0) {
            $ownerSet = school_sidebar_service_column(
                $pdo,'sidebar_items','owner_tenant_id'
            ) ? ',owner_tenant_id=NULL' : '';
            $stmt=$pdo->prepare(
                "UPDATE sidebar_items
                 SET menu_title=:title,route=:route,icon=:icon,
                     parent_id=:parent_id,display_order=:display_order,
                     show_in_sidebar=1,is_active=1{$ownerSet}
                 WHERE id=:id"
            );
            $stmt->execute([
                'title'=>substr($title,0,120),
                'route'=>substr($route,0,255),
                'icon'=>substr($icon,0,4096),
                'parent_id'=>$parentId>0?$parentId:null,
                'display_order'=>$order,
                'id'=>$itemId,
            ]);
            school_sidebar_service_default_upsert(
                $pdo,$itemId,$visible,$order,$userId
            );
            return;
        }

        if (!$visible) {
            $ids=school_sidebar_service_school_descendants($pdo,$tenantId,$itemId);
            school_sidebar_service_remove_school_items($pdo,$tenantId,$ids);
            return;
        }

        if ($parentId>0) {
            school_sidebar_service_assign_parent_chain($pdo,$tenantId,$parentId);
        }
        school_sidebar_service_assign_item(
            $pdo,
            $tenantId,
            $itemId,
            [
                'custom_title'=>substr($title,0,120),
                'custom_icon'=>substr($icon,0,4096),
                'custom_route'=>substr($route,0,255),
                'custom_parent_id'=>$parentId>0?$parentId:null,
                'display_order'=>$order,
            ]
        );
    }
}

if (!function_exists('school_sidebar_service_delete_menu')) {
    /** @return array<int,int> deleted/unassigned IDs */
    function school_sidebar_service_delete_menu(
        PDO $pdo,
        int $tenantId,
        int $itemId
    ): array {
        if (school_sidebar_service_master_item($pdo,$itemId)===[]) {
            throw new RuntimeException('Sidebar menu was not found.');
        }

        if ($tenantId>0) {
            $ids=school_sidebar_service_school_descendants($pdo,$tenantId,$itemId);
            if ($ids===[]) $ids=[$itemId];
            school_sidebar_service_remove_school_items($pdo,$tenantId,$ids);
            return $ids;
        }

        $ids=school_sidebar_service_master_descendants($pdo,$itemId);
        school_sidebar_service_cleanup_global_dependencies($pdo,$ids);

        /* Delete children first; safe even when the self-FK is not CASCADE. */
        foreach (array_reverse($ids) as $id) {
            $stmt=$pdo->prepare('DELETE FROM sidebar_items WHERE id=:id');
            $stmt->execute(['id'=>$id]);
        }
        return $ids;
    }
}

<?php
declare(strict_types=1);

/**
 * School ERP - Default Inheritance Sidebar Service
 * Build: 2026-08-14-branch-permission-inheritance-v26
 *
 * Data model:
 *   sidebar_items              = shared/default master structure
 *   sidebar_default_settings   = global default enable/order
 *   tenant_sidebar_items       = explicit per-school override/customization
 *
 * Effective rule:
 *   - no tenant_sidebar_items row => inherit Default Sidebar state
 *   - explicit tenant row         => school-specific enable/disable/customization
 *
 * Therefore a new enabled Default Sidebar item is automatically available to
 * every school, while each school can independently override that item.
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


if (!function_exists('school_sidebar_service_role_key')) {
    function school_sidebar_service_role_key(string $roleKey): string
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

if (!function_exists('school_sidebar_service_role_master_upsert')) {
    function school_sidebar_service_role_master_upsert(
        PDO $pdo,
        string $roleKey,
        int $itemId,
        bool $enabled = true,
        ?int $displayOrder = null,
        ?int $userId = null
    ): void {
        $roleKey = school_sidebar_service_role_key($roleKey);
        if ($roleKey === '' || $itemId <= 0) {
            return;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO sidebar_role_master_items
                (role_key,sidebar_item_id,is_enabled,display_order,created_by,updated_by)
             VALUES
                (:role_key,:item_id,:enabled,:display_order,:created_by,:updated_by)
             ON DUPLICATE KEY UPDATE
                is_enabled=VALUES(is_enabled),
                display_order=VALUES(display_order),
                updated_by=VALUES(updated_by),
                updated_at=CURRENT_TIMESTAMP"
        );
        $stmt->execute([
            'role_key' => $roleKey,
            'item_id' => $itemId,
            'enabled' => $enabled ? 1 : 0,
            'display_order' => $displayOrder,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }
}

if (!function_exists('school_sidebar_service_role_master_remove')) {
    function school_sidebar_service_role_master_remove(
        PDO $pdo,
        string $roleKey,
        int $itemId
    ): void {
        $roleKey = school_sidebar_service_role_key($roleKey);
        if ($roleKey === '' || $itemId <= 0) {
            return;
        }

        $stmt = $pdo->prepare(
            "DELETE FROM sidebar_role_master_items
             WHERE role_key=:role_key
               AND sidebar_item_id=:item_id"
        );
        $stmt->execute([
            'role_key' => $roleKey,
            'item_id' => $itemId,
        ]);
    }
}

if (!function_exists('school_sidebar_service_role_master_count')) {
    function school_sidebar_service_role_master_count(
        PDO $pdo,
        string $roleKey
    ): int {
        $roleKey = school_sidebar_service_role_key($roleKey);
        if ($roleKey === ''
            || !school_sidebar_service_table($pdo, 'sidebar_role_master_items')) {
            return 0;
        }

        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM sidebar_role_master_items
             WHERE role_key=:role_key"
        );
        $stmt->execute(['role_key' => $roleKey]);
        return (int)$stmt->fetchColumn();
    }
}

if (!function_exists('school_sidebar_service_seed_parent_items')) {
    function school_sidebar_service_seed_parent_items(
        PDO $pdo,
        ?int $userId = null
    ): void {
        if (!school_sidebar_service_table($pdo, 'sidebar_items')) {
            return;
        }

        $items = [
            ['parent_dashboard', 'Dashboard', 'parent/s_dashboard.php', 'layout-dashboard', 1],
            ['parent_my_children', 'My Children', 'parent/s_dashboard.php#profile', 'users-round', 2],
            ['parent_attendance', 'Attendance', 'parent/s_dashboard.php#attendance', 'calendar-check', 3],
            ['parent_fees', 'Fees', 'parent/s_dashboard.php#fees', 'wallet-cards', 4],
            ['parent_fee_payment', 'Fee Payment', 'parent/s_dashboard.php#fees', 'credit-card', 5],
            ['parent_results', 'Exams / Results', 'parent/s_dashboard.php#results', 'notebook-tabs', 6],
            ['parent_homework', 'Homework', 'parent/s_dashboard.php#homework', 'notebook-pen', 7],
            ['parent_notices', 'Notices', 'parent/s_dashboard.php#announcements', 'megaphone', 8],
            ['parent_transport', 'Transport', 'parent/s_dashboard.php#transport', 'bus-front', 9],
            ['parent_profile', 'Profile', 'parent/s_dashboard.php#profile', 'user-round', 10],
        ];

        $select = $pdo->prepare(
            "SELECT id FROM sidebar_items
             WHERE menu_key=:menu_key
             LIMIT 1"
        );
        $insert = $pdo->prepare(
            "INSERT INTO sidebar_items
                (parent_id,module_id,menu_key,menu_title,route,icon,
                 portal_scope,owner_tenant_id,display_order,show_in_sidebar,is_active)
             VALUES
                (NULL,NULL,:menu_key,:menu_title,:route,:icon,
                 'school',NULL,:display_order,1,1)"
        );
        foreach ($items as [$key, $title, $route, $icon, $order]) {
            $select->execute(['menu_key' => $key]);
            $itemId = (int)($select->fetchColumn() ?: 0);

            if ($itemId <= 0) {
                $insert->execute([
                    'menu_key' => $key,
                    'menu_title' => $title,
                    'route' => $route,
                    'icon' => $icon,
                    'display_order' => $order,
                ]);
                $itemId = (int)$pdo->lastInsertId();
            }

            school_sidebar_service_role_master_upsert(
                $pdo,
                'parent',
                $itemId,
                true,
                $order,
                $userId
            );

            /*
             * Keep the shared definition enabled. Role-master membership is the
             * role boundary, so these rows do not leak into School Admin.
             */
            if (school_sidebar_service_table($pdo, 'sidebar_default_settings')) {
                school_sidebar_service_default_upsert(
                    $pdo,
                    $itemId,
                    1,
                    $order,
                    $userId
                );
            }
        }
    }
}

if (!function_exists('school_sidebar_service_ensure_role_master')) {
    function school_sidebar_service_ensure_role_master(
        PDO $pdo,
        string $roleKey,
        ?int $userId = null
    ): void {
        $roleKey = school_sidebar_service_role_key($roleKey);
        if ($roleKey === '' || $roleKey === 'super_admin') {
            return;
        }

        if ($roleKey === 'parent') {
            if (school_sidebar_service_role_master_count(
                $pdo,
                $roleKey
            ) === 0) {
                school_sidebar_service_seed_parent_items(
                    $pdo,
                    $userId
                );
            }
            return;
        }

        if (school_sidebar_service_role_master_count($pdo, $roleKey) > 0) {
            return;
        }

        /*
         * One-time compatibility snapshot for existing school roles. It copies
         * the current School master catalogue, excluding Parent-only items.
         * After this snapshot every role has an independent master list.
         */
        $owner = school_sidebar_service_column(
            $pdo,
            'sidebar_items',
            'owner_tenant_id'
        ) ? 'AND si.owner_tenant_id IS NULL' : '';

        $stmt = $pdo->prepare(
            "INSERT IGNORE INTO sidebar_role_master_items
                (role_key,sidebar_item_id,is_enabled,display_order,created_by,updated_by)
             SELECT
                :role_key,
                si.id,
                COALESCE(sds.is_enabled,1),
                COALESCE(sds.display_order,si.display_order),
                :created_by,
                :updated_by
             FROM sidebar_items si
             LEFT JOIN sidebar_default_settings sds
               ON sds.sidebar_item_id=si.id
             WHERE si.is_active=1
               AND si.show_in_sidebar=1
               AND si.portal_scope IN ('school','all')
               AND si.menu_key NOT LIKE 'parent\\_%'
               {$owner}"
        );
        $stmt->execute([
            'role_key' => $roleKey,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
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

        /*
         * Optional branch-level permission layer.
         *
         * No branch rows means "inherit the school-level permission".
         * A saved branch row can only restrict an action already allowed by the
         * school level; it never changes another branch or the school master.
         */
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS branch_sidebar_permission_grants (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id BIGINT UNSIGNED NOT NULL,
                branch_id BIGINT UNSIGNED NOT NULL,
                role_id BIGINT UNSIGNED NOT NULL,
                sidebar_item_id BIGINT UNSIGNED NOT NULL,
                action_key VARCHAR(50) NOT NULL,
                is_allowed TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_branch_sidebar_grant
                    (tenant_id,branch_id,role_id,sidebar_item_id,action_key),
                KEY idx_branch_sidebar_grant_scope
                    (tenant_id,branch_id,role_id),
                KEY idx_branch_sidebar_grant_item (sidebar_item_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci"
        );

        if (!school_sidebar_service_table($pdo, 'sidebar_items')) {
            return;
        }

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS sidebar_role_master_items (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                role_key VARCHAR(80) NOT NULL,
                sidebar_item_id BIGINT UNSIGNED NOT NULL,
                is_enabled TINYINT(1) NOT NULL DEFAULT 1,
                display_order INT DEFAULT NULL,
                created_by BIGINT UNSIGNED DEFAULT NULL,
                updated_by BIGINT UNSIGNED DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_sidebar_role_master (role_key,sidebar_item_id),
                KEY idx_sidebar_role_master_enabled (role_key,is_enabled,display_order),
                KEY idx_sidebar_role_master_item (sidebar_item_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci"
        );

        /*
         * Every active school receives its own tenant-scoped Parent role.
         * Existing Parent roles are preserved; disabled roles are reactivated.
         */
        if (school_sidebar_service_table($pdo, 'roles')
            && school_sidebar_service_table($pdo, 'tenants')) {
            $pdo->exec(
                "INSERT INTO roles
                    (tenant_id,role_key,role_name,description,role_scope,is_system,status,created_by)
                 SELECT
                    t.id,'parent','Parent','Parent Portal Login','school',0,'active',NULL
                 FROM tenants t
                 WHERE t.status IN ('active','trial')
                   AND NOT EXISTS (
                        SELECT 1
                        FROM roles r
                        WHERE r.tenant_id=t.id
                          AND r.role_key='parent'
                   )"
            );
            $pdo->exec(
                "UPDATE roles r
                 INNER JOIN tenants t ON t.id=r.tenant_id
                 SET r.role_name='Parent',
                     r.description='Parent Portal Login',
                     r.role_scope='school',
                     r.status='active',
                     r.deleted_at=NULL
                 WHERE r.role_key='parent'
                   AND t.status IN ('active','trial')"
            );
        }

        /*
         * V21 IMPORTANT:
         * Keep owner_tenant_id intact. A school-owned custom menu must remain
         * private to that school and must never be promoted into the global
         * Default Sidebar catalogue. Shared/default items use owner_tenant_id
         * NULL; school-specific items keep their tenant id.
         */

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

        /*
         * Build independent master catalogues for every existing school role.
         * Parent is deliberately seeded from its own curated list only.
         */
        if (school_sidebar_service_table($pdo, 'roles')) {
            $roleKeys = $pdo->query(
                "SELECT DISTINCT role_key
                 FROM roles
                 WHERE role_scope='school'
                   AND status='active'
                   AND deleted_at IS NULL
                 ORDER BY role_key"
            )->fetchAll(PDO::FETCH_COLUMN);

            foreach ($roleKeys as $roleKey) {
                school_sidebar_service_ensure_role_master(
                    $pdo,
                    (string)$roleKey,
                    null
                );
            }
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


if (!function_exists('school_sidebar_service_set_school_visibility')) {
    /**
     * Create/update an explicit school visibility override.
     *
     * Missing tenant row means "inherit Default Sidebar".  This helper is used
     * only when the school intentionally chooses Enabled/Disabled or when a
     * school-owned custom menu must remain explicitly assigned.
     */
    function school_sidebar_service_set_school_visibility(
        PDO $pdo,
        int $tenantId,
        int $itemId,
        bool $visible
    ): void {
        if ($tenantId <= 0 || $itemId <= 0) {
            return;
        }

        school_sidebar_service_assign_item($pdo, $tenantId, $itemId);

        $sets = ['is_visible=:is_visible'];
        if (school_sidebar_service_column(
            $pdo,
            'tenant_sidebar_items',
            'inherit_default'
        )) {
            $sets[] = 'inherit_default=0';
        }
        if (school_sidebar_service_column(
            $pdo,
            'tenant_sidebar_items',
            'updated_at'
        )) {
            $sets[] = 'updated_at=CURRENT_TIMESTAMP';
        }

        $stmt = $pdo->prepare(
            "UPDATE tenant_sidebar_items
             SET " . implode(',', $sets) . "
             WHERE tenant_id=:tenant_id
               AND sidebar_item_id=:sidebar_item_id"
        );
        $stmt->execute([
            'is_visible' => $visible ? 1 : 0,
            'tenant_id' => $tenantId,
            'sidebar_item_id' => $itemId,
        ]);
    }
}

if (!function_exists('school_sidebar_service_remove_school_override')) {
    /**
     * Remove only the school's override so the item immediately falls back to
     * the Default Sidebar state. Role/action permission rows are intentionally
     * preserved; they become effective again if the menu is visible.
     */
    function school_sidebar_service_remove_school_override(
        PDO $pdo,
        int $tenantId,
        int $itemId
    ): void {
        if ($tenantId <= 0 || $itemId <= 0
            || !school_sidebar_service_table($pdo, 'tenant_sidebar_items')) {
            return;
        }

        $stmt = $pdo->prepare(
            "DELETE FROM tenant_sidebar_items
             WHERE tenant_id=:tenant_id
               AND sidebar_item_id=:sidebar_item_id"
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'sidebar_item_id' => $itemId,
        ]);
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

        $hasOwner = school_sidebar_service_column(
            $pdo,
            'sidebar_items',
            'owner_tenant_id'
        );

        $findSql = "SELECT id"
            . ($hasOwner ? ",owner_tenant_id" : ",NULL AS owner_tenant_id")
            . " FROM sidebar_items
                WHERE LOWER(menu_key)=LOWER(:menu_key)
                ORDER BY id LIMIT 1";
        $find = $pdo->prepare($findSql);
        $find->execute(['menu_key'=>$key]);
        $existing = $find->fetch(PDO::FETCH_ASSOC);
        $reused = is_array($existing);

        if ($reused) {
            $itemId = (int)$existing['id'];
            $existingOwner = (int)($existing['owner_tenant_id'] ?? 0);

            if ($tenantId <= 0 && $existingOwner > 0) {
                throw new InvalidArgumentException(
                    'This Menu Key is already used by a school-specific sidebar item.'
                );
            }
            if ($tenantId > 0 && $existingOwner > 0 && $existingOwner !== $tenantId) {
                throw new InvalidArgumentException(
                    'This Menu Key is already used by another school.'
                );
            }

            if ($parentId === $itemId
                || in_array(
                    $parentId,
                    school_sidebar_service_master_descendants($pdo,$itemId),
                    true
                )) {
                throw new InvalidArgumentException('Invalid parent hierarchy.');
            }

            /*
             * Never rewrite a shared Default master when assigning it to one
             * school. Per-school title/route/icon are stored as tenant overrides.
             */
            if ($tenantId <= 0 || $existingOwner === $tenantId) {
                $ownerSet = ($hasOwner && $tenantId <= 0)
                    ? ',owner_tenant_id=NULL'
                    : '';
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
            }
        } else {
            $ownerColumn = $hasOwner ? ',owner_tenant_id' : '';
            $ownerValue = $hasOwner ? ',:owner_tenant_id' : '';
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
            $params = [
                'parent_id'=>$parentId>0?$parentId:null,
                'menu_key'=>$key,
                'menu_title'=>substr($title,0,120),
                'route'=>substr($route,0,255),
                'icon'=>substr($icon,0,4096),
                'display_order'=>$order,
            ];
            if ($hasOwner) {
                $params['owner_tenant_id'] = $tenantId > 0 ? $tenantId : null;
            }
            $stmt->execute($params);
            $itemId=(int)$pdo->lastInsertId();
        }

        if ($tenantId <= 0) {
            /*
             * A menu added to Default Sidebar becomes part of the inherited
             * catalogue. Runtime inheritance makes it available to every school
             * automatically; no tenant rows are copied/created.
             */
            school_sidebar_service_default_upsert(
                $pdo,$itemId,$visible,$order,$userId
            );
        } else {
            /*
             * A menu added while a school is selected is school-specific. Do not
             * add/enable it in the global Default Sidebar.
             *
             * Very old schemas without owner_tenant_id cannot mark ownership,
             * so keep the shared default OFF and rely on this school's explicit
             * enabled override.
             */
            if (!$hasOwner && !$reused) {
                school_sidebar_service_default_upsert(
                    $pdo,
                    $itemId,
                    0,
                    $order,
                    $userId
                );
            }

            if ($parentId > 0) {
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
            school_sidebar_service_set_school_visibility(
                $pdo,
                $tenantId,
                $itemId,
                $visible === 1
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

        /*
         * School edits are explicit tenant overrides. Disabling a shared
         * default item must create is_visible=0 instead of deleting the tenant
         * row, otherwise the item would immediately inherit Default=Enabled.
         */
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
        school_sidebar_service_set_school_visibility(
            $pdo,
            $tenantId,
            $itemId,
            $visible === 1
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
            $master=school_sidebar_service_master_item($pdo,$itemId);
            $ownerId=(int)($master['owner_tenant_id']??0);

            if ($ownerId===$tenantId && $ownerId>0) {
                /*
                 * A genuinely school-owned custom menu can be removed from that
                 * school. It is not part of the global Default catalogue.
                 */
                $ids=school_sidebar_service_master_descendants($pdo,$itemId);
                school_sidebar_service_cleanup_global_dependencies($pdo,$ids);
                foreach (array_reverse($ids) as $id) {
                    $stmt=$pdo->prepare(
                        'DELETE FROM sidebar_items
                         WHERE id=:id AND owner_tenant_id=:tenant_id'
                    );
                    $stmt->execute([
                        'id'=>$id,
                        'tenant_id'=>$tenantId,
                    ]);
                }
                return $ids;
            }

            /*
             * Shared Default item: "Delete" in school context means Disabled
             * for this school only. The Default master and every other school
             * remain untouched.
             */
            school_sidebar_service_set_school_visibility(
                $pdo,
                $tenantId,
                $itemId,
                false
            );
            return [$itemId];
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

<?php
declare(strict_types=1);

/**
 * School ERP Permission Chain
 * Build: 2026-08-14-school-branch-permission-chain-v26
 *
 * Final hierarchy:
 * Super Admin School Permission
 *   -> School Admin / Custom Role Permission
 *   -> Parent Delegation
 *   -> Runtime page/sidebar/button permission
 *
 * Existing tables are reused:
 * - sidebar_items / sidebar_default_settings / tenant_sidebar_items
 * - sidebar_role_master_items
 * - school_sidebar_permission_grants
 * - school_sidebar_action_permissions
 * - role_sidebar_permissions
 */

require_once __DIR__ . '/school-sidebar-service.php';

if (!function_exists('pc_normalize_action')) {
    function pc_normalize_action(string $action): string
    {
        $action = strtolower(trim($action));
        $action = preg_replace('/[^a-z0-9]+/', '_', $action) ?? '';
        $action = trim($action, '_');

        return match ($action) {
            'add', 'store', 'insert' => 'create',
            'update' => 'edit',
            'manage', 'settings' => 'manage_settings',
            'visibility' => 'manage_visibility',
            'full' => 'full_access',
            default => $action,
        };
    }
}

if (!function_exists('pc_action_catalog')) {
    function pc_action_catalog(PDO $pdo): array
    {
        $defaults = [
            'view' => 'View',
            'create' => 'Add',
            'edit' => 'Edit',
            'delete' => 'Delete',
            'print' => 'Print',
            'pdf' => 'PDF',
            'export' => 'Export',
            'import' => 'Import',
            'approve' => 'Approve',
            'reject' => 'Reject',
            'restore' => 'Restore',
            'manage_settings' => 'Manage',
            'manage_visibility' => 'Manage Visibility',
            'full_access' => 'Full Access',
        ];

        $rows = [];
        foreach ($defaults as $key => $name) {
            $rows[$key] = ['action_key' => $key, 'action_name' => $name];
        }

        if (school_sidebar_service_table($pdo, 'permission_actions')) {
            try {
                $stmt = $pdo->query(
                    "SELECT action_key,action_name
                     FROM permission_actions
                     WHERE is_active=1
                     ORDER BY display_order,id"
                );
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $key = pc_normalize_action((string)($row['action_key'] ?? ''));
                    if ($key === '') continue;
                    $rows[$key] = [
                        'action_key' => $key,
                        'action_name' => trim((string)($row['action_name'] ?? ''))
                            ?: ucwords(str_replace('_', ' ', $key)),
                    ];
                }
            } catch (Throwable $e) {
                error_log('pc_action_catalog: ' . $e->getMessage());
            }
        }

        return array_values($rows);
    }
}

if (!function_exists('pc_role_key')) {
    function pc_role_key(string $roleKey): string
    {
        return school_sidebar_service_role_key($roleKey);
    }
}

if (!function_exists('pc_role')) {
    function pc_role(PDO $pdo, int $tenantId, int $roleId = 0, string $roleKey = ''): array
    {
        if ($tenantId <= 0 || !school_sidebar_service_table($pdo, 'roles')) {
            return [];
        }

        if ($roleId > 0) {
            $stmt = $pdo->prepare(
                "SELECT id,tenant_id,role_key,role_name,status
                 FROM roles
                 WHERE tenant_id=:tenant_id
                   AND id=:role_id
                   AND role_scope='school'
                   AND status='active'
                   AND deleted_at IS NULL
                 LIMIT 1"
            );
            $stmt->execute(['tenant_id'=>$tenantId,'role_id'=>$roleId]);
        } else {
            $key = pc_role_key($roleKey);
            $aliases = $key === 'school_admin'
                ? ['school_admin','school-administrator','school_administrator','admin']
                : [$key];
            $marks = implode(',', array_fill(0, count($aliases), '?'));
            $stmt = $pdo->prepare(
                "SELECT id,tenant_id,role_key,role_name,status
                 FROM roles
                 WHERE tenant_id=?
                   AND role_scope='school'
                   AND status='active'
                   AND deleted_at IS NULL
                   AND role_key IN ({$marks})
                 ORDER BY role_key='school_admin' DESC,id
                 LIMIT 1"
            );
            $stmt->execute([$tenantId, ...$aliases]);
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : [];
    }
}

if (!function_exists('pc_role_id')) {
    function pc_role_id(PDO $pdo, int $tenantId, string $roleKey): int
    {
        return (int)(pc_role($pdo,$tenantId,0,$roleKey)['id'] ?? 0);
    }
}

if (!function_exists('pc_effective_school_visibility')) {
    function pc_effective_school_visibility(PDO $pdo, int $tenantId, int $itemId): bool
    {
        if ($tenantId <= 0 || $itemId <= 0) return false;

        $defaultEnabled = 1;
        if (school_sidebar_service_table($pdo, 'sidebar_default_settings')) {
            $stmt = $pdo->prepare(
                "SELECT is_enabled
                 FROM sidebar_default_settings
                 WHERE sidebar_item_id=:item_id LIMIT 1"
            );
            $stmt->execute(['item_id'=>$itemId]);
            $v = $stmt->fetchColumn();
            if ($v !== false) $defaultEnabled = (int)$v;
        }

        if (!school_sidebar_service_table($pdo, 'tenant_sidebar_items')) {
            return $defaultEnabled === 1;
        }

        $hasInherit = school_sidebar_service_column(
            $pdo, 'tenant_sidebar_items', 'inherit_default'
        );
        $cols = $hasInherit ? 'id,is_visible,inherit_default' : 'id,is_visible';
        $stmt = $pdo->prepare(
            "SELECT {$cols}
             FROM tenant_sidebar_items
             WHERE tenant_id=:tenant_id
               AND sidebar_item_id=:item_id
             LIMIT 1"
        );
        $stmt->execute(['tenant_id'=>$tenantId,'item_id'=>$itemId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) return $defaultEnabled === 1;
        if ($hasInherit && (int)($row['inherit_default'] ?? 0) === 1) {
            return $defaultEnabled === 1;
        }
        return (int)($row['is_visible'] ?? 0) === 1;
    }
}

if (!function_exists('pc_action_column')) {
    function pc_action_column(PDO $pdo, string $action): ?string
    {
        $map = [
            'view'=>'can_view','create'=>'can_add','edit'=>'can_edit','delete'=>'can_delete',
            'print'=>'can_print','pdf'=>'can_pdf','export'=>'can_export','import'=>'can_import',
            'approve'=>'can_approve','reject'=>'can_reject','restore'=>'can_restore',
            'manage_settings'=>'can_manage','manage_visibility'=>'can_manage_visibility',
        ];
        $action = pc_normalize_action($action);
        $column = $map[$action] ?? null;
        if (!$column) return null;
        if (!school_sidebar_service_table($pdo,'school_sidebar_action_permissions')
            || !school_sidebar_service_column($pdo,'school_sidebar_action_permissions',$column)) {
            return null;
        }
        return $column;
    }
}

if (!function_exists('pc_current_branch_id')) {
    function pc_current_branch_id(int $tenantId): int
    {
        if ($tenantId <= 0) {
            return 0;
        }

        $branchId = 0;

        if (function_exists('current_branch_id')) {
            try {
                $branchId = (int)current_branch_id();
            } catch (Throwable $e) {
                $branchId = 0;
            }
        }

        if ($branchId <= 0) {
            $branchId = (int)(
                $_SESSION['branch_id']
                ?? $_SESSION['default_branch_id']
                ?? 0
            );
        }

        return max(0, $branchId);
    }
}

if (!function_exists('pc_school_role_action')) {
    /**
     * School-level permission only. Branch restrictions are applied by
     * pc_raw_role_action() after this result is known.
     */
    function pc_school_role_action(
        PDO $pdo,
        int $tenantId,
        int $roleId,
        int $itemId,
        string $action,
        bool $missingDefaultsToAllow = false
    ): bool {
        if ($tenantId<=0 || $roleId<=0 || $itemId<=0) {
            return false;
        }

        $action = pc_normalize_action($action);

        if (school_sidebar_service_table(
            $pdo,
            'school_sidebar_permission_grants'
        )) {
            $stmt=$pdo->prepare(
                "SELECT is_allowed
                 FROM school_sidebar_permission_grants
                 WHERE tenant_id=:tenant_id
                   AND role_id=:role_id
                   AND sidebar_item_id=:item_id
                   AND action_key=:action_key
                 LIMIT 1"
            );
            $stmt->execute([
                'tenant_id'=>$tenantId,
                'role_id'=>$roleId,
                'item_id'=>$itemId,
                'action_key'=>$action,
            ]);

            $v=$stmt->fetchColumn();
            if ($v!==false) {
                return (int)$v===1;
            }

            if ($action!=='full_access') {
                $stmt->execute([
                    'tenant_id'=>$tenantId,
                    'role_id'=>$roleId,
                    'item_id'=>$itemId,
                    'action_key'=>'full_access',
                ]);
                $full=$stmt->fetchColumn();
                if ($full!==false && (int)$full===1) {
                    return true;
                }
            }
        }

        $column=pc_action_column($pdo,$action);
        if ($column) {
            $stmt=$pdo->prepare(
                "SELECT {$column}
                 FROM school_sidebar_action_permissions
                 WHERE tenant_id=:tenant_id
                   AND role_id=:role_id
                   AND sidebar_item_id=:item_id
                 LIMIT 1"
            );
            $stmt->execute([
                'tenant_id'=>$tenantId,
                'role_id'=>$roleId,
                'item_id'=>$itemId,
            ]);
            $v=$stmt->fetchColumn();
            if ($v!==false) {
                return (int)$v===1;
            }
        }

        if ($action==='view'
            && school_sidebar_service_table(
                $pdo,
                'role_sidebar_permissions'
            )) {
            $stmt=$pdo->prepare(
                "SELECT can_show
                 FROM role_sidebar_permissions
                 WHERE role_id=:role_id
                   AND sidebar_item_id=:item_id
                 LIMIT 1"
            );
            $stmt->execute([
                'role_id'=>$roleId,
                'item_id'=>$itemId,
            ]);
            $v=$stmt->fetchColumn();
            if ($v!==false) {
                return (int)$v===1;
            }
        }

        if ($missingDefaultsToAllow) {
            return pc_effective_school_visibility(
                $pdo,
                $tenantId,
                $itemId
            );
        }

        return false;
    }
}

if (!function_exists('pc_branch_role_action_override')) {
    /**
     * null = no branch-specific decision, so inherit school permission.
     * true/false = explicit branch decision.
     */
    function pc_branch_role_action_override(
        PDO $pdo,
        int $tenantId,
        int $branchId,
        int $roleId,
        int $itemId,
        string $action
    ): ?bool {
        if ($tenantId<=0
            || $branchId<=0
            || $roleId<=0
            || $itemId<=0
            || !school_sidebar_service_table(
                $pdo,
                'branch_sidebar_permission_grants'
            )) {
            return null;
        }

        $action=pc_normalize_action($action);

        $stmt=$pdo->prepare(
            "SELECT is_allowed
             FROM branch_sidebar_permission_grants
             WHERE tenant_id=:tenant_id
               AND branch_id=:branch_id
               AND role_id=:role_id
               AND sidebar_item_id=:item_id
               AND action_key=:action_key
             LIMIT 1"
        );
        $base=[
            'tenant_id'=>$tenantId,
            'branch_id'=>$branchId,
            'role_id'=>$roleId,
            'item_id'=>$itemId,
        ];

        $stmt->execute(
            $base + ['action_key'=>$action]
        );
        $v=$stmt->fetchColumn();

        if ($v!==false) {
            return (int)$v===1;
        }

        if ($action!=='full_access') {
            $stmt->execute(
                $base + ['action_key'=>'full_access']
            );
            $full=$stmt->fetchColumn();

            if ($full!==false && (int)$full===1) {
                return true;
            }
        }

        return null;
    }
}

if (!function_exists('pc_raw_role_action')) {
    function pc_raw_role_action(
        PDO $pdo,
        int $tenantId,
        int $roleId,
        int $itemId,
        string $action,
        bool $missingDefaultsToAllow = false
    ): bool {
        $action=pc_normalize_action($action);

        /*
         * The school permission is the maximum cap. Branch permissions are a
         * child layer: no branch row means inherit; an explicit branch row can
         * restrict the school permission but can never exceed it.
         */
        $schoolAllowed=pc_school_role_action(
            $pdo,
            $tenantId,
            $roleId,
            $itemId,
            $action,
            $missingDefaultsToAllow
        );

        if (!$schoolAllowed) {
            return false;
        }

        $branchId=pc_current_branch_id($tenantId);
        if ($branchId<=0) {
            return true;
        }

        $branchDecision=pc_branch_role_action_override(
            $pdo,
            $tenantId,
            $branchId,
            $roleId,
            $itemId,
            $action
        );

        return $branchDecision===null
            ? true
            : $branchDecision;
    }
}

if (!function_exists('pc_super_cap')) {
    function pc_super_cap(
        PDO $pdo,
        int $tenantId,
        int $itemId,
        string $action,
        string $capRoleKey='school_admin'
    ): bool {
        $capRoleKey=pc_role_key($capRoleKey);
        $roleId=pc_role_id($pdo,$tenantId,$capRoleKey);
        if ($roleId<=0) return false;

        $allowed=pc_raw_role_action(
            $pdo,$tenantId,$roleId,$itemId,$action,
            $capRoleKey==='school_admin'
        );

        if (pc_normalize_action($action)==='view') {
            $allowed=$allowed && pc_effective_school_visibility($pdo,$tenantId,$itemId);
        }
        return $allowed;
    }
}

if (!function_exists('pc_parent_delegate')) {
    function pc_parent_delegate(
        PDO $pdo,
        int $tenantId,
        int $parentRoleId,
        int $itemId,
        string $action
    ): bool {
        if (!school_sidebar_service_table($pdo,'school_sidebar_permission_grants')) {
            return true;
        }
        $key='parent_delegate_'.pc_normalize_action($action);
        $stmt=$pdo->prepare(
            "SELECT is_allowed FROM school_sidebar_permission_grants
             WHERE tenant_id=:tenant_id AND role_id=:role_id
               AND sidebar_item_id=:item_id AND action_key=:action_key
             LIMIT 1"
        );
        $stmt->execute([
            'tenant_id'=>$tenantId,'role_id'=>$parentRoleId,
            'item_id'=>$itemId,'action_key'=>$key
        ]);
        $v=$stmt->fetchColumn();
        return $v===false ? true : ((int)$v===1);
    }
}

if (!function_exists('pc_effective_role_action')) {
    function pc_effective_role_action(
        PDO $pdo,
        int $tenantId,
        int $roleId,
        int $itemId,
        string $action
    ): bool {
        $role=pc_role($pdo,$tenantId,$roleId);
        if (!$role) return false;
        $key=pc_role_key((string)$role['role_key']);
        $action=pc_normalize_action($action);

        if ($key==='parent') {
            return pc_super_cap($pdo,$tenantId,$itemId,$action,'parent')
                && pc_parent_delegate($pdo,$tenantId,$roleId,$itemId,$action);
        }

        if (!pc_super_cap($pdo,$tenantId,$itemId,$action,'school_admin')) {
            return false;
        }

        if ($key==='school_admin') return true;

        return pc_raw_role_action(
            $pdo,$tenantId,$roleId,$itemId,$action,false
        );
    }
}

if (!function_exists('pc_effective_role_chain_action')) {
    function pc_effective_role_chain_action(
        PDO $pdo,int $tenantId,int $roleId,array $chain,string $action
    ): bool {
        foreach (array_values($chain) as $i=>$itemId) {
            if (!pc_effective_role_action(
                $pdo,$tenantId,$roleId,(int)$itemId,$i===0?$action:'view'
            )) return false;
        }
        return true;
    }
}

if (!function_exists('pc_ensure_parent_permission_page')) {
    function pc_ensure_parent_permission_page(PDO $pdo): void
    {
        school_sidebar_service_ensure($pdo);
        if (!school_sidebar_service_table($pdo,'sidebar_items')) return;

        $moduleId=0;
        if (school_sidebar_service_table($pdo,'app_modules')) {
            $stmt=$pdo->query(
                "SELECT id FROM app_modules
                 WHERE module_key IN('parent_management','school_navigation')
                   AND portal_scope IN('school','all') AND is_active=1
                 ORDER BY module_key='parent_management' DESC,id LIMIT 1"
            );
            $moduleId=(int)($stmt->fetchColumn()?:0);
        }

        $parentId=0;
        $stmt=$pdo->query(
            "SELECT id FROM sidebar_items
             WHERE portal_scope IN('school','all') AND is_active=1
               AND menu_key IN('parents','parent_management','user_management','users')
             ORDER BY menu_key='parents' DESC,menu_key='parent_management' DESC,id
             LIMIT 1"
        );
        $parentId=(int)($stmt->fetchColumn()?:0);

        $stmt=$pdo->query(
            "SELECT id FROM sidebar_items
             WHERE menu_key='parent_sidebar_permissions' LIMIT 1"
        );
        $itemId=(int)($stmt->fetchColumn()?:0);

        if ($itemId<=0) {
            $ins=$pdo->prepare(
                "INSERT INTO sidebar_items
                 (parent_id,module_id,menu_key,menu_title,route,icon,
                  portal_scope,owner_tenant_id,display_order,show_in_sidebar,is_active)
                 VALUES
                 (:parent_id,:module_id,'parent_sidebar_permissions',
                  'Parent Sidebar Permission','school/parent-sidebar-permissions.php',
                  'shield-check','school',NULL,99,1,1)"
            );
            $ins->execute([
                'parent_id'=>$parentId?:null,'module_id'=>$moduleId?:null
            ]);
            $itemId=(int)$pdo->lastInsertId();
        } else {
            $upd=$pdo->prepare(
                "UPDATE sidebar_items SET
                   parent_id=:parent_id,module_id=:module_id,
                   menu_title='Parent Sidebar Permission',
                   route='school/parent-sidebar-permissions.php',
                   icon='shield-check',portal_scope='school',
                   show_in_sidebar=1,is_active=1
                 WHERE id=:id"
            );
            $upd->execute([
                'parent_id'=>$parentId?:null,'module_id'=>$moduleId?:null,'id'=>$itemId
            ]);
        }

        if (school_sidebar_service_table($pdo,'sidebar_default_settings')) {
            $stmt=$pdo->prepare(
                "INSERT IGNORE INTO sidebar_default_settings
                 (sidebar_item_id,is_enabled,display_order,updated_by)
                 VALUES(:item_id,1,99,NULL)"
            );
            $stmt->execute(['item_id'=>$itemId]);
        }
        if (school_sidebar_service_table($pdo,'sidebar_role_master_items')) {
            $stmt=$pdo->prepare(
                "INSERT INTO sidebar_role_master_items
                 (role_key,sidebar_item_id,is_enabled,display_order,created_by,updated_by)
                 VALUES('school_admin',:item_id,1,99,NULL,NULL)
                 ON DUPLICATE KEY UPDATE is_enabled=1"
            );
            $stmt->execute(['item_id'=>$itemId]);
        }
        school_sidebar_service_role_master_remove($pdo,'parent',$itemId);

        if ($moduleId>0 && school_sidebar_service_table($pdo,'app_pages')) {
            $page=$pdo->prepare(
                "INSERT INTO app_pages
                 (module_id,page_key,page_name,portal_scope,route,icon,
                  parent_page_id,display_order,show_in_sidebar,is_active)
                 VALUES
                 (:module_id,'parent_sidebar_permissions','Parent Sidebar Permission',
                  'school','school/parent-sidebar-permissions.php','shield-check',
                  NULL,99,1,1)
                 ON DUPLICATE KEY UPDATE
                   module_id=VALUES(module_id),page_name=VALUES(page_name),
                   portal_scope='school',route=VALUES(route),icon=VALUES(icon),
                   display_order=VALUES(display_order),is_active=1"
            );
            $page->execute(['module_id'=>$moduleId]);
            $pageId=(int)($pdo->query(
                "SELECT id FROM app_pages
                 WHERE page_key='parent_sidebar_permissions'
                   AND portal_scope='school' LIMIT 1"
            )->fetchColumn()?:0);

            if ($pageId>0 && school_sidebar_service_table($pdo,'app_page_api_routes')) {
                $map=$pdo->prepare(
                    "INSERT INTO app_page_api_routes(page_id,api_route,is_active)
                     VALUES(:page_id,'api/parent-sidebar-permissions.php',1)
                     ON DUPLICATE KEY UPDATE is_active=1,updated_at=CURRENT_TIMESTAMP"
                );
                $map->execute(['page_id'=>$pageId]);
            }
        }
    }
}

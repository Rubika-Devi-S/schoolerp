<?php
declare(strict_types=1);

$pageTitle = 'School Sidebar Permissions';
$pageKey = 'school_sidebar_permissions';
$sidebarFile = __DIR__ . '/sidebar.php';

$isAjax = isset($_GET['ajax']) && (string)$_GET['ajax'] === '1';

if ($isAjax) {
    require_once dirname(__DIR__) . '/includes/bootstrap.php';
    require_once dirname(__DIR__) . '/includes/school-sidebar-service.php';

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    function sp_json(bool $success, string $message = '', array $data = [], int $status = 200): never
    {
        http_response_code($status);
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    function sp_input(): array
    {
        $type = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
        if (str_contains($type, 'application/json')) {
            $decoded = json_decode((string)file_get_contents('php://input'), true);
            return is_array($decoded) ? $decoded : [];
        }
        return $_POST;
    }

    function sp_table(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?");
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    }

    function sp_column(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?");
        $stmt->execute([$table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    }

    function sp_super_admin(PDO $pdo): bool
    {
        $roleId = (int)($_SESSION['role_id'] ?? 0);
        $roleKey = strtolower(trim((string)($_SESSION['role_key'] ?? '')));
        $userId = (int)($_SESSION['user_id'] ?? 0);

        if (function_exists('current_user')) {
            $u = current_user();
            $roleId = (int)($u['role_id'] ?? $roleId);
            $roleKey = strtolower(trim((string)($u['role_key'] ?? $roleKey)));
        }

        $aliases = ['super_admin', 'super-administrator', 'super_administrator'];
        if ($roleId === 1 || in_array($roleKey, $aliases, true)) {
            return true;
        }

        if ($roleId > 0 && sp_table($pdo, 'roles')) {
            $stmt = $pdo->prepare("SELECT role_key FROM roles WHERE id=? AND status='active' LIMIT 1");
            $stmt->execute([$roleId]);
            if (in_array(strtolower(trim((string)$stmt->fetchColumn())), $aliases, true)) {
                return true;
            }
        }

        if ($userId > 0 && sp_table($pdo, 'users') && sp_table($pdo, 'roles')) {
            $stmt = $pdo->prepare("SELECT r.role_key FROM users u LEFT JOIN roles r ON r.id=u.role_id WHERE u.id=? LIMIT 1");
            $stmt->execute([$userId]);
            if (in_array(strtolower(trim((string)$stmt->fetchColumn())), $aliases, true)) {
                return true;
            }
        }

        return false;
    }

    function sp_action_key(string $key): string
    {
        $key = strtolower(trim($key));
        $key = preg_replace('/[^a-z0-9]+/', '_', $key) ?? '';
        $key = trim($key, '_');

        return match ($key) {
            'add' => 'create',
            'manage', 'settings' => 'manage_settings',
            'visibility' => 'manage_visibility',
            'full' => 'full_access',
            default => $key,
        };
    }

    /**
     * Standard actions are always shown. Extra active permission_actions rows
     * are appended automatically, so the page stays future-proof.
     *
     * @return array<int,array{action_key:string,action_name:string}>
     */
    function sp_action_catalog(PDO $pdo): array
    {
        $standard = [
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
            'full_access' => 'Full Access',
        ];

        $result = [];
        foreach ($standard as $key => $name) {
            $result[$key] = [
                'action_key' => $key,
                'action_name' => $name,
            ];
        }

        if (sp_table($pdo, 'permission_actions')) {
            try {
                $rows = $pdo->query(
                    "SELECT action_key,action_name
                     FROM permission_actions
                     WHERE is_active=1
                     ORDER BY display_order,id"
                )->fetchAll(PDO::FETCH_ASSOC);

                foreach ($rows as $row) {
                    $key = sp_action_key((string)($row['action_key'] ?? ''));
                    if ($key === '') continue;
                    if (isset($result[$key])) continue;
                    $result[$key] = [
                        'action_key' => $key,
                        'action_name' => trim((string)($row['action_name'] ?? '')) ?: ucwords(str_replace('_', ' ', $key)),
                    ];
                }
            } catch (Throwable $e) {
                error_log('sp_action_catalog: ' . $e->getMessage());
            }
        }

        return array_values($result);
    }

    function sp_ensure_schema(PDO $pdo): void
    {
        if (!sp_table($pdo, 'sidebar_items')) {
            sp_json(false, 'sidebar_items table is missing.', [], 500);
        }

        if (!sp_table($pdo, 'permission_actions')) {
            $pdo->exec("CREATE TABLE permission_actions (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                action_key varchar(50) NOT NULL,
                action_name varchar(80) NOT NULL,
                display_order int NOT NULL DEFAULT 0,
                is_active tinyint(1) NOT NULL DEFAULT 1,
                created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY(id), UNIQUE KEY uq_permission_action(action_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }

        $pdo->exec("INSERT INTO permission_actions(action_key,action_name,display_order,is_active) VALUES
            ('view','View',10,1),
            ('create','Add',20,1),
            ('edit','Edit',30,1),
            ('delete','Delete',40,1),
            ('print','Print',50,1),
            ('pdf','PDF',60,1),
            ('export','Export',70,1),
            ('import','Import',80,1),
            ('approve','Approve',90,1),
            ('reject','Reject',100,1),
            ('restore','Restore',110,1),
            ('manage_settings','Manage',120,1),
            ('full_access','Full Access',999,1)
            ON DUPLICATE KEY UPDATE
                action_name=VALUES(action_name),
                display_order=VALUES(display_order),
                is_active=VALUES(is_active)");

        /* Old builds used action_key=manage. Keep only the canonical key now. */
        try {
            $pdo->exec("UPDATE permission_actions SET is_active=0 WHERE action_key='manage'");
        } catch (Throwable $e) {
            // Safe compatibility cleanup only.
        }

        if (!sp_table($pdo, 'sidebar_default_settings')) {
            $pdo->exec("CREATE TABLE sidebar_default_settings (
                sidebar_item_id bigint unsigned NOT NULL,
                is_enabled tinyint(1) NOT NULL DEFAULT 1,
                display_order int DEFAULT NULL,
                updated_by bigint unsigned DEFAULT NULL,
                created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY(sidebar_item_id),
                KEY idx_sidebar_default_enabled(is_enabled,display_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }

        if (!sp_table($pdo, 'school_sidebar_permission_grants')) {
            $pdo->exec("CREATE TABLE school_sidebar_permission_grants (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                tenant_id bigint unsigned NOT NULL,
                role_id bigint unsigned NOT NULL,
                sidebar_item_id bigint unsigned NOT NULL,
                action_key varchar(50) NOT NULL,
                is_allowed tinyint(1) NOT NULL DEFAULT 0,
                created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY(id),
                UNIQUE KEY uq_school_sidebar_grant(tenant_id,role_id,sidebar_item_id,action_key),
                KEY idx_school_sidebar_grant_role(tenant_id,role_id),
                KEY idx_school_sidebar_grant_item(sidebar_item_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }

        if (!sp_table($pdo, 'tenant_sidebar_items')) {
            $pdo->exec("CREATE TABLE tenant_sidebar_items (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                tenant_id bigint unsigned NOT NULL,
                sidebar_item_id bigint unsigned NOT NULL,
                custom_title varchar(120) DEFAULT NULL,
                custom_icon varchar(4096) DEFAULT NULL,
                custom_route varchar(255) DEFAULT NULL,
                custom_parent_id bigint unsigned DEFAULT NULL,
                display_order int DEFAULT NULL,
                is_visible tinyint(1) NOT NULL DEFAULT 1,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY(id), UNIQUE KEY uq_tenant_sidebar(tenant_id,sidebar_item_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }

        if (!sp_table($pdo, 'school_sidebar_action_permissions')) {
            $pdo->exec("CREATE TABLE school_sidebar_action_permissions (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                tenant_id bigint unsigned NOT NULL,
                role_id bigint unsigned NOT NULL,
                sidebar_item_id bigint unsigned NOT NULL,
                can_view tinyint(1) NOT NULL DEFAULT 0,
                can_add tinyint(1) NOT NULL DEFAULT 0,
                can_edit tinyint(1) NOT NULL DEFAULT 0,
                can_delete tinyint(1) NOT NULL DEFAULT 0,
                can_print tinyint(1) NOT NULL DEFAULT 0,
                can_pdf tinyint(1) NOT NULL DEFAULT 0,
                can_export tinyint(1) NOT NULL DEFAULT 0,
                can_import tinyint(1) NOT NULL DEFAULT 0,
                can_approve tinyint(1) NOT NULL DEFAULT 0,
                can_reject tinyint(1) NOT NULL DEFAULT 0,
                can_restore tinyint(1) NOT NULL DEFAULT 0,
                can_manage tinyint(1) NOT NULL DEFAULT 0,
                can_manage_visibility tinyint(1) NOT NULL DEFAULT 0,
                created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY(id), UNIQUE KEY uq_school_sidebar_action(tenant_id,role_id,sidebar_item_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }

        /*
         * Standard actions use the SAME row as View/Add/Edit/Delete.
         * Missing columns are added automatically for older databases.
         */
        $addedExtendedColumns = false;

        /*
         * PDF used to follow Export. Preserve the existing Export decision
         * when the dedicated PDF permission is introduced.
         */
        if (!sp_column($pdo, 'school_sidebar_action_permissions', 'can_pdf')) {
            $pdo->exec(
                "ALTER TABLE school_sidebar_action_permissions
                 ADD COLUMN can_pdf tinyint(1) NOT NULL DEFAULT 0
                 AFTER can_print"
            );

            if (sp_column($pdo, 'school_sidebar_action_permissions', 'can_export')) {
                $pdo->exec(
                    "UPDATE school_sidebar_action_permissions
                     SET can_pdf = can_export"
                );
            }

            $addedExtendedColumns = true;
        }

        foreach ([
            'can_print',
            'can_export',
            'can_import',
            'can_approve',
            'can_reject',
            'can_restore',
            'can_manage',
        ] as $permissionColumn) {
            if (!sp_column($pdo, 'school_sidebar_action_permissions', $permissionColumn)) {
                $pdo->exec(
                    "ALTER TABLE school_sidebar_action_permissions
                     ADD COLUMN {$permissionColumn} tinyint(1) NOT NULL DEFAULT 0
                     AFTER can_delete"
                );
                $addedExtendedColumns = true;
            }
        }

        /*
         * Older School Administrator rows already existed before the extended
         * columns. Only on the first upgrade, initialise those new actions to
         * allowed so existing School Admin screens do not suddenly lose Print,
         * Export, Approve, etc. Future explicit 0 values are never overwritten.
         */
        if ($addedExtendedColumns && sp_table($pdo, 'roles')) {
            $pdo->exec(
                "UPDATE school_sidebar_action_permissions sap
                 INNER JOIN roles r ON r.id=sap.role_id
                 SET sap.can_print=1,
                     sap.can_pdf=1,
                     sap.can_export=1,
                     sap.can_import=1,
                     sap.can_approve=1,
                     sap.can_reject=1,
                     sap.can_restore=1,
                     sap.can_manage=1
                 WHERE LOWER(r.role_key) IN (
                    'school_admin','school_administrator','school-administrator'
                 )"
            );
        }

        // Backfill normalized grants from the same action matrix.
        if (sp_table($pdo, 'school_sidebar_action_permissions')) {
            foreach ([
                'view' => 'can_view',
                'create' => 'can_add',
                'edit' => 'can_edit',
                'delete' => 'can_delete',
                'print' => 'can_print',
                'pdf' => 'can_pdf',
                'export' => 'can_export',
                'import' => 'can_import',
                'approve' => 'can_approve',
                'reject' => 'can_reject',
                'restore' => 'can_restore',
                'manage_settings' => 'can_manage',
            ] as $action => $column) {
                $pdo->exec("INSERT IGNORE INTO school_sidebar_permission_grants(tenant_id,role_id,sidebar_item_id,action_key,is_allowed)
                    SELECT tenant_id,role_id,sidebar_item_id,'{$action}',{$column}
                    FROM school_sidebar_action_permissions");
            }
        }
    }

    function sp_csrf(array $input): void
    {
        $token = (string)($input['csrf_token'] ?? '');
        $ok = function_exists('csrf_is_valid') ? csrf_is_valid($token) : false;
        if (!$ok) {
            sp_json(false, 'Session expired. Refresh the page and try again.', [], 419);
        }
    }

    function sp_school(PDO $pdo, int $schoolId): array
    {
        if ($schoolId <= 0) return [];
        $stmt = $pdo->prepare("SELECT id,tenant_code,school_name FROM tenants WHERE id=? AND status IN ('active','trial') LIMIT 1");
        $stmt->execute([$schoolId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) sp_json(false, 'Selected school is unavailable.', [], 422);
        return $row;
    }

    function sp_role(PDO $pdo, int $schoolId, int $roleId): array
    {
        if ($schoolId <= 0 || $roleId <= 0) return [];
        $stmt = $pdo->prepare("SELECT id,role_key,role_name FROM roles WHERE id=? AND tenant_id=? AND status='active' LIMIT 1");
        $stmt->execute([$roleId, $schoolId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) sp_json(false, 'Selected role does not belong to this school.', [], 422);
        return $row;
    }

    function sp_tree(array $rows): array
    {
        $byParent = [];
        $ids = [];
        foreach ($rows as $row) {
            $id = (int)$row['sidebar_item_id'];
            $ids[$id] = true;
        }
        foreach ($rows as $row) {
            $parent = (int)($row['parent_id'] ?? 0);
            if ($parent > 0 && !isset($ids[$parent])) $parent = 0;
            $byParent[$parent][] = $row;
        }
        foreach ($byParent as &$group) {
            usort($group, static fn($a, $b) => [(int)$a['display_order'], (int)$a['sidebar_item_id']] <=> [(int)$b['display_order'], (int)$b['sidebar_item_id']]);
        }
        unset($group);

        $out = [];
        $walk = function (int $parent, int $depth) use (&$walk, &$out, $byParent): void {
            foreach ($byParent[$parent] ?? [] as $row) {
                $row['depth'] = $depth;
                $out[] = $row;
                $walk((int)$row['sidebar_item_id'], $depth + 1);
            }
        };
        $walk(0, 0);
        return $out;
    }

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        sp_json(false, 'Database connection unavailable.', [], 500);
    }
    if (!sp_super_admin($pdo)) {
        sp_json(false, 'Only Super Administrator can manage School Sidebar Permissions.', [], 403);
    }

    try {
        sp_ensure_schema($pdo);
        school_sidebar_service_ensure($pdo);
        $input = sp_input();
        $action = strtolower(trim((string)($_GET['action'] ?? $input['action'] ?? 'meta')));

        if ($action === 'meta') {
            $schools = $pdo->query("SELECT id,tenant_code,school_name FROM tenants WHERE status IN ('active','trial') ORDER BY school_name,id")->fetchAll(PDO::FETCH_ASSOC);
            $actions = sp_action_catalog($pdo);
            sp_json(true, 'Loaded.', [
                'schools' => $schools,
                'actions' => $actions,
                'csrf_token' => function_exists('csrfToken') ? csrfToken() : '',
            ]);
        }

        if ($action === 'roles') {
            $schoolId = (int)($_GET['school_id'] ?? 0);
            sp_school($pdo, $schoolId);
            $stmt = $pdo->prepare("SELECT id,role_key,role_name FROM roles WHERE tenant_id=? AND status='active' ORDER BY CASE WHEN role_key IN ('school_admin','school_administrator','school-administrator') THEN 0 ELSE 1 END, role_name,id");
            $stmt->execute([$schoolId]);
            sp_json(true, 'Roles loaded.', ['roles' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }

        if ($action === 'load') {
            $schoolId = (int)($_GET['school_id'] ?? 0);
            $roleId = (int)($_GET['role_id'] ?? 0);
            $role = [];
            $hasInherit = sp_column($pdo, 'tenant_sidebar_items', 'inherit_default');

            if ($schoolId > 0) {
                sp_school($pdo, $schoolId);
                if ($roleId > 0) $role = sp_role($pdo, $schoolId, $roleId);
            }

            if ($schoolId <= 0) {
                $sql = "SELECT
                    si.id AS sidebar_item_id,
                    si.parent_id,
                    si.menu_key,
                    si.menu_title AS display_title,
                    si.route AS display_route,
                    si.icon AS display_icon,
                    COALESCE(sds.display_order,si.display_order) AS display_order,
                    COALESCE(sds.is_enabled,1) AS is_visible,
                    0 AS is_override
                FROM sidebar_items si
                LEFT JOIN sidebar_default_settings sds ON sds.sidebar_item_id=si.id
                WHERE si.is_active=1 AND si.show_in_sidebar=1
                  AND si.portal_scope IN ('school','all')
                  AND si.owner_tenant_id IS NULL
                ORDER BY COALESCE(sds.display_order,si.display_order),si.id";
                $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            } else {
                /*
                 * STRICT assignment mode:
                 * a selected school shows only its own tenant_sidebar_items.
                 * Default Sidebar records are a catalogue, never a fallback.
                 */
                $inheritFilter = $hasInherit
                    ? 'AND COALESCE(tsi.inherit_default,0)=0'
                    : '';
                $sql = "SELECT
                    si.id AS sidebar_item_id,
                    COALESCE(tsi.custom_parent_id,si.parent_id) AS parent_id,
                    si.menu_key,
                    COALESCE(NULLIF(tsi.custom_title,''),si.menu_title) AS display_title,
                    COALESCE(NULLIF(tsi.custom_route,''),si.route) AS display_route,
                    COALESCE(NULLIF(tsi.custom_icon,''),si.icon) AS display_icon,
                    COALESCE(tsi.display_order,si.display_order) AS display_order,
                    1 AS is_visible,
                    1 AS is_override
                FROM tenant_sidebar_items tsi
                INNER JOIN sidebar_items si
                    ON si.id=tsi.sidebar_item_id
                WHERE tsi.tenant_id=:tenant_id
                  AND tsi.is_visible=1
                  {$inheritFilter}
                  AND si.is_active=1
                  AND si.show_in_sidebar=1
                  AND si.portal_scope IN ('school','all')
                  AND (si.owner_tenant_id IS NULL OR si.owner_tenant_id=:owner_id)
                ORDER BY COALESCE(tsi.display_order,si.display_order),si.id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'tenant_id' => $schoolId,
                    'owner_id' => $schoolId,
                ]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            $rows = sp_tree($rows);
            $grants = [];
            if ($schoolId > 0 && $roleId > 0) {
                $stmt = $pdo->prepare("SELECT sidebar_item_id,action_key,is_allowed FROM school_sidebar_permission_grants WHERE tenant_id=? AND role_id=?");
                $stmt->execute([$schoolId, $roleId]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $g) {
                    $key = sp_action_key((string)($g['action_key'] ?? ''));
                    if ($key === '') continue;
                    $itemId = (int)$g['sidebar_item_id'];
                    $grants[$itemId][$key] = max(
                        (int)($grants[$itemId][$key] ?? 0),
                        (int)$g['is_allowed']
                    );
                }
            }

            /*
             * Read standard actions from the same matrix as CRUD. This keeps
             * Print/Export/Import/Approve/Reject/Restore/Manage consistent with
             * View/Add/Edit/Delete.
             */
            if ($schoolId > 0 && $roleId > 0 && sp_table($pdo, 'school_sidebar_action_permissions')) {
                $matrixMap = [
                    'view' => 'can_view',
                    'create' => 'can_add',
                    'edit' => 'can_edit',
                    'delete' => 'can_delete',
                    'print' => 'can_print',
                    'pdf' => 'can_pdf',
                    'export' => 'can_export',
                    'import' => 'can_import',
                    'approve' => 'can_approve',
                    'reject' => 'can_reject',
                    'restore' => 'can_restore',
                    'manage_settings' => 'can_manage',
                    'manage_visibility' => 'can_manage_visibility',
                ];
                $available = [];
                foreach ($matrixMap as $actionKey => $columnName) {
                    if (sp_column($pdo, 'school_sidebar_action_permissions', $columnName)) {
                        $available[$actionKey] = $columnName;
                    }
                }
                if ($available !== []) {
                    $selects = ['sidebar_item_id'];
                    foreach ($available as $actionKey => $columnName) {
                        $selects[] = $columnName;
                    }
                    $stmt = $pdo->prepare(
                        "SELECT " . implode(',', array_values(array_unique($selects))) . "
                         FROM school_sidebar_action_permissions
                         WHERE tenant_id=? AND role_id=?"
                    );
                    $stmt->execute([$schoolId, $roleId]);
                    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $matrixRow) {
                        $itemId = (int)($matrixRow['sidebar_item_id'] ?? 0);
                        if ($itemId <= 0) continue;
                        foreach ($available as $actionKey => $columnName) {
                            $grants[$itemId][$actionKey] = (int)($matrixRow[$columnName] ?? 0);
                        }
                    }
                }
            }

            $isSchoolAdmin = in_array(strtolower((string)($role['role_key'] ?? '')), ['school_admin','school_administrator','school-administrator'], true);
            $actions = sp_action_catalog($pdo);

            foreach ($rows as &$row) {
                $itemId = (int)$row['sidebar_item_id'];
                $row['permissions'] = [];
                foreach ($actions as $a) {
                    $key = (string)$a['action_key'];
                    $row['permissions'][$key] = array_key_exists($key, $grants[$itemId] ?? [])
                        ? (int)$grants[$itemId][$key]
                        : ($isSchoolAdmin ? 1 : 0);
                }
            }
            unset($row);

            sp_json(true, 'Sidebar loaded.', [
                'items' => $rows,
                'actions' => $actions,
                'role' => $role,
            ]);
        }

        if ($action === 'save') {
            sp_csrf($input);
            $schoolId = (int)($input['school_id'] ?? 0);
            $roleId = (int)($input['role_id'] ?? 0);
            $items = $input['items'] ?? [];
            if (!is_array($items)) {
                sp_json(false, 'Invalid sidebar data.', [], 422);
            }

            $userId = (int)($_SESSION['user_id'] ?? 0) ?: null;

            if ($schoolId <= 0) {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare(
                    "INSERT INTO sidebar_default_settings
                        (sidebar_item_id,is_enabled,display_order,updated_by)
                     VALUES(:item_id,:enabled,:display_order,:user_id)
                     ON DUPLICATE KEY UPDATE
                        is_enabled=VALUES(is_enabled),
                        display_order=VALUES(display_order),
                        updated_by=VALUES(updated_by),
                        updated_at=CURRENT_TIMESTAMP"
                );
                foreach ($items as $item) {
                    if (!is_array($item)) continue;
                    $itemId=(int)($item['sidebar_item_id']??0);
                    if ($itemId<=0) continue;
                    $stmt->execute([
                        'item_id'=>$itemId,
                        'enabled'=>!empty($item['is_visible'])?1:0,
                        'display_order'=>max(0,(int)($item['display_order']??0)),
                        'user_id'=>$userId,
                    ]);
                }
                school_sidebar_service_bump_version($pdo, null);
                $pdo->commit();
                sp_json(true, 'Default Sidebar saved successfully.');
            }

            sp_school($pdo,$schoolId);
            if ($roleId<=0) sp_json(false,'Select a School Role.',[],422);
            sp_role($pdo,$schoolId,$roleId);

            /* Hidden parent removes its complete assigned subtree. */
            $remove=[];
            foreach ($items as $item) {
                if (!is_array($item) || !empty($item['is_visible'])) continue;
                $id=(int)($item['sidebar_item_id']??0);
                if ($id<=0) continue;
                $desc=school_sidebar_service_school_descendants($pdo,$schoolId,$id);
                if ($desc===[]) $desc=[$id];
                foreach ($desc as $descId) $remove[$descId]=$descId;
            }

            $grant=$pdo->prepare(
                "INSERT INTO school_sidebar_permission_grants
                    (tenant_id,role_id,sidebar_item_id,action_key,is_allowed)
                 VALUES(:tenant_id,:role_id,:item_id,:action_key,:allowed)
                 ON DUPLICATE KEY UPDATE
                    is_allowed=VALUES(is_allowed),
                    updated_at=CURRENT_TIMESTAMP"
            );
            $legacy=$pdo->prepare(
                "INSERT INTO school_sidebar_action_permissions(
                    tenant_id,role_id,sidebar_item_id,
                    can_view,can_add,can_edit,can_delete,
                    can_print,can_pdf,can_export,can_import,
                    can_approve,can_reject,can_restore,can_manage,
                    can_manage_visibility
                 ) VALUES(
                    :tenant_id,:role_id,:item_id,
                    :can_view,:can_add,:can_edit,:can_delete,
                    :can_print,:can_pdf,:can_export,:can_import,
                    :can_approve,:can_reject,:can_restore,:can_manage,
                    :can_manage_visibility
                 )
                 ON DUPLICATE KEY UPDATE
                    can_view=VALUES(can_view),can_add=VALUES(can_add),
                    can_edit=VALUES(can_edit),can_delete=VALUES(can_delete),
                    can_print=VALUES(can_print),can_pdf=VALUES(can_pdf),
                    can_export=VALUES(can_export),can_import=VALUES(can_import),
                    can_approve=VALUES(can_approve),can_reject=VALUES(can_reject),
                    can_restore=VALUES(can_restore),can_manage=VALUES(can_manage),
                    can_manage_visibility=VALUES(can_manage_visibility),
                    updated_at=CURRENT_TIMESTAMP"
            );

            $pdo->beginTransaction();
            if ($remove!==[]) {
                school_sidebar_service_remove_school_items(
                    $pdo,$schoolId,array_values($remove)
                );
            }

            foreach ($items as $item) {
                if (!is_array($item)) continue;
                $itemId=(int)($item['sidebar_item_id']??0);
                if ($itemId<=0 || isset($remove[$itemId]) || empty($item['is_visible'])) {
                    continue;
                }

                school_sidebar_service_assign_parent_chain($pdo,$schoolId,$itemId);
                school_sidebar_service_assign_item($pdo,$schoolId,$itemId);

                $raw=is_array($item['permissions']??null)?$item['permissions']:[];
                $permissions=[];
                foreach ($raw as $actionKey=>$allowed) {
                    $actionKey=sp_action_key((string)$actionKey);
                    if ($actionKey==='' || !preg_match('/^[a-z0-9_]{1,50}$/',$actionKey)) continue;
                    $permissions[$actionKey]=!empty($allowed)?1:0;
                    $grant->execute([
                        'tenant_id'=>$schoolId,'role_id'=>$roleId,
                        'item_id'=>$itemId,'action_key'=>$actionKey,
                        'allowed'=>$permissions[$actionKey],
                    ]);
                }
                $legacy->execute([
                    'tenant_id'=>$schoolId,'role_id'=>$roleId,'item_id'=>$itemId,
                    'can_view'=>!empty($permissions['view'])?1:0,
                    'can_add'=>!empty($permissions['create'])||!empty($permissions['add'])?1:0,
                    'can_edit'=>!empty($permissions['edit'])?1:0,
                    'can_delete'=>!empty($permissions['delete'])?1:0,
                    'can_print'=>!empty($permissions['print'])?1:0,
                    'can_pdf'=>!empty($permissions['pdf'])?1:0,
                    'can_export'=>!empty($permissions['export'])?1:0,
                    'can_import'=>!empty($permissions['import'])?1:0,
                    'can_approve'=>!empty($permissions['approve'])?1:0,
                    'can_reject'=>!empty($permissions['reject'])?1:0,
                    'can_restore'=>!empty($permissions['restore'])?1:0,
                    'can_manage'=>!empty($permissions['manage_settings'])||!empty($permissions['manage'])?1:0,
                    'can_manage_visibility'=>!empty($permissions['manage_visibility'])?1:0,
                ]);
            }

            school_sidebar_service_bump_version($pdo,$schoolId);
            $pdo->commit();
            sp_json(true,'School sidebar and role permissions saved successfully.');
        }

        if ($action === 'reset_school') {
            sp_csrf($input);
            $schoolId=(int)($input['school_id']??0);
            sp_school($pdo,$schoolId);
            $pdo->beginTransaction();
            school_sidebar_service_clear_school($pdo,$schoolId);
            school_sidebar_service_bump_version($pdo,$schoolId);
            $pdo->commit();
            sp_json(true,'Selected school sidebar assignments cleared successfully.');
        }

        if ($action === 'add') {
            sp_csrf($input);
            $schoolId=(int)($input['school_id']??0);
            $roleId=(int)($input['role_id']??0);
            if ($schoolId>0) {
                sp_school($pdo,$schoolId);
                if ($roleId>0) sp_role($pdo,$schoolId,$roleId);
            }

            $pdo->beginTransaction();
            $created=school_sidebar_service_create_menu(
                $pdo,
                $schoolId,
                $input,
                (int)($_SESSION['user_id']??0)?:null
            );
            $itemId=(int)$created['sidebar_item_id'];

            /* Newly assigned item is immediately View-visible for selected role. */
            if ($schoolId>0 && $roleId>0) {
                $role=sp_role($pdo,$schoolId,$roleId);
                $full=in_array(
                    strtolower((string)($role['role_key']??'')),
                    ['school_admin','school_administrator','school-administrator'],
                    true
                );
                foreach (sp_action_catalog($pdo) as $actionRow) {
                    $actionKey=(string)$actionRow['action_key'];
                    $allowed=($full || $actionKey==='view')?1:0;
                    $stmt=$pdo->prepare(
                        "INSERT INTO school_sidebar_permission_grants
                            (tenant_id,role_id,sidebar_item_id,action_key,is_allowed)
                         VALUES(?,?,?,?,?)
                         ON DUPLICATE KEY UPDATE
                            is_allowed=VALUES(is_allowed),updated_at=CURRENT_TIMESTAMP"
                    );
                    $stmt->execute([$schoolId,$roleId,$itemId,$actionKey,$allowed]);
                }
                $stmt=$pdo->prepare(
                    "INSERT INTO school_sidebar_action_permissions(
                        tenant_id,role_id,sidebar_item_id,
                        can_view,can_add,can_edit,can_delete,
                        can_print,can_pdf,can_export,can_import,
                        can_approve,can_reject,can_restore,can_manage,can_manage_visibility
                     ) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE
                        can_view=VALUES(can_view),can_add=VALUES(can_add),
                        can_edit=VALUES(can_edit),can_delete=VALUES(can_delete),
                        can_print=VALUES(can_print),can_pdf=VALUES(can_pdf),
                        can_export=VALUES(can_export),can_import=VALUES(can_import),
                        can_approve=VALUES(can_approve),can_reject=VALUES(can_reject),
                        can_restore=VALUES(can_restore),can_manage=VALUES(can_manage),
                        can_manage_visibility=VALUES(can_manage_visibility),
                        updated_at=CURRENT_TIMESTAMP"
                );
                $v=$full?1:0;
                $stmt->execute([
                    $schoolId,$roleId,$itemId,1,$v,$v,$v,$v,$v,$v,$v,$v,$v,$v,$v,1
                ]);
            }

            school_sidebar_service_bump_version(
                $pdo,$schoolId>0?$schoolId:null
            );
            $pdo->commit();
            sp_json(
                true,
                !empty($created['reused'])
                    ? 'Existing Default Sidebar menu assigned without creating a duplicate.'
                    : ($schoolId>0
                        ? 'School sidebar created and added to Default Sidebar successfully.'
                        : 'Default sidebar menu created successfully.'),
                ['sidebar_item_id'=>$itemId,'reused'=>(bool)$created['reused']],
                201
            );
        }

        if ($action === 'edit_menu') {
            sp_csrf($input);
            $schoolId=(int)($input['school_id']??0);
            if ($schoolId>0) sp_school($pdo,$schoolId);
            $itemId=(int)($input['sidebar_item_id']??0);
            if ($itemId<=0) sp_json(false,'Invalid sidebar menu.',[],422);

            $pdo->beginTransaction();
            school_sidebar_service_edit_menu(
                $pdo,$schoolId,$itemId,$input,
                (int)($_SESSION['user_id']??0)?:null
            );
            school_sidebar_service_bump_version(
                $pdo,$schoolId>0?$schoolId:null
            );
            $pdo->commit();
            sp_json(true,$schoolId>0
                ? 'School sidebar menu updated successfully.'
                : 'Default sidebar menu updated successfully.');
        }

        if ($action === 'delete_menu') {
            sp_csrf($input);
            $schoolId=(int)($input['school_id']??0);
            if ($schoolId>0) sp_school($pdo,$schoolId);
            $itemId=(int)($input['sidebar_item_id']??0);
            if ($itemId<=0) sp_json(false,'Invalid sidebar menu.',[],422);

            $pdo->beginTransaction();
            $affected=school_sidebar_service_delete_menu(
                $pdo,$schoolId,$itemId
            );
            school_sidebar_service_bump_version(
                $pdo,$schoolId>0?$schoolId:null
            );
            $pdo->commit();

            sp_json(
                true,
                $schoolId>0
                    ? 'Sidebar menu and its assigned submenus removed from this school.'
                    : 'Default sidebar menu, submenus and related records deleted successfully.',
                ['affected_items'=>count($affected)]
            );
        }

        sp_json(false, 'Invalid request.', [], 400);
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        error_log('simple-school-sidebar-permissions: ' . $e->getMessage());
        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        $message = (str_contains($host, 'localhost') || str_contains($host, '127.0.0.1'))
            ? 'Sidebar request failed: ' . $e->getMessage()
            : 'Unable to complete the sidebar request.';
        sp_json(false, $message, [], 500);
    }
}

require dirname(__DIR__) . '/includes/layout-start.php';

/*
 * Existing common toast component.
 * New bootstrap versions inject it globally; older versions do not.
 * Avoid duplicate toast containers/scripts.
 */
require_once dirname(__DIR__) . '/includes/common-toast.php';

$renderCommonToastLocally = !function_exists(
    'school_enable_common_toast_ui'
);

$currentUser = function_exists('current_user') ? current_user() : [];
$currentRoleId = (int)($currentUser['role_id'] ?? $_SESSION['role_id'] ?? 0);
$currentRoleKey = strtolower(trim((string)($currentUser['role_key'] ?? $_SESSION['role_key'] ?? '')));
$isSuperAdmin = $currentRoleId === 1 || in_array($currentRoleKey, ['super_admin','super-administrator','super_administrator'], true);
if (!$isSuperAdmin && isset($pdo) && $pdo instanceof PDO && $currentRoleId > 0) {
    try {
        $s = $pdo->prepare("SELECT role_key FROM roles WHERE id=? AND status='active' LIMIT 1");
        $s->execute([$currentRoleId]);
        $isSuperAdmin = in_array(strtolower(trim((string)$s->fetchColumn())), ['super_admin','super-administrator','super_administrator'], true);
    } catch (Throwable $e) {}
}

if (!$isSuperAdmin) {
    http_response_code(403);
    echo '<div class="ui-card"><div class="ui-card-body" style="padding:40px;text-align:center"><h2>Access denied</h2><p>Only Super Administrator can manage School Sidebar Permissions.</p></div></div>';
    require dirname(__DIR__) . '/includes/layout-end.php';
    exit;
}

$csrfToken = function_exists('csrfToken') ? csrfToken() : '';
?>
<style>
.sp-page{display:grid;gap:16px}.sp-head{display:flex;justify-content:space-between;align-items:flex-start;gap:14px}.sp-head h1{margin:0;font-size:30px;font-weight:800;color:#101a3b}.sp-head p{margin:5px 0 0;color:#64748b;font-size:13px}.sp-actions{display:flex;gap:9px;flex-wrap:wrap}.sp-btn{height:42px;padding:0 16px;border:1px solid #e2e8f0;border-radius:10px;background:#fff;color:#101a3b;font-size:12px;font-weight:750;display:inline-flex;align-items:center;gap:8px;cursor:pointer}.sp-btn.primary{border-color:#4f46e5;background:#4f46e5;color:#fff}.sp-btn:disabled{opacity:.45;cursor:not-allowed}.sp-card{background:#fff;border:1px solid #e5eaf2;border-radius:14px;box-shadow:0 4px 16px rgba(15,23,42,.035)}.sp-controls{padding:16px;display:grid;grid-template-columns:minmax(220px,.8fr) minmax(220px,.8fr) minmax(260px,1.4fr);gap:12px}.sp-field label{display:block;margin-bottom:6px;font-size:11px;font-weight:750;color:#334155}.sp-field select,.sp-field input{width:100%;height:42px;border:1px solid #dfe5ee;border-radius:9px;padding:0 12px;background:#fff;color:#172554}.sp-toolbar{padding:0 16px 16px;display:flex;gap:8px;flex-wrap:wrap}.sp-toolbar .sp-btn{height:34px;padding:0 11px;font-size:11px}.sp-message{display:none;padding:12px 15px;border-radius:10px;font-size:12px;font-weight:650}.sp-message.show{display:block}.sp-message.ok{background:#ecfdf3;color:#166534}.sp-message.err{background:#fff1f2;color:#be123c}.sp-add{display:none;padding:16px}.sp-add.show{display:block}.sp-add-grid{display:grid;grid-template-columns:1.1fr .9fr 1.2fr 1fr 1fr auto;gap:10px;align-items:end}.sp-list-head{padding:15px 16px;border-bottom:1px solid #edf1f6;display:flex;justify-content:space-between;align-items:center;gap:12px}.sp-list-head h2{margin:0;font-size:15px;font-weight:800}.sp-list-head small{color:#64748b}.sp-list{display:grid}.sp-row{padding:13px 16px;border-bottom:1px solid #edf1f6}.sp-row:last-child{border-bottom:0}.sp-row-main{display:grid;grid-template-columns:minmax(260px,1fr) auto;gap:14px;align-items:center}.sp-menu{display:flex;align-items:center;gap:10px;min-width:0}.sp-dot{width:34px;height:34px;border-radius:10px;background:#f0edff;color:#5b48e7;display:grid;place-items:center;flex:0 0 34px;font-size:12px;font-weight:800}.sp-menu-copy{min-width:0}.sp-menu-copy strong,.sp-menu-copy small{display:block}.sp-menu-copy strong{font-size:12px;color:#101a3b}.sp-menu-copy small{margin-top:3px;color:#64748b;font-size:10px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.sp-state{display:flex;align-items:center;gap:8px}.sp-state label{font-size:11px;font-weight:750}.sp-row-tools{display:flex;align-items:center;gap:6px;margin-left:8px}.sp-mini{height:30px;padding:0 9px;border:1px solid #e2e8f0;border-radius:8px;background:#fff;color:#334155;font-size:10px;font-weight:750;cursor:pointer}.sp-mini.edit:hover{border-color:#c7d2fe;background:#eef2ff;color:#4338ca}.sp-mini.delete:hover{border-color:#fecaca;background:#fff1f2;color:#be123c}.sp-add-title{grid-column:1/-1;font-size:13px;font-weight:800;color:#101a3b;margin-bottom:-2px}.sp-perms{margin-top:10px;margin-left:44px;display:grid;grid-template-columns:repeat(auto-fit,minmax(92px,1fr));gap:7px;max-width:1100px}.sp-perm{position:relative}.sp-perm input{position:absolute;opacity:0;pointer-events:none}.sp-perm span{min-height:32px;padding:6px 10px;justify-content:flex-start;border:1px solid #e2e8f0;border-radius:8px;background:#fff;color:#475569;font-size:10px;font-weight:750;display:flex;align-items:center;gap:6px;cursor:pointer;user-select:none}.sp-perm span:before{content:'';width:13px;height:13px;border:1.5px solid #cbd5e1;border-radius:4px;background:#fff}.sp-perm input:checked+span{border-color:#c7d2fe;background:#eef2ff;color:#4338ca}.sp-perm input:checked+span:before{border-color:#6366f1;background:#6366f1;box-shadow:inset 0 0 0 3px #fff}.sp-toggle{width:42px;height:23px;border-radius:99px;background:#cbd5e1;position:relative;display:inline-block;cursor:pointer;transition:.2s}.sp-toggle:after{content:'';width:17px;height:17px;border-radius:50%;background:#fff;position:absolute;top:3px;left:3px;box-shadow:0 1px 3px rgba(0,0,0,.2);transition:.2s}.sp-visible{position:absolute;opacity:0}.sp-visible:checked+.sp-toggle{background:#22c55e}.sp-visible:checked+.sp-toggle:after{transform:translateX(19px)}.sp-empty{padding:42px 20px;text-align:center;color:#64748b}.sp-loading{padding:42px 20px;text-align:center;color:#64748b}.sp-badge{display:inline-flex;margin-left:8px;padding:2px 6px;border-radius:999px;background:#f1f5f9;color:#64748b;font-size:8px;font-weight:800}.sp-badge.override{background:#fff7ed;color:#c2410c}@media(max-width:1000px){.sp-controls{grid-template-columns:1fr 1fr}.sp-field.search{grid-column:1/-1}.sp-add-grid{grid-template-columns:1fr 1fr}.sp-add-grid .wide{grid-column:1/-1}}@media(max-width:700px){.sp-head{display:block}.sp-actions{margin-top:12px}.sp-controls{grid-template-columns:1fr}.sp-field.search{grid-column:auto}.sp-row-main{grid-template-columns:1fr}.sp-state{margin-left:44px}.sp-perms{margin-left:0}.sp-add-grid{grid-template-columns:1fr}}
/* Common toast: solid/sharp display, no blur. */
#schoolToastContainer .school-toast{
    -webkit-backdrop-filter:none !important;
    backdrop-filter:none !important;
    filter:none !important;
    background:#fff !important;
}
#schoolToastContainer{
    -webkit-filter:none !important;
    filter:none !important;
}
</style>

<div class="sp-page">
    <div class="sp-head">
        <div>
            <h1>School Sidebar Permissions</h1>
            <p>Easy method: choose school → choose role → enable View/Add/Edit/Delete/Print/Export/Import/Approve/Reject/Restore/Manage → Save.</p>
        </div>
        <div class="sp-actions">
            <button type="button" class="sp-btn" id="addToggle">+ Add Sidebar</button>
            <button type="button" class="sp-btn" id="refreshBtn">↻ Refresh</button>
            <button type="button" class="sp-btn primary" id="saveBtn" disabled>Save Changes</button>
        </div>
    </div>

    <div id="msg" class="sp-message"></div>

    <section class="sp-card sp-add" id="addPanel">
        <div class="sp-add-grid">
            <div class="sp-add-title" id="formTitle">Add Sidebar Menu</div>
            <div class="sp-field"><label>Menu Title</label><input id="addTitle" placeholder="Example: Library"></div>
            <div class="sp-field"><label>Menu Key</label><input id="addKey" placeholder="library"></div>
            <div class="sp-field"><label>Route</label><input id="addRoute" placeholder="library.php"></div>
            <div class="sp-field"><label>Icon</label><input id="addIcon" value="circle" placeholder="book-open"></div>
            <div class="sp-field"><label>Parent Menu</label><select id="addParent"><option value="0">Root Menu</option></select></div>
            <div class="sp-field"><label>Order</label><input id="addOrder" type="number" min="0" value="100"></div>
            <button type="button" class="sp-btn primary" id="addSave">Add</button>
            <button type="button" class="sp-btn" id="editCancel" style="display:none">Cancel</button>
        </div>
        <div style="margin-top:10px;font-size:11px;color:#64748b">Default Sidebar is the master structure. When a school is selected, Add creates/reuses the same Default menu and explicitly assigns it only to that school.</div>
    </section>

    <section class="sp-card">
        <div class="sp-controls">
            <div class="sp-field"><label>School</label><select id="schoolSelect"><option value="0">Default Sidebar Options</option></select></div>
            <div class="sp-field"><label>School Role</label><select id="roleSelect" disabled><option value="0">Select a school first</option></select></div>
            <div class="sp-field search"><label>Search Menu</label><input id="searchInput" placeholder="Search title, key or route..."></div>
        </div>
        <div class="sp-toolbar">
            <button type="button" class="sp-btn" data-bulk="show">Show All</button>
            <button type="button" class="sp-btn" data-bulk="hide">Hide All</button>
            <button type="button" class="sp-btn" data-bulk="grant">Grant All</button>
            <button type="button" class="sp-btn" data-bulk="remove">Remove All</button>
            <button type="button" class="sp-btn" id="resetDefaultBtn" disabled>Clear School Sidebar</button>
        </div>
    </section>

    <section class="sp-card">
        <div class="sp-list-head">
            <div><h2 id="listTitle">Default Sidebar Options</h2><small id="listInfo">Loading...</small><small id="actionHint" style="display:block;margin-top:4px;color:#4f46e5;font-weight:700"></small></div>
            <small id="countInfo"></small>
        </div>
        <div id="list" class="sp-list"><div class="sp-loading">Loading sidebar options...</div></div>
    </section>
</div>

<?php
if (
    $renderCommonToastLocally
    && function_exists('school_toast_markup')
) {
    echo school_toast_markup();
}
?>

<script>
(() => {
    const csrf = <?= json_encode($csrfToken, JSON_UNESCAPED_SLASHES) ?>;
    const endpoint = location.pathname;
    const el = id => document.getElementById(id);
    const schoolSelect = el('schoolSelect');
    const roleSelect = el('roleSelect');
    const list = el('list');
    const saveBtn = el('saveBtn');
    const resetDefaultBtn = el('resetDefaultBtn');
    const searchInput = el('searchInput');
    let actions = [];
    let items = [];
    let dirty = false;
    let editItemId = 0;

    const api = async (action, params = {}, method = 'GET') => {
        let url = endpoint + '?ajax=1&action=' + encodeURIComponent(action);
        const opts = {credentials:'same-origin', headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}};
        if (method === 'GET') {
            const q = new URLSearchParams(params);
            if ([...q].length) url += '&' + q.toString();
        } else {
            opts.method = 'POST';
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify({...params, csrf_token: csrf});
        }
        const res = await fetch(url, opts);
        const text = await res.text();
        let data;
        try { data = JSON.parse(text); } catch(e) { throw new Error(text || ('HTTP ' + res.status)); }
        if (!res.ok || !data.success) throw new Error(data.message || 'Request failed');
        return data.data || {};
    };

    const message = (text, ok = false) => {
        const m = el('msg');
        m.textContent = text;
        m.className = 'sp-message show ' + (ok ? 'ok' : 'err');
        clearTimeout(message.timer);
        message.timer = setTimeout(() => m.className = 'sp-message', 4500);

        if (typeof window.schoolToast === 'function') {
            window.schoolToast(
                ok ? 'success' : 'error',
                text,
                ok ? 'Success' : 'Action failed'
            );
        }
    };

    const setDirty = value => {
        dirty = value;
        saveBtn.disabled = !dirty;
    };

    const esc = value => String(value ?? '').replace(/[&<>'"]/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[ch]));

    const actionKey = value => {
        const key = String(value || '').trim().toLowerCase().replace(/[^a-z0-9]+/g,'_').replace(/^_+|_+$/g,'');
        if (key === 'add') return 'create';
        if (key === 'manage' || key === 'settings') return 'manage_settings';
        if (key === 'full') return 'full_access';
        return key;
    };

    const renderParents = () => {
        const current = el('addParent').value;
        el('addParent').innerHTML = '<option value="0">Root Menu</option>' + items
            .filter(i => Number(i.sidebar_item_id) !== Number(editItemId || 0))
            .map(i => `<option value="${Number(i.sidebar_item_id)}">${'— '.repeat(Math.min(Number(i.depth||0),3))}${esc(i.display_title)}</option>`)
            .join('');
        if ([...el('addParent').options].some(o => o.value === current)) el('addParent').value = current;
    };

    const render = () => {
        const term = searchInput.value.trim().toLowerCase();
        const visibleItems = items.filter(i => !term || [i.display_title,i.menu_key,i.display_route].join(' ').toLowerCase().includes(term));
        el('countInfo').textContent = visibleItems.length + ' / ' + items.length + ' menus';
        if (!visibleItems.length) {
            list.innerHTML = '<div class="sp-empty">No sidebar menus found.</div>';
            return;
        }
        const schoolId = Number(schoolSelect.value || 0);
        const roleId = Number(roleSelect.value || 0);
        list.innerHTML = visibleItems.map(item => {
            const depth = Math.min(Number(item.depth || 0), 5);
            const id = Number(item.sidebar_item_id);
            const badge = schoolId > 0 ? `<span class="sp-badge ${Number(item.is_override) ? 'override' : ''}">${Number(item.is_override) ? 'School Override' : 'Default'}</span>` : '';
            let permHtml = '';
            if (schoolId > 0 && roleId > 0) {
                permHtml = `<div class="sp-perms">` + actions.map(a => {
                    const key = actionKey(a.action_key);
                    const checked = Number(item.permissions?.[key] || 0) === 1 ? 'checked' : '';
                    return `<label class="sp-perm"><input type="checkbox" data-item="${id}" data-action="${esc(key)}" ${checked}><span>${esc(a.action_name)}</span></label>`;
                }).join('') + `</div>`;
            }
            return `<div class="sp-row" data-row="${id}">
                <div class="sp-row-main">
                    <div class="sp-menu" style="padding-left:${depth * 18}px">
                        <span class="sp-dot">${esc((item.display_title || '?').trim().charAt(0).toUpperCase())}</span>
                        <span class="sp-menu-copy"><strong>${esc(item.display_title)} ${badge}</strong><small>${esc(item.menu_key)} · ${esc(item.display_route || '#')}</small></span>
                    </div>
                    <div class="sp-state">
                        <label>${schoolId > 0 ? 'Enabled' : 'Default Enabled'}</label>
                        <input class="sp-visible" id="vis_${id}" type="checkbox" data-visible="${id}" ${Number(item.is_visible)===1?'checked':''}>
                        <label class="sp-toggle" for="vis_${id}"></label>
                        <div class="sp-row-tools">
                            <button type="button" class="sp-mini edit" data-edit-menu="${id}">Edit</button>
                            <button type="button" class="sp-mini delete" data-delete-menu="${id}">Delete</button>
                        </div>
                    </div>
                </div>
                ${permHtml}
            </div>`;
        }).join('');
        renderParents();
    };

    const collect = () => items.map(item => {
        const id = Number(item.sidebar_item_id);
        const visible = list.querySelector(`[data-visible="${id}"]`);
        const permissions = {...(item.permissions || {})};
        list.querySelectorAll(`[data-item="${id}"][data-action]`).forEach(cb => permissions[cb.dataset.action] = cb.checked ? 1 : 0);
        return {sidebar_item_id:id, is_visible: visible ? (visible.checked ? 1 : 0) : Number(item.is_visible || 0), permissions};
    });

    const loadMeta = async () => {
        const data = await api('meta');
        actions = data.actions || [];
        schoolSelect.innerHTML = '<option value="0">Default Sidebar Options</option>' + (data.schools || []).map(s => `<option value="${Number(s.id)}">${esc(s.school_name)}${s.tenant_code ? ' ('+esc(s.tenant_code)+')' : ''}</option>`).join('');
    };

    const loadRoles = async () => {
        const schoolId = Number(schoolSelect.value || 0);
        if (!schoolId) {
            roleSelect.disabled = true;
            roleSelect.innerHTML = '<option value="0">Select a school first</option>';
            return;
        }
        const data = await api('roles', {school_id:schoolId});
        roleSelect.disabled = false;
        roleSelect.innerHTML = (data.roles || []).map(r => `<option value="${Number(r.id)}">${esc(r.role_name)} (${esc(r.role_key)})</option>`).join('');
        if (!roleSelect.options.length) roleSelect.innerHTML = '<option value="0">No active school roles</option>';
    };

    const loadItems = async () => {
        list.innerHTML = '<div class="sp-loading">Loading sidebar options...</div>';
        const schoolId = Number(schoolSelect.value || 0);
        const roleId = Number(roleSelect.value || 0);
        const data = await api('load', {school_id:schoolId, role_id:roleId});
        items = data.items || [];
        if (data.actions) actions = data.actions;
        el('listTitle').textContent = schoolId ? 'School Sidebar & Role Actions' : 'Default Sidebar Options';
        el('listInfo').textContent = schoolId
            ? 'Enable modules for this school and tick only the actions allowed for the selected role.'
            : 'This is the fallback sidebar used by schools that do not have their own override.';
        el('actionHint').textContent = schoolId && roleId
            ? 'Actions: ' + actions.map(a => a.action_name).join(' · ')
            : '';
        resetDefaultBtn.disabled = !schoolId;
        setDirty(false);
        render();
    };

    schoolSelect.addEventListener('change', async () => {
        try { await loadRoles(); await loadItems(); } catch(e) { message(e.message); list.innerHTML='<div class="sp-empty">'+esc(e.message)+'</div>'; }
    });
    roleSelect.addEventListener('change', () => loadItems().catch(e => message(e.message)));
    searchInput.addEventListener('input', render);

    list.addEventListener('change', e => {
        const cb = e.target;
        if (!(cb instanceof HTMLInputElement)) return;
        if (cb.matches('[data-visible]')) {
            const id = Number(cb.dataset.visible || 0);
            const item = items.find(i => Number(i.sidebar_item_id) === id);
            if (item) item.is_visible = cb.checked ? 1 : 0;
            setDirty(true);
            return;
        }
        if (!cb.matches('[data-action]')) return;
        const row = cb.closest('.sp-row');
        if (!row) return;
        const action = actionKey(cb.dataset.action);
        const view = row.querySelector('[data-action="view"]');
        const full = row.querySelector('[data-action="full_access"]');
        const all = [...row.querySelectorAll('[data-action]')];
        if (cb.checked && action !== 'view' && action !== 'full_access' && view) view.checked = true;
        if (action === 'view' && !cb.checked) all.forEach(x => x.checked = false);
        if (action === 'full_access') all.forEach(x => x.checked = cb.checked);
        if (action !== 'full_access' && !cb.checked && full) full.checked = false;

        const id = Number(row.dataset.row || 0);
        const item = items.find(i => Number(i.sidebar_item_id) === id);
        if (item) {
            item.permissions = item.permissions || {};
            all.forEach(x => item.permissions[actionKey(x.dataset.action)] = x.checked ? 1 : 0);
        }
        setDirty(true);
    });

    document.querySelectorAll('[data-bulk]').forEach(btn => btn.addEventListener('click', () => {
        const type = btn.dataset.bulk;
        const rows = [...list.querySelectorAll('.sp-row')].filter(r => r.offsetParent !== null);
        rows.forEach(row => {
            const id = Number(row.dataset.row || 0);
            const item = items.find(i => Number(i.sidebar_item_id) === id);
            if (type === 'show' || type === 'hide') {
                const v = row.querySelector('[data-visible]');
                if (v) {
                    v.checked = type === 'show';
                    if (item) item.is_visible = v.checked ? 1 : 0;
                }
            } else {
                const value = type === 'grant';
                row.querySelectorAll('[data-action]').forEach(cb => cb.checked = value);
                if (item) {
                    item.permissions = item.permissions || {};
                    row.querySelectorAll('[data-action]').forEach(cb => {
                        item.permissions[actionKey(cb.dataset.action)] = cb.checked ? 1 : 0;
                    });
                }
            }
        });
        setDirty(true);
    }));

    saveBtn.addEventListener('click', async () => {
        saveBtn.disabled = true;
        try {
            const schoolId = Number(schoolSelect.value || 0);
            const roleId = Number(roleSelect.value || 0);
            if (schoolId && !roleId) throw new Error('Select a School Role first.');
            await api('save', {school_id:schoolId, role_id:roleId, items:collect()}, 'POST');
            message('Saved successfully.', true);
            await loadItems();
        } catch(e) { message(e.message); setDirty(true); }
    });

    resetDefaultBtn.addEventListener('click', async () => {
        const schoolId = Number(schoolSelect.value || 0);
        if (!schoolId) return;
        if (!confirm('Remove all sidebar assignments and role sidebar permissions for this school?')) return;
        try { await api('reset_school', {school_id:schoolId}, 'POST'); message('School sidebar assignments cleared.', true); await loadItems(); }
        catch(e) { message(e.message); }
    });

    const resetMenuForm = () => {
        editItemId = 0;
        el('formTitle').textContent = 'Add Sidebar Menu';
        el('addSave').textContent = 'Add';
        el('editCancel').style.display = 'none';
        el('addKey').disabled = false;
        el('addTitle').value = '';
        el('addKey').value = '';
        el('addKey').dataset.touched = '';
        el('addRoute').value = '';
        el('addIcon').value = 'circle';
        el('addOrder').value = '100';
        renderParents();
        el('addParent').value = '0';
    };

    const openEditForm = item => {
        editItemId = Number(item.sidebar_item_id || 0);
        el('addPanel').classList.add('show');
        el('formTitle').textContent = 'Edit Sidebar Menu';
        el('addSave').textContent = 'Update';
        el('editCancel').style.display = '';
        el('addTitle').value = item.display_title || '';
        el('addKey').value = item.menu_key || '';
        el('addKey').disabled = true;
        el('addRoute').value = item.display_route || '#';
        el('addIcon').value = item.display_icon || 'circle';
        el('addOrder').value = String(Number(item.display_order || 0));
        renderParents();
        el('addParent').value = String(Number(item.parent_id || 0));
        el('addTitle').focus();
        el('addPanel').scrollIntoView({behavior:'smooth',block:'nearest'});
    };

    el('refreshBtn').addEventListener('click', () => loadItems().catch(e => message(e.message)));
    el('addToggle').addEventListener('click', () => {
        if (editItemId) resetMenuForm();
        el('addPanel').classList.toggle('show');
    });
    el('editCancel').addEventListener('click', () => {
        resetMenuForm();
        el('addPanel').classList.remove('show');
    });
    el('addTitle').addEventListener('input', () => {
        if (!editItemId && !el('addKey').dataset.touched) {
            el('addKey').value = el('addTitle').value.toLowerCase().replace(/[^a-z0-9]+/g,'_').replace(/^_+|_+$/g,'');
        }
    });
    el('addKey').addEventListener('input', () => el('addKey').dataset.touched = '1');

    list.addEventListener('click', async e => {
        const editButton = e.target.closest('[data-edit-menu]');
        if (editButton) {
            const id = Number(editButton.dataset.editMenu || 0);
            const item = items.find(i => Number(i.sidebar_item_id) === id);
            if (item) openEditForm(item);
            return;
        }

        const deleteButton = e.target.closest('[data-delete-menu]');
        if (deleteButton) {
            const id = Number(deleteButton.dataset.deleteMenu || 0);
            const item = items.find(i => Number(i.sidebar_item_id) === id);
            if (!item) return;
            const schoolId = Number(schoolSelect.value || 0);
            const label = schoolId
                ? `Remove "${item.display_title}" and its assigned submenus from this school?`
                : `Delete "${item.display_title}" from the Default Sidebar?`;
            if (!confirm(label)) return;
            try {
                await api('delete_menu', {school_id:schoolId,role_id:Number(roleSelect.value||0),sidebar_item_id:id}, 'POST');
                message(schoolId ? 'Sidebar menu and assigned submenus removed for this school.' : 'Default sidebar menu and submenus deleted.', true);
                if (editItemId === id) {
                    resetMenuForm();
                    el('addPanel').classList.remove('show');
                }
                await loadItems();
            } catch (err) {
                message(err.message);
            }
        }
    });

    el('addSave').addEventListener('click', async () => {
        try {
            const title = el('addTitle').value.trim();
            if (!title) throw new Error('Enter Menu Title.');
            const schoolId = Number(schoolSelect.value || 0);
            if (editItemId) {
                const row = list.querySelector(`[data-row="${editItemId}"]`);
                const visible = row?.querySelector('[data-visible]')?.checked ? 1 : 0;
                await api('edit_menu', {
                    school_id:schoolId,
                    role_id:Number(roleSelect.value||0),
                    sidebar_item_id:editItemId,
                    menu_title:title,
                    route:el('addRoute').value.trim() || '#',
                    icon:el('addIcon').value.trim() || 'circle',
                    parent_id:Number(el('addParent').value || 0),
                    display_order:Number(el('addOrder').value || 0),
                    is_visible:visible
                }, 'POST');
                message('Sidebar menu updated.', true);
            } else {
                await api('add', {
                    school_id:schoolId,
                    role_id:Number(roleSelect.value||0),
                    menu_title:title,
                    menu_key:el('addKey').value.trim(),
                    route:el('addRoute').value.trim() || '#',
                    icon:el('addIcon').value.trim() || 'circle',
                    parent_id:Number(el('addParent').value || 0),
                    display_order:Number(el('addOrder').value || 100),
                    is_visible:1
                }, 'POST');
                message('Sidebar menu added.', true);
            }
            resetMenuForm();
            el('addPanel').classList.remove('show');
            await loadItems();
        } catch(e) {
            message(e.message);
        }
    });

    (async () => {
        try { await loadMeta(); await loadRoles(); await loadItems(); }
        catch(e) { message(e.message); list.innerHTML = '<div class="sp-empty">'+esc(e.message)+'</div>'; }
    })();
})();
</script>

<?php require dirname(__DIR__) . '/includes/layout-end.php'; ?>

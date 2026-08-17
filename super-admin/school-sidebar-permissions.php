<?php
declare(strict_types=1);

/* Build: 2026-08-14-school-branch-sidebar-permissions-v26 */

$pageTitle = 'School Sidebar Permissions';
$pageKey = 'school_sidebar_permissions';
$sidebarFile = __DIR__ . '/sidebar.php';

$isAjax = isset($_GET['ajax']) && (string)$_GET['ajax'] === '1';

if ($isAjax) {
    require_once dirname(__DIR__) . '/includes/bootstrap.php';
    require_once dirname(__DIR__) . '/includes/school-sidebar-service.php';
    require_once dirname(__DIR__) . '/includes/permission-chain.php';

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

        if (!sp_table($pdo, 'branch_sidebar_permission_grants')) {
            $pdo->exec("CREATE TABLE branch_sidebar_permission_grants (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                tenant_id bigint unsigned NOT NULL,
                branch_id bigint unsigned NOT NULL,
                role_id bigint unsigned NOT NULL,
                sidebar_item_id bigint unsigned NOT NULL,
                action_key varchar(50) NOT NULL,
                is_allowed tinyint(1) NOT NULL DEFAULT 0,
                created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY(id),
                UNIQUE KEY uq_branch_sidebar_grant(
                    tenant_id,branch_id,role_id,sidebar_item_id,action_key
                ),
                KEY idx_branch_sidebar_grant_scope(tenant_id,branch_id,role_id),
                KEY idx_branch_sidebar_grant_item(sidebar_item_id)
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

    function sp_branches(PDO $pdo, int $schoolId): array
    {
        if ($schoolId <= 0 || !sp_table($pdo, 'branches')) {
            return [];
        }

        $select = ['id', 'branch_name'];
        foreach (['branch_code', 'is_main', 'status'] as $column) {
            if (sp_column($pdo, 'branches', $column)) {
                $select[] = $column;
            }
        }

        $stmt = $pdo->prepare(
            "SELECT " . implode(',', $select) . "
             FROM branches
             WHERE tenant_id=?
             ORDER BY "
             . (sp_column($pdo, 'branches', 'is_main') ? 'is_main DESC,' : '')
             . " branch_name,id"
        );
        $stmt->execute([$schoolId]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['id'] = (int)($row['id'] ?? 0);
            $row['is_main'] = (int)($row['is_main'] ?? 0);
            $row['status'] = strtolower(trim((string)($row['status'] ?? 'active')));
        }
        unset($row);

        return $rows;
    }

    function sp_branch(PDO $pdo, int $schoolId, int $branchId): array
    {
        if ($branchId <= 0) {
            return [];
        }
        if (!sp_table($pdo, 'branches')) {
            sp_json(false, 'Branches table is unavailable.', [], 500);
        }

        $select = ['id', 'branch_name'];
        foreach (['branch_code', 'is_main', 'status'] as $column) {
            if (sp_column($pdo, 'branches', $column)) {
                $select[] = $column;
            }
        }

        $stmt = $pdo->prepare(
            "SELECT " . implode(',', $select) . "
             FROM branches
             WHERE id=? AND tenant_id=?
             LIMIT 1"
        );
        $stmt->execute([$branchId, $schoolId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            sp_json(
                false,
                'Selected branch does not belong to the selected school.',
                [],
                422
            );
        }

        $row['id'] = (int)$row['id'];
        $row['is_main'] = (int)($row['is_main'] ?? 0);
        $row['status'] = strtolower(trim((string)($row['status'] ?? 'active')));

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


    function sp_master_role_key(string $roleKey): string
    {
        return school_sidebar_service_role_key($roleKey);
    }

    function sp_role_catalog(PDO $pdo): array
    {
        if (!sp_table($pdo, 'roles')) return [];

        $rows = $pdo->query(
            "SELECT role_key, MAX(role_name) AS role_name
             FROM roles
             WHERE role_scope='school'
               AND status='active'
               AND deleted_at IS NULL
             GROUP BY role_key
             ORDER BY
                CASE
                    WHEN role_key IN ('school_admin','school_administrator','school-administrator') THEN 0
                    WHEN role_key='parent' THEN 1
                    ELSE 2
                END,
                role_name,
                role_key"
        )->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $row) {
            $key = sp_master_role_key((string)($row['role_key'] ?? ''));
            if ($key === '' || $key === 'super_admin') continue;
            if (isset($result[$key])) continue;

            $name = trim((string)($row['role_name'] ?? ''));
            if ($key === 'school_admin') $name = 'School Admin';
            if ($key === 'parent') $name = 'Parent';

            $result[$key] = [
                'role_key' => $key,
                'role_name' => $name !== ''
                    ? $name
                    : ucwords(str_replace('_', ' ', $key)),
            ];
        }

        return array_values($result);
    }

    function sp_role_for_key(
        PDO $pdo,
        int $schoolId,
        string $masterRoleKey
    ): array {
        $masterRoleKey = sp_master_role_key($masterRoleKey);
        if ($schoolId <= 0 || $masterRoleKey === '') return [];

        $stmt = $pdo->prepare(
            "SELECT id,role_key,role_name
             FROM roles
             WHERE tenant_id=:tenant_id
               AND status='active'
               AND deleted_at IS NULL
             ORDER BY id"
        );
        $stmt->execute(['tenant_id' => $schoolId]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (sp_master_role_key((string)$row['role_key']) === $masterRoleKey) {
                return $row;
            }
        }

        sp_json(
            false,
            'The selected role is not available for this school.',
            [],
            422
        );
    }

    function sp_is_school_admin_key(string $roleKey): bool
    {
        return sp_master_role_key($roleKey) === 'school_admin';
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
            $schools = $pdo->query(
                "SELECT id,tenant_code,school_name
                 FROM tenants
                 WHERE status IN ('active','trial')
                 ORDER BY school_name,id"
            )->fetchAll(PDO::FETCH_ASSOC);

            $roleCatalog = sp_role_catalog($pdo);
            foreach ($roleCatalog as $catalogRole) {
                school_sidebar_service_ensure_role_master(
                    $pdo,
                    (string)$catalogRole['role_key'],
                    (int)($_SESSION['user_id'] ?? 0) ?: null
                );
            }

            sp_json(true, 'Loaded.', [
                'schools' => $schools,
                'roles' => $roleCatalog,
                'actions' => sp_action_catalog($pdo),
                'csrf_token' => function_exists('csrfToken')
                    ? csrfToken()
                    : '',
            ]);
        }

        if ($action === 'roles') {
            $schoolId = (int)($_GET['school_id'] ?? 0);
            sp_school($pdo, $schoolId);

            $stmt = $pdo->prepare(
                "SELECT id,role_key,role_name
                 FROM roles
                 WHERE tenant_id=?
                   AND status='active'
                   AND deleted_at IS NULL
                 ORDER BY
                    CASE
                        WHEN role_key IN (
                            'school_admin',
                            'school_administrator',
                            'school-administrator'
                        ) THEN 0
                        WHEN role_key='parent' THEN 1
                        ELSE 2
                    END,
                    role_name,id"
            );
            $stmt->execute([$schoolId]);

            $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($roles as &$roleRow) {
                $roleRow['master_role_key'] = sp_master_role_key(
                    (string)$roleRow['role_key']
                );
            }
            unset($roleRow);

            sp_json(
                true,
                'Roles and branches loaded.',
                [
                    'roles' => $roles,
                    'branches' => sp_branches($pdo, $schoolId),
                ]
            );
        }

        if ($action === 'load') {
            $schoolId = (int)($_GET['school_id'] ?? 0);
            $roleId = (int)($_GET['role_id'] ?? 0);
            $branchId = (int)($_GET['branch_id'] ?? 0);
            $branch = [];
            $masterRoleKey = sp_master_role_key(
                (string)($_GET['master_role_key'] ?? 'school_admin')
            );
            if ($masterRoleKey === '') {
                $masterRoleKey = 'school_admin';
            }

            school_sidebar_service_ensure_role_master(
                $pdo,
                $masterRoleKey,
                (int)($_SESSION['user_id'] ?? 0) ?: null
            );

            $role = [];
            if ($schoolId > 0) {
                sp_school($pdo, $schoolId);
                if ($branchId > 0) {
                    $branch = sp_branch($pdo, $schoolId, $branchId);
                }
                $role = $roleId > 0
                    ? sp_role($pdo, $schoolId, $roleId)
                    : sp_role_for_key(
                        $pdo,
                        $schoolId,
                        $masterRoleKey
                    );
                $roleId = (int)$role['id'];

                if (sp_master_role_key(
                    (string)$role['role_key']
                ) !== $masterRoleKey) {
                    sp_json(
                        false,
                        'Selected school role does not match the Sidebar Master role.',
                        [],
                        422
                    );
                }
            }

            $isSchoolAdmin =
                $masterRoleKey === 'school_admin';
            $isParent =
                $masterRoleKey === 'parent';

            /*
             * GLOBAL ROLE SIDEBAR MASTER
             * --------------------------
             * School Admin keeps using sidebar_default_settings for its
             * existing global Enabled/Disabled behavior. The role-master row
             * acts only as catalogue membership.
             *
             * Parent/other roles use sidebar_role_master_items directly and
             * therefore never inherit School Admin master entries.
             */
            if ($schoolId <= 0) {
                if ($isSchoolAdmin) {
                    $stmt = $pdo->prepare(
                        "SELECT
                            si.id AS sidebar_item_id,
                            si.parent_id,
                            si.menu_key,
                            si.menu_title AS display_title,
                            si.route AS display_route,
                            si.icon AS display_icon,
                            COALESCE(
                                sds.display_order,
                                rm.display_order,
                                si.display_order
                            ) AS display_order,
                            COALESCE(sds.is_enabled,1) AS is_visible,
                            0 AS is_override,
                            COALESCE(sds.is_enabled,1) AS default_enabled,
                            0 AS is_school_owned
                         FROM sidebar_role_master_items rm
                         INNER JOIN sidebar_items si
                            ON si.id=rm.sidebar_item_id
                         LEFT JOIN sidebar_default_settings sds
                            ON sds.sidebar_item_id=si.id
                         WHERE rm.role_key=:role_key
                           AND rm.is_enabled=1
                           AND si.is_active=1
                           AND si.show_in_sidebar=1
                           AND si.portal_scope IN ('school','all')
                           AND si.owner_tenant_id IS NULL
                         ORDER BY
                            COALESCE(
                                sds.display_order,
                                rm.display_order,
                                si.display_order
                            ),
                            si.id"
                    );
                } else {
                    $stmt = $pdo->prepare(
                        "SELECT
                            si.id AS sidebar_item_id,
                            si.parent_id,
                            si.menu_key,
                            si.menu_title AS display_title,
                            si.route AS display_route,
                            si.icon AS display_icon,
                            COALESCE(
                                rm.display_order,
                                si.display_order
                            ) AS display_order,
                            COALESCE(rm.is_enabled,0) AS is_visible,
                            0 AS is_override,
                            COALESCE(rm.is_enabled,0) AS default_enabled,
                            0 AS is_school_owned
                         FROM sidebar_role_master_items rm
                         INNER JOIN sidebar_items si
                            ON si.id=rm.sidebar_item_id
                         WHERE rm.role_key=:role_key
                           AND si.is_active=1
                           AND si.show_in_sidebar=1
                           AND si.portal_scope IN ('school','all')
                           AND si.owner_tenant_id IS NULL
                         ORDER BY
                            COALESCE(
                                rm.display_order,
                                si.display_order
                            ),
                            si.id"
                    );
                }

                $stmt->execute([
                    'role_key' => $masterRoleKey,
                ]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } elseif (!$isSchoolAdmin) {
                /*
                 * ROLE + SCHOOL VISIBILITY
                 * ------------------------
                 * For Parent/other roles there is deliberately NO
                 * tenant_sidebar_items dependency. School visibility comes
                 * from this school's role-specific View permission.
                 */
                $stmt = $pdo->prepare(
                    "SELECT
                        si.id AS sidebar_item_id,
                        si.parent_id,
                        si.menu_key,
                        si.menu_title AS display_title,
                        si.route AS display_route,
                        si.icon AS display_icon,
                        COALESCE(
                            rm.display_order,
                            si.display_order
                        ) AS display_order,
                        CASE
                            WHEN spg.sidebar_item_id IS NOT NULL
                                THEN COALESCE(spg.is_allowed,0)
                            WHEN sap.sidebar_item_id IS NOT NULL
                                THEN COALESCE(sap.can_view,0)
                            ELSE 0
                        END AS is_visible,
                        1 AS is_override,
                        COALESCE(rm.is_enabled,0) AS default_enabled,
                        0 AS is_school_owned
                     FROM sidebar_role_master_items rm
                     INNER JOIN sidebar_items si
                        ON si.id=rm.sidebar_item_id
                     LEFT JOIN school_sidebar_permission_grants spg
                        ON spg.tenant_id=:grant_tenant_id
                       AND spg.role_id=:grant_role_id
                       AND spg.sidebar_item_id=si.id
                       AND spg.action_key='view'
                     LEFT JOIN school_sidebar_action_permissions sap
                        ON sap.tenant_id=:action_tenant_id
                       AND sap.role_id=:action_role_id
                       AND sap.sidebar_item_id=si.id
                     WHERE rm.role_key=:role_key
                       AND rm.is_enabled=1
                       AND si.is_active=1
                       AND si.show_in_sidebar=1
                       AND si.portal_scope IN ('school','all')
                       AND si.owner_tenant_id IS NULL
                     ORDER BY
                        COALESCE(
                            rm.display_order,
                            si.display_order
                        ),
                        si.id"
                );
                $stmt->execute([
                    'grant_tenant_id' => $schoolId,
                    'grant_role_id' => $roleId,
                    'action_tenant_id' => $schoolId,
                    'action_role_id' => $roleId,
                    'role_key' => $masterRoleKey,
                ]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                /*
                 * EXISTING SCHOOL ADMIN BEHAVIOR
                 * ------------------------------
                 * Keep default inheritance + explicit tenant override exactly
                 * as before, but filter shared definitions through the
                 * School Admin master so Parent-only definitions never leak.
                 */
                $hasInherit = sp_column(
                    $pdo,
                    'tenant_sidebar_items',
                    'inherit_default'
                );
                $explicitExpr = $hasInherit
                    ? '(tsi.id IS NOT NULL AND COALESCE(tsi.inherit_default,0)=0)'
                    : '(tsi.id IS NOT NULL)';

                $sql = "SELECT
                    si.id AS sidebar_item_id,
                    CASE
                        WHEN {$explicitExpr}
                            THEN COALESCE(tsi.custom_parent_id,si.parent_id)
                        ELSE si.parent_id
                    END AS parent_id,
                    si.menu_key,
                    CASE
                        WHEN {$explicitExpr}
                            THEN COALESCE(NULLIF(tsi.custom_title,''),si.menu_title)
                        ELSE si.menu_title
                    END AS display_title,
                    CASE
                        WHEN {$explicitExpr}
                            THEN COALESCE(NULLIF(tsi.custom_route,''),si.route)
                        ELSE si.route
                    END AS display_route,
                    CASE
                        WHEN {$explicitExpr}
                            THEN COALESCE(NULLIF(tsi.custom_icon,''),si.icon)
                        ELSE si.icon
                    END AS display_icon,
                    CASE
                        WHEN {$explicitExpr}
                            THEN COALESCE(
                                tsi.display_order,
                                sds.display_order,
                                rm.display_order,
                                si.display_order
                            )
                        ELSE COALESCE(
                            sds.display_order,
                            rm.display_order,
                            si.display_order
                        )
                    END AS display_order,
                    CASE
                        WHEN {$explicitExpr}
                            THEN COALESCE(tsi.is_visible,0)
                        WHEN si.owner_tenant_id=:owner_visibility_id
                            THEN 0
                        ELSE COALESCE(sds.is_enabled,1)
                    END AS is_visible,
                    CASE
                        WHEN {$explicitExpr} THEN 1
                        ELSE 0
                    END AS is_override,
                    CASE
                        WHEN si.owner_tenant_id=:owner_default_id
                            THEN 0
                        ELSE COALESCE(sds.is_enabled,1)
                    END AS default_enabled,
                    CASE
                        WHEN si.owner_tenant_id=:owner_school_id
                            THEN 1
                        ELSE 0
                    END AS is_school_owned
                 FROM sidebar_items si
                 LEFT JOIN sidebar_role_master_items rm
                    ON rm.sidebar_item_id=si.id
                   AND rm.role_key='school_admin'
                 LEFT JOIN sidebar_default_settings sds
                    ON sds.sidebar_item_id=si.id
                 LEFT JOIN tenant_sidebar_items tsi
                    ON tsi.sidebar_item_id=si.id
                   AND tsi.tenant_id=:tenant_id
                 WHERE si.is_active=1
                   AND si.show_in_sidebar=1
                   AND si.portal_scope IN ('school','all')
                   AND (
                        (
                            si.owner_tenant_id IS NULL
                            AND rm.sidebar_item_id IS NOT NULL
                            AND rm.is_enabled=1
                        )
                        OR (
                            si.owner_tenant_id=:owner_filter_id
                            AND tsi.id IS NOT NULL
                        )
                   )
                 ORDER BY display_order,si.id";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'tenant_id' => $schoolId,
                    'owner_visibility_id' => $schoolId,
                    'owner_default_id' => $schoolId,
                    'owner_school_id' => $schoolId,
                    'owner_filter_id' => $schoolId,
                ]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            $rows = sp_tree($rows);

            $grants = [];
            if ($schoolId > 0 && $roleId > 0) {
                $stmt = $pdo->prepare(
                    "SELECT sidebar_item_id,action_key,is_allowed
                     FROM school_sidebar_permission_grants
                     WHERE tenant_id=?
                       AND role_id=?"
                );
                $stmt->execute([$schoolId, $roleId]);

                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $grantRow) {
                    $key = sp_action_key(
                        (string)($grantRow['action_key'] ?? '')
                    );
                    if ($key === '') continue;

                    $itemId = (int)$grantRow['sidebar_item_id'];
                    $grants[$itemId][$key] = max(
                        (int)($grants[$itemId][$key] ?? 0),
                        (int)$grantRow['is_allowed']
                    );
                }
            }

            if ($schoolId > 0
                && $roleId > 0
                && sp_table(
                    $pdo,
                    'school_sidebar_action_permissions'
                )) {
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
                    if (sp_column(
                        $pdo,
                        'school_sidebar_action_permissions',
                        $columnName
                    )) {
                        $available[$actionKey] = $columnName;
                    }
                }

                if ($available !== []) {
                    $selects = ['sidebar_item_id'];
                    foreach ($available as $columnName) {
                        $selects[] = $columnName;
                    }
                    $stmt = $pdo->prepare(
                        "SELECT "
                        . implode(
                            ',',
                            array_values(
                                array_unique($selects)
                            )
                        )
                        . "
                         FROM school_sidebar_action_permissions
                         WHERE tenant_id=?
                           AND role_id=?"
                    );
                    $stmt->execute([$schoolId, $roleId]);

                    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $matrixRow) {
                        $itemId = (int)(
                            $matrixRow['sidebar_item_id'] ?? 0
                        );
                        if ($itemId <= 0) continue;

                        foreach ($available as $actionKey => $columnName) {
                            $grants[$itemId][$actionKey] =
                                (int)($matrixRow[$columnName] ?? 0);
                        }
                    }
                }
            }

            $branchGrants = [];
            if ($schoolId > 0
                && $branchId > 0
                && $roleId > 0
                && sp_table($pdo, 'branch_sidebar_permission_grants')) {
                $stmt = $pdo->prepare(
                    "SELECT sidebar_item_id,action_key,is_allowed
                     FROM branch_sidebar_permission_grants
                     WHERE tenant_id=?
                       AND branch_id=?
                       AND role_id=?"
                );
                $stmt->execute([$schoolId, $branchId, $roleId]);

                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $branchGrantRow) {
                    $key = sp_action_key(
                        (string)($branchGrantRow['action_key'] ?? '')
                    );
                    if ($key === '') continue;

                    $itemId = (int)($branchGrantRow['sidebar_item_id'] ?? 0);
                    if ($itemId <= 0) continue;

                    $branchGrants[$itemId][$key] =
                        (int)($branchGrantRow['is_allowed'] ?? 0);
                }
            }

            $actions = sp_action_catalog($pdo);
            foreach ($rows as &$row) {
                $itemId = (int)$row['sidebar_item_id'];
                $row['permissions'] = [];

                foreach ($actions as $actionRow) {
                    $key = (string)$actionRow['action_key'];
                    $schoolAllowed =
                        array_key_exists(
                            $key,
                            $grants[$itemId] ?? []
                        )
                            ? (int)$grants[$itemId][$key]
                            : ($isSchoolAdmin ? 1 : 0);

                    if ($branchId > 0
                        && array_key_exists(
                            $key,
                            $branchGrants[$itemId] ?? []
                        )) {
                        /*
                         * Branch permissions are a child of the school-level
                         * permission. A branch can restrict an allowed school
                         * action but can never grant an action denied at school
                         * level.
                         */
                        $schoolAllowed =
                            $schoolAllowed === 1
                            && (int)$branchGrants[$itemId][$key] === 1
                                ? 1
                                : 0;
                    }

                    $row['permissions'][$key] = $schoolAllowed;
                }

                if ($schoolId > 0) {
                    $schoolVisible = (int)$row['is_visible'];

                    if ($branchId > 0
                        && array_key_exists(
                            'view',
                            $branchGrants[$itemId] ?? []
                        )) {
                        $schoolVisible =
                            $schoolVisible === 1
                            && (int)$branchGrants[$itemId]['view'] === 1
                                ? 1
                                : 0;
                    }

                    if (!$isSchoolAdmin || $branchId > 0) {
                        /*
                         * Role-specific and branch-specific visibility is the
                         * same decision as View.
                         */
                        $row['is_visible'] = $schoolVisible;
                        $row['permissions']['view'] = $schoolVisible;
                    }
                }

                $row['is_branch_override'] =
                    $branchId > 0
                    && isset($branchGrants[$itemId])
                        ? 1
                        : 0;
            }
            unset($row);

            sp_json(true, 'Sidebar loaded.', [
                'items' => $rows,
                'actions' => $actions,
                'role' => $role,
                'branch' => $branch,
                'branch_id' => $branchId,
                'master_role_key' => $masterRoleKey,
                'is_parent_role' => $isParent,
                'is_school_admin_role' => $isSchoolAdmin,
            ]);
        }

        if ($action === 'save') {
            sp_csrf($input);

            $schoolId = (int)($input['school_id'] ?? 0);
            $roleId = (int)($input['role_id'] ?? 0);
            $branchId = (int)($input['branch_id'] ?? 0);
            $branch = [];
            $masterRoleKey = sp_master_role_key(
                (string)($input['master_role_key'] ?? 'school_admin')
            );
            if ($masterRoleKey === '') {
                $masterRoleKey = 'school_admin';
            }

            $items = $input['items'] ?? [];
            if (!is_array($items)) {
                sp_json(
                    false,
                    'Invalid sidebar data.',
                    [],
                    422
                );
            }

            $userId =
                (int)($_SESSION['user_id'] ?? 0) ?: null;
            $isSchoolAdmin =
                $masterRoleKey === 'school_admin';
            $isParent =
                $masterRoleKey === 'parent';

            school_sidebar_service_ensure_role_master(
                $pdo,
                $masterRoleKey,
                $userId
            );

            if ($schoolId <= 0) {
                $pdo->beginTransaction();

                if ($isSchoolAdmin) {
                    /*
                     * Preserve existing School Admin Default Sidebar save.
                     */
                    $stmt = $pdo->prepare(
                        "INSERT INTO sidebar_default_settings
                            (
                                sidebar_item_id,
                                is_enabled,
                                display_order,
                                updated_by
                            )
                         VALUES
                            (
                                :item_id,
                                :enabled,
                                :display_order,
                                :user_id
                            )
                         ON DUPLICATE KEY UPDATE
                            is_enabled=VALUES(is_enabled),
                            display_order=VALUES(display_order),
                            updated_by=VALUES(updated_by),
                            updated_at=CURRENT_TIMESTAMP"
                    );

                    foreach ($items as $item) {
                        if (!is_array($item)) continue;
                        $itemId =
                            (int)($item['sidebar_item_id'] ?? 0);
                        if ($itemId <= 0) continue;

                        $stmt->execute([
                            'item_id' => $itemId,
                            'enabled' =>
                                !empty($item['is_visible'])
                                    ? 1
                                    : 0,
                            'display_order' => max(
                                0,
                                (int)(
                                    $item['display_order']
                                    ?? 0
                                )
                            ),
                            'user_id' => $userId,
                        ]);
                    }
                } else {
                    foreach ($items as $item) {
                        if (!is_array($item)) continue;
                        $itemId =
                            (int)($item['sidebar_item_id'] ?? 0);
                        if ($itemId <= 0) continue;

                        school_sidebar_service_role_master_upsert(
                            $pdo,
                            $masterRoleKey,
                            $itemId,
                            !empty($item['is_visible']),
                            max(
                                0,
                                (int)(
                                    $item['display_order']
                                    ?? 0
                                )
                            ),
                            $userId
                        );
                    }
                }

                school_sidebar_service_bump_version(
                    $pdo,
                    null
                );
                $pdo->commit();

                sp_json(
                    true,
                    $isSchoolAdmin
                        ? 'School Admin Sidebar Master saved successfully.'
                        : ucwords(
                            str_replace(
                                '_',
                                ' ',
                                $masterRoleKey
                            )
                        )
                            . ' Sidebar Master saved successfully.'
                );
            }

            sp_school($pdo, $schoolId);
            if ($branchId > 0) {
                $branch = sp_branch($pdo, $schoolId, $branchId);
            }

            $role = $roleId > 0
                ? sp_role($pdo, $schoolId, $roleId)
                : sp_role_for_key(
                    $pdo,
                    $schoolId,
                    $masterRoleKey
                );
            $roleId = (int)$role['id'];

            if (sp_master_role_key(
                (string)$role['role_key']
            ) !== $masterRoleKey) {
                sp_json(
                    false,
                    'Selected role does not match this Sidebar Master.',
                    [],
                    422
                );
            }

            if ($branchId > 0) {
                if (!sp_table($pdo, 'branch_sidebar_permission_grants')) {
                    sp_json(
                        false,
                        'Branch permission storage is unavailable.',
                        [],
                        500
                    );
                }

                $branchGrant = $pdo->prepare(
                    "INSERT INTO branch_sidebar_permission_grants
                        (
                            tenant_id,
                            branch_id,
                            role_id,
                            sidebar_item_id,
                            action_key,
                            is_allowed
                        )
                     VALUES
                        (
                            :tenant_id,
                            :branch_id,
                            :role_id,
                            :item_id,
                            :action_key,
                            :allowed
                        )
                     ON DUPLICATE KEY UPDATE
                        is_allowed=VALUES(is_allowed),
                        updated_at=CURRENT_TIMESTAMP"
                );

                $pdo->beginTransaction();

                /*
                 * Saving a branch is a complete branch snapshot. Removing old
                 * rows first also removes stale actions/items. School-wide
                 * permissions and sidebar structure are never modified here.
                 */
                $deleteBranch = $pdo->prepare(
                    "DELETE FROM branch_sidebar_permission_grants
                     WHERE tenant_id=?
                       AND branch_id=?
                       AND role_id=?"
                );
                $deleteBranch->execute([
                    $schoolId,
                    $branchId,
                    $roleId,
                ]);

                $catalog = sp_action_catalog($pdo);

                foreach ($items as $item) {
                    if (!is_array($item)) continue;

                    $itemId =
                        (int)($item['sidebar_item_id'] ?? 0);
                    if ($itemId <= 0) continue;

                    if ($isSchoolAdmin) {
                        /*
                         * School Admin branch mode also supports sidebar items
                         * created specifically for this school. Shared items
                         * must belong to the School Admin role master.
                         */
                        $masterCheck = $pdo->prepare(
                            "SELECT COUNT(*)
                             FROM sidebar_items si
                             LEFT JOIN sidebar_role_master_items rm
                               ON rm.sidebar_item_id=si.id
                              AND rm.role_key='school_admin'
                              AND rm.is_enabled=1
                             WHERE si.id=?
                               AND si.is_active=1
                               AND (
                                    rm.sidebar_item_id IS NOT NULL
                                    OR si.owner_tenant_id=?
                               )"
                        );
                        $masterCheck->execute([
                            $itemId,
                            $schoolId,
                        ]);
                    } else {
                        $masterCheck = $pdo->prepare(
                            "SELECT COUNT(*)
                             FROM sidebar_role_master_items
                             WHERE role_key=?
                               AND sidebar_item_id=?
                               AND is_enabled=1"
                        );
                        $masterCheck->execute([
                            $masterRoleKey,
                            $itemId,
                        ]);
                    }

                    if ((int)$masterCheck->fetchColumn() !== 1) {
                        continue;
                    }

                    $raw = is_array(
                        $item['permissions'] ?? null
                    )
                        ? $item['permissions']
                        : [];

                    $permissions = [];
                    foreach ($raw as $actionKey => $allowed) {
                        $actionKey = sp_action_key((string)$actionKey);
                        if ($actionKey === ''
                            || !preg_match(
                                '/^[a-z0-9_]{1,50}$/',
                                $actionKey
                            )) {
                            continue;
                        }
                        $permissions[$actionKey] =
                            !empty($allowed) ? 1 : 0;
                    }

                    /*
                     * In branch mode the menu switch is the View permission.
                     * This makes branch sidebar visibility and direct-page
                     * authorization use the same decision.
                     */
                    $permissions['view'] =
                        !empty($item['is_visible']) ? 1 : 0;

                    foreach ($catalog as $actionRow) {
                        $actionKey =
                            (string)$actionRow['action_key'];

                        $branchGrant->execute([
                            'tenant_id' => $schoolId,
                            'branch_id' => $branchId,
                            'role_id' => $roleId,
                            'item_id' => $itemId,
                            'action_key' => $actionKey,
                            'allowed' =>
                                (int)($permissions[$actionKey] ?? 0),
                        ]);
                    }
                }

                school_sidebar_service_bump_version(
                    $pdo,
                    $schoolId
                );
                $pdo->commit();

                $branchName = trim(
                    (string)($branch['branch_name'] ?? 'Branch')
                );

                sp_json(
                    true,
                    $branchName
                        . ' permissions saved successfully.'
                );
            }

            $grant = $pdo->prepare(
                "INSERT INTO school_sidebar_permission_grants
                    (
                        tenant_id,
                        role_id,
                        sidebar_item_id,
                        action_key,
                        is_allowed
                    )
                 VALUES
                    (
                        :tenant_id,
                        :role_id,
                        :item_id,
                        :action_key,
                        :allowed
                    )
                 ON DUPLICATE KEY UPDATE
                    is_allowed=VALUES(is_allowed),
                    updated_at=CURRENT_TIMESTAMP"
            );

            $legacy = $pdo->prepare(
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
                    can_view=VALUES(can_view),
                    can_add=VALUES(can_add),
                    can_edit=VALUES(can_edit),
                    can_delete=VALUES(can_delete),
                    can_print=VALUES(can_print),
                    can_pdf=VALUES(can_pdf),
                    can_export=VALUES(can_export),
                    can_import=VALUES(can_import),
                    can_approve=VALUES(can_approve),
                    can_reject=VALUES(can_reject),
                    can_restore=VALUES(can_restore),
                    can_manage=VALUES(can_manage),
                    can_manage_visibility=VALUES(can_manage_visibility),
                    updated_at=CURRENT_TIMESTAMP"
            );

            $pdo->beginTransaction();

            if (!$isSchoolAdmin) {
                /*
                 * Parent/other role visibility is role-specific. No
                 * tenant_sidebar_items rows are touched here.
                 */
                foreach ($items as $item) {
                    if (!is_array($item)) continue;
                    $itemId =
                        (int)($item['sidebar_item_id'] ?? 0);
                    if ($itemId <= 0) continue;

                    $masterCheck = $pdo->prepare(
                        "SELECT COUNT(*)
                         FROM sidebar_role_master_items
                         WHERE role_key=?
                           AND sidebar_item_id=?
                           AND is_enabled=1"
                    );
                    $masterCheck->execute([
                        $masterRoleKey,
                        $itemId,
                    ]);
                    if ((int)$masterCheck->fetchColumn() !== 1) {
                        continue;
                    }

                    $visible =
                        !empty($item['is_visible']) ? 1 : 0;

                    $raw = is_array(
                        $item['permissions'] ?? null
                    )
                        ? $item['permissions']
                        : [];

                    $permissions = [];
                    foreach ($raw as $actionKey => $allowed) {
                        $actionKey = sp_action_key((string)$actionKey);
                        if ($actionKey === ''
                            || !preg_match('/^[a-z0-9_]{1,50}$/', $actionKey)) {
                            continue;
                        }
                        $permissions[$actionKey] = !empty($allowed) ? 1 : 0;
                    }
                    $permissions['view'] = $visible;

                    foreach (sp_action_catalog($pdo) as $actionRow) {
                        $actionKey =
                            (string)$actionRow['action_key'];
                        $allowed = $actionKey === 'view'
                            ? $visible
                            : (int)($permissions[$actionKey] ?? 0);

                        $grant->execute([
                            'tenant_id' => $schoolId,
                            'role_id' => $roleId,
                            'item_id' => $itemId,
                            'action_key' => $actionKey,
                            'allowed' => $allowed,
                        ]);
                    }

                    $legacy->execute([
                        'tenant_id' => $schoolId,
                        'role_id' => $roleId,
                        'item_id' => $itemId,
                        'can_view' => $visible,
                        'can_add' => (!empty($permissions['create']) || !empty($permissions['add'])) ? 1 : 0,
                        'can_edit' => !empty($permissions['edit']) ? 1 : 0,
                        'can_delete' => !empty($permissions['delete']) ? 1 : 0,
                        'can_print' => !empty($permissions['print']) ? 1 : 0,
                        'can_pdf' => !empty($permissions['pdf']) ? 1 : 0,
                        'can_export' => !empty($permissions['export']) ? 1 : 0,
                        'can_import' => !empty($permissions['import']) ? 1 : 0,
                        'can_approve' => !empty($permissions['approve']) ? 1 : 0,
                        'can_reject' => !empty($permissions['reject']) ? 1 : 0,
                        'can_restore' => !empty($permissions['restore']) ? 1 : 0,
                        'can_manage' => (!empty($permissions['manage_settings']) || !empty($permissions['manage'])) ? 1 : 0,
                        'can_manage_visibility' => !empty($permissions['manage_visibility']) ? 1 : 0,
                    ]);
                }

                school_sidebar_service_bump_version(
                    $pdo,
                    $schoolId
                );
                $pdo->commit();

                sp_json(
                    true,
                    $isParent
                        ? 'Parent Sidebar options saved for this school.'
                        : 'Role Sidebar options saved for this school.'
                );
            }

            /*
             * SCHOOL ADMIN ORIGINAL SAVE FLOW
             * --------------------------------
             * Keep tenant default inheritance/overrides unchanged.
             */
            $stateStmt = $pdo->prepare(
                "SELECT
                    si.id,
                    COALESCE(si.owner_tenant_id,0) AS owner_tenant_id,
                    COALESCE(sds.is_enabled,1) AS default_enabled,
                    tsi.id AS override_id
                 FROM sidebar_items si
                 LEFT JOIN sidebar_default_settings sds
                    ON sds.sidebar_item_id=si.id
                 LEFT JOIN tenant_sidebar_items tsi
                    ON tsi.sidebar_item_id=si.id
                   AND tsi.tenant_id=:tenant_id
                 WHERE si.id=:item_id
                   AND si.is_active=1
                   AND si.show_in_sidebar=1
                   AND si.portal_scope IN ('school','all')
                   AND (
                        si.owner_tenant_id IS NULL
                        OR si.owner_tenant_id=:owner_tenant_id
                   )
                 LIMIT 1"
            );

            foreach ($items as $item) {
                if (!is_array($item)) continue;
                $itemId =
                    (int)($item['sidebar_item_id'] ?? 0);
                if ($itemId <= 0) continue;

                $stateStmt->execute([
                    'tenant_id' => $schoolId,
                    'item_id' => $itemId,
                    'owner_tenant_id' => $schoolId,
                ]);
                $state = $stateStmt->fetch(PDO::FETCH_ASSOC);
                if (!is_array($state)) continue;

                $visible =
                    !empty($item['is_visible']) ? 1 : 0;
                $defaultEnabled =
                    (int)($state['default_enabled'] ?? 1);
                $ownerTenantId =
                    (int)($state['owner_tenant_id'] ?? 0);
                $hasOverride =
                    (int)($state['override_id'] ?? 0) > 0;
                $schoolOwned =
                    $ownerTenantId === $schoolId
                    && $ownerTenantId > 0;

                if (
                    $hasOverride
                    || $schoolOwned
                    || $visible !== $defaultEnabled
                ) {
                    school_sidebar_service_set_school_visibility(
                        $pdo,
                        $schoolId,
                        $itemId,
                        $visible === 1
                    );
                }

                $raw = is_array(
                    $item['permissions'] ?? null
                )
                    ? $item['permissions']
                    : [];
                $permissions = [];

                foreach ($raw as $actionKey => $allowed) {
                    $actionKey = sp_action_key(
                        (string)$actionKey
                    );
                    if ($actionKey === ''
                        || !preg_match(
                            '/^[a-z0-9_]{1,50}$/',
                            $actionKey
                        )) {
                        continue;
                    }

                    $permissions[$actionKey] =
                        !empty($allowed) ? 1 : 0;

                    $grant->execute([
                        'tenant_id' => $schoolId,
                        'role_id' => $roleId,
                        'item_id' => $itemId,
                        'action_key' => $actionKey,
                        'allowed' => $permissions[$actionKey],
                    ]);
                }

                $legacy->execute([
                    'tenant_id' => $schoolId,
                    'role_id' => $roleId,
                    'item_id' => $itemId,
                    'can_view' =>
                        !empty($permissions['view']) ? 1 : 0,
                    'can_add' =>
                        !empty($permissions['create'])
                        || !empty($permissions['add'])
                            ? 1
                            : 0,
                    'can_edit' =>
                        !empty($permissions['edit']) ? 1 : 0,
                    'can_delete' =>
                        !empty($permissions['delete']) ? 1 : 0,
                    'can_print' =>
                        !empty($permissions['print']) ? 1 : 0,
                    'can_pdf' =>
                        !empty($permissions['pdf']) ? 1 : 0,
                    'can_export' =>
                        !empty($permissions['export']) ? 1 : 0,
                    'can_import' =>
                        !empty($permissions['import']) ? 1 : 0,
                    'can_approve' =>
                        !empty($permissions['approve']) ? 1 : 0,
                    'can_reject' =>
                        !empty($permissions['reject']) ? 1 : 0,
                    'can_restore' =>
                        !empty($permissions['restore']) ? 1 : 0,
                    'can_manage' =>
                        !empty($permissions['manage_settings'])
                        || !empty($permissions['manage'])
                            ? 1
                            : 0,
                    'can_manage_visibility' =>
                        !empty($permissions['manage_visibility'])
                            ? 1
                            : 0,
                ]);
            }

            school_sidebar_service_bump_version(
                $pdo,
                $schoolId
            );
            $pdo->commit();

            sp_json(
                true,
                'School sidebar overrides and role permissions saved successfully.'
            );
        }

        if ($action === 'reset_branch') {
            sp_csrf($input);

            $schoolId =
                (int)($input['school_id'] ?? 0);
            $branchId =
                (int)($input['branch_id'] ?? 0);
            $roleId =
                (int)($input['role_id'] ?? 0);
            $masterRoleKey = sp_master_role_key(
                (string)($input['master_role_key'] ?? '')
            );

            sp_school($pdo, $schoolId);
            $branch = sp_branch($pdo, $schoolId, $branchId);

            $role = $roleId > 0
                ? sp_role($pdo, $schoolId, $roleId)
                : sp_role_for_key(
                    $pdo,
                    $schoolId,
                    $masterRoleKey
                );
            $roleId = (int)$role['id'];

            if (sp_master_role_key(
                (string)$role['role_key']
            ) !== $masterRoleKey) {
                sp_json(
                    false,
                    'Selected role does not match this Sidebar Master.',
                    [],
                    422
                );
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "DELETE FROM branch_sidebar_permission_grants
                 WHERE tenant_id=?
                   AND branch_id=?
                   AND role_id=?"
            );
            $stmt->execute([
                $schoolId,
                $branchId,
                $roleId,
            ]);

            school_sidebar_service_bump_version(
                $pdo,
                $schoolId
            );
            $pdo->commit();

            sp_json(
                true,
                trim((string)($branch['branch_name'] ?? 'Branch'))
                    . ' permissions reset to school-level permissions.'
            );
        }

        if ($action === 'reset_school') {
            sp_csrf($input);
            $schoolId =
                (int)($input['school_id'] ?? 0);
            sp_school($pdo, $schoolId);

            $pdo->beginTransaction();
            school_sidebar_service_clear_school(
                $pdo,
                $schoolId
            );
            school_sidebar_service_bump_version(
                $pdo,
                $schoolId
            );
            $pdo->commit();

            sp_json(
                true,
                'School sidebar reset to Default Sidebar successfully.'
            );
        }

        if ($action === 'reset_role') {
            sp_csrf($input);

            $schoolId =
                (int)($input['school_id'] ?? 0);
            $roleId =
                (int)($input['role_id'] ?? 0);
            $masterRoleKey = sp_master_role_key(
                (string)($input['master_role_key'] ?? '')
            );

            sp_school($pdo, $schoolId);
            $role = $roleId > 0
                ? sp_role($pdo, $schoolId, $roleId)
                : sp_role_for_key(
                    $pdo,
                    $schoolId,
                    $masterRoleKey
                );
            $roleId = (int)$role['id'];

            if (sp_master_role_key(
                (string)$role['role_key']
            ) !== $masterRoleKey) {
                sp_json(
                    false,
                    'Selected role does not match this Sidebar Master.',
                    [],
                    422
                );
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "DELETE FROM school_sidebar_permission_grants
                 WHERE tenant_id=?
                   AND role_id=?
                   AND action_key NOT LIKE 'parent_delegate\_%'"
            );
            $stmt->execute([$schoolId, $roleId]);

            $stmt = $pdo->prepare(
                "DELETE FROM school_sidebar_action_permissions
                 WHERE tenant_id=?
                   AND role_id=?"
            );
            $stmt->execute([$schoolId, $roleId]);

            if (sp_table($pdo, 'role_sidebar_permissions')) {
                $stmt = $pdo->prepare(
                    "DELETE FROM role_sidebar_permissions
                     WHERE role_id=?"
                );
                $stmt->execute([$roleId]);
            }

            school_sidebar_service_bump_version(
                $pdo,
                $schoolId
            );
            $pdo->commit();

            sp_json(
                true,
                'Selected role sidebar permissions reset successfully.'
            );
        }

        if ($action === 'add') {
            sp_csrf($input);

            $schoolId =
                (int)($input['school_id'] ?? 0);
            $roleId =
                (int)($input['role_id'] ?? 0);
            $masterRoleKey = sp_master_role_key(
                (string)($input['master_role_key'] ?? 'school_admin')
            );
            if ($masterRoleKey === '') {
                $masterRoleKey = 'school_admin';
            }

            $isSchoolAdmin =
                $masterRoleKey === 'school_admin';

            if ($schoolId > 0) {
                sp_school($pdo, $schoolId);

                if (!$isSchoolAdmin) {
                    sp_json(
                        false,
                        'Add Parent/role sidebar options from the global Role Sidebar Master, then Enable/Disable them school-wise.',
                        [],
                        422
                    );
                }

                if ($roleId > 0) {
                    sp_role($pdo, $schoolId, $roleId);
                }
            }

            $pdo->beginTransaction();

            /*
             * School Admin school-specific add keeps its existing behavior.
             * Every global role add creates/reuses one shared sidebar definition
             * and assigns only that role master membership.
             */
            $createSchoolId =
                $schoolId > 0 && $isSchoolAdmin
                    ? $schoolId
                    : 0;

            $created = school_sidebar_service_create_menu(
                $pdo,
                $createSchoolId,
                $input,
                (int)($_SESSION['user_id'] ?? 0) ?: null
            );
            $itemId =
                (int)$created['sidebar_item_id'];

            if ($schoolId <= 0) {
                school_sidebar_service_role_master_upsert(
                    $pdo,
                    $masterRoleKey,
                    $itemId,
                    true,
                    (int)($input['display_order'] ?? 100),
                    (int)($_SESSION['user_id'] ?? 0) ?: null
                );
            }

            /*
             * Preserve existing School Admin immediate access for a
             * school-specific custom item.
             */
            if ($schoolId > 0
                && $roleId > 0
                && $isSchoolAdmin) {
                $role = sp_role(
                    $pdo,
                    $schoolId,
                    $roleId
                );
                $full = sp_is_school_admin_key(
                    (string)($role['role_key'] ?? '')
                );

                foreach (sp_action_catalog($pdo) as $actionRow) {
                    $actionKey =
                        (string)$actionRow['action_key'];
                    $allowed =
                        ($full || $actionKey === 'view')
                            ? 1
                            : 0;

                    $stmt = $pdo->prepare(
                        "INSERT INTO school_sidebar_permission_grants
                            (
                                tenant_id,
                                role_id,
                                sidebar_item_id,
                                action_key,
                                is_allowed
                            )
                         VALUES(?,?,?,?,?)
                         ON DUPLICATE KEY UPDATE
                            is_allowed=VALUES(is_allowed),
                            updated_at=CURRENT_TIMESTAMP"
                    );
                    $stmt->execute([
                        $schoolId,
                        $roleId,
                        $itemId,
                        $actionKey,
                        $allowed,
                    ]);
                }
            }

            school_sidebar_service_bump_version(
                $pdo,
                $schoolId > 0 ? $schoolId : null
            );
            $pdo->commit();

            sp_json(
                true,
                $schoolId > 0
                    ? 'School-specific School Admin sidebar menu created successfully.'
                    : ucwords(
                        str_replace(
                            '_',
                            ' ',
                            $masterRoleKey
                        )
                    )
                        . ' Sidebar Master option added successfully.',
                [
                    'sidebar_item_id' => $itemId,
                    'reused' => (bool)$created['reused'],
                ],
                201
            );
        }

        if ($action === 'edit_menu') {
            sp_csrf($input);

            $schoolId =
                (int)($input['school_id'] ?? 0);
            $masterRoleKey = sp_master_role_key(
                (string)($input['master_role_key'] ?? 'school_admin')
            );
            if ($masterRoleKey === '') {
                $masterRoleKey = 'school_admin';
            }

            $itemId =
                (int)($input['sidebar_item_id'] ?? 0);
            if ($itemId <= 0) {
                sp_json(
                    false,
                    'Invalid sidebar menu.',
                    [],
                    422
                );
            }

            if ($schoolId > 0) {
                sp_school($pdo, $schoolId);
                if ($masterRoleKey !== 'school_admin') {
                    sp_json(
                        false,
                        'Edit Parent/role options from the global Role Sidebar Master.',
                        [],
                        422
                    );
                }
            }

            if ($schoolId <= 0) {
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*)
                     FROM sidebar_role_master_items
                     WHERE role_key=?
                       AND sidebar_item_id=?"
                );
                $stmt->execute([
                    $masterRoleKey,
                    $itemId,
                ]);
                if ((int)$stmt->fetchColumn() === 0) {
                    sp_json(
                        false,
                        'This menu is not part of the selected Role Sidebar Master.',
                        [],
                        422
                    );
                }
            }

            $pdo->beginTransaction();
            school_sidebar_service_edit_menu(
                $pdo,
                $schoolId,
                $itemId,
                $input,
                (int)($_SESSION['user_id'] ?? 0) ?: null
            );

            /*
             * Keep the selected Role Sidebar Master order synchronized with
             * the global sidebar/default order. V21 updated sidebar_items and
             * sidebar_default_settings here, but left the role-master snapshot
             * unchanged. The School Admin runtime could therefore read an old
             * srm.display_order after Super Admin reordered the menu.
             *
             * Preserve is_enabled exactly as it is; only order/audit metadata
             * are synchronized.
             */
            if ($schoolId <= 0) {
                $syncMasterOrder = $pdo->prepare(
                    "UPDATE sidebar_role_master_items
                     SET display_order=:display_order,
                         updated_by=:updated_by,
                         updated_at=CURRENT_TIMESTAMP
                     WHERE role_key=:role_key
                       AND sidebar_item_id=:sidebar_item_id"
                );
                $syncMasterOrder->execute([
                    'display_order' => max(
                        0,
                        min(
                            99999,
                            (int)($input['display_order'] ?? 0)
                        )
                    ),
                    'updated_by' =>
                        (int)($_SESSION['user_id'] ?? 0) ?: null,
                    'role_key' => $masterRoleKey,
                    'sidebar_item_id' => $itemId,
                ]);
            }

            school_sidebar_service_bump_version(
                $pdo,
                $schoolId > 0 ? $schoolId : null
            );
            $pdo->commit();

            sp_json(
                true,
                $schoolId > 0
                    ? 'School sidebar menu updated successfully.'
                    : 'Role Sidebar Master option updated successfully.'
            );
        }

        if ($action === 'delete_menu') {
            sp_csrf($input);

            $schoolId =
                (int)($input['school_id'] ?? 0);
            $masterRoleKey = sp_master_role_key(
                (string)($input['master_role_key'] ?? 'school_admin')
            );
            if ($masterRoleKey === '') {
                $masterRoleKey = 'school_admin';
            }

            $itemId =
                (int)($input['sidebar_item_id'] ?? 0);
            if ($itemId <= 0) {
                sp_json(
                    false,
                    'Invalid sidebar menu.',
                    [],
                    422
                );
            }

            if ($schoolId > 0) {
                sp_school($pdo, $schoolId);

                if ($masterRoleKey !== 'school_admin') {
                    sp_json(
                        false,
                        'Use Enable/Disable for school-wise Parent/role visibility. Remove options only from the global Role Sidebar Master.',
                        [],
                        422
                    );
                }

                $pdo->beginTransaction();
                $affected =
                    school_sidebar_service_delete_menu(
                        $pdo,
                        $schoolId,
                        $itemId
                    );
                school_sidebar_service_bump_version(
                    $pdo,
                    $schoolId
                );
                $pdo->commit();

                sp_json(
                    true,
                    'Sidebar menu disabled/removed for this school only. Other schools are unchanged.',
                    [
                        'affected_items' =>
                            count($affected),
                    ]
                );
            }

            $pdo->beginTransaction();

            if ($masterRoleKey === 'school_admin') {
                /*
                 * Preserve existing School Admin master delete behavior.
                 */
                $affected =
                    school_sidebar_service_delete_menu(
                        $pdo,
                        0,
                        $itemId
                    );
                school_sidebar_service_bump_version(
                    $pdo,
                    null
                );
                $pdo->commit();

                sp_json(
                    true,
                    'School Admin Default Sidebar menu deleted successfully.',
                    [
                        'affected_items' =>
                            count($affected),
                    ]
                );
            }

            /*
             * Parent/other role delete removes ONLY role membership. The
             * shared sidebar definition remains available to any other role
             * that already uses it.
             */
            school_sidebar_service_role_master_remove(
                $pdo,
                $masterRoleKey,
                $itemId
            );
            school_sidebar_service_bump_version(
                $pdo,
                null
            );
            $pdo->commit();

            sp_json(
                true,
                'Sidebar option removed from the selected Role Sidebar Master only.',
                ['affected_items' => 1]
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
.sp-page{display:grid;gap:16px}.sp-head{display:flex;justify-content:space-between;align-items:flex-start;gap:14px}.sp-head h1{margin:0;font-size:30px;font-weight:800;color:#101a3b}.sp-head p{margin:5px 0 0;color:#64748b;font-size:13px}.sp-actions{display:flex;gap:9px;flex-wrap:wrap}.sp-btn{height:42px;padding:0 16px;border:1px solid #e2e8f0;border-radius:10px;background:#fff;color:#101a3b;font-size:12px;font-weight:750;display:inline-flex;align-items:center;gap:8px;cursor:pointer}.sp-btn.primary{border-color:#4f46e5;background:#4f46e5;color:#fff}.sp-btn:disabled{opacity:.45;cursor:not-allowed}.sp-card{background:#fff;border:1px solid #e5eaf2;border-radius:14px;box-shadow:0 4px 16px rgba(15,23,42,.035)}.sp-controls{padding:16px;display:grid;grid-template-columns:minmax(190px,.75fr) minmax(220px,.9fr) minmax(220px,.9fr) minmax(260px,1.35fr);gap:12px}.sp-field label{display:block;margin-bottom:6px;font-size:11px;font-weight:750;color:#334155}.sp-field select,.sp-field input{width:100%;height:42px;border:1px solid #dfe5ee;border-radius:9px;padding:0 12px;background:#fff;color:#172554}.sp-toolbar{padding:0 16px 16px;display:flex;gap:8px;flex-wrap:wrap}.sp-toolbar .sp-btn{height:34px;padding:0 11px;font-size:11px}.sp-message{display:none;padding:12px 15px;border-radius:10px;font-size:12px;font-weight:650}.sp-message.show{display:block}.sp-message.ok{background:#ecfdf3;color:#166534}.sp-message.err{background:#fff1f2;color:#be123c}.sp-add{display:none;padding:16px}.sp-add.show{display:block}.sp-add-grid{display:grid;grid-template-columns:1.1fr .9fr 1.2fr 1fr 1fr auto;gap:10px;align-items:end}.sp-list-head{padding:15px 16px;border-bottom:1px solid #edf1f6;display:flex;justify-content:space-between;align-items:center;gap:12px}.sp-list-head h2{margin:0;font-size:15px;font-weight:800}.sp-list-head small{color:#64748b}.sp-list{display:grid}.sp-row{padding:13px 16px;border-bottom:1px solid #edf1f6}.sp-row:last-child{border-bottom:0}.sp-row-main{display:grid;grid-template-columns:minmax(260px,1fr) auto;gap:14px;align-items:center}.sp-menu{display:flex;align-items:center;gap:10px;min-width:0}.sp-dot{width:34px;height:34px;border-radius:10px;background:#f0edff;color:#5b48e7;display:grid;place-items:center;flex:0 0 34px;font-size:12px;font-weight:800}.sp-menu-copy{min-width:0}.sp-menu-copy strong,.sp-menu-copy small{display:block}.sp-menu-copy strong{font-size:12px;color:#101a3b}.sp-menu-copy small{margin-top:3px;color:#64748b;font-size:10px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.sp-state{display:flex;align-items:center;gap:8px}.sp-state label{font-size:11px;font-weight:750}.sp-row-tools{display:flex;align-items:center;gap:6px;margin-left:8px}.sp-mini{height:30px;padding:0 9px;border:1px solid #e2e8f0;border-radius:8px;background:#fff;color:#334155;font-size:10px;font-weight:750;cursor:pointer}.sp-mini.edit:hover{border-color:#c7d2fe;background:#eef2ff;color:#4338ca}.sp-mini.delete:hover{border-color:#fecaca;background:#fff1f2;color:#be123c}.sp-add-title{grid-column:1/-1;font-size:13px;font-weight:800;color:#101a3b;margin-bottom:-2px}.sp-perms{margin-top:10px;margin-left:44px;display:grid;grid-template-columns:repeat(auto-fit,minmax(92px,1fr));gap:7px;max-width:1100px}.sp-perm{position:relative}.sp-perm input{position:absolute;opacity:0;pointer-events:none}.sp-perm span{min-height:32px;padding:6px 10px;justify-content:flex-start;border:1px solid #e2e8f0;border-radius:8px;background:#fff;color:#475569;font-size:10px;font-weight:750;display:flex;align-items:center;gap:6px;cursor:pointer;user-select:none}.sp-perm span:before{content:'';width:13px;height:13px;border:1.5px solid #cbd5e1;border-radius:4px;background:#fff}.sp-perm input:checked+span{border-color:#c7d2fe;background:#eef2ff;color:#4338ca}.sp-perm input:checked+span:before{border-color:#6366f1;background:#6366f1;box-shadow:inset 0 0 0 3px #fff}.sp-toggle{width:42px;height:23px;border-radius:99px;background:#cbd5e1;position:relative;display:inline-block;cursor:pointer;transition:.2s}.sp-toggle:after{content:'';width:17px;height:17px;border-radius:50%;background:#fff;position:absolute;top:3px;left:3px;box-shadow:0 1px 3px rgba(0,0,0,.2);transition:.2s}.sp-visible{position:absolute;opacity:0}.sp-visible:checked+.sp-toggle{background:#22c55e}.sp-visible:checked+.sp-toggle:after{transform:translateX(19px)}.sp-empty{padding:42px 20px;text-align:center;color:#64748b}.sp-loading{padding:42px 20px;text-align:center;color:#64748b}.sp-badge{display:inline-flex;margin-left:8px;padding:2px 6px;border-radius:999px;background:#f1f5f9;color:#64748b;font-size:8px;font-weight:800}.sp-badge.override{background:#fff7ed;color:#c2410c}
.sp-list.sp-parent-simple{grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;padding:14px;background:#f8fafc}
.sp-list.sp-parent-simple .sp-row{padding:14px;border:1px solid #e5eaf2;border-radius:12px;background:#fff;box-shadow:0 3px 10px rgba(15,23,42,.025)}
.sp-list.sp-parent-simple .sp-row:last-child{border-bottom:1px solid #e5eaf2}
.sp-list.sp-parent-simple .sp-row-main{grid-template-columns:minmax(0,1fr) auto;gap:12px}
.sp-list.sp-parent-simple .sp-menu{min-width:0}
.sp-list.sp-parent-simple .sp-state{margin:0}
.sp-list.sp-parent-simple .sp-state>label:first-child{min-width:54px;text-align:right;color:#15803d}
.sp-list.sp-parent-simple .sp-row:has(.sp-visible:not(:checked)) .sp-state>label:first-child{color:#64748b}
.sp-list.sp-parent-simple .sp-perms{display:none!important}
.sp-list.sp-parent-simple .sp-row-tools{display:none!important}
.sp-list.sp-parent-simple .sp-badge{background:#eef2ff;color:#4f46e5}
.sp-list.sp-parent-simple .sp-dot{background:#eef2ff;color:#4f46e5}
.sp-list.sp-parent-simple .sp-menu-copy strong{font-size:11px}
.sp-list.sp-parent-simple .sp-menu-copy small{font-size:9px}
@media(max-width:1000px){.sp-list.sp-parent-simple{grid-template-columns:1fr}}@media(max-width:1000px){.sp-controls{grid-template-columns:1fr 1fr}.sp-field.search{grid-column:1/-1}.sp-add-grid{grid-template-columns:1fr 1fr}.sp-add-grid .wide{grid-column:1/-1}}@media(max-width:700px){.sp-head{display:block}.sp-actions{margin-top:12px}.sp-controls{grid-template-columns:1fr}.sp-field.search{grid-column:auto}.sp-row-main{grid-template-columns:1fr}.sp-state{margin-left:44px}.sp-perms{margin-left:0}.sp-add-grid{grid-template-columns:1fr}}
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
            <h1>Sidebar Master & School Permissions</h1>
            <p>Select a role, school and optional branch. School-wide permissions remain the parent level; each branch can inherit or apply its own restricted permission set.</p>
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
        <div id="addHelp" style="margin-top:10px;font-size:11px;color:#64748b">Add options to the selected global Role Sidebar Master. School-wise Parent visibility is controlled only with Enable/Disable.</div>
    </section>

    <section class="sp-card">
        <div class="sp-controls">
            <div class="sp-field">
                <label>Role Sidebar Master</label>
                <select id="roleMasterSelect">
                    <option value="school_admin">School Admin</option>
                    <option value="parent">Parent</option>
                </select>
            </div>
            <div class="sp-field">
                <label>School</label>
                <select id="schoolSelect">
                    <option value="0">All Schools / Sidebar Master</option>
                </select>
            </div>
            <div class="sp-field">
                <label>Branch</label>
                <select id="branchSelect" disabled>
                    <option value="0">Select a school first</option>
                </select>
            </div>
            <div class="sp-field search">
                <label>Search Menu</label>
                <input id="searchInput" placeholder="Search title, key or route...">
            </div>
        </div>
        <div class="sp-toolbar">
            <button type="button" class="sp-btn" data-bulk="show">Show All</button>
            <button type="button" class="sp-btn" data-bulk="hide">Hide All</button>
            <button type="button" class="sp-btn" id="grantAllBtn" data-bulk="grant">Grant All</button>
            <button type="button" class="sp-btn" id="removeAllBtn" data-bulk="remove">Remove All</button>
            <button type="button" class="sp-btn" id="resetDefaultBtn" disabled>Reset to Default</button>
        </div>
    </section>

    <section class="sp-card">
        <div class="sp-list-head">
            <div><h2 id="listTitle">Role Sidebar Master</h2><small id="listInfo">Loading...</small><small id="actionHint" style="display:block;margin-top:4px;color:#4f46e5;font-weight:700"></small></div>
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
    'use strict';

    const csrf = <?= json_encode($csrfToken, JSON_UNESCAPED_SLASHES) ?>;
    const endpoint = location.pathname;
    const el = id => document.getElementById(id);

    const schoolSelect = el('schoolSelect');
    const roleMasterSelect = el('roleMasterSelect');
    const branchSelect = el('branchSelect');
    const list = el('list');
    const saveBtn = el('saveBtn');
    const resetDefaultBtn = el('resetDefaultBtn');
    const searchInput = el('searchInput');

    let actions = [];
    let items = [];
    let roleCatalog = [];
    let schoolRoles = [];
    let schoolBranches = [];
    let currentRoleId = 0;
    let dirty = false;
    let editItemId = 0;

    const api = async (action, params = {}, method = 'GET') => {
        let url =
            endpoint
            + '?ajax=1&action='
            + encodeURIComponent(action);

        const opts = {
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        };

        if (method === 'GET') {
            const query = new URLSearchParams(params);
            if ([...query].length) {
                url += '&' + query.toString();
            }
        } else {
            opts.method = 'POST';
            opts.headers['Content-Type'] =
                'application/json';
            opts.body = JSON.stringify({
                ...params,
                csrf_token: csrf
            });
        }

        const response = await fetch(url, opts);
        const text = await response.text();
        let data;

        try {
            data = JSON.parse(text);
        } catch (error) {
            throw new Error(
                text || ('HTTP ' + response.status)
            );
        }

        if (!response.ok || !data.success) {
            throw new Error(
                data.message || 'Request failed'
            );
        }

        return data.data || {};
    };

    const message = (text, ok = false) => {
        const box = el('msg');
        box.textContent = text;
        box.className =
            'sp-message show ' + (ok ? 'ok' : 'err');

        clearTimeout(message.timer);
        message.timer = setTimeout(
            () => box.className = 'sp-message',
            4500
        );

        if (typeof window.schoolToast === 'function') {
            window.schoolToast(
                ok ? 'success' : 'error',
                text,
                ok ? 'Success' : 'Action failed'
            );
        }
    };

    const esc = value =>
        String(value ?? '').replace(
            /[&<>'"]/g,
            character => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                "'": '&#39;',
                '"': '&quot;'
            })[character]
        );

    const actionKey = value => {
        const key = String(value || '')
            .trim()
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '');

        if (key === 'add') return 'create';
        if (key === 'manage' || key === 'settings') {
            return 'manage_settings';
        }
        if (key === 'full') return 'full_access';
        return key;
    };

    const roleKey = () =>
        String(
            roleMasterSelect.value || 'school_admin'
        ).trim().toLowerCase();

    const roleLabel = () => {
        const selected =
            roleMasterSelect.options[
                roleMasterSelect.selectedIndex
            ];
        return selected?.textContent?.trim()
            || 'Role';
    };

    const isSchoolAdmin = () =>
        roleKey() === 'school_admin';

    const isParent = () =>
        roleKey() === 'parent';

    const schoolId = () =>
        Number(schoolSelect.value || 0);

    const branchId = () =>
        Number(branchSelect.value || 0);

    const branchLabel = () => {
        if (!branchId()) return 'All Branches';
        const selected =
            branchSelect.options[
                branchSelect.selectedIndex
            ];
        return selected?.textContent?.trim()
            || 'Branch';
    };

    const setDirty = value => {
        dirty = Boolean(value);
        saveBtn.disabled = !dirty;
    };

    const resolveSchoolRole = async () => {
        currentRoleId = 0;
        schoolRoles = [];

        if (!schoolId()) {
            schoolBranches = [];
            branchSelect.innerHTML =
                '<option value="0">Select a school first</option>';
            branchSelect.value = '0';
            branchSelect.disabled = true;
            return;
        }

        const previousBranchId = branchId();

        const data = await api(
            'roles',
            {school_id: schoolId()}
        );

        schoolRoles = data.roles || [];
        schoolBranches = data.branches || [];

        branchSelect.innerHTML =
            '<option value="0">All Branches / School-wide</option>'
            + schoolBranches
                .map(branch => {
                    const code = String(
                        branch.branch_code || ''
                    ).trim();
                    const status = String(
                        branch.status || 'active'
                    ).toLowerCase();
                    const main = Number(
                        branch.is_main || 0
                    ) === 1
                        ? ' · Main'
                        : '';
                    const inactive =
                        status !== 'active'
                            ? ` · ${status}`
                            : '';

                    return `
                        <option value="${Number(branch.id)}">
                            ${esc(branch.branch_name)}
                            ${code ? ` (${esc(code)})` : ''}
                            ${esc(main + inactive)}
                        </option>
                    `;
                })
                .join('');

        branchSelect.disabled = false;

        if (
            previousBranchId > 0
            && schoolBranches.some(
                branch =>
                    Number(branch.id) ===
                    previousBranchId
            )
        ) {
            branchSelect.value =
                String(previousBranchId);
        } else {
            branchSelect.value = '0';
        }

        const match = schoolRoles.find(
            role =>
                String(
                    role.master_role_key
                    || role.role_key
                    || ''
                ).toLowerCase() === roleKey()
        );

        currentRoleId =
            Number(match?.id || 0);

        if (!currentRoleId) {
            throw new Error(
                `${roleLabel()} role is not available for this school.`
            );
        }
    };

    const updateModeUi = () => {
        const selectedSchool = schoolId();
        const role = roleKey();
        const schoolAdmin = isSchoolAdmin();
        const parent = isParent();

        /*
         * Parent/other role options are created only in the global master.
         * Existing School Admin school-specific Add remains unchanged.
         */
        const canAdd =
            selectedSchool === 0
            || (schoolAdmin && branchId() === 0);

        el('addToggle').style.display =
            canAdd ? '' : 'none';

        if (!canAdd) {
            el('addPanel').classList.remove('show');
        }

        el('grantAllBtn').style.display =
            selectedSchool > 0 && !parent
                ? ''
                : 'none';

        el('removeAllBtn').style.display =
            selectedSchool > 0 && !parent
                ? ''
                : 'none';

        const showAllBtn =
            document.querySelector('[data-bulk="show"]');
        const hideAllBtn =
            document.querySelector('[data-bulk="hide"]');

        if (showAllBtn) {
            showAllBtn.textContent =
                parent
                    ? 'Enable All'
                    : 'Show All';
        }

        if (hideAllBtn) {
            hideAllBtn.textContent =
                parent
                    ? 'Disable All'
                    : 'Hide All';
        }

        resetDefaultBtn.disabled =
            selectedSchool <= 0;

        resetDefaultBtn.textContent =
            selectedSchool <= 0
                ? 'Reset'
                : (
                    branchId() > 0
                        ? 'Reset Branch to School'
                        : (
                            schoolAdmin
                                ? 'Reset to Default'
                                : `Reset ${roleLabel()} Permissions`
                        )
                );

        el('addHelp').textContent =
            parent && selectedSchool === 0
                ? 'Create a Parent sidebar option here. After adding it, use the simple Enable/Disable switch below.'
                : (
                    selectedSchool > 0 && schoolAdmin
                        ? (
                            branchId() > 0
                                ? 'Branch mode controls permissions only. Add or edit sidebar options at the school-wide level.'
                                : 'School Admin keeps the existing school-specific Add behavior.'
                        )
                        : `Add/edit options in the global ${roleLabel()} Sidebar Master. School visibility is configured after selecting a school.`
                );

        el('formTitle').textContent =
            editItemId
                ? `Edit ${roleLabel()} Sidebar Option`
                : `Add ${roleLabel()} Sidebar Option`;
    };

    const renderParents = () => {
        const current = el('addParent').value;

        el('addParent').innerHTML =
            '<option value="0">Root Menu</option>'
            + items
                .filter(item =>
                    Number(item.sidebar_item_id)
                    !== Number(editItemId || 0)
                )
                .map(item => `
                    <option value="${Number(item.sidebar_item_id)}">
                        ${'— '.repeat(Math.min(Number(item.depth || 0), 3))}
                        ${esc(item.display_title)}
                    </option>
                `)
                .join('');

        if (
            [...el('addParent').options]
                .some(option => option.value === current)
        ) {
            el('addParent').value = current;
        }
    };

    const render = () => {
        updateModeUi();

        const term =
            searchInput.value.trim().toLowerCase();

        const visibleItems = items.filter(item =>
            !term
            || [
                item.display_title,
                item.menu_key,
                item.display_route
            ]
                .join(' ')
                .toLowerCase()
                .includes(term)
        );

        el('countInfo').textContent =
            `${visibleItems.length} / ${items.length} menus`;

        if (!visibleItems.length) {
            list.innerHTML =
                '<div class="sp-empty">No sidebar menus found for this role.</div>';
            renderParents();
            return;
        }

        const selectedSchool = schoolId();
        const parentMode = isParent();
        const parentSchool =
            selectedSchool > 0 && parentMode;
        const nonAdminSchool =
            selectedSchool > 0 && !isSchoolAdmin();

        /*
         * Parent uses the same easy permission design in BOTH modes:
         *   1. All Schools / Sidebar Master
         *   2. Individual School
         * One clear Enable/Disable switch is shown for every Parent option.
         */
        list.classList.toggle(
            'sp-parent-simple',
            parentMode
        );

        list.innerHTML = visibleItems
            .map(item => {
                const depth = Math.min(
                    Number(item.depth || 0),
                    5
                );
                const id =
                    Number(item.sidebar_item_id);

                let badge = '';
                if (selectedSchool > 0) {
                    if (isSchoolAdmin()) {
                        const inherited =
                            Number(item.is_override || 0) !== 1;
                        const defaultOn =
                            Number(
                                item.default_enabled
                                ?? item.is_visible
                                ?? 0
                            ) === 1;

                        badge =
                            `<span class="sp-badge ${inherited ? '' : 'override'}">`
                            + (
                                inherited
                                    ? 'Inherited Default'
                                    : 'School Override'
                            )
                            + '</span>'
                            + `<span class="sp-badge">Default ${defaultOn ? 'ON' : 'OFF'}</span>`;
                    } else {
                        badge = parentSchool
                            ? '<span class="sp-badge">Parent Access</span>'
                            : `<span class="sp-badge">${esc(roleLabel())} · School-wise</span>`;
                    }
                } else {
                    badge = parentMode
                        ? '<span class="sp-badge">Parent Option</span>'
                        : `<span class="sp-badge">${esc(roleLabel())} Master</span>`;
                }

                let permissionHtml = '';

                if (
                    selectedSchool > 0
                    && currentRoleId > 0
                    && !parentSchool
                ) {
                    permissionHtml =
                        '<div class="sp-perms">'
                        + actions.map(action => {
                            const key =
                                actionKey(action.action_key);
                            const checked =
                                Number(
                                    item.permissions?.[key]
                                    || 0
                                ) === 1
                                    ? 'checked'
                                    : '';

                            return `
                                <label class="sp-perm">
                                    <input
                                        type="checkbox"
                                        data-item="${id}"
                                        data-action="${esc(key)}"
                                        ${checked}
                                    >
                                    <span>${esc(action.action_name)}</span>
                                </label>`;
                        }).join('')
                        + '</div>';
                }

                const stateText =
                    parentMode
                        ? (
                            Number(item.is_visible) === 1
                                ? 'Enabled'
                                : 'Disabled'
                        )
                        : (
                            selectedSchool > 0
                                ? (
                                    Number(item.is_visible) === 1
                                        ? 'Enabled'
                                        : 'Disabled'
                                )
                                : (
                                    Number(item.is_visible) === 1
                                        ? 'Master Enabled'
                                        : 'Master Disabled'
                                )
                        );

                /* Parent simple mode intentionally uses only the switch. */
                const allowRowTools =
                    !parentMode
                    && (
                        selectedSchool === 0
                        || isSchoolAdmin()
                    );

                return `
                    <div class="sp-row" data-row="${id}">
                        <div class="sp-row-main">
                            <div
                                class="sp-menu"
                                style="padding-left:${depth * 18}px"
                            >
                                <span class="sp-dot">
                                    ${esc(
                                        (item.display_title || '?')
                                            .trim()
                                            .charAt(0)
                                            .toUpperCase()
                                    )}
                                </span>

                                <span class="sp-menu-copy">
                                    <strong>
                                        ${esc(item.display_title)}
                                        ${badge}
                                    </strong>
                                    <small>
                                        ${esc(item.menu_key)}
                                        ·
                                        ${esc(item.display_route || '#')}
                                    </small>
                                </span>
                            </div>

                            <div class="sp-state">
                                <label data-state-label="${id}">
                                    ${stateText}
                                </label>

                                <input
                                    class="sp-visible"
                                    id="vis_${id}"
                                    type="checkbox"
                                    data-visible="${id}"
                                    ${Number(item.is_visible) === 1 ? 'checked' : ''}
                                >
                                <label
                                    class="sp-toggle"
                                    for="vis_${id}"
                                ></label>

                                ${allowRowTools
                                    ? `<div class="sp-row-tools">
                                        <button
                                            type="button"
                                            class="sp-mini edit"
                                            data-edit-menu="${id}"
                                        >Edit</button>
                                        <button
                                            type="button"
                                            class="sp-mini delete"
                                            data-delete-menu="${id}"
                                        >Delete</button>
                                    </div>`
                                    : ''}
                            </div>
                        </div>

                        ${permissionHtml}
                    </div>`;
            })
            .join('');

        renderParents();
    };

    const collect = () => items.map(item => {
        const id = Number(item.sidebar_item_id);
        const visible =
            list.querySelector(
                `[data-visible="${id}"]`
            );
        const permissions = {
            ...(item.permissions || {})
        };

        list
            .querySelectorAll(
                `[data-item="${id}"][data-action]`
            )
            .forEach(checkbox => {
                permissions[
                    actionKey(checkbox.dataset.action)
                ] = checkbox.checked ? 1 : 0;
            });

        /*
         * In Parent + School easy mode there are no individual action
         * checkboxes. The single menu switch controls the complete Parent
         * permission cap for that menu. ON grants every available action;
         * OFF blocks every action.
         */
        if (schoolId() > 0 && isParent()) {
            const enabled = visible
                ? (visible.checked ? 1 : 0)
                : Number(item.is_visible || 0);

            actions.forEach(action => {
                permissions[
                    actionKey(action.action_key)
                ] = enabled;
            });

            permissions.view = enabled;
        }

        return {
            sidebar_item_id: id,
            is_visible: visible
                ? (visible.checked ? 1 : 0)
                : Number(item.is_visible || 0),
            display_order:
                Number(item.display_order || 0),
            permissions
        };
    });

    const loadMeta = async () => {
        const data = await api('meta');

        actions = data.actions || [];
        roleCatalog = data.roles || [];

        if (!roleCatalog.length) {
            roleCatalog = [
                {
                    role_key: 'school_admin',
                    role_name: 'School Admin'
                },
                {
                    role_key: 'parent',
                    role_name: 'Parent'
                }
            ];
        }

        roleMasterSelect.innerHTML =
            roleCatalog
                .map(role => `
                    <option value="${esc(role.role_key)}">
                        ${esc(role.role_name)}
                    </option>
                `)
                .join('');

        if (
            [...roleMasterSelect.options]
                .some(option =>
                    option.value === 'school_admin'
                )
        ) {
            roleMasterSelect.value =
                'school_admin';
        }

        schoolSelect.innerHTML =
            '<option value="0">All Schools / Sidebar Master</option>'
            + (data.schools || [])
                .map(school => `
                    <option value="${Number(school.id)}">
                        ${esc(school.school_name)}
                        ${school.tenant_code
                            ? ` (${esc(school.tenant_code)})`
                            : ''}
                    </option>
                `)
                .join('');

        branchSelect.innerHTML =
            '<option value="0">Select a school first</option>';
        branchSelect.value = '0';
        branchSelect.disabled = true;
    };

    const loadItems = async () => {
        list.innerHTML =
            '<div class="sp-loading">Loading sidebar options...</div>';

        if (schoolId() > 0) {
            await resolveSchoolRole();
        } else {
            currentRoleId = 0;
        }

        const data = await api(
            'load',
            {
                school_id: schoolId(),
                branch_id: branchId(),
                role_id: currentRoleId,
                master_role_key: roleKey()
            }
        );

        items = data.items || [];
        if (Array.isArray(data.actions)) {
            actions = data.actions;
        }

        const selectedSchool = schoolId();

        if (selectedSchool > 0) {
            if (branchId() > 0) {
                el('listTitle').textContent =
                    `${roleLabel()} · ${branchLabel()} Permissions`;

                el('listInfo').textContent =
                    'Branch permissions inherit the selected school permissions by default. Saving this branch creates a branch-specific permission set; a branch can restrict school permissions but cannot exceed them.';

                el('actionHint').textContent =
                    isParent()
                        ? 'Easy Parent branch mode: Enable/Disable controls the Parent menu for this branch only.'
                        : 'Branch-specific actions apply only to users operating under this branch.';
            } else {
                el('listTitle').textContent =
                    `${roleLabel()} · School Permissions`;

                el('listInfo').textContent =
                    isSchoolAdmin()
                        ? 'School Admin keeps the existing Default Sidebar inheritance and school override behavior.'
                        : `Enable only the ${roleLabel()} options that should appear for this school. Other roles are not affected.`;

                el('actionHint').textContent =
                    isParent()
                        ? 'Easy Parent permission mode: use only the Enable/Disable switch. Enabled allows the Parent menu and all available actions; Disabled blocks the menu completely.'
                        : (
                            currentRoleId
                                ? 'Super Admin maximum actions: '
                                    + actions.map(action => action.action_name).join(' · ')
                                : ''
                        );
            }
        } else {
            el('listTitle').textContent =
                isParent()
                    ? 'Parent Sidebar Options'
                    : `${roleLabel()} Sidebar Master`;

            el('listInfo').textContent =
                isParent()
                    ? 'Enable or disable the Parent sidebar options available to all schools.'
                    : (
                        isSchoolAdmin()
                            ? 'This is the existing School Admin Default Sidebar master.'
                            : `These are the global ${roleLabel()} sidebar options available to every school. School visibility is configured separately.`
                    );

            el('actionHint').textContent =
                isParent()
                    ? 'Easy Parent permission mode: use only Enable/Disable. Use + Add Sidebar when you need a new Parent option.'
                    : '';
        }

        setDirty(false);
        render();
    };

    roleMasterSelect.addEventListener(
        'change',
        async () => {
            resetMenuForm();
            el('addPanel').classList.remove('show');

            try {
                await loadItems();
            } catch (error) {
                message(error.message);
                list.innerHTML =
                    `<div class="sp-empty">${esc(error.message)}</div>`;
            }
        }
    );

    schoolSelect.addEventListener(
        'change',
        async () => {
            resetMenuForm();
            el('addPanel').classList.remove('show');
            branchSelect.value = '0';

            try {
                await loadItems();
            } catch (error) {
                message(error.message);
                list.innerHTML =
                    `<div class="sp-empty">${esc(error.message)}</div>`;
            }
        }
    );

    branchSelect.addEventListener(
        'change',
        async () => {
            resetMenuForm();
            el('addPanel').classList.remove('show');

            try {
                await loadItems();
            } catch (error) {
                message(error.message);
                list.innerHTML =
                    `<div class="sp-empty">${esc(error.message)}</div>`;
            }
        }
    );

    searchInput.addEventListener(
        'input',
        render
    );

    list.addEventListener('change', event => {
        const checkbox = event.target;
        if (!(checkbox instanceof HTMLInputElement)) {
            return;
        }

        if (checkbox.matches('[data-visible]')) {
            const id =
                Number(checkbox.dataset.visible || 0);
            const item =
                items.find(row =>
                    Number(row.sidebar_item_id) === id
                );

            if (item) {
                item.is_visible =
                    checkbox.checked ? 1 : 0;

                /*
                 * For role-specific school mode View == visibility.
                 */
                if (
                    schoolId() > 0
                    && !isSchoolAdmin()
                ) {
                    item.permissions =
                        item.permissions || {};
                    item.permissions.view =
                        checkbox.checked ? 1 : 0;

                    /*
                     * Parent school mode has one permission switch only.
                     * Keep every action synchronized with that switch.
                     */
                    if (isParent()) {
                        actions.forEach(action => {
                            item.permissions[
                                actionKey(action.action_key)
                            ] = checkbox.checked ? 1 : 0;
                        });
                    }
                }
            }

            const label =
                list.querySelector(
                    `[data-state-label="${id}"]`
                );

            if (label) {
                label.textContent =
                    isParent()
                        ? (
                            checkbox.checked
                                ? 'Enabled'
                                : 'Disabled'
                        )
                        : (
                            schoolId() > 0
                                ? (
                                    checkbox.checked
                                        ? 'Enabled'
                                        : 'Disabled'
                                )
                                : (
                                    checkbox.checked
                                        ? 'Master Enabled'
                                        : 'Master Disabled'
                                )
                        );
            }

            setDirty(true);
            return;
        }

        if (!checkbox.matches('[data-action]')) {
            return;
        }

        /*
         * Parent simple mode never uses individual action controls.
         */
        if (isParent()) {
            return;
        }

        const row = checkbox.closest('.sp-row');
        if (!row) return;

        const action =
            actionKey(checkbox.dataset.action);

        const view =
            row.querySelector(
                '[data-action="view"]'
            );
        const full =
            row.querySelector(
                '[data-action="full_access"]'
            );
        const all = [
            ...row.querySelectorAll('[data-action]')
        ];

        if (
            checkbox.checked
            && action !== 'view'
            && action !== 'full_access'
            && view
        ) {
            view.checked = true;
        }

        if (action === 'view' && !checkbox.checked) {
            all.forEach(input => {
                input.checked = false;
            });
        }

        if (action === 'full_access') {
            all.forEach(input => {
                input.checked = checkbox.checked;
            });
        }

        if (
            action !== 'full_access'
            && !checkbox.checked
            && full
        ) {
            full.checked = false;
        }

        const id =
            Number(row.dataset.row || 0);
        const item =
            items.find(candidate =>
                Number(candidate.sidebar_item_id) === id
            );

        if (item) {
            item.permissions =
                item.permissions || {};

            all.forEach(input => {
                item.permissions[
                    actionKey(input.dataset.action)
                ] = input.checked ? 1 : 0;
            });
        }

        setDirty(true);
    });

    document
        .querySelectorAll('[data-bulk]')
        .forEach(button => {
            button.addEventListener(
                'click',
                () => {
                    const type =
                        button.dataset.bulk;

                    const rows = [
                        ...list.querySelectorAll('.sp-row')
                    ].filter(row =>
                        row.offsetParent !== null
                    );

                    rows.forEach(row => {
                        const id =
                            Number(row.dataset.row || 0);
                        const item =
                            items.find(candidate =>
                                Number(
                                    candidate.sidebar_item_id
                                ) === id
                            );

                        if (
                            type === 'show'
                            || type === 'hide'
                        ) {
                            const visible =
                                row.querySelector(
                                    '[data-visible]'
                                );

                            if (visible) {
                                visible.checked =
                                    type === 'show';

                                if (item) {
                                    item.is_visible =
                                        visible.checked
                                            ? 1
                                            : 0;

                                    if (
                                        schoolId() > 0
                                        && !isSchoolAdmin()
                                    ) {
                                        item.permissions =
                                            item.permissions
                                            || {};
                                        item.permissions.view =
                                            visible.checked
                                                ? 1
                                                : 0;

                                        if (isParent()) {
                                            actions.forEach(action => {
                                                item.permissions[
                                                    actionKey(
                                                        action.action_key
                                                    )
                                                ] = visible.checked
                                                    ? 1
                                                    : 0;
                                            });
                                        }
                                    }
                                }
                            }
                        } else {
                            const value =
                                type === 'grant';

                            row
                                .querySelectorAll(
                                    '[data-action]'
                                )
                                .forEach(input => {
                                    input.checked = value;
                                });

                            if (item) {
                                item.permissions =
                                    item.permissions || {};

                                row
                                    .querySelectorAll(
                                        '[data-action]'
                                    )
                                    .forEach(input => {
                                        item.permissions[
                                            actionKey(
                                                input.dataset.action
                                            )
                                        ] = input.checked
                                            ? 1
                                            : 0;
                                    });
                            }
                        }
                    });

                    setDirty(true);
                }
            );
        });

    saveBtn.addEventListener(
        'click',
        async () => {
            saveBtn.disabled = true;

            try {
                if (
                    schoolId() > 0
                    && !currentRoleId
                ) {
                    throw new Error(
                        `Select a valid ${roleLabel()} role for this school.`
                    );
                }

                await api(
                    'save',
                    {
                        school_id: schoolId(),
                        branch_id: branchId(),
                        role_id: currentRoleId,
                        master_role_key: roleKey(),
                        items: collect()
                    },
                    'POST'
                );

                message(
                    branchId() > 0
                        ? `${branchLabel()} permissions saved successfully.`
                        : `${roleLabel()} sidebar saved successfully.`,
                    true
                );

                await loadItems();
            } catch (error) {
                message(error.message);
                setDirty(true);
            }
        }
    );

    resetDefaultBtn.addEventListener(
        'click',
        async () => {
            if (!schoolId()) return;

            const prompt = branchId() > 0
                ? `Reset ${branchLabel()} ${roleLabel()} permissions and inherit the school-level permissions again?`
                : (
                    isSchoolAdmin()
                        ? 'Reset this school to the existing School Admin Default Sidebar? This keeps the current School Admin reset behavior.'
                        : `Reset only this school's ${roleLabel()} sidebar permissions? Other roles will not be changed.`
                );

            if (!confirm(prompt)) {
                return;
            }

            try {
                if (branchId() > 0) {
                    await api(
                        'reset_branch',
                        {
                            school_id: schoolId(),
                            branch_id: branchId(),
                            role_id: currentRoleId,
                            master_role_key: roleKey()
                        },
                        'POST'
                    );
                } else if (isSchoolAdmin()) {
                    await api(
                        'reset_school',
                        {school_id: schoolId()},
                        'POST'
                    );
                } else {
                    await api(
                        'reset_role',
                        {
                            school_id: schoolId(),
                            role_id: currentRoleId,
                            master_role_key: roleKey()
                        },
                        'POST'
                    );
                }

                message(
                    branchId() > 0
                        ? `${branchLabel()} permissions reset to school level.`
                        : `${roleLabel()} sidebar permissions reset.`,
                    true
                );

                await loadItems();
            } catch (error) {
                message(error.message);
            }
        }
    );

    const resetMenuForm = () => {
        editItemId = 0;
        el('formTitle').textContent =
            `Add ${roleLabel()} Sidebar Option`;
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
        editItemId =
            Number(item.sidebar_item_id || 0);

        el('addPanel').classList.add('show');
        el('formTitle').textContent =
            `Edit ${roleLabel()} Sidebar Option`;
        el('addSave').textContent = 'Update';
        el('editCancel').style.display = '';
        el('addKey').value =
            item.menu_key || '';
        el('addKey').disabled = true;
        el('addTitle').value =
            item.display_title || '';
        el('addRoute').value =
            item.display_route || '#';
        el('addIcon').value =
            item.display_icon || 'circle';
        el('addOrder').value =
            String(Number(item.display_order || 0));

        renderParents();
        el('addParent').value =
            String(Number(item.parent_id || 0));

        el('addTitle').focus();
        el('addPanel').scrollIntoView({
            behavior: 'smooth',
            block: 'nearest'
        });
    };

    el('refreshBtn').addEventListener(
        'click',
        () =>
            loadItems()
                .catch(error =>
                    message(error.message)
                )
    );

    el('addToggle').addEventListener(
        'click',
        () => {
            if (
                schoolId() > 0
                && (
                    !isSchoolAdmin()
                    || branchId() > 0
                )
            ) {
                message(
                    branchId() > 0
                        ? 'Add or edit sidebar options from the school-wide level, then configure this branch.'
                        : `Add ${roleLabel()} options from the global Role Sidebar Master.`
                );
                return;
            }

            if (editItemId) {
                resetMenuForm();
            }

            el('addPanel').classList.toggle('show');
            updateModeUi();
        }
    );

    el('editCancel').addEventListener(
        'click',
        () => {
            resetMenuForm();
            el('addPanel').classList.remove('show');
        }
    );

    el('addTitle').addEventListener(
        'input',
        () => {
            if (
                !editItemId
                && !el('addKey').dataset.touched
            ) {
                const prefix =
                    !isSchoolAdmin()
                        ? roleKey() + '_'
                        : '';

                el('addKey').value =
                    prefix
                    + el('addTitle')
                        .value
                        .toLowerCase()
                        .replace(/[^a-z0-9]+/g, '_')
                        .replace(/^_+|_+$/g, '');
            }
        }
    );

    el('addKey').addEventListener(
        'input',
        () => {
            el('addKey').dataset.touched = '1';
        }
    );

    list.addEventListener(
        'click',
        async event => {
            const editButton =
                event.target.closest(
                    '[data-edit-menu]'
                );

            if (editButton) {
                if (
                    schoolId() > 0
                    && !isSchoolAdmin()
                ) {
                    message(
                        'Edit this option from the global Role Sidebar Master.'
                    );
                    return;
                }

                const id =
                    Number(
                        editButton.dataset.editMenu || 0
                    );
                const item =
                    items.find(candidate =>
                        Number(
                            candidate.sidebar_item_id
                        ) === id
                    );

                if (item) {
                    openEditForm(item);
                }
                return;
            }

            const deleteButton =
                event.target.closest(
                    '[data-delete-menu]'
                );

            if (!deleteButton) {
                return;
            }

            const id =
                Number(
                    deleteButton.dataset.deleteMenu || 0
                );
            const item =
                items.find(candidate =>
                    Number(candidate.sidebar_item_id)
                    === id
                );

            if (!item) return;

            if (
                schoolId() > 0
                && !isSchoolAdmin()
            ) {
                message(
                    'Use Enable/Disable for this school. Remove Parent/role options only from the global Role Sidebar Master.'
                );
                return;
            }

            const prompt =
                schoolId() > 0
                    ? `Disable/remove "${item.display_title}" for this School Admin sidebar only?`
                    : (
                        isSchoolAdmin()
                            ? `Delete "${item.display_title}" from the School Admin Default Sidebar?`
                            : `Remove "${item.display_title}" from the ${roleLabel()} Sidebar Master? Other role masters will not be changed.`
                    );

            if (!confirm(prompt)) return;

            try {
                await api(
                    'delete_menu',
                    {
                        school_id: schoolId(),
                        role_id: currentRoleId,
                        master_role_key: roleKey(),
                        sidebar_item_id: id
                    },
                    'POST'
                );

                message(
                    'Sidebar option updated successfully.',
                    true
                );

                if (editItemId === id) {
                    resetMenuForm();
                    el('addPanel')
                        .classList.remove('show');
                }

                await loadItems();
            } catch (error) {
                message(error.message);
            }
        }
    );

    el('addSave').addEventListener(
        'click',
        async () => {
            try {
                const title =
                    el('addTitle').value.trim();

                if (!title) {
                    throw new Error(
                        'Enter Menu Title.'
                    );
                }

                if (
                    schoolId() > 0
                    && !isSchoolAdmin()
                ) {
                    throw new Error(
                        `Manage ${roleLabel()} options from the global Role Sidebar Master.`
                    );
                }

                const payload = {
                    school_id: schoolId(),
                    role_id: currentRoleId,
                    master_role_key: roleKey(),
                    menu_title: title,
                    route:
                        el('addRoute').value.trim()
                        || '#',
                    icon:
                        el('addIcon').value.trim()
                        || 'circle',
                    parent_id:
                        Number(
                            el('addParent').value || 0
                        ),
                    display_order:
                        Number(
                            el('addOrder').value || 100
                        ),
                    is_visible: 1
                };

                if (editItemId) {
                    payload.sidebar_item_id =
                        editItemId;

                    const row =
                        list.querySelector(
                            `[data-row="${editItemId}"]`
                        );

                    payload.is_visible =
                        row
                            ?.querySelector(
                                '[data-visible]'
                            )
                            ?.checked
                            ? 1
                            : 0;

                    await api(
                        'edit_menu',
                        payload,
                        'POST'
                    );

                    message(
                        `${roleLabel()} sidebar option updated.`,
                        true
                    );
                } else {
                    payload.menu_key =
                        el('addKey').value.trim();

                    await api(
                        'add',
                        payload,
                        'POST'
                    );

                    message(
                        `${roleLabel()} sidebar option added.`,
                        true
                    );
                }

                resetMenuForm();
                el('addPanel').classList.remove('show');
                await loadItems();
            } catch (error) {
                message(error.message);
            }
        }
    );

    (async () => {
        try {
            await loadMeta();
            resetMenuForm();
            await loadItems();
        } catch (error) {
            message(error.message);
            list.innerHTML =
                `<div class="sp-empty">${esc(error.message)}</div>`;
        }
    })();
})();
</script>

<?php require dirname(__DIR__) . '/includes/layout-end.php'; ?>

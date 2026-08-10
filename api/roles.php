<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');

require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (!function_exists('mrJson')) {
    function mrJson(
        bool $success,
        string $message = '',
        array $data = [],
        int $status = 200
    ): never {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code($status);

        echo json_encode(
            [
                'success' => $success,
                'message' => $message,
                'data' => $data,
            ],
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_INVALID_UTF8_SUBSTITUTE
        );

        exit;
    }
}

if (!function_exists('mrInput')) {
    function mrInput(): array
    {
        $type = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));

        if (str_contains($type, 'application/json')) {
            $raw = (string)file_get_contents('php://input');
            $decoded = json_decode($raw, true);

            if (!is_array($decoded)) {
                mrJson(false, 'Invalid JSON request.', [], 400);
            }

            return $decoded;
        }

        return $_POST;
    }
}

if (!function_exists('mrCsrfValid')) {
    function mrCsrfValid(string $token): bool
    {
        if (function_exists('csrf_is_valid')) {
            return csrf_is_valid($token);
        }

        if (function_exists('verify_csrf_token')) {
            return verify_csrf_token($token);
        }

        $stored = (string)(
            $_SESSION['csrf_token']
            ?? $_SESSION['_csrf']
            ?? $_SESSION['csrf']
            ?? ''
        );

        return $token !== ''
            && $stored !== ''
            && hash_equals($stored, $token);
    }
}

if (!function_exists('mrRequire')) {
    function mrRequire(string $pageKey, string $action): void
    {
        require_login();

        if (!current_user_has_platform_role()) {
            mrJson(
                false,
                'Only a Super Administrator can manage school roles.',
                [],
                403
            );
        }

        if (!has_permission($pageKey, $action)) {
            mrJson(
                false,
                'You do not have permission to perform this action.',
                [],
                403
            );
        }
    }
}

if (!function_exists('mrRequireCsrf')) {
    function mrRequireCsrf(array $input): void
    {
        if (!mrCsrfValid(trim((string)($input['csrf_token'] ?? '')))) {
            mrJson(false, 'Invalid or expired CSRF token.', [], 419);
        }
    }
}

if (!function_exists('mrInt')) {
    function mrInt(mixed $value, int $minimum = 1): int
    {
        $result = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => $minimum]]
        );

        return $result === false ? 0 : (int)$result;
    }
}

if (!function_exists('mrIds')) {
    /**
     * @return array<int,int>
     */
    function mrIds(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map('intval', $value),
            static fn(int $id): bool => $id > 0
        )));
    }
}

if (!function_exists('mrKey')) {
    function mrKey(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';

        return substr(trim($value, '_'), 0, 80);
    }
}

if (!function_exists('mrEnsureSchema')) {
    function mrEnsureSchema(PDO $pdo): void
    {
        $tables = [
            'permission_actions',
            'permissions',
            'role_permissions',
            'user_roles',
            'branch_role_settings',
            'branch_role_permissions',
            'branch_sidebar_permissions',
        ];

        foreach ($tables as $table) {
            if (!school_table_exists($pdo, $table)) {
                mrJson(
                    false,
                    'Run the supplied Multi-School Role & Permission migration first.',
                    ['missing_table' => $table],
                    500
                );
            }
        }

        foreach (
            ['description', 'role_scope', 'deleted_at', 'created_at', 'updated_at']
            as $column
        ) {
            if (!school_column_exists($pdo, 'roles', $column)) {
                mrJson(
                    false,
                    'Run the supplied Multi-School Role & Permission migration first.',
                    ['missing_column' => 'roles.' . $column],
                    500
                );
            }
        }
    }
}

if (!function_exists('mrAudit')) {
    function mrAudit(
        PDO $pdo,
        int $schoolId,
        ?int $branchId,
        string $action,
        string $tableName,
        ?int $recordId,
        string $description,
        array $oldValues = [],
        array $newValues = []
    ): void {
        if (!school_table_exists($pdo, 'activity_logs')) {
            return;
        }

        $statement = $pdo->prepare(
            "INSERT INTO activity_logs (
                tenant_id,
                branch_id,
                user_id,
                role_id,
                module_name,
                action_key,
                table_name,
                record_id,
                old_values,
                new_values,
                description,
                ip_address,
                user_agent
            ) VALUES (
                :tenant_id,
                :branch_id,
                :user_id,
                :role_id,
                'Role & Permission Management',
                :action_key,
                :table_name,
                :record_id,
                :old_values,
                :new_values,
                :description,
                :ip_address,
                :user_agent
            )"
        );

        $statement->execute([
            'tenant_id' => max(1, $schoolId),
            'branch_id' => $branchId ?: null,
            'user_id' => (int)($_SESSION['user_id'] ?? 0) ?: null,
            'role_id' => (int)($_SESSION['role_id'] ?? 0) ?: null,
            'action_key' => $action,
            'table_name' => $tableName,
            'record_id' => $recordId,
            'old_values' => $oldValues
                ? json_encode(
                    $oldValues,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                )
                : null,
            'new_values' => $newValues
                ? json_encode(
                    $newValues,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                )
                : null,
            'description' => substr($description, 0, 255),
            'ip_address' => substr(
                (string)($_SERVER['REMOTE_ADDR'] ?? ''),
                0,
                45
            ) ?: null,
            'user_agent' => substr(
                (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
                0,
                1000
            ) ?: null,
        ]);
    }
}

if (!function_exists('mrSchool')) {
    function mrSchool(PDO $pdo, int $schoolId): array
    {
        $statement = $pdo->prepare(
            "SELECT id, tenant_code, school_name, status
             FROM tenants
             WHERE id = :school_id
               AND status <> 'cancelled'
             LIMIT 1"
        );
        $statement->execute(['school_id' => $schoolId]);
        $school = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($school)) {
            mrJson(false, 'The selected school was not found.', [], 404);
        }

        return $school;
    }
}

if (!function_exists('mrBranch')) {
    function mrBranch(PDO $pdo, int $schoolId, int $branchId): array
    {
        $statement = $pdo->prepare(
            "SELECT id, tenant_id, branch_code, branch_name, is_main, status
             FROM branches
             WHERE id = :branch_id
               AND tenant_id = :school_id
             LIMIT 1"
        );
        $statement->execute([
            'branch_id' => $branchId,
            'school_id' => $schoolId,
        ]);
        $branch = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($branch)) {
            mrJson(
                false,
                'The selected branch does not belong to this school.',
                [],
                422
            );
        }

        return $branch;
    }
}

if (!function_exists('mrRole')) {
    function mrRole(
        PDO $pdo,
        int $roleId,
        int $schoolId = 0,
        bool $includeDeleted = false
    ): array {
        $sql = "SELECT
                    r.id,
                    r.tenant_id,
                    r.role_key,
                    r.role_name,
                    r.description,
                    r.role_scope,
                    r.is_system,
                    r.status,
                    r.created_at,
                    r.updated_at,
                    r.deleted_at,
                    t.school_name
                FROM roles AS r
                LEFT JOIN tenants AS t
                    ON t.id = r.tenant_id
                WHERE r.id = :role_id";

        $params = ['role_id' => $roleId];

        if ($schoolId > 0) {
            $sql .= " AND r.tenant_id = :school_id
                      AND r.role_scope = 'school'";
            $params['school_id'] = $schoolId;
        }

        if (!$includeDeleted) {
            $sql .= ' AND r.deleted_at IS NULL';
        }

        $sql .= ' LIMIT 1';

        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        $role = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($role)) {
            mrJson(false, 'The selected role was not found.', [], 404);
        }

        return $role;
    }
}

if (!function_exists('mrRoleIdsForSchool')) {
    /**
     * @param array<int,int> $roleIds
     * @return array<int,array<string,mixed>>
     */
    function mrRoleIdsForSchool(
        PDO $pdo,
        int $schoolId,
        array $roleIds
    ): array {
        if ($roleIds === []) {
            mrJson(false, 'Select at least one role.', [], 422);
        }

        $marks = implode(',', array_fill(0, count($roleIds), '?'));
        $statement = $pdo->prepare(
            "SELECT id, tenant_id, role_key, role_name, is_system, status
             FROM roles
             WHERE id IN ({$marks})
               AND tenant_id = ?
               AND role_scope = 'school'
               AND deleted_at IS NULL"
        );
        $statement->execute([...$roleIds, $schoolId]);
        $roles = $statement->fetchAll(PDO::FETCH_ASSOC);

        if (count($roles) !== count($roleIds)) {
            mrJson(
                false,
                'One or more roles do not belong to the selected school.',
                [],
                422
            );
        }

        return $roles;
    }
}

if (!function_exists('mrSyncCatalog')) {
    function mrSyncCatalog(PDO $pdo): void
    {
        $statement = $pdo->prepare(
            "INSERT INTO permissions (
                module_id,
                page_id,
                action_id,
                permission_key,
                permission_name,
                portal_scope,
                is_sensitive,
                is_active
            )
            SELECT
                m.id,
                p.id,
                a.id,
                CONCAT(m.module_key, '.', p.page_key, '.', a.action_key),
                CONCAT(p.page_name, ' - ', a.action_name),
                p.portal_scope,
                CASE
                    WHEN a.action_key IN (
                        'delete',
                        'restore',
                        'approve',
                        'reject',
                        'import',
                        'manage_settings',
                        'full_access'
                    ) THEN 1
                    ELSE 0
                END,
                1
            FROM app_modules AS m
            INNER JOIN app_pages AS p
                ON p.module_id = m.id
               AND p.is_active = 1
            CROSS JOIN permission_actions AS a
            WHERE m.is_active = 1
              AND a.is_active = 1
              AND p.portal_scope IN ('school', 'super_admin')
            ON DUPLICATE KEY UPDATE
                module_id = VALUES(module_id),
                page_id = VALUES(page_id),
                action_id = VALUES(action_id),
                permission_name = VALUES(permission_name),
                portal_scope = VALUES(portal_scope),
                is_active = 1"
        );
        $statement->execute();
    }
}

if (!function_exists('mrActions')) {
    function mrActions(PDO $pdo): array
    {
        return $pdo->query(
            "SELECT id, action_key, action_name, display_order
             FROM permission_actions
             WHERE is_active = 1
             ORDER BY display_order, id"
        )->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('mrSchools')) {
    function mrSchools(PDO $pdo): array
    {
        return $pdo->query(
            "SELECT id, tenant_code, school_name, status
             FROM tenants
             WHERE status <> 'cancelled'
             ORDER BY school_name, id"
        )->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('mrBranches')) {
    function mrBranches(PDO $pdo, int $schoolId = 0): array
    {
        $sql = "SELECT id, tenant_id, branch_code, branch_name, is_main, status
                FROM branches";

        $params = [];

        if ($schoolId > 0) {
            $sql .= ' WHERE tenant_id = :school_id';
            $params['school_id'] = $schoolId;
        }

        $sql .= ' ORDER BY tenant_id, is_main DESC, branch_name, id';

        $statement = $pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('mrRoles')) {
    function mrRoles(
        PDO $pdo,
        int $schoolId = 0,
        int $branchId = 0
    ): array {
        $sql = "SELECT
                    r.id,
                    r.tenant_id,
                    r.role_key,
                    r.role_name,
                    r.description,
                    r.role_scope,
                    r.is_system,
                    r.status,
                    r.created_at,
                    r.updated_at,
                    t.school_name,
                    COUNT(DISTINCT ur.user_id) AS user_count,
                    COUNT(DISTINCT CASE
                        WHEN rp.is_allowed = 1
                        THEN rp.permission_id
                    END) AS permission_count,
                    COALESCE(brs.permission_mode, 'inherit')
                        AS permission_mode,
                    COALESCE(brs.sidebar_mode, 'inherit')
                        AS sidebar_mode
                FROM roles AS r
                LEFT JOIN tenants AS t
                    ON t.id = r.tenant_id
                LEFT JOIN user_roles AS ur
                    ON ur.role_id = r.id
                LEFT JOIN role_permissions AS rp
                    ON rp.role_id = r.id
                LEFT JOIN branch_role_settings AS brs
                    ON brs.role_id = r.id
                   AND brs.tenant_id = r.tenant_id";

        $params = [];

        if ($branchId > 0) {
            $sql .= ' AND brs.branch_id = :branch_id';
            $params['branch_id'] = $branchId;
        } else {
            $sql .= ' AND 1 = 0';
        }

        $sql .= " WHERE r.deleted_at IS NULL
                  AND (
                       r.role_scope = 'platform'
                       OR r.role_scope = 'school'
                  )";

        if ($schoolId > 0) {
            $sql .= " AND (
                        r.role_scope = 'platform'
                        OR r.tenant_id = :school_id
                      )";
            $params['school_id'] = $schoolId;
        }

        $sql .= " GROUP BY
                    r.id,
                    r.tenant_id,
                    r.role_key,
                    r.role_name,
                    r.description,
                    r.role_scope,
                    r.is_system,
                    r.status,
                    r.created_at,
                    r.updated_at,
                    t.school_name,
                    brs.permission_mode,
                    brs.sidebar_mode
                  ORDER BY
                    CASE WHEN r.role_scope = 'platform' THEN 0 ELSE 1 END,
                    t.school_name,
                    r.is_system DESC,
                    r.role_name,
                    r.id";

        $statement = $pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('mrUsers')) {
    function mrUsers(PDO $pdo, int $schoolId): array
    {
        if ($schoolId <= 0) {
            return [];
        }

        $statement = $pdo->prepare(
            "SELECT
                u.id,
                u.name,
                u.username,
                u.email,
                u.status,
                u.default_branch_id,
                GROUP_CONCAT(
                    DISTINCT r.id
                    ORDER BY ur.is_primary DESC, r.id
                    SEPARATOR ','
                ) AS role_ids
             FROM users AS u
             LEFT JOIN user_roles AS ur
                ON ur.user_id = u.id
             LEFT JOIN roles AS r
                ON r.id = ur.role_id
               AND r.tenant_id = u.tenant_id
               AND r.role_scope = 'school'
               AND r.deleted_at IS NULL
             WHERE u.tenant_id = :school_id
               AND u.status <> 'locked'
             GROUP BY
                u.id,
                u.name,
                u.username,
                u.email,
                u.status,
                u.default_branch_id
             ORDER BY u.name, u.id"
        );
        $statement->execute(['school_id' => $schoolId]);

        $users = [];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row['role_ids'] = array_values(array_filter(
                array_map(
                    'intval',
                    explode(',', (string)($row['role_ids'] ?? ''))
                ),
                static fn(int $id): bool => $id > 0
            ));
            $users[] = $row;
        }

        return $users;
    }
}

if (!function_exists('mrPermissionMatrix')) {
    function mrPermissionMatrix(
        PDO $pdo,
        int $schoolId,
        int $roleId,
        int $branchId = 0
    ): array {
        $role = mrRole($pdo, $roleId, $schoolId);

        $setting = [
            'permission_mode' => 'inherit',
            'sidebar_mode' => 'inherit',
        ];

        if ($branchId > 0) {
            mrBranch($pdo, $schoolId, $branchId);

            $statement = $pdo->prepare(
                "SELECT permission_mode, sidebar_mode
                 FROM branch_role_settings
                 WHERE tenant_id = :school_id
                   AND branch_id = :branch_id
                   AND role_id = :role_id
                   AND status = 'active'
                 LIMIT 1"
            );
            $statement->execute([
                'school_id' => $schoolId,
                'branch_id' => $branchId,
                'role_id' => $roleId,
            ]);
            $found = $statement->fetch(PDO::FETCH_ASSOC);

            if (is_array($found)) {
                $setting = $found;
            }
        }

        $statement = $pdo->prepare(
            "SELECT
                m.id AS module_id,
                m.module_key,
                m.module_name,
                m.icon AS module_icon,
                m.display_order AS module_order,
                p.id AS page_id,
                p.page_key,
                p.page_name,
                p.route,
                p.icon AS page_icon,
                p.display_order AS page_order,
                a.action_key,
                a.action_name,
                a.display_order AS action_order,
                pr.id AS permission_id,
                COALESCE(base.is_allowed, 0) AS base_allowed,
                COALESCE(branch.is_allowed, 0) AS branch_allowed
             FROM app_modules AS m
             INNER JOIN app_pages AS p
                ON p.module_id = m.id
               AND p.portal_scope = 'school'
               AND p.is_active = 1
             INNER JOIN permissions AS pr
                ON pr.module_id = m.id
               AND pr.page_id = p.id
               AND pr.portal_scope = 'school'
               AND pr.is_active = 1
             INNER JOIN permission_actions AS a
                ON a.id = pr.action_id
               AND a.is_active = 1
             LEFT JOIN role_permissions AS base
                ON base.permission_id = pr.id
               AND base.role_id = :base_role_id
             LEFT JOIN branch_role_permissions AS branch
                ON branch.permission_id = pr.id
               AND branch.role_id = :branch_role_id
               AND branch.tenant_id = :school_id
               AND branch.branch_id = :branch_id
             WHERE m.portal_scope = 'school'
               AND m.is_active = 1
             ORDER BY
                m.display_order,
                m.id,
                p.display_order,
                p.id,
                a.display_order,
                a.id"
        );
        $statement->execute([
            'base_role_id' => $roleId,
            'branch_role_id' => $roleId,
            'school_id' => $schoolId,
            'branch_id' => $branchId,
        ]);

        $modules = [];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $moduleId = (int)$row['module_id'];
            $pageId = (int)$row['page_id'];
            $baseAllowed = (int)$row['base_allowed'];
            $branchAllowed = (int)$row['branch_allowed'];

            $effective = $branchId > 0
                && $setting['permission_mode'] === 'custom'
                    ? $branchAllowed
                    : $baseAllowed;

            if (!isset($modules[$moduleId])) {
                $modules[$moduleId] = [
                    'id' => $moduleId,
                    'module_key' => (string)$row['module_key'],
                    'module_name' => (string)$row['module_name'],
                    'icon' => (string)($row['module_icon'] ?? ''),
                    'pages' => [],
                ];
            }

            if (!isset($modules[$moduleId]['pages'][$pageId])) {
                $modules[$moduleId]['pages'][$pageId] = [
                    'id' => $pageId,
                    'page_key' => (string)$row['page_key'],
                    'page_name' => (string)$row['page_name'],
                    'route' => (string)$row['route'],
                    'icon' => (string)($row['page_icon'] ?? ''),
                    'permissions' => [],
                ];
            }

            $modules[$moduleId]['pages'][$pageId]['permissions'][
                (string)$row['action_key']
            ] = [
                'permission_id' => (int)$row['permission_id'],
                'action_name' => (string)$row['action_name'],
                'base_allowed' => $baseAllowed,
                'branch_allowed' => $branchAllowed,
                'effective_allowed' => $effective,
            ];
        }

        $modules = array_values(array_map(
            static function (array $module): array {
                $module['pages'] = array_values($module['pages']);
                return $module;
            },
            $modules
        ));

        $sidebarStatement = $pdo->prepare(
            "SELECT
                si.id,
                si.parent_id,
                si.menu_key,
                si.menu_title,
                si.route,
                si.icon,
                si.display_order,
                COALESCE(base.can_show, 0) AS base_show,
                COALESCE(branch.can_show, 0) AS branch_show
             FROM sidebar_items AS si
             LEFT JOIN role_sidebar_permissions AS base
                ON base.sidebar_item_id = si.id
               AND base.role_id = :base_sidebar_role_id
             LEFT JOIN branch_sidebar_permissions AS branch
                ON branch.sidebar_item_id = si.id
               AND branch.role_id = :branch_sidebar_role_id
               AND branch.tenant_id = :school_id
               AND branch.branch_id = :branch_id
             WHERE si.portal_scope IN ('school', 'all')
               AND si.is_active = 1
               AND si.show_in_sidebar = 1
             ORDER BY
                COALESCE(si.parent_id, 0),
                si.display_order,
                si.id"
        );
        $sidebarStatement->execute([
            'base_sidebar_role_id' => $roleId,
            'branch_sidebar_role_id' => $roleId,
            'school_id' => $schoolId,
            'branch_id' => $branchId,
        ]);

        $sidebar = [];

        foreach ($sidebarStatement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $base = (int)$row['base_show'];
            $branch = (int)$row['branch_show'];

            $row['effective_show'] = $branchId > 0
                && $setting['sidebar_mode'] === 'custom'
                    ? $branch
                    : $base;

            $sidebar[] = $row;
        }

        return [
            'role' => $role,
            'setting' => $setting,
            'actions' => mrActions($pdo),
            'modules' => $modules,
            'sidebar' => $sidebar,
        ];
    }
}

if (!function_exists('mrSaveBasePermissions')) {
    function mrSaveBasePermissions(
        PDO $pdo,
        int $roleId,
        array $selectedPermissionIds,
        array $selectedSidebarIds
    ): void {
        $permissionIds = array_map(
            'intval',
            $pdo->query(
                "SELECT id
                 FROM permissions
                 WHERE portal_scope = 'school'
                   AND is_active = 1"
            )->fetchAll(PDO::FETCH_COLUMN)
        );

        $selectedPermissionMap = array_fill_keys(
            $selectedPermissionIds,
            true
        );

        $savePermission = $pdo->prepare(
            "INSERT INTO role_permissions (
                role_id,
                permission_id,
                is_allowed,
                created_by
            ) VALUES (
                :role_id,
                :permission_id,
                :is_allowed,
                :created_by
            )
            ON DUPLICATE KEY UPDATE
                is_allowed = VALUES(is_allowed),
                updated_at = CURRENT_TIMESTAMP"
        );

        foreach ($permissionIds as $permissionId) {
            $savePermission->execute([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'is_allowed' => isset(
                    $selectedPermissionMap[$permissionId]
                ) ? 1 : 0,
                'created_by' => (int)($_SESSION['user_id'] ?? 0) ?: null,
            ]);
        }

        $sidebarIds = array_map(
            'intval',
            $pdo->query(
                "SELECT id
                 FROM sidebar_items
                 WHERE portal_scope IN ('school', 'all')
                   AND is_active = 1
                   AND show_in_sidebar = 1"
            )->fetchAll(PDO::FETCH_COLUMN)
        );

        $selectedSidebarMap = array_fill_keys($selectedSidebarIds, true);

        $saveSidebar = $pdo->prepare(
            "INSERT INTO role_sidebar_permissions (
                role_id,
                sidebar_item_id,
                can_show
            ) VALUES (
                :role_id,
                :sidebar_item_id,
                :can_show
            )
            ON DUPLICATE KEY UPDATE
                can_show = VALUES(can_show),
                updated_at = CURRENT_TIMESTAMP"
        );

        foreach ($sidebarIds as $sidebarId) {
            $saveSidebar->execute([
                'role_id' => $roleId,
                'sidebar_item_id' => $sidebarId,
                'can_show' => isset($selectedSidebarMap[$sidebarId]) ? 1 : 0,
            ]);
        }
    }
}

if (!function_exists('mrSaveBranchPermissions')) {
    function mrSaveBranchPermissions(
        PDO $pdo,
        int $schoolId,
        int $branchId,
        int $roleId,
        string $permissionMode,
        string $sidebarMode,
        array $selectedPermissionIds,
        array $selectedSidebarIds
    ): void {
        $setting = $pdo->prepare(
            "INSERT INTO branch_role_settings (
                tenant_id,
                branch_id,
                role_id,
                permission_mode,
                sidebar_mode,
                status,
                created_by,
                updated_by
            ) VALUES (
                :school_id,
                :branch_id,
                :role_id,
                :permission_mode,
                :sidebar_mode,
                'active',
                :created_by,
                :updated_by
            )
            ON DUPLICATE KEY UPDATE
                permission_mode = VALUES(permission_mode),
                sidebar_mode = VALUES(sidebar_mode),
                status = 'active',
                updated_by = VALUES(updated_by),
                updated_at = CURRENT_TIMESTAMP"
        );
        $setting->execute([
            'school_id' => $schoolId,
            'branch_id' => $branchId,
            'role_id' => $roleId,
            'permission_mode' => $permissionMode,
            'sidebar_mode' => $sidebarMode,
            'created_by' => (int)($_SESSION['user_id'] ?? 0) ?: null,
            'updated_by' => (int)($_SESSION['user_id'] ?? 0) ?: null,
        ]);

        if ($permissionMode === 'inherit') {
            $delete = $pdo->prepare(
                "DELETE FROM branch_role_permissions
                 WHERE tenant_id = :school_id
                   AND branch_id = :branch_id
                   AND role_id = :role_id"
            );
            $delete->execute([
                'school_id' => $schoolId,
                'branch_id' => $branchId,
                'role_id' => $roleId,
            ]);
        } else {
            $permissionIds = array_map(
                'intval',
                $pdo->query(
                    "SELECT id
                     FROM permissions
                     WHERE portal_scope = 'school'
                       AND is_active = 1"
                )->fetchAll(PDO::FETCH_COLUMN)
            );

            $selectedMap = array_fill_keys($selectedPermissionIds, true);

            $save = $pdo->prepare(
                "INSERT INTO branch_role_permissions (
                    tenant_id,
                    branch_id,
                    role_id,
                    permission_id,
                    is_allowed,
                    created_by
                ) VALUES (
                    :school_id,
                    :branch_id,
                    :role_id,
                    :permission_id,
                    :is_allowed,
                    :created_by
                )
                ON DUPLICATE KEY UPDATE
                    is_allowed = VALUES(is_allowed),
                    updated_at = CURRENT_TIMESTAMP"
            );

            foreach ($permissionIds as $permissionId) {
                $save->execute([
                    'school_id' => $schoolId,
                    'branch_id' => $branchId,
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'is_allowed' => isset($selectedMap[$permissionId]) ? 1 : 0,
                    'created_by' => (int)($_SESSION['user_id'] ?? 0) ?: null,
                ]);
            }
        }

        if ($sidebarMode === 'inherit') {
            $delete = $pdo->prepare(
                "DELETE FROM branch_sidebar_permissions
                 WHERE tenant_id = :school_id
                   AND branch_id = :branch_id
                   AND role_id = :role_id"
            );
            $delete->execute([
                'school_id' => $schoolId,
                'branch_id' => $branchId,
                'role_id' => $roleId,
            ]);
        } else {
            $sidebarIds = array_map(
                'intval',
                $pdo->query(
                    "SELECT id
                     FROM sidebar_items
                     WHERE portal_scope IN ('school', 'all')
                       AND is_active = 1
                       AND show_in_sidebar = 1"
                )->fetchAll(PDO::FETCH_COLUMN)
            );

            $selectedMap = array_fill_keys($selectedSidebarIds, true);

            $save = $pdo->prepare(
                "INSERT INTO branch_sidebar_permissions (
                    tenant_id,
                    branch_id,
                    role_id,
                    sidebar_item_id,
                    can_show,
                    created_by
                ) VALUES (
                    :school_id,
                    :branch_id,
                    :role_id,
                    :sidebar_item_id,
                    :can_show,
                    :created_by
                )
                ON DUPLICATE KEY UPDATE
                    can_show = VALUES(can_show),
                    updated_at = CURRENT_TIMESTAMP"
            );

            foreach ($sidebarIds as $sidebarId) {
                $save->execute([
                    'school_id' => $schoolId,
                    'branch_id' => $branchId,
                    'role_id' => $roleId,
                    'sidebar_item_id' => $sidebarId,
                    'can_show' => isset($selectedMap[$sidebarId]) ? 1 : 0,
                    'created_by' => (int)($_SESSION['user_id'] ?? 0) ?: null,
                ]);
            }
        }
    }
}

if (!function_exists('mrRoleExport')) {
    function mrRoleExport(
        PDO $pdo,
        int $schoolId,
        int $roleId
    ): array {
        $role = mrRole($pdo, $roleId, $schoolId);

        $permissionStatement = $pdo->prepare(
            "SELECT p.permission_key
             FROM role_permissions AS rp
             INNER JOIN permissions AS p
                ON p.id = rp.permission_id
               AND p.portal_scope = 'school'
             WHERE rp.role_id = :role_id
               AND rp.is_allowed = 1
             ORDER BY p.permission_key"
        );
        $permissionStatement->execute(['role_id' => $roleId]);
        $schoolPermissions = $permissionStatement->fetchAll(
            PDO::FETCH_COLUMN
        );

        $sidebarStatement = $pdo->prepare(
            "SELECT si.menu_key
             FROM role_sidebar_permissions AS rsp
             INNER JOIN sidebar_items AS si
                ON si.id = rsp.sidebar_item_id
               AND si.portal_scope IN ('school', 'all')
             WHERE rsp.role_id = :role_id
               AND rsp.can_show = 1
             ORDER BY si.menu_key"
        );
        $sidebarStatement->execute(['role_id' => $roleId]);
        $schoolSidebarMenus = $sidebarStatement->fetchAll(
            PDO::FETCH_COLUMN
        );

        $branchStatement = $pdo->prepare(
            "SELECT
                brs.branch_id,
                b.branch_code,
                b.branch_name,
                brs.permission_mode,
                brs.sidebar_mode
             FROM branch_role_settings AS brs
             INNER JOIN branches AS b
                ON b.id = brs.branch_id
               AND b.tenant_id = brs.tenant_id
             WHERE brs.tenant_id = :school_id
               AND brs.role_id = :role_id
             ORDER BY b.branch_name, b.id"
        );
        $branchStatement->execute([
            'school_id' => $schoolId,
            'role_id' => $roleId,
        ]);

        $branches = [];

        foreach ($branchStatement->fetchAll(PDO::FETCH_ASSOC) as $branch) {
            $branchId = (int)$branch['branch_id'];

            $permissionStatement = $pdo->prepare(
                "SELECT p.permission_key
                 FROM branch_role_permissions AS brp
                 INNER JOIN permissions AS p
                    ON p.id = brp.permission_id
                 WHERE brp.tenant_id = :school_id
                   AND brp.branch_id = :branch_id
                   AND brp.role_id = :role_id
                   AND brp.is_allowed = 1
                 ORDER BY p.permission_key"
            );
            $permissionStatement->execute([
                'school_id' => $schoolId,
                'branch_id' => $branchId,
                'role_id' => $roleId,
            ]);

            $sidebarStatement = $pdo->prepare(
                "SELECT si.menu_key
                 FROM branch_sidebar_permissions AS bsp
                 INNER JOIN sidebar_items AS si
                    ON si.id = bsp.sidebar_item_id
                 WHERE bsp.tenant_id = :school_id
                   AND bsp.branch_id = :branch_id
                   AND bsp.role_id = :role_id
                   AND bsp.can_show = 1
                 ORDER BY si.menu_key"
            );
            $sidebarStatement->execute([
                'school_id' => $schoolId,
                'branch_id' => $branchId,
                'role_id' => $roleId,
            ]);

            $branches[] = [
                'branch_code' => (string)$branch['branch_code'],
                'branch_name' => (string)$branch['branch_name'],
                'permission_mode' => (string)$branch['permission_mode'],
                'sidebar_mode' => (string)$branch['sidebar_mode'],
                'permissions' => $permissionStatement->fetchAll(
                    PDO::FETCH_COLUMN
                ),
                'sidebar_menus' => $sidebarStatement->fetchAll(
                    PDO::FETCH_COLUMN
                ),
            ];
        }

        return [
            'format' => 'school-erp-role-permissions',
            'version' => 2,
            'exported_at' => date(DATE_ATOM),
            'role' => [
                'role_key' => (string)$role['role_key'],
                'role_name' => (string)$role['role_name'],
                'description' => (string)($role['description'] ?? ''),
                'status' => (string)$role['status'],
            ],
            'school_permissions' => $schoolPermissions,
            'school_sidebar_menus' => $schoolSidebarMenus,
            'branches' => $branches,
        ];
    }
}

$input = mrInput();
$action = strtolower(trim((string)(
    $input['action'] ?? $_GET['action'] ?? ''
)));

if (!isset($pdo) || !($pdo instanceof PDO)) {
    mrJson(false, 'Database connection is unavailable.', [], 500);
}

try {
    mrEnsureSchema($pdo);
    mrSyncCatalog($pdo);

    switch ($action) {
        case 'meta':
            mrRequire('platform_roles', 'view');

            $schoolId = mrInt($_GET['school_id'] ?? 0);
            $branchId = mrInt($_GET['branch_id'] ?? 0);

            if ($schoolId > 0) {
                mrSchool($pdo, $schoolId);
            }

            if ($branchId > 0) {
                mrBranch($pdo, $schoolId, $branchId);
            }

            $roles = mrRoles($pdo, $schoolId, $branchId);
            $schoolRoles = array_values(array_filter(
                $roles,
                static fn(array $role): bool =>
                    $role['role_scope'] === 'school'
            ));

            $permissionCount = (int)$pdo->query(
                "SELECT COUNT(*)
                 FROM permissions
                 WHERE portal_scope = 'school'
                   AND is_active = 1"
            )->fetchColumn();

            mrJson(
                true,
                'Role management details loaded.',
                [
                    'schools' => mrSchools($pdo),
                    'branches' => mrBranches($pdo, $schoolId),
                    'roles' => $roles,
                    'users' => mrUsers($pdo, $schoolId),
                    'actions' => mrActions($pdo),
                    'stats' => [
                        'schools' => count(mrSchools($pdo)),
                        'branches' => count(mrBranches($pdo)),
                        'roles' => count($schoolRoles),
                        'permissions' => $permissionCount,
                    ],
                ]
            );

        case 'save_role':
            mrRequire('platform_roles', 'create');
            mrRequireCsrf($input);

            $schoolId = mrInt($input['school_id'] ?? 0);
            $roleId = mrInt($input['role_id'] ?? 0);
            $isUpdate = $roleId > 0;

            mrRequire(
                'platform_roles',
                $isUpdate ? 'edit' : 'create'
            );

            $school = mrSchool($pdo, $schoolId);
            $roleName = trim((string)($input['role_name'] ?? ''));
            $roleKey = mrKey((string)(
                $input['role_key'] ?? $roleName
            ));
            $description = trim((string)(
                $input['description'] ?? ''
            ));
            $status = strtolower(trim((string)(
                $input['status'] ?? 'active'
            )));

            if (
                mb_strlen($roleName) < 2
                || mb_strlen($roleName) > 120
            ) {
                mrJson(
                    false,
                    'Role Name must contain 2 to 120 characters.',
                    [],
                    422
                );
            }

            if (
                preg_match(
                    '/^[a-z][a-z0-9_]{1,79}$/',
                    $roleKey
                ) !== 1
            ) {
                mrJson(
                    false,
                    'Role Key must start with a letter and contain only lowercase letters, numbers and underscores.',
                    [],
                    422
                );
            }

            if (mb_strlen($description) > 500) {
                mrJson(
                    false,
                    'Description cannot exceed 500 characters.',
                    [],
                    422
                );
            }

            if (!in_array($status, ['active', 'inactive'], true)) {
                mrJson(false, 'Select a valid role status.', [], 422);
            }

            $duplicate = $pdo->prepare(
                "SELECT id
                 FROM roles
                 WHERE tenant_id = :school_id
                   AND role_scope = 'school'
                   AND role_key = :role_key
                   AND deleted_at IS NULL
                   AND (
                        :exclude_role_id_zero = 0
                        OR id <> :exclude_role_id
                   )
                 LIMIT 1"
            );
            $duplicate->execute([
                'school_id' => $schoolId,
                'role_key' => $roleKey,
                'exclude_role_id_zero' => $roleId,
                'exclude_role_id' => $roleId,
            ]);

            if ($duplicate->fetchColumn()) {
                mrJson(
                    false,
                    'Another role in this school already uses this Role Key.',
                    [],
                    409
                );
            }

            $old = [];
            $pdo->beginTransaction();

            try {
                if ($isUpdate) {
                    $existing = mrRole($pdo, $roleId, $schoolId);
                    $old = $existing;

                    if ((int)$existing['is_system'] === 1) {
                        $roleKey = (string)$existing['role_key'];
                    }

                    $statement = $pdo->prepare(
                        "UPDATE roles
                         SET role_name = :role_name,
                             role_key = :role_key,
                             description = :description,
                             status = :status,
                             updated_at = CURRENT_TIMESTAMP
                         WHERE id = :role_id
                           AND tenant_id = :school_id
                           AND role_scope = 'school'
                           AND deleted_at IS NULL"
                    );
                    $statement->execute([
                        'role_name' => $roleName,
                        'role_key' => $roleKey,
                        'description' => $description ?: null,
                        'status' => $status,
                        'role_id' => $roleId,
                        'school_id' => $schoolId,
                    ]);

                    $savedRoleId = $roleId;
                } else {
                    $statement = $pdo->prepare(
                        "INSERT INTO roles (
                            tenant_id,
                            role_key,
                            role_name,
                            description,
                            role_scope,
                            is_system,
                            status,
                            created_by
                        ) VALUES (
                            :school_id,
                            :role_key,
                            :role_name,
                            :description,
                            'school',
                            0,
                            :status,
                            :created_by
                        )"
                    );
                    $statement->execute([
                        'school_id' => $schoolId,
                        'role_key' => $roleKey,
                        'role_name' => $roleName,
                        'description' => $description ?: null,
                        'status' => $status,
                        'created_by' => (int)($_SESSION['user_id'] ?? 0) ?: null,
                    ]);

                    $savedRoleId = (int)$pdo->lastInsertId();

                    $seedSidebar = $pdo->prepare(
                        "INSERT INTO role_sidebar_permissions (
                            role_id,
                            sidebar_item_id,
                            can_show
                        )
                        SELECT :role_id, id, 0
                        FROM sidebar_items
                        WHERE portal_scope IN ('school', 'all')
                          AND is_active = 1
                          AND show_in_sidebar = 1
                        ON DUPLICATE KEY UPDATE
                            updated_at = CURRENT_TIMESTAMP"
                    );
                    $seedSidebar->execute(['role_id' => $savedRoleId]);
                }

                mrAudit(
                    $pdo,
                    $schoolId,
                    null,
                    $isUpdate ? 'role_updated' : 'role_created',
                    'roles',
                    $savedRoleId,
                    ($isUpdate ? 'Updated ' : 'Created ')
                        . $roleName
                        . ' for '
                        . $school['school_name'],
                    $old,
                    [
                        'role_name' => $roleName,
                        'role_key' => $roleKey,
                        'status' => $status,
                    ]
                );

                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }

            mrJson(
                true,
                $isUpdate
                    ? 'Role updated successfully.'
                    : 'Role created successfully.',
                ['role_id' => $savedRoleId],
                $isUpdate ? 200 : 201
            );

        case 'copy_role':
            mrRequire('platform_roles', 'create');
            mrRequireCsrf($input);

            $sourceRoleId = mrInt($input['source_role_id'] ?? 0);
            $targetSchoolId = mrInt($input['school_id'] ?? 0);
            $newName = trim((string)($input['role_name'] ?? ''));
            $newKey = mrKey((string)(
                $input['role_key'] ?? $newName
            ));
            $copyBranches = (int)(
                $input['copy_branch_overrides'] ?? 0
            ) === 1;

            $source = mrRole($pdo, $sourceRoleId);
            $targetSchool = mrSchool($pdo, $targetSchoolId);

            if (
                $source['role_scope'] !== 'school'
                || (int)$source['tenant_id'] <= 0
            ) {
                mrJson(
                    false,
                    'Only a school role can be copied.',
                    [],
                    422
                );
            }

            if ($newName === '') {
                $newName = (string)$source['role_name'] . ' Copy';
            }

            if ($newKey === '') {
                $newKey = mrKey($newName);
            }

            $duplicate = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM roles
                 WHERE tenant_id = :school_id
                   AND role_key = :role_key
                   AND deleted_at IS NULL"
            );
            $duplicate->execute([
                'school_id' => $targetSchoolId,
                'role_key' => $newKey,
            ]);

            if ((int)$duplicate->fetchColumn() > 0) {
                mrJson(
                    false,
                    'The target school already has this Role Key.',
                    [],
                    409
                );
            }

            $pdo->beginTransaction();

            try {
                $insert = $pdo->prepare(
                    "INSERT INTO roles (
                        tenant_id,
                        role_key,
                        role_name,
                        description,
                        role_scope,
                        is_system,
                        status,
                        created_by
                    ) VALUES (
                        :school_id,
                        :role_key,
                        :role_name,
                        :description,
                        'school',
                        0,
                        'active',
                        :created_by
                    )"
                );
                $insert->execute([
                    'school_id' => $targetSchoolId,
                    'role_key' => $newKey,
                    'role_name' => $newName,
                    'description' => (string)($source['description'] ?? ''),
                    'created_by' => (int)($_SESSION['user_id'] ?? 0) ?: null,
                ]);
                $newRoleId = (int)$pdo->lastInsertId();

                $copyPermissions = $pdo->prepare(
                    "INSERT INTO role_permissions (
                        role_id,
                        permission_id,
                        is_allowed,
                        created_by
                    )
                    SELECT
                        :new_role_id,
                        permission_id,
                        is_allowed,
                        :created_by
                    FROM role_permissions
                    WHERE role_id = :source_role_id
                    ON DUPLICATE KEY UPDATE
                        is_allowed = VALUES(is_allowed),
                        updated_at = CURRENT_TIMESTAMP"
                );
                $copyPermissions->execute([
                    'new_role_id' => $newRoleId,
                    'created_by' => (int)($_SESSION['user_id'] ?? 0) ?: null,
                    'source_role_id' => $sourceRoleId,
                ]);

                $copySidebar = $pdo->prepare(
                    "INSERT INTO role_sidebar_permissions (
                        role_id,
                        sidebar_item_id,
                        can_show
                    )
                    SELECT
                        :new_role_id,
                        sidebar_item_id,
                        can_show
                    FROM role_sidebar_permissions
                    WHERE role_id = :source_role_id
                    ON DUPLICATE KEY UPDATE
                        can_show = VALUES(can_show),
                        updated_at = CURRENT_TIMESTAMP"
                );
                $copySidebar->execute([
                    'new_role_id' => $newRoleId,
                    'source_role_id' => $sourceRoleId,
                ]);

                if (
                    $copyBranches
                    && (int)$source['tenant_id'] === $targetSchoolId
                ) {
                    $copySettings = $pdo->prepare(
                        "INSERT INTO branch_role_settings (
                            tenant_id,
                            branch_id,
                            role_id,
                            permission_mode,
                            sidebar_mode,
                            status,
                            created_by,
                            updated_by
                        )
                        SELECT
                            tenant_id,
                            branch_id,
                            :new_role_id,
                            permission_mode,
                            sidebar_mode,
                            status,
                            :created_by,
                            :updated_by
                        FROM branch_role_settings
                        WHERE tenant_id = :school_id
                          AND role_id = :source_role_id
                        ON DUPLICATE KEY UPDATE
                            permission_mode = VALUES(permission_mode),
                            sidebar_mode = VALUES(sidebar_mode),
                            status = VALUES(status),
                            updated_by = VALUES(updated_by)"
                    );
                    $copySettings->execute([
                        'new_role_id' => $newRoleId,
                        'created_by' => (int)($_SESSION['user_id'] ?? 0) ?: null,
                        'updated_by' => (int)($_SESSION['user_id'] ?? 0) ?: null,
                        'school_id' => $targetSchoolId,
                        'source_role_id' => $sourceRoleId,
                    ]);

                    $copyBranchPermissions = $pdo->prepare(
                        "INSERT INTO branch_role_permissions (
                            tenant_id,
                            branch_id,
                            role_id,
                            permission_id,
                            is_allowed,
                            created_by
                        )
                        SELECT
                            tenant_id,
                            branch_id,
                            :new_role_id,
                            permission_id,
                            is_allowed,
                            :created_by
                        FROM branch_role_permissions
                        WHERE tenant_id = :school_id
                          AND role_id = :source_role_id
                        ON DUPLICATE KEY UPDATE
                            is_allowed = VALUES(is_allowed),
                            updated_at = CURRENT_TIMESTAMP"
                    );
                    $copyBranchPermissions->execute([
                        'new_role_id' => $newRoleId,
                        'created_by' => (int)($_SESSION['user_id'] ?? 0) ?: null,
                        'school_id' => $targetSchoolId,
                        'source_role_id' => $sourceRoleId,
                    ]);

                    $copyBranchSidebar = $pdo->prepare(
                        "INSERT INTO branch_sidebar_permissions (
                            tenant_id,
                            branch_id,
                            role_id,
                            sidebar_item_id,
                            can_show,
                            created_by
                        )
                        SELECT
                            tenant_id,
                            branch_id,
                            :new_role_id,
                            sidebar_item_id,
                            can_show,
                            :created_by
                        FROM branch_sidebar_permissions
                        WHERE tenant_id = :school_id
                          AND role_id = :source_role_id
                        ON DUPLICATE KEY UPDATE
                            can_show = VALUES(can_show),
                            updated_at = CURRENT_TIMESTAMP"
                    );
                    $copyBranchSidebar->execute([
                        'new_role_id' => $newRoleId,
                        'created_by' => (int)($_SESSION['user_id'] ?? 0) ?: null,
                        'school_id' => $targetSchoolId,
                        'source_role_id' => $sourceRoleId,
                    ]);
                }

                mrAudit(
                    $pdo,
                    $targetSchoolId,
                    null,
                    'role_copied',
                    'roles',
                    $newRoleId,
                    'Copied role '
                        . $source['role_name']
                        . ' to '
                        . $targetSchool['school_name'],
                    ['source_role_id' => $sourceRoleId],
                    ['new_role_id' => $newRoleId]
                );

                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }

            mrJson(
                true,
                'Role and permissions copied successfully.',
                ['role_id' => $newRoleId],
                201
            );

        case 'toggle_role':
            mrRequire('platform_roles', 'edit');
            mrRequireCsrf($input);

            $schoolId = mrInt($input['school_id'] ?? 0);
            $roleId = mrInt($input['role_id'] ?? 0);
            $role = mrRole($pdo, $roleId, $schoolId);

            if ((int)$role['is_system'] === 1) {
                mrJson(
                    false,
                    'A protected system role cannot be deactivated.',
                    [],
                    422
                );
            }

            $next = $role['status'] === 'active'
                ? 'inactive'
                : 'active';

            $statement = $pdo->prepare(
                "UPDATE roles
                 SET status = :status,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :role_id
                   AND tenant_id = :school_id
                   AND role_scope = 'school'
                   AND is_system = 0
                   AND deleted_at IS NULL"
            );
            $statement->execute([
                'status' => $next,
                'role_id' => $roleId,
                'school_id' => $schoolId,
            ]);

            mrAudit(
                $pdo,
                $schoolId,
                null,
                'role_status_changed',
                'roles',
                $roleId,
                ucfirst($next) . ' role ' . $role['role_name'],
                ['status' => $role['status']],
                ['status' => $next]
            );

            mrJson(
                true,
                $next === 'active'
                    ? 'Role activated successfully.'
                    : 'Role deactivated successfully.'
            );

        case 'delete_role':
            mrRequire('platform_roles', 'delete');
            mrRequireCsrf($input);

            $schoolId = mrInt($input['school_id'] ?? 0);
            $roleId = mrInt($input['role_id'] ?? 0);
            $role = mrRole($pdo, $roleId, $schoolId);

            if ((int)$role['is_system'] === 1) {
                mrJson(
                    false,
                    'A protected system role cannot be deleted.',
                    [],
                    422
                );
            }

            $assigned = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM user_roles
                 WHERE role_id = :role_id"
            );
            $assigned->execute(['role_id' => $roleId]);

            if ((int)$assigned->fetchColumn() > 0) {
                mrJson(
                    false,
                    'Remove this role from all users before deleting it.',
                    [],
                    409
                );
            }

            $statement = $pdo->prepare(
                "UPDATE roles
                 SET status = 'inactive',
                     deleted_at = NOW(),
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :role_id
                   AND tenant_id = :school_id
                   AND role_scope = 'school'
                   AND is_system = 0
                   AND deleted_at IS NULL"
            );
            $statement->execute([
                'role_id' => $roleId,
                'school_id' => $schoolId,
            ]);

            mrAudit(
                $pdo,
                $schoolId,
                null,
                'role_deleted',
                'roles',
                $roleId,
                'Deleted role ' . $role['role_name'],
                $role
            );

            mrJson(true, 'Role deleted successfully.');

        case 'permission_matrix':
            mrRequire('platform_permissions', 'view');

            $schoolId = mrInt($_GET['school_id'] ?? 0);
            $branchId = mrInt($_GET['branch_id'] ?? 0);
            $roleId = mrInt($_GET['role_id'] ?? 0);

            mrSchool($pdo, $schoolId);

            mrJson(
                true,
                'Permission matrix loaded.',
                mrPermissionMatrix(
                    $pdo,
                    $schoolId,
                    $roleId,
                    $branchId
                )
            );

        case 'save_permissions':
            mrRequire('platform_permissions', 'manage');
            mrRequireCsrf($input);

            $schoolId = mrInt($input['school_id'] ?? 0);
            $branchId = mrInt($input['branch_id'] ?? 0);
            $roleIds = mrIds(
                $input['role_ids']
                ?? (
                    isset($input['role_id'])
                        ? [$input['role_id']]
                        : []
                )
            );
            $permissionIds = mrIds($input['permission_ids'] ?? []);
            $sidebarIds = mrIds($input['sidebar_ids'] ?? []);
            $permissionMode = strtolower(trim((string)(
                $input['permission_mode'] ?? 'inherit'
            )));
            $sidebarMode = strtolower(trim((string)(
                $input['sidebar_mode'] ?? 'inherit'
            )));

            mrSchool($pdo, $schoolId);
            $roles = mrRoleIdsForSchool($pdo, $schoolId, $roleIds);

            if ($branchId > 0) {
                mrBranch($pdo, $schoolId, $branchId);
            }

            if (!in_array(
                $permissionMode,
                ['inherit', 'custom'],
                true
            )) {
                mrJson(false, 'Invalid permission inheritance mode.', [], 422);
            }

            if (!in_array(
                $sidebarMode,
                ['inherit', 'custom'],
                true
            )) {
                mrJson(false, 'Invalid sidebar inheritance mode.', [], 422);
            }

            if ($branchId === 0) {
                $permissionMode = 'custom';
                $sidebarMode = 'custom';
            }

            $pdo->beginTransaction();

            try {
                foreach ($roles as $role) {
                    $roleId = (int)$role['id'];

                    if ($branchId > 0) {
                        mrSaveBranchPermissions(
                            $pdo,
                            $schoolId,
                            $branchId,
                            $roleId,
                            $permissionMode,
                            $sidebarMode,
                            $permissionIds,
                            $sidebarIds
                        );
                    } else {
                        mrSaveBasePermissions(
                            $pdo,
                            $roleId,
                            $permissionIds,
                            $sidebarIds
                        );
                    }

                    mrAudit(
                        $pdo,
                        $schoolId,
                        $branchId ?: null,
                        'permissions_updated',
                        $branchId > 0
                            ? 'branch_role_permissions'
                            : 'role_permissions',
                        $roleId,
                        'Updated '
                            . ($branchId > 0 ? 'branch' : 'school')
                            . ' permissions for '
                            . $role['role_name'],
                        [],
                        [
                            'permission_mode' => $permissionMode,
                            'sidebar_mode' => $sidebarMode,
                            'permission_ids' => $permissionIds,
                            'sidebar_ids' => $sidebarIds,
                        ]
                    );
                }

                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }

            mrJson(
                true,
                count($roleIds) > 1
                    ? 'Permissions bulk-assigned successfully.'
                    : 'Permissions saved successfully.'
            );

        case 'export_role':
            mrRequire('platform_permissions', 'export');

            $schoolId = mrInt($_GET['school_id'] ?? 0);
            $roleId = mrInt($_GET['role_id'] ?? 0);

            mrSchool($pdo, $schoolId);
            $export = mrRoleExport($pdo, $schoolId, $roleId);

            mrAudit(
                $pdo,
                $schoolId,
                null,
                'role_exported',
                'roles',
                $roleId,
                'Exported role permission configuration.'
            );

            mrJson(
                true,
                'Role permission export generated.',
                [
                    'file_name' => 'role-permissions-'
                        . $export['role']['role_key']
                        . '.json',
                    'export' => $export,
                ]
            );

        case 'import_role':
            mrRequire('platform_permissions', 'import');
            mrRequireCsrf($input);

            $schoolId = mrInt($input['school_id'] ?? 0);
            $targetRoleId = mrInt($input['role_id'] ?? 0);
            $school = mrSchool($pdo, $schoolId);

            $payload = null;

            if (
                isset($_FILES['import_file'])
                && is_array($_FILES['import_file'])
                && (int)($_FILES['import_file']['error'] ?? UPLOAD_ERR_NO_FILE)
                    === UPLOAD_ERR_OK
            ) {
                $path = (string)$_FILES['import_file']['tmp_name'];
                $raw = (string)file_get_contents($path);
                $payload = json_decode($raw, true);
            } elseif (isset($input['import_json'])) {
                $payload = json_decode(
                    (string)$input['import_json'],
                    true
                );
            }

            if (
                !is_array($payload)
                || ($payload['format'] ?? '') !== 'school-erp-role-permissions'
            ) {
                mrJson(
                    false,
                    'Select a valid School ERP role permission JSON file.',
                    [],
                    422
                );
            }

            $roleData = is_array($payload['role'] ?? null)
                ? $payload['role']
                : [];

            $roleName = trim((string)(
                $input['role_name']
                ?? $roleData['role_name']
                ?? 'Imported Role'
            ));

            $roleKey = mrKey((string)(
                $input['role_key']
                ?? $roleData['role_key']
                ?? $roleName
            ));

            $pdo->beginTransaction();

            try {
                if ($targetRoleId > 0) {
                    $targetRole = mrRole(
                        $pdo,
                        $targetRoleId,
                        $schoolId
                    );
                    $savedRoleId = $targetRoleId;
                } else {
                    $keyCheck = $pdo->prepare(
                        "SELECT COUNT(*)
                         FROM roles
                         WHERE tenant_id = :school_id
                           AND role_key = :role_key
                           AND deleted_at IS NULL"
                    );
                    $keyCheck->execute([
                        'school_id' => $schoolId,
                        'role_key' => $roleKey,
                    ]);

                    if ((int)$keyCheck->fetchColumn() > 0) {
                        $roleKey .= '_' . date('His');
                    }

                    $insert = $pdo->prepare(
                        "INSERT INTO roles (
                            tenant_id,
                            role_key,
                            role_name,
                            description,
                            role_scope,
                            is_system,
                            status,
                            created_by
                        ) VALUES (
                            :school_id,
                            :role_key,
                            :role_name,
                            :description,
                            'school',
                            0,
                            'active',
                            :created_by
                        )"
                    );
                    $insert->execute([
                        'school_id' => $schoolId,
                        'role_key' => $roleKey,
                        'role_name' => $roleName,
                        'description' => trim((string)(
                            $roleData['description'] ?? ''
                        )) ?: null,
                        'created_by' => (int)($_SESSION['user_id'] ?? 0) ?: null,
                    ]);
                    $savedRoleId = (int)$pdo->lastInsertId();
                }

                $permissionKeys = is_array(
                    $payload['school_permissions'] ?? null
                ) ? $payload['school_permissions'] : [];

                $sidebarKeys = is_array(
                    $payload['school_sidebar_menus'] ?? null
                ) ? $payload['school_sidebar_menus'] : [];

                $permissionIds = [];

                if ($permissionKeys !== []) {
                    $marks = implode(
                        ',',
                        array_fill(0, count($permissionKeys), '?')
                    );
                    $statement = $pdo->prepare(
                        "SELECT id
                         FROM permissions
                         WHERE permission_key IN ({$marks})
                           AND portal_scope = 'school'
                           AND is_active = 1"
                    );
                    $statement->execute(array_values($permissionKeys));
                    $permissionIds = array_map(
                        'intval',
                        $statement->fetchAll(PDO::FETCH_COLUMN)
                    );
                }

                $sidebarIds = [];

                if ($sidebarKeys !== []) {
                    $marks = implode(
                        ',',
                        array_fill(0, count($sidebarKeys), '?')
                    );
                    $statement = $pdo->prepare(
                        "SELECT id
                         FROM sidebar_items
                         WHERE menu_key IN ({$marks})
                           AND portal_scope IN ('school', 'all')
                           AND is_active = 1"
                    );
                    $statement->execute(array_values($sidebarKeys));
                    $sidebarIds = array_map(
                        'intval',
                        $statement->fetchAll(PDO::FETCH_COLUMN)
                    );
                }

                mrSaveBasePermissions(
                    $pdo,
                    $savedRoleId,
                    $permissionIds,
                    $sidebarIds
                );

                $branchesByCode = [];
                foreach (mrBranches($pdo, $schoolId) as $branch) {
                    $branchesByCode[(string)$branch['branch_code']] =
                        (int)$branch['id'];
                }

                foreach (
                    is_array($payload['branches'] ?? null)
                        ? $payload['branches']
                        : []
                    as $branchData
                ) {
                    if (!is_array($branchData)) {
                        continue;
                    }

                    $branchCode = (string)(
                        $branchData['branch_code'] ?? ''
                    );
                    $branchId = $branchesByCode[$branchCode] ?? 0;

                    if ($branchId <= 0) {
                        continue;
                    }

                    $branchPermissionKeys = is_array(
                        $branchData['permissions'] ?? null
                    ) ? $branchData['permissions'] : [];

                    $branchSidebarKeys = is_array(
                        $branchData['sidebar_menus'] ?? null
                    ) ? $branchData['sidebar_menus'] : [];

                    $branchPermissionIds = [];

                    if ($branchPermissionKeys !== []) {
                        $marks = implode(
                            ',',
                            array_fill(
                                0,
                                count($branchPermissionKeys),
                                '?'
                            )
                        );
                        $statement = $pdo->prepare(
                            "SELECT id
                             FROM permissions
                             WHERE permission_key IN ({$marks})
                               AND portal_scope = 'school'
                               AND is_active = 1"
                        );
                        $statement->execute(
                            array_values($branchPermissionKeys)
                        );
                        $branchPermissionIds = array_map(
                            'intval',
                            $statement->fetchAll(PDO::FETCH_COLUMN)
                        );
                    }

                    $branchSidebarIds = [];

                    if ($branchSidebarKeys !== []) {
                        $marks = implode(
                            ',',
                            array_fill(
                                0,
                                count($branchSidebarKeys),
                                '?'
                            )
                        );
                        $statement = $pdo->prepare(
                            "SELECT id
                             FROM sidebar_items
                             WHERE menu_key IN ({$marks})
                               AND portal_scope IN ('school', 'all')
                               AND is_active = 1"
                        );
                        $statement->execute(
                            array_values($branchSidebarKeys)
                        );
                        $branchSidebarIds = array_map(
                            'intval',
                            $statement->fetchAll(PDO::FETCH_COLUMN)
                        );
                    }

                    mrSaveBranchPermissions(
                        $pdo,
                        $schoolId,
                        $branchId,
                        $savedRoleId,
                        in_array(
                            $branchData['permission_mode'] ?? '',
                            ['inherit', 'custom'],
                            true
                        ) ? (string)$branchData['permission_mode'] : 'inherit',
                        in_array(
                            $branchData['sidebar_mode'] ?? '',
                            ['inherit', 'custom'],
                            true
                        ) ? (string)$branchData['sidebar_mode'] : 'inherit',
                        $branchPermissionIds,
                        $branchSidebarIds
                    );
                }

                mrAudit(
                    $pdo,
                    $schoolId,
                    null,
                    'role_imported',
                    'roles',
                    $savedRoleId,
                    'Imported role permission configuration into '
                        . $school['school_name'],
                    [],
                    ['role_id' => $savedRoleId]
                );

                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }

            mrJson(
                true,
                'Role permission file imported successfully.',
                ['role_id' => $savedRoleId],
                201
            );

        case 'save_user_roles':
            mrRequire('platform_users', 'manage');
            mrRequireCsrf($input);

            $schoolId = mrInt($input['school_id'] ?? 0);
            $userId = mrInt($input['user_id'] ?? 0);
            $roleIds = mrIds($input['role_ids'] ?? []);

            mrSchool($pdo, $schoolId);
            $roles = mrRoleIdsForSchool($pdo, $schoolId, $roleIds);

            $userStatement = $pdo->prepare(
                "SELECT id, name, username, role_id
                 FROM users
                 WHERE id = :user_id
                   AND tenant_id = :school_id
                 LIMIT 1"
            );
            $userStatement->execute([
                'user_id' => $userId,
                'school_id' => $schoolId,
            ]);
            $user = $userStatement->fetch(PDO::FETCH_ASSOC);

            if (!is_array($user)) {
                mrJson(
                    false,
                    'The selected user does not belong to this school.',
                    [],
                    404
                );
            }

            $oldStatement = $pdo->prepare(
                "SELECT ur.role_id
                 FROM user_roles AS ur
                 INNER JOIN roles AS r
                    ON r.id = ur.role_id
                   AND r.tenant_id = :school_id
                   AND r.role_scope = 'school'
                 WHERE ur.user_id = :user_id
                 ORDER BY ur.is_primary DESC, ur.role_id"
            );
            $oldStatement->execute([
                'school_id' => $schoolId,
                'user_id' => $userId,
            ]);
            $oldRoleIds = array_map(
                'intval',
                $oldStatement->fetchAll(PDO::FETCH_COLUMN)
            );

            $primaryRoleId = (int)$roles[0]['id'];
            $pdo->beginTransaction();

            try {
                $delete = $pdo->prepare(
                    "DELETE ur
                     FROM user_roles AS ur
                     INNER JOIN roles AS r
                        ON r.id = ur.role_id
                       AND r.tenant_id = :school_id
                       AND r.role_scope = 'school'
                     WHERE ur.user_id = :user_id"
                );
                $delete->execute([
                    'school_id' => $schoolId,
                    'user_id' => $userId,
                ]);

                $insert = $pdo->prepare(
                    "INSERT INTO user_roles (
                        user_id,
                        role_id,
                        is_primary,
                        assigned_by
                    ) VALUES (
                        :user_id,
                        :role_id,
                        :is_primary,
                        :assigned_by
                    )"
                );

                foreach ($roles as $index => $role) {
                    $insert->execute([
                        'user_id' => $userId,
                        'role_id' => (int)$role['id'],
                        'is_primary' => $index === 0 ? 1 : 0,
                        'assigned_by' => (int)(
                            $_SESSION['user_id'] ?? 0
                        ) ?: null,
                    ]);
                }

                $update = $pdo->prepare(
                    "UPDATE users
                     SET role_id = :role_id
                     WHERE id = :user_id
                       AND tenant_id = :school_id"
                );
                $update->execute([
                    'role_id' => $primaryRoleId,
                    'user_id' => $userId,
                    'school_id' => $schoolId,
                ]);

                mrAudit(
                    $pdo,
                    $schoolId,
                    null,
                    'user_roles_updated',
                    'user_roles',
                    $userId,
                    'Updated roles for user ' . $user['username'],
                    ['role_ids' => $oldRoleIds],
                    [
                        'role_ids' => array_map(
                            static fn(array $role): int =>
                                (int)$role['id'],
                            $roles
                        ),
                        'primary_role_id' => $primaryRoleId,
                    ]
                );

                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }

            mrJson(true, 'User roles assigned successfully.');

        case 'audit':
            mrRequire('platform_activity_logs', 'view');

            $schoolId = mrInt($_GET['school_id'] ?? 0);
            $branchId = mrInt($_GET['branch_id'] ?? 0);
            $roleId = mrInt($_GET['role_id'] ?? 0);

            $sql = "SELECT
                        al.id,
                        al.tenant_id,
                        al.branch_id,
                        al.user_id,
                        al.role_id,
                        al.action_key,
                        al.table_name,
                        al.record_id,
                        al.description,
                        al.ip_address,
                        al.created_at,
                        t.school_name,
                        b.branch_name,
                        u.name AS changed_by
                    FROM activity_logs AS al
                    LEFT JOIN tenants AS t
                        ON t.id = al.tenant_id
                    LEFT JOIN branches AS b
                        ON b.id = al.branch_id
                    LEFT JOIN users AS u
                        ON u.id = al.user_id
                    WHERE al.module_name = 'Role & Permission Management'";

            $params = [];

            if ($schoolId > 0) {
                $sql .= ' AND al.tenant_id = :school_id';
                $params['school_id'] = $schoolId;
            }

            if ($branchId > 0) {
                $sql .= ' AND al.branch_id = :branch_id';
                $params['branch_id'] = $branchId;
            }

            if ($roleId > 0) {
                $sql .= " AND (
                            al.record_id = :role_id
                            OR JSON_EXTRACT(
                                al.new_values,
                                '$.role_id'
                            ) = :role_id_json
                          )";
                $params['role_id'] = $roleId;
                $params['role_id_json'] = $roleId;
            }

            $sql .= ' ORDER BY al.id DESC LIMIT 250';

            $statement = $pdo->prepare($sql);
            $statement->execute($params);

            mrJson(
                true,
                'Audit history loaded.',
                ['rows' => $statement->fetchAll(PDO::FETCH_ASSOC)]
            );

        default:
            mrJson(false, 'Invalid API action.', [], 400);
    }
} catch (Throwable $exception) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'api/roles.php: '
        . $exception->getMessage()
        . ' in '
        . $exception->getFile()
        . ':'
        . $exception->getLine()
    );

    mrJson(
        false,
        'The request could not be completed. Check the PHP error log.',
        [],
        500
    );
}

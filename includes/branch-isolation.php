<?php
declare(strict_types=1);

/*
 * Complete Branch Isolation Helpers
 * Location: includes/branch-isolation.php
 * Build: 2026-08-14-strict-branch-isolation-v48
 *
 * Usage in branch-owned page/API queries:
 *
 *   require_once __DIR__ . '/branch-isolation.php';
 *
 *   $stmt = branch_execute_scoped(
 *       $pdo,
 *       "SELECT *
 *        FROM students
 *        WHERE tenant_id = :tenant_id
 *          AND branch_id = :branch_id
 *        ORDER BY id DESC"
 *   );
 *
 * Never trust tenant_id or branch_id received through GET/POST.
 */

if (!function_exists('branch_identifier')) {
    function branch_identifier(string $identifier): string
    {
        $identifier = trim($identifier);

        if (
            $identifier === ''
            || preg_match(
                '/^[A-Za-z_][A-Za-z0-9_]*$/',
                $identifier
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Unsafe SQL identifier.'
            );
        }

        return $identifier;
    }
}

if (!function_exists('branch_role_key')) {
    function branch_role_key(): string
    {
        $key = strtolower(
            trim(
                (string)(
                    $_SESSION['role_key']
                    ?? ''
                )
            )
        );

        $name = strtolower(
            trim(
                (string)(
                    $_SESSION['role_name']
                    ?? ''
                )
            )
        );

        $aliases = [
            'schooladministrator' => 'school_admin',
            'school_administrator' => 'school_admin',
            'school-administrator' => 'school_admin',
            'schooladmin' => 'school_admin',
            'administrator' => 'school_admin',
            'admin' => 'school_admin',

            'branchadministrator' => 'branch_admin',
            'branch_administrator' => 'branch_admin',
            'branch-administrator' => 'branch_admin',
            'branchadmin' => 'branch_admin',
        ];

        $normalized = str_replace(
            [' ', '-'],
            '_',
            $key !== '' ? $key : $name
        );

        $compact = str_replace(
            '_',
            '',
            $normalized
        );

        return $aliases[$normalized]
            ?? $aliases[$compact]
            ?? $normalized;
    }
}

if (!function_exists('branch_is_school_admin')) {
    function branch_is_school_admin(): bool
    {
        return branch_role_key() === 'school_admin';
    }
}

if (!function_exists('branch_is_branch_admin')) {
    function branch_is_branch_admin(): bool
    {
        return branch_role_key() === 'branch_admin';
    }
}

if (!function_exists('branch_current_tenant_id')) {
    function branch_current_tenant_id(): int
    {
        if (function_exists('current_school_id')) {
            $id = (int)current_school_id();

            if ($id > 0) {
                return $id;
            }
        }

        return (int)(
            $_SESSION['school_id']
            ?? $_SESSION['tenant_id']
            ?? 0
        );
    }
}

if (!function_exists('branch_current_id')) {
    function branch_current_id(): int
    {
        if (function_exists('current_branch_id')) {
            $id = (int)current_branch_id();

            if ($id > 0) {
                return $id;
            }
        }

        return (int)(
            $_SESSION['branch_id']
            ?? $_SESSION['default_branch_id']
            ?? 0
        );
    }
}

if (!function_exists('branch_require_scope')) {
    function branch_require_scope(
        ?PDO $pdo = null
    ): array {
        $db = $pdo;

        if (!($db instanceof PDO)) {
            $globalPdo = $GLOBALS['pdo'] ?? null;

            if ($globalPdo instanceof PDO) {
                $db = $globalPdo;
            }
        }

        $tenantId = branch_current_tenant_id();
        $branchId = branch_current_id();

        if ($tenantId <= 0) {
            throw new RuntimeException(
                'A valid School context is required.',
                403
            );
        }

        if ($branchId <= 0) {
            throw new RuntimeException(
                'A valid Branch context is required.',
                403
            );
        }

        if ($db instanceof PDO) {
            $stmt = $db->prepare(
                "SELECT id,tenant_id,branch_code,branch_name,status
                 FROM branches
                 WHERE id = :branch_id
                   AND tenant_id = :tenant_id
                   AND status = 'active'
                 LIMIT 1"
            );

            $stmt->execute([
                'branch_id' => $branchId,
                'tenant_id' => $tenantId,
            ]);

            $branch = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$branch) {
                throw new RuntimeException(
                    'The selected Branch is not active for this School.',
                    403
                );
            }

            if (
                function_exists('school_user_can_access_branch')
                && !school_user_can_access_branch($branchId)
            ) {
                throw new RuntimeException(
                    'You do not have access to the selected Branch.',
                    403
                );
            }

            return [
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'branch_code' =>
                    (string)($branch['branch_code'] ?? ''),
                'branch_name' =>
                    (string)($branch['branch_name'] ?? ''),
            ];
        }

        return [
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'branch_code' =>
                (string)($_SESSION['branch_code'] ?? ''),
            'branch_name' =>
                (string)($_SESSION['branch_name'] ?? ''),
        ];
    }
}

if (!function_exists('branch_scope_condition')) {
    function branch_scope_condition(
        string $alias = ''
    ): string {
        $alias = trim($alias);

        if ($alias !== '') {
            branch_identifier($alias);
            $alias .= '.';
        }

        return
            $alias . 'tenant_id = :tenant_id'
            . ' AND '
            . $alias . 'branch_id = :branch_id';
    }
}

if (!function_exists('branch_scope_params')) {
    function branch_scope_params(
        array $params = [],
        ?PDO $pdo = null
    ): array {
        $scope = branch_require_scope($pdo);

        $params['tenant_id'] =
            (int)$scope['tenant_id'];

        $params['branch_id'] =
            (int)$scope['branch_id'];

        return $params;
    }
}

if (!function_exists('branch_execute_scoped')) {
    function branch_execute_scoped(
        PDO $pdo,
        string $sql,
        array $params = []
    ): PDOStatement {
        if (
            !str_contains($sql, ':tenant_id')
            || !str_contains($sql, ':branch_id')
        ) {
            throw new LogicException(
                'Branch-owned SQL must contain both :tenant_id and :branch_id.'
            );
        }

        $stmt = $pdo->prepare($sql);

        $stmt->execute(
            branch_scope_params(
                $params,
                $pdo
            )
        );

        return $stmt;
    }
}

if (!function_exists('branch_table_has_scope')) {
    function branch_table_has_scope(
        PDO $pdo,
        string $table
    ): bool {
        $table = branch_identifier($table);

        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = :table_name
               AND column_name IN ('tenant_id','branch_id')"
        );

        $stmt->execute([
            'table_name' => $table,
        ]);

        return (int)$stmt->fetchColumn() === 2;
    }
}

if (!function_exists('branch_require_record')) {
    function branch_require_record(
        PDO $pdo,
        string $table,
        int $recordId,
        string $idColumn = 'id'
    ): array {
        $table = branch_identifier($table);
        $idColumn = branch_identifier($idColumn);

        if ($recordId <= 0) {
            throw new InvalidArgumentException(
                'Invalid record ID.'
            );
        }

        if (!branch_table_has_scope($pdo, $table)) {
            throw new LogicException(
                $table
                . ' is not branch-scoped. Run the Branch Isolation migration.'
            );
        }

        $sql =
            "SELECT *
             FROM `{$table}`
             WHERE `{$idColumn}` = :record_id
               AND tenant_id = :tenant_id
               AND branch_id = :branch_id
             LIMIT 1";

        $stmt = branch_execute_scoped(
            $pdo,
            $sql,
            ['record_id' => $recordId]
        );

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new RuntimeException(
                'Record not found in the selected Branch.',
                404
            );
        }

        return $row;
    }
}

if (!function_exists('branch_accessible_branches')) {
    function branch_accessible_branches(
        PDO $pdo
    ): array {
        $tenantId = branch_current_tenant_id();

        if ($tenantId <= 0) {
            return [];
        }

        /*
         * Super Admin under an explicit school Support Access context,
         * and School Admin, may access every active branch of that school.
         */
        $platform = function_exists(
            'school_session_is_super_admin'
        )
            ? (bool)school_session_is_super_admin()
            : false;

        if ($platform || branch_is_school_admin()) {
            $stmt = $pdo->prepare(
                "SELECT
                    id,
                    branch_code,
                    branch_name,
                    is_main,
                    status
                 FROM branches
                 WHERE tenant_id = :tenant_id
                   AND status = 'active'
                 ORDER BY
                    is_main DESC,
                    branch_name,
                    id"
            );

            $stmt->execute([
                'tenant_id' => $tenantId,
            ]);

            return $stmt->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];
        }

        $userId = (int)(
            $_SESSION['user_id']
            ?? 0
        );

        if ($userId <= 0) {
            return [];
        }

        if (
            function_exists('school_table_exists')
            && school_table_exists(
                $pdo,
                'user_branch_access'
            )
        ) {
            $stmt = $pdo->prepare(
                "SELECT
                    b.id,
                    b.branch_code,
                    b.branch_name,
                    b.is_main,
                    b.status
                 FROM user_branch_access uba
                 INNER JOIN branches b
                    ON b.id = uba.branch_id
                   AND b.tenant_id = :tenant_id
                   AND b.status = 'active'
                 WHERE uba.user_id = :user_id
                   AND uba.can_access = 1
                 ORDER BY
                    b.is_main DESC,
                    b.branch_name,
                    b.id"
            );

            $stmt->execute([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
            ]);

            return $stmt->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];
        }

        $branchId = branch_current_id();

        if ($branchId <= 0) {
            return [];
        }

        $stmt = $pdo->prepare(
            "SELECT
                id,
                branch_code,
                branch_name,
                is_main,
                status
             FROM branches
             WHERE tenant_id = :tenant_id
               AND id = :branch_id
               AND status = 'active'
             LIMIT 1"
        );

        $stmt->execute([
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? [$row] : [];
    }
}

if (!function_exists('branch_set_current_context')) {
    function branch_set_current_context(
        PDO $pdo,
        int $branchId,
        bool $persistAsDefault = true
    ): array {
        $tenantId = branch_current_tenant_id();
        $userId = (int)(
            $_SESSION['user_id']
            ?? 0
        );

        if (
            $tenantId <= 0
            || $branchId <= 0
            || $userId <= 0
        ) {
            throw new InvalidArgumentException(
                'Invalid Branch context.'
            );
        }

        $branchStmt = $pdo->prepare(
            "SELECT
                id,
                tenant_id,
                branch_code,
                branch_name,
                is_main,
                status
             FROM branches
             WHERE id = :branch_id
               AND tenant_id = :tenant_id
               AND status = 'active'
             LIMIT 1"
        );

        $branchStmt->execute([
            'branch_id' => $branchId,
            'tenant_id' => $tenantId,
        ]);

        $branch = $branchStmt->fetch(
            PDO::FETCH_ASSOC
        );

        if (!$branch) {
            throw new RuntimeException(
                'Branch not found for this School.',
                404
            );
        }

        if (
            function_exists('school_user_can_access_branch')
            && !school_user_can_access_branch($branchId)
        ) {
            throw new RuntimeException(
                'You do not have access to this Branch.',
                403
            );
        }

        /*
         * Persisting as users.default_branch_id is intentional in this
         * project's existing Bootstrap. Bootstrap currently reloads branch_id
         * from default_branch_id on every authenticated request.
         */
        if ($persistAsDefault) {
            $update = $pdo->prepare(
                "UPDATE users
                 SET default_branch_id = :branch_id
                 WHERE id = :user_id
                   AND tenant_id = :tenant_id"
            );

            $update->execute([
                'branch_id' => $branchId,
                'user_id' => $userId,
                'tenant_id' => $tenantId,
            ]);
        }

        $_SESSION['branch_id'] =
            (int)$branch['id'];

        $_SESSION['default_branch_id'] =
            (int)$branch['id'];

        $_SESSION['branch_code'] =
            (string)$branch['branch_code'];

        $_SESSION['branch_name'] =
            (string)$branch['branch_name'];

        return $branch;
    }
}


if (!function_exists('branch_insert_scope_values')) {
    function branch_insert_scope_values(
        array $values = [],
        ?PDO $pdo = null
    ): array {
        $scope = branch_require_scope($pdo);

        /*
         * Server-side scope always wins.
         * Never trust tenant_id/branch_id coming from POST/GET.
         */
        $values['tenant_id'] = (int)$scope['tenant_id'];
        $values['branch_id'] = (int)$scope['branch_id'];

        return $values;
    }
}

if (!function_exists('branch_assert_record_scope')) {
    function branch_assert_record_scope(
        PDO $pdo,
        string $table,
        int $recordId,
        string $idColumn = 'id'
    ): void {
        branch_require_record(
            $pdo,
            $table,
            $recordId,
            $idColumn
        );
    }
}

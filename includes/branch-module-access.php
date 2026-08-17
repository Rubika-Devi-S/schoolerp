<?php
declare(strict_types=1);

/*
 * Branch Module Access
 * Location: includes/branch-module-access.php
 * Build: 2026-08-17-active-branch-data-fix-v58
 *
 * Effective permission model:
 * Role Permission AND Branch Module Permission.
 *
 * Branch module OFF is intentionally "Local-only / read-only" for modules
 * that already store branch_id. View/print/pdf/export remain available when
 * the role allows them; create/edit/delete/import and other mutation actions
 * are blocked. Cross-branch data is never permitted by this helper.
 */

if (!function_exists('branch_module_catalog')) {
    /**
     * @return array<string,array<string,mixed>>
     */
    function branch_module_catalog(): array
    {
        return [
            'classes' => [
                'label' => 'Classes',
                'icon' => 'school',
                'aliases' => [
                    'classes',
                    'class_management',
                ],
            ],
            'academic_years' => [
                'label' => 'Academic Years',
                'icon' => 'calendar-range',
                'aliases' => [
                    'academic_years',
                    'academic_year_management',
                    'academic_calendar',
                    'terms_semesters',
                    'holidays',
                ],
            ],
            'subjects' => [
                'label' => 'Subjects',
                'icon' => 'book-open-check',
                'aliases' => [
                    'subjects',
                    'subject_management',
                ],
            ],
            'sections' => [
                'label' => 'Sections',
                'icon' => 'layout-list',
                'aliases' => [
                    'sections',
                    'school_sections',
                    'section_management',
                ],
            ],
            'students' => [
                'label' => 'Students',
                'icon' => 'users',
                'aliases' => [
                    'students',
                    'student_management',
                    'admissions',
                    'student_admission',
                ],
            ],
            'attendance' => [
                'label' => 'Attendance',
                'icon' => 'clipboard-check',
                'aliases' => [
                    'attendance',
                    'student_attendance',
                    'attendance_management',
                ],
            ],
            'fee_structure' => [
                'label' => 'Fee Structure',
                'icon' => 'receipt-indian-rupee',
                'aliases' => [
                    'fee_structure',
                    'fee_structures',
                    'fee_management',
                    'fees',
                ],
            ],
            'transport_fees' => [
                'label' => 'Transport Fees',
                'icon' => 'bus-front',
                'aliases' => [
                    'transport_fees',
                    'transport_fee',
                    'transport_fee_management',
                ],
            ],
            'books' => [
                'label' => 'Books',
                'icon' => 'library-big',
                'aliases' => [
                    'books',
                    'library',
                    'library_books',
                    'book_management',
                ],
            ],
            'exams' => [
                'label' => 'Exams',
                'icon' => 'file-check-2',
                'aliases' => [
                    'exams',
                    'exam_management',
                    'exam_results',
                    'results',
                    'grades',
                ],
            ],
            'timetable' => [
                'label' => 'Timetable',
                'icon' => 'calendar-clock',
                'aliases' => [
                    'timetable',
                    'class_timetable',
                    'timetable_management',
                ],
            ],
            'transport' => [
                'label' => 'Transport',
                'icon' => 'route',
                'aliases' => [
                    'transport',
                    'student_transport',
                    'transport_management',
                    'transport_routes',
                    'transport_vehicles',
                    'vehicle_management',
                ],
            ],
        ];
    }
}

if (!function_exists('branch_module_normalize_key')) {
    function branch_module_normalize_key(string $key): string
    {
        $key = strtolower(trim($key));
        $key = preg_replace('/[^a-z0-9]+/', '_', $key) ?? '';
        return trim($key, '_');
    }
}

if (!function_exists('branch_module_canonical_key')) {
    function branch_module_canonical_key(string $key): string
    {
        $key = branch_module_normalize_key($key);
        if ($key === '') {
            return '';
        }

        foreach (branch_module_catalog() as $moduleKey => $definition) {
            $aliases = array_map(
                'branch_module_normalize_key',
                (array)($definition['aliases'] ?? [])
            );

            if ($key === $moduleKey || in_array($key, $aliases, true)) {
                return $moduleKey;
            }
        }

        return '';
    }
}

if (!function_exists('branch_module_table_exists')) {
    function branch_module_table_exists(PDO $pdo, string $table): bool
    {
        $statement = $pdo->prepare(
            "SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = :table_name"
        );
        $statement->execute(['table_name' => $table]);

        return (int)$statement->fetchColumn() > 0;
    }
}

if (!function_exists('branch_module_ensure_schema')) {
    function branch_module_ensure_schema(PDO $pdo): void
    {
        static $ready = false;

        if ($ready) {
            return;
        }

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS branch_module_visibility (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id BIGINT UNSIGNED NOT NULL,
                branch_id BIGINT UNSIGNED NOT NULL,
                module_key VARCHAR(80) NOT NULL,
                is_enabled TINYINT(1) NOT NULL DEFAULT 1,
                updated_by BIGINT UNSIGNED NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_branch_module_visibility
                    (tenant_id,branch_id,module_key),
                KEY idx_branch_module_visibility
                    (tenant_id,branch_id,is_enabled)
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci"
        );

        $ready = true;
    }
}

if (!function_exists('branch_module_current_scope')) {
    /**
     * @return array{tenant_id:int,branch_id:int,user_id:int}
     */
    function branch_module_current_scope(): array
    {
        $user = function_exists('current_user')
            ? current_user()
            : [];

        $user = is_array($user) ? $user : [];

        $tenantId = (int)(
            $user['tenant_id']
            ?? $user['school_id']
            ?? $_SESSION['tenant_id']
            ?? $_SESSION['school_id']
            ?? 0
        );

        /*
         * The Branch Settings switcher writes the ACTIVE branch to the session.
         * Always prefer that selected branch over current_user()/DB defaults,
         * otherwise Classes/Years/Subjects can silently fall back to the user's
         * original default branch after switching.
         */
        $branchId = (int)($_SESSION['branch_id'] ?? 0);

        if ($branchId <= 0 && function_exists('current_branch_id')) {
            try {
                $branchId = (int)current_branch_id();
            } catch (Throwable) {
                $branchId = 0;
            }
        }

        if ($branchId <= 0 && function_exists('branch_current_id')) {
            try {
                $branchId = (int)branch_current_id();
            } catch (Throwable) {
                $branchId = 0;
            }
        }

        if ($branchId <= 0) {
            $branchId = (int)(
                $_SESSION['default_branch_id']
                ?? $user['branch_id']
                ?? $user['default_branch_id']
                ?? 0
            );
        }

        $userId = (int)(
            $user['id']
            ?? $user['user_id']
            ?? $_SESSION['user_id']
            ?? 0
        );

        return [
            'tenant_id' => max(0, $tenantId),
            'branch_id' => max(0, $branchId),
            'user_id' => max(0, $userId),
        ];
    }
}

if (!function_exists('branch_module_is_enabled')) {
    function branch_module_is_enabled(
        PDO $pdo,
        int $tenantId,
        int $branchId,
        string $moduleKey,
        bool $defaultEnabled = true
    ): bool {
        $moduleKey = branch_module_canonical_key($moduleKey);

        if (
            $moduleKey === ''
            || $tenantId <= 0
            || $branchId <= 0
        ) {
            return $defaultEnabled;
        }

        if (
            !branch_module_table_exists(
                $pdo,
                'branch_module_visibility'
            )
        ) {
            return $defaultEnabled;
        }

        $statement = $pdo->prepare(
            "SELECT is_enabled
             FROM branch_module_visibility
             WHERE tenant_id = :tenant_id
               AND branch_id = :branch_id
               AND module_key = :module_key
             LIMIT 1"
        );

        $statement->execute([
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'module_key' => $moduleKey,
        ]);

        $value = $statement->fetchColumn();

        return $value === false
            ? $defaultEnabled
            : (int)$value === 1;
    }
}

if (!function_exists('branch_module_set_enabled')) {
    function branch_module_set_enabled(
        PDO $pdo,
        int $tenantId,
        int $branchId,
        string $moduleKey,
        bool $enabled,
        ?int $updatedBy = null
    ): void {
        $moduleKey = branch_module_canonical_key($moduleKey);

        if ($moduleKey === '') {
            throw new InvalidArgumentException('Invalid branch module.');
        }

        if ($tenantId <= 0 || $branchId <= 0) {
            throw new InvalidArgumentException('Invalid School or Branch.');
        }

        branch_module_ensure_schema($pdo);

        $statement = $pdo->prepare(
            "INSERT INTO branch_module_visibility
                (
                    tenant_id,
                    branch_id,
                    module_key,
                    is_enabled,
                    updated_by
                )
             VALUES
                (
                    :tenant_id,
                    :branch_id,
                    :module_key,
                    :is_enabled,
                    :updated_by
                )
             ON DUPLICATE KEY UPDATE
                is_enabled = VALUES(is_enabled),
                updated_by = VALUES(updated_by),
                updated_at = CURRENT_TIMESTAMP"
        );

        $statement->execute([
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'module_key' => $moduleKey,
            'is_enabled' => $enabled ? 1 : 0,
            'updated_by' => $updatedBy ?: null,
        ]);
    }
}

if (!function_exists('branch_module_states')) {
    /**
     * @return array<string,array<string,mixed>>
     */
    function branch_module_states(
        PDO $pdo,
        int $tenantId,
        int $branchId
    ): array {
        $catalog = branch_module_catalog();
        $states = [];

        foreach ($catalog as $moduleKey => $definition) {
            $states[$moduleKey] = [
                'module_key' => $moduleKey,
                'label' => (string)($definition['label'] ?? $moduleKey),
                'icon' => (string)($definition['icon'] ?? 'box'),
                'is_enabled' => 1,
            ];
        }

        if ($tenantId <= 0 || $branchId <= 0) {
            return $states;
        }

        branch_module_ensure_schema($pdo);

        $statement = $pdo->prepare(
            "SELECT module_key,is_enabled
             FROM branch_module_visibility
             WHERE tenant_id = :tenant_id
               AND branch_id = :branch_id"
        );

        $statement->execute([
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
        ]);

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $moduleKey = branch_module_canonical_key(
                (string)($row['module_key'] ?? '')
            );

            if ($moduleKey === '' || !isset($states[$moduleKey])) {
                continue;
            }

            $states[$moduleKey]['is_enabled'] =
                (int)($row['is_enabled'] ?? 1) === 1 ? 1 : 0;
        }

        return $states;
    }
}

if (!function_exists('branch_module_is_write_action')) {
    function branch_module_is_write_action(string $action): bool
    {
        $action = branch_module_normalize_key($action);

        if ($action === '') {
            return false;
        }

        return in_array(
            $action,
            [
                'add',
                'create',
                'store',
                'insert',
                'copy',
                'edit',
                'update',
                'status',
                'approve',
                'reject',
                'assign',
                'delete',
                'remove',
                'destroy',
                'trash',
                'import',
                'upload',
                'restore',
                'manage_settings',
                'manage_visibility',
                'full_access',
            ],
            true
        );
    }
}

if (!function_exists('branch_module_filter_capabilities')) {
    /**
     * OFF => local-only/read-only.
     *
     * @param array<string,mixed> $capabilities
     * @return array<string,mixed>
     */
    function branch_module_filter_capabilities(
        array $capabilities,
        bool $moduleEnabled
    ): array {
        if ($moduleEnabled) {
            $capabilities['branch_module_enabled'] = true;
            $capabilities['branch_access_mode'] = 'enabled';
            return $capabilities;
        }

        foreach (
            [
                'add',
                'create',
                'edit',
                'delete',
                'approve',
                'reject',
                'import',
                'restore',
                'manage_settings',
                'manage_visibility',
                'full_access',
            ]
            as $action
        ) {
            if (array_key_exists($action, $capabilities)) {
                $capabilities[$action] = false;
            }

            if (
                isset($capabilities['actions'])
                && is_array($capabilities['actions'])
                && array_key_exists($action, $capabilities['actions'])
            ) {
                $capabilities['actions'][$action] = false;
            }
        }

        $capabilities['branch_module_enabled'] = false;
        $capabilities['branch_access_mode'] = 'local_only';

        return $capabilities;
    }
}

if (!function_exists('branch_module_require_write')) {
    function branch_module_require_write(
        PDO $pdo,
        int $tenantId,
        int $branchId,
        string $moduleKey
    ): void {
        if (
            branch_module_is_enabled(
                $pdo,
                $tenantId,
                $branchId,
                $moduleKey,
                true
            )
        ) {
            return;
        }

        $canonical = branch_module_canonical_key($moduleKey);
        $definition = branch_module_catalog()[$canonical] ?? [];
        $label = (string)($definition['label'] ?? $canonical ?: 'Module');

        throw new RuntimeException(
            $label
            . ' is OFF for the active Branch. '
            . 'Existing current-branch data remains available in local-only mode, '
            . 'but changes are disabled.',
            403
        );
    }
}

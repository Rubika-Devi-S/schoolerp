<?php
declare(strict_types=1);

/*
 * School ERP shared audit writer.
 * Build: 2026-08-15-super-admin-audit-logs-v1
 *
 * Existing table used:
 * activity_logs
 *
 * Use this helper from create/edit/delete/import/export/print/view APIs so the
 * Super Admin Audit Logs page can show the action.
 */

if (!function_exists('schoolerp_audit_normalize_action')) {
    function schoolerp_audit_normalize_action(string $action): string
    {
        $action = strtolower(trim($action));
        $action = preg_replace('/[^a-z0-9]+/', '_', $action) ?: 'action';
        return trim($action, '_') ?: 'action';
    }
}

if (!function_exists('schoolerp_audit_json')) {
    function schoolerp_audit_json(?array $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return json_encode(
            $value,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_INVALID_UTF8_SUBSTITUTE
        ) ?: null;
    }
}

if (!function_exists('schoolerp_audit_log')) {
    function schoolerp_audit_log(
        PDO $pdo,
        string $moduleName,
        string $actionKey,
        ?string $tableName = null,
        ?int $recordId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null,
        ?int $tenantId = null,
        ?int $branchId = null,
        ?int $academicYearId = null,
        ?int $userId = null,
        ?int $roleId = null
    ): bool {
        try {
            if (function_exists('school_table_exists')
                && !school_table_exists($pdo, 'activity_logs')) {
                return false;
            }

            $currentUser = function_exists('current_user')
                ? current_user()
                : [];

            $currentUser = is_array($currentUser)
                ? $currentUser
                : [];

            $tenantId = (int)(
                $tenantId
                ?? $currentUser['tenant_id']
                ?? $currentUser['school_id']
                ?? $_SESSION['tenant_id']
                ?? $_SESSION['school_id']
                ?? 0
            );

            $branchId = (int)(
                $branchId
                ?? $_SESSION['branch_id']
                ?? $currentUser['branch_id']
                ?? $currentUser['default_branch_id']
                ?? $_SESSION['default_branch_id']
                ?? 0
            );

            $userId = (int)(
                $userId
                ?? $currentUser['id']
                ?? $currentUser['user_id']
                ?? $_SESSION['user_id']
                ?? 0
            );

            $roleId = (int)(
                $roleId
                ?? $currentUser['role_id']
                ?? $_SESSION['role_id']
                ?? 0
            );

            if ($tenantId <= 0 && $userId > 0) {
                $userLookup = $pdo->prepare(
                    "SELECT tenant_id, default_branch_id, role_id
                     FROM users
                     WHERE id = :user_id
                     LIMIT 1"
                );
                $userLookup->execute(['user_id' => $userId]);
                $userRow = $userLookup->fetch(PDO::FETCH_ASSOC) ?: [];

                $tenantId = (int)($userRow['tenant_id'] ?? 0);

                if ($branchId <= 0) {
                    $branchId = (int)($userRow['default_branch_id'] ?? 0);
                }

                if ($roleId <= 0) {
                    $roleId = (int)($userRow['role_id'] ?? 0);
                }
            }

            /*
             * activity_logs.tenant_id is NOT NULL in the current database.
             * Do not invent a tenant when no real authenticated context exists.
             */
            if ($tenantId <= 0) {
                return false;
            }

            if ($academicYearId !== null && $academicYearId > 0) {
                if ($newValues === null) {
                    $newValues = [];
                }

                if (!array_key_exists('academic_year_id', $newValues)) {
                    $newValues['academic_year_id'] = $academicYearId;
                }
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
                    user_agent,
                    created_at
                ) VALUES (
                    :tenant_id,
                    :branch_id,
                    :user_id,
                    :role_id,
                    :module_name,
                    :action_key,
                    :table_name,
                    :record_id,
                    :old_values,
                    :new_values,
                    :description,
                    :ip_address,
                    :user_agent,
                    CURRENT_TIMESTAMP
                )"
            );

            return $statement->execute([
                'tenant_id' => $tenantId,
                'branch_id' => $branchId > 0 ? $branchId : null,
                'user_id' => $userId > 0 ? $userId : null,
                'role_id' => $roleId > 0 ? $roleId : null,
                'module_name' => mb_substr(trim($moduleName), 0, 120),
                'action_key' => mb_substr(
                    schoolerp_audit_normalize_action($actionKey),
                    0,
                    80
                ),
                'table_name' => $tableName !== null
                    ? mb_substr(trim($tableName), 0, 120)
                    : null,
                'record_id' => $recordId !== null && $recordId > 0
                    ? $recordId
                    : null,
                'old_values' => schoolerp_audit_json($oldValues),
                'new_values' => schoolerp_audit_json($newValues),
                'description' => $description !== null
                    ? mb_substr(trim($description), 0, 255)
                    : null,
                'ip_address' => mb_substr(
                    (string)($_SERVER['REMOTE_ADDR'] ?? ''),
                    0,
                    45
                ),
                'user_agent' => (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
            ]);
        } catch (Throwable $exception) {
            error_log(
                'schoolerp_audit_log failed: '
                . $exception->getMessage()
            );
            return false;
        }
    }
}

/*
 * Examples:
 *
 * schoolerp_audit_log($pdo, 'Students', 'view', 'students', $studentId);
 *
 * schoolerp_audit_log(
 *     $pdo, 'Students', 'create', 'students', $studentId,
 *     null, $newStudent, 'Created student', null, null, $academicYearId
 * );
 *
 * schoolerp_audit_log(
 *     $pdo, 'Students', 'edit', 'students', $studentId,
 *     $oldStudent, $newStudent, 'Updated student'
 * );
 *
 * schoolerp_audit_log(
 *     $pdo, 'Students', 'delete', 'students', $studentId,
 *     $oldStudent, null, 'Deleted student'
 * );
 *
 * schoolerp_audit_log($pdo, 'Students', 'import', 'students', null, null, [
 *     'file_name' => $fileName,
 *     'imported' => $importedCount,
 * ]);
 *
 * schoolerp_audit_log($pdo, 'Students', 'export', 'students', null, null, [
 *     'format' => 'csv',
 *     'filters' => $filters,
 * ]);
 */

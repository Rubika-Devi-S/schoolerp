<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/ClassManagementModel.php';

final class ClassManagementController
{
    private const PAGE_KEY = 'classes';

    private ClassManagementModel $model;

    public function __construct(
        private PDO $pdo,
        private array $user
    ) {
        $this->model = new ClassManagementModel($pdo);
    }

    public function tenantId(): int
    {
        return (int)(
            $this->user['tenant_id']
            ?? $this->user['school_id']
            ?? $_SESSION['tenant_id']
            ?? $_SESSION['school_id']
            ?? 0
        );
    }

    public function branchId(): int
    {
        return (int)(
            $this->user['branch_id']
            ?? $this->user['default_branch_id']
            ?? $_SESSION['branch_id']
            ?? $_SESSION['default_branch_id']
            ?? 0
        );
    }

    public function userId(): int
    {
        return (int)(
            $this->user['id']
            ?? $this->user['user_id']
            ?? $_SESSION['user_id']
            ?? 0
        );
    }

    /**
     * Kept only for compatibility with older callers and diagnostics.
     * It must never be used to reduce the rows returned to a role.
     */
    public function access(): string
    {
        if (function_exists('is_super_admin') && is_super_admin()) {
            return 'super_admin';
        }

        return 'school_user';
    }

    /**
     * Authorize only by the configured Classes action permission.
     *
     * Roles in the same school use the same tenant data. Role names such as
     * Teacher or Class Teacher do not create a separate data scope and do not
     * silently remove Create/Edit/Delete rights. Those rights come only from
     * the role-permission matrix.
     */
    public function can(string $action, ?array $class = null): bool
    {
        unset($class); // Record assignment must not override configured RBAC.

        if (function_exists('is_super_admin') && is_super_admin()) {
            return true;
        }

        $action = strtolower(trim($action));
        if ($action === '') {
            return false;
        }

        $canonicalAction = match ($action) {
            'add', 'create', 'store', 'insert' => 'create',
            'edit', 'update', 'modify', 'status', 'assign', 'approve', 'reject' => 'edit',
            'delete', 'remove', 'destroy', 'trash' => 'delete',
            default => 'view',
        };

        /*
         * The shared bootstrap permission evaluator is authoritative because it
         * reads the tenant sidebar assignment and the selected role's
         * View/Add/Edit/Delete values.
         */
        if (function_exists('school_effective_permission')) {
            try {
                return (bool)school_effective_permission(
                    self::PAGE_KEY,
                    $canonicalAction,
                    $this->tenantId(),
                    $this->branchId(),
                    $this->userId()
                );
            } catch (Throwable $exception) {
                error_log(
                    'Class permission evaluation failed: '
                    . $exception->getMessage()
                );
                return false;
            }
        }

        /* Compatibility for installations using only has_permission(). */
        if (function_exists('has_permission')) {
            $actions = [$canonicalAction];
            if ($canonicalAction === 'create') {
                $actions[] = 'add';
            }

            foreach (
                ['classes', 'class_management', 'class_section_management']
                as $pageKey
            ) {
                foreach ($actions as $permissionAction) {
                    try {
                        if ((bool)has_permission($pageKey, $permissionAction)) {
                            return true;
                        }
                    } catch (Throwable $exception) {
                        error_log(
                            'Class permission fallback failed for '
                            . $pageKey . '/' . $permissionAction . ': '
                            . $exception->getMessage()
                        );
                    }
                }

                try {
                    if ((bool)has_permission($pageKey, 'full_access')) {
                        return true;
                    }
                } catch (Throwable) {
                    // Continue to fail closed.
                }
            }

            return false;
        }

        return false;
    }

    public function permissions(): array
    {
        $create = $this->can('create');

        return [
            'view' => $this->can('view'),
            'create' => $create,
            'add' => $create,
            'edit' => $this->can('edit'),
            'delete' => $this->can('delete'),
        ];
    }

    public function meta(): array
    {
        $this->requirePermission('view', 'view class metadata');

        return $this->model->meta($this->tenantId());
    }

    public function list(array $filters): array
    {
        $this->requirePermission('view', 'view classes');

        /*
         * The legacy model accepts an access label and filters Teacher rows by
         * class_teacher_user_id. Pass the unrestricted school data scope so all
         * roles in this tenant read the same class records. Actions remain
         * protected by can()/requirePermission().
         */
        return $this->model->list(
            $this->tenantId(),
            $this->userId(),
            'school_admin',
            $filters
        );
    }

    public function save(array $input): array
    {
        $id = (int)($input['id'] ?? 0);
        $existing = $id > 0
            ? $this->model->find($this->tenantId(), $id)
            : null;

        if ($id > 0 && !$existing) {
            throw new RuntimeException('Class not found.', 404);
        }

        $requiredAction = $id > 0 ? 'edit' : 'create';
        $this->requirePermission(
            $requiredAction,
            $id > 0 ? 'edit classes' : 'create classes'
        );

        $data = $this->validate($input);

        if (
            $this->model->duplicate(
                $this->tenantId(),
                $data['academic_year_id'],
                $data['class_name'],
                $data['section_name'],
                $id
            )
        ) {
            throw new InvalidArgumentException(
                'Duplicate class and section in this academic year.'
            );
        }

        $data['id'] = $id;

        return [
            'id' => $this->model->save(
                $this->tenantId(),
                $this->userId(),
                $data
            ),
        ];
    }

    public function delete(int $id): void
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid class ID.');
        }

        if (!$this->model->find($this->tenantId(), $id)) {
            throw new RuntimeException('Class not found.', 404);
        }

        $this->requirePermission('delete', 'delete classes');
        $this->model->delete($this->tenantId(), $id);
    }

    public function related(string $type, int $classId): array
    {
        if (!$this->model->find($this->tenantId(), $classId)) {
            throw new RuntimeException('Class not found.', 404);
        }

        $this->requirePermission('view', 'view class assignments');

        return $this->model->related(
            $this->tenantId(),
            $type,
            $classId
        );
    }

    public function saveRelated(string $type, array $data): int
    {
        $classId = (int)($data['class_id'] ?? 0);
        if (!$this->model->find($this->tenantId(), $classId)) {
            throw new RuntimeException('Class not found.', 404);
        }

        /* Creating an assignment is an Add/Create operation. */
        $this->requirePermission('create', 'add class assignments');

        return $this->model->saveRelated(
            $this->tenantId(),
            $this->userId(),
            $type,
            $data
        );
    }

    public function deleteRelated(string $type, int $id): void
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid assignment ID.');
        }

        $this->requirePermission('delete', 'delete class assignments');

        $this->model->deleteRelated(
            $this->tenantId(),
            $type,
            $id
        );
    }

    private function requirePermission(string $action, string $label): void
    {
        if (!$this->can($action)) {
            throw new RuntimeException(
                'You do not have permission to ' . $label . '.',
                403
            );
        }
    }

    private function validate(array $input): array
    {
        $name = trim((string)($input['class_name'] ?? ''));
        $code = strtoupper(trim((string)($input['class_code'] ?? '')));
        $section = trim((string)($input['section_name'] ?? ''));
        $yearId = (int)($input['academic_year_id'] ?? 0);
        $maximumStrength = (int)($input['maximum_strength'] ?? 0);
        $currentStrength = (int)($input['current_strength'] ?? 0);

        if ($name === '' || mb_strlen($name) > 100) {
            throw new InvalidArgumentException('Enter a valid class name.');
        }

        if (!preg_match('/^[A-Z0-9_-]{2,30}$/', $code)) {
            throw new InvalidArgumentException(
                'Class code must contain 2-30 letters, numbers, hyphens or underscores.'
            );
        }

        if ($yearId <= 0) {
            throw new InvalidArgumentException('Select academic year.');
        }

        if ($section === '' || mb_strlen($section) > 50) {
            throw new InvalidArgumentException('Enter a valid section.');
        }

        if ($maximumStrength < 1 || $maximumStrength > 500) {
            throw new InvalidArgumentException(
                'Maximum strength must be between 1 and 500.'
            );
        }

        if ($currentStrength < 0 || $currentStrength > $maximumStrength) {
            throw new InvalidArgumentException(
                'Current strength cannot exceed maximum strength.'
            );
        }

        $status = strtolower(trim((string)($input['status'] ?? 'active')));
        if (!in_array($status, ['active', 'inactive', 'archived'], true)) {
            $status = 'active';
        }

        return [
            'academic_year_id' => $yearId,
            'class_name' => $name,
            'class_code' => $code,
            'section_name' => $section,
            'medium' => trim((string)($input['medium'] ?? 'English')),
            'shift_name' => trim(
                (string)(
                    $input['shift_name']
                    ?? $input['shift']
                    ?? 'General'
                )
            ),
            'class_teacher_user_id' => (int)(
                $input['class_teacher_user_id'] ?? 0
            ),
            'class_teacher_name' => trim(
                (string)($input['class_teacher_name'] ?? '')
            ),
            'classroom_name' => trim(
                (string)($input['classroom_name'] ?? '')
            ),
            'maximum_strength' => $maximumStrength,
            'current_strength' => $currentStrength,
            'status' => $status,
            'description' => trim((string)($input['description'] ?? '')),
            'display_order' => max(
                0,
                (int)($input['display_order'] ?? 0)
            ),
        ];
    }
}

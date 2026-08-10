<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/SectionsManagementModel.php';

final class SectionsManagementController
{
    private SectionsManagementModel $model;

    public function __construct(
        private PDO $pdo,
        private array $user
    ) {
        $this->model = new SectionsManagementModel($pdo);
    }

    public function tenantId(): int
    {
        return (int)(
            $this->user['tenant_id']
            ?? $_SESSION['tenant_id']
            ?? $_SESSION['school_id']
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

    public function can(string $action): bool
    {
        if (
            function_exists('is_super_admin')
            && is_super_admin()
        ) {
            return true;
        }

        if (!function_exists('has_permission')) {
            return true;
        }

        return has_permission('sections', $action)
            || has_permission(
                'section_management',
                $action
            )
            || has_permission(
                'class_section_management',
                $action
            )
            || has_permission(
                'academic_management',
                $action
            );
    }

    public function list(array $filters): array
    {
        if (!$this->can('view')) {
            throw new RuntimeException(
                'You do not have permission to view sections.',
                403
            );
        }

        return $this->model->listSections(
            $this->tenantId(),
            $filters
        );
    }

    public function meta(): array
    {
        if (!$this->can('view')) {
            throw new RuntimeException(
                'You do not have permission to view sections.',
                403
            );
        }

        return $this->model->meta(
            $this->tenantId()
        );
    }

    public function save(array $input): array
    {
        $id = (int)($input['id'] ?? 0);
        $action = $id > 0 ? 'edit' : 'add';

        if (!$this->can($action)) {
            throw new RuntimeException(
                'You do not have permission for this section action.',
                403
            );
        }

        $data = $this->validate($input);

        if (
            $this->model->duplicateExists(
                $this->tenantId(),
                $data['academic_year_id'],
                $data['class_id'],
                $data['section_name'],
                $id
            )
        ) {
            throw new InvalidArgumentException(
                'The same section already exists for this class and academic year.'
            );
        }

        if (
            $this->model->sectionCodeExists(
                $this->tenantId(),
                $data['academic_year_id'],
                $data['section_code'],
                $id
            )
        ) {
            throw new InvalidArgumentException(
                'Section code already exists in this academic year.'
            );
        }

        if ($id > 0) {
            $this->model->updateSection(
                $this->tenantId(),
                $this->userId(),
                $id,
                $data
            );
        } else {
            $id = $this->model->createSection(
                $this->tenantId(),
                $this->userId(),
                $data
            );
        }

        return [
            'id' => $id,
        ];
    }

    public function delete(int $id): void
    {
        if (!$this->can('delete')) {
            throw new RuntimeException(
                'You do not have permission to delete sections.',
                403
            );
        }

        $record = $this->model->findSection(
            $this->tenantId(),
            $id
        );

        if (!$record) {
            throw new RuntimeException(
                'Section not found.',
                404
            );
        }

        $this->model->deleteSection(
            $this->tenantId(),
            $id
        );
    }

    public function related(
        string $type,
        int $sectionId
    ): array {
        if (!$this->can('view')) {
            throw new RuntimeException(
                'Permission denied.',
                403
            );
        }

        return $this->model->related(
            $this->tenantId(),
            $sectionId,
            $type
        );
    }

    public function saveRelated(
        string $type,
        array $input
    ): int {
        if (!$this->can('edit')) {
            throw new RuntimeException(
                'You do not have permission to manage section assignments.',
                403
            );
        }

        return $this->model->saveRelated(
            $this->tenantId(),
            $this->userId(),
            $type,
            $input
        );
    }

    public function deleteRelated(
        string $type,
        int $id
    ): void {
        if (!$this->can('edit')) {
            throw new RuntimeException(
                'You do not have permission to manage section assignments.',
                403
            );
        }

        $this->model->deleteRelated(
            $this->tenantId(),
            $type,
            $id
        );
    }

    private function validate(array $input): array
    {
        $sectionName = trim(
            (string)($input['section_name'] ?? '')
        );

        $sectionCode = strtoupper(
            trim((string)($input['section_code'] ?? ''))
        );

        $classId = (int)($input['class_id'] ?? 0);
        $academicYearId =
            (int)($input['academic_year_id'] ?? 0);

        $capacity = (int)(
            $input['maximum_student_capacity'] ?? 0
        );

        if (
            $sectionName === ''
            || mb_strlen($sectionName) > 100
        ) {
            throw new InvalidArgumentException(
                'Enter a valid section name.'
            );
        }

        if (
            !preg_match(
                '/^[A-Z0-9_-]{1,30}$/',
                $sectionCode
            )
        ) {
            throw new InvalidArgumentException(
                'Section code may contain uppercase letters, numbers, underscore and hyphen.'
            );
        }

        if ($classId <= 0) {
            throw new InvalidArgumentException(
                'Select a class.'
            );
        }

        if ($academicYearId <= 0) {
            throw new InvalidArgumentException(
                'Select an academic year.'
            );
        }

        if ($capacity <= 0 || $capacity > 500) {
            throw new InvalidArgumentException(
                'Maximum student capacity must be between 1 and 500.'
            );
        }

        $status = strtolower(
            trim((string)($input['status'] ?? 'active'))
        );

        if (
            !in_array(
                $status,
                ['active', 'inactive', 'archived'],
                true
            )
        ) {
            $status = 'active';
        }

        return [
            'academic_year_id' => $academicYearId,
            'class_id' => $classId,
            'class_name_snapshot' => trim(
                (string)($input['class_name_snapshot'] ?? '')
            ),
            'section_name' => $sectionName,
            'section_code' => $sectionCode,
            'medium' => trim(
                (string)($input['medium'] ?? 'English')
            ),
            'shift_name' => trim(
                (string)($input['shift_name'] ?? 'General')
            ),
            'room_number' => trim(
                (string)($input['room_number'] ?? '')
            ),
            'maximum_student_capacity' => $capacity,
            'class_teacher_user_id' =>
                (int)($input['class_teacher_user_id'] ?? 0),
            'class_teacher_name' => trim(
                (string)($input['class_teacher_name'] ?? '')
            ),
            'status' => $status,
            'description' => trim(
                (string)($input['description'] ?? '')
            ),
            'display_order' => max(
                0,
                (int)($input['display_order'] ?? 0)
            ),
        ];
    }
}

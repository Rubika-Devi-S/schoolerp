<?php
declare(strict_types=1);

final class SectionsManagementModel
{
    public function __construct(private PDO $pdo)
    {
    }

    private function tableExists(string $table): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = :table_name"
        );

        $statement->execute([
            'table_name' => $table,
        ]);

        return (int)$statement->fetchColumn() > 0;
    }

    private function columnExists(
        string $table,
        string $column
    ): bool {
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = :table_name
               AND column_name = :column_name"
        );

        $statement->execute([
            'table_name' => $table,
            'column_name' => $column,
        ]);

        return (int)$statement->fetchColumn() > 0;
    }

    public function listSections(
        int $tenantId,
        array $filters = []
    ): array {
        $where = [
            's.tenant_id = :tenant_id',
        ];

        $params = [
            'tenant_id' => $tenantId,
        ];

        $search = trim((string)($filters['search'] ?? ''));

        if ($search !== '') {
            $where[] = "(
                s.section_name LIKE :search_name
                OR s.section_code LIKE :search_code
                OR s.room_number LIKE :search_room
                OR s.class_teacher_name LIKE :search_teacher
                OR s.class_name_snapshot LIKE :search_class
            )";

            $like = '%' . $search . '%';

            $params['search_name'] = $like;
            $params['search_code'] = $like;
            $params['search_room'] = $like;
            $params['search_teacher'] = $like;
            $params['search_class'] = $like;
        }

        foreach (
            [
                'academic_year_id',
                'class_id',
                'medium',
                'shift_name',
                'status',
            ] as $field
        ) {
            $value = trim((string)($filters[$field] ?? ''));

            if ($value !== '' && $value !== 'all') {
                $where[] = "s.{$field} = :{$field}";
                $params[$field] = $value;
            }
        }

        $statement = $this->pdo->prepare(
            "SELECT
                s.*,
                ay.year_name AS academic_year_name,
                (
                    SELECT COUNT(*)
                    FROM section_subject_assignments ssa
                    WHERE ssa.section_id = s.id
                      AND ssa.status = 'active'
                ) AS assigned_subject_count,
                (
                    SELECT COUNT(*)
                    FROM section_timetable_assignments sta
                    WHERE sta.section_id = s.id
                      AND sta.status = 'active'
                ) AS timetable_assignment_count
             FROM school_sections s
             LEFT JOIN academic_years ay
                ON ay.id = s.academic_year_id
               AND ay.tenant_id = s.tenant_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY
                ay.start_date DESC,
                s.display_order,
                s.class_name_snapshot,
                s.section_name,
                s.id DESC"
        );

        $statement->execute($params);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findSection(
        int $tenantId,
        int $id
    ): ?array {
        $statement = $this->pdo->prepare(
            "SELECT
                s.*,
                ay.year_name AS academic_year_name
             FROM school_sections s
             LEFT JOIN academic_years ay
                ON ay.id = s.academic_year_id
               AND ay.tenant_id = s.tenant_id
             WHERE s.id = :id
               AND s.tenant_id = :tenant_id
             LIMIT 1"
        );

        $statement->execute([
            'id' => $id,
            'tenant_id' => $tenantId,
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function duplicateExists(
        int $tenantId,
        int $academicYearId,
        int $classId,
        string $sectionName,
        int $excludeId = 0
    ): bool {
        $sql = "SELECT id
                FROM school_sections
                WHERE tenant_id = :tenant_id
                  AND academic_year_id = :academic_year_id
                  AND class_id = :class_id
                  AND LOWER(TRIM(section_name))
                      = LOWER(TRIM(:section_name))";

        $params = [
            'tenant_id' => $tenantId,
            'academic_year_id' => $academicYearId,
            'class_id' => $classId,
            'section_name' => $sectionName,
        ];

        if ($excludeId > 0) {
            $sql .= ' AND id <> :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $sql .= ' LIMIT 1';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return (bool)$statement->fetchColumn();
    }

    public function sectionCodeExists(
        int $tenantId,
        int $academicYearId,
        string $sectionCode,
        int $excludeId = 0
    ): bool {
        $sql = "SELECT id
                FROM school_sections
                WHERE tenant_id = :tenant_id
                  AND academic_year_id = :academic_year_id
                  AND section_code = :section_code";

        $params = [
            'tenant_id' => $tenantId,
            'academic_year_id' => $academicYearId,
            'section_code' => $sectionCode,
        ];

        if ($excludeId > 0) {
            $sql .= ' AND id <> :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $sql .= ' LIMIT 1';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return (bool)$statement->fetchColumn();
    }

    public function createSection(
        int $tenantId,
        int $userId,
        array $data
    ): int {
        $statement = $this->pdo->prepare(
            "INSERT INTO school_sections (
                tenant_id,
                academic_year_id,
                class_id,
                class_name_snapshot,
                section_name,
                section_code,
                medium,
                shift_name,
                room_number,
                maximum_student_capacity,
                class_teacher_user_id,
                class_teacher_name,
                status,
                description,
                display_order,
                created_by
             ) VALUES (
                :tenant_id,
                :academic_year_id,
                :class_id,
                :class_name_snapshot,
                :section_name,
                :section_code,
                :medium,
                :shift_name,
                :room_number,
                :maximum_student_capacity,
                :class_teacher_user_id,
                :class_teacher_name,
                :status,
                :description,
                :display_order,
                :created_by
             )"
        );

        $statement->execute([
            'tenant_id' => $tenantId,
            'academic_year_id' => $data['academic_year_id'],
            'class_id' => $data['class_id'],
            'class_name_snapshot' => $data['class_name_snapshot'],
            'section_name' => $data['section_name'],
            'section_code' => $data['section_code'],
            'medium' => $data['medium'],
            'shift_name' => $data['shift_name'],
            'room_number' => $data['room_number'],
            'maximum_student_capacity' =>
                $data['maximum_student_capacity'],
            'class_teacher_user_id' =>
                $data['class_teacher_user_id'] ?: null,
            'class_teacher_name' => $data['class_teacher_name'],
            'status' => $data['status'],
            'description' => $data['description'],
            'display_order' => $data['display_order'],
            'created_by' => $userId,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function updateSection(
        int $tenantId,
        int $userId,
        int $id,
        array $data
    ): void {
        $statement = $this->pdo->prepare(
            "UPDATE school_sections SET
                academic_year_id = :academic_year_id,
                class_id = :class_id,
                class_name_snapshot = :class_name_snapshot,
                section_name = :section_name,
                section_code = :section_code,
                medium = :medium,
                shift_name = :shift_name,
                room_number = :room_number,
                maximum_student_capacity =
                    :maximum_student_capacity,
                class_teacher_user_id =
                    :class_teacher_user_id,
                class_teacher_name = :class_teacher_name,
                status = :status,
                description = :description,
                display_order = :display_order,
                updated_by = :updated_by,
                updated_at = CURRENT_TIMESTAMP
             WHERE id = :id
               AND tenant_id = :tenant_id"
        );

        $statement->execute([
            'academic_year_id' => $data['academic_year_id'],
            'class_id' => $data['class_id'],
            'class_name_snapshot' => $data['class_name_snapshot'],
            'section_name' => $data['section_name'],
            'section_code' => $data['section_code'],
            'medium' => $data['medium'],
            'shift_name' => $data['shift_name'],
            'room_number' => $data['room_number'],
            'maximum_student_capacity' =>
                $data['maximum_student_capacity'],
            'class_teacher_user_id' =>
                $data['class_teacher_user_id'] ?: null,
            'class_teacher_name' => $data['class_teacher_name'],
            'status' => $data['status'],
            'description' => $data['description'],
            'display_order' => $data['display_order'],
            'updated_by' => $userId,
            'id' => $id,
            'tenant_id' => $tenantId,
        ]);
    }

    public function deleteSection(
        int $tenantId,
        int $id
    ): void {
        $statement = $this->pdo->prepare(
            "DELETE FROM school_sections
             WHERE id = :id
               AND tenant_id = :tenant_id"
        );

        $statement->execute([
            'id' => $id,
            'tenant_id' => $tenantId,
        ]);
    }

    public function meta(int $tenantId): array
    {
        $years = $this->pdo->prepare(
            "SELECT
                id,
                year_name,
                is_current,
                status
             FROM academic_years
             WHERE tenant_id = :tenant_id
             ORDER BY is_current DESC, start_date DESC"
        );

        $years->execute([
            'tenant_id' => $tenantId,
        ]);

        return [
            'academic_years' =>
                $years->fetchAll(PDO::FETCH_ASSOC),
            'classes' => $this->loadClasses($tenantId),
            'teachers' => $this->loadTeachers($tenantId),
            'subjects' => $this->loadSubjects($tenantId),
            'timetables' => $this->loadTimetables($tenantId),
            'mediums' => [
                'English',
                'Tamil',
                'Hindi',
                'Kannada',
                'Other',
            ],
            'shifts' => [
                'Morning',
                'General',
                'Evening',
            ],
        ];
    }

    private function loadClasses(int $tenantId): array
    {
        if ($this->tableExists('class_management_classes')) {
            $statement = $this->pdo->prepare(
                "SELECT
                    id,
                    class_name,
                    class_code,
                    academic_year_id,
                    status
                 FROM class_management_classes
                 WHERE tenant_id = :tenant_id
                   AND status <> 'archived'
                 ORDER BY class_name, class_code"
            );

            $statement->execute([
                'tenant_id' => $tenantId,
            ]);

            return $statement->fetchAll(PDO::FETCH_ASSOC);
        }

        if ($this->tableExists('classes')) {
            $nameColumn = $this->columnExists(
                'classes',
                'class_name'
            )
                ? 'class_name'
                : (
                    $this->columnExists('classes', 'name')
                        ? 'name'
                        : 'id'
                );

            $codeColumn = $this->columnExists(
                'classes',
                'class_code'
            )
                ? 'class_code'
                : (
                    $this->columnExists('classes', 'code')
                        ? 'code'
                        : "''"
                );

            $tenantWhere = $this->columnExists(
                'classes',
                'tenant_id'
            )
                ? 'WHERE tenant_id = :tenant_id'
                : '';

            $statement = $this->pdo->prepare(
                "SELECT
                    id,
                    {$nameColumn} AS class_name,
                    {$codeColumn} AS class_code,
                    NULL AS academic_year_id,
                    'active' AS status
                 FROM classes
                 {$tenantWhere}
                 ORDER BY class_name"
            );

            $statement->execute(
                $tenantWhere !== ''
                    ? ['tenant_id' => $tenantId]
                    : []
            );

            return $statement->fetchAll(PDO::FETCH_ASSOC);
        }

        return [];
    }

    private function loadTeachers(int $tenantId): array
    {
        if (!$this->tableExists('users')) {
            return [];
        }

        $nameExpressions = [];

        foreach (
            [
                'display_name',
                'full_name',
                'name',
                'username',
                'email',
            ] as $column
        ) {
            if ($this->columnExists('users', $column)) {
                $nameExpressions[] =
                    "NULLIF({$column}, '')";
            }
        }

        $nameSql = $nameExpressions !== []
            ? 'COALESCE('
                . implode(', ', $nameExpressions)
                . ", CONCAT('User #', id))"
            : "CONCAT('User #', id)";

        $where = [];

        $params = [];

        if ($this->columnExists('users', 'tenant_id')) {
            $where[] = 'tenant_id = :tenant_id';
            $params['tenant_id'] = $tenantId;
        }

        if ($this->columnExists('users', 'status')) {
            $where[] = "status = 'active'";
        }

        $sql = "SELECT
                    id,
                    {$nameSql} AS teacher_name
                FROM users";

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY teacher_name';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function loadSubjects(int $tenantId): array
    {
        if (!$this->tableExists('subjects')) {
            return [];
        }

        $nameColumn = $this->columnExists(
            'subjects',
            'subject_name'
        )
            ? 'subject_name'
            : (
                $this->columnExists('subjects', 'name')
                    ? 'name'
                    : 'id'
            );

        $codeColumn = $this->columnExists(
            'subjects',
            'subject_code'
        )
            ? 'subject_code'
            : (
                $this->columnExists('subjects', 'code')
                    ? 'code'
                    : "''"
            );

        $where = [];
        $params = [];

        if ($this->columnExists('subjects', 'tenant_id')) {
            $where[] = 'tenant_id = :tenant_id';
            $params['tenant_id'] = $tenantId;
        }

        $sql = "SELECT
                    id,
                    {$nameColumn} AS subject_name,
                    {$codeColumn} AS subject_code
                FROM subjects";

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY subject_name';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function loadTimetables(int $tenantId): array
    {
        $table = null;

        foreach (
            [
                'class_timetable_entries',
                'timetable_entries',
                'timetables',
            ] as $candidate
        ) {
            if ($this->tableExists($candidate)) {
                $table = $candidate;
                break;
            }
        }

        if ($table === null) {
            return [];
        }

        $titleColumn = null;

        foreach (
            [
                'title',
                'timetable_name',
                'name',
                'day_name',
            ] as $column
        ) {
            if ($this->columnExists($table, $column)) {
                $titleColumn = $column;
                break;
            }
        }

        $titleSql = $titleColumn !== null
            ? $titleColumn
            : "CONCAT('Timetable #', id)";

        $where = [];
        $params = [];

        if ($this->columnExists($table, 'tenant_id')) {
            $where[] = 'tenant_id = :tenant_id';
            $params['tenant_id'] = $tenantId;
        }

        $sql = "SELECT
                    id,
                    {$titleSql} AS timetable_name
                FROM {$table}";

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY id DESC';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function related(
        int $tenantId,
        int $sectionId,
        string $type
    ): array {
        $maps = [
            'subjects' => [
                'table' => 'section_subject_assignments',
                'columns' =>
                    'id, subject_id, subject_name, subject_code, status',
            ],
            'timetables' => [
                'table' => 'section_timetable_assignments',
                'columns' =>
                    'id, timetable_id, timetable_name, status',
            ],
        ];

        if (!isset($maps[$type])) {
            return [];
        }

        $map = $maps[$type];

        $statement = $this->pdo->prepare(
            "SELECT {$map['columns']}
             FROM {$map['table']}
             WHERE tenant_id = :tenant_id
               AND section_id = :section_id
             ORDER BY id DESC"
        );

        $statement->execute([
            'tenant_id' => $tenantId,
            'section_id' => $sectionId,
        ]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveRelated(
        int $tenantId,
        int $userId,
        string $type,
        array $data
    ): int {
        $sectionId = (int)($data['section_id'] ?? 0);

        if ($sectionId <= 0) {
            throw new InvalidArgumentException(
                'Select a section first.'
            );
        }

        if ($type === 'subjects') {
            $statement = $this->pdo->prepare(
                "INSERT INTO section_subject_assignments (
                    tenant_id,
                    section_id,
                    subject_id,
                    subject_name,
                    subject_code,
                    status,
                    created_by
                 ) VALUES (
                    :tenant_id,
                    :section_id,
                    :subject_id,
                    :subject_name,
                    :subject_code,
                    'active',
                    :created_by
                 )
                 ON DUPLICATE KEY UPDATE
                    subject_name = VALUES(subject_name),
                    subject_code = VALUES(subject_code),
                    status = 'active',
                    updated_at = CURRENT_TIMESTAMP"
            );

            $statement->execute([
                'tenant_id' => $tenantId,
                'section_id' => $sectionId,
                'subject_id' =>
                    (int)($data['subject_id'] ?? 0) ?: null,
                'subject_name' =>
                    trim((string)($data['subject_name'] ?? '')),
                'subject_code' =>
                    trim((string)($data['subject_code'] ?? '')),
                'created_by' => $userId,
            ]);

            return (int)$this->pdo->lastInsertId();
        }

        if ($type === 'timetables') {
            $statement = $this->pdo->prepare(
                "INSERT INTO section_timetable_assignments (
                    tenant_id,
                    section_id,
                    timetable_id,
                    timetable_name,
                    status,
                    created_by
                 ) VALUES (
                    :tenant_id,
                    :section_id,
                    :timetable_id,
                    :timetable_name,
                    'active',
                    :created_by
                 )
                 ON DUPLICATE KEY UPDATE
                    timetable_name = VALUES(timetable_name),
                    status = 'active',
                    updated_at = CURRENT_TIMESTAMP"
            );

            $statement->execute([
                'tenant_id' => $tenantId,
                'section_id' => $sectionId,
                'timetable_id' =>
                    (int)($data['timetable_id'] ?? 0) ?: null,
                'timetable_name' =>
                    trim((string)($data['timetable_name'] ?? '')),
                'created_by' => $userId,
            ]);

            return (int)$this->pdo->lastInsertId();
        }

        throw new InvalidArgumentException(
            'Invalid section assignment type.'
        );
    }

    public function deleteRelated(
        int $tenantId,
        string $type,
        int $id
    ): void {
        $tables = [
            'subjects' => 'section_subject_assignments',
            'timetables' => 'section_timetable_assignments',
        ];

        if (!isset($tables[$type])) {
            throw new InvalidArgumentException(
                'Invalid section assignment type.'
            );
        }

        $statement = $this->pdo->prepare(
            "DELETE FROM {$tables[$type]}
             WHERE id = :id
               AND tenant_id = :tenant_id"
        );

        $statement->execute([
            'id' => $id,
            'tenant_id' => $tenantId,
        ]);
    }
}

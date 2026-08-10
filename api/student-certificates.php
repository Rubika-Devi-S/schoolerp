<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

$originalScriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
$_SERVER['SCRIPT_NAME'] = '/login.php';

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$_SERVER['SCRIPT_NAME'] = $originalScriptName;

function certificateJson(
    bool $success,
    string $message = '',
    array $data = [],
    int $status = 200
): never {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }

    echo json_encode(
        [
            'success' => $success,
            'message' => $message,
            'data' => $data,
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function certificateInput(): array
{
    $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));

    if (str_contains($contentType, 'application/json')) {
        $decoded = json_decode(
            (string)file_get_contents('php://input'),
            true
        );
        return is_array($decoded) ? $decoded : [];
    }

    return $_POST;
}

function certificateUser(): array
{
    $user = function_exists('current_user') ? current_user() : [];
    return is_array($user) ? $user : [];
}

function certificateScope(array $user): array
{
    return [
        'tenant_id' => (int)(
            $user['tenant_id']
            ?? $user['school_id']
            ?? $_SESSION['tenant_id']
            ?? $_SESSION['school_id']
            ?? 0
        ),
        'branch_id' => (int)(
            $user['branch_id']
            ?? $_SESSION['branch_id']
            ?? 0
        ),
        'user_id' => (int)(
            $user['id']
            ?? $user['user_id']
            ?? $_SESSION['user_id']
            ?? 0
        ),
    ];
}

function certificateCan(string $action): bool
{
    if (function_exists('is_super_admin') && is_super_admin()) {
        return true;
    }

    if (!function_exists('has_permission')) {
        return true;
    }

    $action = strtolower(trim($action));
    $canonical = $action === 'add' ? 'create' : $action;
    $aliases = [$canonical];

    if ($canonical === 'create') {
        $aliases[] = 'add';
    }

    if ($canonical === 'issue') {
        $aliases[] = 'edit';
    }

    if ($canonical === 'cancel') {
        $aliases[] = 'edit';
    }

    foreach (
        [
            'student_certificates',
            'certificates',
            'student_management',
            'students',
        ] as $module
    ) {
        foreach ($aliases as $permissionAction) {
            if (has_permission($module, $permissionAction)) {
                return true;
            }
        }

        if (has_permission($module, 'full_access')) {
            return true;
        }
    }

    return false;
}

function certificateCsrf(array $input): void
{
    $token = trim((string)($input['csrf_token'] ?? ''));

    $valid = function_exists('csrf_is_valid')
        ? csrf_is_valid($token)
        : (
            isset($_SESSION['csrf_token'])
            && is_string($_SESSION['csrf_token'])
            && hash_equals((string)$_SESSION['csrf_token'], $token)
        );

    if (!$valid) {
        certificateJson(false, 'Invalid or expired CSRF token.', [], 419);
    }
}

function certificateTableExists(PDO $pdo, string $table): bool
{
    static $cache = [];

    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    $statement = $pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = :table_name"
    );
    $statement->execute(['table_name' => $table]);

    return $cache[$table] = (int)$statement->fetchColumn() > 0;
}

function certificateColumnExists(
    PDO $pdo,
    string $table,
    string $column
): bool {
    static $cache = [];
    $key = $table . '.' . $column;

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $statement = $pdo->prepare(
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

    return $cache[$key] = (int)$statement->fetchColumn() > 0;
}

function certificateEnsureSchema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS student_certificates (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id BIGINT UNSIGNED NOT NULL,
            branch_id BIGINT UNSIGNED NULL,
            student_id BIGINT UNSIGNED NOT NULL,
            academic_year_id BIGINT UNSIGNED NULL,
            class_id BIGINT UNSIGNED NULL,
            section_id BIGINT UNSIGNED NULL,
            certificate_no VARCHAR(60) NOT NULL,
            certificate_type VARCHAR(40) NOT NULL,
            certificate_title VARCHAR(180) NULL,
            purpose VARCHAR(255) NULL,
            conduct_text VARCHAR(255) NULL,
            custom_body TEXT NULL,
            remarks TEXT NULL,
            issue_date DATE NOT NULL,
            status ENUM('draft','issued','cancelled')
                NOT NULL DEFAULT 'draft',
            issued_by BIGINT UNSIGNED NULL,
            issued_at DATETIME NULL,
            cancelled_by BIGINT UNSIGNED NULL,
            cancelled_at DATETIME NULL,
            cancellation_reason VARCHAR(500) NULL,
            created_by BIGINT UNSIGNED NULL,
            updated_by BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_student_certificate_no
                (tenant_id, certificate_no),
            KEY idx_student_certificate_student
                (tenant_id, student_id, issue_date),
            KEY idx_student_certificate_filters
                (tenant_id, certificate_type, status, issue_date)
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS student_certificate_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id BIGINT UNSIGNED NOT NULL,
            certificate_id BIGINT UNSIGNED NOT NULL,
            student_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            action_name VARCHAR(50) NOT NULL,
            description VARCHAR(500) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_student_certificate_log
                (tenant_id, certificate_id, created_at)
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci"
    );
}

function certificateTypes(): array
{
    return [
        'bonafide' => 'Bonafide Certificate',
        'study' => 'Study Certificate',
        'conduct' => 'Conduct Certificate',
        'transfer' => 'Transfer Certificate',
        'custom' => 'Custom Certificate',
    ];
}

function certificateTypePrefix(string $type): string
{
    return match ($type) {
        'study' => 'STU',
        'conduct' => 'CON',
        'transfer' => 'TC',
        'custom' => 'CUS',
        default => 'BON',
    };
}

function certificateCurrentAcademicYearId(
    PDO $pdo,
    int $tenantId
): int {
    if (!certificateTableExists($pdo, 'academic_years')) {
        return 0;
    }

    $statement = $pdo->prepare(
        "SELECT id
         FROM academic_years
         WHERE tenant_id = :tenant_id
         ORDER BY is_current DESC,
                  status = 'active' DESC,
                  start_date DESC,
                  id DESC
         LIMIT 1"
    );
    $statement->execute(['tenant_id' => $tenantId]);

    return (int)$statement->fetchColumn();
}

function certificateLatestEnrollmentJoinSql(): string
{
    return "
        LEFT JOIN student_enrollments se
          ON se.id = (
                SELECT se2.id
                FROM student_enrollments se2
                WHERE se2.student_id = s.id
                  AND se2.tenant_id = s.tenant_id
                ORDER BY
                    (se2.enrollment_status = 'active') DESC,
                    se2.academic_year_id DESC,
                    se2.id DESC
                LIMIT 1
          )
        LEFT JOIN academic_years ay
          ON ay.id = se.academic_year_id
         AND ay.tenant_id = s.tenant_id
        LEFT JOIN classes c
          ON c.id = se.class_id
         AND c.tenant_id = s.tenant_id
        LEFT JOIN sections sec
          ON sec.id = se.section_id
         AND sec.tenant_id = s.tenant_id
    ";
}

function certificateStudents(PDO $pdo, array $scope): array
{
    $where = [
        's.tenant_id = :tenant_id',
    ];
    $params = [
        'tenant_id' => $scope['tenant_id'],
    ];

    if ($scope['branch_id'] > 0) {
        $where[] = 's.branch_id = :branch_id';
        $params['branch_id'] = $scope['branch_id'];
    }

    if (certificateColumnExists($pdo, 'students', 'deleted_at')) {
        $where[] = 's.deleted_at IS NULL';
    }

    /*
     * One row is returned for each student's enrollment. This allows the
     * Certificate form to filter students by Academic Year -> Class ->
     * Section without mixing students from other classes.
     */
    $statement = $pdo->prepare(
        "SELECT
            s.id,
            s.admission_no,
            TRIM(CONCAT_WS(' ', s.first_name, s.last_name))
                AS student_name,
            s.status,
            s.branch_id,
            se.academic_year_id,
            ay.year_name AS academic_year_name,
            se.class_id,
            c.class_name,
            se.section_id,
            sec.section_name,
            se.roll_no,
            se.enrollment_status
         FROM students s
         INNER JOIN student_enrollments se
            ON se.student_id = s.id
           AND se.tenant_id = s.tenant_id
         INNER JOIN academic_years ay
            ON ay.id = se.academic_year_id
           AND ay.tenant_id = s.tenant_id
         INNER JOIN classes c
            ON c.id = se.class_id
           AND c.tenant_id = s.tenant_id
         INNER JOIN sections sec
            ON sec.id = se.section_id
           AND sec.tenant_id = s.tenant_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY
            ay.is_current DESC,
            ay.start_date DESC,
            c.display_order,
            c.class_name,
            sec.section_name,
            s.status = 'active' DESC,
            student_name,
            s.admission_no"
    );
    $statement->execute($params);

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function certificateClasses(PDO $pdo, array $scope): array
{
    if (
        !certificateTableExists($pdo, 'classes')
        || !certificateTableExists($pdo, 'student_enrollments')
    ) {
        return [];
    }

    $where = [
        'c.tenant_id = :tenant_id',
    ];
    $params = [
        'tenant_id' => $scope['tenant_id'],
    ];

    if ($scope['branch_id'] > 0) {
        $where[] = "EXISTS (
            SELECT 1
            FROM student_enrollments e
            INNER JOIN students s
               ON s.id = e.student_id
              AND s.tenant_id = e.tenant_id
            WHERE e.tenant_id = c.tenant_id
              AND e.academic_year_id = c.academic_year_id
              AND e.class_id = c.id
              AND s.branch_id = :branch_id
        )";
        $params['branch_id'] = $scope['branch_id'];
    }

    $statement = $pdo->prepare(
        "SELECT DISTINCT
            c.id,
            c.academic_year_id,
            c.class_name,
            c.display_order
         FROM classes c
         WHERE " . implode(' AND ', $where) . "
         ORDER BY c.academic_year_id DESC,
                  c.display_order,
                  c.class_name"
    );
    $statement->execute($params);

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function certificateSections(PDO $pdo, array $scope): array
{
    if (
        !certificateTableExists($pdo, 'sections')
        || !certificateTableExists($pdo, 'classes')
        || !certificateTableExists($pdo, 'student_enrollments')
    ) {
        return [];
    }

    $where = [
        'sec.tenant_id = :tenant_id',
    ];
    $params = [
        'tenant_id' => $scope['tenant_id'],
    ];

    if ($scope['branch_id'] > 0) {
        $where[] = "EXISTS (
            SELECT 1
            FROM student_enrollments e
            INNER JOIN students s
               ON s.id = e.student_id
              AND s.tenant_id = e.tenant_id
            WHERE e.tenant_id = sec.tenant_id
              AND e.class_id = sec.class_id
              AND e.section_id = sec.id
              AND s.branch_id = :branch_id
        )";
        $params['branch_id'] = $scope['branch_id'];
    }

    $statement = $pdo->prepare(
        "SELECT DISTINCT
            sec.id,
            sec.class_id,
            c.academic_year_id,
            c.class_name,
            sec.section_name
         FROM sections sec
         INNER JOIN classes c
            ON c.id = sec.class_id
           AND c.tenant_id = sec.tenant_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY c.academic_year_id DESC,
                  c.display_order,
                  c.class_name,
                  sec.section_name"
    );
    $statement->execute($params);

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function certificateMeta(PDO $pdo, array $scope): array
{
    $academicYears = [];

    if (certificateTableExists($pdo, 'academic_years')) {
        $statement = $pdo->prepare(
            "SELECT id, year_name, is_current, status
             FROM academic_years
             WHERE tenant_id = :tenant_id
             ORDER BY is_current DESC, start_date DESC, id DESC"
        );
        $statement->execute(['tenant_id' => $scope['tenant_id']]);
        $academicYears = $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    return [
        'students' => certificateStudents($pdo, $scope),
        'academic_years' => $academicYears,
        'classes' => certificateClasses($pdo, $scope),
        'sections' => certificateSections($pdo, $scope),
        'certificate_types' => certificateTypes(),
        'current_academic_year_id' => certificateCurrentAcademicYearId(
            $pdo,
            $scope['tenant_id']
        ),
    ];
}

function certificateFindStudent(
    PDO $pdo,
    array $scope,
    int $studentId,
    int $academicYearId,
    int $classId,
    int $sectionId
): array {
    if ($studentId <= 0) {
        throw new InvalidArgumentException('Please select a student.');
    }

    if ($academicYearId <= 0) {
        throw new InvalidArgumentException(
            'Please select an academic year.'
        );
    }

    if ($classId <= 0) {
        throw new InvalidArgumentException('Please select a class.');
    }

    if ($sectionId <= 0) {
        throw new InvalidArgumentException('Please select a section.');
    }

    $where = [
        's.id = :student_id',
        's.tenant_id = :tenant_id',
        'se.academic_year_id = :academic_year_id',
        'se.class_id = :class_id',
        'se.section_id = :section_id',
    ];
    $params = [
        'student_id' => $studentId,
        'tenant_id' => $scope['tenant_id'],
        'academic_year_id' => $academicYearId,
        'class_id' => $classId,
        'section_id' => $sectionId,
    ];

    if ($scope['branch_id'] > 0) {
        $where[] = 's.branch_id = :branch_id';
        $params['branch_id'] = $scope['branch_id'];
    }

    if (certificateColumnExists($pdo, 'students', 'deleted_at')) {
        $where[] = 's.deleted_at IS NULL';
    }

    $statement = $pdo->prepare(
        "SELECT
            s.*,
            s.admission_no,
            TRIM(CONCAT_WS(' ', s.first_name, s.last_name))
                AS student_name,
            se.academic_year_id,
            ay.year_name AS academic_year_name,
            se.class_id,
            c.class_name,
            se.section_id,
            sec.section_name,
            se.roll_no,
            se.enrollment_status
         FROM students s
         INNER JOIN student_enrollments se
            ON se.student_id = s.id
           AND se.tenant_id = s.tenant_id
         INNER JOIN academic_years ay
            ON ay.id = se.academic_year_id
           AND ay.tenant_id = s.tenant_id
         INNER JOIN classes c
            ON c.id = se.class_id
           AND c.tenant_id = s.tenant_id
         INNER JOIN sections sec
            ON sec.id = se.section_id
           AND sec.tenant_id = s.tenant_id
         WHERE " . implode(' AND ', $where) . "
         LIMIT 1"
    );
    $statement->execute($params);

    $student = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        throw new InvalidArgumentException(
            'The selected student does not belong to the selected '
            . 'Academic Year, Class and Section.'
        );
    }

    return $student;
}

function certificateGenerateNumber(
    PDO $pdo,
    int $tenantId,
    string $type,
    string $issueDate
): string {
    $prefix = certificateTypePrefix($type);
    $year = date('Y', strtotime($issueDate));
    $pattern = $prefix . '-' . $year . '-%';

    $statement = $pdo->prepare(
        "SELECT certificate_no
         FROM student_certificates
         WHERE tenant_id = :tenant_id
           AND certificate_no LIKE :pattern
         ORDER BY id DESC
         LIMIT 1"
    );
    $statement->execute([
        'tenant_id' => $tenantId,
        'pattern' => $pattern,
    ]);

    $last = (string)($statement->fetchColumn() ?: '');
    $sequence = 1;

    if (
        $last !== ''
        && preg_match('/-(\d+)$/', $last, $match)
    ) {
        $sequence = (int)$match[1] + 1;
    }

    while (true) {
        $number = sprintf(
            '%s-%s-%05d',
            $prefix,
            $year,
            $sequence
        );

        $check = $pdo->prepare(
            "SELECT COUNT(*)
             FROM student_certificates
             WHERE tenant_id = :tenant_id
               AND certificate_no = :certificate_no"
        );
        $check->execute([
            'tenant_id' => $tenantId,
            'certificate_no' => $number,
        ]);

        if ((int)$check->fetchColumn() === 0) {
            return $number;
        }

        $sequence++;
    }
}

function certificateLog(
    PDO $pdo,
    array $scope,
    int $certificateId,
    int $studentId,
    string $action,
    string $description
): void {
    try {
        $statement = $pdo->prepare(
            "INSERT INTO student_certificate_logs (
                tenant_id,
                certificate_id,
                student_id,
                user_id,
                action_name,
                description
             ) VALUES (
                :tenant_id,
                :certificate_id,
                :student_id,
                :user_id,
                :action_name,
                :description
             )"
        );
        $statement->execute([
            'tenant_id' => $scope['tenant_id'],
            'certificate_id' => $certificateId,
            'student_id' => $studentId,
            'user_id' => $scope['user_id'] ?: null,
            'action_name' => $action,
            'description' => $description,
        ]);
    } catch (Throwable $exception) {
        error_log(
            'student certificate log failed: '
            . $exception->getMessage()
        );
    }
}

function certificateList(
    PDO $pdo,
    array $scope,
    array $filters
): array {
    $where = [
        'sc.tenant_id = :tenant_id',
    ];
    $params = [
        'tenant_id' => $scope['tenant_id'],
    ];

    if ($scope['branch_id'] > 0) {
        $where[] = 'sc.branch_id = :branch_id';
        $params['branch_id'] = $scope['branch_id'];
    }

    $search = trim((string)($filters['search'] ?? ''));
    if ($search !== '') {
        $where[] = "(
            sc.certificate_no LIKE :search_certificate
            OR s.admission_no LIKE :search_admission
            OR s.first_name LIKE :search_first_name
            OR s.last_name LIKE :search_last_name
            OR CONCAT_WS(' ', s.first_name, s.last_name)
                LIKE :search_full_name
        )";

        $searchValue = '%' . $search . '%';
        $params['search_certificate'] = $searchValue;
        $params['search_admission'] = $searchValue;
        $params['search_first_name'] = $searchValue;
        $params['search_last_name'] = $searchValue;
        $params['search_full_name'] = $searchValue;
    }

    $type = strtolower(
        trim((string)($filters['certificate_type'] ?? ''))
    );
    if ($type !== '' && $type !== 'all') {
        $where[] = 'sc.certificate_type = :certificate_type';
        $params['certificate_type'] = $type;
    }

    $status = strtolower(
        trim((string)($filters['status'] ?? ''))
    );
    if ($status !== '' && $status !== 'all') {
        $where[] = 'sc.status = :status';
        $params['status'] = $status;
    }

    $yearId = (int)($filters['academic_year_id'] ?? 0);
    if ($yearId > 0) {
        $where[] = 'sc.academic_year_id = :academic_year_id';
        $params['academic_year_id'] = $yearId;
    }

    $statement = $pdo->prepare(
        "SELECT
            sc.*,
            s.admission_no,
            TRIM(CONCAT_WS(' ', s.first_name, s.last_name))
                AS student_name,
            ay.year_name AS academic_year_name,
            c.class_name,
            sec.section_name,
            DATE_FORMAT(sc.issue_date, '%d-%m-%Y')
                AS issue_date_display
         FROM student_certificates sc
         INNER JOIN students s
            ON s.id = sc.student_id
           AND s.tenant_id = sc.tenant_id
         LEFT JOIN academic_years ay
            ON ay.id = sc.academic_year_id
           AND ay.tenant_id = sc.tenant_id
         LEFT JOIN classes c
            ON c.id = sc.class_id
           AND c.tenant_id = sc.tenant_id
         LEFT JOIN sections sec
            ON sec.id = sc.section_id
           AND sec.tenant_id = sc.tenant_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY sc.id DESC"
    );
    $statement->execute($params);

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function certificateStats(PDO $pdo, array $scope): array
{
    $where = ['tenant_id = :tenant_id'];
    $params = ['tenant_id' => $scope['tenant_id']];

    if ($scope['branch_id'] > 0) {
        $where[] = 'branch_id = :branch_id';
        $params['branch_id'] = $scope['branch_id'];
    }

    $statement = $pdo->prepare(
        "SELECT
            COUNT(*) AS total,
            SUM(status = 'issued') AS issued,
            SUM(status = 'draft') AS draft,
            SUM(status = 'cancelled') AS cancelled
         FROM student_certificates
         WHERE " . implode(' AND ', $where)
    );
    $statement->execute($params);

    return $statement->fetch(PDO::FETCH_ASSOC) ?: [];
}

function certificateFind(
    PDO $pdo,
    array $scope,
    int $id
): ?array {
    $where = [
        'sc.id = :id',
        'sc.tenant_id = :tenant_id',
    ];
    $params = [
        'id' => $id,
        'tenant_id' => $scope['tenant_id'],
    ];

    if ($scope['branch_id'] > 0) {
        $where[] = 'sc.branch_id = :branch_id';
        $params['branch_id'] = $scope['branch_id'];
    }

    $statement = $pdo->prepare(
        "SELECT
            sc.*,
            s.admission_no,
            TRIM(CONCAT_WS(' ', s.first_name, s.last_name))
                AS student_name,
            s.date_of_birth,
            s.gender,
            s.admission_date,
            ay.year_name AS academic_year_name,
            c.class_name,
            sec.section_name,
            b.branch_name
         FROM student_certificates sc
         INNER JOIN students s
            ON s.id = sc.student_id
           AND s.tenant_id = sc.tenant_id
         LEFT JOIN academic_years ay
            ON ay.id = sc.academic_year_id
           AND ay.tenant_id = sc.tenant_id
         LEFT JOIN classes c
            ON c.id = sc.class_id
           AND c.tenant_id = sc.tenant_id
         LEFT JOIN sections sec
            ON sec.id = sc.section_id
           AND sec.tenant_id = sc.tenant_id
         LEFT JOIN branches b
            ON b.id = sc.branch_id
           AND b.tenant_id = sc.tenant_id
         WHERE " . implode(' AND ', $where) . "
         LIMIT 1"
    );
    $statement->execute($params);

    $record = $statement->fetch(PDO::FETCH_ASSOC);
    return $record ?: null;
}

function certificateValidate(array $input): array
{
    $types = certificateTypes();

    $studentId = (int)($input['student_id'] ?? 0);
    $academicYearId = (int)($input['academic_year_id'] ?? 0);
    $classId = (int)($input['class_id'] ?? 0);
    $sectionId = (int)($input['section_id'] ?? 0);
    $type = strtolower(
        trim((string)($input['certificate_type'] ?? ''))
    );
    $issueDate = trim((string)($input['issue_date'] ?? ''));
    $status = strtolower(
        trim((string)($input['status'] ?? 'draft'))
    );

    if ($academicYearId <= 0) {
        throw new InvalidArgumentException(
            'Please select an academic year.'
        );
    }

    if ($classId <= 0) {
        throw new InvalidArgumentException('Please select a class.');
    }

    if ($sectionId <= 0) {
        throw new InvalidArgumentException('Please select a section.');
    }

    if ($studentId <= 0) {
        throw new InvalidArgumentException('Please select a student.');
    }

    if (!array_key_exists($type, $types)) {
        throw new InvalidArgumentException(
            'Please select a valid certificate type.'
        );
    }

    $date = DateTimeImmutable::createFromFormat(
        'Y-m-d',
        $issueDate
    );
    $dateErrors = DateTimeImmutable::getLastErrors();

    if (
        !$date
        || (
            is_array($dateErrors)
            && (
                ($dateErrors['warning_count'] ?? 0) > 0
                || ($dateErrors['error_count'] ?? 0) > 0
            )
        )
        || $date->format('Y-m-d') !== $issueDate
    ) {
        throw new InvalidArgumentException(
            'Please enter a valid issue date.'
        );
    }

    if (!in_array($status, ['draft', 'issued'], true)) {
        $status = 'draft';
    }

    $title = trim(
        (string)($input['certificate_title'] ?? '')
    );
    $customBody = trim(
        (string)($input['custom_body'] ?? '')
    );

    if ($type === 'custom' && ($title === '' || $customBody === '')) {
        throw new InvalidArgumentException(
            'Custom certificate title and body are required.'
        );
    }

    return [
        'student_id' => $studentId,
        'academic_year_id' => $academicYearId,
        'class_id' => $classId,
        'section_id' => $sectionId,
        'certificate_type' => $type,
        'certificate_title' => $title,
        'purpose' => trim((string)($input['purpose'] ?? '')),
        'conduct_text' => trim(
            (string)($input['conduct_text'] ?? 'Good')
        ),
        'custom_body' => $customBody,
        'remarks' => trim((string)($input['remarks'] ?? '')),
        'issue_date' => $issueDate,
        'status' => $status,
    ];
}

function certificateSave(
    PDO $pdo,
    array $scope,
    array $input
): int {
    $id = (int)($input['id'] ?? 0);
    $requiredAction = $id > 0 ? 'edit' : 'create';

    if (!certificateCan($requiredAction)) {
        throw new RuntimeException(
            $id > 0
                ? 'You do not have permission to edit certificates.'
                : 'You do not have permission to create certificates.',
            403
        );
    }

    $data = certificateValidate($input);
    $student = certificateFindStudent(
        $pdo,
        $scope,
        $data['student_id'],
        $data['academic_year_id'],
        $data['class_id'],
        $data['section_id']
    );

    $existing = $id > 0
        ? certificateFind($pdo, $scope, $id)
        : null;

    if ($id > 0 && !$existing) {
        throw new RuntimeException('Certificate not found.', 404);
    }

    if ($existing && $existing['status'] !== 'draft') {
        throw new RuntimeException(
            'Only draft certificates can be edited.',
            422
        );
    }

    $pdo->beginTransaction();

    try {
        if ($id > 0) {
            $statement = $pdo->prepare(
                "UPDATE student_certificates SET
                    branch_id = :branch_id,
                    student_id = :student_id,
                    academic_year_id = :academic_year_id,
                    class_id = :class_id,
                    section_id = :section_id,
                    certificate_type = :certificate_type,
                    certificate_title = :certificate_title,
                    purpose = :purpose,
                    conduct_text = :conduct_text,
                    custom_body = :custom_body,
                    remarks = :remarks,
                    issue_date = :issue_date,
                    status = :status,
                    issued_by = :issued_by,
                    issued_at = :issued_at,
                    updated_by = :updated_by
                 WHERE id = :id
                   AND tenant_id = :tenant_id"
            );
        } else {
            $statement = $pdo->prepare(
                "INSERT INTO student_certificates (
                    tenant_id,
                    branch_id,
                    student_id,
                    academic_year_id,
                    class_id,
                    section_id,
                    certificate_no,
                    certificate_type,
                    certificate_title,
                    purpose,
                    conduct_text,
                    custom_body,
                    remarks,
                    issue_date,
                    status,
                    issued_by,
                    issued_at,
                    created_by,
                    updated_by
                 ) VALUES (
                    :tenant_id,
                    :branch_id,
                    :student_id,
                    :academic_year_id,
                    :class_id,
                    :section_id,
                    :certificate_no,
                    :certificate_type,
                    :certificate_title,
                    :purpose,
                    :conduct_text,
                    :custom_body,
                    :remarks,
                    :issue_date,
                    :status,
                    :issued_by,
                    :issued_at,
                    :created_by,
                    :updated_by
                 )"
            );
        }

        $params = [
            'tenant_id' => $scope['tenant_id'],
            'branch_id' => (int)$student['branch_id'] ?: null,
            'student_id' => (int)$student['id'],
            'academic_year_id' =>
                (int)($student['academic_year_id'] ?? 0) ?: null,
            'class_id' => (int)($student['class_id'] ?? 0) ?: null,
            'section_id' =>
                (int)($student['section_id'] ?? 0) ?: null,
            'certificate_type' => $data['certificate_type'],
            'certificate_title' =>
                $data['certificate_title'] !== ''
                    ? $data['certificate_title']
                    : null,
            'purpose' =>
                $data['purpose'] !== ''
                    ? $data['purpose']
                    : null,
            'conduct_text' =>
                $data['conduct_text'] !== ''
                    ? $data['conduct_text']
                    : null,
            'custom_body' =>
                $data['custom_body'] !== ''
                    ? $data['custom_body']
                    : null,
            'remarks' =>
                $data['remarks'] !== ''
                    ? $data['remarks']
                    : null,
            'issue_date' => $data['issue_date'],
            'status' => $data['status'],
            'issued_by' =>
                $data['status'] === 'issued'
                    ? ($scope['user_id'] ?: null)
                    : null,
            'issued_at' =>
                $data['status'] === 'issued'
                    ? date('Y-m-d H:i:s')
                    : null,
            'updated_by' => $scope['user_id'] ?: null,
        ];

        if ($id > 0) {
            $params['id'] = $id;
        } else {
            $params['certificate_no'] =
                certificateGenerateNumber(
                    $pdo,
                    $scope['tenant_id'],
                    $data['certificate_type'],
                    $data['issue_date']
                );
            $params['created_by'] = $scope['user_id'] ?: null;
        }

        $statement->execute($params);

        if ($id <= 0) {
            $id = (int)$pdo->lastInsertId();
        }

        certificateLog(
            $pdo,
            $scope,
            $id,
            (int)$student['id'],
            $existing ? 'update' : 'create',
            $existing
                ? 'Certificate updated.'
                : (
                    $data['status'] === 'issued'
                        ? 'Certificate created and issued.'
                        : 'Certificate draft created.'
                )
        );

        $pdo->commit();
        return $id;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function certificateIssue(
    PDO $pdo,
    array $scope,
    int $id
): void {
    if (!certificateCan('issue')) {
        throw new RuntimeException(
            'You do not have permission to issue certificates.',
            403
        );
    }

    $record = certificateFind($pdo, $scope, $id);

    if (!$record) {
        throw new RuntimeException('Certificate not found.', 404);
    }

    if ($record['status'] !== 'draft') {
        throw new RuntimeException(
            'Only draft certificates can be issued.',
            422
        );
    }

    $statement = $pdo->prepare(
        "UPDATE student_certificates
         SET status = 'issued',
             issued_by = :issued_by,
             issued_at = CURRENT_TIMESTAMP,
             updated_by = :updated_by
         WHERE id = :id
           AND tenant_id = :tenant_id"
    );
    $statement->execute([
        'issued_by' => $scope['user_id'] ?: null,
        'updated_by' => $scope['user_id'] ?: null,
        'id' => $id,
        'tenant_id' => $scope['tenant_id'],
    ]);

    certificateLog(
        $pdo,
        $scope,
        $id,
        (int)$record['student_id'],
        'issue',
        'Certificate issued.'
    );
}

function certificateCancel(
    PDO $pdo,
    array $scope,
    int $id,
    string $reason
): void {
    if (!certificateCan('cancel')) {
        throw new RuntimeException(
            'You do not have permission to cancel certificates.',
            403
        );
    }

    $record = certificateFind($pdo, $scope, $id);

    if (!$record) {
        throw new RuntimeException('Certificate not found.', 404);
    }

    if ($record['status'] !== 'issued') {
        throw new RuntimeException(
            'Only issued certificates can be cancelled.',
            422
        );
    }

    $statement = $pdo->prepare(
        "UPDATE student_certificates
         SET status = 'cancelled',
             cancelled_by = :cancelled_by,
             cancelled_at = CURRENT_TIMESTAMP,
             cancellation_reason = :reason,
             updated_by = :updated_by
         WHERE id = :id
           AND tenant_id = :tenant_id"
    );
    $statement->execute([
        'cancelled_by' => $scope['user_id'] ?: null,
        'reason' => trim($reason) !== '' ? trim($reason) : null,
        'updated_by' => $scope['user_id'] ?: null,
        'id' => $id,
        'tenant_id' => $scope['tenant_id'],
    ]);

    certificateLog(
        $pdo,
        $scope,
        $id,
        (int)$record['student_id'],
        'cancel',
        trim($reason) !== ''
            ? 'Certificate cancelled: ' . trim($reason)
            : 'Certificate cancelled.'
    );
}

function certificateDelete(
    PDO $pdo,
    array $scope,
    int $id
): void {
    if (!certificateCan('delete')) {
        throw new RuntimeException(
            'You do not have permission to delete certificate drafts.',
            403
        );
    }

    $record = certificateFind($pdo, $scope, $id);

    if (!$record) {
        throw new RuntimeException('Certificate not found.', 404);
    }

    if ($record['status'] !== 'draft') {
        throw new RuntimeException(
            'Only draft certificates can be deleted.',
            422
        );
    }

    $pdo->beginTransaction();

    try {
        $pdo->prepare(
            "DELETE FROM student_certificate_logs
             WHERE certificate_id = :certificate_id
               AND tenant_id = :tenant_id"
        )->execute([
            'certificate_id' => $id,
            'tenant_id' => $scope['tenant_id'],
        ]);

        $pdo->prepare(
            "DELETE FROM student_certificates
             WHERE id = :id
               AND tenant_id = :tenant_id"
        )->execute([
            'id' => $id,
            'tenant_id' => $scope['tenant_id'],
        ]);

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function certificateFirstAvailableColumn(
    PDO $pdo,
    string $table,
    array $columns
): ?string {
    foreach ($columns as $column) {
        if (certificateColumnExists($pdo, $table, $column)) {
            return $column;
        }
    }

    return null;
}

function certificateSchoolProfile(
    PDO $pdo,
    array $scope
): array {
    $profile = [
        'school_name' => 'School Name',
        'branch_name' => '',
        'address' => '',
        'phone' => '',
        'email' => '',
    ];

    if (certificateTableExists($pdo, 'tenants')) {
        $nameColumn = certificateFirstAvailableColumn(
            $pdo,
            'tenants',
            [
                'school_name',
                'tenant_name',
                'organization_name',
                'name',
            ]
        );

        $addressColumn = certificateFirstAvailableColumn(
            $pdo,
            'tenants',
            ['address', 'school_address']
        );

        $phoneColumn = certificateFirstAvailableColumn(
            $pdo,
            'tenants',
            ['phone', 'mobile', 'contact_number']
        );

        $emailColumn = certificateFirstAvailableColumn(
            $pdo,
            'tenants',
            ['email', 'contact_email']
        );

        $select = ['id'];
        if ($nameColumn) {
            $select[] = $nameColumn . ' AS school_name';
        }
        if ($addressColumn) {
            $select[] = $addressColumn . ' AS address';
        }
        if ($phoneColumn) {
            $select[] = $phoneColumn . ' AS phone';
        }
        if ($emailColumn) {
            $select[] = $emailColumn . ' AS email';
        }

        $statement = $pdo->prepare(
            'SELECT ' . implode(', ', $select)
            . ' FROM tenants WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $scope['tenant_id']]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (is_array($row)) {
            foreach (['school_name', 'address', 'phone', 'email'] as $key) {
                if (trim((string)($row[$key] ?? '')) !== '') {
                    $profile[$key] = trim((string)$row[$key]);
                }
            }
        }
    }

    if (
        $scope['branch_id'] > 0
        && certificateTableExists($pdo, 'branches')
    ) {
        $nameColumn = certificateFirstAvailableColumn(
            $pdo,
            'branches',
            ['branch_name', 'name']
        );

        $addressColumn = certificateFirstAvailableColumn(
            $pdo,
            'branches',
            ['address', 'branch_address']
        );

        $phoneColumn = certificateFirstAvailableColumn(
            $pdo,
            'branches',
            ['phone', 'mobile', 'contact_number']
        );

        $emailColumn = certificateFirstAvailableColumn(
            $pdo,
            'branches',
            ['email', 'contact_email']
        );

        $select = ['id'];
        if ($nameColumn) {
            $select[] = $nameColumn . ' AS branch_name';
        }
        if ($addressColumn) {
            $select[] = $addressColumn . ' AS address';
        }
        if ($phoneColumn) {
            $select[] = $phoneColumn . ' AS phone';
        }
        if ($emailColumn) {
            $select[] = $emailColumn . ' AS email';
        }

        $statement = $pdo->prepare(
            'SELECT ' . implode(', ', $select)
            . ' FROM branches'
            . ' WHERE id = :id AND tenant_id = :tenant_id LIMIT 1'
        );
        $statement->execute([
            'id' => $scope['branch_id'],
            'tenant_id' => $scope['tenant_id'],
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (is_array($row)) {
            foreach (['branch_name', 'address', 'phone', 'email'] as $key) {
                if (trim((string)($row[$key] ?? '')) !== '') {
                    $profile[$key] = trim((string)$row[$key]);
                }
            }
        }
    }

    return $profile;
}

function certificateBody(
    array $record,
    array $profile
): array {
    $schoolName = $profile['school_name'];
    $studentName = (string)$record['student_name'];
    $admissionNo = (string)$record['admission_no'];
    $class = trim(
        (string)($record['class_name'] ?? '')
        . (
            trim((string)($record['section_name'] ?? '')) !== ''
                ? ' - ' . trim((string)$record['section_name'])
                : ''
        )
    );
    $year = (string)($record['academic_year_name'] ?? '');
    $purpose = trim((string)($record['purpose'] ?? ''));
    $conduct = trim((string)($record['conduct_text'] ?? 'Good'));
    $issueDate = date(
        'd-m-Y',
        strtotime((string)$record['issue_date'])
    );

    $type = (string)$record['certificate_type'];

    if ($type === 'study') {
        return [
            'title' => 'STUDY CERTIFICATE',
            'body' => "This is to certify that {$studentName}, "
                . "Admission No. {$admissionNo}, has studied in "
                . "{$schoolName} in {$class} during the academic year "
                . "{$year}. This certificate is issued"
                . ($purpose !== '' ? " for {$purpose}" : '')
                . '.',
        ];
    }

    if ($type === 'conduct') {
        return [
            'title' => 'CONDUCT CERTIFICATE',
            'body' => "This is to certify that {$studentName}, "
                . "Admission No. {$admissionNo}, was a student of "
                . "{$schoolName} in {$class} during the academic year "
                . "{$year}. The student's conduct and character were "
                . strtolower($conduct !== '' ? $conduct : 'good')
                . '.',
        ];
    }

    if ($type === 'transfer') {
        return [
            'title' => 'TRANSFER CERTIFICATE',
            'body' => "This is to certify that {$studentName}, "
                . "Admission No. {$admissionNo}, studied in {$schoolName} "
                . "in {$class} during the academic year {$year}. "
                . "This transfer certificate is issued"
                . ($purpose !== '' ? " for {$purpose}" : '')
                . '.',
        ];
    }

    if ($type === 'custom') {
        $body = (string)($record['custom_body'] ?? '');
        $replace = [
            '{{student_name}}' => $studentName,
            '{{admission_no}}' => $admissionNo,
            '{{class_name}}' => (string)($record['class_name'] ?? ''),
            '{{section_name}}' =>
                (string)($record['section_name'] ?? ''),
            '{{academic_year}}' => $year,
            '{{school_name}}' => $schoolName,
            '{{issue_date}}' => $issueDate,
        ];

        return [
            'title' => trim(
                (string)($record['certificate_title'] ?? '')
            ) ?: 'CERTIFICATE',
            'body' => strtr($body, $replace),
        ];
    }

    return [
        'title' => 'BONAFIDE CERTIFICATE',
        'body' => "This is to certify that {$studentName}, "
            . "Admission No. {$admissionNo}, is a bonafide student of "
            . "{$schoolName}, studying in {$class} during the academic "
            . "year {$year}. This certificate is issued"
            . ($purpose !== '' ? " for {$purpose}" : '')
            . '.',
    ];
}

function certificatePrint(
    PDO $pdo,
    array $scope,
    int $id
): never {
    if (!certificateCan('view')) {
        http_response_code(403);
        exit('Permission denied.');
    }

    $record = certificateFind($pdo, $scope, $id);

    if (!$record) {
        http_response_code(404);
        exit('Certificate not found.');
    }

    $profile = certificateSchoolProfile($pdo, $scope);
    $content = certificateBody($record, $profile);

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');

    $escape = static fn(mixed $value): string => htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );

    $contact = array_filter([
        $profile['address'],
        $profile['phone'] !== '' ? 'Phone: ' . $profile['phone'] : '',
        $profile['email'] !== '' ? 'Email: ' . $profile['email'] : '',
    ]);

    echo '<!doctype html><html><head><meta charset="utf-8">';
    echo '<title>' . $escape($record['certificate_no']) . '</title>';
    echo '<style>
        @page{size:A4 portrait;margin:12mm}
        *{box-sizing:border-box}
        body{margin:0;background:#eef2f7;font-family:Georgia,"Times New Roman",serif;color:#172033}
        .toolbar{max-width:900px;margin:14px auto;display:flex;justify-content:flex-end;gap:8px;font-family:Arial,sans-serif}
        .toolbar button{border:0;border-radius:7px;padding:9px 14px;cursor:pointer}
        .sheet{width:210mm;min-height:297mm;margin:0 auto 20px;background:#fff;padding:15mm}
        .border{min-height:267mm;border:7px double #283b68;padding:15mm;position:relative}
        .header{text-align:center;border-bottom:2px solid #283b68;padding-bottom:16px}
        .school{font-family:Arial,sans-serif;font-size:28px;font-weight:800;letter-spacing:.7px;color:#172a55}
        .branch{font-family:Arial,sans-serif;margin-top:5px;font-size:14px;font-weight:700}
        .contact{font-family:Arial,sans-serif;margin-top:5px;font-size:11px;line-height:1.5;color:#475569}
        .title{text-align:center;margin:38px 0 30px;font-size:25px;text-decoration:underline;text-underline-offset:7px;letter-spacing:1px}
        .number{display:flex;justify-content:space-between;font-family:Arial,sans-serif;font-size:12px;margin-bottom:28px}
        .body{font-size:19px;line-height:2.05;text-align:justify;text-indent:55px;min-height:315px}
        .meta{margin-top:28px;font-family:Arial,sans-serif;font-size:12px;line-height:1.7}
        .signatures{display:grid;grid-template-columns:1fr 1fr;margin-top:85px;text-align:center;font-family:Arial,sans-serif;font-size:12px}
        .sign-line{border-top:1px solid #172033;padding-top:8px;width:180px;margin:auto}
        .cancelled{position:absolute;inset:44% auto auto 50%;transform:translate(-50%,-50%) rotate(-18deg);border:5px solid #dc2626;color:#dc2626;font-family:Arial,sans-serif;font-weight:900;font-size:44px;padding:8px 18px;opacity:.32}
        @media print{
            body{background:#fff}
            .toolbar{display:none}
            .sheet{margin:0;padding:0;width:auto;min-height:auto}
            .border{min-height:270mm}
        }
    </style></head><body>';

    echo '<div class="toolbar"><button onclick="window.print()">Print Certificate</button></div>';
    echo '<main class="sheet"><section class="border">';

    if ($record['status'] === 'cancelled') {
        echo '<div class="cancelled">CANCELLED</div>';
    }

    echo '<header class="header">';
    echo '<div class="school">' . $escape($profile['school_name']) . '</div>';
    if ($profile['branch_name'] !== '') {
        echo '<div class="branch">' . $escape($profile['branch_name']) . '</div>';
    }
    if ($contact) {
        echo '<div class="contact">' . $escape(implode(' | ', $contact)) . '</div>';
    }
    echo '</header>';

    echo '<h1 class="title">' . $escape(strtoupper($content['title'])) . '</h1>';
    echo '<div class="number"><span>Certificate No: <strong>'
        . $escape($record['certificate_no'])
        . '</strong></span><span>Date: <strong>'
        . $escape(date('d-m-Y', strtotime((string)$record['issue_date'])))
        . '</strong></span></div>';

    echo '<div class="body">' . nl2br($escape($content['body'])) . '</div>';

    echo '<div class="meta">';
    echo '<div><strong>Student:</strong> ' . $escape($record['student_name']) . '</div>';
    echo '<div><strong>Admission No:</strong> ' . $escape($record['admission_no']) . '</div>';
    echo '<div><strong>Academic Year:</strong> ' . $escape($record['academic_year_name'] ?? '-') . '</div>';
    echo '<div><strong>Class / Section:</strong> ' . $escape(
        trim(
            (string)($record['class_name'] ?? '')
            . (
                trim((string)($record['section_name'] ?? '')) !== ''
                    ? ' / ' . trim((string)$record['section_name'])
                    : ''
            )
        ) ?: '-'
    ) . '</div>';
    echo '</div>';

    echo '<div class="signatures">';
    echo '<div><div class="sign-line">Class Teacher</div></div>';
    echo '<div><div class="sign-line">Principal / Headmaster</div></div>';
    echo '</div>';

    echo '</section></main></body></html>';
    exit;
}

if (!isset($pdo) || !$pdo instanceof PDO) {
    certificateJson(false, 'Database connection unavailable.', [], 500);
}

try {
    certificateEnsureSchema($pdo);
} catch (Throwable $exception) {
    error_log(
        'student-certificates schema: ' . $exception->getMessage()
    );
    certificateJson(
        false,
        'Unable to prepare Student Certificates tables: '
            . $exception->getMessage(),
        [],
        500
    );
}

$user = certificateUser();
$scope = certificateScope($user);

if ($scope['tenant_id'] <= 0) {
    certificateJson(
        false,
        'School tenant session was not found. Sign out and sign in again.',
        [],
        401
    );
}

$input = certificateInput();
$action = strtolower(
    trim((string)($input['action'] ?? $_GET['action'] ?? ''))
);

try {
    if ($action === 'print') {
        certificatePrint(
            $pdo,
            $scope,
            (int)($_GET['id'] ?? 0)
        );
    }

    if ($action === 'meta') {
        if (!certificateCan('view')) {
            throw new RuntimeException('Permission denied.', 403);
        }

        $canCreate = certificateCan('create');

        certificateJson(
            true,
            'Certificate metadata loaded.',
            [
                'csrf_token' => function_exists('csrfToken')
                    ? csrfToken()
                    : '',
                'meta' => certificateMeta($pdo, $scope),
                'permissions' => [
                    'view' => certificateCan('view'),
                    'create' => $canCreate,
                    'add' => $canCreate,
                    'edit' => certificateCan('edit'),
                    'issue' => certificateCan('issue'),
                    'cancel' => certificateCan('cancel'),
                    'delete' => certificateCan('delete'),
                ],
            ]
        );
    }

    if ($action === 'list') {
        if (!certificateCan('view')) {
            throw new RuntimeException('Permission denied.', 403);
        }

        certificateJson(
            true,
            'Certificates loaded.',
            [
                'records' => certificateList(
                    $pdo,
                    $scope,
                    array_merge($_GET, $input)
                ),
                'stats' => certificateStats($pdo, $scope),
            ]
        );
    }

    if (!in_array(
        $action,
        ['save', 'issue', 'cancel', 'delete'],
        true
    )) {
        certificateJson(false, 'Invalid API action.', [], 400);
    }

    certificateCsrf($input);

    if ($action === 'save') {
        $id = certificateSave($pdo, $scope, $input);

        certificateJson(
            true,
            (int)($input['id'] ?? 0) > 0
                ? 'Certificate updated successfully.'
                : 'Certificate created successfully.',
            ['id' => $id]
        );
    }

    if ($action === 'issue') {
        certificateIssue(
            $pdo,
            $scope,
            (int)($input['id'] ?? 0)
        );
        certificateJson(true, 'Certificate issued successfully.');
    }

    if ($action === 'cancel') {
        certificateCancel(
            $pdo,
            $scope,
            (int)($input['id'] ?? 0),
            (string)($input['reason'] ?? '')
        );
        certificateJson(true, 'Certificate cancelled successfully.');
    }

    if ($action === 'delete') {
        certificateDelete(
            $pdo,
            $scope,
            (int)($input['id'] ?? 0)
        );
        certificateJson(true, 'Certificate draft deleted successfully.');
    }

    certificateJson(false, 'Invalid API action.', [], 400);
} catch (InvalidArgumentException $exception) {
    certificateJson(false, $exception->getMessage(), [], 422);
} catch (RuntimeException $exception) {
    $code = (int)$exception->getCode();
    certificateJson(
        false,
        $exception->getMessage(),
        [],
        $code >= 400 && $code <= 599 ? $code : 403
    );
} catch (Throwable $exception) {
    error_log(
        'student-certificates-api: ' . $exception->getMessage()
    );

    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $message = (
        str_contains($host, 'localhost')
        || str_contains($host, '127.0.0.1')
    )
        ? 'Certificate request failed: ' . $exception->getMessage()
        : 'Unable to complete certificate request.';

    certificateJson(false, $message, [], 500);
}

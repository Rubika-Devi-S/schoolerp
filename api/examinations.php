<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$currentUser = function_exists('current_user') ? current_user() : [];
$tenantId = (int)($currentUser['tenant_id'] ?? $currentUser['school_id'] ?? $_SESSION['tenant_id'] ?? $_SESSION['school_id'] ?? 0);
$roleId = (int)($currentUser['role_id'] ?? $_SESSION['role_id'] ?? 0);
$userId = (int)($currentUser['id'] ?? $currentUser['user_id'] ?? $_SESSION['user_id'] ?? 0);
$csrfToken = function_exists('csrfToken') ? csrfToken() : '';

function examJson(bool $success, string $message = '', array $data = [], int $status = 200): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }

    echo json_encode(
        ['success' => $success, 'message' => $message, 'data' => $data],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function examInput(): array
{
    if (str_contains(strtolower((string)($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json')) {
        $decoded = json_decode((string)file_get_contents('php://input'), true);
        return is_array($decoded) ? $decoded : [];
    }
    return $_POST;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    examJson(false, 'Database connection unavailable.', [], 500);
}

try {
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
} catch (Throwable $e) {
    error_log('examinations PDO prepare mode: ' . $e->getMessage());
}

if ($tenantId <= 0) {
    examJson(false, 'Active School Admin login required.', [], 401);
}

$input = examInput();
$action = strtolower(trim((string)($input['action'] ?? $_GET['action'] ?? '')));
$moduleKey = strtolower(trim((string)($input['module_key'] ?? $_GET['module_key'] ?? '')));

try {
    // ============================================
    // FORM DATA - Get dropdown options
    // ============================================
    if ($action === 'form_data') {
        $data = [];

        // 1. Exam Types
        $types = $pdo->prepare("SELECT id, exam_type_name FROM exam_types WHERE tenant_id = :tenant_id AND is_active = 1 ORDER BY exam_type_name");
        $types->execute(['tenant_id' => $tenantId]);
        $data['exam_types'] = $types->fetchAll(PDO::FETCH_ASSOC);

        // 2. Academic Years
        try {
            $years = $pdo->prepare("
                SELECT id, year_name as academic_year_name 
                FROM academic_years 
                WHERE tenant_id = :tenant_id 
                ORDER BY is_current DESC, start_date DESC
            ");
            $years->execute(['tenant_id' => $tenantId]);
            $data['academic_years'] = $years->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $data['academic_years'] = [];
        }

        // 3. Classes from class_management_classes
        try {
            $classes = $pdo->prepare("
                SELECT id, class_name, section_name 
                FROM class_management_classes 
                WHERE tenant_id = :tenant_id AND status = 'active' 
                ORDER BY class_name, section_name
            ");
            $classes->execute(['tenant_id' => $tenantId]);
            $classList = $classes->fetchAll(PDO::FETCH_ASSOC);
            
            $data['classes'] = array_map(function($class) {
                return [
                    'id' => $class['id'],
                    'class_name' => $class['class_name'] . ' - ' . $class['section_name']
                ];
            }, $classList);
        } catch (Exception $e) {
            $data['classes'] = [];
        }

        // 4. Sections from school_sections
        try {
            $sections = $pdo->prepare("
                SELECT
                    id,
                    section_name,
                    section_code,
                    class_name_snapshot,
                    class_id,
                    academic_year_id,
                    medium,
                    shift_name,
                    room_number,
                    display_order,
                    CONCAT(
                        class_name_snapshot,
                        ' - ',
                        section_name,
                        CASE
                            WHEN section_code IS NULL OR section_code = '' THEN ''
                            ELSE CONCAT(' (', section_code, ')')
                        END
                    ) AS section_label
                FROM school_sections
                WHERE tenant_id = :tenant_id
                  AND status = 'active'
                ORDER BY display_order, class_name_snapshot, section_name
            ");
            $sections->execute(['tenant_id' => $tenantId]);
            $data['sections'] = $sections->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('Error fetching sections: ' . $e->getMessage());
            $data['sections'] = [];
        }

        // 5. Subjects
        try {
            $subjects = $pdo->prepare("
                SELECT id, subject_name 
                FROM subjects 
                WHERE tenant_id = :tenant_id 
                ORDER BY subject_name
            ");
            $subjects->execute(['tenant_id' => $tenantId]);
            $data['subjects'] = $subjects->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $data['subjects'] = [];
        }

        // 6. Teachers
        try {
            $teachers = $pdo->prepare("
                SELECT id, CONCAT(first_name, ' ', last_name) AS name 
                FROM users 
                WHERE tenant_id = :tenant_id AND role IN ('teacher', 'admin') 
                ORDER BY first_name
            ");
            $teachers->execute(['tenant_id' => $tenantId]);
            $data['teachers'] = $teachers->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $data['teachers'] = [];
        }

        examJson(true, 'Form data loaded successfully.', $data);
    }

    // ============================================
    // GET SECTIONS
    // ============================================
    if ($action === 'get_sections') {
        $classId = (int)($input['class_id'] ?? $_GET['class_id'] ?? 0);
        $academicYearId = (int)($input['academic_year_id'] ?? $_GET['academic_year_id'] ?? 0);

        try {
            $baseSelect = "
                SELECT
                    id,
                    section_name,
                    section_code,
                    class_name_snapshot,
                    class_id,
                    academic_year_id,
                    medium,
                    shift_name,
                    room_number,
                    display_order,
                    CONCAT(
                        class_name_snapshot,
                        ' - ',
                        section_name,
                        CASE
                            WHEN section_code IS NULL OR section_code = '' THEN ''
                            ELSE CONCAT(' (', section_code, ')')
                        END
                    ) AS section_label
                FROM school_sections
                WHERE tenant_id = :tenant_id
                  AND status = 'active'
            ";

            $where = '';
            $params = ['tenant_id' => $tenantId];

            if ($classId > 0) {
                $where .= " AND class_id = :class_id";
                $params['class_id'] = $classId;
            }

            if ($academicYearId > 0) {
                $where .= " AND academic_year_id = :academic_year_id";
                $params['academic_year_id'] = $academicYearId;
            }

            $query = $pdo->prepare(
                $baseSelect . $where .
                " ORDER BY display_order, class_name_snapshot, section_name"
            );
            $query->execute($params);
            $records = $query->fetchAll(PDO::FETCH_ASSOC);
            $yearFallbackUsed = false;

            /*
             * Legacy databases sometimes contain valid class sections under an
             * older academic_year_id. When the exact class+year query returns no
             * rows, keep the dropdown usable by returning active sections for the
             * selected class. The response explicitly reports the fallback.
             */
            if (
                !$records
                && $classId > 0
                && $academicYearId > 0
            ) {
                $fallback = $pdo->prepare(
                    $baseSelect .
                    " AND class_id = :class_id
                      ORDER BY display_order, class_name_snapshot, section_name"
                );
                $fallback->execute([
                    'tenant_id' => $tenantId,
                    'class_id' => $classId,
                ]);
                $records = $fallback->fetchAll(PDO::FETCH_ASSOC);
                $yearFallbackUsed = !empty($records);
            }

            examJson(
                true,
                'Sections loaded successfully.',
                [
                    'sections' => $records,
                    'filters' => [
                        'class_id' => $classId,
                        'academic_year_id' => $academicYearId,
                    ],
                    'academic_year_fallback_used' => $yearFallbackUsed,
                ]
            );
        } catch (Throwable $e) {
            error_log('Examination sections: ' . $e->getMessage());
            examJson(false, 'Error loading sections: ' . $e->getMessage(), [], 500);
        }
    }

    // ============================================
    // LIST EXAMS
    // ============================================
    if ($action === 'list_exams') {
        $search = trim((string)($input['search'] ?? $_GET['search'] ?? ''));
        $examTypeId = (string)($input['exam_type_id'] ?? $_GET['exam_type_id'] ?? 'all');
        $status = (string)($input['status'] ?? $_GET['status'] ?? 'all');
        $academicYearId = (string)($input['academic_year_id'] ?? $_GET['academic_year_id'] ?? 'all');
        $classId = (string)($input['class_id'] ?? $_GET['class_id'] ?? 'all');
        $page = max(1, (int)($input['page'] ?? $_GET['page'] ?? 1));
        $perPage = (int)($input['per_page'] ?? $_GET['per_page'] ?? 10);
        $offset = ($page - 1) * $perPage;

        $where = ["e.tenant_id = :tenant_id"];
        $params = ['tenant_id' => $tenantId];

        if (!empty($search)) {
            $where[] = "(e.exam_name LIKE :search OR e.exam_no LIKE :search)";
            $params['search'] = '%' . $search . '%';
        }

        if ($examTypeId !== 'all' && !empty($examTypeId)) {
            $where[] = "e.exam_type_id = :exam_type_id";
            $params['exam_type_id'] = $examTypeId;
        }

        if ($status !== 'all' && !empty($status)) {
            $where[] = "e.exam_status = :status";
            $params['status'] = $status;
        }

        if ($academicYearId !== 'all' && !empty($academicYearId)) {
            $where[] = "e.academic_year_id = :academic_year_id";
            $params['academic_year_id'] = $academicYearId;
        }

        if ($classId !== 'all' && !empty($classId)) {
            $where[] = "e.class_id = :class_id";
            $params['class_id'] = $classId;
        }

        $whereClause = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*) FROM exams e WHERE {$whereClause}";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalRecords = (int)$countStmt->fetchColumn();

        $sql = "
            SELECT 
                e.*, 
                et.exam_type_name,
                (SELECT COUNT(*) FROM exam_schedules WHERE exam_id = e.id) AS schedule_count,
                (SELECT COUNT(*) FROM exam_results WHERE exam_id = e.id) AS result_count
            FROM exams e
            LEFT JOIN exam_types et ON et.id = e.exam_type_id
            WHERE {$whereClause}
            ORDER BY e.start_date DESC, e.id DESC
            LIMIT :offset, :per_page
        ";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue('per_page', $perPage, PDO::PARAM_INT);
        $stmt->execute();
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get filters data
        $types = $pdo->prepare("SELECT id, exam_type_name FROM exam_types WHERE tenant_id = :tenant_id ORDER BY exam_type_name");
        $types->execute(['tenant_id' => $tenantId]);
        $examTypes = $types->fetchAll(PDO::FETCH_ASSOC);

        try {
            $classes = $pdo->prepare("SELECT id, class_name FROM class_management_classes WHERE tenant_id = :tenant_id AND status = 'active' ORDER BY class_name");
            $classes->execute(['tenant_id' => $tenantId]);
            $classesList = $classes->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $classesList = [];
        }

        try {
            $academicYears = $pdo->prepare("SELECT id, year_name FROM academic_years WHERE tenant_id = :tenant_id ORDER BY is_current DESC");
            $academicYears->execute(['tenant_id' => $tenantId]);
            $academicYearsList = $academicYears->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $academicYearsList = [];
        }

        examJson(true, 'Exams loaded.', [
            'records' => $records,
            'pagination' => [
                'total' => $totalRecords,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => ceil($totalRecords / $perPage)
            ],
            'filters' => [
                'exam_types' => $examTypes,
                'classes' => $classesList,
                'academic_years' => $academicYearsList
            ],
            'csrf_token' => $csrfToken
        ]);
    }

    // ============================================
    // LIST EXAM TYPES
    // ============================================
    if ($action === 'list_exam_types') {
        $statement = $pdo->prepare("
            SELECT id, exam_type_name, exam_type_code, description, weightage, is_active
            FROM exam_types
            WHERE tenant_id = :tenant_id
            ORDER BY exam_type_name
        ");
        $statement->execute(['tenant_id' => $tenantId]);
        $records = $statement->fetchAll(PDO::FETCH_ASSOC);

        $formatted = array_map(function($row) {
            return [
                'id' => (int)$row['id'],
                'status' => $row['is_active'] ? 'active' : 'inactive',
                'data' => [
                    'exam_type_name' => $row['exam_type_name'],
                    'exam_type_code' => $row['exam_type_code'],
                    'description' => $row['description'],
                    'weightage' => $row['weightage'],
                    'is_active' => $row['is_active']
                ]
            ];
        }, $records);

        examJson(true, 'Exam types loaded.', ['csrf_token' => $csrfToken, 'records' => $formatted]);
    }

    // ============================================
    // GRADE SYSTEM
    // ============================================
    if ($action === 'grade_system') {
        $grades = [
            ['grade' => 'A+', 'min_percentage' => 90, 'max_percentage' => 100, 'grade_point' => 10, 'description' => 'Outstanding'],
            ['grade' => 'A', 'min_percentage' => 80, 'max_percentage' => 89, 'grade_point' => 9, 'description' => 'Excellent'],
            ['grade' => 'B+', 'min_percentage' => 70, 'max_percentage' => 79, 'grade_point' => 8, 'description' => 'Very Good'],
            ['grade' => 'B', 'min_percentage' => 60, 'max_percentage' => 69, 'grade_point' => 7, 'description' => 'Good'],
            ['grade' => 'C+', 'min_percentage' => 50, 'max_percentage' => 59, 'grade_point' => 6, 'description' => 'Above Average'],
            ['grade' => 'C', 'min_percentage' => 40, 'max_percentage' => 49, 'grade_point' => 5, 'description' => 'Average'],
            ['grade' => 'D', 'min_percentage' => 33, 'max_percentage' => 39, 'grade_point' => 4, 'description' => 'Pass'],
            ['grade' => 'F', 'min_percentage' => 0, 'max_percentage' => 32, 'grade_point' => 0, 'description' => 'Fail']
        ];
        
        $formatted = array_map(function($row) {
            return [
                'id' => null,
                'status' => 'active',
                'data' => $row
            ];
        }, $grades);

        examJson(true, 'Grade system loaded.', ['csrf_token' => $csrfToken, 'records' => $formatted]);
    }

    // ============================================
    // DASHBOARD STATS
    // ============================================
    if ($action === 'dashboard_stats') {
        $stats = $pdo->prepare("
            SELECT 
                COUNT(*) AS total_exams,
                COUNT(CASE WHEN exam_status = 'completed' THEN 1 END) AS completed_exams,
                COUNT(CASE WHEN exam_status = 'ongoing' THEN 1 END) AS ongoing_exams,
                COUNT(CASE WHEN exam_status = 'published' THEN 1 END) AS published_results
            FROM exams
            WHERE tenant_id = :tenant_id
        ");
        $stats->execute(['tenant_id' => $tenantId]);
        examJson(true, 'Dashboard stats loaded.', ['stats' => $stats->fetch(PDO::FETCH_ASSOC)]);
    }

    // ============================================
    // SAVE EXAM
    // ============================================
    if ($action === 'save_exam') {
        $id = (int)($input['id'] ?? 0);
        $data = is_array($input['data'] ?? null) ? $input['data'] : $input;
        
        if (empty($data['exam_name']) && isset($input['exam_name'])) {
            $data = $input;
        }
        
        $examName = trim((string)($data['exam_name'] ?? ''));
        $examTypeId = (int)($data['exam_type_id'] ?? 0);
        $academicYearId = !empty($data['academic_year_id']) ? (int)$data['academic_year_id'] : null;
        $classId = !empty($data['class_id']) ? (int)$data['class_id'] : null;
        $sectionId = !empty($data['section_id']) ? (int)$data['section_id'] : null;
        $startDate = trim((string)($data['start_date'] ?? ''));
        $endDate = trim((string)($data['end_date'] ?? ''));
        $examStatus = $data['exam_status'] ?? 'draft';
        $totalMarks = (float)($data['total_marks'] ?? 0);
        $passingMarks = (float)($data['passing_marks'] ?? 0);
        $description = trim((string)($data['description'] ?? ''));

        /*
         * Class selection is optional in the UI. If a section was selected,
         * its database class/year are authoritative and are used automatically.
         * This prevents an exam from being saved with a section that belongs to
         * another class or academic year.
         */
        if ($sectionId !== null) {
            $sectionStatement = $pdo->prepare("
                SELECT class_id, academic_year_id
                FROM school_sections
                WHERE id = :section_id
                  AND tenant_id = :tenant_id
                  AND status = 'active'
                LIMIT 1
            ");
            $sectionStatement->execute([
                'section_id' => $sectionId,
                'tenant_id' => $tenantId,
            ]);
            $selectedSection = $sectionStatement->fetch(PDO::FETCH_ASSOC);

            if (!$selectedSection) {
                examJson(false, 'The selected section is invalid or inactive.', [], 422);
            }

            $classId = (int)$selectedSection['class_id'];
            $sectionAcademicYearId = (int)$selectedSection['academic_year_id'];

            if ($academicYearId === null) {
                $academicYearId = $sectionAcademicYearId;
            } elseif ($sectionAcademicYearId > 0 && $academicYearId !== $sectionAcademicYearId) {
                examJson(false, 'The selected section does not belong to the selected academic year.', [], 422);
            }
        }

        if ($examName === '' || $startDate === '' || $endDate === '') {
            examJson(false, 'Exam name, start date and end date are required.', [], 422);
        }

        if ($examTypeId <= 0) {
            examJson(false, 'Please select an exam type.', [], 422);
        }

        if ($id > 0) {
            $statement = $pdo->prepare("
                UPDATE exams SET
                    exam_name = :exam_name,
                    exam_type_id = :exam_type_id,
                    academic_year_id = :academic_year_id,
                    class_id = :class_id,
                    section_id = :section_id,
                    start_date = :start_date,
                    end_date = :end_date,
                    exam_status = :exam_status,
                    total_marks = :total_marks,
                    passing_marks = :passing_marks,
                    description = :description,
                    updated_by = :updated_by
                WHERE id = :id AND tenant_id = :tenant_id
            ");
            $statement->execute([
                'exam_name' => $examName,
                'exam_type_id' => $examTypeId,
                'academic_year_id' => $academicYearId,
                'class_id' => $classId,
                'section_id' => $sectionId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'exam_status' => $examStatus,
                'total_marks' => $totalMarks,
                'passing_marks' => $passingMarks,
                'description' => $description,
                'updated_by' => $userId,
                'id' => $id,
                'tenant_id' => $tenantId
            ]);
            examJson(true, 'Exam updated successfully.', ['id' => $id]);
        } else {
            $prefix = 'EXAM-';
            $maxStmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(exam_no, '-', -1) AS UNSIGNED)) FROM exams WHERE tenant_id = :tenant_id");
            $maxStmt->execute(['tenant_id' => $tenantId]);
            $maxNum = (int)$maxStmt->fetchColumn();
            $nextNum = str_pad((string)($maxNum + 1), 6, "0", STR_PAD_LEFT);
            $examNo = $prefix . $nextNum;

            $statement = $pdo->prepare("
                INSERT INTO exams (
                    tenant_id, exam_no, exam_type_id, exam_name,
                    academic_year_id, class_id, section_id, start_date, end_date,
                    exam_status, total_marks, passing_marks, description, created_by
                ) VALUES (
                    :tenant_id, :exam_no, :exam_type_id, :exam_name,
                    :academic_year_id, :class_id, :section_id, :start_date, :end_date,
                    :exam_status, :total_marks, :passing_marks, :description, :created_by
                )
            ");
            $statement->execute([
                'tenant_id' => $tenantId,
                'exam_no' => $examNo,
                'exam_type_id' => $examTypeId,
                'exam_name' => $examName,
                'academic_year_id' => $academicYearId,
                'class_id' => $classId,
                'section_id' => $sectionId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'exam_status' => $examStatus,
                'total_marks' => $totalMarks,
                'passing_marks' => $passingMarks,
                'description' => $description,
                'created_by' => $userId
            ]);
            examJson(true, 'Exam created successfully.', ['id' => (int)$pdo->lastInsertId()]);
        }
    }

    // ============================================
    // DELETE EXAM
    // ============================================
    if ($action === 'delete_exam') {
        $id = (int)($input['id'] ?? 0);

        if ($id <= 0) {
            examJson(false, 'Invalid exam ID.', [], 422);
        }

        try {
            $pdo->beginTransaction();
            
            $pdo->prepare("DELETE em FROM exam_marks em JOIN exam_schedules es ON es.id = em.exam_schedule_id WHERE es.exam_id = :exam_id AND es.tenant_id = :tenant_id")
                ->execute(['exam_id' => $id, 'tenant_id' => $tenantId]);
            
            $pdo->prepare("DELETE FROM exam_schedules WHERE exam_id = :exam_id AND tenant_id = :tenant_id")
                ->execute(['exam_id' => $id, 'tenant_id' => $tenantId]);
            
            $pdo->prepare("DELETE FROM exam_results WHERE exam_id = :exam_id AND tenant_id = :tenant_id")
                ->execute(['exam_id' => $id, 'tenant_id' => $tenantId]);
            
            $pdo->prepare("DELETE FROM exams WHERE id = :id AND tenant_id = :tenant_id")
                ->execute(['id' => $id, 'tenant_id' => $tenantId]);
            
            $pdo->commit();
            examJson(true, 'Exam deleted successfully.');
        } catch (Exception $e) {
            $pdo->rollBack();
            examJson(false, 'Error deleting exam: ' . $e->getMessage(), [], 500);
        }
    }

    // ============================================
    // GET EXAM DETAIL
    // ============================================
    if ($action === 'exam_detail') {
        $id = (int)($input['id'] ?? $_GET['id'] ?? 0);

        $q = $pdo->prepare("
            SELECT e.*, et.exam_type_name
            FROM exams e
            LEFT JOIN exam_types et ON et.id = e.exam_type_id
            WHERE e.id = :id AND e.tenant_id = :tenant_id
        ");
        $q->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $data = $q->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            examJson(false, 'Exam not found.', [], 404);
        }

        examJson(true, 'Exam details loaded.', ['record' => $data]);
    }

    // ============================================
    // PROCESS RESULTS
    // ============================================
    if ($action === 'process_results') {
        $examId = (int)($input['id'] ?? 0);

        if ($examId <= 0) {
            examJson(false, 'Exam ID required.', [], 422);
        }

        $exam = $pdo->prepare("SELECT * FROM exams WHERE id = :id AND tenant_id = :tenant_id");
        $exam->execute(['id' => $examId, 'tenant_id' => $tenantId]);
        $examData = $exam->fetch(PDO::FETCH_ASSOC);

        if (!$examData) {
            examJson(false, 'Exam not found.', [], 404);
        }

        if (empty($examData['class_id']) && !empty($examData['section_id'])) {
            $sectionClass = $pdo->prepare("
                SELECT class_id
                FROM school_sections
                WHERE id = :section_id AND tenant_id = :tenant_id
                LIMIT 1
            ");
            $sectionClass->execute([
                'section_id' => (int)$examData['section_id'],
                'tenant_id' => $tenantId,
            ]);
            $resolvedClassId = (int)$sectionClass->fetchColumn();
            if ($resolvedClassId > 0) {
                $examData['class_id'] = $resolvedClassId;
            }
        }

        if (empty($examData['class_id'])) {
            examJson(false, 'Select a class or section before processing results.', [], 422);
        }

        $schedules = $pdo->prepare("SELECT id, subject_id, max_marks, passing_marks FROM exam_schedules WHERE exam_id = :exam_id AND tenant_id = :tenant_id");
        $schedules->execute(['exam_id' => $examId, 'tenant_id' => $tenantId]);
        $scheduleList = $schedules->fetchAll(PDO::FETCH_ASSOC);

        if (empty($scheduleList)) {
            examJson(false, 'No schedules found for this exam.', [], 422);
        }

        try {
            $students = $pdo->prepare("
                SELECT s.id, CONCAT(s.first_name, ' ', s.last_name) AS name
                FROM students s
                WHERE s.tenant_id = :tenant_id 
                  AND s.class_id = :class_id
                  AND s.status = 'active'
            ");
            $studentParams = ['tenant_id' => $tenantId, 'class_id' => $examData['class_id']];
            $students->execute($studentParams);
            $studentList = $students->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            examJson(false, 'Error fetching students: ' . $e->getMessage(), [], 500);
        }

        if (empty($studentList)) {
            examJson(false, 'No students found for this exam.', [], 422);
        }

        $pdo->beginTransaction();

        try {
            $scheduleIds = array_column($scheduleList, 'id');
            $placeholders = implode(',', array_fill(0, count($scheduleIds), '?'));

            foreach ($studentList as $student) {
                $marks = $pdo->prepare("
                    SELECT 
                        em.marks_obtained,
                        em.is_absent,
                        es.max_marks,
                        es.passing_marks
                    FROM exam_marks em
                    JOIN exam_schedules es ON es.id = em.exam_schedule_id
                    WHERE em.student_id = ? 
                        AND em.exam_schedule_id IN ({$placeholders})
                        AND em.tenant_id = ?
                ");

                $params = array_merge([$student['id']], $scheduleIds, [$tenantId]);
                $marks->execute($params);
                $markData = $marks->fetchAll(PDO::FETCH_ASSOC);

                $totalObtained = 0;
                $totalMax = 0;
                $isPassed = true;

                foreach ($markData as $md) {
                    if (!$md['is_absent'] && $md['marks_obtained'] !== null) {
                        $totalObtained += (float)$md['marks_obtained'];
                        $totalMax += (float)$md['max_marks'];
                        if ((float)$md['marks_obtained'] < (float)$md['passing_marks']) {
                            $isPassed = false;
                        }
                    } else {
                        $isPassed = false;
                    }
                }

                $percentage = $totalMax > 0 ? ($totalObtained / $totalMax) * 100 : 0;

                $grade = '';
                $gradePoint = 0;
                if ($percentage >= 90) { $grade = 'A+'; $gradePoint = 10; }
                elseif ($percentage >= 80) { $grade = 'A'; $gradePoint = 9; }
                elseif ($percentage >= 70) { $grade = 'B+'; $gradePoint = 8; }
                elseif ($percentage >= 60) { $grade = 'B'; $gradePoint = 7; }
                elseif ($percentage >= 50) { $grade = 'C+'; $gradePoint = 6; }
                elseif ($percentage >= 40) { $grade = 'C'; $gradePoint = 5; }
                elseif ($percentage >= 33) { $grade = 'D'; $gradePoint = 4; }
                else { $grade = 'F'; $gradePoint = 0; }

                $result = $pdo->prepare("
                    INSERT INTO exam_results (
                        tenant_id, exam_id, student_id, total_marks, obtained_marks,
                        percentage, grade, grade_point, is_passed, result_status
                    ) VALUES (
                        :tenant_id, :exam_id, :student_id, :total_marks, :obtained_marks,
                        :percentage, :grade, :grade_point, :is_passed, 'processed'
                    ) ON DUPLICATE KEY UPDATE
                        total_marks = :total_marks,
                        obtained_marks = :obtained_marks,
                        percentage = :percentage,
                        grade = :grade,
                        grade_point = :grade_point,
                        is_passed = :is_passed,
                        result_status = 'processed',
                        updated_at = CURRENT_TIMESTAMP
                ");
                $result->execute([
                    'tenant_id' => $tenantId,
                    'exam_id' => $examId,
                    'student_id' => $student['id'],
                    'total_marks' => $totalMax,
                    'obtained_marks' => $totalObtained,
                    'percentage' => $percentage,
                    'grade' => $grade,
                    'grade_point' => $gradePoint,
                    'is_passed' => $isPassed ? 1 : 0
                ]);
            }

            $rankQuery = $pdo->prepare("
                SELECT id, obtained_marks 
                FROM exam_results 
                WHERE exam_id = :exam_id AND tenant_id = :tenant_id
                ORDER BY obtained_marks DESC
            ");
            $rankQuery->execute(['exam_id' => $examId, 'tenant_id' => $tenantId]);
            $results = $rankQuery->fetchAll(PDO::FETCH_ASSOC);

            $rank = 1;
            $prevMarks = null;
            $rankCount = 0;
            foreach ($results as $row) {
                $rankCount++;
                if ($prevMarks !== null && $row['obtained_marks'] < $prevMarks) {
                    $rank = $rankCount;
                }
                $pdo->prepare("UPDATE exam_results SET rank = :rank WHERE id = :id AND tenant_id = :tenant_id")
                    ->execute(['rank' => $rank, 'id' => $row['id'], 'tenant_id' => $tenantId]);
                $prevMarks = $row['obtained_marks'];
            }

            $pdo->prepare("UPDATE exams SET exam_status = 'completed' WHERE id = :id AND tenant_id = :tenant_id")
                ->execute(['id' => $examId, 'tenant_id' => $tenantId]);

            $pdo->commit();
            examJson(true, 'Results processed successfully.');
        } catch (Exception $e) {
            $pdo->rollBack();
            examJson(false, 'Error processing results: ' . $e->getMessage(), [], 500);
        }
    }

    // ============================================
    // PUBLISH RESULTS
    // ============================================
    if ($action === 'publish_results') {
        $examId = (int)($input['id'] ?? 0);

        if ($examId <= 0) {
            examJson(false, 'Exam ID required.', [], 422);
        }

        try {
            $q = $pdo->prepare("UPDATE exam_results SET result_status = 'published' WHERE exam_id = :exam_id AND tenant_id = :tenant_id");
            $q->execute(['exam_id' => $examId, 'tenant_id' => $tenantId]);

            $q2 = $pdo->prepare("UPDATE exams SET exam_status = 'published' WHERE id = :id AND tenant_id = :tenant_id");
            $q2->execute(['id' => $examId, 'tenant_id' => $tenantId]);

            examJson(true, 'Results published successfully.');
        } catch (Exception $e) {
            examJson(false, 'Error publishing results: ' . $e->getMessage(), [], 500);
        }
    }

    // ============================================
    // DEFAULT RESPONSE
    // ============================================
    examJson(false, 'Invalid action.', [], 400);

} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Examinations API Error: ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());
    examJson(false, 'Examination request failed: ' . $exception->getMessage(), [], 500);
}
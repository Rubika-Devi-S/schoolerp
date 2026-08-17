<?php
declare(strict_types=1);

/*
 * Parent Dashboard API
 * Location: parent/api/dashboard.php
 * Build: 2026-08-13-parent-permission-chain-v23
 */

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/sidebar-manager.php';
require_once dirname(__DIR__, 2) . '/includes/permission-chain.php';

if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('Asia/Kolkata');
}

function pdJson(bool $success, string $message = '', array $data = [], int $status = 200): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }

    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}

function pdUser(): array
{
    $user = function_exists('current_user') ? current_user() : [];
    return is_array($user) ? $user : [];
}

function pdScope(): array
{
    $user = pdUser();

    return [
        'tenant_id' => (int)(
            $user['tenant_id']
            ?? $user['school_id']
            ?? $_SESSION['tenant_id']
            ?? $_SESSION['school_id']
            ?? 0
        ),
        'branch_id' => (int)(
            $user['default_branch_id']
            ?? $user['branch_id']
            ?? $_SESSION['branch_id']
            ?? 0
        ),
        'user_id' => (int)(
            $user['id']
            ?? $user['user_id']
            ?? $_SESSION['user_id']
            ?? 0
        ),
        'email' => trim((string)(
            $user['email']
            ?? $_SESSION['email']
            ?? $_SESSION['user_email']
            ?? ''
        )),
        'mobile' => trim((string)(
            $user['mobile']
            ?? $_SESSION['mobile']
            ?? $_SESSION['user_mobile']
            ?? ''
        )),
    ];
}

function pdTableExists(PDO $pdo, string $table): bool
{
    static $cache = [];

    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = :table_name'
    );
    $stmt->execute(['table_name' => $table]);

    return $cache[$table] = ((int)$stmt->fetchColumn() > 0);
}

function pdColumns(PDO $pdo, string $table): array
{
    static $cache = [];

    if (isset($cache[$table])) {
        return $cache[$table];
    }

    if (!pdTableExists($pdo, $table)) {
        return $cache[$table] = [];
    }

    $quoted = str_replace('`', '``', $table);
    $rows = $pdo->query("SHOW COLUMNS FROM `{$quoted}`")->fetchAll(PDO::FETCH_ASSOC);

    $columns = [];
    foreach ($rows as $row) {
        $columns[] = (string)$row['Field'];
    }

    return $cache[$table] = $columns;
}

function pdMobile(string $value): string
{
    return preg_replace('/\D+/', '', $value) ?? '';
}

function pdGuardian(PDO $pdo, array $scope): array
{
    $tenantId=(int)$scope['tenant_id'];$userId=(int)$scope['user_id'];
    if($tenantId<=0)throw new RuntimeException('School session was not found.',401);

    if($userId>0&&pdTableExists($pdo,'student_parent_logins')){
        $q=$pdo->prepare(
            "SELECT DISTINCT g.id,g.tenant_id,g.guardian_name,g.relationship,g.mobile,g.email,g.occupation,g.address
             FROM student_parent_logins spl
             INNER JOIN guardians g ON g.id=spl.guardian_id AND g.tenant_id=spl.tenant_id
             WHERE spl.tenant_id=:tenant_id AND spl.user_id=:user_id AND spl.status='active'
             ORDER BY spl.id LIMIT 1"
        );
        $q->execute(['tenant_id'=>$tenantId,'user_id'=>$userId]);
        if($g=$q->fetch(PDO::FETCH_ASSOC)){$_SESSION['guardian_id']=(int)$g['id'];return $g;}
    }

    if($userId>0&&in_array('user_id',pdColumns($pdo,'guardians'),true)){
        $q=$pdo->prepare(
            "SELECT id,tenant_id,guardian_name,relationship,mobile,email,occupation,address
             FROM guardians WHERE tenant_id=:tenant_id AND user_id=:user_id ORDER BY id LIMIT 1"
        );
        $q->execute(['tenant_id'=>$tenantId,'user_id'=>$userId]);
        if($g=$q->fetch(PDO::FETCH_ASSOC)){$_SESSION['guardian_id']=(int)$g['id'];return $g;}
    }

    $guardianId=(int)($_SESSION['guardian_id']??$_SESSION['parent_guardian_id']??$_SESSION['parent_id']??0);
    if($guardianId>0){
        $q=$pdo->prepare(
            "SELECT id,tenant_id,guardian_name,relationship,mobile,email,occupation,address
             FROM guardians WHERE id=:id AND tenant_id=:tenant_id LIMIT 1"
        );
        $q->execute(['id'=>$guardianId,'tenant_id'=>$tenantId]);
        if($g=$q->fetch(PDO::FETCH_ASSOC))return $g;
    }

    $email=strtolower(trim((string)$scope['email']));$mobile=pdMobile((string)$scope['mobile']);
    if($email===''&&$mobile==='')throw new RuntimeException('This login is not linked to a Parent/Guardian record.',403);

    $q=$pdo->prepare(
        "SELECT id,tenant_id,guardian_name,relationship,mobile,email,occupation,address
         FROM guardians WHERE tenant_id=:tenant_id ORDER BY id"
    );
    $q->execute(['tenant_id'=>$tenantId]);
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $g){
        $ge=strtolower(trim((string)($g['email']??'')));$gm=pdMobile((string)($g['mobile']??''));
        if(($email!==''&&$ge!==''&&$ge===$email)||($mobile!==''&&$gm!==''&&$gm===$mobile)){
            $_SESSION['guardian_id']=(int)$g['id'];return $g;
        }
    }
    throw new RuntimeException('No Parent/Guardian record matches this login.',403);
}

function pdMappedStudentIds(PDO $pdo,int $tenantId,int $userId): array
{
    if($tenantId<=0||$userId<=0||!pdTableExists($pdo,'student_parent_logins'))return [];
    $q=$pdo->prepare("SELECT DISTINCT student_id FROM student_parent_logins
        WHERE tenant_id=:tenant_id AND user_id=:user_id AND status='active'");
    $q->execute(['tenant_id'=>$tenantId,'user_id'=>$userId]);
    return array_values(array_filter(array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN)),fn(int $id)=>$id>0));
}

function pdParentMenuKeys(PDO $pdo,array $scope): array
{
    $u=pdUser();$roleId=(int)($u['role_id']??$_SESSION['role_id']??0);
    if($roleId<=0)return [];
    $items=school_sidebar_get_items($pdo,$roleId,(int)$scope['tenant_id']);
    return array_values(array_unique(array_filter(array_map(
        fn(array $i)=>(string)($i['menu_key']??''),$items
    ))));
}

function pdChildren(PDO $pdo, int $tenantId, int $guardianId, int $userId = 0): array
{
    $stmt = $pdo->prepare(
        "SELECT
            s.id,
            s.tenant_id,
            s.branch_id,
            s.admission_no,
            s.emis_no,
            TRIM(CONCAT_WS(' ', s.first_name, s.last_name)) AS student_name,
            s.first_name,
            s.last_name,
            s.gender,
            s.date_of_birth,
            s.blood_group,
            s.mobile,
            s.email,
            s.address,
            s.photo_path,
            s.admission_date,
            s.status,
            sg.is_primary,
            se.id AS enrollment_id,
            se.academic_year_id,
            ay.year_name AS academic_year_name,
            ay.start_date AS academic_year_start,
            ay.end_date AS academic_year_end,
            se.class_id,
            c.class_name,
            se.section_id,
            sec.section_name,
            se.roll_no,
            se.enrollment_status,
            se.enrolled_on,
            spe.notes AS profile_notes
         FROM student_guardians sg
         INNER JOIN students s
            ON s.id = sg.student_id
           AND s.tenant_id = :tenant_id
         LEFT JOIN student_enrollments se
            ON se.id = (
                SELECT se2.id
                FROM student_enrollments se2
                INNER JOIN academic_years ay2
                   ON ay2.id = se2.academic_year_id
                  AND ay2.tenant_id = se2.tenant_id
                WHERE se2.tenant_id = s.tenant_id
                  AND se2.student_id = s.id
                ORDER BY
                    ay2.start_date DESC,
                    FIELD(se2.enrollment_status, 'active', 'promoted', 'transferred', 'completed'),
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
         LEFT JOIN student_profile_extras spe
            ON spe.tenant_id = s.tenant_id
           AND spe.student_id = s.id
         WHERE sg.guardian_id = :guardian_id
           AND s.deleted_at IS NULL
         ORDER BY sg.is_primary DESC, s.first_name, s.last_name, s.id"
    );
    $stmt->execute([
        'tenant_id' => $tenantId,
        'guardian_id' => $guardianId,
    ]);

    $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    $mapped=pdMappedStudentIds($pdo,$tenantId,$userId);
    if($mapped!==[]){
        $allowed=array_fill_keys($mapped,true);
        $rows=array_values(array_filter($rows,fn(array $row)=>isset($allowed[(int)($row['id']??0)])));
    }
    return $rows;
}

function pdSelectedChild(array $children, int $requestedStudentId): array
{
    if (!$children) {
        throw new RuntimeException(
            'No students are linked to this Parent/Guardian account.',
            404
        );
    }

    if ($requestedStudentId > 0) {
        foreach ($children as $child) {
            if ((int)$child['id'] === $requestedStudentId) {
                $_SESSION['parent_selected_student_id'] = $requestedStudentId;
                return $child;
            }
        }

        throw new RuntimeException(
            'The selected student is not linked to this Parent/Guardian account.',
            403
        );
    }

    $sessionId = (int)($_SESSION['parent_selected_student_id'] ?? 0);
    if ($sessionId > 0) {
        foreach ($children as $child) {
            if ((int)$child['id'] === $sessionId) {
                return $child;
            }
        }
    }

    $_SESSION['parent_selected_student_id'] = (int)$children[0]['id'];
    return $children[0];
}

function pdAttendance(PDO $pdo, int $tenantId, array $child): array
{
    $yearId = (int)($child['academic_year_id'] ?? 0);
    $studentId = (int)$child['id'];

    if ($yearId <= 0) {
        return [
            'percentage' => 0,
            'working_days' => 0,
            'present_days' => 0,
            'absent_days' => 0,
            'leave_days' => 0,
            'late_days' => 0,
            'recent' => [],
        ];
    }

    $stmt = $pdo->prepare(
        "SELECT
            SUM(status <> 'holiday') AS working_days,
            SUM(
                CASE
                    WHEN status IN ('present','late','on_duty') THEN 1
                    WHEN status = 'half_day' THEN 0.5
                    ELSE 0
                END
            ) AS present_days,
            SUM(status = 'absent') AS absent_days,
            SUM(status = 'leave') AS leave_days,
            SUM(status = 'late') AS late_days
         FROM student_attendance
         WHERE tenant_id = :tenant_id
           AND student_id = :student_id
           AND academic_year_id = :academic_year_id"
    );
    $stmt->execute([
        'tenant_id' => $tenantId,
        'student_id' => $studentId,
        'academic_year_id' => $yearId,
    ]);

    $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $workingDays = (float)($summary['working_days'] ?? 0);
    $presentDays = (float)($summary['present_days'] ?? 0);

    $recent = $pdo->prepare(
        "SELECT attendance_date, status, check_in, check_out, late_minutes, remarks
         FROM student_attendance
         WHERE tenant_id = :tenant_id
           AND student_id = :student_id
           AND academic_year_id = :academic_year_id
         ORDER BY attendance_date DESC, id DESC
         LIMIT 10"
    );
    $recent->execute([
        'tenant_id' => $tenantId,
        'student_id' => $studentId,
        'academic_year_id' => $yearId,
    ]);

    return [
        'percentage' => $workingDays > 0
            ? round(($presentDays / $workingDays) * 100, 1)
            : 0,
        'working_days' => (int)$workingDays,
        'present_days' => round($presentDays, 1),
        'absent_days' => (int)($summary['absent_days'] ?? 0),
        'leave_days' => (int)($summary['leave_days'] ?? 0),
        'late_days' => (int)($summary['late_days'] ?? 0),
        'recent' => $recent->fetchAll(PDO::FETCH_ASSOC),
    ];
}

function pdFees(PDO $pdo, int $tenantId, array $child): array
{
    $studentId = (int)$child['id'];
    $yearId = (int)($child['academic_year_id'] ?? 0);

    if ($yearId <= 0) {
        return [
            'assignment' => null,
            'breakdown' => [],
            'upcoming' => [],
            'receipts' => [],
        ];
    }

    $stmt = $pdo->prepare(
        "SELECT sfa.*, fs.structure_name
         FROM student_fee_assignments sfa
         INNER JOIN fee_structures fs
            ON fs.id = sfa.fee_structure_id
           AND fs.tenant_id = sfa.tenant_id
         WHERE sfa.tenant_id = :tenant_id
           AND sfa.student_id = :student_id
           AND sfa.academic_year_id = :academic_year_id
           AND sfa.assignment_status = 'active'
         ORDER BY sfa.id DESC
         LIMIT 1"
    );
    $stmt->execute([
        'tenant_id' => $tenantId,
        'student_id' => $studentId,
        'academic_year_id' => $yearId,
    ]);
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $breakdown = [];
    $upcoming = [];

    if ($assignment) {
        $breakdownStmt = $pdo->prepare(
            "SELECT
                item_type,
                SUM(original_amount) AS gross_amount,
                SUM(discount_amount) AS discount_amount,
                SUM(paid_amount) AS paid_amount,
                SUM(balance_amount) AS balance_amount
             FROM student_fee_items
             WHERE tenant_id = :tenant_id
               AND assignment_id = :assignment_id
               AND item_status NOT IN ('cancelled','carried_forward')
             GROUP BY item_type
             ORDER BY FIELD(
                item_type,
                'admission','tuition','term','additional','transport','previous_due'
             )"
        );
        $breakdownStmt->execute([
            'tenant_id' => $tenantId,
            'assignment_id' => (int)$assignment['id'],
        ]);
        $breakdown = $breakdownStmt->fetchAll(PDO::FETCH_ASSOC);

        $dueStmt = $pdo->prepare(
            "SELECT
                id, item_type, item_name, period_label, due_date,
                original_amount, discount_amount, paid_amount,
                balance_amount, item_status
             FROM student_fee_items
             WHERE tenant_id = :tenant_id
               AND assignment_id = :assignment_id
               AND balance_amount > 0.009
               AND item_status NOT IN ('cancelled','carried_forward','waived')
             ORDER BY due_date, id
             LIMIT 10"
        );
        $dueStmt->execute([
            'tenant_id' => $tenantId,
            'assignment_id' => (int)$assignment['id'],
        ]);
        $upcoming = $dueStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $receiptStmt = $pdo->prepare(
        "SELECT
            id, receipt_no, receipt_date, gross_amount,
            discount_amount, fine_amount, paid_amount, payment_status
         FROM fee_receipts
         WHERE tenant_id = :tenant_id
           AND student_id = :student_id
           AND academic_year_id = :academic_year_id
         ORDER BY receipt_date DESC, id DESC
         LIMIT 6"
    );
    $receiptStmt->execute([
        'tenant_id' => $tenantId,
        'student_id' => $studentId,
        'academic_year_id' => $yearId,
    ]);

    return [
        'assignment' => $assignment,
        'breakdown' => $breakdown,
        'upcoming' => $upcoming,
        'receipts' => $receiptStmt->fetchAll(PDO::FETCH_ASSOC),
    ];
}

function pdExams(PDO $pdo, int $tenantId, array $child): array
{
    $studentId = (int)$child['id'];
    $yearId = (int)($child['academic_year_id'] ?? 0);
    $classId = (int)($child['class_id'] ?? 0);
    $sectionId = (int)($child['section_id'] ?? 0);

    if ($yearId <= 0 || $classId <= 0) {
        return [
            'results' => [],
            'subject_marks' => [],
            'upcoming' => [],
        ];
    }

    $results = $pdo->prepare(
        "SELECT
            er.id, e.id AS exam_id, e.exam_name, e.start_date, e.end_date,
            er.total_marks, er.obtained_marks, er.percentage,
            er.grade, er.grade_point, er.rank, er.is_passed, er.result_status
         FROM exam_results er
         INNER JOIN exams e
            ON e.id = er.exam_id
           AND e.tenant_id = er.tenant_id
         WHERE er.tenant_id = :tenant_id
           AND er.student_id = :student_id
           AND e.academic_year_id = :academic_year_id
           AND e.class_id = :class_id
           AND (e.section_id IS NULL OR e.section_id = :section_id)
           AND er.result_status = 'published'
         ORDER BY e.end_date DESC, er.id DESC
         LIMIT 6"
    );
    $results->execute([
        'tenant_id' => $tenantId,
        'student_id' => $studentId,
        'academic_year_id' => $yearId,
        'class_id' => $classId,
        'section_id' => $sectionId,
    ]);
    $resultRows = $results->fetchAll(PDO::FETCH_ASSOC);

    $subjectMarks = $pdo->prepare(
        "SELECT
            e.exam_name,
            es.exam_date,
            ss.subject_name,
            es.max_marks,
            em.marks_obtained,
            em.grade,
            em.grade_point,
            em.is_absent,
            em.remarks
         FROM exam_marks em
         INNER JOIN exam_schedules es
            ON es.id = em.exam_schedule_id
           AND es.tenant_id = em.tenant_id
         INNER JOIN exams e
            ON e.id = es.exam_id
           AND e.tenant_id = em.tenant_id
         LEFT JOIN school_subjects ss
            ON ss.id = es.subject_id
           AND ss.tenant_id = em.tenant_id
         WHERE em.tenant_id = :tenant_id
           AND em.student_id = :student_id
           AND e.academic_year_id = :academic_year_id
           AND e.class_id = :class_id
           AND (e.section_id IS NULL OR e.section_id = :section_id)
           AND e.exam_status IN ('completed','published')
         ORDER BY es.exam_date DESC, es.id DESC
         LIMIT 10"
    );
    $subjectMarks->execute([
        'tenant_id' => $tenantId,
        'student_id' => $studentId,
        'academic_year_id' => $yearId,
        'class_id' => $classId,
        'section_id' => $sectionId,
    ]);

    $upcoming = $pdo->prepare(
        "SELECT
            es.id, e.exam_name, es.exam_date, es.start_time, es.end_time,
            es.room_no, es.max_marks, es.passing_marks,
            COALESCE(ss.subject_name, CONCAT('Subject #', es.subject_id)) AS subject_name
         FROM exam_schedules es
         INNER JOIN exams e
            ON e.id = es.exam_id
           AND e.tenant_id = es.tenant_id
         LEFT JOIN school_subjects ss
            ON ss.id = es.subject_id
           AND ss.tenant_id = es.tenant_id
         WHERE es.tenant_id = :tenant_id
           AND e.academic_year_id = :academic_year_id
           AND e.class_id = :class_id
           AND (e.section_id IS NULL OR e.section_id = :section_id)
           AND es.exam_date >= CURDATE()
           AND e.exam_status IN ('scheduled','ongoing')
         ORDER BY es.exam_date, es.start_time, es.id
         LIMIT 10"
    );
    $upcoming->execute([
        'tenant_id' => $tenantId,
        'academic_year_id' => $yearId,
        'class_id' => $classId,
        'section_id' => $sectionId,
    ]);

    return [
        'results' => $resultRows,
        'subject_marks' => $subjectMarks->fetchAll(PDO::FETCH_ASSOC),
        'upcoming' => $upcoming->fetchAll(PDO::FETCH_ASSOC),
    ];
}

function pdTimetable(PDO $pdo, int $tenantId, array $child): array
{
    $yearId = (int)($child['academic_year_id'] ?? 0);
    $classId = (int)($child['class_id'] ?? 0);
    $sectionId = (int)($child['section_id'] ?? 0);
    $branchId = (int)($child['branch_id'] ?? 0);
    $dayName = date('l');

    if ($yearId <= 0 || $classId <= 0) {
        return ['day_name' => $dayName, 'entries' => []];
    }

    $stmt = $pdo->prepare(
        "SELECT
            te.id, te.period_name, te.subject_name, te.teacher_name,
            te.room_name, tp.start_time, tp.end_time, tp.display_order
         FROM timetable_entries te
         LEFT JOIN timetable_periods tp
            ON tp.id = te.period_id
           AND tp.tenant_id = te.tenant_id
         WHERE te.tenant_id = :tenant_id
           AND (:branch_zero = 0 OR te.branch_id IS NULL OR te.branch_id = :branch_match)
           AND te.academic_year_id = :academic_year_id
           AND te.class_id = :class_id
           AND (te.section_id IS NULL OR te.section_id = :section_id)
           AND LOWER(te.day_name) = LOWER(:day_name)
           AND te.status = 'approved'
         ORDER BY COALESCE(tp.display_order, 9999), tp.start_time, te.period_id, te.id"
    );
    $stmt->execute([
        'tenant_id' => $tenantId,
        'branch_id' => $branchId,
        'academic_year_id' => $yearId,
        'class_id' => $classId,
        'section_id' => $sectionId,
        'day_name' => $dayName,
    ]);

    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$entries && pdTableExists($pdo, 'class_timetable_entries')) {
        $fallback = $pdo->prepare(
            "SELECT
                id,
                CONCAT('Period ', period_no) AS period_name,
                subject_name,
                teacher_name,
                classroom_name AS room_name,
                start_time,
                end_time,
                period_no AS display_order
             FROM class_timetable_entries
             WHERE tenant_id = :tenant_id
               AND class_id = :class_id
               AND LOWER(day_name) = LOWER(:day_name)
               AND status = 'active'
             ORDER BY period_no, start_time, id"
        );
        $fallback->execute([
            'tenant_id' => $tenantId,
            'class_id' => $classId,
            'day_name' => $dayName,
        ]);

        $entries = $fallback->fetchAll(PDO::FETCH_ASSOC);
    }

    return [
        'day_name' => $dayName,
        'entries' => $entries,
    ];
}

function pdTransport(PDO $pdo, int $tenantId, array $child): ?array
{
    $yearId = (int)($child['academic_year_id'] ?? 0);

    if ($yearId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT
            sta.*,
            sr.route_code,
            sr.start_point,
            sr.end_point,
            sv.vehicle_number,
            sv.registration_number,
            sv.driver_mobile
         FROM student_transport_assignments sta
         LEFT JOIN school_routes sr
            ON sr.id = sta.route_id
           AND sr.tenant_id = sta.tenant_id
         LEFT JOIN school_vehicles sv
            ON sv.id = sta.vehicle_id
           AND sv.tenant_id = sta.tenant_id
         WHERE sta.tenant_id = :tenant_id
           AND sta.student_id = :student_id
           AND sta.academic_year_id = :academic_year_id
           AND sta.status = 'active'
         ORDER BY sta.id DESC
         LIMIT 1"
    );
    $stmt->execute([
        'tenant_id' => $tenantId,
        'student_id' => (int)$child['id'],
        'academic_year_id' => $yearId,
    ]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function pdLeaveRequests(PDO $pdo, int $tenantId, array $child): array
{
    if (!pdTableExists($pdo, 'attendance_change_requests')) {
        return [
            'configured' => false,
            'rows' => [],
        ];
    }

    $stmt = $pdo->prepare(
        "SELECT
            acr.id,
            acr.requested_status,
            acr.reason,
            acr.status,
            acr.decided_at,
            sa.attendance_date,
            sa.status AS attendance_status
         FROM attendance_change_requests acr
         LEFT JOIN student_attendance sa
            ON sa.id = acr.attendance_id
           AND sa.tenant_id = acr.tenant_id
         WHERE acr.tenant_id = :tenant_id
           AND acr.student_id = :student_id
           AND LOWER(acr.requested_status) = 'leave'
         ORDER BY
            COALESCE(sa.attendance_date, DATE(acr.decided_at)) DESC,
            acr.id DESC
         LIMIT 8"
    );
    $stmt->execute([
        'tenant_id' => $tenantId,
        'student_id' => (int)$child['id'],
    ]);

    return [
        'configured' => true,
        'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC),
    ];
}

function pdHomework(PDO $pdo, int $tenantId, array $child): array
{
    $candidates = [
        'homework',
        'homeworks',
        'student_homework',
        'homework_assignments',
    ];

    $table = '';
    foreach ($candidates as $candidate) {
        if (pdTableExists($pdo, $candidate)) {
            $table = $candidate;
            break;
        }
    }

    if ($table === '') {
        return [
            'configured' => false,
            'table' => null,
            'rows' => [],
        ];
    }

    $columns = pdColumns($pdo, $table);
    $where = [];
    $params = [];

    if (in_array('tenant_id', $columns, true)) {
        $where[] = 'tenant_id = :tenant_id';
        $params['tenant_id'] = $tenantId;
    }
    if (in_array('student_id', $columns, true)) {
        $where[] = 'student_id = :student_id';
        $params['student_id'] = (int)$child['id'];
    }
    if (in_array('academic_year_id', $columns, true) && (int)($child['academic_year_id'] ?? 0) > 0) {
        $where[] = 'academic_year_id = :academic_year_id';
        $params['academic_year_id'] = (int)$child['academic_year_id'];
    }
    if (in_array('class_id', $columns, true) && (int)($child['class_id'] ?? 0) > 0) {
        $where[] = 'class_id = :class_id';
        $params['class_id'] = (int)$child['class_id'];
    }
    if (in_array('section_id', $columns, true) && (int)($child['section_id'] ?? 0) > 0) {
        $where[] = '(section_id IS NULL OR section_id = :section_id)';
        $params['section_id'] = (int)$child['section_id'];
    }

    $orderColumn = 'id';
    foreach (['due_date', 'submission_date', 'assigned_date', 'homework_date', 'created_at', 'id'] as $candidate) {
        if (in_array($candidate, $columns, true)) {
            $orderColumn = $candidate;
            break;
        }
    }

    $quotedTable = str_replace('`', '``', $table);
    $quotedOrder = str_replace('`', '``', $orderColumn);
    $sql = "SELECT * FROM `{$quotedTable}`";
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= " ORDER BY `{$quotedOrder}` DESC LIMIT 10";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $first = static function (array $source, array $keys, $default = '') {
            foreach ($keys as $key) {
                if (array_key_exists($key, $source) && $source[$key] !== null && $source[$key] !== '') {
                    return $source[$key];
                }
            }
            return $default;
        };

        $rows[] = [
            'id' => (int)($row['id'] ?? 0),
            'title' => (string)$first($row, ['title', 'homework_title', 'task_name', 'name'], 'Homework'),
            'subject' => (string)$first($row, ['subject_name', 'subject', 'course_name'], ''),
            'description' => (string)$first($row, ['description', 'homework', 'content', 'details', 'instructions'], ''),
            'assigned_date' => (string)$first($row, ['assigned_date', 'homework_date', 'created_at'], ''),
            'due_date' => (string)$first($row, ['due_date', 'submission_date', 'deadline'], ''),
            'status' => (string)$first($row, ['status', 'homework_status', 'submission_status'], 'assigned'),
        ];
    }

    return [
        'configured' => true,
        'table' => $table,
        'rows' => $rows,
    ];
}

function pdCalendarUpdates(PDO $pdo, int $tenantId, array $child): array
{
    if (!pdTableExists($pdo, 'academic_year_module_records')) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT id, module_key, record_data, created_at
         FROM academic_year_module_records
         WHERE tenant_id = :tenant_id
           AND status = 'active'
           AND module_key IN ('academic_calendar','holidays')
         ORDER BY created_at DESC, id DESC
         LIMIT 12"
    );
    $stmt->execute(['tenant_id' => $tenantId]);

    $yearId = (int)($child['academic_year_id'] ?? 0);
    $rows = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $record = json_decode((string)$row['record_data'], true);
        if (!is_array($record)) {
            continue;
        }

        if (
            isset($record['academic_year_id'])
            && $yearId > 0
            && (int)$record['academic_year_id'] !== $yearId
        ) {
            continue;
        }

        if ($row['module_key'] === 'academic_calendar') {
            $rows[] = [
                'type' => 'calendar',
                'title' => (string)($record['event_name'] ?? 'School Calendar'),
                'body' => (string)($record['description'] ?? ''),
                'event_date' => (string)($record['event_date'] ?? ''),
                'category' => (string)($record['event_type'] ?? 'event'),
                'created_at' => (string)$row['created_at'],
            ];
        } elseif ($row['module_key'] === 'holidays') {
            $rows[] = [
                'type' => 'holiday',
                'title' => (string)($record['holiday_name'] ?? 'Holiday'),
                'body' => (string)($record['description'] ?? ''),
                'event_date' => (string)($record['holiday_date'] ?? ''),
                'category' => (string)($record['holiday_type'] ?? 'holiday'),
                'created_at' => (string)$row['created_at'],
            ];
        }
    }

    usort($rows, static function (array $a, array $b): int {
        return strcmp((string)($b['event_date'] ?? ''), (string)($a['event_date'] ?? ''));
    });

    return array_slice($rows, 0, 8);
}

function pdNotifications(PDO $pdo, int $tenantId, int $userId): array
{
    if ($userId <= 0 || !pdTableExists($pdo, 'notifications')) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT id, title, body, route, read_at, created_at
         FROM notifications
         WHERE tenant_id = :tenant_id
           AND user_id = :user_id
         ORDER BY created_at DESC, id DESC
         LIMIT 8"
    );
    $stmt->execute([
        'tenant_id' => $tenantId,
        'user_id' => $userId,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function pdMessages(PDO $pdo, int $tenantId, int $userId): array
{
    if ($userId <= 0 || !pdTableExists($pdo, 'user_messages')) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT
            um.id, um.subject, um.message, um.read_at, um.created_at,
            sender.name AS sender_name
         FROM user_messages um
         LEFT JOIN users sender
            ON sender.id = um.sender_user_id
           AND sender.tenant_id = um.tenant_id
         WHERE um.tenant_id = :tenant_id
           AND um.receiver_user_id = :user_id
         ORDER BY um.created_at DESC, um.id DESC
         LIMIT 8"
    );
    $stmt->execute([
        'tenant_id' => $tenantId,
        'user_id' => $userId,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    pdJson(false, 'Database connection unavailable.', [], 500);
}

try {
    $scope = pdScope();
    $role=pc_role($pdo,(int)$scope['tenant_id'],(int)(pdUser()['role_id']??$_SESSION['role_id']??0));
    if(pc_role_key((string)($role['role_key']??''))!=='parent'){
        throw new RuntimeException('Parent Dashboard is available only to Parent accounts.',403);
    }
    $allowedMenus=pdParentMenuKeys($pdo,$scope);
    if($allowedMenus===[]){
        throw new RuntimeException('Parent Portal is disabled for this school.',403);
    }

    $guardian = pdGuardian($pdo, $scope);
    $children = pdChildren(
        $pdo,(int)$scope['tenant_id'],(int)$guardian['id'],(int)$scope['user_id']
    );
    $child = pdSelectedChild($children,(int)($_GET['student_id'] ?? 0));
    $can=static fn(string $key):bool=>in_array($key,$allowedMenus,true);

    $attendance=$can('parent_attendance')
        ?pdAttendance($pdo,(int)$scope['tenant_id'],$child)
        :['percentage'=>0,'working_days'=>0,'present_days'=>0,'absent_days'=>0,'rows'=>[]];
    $fees=($can('parent_fees')||$can('parent_fee_payment'))
        ?pdFees($pdo,(int)$scope['tenant_id'],$child)
        :['assignment'=>null,'items'=>[],'receipts'=>[]];
    $exams=$can('parent_results')
        ?pdExams($pdo,(int)$scope['tenant_id'],$child)
        :['upcoming'=>[],'results'=>[]];
    $timetable=[];
    $transport=$can('parent_transport')?pdTransport($pdo,(int)$scope['tenant_id'],$child):[];
    $leaveRequests=['rows'=>[]];
    $homework=$can('parent_homework')?pdHomework($pdo,(int)$scope['tenant_id'],$child):['rows'=>[]];
    $calendarUpdates=$can('parent_notices')?pdCalendarUpdates($pdo,(int)$scope['tenant_id'],$child):[];
    $notifications=$can('parent_notices')?pdNotifications($pdo,(int)$scope['tenant_id'],(int)$scope['user_id']):[];
    $messages=$can('parent_notices')?pdMessages($pdo,(int)$scope['tenant_id'],(int)$scope['user_id']):[];

    $feeAssignment = $fees['assignment'] ?? null;

    pdJson(true, 'Parent dashboard loaded.', [
        'parent' => [
            'guardian_id' => (int)$guardian['id'],
            'guardian_name' => (string)$guardian['guardian_name'],
            'relationship' => (string)($guardian['relationship'] ?? ''),
            'mobile' => (string)($guardian['mobile'] ?? ''),
            'email' => (string)($guardian['email'] ?? ''),
            'occupation' => (string)($guardian['occupation'] ?? ''),
            'address' => (string)($guardian['address'] ?? ''),
        ],
        'children' => $children,
        'allowed_menu_keys' => $allowedMenus,
        'selected_child' => $child,
        'summary' => [
            'attendance_percentage' => (float)$attendance['percentage'],
            'working_days' => (int)$attendance['working_days'],
            'present_days' => (float)$attendance['present_days'],
            'absent_days' => (int)$attendance['absent_days'],
            'fee_due' => (float)($feeAssignment['balance_amount'] ?? 0),
            'fee_paid' => (float)($feeAssignment['paid_amount'] ?? 0),
            'fee_total' => (float)($feeAssignment['net_amount'] ?? 0),
            'fee_status' => (string)($feeAssignment['payment_status'] ?? 'not_assigned'),
            'upcoming_exams' => count($exams['upcoming']),
            'homework_count' => count($homework['rows']),
            'pending_leave_requests' => count(array_filter(
                $leaveRequests['rows'],
                static fn(array $row): bool => ($row['status'] ?? '') === 'pending'
            )),
        ],
        'attendance' => $attendance,
        'fees' => $fees,
        'exams' => $exams,
        'timetable' => $timetable,
        'transport' => $transport,
        'homework' => $homework,
        'leave_requests' => $leaveRequests,
        'calendar_updates' => $calendarUpdates,
        'notifications' => $notifications,
        'messages' => $messages,
        'generated_at' => date('c'),
    ]);
} catch (Throwable $exception) {
    $status = (int)$exception->getCode();
    if ($status < 400 || $status > 599) {
        $status = 500;
    }

    error_log('Parent Dashboard API: ' . $exception->getMessage());

    pdJson(
        false,
        $status >= 500
            ? 'Unable to load the Parent Dashboard.'
            : $exception->getMessage(),
        [],
        $status
    );
}

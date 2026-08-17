<?php

declare(strict_types=1);

/* Build: 2026-08-13-academic-calendar-modal-style-v42 */

$pageTitle = 'Academic Calendar';
$pageKey = 'academic_calendar';
$sidebarFile = __DIR__ . '/sidebar.php';

$isAjax = isset($_GET['ajax']) && (string)$_GET['ajax'] === '1';

if ($isAjax) {
    ob_start();
    require_once dirname(__DIR__) . '/includes/bootstrap.php';

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    function acJson(bool $success, string $message = '', array $data = [], int $status = 200): never
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code($status);
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    function acInput(): array
    {
        $type = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
        if (str_contains($type, 'application/json')) {
            $decoded = json_decode((string)file_get_contents('php://input'), true);
            return is_array($decoded) ? $decoded : [];
        }
        return $_POST;
    }

    function acTable(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?'
        );
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    }

    function acEnsure(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS academic_year_module_records (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id BIGINT UNSIGNED NOT NULL,
            module_key VARCHAR(80) NOT NULL,
            record_data JSON NOT NULL,
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_by BIGINT UNSIGNED NULL,
            updated_by BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_academic_module (tenant_id,module_key,status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    function acScope(): array
    {
        $current = function_exists('current_user') ? current_user() : [];
        return [
            'tenant_id' => (int)($current['tenant_id'] ?? $_SESSION['tenant_id'] ?? $_SESSION['school_id'] ?? 0),
            'user_id' => (int)($current['id'] ?? $current['user_id'] ?? $_SESSION['user_id'] ?? 0),
        ];
    }

    function acCan(string $action): bool
    {
        if (function_exists('school_effective_permission')) {
            return school_effective_permission('academic_calendar', $action);
        }
        return true;
    }

    function acCsrf(array $input): void
    {
        if (function_exists('csrf_is_valid')) {
            $token = (string)($input['csrf_token'] ?? '');
            if (!csrf_is_valid($token)) {
                acJson(false, 'Session expired. Refresh the page and try again.', [], 419);
            }
        }
    }

    function acClean(string $value, int $max): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        return mb_substr($value, 0, $max);
    }

    function acDate(string $value): bool
    {
        $date = DateTime::createFromFormat('Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    function acAcademicYears(PDO $pdo, int $tenantId): array
    {
        if (!acTable($pdo, 'academic_years')) {
            return [];
        }
        $stmt = $pdo->prepare(
            "SELECT id,year_name,start_date,end_date,is_current,status
             FROM academic_years
             WHERE tenant_id=? AND status IN ('active','closed')
             ORDER BY is_current DESC,start_date DESC,id DESC"
        );
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }


    function acAudienceData(PDO $pdo, int $tenantId, int $academicYearId): array
    {
        $classes = [];
        $students = [];

        if (
            $academicYearId <= 0
            || !acTable($pdo, 'classes')
        ) {
            return [
                'classes' => [],
                'students' => [],
            ];
        }

        $classStmt = $pdo->prepare(
            "SELECT id,class_name
             FROM classes
             WHERE tenant_id=:tenant_id
               AND academic_year_id=:academic_year_id
               AND status='active'
             ORDER BY class_name,id"
        );
        $classStmt->execute([
            'tenant_id' => $tenantId,
            'academic_year_id' => $academicYearId,
        ]);
        $classes = $classStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (
            !acTable($pdo, 'students')
            || !acTable($pdo, 'student_enrollments')
        ) {
            return [
                'classes' => $classes,
                'students' => [],
            ];
        }

        $studentStmt = $pdo->prepare(
            "SELECT
                s.id,
                s.admission_no,
                CONCAT(
                    TRIM(COALESCE(s.first_name,'')),
                    CASE
                        WHEN TRIM(COALESCE(s.last_name,'')) <> ''
                            THEN CONCAT(' ',TRIM(s.last_name))
                        ELSE ''
                    END
                ) AS student_name,
                se.class_id,
                se.section_id,
                se.roll_no,
                c.class_name,
                sec.section_name
             FROM student_enrollments se
             INNER JOIN students s
                ON s.id=se.student_id
               AND s.tenant_id=se.tenant_id
             LEFT JOIN classes c
                ON c.id=se.class_id
               AND c.tenant_id=se.tenant_id
             LEFT JOIN sections sec
                ON sec.id=se.section_id
               AND sec.tenant_id=se.tenant_id
             WHERE se.tenant_id=:tenant_id
               AND se.academic_year_id=:academic_year_id
               AND se.enrollment_status='active'
               AND s.status='active'
               AND s.deleted_at IS NULL
             ORDER BY
                c.class_name,
                sec.section_name,
                s.first_name,
                s.last_name,
                s.id"
        );
        $studentStmt->execute([
            'tenant_id' => $tenantId,
            'academic_year_id' => $academicYearId,
        ]);
        $students = $studentStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'classes' => $classes,
            'students' => $students,
        ];
    }

    function acAudienceValidate(
        PDO $pdo,
        int $tenantId,
        int $academicYearId,
        int $classId,
        array $studentIds
    ): array {
        $className = '';
        $studentNames = [];

        if ($classId > 0) {
            $classStmt = $pdo->prepare(
                "SELECT class_name
                 FROM classes
                 WHERE id=:class_id
                   AND tenant_id=:tenant_id
                   AND academic_year_id=:academic_year_id
                   AND status='active'
                 LIMIT 1"
            );
            $classStmt->execute([
                'class_id' => $classId,
                'tenant_id' => $tenantId,
                'academic_year_id' => $academicYearId,
            ]);

            $className = (string)($classStmt->fetchColumn() ?: '');

            if ($className === '') {
                throw new InvalidArgumentException(
                    'Selected Class was not found in this Academic Year.'
                );
            }
        }

        $studentIds = array_values(array_unique(array_filter(
            array_map(
                static fn($value): int => (int)$value,
                $studentIds
            ),
            static fn(int $value): bool => $value > 0
        )));

        if ($studentIds !== []) {
            if ($classId <= 0) {
                throw new InvalidArgumentException(
                    'Select a Class before choosing individual Students.'
                );
            }

            $marks = implode(
                ',',
                array_fill(0, count($studentIds), '?')
            );

            $params = array_merge(
                [
                    $tenantId,
                    $academicYearId,
                    $classId,
                ],
                $studentIds
            );

            $studentStmt = $pdo->prepare(
                "SELECT
                    s.id,
                    CONCAT(
                        TRIM(COALESCE(s.first_name,'')),
                        CASE
                            WHEN TRIM(COALESCE(s.last_name,'')) <> ''
                                THEN CONCAT(' ',TRIM(s.last_name))
                            ELSE ''
                        END
                    ) AS student_name
                 FROM student_enrollments se
                 INNER JOIN students s
                    ON s.id=se.student_id
                   AND s.tenant_id=se.tenant_id
                 WHERE se.tenant_id=?
                   AND se.academic_year_id=?
                   AND se.class_id=?
                   AND se.enrollment_status='active'
                   AND s.status='active'
                   AND s.deleted_at IS NULL
                   AND s.id IN ({$marks})
                 ORDER BY s.first_name,s.last_name,s.id"
            );
            $studentStmt->execute($params);
            $rows = $studentStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            if (count($rows) !== count($studentIds)) {
                throw new InvalidArgumentException(
                    'One or more selected Students are not active in the selected Class / Academic Year.'
                );
            }

            $studentIds = [];
            foreach ($rows as $row) {
                $studentIds[] = (int)$row['id'];
                $studentNames[] = (string)$row['student_name'];
            }
        }

        return [
            'class_id' => $classId,
            'class_name' => $className,
            'student_ids' => $studentIds,
            'student_names' => $studentNames,
            'student_scope' => $studentIds !== []
                ? 'selected'
                : ($classId > 0 ? 'all_class' : 'all_year'),
        ];
    }

    function acSelectedYear(PDO $pdo, int $tenantId, array $input): int
    {
        $id = (int)($input['academic_year_id'] ?? $_GET['academic_year_id'] ?? $_SESSION['academic_year_id'] ?? 0);
        if ($id <= 0 && acTable($pdo, 'academic_years')) {
            $stmt = $pdo->prepare(
                "SELECT id FROM academic_years
                 WHERE tenant_id=? AND status='active'
                 ORDER BY is_current DESC,start_date DESC,id DESC LIMIT 1"
            );
            $stmt->execute([$tenantId]);
            $id = (int)$stmt->fetchColumn();
        }
        if ($id > 0 && acTable($pdo, 'academic_years')) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM academic_years WHERE id=? AND tenant_id=?');
            $stmt->execute([$id, $tenantId]);
            if ((int)$stmt->fetchColumn() <= 0) {
                throw new InvalidArgumentException('Selected Academic Year was not found.');
            }
        }
        return $id;
    }

    if (function_exists('require_login')) {
        require_login();
    } elseif (empty($_SESSION['user_id'])) {
        acJson(false, 'Your login session has expired.', [], 401);
    }

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        acJson(false, 'Database connection unavailable.', [], 500);
    }

    try {
        acEnsure($pdo);
        $scope = acScope();
        if ($scope['tenant_id'] <= 0) {
            acJson(false, 'School context is unavailable.', [], 422);
        }

        $input = acInput();
        $action = strtolower(trim((string)($_GET['action'] ?? $input['action'] ?? 'meta')));
        $yearId = acSelectedYear($pdo, $scope['tenant_id'], $input);

        if ($action === 'meta') {
            if (!acCan('view')) {
                acJson(false, 'You do not have permission to view Academic Calendar.', [], 403);
            }
            acJson(true, 'Academic Calendar loaded.', [
                'academic_years' => acAcademicYears($pdo, $scope['tenant_id']),
                'selected_academic_year_id' => $yearId,
                'audience' => acAudienceData($pdo, $scope['tenant_id'], $yearId),
                'csrf_token' => function_exists('csrfToken') ? csrfToken() : '',
                'permissions' => [
                    'view' => acCan('view'),
                    'create' => acCan('create') || acCan('add'),
                    'edit' => acCan('edit'),
                    'delete' => acCan('delete'),
                    'print' => acCan('print'),
                ],
            ]);
        }


        if ($action === 'audience') {
            if (!acCan('view')) {
                acJson(
                    false,
                    'You do not have permission to view Academic Calendar.',
                    [],
                    403
                );
            }

            acJson(
                true,
                'Calendar audience loaded.',
                [
                    'academic_year_id' => $yearId,
                    'audience' => acAudienceData(
                        $pdo,
                        $scope['tenant_id'],
                        $yearId
                    ),
                ]
            );
        }

        if ($action === 'list') {
            if (!acCan('view')) {
                acJson(false, 'You do not have permission to view Academic Calendar.', [], 403);
            }

            $month = trim((string)($_GET['month'] ?? $input['month'] ?? ''));
            if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
                $month = date('Y-m');
            }
            $monthStart = $month . '-01';
            $monthEnd = date('Y-m-t', strtotime($monthStart));

            $stmt = $pdo->prepare(
                "SELECT id,record_data,status,created_at,updated_at
                 FROM academic_year_module_records
                 WHERE tenant_id=:tenant_id AND module_key='academic_calendar'
                 ORDER BY id DESC"
            );
            $stmt->execute(['tenant_id' => $scope['tenant_id']]);

            $events = [];
            $currentYearId = $yearId;
            $yearNameMap = [];
            foreach (acAcademicYears($pdo, $scope['tenant_id']) as $academicYear) {
                $yearNameMap[(int)$academicYear['id']] = (string)$academicYear['year_name'];
            }

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $data = json_decode((string)$row['record_data'], true);
                if (!is_array($data)) continue;
                $recordYear = (int)($data['academic_year_id'] ?? $currentYearId);
                if ($yearId > 0 && $recordYear !== $yearId) continue;
                $eventDate = (string)($data['event_date'] ?? '');
                if (!acDate($eventDate)) continue;
                if ($eventDate < $monthStart || $eventDate > $monthEnd) continue;
                $studentIds = is_array($data['student_ids'] ?? null)
                    ? array_values(array_map('intval', $data['student_ids']))
                    : [];
                $studentNames = is_array($data['student_names'] ?? null)
                    ? array_values(array_map('strval', $data['student_names']))
                    : [];
                $classId = (int)($data['class_id'] ?? 0);
                $className = trim((string)($data['class_name'] ?? ''));
                $studentScope = (string)($data['student_scope'] ?? '');

                if ($studentScope === '') {
                    $studentScope = $studentIds !== []
                        ? 'selected'
                        : ($classId > 0 ? 'all_class' : 'all_year');
                }

                $events[] = [
                    'id' => (int)$row['id'],
                    'academic_year_id' => $recordYear,
                    'academic_year_name' => (string)($yearNameMap[$recordYear] ?? ''),
                    'class_id' => $classId,
                    'class_name' => $className,
                    'student_ids' => $studentIds,
                    'student_names' => $studentNames,
                    'student_scope' => $studentScope,
                    'event_name' => (string)($data['event_name'] ?? ''),
                    'event_date' => $eventDate,
                    'event_type' => (string)($data['event_type'] ?? 'academic'),
                    'description' => (string)($data['description'] ?? ''),
                    'status' => (string)$row['status'],
                    'created_at' => (string)$row['created_at'],
                    'updated_at' => (string)$row['updated_at'],
                ];
            }

            usort($events, static fn(array $a, array $b): int => [$a['event_date'], $a['event_name']] <=> [$b['event_date'], $b['event_name']]);

            acJson(true, 'Events loaded.', [
                'events' => $events,
                'month' => $month,
                'academic_year_id' => $yearId,
            ]);
        }

        if ($action === 'save') {
            acCsrf($input);
            $id = (int)($input['id'] ?? 0);
            $requiredAction = $id > 0 ? 'edit' : 'create';
            if (!acCan($requiredAction) && !($requiredAction === 'create' && acCan('add'))) {
                acJson(false, 'You do not have permission to ' . ($id > 0 ? 'edit' : 'add') . ' calendar events.', [], 403);
            }

            $name = acClean((string)($input['event_name'] ?? ''), 150);
            $date = trim((string)($input['event_date'] ?? ''));
            $type = strtolower(acClean((string)($input['event_type'] ?? 'academic'), 30));
            $description = acClean((string)($input['description'] ?? ''), 1000);
            $status = strtolower(acClean((string)($input['status'] ?? 'active'), 20));
            $classId = (int)($input['class_id'] ?? 0);
            $studentIds = is_array($input['student_ids'] ?? null)
                ? $input['student_ids']
                : [];

            if ($name === '') throw new InvalidArgumentException('Event Name is required.');
            if (!acDate($date)) throw new InvalidArgumentException('Select a valid Event Date.');
            if (!in_array($type, ['academic','exam','holiday','meeting','activity','event','other'], true)) {
                throw new InvalidArgumentException('Invalid Event Type.');
            }
            if (!in_array($status, ['active','inactive'], true)) {
                throw new InvalidArgumentException('Invalid Status.');
            }

            $audience = acAudienceValidate(
                $pdo,
                $scope['tenant_id'],
                $yearId,
                $classId,
                $studentIds
            );

            $recordData = [
                'academic_year_id' => $yearId,
                'class_id' => (int)$audience['class_id'],
                'class_name' => (string)$audience['class_name'],
                'student_ids' => $audience['student_ids'],
                'student_names' => $audience['student_names'],
                'student_scope' => (string)$audience['student_scope'],
                'event_name' => $name,
                'event_date' => $date,
                'event_type' => $type,
                'description' => $description,
            ];
            $json = json_encode($recordData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false) throw new RuntimeException('Unable to prepare calendar event.');

            $pdo->beginTransaction();
            if ($id > 0) {
                $check = $pdo->prepare(
                    "SELECT id,record_data FROM academic_year_module_records
                     WHERE id=? AND tenant_id=? AND module_key='academic_calendar' FOR UPDATE"
                );
                $check->execute([$id, $scope['tenant_id']]);
                if (!$check->fetch(PDO::FETCH_ASSOC)) {
                    throw new InvalidArgumentException('Calendar event was not found.');
                }
                $stmt = $pdo->prepare(
                    "UPDATE academic_year_module_records
                     SET record_data=:record_data,status=:status,updated_by=:updated_by
                     WHERE id=:id AND tenant_id=:tenant_id AND module_key='academic_calendar'"
                );
                $stmt->execute([
                    'record_data' => $json,
                    'status' => $status,
                    'updated_by' => $scope['user_id'],
                    'id' => $id,
                    'tenant_id' => $scope['tenant_id'],
                ]);
                $message = 'Calendar event updated successfully.';
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO academic_year_module_records
                     (tenant_id,module_key,record_data,status,created_by,updated_by)
                     VALUES (:tenant_id,'academic_calendar',:record_data,:status,:created_by,:updated_by)"
                );
                $stmt->execute([
                    'tenant_id' => $scope['tenant_id'],
                    'record_data' => $json,
                    'status' => $status,
                    'created_by' => $scope['user_id'] ?: null,
                    'updated_by' => $scope['user_id'] ?: null,
                ]);
                $id = (int)$pdo->lastInsertId();
                $message = 'Calendar event added successfully.';
            }
            $pdo->commit();
            acJson(true, $message, ['id' => $id]);
        }

        if ($action === 'delete') {
            acCsrf($input);
            if (!acCan('delete')) {
                acJson(false, 'You do not have permission to delete calendar events.', [], 403);
            }
            $id = (int)($input['id'] ?? 0);
            if ($id <= 0) throw new InvalidArgumentException('Invalid calendar event.');
            $stmt = $pdo->prepare(
                "DELETE FROM academic_year_module_records
                 WHERE id=? AND tenant_id=? AND module_key='academic_calendar'"
            );
            $stmt->execute([$id, $scope['tenant_id']]);
            if ($stmt->rowCount() <= 0) throw new InvalidArgumentException('Calendar event was not found.');
            acJson(true, 'Calendar event deleted successfully.');
        }

        acJson(false, 'Invalid Academic Calendar request.', [], 400);
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('school/academic-calendar.php: ' . $e->getMessage());
        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        $message = ($e instanceof InvalidArgumentException || str_contains($host, 'localhost') || str_contains($host, '127.0.0.1'))
            ? $e->getMessage()
            : 'Unable to complete Academic Calendar request.';
        acJson(false, $message, [], $e instanceof InvalidArgumentException ? 422 : 500);
    }
}

require dirname(__DIR__) . '/includes/layout-start.php';

$currentUser = function_exists('current_user') ? current_user() : [];
$tenantId = (int)($currentUser['tenant_id'] ?? $_SESSION['tenant_id'] ?? $_SESSION['school_id'] ?? 0);
$currentAcademicYear = function_exists('current_academic_year') ? current_academic_year() : [];
$currentAcademicYearId = (int)($currentAcademicYear['id'] ?? $_SESSION['academic_year_id'] ?? 0);
$currentAcademicYearName = trim((string)($currentAcademicYear['year_name'] ?? $_SESSION['academic_year_name'] ?? ''));
$csrfToken = function_exists('csrfToken') ? csrfToken() : '';
?>

<style>
.ac-page{display:grid;gap:16px}.ac-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px}.ac-head h1{margin:0;color:#111a3b;font-size:30px;font-weight:800}.ac-head p{margin:5px 0 0;color:#64748b;font-size:13px}.ac-head-actions{display:flex;gap:9px;flex-wrap:wrap}.ac-btn{height:42px;border:1px solid #dfe5ee;border-radius:10px;padding:0 15px;background:#fff;color:#172554;font-size:12px;font-weight:750;display:inline-flex;align-items:center;justify-content:center;gap:8px;cursor:pointer}.ac-btn:hover{background:#f8fafc}.ac-btn.primary{background:#4f46e5;border-color:#4f46e5;color:#fff}.ac-btn.danger{color:#be123c;border-color:#fecdd3;background:#fff1f2}.ac-btn:disabled{opacity:.5;cursor:not-allowed}.ac-card{background:#fff;border:1px solid #e5eaf2;border-radius:16px;box-shadow:0 5px 20px rgba(15,23,42,.045)}.ac-toolbar{padding:14px 16px;display:flex;align-items:center;justify-content:space-between;gap:12px}.ac-toolbar-left,.ac-toolbar-right{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.ac-month-title{min-width:180px;text-align:center;color:#101a3b;font-size:17px;font-weight:800}.ac-year-chip{display:flex;flex-direction:column;padding:7px 12px;border-radius:10px;background:#f5f3ff;color:#4338ca;min-width:145px}.ac-year-chip small{font-size:9px;font-weight:700;opacity:.75}.ac-year-chip strong{font-size:12px}.ac-grid-wrap{overflow:auto}.ac-weekdays,.ac-grid{min-width:850px;display:grid;grid-template-columns:repeat(7,minmax(120px,1fr))}.ac-weekdays{border-top:1px solid #edf1f6;border-bottom:1px solid #edf1f6;background:#f8fafc}.ac-weekdays div{padding:10px 12px;text-align:center;color:#64748b;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.04em}.ac-day{min-height:128px;border-right:1px solid #edf1f6;border-bottom:1px solid #edf1f6;padding:8px;background:#fff;position:relative}.ac-day:nth-child(7n){border-right:0}.ac-day.outside{background:#fafbfc}.ac-day.outside .ac-date{opacity:.38}.ac-day.today{background:#f8f7ff}.ac-date-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:6px}.ac-date{width:27px;height:27px;border-radius:8px;display:grid;place-items:center;color:#334155;font-size:11px;font-weight:800}.ac-day.today .ac-date{background:#4f46e5;color:#fff}.ac-add-day{width:25px;height:25px;border:0;border-radius:7px;background:transparent;color:#94a3b8;display:grid;place-items:center;cursor:pointer}.ac-add-day:hover{background:#eef2ff;color:#4f46e5}.ac-events{display:grid;gap:5px}.ac-event{width:100%;border:0;border-radius:7px;padding:6px 7px;text-align:left;cursor:pointer;display:block;overflow:hidden}.ac-event strong{display:block;font-size:10px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.ac-event small{display:block;margin-top:2px;font-size:8px;opacity:.76;text-transform:capitalize}.ac-event.academic{background:#eef2ff;color:#4338ca}.ac-event.exam{background:#fff1f2;color:#be123c}.ac-event.holiday{background:#ecfdf5;color:#047857}.ac-event.meeting{background:#fff7ed;color:#c2410c}.ac-event.activity{background:#f0fdfa;color:#0f766e}.ac-event.event{background:#fdf4ff;color:#a21caf}.ac-event.other{background:#f1f5f9;color:#475569}.ac-summary{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:14px
}
.ac-stat{
    min-height:124px;
    padding:20px 22px;
    display:flex;
    align-items:center;
    gap:16px;
    position:relative;
    overflow:hidden;
    border:0;
    border-radius:15px;
    color:#fff;
    box-shadow:0 10px 24px rgba(15,23,42,.08);
    isolation:isolate
}
.ac-stat::after{
    content:"";
    position:absolute;
    z-index:-1;
    width:132px;
    height:132px;
    border-radius:50%;
    right:-42px;
    top:-55px;
    background:rgba(255,255,255,.085)
}
.ac-stat::before{
    content:"";
    position:absolute;
    z-index:-1;
    width:52px;
    height:52px;
    border-radius:50%;
    right:14px;
    bottom:-31px;
    background:rgba(255,255,255,.045)
}
.ac-stat-purple{
    background:linear-gradient(135deg,#7548ee 0%,#5033d5 100%)
}
.ac-stat-green{
    background:linear-gradient(135deg,#43c987 0%,#20aa6f 100%)
}
.ac-stat-pink{
    background:linear-gradient(135deg,#ff557f 0%,#ef3267 100%)
}
.ac-stat-orange{
    background:linear-gradient(135deg,#ffb22a 0%,#ff8b19 100%)
}
.ac-stat-icon{
    width:54px;
    height:54px;
    min-width:54px;
    border-radius:50%;
    display:grid;
    place-items:center;
    background:rgba(255,255,255,.17);
    border:1px solid rgba(255,255,255,.09);
    color:#fff;
    position:relative;
    z-index:1
}
.ac-stat-icon svg{
    width:27px;
    height:27px;
    stroke-width:1.9
}
.ac-stat-copy{
    position:relative;
    z-index:1;
    min-width:0
}
.ac-stat-label{
    display:block;
    margin:0 0 5px;
    color:rgba(255,255,255,.94);
    font-size:11px;
    font-weight:700;
    line-height:1.2
}
.ac-stat strong{
    display:block;
    overflow:hidden;
    color:#fff;
    font-size:clamp(27px,2.05vw,34px);
    line-height:1;
    font-weight:800;
    letter-spacing:-.035em;
    text-overflow:ellipsis;
    white-space:nowrap
}
.ac-stat small{
    display:block;
    margin-top:8px;
    overflow:hidden;
    color:rgba(255,255,255,.92);
    font-size:9.5px;
    font-weight:650;
    line-height:1.3;
    text-overflow:ellipsis;
    white-space:nowrap
}.ac-upcoming{padding:16px}.ac-upcoming-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}.ac-upcoming h2{margin:0;font-size:15px;font-weight:800;color:#101a3b}.ac-upcoming-list{display:grid;gap:8px}.ac-upcoming-item{display:grid;grid-template-columns:52px minmax(0,1fr) auto;gap:10px;align-items:center;padding:10px;border:1px solid #edf1f6;border-radius:10px}.ac-upcoming-date{height:48px;border-radius:10px;background:#f8fafc;display:grid;place-items:center;text-align:center}.ac-upcoming-date strong{font-size:15px;line-height:1;color:#172554}.ac-upcoming-date small{font-size:8px;color:#64748b;text-transform:uppercase}.ac-upcoming-copy strong{display:block;font-size:11px;color:#101a3b}.ac-upcoming-copy small{display:block;color:#64748b;font-size:9px;margin-top:3px}.ac-type{padding:4px 8px;border-radius:999px;font-size:8px;font-weight:800;text-transform:capitalize;background:#f1f5f9;color:#475569}.ac-empty{padding:24px;text-align:center;color:#64748b;font-size:12px}.ac-modal .modal-dialog{max-width:860px}
.ac-modal .modal-content{
    border:1px solid #e7ebf3;
    border-radius:18px;
    overflow:hidden;
    background:#f8fafc;
    box-shadow:0 24px 70px rgba(15,23,42,.20)
}
.ac-modal .modal-header{
    padding:17px 20px;
    border-bottom:1px solid #e8edf4;
    background:#fff
}
.ac-modal-head{
    display:flex;
    align-items:center;
    gap:12px;
    min-width:0
}
.ac-modal-head-icon{
    width:42px;
    height:42px;
    flex:0 0 42px;
    display:grid;
    place-items:center;
    border-radius:12px;
    color:#4f46e5;
    background:#eef2ff
}
.ac-modal-head-icon svg{width:20px;height:20px}
.ac-modal-head-copy{min-width:0}
.ac-modal .modal-title{
    margin:0;
    color:#101a3b;
    font-size:17px;
    font-weight:800;
    line-height:1.2
}
.ac-modal-subtitle{
    display:block;
    margin-top:4px;
    color:#64748b;
    font-size:9.5px;
    font-weight:600
}
.ac-modal .btn-close{
    width:30px;
    height:30px;
    padding:0;
    border-radius:8px
}
.ac-modal .modal-body{
    padding:16px 18px 18px;
    background:#f8fafc
}
.ac-form{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.ac-field.full{grid-column:1/-1}
.ac-field label{
    display:block;
    margin-bottom:6px;
    color:#334155;
    font-size:10px;
    font-weight:800
}
.ac-field input,
.ac-field select,
.ac-field textarea{
    width:100%;
    border:1px solid #dbe3ee;
    border-radius:10px;
    background:#fff;
    color:#172554;
    font-size:12px;
    padding:10px 11px;
    outline:none;
    transition:border-color .16s ease,box-shadow .16s ease
}
.ac-field input:focus,
.ac-field select:focus,
.ac-field textarea:focus{
    border-color:#7c6df2;
    box-shadow:0 0 0 3px rgba(79,70,229,.08)
}
.ac-field input[readonly]{
    background:#f8fafc;
    color:#475569
}
.ac-field input,.ac-field select{height:42px}
.ac-field textarea{min-height:88px;resize:vertical}
.ac-modal-section{
    padding:14px;
    border:1px solid #e6ebf2;
    border-radius:13px;
    background:#fff
}
.ac-modal-section + .ac-modal-section{margin-top:12px}
.ac-modal-section-head{
    display:flex;
    align-items:center;
    gap:8px;
    margin-bottom:12px
}
.ac-modal-section-icon{
    width:28px;
    height:28px;
    flex:0 0 28px;
    display:grid;
    place-items:center;
    border-radius:8px;
    color:#4f46e5;
    background:#eef2ff
}
.ac-modal-section-icon svg{width:14px;height:14px}
.ac-modal-section-title{
    margin:0;
    color:#172554;
    font-size:11px;
    font-weight:800
}
.ac-modal-section-note{
    margin:2px 0 0;
    color:#94a3b8;
    font-size:8.5px;
    line-height:1.35
}
.ac-modal .modal-footer{
    padding:12px 18px;
    border-top:1px solid #e8edf4;
    background:#fff
}
.ac-modal .modal-footer .ac-btn{height:38px}
.ac-print-only{display:none}@media(max-width:900px){.ac-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.ac-head{display:block}.ac-head-actions{margin-top:12px}}@media(max-width:600px){.ac-summary{grid-template-columns:1fr;gap:12px}.ac-stat{min-height:108px;padding:17px 18px}.ac-stat-icon{width:48px;height:48px;min-width:48px}.ac-stat-icon svg{width:24px;height:24px}.ac-form{grid-template-columns:1fr}.ac-field.full{grid-column:auto}.ac-toolbar{align-items:flex-start;flex-direction:column}.ac-toolbar-right{width:100%}.ac-year-chip{flex:1}}
.ac-field select[multiple]{height:108px;padding:7px 9px}
.ac-help{
    display:block;
    margin-top:5px;
    color:#64748b;
    font-size:8.5px;
    line-height:1.4
}
.ac-event .ac-event-audience{
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
    text-transform:none
}
.ac-upcoming-meta{
    display:block!important;
    color:#475569!important;
    font-size:8.5px!important;
    line-height:1.35!important;
    white-space:normal!important
}
.ac-modal-audience{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:8px;
    margin:0 0 12px;
    padding:10px 12px;
    border:1px solid #ddd9ff;
    border-radius:11px;
    background:#f7f5ff
}
.ac-modal-audience div{min-width:0}
.ac-modal-audience span,.ac-modal-audience strong{display:block}
.ac-modal-audience span{
    color:#7768ca;
    font-size:7.5px;
    font-weight:800;
    text-transform:uppercase
}
.ac-modal-audience strong{
    margin-top:3px;
    overflow:hidden;
    color:#352a73;
    font-size:9px;
    font-weight:800;
    text-overflow:ellipsis;
    white-space:nowrap
}
@media(max-width:767px){
    .ac-modal .modal-dialog{max-width:calc(100% - 20px);margin:10px auto}
    .ac-modal .modal-header{padding:14px 15px}
    .ac-modal .modal-body{padding:12px}
    .ac-modal .modal-footer{padding:10px 12px}
    .ac-modal-section{padding:12px}
    .ac-modal-audience{grid-template-columns:1fr}
}
@media(max-width:600px){
    .ac-modal .modal-dialog{margin:0;max-width:100%;height:100%}
    .ac-modal .modal-content{min-height:100%;border-radius:0}
    .ac-form{grid-template-columns:1fr}
    .ac-field.full{grid-column:auto}
}
@media print{#sidebar,#topbar,.ac-head-actions,.ac-toolbar-left .ac-btn,.ac-add-day,.ac-upcoming,.modal{display:none!important}.app-content{margin:0!important;padding:0!important}.ac-card{box-shadow:none}.ac-grid-wrap{overflow:visible}.ac-weekdays,.ac-grid{min-width:0}.ac-day{min-height:90px}.ac-print-only{display:block}.ac-summary{display:none}}
</style>

<div class="ac-page">
    <div class="ac-head">
        <div>
            <h1>Academic Calendar</h1>
            <p>Manage academic events, exams, holidays, meetings and school activities for the selected Academic Year.</p>
        </div>
        <div class="ac-head-actions">
            <button class="ac-btn" id="printCalendarBtn" type="button"><i data-lucide="printer"></i> Print</button>
            <button class="ac-btn primary" id="addEventBtn" type="button"><i data-lucide="plus"></i> Add Event</button>
        </div>
    </div>

    <div class="ac-summary">
        <div class="ac-stat ac-stat-purple">
            <span class="ac-stat-icon"><i data-lucide="calendar-days"></i></span>
            <div class="ac-stat-copy"><span class="ac-stat-label">Total Events</span><strong id="totalEvents">0</strong><small>Events in selected month</small></div>
        </div>
        <div class="ac-stat ac-stat-green">
            <span class="ac-stat-icon"><i data-lucide="graduation-cap"></i></span>
            <div class="ac-stat-copy"><span class="ac-stat-label">Academic / Exams</span><strong id="academicEvents">0</strong><small>Academic activities & examinations</small></div>
        </div>
        <div class="ac-stat ac-stat-pink">
            <span class="ac-stat-icon"><i data-lucide="calendar-off"></i></span>
            <div class="ac-stat-copy"><span class="ac-stat-label">Holidays</span><strong id="holidayEvents">0</strong><small>School holidays in this month</small></div>
        </div>
        <div class="ac-stat ac-stat-orange">
            <span class="ac-stat-icon"><i data-lucide="clock-3"></i></span>
            <div class="ac-stat-copy"><span class="ac-stat-label">Upcoming</span><strong id="upcomingEvents">0</strong><small>Upcoming events from today</small></div>
        </div>
    </div>

    <section class="ac-card">
        <div class="ac-toolbar">
            <div class="ac-toolbar-left">
                <button class="ac-btn" id="prevMonthBtn" type="button" title="Previous month"><i data-lucide="chevron-left"></i></button>
                <button class="ac-btn" id="todayBtn" type="button">Today</button>
                <button class="ac-btn" id="nextMonthBtn" type="button" title="Next month"><i data-lucide="chevron-right"></i></button>
                <div class="ac-month-title" id="monthTitle"><?= e(date('F Y')) ?></div>
            </div>
            <div class="ac-toolbar-right">
                <label class="ac-year-chip" for="academicYearSelect"><small>Academic Year</small><strong id="yearChipLabel"><?= e($currentAcademicYearName !== '' ? $currentAcademicYearName : 'Select Year') ?></strong></label>
                <select id="academicYearSelect" class="form-select" style="width:auto;min-width:160px;height:42px"></select>
            </div>
        </div>
        <div class="ac-print-only"><h2 id="printTitle"></h2></div>
        <div class="ac-grid-wrap">
            <div class="ac-weekdays"><div>Sun</div><div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div></div>
            <div class="ac-grid" id="calendarGrid"></div>
        </div>
    </section>

    <section class="ac-card ac-upcoming">
        <div class="ac-upcoming-head"><h2>Upcoming Events</h2><small class="text-muted" id="upcomingInfo">Current month</small></div>
        <div class="ac-upcoming-list" id="upcomingList"><div class="ac-empty">Loading events...</div></div>
    </section>
</div>

<div class="modal fade ac-modal" id="eventModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div class="ac-modal-head">
                    <span class="ac-modal-head-icon">
                        <i data-lucide="calendar-plus-2"></i>
                    </span>
                    <div class="ac-modal-head-copy">
                        <h5 class="modal-title" id="eventModalTitle">Add Calendar Event</h5>
                        <small class="ac-modal-subtitle">
                            <span id="modalYearLabel"></span>
                            <span> · Create and assign a school calendar event</span>
                        </small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="eventId" value="0">

                <div id="eventAudienceSummary" class="ac-modal-audience" style="display:none"></div>

                <section class="ac-modal-section">
                    <div class="ac-modal-section-head">
                        <span class="ac-modal-section-icon"><i data-lucide="calendar-days"></i></span>
                        <div>
                            <h6 class="ac-modal-section-title">Event Details</h6>
                            <p class="ac-modal-section-note">Enter the event name, date and existing event type.</p>
                        </div>
                    </div>

                    <div class="ac-form">
                        <div class="ac-field full">
                            <label>Event Name *</label>
                            <input id="eventName" maxlength="150" placeholder="Example: Quarterly Examination">
                        </div>

                        <div class="ac-field">
                            <label>Event Date *</label>
                            <input id="eventDate" type="date">
                        </div>

                        <div class="ac-field">
                            <label>Event Type *</label>
                            <select id="eventType">
                                <option value="academic">Academic</option>
                                <option value="exam">Exam</option>
                                <option value="holiday">Holiday</option>
                                <option value="meeting">Meeting</option>
                                <option value="activity">Activity</option>
                                <option value="event">Event</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                </section>

                <section class="ac-modal-section">
                    <div class="ac-modal-section-head">
                        <span class="ac-modal-section-icon"><i data-lucide="users-round"></i></span>
                        <div>
                            <h6 class="ac-modal-section-title">Applies To</h6>
                            <p class="ac-modal-section-note">Choose the Academic Year, Class and specific students if required.</p>
                        </div>
                    </div>

                    <div class="ac-form">
                        <div class="ac-field">
                            <label>Academic Year</label>
                            <input id="eventAcademicYear" readonly>
                        </div>

                        <div class="ac-field">
                            <label>Class</label>
                            <select id="eventClass">
                                <option value="0">All Classes</option>
                            </select>
                        </div>

                        <div class="ac-field full">
                            <label>Students</label>
                            <select id="eventStudents" multiple></select>
                            <small class="ac-help">
                                Leave Students unselected to apply the event to all students in the selected Class. Select a Class first to choose individual students.
                            </small>
                        </div>
                    </div>
                </section>

                <section class="ac-modal-section">
                    <div class="ac-modal-section-head">
                        <span class="ac-modal-section-icon"><i data-lucide="settings-2"></i></span>
                        <div>
                            <h6 class="ac-modal-section-title">Additional Settings</h6>
                            <p class="ac-modal-section-note">Set event visibility and optional description.</p>
                        </div>
                    </div>

                    <div class="ac-form">
                        <div class="ac-field">
                            <label>Status</label>
                            <select id="eventStatus">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>

                        <div class="ac-field full">
                            <label>Description</label>
                            <textarea id="eventDescription" maxlength="1000" placeholder="Optional event details..."></textarea>
                        </div>
                    </div>
                </section>
            </div>

            <div class="modal-footer">
                <button class="ac-btn danger me-auto" id="deleteEventBtn" type="button" style="display:none">
                    <i data-lucide="trash-2"></i>
                    Delete
                </button>
                <button class="ac-btn" type="button" data-bs-dismiss="modal">Cancel</button>
                <button class="ac-btn primary" id="saveEventBtn" type="button">
                    <i data-lucide="save"></i>
                    Save Event
                </button>
            </div>
        </div>
    </div>
</div>
<script>
(() => {
    const $ = id => document.getElementById(id);
    const endpoint = window.location.pathname;
    let csrfToken = <?= json_encode($csrfToken, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    let selectedAcademicYearId = <?= (int)$currentAcademicYearId ?>;
    let permissions = {view:true,create:true,edit:true,delete:true,print:true};
    let events = [];
    let viewDate = new Date();
    viewDate.setDate(1);
    let eventModal = null;
    let initialized = false;
    let audienceClasses = [];
    let audienceStudents = [];

    function getEventModal() {
        if (eventModal) return eventModal;
        if (!window.bootstrap || !window.bootstrap.Modal) {
            throw new Error('Bootstrap modal library is not loaded. Refresh the page once.');
        }
        eventModal = window.bootstrap.Modal.getOrCreateInstance($('eventModal'));
        return eventModal;
    }

    function toast(type, message) {
        if (typeof window.showToast === 'function') return window.showToast(type, message);
        if (window.SchoolToast && typeof window.SchoolToast[type] === 'function') return window.SchoolToast[type](message);
        if (typeof window.schoolToast === 'function') return window.schoolToast(type, message);
        console[type === 'error' ? 'error' : 'log'](message);
    }

    function esc(value) {
        return String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
    }

    async function api(action, params = {}, method = 'GET') {
        let url = endpoint + '?ajax=1&action=' + encodeURIComponent(action);
        const options = {
            credentials:'same-origin',
            cache:'no-store',
            headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}
        };

        if (method === 'GET') {
            const query = new URLSearchParams(params);
            if ([...query].length) url += '&' + query.toString();
        } else {
            options.method = 'POST';
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify({...params,csrf_token:csrfToken});
        }

        let response;
        try {
            response = await fetch(url, options);
        } catch (_) {
            throw new Error('Unable to connect to Academic Calendar. Check your server and refresh the page.');
        }

        const text = await response.text();
        let result;
        try {
            result = JSON.parse(text);
        } catch (_) {
            console.error('Academic Calendar invalid response:', text);
            throw new Error('Academic Calendar returned an invalid server response.');
        }

        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Academic Calendar request failed.');
        }
        if (result.data?.csrf_token) csrfToken = result.data.csrf_token;
        return result.data || {};
    }

    function monthKey(date) {
        return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2,'0');
    }

    function isoDate(date) {
        return date.getFullYear() + '-' + String(date.getMonth()+1).padStart(2,'0') + '-' + String(date.getDate()).padStart(2,'0');
    }

    function humanMonth(date) {
        return date.toLocaleDateString(undefined,{month:'long',year:'numeric'});
    }

    function selectedYearName() {
        return $('academicYearSelect')?.selectedOptions?.[0]?.textContent
            || $('yearChipLabel').textContent
            || '';
    }

    function eventStudentsLabel(item) {
        const names = Array.isArray(item.student_names)
            ? item.student_names.filter(Boolean)
            : [];

        if (names.length) {
            if (names.length <= 2) return names.join(', ');
            return names.slice(0,2).join(', ') + ` +${names.length-2} more`;
        }

        return Number(item.class_id || 0) > 0
            ? 'All Students in Class'
            : 'All Students';
    }

    function eventClassLabel(item) {
        return item.class_name
            || (Number(item.class_id || 0) > 0 ? 'Selected Class' : 'All Classes');
    }

    function eventAudienceText(item) {
        return [
            item.academic_year_name || selectedYearName() || 'Academic Year',
            eventClassLabel(item),
            eventStudentsLabel(item)
        ].join(' · ');
    }

    function renderAudienceOptions(classId = 0, selectedStudentIds = []) {
        classId = Number(classId || 0);
        const selectedSet = new Set(
            (Array.isArray(selectedStudentIds) ? selectedStudentIds : [])
                .map(Number)
                .filter(id => id > 0)
        );

        $('eventAcademicYear').value = selectedYearName();

        $('eventClass').innerHTML =
            '<option value="0">All Classes</option>'
            + audienceClasses.map(row =>
                `<option value="${Number(row.id)}" ${Number(row.id)===classId?'selected':''}>${esc(row.class_name)}</option>`
            ).join('');

        const filteredStudents = classId > 0
            ? audienceStudents.filter(row => Number(row.class_id) === classId)
            : [];

        $('eventStudents').innerHTML = filteredStudents.length
            ? filteredStudents.map(row => {
                const section = row.section_name ? ` - ${row.section_name}` : '';
                const admission = row.admission_no ? ` (${row.admission_no})` : '';
                return `<option value="${Number(row.id)}" ${selectedSet.has(Number(row.id))?'selected':''}>${esc(row.student_name + admission + section)}</option>`;
            }).join('')
            : '<option value="" disabled>' + (classId > 0 ? 'No active students in this Class' : 'Select a Class to choose Students') + '</option>';

        $('eventStudents').disabled = classId <= 0 || !filteredStudents.length;
    }

    async function loadAudience() {
        const data = await api('audience',{
            academic_year_id:selectedAcademicYearId
        });

        const audience = data.audience || {};
        audienceClasses = Array.isArray(audience.classes) ? audience.classes : [];
        audienceStudents = Array.isArray(audience.students) ? audience.students : [];
        renderAudienceOptions(0, []);
    }

    function renderEventAudienceSummary(item) {
        const summary = $('eventAudienceSummary');
        if (!summary) return;

        summary.innerHTML = `
            <div><span>Academic Year</span><strong>${esc(item.academic_year_name || selectedYearName() || '—')}</strong></div>
            <div><span>Class</span><strong>${esc(eventClassLabel(item))}</strong></div>
            <div><span>Students</span><strong title="${esc(eventStudentsLabel(item))}">${esc(eventStudentsLabel(item))}</strong></div>
        `;
        summary.style.display = '';
    }

    function renderCalendar() {
        $('monthTitle').textContent = humanMonth(viewDate);
        $('printTitle').textContent = 'Academic Calendar - ' + humanMonth(viewDate) + ' - ' + ($('yearChipLabel').textContent || '');

        const year = viewDate.getFullYear();
        const month = viewDate.getMonth();
        const first = new Date(year, month, 1);
        const start = new Date(year, month, 1 - first.getDay());
        const today = isoDate(new Date());
        let html = '';

        for (let index=0; index<42; index++) {
            const day = new Date(start);
            day.setDate(start.getDate() + index);
            const key = isoDate(day);
            const outside = day.getMonth() !== month;
            const dayEvents = events.filter(item => item.event_date === key && item.status === 'active');
            html += `<div class="ac-day ${outside?'outside':''} ${key===today?'today':''}" data-date="${key}">
                <div class="ac-date-row"><span class="ac-date">${day.getDate()}</span>${!outside && permissions.create?`<button class="ac-add-day" data-add-date="${key}" type="button" title="Add event"><i data-lucide="plus"></i></button>`:''}</div>
                <div class="ac-events">${dayEvents.slice(0,4).map(item => `<button type="button" class="ac-event ${esc(item.event_type)}" data-event-id="${Number(item.id)}" title="${esc(eventAudienceText(item))}"><strong>${esc(item.event_name)}</strong><small>${esc(item.event_type)}</small><small class="ac-event-audience">${esc(eventAudienceText(item))}</small></button>`).join('')}${dayEvents.length>4?`<small style="color:#64748b;font-size:8px">+${dayEvents.length-4} more</small>`:''}</div>
            </div>`;
        }
        $('calendarGrid').innerHTML = html;
        window.lucide?.createIcons();
    }

    function renderSummary() {
        const active = events.filter(item => item.status === 'active');
        const today = isoDate(new Date());
        $('totalEvents').textContent = active.length;
        $('academicEvents').textContent = active.filter(item => ['academic','exam'].includes(item.event_type)).length;
        $('holidayEvents').textContent = active.filter(item => item.event_type === 'holiday').length;
        $('upcomingEvents').textContent = active.filter(item => item.event_date >= today).length;
    }

    function renderUpcoming() {
        const today = isoDate(new Date());
        const upcoming = events
            .filter(item => item.status === 'active' && item.event_date >= today)
            .sort((a,b) => String(a.event_date).localeCompare(String(b.event_date)))
            .slice(0,8);

        $('upcomingInfo').textContent = humanMonth(viewDate);
        if (!upcoming.length) {
            $('upcomingList').innerHTML = '<div class="ac-empty">No upcoming events in this month.</div>';
            return;
        }

        $('upcomingList').innerHTML = upcoming.map(item => {
            const d = new Date(item.event_date + 'T00:00:00');
            return `<div class="ac-upcoming-item">
                <div class="ac-upcoming-date"><strong>${d.getDate()}</strong><small>${d.toLocaleDateString(undefined,{month:'short'})}</small></div>
                <div class="ac-upcoming-copy"><strong>${esc(item.event_name)}</strong><small>${esc(item.description || 'No description')}</small><small class="ac-upcoming-meta">Academic Year: ${esc(item.academic_year_name || selectedYearName() || '—')} · Class: ${esc(eventClassLabel(item))} · Students: ${esc(eventStudentsLabel(item))}</small></div>
                <span class="ac-type">${esc(item.event_type)}</span>
            </div>`;
        }).join('');
    }

    function resetForm(date = '') {
        $('eventId').value = '0';
        $('eventName').value = '';
        $('eventDate').value = date || isoDate(new Date());
        $('eventType').value = 'academic';
        $('eventStatus').value = 'active';
        $('eventDescription').value = '';
        $('eventAcademicYear').value = selectedYearName();
        renderAudienceOptions(0, []);
        $('eventAudienceSummary').style.display = 'none';
        $('eventModalTitle').textContent = 'Add Calendar Event';
        $('modalYearLabel').textContent = $('yearChipLabel').textContent || '';
        $('deleteEventBtn').style.display = 'none';
        $('saveEventBtn').style.display = permissions.create ? '' : 'none';
    }

    function openAddModal(date = '') {
        try {
            resetForm(date);
            getEventModal().show();
        } catch (error) {
            toast('error', error.message || 'Unable to open Add Event.');
        }
    }

    function openEvent(id) {
        const item = events.find(row => Number(row.id) === Number(id));
        if (!item) return;
        $('eventId').value = String(item.id);
        $('eventName').value = item.event_name || '';
        $('eventDate').value = item.event_date || '';
        $('eventType').value = item.event_type || 'academic';
        $('eventStatus').value = item.status || 'active';
        $('eventDescription').value = item.description || '';
        $('eventAcademicYear').value = item.academic_year_name || selectedYearName();
        renderAudienceOptions(
            Number(item.class_id || 0),
            Array.isArray(item.student_ids) ? item.student_ids : []
        );
        renderEventAudienceSummary(item);
        $('eventModalTitle').textContent = permissions.edit ? 'Edit Calendar Event' : 'Calendar Event';
        $('modalYearLabel').textContent = $('yearChipLabel').textContent || '';
        $('deleteEventBtn').style.display = permissions.delete ? '' : 'none';
        $('saveEventBtn').style.display = permissions.edit ? '' : 'none';
        try {
            getEventModal().show();
        } catch (error) {
            toast('error', error.message || 'Unable to open Calendar Event.');
        }
    }

    async function loadMeta() {
        const data = await api('meta',{academic_year_id:selectedAcademicYearId});
        csrfToken = data.csrf_token || csrfToken;
        permissions = data.permissions || permissions;
        selectedAcademicYearId = Number(data.selected_academic_year_id || selectedAcademicYearId || 0);
        const years = Array.isArray(data.academic_years) ? data.academic_years : [];
        const audience = data.audience || {};
        audienceClasses = Array.isArray(audience.classes) ? audience.classes : [];
        audienceStudents = Array.isArray(audience.students) ? audience.students : [];

        $('academicYearSelect').innerHTML = years.length
            ? years.map(year => `<option value="${Number(year.id)}" ${Number(year.id)===selectedAcademicYearId?'selected':''}>${esc(year.year_name)}</option>`).join('')
            : '<option value="0">No Academic Year</option>';

        const selected = years.find(year => Number(year.id) === selectedAcademicYearId);
        if (selected) $('yearChipLabel').textContent = selected.year_name;
        renderAudienceOptions(0, []);
        $('addEventBtn').style.display = permissions.create ? '' : 'none';
        $('printCalendarBtn').style.display = permissions.print ? '' : 'none';
    }

    async function loadEvents() {
        $('monthTitle').textContent = humanMonth(viewDate);
        $('calendarGrid').innerHTML = '<div class="ac-empty" style="grid-column:1/-1">Loading calendar...</div>';
        $('upcomingList').innerHTML = '<div class="ac-empty">Loading events...</div>';

        const data = await api('list',{
            academic_year_id:selectedAcademicYearId,
            month:monthKey(viewDate)
        });
        events = Array.isArray(data.events) ? data.events : [];
        renderCalendar();
        renderSummary();
        renderUpcoming();
    }

    function showLoadError(error) {
        const message = error?.message || 'Unable to load Academic Calendar.';
        $('monthTitle').textContent = humanMonth(viewDate);
        $('calendarGrid').innerHTML = `<div class="ac-empty" style="grid-column:1/-1"><strong style="display:block;color:#b91c1c;margin-bottom:5px">Unable to load calendar</strong>${esc(message)}<br><button type="button" class="ac-btn" id="calendarRetryBtn" style="margin-top:12px">Retry</button></div>`;
        $('upcomingList').innerHTML = `<div class="ac-empty">${esc(message)}</div>`;
        toast('error', message);
        document.getElementById('calendarRetryBtn')?.addEventListener('click', () => initializeData());
    }

    async function initializeData() {
        try {
            await loadMeta();
            await loadEvents();
        } catch (error) {
            showLoadError(error);
        }
    }

    function bindEvents() {
        $('prevMonthBtn').addEventListener('click', async () => {
            viewDate.setMonth(viewDate.getMonth()-1);
            try { await loadEvents(); } catch (error) { showLoadError(error); }
        });
        $('nextMonthBtn').addEventListener('click', async () => {
            viewDate.setMonth(viewDate.getMonth()+1);
            try { await loadEvents(); } catch (error) { showLoadError(error); }
        });
        $('todayBtn').addEventListener('click', async () => {
            viewDate = new Date();
            viewDate.setDate(1);
            try { await loadEvents(); } catch (error) { showLoadError(error); }
        });
        $('printCalendarBtn').addEventListener('click', () => window.print());
        $('addEventBtn').addEventListener('click', () => openAddModal());

        $('academicYearSelect').addEventListener('change', async () => {
            selectedAcademicYearId = Number($('academicYearSelect').value || 0);
            const text = $('academicYearSelect').selectedOptions[0]?.textContent || 'Academic Year';
            $('yearChipLabel').textContent = text;
            try {
                await loadAudience();
                await loadEvents();
                toast('success','Academic Calendar changed to ' + text + '.');
            } catch (error) {
                showLoadError(error);
            }
        });

        $('calendarGrid').addEventListener('click', event => {
            const add = event.target.closest('[data-add-date]');
            if (add) {
                openAddModal(add.dataset.addDate || '');
                return;
            }
            const button = event.target.closest('[data-event-id]');
            if (button) openEvent(Number(button.dataset.eventId || 0));
        });

        $('eventClass').addEventListener('change', () => {
            renderAudienceOptions(
                Number($('eventClass').value || 0),
                []
            );
        });

        $('saveEventBtn').addEventListener('click', async () => {
            const id = Number($('eventId').value || 0);
            const button = $('saveEventBtn');
            button.disabled = true;
            try {
                const name = $('eventName').value.trim();
                const date = $('eventDate').value;
                if (!name) throw new Error('Enter Event Name.');
                if (!date) throw new Error('Select Event Date.');

                const result = await api('save',{
                    id,
                    academic_year_id:selectedAcademicYearId,
                    event_name:name,
                    event_date:date,
                    event_type:$('eventType').value,
                    class_id:Number($('eventClass').value || 0),
                    student_ids:Array.from($('eventStudents').selectedOptions || [])
                        .map(option => Number(option.value || 0))
                        .filter(id => id > 0),
                    status:$('eventStatus').value,
                    description:$('eventDescription').value.trim()
                },'POST');

                getEventModal().hide();
                toast('success', result.message || (id ? 'Calendar event updated successfully.' : 'Calendar event added successfully.'));
                if (date.slice(0,7) !== monthKey(viewDate)) {
                    const next = new Date(date + 'T00:00:00');
                    viewDate = new Date(next.getFullYear(),next.getMonth(),1);
                }
                await loadEvents();
            } catch (error) {
                toast('error',error.message || 'Unable to save event.');
            } finally {
                button.disabled = false;
            }
        });

        $('deleteEventBtn').addEventListener('click', async () => {
            const id = Number($('eventId').value || 0);
            if (!id || !confirm('Delete this calendar event?')) return;
            const button = $('deleteEventBtn');
            button.disabled = true;
            try {
                await api('delete',{id,academic_year_id:selectedAcademicYearId},'POST');
                getEventModal().hide();
                toast('success','Calendar event deleted successfully.');
                await loadEvents();
            } catch (error) {
                toast('error',error.message || 'Unable to delete event.');
            } finally {
                button.disabled = false;
            }
        });
    }

    function start() {
        if (initialized) return;
        initialized = true;
        $('monthTitle').textContent = humanMonth(viewDate);
        renderCalendar();
        renderSummary();
        renderUpcoming();
        bindEvents();
        initializeData();
    }

    /*
     * The ERP loads Bootstrap JS from layout-end.php. This page script appears
     * before layout-end.php, so waiting for window.load prevents the old
     * `bootstrap is not defined` error that left the calendar on "Loading...".
     */
    if (document.readyState === 'complete') {
        start();
    } else {
        window.addEventListener('load', start, {once:true});
    }
})();
</script>

<?php require dirname(__DIR__) . '/includes/layout-end.php'; ?>

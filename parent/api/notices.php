<?php
declare(strict_types=1);

/*
 * Parent Notices API
 * Location: parent/api/notices.php
 * Build: 2026-08-13-parent-notices-v37
 *
 * Read-only.
 * Reuses the existing Parent authentication, linked-child relationship,
 * Parent sidebar permission system and existing School ERP notice sources.
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

function pnFirst(array $source, array $keys, $default = '')
{
    foreach ($keys as $key) {
        if (
            array_key_exists($key, $source)
            && $source[$key] !== null
            && $source[$key] !== ''
        ) {
            return $source[$key];
        }
    }

    return $default;
}

function pnAttachments(array $source): array
{
    $keys = [
        'attachment',
        'attachments',
        'attachment_path',
        'attachment_url',
        'file',
        'file_path',
        'file_url',
        'document',
        'document_path',
        'document_url',
        'notice_file',
        'announcement_file',
        'upload_file',
        'upload_path',
    ];

    $values = [];

    foreach ($keys as $key) {
        if (!array_key_exists($key, $source)) {
            continue;
        }

        $rawValue = $source[$key];

        if ($rawValue === null || $rawValue === '') {
            continue;
        }

        if (is_array($rawValue)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveArrayIterator($rawValue)
            );

            foreach ($iterator as $value) {
                if (is_scalar($value) && trim((string)$value) !== '') {
                    $values[] = trim((string)$value);
                }
            }

            continue;
        }

        $raw = trim((string)$rawValue);

        if ($raw === '') {
            continue;
        }

        $decoded = json_decode($raw, true);

        if (is_array($decoded)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveArrayIterator($decoded)
            );

            foreach ($iterator as $value) {
                if (is_scalar($value) && trim((string)$value) !== '') {
                    $values[] = trim((string)$value);
                }
            }

            continue;
        }

        foreach (
            preg_split('/[\r\n,;]+/', $raw) ?: []
            as $piece
        ) {
            $piece = trim($piece);

            if ($piece !== '') {
                $values[] = $piece;
            }
        }
    }

    $attachments = [];

    foreach (array_values(array_unique($values)) as $value) {
        if (
            preg_match(
                '/^(javascript|data|vbscript)\s*:/i',
                $value
            )
        ) {
            continue;
        }

        $urlPath = parse_url($value, PHP_URL_PATH);
        $name = basename((string)($urlPath ?: $value));

        $attachments[] = [
            'path' => $value,
            'name' => $name !== ''
                ? $name
                : 'Notice Attachment',
        ];
    }

    return $attachments;
}

function pnAudienceAllowsParent(array $record): bool
{
    $audience = strtolower(trim((string)pnFirst(
        $record,
        [
            'audience',
            'target_audience',
            'recipient_type',
            'target_role',
            'role_key',
            'audience_type',
        ],
        ''
    )));

    if ($audience === '') {
        return true;
    }

    foreach (
        [
            'parent',
            'parents',
            'guardian',
            'guardians',
            'all',
            'everyone',
            'school',
            'public',
        ]
        as $allowed
    ) {
        if (
            $audience === $allowed
            || str_contains($audience, $allowed)
        ) {
            return true;
        }
    }

    return false;
}

function pnRecordMatchesChild(array $record, array $child): bool
{
    $checks = [
        'student_id' => (int)($child['id'] ?? 0),
        'academic_year_id' => (int)($child['academic_year_id'] ?? 0),
        'class_id' => (int)($child['class_id'] ?? 0),
        'section_id' => (int)($child['section_id'] ?? 0),
        'branch_id' => (int)($child['branch_id'] ?? 0),
    ];

    foreach ($checks as $key => $expected) {
        if (
            !array_key_exists($key, $record)
            || $record[$key] === null
            || $record[$key] === ''
            || (int)$record[$key] === 0
        ) {
            continue;
        }

        if ($expected <= 0 || (int)$record[$key] !== $expected) {
            return false;
        }
    }

    return pnAudienceAllowsParent($record);
}

function pnAcademicUpdates(
    PDO $pdo,
    int $tenantId,
    array $child
): array {
    if (!pdTableExists($pdo, 'academic_year_module_records')) {
        return [];
    }

    /*
     * This is the same existing source already used by the Parent Dashboard
     * for notices/calendar updates. Notice/announcement module keys are
     * included when they exist in the same master table.
     */
    $stmt = $pdo->prepare(
        "SELECT id, module_key, record_data, created_at
         FROM academic_year_module_records
         WHERE tenant_id = :tenant_id
           AND status = 'active'
           AND module_key IN (
                'academic_calendar',
                'holidays',
                'notice',
                'notices',
                'announcement',
                'announcements'
           )
         ORDER BY created_at DESC, id DESC
         LIMIT 200"
    );

    $stmt->execute([
        'tenant_id' => $tenantId,
    ]);

    $rows = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $record = json_decode(
            (string)$row['record_data'],
            true
        );

        if (!is_array($record)) {
            continue;
        }

        if (!pnRecordMatchesChild($record, $child)) {
            continue;
        }

        $module = strtolower(
            (string)$row['module_key']
        );

        if ($module === 'academic_calendar') {
            $title = (string)pnFirst(
                $record,
                ['event_name', 'title'],
                'School Calendar'
            );
            $body = (string)pnFirst(
                $record,
                ['description', 'body', 'content'],
                ''
            );
            $published = (string)pnFirst(
                $record,
                ['published_date', 'event_date'],
                (string)$row['created_at']
            );
            $category = (string)pnFirst(
                $record,
                ['event_type', 'category'],
                'Calendar'
            );
        } elseif ($module === 'holidays') {
            $title = (string)pnFirst(
                $record,
                ['holiday_name', 'title'],
                'Holiday'
            );
            $body = (string)pnFirst(
                $record,
                ['description', 'body', 'content'],
                ''
            );
            $published = (string)pnFirst(
                $record,
                ['published_date', 'holiday_date'],
                (string)$row['created_at']
            );
            $category = (string)pnFirst(
                $record,
                ['holiday_type', 'category'],
                'Holiday'
            );
        } else {
            $title = (string)pnFirst(
                $record,
                [
                    'notice_title',
                    'announcement_title',
                    'title',
                    'subject',
                    'name',
                ],
                'School Notice'
            );
            $body = (string)pnFirst(
                $record,
                [
                    'description',
                    'body',
                    'content',
                    'message',
                    'details',
                ],
                ''
            );
            $published = (string)pnFirst(
                $record,
                [
                    'published_date',
                    'publish_date',
                    'notice_date',
                    'announcement_date',
                    'date',
                ],
                (string)$row['created_at']
            );
            $category = ucfirst(
                rtrim($module, 's')
            );
        }

        $rows[] = [
            'id' => 'module_' . (int)$row['id'],
            'source' => 'school_update',
            'title' => $title,
            'description' => $body,
            'published_date' => $published,
            'created_at' => (string)$row['created_at'],
            'category' => $category,
            'sender' => 'School',
            'details' => [
                'academic_year' =>
                    (string)($child['academic_year_name'] ?? ''),
                'class' =>
                    (string)($child['class_name'] ?? ''),
                'section' =>
                    (string)($child['section_name'] ?? ''),
            ],
            'attachments' => pnAttachments($record),
        ];
    }

    return $rows;
}

function pnNotifications(
    PDO $pdo,
    int $tenantId,
    int $userId
): array {
    if (
        $userId <= 0
        || !pdTableExists($pdo, 'notifications')
    ) {
        return [];
    }

    $columns = pdColumns($pdo, 'notifications');

    /*
     * Only Parent-specific notifications are returned.
     * This preserves the authorization logic from the supplied Parent API.
     */
    if (
        !in_array('tenant_id', $columns, true)
        || !in_array('user_id', $columns, true)
    ) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT *
         FROM notifications
         WHERE tenant_id = :tenant_id
           AND user_id = :user_id
         ORDER BY created_at DESC, id DESC
         LIMIT 200"
    );

    $stmt->execute([
        'tenant_id' => $tenantId,
        'user_id' => $userId,
    ]);

    $rows = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[] = [
            'id' => 'notification_' . (int)($row['id'] ?? 0),
            'source' => 'notification',
            'title' => (string)pnFirst(
                $row,
                ['title', 'subject'],
                'School Notice'
            ),
            'description' => (string)pnFirst(
                $row,
                ['body', 'message', 'description', 'content'],
                ''
            ),
            'published_date' => (string)pnFirst(
                $row,
                ['published_at', 'published_date', 'created_at'],
                ''
            ),
            'created_at' => (string)($row['created_at'] ?? ''),
            'category' => (string)pnFirst(
                $row,
                ['notification_type', 'type', 'category'],
                'Notification'
            ),
            'sender' => 'School',
            'details' => [],
            'attachments' => pnAttachments($row),
        ];
    }

    return $rows;
}

function pnMessages(
    PDO $pdo,
    int $tenantId,
    int $userId
): array {
    if (
        $userId <= 0
        || !pdTableExists($pdo, 'user_messages')
    ) {
        return [];
    }

    $columns = pdColumns($pdo, 'user_messages');

    if (
        !in_array('tenant_id', $columns, true)
        || !in_array('receiver_user_id', $columns, true)
    ) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT
            um.*,
            sender.name AS sender_name
         FROM user_messages um
         LEFT JOIN users sender
            ON sender.id = um.sender_user_id
           AND sender.tenant_id = um.tenant_id
         WHERE um.tenant_id = :tenant_id
           AND um.receiver_user_id = :user_id
         ORDER BY um.created_at DESC, um.id DESC
         LIMIT 200"
    );

    $stmt->execute([
        'tenant_id' => $tenantId,
        'user_id' => $userId,
    ]);

    $rows = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[] = [
            'id' => 'message_' . (int)($row['id'] ?? 0),
            'source' => 'message',
            'title' => (string)pnFirst(
                $row,
                ['subject', 'title'],
                'School Message'
            ),
            'description' => (string)pnFirst(
                $row,
                ['message', 'body', 'description', 'content'],
                ''
            ),
            'published_date' => (string)pnFirst(
                $row,
                ['published_at', 'published_date', 'created_at'],
                ''
            ),
            'created_at' => (string)($row['created_at'] ?? ''),
            'category' => 'Message',
            'sender' => (string)pnFirst(
                $row,
                ['sender_name'],
                'School'
            ),
            'details' => [],
            'attachments' => pnAttachments($row),
        ];
    }

    return $rows;
}

function pnNoticeTimestamp(array $notice): int
{
    foreach (
        [
            $notice['published_date'] ?? '',
            $notice['created_at'] ?? '',
        ]
        as $value
    ) {
        if ($value === null || $value === '') {
            continue;
        }

        $time = strtotime((string)$value);

        if ($time !== false) {
            return $time;
        }
    }

    return 0;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    pdJson(
        false,
        'Database connection unavailable.',
        [],
        500
    );
}

try {
    $scope = pdScope();

    $role = pc_role(
        $pdo,
        (int)$scope['tenant_id'],
        (int)(
            pdUser()['role_id']
            ?? $_SESSION['role_id']
            ?? 0
        )
    );

    if (
        pc_role_key(
            (string)($role['role_key'] ?? '')
        ) !== 'parent'
    ) {
        throw new RuntimeException(
            'Notices is available only to Parent accounts.',
            403
        );
    }

    $allowedMenus = pdParentMenuKeys(
        $pdo,
        $scope
    );

    if (
        !in_array(
            'parent_notices',
            $allowedMenus,
            true
        )
    ) {
        throw new RuntimeException(
            'Notices is disabled for this Parent account.',
            403
        );
    }

    $guardian = pdGuardian(
        $pdo,
        $scope
    );

    $children = pdChildren(
        $pdo,
        (int)$scope['tenant_id'],
        (int)$guardian['id'],
        (int)$scope['user_id']
    );

    $child = pdSelectedChild(
        $children,
        (int)($_GET['student_id'] ?? 0)
    );

    $notices = array_merge(
        pnAcademicUpdates(
            $pdo,
            (int)$scope['tenant_id'],
            $child
        ),
        pnNotifications(
            $pdo,
            (int)$scope['tenant_id'],
            (int)$scope['user_id']
        ),
        pnMessages(
            $pdo,
            (int)$scope['tenant_id'],
            (int)$scope['user_id']
        )
    );

    usort(
        $notices,
        static fn(array $a, array $b): int =>
            pnNoticeTimestamp($b)
            <=> pnNoticeTimestamp($a)
    );

    pdJson(
        true,
        'Parent Notices loaded.',
        [
            'parent' => [
                'guardian_id' => (int)$guardian['id'],
                'guardian_name' =>
                    (string)$guardian['guardian_name'],
            ],
            'children' => $children,
            'selected_child' => $child,
            'allowed_menu_keys' => $allowedMenus,
            'notices' => $notices,
            'summary' => [
                'total_notices' => count($notices),
                'school_updates' => count(
                    array_filter(
                        $notices,
                        static fn(array $row): bool =>
                            ($row['source'] ?? '')
                            === 'school_update'
                    )
                ),
                'messages' => count(
                    array_filter(
                        $notices,
                        static fn(array $row): bool =>
                            ($row['source'] ?? '')
                            === 'message'
                    )
                ),
                'attachments' => array_sum(
                    array_map(
                        static fn(array $row): int =>
                            count(
                                is_array(
                                    $row['attachments'] ?? null
                                )
                                    ? $row['attachments']
                                    : []
                            ),
                        $notices
                    )
                ),
            ],
            'generated_at' => date('c'),
        ]
    );
} catch (Throwable $exception) {
    $status = (int)$exception->getCode();

    if ($status < 400 || $status > 599) {
        $status = 500;
    }

    error_log(
        'Parent Notices API: '
        . $exception->getMessage()
    );

    pdJson(
        false,
        $status >= 500
            ? 'Unable to load Parent Notices.'
            : $exception->getMessage(),
        [],
        $status
    );
}

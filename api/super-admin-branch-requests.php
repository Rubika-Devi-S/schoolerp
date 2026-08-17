<?php
declare(strict_types=1);

/*
 * Super Admin Branch Request Approval API
 * Location: api/super-admin-branch-requests.php
 * Build: 2026-08-14-strict-branch-isolation-v48
 */

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

/*
 * Always return JSON for runtime fatal errors too.
 * This prevents the frontend from receiving an HTML fatal-error page.
 */
$GLOBALS['sabr_json_sent'] = false;

register_shutdown_function(
    static function (): void {
        if (!empty($GLOBALS['sabr_json_sent'])) {
            return;
        }

        $error = error_get_last();

        if (
            !$error
            || !in_array(
                (int)$error['type'],
                [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR],
                true
            )
        ) {
            return;
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate');
        }

        echo json_encode(
            [
                'success' => false,
                'message' => 'Branch Request API failed: ' . (string)$error['message'],
                'data' => [
                    'build' => '2026-08-14-strict-branch-isolation-v48',
                ],
            ],
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_INVALID_UTF8_SUBSTITUTE
        );
    }
);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

const SUPER_ADMIN_BRANCH_REQUEST_BUILD = '2026-08-14-strict-branch-isolation-v48';

function sabrOut(
    bool $success,
    string $message = '',
    array $data = [],
    int $status = 200
): void {
    $GLOBALS['sabr_json_sent'] = true;

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
    }

    $data['build'] = $data['build']
        ?? SUPER_ADMIN_BRANCH_REQUEST_BUILD;

    echo json_encode(
        [
            'success' => $success,
            'message' => $message,
            'data' => $data,
        ],
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}

function sabrTable(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = ?"
    );

    $stmt->execute([$table]);

    return (int)$stmt->fetchColumn() > 0;
}

function sabrColumn(
    PDO $pdo,
    string $table,
    string $column
): bool {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = ?
           AND column_name = ?"
    );

    $stmt->execute([
        $table,
        $column,
    ]);

    return (int)$stmt->fetchColumn() > 0;
}

function sabrUser(): array
{
    $user = function_exists('current_user')
        ? current_user()
        : [];

    return is_array($user)
        ? $user
        : [];
}

function sabrRequireSuperAdmin(PDO $pdo): array
{
    $user = sabrUser();

    $userId = (int)(
        $user['id']
        ?? $user['user_id']
        ?? $_SESSION['user_id']
        ?? 0
    );

    $roleId = (int)(
        $user['role_id']
        ?? $_SESSION['role_id']
        ?? 0
    );

    $roleKey = strtolower(
        trim(
            (string)(
                $user['role_key']
                ?? $_SESSION['role_key']
                ?? ''
            )
        )
    );

    if (
        $roleKey === ''
        && $roleId > 0
        && sabrTable($pdo, 'roles')
    ) {
        try {
            $stmt = $pdo->prepare(
                "SELECT role_key
                 FROM roles
                 WHERE id = ?
                 LIMIT 1"
            );

            $stmt->execute([$roleId]);

            $roleKey = strtolower(
                trim(
                    (string)(
                        $stmt->fetchColumn()
                        ?: ''
                    )
                )
            );
        } catch (Throwable $exception) {
            error_log(
                'branch request role lookup: '
                . $exception->getMessage()
            );
        }
    }

    if ($userId <= 0) {
        sabrOut(
            false,
            'Login session is required.',
            [],
            401
        );
    }

    $allowed =
        $roleId === 1
        || in_array(
            $roleKey,
            [
                'super_admin',
                'super-admin',
                'superadministrator',
                'super_administrator',
                'super-administrator',
            ],
            true
        );

    if (!$allowed) {
        sabrOut(
            false,
            'Only Super Administrator can manage Branch Requests.',
            [],
            403
        );
    }

    return [
        'user_id' => $userId,
        'role_id' => $roleId,
        'role_key' => $roleKey,
    ];
}


function sabrOperationalBranchTables(): array
{
    /*
     * Only branch-owned operational tables are included.
     * School-wide configuration tables are intentionally excluded.
     */
    return [
        'students',
        'student_enrollments',
        'student_attendance',
        'attendance_change_requests',
        'staff_members',
        'employees',
        'classes',
        'class_management_classes',
        'school_sections',
        'sections',
        'school_subjects',
        'class_subject_allocations',
        'class_teacher_allocations',
        'class_timetable_entries',
        'subject_class_assignments',
        'section_subject_assignments',
        'section_timetable_assignments',
        'subject_teacher_timetable_assignments',
        'fee_structures',
        'student_fee_assignments',
        'student_fee_items',
        'fee_receipts',
        'fee_receipt_items',
        'fee_payments',
        'exams',
        'exam_schedules',
        'exam_marks',
        'exam_results',
        'student_transport_assignments',
        'academic_year_module_records',
        'extra_fee_batches',
        'extra_fee_student_assignments',
        'employee_salary_structures',
        'student_certificate_logs',
        'student_management_profiles',
        'student_profile_extras'
    ];
}

function sabrPinLegacyRowsToMainBranch(
    PDO $pdo,
    int $tenantId
): int {
    if ($tenantId <= 0) {
        throw new InvalidArgumentException(
            'Invalid School for branch isolation.'
        );
    }

    $mainStmt = $pdo->prepare(
        "SELECT id
         FROM branches
         WHERE tenant_id = :tenant_id
           AND status = 'active'
         ORDER BY is_main DESC, id ASC
         LIMIT 1
         FOR UPDATE"
    );

    $mainStmt->execute([
        'tenant_id' => $tenantId,
    ]);

    $mainBranchId = (int)$mainStmt->fetchColumn();

    /*
     * If this is the very first branch, there is no historical branch to pin.
     */
    if ($mainBranchId <= 0) {
        return 0;
    }

    $updated = 0;

    foreach (sabrOperationalBranchTables() as $table) {
        if (
            !sabrTable($pdo, $table)
            || !sabrColumn($pdo, $table, 'tenant_id')
            || !sabrColumn($pdo, $table, 'branch_id')
        ) {
            continue;
        }

        /*
         * Table names come only from the fixed internal whitelist above.
         */
        $sql =
            "UPDATE `{$table}`
             SET branch_id = :branch_id
             WHERE tenant_id = :tenant_id
               AND (branch_id IS NULL OR branch_id = 0)";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            'branch_id' => $mainBranchId,
            'tenant_id' => $tenantId,
        ]);

        $updated += $stmt->rowCount();
    }

    return $updated;
}

function sabrInput(): array
{
    $contentType = strtolower(
        (string)(
            $_SERVER['CONTENT_TYPE']
            ?? ''
        )
    );

    if (
        strpos(
            $contentType,
            'application/json'
        ) !== false
    ) {
        $data = json_decode(
            (string)file_get_contents('php://input'),
            true
        );

        return is_array($data)
            ? $data
            : [];
    }

    return $_POST;
}

function sabrCsrfToken(): string
{
    if (
        empty($_SESSION['super_branch_request_csrf'])
        || !is_string($_SESSION['super_branch_request_csrf'])
    ) {
        $_SESSION['super_branch_request_csrf'] =
            bin2hex(random_bytes(32));
    }

    return (string)$_SESSION['super_branch_request_csrf'];
}

function sabrCsrf(array $input): void
{
    $session = sabrCsrfToken();

    $request = (string)(
        $input['csrf_token']
        ?? ''
    );

    if (
        $session === ''
        || $request === ''
        || !hash_equals(
            $session,
            $request
        )
    ) {
        sabrOut(
            false,
            'Invalid or expired CSRF token. Refresh the page.',
            [],
            419
        );
    }
}

function sabrLog(
    PDO $pdo,
    array $user,
    int $tenantId,
    string $action,
    int $requestId,
    string $description,
    array $values = []
): void {
    if (!sabrTable($pdo, 'activity_logs')) {
        return;
    }

    $required = [
        'tenant_id',
        'user_id',
        'role_id',
        'module_name',
        'action_key',
        'table_name',
        'record_id',
        'new_values',
        'description',
    ];

    foreach ($required as $column) {
        if (
            !sabrColumn(
                $pdo,
                'activity_logs',
                $column
            )
        ) {
            return;
        }
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO activity_logs
                (
                    tenant_id,
                    branch_id,
                    user_id,
                    role_id,
                    module_name,
                    action_key,
                    table_name,
                    record_id,
                    new_values,
                    description,
                    ip_address,
                    user_agent
                )
             VALUES
                (
                    :tenant_id,
                    NULL,
                    :user_id,
                    :role_id,
                    'Branch Requests',
                    :action_key,
                    'branch_creation_requests',
                    :record_id,
                    :new_values,
                    :description,
                    :ip_address,
                    :user_agent
                )"
        );

        $stmt->execute([
            'tenant_id' => $tenantId,
            'user_id' =>
                $user['user_id'] ?: null,
            'role_id' =>
                $user['role_id'] ?: null,
            'action_key' => $action,
            'record_id' =>
                $requestId ?: null,
            'new_values' => json_encode(
                $values,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_INVALID_UTF8_SUBSTITUTE
            ),
            'description' =>
                mb_substr(
                    $description,
                    0,
                    255
                ),
            'ip_address' =>
                $_SERVER['REMOTE_ADDR']
                ?? null,
            'user_agent' =>
                mb_substr(
                    (string)(
                        $_SERVER['HTTP_USER_AGENT']
                        ?? ''
                    ),
                    0,
                    1000
                ) ?: null,
        ]);
    } catch (Throwable $exception) {
        error_log(
            'branch request audit: '
            . $exception->getMessage()
        );
    }
}

if (
    !isset($pdo)
    || !($pdo instanceof PDO)
) {
    sabrOut(
        false,
        'Database connection is missing.',
        [],
        500
    );
}

$user = sabrRequireSuperAdmin($pdo);

if (
    !sabrTable($pdo, 'branches')
    || !sabrTable($pdo, 'tenants')
    || !sabrTable($pdo, 'branch_creation_requests')
) {
    sabrOut(
        false,
        'Required table is missing. Run 20260813_branch_creation_requests.sql first.',
        [],
        500
    );
}

try {
    $input = sabrInput();

    $action = strtolower(
        trim(
            (string)(
                $input['action']
                ?? $_GET['action']
                ?? 'load'
            )
        )
    );

    if ($action === 'load') {
        $status = strtolower(
            trim(
                (string)(
                    $_GET['status']
                    ?? 'pending'
                )
            )
        );

        if (
            !in_array(
                $status,
                [
                    'pending',
                    'approved',
                    'rejected',
                    'all',
                ],
                true
            )
        ) {
            $status = 'pending';
        }

        $whereSql = '';
        $params = [];

        if ($status !== 'all') {
            $whereSql =
                " WHERE r.status = :status";

            $params['status'] =
                $status;
        }

        /*
         * Keep the main request query dependent only on the required
         * request + tenant tables. User names are optional.
         */
        $requesterNameSql =
            "'School Admin' AS requested_by_name";

        $deciderNameSql =
            "NULL AS decided_by_name";

        $userJoins = '';

        if (sabrTable($pdo, 'users')) {
            $requesterNameSql =
                "COALESCE(requester.name,'School Admin') AS requested_by_name";

            $deciderNameSql =
                "decider.name AS decided_by_name";

            $userJoins =
                " LEFT JOIN users requester
                    ON requester.id = r.requested_by
                   AND requester.tenant_id = r.tenant_id
                  LEFT JOIN users decider
                    ON decider.id = r.decided_by";
        }

        $sql =
            "SELECT
                r.id,
                r.tenant_id,
                r.branch_code,
                r.branch_name,
                r.address,
                r.phone,
                r.requested_by,
                r.status,
                r.decision_notes,
                r.decided_by,
                r.decided_at,
                r.created_branch_id,
                r.created_at,
                r.updated_at,
                t.tenant_code,
                t.school_name,
                t.status AS school_status,
                {$requesterNameSql},
                {$deciderNameSql}
             FROM branch_creation_requests r
             INNER JOIN tenants t
                ON t.id = r.tenant_id
             {$userJoins}
             {$whereSql}
             ORDER BY
                CASE r.status
                    WHEN 'pending' THEN 1
                    WHEN 'approved' THEN 2
                    WHEN 'rejected' THEN 3
                    ELSE 4
                END,
                r.created_at DESC,
                r.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $records =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];

        $statsStmt = $pdo->query(
            "SELECT
                COUNT(*) AS total,
                COALESCE(SUM(status='pending'),0) AS pending,
                COALESCE(SUM(status='approved'),0) AS approved,
                COALESCE(SUM(status='rejected'),0) AS rejected
             FROM branch_creation_requests"
        );

        $stats =
            $statsStmt->fetch(
                PDO::FETCH_ASSOC
            ) ?: [];

        sabrOut(
            true,
            'Branch Requests loaded successfully.',
            [
                'records' => $records,
                'stats' => [
                    'total' =>
                        (int)($stats['total'] ?? 0),
                    'pending' =>
                        (int)($stats['pending'] ?? 0),
                    'approved' =>
                        (int)($stats['approved'] ?? 0),
                    'rejected' =>
                        (int)($stats['rejected'] ?? 0),
                ],
                'csrf_token' =>
                    sabrCsrfToken(),
            ]
        );
    }

    if ($action === 'decide') {
        if (
            $_SERVER['REQUEST_METHOD']
            !== 'POST'
        ) {
            sabrOut(
                false,
                'Method not allowed.',
                [],
                405
            );
        }

        sabrCsrf($input);

        $requestId = max(
            0,
            (int)(
                $input['request_id']
                ?? 0
            )
        );

        $decision = strtolower(
            trim(
                (string)(
                    $input['decision']
                    ?? ''
                )
            )
        );

        $decisionNotes = trim(
            (string)(
                $input['decision_notes']
                ?? ''
            )
        );

        if ($requestId <= 0) {
            throw new InvalidArgumentException(
                'A valid Branch Request is required.'
            );
        }

        if (
            !in_array(
                $decision,
                ['approve', 'reject'],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Decision must be Approve or Reject.'
            );
        }

        if (
            mb_strlen($decisionNotes)
            > 1000
        ) {
            throw new InvalidArgumentException(
                'Decision note cannot exceed 1000 characters.'
            );
        }

        $pdo->beginTransaction();

        try {
            $requestStmt =
                $pdo->prepare(
                    "SELECT
                        r.*,
                        t.school_name,
                        t.status AS school_status
                     FROM branch_creation_requests r
                     INNER JOIN tenants t
                        ON t.id = r.tenant_id
                     WHERE r.id = :request_id
                     LIMIT 1
                     FOR UPDATE"
                );

            $requestStmt->execute([
                'request_id' =>
                    $requestId,
            ]);

            $request =
                $requestStmt->fetch(
                    PDO::FETCH_ASSOC
                );

            if (!$request) {
                throw new RuntimeException(
                    'Branch Request was not found.',
                    404
                );
            }

            if (
                strtolower(
                    (string)$request['status']
                ) !== 'pending'
            ) {
                throw new InvalidArgumentException(
                    'This Branch Request has already been decided.'
                );
            }

            $tenantId =
                (int)$request['tenant_id'];

            if ($decision === 'approve') {
                /*
                 * Strict isolation:
                 * before creating another branch, any legacy operational row
                 * that has no branch ownership is assigned to the existing
                 * Main Branch. The new branch therefore starts empty.
                 */
                $legacyRowsPinned =
                    sabrPinLegacyRowsToMainBranch(
                        $pdo,
                        $tenantId
                    );

                if (
                    !in_array(
                        strtolower(
                            (string)$request[
                                'school_status'
                            ]
                        ),
                        ['trial', 'active'],
                        true
                    )
                ) {
                    throw new InvalidArgumentException(
                        'This school is suspended or cancelled. The Branch Request cannot be approved.'
                    );
                }

                $duplicate =
                    $pdo->prepare(
                        "SELECT id
                         FROM branches
                         WHERE tenant_id = :tenant_id
                           AND branch_code = :branch_code
                         LIMIT 1"
                    );

                $duplicate->execute([
                    'tenant_id' =>
                        $tenantId,
                    'branch_code' =>
                        $request['branch_code'],
                ]);

                if (
                    $duplicate->fetchColumn()
                ) {
                    throw new InvalidArgumentException(
                        'The requested Branch Code already exists in this school.'
                    );
                }

                $insertBranch =
                    $pdo->prepare(
                        "INSERT INTO branches
                            (
                                tenant_id,
                                branch_code,
                                branch_name,
                                address,
                                phone,
                                is_main,
                                status,
                                created_at
                            )
                         VALUES
                            (
                                :tenant_id,
                                :branch_code,
                                :branch_name,
                                :address,
                                :phone,
                                0,
                                'active',
                                CURRENT_TIMESTAMP
                            )"
                    );

                $insertBranch->execute([
                    'tenant_id' =>
                        $tenantId,
                    'branch_code' =>
                        $request['branch_code'],
                    'branch_name' =>
                        $request['branch_name'],
                    'address' =>
                        $request['address'],
                    'phone' =>
                        $request['phone'],
                ]);

                $branchId =
                    (int)$pdo
                        ->lastInsertId();

                /*
                 * New branch starts EMPTY. No student/staff/class/fee/attendance
                 * rows are copied from another branch.
                 *
                 * Grant School Administrators access to this new branch.
                 * Branch Admins are intentionally NOT auto-granted; they must be
                 * assigned through the existing user/branch access management.
                 */
                if (
                    sabrTable(
                        $pdo,
                        'user_branch_access'
                    )
                    && sabrTable(
                        $pdo,
                        'users'
                    )
                    && sabrTable(
                        $pdo,
                        'roles'
                    )
                ) {
                    $grantSchoolAdmins =
                        $pdo->prepare(
                            "INSERT INTO user_branch_access
                                (
                                    user_id,
                                    branch_id,
                                    can_access
                                )
                             SELECT
                                u.id,
                                :branch_id,
                                1
                             FROM users u
                             INNER JOIN roles r
                                ON r.id = u.role_id
                               AND r.tenant_id = u.tenant_id
                             WHERE u.tenant_id = :tenant_id
                               AND u.status = 'active'
                               AND r.status = 'active'
                               AND r.role_key IN
                                   (
                                       'school_admin',
                                       'school_administrator',
                                       'school-administrator'
                                   )
                             ON DUPLICATE KEY UPDATE
                                can_access = 1"
                        );

                    $grantSchoolAdmins
                        ->execute([
                            'branch_id' =>
                                $branchId,
                            'tenant_id' =>
                                $tenantId,
                        ]);
                }

                $update =
                    $pdo->prepare(
                        "UPDATE branch_creation_requests
                         SET
                            status = 'approved',
                            decision_notes = :decision_notes,
                            decided_by = :decided_by,
                            decided_at = CURRENT_TIMESTAMP,
                            created_branch_id = :branch_id,
                            updated_at = CURRENT_TIMESTAMP
                         WHERE id = :request_id
                           AND status = 'pending'"
                    );

                $update->execute([
                    'decision_notes' =>
                        $decisionNotes !== ''
                            ? $decisionNotes
                            : null,
                    'decided_by' =>
                        $user['user_id'],
                    'branch_id' =>
                        $branchId,
                    'request_id' =>
                        $requestId,
                ]);

                sabrLog(
                    $pdo,
                    $user,
                    $tenantId,
                    'approve',
                    $requestId,
                    'Approved Branch Request and created branch '
                        . $request['branch_name'],
                    [
                        'branch_id' =>
                            $branchId,
                        'branch_code' =>
                            $request['branch_code'],
                        'branch_name' =>
                            $request['branch_name'],
                        'status' =>
                            'approved',
                    ]
                );

                $pdo->commit();

                sabrOut(
                    true,
                    'Branch Request approved. The branch was created and activated.',
                    [
                        'request_id' =>
                            $requestId,
                        'branch_id' =>
                            $branchId,
                        'status' =>
                            'approved',
                        'legacy_rows_pinned_to_main_branch' =>
                            $legacyRowsPinned,
                        'isolation_mode' =>
                            'strict_branch',
                        'csrf_token' =>
                            sabrCsrfToken(),
                    ]
                );
            }

            $update =
                $pdo->prepare(
                    "UPDATE branch_creation_requests
                     SET
                        status = 'rejected',
                        decision_notes = :decision_notes,
                        decided_by = :decided_by,
                        decided_at = CURRENT_TIMESTAMP,
                        created_branch_id = NULL,
                        updated_at = CURRENT_TIMESTAMP
                     WHERE id = :request_id
                       AND status = 'pending'"
                );

            $update->execute([
                'decision_notes' =>
                    $decisionNotes !== ''
                        ? $decisionNotes
                        : null,
                'decided_by' =>
                    $user['user_id'],
                'request_id' =>
                    $requestId,
            ]);

            sabrLog(
                $pdo,
                $user,
                $tenantId,
                'reject',
                $requestId,
                'Rejected Branch Request '
                    . $request['branch_name'],
                [
                    'branch_code' =>
                        $request['branch_code'],
                    'branch_name' =>
                        $request['branch_name'],
                    'status' =>
                        'rejected',
                    'decision_notes' =>
                        $decisionNotes,
                ]
            );

            $pdo->commit();

            sabrOut(
                true,
                'Branch Request rejected. No branch was created.',
                [
                    'request_id' =>
                        $requestId,
                    'status' =>
                        'rejected',
                    'csrf_token' =>
                        sabrCsrfToken(),
                ]
            );
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    sabrOut(
        false,
        'Invalid action.',
        [],
        400
    );
} catch (InvalidArgumentException $exception) {
    sabrOut(
        false,
        $exception->getMessage(),
        [],
        422
    );
} catch (Throwable $exception) {
    $status =
        (int)$exception->getCode();

    if (
        $status < 400
        || $status > 599
    ) {
        $status = 500;
    }

    error_log(
        'Super Admin Branch Request API: '
        . $exception->getMessage()
    );

    sabrOut(
        false,
        $status >= 500
            ? 'Unable to process Branch Request: '
                . $exception->getMessage()
            : $exception->getMessage(),
        [],
        $status
    );
}

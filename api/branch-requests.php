<?php
declare(strict_types=1);

/*
 * School Admin Branch Request API
 * Location: api/branch-requests.php
 * Build: 2026-08-17-remove-classes-file-toggle-v60
 *
 * School Admin can:
 * - see ONLY its own school and branches
 * - see ONLY its own school branch requests
 * - create a PENDING request
 *
 * This API NEVER inserts directly into branches.
 */

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

/*
 * IMPORTANT:
 * bootstrap.php protects every School write API. This endpoint belongs to the
 * existing School sidebar/page permission key `branch_settings`.
 *
 * The key MUST be declared before bootstrap.php is loaded, otherwise a POST
 * such as create_request is intentionally blocked with:
 * "This API is not linked to a permitted School sidebar page."
 */
if (!defined('SCHOOL_API_PAGE_KEY')) {
    define('SCHOOL_API_PAGE_KEY', 'branch_settings');
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permission-chain.php';
require_once __DIR__ . '/../includes/branch-isolation.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function bra_json(
    bool $success,
    string $message = '',
    array $data = [],
    int $status = 200
): void {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code($status);
    header(
        'Content-Type: application/json; charset=utf-8'
    );
    header('Cache-Control: no-store');

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

function bra_table(
    PDO $pdo,
    string $table
): bool {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = :table_name"
    );

    $stmt->execute([
        'table_name' => $table,
    ]);

    return (int)$stmt->fetchColumn() > 0;
}


function bra_clear_legacy_classes_visibility(
    PDO $pdo,
    int $tenantId
): void {
    if (
        $tenantId <= 0
        || !bra_table($pdo, 'branch_module_visibility')
    ) {
        return;
    }

    /*
     * Classes File ON/OFF was removed from Branch Settings.
     * Delete only the old Classes override for this School.
     * A missing Classes visibility row means enabled in the existing
     * branch-aware Classes API, while tenant_id + branch_id keeps data separate.
     */
    $statement = $pdo->prepare(
        "DELETE FROM branch_module_visibility
         WHERE tenant_id = :tenant_id
           AND module_key = 'classes'"
    );

    $statement->execute([
        'tenant_id' => $tenantId,
    ]);
}

function bra_input(): array
{
    $contentType = strtolower(
        (string)(
            $_SERVER['CONTENT_TYPE']
            ?? ''
        )
    );

    if (
        str_contains(
            $contentType,
            'application/json'
        )
    ) {
        $data = json_decode(
            (string)file_get_contents(
                'php://input'
            ),
            true
        );

        return is_array($data)
            ? $data
            : [];
    }

    return $_POST;
}

function bra_scope(PDO $pdo): array
{
    $user = function_exists(
        'current_user'
    )
        ? current_user()
        : [];

    $user = is_array($user)
        ? $user
        : [];

    $tenantId = (int)(
        $user['tenant_id']
        ?? $user['school_id']
        ?? $_SESSION['tenant_id']
        ?? $_SESSION['school_id']
        ?? 0
    );

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

    if (
        $tenantId <= 0
        || $userId <= 0
        || $roleId <= 0
    ) {
        bra_json(
            false,
            'School login session not found.',
            [],
            401
        );
    }

    $role = pc_role(
        $pdo,
        $tenantId,
        $roleId
    );

    $roleKey = pc_role_key(
        (string)(
            $role['role_key']
            ?? $user['role_key']
            ?? $_SESSION['role_key']
            ?? ''
        )
    );

    if ($roleKey !== 'school_admin') {
        bra_json(
            false,
            'Only School Administrator can manage Branch Requests.',
            [],
            403
        );
    }

    return [
        'tenant_id' => $tenantId,
        'user_id' => $userId,
        'role_id' => $roleId,
    ];
}

function bra_csrf_token(): string
{
    if (
        empty(
            $_SESSION[
                'branch_request_csrf'
            ]
        )
        || !is_string(
            $_SESSION[
                'branch_request_csrf'
            ]
        )
    ) {
        $_SESSION[
            'branch_request_csrf'
        ] = bin2hex(
            random_bytes(32)
        );
    }

    return (string)(
        $_SESSION[
            'branch_request_csrf'
        ]
    );
}

function bra_csrf(array $input): void
{
    $token = (string)(
        $input['csrf_token']
        ?? ''
    );

    if (
        $token === ''
        || !hash_equals(
            bra_csrf_token(),
            $token
        )
    ) {
        bra_json(
            false,
            'Invalid or expired CSRF token.',
            [],
            419
        );
    }
}

function bra_clean(
    mixed $value,
    int $max,
    string $label,
    bool $required = true
): string {
    $value = trim(
        preg_replace(
            '/\s+/u',
            ' ',
            (string)$value
        ) ?? ''
    );

    if (
        $required
        && $value === ''
    ) {
        throw new InvalidArgumentException(
            $label . ' is required.'
        );
    }

    if (
        mb_strlen($value) > $max
    ) {
        throw new InvalidArgumentException(
            $label
            . ' cannot exceed '
            . $max
            . ' characters.'
        );
    }

    return $value;
}

function bra_phone(
    mixed $value
): string {
    $value = trim(
        (string)$value
    );

    if ($value === '') {
        return '';
    }

    if (
        !preg_match(
            '/^[0-9+\-\s()]{7,20}$/',
            $value
        )
    ) {
        throw new InvalidArgumentException(
            'Enter a valid branch phone number.'
        );
    }

    return $value;
}

function bra_generate_code(
    PDO $pdo,
    int $tenantId
): string {
    for (
        $number = 1;
        $number <= 999;
        $number++
    ) {
        $code =
            'BR-'
            . str_pad(
                (string)$number,
                3,
                '0',
                STR_PAD_LEFT
            );

        $branch = $pdo->prepare(
            "SELECT COUNT(*)
             FROM branches
             WHERE tenant_id = :tenant_id
               AND branch_code = :branch_code"
        );

        $branch->execute([
            'tenant_id' => $tenantId,
            'branch_code' => $code,
        ]);

        if (
            (int)$branch->fetchColumn()
            > 0
        ) {
            continue;
        }

        $request = $pdo->prepare(
            "SELECT COUNT(*)
             FROM branch_creation_requests
             WHERE tenant_id = :tenant_id
               AND branch_code = :branch_code
               AND status = 'pending'"
        );

        $request->execute([
            'tenant_id' => $tenantId,
            'branch_code' => $code,
        ]);

        if (
            (int)$request->fetchColumn()
            === 0
        ) {
            return $code;
        }
    }

    return 'BR-'
        . strtoupper(
            substr(
                bin2hex(
                    random_bytes(4)
                ),
                0,
                6
            )
        );
}

function bra_log(
    PDO $pdo,
    array $scope,
    string $action,
    int $recordId,
    string $description,
    array $newValues = []
): void {
    if (
        !bra_table(
            $pdo,
            'activity_logs'
        )
    ) {
        return;
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
                    user_agent,
                    created_at
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
                    :user_agent,
                    CURRENT_TIMESTAMP
                )"
        );

        $stmt->execute([
            'tenant_id' =>
                $scope['tenant_id'],
            'user_id' =>
                $scope['user_id'],
            'role_id' =>
                $scope['role_id'],
            'action_key' => $action,
            'record_id' => $recordId,
            'new_values' => json_encode(
                $newValues,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            ),
            'description' =>
                mb_substr(
                    $description,
                    0,
                    255
                ),
            'ip_address' =>
                mb_substr(
                    (string)(
                        $_SERVER[
                            'REMOTE_ADDR'
                        ]
                        ?? ''
                    ),
                    0,
                    45
                ) ?: null,
            'user_agent' =>
                (string)(
                    $_SERVER[
                        'HTTP_USER_AGENT'
                    ]
                    ?? ''
                ) ?: null,
        ]);
    } catch (Throwable $exception) {
        error_log(
            'Branch request log: '
            . $exception->getMessage()
        );
    }
}

if (
    !isset($pdo)
    || !($pdo instanceof PDO)
) {
    bra_json(
        false,
        'Database unavailable.',
        [],
        500
    );
}

foreach (
    [
        'tenants',
        'branches',
        'branch_creation_requests',
    ]
    as $requiredTable
) {
    if (
        !bra_table(
            $pdo,
            $requiredTable
        )
    ) {
        bra_json(
            false,
            'Required table is missing: '
            . $requiredTable
            . '. Run the supplied SQL migration first.',
            [],
            500
        );
    }
}

$scope = bra_scope($pdo);
$input = bra_input();

$action = strtolower(
    trim(
        (string)(
            $input['action']
            ?? $_GET['action']
            ?? 'load'
        )
    )
);

/*
 * Per-branch Classes File visibility.
 * Missing rows intentionally default to ON for existing installations.
 */
bra_clear_legacy_classes_visibility(
    $pdo,
    (int)$scope['tenant_id']
);

try {
    if ($action === 'load') {
        /*
         * School isolation:
         * every branch query uses the session tenant_id.
         * No school/tenant id is accepted from GET/POST.
         */
        $schoolStmt = $pdo->prepare(
            "SELECT
                id,
                tenant_code,
                school_name,
                legal_name,
                email,
                mobile,
                address,
                status
             FROM tenants
             WHERE id = :tenant_id
             LIMIT 1"
        );

        $schoolStmt->execute([
            'tenant_id' =>
                $scope['tenant_id'],
        ]);

        $school =
            $schoolStmt->fetch(
                PDO::FETCH_ASSOC
            );

        if (!$school) {
            throw new RuntimeException(
                'School record was not found.',
                404
            );
        }

        $branchStmt = $pdo->prepare(
            "SELECT
                b.id,
                b.branch_code,
                b.branch_name,
                b.address,
                b.phone,
                b.is_main,
                b.status,
                b.created_at
             FROM branches b
             WHERE b.tenant_id = :tenant_id
             ORDER BY
                b.is_main DESC,
                b.branch_name,
                b.id"
        );

        $branchStmt->execute([
            'tenant_id' =>
                $scope['tenant_id'],
        ]);

        $branches =
            $branchStmt->fetchAll(
                PDO::FETCH_ASSOC
            );

        $requestStmt = $pdo->prepare(
            "SELECT
                id,
                branch_code,
                branch_name,
                address,
                phone,
                status,
                decision_notes,
                decided_at,
                created_branch_id,
                created_at,
                updated_at
             FROM branch_creation_requests
             WHERE tenant_id = :tenant_id
             ORDER BY
                created_at DESC,
                id DESC"
        );

        $requestStmt->execute([
            'tenant_id' =>
                $scope['tenant_id'],
        ]);

        $requests =
            $requestStmt->fetchAll(
                PDO::FETCH_ASSOC
            );

        $stats = [
            'active_branches' => count(
                array_filter(
                    $branches,
                    static fn(array $row): bool =>
                        strtolower(
                            (string)(
                                $row['status']
                                ?? ''
                            )
                        ) === 'active'
                )
            ),
            'pending_requests' => count(
                array_filter(
                    $requests,
                    static fn(array $row): bool =>
                        ($row['status'] ?? '')
                        === 'pending'
                )
            ),
            'approved_requests' => count(
                array_filter(
                    $requests,
                    static fn(array $row): bool =>
                        ($row['status'] ?? '')
                        === 'approved'
                )
            ),
            'rejected_requests' => count(
                array_filter(
                    $requests,
                    static fn(array $row): bool =>
                        ($row['status'] ?? '')
                        === 'rejected'
                )
            ),
        ];

        bra_json(
            true,
            'Branch Settings loaded.',
            [
                'school' => $school,
                'branches' => $branches,
                'requests' => $requests,
                'stats' => $stats,
                'current_branch_id' =>
                    (int)(
                        $_SESSION['branch_id']
                        ?? $_SESSION['default_branch_id']
                        ?? 0
                    ),
                'current_branch_name' =>
                    (string)(
                        $_SESSION['branch_name']
                        ?? ''
                    ),
                'csrf_token' =>
                    bra_csrf_token(),
            ]
        );
    }

    if ($action === 'switch_branch') {
        bra_csrf($input);

        $branchId = (int)(
            $input['branch_id']
            ?? 0
        );

        if ($branchId <= 0) {
            throw new InvalidArgumentException(
                'Select a valid Branch.'
            );
        }

        /*
         * Branch Settings page is School Admin only.
         * branch_set_current_context additionally validates that the branch
         * belongs to the authenticated tenant and is active.
         */
        $branch = branch_set_current_context(
            $pdo,
            $branchId,
            true
        );

        if (bra_table($pdo, 'user_branch_access')) {
            $access = $pdo->prepare(
                "INSERT INTO user_branch_access
                    (user_id,branch_id,can_access)
                 VALUES
                    (:user_id,:branch_id,1)
                 ON DUPLICATE KEY UPDATE
                    can_access = 1"
            );

            $access->execute([
                'user_id' =>
                    $scope['user_id'],
                'branch_id' =>
                    $branchId,
            ]);
        }

        bra_log(
            $pdo,
            $scope,
            'switch_branch',
            $branchId,
            'Switched active School branch to '
                . (string)$branch['branch_name'],
            [
                'branch_id' =>
                    $branchId,
                'branch_code' =>
                    (string)$branch['branch_code'],
                'branch_name' =>
                    (string)$branch['branch_name'],
            ]
        );

        bra_json(
            true,
            'Active Branch changed to '
                . (string)$branch['branch_name']
                . '.',
            [
                'branch' => $branch,
                'current_branch_id' =>
                    $branchId,
                'current_branch_name' =>
                    (string)$branch['branch_name'],
                'current_branch_code' =>
                    (string)$branch['branch_code'],
                'isolation_mode' =>
                    'strict_branch',
                'csrf_token' =>
                    bra_csrf_token(),
            ]
        );
    }

    if ($action === 'create_request') {
        bra_csrf($input);

        $branchName = bra_clean(
            $input['branch_name']
            ?? '',
            150,
            'Branch Name'
        );

        $branchCode = strtoupper(
            bra_clean(
                $input['branch_code']
                ?? '',
                30,
                'Branch Code',
                false
            )
        );

        if (
            $branchCode !== ''
            && preg_match(
                '/^[A-Z0-9][A-Z0-9_-]{0,29}$/',
                $branchCode
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Branch Code can contain only letters, numbers, hyphen and underscore.'
            );
        }

        $phone = bra_phone(
            $input['phone']
            ?? ''
        );

        $address = bra_clean(
            $input['address']
            ?? '',
            1000,
            'Address',
            false
        );

        $schoolStmt = $pdo->prepare(
            "SELECT id,status
             FROM tenants
             WHERE id = :tenant_id
             LIMIT 1"
        );

        $schoolStmt->execute([
            'tenant_id' =>
                $scope['tenant_id'],
        ]);

        $school =
            $schoolStmt->fetch(
                PDO::FETCH_ASSOC
            );

        if (!$school) {
            throw new RuntimeException(
                'School record was not found.',
                404
            );
        }

        if (
            !in_array(
                strtolower(
                    (string)$school['status']
                ),
                ['trial', 'active'],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'A suspended or cancelled school cannot request a new branch.'
            );
        }

        $pdo->beginTransaction();

        try {
            if ($branchCode === '') {
                $branchCode =
                    bra_generate_code(
                        $pdo,
                        (int)$scope[
                            'tenant_id'
                        ]
                    );
            }

            $duplicateBranch =
                $pdo->prepare(
                    "SELECT id
                     FROM branches
                     WHERE tenant_id = :tenant_id
                       AND branch_code = :branch_code
                     LIMIT 1"
                );

            $duplicateBranch->execute([
                'tenant_id' =>
                    $scope['tenant_id'],
                'branch_code' =>
                    $branchCode,
            ]);

            if (
                $duplicateBranch
                    ->fetchColumn()
            ) {
                throw new InvalidArgumentException(
                    'This Branch Code already exists in your school.'
                );
            }

            $duplicateRequest =
                $pdo->prepare(
                    "SELECT id
                     FROM branch_creation_requests
                     WHERE tenant_id = :tenant_id
                       AND branch_code = :branch_code
                       AND status = 'pending'
                     LIMIT 1"
                );

            $duplicateRequest
                ->execute([
                    'tenant_id' =>
                        $scope['tenant_id'],
                    'branch_code' =>
                        $branchCode,
                ]);

            if (
                $duplicateRequest
                    ->fetchColumn()
            ) {
                throw new InvalidArgumentException(
                    'A pending Branch Request already uses this Branch Code.'
                );
            }

            $insert = $pdo->prepare(
                "INSERT INTO branch_creation_requests
                    (
                        tenant_id,
                        branch_code,
                        branch_name,
                        address,
                        phone,
                        requested_by,
                        status,
                        created_at,
                        updated_at
                    )
                 VALUES
                    (
                        :tenant_id,
                        :branch_code,
                        :branch_name,
                        :address,
                        :phone,
                        :requested_by,
                        'pending',
                        CURRENT_TIMESTAMP,
                        CURRENT_TIMESTAMP
                    )"
            );

            $insert->execute([
                'tenant_id' =>
                    $scope['tenant_id'],
                'branch_code' =>
                    $branchCode,
                'branch_name' =>
                    $branchName,
                'address' =>
                    $address !== ''
                        ? $address
                        : null,
                'phone' =>
                    $phone !== ''
                        ? $phone
                        : null,
                'requested_by' =>
                    $scope['user_id'],
            ]);

            $requestId =
                (int)$pdo
                    ->lastInsertId();

            bra_log(
                $pdo,
                $scope,
                'create',
                $requestId,
                'Submitted new Branch Request '
                    . $branchName,
                [
                    'branch_code' =>
                        $branchCode,
                    'branch_name' =>
                        $branchName,
                    'status' =>
                        'pending',
                ]
            );

            $pdo->commit();

            bra_json(
                true,
                'Branch request sent to Super Admin for approval.',
                [
                    'request_id' =>
                        $requestId,
                    'branch_code' =>
                        $branchCode,
                    'status' =>
                        'pending',
                    'csrf_token' =>
                        bra_csrf_token(),
                ]
            );
        } catch (Throwable $exception) {
            if (
                $pdo->inTransaction()
            ) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    bra_json(
        false,
        'Invalid action.',
        [],
        400
    );
} catch (InvalidArgumentException $exception) {
    bra_json(
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
        'School Branch Request API: '
        . $exception->getMessage()
    );

    bra_json(
        false,
        $status >= 500
            ? 'Unable to process Branch Request.'
            : $exception->getMessage(),
        [],
        $status
    );
}

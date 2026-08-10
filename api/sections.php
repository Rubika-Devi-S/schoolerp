<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');

$originalScriptName =
    (string)($_SERVER['SCRIPT_NAME'] ?? '');

$_SERVER['SCRIPT_NAME'] = '/login.php';

require_once dirname(__DIR__)
    . '/includes/bootstrap.php';

$_SERVER['SCRIPT_NAME'] = $originalScriptName;

require_once dirname(__DIR__)
    . '/includes/controllers/SectionsManagementController.php';

set_error_handler(
    static function (
        int $severity,
        string $message,
        string $file,
        int $line
    ): bool {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        throw new ErrorException(
            $message,
            0,
            $severity,
            $file,
            $line
        );
    }
);

function sectionsJson(
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

        header(
            'Content-Type: application/json; charset=utf-8'
        );

        header('Cache-Control: no-store');
    }

    echo json_encode(
        [
            'success' => $success,
            'message' => $message,
            'data' => $data,
        ],
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    );

    exit;
}

function sectionsInput(): array
{
    $contentType = strtolower(
        (string)($_SERVER['CONTENT_TYPE'] ?? '')
    );

    if (
        str_contains(
            $contentType,
            'application/json'
        )
    ) {
        $decoded = json_decode(
            (string)file_get_contents('php://input'),
            true
        );

        return is_array($decoded)
            ? $decoded
            : [];
    }

    return $_POST;
}

function sectionsCsrf(array $input): void
{
    $token = trim(
        (string)($input['csrf_token'] ?? '')
    );

    $valid = function_exists('csrf_is_valid')
        ? csrf_is_valid($token)
        : (
            isset($_SESSION['csrf_token'])
            && hash_equals(
                (string)$_SESSION['csrf_token'],
                $token
            )
        );

    if (!$valid) {
        sectionsJson(
            false,
            'Invalid or expired CSRF token.',
            [],
            419
        );
    }
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    sectionsJson(
        false,
        'Database connection unavailable.',
        [],
        500
    );
}

$resolvedUser = function_exists('current_user')
    ? current_user()
    : [];

$user = is_array($resolvedUser)
    ? $resolvedUser
    : [];

$controller = new SectionsManagementController(
    $pdo,
    $user
);

$input = sectionsInput();

$action = strtolower(
    trim(
        (string)(
            $input['action']
            ?? $_GET['action']
            ?? ''
        )
    )
);

function sectionsTableExists(
    PDO $pdo,
    string $table
): bool {
    $statement = $pdo->prepare(
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

try {
    foreach (
        [
            'school_sections',
            'section_subject_assignments',
            'section_timetable_assignments',
        ] as $requiredTable
    ) {
        if (!sectionsTableExists($pdo, $requiredTable)) {
            throw new RuntimeException(
                'Missing database table: '
                . $requiredTable
                . '. Import '
                . '20260729_sections_database_repair.sql.',
                500
            );
        }
    }

    if ($action === 'meta') {
        sectionsJson(
            true,
            'Section metadata loaded.',
            [
                'csrf_token' =>
                    function_exists('csrfToken')
                        ? csrfToken()
                        : '',
                'meta' => $controller->meta(),
                'permissions' => [
                    'view' =>
                        $controller->can('view'),
                    'add' =>
                        $controller->can('add'),
                    'edit' =>
                        $controller->can('edit'),
                    'delete' =>
                        $controller->can('delete'),
                ],
            ]
        );
    }

    if ($action === 'list') {
        sectionsJson(
            true,
            'Sections loaded.',
            [
                'sections' =>
                    $controller->list(
                        $_GET + $input
                    ),
            ]
        );
    }

    if ($action === 'related') {
        sectionsJson(
            true,
            'Section assignments loaded.',
            [
                'records' =>
                    $controller->related(
                        trim(
                            (string)(
                                $input['type']
                                ?? $_GET['type']
                                ?? ''
                            )
                        ),
                        (int)(
                            $input['section_id']
                            ?? $_GET['section_id']
                            ?? 0
                        )
                    ),
            ]
        );
    }

    sectionsCsrf($input);

    if ($action === 'save') {
        $result = $controller->save($input);

        sectionsJson(
            true,
            (int)($input['id'] ?? 0) > 0
                ? 'Section updated successfully.'
                : 'Section created successfully.',
            $result
        );
    }

    if ($action === 'delete') {
        $controller->delete(
            (int)($input['id'] ?? 0)
        );

        sectionsJson(
            true,
            'Section deleted successfully.'
        );
    }

    if ($action === 'save_related') {
        $id = $controller->saveRelated(
            trim((string)($input['type'] ?? '')),
            $input
        );

        sectionsJson(
            true,
            'Section assignment saved successfully.',
            [
                'id' => $id,
            ]
        );
    }

    if ($action === 'delete_related') {
        $controller->deleteRelated(
            trim((string)($input['type'] ?? '')),
            (int)($input['id'] ?? 0)
        );

        sectionsJson(
            true,
            'Section assignment removed successfully.'
        );
    }

    sectionsJson(
        false,
        'Invalid Sections API action.',
        [],
        400
    );
} catch (InvalidArgumentException $exception) {
    sectionsJson(
        false,
        $exception->getMessage(),
        [],
        422
    );
} catch (RuntimeException $exception) {
    $status = $exception->getCode();

    sectionsJson(
        false,
        $exception->getMessage(),
        [],
        $status >= 400 && $status <= 599
            ? $status
            : 403
    );
} catch (Throwable $exception) {
    error_log(
        'sections-api: '
        . $exception->getMessage()
    );

    $host = strtolower(
        (string)($_SERVER['HTTP_HOST'] ?? '')
    );

    sectionsJson(
        false,
        str_contains($host, 'localhost')
        || str_contains($host, '127.0.0.1')
            ? 'Section request failed: '
                . $exception->getMessage()
            : 'Unable to complete the section request.',
        [],
        500
    );
}

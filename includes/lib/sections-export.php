<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');

$originalScriptName =
    (string)($_SERVER['SCRIPT_NAME'] ?? '');

$_SERVER['SCRIPT_NAME'] = '/login.php';

require_once dirname(__DIR__, 2)
    . '/includes/bootstrap.php';

$_SERVER['SCRIPT_NAME'] = $originalScriptName;

require_once dirname(__DIR__, 2)
    . '/includes/controllers/SectionsManagementController.php';

require_once __DIR__
    . '/SimpleSectionsPdf.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Database connection unavailable.');
}

$user = function_exists('current_user')
    ? current_user()
    : [];

$controller = new SectionsManagementController(
    $pdo,
    $user
);

try {
    $sections = $controller->list($_GET);
} catch (Throwable $exception) {
    http_response_code(403);
    exit($exception->getMessage());
}

$headers = [
    'Section',
    'Code',
    'Class',
    'Academic Year',
    'Medium',
    'Shift',
    'Room',
    'Capacity',
    'Class Teacher',
    'Status',
];

$rows = array_map(
    static fn(array $row): array => [
        $row['section_name'],
        $row['section_code'],
        $row['class_name_snapshot'],
        $row['academic_year_name'] ?? '',
        $row['medium'],
        $row['shift_name'],
        $row['room_number'],
        $row['maximum_student_capacity'],
        $row['class_teacher_name'],
        $row['status'],
    ],
    $sections
);

$format = strtolower(
    trim((string)($_GET['format'] ?? 'excel'))
);

$filenameBase =
    'sections-management-'
    . date('Ymd-His');

while (ob_get_level() > 0) {
    ob_end_clean();
}

if ($format === 'pdf') {
    (new SimpleSectionsPdf())->download(
        $filenameBase . '.pdf',
        'Sections Management Report',
        $headers,
        $rows
    );
}

if ($format === 'print') {
    header(
        'Content-Type: text/html; charset=utf-8'
    );

    echo '<!doctype html><html><head>'
        . '<meta charset="utf-8">'
        . '<title>Sections Management Print</title>'
        . '<style>'
        . 'body{font-family:Arial;padding:20px}'
        . 'h1{font-size:20px}'
        . 'table{border-collapse:collapse;width:100%;'
        . 'font-size:11px}'
        . 'th,td{border:1px solid #bbb;padding:6px;'
        . 'text-align:left}'
        . '</style></head>'
        . '<body onload="window.print()">'
        . '<h1>Sections Management Report</h1>'
        . '<table><thead><tr>';

    foreach ($headers as $header) {
        echo '<th>'
            . htmlspecialchars($header)
            . '</th>';
    }

    echo '</tr></thead><tbody>';

    foreach ($rows as $row) {
        echo '<tr>';

        foreach ($row as $value) {
            echo '<td>'
                . htmlspecialchars((string)$value)
                . '</td>';
        }

        echo '</tr>';
    }

    echo '</tbody></table></body></html>';
    exit;
}

header(
    'Content-Type: application/vnd.ms-excel; '
    . 'charset=utf-8'
);

header(
    'Content-Disposition: attachment; filename="'
    . $filenameBase
    . '.xls"'
);

echo "\xEF\xBB\xBF";
echo '<table border="1"><thead><tr>';

foreach ($headers as $header) {
    echo '<th>'
        . htmlspecialchars($header)
        . '</th>';
}

echo '</tr></thead><tbody>';

foreach ($rows as $row) {
    echo '<tr>';

    foreach ($row as $value) {
        echo '<td>'
            . htmlspecialchars((string)$value)
            . '</td>';
    }

    echo '</tr>';
}

echo '</tbody></table>';

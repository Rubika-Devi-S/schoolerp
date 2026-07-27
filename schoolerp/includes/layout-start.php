<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/layout_helpers.php';

$pageTitle = $pageTitle ?? 'School ERP';
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?= e($pageTitle) ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    <?php
    if (is_file(__DIR__ . '/theme-loader.php')) {
        require __DIR__ . '/theme-loader.php';
    }
    ?>

    <link rel="stylesheet" href="assets/css/school-ui.css?v=10">
</head>

<body>
    <?php require __DIR__ . '/sidebar.php'; ?>
    <?php require __DIR__ . '/topbar.php'; ?>
    <main class="app-content">
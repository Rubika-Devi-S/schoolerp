<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/layout_helpers.php';
require_once __DIR__ . '/toast.php';

$pageTitle = $pageTitle ?? 'School ERP';

$assetBaseUrl = defined('BASE_URL')
    ? rtrim((string)BASE_URL, '/') . '/'
    : '/git/schoolerp/';

$resolvedSidebarFile = isset($sidebarFile)
    && is_string($sidebarFile)
    && is_file($sidebarFile)
        ? $sidebarFile
        : __DIR__ . '/sidebar.php';

$toastCssFile = dirname(__DIR__) . '/assets/css/toast.css';
$toastJsFile = dirname(__DIR__) . '/assets/js/toast.js';
$toastCssVersion = is_file($toastCssFile) ? (string)filemtime($toastCssFile) : '8';
$toastJsVersion = is_file($toastJsFile) ? (string)filemtime($toastJsFile) : '8';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Lato:wght@400;700;900&family=Merriweather+Sans:wght@400;500;600;700;800&family=Montserrat:wght@400;500;600;700;800&family=Nunito:wght@400;500;600;700;800&family=Open+Sans:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&family=Roboto:wght@400;500;700;900&family=Source+Sans+3:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <link
        href="<?= e($assetBaseUrl) ?>assets/css/school-ui.css?v=14"
        rel="stylesheet"
    >

    <link
        href="<?= e($assetBaseUrl) ?>assets/css/toast.css?v=<?= e($toastCssVersion) ?>"
        rel="stylesheet"
    >

    <?php
    $themeLoader = __DIR__ . '/theme-loader.php';
    if (is_file($themeLoader)) {
        require $themeLoader;
    }
    ?>

    <script>
        window.SCHOOL_ERP_BASE_URL = <?= json_encode(
            $assetBaseUrl,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) ?>;

        // Global preload queue. Any page may call showToast() immediately,
        // even before the deferred toast.js file has finished loading.
        window.__schoolToastPreQueue = window.__schoolToastPreQueue || [];
        if (typeof window.showToast !== 'function' || !window.showToast.__schoolToastReady) {
            window.showToast = function () {
                window.__schoolToastPreQueue.push(Array.prototype.slice.call(arguments));
            };
            window.showToast.__schoolToastPreloader = true;
        }
    </script>
    <script
        defer
        src="<?= e($assetBaseUrl) ?>assets/js/toast.js?v=<?= e($toastJsVersion) ?>"
    ></script>
</head>
<body>
<?php school_toast_render(); ?>
<?php require $resolvedSidebarFile; ?>
<?php require __DIR__ . '/topbar.php'; ?>

<main class="app-content">

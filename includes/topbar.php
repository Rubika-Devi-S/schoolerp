<?php
declare(strict_types=1);

/* Build: 2026-08-15-platform-topbar-actions-v2 */

/*
 * This file has two jobs without needing an extra endpoint file:
 * 1) Normal include: render the shared School ERP topbar.
 * 2) Direct AJAX POST: switch the logged-in user's Academic Year.
 */
$topbarIsDirectRequest = false;
$topbarScriptFile = (string)($_SERVER['SCRIPT_FILENAME'] ?? '');
if ($topbarScriptFile !== '') {
    $topbarRealScript = realpath($topbarScriptFile);
    $topbarRealSelf = realpath(__FILE__);
    $topbarIsDirectRequest = $topbarRealScript !== false
        && $topbarRealSelf !== false
        && $topbarRealScript === $topbarRealSelf;
}

if ($topbarIsDirectRequest) {
    ob_start();
    require_once __DIR__ . '/bootstrap.php';
    require_once __DIR__ . '/layout_helpers.php';

    $topbarJson = static function (
        bool $success,
        string $message,
        array $data = [],
        int $status = 200
    ): never {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        echo json_encode([
            'success' => $success,
            'message' => $message,
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    };

    if (function_exists('require_login')) {
        require_login();
    } elseif (empty($_SESSION['user_id'])) {
        $topbarJson(false, 'Your login session has expired. Please sign in again.', [], 401);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $topbarJson(false, 'Invalid request method.', [], 405);
    }

    $topbarInput = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($topbarInput)) {
        $topbarInput = $_POST;
    }

    if (($topbarInput['action'] ?? '') !== 'switch_academic_year') {
        $topbarJson(false, 'Invalid Topbar action.', [], 400);
    }

    $topbarCsrf = (string)($topbarInput['csrf_token'] ?? '');
    if (function_exists('csrf_is_valid') && !csrf_is_valid($topbarCsrf)) {
        $topbarJson(false, 'Session expired. Refresh the page and try again.', [], 419);
    }

    $topbarYearId = (int)($topbarInput['academic_year_id'] ?? 0);
    if ($topbarYearId <= 0) {
        $topbarJson(false, 'Select a valid Academic Year.', [], 422);
    }

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        $topbarJson(false, 'Database connection unavailable.', [], 500);
    }

    try {
        $topbarTenantId = (int)($_SESSION['tenant_id'] ?? $_SESSION['school_id'] ?? 0);
        $topbarUserId = (int)($_SESSION['user_id'] ?? 0);

        if (function_exists('current_user')) {
            $topbarCurrentUser = current_user();
            if (is_array($topbarCurrentUser)) {
                $topbarTenantId = (int)($topbarCurrentUser['tenant_id'] ?? $topbarTenantId);
                $topbarUserId = (int)($topbarCurrentUser['id'] ?? $topbarCurrentUser['user_id'] ?? $topbarUserId);
            }
        }

        if ($topbarTenantId <= 0) {
            $topbarJson(false, 'School context is unavailable.', [], 422);
        }

        $topbarHasTable = static function (PDO $db, string $table): bool {
            if (function_exists('school_table_exists')) {
                return school_table_exists($db, $table);
            }
            $stmt = $db->prepare(
                'SELECT COUNT(*) FROM information_schema.tables '
                . 'WHERE table_schema=DATABASE() AND table_name=?'
            );
            $stmt->execute([$table]);
            return (int)$stmt->fetchColumn() > 0;
        };

        if (!$topbarHasTable($pdo, 'academic_years')) {
            $topbarJson(false, 'Academic Years table is unavailable.', [], 500);
        }

        $topbarSql = "SELECT ay.id, ay.year_name, ay.is_current
                      FROM academic_years ay";
        $topbarParams = [
            'academic_year_id' => $topbarYearId,
            'tenant_id' => $topbarTenantId,
        ];

        $topbarHasUserAccess = $topbarHasTable($pdo, 'user_academic_year_access');
        if ($topbarHasUserAccess && $topbarUserId > 0) {
            $topbarSql .= " LEFT JOIN user_academic_year_access uaya
                                ON uaya.academic_year_id=ay.id
                               AND uaya.user_id=:user_id";
            $topbarParams['user_id'] = $topbarUserId;
        }

        $topbarSql .= " WHERE ay.id=:academic_year_id
                          AND ay.tenant_id=:tenant_id
                          AND ay.status='active'";

        if ($topbarHasUserAccess && $topbarUserId > 0) {
            $topbarSql .= ' AND COALESCE(uaya.can_access,1)=1';
        }

        $topbarSql .= ' LIMIT 1';
        $topbarStmt = $pdo->prepare($topbarSql);
        $topbarStmt->execute($topbarParams);
        $topbarYear = $topbarStmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($topbarYear)) {
            $topbarJson(
                false,
                'This Academic Year is unavailable or you do not have access to it.',
                [],
                403
            );
        }

        $_SESSION['academic_year_id'] = (int)$topbarYear['id'];
        $_SESSION['academic_year_name'] = (string)$topbarYear['year_name'];

        $topbarJson(true, 'Academic Year changed successfully.', [
            'academic_year_id' => (int)$topbarYear['id'],
            'year_name' => (string)$topbarYear['year_name'],
            'is_current' => (int)($topbarYear['is_current'] ?? 0),
        ]);
    } catch (Throwable $topbarException) {
        error_log('includes/topbar.php academic-year switch: ' . $topbarException->getMessage());
        $topbarJson(false, 'Unable to change Academic Year.', [], 500);
    }
}

require_once __DIR__ . '/layout_helpers.php';

$user = current_user();
$school = current_tenant_branding();
$academicYears = get_accessible_academic_years();
$currentAcademicYear = current_academic_year();

$userName = (string)($user['name'] ?? 'John Admin');
$roleName = (string)($user['role_name'] ?? 'Super Administrator');
$photoPath = trim((string)($user['photo_path'] ?? ''));
$notificationCount = max(0, (int)($user['notification_count'] ?? 0));
$messageCount = max(0, (int)($user['message_count'] ?? 0));
$currentAcademicYearId = (int)($currentAcademicYear['id'] ?? 0);
$currentAcademicYearName = trim((string)($currentAcademicYear['year_name'] ?? ''));

if (!function_exists('topbarPlatformGeneralSettings')) {
    function topbarPlatformGeneralSettings(
        PDO $pdo
    ): array {
        $defaults = [
            'topbar_brand_name' => 'School ERP',
            'topbar_brand_type' => 'icon',
            'topbar_brand_icon' => 'school',
            'topbar_brand_image_path' => '',
            'show_notifications' => 1,
            'show_messages' => 1,
        ];

        try {
            $hasTable = function_exists(
                'school_table_exists'
            )
                ? school_table_exists(
                    $pdo,
                    'platform_general_settings'
                )
                : false;

            if (!$hasTable) {
                return $defaults;
            }

            $statement = $pdo->query(
                "SELECT
                    topbar_brand_name,
                    topbar_brand_type,
                    topbar_brand_icon,
                    topbar_brand_image_path,
                    show_notifications,
                    show_messages
                 FROM platform_general_settings
                 WHERE id = 1
                 LIMIT 1"
            );

            $row = $statement->fetch(
                PDO::FETCH_ASSOC
            );

            if (!is_array($row)) {
                return $defaults;
            }

            return array_merge(
                $defaults,
                $row
            );
        } catch (Throwable $exception) {
            error_log(
                'includes/topbar.php platform settings: '
                . $exception->getMessage()
            );

            return $defaults;
        }
    }
}

$topbarPlatformSettings =
    isset($pdo)
    && $pdo instanceof PDO
        ? topbarPlatformGeneralSettings(
            $pdo
        )
        : [
            'topbar_brand_name' => 'School ERP',
            'topbar_brand_type' => 'icon',
            'topbar_brand_icon' => 'school',
            'topbar_brand_image_path' => '',
            'show_notifications' => 1,
            'show_messages' => 1,
        ];

$topbarShowNotifications =
    (int)(
        $topbarPlatformSettings[
            'show_notifications'
        ]
        ?? 1
    ) === 1;

$topbarShowMessages =
    (int)(
        $topbarPlatformSettings[
            'show_messages'
        ]
        ?? 1
    ) === 1;

/*
 * Resolve Topbar links from the project base so this same include works
 * correctly from every School ERP page and nested directory.
 */
$topbarBaseUrl = defined('BASE_URL')
    ? rtrim((string)BASE_URL, '/') . '/'
    : '';

$topbarHref = static function (
    string $path
) use ($topbarBaseUrl): string {
    $path = ltrim($path, '/');

    if (function_exists('school_sidebar_href')) {
        return school_sidebar_href(
            $path,
            $topbarBaseUrl
        );
    }

    return $topbarBaseUrl . $path;
};

$photoUrl = $photoPath !== ''
    ? $topbarHref($photoPath)
    : '';

/*
 * Profile is a School portal page. Calling school_sidebar_href() keeps the
 * route correct for localhost /git/schoolerp and live /school-erp installs.
 */
$profileUrl = $topbarHref('school/profile.php');
$academicYearSwitchUrl = $topbarBaseUrl . 'includes/topbar.php';
$topbarCsrfToken = function_exists('csrfToken') ? csrfToken() : '';
?>

<header id="topbar">
    <div class="topbar-left">
        <button class="icon-button" id="sidebarToggle" type="button" aria-label="Toggle sidebar">
            <i data-lucide="menu"></i>
        </button>

        <form class="global-search" action="<?= e($topbarHref('global-search.php')) ?>" method="get">
            <input name="q" type="search"
                   placeholder="<?= e($school['search_placeholder'] ?? 'Search students, classes, teachers...') ?>">
            <button type="submit" aria-label="Search">
                <i data-lucide="search"></i>
            </button>
        </form>
    </div>

    <div class="topbar-actions">
        <div class="dropdown">
            <button class="academic-year" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span>
                    <small>Academic Year</small>
                    <strong id="topbarAcademicYearLabel"><?= e($currentAcademicYearName !== '' ? $currentAcademicYearName : 'Select Year') ?></strong>
                </span>
                <i data-lucide="chevron-down"></i>
            </button>

            <div class="dropdown-menu dropdown-menu-end">
                <?php if (!empty($academicYears)): ?>
                    <?php foreach ((is_array($academicYears) ? $academicYears : []) as $year): ?>
                        <?php
                        if (!is_array($year)) continue;
                        $yearId = (int)($year['id'] ?? 0);
                        $yearName = trim((string)($year['year_name'] ?? ''));
                        if ($yearId <= 0 || $yearName === '') continue;
                        $selected = $yearId === $currentAcademicYearId;
                        ?>
                        <button class="dropdown-item js-academic-year<?= $selected ? ' active' : '' ?>"
                                data-year-id="<?= $yearId ?>"
                                data-year-name="<?= e($yearName) ?>"
                                type="button"
                                <?= $selected ? 'aria-current="true"' : '' ?>>
                            <?= e($yearName) ?>
                            <?php if ($selected): ?>
                                <span class="ms-2">✓</span>
                            <?php endif; ?>
                        </button>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span class="dropdown-item-text text-muted">No active academic years</span>
                <?php endif; ?>
            </div>
        </div>

        <a class="icon-button topbar-calendar" href="<?= e($topbarHref('academic-calendar.php')) ?>" aria-label="Academic calendar">
            <i data-lucide="calendar-days"></i>
        </a>

        <?php if ($topbarShowNotifications): ?>
        <div class="dropdown">
            <button class="icon-button notification" type="button" data-bs-toggle="dropdown" aria-label="Notifications">
                <i data-lucide="bell"></i>
                <?php if ($notificationCount > 0): ?>
                    <b><?= min(99, $notificationCount) ?></b>
                <?php endif; ?>
            </button>
            <div class="dropdown-menu dropdown-menu-end topbar-panel">
                <h6>Notifications</h6>
                <p class="small text-muted mb-0">No new notifications.</p>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($topbarShowMessages): ?>
        <div class="dropdown">
            <button class="icon-button notification" type="button" data-bs-toggle="dropdown" aria-label="Messages">
                <i data-lucide="mail"></i>
                <?php if ($messageCount > 0): ?>
                    <b><?= min(99, $messageCount) ?></b>
                <?php endif; ?>
            </button>
            <div class="dropdown-menu dropdown-menu-end topbar-panel">
                <h6>Messages</h6>
                <p class="small text-muted mb-0">No unread messages.</p>
            </div>
        </div>
        <?php endif; ?>

        <div class="dropdown">
            <button class="user-chip" type="button" data-bs-toggle="dropdown">
                <span class="avatar">
                    <?php if ($photoPath !== '' && is_file(dirname(__DIR__) . '/' . ltrim($photoPath, '/'))): ?>
                        <img src="<?= e($photoUrl) ?>" alt="<?= e($userName) ?>">
                    <?php else: ?>
                        <?= e(user_initials($userName)) ?>
                    <?php endif; ?>
                </span>

                <span class="user-chip-copy">
                    <strong><?= e($userName) ?></strong>
                    <small><?= e($roleName) ?></small>
                </span>

                <i data-lucide="chevron-down"></i>
            </button>

            <div class="dropdown-menu dropdown-menu-end">
                <a class="dropdown-item" href="<?= e($profileUrl) ?>">
                    <i data-lucide="user-round"></i> My Profile
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item text-danger" href="<?= e($topbarHref('logout.php')) ?>">
                    <i data-lucide="log-out"></i> Logout
                </a>
            </div>
        </div>
    </div>
</header>

<script>
(() => {
    const buttons = [...document.querySelectorAll('.js-academic-year')];
    if (!buttons.length) return;

    const endpoint = <?= json_encode($academicYearSwitchUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const csrfToken = <?= json_encode($topbarCsrfToken, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const label = document.getElementById('topbarAcademicYearLabel');
    let changing = false;

    function toast(type, text) {
        if (typeof window.showToast === 'function') {
            window.showToast(type, text);
            return;
        }
        if (window.SchoolToast && typeof window.SchoolToast[type] === 'function') {
            window.SchoolToast[type](text);
            return;
        }
        if (typeof window.schoolToast === 'function') {
            window.schoolToast(type, text);
        }
    }

    async function switchAcademicYear(button) {
        if (changing) return;

        const yearId = Number(button.dataset.yearId || 0);
        const yearName = String(button.dataset.yearName || '').trim();
        if (yearId <= 0) return;
        if (button.classList.contains('active')) return;

        changing = true;
        buttons.forEach(item => item.disabled = true);
        const oldLabel = label?.textContent || '';
        if (label) label.textContent = 'Changing...';

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'switch_academic_year',
                    academic_year_id: yearId,
                    csrf_token: csrfToken
                })
            });

            const text = await response.text();
            let result;
            try {
                result = JSON.parse(text);
            } catch (_) {
                throw new Error('Academic Year change returned an invalid server response.');
            }

            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Unable to change Academic Year.');
            }

            const selectedName = String(result.data?.year_name || yearName || 'Academic Year');
            if (label) label.textContent = selectedName;
            toast('success', `Academic Year changed to ${selectedName}.`);

            window.dispatchEvent(new CustomEvent('school:academic-year-changed', {
                detail: {
                    academic_year_id: yearId,
                    year_name: selectedName
                }
            }));

            /*
             * Reload the same page so every module immediately reads the new
             * $_SESSION['academic_year_id'] context. No redirect to another page.
             */
            window.setTimeout(() => window.location.reload(), 450);
        } catch (error) {
            if (label) label.textContent = oldLabel;
            toast('error', error?.message || 'Unable to change Academic Year.');
            changing = false;
            buttons.forEach(item => item.disabled = false);
        }
    }

    buttons.forEach(button => {
        button.addEventListener('click', () => switchAcademicYear(button));
    });
})();
</script>

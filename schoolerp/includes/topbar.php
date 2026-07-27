<?php
declare(strict_types=1);

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
?>
<header id="topbar">
    <div class="topbar-left">
        <button class="icon-button" id="sidebarToggle" type="button" aria-label="Toggle sidebar">
            <i data-lucide="menu"></i>
        </button>

        <form class="global-search" action="global-search.php" method="get">
            <input name="q" type="search"
                   placeholder="<?= e($school['search_placeholder'] ?? 'Search students, classes, teachers...') ?>">
            <button type="submit" aria-label="Search">
                <i data-lucide="search"></i>
            </button>
        </form>
    </div>

    <div class="topbar-actions">
        <div class="dropdown">
            <button class="academic-year" type="button" data-bs-toggle="dropdown">
                <span>
                    <small>Academic Year</small>
                    <strong><?= e($currentAcademicYear['year_name'] ?? '2024 - 2025') ?></strong>
                </span>
                <i data-lucide="chevron-down"></i>
            </button>

            <div class="dropdown-menu dropdown-menu-end">
                <?php foreach ((is_array($academicYears) ? $academicYears : []) as $year): ?>
                    <?php if (!is_array($year)) continue; ?>
                    <button class="dropdown-item js-academic-year"
                            data-year-id="<?= (int)($year['id'] ?? 0) ?>"
                            type="button">
                        <?= e($year['year_name'] ?? '') ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <a class="icon-button topbar-calendar" href="academic-calendar.php" aria-label="Academic calendar">
            <i data-lucide="calendar-days"></i>
        </a>

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

        <div class="dropdown">
            <button class="user-chip" type="button" data-bs-toggle="dropdown">
                <span class="avatar">
                    <?php if ($photoPath !== '' && is_file(dirname(__DIR__) . '/' . ltrim($photoPath, '/'))): ?>
                        <img src="<?= e($photoPath) ?>" alt="">
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
                <a class="dropdown-item" href="profile.php">
                    <i data-lucide="user-round"></i> My Profile
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item text-danger" href="logout.php">
                    <i data-lucide="log-out"></i> Logout
                </a>
            </div>
        </div>
    </div>
</header>

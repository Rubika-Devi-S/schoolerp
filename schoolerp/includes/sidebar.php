<?php
declare(strict_types=1);

require_once __DIR__ . '/layout_helpers.php';

$currentPage = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');
$roleId = (int)($_SESSION['role_id'] ?? 0);
$tenantId = max(1, (int)($_SESSION['tenant_id'] ?? 1));

$fallbackMenus = [
    ['id'=>1,'parent_id'=>null,'menu_key'=>'dashboard','display_title'=>'Dashboard','route'=>'dashboard.php','display_icon'=>'house'],
    ['id'=>2,'parent_id'=>null,'menu_key'=>'students','display_title'=>'Students','route'=>'students.php','display_icon'=>'users'],
    ['id'=>3,'parent_id'=>null,'menu_key'=>'admissions','display_title'=>'Admissions','route'=>'admissions.php','display_icon'=>'briefcase-business'],
    ['id'=>4,'parent_id'=>null,'menu_key'=>'attendance','display_title'=>'Attendance','route'=>'attendance.php','display_icon'=>'calendar-check'],
    ['id'=>5,'parent_id'=>null,'menu_key'=>'teachers','display_title'=>'Teachers','route'=>'teachers.php','display_icon'=>'user-round'],
    ['id'=>6,'parent_id'=>null,'menu_key'=>'classes','display_title'=>'Classes & Sections','route'=>'classes.php','display_icon'=>'layout-grid'],
    ['id'=>7,'parent_id'=>null,'menu_key'=>'timetable','display_title'=>'Timetable','route'=>'timetable.php','display_icon'=>'calendar-days'],
    ['id'=>8,'parent_id'=>null,'menu_key'=>'examinations','display_title'=>'Examinations','route'=>'examinations.php','display_icon'=>'file-check-2'],
    ['id'=>9,'parent_id'=>null,'menu_key'=>'fees','display_title'=>'Fees & Payments','route'=>'fees-payments.php','display_icon'=>'wallet-cards'],
    ['id'=>10,'parent_id'=>null,'menu_key'=>'transport','display_title'=>'Transport','route'=>'transport.php','display_icon'=>'bus-front'],
    ['id'=>11,'parent_id'=>null,'menu_key'=>'library','display_title'=>'Library','route'=>'library.php','display_icon'=>'book-open'],
    ['id'=>12,'parent_id'=>null,'menu_key'=>'hostel','display_title'=>'Hostel','route'=>'hostel.php','display_icon'=>'building-2'],
    ['id'=>13,'parent_id'=>null,'menu_key'=>'payroll','display_title'=>'Payroll','route'=>'payroll.php','display_icon'=>'badge-indian-rupee'],
    ['id'=>14,'parent_id'=>null,'menu_key'=>'inventory','display_title'=>'Inventory','route'=>'inventory.php','display_icon'=>'package-check'],
    ['id'=>15,'parent_id'=>null,'menu_key'=>'communication','display_title'=>'Communication','route'=>'communication.php','display_icon'=>'messages-square'],
    ['id'=>16,'parent_id'=>null,'menu_key'=>'reports','display_title'=>'Reports','route'=>'reports.php','display_icon'=>'chart-no-axes-combined'],
    ['id'=>17,'parent_id'=>null,'menu_key'=>'users','display_title'=>'User Management','route'=>'users.php','display_icon'=>'user-cog'],
    ['id'=>18,'parent_id'=>null,'menu_key'=>'settings','display_title'=>'Settings','route'=>'settings.php','display_icon'=>'settings'],
];

$menus = [];

if (isset($pdo) && $pdo instanceof PDO
    && school_table_exists($pdo, 'sidebar_items')
    && school_table_exists($pdo, 'role_sidebar_permissions')
    && $roleId > 0) {
    try {
        $hasTenantOverrides = school_table_exists($pdo, 'tenant_sidebar_items');

        $selectOverride = $hasTenantOverrides
            ? "COALESCE(tsi.custom_title, si.menu_title) AS display_title,
               COALESCE(tsi.custom_icon, si.icon) AS display_icon"
            : "si.menu_title AS display_title,
               si.icon AS display_icon";

        $joinOverride = $hasTenantOverrides
            ? "LEFT JOIN tenant_sidebar_items tsi
                 ON tsi.sidebar_item_id = si.id
                AND tsi.tenant_id = :tenant_id"
            : "";

        $visibleOverride = $hasTenantOverrides
            ? "AND COALESCE(tsi.is_visible, 1) = 1"
            : "";

        $orderOverride = $hasTenantOverrides
            ? "COALESCE(tsi.display_order, si.display_order)"
            : "si.display_order";

        $sql = "SELECT si.id, si.parent_id, si.menu_key, si.route,
                       {$selectOverride}
                FROM sidebar_items si
                INNER JOIN role_sidebar_permissions rsp
                    ON rsp.sidebar_item_id = si.id
                   AND rsp.role_id = :role_id
                   AND rsp.can_show = 1
                {$joinOverride}
                WHERE si.is_active = 1
                  AND si.show_in_sidebar = 1
                  {$visibleOverride}
                ORDER BY {$orderOverride}, si.id";

        $stmt = $pdo->prepare($sql);
        $params = ['role_id' => $roleId];
        if ($hasTenantOverrides) {
            $params['tenant_id'] = $tenantId;
        }
        $stmt->execute($params);
        $loaded = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (is_array($loaded)) {
            $menus = $loaded;
        }
    } catch (Throwable $e) {
        error_log('sidebar.php: ' . $e->getMessage());
        $menus = [];
    }
}

if (!$menus) {
    $menus = $fallbackMenus;
}

$parents = [];
$orphanChildren = [];

foreach ($menus as $menu) {
    if (!is_array($menu)) {
        continue;
    }

    $menu += [
        'id' => 0,
        'parent_id' => null,
        'menu_key' => '',
        'display_title' => 'Menu',
        'route' => '#',
        'display_icon' => 'circle',
        'children' => [],
    ];

    $parentId = (int)($menu['parent_id'] ?? 0);

    if ($parentId <= 0) {
        $menu['children'] = [];
        $parents[(int)$menu['id']] = $menu;
    } else {
        $orphanChildren[$parentId][] = $menu;
    }
}

foreach ($orphanChildren as $parentId => $items) {
    if (isset($parents[$parentId])) {
        $parents[$parentId]['children'] = is_array($items) ? $items : [];
    }
}

function school_sidebar_active(string $route, string $currentPage): bool
{
    if ($route === '' || $route === '#') {
        return false;
    }
    return basename(parse_url($route, PHP_URL_PATH) ?: '') === $currentPage;
}

$school = current_tenant_branding();

$schoolName = 'VISALI VIDYALAYA MATRIC SCHOOL PENNAGRAM';

$logo = trim(
    (string)($school['logo_path'] ?? '')
);
?>
<div id="sidebarBackdrop" class="sidebar-backdrop"></div>

<aside id="sidebar">
    <?php
$schoolName = 'VISALI VIDYALAYA MATRIC SCHOOL PENNAGRAM';

$logo = trim(
    (string)($school['logo_path'] ?? '')
);

$logoAbsolutePath = dirname(__DIR__)
    . '/'
    . ltrim($logo, '/');

$hasValidLogo = $logo !== ''
    && is_file($logoAbsolutePath);
?>

    <div class="sidebar-brand">
        <a href="dashboard.php" class="brand-link">
            <span class="brand-logo">
                <?php if ($hasValidLogo): ?>
                <img src="<?= e($logo) ?>" alt="<?= e($schoolName) ?>">
                <?php else: ?>
                <span class="brand-monogram">
                    VV
                </span>
                <?php endif; ?>
            </span>

            <span class="brand-copy">
                <strong class="school-name-primary">
                    VISALI VIDYALAYA
                </strong>

            </span>
        </a>

        <button id="sidebarMobileClose" class="sidebar-close d-xl-none" type="button" aria-label="Close sidebar">
            <i data-lucide="x"></i>
        </button>
    </div>
    </a>

    <button id="sidebarMobileClose" class="sidebar-close d-xl-none" type="button" aria-label="Close sidebar">
        <i data-lucide="x"></i>
    </button>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($parents as $menu): ?>
        <?php
            $menuChildren = is_array($menu['children'] ?? null) ? $menu['children'] : [];
            $childActive = false;

            foreach ($menuChildren as $child) {
                if (is_array($child) && school_sidebar_active((string)($child['route'] ?? '#'), $currentPage)) {
                    $childActive = true;
                    break;
                }
            }

            $mainActive = school_sidebar_active((string)($menu['route'] ?? '#'), $currentPage);
            $active = $mainActive || $childActive;
            $hasChildren = count($menuChildren) > 0;
            $collapseId = 'sidebarMenu' . (int)($menu['id'] ?? 0);
            ?>

        <?php if ($hasChildren): ?>
        <button type="button" class="sidebar-link sidebar-parent <?= $active ? 'active' : '' ?>"
            data-bs-toggle="collapse" data-bs-target="#<?= e($collapseId) ?>"
            aria-expanded="<?= $childActive ? 'true' : 'false' ?>">
            <i data-lucide="<?= e($menu['display_icon'] ?? 'circle') ?>"></i>
            <span><?= e($menu['display_title'] ?? 'Menu') ?></span>
            <i class="submenu-chevron" data-lucide="chevron-down"></i>
        </button>

        <div id="<?= e($collapseId) ?>" class="collapse sidebar-submenu <?= $childActive ? 'show' : '' ?>">
            <?php foreach ($menuChildren as $child): ?>
            <?php if (!is_array($child)) continue; ?>
            <a class="sidebar-link sidebar-child <?= school_sidebar_active((string)($child['route'] ?? '#'), $currentPage) ? 'active' : '' ?>"
                href="<?= e($child['route'] ?? '#') ?>">
                <i data-lucide="<?= e($child['display_icon'] ?? 'circle') ?>"></i>
                <span><?= e($child['display_title'] ?? 'Menu') ?></span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <a class="sidebar-link <?= $active ? 'active' : '' ?>" href="<?= e($menu['route'] ?? '#') ?>">
            <i data-lucide="<?= e($menu['display_icon'] ?? 'circle') ?>"></i>
            <span><?= e($menu['display_title'] ?? 'Menu') ?></span>
        </a>
        <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-promo">
        <i data-lucide="<?= e($school['sidebar_footer_icon'] ?? 'graduation-cap') ?>"></i>
        <strong><?= e($school['sidebar_footer_title'] ?? 'Excellence in Education') ?></strong>
        <small><?= e($school['sidebar_footer_text'] ?? '') ?></small>
    </div>
</aside>